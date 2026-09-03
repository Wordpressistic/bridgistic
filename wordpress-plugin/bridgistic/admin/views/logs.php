<?php
/**
 * Logs view — filter chips + readable audit rows.
 *
 * @package Bridgistic
 * @var array<string,mixed> $data
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bridgistic_status_kind = static function ( string $status ): string {
	if ( 'ok' === $status || 'dry_run' === $status ) {
		return 'pass';
	}
	if ( 'queued' === $status || 0 === strpos( $status, 'approval' ) ) {
		return 'info';
	}
	if ( in_array( $status, array( 'error', 'denied', 'snapshot_failed' ), true ) ) {
		return 'fail';
	}
	return 'muted';
};
?>

<header class="bridgistic-page-head bridgistic-fade-in">
	<h1><?php esc_html_e( 'Logs', 'bridgistic' ); ?></h1>
	<p><?php esc_html_e( 'Every bridge operation, recorded: who (key), what (action), when, and outcome. Parameters are stored as hashes — sensitive values never appear here.', 'bridgistic' ); ?></p>
</header>

<nav class="bridgistic-chips bridgistic-fade-in" aria-label="<?php esc_attr_e( 'Log filters', 'bridgistic' ); ?>">
	<?php foreach ( $data['filters'] as $key => $label ) : ?>
		<a
			class="bridgistic-chip<?php echo $key === $data['filter'] ? ' is-active' : ''; ?>"
			href="<?php echo esc_url( add_query_arg( 'filter', $key, $data['base_url'] ) ); ?>"
		><?php echo esc_html( $label ); ?></a>
	<?php endforeach; ?>
</nav>

<?php if ( ! $data['rows'] ) : ?>
	<div class="bridgistic-card bridgistic-empty bridgistic-fade-in">
		<?php echo Page::icon( 'list', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<p><?php esc_html_e( 'No MCP activity yet. Connect Claude to start seeing activity.', 'bridgistic' ); ?></p>
		<a class="bridgistic-button is-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=bridgistic-setup' ) ); ?>"><?php esc_html_e( 'Set Up Claude', 'bridgistic' ); ?></a>
	</div>
<?php else : ?>
	<div class="bridgistic-card bridgistic-fade-in">
		<table class="bridgistic-table bridgistic-log-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'bridgistic' ); ?></th>
					<th><?php esc_html_e( 'Action', 'bridgistic' ); ?></th>
					<th><?php esc_html_e( 'Key', 'bridgistic' ); ?></th>
					<th><?php esc_html_e( 'Status', 'bridgistic' ); ?></th>
					<th><?php esc_html_e( 'IP', 'bridgistic' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $data['rows'] as $row ) : ?>
					<tr>
						<td>
							<span title="<?php echo esc_attr( (string) $row['created_at'] . ' UTC' ); ?>"><?php echo esc_html( Page::ago( (string) $row['created_at'] ) ); ?></span>
						</td>
						<td><code><?php echo esc_html( (string) $row['action'] ); ?></code></td>
						<td><code class="bridgistic-key-id"><?php echo esc_html( (string) ( $row['key_id'] ?: '—' ) ); ?></code></td>
						<td><?php echo Page::badge( $bridgistic_status_kind( (string) $row['status'] ), (string) $row['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
						<td><?php echo esc_html( (string) ( $row['ip'] ?: '—' ) ); ?></td>
						<td class="bridgistic-table-actions">
							<?php if ( ! empty( $row['summary'] ) ) : ?>
								<button type="button" class="bridgistic-button is-ghost is-small" data-log-toggle="log-<?php echo esc_attr( (string) $row['id'] ); ?>"><?php esc_html_e( 'Details', 'bridgistic' ); ?></button>
							<?php endif; ?>
						</td>
					</tr>
					<?php if ( ! empty( $row['summary'] ) ) : ?>
						<tr class="bridgistic-log-details" id="log-<?php echo esc_attr( (string) $row['id'] ); ?>" hidden>
							<td colspan="6">
								<pre class="bridgistic-code is-compact"><?php echo esc_html( (string) $row['summary'] ); ?></pre>
								<span class="bridgistic-help is-muted"><?php echo esc_html( sprintf( __( 'Params hash: %s', 'bridgistic' ), substr( (string) $row['params_hash'], 0, 16 ) . '…' ) ); ?></span>
							</td>
						</tr>
					<?php endif; ?>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
