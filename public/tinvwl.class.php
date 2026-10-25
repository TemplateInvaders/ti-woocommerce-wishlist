<?php
/**
 * Public pages class
 *
 * @since             1.0.0
 * @package           TInvWishlist\Public
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Public pages class
 */
class TInvWL_Public_TInvWL {

	/**
	 * Plugin name
	 *
	 * @var string
	 */
	public $_name;

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	public $_version;

	/**
	 * Raw rewrites
	 *
	 * @var array
	 */
	public static $rules_raw;

	/**
	 * Instance of TInvWL_Public_AddToWishlist.
	 *
	 * @var TInvWL_Public_AddToWishlist
	 */
	public $addto;

	/**
	 * Instance of TInvWL_Public_Wishlist_View.
	 *
	 * @var TInvWL_Public_Wishlist_View
	 */
	public $view;

	/**
	 * Instance of TInvWL_Public_Wishlist_Ajax.
	 *
	 * @var TInvWL_Public_Wishlist_Ajax
	 */
	public $ajax;

	/**
	 * Instance of TInvWL_Public_Cart.
	 *
	 * @var TInvWL_Public_Cart
	 */
	public $cart;

	/**
	 * Instance of TInvWL_Public_WishlistCounter.
	 *
	 * @var TInvWL_Public_WishlistCounter
	 */
	public $topwishlist;

	/**
	 * This class
	 *
	 * @var \TInvWL_Public_TInvWL
	 */
	protected static $_instance = null;

	/**
	 * Get this class object
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $version Plugin version.
	 *
	 * @return \TInvWL_Public_TInvWL
	 */
	public static function instance( $plugin_name = TINVWL_PREFIX, $version = TINVWL_FVERSION ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $plugin_name, $version );
		}

		return self::$_instance;
	}

	/**
	 * Constructor
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $version Plugin version.
	 */
	function __construct( $plugin_name, $version ) {
		$this->_name    = $plugin_name;
		$this->_version = $version;
		$this->pre_load_function();
	}

	/**
	 * Create all object and shortcode
	 */
	function pre_load_function() {

		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );

		if ( tinv_get_option( 'general', 'my_account_endpoint' ) ) {
			add_action( 'init', array( $this, 'wishlist_endpoint' ) );
			if ( ! is_admin() ) {
				add_filter( 'query_vars', array( $this, 'wishlist_query_vars' ), 0 );
				add_action( 'woocommerce_account_' . tinv_get_option( 'general', 'my_account_endpoint_slug' ) . '_endpoint', array(
					$this,
					'wishlist_content'
				) );
			}
		}

		add_filter( 'rewrite_rules_array', array( $this, 'add_rewrite_rules_raw' ), 9999999 );

		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_filter( 'request', array( $this, 'front_page_query_vars' ) );
		add_action( 'wp', array( $this, 'analytics_referer' ) );
		add_action( 'deleted_user', array( $this, 'delete_user_wishlist' ) );
		// WordPress switches to the departing site before firing this hook.
		add_action( 'remove_user_from_blog', array( $this, 'delete_user_wishlist' ) );
		add_action( 'tinvwl_retry_user_cleanup', array( $this, 'retry_user_cleanup' ) );

		add_action( 'wp_ajax_nopriv_' . $this->_name . '_css', array( $this, 'dynaminc_css' ) );
		add_action( 'wp_ajax_' . $this->_name . '_css', array( $this, 'dynaminc_css' ) );
		add_action( 'widgets_init', array( $this, 'register_widgets' ) );

		add_filter( 'woocommerce_locate_core_template', array( $this, 'locate_template' ), 10, 3 );
		add_filter( 'woocommerce_locate_template', array( $this, 'locate_template' ), 10, 3 );

		$this->addto       = TInvWL_Public_AddToWishlist::instance( $this->_name );
		$this->view        = TInvWL_Public_Wishlist_View::instance( $this->_name );
		$this->ajax        = TInvWL_Public_Wishlist_Ajax::instance( $this->_name );
		$this->cart        = TInvWL_Public_Cart::instance( $this->_name );
		$this->topwishlist = TInvWL_Public_WishlistCounter::instance( $this->_name );
	}

	/**
	 * @param $rules
	 *
	 * @return mixed
	 */
	function add_rewrite_rules_raw( $rules ) {
		if ( is_array( self::$rules_raw ) && tinv_get_option( 'permalinks', 'force' ) ) {
			self::add_rewrite_rules();
			$rules = self::$rules_raw + $rules;
		}

		return $rules;
	}

	/**
	 * Define hooks
	 */
	function define_hooks() {
		if ( tinv_get_option( 'social', 'facebook' ) ) {
			add_filter( 'language_attributes', array( $this, 'add_ogp' ), 100 );
		}

		if ( tinv_get_option( 'general', 'link_in_myaccount' ) || tinv_get_option( 'general', 'my_account_endpoint' ) ) {
			add_filter( 'woocommerce_account_menu_items', array( $this, 'account_menu_items' ) );
			add_filter( 'woocommerce_get_endpoint_url', array( $this, 'account_menu_endpoint' ), 4, 10 );
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_header' ) );
		add_action( 'wp_login', array( $this, 'transfert_local_to_user' ), 10, 2 );
		add_action( 'wp_logout', array( $this, 'logout' ), 10, 2 );
		add_action( 'user_register', array( $this, 'transfert_local_to_user_register' ) );
		add_action( 'init', array( $this, 'legacy_transfer' ), 90 );
		add_action( 'init', array( $this, 'retry_guest_transfer' ), 95 );
		add_action( 'clear_auth_cookie', array( $this, 'set_user_sharekey' ) );

		add_action( 'tinvwl_after_wishlist_table', array( $this, 'wishlist_button_action_before' ), 0 );
		add_action( 'tinvwl_after_wishlist_table', array( $this, 'wishlist_button_action_after' ), 15 );
		add_action( 'tinvwl_after_wishlist_table', array( $this, 'wishlist_button_updcart_before' ), 15 );
		add_action( 'tinvwl_after_wishlist_table', array( $this, 'wishlist_button_action_after' ), 100 );
	}

	/**
	 * Add analytic click from wishlist
	 */
	function analytics_referer() {
		$product_id = absint( get_query_var( 'tiwp' ) );
		if ( empty( $product_id ) ) {
			return false;
		}

		$wlp = new TInvWL_Product( array(), TINVWL_PREFIX, true );

		$items = $wlp->get( array( 'ID' => $product_id ) );
		$item  = array_shift( $items );
		if ( empty( $item ) ) {
			return false;
		}
		if ( empty( $item['data'] ) ) {
			return false;
		}
		$wishlist = tinv_wishlist_get( $item['wishlist_id'] );
		if ( ! $wishlist ) {
			wp_redirect( remove_query_arg( array( 'tiwp', 'tiwps' ) ) );
			return;
		}

		TInvWL_Public_Cart::remember_product_source( $item, $wishlist );

		$wla = new TInvWL_Analytics( $wishlist, $this->_name );
		if ( $wishlist['is_owner'] ) {
			$wla->click_author_product_from_wl( $item['product_id'], $item['variation_id'] );
		} else {
			$wla->click_product_from_wl( $item['product_id'], $item['variation_id'] );
		}
		wp_redirect( remove_query_arg( array( 'tiwp', 'tiwps' ) ) ); // @codingStandardsIgnoreLine WordPress.VIP.RestrictedFunctions.wp_redirect
	}

	/**
	 * Left class button
	 */
	function wishlist_button_action_before() {
		echo '<div class="tinvwl-to-left look_in"><div class="tinvwl-input-group tinvwl-no-full">';
	}

	/**
	 * Right class button
	 */
	function wishlist_button_updcart_before() {
		echo '<div class="tinvwl-to-right look_in">';
	}

	/**
	 * Close class button
	 */
	function wishlist_button_action_after() {
		echo '</div></div>';
	}

	/**
	 * Register Widgets
	 */
	function register_widgets() {
		$paths = glob( TINVWL_PATH . 'public' . DIRECTORY_SEPARATOR . 'widget' . DIRECTORY_SEPARATOR . '*.class.php' );
		foreach ( $paths as $path ) {
			$path = ucfirst( str_replace( '.class.php', '', basename( $path ) ) );
			register_widget( 'TInvWL_Public_Widget_' . $path );
		}
	}

	/**
	 * Overwrites path for email and other template
	 *
	 * @param string $core_file Absolute path.
	 * @param string $template Requered Template file.
	 * @param string $template_base Template path.
	 *
	 * @return string
	 */
	function locate_template( $core_file, $template, $template_base ) {
		$_core_file = tinv_wishlist_locate_template( $template, $template_base );
		if ( empty( $_core_file ) ) {
			return $core_file;
		}

		return $_core_file;
	}

	/**
	 * Update rewrite url for wishlist
	 */
	public static function update_rewrite_rules() {
		wp_schedule_single_event( time(), 'tinvwl_flush_rewrite_rules' );
	}

	/**
	 * Apply rewrite url for wishlist
	 */
	public static function apply_rewrite_rules() {
		self::add_rewrite_rules();
		flush_rewrite_rules();
	}

	/**
	 * Create rewrite url for wishlist
	 */
	public static function add_rewrite_rules() {
		// Refresh persisted rules once so new keys work without saving permalinks.
		if ( 128 !== (int) get_option( 'tinvwl_share_key_bits' ) ) {
			update_option( 'tinvwl_share_key_bits', 128, false );
			add_action( 'wp_loaded', static function () {
				flush_rewrite_rules( false );
			} );
		}
		if ( tinv_get_option( 'general', 'my_account_endpoint' ) ) {
			return;
		}

		$id             = tinv_get_option( 'page', 'wishlist' );
		$pages          = array( $id );
		$language_codes = array();
		if ( function_exists( 'pll_languages_list' ) ) {
			$language_codes = implode( '|', pll_languages_list() );
			$translations   = PLL()->model->post->get_translations( $id );
			$pages          = array_merge( $pages, array_values( $translations ) );
		} else {
			$languages = apply_filters( 'wpml_active_languages', array(), array(
				'skip_missing' => 0,
				'orderby'      => 'code',
			) );
			if ( ! empty( $languages ) ) {
				foreach ( $languages as $l ) {
					$pages[]          = apply_filters( 'wpml_object_id', $id, 'page', true, $l['language_code'] );
					$language_codes[] = $l['language_code'];
				}
				$pages          = array_unique( $pages );
				$language_codes = implode( '|', array_unique( $language_codes ) );
			}
		}

		$pages = array_filter( $pages );
		if ( ! empty( $pages ) ) {
			foreach ( $pages as $page ) {
				$page = get_post( $page );

				if ( ! $page ) {
					continue;
				}

				$page_slug = $page->post_name;

				if ( $language_codes && ( defined( 'POLYLANG_VERSION' ) || defined( 'ICL_PLUGIN_PATH' ) ) ) {
					add_rewrite_rule( '^(' . $language_codes . ')/(([^/]+/)*' . $page_slug . ')/((?:[A-Fa-f0-9]{6}|[A-Fa-f0-9]{32}))?/wl_page/([0-9]{1,})/{0,1}$', 'index.php?pagename=$matches[2]&tinvwlID=$matches[4]&wl_paged=$matches[5]&lang=$matches[1]', 'top' );
					self::$rules_raw[ '^(' . $language_codes . ')/(([^/]+/)*' . $page_slug . ')/((?:[A-Fa-f0-9]{6}|[A-Fa-f0-9]{32}))?/wl_page/([0-9]{1,})/{0,1}$' ] = 'index.php?pagename=$matches[2]&tinvwlID=$matches[4]&wl_paged=$matches[5]&lang=$matches[1]';
					add_rewrite_rule( '^(' . $language_codes . ')/(([^/]+/)*' . $page_slug . ')/((?:[A-Fa-f0-9]{6}|[A-Fa-f0-9]{32}))?/{0,1}$', 'index.php?pagename=$matches[2]&tinvwlID=$matches[4]&lang=$matches[1]', 'top' );
					self::$rules_raw[ '^(' . $language_codes . ')/(([^/]+/)*' . $page_slug . ')/((?:[A-Fa-f0-9]{6}|[A-Fa-f0-9]{32}))?/{0,1}$' ] = 'index.php?pagename=$matches[2]&tinvwlID=$matches[4]&lang=$matches[1]';
				}

				// Wishlist on frontpage.
				$page_on_front = absint( get_option( 'page_on_front' ) );
				if ( $page_on_front && 'page' === get_option( 'show_on_front' ) && $page->ID === $page_on_front ) {
					add_filter( 'redirect_canonical', array(
						'TInvWL_Public_TInvWL',
						'disable_canonical_redirect_for_front_page',
					) );
					// Match the front page and pass item value as a query var.
					add_rewrite_rule( '^((?:[A-Fa-f0-9]{6}|[A-Fa-f0-9]{32}))?/{0,1}$', 'index.php?page_id=' . $page_on_front . '&tinvwlID=$matches[1]', 'top' );
					self::$rules_raw['^((?:[A-Fa-f0-9]{6}|[A-Fa-f0-9]{32}))?/{0,1}$'] = 'index.php?page_id=' . $page_on_front . '&tinvwlID=$matches[1]';
					add_rewrite_rule( '^((?:[A-Fa-f0-9]{6}|[A-Fa-f0-9]{32}))?/wl_page/([0-9]{1,})/{0,1}$', 'index.php?page_id=' . $page_on_front . '&tinvwlID=$matches[1]&wl_paged=$matches[2]', 'top' );
					self::$rules_raw['^((?:[A-Fa-f0-9]{6}|[A-Fa-f0-9]{32}))?/wl_page/([0-9]{1,})/{0,1}$'] = 'index.php?page_id=' . $page_on_front . '&tinvwlID=$matches[1]&wl_paged=$matches[2]';
				}

				add_rewrite_rule( '(([^/]+/)*' . $page_slug . ')/((?:[A-Fa-f0-9]{6}|[A-Fa-f0-9]{32}))?/wl_page/([0-9]{1,})/{0,1}$', 'index.php?pagename=$matches[1]&tinvwlID=$matches[3]&wl_paged=$matches[4]', 'top' );
				self::$rules_raw[ '(([^/]+/)*' . $page_slug . ')/((?:[A-Fa-f0-9]{6}|[A-Fa-f0-9]{32}))?/wl_page/([0-9]{1,})/{0,1}$' ] = 'index.php?pagename=$matches[1]&tinvwlID=$matches[3]&wl_paged=$matches[4]';
				add_rewrite_rule( '(([^/]+/)*' . $page_slug . ')/((?:[A-Fa-f0-9]{6}|[A-Fa-f0-9]{32}))?/{0,1}$', 'index.php?pagename=$matches[1]&tinvwlID=$matches[3]', 'top' );
				self::$rules_raw[ '(([^/]+/)*' . $page_slug . ')/((?:[A-Fa-f0-9]{6}|[A-Fa-f0-9]{32}))?/{0,1}$' ] = 'index.php?pagename=$matches[1]&tinvwlID=$matches[3]';

				// Wishlist on shop page.
				$shop_page_id = wc_get_page_id( 'shop' );
				if ( $shop_page_id && $page->ID === $shop_page_id ) {
					$shop      = get_post( $shop_page_id );
					$shop_slug = $shop->post_name;
					add_rewrite_rule( '(([^/]+/)*' . $shop_slug . ')/((?:[A-Fa-f0-9]{6}|[A-Fa-f0-9]{32}))?/{0,1}$', 'index.php?post_type=product&tinvwlID=$matches[3]', 'top' );
					self::$rules_raw[ '(([^/]+/)*' . $shop_slug . ')/((?:[A-Fa-f0-9]{6}|[A-Fa-f0-9]{32}))?/{0,1}$' ] = 'index.php?post_type=product&tinvwlID=$matches[3]';
					add_rewrite_rule( '(([^/]+/)*' . $shop_slug . ')/((?:[A-Fa-f0-9]{6}|[A-Fa-f0-9]{32}))?/wl_page/([0-9]{1,})/{0,1}$', 'index.php?post_type=product&tinvwlID=$matches[3]&wl_paged=$matches[4]', 'top' );
					self::$rules_raw[ '(([^/]+/)*' . $shop_slug . ')/((?:[A-Fa-f0-9]{6}|[A-Fa-f0-9]{32}))?/wl_page/([0-9]{1,})/{0,1}$' ] = 'index.php?post_type=product&tinvwlID=$matches[3]&wl_paged=$matches[4]';
					add_rewrite_rule( '(([^/]+/)*' . $shop_slug . ')/((?:[A-Fa-f0-9]{6}|[A-Fa-f0-9]{32}))?/page/([0-9]{1,})/{0,1}$', 'index.php?post_type=product&tinvwlID=$matches[3]&paged=$matches[4]', 'top' );
					self::$rules_raw[ '(([^/]+/)*' . $shop_slug . ')/((?:[A-Fa-f0-9]{6}|[A-Fa-f0-9]{32}))?/page/([0-9]{1,})/{0,1}$' ] = 'index.php?post_type=product&tinvwlID=$matches[3]&paged=$matches[4]';
				}
			}
		}
	}

	/**
	 *  Disable the front page redirect.
	 *
	 * @param bool $redirect Allow redirect.
	 *
	 * @return bool
	 */
	public static function disable_canonical_redirect_for_front_page( $redirect ) {
		$page_on_front = absint( get_option( 'page_on_front' ) );
		if ( is_page() && 'page' === get_option( 'show_on_front' ) && $page_on_front ) {
			if ( is_page( $page_on_front ) ) {
				$redirect = false;
			}
		}

		return $redirect;
	}

	/**
	 * Add new POST variable
	 *
	 * @param array $public_var WordPress Public variable.
	 *
	 * @return array
	 */
	function add_query_var( $public_var ) {
		$public_var[] = 'tinvwlID';
		$public_var[] = 'tiws';
		$public_var[] = 'tiwp';
		$public_var[] = 'wl_paged';

		return $public_var;
	}

	/** Keep plugin query parameters on the static front page, rather than the posts index. */
	function front_page_query_vars( $vars ) {
		$front = absint( get_option( 'page_on_front' ) );
		if ( ! $front || 'page' !== get_option( 'show_on_front' )
			|| ! array_intersect( array_keys( $vars ), array( 'tinvwlID', 'wl_paged' ) ) ) {
			return $vars;
		}
		// Explicit pages, searches, archives, endpoints and unmatched paths keep their own routing.
		$allowed = array( 'tinvwlID', 'wl_paged', 'paged', 'page', 'preview', 'cpage', 'embed', 'lang' );
		if ( ! array_diff( array_keys( $vars ), $allowed ) ) {
			$vars['page_id'] = $front;
		}
		return $vars;
	}

	/**
	 * Create ogp namespace
	 *
	 * @param string $text A space-separated list of language attributes.
	 *
	 * @return string
	 */
	function add_ogp( $text ) {
		global $wp_query;
		if ( isset( $wp_query ) && is_page( apply_filters( 'wpml_object_id', tinv_get_option( 'page', 'wishlist' ), 'page', true ) ) ) {
			if ( ! preg_match( '/prefix\=/i', $text ) ) {
				$text .= ' prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# product: http://ogp.me/ns/product#"';
			}
			if ( ! preg_match( '/itemscope/i', $text ) ) {
				$text .= ' itemscope';
			}
			if ( ! preg_match( '/itemtype\=/i', $text ) ) {
				$text .= ' itemtype="http://schema.org/Offer"';
			}
		}

		return $text;
	}

	/**
	 * Check if is plugin page
	 *
	 * @return boolean
	 */
	function is_pluginpage() {
		$pages = tinv_get_option( 'page' );
		$pages = array_filter( $pages );
		foreach ( $pages as $page ) {
			if ( is_page( apply_filters( 'wpml_object_id', $page, 'page', true ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Load style and javascript
	 */
	function enqueue_header() {
		if ( $this->is_pluginpage() ) {
			$this->enqueue_wc_styles();
		}
		$this->enqueue_scripts();
		$this->enqueue_styles();
	}

	/**
	 * Load style
	 */
	function enqueue_styles() {
		if ( apply_filters( 'tinvwl_load_webfont', true ) ) {
			wp_enqueue_style( $this->_name . '-webfont-font', TINVWL_URL . 'assets/fonts/tinvwl-webfont.woff2', array(), 'xu2uyi' );
			wp_enqueue_style( $this->_name . '-webfont', TINVWL_URL . 'assets/css/webfont.min.css', array(), $this->_version, 'all' );
			wp_style_add_data( $this->_name . '-webfont', 'rtl', 'replace' );
			wp_style_add_data( $this->_name . '-webfont', 'suffix', '.min' );
			add_filter( 'style_loader_tag', array( $this, 'font_loader_tag_filter' ), 100, 2 );
		}
		wp_enqueue_style( 'tinvwl', TINVWL_URL . 'assets/css/public.min.css', array(), $this->_version, 'all' );
		wp_style_add_data( 'tinvwl', 'rtl', 'replace' );
		wp_style_add_data( 'tinvwl', 'suffix', '.min' );

		if ( ! tinv_get_option( 'style', 'customstyle' ) ) {
			wp_enqueue_style( 'tinvwl-theme', TINVWL_URL . 'assets/css/theme.min.css', array(), $this->_version, 'all' );
			wp_style_add_data( 'tinvwl-theme', 'rtl', 'replace' );
			wp_style_add_data( 'tinvwl-theme', 'suffix', '.min' );
		}
		if ( ! tinv_get_option( 'style', 'customstyle' ) || ( tinv_get_option( 'style_plain', 'allow' ) && tinv_get_option( 'style_plain', 'css' ) ) ) {
			$newcss = $this->dynaminc_css();
			if ( $newcss ) {
				$name_style = tinv_get_option( 'style', 'customstyle' ) ? 'tinvwl' : 'tinvwl-theme';
				wp_add_inline_style( $name_style, $newcss );
			}
		}
	}

	/* Preload Icons font */
	function font_loader_tag_filter( $html, $handle ) {
		if ( $handle === $this->_name . '-webfont-font' ) {
			$html = str_replace( "type='text/css'", '', $html );

			return str_replace( "rel='stylesheet'", "rel='preload' as='font' type='font/woff2' crossorigin='anonymous'", $html );
		}

		return $html;
	}

	/**
	 * Compress CSS
	 *
	 * @param string $css CSS Content.
	 *
	 * @return string
	 */
	function compress_css( $css ) {
		$css = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', ' ', $css );
		$css = preg_replace( '/(\r|\n|\t| {2,})/', ' ', $css );

		return $css;
	}

	/**
	 * Generate dynaminc css
	 */
	function dynaminc_css() {
		$css = get_transient( TINVWL_PREFIX . '_dynamic_' );
		if ( ! $css ) {
			$css = '';
			if ( ! tinv_get_option( 'style', 'customstyle' ) ) {
				$newcss = tinv_get_option( 'style_options', 'css' );
				if ( $newcss ) {
					$newcss = $this->compress_css( $newcss );
					$css    .= $newcss;
				}
			}
			if ( tinv_get_option( 'style_plain', 'allow' ) ) {
				$newcss = tinv_get_option( 'style_plain', 'css' );
				if ( $newcss ) {
					$newcss = $this->compress_css( $newcss );
					$css    .= $newcss;
				}
			}
			$image_url = TINVWL_URL . 'assets/img/';
			$css       = str_replace( '../img/', $image_url, $css );
			set_transient( TINVWL_PREFIX . '_dynamic_', $css, DAY_IN_SECONDS );
		}

		return $css;
	}

	/**
	 * Add woocommerce style
	 */
	function enqueue_wc_styles() {
		if ( $enqueue_styles = WC_Frontend_Scripts::get_styles() ) {
			foreach ( $enqueue_styles as $handle => $args ) {
				wp_register_style( $handle, $args['src'], $args['deps'], $args['version'], $args['media'] );
				wp_enqueue_style( $handle );
			}
		}
	}

	/**
	 * Load javascript
	 */
	function enqueue_scripts() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_register_script( $this->_name . '-clipboard', TINVWL_URL . 'assets/js/clipboard.min.js', array( 'jquery' ), $this->_version, true );
		wp_register_script( $this->_name, TINVWL_URL . 'assets/js/public' . $suffix . '.js', array(
			'jquery',
			'jquery-blockui',
			'js-cookie',
			apply_filters( 'tinvwl_wc_cart_fragments_enabled', true ) ? 'wc-cart-fragments' : 'jquery',
		), $this->_version, true );

		$args = array(
			'text_create'                => __( 'Create New', 'ti-woocommerce-wishlist' ),
			'text_already_in'            => apply_filters( 'tinvwl_already_in_wishlist_text', tinv_get_option( 'general', 'text_already_in' ) ),
			'simple_flow'                => tinv_get_option( 'general', 'simple_flow' ),
			'hide_zero_counter'          => tinv_get_option( 'topline', 'hide_zero_counter' ),
			'i18n_make_a_selection_text' => esc_attr__( 'Please select some product options before adding this product to your wishlist.', 'ti-woocommerce-wishlist' ),
			'tinvwl_break_submit'        => esc_attr__( 'No items or actions are selected.', 'ti-woocommerce-wishlist' ),
			'tinvwl_clipboard'           => esc_attr__( 'Copied!', 'ti-woocommerce-wishlist' ),
			'allow_parent_variable'      => apply_filters( 'tinvwl_allow_add_parent_variable_product', false ),
			'block_ajax_wishlists_data'  => apply_filters( 'tinvwl_block_ajax_wishlists_data', false ),
			'update_wishlists_data'      => apply_filters( 'tinvwl_update_wishlists_data', false ),
			'hash_key'                   => 'ti_wishlist_data_' . md5( get_current_blog_id() . '_' . get_site_url( get_current_blog_id(), '/' ) . get_template() ),
			'nonce'                      => '', // Table actions receive a session-bound nonce from the uncached preflight.
			'nonce_url'                  => WC_AJAX::get_endpoint( 'tinvwl_request_nonce' ),
			'i18n_ajax_error'            => __( 'Unable to update wishlist. Please try again.', 'ti-woocommerce-wishlist' ),
			'i18n_modal_title'           => __( 'Wishlist', 'ti-woocommerce-wishlist' ),
			'i18n_modal_close'           => __( 'Close', 'ti-woocommerce-wishlist' ),
			'rest_root'                  => esc_url_raw( get_rest_url() ),
			'plugin_url'                 => esc_url_raw( TINVWL_URL ),
			'wc_ajax_url'                => WC_AJAX::get_endpoint( 'tinvwl' ),
			'stats'                      => tinv_get_option( 'general', 'product_stats' ),
			'popup_timer'                => apply_filters( 'tinvwl_popup_close_timer', 6000 ),
		);

		if ( function_exists( 'wpml_get_current_language' ) ) {

			global $sitepress;

			if ( $sitepress && $sitepress instanceof SitePress ) {
				$wpml_settings = $sitepress->get_settings();
				if ( isset( $wpml_settings['custom_posts_sync_option'] ) && isset( $wpml_settings['custom_posts_sync_option']['product'] ) && in_array( $wpml_settings['custom_posts_sync_option']['product'], array(
						1,
						2,
					) ) ) {

					if ( 2 == $wpml_settings['custom_posts_sync_option']['product'] ) {
						$args['wpml_default'] = wpml_get_default_language();
					}
					$args['wpml'] = wpml_get_current_language();
				}
			}
		}

		wp_localize_script( $this->_name, 'tinvwl_add_to_wishlist', $args );

		if ( wp_script_is( 'woocommerce', 'enqueued' ) ) {
			wp_enqueue_script( 'tinvwl' );
		}

		$support_all_products_block = function_exists( 'has_block' ) && has_block( 'woocommerce/all-products' );

		if ( $support_all_products_block ) {
			wp_enqueue_script( $this->_name . '-blocks', TINVWL_URL . 'assets/js/blocks.js', array(
				'wc-blocks-registry',
				'wp-i18n',
				'wp-element'
			), $this->_version, true );
		}
	}

	/**
	 * Load function
	 */
	function load_function() {
		$this->define_hooks();
	}

	/**
	 * Transfer Cookie Wishlist when login user
	 *
	 * @param string $user_login Not used.
	 * @param object $user User object.
	 *
	 * @return void
	 */
	function transfert_local_to_user( $user_login, $user ) {
		return $this->transfert_local_to_user_register( $user->ID );
	}

	function logout( $user_id ) {
//		set_transient( '_tinvwl_update_wishlists_data', '1' );
	}

	/** Retry a captured login transfer without requiring another authentication event. */
	function retry_guest_transfer() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		$key = TInvWL_Wishlist::guest_transfer_pending_key();
		if ( get_user_meta( $user_id, $key, true ) || get_user_meta( $user_id, $key . '_captures', false )
			|| ! empty( $_COOKIE['tinv_wishlist_owner'] ) ) {
			return $this->transfert_local_to_user_register( $user_id );
		}
		return false;
	}

	/**
	 * Transfer Cookie Wishlist when register user
	 *
	 * @param integer $user_id New user id.
	 */
	function transfert_local_to_user_register( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return false;
		}
		$wl          = new TInvWL_Wishlist( $this->_name );
		$pending_key = TInvWL_Wishlist::guest_transfer_pending_key();
		$blog_id     = get_current_blog_id();
		$multisite   = is_multisite();
		$capture_key = $pending_key . '_captures';
		// Each proof is an independent metadata row: concurrent logins must not overwrite one another.
		$wishlists = $wl->get_by_sharekey_default();
		$wishlist = array_shift( $wishlists );
		if ( $wpdb->last_error ) {
			return false;
		}
		if ( $wishlist && empty( $wishlist['author'] ) && $wl->is_owner( $wishlist ) ) {
			$capture = array( 'user_id' => $user_id, 'blog_id' => $blog_id, 'sources' => array(
				array( 'ID' => (int) $wishlist['ID'], 'share_key' => $wishlist['share_key'] ),
			) );
			$captured = get_user_meta( $user_id, $capture_key, false );
			if ( $wpdb->last_error ) {
				return false;
			}
			if ( ! in_array( $capture, $captured, true ) && ! add_user_meta( $user_id, $capture_key, $capture ) ) {
				return false; // Keep the signed browser proof if durable capture could not be written.
			}
		}
		$lock        = 'tinvwl_merge_' . md5( $wpdb->prefix . '|' . $user_id );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) {
			return false;
		}
		$transaction = false;
		$source_lock = null;
		try {
			$pending = get_user_meta( $user_id, $pending_key, true );
			if ( $wpdb->last_error ) {
				return false;
			}
			$sources = array();
			if ( '' !== $pending && ( ! is_array( $pending ) || (int) ( $pending['user_id'] ?? 0 ) !== $user_id
				|| ( $multisite && (int) ( $pending['blog_id'] ?? 0 ) !== $blog_id ) ) ) {
				return false; // Keep mismatched metadata available for recovery; never replay it here.
			}
			$append_sources = static function ( $pending ) use ( &$sources ) {
				$stored = isset( $pending['sources'] ) && is_array( $pending['sources'] ) ? $pending['sources'] : array( $pending );
				foreach ( $stored as $source ) {
					if ( is_array( $source ) && ! empty( $source['ID'] ) && TInvWL_Wishlist::is_valid_share_key( $source['share_key'] ?? '' ) ) {
						$id = absint( $source['ID'] );
						if ( ! isset( $sources[ $id ] ) ) {
							$sources[ $id ] = array( 'ID' => $id, 'share_key' => $source['share_key'] );
						}
					}
				}
			};
			if ( is_array( $pending ) ) {
				$append_sources( $pending );
			}
			$captures = get_user_meta( $user_id, $capture_key, false );
			if ( $wpdb->last_error ) {
				return false;
			}
			foreach ( $captures as $capture ) {
				if ( is_array( $capture ) && (int) ( $capture['user_id'] ?? 0 ) === $user_id
					&& (int) ( $capture['blog_id'] ?? 0 ) === $blog_id ) {
					$append_sources( $capture );
				}
			}
			if ( $multisite ) {
				// The shared legacy record is a read-only archive. Local IDs/keys do not prove its origin.
				$legacy = get_user_meta( $user_id, '_tinvwl_pending_guest_transfer', true );
				if ( is_array( $legacy ) && (int) ( $legacy['user_id'] ?? 0 ) === $user_id
					&& (int) ( $legacy['blog_id'] ?? 0 ) === $blog_id ) {
					$append_sources( $legacy );
				}
			}
			if ( ! $sources ) {
				return false;
			}
			$persist = static function () use ( $user_id, $pending_key, $blog_id, $multisite, &$sources ) {
				if ( ! $sources ) {
					delete_user_meta( $user_id, $pending_key );
					return '' === get_user_meta( $user_id, $pending_key, true );
				}
				$state = array( 'user_id' => $user_id );
				if ( $multisite ) {
					$state['blog_id'] = $blog_id;
				}
				$state['sources'] = $sources;
				update_user_meta( $user_id, $pending_key, $state );
				return get_user_meta( $user_id, $pending_key, true ) === $state;
			};
			if ( ! $persist() ) {
				return false;
			}
			// The account-locked checkpoint now owns these proofs. New concurrent captures stay untouched.
			foreach ( $captures as $capture ) {
				if ( is_array( $capture ) && (int) ( $capture['user_id'] ?? 0 ) === $user_id
					&& (int) ( $capture['blog_id'] ?? 0 ) === $blog_id ) {
					delete_user_meta( $user_id, $capture_key, $capture );
				}
			}
			$lists = $wpdb->prefix . $this->_name . '_lists';
			$items = $wpdb->prefix . $this->_name . '_items';
			$tables = $wpdb->get_results( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name IN (%s, %s)', $lists, $items ), ARRAY_A );
			if ( ! is_array( $tables ) || 2 !== count( $tables ) ) {
				return false;
			}
			foreach ( $tables as $table ) {
				if ( ! in_array( strtolower( $table['Engine'] ), array( 'innodb', 'xtradb' ), true ) ) {
					return false; // Never copy then delete when rollback cannot protect the source.
				}
			}
			$last_key = '';
			foreach ( $sources as $source_id => $pending_source ) {
				$receiver = null;
				$committed_actions = array();
				$source_lock = 'tinvwl_item_' . md5( $items . '|' . $pending_source['ID'] );
				if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $source_lock ) ) ) {
					$source_lock = null;
					continue; // Keep the verified source pending while an ordinary write completes.
				}
				// MySQL and MariaDB reject this noncommitting next-transaction setting inside an active transaction.
				$suppressed = $wpdb->suppress_errors( true );
				try {
					$can_start = $wpdb->query( 'SET TRANSACTION READ WRITE' );
				} finally {
					$wpdb->suppress_errors( $suppressed );
				}
				if ( false === $can_start || false === $wpdb->query( 'START TRANSACTION' ) ) {
					return false; // Leave the caller's transaction and all pending sources intact.
				}
				$transaction = true;
				try {
					$wishlist = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$lists` WHERE ID = %d AND share_key = %s FOR UPDATE", $pending_source['ID'], $pending_source['share_key'] ), ARRAY_A );
					if ( $wpdb->last_error ) {
						throw new RuntimeException( 'Could not read the pending guest wishlist.' );
					}
					if ( ! $wishlist || ( ! empty( $wishlist['author'] ) && (int) $wishlist['author'] !== $user_id ) ) {
						$wpdb->query( 'ROLLBACK' );
						$transaction = false;
						unset( $sources[ $source_id ] );
					} elseif ( (int) $wishlist['author'] === $user_id ) {
						// A prior commit may have succeeded before its pending marker was cleared.
						$wpdb->query( 'ROLLBACK' );
						$transaction = false;
						$last_key = $wishlist['share_key'];
						unset( $sources[ $source_id ] );
					} else {
						$wl->user = $user_id;
						$targets  = $wl->get_by_user_default( $user_id );
						if ( $wpdb->last_error ) {
							throw new RuntimeException( 'Could not read the account wishlist.' );
						}
						$target   = array_shift( $targets );
						if ( ! $target ) {
							// Keep the original adoption order for filters and product-update callbacks.
							$wishlist['author'] = $user_id;
							unset( $wishlist['title'] );
							if ( ! $wl->update( $wishlist['ID'], $wishlist ) ) {
								throw new RuntimeException( 'Could not assign the guest wishlist owner.' );
							}
						}
						$source   = TInvWL_Product::for_storage( $wishlist, $this->_name );
						$receiver = $target ? TInvWL_Product::for_storage( $target, $this->_name ) : $source;
						$receiver->defer_product_actions();
						$offset   = 0;
						do {
							$products = $source->get_wishlist( array( 'external' => false, 'count' => 100, 'offset' => $offset, 'order_by' => 'ID', 'order' => 'ASC' ) );
							if ( $wpdb->last_error ) {
								throw new RuntimeException( 'Could not read guest wishlist items.' );
							}
							foreach ( $products as $product ) {
								if ( ! $receiver->transfer_saved_item( $product ) ) {
									throw new RuntimeException( 'Could not transfer a saved guest wishlist item.' );
								}
								if ( $target && 1 !== $wpdb->delete( $items, array( 'ID' => $product['ID'], 'wishlist_id' => $wishlist['ID'] ) ) ) {
									throw new RuntimeException( 'Could not remove the transferred guest wishlist item.' );
								}
							}
							if ( ! $target ) {
								$offset += count( $products );
							}
						} while ( 100 === count( $products ) );
						$remaining_sql  = "SELECT COUNT(*) FROM `$items` WHERE wishlist_id = %d" . ( $target ? '' : ' AND author <> %d' );
						$remaining_args = $target ? array( $wishlist['ID'] ) : array( $wishlist['ID'], $user_id );
						$remaining = $wpdb->get_var( $wpdb->prepare( $remaining_sql, $remaining_args ) );
						if ( null === $remaining || (int) $remaining > 0 ) {
							throw new RuntimeException( 'Guest wishlist transfer was incomplete.' );
						}
						if ( false === $wpdb->query( 'COMMIT' ) ) {
							throw new RuntimeException( 'Could not commit guest wishlist transfer.' );
						}
						$transaction = false;
						$last_key = $target ? $target['share_key'] : $wishlist['share_key'];
						unset( $sources[ $source_id ] );
						$committed_actions = $receiver->release_product_actions();
					}
				} catch ( Throwable $error ) {
					if ( $transaction ) {
						$wpdb->query( 'ROLLBACK' );
						$transaction = false;
					}
					// A failed source remains pending, but must not block another verified guest session.
					if ( $receiver ) {
						$receiver->release_product_actions(); // Discard actions belonging to rolled-back writes.
					}
				}
				$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $source_lock ) );
				$source_lock = null;
				$persisted = false;
				try {
					$persisted = $persist();
				} finally {
					foreach ( $committed_actions as $action ) {
						try {
							do_action( $action[0], $action[1] );
						} catch ( Throwable $error ) {
							// The data is already committed: a callback failure must not trigger a replay.
							error_log( 'TI Wishlist guest transfer action failed: ' . $error->getMessage() );
						}
					}
				}
				if ( ! $persisted ) {
					return false;
				}
			}
			if ( $last_key ) {
				$wl->set_sharekey( $last_key );
			}
			return empty( $sources );
		} catch ( Throwable $error ) {
			return false;
		} finally {
			if ( $transaction ) {
				$wpdb->query( 'ROLLBACK' );
			}
			if ( $source_lock ) {
				$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $source_lock ) );
			}
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/**
	 * Set the default wishlist key if the user loguot
	 */
	public function set_user_sharekey() {
		$wl       = new TInvWL_Wishlist( $this->_name );
		$wishlist = $wl->get_by_user_default();
		if ( ! empty( $wishlist ) ) {
			$wishlist = array_shift( $wishlist );
			$wl->set_sharekey( $wishlist['share_key'] );
		}
	}


	function wishlist_endpoint() {
		add_rewrite_endpoint( tinv_get_option( 'general', 'my_account_endpoint_slug' ), EP_ROOT | EP_PAGES );
	}

	function wishlist_query_vars( $vars ) {
		$vars[] = tinv_get_option( 'general', 'my_account_endpoint_slug' );

		return $vars;
	}

	function wishlist_content() {
		echo do_shortcode( ' [ti_wishlistsview] ' );
	}

	/**
	 * Add link to wishlist in WooCommerce My Account page.
	 *
	 * @param array $items Menu items links in my accounts.
	 *
	 * @return array
	 */
	function account_menu_items( $items ) {
		$index_position = apply_filters( 'tinvwl_myaccount_position_wishlist', - 1, $items );

		$items = array_merge(
			array_slice( $items, 0, $index_position, true ),
			array(
				tinv_get_option( 'general', 'my_account_endpoint_slug' ) => __( 'Wishlist', 'ti-woocommerce-wishlist' ),
			),
			array_slice( $items, $index_position, null, true )
		);

		return $items;
	}

	/**
	 * Create end point for wishlist url
	 *
	 * @param string $url URL from wishlist.
	 * @param string $endpoint End point name.
	 * @param string $value Not used.
	 * @param string $permalink Not used.
	 *
	 * @return string
	 */
	function account_menu_endpoint( $url, $endpoint, $value, $permalink ) {
		if ( ! tinv_get_option( 'general', 'my_account_endpoint' ) && tinv_get_option( 'general', 'my_account_endpoint_slug' ) === $endpoint ) {
			$url = tinv_url_wishlist_default();
		}

		return $url;
	}

	/**
	 * Remove Wishlist a user when the user is deleted
	 *
	 * @param integer $id Removed userid.
	 */
	function delete_user_wishlist( $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}
		$wl = new TInvWL_Wishlist( $this->_name );
		$wishlists = $wl->get( array( 'author' => $id, 'count' => 100 ) );
		$complete = ! $wpdb->last_error;
		if ( $complete ) {
			foreach ( $wishlists as $wishlist ) {
				if ( ! $wl->remove( $wishlist['ID'], $id ) ) {
					$complete = false;
				}
			}
		}
		$args = array( $id );
		if ( ! $complete || count( $wishlists ) >= 100 ) {
			if ( ! wp_next_scheduled( 'tinvwl_retry_user_cleanup', $args ) ) {
				wp_schedule_single_event( time() + 300, 'tinvwl_retry_user_cleanup', $args );
			}
			return false;
		}
		wp_clear_scheduled_hook( 'tinvwl_retry_user_cleanup', $args );
		return true;
	}

	/** Retry only the removed identity in the current site's native cron context. */
	function retry_user_cleanup( $id ) {
		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}
		$user = get_userdata( $id );
		if ( $user && ( ! is_multisite() || is_user_member_of_blog( $id, get_current_blog_id() ) ) ) {
			return false; // Preserve a restored account or renewed site membership.
		}
		return $this->delete_user_wishlist( $id );
	}

	/**
	 * Export cookies wishlist to database
	 */
	function legacy_transfer() {
		$wlpl     = TInvWL_Product_Legacy::instance( $this->_name );
		$products = $wlpl->get_transfer_products();
		if ( ! empty( $products ) && is_array( $products ) ) {
			$wl       = new TInvWL_Wishlist( $this->_name );
			$wishlist = $wl->add_user_default();
			if ( ! is_array( $wishlist ) || ! $wl->is_owner( $wishlist ) ) {
				return false;
			}

			$wlp = new TInvWL_Product( $wishlist, $this->_name );

			$transferred = array();
			try {
				foreach ( $products as $key => $product ) {
					if ( ! is_array( $product ) ) {
						continue;
					}
					unset( $product['author'] );
					$product['wishlist_id'] = $wishlist['ID'];
					if ( $wlp->add_product( $product ) ) {
						$transferred[] = $key;
					}
				}
			} finally {
				$wlpl->consume_transferred( $transferred );
			}
		}
	}
}
