<?php
/**
 * License view — connect to WPistic, activate a key, see plan + gated features.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

use Bridgistic\License;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array $data { status: array, gates: array }
 * Status shape comes from the WPistic SDK (WpisticClient::status()).
 */
$status   = $data['status'];
$gates    = $data['gates'];
$active   = ! empty( $status['active'] );
$plan     = $active && ! empty( $status['plan'] ) ? $status['plan'] : 'free';
$expires  = isset( $status['expires_at'] ) ? (string) $status['expires_at'] : '';
$grace    = $status['grace_left'] ?? null;
$key_mask = isset( $status['key_mask'] ) ? (string) $status['key_mask'] : '';
?>
<div class="wrap bridgistic-admin">
	<h1>Bridgistic — License</h1>

	<?php settings_errors( 'bridgistic_license' ); ?>

	<section class="card" style="max-width:720px;padding:20px 24px;">
		<h2 style="margin-top:0;">Current plan: <strong style="text-transform:uppercase;"><?php echo esc_html( $plan ); ?></strong></h2>

		<?php if ( $active ) : ?>
			<p>✅ Licensed to this site<?php echo $key_mask ? ' — key <code>' . esc_html( $key_mask ) . '</code>' : ''; ?>.
			<?php if ( '' !== $expires ) : ?> Expires <?php echo esc_html( $expires ); ?>.<?php endif; ?>
			<?php if ( null !== $grace && (int) $grace > 0 ) : ?><br><em>Offline grace: <?php echo esc_html( human_time_diff( time(), time() + (int) $grace ) ); ?> remaining (server unreachable).</em><?php endif; ?>
			</p>
			<form method="post" style="display:inline-block;">
				<?php wp_nonce_field( 'bridgistic_license_action', 'bridgistic_license_nonce' ); ?>
				<input type="hidden" name="bridgistic_license_action" value="check" />
				<button class="button button-secondary" type="submit">Check again</button>
			</form>
			<form method="post" style="display:inline-block;margin-left:8px;">
				<?php wp_nonce_field( 'bridgistic_license_action', 'bridgistic_license_nonce' ); ?>
				<input type="hidden" name="bridgistic_license_action" value="deactivate" />
				<button class="button button-secondary" type="submit">Deactivate on this site</button>
			</form>
		<?php else : ?>
			<p>Free plan is active — the local bridge, keys, scopes, approvals, audit, snapshots and manual playbooks all work, forever.</p>
			<form method="post">
				<?php wp_nonce_field( 'bridgistic_license_action', 'bridgistic_license_nonce' ); ?>
				<input type="hidden" name="bridgistic_license_action" value="activate" />
				<p>
					<label for="bridgistic-license-key"><strong>Paste your WPistic license key</strong></label><br>
					<input id="bridgistic-license-key" class="regular-text" type="text" name="bridgistic_license_key" placeholder="wpk-… (from wpistic.com after purchase)" autocomplete="off" style="margin-top:6px;" />
				</p>
				<p><button class="button button-primary" type="submit">Activate</button>
				<a class="button" href="https://wpistic.com/pricing" target="_blank" rel="noopener noreferrer">Get a key →</a></p>
			</form>
		<?php endif; ?>
	</section>

	<section style="max-width:720px;margin-top:24px;">
		<h2>What each plan unlocks</h2>
		<table class="widefat striped" style="max-width:720px;">
			<thead><tr><th>Feature</th><th>Free</th><th>Starter</th><th>Pro</th><th>Agency</th></tr></thead>
			<tbody>
				<tr><td>Connect any AI (Claude, ChatGPT, Codex, Gemini, Cursor…)</td><td>✅</td><td>✅</td><td>✅</td><td>✅</td></tr>
				<tr><td>HMAC keys, scopes, approvals, audit, snapshots, playbooks</td><td>✅</td><td>✅</td><td>✅</td><td>✅</td></tr>
				<tr><td>Scheduled playbooks</td><td>Basic (<?php echo esc_html( (string) \Bridgistic\Scheduler::FREE_MAX_ACTIVE ); ?>)</td><td>Unlimited</td><td>Unlimited</td><td>Unlimited</td></tr>
				<tr><td>Advanced snapshots (retention, history)</td><td>—</td><td>—</td><td>✅</td><td>✅</td></tr>
				<tr><td>Audit export &amp; analytics</td><td>—</td><td>—</td><td>✅</td><td>✅</td></tr>
				<tr><td>AI skills marketplace (SEO, schema, audits)</td><td>—</td><td>—</td><td>✅</td><td>✅</td></tr>
				<tr><td>Agency dashboard — manage many sites</td><td>—</td><td>—</td><td>—</td><td>✅</td></tr>
				<tr><td>Team permissions</td><td>—</td><td>—</td><td>—</td><td>✅</td></tr>
				<tr><td>White-label</td><td>—</td><td>—</td><td>—</td><td>✅</td></tr>
			</tbody>
		</table>
		<p style="color:#666;">License validation runs twice daily and is signed by the WPistic platform; the license state is encrypted at rest and your key is never stored. If the server can't be reached, your site keeps working through a 7-day offline grace window — paid features pause only after grace expires, and free features never stop.</p>
	</section>
</div>
