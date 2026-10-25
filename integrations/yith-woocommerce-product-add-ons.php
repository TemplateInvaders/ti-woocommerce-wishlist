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
			if ( ! is_scalar( $value ) ) { return false; }
			if ( ! $key ) { unset( $options[ $index ][ $key ] ); continue; }
			$parts = explode( '-', $key );
			$addon_id = $parts[0];
			$option_id = $parts[1] ?? $value;
			$info = yith_wapo_get_option_info( $addon_id, $option_id );
			if ( ! is_array( $info ) || empty( $info['addon_type'] ) ) { return false; }
			$addon = $info['addon'] ?? new YITH_WAPO_Addon( $addon_id );
			if ( ! is_object( $addon ) || ! is_callable( array( $addon, 'get_option' ) ) ) { return false; }
			$required = 'yes' === $addon->get_option( 'required', $option_id );
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

		if ( isset( $item_data['yith_wapo_product_id'] ) && class_exists( 'YITH_WAPO' ) ) {
			$saved_meta = $item_data;
			$options = tinvwl_yith_saved_options( $item_data );
			if ( false === $options ) {
				unset( $item_data['yith_wapo'], $item_data['yith_wapo_product_id'], $item_data['yith_wapo_is_single'], $item_data['yith_wapo_product_qty'], $item_data['yith_wapo_qty_options'] );
				$item_data[] = array( 'key' => __( 'Configuration', 'ti-woocommerce-wishlist' ), 'display' => __( 'Saved configuration is unavailable.', 'ti-woocommerce-wishlist' ) );
				return $item_data;
			}
			unset( $item_data['yith_wapo_product_id'] );
			unset( $item_data['yith_wapo_is_single'], $item_data['yith_wapo_product_qty'], $item_data['yith_wapo_qty_options'] );

			$id = ( $variation_id ) ? $variation_id : $product_id;

			$base_product = wc_get_product( $id );
			if ( ! TInvWL_Product::can_read_product( $base_product ) ) {
				return array( array( 'key' => __( 'Configuration', 'ti-woocommerce-wishlist' ), 'display' => __( 'Saved configuration is unavailable.', 'ti-woocommerce-wishlist' ) ) );
			}

			if ( ( is_object( $base_product ) && get_option( 'yith_wapo_settings_show_product_price_cart' ) == 'yes' ) ) {

				$price = yit_get_display_price( $base_product );

				$price_html = wc_price( $price );

				$item_data[] = array(
					'key'     => __( 'Base price', 'ti-woocommerce-wishlist' ),
					'display' => $price_html,
				);

			}
			if ( ! empty( $item_data['yith_wapo'] ) ) {
					$base_product_price = is_object( $base_product ) ? (float) yit_get_display_price( $base_product ) : 0;
				// $total_options_price = 0;
				$cart_data_array          = array();
				$first_free_options_count = 0;
				foreach ( $options as $index => $option ) {
					foreach ( $option as $key => $value ) {
						if ( $key && '' !== $value ) {
							$value = stripslashes( $value );

							$explode = explode( '-', $key );
							if ( isset( $explode[1] ) ) {
								$addon_id  = $explode[0];
								$option_id = $explode[1];
							} else {
								$addon_id  = $key;
								$option_id = $value;
							}

							$info = yith_wapo_get_option_info( $addon_id, $option_id );

							if ( $info['price_type'] == 'percentage' ) {
								$option_percentage      = floatval( $info['price'] );
								$option_percentage_sale = floatval( $info['price_sale'] );
								$option_price           = ( $base_product_price / 100 ) * $option_percentage;
								$option_price_sale      = ( $base_product_price / 100 ) * $option_percentage_sale;
							} else if ( $info['price_type'] == 'multiplied' ) {
								$option_price      = $info['price'] * $value;
								$option_price_sale = (float) $info['price_sale'] * $value;
							} else {
								$option_price      = $info['price'];
								$option_price_sale = $info['price_sale'];
							}

							$sign = $info['price_method'] == 'decrease' ? '-' : '+';

							// First X free options check
							if ( $info['addon_first_options_selected'] == 'yes' && $first_free_options_count < $info['addon_first_free_options'] ) {
								$option_price = 0;
								$first_free_options_count ++;
							} else {
								$option_price = isset( $info['price_sale'] ) && '' !== $info['price_sale'] && $option_price_sale >= 0 ? $option_price_sale : $option_price;
							}

							$cart_data_name = ( ( isset( $info['addon_label'] ) && $info['addon_label'] != '' ) ? $info['addon_label'] : '' );

							if ( in_array( $info['addon_type'], array(
								'checkbox',
								'color',
								'label',
								'radio',
								'select'
							) ) ) {
								$value = $info['label'];
							} else if ( in_array( $info['addon_type'], array( 'product' ) ) ) {
								$option_product_info = explode( '-', $value );
								$option_product_id   = $option_product_info[1];
								$option_product_qty  = tinvwl_yith_option_quantity( $saved_meta, $key, $value );
								$option_product      = tinvwl_yith_selected_product( $value, $info );
								if ( ! $option_product ) { return array( array( 'key' => __( 'Configuration', 'ti-woocommerce-wishlist' ), 'display' => __( 'Saved configuration is unavailable.', 'ti-woocommerce-wishlist' ) ) ); }
								$value               = $option_product_qty . ' x ' . $option_product->get_title();

								if ( in_array( $info['price_method'], array( 'product', 'discount' ), true ) ) {
									$option_price = tinvwl_yith_product_option_price( $addon_id, $option_id, $key, $option[ $key ], $info, $option_product_qty, $base_product_price );
									if ( ! is_finite( $option_price ) ) { return array( array( 'key' => __( 'Configuration', 'ti-woocommerce-wishlist' ), 'display' => __( 'Saved configuration is unavailable.', 'ti-woocommerce-wishlist' ) ) ); }
								}

							} else if ( in_array( $info['addon_type'], array( 'file' ) ) ) {
								$file_url = explode( '/', $value );
								$value    = '<a href="' . $value . '" target="_blank">' . end( $file_url ) . '</a>';
							} else {
								$cart_data_name = $info['label'];
							}

							$option_price = $option_price != '' ? ( $option_price + ( ( $option_price / 100 ) * yith_wapo_get_tax_rate() ) ) : 0;

							if ( get_option( 'yith_wapo_show_options_in_cart' ) == 'yes' ) {
								if ( ! isset( $cart_data_array[ $cart_data_name ] ) ) {
									$cart_data_array[ $cart_data_name ] = '';
								}
								$cart_data_array[ $cart_data_name ] .= '<div>' . $value . ( $option_price != '' ? ' (' . $sign . wc_price( $option_price ) . ')' : '' ) . '</div>';
							}

						}
					}
				}
				foreach ( $cart_data_array as $key => $value ) {
					$item_data[] = array(
						'key'     => $key,
						'display' => $value,
					);
				}
				unset( $item_data['yith_wapo'] );

			}

		}

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
		$options = tinvwl_yith_saved_options( $wl_product['meta'] ?? array() );
		if ( false === $options || ! apply_filters( 'tinvwl_wishlist_item_configuration_readable', true, $wl_product, $product ) ) { return $raw ? NAN : __( 'Unavailable', 'ti-woocommerce-wishlist' ); }

		if ( class_exists( 'YITH_WAPO' ) ) {

			if ( ! empty( $wl_product['meta']['yith_wapo'] ) ) {
				$total_options_price      = 0;
				$first_free_options_count = 0;
				foreach ( $options as $index => $option ) {
					foreach ( $option as $key => $value ) {

						$value = stripslashes( $value );

						$explode = explode( '-', $key );
						if ( isset( $explode[1] ) ) {
							$addon_id  = $explode[0];
							$option_id = $explode[1];
						} else {
							$addon_id  = $key;
							$option_id = $value;
						}

						$info = yith_wapo_get_option_info( $addon_id, $option_id );
						if ( $key && '' !== $value ) {
							if ( $info['price_type'] == 'percentage' ) {
								$_product = $product;
								// WooCommerce Measurement Price Calculator (compatibility)
								if ( isset( $cart_item['pricing_item_meta_data']['_price'] ) ) {
									$product_price = $cart_item['pricing_item_meta_data']['_price'];
								} else {
									$product_price = floatval( $_product->get_price() );
								}
								$option_percentage      = floatval( $info['price'] );
								$option_percentage_sale = floatval( $info['price_sale'] );
								$option_price           = ( $product_price / 100 ) * $option_percentage;
								$option_price_sale      = ( $product_price / 100 ) * $option_percentage_sale;
							} else if ( $info['price_type'] == 'multiplied' ) {
								$option_price      = $info['price'] * $value;
								$option_price_sale = (float) $info['price_sale'] * $value;
							} else {
								$option_price      = $info['price'];
								$option_price_sale = $info['price_sale'];
							}

							// First X free options check
							if ( $info['addon_first_options_selected'] == 'yes' && $first_free_options_count < $info['addon_first_free_options'] ) {
								$first_free_options_count ++;
							} else {
								$option_price = isset( $info['price_sale'] ) && '' !== $info['price_sale'] && $option_price_sale >= 0 ? $option_price_sale : $option_price;


								if ( in_array( $info['addon_type'], array( 'product' ) ) && ( $info['price_method'] == 'product' || $info['price_method'] == 'discount' ) ) {
									$option_product_info = explode( '-', $value );
									$option_product_id   = $option_product_info[1];
									$option_product_qty  = tinvwl_yith_option_quantity( $wl_product['meta'], $key, $value );
									$option_product      = tinvwl_yith_selected_product( $value, $info );
									if ( ! $option_product ) { return $raw ? NAN : __( 'Unavailable', 'ti-woocommerce-wishlist' ); }
									$option_price = tinvwl_yith_product_option_price( $addon_id, $option_id, $key, $value, $info, $option_product_qty, $product->get_price() );
									if ( ! is_finite( $option_price ) ) { return $raw ? NAN : __( 'Unavailable', 'ti-woocommerce-wishlist' ); }
									$total_options_price += floatval( $option_price );

								} else if ( $info['price_method'] == 'decrease' ) {
									$total_options_price -= floatval( $option_price );
								} else {
									$total_options_price += floatval( $option_price );
								}
							}

						}
					}
				}

				$base_price = $product->get_price();
				$price      = $raw ? (float) $base_price + $total_options_price : wc_price( $base_price + $total_options_price );

			}
		}

		return $price;
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
