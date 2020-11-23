<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name WooCommerce Gravity Forms Product Add-Ons
 *
 * @version 3.3.8
 *
 * @slug woocommerce-gravityforms-product-addons
 *
 * @url https://woocommerce.com/products/gravity-forms-add-ons/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! function_exists( 'tinvwl_gf_productaddon_support' ) ) {

	/**
	 * Add supports WooCommerce - Gravity Forms Product Add-Ons
	 */
	function tinvwl_gf_productaddon_support() {
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
				$gravity_form_data = get_post_meta( ( ( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() ) ), '_gravity_form_data', true );

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
				$gravity_form_data = get_post_meta( ( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() ), '_gravity_form_data', true );

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
				$gravity_form_data = get_post_meta( ( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() ), '_gravity_form_data', true );

				return ( $gravity_form_data ) ? $product->get_permalink() : $url;
			}

			add_filter( 'tinvwl_product_add_to_cart_redirect_url', 'gf_productaddon_action_button', 10, 2 );
		}
	}

	add_action( 'init', 'tinvwl_gf_productaddon_support' );
} // End if().

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
