<?php
/**
 * Claude Setup view — five-step wizard.
 *
 * Steps 1–2 select options, step 3 mints the key via AJAX (secret shown once),
 * step 4 renders configs, step 5 tests the pipeline. All state lives in JS;
 * nothing sensitive is embedded in the page source.
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
	<h1><?php esc_html_e( 'AI / MCP Connections', 'bridgistic' ); ?></h1>
	<p><?php esc_html_e( 'Connect an AI assistant to WordPress safely — pick your client, choose what it is allowed to do, and copy a ready-made config.', 'bridgistic' ); ?></p>
	<p class="bridgistic-help is-muted"><?php esc_html_e( 'Works with Claude Desktop, Claude Code, OpenAI Codex CLI, Gemini CLI, ChatGPT, and any other MCP-compatible client. Clients that cannot run a local server — ChatGPT among them — connect through Bridgistic Cloud instead; pick that option below.', 'bridgistic' ); ?></p>
</header>

<div class="bridgistic-stepper bridgistic-fade-in" id="bridgistic-setup" data-step="1">

	<!-- Step 1: connection type -->
	<section class="bridgistic-card bridgistic-step is-current" data-step-panel="1">
		<header class="bridgistic-step-head">
			<span class="bridgistic-step-num">1</span>
			<h2><?php esc_html_e( 'Choose connection type', 'bridgistic' ); ?></h2>
			<span class="bridgistic-step-check"><?php echo Page::icon( 'check', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		</header>
		<div class="bridgistic-step-body">
			<div class="bridgistic-choice-grid" role="radiogroup" aria-label="<?php esc_attr_e( 'Connection type', 'bridgistic' ); ?>">
				<button type="button" class="bridgistic-choice is-selected" data-connection="extension">
					<?php echo Page::icon( 'sparkle', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<strong><?php esc_html_e( 'Desktop Extension', 'bridgistic' ); ?> <?php echo Page::badge( 'pass', __( 'Recommended — no install', 'bridgistic' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></strong>
					<span><?php esc_html_e( 'Download .mcpb, double-click, paste three values. No terminal, no config files, no Node.js.', 'bridgistic' ); ?></span>
				</button>
				<button type="button" class="bridgistic-choice" data-connection="desktop">
					<?php echo Page::icon( 'desktop', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<strong><?php esc_html_e( 'Claude Desktop (manual)', 'bridgistic' ); ?></strong>
					<span><?php esc_html_e( 'For advanced setups: hand-edit a local config file yourself.', 'bridgistic' ); ?></span>
				</button>
				<button type="button" class="bridgistic-choice" data-connection="code">
					<?php echo Page::icon( 'code', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<strong><?php esc_html_e( 'Claude Code', 'bridgistic' ); ?></strong>
					<span><?php esc_html_e( 'Terminal / IDE, installs via plugin marketplace. Requires Node.js.', 'bridgistic' ); ?></span>
				</button>
				<button type="button" class="bridgistic-choice" data-connection="codex">
					<?php echo Page::icon( 'bolt', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<strong><?php esc_html_e( 'OpenAI Codex CLI', 'bridgistic' ); ?></strong>
					<span><?php esc_html_e( 'Terminal. Uses the published npm package — nothing to clone or build.', 'bridgistic' ); ?></span>
				</button>
				<button type="button" class="bridgistic-choice" data-connection="gemini">
					<?php echo Page::icon( 'globe', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<strong><?php esc_html_e( 'Gemini CLI', 'bridgistic' ); ?></strong>
					<span><?php esc_html_e( 'Terminal. Uses the published npm package — nothing to clone or build.', 'bridgistic' ); ?></span>
				</button>
				<button type="button" class="bridgistic-choice" data-connection="remote">
					<?php echo Page::icon( 'plug', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<strong><?php esc_html_e( 'ChatGPT / remote MCP', 'bridgistic' ); ?> <?php echo Page::badge( 'warn', __( 'Public beta', 'bridgistic' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></strong>
					<span><?php esc_html_e( 'For clients that cannot launch a local server. Connects through the hosted Bridgistic Cloud endpoint over OAuth.', 'bridgistic' ); ?></span>
				</button>
				<button type="button" class="bridgistic-choice" data-connection="manual">
					<?php echo Page::icon( 'gear', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<strong><?php esc_html_e( 'Other MCP clients', 'bridgistic' ); ?></strong>
					<span><?php esc_html_e( 'Raw MCP JSON for any client that can launch a local stdio server.', 'bridgistic' ); ?></span>
				</button>
			</div>
			<p class="bridgistic-help is-muted">
				<?php
				printf(
					/* translators: %s: link to the multi-site and other-clients guide. */
					esc_html__( 'Connecting more than one WordPress site? See %s.', 'bridgistic' ),
					'<a href="https://github.com/wordpressistic/bridgistic/blob/main/docs/CONNECT_OTHER_AI.md" target="_blank" rel="noopener noreferrer">' . esc_html__( 'multi-site &amp; other AI clients', 'bridgistic' ) . '</a>'
				); // phpcs:ignore WordPress.Security.EscapeOutput
				?>
			</p>
			<div class="bridgistic-step-actions">
				<button type="button" class="bridgistic-button is-primary" data-step-next="2"><?php esc_html_e( 'Continue', 'bridgistic' ); ?></button>
			</div>
		</div>
	</section>

	<!-- Step 2: permission preset -->
	<section class="bridgistic-card bridgistic-step" data-step-panel="2">
		<header class="bridgistic-step-head">
			<span class="bridgistic-step-num">2</span>
			<h2><?php esc_html_e( 'Choose permission preset', 'bridgistic' ); ?></h2>
			<span class="bridgistic-step-check"><?php echo Page::icon( 'check', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		</header>
		<div class="bridgistic-step-body">
			<div class="bridgistic-choice-grid is-presets" role="radiogroup" aria-label="<?php esc_attr_e( 'Permission preset', 'bridgistic' ); ?>">
				<?php foreach ( $data['presets'] as $preset_id => $preset ) : ?>
					<button
						type="button"
						class="bridgistic-choice<?php echo $preset['risky'] ? ' is-risky' : ''; ?><?php echo 'read_only' === $preset_id ? ' is-selected' : ''; ?>"
						data-preset="<?php echo esc_attr( $preset_id ); ?>"
						data-risky="<?php echo $preset['risky'] ? '1' : '0'; ?>"
					>
						<strong>
							<?php echo esc_html( $preset['label'] ); ?>
							<?php if ( $preset['risky'] ) : ?>
								<?php echo Page::badge( 'warn', __( 'High risk', 'bridgistic' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<?php elseif ( ! empty( $preset['require_approval'] ) ) : ?>
								<?php echo Page::badge( 'info', __( 'Approval required', 'bridgistic' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<?php endif; ?>
						</strong>
						<span><?php echo esc_html( $preset['description'] ); ?></span>
						<code class="bridgistic-choice-scopes"><?php echo esc_html( implode( ' · ', array_slice( (array) $preset['scopes'], 0, 5 ) ) . ( count( (array) $preset['scopes'] ) > 5 ? ' +' . ( count( (array) $preset['scopes'] ) - 5 ) : '' ) ); ?></code>
					</button>
				<?php endforeach; ?>
			</div>
			<div class="bridgistic-callout is-danger" data-dev-warning hidden>
				<?php echo Page::icon( 'warn', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<p><?php esc_html_e( 'Developer Mode can access sensitive tools such as database, filesystem, and PHP execution. Use only on sites you control. Destructive operations still require approval.', 'bridgistic' ); ?></p>
			</div>
			<div class="bridgistic-step-actions">
				<button type="button" class="bridgistic-button is-ghost" data-step-back="1"><?php esc_html_e( 'Back', 'bridgistic' ); ?></button>
				<button type="button" class="bridgistic-button is-primary" data-step-next="3"><?php esc_html_e( 'Continue', 'bridgistic' ); ?></button>
			</div>
		</div>
	</section>

	<!-- Step 3: generate key -->
	<section class="bridgistic-card bridgistic-step" data-step-panel="3">
		<header class="bridgistic-step-head">
			<span class="bridgistic-step-num">3</span>
			<h2><?php esc_html_e( 'Generate connection key', 'bridgistic' ); ?></h2>
			<span class="bridgistic-step-check"><?php echo Page::icon( 'check', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		</header>
		<div class="bridgistic-step-body">
			<div class="bridgistic-field-row">
				<label for="bridgistic-key-label"><?php esc_html_e( 'Key label (optional)', 'bridgistic' ); ?></label>
				<input type="text" id="bridgistic-key-label" class="bridgistic-input" placeholder="<?php esc_attr_e( 'e.g. Claude on my laptop', 'bridgistic' ); ?>" maxlength="120" />
			</div>
			<div class="bridgistic-step-actions">
				<button type="button" class="bridgistic-button is-ghost" data-step-back="2"><?php esc_html_e( 'Back', 'bridgistic' ); ?></button>
				<button type="button" class="bridgistic-button is-primary" id="bridgistic-create-key">
					<?php echo Page::icon( 'key', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php esc_html_e( 'Create key', 'bridgistic' ); ?>
				</button>
			</div>

			<div id="bridgistic-key-result" hidden>
				<div class="bridgistic-callout is-success">
					<?php echo Page::icon( 'check', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<p><?php esc_html_e( 'Key created. The secret below is shown once — copy it now. It is stored encrypted and cannot be displayed again.', 'bridgistic' ); ?></p>
				</div>
				<div class="bridgistic-secret-grid">
					<div class="bridgistic-secret-row">
						<label><?php esc_html_e( 'Key ID', 'bridgistic' ); ?></label>
						<code id="bridgistic-new-key-id"></code>
						<button type="button" class="bridgistic-button is-soft is-small" data-copy-target="bridgistic-new-key-id"><?php echo Page::icon( 'copy', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Copy', 'bridgistic' ); ?></button>
					</div>
					<div class="bridgistic-secret-row is-secret">
						<label><?php esc_html_e( 'Secret', 'bridgistic' ); ?></label>
						<code id="bridgistic-new-key-secret" class="bridgistic-secret-value"></code>
						<button type="button" class="bridgistic-button is-soft is-small" data-copy-target="bridgistic-new-key-secret"><?php echo Page::icon( 'copy', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Copy', 'bridgistic' ); ?></button>
					</div>
				</div>
				<div class="bridgistic-step-actions">
					<button type="button" class="bridgistic-button is-primary" data-step-next="4"><?php esc_html_e( 'I copied the secret — continue', 'bridgistic' ); ?></button>
				</div>
			</div>
		</div>
	</section>

	<!-- Step 4: config -->
	<section class="bridgistic-card bridgistic-step" data-step-panel="4">
		<header class="bridgistic-step-head">
			<span class="bridgistic-step-num">4</span>
			<h2><?php esc_html_e( 'Generate config', 'bridgistic' ); ?></h2>
			<span class="bridgistic-step-check"><?php echo Page::icon( 'check', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		</header>
		<div class="bridgistic-step-body">

			<div class="bridgistic-tabs" role="tablist">
				<button type="button" class="bridgistic-tab is-active" data-config-tab="extension" role="tab"><?php esc_html_e( 'Desktop Extension', 'bridgistic' ); ?></button>
				<button type="button" class="bridgistic-tab" data-config-tab="desktop" role="tab"><?php esc_html_e( 'Claude Desktop (manual)', 'bridgistic' ); ?></button>
				<button type="button" class="bridgistic-tab" data-config-tab="code" role="tab"><?php esc_html_e( 'Claude Code', 'bridgistic' ); ?></button>
				<button type="button" class="bridgistic-tab" data-config-tab="codex" role="tab"><?php esc_html_e( 'Codex CLI', 'bridgistic' ); ?></button>
				<button type="button" class="bridgistic-tab" data-config-tab="gemini" role="tab"><?php esc_html_e( 'Gemini CLI', 'bridgistic' ); ?></button>
				<button type="button" class="bridgistic-tab" data-config-tab="remote" role="tab"><?php esc_html_e( 'ChatGPT / remote MCP', 'bridgistic' ); ?></button>
				<button type="button" class="bridgistic-tab" data-config-tab="cli" role="tab"><?php esc_html_e( 'CLI command', 'bridgistic' ); ?></button>
			</div>

			<div data-config-panel="desktop">
				<p class="bridgistic-help">
					<?php esc_html_e( 'Merge this into your Claude Desktop config, update the server path, then restart Claude Desktop. Config location:', 'bridgistic' ); ?>
					<code>~/Library/Application Support/Claude/claude_desktop_config.json</code> (macOS) ·
					<code>%APPDATA%\Claude\claude_desktop_config.json</code> (Windows)
				</p>
				<pre class="bridgistic-code" id="bridgistic-config-desktop"></pre>
			</div>
			<div data-config-panel="extension" hidden>
				<p class="bridgistic-help">
					<?php esc_html_e( 'One-click setup: download the extension, double-click it (Claude Desktop opens), then copy-paste these three values when prompted, one at a time. The secret is stored securely by Claude Desktop.', 'bridgistic' ); ?>
				</p>
				<div class="bridgistic-step-actions" style="margin-top:0">
					<a class="bridgistic-button is-primary" href="https://github.com/wordpressistic/bridgistic/releases/latest/download/bridgistic.mcpb" target="_blank" rel="noopener noreferrer">
						<?php echo Page::icon( 'download', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php esc_html_e( 'Download bridgistic.mcpb', 'bridgistic' ); ?>
					</a>
				</div>
				<div class="bridgistic-secret-grid">
					<div class="bridgistic-secret-row">
						<label><?php esc_html_e( 'Site URL', 'bridgistic' ); ?></label>
						<code id="bridgistic-ext-site-url"></code>
						<button type="button" class="bridgistic-button is-soft is-small" data-copy-target="bridgistic-ext-site-url"><?php echo Page::icon( 'copy', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Copy', 'bridgistic' ); ?></button>
					</div>
					<div class="bridgistic-secret-row">
						<label><?php esc_html_e( 'Key ID', 'bridgistic' ); ?></label>
						<code id="bridgistic-ext-key-id"></code>
						<button type="button" class="bridgistic-button is-soft is-small" data-copy-target="bridgistic-ext-key-id"><?php echo Page::icon( 'copy', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Copy', 'bridgistic' ); ?></button>
					</div>
					<div class="bridgistic-secret-row is-secret">
						<label><?php esc_html_e( 'Secret', 'bridgistic' ); ?></label>
						<code id="bridgistic-ext-secret" class="bridgistic-secret-value"></code>
						<button type="button" class="bridgistic-button is-soft is-small" data-copy-target="bridgistic-ext-secret"><?php echo Page::icon( 'copy', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Copy', 'bridgistic' ); ?></button>
					</div>
				</div>
			</div>
			<div data-config-panel="code" hidden>
				<p class="bridgistic-help"><?php esc_html_e( 'Easiest path — install from the plugin marketplace inside Claude Code:', 'bridgistic' ); ?></p>
				<pre class="bridgistic-code"><?php echo esc_html( (string) $data['marketplace_cmds'] ); ?></pre>
				<p class="bridgistic-help"><?php esc_html_e( 'Then export the environment variables, or save this as .mcp.json in your project:', 'bridgistic' ); ?></p>
				<pre class="bridgistic-code" id="bridgistic-config-code"></pre>
			</div>
			<div data-config-panel="codex" hidden>
				<p class="bridgistic-help">
					<?php esc_html_e( 'Add this block to your Codex config, then restart Codex. Config location:', 'bridgistic' ); ?>
					<code>~/.codex/config.toml</code>
				</p>
				<pre class="bridgistic-code" id="bridgistic-config-codex"></pre>
			</div>
			<div data-config-panel="gemini" hidden>
				<p class="bridgistic-help">
					<?php esc_html_e( 'Merge this into your Gemini CLI settings, then restart Gemini CLI. Config location:', 'bridgistic' ); ?>
					<code>~/.gemini/settings.json</code>
				</p>
				<pre class="bridgistic-code" id="bridgistic-config-gemini"></pre>
			</div>
			<div data-config-panel="remote" hidden>
				<p class="bridgistic-help">
					<?php esc_html_e( 'ChatGPT cannot launch a local MCP server, so it connects to a hosted one instead. Bridgistic Cloud is that hosted endpoint: you add the URL below in ChatGPT, it sends you back here to approve the connection in your own WordPress admin, and the credential it receives never leaves the connector.', 'bridgistic' ); ?>
				</p>

				<div class="bridgistic-secret-grid">
					<div class="bridgistic-secret-row">
						<label><?php esc_html_e( 'MCP server URL', 'bridgistic' ); ?></label>
						<code id="bridgistic-remote-endpoint"><?php echo esc_html( (string) $data['cloud_endpoint'] ); ?></code>
						<button type="button" class="bridgistic-button is-soft is-small" data-copy-target="bridgistic-remote-endpoint"><?php echo Page::icon( 'copy', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Copy', 'bridgistic' ); ?></button>
					</div>
				</div>

				<p class="bridgistic-help">
					<?php esc_html_e( 'Add it as a remote MCP server / connector in your AI client, then complete the OAuth prompt. The exact menu name and which plans expose it change from release to release, so follow your client\'s own current documentation for adding a remote MCP server rather than a fixed set of clicks here.', 'bridgistic' ); ?>
				</p>

				<div class="bridgistic-callout is-warn">
					<?php echo Page::icon( 'warn', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span>
						<?php esc_html_e( 'Bridgistic Cloud is a free public beta. It has not had an independent third-party security review. Remote MCP availability depends on your AI client\'s current plan and workspace settings — if you do not see a way to add a custom MCP server, your plan may not expose one yet.', 'bridgistic' ); ?>
					</span>
				</div>

				<div class="bridgistic-step-actions" style="margin-top:0">
					<a class="bridgistic-button is-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=bridgistic-cloud' ) ); ?>">
						<?php echo Page::icon( 'plug', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php esc_html_e( 'Open Bridgistic Cloud', 'bridgistic' ); ?>
					</a>
					<a class="bridgistic-button is-soft" href="<?php echo esc_url( admin_url( 'admin.php?page=bridgistic-health' ) ); ?>">
						<?php echo Page::icon( 'pulse', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php esc_html_e( 'Run diagnostics', 'bridgistic' ); ?>
					</a>
					<a class="bridgistic-button is-soft" href="https://github.com/wordpressistic/bridgistic/blob/main/docs/CHATGPT_SETUP.md" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'ChatGPT setup guide', 'bridgistic' ); ?>
					</a>
				</div>

				<p class="bridgistic-help is-muted">
					<?php esc_html_e( 'A key created on this page is for local clients and is not needed for the remote path — the cloud connector mints its own scoped key when you approve the connection.', 'bridgistic' ); ?>
				</p>
			</div>
			<div data-config-panel="cli" hidden>
				<p class="bridgistic-help"><?php esc_html_e( 'One-liner for claude mcp add (fill in the real server path):', 'bridgistic' ); ?></p>
				<pre class="bridgistic-code" id="bridgistic-config-cli"></pre>
			</div>

			<p class="bridgistic-help is-muted">
				<?php esc_html_e( 'The config embeds your secret only if you generated the key in this session. Keep it private.', 'bridgistic' ); ?>
			</p>

			<div class="bridgistic-step-actions">
				<button type="button" class="bridgistic-button is-ghost" data-step-back="3"><?php esc_html_e( 'Back', 'bridgistic' ); ?></button>
				<button type="button" class="bridgistic-button is-soft" id="bridgistic-copy-config"><?php echo Page::icon( 'copy', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Copy config', 'bridgistic' ); ?></button>
				<button type="button" class="bridgistic-button is-soft" id="bridgistic-download-config"><?php echo Page::icon( 'download', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Download JSON', 'bridgistic' ); ?></button>
				<button type="button" class="bridgistic-button is-primary" data-step-next="5"><?php esc_html_e( 'Continue', 'bridgistic' ); ?></button>
			</div>
		</div>
	</section>

	<!-- Step 5: test -->
	<section class="bridgistic-card bridgistic-step" data-step-panel="5">
		<header class="bridgistic-step-head">
			<span class="bridgistic-step-num">5</span>
			<h2><?php esc_html_e( 'Test connection', 'bridgistic' ); ?></h2>
			<span class="bridgistic-step-check"><?php echo Page::icon( 'check', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		</header>
		<div class="bridgistic-step-body">
			<p class="bridgistic-help">
				<?php esc_html_e( 'Two separate checks confirm you\'re actually connected — a server check you run here, and a client check this page detects automatically.', 'bridgistic' ); ?>
			</p>

			<div class="bridgistic-check-block">
				<h3><?php esc_html_e( '1. Server check', 'bridgistic' ); ?></h3>
				<p class="bridgistic-help"><?php esc_html_e( 'Runs a signed request through the full pipeline on the server (REST → HMAC → scopes). This confirms WordPress itself is reachable and configured correctly — it does not yet confirm your AI client is set up right.', 'bridgistic' ); ?></p>
				<div class="bridgistic-step-actions">
					<button type="button" class="bridgistic-button is-primary" id="bridgistic-test-connection">
						<?php echo Page::icon( 'pulse', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php esc_html_e( 'Run test', 'bridgistic' ); ?>
					</button>
				</div>
				<div id="bridgistic-test-result" class="bridgistic-test-result" hidden></div>
			</div>

			<div class="bridgistic-check-block">
				<h3><?php esc_html_e( '2. Client check', 'bridgistic' ); ?></h3>
				<p class="bridgistic-help"><?php esc_html_e( 'In your AI client (the one you configured in Step 4), ask it to run bridgistic_get_site_info. This page checks automatically and updates the moment a real request arrives — no need to refresh or go check Logs yourself.', 'bridgistic' ); ?></p>
				<div id="bridgistic-client-status" class="bridgistic-test-result"></div>
			</div>

			<div class="bridgistic-step-actions">
				<button type="button" class="bridgistic-button is-ghost" data-step-back="4"><?php esc_html_e( 'Back', 'bridgistic' ); ?></button>
			</div>
			<div class="bridgistic-callout is-info" style="margin-top:14px">
				<?php echo Page::icon( 'info', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<p>
					<?php esc_html_e( 'All set? You can also download everything as a package for your computer.', 'bridgistic' ); ?>
					<a href="<?php echo esc_url( $data['export_url'] ); ?>"><?php esc_html_e( 'Export Package', 'bridgistic' ); ?></a>
				</p>
			</div>
		</div>
	</section>

</div>
