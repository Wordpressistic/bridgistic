<?php
/**
 * Scope and preset wiring, including the WooCommerce scopes.
 *
 * A preset is the only thing most users will ever choose, so the mapping from
 * a friendly label to a scope set is the real permission model. These tests
 * assert the properties that make that model trustworthy: the safe presets are
 * actually safe, the dangerous scope stays out of everything but Full Trust,
 * and every scope a route enforces is a scope the UI can offer.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

use Bridgistic\Admin\Presets;
use Bridgistic\Security\Scopes;

bridgistic_suite( 'scopes' );

$all = Scopes::all();

// ---- 1. The scope catalogue ------------------------------------------------

check( count( $all ) > 0, 'the scope catalogue is populated' );
foreach ( $all as $scope => $description ) {
	check( '' !== trim( (string) $description ), "scope {$scope} has a description for the admin UI" );
	check( 1 === preg_match( '/^[a-z]+(:[a-z]+)+$/', $scope ), "scope {$scope} follows the documented name:verb form" );
}

// ---- 2. WooCommerce scopes -------------------------------------------------

$woo = Scopes::woocommerce();
check_equals( 6, count( $woo ), 'six WooCommerce scopes are defined' );
foreach ( $woo as $scope ) {
	check( isset( $all[ $scope ] ), "WooCommerce scope {$scope} is in the catalogue, so the UI can offer it" );
	check( 0 === strpos( $scope, 'woo:' ), "WooCommerce scope {$scope} is namespaced" );
}

// Store permissions must be separable from content permissions: a key that can
// write blog posts must not thereby be able to change order status or pricing.
check( ! in_array( Scopes::WOO_ORDERS_WRITE, Presets::get( 'content' )['scopes'], true ), 'the Content Manager preset cannot change orders' );
check( ! in_array( Scopes::WOO_PRODUCTS_WRITE, Presets::get( 'content' )['scopes'], true ), 'the Content Manager preset cannot change products' );
check( ! in_array( Scopes::POSTS_WRITE, Scopes::woocommerce(), true ), 'the WooCommerce scope set does not smuggle in posts:write' );

// ...and the reverse: a store key has no business running SQL or PHP.
$store = Presets::get( 'woocommerce' );
check( is_array( $store ), 'a WooCommerce Manager preset exists' );
foreach ( array( Scopes::PHP_EXECUTE, Scopes::DB_READ, Scopes::DB_WRITE, Scopes::FS_READ, Scopes::FS_WRITE, Scopes::PLUGINS_MANAGE, Scopes::USERS_WRITE, Scopes::OPTIONS_WRITE ) as $forbidden ) {
	check( ! in_array( $forbidden, $store['scopes'], true ), "the WooCommerce Manager preset does not grant {$forbidden}" );
}
foreach ( $woo as $scope ) {
	check( in_array( $scope, $store['scopes'], true ), "the WooCommerce Manager preset grants {$scope}" );
}
check( $store['require_approval'], 'the WooCommerce Manager preset routes writes through the approval queue' );

// ---- 3. php:execute containment --------------------------------------------

// The single most dangerous scope. It must appear in exactly one place.
$presets_with_php = array();
foreach ( Presets::all() as $id => $preset ) {
	if ( in_array( Scopes::PHP_EXECUTE, $preset['scopes'], true ) ) {
		$presets_with_php[] = $id;
	}
}
check_equals( array( 'developer' ), $presets_with_php, 'php:execute is offered by exactly one preset (Developer Mode)' );
check( Presets::get( 'developer' )['risky'], 'the preset carrying php:execute is flagged risky' );
check( Presets::get( 'developer' )['require_approval'], 'the preset carrying php:execute requires approval' );

// ---- 4. Read-only really is read-only --------------------------------------

$read_only = Presets::get( 'read_only' );
check( is_array( $read_only ), 'a Read-only preset exists' );
foreach ( $read_only['scopes'] as $scope ) {
	check(
		false === strpos( $scope, ':write' ) && Scopes::PHP_EXECUTE !== $scope && Scopes::PLUGINS_MANAGE !== $scope && Scopes::SNAPSHOT !== $scope,
		"the Read-only preset grants no write capability, but grants {$scope}"
	);
}
check( ! $read_only['risky'], 'the Read-only preset is not flagged risky' );

// ---- 5. Presets are ordered safest-first -----------------------------------

// Both the wizard and the OAuth consent screen preselect the first entry, so
// declaration order is a security property rather than a cosmetic one.
$ids = array_keys( Presets::all() );
check_equals( 'read_only', $ids[0], 'the narrowest preset is declared first, so it is the default everywhere' );
check_equals( 'read_only', Presets::SAFEST, 'the declared safest preset id matches the first entry' );

$counts = array_map( static fn( array $p ): int => count( $p['scopes'] ), Presets::all() );
$sorted = $counts;
sort( $sorted );
check_equals( array_values( $sorted ), array_values( $counts ), 'presets are declared in non-decreasing order of breadth' );

// ---- 6. Every preset is well-formed ----------------------------------------

foreach ( Presets::all() as $id => $preset ) {
	check( isset( $preset['label'], $preset['description'], $preset['scopes'], $preset['require_approval'], $preset['risky'] ), "preset {$id} declares every required field" );
	check( '' !== trim( (string) $preset['description'] ), "preset {$id} has a description" );
	check( count( $preset['scopes'] ) > 0, "preset {$id} grants at least one scope" );
	check_equals( Scopes::sanitize( $preset['scopes'] ), array_values( array_unique( $preset['scopes'] ) ), "preset {$id} contains only real, non-duplicated scopes" );
}

// ---- 7. Risky scopes are labelled -------------------------------------------

$risky = Presets::risky_scopes();
foreach ( array( Scopes::PHP_EXECUTE, Scopes::DB_WRITE, Scopes::FS_WRITE, Scopes::PLUGINS_MANAGE, Scopes::USERS_WRITE, Scopes::OPTIONS_WRITE ) as $expected ) {
	check( in_array( $expected, $risky, true ), "{$expected} is flagged as risky, so the consent screen warns about it" );
}
foreach ( $risky as $scope ) {
	check( isset( $all[ $scope ] ), "risky scope {$scope} is a real scope" );
}

// ---- 8. Scopes::presets() stays consistent with the catalogue --------------

foreach ( Scopes::presets() as $id => $scopes ) {
	check_equals( $scopes, Scopes::sanitize( $scopes ), "the {$id} scope bundle contains only real scopes" );
}
check_equals( array_keys( $all ), Scopes::presets()['full_trust'], 'full_trust is exactly the whole catalogue' );
check( ! in_array( Scopes::PHP_EXECUTE, Scopes::presets()['developer'], true ), 'the developer scope bundle excludes php:execute by default' );
check( in_array( Scopes::WOO_PRODUCTS_READ, Scopes::presets()['full_trust'], true ), 'new WooCommerce scopes are picked up by full_trust automatically' );
