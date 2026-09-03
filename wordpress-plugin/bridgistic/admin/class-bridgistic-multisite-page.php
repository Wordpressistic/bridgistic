<?php
/**
 * Multi-Site: guided builder for the BRIDGISTIC_CONNECTIONS registry file
 * (see docs/CONNECT_OTHER_AI.md). Previously this was purely a hand-edited
 * JSON file with a starter template in the Export Package zip; this page
 * doesn't change that file format, it just builds it for you in the
 * browser instead of by hand. Nothing here is stored server-side beyond
 * what already exists (keys) — the "other sites" rows a user adds live only
 * in the page's own JS state (and, for the non-secret fields only, the
 * browser's localStorage so a reload doesn't lose them).
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

use Bridgistic\Security\KeyStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MultiSitePage extends Page {

	protected function view(): string {
		return 'multisite';
	}

	protected function data(): array {
		$keys = array_values(
			array_filter(
				KeyStore::list_all(),
				static fn( $k ) => (int) $k['enabled'] === 1
			)
		);

		// Same 2-minute show-once window every other secret-embedding screen uses.
		$fresh        = get_transient( 'bridgistic_new_key_' . get_current_user_id() );
		$fresh_key_id = is_array( $fresh ) ? (string) ( $fresh['key_id'] ?? '' ) : '';
		$fresh_secret = is_array( $fresh ) ? (string) ( $fresh['secret'] ?? '' ) : '';

		$host          = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$alias_default = sanitize_title( $host ?: 'my-site' );
		if ( '' === $alias_default ) {
			$alias_default = 'my-site';
		}

		return array(
			'keys'          => $keys,
			'site_url'      => home_url(),
			'alias_default' => $alias_default,
			'fresh_key_id'  => $fresh_key_id,
			'fresh_secret'  => $fresh_secret,
			'setup_url'     => admin_url( 'admin.php?page=bridgistic-setup' ),
			'keys_url'      => admin_url( 'admin.php?page=bridgistic-keys' ),
		);
	}
}
