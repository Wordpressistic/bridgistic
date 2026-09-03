<?php
/**
 * Stubs that live inside the plugin's own namespace.
 *
 * Kept in a separate file because PHP forbids mixing bracketed and
 * unbracketed namespace declarations in one script, and bootstrap.php is
 * global-namespace throughout.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic;

/**
 * Records instead of writing.
 *
 * Suites assert on these entries, because "the denial was audited" is part of
 * the contract rather than an implementation detail: a scope refusal that
 * leaves no trace is indistinguishable from one that never happened when
 * someone is reconstructing an incident.
 */
class AuditLog {

	/**
	 * @param array<string,mixed> $meta Structured metadata.
	 */
	public static function record( string $key_id, string $action, ?string $status = 'ok', array $meta = array(), string $summary = '' ): void {
		$GLOBALS['__audit'][] = compact( 'key_id', 'action', 'status', 'meta', 'summary' );
	}

	public static function prune(): void {}
}

/** @return array<int,array<string,mixed>> */
function test_audit_entries(): array {
	return $GLOBALS['__audit'];
}

function test_audit_reset(): void {
	$GLOBALS['__audit'] = array();
}
