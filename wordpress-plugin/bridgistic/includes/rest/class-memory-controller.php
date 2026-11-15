<?php
/**
 * Per-site memory endpoints.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Rest;

use Bridgistic\Security\Scopes;
use Bridgistic\Memory;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MemoryController extends Controller {

	private const MAX_FETCH_BYTES = 120000;
	private const FETCH_TIMEOUT    = 8;

	public function register( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/memory',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'read' ),
					'permission_callback' => array( $this, 'authenticate' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'write' ),
					'permission_callback' => array( $this, 'authenticate' ),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/memory/delete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'remove' ),
				'permission_callback' => array( $this, 'authenticate' ),
			)
		);
		register_rest_route(
			$namespace,
			'/memory/scan',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'scan' ),
				'permission_callback' => array( $this, 'authenticate' ),
			)
		);
		register_rest_route(
			$namespace,
			'/memory/import-url',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'import_url' ),
				'permission_callback' => array( $this, 'authenticate' ),
			)
		);
	}

	public function read( WP_REST_Request $request ) {
		$gate = $this->require_scope( $request, Scopes::MEMORY_READ );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$category = (string) ( $request->get_param( 'category' ) ?? '' );
		$key      = (string) ( $request->get_param( 'key' ) ?? '' );

		if ( '' !== $key ) {
			$item = Memory::get( $category ?: 'general', $key );
			return $item ? $this->ok( $item ) : $this->fail( 'bridgistic_mem_404', 'No memory under that key.', 404 );
		}
		return $this->ok( array( 'items' => Memory::list( $category ) ) );
	}

	public function write( WP_REST_Request $request ) {
		$gate = $this->require_scope( $request, Scopes::MEMORY_WRITE );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$key = (string) ( $request->get_param( 'key' ) ?? '' );
		if ( '' === $key ) {
			return $this->fail( 'bridgistic_mem_args', "Provide 'key' (and optional 'category', 'value').", 400 );
		}
		return $this->ok(
			Memory::set(
				(string) ( $request->get_param( 'category' ) ?? 'general' ),
				$key,
				$request->get_param( 'value' ),
				$this->key_id( $request )
			)
		);
	}

	public function remove( WP_REST_Request $request ) {
		$gate = $this->require_scope( $request, Scopes::MEMORY_WRITE );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$key = (string) ( $request->get_param( 'key' ) ?? '' );
		Memory::delete( (string) ( $request->get_param( 'category' ) ?? 'general' ), $key );
		return $this->ok( array( 'deleted' => true, 'key' => $key ) );
	}

	/**
	 * Fetch the site's public context and persist each successful source.
	 * Missing optional files are reported per-source, not as a failed scan.
	 */
	public function scan( WP_REST_Request $request ) {
		$gate = $this->require_scope( $request, Scopes::MEMORY_WRITE );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$base = rtrim( home_url( '/' ), '/' ) . '/';
		$sources = array(
			'llms'      => $base . 'llms.txt',
			'llms-full' => $base . 'llms-full.txt',
			'sitemap'   => $base . 'sitemap.xml',
		);
		$results = array();
		$saved   = 0;

		foreach ( $sources as $key => $url ) {
			$fetched = self::fetch_text( $url );
			$results[ $key ] = array(
				'url'     => $url,
				'ok'      => $fetched['ok'],
				'status'  => $fetched['status'],
				'bytes'   => $fetched['bytes'],
				'message' => $fetched['message'],
			);
			if ( ! $fetched['ok'] ) {
				continue;
			}
			Memory::set(
				'site_context',
				$key,
				array(
					'url'        => $url,
					'content'    => $fetched['content'],
					'http_status' => $fetched['status'],
					'scanned_at' => gmdate( 'c' ),
				),
				$this->key_id( $request )
			);
			$saved++;
		}

		Memory::set(
			'site_context',
			'wordpress',
			array(
				'home_url'       => $base,
				'rest_url'       => rtrim( site_url( '/wp-json/' ), '/' ) . '/',
				'wordpress'      => get_bloginfo( 'version' ),
				'theme'          => (string) get_option( 'stylesheet', '' ),
				'active_plugins' => array_values( (array) get_option( 'active_plugins', array() ) ),
				'scanned_at'     => gmdate( 'c' ),
			),
			$this->key_id( $request )
		);
		$saved++;

		return $this->ok(
			array(
				'saved'   => $saved,
				'sources' => $results,
			)
		);
	}

	/** Import a bounded public Markdown/text URL into site memory. */
	public function import_url( WP_REST_Request $request ) {
		$gate = $this->require_scope( $request, Scopes::MEMORY_WRITE );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$url = trim( (string) ( $request->get_param( 'url' ) ?? '' ) );
		if ( '' === $url ) {
			return $this->fail( 'bridgistic_mem_args', "Provide 'url'.", 400 );
		}
		$fetched = self::fetch_text( $url );
		if ( ! $fetched['ok'] ) {
			return $this->fail( 'bridgistic_mem_fetch', $fetched['message'], $fetched['status'] >= 400 ? 422 : 502 );
		}

		$key = sanitize_key( (string) ( $request->get_param( 'key' ) ?? '' ) );
		if ( '' === $key ) {
			$parts = parse_url( $url );
			$key   = sanitize_key( (string) ( $parts['host'] ?? 'import' ) . '-' . (string) ( basename( (string) ( $parts['path'] ?? '' ) ) ?: 'context' ) );
		}
		$key = substr( $key ?: 'imported-context', 0, 180 );

		return $this->ok(
			array_merge(
				Memory::set(
					'custom',
					$key,
					array(
						'url'         => $url,
						'content'     => $fetched['content'],
						'http_status' => $fetched['status'],
						'imported_at' => gmdate( 'c' ),
					),
					$this->key_id( $request )
				),
				array( 'bytes' => $fetched['bytes'] )
			)
		);
	}

	/** @return array{ok:bool,status:int,bytes:int,content:string,message:string} */
	private static function fetch_text( string $url ): array {
		$parts = parse_url( $url );
		if ( ! is_array( $parts ) || ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), array( 'http', 'https' ), true ) || '' === (string) ( $parts['host'] ?? '' ) ) {
			return array( 'ok' => false, 'status' => 400, 'bytes' => 0, 'content' => '', 'message' => 'Only public HTTP(S) URLs can be imported.' );
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => self::FETCH_TIMEOUT,
				'redirection'         => 3,
				'limit_response_size' => self::MAX_FETCH_BYTES,
				'user-agent'          => 'Bridgistic/' . BRIDGISTIC_VERSION . '; ' . home_url( '/' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'status' => 502, 'bytes' => 0, 'content' => '', 'message' => 'The public URL could not be fetched.' );
		}

		$status  = wp_remote_retrieve_response_code( $response );
		$content = wp_remote_retrieve_body( $response );
		$bytes   = strlen( $content );
		if ( $status < 200 || $status >= 300 ) {
			return array( 'ok' => false, 'status' => $status, 'bytes' => $bytes, 'content' => '', 'message' => 'The URL returned HTTP ' . $status . '.' );
		}
		return array( 'ok' => true, 'status' => $status, 'bytes' => $bytes, 'content' => $content, 'message' => 'Fetched.' );
	}
}
