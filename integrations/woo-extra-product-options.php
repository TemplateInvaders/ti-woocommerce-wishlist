<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name Extra product options For WooCommerce | Custom Product Addons and Fields
 *
 * @version 3.1.9
 *
 * @slug woo-extra-product-options
 *
 * @url https://wordpress.org/plugins/woo-extra-product-options/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "woo-extra-product-options";

$name = "Extra product options For WooCommerce | Custom Product Addons and Fields";

$available = class_exists( 'WEPOF_Extra_Product_Options' );

$tinvwl_integrations = is_array( $tinvwl_integrations ) ? $tinvwl_integrations : [];

$tinvwl_integrations[ $slug ] = array(
	'name'      => $name,
	'available' => $available,
);

if ( ! tinv_get_option( 'integrations', $slug ) ) {
	return;
}

if ( ! $available ) {
	return;
}

if ( ! function_exists( 'tinv_wishlist_item_meta_woo_extra_product_options' ) ) {

	/**
	 * Set description for meta  Extra product options For WooCommerce | Custom Product Addons and Fields
	 *
	 * @param array $item_data Meta array.
	 * @param int $product_id Wishlist Product.
	 * @param int $variation_id Woocommerce Product.
	 *
	 * @return array
	 */
	function tinv_wishlist_item_meta_woo_extra_product_options( $item_data, $product_id, $variation_id ) {

		if ( class_exists( 'WEPOF_Extra_Product_Options' ) ) {
			$final_fields  = array();
			$extra_options = THWEPOF_Utils::get_product_fields_full();
			foreach ( $item_data as $key => $value ) {
				if ( isset( $extra_options[ $key ] ) ) {

					$val = json_decode( $value['display'], true );
					if ( JSON_ERROR_NONE !== json_last_error() ) {
						$val = $value['display'];
					}

					$data_arr            = array();
					$data_arr['order']   = $extra_options[ $key ]->get_property( 'order' );
					$data_arr['name']    = $value['key'];
					$data_arr['value']   = is_array( $val ) ? implode( ',', $val ) : $val;
					$data_arr['type']    = $extra_options[ $key ]->get_property( 'type' );
					$data_arr['label']   = $extra_options[ $key ]->get_property( 'title' );
					$data_arr['options'] = $extra_options[ $key ]->get_property( 'options' );

					$final_fields[ $key ] = $data_arr;

					unset( $item_data[ $key ] );
				}
			}

			array_multisort( array_map( function ( $element ) {
				return $element['order'];
			}, $final_fields ), SORT_ASC, $final_fields );

			if ( $final_fields ) {
				foreach ( $final_fields as $name => $data ) {
					if ( isset( $data['value'] ) && isset( $data['label'] ) ) {
						$display = isset( $data['value'] ) ? $data['value'] : '';
						$display = is_array( $display ) ? implode( ",", $display ) : trim( stripslashes( $display ) );

						if ( isset( $data['type'] ) && ( $data['type'] === 'colorpicker' ) ) {
							$display = THWEPOF_Utils::get_cart_item_color_display( $display );
						}

						$item_data[ $name ] = array(
							"key"     => $data['label'],
							"display" => $display,
						);
					}
				}
			}

			if ( isset( $item_data['thwepof_product_fields'] ) ) {
				unset( $item_data['thwepof_product_fields'] );
			}
		}

		return $item_data;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_item_meta_woo_extra_product_options', 10, 3 );
} // End if().

add_filter( 'tinvwl_addproduct_tocart', 'tinvwl_add_to_cart_meta_woo_extra_product_options' );

function tinvwl_add_to_cart_meta_woo_extra_product_options( $wl_product ) {
	if ( class_exists( 'WEPOF_Extra_Product_Options' ) ) {

		$item_data     = $wl_product['meta'];
		$final_fields  = array();
		$extra_options = THWEPOF_Utils::get_product_fields_full();
		foreach ( $item_data as $key => $value ) {
			if ( isset( $extra_options[ $key ] ) ) {

				$val = json_decode( $value, true );
				if ( JSON_ERROR_NONE !== json_last_error() ) {
					$val = $value;
				}

				$data_arr            = array();
				$data_arr['order']   = $extra_options[ $key ]->get_property( 'order' );
				$data_arr['name']    = $key;
				$data_arr['value']   = $val;
				$data_arr['type']    = $extra_options[ $key ]->get_property( 'type' );
				$data_arr['label']   = $extra_options[ $key ]->get_property( 'title' );
				$data_arr['options'] = $extra_options[ $key ]->get_property( 'options' );

				$final_fields[ $key ] = $data_arr;

				unset( $wl_product['meta'][ $key ] );
			}
		}

		if ( isset( $wl_product['meta']['add-to-cart'] ) ) {
			unset( $wl_product['meta']['add-to-cart'] );
		}
		if ( isset( $wl_product['meta']['quantity'] ) ) {
			unset( $wl_product['meta']['quantity'] );
		}
		if ( isset( $wl_product['meta']['product_id'] ) ) {
			unset( $wl_product['meta']['product_id'] );
		}

		array_multisort( array_map( function ( $element ) {
			return $element['order'];
		}, $final_fields ), SORT_ASC, $final_fields );

		if ( $final_fields ) {
			foreach ( $final_fields as $name => $data ) {
				if ( isset( $data['value'] ) ) {
					$wl_product['meta'][ $name ] = $data['value'];
				}
			}
		}

	}

	return $wl_product;
}
