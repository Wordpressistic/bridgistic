<?php
/**
 * Cache flush + permalink endpoints.
 *
 * Both routes change the site rather than read it, so the tests pin what would
 * otherwise fail quietly: a cache plugin that is not installed must be skipped
 * instead of fataling, a structure outside the whitelist must be refused with
 * 422 and leave the stored option untouched, and the rewrite object must see
 * the new values before the rules are rebuilt — otherwise it regenerates the
 * rules for the structure it booted with.
 *
 * The happy path through Guard::run() is covered for the cache route. For
 * permalinks it is not: the pre-update snapshot reads wp_options, which the
 * in-memory database does not model, so validation/apply are tested directly
 * instead (the same trade-off suite-filesystem documents).
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

use Bridgistic\Rest\CacheController;
use Bridgistic\Rest\PermalinkController;
use Bridgistic\Security\Scopes;

bridgistic_suite( 'cache-permalink' );

// Guard::run() is the write path for both controllers; the approval stub keeps
// the queue in memory instead of asking for tables the harness lacks.
require_once __DIR__ . '/stubs-guard.php';
require_once BRIDGISTIC_DIR . 'includes/class-guard.php';
require_once BRIDGISTIC_DIR . 'includes/rest/class-controller.php';
require_once BRIDGISTIC_DIR . 'includes/rest/class-cache-controller.php';
require_once BRIDGISTIC_DIR . 'includes/rest/class-permalink-controller.php';

// WordPress' object-cache flush, counted instead of executed.
$GLOBALS['__wp_cache_flushes'] = 0;

function wp_cache_flush(): bool {
	$GLOBALS['__wp_cache_flushes']++;
	return true;
}

/** Stands in for WP_Rewrite: records what the controller asks it to do. */
class CpRewriteSpy {

	/** @var array<int,string> */
	public array $structures = array();

	/** @var array<int,string> */
	public array $categories = array();

	/** @var array<int,string> */
	public array $tags = array();

	/** @var array<int,bool> */
	public array $flushes = array();

	public function set_permalink_structure( string $structure ): void {
		$this->structures[] = $structure;
	}

	public function set_category_base( string $base ): void {
		$this->categories[] = $base;
	}

	public function set_tag_base( string $base ): void {
		$this->tags[] = $base;
	}

	public function flush_rules( bool $hard = true ): void {
		$this->flushes[] = $hard;
	}
}

/**
 * Request carrying the auth context Controller::require_scope() reads.
 *
 * @param array<string,mixed> $params Body params.
 * @param array<int,string>   $scopes Key scopes.
 */
function cp_request( string $method, string $route, array $params, array $scopes ): WP_REST_Request {
	$params['__ctx'] = array( 'key_id' => 'cp-key', 'scopes' => $scopes );
	return new WP_REST_Request( $method, '/bridgistic/v1/' . $route, $params );
}

// ---- 1. Both controllers are wired into the router --------------------------

$router_src = (string) file_get_contents( BRIDGISTIC_DIR . 'includes/rest/class-router.php' );
check( false !== strpos( $router_src, 'new CacheController()' ), 'the router registers the cache controller' );
check( false !== strpos( $router_src, 'new PermalinkController()' ), 'the router registers the permalink controller' );

// ---- 2. Cache targets -------------------------------------------------------

check_equals(
	array( 'object_cache', 'wp_rocket', 'litespeed', 'w3tc', 'autoptimize', 'wp_super_cache', 'opcache' ),
	array_keys( CacheController::available() ),
	'every documented cache target is probed, object cache first'
);

$flushed_names = CacheController::flush_all();
check( in_array( 'object_cache', $flushed_names['flushed'], true ), 'the object cache is flushed and reported' );
check_equals( array(), array_values( array_intersect( $flushed_names['flushed'], $flushed_names['skipped'] ) ), 'no target is both flushed and skipped' );
foreach ( array( 'wp_rocket', 'litespeed', 'w3tc', 'autoptimize', 'wp_super_cache' ) as $absent ) {
	check( in_array( $absent, $flushed_names['skipped'], true ), "absent target {$absent} is skipped, not fatal" );
}

// A plugin can be present and still fail mid-purge; that must not 500 the
// request, and the order must follow the registry rather than the input array.
$result = CacheController::run_targets(
	array(
		'opcache'      => static function (): void {},
		'object_cache' => static function (): void {},
		'wp_rocket'    => static function (): void {
			throw new RuntimeException( 'purge blew up' );
		},
	)
);
check_equals( array( 'object_cache', 'opcache' ), $result['flushed'], 'flushers run in declared order regardless of array order' );
check( in_array( 'wp_rocket', $result['skipped'], true ), 'a target that throws is skipped instead of failing the flush' );
check( in_array( 'w3tc', $result['skipped'], true ), 'a target with no flusher is reported as skipped' );
check( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $result['at'] ), 'timestamp is ISO-8601 UTC' );

// ---- 3. POST /cache/flush ---------------------------------------------------

$cache = new CacheController();

$denied = $cache->flush( cp_request( 'POST', 'cache/flush', array(), array( Scopes::SITE_READ ) ) );
check( is_wp_error( $denied ), 'cache flush refuses a key without options:write' );
check_equals( 'bridgistic_scope_denied', is_wp_error( $denied ) ? $denied->get_error_code() : '', 'cache flush denial is the standard scope error' );

$GLOBALS['__wp_cache_flushes'] = 0;
$response                      = $cache->flush( cp_request( 'POST', 'cache/flush', array(), array( Scopes::OPTIONS_WRITE ) ) );
$body                          = $response->get_data();
check_equals( true, $body['ok'] ?? null, 'cache flush succeeds for options:write' );
$result = (array) ( $body['data']['result'] ?? array() );
check( in_array( 'object_cache', (array) ( $result['flushed'] ?? array() ), true ), 'the response lists the object cache as flushed' );
check( isset( $result['skipped'], $result['at'] ), 'the response carries skipped and at' );
check_equals( 1, $GLOBALS['__wp_cache_flushes'], 'the endpoint called wp_cache_flush exactly once' );

$GLOBALS['__wp_cache_flushes'] = 0;
$preview                       = $cache->flush( cp_request( 'POST', 'cache/flush', array( 'dry_run' => true ), array( Scopes::OPTIONS_WRITE ) ) );
$dry                           = (array) ( $preview->get_data()['data'] ?? array() );
check_equals( true, $dry['dry_run'] ?? null, 'dry_run returns a preview' );
check_equals( 0, $GLOBALS['__wp_cache_flushes'], 'dry_run does not flush anything' );
check( isset( $dry['would']['targets']['object_cache'] ), 'the preview lists the targets it would flush' );

// ---- 4. Permalink structure validation --------------------------------------

foreach ( PermalinkController::ALLOWED_STRUCTURES as $allowed ) {
	check( PermalinkController::is_safe_structure( $allowed ), 'whitelisted structure accepted: "' . $allowed . '"' );
}

$custom_ok = array(
	'/blog/%postname%/',
	'/news/%year%/%monthnum%/',
	'/%postname%.html',
	'/%post_title%/%post_id%/',
	'/plain/path/',
);
foreach ( $custom_ok as $structure ) {
	check( PermalinkController::is_safe_structure( $structure ), "safe custom structure accepted: {$structure}" );
}

$custom_bad = array(
	'/%postname/'         => 'an unclosed tag',
	'/%postname%%'        => 'an extra percent',
	'/%post name%/'       => 'a space inside a tag',
	'/%post-name%/'       => 'a hyphen inside a tag',
	'/%1year%/'           => 'a tag that does not start with a letter',
	'/%%/'                => 'an empty tag',
	'/%postname%?p=1'     => 'a query string',
	'/%postname%#top'     => 'a fragment',
	'no-leading-slash'    => 'a missing leading slash',
	' /%postname%/'       => 'a leading space',
	'/%postname%<script>' => 'a tag-like value with markup',
	'/%../%'              => 'a tag made of dots',
);
foreach ( $custom_bad as $structure => $why ) {
	check( ! PermalinkController::is_safe_structure( (string) $structure ), "structure refused ({$why}): {$structure}" );
}

// ---- 5. GET /permalinks -----------------------------------------------------

$permalinks = new PermalinkController();

$GLOBALS['__options']['permalink_structure'] = '/%postname%/';
$GLOBALS['__options']['category_base']       = '';
$GLOBALS['__options']['tag_base']            = '';

$get  = $permalinks->get_permalinks( cp_request( 'GET', 'permalinks', array(), array( Scopes::OPTIONS_READ ) ) );
$data = (array) ( $get->get_data()['data'] ?? array() );
check_equals( '/%postname%/', $data['structure'] ?? null, 'GET reports the stored structure' );
check_equals( '', $data['category_base'] ?? null, 'GET reports an empty category base' );
check_equals( false, $data['is_plain'] ?? null, 'a pretty structure is not plain' );
check_equals( 'https://example.test/', $data['home_url'] ?? null, 'GET reports the home URL' );

$GLOBALS['__options']['permalink_structure'] = '';
$plain                                      = (array) ( $permalinks->get_permalinks( cp_request( 'GET', 'permalinks', array(), array( Scopes::OPTIONS_READ ) ) )->get_data()['data'] ?? array() );
check_equals( true, $plain['is_plain'] ?? null, 'an empty structure reports is_plain' );

$no_scope = $permalinks->get_permalinks( cp_request( 'GET', 'permalinks', array(), array( Scopes::POSTS_READ ) ) );
check_equals( 'bridgistic_scope_denied', is_wp_error( $no_scope ) ? $no_scope->get_error_code() : '', 'GET needs options:read' );

// ---- 6. Applying a structure ------------------------------------------------

$GLOBALS['__options']['permalink_structure'] = '/%postname%/';
$applied                                     = PermalinkController::apply( '/%year%/%monthnum%/%postname%/', 'topics', '' );
check_equals( '/%year%/%monthnum%/%postname%/', $applied['structure'], 'apply returns the new structure' );
check_equals( 'topics', $applied['category_base'], 'apply returns the new category base' );
check_equals( '', $applied['tag_base'], 'an empty tag base clears the value' );
check_equals( '/%year%/%monthnum%/%postname%/', get_option( 'permalink_structure' ), 'apply persists permalink_structure' );
check_equals( 'topics', get_option( 'category_base' ), 'apply persists category_base' );

$untouched = PermalinkController::apply( null, null, null );
check_equals( '/%year%/%monthnum%/%postname%/', $untouched['structure'], 'a null field leaves the stored value alone' );
check_equals( 'topics', $untouched['category_base'], 'a null category base leaves the stored value alone' );
check_equals( true, PermalinkController::apply( '', null, null )['is_plain'], 'an empty structure switches to Plain' );

// The rewrite object must be re-seeded before the hard flush, or the rules it
// regenerates belong to the structure the site booted with.
$rewrite               = new CpRewriteSpy();
$GLOBALS['wp_rewrite'] = $rewrite;
PermalinkController::apply( '/%postname%/', 'topics', '' );
check_equals( array( '/%postname%/' ), $rewrite->structures, 'the rewrite object is re-seeded before flushing' );
check_equals( array( 'topics' ), $rewrite->categories, 'the rewrite object gets the new category base' );
check_equals( array( true ), $rewrite->flushes, 'rewrite rules are flushed hard' );
unset( $GLOBALS['wp_rewrite'] );

// ---- 7. POST /permalinks refuses bad input ---------------------------------

$GLOBALS['__options']['permalink_structure'] = '/%postname%/';

$bad = $permalinks->update_permalinks( cp_request( 'POST', 'permalinks', array( 'structure' => '/%postname' ), array( Scopes::OPTIONS_WRITE ) ) );
check( is_wp_error( $bad ), 'an unsafe structure is refused' );
check_equals( 422, is_wp_error( $bad ) ? $bad->get_status() : 0, 'an unsafe structure returns 422' );
check_equals( '/%postname%/', get_option( 'permalink_structure' ), 'a refused structure changes nothing' );

$denied = $permalinks->update_permalinks( cp_request( 'POST', 'permalinks', array( 'structure' => '/%postname%/' ), array( Scopes::OPTIONS_READ ) ) );
check_equals( 'bridgistic_scope_denied', is_wp_error( $denied ) ? $denied->get_error_code() : '', 'POST needs options:write, not options:read' );

$empty = $permalinks->update_permalinks( cp_request( 'POST', 'permalinks', array(), array( Scopes::OPTIONS_WRITE ) ) );
check_equals( 422, is_wp_error( $empty ) ? $empty->get_status() : 0, 'a request with no fields is refused' );

$bad_base = $permalinks->update_permalinks( cp_request( 'POST', 'permalinks', array( 'category_base' => 'bad base' ), array( Scopes::OPTIONS_WRITE ) ) );
check_equals( 'bridgistic_permalink_invalid', is_wp_error( $bad_base ) ? $bad_base->get_error_code() : '', 'a category base with a space is refused' );

$array_param = $permalinks->update_permalinks( cp_request( 'POST', 'permalinks', array( 'structure' => array( '/%postname%/' ) ), array( Scopes::OPTIONS_WRITE ) ) );
check_equals( 422, is_wp_error( $array_param ) ? $array_param->get_status() : 0, 'an array is not a string parameter' );

check_equals( '/%postname%/', get_option( 'permalink_structure' ), 'no refused request reached update_option' );
