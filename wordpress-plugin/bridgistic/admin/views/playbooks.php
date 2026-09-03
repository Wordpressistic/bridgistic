<?php
/**
 * Playbooks view — built-ins, saved playbooks, schedules, locked premium.
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
	<h1><?php esc_html_e( 'Playbooks', 'bridgistic' ); ?></h1>
	<p><?php esc_html_e( 'Reusable routines. Built-in manual playbooks run safe, mostly read-only checks; saved playbooks are created by Claude through the MCP tools.', 'bridgistic' ); ?></p>
</header>

<section class="bridgistic-section">
	<h2 class="bridgistic-section-title"><?php esc_html_e( 'Manual playbooks', 'bridgistic' ); ?></h2>
	<div class="bridgistic-grid is-4 bridgistic-stagger">
		<?php foreach ( $data['builtin'] as $slug => $pb ) : ?>
			<article class="bridgistic-card bridgistic-playbook-card">
				<header>
					<?php echo Page::icon( (string) $pb['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<h3><?php echo esc_html( (string) $pb['label'] ); ?></h3>
				</header>
				<p class="bridgistic-card-desc"><?php echo esc_html( (string) $pb['description'] ); ?></p>
				<p class="bridgistic-help is-muted">
					<?php echo esc_html( sprintf( __( 'Permission: %s', 'bridgistic' ), (string) $pb['permission'] ) ); ?>
					·
					<?php
					if ( $pb['last_run'] ) {
						echo esc_html( sprintf( __( 'Last run %s ago', 'bridgistic' ), human_time_diff( (int) $pb['last_run'] ) ) );
					} else {
						esc_html_e( 'Never run', 'bridgistic' );
					}
					?>
				</p>
				<div class="bridgistic-playbook-result" data-playbook-result="<?php echo esc_attr( (string) $slug ); ?>" hidden></div>
				<button type="button" class="bridgistic-button is-soft is-small" data-playbook-run="<?php echo esc_attr( (string) $slug ); ?>">
					<?php echo Page::icon( 'play', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php esc_html_e( 'Run', 'bridgistic' ); ?>
				</button>
			</article>
		<?php endforeach; ?>
	</div>
</section>

<section class="bridgistic-section">
	<h2 class="bridgistic-section-title"><?php esc_html_e( 'Saved playbooks', 'bridgistic' ); ?></h2>
	<?php if ( ! $data['saved'] ) : ?>
		<div class="bridgistic-card bridgistic-empty">
			<?php echo Page::icon( 'play', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<p><?php esc_html_e( 'No saved playbooks yet. Ask Claude to create one with the bridgistic_playbook_save tool — for example "save a playbook that drafts a weekly changelog post".', 'bridgistic' ); ?></p>
		</div>
	<?php else : ?>
		<div class="bridgistic-card">
			<table class="bridgistic-table">
				<thead><tr><th><?php esc_html_e( 'Playbook', 'bridgistic' ); ?></th><th><?php esc_html_e( 'Description', 'bridgistic' ); ?></th><th><?php esc_html_e( 'Updated', 'bridgistic' ); ?></th><th></th></tr></thead>
				<tbody>
					<?php foreach ( $data['saved'] as $pb ) : ?>
						<tr>
							<td><strong><?php echo esc_html( (string) ( $pb['name'] ?: $pb['slug'] ) ); ?></strong><br><code><?php echo esc_html( (string) $pb['slug'] ); ?></code></td>
							<td><?php echo esc_html( (string) ( $pb['description'] ?: '—' ) ); ?></td>
							<td><?php echo esc_html( Page::ago( (string) $pb['updated_at'] ) ); ?></td>
							<td class="bridgistic-table-actions">
								<button type="button" class="bridgistic-button is-soft is-small" data-saved-playbook-run="<?php echo esc_attr( (string) $pb['slug'] ); ?>" data-dry="1"><?php esc_html_e( 'Dry run', 'bridgistic' ); ?></button>
								<button type="button" class="bridgistic-button is-soft is-small" data-saved-playbook-run="<?php echo esc_attr( (string) $pb['slug'] ); ?>" data-dry="0"><?php esc_html_e( 'Run now', 'bridgistic' ); ?></button>
							</td>
						</tr>
						<tr class="bridgistic-log-details" data-saved-playbook-result="<?php echo esc_attr( (string) $pb['slug'] ); ?>" hidden>
							<td colspan="4"><pre class="bridgistic-code is-compact"></pre></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>

<section class="bridgistic-section">
	<h2 class="bridgistic-section-title"><?php esc_html_e( 'Scheduled playbooks (limited)', 'bridgistic' ); ?></h2>

	<?php if ( $data['cron_off'] ) : ?>
		<div class="bridgistic-callout is-info">
			<?php echo Page::icon( 'info', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<p><?php echo esc_html( sprintf( __( 'WP-Cron is disabled — good. Make sure a real system cron hits %s every few minutes so schedules fire reliably.', 'bridgistic' ), (string) $data['cron_url'] ) ); ?></p>
		</div>
	<?php else : ?>
		<div class="bridgistic-callout is-warning">
			<?php echo Page::icon( 'warn', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<p><?php esc_html_e( 'Schedules currently rely on WP-Cron, which only fires when the site gets traffic. For dependable unattended runs, switch to a real system cron.', 'bridgistic' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! $data['schedules'] ) : ?>
		<div class="bridgistic-card bridgistic-empty">
			<?php echo Page::icon( 'clock', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<p><?php esc_html_e( 'No schedules yet. Ask Claude to schedule a saved playbook with the bridgistic_schedule_create tool.', 'bridgistic' ); ?></p>
		</div>
	<?php else : ?>
		<div class="bridgistic-card">
			<table class="bridgistic-table">
				<thead><tr><th>ID</th><th><?php esc_html_e( 'Playbook', 'bridgistic' ); ?></th><th><?php esc_html_e( 'Recurrence', 'bridgistic' ); ?></th><th><?php esc_html_e( 'Next run (UTC)', 'bridgistic' ); ?></th><th><?php esc_html_e( 'Last status', 'bridgistic' ); ?></th><th><?php esc_html_e( 'State', 'bridgistic' ); ?></th><th></th></tr></thead>
				<tbody>
					<?php foreach ( $data['schedules'] as $r ) : ?>
						<tr>
							<td><code><?php echo esc_html( (string) $r['schedule_id'] ); ?></code></td>
							<td><?php echo esc_html( (string) $r['playbook_slug'] ); ?></td>
							<td><?php echo esc_html( (string) $r['recurrence'] ); ?></td>
							<td><?php echo esc_html( (string) ( $r['next_run'] ?: '—' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $r['last_status'] ?: '—' ) ); ?></td>
							<td><?php echo (int) $r['enabled'] === 1 ? Page::badge( 'pass', __( 'enabled', 'bridgistic' ) ) : Page::badge( 'muted', __( 'disabled', 'bridgistic' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
							<td class="bridgistic-table-actions">
								<?php
								$bridgistic_schedule_buttons = array(
									'run' => __( 'Run now', 'bridgistic' ),
									( (int) $r['enabled'] === 1 ? 'disable' : 'enable' ) => ( (int) $r['enabled'] === 1 ? __( 'Disable', 'bridgistic' ) : __( 'Enable', 'bridgistic' ) ),
									'delete' => __( 'Delete', 'bridgistic' ),
								);
								foreach ( $bridgistic_schedule_buttons as $do => $label ) :
									?>
									<form method="post" action="<?php echo esc_url( (string) $data['action_url'] ); ?>" class="bridgistic-inline-form"<?php echo 'delete' === $do ? ' data-confirm="delete"' : ''; ?>>
										<input type="hidden" name="action" value="bridgistic_schedule_action" />
										<input type="hidden" name="schedule_id" value="<?php echo esc_attr( (string) $r['schedule_id'] ); ?>" />
										<input type="hidden" name="do" value="<?php echo esc_attr( (string) $do ); ?>" />
										<?php wp_nonce_field( 'bridgistic_schedule_action' ); ?>
										<button class="bridgistic-button is-small <?php echo 'delete' === $do ? 'is-danger-soft' : 'is-soft'; ?>"><?php echo esc_html( $label ); ?></button>
									</form>
								<?php endforeach; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>

<section class="bridgistic-section">
	<h2 class="bridgistic-section-title"><?php esc_html_e( 'Premium automation', 'bridgistic' ); ?></h2>
	<div class="bridgistic-grid is-3 bridgistic-stagger">
		<?php foreach ( $data['premium'] as $pb ) : ?>
			<article class="bridgistic-card bridgistic-locked-card">
				<div class="bridgistic-locked-blur" aria-hidden="true">
					<div class="bridgistic-skeleton-line"></div>
					<div class="bridgistic-skeleton-line is-short"></div>
				</div>
				<div class="bridgistic-locked-overlay">
					<?php echo Page::icon( (string) $pb['icon'], 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<h3><?php echo esc_html( (string) $pb['label'] ); ?></h3>
					<?php echo Page::badge( 'muted', __( 'Coming in Bridgistic SaaS', 'bridgistic' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
	<p class="bridgistic-help is-muted"><?php esc_html_e( 'Advanced automation is available in Bridgistic SaaS. The free version stays focused on the local, secure bridge.', 'bridgistic' ); ?></p>
</section>
