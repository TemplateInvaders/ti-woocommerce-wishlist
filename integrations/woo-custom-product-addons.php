<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name WooCommerce Custom Product Addons
 *
 * @version 3.1.4
 *
 * @slug woo-custom-product-addons
 *
 * @url https://wordpress.org/plugins/woo-custom-product-addons/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "woo-custom-product-addons";

$name = "WooCommerce Custom Product Addons";

$available = ( defined( 'WCPA_POST_TYPE' ) && class_exists( 'WCPA_Form' ) ) || ( defined( 'WCPA_CART_ITEM_KEY' ) && class_exists( 'Acowebs\\WCPA\\Free\\Product' ) );

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

if ( ! function_exists( 'tinvwl_wcpa_legacy_available' ) ) {
	function tinvwl_wcpa_legacy_available() {
		return defined( 'WCPA_POST_TYPE' ) && class_exists( 'WCPA_Form' ) && class_exists( 'WCPA_Front_End' );
	}
}

if ( ! function_exists( 'tinvwl_wcpa_current_available' ) ) {
	function tinvwl_wcpa_current_available() {
		return defined( 'WCPA_CART_ITEM_KEY' ) && class_exists( 'Acowebs\\WCPA\\Free\\Product' );
	}
}

if ( ! function_exists( 'tinvwl_wcpa_read_value' ) ) {
	function tinvwl_wcpa_read_value( $source, $key, $default = null ) {
		if ( is_array( $source ) && array_key_exists( $key, $source ) ) {
			return $source[ $key ];
		}

		if ( is_object( $source ) && isset( $source->{$key} ) ) {
			return $source->{$key};
		}

		return $default;
	}
}

if ( ! function_exists( 'tinvwl_wcpa_is_empty_value' ) ) {
	function tinvwl_wcpa_is_empty_value( $value ) {
		if ( is_array( $value ) ) {
			return empty( $value );
		}

		return null === $value || false === $value || '' === $value;
	}
}

if ( ! function_exists( 'tinvwl_wcpa_decode_meta_value' ) ) {
	function tinvwl_wcpa_decode_meta_value( $value ) {
		if ( is_array( $value ) && array_key_exists( 'display', $value ) ) {
			$value = $value['display'];
		}

		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( JSON_ERROR_NONE === json_last_error() ) {
				return $decoded;
			}
		}

		return $value;
	}
}

if ( ! function_exists( 'tinvwl_wcpa_current_product_fields' ) ) {
	function tinvwl_wcpa_current_product_fields( $product_id ) {
		if ( ! tinvwl_wcpa_current_available() ) {
			return array();
		}

		try {
			$product_class = 'Acowebs\\WCPA\\Free\\Product';
			$wcpa_product  = new $product_class();
			$data          = $wcpa_product->get_fields( $product_id );
		} catch ( \Throwable $e ) {
			return array();
		}

		if ( empty( $data['fields'] ) || ! is_object( $data['fields'] ) ) {
			return array();
		}

		return $data['fields'];
	}
}

if ( ! function_exists( 'tinvwl_wcpa_current_each_field' ) ) {
	function tinvwl_wcpa_current_each_field( $product_id, $callback ) {
		$sections = tinvwl_wcpa_current_product_fields( $product_id );

		foreach ( $sections as $section ) {
			$fields = tinvwl_wcpa_read_value( $section, 'fields', array() );
			if ( ! is_array( $fields ) ) {
				continue;
			}

			$extra      = tinvwl_wcpa_read_value( $section, 'extra' );
			$form_rules = tinvwl_wcpa_read_value( $extra, 'form_rules', false );

			foreach ( $fields as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				foreach ( $row as $field ) {
					if ( is_object( $field ) ) {
						call_user_func( $callback, $field, $form_rules, $section );
					}
				}
			}
		}
	}
}

if ( ! function_exists( 'tinvwl_wcpa_current_field_name' ) ) {
	function tinvwl_wcpa_current_field_name( $field ) {
		$name = tinvwl_wcpa_read_value( $field, 'name', '' );

		if ( '' === $name ) {
			$name = tinvwl_wcpa_read_value( $field, 'elementId', '' );
		}

		return is_array( $name ) ? implode( ',', $name ) : (string) $name;
	}
}

if ( ! function_exists( 'tinvwl_wcpa_current_field_label' ) ) {
	function tinvwl_wcpa_current_field_label( $field ) {
		$label = tinvwl_wcpa_read_value( $field, 'label', '' );

		if ( is_array( $label ) && isset( $label['label'] ) ) {
			$label = $label['label'];
		}

		if ( '' === $label && defined( 'WCPA_EMPTY_LABEL' ) ) {
			$label = WCPA_EMPTY_LABEL;
		}

		return is_array( $label ) ? implode( ', ', $label ) : (string) $label;
	}
}

if ( ! function_exists( 'tinvwl_wcpa_current_build_field_data' ) ) {
	function tinvwl_wcpa_current_build_field_data( $field, $value ) {
		$type      = (string) tinvwl_wcpa_read_value( $field, 'type', '' );
		$form_data = $field;
		$extract   = 'Acowebs\\WCPA\\Free\\extractFormData';

		if ( function_exists( $extract ) ) {
			$form_data = $extract( $field );
		}

		$field_data = array(
			'type'      => $type,
			'name'      => tinvwl_wcpa_current_field_name( $field ),
			'label'     => tinvwl_wcpa_current_field_label( $field ),
			'value'     => $value,
			'price'     => tinvwl_wcpa_read_value( $field, 'price', false ),
			'form_data' => $form_data,
		);

		if ( in_array( $type, array( 'date', 'datetime-local', 'time' ), true ) ) {
			$get_date_format = 'Acowebs\\WCPA\\Free\\getDateFormat';
			if ( function_exists( $get_date_format ) ) {
				$field_data['dateFormat'] = $get_date_format( $field );
			}
		}

		return $field_data;
	}
}

if ( ! function_exists( 'tinvwl_wcpa_current_display_field' ) ) {
	function tinvwl_wcpa_current_display_field( $field, $form_rules = false ) {
		$display = tinvwl_wcpa_read_value( $field, 'value', '' );

		if ( class_exists( 'Acowebs\\WCPA\\Free\\MetaDisplay' ) ) {
			try {
				$meta_display_class = 'Acowebs\\WCPA\\Free\\MetaDisplay';
				$meta_display       = new $meta_display_class( true );
				$display            = $meta_display->display( $field, $form_rules );
			} catch ( \Throwable $e ) {
				$display = tinvwl_wcpa_read_value( $field, 'value', '' );
			}
		}

		if ( is_array( $display ) || is_object( $display ) ) {
			$display = wp_json_encode( $display );
		}

		return $display;
	}
}

if ( ! function_exists( 'tinvwl_wcpa_current_item_meta_from_cart_data' ) ) {
	function tinvwl_wcpa_current_item_meta_from_cart_data( $item_data ) {
		if ( ! defined( 'WCPA_CART_ITEM_KEY' ) || ! isset( $item_data[ WCPA_CART_ITEM_KEY ] ) ) {
			return $item_data;
		}

		$cart_data = tinvwl_wcpa_decode_meta_value( $item_data[ WCPA_CART_ITEM_KEY ] );
		unset( $item_data[ WCPA_CART_ITEM_KEY ] );

		if ( ! is_array( $cart_data ) ) {
			return $item_data;
		}

		foreach ( $cart_data as $section_key => $section ) {
			$fields = tinvwl_wcpa_read_value( $section, 'fields', array() );
			if ( ! is_array( $fields ) ) {
				continue;
			}

			$extra      = tinvwl_wcpa_read_value( $section, 'extra' );
			$form_rules = tinvwl_wcpa_read_value( $extra, 'form_rules', false );

			foreach ( $fields as $row_index => $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				foreach ( $row as $col_index => $field ) {
					if ( is_object( $field ) ) {
						$field = (array) $field;
					}

					$type = (string) tinvwl_wcpa_read_value( $field, 'type', '' );
					if ( in_array( $type, array( 'header', 'content', 'hidden', 'separator' ), true ) ) {
						continue;
					}

					$name    = tinvwl_wcpa_current_field_name( $field );
					$key     = 'wcpa_' . sanitize_key( $name ? $name : $section_key . '_' . $row_index . '_' . $col_index );
					$display = tinvwl_wcpa_current_display_field( $field, $form_rules );

					$item_data[ $key ] = array(
						'type'    => $type,
						'name'    => $name,
						'key'     => tinvwl_wcpa_current_field_label( $field ),
						'display' => $display,
					);
				}
			}
		}

		return $item_data;
	}
}

if ( ! function_exists( 'tinvwl_wcpa_current_item_meta_from_raw_fields' ) ) {
	function tinvwl_wcpa_current_item_meta_from_raw_fields( $item_data, $product_id ) {
		tinvwl_wcpa_current_each_field( $product_id, function ( $field, $form_rules ) use ( &$item_data ) {
			$type = (string) tinvwl_wcpa_read_value( $field, 'type', '' );
			if ( in_array( $type, array( 'header', 'content', 'hidden', 'separator' ), true ) ) {
				return;
			}

			$name = tinvwl_wcpa_current_field_name( $field );
			if ( '' === $name || ! isset( $item_data[ $name ] ) ) {
				return;
			}

			if ( ! is_array( $item_data[ $name ] ) ) {
				$item_data[ $name ] = array(
					'key'     => $name,
					'display' => $item_data[ $name ],
				);
			}

			$value      = tinvwl_wcpa_decode_meta_value( $item_data[ $name ] );
			$field_data = tinvwl_wcpa_current_build_field_data( $field, $value );

			$item_data[ $name ]['key']     = tinvwl_wcpa_current_field_label( $field );
			$item_data[ $name ]['display'] = tinvwl_wcpa_current_display_field( $field_data, $form_rules );
		} );

		return $item_data;
	}
}

if ( ! function_exists( 'tinvwl_wcpa_current_item_meta' ) ) {
	function tinvwl_wcpa_current_item_meta( $item_data, $product_id ) {
		$item_data = tinvwl_wcpa_current_item_meta_from_cart_data( $item_data );

		foreach ( array( 'wcpa_cart_rules', 'wcpa_field_key_checker' ) as $internal_key ) {
			if ( isset( $item_data[ $internal_key ] ) ) {
				unset( $item_data[ $internal_key ] );
			}
		}

		return tinvwl_wcpa_current_item_meta_from_raw_fields( $item_data, $product_id );
	}
}

if ( ! function_exists( 'tinvwl_wcpa_price_value' ) ) {
	function tinvwl_wcpa_price_value( $price ) {
		if ( is_numeric( $price ) ) {
			return (float) $price;
		}

		$total = 0.0;

		if ( is_object( $price ) ) {
			$price = (array) $price;
		}

		if ( is_array( $price ) ) {
			foreach ( $price as $value ) {
				if ( is_numeric( $value ) ) {
					$total += (float) $value;
				} elseif ( is_array( $value ) || is_object( $value ) ) {
					$total += tinvwl_wcpa_price_value( $value );
				}
			}
		}

		return $total;
	}
}

if ( ! function_exists( 'tinvwl_wcpa_current_cart_data_has_value' ) ) {
	function tinvwl_wcpa_current_cart_data_has_value( $cart_data, $field_name ) {
		if ( ! is_array( $cart_data ) ) {
			return false;
		}

		foreach ( $cart_data as $section ) {
			$fields = tinvwl_wcpa_read_value( $section, 'fields', array() );
			if ( ! is_array( $fields ) ) {
				continue;
			}

			foreach ( $fields as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				foreach ( $row as $field ) {
					$name = tinvwl_wcpa_current_field_name( $field );
					if ( $field_name === $name && ! tinvwl_wcpa_is_empty_value( tinvwl_wcpa_read_value( $field, 'value' ) ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}
}

if ( ! function_exists( 'tinvwl_wcpa_current_options_price' ) ) {
	function tinvwl_wcpa_current_options_price( $wl_product ) {
		$meta       = isset( $wl_product['meta'] ) && is_array( $wl_product['meta'] ) ? $wl_product['meta'] : array();
		$product_id = isset( $wl_product['product_id'] ) ? absint( $wl_product['product_id'] ) : 0;
		$price      = 0.0;

		if ( defined( 'WCPA_CART_ITEM_KEY' ) && isset( $meta[ WCPA_CART_ITEM_KEY ] ) && is_array( $meta[ WCPA_CART_ITEM_KEY ] ) ) {
			foreach ( $meta[ WCPA_CART_ITEM_KEY ] as $section ) {
				$fields = tinvwl_wcpa_read_value( $section, 'fields', array() );
				if ( ! is_array( $fields ) ) {
					continue;
				}

				foreach ( $fields as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}

					foreach ( $row as $field ) {
						if ( ! tinvwl_wcpa_is_empty_value( tinvwl_wcpa_read_value( $field, 'value' ) ) ) {
							$price += tinvwl_wcpa_price_value( tinvwl_wcpa_read_value( $field, 'price', false ) );
						}
					}
				}
			}
		}

		if ( $product_id ) {
			tinvwl_wcpa_current_each_field( $product_id, function ( $field ) use ( &$price, $meta ) {
				$name = tinvwl_wcpa_current_field_name( $field );
				if ( '' === $name || ! array_key_exists( $name, $meta ) || tinvwl_wcpa_is_empty_value( $meta[ $name ] ) ) {
					return;
				}

				if ( true === tinvwl_wcpa_read_value( $field, 'is_fee', false ) || true === tinvwl_wcpa_read_value( $field, 'is_show_price', false ) ) {
					return;
				}

				$price += tinvwl_wcpa_price_value( tinvwl_wcpa_read_value( $field, 'price', false ) );
			} );
		}

		return $price;
	}
}

if ( ! function_exists( 'tinvwl_wcpa_current_required_missing' ) ) {
	function tinvwl_wcpa_current_required_missing( $wl_product, $product ) {
		$meta       = isset( $wl_product['meta'] ) && is_array( $wl_product['meta'] ) ? $wl_product['meta'] : array();
		$product_id = $product ? $product->get_id() : 0;
		$missing    = false;
		$cart_data  = defined( 'WCPA_CART_ITEM_KEY' ) && isset( $meta[ WCPA_CART_ITEM_KEY ] ) ? $meta[ WCPA_CART_ITEM_KEY ] : array();

		tinvwl_wcpa_current_each_field( $product_id, function ( $field ) use ( &$missing, $meta, $cart_data ) {
			if ( $missing ) {
				return;
			}

			$type = (string) tinvwl_wcpa_read_value( $field, 'type', '' );
			if ( 'file' === $type || ! tinvwl_wcpa_read_value( $field, 'required', false ) ) {
				return;
			}

			$name = tinvwl_wcpa_current_field_name( $field );
			if ( '' !== $name && array_key_exists( $name, $meta ) && ! tinvwl_wcpa_is_empty_value( $meta[ $name ] ) ) {
				return;
			}

			if ( tinvwl_wcpa_current_cart_data_has_value( $cart_data, $name ) ) {
				return;
			}

			$missing = true;
		} );

		return $missing;
	}
}

if ( ! function_exists( 'tinv_wishlist_item_meta_woocommerce_custom_product_addons' ) ) {

	/**
	 * Set description for meta WooCommerce Custom Product Addons
	 *
	 * @param array $meta Meta array.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return array
	 */

	function tinv_wishlist_item_meta_woocommerce_custom_product_addons( $item_data, $product_id, $variation_id ) {
		if ( tinvwl_wcpa_legacy_available() ) {
			$form     = new WCPA_Form();
			$frontend = new WCPA_Front_End();
			$data     = array();
			$post_ids = $form->get_form_ids( $product_id );

			if ( isset( $item_data['wcpa_field_key_checker'] ) ) {
				unset( $item_data['wcpa_field_key_checker'] );
			}

			if ( wcpa_get_option( 'form_loading_order_by_date' ) === true ) {
				if ( is_array( $post_ids ) && count( $post_ids ) ) {
					$post_ids = get_posts( array(
						'posts_per_page' => - 1,
						'include'        => $post_ids,
						'fields'         => 'ids',
						'post_type'      => WCPA_POST_TYPE,
					) );
				}
			}
			foreach ( $post_ids as $id ) {
				if ( get_post_status( $id ) == 'publish' ) {
					$json_string  = get_post_meta( $id, WCPA_FORM_META_KEY, true );
					$json_encoded = json_decode( $json_string );
					if ( $json_encoded && is_array( $json_encoded ) ) {
						$data = array_merge( $data, $json_encoded );
					}
				}
			}

			foreach ( $data as $v ) {
				$form_data = clone $v;
				unset( $form_data->values ); //avoid saving large number of data
				unset( $form_data->className ); //avoid saving no use data
				if ( ! in_array( $v->type, array( 'header', 'paragraph' ) ) ) {
					if ( isset( $item_data[ $v->name ] ) ) {

						if ( ! is_object( $v ) ) {
							$value = sanitize_text_field( $v );
						} else if ( ( isset( $v->name ) ) ) {
							if ( is_array( $item_data[ $v->name ] ) ) {

								$_values = $item_data[ $v->name ];
								array_walk( $_values, function ( &$a ) {
									sanitize_text_field( $a );
								} ); // using this array_wal method to preserve the keys
								$value = $_values;
							} else if ( $v->type == 'textarea' ) {
								$value = sanitize_textarea_field( wp_unslash( $item_data[ $v->name ] ) );
							} else {
								$value = sanitize_text_field( wp_unslash( $item_data[ $v->name ] ) );
							}
						}
						$item_data[ $v->name ]['key'] = ( isset( $v->label ) ) ? $v->label : '';

						$item_data[ $v->name ]['display'] = $frontend->cart_display( array(
							'type'      => $v->type,
							'name'      => $v->name,
							'label'     => ( isset( $v->label ) ) ? $v->label : '',
							'value'     => ( is_object( json_decode( $value['display'] ) ) ) ? json_decode( $value['display'], true ) : $value['display'],
							'price'     => ( isset( $v->price ) ) ? $v->price : false,
							'form_data' => $form_data,
						), wc_get_product( $product_id ) );
					}
				}
			}
		}

		if ( tinvwl_wcpa_current_available() ) {
			$item_data = tinvwl_wcpa_current_item_meta( $item_data, $product_id );
		}

		return $item_data;
	}

	add_filter( 'tinvwl_wishlist_item_meta_post', 'tinv_wishlist_item_meta_woocommerce_custom_product_addons', 10, 3 );
}

if ( ! function_exists( 'tinvwl_item_price_woocommerce_custom_product_addons' ) ) {

	/**
	 * Modify price for  WooCommerce Custom Product Addons.
	 *
	 * @param string $price Returned price.
	 * @param array $wl_product Wishlist Product.
	 * @param \WC_Product $product Woocommerce Product.
	 *
	 * @return string
	 */
	function tinvwl_item_price_woocommerce_custom_product_addons( $price, $wl_product, $product ) {
		if ( defined( 'WCPA_ITEM_ID' ) && class_exists( 'WCPA_Form' ) && class_exists( 'WCPA_MC' ) ) {

			$price    = 0;
			$form     = new WCPA_Form();
			$data     = array();
			$post_ids = $form->get_form_ids( $wl_product['product_id'] );

			if ( wcpa_get_option( 'form_loading_order_by_date' ) === true ) {
				if ( is_array( $post_ids ) && count( $post_ids ) ) {
					$post_ids = get_posts( array(
						'posts_per_page' => - 1,
						'include'        => $post_ids,
						'fields'         => 'ids',
						'post_type'      => WCPA_POST_TYPE,
					) );
				}
			}
			foreach ( $post_ids as $id ) {
				if ( get_post_status( $id ) == 'publish' ) {
					$json_string  = get_post_meta( $id, WCPA_FORM_META_KEY, true );
					$json_encoded = json_decode( $json_string );
					if ( $json_encoded && is_array( $json_encoded ) ) {
						$data = array_merge( $data, $json_encoded );
					}
				}
			}

			foreach ( $data as $v ) {
				$form_data = clone $v;
				unset( $form_data->values ); //avoid saving large number of data
				unset( $form_data->className ); //avoid saving no use data
				if ( ! in_array( $v->type, array( 'header', 'paragraph' ) ) ) {
					if ( isset( $wl_product['meta'][ $v->name ] ) ) {
						if ( ! is_object( $v ) ) {
							continue;
						} else if ( ( isset( $v->name ) ) ) {
							if ( ( ! isset( $v->is_fee ) || $v->is_fee === false ) && ( ! isset( $v->is_show_price ) || $v->is_show_price === false ) ) {
								if ( isset( $v->price ) && is_array( $v->price ) ) {
									foreach ( $v->price as $p ) {
										$price += $p;
									}
								} else if ( isset( $v->price ) && $v->price ) {
									$price += $v->price;
								}
							}
						}
					}
				}
			}

			$mc    = new WCPA_MC();
			$price = $mc->mayBeConvert( $price ) + $product->get_price( 'edit' );

			$price = wc_price( $price );

		}

		if ( tinvwl_wcpa_current_available() ) {
			$options_price = tinvwl_wcpa_current_options_price( $wl_product );

			if ( 0 < $options_price ) {
				$price = wc_price( (float) $product->get_price( 'edit' ) + $options_price );
			}
		}

		return $price;
	}

	add_filter( 'tinvwl_wishlist_item_price', 'tinvwl_item_price_woocommerce_custom_product_addons', 10, 3 );
} // End if().

if ( ! function_exists( 'tinvwl_item_price_woocommerce_custom_product_addons_text_button' ) ) {

	/**
	 * Change text for button add to cart
	 *
	 * @param string $text_add_to_cart Text "Add to cart".
	 * @param array $wl_product Wishlist product.
	 * @param object $product WooCommerce Product.
	 *
	 * @return string
	 */
	function tinvwl_item_price_woocommerce_custom_product_addons_text_button( $text_add_to_cart, $wl_product, $product ) {

		if ( tinvwl_wcpa_legacy_available() ) {
			$product_id = $product->get_id();
			$form       = new WCPA_Form();
			$post_ids   = $form->get_form_ids( $product_id );
			$data       = array();
			if ( wcpa_get_option( 'form_loading_order_by_date' ) === true ) {
				if ( is_array( $post_ids ) && count( $post_ids ) ) {
					$post_ids = get_posts( array(
						'posts_per_page' => - 1,
						'include'        => $post_ids,
						'fields'         => 'ids',
						'post_type'      => WCPA_POST_TYPE,
					) );
				}
			}
			foreach ( $post_ids as $id ) {
				if ( get_post_status( $id ) == 'publish' ) {
					$json_string  = get_post_meta( $id, WCPA_FORM_META_KEY, true );
					$json_encoded = json_decode( $json_string );
					if ( $json_encoded && is_array( $json_encoded ) ) {
						$data = array_merge( $data, $json_encoded );
					}
				}
			}

			$status = true;

			foreach ( $data as $v ) {
				if ( $v->type != 'file' && isset( $v->required ) && $v->required && ( ! isset( $wl_product['meta'][ $v->name ] ) || empty( $wl_product['meta'][ $v->name ] ) ) ) {
					$status = false;
				}
			}

			if ( ! $status ) {

				$WCPA = new WCPA_Front_End();

				return $WCPA->add_to_cart_text( $text_add_to_cart, $product );
			}
		}

		if ( tinvwl_wcpa_current_available() && tinvwl_wcpa_current_required_missing( $wl_product, $product ) ) {
			if ( class_exists( 'Acowebs\\WCPA\\Free\\Config' ) ) {
				return Acowebs\WCPA\Free\Config::get_config( 'add_to_cart_text', 'Select options', true );
			}

			if ( ! class_exists( 'Acowebs\\WCPA\\Free\\Front' ) ) {
				return $text_add_to_cart;
			}

			$wcpa_front_class = 'Acowebs\\WCPA\\Free\\Front';
			$wcpa_front       = new $wcpa_front_class();

			return $wcpa_front->add_to_cart_text( $text_add_to_cart, $product );
		}

		return $text_add_to_cart;

	}

	add_filter( 'tinvwl_wishlist_item_add_to_cart', 'tinvwl_item_price_woocommerce_custom_product_addons_text_button', 10, 3 );
}
