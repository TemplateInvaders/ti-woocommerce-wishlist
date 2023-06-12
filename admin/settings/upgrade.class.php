<?php
/**
 * Admin settings class
 *
 * @package TInvWishlist\Admin
 * @subpackage Upgrade page
 * @since 1.0.0
 */

// If this file is called directly, abort.
defined( 'ABSPATH' ) or exit;

/**
 * Admin settings class
 */
class TInvWL_Admin_Settings_Upgrade extends TInvWL_Admin_BaseSection {

	/**
	 * Priority for admin menu
	 *
	 * @var int
	 */
	public int $priority = 200;

	/**
	 * This class
	 *
	 * @var TInvWL_Admin_Settings_Upgrade
	 */
	protected static ?self $_instance = null;

	/**
	 * Get this class object
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $plugin_version Plugin version.
	 *
	 * @return TInvWL_Admin_Settings_Upgrade
	 */
	public static function instance( string $plugin_name = TINVWL_PREFIX, string $plugin_version = TINVWL_FVERSION ): self {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $plugin_name, $plugin_version );
		}

		return self::$_instance;
	}

	/**
	 * Menu array
	 *
	 * @return array
	 */
	public function menu(): array {
		return [
			'title'      => __( 'Upgrade', 'ti-woocommerce-wishlist' ),
			'page_title' => __( 'Premium Features', 'ti-woocommerce-wishlist' ),
			'method'     => [ $this, '_print_' ],
			'slug'       => 'upgrade',
			'capability' => 'tinvwl_upgrade',
		];
	}
}
