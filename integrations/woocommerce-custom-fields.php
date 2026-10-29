<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name WooCommerce Custom Fields
 *
 * @version 2.3.2
 *
 * @slug woocommerce-custom-fields
 *
 * @url https://codecanyon.net/item/woocommerce-custom-fields/11332742
 *
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "woocommerce-custom-fields";

$name = "WooCommerce Custom Fields";

$available = class_exists('WCCF');

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

/** Normalize the WCCF form container while preserving its literal field values. */
function tinvwl_wccf_container( $value ) {
	if ( is_string( $value ) ) { $value = json_decode( $value, true ); }
	return is_array( $value ) ? $value : array();
}
function tinvwl_wccf_prepare_request( $meta ) {
	if ( isset( $meta['wccf'] ) ) { $meta['wccf'] = tinvwl_wccf_container( $meta['wccf'] ); }
	return $meta;
}
add_filter( 'tinvwl_addproduct_tocart_prepare_meta', 'tinvwl_wccf_prepare_request' );

if (!function_exists('tinvwl_item_price_woocommerce_custom_fields')) {

	/**
	 * Modify price for WooCommerce Custom Fields.
	 *
	 * @param string $price Returned price.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return string
	 */
	function tinvwl_item_price_woocommerce_custom_fields( $price, $wl_product, $product, $raw = false ) {
		$container = tinvwl_wccf_container( $wl_product['meta']['wccf'] ?? array() );
		if ( ! class_exists( 'WCCF' ) || ! isset( $container['product_field'] ) || ! is_array( $container['product_field'] ) ) { return $price; }
		$posted = array();
		foreach ( $container['product_field'] as $key => $value ) { $posted[ $key ] = array( 'value' => $value ); }
		$amount = WCCF_Pricing::get_adjusted_price( $product->get_price(), $wl_product['product_id'], $wl_product['variation_id'], $posted, 1, false, false, $product, false );
		return $raw ? $amount : wc_price( wc_get_price_to_display( $product, array( 'price' => $amount, 'qty' => 1 ) ) );
	}


	add_filter('tinvwl_wishlist_item_price', 'tinvwl_item_price_woocommerce_custom_fields', 10, 4);
} // End if().

if (!function_exists('tinv_wishlist_item_meta_woocommerce_custom_fields')) {

	/**
	 * Set description for meta  WooCommerce Custom Fields
	 *
	 * @param array $meta Meta array.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return array
	 */
	function tinv_wishlist_item_meta_woocommerce_custom_fields($item_data, $product_id, $variation_id)
	{
		if ( isset( $item_data['wccf']['display'] ) ) { $item_data['wccf']['display'] = tinvwl_wccf_container( $item_data['wccf']['display'] ); }


		if (class_exists('WCCF') && isset($item_data['wccf'])) {

			$id = ($variation_id) ? $variation_id : $product_id;
			$product = wc_get_product($id);
			if ($product) {

				// Get fields to save values for
				$fields = WCCF_Product_Field_Controller::get_filtered(null, array(
					'item_id' => $product_id,
					'child_id' => $variation_id,
				));

				$display_pricing = null;

				// Check if pricing can be displayed for this product
				if ($display_pricing === null) {
					$display_pricing = !WCCF_WC_Product::skip_pricing($product);
				}

				foreach ($fields as $field) {

					// Enumerate saved instances instead of guessing their count from a display quantity.
					$instances = array( 0 => (string) $field->get_id() );
					if ( $field->is_quantity_based() ) {
						foreach ( array_keys( $item_data['wccf']['display']['product_field'] ?? array() ) as $saved_id ) {
							if ( preg_match( '/^' . preg_quote( (string) $field->get_id(), '/' ) . '_(\d+)$/D', (string) $saved_id, $match ) ) { $instances[ (int) $match[1] ] = $saved_id; }
						}
						ksort( $instances, SORT_NUMERIC );
					}
					foreach ( $instances as $field_id ) {

						// Special handling for files
						if ($field->field_type_is('file')) {
							//just skip this field type because we can't save uploaded data.
						} // Handle other field values
						else {

							// Check if any data for this field was posted or is available in request query vars for GET requests
							if (isset($item_data['wccf']['display']['product_field'][$field_id])) {

								// Get field value
								if (isset($item_data['wccf']['display']['product_field'][$field_id])) {
									$field_value = $item_data['wccf']['display']['product_field'][$field_id];
								}

								// Prepare multiselect field values
								if ($field->accepts_multiple_values()) {

									// Ensure that value is array
									$value = !RightPress_Help::is_empty($field_value) ? (array)$field_value : array();

									// Filter out hidden placeholder input value
									$value = array_filter((array)$value, function ($test_value) {
										return trim($test_value) !== '';
									});
								} else {
									$value = is_scalar( $field_value ) || null === $field_value ? (string) $field_value : '';
								}

								$item_data[] = array(
									'key' => $field->get_label(),
									'display' => $field->format_display_value(array('value' => $value), $display_pricing),
								);

							}
						}
					}
				}

				unset($item_data['wccf']);
				unset($item_data['wccf_ignore']);
			}
		}

		return $item_data;
	}

	add_filter('tinvwl_wishlist_item_meta_post', 'tinv_wishlist_item_meta_woocommerce_custom_fields', 10, 3);
} // End if().
