<?php
/**
 * REST API plugin class
 *
 * @since             1.13.0
 * @package           TInvWishlist
 */

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/**
 * REST API plugin class
 */
class TInvWL_Includes_API_Wishlist {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected string $namespace = 'wc/v3';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected string $rest_base = 'wishlist';

	/**
	 * Register the routes for wishlist.
	 */
	public function register_routes(): void {
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/get_by_share_key/(?P<share_key>[A-Fa-f0-9]{6})', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'wishlist_get_by_share_key' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/get_by_user/(?P<user_id>[\d]+)', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'wishlist_get_by_user' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/update/(?P<share_key>[A-Fa-f0-9]{6})', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'wishlist_update' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<share_key>[A-Fa-f0-9]{6})/get_products', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'wishlist_get_products' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<share_key>[A-Fa-f0-9]{6})/add_product', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'wishlist_add_product' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/remove_product/(?P<item_id>[\d]+)', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'wishlist_remove_product' ],
			'permission_callback' => '__return_true',
		] );
	}

	/**
	 * Get wishlist data by share key.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function wishlist_get_by_share_key( WP_REST_Request $request ) {
		$share_key = $request->get_param( 'share_key' );

		if ( empty( $share_key ) || ! preg_match( '/^[A-Fa-f0-9]{6}$/', $share_key ) ) {
			return new WP_Error( 'ti_woocommerce_wishlist_api_invalid_share_key', __( 'Invalid wishlist share key.', 'ti-woocommerce-wishlist' ), [ 'status' => 400 ] );
		}

		$wishlist = tinv_wishlist_get( $share_key );

		if ( ! $wishlist ) {
			return new WP_Error( 'ti_woocommerce_wishlist_api_invalid_share_key', __( 'Invalid wishlist share key.', 'ti-woocommerce-wishlist' ), [ 'status' => 400 ] );
		}

		$response = $this->prepare_wishlist_data( $wishlist, 'get_by_share_key', $request->get_params() );

		return rest_ensure_response( $response );
	}

	/**
	 * Get wishlist(s) data by user ID.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function wishlist_get_by_user( WP_REST_Request $request ) {
		$user_id = $request->get_param( 'user_id' );

		if ( empty( $user_id ) || ! $this->user_id_exists( $user_id ) ) {
			return new WP_Error( 'ti_woocommerce_wishlist_api_wishlist_user_not_exists', __( 'WordPress user does not exist.', 'ti-woocommerce-wishlist' ), [ 'status' => 400 ] );
		}

		$wl        = new TInvWL_Wishlist();
		$wishlists = $wl->get_by_user( $user_id );

		if ( ! $wishlists ) {
			return new WP_Error( 'ti_woocommerce_wishlist_api_wishlist_not_found', __( 'No wishlists found for this user.', 'ti-woocommerce-wishlist' ), [ 'status' => 400 ] );
		}

		$response = array_map( function ( $wishlist ) use ( $request ) {
			return $this->prepare_wishlist_data( $wishlist, 'get_by_user', $request->get_params() );
		}, $wishlists );

		return rest_ensure_response( $response );
	}

	/**
	 * Update wishlist data by share key.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function wishlist_update( WP_REST_Request $request ) {
		$result = $this->get_wishlist_by_share_key( $request );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$wishlist  = $result['wishlist'];
		$share_key = $result['share_key'];
		$wl        = $result['wl'];

		$data = array_filter( [
			'title'  => $request->get_param( 'title' ),
			'author' => $request->get_param( 'user_id' ),
		] );

		if ( empty( $data ) || ! ( current_user_can( 'tinvwl_general_settings' ) || $wishlist['author'] === get_current_user_id() ) ) {
			return new WP_Error( 'ti_woocommerce_wishlist_api_wishlist_forbidden', __( 'Update wishlist data failed.', 'ti-woocommerce-wishlist' ), [ 'status' => 403 ] );
		}

		if ( ! $wl->update( $wishlist['ID'], $data ) ) {
			return new WP_Error( 'ti_woocommerce_wishlist_api_wishlist_update_error', __( 'Update wishlist data failed.', 'ti-woocommerce-wishlist' ), [ 'status' => 400 ] );
		}

		$response = $wl->get_by_share_key( $share_key );

		return rest_ensure_response( $this->prepare_wishlist_data( $response, 'update', $request->get_params() ) );
	}

	/**
	 * Get wishlist products by share key.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function wishlist_get_products( WP_REST_Request $request ) {
		$wishlist = $this->get_wishlist_by_share_key( $request );

		if ( is_wp_error( $wishlist ) ) {
			return $wishlist;
		}

		$wlp  = new TInvWL_Product();
		$args = [
			'wishlist_id' => $wishlist['ID'],
			'external'    => false,
			'count'       => $request->get_param( 'count' ),
			'offset'      => $request->get_param( 'offset' ),
			'order'       => $request->get_param( 'order' ),
		];

		$products = $wlp->get( $args );

		$response = array_map( function ( $product ) use ( $request ) {
			return $this->prepare_product_data( $product, 'get_products', $request->get_params() );
		}, $products );

		return rest_ensure_response( apply_filters( 'tinvwl_api_wishlist_get_products_response', $response ) );
	}

	/**
	 * Add product to wishlist by share key.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function wishlist_add_product( WP_REST_Request $request ) {
		$wishlist = $this->get_wishlist_by_share_key( $request );

		if ( is_wp_error( $wishlist ) ) {
			return $wishlist;
		}

		if ( ! current_user_can( 'tinvwl_general_settings' ) && $wishlist['author'] !== get_current_user_id() ) {
			return new WP_Error( 'ti_woocommerce_wishlist_api_wishlist_forbidden', __( 'Add product to wishlist failed.', 'ti-woocommerce-wishlist' ), [ 'status' => 403 ] );
		}

		$wlp  = new TInvWL_Product();
		$args = [
			'wishlist_id'  => $wishlist['ID'],
			'author'       => $wishlist['author'],
			'product_id'   => $request->get_param( 'product_id' ),
			'variation_id' => $request->get_param( 'variation_id' ),
		];
		$meta = $request->get_param( 'meta' ) ?? [];

		$product = $wlp->add_product( $args, $meta );

		if ( ! $product ) {
			return new WP_Error( 'ti_woocommerce_wishlist_api_wishlist_products_not_found', __( 'Add product to wishlist failed.', 'ti-woocommerce-wishlist' ), [ 'status' => 400 ] );
		}

		$products = $wlp->get( [ 'ID' => $product ] );
		$response = array_map( function ( $product ) use ( $request ) {
			return $this->prepare_product_data( $product, 'add_product', $request->get_params() );
		}, $products );

		return rest_ensure_response( $response );
	}

	/**
	 * Remove product by item ID.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function wishlist_remove_product( WP_REST_Request $request ) {
		$item_id = $request->get_param( 'item_id' );

		if ( empty( $item_id ) ) {
			return new WP_Error( 'ti_woocommerce_wishlist_api_invalid_item_id', __( 'Invalid item ID.', 'ti-woocommerce-wishlist' ), [ 'status' => 400 ] );
		}

		$wlp      = new TInvWL_Product();
		$wishlist = $wlp->get_wishlist_by_product_id( $item_id );

		if ( ! $wishlist ) {
			return new WP_Error( 'ti_woocommerce_wishlist_api_wishlist_product_not_found', __( 'Product not found.', 'ti-woocommerce-wishlist' ), [ 'status' => 400 ] );
		}

		if ( ! current_user_can( 'tinvwl_general_settings' ) && $wishlist['author'] !== get_current_user_id() ) {
			return new WP_Error( 'ti_woocommerce_wishlist_api_wishlist_forbidden', __( 'Remove product from wishlist failed.', 'ti-woocommerce-wishlist' ), [ 'status' => 403 ] );
		}

		$result = $wlp->remove( [ 'ID' => $item_id ] );

		if ( ! $result ) {
			return new WP_Error( 'ti_woocommerce_wishlist_api_wishlist_product_not_found', __( 'Product not found.', 'ti-woocommerce-wishlist' ), [ 'status' => 400 ] );
		}

		return rest_ensure_response( __( 'Product removed from a wishlist.', 'ti-woocommerce-wishlist' ) );
	}

	/**
	 * Get wishlist by share key.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return array|WP_Error
	 */
	private function get_wishlist_by_share_key( WP_REST_Request $request ) {
		$share_key = $request->get_param( 'share_key' );

		if ( empty( $share_key ) || ! preg_match( '/^[A-Fa-f0-9]{6}$/', $share_key ) ) {
			return new WP_Error( 'ti_woocommerce_wishlist_api_invalid_share_key', __( 'Invalid wishlist share key.', 'ti-woocommerce-wishlist' ), [ 'status' => 400 ] );
		}

		$wl       = new TInvWL_Wishlist();
		$wishlist = $wl->get_by_share_key( $share_key );

		if ( ! $wishlist ) {
			return new WP_Error( 'ti_woocommerce_wishlist_api_invalid_share_key', __( 'Invalid wishlist share key.', 'ti-woocommerce-wishlist' ), [ 'status' => 400 ] );
		}

		return [ 'wishlist' => $wishlist, 'share_key' => $share_key, 'wl' => $wl ];
	}

	/**
	 * Prepare wishlist data.
	 *
	 * @param array $wishlist Default wishlist data.
	 * @param string $event Event type.
	 * @param array $request original request data.
	 *
	 * @return array
	 */
	public function prepare_wishlist_data( array $wishlist, string $event, array $request ): array {
		return apply_filters( 'tinvwl_api_wishlist_data_response', [
			'id'         => $wishlist['ID'],
			'user_id'    => $wishlist['author'],
			'date_added' => $wishlist['date'],
			'title'      => $wishlist['title'],
			'share_key'  => $wishlist['share_key'],
		], $wishlist, $event, $request );
	}

	/**
	 * Prepare wishlist item data.
	 *
	 * @param array $product Default wishlist item data.
	 * @param string $event Event type.
	 * @param array $request original request data.
	 *
	 * @return array
	 */
	public function prepare_product_data( array $product, string $event, array $request ): array {
		return apply_filters( 'tinvwl_api_product_data_response', [
			'item_id'      => $product['ID'],
			'product_id'   => $product['product_id'],
			'variation_id' => $product['variation_id'],
			'meta'         => $product['meta'],
			'date_added'   => $product['date'],
			'price'        => $product['price'],
			'in_stock'     => $product['in_stock'],
		], $product, $event, $request );
	}

	/**
	 * Check if WordPress user exists.
	 *
	 * @param int $user_id
	 *
	 * @return bool
	 */
	public function user_id_exists( int $user_id ): bool {
		global $wpdb;

		// Check cache:
		if ( wp_cache_get( $user_id, 'users' ) ) {
			return true;
		}

		// Check database:
		$user_exists = $wpdb->get_var( $wpdb->prepare( "SELECT EXISTS (SELECT 1 FROM $wpdb->users WHERE ID = %d)", $user_id ) );

		return (bool) $user_exists;
	}
}
