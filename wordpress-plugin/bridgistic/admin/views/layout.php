<?php
/**
 * Shared admin shell: brand header, navigation, main column, toast root.
 *
 * Scope: everything renders inside .bridgistic-admin so styles never leak
 * into the rest of wp-admin. Expects $this (Page), $data (array), $view (path).
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var Page $this */
/** @var array<string,mixed> $data */
/** @var string $view */

$bridgistic_nav_icons = array(
	'bridgistic'           => 'dashboard',
	'bridgistic-setup'     => 'bolt',
	'bridgistic-cloud'     => 'globe',
	'bridgistic-keys'      => 'key',
	'bridgistic-multisite' => 'users',
	'bridgistic-approvals' => 'shield',
	'bridgistic-health'    => 'pulse',
	'bridgistic-logs'      => 'list',
	'bridgistic-snapshots' => 'camera',
	'bridgistic-playbooks' => 'play',
	'bridgistic-export'    => 'download',
	'bridgistic-premium'   => 'lock',
	'bridgistic-settings'  => 'gear',
);
?>
<div class="wrap bridgistic-admin" id="bridgistic-admin">
	<div class="bridgistic-shell">

		<header class="bridgistic-topbar">
			<div class="bridgistic-brand">
				<span class="bridgistic-brand-mark"><?php echo Page::icon( 'bridge', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
				<span class="bridgistic-brand-text">
					<strong>Bridgistic</strong>
					<em><?php esc_html_e( 'Safe AI Control Bridge for WordPress', 'bridgistic' ); ?></em>
				</span>
				<span class="bridgistic-badge is-accent"><?php esc_html_e( 'Free Local MCP Version', 'bridgistic' ); ?></span>
			</div>
			<div class="bridgistic-topbar-actions">
				<a class="bridgistic-topbar-link" href="https://wordpressistic.com" target="_blank" rel="noopener noreferrer">WordPressistic <?php echo Page::icon( 'arrow', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
				<button type="button" class="bridgistic-button is-ghost is-icon" id="bridgistic-theme-toggle" aria-label="<?php esc_attr_e( 'Toggle light / dark theme', 'bridgistic' ); ?>">
					<span class="bridgistic-theme-dot" aria-hidden="true"></span>
				</button>
			</div>
		</header>

		<nav class="bridgistic-nav" aria-label="<?php esc_attr_e( 'Bridgistic sections', 'bridgistic' ); ?>">
			<?php foreach ( Controller::pages() as $nav_slug => $nav_page ) : ?>
				<a
					class="bridgistic-nav-item<?php echo $nav_slug === $this->slug() ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( admin_url( 'admin.php?page=' . $nav_slug ) ); ?>"
					<?php echo $nav_slug === $this->slug() ? 'aria-current="page"' : ''; ?>
				>
					<?php echo Page::icon( $bridgistic_nav_icons[ $nav_slug ] ?? 'dashboard', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span>
						<?php echo esc_html( $nav_page[0] ); ?>
						<?php if ( 'bridgistic-setup' === $nav_slug ) : ?>
							<span class="bridgistic-nav-hint"><?php esc_html_e( 'all clients', 'bridgistic' ); ?></span>
						<?php endif; ?>
					</span>
					<?php if ( 'bridgistic-cloud' === $nav_slug ) : ?>
						<span class="bridgistic-nav-beta"><?php esc_html_e( 'Beta', 'bridgistic' ); ?></span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<main class="bridgistic-main" data-bridgistic-page="<?php echo esc_attr( $this->slug() ); ?>">
			<?php include $view; ?>
		</main>

		<footer class="bridgistic-footer">
			<span>Bridgistic <?php echo esc_html( BRIDGISTIC_VERSION ); ?> — <?php esc_html_e( 'free local MCP bridge', 'bridgistic' ); ?></span>
			<span class="bridgistic-footer-links">
				<a href="https://github.com/wordpressistic/bridgistic" target="_blank" rel="noopener noreferrer">GitHub</a>
				·
				<a href="https://wordpressistic.com" target="_blank" rel="noopener noreferrer">WordPressistic</a>
			</span>
		</footer>
	</div>

	<div class="bridgistic-toasts" id="bridgistic-toasts" aria-live="polite"></div>
</div>
