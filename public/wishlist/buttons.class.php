<?php
/**
 * Action buttons for Wishlist
 *
 * @since             1.0.0
 * @package           TInvWishlist\Public
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Action buttons for Wishlist
 */
class TInvWL_Public_Wishlist_Buttons {

	/**
	 * Plugin name
	 *
	 * @var string
	 */
	static $_n;
	/**
	 * Basic event
	 *
	 * @var string
	 */
	static $event;

	/**
	 * First run function
	 *
	 * @param string $plugin_name Plugin name.
	 */
	public static function init( $plugin_name = TINVWL_PREFIX ) {
		self::$_n = $plugin_name;
		self::$event = 'tinvwl_after_wishlist_table';
		self::htmloutput();
	}

	/**
	 * Defined buttons
	 *
	 * @return array
	 */
	private static function prepare() {
		$buttons = array();
		if ( tinv_get_option( 'table', 'colm_checkbox' ) && tinv_get_option( 'table', 'colm_actions' ) ) {
			$buttons[]	 = array(
				'name'		 => 'product_apply',
				'title'		 => sprintf( __( 'Apply %s', 'ti-woocommerce-wishlist' ), "<span class='tinvwl-mobile'>" . __( 'Action', 'ti-woocommerce-wishlist' ) . '</span>' ),
				'method'	 => array( __CLASS__, 'apply_action' ),
				'before'	 => array( __CLASS__, 'apply_action_before' ),
				'priority'	 => 10,
				'condition'	 => '$a["is_owner"]',
			);
			add_filter( self::$_n . '_prepare_attr__button_product_apply', array( __CLASS__, 'add_break_class_input' ) );
			add_filter( self::$_n . '_prepare_attr__button_product_apply', array( __CLASS__, 'add_break_class_checkbox' ) );
		}
		if ( tinv_get_option( 'table', 'colm_checkbox' ) && tinv_get_option( 'table', 'add_select_to_card' ) ) {
			$buttons[]	 = array(
				'name'		 => 'product_selected',
				'title'		 => tinv_get_option( 'table', 'text_add_select_to_card' ),
				'method'	 => array( __CLASS__, 'apply_action_add_selected' ),
				'priority'	 => 25,
			);
			add_filter( self::$_n . '_prepare_attr__button_product_selected', array( __CLASS__, 'add_break_class_checkbox' ) );
		}
		if ( tinv_get_option( 'table', 'add_all_to_card' ) ) {
			$buttons[] = array(
				'name'		 => 'product_all',
				'title'		 => tinv_get_option( 'table', 'text_add_all_to_card' ),
				'method'	 => array( __CLASS__, 'add_all' ),
				'priority'	 => 30,
			);
			add_filter( self::$_n . '_prepare_attr__button_product_selected', array( __CLASS__, 'class_action' ) );
			add_filter( self::$_n . '_prepare_attr__button_product_all', array( __CLASS__, 'class_action' ) );
		}
		$buttons	 = apply_filters( 'tinvwl_manage_buttons_create', $buttons );
		return $buttons;
	}

	/**
	 * Output buttons
	 */
	public static function htmloutput() {
		$buttons = self::prepare();
		foreach ( $buttons as $button ) {
			self::addbutton( $button );
		}
	}

	/**
	 * Add break class
	 *
	 * @param array $attr Attributes.
	 * @return array
	 */
	public static function add_break_class_input( $attr ) {
		if ( array_key_exists( 'class', $attr ) ) {
			$attr['class'] .= ' tinvwl-break-input';
		} else {
			$attr['class'] = 'tinvwl-break-input';
		}
		return $attr;
	}

	/**
	 * Add break class
	 *
	 * @param array $attr Attributes.
	 * @return array
	 */
	public static function add_break_class_checkbox( $attr ) {
		if ( array_key_exists( 'class', $attr ) ) {
			$attr['class'] .= ' tinvwl-break-checkbox';
		} else {
			$attr['class'] = 'tinvwl-break-checkbox';
		}
		return $attr;
	}

	/**
	 * Create button and action
	 *
	 * @param array $button Structure for button.
	 * @return boolean
	 */
	public static function addbutton( $button ) {
		if ( ! array_key_exists( 'name', $button ) ) {
			return false;
		}
		if ( ! array_key_exists( 'priority', $button ) ) {
			$button['priority'] = 10;
		}
		if ( ! array_key_exists( 'method', $button ) ) {
			$button['method'] = array( __CLASS__, 'null_action' );
		}
		if ( ! array_key_exists( 'event', $button ) ) {
			$button['event'] = self::$event;
		}
		if ( ! array_key_exists( 'condition', $button ) ) {
			$button['condition'] = 'true';
		}
		if ( array_key_exists( 'submit', $button ) ) {
			$button['submit'] = $button['submit'] ? 'submit' : 'button';
		} else {
			$button['submit'] = 'submit';
		}

		if ( array_key_exists( 'before', $button ) ) {
			add_filter( self::$_n . '_before__button_' . $button['name'], $button['before'] );
		}
		if ( array_key_exists( 'after', $button ) ) {
			add_filter( self::$_n . '_after__button_' . $button['name'], $button['after'] );
		}

		add_action( $button['event'], create_function( '$a', 'if (' . $button['condition'] . '){' . __CLASS__ . '::button("' . $button['name'] . '","' . $button['title'] . '","' . $button['submit'] . '");}' ), $button['priority'] ); // @codingStandardsIgnoreLine WordPress.VIP.RestrictedFunctions.create_function
		add_action( 'tinvwl_action_' . $button['name'], $button['method'], 10, 4 );
	}

	/**
	 * Create html button
	 *
	 * @param string  $value Vaule for tinvwl-action.
	 * @param string  $title HTML title for button.
	 * @param string  $submit Type button.
	 * @param boolean $echo Retun or echo.
	 * @return string
	 */
	public static function button( $value, $title, $submit, $echo = true ) {
		$html = apply_filters( self::$_n . '_before__button_' . $value, '' );
		$attr	 = array(
			'type'	 => $submit,
			'class'	 => 'button',
			'name'	 => 'tinvwl-action',
			'value'	 => $value,
		);
		$attr = apply_filters( self::$_n . '_prepare_attr__button_' . $value, $attr );
		foreach ( $attr as $key => &$value ) {
			$value = sprintf( '%s="%s"', $key, esc_attr( $value ) );
		}
		$attr = implode( ' ', $attr );

		$html .= apply_filters( self::$_n . '__button_' . $value, sprintf( '<button %s>%s</button>', $attr, $title ) );
		$html .= apply_filters( self::$_n . '_after__button_' . $value, '' );

		if ( $echo ) {
			echo $html; // WPCS: xss ok.
		} else {
			return $html;
		}
	}

	/**
	 * Default action for button
	 *
	 * @return boolean
	 */
	public static function null_action() {
		return false;
	}

	/**
	 * Add class 'alt' to button
	 *
	 * @param array $attr Attributes for button.
	 * @return array
	 */
	public static function class_action( $attr ) {
		if ( array_key_exists( 'class', $attr ) ) {
			$attr['class'] .= ' alt';
		} else {
			$attr['class'] = 'alt';
		}
		return $attr;
	}

	/**
	 * Apply action for product_all
	 *
	 * @param array   $wishlist Wishlist object.
	 * @param array   $selected Not used.
	 * @param array   $_quantity Not used.
	 * @param boolean $owner Owner this wishlist.
	 * @return boolean
	 */
	public static function add_all( $wishlist, $selected = array(), $_quantity = array(), $owner = false ) {
		$products = self::get_current_products( $wishlist );
		$result = array();
		foreach ( $products as $product ) {
			$product_data = wc_get_product( $product['variation_id'] ? $product['variation_id'] : $product['product_id'] );

			add_filter( 'clean_url', 'tinvwl_clean_url', 10, 2 );
			$redirect_url = $product_data->add_to_cart_url();
			remove_filter( 'clean_url', 'tinvwl_clean_url', 10 );

			if ( apply_filters( 'tinvwl_product_add_to_cart_need_redirect', false, $product_data, $redirect_url, $product ) ) {
				continue;
			}
			$product	 = $product['ID'];
			$quantity	 = array_key_exists( $product, (array) $_quantity ) ? $_quantity[ $product ] : 1;
			$add		 = TInvWL_Public_Cart::add( $wishlist, $product, $quantity );
			if ( $add ) {
				$result = tinv_array_merge( $result, $add );
			}
		}
		if ( ! empty( $result ) ) {
			wc_add_to_cart_message( $result, true );
			return true;
		}
		return false;
	}

	/**
	 * Create select for custom action
	 *
	 * @return string
	 */
	public static function apply_action_before() {
		$options = array(
			''				 => __( 'Actions', 'ti-woocommerce-wishlist' ),
			'add_selected'	 => tinv_get_option( 'product_table', 'text_add_to_card' ),
		);
		$wishlist_curent = tinv_wishlist_get();
		if ( $wishlist_curent['is_owner'] ) {
			$options['remove'] = __( 'Remove', 'ti-woocommerce-wishlist' );
		}
		return TInvWL_Form::_select( 'product_actions', '', array( 'class' => 'tinvwl-break-input-filed' ), $options );
	}

	/**
	 * Apply action for product_actions
	 *
	 * @param array   $wishlist Wishlist object.
	 * @param array   $selected Array selected products.
	 * @param array   $_quantity Array quantity products.
	 * @param boolean $owner Owner this wishlist.
	 * @return boolean
	 */
	public static function apply_action( $wishlist, $selected = array(), $_quantity = array(), $owner = false ) {
		if ( empty( $selected ) || ! is_array( $selected ) ) {
			return false;
		}
		$action = filter_input( INPUT_POST, 'product_actions', FILTER_SANITIZE_STRING );
		switch ( $action ) {
			case 'add_selected':
				self::apply_action_add_selected( $wishlist, $selected, $_quantity, $owner );
				break;
			case 'remove':
				self::apply_action_remove( $wishlist, $selected, $_quantity, $owner );
				break;
		}
	}

	/**
	 * Apply action for product_actions add_selected
	 *
	 * @param array   $wishlist Wishlist object.
	 * @param array   $selected Array selected products.
	 * @param array   $_quantity Array quantity products.
	 * @param boolean $owner Owner this wishlist.
	 * @return boolean
	 */
	public static function apply_action_add_selected( $wishlist, $selected = array(), $_quantity = array(), $owner = false ) {
		if ( ! empty( $selected ) ) {
			$result = array();
			foreach ( $selected as $product ) {
				$wlp = null;
				if ( 0 === $wishlist['ID'] ) {
					$wlp = TInvWL_Product_Local::instance();
				} else {
					$wlp = new TInvWL_Product( $wishlist );
				}
				$_product = $wlp->get_wishlist( array( 'ID' => $product ) );
				$_product = array_shift( $_product );
				if ( ! empty( $_product ) && ! empty( $_product['data'] ) ) {
					add_filter( 'clean_url', 'tinvwl_clean_url', 10, 2 );
					$redirect_url = $_product['data']->add_to_cart_url();
					remove_filter( 'clean_url', 'tinvwl_clean_url', 10 );

					if ( apply_filters( 'tinvwl_product_add_to_cart_need_redirect', false, $_product['data'], $redirect_url, $_product ) ) {
						continue;
					}
				}
				$quantity	 = array_key_exists( $product, (array) $_quantity ) ? $_quantity[ $product ] : 1;
				$add		 = TInvWL_Public_Cart::add( $wishlist, $product, $quantity );
				if ( $add ) {
					$result = tinv_array_merge( $result, $add );
				}
			}
			if ( ! empty( $result ) ) {
				wc_add_to_cart_message( $result, true );
				return true;
			}
		}
		return false;
	}

	/**
	 * Apply action for product_actions remove
	 *
	 * @param array   $wishlist Wishlist object.
	 * @param array   $selected Array selected products.
	 * @param array   $_quantity Not used.
	 * @param boolean $owner Owner this wishlist.
	 * @return boolean
	 */
	public static function apply_action_remove( $wishlist, $selected = array(), $_quantity = array(), $owner = false ) {
		if ( ! $owner ) {
			return false;
		}
		$wlp = null;
		if ( 0 === $wishlist['ID'] ) {
			$wlp = TInvWL_Product_Local::instance( self::$_n );
		} else {
			$wlp = new TInvWL_Product( $wishlist, self::$_n );
		}
		if ( empty( $wlp ) ) {
			return false;
		}

		$products = $wlp->get_wishlist( array(
			'ID'		 => $selected,
			'count'		 => 100,
		) );

		$titles = array();
		foreach ( $products as $product ) {
			if ( $wlp->remove_product_from_wl( $product['wishlist_id'], $product['product_id'], $product['variation_id'], $product['meta'] ) ) {
				$titles[] = sprintf( __( '&ldquo;%s&rdquo;', 'ti-woocommerce-wishlist' ), $product['data']->get_title() );
			}
		}

		if ( ! empty( $titles ) ) {
			wc_add_notice( sprintf( _n( '%s has been successfully removed from wishlist.', '%s have been successfully removed from wishlist.', count( $titles ), 'ti-woocommerce-wishlist' ), wc_format_list_of_items( $titles ) ) );
		}

		if ( ! is_user_logged_in() && ! empty( $titles2 ) ) {
			wp_safe_redirect( tinv_url_wishlist() );
		}

		return true;
	}

	/**
	 * Get product bu wishlist
	 *
	 * @param array $wishlist Wishlist object.
	 * @return array
	 */
	public static function get_current_products( $wishlist = null ) {
		if ( empty( $wishlist ) ) {
			return array();
		}
		$wlp = null;
		if ( 0 === $wishlist['ID'] ) {
			$wlp = TInvWL_Product_Local::instance();
		} else {
			$wlp = new TInvWL_Product( $wishlist );
		}
		if ( empty( $wlp ) ) {
			return array();
		}

		$paged		 = get_query_var( 'paged', 1 );
		$paged		 = 1 < $paged ? $paged : 1;
		$per_page	 = apply_filters( 'tinvwl_wishlist_buttons_per_page', filter_input( INPUT_POST, 'lists_per_page', FILTER_VALIDATE_INT, array(
			'options' => array(
				'default'	 => 10,
				'min_range'	 => 1,
			),
		) ) );

		$product_data = array(
			'count'		 => $per_page,
			'offset'	 => $per_page * ($paged - 1),
			'external'	 => false,
		);

		$product_data	 = apply_filters( 'tinvwl_before_get_current_product', $product_data );
		$products		 = $wlp->get_wishlist( $product_data );
		$products		 = apply_filters( 'tinvwl_after_get_current_product', $products );

		return $products;
	}
}
