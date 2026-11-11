<?php
/**
 * Settings → Plan & usage panel (1.5.11).
 *
 * @package Bridgistic
 * @var array<string,mixed> $data
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

use Bridgistic\PlanLimits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bridgistic_u     = (array) ( $data['usage'] ?? array() );
$bridgistic_pro   = ! empty( $bridgistic_u['is_pro'] );
$bridgistic_df    = (string) get_option( 'date_format', 'F j, Y' );
$bridgistic_reset = wp_date( $bridgistic_df, (int) ( $bridgistic_u['resets_at'] ?? time() ) );
$bridgistic_used  = (int) ( $bridgistic_u['used'] ?? 0 );
$bridgistic_unl   = __( 'Unlimited', 'bridgistic' );
?>
<section class="bridgistic-card bridgistic-fade-in bridgistic-usage" id="bridgistic-usage">
	<style>
		.bridgistic-usage .bu-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
		.bridgistic-usage .bu-plan{display:inline-block;padding:3px 10px;border-radius:999px;font-weight:700;font-size:12px;letter-spacing:.04em;text-transform:uppercase}
		.bridgistic-usage .bu-plan.is-free{background:#e8edf4;color:#0B1F3A}
		.bridgistic-usage .bu-plan.is-pro{background:#C6A15B;color:#0B1F3A}
		.bridgistic-usage .bu-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:14px 0}
		.bridgistic-usage .bu-cell{border:1px solid var(--bz-border,rgba(11,31,58,.12));border-radius:12px;padding:12px}
		.bridgistic-usage .bu-cell dt{font-size:12px;opacity:.7;margin:0 0 4px}
		.bridgistic-usage .bu-cell dd{margin:0;font-size:20px;font-weight:700}
		.bridgistic-usage .bu-bar{height:10px;border-radius:999px;background:var(--bz-bg-2,rgba(11,31,58,.1));overflow:hidden;margin-bottom:10px}
		.bridgistic-usage .bu-bar i{display:block;height:100%;background:var(--bz-accent,#1F8A70)}
		.bridgistic-usage .bu-bar.is-warn i{background:#C6A15B}
		.bridgistic-usage .bu-bar.is-full i{background:#b42318}
		.bridgistic-usage table{width:100%;border-collapse:collapse;margin-top:12px}
		.bridgistic-usage th,.bridgistic-usage td{text-align:left;padding:8px 6px;border-bottom:1px solid var(--bz-border,rgba(11,31,58,.08));font-size:13px}
		@media (max-width:900px){.bridgistic-usage .bu-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
	</style>
	<div class="bu-head">
		<h2 class="bridgistic-section-title" style="margin:0"><?php esc_html_e( 'Plan & usage', 'bridgistic' ); ?></h2>
		<?php if ( $bridgistic_pro ) : ?>
			<span class="bu-plan is-pro"><?php echo esc_html( (string) ( $bridgistic_u['billing']['label'] ?? 'Pro' ) ); ?></span>
		<?php else : ?>
			<span class="bu-plan is-free"><?php esc_html_e( 'Free plan', 'bridgistic' ); ?></span>
		<?php endif; ?>
	</div>

	<?php if ( $bridgistic_pro ) : ?>
		<?php
		$bridgistic_renew = (string) ( $bridgistic_u['renews_at'] ?? '' );
		$bridgistic_ts    = '' !== $bridgistic_renew ? strtotime( $bridgistic_renew ) : false;
		$bridgistic_life  = 'lifetime' === (string) ( $bridgistic_u['billing']['interval'] ?? '' );
		?>
		<dl class="bu-grid">
			<div class="bu-cell"><dt><?php esc_html_e( 'Plan', 'bridgistic' ); ?></dt><dd><?php echo esc_html( (string) ( $bridgistic_u['billing']['label'] ?? 'Pro' ) ); ?></dd></div>
			<div class="bu-cell"><dt><?php esc_html_e( 'Price paid', 'bridgistic' ); ?></dt><dd><?php echo esc_html( (string) ( $bridgistic_u['billing']['price'] ?? '' ) ); ?></dd></div>
			<div class="bu-cell"><dt><?php echo esc_html( $bridgistic_life ? __( 'Renewal', 'bridgistic' ) : __( 'Renews / expires', 'bridgistic' ) ); ?></dt><dd><?php echo esc_html( $bridgistic_ts ? wp_date( $bridgistic_df, (int) $bridgistic_ts ) : ( $bridgistic_life ? __( 'Never (lifetime)', 'bridgistic' ) : __( 'Not set', 'bridgistic' ) ) ); ?></dd></div>
			<div class="bu-cell"><dt><?php esc_html_e( 'AI requests this month', 'bridgistic' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $bridgistic_used ) ); ?></dd></div>
		</dl>
		<p class="bridgistic-help"><?php /* translators: %s: masked license key */ printf( esc_html__( 'License %s is active on this site. Manage it under Premium Features.', 'bridgistic' ), '<code>' . esc_html( (string) ( $bridgistic_u['key_mask'] ?? '' ) ) . '</code>' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
	<?php else : ?>
		<?php
		$bridgistic_limit = (int) ( $bridgistic_u['limit'] ?? 0 );
		$bridgistic_pct   = (int) ( $bridgistic_u['percent'] ?? 0 );
		$bridgistic_bar   = $bridgistic_pct >= 100 ? 'is-full' : ( $bridgistic_pct >= UpgradeNotice::WARN_AT ? 'is-warn' : '' );
		?>
		<dl class="bu-grid">
			<div class="bu-cell"><dt><?php esc_html_e( 'Monthly AI requests', 'bridgistic' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $bridgistic_limit ) ); ?></dd></div>
			<div class="bu-cell"><dt><?php esc_html_e( 'Used', 'bridgistic' ); ?></dt><dd><?php echo esc_html( number_format_i18n( min( $bridgistic_used, $bridgistic_limit ) ) ); ?></dd></div>
			<div class="bu-cell"><dt><?php esc_html_e( 'Remaining', 'bridgistic' ); ?></dt><dd><?php echo esc_html( number_format_i18n( (int) ( $bridgistic_u['remaining'] ?? 0 ) ) ); ?></dd></div>
			<div class="bu-cell"><dt><?php esc_html_e( 'Resets on', 'bridgistic' ); ?></dt><dd><?php echo esc_html( $bridgistic_reset ); ?></dd></div>
		</dl>
		<div class="bu-bar <?php echo esc_attr( $bridgistic_bar ); ?>" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) $bridgistic_pct ); ?>" aria-label="<?php esc_attr_e( 'Free allowance used', 'bridgistic' ); ?>"><i style="width:<?php echo esc_attr( (string) $bridgistic_pct ); ?>%"></i></div>
		<p class="bridgistic-help"><?php esc_html_e( 'Every signed request from an AI client (local MCP, Claude Desktop extension or Bridgistic Cloud) and every scheduled run counts. The allowance is shared by all keys on this site and resets on the 1st of each month (UTC). The Bridgistic dashboard and the usage lookup do not count.', 'bridgistic' ); ?></p>
		<p style="margin:10px 0 0">
			<a class="bridgistic-button is-primary" href="<?php echo esc_url( PlanLimits::UPGRADE_URL ); ?>" target="_blank" rel="noopener"><?php /* translators: 1: monthly price, 2: yearly price. */ echo esc_html( sprintf( __( 'Upgrade to Pro · $%1$d/month or $%2$d/year', 'bridgistic' ), PlanLimits::PRO_MONTHLY_USD, PlanLimits::PRO_YEARLY_USD ) ); ?></a>
			<a class="bridgistic-button is-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=bridgistic-premium' ) ); ?>"><?php esc_html_e( 'Enter a license key', 'bridgistic' ); ?></a>
		</p>
	<?php endif; ?>

	<table>
		<thead><tr><th><?php esc_html_e( 'Limit', 'bridgistic' ); ?></th><th><?php esc_html_e( 'Free', 'bridgistic' ); ?></th><th><?php esc_html_e( 'Pro', 'bridgistic' ); ?></th><th><?php esc_html_e( 'This site now', 'bridgistic' ); ?></th></tr></thead>
		<tbody>
			<tr><td><?php esc_html_e( 'AI requests per month', 'bridgistic' ); ?></td><td><?php echo esc_html( number_format_i18n( PlanLimits::free_monthly_requests() ) ); ?></td><td><?php echo esc_html( $bridgistic_unl ); ?></td><td><?php echo esc_html( number_format_i18n( $bridgistic_used ) ); ?></td></tr>
			<tr><td><?php esc_html_e( 'Active scheduled playbooks', 'bridgistic' ); ?></td><td>3</td><td><?php echo esc_html( $bridgistic_unl ); ?></td><td><?php echo esc_html( (string) (int) ( $bridgistic_u['schedules_active'] ?? 0 ) ); ?></td></tr>
			<tr><td><?php esc_html_e( 'Manual snapshots kept', 'bridgistic' ); ?></td><td><?php echo esc_html( (string) PlanLimits::free_snapshot_cap() ); ?></td><td><?php esc_html_e( 'No cap', 'bridgistic' ); ?></td><td>—</td></tr>
			<tr><td><?php esc_html_e( 'Audit log export (JSON, latest 1,000 entries)', 'bridgistic' ); ?></td><td><?php esc_html_e( 'No', 'bridgistic' ); ?></td><td><?php esc_html_e( 'Yes', 'bridgistic' ); ?></td><td><?php echo esc_html( $bridgistic_pro ? __( 'Yes', 'bridgistic' ) : __( 'No', 'bridgistic' ) ); ?></td></tr>
			<tr><td><?php esc_html_e( 'On-screen audit history', 'bridgistic' ); ?></td><td><?php esc_html_e( '90 days', 'bridgistic' ); ?></td><td><?php esc_html_e( '90 days', 'bridgistic' ); ?></td><td>—</td></tr>
		</tbody>
	</table>
</section>
