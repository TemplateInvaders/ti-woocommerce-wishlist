<?php
/**
 * REST API plugin class
 *
 * @since             1.13.0
 * @package           TInvWishlist
 */

// If this file is called directly, abort.
defined('ABSPATH') || exit;

/**
 * REST API plugin class
 */
class TInvWL_Includes_API_Wishlist
{

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
	public function register_routes(): void
	{
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/get_by_share_key/(?P<share_key>[A-Fa-f0-9]{6})',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array($this, 'wishlist_get_by_share_key'),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/get_by_user/(?P<user_id>[\d]+)',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array($this, 'wishlist_get_by_user'),
				'permission_callback' => array($this, 'permission_view_user_wishlist'),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/update/(?P<share_key>[A-Fa-f0-9]{6})',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array($this, 'wishlist_update'),
				'permission_callback' => array($this, 'permission_modify_wishlist'),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<share_key>[A-Fa-f0-9]{6})/get_products',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array($this, 'wishlist_get_products'),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<share_key>[A-Fa-f0-9]{6})/add_product',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array($this, 'wishlist_add_product'),
				'permission_callback' => array($this, 'permission_modify_wishlist'),
			)
		);

		// Hardened: share_key required and proper HTTP verb.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<share_key>[A-Fa-f0-9]{6})/remove_product/(?P<item_id>[\d]+)',
			array(
				'methods' => WP_REST_Server::DELETABLE,
				'callback' => array($this, 'wishlist_remove_product'),
				'permission_callback' => array($this, 'permission_modify_wishlist'),
			)
		);
	}

	/**
	 * Get wishlist data by share key.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function wishlist_get_by_share_key(WP_REST_Request $request)
	{
		$share_key = $request->get_param('share_key');

		if (empty($share_key) || !preg_match('/^[A-Fa-f0-9]{6}$/', $share_key)) {
			return new WP_Error(
				'ti_woocommerce_wishlist_api_invalid_share_key',
				__('Invalid wishlist share key.', 'ti-woocommerce-wishlist'),
				array('status' => 400)
			);
		}

		$wishlist = tinv_wishlist_get($share_key);

		if (!$wishlist) {
			return new WP_Error(
				'ti_woocommerce_wishlist_api_invalid_share_key',
				__('Invalid wishlist share key.', 'ti-woocommerce-wishlist'),
				array('status' => 400)
			);
		}

		$response = $this->prepare_wishlist_data($wishlist, 'get_by_share_key', $request->get_params());

		return rest_ensure_response($response);
	}

	/**
	 * Get wishlist(s) data by user ID.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function wishlist_get_by_user(WP_REST_Request $request)
	{
		$user_id = absint($request->get_param('user_id'));

		if ($user_id && !$this->user_id_exists($user_id)) {
			// Don't expose which user_ids exist by not returning an error that says that.
			return new WP_Error(
				'ti_woocommerce_wishlist_api_wishlist_not_found',
				__('No wishlists found for this user.', 'ti-woocommerce-wishlist'),
				array('status' => 400)
			);
		}

		$wl = new TInvWL_Wishlist();

		if (0 === $user_id) {
			$wishlists = array();
			$wishlists[] = $wl->add_sharekey_default();
		} else {
			$wishlists = $wl->get_by_user($user_id);

			if (!$wishlists) {
				return new WP_Error(
					'ti_woocommerce_wishlist_api_wishlist_not_found',
					__('No wishlists found for this user.', 'ti-woocommerce-wishlist'),
					array('status' => 400)
				);
			}
		}

		$response = array_map(
			function ($wishlist) use ($request) {
				return $this->prepare_wishlist_data($wishlist, 'get_by_user', $request->get_params());
			},
			$wishlists
		);

		return rest_ensure_response($response);
	}

	/**
	 * Update wishlist data by share key.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function wishlist_update(WP_REST_Request $request)
	{
		$result = $this->get_wishlist_by_share_key($request);

		if (is_wp_error($result)) {
			return $result;
		}

		$wishlist = $result['wishlist'];
		$share_key = $result['share_key'];
		$wl = $result['wl'];

		$data = array();
		$title = $request->get_param('title');
		if (null !== $title) {
			$data['title'] = sanitize_text_field($title);
		}

		// Only admins/managers can change author via API.
		if (current_user_can('tinvwl_general_settings') || current_user_can('manage_woocommerce') || current_user_can('manage_options')) {
			$user_param = $request->get_param('user_id');
			if (null !== $user_param) {
				$data['author'] = absint($user_param);
			}
		}

		// Defense-in-depth: for non-guest lists ensure the caller is the owner or has elevated caps.
		if (!(current_user_can('tinvwl_general_settings') || current_user_can('manage_woocommerce') || current_user_can('manage_options'))) {
			if (!empty($wishlist['author']) && (int)$wishlist['author'] !== get_current_user_id()) {
				return new WP_Error(
					'ti_woocommerce_wishlist_api_wishlist_forbidden',
					__('Update wishlist data failed.', 'ti-woocommerce-wishlist'),
					array('status' => 403)
				);
			}
		}

		if (empty($data)) {
			return new WP_Error(
				'ti_woocommerce_wishlist_api_wishlist_update_error',
				__('Update wishlist data failed.', 'ti-woocommerce-wishlist'),
				array('status' => 400)
			);
		}

		if (!$wl->update($wishlist['ID'], $data)) {
			return new WP_Error(
				'ti_woocommerce_wishlist_api_wishlist_update_error',
				__('Update wishlist data failed.', 'ti-woocommerce-wishlist'),
				array('status' => 400)
			);
		}

		$response = $wl->get_by_share_key($share_key);

		return rest_ensure_response(
			$this->prepare_wishlist_data(
				$response,
				'update',
				$request->get_params()
			)
		);
	}

	/**
	 * Get wishlist products by share key.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function wishlist_get_products(WP_REST_Request $request)
	{
		$wishlist = $this->get_wishlist_by_share_key($request);

		if (is_wp_error($wishlist)) {
			return $wishlist;
		}

		$wlp = new TInvWL_Product();
		$args = array(
			'wishlist_id' => $wishlist['wishlist']['ID'],
			'external' => false,
		);

		// Proper sanitizing.
		if (null !== ($count = $request->get_param('count'))) {
			$args['count'] = absint($count);
		}
		if (null !== ($offset = $request->get_param('offset'))) {
			$args['offset'] = absint($offset);
		}
		// The order value is passed directly to the DB so it needs to be protected against SQL injections.
		if (null !== ($order = $request->get_param('order'))) {
			$order = strtoupper($order);
			$valid_order_values = array('ASC', 'DESC');
			if (in_array($order, $valid_order_values, true)) {
				$args['order'] = $order;
			}
		}

		$products = $wlp->get($args);

		$response = array_map(
			function ($product) use ($request) {
				return $this->prepare_product_data($product, 'get_products', $request->get_params());
			},
			$products
		);

		return rest_ensure_response(apply_filters('tinvwl_api_wishlist_get_products_response', $response));
	}

	/**
	 * Add product to wishlist by share key.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function wishlist_add_product(WP_REST_Request $request)
	{
		$wishlist = $this->get_wishlist_by_share_key($request);

		if (is_wp_error($wishlist)) {
			return $wishlist;
		}

		$wlp = new TInvWL_Product();
		$args = array(
			'wishlist_id' => $wishlist['wishlist']['ID'],
			'author' => $wishlist['wishlist']['author'],
			'product_id' => absint($request->get_param('product_id')),
			'variation_id' => absint($request->get_param('variation_id')),
		);

		$meta = $request->get_param('meta');
		if (!is_array($meta)) {
			$meta = array();
		}

		$product = $wlp->add_product($args, $meta);

		if (!$product) {
			return new WP_Error(
				'ti_woocommerce_wishlist_api_wishlist_products_not_found',
				__('Add product to wishlist failed.', 'ti-woocommerce-wishlist'),
				array('status' => 400)
			);
		}

		$products = $wlp->get(
			array(
				'ID' => $product,
			)
		);
		$response = array_map(
			function ($product) use ($request) {
				return $this->prepare_product_data($product, 'add_product', $request->get_params());
			},
			$products
		);

		return rest_ensure_response($response);
	}

	/**
	 * Remove product by item ID (bound to a specific wishlist via share_key).
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function wishlist_remove_product(WP_REST_Request $request)
	{
		// Ensure the wishlist exists (permission handled by permission_modify_wishlist).
		$result = $this->get_wishlist_by_share_key($request);
		if (is_wp_error($result)) {
			return $result;
		}

		$wishlist = $result['wishlist'];
		$item_id = absint($request->get_param('item_id'));

		if (empty($item_id)) {
			return new WP_Error(
				'ti_woocommerce_wishlist_api_invalid_item_id',
				__('Invalid item ID.', 'ti-woocommerce-wishlist'),
				array('status' => 400)
			);
		}

		$wlp = new TInvWL_Product();

		// Verify that the item belongs to the same wishlist referenced by share_key.
		$item_wishlist = $wlp->get_wishlist_by_product_id($item_id);
		if (!$item_wishlist || (int)$item_wishlist['ID'] !== (int)$wishlist['ID']) {
			return new WP_Error(
				'ti_woocommerce_wishlist_api_wishlist_product_not_found',
				__('Product not found.', 'ti-woocommerce-wishlist'),
				array('status' => 400)
			);
		}

		$result = $wlp->remove(
			array(
				'ID' => $item_id,
			)
		);

		if (!$result) {
			return new WP_Error(
				'ti_woocommerce_wishlist_api_wishlist_product_not_found',
				__('Product not found.', 'ti-woocommerce-wishlist'),
				array('status' => 400)
			);
		}

		return rest_ensure_response(__('Product removed from a wishlist.', 'ti-woocommerce-wishlist'));
	}

	/**
	 * Get wishlist by share key.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return array|WP_Error
	 */
	private function get_wishlist_by_share_key(WP_REST_Request $request)
	{
		$share_key = $request->get_param('share_key');

		if (empty($share_key) || !preg_match('/^[A-Fa-f0-9]{6}$/', $share_key)) {
			return new WP_Error(
				'ti_woocommerce_wishlist_api_invalid_share_key',
				__('Invalid wishlist share key.', 'ti-woocommerce-wishlist'),
				array('status' => 400)
			);
		}

		$wl = new TInvWL_Wishlist();
		$wishlist = $wl->get_by_share_key($share_key);

		if (!$wishlist) {
			return new WP_Error(
				'ti_woocommerce_wishlist_api_invalid_share_key',
				__('Invalid wishlist share key.', 'ti-woocommerce-wishlist'),
				array('status' => 400)
			);
		}

		return array(
			'wishlist' => $wishlist,
			'share_key' => $share_key,
			'wl' => $wl,
		);
	}

	/**
	 * Prepare wishlist data.
	 *
	 * @param array $wishlist Default wishlist data.
	 * @param string $event Event type.
	 * @param array $request Original request data.
	 *
	 * @return array
	 */
	public function prepare_wishlist_data(array $wishlist, string $event, array $request): array
	{
		return apply_filters(
			'tinvwl_api_wishlist_data_response',
			array(
				'id' => $wishlist['ID'],
				'user_id' => $wishlist['author'],
				'date_added' => $wishlist['date'],
				'title' => $wishlist['title'],
				'share_key' => $wishlist['share_key'],
			),
			$wishlist,
			$event,
			$request
		);
	}

	/**
	 * Prepare wishlist item data.
	 *
	 * @param array $product Default wishlist item data.
	 * @param string $event Event type.
	 * @param array $request Original request data.
	 *
	 * @return array
	 */
	public function prepare_product_data(array $product, string $event, array $request): array
	{
		return apply_filters(
			'tinvwl_api_product_data_response',
			array(
				'item_id' => $product['ID'],
				'product_id' => $product['product_id'],
				'variation_id' => $product['variation_id'],
				'meta' => $product['meta'],
				'date_added' => $product['date'],
				'price' => $product['price'],
				'in_stock' => $product['in_stock'],
			),
			$product,
			$event,
			$request
		);
	}

	/**
	 * Permission callback for viewing wishlists by user ID.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return bool|WP_Error
	 */
	public function permission_view_user_wishlist(WP_REST_Request $request)
	{
		$user_id = absint($request->get_param('user_id'));

		if (!is_user_logged_in()) {
			return new WP_Error(
				'ti_woocommerce_wishlist_rest_unauthorized',
				__('Authentication is required.', 'ti-woocommerce-wishlist'),
				array('status' => 401)
			);
		}

		$current_user_id = get_current_user_id();

		if (
			$user_id === $current_user_id
			|| current_user_can('tinvwl_general_settings')
			|| current_user_can('manage_woocommerce')
			|| current_user_can('manage_options')
		) {
			return true;
		}

		return new WP_Error(
			'ti_woocommerce_wishlist_rest_forbidden',
			__('You are not allowed to view this wishlist.', 'ti-woocommerce-wishlist'),
			array('status' => 403)
		);
	}

	/**
	 * Permission callback for modifying a wishlist (write operations).
	 *
	 * For logged-in wishlists:
	 * - Requires elevated capability or ownership.
	 *
	 * For guest wishlists (author = 0):
	 * - Requires valid share_key and a valid nonce in "tinvwl_nonce" param:
	 *   wp_verify_nonce( $nonce, 'tinvwl_wishlist_' . $share_key ).
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return bool|WP_Error
	 */
	public function permission_modify_wishlist(WP_REST_Request $request)
	{
		// Elevated capabilities bypass further checks.
		if (current_user_can('tinvwl_general_settings') || current_user_can('manage_woocommerce') || current_user_can('manage_options')) {
			return true;
		}

		$result = $this->get_wishlist_by_share_key($request);
		if (is_wp_error($result)) {
			return $result;
		}

		$wishlist = $result['wishlist'];
		$share_key = $result['share_key'];

		// Guest wishlist: allow modification if share_key is valid and nonce matches.
		if (empty($wishlist['author'])) {
			$nonce = $request->get_param('tinvwl_nonce');
			if (empty($nonce) || !is_string($nonce) || !wp_verify_nonce($nonce, 'tinvwl_wishlist_' . $share_key)) {
				return new WP_Error(
					'ti_woocommerce_wishlist_rest_forbidden',
					__('You are not allowed to modify this wishlist.', 'ti-woocommerce-wishlist'),
					array('status' => 403)
				);
			}

			return true;
		}

		// Non-guest wishlist: require logged-in owner.
		if (!is_user_logged_in()) {
			return new WP_Error(
				'ti_woocommerce_wishlist_rest_unauthorized',
				__('Authentication is required.', 'ti-woocommerce-wishlist'),
				array('status' => 401)
			);
		}

		if ((int)$wishlist['author'] === get_current_user_id()) {
			return true;
		}

		return new WP_Error(
			'ti_woocommerce_wishlist_rest_forbidden',
			__('You are not allowed to modify this wishlist.', 'ti-woocommerce-wishlist'),
			array('status' => 403)
		);
	}

	/**
	 * Check if WordPress user exists.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool
	 */
	public function user_id_exists(int $user_id): bool
	{
		global $wpdb;
		$user_id = absint($user_id);

		// Check cache.
		if (wp_cache_get($user_id, 'users')) {
			return true;
		}

		// Check database.
		$user_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT EXISTS (SELECT 1 FROM $wpdb->users WHERE ID = %d)",
				$user_id
			) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return (bool)$user_exists;
	}
}
