<?php
/**
 * Support functions for other plugins
 *
 * @since             1.5.0
 * @package           TInvWishlist
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! function_exists( 'tinvwl_rocket_reject_cookies' ) ) {

	/**
	 * Disable cache for WP Rocket
	 *
	 * @param array $cookies Cookies.
	 *
	 * @return array
	 */
	function tinvwl_rocket_reject_cookies( $cookies = array() ) {
		$cookies[] = 'tinv_wishlist';

		return $cookies;
	}

	add_filter( 'rocket_cache_reject_cookies', 'tinvwl_rocket_reject_cookies' );
}

if ( ! function_exists( 'tinvwl_wp_fastest_cache_reject' ) ) {

	/**
	 * Disable cache for WP Fastest Cache
	 */
	function tinvwl_wp_fastest_cache_reject() {
		if ( defined( 'WPFC_WP_PLUGIN_DIR' ) ) {
			if ( $rules_json = get_option( 'WpFastestCacheExclude' ) ) {
				if ( 'null' !== $rules_json ) {
					$ids       = array(
						tinv_get_option( 'page', 'wishlist' ),
						tinv_get_option( 'page', 'manage' ),
					);
					$pages     = $ids;
					$languages = apply_filters( 'wpml_active_languages', array(), array(
						'skip_missing' => 0,
						'orderby'      => 'code',
					) );
					if ( ! empty( $languages ) ) {
						foreach ( $ids as $id ) {
							foreach ( $languages as $l ) {
								$pages[] = apply_filters( 'wpml_object_id', $id, 'page', true, $l['language_code'] );
							}
						}
						$pages = array_unique( $pages );
					}
					$pages = array_filter( $pages );
					if ( ! empty( $pages ) ) {
						foreach ( $pages as $i => $page ) {
							$pages[ $i ] = preg_replace( "/^\//", '', str_replace( get_site_url(), '', get_permalink( $page ) ) ); // @codingStandardsIgnoreLine Squiz.Strings.DoubleQuoteUsage.NotRequired
						}
					}
					$pages = array_unique( $pages );
					$pages = array_filter( $pages );

					$rules_std = json_decode( $rules_json, true );
					$ex_pages  = array();
					foreach ( $rules_std as $value ) {
						$value['type'] = isset( $value['type'] ) ? $value['type'] : 'page';
						if ( 'page' === $value['type'] ) {
							$ex_pages[] = $value['content'];
						}
					}
					$ex_pages = array_unique( $ex_pages );
					$ex_pages = array_filter( $ex_pages );
					$changed  = false;

					foreach ( $pages as $page ) {
						$page = preg_replace( '/\/$/', '', $page );

						if ( ! in_array( $page, $ex_pages ) ) {
							$changed     = true;
							$rules_std[] = array(
								'prefix'  => 'startwith',
								'content' => $page,
								'type'    => 'page',
							);
						}
					}
					if ( $changed ) {
						$data = json_encode( $rules_std );
						update_option( 'WpFastestCacheExclude', $data );
					}
				} // End if().
			} // End if().
		} // End if().
	}

	add_action( 'admin_init', 'tinvwl_wp_fastest_cache_reject' );
} // End if().

if ( function_exists( 'tinvwl_comet_cache_reject' ) ) {

	/**
	 * Set define disabled for Comet Cache
	 *
	 * @param mixed $data Any content.
	 *
	 * @return mixed
	 */
	function tinvwl_comet_cache_reject( $data = '' ) {
		define( 'COMET_CACHE_ALLOWED', false );

		return $data;
	}

	add_filter( 'tinvwl_addtowishlist_return_ajax', 'tinvwl_comet_cache_reject' );
	add_action( 'tinvwl_before_action_owner', 'tinvwl_comet_cache_reject' );
	add_action( 'tinvwl_before_action_user', 'tinvwl_comet_cache_reject' );
	add_action( 'tinvwl_addproduct_tocart', 'tinvwl_comet_cache_reject' );
	add_action( 'tinv_wishlist_addtowishlist_button', 'tinvwl_comet_cache_reject' );
	add_action( 'tinv_wishlist_addtowishlist_dialogbox', 'tinvwl_comet_cache_reject' );
}

if ( ! function_exists( 'gf_productaddon_support' ) ) {

	/**
	 * Add supports WooCommerce - Gravity Forms Product Add-Ons
	 */
	function gf_productaddon_support() {
		if ( ! class_exists( 'woocommerce_gravityforms' ) ) {
			return false;
		}
		if ( ! function_exists( 'gf_productaddon_text_button' ) ) {

			/**
			 * Change text for button add to cart
			 *
			 * @param string $text_add_to_cart Text "Add to cart".
			 * @param array $wl_product Wishlist product.
			 * @param object $product WooCommerce Product.
			 *
			 * @return string
			 */
			function gf_productaddon_text_button( $text_add_to_cart, $wl_product, $product ) {
				$gravity_form_data = get_post_meta( ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->id : ( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() ) ), '_gravity_form_data', true );

				return ( $gravity_form_data ) ? __( 'Select options', 'ti-woocommerce-wishlist' ) : $text_add_to_cart;
			}

			add_filter( 'tinvwl_wishlist_item_add_to_cart', 'gf_productaddon_text_button', 10, 3 );
		}

		if ( ! function_exists( 'gf_productaddon_run_action_button' ) ) {

			/**
			 * Check for make redirect to url
			 *
			 * @param boolean $need Need redirect or not.
			 * @param object $product WooCommerce Product.
			 *
			 * @return boolean
			 */
			function gf_productaddon_run_action_button( $need, $product ) {
				$gravity_form_data = get_post_meta( ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->id : ( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() ) ), '_gravity_form_data', true );

				return ( $gravity_form_data ) ? true : $need;
			}

			add_filter( 'tinvwl_product_add_to_cart_need_redirect', 'gf_productaddon_run_action_button', 10, 2 );
		}

		if ( ! function_exists( 'gf_productaddon_action_button' ) ) {

			/**
			 * Redirect url
			 *
			 * @param string $url Redirect URL.
			 * @param object $product WooCommerce Product.
			 *
			 * @return string
			 */
			function gf_productaddon_action_button( $url, $product ) {
				$gravity_form_data = get_post_meta( ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->id : ( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() ) ), '_gravity_form_data', true );

				return ( $gravity_form_data ) ? $product->get_permalink() : $url;
			}

			add_filter( 'tinvwl_product_add_to_cart_redirect_url', 'gf_productaddon_action_button', 10, 2 );
		}
	}

	add_action( 'init', 'gf_productaddon_support' );
} // End if().

if ( ! function_exists( 'tinvwl_wpml_product_get' ) ) {

	/**
	 * Change product data if product need translate
	 *
	 * @param array $product Wishlistl product.
	 *
	 * @return array
	 */
	function tinvwl_wpml_product_get( $product ) {
		if ( array_key_exists( 'data', $product ) ) {
			$_product_id   = $product_id = $product['product_id'];
			$_variation_id = $variation_id = $product['variation_id'];
			$_product_id   = apply_filters( 'wpml_object_id', $_product_id, 'product', true );
			if ( ! empty( $_variation_id ) ) {
				$_variation_id = apply_filters( 'wpml_object_id', $_variation_id, 'product', true );
			}
			if ( $_product_id !== $product_id || $_variation_id !== $variation_id ) {
				$product['data'] = wc_get_product( $variation_id ? $_variation_id : $_product_id );
			}
		}

		return $product;
	}

	add_filter( 'tinvwl_wishlist_product_get', 'tinvwl_wpml_product_get' );
}

if ( ! function_exists( 'tinvwl_wpml_addtowishlist_prepare' ) ) {

	/**
	 * Change product data if product need translate in WooCommerce Multilingual
	 *
	 * @param array $post_data Data for wishlist.
	 *
	 * @return array
	 */
	function tinvwl_wpml_addtowishlist_prepare( $post_data ) {
		if ( class_exists( 'woocommerce_wpml' ) ) {

			global $woocommerce_wpml, $sitepress, $wpdb;

			// Reload products class.
			if ( version_compare( WCML_VERSION, '4.4.0', '<' ) ) {
				$woocommerce_wpml->products = new WCML_Products( $woocommerce_wpml, $sitepress, $wpdb );
			} else {
				global $wpml_post_translations;
				$woocommerce_wpml->products = new WCML_Products( $woocommerce_wpml, $sitepress, $wpml_post_translations, $wpdb );
			}

			if ( array_key_exists( 'product_id', $post_data ) && ! empty( $post_data['product_id'] ) ) {
				$post_data['product_id'] = $woocommerce_wpml->products->get_original_product_id( $post_data['product_id'] );
			}
			if ( array_key_exists( 'product_id', $post_data ) && ! empty( $post_data['product_id'] ) && array_key_exists( 'product_variation', $post_data ) && ! empty( $post_data['product_variation'] ) ) {
				$original_product_language      = $woocommerce_wpml->products->get_original_product_language( $post_data['product_id'] );
				$post_data['product_variation'] = apply_filters( 'translate_object_id', $post_data['product_variation'], 'product_variation', true, $original_product_language );
			}
		}

		return $post_data;
	}

	add_filter( 'tinvwl_addtowishlist_prepare', 'tinvwl_wpml_addtowishlist_prepare' );
}

if ( ! function_exists( 'tinvwl_wpml_addtowishlist_out_prepare' ) ) {

	/**
	 * Change product data if product need translate in WooCommerce Multilingual
	 *
	 * @param array $attr Data for wishlist.
	 *
	 * @return array
	 */
	function tinvwl_wpml_addtowishlist_out_prepare( $attr ) {
		if ( class_exists( 'woocommerce_wpml' ) ) {

			global $woocommerce_wpml, $sitepress, $wpdb;

			// Reload products class.
			if ( version_compare( WCML_VERSION, '4.4.0', '<' ) ) {
				$woocommerce_wpml->products = new WCML_Products( $woocommerce_wpml, $sitepress, $wpdb );
			} else {
				global $wpml_post_translations;
				$woocommerce_wpml->products = new WCML_Products( $woocommerce_wpml, $sitepress, $wpml_post_translations, $wpdb );
			}

			if ( array_key_exists( 'product_id', $attr ) && ! empty( $attr['product_id'] ) ) {
				$attr['product_id'] = $woocommerce_wpml->products->get_original_product_id( $attr['product_id'] );
			}
			if ( array_key_exists( 'product_id', $attr ) && ! empty( $attr['product_id'] ) && array_key_exists( 'variation_id', $attr ) && ! empty( $attr['variation_id'] ) ) {
				$original_product_language = $woocommerce_wpml->products->get_original_product_language( $attr['product_id'] );
				$attr['variation_id']      = apply_filters( 'translate_object_id', $attr['variation_id'], 'product_variation', true, $original_product_language );
			}
		}

		return $attr;
	}

	add_filter( 'tinvwl_addtowishlist_out_prepare_attr', 'tinvwl_wpml_addtowishlist_out_prepare' );
}

if ( ! function_exists( 'tinvwl_wpml_addtowishlist_out_prepare_product' ) ) {

	/**
	 * Change product if product need translate in WooCommerce Multilingual
	 *
	 * @param \WC_Product $product WooCommerce Product.
	 *
	 * @return \WC_Product
	 */
	function tinvwl_wpml_addtowishlist_out_prepare_product( $product ) {
		if ( class_exists( 'woocommerce_wpml' ) && is_object( $product ) ) {

			global $woocommerce_wpml, $sitepress, $wpdb;

			// Reload products class.
			if ( version_compare( WCML_VERSION, '4.4.0', '<' ) ) {
				$woocommerce_wpml->products = new WCML_Products( $woocommerce_wpml, $sitepress, $wpdb );
			} else {
				global $wpml_post_translations;
				$woocommerce_wpml->products = new WCML_Products( $woocommerce_wpml, $sitepress, $wpml_post_translations, $wpdb );
			}

			$product_id   = version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->id : ( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() );
			$variation_id = version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->variation_id : ( $product->is_type( 'variation' ) ? $product->get_id() : 0 );

			if ( ! empty( $product_id ) ) {
				$product_id = $woocommerce_wpml->products->get_original_product_id( $product_id );
			}
			if ( ! empty( $product_id ) && ! empty( $variation_id ) ) {
				$original_product_language = $woocommerce_wpml->products->get_original_product_language( $product_id );
				$variation_id              = apply_filters( 'translate_object_id', $variation_id, 'product_variation', true, $original_product_language );
			}
			if ( ! empty( $product_id ) ) {
				$product = wc_get_product( $variation_id ? $variation_id : $product_id );
			}
		}

		return $product;
	}

	add_filter( 'tinvwl_addtowishlist_out_prepare_product', 'tinvwl_wpml_addtowishlist_out_prepare_product' );
}

if ( ! function_exists( 'tinvwl_wpml_addtowishlist_prepare_form' ) ) {

	/**
	 * Change product form data if product need translate in WooCommerce Multilingual
	 *
	 * @param array $post_data Data for wishlist.
	 *
	 * @return array
	 */
	function tinvwl_wpml_addtowishlist_prepare_form( $post_data ) {
		if ( class_exists( 'woocommerce_wpml' ) && is_array( $post_data ) ) {

			global $woocommerce_wpml, $sitepress, $wpdb;

			// Reload products class.
			if ( version_compare( WCML_VERSION, '4.4.0', '<' ) ) {
				$woocommerce_wpml->products = new WCML_Products( $woocommerce_wpml, $sitepress, $wpdb );
			} else {
				global $wpml_post_translations;
				$woocommerce_wpml->products = new WCML_Products( $woocommerce_wpml, $sitepress, $wpml_post_translations, $wpdb );
			}

			if ( array_key_exists( 'product_id', $post_data ) && ! empty( $post_data['product_id'] ) ) {
				$post_data['product_id'] = $woocommerce_wpml->products->get_original_product_id( $post_data['product_id'] );
			}
			if ( array_key_exists( 'product_id', $post_data ) && ! empty( $post_data['product_id'] ) && array_key_exists( 'variation_id', $post_data ) && ! empty( $post_data['variation_id'] ) ) {
				$original_product_language = $woocommerce_wpml->products->get_original_product_language( $post_data['product_id'] );
				$post_data['variation_id'] = apply_filters( 'translate_object_id', $post_data['variation_id'], 'product_variation', true, $original_product_language );
			}
		}

		return $post_data;
	}

	add_filter( 'tinvwl_addtowishlist_prepare_form', 'tinvwl_wpml_addtowishlist_prepare_form' );
}

if ( ! function_exists( 'tinvwl_wpml_filter_link' ) ) {

	/**
	 * Correct add wishlist key for WPML plugin.
	 *
	 * @param string $full_link Link for page.
	 * @param array $l Language.
	 *
	 * @return string
	 */
	function tinvwl_wpml_filter_link( $full_link, $l ) {
		$share_key = get_query_var( 'tinvwlID', null );
		if ( ! empty( $share_key ) ) {
			if ( get_option( 'permalink_structure' ) ) {
				$suffix = '';
				if ( preg_match( '/([^\?]+)\?*?(.*)/i', $full_link, $_full_link ) ) {
					$full_link = $_full_link[1];
					$suffix    = $_full_link[2];
				}
				if ( ! preg_match( '/\/$/', $full_link ) ) {
					$full_link .= '/';
				}
				$full_link .= $share_key . '/' . $suffix;
			} else {
				$full_link .= add_query_arg( 'tinvwlID', $share_key, $full_link );
			}
		}

		return $full_link;
	}

	add_filter( 'WPML_filter_link', 'tinvwl_wpml_filter_link', 0, 2 );
}

if ( ! function_exists( 'tinvwl_gift_card_add' ) ) {

	/**
	 * Support WooCommerce - Gift Cards
	 * Redirect to page gift card, if requires that customers enter a name and email when purchasing a Gift Card.
	 *
	 * @param boolean $redirect Default value to redirect.
	 * @param \WC_Product $product Product data.
	 *
	 * @return boolean
	 */
	function tinvwl_gift_card_add( $redirect, $product ) {
		if ( class_exists( 'KODIAK_GIFTCARDS' ) ) {
			$is_required_field_giftcard = get_option( 'woocommerce_enable_giftcard_info_requirements' );

			if ( 'yes' == $is_required_field_giftcard ) { // WPCS: loose comparison ok.
				$is_giftcard = get_post_meta( $product->get_id(), '_giftcard', true );
				if ( 'yes' == $is_giftcard ) { // WPCS: loose comparison ok.
					return true;
				}
			}
		}

		return $redirect;
	}

	add_filter( 'tinvwl_product_add_to_cart_need_redirect', 'tinvwl_gift_card_add', 20, 2 );
}

if ( ! function_exists( 'tinvwl_gift_card_add_url' ) ) {

	/**
	 * Support WooCommerce - Gift Cards
	 * Redirect to page gift card, if requires that customers enter a name and email when purchasing a Gift Card.
	 *
	 * @param string $redirect_url Default value to redirect.
	 * @param \WC_Product $product Product data.
	 *
	 * @return boolean
	 */
	function tinvwl_gift_card_add_url( $redirect_url, $product ) {
		if ( class_exists( 'KODIAK_GIFTCARDS' ) ) {
			$is_required_field_giftcard = get_option( 'woocommerce_enable_giftcard_info_requirements' );

			if ( 'yes' == $is_required_field_giftcard ) { // WPCS: loose comparison ok.
				$is_giftcard = get_post_meta( $product->get_id(), '_giftcard', true );
				if ( 'yes' == $is_giftcard ) { // WPCS: loose comparison ok.
					return $product->get_permalink();
				}
			}
		}

		return $redirect_url;
	}

	add_filter( 'tinvwl_product_add_to_cart_redirect_url', 'tinvwl_gift_card_add_url', 20, 2 );
}

if ( ! function_exists( 'tinv_wishlist_meta_support_rpgiftcards' ) ) {

	/**
	 * Set description for meta WooCommerce - Gift Cards
	 *
	 * @param array $meta Meta array.
	 *
	 * @return array
	 */
	function tinv_wishlist_metasupport_rpgiftcards( $meta ) {
		if ( class_exists( 'KODIAK_GIFTCARDS' ) ) {
			foreach ( $meta as $key => $data ) {
				switch ( $data['key'] ) {
					case 'rpgc_note':
						$meta[ $key ]['key'] = __( 'Note', 'ti-woocommerce-wishlist' );
						break;
					case 'rpgc_to':
						$meta[ $key ]['key'] = ( get_option( 'woocommerce_giftcard_to' ) <> null ? get_option( 'woocommerce_giftcard_to' ) : __( 'To', 'ti-woocommerce-wishlist' ) ); // WPCS: loose comparison ok.
						break;
					case 'rpgc_to_email':
						$meta[ $key ]['key'] = ( get_option( 'woocommerce_giftcard_toEmail' ) <> null ? get_option( 'woocommerce_giftcard_toEmail' ) : __( 'To Email', 'ti-woocommerce-wishlist' ) ); // WPCS: loose comparison ok.
						break;
					case 'rpgc_address':
						$meta[ $key ]['key'] = ( get_option( 'woocommerce_giftcard_address' ) <> null ? get_option( 'woocommerce_giftcard_address' ) : __( 'Address', 'ti-woocommerce-wishlist' ) ); // WPCS: loose comparison ok.
						break;
					case 'rpgc_reload_card':
						$meta[ $key ]['key'] = __( 'Reload existing Gift Card', 'ti-woocommerce-wishlist' );
						break;
					case 'rpgc_description':
					case 'rpgc_reload_check':
						unset( $meta[ $key ] );
						break;
				}
			}
		}

		return $meta;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_metasupport_rpgiftcards' );
} // End if().

if ( ! function_exists( 'tinv_wishlist_metaprepare_rpgiftcards' ) ) {

	/**
	 * Prepare save meta for WooCommerce - Gift Cards
	 *
	 * @param array $meta Meta array.
	 *
	 * @return array
	 */
	function tinv_wishlist_metaprepare_rpgiftcards( $meta ) {
		if ( class_exists( 'KODIAK_GIFTCARDS' ) ) {
			if ( array_key_exists( 'rpgc_reload_check', $meta ) ) {
				foreach ( array( 'rpgc_note', 'rpgc_to', 'rpgc_to_email', 'rpgc_address' ) as $value ) {
					if ( array_key_exists( $value, $meta ) ) {
						unset( $meta[ $value ] );
					}
				}
			}
		}

		return $meta;
	}

	add_filter( 'tinvwl_product_prepare_meta', 'tinv_wishlist_metaprepare_rpgiftcards' );
}

if ( ! function_exists( 'tinv_wishlist_metasupport_woocommerce_bookings' ) ) {

	/**
	 * Set description for meta WooCommerce Bookings
	 *
	 * @param array $meta Meta array.
	 * @param integer $product_id Priduct ID.
	 * @param integer $variation_id Variation Product ID.
	 *
	 * @return array
	 */
	function tinv_wishlist_metasupport_woocommerce_bookings( $meta, $product_id, $variation_id ) {
		if ( ! class_exists( 'WC_Booking_Form' ) || ! function_exists( 'is_wc_booking_product' ) ) {
			return $meta;
		}
		$product = wc_get_product( $variation_id ? $variation_id : $product_id );
		if ( is_wc_booking_product( $product ) ) {
			$booking_form = new WC_Booking_Form( $product );
			$post_data    = array();
			foreach ( $meta as $data ) {
				$post_data[ $data['key'] ] = $data['display'];
			}
			$booking_data = $booking_form->get_posted_data( $post_data );
			$meta         = array();
			foreach ( $booking_data as $key => $value ) {
				if ( ! preg_match( '/^_/', $key ) ) {
					$meta[ $key ] = array(
						'key'     => get_wc_booking_data_label( $key, $product ),
						'display' => $value,
					);
				}
			}
		}

		return $meta;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_metasupport_woocommerce_bookings', 10, 3 );
} // End if().

if ( ! function_exists( 'tinvwl_item_price_woocommerce_bookings' ) ) {

	/**
	 * Modify price for WooCommerce Bookings
	 *
	 * @param string $price Returned price.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return string
	 */
	function tinvwl_item_price_woocommerce_bookings( $price, $wl_product, $product ) {
		if ( ! class_exists( 'WC_Booking_Form' ) || ! function_exists( 'is_wc_booking_product' ) ) {
			return $price;
		}
		if ( is_wc_booking_product( $product ) && array_key_exists( 'meta', $wl_product ) ) {
			$booking_form = new WC_Booking_Form( $product );
			$cost         = $booking_form->calculate_booking_cost( $wl_product['meta'] );
			if ( is_wp_error( $cost ) ) {
				return $price;
			}

			if ( 'incl' === get_option( 'woocommerce_tax_display_shop' ) ) {
				if ( function_exists( 'wc_get_price_excluding_tax' ) ) {
					$display_price = wc_get_price_including_tax( $product, array( 'price' => $cost ) );
				} else {
					$display_price = $product->get_price_including_tax( 1, $cost );
				}
			} else {
				if ( function_exists( 'wc_get_price_excluding_tax' ) ) {
					$display_price = wc_get_price_excluding_tax( $product, array( 'price' => $cost ) );
				} else {
					$display_price = $product->get_price_excluding_tax( 1, $cost );
				}
			}

			if ( version_compare( WC_VERSION, '2.4.0', '>=' ) ) {
				$price_suffix = $product->get_price_suffix( $cost, 1 );
			} else {
				$price_suffix = $product->get_price_suffix();
			}
			$price = wc_price( $display_price ) . $price_suffix;
		}

		return $price;
	}

	add_filter( 'tinvwl_wishlist_item_price', 'tinvwl_item_price_woocommerce_bookings', 10, 3 );
} // End if().

if ( ! function_exists( 'tinvwl_item_status_woocommerce_bookings' ) ) {

	/**
	 * Modify availability for WooCommerce Bookings
	 *
	 * @param string $status Status availability.
	 * @param string $availability Default availability.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return type
	 */
	function tinvwl_item_status_woocommerce_bookings( $status, $availability, $wl_product, $product ) {
		if ( ! class_exists( 'WC_Booking_Form' ) || ! function_exists( 'is_wc_booking_product' ) ) {
			return $status;
		}
		if ( is_wc_booking_product( $product ) && array_key_exists( 'meta', $wl_product ) ) {
			$booking_form = new WC_Booking_Form( $product );
			$cost         = $booking_form->calculate_booking_cost( $wl_product['meta'] );
			if ( is_wp_error( $cost ) ) {
				return '<p class="stock out-of-stock"><span><i class="ftinvwl ftinvwl-times"></i></span><span>' . $cost->get_error_message() . '</span></p>';
			}
		}

		return $status;
	}

	add_filter( 'tinvwl_wishlist_item_status', 'tinvwl_item_status_woocommerce_bookings', 10, 4 );
}

if ( ! function_exists( 'tinv_wishlist_metasupport_wc_gf_addons' ) ) {

	/**
	 * Set description for meta WooCommerce - Gravity Forms Product Add-Ons
	 *
	 * @param array $meta Meta array.
	 *
	 * @return array
	 */
	function tinv_wishlist_metasupport_wc_gf_addons( $meta ) {
		if ( array_key_exists( 'wc_gforms_form_id', $meta ) && class_exists( 'RGFormsModel' ) ) {
			$form_meta = RGFormsModel::get_form_meta( $meta['wc_gforms_form_id']['display'] );
			if ( array_key_exists( 'fields', $form_meta ) ) {
				$_meta = array();
				foreach ( $form_meta['fields'] as $field ) {
					$field_name = $field->get_first_input_id( array( 'id' => 0 ) );
					if ( array_key_exists( $field_name, $meta ) ) {
						$meta[ $field_name ]['key'] = $field->label;
						$_meta[ $field_name ]       = $meta[ $field_name ];
					}
				}
				$meta = $_meta;
			}
		}

		return $meta;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_metasupport_wc_gf_addons' );
}

if ( ! function_exists( 'tinv_wishlist_metasupport_woocommerce_composite_products' ) ) {

	/**
	 * Set description for meta WooCommerce Composite Products
	 *
	 * @param array $meta Meta array.
	 * @param integer $product_id Product ID.
	 *
	 * @return array
	 */
	function tinv_wishlist_metasupport_woocommerce_composite_products( $meta, $product_id ) {
		if ( array_key_exists( 'wccp_component_selection', $meta ) && is_array( $meta['wccp_component_selection'] ) ) {
			$meta = array();
		} // End if().

		return $meta;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_metasupport_woocommerce_composite_products', 10, 2 );
} // End if().

if ( ! function_exists( 'tinvwl_row_woocommerce_composite_products' ) ) {

	/**
	 * Add rows for sub product for WooCommerce Composite Products
	 *
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product_Composite $product Woocommerce Product.
	 */
	function tinvwl_row_woocommerce_composite_products( $wl_product, $product ) {
		if ( is_object( $product ) && $product->is_type( 'composite' ) && array_key_exists( 'wccp_component_selection', $wl_product['meta'] ) ) {
			$product_quantity = $product->is_sold_individually() ? 1 : $wl_product['quantity'];

			$components = $product->get_components();
			foreach ( $components as $component_id => $component ) {
				$composited_product_id       = ! empty( $wl_product['meta']['wccp_component_selection'][ $component_id ] ) ? absint( $wl_product['meta']['wccp_component_selection'][ $component_id ] ) : '';
				$composited_product_quantity = isset( $wl_product['meta']['wccp_component_quantity'][ $component_id ] ) ? absint( $wl_product['meta']['wccp_component_quantity'][ $component_id ] ) : $component->get_quantity( 'min' );

				$composited_variation_id = isset( $wl_product['meta']['wccp_variation_id'][ $component_id ] ) ? wc_clean( $wl_product['meta']['wccp_variation_id'][ $component_id ] ) : '';

				if ( $composited_product_id ) {

					$composited_product_wrapper = $component->get_option( $composited_variation_id ? $composited_variation_id : $composited_product_id );

					if ( ! $composited_product_wrapper ) {
						continue;
					}

					$composited_product = $composited_product_wrapper->get_product();

					if ( $composited_product->is_sold_individually() && $composited_product_quantity > 1 ) {
						$composited_product_quantity = 1;
					}

					$product_url   = $composited_product->get_permalink();
					$product_image = $composited_product->get_image();
					$product_title = $composited_product->get_title();
					$product_price = $composited_product->get_price_html();

					$component_option = $product->get_component_option( $component_id, $composited_product_id );

					if ( $component_option ) {
						if ( false === $component_option->is_priced_individually() && $composited_product->get_price() == 0 ) {
							$product_price = '';
						} elseif ( false === $component_option->get_component()->is_subtotal_visible( 'cart' ) ) {
							$product_price = '';
						} elseif ( apply_filters( 'woocommerce_add_composited_cart_item_prices', true ) ) {
							if ( $product_price ) {
								$product_price = '<span class="component_table_item_price">' . $product_price . '</span>';
							}
						}
					}


					if ( $composited_product->is_visible() ) {
						$product_image = sprintf( '<a href="%s">%s</a>', esc_url( $product_url ), $product_image );
						$product_title = sprintf( '<a href="%s">%s</a>', esc_url( $product_url ), $product_title );
					}
					$product_title .= tinv_wishlist_get_item_data( $composited_product, $wl_product );

					$availability = (array) $composited_product->get_availability();
					if ( ! array_key_exists( 'availability', $availability ) ) {
						$availability['availability'] = '';
					}
					if ( ! array_key_exists( 'class', $availability ) ) {
						$availability['class'] = '';
					}
					$availability_html = empty( $availability['availability'] ) ? '<p class="stock ' . esc_attr( $availability['class'] ) . '"><span><i class="ftinvwl ftinvwl-check"></i></span><span class="tinvwl-txt">' . esc_html__( 'In stock', 'ti-woocommerce-wishlist' ) . '</span></p>' : '<p class="stock ' . esc_attr( $availability['class'] ) . '"><span><i class="ftinvwl ftinvwl-times"></i></span><span>' . esc_html( $availability['availability'] ) . '</span></p>';
					$row_string        = '<tr>';
					$row_string        .= ( ( ! is_user_logged_in() || get_current_user_id() !== $wl_product['author'] ) ? ( ( ! tinv_get_option( 'table', 'colm_checkbox' ) ) ? '' : '<td colspan="1"></td>' ) : '<td colspan="' . ( ( ! tinv_get_option( 'table', 'colm_checkbox' ) ) ? '1' : '2' ) . '"></td>' ) . '&nbsp;<td class="product-thumbnail">%2$s</td><td class="product-name">%1$s:<br/>%3$s</td>';
					if ( tinv_get_option( 'product_table', 'colm_price' ) ) {
						$row_string .= ( $product_price ) ? '<td class="product-price">%4$s &times; %6$s</td>' : '<td class="product-price">%4$s</td>';
					}
					if ( tinv_get_option( 'product_table', 'colm_date' ) ) {
						$row_string .= '<td class="product-date">&nbsp;</td>';
					}
					if ( tinv_get_option( 'product_table', 'colm_stock' ) ) {
						$row_string .= '<td class="product-stock">%5$s</td>';
					}
					if ( tinv_get_option( 'product_table', 'colm_quantity' ) ) {
						$row_string .= '<td class="product-quantity">&nbsp;</td>';
					}
					if ( tinv_get_option( 'product_table', 'add_to_cart' ) ) {
						$row_string .= '<td class="product-action">&nbsp;</td>';
					}
					$row_string .= '</tr>';

					echo sprintf( $row_string, $component->get_title(), $product_image, $product_title, $product_price, $availability_html, $composited_product_quantity * $product_quantity ); // WPCS: xss ok.
				} // End if().
			} // End foreach().
		} // End if().
	}

	add_action( 'tinvwl_wishlist_row_after', 'tinvwl_row_woocommerce_composite_products', 10, 2 );
} // End if().

if ( ! function_exists( 'tinvwl_item_price_woocommerce_composite_products' ) ) {

	/**
	 * Modify price for WooCommerce Composite Products
	 *
	 * @param string $price Returned price.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return string
	 */
	function tinvwl_item_price_woocommerce_composite_products( $price, $wl_product, $product ) {
		if ( is_object( $product ) && $product->is_type( 'composite' ) && array_key_exists( 'wccp_component_selection', $wl_product['meta'] ) ) {
			$components    = $product->get_components();
			$_price        = $product->get_price();
			$regular_price = $product->get_regular_price();
			foreach ( $components as $component_id => $component ) {
				$composited_product_id       = ! empty( $wl_product['meta']['wccp_component_selection'][ $component_id ] ) ? absint( $wl_product['meta']['wccp_component_selection'][ $component_id ] ) : '';
				$composited_product_quantity = isset( $wl_product['meta']['wccp_component_quantity'][ $component_id ] ) ? absint( $wl_product['meta']['wccp_component_quantity'][ $component_id ] ) : $component->get_quantity( 'min' );

				$composited_variation_id = isset( $wl_product['meta']['wccp_variation_id'][ $component_id ] ) ? wc_clean( $wl_product['meta']['wccp_variation_id'][ $component_id ] ) : '';

				if ( $composited_product_id ) {
					$composited_product_wrapper = $component->get_option( $composited_variation_id ? $composited_variation_id : $composited_product_id );
					if ( $component->is_priced_individually() ) {
						$_price        += $composited_product_wrapper->get_price() * $composited_product_quantity;
						$regular_price += $composited_product_wrapper->get_regular_price() * $composited_product_quantity;
					}
				}
			}
			if ( $_price == $regular_price ) {
				$price = wc_price( $_price ) . $product->get_price_suffix();
			} else {
				$price = wc_format_sale_price( $regular_price, $_price ) . $product->get_price_suffix();
			}
		}

		return $price;
	}

	add_filter( 'tinvwl_wishlist_item_price', 'tinvwl_item_price_woocommerce_composite_products', 10, 3 );
} // End if().

if ( ! function_exists( 'tinv_wishlist_metasupport_woocommerce_product_bundles' ) ) {

	/**
	 * Set description for meta WooCommerce Product Bundles
	 *
	 * @param array $meta Meta array.
	 * @param integer $product_id Product ID.
	 *
	 * @return array
	 */
	function tinv_wishlist_metasupport_woocommerce_product_bundles( $meta, $product_id ) {
		$product = wc_get_product( $product_id );
		if ( is_object( $product ) && $product->is_type( 'bundle' ) ) {
			$meta = array();
		}

		return $meta;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_metasupport_woocommerce_product_bundles', 10, 2 );
} // End if().

if ( ! function_exists( 'tinvwl_row_woocommerce_product_bundles' ) ) {

	/**
	 * Add rows for sub product for WooCommerce Product Bundles
	 *
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 */
	function tinvwl_row_woocommerce_product_bundles( $wl_product, $product ) {
		if ( is_object( $product ) && $product->is_type( 'bundle' ) ) {

			$product_id    = WC_PB_Core_Compatibility::get_id( $product );
			$bundled_items = $product->get_bundled_items();
			if ( ! empty( $bundled_items ) ) {
				foreach ( $bundled_items as $bundled_item_id => $bundled_item ) {

					$bundled_item_variation_id_request_key = apply_filters( 'woocommerce_product_bundle_field_prefix', '', $product_id ) . 'bundle_variation_id_' . $bundled_item_id;
					$bundled_variation_id                  = absint( isset( $wl_product['meta'][ $bundled_item_variation_id_request_key ] ) ? $wl_product['meta'][ $bundled_item_variation_id_request_key ] : 0 );
					if ( ! empty( $bundled_variation_id ) ) {
						$bundled_item->product = wc_get_product( $bundled_variation_id );
					}

					$is_optional = $bundled_item->is_optional();

					$bundled_item_quantity_request_key = apply_filters( 'woocommerce_product_bundle_field_prefix', '', $product_id ) . 'bundle_quantity_' . $bundled_item_id;
					$bundled_product_qty               = isset( $wl_product['meta'][ $bundled_item_quantity_request_key ] ) ? absint( $wl_product['meta'][ $bundled_item_quantity_request_key ] ) : $bundled_item->get_quantity();

					if ( $is_optional ) {

						/** Documented in method 'get_posted_bundle_configuration'. */
						$bundled_item_selected_request_key = apply_filters( 'woocommerce_product_bundle_field_prefix', '', $product_id ) . 'bundle_selected_optional_' . $bundled_item_id;

						if ( ! array_key_exists( $bundled_item_selected_request_key, $wl_product['meta'] ) ) {
							$bundled_product_qty = 0;
						}
					}
					if ( 0 === $bundled_product_qty || 'visible' != $bundled_item->cart_visibility ) {
						continue;
					}

					$product_url   = $bundled_item->product->get_permalink();
					$product_image = $bundled_item->product->get_image();
					$product_title = $bundled_item->has_title_override() ? $bundled_item->get_title() : $bundled_item->get_raw_title();

					$product_price     = $bundled_item->product->get_price_html();
					$product_price_raw = $bundled_item->product->get_regular_price();
					$discount          = $bundled_item->get_discount();
					$product_price     = empty( $discount ) ? $product_price : wc_price( WC_PB_Product_Prices::get_discounted_price( $product_price_raw, $discount ) );

					if ( $bundled_item->product->is_visible() ) {
						$product_image = sprintf( '<a href="%s">%s</a>', esc_url( $product_url ), $product_image );
						$product_title = sprintf( '<a href="%s">%s</a>', esc_url( $product_url ), $product_title );
					}
					$product_title .= tinv_wishlist_get_item_data( $bundled_item->product, $wl_product );

					$availability = (array) $bundled_item->product->get_availability();
					if ( ! array_key_exists( 'availability', $availability ) ) {
						$availability['availability'] = '';
					}
					if ( ! array_key_exists( 'class', $availability ) ) {
						$availability['class'] = '';
					}
					$availability_html = empty( $availability['availability'] ) ? '<p class="stock ' . esc_attr( $availability['class'] ) . '"><span><i class="ftinvwl ftinvwl-check"></i></span><span class="tinvwl-txt">' . esc_html__( 'In stock', 'ti-woocommerce-wishlist' ) . '</span></p>' : '<p class="stock ' . esc_attr( $availability['class'] ) . '"><span><i class="ftinvwl ftinvwl-times"></i></span><span>' . esc_html( $availability['availability'] ) . '</span></p>';
					$row_string        = '<tr>';
					$row_string        .= '<td colspan="2">&nbsp;</td><td class="product-thumbnail">%1$s</td><td class="product-name">%2$s</td>';
					if ( tinv_get_option( 'product_table', 'colm_price' ) && $bundled_item->is_priced_individually() ) {
						$row_string .= '<td class="product-price">%3$s &times; %5$s</td>';
					} elseif ( ! $bundled_item->is_priced_individually() ) {
						$row_string .= '<td class="product-price"></td>';
					}
					if ( tinv_get_option( 'product_table', 'colm_date' ) ) {
						$row_string .= '<td class="product-date">&nbsp;</td>';
					}
					if ( tinv_get_option( 'product_table', 'colm_stock' ) ) {
						$row_string .= '<td class="product-stock">%4$s</td>';
					}

					if ( tinv_get_option( 'product_table', 'add_to_cart' ) ) {
						$row_string .= '<td class="product-action">&nbsp;</td>';
					}
					$row_string .= '</tr>';

					echo sprintf( $row_string, $product_image, $product_title, $product_price, $availability_html, $bundled_product_qty ); // WPCS: xss ok.
				} // End foreach().
			} // End if().
		} // End if().
	}

	add_action( 'tinvwl_wishlist_row_after', 'tinvwl_row_woocommerce_product_bundles', 10, 2 );
} // End if().

if ( ! function_exists( 'tinvwl_item_price_woocommerce_product_bundles' ) ) {

	/**
	 * Modify price for WooCommerce Product Bundles
	 *
	 * @param string $price Returned price.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return string
	 */
	function tinvwl_item_price_woocommerce_product_bundles( $price, $wl_product, $product ) {
		if ( is_object( $product ) && $product->is_type( 'bundle' ) ) {

			$bundle_price  = $product->get_price();
			$product_id    = WC_PB_Core_Compatibility::get_id( $product );
			$bundled_items = $product->get_bundled_items();

			if ( ! empty( $bundled_items ) ) {

				$bundled_items_price = 0.0;

				foreach ( $bundled_items as $bundled_item_id => $bundled_item ) {

					$bundled_item_variation_id_request_key = apply_filters( 'woocommerce_product_bundle_field_prefix', '', $product_id ) . 'bundle_variation_id_' . $bundled_item_id;
					$bundled_variation_id                  = absint( isset( $wl_product['meta'][ $bundled_item_variation_id_request_key ] ) ? $wl_product['meta'][ $bundled_item_variation_id_request_key ] : 0 );
					if ( ! empty( $bundled_variation_id ) ) {
						$_bundled_product = wc_get_product( $bundled_variation_id );
					} else {
						$_bundled_product = $bundled_item->product;
					}

					$is_optional = $bundled_item->is_optional();

					$bundled_item_quantity_request_key = apply_filters( 'woocommerce_product_bundle_field_prefix', '', $product_id ) . 'bundle_quantity_' . $bundled_item_id;
					$bundled_product_qty               = isset( $wl_product['meta'][ $bundled_item_quantity_request_key ] ) ? absint( $wl_product['meta'][ $bundled_item_quantity_request_key ] ) : $bundled_item->get_quantity();

					if ( $is_optional ) {

						/** Documented in method 'get_posted_bundle_configuration'. */
						$bundled_item_selected_request_key = apply_filters( 'woocommerce_product_bundle_field_prefix', '', $product_id ) . 'bundle_selected_optional_' . $bundled_item_id;

						if ( ! array_key_exists( $bundled_item_selected_request_key, $wl_product['meta'] ) ) {
							$bundled_product_qty = 0;
						}
					}

					if ( $bundled_item->is_priced_individually() ) {
						$product_price = $_bundled_product->get_regular_price();

						$discount      = $bundled_item->get_discount();
						$product_price = empty( $discount ) ? $product_price : WC_PB_Product_Prices::get_discounted_price( $product_price, $discount );

						$bundled_item_price = $product_price * $bundled_product_qty;

						$bundled_items_price += (double) $bundled_item_price;
					}

				} // End foreach().
				$price = wc_price( (double) $bundle_price + $bundled_items_price );
				$price = apply_filters( 'woocommerce_get_price_html', $price, $product );
			} // End if().
		} // End if().

		return $price;
	}

	add_filter( 'tinvwl_wishlist_item_price', 'tinvwl_item_price_woocommerce_product_bundles', 10, 3 );
} // End if().

if ( ! function_exists( 'tinv_wishlist_metasupport_woocommerce_mix_and_match_products' ) ) {

	/**
	 * Set description for meta WooCommerce Mix and Match
	 *
	 * @param array $meta Meta array.
	 * @param integer $product_id Product ID.
	 *
	 * @return array
	 */
	function tinv_wishlist_metasupport_woocommerce_mix_and_match_products( $meta, $product_id ) {
		if ( array_key_exists( 'mnm_quantity', $meta ) ) {
			$product = wc_get_product( $product_id );
			if ( is_object( $product ) && $product->is_type( 'mix-and-match' ) ) {
				$meta = array();
			}
		}

		return $meta;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_metasupport_woocommerce_mix_and_match_products', 10, 2 );
} // End if().

if ( ! function_exists( 'tinvwl_row_woocommerce_mix_and_match_products' ) ) {

	/**
	 * Add rows for sub product for WooCommerce Mix and Match
	 *
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 */
	function tinvwl_row_woocommerce_mix_and_match_products( $wl_product, $product ) {
		if ( is_object( $product ) && $product->is_type( 'mix-and-match' ) && array_key_exists( 'mnm_quantity', $wl_product['meta'] ) ) {
			$product_quantity = $product->is_sold_individually() ? 1 : $wl_product['quantity'];
			$mnm_items        = $product->get_children();
			if ( ! empty( $mnm_items ) ) {
				foreach ( $mnm_items as $id => $mnm_item ) {
					$item_quantity = 0;
					if ( array_key_exists( $id, $wl_product['meta']['mnm_quantity'] ) ) {
						$item_quantity = absint( $wl_product['meta']['mnm_quantity'][ $id ] );
					}
					if ( 0 >= $item_quantity ) {
						continue;
					}

					$product_url   = $mnm_item->get_permalink();
					$product_image = $mnm_item->get_image();
					$product_title = $mnm_item->get_title();
					$product_price = $mnm_item->get_price_html();
					if ( $mnm_item->is_visible() ) {
						$product_image = sprintf( '<a href="%s">%s</a>', esc_url( $product_url ), $product_image );
						$product_title = sprintf( '<a href="%s">%s</a>', esc_url( $product_url ), $product_title );
					}
					$product_title .= tinv_wishlist_get_item_data( $mnm_item, $wl_product );

					$availability = (array) $mnm_item->get_availability();
					if ( ! array_key_exists( 'availability', $availability ) ) {
						$availability['availability'] = '';
					}
					if ( ! array_key_exists( 'class', $availability ) ) {
						$availability['class'] = '';
					}
					$availability_html = empty( $availability['availability'] ) ? '<p class="stock ' . esc_attr( $availability['class'] ) . '"><span><i class="ftinvwl ftinvwl-check"></i></span><span class="tinvwl-txt">' . esc_html__( 'In stock', 'ti-woocommerce-wishlist' ) . '</span></p>' : '<p class="stock ' . esc_attr( $availability['class'] ) . '"><span><i class="ftinvwl ftinvwl-times"></i></span><span>' . esc_html( $availability['availability'] ) . '</span></p>';
					$row_string        = '<tr>';
					$row_string        .= '<td colspan="2">&nbsp;</td><td class="product-thumbnail">%1$s</td><td class="product-name">%2$s</td>';
					if ( tinv_get_option( 'product_table', 'colm_price' ) ) {
						$row_string .= '<td class="product-price">%3$s &times; %5$s</td>';
					}
					if ( tinv_get_option( 'product_table', 'colm_date' ) ) {
						$row_string .= '<td class="product-date">&nbsp;</td>';
					}
					if ( tinv_get_option( 'product_table', 'colm_stock' ) ) {
						$row_string .= '<td class="product-stock">%4$s</td>';
					}
					if ( tinv_get_option( 'product_table', 'add_to_cart' ) ) {
						$row_string .= '<td class="product-action">&nbsp;</td>';
					}
					$row_string .= '</tr>';

					echo sprintf( $row_string, $product_image, $product_title, $product_price, $availability_html, $item_quantity * $product_quantity ); // WPCS: xss ok.
				} // End foreach().
			} // End if().
		} // End if().
	}

	add_action( 'tinvwl_wishlist_row_after', 'tinvwl_row_woocommerce_mix_and_match_products', 10, 2 );
} // End if().

if ( ! function_exists( 'tinvwl_item_price_woocommerce_mix_and_match_products' ) ) {

	/**
	 * Modify price for WooCommerce Mix and Match
	 *
	 * @param string $price Returned price.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return string
	 */
	function tinvwl_item_price_woocommerce_mix_and_match_products( $price, $wl_product, $product ) {
		if ( is_object( $product ) && $product->is_type( 'mix-and-match' ) && $product->is_priced_per_product() ) {
			$mnm_items = $product->get_children();
			if ( ! empty( $mnm_items ) ) {
				$_price = 0;
				foreach ( $mnm_items as $id => $mnm_item ) {
					$item_quantity = 0;
					if ( array_key_exists( $id, $wl_product['meta']['mnm_quantity'] ) ) {
						$item_quantity = absint( $wl_product['meta']['mnm_quantity'][ $id ] );
					}
					if ( 0 >= $item_quantity ) {
						continue;
					}
					$_price += wc_get_price_to_display( $mnm_item, array( 'qty' => $item_quantity ) );
				}
				if ( 0 < $_price ) {
					if ( $product->is_on_sale() ) {
						$price = wc_format_sale_price( $_price + wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) ), $_price + wc_get_price_to_display( $product ) ) . $product->get_price_suffix();
					} else {
						$price = wc_price( $_price + wc_get_price_to_display( $product ) ) . $product->get_price_suffix();
					}
					$price = apply_filters( 'woocommerce_get_price_html', $price, $product );
				}
			}
		}

		return $price;
	}

	add_filter( 'tinvwl_wishlist_item_price', 'tinvwl_item_price_woocommerce_mix_and_match_products', 10, 3 );
} // End if().

if ( ! function_exists( 'tinvwl_add_form_woocommerce_mix_and_match_products' ) ) {

	/**
	 * Remove empty meta for WooCommerce Mix and Match
	 *
	 * @param array $form Post form data.
	 *
	 * @return array
	 */
	function tinvwl_add_form_woocommerce_mix_and_match_products( $form = array() ) {
		if ( array_key_exists( 'mnm_quantity', $form ) ) {
			if ( is_array( $form['mnm_quantity'] ) && ! empty( $form['mnm_quantity'] ) ) {
				foreach ( $form['mnm_quantity'] as $key => $value ) {
					$value = absint( $value );
					if ( empty( $value ) ) {
						unset( $form['mnm_quantity'][ $key ] );
					}
				}
				if ( empty( $form['mnm_quantity'] ) ) {
					unset( $form['mnm_quantity'] );
				}
			}
		}

		return $form;
	}

	add_filter( 'tinvwl_addtowishlist_add_form', 'tinvwl_add_form_woocommerce_mix_and_match_products' );
} // End if().

if ( ! function_exists( 'tinv_wishlist_metasupport_yith_woocommerce_product_bundles' ) ) {

	/**
	 * Set description for meta WooCommerce Mix and Match
	 *
	 * @param array $meta Meta array.
	 * @param integer $product_id Product ID.
	 *
	 * @return array
	 */
	function tinv_wishlist_metasupport_yith_woocommerce_product_bundles( $meta, $product_id ) {
		if ( array_key_exists( 'yith_bundle_quantity_1', $meta ) ) {
			$product = wc_get_product( $product_id );
			if ( is_object( $product ) && $product->is_type( 'yith_bundle' ) ) {
				$meta = array();
			}
		}

		return $meta;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_metasupport_yith_woocommerce_product_bundles', 10, 2 );
} // End if().

if ( ! function_exists( 'tinvwl_item_status_yith_woocommerce_product_bundles' ) ) {

	/**
	 * Modify status for YITH WooCommerce Product Bundles
	 *
	 * @param string $availability_html Returned availability status.
	 * @param string $availability Availability status.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return string
	 */
	function tinvwl_item_status_yith_woocommerce_product_bundles( $availability_html, $availability, $wl_product, $product ) {
		if ( empty( $availability ) && is_object( $product ) && $product->is_type( 'yith_bundle' ) ) {
			$response      = true;
			$bundled_items = $product->get_bundled_items();
			foreach ( $bundled_items as $key => $bundled_item ) {
				if ( method_exists( $bundled_item, 'is_optional' ) ) {
					if ( $bundled_item->is_optional() && ! array_key_exists( 'yith_bundle_optional_' . $key, $wl_product['meta'] ) ) {
						continue;
					}
				}
				if ( ! $bundled_item->get_product()->is_in_stock() ) {
					$response = false;
				}
			}

			if ( ! $response ) {
				$availability      = array(
					'class'        => 'out-of-stock',
					'availability' => __( 'Out of stock', 'ti-woocommerce-wishlist' ),
				);
				$availability_html = '<p class="stock ' . esc_attr( $availability['class'] ) . '"><span><i class="ftinvwl ftinvwl-times"></i></span><span>' . esc_html( $availability['availability'] ) . '</span></p>';
			}
		}

		return $availability_html;
	}

	add_filter( 'tinvwl_wishlist_item_status', 'tinvwl_item_status_yith_woocommerce_product_bundles', 10, 4 );
} // End if().

if ( ! function_exists( 'tinvwl_row_yith_woocommerce_product_bundles' ) ) {

	/**
	 * Add rows for sub product for YITH WooCommerce Product Bundles
	 *
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 */
	function tinvwl_row_yith_woocommerce_product_bundles( $wl_product, $product ) {
		if ( is_object( $product ) && $product->is_type( 'yith_bundle' ) ) {
			$bundled_items    = $product->get_bundled_items();
			$product_quantity = $product->is_sold_individually() ? 1 : $wl_product['quantity'];
			if ( ! empty( $bundled_items ) ) {
				foreach ( $bundled_items as $key => $bundled_item ) {
					$item_quantity = $bundled_item->get_quantity();
					if ( array_key_exists( 'yith_bundle_quantity_' . $key, $wl_product['meta'] ) ) {
						$item_quantity = absint( $wl_product['meta'][ 'yith_bundle_quantity_' . $key ] );
					}
					if ( method_exists( $bundled_item, 'is_optional' ) ) {
						if ( $bundled_item->is_optional() && ! array_key_exists( 'yith_bundle_optional_' . $key, $wl_product['meta'] ) ) {
							$item_quantity = 0;
						}
					}
					if ( 0 >= $item_quantity ) {
						continue;
					}

					$product = $bundled_item->get_product();
					if ( ! is_object( $product ) ) {
						continue;
					}

					$product_url   = $product->get_permalink();
					$product_image = $product->get_image();
					$product_title = $product->get_title();
					$product_price = $product->get_price_html();
					if ( $product->is_visible() ) {
						$product_image = sprintf( '<a href="%s">%s</a>', esc_url( $product_url ), $product_image );
						$product_title = sprintf( '<a href="%s">%s</a>', esc_url( $product_url ), $product_title );
					}
					$product_title .= tinv_wishlist_get_item_data( $product, $wl_product );

					$availability = (array) $product->get_availability();
					if ( ! array_key_exists( 'availability', $availability ) ) {
						$availability['availability'] = '';
					}
					if ( ! array_key_exists( 'class', $availability ) ) {
						$availability['class'] = '';
					}
					$availability_html = empty( $availability['availability'] ) ? '<p class="stock ' . esc_attr( $availability['class'] ) . '"><span><i class="ftinvwl ftinvwl-check"></i></span><span class="tinvwl-txt">' . esc_html__( 'In stock', 'ti-woocommerce-wishlist' ) . '</span></p>' : '<p class="stock ' . esc_attr( $availability['class'] ) . '"><span><i class="ftinvwl ftinvwl-times"></i></span><span>' . esc_html( $availability['availability'] ) . '</span></p>';
					$row_string        = '<tr>';
					$row_string        .= '<td colspan="2">&nbsp;</td><td class="product-thumbnail">%1$s</td><td class="product-name">%2$s</td>';
					if ( tinv_get_option( 'product_table', 'colm_price' ) ) {
						$row_string .= '<td class="product-price">%3$s &times; %5$s</td>';
					}
					if ( tinv_get_option( 'product_table', 'colm_date' ) ) {
						$row_string .= '<td class="product-date">&nbsp;</td>';
					}
					if ( tinv_get_option( 'product_table', 'colm_stock' ) ) {
						$row_string .= '<td class="product-stock">%4$s</td>';
					}
					if ( tinv_get_option( 'product_table', 'add_to_cart' ) ) {
						$row_string .= '<td class="product-action">&nbsp;</td>';
					}
					$row_string .= '</tr>';

					echo sprintf( $row_string, $product_image, $product_title, $product_price, $availability_html, $item_quantity * $product_quantity ); // WPCS: xss ok.
				} // End foreach().
			} // End if().
		} // End if().
	}

	add_action( 'tinvwl_wishlist_row_after', 'tinvwl_row_yith_woocommerce_product_bundles', 10, 2 );
} // End if().

if ( ! function_exists( 'tinv_wishlist_metasupport_woocommerce_product_add_on' ) ) {

	/**
	 * Set description for meta WooCommerce Product Add-on
	 *
	 * @param array $meta Meta array.
	 * @param integer $product_id Product ID.
	 *
	 * @return array
	 */
	function tinv_wishlist_metasupport_woocommerce_product_add_on( $meta, $product_id ) {
		if ( isset( $meta['ppom'] ) ) {
			$meta = array();
		}

		return $meta;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_metasupport_woocommerce_product_add_on', 10, 2 );
} // End if().

if ( ! function_exists( 'tinv_wishlist_item_meta_woocommerce_product_add_on' ) ) {

	/**
	 * Set description for meta WooCommerce Product Add-on
	 *
	 * @param array $meta Meta array.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return array
	 */
	function tinv_wishlist_item_meta_woocommerce_product_add_on( $meta, $wl_product, $product ) {
		if ( isset( $wl_product['meta'] ) && isset( $wl_product['meta']['ppom'] ) && class_exists( 'NM_PersonalizedProduct' ) ) {
			$product_meta = ( isset( $wl_product['meta']['ppom'] ) ) ? $wl_product['meta']['ppom']['fields'] : '';

			$item_meta = array();

			if ( $product_meta ) {

				foreach ( $product_meta as $key => $value ) {

					if ( empty( $value ) ) {
						continue;
					}

					$product_id = $wl_product['product_id'];
					$field_meta = ppom_get_field_meta_by_dataname( $product_id, $key );

					if ( empty( $field_meta ) ) {
						continue;
					}

					$field_type  = $field_meta['type'];
					$field_title = $field_meta['title'];


					switch ( $field_type ) {
						case 'quantities':
							$total_qty = 0;
							foreach ( $value as $label => $qty ) {
								if ( ! empty( $qty ) ) {
									$item_meta[] = array(
										'key'     => $label,
										'display' => $qty,
									);
									$total_qty   += $qty;
								}
							}
							break;

						case 'file':
							$file_thumbs_html = '';
							foreach ( $value as $file_id => $file_uploaded ) {
								$file_name        = $file_uploaded['org'];
								$file_thumbs_html .= ppom_show_file_thumb( $file_name );
							}
							$item_meta[] = array(
								'key'     => $field_title,
								'display' => $file_thumbs_html,
							);

							break;

						case 'cropper':
							$file_thumbs_html = '';
							foreach ( $value as $file_id => $file_cropped ) {

								$file_name        = $file_cropped['org'];
								$file_thumbs_html .= ppom_show_file_thumb( $file_name, true );
							}
							$item_meta[] = array(
								'key'     => $field_title,
								'display' => $file_thumbs_html,
							);
							break;

						case 'image':
							if ( $value ) {
								foreach ( $value as $id => $images_meta ) {
									$images_meta = json_decode( stripslashes( $images_meta ), true );
									$image_url   = stripslashes( $images_meta['link'] );
									$image_html  = '<img class="img-thumbnail" style="width:' . esc_attr( ppom_get_thumbs_size() ) . '" src="' . esc_url( $image_url ) . '" title="' . esc_attr( $images_meta['title'] ) . '">';
									$meta_key    = $field_title . '(' . $images_meta['title'] . ')';
									$item_meta[] = array(
										'key'     => $meta_key,
										'display' => $image_html,
									);
								}
							}
							break;

						case 'audio':
							if ( $value ) {
								$ppom_file_count = 1;
								foreach ( $value as $id => $audio_meta ) {
									$audio_meta  = json_decode( stripslashes( $audio_meta ), true );
									$audio_url   = stripslashes( $audio_meta['link'] );
									$audio_html  = '<a href="' . esc_url( $audio_url ) . '" title="' . esc_attr( $audio_meta['title'] ) . '">' . $audio_meta['title'] . '</a>';
									$meta_key    = $field_title . ': ' . $ppom_file_count ++;
									$item_meta[] = array(
										'key'     => $meta_key,
										'display' => $audio_html,
									);
								}
							}
							break;

						case 'bulkquantity':
							$item_meta[] = array(
								'key'     => $key,
								'display' => $value['option'] . ' (' . $value['qty'] . ')',
							);
							break;

						default:
							$value       = is_array( $value ) ? implode( ",", $value ) : $value;
							$item_meta[] = array(
								'key'     => $field_title,
								'display' => stripcslashes( $value ),
							);
							break;
					}

				} // End foreach().
			} // End if().

			if ( 0 < count( $item_meta ) ) {
				ob_start();
				tinv_wishlist_template( 'ti-wishlist-item-data.php', array( 'item_data' => $item_meta ) );
				$meta .= ob_get_clean();
			}
		} // End if().

		return $meta;
	}

	add_filter( 'tinvwl_wishlist_item_meta_data', 'tinv_wishlist_item_meta_woocommerce_product_add_on', 10, 3 );
} // End if().

if ( ! function_exists( 'tinv_wishlist_metasupport_woocommerce_tm_extra_product_options' ) ) {

	/**
	 * Set description for meta WooCommerce TM Extra Product Options
	 *
	 * @param array $meta Meta array.
	 * @param integer $product_id Product ID.
	 * @param integer $variation_id Product variation ID.
	 *
	 * @return array
	 */
	function tinv_wishlist_metasupport_woocommerce_tm_extra_product_options( $meta, $product_id, $variation_id ) {
		if ( array_key_exists( 'tcaddtocart', $meta ) && function_exists( 'TM_EPO_API' ) && function_exists( 'TM_EPO' ) ) {
			$has_epo = TM_EPO_API()->has_options( $product_id );
			if ( TM_EPO_API()->is_valid_options( $has_epo ) ) {
				$post_data = array();
				foreach ( $meta as $key => $value ) {
					$post_data[ $key ] = $value['display'];
				}

				$cart_class = version_compare( TM_EPO_VERSION, '4.8.0', '<' ) ? TM_EPO() : TM_EPO_CART();

				$cart_item = $cart_class->add_cart_item_data_helper( array(), $product_id, $post_data );

				if ( 'normal' == TM_EPO()->tm_epo_hide_options_in_cart && 'advanced' != TM_EPO()->tm_epo_cart_field_display && ! empty( $cart_item['tmcartepo'] ) ) {
					$cart_item['quantity']         = 1;
					$cart_item['data']             = wc_get_product( $variation_id ? $variation_id : $product_id );
					$cart_item['tm_cart_item_key'] = '';
					$item_data                     = $cart_class->get_item_data_array( array(), $cart_item );

					foreach ( $item_data as $key => $data ) {
						// Set hidden to true to not display meta on cart.
						if ( ! empty( $data['hidden'] ) ) {
							unset( $item_data[ $key ] );
							continue;
						}
						$item_data[ $key ]['key']     = ! empty( $data['key'] ) ? $data['key'] : $data['name'];
						$item_data[ $key ]['display'] = ! empty( $data['display'] ) ? $data['display'] : $data['value'];
					}

					return $item_data;
				}
			}

			return array();
		}

		return $meta;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_metasupport_woocommerce_tm_extra_product_options', 10, 3 );
} // End if().

if ( ! function_exists( 'tinvwl_item_price_woocommerce_tm_extra_product_options' ) ) {

	/**
	 * Modify price for WooCommerce TM Extra Product Options
	 *
	 * @param string $price Returned price.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return string
	 */
	function tinvwl_item_price_woocommerce_tm_extra_product_options( $price, $wl_product, $product ) {
		if ( array_key_exists( 'tcaddtocart', (array) @$wl_product['meta'] ) && function_exists( 'TM_EPO_API' ) && function_exists( 'TM_EPO' ) && TM_EPO()->tm_epo_hide_options_in_cart == 'normal' ) {
			$product_id = $wl_product['product_id'];
			$has_epo    = TM_EPO_API()->has_options( $product_id );
			if ( TM_EPO_API()->is_valid_options( $has_epo ) ) {

				$cart_class = version_compare( TM_EPO_VERSION, '4.8.0', '<' ) ? TM_EPO() : TM_EPO_CART();

				$cart_item             = $cart_class->add_cart_item_data_helper( array(), $product_id, $wl_product['meta'] );
				$cart_item['quantity'] = 1;
				$cart_item['data']     = $product;

				$product_price = apply_filters( 'wc_epo_add_cart_item_original_price', $cart_item['data']->get_price(), $cart_item );
				if ( ! empty( $cart_item['tmcartepo'] ) ) {
					$to_currency = tc_get_woocommerce_currency();
					foreach ( $cart_item['tmcartepo'] as $value ) {
						if ( array_key_exists( $to_currency, $value['price_per_currency'] ) ) {
							$value         = floatval( $value['price_per_currency'][ $to_currency ] );
							$product_price += $value;
						}
					}
				}

				$price = apply_filters( 'wc_tm_epo_ac_product_price', apply_filters( 'woocommerce_cart_item_price', $cart_class->get_price_for_cart( $product_price, $cart_item, '' ), $cart_item, '' ), '', $cart_item, $product, $product_id );
			}
		}

		return $price;
	}

	add_filter( 'tinvwl_wishlist_item_price', 'tinvwl_item_price_woocommerce_tm_extra_product_options', 10, 3 );
} // End if().

if ( ! function_exists( 'TII18n' ) ) {

	/**
	 * Return TI Yoasti 18n module class
	 *
	 * @return \TInvWL_Includes_API_Yoasti18n
	 */
	function TII18n() { // @codingStandardsIgnoreLine WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
		return TInvWL_Includes_API_Yoasti18n::instance();
	}
}

// WP Multilang string translations.
if ( function_exists( 'wpm_translate_string' ) ) {

	add_filter( 'tinvwl-general-default_title', 'wpm_translate_string' );
	add_filter( 'tinvwl-general-text_browse', 'wpm_translate_string' );
	add_filter( 'tinvwl-general-text_added_to', 'wpm_translate_string' );
	add_filter( 'tinvwl-general-text_already_in', 'wpm_translate_string' );
	add_filter( 'tinvwl-general-text_removed_from', 'wpm_translate_string' );

	add_filter( 'tinvwl-add_to_wishlist_catalog-text', 'wpm_translate_string' );
	add_filter( 'tinvwl-add_to_wishlist_catalog-text_remove', 'wpm_translate_string' );

	add_filter( 'tinvwl-product_table-text_add_to_cart', 'wpm_translate_string' );

	add_filter( 'tinvwl-table-text_add_select_to_cart', 'wpm_translate_string' );
	add_filter( 'tinvwl-table-text_add_all_to_cart', 'wpm_translate_string' );

	add_filter( 'tinvwl-social-share_on', 'wpm_translate_string' );

	add_filter( 'tinvwl-topline-text', 'wpm_translate_string' );

} // End if().


if ( ! function_exists( 'tinv_wishlist_item_meta_yith_woocommerce_product_add_on' ) ) {

	/**
	 * Set description for meta YITH WooCommerce Product Add-on
	 *
	 * @param array $meta Meta array.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return array
	 */
	function tinv_wishlist_item_meta_yith_woocommerce_product_add_on( $item_data, $product_id, $variation_id ) {

		if ( isset( $item_data['yith_wapo_is_single'] ) && class_exists( 'YITH_WAPO' ) ) {
			unset( $item_data['yith_wapo_is_single'] );

			$id = ( $variation_id ) ? $variation_id : $product_id;

			$base_product = wc_get_product( $id );

			if ( ( is_object( $base_product ) && get_option( 'yith_wapo_settings_show_product_price_cart' ) == 'yes' ) ) {


				$price = yit_get_display_price( $base_product );


				$price_html = wc_price( $price );

				$item_data[] = array(
					'key'     => __( 'Base price', 'ti-woocommerce-wishlist' ),
					'display' => $price_html,
				);

			}
			$type_list = YITH_WAPO_Type::getAllowedGroupTypes( $id );


			foreach ( $type_list as $single_type ) {

				$original_data = 'ywapo_' . $single_type->type . '_' . $single_type->id;

				$value = isset( $item_data[ $original_data ] ) ? $item_data[ $original_data ] : '';

				if ( ! $value || ! is_array( $value ) || ! isset( $value['display'] ) ) {
					$value = '';
				} elseif ( is_array( $value ) && isset( $value['display'] ) && ! ctype_digit( strval( $value['display'][0] ) ) ) {
					$value = $value['display'][0];
				} else {
					$value = YITH_WAPO_Option::getOptionDataByValueKey( $single_type, $value['display'][0], 'label' );
				}


				unset( $item_data[ $original_data ] );
				if ( $value ) {
					$item_data[] = array(
						'key'     => $single_type->label,
						'display' => $value,
					);
				}

			}

		}

		return $item_data;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_item_meta_yith_woocommerce_product_add_on', 10, 3 );
} // End if().

if ( ! function_exists( 'tinvwl_item_price_yith_woocommerce_product_add_on' ) ) {

	/**
	 * Modify price for YITH WooCommerce product Addons.
	 *
	 * @param string $price Returned price.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return string
	 */
	function tinvwl_item_price_yith_woocommerce_product_add_on( $price, $wl_product, $product ) {

		if ( class_exists( 'YITH_WAPO' ) ) {

			$type_list = YITH_WAPO_Type::getAllowedGroupTypes( $product->get_id() );

			if ( $type_list ) {

				$addons_total = 0;

				foreach ( $type_list as $single_type ) {

					$original_data = 'ywapo_' . $single_type->type . '_' . $single_type->id;

					$value = isset( $wl_product['meta'][ $original_data ] ) ? $wl_product['meta'][ $original_data ] : '';


					if ( ! is_array( $value ) || ! ctype_digit( strval( $value[0] ) ) ) {
						continue;
					}

					$addon_price = YITH_WAPO_Option::getOptionDataByValueKey( $single_type, $value[0], 'price' );

					if ( is_numeric( $addon_price ) ) {
						$addons_total += $addon_price;
					}

				}

				$price = wc_price( $product->get_price() + $addons_total );
			}
		}


		return $price;
	}

	add_filter( 'tinvwl_wishlist_item_price', 'tinvwl_item_price_yith_woocommerce_product_add_on', 10, 3 );
} // End if().


if ( ! function_exists( 'tinvwl_item_price_woocommerce_custom_fields' ) ) {

	/**
	 * Modify price for WooCommerce Custom Fields.
	 *
	 * @param string $price Returned price.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return string
	 */
	function tinvwl_item_price_woocommerce_custom_fields( $price, $wl_product, $product ) {

		if ( class_exists( 'WCCF' ) && isset( $wl_product['meta']['wccf']['product_field'] ) ) {

			$posted = array();

			foreach ( $wl_product['meta']['wccf']['product_field'] as $key => $value ) {
				$posted[ $key ] = array( 'value' => $value );
			}

			$price = wc_price( WCCF_Pricing::get_adjusted_price( $product->get_price(), $wl_product['product_id'], $wl_product['variation_id'], $posted, 1, false, false, $product, false ) );
		}

		return $price;
	}

	add_filter( 'tinvwl_wishlist_item_price', 'tinvwl_item_price_woocommerce_custom_fields', 10, 3 );
} // End if().


if ( ! function_exists( 'tinv_wishlist_item_meta_woocommerce_product_addons' ) ) {

	/**
	 * Set description for meta  WooCommerce Product Addons
	 *
	 * @param array $meta Meta array.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return array
	 */
	function tinv_wishlist_item_meta_woocommerce_product_addons( $item_data, $product_id, $variation_id ) {


		if ( class_exists( 'WC_Product_Addons' ) ) {


			$id = ( $variation_id ) ? $variation_id : $product_id;

			if ( function_exists( 'get_product_addons' ) ) {
				$product_addons = get_product_addons( $id );
			} else {
				$product_addons = WC_Product_Addons_Helper::get_product_addons( $id );
			}

			if ( $product_addons ) {


				foreach ( $product_addons as $addon ) {
					foreach ( $addon['options'] as $option ) {
						$original_data = 'addon-' . $addon['field_name'];

						if ( 'file_upload' === $addon['type'] ) {
							$original_data = 'addon-' . $addon['field_name'] . '-' . sanitize_title( $option['label'] );
						}

						$value = isset( $item_data[ $original_data ] ) ? $item_data[ $original_data ]['display'] : '';

						if ( $value == '' ) {
							continue;
						}


						if ( is_array( $value ) ) {
							$value = array_map( 'stripslashes', $value );
						} else {
							$value = stripslashes( $value );
						}
						include_once( WP_PLUGIN_DIR . '/woocommerce-product-addons/includes/fields/abstract-wc-product-addons-field.php' );
						switch ( $addon['type'] ) {
							case 'checkbox':
								include_once( WP_PLUGIN_DIR . '/woocommerce-product-addons/includes/fields/class-wc-product-addons-field-list.php' );
								$field = new WC_Product_Addons_Field_List( $addon, $value );
								break;
							case 'multiple_choice':
								switch ( $addon['display'] ) {
									case 'radiobutton':
										include_once( WP_PLUGIN_DIR . '/woocommerce-product-addons/includes/fields/class-wc-product-addons-field-list.php' );
										$field = new WC_Product_Addons_Field_List( $addon, $value );
										break;
									case 'images':
									case 'select':
										include_once( WP_PLUGIN_DIR . '/woocommerce-product-addons/includes/fields/class-wc-product-addons-field-select.php' );
										$field = new WC_Product_Addons_Field_Select( $addon, $value );
										break;
								}
								break;
							case 'custom_text':
							case 'custom_textarea':
							case 'custom_price':
							case 'input_multiplier':
								include_once( WP_PLUGIN_DIR . '/woocommerce-product-addons/includes/fields/class-wc-product-addons-field-custom.php' );
								$field = new WC_Product_Addons_Field_Custom( $addon, $value );
								break;
							case 'file_upload':
								include_once( WP_PLUGIN_DIR . '/woocommerce-product-addons/includes/fields/class-wc-product-addons-field-file-upload.php' );
								$field = new WC_Product_Addons_Field_File_Upload( $addon, $value );
								break;
							default:
								// Continue to the next field in case the type is not recognized (instead of causing a fatal error)
								break;
						}


						$data = $field->get_cart_item_data();

						unset( $item_data[ $original_data ] );
						foreach ( $data as $option ) {
							$name = $option['name'];

							if ( $option['price'] && apply_filters( 'woocommerce_addons_add_price_to_name', '__return_true' ) ) {
								$name .= ' (' . wc_price( get_product_addon_price_for_display( $option['price'] ) ) . ')';
							}

							$item_data[] = array(
								'key'     => $name,
								'display' => $option['value'],
							);
						}
					}
				}
			}
		}

		return $item_data;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_item_meta_woocommerce_product_addons', 10, 3 );
} // End if().

if ( ! function_exists( 'tinv_wishlist_item_meta_woocommerce_custom_fields' ) ) {

	/**
	 * Set description for meta  WooCommerce Custom Fields
	 *
	 * @param array $meta Meta array.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return array
	 */
	function tinv_wishlist_item_meta_woocommerce_custom_fields( $item_data, $product_id, $variation_id ) {


		if ( class_exists( 'WCCF' ) && isset( $item_data['wccf'] ) ) {

			$id      = ( $variation_id ) ? $variation_id : $product_id;
			$product = wc_get_product( $id );
			if ( $product ) {


				// Get fields to save values for
				$fields = WCCF_Product_Field_Controller::get_filtered( null, array(
					'item_id'  => $product_id,
					'child_id' => $variation_id,
				) );

				// Set quantity
				$quantity        = 1;
				$quantity_index  = null;
				$display_pricing = null;

				// Check if pricing can be displayed for this product
				if ( $display_pricing === null ) {
					$display_pricing = ! WCCF_WC_Product::skip_pricing( $product );
				}

				foreach ( $fields as $field ) {

					// Check how many times to iterate the same field (used for quantity-based product fields)
					if ( $quantity_index !== null ) {
						$iterations = ( $quantity_index + 1 );
						$i          = $quantity_index;
					} else {
						$iterations = ( $field->is_quantity_based() && $quantity ) ? $quantity : 1;
						$i          = 0;
					}

					// Start iteration of the same field
					for ( $i = $i; $i < $iterations; $i ++ ) {

						// Get field id
						$field_id = $field->get_id() . ( $i ? ( '_' . $i ) : '' );

						// Special handling for files
						if ( $field->field_type_is( 'file' ) ) {
							//just skip this field type because we can't save uploaded data.
						} // Handle other field values
						else {

							// Check if any data for this field was posted or is available in request query vars for GET requests
							if ( isset( $item_data['wccf']['display']['product_field'][ $field_id ] ) ) {

								// Get field value
								if ( isset( $item_data['wccf']['display']['product_field'][ $field_id ] ) ) {
									$field_value = $item_data['wccf']['display']['product_field'][ $field_id ];
								}

								// Prepare multiselect field values
								if ( $field->accepts_multiple_values() ) {

									// Ensure that value is array
									$value = ! RightPress_Help::is_empty( $field_value ) ? (array) $field_value : array();

									// Filter out hidden placeholder input value
									$value = array_filter( (array) $value, function ( $test_value ) {
										return trim( $test_value ) !== '';
									} );
								} else {
									$value = stripslashes( trim( $field_value ) );
								}

								$item_data[] = array(
									'key'     => $field->get_label(),
									'display' => $field->format_display_value( array( 'value' => $value ), $display_pricing ),
								);


							}
						}
					}
				}

				unset( $item_data['wccf'] );
				unset( $item_data['wccf_ignore'] );
			}
		}

		return $item_data;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_item_meta_woocommerce_custom_fields', 10, 3 );
} // End if().

if ( ! function_exists( 'tinvwl_item_price_woocommerce_product_addons' ) ) {

	/**
	 * Modify price for  WooCommerce Product Addons.
	 *
	 * @param string $price Returned price.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return string
	 */
	function tinvwl_item_price_woocommerce_product_addons( $price, $wl_product, $product ) {

		if ( class_exists( 'WC_Product_Addons' ) ) {


			if ( function_exists( 'get_product_addons' ) ) {
				$product_addons = get_product_addons( $product->get_id() );
			} else {
				$product_addons = WC_Product_Addons_Helper::get_product_addons( $product->get_id() );
			}

			if ( $product_addons ) {

				$price = 0;

				foreach ( $product_addons as $addon ) {

					$original_data = 'addon-' . $addon['field_name'];

					$value = isset( $wl_product['meta'][ $original_data ] ) ? $wl_product['meta'][ $original_data ] : '';
					if ( $value == '' ) {
						continue;
					}


					if ( is_array( $value ) ) {
						$value = array_map( 'stripslashes', $value );
					} else {
						$value = stripslashes( $value );
					}
					include_once( WP_PLUGIN_DIR . '/woocommerce-product-addons/includes/fields/abstract-wc-product-addons-field.php' );
					switch ( $addon['type'] ) {
						case 'checkbox':
							include_once( WP_PLUGIN_DIR . '/woocommerce-product-addons/includes/fields/class-wc-product-addons-field-list.php' );
							$field = new WC_Product_Addons_Field_List( $addon, $value );
							break;
						case 'multiple_choice':
							switch ( $addon['display'] ) {
								case 'radiobutton':
									include_once( WP_PLUGIN_DIR . '/woocommerce-product-addons/includes/fields/class-wc-product-addons-field-list.php' );
									$field = new WC_Product_Addons_Field_List( $addon, $value );
									break;
								case 'images':
								case 'select':
									include_once( WP_PLUGIN_DIR . '/woocommerce-product-addons/includes/fields/class-wc-product-addons-field-select.php' );
									$field = new WC_Product_Addons_Field_Select( $addon, $value );
									break;
							}
							break;
						case 'custom_text':
						case 'custom_textarea':
						case 'custom_price':
						case 'input_multiplier':
							include_once( WP_PLUGIN_DIR . '/woocommerce-product-addons/includes/fields/class-wc-product-addons-field-custom.php' );
							$field = new WC_Product_Addons_Field_Custom( $addon, $value );
							break;
						case 'file_upload':
							include_once( WP_PLUGIN_DIR . '/woocommerce-product-addons/includes/fields/class-wc-product-addons-field-file-upload.php' );
							$field = new WC_Product_Addons_Field_File_Upload( $addon, $value );
							break;
						default:
							// Continue to the next field in case the type is not recognized (instead of causing a fatal error)
							break;
					}


					$data = $field->get_cart_item_data();
					foreach ( $data as $option ) {
						if ( $option['price'] ) {
							$price += (float) $option['price'];
						}
					}


				}

				$price = wc_price( $product->get_price() + $price );
			}
		}


		return $price;
	}

	add_filter( 'tinvwl_wishlist_item_price', 'tinvwl_item_price_woocommerce_product_addons', 10, 3 );
} // End if().


// OceanWP theme compatibility;
if ( ! function_exists( 'oceanwp_fix_archive_markup' ) ) {
	add_action( 'init', 'oceanwp_fix_archive_markup' );

	/**
	 * OceanWP theme fix for catalog add to wishlist button position
	 */
	function oceanwp_fix_archive_markup() {
		if ( class_exists( 'OceanWP_WooCommerce_Config' ) && 'above_thumb' === tinv_get_option( 'add_to_wishlist_catalog', 'position' ) ) {
			remove_action( 'woocommerce_before_shop_loop_item', 'tinvwl_view_addto_htmlloop', 9 );
			add_action( 'woocommerce_before_shop_loop_item', 'tinvwl_view_addto_htmlloop', 10 );
		}
	}
}

// Google Tag Manager for WordPress compatibility.
if ( ! function_exists( 'tinv_wishlist_metaprepare_gtm4wp' ) ) {

	/**
	 * Prepare save meta for WooCommerce - Google Tag Manager for WordPress
	 *
	 * @param array $meta Meta array.
	 *
	 * @return array
	 */
	function tinv_wishlist_metaprepare_gtm4wp( $meta ) {

		foreach ( array_keys( $meta ) as $key ) {
			if ( strpos( $key, 'gtm4wp_' ) === 0 ) {
				unset( $meta[ $key ] );
			}
		}

		return $meta;
	}

	add_filter( 'tinvwl_product_prepare_meta', 'tinv_wishlist_metaprepare_gtm4wp' );
}

// WooCommerce Advanced Quantity compatibility.
if ( ! function_exists( 'tinv_wishlist_qty_woo_advanced_qty' ) ) {

	/**
	 * Force quantity to minimum.
	 *
	 * @param $quantity
	 * @param $product
	 *
	 * @return mixed
	 */
	function tinv_wishlist_qty_woo_advanced_qty( $quantity, $product ) {

		if ( class_exists( 'Woo_Advanced_QTY_Public' ) ) {
			$advanced_qty = new Woo_Advanced_QTY_Public( null, null );

			$args = $advanced_qty->qty_input_args( array(
				'min_value' => 1,
				'max_value' => '',
				'step'      => 1
			), $product );

			$quantity = $args['input_value'];
		}

		return $quantity;
	}

	add_filter( 'tinvwl_product_add_to_cart_quantity', 'tinv_wishlist_qty_woo_advanced_qty', 10, 2 );
}

// WooCommerce Advanced Quantity compatibility.
if ( ! function_exists( 'tinv_wishlist_qty_woo_advanced_url' ) ) {

	/**
	 * @param $url
	 * @param $product
	 *
	 * @return string|string[]|null
	 */
	function tinv_wishlist_qty_woo_advanced_url( $url, $product ) {

		if ( class_exists( 'Woo_Advanced_QTY_Public' ) ) {
			if ( strpos( $url, 'add-to-cart=' ) ) {
				$advanced_qty = new Woo_Advanced_QTY_Public( null, null );
				$args         = $advanced_qty->qty_input_args( array(
					'min_value' => 1,
					'max_value' => '',
					'step'      => 1,
				), $product );

				$url = preg_replace( '/&quantity=[0-9.]*/', '', $url );

				$url .= '&quantity=' . $args['input_value'];
			}
		}

		return $url;
	}

	add_filter( 'tinvwl_product_add_to_cart_redirect_slug_original', 'tinv_wishlist_qty_woo_advanced_url', 10, 2 );
	add_filter( 'tinvwl_product_add_to_cart_redirect_url_original', 'tinv_wishlist_qty_woo_advanced_url', 10, 2 );
	add_filter( 'tinvwl_product_add_to_cart_redirect_url', 'tinv_wishlist_qty_woo_advanced_url', 10, 2 );
}

if ( ! function_exists( 'tinv_wishlist_item_meta_woocommerce_custom_product_addons' ) ) {

	/**
	 * Set description for meta WooCommerce Custom Product Addons
	 *
	 * @param array $meta Meta array.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return array
	 */

	function tinv_wishlist_item_meta_woocommerce_custom_product_addons( $item_data, $product_id, $variation_id ) {
		if ( function_exists( 'WCPA' ) ) {
			$form     = new WCPA_Form();
			$frontend = new WCPA_Front_End();
			$data     = array();


			$post_ids = $form->get_form_ids( $product_id );

			if ( wcpa_get_option( 'form_loading_order_by_date' ) === true ) {
				if ( is_array( $post_ids ) && count( $post_ids ) ) {
					$post_ids = get_posts( array(
						'posts_per_page' => - 1,
						'include'        => $post_ids,
						'fields'         => 'ids',
						'post_type'      => WCPA_POST_TYPE,
						'posts_per_page' => - 1,
					) );
				}
			}
			foreach ( $post_ids as $id ) {
				if ( get_post_status( $id ) == 'publish' ) {
					$json_string  = get_post_meta( $id, WCPA_FORM_META_KEY, true );
					$json_encoded = json_decode( $json_string );
					if ( $json_encoded && is_array( $json_encoded ) ) {
						$data = array_merge( $data, $json_encoded );
					}
				}
			}


			foreach ( $data as $v ) {

				$form_data = clone $v;
				unset( $form_data->values ); //avoid saving large number of data
				unset( $form_data->className ); //avoid saving no use data
				if ( ! in_array( $v->type, array( 'header', 'paragraph' ) ) ) {
					if ( isset( $item_data[ $v->name ] ) ) {

						if ( ! is_object( $v ) ) {
							$value = sanitize_text_field( $v );
						} else if ( ( isset( $v->name ) ) ) {
							if ( is_array( $item_data[ $v->name ] ) ) {

								$_values = $item_data[ $v->name ];
								array_walk( $_values, function ( &$a ) {
									sanitize_text_field( $a );
								} ); // using this array_wal method to preserve the keys
								$value = $_values;
							} else if ( $v->type == 'textarea' ) {
								$value = sanitize_textarea_field( wp_unslash( $item_data[ $v->name ] ) );
							} else {
								$value = sanitize_text_field( wp_unslash( $item_data[ $v->name ] ) );
							}
						}
						$item_data[ $v->name ]['key']     = ( isset( $v->label ) ) ? $v->label : '';
						$item_data[ $v->name ]['display'] = $frontend->cart_display( array(
							'type'      => $v->type,
							'name'      => $v->name,
							'label'     => ( isset( $v->label ) ) ? $v->label : '',
							'value'     => $value['display'],
							'price'     => false,
							'form_data' => $form_data,
						) );
					}
				}
			}
		}

		return $item_data;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_item_meta_woocommerce_custom_product_addons', 10, 3 );
}


if ( ! function_exists( 'tinv_wishlist_meta_support_ivpa' ) ) {

	/**
	 * Set description for meta Improved Product Options for WooCommerce
	 *
	 * @param array $meta Meta array.
	 *
	 * @return array
	 */
	function tinv_wishlist_meta_support_ivpa( $meta ) {
		global $product;

		if ( class_exists( 'WC_Improved_Variable_Product_Attributes_Init' ) ) {

			$curr_customizations = WC_Improved_Variable_Product_Attributes::get_custom();

			foreach ( $meta as $k => $v ) {
				$prefix  = 'ivpac_';
				$k_ivpac = ( 0 === strpos( $k, $prefix ) ) ? substr( $k, strlen( $prefix ) ) : $k;

				$prefix  = 'attribute_';
				$k_ivpac = ( 0 === strpos( $k, $prefix ) ) ? substr( $k, strlen( $prefix ) ) : $k_ivpac;
				$v       = is_array( $v['display'] ) ? implode( ', ', $v['display'] ) : $v['display'];
				if ( isset( $curr_customizations['ivpa_attr'][ $k_ivpac ] ) ) {
					if ( $curr_customizations['ivpa_attr'][ $k_ivpac ] == 'ivpa_custom' ) {
						$meta[ $k ] = array(
							'key'     => $curr_customizations['ivpa_title'][ $k_ivpac ],
							'display' => $v,
						);
					}
				}
				if ( in_array( $k_ivpac, $curr_customizations['ivpa_attr'] ) ) {
					if ( $product->is_type( 'variation' ) && $product->get_attribute( $k_ivpac ) === $v ) {
						unset( $meta[ $k ] );
					} else {
						$meta[ $k ] = array(
							'key'     => wc_attribute_label( $k_ivpac ),
							'display' => $v,
						);
					}
				}
			}
		}

		return $meta;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_meta_support_ivpa' );
} // End if().


function tinv_add_to_wishlist_ivpa() {
	if ( class_exists( 'WC_Improved_Variable_Product_Attributes_Init' ) ) {

		wp_add_inline_script( 'tinvwl', "
		jQuery(document).ready(function($){		
		    $(document).on('tinvwl_wishlist_button_clicked', function (e, el, data) {
		    
				if (!ivpa) {
					return false;
				}
				var button = $(el);
				var container = button.closest(ivpa.settings.archive_selector);
				var find = button.closest('.summary').length > 0 ? '#ivpa-content' : '.ivpa-content';
	
				if (container.find(find).length > 0) {
					var var_id = container.find(find).attr('data-selected');
	
					if (typeof var_id == 'undefined' || var_id == '') {
						var_id = container.find('[name=\"variation_id\"]').val();
					}
	
					if (typeof var_id == 'undefined' || var_id == '') {
						var_id = container.find(find).attr('data-id');
					}
	
					var item = {};
					container.find(find + ' .ivpa_attribute').each(function () {
						var attribute = $(this).attr('data-attribute');
						var attribute_value = $(this).find('.ivpa_term.ivpa_clicked').attr('data-term');
	
						data.form['attribute_' + attribute] = attribute_value;
					});
	
					var ivpac = container.find(find + ' .ivpa_custom_option').length > 0 ? container.find(find + ' .ivpa_custom_option [name^=\"ivpac_\"]').serialize() : '';
	
					var ivpac_fields = container.find(find + ' .ivpa_custom_option').length > 0 ? container.find(find + ' .ivpa_custom_option [name^=\"ivpac_\"]') : '';
	
					ivpac_fields.each(function () {
	
						var name = $(this).attr('name').replace(/\[.*\]/g, '');
	
						if ($(this).is(':checkbox')) {
	
							if (!$(this).is(':checked')) return true;
	
							if (data.form.hasOwnProperty(name) && data.form[name].length) {
								data.form[name] = (data.form[name] + ', ' + $(this).val()).replace(/^, /, '');
							} else {
								data.form[name] = $(this).val();
							}
						} else {
							data.form[name] = $(this).val();
						}
					});
	
	
					data.form.variation_id = var_id;
					data.ivpac = ivpac;				
				}
			});
        });
        " );
	}
}

add_action( 'wp_enqueue_scripts', 'tinv_add_to_wishlist_ivpa', 100, 1 );


// myCred hooks
if ( defined( 'myCRED_VERSION' ) ) {

	/**
	 * Register Hook
	 */
	add_filter( 'mycred_setup_hooks', 'mycred_register_ti_woocommerce_wishlist_hook', 100 );
	function mycred_register_ti_woocommerce_wishlist_hook( $installed ) {

		$installed['tinvwl'] = array(
			'title'       => __( 'WooCommerce Wishlist', 'ti-woocommerce-wishlist' ),
			'description' => __( 'Awards %_plural% for users adding products to their wishlist and purchased products from their wishlist.', 'ti-woocommerce-wishlist' ),
			'callback'    => array( 'myCRED_Hook_TinvWL' ),
		);

		return $installed;

	}

	/**
	 * TI WooCommerce Wihslist Hook
	 */
	add_action( 'mycred_load_hooks', 'mycred_load_ti_woocommerce_wishlist_hook', 100 );
	function mycred_load_ti_woocommerce_wishlist_hook() {

		// If the hook has been replaced or if plugin is not installed, exit now
		if ( class_exists( 'myCRED_Hook_TinvWL' ) ) {
			return;
		}

		class myCRED_Hook_TinvWL extends myCRED_Hook {

			/**
			 * Construct
			 */
			public function __construct( $hook_prefs, $type = MYCRED_DEFAULT_TYPE_KEY ) {

				parent::__construct( array(
					'id'       => 'tinvwl',
					'defaults' => array(
						'tinvwl_added'     => array(
							'creds' => 1,
							'log'   => '%plural% for adding a product to a wishlist',
							'limit' => '0/x',
						),
						'tinvwl_purchased' => array(
							'creds' => 1,
							'log'   => '%plural% for purchasing a product from a wishlist',
							'limit' => '0/x',
						),
					),
				), $hook_prefs, $type );

			}

			/**
			 * Run
			 */
			public function run() {
				add_action( 'tinvwl_product_added', array( $this, 'added' ) );
				add_action( 'tinvwl_product_purchased', array( $this, 'purchased' ), 10, 3 );
			}

			/**
			 * Added product to a wishlist
			 *
			 * @param array $data product data including author and wishlist IDs.
			 */
			public function added( $data ) {

				// Must be logged in
				if ( ! is_user_logged_in() ) {
					return;
				}

				$user_id = get_current_user_id();

				// Award the user adding to wishlist
				if ( $this->prefs['tinvwl_added']['creds'] != 0 && ! $this->core->exclude_user( $user_id ) ) {

					// Limit
					if ( ! $this->over_hook_limit( 'tinvwl_added', 'added_to_wishlist', $user_id ) ) {

						// Make sure this is unique event
						if ( ! $this->core->has_entry( 'added_to_wishlist', $data['product_id'], $user_id ) ) {

							// Execute
							$this->core->add_creds(
								'added_to_wishlist',
								$user_id,
								$this->prefs['tinvwl_added']['creds'],
								$this->prefs['tinvwl_added']['log'],
								$data['product_id'],
								array( 'ref_type' => 'post' ),
								$this->mycred_type
							);

						}

					}

				}
			}

			/**
			 * Purchased product from a wishlist
			 *
			 * @param WC_order $order Order object.
			 * @param WC_Order_Item_Product $item Order item product object.
			 * @param array $wishlist A wishlist data where product added from.
			 */
			public function purchased( $order, $item, $wishlist ) {

				// Must be logged in
				if ( ! is_user_logged_in() ) {
					return;
				}

				$user_id = get_current_user_id();

				// Award the user adding to wishlist
				if ( $this->prefs['tinvwl_purchased']['creds'] != 0 && ! $this->core->exclude_user( $user_id ) ) {

					// Limit
					if ( ! $this->over_hook_limit( 'tinvwl_purchased', 'purchased_from_wishlist', $user_id ) ) {

						// Make sure this is unique event
						if ( ! $this->core->has_entry( 'purchased_from_wishlist', $item->get_id(), $user_id ) ) {

							// Execute
							$this->core->add_creds(
								'purchased_from_wishlist',
								$user_id,
								$this->prefs['tinvwl_purchased']['creds'],
								$this->prefs['tinvwl_purchased']['log'],
								$item->get_id(),
								array( 'ref_type' => 'post' ),
								$this->mycred_type
							);

						}

					}

				}

			}

			/**
			 * Preferences
			 */
			public function preferences() {

				$prefs = $this->prefs;

				?>
				<div class="hook-instance">
					<h3><?php _e( 'Adding Product to Wishlist', 'ti-woocommerce-wishlist' ); ?></h3>
					<div class="row">
						<div class="col-lg-2 col-md-6 col-sm-6 col-xs-12">
							<div class="form-group">
								<label
									for="<?php echo $this->field_id( array( 'tinvwl_added' => 'creds' ) ); ?>"><?php _e( 'Points', 'ti-woocommerce-wishlist' ); ?></label>
								<input type="text"
								       name="<?php echo $this->field_name( array( 'tinvwl_added' => 'creds' ) ); ?>"
								       id="<?php echo $this->field_id( array( 'tinvwl_added' => 'creds' ) ); ?>"
								       value="<?php echo $this->core->number( $prefs['tinvwl_added']['creds'] ); ?>"
								       class="form-control"/>
							</div>
						</div>
						<div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
							<div class="form-group">
								<label for="<?php echo $this->field_id( array(
									'tinvwl_added',
									'limit'
								) ); ?>"><?php _e( 'Limit', 'ti-woocommerce-wishlist' ); ?></label>
								<?php echo $this->hook_limit_setting( $this->field_name( array(
									'tinvwl_added',
									'limit'
								) ), $this->field_id( array(
									'tinvwl_added',
									'limit'
								) ), $prefs['tinvwl_added']['limit'] ); ?>
							</div>
						</div>
						<div class="col-lg-6 col-md-12 col-sm-12 col-xs-12">
							<div class="form-group">
								<label
									for="<?php echo $this->field_id( array( 'tinvwl_added' => 'log' ) ); ?>"><?php _e( 'Log Template', 'ti-woocommerce-wishlist' ); ?></label>
								<input type="text"
								       name="<?php echo $this->field_name( array( 'tinvwl_added' => 'log' ) ); ?>"
								       id="<?php echo $this->field_id( array( 'tinvwl_added' => 'log' ) ); ?>"
								       placeholder="<?php _e( 'required', 'ti-woocommerce-wishlist' ); ?>"
								       value="<?php echo esc_attr( $prefs['tinvwl_added']['log'] ); ?>"
								       class="form-control"/>
								<span class="description"><?php echo $this->available_template_tags( array(
										'general',
										'post'
									) ); ?></span>
							</div>
						</div>
					</div>
					<h3><?php _e( 'Purchasing Product from Wishlist', 'ti-woocommerce-wishlist' ); ?></h3>
					<div class="row">
						<div class="col-lg-2 col-md-6 col-sm-6 col-xs-12">
							<div class="form-group">
								<label
									for="<?php echo $this->field_id( array( 'tinvwl_purchased' => 'creds' ) ); ?>"><?php _e( 'Points', 'ti-woocommerce-wishlist' ); ?></label>
								<input type="text"
								       name="<?php echo $this->field_name( array( 'tinvwl_purchased' => 'creds' ) ); ?>"
								       id="<?php echo $this->field_id( array( 'tinvwl_purchased' => 'creds' ) ); ?>"
								       value="<?php echo $this->core->number( $prefs['tinvwl_purchased']['creds'] ); ?>"
								       class="form-control"/>
							</div>
						</div>
						<div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
							<div class="form-group">
								<label for="<?php echo $this->field_id( array(
									'tinvwl_purchased',
									'limit'
								) ); ?>"><?php _e( 'Limit', 'ti-woocommerce-wishlist' ); ?></label>
								<?php echo $this->hook_limit_setting( $this->field_name( array(
									'tinvwl_purchased',
									'limit'
								) ), $this->field_id( array(
									'tinvwl_purchased',
									'limit',
								) ), $prefs['tinvwl_purchased']['limit'] ); ?>
							</div>
						</div>
						<div class="col-lg-6 col-md-12 col-sm-12 col-xs-12">
							<div class="form-group">
								<label
									for="<?php echo $this->field_id( array( 'tinvwl_purchased' => 'log' ) ); ?>"><?php _e( 'Log Template', 'ti-woocommerce-wishlist' ); ?></label>
								<input type="text"
								       name="<?php echo $this->field_name( array( 'tinvwl_purchased' => 'log' ) ); ?>"
								       id="<?php echo $this->field_id( array( 'tinvwl_purchased' => 'log' ) ); ?>"
								       placeholder="<?php _e( 'required', 'ti-woocommerce-wishlist' ); ?>"
								       value="<?php echo esc_attr( $prefs['tinvwl_purchased']['log'] ); ?>"
								       class="form-control"/>
								<span class="description"><?php echo $this->available_template_tags( array(
										'general',
										'post',
									) ); ?></span>
							</div>
						</div>
					</div>
				</div>

				<?php

			}

			/**
			 * Sanitise Preferences
			 */
			public function sanitise_preferences( $data ) {

				if ( isset( $data['tinvwl_added']['limit'] ) && isset( $data['tinvwl_added']['limit_by'] ) ) {
					$limit = sanitize_text_field( $data['tinvwl_added']['limit'] );
					if ( $limit == '' ) {
						$limit = 0;
					}
					$data['tinvwl_added']['limit'] = $limit . '/' . $data['tinvwl_added']['limit_by'];
					unset( $data['tinvwl_added']['limit_by'] );
				}

				if ( isset( $data['tinvwl_purchased']['limit'] ) && isset( $data['tinvwl_purchased']['limit_by'] ) ) {
					$limit = sanitize_text_field( $data['tinvwl_purchased']['limit'] );
					if ( $limit == '' ) {
						$limit = 0;
					}
					$data['tinvwl_purchased']['limit'] = $limit . '/' . $data['tinvwl_purchased']['limit_by'];
					unset( $data['tinvwl_purchased']['limit_by'] );
				}

				return $data;

			}

		}

	}

	add_filter( 'mycred_all_references', 'tinvwl_mycred_references' );

	function tinvwl_mycred_references( $references ) {

		$references['purchased_from_wishlist'] = __( 'Purchased From Wishlist', 'ti-woocommerce-wishlist' );
		$references['added_to_wishlist']       = __( 'Added To Wishlist', 'ti-woocommerce-wishlist' );

		return $references;
	}

}
