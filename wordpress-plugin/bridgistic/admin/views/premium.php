<?php
/**
 * Premium Features view — locked showcase, display only.
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
	<h1><?php esc_html_e( 'Premium Features', 'bridgistic' ); ?></h1>
	<p><?php esc_html_e( 'The free version is the complete bridge: connect any AI, keys, scopes, approvals, logs, snapshots, playbooks. Paid WPistic plans add skills, unlimited schedules, and team workflows on top — activate a license key under Bridgistic → License.', 'bridgistic' ); ?></p>
</header>

<div class="bridgistic-callout is-info bridgistic-fade-in">
	<?php echo Page::icon( 'info', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<p><?php esc_html_e( 'These features are part of Bridgistic SaaS and are not included in the free local MCP version. Nothing on this page collects payment or unlocks anything.', 'bridgistic' ); ?></p>
</div>

<div class="bridgistic-callout is-warning bridgistic-fade-in">
	<?php echo Page::icon( 'warn', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<p>
		<?php
		printf(
			/* translators: %s: link to the Bridgistic Cloud page. */
			esc_html__( 'The remote MCP connector is no longer SaaS-exclusive — it\'s free, but in public beta with no independent security review yet. See %s.', 'bridgistic' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=bridgistic-license' ) ) . '">' . esc_html__( 'Bridgistic Cloud', 'bridgistic' ) . '</a>'
		); // phpcs:ignore WordPress.Security.EscapeOutput
		?>
	</p>
</div>

<section class="bridgistic-grid is-3 bridgistic-stagger">
	<?php foreach ( $data['sections'] as $section ) : ?>
		<article class="bridgistic-card bridgistic-locked-card is-tall">
			<div class="bridgistic-locked-blur" aria-hidden="true">
				<div class="bridgistic-skeleton-line"></div>
				<div class="bridgistic-skeleton-line is-short"></div>
				<div class="bridgistic-skeleton-line"></div>
				<div class="bridgistic-skeleton-line is-short"></div>
			</div>
			<div class="bridgistic-locked-overlay">
				<?php echo Page::icon( (string) $section['icon'], 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<h3>
					<?php echo Page::icon( 'lock', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php echo esc_html( (string) $section['label'] ); ?>
				</h3>
				<p><?php echo esc_html( (string) $section['description'] ); ?></p>
				<?php if ( ! empty( $section['items'] ) ) : ?>
					<ul class="bridgistic-locked-list">
						<?php foreach ( $section['items'] as $item ) : ?>
							<li><?php echo esc_html( (string) $item ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php echo Page::badge( 'muted', __( 'Coming in Bridgistic SaaS', 'bridgistic' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<a class="bridgistic-button is-ghost is-small" href="https://wpistic.com/pricing" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Learn More', 'bridgistic' ); ?></a>
			</div>
		</article>
	<?php endforeach; ?>
</section>
