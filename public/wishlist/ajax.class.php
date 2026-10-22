<?php
/**
 * Wishlist table AJAX actions
 *
 * @since             2.0.0
 * @package           TInvWishlist\Public
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TInvWL_Public_Wishlist_Ajax
 *
 * Handles Wishlist AJAX actions.
 */
class TInvWL_Public_Wishlist_Ajax {

	/**
	 * The plugin name.
	 *
	 * @var string
	 */
	private $_name;

	/**
	 * This class instance
	 *
	 * @var TInvWL_Public_Wishlist_Ajax|null
	 */
	private static ?TInvWL_Public_Wishlist_Ajax $_instance = null;

	/**
	 * Get this class instance.
	 *
	 * @param string $plugin_name Plugin name.
	 *
	 * @return TInvWL_Public_Wishlist_Ajax
	 */
	public static function instance( string $plugin_name = TINVWL_PREFIX ): TInvWL_Public_Wishlist_Ajax {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $plugin_name );
		}

		return self::$_instance;
	}

	/**
	 * TInvWL_Public_Wishlist_Ajax constructor.
	 *
	 * @param string $plugin_name Plugin name.
	 */
	public function __construct( string $plugin_name ) {
		$this->_name = $plugin_name;
		$this->define_hooks();
	}

	/**
	 * Define shortcode and hooks.
	 */
	private function define_hooks(): void {
		add_action( 'wc_ajax_tinvwl', [ $this, 'ajax_action' ] );
	}

	/**
	 * Perform AJAX action.
	 */
	public function ajax_action(): void {
		$post = filter_input_array( INPUT_POST, [
			'tinvwl-security'   => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
			'tinvwl-action'     => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
			'tinvwl-product_id' => FILTER_VALIDATE_INT,
			'tinvwl-paged'      => FILTER_VALIDATE_INT,
			'tinvwl-per-page'   => FILTER_VALIDATE_INT,
			'tinvwl-sharekey'   => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
			'tinvwl-products'   => [
				'filter' => FILTER_VALIDATE_INT,
				'flags'  => FILTER_FORCE_ARRAY,
			],
		] );

		// Check for valid AJAX action
		if ( ! isset( $post['tinvwl-action'] ) || ! $post['tinvwl-action'] ) {
			return;
		}

		$wl             = new TInvWL_Wishlist( $this->_name );
		$guest_wishlist = ! is_user_logged_in() ? ( $wl->get_by_sharekey_default()[0] ?? [] ) : [];
		// An explicit inaccessible key must never fall back to a different list.
		$wishlist = ! empty( $post['tinvwl-sharekey'] )
			? $wl->get_by_share_key( $post['tinvwl-sharekey'] )
			: ( is_user_logged_in() ? ( $wl->get_by_user_default()[0] ?? null ) : $guest_wishlist );
		$wishlist = $wishlist ?: [];

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $post['tinvwl-security'] ) && wp_verify_nonce( $post['tinvwl-security'], 'wp_rest' ) ) {
			$this->wishlist_ajax_actions( $wishlist, $post, $guest_wishlist );
		} else {
			$response = [
				'status' => false,
				'error'  => 'invalid_nonce',
				'msg'    => [ __( 'Something went wrong', 'ti-woocommerce-wishlist' ) ],
				'icon'   => 'icon_big_times',
			];

			$response['msg'] = array_unique( $response['msg'] );
			$response['msg'] = implode( '<br>', $response['msg'] );

			if ( tinv_get_option( 'table', 'hide_popup' ) ) {
				unset( $response['msg'] );
			}

			if ( ! empty( $response['msg'] ) ) {
				$response['msg'] = tinv_wishlist_template_html( 'ti-addedtowishlist-dialogbox.php', apply_filters( 'tinvwl_addtowishlist_dialog_box', $response, $post ) );
			}

			wp_send_json( $response, 403 );
		}
	}

	/**
	 * Wishlist Ajax Actions
	 *
	 * @param array $wishlist
	 * @param array $post
	 * @param array $guest_wishlist
	 *
	 * @return void
	 */
	public function wishlist_ajax_actions( array $wishlist, array $post, array $guest_wishlist = [] ): void {
		$wl = new TInvWL_Wishlist( $this->_name );
		if ( ! $wishlist && empty( $post['tinvwl-sharekey'] ) ) {
			$wishlist = $guest_wishlist;
		}
		// A first visit has no stored wishlist until a product is added.
		if ( ! $wishlist && ! $guest_wishlist && empty( $post['tinvwl-sharekey'] ) && 'get_data' === ( $post['tinvwl-action'] ?? '' ) ) {
			$response = [
				'status'        => true,
				'action'        => 'get_data',
				'icon'          => 'icon_big_heart_check',
				'msg'           => '',
				'content'       => tinvwl_shortcode_view( [
					'paged'          => $post['tinvwl-paged'] ?? 1,
					'sharekey'       => false,
					'lists_per_page' => $post['tinvwl-per-page'] ?? 0,
				] ),
				'wishlists_data' => TInvWL_Public_AddToWishlist::instance()->get_wishlists_data( false ),
			];
			if ( tinv_get_option( 'table', 'hide_popup' ) ) {
				unset( $response['msg'] );
			}
			wp_send_json( $response );
			return;
		}
		// Check before hooks, product queries, cart changes, and response rendering.
		if ( ! $wl->can_view( $wishlist ) ) {
			wp_send_json( [
				'status' => false,
				'msg'    => __( 'Wishlist is not found!', 'ti-woocommerce-wishlist' ),
				'icon'   => 'icon_big_times',
			], 404 );
			return;
		}
		$wishlist['is_owner'] = $wl->is_owner( $wishlist );
		$guest_wishlist = $wl->is_owner( $guest_wishlist ) ? $guest_wishlist : [];

		do_action( 'tinvwl_ajax_actions_before', $wishlist, $post, $guest_wishlist );

		// A login transfer may have committed while the before-action callbacks ran.
		if ( ! empty( $wishlist['ID'] ) ) {
			$fresh = $wl->get_by_id( $wishlist['ID'] );
			if ( ! $fresh || ! $wl->can_view( $fresh ) ) {
				wp_send_json( array( 'status' => false, 'msg' => __( 'Wishlist is not found!', 'ti-woocommerce-wishlist' ), 'icon' => 'icon_big_times' ), 404 );
				return;
			}
			$wishlist = $fresh;
			$wishlist['is_owner'] = $wl->is_owner( $fresh );
		}

		$post['wishlist_qty'] = 1;
		$action               = $post['tinvwl-action'];
		$class                = TInvWL_Public_AddToWishlist::instance();
		$owner                = $wishlist && isset( $wishlist['is_owner'] ) && $wishlist['is_owner'];

		$response = [ 'status' => false, 'msg' => [] ];

		switch ( $action ) {
			case 'remove':
				if ( ! $wishlist['is_owner'] ) {
					$response['msg'][] = __( 'Something went wrong', 'ti-woocommerce-wishlist' );
					break;
				}

				$product = $post['tinvwl-product_id'];

				if ( 0 === $wishlist['ID'] ) {
					$wlp = TInvWL_Product_Local::instance( TINVWL_PREFIX, true );
				} else {
					$wlp = new TInvWL_Product( $wishlist, TINVWL_PREFIX, true );
				}

				if ( empty( $wlp ) ) {
					$response['msg'][] = __( 'Something went wrong', 'ti-woocommerce-wishlist' );
					break;
				}

				$product_data = $wlp->get_wishlist( array( 'ID' => $product ) );
				$product_data = array_shift( $product_data );

				if ( empty( $product_data ) ) {
					$response['msg'][] = __( 'Something went wrong', 'ti-woocommerce-wishlist' );
					break;
				}

				$title = sprintf(
					__( '&ldquo;%s&rdquo;', 'ti-woocommerce-wishlist' ),
					is_callable( array( $product_data['data'], 'get_name' ) )
						? $product_data['data']->get_name()
						: $product_data['data']->get_title()
				);

				if ( $wlp->remove( $product_data ) ) {
					$response['status'] = true;
					$response['msg'][]  = sprintf(
						__( '%s has been removed from the wishlist.', 'ti-woocommerce-wishlist' ),
						$title
					);
				} else {
					$response['status'] = false;
					$response['msg'][]  = sprintf(
						__( '%s has not been removed from the wishlist.', 'ti-woocommerce-wishlist' ),
						$title
					);
				}

				break;
			case 'add_to_cart_single':
				$product_id = $post['tinvwl-product_id'];

				if ( 0 === $wishlist['ID'] ) {
					$wlp = TInvWL_Product_Local::instance( TINVWL_PREFIX, true );
				} else {
					$wlp = new TInvWL_Product( $wishlist, TINVWL_PREFIX, true );
				}

				if ( empty( $wlp ) ) {
					$response['msg'][] = __( 'Something went wrong', 'ti-woocommerce-wishlist' );
					break;
				}

				$product_data = $wlp->get_wishlist( [ 'ID' => $product_id ] );
				$product_data = array_shift( $product_data );

				if ( empty( $product_data ) ) {
					$response['msg'][] = __( 'Something went wrong', 'ti-woocommerce-wishlist' );
					break;
				}

				$product_data_obj = $product_data['data'];
				$title            = sprintf(
					__( '&ldquo;%s&rdquo;', 'ti-woocommerce-wishlist' ),
					is_callable( [
						$product_data_obj,
						'get_name'
					] ) ? $product_data_obj->get_name() : $product_data_obj->get_title()
				);

				global $product;
				$_product_tmp = $product; // Store global product data.
				$product      = $product_data_obj; // Override global product data.

				add_filter( 'clean_url', 'tinvwl_clean_url', 10, 2 );
				$redirect_url = $product_data_obj->add_to_cart_url();
				remove_filter( 'clean_url', 'tinvwl_clean_url', 10 );

				$product = $_product_tmp; // Restore global product data.

				$quantity = apply_filters( 'tinvwl_product_add_to_cart_quantity', 1, $product_data_obj );

				if ( apply_filters( 'tinvwl_product_add_to_cart_need_redirect', false, $product_data_obj, $redirect_url, $product_data ) ) {
					$response['redirect'] = apply_filters( 'tinvwl_product_add_to_cart_redirect_url', $redirect_url, $product_data_obj, $product_data );

				} elseif ( apply_filters( 'tinvwl_allow_addtocart_in_wishlist', true, $wishlist, $owner ) ) {
					$add = TInvWL_Public_Cart::add( $wishlist, $product_id, $quantity );

					if ( $add && ! isset( $add['error_code'] ) ) {
						$response['status'] = true;
						$response['msg'][]  = sprintf(
							_n( '%s has been added to your cart.', '%s have been added to your cart.', 1, 'ti-woocommerce-wishlist' ),
							$title
						);

						if ( tinv_get_option( 'processing', 'redirect_checkout' ) ) {
							$response['redirect'] = wc_get_checkout_url();
						}

						if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
							$response['redirect'] = wc_get_cart_url();
						}
					} else {
						$response['status'] = false;
						$response['msg'][]  = TInvWL_Public_Cart::cart_all_errors_message( [ $add ] );
					}
				}

				break;

			case 'remove_selected':
				$ids = array_values( array_unique( array_map( static function ( $id ) { return (int) filter_var( $id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ); }, (array) ( $post['tinvwl-products'] ?? array() ) ) ) );
				$read_ids = array_values( array_filter( $ids ) );
				$results = $failed_titles = array();
				foreach ( $ids as $id ) {
					$results[ $id ] = array( 'status' => 'failed', 'error' => $owner ? ( $id > 0 ? 'item_unavailable' : 'invalid_item' ) : 'forbidden' );
					$failed_titles[ $id ] = sprintf( __( 'Item #%d', 'ti-woocommerce-wishlist' ), $id );
				}
				$response['removal_results'] = $results;
				if ( ! $owner || ! $read_ids ) {
					$response['outcome'] = 'failed';
					$response['error'] = $owner ? ( $ids ? 'invalid_selection' : 'no_items_selected' ) : 'forbidden';
					$response['msg'][] = __( 'Could not remove the selected items. Please refresh the wishlist and try again.', 'ti-woocommerce-wishlist' );
					break;
				}

				$wlp = 0 === $wishlist['ID'] ? TInvWL_Product_Local::instance( TINVWL_PREFIX, true ) : new TInvWL_Product( $wishlist, TINVWL_PREFIX, true );
				global $wpdb;
				$read_failed = false;
				try {
					$products = $wlp ? $wlp->get_wishlist( array( 'ID' => $read_ids, 'count' => 9999999 ) ) : false;
					$read_failed = ! is_array( $products ) || ! empty( $wpdb->last_error );
				} catch ( Throwable $error ) {
					$read_failed = true;
				}
				if ( $read_failed ) {
					$products = array();
					foreach ( $results as &$result ) { $result['error'] = 'read_failed'; }
					unset( $result );
				}

				$titles = $seen = array();
				foreach ( $products as $product ) {
					$id = (int) $product['ID'];
					if ( ! isset( $results[ $id ] ) || isset( $seen[ $id ] ) || empty( $product['data'] ) ) { continue; }
					$seen[ $id ] = true;
					$title = sprintf( __( '&ldquo;%s&rdquo;', 'ti-woocommerce-wishlist' ), is_callable( array( $product['data'], 'get_name' ) ) ? $product['data']->get_name() : $product['data']->get_title() );
					$failed_titles[ $id ] = $title;
					if ( $wlp->remove_item_from_wl( $product ) ) {
						$results[ $id ] = array( 'status' => 'removed' );
						$titles[] = $title;
						unset( $failed_titles[ $id ] );
					} else {
						$results[ $id ]['error'] = 'remove_failed';
					}
				}
				$response['removal_results'] = $results;
				if ( $titles ) {
					$response['msg'][] = sprintf( _n( '%s has been successfully removed from the wishlist.', '%s have been successfully removed from the wishlist.', count( $titles ), 'ti-woocommerce-wishlist' ), wc_format_list_of_items( $titles ) );
				}
				$response['status'] = empty( $failed_titles );
				$response['outcome'] = $failed_titles ? ( $titles ? 'partial' : 'failed' ) : 'removed';
				if ( $failed_titles ) {
					$response['error'] = $titles ? 'partial_remove' : 'remove_failed';
					$response['msg'][] = sprintf( __( 'Could not complete or confirm removal of %s. Please refresh the wishlist and check these items.', 'ti-woocommerce-wishlist' ), wc_format_list_of_items( array_values( $failed_titles ) ) );
				}

				break;
			case 'add_to_cart_selected':
				$products = $post['tinvwl-products'];

				$result = $errors = array();

				foreach ( $products as $id ) {
					$wishlist_product = null;

					if ( 0 === $wishlist['ID'] ) {
						$wishlist_product = TInvWL_Product_Local::instance( TINVWL_PREFIX, true );
					} else {
						$wishlist_product = new TInvWL_Product( $wishlist, TINVWL_PREFIX, true );
					}

					$wishlist_item = $wishlist_product->get_wishlist( array( 'ID' => $id ) );
					$wishlist_item = array_shift( $wishlist_item );

					$product_data = wc_get_product( $wishlist_item['variation_id'] ?: $wishlist_item['product_id'] );

					if ( ! TInvWL_Product::can_read_product( $product_data ) ) {
						continue;
					}

					global $product;
					// Store global product data.
					$previous_product = $product;
					// Override global product data.
					$product = $product_data;

					add_filter( 'clean_url', 'tinvwl_clean_url', 10, 2 );
					$redirect_url = $product_data->add_to_cart_url();
					remove_filter( 'clean_url', 'tinvwl_clean_url', 10 );

					// Restore global product data.
					$product = $previous_product;

					$quantity                  = apply_filters( 'tinvwl_product_add_to_cart_quantity', 1, $product_data );
					$wishlist_item['quantity'] = $quantity;

					if ( apply_filters( 'tinvwl_product_add_to_cart_need_redirect', false, $product_data, $redirect_url, $wishlist_item ) ) {
						$cart_errors = TInvWL_Public_Cart::add_to_cart_errors( $product_data, $quantity );
						$error_code  = $cart_errors['error_code'] ?? 'default';
						$errors[]    = array(
							'product'    => $product_data,
							'quantity'   => $quantity,
							'error_code' => $error_code,
						);
						continue;
					}

					$wishlist_item = $wishlist_item['ID'];
					$add           = TInvWL_Public_Cart::add( $wishlist, $wishlist_item, $quantity );

					if ( $add && ! isset( $add['error_code'] ) ) {
						$result[] = $add;
					} else {
						$errors[] = $add;
					}
				}

				if ( ! empty( $errors ) ) {
					$response['msg'][] = TInvWL_Public_Cart::cart_all_errors_message( $errors );
				}

				if ( ! empty( $result ) ) {
					$response['status'] = true;

					$titles = array();
					$count  = 0;

					foreach ( $result as $data ) {
						/* translators: %s: product name */
						$titles[] = apply_filters( 'woocommerce_add_to_cart_qty_html', ( 1.0 !== (float) $data['quantity'] ? esc_html( wc_format_localized_decimal( $data['quantity'] ) ) . ' &times; ' : '' ), $data['product']->get_id() ) . apply_filters( 'woocommerce_add_to_cart_item_name_in_quotes', sprintf( _x( '&ldquo;%s&rdquo;', 'Item name in quotes', 'ti-woocommerce-wishlist' ), $data['product']->get_name() ), $data['product']->get_id() );
						$count    += $data['quantity'];
					}

					$titles = array_filter( $titles );

					/* translators: %s: product name */
					$response['msg'][] = sprintf( _n( '%s has been added to your cart.', '%s have been added to your cart.', $count, 'ti-woocommerce-wishlist' ), wc_format_list_of_items( $titles ) );

					if ( tinv_get_option( 'processing', 'redirect_checkout' ) ) {
						$response['redirect'] = wc_get_checkout_url();
					}

					if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
						$response['redirect'] = wc_get_cart_url();
					}
				}

				break;
			case 'add_to_cart_all':
				add_filter( 'tinvwl_before_get_current_product', [
					'TInvWL_Public_Wishlist_Buttons',
					'get_all_products_fix_offset'
				] );
				$products = TInvWL_Public_Wishlist_Buttons::get_current_products( $wishlist, 9999999 );
				$result   = $errors = [];

				foreach ( $products as $_product ) {
					$product_data = wc_get_product( $_product['variation_id'] ?: $_product['product_id'] );

					if ( ! TInvWL_Product::can_read_product( $product_data ) ) {
						continue;
					}

					global $product;
					$_product_tmp = $product; // Store global product data.
					$product      = $product_data; // Override global product data.

					add_filter( 'clean_url', 'tinvwl_clean_url', 10, 2 );
					$redirect_url = $product_data->add_to_cart_url();
					remove_filter( 'clean_url', 'tinvwl_clean_url', 10 );

					$product = $_product_tmp; // Restore global product data.

					$quantity             = apply_filters( 'tinvwl_product_add_to_cart_quantity', 1, $product_data );
					$_product['quantity'] = $quantity;

					if ( apply_filters( 'tinvwl_product_add_to_cart_need_redirect', false, $product_data, $redirect_url, $_product ) ) {
						$cart_errors = TInvWL_Public_Cart::add_to_cart_errors( $product_data, $quantity );
						$error_code  = $cart_errors['error_code'] ?? 'default';
						$errors[]    = [
							'product'    => $product_data,
							'quantity'   => $quantity,
							'error_code' => $error_code,
						];
						continue;
					}

					$_product = $_product['ID'];

					$add = TInvWL_Public_Cart::add( $wishlist, $_product, $quantity );

					if ( $add && ! isset( $add['error_code'] ) ) {
						$result[] = $add;
					} else {
						$errors[] = $add;
					}
				}

				if ( ! empty( $errors ) ) {
					$response['msg'][] = TInvWL_Public_Cart::cart_all_errors_message( $errors );
				}

				if ( ! empty( $result ) ) {
					$response['status'] = true;

					$titles = [];
					$count  = 0;

					foreach ( $result as $data ) {
						/* translators: %s: product name */
						$titles[] = apply_filters( 'woocommerce_add_to_cart_qty_html', ( 1.0 !== (float) $data['quantity'] ? esc_html( wc_format_localized_decimal( $data['quantity'] ) ) . ' &times; ' : '' ), $data['product']->get_id() ) . apply_filters( 'woocommerce_add_to_cart_item_name_in_quotes', sprintf( _x( '&ldquo;%s&rdquo;', 'Item name in quotes', 'ti-woocommerce-wishlist' ), $data['product']->get_name() ), $data['product']->get_id() );
						$count    += $data['quantity'];
					}

					$titles = array_filter( $titles );

					/* translators: %s: product name */
					$response['msg'][] = sprintf( _n( '%s has been added to your cart.', '%s have been added to your cart.', $count, 'ti-woocommerce-wishlist' ), wc_format_list_of_items( $titles ) );

					if ( tinv_get_option( 'processing', 'redirect_checkout' ) ) {
						$response['redirect'] = wc_get_checkout_url();
					}

					if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
						$response['redirect'] = wc_get_cart_url();
					}
				}
				break;

			case 'get_data':
				$response['status'] = true;
				break;
		}

		$response['content'] = tinvwl_shortcode_view(
			[
				'paged'          => $post['tinvwl-paged'],
				'sharekey'       => $post['tinvwl-sharekey'],
				'lists_per_page' => $post['tinvwl-per-page'],
			]
		);
		$response['action']  = $action;
		$response['icon']    = $response['status'] ? 'icon_big_heart_check' : 'icon_big_times';
		$response['msg']     = array_unique( $response['msg'] );
		$response['msg']     = implode( '<br>', $response['msg'] );

		if ( tinv_get_option( 'table', 'hide_popup' ) && ( 'remove_selected' !== $action || $response['status'] ) ) {
			unset( $response['msg'] );
		}

		if ( ! empty( $response['msg'] ) ) {
			$response['msg'] = tinv_wishlist_template_html(
				'ti-addedtowishlist-dialogbox.php',
				apply_filters( 'tinvwl_addtowishlist_dialog_box', $response, $post )
			);
		}

		$share_key = false;

		if ( $guest_wishlist ) {
			$share_key = $guest_wishlist['share_key'];
		}

		$response['wishlists_data'] = $class->get_wishlists_data( $share_key );

		do_action( 'tinvwl_action_' . $action, $wishlist, $post['tinvwl-products'], $post['wishlist_qty'], $owner );
		do_action( 'tinvwl_ajax_actions_after', $wishlist, $post, $guest_wishlist );
		wp_send_json( $response );

	}
}
