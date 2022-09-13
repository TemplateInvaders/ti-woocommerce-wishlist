<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name WooCommerce Variation Swatches - Pro
 *
 * @version 2.0.9
 *
 * @slug woo-variation-swatches-pro
 *
 * @url https://getwooplugins.com/plugins/woocommerce-variation-swatches/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "woo-variation-swatches-pro";

$name = "WooCommerce Variation Swatches - Pro";

$available = class_exists( 'Woo_Variation_Swatches_Pro' );

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

if ( class_exists( 'Woo_Variation_Swatches_Pro' ) ) {

	function tinv_add_to_wishlist_woo_variation_swatches_pro() {
		wp_add_inline_script( 'tinvwl', "
		jQuery(document).ready(function($){
			  $(document).on('tinvwl_wishlist_button_clicked', function (e, el, data) {
			        var button = $(el);

			        var wrapper = button.closest('div.tinv-wraper');

			        if (wrapper.hasClass('tinvwl-loop-button-wrapper')){

			            var container = wrapper.closest('*.product');

			            if (container.find('a.add_to_cart_button').length > 0){
			                var hash,  url, hashes;
			                url = container.find('a.add_to_cart_button').attr('href')
						    hashes = url.slice(url.indexOf('?') + 1).split('&');
						    for(var i = 0; i < hashes.length; i++)
						    {
							    hash = hashes[i].split('=');
							    if ('variation_id' === hash[0]){
								     data.form.variation_id = hash[1];
								}
						    }
			            }
			        }
			  });
        });
        " );
	}

	add_action( 'wp_enqueue_scripts', 'tinv_add_to_wishlist_woo_variation_swatches_pro', 100, 1 );
}
