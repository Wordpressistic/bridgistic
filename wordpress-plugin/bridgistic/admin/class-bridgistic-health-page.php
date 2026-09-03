<?php
/**
 * Health Check: diagnostic cards + score + copyable debug report.
 * Checks run via AJAX (skeleton first) so slow loopback probes never block paint.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HealthPage extends Page {

	protected function view(): string {
		return 'health';
	}

	protected function data(): array {
		$last = (array) get_option( 'bridgistic_last_health', array() );
		return array(
			'last_score' => isset( $last['score'] ) ? (int) $last['score'] : null,
			'last_time'  => isset( $last['time'] ) ? (int) $last['time'] : null,
			// Static skeleton labels shown while the AJAX run is in flight.
			'skeleton'   => array(
				__( 'REST API', 'bridgistic' ),
				__( 'Bridgistic namespace', 'bridgistic' ),
				__( 'Security plugin / WAF', 'bridgistic' ),
				__( 'HMAC authentication', 'bridgistic' ),
				__( 'Site URL', 'bridgistic' ),
				__( 'SSL / HTTPS', 'bridgistic' ),
				__( 'Permalinks', 'bridgistic' ),
				__( 'PHP version', 'bridgistic' ),
				__( 'WordPress version', 'bridgistic' ),
				__( 'Uploads directory', 'bridgistic' ),
				__( 'Sandbox directory', 'bridgistic' ),
				__( 'Audit log table', 'bridgistic' ),
				__( 'Approval queue', 'bridgistic' ),
				__( 'Key scopes', 'bridgistic' ),
				__( 'MCP config generated', 'bridgistic' ),
				__( 'Server time', 'bridgistic' ),
			),
		);
	}
}
