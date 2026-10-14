<?php
/**
 * Approval-queue stub for suites that drive Guard::run().
 *
 * The real Approvals class needs WordPress tables that the
 * in-memory test database does not model; these keep the same contract
 * (enqueue → pending, verify_ready → payload-bound approval) in memory.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic;

class Approvals {
	public static function hash( string $action, array $payload ): string {
		return hash( 'sha256', $action . '|' . wp_json_encode( $payload ) );
	}

	public static function enqueue( string $key_id, string $action, array $payload, string $summary = '' ): string {
		$id                                 = 'apr_' . bin2hex( random_bytes( 6 ) );
		$GLOBALS['__approvals'][ $id ] = array( 'hash' => self::hash( $action, $payload ), 'status' => 'pending' );
		return $id;
	}

	/** Test helper: an admin approves. */
	public static function test_approve( string $id ): void {
		$GLOBALS['__approvals'][ $id ]['status'] = 'approved';
	}

	/** @return true|\WP_Error */
	public static function verify_ready( string $approval_id, string $action, array $payload ) {
		$rec = $GLOBALS['__approvals'][ $approval_id ] ?? null;
		if ( ! $rec || 'approved' !== $rec['status'] ) {
			return new \WP_Error( 'not_approved', 'Not approved.', array( 'status' => 403 ) );
		}
		if ( ! hash_equals( $rec['hash'], self::hash( $action, $payload ) ) ) {
			return new \WP_Error( 'mismatch', 'Approval does not match this payload.', array( 'status' => 409 ) );
		}
		return true;
	}

	public static function mark_executed( string $approval_id ): void {
		$GLOBALS['__approvals'][ $approval_id ]['status'] = 'executed';
	}
}
