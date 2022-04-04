<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name WooCommerce Waitlist
 *
 * @version 2.3.2
 *
 * @slug woocommerce-waitlist
 *
 * @url https://woocommerce.com/document/woocommerce-waitlist/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "woocommerce-waitlist";

$name = "WooCommerce Waitlist";

$available = defined( 'WCWL_VERSION' );

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

// WooCommerce Waitlist compatibility.
if ( ! function_exists( 'tinvwl_wishlist_item_meta_post_woocommerce_waitlist' ) ) {

	/**
	 * Set description for meta for WooCommerce Waitlist
	 *
	 * @param array $meta Meta array.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return array
	 */
	function tinvwl_wishlist_item_meta_post_woocommerce_waitlist( $item_data, $product_id, $variation_id ) {

		foreach ( array_keys( $item_data ) as $key ) {
			if ( strpos( $key, 'wcwl_' ) === 0 ) {
				unset( $item_data[ $key ] );
			}
		}

		return $item_data;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinvwl_wishlist_item_meta_post_woocommerce_waitlist', 10, 3 );
}
