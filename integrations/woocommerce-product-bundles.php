<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name WooCommerce Product Bundles
 *
 * @version 8.5.9
 *
 * @slug woocommerce-product-bundles
 *
 * @url https://woocommerce.com/products/product-bundles/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "woocommerce-product-bundles";

$name = "WooCommerce Product Bundles";

$available = class_exists( 'WC_Bundles' );

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

if ( ! function_exists( 'tinv_wishlist_metasupport_woocommerce_product_bundles' ) ) {

	/**
	 * Set description for meta WooCommerce Product Bundles
	 *
	 * @param array $meta Meta array.
	 * @param integer $product_id Product ID.
	 *
	 * @return array
	 */
	function tinv_wishlist_metasupport_woocommerce_product_bundles( $meta, $product_id ) {
		$product = wc_get_product( $product_id );

		if ( is_object( $product ) && $product->is_type( 'bundle' ) && empty( $meta['bkap_price_charged'] ) ) {
			$meta = array();
		}

		return $meta;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_metasupport_woocommerce_product_bundles', 10, 2 );
} // End if().

/** Return null for an unconfigured catalog bundle, false for invalid saved choices, or resolved components. */
function tinvwl_bundle_saved_components( $wl_product, $product ) {
	if ( ! TInvWL_Product::can_read_product( $product ) ) { return false; }
	$meta = $wl_product['meta'] ?? array();
	$meta = is_string( $meta ) ? json_decode( $meta, true ) : $meta;
	if ( ! is_array( $meta ) ) { return false; }
	$prefix = apply_filters( 'woocommerce_product_bundle_field_prefix', '', $product->get_id() );
	$items = $product->get_bundled_items();
	if ( ! is_array( $items ) ) { return false; }
	$configured = false;
	foreach ( $meta as $key => $value ) {
		if ( preg_match( '/^' . preg_quote( $prefix, '/' ) . 'bundle_(?:variation_id|quantity|selected_optional)_(\d+)$/D', $key ) ) { $configured = true; }
		if ( preg_match( '/^' . preg_quote( $prefix, '/' ) . 'bundle_(?:variation_id|quantity|selected_optional)_(\d+)$/D', $key, $match ) && ! isset( $items[ $match[1] ] ) ) {
			$quantity = $meta[ $prefix . 'bundle_quantity_' . $match[1] ] ?? 1;
			if ( ! is_numeric( $quantity ) || 0.0 !== (float) $quantity ) { return false; }
		}
	}
	$components = array();
	$requires_selection = false;
	foreach ( $items as $id => $item ) {
		$quantity = $meta[ $prefix . 'bundle_quantity_' . $id ] ?? $item->get_quantity( 'default' );
		if ( ! is_numeric( $quantity ) || ! is_finite( (float) $quantity ) || (float) $quantity < 0 ) { return false; }
		if ( $item->is_optional() && ! array_key_exists( $prefix . 'bundle_selected_optional_' . $id, $meta ) ) { continue; }
		$minimum = $item->get_quantity( 'min' );
		$maximum = $item->get_quantity( 'max' );
		if ( (float) $quantity < $minimum || ( $maximum && (float) $quantity > $maximum ) ) { return false; }
		if ( 0.0 === (float) $quantity ) { continue; }
		$base_id = filter_var( $item->get_product_id(), FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		if ( false === $base_id ) { return false; }
		$base = wc_get_product( $base_id );
		if ( ! TInvWL_Product::can_read_product( $base ) ) { return false; }
		$selected = $meta[ $prefix . 'bundle_variation_id_' . $id ] ?? 0;
		$selected = is_int( $selected ) || is_string( $selected ) ? filter_var( $selected, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) ) : false;
		if ( false === $selected ) { return false; }
		$component = $selected ? wc_get_product( (int) $selected ) : $base;
		if ( ! TInvWL_Product::can_read_product( $component ) ) { return false; }
		if ( $selected && ( ! $component instanceof WC_Product_Variation || (int) $component->get_parent_id() !== (int) $item->get_product_id() ) ) { return false; }
		if ( $component instanceof WC_Product_Variation && ! TInvWL_Product::can_read_product( wc_get_product( $component->get_parent_id() ) ) ) { return false; }
		if ( $selected && is_callable( array( $item, 'has_filtered_variations' ) ) && $item->has_filtered_variations()
			&& ! in_array( $selected, array_map( 'intval', (array) $item->get_filtered_variations() ), true ) ) { return false; }
		if ( ! $selected && in_array( $base->get_type(), array( 'variable', 'variable-subscription' ), true ) ) {
			if ( $configured ) { return false; }
			$requires_selection = true;
			continue;
		}
		$components[ $id ] = array( 'item' => $item, 'product' => $component, 'quantity' => (float) $quantity );
	}
	return $requires_selection ? null : $components;
}

function tinvwl_bundle_configuration_available( $available, $wl_product, $product ) {
	return $available && ( ! $product->is_type( 'bundle' ) || is_array( tinvwl_bundle_saved_components( $wl_product, $product ) ) );
}
add_filter( 'tinvwl_wishlist_item_configuration_available', 'tinvwl_bundle_configuration_available', 10, 3 );
add_filter( 'tinvwl_wishlist_product_configuration_valid', 'tinvwl_bundle_configuration_available', 10, 3 );
function tinvwl_bundle_configuration_readable( $readable, $wl_product, $product ) {
	return $readable && ( ! $product->is_type( 'bundle' ) || false !== tinvwl_bundle_saved_components( $wl_product, $product ) );
}
add_filter( 'tinvwl_wishlist_item_configuration_readable', 'tinvwl_bundle_configuration_readable', 10, 3 );

if ( ! function_exists( 'tinvwl_row_woocommerce_product_bundles' ) ) {

	/**
	 * Add rows for sub product for WooCommerce Product Bundles
	 *
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 * @param int $discount_extra possible discount on bundle row.
	 */
	function tinvwl_row_woocommerce_product_bundles( $wl_product, $product, $discount_extra = 0 ) {
		if ( is_object( $product ) && $product->is_type( 'bundle' ) ) {

			$components = tinvwl_bundle_saved_components( $wl_product, $product );
			if ( false === $components ) {
				echo '<tr class="tinvwl-unavailable-configuration"><td colspan="20">' . esc_html__( 'Saved configuration is unavailable.', 'ti-woocommerce-wishlist' ) . '</td></tr>';
				return;
			}
			if ( $components ) {
				foreach ( $components as $component ) {
					$bundled_item = clone $component['item'];
					$bundled_item->product = $component['product'];
					$bundled_product_qty = $component['quantity'];
					if ( ! $bundled_item->is_visible( 'cart' ) ) { continue; }

					$product_url   = $bundled_item->product->get_permalink();
					$product_image = $bundled_item->product->get_image();
					$product_title = $bundled_item->has_title_override() ? is_callable( array(
						$bundled_item,
						'get_name'
					) ) ? $bundled_item->get_name() : $bundled_item->get_title() : $bundled_item->get_raw_title();

					$product_price     = $bundled_item->product->get_price_html();
					$product_price_raw = $bundled_item->is_discount_allowed_on_sale_price() ? $bundled_item->product->get_price() : $bundled_item->product->get_regular_price();
					$discount          = (float) $bundled_item->get_discount();
					// Match Product Bundles' composition of item and component discounts.
					$discount          = $discount + (float) $discount_extra - $discount * (float) $discount_extra / 100;
					$product_price     = empty( $discount ) ? $product_price : wc_price( WC_PB_Product_Prices::get_discounted_price( $product_price_raw, $discount ) );

					if ( $bundled_item->product->is_visible() ) {
						$product_image = sprintf( '<a href="%s">%s</a>', esc_url( $product_url ), $product_image );
						$product_title = sprintf( '<a href="%s">%s</a>', esc_url( $product_url ), $product_title );
					}
					$product_title .= tinv_wishlist_get_item_data( $bundled_item->product, $wl_product );

					$availability = (array) $bundled_item->product->get_availability();
					if ( ! array_key_exists( 'availability', $availability ) ) {
						$availability['availability'] = '';
					}
					if ( ! array_key_exists( 'class', $availability ) ) {
						$availability['class'] = '';
					}
					$availability_html = empty( $availability['availability'] ) ? '<p class="stock ' . esc_attr( $availability['class'] ) . '"><span><i class="ftinvwl ftinvwl-check"></i></span><span class="tinvwl-txt">' . esc_html__( 'In stock', 'ti-woocommerce-wishlist' ) . '</span></p>' : '<p class="stock ' . esc_attr( $availability['class'] ) . '"><span><i class="ftinvwl ftinvwl-times"></i></span><span>' . esc_html( $availability['availability'] ) . '</span></p>';
					$row_string        = '<tr>';
					$row_string        .= '<td colspan="2">&nbsp;</td><td class="product-thumbnail">%1$s</td><td class="product-name">%2$s</td>';
					if ( tinv_get_option( 'product_table', 'colm_price' ) && $bundled_item->is_priced_individually() ) {
						$row_string .= '<td class="product-price">%3$s &times; %5$s</td>';
					} elseif ( ! $bundled_item->is_priced_individually() ) {
						$row_string .= '<td class="product-price"></td>';
					}
					if ( tinv_get_option( 'product_table', 'colm_date' ) ) {
						$row_string .= '<td class="product-date">&nbsp;</td>';
					}
					if ( tinv_get_option( 'product_table', 'colm_stock' ) ) {
						$row_string .= '<td class="product-stock">%4$s</td>';
					}
					if ( tinv_get_option( 'product_table', 'colm_quantity' ) ) {
						$row_string .= '<td class="product-quantity">&nbsp;</td>';
					}
					if ( tinv_get_option( 'product_table', 'add_to_cart' ) ) {
						$row_string .= '<td class="product-action">&nbsp;</td>';
					}
					$row_string .= '</tr>';

					echo sprintf( $row_string, $product_image, $product_title, $product_price, $availability_html, $bundled_product_qty ); // WPCS: xss ok.
				} // End foreach().
			} // End if().
		} // End if().
	}

	add_action( 'tinvwl_wishlist_row_after', 'tinvwl_row_woocommerce_product_bundles', 10, 2 );
} // End if().

if ( ! function_exists( 'tinvwl_item_price_woocommerce_product_bundles' ) ) {

	/**
	 * Modify price for WooCommerce Product Bundles
	 *
	 * @param string $price Returned price.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return string
	 */
	function tinvwl_item_price_woocommerce_product_bundles( $price, $wl_product, $product, $raw = false ) {
		if ( is_object( $product ) && $product->is_type( 'bundle' ) ) {

			$components = tinvwl_bundle_saved_components( $wl_product, $product );
			if ( false === $components || ! apply_filters( 'tinvwl_wishlist_item_configuration_readable', true, $wl_product, $product ) ) { return $raw ? NAN : __( 'Unavailable', 'ti-woocommerce-wishlist' ); }
			if ( null === $components ) { return $price; }
			$bundle_price = $product->get_price();
			if ( is_array( $components ) ) {
				$bundled_items_price = 0.0;
				foreach ( $components as $component ) {
					$bundled_item = $component['item'];
					$_bundled_product = $component['product'];
					$bundled_product_qty = $component['quantity'];

					if ( $bundled_item->is_priced_individually() ) {
						$product_price = $bundled_item->get_raw_price( $_bundled_product );

						$bundled_item_price = (double) $product_price * (int) $bundled_product_qty;

						$bundled_items_price += (double) $bundled_item_price;
					}

				} // End foreach().
				$price = (double) $bundle_price + $bundled_items_price;

				if ( $raw ) {
					return $price;
				}

				if ( ! $raw ) {
					$price = apply_filters( 'woocommerce_get_price_html', wc_price( $price ), $product );
				}
			} // End if().
		} // End if().

		return $price;
	}

	add_filter( 'tinvwl_wishlist_item_price', 'tinvwl_item_price_woocommerce_product_bundles', 10, 4 );
} // End if().
