<?php
/**
 * Free-edition feature policy.
 *
 * The public download stays free until a site activates a WPistic license.
 * License::gate() then opens unlimited schedules, advanced snapshots, audit
 * export, and the named agency lines. White-label stays closed. There is no
 * Cloud Agent credit gate in this plugin.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class License {

    public const PRODUCT_SLUG = 'bridgistic';
    public const FREE_ONLY    = true;

    /** Features reserved for the private WPistic/SaaS layer. */
    private const PAID_FEATURES = array(
        'scheduled_playbooks_unlimited',
        'snapshots_advanced',
        'audit_export',
        'skills_marketplace',
        'agency_dashboard',
        'team_permissions',
        'white_label',
    );

    /**
     * Kept as a lifecycle hook so the bootstrap contract remains stable. The
     * free build never contacts a licensing or billing service.
     */
    public static function init(): void {}

    /** The public package always runs as the free edition. */
    public static function plan(): string {
        return 'free';
    }

    /** Paid features cannot be active in the public package. */
    public static function is_premium(): bool {
        return false;
    }

    /** The free package has no account or license connection state. */
    public static function is_connected(): bool {
        return false;
    }

    /**
     * Stable, non-sensitive status shape for any remaining integrations.
     *
     * @return array<string,mixed>
     */
    public static function get_status(): array {
        return array(
            'connected'            => false,
            'active'               => false,
            'plan'                 => 'free',
            'status'               => 'free_only',
            'key_mask'             => '',
            'expires_at'           => '',
            'grace_left'           => 0,
            'grace_period_ends_at' => '',
            'entitlements'         => array(),
        );
    }

    /**
     * Paid lines open only when the WPistic license link has a live activation.
     * White-label stays closed. Cloud Agent credits are not a plugin gate.
     * With no activation, the public package behaves as the free edition.
     */
    public static function gate( string $feature ): bool {
        if ( 'white_label' === $feature ) {
            return false;
        }
        if ( ! in_array( $feature, self::PAID_FEATURES, true ) ) {
            return true;
        }
        return self::has_active_license();
    }

    /** True only for a verified live license. Missing WordPress or no activation stays false. */
    public static function has_active_license(): bool {
        if ( ! function_exists( 'get_option' ) || ! class_exists( LicenseLink::class ) ) {
            return false;
        }
        $status = strtolower( (string) ( LicenseLink::status()['status'] ?? '' ) );
        return in_array( $status, array( 'active', 'trialing', 'grace' ), true );
    }

    /** Human-readable explanation for a feature that belongs to SaaS. */
    public static function gate_hint( string $feature ): string {
        unset( $feature );
        return __( 'This feature is part of Bridgistic SaaS and is not included in the free local plugin.', 'bridgistic' );
    }

    /** Paid license activation is intentionally unavailable in the free build. */
    public static function activate( string $key ): array {
        unset( $key );
        return array(
            'ok'      => false,
            'message' => __( 'Paid licensing is not included in the free Bridgistic plugin.', 'bridgistic' ),
        );
    }

    /** No remote seat or local license state exists to deactivate. */
    public static function deactivate(): array {
        return array(
            'ok'      => false,
            'message' => __( 'The free Bridgistic plugin has no license connection to deactivate.', 'bridgistic' ),
        );
    }

    /** No remote validation request is made by the free build. */
    public static function validate_now(): array {
        return array(
            'ok'      => false,
            'message' => __( 'The free Bridgistic plugin does not contact a licensing service.', 'bridgistic' ),
        );
    }
}
