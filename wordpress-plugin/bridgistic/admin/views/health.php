<?php
/**
 * Health Check view — summary card + diagnostic grid (AJAX-populated).
 *
 * @package Bridgistic
 * @var array<string,mixed> $data
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<header class="bridgistic-page-head bridgistic-fade-in">
	<h1><?php esc_html_e( 'Health Check', 'bridgistic' ); ?></h1>
	<p><?php esc_html_e( 'Sixteen diagnostics across REST, authentication, and environment — each failure comes with a fix.', 'bridgistic' ); ?></p>
</header>

<section class="bridgistic-card bridgistic-health-summary bridgistic-fade-in">
	<div class="bridgistic-health-score" id="bridgistic-health-score" data-score="<?php echo esc_attr( (string) ( $data['last_score'] ?? '' ) ); ?>">
		<svg viewBox="0 0 120 120" width="108" height="108" aria-hidden="true">
			<circle class="bridgistic-score-track" cx="60" cy="60" r="52" />
			<circle class="bridgistic-score-arc" cx="60" cy="60" r="52" id="bridgistic-score-arc" />
		</svg>
		<div class="bridgistic-score-label">
			<strong id="bridgistic-score-number"><?php echo null !== $data['last_score'] ? esc_html( (string) $data['last_score'] ) : '—'; ?></strong>
			<span>/100</span>
		</div>
	</div>
	<div class="bridgistic-health-summary-body">
		<h2 id="bridgistic-health-headline"><?php esc_html_e( 'Overall status', 'bridgistic' ); ?></h2>
		<p class="bridgistic-help" id="bridgistic-health-subline">
			<?php
			if ( $data['last_time'] ) {
				echo esc_html( sprintf( __( 'Last checked %s ago.', 'bridgistic' ), human_time_diff( (int) $data['last_time'] ) ) );
			} else {
				esc_html_e( 'Not run yet on this site.', 'bridgistic' );
			}
			?>
		</p>
		<div class="bridgistic-step-actions">
			<button type="button" class="bridgistic-button is-primary" id="bridgistic-run-health">
				<?php echo Page::icon( 'refresh', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php esc_html_e( 'Run Check', 'bridgistic' ); ?>
			</button>
			<button type="button" class="bridgistic-button is-soft" id="bridgistic-copy-report" disabled>
				<?php echo Page::icon( 'copy', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php esc_html_e( 'Copy Debug Report', 'bridgistic' ); ?>
			</button>
		</div>
		<p class="bridgistic-help is-muted"><?php esc_html_e( 'The debug report contains versions and check results only — never keys or secrets.', 'bridgistic' ); ?></p>
	</div>
</section>

<section class="bridgistic-grid is-4 bridgistic-health-grid" id="bridgistic-health-grid">
	<?php foreach ( $data['skeleton'] as $label ) : ?>
		<article class="bridgistic-card bridgistic-health-card is-skeleton">
			<header>
				<span class="bridgistic-skeleton-dot"></span>
				<h3><?php echo esc_html( $label ); ?></h3>
			</header>
			<div class="bridgistic-skeleton-line"></div>
			<div class="bridgistic-skeleton-line is-short"></div>
		</article>
	<?php endforeach; ?>
</section>
