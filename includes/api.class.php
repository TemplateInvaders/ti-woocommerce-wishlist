<?php
/**
 * Handles the REST API endpoints of the plugin.
 *
 * @since             1.13.0
 * @package           TInvWishlist
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TInvWL_API
 *
 * This class is responsible for the initialization of the REST API and registration of routes.
 */
class TInvWL_API {
	/**
	 * Initializes the class by adding the necessary filters and actions.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'woocommerce_api_classes', [ self::class, 'includes' ] );
		add_action( 'rest_api_init', [ self::class, 'register_routes' ], 15 );
	}

	/**
	 * Includes the necessary files for the API and adds the Wishlist API class to the list of registered resources.
	 *
	 * @param array $wc_api_classes The list of currently registered WooCommerce API classes.
	 *
	 * @return array Returns the modified list of WooCommerce API classes.
	 */
	public static function includes( array $wc_api_classes ): array {
		if ( ! defined( 'WC_API_REQUEST_VERSION' ) || WC_API_REQUEST_VERSION === 3 ) {
			$wc_api_classes[] = 'TInvWL_Includes_API_Wishlist';
		}

		return $wc_api_classes;
	}

	/**
	 * Registers the routes for the Wishlist REST API.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		global $wp_version;

		if ( version_compare( $wp_version, '4.4', '<' ) || ( ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, '2.6', '<' ) ) ) {
			return;
		}

		$controller = new TInvWL_Includes_API_Wishlist();
		$controller->register_routes();
	}
}
