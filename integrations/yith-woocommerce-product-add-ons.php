<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name YITH WooCommerce Product Add-Ons
 *
 * @version 4.34.1
 *
 * @slug yith-woocommerce-product-add-ons
 *
 * @url https://wordpress.org/plugins/yith-woocommerce-product-add-ons/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "yith-woocommerce-product-add-ons";

$name = "YITH WooCommerce Product Add-Ons";

$available = class_exists( 'YITH_WAPO' );

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

/** Quantity maps may be stored as JSON or wrapped for metadata display. */
function tinvwl_yith_quantity_map( $map ) {
	if ( is_array( $map ) && array_key_exists( 'display', $map ) ) { $map = $map['display']; }
	$map = is_string( $map ) ? json_decode( $map, true ) : $map;
	return is_array( $map ) ? $map : false;
}

/** Resolve native POST/cart quantity maps before the legacy encoded quantity. */
function tinvwl_yith_option_quantity( $meta, $key, $value ) {
	$meta = is_string( $meta ) ? json_decode( $meta, true ) : $meta;
	if ( ! is_array( $meta ) ) { return false; }
	$parts = explode( '-', (string) $value );
	if ( count( $parts ) < 2 || count( $parts ) > 3 ) { return false; }
	$quantity = $parts[2] ?? 1;
	foreach ( array( 'yith_wapo_product_qty', 'yith_wapo_qty_options' ) as $field ) {
		if ( ! array_key_exists( $field, $meta ) ) { continue; }
		$map = tinvwl_yith_quantity_map( $meta[ $field ] );
		if ( false === $map ) { return false; }
		if ( array_key_exists( $key, $map ) ) { $quantity = $map[ $key ]; break; }
	}
	return is_numeric( $quantity ) && is_finite( (float) $quantity ) && (float) $quantity >= 0 ? (float) $quantity : false;
}

/** Match the native per-product option calculation, including its quantity and filters. */
function tinvwl_yith_product_option_price( $addon_id, $option_id, $key, $value, $info, $quantity, $base_price ) {
	$product = tinvwl_yith_selected_product( $value, $info );
	if ( ! $product ) { return NAN; }
	$cart = function_exists( 'YITH_WAPO_Cart' ) ? YITH_WAPO_Cart() : null;
	if ( is_callable( array( $cart, 'calculate_addon_prices_on_cart' ) ) ) {
		$prices = $cart->calculate_addon_prices_on_cart( $addon_id, $option_id, $key, $value, array( 'yith_wapo_qty_options' => array( $key => $quantity ) ), $base_price, false );
		$price = isset( $prices['price_sale'] ) && '' !== $prices['price_sale'] && is_numeric( $prices['price_sale'] ) && (float) $prices['price_sale'] >= 0 ? $prices['price_sale'] : ( $prices['price'] ?? null );
		return is_numeric( $price ) && is_finite( (float) $price ) ? (float) $price : NAN;
	}
	$unit = (float) $product->get_price();
	if ( 'discount' === $info['price_method'] ) {
		$discount = (float) $info['price'];
		$unit -= 'percentage' === $info['price_type'] ? $unit * $discount / 100 : $discount;
	}
	return $unit * $quantity;
}

/** Accept stored JSON, cart-decoded options and the metadata display wrapper. */
function tinvwl_yith_saved_options( $meta ) {
	$meta = is_string( $meta ) ? json_decode( $meta, true ) : $meta;
	if ( ! is_array( $meta ) ) { return false; }
	$options = array_key_exists( 'yith_wapo', $meta ) ? $meta['yith_wapo'] : array();
	if ( is_array( $options ) && array_key_exists( 'display', $options ) ) { $options = $options['display']; }
	if ( '' === $options ) { return array(); }
	$options = is_string( $options ) ? json_decode( $options, true ) : $options;
	if ( ! is_array( $options ) ) { return false; }
	foreach ( $options as $index => $option ) {
		if ( ! is_array( $option ) ) { return false; }
		foreach ( $option as $key => $value ) {
			if ( ! $key ) { unset( $options[ $index ][ $key ] ); continue; }
			$parts = explode( '-', $key );
			$addon_id = $parts[0];
			$option_id = $parts[1] ?? $value;
			if ( ! isset( $parts[1] ) && ! is_scalar( $value ) ) { return false; }
			$info = yith_wapo_get_option_info( $addon_id, $option_id, false );
			if ( ! is_array( $info ) || empty( $info['addon_type'] ) ) { return false; }
			$addon = $info['addon'] ?? new YITH_WAPO_Addon( $addon_id );
			if ( ! is_object( $addon ) || ! is_callable( array( $addon, 'get_option' ) ) ) { return false; }
			$required = 'yes' === $addon->get_option( 'required', $option_id );
			if ( 'file' === $info['addon_type'] && is_array( $value ) ) {
				foreach ( $value as $file ) { if ( ! is_string( $file ) ) { return false; } }
				$value = array_values( array_filter( $value, static function ( $file ) { return '' !== $file; } ) );
				if ( ! $value ) { if ( $required ) { return false; } unset( $options[ $index ][ $key ] ); continue; }
				$options[ $index ][ $key ] = $value;
				continue;
			}
			if ( ! is_scalar( $value ) ) { return false; }
			if ( '' === (string) $value ) {
				if ( $required ) { return false; }
				unset( $options[ $index ][ $key ] );
				continue;
			}
			if ( 'product' === $info['addon_type'] ) {
				$quantity = tinvwl_yith_option_quantity( $meta, $key, $value );
				if ( false === $quantity ) { return false; }
				if ( 0.0 === $quantity ) {
					if ( $required ) { return false; }
					unset( $options[ $index ][ $key ] );
					continue;
				}
				if ( ! tinvwl_yith_selected_product( $value, $info ) ) { return false; }
			}
		}
	}
	return $options;
}
function tinvwl_yith_selected_product( $value, $info ) {
	$parts = explode( '-', (string) $value );
	$id = isset( $parts[1] ) ? filter_var( $parts[1], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) : false;
	if ( false === $id ) { return false; }
	$product = wc_get_product( $id );
	if ( ! TInvWL_Product::can_read_product( $product ) ) { return false; }
	$expected = 0;
	if ( array_key_exists( 'product_id', $info ) ) {
		$expected = filter_var( $info['product_id'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		if ( false === $expected ) { return false; }
	}
	if ( $product instanceof WC_Product_Variation ) {
		if ( ! TInvWL_Product::can_read_product( wc_get_product( $product->get_parent_id() ) ) || ( $expected && $expected !== $id && $expected !== (int) $product->get_parent_id() ) ) { return false; }
	} elseif ( $expected && $expected !== $id ) { return false; }
	return $product;
}
function tinvwl_yith_configuration_available( $available, $wl_product, $product ) {
	return $available && TInvWL_Product::can_read_product( $product ) && false !== tinvwl_yith_saved_options( $wl_product['meta'] ?? array() );
}
add_filter( 'tinvwl_wishlist_item_configuration_available', 'tinvwl_yith_configuration_available', 10, 3 );
add_filter( 'tinvwl_wishlist_item_configuration_readable', 'tinvwl_yith_configuration_available', 10, 3 );


/** One raw-price convention for metadata and totals, with a separate free quota per addon. */
function tinvwl_yith_priced_options( $meta, $product, $options ) {
	$priced = $free = array();
	$base = (float) $product->get_price();
	$cart = function_exists( 'YITH_WAPO_Cart' ) ? YITH_WAPO_Cart() : null;
	foreach ( $options as $option ) {
		foreach ( $option as $key => $value ) {
			$ids = explode( '-', (string) $key );
			$addon_id = $ids[0]; $option_id = $ids[1] ?? $value;
			$info = yith_wapo_get_option_info( $addon_id, $option_id, false );
			if ( ! is_array( $info ) || empty( $info['addon_type'] ) ) { return false; }
			$quantity = 1;
			if ( 'product' === $info['addon_type'] ) {
				$quantity = tinvwl_yith_option_quantity( $meta, $key, $value );
			} else {
				foreach ( array( 'yith_wapo_product_qty', 'yith_wapo_qty_options' ) as $field ) {
					if ( ! isset( $meta[ $field ] ) ) { continue; }
					$map = tinvwl_yith_quantity_map( $meta[ $field ] );
					if ( false === $map ) { return false; }
					if ( array_key_exists( $key, $map ) ) { $quantity = $map[ $key ]; break; }
				}
			}
			if ( ! is_numeric( $quantity ) || ! is_finite( (float) $quantity ) || $quantity < 0 ) { return false; }
			$input = is_array( $value ) ? ( reset( $value ) ?: '' ) : (string) $value;
			if ( is_callable( array( $cart, 'calculate_addon_prices_on_cart' ) ) ) {
				$amounts = $cart->calculate_addon_prices_on_cart( $addon_id, $option_id, $key, $input, array( 'yith_wapo_qty_options' => array( $key => $quantity ) ), $base, false );
			} else {
				$method = $info['price_method'] ?? 'free';
				if ( 'product' === $info['addon_type'] && in_array( $method, array( 'product', 'discount' ), true ) ) {
					$amounts = array( 'price' => tinvwl_yith_product_option_price( $addon_id, $option_id, $key, $input, $info, $quantity, $base ), 'price_sale' => '' );
				} else {
					$calculate = static function ( $price ) use ( $info, $method, $base, $input, $quantity ) {
						if ( 'free' === $method ) { return 0; }
						if ( 'value_x_product' === $method ) { return is_numeric( $input ) ? $base * $input * $quantity : NAN; }
						if ( '' === $price ) { return ''; }
						if ( ! is_numeric( $price ) ) { return NAN; }
						$price = (float) $price;
						if ( $price > 0 ) {
							$type = $info['price_type'] ?? 'fixed';
							if ( 'percentage' === $type ) { $price *= $base / 100; }
							elseif ( 'multiplied' === $type ) { $price = is_numeric( $input ) ? $price * $input : NAN; }
							elseif ( 'characters' === $type ) { $text = apply_filters( 'yith_wapo_remove_spaces', false ) ? str_replace( ' ', '', $input ) : $input; $price *= function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text ); }
							if ( 'decrease' === $method && in_array( $type, array( 'fixed', 'percentage' ), true ) ) { $price = -$price; }
						}
						return $price * $quantity;
					};
					$amounts = array( 'price' => $calculate( $info['price'] ?? 0 ), 'price_sale' => $calculate( $info['price_sale'] ?? '' ) );
				}
			}
			$sale = $amounts['price_sale'] ?? '';
			$amount = '' !== $sale && is_numeric( $sale ) && $sale >= 0 ? $sale : ( $amounts['price'] ?? null );
			if ( ! is_numeric( $amount ) || ! is_finite( (float) $amount ) ) { return false; }
			$free[ $addon_id ] = $free[ $addon_id ] ?? 0;
			if ( 'yes' === ( $info['addon_first_options_selected'] ?? 'no' ) && $free[ $addon_id ] < (int) ( $info['addon_first_free_options'] ?? 0 ) ) { $amount = 0; ++$free[ $addon_id ]; }
			$priced[] = array( 'key' => $key, 'value' => $value, 'info' => $info, 'quantity' => $quantity, 'price' => (float) $amount );
		}
	}
	return $priced;
}

if ( ! function_exists( 'tinv_wishlist_item_meta_yith_woocommerce_product_add_on' ) ) {

	/**
	 * Set description for meta YITH WooCommerce Product Add-on
	 *
	 * @param array $meta Meta array.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return array
	 */

	function tinv_wishlist_item_meta_yith_woocommerce_product_add_on( $item_data, $product_id, $variation_id ) {
		if ( ! isset( $item_data['yith_wapo_product_id'] ) || ! class_exists( 'YITH_WAPO' ) ) { return $item_data; }
		$options = tinvwl_yith_saved_options( $item_data );
		$product = wc_get_product( $variation_id ?: $product_id );
		$priced = false !== $options && TInvWL_Product::can_read_product( $product ) ? tinvwl_yith_priced_options( $item_data, $product, $options ) : false;
		foreach ( array( 'yith_wapo', 'yith_wapo_product_id', 'yith_wapo_is_single', 'yith_wapo_product_qty', 'yith_wapo_qty_options' ) as $field ) { unset( $item_data[ $field ] ); }
		if ( false === $priced ) {
			$item_data[] = array( 'key' => __( 'Configuration', 'ti-woocommerce-wishlist' ), 'display' => __( 'Saved configuration is unavailable.', 'ti-woocommerce-wishlist' ) );
			return $item_data;
		}
		if ( 'yes' === get_option( 'yith_wapo_settings_show_product_price_cart' ) ) {
			$item_data[] = array( 'key' => __( 'Base price', 'ti-woocommerce-wishlist' ), 'display' => wc_price( wc_get_price_to_display( $product, array( 'price' => $product->get_price(), 'qty' => 1 ) ) ) );
		}
		$groups = array();
		foreach ( $priced as $option ) {
			$info = $option['info']; $value = $option['value']; $type = $info['addon_type'];
			$name = $info['addon_label'] ?? '';
			if ( 'product' === $type ) {
				$child = tinvwl_yith_selected_product( $value, $info );
				$value = esc_html( $option['quantity'] . ' x ' . $child->get_title() );
			} elseif ( 'file' === $type ) {
				$links = array();
				foreach ( is_array( $value ) ? $value : array( $value ) as $file ) {
					$url = urldecode( $file ); $parts = explode( '/', $url );
					$links[] = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( end( $parts ) ) . '</a>';
				}
				$value = implode( '<br>', $links );
			} elseif ( in_array( $type, array( 'checkbox', 'color', 'label', 'radio', 'select' ), true ) ) {
				$value = esc_html( $info['label'] ?? '' );
			} else { $name = $info['label'] ?? $name; $value = esc_html( (string) $value ); }
			if ( 'yes' === get_option( 'yith_wapo_show_options_in_cart' ) ) {
				$amount = $option['price'];
				$display = wc_get_price_to_display( $product, array( 'price' => abs( $amount ), 'qty' => 1 ) );
				$groups[ $name ] = ( $groups[ $name ] ?? '' ) . '<div>' . $value . ( 0.0 !== $amount ? ' (' . ( $amount < 0 ? '-' : '+' ) . wc_price( $display ) . ')' : '' ) . '</div>';
			}
		}
		foreach ( $groups as $key => $value ) { $item_data[] = array( 'key' => $key, 'display' => $value ); }
		return $item_data;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_item_meta_yith_woocommerce_product_add_on', 10, 3 );
} // End if().

if ( ! function_exists( 'tinvwl_item_price_yith_woocommerce_product_add_on' ) ) {

	/**
	 * Modify price for YITH WooCommerce product Addons.
	 *
	 * @param string $price Returned price.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 * @param bool $raw Return an unformatted unit price.
	 *
	 * @return string|float
	 */

	function tinvwl_item_price_yith_woocommerce_product_add_on( $price, $wl_product, $product, $raw = false ) {
		$meta = $wl_product['meta'] ?? array();
		$meta = is_string( $meta ) ? json_decode( $meta, true ) : $meta;
		if ( is_array( $meta ) && ! array_key_exists( 'yith_wapo', $meta ) ) { return $price; }
		$wl_product['meta'] = $meta;
		$options = tinvwl_yith_saved_options( $meta );
		if ( false === $options || ! apply_filters( 'tinvwl_wishlist_item_configuration_readable', true, $wl_product, $product ) ) { return $raw ? NAN : __( 'Unavailable', 'ti-woocommerce-wishlist' ); }
		$priced = tinvwl_yith_priced_options( $meta, $product, $options );
		if ( false === $priced ) { return $raw ? NAN : __( 'Unavailable', 'ti-woocommerce-wishlist' ); }
		$total = (float) $product->get_price() + array_sum( array_column( $priced, 'price' ) );
		return $raw ? $total : wc_price( wc_get_price_to_display( $product, array( 'price' => $total, 'qty' => 1 ) ) );
	}

	add_filter( 'tinvwl_wishlist_item_price', 'tinvwl_item_price_yith_woocommerce_product_add_on', 10, 4 );
} // End if().

/** Native request quantities must not alter the saved configuration used for attribution. */
function tinvwl_prepare_cart_yith_component_quantities( $meta, $wl_product ) {
	if ( empty( $meta['yith_wapo'] ) ) { return $meta; }
	$options = tinvwl_yith_saved_options( $meta );
	if ( false === $options ) { throw new InvalidArgumentException( __( 'Saved configuration is unavailable.', 'ti-woocommerce-wishlist' ) ); }
	$quantities = array();
	foreach ( $options as $option ) {
		foreach ( $option as $key => $value ) {
			$ids = explode( '-', $key );
			$info = yith_wapo_get_option_info( $ids[0], $ids[1] ?? $value );
			if ( 'product' === ( $info['addon_type'] ?? '' ) ) {
				$quantities[ $key ] = tinvwl_yith_option_quantity( $meta, $key, $value );
			}
		}
	}
	$meta['yith_wapo'] = $options;
	foreach ( array( 'yith_wapo_product_qty', 'yith_wapo_qty_options' ) as $field ) {
		if ( ! array_key_exists( $field, $meta ) ) { continue; }
		$meta[ $field ] = tinvwl_yith_quantity_map( $meta[ $field ] );
		if ( false === $meta[ $field ] ) { throw new InvalidArgumentException( __( 'Saved configuration is unavailable.', 'ti-woocommerce-wishlist' ) ); }
	}
	if ( $quantities || isset( $meta['yith_wapo_product_qty'] ) || isset( $meta['yith_wapo_qty_options'] ) ) {
		$meta['yith_wapo_product_qty'] = array_replace( $meta['yith_wapo_qty_options'] ?? array(), $meta['yith_wapo_product_qty'] ?? array(), $quantities );
	}
	return $meta;
}
add_filter( 'tinvwl_addproduct_tocart_prepare_meta', 'tinvwl_prepare_cart_yith_component_quantities', 10, 2 );

if ( ! function_exists( 'tinvwl_add_to_cart_meta_yith_woocommerce_product_add_on' ) ) {

	function tinvwl_add_to_cart_meta_yith_woocommerce_product_add_on( $wl_product ) {
		if ( class_exists( 'YITH_WAPO' ) ) {

			if ( ! empty( $wl_product['meta']['yith_wapo'] ) ) {
				if ( is_string( $wl_product['meta']['yith_wapo'] ) ) {
					$decoded = json_decode( $wl_product['meta']['yith_wapo'], true );
					if ( is_array( $decoded ) ) { $wl_product['meta']['yith_wapo'] = $decoded; }
				}
			}
		}

		return $wl_product;
	}

	add_filter( 'tinvwl_addproduct_tocart', 'tinvwl_add_to_cart_meta_yith_woocommerce_product_add_on' );
} // End if().


if ( ! function_exists( 'tinvwl_meta_validate_cart_add_yith_woocommerce_product_add_on' ) ) {

	/**
	 * Checks the ability to add a product
	 *
	 * @param boolean $redirect Default value to redirect.
	 * @param \WC_Product $product Product data.
	 * @param string $redirect_url Current url for redirect.
	 * @param array $wl_product Wishlist Product.
	 *
	 * @return boolean
	 */
	function tinvwl_meta_validate_cart_add_yith_woocommerce_product_add_on( $redirect, $product, $redirect_url, $wl_product ) {
		if ( class_exists( 'YITH_WAPO' ) ) {

			if ( ! empty( $wl_product['meta']['yith_wapo'] ) ) {

				$options = tinvwl_yith_saved_options( $wl_product['meta'] );
				if ( false === $options ) { return true; }
				foreach ( $options as $index => $option ) {
					foreach ( $option as $key => $value ) {
						if ( $key && '' === $value ) {
							$value = stripslashes( $value );

							$explode = explode( '-', $key );
							if ( isset( $explode[1] ) ) {
								$addon_id  = $explode[0];
								$option_id = $explode[1];
							} else {
								$addon_id  = $key;
								$option_id = $value;
							}

							$addon    = new YITH_WAPO_Addon( $addon_id );
							$required = $addon->get_option( 'required', $option_id );
							if ( 'yes' === $required ) {
								return true;
							}
						}
					}
				}
			}
		}

		return $redirect;
	}

	add_filter( 'tinvwl_product_add_to_cart_need_redirect', 'tinvwl_meta_validate_cart_add_yith_woocommerce_product_add_on', 100, 4 );
} // End if().
