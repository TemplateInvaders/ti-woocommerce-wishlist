<?php
/**
 * Admin wishlists page class
 *
 * @package TInvWishlist\Admin
 * @since 2.6.0
 */

// If this file is called directly, abort.
defined( 'ABSPATH' ) or exit;

/**
 * Admin wishlists page class
 */
class TInvWL_Admin_Wishlist extends TInvWL_Admin_BaseSection {
	/**
	 * Priority for admin menu
	 *
	 * @var int
	 */
	public int $priority = 30;

	/**
	 * Returns menu array
	 *
	 * @return array
	 */
	public function menu(): array {
		return [
			'title'      => __( 'Wishlists', 'ti-woocommerce-wishlist' ),
			'method'     => [ $this, '_print_' ],
			'slug'       => 'wishlists',
			'capability' => 'tinvwl_wishlists',
			'roles'      => [ 'administrator', 'shop_manager' ],
		];
	}

	/**
	 * General page wishlists
	 *
	 * @param int $id Id parameter.
	 * @param string $cat Category parameter.
	 */
	public function _print_general( int $id = 0, string $cat = '' ): void {
		$data = [
			'_header' => __( 'Manage wishlists', 'ti-woocommerce-wishlist' ) . '<sup>* ' . __( 'premium only', 'ti-woocommerce-wishlist' ) . '</sup>',
		];

		$data = apply_filters( 'tinvwl_wishlist_general', $data );
		TInvWL_View::render( 'wishlists', $data );
	}
}
