<?php
/**
 * Dashboard view.
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

<section class="bridgistic-hero bridgistic-card bridgistic-fade-in">
	<div class="bridgistic-hero-glow" aria-hidden="true"></div>
	<div class="bridgistic-hero-body">
		<h1><?php esc_html_e( 'Bridgistic', 'bridgistic' ); ?></h1>
		<p class="bridgistic-hero-subtitle"><?php esc_html_e( 'Safe AI Control Bridge for WordPress', 'bridgistic' ); ?></p>
		<p class="bridgistic-hero-desc">
			<?php esc_html_e( 'Connect Claude to WordPress with secure keys, scoped permissions, approvals, logs, snapshots, and local MCP setup.', 'bridgistic' ); ?>
		</p>
		<div class="bridgistic-hero-actions">
			<a class="bridgistic-button is-primary" href="<?php echo esc_url( $data['setup_url'] ); ?>">
				<?php echo Page::icon( 'bolt', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php esc_html_e( 'Set Up Claude', 'bridgistic' ); ?>
			</a>
			<a class="bridgistic-button is-ghost" href="<?php echo esc_url( $data['health_url'] ); ?>">
				<?php echo Page::icon( 'pulse', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php esc_html_e( 'Run Health Check', 'bridgistic' ); ?>
			</a>
		</div>
	</div>
	<div class="bridgistic-hero-status" id="bridgistic-hero-status" data-poll-dashboard>
		<?php if ( $data['connected'] ) : ?>
			<?php echo Page::badge( 'pass', __( 'Connected', 'bridgistic' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<span class="bridgistic-hero-status-note"><?php echo esc_html( sprintf( __( 'Last MCP request %s', 'bridgistic' ), Page::ago( $data['last_used'] ) ) ); ?></span>
		<?php elseif ( $data['keys_enabled'] > 0 ) : ?>
			<?php echo Page::badge( 'warn', __( 'Waiting for first request', 'bridgistic' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<span class="bridgistic-hero-status-note"><?php esc_html_e( 'Keys exist — connect Claude and run a read-only tool.', 'bridgistic' ); ?></span>
		<?php else : ?>
			<?php echo Page::badge( 'muted', __( 'Not connected', 'bridgistic' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<span class="bridgistic-hero-status-note"><?php esc_html_e( 'Start with Set Up Claude — it takes about two minutes.', 'bridgistic' ); ?></span>
		<?php endif; ?>
	</div>
</section>

<section class="bridgistic-grid is-4 bridgistic-stagger">

	<article class="bridgistic-card bridgistic-stat-card">
		<header>
			<?php echo Page::icon( 'plug' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<h3><?php esc_html_e( 'Claude Connection', 'bridgistic' ); ?></h3>
		</header>
		<p class="bridgistic-stat" id="bridgistic-connection-badge">
			<?php echo $data['connected'] ? Page::badge( 'pass', __( 'Connected', 'bridgistic' ) ) : Page::badge( 'muted', __( 'Not connected', 'bridgistic' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</p>
		<p class="bridgistic-card-desc"><?php esc_html_e( 'Local MCP server signs every request with your scoped key.', 'bridgistic' ); ?></p>
		<a class="bridgistic-button is-soft" href="<?php echo esc_url( $data['setup_url'] ); ?>"><?php esc_html_e( 'Configure', 'bridgistic' ); ?></a>
	</article>

	<article class="bridgistic-card bridgistic-stat-card">
		<header>
			<?php echo Page::icon( 'shield' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<h3><?php esc_html_e( 'Security Layer', 'bridgistic' ); ?></h3>
		</header>
		<p class="bridgistic-stat">
			<?php echo Page::badge( 'pass', __( 'HMAC + Scopes Enabled', 'bridgistic' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</p>
		<p class="bridgistic-card-desc">
			<?php echo esc_html( sprintf( _n( '%d active key with least-privilege scopes.', '%d active keys with least-privilege scopes.', $data['keys_enabled'], 'bridgistic' ), $data['keys_enabled'] ) ); ?>
		</p>
		<a class="bridgistic-button is-soft" href="<?php echo esc_url( $data['keys_url'] ); ?>"><?php esc_html_e( 'Manage Keys', 'bridgistic' ); ?></a>
	</article>

	<article class="bridgistic-card bridgistic-stat-card">
		<header>
			<?php echo Page::icon( 'camera' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<h3><?php esc_html_e( 'Site Protection', 'bridgistic' ); ?></h3>
		</header>
		<p class="bridgistic-stat"><span class="bridgistic-stat-number"><?php echo esc_html( (string) $data['snapshots'] ); ?></span> <?php esc_html_e( 'snapshots', 'bridgistic' ); ?></p>
		<p class="bridgistic-card-desc"><?php esc_html_e( 'Reversible captures are taken automatically before destructive operations.', 'bridgistic' ); ?></p>
		<a class="bridgistic-button is-soft" href="<?php echo esc_url( $data['snapshots_url'] ); ?>"><?php esc_html_e( 'View Snapshots', 'bridgistic' ); ?></a>
	</article>

	<article class="bridgistic-card bridgistic-stat-card">
		<header>
			<?php echo Page::icon( 'list' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<h3><?php esc_html_e( 'Activity', 'bridgistic' ); ?></h3>
		</header>
		<p class="bridgistic-stat"><span class="bridgistic-stat-number" id="bridgistic-audit-count"><?php echo esc_html( number_format_i18n( (int) $data['audit_count'] ) ); ?></span> <?php esc_html_e( 'logged actions', 'bridgistic' ); ?></p>
		<p class="bridgistic-card-desc" id="bridgistic-latest-log">
			<?php if ( $data['latest_log'] ) : ?>
				<?php echo esc_html( sprintf( __( 'Latest: %1$s (%2$s)', 'bridgistic' ), (string) $data['latest_log']['action'], Page::ago( (string) $data['latest_log']['created_at'] ) ) ); ?>
			<?php else : ?>
				<?php esc_html_e( 'No MCP activity yet.', 'bridgistic' ); ?>
			<?php endif; ?>
		</p>
		<a class="bridgistic-button is-soft" href="<?php echo esc_url( $data['logs_url'] ); ?>"><?php esc_html_e( 'View Logs', 'bridgistic' ); ?></a>
	</article>

</section>

<section class="bridgistic-grid is-3 bridgistic-stagger">
	<article class="bridgistic-card bridgistic-mini-card">
		<?php echo Page::icon( 'key' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<div>
			<strong><?php echo esc_html( (string) $data['keys_enabled'] ); ?> / <?php echo esc_html( (string) $data['keys_total'] ); ?></strong>
			<span><?php esc_html_e( 'Active keys', 'bridgistic' ); ?></span>
		</div>
	</article>
	<article class="bridgistic-card bridgistic-mini-card">
		<?php echo Page::icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<div>
			<strong><?php echo esc_html( (string) $data['playbooks'] ); ?></strong>
			<span><?php esc_html_e( 'Saved playbooks', 'bridgistic' ); ?></span>
		</div>
	</article>
	<article class="bridgistic-card bridgistic-mini-card">
		<?php echo Page::icon( 'pulse' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<div>
			<strong><?php echo null !== $data['health_score'] ? esc_html( $data['health_score'] . '/100' ) : '—'; ?></strong>
			<span>
				<?php
				if ( $data['health_time'] ) {
					echo esc_html( sprintf( __( 'Health score · checked %s ago', 'bridgistic' ), human_time_diff( (int) $data['health_time'] ) ) );
				} else {
					esc_html_e( 'Health score · not run yet', 'bridgistic' );
				}
				?>
			</span>
		</div>
	</article>
</section>

<section class="bridgistic-card bridgistic-locked-strip bridgistic-fade-in">
	<?php echo Page::icon( 'lock', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<p>
		<?php esc_html_e( 'Advanced AI Skills, a multi-site agency dashboard, team permissions, and white-label options are part of Bridgistic SaaS.', 'bridgistic' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bridgistic-premium' ) ); ?>"><?php esc_html_e( 'See what\'s coming', 'bridgistic' ); ?></a>
	</p>
</section>
