<?php
/**
 * Drop down widget
 *
 * @since             1.4.0
 * @package           TInvWishlist\Public
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Drop down widget
 */
class TInvWL_Public_TopWishlist {

	/**
	 * Plugin name
	 *
	 * @var string
	 */
	static $_n;
	/**
	 * This class
	 *
	 * @var \TInvWL_Public_CartSave
	 */
	protected static $_instance = null;

	/**
	 * Get this class object
	 *
	 * @param string $plugin_name Plugin name.
	 * @return \TInvWL_Public_CartSave
	 */
	public static function instance( $plugin_name = TINVWL_PREFIX ) {
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
	function __construct( $plugin_name ) {
		self::$_n	 = $plugin_name;
		$this->define_hooks();
	}

	/**
	 * Define hooks
	 */
	function define_hooks() {
		add_filter( 'tinvwl_addtowishlist_return_ajax', array( __CLASS__, 'update_widget' ) );
	}

	/**
	 * Output shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 */
	function htmloutput( $atts ) {
		$data = array(
			'icon'			 => tinv_get_option( 'topline', 'icon' ),
			'icon_class'	 => ( $atts['show_icon'] && tinv_get_option( 'topline', 'icon' ) ) ? 'top_wishlist-' . tinv_get_option( 'topline', 'icon' ) : '',
			'icon_style'	 => esc_attr( 'top_wishlist-' . tinv_get_option( 'topline', 'icon_style' ) ),
			'icon_upload'	 => tinv_get_option( 'topline', 'icon_upload' ),
			'text'			 => $atts['show_text'] ? $atts['text'] : '',
			'counter'		 => $atts['show_counter'] ? self::counter() : 0,
			'show_counter'	 => $atts['show_counter'],
		);
		tinv_wishlist_template( 'ti-wishlist-product-counter.php', $data );
	}

	/**
	 * AJAX update elements.
	 *
	 * @param array $data AJAX data.
	 * @return array
	 */
	public static function update_widget( $data ) {
		$data['top_wishlist_counter'] = self::counter();
		return $data;
	}

	/**
	 * Get count product in all wishlist
	 *
	 * @return integer
	 */
	public static function counter() {
		$count	 = 0;
		if ( is_user_logged_in() ) {
			$wlp = new TInvWL_Product();
			$data	 = array(
				'external'	 => false,
				'author'	 => $wlp->user,
				'sql'		 => 'SELECT COUNT(`quantity`) AS `quantity` FROM {table} WHERE {where}',
			);
			$counts	 = $wlp->get( $data );
			$counts	 = array_shift( $counts );
			$count	 = absint( $counts['quantity'] );
		} else {
			$wl			 = new TInvWL_Wishlist();
			$wishlist	 = $wl->get_by_sharekey_default();
			if ( ! empty( $wishlist )  ) {
				$wishlist	 = array_shift( $wishlist );
				$wlp		 = new TInvWL_Product( $wishlist );
				$products	 = $wlp->get_wishlist( array(
					'count'		 => 9999999,
					'external'	 => false,
				) );
				foreach ( $products as $product ) {
					$count++;
				}
			}
		}
		return $count;
	}
	/**
	 * Shortcode basic function
	 *
	 * @param array $atts Array parameter from shortcode.
	 * @return string
	 */
	function shortcode( $atts = array() ) {
		$default = array(
			'show_icon'		 => (bool) tinv_get_option( 'topline', 'icon' ),
			'show_text'		 => tinv_get_option( 'topline', 'show_text' ),
			'text'			 => tinv_get_option( 'topline', 'text' ),
			'show_counter'	 => 'on',
		);
		$atts	 = filter_var_array( shortcode_atts( $default, $atts ), array(
			'show_icon'		 => FILTER_VALIDATE_BOOLEAN,
			'show_text'		 => FILTER_VALIDATE_BOOLEAN,
			'show_counter'	 => FILTER_VALIDATE_BOOLEAN,
			'text'			 => FILTER_DEFAULT,
		) );
		ob_start();
		$this->htmloutput( $atts );
		return ob_get_clean();
	}
}
