<?php
/**
 * Filesystem containment and credential-file protection.
 *
 * fs:read and fs:write are confined to ABSPATH, and executable PHP is confined
 * further to one sandbox directory. Both confinements are a single containment
 * predicate, so that predicate is what gets tested here — directly, via
 * reflection, rather than through a controller method that would need the
 * whole REST and Guard stack standing up around it.
 *
 * The specific bug these tests pin down: containment used to be
 * `strpos($real, $base) === 0`, which accepts `/var/www/html-backup` for a
 * base of `/var/www/html`. A sibling directory is not inside the install.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

use Bridgistic\Rest\FsController;

bridgistic_suite( 'filesystem' );

// FsController pulls in the Guard/Snapshot stack at call time, but the
// containment helpers are pure and static, so the class loads fine on its own.
require_once BRIDGISTIC_DIR . 'includes/rest/class-controller.php';
require_once BRIDGISTIC_DIR . 'includes/rest/class-fs-controller.php';

$contains = new ReflectionMethod( FsController::class, 'contains' );
$contains->setAccessible( true );

$protected = new ReflectionMethod( FsController::class, 'is_protected_credential_file' );
$protected->setAccessible( true );

$is_php = new ReflectionMethod( FsController::class, 'is_php' );
$is_php->setAccessible( true );

$is_exec = new ReflectionMethod( FsController::class, 'is_exec_control' );
$is_exec->setAccessible( true );

$controller = new FsController();

// ---- 1. Containment accepts what is genuinely inside ------------------------

$inside = array(
	'/var/www/html/wp-content/themes/x/style.css',
	'/var/www/html/index.php',
	'/var/www/html/a',
	'/var/www/html/', // the base itself, trailing slash
	'/var/www/html',  // the base itself
);
foreach ( $inside as $path ) {
	check( $contains->invoke( null, '/var/www/html', $path ), "contained: {$path}" );
}

// ---- 2. Containment refuses siblings sharing a prefix -----------------------

// Each of these passes a naive `strpos(...) === 0` check while living
// entirely outside the WordPress install.
$outside = array(
	'/var/www/html-backup/wp-config.php',
	'/var/www/htmlx/secrets.txt',
	'/var/www/html2/index.php',
	'/var/www/html.old/wp-config.php',
	'/var/www/htm',
	'/var/www',
	'/etc/passwd',
	'/var/www/htmlsomething',
);
foreach ( $outside as $path ) {
	check( ! $contains->invoke( null, '/var/www/html', $path ), "not contained: {$path}" );
}

// The same property must hold for the sandbox, whose directory name is a
// prefix of plausible siblings.
$sandbox = '/var/www/html/wp-content/uploads/bridgistic-sandbox';
check( $contains->invoke( null, $sandbox, $sandbox . '/tool.php' ), 'a file inside the sandbox is contained' );
check( ! $contains->invoke( null, $sandbox, $sandbox . '-evil/tool.php' ), 'a sibling directory sharing the sandbox name prefix is not the sandbox' );
check( ! $contains->invoke( null, $sandbox, '/var/www/html/wp-content/plugins/tool.php' ), 'a plugin directory is not the sandbox' );

// ---- 3. Executable-file detection ------------------------------------------

foreach ( array( 'x.php', 'x.PHP', 'x.php7', 'x.phtml', 'x.phps', 'x.phar', '/a/b/c.php' ) as $path ) {
	check( $is_php->invoke( $controller, $path ), "treated as executable PHP: {$path}" );
}
foreach ( array( 'x.txt', 'x.css', 'x.phpx', 'x.js', 'style.php.css' ) as $path ) {
	check( ! $is_php->invoke( $controller, $path ), "not treated as executable PHP: {$path}" );
}

// Files that can turn inert files into executable PHP are as dangerous as PHP
// itself — writing one anywhere in the docroot is a sandbox escape.
foreach ( array( '.htaccess', '.htpasswd', '.user.ini', 'php.ini', 'web.config', '.htfoo', '/a/b/.htaccess' ) as $path ) {
	check( $is_exec->invoke( $controller, $path ), "treated as an execution-control file: {$path}" );
}
foreach ( array( 'readme.txt', 'access.log', 'htaccess.bak' ) as $path ) {
	check( ! $is_exec->invoke( $controller, $path ), "not treated as an execution-control file: {$path}" );
}

// ---- 4. Credential files are off limits ------------------------------------

// wp-config.php carries AUTH_KEY and SECURE_AUTH_KEY, two of the three inputs
// to Security\Crypto's key derivation. Reading it plus a db:read of the key
// table is enough to decrypt every stored key secret, which turns a
// read-oriented scope into full credential disclosure.
$credential_files = array(
	'/var/www/html/wp-config.php',
	'/var/www/html/WP-CONFIG.PHP',
	'/var/www/html/wp-config-sample.php',
	'/var/www/html/.env',
	'/var/www/html/.env.production',
	'/var/www/html/.htpasswd',
	'/home/user/.my.cnf',
	'/home/user/.netrc',
	'/home/user/.npmrc',
	'/home/user/.pgpass',
	'/home/user/.ssh/id_rsa',
	'/home/user/.ssh/id_ed25519',
);
foreach ( $credential_files as $path ) {
	check( $protected->invoke( $controller, $path ), "protected credential file: {$path}" );
}

$ordinary_files = array(
	'/var/www/html/wp-content/themes/x/functions.php',
	'/var/www/html/readme.html',
	'/var/www/html/wp-content/uploads/photo.jpg',
	'/var/www/html/environment.md',
	'/var/www/html/wp-config-notes.txt',
);
foreach ( $ordinary_files as $path ) {
	check( ! $protected->invoke( $controller, $path ), "ordinary file, not treated as a credential: {$path}" );
}

// ---- 5. Traversal collapses through realpath -------------------------------

// realpath() resolves `..` and symlinks, which is why confine() can reason
// about containment as a plain prefix question afterwards. This pins the
// assumption that makes that safe: a resolved traversal must land outside and
// be refused, not be silently rewritten to something inside.
$base = sys_get_temp_dir() . '/bridgistic-fs-test';
@mkdir( $base . '/inner', 0777, true );
$base = (string) realpath( $base ); // Match the runtime contract on Windows too.
$resolved_escape = realpath( $base . '/inner/../../' );
check( is_string( $resolved_escape ), 'the traversal target resolves' );
check( ! $contains->invoke( null, $base, (string) $resolved_escape ), 'a resolved `..` traversal lands outside the base and is refused' );
check( $contains->invoke( null, $base, (string) realpath( $base . '/inner' ) ), 'a resolved path that stays inside is accepted' );
@rmdir( $base . '/inner' );
@rmdir( $base );
