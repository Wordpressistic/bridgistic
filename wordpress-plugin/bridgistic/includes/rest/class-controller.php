<?php
/**
 * Base REST controller — shared auth, scope checks, and response shaping.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Rest;

use Bridgistic\Security\HmacVerifier;
use Bridgistic\AuditLog;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Controller {

	/**
	 * permission_callback: verify the HMAC signature and stash the auth context.
	 *
	 * @return true|WP_Error
	 */
	public function authenticate( WP_REST_Request $request ) {
		// Trusted internal dispatch (playbook steps): a per-run random token,
		// never sent to clients, authorises the already-built __ctx. External
		// requests cannot forge it, so this is safe to short-circuit on.
		$internal = (string) $request->get_param( '__internal_token' );
		if ( '' !== $internal && \Bridgistic\Playbooks::valid_internal_token( $internal ) ) {
			return true;
		}

		// The HMAC covers METHOD, route, timestamp, nonce and the body hash, but
		// not the query string. Unsigned input must not be able to change what
		// a signed request does, so: control params are never accepted from
		// the query string, and mutating methods accept no query params at all
		// (the official clients send everything in the signed JSON body).
		$unsigned = self::unsigned_param_violation( $request->get_method(), (array) $request->get_query_params(), (string) $request->get_route() );
		if ( null !== $unsigned ) {
			return new \WP_Error(
				'bridgistic_unsigned_param',
				$unsigned,
				array( 'status' => 400 )
			);
		}

		$headers = array();
		foreach ( $request->get_headers() as $name => $values ) {
			$headers[ str_replace( '_', '-', strtolower( $name ) ) ] = is_array( $values ) ? ( $values[0] ?? '' ) : $values;
		}

		$ctx = HmacVerifier::authenticate(
			$request->get_method(),
			$request->get_route(),
			$request->get_body(),
			$headers
		);

		if ( is_wp_error( $ctx ) ) {
			return $ctx;
		}

		// Rate-limit + monthly-quota enforcement + metering (one choke point for all routes).
		$metered = \Bridgistic\Usage::guard( $ctx );
		if ( is_wp_error( $metered ) ) {
			return $metered;
		}
		\Bridgistic\Usage::meter_action( (string) $ctx['key_id'], $this->action_name( $request ) );

		// Stash for the handler.
		$request->set_param( '__ctx', $ctx );
		return true;
	}

	/** Params that change Guard/auth behaviour and therefore must be signed. */
	public const SIGNED_ONLY_PARAMS = array( 'dry_run', 'force', 'approval_id', '__ctx', '__internal_token' );

	/** Per-route exceptions for read-only GET lookups (route => params). */
	public const READ_ONLY_QUERY_PARAMS = array(
		'/bridgistic/v1/approvals/status' => array( 'approval_id' ),
	);

	/**
	 * Return an error message when the (unsigned) query string carries input it
	 * must not, or null when it is acceptable.
	 *
	 * @param array<string,mixed> $query Query-string params.
	 */
	public static function unsigned_param_violation( string $method, array $query, string $route = '' ): ?string {
		unset( $query['rest_route'] ); // Plain-permalink routing, part of the path.
		// Read-only lookups that take an ID by query string. Changing the value
		// can only select a different record the same key may already read
		// (the handler enforces ownership), so it cannot change what a signed
		// request is allowed to do. GET cannot carry a signed body.
		if ( 'GET' === strtoupper( $method ) && isset( self::READ_ONLY_QUERY_PARAMS[ $route ] ) ) {
			foreach ( self::READ_ONLY_QUERY_PARAMS[ $route ] as $allowed ) {
				unset( $query[ $allowed ] );
			}
		}
		foreach ( self::SIGNED_ONLY_PARAMS as $name ) {
			if ( array_key_exists( $name, $query ) ) {
				return sprintf( 'Parameter "%s" is not accepted in the query string because it is not covered by the request signature. Send it in the signed JSON body.', $name );
			}
		}
		if ( 'GET' !== strtoupper( $method ) && 'HEAD' !== strtoupper( $method ) && ! empty( $query ) ) {
			return 'Query-string parameters are not accepted on mutating requests because they are not covered by the request signature. Send them in the signed JSON body.';
		}
		return null;
	}

	/**
	 * Enforce a required scope. Returns WP_Error if missing.
	 *
	 * @return true|WP_Error
	 */
	protected function require_scope( WP_REST_Request $request, string $scope ) {
		$ctx    = (array) $request->get_param( '__ctx' );
		$scopes = (array) ( $ctx['scopes'] ?? array() );

		if ( ! in_array( $scope, $scopes, true ) ) {
			AuditLog::record( (string) ( $ctx['key_id'] ?? '' ), $this->action_name( $request ), 'denied', array( 'scope' => $scope ) );
			return new WP_Error(
				'bridgistic_scope_denied',
				sprintf( 'This key lacks the required scope: %s', $scope ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	protected function key_id( WP_REST_Request $request ): string {
		$ctx = (array) $request->get_param( '__ctx' );
		return (string) ( $ctx['key_id'] ?? '' );
	}

	protected function action_name( WP_REST_Request $request ): string {
		return trim( str_replace( '/' . BRIDGISTIC_REST_NAMESPACE, '', $request->get_route() ), '/' );
	}

	/**
	 * Standard success envelope.
	 *
	 * @param array<string,mixed> $data Payload.
	 */
	protected function ok( array $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'ok'   => true,
				'data' => $data,
			),
			$status
		);
	}

	protected function fail( string $code, string $message, int $status = 400 ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
