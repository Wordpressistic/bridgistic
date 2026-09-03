<?php
/**
 * Plugin Name:       Bridgistic
 * Plugin URI:        https://github.com/wordpressistic/bridgistic
 * Description:        Connect your WordPress site to any AI model — Claude, ChatGPT, Codex, Gemini, Cursor or any MCP client — with production-safe, scoped control — HMAC-signed requests, least-privilege keys, dry-run and human approval on destructive ops, one-call rollback, full audit, usage metering, and scheduled playbooks.
 * Version:           1.3.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Shuvo Sarker (WordPressistic)
 * Author URI:        https://wordpressistic.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bridgistic
 * Domain Path:       /languages
 *
 * Bridgistic — part of the WordPressistic Galaxy.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'BRIDGISTIC_VERSION', '1.3.0' );
define( 'BRIDGISTIC_FILE', __FILE__ );
define( 'BRIDGISTIC_DIR', plugin_dir_path( __FILE__ ) );
define( 'BRIDGISTIC_URL', plugin_dir_url( __FILE__ ) );
define( 'BRIDGISTIC_REST_NAMESPACE', 'bridgistic/v1' );

/**
 * Directory where the agent is allowed to write executable PHP.
 * Anywhere WordPress autoloads from (plugins, mu-plugins, themes) is OFF-LIMITS
 * for PHP writes, exactly like Novamira's sandbox model — but enforced server-side.
 */
define( 'BRIDGISTIC_SANDBOX', 'bridgistic-sandbox' );

// PSR-style lightweight autoloader for our includes.
spl_autoload_register(
	static function ( string $class ): void {
		if ( strpos( $class, 'Bridgistic\\' ) !== 0 ) {
			return;
		}

		$relative = substr( $class, strlen( 'Bridgistic\\' ) );
		$relative = str_replace( '\\', '/', $relative );

		// Convert PascalCase class to kebab-case file: class-{name}.php.
		$parts     = explode( '/', $relative );
		$file_name = array_pop( $parts );
		$file_name = 'class-' . strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $file_name ) ) . '.php';
		$subdir    = $parts ? strtolower( implode( '/', $parts ) ) . '/' : '';

		$path = BRIDGISTIC_DIR . 'includes/' . $subdir . $file_name;

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( 'Bridgistic\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Bridgistic\Plugin', 'deactivate' ) );

/*
 * The plugin ships its own License tab (admin.php?page=bridgistic-license).
 * Suppress the SDK's duplicate "Settings → Bridgistic License" menu entry.
 */
add_filter( 'wpistic_sdk_show_settings_menu', '__return_false' );

add_action(
	'plugins_loaded',
	static function (): void {
		\Bridgistic\Plugin::instance()->boot();
	}
);
