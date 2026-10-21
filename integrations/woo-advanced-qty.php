<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name WooCommerce Advanced Quantity
 *
 * @version 2.4.4
 *
 * @slug woo-advanced-qty
 *
 * @url https://codecanyon.net/item/woocommerce-advanced-quantity/11861326
 *
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "woo-advanced-qty";

$name = "WooCommerce Advanced Quantity";

$available = class_exists('Woo_Advanced_QTY_Public');

$tinvwl_integrations = is_array( $tinvwl_integrations ) ? $tinvwl_integrations : [];

$tinvwl_integrations[$slug] = array(
	'name' => $name,
	'available' => $available,
);

if (!tinv_get_option('integrations', $slug)) {
	return;
}

if (!$available) {
	return;
}

// WooCommerce Advanced Quantity compatibility.
if (!function_exists('tinv_wishlist_qty_woo_advanced_qty')) {

	/**
	 * Preserve the requested quantity while respecting the vendor minimum.
	 *
	 * @param $quantity
	 * @param $product
	 *
	 * @return mixed
	 */
	function tinv_wishlist_qty_woo_advanced_qty($quantity, $product)
	{

		if (class_exists('Woo_Advanced_QTY_Public')) {
			$advanced_qty = new Woo_Advanced_QTY_Public(null, null);
			$has_quantity = is_numeric($quantity) && (float)$quantity > 0;

			$args = $advanced_qty->qty_input_args(array(
				'min_value' => 1,
				'max_value' => '',
				'step' => 1,
				'input_value' => $has_quantity ? $quantity : 1,
			), $product);

			$minimum = isset($args['min_value']) && is_numeric($args['min_value']) ? max(0, (float)$args['min_value']) : 0;
			if (!$has_quantity) {
				$quantity = isset($args['input_value']) && is_numeric($args['input_value']) && (float)$args['input_value'] > 0 ? $args['input_value'] : 1;
			}
			$quantity = max((float)$quantity, $minimum);
		}

		return $quantity;
	}

	add_filter('tinvwl_product_add_to_cart_quantity', 'tinv_wishlist_qty_woo_advanced_qty', 10, 2);
}

// WooCommerce Advanced Quantity compatibility.
if (!function_exists('tinv_wishlist_qty_woo_advanced_url')) {

	/**
	 * @param $url
	 * @param $product
	 *
	 * @return string|string[]|null
	 */
	function tinv_wishlist_qty_woo_advanced_url($url, $product)
	{

		if (class_exists('Woo_Advanced_QTY_Public')) {
			parse_str((string)wp_parse_url($url, PHP_URL_QUERY), $query);
			if (isset($query['add-to-cart'])) {
				$quantity = isset($query['quantity']) && is_numeric($query['quantity']) ? $query['quantity'] : 0;
				$url = add_query_arg('quantity', tinv_wishlist_qty_woo_advanced_qty($quantity, $product), $url);
			}
		}

		return $url;
	}

	add_filter('tinvwl_product_add_to_cart_redirect_slug_original', 'tinv_wishlist_qty_woo_advanced_url', 10, 2);
	add_filter('tinvwl_product_add_to_cart_redirect_url_original', 'tinv_wishlist_qty_woo_advanced_url', 10, 2);
	add_filter('tinvwl_product_add_to_cart_redirect_url', 'tinv_wishlist_qty_woo_advanced_url', 10, 2);
}
