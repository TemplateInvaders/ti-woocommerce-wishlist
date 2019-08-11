<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name Google Tag Manager for WordPress
 *
 * @version 1.10
 *
 * @slug duracelltomi-google-tag-manager
 *
 * @url https://wordpress.org/plugins/duracelltomi-google-tag-manager/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
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
