<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name Nasa Core
 *
 * @version 2.3.7
 *
 * @slug nasa-core
 *
 * @url https://nasatheme.com
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "nasa-core";

$name = "Nasa Core";

$available = defined( 'NASA_CORE_ACTIVED' );

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

/**
 * Set description for meta Quick Buy Now Button for WooCommerce
 *
 * @param array $meta Meta array.
 * @param array $wl_product Wishlist Product.
 * @param \WC_Product $product Woocommerce Product.
 *
 * @return array
 */

function tinv_wishlist_item_meta_nasa_core( $item_data, $product_id, $variation_id ) {
	$nasa = false;
	foreach ( array_keys( $item_data ) as $key ) {
		if ( strpos( $key, 'nasa' ) === 0 ) {
			$nasa = true;
			unset( $item_data[ $key ] );
		}
	}

	if ( $nasa ) {
		unset( $item_data['data-product_id'] );
		unset( $item_data['data-type'] );
		unset( $item_data['data-from_wishlist'] );
	}

	return $item_data;
}

add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_item_meta_nasa_core', 10, 3 );
