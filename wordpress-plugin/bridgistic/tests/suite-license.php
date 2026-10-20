<?php
/**
 * The public plugin must stay a deterministic free-only build.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

require_once BRIDGISTIC_DIR . 'includes/class-license-link.php';
require_once BRIDGISTIC_DIR . 'includes/class-license.php';

use Bridgistic\License;
use Bridgistic\LicenseLink;

bridgistic_suite( 'license-free-only' );

$license_features = array(
    'scheduled_playbooks_unlimited',
    'snapshots_advanced',
    'audit_export',
);

$closed_features = array(
    'skills_marketplace',
    'agency_dashboard',
    'team_permissions',
    'white_label',
);

check_equals( 'free', License::plan(), 'the public package always reports the free plan' );
check( License::FREE_ONLY, 'the free-only policy marker is enabled' );
check( ! License::is_premium(), 'the public package can never report a premium plan' );
check( ! License::is_connected(), 'the public package has no license connection state' );

unset( $GLOBALS['__options'][ LicenseLink::OPTION ] );

foreach ( $license_features as $feature ) {
    check( ! License::gate( $feature ), $feature . ' stays locked' );
}

foreach ( $closed_features as $feature ) {
    check( ! License::gate( $feature ), $feature . ' stays closed until shipped' );
}

check( License::gate( 'scheduled_playbooks_basic' ), 'the free schedule feature remains available' );
check( License::gate( 'site_read' ), 'security and bridge features are not commercial gates' );
check_equals( false, License::activate( 'wpk_test_key' )['ok'], 'license activation is unavailable' );
check_equals( false, License::validate_now()['ok'], 'remote validation is unavailable' );
check_equals( 'free_only', License::get_status()['status'], 'status identifies the free-only build' );

$GLOBALS['__options'][ LicenseLink::OPTION ] = array(
    'activation_token' => 'activation-token',
    'response'         => array( 'status' => 'active', 'plan' => 'lifetime' ),
    'key_mask'         => 'wpk_te…key',
);
check( License::has_active_license(), 'a verified active license is visible to the feature gate' );
foreach ( $license_features as $feature ) {
    check( License::gate( $feature ), $feature . ' opens with an active license' );
}
foreach ( $closed_features as $feature ) {
    check( ! License::gate( $feature ), $feature . ' stays closed with an active license' );
}
unset( $GLOBALS['__options'][ LicenseLink::OPTION ] );

$source = (string) file_get_contents( BRIDGISTIC_DIR . 'includes/class-license.php' );
check( false === strpos( $source, 'WpisticClient' ), 'the free policy has no remote SDK client' );
check( false === strpos( $source, 'includes/sdk' ), 'the free policy does not load SDK files' );
