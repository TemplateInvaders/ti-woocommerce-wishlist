<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name WooCommerce Fast Cart
 *
 * @version 1.1.7
 *
 * @slug woocommerce-fast-cart
 *
 * @url https://barn2.com/wordpress-plugins/woocommerce-fast-cart/ref/1007/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "woocommerce-fast-cart";

$name = "WooCommerce Fast Cart";

$available = class_exists( 'Barn2\Plugin\WC_Fast_Cart\Plugin' );

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
 * Outputs the script for refreshing FastCart after wishlist actions.
 */
function tinvwl_woocommerce_fast_cart() {
	wp_add_inline_script( 'tinvwl', "
		jQuery(document).ready(function ($) {
			$(document.body).on('tinvwl_wishlist_ajax_response', function (event, element, response) {
				// Check if the action is one of the specified values and the status is true
				if (response.status && ['add_to_cart_single', 'add_to_cart_selected', 'add_to_cart_all'].includes(response.action)) {
					// Check if FastCart object exists
					if (typeof window.FastCart !== 'undefined' && typeof window.FastCart.refresh === 'function') {
						// Run the code FastCart.refresh()
						window.FastCart.refresh();
					}
				}
			});
		});
	" );
}

add_action( 'wp_enqueue_scripts', 'tinvwl_woocommerce_fast_cart', 100, 1 );

