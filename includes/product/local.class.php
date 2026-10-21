<?php
/**
 * Local product function class
 *
 * @since             1.5.0
 * @package           TInvWishlist\Products
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Local product function class
 */
class TInvWL_Product_Local extends TInvWL_Product {
	/**
	 * Instance for this class
	 *
	 * @var \TInvWL_Product_Local
	 */
	private static $_instance;
	private static $_public_instance;

	/**
	 * Get this class object
	 *
	 * @param string $plugin_name Plugin name.
	 *
	 * @return \TInvWL_Product_Local
	 */
	public static function instance( $plugin_name = TINVWL_PREFIX, $public_context = false ) {
		if ( $public_context ) {
			if ( is_null( self::$_public_instance ) ) {
				self::$_public_instance = new self( $plugin_name, true );
			}
			return self::$_public_instance;
		}
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $plugin_name );
		}

		return self::$_instance;
	}

	/**
	 * Constructor
	 *
	 * @param string $plugin_name Plugin name.
	 */
	function __construct( $plugin_name = TINVWL_PREFIX, $public_context = false ) {
		$wl       = new TInvWL_Wishlist( $plugin_name );
		$wishlist = $wl->get_default() ?: array();
		parent::__construct( $wishlist, $plugin_name, $public_context );
	}
}
