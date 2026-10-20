<?php
/**
 * Read-only routes for the Bridgistic dashboard (dashboard.bridgistic.app), plus the
 * license link. Everything here is HMAC-authenticated and scoped like every
 * other bridge route.
 *
 *   GET  audit             audit:read      recent audit entries (digests only, never arguments)
 *   GET  audit/export      audit:read      same rows, only with a live license
 *   GET  approvals         site:read       this key's own approval requests (no decisions here)
 *   GET  license           license:manage  license status (never the token or key)
 *   POST license/activate  license:manage
 *   POST license/validate  license:manage
 *   POST license/deactivate license:manage
 *
 * Approval decisions stay in WP Admin on purpose: an API key must never be
 * able to approve its own request.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Rest;

use Bridgistic\Approvals;
use Bridgistic\AuditLog;
use Bridgistic\License;
use Bridgistic\LicenseLink;
use Bridgistic\Security\Scopes;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DashboardController extends Controller {

	public function register( string $namespace ): void {
		$routes = array(
			array( '/audit', 'GET', 'audit' ),
			array( '/audit/export', 'GET', 'audit_export' ),
			array( '/approvals', 'GET', 'approvals' ),
			array( '/license', 'GET', 'license' ),
			array( '/license/activate', 'POST', 'license_activate' ),
			array( '/license/validate', 'POST', 'license_validate' ),
			array( '/license/deactivate', 'POST', 'license_deactivate' ),
		);
		foreach ( $routes as $r ) {
			register_rest_route(
				$namespace,
				$r[0],
				array(
					'methods'             => $r[1],
					'callback'            => array( $this, $r[2] ),
					'permission_callback' => array( $this, 'authenticate' ),
				)
			);
		}
	}

	public function audit( WP_REST_Request $request ) {
		$gate = $this->require_scope( $request, Scopes::AUDIT_READ );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$filter = sanitize_key( (string) ( $request->get_param( 'filter' ) ?: 'all' ) );
		$limit  = max( 1, min( 500, (int) ( $request->get_param( 'limit' ) ?: 200 ) ) );
		$rows   = array_map( array( AuditLog::class, 'public_row' ), AuditLog::query( $filter, $limit ) );
		return $this->ok( array( 'entries' => $rows ) );
	}

	/** Same rows as the on-screen log. Refused until the license link is live. */
	public function audit_export( WP_REST_Request $request ) {
		$gate = $this->require_scope( $request, Scopes::AUDIT_READ );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		if ( ! License::gate( 'audit_export' ) ) {
			return new \WP_Error(
				'bridgistic_audit_export',
				'Audit log export opens when a live Bridgistic license is on this site. The on-screen log stays available.',
				array( 'status' => 402 )
			);
		}
		$rows = array_map( array( AuditLog::class, 'public_row' ), AuditLog::query( 'all', 1000 ) );
		return $this->ok( array( 'format' => 'json', 'entries' => $rows ) );
	}

	public function approvals( WP_REST_Request $request ) {
		$gate = $this->require_scope( $request, Scopes::SITE_READ );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$key  = $this->key_id( $request );
		$rows = array_values( array_filter( Approvals::list_by_status( '', 200 ), static fn( $r ) => (string) ( $r['key_id'] ?? '' ) === $key ) );
		return $this->ok( array( 'approvals' => $rows ) );
	}

	public function license( WP_REST_Request $request ) {
		$gate = $this->require_scope( $request, Scopes::LICENSE_MANAGE );
		return is_wp_error( $gate ) ? $gate : $this->ok( LicenseLink::status() );
	}

	public function license_activate( WP_REST_Request $request ) {
		$gate = $this->require_scope( $request, Scopes::LICENSE_MANAGE );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$r = LicenseLink::activate( (string) $request->get_param( 'key' ) );
		AuditLog::record( $this->key_id( $request ), 'license:activate', is_wp_error( $r ) ? 'error' : 'ok' );
		return is_wp_error( $r ) ? $r : $this->ok( $r );
	}

	public function license_validate( WP_REST_Request $request ) {
		$gate = $this->require_scope( $request, Scopes::LICENSE_MANAGE );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$r = LicenseLink::validate();
		return is_wp_error( $r ) ? $r : $this->ok( $r );
	}

	public function license_deactivate( WP_REST_Request $request ) {
		$gate = $this->require_scope( $request, Scopes::LICENSE_MANAGE );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		AuditLog::record( $this->key_id( $request ), 'license:deactivate', 'ok' );
		return $this->ok( LicenseLink::deactivate() );
	}
}
