<?php
/**
 * License admin page: connect the site to a WPistic account / activate a
 * license key to unlock paid plans. Backed by the vendored WPistic SDK.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

use Bridgistic\License;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LicensePage extends Page {

	protected function view(): string {
		return 'license';
	}

	protected function data(): array {
		return array(
			'status'  => License::get_status(),
			'gates'   => array(
				'scheduled_playbooks_unlimited' => License::gate( 'scheduled_playbooks_unlimited' ),
				'snapshots_advanced'            => License::gate( 'snapshots_advanced' ),
				'audit_export'                  => License::gate( 'audit_export' ),
				'skills_marketplace'            => License::gate( 'skills_marketplace' ),
				'agency_dashboard'              => License::gate( 'agency_dashboard' ),
			),
		);
	}

	/**
	 * Handle activate / deactivate / re-validate POSTs (nonce + cap checked).
	 * Hooked on admin_init by Admin\Controller::hooks().
	 */
	public static function handle_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = isset( $_POST['bridgistic_license_action'] ) ? sanitize_key( wp_unslash( $_POST['bridgistic_license_action'] ) ) : '';
		if ( '' === $action ) {
			return;
		}
		check_admin_referer( 'bridgistic_license_action', 'bridgistic_license_nonce' );

		if ( 'activate' === $action ) {
			$key    = isset( $_POST['bridgistic_license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['bridgistic_license_key'] ) ) : '';
			$result = License::activate( $key );
			if ( ! empty( $result['ok'] ) ) {
				add_settings_error( 'bridgistic_license', 'activated', __( 'License activated — paid features unlocked. Thank you for supporting Bridgistic!', 'bridgistic' ), 'success' );
			} else {
				add_settings_error( 'bridgistic_license', 'activate_failed', (string) ( $result['message'] ?? __( 'Activation failed.', 'bridgistic' ) ), 'error' );
			}
		} elseif ( 'deactivate' === $action ) {
			License::deactivate();
			add_settings_error( 'bridgistic_license', 'deactivated', __( 'License deactivated on this site. Running in Free mode.', 'bridgistic' ), 'success' );
		} elseif ( 'check' === $action ) {
			$result = License::validate_now();
			if ( ! empty( $result['ok'] ) ) {
				add_settings_error( 'bridgistic_license', 'checked', __( 'License re-validated against wpistic.com.', 'bridgistic' ), 'success' );
			} else {
				add_settings_error( 'bridgistic_license', 'check_failed', (string) ( $result['message'] ?? __( 'Validation failed.', 'bridgistic' ) ), 'error' );
			}
		}
	}
}
