<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name VAD Product Design
 *
 * @version 1.6.3
 *
 * @slug vad-product-design
 *
 * @url http://www.virtualartdevelopers.com
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "vad-product-design";

$name = "VAD Product Design";

$available = class_exists( 'VAD_Product_Design' );

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

if ( ! function_exists( 'tinv_wishlist_item_meta_vad_product_design' ) ) {

	/**
	 * Set description for meta  VAD Product Design
	 *
	 * @param array $item_data Meta array.
	 * @param int $product_id Wishlist Product.
	 * @param int $variation_id Woocommerce Product.
	 *
	 * @return array
	 */
	function tinv_wishlist_item_meta_vad_product_design( $item_data, $product_id, $variation_id ) {

		if ( class_exists( 'VAD_Product_Design' ) ) {

			$product = wc_get_product( $product_id );
			if ( $product && 'design' === $product->get_type() ) {

				if ( isset( $item_data['select_variation_id'] ) ) {
					$_product = wc_get_product( $item_data['select_variation_id']['display'] );
					if ( $_product ) {
						$name = $_product->get_name();
					}
				} else {
					$name = $product->get_name();
				}

				if ( isset( $item_data['select_product_id'] ) ) {
					$item_data[] = array(
						'key'     => __( 'Product', 'vad-product-design' ),
						'display' => $name,
					);
				}

				foreach ( array_keys( $item_data ) as $key ) {
					if ( strpos( $key, 'select_' ) === 0 ) {
						unset( $item_data[ $key ] );
					}
				}
			}
		}

		return $item_data;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_item_meta_vad_product_design', 10, 3 );
} // End if().


add_filter( 'tinvwl_wishlist_item_thumbnail', 'tinvwl_wishlist_item_thumbnail_vad_product_design', 10, 3 );

function tinvwl_wishlist_item_thumbnail_vad_product_design( $image, $wl_product, $product ) {

	if ( class_exists( 'VAD_Product_Design' ) ) {
		if ( $product && 'design' === $product->get_type() ) {
			if ( isset( $wl_product['meta']['select_product_ima'] ) ) {
				return '<img src="' . $wl_product['meta']['select_product_ima'] . '" alt="' . esc_attr( $product->get_name() ) . '" />';
			}
		}
	}

	return $image;
}

if ( ! function_exists( 'tinvwl_item_price_vad_product_design' ) ) {

	/**
	 * Modify price for  VAD Product Design.
	 *
	 * @param string $price Returned price.
	 * @param array $wl_product Wishlist Product.
	 * @param WC_Product $product Woocommerce Product.
	 *
	 * @return string
	 */
	function tinvwl_item_price_vad_product_design( $price, $wl_product, $product, $raw ) {
//		echo '<pre><code>' . print_r( $wl_product, true ) . '</code></pre>';
		if ( class_exists( 'VAD_Product_Design' ) ) {
			if ( $product && 'design' === $product->get_type() && isset( $wl_product['meta']['select_product_price'] ) ) {
				return wc_price( $wl_product['meta']['select_product_price'] );
			}
		}

		return $price;
	}

	add_filter( 'tinvwl_wishlist_item_price', 'tinvwl_item_price_vad_product_design', 10, 4 );
} // End if().

add_filter( 'woocommerce_add_cart_item_data', 'woocommerce_add_cart_item_data_vad_product_design', 10, 3 );
function woocommerce_add_cart_item_data_vad_product_design( $cart_item_data, $product_id, $variation_id ) {

	$product = wc_get_product( $product_id );

	if ( $product->get_type() === 'design' ) {


		if ( isset( $cart_item_data['select_product_id'] ) ) {
			$cart_item_data['select-product-id'] = $cart_item_data['select_product_id'];
		}

		if ( isset( $cart_item_data['select_variation_id'] ) ) {
			$cart_item_data['select-variation-id'] = $cart_item_data['select_variation_id'];
		}

		if ( isset( $cart_item_data['select_product_name'] ) ) {
			$cart_item_data['select-product-name'] = $cart_item_data['select_product_name'];
		}

		if ( isset( $cart_item_data['select_product_sku'] ) ) {
			$cart_item_data['select-product-sku'] = $cart_item_data['select_product_sku'];
		}

		if ( isset( $cart_item_data['select_product_ima'] ) ) {
			$cart_item_data['select-product-ima'] = $cart_item_data['select_product_ima'];
		}

	}

	return $cart_item_data;
}

add_filter( 'tinvwl_addtowishlist_prepare_form_cart', 'tinvwl_addtowishlist_prepare_form_cart_vad_product_design', 10, 4 );

function tinvwl_addtowishlist_prepare_form_cart_vad_product_design( $data, $cart_item_key, $cart_items, $product ) {

	if ( $product->get_type() === 'design' ) {
		$data = array();
		foreach ( $cart_items[ $cart_item_key ] as $key => $value ) {
			if ( strpos( $key, 'select-' ) === 0 && 'select-product-sku' !== $key ) {
				$data[ str_replace( '-', '_', $key ) ] = wp_strip_all_tags( $value );
			}
		}
	}

	return $data;
}

add_filter( 'tinvwl_addtowishlist_prepare_form', 'tinvwl_meta_vad_product_design', 10, 3 );

function tinvwl_meta_vad_product_design( $meta, $post, $files ) {

	$product = wc_get_product( $post['product_id'] );

	if ( $product && $product->get_type() === 'design' ) {
		if ( isset( $meta['select_product_price'] ) && $meta['select_product_price'] == $product->get_price() ) {
			unset( $meta['select_product_price'] );
		}
		foreach ( array_keys( $meta ) as $key ) {
			if ( strpos( $key, 'attribute_' ) === 0 ) {
				unset( $meta[ $key ] );
			}
		}
	}

	return $meta;
}
