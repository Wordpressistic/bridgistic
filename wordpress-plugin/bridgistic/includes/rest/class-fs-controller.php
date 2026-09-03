<?php
/**
 * Filesystem access, confined to ABSPATH.
 *
 * Hard rule (mirrors the sandbox model, enforced server-side):
 * executable PHP can only be WRITTEN inside wp-content/uploads/bridgistic-sandbox/.
 * Non-PHP files may be written anywhere under ABSPATH. Reads/lists are ABSPATH-wide.
 * Writes and deletes snapshot the file first.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Rest;

use Bridgistic\Security\Scopes;
use Bridgistic\Snapshot;
use Bridgistic\Guard;
use Bridgistic\Plugin;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FsController extends Controller {

	public function register( string $namespace ): void {
		foreach ( array( 'list', 'read', 'write', 'delete' ) as $op ) {
			register_rest_route(
				$namespace,
				"/fs/{$op}",
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, $op ),
					'permission_callback' => array( $this, 'authenticate' ),
				)
			);
		}
	}

	public function list( WP_REST_Request $request ) {
		$gate = $this->require_scope( $request, Scopes::FS_READ );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$path = $this->confine( (string) ( $request->get_param( 'path' ) ?? ABSPATH ), true );
		if ( ! $path || ! is_dir( $path ) ) {
			return $this->fail( 'bridgistic_fs_dir', 'Not a directory inside ABSPATH.', 400 );
		}
		$entries = array();
		foreach ( (array) scandir( $path ) as $e ) {
			if ( '.' === $e || '..' === $e ) {
				continue;
			}
			$full      = trailingslashit( $path ) . $e;
			$entries[] = array(
				'name'  => $e,
				'type'  => is_dir( $full ) ? 'dir' : 'file',
				'size'  => is_file( $full ) ? filesize( $full ) : null,
			);
		}
		return $this->ok( array( 'path' => $path, 'entries' => $entries ) );
	}

	public function read( WP_REST_Request $request ) {
		$gate = $this->require_scope( $request, Scopes::FS_READ );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$path = $this->confine( (string) ( $request->get_param( 'path' ) ?? '' ), true );
		if ( ! $path || ! is_file( $path ) ) {
			return $this->fail( 'bridgistic_fs_404', 'File not found inside ABSPATH.', 404 );
		}
		if ( $this->is_protected_credential_file( $path ) ) {
			return $this->fail(
				'bridgistic_fs_protected',
				'This file holds site credentials (database password, WordPress salts, or private keys) and cannot be read through the bridge, at any scope.',
				403
			);
		}
		if ( filesize( $path ) > 5 * MB_IN_BYTES ) {
			return $this->fail( 'bridgistic_fs_big', 'File too large to read (>5MB).', 413 );
		}
		return $this->ok( array( 'path' => $path, 'content' => (string) file_get_contents( $path ) ) ); // phpcs:ignore
	}

	public function write( WP_REST_Request $request ) {
		$gate = $this->require_scope( $request, Scopes::FS_WRITE );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$raw     = (string) ( $request->get_param( 'path' ) ?? '' );
		$content = (string) ( $request->get_param( 'content' ) ?? '' );
		$path    = $this->confine( $raw, false );
		if ( ! $path ) {
			return $this->fail( 'bridgistic_fs_path', 'Path is outside ABSPATH.', 400 );
		}
		// Execution guard. Block not only PHP but any file that can turn other
		// files into executable PHP (.htaccess / .user.ini / php.ini / web.config).
		// Otherwise fs:write silently escalates to arbitrary code execution.
		if ( $this->is_protected_credential_file( $path ) ) {
			return $this->fail(
				'bridgistic_fs_protected',
				'This file holds site credentials and cannot be written through the bridge, at any scope.',
				403
			);
		}
		if ( ( $this->is_php( $path ) || $this->is_exec_control( $path ) ) && ! $this->in_sandbox( $path ) ) {
			return $this->fail(
				'bridgistic_fs_sandbox',
				'PHP and execution-control files (.htaccess, .user.ini, php.ini, web.config) can only be written inside wp-content/uploads/' . BRIDGISTIC_SANDBOX . '/.',
				403
			);
		}

		return Guard::run(
			$request,
			array(
				'action'      => 'fs.write',
				'destructive' => file_exists( $path ), // only destructive if overwriting.
				'mutating'    => true,
				'payload'     => array( 'path' => $path, 'bytes' => strlen( $content ) ),
				'summary'     => 'Write ' . $path,
				'snapshot'    => fn() => Snapshot::create( 'file', array( 'path' => $path ), 'pre-write ' . basename( $path ), $this->key_id( $request ) ),
				'dry_run'     => static fn() => array( 'write' => $path, 'bytes' => strlen( $content ), 'exists' => file_exists( $path ) ),
				'execute'     => static function () use ( $path, $content ) {
					if ( ! is_dir( dirname( $path ) ) ) {
						wp_mkdir_p( dirname( $path ) );
					}
					$ok = file_put_contents( $path, $content ); // phpcs:ignore
					return false === $ok ? new \WP_Error( 'bridgistic_fs_write', 'Write failed.', array( 'status' => 500 ) ) : array( 'path' => $path, 'bytes' => (int) $ok );
				},
			)
		);
	}

	public function delete( WP_REST_Request $request ) {
		$gate = $this->require_scope( $request, Scopes::FS_WRITE );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$path = $this->confine( (string) ( $request->get_param( 'path' ) ?? '' ), true );
		if ( ! $path || ! is_file( $path ) ) {
			return $this->fail( 'bridgistic_fs_404', 'File not found inside ABSPATH.', 404 );
		}
		if ( $this->is_protected_credential_file( $path ) ) {
			return $this->fail(
				'bridgistic_fs_protected',
				'This file holds site credentials and cannot be deleted through the bridge, at any scope.',
				403
			);
		}

		return Guard::run(
			$request,
			array(
				'action'      => 'fs.delete',
				'destructive' => true,
				'mutating'    => true,
				'force_approval' => true, // deleting files is high-risk.
				'payload'     => array( 'path' => $path ),
				'summary'     => 'Delete ' . $path,
				'snapshot'    => fn() => Snapshot::create( 'file', array( 'path' => $path ), 'pre-delete ' . basename( $path ), $this->key_id( $request ) ),
				'dry_run'     => static fn() => array( 'delete' => $path ),
				'execute'     => static function () use ( $path ) {
					return wp_delete_file( $path ) || ! file_exists( $path )
						? array( 'path' => $path, 'deleted' => true )
						: new \WP_Error( 'bridgistic_fs_del', 'Delete failed.', array( 'status' => 500 ) );
				},
			)
		);
	}

	/**
	 * Confine a path to ABSPATH. $must_exist=false allows new-file paths whose
	 * parent dir is inside ABSPATH.
	 *
	 * realpath() resolves symlinks and `..`, so traversal and symlink escapes
	 * both collapse into a plain containment question — but that question has
	 * to be asked about *path segments*, not string prefixes: with an ABSPATH
	 * of `/var/www/html`, a naive `strpos($real, $base) === 0` also accepts
	 * `/var/www/html-backup/wp-config.php`, a sibling directory outside the
	 * install. contains() below compares against `$base . DIRECTORY_SEPARATOR`.
	 */
	private function confine( string $path, bool $must_exist ): string {
		$base = realpath( ABSPATH );
		if ( false === $base ) {
			return '';
		}
		$real = realpath( $path );
		if ( false === $real ) {
			if ( $must_exist ) {
				return '';
			}
			$parent = realpath( dirname( $path ) );
			if ( false === $parent || ! self::contains( $base, $parent ) ) {
				return '';
			}
			// basename() strips any traversal the caller put in the final
			// segment, so the result cannot climb out of the resolved parent.
			return trailingslashit( $parent ) . basename( $path );
		}
		return self::contains( $base, $real ) ? $real : '';
	}

	/**
	 * True when $candidate is $base itself or lives beneath it, compared on
	 * whole path segments rather than raw string prefixes.
	 */
	private static function contains( string $base, string $candidate ): bool {
		$base      = rtrim( $base, '/\\' );
		$candidate = rtrim( $candidate, '/\\' );
		if ( $base === $candidate ) {
			return true;
		}
		return 0 === strpos( $candidate, $base . DIRECTORY_SEPARATOR )
			|| 0 === strpos( $candidate, $base . '/' );
	}

	/**
	 * Files whose contents are credentials, not site content.
	 *
	 * wp-config.php holds the database password *and* AUTH_KEY/SECURE_AUTH_KEY,
	 * which are two of the three inputs to Security\Crypto's key derivation —
	 * so an fs:read of it plus a db:read of wp_bridgistic_keys is enough to
	 * decrypt every key secret on the site. That turns a read-only-ish scope
	 * into full credential disclosure, so these are denied to fs:read
	 * regardless of where under ABSPATH they sit.
	 */
	private function is_protected_credential_file( string $path ): bool {
		$base = strtolower( basename( $path ) );
		return in_array(
			$base,
			array(
				'wp-config.php',
				'wp-config-sample.php',
				'.env',
				'.env.local',
				'.env.production',
				'.htpasswd',
				'.my.cnf',
				'.netrc',
				'.npmrc',
				'.pgpass',
				'id_rsa',
				'id_ed25519',
			),
			true
		);
	}

	private function is_php( string $path ): bool {
		return (bool) preg_match( '/\.(php|php\d|phtml|phps|phar)$/i', $path );
	}

	/**
	 * Files that can re-configure the web server to execute otherwise-inert
	 * files as PHP (Apache/LiteSpeed/IIS). Writing these anywhere in the docroot
	 * is a sandbox escape, so they are treated like PHP.
	 */
	private function is_exec_control( string $path ): bool {
		$base = strtolower( basename( $path ) );
		if ( in_array( $base, array( '.htaccess', '.htpasswd', '.user.ini', 'php.ini', 'web.config' ), true ) ) {
			return true;
		}
		// Any Apache .ht* control file.
		return strpos( $base, '.ht' ) === 0;
	}

	private function in_sandbox( string $path ): bool {
		$sandbox = realpath( Plugin::ensure_sandbox() );
		// Segment-aware, for the same reason confine() is: a `bridgistic-sandbox-x`
		// sibling directory must not read as "inside the sandbox".
		return $sandbox && self::contains( $sandbox, $path );
	}
}
