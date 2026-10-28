<?php
/**
 * Main plugin class.
 *
 * @since             1.0.0
 * @package           TInvWishlist
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Class TInvWL
 *
 * This class manages the main functionalities of the plugin.
 */
class TInvWL {
	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	private string $_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private string $_version;

	/**
	 * Admin class instance.
	 *
	 * @var TInvWL_Admin_TInvWL
	 */
	public TInvWL_Admin_TInvWL $object_admin;

	/**
	 * Public class instance.
	 *
	 * @var TInvWL_Public_TInvWL|null
	 */
	public ?TInvWL_Public_TInvWL $object_public = null;

	/**
	 * Array of deprecated hook handlers.
	 *
	 * @var array
	 */
	public array $deprecated_hook_handlers = [];

	/**
	 * TInvWL constructor.
	 * Initializes admin and public classes.
	 */
	public function __construct() {
		$this->_name    = TINVWL_PREFIX;
		$this->_version = TINVWL_FVERSION;

		$this->set_locale();
		$this->define_hooks();
		$this->object_admin = new TInvWL_Admin_TInvWL( $this->_name, $this->_version );

		// Allow to disable wishlist for frontend conditionally. Must be hooked on 'plugins_loaded' action.
		if ( apply_filters( 'tinvwl_load_frontend', true ) ) {
			$this->object_public = TInvWL_Public_TInvWL::instance( $this->_name, $this->_version );
		}
	}

	/**
	 * Run the plugin.
	 */
	public function run(): void {
		if ( is_null( get_option( $this->_name . '_db_ver', null ) ) ) {
			TInvWL_Activator::activate();
		}

		TInvWL_View::_init( $this->_name, $this->_version );
		TInvWL_Activator::maybe_upgrade_charset();
		TInvWL_Form::_init( $this->_name );
		add_action( 'wc_ajax_tinvwl_request_nonce', array( 'TInvWL_Request_Nonce', 'endpoint' ) );

		if ( is_admin() ) {
			if ( current_user_can( 'manage_options' ) ) {
				new TInvWL_WizardSetup( $this->_name, $this->_version );
			}
			new TInvWL_Export( $this->_name, $this->_version );
			TInvWL_Admin_Notices::instance();

			add_action( 'init', array( $this->object_admin, 'load_function' ) );
		} else {
			// Allow to disable wishlist for frontend conditionally. Must be hooked on 'plugins_loaded' action.
			if ( apply_filters( 'tinvwl_load_frontend', true ) && $this->object_public ) {
				$this->object_public->load_function();
			}
		}

		$this->deprecated_hook_handlers['actions'] = new TInvWL_Deprecated_Actions();
		$this->deprecated_hook_handlers['filters'] = new TInvWL_Deprecated_Filters();
		TInvWL_API::init();
	}

	/**
	 * Set the locale for the plugin.
	 */
	private function set_locale(): void {
		// Load translations before init consumers, after WordPress and locale filters are ready.
		if ( ! did_action( 'init' ) ) {
			add_action( 'init', function () {
				$this->set_locale();
			}, 0 );
			return;
		}

		if ( function_exists( 'determine_locale' ) ) {
			$locale = determine_locale();
		} else {
			$locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
		}

		$locale = apply_filters( 'plugin_locale', $locale, TINVWL_DOMAIN );

		$mofile  = sprintf( '%1$s-%2$s.mo', TINVWL_DOMAIN, $locale );
		$mofiles = array(
			WP_LANG_DIR . DIRECTORY_SEPARATOR . basename( TINVWL_PATH ) . DIRECTORY_SEPARATOR . $mofile,
			WP_LANG_DIR . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . $mofile,
			TINVWL_PATH . 'languages' . DIRECTORY_SEPARATOR . $mofile,
		);

		foreach ( $mofiles as $mofile ) {
			if ( file_exists( $mofile ) && load_textdomain( TINVWL_DOMAIN, $mofile ) ) {
				return;
			}
		}

		load_plugin_textdomain( TINVWL_DOMAIN, false, basename( TINVWL_PATH ) . DIRECTORY_SEPARATOR . 'languages' );
	}

	/**
	 * Define hooks for the plugin.
	 */
	public function define_hooks(): void {
		// Woo admin explicitly hides internal item metadata; its underscore prefix alone is insufficient.
		add_filter( 'woocommerce_hidden_order_itemmeta', static function ( $hidden ) {
			$hidden[] = '_tinvwl_cart_item_key';
			return array_values( array_unique( $hidden ) );
		} );
		if ( ! empty( $_COOKIE['tinv_wishlist_owner'] ) ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
			add_action( 'send_headers', 'nocache_headers' );
		}
		add_filter(
			'plugin_action_links_' . plugin_basename( TINVWL_PATH . 'ti-woocommerce-wishlist.php' ),
			[ $this, 'action_links' ]
		);
		add_action( 'after_setup_theme', 'tinvwl_set_utm', 100 );

		if ( apply_filters( 'tinvwl_allow_data_cookies', true ) ) {
			add_action( 'wp_logout', [ $this, 'reset_cookie' ] );
			// Transfer the signed guest list before clearing its credentials.
			add_action( 'wp_login', [ $this, 'reset_cookie' ], 20 );
		}
	}

	/**
	 * Reset cookies sharekey on logout.
	 *
	 * @return void
	 */
	public function reset_cookie(): void {
		global $wpdb;
		$keep_guest = false;
		$wl = new TInvWL_Wishlist();
		$guest = $wl->get_by_sharekey_default()[0] ?? null;
		$keep_guest = ( $guest && empty( $guest['author'] ) && $wl->is_owner( $guest ) )
			|| ( $wpdb->last_error && ! empty( $_COOKIE['tinv_wishlist_owner'] ) );
		// A busy or failed transfer must retain its signed source across login and logout.
		if ( ! $keep_guest ) {
			TInvWL_Request_Nonce::rotate_guest_identity();
			wc_setcookie( 'tinv_wishlistkey', 0, time() - HOUR_IN_SECONDS );
			unset( $_COOKIE['tinv_wishlistkey'] );
			wc_setcookie( 'tinv_wishlist_owner', '', time() - HOUR_IN_SECONDS, is_ssl(), true );
			unset( $_COOKIE['tinv_wishlist_owner'] );
			TInvWL_Wishlist::$default_sharekey = null;
			$GLOBALS['tinvwl_wishlist_sharekey'] = null;
		}
		wc_setcookie( 'tinvwl_wishlists_data_counter', 0, time() - HOUR_IN_SECONDS );
		unset( $_COOKIE['tinvwl_wishlists_data_counter'] );
		wc_setcookie( 'tinvwl_update_data', 1, time() + HOUR_IN_SECONDS );
	}

	/**
	 * Define the action links for the plugin.
	 *
	 * @param array $links Existing action links.
	 *
	 * @return array Modified action links.
	 */
	public function action_links( array $links ): array {
		$plugin_links = [
			'<a href="' . admin_url( 'admin.php?page=tinvwl' ) . '">' . __( 'Settings', 'ti-woocommerce-wishlist' ) . '</a>',
			'<a target="_blank" href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?utm_source=' . TINVWL_UTM_SOURCE . '&utm_campaign=' . TINVWL_UTM_CAMPAIGN . '&utm_medium=' . TINVWL_UTM_MEDIUM . '&utm_content=action_link&partner=' . TINVWL_UTM_SOURCE . '" style="color:#46b450;font-weight:700;">' . __( 'Premium Version', 'ti-woocommerce-wishlist' ) . '</a>'
		];

		return array_merge( $links, $plugin_links );
	}
}
