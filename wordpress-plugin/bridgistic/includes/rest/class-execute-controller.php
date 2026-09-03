<?php
/**
 * High-privilege execution endpoints.
 *
 * - POST /execute    Run PHP in full WordPress context (scope php:execute).
 * - POST /db/query   Run SQL, classified read vs write (scopes db:read / db:write).
 *
 * Every call is audited. Write SQL is rejected unless db:write is granted.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Rest;

use Bridgistic\Security\Scopes;
use Bridgistic\Security\SqlClassifier;
use Bridgistic\AuditLog;
use WP_REST_Request;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ExecuteController extends Controller {

	/**
	 * Cap on captured echo output and on the serialised return value.
	 *
	 * Without this, a single `while(true) echo str_repeat('x', 1e6);` fills PHP
	 * memory before the request can return, taking the site down rather than
	 * failing the call. Truncation is reported in the response so the caller
	 * knows the output is partial rather than silently short.
	 */
	private const MAX_OUTPUT_BYTES = 256 * 1024;

	/** Wall-clock budget for a single php:execute call, in seconds. */
	private const EXECUTION_TIMEOUT = 30;

	public function register( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/execute',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'execute_php' ),
				'permission_callback' => array( $this, 'authenticate' ),
				'args'                => array(
					'code' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/db/query',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'db_query' ),
				'permission_callback' => array( $this, 'authenticate' ),
				'args'                => array(
					'sql' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);
	}

	/**
	 * Execute PHP and capture output + return value + errors.
	 */
	public function execute_php( WP_REST_Request $request ) {
		$scope = $this->require_scope( $request, Scopes::PHP_EXECUTE );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}

		$code = (string) $request->get_param( 'code' );
		if ( '' === trim( $code ) ) {
			return $this->fail( 'bridgistic_empty_code', 'No code provided.', 400 );
		}

		$captured_errors = array();
		$prev_handler    = set_error_handler( // phpcs:ignore
			static function ( $errno, $errstr, $errfile, $errline ) use ( &$captured_errors ) {
				$captured_errors[] = array(
					'type' => $errno,
					'msg'  => $errstr,
					'line' => $errline,
				);
				return true;
			}
		);

		$result = null;
		$thrown = null;

		// Best-effort wall-clock budget. set_time_limit is a no-op in some SAPIs
		// (and under an active max_execution_time of 0), so this bounds the
		// common case rather than guaranteeing termination — the output cap
		// below is the guarantee that a runaway script cannot exhaust memory.
		$previous_limit = (string) ini_get( 'max_execution_time' );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( self::EXECUTION_TIMEOUT ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		ob_start();
		$start = microtime( true );
		try {
			// Strip a leading <?php so callers can paste either form.
			$clean  = preg_replace( '/^\s*<\?php\s*/', '', $code );
			$result = eval( $clean ); // phpcs:ignore Squiz.PHP.Eval
		} catch ( Throwable $e ) {
			$thrown = array(
				'class'   => get_class( $e ),
				'message' => $e->getMessage(),
				'line'    => $e->getLine(),
			);
		} finally {
			$elapsed = round( ( microtime( true ) - $start ) * 1000, 2 );
			$output  = (string) ob_get_clean();

			if ( null !== $prev_handler ) {
				set_error_handler( $prev_handler ); // phpcs:ignore
			} else {
				restore_error_handler();
			}
			if ( function_exists( 'set_time_limit' ) && '' !== $previous_limit ) {
				@set_time_limit( (int) $previous_limit ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
		}

		$truncated = false;
		if ( strlen( $output ) > self::MAX_OUTPUT_BYTES ) {
			$output    = substr( $output, 0, self::MAX_OUTPUT_BYTES );
			$truncated = true;
		}

		// Only the first 200 error entries are returned; a loop that emits a
		// notice per iteration should not turn into an unbounded response body.
		if ( count( $captured_errors ) > 200 ) {
			$captured_errors = array_slice( $captured_errors, 0, 200 );
			$truncated       = true;
		}

		$status = $thrown ? 'error' : 'ok';
		AuditLog::record(
			$this->key_id( $request ),
			'execute',
			$status,
			array( 'code_len' => strlen( $code ) ),
			mb_substr( $code, 0, 500 )
		);

		return $this->ok(
			array(
				'output'     => $output,
				'return'     => $this->normalize( $result ),
				'errors'     => $captured_errors,
				'exception'  => $thrown,
				'elapsed_ms' => $elapsed,
				'truncated'  => $truncated,
			)
		);
	}

	/**
	 * Run SQL with read/write classification.
	 */
	public function db_query( WP_REST_Request $request ) {
		global $wpdb;

		$sql = trim( (string) $request->get_param( 'sql' ) );

		if ( '' === $sql ) {
			return $this->fail( 'bridgistic_empty_sql', 'No SQL provided.', 400 );
		}

		// Classification decides which scope is required, so it happens before
		// any scope check. See SqlClassifier for why a prefix regex on the raw
		// string is not enough.
		$verdict = SqlClassifier::classify( $sql );

		if ( SqlClassifier::FORBIDDEN === $verdict['class'] ) {
			AuditLog::record( $this->key_id( $request ), 'db.rejected', 'denied', array( 'reason' => $verdict['reason'] ), mb_substr( $sql, 0, 500 ) );
			return $this->fail( 'bridgistic_sql_forbidden', $verdict['reason'], 403 );
		}

		$is_read = SqlClassifier::READ === $verdict['class'];

		$needed = $is_read ? Scopes::DB_READ : Scopes::DB_WRITE;
		$scope  = $this->require_scope( $request, $needed );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}

		if ( $is_read ) {
			$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB
			AuditLog::record( $this->key_id( $request ), 'db.read', null === $rows ? 'error' : 'ok', array(), mb_substr( $sql, 0, 500 ) );
			return $this->ok(
				array(
					'rows'      => is_array( $rows ) ? $rows : array(),
					'row_count' => is_array( $rows ) ? count( $rows ) : 0,
					'error'     => $wpdb->last_error ?: null,
				)
			);
		}

		// Write path → Guard (dry-run, approval, auto-snapshot of affected tables).
		$key_id        = $this->key_id( $request );
		$expects_prior = (bool) $verdict['destructive'];

		return \Bridgistic\Guard::run(
			$request,
			array(
				'action'      => 'db.write',
				'destructive' => true,
				'mutating'    => true,
				'force_approval' => true, // raw write SQL always needs a human OK when approval is on.
				'payload'     => array( 'sql' => $sql ),
				'summary'     => 'SQL: ' . mb_substr( $sql, 0, 120 ),
				'snapshot'    => static function () use ( $sql, $key_id, $expects_prior ) {
					$id = \Bridgistic\Snapshot::for_sql( $sql, $key_id );
					if ( $id ) {
						return array( 'snapshot_id' => $id );
					}
					// Couldn't capture prior state for a statement that destroys it.
					if ( $expects_prior ) {
						return new \WP_Error(
							'bridgistic_snap_unparsed',
							'Could not identify the table to snapshot for this write. Pass force=true to run without rollback (irreversible).',
							array( 'status' => 412 )
						);
					}
					return null; // INSERT/CREATE/SET: nothing prior to lose.
				},
				'dry_run'     => static function () use ( $sql, $wpdb ) {
					// Run inside a rolled-back transaction to report impact safely.
					$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB
					$affected = $wpdb->query( $sql );    // phpcs:ignore WordPress.DB
					$err      = $wpdb->last_error;
					$wpdb->query( 'ROLLBACK' );          // phpcs:ignore WordPress.DB
					return array(
						'would_affect_rows' => false === $affected ? 0 : (int) $affected,
						'error'             => $err ?: null,
						'note'              => 'Executed in a transaction and rolled back; nothing was persisted.',
					);
				},
				'execute'     => static function () use ( $sql, $wpdb ) {
					$affected = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB
					if ( false === $affected ) {
						return new \WP_Error( 'bridgistic_sql_error', $wpdb->last_error ?: 'SQL execution failed.', array( 'status' => 400 ) );
					}
					return array(
						'affected_rows' => (int) $affected,
						'insert_id'     => (int) $wpdb->insert_id,
					);
				},
			)
		);
	}

	/**
	 * Make a return value JSON-safe.
	 *
	 * @param mixed $value Raw return value.
	 * @return mixed
	 */
	private function normalize( $value ) {
		if ( is_array( $value ) ) {
			// A returned array can be arbitrarily large (`return $wpdb->get_results(...)`
			// over a big table), so bound it the same way echoed output is bounded.
			$encoded = wp_json_encode( $value );
			if ( is_string( $encoded ) && strlen( $encoded ) > self::MAX_OUTPUT_BYTES ) {
				return array(
					'__truncated' => true,
					'__note'      => sprintf(
						'Return value omitted: %d bytes exceeds the %d-byte response cap. Narrow the query or return a count.',
						strlen( $encoded ),
						self::MAX_OUTPUT_BYTES
					),
				);
			}
			return $value;
		}
		if ( is_string( $value ) && strlen( $value ) > self::MAX_OUTPUT_BYTES ) {
			return substr( $value, 0, self::MAX_OUTPUT_BYTES );
		}
		if ( is_scalar( $value ) || null === $value ) {
			return $value;
		}
		if ( is_object( $value ) ) {
			return array(
				'__type' => get_class( $value ),
				'value'  => method_exists( $value, '__toString' ) ? (string) $value : get_object_vars( $value ),
			);
		}
		return gettype( $value );
	}
}
