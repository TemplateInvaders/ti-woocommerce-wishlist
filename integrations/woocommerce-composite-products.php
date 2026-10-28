<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name WooCommerce Composite Products
 *
 * @version 11.0.7
 *
 * @slug woocommerce-composite-products
 *
 * @url https://woocommerce.com/products/composite-products/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "woocommerce-composite-products";

$name = "WooCommerce Composite Products";

$available = class_exists( 'WC_Composite_Products' );

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

if ( ! function_exists( 'tinv_wishlist_metasupport_woocommerce_composite_products' ) ) {

	/**
	 * Set description for meta WooCommerce Composite Products
	 *
	 * @param array $meta Meta array.
	 * @param integer $product_id Product ID.
	 *
	 * @return array
	 */
	function tinv_wishlist_metasupport_woocommerce_composite_products( $meta, $product_id ) {
		if ( array_key_exists( 'wccp_component_selection', $meta ) ) {
			$product = wc_get_product( $product_id );
			if ( ! $product || ! $product->is_type( 'composite' ) ) { return $meta; }
			$components = tinvwl_composite_saved_components( array( 'meta' => $meta ), $product );
			$meta = false === $components ? array( 'tinvwl_configuration' => array( 'key' => esc_html__( 'Configuration', 'ti-woocommerce-wishlist' ), 'display' => esc_html__( 'Saved configuration is unavailable.', 'ti-woocommerce-wishlist' ) ) ) : array();
		} // End if().

		return $meta;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_metasupport_woocommerce_composite_products', 10, 2 );
} // End if().

/** Normalize saved fields locally without filling missing choices from current defaults. */
function tinvwl_composite_saved_meta( $meta ) {
	$meta = is_string( $meta ) ? json_decode( $meta, true ) : $meta;
	if ( ! is_array( $meta ) ) { return false; }
	foreach ( $meta as $key => $value ) {
		if ( is_array( $value ) && array_key_exists( 'display', $value ) ) { $value = $value['display']; }
		if ( 0 === strpos( $key, 'wccp_' ) && is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) { $value = $decoded; }
		}
		$meta[ $key ] = $value;
	}
	foreach ( array( 'wccp_component_selection', 'wccp_component_quantity', 'wccp_variation_id', 'wccp_component_selection_nil' ) as $field ) {
		if ( ! array_key_exists( $field, $meta ) ) { continue; }
		$map = $meta[ $field ];
		if ( is_array( $map ) && array_key_exists( 'display', $map ) ) { $map = $map['display']; }
		$map = is_string( $map ) ? json_decode( $map, true ) : $map;
		if ( ! is_array( $map ) ) { return false; }
		$meta[ $field ] = $map;
	}
	return $meta;
}

/** Resolve every selected component before any partial price or component details are rendered. */
function tinvwl_composite_saved_components( $wl_product, $product ) {
	if ( ! TInvWL_Product::can_read_product( $product ) ) { return false; }
	$meta = tinvwl_composite_saved_meta( $wl_product['meta'] ?? array() );
	if ( false === $meta ) { return false; }
	if ( ! array_key_exists( 'wccp_component_selection', $meta ) ) {
		return array_intersect( array( 'wccp_component_quantity', 'wccp_variation_id', 'wccp_component_selection_nil' ), array_keys( $meta ) ) ? false : null;
	}
	$items = $product->get_components();
	if ( ! is_array( $items ) ) { return false; }
	foreach ( $meta['wccp_component_selection'] as $id => $selection ) {
		if ( ! isset( $items[ $id ] ) && ! in_array( $selection, array( '', 0, '0' ), true ) ) { return false; }
	}
	$components = array();
	foreach ( $items as $id => $item ) {
		$quantity = $meta['wccp_component_quantity'][ $id ] ?? $item->get_quantity( 'min' );
		$quantity = is_int( $quantity ) || is_string( $quantity ) ? filter_var( $quantity, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) ) : false;
		if ( false === $quantity ) { return false; }
		if ( isset( $meta['wccp_component_selection_nil'][ $id ] ) && ! is_scalar( $meta['wccp_component_selection_nil'][ $id ] ) ) { return false; }
		if ( isset( $meta['wccp_component_selection_nil'][ $id ] ) ) { continue; }
		$selected = $meta['wccp_component_selection'][ $id ] ?? '';
		if ( in_array( $selected, array( '', 0, '0' ), true ) && $item->is_optional() ) { continue; }
		$minimum = $item->get_quantity( 'min' );
		$maximum = $item->get_quantity( 'max' );
		if ( $quantity < $minimum || ( $maximum && $quantity > $maximum ) ) { return false; }
		if ( 0 === $quantity ) { continue; }
		if ( in_array( $selected, array( '', 0, '0' ), true ) ) {
			return false;
		}
		$selected = is_int( $selected ) || is_string( $selected ) ? filter_var( $selected, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) : false;
		$variation = $meta['wccp_variation_id'][ $id ] ?? 0;
		$variation = '' === $variation ? 0 : ( is_int( $variation ) || is_string( $variation ) ? filter_var( $variation, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) ) : false );
		if ( false === $selected || false === $variation || ! in_array( $selected, array_map( 'intval', $item->get_options() ), true ) ) { return false; }
		$base = wc_get_product( $selected );
		if ( ! TInvWL_Product::can_read_product( $base ) ) { return false; }
		$child = $variation ? wc_get_product( $variation ) : $base;
		if ( ! TInvWL_Product::can_read_product( $child ) ) { return false; }
		if ( $variation && ( ! $child instanceof WC_Product_Variation || (int) $child->get_parent_id() !== $selected ) ) { return false; }
		if ( $child instanceof WC_Product_Variation && ! TInvWL_Product::can_read_product( wc_get_product( $child->get_parent_id() ) ) ) { return false; }
		if ( ! $variation && in_array( $base->get_type(), array( 'variable', 'variable-subscription' ), true ) ) { return false; }
		$wrapper = $item->get_option( $variation ?: $selected );
		$option = $variation ? $item->get_option( $selected ) : $wrapper;
		if ( ! $wrapper || ! $option ) { return false; }
		$child = $wrapper->get_product();
		if ( ! TInvWL_Product::can_read_product( $child ) || (int) $child->get_id() !== ( $variation ?: $selected ) ) { return false; }
		if ( $child->is_type( 'bundle' ) ) {
			$nested = array();
			$prefix = 'component_' . $id . '_';
			foreach ( $meta as $key => $value ) {
				if ( 0 === strpos( $key, $prefix ) ) { $nested[ substr( $key, strlen( $prefix ) ) ] = $value; }
			}
			if ( ! function_exists( 'tinvwl_bundle_saved_components' ) || ! is_array( tinvwl_bundle_saved_components( array( 'meta' => $nested ), $child ) ) ) { return false; }
		}
		$components[ $id ] = array( 'component' => $item, 'product' => $child, 'wrapper' => $wrapper, 'option' => $option, 'quantity' => $child->is_sold_individually() ? min( 1, $quantity ) : $quantity );
	}
	return $components;
}

function tinvwl_composite_configuration_available( $available, $wl_product, $product ) {
	return $available && ( ! $product->is_type( 'composite' ) || is_array( tinvwl_composite_saved_components( $wl_product, $product ) ) );
}
/** An unconfigured catalog item has readable pricing but still requires selection before cart. */
function tinvwl_composite_configuration_readable( $readable, $wl_product, $product ) {
	return $readable && ( ! $product->is_type( 'composite' ) || false !== tinvwl_composite_saved_components( $wl_product, $product ) );
}
add_filter( 'tinvwl_wishlist_item_configuration_readable', 'tinvwl_composite_configuration_readable', 10, 3 );

add_filter( 'tinvwl_wishlist_item_configuration_available', 'tinvwl_composite_configuration_available', 10, 3 );
add_filter( 'tinvwl_wishlist_product_configuration_valid', 'tinvwl_composite_configuration_available', 10, 3 );

function tinvwl_composite_cart_meta( $meta, $wl_product ) {
	if ( $wl_product['data']->is_type( 'composite' ) ) {
		$decoded = tinvwl_composite_saved_meta( $meta );
		if ( false !== $decoded ) {
			$components = tinvwl_composite_saved_components( array( 'meta' => $decoded ), $wl_product['data'] );
			foreach ( is_array( $components ) ? $components : array() as $id => $saved ) {
				if ( ! $saved['product']->is_type( 'bundle' ) ) { continue; }
				$prefix = 'component_' . $id . '_';
				$nested = array();
				foreach ( $decoded as $key => $value ) { if ( 0 === strpos( $key, $prefix ) ) { $nested[ substr( $key, strlen( $prefix ) ) ] = $value; } }
				$parts = tinvwl_bundle_saved_components( array( 'meta' => $nested ), $saved['product'] );
				$field_prefix = apply_filters( 'woocommerce_product_bundle_field_prefix', '', $saved['product']->get_id() );
				foreach ( is_array( $parts ) ? $parts : array() as $part_id => $part ) {
					$field = $prefix . $field_prefix . 'bundle_quantity_' . $part_id;
					if ( ! array_key_exists( $field, $decoded ) ) { $decoded[ $field ] = $part['quantity']; }
				}
			}
			return $decoded;
		}
	}
	return $meta;
}
add_filter( 'tinvwl_addproduct_tocart_prepare_meta', 'tinvwl_composite_cart_meta', 10, 2 );

if ( ! function_exists( 'tinvwl_row_woocommerce_composite_products' ) ) {

	/**
	 * Add rows for sub product for WooCommerce Composite Products
	 *
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product_Composite $product Woocommerce Product.
	 */
	function tinvwl_row_woocommerce_composite_products( $wl_product, $product ) {
		if ( is_object( $product ) && $product->is_type( 'composite' ) ) {
			$components = tinvwl_composite_saved_components( $wl_product, $product );
			if ( null === $components ) { return; }
			if ( false === $components ) {
				echo '<tr class="tinvwl-unavailable-configuration"><td colspan="20">' . esc_html__( 'Saved configuration is unavailable.', 'ti-woocommerce-wishlist' ) . '</td></tr>';
				return;
			}
			$wl_product['meta'] = tinvwl_composite_saved_meta( $wl_product['meta'] );
			$product_quantity = $product->is_sold_individually() ? 1 : $wl_product['quantity'];
			foreach ( $components as $component_id => $saved ) {
				$component = $saved['component'];
				$composited_product_quantity = $saved['quantity'];
				$composited_product_wrapper = $saved['wrapper'];
				$composited_product = $saved['product'];
				$component_option = $saved['option'];

				$product_url   = $composited_product->get_permalink();
				$product_image = $composited_product->get_image();
				$product_title = is_callable( array(
					$composited_product,
					'get_name'
				) ) ? $composited_product->get_name() : $composited_product->get_title();
				$product_price = $composited_product->get_price();

				$discount = $component_option->get_discount();

				if ( $discount ) {
					$product_price = $product_price * ( 100 - $discount ) / 100;
				}
				$product_price = wc_price( $product_price );

				if ( $component_option ) {
					if ( false === $component_option->is_priced_individually() && false === $component_option->get_raw_price() > 0 ) {
						$product_price = '';
					} elseif ( false === $component_option->get_component()->is_subtotal_visible( 'cart' ) ) {
						$product_price = '';
					} elseif ( apply_filters( 'woocommerce_add_composited_cart_item_prices', true, false, false ) ) {
						if ( $product_price ) {
							$product_price = '<span class="component_table_item_price">' . $product_price . '</span>';
						}
					}
				}

				if ( $composited_product->is_visible() ) {
					$product_image = sprintf( '<a href="%s">%s</a>', esc_url( $product_url ), $product_image );
					$product_title = sprintf( '<a href="%s">%s</a>', esc_url( $product_url ), $product_title );
				}
				$product_title .= tinv_wishlist_get_item_data( $composited_product, $wl_product );

				$availability = (array) $composited_product->get_availability();
				if ( ! array_key_exists( 'availability', $availability ) ) {
					$availability['availability'] = '';
				}
				if ( ! array_key_exists( 'class', $availability ) ) {
					$availability['class'] = '';
				}
				$availability_html = empty( $availability['availability'] ) ? '<p class="stock ' . esc_attr( $availability['class'] ) . '"><span><i class="ftinvwl ftinvwl-check"></i></span><span class="tinvwl-txt">' . esc_html__( 'In stock', 'ti-woocommerce-wishlist' ) . '</span></p>' : '<p class="stock ' . esc_attr( $availability['class'] ) . '"><span><i class="ftinvwl ftinvwl-times"></i></span><span>' . esc_html( $availability['availability'] ) . '</span></p>';
				$row_string        = '<tr>';
				$row_string        .= ( ( ! is_user_logged_in() || get_current_user_id() !== $wl_product['author'] ) ? ( ( ! tinv_get_option( 'table', 'colm_checkbox' ) ) ? '' : '<td colspan="1"></td>' ) : '<td colspan="' . ( ( ! tinv_get_option( 'table', 'colm_checkbox' ) ) ? '1' : '2' ) . '"></td>' ) . '&nbsp;<td class="product-thumbnail">%2$s</td><td class="product-name">%1$s:<br/>%3$s</td>';
				if ( tinv_get_option( 'product_table', 'colm_price' ) ) {
					$row_string .= ( $product_price && ! $composited_product->is_type( 'bundle' ) ) ? '<td class="product-price">%4$s &times; %6$s</td>' : '<td class="product-price">%4$s</td>';
				}
				if ( tinv_get_option( 'product_table', 'colm_date' ) ) {
					$row_string .= '<td class="product-date">&nbsp;</td>';
				}
				if ( tinv_get_option( 'product_table', 'colm_stock' ) ) {
					$row_string .= '<td class="product-stock">%5$s</td>';
				}
				if ( tinv_get_option( 'product_table', 'colm_quantity' ) ) {
					$row_string .= '<td class="product-quantity">&nbsp;</td>';
				}
				if ( tinv_get_option( 'product_table', 'add_to_cart' ) ) {
					$row_string .= '<td class="product-action">&nbsp;</td>';
				}
				$row_string .= '</tr>';

				if ( $composited_product->is_type( 'bundle' ) ) {
					$product_price = $availability_html = $product_title = '';
				}

				echo sprintf( $row_string, is_callable( array(
					$component,
					'get_name'
				) ) ? $component->get_name() : $component->get_title(), $product_image, $product_title, $product_price, $availability_html, $composited_product_quantity * $product_quantity ); // WPCS: xss ok.

				if ( $composited_product->is_type( 'bundle' ) ) {

					$wl_product_bundle               = $wl_product;
					$wl_product_bundle['product_id'] = $composited_product->get_id();

					$component_meta = array();

					foreach ( $wl_product['meta'] as $key => $value ) {
						if ( substr( $key, 0, strlen( 'component_' . $component_id ) ) === 'component_' . $component_id ) {

							$component_meta[ substr( $key, strlen( 'component_' . $component_id . '_' ), strlen( $key ) ) ] = $value;
						}
					}

					$wl_product_bundle['meta'] = $component_meta;

					tinvwl_row_woocommerce_product_bundles( $wl_product_bundle, $composited_product, $composited_product_wrapper->get_discount(), $composited_product_quantity * $product_quantity );
				}
			} // End foreach().
		} // End if().
	}

	add_action( 'tinvwl_wishlist_row_after', 'tinvwl_row_woocommerce_composite_products', 10, 2 );
} // End if().

if ( ! function_exists( 'tinvwl_composited_bundle_price' ) ) {

	/**
	 * Price the exact saved selection using native cart pricing on disposable object copies.
	 *
	 * @param array $meta Saved Bundle fields, without the Composite component prefix.
	 * @param WC_Product_Bundle $bundle Selected Bundle product.
	 * @param WC_CP_Product $component_option Native selected component option.
	 * @return float|false Configured unit price, or false for legacy vendor APIs.
	 */
	function tinvwl_composited_bundle_price( $meta, $bundle, $component_option ) {
		// Older vendors do not expose object-local cart contexts; retain their existing adapter below.
		if ( ! isset( WC_CP()->product_data, WC_PB()->product_data )
			|| ! is_callable( array( WC_CP()->product_data, 'set' ) ) || ! is_callable( array( WC_CP()->product_data, 'delete' ) )
			|| ! is_callable( array( WC_PB()->product_data, 'set' ) ) || ! is_callable( array( WC_PB()->product_data, 'delete' ) )
			|| ! is_callable( array( 'WC_CP_Products', 'get_composited_cart_item_discount_method' ) )
			|| ! is_callable( array( 'WC_PB_Product_Prices', 'get_bundled_cart_item_discount_method' ) )
			|| ! is_callable( array( $bundle, 'set_object_context' ) ) ) {
			return false;
		}

		$discount_method = WC_CP_Products::get_composited_cart_item_discount_method();
		$sale_contexts = array();
		if ( 'props' === $discount_method ) {
			if ( ! is_callable( array( $component_option, 'get_product_id' ) )
				|| ! is_callable( array( $component_option, 'get_component_id' ) )
				|| ! is_callable( array( $component_option, 'get_composite' ) ) ) {
				return false;
			}
			// The native sale-state getter nests CP/PB catalog scopes without exception guards.
			// Read their depths only: a top-object comparison cannot distinguish repeated pushes
			// of an existing caller's object. Unsupported vendor internals use the legacy adapter.
			foreach ( array( 'WC_CP_Products' => 'filtered_component_option_pre', 'WC_PB_Product_Prices' => 'bundled_item_pre' ) as $class => $property ) {
				if ( ! property_exists( $class, $property ) ) {
					return false;
				}
				try {
					$reflection = new ReflectionProperty( $class, $property );
					$reflection->setAccessible( true );
					$stack = $reflection->getValue();
				} catch ( Throwable $error ) {
					return false;
				}
				if ( null !== $stack && ! is_array( $stack ) ) {
					return false;
				}
				$sale_contexts[ $class ] = $reflection;
			}
		}

		$products = array();
		$bundle_product = clone $bundle;
		$bundle_product->set_object_context( 'cart' );
		$products[] = $bundle_product;
		try {
			if ( 'props' === $discount_method ) {
				$bundle_product->set_price( $component_option->get_raw_price( $bundle_product, 'cart' ) );
				$discount = $component_option->get_discount();
				if ( ! empty( $discount ) || $bundle->is_on_sale() ) {
					$depths = array();
					foreach ( $sale_contexts as $class => $reflection ) {
						$stack = $reflection->getValue();
						if ( null !== $stack && ! is_array( $stack ) ) {
							return false;
						}
						$depths[ $class ] = is_array( $stack ) ? count( $stack ) : 0;
					}
					$unwind = static function ( $class, $callback ) use ( $sale_contexts, $depths ) {
						$stack = $sale_contexts[ $class ]->getValue();
						while ( is_array( $stack ) && count( $stack ) > $depths[ $class ] ) {
							call_user_func( $callback );
							$stack = $sale_contexts[ $class ]->getValue();
						}
					};
					try {
						// Match native sale properties only; the configured unit still uses the saved stamp.
						// A fresh native wrapper keeps range-calculation runtime state off the caller's product.
						$sale_option = new WC_CP_Product( $component_option->get_product_id(), $component_option->get_component_id(), $component_option->get_composite() );
						$bundle_product->set_sale_price( $sale_option->get_price( 'edit' ) );
					} finally {
						try {
							$unwind( 'WC_PB_Product_Prices', array( 'WC_PB_Product_Prices', 'remove_price_filters' ) );
						} finally {
							$unwind( 'WC_CP_Products', array( 'WC_CP_Products', 'remove_filters' ) );
						}
					}
				}
			} else {
				WC_CP()->product_data->set( $bundle_product, 'composited_cart_item', $component_option );
			}
			$price = (float) $bundle_product->get_price();
			$prefix = apply_filters( 'woocommerce_product_bundle_field_prefix', '', $bundle->get_id() );
			foreach ( $bundle->get_bundled_items() as $item_id => $item ) {
				$quantity = isset( $meta[ $prefix . 'bundle_quantity_' . $item_id ] ) ? absint( $meta[ $prefix . 'bundle_quantity_' . $item_id ] ) : $item->get_quantity( 'default' );
				if ( ! $quantity || ! $item->is_priced_individually()
					|| ( $item->is_optional() && ! array_key_exists( $prefix . 'bundle_selected_optional_' . $item_id, $meta ) ) ) {
					continue;
				}
				$variation_id = absint( $meta[ $prefix . 'bundle_variation_id_' . $item_id ] ?? 0 );
				$selected = $variation_id ? wc_get_product( $variation_id ) : $item->product;
				if ( ! $selected ) {
					continue;
				}
				$selected = clone $selected;
				$products[] = $selected;
				$cart_item = clone $item;
				$cart_item->composited_cart_item = $component_option;
				if ( 'props' === WC_PB_Product_Prices::get_bundled_cart_item_discount_method() ) {
					$selected->set_price( $cart_item->get_raw_price( $selected, 'cart' ) );
					if ( $cart_item->is_on_sale( 'cart' ) ) {
						$selected->set_sale_price( $selected->get_price( 'edit' ) );
					}
				} else {
					WC_PB()->product_data->set( $selected, 'bundled_cart_item', $cart_item );
				}
				$price += (float) $selected->get_price() * $quantity;
			}
			return $price;
		} finally {
			// Vendor sidecars use object hashes, which PHP can reuse after a clone is released.
			foreach ( $products as $priced_product ) {
				WC_CP()->product_data->delete( $priced_product, 'composited_cart_item' );
				WC_PB()->product_data->delete( $priced_product, 'bundled_cart_item' );
				WC_PB()->product_data->delete( $priced_product, 'bundled_item_price' );
			}
		}
	}
}

if ( ! function_exists( 'tinvwl_item_price_woocommerce_composite_products' ) ) {

	/**
	 * Modify price for WooCommerce Composite Products
	 *
	 * @param string $price Returned price.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return string
	 */
	function tinvwl_item_price_woocommerce_composite_products( $price, $wl_product, $product, $raw = false ) {
		if ( is_object( $product ) && $product->is_type( 'composite' ) ) {
			$components = tinvwl_composite_saved_components( $wl_product, $product );
			if ( false === $components ) { return $raw ? NAN : esc_html__( 'Unavailable', 'ti-woocommerce-wishlist' ); }
			if ( null === $components || ( is_numeric( $price ) && ! is_finite( (float) $price ) ) ) { return $price; }
			$wl_product['meta'] = tinvwl_composite_saved_meta( $wl_product['meta'] );
			$_price = $product->get_price();
			$regular_price = $product->get_regular_price();
			foreach ( $components as $component_id => $saved ) {
				$component = $saved['component'];
				$composited_product_quantity = $saved['quantity'];
				$composited_product_wrapper = $saved['wrapper'];
				$composited_product = $saved['product'];
				if ( $component->is_priced_individually() ) {

					if ( $composited_product->is_type( 'bundle' ) ) {

						$wl_product_bundle               = $wl_product;
						$wl_product_bundle['product_id'] = $composited_product->get_id();

						$component_meta = array();

						foreach ( $wl_product['meta'] as $key => $value ) {
							if ( substr( $key, 0, strlen( 'component_' . $component_id ) ) === 'component_' . $component_id ) {

								$component_meta[ substr( $key, strlen( 'component_' . $component_id . '_' ), strlen( $key ) ) ] = $value;
							}
						}

						$wl_product_bundle['meta'] = $component_meta;

						$bundle_price = tinvwl_composited_bundle_price( $component_meta, $composited_product, $composited_product_wrapper );
						if ( false === $bundle_price ) {
							$bundle_price = tinvwl_item_price_woocommerce_product_bundles( 0, $wl_product_bundle, $composited_product, true );
							if ( $discount = $composited_product_wrapper->get_discount() ) {
								$bundle_price = empty( $bundle_price ) ? $bundle_price : round( (double) $bundle_price * ( 100 - $discount ) / 100, wc_cp_price_num_decimals( 'extended' ) );
							}
						}
						$regular_price += $bundle_price * $composited_product_quantity;
						$_price += $bundle_price * $composited_product_quantity;

						continue;
					}

					$_price        += $composited_product_wrapper->get_price() * $composited_product_quantity;
					$regular_price += $composited_product_wrapper->get_regular_price() * $composited_product_quantity;
				}
			}

			if ( $raw ) {
				return $_price;
			}

			$price = is_finite( (float) $_price ) ? wc_price( $_price ) . $product->get_price_suffix() : esc_html__( 'Unavailable', 'ti-woocommerce-wishlist' );

		}

		return $price;
	}

	add_filter( 'tinvwl_wishlist_item_price', 'tinvwl_item_price_woocommerce_composite_products', 10, 4 );
} // End if().

if ( ! function_exists( 'tinv_wishlist_metaprepare_woocommerce_composite_products' ) ) {

	/**
	 * Prepare save meta for WooCommerce Composite Products
	 *
	 * @param array $meta Meta array.
	 *
	 * @return array
	 */
	function tinv_wishlist_metaprepare_woocommerce_composite_products( $meta, $product_id, $variation_id ) {

		$product = wc_get_product( $product_id );

		if ( $product instanceof WC_Product_Composite ) {

			if ( method_exists( 'WC_CP_Core_Compatibility', 'save_rest_request' ) ) {
				WC_CP_Core_Compatibility::save_rest_request( false, false, false );
			}

			// Preserve the submitted selection, including missing or obsolete choices for reconfiguration.
			foreach ( $meta as $key => $value ) {
				if ( 0 === strpos( $key, 'wccp_' ) && is_string( $value ) ) {
					$decoded = json_decode( $value, true );
					if ( is_array( $decoded ) ) { $meta[ $key ] = $decoded; }
				}
			}
		}

		return $meta;
	}

	add_filter( 'tinvwl_product_prepare_meta', 'tinv_wishlist_metaprepare_woocommerce_composite_products', 10, 3 );
}

function tinv_add_to_wishlist_woocommerce_composite_products() {

	wp_add_inline_script( 'tinvwl', "
		jQuery(document).ready(function($){
			  $(document).on('tinvwl_wishlist_button_clicked', function (e, el, data) {
			        var button = $(el), composite_form =[];

			       	$( 'form.cart[method=post][data-product_id=\"' + button.attr( 'data-tinv-wl-product' ) + '\"], form.vtajaxform[method=post][data-product_id=\"' + button.attr( 'data-tinv-wl-product' ) + '\"]' ).each( function() {
							composite_form.push( $( this ) );
					});

					if ( ! composite_form.length ) {
						button.closest( 'form.cart[method=post], form.vtajaxform[method=post]' ).each( function() {
							composite_form.push( $( this ) );
						});
					if ( ! composite_form.length ) {
						composite_form.push( $( 'form.cart[method=post]' ) );
					}

					$.each( composite_form, function( index, element ) {
						$( element ).find( 'div.composite_component' ).not(':visible').each( function() {
						var id = $(this).attr('data-item_id');
						if (!data.form.hasOwnProperty('wccp_component_selection_nil')) {
							data.form.wccp_component_selection_nil = {};
						}
						data.form.wccp_component_selection_nil[id] = '1';
						});
					});
				}
			  });
        });
        " );
}

add_action( 'wp_enqueue_scripts', 'tinv_add_to_wishlist_woocommerce_composite_products', 100, 1 );

/**
 * Filters the product being added to the cart in the Wishlist plugin for WooCommerce.
 *
 * @param array $wl_product The wishlist product data.
 *
 * @return array The modified wishlist product data.
 */
function tinvwl_addproduct_tocart_woocommerce_composite_products( $wl_product ) {
	$product = wc_get_product( $wl_product['product_id'] );

	if ( $product instanceof WC_Product_Composite ) {
		if ( method_exists( 'WC_CP_Core_Compatibility', 'save_rest_request' ) ) {
			WC_CP_Core_Compatibility::save_rest_request( false, false, false );
		}
	}

	return $wl_product;
}

add_filter( 'tinvwl_addproduct_tocart', 'tinvwl_addproduct_tocart_woocommerce_composite_products' );

/**
 * Callback function for the 'tinvwl_before_unprepare_post' action hook.
 * Performs additional checks for WooCommerce Composite Products.
 *
 * @param array $wl_product The wishlist product data.
 */
function tinvwl_before_unprepare_post_woocommerce_composite_products( $wl_product ) {
	$product = wc_get_product( $wl_product['product_id'] );

	if ( $product instanceof WC_Product_Composite && ! isset( $wl_product['meta']['action'] ) ) {
		if ( function_exists( 'wc_clear_notices' ) ) {
			wc_clear_notices();
		}
	}
}

add_action( 'tinvwl_before_unprepare_post', 'tinvwl_before_unprepare_post_woocommerce_composite_products', 10, 1 );

/**
 * Filter for adding an item to cart in the wishlist.
 *
 * @param bool $allow Whether to allow adding the item to cart.
 * @param array $wl_product Wishlist item being added to cart.
 * @param WC_Product $product Product being added to cart.
 *
 * @return bool Whether to allow adding the item to cart.
 */
add_filter( 'tinvwl_wishlist_item_action_add_to_cart', 'tinvwl_wishlist_item_action_add_to_cart_woocommerce_composite_products', 10, 3 );

/**
 * Prevents adding composite products to the cart from the wishlist.
 *
 * @param bool $allow Whether to allow adding the item to cart.
 * @param array $wl_product Wishlist item being added to cart.
 * @param WC_Product $product Product being added to cart.
 *
 * @return bool Whether to allow adding the item to cart.
 */
function tinvwl_wishlist_item_action_add_to_cart_woocommerce_composite_products( $allow, $wl_product, $product ) {
	if ( $product instanceof WC_Product_Composite ) {
		$allow = ! tinvwl_meta_validate_cart_add( true, $product, '', $wl_product );
	}

	return $allow;
}
