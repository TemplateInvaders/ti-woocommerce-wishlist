<?php
/**
 * Action buttons for Wishlist
 *
 * @since             1.0.0
 * @package           TInvWishlist\Public
 */

defined( 'ABSPATH' ) || exit;

/**
 * Action buttons for Wishlist
 */
class TInvWL_Public_Wishlist_Buttons {

	/**
	 * Plugin name
	 *
	 * @var string
	 */
	private static string $_name;

	/**
	 * Basic event
	 *
	 * @var string
	 */
	private static string $event;

	/**
	 * First run function
	 *
	 * @param string $plugin_name Plugin name.
	 */
	public static function init( string $plugin_name = TINVWL_PREFIX ): void {
		self::$_name = $plugin_name;
		self::$event = 'tinvwl_after_wishlist_table';
		self::htmloutput();
	}

	/**
	 * Define buttons
	 *
	 * @return array
	 */
	private static function prepare(): array {
		if ( function_exists( 'wpm_translate_string' ) ) {
			add_filter( 'tinvwl_add_selected_to_cart_text', 'wpm_translate_string' );
			add_filter( 'tinvwl_add_all_to_cart_text', 'wpm_translate_string' );
		}

		$buttons        = [];
		$isColmCheckbox = tinv_get_option( 'table', 'colm_checkbox' );
		$isColmActions  = tinv_get_option( 'table', 'colm_actions' );

		if ( $isColmCheckbox && $isColmActions ) {
			$buttons[] = [
				'name'      => 'product_apply',
				'title'     => sprintf( __( 'Apply %s', 'ti-woocommerce-wishlist' ), "<span class='tinvwl-mobile'>" . __( 'Action', 'ti-woocommerce-wishlist' ) . '</span>' ),
				'before'    => [ self::class, 'apply_action_before' ],
				'after'     => '</span>',
				'priority'  => 10,
				'condition' => '$a["is_owner"]',
			];
		}

		if ( $isColmCheckbox && tinv_get_option( 'table', 'add_select_to_cart' ) ) {
			$buttons[] = [
				'name'     => 'product_selected',
				'title'    => apply_filters( 'tinvwl_add_selected_to_cart_text', tinv_get_option( 'table', 'text_add_select_to_cart' ) ),
				'priority' => 25,
			];
			add_filter( 'tinvwl_prepare_attr_button_product_selected', [ self::class, 'add_break_class_checkbox' ] );
		}

		if ( tinv_get_option( 'table', 'add_all_to_cart' ) ) {
			$buttons[] = [
				'name'     => 'product_all',
				'title'    => apply_filters( 'tinvwl_add_all_to_cart_text', tinv_get_option( 'table', 'text_add_all_to_cart' ) ),
				'priority' => 30,
			];
			add_filter( 'tinvwl_prepare_attr_button_product_selected', [ self::class, 'class_action' ] );
			add_filter( 'tinvwl_prepare_attr_button_product_all', [ self::class, 'class_action' ] );
		}

		return apply_filters( 'tinvwl_manage_buttons_create', $buttons );
	}


	/**
	 * Output buttons
	 */
	public static function htmloutput(): void {
		$buttons = self::prepare();
		foreach ( $buttons as $button ) {
			self::addbutton( $button );
		}
	}

	/**
	 * Create button and action
	 *
	 * @param array $button Structure for button.
	 *
	 * @return void
	 */
	public static function addbutton( array $button ): void {
		if ( ! array_key_exists( 'name', $button ) ) {
			return;
		}

		$button['priority']  = $button['priority'] ?? 10;
		$button['method']    = $button['method'] ?? [ self::class, 'null_action' ];
		$button['event']     = $button['event'] ?? self::$event;
		$button['condition'] = $button['condition'] ?? 'true';
		$button['submit']    = $button['submit'] ?? 'submit';

		if ( array_key_exists( 'before', $button ) ) {
			add_filter( 'tinvwl_before__button_' . $button['name'], $button['before'] );
		}
		if ( array_key_exists( 'after', $button ) ) {
			add_filter( 'tinvwl_after__button_' . $button['name'], $button['after'] );
		}

		add_action( $button['event'], function () use ( $button ) {
			if ( $button['condition'] ) {
				self::button( $button['name'], __( $button['title'] ), $button['submit'] );
			}
		}, $button['priority'] );

		add_action( 'tinvwl_action_' . $button['name'], $button['method'], 10, 4 );
	}

	/**
	 * Create html button
	 *
	 * @param string $value Value for tinvwl-action.
	 * @param string $title HTML title for button.
	 * @param string $submit Type button.
	 * @param boolean $echo Return or echo.
	 *
	 * @return string
	 */
	public static function button( string $value, string $title, string $submit, bool $echo = true ): string {
		$html = apply_filters( 'tinvwl_before__button_' . $value, '' );

		$attr = [
			'type'  => $submit,
			'class' => 'button',
			'name'  => 'tinvwl-action-' . $value,
			'value' => $value,
			'title' => esc_attr( wp_strip_all_tags( $title ) ),
		];

		$attr = apply_filters( 'tinvwl_prepare_attr__button_' . $value, $attr );

		array_walk( $attr, function ( &$value, $key ) {
			$value = sprintf( '%s="%s"', $key, esc_attr( $value ) );
		} );

		$attrStr = implode( ' ', $attr );

		$html .= apply_filters( 'tinvwl_button_' . $value, sprintf( '<button %s>%s</button>', $attrStr, $title ) );
		$html .= apply_filters( 'tinvwl_after_button_' . $value, '' );

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	/**
	 * Default action for button
	 *
	 * @return boolean
	 */
	public static function null_action(): bool {
		return false;
	}

	/**
	 * Add class 'alt' to button
	 *
	 * @param array $attr Attributes for button.
	 *
	 * @return array
	 */
	public static function class_action( array $attr ): array {
		$attr['class'] = isset( $attr['class'] ) ? $attr['class'] . ' alt' : 'alt';

		return $attr;
	}

	/**
	 * Get all products fix offset issue when paged argument exists.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public static function get_all_products_fix_offset( array $data ): array {
		$data['offset'] = 0;

		return $data;
	}

	/**
	 * Create select for custom action
	 *
	 * @return string
	 */
	public static function apply_action_before(): string {
		$options = [
			'' => __( 'Actions', 'ti-woocommerce-wishlist' ),
		];

		if ( tinv_get_option( 'table', 'add_select_to_cart' ) ) {
			$options['add_to_cart_selected'] = apply_filters( 'tinvwl_add_to_cart_text', tinv_get_option( 'product_table', 'text_add_to_cart' ) );
		}

		$wishlist_current = TInvWL_Public_Wishlist_View::instance()->get_current_wishlist();
		if ( $wishlist_current['is_owner'] ) {
			$options['remove_selected'] = __( 'Remove', 'ti-woocommerce-wishlist' );
		}

		return TInvWL_Form::_select( 'product_actions', '', [ 'class' => 'tinvwl-break-input-filed form-control' ], $options ) . '<span class="tinvwl-input-group-btn">';
	}

	/**
	 * Get product by wishlist
	 *
	 * @param array|null $wishlist Wishlist object.
	 * @param int|null $per_page
	 *
	 * @return array
	 */
	public static function get_current_products( ?array $wishlist = null, ?int $per_page = null ): array {
		if ( empty( $wishlist ) ) {
			return [];
		}

		if ( $wishlist['ID'] === 0 ) {
			$wlp = TInvWL_Product_Local::instance();
		} else {
			$wlp = new TInvWL_Product( $wishlist );
		}

		if ( empty( $wlp ) ) {
			return [];
		}

		$paged = max( 1, absint( get_query_var( 'wl_paged', 1 ) ) );

		if ( ! $per_page ) {
			$per_page = absint( apply_filters( 'tinvwl_wishlist_products_per_page', filter_input( INPUT_POST, 'lists_per_page', FILTER_VALIDATE_INT, [
				'options' => [
					'default'   => 10,
					'min_range' => 1,
				],
			] ) ) );
		}

		$product_data = [
			'count'    => $per_page,
			'offset'   => $per_page * ( $paged - 1 ),
			'external' => false,
		];

		$product_data = apply_filters( 'tinvwl_before_get_current_product', $product_data );
		$products     = $wlp->get_wishlist( $product_data );

		return apply_filters( 'tinvwl_after_get_current_product', $products );
	}
}
