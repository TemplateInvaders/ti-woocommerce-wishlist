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
			add_action( 'woocommerce_before_cart_item_quantity_zero', array( __CLASS__, 'remove_item_data' ) );
		} else {
			add_action( 'woocommerce_remove_cart_item', array( __CLASS__, 'remove_item_data' ) );
		}
		if ( version_compare( WC_VERSION, '3.9.0', '<' ) ) {
			add_action( 'woocommerce_cart_emptied', array( __CLASS__, 'remove_item_data' ) );
		} else {
			add_action( 'woocommerce_cart_emptied', array( __CLASS__, 'remove_item_data_cart_session' ) );
		}

		add_action( 'woocommerce_checkout_create_order', array( $this, 'add_order_item_meta' ) );

		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'purchased_items' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'order_status_analytics' ), 9, 3 );
	}

	/**
	 * Add product to cart from wishlist
	 *
	 * @param array $wishlist Wishlist object.
	 * @param integer $wl_product Wishlist product id.
	 * @param integer $wl_quantity Product quantity.
	 *
	 * @return array|boolean
	 */
	public static function add( $wishlist = null, $wl_product = 0, $wl_quantity = 1 ) {
		if ( empty( $wishlist ) ) {
			$wishlist = tinv_wishlist_get();
		}
		$wlp = null;
		if ( 0 === $wishlist['ID'] ) {
			$wlp = TInvWL_Product_Local::instance();
		} else {
			$wlp = new TInvWL_Product( $wishlist );
		}
		$product = $wlp->get_wishlist( array( 'ID' => $wl_product ) );
		$product = array_shift( $product );
		if ( empty( $product ) ) {
			return false;
		}
		if ( empty( $product['data'] ) ) {
			return false;
		}
		$product['action'] = 'add';
		$product           = apply_filters( 'tinvwl_addproduct_tocart', $product );
		self::prepare_post( $product );

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
		$variation_id = apply_filters( 'wpml_object_id', $product['variation_id'], 'product_variation', $use_original_id );
		$variations   = $product['data']->is_type( 'variation' ) ? wc_get_product_variation_attributes( apply_filters( 'wpml_object_id', $product['data']->get_id(), 'product', $use_original_id ) ) : array();

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

		$cart_errors       = self::add_to_cart_errors( $product['data'], $quantity );
		$passed_validation = ! isset( $cart_errors['error_code'] );
		$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', $passed_validation, $product_id, $quantity, $variation_id, $variations );
		if ( $passed_validation ) {
			$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variations, $product['meta'] );
			if ( $cart_item_key ) {

				/* Run a 3rd party code when product added to a cart from a wishlist.
				 *
				 * @param string $cart_item_key cart product unique key.
				 * @param integer $quantity Product quantity.
				 * @param array $product product data.
				 * */
				do_action( 'tinvwl_product_added_to_cart', $cart_item_key, $quantity, $product );
				$wla = new TInvWL_Analytics( $wishlist, self::$_name );
				$wla->cart_product( $product_id, $variation_id );
				if ( ( 'private' !== $wishlist['status'] && tinv_get_option( 'processing', 'autoremove_anyone' ) ) || $wishlist['is_owner'] && 'tinvwl-addcart' === tinv_get_option( 'processing', 'autoremove_status' ) ) {
					self::ar_f_wl( $wishlist, $product_id, $quantity, $variation_id, $product['meta'] );
				}
				self::set_item_data( $cart_item_key, $wishlist['share_key'], $quantity );
				self::set_item_meta( $cart_item_key, $product['meta'] );
				self::unprepare_post( $product );

				return array(
					'product'       => $product['data'],
					'quantity'      => $quantity,
					'cart_item_key' => $cart_item_key
				);
			}
		}
		self::unprepare_post( $product );
		$error_code = $cart_errors['error_code'] ?? 'default';

		return array( 'product' => $product['data'], 'quantity' => $quantity, 'error_code' => $error_code );
	}

	/**
	 * Prepare _POST data
	 *
	 * @param array $product Wishlist Product.
	 */
	public static function prepare_post( $product ) {
		do_action( 'tinvwl_before_prepare_post', $product );
		self::$_post    = $_POST; // @codingStandardsIgnoreLine WordPress.VIP.SuperGlobalInputUsage.AccessDetected
		self::$_request = $_REQUEST;
		if ( array_key_exists( 'meta', $product ) && ! empty( $product['meta'] ) ) {
			$_POST    = $product['meta']; // Maybe a conflict there will be no GET attributes.
			$_REQUEST = $product['meta'];
		} else {
			$_POST    = array();
			$_REQUEST = array();
		}
	}

	/**
	 * Unprepare _POST data
	 *
	 * @param array $product Wishlist Product.
	 */
	public static function unprepare_post( $product ) {
		do_action( 'tinvwl_before_unprepare_post', $product, self::$_post, self::$_request );
		$_POST    = self::$_post;
		$_REQUEST = self::$_request;
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
	 * @param integer $quantity Product quantity.
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
			$data = self::get_item_data( $item->legacy_cart_item_key );
			$data = apply_filters( 'tinvwl_addproduct_toorder', $data, $item->legacy_cart_item_key, $item->legacy_values );
			if ( ! empty( $data ) ) {
				$item->update_meta_data( '_tinvwl_wishlist_cart', $data );
			}
		}
	}

	/**
	 *  Run action when purchased product from a wishlist.
	 *
	 * @param int $order Order ID.
	 */
	public function purchased_items( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {

			$_wishlist_cart = self::get_order_item_meta( $item, '_tinvwl_wishlist_cart' );

			if ( $_wishlist_cart ) {
				$wishlist = null;

				if ( is_array( $_wishlist_cart ) ) {
					reset( $_wishlist_cart );
					$share_key = key( $_wishlist_cart );

					$wl       = new TInvWL_Wishlist();
					$wishlist = $wl->get_by_share_key( $share_key );
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
			$wishlist = $wl->get_by_share_key( $key );
			if ( ! empty( $user_id ) && ( $wishlist['author'] !== $user_id && ! ( ( tinv_get_option( 'processing', 'autoremove_anyone_type' ) ? tinv_get_option( 'processing', 'autoremove_anyone_type' ) === $wishlist['status'] : 'private' !== $wishlist['status'] ) && tinv_get_option( 'processing', 'autoremove_anyone' ) ) ) ) {
				return null;
			}

			return $wishlist;
		}
		if ( ! empty( $user_id ) ) {
			return $wl->add_user_default( $user_id );
		}

		return null;
	}

	/**
	 * Autoremove product from wishlist
	 *
	 * @param array $wishlist Wishlist object.
	 * @param integer $product_id Product id.
	 * @param integer $quantity Quantity product.
	 * @param integer $variation_id Variation product id.
	 * @param array $meta Meta array for post form.
	 *
	 * @return integer
	 */
	private static function ar_f_wl( $wishlist, $product_id, $quantity = 1, $variation_id = 0, $meta = array() ) {
		$product_id   = absint( $product_id );
		$quantity     = absint( $quantity );
		$variation_id = absint( $variation_id );
		if ( ! tinv_get_option( 'processing', 'autoremove' ) || empty( $wishlist ) || empty( $product_id ) || empty( $quantity ) ) {
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
			'product_id'   => $product_id,
			'variation_id' => $variation_id,
			'meta'         => $meta,
			'external'     => false,
		) );
		$product  = array_shift( $products );
		if ( empty( $product ) ) {
			return $quantity;
		}
		$wlp->remove_product_from_wl( 0, $product_id, $variation_id, $product['meta'] );

		return 0;
	}

	/**
	 * Analytics check completed orders
	 *
	 * @param integer $order_id Order id.
	 * @param string $old_status Not used.
	 * @param string $new_status Updated status order.
	 *
	 * @return void
	 */
	function order_status_analytics( $order_id, $old_status, $new_status ) {
		$new_status = str_replace( 'wc-', '', $new_status );
		$order      = wc_get_order( $order_id );

		if ( in_array( $new_status, array(
				'processing',
				'completed',
			) ) && empty( get_post_meta( $order_id, '_wishlist_analytics_processed', true ) ) ) {

			$items = $order->get_items();
			if ( empty( $items ) || ! is_array( $items ) ) {
				return;
			}

			foreach ( $items as $item ) {

				$_wishlist_cart = self::get_order_item_meta( $item, '_tinvwl_wishlist_cart' );

				if ( $_wishlist_cart ) {
					$_quantity = absint( $item['qty'] );
					if ( is_array( $_wishlist_cart ) ) {
						foreach ( array_keys( $_wishlist_cart ) as $key ) {
							if ( 0 >= $_quantity ) {
								break;
							}
							$wishlist = $this->get_order_wishlist( $key );

							if ( empty( $wishlist ) ) {
								continue;
							}
							$wla = new TInvWL_Analytics( $wishlist, self::$_name );
							$wla->sell_product_from_wl( $item['product_id'], $item['variation_id'] );
						}
					}
				}
			}

			update_post_meta( $order_id, '_wishlist_analytics_processed', '1' );
		}
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
	 * @param int $quantity The quantity of the product to be added. Default is 1.
	 *
	 * @return array Array of error codes or false if no errors.
	 */
	public static function add_to_cart_errors( WC_Product $product, int $quantity = 1 ): array {
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

		/** @global \WooCommerce $woocommerce */
		global $woocommerce;
		$product_in_cart = array_filter(
			$woocommerce->cart->get_cart(),
			fn( array $item ): bool => $item['product_id'] === $product->get_id()
		);

		if ( $product->is_sold_individually() && ! empty( $product_in_cart ) ) {
			return [ 'product' => $product, 'error_code' => 'sold_individually' ];
		}

		if ( ! $product->has_enough_stock( $quantity ) ) {
			return [ 'product' => $product, 'error_code' => 'more_than_stock' ];
		}

		if ( $product->managing_stock() ) {
			$quantity_in_cart = array_sum( wp_list_pluck( $product_in_cart, 'quantity' ) );
			if ( $quantity_in_cart + $quantity > $product->get_stock_quantity() ) {
				return [ 'product' => $product, 'error_code' => 'more_than_stock' ];
			}
		}

		return [ 'product' => $product ];
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
			$error_code             = $product['error_code'];
			$product_name           = $product['product']->get_name();
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
