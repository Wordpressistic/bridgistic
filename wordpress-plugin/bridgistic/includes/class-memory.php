<?php
/**
 * Per-site memory: durable notes the agent writes once and recalls later
 * (site quirks, conventions, IDs, client preferences). Namespaced by category.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Custom-table persistence layer.
 *
 * The table name comes from self::table() (code-defined, plugin-prefixed) and
 * cannot be a prepare() placeholder, so the direct-DB sniffs are disabled for
 * this file. Every user-supplied value still goes through $wpdb->prepare().
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery
// phpcs:disable WordPress.DB.PreparedSQL
// phpcs:disable PluginCheck.Security.DirectDB

final class Memory {

	/** Maximum bytes retained for one fetched public context document. */
	private const FETCH_LIMIT = 200000;

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'bridgistic_memory';
	}

	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			category VARCHAR(64) NOT NULL DEFAULT 'general',
			mkey VARCHAR(191) NOT NULL,
			mvalue LONGTEXT NULL,
			updated_by VARCHAR(40) NOT NULL DEFAULT '',
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq (category, mkey),
			KEY category (category)
		) {$charset};";

		dbDelta( $sql );
	}

	public static function set( string $category, string $key, $value, string $by = '' ): array {
		global $wpdb;
		$category = $category ?: 'general';
		$json     = wp_json_encode( $value );
		$now      = current_time( 'mysql', true );

		$wpdb->query( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				'INSERT INTO ' . self::table() . ' (category, mkey, mvalue, updated_by, updated_at)
				 VALUES (%s, %s, %s, %s, %s)
				 ON DUPLICATE KEY UPDATE mvalue = VALUES(mvalue), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)',
				$category,
				$key,
				$json,
				$by,
				$now
			)
		);

		return array( 'category' => $category, 'key' => $key, 'saved' => true );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get( string $category, string $key ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE category = %s AND mkey = %s', $category ?: 'general', $key ),
			ARRAY_A
		);
		if ( ! $row ) {
			return null;
		}
		return array(
			'category'   => $row['category'],
			'key'        => $row['mkey'],
			'value'      => json_decode( (string) $row['mvalue'], true ),
			'updated_at' => $row['updated_at'],
		);
	}

	/** @return array<int,array<string,mixed>> */
	public static function list( string $category = '' ): array {
		global $wpdb;
		if ( $category ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT category, mkey, mvalue, updated_at FROM ' . self::table() . ' WHERE category = %s ORDER BY mkey ASC', $category ), ARRAY_A ); // phpcs:ignore WordPress.DB
		} else {
			$rows = $wpdb->get_results( 'SELECT category, mkey, mvalue, updated_at FROM ' . self::table() . ' ORDER BY category, mkey ASC', ARRAY_A ); // phpcs:ignore WordPress.DB
		}
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'category'   => $r['category'],
				'key'        => $r['mkey'],
				'value'      => json_decode( (string) $r['mvalue'], true ),
				'updated_at' => $r['updated_at'],
			);
		}
		return $out;
	}

	public static function delete( string $category, string $key ): void {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'category' => $category ?: 'general', 'mkey' => $key ), array( '%s', '%s' ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Build a durable, auditable context snapshot from the public site files and
	 * WordPress runtime. The fetch list is deliberately fixed: no arbitrary URL
	 * is accepted by this method, which keeps the scan safe from SSRF.
	 *
	 * @return array<string,mixed>
	 */
	public static function scan_site_context( string $by = '' ): array {
		$paths = array(
			'llms'      => '/llms.txt',
			'llms_full' => '/llms-full.txt',
			'sitemap'   => '/sitemap.xml',
		);
		$sources = array();
		foreach ( $paths as $key => $path ) {
			$sources[ $key ] = self::fetch_public_document( home_url( $path ) );
		}

		global $wp_version;
		$theme = wp_get_theme();
		$plugins = array();
		if ( function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			foreach ( (array) get_plugins() as $file => $data ) {
				$plugins[] = array(
					'name'    => (string) ( $data['Name'] ?? $file ),
					'version' => (string) ( $data['Version'] ?? '' ),
					'active'  => function_exists( 'is_plugin_active' ) && is_plugin_active( $file ),
				);
			}
		}
		$context = array(
			'scanned_at' => current_time( 'mysql', true ),
			'site'       => array(
				'name'       => get_bloginfo( 'name' ),
				'description' => get_bloginfo( 'description' ),
				'home_url'   => home_url( '/' ),
				'language'   => get_bloginfo( 'language' ),
				'timezone'   => wp_timezone_string(),
			),
			'wordpress'  => array(
				'version' => (string) $wp_version,
				'php'     => PHP_VERSION,
				'multisite' => is_multisite(),
			),
			'theme'      => array(
				'name' => $theme->get( 'Name' ),
				'version' => $theme->get( 'Version' ),
				'template' => $theme->get_template(),
			),
			'plugins'    => $plugins,
			'sources'    => $sources,
		);
		self::set( 'site_context', 'context', $context, $by );
		foreach ( $sources as $key => $source ) {
			self::set( 'site_context', $key, $source, $by );
		}
		return array( 'category' => 'site_context', 'key' => 'context', 'value' => $context, 'saved' => true );
	}

	/**
	 * Save a custom URL source after the caller has explicitly requested it.
	 * wp_safe_remote_get and wp_http_validate_url reject local/private targets.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function import_url( string $url, string $key, string $by = '' ) {
		$url = esc_url_raw( trim( $url ) );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return new \WP_Error( 'bridgistic_memory_url', 'Only a valid public HTTP(S) URL can be imported.', array( 'status' => 400 ) );
		}
		$key = sanitize_key( $key ?: wp_parse_url( $url, PHP_URL_HOST ) ?: 'external-source' );
		$source = self::fetch_public_document( $url );
		if ( ! empty( $source['error'] ) ) {
			return new \WP_Error( 'bridgistic_memory_fetch', (string) $source['error'], array( 'status' => 502 ) );
		}
		return self::set( 'custom', $key, $source, $by );
	}

	/** @return array<string,mixed> */
	private static function fetch_public_document( string $url ): array {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 8,
				'redirection'         => 0,
				'limit_response_size' => self::FETCH_LIMIT,
				'user-agent'          => 'Bridgistic/' . ( defined( 'BRIDGISTIC_VERSION' ) ? BRIDGISTIC_VERSION : '1.0' ) . '; site-context-scan',
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'url' => $url, 'status' => 0, 'content' => '', 'error' => $response->get_error_message() );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$content = (string) wp_remote_retrieve_body( $response );
		return array(
			'url'        => $url,
			'status'     => $status,
			'truncated'  => strlen( $content ) >= self::FETCH_LIMIT,
			'content'    => substr( $content, 0, self::FETCH_LIMIT ),
			'content_type' => (string) wp_remote_retrieve_header( $response, 'content-type' ),
		);
	}
}
