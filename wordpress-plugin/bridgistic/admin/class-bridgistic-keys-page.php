<?php
/**
 * Keys & Scopes: key cards, advanced creation, revoked keys.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

use Bridgistic\Usage;
use Bridgistic\Security\KeyStore;
use Bridgistic\Security\Scopes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KeysPage extends Page {

	protected function view(): string {
		return 'keys';
	}

	protected function data(): array {
		$fresh = get_transient( 'bridgistic_new_key_' . get_current_user_id() );
		if ( $fresh ) {
			delete_transient( 'bridgistic_new_key_' . get_current_user_id() );
		}

		$active  = array();
		$revoked = array();
		foreach ( KeyStore::list_all() as $k ) {
			$k['scopes_list'] = (array) json_decode( (string) $k['scopes'], true );
			$k['preset']      = Presets::match( $k['scopes_list'] );
			$k['usage']       = Usage::summary( (string) $k['key_id'] );
			if ( (int) $k['enabled'] === 1 ) {
				$active[] = $k;
			} else {
				$revoked[] = $k;
			}
		}

		return array(
			'fresh'        => is_array( $fresh ) ? $fresh : null,
			'active'       => $active,
			'revoked'      => $revoked,
			'all_scopes'   => Scopes::all(),
			'risky_scopes' => Presets::risky_scopes(),
			'presets'      => Presets::all(),
			'action_url'   => admin_url( 'admin-post.php' ),
		);
	}
}
