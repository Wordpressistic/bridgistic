<?php
/**
 * A small OAuth 2.1 authorization server for the official cloud connector
 * (mcp.wpistic.cloud). This is intentionally NOT a general-purpose dynamic
 * client registry - there is exactly one recognized client (the cloud
 * connector itself), identified by CLIENT_ID. Everything it issues is a
 * normal Bridgistic key (via KeyStore::create()), so the existing
 * HMAC/Guard/Scopes pipeline is completely unchanged - this class only adds
 * a browser-friendly, no-copy-paste way to mint one.
 *
 * There is deliberately no client secret here. The Worker is a "public
 * client" (RFC 6749 section 2.1) to every WordPress site it has never seen
 * before - there is no way to pre-share a secret with an install it doesn't
 * know about yet, and OAuth 2.1 explicitly designed PKCE to secure exactly
 * this kind of client. The single-use code + PKCE S256 challenge are the
 * real security boundary, not a shared secret.
 *
 * Flow:
 *   1. Cloud Worker redirects the admin's browser here with a PKCE challenge.
 *   2. issue_code() renders a consent screen and, on approval, mints a
 *      single-use authorization code bound to that challenge + the chosen
 *      permission preset.
 *   3. The Worker calls redeem_code() server-to-server with the matching
 *      PKCE verifier and receives a real Bridgistic key.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic;

use Bridgistic\Admin\Presets;
use Bridgistic\Security\KeyStore;
use Bridgistic\Security\Scopes;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Oauth {

	/** The one recognized client. Not a dynamic registry. */
	public const CLIENT_ID = 'bridgistic-cloud';

	/** Hosts an authorize redirect_uri is allowed to point at. */
	private const ALLOWED_REDIRECT_HOSTS = array( 'mcp.wpistic.cloud' );

	/** Authorization codes expire quickly and are single-use. */
	private const CODE_TTL = 300;

	/** The only code_challenge_method OAuth 2.1 permits, and the only one accepted here. */
	public const CODE_CHALLENGE_METHOD = 'S256';

	/**
	 * RFC 7636 section 4.1: a code_verifier is 43-128 characters from the
	 * unreserved set. A base64url SHA-256 challenge is always exactly 43.
	 */
	private const VERIFIER_MIN = 43;
	private const VERIFIER_MAX = 128;
	private const CHALLENGE_LENGTH = 43;

	/**
	 * Validate a redirect_uri against the allowed cloud connector host(s).
	 * https-only; exact host match (no subdomain wildcards).
	 *
	 * Rejects embedded credentials and any explicit port: `https://user:pw@mcp.wpistic.cloud`
	 * and `https://mcp.wpistic.cloud:8443` both carry the allowed host but are
	 * not the connector, and a userinfo segment is a classic way to make a
	 * hostile origin read like an allowed one in a URL bar.
	 */
	public static function redirect_uri_allowed( string $redirect_uri ): bool {
		$parts = wp_parse_url( $redirect_uri );
		if ( ! is_array( $parts ) || ( $parts['scheme'] ?? '' ) !== 'https' ) {
			return false;
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['port'] ) ) {
			return false;
		}
		if ( ! in_array( strtolower( $parts['host'] ?? '' ), self::ALLOWED_REDIRECT_HOSTS, true ) ) {
			return false;
		}
		// The Worker's callback is a fixed path; anything else on the host is
		// not a redirect target this site should hand an authorization code to.
		return '/wp-callback' === ( $parts['path'] ?? '' );
	}

	/**
	 * A well-formed S256 code_challenge: base64url, no padding, exactly the
	 * length of a SHA-256 digest. Rejecting a malformed challenge at authorize
	 * time means redeem_code() can never be handed one that no verifier could
	 * ever match — which would otherwise mint a code that is guaranteed to fail
	 * and leave the user with an unexplained error at the end of the flow.
	 */
	public static function valid_code_challenge( string $challenge ): bool {
		return strlen( $challenge ) === self::CHALLENGE_LENGTH
			&& 1 === preg_match( '/^[A-Za-z0-9_-]+$/', $challenge );
	}

	/** RFC 7636 unreserved-character verifier of a permitted length. */
	public static function valid_code_verifier( string $verifier ): bool {
		$length = strlen( $verifier );
		return $length >= self::VERIFIER_MIN
			&& $length <= self::VERIFIER_MAX
			&& 1 === preg_match( '/^[A-Za-z0-9._~-]+$/', $verifier );
	}

	/**
	 * Step 2: mint a single-use authorization code after the admin approves.
	 *
	 * @param array<int,string> $scopes Scopes granted (from the chosen preset).
	 */
	public static function issue_code( string $redirect_uri, string $code_challenge, array $scopes, bool $require_approval ): string {
		// Callers are expected to have validated already; asserting here keeps
		// the invariant local to the class that depends on it, so no future
		// caller can create a grant whose challenge no verifier can satisfy.
		if ( ! self::redirect_uri_allowed( $redirect_uri ) || ! self::valid_code_challenge( $code_challenge ) ) {
			return '';
		}

		$code      = bin2hex( random_bytes( 32 ) );
		$transient = 'bridgistic_oauth_code_' . hash( 'sha256', $code );

		set_transient(
			$transient,
			array(
				'redirect_uri'     => $redirect_uri,
				'code_challenge'   => $code_challenge,
				'scopes'           => array_values( $scopes ),
				'require_approval' => $require_approval,
				'created_at'       => time(),
			),
			self::CODE_TTL
		);

		return $code;
	}

	/**
	 * Step 3: the Worker exchanges a code (+ PKCE verifier) for a freshly
	 * minted Bridgistic key. Single-use - the transient is deleted whether
	 * or not the exchange succeeds.
	 *
	 * @return array{site_url:string,key_id:string,key_secret:string,scopes:array<int,string>}|WP_Error
	 */
	public static function redeem_code( string $code, string $client_id, string $redirect_uri, string $code_verifier ) {
		if ( self::CLIENT_ID !== $client_id ) {
			return new WP_Error( 'bridgistic_oauth_client', 'Unknown client_id.', array( 'status' => 400 ) );
		}

		// Reject a malformed verifier before touching the stored grant, so a
		// scan of junk verifiers cannot burn legitimate single-use codes.
		if ( ! self::valid_code_verifier( $code_verifier ) ) {
			return new WP_Error(
				'bridgistic_oauth_verifier',
				'code_verifier must be 43-128 unreserved characters (RFC 7636).',
				array( 'status' => 400 )
			);
		}

		$transient = 'bridgistic_oauth_code_' . hash( 'sha256', $code );
		$data      = get_transient( $transient );
		delete_transient( $transient ); // Single-use regardless of outcome.

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'bridgistic_oauth_grant', 'Authorization code is invalid, expired, or already used.', array( 'status' => 400 ) );
		}

		// Belt-and-braces expiry: the transient TTL is the primary control, but
		// a persistent object cache that ignores or rounds TTLs would otherwise
		// silently extend a code's life. The stored timestamp does not depend
		// on the cache backend honouring anything.
		if ( ! isset( $data['created_at'] ) || ( time() - (int) $data['created_at'] ) > self::CODE_TTL ) {
			return new WP_Error( 'bridgistic_oauth_grant', 'Authorization code has expired.', array( 'status' => 400 ) );
		}

		if ( ! hash_equals( (string) $data['redirect_uri'], $redirect_uri ) ) {
			return new WP_Error( 'bridgistic_oauth_grant', 'redirect_uri does not match the authorization request.', array( 'status' => 400 ) );
		}

		// PKCE S256 verification (RFC 7636). Only S256 is accepted.
		$computed = rtrim( strtr( base64_encode( hash( 'sha256', $code_verifier, true ) ), '+/', '-_' ), '=' );
		if ( ! hash_equals( (string) $data['code_challenge'], $computed ) ) {
			return new WP_Error( 'bridgistic_oauth_grant', 'PKCE verification failed.', array( 'status' => 400 ) );
		}

		$scopes  = Scopes::sanitize( (array) $data['scopes'] );
		$created = KeyStore::create(
			'Cloud connector - mcp.wpistic.cloud',
			$scopes,
			array(),
			120,
			(bool) $data['require_approval'],
			'custom',
			0
		);

		return array(
			'site_url'   => home_url(),
			'key_id'     => $created['key_id'],
			'key_secret' => $created['secret'],
			'scopes'     => $scopes,
		);
	}

	/**
	 * Resolve the preset for the consent screen; defaults to read_only if
	 * the requested preset id is unknown so a bad/forged param can't
	 * silently grant more than expected.
	 *
	 * @return array<string,mixed>
	 */
	public static function preset_or_default( string $preset_id ): array {
		return Presets::get_or_safest( $preset_id );
	}
}
