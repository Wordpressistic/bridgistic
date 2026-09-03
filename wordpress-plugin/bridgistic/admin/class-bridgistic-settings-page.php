<?php
/**
 * Settings: options allowlist (unchanged behavior, restyled).
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsPage extends Page {

	protected function view(): string {
		return 'settings';
	}

	protected function data(): array {
		return array(
			'allowlist'  => (array) get_option( 'bridgistic_options_allowlist', array() ),
			'action_url' => admin_url( 'admin-post.php' ),
			'saved'      => isset( $_GET['saved'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
		);
	}
}
