<?php
/**
 * Update plugin class
 *
 * @since             1.0.0
 * @package           TInvWishlist
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Update plugin class
 */
class TInvWL_Update {

	/**
	 * Version
	 *
	 * @var string
	 */
	public $_v;

	/**
	 * Previous Version
	 *
	 * @var string
	 */
	public $_prev;

	/**
	 * Regular expression for sorting version function
	 *
	 * @var string
	 */
	const REGEXP = '/^up_/i';

	/**
	 * Get update methods and apply
	 *
	 * @param string $version Version.
	 * @param string $previous_version Previous Version.
	 * @return boolean
	 */
	function __construct( $version, $previous_version = 0 ) {
		$lists = get_class_methods( $this );

		$this->_v	 = $version;
		$this->_prev = $previous_version;
		$lists		 = array_filter( $lists, array( $this, 'filter' ) );
		if ( empty( $lists ) ) {
			return false;
		}
		uasort( $lists, array( $this, 'sort' ) );
		foreach ( $lists as $method ) {
			call_user_func( array( $this, $method ), $previous_version );
		}
		return true;
	}

	/**
	 * Filter methods
	 *
	 * @param string $method Method name from this class.
	 * @return boolean
	 */
	public function filter( $method ) {
		if ( ! preg_match( self::REGEXP, $method ) ) {
			return false;
		}
		if ( version_compare( $this->_prev, $this->prepare( $method ), 'ge' ) ) {
			return false;
		}
		return version_compare( $this->_v, $this->prepare( $method ), 'ge' );
	}

	/**
	 * Sort methods
	 *
	 * @param string $method1 Method name first from this class.
	 * @param string $method2 Method name second from this class.
	 * @return type
	 */
	public function sort( $method1, $method2 ) {
		return version_compare( $this->prepare( $method1 ), $this->prepare( $method2 ) );
	}

	/**
	 * Conver method name to version
	 *
	 * @param string $method Method name from this class.
	 * @return string
	 */
	public function prepare( $method ) {
		$method	 = preg_replace( self::REGEXP, '', $method );
		$method	 = str_replace( '_', '.', $method );
		return $method;
	}

	/**
	 * Example of the method updating
	 *
	 * @param string $previous_version Previous Version.
	 */
	function up_0_0_0( $previous_version = 0 ) {

	}

	/**
	 * Set runed wizard
	 *
	 * @param string $previous_version Previous version value.
	 */
	function up_1_1_10_1( $previous_version = 0 ) {
		update_option( 'tinvwl_wizard', true );
	}
}
