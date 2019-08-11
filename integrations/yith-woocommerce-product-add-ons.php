<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name YITH WooCommerce Product Add-Ons
 *
 * @version 1.5.20
 *
 * @slug yith-woocommerce-product-add-ons
 *
 * @url https://wordpress.org/plugins/yith-woocommerce-product-add-ons/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

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
