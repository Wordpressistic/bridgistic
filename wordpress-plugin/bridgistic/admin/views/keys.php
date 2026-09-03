<?php
/**
 * Keys & Scopes view.
 *
 * @package Bridgistic
 * @var array<string,mixed> $data
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bridgistic_scope_badge = static function ( string $scope ) use ( $data ): void {
	$risky = in_array( $scope, (array) $data['risky_scopes'], true );
	printf(
		'<span class="bridgistic-badge is-scope%s">%s</span>',
		$risky ? ' is-warn' : '',
		esc_html( $scope )
	);
};
?>

<header class="bridgistic-page-head bridgistic-fade-in">
	<h1><?php esc_html_e( 'Keys & Scopes', 'bridgistic' ); ?></h1>
	<p><?php esc_html_e( 'Use scoped keys for controlled access. Each key only unlocks what its scopes allow — revoke instantly at any time.', 'bridgistic' ); ?></p>
</header>

<?php if ( $data['fresh'] ) : ?>
	<section class="bridgistic-card bridgistic-callout-card is-success bridgistic-fade-in">
		<h2><?php esc_html_e( 'New key created — copy the secret now', 'bridgistic' ); ?></h2>
		<p class="bridgistic-help"><?php esc_html_e( 'Shown once. It is stored encrypted and cannot be displayed again.', 'bridgistic' ); ?></p>
		<div class="bridgistic-secret-grid">
			<div class="bridgistic-secret-row">
				<label><?php esc_html_e( 'Key ID', 'bridgistic' ); ?></label>
				<code id="bridgistic-fresh-key-id"><?php echo esc_html( (string) $data['fresh']['key_id'] ); ?></code>
				<button type="button" class="bridgistic-button is-soft is-small" data-copy-target="bridgistic-fresh-key-id"><?php echo Page::icon( 'copy', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Copy', 'bridgistic' ); ?></button>
			</div>
			<div class="bridgistic-secret-row is-secret">
				<label><?php esc_html_e( 'Secret', 'bridgistic' ); ?></label>
				<code id="bridgistic-fresh-key-secret" class="bridgistic-secret-value"><?php echo esc_html( (string) $data['fresh']['secret'] ); ?></code>
				<button type="button" class="bridgistic-button is-soft is-small" data-copy-target="bridgistic-fresh-key-secret"><?php echo Page::icon( 'copy', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Copy', 'bridgistic' ); ?></button>
			</div>
		</div>
	</section>
<?php endif; ?>

<section class="bridgistic-section">
	<h2 class="bridgistic-section-title"><?php esc_html_e( 'Active keys', 'bridgistic' ); ?></h2>

	<?php if ( ! $data['active'] ) : ?>
		<div class="bridgistic-card bridgistic-empty">
			<?php echo Page::icon( 'key', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<p><?php esc_html_e( 'No active keys. Create one below, or use the guided AI / MCP Connections wizard.', 'bridgistic' ); ?></p>
			<a class="bridgistic-button is-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=bridgistic-setup' ) ); ?>"><?php esc_html_e( 'Open AI / MCP Connections', 'bridgistic' ); ?></a>
		</div>
	<?php else : ?>
		<div class="bridgistic-grid is-2 bridgistic-stagger">
			<?php foreach ( $data['active'] as $k ) : ?>
				<article class="bridgistic-card bridgistic-key-card">
					<header class="bridgistic-key-head">
						<div>
							<h3><?php echo esc_html( (string) $k['label'] ); ?></h3>
							<code class="bridgistic-key-id"><?php echo esc_html( (string) $k['key_id'] ); ?></code>
						</div>
						<?php
						$preset_label = isset( $data['presets'][ $k['preset'] ] ) ? (string) $data['presets'][ $k['preset'] ]['label'] : __( 'Custom', 'bridgistic' );
						echo Page::badge( 'developer' === $k['preset'] ? 'warn' : 'info', $preset_label ); // phpcs:ignore WordPress.Security.EscapeOutput
						?>
					</header>

					<dl class="bridgistic-key-meta">
						<div><dt><?php esc_html_e( 'Created', 'bridgistic' ); ?></dt><dd><?php echo esc_html( Page::ago( (string) $k['created_at'] ) ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Last used', 'bridgistic' ); ?></dt><dd><?php echo esc_html( Page::ago( $k['last_used_at'] ? (string) $k['last_used_at'] : null ) ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Rate limit', 'bridgistic' ); ?></dt><dd><?php echo esc_html( (string) ( $k['rate_limit'] ?? 120 ) ); ?>/min</dd></div>
						<div><dt><?php esc_html_e( 'This month', 'bridgistic' ); ?></dt><dd><?php echo esc_html( number_format_i18n( (int) ( $k['usage']['this_month'] ?? 0 ) ) ); ?> req</dd></div>
						<div><dt><?php esc_html_e( 'Approval', 'bridgistic' ); ?></dt><dd><?php echo ! empty( $k['require_approval'] ) ? esc_html__( 'Required for writes', 'bridgistic' ) : '—'; ?></dd></div>
					</dl>

					<div class="bridgistic-key-scopes">
						<?php
						foreach ( (array) $k['scopes_list'] as $scope ) {
							$bridgistic_scope_badge( (string) $scope );
						}
						?>
					</div>

					<footer class="bridgistic-key-actions">
						<button type="button" class="bridgistic-button is-soft is-small" data-key-config="<?php echo esc_attr( (string) $k['key_id'] ); ?>">
							<?php echo Page::icon( 'copy', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Get config', 'bridgistic' ); ?>
						</button>
						<button type="button" class="bridgistic-button is-soft is-small" data-key-rotate="<?php echo esc_attr( (string) $k['key_id'] ); ?>">
							<?php echo Page::icon( 'refresh', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Rotate secret', 'bridgistic' ); ?>
						</button>
						<form method="post" action="<?php echo esc_url( (string) $data['action_url'] ); ?>" class="bridgistic-inline-form" data-confirm="revoke">
							<input type="hidden" name="action" value="bridgistic_revoke_key" />
							<input type="hidden" name="key_id" value="<?php echo esc_attr( (string) $k['key_id'] ); ?>" />
							<?php wp_nonce_field( 'bridgistic_revoke_key' ); ?>
							<button class="bridgistic-button is-danger-soft is-small"><?php esc_html_e( 'Revoke', 'bridgistic' ); ?></button>
						</form>
					</footer>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>

<section class="bridgistic-section" id="bridgistic-rotated-result" hidden>
	<div class="bridgistic-card bridgistic-callout-card is-success">
		<h2><?php esc_html_e( 'Secret rotated — copy it now', 'bridgistic' ); ?></h2>
		<p class="bridgistic-help"><?php esc_html_e( 'Shown once. Update your Claude config with the new secret; the old one stopped working immediately.', 'bridgistic' ); ?></p>
		<div class="bridgistic-secret-grid">
			<div class="bridgistic-secret-row">
				<label><?php esc_html_e( 'Key ID', 'bridgistic' ); ?></label>
				<code id="bridgistic-rotated-key-id"></code>
				<button type="button" class="bridgistic-button is-soft is-small" data-copy-target="bridgistic-rotated-key-id"><?php echo Page::icon( 'copy', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Copy', 'bridgistic' ); ?></button>
			</div>
			<div class="bridgistic-secret-row is-secret">
				<label><?php esc_html_e( 'New secret', 'bridgistic' ); ?></label>
				<code id="bridgistic-rotated-key-secret" class="bridgistic-secret-value"></code>
				<button type="button" class="bridgistic-button is-soft is-small" data-copy-target="bridgistic-rotated-key-secret"><?php echo Page::icon( 'copy', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Copy', 'bridgistic' ); ?></button>
			</div>
		</div>
	</div>
</section>

<section class="bridgistic-section" id="bridgistic-key-config-result" hidden>
	<div class="bridgistic-card">
		<h2 class="bridgistic-section-title"><?php esc_html_e( 'Config for key', 'bridgistic' ); ?> <code id="bridgistic-config-key-id"></code></h2>
		<p class="bridgistic-help"><?php esc_html_e( 'Stored secrets cannot be shown, so the secret field is a placeholder — paste the secret you copied at creation, or rotate the key for a fresh one.', 'bridgistic' ); ?></p>
		<pre class="bridgistic-code" id="bridgistic-existing-config"></pre>
		<div class="bridgistic-step-actions">
			<button type="button" class="bridgistic-button is-soft is-small" data-copy-target="bridgistic-existing-config"><?php echo Page::icon( 'copy', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Copy', 'bridgistic' ); ?></button>
		</div>
	</div>
</section>

<section class="bridgistic-section">
	<h2 class="bridgistic-section-title"><?php esc_html_e( 'Scope presets', 'bridgistic' ); ?></h2>
	<div class="bridgistic-grid is-4 bridgistic-stagger">
		<?php foreach ( $data['presets'] as $preset ) : ?>
			<article class="bridgistic-card bridgistic-preset-card<?php echo $preset['risky'] ? ' is-risky' : ''; ?>">
				<h3>
					<?php echo esc_html( (string) $preset['label'] ); ?>
					<?php if ( $preset['risky'] ) : ?>
						<?php echo Page::badge( 'warn', __( 'High risk', 'bridgistic' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php endif; ?>
				</h3>
				<p><?php echo esc_html( (string) $preset['description'] ); ?></p>
			</article>
		<?php endforeach; ?>
	</div>
</section>

<section class="bridgistic-section">
	<h2 class="bridgistic-section-title"><?php esc_html_e( 'Create new key (advanced)', 'bridgistic' ); ?></h2>
	<div class="bridgistic-card">
		<p class="bridgistic-help"><?php esc_html_e( 'Pick individual scopes when a preset does not fit. Prefer the smallest set that does the job.', 'bridgistic' ); ?></p>
		<form method="post" action="<?php echo esc_url( (string) $data['action_url'] ); ?>" class="bridgistic-form">
			<input type="hidden" name="action" value="bridgistic_create_key" />
			<?php wp_nonce_field( 'bridgistic_create_key' ); ?>

			<div class="bridgistic-field-row">
				<label for="bridgistic-adv-label"><?php esc_html_e( 'Label', 'bridgistic' ); ?></label>
				<input name="label" id="bridgistic-adv-label" type="text" class="bridgistic-input" value="" placeholder="<?php esc_attr_e( 'e.g. Claude — staging site', 'bridgistic' ); ?>" />
			</div>

			<fieldset class="bridgistic-field-row">
				<legend><?php esc_html_e( 'Scopes', 'bridgistic' ); ?></legend>
				<div class="bridgistic-scope-grid">
					<?php foreach ( $data['all_scopes'] as $scope => $desc ) : ?>
						<label class="bridgistic-scope-option<?php echo in_array( $scope, (array) $data['risky_scopes'], true ) ? ' is-risky' : ''; ?>">
							<input type="checkbox" name="scopes[]" value="<?php echo esc_attr( $scope ); ?>" />
							<span class="bridgistic-scope-option-name"><code><?php echo esc_html( $scope ); ?></code></span>
							<span class="bridgistic-scope-option-desc"><?php echo esc_html( $desc ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>

			<div class="bridgistic-field-cols">
				<div class="bridgistic-field-row">
					<label for="bridgistic-adv-rate"><?php esc_html_e( 'Rate limit (req/min)', 'bridgistic' ); ?></label>
					<input name="rate_limit" id="bridgistic-adv-rate" type="number" class="bridgistic-input is-narrow" value="120" min="1" max="6000" />
				</div>
				<div class="bridgistic-field-row">
					<label for="bridgistic-adv-ips"><?php esc_html_e( 'IP allowlist (optional, one per line)', 'bridgistic' ); ?></label>
					<textarea name="ip_allowlist" id="bridgistic-adv-ips" rows="2" class="bridgistic-input" placeholder="203.0.113.10&#10;198.51.100.0/24"></textarea>
				</div>
			</div>

			<label class="bridgistic-check-row">
				<input type="checkbox" name="require_approval" value="1" />
				<span><?php esc_html_e( 'Require human approval before this key can perform any write or destructive operation (recommended for live sites).', 'bridgistic' ); ?></span>
			</label>

			<button class="bridgistic-button is-primary"><?php echo Page::icon( 'key', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Create key', 'bridgistic' ); ?></button>
		</form>
	</div>
</section>

<?php if ( $data['revoked'] ) : ?>
	<section class="bridgistic-section">
		<h2 class="bridgistic-section-title"><?php esc_html_e( 'Revoked keys', 'bridgistic' ); ?></h2>
		<div class="bridgistic-card">
			<table class="bridgistic-table">
				<thead><tr><th><?php esc_html_e( 'Label', 'bridgistic' ); ?></th><th><?php esc_html_e( 'Key ID', 'bridgistic' ); ?></th><th><?php esc_html_e( 'Last used', 'bridgistic' ); ?></th><th></th></tr></thead>
				<tbody>
					<?php foreach ( $data['revoked'] as $k ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $k['label'] ); ?></td>
							<td><code><?php echo esc_html( (string) $k['key_id'] ); ?></code></td>
							<td><?php echo esc_html( Page::ago( $k['last_used_at'] ? (string) $k['last_used_at'] : null ) ); ?></td>
							<td class="bridgistic-table-actions">
								<form method="post" action="<?php echo esc_url( (string) $data['action_url'] ); ?>" class="bridgistic-inline-form">
									<input type="hidden" name="action" value="bridgistic_enable_key" />
									<input type="hidden" name="key_id" value="<?php echo esc_attr( (string) $k['key_id'] ); ?>" />
									<?php wp_nonce_field( 'bridgistic_enable_key' ); ?>
									<button class="bridgistic-button is-soft is-small"><?php esc_html_e( 'Re-enable', 'bridgistic' ); ?></button>
								</form>
								<form method="post" action="<?php echo esc_url( (string) $data['action_url'] ); ?>" class="bridgistic-inline-form" data-confirm="delete">
									<input type="hidden" name="action" value="bridgistic_delete_key" />
									<input type="hidden" name="key_id" value="<?php echo esc_attr( (string) $k['key_id'] ); ?>" />
									<?php wp_nonce_field( 'bridgistic_delete_key' ); ?>
									<button class="bridgistic-button is-danger-soft is-small"><?php esc_html_e( 'Delete permanently', 'bridgistic' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>
<?php endif; ?>
