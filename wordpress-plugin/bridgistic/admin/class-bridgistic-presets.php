<?php
/**
 * Claude Setup permission presets.
 *
 * UI-level bundles mapped onto the existing security scopes. The mapping
 * lives here (admin layer) so the security classes stay untouched.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

use Bridgistic\Security\Scopes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Presets {

	/** The preset any unknown id resolves to. Must always be the narrowest one. */
	public const SAFEST = 'read_only';

	/**
	 * All setup presets, keyed by id.
	 *
	 * Declaration order is the order they render, and it runs narrowest-first
	 * on purpose: the setup wizard and the OAuth consent screen both preselect
	 * the first entry, so the safest choice is the default choice everywhere
	 * without either surface having to know which one that is.
	 *
	 * require_approval: writes/destructive ops pause in the approval queue.
	 * risky: renders with warning styling and needs an explicit confirm.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function all(): array {
		return array(
			'read_only'   => array(
				'label'            => __( 'Read-only', 'bridgistic' ),
				'description'      => __( 'Safe browsing, content reading, website inspection. Claude cannot change anything.', 'bridgistic' ),
				'scopes'           => array(
					Scopes::SITE_READ,
					Scopes::POSTS_READ,
					Scopes::USERS_READ,
					Scopes::OPTIONS_READ,
				),
				'require_approval' => false,
				'risky'            => false,
			),
			'content'     => array(
				'label'            => __( 'Content Manager', 'bridgistic' ),
				'description'      => __( 'Create and update posts, pages, and media. No plugin, database, or file access.', 'bridgistic' ),
				'scopes'           => array(
					Scopes::SITE_READ,
					Scopes::POSTS_READ,
					Scopes::POSTS_WRITE,
					Scopes::MEDIA_WRITE,
					Scopes::MEMORY_READ,
					Scopes::MEMORY_WRITE,
				),
				'require_approval' => false,
				'risky'            => false,
			),
			'woocommerce' => array(
				'label'            => __( 'WooCommerce Manager', 'bridgistic' ),
				'description'      => __( 'Read and update products, read orders and customers, and change order status. No plugin, database, file, or PHP access. Order status changes go through the approval queue because they trigger customer emails and stock movements.', 'bridgistic' ),
				'scopes'           => array_merge(
					array(
						Scopes::SITE_READ,
						Scopes::POSTS_READ,
						Scopes::MEDIA_WRITE,
					),
					Scopes::woocommerce()
				),
				'require_approval' => true,
				'risky'            => false,
			),
			'safe_admin'  => array(
				'label'            => __( 'Safe Admin', 'bridgistic' ),
				'description'      => __( 'Content plus plugin management, options, snapshots, and playbooks — every write goes through the approval queue.', 'bridgistic' ),
				'scopes'           => array(
					Scopes::SITE_READ,
					Scopes::POSTS_READ,
					Scopes::POSTS_WRITE,
					Scopes::MEDIA_WRITE,
					Scopes::USERS_READ,
					Scopes::OPTIONS_READ,
					Scopes::OPTIONS_WRITE,
					Scopes::PLUGINS_MANAGE,
					Scopes::SNAPSHOT,
					Scopes::MEMORY_READ,
					Scopes::MEMORY_WRITE,
					Scopes::PLAYBOOK_MANAGE,
				),
				'require_approval' => true,
				'risky'            => false,
			),
			'developer'   => array(
				'label'            => __( 'Developer Mode', 'bridgistic' ),
				'description'      => __( 'Full toolset including database, filesystem, and PHP execution. Requires approval on destructive operations. Use only on sites you control.', 'bridgistic' ),
				'scopes'           => array_keys( Scopes::all() ),
				'require_approval' => true,
				'risky'            => true,
			),
		);
	}

	/**
	 * Resolve a preset id to its definition, or null.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function get( string $id ): ?array {
		$all = self::all();
		return $all[ $id ] ?? null;
	}

	/**
	 * Resolve an id to a preset, falling back to the narrowest one.
	 *
	 * Every caller that takes a preset id from a request must go through this
	 * rather than get(), so a typo or a forged parameter can only ever grant
	 * less than intended, never more.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_or_safest( string $id ): array {
		return self::get( $id ) ?? self::all()[ self::SAFEST ];
	}

	/**
	 * Best-effort reverse lookup: which preset does a scope set match?
	 * Returns the preset id or 'custom'.
	 *
	 * @param array<int,string> $scopes Scope list.
	 */
	public static function match( array $scopes ): string {
		sort( $scopes );
		foreach ( self::all() as $id => $preset ) {
			$preset_scopes = $preset['scopes'];
			sort( $preset_scopes );
			if ( $preset_scopes === $scopes ) {
				return $id;
			}
		}
		return 'custom';
	}

	/**
	 * Scopes considered dangerous — rendered with warning styling.
	 *
	 * @return array<int,string>
	 */
	public static function risky_scopes(): array {
		return array(
			Scopes::DB_READ,
			Scopes::DB_WRITE,
			Scopes::FS_READ,
			Scopes::FS_WRITE,
			Scopes::PHP_EXECUTE,
			Scopes::PLUGINS_MANAGE,
			Scopes::USERS_WRITE,
			Scopes::OPTIONS_WRITE,
		);
	}
}
