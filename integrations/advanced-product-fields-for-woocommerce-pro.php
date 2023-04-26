<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name Advanced Product Fields for WooCommerce Pro
 *
 * @version 1.5.4
 *
 * @slug advanced-product-fields-for-woocommerce-pro
 *
 * @url https://www.studiowombat.com/plugin/advanced-product-fields-for-woocommerce/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "advanced-product-fields-for-woocommerce-pro";

$name = "Advanced Product Fields for WooCommerce Pro";

$available = class_exists( 'SW_WAPF_PRO\WAPF' );

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

use SW_WAPF_PRO\Includes\Classes\Enumerable;
use SW_WAPF_PRO\Includes\Classes\Field_Groups;
use SW_WAPF_PRO\Includes\Classes\Fields;
use SW_WAPF_PRO\Includes\Classes\Helper;
use SW_WAPF_PRO\Includes\Classes\Cart;
use SW_WAPF_PRO\Includes\Controllers\Product_Controller;
use SW_WAPF_PRO\Includes\Models\Field;

if ( class_exists( 'SW_WAPF_PRO\WAPF' ) ) {

	$wapf_ti_cartfield_cache = array();

	if ( ! function_exists( 'tinv_wishlist_item_meta_wapf' ) ) {

		function tinv_wishlist_item_meta_wapf( $item_data, $product_id, $variation_id ) {
			global $wapf_ti_cartfield_cache;

			if ( class_exists( 'SW_WAPF_PRO\WAPF' ) ) {

				if ( empty( $item_data['wapf'] ) || ! is_array( $item_data['wapf'] ) || ! isset( $item_data['wapf_field_groups'] ) ) {
					return $item_data;
				}

				$field_groups = SW_WAPF_PRO\Includes\Classes\Field_Groups::get_by_ids( explode( ',', sanitize_text_field( $item_data['wapf_field_groups']['display'] ) ) );

				$fields = SW_WAPF_PRO\Includes\Classes\Enumerable::from( $field_groups )->merge( function ( $x ) {
					return $x->fields;
				} )->toArray();

				foreach ( $item_data['wapf']['display'] as $key => $field ) {

					if ( empty( $field ) ) {
						continue;
					}
					$field_id = str_replace( 'field_', '', $key );

					$field_obj = SW_WAPF_PRO\Includes\Classes\Enumerable::from( $fields )->firstOrDefault( function ( $x ) use ( $field_id ) {
						return $x->id === $field_id;
					} );

					$cartitem                  = Cart::to_cart_item_field( $field_obj, 0, $field );
					$wapf_ti_cartfield_cache[] = $cartitem;

					$item_data[ $key ] = array(
						'key'     => $field_obj->label,
						'display' => Helper::values_to_string( $cartitem, '' )
					);
				}

				foreach ( array_keys( $item_data ) as $key ) {
					if ( strpos( $key, 'wapf' ) === 0 ) {
						unset( $item_data[ $key ] );
					}
				}

			}

			return $item_data;
		}

		add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_item_meta_wapf', 10, 3 );
	}

	if ( ! function_exists( 'tinvwl_calc_wapf_option_price' ) ) {
		function tinvwl_calc_wapf_option_price( $price, $wl_product, $product ) {
			global $wapf_ti_cartfield_cache;

			$options_total = 0;

			$quantity = empty( $wl_product['quantity'] ) ? 1 : wc_stock_amount( $wl_product['quantity'] );
			if ( wc_prices_include_tax() ) {
				$price = wc_get_price_including_tax( $product );
			} else {
				$price = wc_get_price_excluding_tax( $product );
			}

			$base = apply_filters( 'wapf/pricing/base', $price, $product, $quantity );

			if ( ! empty( $wapf_ti_cartfield_cache ) ) {
				foreach ( $wapf_ti_cartfield_cache as $cart_field ) {
					foreach ( $cart_field['values'] as $value ) {
						if ( $value['price'] === 0 || $value['price_type'] === 'none' ) {
							continue;
						}

						$v         = isset( $value['slug'] ) ? $value['label'] : $cart_field['raw'];
						$field_ids = array();
						if ( isset( $wl_product['meta']['wapf_field_groups'] ) ) {
							$field_ids = explode( ',', $wl_product['meta']['wapf_field_groups'] );
						}
						$qty_based     = ( isset( $cart_field['clone_type'] ) && $cart_field['clone_type'] === 'qty' ) || ! empty( $cart_field['qty_based'] );
						$n_price       = Fields::do_pricing( $qty_based, $value['price_type'], $value['price'], $base, $quantity, $v, $product->get_id(), $wapf_ti_cartfield_cache, $field_ids, 0 );
						$options_total = $options_total + $n_price;
					}
				}
			}

			$wapf_ti_cartfield_cache = [];
			if ( $options_total > 0 ) {
				return wc_price( $base + $options_total );
			}

			return $price;
		}

		add_filter( 'tinvwl_wishlist_item_price', 'tinvwl_calc_wapf_option_price', 10, 3 );
	}

	if ( ! function_exists( 'tinv_wishlist_metaprepare_wapf' ) ) {
		function tinv_wishlist_metaprepare_wapf( $meta ) {
			foreach ( $meta as $key => $value ) {
				if ( 'wapf' === $key && ! is_array( $value ) ) {
					$meta[ $key ] = json_decode( $value );
				}
			}

			return $meta;
		}

		add_filter( 'tinvwl_product_prepare_meta', 'tinv_wishlist_metaprepare_wapf' );
	}
}

add_filter( 'tinvwl_addproduct_tocart', 'tinvwl_add_to_cart_meta_wapf_pro' );

function tinvwl_add_to_cart_meta_wapf_pro( $wl_product ) {

	if ( isset( $wl_product['action'] ) && 'add' === $wl_product['action'] && isset( $wl_product['meta']['wapf'] ) && isset( $wl_product['meta']['wapf_field_groups'] ) && is_array( $wl_product['meta']['wapf'] ) ) {
		$field_groups = Field_Groups::get_by_ids( explode( ',', sanitize_text_field( $wl_product['meta']['wapf_field_groups'] ) ) );

		$fields = Enumerable::from( $field_groups )->merge( function ( $x ) {
			return $x->fields;
		} )->toArray();

		$wapf_data = [];
		$product   = wc_get_product( empty( $wl_product['variation_id'] ) ? $wl_product['product_id'] : $wl_product['variation_id'] );
		foreach ( $wl_product['meta']['wapf'] as $raw_field_id => $field_value ) {
			if ( $field_value === '' ) {
				continue;
			}

			$field_id = str_replace( 'field_', '', $raw_field_id );

			$field = Enumerable::from( $fields )->firstOrDefault( function ( $x ) use ( $field_id ) {
				return $x->id === $field_id;
			} );

			if ( ! $field ) {
				continue;
			}

			$wapf_data[] = tinvwl_to_cart_fields_wapf_pro( $field, $product, $field_value );

		}
		if ( $wapf_data ) {
			$wl_product['meta']['wapf'] = $wapf_data;
		}

	}

	return $wl_product;
}

function tinvwl_to_cart_fields_wapf_pro( Field $field, $product, $raw_value = null ) {

	if ( $raw_value === null ) {
		$raw_value = Fields::get_raw_field_value_from_request( $field );
	}

	$values = Fields::raw_to_cartfield_values( $field, $raw_value );

	return [
		'id'     => $field->id,
		'raw'    => is_string( $raw_value ) ? sanitize_textarea_field( $raw_value ) : array_map( 'sanitize_textarea_field', $raw_value ),
		'values' => $values,
		'label'  => esc_html( $field->label )
	];
}
