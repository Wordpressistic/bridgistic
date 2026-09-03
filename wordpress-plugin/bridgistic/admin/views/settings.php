<?php
/**
 * Settings view — options allowlist.
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
	<h1><?php esc_html_e( 'Settings', 'bridgistic' ); ?></h1>
	<p><?php esc_html_e( 'Guardrails for what the bridge is allowed to touch.', 'bridgistic' ); ?></p>
</header>

<?php if ( $data['saved'] ) : ?>
	<div class="bridgistic-callout is-success bridgistic-fade-in">
		<?php echo Page::icon( 'check', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<p><?php esc_html_e( 'Settings saved.', 'bridgistic' ); ?></p>
	</div>
<?php endif; ?>

<section class="bridgistic-card bridgistic-fade-in">
	<h2 class="bridgistic-section-title"><?php esc_html_e( 'Options allowlist', 'bridgistic' ); ?></h2>
	<p class="bridgistic-help">
		<?php esc_html_e( 'Only these option names can be read or written via the options tools. One per line. A trailing * wildcard is allowed (e.g. woocommerce_*). Leave empty to use the safe defaults.', 'bridgistic' ); ?>
	</p>
	<form method="post" action="<?php echo esc_url( (string) $data['action_url'] ); ?>" class="bridgistic-form">
		<input type="hidden" name="action" value="bridgistic_save_allowlist" />
		<?php wp_nonce_field( 'bridgistic_save_allowlist' ); ?>
		<textarea name="allowlist" rows="12" class="bridgistic-input bridgistic-code-input" spellcheck="false"><?php echo esc_textarea( implode( "\n", (array) $data['allowlist'] ) ); ?></textarea>
		<div class="bridgistic-step-actions">
			<button class="bridgistic-button is-primary"><?php esc_html_e( 'Save allowlist', 'bridgistic' ); ?></button>
		</div>
	</form>
</section>

<section class="bridgistic-card bridgistic-fade-in">
	<h2 class="bridgistic-section-title"><?php esc_html_e( 'Hardening tips', 'bridgistic' ); ?></h2>
	<ul class="bridgistic-tip-list">
		<li><?php esc_html_e( 'Use scoped keys for controlled access — prefer Read-only and Content Manager presets.', 'bridgistic' ); ?></li>
		<li><?php esc_html_e( 'Keep sensitive actions behind approval: enable "require approval" on any key used on a live site.', 'bridgistic' ); ?></li>
		<li><?php echo wp_kses( __( 'For hardened secret encryption, define <code>BRIDGISTIC_ENC_KEY</code> in wp-config.php (32+ random characters).', 'bridgistic' ), array( 'code' => array() ) ); ?></li>
		<li><?php esc_html_e( 'Restrict keys to known IPs with the allowlist when your machine has a static address.', 'bridgistic' ); ?></li>
	</ul>
</section>
