<?php
/**
 * Snapshots view.
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
	<h1><?php esc_html_e( 'Snapshots', 'bridgistic' ); ?></h1>
	<p><?php esc_html_e( 'Reversible captures taken automatically before destructive operations — or manually, before you let Claude edit content.', 'bridgistic' ); ?></p>
</header>

<section class="bridgistic-card bridgistic-snapshot-bar bridgistic-fade-in">
	<div>
		<strong><?php echo esc_html( sprintf( __( '%1$d of %2$d snapshots used', 'bridgistic' ), (int) $data['count'], (int) $data['limit'] ) ); ?></strong>
		<p class="bridgistic-help"><?php echo esc_html( sprintf( __( 'The free version keeps up to %d snapshots. Delete old ones to make room.', 'bridgistic' ), (int) $data['limit'] ) ); ?></p>
	</div>
	<button type="button" class="bridgistic-button is-primary" id="bridgistic-create-snapshot">
		<?php echo Page::icon( 'camera', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php esc_html_e( 'Create manual snapshot', 'bridgistic' ); ?>
	</button>
</section>

<div class="bridgistic-callout is-warning bridgistic-fade-in">
	<?php echo Page::icon( 'warn', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<p><?php esc_html_e( 'Restoring a snapshot may overwrite current changes. Create a backup first.', 'bridgistic' ); ?></p>
</div>

<?php if ( ! $data['snapshots'] ) : ?>
	<div class="bridgistic-card bridgistic-empty bridgistic-fade-in">
		<?php echo Page::icon( 'camera', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<p><?php esc_html_e( 'No snapshots yet. They appear automatically before destructive operations, or create one manually above.', 'bridgistic' ); ?></p>
	</div>
<?php else : ?>
	<section class="bridgistic-grid is-3 bridgistic-stagger">
		<?php foreach ( $data['snapshots'] as $s ) : ?>
			<article class="bridgistic-card bridgistic-snapshot-card">
				<header>
					<?php echo Page::icon( 'camera' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<div>
						<h3><?php echo esc_html( (string) ( $s['label'] ?: $s['type'] ) ); ?></h3>
						<code><?php echo esc_html( (string) $s['snapshot_id'] ); ?></code>
					</div>
					<?php echo Page::badge( 'info', (string) $s['type'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</header>
				<dl class="bridgistic-key-meta">
					<div><dt><?php esc_html_e( 'Created', 'bridgistic' ); ?></dt><dd><?php echo esc_html( Page::ago( (string) $s['created_at'] ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Size', 'bridgistic' ); ?></dt><dd><?php echo esc_html( size_format( (int) $s['byte_size'] ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Restored', 'bridgistic' ); ?></dt><dd><?php echo esc_html( $s['restored_at'] ? Page::ago( (string) $s['restored_at'] ) : '—' ); ?></dd></div>
				</dl>
				<footer class="bridgistic-key-actions">
					<button type="button" class="bridgistic-button is-soft is-small" data-snapshot-restore="<?php echo esc_attr( (string) $s['snapshot_id'] ); ?>">
						<?php echo Page::icon( 'refresh', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Restore', 'bridgistic' ); ?>
					</button>
					<button type="button" class="bridgistic-button is-danger-soft is-small" data-snapshot-delete="<?php echo esc_attr( (string) $s['snapshot_id'] ); ?>">
						<?php esc_html_e( 'Delete', 'bridgistic' ); ?>
					</button>
				</footer>
			</article>
		<?php endforeach; ?>
	</section>
<?php endif; ?>

<section class="bridgistic-grid is-2">
	<article class="bridgistic-card bridgistic-locked-card">
		<div class="bridgistic-locked-blur" aria-hidden="true">
			<div class="bridgistic-skeleton-line"></div>
			<div class="bridgistic-skeleton-line is-short"></div>
			<div class="bridgistic-skeleton-line"></div>
		</div>
		<div class="bridgistic-locked-overlay">
			<?php echo Page::icon( 'lock', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<h3><?php esc_html_e( 'Advanced Snapshot History', 'bridgistic' ); ?></h3>
			<p><?php esc_html_e( 'Full history, retention policies, and rollback analytics.', 'bridgistic' ); ?></p>
			<?php echo Page::badge( 'muted', __( 'Coming in Bridgistic SaaS', 'bridgistic' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
	</article>
</section>
