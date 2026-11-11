<?php
/**
 * Permalink settings — read the current structure, change it from the
 * dashboard, and rebuild the rewrite rules so the change takes effect
 * immediately instead of 404ing every URL until someone re-saves Settings.
 *
 *   GET  /permalinks   options:read    current structure + bases
 *   POST /permalinks   options:write   set structure and/or bases, flush rules
 *
 * Validation is a whitelist first (the four structures WordPress' own screen
 * offers, plus "" for Plain) and then a conservative custom-value check: the
 * value always starts with "/", carries only URL-safe characters, and every
 * "%" opens a bare rewrite tag. Anything else is refused with 422 before
 * update_option() is reached.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Rest;

use Bridgistic\Guard;
use Bridgistic\Security\Scopes;
use Bridgistic\Snapshot;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PermalinkController extends Controller {

	/** The structures the WordPress Settings → Permalinks screen offers, "" being Plain. */
	public const ALLOWED_STRUCTURES = array(
		'',
		'/%postname%/',
		'/%year%/%monthnum%/%postname%/',
		'/%category%/%postname%/',
		'/%postname%/%post_id%/',
	);

	/** Custom structure: leading slash, then URL-safe characters only. */
	private const SAFE_CUSTOM = '#^/[A-Za-z0-9%_./\-]+$#';

	/** A rewrite tag name: %postname%, %post_id%, %year% … */
	private const TAG_NAME = '#^[A-Za-z][A-Za-z0-9_]*$#';

	/** category_base / tag_base: a path segment, "" meaning the WordPress default. */
	private const SAFE_BASE = '#^[A-Za-z0-9][A-Za-z0-9/_-]*$#';

	public function register( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/permalinks',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_permalinks' ),
					'permission_callback' => array( $this, 'authenticate' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_permalinks' ),
					'permission_callback' => array( $this, 'authenticate' ),
				),
			)
		);
	}

	public function get_permalinks( WP_REST_Request $request ) {
		$gate = $this->require_scope( $request, Scopes::OPTIONS_READ );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		return $this->ok( self::state() );
	}

	public function update_permalinks( WP_REST_Request $request ) {
		$gate = $this->require_scope( $request, Scopes::OPTIONS_WRITE );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		// null means "leave this one alone"; "" is a real value (Plain, or the
		// default category/tag base), so it must survive the trip.
		$values = array(
			'structure'     => null,
			'category_base' => null,
			'tag_base'      => null,
		);
		foreach ( array_keys( $values ) as $field ) {
			$value = $request->get_param( $field );
			if ( null === $value ) {
				continue;
			}
			if ( ! is_scalar( $value ) ) {
				return $this->fail( 'bridgistic_permalink_invalid', sprintf( 'Parameter "%s" must be a string.', $field ), 422 );
			}
			$values[ $field ] = (string) $value;
		}

		if ( null === $values['structure'] && null === $values['category_base'] && null === $values['tag_base'] ) {
			return $this->fail( 'bridgistic_permalink_noop', 'Send at least one of structure, category_base or tag_base.', 422 );
		}

		if ( null !== $values['structure'] && ! self::is_safe_structure( $values['structure'] ) ) {
			return $this->fail(
				'bridgistic_permalink_invalid',
				'Unsupported permalink structure. Use one of the WordPress presets or a custom structure that starts with "/", uses only URL-safe characters, and closes every %tag%.',
				422
			);
		}

		foreach ( array( 'category_base', 'tag_base' ) as $field ) {
			if ( null !== $values[ $field ] && ! self::is_safe_base( $values[ $field ] ) ) {
				return $this->fail(
					'bridgistic_permalink_invalid',
					sprintf( 'Parameter "%s" must be a path segment such as "topics" (letters, digits, "/", "_", "-"), or "" for the WordPress default.', $field ),
					422
				);
			}
		}

		$structure     = $values['structure'];
		$category_base = $values['category_base'];
		$tag_base      = $values['tag_base'];

		return Guard::run(
			$request,
			array(
				'action'      => 'permalinks.update',
				'destructive' => true,
				'mutating'    => true,
				'payload'     => array(
					'structure'     => $structure,
					'category_base' => $category_base,
					'tag_base'      => $tag_base,
				),
				'summary'     => 'Update permalink settings and flush rewrite rules',
				// Snapshot::create() captures one option at a time; the structure
				// is the one whose loss breaks every URL, so it is the one kept.
				// The dry run echoes all three values for the approval reviewer.
				'snapshot'    => fn() => Snapshot::create( 'option', array( 'name' => 'permalink_structure' ), 'pre-update permalinks', $this->key_id( $request ) ),
				'dry_run'     => static function () use ( $structure, $category_base, $tag_base ): array {
					$to = array();
					foreach ( array( 'structure' => $structure, 'category_base' => $category_base, 'tag_base' => $tag_base ) as $field => $value ) {
						if ( null !== $value ) {
							$to[ $field ] = $value;
						}
					}
					return array( 'from' => self::state(), 'to' => $to, 'flush_rules' => true );
				},
				'execute'     => static fn(): array => self::apply( $structure, $category_base, $tag_base ),
			)
		);
	}

	/**
	 * Accept a whitelisted structure, or a custom one that is URL-safe and
	 * whose %-tags are well formed.
	 */
	public static function is_safe_structure( string $structure ): bool {
		if ( in_array( $structure, self::ALLOWED_STRUCTURES, true ) ) {
			return true;
		}
		if ( '' === $structure || 1 !== preg_match( self::SAFE_CUSTOM, $structure ) ) {
			return false;
		}
		return self::tags_are_well_formed( $structure );
	}

	/**
	 * Every % must open and close one tag, in that order, and the tag must be
	 * a bare name: %postname% is a tag; %post name%, %% and %tag are not.
	 */
	private static function tags_are_well_formed( string $structure ): bool {
		$parts = explode( '%', $structure );
		// Paired % means an odd number of pieces: text, tag, text, …, text.
		if ( 0 === count( $parts ) % 2 ) {
			return false;
		}
		for ( $i = 1, $last = count( $parts ) - 1; $i < $last; $i += 2 ) {
			if ( 1 !== preg_match( self::TAG_NAME, $parts[ $i ] ) ) {
				return false;
			}
		}
		return true;
	}

	/** category_base / tag_base are path segments, never URLs and never tags. */
	private static function is_safe_base( string $base ): bool {
		return '' === $base || 1 === preg_match( self::SAFE_BASE, $base );
	}

	/**
	 * Current permalink settings.
	 *
	 * @return array{structure:string,category_base:string,tag_base:string,is_plain:bool,home_url:string}
	 */
	public static function state(): array {
		$structure = (string) get_option( 'permalink_structure', '' );
		return array(
			'structure'     => $structure,
			'category_base' => (string) get_option( 'category_base', '' ),
			'tag_base'      => (string) get_option( 'tag_base', '' ),
			'is_plain'      => '' === $structure,
			'home_url'      => home_url( '/' ),
		);
	}

	/**
	 * Persist the supplied values, rebuild the rules, and report the result.
	 *
	 * @return array{structure:string,category_base:string,tag_base:string,is_plain:bool,home_url:string}
	 */
	public static function apply( ?string $structure, ?string $category_base, ?string $tag_base ): array {
		if ( null !== $structure ) {
			update_option( 'permalink_structure', $structure );
		}
		if ( null !== $category_base ) {
			update_option( 'category_base', $category_base );
		}
		if ( null !== $tag_base ) {
			update_option( 'tag_base', $tag_base );
		}
		self::flush_rules( $structure, $category_base, $tag_base );
		return self::state();
	}

	/**
	 * Rebuild the rewrite rules from the values just stored.
	 *
	 * update_option() on its own leaves $wp_rewrite holding the structure it
	 * booted with, so flush_rules() would regenerate the old rules. The
	 * setters re-seed that object (and write the same options again, which is
	 * idempotent), then the hard flush persists them.
	 */
	private static function flush_rules( ?string $structure, ?string $category_base, ?string $tag_base ): void {
		if ( ! isset( $GLOBALS['wp_rewrite'] ) || ! is_object( $GLOBALS['wp_rewrite'] ) ) {
			return;
		}
		$rewrite = $GLOBALS['wp_rewrite'];

		$setters = array(
			'set_permalink_structure' => $structure,
			'set_category_base'       => $category_base,
			'set_tag_base'            => $tag_base,
		);
		foreach ( $setters as $method => $value ) {
			if ( null !== $value && method_exists( $rewrite, $method ) ) {
				$rewrite->{$method}( $value );
			}
		}

		if ( method_exists( $rewrite, 'flush_rules' ) ) {
			$rewrite->flush_rules( true );
		}
	}
}
