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
	<p><?php esc_html_e( 'The free version is the complete local bridge: connect any AI, keys, scopes, approvals, logs, snapshots, playbooks and limited scheduling. A live WPistic license opens unlimited schedules, advanced snapshots and audit export on this site.', 'bridgistic' ); ?></p>
</header>

<?php if ( 'activated' === $data['notice'] ) : ?>
	<div class="bridgistic-callout is-success bridgistic-fade-in"><p><?php esc_html_e( 'License activated on this site.', 'bridgistic' ); ?></p></div>
<?php elseif ( 'deactivated' === $data['notice'] ) : ?>
	<div class="bridgistic-callout is-info bridgistic-fade-in"><p><?php esc_html_e( 'License deactivated. Local state cleared.', 'bridgistic' ); ?></p></div>
<?php elseif ( 'validated' === $data['notice'] ) : ?>
	<div class="bridgistic-callout is-success bridgistic-fade-in"><p><?php esc_html_e( 'License re-validated with the license server.', 'bridgistic' ); ?></p></div>
<?php elseif ( 'error' === $data['notice'] ) : ?>
	<div class="bridgistic-callout is-warning bridgistic-fade-in"><p><?php esc_html_e( 'That did not work. The message under the form has the detail.', 'bridgistic' ); ?></p></div>
<?php endif; ?>

<section class="bridgistic-card bridgistic-fade-in">
	<h2 class="bridgistic-section-title"><?php esc_html_e( 'License', 'bridgistic' ); ?></h2>
	<?php $license = (array) $data['license']; ?>
	<?php if ( ! empty( $license['connected'] ) ) : ?>
		<p class="bridgistic-help">
			<?php
			printf(
				/* translators: 1: status, 2: plan, 3: masked key, 4: domain */
				esc_html__( 'Status: %1$s · Plan: %2$s · Key: %3$s · Bound to %4$s', 'bridgistic' ),
				'<strong>' . esc_html( (string) ( $license['status'] ?? 'unknown' ) ) . '</strong>',
				'<strong>' . esc_html( (string) ( $license['plan'] ?? '' ) ) . '</strong>',
				'<code>' . esc_html( (string) ( $license['key_mask'] ?? '' ) ) . '</code>',
				'<strong>' . esc_html( (string) ( $license['domain'] ?? '' ) ) . '</strong>'
			);
			?>
		</p>
		<?php if ( ! empty( $license['member'] ) ) : ?>
			<p class="bridgistic-help"><?php printf( esc_html__( 'Marketplace membership: sign in to %1$s with %2$s — the Pro skills collection unlocks there.', 'bridgistic' ), '<a href="https://admin.bridgistic.app/" target="_blank" rel="noopener noreferrer">admin.bridgistic.app</a>', '<strong>' . esc_html( (string) $license['member'] ) . '</strong>' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
		<?php endif; ?>
	<?php else : ?>
		<p class="bridgistic-help"><?php esc_html_e( 'Paste your Bridgistic license key (BRG-…, from your purchase email or the Bridgistic console) to unlock unlimited scheduled playbooks, advanced snapshots and audit export on this site.', 'bridgistic' ); ?></p>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( (string) $data['action_url'] ); ?>" class="bridgistic-form">
		<input type="hidden" name="action" value="bridgistic_license_activate" />
		<?php wp_nonce_field( 'bridgistic_license_activate' ); ?>
		<input type="text" name="license_key" class="bridgistic-input" placeholder="BRG-LTD-XXXX-XXXX-XXXX" autocomplete="off" spellcheck="false" />
		<div class="bridgistic-step-actions">
			<button class="bridgistic-button is-primary"><?php echo esc_html( empty( $license['connected'] ) ? __( 'Activate license', 'bridgistic' ) : __( 'Change license', 'bridgistic' ) ); ?></button>
		</div>
		<?php if ( ! empty( $data['notice'] ) && 'error' === $data['notice'] ) : ?>
			<p class="bridgistic-help"><?php echo esc_html( (string) ( get_transient( 'bridgistic_license_error' ) ?: '' ) ); ?></p>
		<?php endif; ?>
	</form>

	<?php if ( ! empty( $license['connected'] ) ) : ?>
		<form method="post" action="<?php echo esc_url( (string) $data['action_url'] ); ?>" class="bridgistic-form">
			<input type="hidden" name="action" value="bridgistic_license_validate" />
			<?php wp_nonce_field( 'bridgistic_license_validate' ); ?>
			<div class="bridgistic-step-actions">
				<button class="bridgistic-button is-ghost"><?php esc_html_e( 'Check now', 'bridgistic' ); ?></button>
			</div>
		</form>
		<form method="post" action="<?php echo esc_url( (string) $data['action_url'] ); ?>" class="bridgistic-form" onsubmit="return confirm('<?php echo esc_js( __( 'Deactivate the license on this site? The seat is released immediately.', 'bridgistic' ) ); ?>');">
			<input type="hidden" name="action" value="bridgistic_license_deactivate" />
			<?php wp_nonce_field( 'bridgistic_license_deactivate' ); ?>
			<div class="bridgistic-step-actions">
				<button class="bridgistic-button is-ghost"><?php esc_html_e( 'Deactivate on this site', 'bridgistic' ); ?></button>
			</div>
		</form>
		<p class="bridgistic-help">
			<?php esc_html_e( 'Entitlements from the license server: ', 'bridgistic' ); ?>
			<?php
			$entitlements = (array) ( $license['entitlements'] ?? array() );
			echo esc_html( $entitlements ? implode( ', ', array_keys( $entitlements ) ) : __( 'none yet — run Check now.', 'bridgistic' ) );
			?>
		</p>
	<?php endif; ?>
	<p class="bridgistic-help"><?php esc_html_e( 'No license yet? The Lifetime Deal is $49.99 one time at', 'bridgistic' ); ?> <a href="https://pay.bridgistic.app/" target="_blank" rel="noopener noreferrer">pay.bridgistic.app</a>.</p>
</section>

<div class="bridgistic-callout is-info bridgistic-fade-in">
	<?php echo Page::icon( 'info', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<p><?php esc_html_e( 'Everything else below ships with Bridgistic SaaS and is not part of the free local MCP version. Nothing on this page collects payment.', 'bridgistic' ); ?></p>
</div>

<div class="bridgistic-callout is-warning bridgistic-fade-in">
	<?php echo Page::icon( 'warn', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<p>
		<?php
		printf(
			/* translators: %s: link to the Bridgistic Cloud page. */
			esc_html__( 'The remote MCP connector is no longer SaaS-exclusive — it\'s free, but in public beta with no independent security review yet. See %s.', 'bridgistic' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=bridgistic-cloud' ) ) . '">' . esc_html__( 'Bridgistic Cloud', 'bridgistic' ) . '</a>'
		); // phpcs:ignore WordPress.Security.EscapeOutput
		?>
	</p>
</div>

<section class="bridgistic-grid is-3 bridgistic-stagger">
	<?php foreach ( $data['sections'] as $section ) : ?>
		<?php if ( ! empty( $section['live'] ) ) : ?>
			<article class="bridgistic-card bridgistic-fade-in">
				<?php echo Page::icon( (string) $section['icon'], 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<h3><?php echo esc_html( (string) $section['label'] ); ?></h3>
				<p><?php echo esc_html( (string) $section['description'] ); ?></p>
				<?php echo Page::badge( 'success', (string) $section['live_badge'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<a class="bridgistic-button is-primary is-small" href="<?php echo esc_url( (string) $section['live_href'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) $section['live_cta'] ); ?></a>
			</article>
		<?php else : ?>
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
		<?php endif; ?>
	<?php endforeach; ?>
</section>
