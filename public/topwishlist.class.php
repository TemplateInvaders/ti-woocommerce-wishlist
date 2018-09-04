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
	static $_name;
	/**
	 * This class
	 *
	 * @var \TInvWL_Public_TopWishlist
	 */
	protected static $_instance = null;

	/**
	 * Get this class object
	 *
	 * @param string $plugin_name Plugin name.
	 *
	 * @return \TInvWL_Public_TopWishlist
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
		self::$_name = $plugin_name;
		$this->define_hooks();
	}

	/**
	 * Define hooks
	 */
	function define_hooks() {
		add_filter( 'tinvwl_addtowishlist_return_ajax', array( __CLASS__, 'update_widget' ) );
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'update_fragments' ) );
	}

	/**
	 * Output shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 */
	function htmloutput( $atts ) {
		$data = array(
			'icon'         => tinv_get_option( 'topline', 'icon' ),
			'icon_class'   => ( $atts['show_icon'] && tinv_get_option( 'topline', 'icon' ) ) ? 'top_wishlist-' . tinv_get_option( 'topline', 'icon' ) : '',
			'icon_style'   => ( $atts['show_icon'] && tinv_get_option( 'topline', 'icon' ) ) ? esc_attr( 'top_wishlist-' . tinv_get_option( 'topline', 'icon_style' ) ) : '',
			'icon_upload'  => tinv_get_option( 'topline', 'icon_upload' ),
			'text'         => $atts['show_text'] ? $atts['text'] : '',
			'counter'      => $atts['show_counter'],
			'show_counter' => $atts['show_counter'],
		);
		tinv_wishlist_template( 'ti-wishlist-product-counter.php', $data );
	}

	/**
	 * AJAX update elements.
	 *
	 * @param array $data AJAX data.
	 *
	 * @return array
	 */
	public static function update_widget( $data ) {
		$data['fragments'] = self::update_fragments( array() );

		return $data;
	}

	/**
	 * Load fragments for wishlist product counter
	 *
	 * @param array $data Woocommerce Fragments for updateing data.
	 */
	public static function update_fragments( $data = array() ) {
		$data['span.wishlist_products_counter_number']            = sprintf( '<span class="wishlist_products_counter_number">%s</span>', apply_filters( 'tinvwl_wishlist_products_counter', self::counter() ) );
		$data['span.sidr-class-wishlist_products_counter_number'] = sprintf( '<span class="sidr-class-wishlist_products_counter_number">%s</span>', apply_filters( 'tinvwl_wishlist_products_counter', self::counter() ) );

		return $data;
	}

	/**
	 * Get count product in all wishlist
	 *
	 * @return integer
	 */
	public static function counter() {
		global $wpdb;
		$count = 0;
		$wl    = new TInvWL_Wishlist();
		if ( is_user_logged_in() ) {
			$wishlist = $wl->add_user_default();
			$wlp      = new TInvWL_Product();
			$counts   = $wlp->get( array(
				'external'    => false,
				'wishlist_id' => $wishlist['ID'],
				'sql'         => 'SELECT COUNT(`quantity`) AS `quantity` FROM {table} t1 INNER JOIN ' . $wpdb->prefix . 'posts t2 on t1.product_id = t2.ID AND t2.post_status = "publish" WHERE {where} ',
			) );
			$counts   = array_shift( $counts );
			$count    = absint( $counts['quantity'] );
		} else {
			$wishlist = $wl->get_by_sharekey_default();
			if ( ! empty( $wishlist ) ) {
				$wishlist = array_shift( $wishlist );
				$wlp      = new TInvWL_Product( $wishlist );
				$counts   = $wlp->get_wishlist( array(
					'external' => false,
					'sql'      => sprintf( 'SELECT %s(`quantity`) AS `quantity` FROM {table}  t1 INNER JOIN ' . $wpdb->prefix . 'posts t2 on t1.product_id = t2.ID AND t2.post_status = "publish" WHERE {where}', 'COUNT' ),
				) );
				$counts   = array_shift( $counts );
				$count    = absint( $counts['quantity'] );
			}
		}

		return $count;
	}

	/**
	 * Shortcode basic function
	 *
	 * @param array $atts Array parameter from shortcode.
	 *
	 * @return string
	 */
	function shortcode( $atts = array() ) {
		$default = array(
			'show_icon'    => (bool) tinv_get_option( 'topline', 'icon' ),
			'show_text'    => tinv_get_option( 'topline', 'show_text' ),
			'text'         => apply_filters( 'tinvwl-topline-text', tinv_get_option( 'topline', 'text' ) ),
			'show_counter' => 'on',
		);
		$atts    = filter_var_array( shortcode_atts( $default, $atts ), array(
			'show_icon'    => FILTER_VALIDATE_BOOLEAN,
			'show_text'    => FILTER_VALIDATE_BOOLEAN,
			'show_counter' => FILTER_VALIDATE_BOOLEAN,
			'text'         => FILTER_DEFAULT,
		) );
		ob_start();
		$this->htmloutput( $atts );

		return ob_get_clean();
	}
}
