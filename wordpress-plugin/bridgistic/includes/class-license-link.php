<?php
/**
 * License link to the WPistic license server (api.wpistic.com/api/v1/licenses).
 *
 * Restores license activation for the Bridgistic dashboard without changing
 * the free-only feature policy in class-license.php: an active license is
 * recorded and verified here, but no plugin capability is gated on it.
 *
 * Contract (WPistic Control Plane API spec, "Licenses"):
 *   POST /licenses/activate   {key, domain, installation_uuid, site_url, home_url, environment, product_version, wp_version, php_version}
 *   POST /licenses/validate   {activation_token, domain, environment, installation_uuid, plugin_version}
 *   POST /licenses/deactivate {activation_token, installation_uuid}
 * Every status response carries an HMAC-SHA256 signature over canonical JSON,
 * keyed by the verification_key returned once at activation. Unsigned or
 * mis-signed responses are discarded.
 *
 * The raw license key is never stored. The activation token and verification
 * key are stored in a non-autoloaded option and never returned over REST.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LicenseLink {

	public const OPTION       = 'bridgistic_license_link';
	public const UUID_OPTION  = 'wpistic_installation_uuid';
	public const DEFAULT_API  = 'https://api.wpistic.com/api/v1/licenses';

	/** Base URL, overridable for staging with the BRIDGISTIC_LICENSE_API constant. */
	public static function api(): string {
		return defined( 'BRIDGISTIC_LICENSE_API' ) ? rtrim( (string) BRIDGISTIC_LICENSE_API, '/' ) : self::DEFAULT_API;
	}

	/** Stable per-site id shared by WPistic plugins. */
	public static function installation_uuid(): string {
		$id = (string) get_option( self::UUID_OPTION, '' );
		if ( '' === $id ) {
			$id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : bin2hex( random_bytes( 16 ) );
			update_option( self::UUID_OPTION, $id, false );
		}
		return $id;
	}

	public static function domain(): string {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		return strtolower( preg_replace( '/^www\./', '', $host ) ?? $host );
	}

	public static function environment(): string {
		$env = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		return in_array( $env, array( 'local', 'development', 'staging', 'production' ), true ) ? $env : 'production';
	}

	/** Public status shape. Never includes the token, verification key or raw key. */
	public static function status(): array {
		$s    = self::state();
		$resp = (array) ( $s['response'] ?? array() );
		$on   = ! empty( $s['activation_token'] );
		return array(
			'connected'    => $on,
			'status'       => $on ? (string) ( $resp['status'] ?? 'unknown' ) : 'free',
			'plan'         => $on ? (string) ( $resp['plan'] ?? '' ) : 'free',
			'key_mask'     => $on ? (string) ( $s['key_mask'] ?? '' ) : null,
			'expires_at'   => $on ? ( $resp['expires_at'] ?? null ) : null,
			'validated_at' => $on && ! empty( $s['validated_at'] ) ? gmdate( 'c', (int) $s['validated_at'] ) : null,
			'domain'       => $on ? (string) ( $resp['activation']['domain'] ?? self::domain() ) : null,
			'entitlements' => $on ? self::bridgistic_entitlements( (array) ( $resp['entitlements'] ?? array() ) ) : array(),
		);
	}

	/** @return array<string,mixed>|\WP_Error */
	public static function activate( string $raw_key ) {
		$raw_key = trim( $raw_key );
		if ( '' === $raw_key || strlen( $raw_key ) > 200 ) {
			return new \WP_Error( 'bridgistic_license_key', 'Enter a valid license key.', array( 'status' => 400 ) );
		}
		$resp = self::post(
			'/activate',
			array(
				'key'               => $raw_key,
				'domain'            => self::domain(),
				'installation_uuid' => self::installation_uuid(),
				'site_url'          => site_url(),
				'home_url'          => home_url(),
				'environment'       => self::environment(),
				'product_version'   => defined( 'BRIDGISTIC_VERSION' ) ? BRIDGISTIC_VERSION : '',
				'wp_version'        => get_bloginfo( 'version' ),
				'php_version'       => PHP_VERSION,
			),
			array( 'X-Idempotency-Key' => self::installation_uuid() . '-' . md5( $raw_key ) )
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		if ( empty( $resp['activation_token'] ) || empty( $resp['verification_key'] ) || ! self::verify( $resp, (string) $resp['verification_key'] ) ) {
			return new \WP_Error( 'bridgistic_license_unverified', 'The license server response could not be verified.', array( 'status' => 502 ) );
		}
		if ( 'bridgistic' !== (string) ( $resp['product'] ?? '' ) ) {
			return new \WP_Error( 'bridgistic_license_product', 'This license key is not for Bridgistic.', array( 'status' => 400 ) );
		}
		self::store(
			array(
				'activation_token' => (string) $resp['activation_token'],
				'verification_key' => (string) $resp['verification_key'],
				'key_mask'         => self::mask( $raw_key ),
				'response'         => self::strip( $resp ),
				'validated_at'     => time(),
			)
		);
		return self::status();
	}

	/** @return array<string,mixed>|\WP_Error */
	public static function validate() {
		$s = self::state();
		if ( empty( $s['activation_token'] ) ) {
			return new \WP_Error( 'bridgistic_license_none', 'No license is activated on this site.', array( 'status' => 400 ) );
		}
		$resp = self::post(
			'/validate',
			array(
				'activation_token'  => (string) $s['activation_token'],
				'domain'            => self::domain(),
				'environment'       => self::environment(),
				'installation_uuid' => self::installation_uuid(),
				'plugin_version'    => defined( 'BRIDGISTIC_VERSION' ) ? BRIDGISTIC_VERSION : '',
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		if ( ! self::verify( $resp, (string) $s['verification_key'] ) ) {
			return new \WP_Error( 'bridgistic_license_unverified', 'The license server response failed signature verification and was discarded.', array( 'status' => 502 ) );
		}
		$s['response']     = self::strip( $resp );
		$s['validated_at'] = time();
		self::store( $s );
		return self::status();
	}

	/** Local state always clears; a failed remote call can be cleaned up from the WPistic account. */
	public static function deactivate(): array {
		$s = self::state();
		if ( ! empty( $s['activation_token'] ) ) {
			self::post( '/deactivate', array( 'activation_token' => (string) $s['activation_token'], 'installation_uuid' => self::installation_uuid() ) );
		}
		delete_option( self::OPTION );
		return self::status();
	}

	/** HMAC check identical to the WPistic SDK HmacVerifier (canonical JSON, hex). */
	public static function verify( array $response, string $verification_key_hex ): bool {
		if ( empty( $response['signature'] ) || '' === $verification_key_hex || 0 !== strlen( $verification_key_hex ) % 2 || ! ctype_xdigit( $verification_key_hex ) ) {
			return false;
		}
		$sig = strtolower( (string) $response['signature'] );
		unset( $response['signature'], $response['activation_token'], $response['verification_key'] );
		$expected = hash_hmac( 'sha256', self::canonical_json( $response ), (string) hex2bin( $verification_key_hex ) );
		return hash_equals( $expected, $sig );
	}

	/** @param mixed $value */
	public static function canonical_json( $value ): string {
		if ( is_array( $value ) ) {
			$is_list = array() !== $value && array_keys( $value ) === range( 0, count( $value ) - 1 );
			if ( $is_list ) {
				return '[' . implode( ',', array_map( array( self::class, 'canonical_json' ), $value ) ) . ']';
			}
			ksort( $value, SORT_STRING );
			$parts = array();
			foreach ( $value as $k => $v ) {
				$parts[] = self::json_string( (string) $k ) . ':' . self::canonical_json( $v );
			}
			return '{' . implode( ',', $parts ) . '}';
		}
		if ( null === $value ) {
			return 'null';
		}
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) json_encode( $value );
		}
		return self::json_string( (string) $value );
	}

	public static function mask( string $key ): string {
		return strlen( $key ) <= 10 ? str_repeat( '•', 6 ) : substr( $key, 0, 6 ) . '…' . substr( $key, -4 );
	}

	/** Only the bridgistic.* slice of the ecosystem entitlement map. */
	public static function bridgistic_entitlements( array $map ): array {
		$out = array();
		foreach ( $map as $k => $v ) {
			if ( 0 === strpos( (string) $k, 'bridgistic.' ) ) {
				$out[ (string) $k ] = $v;
			}
		}
		return $out;
	}

	private static function json_string( string $value ): string {
		$encoded = json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return is_string( $encoded ) ? $encoded : '""';
	}

	private static function strip( array $resp ): array {
		unset( $resp['activation_token'], $resp['verification_key'] );
		return $resp;
	}

	private static function state(): array {
		$s = get_option( self::OPTION, array() );
		return is_array( $s ) ? $s : array();
	}

	private static function store( array $s ): void {
		update_option( self::OPTION, $s, false );
	}

	/** @return array<string,mixed>|\WP_Error */
	private static function post( string $path, array $body, array $headers = array() ) {
		$res = wp_remote_post(
			self::api() . $path,
			array(
				'timeout'     => 15,
				'redirection' => 0,
				'headers'     => array_merge( array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' ), $headers ),
				'body'        => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return new \WP_Error( 'bridgistic_license_unreachable', 'Could not reach the license server.', array( 'status' => 502 ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $json ) ) {
			$msg = is_array( $json ) && isset( $json['error']['message'] ) ? (string) $json['error']['message'] : 'The license server refused the request.';
			$status = in_array( $code, array( 400, 401, 403, 404, 409, 429 ), true ) ? $code : 502;
			return new \WP_Error( 'bridgistic_license_refused', $msg, array( 'status' => $status ) );
		}
		return $json;
	}
}
