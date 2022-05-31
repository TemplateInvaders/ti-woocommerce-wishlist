<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name ShopEngine
 *
 * @version 2.2.2
 *
 * @slug shopengine
 *
 * @url https://wpmet.com
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "shopengine";

$name = "ShopEngine";

$available = class_exists( 'ShopEngine' );

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

function tinv_shop_engine() {

	remove_action( 'tinvwl_before_add_to_cart_button', 'tinvwl_view_addto_html' );
	remove_action( 'tinvwl_single_product_summary', 'tinvwl_view_addto_htmlout' );
	remove_action( 'woocommerce_before_add_to_cart_button', 'tinvwl_view_addto_html', 9 );
	remove_action( 'woocommerce_single_product_summary', 'tinvwl_view_addto_htmlout', 29 );
	remove_action( 'catalog_visibility_before_alternate_add_to_cart_button', 'tinvwl_view_addto_html' );

	remove_action( 'tinvwl_after_add_to_cart_button', 'tinvwl_view_addto_html' );
	remove_action( 'tinvwl_single_product_summary', 'tinvwl_view_addto_htmlout' );
	remove_action( 'woocommerce_after_add_to_cart_button', 'tinvwl_view_addto_html', 20 );
	remove_action( 'woocommerce_single_product_summary', 'tinvwl_view_addto_htmlout', 31 );
	remove_action( 'catalog_visibility_after_alternate_add_to_cart_button', 'tinvwl_view_addto_html' );


	remove_action( 'tinvwl_after_thumbnails', 'tinvwl_view_addto_html' );
	remove_action( 'woocommerce_product_thumbnails', 'tinvwl_view_addto_html', 21 );

	remove_action( 'tinvwl_after_summary', 'tinvwl_view_addto_html' );
	remove_action( 'woocommerce_after_single_product_summary', 'tinvwl_view_addto_html', 11 );


	remove_action( 'tinvwl_after_shop_loop_item', 'tinvwl_view_addto_htmlloop' );
	remove_action( 'woocommerce_after_shop_loop_item', 'tinvwl_view_addto_htmlloop', 8 );


	remove_action( 'tinvwl_above_thumb_loop_item', 'tinvwl_view_addto_htmlloop' );
	remove_action( 'woocommerce_before_shop_loop_item', 'tinvwl_view_addto_htmlloop', 9 );

	remove_action( 'tinvwl_after_shop_loop_item', 'tinvwl_view_addto_htmlloop' );
	remove_action( 'woocommerce_after_shop_loop_item', 'tinvwl_view_addto_htmlloop', 20 );


	switch ( tinv_get_option( 'add_to_wishlist', 'position' ) ) {
		case 'before':
			add_action( 'woocommerce_before_add_to_cart_button', 'tinvwl_view_addto_html', 10, 0 );
			break;
		case 'shortcode':
			break;
		default:
			add_action( 'woocommerce_after_add_to_cart_button', 'tinvwl_view_addto_html', 10, 0 );
			break;
	}
	add_filter( 'woocommerce_loop_add_to_cart_link', 'tinv_shop_engine_loop', 10, 3 );


}

add_action( 'init', 'tinv_shop_engine' );

/**
 * @param $add_to_cart_html
 * @param $product
 * @param array $args
 *
 * @return mixed
 */
function tinv_shop_engine_loop( $add_to_cart_html, $product, $args = [] ) {

	$before = $after = '';

	if ( tinv_get_option( 'add_to_wishlist_catalog', 'show_in_loop' ) ) {
		ob_start();
		tinvwl_view_addto_htmlloop();
		$add_to_wishlist = ob_get_clean();

		switch ( tinv_get_option( 'add_to_wishlist_catalog', 'position' ) ) {
			case 'before':
				$before = $add_to_wishlist;
				break;
			case 'shortcode':
				break;
			default:
				$after = $add_to_wishlist;
				break;
		}
	}

	return $before . $add_to_cart_html . $after;
}

add_action( 'wp_enqueue_scripts', 'tinv_shop_engine_styles', 20 );

function tinv_shop_engine_styles() {
	wp_add_inline_style(
		'tinvwl',
		'.shopengine-single-product-item .overlay-add-to-cart .tinv-wishlist.tinvwl-loop-button-wrapper .tinvwl_add_to_wishlist_button::before{position:relative!important;left:0!important;top:0!important;margin:0!important;font-size:24px!important}.shopengine-single-product-item .overlay-add-to-cart .tinv-wishlist.tinvwl-loop-button-wrapper .tinvwl_add_to_wishlist_button{display:inline-block;margin:0;width:auto!important}.shopengine-single-product-item .overlay-add-to-cart .tinv-wishlist.tinvwl-loop-button-wrapper{display:inline-block}'
	);
}
