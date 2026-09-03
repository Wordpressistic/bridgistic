<?php
/**
 * Multi-Site view — client-side connections.json builder.
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
	<h1><?php esc_html_e( 'Multi-Site', 'bridgistic' ); ?></h1>
	<p><?php esc_html_e( 'Build a connections.json file so one Claude Desktop, Claude Code, Codex CLI, or Gemini CLI setup can reach more than one WordPress site — pick a site by alias instead of running a separate config per site.', 'bridgistic' ); ?></p>
</header>

<?php if ( ! $data['keys'] ) : ?>
	<div class="bridgistic-card bridgistic-empty bridgistic-fade-in">
		<?php echo Page::icon( 'users', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<p><?php esc_html_e( 'You need an active key on this site first.', 'bridgistic' ); ?></p>
		<a class="bridgistic-button is-primary" href="<?php echo esc_url( (string) $data['setup_url'] ); ?>"><?php esc_html_e( 'Open AI / MCP Connections', 'bridgistic' ); ?></a>
	</div>
<?php else : ?>

	<section class="bridgistic-card bridgistic-fade-in">
		<h2 class="bridgistic-section-title"><?php esc_html_e( 'This site', 'bridgistic' ); ?></h2>
		<p class="bridgistic-help"><?php esc_html_e( 'Pick which key on this site to include, and the short name (alias) your AI client will use to target it.', 'bridgistic' ); ?></p>

		<div class="bridgistic-ms-row" id="bridgistic-ms-this">
			<div class="bridgistic-field-row">
				<label for="bridgistic-ms-this-alias"><?php esc_html_e( 'Alias', 'bridgistic' ); ?></label>
				<input type="text" id="bridgistic-ms-this-alias" class="bridgistic-input" value="<?php echo esc_attr( (string) $data['alias_default'] ); ?>" />
			</div>
			<div class="bridgistic-field-row">
				<label for="bridgistic-ms-this-site-url"><?php esc_html_e( 'Site URL', 'bridgistic' ); ?></label>
				<input type="text" id="bridgistic-ms-this-site-url" class="bridgistic-input" value="<?php echo esc_attr( (string) $data['site_url'] ); ?>" readonly />
			</div>
			<div class="bridgistic-field-row">
				<label for="bridgistic-ms-this-key"><?php esc_html_e( 'Key', 'bridgistic' ); ?></label>
				<select id="bridgistic-ms-this-key" class="bridgistic-input" data-fresh-key-id="<?php echo esc_attr( (string) $data['fresh_key_id'] ); ?>" data-fresh-secret="<?php echo esc_attr( (string) $data['fresh_secret'] ); ?>">
					<?php foreach ( $data['keys'] as $k ) : ?>
						<option value="<?php echo esc_attr( (string) $k['key_id'] ); ?>"><?php echo esc_html( (string) $k['label'] . ' — ' . (string) $k['key_id'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="bridgistic-field-row">
				<label for="bridgistic-ms-this-secret"><?php esc_html_e( 'Secret', 'bridgistic' ); ?></label>
				<input type="text" id="bridgistic-ms-this-secret" class="bridgistic-input bridgistic-secret-value" placeholder="PASTE_YOUR_SECRET_HERE" />
			</div>
		</div>
		<p class="bridgistic-help is-muted" id="bridgistic-ms-secret-hint">
			<?php
			printf(
				/* translators: %s: link to Keys & Scopes. */
				esc_html__( 'Secrets can\'t be displayed again after creation. If the field above is empty, rotate the key in %s and come straight back — it\'ll prefill for two minutes.', 'bridgistic' ),
				'<a href="' . esc_url( (string) $data['keys_url'] ) . '">' . esc_html__( 'Keys & Scopes', 'bridgistic' ) . '</a>'
			); // phpcs:ignore WordPress.Security.EscapeOutput
			?>
		</p>
	</section>

	<section class="bridgistic-card bridgistic-fade-in">
		<h2 class="bridgistic-section-title"><?php esc_html_e( 'Other sites', 'bridgistic' ); ?></h2>
		<p class="bridgistic-help"><?php esc_html_e( 'For each other WordPress site running Bridgistic, run its own AI / MCP Connections wizard to mint a key, then copy that alias/URL/key ID/secret in here. This list only lives in your browser — it is never sent to this site\'s server.', 'bridgistic' ); ?></p>

		<div id="bridgistic-ms-others"></div>
		<p class="bridgistic-help is-muted" id="bridgistic-ms-empty"><?php esc_html_e( 'No other sites added yet.', 'bridgistic' ); ?></p>

		<button type="button" class="bridgistic-button is-soft" id="bridgistic-ms-add">
			<?php echo Page::icon( 'plug', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php esc_html_e( 'Add another site', 'bridgistic' ); ?>
		</button>

		<p class="bridgistic-help is-muted" style="margin-top:10px;">
			<?php esc_html_e( 'Alias, site URL, and key ID for other sites are remembered in this browser (localStorage) so you don\'t have to retype them every visit. Secrets are never saved this way — paste them again each time and copy/download the file before navigating away.', 'bridgistic' ); ?>
		</p>
	</section>

	<section class="bridgistic-card bridgistic-fade-in">
		<h2 class="bridgistic-section-title"><?php esc_html_e( 'connections.json', 'bridgistic' ); ?></h2>
		<pre class="bridgistic-code" id="bridgistic-ms-json">{}</pre>
		<div class="bridgistic-step-actions">
			<button type="button" class="bridgistic-button is-soft" id="bridgistic-ms-copy"><?php echo Page::icon( 'copy', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Copy', 'bridgistic' ); ?></button>
			<button type="button" class="bridgistic-button is-primary" id="bridgistic-ms-download"><?php echo Page::icon( 'download', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Download connections.json', 'bridgistic' ); ?></button>
		</div>
		<p class="bridgistic-help is-muted" style="margin-top:10px;">
			<?php esc_html_e( 'Save the download somewhere private (never inside a repo), then point your AI client at it:', 'bridgistic' ); ?>
		</p>
		<pre class="bridgistic-code is-compact">"env": {
  "BRIDGISTIC_CONNECTIONS": "/absolute/path/to/connections.json"
}</pre>
		<p class="bridgistic-help">
			<?php
			printf(
				/* translators: %s: link to CONNECT_OTHER_AI.md. */
				esc_html__( 'Full details, including how to target a specific alias from your AI client: %s.', 'bridgistic' ),
				'<a href="https://github.com/wordpressistic/bridgistic/blob/main/docs/CONNECT_OTHER_AI.md" target="_blank" rel="noopener noreferrer">' . esc_html__( 'CONNECT_OTHER_AI.md', 'bridgistic' ) . '</a>'
			); // phpcs:ignore WordPress.Security.EscapeOutput
			?>
		</p>
	</section>

	<template id="bridgistic-ms-row-template">
		<div class="bridgistic-ms-row" data-ms-row>
			<div class="bridgistic-field-row">
				<label><?php esc_html_e( 'Alias', 'bridgistic' ); ?></label>
				<input type="text" class="bridgistic-input" data-ms-field="alias" placeholder="my-shop" />
			</div>
			<div class="bridgistic-field-row">
				<label><?php esc_html_e( 'Site URL', 'bridgistic' ); ?></label>
				<input type="url" class="bridgistic-input" data-ms-field="siteUrl" placeholder="https://shop.example.com" />
			</div>
			<div class="bridgistic-field-row">
				<label><?php esc_html_e( 'Key ID', 'bridgistic' ); ?></label>
				<input type="text" class="bridgistic-input" data-ms-field="keyId" placeholder="wpk_…" />
			</div>
			<div class="bridgistic-field-row">
				<label><?php esc_html_e( 'Secret', 'bridgistic' ); ?></label>
				<input type="text" class="bridgistic-input bridgistic-secret-value" data-ms-field="secret" placeholder="wps_…" />
			</div>
			<button type="button" class="bridgistic-button is-ghost is-small" data-ms-remove aria-label="<?php esc_attr_e( 'Remove this site', 'bridgistic' ); ?>">
				<?php echo Page::icon( 'x', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</button>
		</div>
	</template>

<?php endif; ?>
