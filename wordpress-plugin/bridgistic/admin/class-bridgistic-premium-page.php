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
		return array(
			'license'    => LicenseLink::status(),
			'action_url' => admin_url( 'admin-post.php' ),
			'notice'     => isset( $_GET['license'] ) ? sanitize_key( wp_unslash( $_GET['license'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'sections'   => array(
				array(
					'icon'        => 'sparkle',
					'label'       => __( 'AI Skills Marketplace', 'bridgistic' ),
					'description' => __( 'Prebuilt expert skills Claude can run against your site.', 'bridgistic' ),
					'items'       => array(
						__( 'SEO Audit', 'bridgistic' ),
						__( 'AIO Audit', 'bridgistic' ),
						__( 'Schema Audit', 'bridgistic' ),
						__( 'Robots.txt Optimization', 'bridgistic' ),
						__( 'Metadata Optimization', 'bridgistic' ),
						__( 'Design Audit', 'bridgistic' ),
					),
				),
				array(
					'icon'        => 'dashboard',
					'label'       => __( 'Multi-Site Agency Dashboard', 'bridgistic' ),
					'description' => __( 'Manage many client websites from one dashboard.', 'bridgistic' ),
					'items'       => array(),
				),
				array(
					'icon'        => 'list',
					'label'       => __( 'Advanced Logs & Snapshots', 'bridgistic' ),
					'description' => __( 'Full history, rollback, approval analytics.', 'bridgistic' ),
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
