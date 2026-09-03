<?php
/**
 * OAuth token endpoint - the only public (non-HMAC) route in the bridge.
 * Server-to-server only: the cloud Worker calls this to exchange an
 * authorization code (minted by the wp-admin consent screen, see
 * Bridgistic\Admin\OAuthAuthorizePage) for a real Bridgistic key.
 *
 * Deliberately does NOT go through Controller::authenticate() - there is no
 * HMAC key yet at this point, that's the whole point of this endpoint. PKCE
 * (verified inside Oauth::redeem_code()) is what makes this safe to expose
 * without a client secret - see the docblock on Bridgistic\Oauth.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Rest;

use Bridgistic\Oauth;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OauthController {

	public function register( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/oauth/token',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'token' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/** Redemption attempts allowed per client IP per minute. */
	private const ATTEMPTS_PER_MINUTE = 10;

	public function token( WP_REST_Request $request ) {
		// This is the one route without HMAC, so it is the one route where an
		// unauthenticated caller can loop. Codes are 256-bit random and
		// single-use, so guessing is not the threat — grinding the endpoint is.
		if ( $this->too_many_attempts() ) {
			return new WP_Error(
				'bridgistic_oauth_rate_limited',
				'Too many token requests. Try again in a minute.',
				array( 'status' => 429 )
			);
		}

		$grant_type = (string) $request->get_param( 'grant_type' );
		if ( 'authorization_code' !== $grant_type ) {
			return new WP_Error( 'bridgistic_oauth_grant_type', 'Only grant_type=authorization_code is supported.', array( 'status' => 400 ) );
		}

		$code          = (string) $request->get_param( 'code' );
		$client_id     = (string) $request->get_param( 'client_id' );
		$redirect_uri  = (string) $request->get_param( 'redirect_uri' );
		$code_verifier = (string) $request->get_param( 'code_verifier' );

		if ( '' === $code || '' === $client_id || '' === $redirect_uri || '' === $code_verifier ) {
			return new WP_Error( 'bridgistic_oauth_params', 'Missing required OAuth parameters.', array( 'status' => 400 ) );
		}

		// Codes are hex; anything else cannot match a code this site issued, and
		// rejecting it here keeps malformed input out of the transient lookup.
		if ( 1 !== preg_match( '/^[0-9a-f]{64}$/', $code ) ) {
			return new WP_Error( 'bridgistic_oauth_grant', 'Authorization code is invalid, expired, or already used.', array( 'status' => 400 ) );
		}

		$result = Oauth::redeem_code( $code, $client_id, $redirect_uri, $code_verifier );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response( $result, 200 );
	}

	/** Fixed-window per-IP counter, stored in a transient. */
	private function too_many_attempts(): bool {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'bridgistic_oauth_rl_' . md5( $ip . '|' . floor( time() / MINUTE_IN_SECONDS ) );

		$count = (int) get_transient( $key );
		if ( $count >= self::ATTEMPTS_PER_MINUTE ) {
			return true;
		}
		set_transient( $key, $count + 1, 2 * MINUTE_IN_SECONDS );
		return false;
	}
}
