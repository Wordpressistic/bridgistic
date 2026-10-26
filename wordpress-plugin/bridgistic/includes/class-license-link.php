<?php
/**
 * License link to the WPistic license server (api.wpistic.com/api/v1/licenses).
 *
 * Restores license activation for the Bridgistic dashboard without changing
 * the free-only feature policy in class-license.php: an active license is
 * recorded and verified here. License::gate() reads this status. A live
 * activation (active, trialing, or grace) opens the three shipped paid
 * lines. Product lines still marked coming soon remain closed.
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

use Bridgistic\Security\KeyStore;
use Bridgistic\Security\Scopes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LicenseLink {

	public const OPTION       = 'bridgistic_license_link';
	public const UUID_OPTION  = 'wpistic_installation_uuid';
	/**
	 * Bridgistic keys are issued by the Bridgistic console (Paddle purchases
	 * land there), so that is the default activation endpoint. The WPistic
	 * ecosystem server speaks the identical contract — point BRIDGISTIC_LICENSE_API
	 * at https://api.wpistic.com/api/v1/licenses when a deployment is aliased there.
	 */
	public const DEFAULT_API  = 'https://admin.bridgistic.app/api/v1/licenses';

	/** Daily re-validation: keeps the status fresh and re-pushes the dashboard key (1.5.10). */
	public const CRON_HOOK = 'bridgistic_license_validate';

	/** Base URL: BRIDGISTIC_LICENSE_API constant, bridgistic_license_api filter, then the default. */
	public static function api(): string {
		if ( defined( 'BRIDGISTIC_LICENSE_API' ) ) {
			return rtrim( (string) BRIDGISTIC_LICENSE_API, '/' );
		}
		$filtered = apply_filters( 'bridgistic_license_api', self::DEFAULT_API );
		return is_string( $filtered ) && '' !== $filtered ? rtrim( $filtered, '/' ) : self::DEFAULT_API;
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

	/**
	 * The auto-paired dashboard key. Minted once per site at first license
	 * activation and pushed to the license server on every activate/validate,
	 * so the license owner's Bridgistic dashboard connects this site with no
	 * manual key ceremony. Heals itself: if the key is deleted in WP Admin,
	 * the next mint replaces it. The secret lives here (non-autoloaded option)
	 * and at the license server, encrypted at rest — never in a URL.
	 *
	 * @return array{key_id:string,secret:string} Empty when minting is impossible.
	 */
	private static function dashboard_key(): array {
		$s  = self::state();
		$dk = $s['dashboard_key'] ?? array();
		if ( is_array( $dk ) && ! empty( $dk['key_id'] ) && ! empty( $dk['secret'] ) ) {
			return array( 'key_id' => (string) $dk['key_id'], 'secret' => (string) $dk['secret'] );
		}
		if ( ! class_exists( KeyStore::class ) ) {
			return array();
		}
		$scopes = array(
			Scopes::SITE_READ,
			Scopes::POSTS_READ,
			Scopes::POSTS_WRITE,
			Scopes::MEDIA_WRITE,
			Scopes::USERS_READ,
			Scopes::OPTIONS_READ,
			Scopes::OPTIONS_WRITE,
			Scopes::PLUGINS_MANAGE,
			Scopes::DB_READ,
			Scopes::FS_READ,
			Scopes::SNAPSHOT,
			Scopes::MEMORY_READ,
			Scopes::MEMORY_WRITE,
			Scopes::PLAYBOOK_MANAGE,
			Scopes::SCHEDULE_MANAGE,
			Scopes::AUDIT_READ,
			Scopes::LICENSE_MANAGE,
			Scopes::WOO_PRODUCTS_READ,
			Scopes::WOO_ORDERS_READ,
			Scopes::WOO_CUSTOMERS_READ,
			Scopes::WOO_ANALYTICS_READ,
		);
		$created = KeyStore::create( __( 'Bridgistic Dashboard (auto-paired)', 'bridgistic' ), $scopes, array(), 240, true, 'dashboard' );
		if ( empty( $created['key_id'] ) || empty( $created['secret'] ) ) {
			return array();
		}
		$s['dashboard_key'] = array( 'key_id' => (string) $created['key_id'], 'secret' => (string) $created['secret'] );
		self::store( $s );
		return $s['dashboard_key'];
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
			// Masked license-owner email: tells the member which Bridgistic
			// dashboard account unlocks Pro and the marketplace collection.
			'member'       => $on ? (string) ( $resp['member'] ?? '' ) : '',
			'entitlements' => $on ? self::bridgistic_entitlements( (array) ( $resp['entitlements'] ?? array() ) ) : array(),
		);
	}

	/** @return array<string,mixed>|\WP_Error */
	public static function activate( string $raw_key ) {
		$raw_key = trim( $raw_key );
		if ( '' === $raw_key || strlen( $raw_key ) > 200 ) {
			return new \WP_Error( 'bridgistic_license_key', 'Enter a valid license key.', array( 'status' => 400 ) );
		}
		$body = array(
			'key'               => $raw_key,
			'domain'            => self::domain(),
			'installation_uuid' => self::installation_uuid(),
			'site_url'          => site_url(),
			'home_url'          => home_url(),
			'environment'       => self::environment(),
			'product_version'   => defined( 'BRIDGISTIC_VERSION' ) ? BRIDGISTIC_VERSION : '',
			'wp_version'        => get_bloginfo( 'version' ),
			'php_version'       => PHP_VERSION,
		);
		$dashboard_key = self::dashboard_key();
		if ( $dashboard_key ) {
			$body['dashboard_key'] = $dashboard_key;
		}
		$resp = self::post(
			'/activate',
			$body,
			// One key per attempt: the server replays a cached response for a repeated
			// key, so a fixed key would hand back the token revoked by an earlier deactivate.
			array( 'X-Idempotency-Key' => self::installation_uuid() . '-' . md5( $raw_key ) . '-' . ( function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : bin2hex( random_bytes( 16 ) ) ) )
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
		self::schedule();
		return self::status();
	}

	/** @return array<string,mixed>|\WP_Error */
	public static function validate() {
		$s = self::state();
		if ( empty( $s['activation_token'] ) ) {
			return new \WP_Error( 'bridgistic_license_none', 'No license is activated on this site.', array( 'status' => 400 ) );
		}
		$validate_body = array(
			'activation_token'  => (string) $s['activation_token'],
			'domain'            => self::bound( $s, 'domain', self::domain() ),
			'environment'       => self::bound( $s, 'environment', self::environment() ),
			'installation_uuid' => self::installation_uuid(),
			'plugin_version'    => defined( 'BRIDGISTIC_VERSION' ) ? BRIDGISTIC_VERSION : '',
		);
		$dashboard_key = self::dashboard_key();
		if ( $dashboard_key ) {
			$validate_body['dashboard_key'] = $dashboard_key;
		}
		$resp = self::post( '/validate', $validate_body );
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
		self::unschedule();
		return self::status();
	}

	/** Ensure the daily validate event exists while a license is active; drop it otherwise. */
	public static function schedule(): void {
		$s = self::state();
		if ( empty( $s['activation_token'] ) ) {
			self::unschedule();
			return;
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Cron callback. A successful validate refreshes the signed status and
	 * re-sends the dashboard key, so the account dashboard stays in sync.
	 * When the server says the activation no longer exists (seat released from
	 * the account), paid features close; the token is kept so Check now can retry.
	 */
	public static function cron_validate(): void {
		$s = self::state();
		if ( empty( $s['activation_token'] ) ) {
			self::unschedule();
			return;
		}
		$result = self::validate();
		if ( is_wp_error( $result ) ) {
			$data = (array) $result->get_error_data();
			$s    = self::state();
			if ( 404 === (int) ( $data['status'] ?? 0 ) ) {
				$s['response']           = (array) ( $s['response'] ?? array() );
				$s['response']['status'] = 'activation_not_found';
			}
			$s['last_error']    = substr( (string) $result->get_error_message(), 0, 200 );
			$s['last_error_at'] = time();
			self::store( $s );
		}
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

	/**
	 * Domain/environment the server bound the activation to. The license server
	 * re-classifies the environment from the domain (.test/.local/localhost/private
	 * IPs are "local"; staging.*, dev.*, *.wpengine.com, *.kinsta.cloud and
	 * *.pantheonsite.io are "staging") and /validate hard-rejects any mismatch, so
	 * validate must echo what activation returned, not wp_get_environment_type().
	 */
	public static function bound( array $s, string $field, string $fallback ): string {
		$v = $s['response']['activation'][ $field ] ?? '';
		return is_string( $v ) && '' !== $v ? $v : $fallback;
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
