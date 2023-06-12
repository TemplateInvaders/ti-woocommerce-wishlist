<?php
/**
 * Widget "Popular product"
 *
 * @since             1.0.0
 * @package           TInvWishlist\Widget
 */

defined( 'ABSPATH' ) || exit;

/**
 * Widget "Popular product"
 */
class TInvWL_Public_Widget_WishlistCounter extends WC_Widget {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->widget_cssclass    = 'tinvwl widget_wishlist_products_counter';
		$this->widget_description = __( 'Displays the number of products in the wishlist on your site.', 'ti-woocommerce-wishlist' );
		$this->widget_id          = 'widget_top_wishlist';
		$this->widget_name        = __( 'TI Wishlist Products Counter', 'ti-woocommerce-wishlist' );
		$this->settings           = [
			'show_icon' => [
				'type'  => 'checkbox',
				'std'   => (bool) tinv_get_option( 'topline', 'icon' ) ? 1 : 0,
				'label' => __( 'Show counter icon', 'ti-woocommerce-wishlist' ),
			],
			'show_text' => [
				'type'  => 'checkbox',
				'std'   => tinv_get_option( 'topline', 'show_text' ) ? 1 : 0,
				'label' => __( 'Show counter text', 'ti-woocommerce-wishlist' ),
			],
			'text'      => [
				'type'  => 'text',
				'std'   => apply_filters( 'tinvwl_wishlist_products_counter_text', tinv_get_option( 'topline', 'text' ) ),
				'label' => __( 'Counter Text', 'ti-woocommerce-wishlist' ),
			],
		];

		parent::__construct();
	}

	/**
	 * Outputs the widget content.
	 *
	 * @param array $args Arguments for the widget.
	 * @param array $instance Instance of the widget.
	 */
	public function widget( $args, $instance ): void {
		if ( $this->get_cached_widget( $args ) ) {
			return;
		}

		array_walk( $instance, function ( &$value ) {
			$value = 'on' === $value ? 1 : $value;
		} );

		$this->widget_start( $args, $instance );
		$content = tinvwl_shortcode_products_counter( [
			'show_icon' => $instance['show_icon'] ?? $this->settings['show_icon']['std'],
			'show_text' => $instance['show_text'] ?? $this->settings['show_text']['std'],
			'text'      => $instance['text'] ?? $this->settings['text']['std'],
		] );

		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$this->widget_end( $args, $instance );
		$this->cache_widget( $args, $content );
	}
}
