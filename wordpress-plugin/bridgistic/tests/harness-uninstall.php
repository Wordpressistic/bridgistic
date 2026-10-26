<?php
/**
 * Child-process harness for suite-license-cron: runs uninstall.php against the
 * stubbed WordPress and prints what it did as JSON.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

require_once __DIR__ . '/bootstrap.php';
require_once BRIDGISTIC_DIR . 'includes/class-license-link.php';

use Bridgistic\LicenseLink;

if ( ! function_exists( 'wp_unschedule_hook' ) ) {
	function wp_unschedule_hook( string $hook ): int { // phpcs:ignore
		return wp_clear_scheduled_hook( $hook );
	}
}

$GLOBALS['__options'][ LicenseLink::OPTION ] = array( 'activation_token' => 'uninstall-token', 'verification_key' => str_repeat( 'cd', 32 ), 'response' => array( 'status' => 'active' ) );
wp_schedule_event( time(), 'daily', LicenseLink::CRON_HOOK );
$GLOBALS['__seen'] = '';
$GLOBALS['__http_responses'][ LicenseLink::DEFAULT_API . '/deactivate' ] = static function ( array $args ): array {
	$GLOBALS['__seen'] = (string) ( json_decode( (string) $args['body'], true )['activation_token'] ?? '' );
	return array( 'success' => true, 'status' => 'deactivated' );
};
define( 'WP_UNINSTALL_PLUGIN', 'bridgistic/bridgistic.php' );
ob_start();
require BRIDGISTIC_DIR . 'uninstall.php';
ob_end_clean();
echo wp_json_encode(
	array(
		'deactivate_token' => $GLOBALS['__seen'],
		'link_left'        => isset( $GLOBALS['__options'][ LicenseLink::OPTION ] ),
		'cron_left'        => false !== wp_next_scheduled( LicenseLink::CRON_HOOK ),
	)
);
