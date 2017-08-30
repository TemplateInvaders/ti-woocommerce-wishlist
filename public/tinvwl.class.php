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
	public $_n;

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	public $_v;
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
	public static function instance( $plugin_name = TINVWL_PREFIX, $version = TINVWL_VERSION ) {
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
		$this->_n = $plugin_name;
		$this->_v = $version;
		$this->pre_load_function();
	}

	/**
	 * Create all object and shortcode
	 */
	function pre_load_function() {
		add_action( 'init', array( $this, 'apply_rewrite_rules' ), 0 );
		add_action( 'init', array( $this, 'add_rewrite_rules' ), 0 );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'deleted_user', array( $this, 'delete_user_wishlist' ) );

		add_action( 'wp_ajax_nopriv_' . $this->_n . '_css', array( $this, 'dynaminc_css' ) );
		add_action( 'wp_ajax_' . $this->_n . '_css', array( $this, 'dynaminc_css' ) );
		add_action( 'widgets_init', array( $this, 'register_widgets' ) );

		add_filter( 'woocommerce_locate_core_template', array( $this, 'locate_template' ), 10, 3 );
		add_filter( 'woocommerce_locate_template', array( $this, 'locate_template' ), 10, 3 );

		$this->addto		 = TInvWL_Public_AddToWishlist::instance( $this->_n );
		$this->view			 = TInvWL_Public_Wishlist_View::instance( $this->_n );
		$this->cart			 = TInvWL_Public_Cart::instance( $this->_n );
		$this->topwishlist	 = TInvWL_Public_TopWishlist::instance( $this->_n );
	}

	/**
	 * Define hooks
	 */
	function define_hooks() {
		if ( tinv_get_option( 'social', 'facebook' ) || tinv_get_option( 'social', 'google' ) ) {
			add_filter( 'language_attributes', array( $this, 'add_ogp' ) );
			add_action( 'wp_head', array( $this, 'add_meta_tags' ), 0 );
		}

		if ( tinv_get_option( 'general', 'link_in_myaccount' ) ) {
			add_filter( 'woocommerce_account_menu_items', array( $this, 'account_menu_items' ) );
			add_filter( 'woocommerce_get_endpoint_url', array( $this, 'account_menu_endpoint' ), 4, 10 );
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_header' ) );
		add_action( 'wp_login', array( $this, 'transfert_local_to_user' ), 10, 2 );
		add_action( 'user_register', array( $this, 'transfert_local_to_user_register' ) );
		add_action( 'init', array( $this, 'legacy_transfer' ), 90 );

		add_action( $this->_n . '_after_wishlist_table', array( $this, 'wishlist_button_action_before' ), 0 );
		add_action( $this->_n . '_after_wishlist_table', array( $this, 'wishlist_button_action_after' ), 15 );
		add_action( $this->_n . '_after_wishlist_table', array( $this, 'wishlist_button_updcart_before' ), 15 );
		add_action( $this->_n . '_after_wishlist_table', array( $this, 'wishlist_button_action_after' ), 100 );
	}

	/**
	 * Left class button
	 */
	function wishlist_button_action_before() {
		echo '<div class="tinvwl-to-left look_in">';
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
		echo '</div>';
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
		$_core_file = tinv_wishlist_locate_template( $template );
		if ( empty( $_core_file ) ) {
			return $core_file;
		}

		return $_core_file;
	}

	/**
	 * Update rewrite url for wishlist
	 */
	public static function update_rewrite_rules() {
		set_transient( '_tinvwl_rewrite_rules', 1, 30 );
	}

	/**
	 * Apply rewrite url for wishlist
	 */
	function apply_rewrite_rules() {
		if ( ! get_transient( '_tinvwl_rewrite_rules' ) ) {
			return;
		}
		delete_transient( '_tinvwl_rewrite_rules' );
		flush_rewrite_rules();
	}

	/**
	 * Create rewrite url for wishlist
	 */
	function add_rewrite_rules() {
		$id             = tinv_get_option( 'page', 'wishlist' );
		$pages          = array( $id );
		$language_codes = array();
		$languages      = apply_filters( 'wpml_active_languages', array(), array(
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

		$pages = array_filter( $pages );
		if ( ! empty( $pages ) ) {
			foreach ( $pages as $page ) {
				$page      = get_post( $page );
				$page_slug = $page->post_name;

				if ( $language_codes && defined( 'POLYLANG_VERSION' ) ) {
					add_rewrite_rule( '^(' . $language_codes . ')/(([^/]+/)*' . $page_slug . ')/([A-Fa-f0-9]{6})?/page/([0-9]{1,})/{0,1}$', 'index.php?pagename=$matches[2]&tinvwlID=$matches[4]&paged=$matches[5]&lang=$matches[1]', 'top' );
					add_rewrite_rule( '^(' . $language_codes . ')/(([^/]+/)*' . $page_slug . ')/([A-Fa-f0-9]{6})?/{0,1}$', 'index.php?pagename=$matches[2]&tinvwlID=$matches[4]&paged=$matches[5]&lang=$matches[1]', 'top' );
				}

				add_rewrite_rule( '(([^/]+/)*' . $page_slug . ')/([A-Fa-f0-9]{6})?/page/([0-9]{1,})/{0,1}$', 'index.php?pagename=$matches[1]&tinvwlID=$matches[3]&paged=$matches[4]', 'top' );
				add_rewrite_rule( '(([^/]+/)*' . $page_slug . ')/([A-Fa-f0-9]{6})?/{0,1}$', 'index.php?pagename=$matches[1]&tinvwlID=$matches[3]', 'top' );
			}
		}
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

		return $public_var;
	}

	/**
	 * Create social meta tags
	 */
	function add_meta_tags() {
		if ( is_page( apply_filters( 'wpml_object_id', tinv_get_option( 'page', 'wishlist' ), 'page', true ) ) && ( tinv_get_option( 'social', 'facebook' )  || tinv_get_option( 'social', 'google' ) ) ) {
			$wishlist = tinv_wishlist_get();
			if ( 0 < $wishlist['ID'] && 'private' !== $wishlist['status'] ) {
				if ( is_user_logged_in() ) {
					$user		 = get_user_by( 'id', $wishlist['author'] );
					$user_name	 = trim( sprintf( '%s %s', $user->user_firstname, $user->user_lastname ) );
					$user		 = @$user->display_name; // @codingStandardsIgnoreLine Generic.PHP.NoSilencedErrors.Discouraged
				} else {
					$user_name	 = '';
					$user		 = '';
				}

				$wlp      = new TInvWL_Product( $wishlist );
				$products = $wlp->get_wishlist( array(
					'count'    => 1,
					'order_by' => 'date',
					'order'    => 'DESC',
				) );
				$product  = array_shift( $products );
				$image    = '';
				if ( ! empty( $product ) && ! empty( $product['data'] ) ) {
					list( $image, $width, $height, $is_intermediate ) = wp_get_attachment_image_src( $product['data']->get_image_id(), 'full' );
				}

				$meta = array(
					'url'         => tinv_url_wishlist( $wishlist['share_key'] ),
					'type'        => 'product.group',
					'title'       => sprintf( __( '%1$s of %2$s', 'ti-woocommerce-wishlist-premium' ), $wishlist['title'], ( empty( $user_name ) ? $user : $user_name ) ),
					'description' => __( 'Coming soon', 'ti-woocommerce-wishlist-premium' ),
					'image'       => $image,
				);
				if ( tinv_get_option( 'social', 'facebook' ) ) {
					foreach ( $meta as $name => $content ) {
						echo sprintf( '<meta property="og:%s" content="%s" />', esc_attr( $name ), esc_attr( $content ) );
					}
					echo "\n";
				}
				if ( tinv_get_option( 'social', 'google' ) ) {
					unset( $meta['url'], $meta['type'] );
					foreach ( $meta as $name => $content ) {
						if ( 'title' === $name ) {
							$name = 'name';
						}
						echo sprintf( '<meta itemprop="%s" content="%s">', esc_attr( $name ), esc_attr( $content ) );
					}
					echo "\n";
				}
			} // End if().
		} // End if().
	}

	/**
	 * Create ogp namespace
	 *
	 * @param string $text A space-separated list of language attributes.
	 *
	 * @return string
	 */
	function add_ogp( $text ) {
		if ( is_page( apply_filters( 'wpml_object_id', tinv_get_option( 'page', 'wishlist' ), 'page', true ) ) && tinv_get_option( 'social', 'facebook' ) ) {
			$text = 'prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# product: http://ogp.me/ns/product#" itemscope itemtype="http://schema.org/Offer" ' . $text;
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
		wp_enqueue_style( $this->_n, TINVWL_URL . 'asset/css/public.min.css', array(), $this->_v, 'all' );
		if ( ! tinv_get_option( 'style', 'customstyle' ) ) {
			wp_enqueue_style( $this->_n . '-theme', TINVWL_URL . 'asset/css/theme.min.css', array(), $this->_v, 'all' );
		}
		if ( ! tinv_get_option( 'style', 'customstyle' ) || tinv_get_option( 'style_plain', 'allow' ) ) {
			wp_enqueue_style( $this->_n . '-dynaminc', admin_url( 'admin-ajax.php' ) . '?action=' . $this->_n . '_css', array( $this->_n ), $this->_v, 'all' );
		}
		wp_enqueue_style( $this->_n . '-font-awesome', TINVWL_URL . 'asset/css/font-awesome.min.css', array(), $this->_v, 'all' );
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
		header( 'Content-type: text/css; charset=' . get_option( 'blog_charset' ) );
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
			$image_url = TINVWL_URL . 'asset/img/';
			$css       = str_replace( '../img/', $image_url, $css );
			set_transient( TINVWL_PREFIX . '_dynamic_', $css, DAY_IN_SECONDS );
		}
		echo $css; // WPCS: xss ok.
		die();
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
		wp_register_script( $this->_n, TINVWL_URL . 'asset/js/public' . $suffix . '.js', array( 'jquery' ), $this->_v, true );
		wp_localize_script( $this->_n, 'tinvwl_add_to_wishlist', array(
			'text_create'                => __( 'Create New', 'ti-woocommerce-wishlist' ),
			'text_already_in'            => tinv_get_option( 'general', 'text_already_in' ),
			'i18n_make_a_selection_text' => esc_attr__( 'Please select some product options before adding this product to your wishlist.', 'ti-woocommerce-wishlist' ),
		) );
		wp_enqueue_script( $this->_n );
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
	 * @return boolean
	 */
	function transfert_local_to_user( $user_login, $user ) {
		return $this->transfert_local_to_user_register( $user->ID );
	}

	/**
	 * Transfer Cookie Wishlist when register user
	 *
	 * @param integer $user_id New user id.
	 */
	function transfert_local_to_user_register( $user_id ) {
		$wl			 = new TInvWL_Wishlist( $this->_n );
		$wishlist	 = $wl->get_by_sharekey_default();
		if ( ! empty( $wishlist ) ) {
			$wishlist	 = array_shift( $wishlist );
			$wlpl		 = new TInvWL_Product( $wishlist );
			$wl->user = $user_id;
			$_wishlist	 = $wl->get_by_user_default( $user_id );
			if ( empty( $_wishlist ) ) {
				$wishlist['author'] = $user_id;
				unset( $wishlist['title'] );
				$wl->update( $wishlist['ID'], $wishlist );
				$wlp		 = new TInvWL_Product( $wishlist, $this->_n );
				$products	 = $wlp->get_wishlist( array( 'external' => false ) );
				foreach ( $products as $product ) {
					$product['author'] = $user_id;
					$wlp->update( $product );
				}
			} else {
				$_wishlist	 = array_shift( $_wishlist );
				$wlp		 = new TInvWL_Product( $_wishlist, $this->_n );
				$products	 = $wlpl->get_wishlist( array( 'external' => false ) );
				$added = true;
				foreach ( $products as $product ) {
					unset( $product['author'] );
					unset( $product['wishlist_id'] );
					$added = $added && $wlp->add_product( $product );
				}
				if ( $added ) {
					$wlpl->remove_product_from_wl();
					$wl->set_sharekey( $_wishlist['share_key'] );
				}
			}
		}
	}

	/**
	 * Add link to wishlist in WooCommerce My Account page.
	 *
	 * @param array $items Menu items links in my accounts.
	 *
	 * @return array
	 */
	function account_menu_items( $items ) {
		$index_position = apply_filters( $this->_n . '_myaccount_position_wishlist', - 1, $items );
		$items          = array_merge(
			array_slice( $items, 0, $index_position, true ),
			array(
				'tinv_wishlist' => __( 'Wishlist', 'ti-woocommerce-wishlist' ),
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
		if ( 'tinv_wishlist' === $endpoint ) {
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
		$wl        = new TInvWL_Wishlist( $this->_n );
		$wishlists = $wl->get( array(
			'author' => $id,
			'count'  => 9999999,
		) );
		if ( ! empty( $wishlists ) ) {
			foreach ( $wishlists as $wishlist ) {
				$wl->remove( $wishlist['ID'] );
			}
		}
	}

	/**
	 * Export cookies wishlist to database
	 */
	function legacy_transfer() {
		$wlpl		 = TInvWL_Product_Legacy::instance( $this->_n );
		$products	 = $wlpl->get_wishlist( array( 'external' => false ) );
		if ( ! empty( $products ) && is_array( $products ) ) {
			$wl       = new TInvWL_Wishlist( $this->_n );
			$wishlist = $wl->add_user_default();

			$wlp = new TInvWL_Product( $wishlist, $this->_n );

			$added = true;
			foreach ( $products as $product ) {
				unset( $product['author'] );
				if ( ! $wlp->add_product( $product ) ) {
					$added = false;
				}
			}
			if ( $added ) {
				$wlpl->remove_product_from_wl();
			}
		}
	}
}
