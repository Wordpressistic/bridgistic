<?php
/**
 * Admin asset loading — Bridgistic screens only, never globally.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Assets {

	public function hooks(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * True only on Bridgistic admin screens (hook suffix carries the page slug).
	 */
	private function is_bridgistic_screen( string $hook_suffix ): bool {
		if ( false !== strpos( $hook_suffix, 'page_bridgistic' ) ) {
			return true;
		}
		return 'toplevel_page_bridgistic' === $hook_suffix;
	}

	public function enqueue( string $hook_suffix ): void {
		if ( ! $this->is_bridgistic_screen( $hook_suffix ) ) {
			return;
		}

		wp_enqueue_style(
			'bridgistic-admin',
			BRIDGISTIC_URL . 'assets/admin/css/bridgistic-admin.css',
			array(),
			BRIDGISTIC_VERSION
		);

		wp_enqueue_script(
			'bridgistic-admin',
			BRIDGISTIC_URL . 'assets/admin/js/bridgistic-admin.js',
			array(),
			BRIDGISTIC_VERSION,
			true
		);

		$slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'bridgistic'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.

		wp_localize_script(
			'bridgistic-admin',
			'bridgisticAdmin',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'adminPost' => admin_url( 'admin-post.php' ),
				'nonce'     => wp_create_nonce( 'bridgistic_admin' ),
				'page'      => $slug,
				'siteUrl'   => home_url(),
				'restNs'    => BRIDGISTIC_REST_NAMESPACE,
				'i18n'      => array(
					'copied'                 => __( 'Copied to clipboard', 'bridgistic' ),
					'copyFailed'             => __( 'Copy failed — select and copy manually', 'bridgistic' ),
					'working'                => __( 'Working…', 'bridgistic' ),
					'error'                  => __( 'Something went wrong. Check the Health page.', 'bridgistic' ),
					'networkError'           => __( 'Could not reach WordPress. Check the network connection, or whether the request was blocked before it reached WordPress.', 'bridgistic' ),
					'timeoutError'           => __( 'The request timed out. The site may be slow, or a proxy or firewall may be holding the connection. Try again, then check Bridgistic → Health Check.', 'bridgistic' ),
					'badResponse'            => __( 'WordPress returned something other than JSON. A security plugin, proxy, or cache may have replaced the response with an HTML page. Check Bridgistic → Health Check.', 'bridgistic' ),
					/* translators: %d is replaced client-side with the HTTP status code — keep the literal "%d" in the translation. */
					'blockedResponse'        => __( 'A security plugin, firewall, or proxy may be blocking this request (got HTTP %d instead of JSON). Check Bridgistic → Health Check.', 'bridgistic' ),
					'httpForbidden'          => __( 'The request was rejected. Likely causes: your login session expired, the security token is stale (reload this page), your account lost the required capability, or a security plugin or firewall blocked the request. Check Bridgistic → Health Check.', 'bridgistic' ),
					'httpNotFound'           => __( 'The endpoint was not found (HTTP 404). Likely causes: the plugin was deactivated mid-session, permalinks need re-saving, or the REST API is disabled. Check Bridgistic → Health Check.', 'bridgistic' ),
					'httpRateLimited'        => __( 'Too many requests (HTTP 429). Something is rate limiting this site — wait a minute and try again.', 'bridgistic' ),
					/* translators: %d is replaced client-side with the HTTP status code — keep the literal "%d" in the translation. */
					'httpServerError'        => __( 'The server returned an error (HTTP %d). Check your PHP error log, or open Bridgistic → Health Check.', 'bridgistic' ),
					'confirmRevoke'          => __( 'Revoke this key? Claude will immediately lose access.', 'bridgistic' ),
					'confirmDelete'          => __( 'Permanently delete? This cannot be undone.', 'bridgistic' ),
					'confirmRestore'         => __( 'Restoring a snapshot may overwrite current changes. Create a backup first. Continue?', 'bridgistic' ),
					'confirmRotate'          => __( 'Rotate the secret? Existing Claude configs stop working until you paste the new secret.', 'bridgistic' ),
					'confirmDevMode'         => __( 'Developer Mode can access sensitive tools such as database, filesystem, and PHP execution. Use only on sites you control. Continue?', 'bridgistic' ),
					'secretOnce'             => __( 'Shown once — copy it now. It is stored encrypted and cannot be displayed again.', 'bridgistic' ),
					'testRunning'            => __( 'Testing connection…', 'bridgistic' ),
					'healthRunning'          => __( 'Running checks…', 'bridgistic' ),
					'done'                   => __( 'Done', 'bridgistic' ),
					'clientWaiting'          => __( "Waiting for your AI client's first request…", 'bridgistic' ),
					'clientConnected'        => __( 'Connected — a real request just came in from your AI client.', 'bridgistic' ),
					'clientStillWaiting'     => __( "Still waiting. That's normal if you haven't asked your AI assistant to do anything yet.", 'bridgistic' ),
					'checkNow'               => __( 'Check now', 'bridgistic' ),
					'connectedShort'         => __( 'Connected', 'bridgistic' ),
					'notConnectedShort'      => __( 'Not connected', 'bridgistic' ),
					'noActivity'             => __( 'No MCP activity yet.', 'bridgistic' ),
					'dashboardJustConnected' => __( 'Bridgistic just connected — a request came in from your AI client.', 'bridgistic' ),
				),
			)
		);

		// Apply the saved theme before first paint to avoid a flash.
		wp_add_inline_script(
			'bridgistic-admin',
			'try{var t=localStorage.getItem("bridgistic-theme");if(t){document.documentElement.setAttribute("data-bridgistic-theme",t);}}catch(e){}',
			'before'
		);
	}
}
