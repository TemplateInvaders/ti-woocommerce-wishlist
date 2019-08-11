<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name WP Rocket
 *
 * @version 3.3.6
 *
 * @slug wp-rocket
 *
 * @url https://wp-rocket.me/
 *
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
