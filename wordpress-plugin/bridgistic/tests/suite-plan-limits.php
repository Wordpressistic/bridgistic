<?php
/**
 * Free vs Pro plan limits (1.5.11): the site-wide monthly AI-request
 * allowance, the upgrade message AI clients receive, the Pro price read from
 * license data, and when the admin upgrade notice shows.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

require_once BRIDGISTIC_DIR . 'includes/class-license-link.php';
require_once BRIDGISTIC_DIR . 'includes/class-license.php';
require_once BRIDGISTIC_DIR . 'includes/class-usage.php';
require_once BRIDGISTIC_DIR . 'includes/class-plan-limits.php';
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
require_once BRIDGISTIC_DIR . 'admin/class-bridgistic-upgrade-notice.php';

use Bridgistic\LicenseLink;
use Bridgistic\PlanLimits;
use Bridgistic\Usage;
use Bridgistic\Admin\UpgradeNotice;

bridgistic_suite( 'plan-limits' );

/** Reset the site counter and drop any license. */
function bridgistic_pl_reset( int $used = 0 ): void {
	global $wpdb;
	$wpdb->query( "DELETE FROM wp_bridgistic_usage WHERE key_id = '_site'" );
	if ( $used > 0 ) {
		Usage::incr( PlanLimits::SITE_KEY, PlanLimits::month_bucket(), 'req', $used );
	}
	unset( $GLOBALS['__options'][ LicenseLink::OPTION ] );
}

/** Put a live license on the site, as LicenseLink stores it after activation. */
function bridgistic_pl_license( string $plan, string $status = 'active', array $extra = array() ): void {
	$GLOBALS['__options'][ LicenseLink::OPTION ] = array(
		'activation_token' => 'tok_test',
		'key_mask'         => 'BRG-YR…ABCD',
		'response'         => array_merge(
			array( 'status' => $status, 'plan' => $plan, 'expires_at' => '2027-11-05T11:26:08Z' ),
			$extra
		),
	);
}

$ai  = array( 'key_id' => 'wpk_ai', 'tier' => 'custom', 'rate_limit' => 120, 'monthly_quota' => 0 );
$dsh = array( 'key_id' => 'wpk_dash', 'tier' => 'dashboard', 'rate_limit' => 240, 'monthly_quota' => 0 );

// ---- the allowance itself ----------------------------------------------------
check_equals( 5000, PlanLimits::free_monthly_requests(), 'free plan includes 5,000 AI requests per month (Usage::tiers free quota)' );
check_equals( 5000, (int) Usage::tiers()['free']['quota'], 'the free billing tier and the plan limit are the same number' );

$jan = gmmktime( 12, 0, 0, 1, 15, 2027 );
$dec = gmmktime( 12, 0, 0, 12, 31, 2026 );
check_equals( gmmktime( 0, 0, 0, 2, 1, 2027 ), PlanLimits::resets_at( $jan ), 'allowance resets on the 1st of next month (UTC)' );
check_equals( gmmktime( 0, 0, 0, 1, 1, 2027 ), PlanLimits::resets_at( $dec ), 'December resets on January 1st of the next year' );

// ---- free: counts and blocks ---------------------------------------------------
bridgistic_pl_reset();
check( true === PlanLimits::check_and_count( $ai, '/bridgistic/v1/posts' ), 'free site under the limit: request allowed' );
check_equals( 1, PlanLimits::used_this_month(), 'an AI request is counted site-wide' );

check( true === PlanLimits::check_and_count( $dsh, '/bridgistic/v1/site' ), 'dashboard key is allowed' );
check_equals( 1, PlanLimits::used_this_month(), 'the paired dashboard key does not spend the allowance' );

check( true === PlanLimits::check_and_count( $ai, '/bridgistic/v1/usage' ), 'usage lookup is allowed' );
check_equals( 1, PlanLimits::used_this_month(), 'checking usage does not spend the allowance' );

bridgistic_pl_reset( 4999 );
check( true === PlanLimits::check_and_count( $ai, '/bridgistic/v1/posts' ), 'request number 5,000 is allowed' );
$err = PlanLimits::check_and_count( $ai, '/bridgistic/v1/posts' );
check( is_wp_error( $err ), 'request number 5,001 is blocked on the free plan' );
check_equals( 'bridgistic_free_limit_reached', is_wp_error( $err ) ? $err->get_error_code() : '', 'block uses a stable error code' );
check_equals( 402, is_wp_error( $err ) ? $err->get_status() : 0, 'block is HTTP 402 Payment Required' );
$msg = is_wp_error( $err ) ? $err->get_error_message() : '';
check( false !== strpos( $msg, 'https://pay.bridgistic.app/' ), 'AI-client message links to pay.bridgistic.app' );
check( false !== strpos( $msg, '$12/month' ) && false !== strpos( $msg, '$99/year' ), 'AI-client message states the Pro prices' );
check( false !== strpos( $msg, '5,000' ), 'AI-client message states the free allowance' );
check_equals( 5000, PlanLimits::used_this_month(), 'blocked requests are not counted' );
$data = is_wp_error( $err ) ? (array) $err->get_error_data() : array();
check_equals( 0, (int) ( $data['remaining'] ?? -1 ), 'error data reports nothing remaining' );
check_equals( 'https://pay.bridgistic.app/', (string) ( $data['upgrade_url'] ?? '' ), 'error data carries the upgrade URL' );

check( true === PlanLimits::check_and_count( $dsh, '/bridgistic/v1/site' ), 'dashboard keeps working after the free limit' );
check( true === PlanLimits::check_and_count( $ai, '/bridgistic/v1/usage' ), 'usage lookup keeps working after the free limit' );

// ---- pro: no cap ---------------------------------------------------------------
bridgistic_pl_license( 'yearly' );
check( PlanLimits::is_pro(), 'a live license makes the site Pro' );
check( true === PlanLimits::check_and_count( $ai, '/bridgistic/v1/posts' ), 'Pro site past 5,000 requests is not blocked' );
check_equals( 5001, PlanLimits::used_this_month(), 'Pro requests are still counted for the usage panel' );

foreach ( array( 'trialing', 'grace' ) as $st ) {
	bridgistic_pl_license( 'monthly', $st );
	check( PlanLimits::is_pro(), "license status {$st} counts as Pro" );
}
foreach ( array( 'expired', 'revoked', 'suspended', 'activation_not_found' ) as $st ) {
	bridgistic_pl_license( 'monthly', $st );
	check( ! PlanLimits::is_pro(), "license status {$st} falls back to Free" );
	check( is_wp_error( PlanLimits::check_and_count( $ai, '/bridgistic/v1/posts' ) ), "{$st} license over the free limit is blocked" );
}

// ---- pro price from license data -------------------------------------------------
$b = PlanLimits::pro_billing( array( 'plan' => 'monthly' ) );
check_equals( '$12 / month', $b['price'], 'monthly license shows $12 / month' );
check_equals( 'Pro Monthly', $b['label'], 'monthly license label' );
$b = PlanLimits::pro_billing( array( 'plan' => 'yearly' ) );
check_equals( '$99 / year', $b['price'], 'yearly license shows $99 / year' );
$b = PlanLimits::pro_billing( array( 'plan' => 'lifetime' ) );
check_equals( 'lifetime', $b['interval'], 'AppSumo lifetime license is lifetime' );
check( false === strpos( $b['price'], '$' ), 'lifetime license does not invent a price' );
$b = PlanLimits::pro_billing( array( 'plan' => 'yearly', 'billing' => array( 'amount_cents' => 7900, 'currency' => 'usd', 'interval' => 'year' ) ) );
check_equals( '$79 / year', $b['price'], 'an explicit billing block from the license server wins over the list price' );

bridgistic_pl_license( 'yearly' );
$snap = PlanLimits::snapshot();
check( $snap['is_pro'], 'snapshot reports Pro' );
check_equals( '$99 / year', $snap['billing']['price'], 'snapshot price comes from the stored license response' );
check_equals( '2027-11-05T11:26:08Z', $snap['renews_at'], 'snapshot renewal date is the license expiry' );
check( ! isset( $snap['billing']['activation_token'] ), 'snapshot never carries the activation token' );

// ---- free snapshot numbers --------------------------------------------------------
bridgistic_pl_reset( 1250 );
$snap = PlanLimits::snapshot();
check( ! $snap['is_pro'], 'no license: Free' );
check_equals( 5000, $snap['limit'], 'free snapshot: total limit' );
check_equals( 1250, $snap['used'], 'free snapshot: used' );
check_equals( 3750, $snap['remaining'], 'free snapshot: remaining' );
check_equals( 25, $snap['percent'], 'free snapshot: percent used' );
check_equals( 3, $snap['schedules_limit'], 'free snapshot: 3 active schedules' );
check_equals( 50, $snap['snapshots_limit'], 'free snapshot: 50 manual snapshots' );

// ---- admin notice -------------------------------------------------------------------
$now = time();
check_equals( '', UpgradeNotice::state( array( 'is_pro' => false, 'limit' => 5000, 'used' => 100, 'percent' => 2 ), 0, $now ), 'no notice at low usage' );
check_equals( 'warn', UpgradeNotice::state( array( 'is_pro' => false, 'limit' => 5000, 'used' => 4000, 'percent' => 80 ), 0, $now ), 'warning notice from 80%' );
check_equals( 'limit', UpgradeNotice::state( array( 'is_pro' => false, 'limit' => 5000, 'used' => 5000, 'percent' => 100 ), 0, $now ), 'limit notice when the allowance is used up' );
check_equals( '', UpgradeNotice::state( array( 'is_pro' => false, 'limit' => 5000, 'used' => 5000, 'percent' => 100 ), $now + 3600, $now ), 'dismissed notice stays hidden while snoozed' );
check_equals( 'limit', UpgradeNotice::state( array( 'is_pro' => false, 'limit' => 5000, 'used' => 5000, 'percent' => 100 ), $now - 1, $now ), 'notice comes back after the snooze ends' );
check_equals( '', UpgradeNotice::state( array( 'is_pro' => true, 'limit' => 0, 'used' => 9000, 'percent' => 0 ), 0, $now ), 'Pro sites never see the upgrade notice' );
check_equals( 86400, UpgradeNotice::SNOOZE, 'dismiss snoozes for one day' );

bridgistic_pl_reset();
