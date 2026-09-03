<?php
/**
 * Export Package: build a ready-to-use Claude setup zip.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

use Bridgistic\Security\KeyStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ExportPage extends Page {

	protected function view(): string {
		return 'export';
	}

	protected function data(): array {
		$keys = array_values(
			array_filter(
				KeyStore::list_all(),
				static fn( $k ) => (int) $k['enabled'] === 1
			)
		);

		// A secret can only be embedded during the 2-minute window after
		// creation/rotation, and only for the exact key that produced it.
		$fresh          = get_transient( 'bridgistic_new_key_' . get_current_user_id() );
		$fresh_key_id   = is_array( $fresh ) ? (string) ( $fresh['key_id'] ?? '' ) : '';

		return array(
			'keys'         => $keys,
			'fresh_key_id' => $fresh_key_id,
			'action_url'   => admin_url( 'admin-post.php' ),
			'setup_url'    => admin_url( 'admin.php?page=bridgistic-setup' ),
			'zip_ok'       => class_exists( '\ZipArchive' ),
		);
	}
}
