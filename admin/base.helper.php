<?php
/**
 * Basic admin helper class
 *
 * @package TInvWishlist\Admin\Helper
 * @since 1.0.0
 */

// If this file is called directly, abort.
defined( 'ABSPATH' ) or exit;

/**
 * Basic admin helper class
 */
abstract class TInvWL_Admin_Base {

	/**
	 * Plugin name
	 *
	 * @var string
	 */
	public string $_name;

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	public string $_version;

	/**
	 * Constructor
	 *
	 * @param string $_name Plugin name.
	 * @param string $_version Plugin version.
	 */
	public function __construct( string $_name, string $_version ) {
		$this->_name    = $_name;
		$this->_version = $_version;
		$this->load_function();
	}

	/**
	 * Load function
	 */
	protected function load_function(): void {
		// To be implemented in child classes
	}

	/**
	 * Formatted admin url.
	 *
	 * @param string $page Page title.
	 * @param string $cat Category title.
	 * @param array $arg Arguments array.
	 *
	 * @return string
	 */
	public function admin_url( string $page, string $cat = '', array $arg = [] ): string {
		$protocol = is_ssl() ? 'https' : 'http';
		$glue     = '-';
		$params   = [
			'page' => implode( $glue, array_filter( [ $this->_name, $page ] ) ),
			'cat'  => $cat,
		];
		if ( is_array( $arg ) ) {
			$params = array_merge( $params, $arg );
		}
		$params = array_filter( $params );
		$params = http_build_query( $params );
		if ( is_string( $arg ) ) {
			$params = $params . '&' . $arg;
		}

		return admin_url( sprintf( 'admin.php?%s', $params ), $protocol );
	}

	/**
	 * Basic print admin page. By attributes page and cat, determined sub function for print
	 *
	 */
	public function _print_() {
		$default = 'general';
		$params  = filter_input_array( INPUT_GET, [
			'page' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
			'cat'  => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
			'id'   => FILTER_VALIDATE_INT,
		] );
		extract( $params );

		$glue      = '-';
		$page      = explode( $glue, $page );
		$page_last = array_shift( $page );
		if ( $this->_name != $page_last ) { // WPCS: loose comparison ok.
			return;
		}

		$cat  = empty( $cat ) ? $default : $cat;
		$glue = '_';
		array_push( $page, $cat );
		$cat           = implode( $glue, $page );
		$function_name = __FUNCTION__ . $cat;

		if ( method_exists( $this, $function_name ) && __FUNCTION__ != $function_name ) { // WPCS: loose comparison ok.
			return $this->$function_name();
		} else {
			$function_name = __FUNCTION__ . $default;
			if ( method_exists( $this, $function_name ) ) {
				return $this->$function_name( 0, $cat );
			}
		}
	}
}
