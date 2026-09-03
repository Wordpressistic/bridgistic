<?php
/**
 * SQL read/write classification for the db/query tool.
 *
 * The naive version of this was a single prefix regex on the raw string, which
 * three different inputs walk straight past:
 *
 *   1. `WITH t AS (SELECT 1) DELETE FROM wp_posts WHERE ...`
 *      MySQL 8 allows a CTE to prefix UPDATE/DELETE. A leading-keyword test
 *      reads `WITH` and hands a destructive statement the `db:read` scope —
 *      skipping the approval queue and the pre-write snapshot entirely.
 *   2. `SELECT 1; DROP TABLE wp_posts`
 *      Stacked statements. Whether the driver executes the tail depends on the
 *      connection's multi-statement flag, which is not this plugin's to assume.
 *   3. `/*x*\/ SELECT ...` or `-- note\nDELETE ...`
 *      A leading comment moves the real keyword off position zero, so the
 *      classification lands on whatever the comment happens to start with.
 *
 * So: strip comments and string literals first, then classify the resulting
 * skeleton, and reject anything that cannot be classified with confidence.
 * Failing closed here is cheap — the caller can always split a batch into
 * single statements — while failing open silently escalates db:read to
 * db:write.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SqlClassifier {

	public const READ      = 'read';
	public const WRITE     = 'write';
	public const FORBIDDEN = 'forbidden';

	/**
	 * Statements that only ever read. Anything not on this list is treated as
	 * a write, so a keyword this plugin has never heard of fails safe.
	 */
	private const READ_KEYWORDS = array( 'select', 'show', 'explain', 'describe', 'desc', 'analyze' );

	/**
	 * Keywords that mutate. Used to catch a mutation hiding behind a CTE, and
	 * to decide whether prior state exists to snapshot.
	 */
	private const WRITE_KEYWORDS = array(
		'insert',
		'update',
		'delete',
		'replace',
		'drop',
		'truncate',
		'alter',
		'create',
		'rename',
		'grant',
		'revoke',
		'set',
		'call',
		'do',
		'handler',
		'lock',
		'unlock',
		'flush',
		'reset',
		'load',
		'install',
		'uninstall',
		'shutdown',
		'kill',
		'start',
		'commit',
		'rollback',
		'savepoint',
		'prepare',
		'execute',
		'deallocate',
	);

	/**
	 * Statements that destroy or overwrite prior state, so a snapshot must
	 * exist before they run.
	 */
	private const DESTRUCTIVE_KEYWORDS = array( 'update', 'delete', 'replace', 'drop', 'truncate', 'alter', 'rename' );

	/**
	 * Classify a statement.
	 *
	 * @param string $sql Raw SQL as submitted.
	 * @return array{class:string,keyword:string,destructive:bool,reason:string}
	 */
	public static function classify( string $sql ): array {
		$skeleton = self::skeleton( $sql );

		if ( '' === trim( $skeleton ) ) {
			return self::verdict( self::FORBIDDEN, '', false, 'The statement is empty once comments are removed.' );
		}

		// 1. File-access SQL reads or writes the server filesystem regardless of
		// its leading keyword, so it escapes the read/write scope split and the
		// filesystem sandbox at the same time. Never allowed, either scope.
		if ( preg_match( '/\b(into\s+(?:out|dump)file|load_file|load\s+data)\b/i', $skeleton ) ) {
			return self::verdict(
				self::FORBIDDEN,
				'',
				false,
				'File-access SQL (INTO OUTFILE / INTO DUMPFILE / LOAD_FILE / LOAD DATA) is not permitted.'
			);
		}

		// 2. Stacked statements. Semicolons inside strings and comments are
		// already gone from the skeleton, so a remaining one that is followed by
		// anything means a second statement is present.
		if ( self::has_stacked_statements( $skeleton ) ) {
			return self::verdict(
				self::FORBIDDEN,
				'',
				false,
				'Multiple SQL statements in one call are not permitted. Send one statement per request.'
			);
		}

		// 3. Leading keyword of the (comment-free) statement.
		if ( ! preg_match( '/^\s*\(*\s*([a-z_]+)/i', $skeleton, $match ) ) {
			return self::verdict( self::FORBIDDEN, '', false, 'Could not identify the SQL statement type.' );
		}
		$keyword = strtolower( $match[1] );

		// 4. A CTE can prefix a SELECT (read) or an UPDATE/DELETE (write), so
		// the leading keyword alone decides nothing — look at what follows the
		// CTE definition list.
		if ( 'with' === $keyword ) {
			$mutation = self::mutation_after_cte( $skeleton );
			if ( null !== $mutation ) {
				return self::verdict( self::WRITE, $mutation, self::is_destructive( $mutation ), 'Common table expression feeding a ' . strtoupper( $mutation ) . '.' );
			}
			return self::verdict( self::READ, 'with', false, 'Read-only common table expression.' );
		}

		if ( in_array( $keyword, self::READ_KEYWORDS, true ) ) {
			// `SELECT ... FOR UPDATE` / `LOCK IN SHARE MODE` take row locks but
			// change no data; they stay reads. `SELECT ... INTO OUTFILE` was
			// already rejected above.
			return self::verdict( self::READ, $keyword, false, 'Read-only statement.' );
		}

		if ( in_array( $keyword, self::WRITE_KEYWORDS, true ) ) {
			return self::verdict( self::WRITE, $keyword, self::is_destructive( $keyword ), 'Mutating statement.' );
		}

		// 5. Unknown leading keyword — fail closed as a write so it still has to
		// clear db:write, approval, and the snapshot gate.
		return self::verdict( self::WRITE, $keyword, true, 'Unrecognised statement type; treated as a destructive write.' );
	}

	/** True when the statement destroys prior state that a snapshot must capture. */
	public static function is_destructive( string $keyword ): bool {
		return in_array( strtolower( $keyword ), self::DESTRUCTIVE_KEYWORDS, true );
	}

	/**
	 * Strip comments and string/identifier literal *contents*, leaving a
	 * skeleton whose keywords and punctuation are structural rather than data.
	 *
	 * Literals are replaced by an empty quoted pair rather than deleted so the
	 * result is still recognisably the same statement shape — `WHERE x = ''`
	 * instead of `WHERE x =`.
	 */
	public static function skeleton( string $sql ): string {
		$out    = '';
		$len    = strlen( $sql );
		$i      = 0;

		while ( $i < $len ) {
			$char = $sql[ $i ];
			$next = $i + 1 < $len ? $sql[ $i + 1 ] : '';

			// -- line comment (requires a following space/newline per MySQL) and # comment.
			if ( ( '-' === $char && '-' === $next && ( $i + 2 >= $len || preg_match( '/\s/', $sql[ $i + 2 ] ) ) ) || '#' === $char ) {
				$end = strpos( $sql, "\n", $i );
				if ( false === $end ) {
					break;
				}
				$out .= ' ';
				$i    = $end + 1;
				continue;
			}

			// /* block comment */ — including MySQL's /*! executable comments,
			// which are stripped the same way so their contents cannot smuggle
			// a keyword past classification.
			if ( '/' === $char && '*' === $next ) {
				$end = strpos( $sql, '*/', $i + 2 );
				if ( false === $end ) {
					break;
				}
				$out .= ' ';
				$i    = $end + 2;
				continue;
			}

			// Quoted literals and identifiers: ' " `
			if ( "'" === $char || '"' === $char || '`' === $char ) {
				$quote = $char;
				$i++;
				while ( $i < $len ) {
					if ( '\\' === $sql[ $i ] && "'" !== $quote && '`' !== $quote ) {
						$i += 2;
						continue;
					}
					if ( '\\' === $sql[ $i ] && "'" === $quote ) {
						$i += 2;
						continue;
					}
					if ( $sql[ $i ] === $quote ) {
						// Doubled quote is an escaped quote, not a terminator.
						if ( $i + 1 < $len && $sql[ $i + 1 ] === $quote ) {
							$i += 2;
							continue;
						}
						$i++;
						break;
					}
					$i++;
				}
				$out .= $quote . $quote;
				continue;
			}

			$out .= $char;
			$i++;
		}

		return $out;
	}

	/**
	 * True when the skeleton contains a second statement.
	 *
	 * A single trailing semicolon (with only whitespace after it) is the normal
	 * way people paste SQL and is not a stacked statement.
	 */
	private static function has_stacked_statements( string $skeleton ): bool {
		$position = strpos( $skeleton, ';' );
		while ( false !== $position ) {
			if ( '' !== trim( substr( $skeleton, $position + 1 ) ) ) {
				return true;
			}
			$position = strpos( $skeleton, ';', $position + 1 );
		}
		return false;
	}

	/**
	 * For a `WITH ...` statement, return the mutating keyword it feeds, or null
	 * when the CTE only feeds a SELECT.
	 *
	 * Walks past the balanced parentheses of each CTE body so a `DELETE` that
	 * appears *inside* a CTE subquery is not confused with the statement the
	 * CTE list is attached to.
	 */
	private static function mutation_after_cte( string $skeleton ): ?string {
		$len   = strlen( $skeleton );
		$depth = 0;

		for ( $i = 0; $i < $len; $i++ ) {
			$char = $skeleton[ $i ];

			if ( '(' === $char ) {
				$depth++;
				continue;
			}
			if ( ')' !== $char ) {
				continue;
			}

			$depth--;
			if ( 0 !== $depth ) {
				continue;
			}

			// A CTE body just closed. What follows is either another CTE
			// definition (`, name AS (...)`) or the statement the whole CTE
			// list is attached to.
			//
			// This has to be the FIRST group that closes at depth zero, not
			// the last: `WITH t AS (...) DELETE FROM p WHERE id IN (SELECT ...)`
			// closes a second group at the end of the statement, and reading
			// the tail after *that* one finds nothing at all — which is how a
			// DELETE ends up classified as a read.
			$tail = substr( $skeleton, $i + 1 );

			if ( 1 === preg_match( '/^\s*,/', $tail ) ) {
				continue; // another CTE definition follows
			}

			if ( 1 !== preg_match( '/^\s*([a-z_]+)/i', $tail, $match ) ) {
				return null;
			}

			$keyword = strtolower( $match[1] );
			return in_array( $keyword, self::WRITE_KEYWORDS, true ) ? $keyword : null;
		}

		// Unbalanced parentheses: can't reason about it, so treat it as a write.
		return 0 !== $depth ? 'unbalanced' : null;
	}

	/**
	 * @return array{class:string,keyword:string,destructive:bool,reason:string}
	 */
	private static function verdict( string $class, string $keyword, bool $destructive, string $reason ): array {
		return array(
			'class'       => $class,
			'keyword'     => $keyword,
			'destructive' => $destructive,
			'reason'      => $reason,
		);
	}
}
