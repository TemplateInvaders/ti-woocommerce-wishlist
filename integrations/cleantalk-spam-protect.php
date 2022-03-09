<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name Anti-Spam by CleanTalk
 *
 * @version 5.173
 *
 * @slug cleantalk-spam-protect
 *
 * @url https://wordpress.org/plugins/cleantalk-spam-protect/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "cleantalk-spam-protect";

$name = "Anti-Spam by CleanTalk";

$available = defined( 'APBCT_NAME' );

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

// Anti-Spam by CleanTalk compatibility.
if ( ! function_exists( 'tinvwl_wishlist_item_meta_post_cleantalk_spam_protect' ) ) {

	/**
	 * Set description for meta for Anti-Spam by CleanTalk
	 *
	 * @param array $meta Meta array.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return array
	 */
	function tinvwl_wishlist_item_meta_post_cleantalk_spam_protect( $item_data, $product_id, $variation_id ) {

		foreach ( array_keys( $item_data ) as $key ) {
			if ( strpos( $key, 'apbct_' ) === 0 ) {
				unset( $item_data[ $key ] );
			}
		}

		return $item_data;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinvwl_wishlist_item_meta_post_cleantalk_spam_protect', 10, 3 );
}
