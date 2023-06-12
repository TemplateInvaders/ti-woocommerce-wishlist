<?php
/**
 * Basic admin style helper class
 *
 * @package TInvWishlist\Admin\Helper
 * @since 1.0.0
 */

// If this file is called directly, abort.
defined( 'ABSPATH' ) or exit;

/**
 * Basic admin style helper class
 */
abstract class TInvWL_Admin_BaseStyle extends TInvWL_Admin_BaseSection {

	/**
	 * Prepare sections for template attributes
	 *
	 * @return array
	 */
	public function prepare_sections(): array {
		$fields_data = [];
		$fields      = $this->default_style_settings();
		$theme_file  = TINVWL_PATH . implode( DIRECTORY_SEPARATOR, [ 'assets', 'css', 'theme.css' ] );
		if ( file_exists( $theme_file ) ) {
			$fields_data = $this->break_css( file_get_contents( $theme_file ) ); // @codingStandardsIgnoreLine WordPress.VIP.RestrictedFunctions.file_get_contents
		}
		$_fields = $this->prepare_fields( $fields, $fields_data );
		foreach ( $_fields as &$_field ) {
			if ( ! array_key_exists( 'skin', $_field ) ) {
				switch ( $_field['type'] ) {
					case 'group':
					case 'groupHTML':
						$_field['skin'] = 'section-group-style';
						break;
					default:
						$_field['skin'] = 'section-field-style';
						break;
				}
			}
		}

		return $_fields;
	}

	/**
	 * Create section for this settings.
	 *
	 * @return array
	 */
	public function constructor_data(): array {
		return [
			[
				'id'         => 'style',
				'title'      => __( 'Templates', 'ti-woocommerce-wishlist' ),
				'desc'       => '',
				'show_names' => false,
				'fields'     => [
					[
						'type'  => 'checkboxonoff',
						'name'  => 'customstyle',
						'text'  => __( 'Use Theme style', 'ti-woocommerce-wishlist' ),
						'std'   => true,
						'extra' => [ 'tiwl-hide' => '.tinvwl-style-options' ],
						'class' => 'tinvwl-header-row',
					],
				],
			],
			[
				'id'         => 'style_options',
				'title'      => __( 'Template Options', 'ti-woocommerce-wishlist' ),
				'show_names' => true,
				'class'      => 'tinvwl-style-options',
				'fields'     => $this->prepare_sections(),
				'skin'       => 'section-general',
			],
			[
				'id'         => 'style_plain',
				'title'      => __( 'Template Custom CSS', 'ti-woocommerce-wishlist' ),
				'desc'       => '',
				'show_names' => false,
				'fields'     => [
					[
						'type'  => 'checkboxonoff',
						'name'  => 'allow',
						'text'  => __( 'Template Custom CSS', 'ti-woocommerce-wishlist' ),
						'std'   => false,
						'extra' => [ 'tiwl-show' => '.tiwl-style-custom-allow' ],
						'class' => 'tinvwl-header-row',
					],
					[
						'type'  => 'group',
						'id'    => 'custom',
						'class' => 'tiwl-style-custom-allow',
					],
					[
						'type' => 'textarea',
						'name' => 'css',
						'text' => '',
						'std'  => '',
					],
				],
			],
			[
				'id'     => 'save_buttons',
				'class'  => 'only-button',
				'noform' => true,
				'fields' => [
					[
						'type'  => 'button_submit',
						'name'  => 'setting_save',
						'std'   => '<span><i class="ftinvwl ftinvwl-check"></i></span>' . __( 'Save Settings', 'ti-woocommerce-wishlist' ),
						'extra' => [ 'class' => 'tinvwl-btn split status-btn-ok' ],
					],
					[
						'type'  => 'button_submit',
						'name'  => 'setting_reset',
						'std'   => '<span><i class="ftinvwl ftinvwl-times"></i></span>' . __( 'Reset', 'ti-woocommerce-wishlist' ),
						'extra' => [ 'class' => 'tinvwl-btn split status-btn-ok tinvwl-confirm-reset' ],
					],
					[
						'type' => 'button_submit_quick',
						'name' => 'setting_save_quick',
						'std'  => '<span><i class="ftinvwl ftinvwl-floppy-o"></i></span>' . __( 'Save', 'ti-woocommerce-wishlist' ),
					],
				],
			],
		];
	}

	/**
	 * Prepare style fields for sections fields
	 *
	 * @param array $fields Array of fields list.
	 * @param array $data Array of default values for fields.
	 *
	 * @return array
	 */
	public function prepare_fields( array $fields = [], array $data = [] ): array {
		foreach ( $fields as &$field ) {
			if ( ! array_key_exists( 'selector', $field ) || ! array_key_exists( 'element', $field ) ) {
				continue;
			}
			$field['name'] = $this->create_selectorkey( $field['selector'], $field['element'] );
			if ( ! array_key_exists( 'std', $field ) ) {
				$field['std'] = '';
			}
			if ( isset( $data[ $field['selector'] ][ $field['element'] ] ) ) {
				$value = $data[ $field['selector'] ][ $field['element'] ];
				if ( array_key_exists( 'format', (array) $field ) ) {
					$pregx = preg_replace( '/(\[|\]|\\|\/|\^|\$|\%|\.|\||\?|\*|\+|\(|\)|\{|\})/', '\\\${1}', $field['format'] );
					$pregx = str_replace( '\{0\}', '(.*?)', $pregx );
					$pregx = '/^' . $pregx . '$/i';
					if ( preg_match( $pregx, $value, $matches ) ) {
						if ( isset( $matches[1] ) ) {
							$field['std'] = trim( $matches[1] );
							$field['std'] = preg_replace( '/^\.\.\//', TINVWL_URL . 'assets/', $field['std'] );
						}
					}
				} else {
					$field['std'] = $value;
				}
			}
			unset( $field['selector'], $field['element'], $field['format'] );
		}

		return $fields;
	}

	/**
	 * Save value to database
	 *
	 * @param array $data Post section data.
	 *
	 * @return void
	 */
	public function constructor_save( array $data ): void {
		if ( empty( $data ) || ! is_array( $data ) ) {
			return;
		}
		if ( array_key_exists( 'style', (array) $data ) && array_key_exists( 'style_options', (array) $data ) ) {
			if ( false === $data['style']['customstyle'] ) {
				$data['style_options']['css'] = $this->convert_styles( $data['style_options'] );
			}
			delete_transient( $this->_name . '_dynamic_' );
		}
		if ( array_key_exists( 'style_plain', (array) $data ) ) {
			if ( ! $data['style_plain']['allow'] ) {
				$data['style_plain']['css'] = '';
			}
			if ( empty( $data['style_plain']['css'] ) ) {
				$data['style_plain']['allow'] = false;
			}
		}
		if ( filter_input( INPUT_POST, 'save_buttons-setting_reset' ) ) {
			foreach ( array_keys( $data ) as $key ) {
				if ( $key != 'style' ) {
					$data[ $key ] = array();
				}
			}
		}
		parent::constructor_save( $data );
		if ( filter_input( INPUT_POST, 'save_buttons-setting_reset' ) ) {
			tinv_update_option( 'style_options', '', array() );
		}
	}

	/**
	 * Generate fields name for form
	 *
	 * @param string $selector Selector for fields.
	 * @param string $element Attribute name.
	 *
	 * @return string
	 */
	public function create_selectorkey( string $selector, string $element ): string {
		return md5( $selector . '||' . $element );
	}

	/**
	 * Create array of css attributes
	 *
	 * @param string $css CSS content.
	 *
	 * @return array
	 */
	public function break_css( string $css ): array {
		$results = array();
		$css     = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css );
		$css     = preg_replace( '/(\r|\n|\t| {2,})/', '', $css );
		$css     = str_replace( array( '{', '}' ), array( ' { ', ' } ' ), $css );
		preg_match_all( '/(.+?)\s*?\{\s*?(.+?)\s*?\}/', $css, $matches );
		foreach ( array_keys( $matches[0] ) as $i ) {
			foreach ( explode( ';', $matches[2][ $i ] ) as $attr ) {
				if ( strlen( trim( $attr ) ) > 0 ) {
					list( $name, $value ) = explode( ':', $attr );
					$results[ trim( $matches[1][ $i ] ) ][ trim( $name ) ] = trim( $value );
				}
			}
		}

		return $results;
	}

	/**
	 * Convert settings to css
	 *
	 * @param array $style Array of style attributes.
	 *
	 * @return string
	 */
	public function convert_styles( array $style = [] ): string {
		$fields = $this->default_style_settings();
		$styles = array();
		foreach ( $fields as $field ) {
			if ( ! array_key_exists( 'selector', $field ) || ! array_key_exists( 'element', $field ) ) {
				continue;
			}
			$key = $this->create_selectorkey( $field['selector'], $field['element'] );
			if ( array_key_exists( $key, (array) $style ) ) {
				$value = $style[ $key ];
				if ( array_key_exists( 'format', $field ) ) {
					$value = str_replace( '{0}', $value, $field['format'] );
				}
				$styles[ $field['selector'] ][ $field['element'] ] = $value;
			}
		}
		foreach ( $styles as $selector => &$elements ) {
			foreach ( $elements as $key => &$element ) {
				$element = sprintf( '%s:%s;', $key, $element );
			}
			$elements = implode( '', $elements );
			$elements = sprintf( '%s {%s}', $selector, $elements );
		}
		$styles = implode( ' ', $styles );

		return $styles;
	}
}
