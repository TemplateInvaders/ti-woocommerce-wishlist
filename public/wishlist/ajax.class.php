<?php
/**
 * Wishlist table AJAX actions
 *
 * @since             2.0.0
 * @package           TInvWishlist\Public
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Wishlist shortcode
 */
class TInvWL_Public_Wishlist_Ajax {

	/**
	 * Plugin name
	 *
	 * @var string
	 */
	private $_name;

	/**
	 * Current wishlist
	 *
	 * @var array
	 */
	private $current_wishlist;

	/**
	 * This class
	 *
	 * @var \TInvWL_Public_Wishlist_Ajax
	 */
	protected static $_instance = null;

	/**
	 * Get this class object
	 *
	 * @param string $plugin_name Plugin name.
	 *
	 * @return \TInvWL_Public_Wishlist_Ajax
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
		$this->_name = $plugin_name;
		$this->define_hooks();
	}

	/**
	 * Defined shortcode and hooks
	 */
	function define_hooks() {
		add_action( 'wc_ajax_tinvwl', array( $this, 'ajax_action' ) );
	}

	/**
	 * Get current wishlist
	 *
	 * @return array
	 */
	function get_current_wishlist() {
		if ( empty( $this->current_wishlist ) ) {
			$this->current_wishlist = apply_filters( 'tinvwl_get_current_wishlist', tinv_wishlist_get() );
		}

		return $this->current_wishlist;
	}

	function ajax_action() {

		$post = filter_input_array( INPUT_POST, array(
			'tinvwl-security'   => FILTER_SANITIZE_STRING,
			'tinvwl-action'     => FILTER_SANITIZE_STRING,
			'tinvwl-product_id' => FILTER_VALIDATE_INT,
			'tinvwl-paged'      => FILTER_VALIDATE_INT,
		) );

		$wishlist = $this->get_current_wishlist();

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX && $post['tinvwl-security'] && wp_verify_nonce( $post['tinvwl-security'], 'wp_rest' ) && $wishlist && $post['tinvwl-action'] ) {
			$this->wishlist_ajax_actions( $wishlist, $post );
		} else {
			$response['status'] = false;
			$response['msg'][]  = __( 'Something went wrong', 'ti-woocommerce-wishlist' );
			$response['icon']   = $response['status'] ? 'icon_big_heart_check' : 'icon_big_times';
			$response['msg']    = array_unique( $response['msg'] );
			$response['msg']    = implode( '<br>', $response['msg'] );
			if ( ! empty( $response['msg'] ) ) {
				$response['msg'] = tinv_wishlist_template_html( 'ti-addedtowishlist-dialogbox.php', apply_filters( 'tinvwl_addtowishlist_dialog_box', $response, $post ) );
			}
			wp_send_json( $response );
		}
	}

	function wishlist_ajax_actions( $wishlist, $post ) {
		$post['wishlist_qty'] = 1;
		$action               = $post['tinvwl-action'];
		$class                = TInvWL_Public_AddToWishlist::instance();
		$owner                = (bool) $wishlist['is_owner'];

		switch ( $action ) {
			case 'remove':
				if ( ! $wishlist['is_owner'] ) {
					$response['status'] = false;
					$response['msg'][]  = __( 'Something went wrong', 'ti-woocommerce-wishlist' );
					break;
				}
				$product = $post['tinvwl-product_id'];
				if ( 0 === $wishlist['ID'] ) {
					$wlp = TInvWL_Product_Local::instance();
				} else {
					$wlp = new TInvWL_Product( $wishlist );
				}
				if ( empty( $wlp ) ) {
					$response['status'] = false;
					$response['msg'][]  = __( 'Something went wrong', 'ti-woocommerce-wishlist' );
					break;
				}
				$product_data = $wlp->get_wishlist( array( 'ID' => $product ) );
				$product_data = array_shift( $product_data );
				if ( empty( $product_data ) ) {
					$response['status'] = false;
					$response['msg'][]  = __( 'Something went wrong', 'ti-woocommerce-wishlist' );
					break;
				}
				$post['wishlist_pr'] = array( $product );
				$title               = sprintf( __( '&ldquo;%s&rdquo;', 'ti-woocommerce-wishlist' ), is_callable( array(
					$product_data['data'],
					'get_name'
				) ) ? $product_data['data']->get_name() : $product_data['data']->get_title() );

				if ( $wlp->remove( $product_data ) ) {
					$response['status'] = true;
					$response['msg']    = array( sprintf( __( '%s has been removed from wishlist.', 'ti-woocommerce-wishlist' ), $title ) );
				} else {
					$response['status'] = false;
					$response['msg']    = array( sprintf( __( '%s has not been removed from wishlist.', 'ti-woocommerce-wishlist' ), $title ) );
				}

				$response['wishlists_data'] = $class->get_wishlists_data( $wishlist['share_key'] );

				break;
			case 'add_to_cart_single':

				break;

			case 'remove_selected':

				break;
			case 'add_to_cart_selected':

				break;
			case 'add_to_cart_all':

				break;
		}
		$response['icon'] = $response['status'] ? 'icon_big_heart_check' : 'icon_big_times';
		$response['msg']  = array_unique( $response['msg'] );
		$response['msg']  = implode( '<br>', $response['msg'] );
		if ( ! empty( $response['msg'] ) ) {
			$response['msg'] = tinv_wishlist_template_html( 'ti-addedtowishlist-dialogbox.php', apply_filters( 'tinvwl_addtowishlist_dialog_box', $response, $post ) );
		}
		if ( $response['status'] ) {
			$response['content'] = tinvwl_shortcode_view( array( 'paged' => $post['tinvwl-paged'] ) );
		}

		do_action( 'tinvwl_action_' . $action, $wishlist, $post['wishlist_pr'], $post['wishlist_qty'], $owner ); // @codingStandardsIgnoreLine WordPress.NamingConventions.ValidHookName.UseUnderscores

		wp_send_json( $response );
	}
}
