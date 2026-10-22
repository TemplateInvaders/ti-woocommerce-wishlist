<?php
/**
 * Export/Import plugin settings class
 *
 * @since             1.17.0
 * @package           TInvWishlist
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TInvWL_Export
 *
 * This class handles the exporting and importing of plugin settings.
 */
class TInvWL_Export {
	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	private string $_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private string $_version;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $version Plugin version.
	 */
	public function __construct( string $plugin_name, string $version ) {
		$this->_name    = $plugin_name;
		$this->_version = $version;
		$this->define_hooks();
	}

	/**
	 * Define hooks.
	 */
	private function define_hooks(): void {
		add_action( 'admin_action_tinvwl_export_settings', [ $this, 'export_settings' ] );
		add_action( 'admin_action_tinvwl_import_settings', [ $this, 'import_settings' ] );

		if ( isset( $_REQUEST['error'] ) && isset( $_REQUEST['page'] ) && $_REQUEST['page'] === 'tinvwl-export-import-settings' ) {
			add_action( 'admin_notices', [ $this, 'show_error' ] );
		}
	}

	/**
	 * Show error on the current page.
	 */
	public function show_error(): void {
		printf(
			'<div class="notice notice-error"><p><strong>%s</strong></p></div>',
			sanitize_text_field( $_REQUEST['error'] )
		);
	}

	/**
	 * Get sanitized blog name.
	 *
	 * @return string
	 */
	private function blog_name(): string {
		$name = get_bloginfo( 'name' );

		if ( empty( $name ) ) {
			$name = wp_parse_url( home_url(), PHP_URL_HOST );
			$port = wp_parse_url( home_url(), PHP_URL_PORT );
			if ( $port ) {
				$name .= ':' . $port;
			}
		}

		$name = preg_replace( '/[^A-Za-z0-9 ]/', '', $name );
		$name = preg_replace( '/\s+/', ' ', $name );
		$name = str_replace( ' ', '-', $name );

		return $name;
	}

	/**
	 * Get all plugin settings.
	 *
	 * @return array|bool
	 */
	public function get_settings(): array {
		$settings = array();
		foreach ( $this->settings_schema() as $key => $fields ) {
			$settings[ $key ] = get_option( $key );
		}

		return $settings;
	}

	/**
	 * Use the same section registration filters as the settings UI. A prefix alone
	 * does not make an option importable; extensions must register their fields.
	 *
	 * @return array Registered option names and their field definitions.
	 */
	private function settings_schema(): array {
		$dir = TINVWL_PATH . 'admin/settings/';
		if ( ! is_dir( $dir ) ) {
			return array();
		}
		$schema = array();
		foreach ( scandir( $dir ) as $file ) {
			if ( ! preg_match( '/\.class\.php$/i', $file ) ) {
				continue;
			}
			$class = 'TInvWL_Admin_Settings_' . ucfirst( preg_replace( '/\.class\.php$/i', '', $file ) );
			if ( ! class_exists( $class ) ) {
				continue;
			}
			$options = $class::instance( $this->_name, $this->_version );
			if ( ! method_exists( $options, 'constructor_data' ) ) {
				continue;
			}
			$sections = $options->constructor_data();
			$style_ids = array();
			if ( $options instanceof TInvWL_Admin_BaseStyle ) {
				$style_ids[] = 'style_options';
				if ( method_exists( $options, 'get_templates' ) ) {
					foreach ( array_merge( array( '' ), array_keys( $options->get_templates() ) ) as $template ) {
						foreach ( $options->prepare_sections( $template ) as $style_section ) {
							$sections[] = $style_section;
							$style_ids[] = $style_section['id'];
						}
					}
				}
			}
			$sections = apply_filters( 'tinvwl_prepare_admsections_constructor_data', $sections );
			$sections = apply_filters( 'tinvwl_prepare_admsections', $sections );
			if ( ! is_array( $sections ) ) {
				continue;
			}
			foreach ( $sections as $section ) {
				$id = is_array( $section ) ? ( $section['id'] ?? '' ) : '';
				if ( ! is_string( $id ) || '' === $id || 'save_buttons' === $id ) {
					continue;
				}
				$key = $this->_name . '-' . $id;
				$schema[ $key ] = $schema[ $key ] ?? array();
				if ( in_array( $id, $style_ids, true ) ) {
					// Native style saves also persist the generated CSS, outside UI fields.
					$schema[ $key ]['css'] = array( 'type' => 'textarea', 'std' => '' );
				}
				// Keep old export placeholders for informational/noform sections,
				// but those sections do not register editable fields.
				if ( ! empty( $section['noform'] ) || empty( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
					continue;
				}
				foreach ( $section['fields'] as $field ) {
					$name = is_array( $field ) ? ( $field['name'] ?? '' ) : '';
					if ( is_string( $name ) && '' !== $name ) {
						$schema[ $key ][ $name ] = $field;
					}
				}
			}
		}

		return $schema;
	}

	/** Validate every key/value before the first option is written. */
	private function valid_settings( $settings ): bool {
		if ( ! is_array( $settings ) || empty( $settings ) ) {
			return false;
		}
		$schema = $this->settings_schema();
		foreach ( $settings as $key => $values ) {
			if ( ! is_string( $key ) || ! array_key_exists( $key, $schema ) ) {
				return false;
			}
			// get_option() exported false for missing sections. WordPress may store
			// that sentinel as an empty string after an older import.
			if ( false === $values || '' === $values ) {
				continue;
			}
			if ( ! is_array( $values ) ) {
				return false;
			}
			foreach ( $values as $name => $value ) {
				if ( ! array_key_exists( $name, $schema[ $key ] ) || ! $this->valid_setting_value( $value, $schema[ $key ][ $name ] ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/** Validate shape and declared validators without re-sanitizing exported HTML/CSS. */
	private function valid_setting_value( $value, array $field ): bool {
		$type = $field['type'] ?? 'text';
		// Native form validators can leave unset false/empty values, including
		// multiselects whose declared default is an empty string.
		if ( false === $value || ( '' === $value && empty( $field['std'] ) ) ) {
			return true;
		}
		$validation = $field['validate'] ?? null;
		$flags = is_array( $validation ) ? ( $validation['flags'] ?? 0 ) : 0;
		$array_type = in_array( $type, array( 'multiselect', 'multicheckbox', 'dateperiod', 'timeperiod' ), true );
		$expects_array = $array_type || is_array( $field['std'] ?? null ) || ( $flags & ( FILTER_REQUIRE_ARRAY | FILTER_FORCE_ARRAY ) );
		if ( $expects_array ) {
			if ( ! is_array( $value ) ) {
				return false;
			}
			if ( $array_type ) {
				foreach ( $value as $item ) {
					if ( ! is_scalar( $item ) ) {
						return false;
					}
				}
			}
			return true;
		}
		if ( ! is_scalar( $value ) ) {
			return null === $value && array_key_exists( 'std', $field ) && null === $field['std'];
		}
		if ( null === $validation ) {
			if ( 'checkboxonoff' === $type ) {
				$validation = FILTER_VALIDATE_BOOLEAN;
			} elseif ( in_array( $type, array( 'number', 'numberrange', 'time' ), true ) ) {
				$validation = FILTER_VALIDATE_INT;
			} else {
				return true;
			}
		}
		$filter = is_array( $validation ) ? ( $validation['filter'] ?? FILTER_DEFAULT ) : $validation;
		if ( ! in_array( $filter, array( FILTER_VALIDATE_INT, FILTER_VALIDATE_FLOAT, FILTER_VALIDATE_BOOLEAN, FILTER_VALIDATE_REGEXP, FILTER_VALIDATE_URL, FILTER_VALIDATE_EMAIL, FILTER_VALIDATE_IP, FILTER_VALIDATE_MAC, FILTER_VALIDATE_DOMAIN, FILTER_CALLBACK ), true ) ) {
			return true;
		}
		$args = is_array( $validation ) ? $validation : array();
		unset( $args['filter'] );
		if ( isset( $args['options'] ) && is_array( $args['options'] ) ) {
			unset( $args['options']['default'] );
		}
		$args['flags'] = ( $args['flags'] ?? 0 ) | FILTER_NULL_ON_FAILURE;
		$result = filter_var( $value, $filter, $args );

		return null !== $result && ( FILTER_VALIDATE_BOOLEAN === $filter || false !== $result );
	}

	/** A false native update can mean unchanged data; verify its stored value. */
	private function import_option( string $key, $value ): bool {
		global $wpdb;
		$wpdb->last_error = '';
		$updated = update_option( $key, $value );
		if ( $wpdb->last_error ) {
			return false;
		}
		if ( $updated ) {
			if ( 0 === strpos( $key, $this->_name . '-style' ) ) {
				// A partially successful import must not keep CSS from its previous settings.
				$templates = array( '', tinv_template() );
				$style = TInvWL_Admin_Settings_Style::instance( $this->_name, $this->_version );
				if ( method_exists( $style, 'get_templates' ) ) {
					$templates = array_merge( $templates, array_keys( $style->get_templates() ) );
				}
				foreach ( array_unique( $templates ) as $template ) {
					delete_transient( $this->_name . '_dynamic_' . $template );
				}
				delete_transient( $this->_name . '_dynamicfont' );
			}
			return true; // Preserve values accepted by native sanitizers and save filters.
		}
		$stored = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM `$wpdb->options` WHERE option_name = %s", $key ), ARRAY_A );
		if ( $wpdb->last_error ) {
			return false;
		}
		// An absent section is exported as false; WordPress stores scalar false as ''.
		return null === $stored ? false === $value : (string) $stored['option_value'] === (string) maybe_serialize( $value );
	}

	/** Effective settings used by public wishlist route registration. */
	private function routing_settings(): array {
		return array(
			(int) tinv_get_option( 'page', 'wishlist' ),
			(bool) tinv_get_option( 'general', 'my_account_endpoint' ),
			(string) tinv_get_option( 'general', 'my_account_endpoint_slug' ),
		);
	}

	/**
	 * Allow upload JSON extension.
	 *
	 * @param array $mimes
	 *
	 * @return array
	 */
	public function json_mime_type( array $mimes ): array {
		$mimes['json'] = 'application/json';

		return $mimes;
	}

	/**
	 * Handle import settings upload and updating database.
	 */
	public function import_settings() {

		$nonce_value = $_REQUEST['tinvwl_import_nonce'] ?? '';

		if ( ! is_string( $nonce_value ) || ! wp_verify_nonce( $nonce_value, 'tinvwl_import' ) || ! current_user_can( 'tinvwl_export_import_settings' ) ) {
			$this->exitWithRedirect( __( 'There was an error importing your settings, please try again.', 'ti-woocommerce-wishlist' ) );
		}

		$settings_json = '';
		if ( isset( $_FILES['settings-file'] ) && $_FILES['settings-file']['error'] === 0 ) {
			if ( ! function_exists( 'wp_handle_upload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			$uploadedfile     = $_FILES['settings-file'];
			$upload_overrides = array(
				'test_form' => false,
				'mimes'     => array( 'json' => 'application/json' ),
			);

			add_filter( 'upload_mimes', array( $this, 'json_mime_type' ) );
			$movefile = wp_handle_upload( $uploadedfile, $upload_overrides );
			remove_filter( 'upload_mimes', array( $this, 'json_mime_type' ) );

			if ( $movefile && ! isset( $movefile['error'] ) ) {
				$settings_json = file_get_contents( $movefile['file'] );
				unlink( $movefile['file'] );
			} else {
				$this->exitWithRedirect( $movefile['error'] );
			}
		} elseif ( isset( $_POST['settings-json'] ) && is_string( $_POST['settings-json'] ) && '' !== trim( wp_unslash( $_POST['settings-json'] ) ) ) {
			$settings_json = trim( wp_unslash( $_POST['settings-json'] ) );
		} else {
			$this->exitWithRedirect( __( 'Please upload the TI WooCommerce Wishlist setting file or copy the content.', 'ti-woocommerce-wishlist' ) );
		}

		$settings = json_decode( $settings_json, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! $this->valid_settings( $settings ) ) {
			$this->exitWithRedirect( __( 'The settings JSON is not valid.', 'ti-woocommerce-wishlist' ) );
		}

		$routes_before = $this->routing_settings();
		$failed = false;
		foreach ( $settings as $key => $value ) {
			if ( ! $this->import_option( $key, $value ) ) {
				$failed = true;
			}
		}
		if ( $routes_before !== $this->routing_settings() ) {
			// Let the next request register the imported routes before rebuilding their cache.
			delete_option( 'rewrite_rules' );
		}
		if ( $failed ) {
			$this->exitWithRedirect( __( 'There was an error importing your settings, please try again.', 'ti-woocommerce-wishlist' ) );
		}

		exit( wp_redirect( admin_url( 'admin.php?page=tinvwl' ) ) );
	}

	private function exitWithRedirect( $errorMessage ) {
		exit(
		wp_redirect(
			admin_url(
				'admin.php?page=tinvwl-export-import-settings&error=' .
				rawurlencode( $errorMessage )
			)
		)
		);
	}

	/**
	 * Handle settings export in a JSON file.
	 */
	public function export_settings() {
		$nonce_value = $_REQUEST['tinvwl_import_nonce'] ?? '';

		if ( ! is_string( $nonce_value ) || ! wp_verify_nonce( $nonce_value, 'tinvwl_import' ) || ! current_user_can( 'tinvwl_export_import_settings' ) ) {
			$this->exitWithRedirect( __( 'There was an error exporting your settings, please try again.', 'ti-woocommerce-wishlist' ) );
		}

		header( 'Content-Type: application/json' );
		$name = urlencode( $this->blog_name() );
		header( "Content-Disposition: attachment; filename=ti_woocommerce_wishlist_settings-$name.json" );
		header( 'Pragma: no-cache' );

		$settings = $this->get_settings();

		echo json_encode( $settings );
		exit;
	}
}
