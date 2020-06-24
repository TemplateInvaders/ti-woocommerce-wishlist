<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name WooCommerce TM Extra Product Options
 *
 * @version 5.0.12.1
 *
 * @slug woocommerce-tm-extra-product-options
 *
 * @url https://codecanyon.net/item/woocommerce-extra-product-options/7908619
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

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
		if ( array_key_exists( 'tcaddtocart', $meta ) && ( defined( 'THEMECOMPLETE_EPO_VERSION' ) || defined( 'TM_EPO_VERSION' ) ) ) {
			$api     = defined( 'THEMECOMPLETE_EPO_VERSION' ) ? THEMECOMPLETE_EPO_API() : TM_EPO_API();
			$core    = defined( 'THEMECOMPLETE_EPO_VERSION' ) ? THEMECOMPLETE_EPO() : TM_EPO();
			$version = defined( 'THEMECOMPLETE_EPO_VERSION' ) ? THEMECOMPLETE_EPO_VERSION : TM_EPO_VERSION;
			$cart    = defined( 'THEMECOMPLETE_EPO_VERSION' ) ? THEMECOMPLETE_EPO_CART() : TM_EPO();

			$has_epo = $api->has_options( $product_id );
			if ( $api->is_valid_options( $has_epo ) ) {
				$post_data = array();
				foreach ( $meta as $key => $value ) {
					$post_data[ $key ] = $value['display'];
				}

				$cart_class = version_compare( $version, '4.8.0', '<' ) ? $core : $cart;

				$cart_item = $cart_class->add_cart_item_data_helper( array(), $product_id, $post_data );
				if ( 'normal' == $core->tm_epo_hide_options_in_cart && 'advanced' != $core->tm_epo_cart_field_display && ! empty( $cart_item['tmcartepo'] ) ) {
					$cart_item['quantity']         = 1;
					$cart_item['data']             = wc_get_product( $variation_id ? $variation_id : $product_id );
					$cart_item['tm_cart_item_key'] = '';
					$cart_item['product_id']       = $product_id;
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
		if ( array_key_exists( 'tcaddtocart', (array) @$wl_product['meta'] ) && ( defined( 'THEMECOMPLETE_EPO_VERSION' ) || defined( 'TM_EPO_VERSION' ) ) ) {

			$api     = defined( 'THEMECOMPLETE_EPO_VERSION' ) ? THEMECOMPLETE_EPO_API() : TM_EPO_API();
			$core    = defined( 'THEMECOMPLETE_EPO_VERSION' ) ? THEMECOMPLETE_EPO() : TM_EPO();
			$version = defined( 'THEMECOMPLETE_EPO_VERSION' ) ? THEMECOMPLETE_EPO_VERSION : TM_EPO_VERSION;
			$cart    = defined( 'THEMECOMPLETE_EPO_VERSION' ) ? THEMECOMPLETE_EPO_CART() : TM_EPO();
			if ( $core->tm_epo_hide_options_in_cart == 'normal' ) {
				$product_id = $wl_product['product_id'];
				$has_epo    = $api->has_options( $product_id );
				if ( $api->is_valid_options( $has_epo ) ) {

					$cart_class = version_compare( $version, '4.8.0', '<' ) ? $core : $cart;

					$cart_item             = $cart_class->add_cart_item_data_helper( array(), $product_id, $wl_product['meta'] );
					$cart_item['quantity'] = 1;
					$cart_item['data']     = $product;

					$product_price = apply_filters( 'wc_epo_add_cart_item_original_price', $cart_item['data']->get_price(), $cart_item );
					if ( ! empty( $cart_item['tmcartepo'] ) ) {
						$to_currency = version_compare( $version, '4.9.0', '<' ) ? tc_get_woocommerce_currency() : themecomplete_get_woocommerce_currency();
						foreach ( $cart_item['tmcartepo'] as $value ) {
							if ( isset( $value['price_per_currency'] ) && array_key_exists( $to_currency, $value['price_per_currency'] ) ) {
								$value         = floatval( $value['price_per_currency'][ $to_currency ] );
								$product_price += $value;
							} else {
								$product_price += floatval( $value['price'] );
							}
						}
					}

					$price = apply_filters( 'wc_tm_epo_ac_product_price', apply_filters( 'woocommerce_cart_item_price', $cart_class->get_price_for_cart( $product_price, $cart_item, '' ), $cart_item, '' ), '', $cart_item, $product, $product_id );
				}
			}
		}

		return $price;
	}

	add_filter( 'tinvwl_wishlist_item_price', 'tinvwl_item_price_woocommerce_tm_extra_product_options', 10, 3 );
} // End if().

add_filter( 'tinvwl_addtowishlist_prepare_form', 'tinvwl_meta_woocommerce_tm_extra_product_options', 10, 3 );

function tinvwl_meta_woocommerce_tm_extra_product_options( $meta, $post, $files ) {

	if ( defined( 'THEMECOMPLETE_EPO_VERSION' ) || defined( 'TM_EPO_VERSION' ) ) {
		foreach ( $files as $name => $file ) {

			if ( array_key_exists( $name, $meta ) ) {
				$upload = THEMECOMPLETE_EPO()->upload_file( $file );
				if ( empty( $upload['error'] ) && ! empty( $upload['file'] ) ) {
					$meta[ $name ] = wc_clean( $upload['url'] );
				}
			}
		}
	}

	return $meta;
}

add_filter( 'tinvwl_product_prepare_meta', 'tinvwl_cart_meta_woocommerce_tm_extra_product_options' );

function tinvwl_cart_meta_woocommerce_tm_extra_product_options( $meta ) {

	if ( defined( 'THEMECOMPLETE_EPO_VERSION' ) || defined( 'TM_EPO_VERSION' ) ) {

		$files = $_FILES;

		foreach ( $files as $name => $file ) {

			if ( ! array_key_exists( $name, $meta ) ) {
				$upload = THEMECOMPLETE_EPO()->upload_file( $file );
				if ( empty( $upload['error'] ) && ! empty( $upload['file'] ) ) {
					$meta[ $name ] = wc_clean( $upload['url'] );
				}
			}
		}
	}

	return $meta;
}
