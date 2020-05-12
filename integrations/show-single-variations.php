<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name WooCommerce Show Single Variations by Iconic
 *
 * @version 1.1.16
 *
 * @slug show-single-variations-premium
 *
 * @url https://iconicwp.com/products/woocommerce-show-single-variations/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( class_exists( 'Iconic_WSSV_Product_Variation' ) && ! function_exists( 'tinvwl_product_visibility_fix' ) ) {

	add_action( 'tinvwl_wishlist_contents_before', 'tinvwl_product_visibility_fix' );

	function tinvwl_product_visibility_fix() {
		add_filter( 'woocommerce_product_is_visible', '__return_true', 100, 2 );
	}
}
