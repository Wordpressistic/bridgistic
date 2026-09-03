<?php
/**
 * Admin actions: classic admin-post handlers (ported verbatim from the
 * original Connect screen, same nonces + capability checks) plus the AJAX
 * endpoints used by the Claude Setup, Health, Snapshots, Playbooks and
 * Export screens.
 *
 * Security invariants:
 *  - every handler checks current_user_can( Controller::CAP )
 *  - every handler verifies a nonce
 *  - secrets are returned exactly once, in the response to the request that
 *    created/rotated them — they are never re-readable and never logged.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

use Bridgistic\AuditLog;
use Bridgistic\Oauth;
use Bridgistic\Playbooks;
use Bridgistic\Snapshot;
use Bridgistic\Security\KeyStore;
use Bridgistic\Security\Scopes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Actions {

	private const AJAX_NONCE = 'bridgistic_admin';

	/** Free-version cap on stored snapshots (manual creation is blocked above it). */
	public const SNAPSHOT_LIMIT = 50;

	/** Transient key template for the include-secret export window. */
	private const FRESH_SECRET_TRANSIENT = 'bridgistic_new_key_';

	public function hooks(): void {
		// Classic form handlers (ported from the original admin screen).
		add_action( 'admin_post_bridgistic_create_key', array( $this, 'handle_create_key' ) );
		add_action( 'admin_post_bridgistic_revoke_key', array( $this, 'handle_revoke_key' ) );
		add_action( 'admin_post_bridgistic_enable_key', array( $this, 'handle_enable_key' ) );
		add_action( 'admin_post_bridgistic_delete_key', array( $this, 'handle_delete_key' ) );
		add_action( 'admin_post_bridgistic_decide_approval', array( $this, 'handle_decide_approval' ) );
		add_action( 'admin_post_bridgistic_save_allowlist', array( $this, 'handle_save_allowlist' ) );
		add_action( 'admin_post_bridgistic_schedule_action', array( $this, 'handle_schedule_action' ) );
		add_action( 'admin_post_bridgistic_export_package', array( $this, 'handle_export_package' ) );
		add_action( 'admin_post_bridgistic_oauth_consent', array( $this, 'handle_oauth_consent' ) );

		// AJAX endpoints (admin only; no nopriv variants on purpose).
		add_action( 'wp_ajax_bridgistic_setup_create_key', array( $this, 'ajax_setup_create_key' ) );
		add_action( 'wp_ajax_bridgistic_rotate_key', array( $this, 'ajax_rotate_key' ) );
		add_action( 'wp_ajax_bridgistic_revoke_key', array( $this, 'ajax_revoke_key' ) );
		add_action( 'wp_ajax_bridgistic_get_config', array( $this, 'ajax_get_config' ) );
		add_action( 'wp_ajax_bridgistic_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_bridgistic_poll_client_connected', array( $this, 'ajax_poll_client_connected' ) );
		add_action( 'wp_ajax_bridgistic_dashboard_status', array( $this, 'ajax_dashboard_status' ) );
		add_action( 'wp_ajax_bridgistic_run_health', array( $this, 'ajax_run_health' ) );
		add_action( 'wp_ajax_bridgistic_create_snapshot', array( $this, 'ajax_create_snapshot' ) );
		add_action( 'wp_ajax_bridgistic_restore_snapshot', array( $this, 'ajax_restore_snapshot' ) );
		add_action( 'wp_ajax_bridgistic_delete_snapshot', array( $this, 'ajax_delete_snapshot' ) );
		add_action( 'wp_ajax_bridgistic_run_builtin_playbook', array( $this, 'ajax_run_builtin_playbook' ) );
		add_action( 'wp_ajax_bridgistic_run_saved_playbook', array( $this, 'ajax_run_saved_playbook' ) );
	}

	// ---- shared guards --------------------------------------------------------

	private function guard_post( string $nonce_action ): void {
		if ( ! current_user_can( Controller::CAP ) || ! check_admin_referer( $nonce_action ) ) {
			wp_die( esc_html__( 'Not allowed.', 'bridgistic' ) );
		}
	}

	private function guard_ajax(): void {
		if ( ! current_user_can( Controller::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'bridgistic' ) ), 403 );
		}
		check_ajax_referer( self::AJAX_NONCE, 'nonce' );
	}

	// ---- classic form handlers (ported) ----------------------------------------

	public function handle_create_key(): void {
		$this->guard_post( 'bridgistic_create_key' );

		$label   = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : 'MCP Key';
		$scopes  = isset( $_POST['scopes'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['scopes'] ) ) : array();
		$rate    = isset( $_POST['rate_limit'] ) ? (int) $_POST['rate_limit'] : 120;
		$ips     = isset( $_POST['ip_allowlist'] ) ? array_filter( array_map( 'trim', explode( "\n", sanitize_textarea_field( wp_unslash( $_POST['ip_allowlist'] ) ) ) ) ) : array();
		$approve = ! empty( $_POST['require_approval'] );

		$created = KeyStore::create( $label, Scopes::sanitize( $scopes ), $ips, $rate, $approve );

		// Show the secret exactly once via a short-lived transient.
		set_transient( self::FRESH_SECRET_TRANSIENT . get_current_user_id(), $created, 120 );

		wp_safe_redirect( admin_url( 'admin.php?page=bridgistic-keys&created=1' ) );
		exit;
	}

	public function handle_revoke_key(): void {
		$this->guard_post( 'bridgistic_revoke_key' );
		$key_id = isset( $_POST['key_id'] ) ? sanitize_text_field( wp_unslash( $_POST['key_id'] ) ) : '';
		if ( $key_id ) {
			KeyStore::set_enabled( $key_id, false );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=bridgistic-keys&revoked=1' ) );
		exit;
	}

	public function handle_enable_key(): void {
		$this->guard_post( 'bridgistic_enable_key' );
		$key_id = isset( $_POST['key_id'] ) ? sanitize_text_field( wp_unslash( $_POST['key_id'] ) ) : '';
		if ( $key_id ) {
			KeyStore::set_enabled( $key_id, true );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=bridgistic-keys&enabled=1' ) );
		exit;
	}

	public function handle_delete_key(): void {
		$this->guard_post( 'bridgistic_delete_key' );
		$key_id = isset( $_POST['key_id'] ) ? sanitize_text_field( wp_unslash( $_POST['key_id'] ) ) : '';
		if ( $key_id ) {
			KeyStore::revoke( $key_id );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=bridgistic-keys&deleted=1' ) );
		exit;
	}

	public function handle_oauth_consent(): void {
		$this->guard_post( 'bridgistic_oauth_consent' );

		$client_id      = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
		$redirect_uri   = isset( $_POST['redirect_uri'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_uri'] ) ) : '';
		$code_challenge = isset( $_POST['code_challenge'] ) ? sanitize_text_field( wp_unslash( $_POST['code_challenge'] ) ) : '';
		$method         = isset( $_POST['code_challenge_method'] ) ? sanitize_text_field( wp_unslash( $_POST['code_challenge_method'] ) ) : '';
		$state          = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '';
		$decision       = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		$preset_id      = isset( $_POST['preset'] ) ? sanitize_key( wp_unslash( $_POST['preset'] ) ) : 'read_only';

		// Re-validate every hidden field independently - a nonce proves this
		// browser session submitted the form, not that the values in it are safe.
		// Anything that fails here would produce a grant that cannot be redeemed,
		// so refusing now is strictly better than redirecting with a doomed code.
		$invalid = '' === $client_id
			|| Oauth::CLIENT_ID !== $client_id
			|| '' === $redirect_uri
			|| ! Oauth::redirect_uri_allowed( $redirect_uri )
			|| ! Oauth::valid_code_challenge( $code_challenge )
			|| Oauth::CODE_CHALLENGE_METHOD !== $method
			|| '' === $state;

		if ( $invalid ) {
			AuditLog::record( 'admin-ui', 'oauth.reject', 'denied', array( 'client_id' => $client_id ), 'Rejected a malformed or unsafe cloud connector authorization request' );
			wp_die( esc_html__( 'Invalid or unsafe OAuth request.', 'bridgistic' ) );
		}

		if ( 'allow' !== $decision ) {
			AuditLog::record( 'admin-ui', 'oauth.deny', 'ok', array(), 'Cloud connector authorization denied from wp-admin' );
			wp_redirect( $redirect_uri . '?' . http_build_query( array( 'error' => 'access_denied', 'state' => $state ) ) ); // phpcs:ignore WordPress.Security.SafeRedirect -- redirect_uri already host-validated by Oauth::redirect_uri_allowed().
			exit;
		}

		// An unknown preset id resolves to read_only, never to something broader.
		$preset = Oauth::preset_or_default( $preset_id );
		$code   = Oauth::issue_code( $redirect_uri, $code_challenge, $preset['scopes'], (bool) $preset['require_approval'] );

		if ( '' === $code ) {
			wp_die( esc_html__( 'Could not issue an authorization code for this request.', 'bridgistic' ) );
		}

		AuditLog::record(
			'admin-ui',
			'oauth.approve',
			'ok',
			array(
				'preset' => Presets::get( $preset_id ) ? $preset_id : 'read_only',
				'scopes' => $preset['scopes'],
			),
			'Cloud connector authorized from wp-admin'
		);
		wp_redirect( $redirect_uri . '?' . http_build_query( array( 'code' => $code, 'state' => $state ) ) ); // phpcs:ignore WordPress.Security.SafeRedirect -- see above.
		exit;
	}

	public function handle_decide_approval(): void {
		$this->guard_post( 'bridgistic_decide_approval' );
		$id      = isset( $_POST['approval_id'] ) ? sanitize_text_field( wp_unslash( $_POST['approval_id'] ) ) : '';
		$approve = isset( $_POST['decision'] ) && 'approve' === $_POST['decision'];
		if ( $id ) {
			\Bridgistic\Approvals::decide( $id, $approve, get_current_user_id() );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=bridgistic-approvals' ) );
		exit;
	}

	public function handle_save_allowlist(): void {
		$this->guard_post( 'bridgistic_save_allowlist' );
		$raw  = isset( $_POST['allowlist'] ) ? sanitize_textarea_field( wp_unslash( $_POST['allowlist'] ) ) : '';
		$list = array_values( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) );
		update_option( 'bridgistic_options_allowlist', $list );
		wp_safe_redirect( admin_url( 'admin.php?page=bridgistic-settings&saved=1' ) );
		exit;
	}

	public function handle_schedule_action(): void {
		$this->guard_post( 'bridgistic_schedule_action' );
		$id  = isset( $_POST['schedule_id'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_id'] ) ) : '';
		$act = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		if ( $id ) {
			switch ( $act ) {
				case 'enable':
					\Bridgistic\Scheduler::toggle( $id, true );
					break;
				case 'disable':
					\Bridgistic\Scheduler::toggle( $id, false );
					break;
				case 'delete':
					\Bridgistic\Scheduler::delete( $id );
					break;
				case 'run':
					$row = \Bridgistic\Scheduler::get( $id );
					if ( $row ) {
						\Bridgistic\Scheduler::execute( $row );
					}
					break;
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=bridgistic-playbooks' ) );
		exit;
	}

	/**
	 * Build and stream the Claude setup package zip.
	 *
	 * The secret is included ONLY when (a) it was created/rotated in the last
	 * two minutes by this same user (short-lived transient) and (b) the user
	 * ticked the explicit include checkbox. Stored secrets are unreadable.
	 */
	public function handle_export_package(): void {
		$this->guard_post( 'bridgistic_export_package' );

		$key_id = isset( $_POST['key_id'] ) ? sanitize_text_field( wp_unslash( $_POST['key_id'] ) ) : '';
		if ( '' === $key_id || ! KeyStore::get( $key_id ) ) {
			wp_die( esc_html__( 'Pick a key first (create one in AI / MCP Connections).', 'bridgistic' ) );
		}

		$include = array(
			'desktop'         => ! empty( $_POST['include_desktop'] ),
			'code'            => ! empty( $_POST['include_code'] ),
			'scripts'         => ! empty( $_POST['include_scripts'] ),
			'troubleshooting' => ! empty( $_POST['include_troubleshooting'] ),
		);

		$secret = null;
		if ( ! empty( $_POST['include_secret'] ) ) {
			$fresh = get_transient( self::FRESH_SECRET_TRANSIENT . get_current_user_id() );
			if ( is_array( $fresh ) && ( $fresh['key_id'] ?? '' ) === $key_id && ! empty( $fresh['secret'] ) ) {
				$secret = (string) $fresh['secret'];
			}
		}

		$tmp = ConfigGenerator::build_package( $include, $key_id, $secret );
		if ( is_wp_error( $tmp ) ) {
			wp_die( esc_html( $tmp->get_error_message() ) );
		}

		ConfigGenerator::mark_generated();
		AuditLog::record( 'admin-ui', 'export.package', 'ok', array( 'with_secret' => (bool) $secret ), 'Setup package exported from wp-admin' );

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="bridgistic-claude-package.zip"' );
		header( 'Content-Length: ' . (string) filesize( $tmp ) );
		readfile( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- streaming a generated temp file.
		unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	// ---- AJAX: keys + configs ---------------------------------------------------

	/**
	 * Claude Setup step 3: mint a key from a permission preset.
	 * Returns the secret ONCE plus ready-made configs with it embedded.
	 */
	public function ajax_setup_create_key(): void {
		$this->guard_ajax();

		$preset_id = isset( $_POST['preset'] ) ? sanitize_key( wp_unslash( $_POST['preset'] ) ) : '';
		$label     = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';

		$preset = Presets::get( $preset_id );
		if ( ! $preset ) {
			wp_send_json_error( array( 'message' => __( 'Unknown permission preset.', 'bridgistic' ) ), 400 );
		}

		$label   = $label ?: sprintf( 'Claude — %s', $preset['label'] );
		$created = KeyStore::create( $label, Scopes::sanitize( $preset['scopes'] ), array(), 120, (bool) $preset['require_approval'] );

		// Same show-once window the classic flow uses; also powers export-with-secret.
		set_transient( self::FRESH_SECRET_TRANSIENT . get_current_user_id(), $created, 120 );

		AuditLog::record( 'admin-ui', 'key.create', 'ok', array( 'preset' => $preset_id ), 'Key created from Claude Setup: ' . $label );

		wp_send_json_success( $this->key_payload( $created['key_id'], $created['secret'], $preset_id, $label ) );
	}

	/** Rotate a key's secret in place (same key id, new secret, shown once). */
	public function ajax_rotate_key(): void {
		$this->guard_ajax();

		$key_id = isset( $_POST['key_id'] ) ? sanitize_text_field( wp_unslash( $_POST['key_id'] ) ) : '';
		$secret = $key_id ? KeyStore::rotate_secret( $key_id ) : null;
		if ( null === $secret ) {
			wp_send_json_error( array( 'message' => __( 'Key not found.', 'bridgistic' ) ), 404 );
		}

		set_transient( self::FRESH_SECRET_TRANSIENT . get_current_user_id(), array( 'key_id' => $key_id, 'secret' => $secret ), 120 );
		AuditLog::record( 'admin-ui', 'key.rotate', 'ok', array(), 'Secret rotated for ' . $key_id );

		$row = KeyStore::get( $key_id );
		wp_send_json_success( $this->key_payload( $key_id, $secret, Presets::match( (array) $row['scopes'] ), (string) $row['label'] ) );
	}

	/** Soft-revoke (disable) a key. */
	public function ajax_revoke_key(): void {
		$this->guard_ajax();

		$key_id = isset( $_POST['key_id'] ) ? sanitize_text_field( wp_unslash( $_POST['key_id'] ) ) : '';
		if ( ! $key_id || ! KeyStore::get( $key_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Key not found.', 'bridgistic' ) ), 404 );
		}
		KeyStore::set_enabled( $key_id, false );
		AuditLog::record( 'admin-ui', 'key.revoke', 'ok', array(), 'Key revoked: ' . $key_id );
		wp_send_json_success( array( 'message' => __( 'Key revoked. Claude can no longer use it.', 'bridgistic' ) ) );
	}

	/** Configs for an existing key — placeholder secret only. */
	public function ajax_get_config(): void {
		$this->guard_ajax();

		$key_id = isset( $_POST['key_id'] ) ? sanitize_text_field( wp_unslash( $_POST['key_id'] ) ) : '';
		if ( ! $key_id || ! KeyStore::get( $key_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Key not found.', 'bridgistic' ) ), 404 );
		}
		ConfigGenerator::mark_generated();
		wp_send_json_success( $this->config_payload( $key_id, null ) );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function key_payload( string $key_id, string $secret, string $preset_id, string $label ): array {
		return array_merge(
			array(
				'keyId'      => $key_id,
				'secret'     => $secret,
				'preset'     => $preset_id,
				'label'      => $label,
				'secretNote' => __( 'Shown once — copy it now. It is stored encrypted and cannot be displayed again.', 'bridgistic' ),
				// Baseline for the step-5 "waiting for your AI client" poll —
				// anything Claude does with this key after this instant counts
				// as a real connection.
				'connectSince' => current_time( 'mysql', true ),
			),
			$this->config_payload( $key_id, $secret )
		);
	}

	/**
	 * @return array<string,array<string,string>|string>
	 */
	private function config_payload( string $key_id, ?string $secret ): array {
		return array(
			'configs' => array(
				'desktop'         => ConfigGenerator::to_json( ConfigGenerator::desktop( $key_id, $secret ) ),
				'code'            => ConfigGenerator::to_json( ConfigGenerator::code( $key_id, $secret ) ),
				'cli'             => ConfigGenerator::code_cli( $key_id, $secret ),
				'extension'       => ConfigGenerator::extension_values( $key_id, $secret ),
				'extensionFields' => array(
					'siteUrl' => home_url(),
					'keyId'   => $key_id,
					'secret'  => $secret ?: ConfigGenerator::SECRET_PLACEHOLDER,
				),
				'codex'           => ConfigGenerator::codex( $key_id, $secret ),
				'gemini'          => ConfigGenerator::to_json( ConfigGenerator::gemini_cli( $key_id, $secret ) ),
			),
		);
	}

	// ---- AJAX: diagnostics --------------------------------------------------------

	/**
	 * Claude Setup step 5 / Health page "Test connection": mint an ephemeral
	 * read-only key, run a signed loopback request, then remove the key.
	 */
	public function ajax_test_connection(): void {
		$this->guard_ajax();

		$result = HealthCheck::run();
		$hmac   = null;
		$rest   = null;
		foreach ( $result['checks'] as $check ) {
			if ( 'hmac' === $check['id'] ) {
				$hmac = $check;
			}
			if ( 'rest_api' === $check['id'] ) {
				$rest = $check;
			}
		}

		$ok = $hmac && 'pass' === $hmac['status'];
		wp_send_json_success(
			array(
				'ok'      => $ok,
				'rest'    => $rest,
				'hmac'    => $hmac,
				'score'   => $result['score'],
				'message' => $ok
					? __( 'Server-side check passed: REST reachable, signed requests authenticate, scopes enforced. This does not yet confirm your AI client is configured — see below.', 'bridgistic' )
					: __( 'The signed self-test did not pass. Open the Health Check page for details and fixes.', 'bridgistic' ),
			)
		);
	}

	/**
	 * Claude Setup step 5: has the key created in this wizard session been
	 * used since it was created? Polled from JS so the wizard can flip to
	 * "connected" the moment the AI client makes its first real request,
	 * instead of the user having to guess and go check the Logs page.
	 */
	public function ajax_poll_client_connected(): void {
		$this->guard_ajax();

		$key_id = isset( $_POST['key_id'] ) ? sanitize_text_field( wp_unslash( $_POST['key_id'] ) ) : '';
		$since  = isset( $_POST['since'] ) ? sanitize_text_field( wp_unslash( $_POST['since'] ) ) : '';

		$row = $key_id ? KeyStore::get( $key_id ) : null;
		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'Key not found.', 'bridgistic' ) ), 404 );
		}

		$last_used_at = (string) ( $row['last_used_at'] ?? '' );
		$since_ts     = $since ? strtotime( $since . ' UTC' ) : false;
		$last_used_ts = $last_used_at ? strtotime( $last_used_at . ' UTC' ) : false;
		$connected    = $since_ts && $last_used_ts && $last_used_ts > $since_ts;

		wp_send_json_success(
			array(
				'connected'  => (bool) $connected,
				'lastUsedAt' => $last_used_at,
			)
		);
	}

	/**
	 * Dashboard: live connection status, polled from JS so "Connected" and
	 * the activity counters update the moment a request actually lands,
	 * instead of only refreshing on a full page reload (previously this was
	 * a value computed once at render time — see docblock on
	 * DashboardPage::live_stats(), which this reuses so both places agree).
	 */
	public function ajax_dashboard_status(): void {
		$this->guard_ajax();

		$stats = DashboardPage::live_stats();

		$status_label = __( 'Not connected', 'bridgistic' );
		$status_kind  = 'muted';
		$status_note  = __( 'Start with Set Up Claude — it takes about two minutes.', 'bridgistic' );
		if ( $stats['connected'] ) {
			$status_label = __( 'Connected', 'bridgistic' );
			$status_kind  = 'pass';
			/* translators: %s: human-readable time since the last request. */
			$status_note = sprintf( __( 'Last MCP request %s', 'bridgistic' ), Page::ago( $stats['last_used'] ) );
		} elseif ( $stats['keys_enabled'] > 0 ) {
			$status_label = __( 'Waiting for first request', 'bridgistic' );
			$status_kind  = 'warn';
			$status_note  = __( 'Keys exist — connect Claude and run a read-only tool.', 'bridgistic' );
		}

		$latest_log_text = '';
		if ( $stats['latest_log'] ) {
			/* translators: 1: action name, 2: human-readable time since. */
			$latest_log_text = sprintf( __( 'Latest: %1$s (%2$s)', 'bridgistic' ), (string) $stats['latest_log']['action'], Page::ago( (string) $stats['latest_log']['created_at'] ) );
		}

		wp_send_json_success(
			array(
				'connected'     => (bool) $stats['connected'],
				'statusLabel'   => $status_label,
				'statusKind'    => $status_kind,
				'statusNote'    => $status_note,
				'keysEnabled'   => (int) $stats['keys_enabled'],
				'keysTotal'     => (int) $stats['keys_total'],
				'auditCount'    => number_format_i18n( (int) $stats['audit_count'] ),
				'latestLogText' => $latest_log_text,
			)
		);
	}

	public function ajax_run_health(): void {
		$this->guard_ajax();
		$result = HealthCheck::run();
		update_option( 'bridgistic_last_health', array( 'score' => $result['score'], 'time' => time() ), false );
		$result['report'] = HealthCheck::debug_report( $result );
		wp_send_json_success( $result );
	}

	// ---- AJAX: snapshots ------------------------------------------------------------

	public function ajax_create_snapshot(): void {
		$this->guard_ajax();
		global $wpdb;

		if ( ! \Bridgistic\License::gate( 'snapshots_advanced' )
			&& count( Snapshot::list_recent( self::SNAPSHOT_LIMIT + 1 ) ) >= self::SNAPSHOT_LIMIT ) {
			wp_send_json_error(
				array( 'message' => sprintf( __( 'The free version keeps up to %d snapshots. Upgrade to Pro for unlimited snapshot history, or delete old ones to create new ones.', 'bridgistic' ), self::SNAPSHOT_LIMIT ) ),
				400
			);
		}

		$res = Snapshot::create(
			'tables',
			array( 'tables' => array( $wpdb->posts, $wpdb->postmeta ) ),
			__( 'Manual snapshot (posts + postmeta)', 'bridgistic' ),
			'admin-ui'
		);

		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ), 400 );
		}

		AuditLog::record( 'admin-ui', 'snapshot.create', 'ok', array( 'type' => 'tables' ), 'Manual snapshot from wp-admin' );
		wp_send_json_success( array_merge( $res, array( 'message' => __( 'Snapshot created.', 'bridgistic' ) ) ) );
	}

	public function ajax_restore_snapshot(): void {
		$this->guard_ajax();

		$id  = isset( $_POST['snapshot_id'] ) ? sanitize_text_field( wp_unslash( $_POST['snapshot_id'] ) ) : '';
		$res = Snapshot::restore( $id );
		if ( is_wp_error( $res ) ) {
			AuditLog::record( 'admin-ui', 'snapshot.restore', 'error', array( 'snapshot_id' => $id ), $res->get_error_message() );
			wp_send_json_error( array( 'message' => $res->get_error_message() ), 400 );
		}
		AuditLog::record( 'admin-ui', 'snapshot.restore', 'ok', array( 'snapshot_id' => $id ), 'Restored from wp-admin' );
		wp_send_json_success( array( 'message' => __( 'Snapshot restored.', 'bridgistic' ) ) );
	}

	public function ajax_delete_snapshot(): void {
		$this->guard_ajax();

		$id = isset( $_POST['snapshot_id'] ) ? sanitize_text_field( wp_unslash( $_POST['snapshot_id'] ) ) : '';
		if ( '' === $id ) {
			wp_send_json_error( array( 'message' => __( 'Missing snapshot id.', 'bridgistic' ) ), 400 );
		}
		Snapshot::delete( $id );
		wp_send_json_success( array( 'message' => __( 'Snapshot deleted.', 'bridgistic' ) ) );
	}

	// ---- AJAX: playbooks --------------------------------------------------------------

	/**
	 * Built-in manual playbooks. Deliberately conservative: read-only reports
	 * plus one snapshot action. They never touch the Guard/approval pipeline.
	 */
	public function ajax_run_builtin_playbook(): void {
		$this->guard_ajax();
		global $wpdb;

		$slug   = isset( $_POST['playbook'] ) ? sanitize_key( wp_unslash( $_POST['playbook'] ) ) : '';
		$result = null;

		switch ( $slug ) {
			case 'backup_before_edit':
				$res = Snapshot::create( 'tables', array( 'tables' => array( $wpdb->posts, $wpdb->postmeta ) ), __( 'Backup before edit', 'bridgistic' ), 'admin-ui' );
				if ( is_wp_error( $res ) ) {
					wp_send_json_error( array( 'message' => $res->get_error_message() ), 400 );
				}
				$result = sprintf( __( 'Snapshot %1$s created (%2$s).', 'bridgistic' ), $res['snapshot_id'], size_format( (int) $res['byte_size'] ) );
				break;

			case 'site_inspection':
				global $wp_version;
				$plugins = get_option( 'active_plugins', array() );
				$result  = sprintf(
					/* translators: versions + counts summary. */
					__( 'WordPress %1$s, PHP %2$s, theme "%3$s", %4$d active plugins, %5$d Bridgistic keys, HTTPS %6$s.', 'bridgistic' ),
					$wp_version,
					PHP_VERSION,
					wp_get_theme()->get( 'Name' ),
					count( (array) $plugins ),
					count( KeyStore::list_all() ),
					is_ssl() ? __( 'on', 'bridgistic' ) : __( 'off', 'bridgistic' )
				);
				break;

			case 'plugin_audit':
				if ( ! function_exists( 'get_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				$all      = get_plugins();
				$active   = (array) get_option( 'active_plugins', array() );
				$updates  = (array) get_site_transient( 'update_plugins' );
				$pending  = isset( $updates['response'] ) ? count( (array) $updates['response'] ) : ( is_object( $updates ) && isset( $updates->response ) ? count( (array) $updates->response ) : 0 );
				$inactive = count( $all ) - count( $active );
				$result   = sprintf(
					__( '%1$d plugins installed: %2$d active, %3$d inactive, %4$d with updates pending. Consider removing inactive plugins.', 'bridgistic' ),
					count( $all ),
					count( $active ),
					$inactive,
					$pending
				);
				break;

			case 'content_checklist':
				$drafts    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'draft'" ); // phpcs:ignore WordPress.DB
				$trash     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" ); // phpcs:ignore WordPress.DB
				$revisions = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" ); // phpcs:ignore WordPress.DB
				$result    = sprintf(
					__( 'Content review: %1$d drafts, %2$d items in trash, %3$d stored revisions. Ask Claude (Content Manager key) to help you clean these up safely.', 'bridgistic' ),
					$drafts,
					$trash,
					$revisions
				);
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Unknown playbook.', 'bridgistic' ) ), 400 );
		}

		$runs          = (array) get_option( 'bridgistic_builtin_playbook_runs', array() );
		$runs[ $slug ] = time();
		update_option( 'bridgistic_builtin_playbook_runs', $runs, false );

		AuditLog::record( 'admin-ui', 'playbook.builtin', 'ok', array( 'playbook' => $slug ), (string) $result );
		wp_send_json_success( array( 'message' => $result ) );
	}

	/**
	 * Run a saved (agent-created) playbook from the admin, through the same
	 * internal REST pipeline the MCP flow uses — Guard, approvals and
	 * snapshots all still apply.
	 */
	public function ajax_run_saved_playbook(): void {
		$this->guard_ajax();

		$slug    = isset( $_POST['playbook'] ) ? sanitize_title( wp_unslash( $_POST['playbook'] ) ) : '';
		$dry_run = ! empty( $_POST['dry_run'] );

		if ( ! Playbooks::get( $slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Playbook not found.', 'bridgistic' ) ), 404 );
		}

		$ctx = array(
			'key_id' => 'admin-ui',
			'scopes' => array_keys( Scopes::all() ), // The actor is a manage_options admin.
		);

		$res = Playbooks::run( $slug, array(), $ctx, array( 'dry_run' => $dry_run ) );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ), 400 );
		}

		wp_send_json_success( $res );
	}
}
