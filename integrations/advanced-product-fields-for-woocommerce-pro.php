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

if ( ! defined( 'ABSPATH' ) ) { exit; }

global $tinvwl_integrations;
$slug = 'advanced-product-fields-for-woocommerce-pro';
$tinvwl_integrations = is_array( $tinvwl_integrations ) ? $tinvwl_integrations : array();
$tinvwl_integrations[ $slug ] = array(
	'name' => 'Advanced Product Fields for WooCommerce Pro',
	'available' => class_exists( 'SW_WAPF_PRO\\WAPF' ),
);
if ( ! tinv_get_option( 'integrations', $slug ) || ! $tinvwl_integrations[ $slug ]['available'] ) { return; }

use SW_WAPF_PRO\Includes\Classes\Field_Groups;
use SW_WAPF_PRO\Includes\Classes\Fields;
use SW_WAPF_PRO\Includes\Classes\Helper;
use SW_WAPF_PRO\Includes\Classes\Cart;
use SW_WAPF_PRO\Includes\Models\Field;

/** Accept stored JSON/objects and old native cart records as one raw field map. */
function tinvwl_wapf_pro_field_values( $value ) {
	if ( is_string( $value ) ) {
		$value = json_decode( $value, true, 32 );
		if ( JSON_ERROR_NONE !== json_last_error() ) { return null; }
	}
	$normalize = static function ( $item, $depth = 0 ) use ( &$normalize ) {
		if ( $depth > 32 ) { return null; }
		if ( $item instanceof stdClass ) { $item = get_object_vars( $item ); }
		if ( is_array( $item ) ) {
			foreach ( $item as $key => $entry ) { $item[ $key ] = $normalize( $entry, $depth + 1 ); }
			return $item;
		}
		return is_scalar( $item ) || null === $item ? $item : null;
	};
	$value = $normalize( $value );
	if ( ! is_array( $value ) ) { return null; }
	$fields = array();
	foreach ( $value as $key => $entry ) {
		if ( is_string( $key ) && 0 === strpos( $key, 'field_' ) ) {
			$fields[ $key ] = $entry;
		} elseif ( is_int( $key ) && is_array( $entry ) && isset( $entry['id'] ) && is_scalar( $entry['id'] ) && array_key_exists( 'raw', $entry ) ) {
			// Older adapters copied derived cart records into the saved/request data.
			$name = 'field_' . $entry['id'];
			if ( ! array_key_exists( $name, $fields ) ) { $fields[ $name ] = $entry['raw']; }
		}
	}
	return $fields;
}

function tinvwl_wapf_pro_group_ids( $value ) {
	if ( ! is_scalar( $value ) && ! is_array( $value ) ) { return array(); }
	$ids = is_array( $value ) ? $value : explode( ',', (string) $value );
	$ids = array_filter( $ids, 'is_scalar' );
	$ids = array_map( 'trim', array_map( 'strval', $ids ) );
	return array_values( array_unique( array_filter( $ids, static function ( $id ) { return '' !== $id; } ) ) );
}

/** Native readers may inspect other posted fields and product/quantity context. */
function tinvwl_wapf_pro_with_request( array $meta, $product_id, $variation_id, $quantity, callable $callback ) {
	$previous_post = $_POST;
	$previous_request = $_REQUEST;
	$without_fields = static function ( $request ) {
		foreach ( array_keys( $request ) as $key ) { if ( 0 === strpos( (string) $key, 'wapf' ) ) { unset( $request[ $key ] ); } }
		return $request;
	};
	$request = array_merge( $without_fields( $previous_request ), $meta );
	$request['wapf'] = wp_slash( tinvwl_wapf_pro_field_values( $meta['wapf'] ?? array() ) ?? array() );
	$request['wapf_field_groups'] = implode( ',', tinvwl_wapf_pro_group_ids( $meta['wapf_field_groups'] ?? array() ) );
	$request['add-to-cart'] = $request['product_id'] = $product_id;
	$request['variation_id'] = $variation_id;
	$request['quantity'] = $quantity;
	$_POST = array_merge( $without_fields( $previous_post ), $request );
	$_REQUEST = $request;
	try { return $callback(); }
	finally { $_POST = $previous_post; $_REQUEST = $previous_request; }
}

/** Use only APF's registered public WooCommerce callbacks, in their native order. */
function tinvwl_wapf_pro_callbacks( $hook ) {
	global $wp_filter;
	$callbacks = array();
	foreach ( ( $wp_filter[ $hook ]->callbacks ?? array() ) as $priority => $entries ) {
		foreach ( $entries as $entry ) {
			$callback = $entry['function'];
			$class = is_array( $callback ) ? ( is_object( $callback[0] ) ? get_class( $callback[0] ) : $callback[0] ) : '';
			if ( is_string( $class ) && 0 === strpos( ltrim( $class, '\\' ), 'SW_WAPF_PRO\\' ) && is_callable( $callback ) ) { $callbacks[] = $entry; }
		}
	}
	return $callbacks;
}

function tinvwl_wapf_pro_native_cart_data( $product_id, $variation_id, $quantity ) {
	$callbacks = tinvwl_wapf_pro_callbacks( 'woocommerce_add_cart_item_data' );
	if ( ! $callbacks ) { return null; }
	$data = array();
	foreach ( $callbacks as $entry ) {
		$args = array( $data, $product_id, $variation_id, $quantity );
		$data = call_user_func_array( $entry['function'], array_slice( $args, 0, $entry['accepted_args'] ) );
		if ( ! is_array( $data ) ) { throw new UnexpectedValueException( 'Invalid APF cart data.' ); }
	}
	return $data;
}

/** A disposable WC_Cart with one copied product; never initialize or use a session. */
function tinvwl_wapf_pro_native_price( $data, $wl_product, $product, $quantity ) {
	$callbacks = tinvwl_wapf_pro_callbacks( 'woocommerce_before_calculate_totals' );
	if ( null === $data || ! $callbacks || ! class_exists( 'WC_Cart' ) ) { return null; }
	$copy = clone $product;
	$key = 'tinvwl_apf_preview';
	$item = array_merge( $data, array(
		'key' => $key,
		'product_id' => $wl_product['product_id'] ?? $product->get_id(),
		'variation_id' => $wl_product['variation_id'] ?? 0,
		'variation' => $product->is_type( 'variation' ) ? $product->get_variation_attributes() : array(),
		'quantity' => $quantity,
		'data' => $copy,
	) );
	$cart = new class( array( $key => $item ) ) extends WC_Cart {
		public function __construct( $items ) { $this->cart_contents = $items; }
		public function get_cart() { return $this->cart_contents; }
		public function get_cart_contents() { return $this->cart_contents; }
	};
	foreach ( $callbacks as $entry ) {
		// A callback without a cart argument cannot receive this isolated preview.
		if ( $entry['accepted_args'] > 0 ) { call_user_func( $entry['function'], $cart ); }
	}
	$items = $cart->get_cart();
	$value = isset( $items[ $key ]['data'] ) && $items[ $key ]['data'] instanceof WC_Product ? $items[ $key ]['data']->get_price() : null;
	return is_numeric( $value ) && is_finite( (float) $value ) ? (float) $value : null;
}

/** Rebuild only this row's selections through APF's own field conversion API. */
function tinvwl_wapf_pro_cart_fields( array $meta ) {
	$values = tinvwl_wapf_pro_field_values( $meta['wapf'] ?? null );
	$ids = tinvwl_wapf_pro_group_ids( $meta['wapf_field_groups'] ?? null );
	if ( null === $values || ! $ids ) { return array(); }
	$native = tinvwl_wapf_pro_native_cart_data( $_REQUEST['product_id'] ?? 0, $_REQUEST['variation_id'] ?? 0, $_REQUEST['quantity'] ?? 1 );
	if ( null !== $native ) { return is_array( $native['wapf'] ?? null ) ? $native['wapf'] : array(); }
	// Older APF builds expose these conversion methods instead of cart callbacks.
	if ( ! is_callable( array( Cart::class, 'to_cart_item_field' ) ) ) { return array(); }
	$fields = array();
	foreach ( (array) Field_Groups::get_by_ids( $ids ) as $group ) {
		foreach ( (array) ( $group->fields ?? array() ) as $field ) { $fields[ (string) $field->id ] = $field; }
	}
	$cart_fields = array();
	foreach ( $values as $key => $value ) {
		if ( '' === $value || null === $value || array() === $value ) { continue; }
		$id = substr( $key, 6 );
		if ( isset( $fields[ $id ] ) ) { $cart_fields[] = Cart::to_cart_item_field( $fields[ $id ], 0, $value ); }
	}
	return $cart_fields;
}

if ( ! function_exists( 'tinv_wishlist_item_meta_wapf' ) ) {
	function tinv_wishlist_item_meta_wapf( $item_data, $product_id, $variation_id, $form_meta = array() ) {
		global $wapf_ti_cartfield_cache;
		$wapf_ti_cartfield_cache = array();
		$original = $item_data;
		try {
			$meta = is_array( $form_meta ) ? $form_meta : array();
			$meta['wapf'] = $meta['wapf'] ?? ( $item_data['wapf']['display'] ?? null );
			$meta['wapf_field_groups'] = $meta['wapf_field_groups'] ?? ( $item_data['wapf_field_groups']['display'] ?? null );
			if ( null === tinvwl_wapf_pro_field_values( $meta['wapf'] ) || ! tinvwl_wapf_pro_group_ids( $meta['wapf_field_groups'] ) ) { return $item_data; }
			$quantity = wc_stock_amount( $meta['quantity'] ?? 1 );
			if ( ! is_numeric( $quantity ) || ! is_finite( (float) $quantity ) || $quantity <= 0 ) { $quantity = 1; }
			$cart_fields = tinvwl_wapf_pro_with_request( $meta, $product_id, $variation_id, $quantity, static function () use ( $meta ) { return tinvwl_wapf_pro_cart_fields( $meta ); } );
			$labels = array();
			foreach ( (array) Field_Groups::get_by_ids( tinvwl_wapf_pro_group_ids( $meta['wapf_field_groups'] ) ) as $group ) {
				foreach ( (array) ( $group->fields ?? array() ) as $field ) { $labels[ (string) $field->id ] = $field->label; }
			}
			foreach ( $cart_fields as $field ) {
				$item_data[ 'field_' . $field['id'] ] = array( 'key' => $labels[ (string) $field['id'] ] ?? $field['label'], 'display' => Helper::values_to_string( $field, '' ) );
			}
			foreach ( array_keys( $item_data ) as $key ) { if ( 0 === strpos( (string) $key, 'wapf' ) ) { unset( $item_data[ $key ] ); } }
			// Extensions may still read this legacy display output; prices never read it.
			$wapf_ti_cartfield_cache = $cart_fields;
			return $item_data;
		} catch ( Throwable $error ) { return $original; }
	}
	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_item_meta_wapf', 10, 4 );
}

if ( ! function_exists( 'tinvwl_calc_wapf_option_price' ) ) {
	function tinvwl_calc_wapf_option_price( $price, $wl_product, $product, $raw = false ) {
		$meta = $wl_product['meta'] ?? array();
		if ( ! is_array( $meta ) || ! $product instanceof WC_Product || null === tinvwl_wapf_pro_field_values( $meta['wapf'] ?? null ) || ! tinvwl_wapf_pro_group_ids( $meta['wapf_field_groups'] ?? null ) ) { return $price; }
		$quantity = wc_stock_amount( $wl_product['quantity'] ?? 1 );
		if ( ! is_numeric( $quantity ) || ! is_finite( (float) $quantity ) || $quantity <= 0 ) { $quantity = 1; }
		$product_id = $wl_product['product_id'] ?? $product->get_id();
		$variation_id = $wl_product['variation_id'] ?? 0;
		try { return tinvwl_wapf_pro_with_request( $meta, $product_id, $variation_id, $quantity, static function () use ( $price, $meta, $wl_product, $product, $quantity, $raw ) {
			$native = tinvwl_wapf_pro_native_cart_data( $_REQUEST['product_id'], $_REQUEST['variation_id'], $quantity );
			if ( null !== $native && tinvwl_wapf_pro_callbacks( 'woocommerce_before_calculate_totals' ) ) {
				$unit_price = tinvwl_wapf_pro_native_price( $native, $wl_product, $product, $quantity );
				if ( null === $unit_price || $unit_price === (float) $product->get_price() ) { return $price; }
				return $raw ? $unit_price : wc_price( wc_get_price_to_display( $product, array( 'price' => $unit_price ) ) );
			}
			if ( ! is_callable( array( Fields::class, 'do_pricing' ) ) ) { return $price; }
			$cart_fields = tinvwl_wapf_pro_cart_fields( $meta );
			if ( ! $cart_fields ) { return $price; }
			// Raw totals use the stored tax basis; display tax is applied once below.
			$base = (float) $product->get_price();
			$base = apply_filters( 'wapf/pricing/base', $base, $product, $quantity );
			$options_total = 0;
			$ids = tinvwl_wapf_pro_group_ids( $meta['wapf_field_groups'] );
			foreach ( $cart_fields as $cart_field ) {
				foreach ( (array) ( $cart_field['values'] ?? array() ) as $value ) {
					if ( 'none' === ( $value['price_type'] ?? 'none' ) ) { continue; }
					$v = isset( $value['slug'] ) ? $value['label'] : $cart_field['raw'];
					$qty_based = ( isset( $cart_field['clone_type'] ) && 'qty' === $cart_field['clone_type'] ) || ! empty( $cart_field['qty_based'] );
					$amount = Fields::do_pricing( $qty_based, $value['price_type'], $value['price'], $base, $quantity, $v, $product->get_id(), $cart_fields, $ids, 0 );
					if ( is_numeric( $amount ) && is_finite( (float) $amount ) ) { $options_total += (float) $amount; }
				}
			}
			if ( 0.0 === (float) $options_total && (float) $base === (float) $product->get_price() ) { return $price; }
			$unit_price = (float) $base + $options_total;
			return $raw ? $unit_price : wc_price( wc_get_price_to_display( $product, array( 'price' => $unit_price ) ) );
		} ); } catch ( Throwable $error ) { return $price; }
	}
	add_filter( 'tinvwl_wishlist_item_price', 'tinvwl_calc_wapf_option_price', 10, 4 );
}

if ( ! function_exists( 'tinv_wishlist_metaprepare_wapf' ) ) {
	function tinv_wishlist_metaprepare_wapf( $meta ) {
		if ( array_key_exists( 'wapf', $meta ) ) {
			$fields = tinvwl_wapf_pro_field_values( $meta['wapf'] );
			if ( null !== $fields ) { $meta['wapf'] = $fields; }
		}
		return $meta;
	}
	add_filter( 'tinvwl_product_prepare_meta', 'tinv_wishlist_metaprepare_wapf' );
}

/** Native cart validation consumes a request copy, preserving the saved identity. */
function tinvwl_prepare_request_meta_wapf_pro( $meta, $product ) {
	if ( array_key_exists( 'wapf', $meta ) ) { $meta['wapf'] = wp_slash( tinvwl_wapf_pro_field_values( $meta['wapf'] ) ?? array() ); }
	if ( array_key_exists( 'wapf_field_groups', $meta ) ) { $meta['wapf_field_groups'] = implode( ',', tinvwl_wapf_pro_group_ids( $meta['wapf_field_groups'] ) ); }
	return $meta;
}
add_filter( 'tinvwl_addproduct_tocart_prepare_meta', 'tinvwl_prepare_request_meta_wapf_pro', 10, 2 );

function tinvwl_prepare_cart_data_wapf_pro( $meta, $product ) {
	unset( $meta['wapf'], $meta['wapf_field_groups'] );
	return $meta;
}
add_filter( 'tinvwl_addproduct_tocart_prepare_cart_data', 'tinvwl_prepare_cart_data_wapf_pro', 10, 2 );

/** Retained for old callers; native APF now derives its own cart records. */
function tinvwl_add_to_cart_meta_wapf_pro( $wl_product ) { return $wl_product; }

function tinvwl_to_cart_fields_wapf_pro( Field $field, $product, $raw_value = null ) {
	if ( null === $raw_value ) { $raw_value = Fields::get_raw_field_value_from_request( $field ); }
	return Cart::to_cart_item_field( $field, 0, $raw_value );
}
