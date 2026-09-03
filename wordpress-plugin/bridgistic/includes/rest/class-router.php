<?php
/**
 * Registers every REST controller under the bridge namespace.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Router {

	public function register_routes(): void {
		$ns = BRIDGISTIC_REST_NAMESPACE;

		// Core / discovery.
		( new SiteController() )->register( $ns );
		( new ExecuteController() )->register( $ns );

		// Cloud connector OAuth (the one unauthenticated route â see
		// OauthController's docblock for why it bypasses Controller::authenticate()).
		( new OauthController() )->register( $ns );

		// Structured tools.
		( new PostsController() )->register( $ns );
		( new MediaController() )->register( $ns );
		( new UsersController() )->register( $ns );
		( new OptionsController() )->register( $ns );
		( new PluginsController() )->register( $ns );
		( new FsController() )->register( $ns );

		// WooCommerce. Routes register unconditionally so a store that
		// activates Woo later needs no plugin reload, and so a call against a
		// non-store site gets a structured "WooCommerce unavailable" answer
		// instead of a bare 404 the agent has to guess at.
		( new WooController() )->register( $ns );

		// Safety layer.
		( new SnapshotController() )->register( $ns );
		( new ApprovalsController() )->register( $ns );

		// Metering + intelligence layer.
		( new UsageController() )->register( $ns );
		( new MemoryController() )->register( $ns );
		( new PlaybookController() )->register( $ns );
		( new ScheduleController() )->register( $ns );
	}
}
