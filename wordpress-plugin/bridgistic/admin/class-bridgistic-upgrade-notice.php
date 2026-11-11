<?php
/**
 * Free-plan upgrade notice (1.5.11).
 *
 * Shown to administrators when the site has used 80% or more of the free
 * monthly AI-request allowance, and in a stronger form once it is used up.
 * The close button snoozes it for one day for that user, after which it comes
 * back while the condition still holds. Pro sites never see it.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

use Bridgistic\PlanLimits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UpgradeNotice {

	public const META        = 'bridgistic_upgrade_notice_snoozed_until';
	public const AJAX_ACTION = 'bridgistic_snooze_upgrade_notice';
	public const SNOOZE      = DAY_IN_SECONDS;
	public const WARN_AT     = 80;

	public function hooks(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'snooze' ) );
	}

	/**
	 * Which notice to show, if any: '' (none), 'warn' or 'limit'.
	 *
	 * @param array<string,mixed> $snap PlanLimits::snapshot().
	 */
	public static function state( array $snap, int $snoozed_until, int $now ): string {
		if ( ! empty( $snap['is_pro'] ) || $snoozed_until > $now ) {
			return '';
		}
		$limit = (int) ( $snap['limit'] ?? 0 );
		$used  = (int) ( $snap['used'] ?? 0 );
		if ( $limit <= 0 ) {
			return '';
		}
		if ( $used >= $limit ) {
			return 'limit';
		}
		return (int) ( $snap['percent'] ?? 0 ) >= self::WARN_AT ? 'warn' : '';
	}

	public function render(): void {
		if ( ! current_user_can( Controller::CAP ) ) {
			return;
		}
		$snap  = PlanLimits::snapshot();
		$until = (int) get_user_meta( get_current_user_id(), self::META, true );
		$state = self::state( $snap, $until, time() );
		if ( '' === $state ) {
			return;
		}

		$reset   = wp_date( get_option( 'date_format', 'F j, Y' ), (int) $snap['resets_at'] );
		$limit   = number_format_i18n( (int) $snap['limit'] );
		$used    = number_format_i18n( min( (int) $snap['used'], (int) $snap['limit'] ) );
		$premium = admin_url( 'admin.php?page=bridgistic-premium' );
		$usage   = admin_url( 'admin.php?page=bridgistic-settings#bridgistic-usage' );
		$nonce   = wp_create_nonce( self::AJAX_ACTION );
		$class   = 'limit' === $state ? 'notice-error' : 'notice-warning';

		if ( 'limit' === $state ) {
			/* translators: 1: monthly limit, 2: reset date. */
			$headline = sprintf( __( 'Your free Bridgistic allowance is used up: %1$s of %1$s AI requests this month. AI clients are paused until %2$s.', 'bridgistic' ), $limit, $reset );
		} else {
			/* translators: 1: used, 2: monthly limit, 3: reset date. */
			$headline = sprintf( __( 'Bridgistic Free: %1$s of %2$s AI requests used this month. The allowance resets on %3$s.', 'bridgistic' ), $used, $limit, $reset );
		}
		/* translators: 1: monthly price, 2: yearly price. */
		$pitch = sprintf( __( 'Bridgistic Pro removes the monthly cap and unlocks unlimited scheduled playbooks, snapshots past the free 50 and audit log export, for $%1$d/month or $%2$d/year.', 'bridgistic' ), PlanLimits::PRO_MONTHLY_USD, PlanLimits::PRO_YEARLY_USD );
		?>
		<div class="notice <?php echo esc_attr( $class ); ?> is-dismissible bridgistic-upgrade-notice" data-nonce="<?php echo esc_attr( $nonce ); ?>" style="border-left-color:#C6A15B;padding:12px 14px">
			<p style="margin:0 0 6px;font-size:14px"><strong>Bridgistic</strong> · <?php echo esc_html( $headline ); ?></p>
			<p style="margin:0 0 10px"><?php echo esc_html( $pitch ); ?></p>
			<p style="margin:0">
				<a class="button button-primary" href="<?php echo esc_url( PlanLimits::UPGRADE_URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade to Pro', 'bridgistic' ); ?></a>
				<a class="button" href="<?php echo esc_url( $premium ); ?>"><?php esc_html_e( 'I have a license key', 'bridgistic' ); ?></a>
				<a href="<?php echo esc_url( $usage ); ?>" style="margin-left:8px"><?php esc_html_e( 'View usage', 'bridgistic' ); ?></a>
			</p>
		</div>
		<script>
		(function () {
			document.addEventListener('click', function (e) {
				var btn = e.target.closest && e.target.closest('.bridgistic-upgrade-notice .notice-dismiss');
				if (!btn || !window.fetch) { return; }
				var box = btn.closest('.bridgistic-upgrade-notice');
				var body = new URLSearchParams({ action: '<?php echo esc_js( self::AJAX_ACTION ); ?>', _ajax_nonce: box.getAttribute('data-nonce') });
				fetch('<?php echo esc_url_raw( admin_url( 'admin-ajax.php' ) ); ?>', { method: 'POST', credentials: 'same-origin', body: body });
			});
		})();
		</script>
		<?php
	}

	/** AJAX: hide the notice for this user for one day. */
	public function snooze(): void {
		check_ajax_referer( self::AJAX_ACTION );
		if ( ! current_user_can( Controller::CAP ) ) {
			wp_send_json_error( array(), 403 );
		}
		update_user_meta( get_current_user_id(), self::META, time() + self::SNOOZE );
		wp_send_json_success();
	}
}
