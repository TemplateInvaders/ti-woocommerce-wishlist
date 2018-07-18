<?php
/**
 * WooCommerce Wishlist Plugin.
 * Plugin Name:       WooCommerce Wishlist Plugin
 * Plugin URI:        https://wordpress.org/plugins/ti-woocommerce-wishlist/
 * Description:       Wishlist functionality for your WooCommerce store.
 * Version:           1.8.6
 * Requires at least: 4.5
 * Tested up to: 4.9
 * WC requires at least: 2.6
 * WC tested up to: 3.4
 * Author:            TemplateInvaders
 * Author URI:        https://templateinvaders.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ti-woocommerce-wishlist
 * Domain Path:       /languages
 *
 * @package           TInvWishlist
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

// Define default path.
if ( ! defined( 'TINVWL_URL' ) ) {
	define( 'TINVWL_URL', plugins_url( '/', __FILE__ ) );
}
if ( ! defined( 'TINVWL_PATH' ) ) {
	define( 'TINVWL_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'TINVWL_PREFIX' ) ) {
	define( 'TINVWL_PREFIX', 'tinvwl' );
}

if ( ! defined( 'TINVWL_DOMAIN' ) ) {
	define( 'TINVWL_DOMAIN', 'ti-woocommerce-wishlist' );
}

if ( ! defined( 'TINVWL_FVERSION' ) ) {
	define( 'TINVWL_FVERSION', '1.8.6' );
}

if ( ! defined( 'TINVWL_LOAD_FREE' ) ) {
	define( 'TINVWL_LOAD_FREE', plugin_basename( __FILE__ ) );
}

require_once TINVWL_PATH . 'tinv-wishlists-function.php';
require_once TINVWL_PATH . 'tinv-wishlists-function-integration.php';

if ( ! function_exists( 'activation_tinv_wishlist' ) ) {

	/**
	 * Activation plugin
	 */
	function activation_tinv_wishlist() {
		if ( dependency_tinv_wishlist( false ) ) {
			TInvWL_Activator::activate();
			flush_rewrite_rules();
		}
	}
}

if ( ! function_exists( 'deactivation_tinv_wishlist' ) ) {

	/**
	 * Deactivation plugin
	 */
	function deactivation_tinv_wishlist() {
		flush_rewrite_rules();
	}
}

if ( ! function_exists( 'uninstall_tinv_wishlist' ) ) {

	/**
	 * Uninstall plugin
	 */
	function uninstall_tinv_wishlist() {
		if ( ! defined( 'TINVWL_LOAD_PREMIUM' ) ) {
			TInvWL_Activator::uninstall();
			flush_rewrite_rules();
			wp_clear_scheduled_hook( 'tinvwl_remove_without_author_wishlist' );
		}
	}
}

if ( ! function_exists( 'dependency_tinv_wishlist' ) ) {

	/**
	 * Dependency plugin
	 *
	 * @param boolean $run For run hooks dependency or return error message.
	 *
	 * @return boolean
	 */
	function dependency_tinv_wishlist( $run = true ) {
		$ext = new TInvWL_PluginExtend( null, __FILE__, TINVWL_PREFIX );
		$ext->set_dependency( 'woocommerce/woocommerce.php', 'WooCommerce' )->need();
		if ( $run ) {
			$ext->run();
		}

		return $ext->status_dependency();
	}
}

if ( ! function_exists( 'run_tinv_wishlist' ) ) {

	/**
	 * Run plugin
	 */
	function run_tinv_wishlist() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		if ( defined( 'TINVWL_LOAD_PREMIUM' ) && defined( 'TINVWL_LOAD_FREE' ) ) {
			$redirect = tinv_wishlist_status( plugin_basename( __FILE__ ) );
			if ( $redirect ) {
				header( 'Location: ' . $redirect );
				exit;
			}
		} elseif ( dependency_tinv_wishlist() ) {
			$plugin = new TInvWL();
			$plugin->run();
		}
	}
}

register_activation_hook( __FILE__, 'activation_tinv_wishlist' );
register_deactivation_hook( __FILE__, 'deactivation_tinv_wishlist' );
register_uninstall_hook( __FILE__, 'uninstall_tinv_wishlist' );
add_action( 'plugins_loaded', 'run_tinv_wishlist', 11 );
