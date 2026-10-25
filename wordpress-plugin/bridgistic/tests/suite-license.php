<?php
/**
 * License policy and the license link.
 *
 * The public plugin stays a deterministic free-only build; License::activate()
 * and friends run the real LicenseLink HTTP contract since 1.5.5, so the
 * suite stubs wp_remote_post and covers the unreachable server, a signed
 * activation round-trip, validate (with the auto-paired dashboard key
 * re-push) and deactivation.
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

// ---- unreachable server ----------------------------------------------------
// wp_remote_post is stubbed in the bootstrap; with no handler queued the
// server is unreachable, so activation and validation degrade to ok=false
// instead of throwing.
$GLOBALS['__http_responses'] = array();

$activation = License::activate( 'wpk_test_key' );
check_equals( false, $activation['ok'], 'activation fails cleanly when the license server is unreachable' );
check_equals( false, License::validate_now()['ok'], 'validation fails cleanly when the license server is unreachable' );
check_equals( 'free_only', License::get_status()['status'], 'status identifies the free-only build with no activation' );

// ---- signed activation round-trip ------------------------------------------
// The stub returns a properly signed activation response (the plugin verifies
// it with the canonical-JSON HMAC algorithm and the raw verification key) and
// records the request body so the auto-paired dashboard key push is asserted.
$verification_key = str_repeat( 'ab', 32 ); // 64 hex chars -> 32 raw bytes.
$GLOBALS['__options'][ LicenseLink::UUID_OPTION ] = '11111111-2222-3333-4444-555555555555';
$GLOBALS['__http_responses'][ LicenseLink::DEFAULT_API . '/activate' ] = static function ( array $args ): array {
    $GLOBALS['__activate_body'] = json_decode( (string) $args['body'], true );
    $response                   = array(
        'success'          => true,
        'product'          => 'bridgistic',
        'status'           => 'active',
        'plan'             => 'lifetime',
        'expires_at'       => null,
        'member'           => 'bu•••••@example.com',
        'activation'       => array( 'domain' => 'unit.test', 'environment' => 'production', 'installation_uuid' => '11111111-2222-3333-4444-555555555555' ),
        'entitlements'     => array( 'bridgistic.schedules' => 'unlimited' ),
        'activation_token' => 'unit-activation-token',
        'verification_key' => str_repeat( 'ab', 32 ),
    );
    // Signature over canonical JSON minus signature/activation_token/verification_key.
    $payload               = $response;
    unset( $payload['signature'], $payload['activation_token'], $payload['verification_key'] );
    $response['signature'] = hash_hmac( 'sha256', LicenseLink::canonical_json( $payload ), hex2bin( str_repeat( 'ab', 32 ) ) );
    return $response;
};

$activated = License::activate( 'BRG-LTD-TEST-KEY-0001' );
check_equals( true, $activated['ok'], 'a signed activation response activates the site' );
check_equals( 'lifetime', (string) ( $activated['status']['plan'] ?? '' ), 'the activation carries the plan' );
$activate_body = $GLOBALS['__activate_body'] ?? array();
check_equals( 'BRG-LTD-TEST-KEY-0001', (string) ( $activate_body['key'] ?? '' ), 'the request body carries the raw key' );
check(
    ! empty( $activate_body['dashboard_key']['key_id'] ) && ! empty( $activate_body['dashboard_key']['secret'] ),
    'the auto-paired dashboard key is pushed at activation'
);
check(
    is_string( $activate_body['dashboard_key']['key_id'] ?? null ) && str_starts_with( $activate_body['dashboard_key']['key_id'], 'wpk_' ),
    'the pushed dashboard key looks like a bridge key'
);

// The stored activation now drives the feature gates.
check( License::has_active_license(), 'the stored activation opens the license gates' );
foreach ( $license_features as $feature ) {
    check( License::gate( $feature ), $feature . ' opens with an active license' );
}
foreach ( $closed_features as $feature ) {
    check( ! License::gate( $feature ), $feature . ' stays closed with an active license' );
}

// Validate replays the signed status and re-pushes the dashboard key.
$GLOBALS['__http_responses'][ LicenseLink::DEFAULT_API . '/validate' ] = static function ( array $args ): array {
    $GLOBALS['__validate_body'] = json_decode( (string) $args['body'], true );
    $response                   = array(
        'success'          => true,
        'product'          => 'bridgistic',
        'status'           => 'active',
        'plan'             => 'lifetime',
        'expires_at'       => null,
        'member'           => 'bu•••••@example.com',
        'activation'       => array( 'domain' => 'unit.test', 'environment' => 'production', 'installation_uuid' => '11111111-2222-3333-4444-555555555555' ),
        'entitlements'     => array(),
    );
    $payload               = $response;
    unset( $payload['signature'], $payload['activation_token'], $payload['verification_key'] );
    $response['signature'] = hash_hmac( 'sha256', LicenseLink::canonical_json( $payload ), hex2bin( str_repeat( 'ab', 32 ) ) );
    return $response;
};
$validated = License::validate_now();
check_equals( true, $validated['ok'], 'a signed validate response re-validates the site' );
$validate_body = $GLOBALS['__validate_body'] ?? array();
check_equals( 'unit-activation-token', (string) ( $validate_body['activation_token'] ?? '' ), 'validate carries the activation token' );
check( ! empty( $validate_body['dashboard_key']['secret'] ), 'validate re-pushes the dashboard key (self-healing)' );

// Deactivation clears local state and releases the seat.
$GLOBALS['__http_responses'][ LicenseLink::DEFAULT_API . '/deactivate' ] = static function ( array $args ): array {
    return array( 'success' => true, 'status' => 'deactivated' );
};
$deactivated = License::deactivate();
check_equals( true, $deactivated['ok'], 'deactivation succeeds' );
check( empty( $GLOBALS['__options'][ LicenseLink::OPTION ]['activation_token'] ), 'deactivation clears the stored activation' );

unset( $GLOBALS['__options'][ LicenseLink::OPTION ] );
check_equals( 'free_only', License::get_status()['status'], 'status returns to free-only after deactivation' );

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
unset( $GLOBALS['__http_responses'] );

$source = (string) file_get_contents( BRIDGISTIC_DIR . 'includes/class-license.php' );
check( false === strpos( $source, 'WpisticClient' ), 'the free policy has no remote SDK client' );
check( false === strpos( $source, 'includes/sdk' ), 'the free policy does not load SDK files' );
