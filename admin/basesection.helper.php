<?php
/**
 * Basic admin section helper class
 *
 * @package TInvWishlist\Admin\Helper
 * @since 1.0.0
 */

// If this file is called directly, abort.
defined( 'ABSPATH' ) or exit;

/**
 * Basic admin section helper class
 */
abstract class TInvWL_Admin_BaseSection extends TInvWL_Admin_Base {
	/**
	 * Priority for admin menu
	 *
	 * @var int
	 */
	public int $priority = 10;

	/**
	 * Constructor
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $version Plugin version.
	 */
	public function __construct( string $plugin_name, string $version ) {
		$this->_name    = $plugin_name;
		$this->_version = $version;
		$menu           = $this->menu();
		if ( ! empty( $menu ) ) {
			add_filter( 'tinvwl_admin_menu', [ $this, 'adminmenu' ], $this->priority );
		}
		$this->load_function();
	}

	/**
	 * Add item to admin menu
	 *
	 * @param array $data Menu.
	 *
	 * @return array
	 */
	public function adminmenu( array $data ): array {
		if ( ! is_array( $data ) ) {
			$data = [];
		}

		$data[] = $this->menu();

		return $data;
	}

	/**
	 * Menu array
	 */
	abstract public function menu(): array;

	/**
	 * Load function. Default load form for sections
	 */
	public function load_function(): void {
		$this->form();
	}

	/**
	 * General print
	 *
	 * @param int $id Id parameter.
	 * @param string $cat Category parameter.
	 */
	public function _print_general( int $id = 0, string $cat = '' ): void {
		$title  = $this->menu();
		$slug   = $title['slug'];
		$title  = $title['page_title'] ?? $title['title'];
		$data   = [
			'_header' => $title,
		];
		$method = $cat . '_data';
		if ( ! method_exists( $this, $method ) ) {
			$method = 'constructor_data';
		}

		$data = apply_filters( "tinvwl_{$cat}_data", $data );
		if ( method_exists( $this, $method ) ) {
			$sections = apply_filters( 'tinvwl_prepare_admsections_' . $method, $this->$method() );
			$sections = apply_filters( 'tinvwl_prepare_admsections', $sections );
			$view     = new TInvWL_ViewSection( $this->_name, $this->_version );
			$view->load_data( $sections );
			$method = $cat . '_save';
			if ( ! method_exists( $this, $method ) ) {
				$method = 'constructor_save';
			}
			if ( method_exists( $this, $method ) ) {
				$this->$method( apply_filters( 'tinvwl_prepare_admsections_' . $method, $view->post_form() ) );
			}
			$method = $cat . '_load';
			if ( ! method_exists( $this, $method ) ) {
				$method = 'constructor_load';
			}
			if ( method_exists( $this, $method ) ) {
				$view->load_value( apply_filters( 'tinvwl_prepare_admsections_' . $method, $this->$method( $sections ) ) );
			}
			TInvWL_View::render( $view, $view->form_data( $data ) );
		} else {
			TInvWL_View::render( $slug, $data );
		}
	}

	/**
	 * Method for default settings array
	 *
	 * @param array $sections Sections array.
	 *
	 * @return array
	 */
	public function get_defaults( array $sections ): array {
		$defaults = [];
		if ( ! is_array( $sections ) ) {
			return $defaults;
		}
		$sections = apply_filters( 'tinvwl_prepare_admsections', $sections );
		foreach ( $sections as $section ) {
			if ( array_key_exists( 'noform', $section ) && $section['noform'] ) {
				continue;
			}

			if ( array_key_exists( 'fields', $section ) ) {
				$fields = $section['fields'];
			} else {
				continue;
			}
			$id = array_key_exists( 'id', $section ) ? $section['id'] : '';
			if ( ! array_key_exists( $id, $defaults ) ) {
				$defaults[ $id ] = [];
			}
			foreach ( $fields as $field ) {
				$name = array_key_exists( 'name', $field ) ? $field['name'] : '';
				$std  = array_key_exists( 'std', $field ) ? $field['std'] : '';

				$defaults[ $id ][ $name ] = $std;
			}
			if ( array_key_exists( '', $defaults[ $id ] ) ) {
				unset( $defaults[ $id ][''] );
			}
		}

		return $defaults;
	}

	/**
	 * Form for section
	 */
	public function form(): void {
		add_filter( 'tinvwl_section_before', [ $this, 'start_form' ] );
		add_filter( 'tinvwl_section_after', [ $this, 'end_form' ] );
	}

	/**
	 * Form start for section
	 *
	 * @param string|null $content Sections content.
	 *
	 * @return string
	 */
	function start_form( ?string $content = '' ): string {
		$content = $content ?? '';
		$content .= '<form method="POST" autocomplete="off">';

		return $content;
	}

	/**
	 * Form end for section
	 *
	 * @param string $content Sections content.
	 *
	 * @return string
	 */
	public function end_form( string $content = '' ): string {
		$content .= '</form>';

		return $content;
	}

	/**
	 * Load value from database
	 *
	 * @param array $sections Sections array.
	 *
	 * @return array
	 */
	public function constructor_load( array $sections ): array {
		$sections = $this->get_defaults( $sections );
		$sections = array_keys( $sections );
		$data     = [];
		foreach ( $sections as $section ) {
			$data[ $section ] = tinv_get_option( $section );
		}

		return $data;
	}

	/**
	 * Save value to database
	 *
	 * @param array $data Post section data.
	 */
	public function constructor_save( array $data ): void {
		if ( empty( $data ) || ! is_array( $data ) ) {
			return;
		}
		foreach ( $data as $key => $value ) {
			tinv_update_option( $key, '', $value );
		}
	}
}
