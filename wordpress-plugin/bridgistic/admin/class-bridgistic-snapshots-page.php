<?php
/**
 * Snapshots: list, manual creation, restore with warning, free-limit notice.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

use Bridgistic\Snapshot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SnapshotsPage extends Page {

	protected function view(): string {
		return 'snapshots';
	}

	protected function data(): array {
		$snaps = Snapshot::list_recent( Actions::SNAPSHOT_LIMIT );
		return array(
			'snapshots' => $snaps,
			'count'     => count( $snaps ),
			'limit'     => Actions::SNAPSHOT_LIMIT,
		);
	}
}
