<?php
/**
 * Approvals view — keep sensitive actions behind approval.
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
	<h1><?php esc_html_e( 'Approvals', 'bridgistic' ); ?></h1>
	<p><?php esc_html_e( 'Review before changes are applied. Operations queued by keys that require approval wait here — approve to let Claude retry and execute, reject to block.', 'bridgistic' ); ?></p>
</header>

<section class="bridgistic-section">
	<h2 class="bridgistic-section-title">
		<?php esc_html_e( 'Pending', 'bridgistic' ); ?>
		<?php if ( $data['pending'] ) : ?>
			<?php echo Page::badge( 'warn', (string) count( $data['pending'] ), true ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php endif; ?>
	</h2>

	<?php if ( ! $data['pending'] ) : ?>
		<div class="bridgistic-card bridgistic-empty">
			<?php echo Page::icon( 'shield', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<p><?php esc_html_e( 'Nothing waiting. Destructive operations from approval-required keys will appear here.', 'bridgistic' ); ?></p>
		</div>
	<?php else : ?>
		<div class="bridgistic-grid is-2 bridgistic-stagger">
			<?php foreach ( $data['pending'] as $a ) : ?>
				<article class="bridgistic-card bridgistic-approval-card">
					<header>
						<code><?php echo esc_html( (string) $a['action'] ); ?></code>
						<?php echo Page::badge( 'warn', __( 'awaiting decision', 'bridgistic' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</header>
					<p class="bridgistic-card-desc"><?php echo esc_html( (string) $a['summary'] ); ?></p>
					<p class="bridgistic-help is-muted">
						<?php echo esc_html( sprintf( __( 'Key %1$s · requested %2$s', 'bridgistic' ), (string) $a['key_id'], Page::ago( (string) $a['created_at'] ) ) ); ?>
					</p>
					<form method="post" action="<?php echo esc_url( (string) $data['action_url'] ); ?>" class="bridgistic-key-actions">
						<input type="hidden" name="action" value="bridgistic_decide_approval" />
						<input type="hidden" name="approval_id" value="<?php echo esc_attr( (string) $a['approval_id'] ); ?>" />
						<?php wp_nonce_field( 'bridgistic_decide_approval' ); ?>
						<button class="bridgistic-button is-primary is-small" name="decision" value="approve"><?php esc_html_e( 'Approve', 'bridgistic' ); ?></button>
						<button class="bridgistic-button is-danger-soft is-small" name="decision" value="reject"><?php esc_html_e( 'Reject', 'bridgistic' ); ?></button>
					</form>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>

<section class="bridgistic-section">
	<h2 class="bridgistic-section-title"><?php esc_html_e( 'Recent decisions', 'bridgistic' ); ?></h2>
	<div class="bridgistic-card">
		<table class="bridgistic-table">
			<thead><tr><th><?php esc_html_e( 'When', 'bridgistic' ); ?></th><th><?php esc_html_e( 'Action', 'bridgistic' ); ?></th><th><?php esc_html_e( 'Status', 'bridgistic' ); ?></th><th><?php esc_html_e( 'Decided', 'bridgistic' ); ?></th></tr></thead>
			<tbody>
				<?php if ( ! $data['recent'] ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'No decisions yet.', 'bridgistic' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $data['recent'] as $a ) : ?>
						<tr>
							<td><?php echo esc_html( Page::ago( (string) $a['created_at'] ) ); ?></td>
							<td><code><?php echo esc_html( (string) $a['action'] ); ?></code></td>
							<td><?php echo Page::badge( 'approved' === $a['status'] ? 'pass' : ( 'pending' === $a['status'] ? 'info' : 'fail' ), (string) $a['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
							<td><?php echo esc_html( $a['decided_at'] ? Page::ago( (string) $a['decided_at'] ) : '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</section>
