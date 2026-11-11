<?php
/**
 * Free vs Pro plan limits for the whole site (1.5.11).
 *
 * The free plugin includes a monthly allowance of AI requests, shared by every
 * key on the site and reset on the first day of each month (UTC). A live
 * Bridgistic Pro license removes the monthly cap. The allowance is the same
 * number the billing tiers already define for "free" in Usage::tiers().
 *
 * What counts: every signed request an AI client makes to the bridgistic/v1
 * REST API (local MCP server, Claude Desktop extension, Bridgistic Cloud).
 * What does not count: the auto-paired Bridgistic dashboard key and the
 * read-only usage lookup, so checking the meter never spends it.
 *
 * When the allowance is used up, requests stop with HTTP 402 and a message the
 * AI client shows to the user, pointing to the Pro upgrade.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PlanLimits {

	/** Usage-table key that holds the site-wide monthly counter. */
	public const SITE_KEY = '_site';

	/** Where a visitor buys Pro. */
	public const UPGRADE_URL = 'https://pay.bridgistic.app/';

	/** Published Pro prices (USD), matching pay.bridgistic.app and the console billing map. */
	public const PRO_MONTHLY_USD = 12;
	public const PRO_YEARLY_USD  = 99;

	/** Routes that never spend or block on the allowance. */
	private const EXEMPT_ROUTES = array( '/bridgistic/v1/usage' );

	/** Key tiers that are not AI clients (the paired account dashboard). */
	private const EXEMPT_TIERS = array( 'dashboard' );

	/** Monthly AI requests included in the free plugin (filterable). */
	public static function free_monthly_requests(): int {
		$tiers   = Usage::tiers();
		$default = (int) ( $tiers['free']['quota'] ?? 5000 );
		$value   = (int) apply_filters( 'bridgistic_free_monthly_requests', $default );
		return max( 1, $value );
	}

	/** True while a live Pro license (active, trialing or grace) is on this site. */
	public static function is_pro(): bool {
		return License::has_active_license();
	}

	/** Current billing window (UTC), e.g. 202611. */
	public static function month_bucket( ?int $now = null ): string {
		return gmdate( 'Ym', $now ?? time() );
	}

	/** First second of next month, UTC. */
	public static function resets_at( ?int $now = null ): int {
		$now = $now ?? time();
		$y   = (int) gmdate( 'Y', $now );
		$m   = (int) gmdate( 'n', $now );
		return (int) gmmktime( 0, 0, 0, 12 === $m ? 1 : $m + 1, 1, 12 === $m ? $y + 1 : $y );
	}

	/** AI requests counted this month for the whole site. */
	public static function used_this_month(): int {
		return Usage::get( self::SITE_KEY, self::month_bucket(), 'req' );
	}

	/** Does this request spend the monthly allowance? */
	public static function counts( array $ctx, string $route = '' ): bool {
		if ( in_array( (string) ( $ctx['tier'] ?? '' ), self::EXEMPT_TIERS, true ) ) {
			return false;
		}
		return ! in_array( rtrim( $route, '/' ), self::EXEMPT_ROUTES, true );
	}

	/**
	 * Check the allowance and, when allowed, count the request.
	 *
	 * @param array<string,mixed> $ctx Auth context from HmacVerifier.
	 * @return true|WP_Error
	 */
	public static function check_and_count( array $ctx, string $route = '' ) {
		if ( ! self::counts( $ctx, $route ) ) {
			return true;
		}
		$bucket = self::month_bucket();
		if ( ! self::is_pro() ) {
			$limit = self::free_monthly_requests();
			$used  = Usage::get( self::SITE_KEY, $bucket, 'req' );
			if ( $used >= $limit ) {
				return self::limit_error( $limit, $used );
			}
		}
		Usage::incr( self::SITE_KEY, $bucket, 'req' );
		return true;
	}

	/** The error an AI client relays to the person using it. */
	public static function limit_error( int $limit, int $used ): WP_Error {
		$reset = self::resets_at();
		return new WP_Error(
			'bridgistic_free_limit_reached',
			sprintf(
				/* translators: 1: monthly limit, 2: reset date, 3: monthly price, 4: yearly price, 5: upgrade URL. */
				__( 'This site has used all %1$s AI requests included in the Bridgistic Free plan this month. The allowance resets on %2$s (UTC). To keep working now, upgrade to Bridgistic Pro ($%3$d/month or $%4$d/year) at %5$s and activate the license in WP Admin → Bridgistic → Premium Features. Pro has no monthly request cap.', 'bridgistic' ),
				number_format( $limit ),
				gmdate( 'F j, Y', $reset ),
				self::PRO_MONTHLY_USD,
				self::PRO_YEARLY_USD,
				self::UPGRADE_URL
			),
			array(
				'status'      => 402,
				'plan'        => 'free',
				'limit'       => $limit,
				'used'        => min( $used, $limit ),
				'remaining'   => 0,
				'resets_at'   => gmdate( 'c', $reset ),
				'upgrade_url' => self::UPGRADE_URL,
				'pro_prices'  => array(
					'monthly_usd' => self::PRO_MONTHLY_USD,
					'yearly_usd'  => self::PRO_YEARLY_USD,
				),
			)
		);
	}

	/**
	 * What the license says was bought. Reads the signed license response:
	 * an explicit billing block when the server sends one, otherwise the plan
	 * code (monthly, yearly, lifetime) mapped to its published price.
	 *
	 * @param array<string,mixed> $link LicenseLink::status() plus raw response fields.
	 * @return array{plan:string,label:string,price:string,interval:string,amount:float|null,currency:string}
	 */
	public static function pro_billing( array $link ): array {
		$plan    = strtolower( (string) ( $link['plan'] ?? '' ) );
		$billing = (array) ( $link['billing'] ?? array() );
		$amount  = null;
		if ( isset( $billing['amount_cents'] ) && is_numeric( $billing['amount_cents'] ) ) {
			$amount = ( (float) $billing['amount_cents'] ) / 100;
		} elseif ( isset( $billing['amount'] ) && is_numeric( $billing['amount'] ) ) {
			$amount = (float) $billing['amount'];
		}
		$cur = strtoupper( (string) ( $billing['currency'] ?? 'USD' ) );
		$int = strtolower( (string) ( $billing['interval'] ?? '' ) );

		if ( null === $amount ) {
			if ( 'monthly' === $plan ) {
				$amount = (float) self::PRO_MONTHLY_USD;
				$int    = 'month';
			} elseif ( 'yearly' === $plan ) {
				$amount = (float) self::PRO_YEARLY_USD;
				$int    = 'year';
			} elseif ( 'lifetime' === $plan ) {
				$int = 'lifetime';
			}
		}

		$labels = array(
			'monthly'  => __( 'Pro Monthly', 'bridgistic' ),
			'yearly'   => __( 'Pro Yearly', 'bridgistic' ),
			'lifetime' => __( 'Pro Lifetime (AppSumo)', 'bridgistic' ),
		);
		$label = $labels[ $plan ] ?? ( '' !== $plan ? sprintf( 'Pro (%s)', $plan ) : 'Pro' );

		if ( null !== $amount && 'USD' === $cur ) {
			$num   = 0.0 === fmod( $amount, 1.0 ) ? number_format( $amount ) : number_format( $amount, 2 );
			$price = 'month' === $int ? sprintf( '$%s / month', $num ) : ( 'year' === $int ? sprintf( '$%s / year', $num ) : sprintf( '$%s', $num ) );
		} elseif ( null !== $amount ) {
			$price = sprintf( '%s %s%s', $cur, number_format( $amount, 2 ), $int && 'lifetime' !== $int ? ' / ' . $int : '' );
		} else {
			$price = 'lifetime' === $int ? __( 'One-time AppSumo purchase', 'bridgistic' ) : __( 'See your purchase receipt', 'bridgistic' );
		}

		return array(
			'plan'     => $plan,
			'label'    => $label,
			'price'    => $price,
			'interval' => $int,
			'amount'   => $amount,
			'currency' => $cur,
		);
	}

	/**
	 * Everything the Settings usage panel and the admin notice need.
	 *
	 * @return array<string,mixed>
	 */
	public static function snapshot(): array {
		$pro   = self::is_pro();
		$limit = self::free_monthly_requests();
		$used  = self::used_this_month();
		$reset = self::resets_at();

		$out = array(
			'is_pro'             => $pro,
			'used'               => $used,
			'limit'              => $pro ? 0 : $limit,
			'remaining'          => $pro ? null : max( 0, $limit - $used ),
			'percent'            => $pro ? 0 : (int) min( 100, floor( ( $used / max( 1, $limit ) ) * 100 ) ),
			'resets_at'          => $reset,
			'schedules_active'   => class_exists( Scheduler::class ) ? self::safe_int( array( Scheduler::class, 'enabled_count' ) ) : 0,
			'schedules_limit'    => $pro ? 0 : ( class_exists( Scheduler::class ) ? Scheduler::FREE_MAX_ACTIVE : 3 ),
			'snapshots_limit'    => $pro ? 0 : self::free_snapshot_cap(),
			'audit_export'       => $pro,
			'audit_days'         => 90,
			'upgrade_url'        => self::UPGRADE_URL,
		);

		if ( $pro ) {
			$link              = LicenseLink::status();
			$raw               = LicenseLink::raw_response();
			$link['billing']   = (array) ( $raw['billing'] ?? array() );
			$out['billing']    = self::pro_billing( $link );
			$out['renews_at']  = (string) ( $link['expires_at'] ?? '' );
			$out['key_mask']   = (string) ( $link['key_mask'] ?? '' );
			$out['status']     = (string) ( $link['status'] ?? '' );
		}
		return $out;
	}

	/** Free snapshot cap enforced by the admin snapshot action. */
	public static function free_snapshot_cap(): int {
		return class_exists( '\\Bridgistic\\Admin\\Actions' ) ? (int) \Bridgistic\Admin\Actions::SNAPSHOT_LIMIT : 50;
	}

	/** @param callable $fn Counter. */
	private static function safe_int( callable $fn ): int {
		try {
			return (int) $fn();
		} catch ( \Throwable $e ) {
			return 0;
		}
	}
}
