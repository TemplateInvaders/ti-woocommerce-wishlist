<?php
/**
 * Admin wishlists page class
 *
 * @since             1.0.0
 * @package           TInvWishlist\Admin
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Admin wishlists page class
 */
class TInvWL_Admin_Wishlist extends TInvWL_Admin_BaseSection {

	/**
	 * Priority for admin menu
	 *
	 * @var integer
	 */
	public $priority = 30;

	/**
	 * Menu array
	 *
	 * @return array
	 */
	function menu() {
		return array(
			'title'      => __( 'Wishlists', 'ti-woocommerce-wishlist' ),
			'method'     => array( $this, '_print_' ),
			'slug'       => 'wishlists',
			'capability' => 'tinvwl_wishlists',
			'roles'      => array( 'administrator', 'shop_manager' ),
		);
	}

	/**
	 * General page wishlists
	 *
	 * @param integer $id Id parameter.
	 * @param string $cat Category parameter.
	 */
	function _print_general( $id = 0, $cat = '' ) {
		$data = array(
			'_header' => __( 'Manage wishlists', 'ti-woocommerce-wishlist' ).'<sup>* '.__( 'premium only', 'ti-woocommerce-wishlist' ).'</sup>',
		);
		$data = apply_filters( 'tinvwl_wishlist_general', $data );
		TInvWL_View::render( 'wishlists', $data );
	}
}
