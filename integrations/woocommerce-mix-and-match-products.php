<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name WooCommerce Mix and Match
 *
 * @version 2.8.7
 *
 * @slug woocommerce-mix-and-match-products
 *
 * @url https://woocommerce.com/products/woocommerce-mix-and-match-products/
 *
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "woocommerce-mix-and-match-products";

$name = "WooCommerce Mix and Match";

$available = class_exists('WC_Mix_and_Match');

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

if (!function_exists('tinv_wishlist_metasupport_woocommerce_mix_and_match_products')) {

	/**
	 * Set description for meta WooCommerce Mix and Match
	 *
	 * @param array $meta Meta array.
	 * @param integer $product_id Product ID.
	 *
	 * @return array
	 */
	function tinv_wishlist_metasupport_woocommerce_mix_and_match_products( $meta, $product_id ) {
		if (array_key_exists('mnm_quantity', $meta)) {
			$product = wc_get_product($product_id);
			if (is_object($product) && $product->is_type('mix-and-match')) {
				$components = tinvwl_mnm_saved_components( array( 'meta' => $meta ), $product );
				$meta = false === $components ? array( 'tinvwl_configuration' => array( 'key' => esc_html__( 'Configuration', 'ti-woocommerce-wishlist' ), 'display' => esc_html__( 'Saved configuration is unavailable.', 'ti-woocommerce-wishlist' ) ) ) : array();
			}
		}

		return $meta;
	}

	add_filter('tinvwl_wishlist_item_meta_post', 'tinv_wishlist_metasupport_woocommerce_mix_and_match_products', 10, 2);
} // End if().

if ( ! function_exists( 'tinvwl_mnm_child_products' ) ) {
	/** Resolve native child items to their configured product instances. */
	function tinvwl_mnm_child_products( $product ) {
		$items = is_callable( array( $product, 'get_child_items' ) ) ? $product->get_child_items() : $product->get_children();
		$products = array();
		foreach ( (array) $items as $item ) {
			$child = $item instanceof WC_Product ? $item : ( is_object( $item ) && is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : false );
			if ( $child instanceof WC_Product ) {
				$products[ $child->get_id() ] = $child;
			}
		}
		return $products;
	}
}

/** Resolve the exact saved selection; null denotes an unconfigured catalog item. */
function tinvwl_mnm_saved_components( $wl_product, $product ) {
	if ( ! TInvWL_Product::can_read_product( $product ) ) {
		return false;
	}
	$meta = $wl_product['meta'] ?? array();
	$meta = is_string( $meta ) ? json_decode( $meta, true ) : $meta;
	if ( ! is_array( $meta ) ) {
		return false;
	}
	if ( ! array_key_exists( 'mnm_quantity', $meta ) ) {
		return null;
	}
	$quantities = $meta['mnm_quantity'];
	if ( is_array( $quantities ) && array_key_exists( 'display', $quantities ) ) {
		$quantities = $quantities['display'];
	}
	$quantities = is_string( $quantities ) ? json_decode( $quantities, true ) : $quantities;
	if ( ! is_array( $quantities ) ) {
		return false;
	}
	$items = is_callable( array( $product, 'get_child_items' ) ) ? $product->get_child_items( 'edit' ) : $product->get_children();
	if ( ! is_array( $items ) ) {
		return false;
	}
	$allowed = array();
	foreach ( $items as $item ) {
		$id = $item instanceof WC_Product ? $item->get_id() : ( is_callable( array( $item, 'get_the_id' ) ) ? $item->get_the_id() : 0 );
		if ( $id ) {
			$allowed[ $id ] = $item;
		}
	}
	$components = array();
	foreach ( $quantities as $id => $quantity ) {
		$id = filter_var( $id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		$quantity = is_int( $quantity ) || is_string( $quantity ) ? filter_var( $quantity, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) ) : false;
		if ( false === $id || false === $quantity ) {
			return false;
		}
		if ( 0 === $quantity ) {
			continue;
		}
		if ( ! isset( $allowed[ $id ] ) ) {
			return false;
		}
		$item = $allowed[ $id ];
		$child = wc_get_product( $id );
		if ( ! TInvWL_Product::can_read_product( $child ) ) {
			return false;
		}
		if ( $child instanceof WC_Product_Variation ) {
			$parent_id = (int) $child->get_parent_id();
			if ( ! $parent_id || ! TInvWL_Product::can_read_product( wc_get_product( $parent_id ) )
				|| ( ! $item instanceof WC_Product && is_callable( array( $item, 'get_product_id' ) ) && (int) $item->get_product_id() !== $parent_id ) ) {
				return false;
			}
		}
		if ( ! $item instanceof WC_Product ) {
			$child = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : false;
			if ( ! TInvWL_Product::can_read_product( $child ) || (int) $child->get_id() !== $id ) {
				return false;
			}
		}
		$components[ $id ] = array( 'product' => $child, 'quantity' => $quantity );
	}
	return $components;
}

function tinvwl_mnm_configuration_available( $available, $wl_product, $product ) {
	return $available && ( ! $product->is_type( 'mix-and-match' ) || is_array( tinvwl_mnm_saved_components( $wl_product, $product ) ) );
}
add_filter( 'tinvwl_wishlist_item_configuration_available', 'tinvwl_mnm_configuration_available', 10, 3 );
add_filter( 'tinvwl_wishlist_product_configuration_valid', 'tinvwl_mnm_configuration_available', 10, 3 );

/** Decode legacy maps only for the native request, keeping the saved configuration intact. */
function tinvwl_mnm_cart_meta( $meta, $wl_product ) {
	if ( $wl_product['data']->is_type( 'mix-and-match' ) && isset( $meta['mnm_quantity'] ) && is_string( $meta['mnm_quantity'] ) ) {
		$meta['mnm_quantity'] = json_decode( $meta['mnm_quantity'], true );
	}
	return $meta;
}
add_filter( 'tinvwl_addproduct_tocart_prepare_meta', 'tinvwl_mnm_cart_meta', 10, 2 );

if (!function_exists('tinvwl_row_woocommerce_mix_and_match_products')) {

	/**
	 * Add rows for sub product for WooCommerce Mix and Match
	 *
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 */
	function tinvwl_row_woocommerce_mix_and_match_products( $wl_product, $product ) {
		if ( is_object( $product ) && $product->is_type( 'mix-and-match' ) ) {
			$components = tinvwl_mnm_saved_components( $wl_product, $product );
			if ( null === $components ) { return; }
			if ( false === $components ) {
				echo '<tr class="tinvwl-unavailable-configuration"><td colspan="20">' . esc_html__( 'Saved configuration is unavailable.', 'ti-woocommerce-wishlist' ) . '</td></tr>';
				return;
			}
			$product_quantity = $product->is_sold_individually() ? 1 : $wl_product['quantity'];
			if ( $components ) {
				foreach ( $components as $component ) {
					$mnm_item = $component['product'];
					$item_quantity = $component['quantity'];

					$product_url = $mnm_item->get_permalink();
					$product_image = $mnm_item->get_image();
					$product_title = is_callable(array(
						$mnm_item,
						'get_name'
					)) ? $mnm_item->get_name() : $mnm_item->get_title();
					$product_price = $mnm_item->get_price_html();
					if ($mnm_item->is_visible()) {
						$product_image = sprintf('<a href="%s">%s</a>', esc_url($product_url), $product_image);
						$product_title = sprintf('<a href="%s">%s</a>', esc_url($product_url), $product_title);
					}
					$product_title .= tinv_wishlist_get_item_data($mnm_item, $wl_product);

					$availability = (array)$mnm_item->get_availability();
					if (!array_key_exists('availability', $availability)) {
						$availability['availability'] = '';
					}
					if (!array_key_exists('class', $availability)) {
						$availability['class'] = '';
					}
					$availability_html = empty($availability['availability']) ? '<p class="stock ' . esc_attr($availability['class']) . '"><span><i class="ftinvwl ftinvwl-check"></i></span><span class="tinvwl-txt">' . esc_html__('In stock', 'ti-woocommerce-wishlist') . '</span></p>' : '<p class="stock ' . esc_attr($availability['class']) . '"><span><i class="ftinvwl ftinvwl-times"></i></span><span>' . esc_html($availability['availability']) . '</span></p>';
					$row_string = '<tr>';
					$row_string .= '<td colspan="2">&nbsp;</td><td class="product-thumbnail">%1$s</td><td class="product-name">%2$s</td>';
					if (tinv_get_option('product_table', 'colm_price')) {
						$row_string .= '<td class="product-price">%3$s &times; %5$s</td>';
					}
					if (tinv_get_option('product_table', 'colm_date')) {
						$row_string .= '<td class="product-date">&nbsp;</td>';
					}
					if (tinv_get_option('product_table', 'colm_stock')) {
						$row_string .= '<td class="product-stock">%4$s</td>';
					}
					if (tinv_get_option('product_table', 'add_to_cart')) {
						$row_string .= '<td class="product-action">&nbsp;</td>';
					}
					$row_string .= '</tr>';

					echo sprintf($row_string, $product_image, $product_title, $product_price, $availability_html, $item_quantity * $product_quantity); // WPCS: xss ok.
				} // End foreach().
			} // End if().
		} // End if().
	}

	add_action('tinvwl_wishlist_row_after', 'tinvwl_row_woocommerce_mix_and_match_products', 10, 2);
} // End if().

if (!function_exists('tinvwl_item_price_woocommerce_mix_and_match_products')) {

	/**
	 * Modify price for WooCommerce Mix and Match
	 *
	 * @param string $price Returned price.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return string
	 */
	function tinvwl_item_price_woocommerce_mix_and_match_products( $price, $wl_product, $product, $raw = false ) {
		if ( ! is_object( $product ) || ! $product->is_type( 'mix-and-match' ) ) {
			return $price;
		}
		$components = tinvwl_mnm_saved_components( $wl_product, $product );
		if ( false === $components ) {
			return $raw ? NAN : esc_html__( 'Unavailable', 'ti-woocommerce-wishlist' );
		}
		if ( null === $components || ! $product->is_priced_per_product() || ( is_numeric( $price ) && ! is_finite( (float) $price ) ) ) {
			return $price;
		}
		$_price = 0;
		foreach ( $components as $component ) {
			$_price += wc_get_price_to_display( $component['product'], array( 'qty' => $component['quantity'] ) );
		}
		if ( $raw ) {
			return $_price + wc_get_price_to_display( $product );
		}
		if ( $product->is_on_sale() ) {
			$price = wc_format_sale_price( $_price + wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) ), $_price + wc_get_price_to_display( $product ) ) . $product->get_price_suffix();
		} else {
			$price = wc_price( $_price + wc_get_price_to_display( $product ) ) . $product->get_price_suffix();
		}
		$price = apply_filters( 'woocommerce_get_price_html', $price, $product );

		return $price;
	}

	add_filter( 'tinvwl_wishlist_item_price', 'tinvwl_item_price_woocommerce_mix_and_match_products', 10, 4 );
} // End if().

if (!function_exists('tinvwl_add_form_woocommerce_mix_and_match_products')) {

	/**
	 * Preserve explicit selections for validation, including malformed historical input
	 *
	 * @param array $form Post form data.
	 *
	 * @return array
	 */
	function tinvwl_add_form_woocommerce_mix_and_match_products( $form = array() ) {

		return $form;
	}

	add_filter('tinvwl_addtowishlist_add_form', 'tinvwl_add_form_woocommerce_mix_and_match_products');
} // End if().
