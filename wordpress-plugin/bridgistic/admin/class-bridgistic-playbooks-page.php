<?php
/**
 * Playbooks: built-in manual playbooks, saved (agent-created) playbooks,
 * limited scheduled playbooks, and locked premium automation cards.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

use Bridgistic\Playbooks;
use Bridgistic\Scheduler;
use Bridgistic\Security\Scopes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PlaybooksPage extends Page {

	protected function view(): string {
		return 'playbooks';
	}

	protected function data(): array {
		$runs = (array) get_option( 'bridgistic_builtin_playbook_runs', array() );

		$builtin = array(
			'backup_before_edit' => array(
				'label'       => __( 'Backup before edit', 'bridgistic' ),
				'description' => __( 'Snapshot posts and post meta so any content change can be rolled back.', 'bridgistic' ),
				'permission'  => Scopes::SNAPSHOT,
				'icon'        => 'camera',
			),
			'site_inspection'    => array(
				'label'       => __( 'Safe site inspection', 'bridgistic' ),
				'description' => __( 'Read-only summary of versions, theme, plugins, keys, and HTTPS state.', 'bridgistic' ),
				'permission'  => Scopes::SITE_READ,
				'icon'        => 'eye',
			),
			'plugin_audit'       => array(
				'label'       => __( 'Basic plugin audit', 'bridgistic' ),
				'description' => __( 'Count active, inactive, and update-pending plugins. Read-only.', 'bridgistic' ),
				'permission'  => Scopes::SITE_READ,
				'icon'        => 'plug',
			),
			'content_checklist'  => array(
				'label'       => __( 'Content cleanup checklist', 'bridgistic' ),
				'description' => __( 'Count drafts, trashed items, and stored revisions worth reviewing.', 'bridgistic' ),
				'permission'  => Scopes::POSTS_READ,
				'icon'        => 'list',
			),
		);
		foreach ( $builtin as $slug => &$b ) {
			$b['last_run'] = isset( $runs[ $slug ] ) ? (int) $runs[ $slug ] : null;
		}
		unset( $b );

		$premium = array(
			array( 'label' => __( 'SEO Audit Automation', 'bridgistic' ), 'icon' => 'globe' ),
			array( 'label' => __( 'Schema Audit Automation', 'bridgistic' ), 'icon' => 'code' ),
			array( 'label' => __( 'WooCommerce Growth Audit', 'bridgistic' ), 'icon' => 'tag' ),
			array( 'label' => __( 'AI Design Review', 'bridgistic' ), 'icon' => 'sparkle' ),
			array( 'label' => __( 'Weekly Site Intelligence Report', 'bridgistic' ), 'icon' => 'pulse' ),
		);

		return array(
			'builtin'    => $builtin,
			'saved'      => Playbooks::list(),
			'schedules'  => Scheduler::list(),
			'cron_off'   => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'cron_url'   => site_url( 'wp-cron.php' ),
			'premium'    => $premium,
			'action_url' => admin_url( 'admin-post.php' ),
		);
	}
}
