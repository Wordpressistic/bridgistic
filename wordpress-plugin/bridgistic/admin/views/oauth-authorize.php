<?php
/**
 * OAuth consent screen. Standalone page (no wp-admin chrome) - see
 * OAuthAuthorizePage::render().
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
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php esc_html_e( 'Connect Bridgistic Cloud', 'bridgistic' ); ?></title>
	<style>
		/* Standalone page on purpose (no dashboard nav/footer/theme toggle — a
		   consent screen should look like one), but themed with the same
		   tokens and light/dark-by-OS-preference behavior as the dashboard so
		   it doesn't jar mid-flow. */
		:root {
			--bz-bg: #f7faf9;
			--bz-card: #ffffff;
			--bz-card-2: #f6f7fb;
			--bz-accent: #00b86b;
			--bz-border: #ddebe6;
			--bz-text: #12201e;
			--bz-text-2: #4f6660;
			--bz-danger-bg: #fbeae8;
			--bz-danger-border: #f0c9c4;
			--bz-danger-text: #9a2f24;
			--bz-warn-bg: #fff8e6;
			--bz-warn-border: #f2dfa3;
			--bz-warn-text: #6b5405;
		}
		@media (prefers-color-scheme: dark) {
			:root {
				--bz-bg: #101918;
				--bz-card: #1e2d2a;
				--bz-card-2: #172321;
				--bz-accent: #00ff85;
				--bz-border: rgba(134, 219, 184, 0.25);
				--bz-text: #f4fff9;
				--bz-text-2: #b8c9c3;
				--bz-danger-bg: rgba(239, 68, 68, 0.12);
				--bz-danger-border: rgba(239, 68, 68, 0.35);
				--bz-danger-text: #f4a3a3;
				--bz-warn-bg: rgba(251, 191, 36, 0.1);
				--bz-warn-border: rgba(251, 191, 36, 0.35);
				--bz-warn-text: #fbbf24;
			}
		}
		body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: var(--bz-bg); color: var(--bz-text); margin: 0; padding: 48px 20px; }
		.bridgistic-consent-card { max-width: 480px; margin: 0 auto; background: var(--bz-card); border: 1px solid var(--bz-border); border-radius: 14px; padding: 32px; box-shadow: 0 8px 30px rgba(20,20,40,.08); }
		.bridgistic-consent-card h1 { font-size: 1.3rem; margin: 0 0 6px; color: var(--bz-text); }
		.bridgistic-consent-card p.lead { color: var(--bz-text-2); margin: 0 0 16px; }
		.bridgistic-consent-beta { display: flex; gap: 8px; align-items: flex-start; color: var(--bz-warn-text); background: var(--bz-warn-bg); border: 1px solid var(--bz-warn-border); border-radius: 10px; padding: 10px 12px; margin-bottom: 22px; font-size: .82rem; }
		.bridgistic-consent-site { display: flex; align-items: center; gap: 10px; padding: 12px 14px; background: var(--bz-card-2); color: var(--bz-text); border-radius: 10px; margin-bottom: 22px; font-size: .92rem; }
		.bridgistic-consent-presets { display: flex; flex-direction: column; gap: 8px; margin-bottom: 22px; }
		.bridgistic-consent-presets label { display: flex; gap: 10px; align-items: flex-start; padding: 12px; border: 1px solid var(--bz-border); border-radius: 10px; cursor: pointer; }
		.bridgistic-consent-presets label:has(input:checked) { border-color: var(--bz-accent); background: var(--bz-card-2); }
		.bridgistic-consent-presets input { margin-top: 3px; }
		.bridgistic-consent-presets strong { display: block; font-size: .92rem; color: var(--bz-text); }
		.bridgistic-consent-presets span.desc { display: block; font-size: .82rem; color: var(--bz-text-2); }
		.bridgistic-consent-presets span.bridgistic-consent-risky { margin-top: 4px; color: var(--bz-danger-text); font-weight: 600; }
		.bridgistic-consent-actions { display: flex; gap: 10px; }
		.bridgistic-consent-actions button { flex: 1; padding: 11px 16px; border-radius: 9px; font-size: .92rem; font-weight: 600; cursor: pointer; border: 1px solid transparent; }
		.bridgistic-consent-actions .allow { background: var(--bz-accent); color: #052013; }
		.bridgistic-consent-actions .deny { background: transparent; color: var(--bz-text); border-color: var(--bz-border); }
		.bridgistic-consent-error { color: var(--bz-danger-text); background: var(--bz-danger-bg); border: 1px solid var(--bz-danger-border); border-radius: 10px; padding: 14px; }
	</style>
</head>
<body>
	<div class="bridgistic-consent-card">
		<h1><?php esc_html_e( 'Connect this site to Bridgistic Cloud', 'bridgistic' ); ?></h1>

		<?php if ( $data['error'] ) : ?>
			<p class="bridgistic-consent-error"><?php echo esc_html( (string) $data['error'] ); ?></p>
		<?php else : ?>
			<p class="lead"><?php esc_html_e( 'The Bridgistic cloud connector is asking to control this site through your AI assistant. Choose what it can do, then allow or deny.', 'bridgistic' ); ?></p>

			<div class="bridgistic-consent-beta">
				<?php echo Page::icon( 'warn', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span><?php esc_html_e( 'Public beta: the cloud connector has not yet had an independent third-party security review. Prefer the local connection (Bridgistic → AI / MCP Connections) for sites you can\'t afford to risk, and avoid approving Developer Mode-scoped access here.', 'bridgistic' ); ?></span>
			</div>

			<div class="bridgistic-consent-site">
				<?php echo Page::icon( 'globe', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span><?php echo esc_html( home_url() ); ?></span>
			</div>

			<form method="post" action="<?php echo esc_url( (string) $data['action_url'] ); ?>">
				<input type="hidden" name="action" value="bridgistic_oauth_consent" />
				<input type="hidden" name="client_id" value="<?php echo esc_attr( (string) $data['client_id'] ); ?>" />
				<input type="hidden" name="redirect_uri" value="<?php echo esc_attr( (string) $data['redirect_uri'] ); ?>" />
				<input type="hidden" name="code_challenge" value="<?php echo esc_attr( (string) $data['code_challenge'] ); ?>" />
				<input type="hidden" name="code_challenge_method" value="<?php echo esc_attr( (string) $data['code_challenge_method'] ); ?>" />
				<input type="hidden" name="state" value="<?php echo esc_attr( (string) $data['state'] ); ?>" />
				<?php wp_nonce_field( 'bridgistic_oauth_consent' ); ?>

				<div class="bridgistic-consent-presets">
					<?php
					foreach ( $data['presets'] as $preset_id => $preset ) :
						// Name the destructive capabilities rather than only styling
						// the option — an admin approving a connection should not
						// have to know which scope strings are dangerous.
						$granted_risky = array_values( array_intersect( (array) $data['risky_scopes'], (array) $preset['scopes'] ) );
						?>
						<label>
							<input type="radio" name="preset" value="<?php echo esc_attr( $preset_id ); ?>" <?php checked( 'read_only', $preset_id ); ?> />
							<span>
								<strong><?php echo esc_html( (string) $preset['label'] ); ?></strong>
								<span class="desc"><?php echo esc_html( (string) $preset['description'] ); ?></span>
								<?php if ( $granted_risky ) : ?>
									<span class="desc bridgistic-consent-risky">
										<?php
										printf(
											/* translators: %s: comma-separated list of destructive permission scopes. */
											esc_html__( 'Includes destructive access: %s', 'bridgistic' ),
											esc_html( implode( ', ', $granted_risky ) )
										);
										?>
									</span>
								<?php endif; ?>
							</span>
						</label>
					<?php endforeach; ?>
				</div>

				<div class="bridgistic-consent-actions">
					<button type="submit" name="decision" value="deny" class="deny"><?php esc_html_e( 'Deny', 'bridgistic' ); ?></button>
					<button type="submit" name="decision" value="allow" class="allow"><?php esc_html_e( 'Allow', 'bridgistic' ); ?></button>
				</div>
			</form>
		<?php endif; ?>
	</div>
</body>
</html>
