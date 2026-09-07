<?php
/** Regression checks for file snapshot/restore containment. */
declare( strict_types=1 );
bridgistic_suite( 'snapshot-path' );
require_once BRIDGISTIC_DIR . 'includes/class-snapshot.php';
$safe = new ReflectionMethod( \Bridgistic\Snapshot::class, 'safe_path' );
$safe->setAccessible( true );
$root = (string) realpath( ABSPATH );
$inside = $root . DIRECTORY_SEPARATOR . 'tests';
check( $safe->invoke( null, __FILE__ ) === realpath( __FILE__ ), 'existing inside file accepted' );
check( $safe->invoke( null, $inside . '/snapshot-never-created.txt' ) !== '', 'new file with existing inside parent accepted' );
check( $safe->invoke( null, $root . '/../snapshot-never-created.txt' ) === '', 'new file traversal rejected' );
check( $safe->invoke( null, dirname( $root ) . '/snapshot-never-created.txt' ) === '', 'new file outside install rejected' );
check( $safe->invoke( null, $root . '-backup/snapshot-never-created.txt' ) === '', 'prefix sibling rejected' );
check( $safe->invoke( null, $inside . '/missing-dir/snapshot-never-created.txt' ) === '', 'unresolved parent fails closed' );
check( $safe->invoke( null, '' ) === '', 'empty path rejected' );
check( $safe->invoke( null, "bad\0path" ) === '', 'NUL path rejected' );
