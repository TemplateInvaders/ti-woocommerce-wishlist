<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name PPOM for WooCommerce
 *
 * @version 34.0.11
 *
 * @slug woocommerce-product-addon
 *
 * @url https://wordpress.org/plugins/woocommerce-product-addon/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "woocommerce-product-addon";

$name = "PPOM for WooCommerce";

$available = defined( 'PPOM_VERSION' );

$tinvwl_integrations = is_array( $tinvwl_integrations ) ? $tinvwl_integrations : [];

$tinvwl_integrations[ $slug ] = array(
	'name'      => $name,
	'available' => $available,
);

if ( ! tinv_get_option( 'integrations', $slug ) ) {
	return;
}

/** Normalize only PPOM containers, preserving literal values inside their fields. */
function tinvwl_ppom_saved_data( $value ) {
	if ( is_string( $value ) ) { $value = json_decode( $value, true ); }
	elseif ( is_array( $value ) || is_object( $value ) ) { $value = json_decode( wp_json_encode( $value ), true ); }
	return is_array( $value ) ? $value : array();
}

function tinvwl_ppom_file_preview( $name, $settings, $cropped = false, $product_id = 0 ) {
	if ( ! is_scalar( $name ) || '' === (string) $name ) { return ''; }
	// The upload preview is an editor with Delete controls; saved metadata is read-only.
	$html = '';
	if ( function_exists( 'ppom_create_thumb_for_meta' ) ) {
		try { $html = ppom_create_thumb_for_meta( (string) $name, absint( $product_id ), $cropped, null ); }
		catch ( Throwable $error ) { $html = ''; }
	}
	return is_string( $html ) && '' !== $html ? $html : esc_html( (string) $name );
}

if ( ! $available ) {
	return;
}


if ( ! function_exists( 'tinv_wishlist_item_meta_woocommerce_product_add_on' ) ) {

	/**
	 * Set description for meta WooCommerce Product Add-on
	 *
	 * @param array $meta Meta array.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return array
	 */
	function tinv_wishlist_item_meta_woocommerce_product_add_on( $meta, $wl_product, $product ) {
		if ( isset( $wl_product['meta'] ) && isset( $wl_product['meta']['ppom'] ) && class_exists( 'NM_PersonalizedProduct' ) ) {

			$ppom = tinvwl_ppom_saved_data( $wl_product['meta']['ppom'] );
			$product_meta = isset( $ppom['fields'] ) && is_array( $ppom['fields'] ) ? $ppom['fields'] : array();

			$item_meta = array();

			if ( $product_meta ) {

				foreach ( $product_meta as $key => $value ) {

					if ( null === $value || '' === $value || array() === $value ) {
						continue;
					}

					$product_id = $wl_product['product_id'];
					$field_meta = ppom_get_field_meta_by_dataname( $product_id, $key );

					if ( empty( $field_meta ) ) {
						continue;
					}

					$field_type  = $field_meta['type'];
					$field_title = $field_meta['title'];


					switch ( $field_type ) {
						case 'quantities':
							$total_qty = 0;
							foreach ( (array) $value as $label => $qty ) {
								if ( ! empty( $qty ) ) {
									$item_meta[] = array(
										'key'     => $label,
										'display' => $qty,
									);
									$total_qty   += $qty;
								}
							}
							break;

						case 'file':
							$file_thumbs_html = '';
							foreach ( (array) $value as $file_id => $file_uploaded ) {
								$file_name = is_array( $file_uploaded ) ? ( $file_uploaded['org'] ?? '' ) : '';
								$file_thumbs_html .= tinvwl_ppom_file_preview( $file_name, $field_meta, false, $product_id );
							}
							$item_meta[] = array(
								'key'     => $field_title,
								'display' => $file_thumbs_html,
							);

							break;

						case 'cropper':
							$file_thumbs_html = '';
							foreach ( (array) $value as $file_id => $file_cropped ) {

								$file_name = is_array( $file_cropped ) ? ( $file_cropped['org'] ?? '' ) : '';
								$file_thumbs_html .= tinvwl_ppom_file_preview( $file_name, $field_meta, true, $product_id );
							}
							$item_meta[] = array(
								'key'     => $field_title,
								'display' => $file_thumbs_html,
							);
							break;

						case 'image':
							if ( $value ) {
								foreach ( (array) $value as $id => $images_meta ) {
									$images_meta = tinvwl_ppom_saved_data( $images_meta );
									if ( ! isset( $images_meta['link'], $images_meta['title'] ) || ! is_scalar( $images_meta['link'] ) || ! is_scalar( $images_meta['title'] ) ) { continue; }
									$image_url   = $images_meta['link'];
									$image_html  = '<img class="img-thumbnail" style="width:' . esc_attr( ppom_get_thumbs_size() ) . '" src="' . esc_url( $image_url ) . '" title="' . esc_attr( $images_meta['title'] ) . '">';
									$meta_key    = $field_title . '(' . $images_meta['title'] . ')';
									$item_meta[] = array(
										'key'     => $meta_key,
										'display' => $image_html,
									);
								}
							}
							break;

						case 'audio':
							if ( $value ) {
								$ppom_file_count = 1;
								foreach ( (array) $value as $id => $audio_meta ) {
									$audio_meta  = tinvwl_ppom_saved_data( $audio_meta );
									if ( ! isset( $audio_meta['link'], $audio_meta['title'] ) || ! is_scalar( $audio_meta['link'] ) || ! is_scalar( $audio_meta['title'] ) ) { continue; }
									$audio_url   = $audio_meta['link'];
									$audio_html  = '<a href="' . esc_url( $audio_url ) . '" title="' . esc_attr( $audio_meta['title'] ) . '">' . $audio_meta['title'] . '</a>';
									$meta_key    = $field_title . ': ' . $ppom_file_count ++;
									$item_meta[] = array(
										'key'     => $meta_key,
										'display' => $audio_html,
									);
								}
							}
							break;

						case 'bulkquantity':
							$item_meta[] = array(
								'key'     => $key,
								'display' => is_array( $value ) ? ( $value['option'] ?? '' ) . ' (' . ( $value['qty'] ?? '' ) . ')' : '',
							);
							break;

						default:
							$value       = ( is_array( $value ) || is_object( $value ) ) ? implode( ',', array_filter( (array) $value, 'is_scalar' ) ) : $value;
							$item_meta[] = array(
								'key'     => $field_title,
								'display' => (string) $value,
							);
							break;
					}

				} // End foreach().
			} // End if().

			if ( 0 < count( $item_meta ) ) {
				ob_start();
				tinv_wishlist_template( 'ti-wishlist-item-data.php', array( 'item_data' => $item_meta ) );
				$meta .= ob_get_clean();
			}
		} // End if().

		return $meta;
	}

	add_filter( 'tinvwl_wishlist_item_meta_data', 'tinv_wishlist_item_meta_woocommerce_product_add_on', 10, 3 );
} // End if().

function tinvwl_add_to_cart_meta_woocommerce_product_add_on( $wl_product ) {

	if ( isset( $wl_product['action'] ) && 'add' === $wl_product['action'] && isset( $wl_product['meta']['ppom'] ) && is_string( $wl_product['meta']['ppom'] ) ) {

		$decoded = json_decode( $wl_product['meta']['ppom'], true );

		if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
			$wl_product['meta']['ppom'] = $decoded;
		}
	}

	return $wl_product;
}

add_filter( 'tinvwl_addproduct_tocart', 'tinvwl_add_to_cart_meta_woocommerce_product_add_on' );

if ( ! function_exists( 'tinvwl_item_price_woocommerce_product_add_on' ) ) {

	/**
	 * Modify price for PPOM for WooCommerce
	 *
	 * @param string $price Returned price.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return string
	 */
	function tinvwl_item_price_woocommerce_product_add_on( $price, $wl_product, $product ) {

		if ( defined( 'PPOM_VERSION' ) ) {

			if ( isset( $wl_product['meta'] ) && isset( $wl_product['meta']['ppom'] ) ) {
				$decoded = json_decode( $wl_product['meta']['ppom'], true );

				if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) && isset( $decoded['ppom_option_price'] ) ) {
					$option_price = json_decode( $decoded['ppom_option_price'], true );

					if ( JSON_ERROR_NONE === json_last_error() && is_array( $option_price ) && ! empty( $option_price ) ) {

						$options_total = 0;

						foreach ( $option_price as $option ) {

							$options_total = $options_total + $option['price'];
						}


						if ( $options_total > 0 ) {
							$price = wc_price( floatval( $product->get_price() ) + floatval( $options_total ) );
						}

					}
				}

				return $price;
			}
		}


		return $price;
	}

	add_filter( 'tinvwl_wishlist_item_price', 'tinvwl_item_price_woocommerce_product_add_on', 10, 3 );
} // End if().
