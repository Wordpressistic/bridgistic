<?php
/**
 * Approvals: pending queue + recent decisions.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

use Bridgistic\Approvals;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ApprovalsPage extends Page {

	protected function view(): string {
		return 'approvals';
	}

	protected function data(): array {
		return array(
			'pending'    => Approvals::list_by_status( Approvals::PENDING, 100 ),
			'recent'     => Approvals::list_by_status( '', 50 ),
			'action_url' => admin_url( 'admin-post.php' ),
		);
	}
}
