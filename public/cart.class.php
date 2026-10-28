<?php
/**
 * Cart action for wishlists
 *
 * @since             1.0.0
 * @package           TInvWishlist\Public
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Cart action for wishlists
 */
class TInvWL_Public_Cart {

	/**
	 * Plugin name
	 *
	 * @var string
	 */
	static $_name;

	/**
	 * Default post object.
	 *
	 * @var array
	 */
	static $_request;

	/**
	 * Default post object.
	 *
	 * @var array
	 */
	static $_post;

	/** Request-local frames preserve nested cart preparations and their cleanup owners. */
	private static $request_frames = array();
	private static $request_sequence = 0;
	/**
	 * This class
	 *
	 * @var \TInvWL_Public_Cart
	 */
	protected static $_instance = null;

	/**
	 * Get this class object
	 *
	 * @param string $plugin_name Plugin name.
	 *
	 * @return \TInvWL_Public_Cart
	 */
	public static function instance( $plugin_name = TINVWL_PREFIX ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $plugin_name );
		}

		return self::$_instance;
	}

	/**
	 * Constructor
	 *
	 * @param string $plugin_name Plugin name.
	 */
	function __construct( $plugin_name ) {
		self::$_name = $plugin_name;
		$this->define_hooks();
	}

	/**
	 * Define hooks
	 */
	function define_hooks() {
		if ( version_compare( WC_VERSION, '3.7.0', '<' ) ) {
			add_action( 'woocommerce_before_cart_item_quantity_zero', array( __CLASS__, 'archive_item_data' ), 10, 2 );
		} else {
			add_action( 'woocommerce_remove_cart_item', array( __CLASS__, 'archive_item_data' ), 10, 2 );
		}
		if ( version_compare( WC_VERSION, '3.9.0', '<' ) ) {
			add_action( 'woocommerce_cart_emptied', array( __CLASS__, 'remove_item_data' ) );
		} else {
			add_action( 'woocommerce_cart_emptied', array( __CLASS__, 'remove_item_data_cart_session' ) );
		}

		add_action( 'woocommerce_cart_item_restored', array( __CLASS__, 'restore_item_data' ), 10, 2 );
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'consume_product_source' ), 100, 6 );

		add_action( 'woocommerce_checkout_create_order', array( $this, 'add_order_item_meta' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( $this, 'add_order_item_meta' ) );

		add_action( 'woocommerce_checkout_order_created', array( $this, 'purchased_items' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'purchased_items' ) );

		add_action( 'woocommerce_order_status_changed', array( $this, 'order_status_analytics' ), 9, 3 );
	}

	/** Remember only a signed, owned parent product link; shared and old links stay analytics-only. */
	public static function remember_product_source( $item, $wishlist ) {
		$nonce = isset( $_GET['tiwps'] ) && is_string( $_GET['tiwps'] ) ? wp_unslash( $_GET['tiwps'] ) : '';
		$wl = new TInvWL_Wishlist( self::$_name );
		if ( ! WC()->session || ! tinv_get_option( 'processing', 'autoremove' ) || 'tinvwl-addcart' !== tinv_get_option( 'processing', 'autoremove_status' )
			|| ! $wl->is_owner( $wishlist ) || ! wp_verify_nonce( $nonce, 'tinvwl_product_source_' . absint( $item['ID'] ?? 0 ) )
			|| ! empty( $item['variation_id'] ) || empty( $item['data'] )
			|| ! $item['data']->is_type( array( 'variable', 'variable-subscription' ) ) ) {
			return false;
		}
		$model = new TInvWL_Product( $wishlist, self::$_name, true );
		$saved = $model->get_parent_source( $item['ID'], $item['product_id'] );
		if ( ! $saved ) { return false; }
		$saved['meta'] = empty( $saved['formdata'] ) ? array() : json_decode( $saved['formdata'], true );
		$operation = TInvWL_Public_CartDebit::prepare( $wishlist, $saved, 'remove' );
		if ( ! $operation ) { return false; }
		$sources = (array) WC()->session->get( 'tinvwl_product_sources', array() );
		foreach ( $sources as $key => $source ) {
			if ( ! is_array( $source ) || (int) ( $source['expires'] ?? 0 ) < time() ) { unset( $sources[ $key ] ); }
		}
		$sources[ absint( $item['data']->get_id() ) ] = array( 'item_id' => absint( $item['ID'] ), 'product_id' => absint( $item['product_id'] ), 'share_key' => $wishlist['share_key'], 'expires' => time() + HOUR_IN_SECONDS, 'operation_key' => $operation, 'actor' => get_current_user_id() );
		// A session needs only a small set of recent product-page sources.
		WC()->session->set( 'tinvwl_product_sources', array_slice( $sources, -20, null, true ) );
		if ( ! headers_sent() && is_callable( array( WC()->session, 'set_customer_session_cookie' ) ) ) {
			WC()->session->set_customer_session_cookie( true );
		}
		return true;
	}

	/** A successful native add consumes its exact owned source, never another saved variation/configuration. */
	public static function consume_product_source( $cart_item_key, $product_id, $quantity, $variation_id = 0, $variation = array(), $cart_item_data = array() ) {
		$quantity = filter_var( $quantity, FILTER_VALIDATE_FLOAT );
		if ( self::$request_frames || ! WC()->session || ! WC()->cart || ! $variation_id || false === $quantity || ! is_finite( $quantity ) || $quantity <= 0 ) {
			return;
		}
		$sources = (array) WC()->session->get( 'tinvwl_product_sources', array() );
		$source = $sources[ $product_id ] ?? null;
		if ( ! is_array( $source ) ) { return; }
		$item = WC()->cart->get_cart_item( $cart_item_key );
		$variation_product = wc_get_product( $variation_id );
		if ( ! $item || (int) $item['product_id'] !== (int) $product_id || (int) $item['variation_id'] !== (int) $variation_id
			|| ! ( $variation_product instanceof WC_Product_Variation ) || (int) $variation_product->get_parent_id() !== (int) $product_id ) {
			return;
		}
		unset( $sources[ $product_id ] );
		WC()->session->set( 'tinvwl_product_sources', $sources );
		if ( (int) ( $source['expires'] ?? 0 ) < time() || ! tinv_get_option( 'processing', 'autoremove' ) || 'tinvwl-addcart' !== tinv_get_option( 'processing', 'autoremove_status' ) ) {
			return;
		}
		$operation = $source['operation_key'] ?? '';
		if ( ! $operation || ( ! empty( $source['actor'] ) && (int) $source['actor'] !== get_current_user_id() ) ) { return; }
		// Retain success independently of the one-use click token when confirmation is temporarily unavailable.
		TInvWL_Public_CartDebit::remember_success( $operation, $quantity );
		if ( TInvWL_Public_CartDebit::confirm( $operation, $quantity ) ) {
			TInvWL_Public_CartDebit::forget_success( $operation );
			TInvWL_Public_CartDebit::process( $operation );
		}
	}

	/**
	 * Add product to cart from wishlist
	 *
	 * @param array $wishlist Wishlist object.
	 * @param integer $wl_product Wishlist product id.
	 * @param int|float $wl_quantity Product quantity.
	 *
	 * @return array|boolean
	 */
	public static function add( $wishlist = null, $wl_product = 0, $wl_quantity = 1 ) {
		if ( empty( $wishlist ) ) {
			$wishlist = tinv_wishlist_get();
		}
		$wl = new TInvWL_Wishlist();
		if ( ! $wl->can_view( $wishlist ) ) {
			return self::unavailable_cart_item( $wl_product );
		}
		$wishlist['is_owner'] = $wl->is_owner( $wishlist );
		$wlp = null;
		if ( 0 === $wishlist['ID'] ) {
			$wlp = TInvWL_Product_Local::instance( TINVWL_PREFIX, true );
		} else {
			$wlp = new TInvWL_Product( $wishlist, TINVWL_PREFIX, true );
		}
		$product = $wlp->get_wishlist( array( 'ID' => $wl_product ) );
		$product = array_shift( $product );
		if ( empty( $product ) ) {
			return self::unavailable_cart_item( $wl_product );
		}
		if ( empty( $product['data'] ) ) {
			return self::unavailable_cart_item( $wl_product );
		}
		$product['action'] = 'add';
		$product           = apply_filters( 'tinvwl_addproduct_tocart', $product );
		if ( ! apply_filters( 'tinvwl_wishlist_item_configuration_available', true, $product, $product['data'] ) ) {
			return array( 'product' => $product['data'], 'quantity' => $wl_quantity, 'error_code' => 'unavailable_configuration' );
		}
		$request_product = $product;
		// Native request encoding must not change saved configuration or cart attribution.
		$request_product['meta'] = apply_filters( 'tinvwl_addproduct_tocart_prepare_meta', $product['meta'], $product );
		try {
			self::prepare_post( $request_product );

			$use_original_id = false;

			if ( function_exists( 'pll_is_translated_post_type' ) ) {
				$use_original_id = true;
			}

			if ( function_exists( 'wpml_get_current_language' ) ) {

				global $sitepress;

				if ( $sitepress && $sitepress instanceof SitePress ) {
					$wpml_settings = $sitepress->get_settings();
					if ( isset( $wpml_settings['custom_posts_sync_option'] ) && isset( $wpml_settings['custom_posts_sync_option']['product'] ) && in_array( $wpml_settings['custom_posts_sync_option']['product'], array(
							1,
							2,
						) ) ) {
						$use_original_id = true;
					}
				}
			}

			$product_id   = apply_filters( 'woocommerce_add_to_cart_product_id', apply_filters( 'wpml_object_id', absint( $product['product_id'] ), 'product', $use_original_id ) );
			$quantity     = empty( $wl_quantity ) ? 1 : apply_filters( 'tinvwl_wishlist_product_add_cart_qty', wc_stock_amount( $wl_quantity ), $product );
			$variation_id = absint( apply_filters( 'wpml_object_id', $product['variation_id'], 'product_variation', $use_original_id ) );
			$variations = array();
			if ( ! empty( $product['variation_id'] ) ) {
				// Resolve the selected variation once. Its attributes and parent must describe
				// that same native variation, including translated wishlist rows.
				$variation_product = wc_get_product( $variation_id );
				if ( ! ( $variation_product instanceof WC_Product_Variation ) || (int) $variation_product->get_parent_id() !== (int) $product_id ) {
					return array( 'product' => $product['data'], 'quantity' => $quantity, 'error_code' => 'not_purchasable' );
				}
				$variations = wc_get_product_variation_attributes( $variation_id );
			}

			if ( ! empty( $variation_id ) && is_array( $variations ) ) {
				foreach ( $variations as $name => $value ) {
					if ( '' === $value ) {
						// Could be any value that saved to a custom meta.
						if ( array_key_exists( 'meta', $product ) && array_key_exists( $name, $product['meta'] ) ) {
							$variations[ $name ] = $product['meta'][ $name ];
						}
					}
				}
			}

			$cart_errors       = self::add_to_cart_errors( $product['data'], $quantity, true );
			$passed_validation = ! isset( $cart_errors['error_code'] );
			$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', $passed_validation, $product_id, $quantity, $variation_id, $variations );
			if ( $passed_validation ) {
				$remove_saved_item = tinv_get_option( 'processing', 'autoremove' ) && ( ( 'private' !== $wishlist['status'] && tinv_get_option( 'processing', 'autoremove_anyone' ) ) || $wishlist['is_owner'] ) && 'tinvwl-addcart' === tinv_get_option( 'processing', 'autoremove_status' );
				$saved_source = $remove_saved_item ? self::saved_cart_source( $wlp, $product['ID'] ) : null;
				if ( $remove_saved_item && ! $saved_source ) { return self::unavailable_cart_item( $product['ID'] ); }
				$cart_item_data = apply_filters( 'tinvwl_addproduct_tocart_prepare_cart_data', $request_product['meta'], $product );
				$debit_operation = $remove_saved_item ? TInvWL_Public_CartDebit::prepare( $wishlist, $saved_source, 'remove' ) : null;
				if ( $remove_saved_item && ! $debit_operation ) { return array( 'product' => $product['data'], 'quantity' => $quantity, 'error_code' => 'wishlist_update_unavailable' ); }
				$cart_quantities = wp_list_pluck( WC()->cart->get_cart(), 'quantity' );
				$cart_item_key   = self::add_native_cart_item( $product_id, $quantity, $variation_id, $variations, $cart_item_data, $cart_errors );
				if ( $cart_item_key ) {
					// Native quantity filters and sold-individually rules may adjust the request.
					// Attribute only the amount actually added to this new or existing cart row.
					$cart_item = WC()->cart->get_cart_item( $cart_item_key );
					$observed = isset( $cart_item['quantity'] ) && is_numeric( $cart_item['quantity'] ) ? (float) $cart_item['quantity'] - (float) ( $cart_quantities[ $cart_item_key ] ?? 0 ) : null;
					$actual = $cart_errors['native_quantity'] ?? $observed;
					$confirmed_quantity = is_numeric( $actual ) && is_finite( (float) $actual ) && $actual > 0;
					if ( $confirmed_quantity ) { $quantity = (float) $actual; }
					if ( $debit_operation ) {
						if ( $confirmed_quantity ) { TInvWL_Public_CartDebit::remember_success( $debit_operation, $quantity ); }
						if ( $confirmed_quantity && TInvWL_Public_CartDebit::confirm( $debit_operation, $quantity ) ) { TInvWL_Public_CartDebit::forget_success( $debit_operation ); TInvWL_Public_CartDebit::process( $debit_operation ); }
					}

					/* Run a 3rd party code when product added to a cart from a wishlist.
					 *
					 * @param string $cart_item_key cart product unique key.
					 * @param int|float $quantity Product quantity actually added.
					 * @param array $product product data.
					 * */
					do_action( 'tinvwl_product_added_to_cart', $cart_item_key, $quantity, $product );
					$wla = new TInvWL_Analytics( $wishlist, self::$_name );
					$wla->cart_product( $product_id, $variation_id );
					self::set_item_data( $cart_item_key, $wishlist['share_key'], $quantity );
					self::set_item_meta( $cart_item_key, $product['meta'] );

					return array(
						'product'       => $product['data'],
						'quantity'      => $quantity,
						'cart_item_key' => $cart_item_key
					);
				}
				if ( $debit_operation ) { TInvWL_Public_CartDebit::cancel( $debit_operation ); }
			}
			$error_code = $cart_errors['error_code'] ?? 'default';

			return array( 'product' => $product['data'], 'quantity' => $quantity, 'error_code' => $error_code );
		} finally {
			self::unprepare_post( $request_product );
		}

	}

	/** The cart can use a translation; storage mutations retain the saved row's identity. */
	private static function saved_cart_source( $model, $item_id ) {
		global $wpdb;
		$table = $wpdb->prefix . self::$_name . '_items';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table` WHERE ID = %d AND wishlist_id = %d", $item_id, $model->wishlist_id() ), ARRAY_A );
		if ( $wpdb->last_error || ! $row ) { return false; }
		$row['meta'] = empty( $row['formdata'] ) ? array() : json_decode( $row['formdata'], true );
		return is_array( $row['meta'] ) ? $row : false;
	}

	/** Let WooCommerce validate the fully prepared configuration and retain its error category. */
	private static function add_native_cart_item( $product_id, $quantity, $variation_id, $variations, $cart_item_data, &$errors ) {
		// These are request controls, not native cart configuration. Raw metadata is retained separately.
		unset( $cart_item_data['tinvwl-hidden-fields'], $cart_item_data['add-to-cart'], $cart_item_data['product_id'], $cart_item_data['variation_id'], $cart_item_data['quantity'] );
		$native_id = $variation_id ?: $product_id;
		$capture = static function ( $message, $product ) use ( &$errors, $native_id ) {
			if ( $product instanceof WC_Product && (int) $product->get_id() === (int) $native_id ) {
				$errors['error_code'] = 'woocommerce_cart_product_cannot_add_another_message' === current_filter() ? 'sold_individually' : 'more_than_stock';
			}
			return $message;
		};
		$hooks = array( 'woocommerce_cart_product_cannot_add_another_message', 'woocommerce_cart_product_not_enough_stock_message', 'woocommerce_cart_product_not_enough_stock_already_in_cart_message' );
		$depth = count( array_keys( (array) ( $GLOBALS['wp_current_filter'] ?? array() ), 'woocommerce_add_to_cart', true ) );
		$added = static function ( $key, $added_product, $added_quantity, $added_variation ) use ( &$errors, $product_id, $variation_id, $depth ) {
			$current_depth = count( array_keys( (array) ( $GLOBALS['wp_current_filter'] ?? array() ), 'woocommerce_add_to_cart', true ) );
			if ( $current_depth === $depth + 1 && (int) $product_id === (int) $added_product && (int) $variation_id === (int) $added_variation
				&& is_string( $key ) && '' !== $key && is_numeric( $added_quantity ) && is_finite( (float) $added_quantity ) && $added_quantity > 0 ) {
				$errors['native_quantity'] = (float) $added_quantity;
			}
		};
		add_action( 'woocommerce_add_to_cart', $added, PHP_INT_MIN, 4 );
		foreach ( $hooks as $hook ) {
			add_filter( $hook, $capture, PHP_INT_MAX, 2 );
		}
		try {
			return WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variations, $cart_item_data );
		} finally {
			remove_action( 'woocommerce_add_to_cart', $added, PHP_INT_MIN );
			foreach ( $hooks as $hook ) {
				remove_filter( $hook, $capture, PHP_INT_MAX );
			}
		}
	}

	/**
	 * Prepare _POST data
	 *
	 * @param array $product Wishlist Product.
	 */
	public static function prepare_post( $product ) {
		$token = ++self::$request_sequence;
		self::$request_frames[] = array(
			'token' => $token,
			'post' => $_POST,
			'request' => $_REQUEST,
			'previous_post' => self::$_post,
			'previous_request' => self::$_request,
		);
		self::$_post = $_POST;
		self::$_request = $_REQUEST;
		do_action( 'tinvwl_before_prepare_post', $product, $token );
		if ( array_key_exists( 'meta', $product ) && ! empty( $product['meta'] ) ) {
			$_POST = $product['meta'];
			$_REQUEST = $product['meta'];
		} else {
			$_POST = array();
			$_REQUEST = array();
		}
	}

	/** Restore this preparation even when its observer or a nested cart operation throws. */
	public static function unprepare_post( $product ) {
		$frame = array_pop( self::$request_frames );
		if ( ! $frame ) {
			$frame = array( 'token' => 0, 'post' => self::$_post, 'request' => self::$_request, 'previous_post' => self::$_post, 'previous_request' => self::$_request );
		}
		try {
			do_action( 'tinvwl_before_unprepare_post', $product, $frame['post'], $frame['request'], $frame['token'] );
		} finally {
			$_POST = $frame['post'];
			$_REQUEST = $frame['request'];
			self::$_post = $frame['previous_post'];
			self::$_request = $frame['previous_request'];
			do_action( 'tinvwl_after_unprepare_post', $product, $frame['post'], $frame['request'], $frame['token'] );
		}
	}

	/**
	 * Get product added from wishlist
	 *
	 * @param string $cart_item_key Cart product key.
	 *
	 * @return int
	 */
	public static function get_item_data( $cart_item_key ) {
		$data = (array) WC()->session->get( 'tinvwl_wishlist_cart', array() );
		if ( empty( $data[ $cart_item_key ] ) ) {
			$data[ $cart_item_key ] = array();
		}

		return $data[ $cart_item_key ];
	}

	/**
	 * Set product added from wishlist
	 *
	 * @param string $cart_item_key Cart product key.
	 * @param string $wishlist_sharekey Wishlist sharekey.
	 * @param int|float $quantity Product quantity.
	 *
	 * @return boolean
	 */
	public static function set_item_data( $cart_item_key, $wishlist_sharekey, $quantity = 1 ) {
		$data = (array) WC()->session->get( 'tinvwl_wishlist_cart', array() );
		if ( empty( $data[ $cart_item_key ] ) ) {
			$data[ $cart_item_key ] = array();
		}
		if ( array_key_exists( $wishlist_sharekey, $data[ $cart_item_key ] ) ) {
			$data[ $cart_item_key ][ $wishlist_sharekey ] += $quantity;
		} else {
			$data[ $cart_item_key ][ $wishlist_sharekey ] = $quantity;
		}

		WC()->session->set( 'tinvwl_wishlist_cart', $data );

		return true;
	}

	/**
	 * Get product added from wishlist meta
	 *
	 * @param string $cart_item_key Cart product key.
	 *
	 * @return array
	 */
	public static function get_item_meta( $cart_item_key ) {
		$data = (array) WC()->session->get( 'tinvwl_wishlist_meta', array() );
		if ( array_key_exists( $cart_item_key, $data ) ) {
			return $data[ $cart_item_key ];
		}

		return array();
	}

	/**
	 * Set product added from wishlist meta
	 *
	 * @param string $cart_item_key Cart product key.
	 * @param array $meta Meta data.
	 */
	public static function set_item_meta( $cart_item_key, $meta = array() ) {
		$data                   = (array) WC()->session->get( 'tinvwl_wishlist_meta', array() );
		$data[ $cart_item_key ] = $meta;
		WC()->session->set( 'tinvwl_wishlist_meta', $data );
	}

	/** Keep attribution for exactly the rows WooCommerce can still restore with Undo. */
	public static function archive_item_data( $cart_item_key, $cart = null ) {
		$cart = $cart ?: WC()->cart;
		$archive = (array) WC()->session->get( 'tinvwl_removed_cart_sources', array() );
		$removed = $cart && is_callable( array( $cart, 'get_removed_cart_contents' ) ) ? $cart->get_removed_cart_contents() : array();
		$archive = array_intersect_key( $archive, (array) $removed + array( $cart_item_key => true ) );
		$sources = (array) WC()->session->get( 'tinvwl_wishlist_cart', array() );
		$metadata = (array) WC()->session->get( 'tinvwl_wishlist_meta', array() );
		if ( isset( $sources[ $cart_item_key ] ) ) {
			$archive[ $cart_item_key ] = array( 'sources' => $sources[ $cart_item_key ], 'meta' => $metadata[ $cart_item_key ] ?? array() );
		} else { unset( $archive[ $cart_item_key ] ); }
		WC()->session->set( 'tinvwl_removed_cart_sources', $archive );
		self::remove_item_data( $cart_item_key );
	}

	/** Restoring a cart row restores its source without adding or debiting it again. */
	public static function restore_item_data( $cart_item_key, $cart = null ) {
		$cart = $cart ?: WC()->cart;
		$archive = (array) WC()->session->get( 'tinvwl_removed_cart_sources', array() );
		if ( ! $cart || ! $cart->get_cart_item( $cart_item_key ) || ! isset( $archive[ $cart_item_key ]['sources'] ) ) { return; }
		$sources = (array) WC()->session->get( 'tinvwl_wishlist_cart', array() );
		$metadata = (array) WC()->session->get( 'tinvwl_wishlist_meta', array() );
		$sources[ $cart_item_key ] = $archive[ $cart_item_key ]['sources'];
		$metadata[ $cart_item_key ] = $archive[ $cart_item_key ]['meta'];
		unset( $archive[ $cart_item_key ] );
		WC()->session->set( 'tinvwl_wishlist_cart', $sources );
		WC()->session->set( 'tinvwl_wishlist_meta', $metadata );
		WC()->session->set( 'tinvwl_removed_cart_sources', $archive );
	}

	/**
	 * Remove product added from wishlist
	 *
	 * @param string $cart_item_key Cart product key.
	 *
	 * @return boolean
	 */
	public static function remove_item_data( $cart_item_key = null ) {
		$data = (array) WC()->session->get( 'tinvwl_wishlist_cart', array() );
		if ( empty( $cart_item_key ) ) {
			WC()->session->set( 'tinvwl_wishlist_cart', array() );
			WC()->session->set( 'tinvwl_removed_cart_sources', array() );

			return true;
		}
		if ( ! array_key_exists( $cart_item_key, $data ) ) {
			return false;
		}

		unset( $data[ $cart_item_key ] );

		WC()->session->set( 'tinvwl_wishlist_cart', $data );

		return true;
	}

	/**
	 * Clear wishlist cart session.
	 *
	 * @param bool $clear_persistent_cart Should the persistant cart be cleared too. Defaults to true.
	 *
	 * @return boolean
	 */
	public static function remove_item_data_cart_session( $clear_persistent_cart = true ) {
		WC()->session->set( 'tinvwl_removed_cart_sources', array() );
		if ( $clear_persistent_cart ) {
			WC()->session->set( 'tinvwl_wishlist_cart', array() );

			return true;
		}
	}

	/**
	 * Add meta data for product when created order
	 *
	 * @param \WC_Order $order Order object.
	 */
	public function add_order_item_meta( $order ) {
		foreach ( $order->get_items() as $item ) {
			$store_api = 'woocommerce_store_api_checkout_update_order_meta' === current_filter();
			$key = $item->legacy_cart_item_key;
			$values = $item->legacy_values;
			if ( $store_api ) {
				if ( ! $key ) {
					$key = $item->get_meta( '_tinvwl_cart_item_key', true, 'edit' );
				}
				$key = is_string( $key ) ? $key : '';
				$values = $key && WC()->cart ? WC()->cart->get_cart_item( $key ) : array();
			}
			if ( $key ) {
				$item->update_meta_data( '_tinvwl_cart_item_key', $key );
			}
			$data = $store_api && ! $values ? array() : self::get_item_data( $key );
			$data = apply_filters( 'tinvwl_addproduct_toorder', $data, $key, $values );
			if ( $store_api ) {
				// Refresh unchanged drafts from the current cart, including removed sources.
				$item->delete_meta_data( '_tinvwl_wishlist_cart' );
			}
			if ( ! empty( $data ) ) {
				$item->update_meta_data( '_tinvwl_wishlist_cart', $data );
			}
		}
	}

	/**
	 *  Run action when purchased product from a wishlist.
	 *
	 * @param \WC_Order $order Order object.
	 */
	public function purchased_items( $order ) {
		foreach ( $order->get_items() as $item ) {
			$_wishlist_cart = self::get_order_item_meta( $item, '_tinvwl_wishlist_cart' );

			if ( $_wishlist_cart ) {
				$wishlist = null;

				if ( is_array( $_wishlist_cart ) ) {
					reset( $_wishlist_cart );
					$share_key = key( $_wishlist_cart );

					$wl       = new TInvWL_Wishlist();
					// Trusted order metadata is processed without returning list contents.
					$wishlist = $wl->get( array( 'share_key' => $share_key ) )[0] ?? null;
				}

				/* Run a 3rd party code when product purchased from wishlist.
				 *
				 * @param WC_Order $order Order object.
				 * @param WC_Order_Item_Product $item Order item product object.
				 * @param array $wishlist A wishlist data where product added from.
				 * */
				do_action( 'tinvwl_product_purchased', $order, $item, $wishlist );
			}
		}
	}

	/**
	 * Get wishlist by key or user id
	 *
	 * @param string $key Share key.
	 * @param integer $user_id Author order id.
	 *
	 * @return array
	 */
	private function get_order_wishlist( $key, $user_id = 0 ) {
		$wl = new TInvWL_Wishlist( self::$_name );
		if ( ! empty( $key ) ) {
			// Order callbacks may run without the owner's browser or login session.
			$wishlist = $wl->get( array( 'share_key' => $key ) )[0] ?? null;
			if ( ! $wishlist ) {
				return null;
			}
			if ( ! empty( $user_id ) && ( $wishlist['author'] !== $user_id && ! ( ( tinv_get_option( 'processing', 'autoremove_anyone_type' ) ? tinv_get_option( 'processing', 'autoremove_anyone_type' ) === $wishlist['status'] : 'private' !== $wishlist['status'] ) && tinv_get_option( 'processing', 'autoremove_anyone' ) ) ) ) {
				return null;
			}

			return $wishlist;
		}
		if ( ! empty( $user_id ) ) {
			return $wl->get_default( $user_id );
		}

		return null;
	}

	/** The same visitor privacy policy applies at cart time and order time. */
	private static function may_remove_wishlist( $wishlist, $is_owner ): bool {
		if ( $is_owner ) { return true; }
		$status = $wishlist['status'] ?? '';
		$allowed = tinv_get_option( 'processing', 'autoremove_anyone_type' );
		return tinv_get_option( 'processing', 'autoremove_anyone' ) && in_array( $status, array( 'share', 'public' ), true )
			&& ( ! $allowed || $allowed === $status );
	}

	/** Complete an independently persisted debit exactly once, including from WP-Cron. */
	public static function apply_cart_debit( $operation_key ): bool {
		global $wpdb;
		if ( ! self::$_name ) { self::$_name = TINVWL_PREFIX; }
		$operation = TInvWL_Public_CartDebit::get( $operation_key );
		if ( ! $operation ) { return false; }
		if ( in_array( $operation['state'], array( 'applied', 'discarded' ), true ) ) { return true; }
		if ( 'pending' !== $operation['state'] ) { return false; }
		$payload = json_decode( $operation['payload'], true );
		if ( ! is_array( $payload ) || ! isset( $payload['author'], $payload['share_key'], $payload['as_owner'], $payload['product_id'], $payload['variation_id'], $payload['quantity'], $payload['meta'] )
			|| ! is_array( $payload['meta'] ) || ! is_numeric( $payload['quantity'] ) || ! is_finite( (float) $payload['quantity'] ) || $payload['quantity'] <= 0 ) { return false; }
		// A quantity obligation must wait for its original implementation rather than delete a whole row.
		if ( 'remove' !== ( $payload['strategy'] ?? 'decrement' ) ) { return false; }
		$items = $wpdb->prefix . self::$_name . '_items';
		$lists = $wpdb->prefix . self::$_name . '_lists';
		$receipts = TInvWL_Public_CartDebit::table();
		$owner = (int) $payload['author'];
		$locks = $owner ? array( 'tinvwl_merge_' . md5( $wpdb->prefix . '|' . $owner ) ) : array();
		$locks[] = 'tinvwl_item_' . md5( $items . '|' . $operation['wishlist_id'] );
		$acquired = $actions = array();
		$transaction = $committed = false;
		try {
			foreach ( $locks as $lock ) {
				if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) { return false; }
				$acquired[] = $lock;
			}
			$tables = $wpdb->get_results( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name IN (%s, %s, %s)', $items, $lists, $receipts ), ARRAY_A );
			if ( $wpdb->last_error || count( (array) $tables ) !== 3 ) { return false; }
			foreach ( $tables as $table ) {
				if ( ! in_array( strtolower( $table['Engine'] ), array( 'innodb', 'xtradb' ), true ) ) { return false; }
			}
			$suppressed = $wpdb->suppress_errors( true );
			try { $independent = $wpdb->query( 'SET TRANSACTION READ WRITE' ); }
			finally { $wpdb->suppress_errors( $suppressed ); }
			if ( false === $independent || false === $wpdb->query( 'START TRANSACTION' ) ) { return false; }
			$transaction = true;
			$receipt = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$receipts` WHERE operation_key = %s FOR UPDATE", $operation_key ), ARRAY_A );
			if ( $wpdb->last_error || ! $receipt ) { return false; }
			if ( in_array( $receipt['state'], array( 'applied', 'discarded' ), true ) ) { return true; }
			if ( 'pending' !== $receipt['state'] || $receipt['payload'] !== $operation['payload'] ) { return false; }
			$current = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$lists` WHERE ID = %d FOR UPDATE", $operation['wishlist_id'] ), ARRAY_A );
			if ( $wpdb->last_error ) { return false; }
			$allowed = $current && $owner === (int) $current['author'] && hash_equals( (string) $payload['share_key'], (string) $current['share_key'] )
				&& tinv_get_option( 'processing', 'autoremove' ) && 'tinvwl-addcart' === tinv_get_option( 'processing', 'autoremove_status' )
				&& self::may_remove_wishlist( $current, (bool) $payload['as_owner'] );
			$state = 'discarded';
			if ( $allowed ) {
				$bounded = TInvWL_Public_CartDebit::bounded_payload( $current, $operation, $payload );
				if ( false === $bounded ) { return false; }
				$payload = $bounded;
				$outcome = array( 'complete' => true, 'actions' => array() );
				if ( (float) $payload['quantity'] > 0 || $payload['remove_empty'] ) {
					$outcome = TInvWL_Public_CartDebit::remove_saved_contribution( $current, $payload, $operation['item_id'] );
				}
				if ( ! $outcome['complete'] ) { return false; }
				$actions = $outcome['actions'];
				$state = 'applied';
			}
			$written = $wpdb->query( $wpdb->prepare( "UPDATE `$receipts` SET state = %s, updated_at = %d WHERE operation_key = %s AND state = 'pending'", $state, time(), $operation_key ) );
			if ( 1 !== $written || false === $wpdb->query( 'COMMIT' ) ) { return false; }
			$transaction = false;
			$committed = true;
		} catch ( Throwable $error ) {
			error_log( 'TI Wishlist cart debit remains pending after an unsuccessful transaction.' );
			return false;
		} finally {
			if ( $transaction ) { $wpdb->query( 'ROLLBACK' ); }
			foreach ( array_reverse( $acquired ) as $lock ) { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
		}
		foreach ( $actions as $action ) {
			try { do_action( ...$action ); }
			catch ( Throwable $error ) { error_log( 'TI Wishlist cart debit action failed after its commit.' ); }
		}
		return $committed;
	}


	/**
	 * Autoremove product from wishlist
	 *
	 * @param array $wishlist Wishlist object.
	 * @param integer $product_id Product id.
	 * @param int|float $quantity Quantity product.
	 * @param integer $variation_id Variation product id.
	 * @param array $meta Meta array for post form.
	 *
	 * @return int|float
	 */
	private static function ar_f_wl( $wishlist, $product_id, $quantity = 1, $variation_id = 0, $meta = array(), $item_id = 0 ) {
		$product_id   = absint( $product_id );
		$quantity     = floatval( $quantity );
		$variation_id = absint( $variation_id );
		if ( ! tinv_get_option( 'processing', 'autoremove' ) || empty( $wishlist ) || empty( $product_id ) || ! is_finite( $quantity ) || $quantity <= 0 ) {
			return $quantity;
		}
		$wlp = null;
		if ( 0 === $wishlist['ID'] ) {
			$wlp = TInvWL_Product_Local::instance();
		} else {
			$wlp = new TInvWL_Product( $wishlist, self::$_name );
		}
		if ( empty( $wlp ) ) {
			return 0;
		}
		$products = $wlp->get_wishlist( array(
			'ID'           => $item_id ?: null,
			'product_id'   => $product_id,
			'variation_id' => $variation_id,
			'meta'         => $meta,
			'external'     => false,
		) );
		$product  = array_shift( $products );
		if ( empty( $product ) ) {
			return $quantity;
		}
		$wlp->remove_item_from_wl( $product );

		return 0;
	}

	/**
	 * Analytics check completed orders
	 *
	 * @param integer $order_id Order id.
	 * @param string $old_status Not used.
	 * @param string $new_status Updated status order.
	 *
	 * @return boolean
	 */
	function order_status_analytics( $order_id, $old_status, $new_status ) {
		global $wpdb;
		for ( $attempt = 0; $attempt < 3; $attempt++ ) {
			$retry = false;
			$complete = $this->process_order_analytics( $order_id, $old_status, $new_status, $retry );
			if ( $complete || ! $retry || $attempt === 2 ) {
				return $complete;
			}
			// A concurrency failure rolled back this entire attempt; start with a fresh receipt/snapshot.
			$wpdb->last_error = '';
			usleep( 20000 * ( $attempt + 1 ) );
		}
		return false;
	}

	/** One independent analytics transaction; only recognized transient SQL failures permit retry. */
	private function process_order_analytics( $order_id, $old_status, $new_status, &$retry ) {
		global $wpdb;
		$new_status = str_replace( 'wc-', '', $new_status );
		if ( ! in_array( $new_status, array( 'processing', 'completed' ), true ) ) {
			return false;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}
		// Metadata refresh/save must not discard pending changes on a shared cached order.
		$order = clone $order;
		$store = $order->get_data_store()->get_current_class_name();
		$hpos = is_a( $store, 'Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore', true );
		if ( ! $hpos && ! is_a( $store, 'WC_Order_Data_Store_CPT', true ) ) {
			return false;
		}
		$order_id = $order->get_id();
		$root = $hpos ? $wpdb->prefix . 'wc_orders' : $wpdb->posts;
		$root_id = $hpos ? 'id' : 'ID';
		$meta = $hpos ? $wpdb->prefix . 'wc_orders_meta' : $wpdb->postmeta;
		$meta_id = $hpos ? 'id' : 'meta_id';
		$meta_owner = $hpos ? 'order_id' : 'post_id';
		$analytics = $wpdb->prefix . self::$_name . '_analytics';
		$items = $wpdb->prefix . 'woocommerce_order_items';
		$item_meta = $wpdb->prefix . 'woocommerce_order_itemmeta';
		$key = '_wishlist_analytics_processed';
		$lock = 'tinvwl_analytics_' . md5( $wpdb->prefix . '|' . $order_id );
		$locked = $transaction = $touched_meta = false;
		$item_caches = array();
		$prime_item_meta = method_exists( 'WC_Order_Item', 'prime_raw_meta_data_cache' ) && method_exists( 'WC_Order_Item', 'generate_meta_cache_key' );
		try {
			$suppressed = $wpdb->suppress_errors( true );
			try {
				$independent = $wpdb->query( 'SET TRANSACTION READ WRITE' );
			} finally {
				$wpdb->suppress_errors( $suppressed );
			}
			if ( false === $independent ) {
				return false;
			}
			// HPOS metadata CRUD may also backfill postmeta when Woo synchronization is enabled.
			$required_tables = $hpos ? array( $root, $meta, $analytics, $wpdb->postmeta ) : array( $root, $meta, $analytics );
			$tables = $wpdb->get_results( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name IN (' . implode( ',', array_fill( 0, count( $required_tables ), '%s' ) ) . ')', $required_tables ), ARRAY_A );
			if ( $wpdb->last_error || ! is_array( $tables ) || count( $tables ) !== count( $required_tables ) ) {
				return false;
			}
			foreach ( $tables as $table ) {
				if ( ! in_array( strtolower( $table['Engine'] ), array( 'innodb', 'xtradb' ), true ) ) {
					return false;
				}
			}
			if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
				return false;
			}
			$locked = true;
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
				return false;
			}
			$transaction = true;
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT `$root_id` FROM `$root` WHERE `$root_id` = %d FOR UPDATE", $order_id ) );
			if ( $wpdb->last_error || ! $exists ) {
				return false;
			}
			// The first consistent read starts a fresh snapshot after the per-order mutex/root lock.
			// Read-only metadata needs no cross-order next-key locks; completion stays serialized.
			$receipt_sql = $wpdb->prepare( "SELECT meta_value FROM `$meta` WHERE `$meta_owner` = %d AND meta_key = %s ORDER BY `$meta_id`", $order_id, $key );
			$receipt = $wpdb->get_col( $receipt_sql );
			if ( $wpdb->last_error ) {
				return false;
			}
			if ( ! empty( $receipt ) && ! empty( maybe_unserialize( $receipt[0] ) ) ) {
				return true;
			}
			if ( $hpos ) {
				// Earlier releases wrote this marker to postmeta even with HPOS authoritative.
				$legacy = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM `{$wpdb->postmeta}` WHERE post_id = %d AND meta_key = %s ORDER BY meta_id", $order_id, $key ) );
				if ( $wpdb->last_error ) {
					return false;
				}
				if ( ! empty( $legacy ) && ! empty( maybe_unserialize( $legacy[0] ) ) ) {
					return true;
				}
			}
			$lines = $wpdb->get_results( $wpdb->prepare( "SELECT order_item_id, order_item_type, order_id, order_item_name FROM `$items` WHERE order_id = %d AND order_item_type = %s ORDER BY order_item_id", $order_id, 'line_item' ), ARRAY_A );
			if ( $wpdb->last_error ) {
				return false;
			}
			$native_items = array();
			foreach ( $lines as $line ) {
				$line_id = (int) $line['order_item_id'];
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_key, meta_value FROM `$item_meta` WHERE order_item_id = %d ORDER BY meta_id", $line_id ), ARRAY_A );
				if ( $wpdb->last_error ) {
					return false;
				}
				$wp_meta = $raw_meta = array();
				foreach ( $rows as $row ) {
					$wp_meta[ $row['meta_key'] ][] = $row['meta_value'];
					$raw_meta[] = (object) $row;
				}
				// Hydrate through Woo's factory/setters/read filters using only successful snapshot reads.
				$item_caches[] = $line_id;
				wp_cache_set( 'item-' . $line_id, (object) $line, 'order-items' );
				wp_cache_set( $line_id, $wp_meta, 'order_item_meta' );
				if ( $prime_item_meta ) {
					WC_Order_Item::prime_raw_meta_data_cache( array( $line_id => $raw_meta ), 'order-items' );
				}
				$item = WC_Order_Factory::get_order_item( (object) $line );
				if ( $wpdb->last_error || ! $item instanceof WC_Order_Item_Product ) {
					return false;
				}
				if ( ! $prime_item_meta ) {
					// Woo 3.2-4.6 has no public raw-cache priming API: force a checked native read.
					$item->read_meta_data( true );
					if ( $wpdb->last_error ) { return false; }
				}
				if ( method_exists( $item, 'set_order' ) ) { $item->set_order( $order ); }
				$native_items[ $line_id ] = $item;
			}
			// Preserve the same collection extension point exposed by WC_Order::get_items().
			$native_items = apply_filters( 'woocommerce_order_get_items', $native_items, $order, array( 'line_item' ) );
			if ( empty( $native_items ) || ! is_array( $native_items ) || $wpdb->last_error ) {
				return false;
			}
			$events = array();
			foreach ( $native_items as $item ) {
				if ( ! $item instanceof WC_Order_Item_Product ) { continue; }
				$map = $this->get_order_item_meta( $item, '_tinvwl_wishlist_cart' );
				$quantity = floatval( $item['qty'] );
				$product_id = $item['product_id'];
				$variation_id = $item['variation_id'];
				$product_id = is_scalar( $product_id ) ? absint( $product_id ) : 0;
				$variation_id = is_scalar( $variation_id ) ? absint( $variation_id ) : 0;
				if ( $wpdb->last_error ) {
					return false;
				}
				if ( ! is_array( $map ) ) { continue; }
				foreach ( array_keys( $map ) as $share_key ) {
					if ( 0 >= $quantity ) { break; }
					$wishlist = $this->get_order_wishlist( $share_key );
					if ( $wpdb->last_error ) {
						return false;
					}
					if ( empty( $wishlist ) ) { continue; }
					$events[] = array( 'wishlist' => $wishlist, 'product_id' => $product_id, 'variation_id' => $variation_id );
				}
			}
			// All orders acquire shared analytics rows in the same order, even with reversed line/maps.
			usort( $events, static function ( $left, $right ) {
				return array( (int) $left['wishlist']['ID'], $left['product_id'], $left['variation_id'] )
					<=> array( (int) $right['wishlist']['ID'], $right['product_id'], $right['variation_id'] );
			} );
			foreach ( $events as $event ) {
				$wla = new TInvWL_Analytics( $event['wishlist'], self::$_name );
				// Preserve C13: each positive line/source remains a separate one-unit event.
				if ( false === $wla->sell_product_from_wl( $event['product_id'], $event['variation_id'] ) || $wpdb->last_error ) {
					return false;
				}
			}
			self::refresh_analytics_order_meta( $order, $hpos );
			if ( $wpdb->last_error ) {
				return false;
			}
			$touched_meta = true;
			$order->update_meta_data( $key, '1' );
			// This internal marker must not trigger HPOS full-order autosave/status side effects.
			$metadata_only = static function ( $should_save ) { return false; };
			if ( $hpos ) { add_filter( 'woocommerce_orders_table_datastore_should_save_after_meta_change', $metadata_only, PHP_INT_MAX ); }
			try {
				$order->save_meta_data();
			} finally {
				if ( $hpos ) { remove_filter( 'woocommerce_orders_table_datastore_should_save_after_meta_change', $metadata_only, PHP_INT_MAX ); }
			}
			if ( $wpdb->last_error ) {
				return false;
			}
			$receipt = $wpdb->get_col( $receipt_sql );
			if ( $wpdb->last_error || count( $receipt ) !== 1 || $receipt[0] !== '1' ) {
				return false;
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				return false;
			}
			$transaction = false;
			return true;
		} finally {
			$error = $wpdb->last_error;
			$retry = $transaction && self::transient_order_error();
			foreach ( $item_caches as $line_id ) {
				wp_cache_delete( 'item-' . $line_id, 'order-items' );
				wp_cache_delete( $line_id, 'order_item_meta' );
				if ( $prime_item_meta ) {
					wp_cache_delete( WC_Order_Item::generate_meta_cache_key( $line_id, 'order-items' ), 'order-items' );
				}
			}
			if ( ! $prime_item_meta && $item_caches && method_exists( 'WC_Cache_Helper', 'incr_cache_prefix' ) ) {
				WC_Cache_Helper::incr_cache_prefix( 'order-items' );
			}
			if ( $transaction && false === $wpdb->query( 'ROLLBACK' ) ) { $retry = false; }
			if ( $locked ) { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
			if ( $touched_meta ) {
				try {
					self::refresh_analytics_order_meta( $order, $hpos );
				} catch ( Throwable $error_refresh ) {
					// A cache callback cannot turn a persisted marker into a repeated increment.
					$retry = false;
				}
			}
			$wpdb->last_error = $error;
		}
	}

	/** Retry only concurrency failures, with a new transaction and snapshot. */
	private static function transient_order_error() {
		global $wpdb;
		$code = class_exists( 'mysqli', false ) && $wpdb->dbh instanceof mysqli ? mysqli_errno( $wpdb->dbh ) : 0;
		return in_array( $code, array( 1020, 1205, 1213 ), true ) || preg_match( '/Record has changed since last read|Lock wait timeout exceeded|Deadlock found when trying to get lock/i', $wpdb->last_error );
	}

	/** Clear native metadata caches before refreshing this analytics-only order clone. */
	private static function refresh_analytics_order_meta( $order, $hpos ) {
		$meta_store = 'Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStoreMeta';
		if ( $hpos && class_exists( $meta_store ) && method_exists( $meta_store, 'clear_cached_data' ) ) {
			wc_get_container()->get( $meta_store )->clear_cached_data( array( $order->get_id() ) );
		}
		wp_cache_delete( $order->get_id(), 'post_meta' );
		if ( method_exists( $order, 'get_meta_cache_key' ) ) {
			wp_cache_delete( $order->get_meta_cache_key(), 'orders' );
		}
		$order->read_meta_data( true );
	}

	/**
	 * Get order item meta value.
	 *
	 * @param $item
	 * @param $key
	 *
	 * @return mixed
	 */
	public static function get_order_item_meta( $item, $key ) {

		// Check if wishlist meta exists for current item order.
		$value = $item->get_meta( $key );

		return $value;
	}


	/**
	 * Get errors when adding a product to the cart.
	 *
	 * @param WC_Product $product The product to add to the cart.
	 * @param int|float $quantity The normalized quantity of the product to be added. Default is 1.
	 * @param bool $defer_quantity Let the actual WC_Cart add normalize and validate stock.
	 *
	 * @return array Array of error codes or false if no errors.
	 */
	public static function add_to_cart_errors( WC_Product $product, $quantity = 1, $defer_quantity = false ): array {
		if ( ! $product->is_purchasable() ) {
			return [ 'product' => $product, 'error_code' => 'not_purchasable' ];
		}

		if ( ! $product->is_in_stock() && ! $product->backorders_allowed() ) {
			return [ 'product' => $product, 'error_code' => 'not_in_stock' ];
		}

		if ( 'external' === $product->get_type() ) {
			return [ 'product' => $product, 'error_code' => 'external' ];
		}

		if ( 'variable' === $product->get_type() && ! empty( $product->get_children() ) ) {
			return [ 'product' => $product, 'error_code' => 'parent_variable' ];
		}

		// During a real add, WC_Cart normalizes quantity (including sold-individually) once,
		// then performs native stock checks against that final amount and stock owner.
		if ( $defer_quantity ) {
			return [ 'product' => $product ];
		}

		/** @global \WooCommerce $woocommerce */
		global $woocommerce;
		$product_in_cart = array_filter(
			$woocommerce->cart->get_cart(),
			fn( array $item ): bool => $item['product_id'] === $product->get_id()
		);

		// Sold-individually identity includes native add-on cart data, prepared by WC_Cart.

		if ( ! $product->has_enough_stock( $quantity ) ) {
			return [ 'product' => $product, 'error_code' => 'more_than_stock' ];
		}

		if ( $product->managing_stock() ) {
			$quantity_in_cart = array_sum( wp_list_pluck( $product_in_cart, 'quantity' ) );
			if ( ! $product->has_enough_stock( $quantity_in_cart + $quantity ) ) {
				return [ 'product' => $product, 'error_code' => 'more_than_stock' ];
			}
		}

		return [ 'product' => $product ];
	}

	/** Normalize a disappeared saved row without losing earlier cart successes. */
	public static function unavailable_cart_item( $item_id = 0, $product = null ): array {
		return array( 'product' => $product, 'item_id' => is_scalar( $item_id ) ? absint( $item_id ) : 0, 'quantity' => 0, 'error_code' => 'unavailable' );
	}

	/** A bulk response describes every row and redirects only after complete success. */
	public static function complete_bulk_response( array $response ): array {
		$results = $response['cart_results'] ?? array();
		$failed = $added = array();
		foreach ( $results as $id => $result ) {
			if ( 'added' === ( $result['status'] ?? '' ) ) { $added[] = (int) $id; }
			else { $failed[] = (int) $id; }
		}
		$response['retry_items'] = $failed;
		$response['status'] = ! $failed && (bool) $added;
		$response['outcome'] = $failed ? ( $added ? 'partial' : 'failed' ) : ( $added ? 'full' : 'failed' );
		if ( ! $response['status'] ) {
			unset( $response['redirect'] );
			$response['error'] = $added ? 'partial_cart' : 'cart_failed';
			$message = __( 'Some selected items could not be added. Try again to retry only those items.', 'ti-woocommerce-wishlist' );
			if ( ! in_array( $message, $response['msg'] ?? array(), true ) ) { $response['msg'][] = $message; }
		}
		return $response;
	}

	/**
	 * Generates an error message for products that couldn't be added to the cart.
	 *
	 * @param array $products Array of products with error codes.
	 *                        Each product should have the 'product' (WC_Product) and 'error_code' (string) keys.
	 *
	 * @return string Error message.
	 */
	public static function cart_all_errors_message( array $products ): string {
		$response = [];

		// Group $products data by error_code to get a new array $codes
		$codes = [];
		foreach ( $products as $product ) {
			$product = is_array( $product ) ? $product : self::unavailable_cart_item();
			$error_code = isset( $product['error_code'] ) && is_string( $product['error_code'] ) ? $product['error_code'] : 'unavailable';
			$object = $product['product'] ?? null;
			$product_name = $object instanceof WC_Product ? $object->get_name()
				: ( ! empty( $product['item_id'] ) ? sprintf( __( 'Item #%d', 'ti-woocommerce-wishlist' ), $product['item_id'] ) : __( 'Unavailable item', 'ti-woocommerce-wishlist' ) );
			$codes[ $error_code ][] = $product_name;
		}

		foreach ( $codes as $code => $titles ) {
			$response[] = self::cart_error_message( $code, $titles );
		}

		return implode( '<br>', $response );
	}

	/**
	 * Generate an error message based on the provided code and titles.
	 *
	 * @param string $code Error code.
	 * @param array $titles Array of titles.
	 *
	 * @return string Error message.
	 */
	public static function cart_error_message( string $code, array $titles ): string {
		$error_message = '';

		switch ( $code ) {
			case 'not_purchasable':
				$error_message = sprintf(
					_n(
						'Sorry, the &quot;%s&quot; cannot be purchased.',
						'Sorry, the following products cannot be purchased: &quot;%s&quot;.',
						count( $titles ),
						'ti-woocommerce-wishlist'
					),
					wc_format_list_of_items( $titles )
				);
				break;
			case 'not_in_stock':
				$error_message = sprintf(
					_n(
						'You cannot add &quot;%s&quot; to the cart because the product is out of stock.',
						'You cannot add the following products to the cart because they are out of stock: &quot;%s&quot;.',
						count( $titles ),
						'ti-woocommerce-wishlist'
					),
					wc_format_list_of_items( $titles )
				);
				break;
			case 'external':
				$error_message = sprintf(
					_n(
						'External product &quot;%s&quot; cannot be bought.',
						'The following external products cannot be bought: &quot;%s&quot;.',
						count( $titles ),
						'ti-woocommerce-wishlist'
					),
					wc_format_list_of_items( $titles )
				);
				break;
			case 'parent_variable':
				$error_message = sprintf(
					_n(
						'Please choose product options for &quot;%s&quot;.',
						'Please choose options for the following products: &quot;%s&quot;.',
						count( $titles ),
						'ti-woocommerce-wishlist'
					),
					wc_format_list_of_items( $titles )
				);
				break;
			case 'sold_individually':
				$error_message = sprintf(
					_n(
						'You cannot add another &quot;%s&quot; to your cart.',
						'You cannot add &quot;%s&quot; more to your cart.',
						count( $titles ),
						'ti-woocommerce-wishlist'
					),
					wc_format_list_of_items( $titles )
				);
				break;
			case 'more_than_stock':
				$error_message = sprintf(
					_n(
						'You cannot add that amount of &quot;%s&quot; to the cart because there is not enough stock.',
						'You cannot add the following products to the cart because there is not enough stock: &quot;%s&quot;.',
						count( $titles ),
						'ti-woocommerce-wishlist'
					),
					wc_format_list_of_items( $titles )
				);
				break;
			default:
				$error_message = sprintf(
					_n(
						'Product &quot;%s&quot; could not be added to the cart because some requirements are not met.',
						'Products: &quot;%s&quot; could not be added to the cart because some requirements are not met.',
						count( $titles ),
						'ti-woocommerce-wishlist'
					),
					wc_format_list_of_items( $titles )
				);
				break;
		}

		return $error_message;
	}
}
