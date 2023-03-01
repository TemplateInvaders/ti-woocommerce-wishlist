<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name ione360 Configurator
 *
 * @version 1.0.0
 *
 * @slug ione360-configurator
 *
 * @url https://wordpress.org/plugins/ione360-configurator/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "ione360-configurator";

$name = "ione360 Configurator";

$available = defined( 'ione360_configurator_VERSION' );

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

if ( ! function_exists( 'tinv_wishlist_meta_support_ione360_configurator' ) ) {

	/**
	 * Set description for meta ione360 Configurator
	 *
	 * @param array $meta Meta array.
	 *
	 * @return array
	 */
	function tinv_wishlist_meta_support_ione360_configurator( $meta ) {
		if ( defined( 'ione360_configurator_VERSION' ) ) {
			if ( ! empty( $meta['configurator_price'] ) ) {
				unset( $meta['configurator_price'] );
			}
			if ( ! empty( $meta['configurator_text'] ) ) {
				$meta['configurator_text']['key'] = __( 'Configuration' );
			}
		}

		return $meta;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_meta_support_ione360_configurator' );
} // End if().

function tinv_add_to_wishlist_ione360_configurator() {
	if ( defined( 'ione360_configurator_VERSION' ) ) {

		wp_add_inline_script( 'tinvwl', "
		jQuery(document).ready(function($){

			  $(document).on('tinvwl_add_to_wishlist_button_click', function (e, el, data) {
			    let selector = document.getElementById('selector');
			    if (selector){
					if ( $( el ).is( '.inited-add-wishlist-ione360' ) ) {
						return;
					}

	                selector.addToCart();
	                $(el).addClass('inited-add-wishlist inited-add-wishlist-ione360');
	                 setTimeout(function () {
			              $(el).removeClass('inited-add-wishlist').trigger('click');
			          }, 2000);
				}
			  });
        });
        " );
	}
}

add_action( 'wp_enqueue_scripts', 'tinv_add_to_wishlist_ione360_configurator', 100, 1 );

if ( ! function_exists( 'tinvwl_item_price_ione360_configurator' ) ) {

	/**
	 * Modify price for ione360 Configurator
	 *
	 * @param string $price Returned price.
	 * @param array $wl_product Wishlist Product.
	 * @param WC_Product $product Woocommerce Product.
	 *
	 * @return string
	 */
	function tinvwl_item_price_ione360_configurator( $price, $wl_product, $product ) {

		if ( defined( 'ione360_configurator_VERSION' ) ) {
			if ( isset( $wl_product['meta']['configurator_price'] ) ) {
				$price = wc_price( $wl_product['meta']['configurator_price'] );
			}
		}

		return $price;
	}

	add_filter( 'tinvwl_wishlist_item_price', 'tinvwl_item_price_ione360_configurator', 10, 3 );
} // End if().

add_action( 'tinvwl_ajax_actions_before', 'remove_ione360_configurator_cart_filters' );

function remove_ione360_configurator_cart_filters() {
	if ( defined( 'ione360_configurator_VERSION' ) ) {
		remove_filter( 'woocommerce_add_cart_item_data', 'add_configurator_text_to_cart_item', 10, 3 );
		remove_filter( 'woocommerce_add_cart_item_data', 'add_cart_price_data', 10, 3 );
	}
}

add_action( 'tinvwl_ajax_actions_after', 'add_ione360_configurator_cart_filters' );
function add_ione360_configurator_cart_filters() {
	if ( defined( 'ione360_configurator_VERSION' ) ) {
		add_filter( 'woocommerce_add_cart_item_data', 'add_configurator_text_to_cart_item', 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data', 'add_cart_price_data', 10, 3 );
	}
}

add_filter( 'tinvwl_addproduct_tocart', 'tinvwl_add_to_cart_meta_ione360_configurator' );

function tinvwl_add_to_cart_meta_ione360_configurator( $wl_product ) {
	if ( defined( 'ione360_configurator_VERSION' ) ) {

		if ( isset( $wl_product['meta']['add-to-cart'] ) ) {
			unset( $wl_product['meta']['add-to-cart'] );
		}
		if ( isset( $wl_product['meta']['quantity'] ) ) {
			unset( $wl_product['meta']['quantity'] );
		}
		if ( isset( $wl_product['meta']['product_id'] ) ) {
			unset( $wl_product['meta']['product_id'] );
		}

		krsort( $wl_product['meta'] );
	}

	return $wl_product;
}
