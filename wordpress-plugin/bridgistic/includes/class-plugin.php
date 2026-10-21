<?php
/**
 * Core plugin loader.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic;

use Bridgistic\Security\KeyStore;
use Bridgistic\Rest\Router;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Singleton bootstrap that wires everything together.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	private bool $booted = false;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Hook everything in. Safe to call once.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		// REST API routes — the only entry point for the MCP server.
		add_action(
			'rest_api_init',
			static function (): void {
				( new Router() )->register_routes();
			}
		);

		// Let a browser dashboard (dashboard.bridgistic.app, the legacy
		// app.bridgistic.app, or a local preview) send the signed headers. WordPress core already echoes the Origin; this
		// only adds header names to the preflight allow-list. Auth is still
		// the HMAC signature, never a cookie.
		add_filter(
			'rest_allowed_cors_headers',
			static function ( array $headers ): array {
				return array_merge( $headers, array( 'X-Bridgistic-Key', 'X-Bridgistic-Timestamp', 'X-Bridgistic-Nonce', 'X-Bridgistic-Signature', 'X-Bridgistic-Request-Id' ) );
			}
		);

		// Explicitly allow the hosted dashboard origins on the Bridgistic
		// namespace. Core normally echoes any Origin, but some sites (security
		// or "private API" plugins) strip Access-Control-Allow-Origin from REST
		// responses, which breaks the dashboard's live mode. No credentials are
		// allowed: auth is the HMAC signature, never a cookie.
		add_filter( 'rest_pre_serve_request', array( self::class, 'send_dashboard_cors' ), 99, 3 );

		// Admin UI: dashboard, Claude setup, keys, health, logs, snapshots,
		// playbooks, export, premium overview, settings.
		if ( is_admin() ) {
			require_once BRIDGISTIC_DIR . 'admin/class-bridgistic-admin.php';
			( new Admin\Controller() )->hooks();
		}

		// Daily cleanup of expired nonces + old audit rows.
		add_action( 'bridgistic_cron_cleanup', array( $this, 'cleanup' ) );
		if ( ! wp_next_scheduled( 'bridgistic_cron_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'bridgistic_cron_cleanup' );
		}

		// Scheduled playbooks: register custom cron intervals + the run hook.
		Scheduler::boot();

		// Feature policy. Paid lines stay closed until LicenseLink reports a live key.
		License::init();
	}

	/**
	 * Activation: create custom tables, the sandbox dir, and a site secret.
	 */
	public static function activate(): void {
		require_once BRIDGISTIC_DIR . 'includes/security/class-key-store.php';
		require_once BRIDGISTIC_DIR . 'includes/class-audit-log.php';
		require_once BRIDGISTIC_DIR . 'includes/class-snapshot.php';
		require_once BRIDGISTIC_DIR . 'includes/class-approvals.php';
		require_once BRIDGISTIC_DIR . 'includes/class-usage.php';
		require_once BRIDGISTIC_DIR . 'includes/class-memory.php';
		require_once BRIDGISTIC_DIR . 'includes/class-playbooks.php';
		require_once BRIDGISTIC_DIR . 'includes/class-scheduler.php';

		KeyStore::install();
		AuditLog::install();
		Snapshot::install();
		Approvals::install();
		Usage::install();
		Memory::install();
		Playbooks::install();
		Scheduler::install();
		self::ensure_sandbox();

		// One-time, per-site signing pepper. Never leaves the server.
		if ( ! get_option( 'bridgistic_bridge_pepper' ) ) {
			add_option( 'bridgistic_bridge_pepper', wp_generate_password( 64, true, true ), '', 'no' );
		}

		// Routes the admin straight to Claude Setup on their very next page
		// load — otherwise nothing tells a first-time installer this menu
		// exists. Consumed once by Admin\Controller::maybe_redirect_after_activation().
		set_transient( 'bridgistic_activation_redirect', 1, MINUTE_IN_SECONDS );

		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'bridgistic_cron_cleanup' );
		wp_unschedule_hook( Scheduler::HOOK );
		flush_rewrite_rules();
	}

	/**
	 * Create the sandbox directory under uploads with a hardening .htaccess.
	 */
	public static function ensure_sandbox(): string {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . BRIDGISTIC_SANDBOX;

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		// Block direct web access to anything inside the sandbox. These files
		// are re-asserted on every call (not just created once) so a tampered
		// copy is repaired, and the bridge refuses to write or delete them
		// (FsController). Apache/LiteSpeed read .htaccess, IIS reads
		// web.config; nginx reads neither, which is why every sandbox PHP file
		// also carries an ABSPATH guard (FsController::sandbox_php_guard()).
		$guards = array(
			'.htaccess'  => "Require all denied\n<FilesMatch \".*\">\nRequire all denied\n</FilesMatch>\n<IfModule mod_php.c>\nphp_flag engine off\n</IfModule>\n<IfModule mod_php7.c>\nphp_flag engine off\n</IfModule>\n<IfModule mod_php8.c>\nphp_flag engine off\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization><handlers><clear /></handlers></system.webServer></configuration>\n",
			'index.php'  => "<?php // Silence is golden.\n",
		);
		foreach ( $guards as $name => $body ) {
			$file = $dir . '/' . $name;
			if ( ! file_exists( $file ) || (string) @file_get_contents( $file ) !== $body ) { // phpcs:ignore
				file_put_contents( $file, $body ); // phpcs:ignore
			}
		}

		return $dir;
	}

	/**
	 * Scheduled cleanup.
	 */
	public function cleanup(): void {
		AuditLog::prune();
	}

	/**
	 * Origins of the hosted Bridgistic dashboard.
	 *
	 * @return string[]
	 */
	public static function dashboard_origins(): array {
		$origins = array( 'https://dashboard.bridgistic.app', 'https://app.bridgistic.app' );
		/**
		 * Filter the browser origins allowed to call the Bridgistic REST namespace.
		 *
		 * @param string[] $origins Exact origins (scheme + host [+ port]).
		 */
		if ( function_exists( 'apply_filters' ) ) {
			$origins = (array) apply_filters( 'bridgistic_dashboard_origins', $origins );
		}
		return array_values( array_filter( $origins, 'is_string' ) );
	}

	/**
	 * Whether a request to $route from $origin should get the dashboard CORS header.
	 */
	public static function dashboard_cors_origin( string $route, string $origin ): string {
		if ( '' === $origin || 0 !== strpos( $route, '/bridgistic/v1' ) ) {
			return '';
		}
		return in_array( $origin, self::dashboard_origins(), true ) ? $origin : '';
	}

	/**
	 * @param mixed            $served  Whether the request was already served.
	 * @param mixed            $result  Result.
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function send_dashboard_cors( $served, $result, $request ) {
		if ( headers_sent() || ! $request instanceof \WP_REST_Request ) {
			return $served;
		}
		$origin = self::dashboard_cors_origin( (string) $request->get_route(), (string) get_http_origin() );
		if ( '' !== $origin ) {
			header( 'Access-Control-Allow-Origin: ' . $origin, true );
			header( 'Access-Control-Allow-Headers: Authorization, X-WP-Nonce, Content-Disposition, Content-MD5, Content-Type, X-Bridgistic-Key, X-Bridgistic-Timestamp, X-Bridgistic-Nonce, X-Bridgistic-Signature, X-Bridgistic-Request-Id', true );
			header( 'Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, PATCH, DELETE', true );
			header( 'Vary: Origin', false );
		}
		return $served;
	}
}
