<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name Divi
 *
 * @version 4.0.7
 *
 * @slug Divi
 *
 * @url http://www.elegantthemes.com/gallery/divi/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}


/**
 * Run hooks on page redirect.
 */
function divi_init() {
	if ( class_exists( 'ET_Builder_Element' ) && is_product() && version_compare( ET_BUILDER_PRODUCT_VERSION, '4.0.0', '>=' ) ) {
		global $post;
		$product = wc_get_product( $post->ID );
		if ( ! empty( $product ) && ! $product->is_in_stock() ) {
			add_action( 'woocommerce_' . $product->get_type() . '_add_to_cart', 'divi_single_product_summary', 40 );
		}
	}
}

add_action( 'template_redirect', 'divi_init' );

// Add a custom hook for single page.
function divi_single_product_summary() {
	do_action( 'tinvwl_single_product_summary' );
}
