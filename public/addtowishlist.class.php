<?php
/**
 * Add to wishlists shortcode and hooks
 *
 * @since             1.0.0
 * @package           TInvWishlist\Public
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Add to wishlists shortcode and hooks
 */
class TInvWL_Public_AddToWishlist {

	/**
	 * Plugin name
	 *
	 * @var string
	 */
	private $_name;

	/**
	 * Global product
	 *
	 * @var object
	 */
	private $product;
	/**
	 * This user wishlists
	 *
	 * @var array
	 */
	private $user_wishlist;

	/**
	 * This wishlists and product
	 *
	 * @var array
	 */
	private $wishlist;

	/**
	 * Check is loop button
	 *
	 * @var bolean
	 */
	private $is_loop;

	/**
	 * This class
	 *
	 * @var \TInvWL_Public_AddToWishlist
	 */
	protected static $_instance = null;

	/**
	 * Get this class object
	 *
	 * @param string $plugin_name Plugin name.
	 *
	 * @return \TInvWL_Public_AddToWishlist
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
		$this->_name   = $plugin_name;
		$this->is_loop = false;
		$this->define_hooks();
	}

	/**
	 * Defined shortcode and hooks
	 */
	function define_hooks() {
		switch ( tinv_get_option( 'add_to_wishlist', 'position' ) ) {
			case 'before':
				add_action( 'tinvwl_before_add_to_cart_button', 'tinvwl_view_addto_html' );
				add_action( 'tinvwl_single_product_summary', 'tinvwl_view_addto_htmlout' );
				add_action( 'woocommerce_before_add_to_cart_button', 'tinvwl_view_addto_html', 20 );
				add_action( 'woocommerce_single_product_summary', 'tinvwl_view_addto_htmlout', 29 );
				add_action( 'catalog_visibility_before_alternate_add_to_cart_button', 'tinvwl_view_addto_html' );
				break;
			case 'after':
				add_action( 'tinvwl_after_add_to_cart_button', 'tinvwl_view_addto_html' );
				add_action( 'tinvwl_single_product_summary', 'tinvwl_view_addto_htmlout' );
				add_action( 'woocommerce_after_add_to_cart_button', 'tinvwl_view_addto_html', 0 );
				add_action( 'woocommerce_single_product_summary', 'tinvwl_view_addto_htmlout', 31 );
				add_action( 'catalog_visibility_after_alternate_add_to_cart_button', 'tinvwl_view_addto_html' );
				break;
		}
		if ( tinv_get_option( 'add_to_wishlist_catalog', 'show_in_loop' ) ) {
			switch ( tinv_get_option( 'add_to_wishlist_catalog', 'position' ) ) {
				case 'before':
					add_action( 'tinvwl_after_shop_loop_item', 'tinvwl_view_addto_htmlloop' );
					add_action( 'woocommerce_after_shop_loop_item', 'tinvwl_view_addto_htmlloop', 9 );
					break;
				case 'above_thumb':
					add_action( 'tinvwl_above_thumb_loop_item', 'tinvwl_view_addto_htmlloop' );
					add_action( 'woocommerce_before_shop_loop_item', 'tinvwl_view_addto_htmlloop', 9 );
				case 'shortcode':
					break;
				case 'after':
				default: // Compatibility with previous versions.
					add_action( 'tinvwl_after_shop_loop_item', 'tinvwl_view_addto_htmlloop' );
					add_action( 'woocommerce_after_shop_loop_item', 'tinvwl_view_addto_htmlloop' );
					break;
			}
		}

		add_action( 'wp_loaded', array( $this, 'add_to_wishlist' ), 0 );
	}

	/**
	 * Action add product to wishlist
	 *
	 * @return boolean
	 */
	function add_to_wishlist() {
		if ( is_null( filter_input( INPUT_POST, 'tinv_wishlist_id' ) ) ) {
			return false;
		} else {
			remove_action( 'init', 'woocommerce_add_to_cart_action' );
			remove_action( 'wp_loaded', 'WC_Form_Handler::add_to_cart_action', 20 );
		}
		ob_start();
		$post = filter_input_array( INPUT_POST, array(
			'tinv_wishlist_id'   => FILTER_VALIDATE_INT,
			'tinv_wishlist_name' => FILTER_SANITIZE_STRING,
			'product_id'         => FILTER_VALIDATE_INT,
			'product_variation'  => FILTER_VALIDATE_INT,
			'product_type'       => FILTER_SANITIZE_STRING,
			'product_action'     => FILTER_SANITIZE_STRING,
		) );

		$wlp      = null;
		$wishlist = null;
		$data     = array( 'msg' => array() );
		if ( is_user_logged_in() ) {
			$wl       = new TInvWL_Wishlist( $this->_name );
			$wishlist = $wl->add_user_default();
			$wishlist = apply_filters( 'tinvwl_addtowishlist_wishlist', $wishlist );
			if ( empty( $wishlist ) ) {
				$data['status'] = false;
				$data           = apply_filters( 'tinvwl_addtowishlist_return_ajax', $data, $post );
				ob_clean();
				wp_send_json( $data );
			}
			$wlp = new TInvWL_Product( $wishlist, $this->_name );
		} elseif ( tinv_get_option( 'general', 'require_login' ) ) {
			$data['status'] = false;
			$data['icon']   = 'icon_big_times';
			if ( tinv_get_option( 'general', 'redirect_require_login' ) ) {
				$data['msg']            = array();
				$data['force_redirect'] = apply_filters( 'tinvwl_addtowishlist_login_page', wc_get_page_permalink( 'myaccount' ), $post );
			} else {
				$data['msg'][]              = __( 'Please, login to add products to Wishlist', 'ti-woocommerce-wishlist' );
				$data['dialog_custom_url']  = apply_filters( 'tinvwl_addtowishlist_login_page', wc_get_page_permalink( 'myaccount' ), $post );
				$data['dialog_custom_html'] = '<i class="ftinvwl ftinvwl-key"></i>' . esc_html( __( 'Login', 'ti-woocommerce-wishlist' ) );
			}
			$data['msg'] = array_unique( $data['msg'] );
			$data['msg'] = implode( '<br>', $data['msg'] );
			if ( ! empty( $data['msg'] ) ) {
				$data['msg'] = tinv_wishlist_template_html( 'ti-addedtowishlist-dialogbox.php', apply_filters( 'tinvwl_addtowishlist_dialog_box', $data, $post ) );
			}
			$data = apply_filters( 'tinvwl_addtowishlist_return_ajax', $data, $post );
			ob_clean();
			wp_send_json( $data );
		} else {
			$wl       = new TInvWL_Wishlist( $this->_name );
			$wishlist = $wl->add_sharekey_default();
			$wlp      = new TInvWL_Product( $wishlist );
		}

		$status = true;
		if ( empty( $post['product_id'] ) ) {
			$status = false;
		} else {
			$post['product_type'] = apply_filters( $this->_name . '_addtowishlist_modify_type', $post['product_type'], $post );
			$post                 = apply_filters( 'tinvwl_addtowishlist_prepare', $post );
			$form                 = apply_filters( 'tinvwl_addtowishlist_prepare_form', filter_input( INPUT_POST, 'form', FILTER_DEFAULT, FILTER_FORCE_ARRAY ) );
			if ( empty( $form ) ) {
				$form = array();
			}
			switch ( $post['product_type'] ) {
				case 'group':
				case 'grouped' :
					$product = $wlp->product_data( $post['product_id'] );
					if ( empty( $product ) ) {
						$status = false;
					} else {
						$variations = $product->get_children();

						foreach ( $variations as $variation_id ) {
							$quantity       = 1;
							$allowed_adding = ! count( $wlp->get_wishlist( array(
								'product_id'   => $post['product_id'],
								'variation_id' => $variation_id,
								'external'     => false,
							) ) );
							if ( tinv_get_option( 'general', 'simple_flow' ) && 'remove' === $post['product_action'] ) {
								if ( $wlp->remove_product_from_wl( 0, $post['product_id'], $variation_id, apply_filters( 'tinvwl_addtowishlist_add_form', $form ) ) ) {
									$data['msg'][]   = apply_filters( 'tinvwl-general-text_removed_from', tinv_get_option( 'general', 'text_removed_from' ) );
									$data['removed'] = true;
									$status          = false;
								}
							} elseif ( ! $allowed_adding ) {
								$data['msg'][] = apply_filters( 'tinvwl-general-text_already_in', tinv_get_option( 'general', 'text_already_in' ) );
								$status        = false;
							} elseif ( $wlp->add_product( apply_filters( 'tinvwl_addtowishlist_add', array(
								'product_id'   => $post['product_id'],
								'variation_id' => $variation_id,
								'quantity'     => $quantity,
							) ) )
							) {
								$data['msg'][] = apply_filters( 'tinvwl-general-text_added_to', tinv_get_option( 'general', 'text_added_to' ) );
							} else {
								$status = false;
							}
						}
					}
					break;
				case 'variable' :
				case 'variation' :

					if ( $post['product_variation'] ) {
						$variation_id = $post['product_variation'];
					} else {
						$variation_id = absint( array_key_exists( 'variation_id', $form ) ? filter_var( $form['variation_id'], FILTER_VALIDATE_INT ) : 0 );
					}
					$quantity       = 1;
					$allowed_adding = ! count( $wlp->get_wishlist( array(
						'product_id'   => $post['product_id'],
						'variation_id' => $variation_id,
						'meta'         => apply_filters( 'tinvwl_addtowishlist_add_form', $form ),
						'external'     => false,
					) ) );
					if ( tinv_get_option( 'general', 'simple_flow' ) && 'remove' === $post['product_action'] ) {
						if ( $wlp->remove_product_from_wl( 0, $post['product_id'], $variation_id, apply_filters( 'tinvwl_addtowishlist_add_form', $form ) ) ) {
							$data['msg'][]   = apply_filters( 'tinvwl-general-text_removed_from', tinv_get_option( 'general', 'text_removed_from' ) );
							$data['removed'] = true;
							$status          = false;
						}
					} elseif ( ! $allowed_adding ) {
						$data['msg'][] = apply_filters( 'tinvwl-general-text_already_in', tinv_get_option( 'general', 'text_already_in' ) );
						$status        = false;
					} elseif ( $wlp->add_product( apply_filters( 'tinvwl_addtowishlist_add', array(
						'product_id'   => $post['product_id'],
						'quantity'     => $quantity,
						'variation_id' => $variation_id,
					) ), apply_filters( 'tinvwl_addtowishlist_add_form', $form ) ) ) {
						$data['msg'][] = apply_filters( 'tinvwl-general-text_added_to', tinv_get_option( 'general', 'text_added_to' ) );
					} else {
						$status = false;
					}
					break;
				case 'simple' :
				default:
					$quantity       = 1;
					$allowed_adding = ! count( $wlp->get_wishlist( array(
						'product_id' => $post['product_id'],
						'meta'       => apply_filters( 'tinvwl_addtowishlist_add_form', $form ),
						'external'   => false,
					) ) );
					if ( tinv_get_option( 'general', 'simple_flow' ) && 'remove' === $post['product_action'] ) {
						if ( $wlp->remove_product_from_wl( 0, $post['product_id'], 0, apply_filters( 'tinvwl_addtowishlist_add_form', $form ) ) ) {
							$data['msg'][]   = apply_filters( 'tinvwl-general-text_removed_from', tinv_get_option( 'general', 'text_removed_from' ) );
							$data['removed'] = true;
							$status          = false;
						}
					} elseif ( ! $allowed_adding ) {
						$data['msg'][] = apply_filters( 'tinvwl-general-text_already_in', tinv_get_option( 'general', 'text_already_in' ) );
						$status        = false;
					} elseif ( $wlp->add_product( apply_filters( 'tinvwl_addtowishlist_add', array(
						'product_id' => $post['product_id'],
						'quantity'   => $quantity,
					) ), apply_filters( 'tinvwl_addtowishlist_add_form', $form ) ) ) {
						$data['msg'][] = apply_filters( 'tinvwl-general-text_added_to', tinv_get_option( 'general', 'text_added_to' ) );
					} else {
						$status = false;
					}
					break;
			} // End switch().
		} // End if().
		$data['status']       = $status;
		$data['wishlist_url'] = tinv_url_wishlist_default();
		if ( ! empty( $wishlist ) ) {
			$data['wishlist_url'] = tinv_url_wishlist( $wishlist['ID'] );
		}

		if ( $status && tinv_get_option( 'general', 'redirect' ) && tinv_get_option( 'page', 'wishlist' ) && tinv_get_option( 'general', 'show_notice' ) ) {
			$data['redirect'] = $data['wishlist_url'];
		}

		$product           = wc_get_product( $post['product_id'] );
		$data['wishlists'] = wp_json_encode( $this->user_wishlist( $product, $wlp ) );

		$data['icon'] = $data['status'] ? 'icon_big_heart_check' : 'icon_big_times';
		$data['msg']  = array_unique( $data['msg'] );
		$data['msg']  = implode( '<br>', $data['msg'] );
		if ( ! empty( $data['msg'] ) ) {
			$data['msg'] = apply_filters( $this->_name . '_addtowishlist_message_after', $data['msg'], $data, $post, $form, $product );
			$data['msg'] = tinv_wishlist_template_html( 'ti-addedtowishlist-dialogbox.php', apply_filters( 'tinvwl_addtowishlist_dialog_box', $data, $post ) );
		}
		if ( ! tinv_get_option( 'general', 'show_notice' ) && array_key_exists( 'msg', $data ) ) {
			unset( $data['msg'] );
		}
		if ( tinv_get_option( 'general', 'simple_flow' ) ) {
			$data['make_remove'] = $data['status'];
		}
		$data = apply_filters( 'tinvwl_addtowishlist_return_ajax', $data, $post, $form, $product );
		ob_clean();
		wp_send_json( $data );
	}

	/**
	 * Get user wishlist
	 *
	 * @return array
	 */
	function user_wishlists() {
		if ( ! empty( $this->user_wishlist ) ) {
			return $this->user_wishlist;
		}
		$wishlists = array();
		$wl        = new TInvWL_Wishlist( $this->_name );
		if ( is_user_logged_in() ) {
			$wishlists = $wl->get_by_user_default();
		} else {
			$wishlists = $wl->get_by_sharekey_default();
		}
		$wishlists = array_filter( $wishlists );
		if ( ! empty( $wishlists ) ) {
			$_wishlists = array();
			foreach ( $wishlists as $key => $wishlist ) {
				if ( is_array( $wishlist ) && array_key_exists( 'ID', $wishlist ) ) {
					$_wishlists[ $key ] = array(
						'ID'    => $wishlist['ID'],
						'title' => $wishlist['title'],
						'url'   => tinv_url_wishlist_by_key( $wishlist['share_key'] ),
					);
				}
			}
			$wishlists = $_wishlists;
		}
		$this->user_wishlist = $wishlists;

		return $wishlists;
	}

	/**
	 * Check exists product in user wishlists
	 *
	 * @param object $product Product object.
	 * @param object $wlp Product class, used for local products.
	 *
	 * @return array
	 */
	function user_wishlist( $product, $wlp = null ) {
		$wishlists = $this->wishlist = array();
		$vproduct  = $product->is_type( 'variation' ) || $product->is_type( 'variable' );
		$wlp       = new TInvWL_Product();
		$wishlists = $this->user_wishlists();
		$ids       = array();
		foreach ( $wishlists as $key => $wishlist ) {
			$ids[] = $wishlist['ID'];
		}
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			return $wishlists;
		}
		$products = $wlp->get( array(
			'product_id'  => ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->id : ( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() ) ),
			'wishlist_id' => $ids,
			'external'    => false,
		) );
		$in       = array();
		if ( ! empty( $products ) ) {
			foreach ( $products as $product ) {
				$in[ $product['wishlist_id'] ][] = $product['variation_id'];
			}
			foreach ( $in as $wishlist_id => $products ) {
				sort( $in[ $wishlist_id ], SORT_NUMERIC );
				if ( empty( $in[ $wishlist_id ] ) && ( $this->is_loop || ! $vproduct ) ) {
					$in[ $wishlist_id ] = true;
				}
			}
		}
		foreach ( $wishlists as $key => $wishlist ) {
			$wishlists[ $key ]['in'] = array_key_exists( $wishlist['ID'], $in ) ? $in[ $wishlist['ID'] ] : false;
		}
		$wishlists      = apply_filters( 'tinvwl_addtowishlist_preparewishlists', $wishlists, $product );
		$this->wishlist = $wishlists;

		return $wishlists;
	}

	/**
	 * Create add button in loop
	 *
	 * @global object $product
	 */
	function htmloutput_loop() {
		global $product;

		if ( $product ) {
			if ( apply_filters( 'tinvwl_allow_addtowishlist_shop_loop_item', true, $product ) ) { // @codingStandardsIgnoreLine WordPress.PHP.StrictInArray.MissingTrueStrict
				$this->is_loop = true;
				$this->htmloutput();
				$this->is_loop = false;
			}
		}
	}

	/**
	 * Create add button if simple product out stock
	 *
	 * @global object $product
	 */
	function htmloutput_out() {
		global $product;

		if ( $product ) {
			$allow = false;
			if ( 'simple' === ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->product_type : $product->get_type() ) ) {
				$allow = ( ( ! $product->is_purchasable() && '' == $product->get_price() ) || ( $product->is_purchasable() && ! $product->is_in_stock() ) );
			}

			if ( 'variable' === ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->product_type : $product->get_type() ) ) {
				$get_variations       = count( $product->get_children() ) <= apply_filters( 'woocommerce_ajax_variation_threshold', 30, $product );
				$available_variations = $get_variations ? $product->get_available_variations() : false;
				$allow                = ( empty( $available_variations ) && false !== $available_variations );
			}

			if ( apply_filters( 'tinvwl_allow_addtowishlist_single_product_summary', $allow, $product ) ) {
				$this->htmloutput();
			}
		}
	}

	/**
	 * Output page
	 *
	 * @global object $product
	 *
	 * @param array $attr Array parameter for shortcode.
	 * @param boolean $is_shortcode Shortcode or action.
	 *
	 * @return boolean
	 */
	function htmloutput( $attr = array(), $is_shortcode = false ) {
		global $product;

		$attr          = apply_filters( 'tinvwl_addtowishlist_out_prepare_attr', $attr );
		$this->product = apply_filters( 'tinvwl_addtowishlist_out_prepare_product', $product );
		$position      = tinv_get_option( 'add_to_wishlist', 'position' );
		if ( $is_shortcode ) {
			$position     = 'shortcode';
			$product_id   = absint( $attr['product_id'] );
			$variation_id = absint( $attr['variation_id'] );

			if ( 'product_variation' == get_post_type( $product_id ) ) { // WPCS: loose comparison ok.
				$variation_id = $product_id;
				$product_id   = wp_get_post_parent_id( $variation_id );
			}

			$product_data = wc_get_product( $variation_id ? $variation_id : $product_id );

			if ( $product_data && 'trash' !== ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $product_data->post->post_status : get_post( $product_data->get_id() )->post_status ) ) {
				$this->product = $product_data;
			} else {
				return '';
			}
		}
		if ( empty( $this->product ) || ! apply_filters( 'tinvwl_allow_addtowishlist_single_product', true, $this->product ) ) {
			return;
		}

		$wishlists = $this->user_wishlist( $this->product );

		add_action( 'tinv_wishlist_addtowishlist_button', array( $this, 'button' ) );

		if ( $this->is_loop && 'variable' === ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $this->product->product_type : $this->product->get_type() ) ) {
			$this->variation_id = null;
			$match_attributes   = array();

			foreach ( ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $this->product->get_variation_default_attributes() : $this->product->get_default_attributes() ) as $attribute_name => $value ) {
				$match_attributes[ 'attribute_' . sanitize_title( $attribute_name ) ] = $value;
			}

			if ( $match_attributes ) {
				if ( version_compare( WC_VERSION, '3.0.0', '<' ) ) {
					$this->variation_id = $this->product->get_matching_variation( $match_attributes );
				} else {
					$data_store         = WC_Data_Store::load( 'product' );
					$this->variation_id = $data_store->find_matching_product_variation( $this->product, $match_attributes );
				}
			}
		}

		$data = array(
			'class_postion'       => sprintf( 'tinvwl-%s-add-to-cart', $this->is_loop ? tinv_get_option( 'add_to_wishlist_catalog', 'position' ) : $position ) . ( $this->is_loop ? ' tinvwl-loop-button-wrapper' : '' ),
			'product'             => $this->product,
			'variation_id'        => ( $this->is_loop && 'variable' === ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $this->product->product_type : $this->product->get_type() ) ) ? $this->variation_id : ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $this->product->variation_id : ( $this->product->is_type( 'variation' ) ? $this->product->get_id() : 0 ) ),
			'TInvWishlist'        => $wishlists,
			'button_icon'         => tinv_get_option( 'add_to_wishlist' . ( $this->is_loop ? '_catalog' : '' ), 'icon' ),
			'add_to_wishlist'     => tinv_get_option( 'add_to_wishlist' . ( $this->is_loop ? '_catalog' : '' ), 'text' ),
			'browse_in_wishlist'  => apply_filters( 'tinvwl-general-text_browse', tinv_get_option( 'general', 'text_browse' ) ),
			'product_in_wishlist' => apply_filters( 'tinvwl-general-text_already_in', tinv_get_option( 'general', 'text_already_in' ) ),
			'product_to_wishlist' => apply_filters( 'tinvwl-general-text_added_to', tinv_get_option( 'general', 'text_added_to' ) ),
			'loop'                => $this->is_loop,
		);
		tinv_wishlist_template( 'ti-addtowishlist.php', $data );
	}

	/**
	 * Create button
	 *
	 * @param boolean $echo Return or output.
	 */
	function button( $echo = true ) {
		$content    = apply_filters( 'tinvwl_wishlist_button_before', '' );
		$text       = ( tinv_get_option( 'add_to_wishlist' . ( $this->is_loop ? '_catalog' : '' ), 'show_text' ) ) ? apply_filters( 'tinvwl-add_to_wishlist_catalog-text', tinv_get_option( 'add_to_wishlist' . ( $this->is_loop ? '_catalog' : '' ), 'text' ) ) : '';
		$icon       = tinv_get_option( 'add_to_wishlist' . ( $this->is_loop ? '_catalog' : '' ), 'icon' );
		$icon_color = tinv_get_option( 'add_to_wishlist' . ( $this->is_loop ? '_catalog' : '' ), 'icon_style' );
		$icon_class = '';
		$action     = 'addto';
		if ( empty( $text ) ) {
			$icon_class = ' no-txt';
		} else {
			$content .= '<div class="tinv-wishlist-clear"></div>';
			if ( tinv_get_option( 'general', 'simple_flow' ) ) {
				$text = sprintf( '<span class="tinvwl_add_to_wishlist-text">%s</span><span class="tinvwl_remove_from_wishlist-text">%s</span>', $text, apply_filters( 'tinvwl-add_to_wishlist_catalog-text_remove', tinv_get_option( 'add_to_wishlist' . ( $this->is_loop ? '_catalog' : '' ), 'text_remove' ) ) );
			} else {
				$text = sprintf( '<span class="tinvwl_add_to_wishlist-text">%s</span>', $text );
			}
		}
		if ( ! empty( $icon ) ) {
			$icon_upload = tinv_get_option( 'add_to_wishlist' . ( $this->is_loop ? '_catalog' : '' ), 'icon_upload' );
			if ( 'custom' === $icon && ! empty( $icon_upload ) ) {
				$text = sprintf( '<img src="%s" alt="%s" /> %s', esc_url( $icon_upload ), esc_attr( apply_filters( 'tinvwl-add_to_wishlist_catalog-text', tinv_get_option( 'add_to_wishlist' . ( $this->is_loop ? '_catalog' : '' ), 'text' ) ) ), $text );
			}
			$icon = 'tinvwl-icon-' . $icon;
			if ( 'custom' !== $icon && $icon_color ) {
				$icon .= ' icon-' . $icon_color;
			}
		}
		$icon         .= $icon_class;
		$variation_id = ( ( $this->is_loop && 'variable' === ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $this->product->product_type : $this->product->get_type() ) ) ? $this->variation_id : ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $this->product->variation_id : ( $this->product->is_type( 'variation' ) ? $this->product->get_id() : 0 ) ) );
		foreach ( $this->wishlist as $value ) {
			if ( $value['in'] && in_array( $variation_id, $value['in'] ) ) {
				$icon .= ' tinvwl-product-in-list';
				if ( tinv_get_option( 'general', 'simple_flow' ) ) {
					if ( $this->is_loop ) {
						if ( ! is_array( $value['in'] ) || in_array( $variation_id, $value['in'] ) ) {
							$icon   .= ' tinvwl-product-make-remove';
							$action = 'remove';
						}
					} else {
						$icon   .= ' tinvwl-product-make-remove';
						$action = 'remove';
					}
				}
				break;
			}
		}

		$icon .= ' ' . tinv_get_option( 'add_to_wishlist' . ( $this->is_loop ? '_catalog' : '' ), 'class' );

		$icon .= ' tinvwl-position-' . tinv_get_option( 'add_to_wishlist' . ( $this->is_loop ? '_catalog' : '' ), 'position' );

		$icon .= ( tinv_get_option( 'add_to_wishlist' . ( $this->is_loop ? '_catalog' : '' ), 'show_preloader' ) ) ? ' ftinvwl-animated' : '';

		$content .= sprintf( '<a class="tinvwl_add_to_wishlist_button %s" data-tinv-wl-list="%s" data-tinv-wl-product="%s" data-tinv-wl-productvariation="%s" data-tinv-wl-producttype="%s" data-tinv-wl-action="%s" rel="nofollow">%s</a>', $icon, htmlspecialchars( wp_json_encode( $this->wishlist ) ), ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $this->product->id : ( $this->product->is_type( 'variation' ) ? $this->product->get_parent_id() : $this->product->get_id() ) ), ( ( $this->is_loop && 'variable' === ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $this->product->product_type : $this->product->get_type() ) ) ? $this->variation_id : ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $this->product->variation_id : ( $this->product->is_type( 'variation' ) ? $this->product->get_id() : 0 ) ) ), ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $this->product->product_type : $this->product->get_type() ), $action, $text );
		$content .= apply_filters( 'tinvwl_wishlist_button_after', '' );

		if ( ! empty( $text ) ) {
			$content .= '<div class="tinv-wishlist-clear"></div>';
		}

		echo apply_filters( 'tinvwl_wishlist_button', $content ); // WPCS: xss ok.
	}

	/**
	 * Shortcode basic function
	 *
	 * @global object $product
	 *
	 * @param array $atts Array parameter from shortcode.
	 *
	 * @return string
	 */
	function shortcode( $atts = array() ) {
		global $product;

		$default = array(
			'product_id'   => 0,
			'variation_id' => 0,
			'loop'         => 'no',
		);
		if ( $product && is_a( $product, 'WC_Product' ) ) {
			$default['product_id']   = ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->get_id() : ( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() ) );
			$default['variation_id'] = ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->variation_id : ( $product->is_type( 'variation' ) ? $product->get_id() : 0 ) );
		}
		$atts = shortcode_atts( $default, $atts );

		ob_start();
		if ( 'yes' === $atts['loop'] ) {
			$this->is_loop = true;
			$this->htmloutput( $atts, true );
			$this->is_loop = false;
		} else {
			$this->htmloutput( $atts, true );
		}

		return ob_get_clean();
	}

}
