<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name All in One Product Quantity for WooCommerce
 *
 * @version 5.4.5
 *
 * @slug product-quantity-for-woocommerce
 *
 * @url https://wordpress.org/plugins/product-quantity-for-woocommerce/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "product-quantity-for-woocommerce";

$name = "All in One Product Quantity for WooCommerce";

$available = class_exists( 'Alg_WC_PQ' ) || class_exists( 'WPFMMSQ' );

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

if ( ! function_exists( 'tinv_wishlist_cart_quantity_alg_wc_pq' ) ) {

	/**
	 * Set description for meta All in One Product Quantity for WooCommerce
	 *
	 * @param int|float $qty Product quantity.
	 * @param array $wl_product Wishlist product data.
	 *
	 * @return int|float
	 */
	function tinv_wishlist_cart_quantity_alg_wc_pq( $qty, $wl_product ) {

		$quantity_plugin = function_exists( 'wpfmmsq' ) ? wpfmmsq() : alg_wc_pq();

		// Free supplies one when no quantity is chosen; retain its configured default.
		$default_quantity = 1 == $qty ? $quantity_plugin->core->get_product_qty_default( $wl_product['product_id'] ) : $qty;
		$minimum = $quantity_plugin->core->get_product_qty_min_max( $wl_product['product_id'], $default_quantity, 'min', $wl_product['variation_id'] );

		return max( $qty, $minimum );
	}

	add_filter( 'tinvwl_wishlist_product_add_cart_qty', 'tinv_wishlist_cart_quantity_alg_wc_pq', 10, 2 );
} // End if().

if ( ! function_exists( 'tinv_wishlist_metaprepare_alg_wc_pq' ) ) {

	/**
	 * Prepare save meta for All in One Product Quantity for WooCommerce
	 *
	 * @param array $meta Meta array.
	 *
	 * @return array
	 */
	function tinv_wishlist_metaprepare_alg_wc_pq( $item_data, $product_id, $variation_id ) {

		foreach ( array_keys( $item_data ) as $key ) {
			if ( strpos( $key, 'quantity_pq' ) === 0 ) {
				unset( $item_data[ $key ] );
			}
		}

		return $item_data;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_metaprepare_alg_wc_pq', 10, 3 );
}
