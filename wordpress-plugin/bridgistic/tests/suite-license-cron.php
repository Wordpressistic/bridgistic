<?php
/**
 * 1.5.10: daily license re-validation cron and remote deactivate on uninstall.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

require_once BRIDGISTIC_DIR . 'includes/class-license-link.php';

use Bridgistic\LicenseLink;

bridgistic_suite( 'license-cron' );

$bridgistic_vk   = str_repeat( 'cd', 32 );
$bridgistic_sign = static function ( array $response ) use ( $bridgistic_vk ): array {
	$payload = $response;
	unset( $payload['signature'], $payload['activation_token'], $payload['verification_key'] );
	$response['signature'] = hash_hmac( 'sha256', LicenseLink::canonical_json( $payload ), (string) hex2bin( $bridgistic_vk ) );
	return $response;
};
$bridgistic_base = array(
	'success'      => true,
	'product'      => 'bridgistic',
	'status'       => 'active',
	'plan'         => 'pro',
	'expires_at'   => '2099-01-01T00:00:00Z',
	'member'       => 'bu•••@example.com',
	'activation'   => array( 'id' => 'act-1', 'domain' => 'unit.test', 'environment' => 'production' ),
	'entitlements' => array(),
);

$GLOBALS['__cron']           = array();
$GLOBALS['__http_responses'] = array();
unset( $GLOBALS['__options'][ LicenseLink::OPTION ] );

// No license: schedule() never leaves a cron behind.
LicenseLink::schedule();
check( false === wp_next_scheduled( LicenseLink::CRON_HOOK ), 'no cron without an activated license' );

// Activation schedules a daily validate.
$GLOBALS['__http_responses'][ LicenseLink::DEFAULT_API . '/activate' ] = static function ( array $args ) use ( $bridgistic_sign, $bridgistic_base, $bridgistic_vk ): array {
	return $bridgistic_sign( $bridgistic_base + array( 'activation_token' => 'cron-token', 'verification_key' => $bridgistic_vk ) );
};
$bridgistic_activated = LicenseLink::activate( 'WPI-PRO-CRON-0001' );
check( is_array( $bridgistic_activated ) && 'active' === $bridgistic_activated['status'], 'activation succeeds' );
check( false !== wp_next_scheduled( LicenseLink::CRON_HOOK ), 'activation schedules the daily validate' );
check_equals( 'daily', $GLOBALS['__cron'][ LicenseLink::CRON_HOOK ]['recurrence'] ?? '', 'the validate cron runs daily' );
$bridgistic_first = wp_next_scheduled( LicenseLink::CRON_HOOK );
LicenseLink::schedule();
check_equals( $bridgistic_first, wp_next_scheduled( LicenseLink::CRON_HOOK ), 'schedule() is idempotent' );

// The cron re-sends the dashboard key and refreshes the signed status.
$GLOBALS['__http_responses'][ LicenseLink::DEFAULT_API . '/validate' ] = static function ( array $args ) use ( $bridgistic_sign, $bridgistic_base ): array {
	$GLOBALS['__cron_validate_body'] = json_decode( (string) $args['body'], true );
	return $bridgistic_sign( array( 'status' => 'grace_period' ) + $bridgistic_base );
};
LicenseLink::cron_validate();
$bridgistic_body = $GLOBALS['__cron_validate_body'] ?? array();
check_equals( 'cron-token', (string) ( $bridgistic_body['activation_token'] ?? '' ), 'cron validate carries the activation token' );
check( ! empty( $bridgistic_body['dashboard_key']['key_id'] ) && ! empty( $bridgistic_body['dashboard_key']['secret'] ), 'cron validate re-sends the dashboard key' );
check_equals( 'grace_period', LicenseLink::status()['status'], 'cron validate refreshes the site status' );

// A forged response is discarded; the last good status stays.
$GLOBALS['__http_responses'][ LicenseLink::DEFAULT_API . '/validate' ] = static function () use ( $bridgistic_base ): array {
	return array( 'status' => 'active', 'signature' => str_repeat( '0', 64 ) ) + $bridgistic_base;
};
LicenseLink::cron_validate();
check_equals( 'grace_period', LicenseLink::status()['status'], 'an unsigned/forged cron response does not change status' );
check( ! empty( $GLOBALS['__options'][ LicenseLink::OPTION ]['last_error'] ), 'the cron records the failure' );

// Server says the activation is gone (seat released from the account): paid features close, token kept for Check now.
$GLOBALS['__http_responses'][ LicenseLink::DEFAULT_API . '/validate' ] = static function (): array {
	return array( '__status' => 404, 'success' => false, 'error' => array( 'code' => 'activation_not_found', 'message' => 'This activation no longer exists.' ) );
};
LicenseLink::cron_validate();
check_equals( 'activation_not_found', LicenseLink::status()['status'], 'a released seat closes paid features' );
check( ! empty( $GLOBALS['__options'][ LicenseLink::OPTION ]['activation_token'] ), 'the token is kept so Check now can retry' );

// Unreachable server: status unchanged, no fatal.
unset( $GLOBALS['__http_responses'][ LicenseLink::DEFAULT_API . '/validate' ] );
LicenseLink::cron_validate();
check_equals( 'activation_not_found', LicenseLink::status()['status'], 'an unreachable server leaves the status as it was' );

// Deactivate releases the seat remotely and drops the cron.
$GLOBALS['__http_responses'][ LicenseLink::DEFAULT_API . '/deactivate' ] = static function ( array $args ): array {
	$GLOBALS['__cron_deactivate_body'] = json_decode( (string) $args['body'], true );
	return array( 'success' => true, 'status' => 'deactivated' );
};
LicenseLink::deactivate();
check_equals( 'cron-token', (string) ( $GLOBALS['__cron_deactivate_body']['activation_token'] ?? '' ), 'deactivate sends the activation token to the server' );
check( false === wp_next_scheduled( LicenseLink::CRON_HOOK ), 'deactivate clears the validate cron' );
check( ! isset( $GLOBALS['__options'][ LicenseLink::OPTION ] ), 'deactivate deletes the license link' );

// cron_validate with nothing stored unschedules itself.
wp_schedule_event( time(), 'daily', LicenseLink::CRON_HOOK );
LicenseLink::cron_validate();
check( false === wp_next_scheduled( LicenseLink::CRON_HOOK ), 'a stray cron with no license removes itself' );

// Wiring: the plugin registers the hook and clears it on deactivation; uninstall deactivates remotely.
$bridgistic_plugin_src    = (string) file_get_contents( BRIDGISTIC_DIR . 'includes/class-plugin.php' );
$bridgistic_uninstall_src = (string) file_get_contents( BRIDGISTIC_DIR . 'uninstall.php' );
check( false !== strpos( $bridgistic_plugin_src, "add_action( LicenseLink::CRON_HOOK, array( LicenseLink::class, 'cron_validate' ) )" ), 'boot registers the cron callback' );
check( false !== strpos( $bridgistic_plugin_src, 'wp_clear_scheduled_hook( LicenseLink::CRON_HOOK )' ), 'plugin deactivation clears the cron' );
check( false !== strpos( $bridgistic_uninstall_src, 'LicenseLink::deactivate()' ), 'uninstall releases the seat on the license server' );
check( false !== strpos( $bridgistic_uninstall_src, "delete_option( 'bridgistic_license_link' )" ), 'uninstall deletes the license link option' );
check( false !== strpos( $bridgistic_uninstall_src, "wp_clear_scheduled_hook( 'bridgistic_license_validate' )" ), 'uninstall clears the validate cron' );

// Uninstall end-to-end, in a child process (uninstall drops the shared test tables).
$bridgistic_out = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/harness-uninstall.php' ) ); // phpcs:ignore
$bridgistic_res = json_decode( (string) $bridgistic_out, true );
check_equals( 'uninstall-token', (string) ( $bridgistic_res['deactivate_token'] ?? '' ), 'uninstall calls the remote deactivate' );
check_equals( false, $bridgistic_res['link_left'] ?? true, 'uninstall removes the license link' );
check_equals( false, $bridgistic_res['cron_left'] ?? true, 'uninstall removes the cron' );

unset( $GLOBALS['__http_responses'], $GLOBALS['__options'][ LicenseLink::OPTION ] );
