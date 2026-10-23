<?php
/**
 * Premium Features: the license link plus a locked showcase of the Bridgistic
 * SaaS direction. A live WPistic license opens the three shipped gates
 * (unlimited schedules, advanced snapshots, audit export); the marketplace,
 * agency, team and white-label lines stay SaaS.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

use Bridgistic\LicenseLink;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

	final class PremiumPage extends Page {

		protected function view(): string {
			return 'premium';
		}

		protected function data(): array {
			$licensed = \Bridgistic\License::has_active_license();
			return array(
				'license'    => LicenseLink::status(),
				'action_url' => admin_url( 'admin-post.php' ),
				'notice'     => isset( $_GET['license'] ) ? sanitize_key( wp_unslash( $_GET['license'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'sections'   => array(
					array(
						'icon'        => 'sparkle',
						'label'       => __( 'AI Skills Marketplace', 'bridgistic' ),
						'description' => __( '100 free instruction skills for your AI agents — browse free, and the Pro collection of 30 production skills for license holders.', 'bridgistic' ),
						'items'       => array(),
						'live'        => true,
						'live_badge'  => __( 'Live', 'bridgistic' ),
						'live_href'   => 'https://marketplace.bridgistic.app/',
						'live_cta'    => __( 'Open the marketplace', 'bridgistic' ),
					),
					array(
						'icon'        => 'list',
						'label'       => __( 'Advanced Logs & Snapshots', 'bridgistic' ),
						'description' => $licensed
							? __( 'Included with your live license: audit log export and snapshots past the free cap of 50.', 'bridgistic' )
							: __( 'Audit log export and snapshots past the free cap of 50 — opens the moment a license is active on this site.', 'bridgistic' ),
						'items'       => array(),
						'live'        => $licensed,
						'live_badge'  => __( 'Included with your license', 'bridgistic' ),
						'live_href'   => admin_url( 'admin.php?page=bridgistic-logs' ),
						'live_cta'    => __( 'Open the logs', 'bridgistic' ),
					),
					array(
						'icon'        => 'dashboard',
						'label'       => __( 'Multi-Site Agency Dashboard', 'bridgistic' ),
						'description' => __( 'Manage many client websites from one dashboard.', 'bridgistic' ),
						'items'       => array(),
					),
					array(
						'icon'        => 'users',
						'label'       => __( 'Team Permissions', 'bridgistic' ),
						'description' => __( 'Roles, team members, client approval workflow.', 'bridgistic' ),
						'items'       => array(),
					),
					array(
						'icon'        => 'tag',
						'label'       => __( 'White Label', 'bridgistic' ),
						'description' => __( 'Agency-branded client portal.', 'bridgistic' ),
						'items'       => array(),
					),
				),
			);
		}
	}
