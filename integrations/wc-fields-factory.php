<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name WC Fields Factory
 *
 * @version 4.1.5
 *
 * @slug wc-fields-factory
 *
 * @url https://wordpress.org/plugins/wc-fields-factory/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "wc-fields-factory";

$name = "WC Fields Factory";

$available = class_exists( 'wcff' );

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

if ( ! function_exists( 'tinv_wishlist_item_meta_wc_fields_factory' ) ) {

	/**
	 * Set description for meta  WooCommerce Product Addons
	 *
	 * @param array $item_data Meta array.
	 * @param int $product_id Wishlist Product.
	 * @param int $variation_id Woocommerce Product.
	 *
	 * @return array
	 */
	function tinv_wishlist_item_meta_wc_fields_factory( $item_data, $product_id, $variation_id ) {

		if ( function_exists( 'wcff' ) ) {
			/* Get the last used template from session */
			$template = WC()->session->get( "wcff_current_template", "single-product" );

			$wccpf_options              = wcff()->option->get_options();
			$is_admin_module_enabled    = isset( $wccpf_options["enable_admin_field"] ) ? $wccpf_options["enable_admin_field"] : "yes";
			$is_variable_module_enabled = isset( $wccpf_options["enable_variable_field"] ) ? $wccpf_options["enable_variable_field"] : "yes";

			$meta = wcff()->dao->load_fields_groups_for_product( $product_id, 'wccpf', $template, "any" );

			/* If it is Variation products, then loads fields for Variations too */
			if ( isset( $variation_id ) && $variation_id != null && $variation_id != 0 ) {

				$wccvf_posts = array();
				$wccvf_posts = wcff()->dao->load_fields_groups_for_product( $variation_id, 'wccpf', "variable", "any" );
				$meta        = array_merge( $meta, $wccvf_posts );

				if ( $is_variable_module_enabled == "yes" ) {
					$wccvf_posts = array();
					$wccvf_posts = wcff()->dao->load_fields_groups_for_product( $variation_id, 'wccvf', "any", "any" );
					$meta        = array_merge( $meta, $wccvf_posts );
				}

				if ( $is_admin_module_enabled == "yes" ) {
					/* Also get the admin fields for variations */
					$wccaf_posts = wcff()->dao->load_fields_groups_for_product( $variation_id, 'wccaf', "variable", "any", true );
					$meta        = array_merge( $meta, $wccaf_posts );
				}
			}

			$meta = array_unique( $meta, SORT_REGULAR );

			$item = array();

			foreach ( $item_data as $key => $value ) {
				if ( strpos( $key, 'wccpf' ) === 0 || strpos( $key, 'wccvf' ) === 0 ) {
					$data = ( ! is_array( $value['display'] ) && is_object( json_decode( $value['display'] ) ) && strpos( $value['display'], '"file":' ) === false ) ? json_decode( $value['display'], true ) : $value['display'];

					$item[ $key ]['fname']    = $key;
					$item[ $key ]['user_val'] = $data;

					$ftype     = $format = '';
					$fee_rules = $pricing_rules = array();
					foreach ( $meta as $group ) {

						foreach ( $group['fields'] as $field ) {

							$key_parts = explode( '_', $key );
							$short_key = $key_parts[0] . '_' . $key_parts[1];

							if ( $short_key === $field['key'] ) {

								$ftype         = $field['type'];
								$fee_rules     = isset( $field['fee_rules'] ) ? $field['fee_rules'] : array();
								$pricing_rules = isset( $field['pricing_rules'] ) ? $field['pricing_rules'] : array();
								$format        = isset( $field['format'] ) ? $field['format'] : '';
								break;
							}
						}
					}
					$item[ $key ]['fee_rules']     = $fee_rules;
					$item[ $key ]['pricing_rules'] = $pricing_rules;
					$item[ $key ]['format']        = $format;
					$item[ $key ]['ftype']         = $ftype;

					unset( $item_data[ $key ] );
				}
			}

			$item['product_id']   = $product_id;
			$item['variation_id'] = $variation_id;

			$data = wcff()->renderer->render_fields_data( array(), $item );

			foreach ( $data as $opt ) {

				$item_data[] = array(
					'key'     => $opt['name'],
					'display' => $opt['value'],
				);
			}
		}

		return $item_data;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_item_meta_wc_fields_factory', 10, 3 );
} // End if().

if ( ! function_exists( 'tinvwl_item_price_wc_fields_factory' ) ) {

	/**
	 * Modify price for  WooCommerce Product Addons.
	 *
	 * @param string $price Returned price.
	 * @param array $wl_product Wishlist Product.
	 * @param WC_Product $product Woocommerce Product.
	 *
	 * @return string
	 */
	function tinvwl_item_price_wc_fields_factory( $price, $wl_product, $product ) {

		if ( function_exists( 'wcff' ) ) {
			$replaced_price  = 0;
			$additional_cost = 0;

			$customPrice = $basePrice = $product->get_price();

			$product_id   = $wl_product['product_id'];
			$variation_id = $wl_product['variation_id'];


			foreach ( $wl_product['meta'] as $ckey => $cval ) {
				if ( ( strpos( $ckey, "wccpf_" ) !== false || strpos( $ckey, "wccvf_" ) !== false ) ) {

					/* Get the last used template from session */
					$template = WC()->session->get( "wcff_current_template", "single-product" );

					$wccpf_options              = wcff()->option->get_options();
					$is_admin_module_enabled    = isset( $wccpf_options["enable_admin_field"] ) ? $wccpf_options["enable_admin_field"] : "yes";
					$is_variable_module_enabled = isset( $wccpf_options["enable_variable_field"] ) ? $wccpf_options["enable_variable_field"] : "yes";

					$meta = wcff()->dao->load_fields_groups_for_product( $product_id, 'wccpf', $template, "any" );

					/* If it is Variation products, then loads fields for Variations too */
					if ( isset( $variation_id ) && $variation_id != null && $variation_id != 0 ) {

						$wccvf_posts = array();
						$wccvf_posts = wcff()->dao->load_fields_groups_for_product( $variation_id, 'wccpf', "variable", "any" );
						$meta        = array_merge( $meta, $wccvf_posts );

						if ( $is_variable_module_enabled == "yes" ) {
							$wccvf_posts = array();
							$wccvf_posts = wcff()->dao->load_fields_groups_for_product( $variation_id, 'wccvf', "any", "any" );
							$meta        = array_merge( $meta, $wccvf_posts );
						}

						if ( $is_admin_module_enabled == "yes" ) {
							/* Also get the admin fields for variations */
							$wccaf_posts = wcff()->dao->load_fields_groups_for_product( $variation_id, 'wccaf', "variable", "any", true );
							$meta        = array_merge( $meta, $wccaf_posts );
						}
					}

					$meta = array_unique( $meta, SORT_REGULAR );

					$item = array();

					foreach ( $wl_product['meta'] as $key => $value ) {
						if ( strpos( $key, 'wccpf' ) === 0 || strpos( $key, 'wccvf' ) === 0 ) {
							$data = ( ! is_array( $value ) && is_object( json_decode( $value ) ) ) ? json_decode( $value, true ) : $value;

							$item[ $key ]['fname']    = $key;
							$item[ $key ]['user_val'] = $data;

							$ftype     = $format = '';
							$fee_rules = $pricing_rules = array();
							foreach ( $meta as $group ) {

								foreach ( $group['fields'] as $field ) {

									$key_parts = explode( '_', $key );
									$short_key = $key_parts[0] . '_' . $key_parts[1];

									if ( $short_key === $field['key'] ) {

										$ftype         = $field['type'];
										$fee_rules     = isset( $field['fee_rules'] ) ? $field['fee_rules'] : array();
										$pricing_rules = isset( $field['pricing_rules'] ) ? $field['pricing_rules'] : array();
										$format        = isset( $field['format'] ) ? $field['format'] : '';
										break;
									}
								}
							}
							$item[ $key ]['fee_rules']     = $fee_rules;
							$item[ $key ]['pricing_rules'] = $pricing_rules;
							$item[ $key ]['format']        = $format;
							$item[ $key ]['ftype']         = $ftype;
						}
					}

					$item['product_id']   = $product_id;
					$item['variation_id'] = $variation_id;


					if ( isset( $item[ $ckey ]["pricing_rules"] ) ) {


						$ftype   = $item [ $ckey ] ["ftype"];
						$dformat = $item[ $ckey ] ["format"];
						$uvalue  = $item [ $ckey ] ["user_val"];
						$p_rules = $item [ $ckey ] ["pricing_rules"];

						foreach ( $p_rules as $prule ) {
							if ( wcff()->negotiator->check_rules( $prule, $uvalue, $ftype, $dformat ) ) {

								$is_amount = isset( $prule["tprice"] ) && $prule["tprice"] == "cost" ? true : false;

								/* Determine the price */
								if ( $is_amount ) {

									if ( class_exists( 'WOOCS' ) ) {
										global $WOOCS;
										if ( $WOOCS->is_multiple_allowed ) {
											$prule ['amount'] = $WOOCS->woocs_exchange_value( floatval( $prule ['amount'] ) );
										}
									}

									if ( $prule["ptype"] == "add" ) {
										$customPrice = $customPrice + floatval( $prule["amount"] );

										$additional_cost = $additional_cost + floatval( $prule["amount"] );

									} else if ( $prule["ptype"] == "sub" ) {
										$customPrice = $customPrice - floatval( $prule["amount"] );

										$additional_cost = $additional_cost - floatval( $prule["amount"] );
									} else {
										$customPrice = floatval( $prule["amount"] );

										$replaced_price = $replaced_price + floatval( $prule["amount"] );
									}
								} else {
									if ( $prule ["ptype"] == "add" ) {
										$additional_cost = $additional_cost + ( ( floatval( $prule["amount"] ) / 100 ) * $basePrice );
									} else if ( $prule["ptype"] == "sub" ) {
										$additional_cost = $additional_cost - ( ( floatval( $prule["amount"] ) / 100 ) * $basePrice );
									} else {
										$replaced_price = $replaced_price + ( floatval( $prule["amount"] ) / 100 ) * $basePrice;
									}
								}
							}
						}

						if ( $replaced_price > 0 ) {
							$orgPrice = $replaced_price + $additional_cost;
						} else {
							$orgPrice = $basePrice + $additional_cost;
						}

						$price = wc_price( $orgPrice );
					}
				}

			}
		}

		return $price;
	}

	add_filter( 'tinvwl_wishlist_item_price', 'tinvwl_item_price_wc_fields_factory', 10, 3 );
} // End if().

add_filter( 'tinvwl_addtowishlist_prepare_form', 'tinvwl_meta_wc_fields_factory', 10, 3 );

function tinvwl_meta_wc_fields_factory( $meta, $post, $files ) {

	if ( function_exists( 'wcff' ) ) {

		foreach ( $files as $name => $file ) {
			if ( array_key_exists( $name, $meta ) ) {
				$upload = tinvwl_upload_file_wc_fields_factory( $file );
				if ( empty( $upload['error'] ) && ! empty( $upload['file'] ) ) {
					$file['tmp_name'] = $upload['file'];
					$meta[ $name ]    = json_encode( array_merge( $upload, $file ) );
				}
			}
		}
	}

	return $meta;
}

add_filter( 'tinvwl_product_prepare_meta', 'tinvwl_cart_meta_wc_fields_factory' );

function tinvwl_cart_meta_wc_fields_factory( $meta ) {

	if ( function_exists( 'wcff' ) ) {

		$files = $_FILES;

		foreach ( $files as $name => $file ) {
			if ( ! array_key_exists( $name, $meta ) ) {
				$upload = tinvwl_upload_file_wc_fields_factory( $file );
				if ( empty( $upload['error'] ) && ! empty( $upload['file'] ) ) {
					$file['tmp_name'] = $upload['file'];
					$meta[ $name ]    = json_encode( array_merge( $upload, $file ) );
				}
			}
		}
	}

	return $meta;
}


add_filter( 'tinvwl_addproduct_tocart', 'tinvwl_add_to_cart_meta_wc_fields_factory' );

function tinvwl_add_to_cart_meta_wc_fields_factory( $wl_product ) {
	if ( function_exists( 'wcff' ) ) {

		foreach ( $wl_product['meta'] as $key => $value ) {
			if ( strpos( $key, 'wccpf' ) === 0 || strpos( $key, 'wccvf' ) === 0 ) {
				$data                       = ( ! is_array( $value ) && is_object( json_decode( $value ) ) ) ? json_decode( $value, true ) : $value;
				$wl_product['meta'][ $key ] = $data;

				if ( strpos( $value, '"file":' ) !== false ) {
					$_FILES[ $key ] = json_decode( $value, true );
				}
			}
		}
	}

	return $wl_product;
}

function tinvwl_upload_file_wc_fields_factory( $file ) {

	if ( ! function_exists( 'wp_handle_upload' ) ) {
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
	}
	$upload = wp_handle_upload(
		$file,
		[
			'test_form' => false,
			'test_type' => false,
		]
	);

	return $upload;
}
