<?php
/**
 * WPistic licensing integration — thin facade over the vendored WPistic
 * WordPress SDK (includes/sdk). One client instance per site, shared by
 * every feature gate in the plugin.
 *
 * Product: Bridgistic (WordPressistic LLC)
 * Platform: api.wpistic.com — canonical /api/v1/licenses contract
 *   activate → { key + site identity } → activation_token + verification_key
 *   validate → HMAC-signed response, cached for offline grace (7 days)
 *   refresh  → token rotation, run twice-daily via WP-Cron
 *
 * Feature gating is entitlement-driven (the platform decides what a plan
 * includes), with a small plan-name fallback so the UI can render a
 * feature matrix before the first successful validation lands.
 *
 * Security notes:
 *   - The raw license key is sent once at activation and NEVER stored.
 *   - State is AES-256-GCM encrypted at rest (Security\TokenStorage).
 *   - Validate responses are HMAC-verified before being trusted.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic;

use WPistic\Sdk\WpisticClient;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class License {

	public const PRODUCT_SLUG = 'bridgistic';

	/**
	 * Entitlement keys the platform may grant; each maps to a plugin feature.
	 * When a key is present and truthy, the feature is unlocked regardless of
	 * plan naming — the platform is the source of truth.
	 */
	private const FEATURE_ENTITLEMENTS = array(
		'scheduled_playbooks_unlimited' => 'bridgistic.scheduled_playbooks.unlimited',
		'snapshots_advanced'            => 'bridgistic.snapshots.advanced',
		'audit_export'                  => 'bridgistic.audit.export',
		'skills_marketplace'            => 'bridgistic.skills.marketplace',
		'agency_dashboard'              => 'bridgistic.agency.dashboard',
		'team_permissions'              => 'bridgistic.team.permissions',
		'white_label'                   => 'bridgistic.white_label',
	);

	/** Plan-name fallback gates (used only when entitlements are absent). */
	private const PLAN_GATES = array(
		'scheduled_playbooks_unlimited' => array( 'starter', 'pro', 'agency' ),
		'scheduled_playbooks_basic'     => array( 'free', 'starter', 'pro', 'agency' ),
		'snapshots_advanced'            => array( 'pro', 'agency' ),
		'audit_export'                  => array( 'pro', 'agency' ),
		'skills_marketplace'            => array( 'pro', 'agency' ),
		'agency_dashboard'              => array( 'agency' ),
		'team_permissions'              => array( 'agency' ),
		'white_label'                   => array( 'agency' ),
	);

	/** @var WpisticClient|null Shared SDK client. */
	private static ?WpisticClient $client = null;

	/** The shared SDK client (licensing, entitlements, secure updates). */
	public static function client(): WpisticClient {
		if ( null === self::$client ) {
			self::$client = new WpisticClient(
				array(
					'product_slug'    => self::PRODUCT_SLUG,
					'product_version' => BRIDGISTIC_VERSION,
					'plugin_file'     => BRIDGISTIC_FILE,
				)
			);
		}
		return self::$client;
	}

	/**
	 * Boot the SDK: validation cron, secure update pipeline, Settings →
	 * License screen, and the "Connect to WPistic" onboarding wizard.
	 * Called from Plugin::boot().
	 */
	public static function init(): void {
		// Several WPistic plugins vendor the same shared SDK. Whichever plugin
		// loads first provides it; a second copy must not redeclare the namespace.
		if ( ! class_exists( WpisticClient::class, false ) ) {
			require_once BRIDGISTIC_DIR . 'includes/sdk/Activation.php';
			require_once BRIDGISTIC_DIR . 'includes/sdk/DomainNormalizer.php';
			require_once BRIDGISTIC_DIR . 'includes/sdk/EntitlementChecker.php';
			require_once BRIDGISTIC_DIR . 'includes/sdk/GracePeriodManager.php';
			require_once BRIDGISTIC_DIR . 'includes/sdk/Api/ApiClientInterface.php';
			require_once BRIDGISTIC_DIR . 'includes/sdk/Api/RetryHandler.php';
			require_once BRIDGISTIC_DIR . 'includes/sdk/Api/WpisticApi.php';
			require_once BRIDGISTIC_DIR . 'includes/sdk/Security/TokenStorage.php';
			require_once BRIDGISTIC_DIR . 'includes/sdk/Security/HmacVerifier.php';
			require_once BRIDGISTIC_DIR . 'includes/sdk/LicenseManager.php';
			require_once BRIDGISTIC_DIR . 'includes/sdk/UpdateClient.php';
			require_once BRIDGISTIC_DIR . 'includes/sdk/WpisticClient.php';
			require_once BRIDGISTIC_DIR . 'includes/sdk/Admin/SettingsPage.php';
			require_once BRIDGISTIC_DIR . 'includes/sdk/Admin/OnboardingWizard.php';
		}

		self::client()->boot();
	}

	// -------------------------------------------------------------------------
	// State queries
	// -------------------------------------------------------------------------

	/** Current plan id, or 'free'. */
	public static function plan(): string {
		$status = self::client()->status();
		if ( empty( $status['active'] ) ) {
			return 'free';
		}
		$plan = isset( $status['plan'] ) ? sanitize_key( (string) $status['plan'] ) : '';
		return '' !== $plan ? $plan : 'pro';
	}

	/** Whether a paid plan is currently active (grace-period aware). */
	public static function is_premium(): bool {
		return 'free' !== self::plan();
	}

	/** Whether this site ever activated a key (even if now expired). */
	public static function is_connected(): bool {
		return self::client()->is_connected();
	}

	/** Sanitized status array for admin views (SDK shape). */
	public static function get_status(): array {
		return self::client()->status();
	}

	// -------------------------------------------------------------------------
	// Feature gates
	// -------------------------------------------------------------------------

	/**
	 * Can this site use a gated feature?
	 * 1. Platform entitlement key wins when present.
	 * 2. Otherwise fall back to the plan-name matrix (for the period between
	 *    activation and the platform shipping per-feature entitlements).
	 * Unknown features default to unlocked (convenience features only —
	 * security is never gated here).
	 */
	public static function gate( string $feature ): bool {
		$entitlement = self::FEATURE_ENTITLEMENTS[ $feature ] ?? null;
		if ( null !== $entitlement && self::is_premium() ) {
			$allows = self::client()->entitlements()->allows( $entitlement );
			if ( $allows ) {
				return true;
			}
			// Entitlement map exists but this key is absent: if the platform
			// is authoritative for this license (any entitlement key present),
			// respect its decision instead of falling back to plan names.
			if ( self::entitlement_map_present() ) {
				return false;
			}
		}

		$allowed = self::PLAN_GATES[ $feature ] ?? null;
		if ( null === $allowed ) {
			return true;
		}
		return in_array( self::plan(), $allowed, true );
	}

	/** Whether the platform returned any bridgistic.* entitlement keys. */
	private static function entitlement_map_present(): bool {
		$all = self::client()->entitlements()->all();
		foreach ( array_keys( $all ) as $key ) {
			if ( 0 === strpos( (string) $key, 'bridgistic.' ) ) {
				return true;
			}
		}
		return false;
	}

	/** Human-readable upgrade hint for a locked feature. */
	public static function gate_hint( string $feature ): string {
		$needed = self::PLAN_GATES[ $feature ] ?? array();
		return sprintf(
			/* translators: 1: required plans, 2: upgrade URL */
			__( 'This feature needs a paid plan (%1$s). Upgrade at %2$s — activate the key under Bridgistic → License.', 'bridgistic' ),
			implode( ', ', $needed ),
			'https://wpistic.com/pricing'
		);
	}

	// -------------------------------------------------------------------------
	// Lifecycle (admin-post handlers call these)
	// -------------------------------------------------------------------------

	/**
	 * Activate a license key. Returns [ ok => bool, message => string ].
	 */
	public static function activate( string $key ): array {
		$result = self::client()->activate( $key );
		if ( true === $result ) {
			return array( 'ok' => true );
		}
		return array(
			'ok'      => false,
			'message' => is_wp_error( $result ) ? $result->get_error_message() : __( 'Activation failed.', 'bridgistic' ),
		);
	}

	/** Deactivate on this site (frees the seat server-side). */
	public static function deactivate(): array {
		$result = self::client()->deactivate();
		return array( 'ok' => true, 'error' => is_wp_error( $result ) ? $result->get_error_message() : '' );
	}

	/** Re-validate now ("Check again" button). */
	public static function validate_now(): array {
		$result = self::client()->validate_now();
		if ( true === $result ) {
			return array( 'ok' => true );
		}
		return array(
			'ok'      => false,
			'message' => is_wp_error( $result ) ? $result->get_error_message() : __( 'Validation failed.', 'bridgistic' ),
		);
	}
}
