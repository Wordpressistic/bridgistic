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

use Bridgistic\LicenseLink;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var Page $this */
/** @var array<string,mixed> $data */
/** @var string $view */

$bridgistic_license = LicenseLink::status();
$bridgistic_licensed = ! empty( $bridgistic_license['connected'] ) && in_array( (string) ( $bridgistic_license['status'] ?? '' ), array( 'active', 'trialing', 'grace' ), true );
$bridgistic_plan = $bridgistic_licensed ? (string) ( $bridgistic_license['plan'] ?? 'pro' ) : '';

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
				<span class="bridgistic-brand-mark">
					<img class="bridgistic-brand-image" src="<?php echo esc_url( BRIDGISTIC_URL . 'assets/brand/bridgistic-icon.png' ); ?>" alt="" width="40" height="40" />
				</span>
				<span class="bridgistic-brand-text">
					<strong>Bridgistic</strong>
					<em><?php esc_html_e( 'Safe AI Control Bridge for WordPress', 'bridgistic' ); ?></em>
				</span>
				<?php if ( $bridgistic_licensed ) : ?>
					<span class="bridgistic-badge is-accent"><?php echo esc_html( sprintf( /* translators: %s: plan name */ __( 'Pro · %s licensed', 'bridgistic' ), ucfirst( $bridgistic_plan ) ) ); ?></span>
				<?php else : ?>
					<span class="bridgistic-badge is-accent"><?php esc_html_e( 'Free Local MCP Version', 'bridgistic' ); ?></span>
				<?php endif; ?>
			</div>
			<div class="bridgistic-topbar-actions">
				<a class="bridgistic-topbar-link" href="https://wordpressistic.com" target="_blank" rel="noopener noreferrer">WordPressistic <?php echo Page::icon( 'arrow', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
				<button type="button" class="bridgistic-button is-ghost is-icon" id="bridgistic-theme-toggle" aria-label="<?php esc_attr_e( 'Toggle light / dark theme', 'bridgistic' ); ?>">
					<span class="bridgistic-theme-dot" aria-hidden="true"></span>
				</button>
			</div>
		</header>

		<nav class="bridgistic-nav" aria-label="<?php esc_attr_e( 'Bridgistic sections', 'bridgistic' ); ?>">
			<?php foreach ( Controller::pages() as $bridgistic_nav_slug => $bridgistic_nav_page ) : ?>
				<a
					class="bridgistic-nav-item<?php echo $bridgistic_nav_slug === $this->slug() ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( admin_url( 'admin.php?page=' . $bridgistic_nav_slug ) ); ?>"
					<?php echo $bridgistic_nav_slug === $this->slug() ? 'aria-current="page"' : ''; ?>
				>
					<?php echo Page::icon( $bridgistic_nav_icons[ $bridgistic_nav_slug ] ?? 'dashboard', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span>
						<?php echo esc_html( $bridgistic_nav_page[0] ); ?>
						<?php if ( 'bridgistic-setup' === $bridgistic_nav_slug ) : ?>
							<span class="bridgistic-nav-hint"><?php esc_html_e( 'all clients', 'bridgistic' ); ?></span>
						<?php endif; ?>
					</span>
					<?php if ( 'bridgistic-cloud' === $bridgistic_nav_slug ) : ?>
						<span class="bridgistic-nav-beta"><?php esc_html_e( 'Beta', 'bridgistic' ); ?></span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<main class="bridgistic-main" data-bridgistic-page="<?php echo esc_attr( $this->slug() ); ?>">
			<?php include $view; ?>
		</main>

		<footer class="bridgistic-footer">
			<span><?php echo esc_html( 'Bridgistic ' . BRIDGISTIC_VERSION . ' — ' . ( $bridgistic_licensed ? sprintf( /* translators: %s: plan name */ __( 'Pro (%s) licensed', 'bridgistic' ), $bridgistic_plan ) : __( 'free local MCP bridge', 'bridgistic' ) ) ); ?></span>
			<span class="bridgistic-footer-links">
				<a href="https://github.com/wordpressistic/bridgistic" target="_blank" rel="noopener noreferrer">GitHub</a>
				·
				<a href="https://wordpressistic.com" target="_blank" rel="noopener noreferrer">WordPressistic</a>
			</span>
		</footer>
	</div>

	<div class="bridgistic-toasts" id="bridgistic-toasts" aria-live="polite"></div>
</div>
