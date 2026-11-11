<?php
/**
 * Cache maintenance — the dashboard "clear cache" action.
 *
 *   POST /cache/flush   options:write
 *
 * Flushing is best-effort on purpose. A page-cache plugin that is absent,
 * half-loaded or throwing must never turn a cache clear into a 500, so every
 * target is probed before it is called and anything that does not run is
 * reported in `skipped` rather than failing the request. WordPress' own object
 * cache is always flushed first; the plugin-specific purges follow.
 *
 * Scope: options:write. There is no cache:manage scope, and a purge changes
 * site-wide state without touching data, so it sits with the option-writing
 * controllers instead of widening the scope catalogue for one route.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Rest;

use Bridgistic\Guard;
use Bridgistic\Security\Scopes;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CacheController extends Controller {

	public function register( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/cache/flush',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'flush' ),
				'permission_callback' => array( $this, 'authenticate' ),
			)
		);
	}

	public function flush( WP_REST_Request $request ) {
		$gate = $this->require_scope( $request, Scopes::OPTIONS_WRITE );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		return Guard::run(
			$request,
			array(
				'action'      => 'cache.flush',
				// Nothing to restore: a warm cache is not data, and every target
				// rebuilds itself on the next request.
				'destructive' => false,
				'mutating'    => true,
				'payload'     => array(),
				'summary'     => 'Flush the object cache and any page-cache plugin present',
				'dry_run'     => static fn(): array => array( 'targets' => self::available() ),
				'execute'     => static fn(): array => self::flush_all(),
			)
		);
	}

	/**
	 * Every flush target, in execution order, with its probe and its purger.
	 *
	 * The probe is a `function_exists`/`defined`/`class_exists` check, never a
	 * call, so asking "can this site flush X" is always safe. The purger stays
	 * a closure so the name of a missing plugin function is only ever resolved
	 * at call time, which is what keeps an absent plugin from being fatal.
	 *
	 * @return array<string,array{available:callable():bool,flush:callable():void}>
	 */
	private static function registry(): array {
		return array(
			'object_cache'   => array(
				'available' => static fn(): bool => function_exists( 'wp_cache_flush' ),
				'flush'     => static function (): void {
					wp_cache_flush();
				},
			),
			'wp_rocket'      => array(
				'available' => static fn(): bool => function_exists( 'rocket_clean_domain' ),
				'flush'     => static function (): void {
					rocket_clean_domain();
				},
			),
			'litespeed'      => array(
				// The plugin exposes a purge hook rather than a function; the
				// constant covers the versions where the hook is not registered
				// yet (early boot), the hook check covers a renamed constant.
				'available' => static fn(): bool => defined( 'LSCWP_V' ) || ( function_exists( 'has_action' ) && false !== has_action( 'litespeed_purge_all' ) ),
				'flush'     => static function (): void {
					if ( function_exists( 'do_action' ) ) {
						do_action( 'litespeed_purge_all' );
					}
				},
			),
			'w3tc'           => array(
				'available' => static fn(): bool => function_exists( 'w3tc_flush_all' ),
				'flush'     => static function (): void {
					w3tc_flush_all();
				},
			),
			'autoptimize'    => array(
				'available' => static fn(): bool => class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ),
				'flush'     => static function (): void {
					autoptimizeCache::clearall();
				},
			),
			'wp_super_cache' => array(
				'available' => static fn(): bool => function_exists( 'wp_cache_clear_cache' ),
				'flush'     => static function (): void {
					wp_cache_clear_cache();
				},
			),
			'opcache'        => array(
				'available' => static fn(): bool => function_exists( 'opcache_reset' ),
				'flush'     => static function (): void {
					opcache_reset();
				},
			),
		);
	}

	/**
	 * Which targets this site can flush right now, in execution order.
	 *
	 * @return array<string,bool>
	 */
	public static function available(): array {
		$available = array();
		foreach ( self::registry() as $name => $target ) {
			$available[ $name ] = (bool) call_user_func( $target['available'] );
		}
		return $available;
	}

	/**
	 * Run the purgers that were found, keeping the declared target order.
	 *
	 * Split out from flush_all() so the skip/throw handling can be exercised
	 * without a site whose cache plugin misbehaves.
	 *
	 * @param array<string,callable():void> $flushers Runnable targets, by name.
	 * @return array{flushed:array<int,string>,skipped:array<int,string>,at:string}
	 */
	public static function run_targets( array $flushers ): array {
		$flushed = array();
		$skipped = array();

		foreach ( self::registry() as $name => $target ) {
			if ( ! isset( $flushers[ $name ] ) ) {
				$skipped[] = $name;
				continue;
			}
			try {
				call_user_func( $flushers[ $name ] );
			} catch ( \Throwable $e ) {
				// A plugin can be loaded and still not be in a state that can
				// purge (init not run, object gone). Report it; don't fail.
				$skipped[] = $name;
				continue;
			}
			$flushed[] = $name;
		}

		return array(
			'flushed' => $flushed,
			'skipped' => $skipped,
			'at'      => gmdate( 'c' ),
		);
	}

	/**
	 * Flush everything this site can flush.
	 *
	 * @return array{flushed:array<int,string>,skipped:array<int,string>,at:string}
	 */
	public static function flush_all(): array {
		$flushers = array();
		foreach ( self::registry() as $name => $target ) {
			if ( (bool) call_user_func( $target['available'] ) ) {
				$flushers[ $name ] = $target['flush'];
			}
		}
		return self::run_targets( $flushers );
	}
}
