<?php
/**
 * Structured WooCommerce operations.
 *
 * Before this existed, "list last month's orders" meant an agent writing raw
 * SQL against wp_posts/wp_postmeta (or worse, HPOS tables, whose schema
 * differs) or arbitrary PHP — both of which need db:read or php:execute, the
 * two broadest scopes the bridge has. A store owner who wants an assistant to
 * read orders should not have to grant it the ability to run arbitrary
 * queries, and an agent should not have to know whether a given install has
 * migrated to High-Performance Order Storage.
 *
 * So: everything here goes through WooCommerce's own public API (wc_get_products,
 * wc_get_orders, WC_Order, WC_Customer), which is HPOS-agnostic, and each route
 * requires exactly one narrow woo:* scope. Writes still flow through Guard, so
 * dry-run, the approval queue, and pre-write snapshots all apply unchanged.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Rest;

use Bridgistic\Guard;
use Bridgistic\Security\Scopes;
use Bridgistic\Snapshot;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WooController extends Controller {

	/** Hard ceiling on any list response, whatever per_page asks for. */
	private const MAX_PER_PAGE = 100;

	public function register( string $namespace ): void {
		$read_only = array(
			'woo/products'           => 'list_products',
			'woo/orders'             => 'list_orders',
			'woo/customers'          => 'list_customers',
			'woo/inventory'          => 'inventory_status',
			'woo/sales-summary'      => 'sales_summary',
		);
		foreach ( $read_only as $route => $method ) {
			register_rest_route(
				$namespace,
				'/' . $route,
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, $method ),
					'permission_callback' => array( $this, 'authenticate' ),
				)
			);
		}

		register_rest_route(
			$namespace,
			'/woo/products/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_product' ),
					'permission_callback' => array( $this, 'authenticate' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_product' ),
					'permission_callback' => array( $this, 'authenticate' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/woo/products',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_product' ),
				'permission_callback' => array( $this, 'authenticate' ),
			)
		);

		register_rest_route(
			$namespace,
			'/woo/orders/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_order' ),
				'permission_callback' => array( $this, 'authenticate' ),
			)
		);

		register_rest_route(
			$namespace,
			'/woo/orders/(?P<id>\d+)/status',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_order_status' ),
				'permission_callback' => array( $this, 'authenticate' ),
			)
		);

		register_rest_route(
			$namespace,
			'/woo/customers/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_customer' ),
				'permission_callback' => array( $this, 'authenticate' ),
			)
		);
	}

	// ---- availability --------------------------------------------------------

	/**
	 * True when WooCommerce is installed, active, and far enough through boot
	 * for its data API to be usable.
	 */
	public static function is_available(): bool {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' ) && function_exists( 'wc_get_orders' );
	}

	/**
	 * Structured "not available" error.
	 *
	 * Deliberately a 200-level structured failure rather than a fatal: an agent
	 * calling a Woo tool on a non-store site should get a sentence it can act
	 * on ("this site doesn't run WooCommerce"), and site-info must keep working
	 * either way.
	 */
	private function unavailable() {
		return $this->fail(
			'bridgistic_woo_unavailable',
			'WooCommerce is not installed or not active on this site, so store tools are unavailable. Every other Bridgistic tool still works.',
			409
		);
	}

	/**
	 * Shared entry gate: scope first, then availability.
	 *
	 * Order matters — a key without the scope must be told about the scope,
	 * not handed a store-availability probe it was never authorised to run.
	 *
	 * @return true|\WP_Error
	 */
	private function gate( WP_REST_Request $request, string $scope ) {
		$allowed = $this->require_scope( $request, $scope );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		if ( ! self::is_available() ) {
			return $this->unavailable();
		}
		return true;
	}

	// ---- products ------------------------------------------------------------

	public function list_products( WP_REST_Request $request ) {
		$gate = $this->gate( $request, Scopes::WOO_PRODUCTS_READ );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
		$per_page = min( self::MAX_PER_PAGE, max( 1, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) );

		$args = array(
			'limit'    => $per_page,
			'page'     => $page,
			'paginate' => true,
			'orderby'  => 'date',
			'order'    => 'DESC',
		);

		$search = trim( (string) ( $request->get_param( 'search' ) ?? '' ) );
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		$status = sanitize_key( (string) ( $request->get_param( 'status' ) ?? '' ) );
		if ( '' !== $status ) {
			$args['status'] = $status;
		}
		$stock_status = sanitize_key( (string) ( $request->get_param( 'stock_status' ) ?? '' ) );
		if ( in_array( $stock_status, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
			$args['stock_status'] = $stock_status;
		}
		$category = trim( (string) ( $request->get_param( 'category' ) ?? '' ) );
		if ( '' !== $category ) {
			$args['category'] = array( sanitize_title( $category ) );
		}

		$results = wc_get_products( $args );

		$items = array();
		foreach ( $results->products as $product ) {
			$items[] = $this->product_summary( $product );
		}

		return $this->ok(
			array(
				'total'    => (int) $results->total,
				'count'    => count( $items ),
				'page'     => $page,
				'has_more' => $page < (int) $results->max_num_pages,
				'items'    => $items,
			)
		);
	}

	public function get_product( WP_REST_Request $request ) {
		$gate = $this->gate( $request, Scopes::WOO_PRODUCTS_READ );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$product = wc_get_product( (int) $request['id'] );
		if ( ! $product ) {
			return $this->fail( 'bridgistic_woo_product_404', sprintf( 'No WooCommerce product with id %d.', (int) $request['id'] ), 404 );
		}

		$data = $this->product_summary( $product );

		$data['description']       = $product->get_description();
		$data['short_description'] = $product->get_short_description();
		$data['weight']            = $product->get_weight();
		$data['categories']        = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
		$data['tags']              = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'names' ) );
		$data['attributes']        = array();
		foreach ( $product->get_attributes() as $attribute ) {
			$data['attributes'][] = array(
				'name'      => $attribute->get_name(),
				'options'   => $attribute->get_options(),
				'variation' => $attribute->get_variation(),
			);
		}

		// Variations carry their own price and stock, which is usually the
		// actual answer when someone asks "is this in stock?".
		$data['variations'] = array();
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				$variation = wc_get_product( $child_id );
				if ( $variation ) {
					$data['variations'][] = array(
						'id'           => $variation->get_id(),
						'sku'          => $variation->get_sku(),
						'price'        => $variation->get_price(),
						'stock_status' => $variation->get_stock_status(),
						'stock'        => $variation->get_stock_quantity(),
						'attributes'   => $variation->get_attributes(),
					);
				}
			}
		}

		return $this->ok( $data );
	}

	public function create_product( WP_REST_Request $request ) {
		$gate = $this->gate( $request, Scopes::WOO_PRODUCTS_WRITE );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$fields = $this->product_input( $request );
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}
		if ( '' === (string) ( $fields['name'] ?? '' ) ) {
			return $this->fail( 'bridgistic_woo_name_required', 'A product name is required.', 400 );
		}

		return Guard::run(
			$request,
			array(
				'action'      => 'woo.product.create',
				// Creating adds a row; there is no prior state a snapshot could
				// preserve, so this is mutating but not destructive.
				'destructive' => false,
				'mutating'    => true,
				'payload'     => array( 'name' => $fields['name'] ),
				'summary'     => 'Create WooCommerce product: ' . $fields['name'],
				'dry_run'     => static fn() => array( 'would_create' => $fields ),
				'execute'     => function () use ( $fields ) {
					$product = new \WC_Product_Simple();
					$this->apply_product_fields( $product, $fields );
					$id = $product->save();
					if ( ! $id ) {
						return new \WP_Error( 'bridgistic_woo_create_failed', 'WooCommerce declined to save the product.', array( 'status' => 500 ) );
					}
					return $this->product_summary( wc_get_product( $id ) );
				},
			)
		);
	}

	public function update_product( WP_REST_Request $request ) {
		$gate = $this->gate( $request, Scopes::WOO_PRODUCTS_WRITE );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$id      = (int) $request['id'];
		$product = wc_get_product( $id );
		if ( ! $product ) {
			return $this->fail( 'bridgistic_woo_product_404', sprintf( 'No WooCommerce product with id %d.', $id ), 404 );
		}

		$fields = $this->product_input( $request );
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}
		if ( ! $fields ) {
			return $this->fail( 'bridgistic_woo_nothing_to_update', 'No updatable product fields were supplied.', 400 );
		}

		$key_id = $this->key_id( $request );

		return Guard::run(
			$request,
			array(
				'action'      => 'woo.product.update',
				'destructive' => true, // overwrites price/stock/description that existed before
				'mutating'    => true,
				'payload'     => array( 'id' => $id, 'fields' => array_keys( $fields ) ),
				'summary'     => sprintf( 'Update WooCommerce product #%d (%s)', $id, $product->get_name() ),
				'snapshot'    => static fn() => Snapshot::create( 'post', array( 'post_id' => $id ), 'pre-update product #' . $id, $key_id ),
				'dry_run'     => function () use ( $product, $fields ) {
					$before = array();
					foreach ( array_keys( $fields ) as $field ) {
						$before[ $field ] = $this->read_product_field( $product, $field );
					}
					return array( 'before' => $before, 'after' => $fields );
				},
				'execute'     => function () use ( $id, $fields ) {
					$fresh = wc_get_product( $id );
					$this->apply_product_fields( $fresh, $fields );
					$fresh->save();
					return $this->product_summary( wc_get_product( $id ) );
				},
			)
		);
	}

	// ---- orders --------------------------------------------------------------

	public function list_orders( WP_REST_Request $request ) {
		$gate = $this->gate( $request, Scopes::WOO_ORDERS_READ );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
		$per_page = min( self::MAX_PER_PAGE, max( 1, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) );

		$args = array(
			'limit'    => $per_page,
			'page'     => $page,
			'paginate' => true,
			'orderby'  => 'date',
			'order'    => 'DESC',
		);

		$status = $this->normalize_statuses( (string) ( $request->get_param( 'status' ) ?? '' ) );
		if ( $status ) {
			$args['status'] = $status;
		}
		$customer_id = (int) ( $request->get_param( 'customer_id' ) ?? 0 );
		if ( $customer_id > 0 ) {
			$args['customer_id'] = $customer_id;
		}
		$after = $this->parse_date( (string) ( $request->get_param( 'after' ) ?? '' ) );
		if ( $after ) {
			$args['date_created'] = '>=' . $after;
		}

		$results = wc_get_orders( $args );

		$items = array();
		foreach ( $results->orders as $order ) {
			$items[] = $this->order_summary( $order );
		}

		return $this->ok(
			array(
				'total'    => (int) $results->total,
				'count'    => count( $items ),
				'page'     => $page,
				'has_more' => $page < (int) $results->max_num_pages,
				'currency' => get_woocommerce_currency(),
				'items'    => $items,
			)
		);
	}

	public function get_order( WP_REST_Request $request ) {
		$gate = $this->gate( $request, Scopes::WOO_ORDERS_READ );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$order = wc_get_order( (int) $request['id'] );
		if ( ! $order ) {
			return $this->fail( 'bridgistic_woo_order_404', sprintf( 'No WooCommerce order with id %d.', (int) $request['id'] ), 404 );
		}

		$data          = $this->order_summary( $order );
		$data['items'] = array();
		foreach ( $order->get_items() as $item ) {
			$data['items'][] = array(
				'name'       => $item->get_name(),
				'product_id' => $item->get_product_id(),
				'quantity'   => $item->get_quantity(),
				'subtotal'   => $item->get_subtotal(),
				'total'      => $item->get_total(),
			);
		}
		$data['shipping_total'] = $order->get_shipping_total();
		$data['tax_total']      = $order->get_total_tax();
		$data['customer_note']  = $order->get_customer_note();
		// Payment method *title* only. The gateway's stored token, transaction
		// id, and any card metadata are deliberately not exposed here — no
		// scope in this plugin grants access to payment credentials.
		$data['payment_method'] = $order->get_payment_method_title();

		return $this->ok( $data );
	}

	public function update_order_status( WP_REST_Request $request ) {
		$gate = $this->gate( $request, Scopes::WOO_ORDERS_WRITE );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$id    = (int) $request['id'];
		$order = wc_get_order( $id );
		if ( ! $order ) {
			return $this->fail( 'bridgistic_woo_order_404', sprintf( 'No WooCommerce order with id %d.', $id ), 404 );
		}

		// Accept both "completed" and WooCommerce's internal "wc-completed".
		$raw_status = (string) ( $request->get_param( 'status' ) ?? '' );
		$requested  = sanitize_key( (string) preg_replace( '/^wc-/', '', $raw_status ) );

		// Validate against the statuses this install actually has, not a
		// hard-coded list — plugins add statuses (wc-partially-shipped and
		// friends), and refusing a legitimate one is as wrong as accepting junk.
		$valid = array_map(
			static fn( string $key ): string => preg_replace( '/^wc-/', '', $key ),
			array_keys( wc_get_order_statuses() )
		);

		if ( '' === $requested || ! in_array( $requested, $valid, true ) ) {
			return $this->fail(
				'bridgistic_woo_bad_status',
				sprintf( 'Unknown order status "%s". This store accepts: %s.', $raw_status, implode( ', ', $valid ) ),
				400
			);
		}

		$previous = $order->get_status();
		if ( $previous === $requested ) {
			return $this->ok(
				array(
					'result' => array(
						'id'        => $id,
						'status'    => $requested,
						'unchanged' => true,
						'note'      => 'The order is already in that status; nothing was changed.',
					),
				)
			);
		}

		$note   = sanitize_textarea_field( (string) ( $request->get_param( 'note' ) ?? '' ) );
		$key_id = $this->key_id( $request );

		return Guard::run(
			$request,
			array(
				'action'      => 'woo.order.status',
				// A status change fires WooCommerce hooks: emails to the
				// customer, stock adjustments, gateway refund flows. It is not
				// reversible by simply setting the status back, so it is always
				// treated as destructive and always snapshotted.
				'destructive' => true,
				'mutating'    => true,
				'payload'     => array( 'id' => $id, 'from' => $previous, 'to' => $requested ),
				'summary'     => sprintf( 'Order #%d: %s → %s', $id, $previous, $requested ),
				'snapshot'    => static fn() => Snapshot::create( 'post', array( 'post_id' => $id ), 'pre-status-change order #' . $id, $key_id ),
				'dry_run'     => static fn() => array(
					'order_id'    => $id,
					'from'        => $previous,
					'to'          => $requested,
					'side_effects' => 'Changing status triggers WooCommerce hooks: customer emails, stock changes, and gateway actions may fire.',
				),
				'execute'     => static function () use ( $id, $requested, $note ) {
					$fresh = wc_get_order( $id );
					$fresh->update_status( $requested, $note ?: 'Changed via Bridgistic.', true );
					return array(
						'id'     => $id,
						'status' => $fresh->get_status(),
					);
				},
			)
		);
	}

	// ---- customers -----------------------------------------------------------

	public function list_customers( WP_REST_Request $request ) {
		$gate = $this->gate( $request, Scopes::WOO_CUSTOMERS_READ );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
		$per_page = min( self::MAX_PER_PAGE, max( 1, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) );
		$search   = trim( (string) ( $request->get_param( 'search' ) ?? '' ) );

		$query = new \WP_User_Query(
			array(
				'role'           => 'customer',
				'number'         => $per_page,
				'paged'          => $page,
				'search'         => '' !== $search ? '*' . $search . '*' : '',
				'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
				'orderby'        => 'registered',
				'order'          => 'DESC',
			)
		);

		$items = array();
		foreach ( $query->get_results() as $user ) {
			$items[] = $this->customer_summary( (int) $user->ID );
		}

		return $this->ok(
			array(
				'total'    => (int) $query->get_total(),
				'count'    => count( $items ),
				'page'     => $page,
				'has_more' => ( $page * $per_page ) < (int) $query->get_total(),
				'items'    => $items,
			)
		);
	}

	public function get_customer( WP_REST_Request $request ) {
		$gate = $this->gate( $request, Scopes::WOO_CUSTOMERS_READ );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$id = (int) $request['id'];
		if ( ! get_userdata( $id ) ) {
			return $this->fail( 'bridgistic_woo_customer_404', sprintf( 'No user with id %d.', $id ), 404 );
		}

		return $this->ok( $this->customer_summary( $id, true ) );
	}

	// ---- analytics -----------------------------------------------------------

	public function inventory_status( WP_REST_Request $request ) {
		$gate = $this->gate( $request, Scopes::WOO_ANALYTICS_READ );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$threshold = max( 0, (int) ( $request->get_param( 'low_stock_threshold' ) ?? get_option( 'woocommerce_notify_low_stock_amount', 2 ) ) );
		$limit     = min( self::MAX_PER_PAGE, max( 1, (int) ( $request->get_param( 'limit' ) ?: 50 ) ) );

		$out_of_stock = wc_get_products(
			array(
				'limit'        => $limit,
				'stock_status' => 'outofstock',
				'status'       => 'publish',
				'return'       => 'objects',
			)
		);

		// Low stock is "managed, in stock, at or below the threshold" — an
		// unmanaged product has no quantity to be low.
		$managed  = wc_get_products(
			array(
				'limit'         => -1,
				'status'        => 'publish',
				'manage_stock'  => true,
				'stock_status'  => 'instock',
				'return'        => 'objects',
			)
		);
		$low      = array();
		$in_stock = 0;
		foreach ( $managed as $product ) {
			$quantity = (int) $product->get_stock_quantity();
			if ( $quantity <= $threshold ) {
				if ( count( $low ) < $limit ) {
					$low[] = array(
						'id'    => $product->get_id(),
						'name'  => $product->get_name(),
						'sku'   => $product->get_sku(),
						'stock' => $quantity,
					);
				}
			} else {
				$in_stock++;
			}
		}

		return $this->ok(
			array(
				'low_stock_threshold' => $threshold,
				'counts'              => array(
					'out_of_stock'     => count( $out_of_stock ),
					'low_stock'        => count( $low ),
					'healthy_in_stock' => $in_stock,
				),
				'out_of_stock'        => array_map( fn( $p ) => array( 'id' => $p->get_id(), 'name' => $p->get_name(), 'sku' => $p->get_sku() ), $out_of_stock ),
				'low_stock'           => $low,
				'truncated'           => count( $out_of_stock ) >= $limit || count( $low ) >= $limit,
			)
		);
	}

	public function sales_summary( WP_REST_Request $request ) {
		$gate = $this->gate( $request, Scopes::WOO_ANALYTICS_READ );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$days  = min( 365, max( 1, (int) ( $request->get_param( 'days' ) ?: 30 ) ) );
		$after = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// Only statuses that represent money actually taken. Pending/cancelled/
		// failed orders would inflate revenue into a number nobody can reconcile.
		$orders = wc_get_orders(
			array(
				'limit'        => -1,
				'status'       => array( 'processing', 'completed', 'on-hold' ),
				'date_created' => '>=' . $after,
				'return'       => 'objects',
			)
		);

		$revenue     = 0.0;
		$by_status   = array();
		$by_product  = array();
		$order_count = 0;

		foreach ( $orders as $order ) {
			$order_count++;
			$revenue += (float) $order->get_total();

			$status               = $order->get_status();
			$by_status[ $status ] = ( $by_status[ $status ] ?? 0 ) + 1;

			foreach ( $order->get_items() as $item ) {
				$name = $item->get_name();
				if ( ! isset( $by_product[ $name ] ) ) {
					$by_product[ $name ] = array( 'name' => $name, 'quantity' => 0, 'revenue' => 0.0 );
				}
				$by_product[ $name ]['quantity'] += (int) $item->get_quantity();
				$by_product[ $name ]['revenue']  += (float) $item->get_total();
			}
		}

		usort( $by_product, static fn( array $a, array $b ): int => $b['revenue'] <=> $a['revenue'] );

		return $this->ok(
			array(
				'period_days'         => $days,
				'since'               => $after,
				'currency'            => get_woocommerce_currency(),
				'counted_statuses'    => array( 'processing', 'completed', 'on-hold' ),
				'order_count'         => $order_count,
				'gross_revenue'       => round( $revenue, 2 ),
				'average_order_value' => $order_count > 0 ? round( $revenue / $order_count, 2 ) : 0.0,
				'orders_by_status'    => $by_status,
				'top_products'        => array_slice( array_values( $by_product ), 0, 10 ),
			)
		);
	}

	// ---- shaping helpers ------------------------------------------------------

	/**
	 * @param \WC_Product $product Product object.
	 * @return array<string,mixed>
	 */
	private function product_summary( $product ): array {
		return array(
			'id'           => $product->get_id(),
			'name'         => $product->get_name(),
			'sku'          => $product->get_sku(),
			'type'         => $product->get_type(),
			'status'       => $product->get_status(),
			'price'        => $product->get_price(),
			'regular_price'=> $product->get_regular_price(),
			'sale_price'   => $product->get_sale_price(),
			'stock_status' => $product->get_stock_status(),
			'stock'        => $product->get_stock_quantity(),
			'manage_stock' => $product->get_manage_stock(),
			'permalink'    => $product->get_permalink(),
		);
	}

	/**
	 * @param \WC_Order $order Order object.
	 * @return array<string,mixed>
	 */
	private function order_summary( $order ): array {
		return array(
			'id'          => $order->get_id(),
			'number'      => $order->get_order_number(),
			'status'      => $order->get_status(),
			'total'       => $order->get_total(),
			'currency'    => $order->get_currency(),
			'created'     => $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : null,
			'customer_id' => $order->get_customer_id(),
			'billing'     => array(
				// Name/email/country are what an assistant needs to answer
				// "who ordered this". Full street address, phone, and anything
				// payment-related stay out of the summary shape entirely.
				'name'    => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				'email'   => $order->get_billing_email(),
				'country' => $order->get_billing_country(),
			),
			'item_count'  => $order->get_item_count(),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function customer_summary( int $user_id, bool $detailed = false ): array {
		$user = get_userdata( $user_id );
		$data = array(
			'id'           => $user_id,
			'name'         => $user ? $user->display_name : '',
			'email'        => $user ? $user->user_email : '',
			'registered'   => $user ? $user->user_registered : null,
		);

		if ( function_exists( 'wc_get_customer_order_count' ) ) {
			$data['order_count'] = (int) wc_get_customer_order_count( $user_id );
		}
		if ( function_exists( 'wc_get_customer_total_spent' ) ) {
			$data['total_spent'] = (float) wc_get_customer_total_spent( $user_id );
		}

		if ( $detailed && class_exists( 'WC_Customer' ) ) {
			$customer                 = new \WC_Customer( $user_id );
			$data['billing_country']  = $customer->get_billing_country();
			$data['billing_city']     = $customer->get_billing_city();
			$data['shipping_country'] = $customer->get_shipping_country();
			$data['last_order_date']  = null;
			$last                     = $customer->get_last_order();
			if ( $last && $last->get_date_created() ) {
				$data['last_order_date'] = $last->get_date_created()->date( 'c' );
			}
		}

		// Never present: user_pass (hashed or otherwise), session tokens,
		// payment tokens, or capability arrays. get_userdata() carries the
		// password hash, so this builds an allowlisted shape rather than
		// filtering a denylist out of the raw object.
		return $data;
	}

	/**
	 * Collect and validate the writable product fields present in the request.
	 *
	 * @return array<string,mixed>|\WP_Error Only the keys actually supplied.
	 */
	private function product_input( WP_REST_Request $request ) {
		$fields = array();

		if ( null !== $request->get_param( 'name' ) ) {
			$fields['name'] = sanitize_text_field( (string) $request->get_param( 'name' ) );
		}
		if ( null !== $request->get_param( 'sku' ) ) {
			$fields['sku'] = sanitize_text_field( (string) $request->get_param( 'sku' ) );
		}
		if ( null !== $request->get_param( 'description' ) ) {
			$fields['description'] = wp_kses_post( (string) $request->get_param( 'description' ) );
		}
		if ( null !== $request->get_param( 'short_description' ) ) {
			$fields['short_description'] = wp_kses_post( (string) $request->get_param( 'short_description' ) );
		}
		if ( null !== $request->get_param( 'status' ) ) {
			$status = sanitize_key( (string) $request->get_param( 'status' ) );
			if ( ! in_array( $status, array( 'draft', 'pending', 'private', 'publish' ), true ) ) {
				return $this->fail( 'bridgistic_woo_bad_product_status', 'Product status must be one of: draft, pending, private, publish.', 400 );
			}
			$fields['status'] = $status;
		}

		foreach ( array( 'regular_price', 'sale_price' ) as $price_field ) {
			if ( null === $request->get_param( $price_field ) ) {
				continue;
			}
			$raw = (string) $request->get_param( $price_field );
			if ( '' === $raw ) {
				$fields[ $price_field ] = '';
				continue;
			}
			if ( ! is_numeric( $raw ) || (float) $raw < 0 ) {
				return $this->fail( 'bridgistic_woo_bad_price', sprintf( '%s must be a non-negative number.', $price_field ), 400 );
			}
			$fields[ $price_field ] = wc_format_decimal( $raw );
		}

		if ( null !== $request->get_param( 'manage_stock' ) ) {
			$fields['manage_stock'] = (bool) $request->get_param( 'manage_stock' );
		}
		if ( null !== $request->get_param( 'stock_quantity' ) ) {
			$quantity = $request->get_param( 'stock_quantity' );
			if ( ! is_numeric( $quantity ) ) {
				return $this->fail( 'bridgistic_woo_bad_stock', 'stock_quantity must be a number.', 400 );
			}
			$fields['stock_quantity'] = (int) $quantity;
		}
		if ( null !== $request->get_param( 'stock_status' ) ) {
			$stock_status = sanitize_key( (string) $request->get_param( 'stock_status' ) );
			if ( ! in_array( $stock_status, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
				return $this->fail( 'bridgistic_woo_bad_stock_status', 'stock_status must be one of: instock, outofstock, onbackorder.', 400 );
			}
			$fields['stock_status'] = $stock_status;
		}

		return $fields;
	}

	/**
	 * @param \WC_Product          $product Product to mutate (not saved here).
	 * @param array<string,mixed>  $fields  Validated fields.
	 */
	private function apply_product_fields( $product, array $fields ): void {
		$setters = array(
			'name'              => 'set_name',
			'sku'               => 'set_sku',
			'description'       => 'set_description',
			'short_description' => 'set_short_description',
			'status'            => 'set_status',
			'regular_price'     => 'set_regular_price',
			'sale_price'        => 'set_sale_price',
			'manage_stock'      => 'set_manage_stock',
			'stock_quantity'    => 'set_stock_quantity',
			'stock_status'      => 'set_stock_status',
		);

		foreach ( $fields as $field => $value ) {
			if ( isset( $setters[ $field ] ) && method_exists( $product, $setters[ $field ] ) ) {
				$product->{$setters[ $field ]}( $value );
			}
		}
	}

	/**
	 * Read one writable field back off a product, for the dry-run "before" view.
	 *
	 * @param \WC_Product $product Product object.
	 * @return mixed
	 */
	private function read_product_field( $product, string $field ) {
		$getter = 'get_' . $field;
		return method_exists( $product, $getter ) ? $product->{$getter}() : null;
	}

	/**
	 * Turn a comma-separated status filter into the list wc_get_orders wants,
	 * dropping anything this store does not define.
	 *
	 * @return array<int,string>
	 */
	private function normalize_statuses( string $raw ): array {
		if ( '' === trim( $raw ) ) {
			return array();
		}
		$valid = array_map(
			static fn( string $key ): string => preg_replace( '/^wc-/', '', $key ),
			array_keys( wc_get_order_statuses() )
		);

		$out = array();
		foreach ( explode( ',', $raw ) as $candidate ) {
			$status = sanitize_key( preg_replace( '/^wc-/', '', trim( $candidate ) ) );
			if ( '' !== $status && in_array( $status, $valid, true ) ) {
				$out[] = $status;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** Accept a Y-m-d or ISO 8601 date, return a MySQL datetime or ''. */
	private function parse_date( string $raw ): string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return '';
		}
		$timestamp = strtotime( $raw );
		return false === $timestamp ? '' : gmdate( 'Y-m-d H:i:s', $timestamp );
	}
}
