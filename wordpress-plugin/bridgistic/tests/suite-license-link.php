<?php
/**
 * License link: signature verification, secret hygiene and status shape.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

require_once BRIDGISTIC_DIR . 'includes/class-license-link.php';

use Bridgistic\LicenseLink;

bridgistic_suite( 'license-link' );

// Canonical JSON matches the WPistic platform contract: sorted keys, no whitespace, {} for empty maps.
check_equals( '{"a":1,"b":{"c":true,"d":null},"e":[2,1],"f":{}}', LicenseLink::canonical_json( array( 'b' => array( 'd' => null, 'c' => true ), 'a' => 1, 'e' => array( 2, 1 ), 'f' => array() ) ), 'canonical json is sorted and compact' );
check_equals( '"https://x.test/é"', LicenseLink::canonical_json( 'https://x.test/é' ), 'slashes and unicode are not escaped' );

$vk      = str_repeat( 'ab', 32 );
$payload = array( 'valid' => true, 'status' => 'active', 'product' => 'bridgistic', 'plan' => 'lifetime', 'expires_at' => null, 'entitlements' => array( 'bridgistic.plan' => 'lifetime', 'seoistic.pro.enabled' => true ) );
$signed  = $payload + array( 'signature' => hash_hmac( 'sha256', LicenseLink::canonical_json( $payload ), (string) hex2bin( $vk ) ), 'activation_token' => 'tok', 'verification_key' => $vk );

check( LicenseLink::verify( $signed, $vk ), 'a correctly signed response verifies (token and key excluded from the digest)' );
$tampered            = $signed;
$tampered['plan']    = 'agency';
check( ! LicenseLink::verify( $tampered, $vk ), 'a tampered response is rejected' );
$unsigned = $signed;
unset( $unsigned['signature'] );
check( ! LicenseLink::verify( $unsigned, $vk ), 'an unsigned response is rejected' );
check( ! LicenseLink::verify( $signed, 'zz' ), 'a malformed verification key is rejected' );
check( ! LicenseLink::verify( $signed, str_repeat( 'cd', 32 ) ), 'the wrong key is rejected' );

check_equals( array( 'bridgistic.plan' => 'lifetime' ), LicenseLink::bridgistic_entitlements( $payload['entitlements'] ), 'only the bridgistic.* slice is kept' );
check_equals( 'wpk_li…3456', LicenseLink::mask( 'wpk_live_abcdef123456' ), 'keys are masked' );

$st = LicenseLink::status();
check_equals( false, $st['connected'], 'no license by default' );
check_equals( 'free', $st['status'], 'default status is free' );
check( ! array_key_exists( 'activation_token', $st ) && ! array_key_exists( 'verification_key', $st ), 'status never exposes secrets' );

$src = (string) file_get_contents( BRIDGISTIC_DIR . 'includes/rest/class-dashboard-controller.php' );
check( false === strpos( $src, 'Approvals::decide' ), 'the dashboard API cannot decide approvals' );
