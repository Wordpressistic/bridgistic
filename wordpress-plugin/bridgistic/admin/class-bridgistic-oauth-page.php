<?php
/**
 * OAuth consent screen — the browser-facing half of the cloud connector
 * handshake. Reached via a deep link from mcp.wpistic.cloud, never from the
 * Bridgistic menu (hidden page, see Controller::menu()).
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

use Bridgistic\Oauth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OAuthAuthorizePage extends Page {

	protected function view(): string {
		return 'oauth-authorize';
	}

	/**
	 * Standalone screen — a consent page shouldn't wear the full
	 * dashboard shell (nav, footer, theme toggle); it needs to look and
	 * feel like a single, focused "allow this app?" prompt.
	 */
	public function render(): void {
		$data = $this->data();
		$view = __DIR__ . '/views/' . $this->view() . '.php';
		include $view;
	}

	protected function data(): array {
		$client_id      = isset( $_GET['client_id'] ) ? sanitize_text_field( wp_unslash( $_GET['client_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, this IS the authorize request.
		$redirect_uri   = isset( $_GET['redirect_uri'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_uri'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code_challenge = isset( $_GET['code_challenge'] ) ? sanitize_text_field( wp_unslash( $_GET['code_challenge'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$method         = isset( $_GET['code_challenge_method'] ) ? sanitize_text_field( wp_unslash( $_GET['code_challenge_method'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$state          = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$error = null;
		if ( Oauth::CLIENT_ID !== $client_id ) {
			$error = __( 'Unknown client. This link did not come from the official Bridgistic cloud connector.', 'bridgistic' );
		} elseif ( ! $redirect_uri || ! Oauth::redirect_uri_allowed( $redirect_uri ) ) {
			$error = __( 'This request\'s redirect address is not an allowed Bridgistic cloud connector address. Refusing to continue.', 'bridgistic' );
		} elseif ( Oauth::CODE_CHALLENGE_METHOD !== $method || ! Oauth::valid_code_challenge( $code_challenge ) ) {
			$error = __( 'This request is missing a valid security challenge (PKCE S256). Refusing to continue.', 'bridgistic' );
		} elseif ( '' === $state ) {
			$error = __( 'This request is missing its state parameter. Refusing to continue.', 'bridgistic' );
		}

		return array(
			'error'                 => $error,
			'client_id'             => $client_id,
			'redirect_uri'          => $redirect_uri,
			'code_challenge'        => $code_challenge,
			'code_challenge_method' => $method,
			'state'                 => $state,
			'presets'               => Presets::all(),
			'risky_scopes'          => Presets::risky_scopes(),
			'action_url'            => admin_url( 'admin-post.php' ),
		);
	}
}
