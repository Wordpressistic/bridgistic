<?php
/**
 * The public plugin must stay a deterministic free-only build.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

require_once BRIDGISTIC_DIR . 'includes/class-license.php';

use Bridgistic\License;

bridgistic_suite( 'license-free-only' );

$paid_features = array(
    'scheduled_playbooks_unlimited',
    'snapshots_advanced',
    'audit_export',
    'skills_marketplace',
    'agency_dashboard',
    'team_permissions',
    'white_label',
);

check_equals( 'free', License::plan(), 'the public package always reports the free plan' );
check( License::FREE_ONLY, 'the free-only policy marker is enabled' );
check( ! License::is_premium(), 'the public package can never report a premium plan' );
check( ! License::is_connected(), 'the public package has no license connection state' );

foreach ( $paid_features as $feature ) {
    check( ! License::gate( $feature ), $feature . ' stays locked' );
}

check( License::gate( 'scheduled_playbooks_basic' ), 'the free schedule feature remains available' );
check( License::gate( 'site_read' ), 'security and bridge features are not commercial gates' );
check_equals( false, License::activate( 'wpk_test_key' )['ok'], 'license activation is unavailable' );
check_equals( false, License::validate_now()['ok'], 'remote validation is unavailable' );
check_equals( 'free_only', License::get_status()['status'], 'status identifies the free-only build' );

$source = (string) file_get_contents( BRIDGISTIC_DIR . 'includes/class-license.php' );
check( false === strpos( $source, 'WpisticClient' ), 'the free policy has no remote SDK client' );
check( false === strpos( $source, 'includes/sdk' ), 'the free policy does not load SDK files' );
