<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name WP Armour - Honeypot Anti Spam
 *
 * @version 2.1.15
 *
 * @slug honeypot
 *
 * @url https://wordpress.org/plugins/honeypot/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "honeypot";

$name = "WP Armour - Honeypot Anti Spam";

$available = isset( $GLOBALS['wpa_version'] );

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

// WP Armour - Honeypot Anti Spam compatibility.
if ( ! function_exists( 'tinvwl_wishlist_item_meta_post_honeypot' ) ) {

	/**
	 * Set description for meta for WP Armour - Honeypot Anti Spam
	 *
	 * @param array $meta Meta array.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return array
	 */
	function tinvwl_wishlist_item_meta_post_honeypot( $item_data, $product_id, $variation_id ) {

		$honeypot_field_name = get_option( 'wpa_field_name' );

		foreach ( array_keys( $item_data ) as $key ) {
			if ( strpos( $key, $honeypot_field_name ) === 0 ) {
				unset( $item_data[ $key ] );
			}
		}

		return $item_data;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinvwl_wishlist_item_meta_post_honeypot', 10, 3 );
}
