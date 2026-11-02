<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name WooCommerce Gravity Forms Product Add-Ons
 *
 * @version 4.0.3
 *
 * @slug woocommerce-gravityforms-product-addons
 *
 * @url https://woocommerce.com/products/gravity-forms-add-ons/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "woocommerce-gravityforms-product-addons";

$name = "WooCommerce Gravity Forms Product Add-Ons";

$available = class_exists( 'WC_GFPA_Main' );

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

if ( ! function_exists( 'tinvwl_gf_repeater_inputs' ) ) {
	/** Only these native Repeater inputs contain row arrays, rather than literal text. */
	function tinvwl_gf_repeater_inputs( $field ) {
		$names = array( 'input_' . $field->id . '_row' );
		foreach ( (array) $field->fields as $child ) {
			if ( 'repeater' === $child->type ) {
				$names = array_merge( $names, tinvwl_gf_repeater_inputs( $child ) );
				continue;
			}
			$inputs = $child->get_entry_inputs();
			foreach ( is_array( $inputs ) ? $inputs : array( array( 'id' => $child->id ) ) as $input ) {
				$names[] = 'input_' . str_replace( '.', '_', (string) $input['id'] );
			}
		}
		return $names;
	}

	function tinvwl_gf_row_array( $value ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) { return $decoded; }
		}
		return $value;
	}

	/** Resolve current form fields without changing the stored configuration or request. */
	function tinvwl_gf_saved_repeater( $field, $form, $meta ) {
		$value = tinvwl_gf_row_array( $meta[ 'input_' . $field->id ] ?? null );
		if ( is_array( $value ) ) { return $value; }
		$post = array();
		foreach ( tinvwl_gf_repeater_inputs( $field ) as $name ) {
			$value = $meta[ $name ] ?? $meta[ 'input_' . str_replace( '_', '.', substr( $name, 6 ) ) ] ?? null;
			if ( null !== $value ) { $post[ $name ] = tinvwl_gf_row_array( $value ); }
		}
		$original_post = $_POST;
		$field = clone $field;
		try {
			$post[ 'is_submit_' . $form['id'] ] = 1;
			$_POST = wp_slash( $post );
			$field->set_context_property( 'form', $form );
			return $field->get_value_submission( array() );
		} finally { $_POST = $original_post; }
	}

	/** Restore row arrays only for native Repeater input names at the cart boundary. */
	function tinvwl_gf_prepare_repeater_request( $meta, $row ) {
		$id = $meta['wc_gforms_form_id'] ?? 0;
		if ( ! is_scalar( $id ) || ! class_exists( 'RGFormsModel' ) ) { return $meta; }
		$form = RGFormsModel::get_form_meta( absint( $id ) );
		if ( ! is_array( $form ) || empty( $form['fields'] ) ) { return $meta; }
		foreach ( $form['fields'] as $field ) {
			if ( 'repeater' !== $field->type ) { continue; }
			foreach ( tinvwl_gf_repeater_inputs( $field ) as $name ) {
				if ( array_key_exists( $name, $meta ) ) { $meta[ $name ] = tinvwl_gf_row_array( $meta[ $name ] ); }
			}
		}
		// GF reads WordPress POST values with unslashing. Restore that convention only
		// for its request fields; the stored configuration remains unchanged.
		foreach ( $meta as $key => $value ) {
			if ( preg_match( '/^(?:input_|gform_|state_|is_submit_|wc_gforms_)/', (string) $key ) ) {
				$meta[ $key ] = wp_slash( $value );
			}
		}
		return $meta;
	}
	add_filter( 'tinvwl_addproduct_tocart_prepare_meta', 'tinvwl_gf_prepare_repeater_request', 10, 2 );
}

if ( ! function_exists( 'tinv_wishlist_metasupport_wc_gf_addons' ) ) {

	/**
	 * Set description for meta WooCommerce - Gravity Forms Product Add-Ons
	 *
	 * @param array $meta Meta array.
	 *
	 * @return array
	 */
	function tinv_wishlist_metasupport_wc_gf_addons( $meta, $product_id = 0, $variation_id = 0, $saved_meta = array() ) {
		// Hidden form controls are removed from display metadata before this filter.
		$form_id = $meta['wc_gforms_form_id']['display'] ?? $saved_meta['wc_gforms_form_id'] ?? 0;
		if ( is_scalar( $form_id ) && $form_id && class_exists( 'RGFormsModel' ) ) {
			$form_meta = RGFormsModel::get_form_meta( absint( $form_id ) );

			if ( is_array( $form_meta ) && isset( $form_meta['fields'] ) && is_array( $form_meta['fields'] ) ) {
				$_meta = array();
				foreach ( $form_meta['fields'] as $field ) {

					if ( isset( $field['displayOnly'] ) && $field['displayOnly'] ) {
						continue;
					}

					$inputs = $field instanceof GF_Field ? $field->get_entry_inputs() : rgar( $field, 'inputs' );
					$first_input = is_array( $inputs ) && $inputs ? reset( $inputs )['id'] : $field['id'];
					$field_name = 'input_' . str_replace( '.', '_', (string) $first_input );
					if ( 'repeater' === $field['type'] && ! isset( $meta[ $field_name ] ) ) {
						$meta[ $field_name ] = array( 'key' => $field->label, 'display' => '' );
					}

					if ( array_key_exists( $field_name, $meta ) ) {

						if ( $field['type'] == 'product' ) {
							$inputs = $field instanceof GF_Field ? $field->get_entry_inputs() : rgar( $field, 'inputs' );
							if ( is_array( $inputs ) ) {
								$value = array();
								foreach ( $inputs as $input ) {
									$full_name = 'input_' . str_replace( '.', '_', $input['id'] );
									if ( $field_name == $full_name ) {
										continue;
									}
									if ( array_key_exists( $full_name, $meta ) ) {
										$value[] = $field->inputs[ array_search( $input['id'], array_column( $field->inputs, 'id' ) ) ]['label'] . ': ' . $meta[ $full_name ]['display'];
									}
								}

								$meta[ $field_name ]['display'] = ( is_array( $value ) ) ? implode( ', ', $value ) : '-';
							}
						}

						if ( 'repeater' === $field['type'] && is_callable( array( 'WC_GFPA_Item_Meta', 'repeater_lines' ) ) ) {
							$raw = $saved_meta ?: array_map( static function ( $data ) { return is_array( $data ) ? ( $data['display'] ?? '' ) : $data; }, $meta );
							$rows = tinvwl_gf_saved_repeater( $field, $form_meta, $raw );
							$lines = WC_GFPA_Item_Meta::repeater_lines( $field, $rows, array( 'form_id' => $form_meta['id'] ) );
							$meta[ $field_name ]['display'] = html_entity_decode( wp_strip_all_tags( implode( '; ', $lines ) ), ENT_QUOTES, get_bloginfo( 'charset' ) );
						}
						$meta[ $field_name ]['key'] = $field->label;
						$_meta[ $field_name ]       = $meta[ $field_name ];

					}
				}
				$meta = $_meta;
			}
		}

		return $meta;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_metasupport_wc_gf_addons', 10, 4 );
}

if ( ! function_exists( 'tinvwl_wishlist_item_url_wc_gf_addons' ) ) {

	function tinvwl_wishlist_item_url_wc_gf_addons( $url, $wl_product, $product ) {

		$gravity_form_data = wc_gfpa()->get_gravity_form_data( $product->get_id() );

		if ( isset( $gravity_form_data['enable_cart_edit'] ) && $gravity_form_data['enable_cart_edit'] !== 'no' ) {
			$url = add_query_arg( array( 'wc_gforms_wishlist_product_id' => $wl_product['ID'] ), $product->get_permalink() );
		}

		return $url;
	}

	add_filter( 'tinvwl_wishlist_item_url', 'tinvwl_wishlist_item_url_wc_gf_addons', 10, 3 );
}

if ( ! function_exists( 'tinvwl_item_price_wc_gf_addons' ) ) {

	/**
	 * Modify price for WooCommerce - Gravity Forms Product Add-Ons
	 *
	 * @param string $price Returned price.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @param bool $raw Return the numeric price before display tax.
	 * @return string|float
	 */
	function tinvwl_item_price_wc_gf_addons( $price, $wl_product, $product, $raw = false ) {
		if ( array_key_exists( 'wc_gforms_form_id', $wl_product['meta'] ) && class_exists( 'RGFormsModel' ) ) {
			$form = RGFormsModel::get_form_meta( $wl_product['meta']['wc_gforms_form_id'] );

			if ( ! is_array( $form ) || empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
				return $price;
			}

			$lead       = array(
				'form_id' => $wl_product['meta']['wc_gforms_form_id']
			);
			$lead['id'] = uniqid() . time() . rand();

			foreach ( $wl_product['meta'] as $key => $meta ) {
				if ( strpos( $key, 'input_' ) === 0 ) {
					$lead[ str_replace( '_', '.', substr( $key, strlen( 'input_' ) ) ) ] = $meta;
				}
			}

			if ( is_callable( array( 'WC_GFPA_Field_Helpers', 'get_product_fields' ) ) ) {
				// The current native calculator includes conditional fields and Repeater rows.
				$lead['currency'] = GFCommon::get_currency();
				foreach ( $form['fields'] as $field ) {
					$id = (string) $field['id'];
					if ( 'repeater' === $field['type'] ) { $lead[ $id ] = tinvwl_gf_saved_repeater( $field, $form, $wl_product['meta'] ); }
				}
				$products = WC_GFPA_Field_Helpers::get_product_fields( $form, $lead );
				$total = 0;
			} else {
				$use_choice_text = false;
				$use_admin_label = false;
				$products        = array();
				$total           = 0;

				foreach ( $form["fields"] as $field ) {
					$id         = $field["id"];
					$lead_value = RGFormsModel::get_lead_field_value( $lead, $field );

					$quantity_field = GFCommon::get_product_fields_by_type( $form, array( "quantity" ), $id );
					$quantity       = sizeof( $quantity_field ) > 0 ? RGFormsModel::get_lead_field_value( $lead, $quantity_field[0] ) : 1;

					switch ( $field["type"] ) {

						case "product" :

							//if single product, get values from the multiple inputs
							if ( is_array( $lead_value ) ) {
								$product_quantity = sizeof( $quantity_field ) == 0 && ! rgar( $field, "disableQuantity" ) ? rgget( $id . ".3", $lead_value ) : $quantity;
								if ( empty( $product_quantity ) ) {
									break;
								}

								if ( ! rgget( $id, $products ) ) {
									$products[ $id ] = array();
								}

								$products[ $id ]["name"]     = $use_admin_label && ! rgempty( "adminLabel", $field ) ? $field["adminLabel"] : $lead_value[ $id . ".1" ];
								$products[ $id ]["price"]    = rgar( $lead_value, $id . ".2" );
								$products[ $id ]["quantity"] = $product_quantity;
							} else if ( ! empty( $lead_value ) ) {

								if ( empty( $quantity ) ) {
									break;
								}

								if ( ! rgar( $products, $id ) ) {
									$products[ $id ] = array();
								}

								if ( $field["inputType"] == "price" ) {
									$name  = $field["label"];
									$price = $lead_value;
								} else {
									list( $name, $price ) = explode( "|", $lead_value );
								}

								$products[ $id ]["name"]     = ! $use_choice_text ? $name : RGFormsModel::get_choice_text( $field, $name );
								$products[ $id ]["price"]    = $price;
								$products[ $id ]["quantity"] = $quantity;
								$products[ $id ]["options"]  = array();
							}

							if ( isset( $products[ $id ] ) ) {
								$options = GFCommon::get_product_fields_by_type( $form, array( "option" ), $id );
								foreach ( $options as $option ) {
									$option_value = RGFormsModel::get_lead_field_value( $lead, $option );
									$option_label = empty( $option["adminLabel"] ) ? $option["label"] : $option["adminLabel"];
									if ( is_array( $option_value ) ) {
										foreach ( $option_value as $value ) {
											$option_info = GFCommon::get_option_info( $value, $option, $use_choice_text );
											if ( ! empty( $option_info ) ) {
												$products[ $id ]["options"][] = array(
													"field_label"  => rgar( $option, "label" ),
													"option_name"  => rgar( $option_info, "name" ),
													"option_label" => $option_label . ": " . rgar( $option_info, "name" ),
													"price"        => rgar( $option_info, "price" )
												);
											}
										}
									} else if ( ! empty( $option_value ) ) {
										$option_info                  = GFCommon::get_option_info( $option_value, $option, $use_choice_text );
										$products[ $id ]["options"][] = array(
											"field_label"  => rgar( $option, "label" ),
											"option_name"  => rgar( $option_info, "name" ),
											"option_label" => $option_label . ": " . rgar( $option_info, "name" ),
											"price"        => rgar( $option_info, "price" )
										);
									}
								}
							}
							break;
					}
				}

				$shipping_field = GFCommon::get_fields_by_type( $form, array( "shipping" ) );
				$shipping_price = $shipping_name = "";

				if ( ! empty( $shipping_field ) && ! RGFormsModel::is_field_hidden( $form, $shipping_field[0], array(), $lead ) ) {
					$shipping_price = RGFormsModel::get_lead_field_value( $lead, $shipping_field[0] );
					$shipping_name  = $shipping_field[0]["label"];
					if ( $shipping_field[0]["inputType"] != "singleshipping" ) {
						list( $shipping_method, $shipping_price ) = explode( "|", $shipping_price );
						$shipping_name = $shipping_field[0]["label"] . " ($shipping_method)";
					}
				}

				$shipping_price = GFCommon::to_number( $shipping_price );

				$product_info = array(
					"products" => $products,
					"shipping" => array( "name" => $shipping_name, "price" => $shipping_price )
				);

				$products = apply_filters( "gform_product_info_{$form["id"]}", apply_filters( "gform_product_info", $product_info, $form, $lead ), $form, $lead );
			}

			if ( ! empty( $products["products"] ) ) {

				foreach ( $products["products"] as $_product ) {
					$price = GFCommon::to_number( $_product["price"] );
					if ( is_array( rgar( $_product, "options" ) ) ) {
						$index = 1;
						foreach ( $_product["options"] as $option ) {
							$price += GFCommon::to_number( $option["price"] );
							$index ++;
						}
					}
					$subtotal = floatval( $_product["quantity"] ) * $price;
					$total    += $subtotal;
				}

				$total += floatval( $products["shipping"]["price"] );
			}
			$price = $product->get_price( 'edit' );
			$price += (float) $total;

			if ( $raw ) { return $price; }
			return wc_price( wc_get_price_to_display( $product, array( 'price' => $price ) ) );
		}

		return $price;
	}

	add_filter( 'tinvwl_wishlist_item_price', 'tinvwl_item_price_wc_gf_addons', 10, 4 );
} // End if().

if ( ! function_exists( 'tinvwl_wc_gf_addons_edit_link' ) ) {
	function tinvwl_wc_gf_addons_edit_link( $form ) {

		if ( isset( $_GET['wc_gforms_wishlist_product_id'] ) && empty( $_POST ) ) {

			if ( ! is_scalar( $_GET['wc_gforms_wishlist_product_id'] ) ) {
				return $form;
			}
			$wl_product_id = filter_var( $_GET['wc_gforms_wishlist_product_id'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
			if ( false === $wl_product_id ) {
				return $form;
			}
			$wlp           = new TInvWL_Product( array(), TINVWL_PREFIX, true );
			$wl_product    = $wlp->get( array( 'ID' => $wl_product_id ) );
			$wl_product    = array_shift( $wl_product );

			if ( empty( $wl_product ) ) {
				return $form;
			}
			if ( empty( $wl_product['wishlist_id'] ) || ! isset( $wl_product['meta']['wc_gforms_form_id'], $form['id'] ) ) {
				return $form;
			}
			$saved_form_id = filter_var( $wl_product['meta']['wc_gforms_form_id'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
			if ( false === $saved_form_id || $saved_form_id !== filter_var( $form['id'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) ) {
				return $form;
			}

			// A product ID does not grant access to its wishlist's saved form values.
			$wl = new TInvWL_Wishlist();
			$wishlists = $wl->get( array( 'ID' => (int) $wl_product['wishlist_id'], 'count' => 1 ) );
			$wishlist = array_shift( $wishlists );
			if ( ! $wishlist || ! method_exists( $wl, 'can_view' ) || ! $wl->can_view( $wishlist ) ) {
				return $form;
			}

			global $product;
			if ( ! $product instanceof WC_Product ) {
				return $form;
			}
			$variation_id = ! empty( $wl_product['variation_id'] ) ? (int) $wl_product['variation_id'] : 0;
			$saved_product_id = $variation_id ?: (int) $wl_product['product_id'];
			$saved_product_id = apply_filters( 'wpml_object_id', $saved_product_id, $variation_id ? 'product_variation' : 'product', true );
			$saved_product = wc_get_product( $saved_product_id );
			if ( ! $saved_product ) {
				return $form;
			}
			$current_parent_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
			$saved_parent_id = $saved_product->is_type( 'variation' ) ? $saved_product->get_parent_id() : $saved_product->get_id();
			if ( (int) $current_parent_id !== (int) $saved_parent_id ) {
				return $form;
			}
			if ( array_key_exists( 'wc_gforms_form_id', $wl_product['meta'] ) ) {

				$entry       = array(
					'form_id' => $wl_product['meta']['wc_gforms_form_id']
				);
				$entry['id'] = uniqid() . time() . rand();

				foreach ( $wl_product['meta'] as $key => $meta ) {
					if ( strpos( $key, 'input_' ) === 0 ) {
						$entry[ str_replace( '_', '.', substr( $key, strlen( 'input_' ) ) ) ] = $meta;
					}
				}

				$exclude_fields = array();
				foreach ( $form['fields'] as &$field ) {
					if ( in_array( $field['id'], $exclude_fields ) ) {
						$field['cssClass']   = 'gform_hidden';
						$field['isRequired'] = false;
					} else {
						$value = null;
						if ( $field['type'] == 'checkbox' || ( $field['type'] == 'option' && $field['inputType'] == 'checkbox' ) ) { // handle checkbox fields
							// only pull the field values from the entry that match the form field we are evaluating
							$field_values = array();

							foreach ( $entry as $key => $value ) {
								$entry_key = explode( '.', $key );
								if ( $entry_key[0] == $field['id'] ) {
									$v              = explode( '|', $value );
									$field_values[] = $v[0];
								}
							}
							foreach ( $field->choices as &$choice ) {
								$choice['isSelected'] = ( in_array( $choice['value'], $field_values, true ) ) ? true : '';
							}
						} elseif ( is_array( $field->inputs ) ) { // handle other multi-input fields (address, name, time, etc.)

							// for time field, parse entry string to get individual parts of time string
							if ( $field['type'] == 'time' ) {
								// separate time string from entry into individual parts
								list( $HH, $time_end_part ) = explode( ':', $entry[ strval( $field['id'] ) ] );
								list( $MM, $AMPM ) = explode( ' ', $time_end_part );
								// save the time parts into individual array elements within the entry for our loop
								$entry[ $field['id'] . '.1' ] = $HH;
								$entry[ $field['id'] . '.2' ] = $MM;
								$entry[ $field['id'] . '.3' ] = $AMPM;
							}

							// loop each field input and set the default value from the entry
							foreach ( $field->inputs as $key => &$input ) {
								$value = '';
								if ( isset( $entry[ strval( $input['id'] ) ] ) ) {
									$value = $entry[ strval( $input['id'] ) ];
								} elseif ( isset( $entry[ $field['id'] ] ) ) {
									$value = $entry[ $field['id'] ];
								} elseif ( isset( $entry[ $field['id'] . '1' ] ) ) {
									$value = $entry[ $field['id'] ] . '.1';
								}

								$input['defaultValue'] = $value;
							}
						} else { // handle remaining single input fields
							if ( isset( $entry[ $field['id'] ] ) ) {
								$value = $entry[ $field['id'] ];
							}
						}

						// if we have a value for the field from the provided entry, set the default value for the field
						if ( ! empty( $value ) ) {
							$field['defaultValue'] = $value;
						}
					}
				}

			}

		}

		return $form;

	}

	add_filter( 'gform_pre_render', 'tinvwl_wc_gf_addons_edit_link', 99, 1 );
}

if ( ! function_exists( 'tinv_wishlist_metaprepare_wc_gf_addons' ) ) {

	/**
	 * Prepare save meta for WooCommerce - Gravity Forms Product Add-Ons
	 *
	 * @param array $meta Meta array.
	 *
	 * @return array
	 */
	function tinv_wishlist_metaprepare_wc_gf_addons( $meta ) {
		if ( array_key_exists( 'wc_gforms_form_id', $meta ) && class_exists( 'RGFormsModel' ) ) {
			foreach ( $meta as $key => $value ) {
				if ( strpos( $key, 'input_' ) === 0 ) {
					unset( $meta[ $key ] );
					$meta[ 'input_' . str_replace( '.', '_', substr( $key, strlen( 'input_' ) ) ) ] = $value;
				}
			}
		}

		return $meta;
	}

	add_filter( 'tinvwl_product_prepare_meta', 'tinv_wishlist_metaprepare_wc_gf_addons' );
}

function tinv_add_to_wishlist_wc_gf_addons() {
	wp_add_inline_script( 'tinvwl', "
						jQuery('body').off('tinvwl_add_to_wishlist_button_click.tinvwlGfAddons').on('tinvwl_add_to_wishlist_button_click.tinvwlGfAddons', function(e, el){
							jQuery(el).closest('form.cart').each(function(){
								if (jQuery(this).find('.gform_variation_wrapper').length) {

								jQuery(this).off('change.tinvwlGfAddons input.tinvwlGfAddons').on('change.tinvwlGfAddons input.tinvwlGfAddons', function(){
									tinv_add_to_wishlist_wc_gf_addons(jQuery(this), jQuery(this).find('.tinvwl_add_to_wishlist_button'));
								});

								tinv_add_to_wishlist_wc_gf_addons(jQuery(this), jQuery(el));

								}
							});
					});

					function tinv_add_to_wishlist_wc_gf_addons(form, el){

									form.find('*[aria-required]').attr('required', true);

									if (!form[0].checkValidity()){
										el.addClass('disabled-add-wishlist');
										form[0].reportValidity();
									} else {
										el.removeClass('disabled-add-wishlist');
									}

									form.find('*[aria-required]').removeAttr('required');

					}
			" );
}

add_action( 'wp_enqueue_scripts', 'tinv_add_to_wishlist_wc_gf_addons', 100, 1 );
