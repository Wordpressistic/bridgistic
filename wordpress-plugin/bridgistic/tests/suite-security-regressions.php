<?php
/**
 * Regression tests for the October 2026 audit findings (S1–S5).
 *
 * S1  write-SQL dry run executed the statement (DDL auto-commits) before the
 *     approval gate.
 * S2  MySQL executable comments (/*! … *\/) were stripped as plain comments,
 *     hiding LOAD_FILE / INTO OUTFILE / mutations from the classifier.
 * S3  fs:write could overwrite the sandbox's own .htaccess and re-open it.
 * S4  php:execute bypassed the Guard (no approval, no dry run).
 * S5  query-string params are not covered by the HMAC signature.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

use Bridgistic\Approvals;
use Bridgistic\Plugin;
use Bridgistic\Rest\Controller;
use Bridgistic\Rest\ExecuteController;
use Bridgistic\Rest\FsController;
use Bridgistic\Security\KeyStore;
use Bridgistic\Security\SqlClassifier;

bridgistic_suite( 'security-regressions' );

require_once __DIR__ . '/stubs-guard.php';
require_once BRIDGISTIC_DIR . 'includes/class-snapshot.php';
require_once BRIDGISTIC_DIR . 'includes/class-guard.php';
require_once BRIDGISTIC_DIR . 'includes/class-plugin.php';
require_once BRIDGISTIC_DIR . 'includes/rest/class-controller.php';
require_once BRIDGISTIC_DIR . 'includes/rest/class-execute-controller.php';
require_once BRIDGISTIC_DIR . 'includes/rest/class-fs-controller.php';

$GLOBALS['__approvals'] = array();

/** Build an authenticated-looking request for a key. */
function sr_request( string $route, array $params, array $key ): WP_REST_Request {
	$params['__ctx'] = array(
		'key_id' => $key['key_id'],
		'scopes' => array( 'db:read', 'db:write', 'php:execute', 'fs:read', 'fs:write' ),
	);
	return new WP_REST_Request( 'POST', '/bridgistic/v1/' . $route, $params );
}

/** @return array<string,mixed> */
function sr_data( $response ): array {
	if ( is_wp_error( $response ) ) {
		return array( '__error' => $response->get_error_code() );
	}
	$body = $response->get_data();
	return (array) ( $body['data'] ?? array() );
}

function sr_table_exists( string $name ): bool {
	return null !== $GLOBALS['wpdb']->get_var( "SELECT name FROM sqlite_master WHERE type='table' AND name='{$name}'" );
}

$plain_key    = KeyStore::create( 'sr-plain', array( 'db:read', 'db:write', 'php:execute' ) );
$approval_key = KeyStore::create( 'sr-approval', array( 'db:read', 'db:write', 'php:execute' ), array(), 120, true );
$exec         = new ExecuteController();

// ---- S1: write-SQL dry run never executes ------------------------------------

$GLOBALS['wpdb']->query( 'CREATE TABLE sr_victim (id INTEGER)' );
check( sr_table_exists( 'sr_victim' ), 'S1 setup: victim table exists' );

foreach ( array( $plain_key, $approval_key ) as $key ) {
	$res  = $exec->db_query( sr_request( 'db/query', array( 'sql' => 'DROP TABLE sr_victim', 'dry_run' => true ), $key ) );
	$data = sr_data( $res );
	check( sr_table_exists( 'sr_victim' ), 'S1 dry_run DROP TABLE does not execute (' . $key['key_id'] . ')' );
	check_equals( true, $data['dry_run'] ?? null, 'S1 dry_run returns a preview' );
	check_equals( false, $data['would']['executed'] ?? null, 'S1 dry_run preview says not executed' );
	check_equals( true, $data['requires_approval'] ?? null, 'S1 dry_run reports that raw write SQL needs approval' );
}

$GLOBALS['wpdb']->query( 'CREATE TABLE sr_victim2 (id INTEGER)' );
$res  = $exec->db_query( sr_request( 'db/query', array( 'sql' => 'DROP TABLE sr_victim2', 'dry_run' => 'true' ), $plain_key ) );
check( sr_table_exists( 'sr_victim2' ), 'S1 string "true" dry_run also does not execute' );

$res  = $exec->db_query( sr_request( 'db/query', array( 'sql' => 'DROP TABLE sr_victim' ), $approval_key ) );
$data = sr_data( $res );
check_equals( 'pending_approval', $data['status'] ?? null, 'S1 real DROP on an approval key is queued' );
check( sr_table_exists( 'sr_victim' ), 'S1 queued DROP has not executed' );

// ---- S2: executable comments are hostile -------------------------------------

$hostile = array(
	'SELECT /*! LOAD_FILE("/etc/passwd") */',
	'SELECT 1 /*! INTO OUTFILE "/var/www/html/x.php" */',
	'SELECT * FROM wp_users /*!50000 WHERE 1=1 */',
	'/*!50000 DELETE */ FROM wp_posts',
	'SELECT 1 /*M! , SLEEP(5) */',
	'select 1 /*!*/',
	'SELECT 1 /*!40101 unterminated',
);
foreach ( $hostile as $sql ) {
	check_equals( SqlClassifier::FORBIDDEN, SqlClassifier::classify( $sql )['class'], 'S2 executable comment rejected: ' . $sql );
}
check_equals( SqlClassifier::READ, SqlClassifier::classify( 'SELECT 1 /* plain comment */' )['class'], 'S2 plain block comments still allowed' );
check_equals( SqlClassifier::READ, SqlClassifier::classify( "SELECT '/*! not a comment */' AS s" )['class'], 'S2 /*! inside a string literal is data, not a comment' );
$res = $exec->db_query( sr_request( 'db/query', array( 'sql' => 'SELECT /*! LOAD_FILE("/etc/passwd") */' ), $plain_key ) );
check_equals( 'bridgistic_sql_forbidden', sr_data( $res )['__error'] ?? null, 'S2 db_query endpoint returns forbidden for an executable comment' );

// ---- S3: sandbox protective files ---------------------------------------------

$sandbox = realpath( Plugin::ensure_sandbox() );
check( false !== $sandbox && is_file( $sandbox . '/.htaccess' ), 'S3 sandbox .htaccess created' );
check( is_file( $sandbox . '/web.config' ), 'S3 sandbox web.config created (IIS)' );
check( is_file( $sandbox . '/index.php' ), 'S3 sandbox index.php created' );

file_put_contents( $sandbox . '/.htaccess', "# opened\n" );
Plugin::ensure_sandbox();
check( false !== strpos( (string) file_get_contents( $sandbox . '/.htaccess' ), 'Require all denied' ), 'S3 tampered sandbox .htaccess is repaired' );

$fs        = new FsController();
$is_exec   = new ReflectionMethod( FsController::class, 'is_exec_control' );
$is_guard  = new ReflectionMethod( FsController::class, 'is_sandbox_guard_file' );
$is_exec->setAccessible( true );
$is_guard->setAccessible( true );
foreach ( array( '.htaccess', 'web.config', '.user.ini', 'php.ini' ) as $name ) {
	check( $is_exec->invoke( $fs, $sandbox . '/' . $name ) || $is_guard->invoke( $fs, $sandbox . '/' . $name ), "S3 sandbox {$name} is a protected file" );
}
check( $is_guard->invoke( $fs, $sandbox . '/index.php' ), 'S3 sandbox index.php placeholder is protected' );
check( ! $is_guard->invoke( $fs, $sandbox . '/tool.php' ), 'S3 ordinary sandbox PHP is not a guard file' );
check( ! $is_guard->invoke( $fs, $sandbox . '/sub/index.php' ), 'S3 nested index.php is not a guard file' );

$guarded = FsController::sandbox_php_guard( "<?php echo 'hi';" );
check( 0 === strpos( $guarded, FsController::SANDBOX_PHP_GUARD ), 'S3 sandbox PHP is prefixed with the ABSPATH guard' );
check_equals( $guarded, FsController::sandbox_php_guard( $guarded ), 'S3 sandbox guard prefix is idempotent' );
// The guard must make a direct (non-WordPress) request exit before user code.
$tmp = tempnam( sys_get_temp_dir(), 'srg' ) . '.php';
file_put_contents( $tmp, FsController::sandbox_php_guard( "<?php echo 'RAN';" ) );
$out = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $tmp ) );
check_equals( '', (string) $out, 'S3 guarded sandbox PHP does nothing when requested directly' );
@unlink( $tmp );

// ---- S4: php:execute goes through the Guard ------------------------------------

$GLOBALS['__sr_ran'] = 0;
$code                = '$GLOBALS["__sr_ran"]++; return "done";';

$data = sr_data( $exec->execute_php( sr_request( 'execute', array( 'code' => $code, 'dry_run' => true ), $plain_key ) ) );
check_equals( 0, $GLOBALS['__sr_ran'], 'S4 dry_run never evaluates PHP' );
check_equals( true, $data['dry_run'] ?? null, 'S4 dry_run returns a preview' );

$data = sr_data( $exec->execute_php( sr_request( 'execute', array( 'code' => $code ), $approval_key ) ) );
check_equals( 'pending_approval', $data['status'] ?? null, 'S4 approval key queues php:execute' );
check_equals( 0, $GLOBALS['__sr_ran'], 'S4 queued PHP has not run' );
$approval_id = (string) ( $data['approval_id'] ?? '' );

$res = $exec->execute_php( sr_request( 'execute', array( 'code' => $code, 'approval_id' => $approval_id ), $approval_key ) );
check( is_wp_error( $res ), 'S4 unapproved approval_id is refused' );
check_equals( 0, $GLOBALS['__sr_ran'], 'S4 PHP still has not run before approval' );

Approvals::test_approve( $approval_id );
$res = $exec->execute_php( sr_request( 'execute', array( 'code' => 'return 1;', 'approval_id' => $approval_id ), $approval_key ) );
check( is_wp_error( $res ), 'S4 approval is bound to the exact code that was queued' );

$data = sr_data( $exec->execute_php( sr_request( 'execute', array( 'code' => $code, 'approval_id' => $approval_id ), $approval_key ) ) );
check_equals( 1, $GLOBALS['__sr_ran'], 'S4 approved PHP runs exactly once' );
check_equals( 'done', $data['result']['return'] ?? null, 'S4 approved PHP returns its value in the Guard envelope' );

$data = sr_data( $exec->execute_php( sr_request( 'execute', array( 'code' => $code ), $plain_key ) ) );
check_equals( 2, $GLOBALS['__sr_ran'], 'S4 key without require_approval still runs PHP directly' );

// ---- S5: unsigned query-string params -----------------------------------------

foreach ( Controller::SIGNED_ONLY_PARAMS as $name ) {
	check( null !== Controller::unsigned_param_violation( 'GET', array( $name => '1' ) ), "S5 GET rejects unsigned {$name} in query" );
	check( null !== Controller::unsigned_param_violation( 'POST', array( $name => '1' ) ), "S5 POST rejects unsigned {$name} in query" );
}
check( null !== Controller::unsigned_param_violation( 'POST', array( 'sql' => 'DROP TABLE x' ) ), 'S5 mutating request rejects any query param' );
check( null !== Controller::unsigned_param_violation( 'DELETE', array( 'id' => '5' ) ), 'S5 DELETE rejects query params' );
check( null === Controller::unsigned_param_violation( 'GET', array( 'per_page' => '10', 'name' => 'blogname' ) ), 'S5 GET read filters still allowed' );
check( null === Controller::unsigned_param_violation( 'POST', array( 'rest_route' => '/bridgistic/v1/db/query' ) ), 'S5 rest_route (plain permalinks) is allowed' );
check( null === Controller::unsigned_param_violation( 'POST', array() ), 'S5 POST with no query is fine' );
