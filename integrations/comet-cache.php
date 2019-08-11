<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name Comet Cache
 *
 * @version 170220
 *
 * @slug comet-cache
 *
 * @url https://wordpress.org/plugins/comet-cache/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

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
	add_action( 'tinvwl_wishlist_addtowishlist_button', 'tinvwl_comet_cache_reject' );
	add_action( 'tinvwl_wishlist_addtowishlist_dialogbox', 'tinvwl_comet_cache_reject' );
}
