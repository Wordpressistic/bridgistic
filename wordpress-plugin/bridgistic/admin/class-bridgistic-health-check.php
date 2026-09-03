<?php
/**
 * Health / debug center: runs every connection-related diagnostic and
 * returns structured results the Health page and AJAX endpoint render.
 *
 * Each check: { id, label, status: pass|warn|fail|info, message, fix }.
 * The debug report is the same data — it never contains secrets.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

use Bridgistic\Security\KeyStore;
use Bridgistic\Security\Scopes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HealthCheck {

	private const MIN_PHP = '8.0';
	private const MIN_WP  = '6.4';

	/**
	 * Run all checks.
	 *
	 * @return array{score:int,checks:array<int,array<string,string>>,checked_at:string}
	 */
	public static function run(): array {
		$checks = array();

		// Loopback probes power several checks; run them once.
		$probe = self::probe_rest();

		$checks[] = self::check_rest_api( $probe );
		$checks[] = self::check_namespace( $probe );
		$checks[] = self::check_waf( $probe );
		$checks[] = self::check_hmac();
		$checks[] = self::check_site_url();
		$checks[] = self::check_ssl();
		$checks[] = self::check_permalinks();
		$checks[] = self::check_php_version();
		$checks[] = self::check_wp_version();
		$checks[] = self::check_uploads_writable();
		$checks[] = self::check_sandbox_protected();
		$checks[] = self::check_table( 'bridgistic_audit', __( 'Audit log table', 'bridgistic' ) );
		$checks[] = self::check_table( 'bridgistic_approvals', __( 'Approval queue', 'bridgistic' ) );
		$checks[] = self::check_table( 'bridgistic_keys', __( 'Key store table', 'bridgistic' ) );
		$checks[] = self::check_table( 'bridgistic_snapshots', __( 'Snapshot storage', 'bridgistic' ) );
		$checks[] = self::check_key_scopes();
		$checks[] = self::check_config_generated();
		$checks[] = self::check_time_drift();
		$checks[] = self::check_admin_ajax();
		$checks[] = self::check_zip_extension();
		$checks[] = self::check_temp_dir();
		$checks[] = self::check_object_cache();
		$checks[] = self::check_cron();
		$checks[] = self::check_outbound_https();
		$checks[] = self::check_cloud_oauth();
		$checks[] = self::check_woocommerce();

		$earned   = 0.0;
		$possible = 0;
		foreach ( $checks as $c ) {
			if ( 'info' === $c['status'] ) {
				continue;
			}
			++$possible;
			if ( 'pass' === $c['status'] ) {
				$earned += 1.0;
			} elseif ( 'warn' === $c['status'] ) {
				$earned += 0.5;
			}
		}

		return array(
			'score'      => $possible ? (int) round( 100 * $earned / $possible ) : 0,
			'checks'     => $checks,
			'checked_at' => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
		);
	}

	/**
	 * Secret-free debug report for support requests.
	 *
	 * @param array<string,mixed> $result Output of run().
	 * @return array<string,mixed>
	 */
	public static function debug_report( array $result ): array {
		global $wp_version;

		// Key metadata only — never key_id, never secret_enc, never the
		// decrypted secret. Scope names are safe and are the single most
		// useful thing when diagnosing a 403.
		$keys = array();
		foreach ( KeyStore::list_all() as $row ) {
			$scopes = json_decode( (string) ( $row['scopes'] ?? '[]' ), true );
			$keys[] = array(
				'enabled'          => (bool) ( $row['enabled'] ?? 0 ),
				'scope_count'      => is_array( $scopes ) ? count( $scopes ) : 0,
				'scopes'           => is_array( $scopes ) ? $scopes : array(),
				'require_approval' => (bool) ( $row['require_approval'] ?? 0 ),
				'created_at'       => (string) ( $row['created_at'] ?? '' ),
				'last_used_at'     => (string) ( $row['last_used_at'] ?? '' ),
			);
		}

		$report = array(
			'generated_at'   => gmdate( 'c' ),
			'plugin'         => 'bridgistic ' . BRIDGISTIC_VERSION,
			'site_url'       => home_url(),
			'wordpress'      => $wp_version,
			'php'            => PHP_VERSION,
			'php_sapi'       => PHP_SAPI,
			'ssl'            => is_ssl(),
			'permalinks'     => (bool) get_option( 'permalink_structure' ),
			'multisite'      => is_multisite(),
			'object_cache'   => wp_using_ext_object_cache(),
			'wp_cron'        => ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ),
			'zip_extension'  => class_exists( '\ZipArchive' ),
			'woocommerce'    => defined( 'WC_VERSION' ) ? WC_VERSION : null,
			'active_plugins' => array_values( (array) get_option( 'active_plugins', array() ) ),
			'theme'          => wp_get_theme()->get( 'Name' ),
			'keys'           => array(
				'count'   => count( $keys ),
				'summary' => $keys,
			),
			'score'          => $result['score'],
			'checks'         => $result['checks'],
		);

		return self::redact( $report );
	}

	/**
	 * Last-line scrub before a report leaves the server.
	 *
	 * Everything assembled above is already an allowlist, so in normal
	 * operation this changes nothing. It exists because a debug report is
	 * pasted into support threads and issue trackers by definition, and the
	 * cost of one future field accidentally carrying a credential is far
	 * higher than the cost of a regex pass. Check messages in particular are
	 * free text that a future check could build from a caught exception.
	 *
	 * @param mixed $value Any part of the report.
	 * @return mixed
	 */
	private static function redact( $value ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $key => $item ) {
				// Drop anything whose *name* suggests a credential, whatever
				// its value looks like.
				if ( is_string( $key ) && preg_match( '/(secret|password|passwd|token|nonce|salt|private[_-]?key|api[_-]?key|authorization|code_verifier)/i', $key ) ) {
					$out[ $key ] = '[redacted]';
					continue;
				}
				$out[ $key ] = self::redact( $item );
			}
			return $out;
		}

		if ( ! is_string( $value ) ) {
			return $value;
		}

		// Bridgistic key ids/secrets, and long hex or base64 blobs that are
		// credential-shaped regardless of where they came from.
		$patterns = array(
			'/wps_[0-9a-f]{8,}/i',
			'/wpk_[0-9a-f]{8,}/i',
			'/\b[0-9a-f]{40,}\b/i',
			'/\bBearer\s+\S+/i',
		);
		return (string) preg_replace( $patterns, '[redacted]', $value );
	}

	// ---- probes -------------------------------------------------------------

	/**
	 * One unsigned loopback request to the namespace root.
	 *
	 * @return array{code:int,body:string,json:mixed,error:string}
	 */
	private static function probe_rest(): array {
		$url = rest_url( BRIDGISTIC_REST_NAMESPACE . '/site-info' );
		$res = wp_remote_get(
			$url,
			array(
				'timeout'   => 10,
				'sslverify' => false, // Loopback self-request; many hosts have certs that don't match internally.
			)
		);

		if ( is_wp_error( $res ) ) {
			return array(
				'code'  => 0,
				'body'  => '',
				'json'  => null,
				'error' => $res->get_error_message(),
			);
		}

		$body = (string) wp_remote_retrieve_body( $res );
		return array(
			'code'  => (int) wp_remote_retrieve_response_code( $res ),
			'body'  => $body,
			'json'  => json_decode( $body, true ),
			'error' => '',
		);
	}

	// ---- individual checks ----------------------------------------------------

	/** @param array<string,mixed> $probe */
	private static function check_rest_api( array $probe ): array {
		if ( $probe['error'] ) {
			return self::result( 'rest_api', __( 'REST API', 'bridgistic' ), 'fail',
				sprintf( __( 'The site cannot reach its own REST API: %s', 'bridgistic' ), $probe['error'] ),
				__( 'Ask your host whether loopback HTTP requests are blocked. Claude connects from outside, so this may still work — test from Claude to confirm.', 'bridgistic' )
			);
		}
		if ( 404 === $probe['code'] && null === $probe['json'] ) {
			return self::result( 'rest_api', __( 'REST API', 'bridgistic' ), 'fail',
				__( 'The REST API returned 404 with no JSON — it looks disabled or rewritten away.', 'bridgistic' ),
				__( 'Enable pretty permalinks (Settings → Permalinks) and make sure no plugin disables the REST API.', 'bridgistic' )
			);
		}
		return self::result( 'rest_api', __( 'REST API', 'bridgistic' ), 'pass',
			__( 'The WordPress REST API responds.', 'bridgistic' ), '' );
	}

	/** @param array<string,mixed> $probe */
	private static function check_namespace( array $probe ): array {
		$registered = in_array( BRIDGISTIC_REST_NAMESPACE, rest_get_server()->get_namespaces(), true );
		if ( ! $registered ) {
			return self::result( 'namespace', __( 'Bridgistic namespace', 'bridgistic' ), 'fail',
				sprintf( __( 'The %s REST namespace is not registered.', 'bridgistic' ), BRIDGISTIC_REST_NAMESPACE ),
				__( 'Deactivate and reactivate the Bridgistic plugin. If it persists, another plugin may be interfering with rest_api_init.', 'bridgistic' )
			);
		}
		// The unsigned probe should get OUR 401, proving routing reaches the plugin.
		$code = is_array( $probe['json'] ) ? (string) ( $probe['json']['code'] ?? '' ) : '';
		if ( 0 === strpos( $code, 'bridgistic_' ) || 200 === $probe['code'] ) {
			return self::result( 'namespace', __( 'Bridgistic namespace', 'bridgistic' ), 'pass',
				__( 'Bridgistic routes are registered and reachable.', 'bridgistic' ), '' );
		}
		return self::result( 'namespace', __( 'Bridgistic namespace', 'bridgistic' ), 'warn',
			__( 'Routes are registered, but the loopback probe did not reach the Bridgistic handler.', 'bridgistic' ),
			__( 'Usually harmless (loopback quirk). If Claude cannot connect, check the WAF result below.', 'bridgistic' )
		);
	}

	/** @param array<string,mixed> $probe */
	private static function check_waf( array $probe ): array {
		$code = is_array( $probe['json'] ) ? (string) ( $probe['json']['code'] ?? '' ) : '';
		if ( 0 === strpos( $code, 'bridgistic_' ) ) {
			return self::result( 'waf', __( 'Security plugin / WAF', 'bridgistic' ), 'pass',
				__( 'Requests reach Bridgistic unmodified — no firewall interference detected.', 'bridgistic' ), '' );
		}
		if ( in_array( $probe['code'], array( 403, 406, 503 ), true ) || ( $probe['code'] >= 400 && null === $probe['json'] && '' !== $probe['body'] ) ) {
			return self::result( 'waf', __( 'Security plugin / WAF', 'bridgistic' ), 'warn',
				sprintf( __( 'A security layer may be intercepting REST requests (HTTP %d, non-Bridgistic response).', 'bridgistic' ), $probe['code'] ),
				__( 'Allowlist the /wp-json/bridgistic/v1/ namespace in your security plugin or CDN firewall, and make sure X-Bridgistic-* headers are not stripped.', 'bridgistic' )
			);
		}
		return self::result( 'waf', __( 'Security plugin / WAF', 'bridgistic' ), 'pass',
			__( 'No firewall interference detected.', 'bridgistic' ), '' );
	}

	/**
	 * Full-pipeline HMAC self-test: mint an ephemeral read-only key, sign a
	 * loopback request exactly like the MCP server does, then delete the key.
	 */
	private static function check_hmac(): array {
		$created = KeyStore::create( 'Health self-test (auto-removed)', array( Scopes::SITE_READ ), array(), 30 );

		try {
			$path      = '/' . BRIDGISTIC_REST_NAMESPACE . '/site-info';
			$timestamp = (string) time();
			$nonce     = bin2hex( random_bytes( 8 ) );
			$canonical = implode( "\n", array( 'GET', $path, $timestamp, $nonce, hash( 'sha256', '' ) ) );
			$signature = hash_hmac( 'sha256', $canonical, $created['secret'] );

			$res = wp_remote_get(
				rest_url( BRIDGISTIC_REST_NAMESPACE . '/site-info' ),
				array(
					'timeout'   => 10,
					'sslverify' => false,
					'headers'   => array(
						'X-Bridgistic-Key'       => $created['key_id'],
						'X-Bridgistic-Timestamp' => $timestamp,
						'X-Bridgistic-Nonce'     => $nonce,
						'X-Bridgistic-Signature' => $signature,
					),
				)
			);
		} finally {
			KeyStore::revoke( $created['key_id'] );
		}

		if ( is_wp_error( $res ) ) {
			return self::result( 'hmac', __( 'HMAC authentication', 'bridgistic' ), 'warn',
				sprintf( __( 'Could not run the loopback self-test: %s', 'bridgistic' ), $res->get_error_message() ),
				__( 'Loopback requests may be blocked on this host. Test from Claude directly — authentication can still work from outside.', 'bridgistic' )
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( 200 === $code ) {
			return self::result( 'hmac', __( 'HMAC authentication', 'bridgistic' ), 'pass',
				__( 'A signed test request authenticated end-to-end (signature, timestamp, nonce, scopes).', 'bridgistic' ), '' );
		}

		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$why  = is_array( $json ) ? (string) ( $json['code'] ?? "HTTP {$code}" ) : "HTTP {$code}";
		return self::result( 'hmac', __( 'HMAC authentication', 'bridgistic' ), 'fail',
			sprintf( __( 'The signed self-test was rejected (%s).', 'bridgistic' ), $why ),
			__( 'If the code is bridgistic_auth_stale, fix server time (see Server time below). Otherwise a proxy may be altering request bodies or stripping X-Bridgistic-* headers.', 'bridgistic' )
		);
	}

	private static function check_site_url(): array {
		$home = home_url();
		if ( ! wp_http_validate_url( $home ) ) {
			return self::result( 'site_url', __( 'Site URL', 'bridgistic' ), 'fail',
				sprintf( __( 'The configured site URL (%s) is not a valid URL.', 'bridgistic' ), $home ),
				__( 'Fix it in Settings → General. Claude configs embed this value.', 'bridgistic' )
			);
		}
		if ( get_option( 'siteurl' ) !== get_option( 'home' ) ) {
			return self::result( 'site_url', __( 'Site URL', 'bridgistic' ), 'warn',
				__( 'WordPress Address and Site Address differ. That is fine, but always use the Site Address in Claude configs.', 'bridgistic' ),
				sprintf( __( 'Use %s in your Claude config.', 'bridgistic' ), $home )
			);
		}
		return self::result( 'site_url', __( 'Site URL', 'bridgistic' ), 'pass',
			sprintf( __( '%s is valid.', 'bridgistic' ), $home ), '' );
	}

	private static function check_ssl(): array {
		if ( 0 === strpos( home_url(), 'https://' ) ) {
			return self::result( 'ssl', __( 'SSL / HTTPS', 'bridgistic' ), 'pass',
				__( 'The site uses HTTPS. Signed requests travel encrypted.', 'bridgistic' ), '' );
		}
		$host     = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$is_local = (bool) preg_match( '/^(localhost|127\.0\.0\.1|.*\.(local|test))$/', $host );
		return self::result( 'ssl', __( 'SSL / HTTPS', 'bridgistic' ), $is_local ? 'warn' : 'fail',
			$is_local
				? __( 'Running on plain HTTP — acceptable for a local development site only.', 'bridgistic' )
				: __( 'The site is served over plain HTTP. Do not connect AI tooling to a production site without HTTPS.', 'bridgistic' ),
			__( 'Install an SSL certificate (most hosts offer free Let\'s Encrypt) and update the site URL to https://.', 'bridgistic' )
		);
	}

	private static function check_permalinks(): array {
		if ( get_option( 'permalink_structure' ) ) {
			return self::result( 'permalinks', __( 'Permalinks', 'bridgistic' ), 'pass',
				__( 'Pretty permalinks are enabled — /wp-json/ routing works.', 'bridgistic' ), '' );
		}
		return self::result( 'permalinks', __( 'Permalinks', 'bridgistic' ), 'warn',
			__( 'Permalinks are set to "Plain". The REST API falls back to ?rest_route=, which some clients and firewalls mishandle.', 'bridgistic' ),
			__( 'Choose any pretty structure under Settings → Permalinks and save.', 'bridgistic' )
		);
	}

	private static function check_php_version(): array {
		$ok = version_compare( PHP_VERSION, self::MIN_PHP, '>=' );
		return self::result( 'php', __( 'PHP version', 'bridgistic' ), $ok ? 'pass' : 'fail',
			sprintf( __( 'Running PHP %1$s (minimum %2$s).', 'bridgistic' ), PHP_VERSION, self::MIN_PHP ),
			$ok ? '' : __( 'Ask your host to switch this site to PHP 8.0 or newer.', 'bridgistic' )
		);
	}

	private static function check_wp_version(): array {
		global $wp_version;
		$ok = version_compare( $wp_version, self::MIN_WP, '>=' );
		return self::result( 'wp', __( 'WordPress version', 'bridgistic' ), $ok ? 'pass' : 'fail',
			sprintf( __( 'Running WordPress %1$s (minimum %2$s).', 'bridgistic' ), $wp_version, self::MIN_WP ),
			$ok ? '' : __( 'Update WordPress from Dashboard → Updates.', 'bridgistic' )
		);
	}

	private static function check_uploads_writable(): array {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || ! wp_is_writable( $uploads['basedir'] ) ) {
			return self::result( 'uploads', __( 'Uploads directory', 'bridgistic' ), 'fail',
				__( 'The uploads directory is not writable — media tools and the sandbox cannot work.', 'bridgistic' ),
				sprintf( __( 'Fix permissions on %s (your host can do this).', 'bridgistic' ), esc_html( (string) $uploads['basedir'] ) )
			);
		}
		return self::result( 'uploads', __( 'Uploads directory', 'bridgistic' ), 'pass',
			__( 'The uploads directory is writable.', 'bridgistic' ), '' );
	}

	private static function check_sandbox_protected(): array {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . BRIDGISTIC_SANDBOX;
		if ( ! is_dir( $dir ) ) {
			return self::result( 'sandbox', __( 'Sandbox directory', 'bridgistic' ), 'warn',
				__( 'The PHP sandbox directory does not exist yet.', 'bridgistic' ),
				__( 'Deactivate and reactivate the plugin to recreate it, or ignore this if you never grant fs:write.', 'bridgistic' )
			);
		}
		if ( ! file_exists( $dir . '/.htaccess' ) || ! file_exists( $dir . '/index.php' ) ) {
			return self::result( 'sandbox', __( 'Sandbox directory', 'bridgistic' ), 'fail',
				__( 'The sandbox exists but its protection files (.htaccess / index.php) are missing.', 'bridgistic' ),
				__( 'Deactivate and reactivate the plugin to restore them. On nginx, also deny direct access to the bridgistic-sandbox folder in your server config.', 'bridgistic' )
			);
		}
		return self::result( 'sandbox', __( 'Sandbox directory', 'bridgistic' ), 'pass',
			__( 'The sandbox directory exists and direct web execution is blocked.', 'bridgistic' ), '' );
	}

	private static function check_table( string $suffix, string $label ): array {
		global $wpdb;
		$table  = $wpdb->prefix . $suffix;
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table; // phpcs:ignore WordPress.DB
		return self::result( $suffix, $label, $exists ? 'pass' : 'fail',
			$exists
				? sprintf( __( 'Table %s exists.', 'bridgistic' ), $table )
				: sprintf( __( 'Table %s is missing.', 'bridgistic' ), $table ),
			$exists ? '' : __( 'Deactivate and reactivate the Bridgistic plugin to recreate its tables.', 'bridgistic' )
		);
	}

	private static function check_key_scopes(): array {
		$keys = KeyStore::list_all();
		if ( ! $keys ) {
			return self::result( 'scopes', __( 'Key scopes', 'bridgistic' ), 'info',
				__( 'No keys yet — create one in Claude Setup.', 'bridgistic' ),
				''
			);
		}
		$valid   = array_keys( Scopes::all() );
		$broken  = array();
		$enabled = 0;
		foreach ( $keys as $k ) {
			$scopes = (array) json_decode( (string) $k['scopes'], true );
			if ( array_diff( $scopes, $valid ) ) {
				$broken[] = (string) $k['key_id'];
			}
			if ( (int) $k['enabled'] === 1 ) {
				++$enabled;
			}
		}
		if ( $broken ) {
			return self::result( 'scopes', __( 'Key scopes', 'bridgistic' ), 'warn',
				sprintf( __( '%d key(s) carry unknown scopes (created by a different version?).', 'bridgistic' ), count( $broken ) ),
				__( 'Rotate or recreate the affected keys from Keys & Scopes.', 'bridgistic' )
			);
		}
		return self::result( 'scopes', __( 'Key scopes', 'bridgistic' ), 'pass',
			sprintf( __( '%1$d key(s), %2$d enabled — all scopes valid.', 'bridgistic' ), count( $keys ), $enabled ), '' );
	}

	private static function check_config_generated(): array {
		$when = (int) get_option( ConfigGenerator::GENERATED_FLAG, 0 );
		if ( $when ) {
			return self::result( 'config', __( 'MCP config generated', 'bridgistic' ), 'pass',
				sprintf( __( 'A Claude config was generated %s ago.', 'bridgistic' ), human_time_diff( $when ) ), '' );
		}
		return self::result( 'config', __( 'MCP config generated', 'bridgistic' ), 'info',
			__( 'No Claude config generated yet.', 'bridgistic' ),
			__( 'Use the Claude Setup page to create a key and generate your config.', 'bridgistic' )
		);
	}

	private static function check_time_drift(): array {
		global $wpdb;
		$db_time  = (int) $wpdb->get_var( 'SELECT UNIX_TIMESTAMP()' ); // phpcs:ignore WordPress.DB
		$php_time = time();
		$drift    = abs( $db_time - $php_time );

		if ( $drift > 30 ) {
			return self::result( 'time', __( 'Server time', 'bridgistic' ), 'warn',
				sprintf( __( 'PHP and database clocks differ by %d seconds — server time may be unreliable.', 'bridgistic' ), $drift ),
				__( 'Signed requests allow ±300s of drift between Claude\'s machine and this server. Ask your host to enable NTP.', 'bridgistic' )
			);
		}
		return self::result( 'time', __( 'Server time', 'bridgistic' ), 'pass',
			__( 'Server clocks agree. Remember the ±300s window also depends on the clock of the computer running Claude.', 'bridgistic' ), '' );
	}

	/**
	 * admin-ajax.php is what every screen in this plugin talks to, and it is
	 * one of the first paths a WAF or security plugin locks down. A loopback
	 * probe here is what turns "the dashboard just spins" into a named cause.
	 */
	private static function check_admin_ajax(): array {
		$res = wp_remote_post(
			admin_url( 'admin-ajax.php' ),
			array(
				'timeout'   => 8,
				'sslverify' => false,
				// heartbeat with no nonce answers 0/-1 rather than 200-with-JSON;
				// either way a *WordPress* answer proves the endpoint is reachable.
				'body'      => array( 'action' => 'heartbeat' ),
			)
		);

		if ( is_wp_error( $res ) ) {
			return self::result( 'admin_ajax', __( 'admin-ajax.php reachable', 'bridgistic' ), 'warn',
				sprintf( __( 'Loopback request to admin-ajax.php failed: %s', 'bridgistic' ), $res->get_error_message() ),
				__( 'The plugin\'s admin screens talk to admin-ajax.php. If a firewall or security plugin blocks it, allowlist it for logged-in administrators.', 'bridgistic' )
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( $code >= 200 && $code < 400 ) {
			return self::result( 'admin_ajax', __( 'admin-ajax.php reachable', 'bridgistic' ), 'pass',
				sprintf( __( 'admin-ajax.php answered with HTTP %d.', 'bridgistic' ), $code ), '' );
		}

		return self::result( 'admin_ajax', __( 'admin-ajax.php reachable', 'bridgistic' ), 'fail',
			sprintf( __( 'admin-ajax.php answered with HTTP %d — the admin screens will not work.', 'bridgistic' ), $code ),
			__( 'A security plugin, firewall, or server rule is blocking admin-ajax.php. Allowlist it for logged-in administrators.', 'bridgistic' )
		);
	}

	private static function check_zip_extension(): array {
		if ( class_exists( '\ZipArchive' ) ) {
			return self::result( 'zip', __( 'PHP zip extension', 'bridgistic' ), 'pass',
				__( 'ZipArchive is available, so setup packages can be built here.', 'bridgistic' ), '' );
		}
		return self::result( 'zip', __( 'PHP zip extension', 'bridgistic' ), 'warn',
			__( 'ZipArchive is not available, so the Export Package screen cannot build a zip.', 'bridgistic' ),
			__( 'Ask your host to enable the PHP zip extension. Everything else works without it — copy configs from the AI / MCP Connections page instead.', 'bridgistic' )
		);
	}

	private static function check_temp_dir(): array {
		$dir = get_temp_dir();
		if ( ! is_dir( $dir ) || ! wp_is_writable( $dir ) ) {
			return self::result( 'temp_dir', __( 'Writable temp directory', 'bridgistic' ), 'warn',
				sprintf( __( 'PHP\'s temp directory (%s) is missing or not writable.', 'bridgistic' ), $dir ),
				__( 'Package export and snapshot creation write temporary files. Ask your host to provide a writable temp directory, or define WP_TEMP_DIR in wp-config.php.', 'bridgistic' )
			);
		}
		return self::result( 'temp_dir', __( 'Writable temp directory', 'bridgistic' ), 'pass',
			sprintf( __( 'Temp directory is writable (%s).', 'bridgistic' ), $dir ), '' );
	}

	/**
	 * Replay protection and one-time secret display both ride on transients.
	 * A persistent object cache that silently drops or shortens them weakens
	 * the nonce store, so this is a real security signal, not trivia.
	 */
	private static function check_object_cache(): array {
		$key   = 'bridgistic_health_probe';
		$value = wp_generate_password( 12, false );
		set_transient( $key, $value, 60 );
		$read = get_transient( $key );
		delete_transient( $key );

		$external = wp_using_ext_object_cache();

		if ( $read !== $value ) {
			return self::result( 'object_cache', __( 'Transient storage', 'bridgistic' ), 'fail',
				__( 'A transient written and read back immediately did not survive. Replay protection depends on transients.', 'bridgistic' ),
				__( 'Check your object cache (Redis / Memcached) configuration, or disable the persistent object cache and retest.', 'bridgistic' )
			);
		}

		if ( $external ) {
			return self::result( 'object_cache', __( 'Transient storage', 'bridgistic' ), 'warn',
				__( 'A persistent object cache is active and transients round-trip correctly.', 'bridgistic' ),
				__( 'Nonce replay protection and one-time secret display rely on transients honouring their expiry. If the cache evicts entries early, replay protection weakens; if it ignores expiry, one-time values live longer than intended.', 'bridgistic' )
			);
		}

		return self::result( 'object_cache', __( 'Transient storage', 'bridgistic' ), 'pass',
			__( 'Transients round-trip correctly through the database.', 'bridgistic' ), '' );
	}

	private static function check_cron(): array {
		$disabled  = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$scheduled = wp_next_scheduled( 'bridgistic_cron_cleanup' );

		if ( $disabled ) {
			return self::result( 'cron', __( 'Scheduled tasks', 'bridgistic' ), 'info',
				__( 'WP-Cron is disabled, which is the recommended production setup.', 'bridgistic' ),
				__( 'Make sure a real system cron calls wp-cron.php, otherwise scheduled playbooks and daily log pruning will not run.', 'bridgistic' )
			);
		}

		if ( ! $scheduled ) {
			return self::result( 'cron', __( 'Scheduled tasks', 'bridgistic' ), 'warn',
				__( 'Bridgistic\'s daily cleanup task is not scheduled.', 'bridgistic' ),
				__( 'Deactivate and reactivate the plugin to re-register it. Without it, audit rows and expired nonces are never pruned.', 'bridgistic' )
			);
		}

		return self::result( 'cron', __( 'Scheduled tasks', 'bridgistic' ), 'pass',
			sprintf( __( 'Daily cleanup is scheduled (next run %s UTC).', 'bridgistic' ), gmdate( 'Y-m-d H:i', (int) $scheduled ) ), '' );
	}

	private static function check_outbound_https(): array {
		$res = wp_remote_get( 'https://api.wordpress.org/core/version-check/1.7/', array( 'timeout' => 8 ) );
		if ( is_wp_error( $res ) ) {
			return self::result( 'outbound_https', __( 'Outbound HTTPS', 'bridgistic' ), 'warn',
				sprintf( __( 'This server could not make an outbound HTTPS request: %s', 'bridgistic' ), $res->get_error_message() ),
				__( 'The local MCP connection does not need outbound access, so this is not fatal. Bridgistic Cloud and plugin updates do.', 'bridgistic' )
			);
		}
		return self::result( 'outbound_https', __( 'Outbound HTTPS', 'bridgistic' ), 'pass',
			__( 'This server can make outbound HTTPS requests.', 'bridgistic' ), '' );
	}

	/**
	 * The cloud connector needs a reachable token endpoint and HTTPS. Both are
	 * checked without contacting the connector, so this stays fast and adds no
	 * outbound dependency for sites that only use the local path.
	 */
	private static function check_cloud_oauth(): array {
		$has_route = (bool) rest_url( BRIDGISTIC_REST_NAMESPACE . '/oauth/token' );

		if ( ! is_ssl() ) {
			return self::result( 'cloud_oauth', __( 'Cloud connector prerequisites', 'bridgistic' ), 'warn',
				__( 'This site is not served over HTTPS, so the cloud connector cannot be used.', 'bridgistic' ),
				__( 'Bridgistic Cloud will only exchange credentials with an https site. The local connection works without HTTPS.', 'bridgistic' )
			);
		}

		if ( ! $has_route ) {
			return self::result( 'cloud_oauth', __( 'Cloud connector prerequisites', 'bridgistic' ), 'fail',
				__( 'The OAuth token route could not be resolved.', 'bridgistic' ),
				__( 'Re-save permalinks (Settings → Permalinks) and retest.', 'bridgistic' )
			);
		}

		return self::result( 'cloud_oauth', __( 'Cloud connector prerequisites', 'bridgistic' ), 'pass',
			__( 'HTTPS is active and the OAuth token route is registered. Bridgistic Cloud is a public beta and has not had an independent security review.', 'bridgistic' ), '' );
	}

	private static function check_woocommerce(): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return self::result( 'woocommerce', __( 'WooCommerce tools', 'bridgistic' ), 'info',
				__( 'WooCommerce is not active, so the store tools are unavailable. Everything else works normally.', 'bridgistic' ), '' );
		}
		if ( ! \Bridgistic\Rest\WooController::is_available() ) {
			return self::result( 'woocommerce', __( 'WooCommerce tools', 'bridgistic' ), 'warn',
				__( 'WooCommerce is present but its data API is not loaded, so the store tools will report as unavailable.', 'bridgistic' ),
				__( 'This usually means WooCommerce failed to boot fully. Check for a plugin conflict or a PHP error in WooCommerce itself.', 'bridgistic' )
			);
		}
		return self::result( 'woocommerce', __( 'WooCommerce tools', 'bridgistic' ), 'pass',
			sprintf( __( 'WooCommerce %s detected — store tools are available to keys holding woo:* scopes.', 'bridgistic' ), defined( 'WC_VERSION' ) ? WC_VERSION : '?' ), '' );
	}

	/**
	 * @return array{id:string,label:string,status:string,message:string,fix:string}
	 */
	private static function result( string $id, string $label, string $status, string $message, string $fix ): array {
		return compact( 'id', 'label', 'status', 'message', 'fix' );
	}
}
