<?php
/**
 * TI WooCommerce Wishlist.
 * Plugin Name:       TI WooCommerce Wishlist
 * Plugin URI:        https://wordpress.org/plugins/ti-woocommerce-wishlist/
 * Description:       Wishlist functionality for your WooCommerce store.
 * Version:           2.9.2
 * Requires at least: 6.1
 * Tested up to: 6.7
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 3.2
 * WC tested up to: 9.4
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
defined( 'ABSPATH' ) || exit;

// Define constants.
defined( 'TINVWL_URL' ) || define( 'TINVWL_URL', plugins_url( '/', __FILE__ ) );
defined( 'TINVWL_PATH' ) || define( 'TINVWL_PATH', plugin_dir_path( __FILE__ ) );
defined( 'TINVWL_PREFIX' ) || define( 'TINVWL_PREFIX', 'tinvwl' );
defined( 'TINVWL_DOMAIN' ) || define( 'TINVWL_DOMAIN', 'ti-woocommerce-wishlist' );
defined( 'TINVWL_FVERSION' ) || define( 'TINVWL_FVERSION', '2.9.2' );
defined( 'TINVWL_LOAD_FREE' ) || define( 'TINVWL_LOAD_FREE', plugin_basename( __FILE__ ) );
defined( 'TINVWL_NAME' ) || define( 'TINVWL_NAME', 'TI WooCommerce Wishlist' );

if ( ! function_exists( 'tinv_array_merge' ) ) {
	/**
	 * Function to merge arrays with replacement options
	 *
	 * @param array $array1 Array.
	 * @param array|null $_ Array.
	 *
	 * @return array
	 */
	function tinv_array_merge( array $array1, array $_ = null ): array {
		$args = func_get_args();
		array_shift( $args );
		foreach ( $args as $array2 ) {
			if ( is_array( $array2 ) ) {
				$array1 = array_merge( $array1, $array2 );
			}
		}

		return $array1;
	}
}

if ( ! function_exists( 'tinv_get_option_defaults' ) ) {
	/**
	 * Extracts default options from settings class.
	 *
	 * @param string $category Name of the category settings.
	 *
	 * @return array Default settings for a given category or all settings.
	 */
	function tinv_get_option_defaults( string $category ): array {
		$dir = TINVWL_PATH . 'admin/settings/';
		if ( ! file_exists( $dir ) || ! is_dir( $dir ) ) {
			return [];
		}

		$files = array_filter( scandir( $dir ), static function ( $file ) {
			return preg_match( '/\.class\.php$/i', $file );
		} );

		$classFiles = array_map( static function ( $value ) {
			return preg_replace( '/\.class\.php$/i', '', $value );
		}, $files );

		$defaults = [];

		foreach ( $classFiles as $file ) {
			$className     = 'TInvWL_Admin_Settings_' . ucfirst( $file );
			$classInstance = $className::instance( TINVWL_PREFIX, TINVWL_FVERSION );

			$classMethods = get_class_methods( $classInstance );

			foreach ( $classMethods as $method ) {
				if ( preg_match( '/_data$/i', $method ) ) {
					$settings = $classInstance->get_defaults( $classInstance->$method() );
					$defaults = tinv_array_merge( $defaults, $settings );
				}
			}
		}

		if ( 'all' === $category ) {
			return $defaults;
		}

		return $defaults[ $category ] ?? [];
	}
}

if ( ! function_exists( 'activation_tinv_wishlist' ) ) {
	/**
	 * Activation plugin
	 */
	function activation_tinv_wishlist(): void {
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
	function deactivation_tinv_wishlist(): void {
		flush_rewrite_rules();
	}
}

if ( ! function_exists( 'uninstall_tinv_wishlist' ) ) {
	/**
	 * Uninstall plugin
	 */
	function uninstall_tinv_wishlist(): void {
		if ( ! defined( 'TINVWL_LOAD_PREMIUM' ) ) {

			require_once TINVWL_PATH . 'tinv-wishlists-function.php';

			if ( tinv_get_option( 'uninstall', 'delete_data' ) ) {
				TInvWL_Activator::uninstall();
			}
			flush_rewrite_rules();
			wp_clear_scheduled_hook( 'tinvwl_remove_without_author_wishlist' );
		}
	}
}

if ( function_exists( 'spl_autoload_register' ) && ! function_exists( 'autoload_tinv_wishlist' ) ) {
	/**
	 * Autoloader class. If no function spl_autoload_register, then all the files will be required
	 *
	 * @param string $_class Required class name.
	 *
	 * @return boolean
	 */
	function autoload_tinv_wishlist( string $_class ): bool {
		$preffix = 'TInvWL';
		$ext     = '.php';
		$class   = explode( '_', $_class );
		$object  = array_shift( $class );
		if ( $preffix !== $object ) {
			return false;
		}
		if ( empty( $class ) ) {
			$class = array( $preffix );
		}
		$basicclass = $class;
		array_unshift( $class, 'includes' );
		$classes = array(
			TINVWL_PATH . strtolower( implode( DIRECTORY_SEPARATOR, $basicclass ) ),
			TINVWL_PATH . strtolower( implode( DIRECTORY_SEPARATOR, $class ) ),
		);

		foreach ( $classes as $class ) {
			foreach ( array( '.class', '.helper' ) as $suffix ) {
				$filename = $class . $suffix . $ext;
				if ( file_exists( $filename ) ) {
					require_once $filename;
				}
			}
		}

		require_once TINVWL_PATH . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

		return false;
	}

	spl_autoload_register( 'autoload_tinv_wishlist' );
}

if ( ! function_exists( 'dependency_tinv_wishlist' ) ) {
	/**
	 * Dependency plugin
	 *
	 * @param boolean $run For run hooks dependency or return error message.
	 *
	 * @return boolean
	 */
	function dependency_tinv_wishlist( bool $run = true ): bool {
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
	function run_tinv_wishlist(): void {
		global $tinvwl_integrations;
		$tinvwl_integrations = [];

		require_once TINVWL_PATH . 'tinv-wishlists-function.php';

		foreach ( glob( TINVWL_PATH . 'integrations' . DIRECTORY_SEPARATOR . '*.php' ) as $file ) {
			require_once $file;
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

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'product_block_editor', __FILE__, true );
	}
} );

register_activation_hook( __FILE__, 'activation_tinv_wishlist' );
register_deactivation_hook( __FILE__, 'deactivation_tinv_wishlist' );
register_uninstall_hook( __FILE__, 'uninstall_tinv_wishlist' );
add_action( 'plugins_loaded', 'run_tinv_wishlist', 20 );
