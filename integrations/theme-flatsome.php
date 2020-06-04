<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name Flatsome
 *
 * @version 3.8.3
 *
 * @slug flatsome
 *
 * @url http://flatsome.uxthemes.com/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! function_exists( 'tinvwl_flatsome_init' ) ) {

	/**
	 * Run hooks after theme init.
	 */
	function tinvwl_flatsome_init() {
		if ( function_exists( 'flatsome_option' ) ) {


			if ( get_theme_mod( 'catalog_mode' ) ) {

				add_filter( 'tinvwl_allow_addtowishlist_single_product_summary', 'tinvwl_flatsome_woocommerce_catalog_mode', 10, 2 );

				switch ( tinv_get_option( 'add_to_wishlist', 'position' ) ) {
					case 'before':
						add_action( 'woocommerce_single_variation', 'tinvwl_view_addto_html', 10 );
						break;
					case 'after':
						add_action( 'woocommerce_single_variation', 'tinvwl_view_addto_html', 20 );
						break;
				}

				add_action( 'woocommerce_single_variation', 'tinvwl_tinvwl_flatsome_woocommerce_catalog_mode_variable', 20 );


			}
		}
	}

	add_action( 'init', 'tinvwl_flatsome_init' );
}

if ( ! function_exists( 'tinvwl_tinvwl_flatsome_woocommerce_catalog_mode_variable' ) ) {

	/**
	 * Output variation hidden field.
	 *
	 */
	function tinvwl_tinvwl_flatsome_woocommerce_catalog_mode_variable() {
		echo '<input type="hidden" name="variation_id" class="variation_id" value="0" />';
	}
}

if ( ! function_exists( 'tinvwl_flatsome_woocommerce_catalog_mode' ) ) {

	/**
	 * Output wishlist button for Flatsome catalog mode
	 *
	 * @param bool $allow allow output.
	 *
	 * @return bool
	 */
	function tinvwl_flatsome_woocommerce_catalog_mode( $allow, $product ) {
		if ( ! $product->is_type( 'variable' ) ) {
			return true;
		}

		return $allow;
	}
}
