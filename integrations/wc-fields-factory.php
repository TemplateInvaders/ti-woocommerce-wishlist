<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name WC Fields Factory
 *
 * @version 4.1.12
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
					if ( 'file' === $ftype && is_array( $data ) && 'file' === ( $data['ftype'] ?? '' ) && isset( $data['user_val'] ) && is_string( $data['user_val'] ) ) {
						$data = $data['user_val'];
						$item[ $key ]['user_val'] = $data;
					}
					$item[ $key ]['fee_rules']     = $fee_rules;
					$item[ $key ]['pricing_rules'] = $pricing_rules;
					$item[ $key ]['format']        = $format;
					$item[ $key ]['ftype']         = $ftype;
					if ( 'file' === $ftype && is_array( $data ) && ! array_filter( $data ) ) {
						unset( $item[ $key ] );
					}

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
	function tinvwl_item_price_wc_fields_factory( $price, $wl_product, $product, $raw = false ) {

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

						$price = wc_price( wc_get_price_to_display( $product, array( 'price' => $orgPrice, 'qty' => 1 ) ) );
					}
				}
			}
		}
		if ( $raw  && isset($orgPrice)) {
			return $orgPrice;
		}

		return $price;
	}

	add_filter( 'tinvwl_wishlist_item_price', 'tinvwl_item_price_wc_fields_factory', 10, 4 );
} // End if().

add_filter( 'tinvwl_addtowishlist_prepare_form', 'tinvwl_meta_wc_fields_factory', 10, 3 );

function tinvwl_meta_wc_fields_factory( $meta, $post, $files ) {

	if ( function_exists( 'wcff' ) ) {

		foreach ( $files as $name => $file ) {
			if ( array_key_exists( $name, $meta ) ) {
				$upload = tinvwl_upload_meta_wc_fields_factory( $file );
				if ( false !== $upload && ! empty( $upload ) ) {
					$meta[ $name ] = json_encode( $upload );
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
				$upload = tinvwl_upload_meta_wc_fields_factory( $file );
				if ( false !== $upload && ! empty( $upload ) ) {
					$meta[ $name ] = json_encode( $upload );
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
				// Saved files stay encoded until their native validation/persistence scopes.
				if ( is_string( $value ) && strpos( $value, '"file":' ) !== false ) { continue; }
				$wl_product['meta'][ $key ] = ( ! is_array( $value ) && is_object( json_decode( $value ) ) ) ? json_decode( $value, true ) : $value;
			}
		}
	}
	return $wl_product;
}


/** Reuse only verified public upload descriptors; WCFF still validates all current field policies. */
class TInvWL_WCFF_Saved_Files {
    private static $contexts = array();

    public static function begin( $product, $token = 0 ) {
        $index = count( self::$contexts );
        self::$contexts[] = array( 'token' => $token, 'product_id' => (int) $product['product_id'], 'files' => array(), 'busy' => false, 'validated' => false, 'quantity' => null );
        $files = array();
        $product_id = (int) $product['product_id'];
        $variation_id = (int) ( $product['variation_id'] ?? 0 );
        $template = WC()->session ? WC()->session->get( 'wcff_current_template', 'single-product' ) : 'single-product';
        $groups = wcff()->dao->load_fields_groups_for_product( $product_id, 'wccpf', $template, 'any' );
        if ( $variation_id ) {
            $groups = array_merge( $groups, wcff()->dao->load_fields_groups_for_product( $variation_id, 'wccpf', $template, 'any' ) );
            if ( 'yes' === ( wcff()->option->get_options()['enable_variable_field'] ?? 'yes' ) ) {
                $groups = array_merge( $groups, wcff()->dao->load_fields_groups_for_product( $variation_id, 'wccvf', 'any', 'any' ) );
            }
        }
        foreach ( $groups as $group ) {
            foreach ( $group['fields'] as $field ) {
                if ( 'file' !== $field['type'] ) { continue; }
                $base = $field['key'] ?? $field['name'];
                foreach ( $product['meta'] as $key => $value ) {
                    if ( $key !== $base && ! preg_match( '/^' . preg_quote( $base, '/' ) . '_[1-9][0-9]*$/D', $key ) ) { continue; }
                    $files[ $key ] = self::prepare( $value, $field );
                    $files[ $key ]['input'] = $value;
                    $files[ $key ]['field'] = $field;
                }
            }
        }
        self::$contexts[ $index ]['files'] = $files;
        if ( ! $files ) { return; }
        // Keep native callback IDs, tie order, accepted_args, and has/remove_filter semantics.
        // Replacing the callable in its public WP_Hook slot avoids remove/add reordering.
        foreach ( array( 'woocommerce_add_to_cart_validation' => 'fields_validator', 'woocommerce_add_cart_item_data' => 'fields_persister' ) as $hook => $method ) {
            if ( empty( $GLOBALS['wp_filter'][ $hook ] ) || ! $GLOBALS['wp_filter'][ $hook ] instanceof WP_Hook ) { continue; }
            foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks as &$callbacks ) {
                foreach ( $callbacks as &$entry ) {
                    $native = $entry['function'];
                    if ( ! is_array( $native ) || ! $native[0] instanceof wcff_product_fields || $native[1] !== $method ) { continue; }
                    $entry['function'] = static function ( ...$args ) use ( $native, $method ) { return self::dispatch( $native, $method, $args ); };
                }
                unset( $entry );
            }
            unset( $callbacks );
        }
    }

    public static function end( $product = null, $post = null, $request = null, $token = 0 ) {
        for ( $index = count( self::$contexts ) - 1; $index >= 0; $index-- ) {
            if ( self::$contexts[ $index ]['token'] === $token ) { array_splice( self::$contexts, $index, 1 ); break; }
        }
    }

    public static function quantity( $passed, $product_id, $quantity ) {
        $index = count( self::$contexts ) - 1;
        if ( $index >= 0 && ! self::$contexts[ $index ]['busy'] && self::$contexts[ $index ]['files'] && (int) $product_id === self::$contexts[ $index ]['product_id'] ) {
            self::$contexts[ $index ]['quantity'] = $quantity;
        }
        return $passed;
    }

    private static function prepare( $value, $field ) {
        if ( is_array( $value ) && isset( $value['user_val'] ) ) { $value = $value['user_val']; }
        $data = is_string( $value ) ? json_decode( $value, true ) : $value;
        $multi = 'yes' === ( $field['multi_file'] ?? 'no' );
        $empty = is_array( $data ) && ! array_filter( $data );
        $uploads = $empty ? array() : ( $multi ? $data : array( $data ) );
        $valid = is_array( $uploads );
        $native = array(); $resolved = array();
        if ( $valid ) {
            foreach ( $uploads as $upload ) {
                $file = self::file( $upload, $field );
                if ( ! $file ) { $valid = false; break; }
                $resolved[] = $file['upload']; $native[] = $file['file'];
            }
        }
        $missing = array( 'name' => '', 'type' => '', 'tmp_name' => '', 'error' => $valid ? UPLOAD_ERR_NO_FILE : UPLOAD_ERR_CANT_WRITE, 'size' => 0 );
        if ( ! $native || ! $valid ) { $native = array( $missing ); }
        $input = $native[0];
        if ( $multi ) {
            $input = array();
            foreach ( array( 'name', 'type', 'tmp_name', 'error', 'size' ) as $column ) { $input[ $column ] = array_column( $native, $column ); }
        }
        return array( 'valid' => $valid, 'file' => $input, 'uploads' => $resolved, 'value' => $multi ? $resolved : ( $resolved[0] ?? array() ) );
    }

    private static function file( $upload, $field ) {
        if ( ! is_array( $upload ) || empty( $upload['file'] ) || empty( $upload['url'] ) || ! is_string( $upload['file'] ) || ! is_string( $upload['url'] ) ) { return false; }
        $path = realpath( $upload['file'] );
        if ( ! $path || ! is_file( $path ) || ! is_readable( $path ) || is_link( $upload['file'] ) ) { return false; }
        $path = wp_normalize_path( $path );
        $given = wp_normalize_path( $upload['file'] );
        if ( preg_match( '~(^|/)\.\.?(/|$)~', $given ) || ( isset( $upload['tmp_name'] ) && ! in_array( wp_normalize_path( $upload['tmp_name'] ), array( $given, $path ), true ) ) ) { return false; }
        $current = wp_get_upload_dir();
        $roots = array( array( $current['basedir'], $current['baseurl'] ) );
        // Native WCFF custom_upload_dir maps only this registered field's relative folder.
        $custom = $field['upload_url'] ?? '';
        if ( is_string( $custom ) && $custom && ! preg_match( '~(^[/\\\\]|^[A-Za-z]:|(^|[/\\\\])\.\.?([/\\\\]|$))~', $custom ) ) {
            $roots[] = array( WP_CONTENT_DIR . '/' . $custom, WP_CONTENT_URL . '/' . $custom );
        }
        $allowed = false;
        foreach ( $roots as $root ) {
            if ( empty( $root[0] ) || empty( $root[1] ) ) { continue; }
            $base = realpath( $root[0] );
            if ( ! $base ) { continue; }
            $base = trailingslashit( wp_normalize_path( $base ) );
            if ( strpos( $path, $base ) !== 0 ) { continue; }
            $relative = substr( $path, strlen( $base ) );
            $configured = trailingslashit( wp_normalize_path( $root[0] ) ) . $relative;
            if ( ( $given === $path || $given === $configured ) && $upload['url'] === trailingslashit( $root[1] ) . $relative ) { $allowed = true; break; }
        }
        if ( ! $allowed ) { return false; }
        $name = wp_basename( $path ); $size = filesize( $path );
        $type = wp_check_filetype_and_ext( $path, $name, get_allowed_mime_types() );
        if ( ! $size || ! $type['ext'] || ! $type['type'] ) { return false; }
        $file = array( 'name' => $name, 'type' => $type['type'], 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => $size );
        return array( 'file' => $file, 'upload' => array_merge( array( 'file' => $path, 'url' => $upload['url'] ), $file ) );
    }

    private static function dispatch( $native, $method, $args ) {
        $index = count( self::$contexts ) - 1;
        if ( $index < 0 || self::$contexts[ $index ]['busy'] || (int) ( $args[1] ?? 0 ) !== self::$contexts[ $index ]['product_id'] || ! self::$contexts[ $index ]['files'] ) { return call_user_func_array( $native, $args ); }
        $files = self::$contexts[ $index ]['files'];
        foreach ( $files as $key => $file ) {
            if ( ! array_key_exists( $key, $_REQUEST ) || $_REQUEST[ $key ] !== $file['input'] ) { return call_user_func_array( $native, $args ); }
        }
        if ( 'fields_persister' === $method ) {
            foreach ( $files as $key => $file ) { if ( ! array_key_exists( $key, $args[0] ) || $args[0][ $key ] !== $file['input'] ) { return call_user_func_array( $native, $args ); } }
        }
        self::$contexts[ $index ]['busy'] = true;
        $request = $uploaded = array();
        foreach ( $files as $key => $file ) {
            $request[ $key ] = array( array_key_exists( $key, $_REQUEST ), $_REQUEST[ $key ] ?? null );
            $uploaded[ $key ] = array( array_key_exists( $key, $_FILES ), $_FILES[ $key ] ?? null );
            unset( $_REQUEST[ $key ], $_FILES[ $key ] );
            if ( 'fields_validator' === $method ) { $_FILES[ $key ] = $file['file']; }
        }
        if ( null !== self::$contexts[ $index ]['quantity'] ) {
            $request['quantity'] = array( array_key_exists( 'quantity', $_REQUEST ), $_REQUEST['quantity'] ?? null );
            $_REQUEST['quantity'] = self::$contexts[ $index ]['quantity'];
        }
        $seen = false; $declared = array(); $observer = null;
        if ( 'fields_persister' === $method ) {
            $observer = static function ( $groups, $id ) use ( &$seen, &$declared, $args ) {
                if ( (int) $id === (int) $args[1] || ( ! empty( $args[2] ) && (int) $id === (int) $args[2] ) ) {
                    $seen = true;
                    foreach ( $groups as $group ) { foreach ( $group['fields'] as $field ) { if ( 'file' === $field['type'] ) { $declared[ $field['key'] ?? $field['name'] ] = true; } } }
                }
                return $groups;
            };
            add_filter( 'wcff_fields_for_product', $observer, PHP_INT_MAX, 2 );
        }
        try {
            $data = call_user_func_array( $native, $args );
            if ( 'fields_validator' === $method ) { self::$contexts[ $index ]['validated'] = true === $data; }
            if ( 'fields_persister' === $method ) {
                foreach ( $files as $key => $file ) {
                    unset( $data[ $key ] );
                    $field = $file['field'];
                    if ( $seen && empty( $declared[ $field['key'] ?? $field['name'] ] ) ) { continue; }
                    if ( ! self::$contexts[ $index ]['validated'] || ! $file['valid'] ) { throw new Exception( __( 'Unable to restore the saved product file.', 'ti-woocommerce-wishlist' ) ); }
                    if ( ! $file['uploads'] ) { continue; }
                    $value = array( 'field_key' => $key, 'field_val' => array( 'fname' => $field['key'] ?? $field['name'], 'ftype' => 'file', 'user_val' => wp_json_encode( $file['value'] ), 'fee_rules' => array(), 'pricing_rules' => array(), 'format' => '' ) );
                    if ( has_filter( 'wcff_before_inserting_cart_data' ) ) { $value = apply_filters( 'wcff_before_inserting_cart_data', $field, $value ); }
                    $data[ $value['field_key'] ] = $value['field_val'];
                }
            }
            return $data;
        } finally {
            if ( $observer ) { remove_filter( 'wcff_fields_for_product', $observer, PHP_INT_MAX ); }
            foreach ( $request as $key => $value ) { if ( $value[0] ) { $_REQUEST[ $key ] = $value[1]; } else { unset( $_REQUEST[ $key ] ); } }
            foreach ( $uploaded as $key => $value ) { if ( $value[0] ) { $_FILES[ $key ] = $value[1]; } else { unset( $_FILES[ $key ] ); } }
            self::$contexts[ $index ]['busy'] = false;
        }
    }
}

add_filter( 'woocommerce_add_to_cart_validation', array( 'TInvWL_WCFF_Saved_Files', 'quantity' ), PHP_INT_MIN, 3 );
add_action( 'tinvwl_before_prepare_post', array( 'TInvWL_WCFF_Saved_Files', 'begin' ), 10, 2 );
add_action( 'tinvwl_after_unprepare_post', array( 'TInvWL_WCFF_Saved_Files', 'end' ), PHP_INT_MIN, 4 );

function tinvwl_upload_meta_wc_fields_factory( $file ) {
	if ( isset( $file['name'] ) && is_array( $file['name'] ) ) {
		$uploads = array();
		foreach ( $file['name'] as $index => $name ) {
			if ( '' === $name ) {
				continue;
			}
			$single_file = array();
			foreach ( $file as $field => $values ) {
				if ( is_array( $values ) && array_key_exists( $index, $values ) ) {
					$single_file[ $field ] = $values[ $index ];
				}
			}
			$upload = tinvwl_upload_meta_wc_fields_factory( $single_file );
			if ( false === $upload ) {
				return false;
			}
			$uploads[] = $upload;
		}
		return $uploads;
	}

	$upload = tinvwl_upload_file_wc_fields_factory( $file );
	if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
		return false;
	}
	$file['tmp_name'] = $upload['file'];
	return array_merge( $upload, $file );
}

function tinvwl_upload_file_wc_fields_factory( $file ) {

	if ( ! function_exists( 'wp_handle_upload' ) ) {
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
	}
	$upload = wp_handle_upload(
		$file,
		[
			'test_form' => false,
		]
	);

	return $upload;
}
