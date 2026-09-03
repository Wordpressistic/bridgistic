<?php
/**
 * Shared test harness for the Bridgistic PHP suites.
 *
 * WHAT THIS IS, AND WHAT IT IS NOT.
 *
 * This boots the plugin's real classes against a stub of the WordPress
 * surface they touch, with an SQLite-backed $wpdb so storage behaviour is
 * genuinely exercised rather than asserted against a mock that always agrees.
 * That means KeyStore really writes and reads rows, key rotation really
 * invalidates the previous secret, and the nonce store really rejects a
 * second use.
 *
 * It is NOT a WordPress integration harness. There is no WordPress core, no
 * hook system, and no HTTP stack, so anything whose behaviour depends on core
 * (rewrite rules, capability mapping, the REST dispatcher itself) is out of
 * scope here and is covered — or explicitly not covered — in the release test
 * report instead. The point of this file is that the security-critical logic
 * gets real behavioural tests in CI on every push, which a `php -l` pass does
 * not provide.
 *
 * Run everything: php tests/run-all.php
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

// ---- constants ------------------------------------------------------------

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
define( 'BRIDGISTIC_TESTS', true );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'OBJECT', 'OBJECT' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MB_IN_BYTES', 1048576 );
define( 'AUTH_KEY', 'unit-test-auth-key-000000000000000000' );
define( 'SECURE_AUTH_KEY', 'unit-test-secure-auth-key-00000000000' );
define( 'BRIDGISTIC_VERSION', '1.2.0' );
define( 'BRIDGISTIC_DIR', dirname( __DIR__ ) . '/' );
define( 'BRIDGISTIC_URL', 'https://example.test/wp-content/plugins/bridgistic/' );
define( 'BRIDGISTIC_REST_NAMESPACE', 'bridgistic/v1' );
define( 'BRIDGISTIC_SANDBOX', 'bridgistic-sandbox' );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$GLOBALS['__options']    = array( 'bridgistic_bridge_pepper' => 'unit-test-pepper-value' );
$GLOBALS['__transients'] = array();
$GLOBALS['__audit']      = array();

// ---- assertion runner -----------------------------------------------------

$GLOBALS['__checks']   = 0;
$GLOBALS['__failures'] = array();
$GLOBALS['__suite']    = '';

function bridgistic_suite( string $name ): void {
	$GLOBALS['__suite'] = $name;
}

/**
 * @param mixed $condition Truthy assertion.
 */
function check( $condition, string $label ): void {
	$GLOBALS['__checks']++;
	if ( ! $condition ) {
		$GLOBALS['__failures'][] = $GLOBALS['__suite'] . ' :: ' . $label;
		fwrite( STDERR, "  FAIL  [{$GLOBALS['__suite']}] {$label}\n" );
	}
}

/**
 * @param mixed $expected Expected value.
 * @param mixed $actual   Actual value.
 */
function check_equals( $expected, $actual, string $label ): void {
	$ok = $expected === $actual;
	if ( ! $ok ) {
		$label .= sprintf( ' (expected %s, got %s)', var_export( $expected, true ), var_export( $actual, true ) );
	}
	check( $ok, $label );
}

/** Assert that $callable throws, and optionally that the message matches. */
function check_throws( callable $callable, string $label, string $pattern = '' ): void {
	try {
		$callable();
	} catch ( \Throwable $e ) {
		check( '' === $pattern || 1 === preg_match( $pattern, $e->getMessage() ), $label . ( '' === $pattern ? '' : ' (message: ' . $e->getMessage() . ')' ) );
		return;
	}
	check( false, $label . ' (nothing was thrown)' );
}

// ---- WordPress stubs ------------------------------------------------------

class WP_Error {
	public string $code;
	public string $message;
	/** @var array<string,mixed> */
	public $data;

	/**
	 * @param array<string,mixed> $data Error data.
	 */
	public function __construct( string $code = '', string $message = '', $data = array() ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	public function get_error_code(): string {
		return $this->code;
	}

	public function get_error_message(): string {
		return $this->message;
	}

	public function get_error_data() {
		return $this->data;
	}

	public function get_status(): int {
		return (int) ( $this->data['status'] ?? 0 );
	}
}

class WP_REST_Response {
	/** @var mixed */
	public $data;
	public int $status;

	/**
	 * @param mixed $data Response payload.
	 */
	public function __construct( $data = null, int $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}

	public function get_data() {
		return $this->data;
	}

	public function get_status(): int {
		return $this->status;
	}
}

/**
 * Enough of WP_REST_Request to drive a controller method: params, method,
 * route, body, and headers. ArrayAccess is what `$request['id']` uses.
 */
class WP_REST_Request implements ArrayAccess {
	/** @var array<string,mixed> */
	private array $params = array();
	/** @var array<string,array<int,string>> */
	private array $headers = array();
	private string $method;
	private string $route;
	private string $body;

	/**
	 * @param array<string,mixed> $params Request params.
	 */
	public function __construct( string $method = 'GET', string $route = '/bridgistic/v1/test', array $params = array(), string $body = '' ) {
		$this->method = $method;
		$this->route  = $route;
		$this->params = $params;
		$this->body   = $body;
	}

	/** @return mixed */
	public function get_param( string $key ) {
		return $this->params[ $key ] ?? null;
	}

	/** @param mixed $value Param value. */
	public function set_param( string $key, $value ): void {
		$this->params[ $key ] = $value;
	}

	/** @return array<string,mixed> */
	public function get_params(): array {
		return $this->params;
	}

	public function get_method(): string {
		return $this->method;
	}

	public function get_route(): string {
		return $this->route;
	}

	public function get_body(): string {
		return $this->body;
	}

	/** @return array<string,array<int,string>> */
	public function get_headers(): array {
		return $this->headers;
	}

	/** @param array<string,string> $headers Header map. */
	public function set_headers( array $headers ): void {
		foreach ( $headers as $name => $value ) {
			$this->headers[ $name ] = array( $value );
		}
	}

	#[\ReturnTypeWillChange]
	public function offsetExists( $offset ): bool {
		return isset( $this->params[ $offset ] );
	}

	#[\ReturnTypeWillChange]
	public function offsetGet( $offset ) {
		return $this->params[ $offset ] ?? null;
	}

	#[\ReturnTypeWillChange]
	public function offsetSet( $offset, $value ): void {
		$this->params[ $offset ] = $value;
	}

	#[\ReturnTypeWillChange]
	public function offsetUnset( $offset ): void {
		unset( $this->params[ $offset ] );
	}
}

/** @param mixed $thing Value to test. */
function is_wp_error( $thing ): bool {
	return $thing instanceof WP_Error;
}

/** @param mixed $default Default when absent. @return mixed */
function get_option( string $name, $default = false ) {
	return $GLOBALS['__options'][ $name ] ?? $default;
}

/** @param mixed $value Option value. */
function add_option( string $name, $value, string $deprecated = '', string $autoload = 'yes' ): bool {
	$GLOBALS['__options'][ $name ] = $value;
	return true;
}

/** @param mixed $value Option value. */
function update_option( string $name, $value, $autoload = null ): bool {
	$GLOBALS['__options'][ $name ] = $value;
	return true;
}

/**
 * Transients carry an expiry so tests can prove that an expired
 * authorization code is refused — the OAuth suite depends on it.
 *
 * @return mixed
 */
function get_transient( string $key ) {
	$entry = $GLOBALS['__transients'][ $key ] ?? null;
	if ( null === $entry ) {
		return false;
	}
	if ( 0 !== $entry['expires'] && $entry['expires'] < time() ) {
		unset( $GLOBALS['__transients'][ $key ] );
		return false;
	}
	return $entry['value'];
}

/** @param mixed $value Transient value. */
function set_transient( string $key, $value, int $expiration = 0 ): bool {
	$GLOBALS['__transients'][ $key ] = array(
		'value'   => $value,
		'expires' => $expiration > 0 ? time() + $expiration : 0,
	);
	return true;
}

function delete_transient( string $key ): bool {
	unset( $GLOBALS['__transients'][ $key ] );
	return true;
}

/** Force a stored transient to look older than it is, without sleeping. */
function bridgistic_test_age_transient( string $key, int $seconds ): void {
	if ( isset( $GLOBALS['__transients'][ $key ] ) && 0 !== $GLOBALS['__transients'][ $key ]['expires'] ) {
		$GLOBALS['__transients'][ $key ]['expires'] -= $seconds;
	}
}

/** @param mixed $data Value to encode. */
function wp_json_encode( $data, int $flags = 0 ): string {
	return (string) json_encode( $data, $flags );
}

/** @param mixed $str Value. @return mixed */
function wp_unslash( $str ) {
	return $str;
}

function sanitize_text_field( string $str ): string {
	return trim( strip_tags( $str ) );
}

function sanitize_textarea_field( string $str ): string {
	return trim( strip_tags( $str ) );
}

function sanitize_key( string $key ): string {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
}

function sanitize_title( string $title ): string {
	return preg_replace( '/[^a-z0-9\-]/', '', strtolower( str_replace( ' ', '-', trim( $title ) ) ) );
}

function wp_kses_post( string $content ): string {
	return $content;
}

function esc_url_raw( string $url ): string {
	return trim( $url );
}

function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function __( string $text, string $domain = '' ): string {
	return $text;
}

function esc_html__( string $text, string $domain = '' ): string {
	return $text;
}

function home_url( string $path = '' ): string {
	return 'https://example.test' . $path;
}

function admin_url( string $path = '' ): string {
	return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}

function rest_url( string $path = '' ): string {
	return 'https://example.test/wp-json/' . ltrim( $path, '/' );
}

function is_ssl(): bool {
	return true;
}

function current_time( string $type, bool $gmt = false ): string {
	return gmdate( 'Y-m-d H:i:s' );
}

function wp_generate_password( int $length = 12, bool $special = true, bool $extra = false ): string {
	return substr( bin2hex( random_bytes( (int) ceil( $length / 2 ) + 1 ) ), 0, $length );
}

function trailingslashit( string $value ): string {
	return rtrim( $value, '/\\' ) . '/';
}

function wp_parse_url( string $url, int $component = -1 ) {
	return parse_url( $url, $component );
}

function wp_upload_dir(): array {
	$base = sys_get_temp_dir() . '/bridgistic-tests-uploads';
	if ( ! is_dir( $base ) ) {
		mkdir( $base, 0777, true );
	}
	return array( 'basedir' => $base, 'baseurl' => 'https://example.test/uploads' );
}

function wp_mkdir_p( string $dir ): bool {
	return is_dir( $dir ) || mkdir( $dir, 0777, true );
}

function wp_is_writable( string $path ): bool {
	return is_writable( $path );
}

function get_temp_dir(): string {
	return trailingslashit( sys_get_temp_dir() );
}

function wp_using_ext_object_cache(): bool {
	return false;
}

function wp_next_scheduled( string $hook ) {
	return false;
}

function human_time_diff( int $from, int $to = 0 ): string {
	return 'a moment';
}

function get_woocommerce_currency(): string {
	return 'USD';
}

// ---- SQLite-backed $wpdb --------------------------------------------------

/**
 * A $wpdb that actually stores rows.
 *
 * The previous harness returned canned values from update()/get_row(), which
 * meant a test could not tell a working key rotation from a no-op — the very
 * thing rotation tests exist to catch. This translates the handful of MySQL-isms
 * the plugin's storage layer uses into SQLite and runs real statements.
 */
class TestWpdb {
	public string $prefix = 'wp_';
	public int $insert_id = 0;
	public string $last_error = '';
	private PDO $pdo;

	public function __construct() {
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		// dbDelta is not available here, so the schema the tests need is
		// created directly, mirroring KeyStore::install()'s columns.
		$this->pdo->exec(
			'CREATE TABLE wp_bridgistic_keys (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				key_id TEXT NOT NULL UNIQUE,
				secret_enc TEXT NOT NULL,
				label TEXT NOT NULL DEFAULT "",
				scopes TEXT NOT NULL,
				ip_allowlist TEXT NULL,
				rate_limit INTEGER NOT NULL DEFAULT 120,
				require_approval INTEGER NOT NULL DEFAULT 0,
				tier TEXT NOT NULL DEFAULT "custom",
				monthly_quota INTEGER NOT NULL DEFAULT 0,
				enabled INTEGER NOT NULL DEFAULT 1,
				created_at TEXT NOT NULL,
				last_used_at TEXT NULL
			)'
		);
	}

	public function get_charset_collate(): string {
		return '';
	}

	/**
	 * @param array<string,mixed> $data    Row data.
	 * @param array<int,string>|null $formats Ignored; SQLite is dynamically typed.
	 */
	public function insert( string $table, array $data, $formats = null ) {
		$columns      = array_keys( $data );
		$placeholders = implode( ', ', array_fill( 0, count( $columns ), '?' ) );
		$sql          = sprintf( 'INSERT INTO %s (%s) VALUES (%s)', $table, implode( ', ', $columns ), $placeholders );

		try {
			$stmt = $this->pdo->prepare( $sql );
			$stmt->execute( array_values( $data ) );
			$this->insert_id = (int) $this->pdo->lastInsertId();
			return 1;
		} catch ( PDOException $e ) {
			$this->last_error = $e->getMessage();
			return false;
		}
	}

	/**
	 * @param array<string,mixed> $data  Columns to set.
	 * @param array<string,mixed> $where Conditions.
	 */
	public function update( string $table, array $data, array $where, $f = null, $wf = null ) {
		$set    = implode( ', ', array_map( static fn( $c ) => "{$c} = ?", array_keys( $data ) ) );
		$clause = implode( ' AND ', array_map( static fn( $c ) => "{$c} = ?", array_keys( $where ) ) );

		try {
			$stmt = $this->pdo->prepare( "UPDATE {$table} SET {$set} WHERE {$clause}" );
			$stmt->execute( array_merge( array_values( $data ), array_values( $where ) ) );
			return $stmt->rowCount();
		} catch ( PDOException $e ) {
			$this->last_error = $e->getMessage();
			return false;
		}
	}

	/** @param array<string,mixed> $where Conditions. */
	public function delete( string $table, array $where, $wf = null ) {
		$clause = implode( ' AND ', array_map( static fn( $c ) => "{$c} = ?", array_keys( $where ) ) );
		$stmt   = $this->pdo->prepare( "DELETE FROM {$table} WHERE {$clause}" );
		$stmt->execute( array_values( $where ) );
		return $stmt->rowCount();
	}

	/** @param mixed ...$args Bound values. */
	public function prepare( string $query, ...$args ): string {
		if ( isset( $args[0] ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		foreach ( $args as $value ) {
			$replacement = is_int( $value ) ? (string) $value : $this->pdo->quote( (string) $value );
			$query       = preg_replace( '/%[sdf]/', str_replace( '$', '\\$', $replacement ), $query, 1 );
		}
		return (string) $query;
	}

	/** @return array<string,mixed>|null */
	public function get_row( string $query, $output = null ): ?array {
		$row = $this->pdo->query( $query )->fetch( PDO::FETCH_ASSOC );
		return false === $row ? null : $row;
	}

	/** @return array<int,array<string,mixed>> */
	public function get_results( string $query, $output = null ): array {
		return $this->pdo->query( $query )->fetchAll( PDO::FETCH_ASSOC );
	}

	/** @return mixed */
	public function get_var( string $query ) {
		$row = $this->pdo->query( $query )->fetch( PDO::FETCH_NUM );
		return false === $row ? null : $row[0];
	}

	public function query( string $sql ) {
		return $this->pdo->exec( $sql );
	}
}

$GLOBALS['wpdb'] = new TestWpdb();

// ---- audit log stub -------------------------------------------------------

require_once __DIR__ . '/stubs-bridgistic.php';



// ---- load the real classes under test ---------------------------------

require_once BRIDGISTIC_DIR . 'includes/security/class-crypto.php';
require_once BRIDGISTIC_DIR . 'includes/security/class-scopes.php';
require_once BRIDGISTIC_DIR . 'includes/security/class-key-store.php';
require_once BRIDGISTIC_DIR . 'includes/security/class-hmac-verifier.php';
require_once BRIDGISTIC_DIR . 'includes/security/class-sql-classifier.php';
require_once BRIDGISTIC_DIR . 'admin/class-bridgistic-presets.php';
require_once BRIDGISTIC_DIR . 'admin/class-bridgistic-config-generator.php';
require_once BRIDGISTIC_DIR . 'includes/class-oauth.php';

/**
 * Sign a request the way the MCP server's signer does.
 *
 * Shared by the HMAC and scope suites so both prove the same canonical
 * form the TypeScript signer produces, rather than each inventing one.
 *
 * @return array<string,string>
 */
function bridgistic_sign( string $method, string $path, string $body, string $key_id, string $secret, ?string $nonce = null, ?int $timestamp = null ): array {
	$ts        = (string) ( $timestamp ?? time() );
	$nonce     = $nonce ?? bin2hex( random_bytes( 16 ) );
	$canonical = implode( "\n", array( strtoupper( $method ), $path, $ts, $nonce, hash( 'sha256', $body ) ) );

	return array(
		'x-bridgistic-key'       => $key_id,
		'x-bridgistic-timestamp' => $ts,
		'x-bridgistic-nonce'     => $nonce,
		'x-bridgistic-signature' => hash_hmac( 'sha256', $canonical, $secret ),
	);
}

/** Derive an S256 challenge from a verifier, as an OAuth client would. */
function bridgistic_pkce_challenge( string $verifier ): string {
	return rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
}
