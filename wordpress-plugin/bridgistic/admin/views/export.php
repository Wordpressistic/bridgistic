<?php
/**
 * Export Package view.
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
	<h1><?php esc_html_e( 'Export Package', 'bridgistic' ); ?></h1>
	<p><?php esc_html_e( 'Generate a ready-to-use package for Claude Desktop or Claude Code setup.', 'bridgistic' ); ?></p>
</header>

<?php if ( ! $data['zip_ok'] ) : ?>
	<div class="bridgistic-callout is-danger bridgistic-fade-in">
		<?php echo Page::icon( 'warn', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<p><?php esc_html_e( 'The PHP zip extension is missing on this server, so packages cannot be built here. Copy your config from the AI / MCP Connections page instead.', 'bridgistic' ); ?></p>
	</div>
<?php endif; ?>

<?php if ( ! $data['keys'] ) : ?>
	<div class="bridgistic-card bridgistic-empty bridgistic-fade-in">
		<?php echo Page::icon( 'download', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<p><?php esc_html_e( 'You need an active key first — the package embeds its key ID.', 'bridgistic' ); ?></p>
		<a class="bridgistic-button is-primary" href="<?php echo esc_url( (string) $data['setup_url'] ); ?>"><?php esc_html_e( 'Open AI / MCP Connections', 'bridgistic' ); ?></a>
	</div>
<?php else : ?>

	<section class="bridgistic-card bridgistic-fade-in">
		<h2 class="bridgistic-section-title"><?php esc_html_e( 'Download Claude Setup Package', 'bridgistic' ); ?></h2>
		<p class="bridgistic-help"><?php esc_html_e( 'The zip contains a README, your chosen configs, install helper scripts, and a troubleshooting guide.', 'bridgistic' ); ?></p>

		<form method="post" action="<?php echo esc_url( (string) $data['action_url'] ); ?>" class="bridgistic-form" id="bridgistic-export-form">
			<input type="hidden" name="action" value="bridgistic_export_package" />
			<?php wp_nonce_field( 'bridgistic_export_package' ); ?>

			<div class="bridgistic-field-row">
				<label for="bridgistic-export-key"><?php esc_html_e( 'Key to embed', 'bridgistic' ); ?></label>
				<select name="key_id" id="bridgistic-export-key" class="bridgistic-input">
					<?php foreach ( $data['keys'] as $k ) : ?>
						<option value="<?php echo esc_attr( (string) $k['key_id'] ); ?>">
							<?php echo esc_html( (string) $k['label'] . ' — ' . (string) $k['key_id'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<fieldset class="bridgistic-field-row">
				<legend><?php esc_html_e( 'Include', 'bridgistic' ); ?></legend>
				<label class="bridgistic-check-row"><input type="checkbox" name="include_desktop" value="1" checked /> <span><?php esc_html_e( 'Claude Desktop config', 'bridgistic' ); ?></span></label>
				<label class="bridgistic-check-row"><input type="checkbox" name="include_code" value="1" checked /> <span><?php esc_html_e( 'Claude Code config', 'bridgistic' ); ?></span></label>
				<label class="bridgistic-check-row"><input type="checkbox" name="include_troubleshooting" value="1" checked /> <span><?php esc_html_e( 'Troubleshooting guide', 'bridgistic' ); ?></span></label>
				<label class="bridgistic-check-row"><input type="checkbox" name="include_scripts" value="1" checked /> <span><?php esc_html_e( 'Install scripts (Windows / macOS / Linux)', 'bridgistic' ); ?></span></label>
			</fieldset>

			<?php if ( $data['fresh_key_id'] ) : ?>
				<div class="bridgistic-callout is-warning">
					<?php echo Page::icon( 'warn', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<div>
						<label class="bridgistic-check-row">
							<input type="checkbox" name="include_secret" value="1" data-fresh-key="<?php echo esc_attr( (string) $data['fresh_key_id'] ); ?>" />
							<span>
								<?php echo esc_html( sprintf( __( 'Embed the secret for the key created just now (%s). Available for two minutes after creation only.', 'bridgistic' ), (string) $data['fresh_key_id'] ) ); ?>
							</span>
						</label>
						<p class="bridgistic-help"><?php esc_html_e( 'Never share this package publicly if it contains secrets.', 'bridgistic' ); ?></p>
					</div>
				</div>
			<?php else : ?>
				<p class="bridgistic-help is-muted">
					<?php esc_html_e( 'Secrets can only be embedded within two minutes of creating or rotating a key (they are stored encrypted and cannot be read back). This package will use a placeholder — paste your secret after download.', 'bridgistic' ); ?>
				</p>
			<?php endif; ?>

			<div class="bridgistic-step-actions">
				<button class="bridgistic-button is-primary" <?php disabled( ! $data['zip_ok'] ); ?>>
					<?php echo Page::icon( 'download', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php esc_html_e( 'Generate & Download Package', 'bridgistic' ); ?>
				</button>
			</div>
		</form>
	</section>

	<section class="bridgistic-card bridgistic-fade-in">
		<h2 class="bridgistic-section-title"><?php esc_html_e( 'What\'s inside', 'bridgistic' ); ?></h2>
		<pre class="bridgistic-code is-compact">bridgistic-claude-package.zip
├── README.md
├── claude_desktop_config.json
├── claude_code_config.json
├── connections.example.json
├── install-windows.ps1
├── install-macos-linux.sh
└── TROUBLESHOOTING.md</pre>
	</section>

<?php endif; ?>
