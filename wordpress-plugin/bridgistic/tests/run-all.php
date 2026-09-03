<?php
/**
 * Runs every Bridgistic PHP suite in one process and reports the total.
 *
 * Suites share one bootstrap (and therefore one in-memory database and one
 * transient store), so they must not depend on execution order — each mints
 * the keys it needs rather than reusing another suite's.
 *
 * Run: php tests/run-all.php
 * Exit code is non-zero if any check failed, so CI can gate on it.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

require_once __DIR__ . '/bootstrap.php';

$suites = glob( __DIR__ . '/suite-*.php' );
sort( $suites );

if ( ! $suites ) {
	fwrite( STDERR, "No suites found in tests/.\n" );
	exit( 1 );
}

$started = microtime( true );

foreach ( $suites as $suite ) {
	$before = $GLOBALS['__checks'];
	require_once $suite;
	$ran = $GLOBALS['__checks'] - $before;
	printf( "  %-22s %4d checks\n", basename( $suite, '.php' ), $ran );
}

$elapsed = round( ( microtime( true ) - $started ) * 1000 );
$failed  = count( $GLOBALS['__failures'] );

echo "\n";

if ( $failed > 0 ) {
	fwrite( STDERR, "FAIL  php — {$failed} of {$GLOBALS['__checks']} checks failed:\n" );
	foreach ( $GLOBALS['__failures'] as $failure ) {
		fwrite( STDERR, "  - {$failure}\n" );
	}
	exit( 1 );
}

printf( "PASS  php — %d checks across %d suites (%dms)\n", $GLOBALS['__checks'], count( $suites ), $elapsed );
exit( 0 );
