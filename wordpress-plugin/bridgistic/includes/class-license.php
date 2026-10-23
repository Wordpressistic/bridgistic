<?php
/**
 * Free-edition feature policy.
 *
 * The public download stays free until a site activates a WPistic license.
 * License::gate() then opens unlimited schedules, advanced snapshots and
 * audit export. The skills marketplace, agency/team lines and white-label
 * stay closed. There is no Cloud Agent credit gate in this plugin.
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

    /** Features opened by a verified live WPistic license on this site. */
    private const LICENSE_FEATURES = array(
        'scheduled_playbooks_unlimited',
        'snapshots_advanced',
        'audit_export',
    );

    /** Product lines that remain closed until their own product ships. */
    private const CLOSED_FEATURES = array(
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
     * Stable, non-sensitive status shape for any remaining integrations,
     * fed by the live license link when one is connected.
     *
     * @return array<string,mixed>
     */
    public static function get_status(): array {
        $link = LicenseLink::status();
        $on   = ! empty( $link['connected'] );
        return array(
            'connected'            => $on,
            'active'               => self::has_active_license(),
            'plan'                 => $on ? (string) ( $link['plan'] ?? '' ) : 'free',
            'status'               => $on ? (string) ( $link['status'] ?? 'unknown' ) : 'free_only',
            'key_mask'             => (string) ( $link['key_mask'] ?? '' ),
            'expires_at'           => (string) ( $link['expires_at'] ?? '' ),
            'grace_left'           => 0,
            'grace_period_ends_at' => '',
            'entitlements'         => (array) ( $link['entitlements'] ?? array() ),
        );
    }

    /**
     * The three shipped paid lines open only when the WPistic license link has
     * a live activation. Product lines that are not shipped stay closed.
     * Cloud Agent credits are not a plugin gate.
     * With no activation, the public package behaves as the free edition.
     */
    public static function gate( string $feature ): bool {
        if ( in_array( $feature, self::CLOSED_FEATURES, true ) ) {
            return false;
        }
        if ( ! in_array( $feature, self::LICENSE_FEATURES, true ) ) {
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

	/** Paid license activation runs through the WPistic license link. */
	public static function activate( string $key ): array {
		$r = LicenseLink::activate( $key );
		if ( is_wp_error( $r ) ) {
			return array(
				'ok'      => false,
				'message' => $r->get_error_message(),
			);
		}
		return array(
			'ok'      => true,
			'message' => __( 'License activated on this site.', 'bridgistic' ),
			'status'  => $r,
		);
	}

	/** Deactivation clears local state and releases the seat at the server. */
	public static function deactivate(): array {
		LicenseLink::deactivate();
		return array(
			'ok'      => true,
			'message' => __( 'License deactivated on this site.', 'bridgistic' ),
		);
	}

	/** Validation replays the signed status from the license server. */
	public static function validate_now(): array {
		$r = LicenseLink::validate();
		if ( is_wp_error( $r ) ) {
			return array(
				'ok'      => false,
				'message' => $r->get_error_message(),
			);
		}
		return array(
			'ok'      => true,
			'message' => __( 'License status confirmed with the license server.', 'bridgistic' ),
			'status'  => $r,
		);
	}
}
