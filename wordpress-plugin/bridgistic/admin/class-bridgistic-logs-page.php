<?php
/**
 * Logs: filterable audit trail. Params are hashed at write time, so rows
 * are safe to render — key ids and summaries only, never secrets.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

use Bridgistic\AuditLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LogsPage extends Page {

	private const FILTERS = array( 'all', 'read', 'write', 'approval', 'failed', 'security', 'developer' );

	protected function view(): string {
		return 'logs';
	}

	protected function data(): array {
		$filter = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view filter.
		if ( ! in_array( $filter, self::FILTERS, true ) ) {
			$filter = 'all';
		}

		return array(
			'filter'   => $filter,
			'filters'  => array(
				'all'       => __( 'All', 'bridgistic' ),
				'read'      => __( 'Read', 'bridgistic' ),
				'write'     => __( 'Write', 'bridgistic' ),
				'approval'  => __( 'Approval', 'bridgistic' ),
				'failed'    => __( 'Failed', 'bridgistic' ),
				'security'  => __( 'Security', 'bridgistic' ),
				'developer' => __( 'Developer', 'bridgistic' ),
			),
			'rows'     => AuditLog::query( $filter, 100 ),
			'base_url' => admin_url( 'admin.php?page=bridgistic-logs' ),
		);
	}
}
