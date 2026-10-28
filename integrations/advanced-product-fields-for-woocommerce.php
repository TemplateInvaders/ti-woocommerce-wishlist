<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name Advanced Product Fields (Product Addons) for WooCommerce
 *
 * @version 1.7.2
 *
 * @slug advanced-product-fields-for-woocommerce
 *
 * @url https://wordpress.org/plugins/advanced-product-fields-for-woocommerce/
 *
 */

// If this file is called directly, abort.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "advanced-product-fields-for-woocommerce";

$name = "Advanced Product Fields (Product Addons) for WooCommerce";

$available = class_exists( 'SW_WAPF\WAPF' );

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

use SW_WAPF\Includes\Classes\Enumerable;
use SW_WAPF\Includes\Classes\Field_Groups;
use SW_WAPF\Includes\Classes\Fields;
use SW_WAPF\Includes\Models\Field;

if ( ! function_exists( 'tinv_wishlist_item_meta_hidden_fields_wapf' ) ) {

	/**
	 * Filter hidden fields data
	 *
	 * @param array $hidden_fields Fields names array.
	 *
	 * @return array
	 */

	function tinv_wishlist_item_meta_hidden_fields_wapf( $hidden_fields ) {
		if ( class_exists( 'SW_WAPF\WAPF' ) ) {
			$hidden_fields = array_values( array_diff( $hidden_fields, [ 'wapf', 'wapf_field_groups' ] ) );
		}

		return $hidden_fields;
	}

	add_filter( 'tinvwl_wishlist_item_meta_hidden_fields', 'tinv_wishlist_item_meta_hidden_fields_wapf', 10, 1 );
}


if ( ! function_exists( 'tinv_wishlist_item_meta_wapf' ) ) {

	/**
	 * Set description for meta Advanced Product Fields (Product Options) for WooCommerce
	 *
	 * @param array $meta Meta array.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return array
	 */

	function tinv_wishlist_item_meta_wapf( $item_data, $product_id, $variation_id ) {
		if ( class_exists( 'SW_WAPF\WAPF' ) ) {

			if ( empty( $item_data['wapf'] ) || ! is_array( $item_data['wapf'] ) || ! isset( $item_data['wapf_field_groups'] ) ) {
				return $item_data;
			}

			$field_values = $item_data['wapf']['display'] ?? null;
			if ( is_string( $field_values ) ) {
				$field_values = json_decode( $field_values, true );
			}
			if ( ! is_array( $field_values ) ) {
				return $item_data;
			}

			$field_groups = SW_WAPF\Includes\Classes\Field_Groups::get_by_ids( explode( ',', sanitize_text_field( $item_data['wapf_field_groups']['display'] ) ) );

			$fields = SW_WAPF\Includes\Classes\Enumerable::from( $field_groups )->merge( function ( $x ) {
				return $x->fields;
			} )->toArray();

			foreach ( $field_values as $key => $field ) {
				if ( ! is_numeric( $field ) && empty( $field ) ) {
					continue;
				}
				$field_id = str_replace( 'field_', '', $key );

				$field_obj      = SW_WAPF\Includes\Classes\Enumerable::from( $fields )->firstOrDefault( function ( $x ) use ( $field_id ) {
					return $x->id === $field_id;
				} );
				if ( ! $field_obj ) {
					continue;
				}
				$product        = wc_get_product( $product_id );
				$price_addition = array();

				if ( $field_obj->pricing_enabled() ) {
					$price_addition = SW_WAPF\Includes\Classes\Fields::pricing_value( $field_obj, $field );
				}

				$item_data[ $key ] = array(
					'key'     => $field_obj->label,
					'display' => SW_WAPF\Includes\Classes\Fields::value_to_string( $field_obj, $field, ! empty( $price_addition ), $product, 'cart' ),
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

if ( ! function_exists( 'tinvwl_item_price_wapf' ) ) {

	/**
	 * Modify price for Advanced Product Fields (Product Options) for WooCommerce
	 *
	 * @param string $price Returned price.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @param bool $raw Whether a numeric unit price is required.
	 *
	 * @return float|string
	 */
	function tinvwl_item_price_wapf( $price, $wl_product, $product, $raw = false ) {

		if ( class_exists( 'SW_WAPF\WAPF' ) ) {

			if ( empty( $wl_product['meta'] ) || empty( $wl_product['meta']['wapf'] ) || ! is_array( $wl_product['meta']['wapf'] ) || ! isset( $wl_product['meta']['wapf_field_groups'] ) ) {
				return $price;
			}

			$field_groups = SW_WAPF\Includes\Classes\Field_Groups::get_by_ids( explode( ',', sanitize_text_field( $wl_product['meta']['wapf_field_groups'] ) ) );

			$fields = SW_WAPF\Includes\Classes\Enumerable::from( $field_groups )->merge( function ( $x ) {
				return $x->fields;
			} )->toArray();

			$quantity = empty( $wl_product['quantity'] ) ? 1 : wc_stock_amount( $wl_product['quantity'] );

			$base          = SW_WAPF\Includes\Classes\Helper::get_product_base_price( $product );
			$options_total = 0;

			foreach ( $wl_product['meta']['wapf'] as $key => $field ) {

				$field_id = str_replace( 'field_', '', $key );

				$field_obj = SW_WAPF\Includes\Classes\Enumerable::from( $fields )->firstOrDefault( function ( $x ) use ( $field_id ) {
					return $x->id === $field_id;
				} );
				if ( ! $field_obj ) {
					continue;
				}

				$price_addition = array();

				if ( $field_obj->pricing_enabled() ) {
					$price_addition = SW_WAPF\Includes\Classes\Fields::pricing_value( $field_obj, $field );
				}

				if ( ! empty( $price_addition ) ) {
					foreach ( $price_addition as $addition ) {

						if ( $addition['value'] === 0 ) {
							continue;
						}

						$options_total = $options_total + SW_WAPF\Includes\Classes\Fields::do_pricing( $addition['value'], $quantity );

					}
				}
			}

			if ( $options_total > 0 ) {
				$unit_price = $base + $options_total;
				return $raw ? $unit_price : wc_price( wc_get_price_to_display( $product, array( 'price' => $unit_price, 'qty' => 1 ) ) );
			}

		}

		return $price;
	}

	add_filter( 'tinvwl_wishlist_item_price', 'tinvwl_item_price_wapf', 10, 4 );
} // End if().

if ( ! function_exists( 'tinv_wishlist_metaprepare_wapf' ) ) {

	/**
	 * Prepare save meta for Advanced Product Fields (Product Options) for WooCommerce
	 *
	 * @param array $meta Meta array.
	 *
	 * @return array
	 */
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

add_filter( 'tinvwl_addproduct_tocart_prepare_meta', 'tinvwl_prepare_request_meta_wapf', 10, 2 );
add_filter( 'tinvwl_addproduct_tocart_prepare_cart_data', 'tinvwl_prepare_cart_data_wapf', 10, 2 );

/** Derived cart records from older saves do not belong in the posted field map. */
function tinvwl_prepare_request_meta_wapf( $meta, $product ) {
	if ( isset( $meta['wapf'] ) && is_array( $meta['wapf'] ) ) {
		foreach ( $meta['wapf'] as $key => $value ) {
			if ( is_int( $key ) && is_array( $value ) && isset( $value['id'], $value['raw'], $value['price'], $value['value_cart'] ) ) {
				unset( $meta['wapf'][ $key ] );
			}
		}
		// WAPF reads WordPress request values with stripslashes; saved values stay raw.
		$meta['wapf'] = wp_slash( $meta['wapf'] );
	}
	return $meta;
}

/** Keep the saved/request field map separate from WAPF's native cart records. */
function tinvwl_prepare_cart_data_wapf( $meta, $product ) {
	// The installed native cart hook builds its complete records from the prepared
	// request. Duplicate raw fields or manually copied records change Woo cart IDs.
	// Missing or invalid group data must also leave the native cart key empty.
	unset( $meta['wapf'], $meta['wapf_field_groups'] );
	return $meta;
}

function tinvwl_add_to_cart_meta_wapf( $wl_product ) {

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

			$wapf_data[] = tinvwl_to_cart_fields_wapf( $field, $product, $field_value );

		}
		$wl_product['meta']['wapf'] = $wapf_data;

	}

	return $wl_product;
}

function tinvwl_to_cart_fields_wapf( Field $field, $product, $raw_value = null ) {

	if ( $raw_value === null ) {
		$raw_value = Fields::get_raw_field_value_from_request( $field );
	}

	$price_addition = [];

	if ( $field->pricing_enabled() ) {
		$price_addition = Fields::pricing_value( $field, $raw_value );
	}

	return [
		'id'         => $field->id,
		'raw'        => is_string( $raw_value ) ? sanitize_textarea_field( $raw_value ) : array_map( 'sanitize_textarea_field', $raw_value ),
		'value'      => Fields::value_to_string( $field, $raw_value, ! empty( $price_addition ), $product ),
		'value_cart' => Fields::value_to_string( $field, $raw_value, ! empty( $price_addition ), $product, 'cart' ),
		'price'      => $price_addition,
		'label'      => esc_html( $field->label )
	];
}
