<?php
/**
 * Dashboard: hero, connection status, and stat cards.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

use Bridgistic\AuditLog;
use Bridgistic\Playbooks;
use Bridgistic\Snapshot;
use Bridgistic\Security\KeyStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DashboardPage extends Page {

	protected function view(): string {
		return 'dashboard';
	}

	/**
	 * The subset of dashboard state that can go stale between page loads —
	 * shared by the initial render and Actions::ajax_dashboard_status() so
	 * the "Connected" badge, activity count, and latest log line can be
	 * refreshed live via polling instead of only on a full page reload.
	 *
	 * @return array<string,mixed>
	 */
	public static function live_stats(): array {
		$keys      = KeyStore::list_all();
		$enabled   = array_filter( $keys, static fn( $k ) => (int) $k['enabled'] === 1 );
		$last_used = null;
		foreach ( $keys as $k ) {
			if ( ! empty( $k['last_used_at'] ) && ( null === $last_used || $k['last_used_at'] > $last_used ) ) {
				$last_used = (string) $k['last_used_at'];
			}
		}

		$latest_log = AuditLog::latest();

		$connected = false;
		if ( $last_used && strtotime( $last_used . ' UTC' ) > ( time() - 7 * DAY_IN_SECONDS ) ) {
			$connected = true;
		}

		return array(
			'keys_total'   => count( $keys ),
			'keys_enabled' => count( $enabled ),
			'connected'    => $connected,
			'last_used'    => $last_used,
			'audit_count'  => AuditLog::count(),
			'latest_log'   => $latest_log,
		);
	}

	protected function data(): array {
		$health = (array) get_option( 'bridgistic_last_health', array() );

		return array_merge(
			self::live_stats(),
			array(
				'snapshots'     => count( Snapshot::list_recent( 200 ) ),
				'playbooks'     => count( Playbooks::list() ),
				'health_score'  => isset( $health['score'] ) ? (int) $health['score'] : null,
				'health_time'   => isset( $health['time'] ) ? (int) $health['time'] : null,
				'setup_url'     => admin_url( 'admin.php?page=bridgistic-setup' ),
				'health_url'    => admin_url( 'admin.php?page=bridgistic-health' ),
				'keys_url'      => admin_url( 'admin.php?page=bridgistic-keys' ),
				'logs_url'      => admin_url( 'admin.php?page=bridgistic-logs' ),
				'snapshots_url' => admin_url( 'admin.php?page=bridgistic-snapshots' ),
			)
		);
	}
}
