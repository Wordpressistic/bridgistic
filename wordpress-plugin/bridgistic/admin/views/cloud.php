<?php
/**
 * Bridgistic Cloud view — informational page for the hosted MCP connector.
 * No form on this page; the OAuth handshake itself starts from the AI
 * client and lands back on OAuthAuthorizePage's consent screen.
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
	<h1><?php esc_html_e( 'Bridgistic Cloud', 'bridgistic' ); ?> <?php echo Page::badge( 'warn', __( 'Public beta', 'bridgistic' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></h1>
	<p><?php esc_html_e( 'A hosted relay so a remote AI client (like ChatGPT, or Claude\'s remote connector) can reach this site without you running a local server. No Node.js, no config files, no copy-pasted secret.', 'bridgistic' ); ?></p>
</header>

<div class="bridgistic-callout is-danger bridgistic-fade-in">
	<?php echo Page::icon( 'warn', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<p><?php esc_html_e( 'This connector has not yet had an independent third-party security review. Its automated test suite covers tenant isolation, credential encryption, request-forgery protection, and rate limiting, and the relay applies layered per-IP, per-tenant, and global limits — but automated tests are not an audit. If this site cannot tolerate any risk, use the local connection (AI / MCP Connections) instead: it never sends your credentials anywhere but between your own computer and this site.', 'bridgistic' ); ?></p>
</div>

<section class="bridgistic-card bridgistic-fade-in">
	<h2><?php esc_html_e( 'How it works', 'bridgistic' ); ?></h2>
	<ol class="bridgistic-help" style="padding-left:18px;">
		<li><?php esc_html_e( 'In your AI client, add a custom / remote MCP connector and paste the URL below.', 'bridgistic' ); ?></li>
		<li><?php esc_html_e( 'Your client redirects you here to approve the connection — you\'ll see a consent screen and pick a permission preset, same as the local flow.', 'bridgistic' ); ?></li>
		<li><?php esc_html_e( 'Approving mints a normal scoped Bridgistic key behind the scenes. Nothing further to configure on this site.', 'bridgistic' ); ?></li>
	</ol>

	<div class="bridgistic-secret-grid">
		<div class="bridgistic-secret-row">
			<label><?php esc_html_e( 'Connector URL', 'bridgistic' ); ?></label>
			<code id="bridgistic-cloud-url"><?php echo esc_html( (string) $data['connector_url'] ); ?></code>
			<button type="button" class="bridgistic-button is-soft is-small" data-copy-target="bridgistic-cloud-url"><?php echo Page::icon( 'copy', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Copy', 'bridgistic' ); ?></button>
		</div>
	</div>

	<p class="bridgistic-help is-muted" style="margin-top:14px;">
		<?php
		printf(
			/* translators: 1: link to ChatGPT setup guide, 2: link to full cloud connector status doc. */
			esc_html__( 'Step-by-step for ChatGPT: %1$s. Full technical status of this connector: %2$s.', 'bridgistic' ),
			'<a href="https://github.com/wordpressistic/bridgistic/blob/main/docs/CHATGPT_SETUP.md" target="_blank" rel="noopener noreferrer">' . esc_html__( 'ChatGPT connector guide', 'bridgistic' ) . '</a>',
			'<a href="https://github.com/wordpressistic/bridgistic/blob/main/docs/CLOUD_CONNECTOR.md" target="_blank" rel="noopener noreferrer">' . esc_html__( 'CLOUD_CONNECTOR.md', 'bridgistic' ) . '</a>'
		); // phpcs:ignore WordPress.Security.EscapeOutput
		?>
	</p>
</section>

<div class="bridgistic-callout is-info bridgistic-fade-in">
	<?php echo Page::icon( 'info', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<p>
		<?php
		printf(
			/* translators: %s: link to Claude Setup. */
			esc_html__( 'Only using Claude Desktop, Claude Code, Codex CLI, or Gemini CLI? You don\'t need this — %s runs entirely on your own computer and has had far more real-world use.', 'bridgistic' ),
			'<a href="' . esc_url( (string) $data['setup_url'] ) . '">' . esc_html__( 'the local AI / MCP Connections wizard', 'bridgistic' ) . '</a>'
		); // phpcs:ignore WordPress.Security.EscapeOutput
		?>
	</p>
</div>

<p class="bridgistic-help">
	<?php
	printf(
		/* translators: 1: link to Keys & Scopes, 2: link to Logs. */
		esc_html__( 'Keys minted through this connector are labeled "Cloud connector — mcp.wpistic.cloud" and behave like any other key — manage or revoke them any time in %1$s, and every request is recorded in %2$s exactly like local requests.', 'bridgistic' ),
		'<a href="' . esc_url( (string) $data['keys_url'] ) . '">' . esc_html__( 'Keys & Scopes', 'bridgistic' ) . '</a>',
		'<a href="' . esc_url( (string) $data['logs_url'] ) . '">' . esc_html__( 'Logs', 'bridgistic' ) . '</a>'
	); // phpcs:ignore WordPress.Security.EscapeOutput
	?>
</p>
