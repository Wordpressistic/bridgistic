<?php
/**
 * Bridgistic Cloud: informational entry point for the hosted MCP connector
 * (mcp.wpistic.cloud). Public beta — see docs/CLOUD_CONNECTOR.md for what
 * that status means. This page doesn't perform the OAuth handshake itself
 * (that's OAuthAuthorizePage, reached via a deep link from the Worker); it
 * just makes the option discoverable and explains it honestly, since it was
 * previously live in code but linked from nowhere in the dashboard.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CloudPage extends Page {

	/** The one fixed connector URL every site's AI client points at. */
	public const CONNECTOR_URL = 'https://mcp.wpistic.cloud/mcp';

	protected function view(): string {
		return 'cloud';
	}

	protected function data(): array {
		return array(
			'connector_url' => self::CONNECTOR_URL,
			'setup_url'     => admin_url( 'admin.php?page=bridgistic-setup' ),
			'logs_url'      => admin_url( 'admin.php?page=bridgistic-logs' ),
			'keys_url'      => admin_url( 'admin.php?page=bridgistic-keys' ),
		);
	}
}
