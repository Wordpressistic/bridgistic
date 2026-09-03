<?php
/**
 * AI / MCP Connections: five-step guided connection wizard.
 *
 * The class name, view name, and page slug still say "claude-setup" on
 * purpose. The screen is no longer Claude-only, but the slug is in bookmarks,
 * in the activation redirect, and in every doc and changelog entry shipped so
 * far, and the class is referenced by Admin\Controller::pages(). Renaming the
 * identifiers would break those links to relabel a heading, so only the
 * user-facing strings changed.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

use Bridgistic\Security\KeyStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ClaudeSetupPage extends Page {

	protected function view(): string {
		return 'claude-setup';
	}

	protected function data(): array {
		return array(
			'presets'        => Presets::all(),
			'site_url'       => home_url(),
			'keys_count'     => count( KeyStore::list_all() ),
			'export_url'     => admin_url( 'admin.php?page=bridgistic-export' ),
			// Single source of truth, shared with the Bridgistic Cloud page so
			// the two screens can never show different endpoints.
			'cloud_endpoint' => CloudPage::CONNECTOR_URL,
			'marketplace_cmds' => "/plugin marketplace add wordpressistic/bridgistic\n/plugin install bridgistic@bridgistic-marketplace",
		);
	}
}
