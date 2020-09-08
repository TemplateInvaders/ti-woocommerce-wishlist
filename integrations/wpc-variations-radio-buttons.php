<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name WPC Variations Radio Buttons for WooCommerce
 *
 * @version 2.2.1
 *
 * @slug wpc-variations-radio-buttons
 *
 * @url https://wordpress.org/plugins/wpc-variations-radio-buttons/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( defined( 'WOOVR_VERSION' ) ) {

	/**
	 * Set description for meta WPC Variations Radio Buttons for WooCommerce
	 *
	 * @param array $meta Meta array.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return array
	 */

	function tinv_wishlist_item_meta_wpc_variations_radio_buttons( $item_data, $product_id, $variation_id ) {

		foreach ( array_keys( $item_data ) as $key ) {
			if ( strpos( $key, 'woovr_' ) === 0 ) {
				unset( $item_data[ $key ] );
			}
		}


		return $item_data;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_item_meta_wpc_variations_radio_buttons', 10, 3 );
}
