<?php
/**
 * Lets a key read its own usage + limits (read-only, no extra scope).
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Rest;

use Bridgistic\Usage;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UsageController extends Controller {

	public function register( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/usage',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'usage' ),
				'permission_callback' => array( $this, 'authenticate' ),
			)
		);
	}

	public function usage( WP_REST_Request $request ) {
		$ctx = (array) $request->get_param( '__ctx' );
		$key = (string) ( $ctx['key_id'] ?? '' );

		$summary = Usage::summary( $key );
		$quota   = (int) ( $ctx['monthly_quota'] ?? 0 );

		return $this->ok(
			array(
				'tier'         => (string) ( $ctx['tier'] ?? 'custom' ),
				'rate_limit'   => (int) ( $ctx['rate_limit'] ?? 120 ),
				'monthly_quota' => $quota,
				'usage'        => $summary,
				'quota_remaining' => $quota > 0 ? max( 0, $quota - (int) $summary['this_month'] ) : null,
				'plan'         => self::plan_block(),
			)
		);
	}

	/**
	 * Site plan and the free monthly allowance, safe to show an AI client.
	 *
	 * @return array<string,mixed>
	 */
	public static function plan_block(): array {
		$snap = \Bridgistic\PlanLimits::snapshot();
		$out  = array(
			'name'                    => $snap['is_pro'] ? (string) ( $snap['billing']['label'] ?? 'Pro' ) : 'Free',
			'monthly_requests_limit'  => $snap['is_pro'] ? null : (int) $snap['limit'],
			'monthly_requests_used'   => (int) $snap['used'],
			'monthly_requests_remaining' => $snap['remaining'],
			'resets_at'               => gmdate( 'c', (int) $snap['resets_at'] ),
			'active_schedules_limit'  => $snap['is_pro'] ? null : (int) $snap['schedules_limit'],
			'snapshots_limit'         => $snap['is_pro'] ? null : (int) $snap['snapshots_limit'],
			'audit_export'            => (bool) $snap['audit_export'],
		);
		if ( ! $snap['is_pro'] ) {
			$out['upgrade_url'] = \Bridgistic\PlanLimits::UPGRADE_URL;
			$out['pro_price']   = sprintf( '$%d/month or $%d/year', \Bridgistic\PlanLimits::PRO_MONTHLY_USD, \Bridgistic\PlanLimits::PRO_YEARLY_USD );
		}
		return $out;
	}
}
