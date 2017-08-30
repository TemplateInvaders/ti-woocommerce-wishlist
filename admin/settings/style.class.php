<?php
/**
 * Admin settings class
 *
 * @since             1.0.0
 * @package           TInvWishlist\Admin
 * @subpackage        Settings
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Admin settings class
 */
class TInvWL_Admin_Settings_Style extends TInvWL_Admin_BaseStyle {

	/**
	 * Priority for admin menu
	 *
	 * @var integer
	 */
	public $priority = 100;

	/**
	 * Menu array
	 *
	 * @return array
	 */
	function menu() {
		return array(
			'title'		 => __( 'Style Options', 'ti-woocommerce-wishlist' ),
			'page_title' => __( 'Wishlist Style Options', 'ti-woocommerce-wishlist' ),
			'method'	 => array( $this, '_print_' ),
			'slug'		 => 'style-settings',
		);
	}

	/**
	 * The modifiable attributes for the Default theme
	 *
	 * @return array
	 */
	function default_style_settings() {
		$font_family = apply_filters( 'tinwl_prepare_fonts', array(
			'inherit'															 => __( 'Use Default Font', 'ti-woocommerce-wishlist' ),
			'Georgia, serif'													 => __( 'Georgia', 'ti-woocommerce-wishlist' ),
			"'Times New Roman', Times, serif"									 => __( 'Times New Roman, Times', 'ti-woocommerce-wishlist' ),
			'Arial, Helvetica, sans-serif'										 => __( 'Arial, Helvetica', 'ti-woocommerce-wishlist' ),
			"'Courier New', Courier, monospace"									 => __( 'Courier New, Courier', 'ti-woocommerce-wishlist' ),
			"Georgia, 'Times New Roman', Times, serif"							 => __( 'Georgia, Times New Roman, Times', 'ti-woocommerce-wishlist' ),
			'Verdana, Arial, Helvetica, sans-serif'								 => __( 'Verdana, Arial, Helvetica', 'ti-woocommerce-wishlist' ),
			'Geneva, Arial, Helvetica, sans-serif'								 => __( 'Geneva, Arial, Helvetica', 'ti-woocommerce-wishlist' ),
			"'Source Sans Pro', 'Open Sans', sans-serif"						 => __( 'Source Sans Pro, Open Sans', 'ti-woocommerce-wishlist' ),
			"'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif"			 => __( 'Helvetica Neue, Helvetica, Roboto, Arial', 'ti-woocommerce-wishlist' ),
			'Arial, sans-serif'													 => __( 'Arial', 'ti-woocommerce-wishlist' ),
			"'Lucida Grande', Verdana, Arial, 'Bitstream Vera Sans', sans-serif" => __( 'Lucida Grande, Verdana, Arial, Bitstream Vera Sans', 'ti-woocommerce-wishlist' ),
		) );
		return array(
			array(
				'type'		 => 'group',
				'title'		 => __( 'text', 'ti-woocommerce-wishlist' ),
				'show_names' => true,
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist .tinv-header h2',
				'element'	 => 'color',
				'text'		 => __( 'Title Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'text',
				'selector'	 => '.tinv-wishlist .tinv-header h2',
				'element'	 => 'font-size',
				'text'		 => __( 'Title Font Size', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist',
				'element'	 => 'color',
				'text'		 => __( 'Content Text Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'select',
				'selector'	 => '.tinv-wishlist,.tinv-wishlist button,.tinv-wishlist input,.tinv-wishlist select,.tinv-wishlist textarea,.tinv-wishlist button,.tinv-wishlist input[type="button"],.tinv-wishlist input[type="reset"],.tinv-wishlist input[type="submit"]',
				'element'	 => 'font-family',
				'text'		 => __( 'Font', 'ti-woocommerce-wishlist' ),
				'options'	 => $font_family,
			),

			array(
				'type'		 => 'group',
				'title'		 => __( 'links', 'ti-woocommerce-wishlist' ),
				'show_names' => true,
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist a:not(.button)',
				'element'	 => 'color',
				'text'		 => __( 'Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist a:not(.button):hover,.tinv-wishlist a:not(.button):active,.tinv-wishlist a:not(.button):focus',
				'element'	 => 'color',
				'text'		 => __( 'Hover Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'select',
				'selector'	 => '.tinv-wishlist a:not(.button)',
				'element'	 => 'text-decoration',
				'text'		 => __( 'Underline', 'ti-woocommerce-wishlist' ),
				'options'	 => array(
					'underline' => __( 'Yes', 'ti-woocommerce-wishlist' ),
					'none !important' => __( 'No', 'ti-woocommerce-wishlist' ),
				),
			),
			array(
				'type'		 => 'select',
				'selector'	 => '.tinv-wishlist a:not(.button)',
				'element'	 => 'font-family',
				'text'		 => __( 'Font', 'ti-woocommerce-wishlist' ),
				'options'	 => $font_family,
			),

			array(
				'type'		 => 'group',
				'title'		 => __( 'fields', 'ti-woocommerce-wishlist' ),
				'show_names' => true,
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist input[type="text"],.tinv-wishlist input[type="email"],.tinv-wishlist input[type="url"],.tinv-wishlist input[type="password"],.tinv-wishlist input[type="search"],.tinv-wishlist input[type="tel"],.tinv-wishlist input[type="number"],.tinv-wishlist textarea,.tinv-wishlist select,.tinv-wishlist .product-quantity input[type="text"].qty',
				'element'	 => 'background-color',
				'text'		 => __( 'Background Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist input[type="text"],.tinv-wishlist input[type="email"],.tinv-wishlist input[type="url"],.tinv-wishlist input[type="password"],.tinv-wishlist input[type="search"],.tinv-wishlist input[type="tel"],.tinv-wishlist input[type="number"],.tinv-wishlist textarea,.tinv-wishlist select,.tinv-wishlist .product-quantity input[type="text"].qty',
				'element'	 => 'border-color',
				'text'		 => __( 'Border Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'text',
				'selector'	 => '.tinv-wishlist input[type="text"],.tinv-wishlist input[type="email"],.tinv-wishlist input[type="url"],.tinv-wishlist input[type="password"],.tinv-wishlist input[type="search"],.tinv-wishlist input[type="tel"],.tinv-wishlist input[type="number"],.tinv-wishlist textarea,.tinv-wishlist select,.tinv-wishlist .product-quantity input[type="text"].qty',
				'element'	 => 'border-radius',
				'text'		 => __( 'Border Radius', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist input[type="text"],.tinv-wishlist input[type="email"],.tinv-wishlist input[type="url"],.tinv-wishlist input[type="password"],.tinv-wishlist input[type="search"],.tinv-wishlist input[type="tel"],.tinv-wishlist input[type="number"],.tinv-wishlist textarea,.tinv-wishlist select,.tinv-wishlist .product-quantity input[type="text"].qty',
				'element'	 => 'color',
				'text'		 => __( 'Text Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'select',
				'selector'	 => '.tinv-wishlist input[type="text"],.tinv-wishlist input[type="email"],.tinv-wishlist input[type="url"],.tinv-wishlist input[type="password"],.tinv-wishlist input[type="search"],.tinv-wishlist input[type="tel"],.tinv-wishlist input[type="number"],.tinv-wishlist textarea,.tinv-wishlist select,.tinv-wishlist .product-quantity input[type="text"].qty',
				'element'	 => 'font-family',
				'text'		 => __( 'Font', 'ti-woocommerce-wishlist' ),
				'options'	 => $font_family,
			),
			array(
				'type'		 => 'text',
				'selector'	 => '.tinv-wishlist input[type="text"],.tinv-wishlist input[type="email"],.tinv-wishlist input[type="url"],.tinv-wishlist input[type="password"],.tinv-wishlist input[type="search"],.tinv-wishlist input[type="tel"],.tinv-wishlist input[type="number"],.tinv-wishlist textarea,.tinv-wishlist .product-quantity input[type="text"].qty',
				'element'	 => 'font-size',
				'text'		 => __( 'Font Size', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'text',
				'selector'	 => '.tinv-wishlist select',
				'element'	 => 'font-size',
				'text'		 => __( 'Select Font Size', 'ti-woocommerce-wishlist' ),
			),

			array(
				'type'		 => 'group',
				'title'		 => __( 'add to wishlist product page button', 'ti-woocommerce-wishlist' ),
				'show_names' => true,
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.woocommerce div.product form.cart .tinvwl_add_to_wishlist_button.button',
				'element'	 => 'background-color',
				'text'		 => __( 'Background Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.woocommerce div.product form.cart .tinvwl_add_to_wishlist_button.button:hover,.woocommerce div.product form.cart .tinvwl_add_to_wishlist_button.button:active,.woocommerce div.product form.cart .tinvwl_add_to_wishlist_button.button:focus',
				'element'	 => 'background-color',
				'text'		 => __( 'Background Hover Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.woocommerce div.product form.cart .tinvwl_add_to_wishlist_button',
				'element'	 => 'color',
				'text'		 => __( 'Text Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.woocommerce div.product form.cart .tinvwl_add_to_wishlist_button.button',
				'element'	 => 'color',
				'text'		 => __( 'Button Text Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.woocommerce div.product form.cart .tinvwl_add_to_wishlist_button:hover,.woocommerce div.product form.cart .tinvwl_add_to_wishlist_button:active,.woocommerce div.product form.cart .tinvwl_add_to_wishlist_button:focus',
				'element'	 => 'color',
				'text'		 => __( 'Text Hover Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.woocommerce div.product form.cart .tinvwl_add_to_wishlist_button.button:hover,.woocommerce div.product form.cart .tinvwl_add_to_wishlist_button.button:active,.woocommerce div.product form.cart .tinvwl_add_to_wishlist_button.button:focus',
				'element'	 => 'color',
				'text'		 => __( 'Button Text Hover Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'select',
				'selector'	 => '.woocommerce div.product form.cart .tinvwl_add_to_wishlist_button',
				'element'	 => 'font-family',
				'text'		 => __( 'Font', 'ti-woocommerce-wishlist' ),
				'options'	 => $font_family,
			),
			array(
				'type'		 => 'text',
				'selector'	 => '.woocommerce div.product form.cart .tinvwl_add_to_wishlist_button',
				'element'	 => 'font-size',
				'text'		 => __( 'Font Size', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'text',
				'selector'	 => '.woocommerce div.product form.cart .tinvwl_add_to_wishlist_button.button',
				'element'	 => 'border-radius',
				'text'		 => __( 'Border Radius', 'ti-woocommerce-wishlist' ),
			),

			array(
				'type'		 => 'group',
				'title'		 => __( 'accent buttons style', 'ti-woocommerce-wishlist' ),
				'show_names' => true,
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist button',
				'element'	 => 'background-color',
				'text'		 => __( 'Background Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist button:hover,.tinv-wishlist button:focus,.tinv-wishlist input[type="button"]:hover,.tinv-wishlist input[type="button"]:focus,.tinv-wishlist input[type="reset"]:hover,.tinv-wishlist input[type="reset"]:focus,.tinv-wishlist input[type="submit"]:hover,.tinv-wishlist input[type="submit"]:focus',
				'element'	 => 'background-color',
				'text'		 => __( 'Background Hover Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist button',
				'element'	 => 'color',
				'text'		 => __( 'Text Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist button:hover,.tinv-wishlist button:focus,.tinv-wishlist input[type="button"]:hover,.tinv-wishlist input[type="button"]:focus,.tinv-wishlist input[type="reset"]:hover,.tinv-wishlist input[type="reset"]:focus,.tinv-wishlist input[type="submit"]:hover,.tinv-wishlist input[type="submit"]:focus',
				'element'	 => 'color',
				'text'		 => __( 'Text Hover Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'select',
				'selector'	 => '.tinv-wishlist button',
				'element'	 => 'font-family',
				'text'		 => __( 'Font', 'ti-woocommerce-wishlist' ),
				'options'	 => $font_family,
			),
			array(
				'type'		 => 'text',
				'selector'	 => '.tinv-wishlist button',
				'element'	 => 'font-size',
				'text'		 => __( 'Font Size', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'text',
				'selector'	 => '.tinv-wishlist button',
				'element'	 => 'border-radius',
				'text'		 => __( 'Border Radius', 'ti-woocommerce-wishlist' ),
			),

			array(
				'type'		 => 'group',
				'title'		 => __( 'normal buttons style', 'ti-woocommerce-wishlist' ),
				'show_names' => true,
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.woocommerce.tinv-wishlist #respond input#submit,.woocommerce.tinv-wishlist a.button,.woocommerce.tinv-wishlist button.button,.woocommerce.tinv-wishlist input.button',
				'element'	 => 'background-color',
				'text'		 => __( 'Background Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.woocommerce.tinv-wishlist #respond input#submit:hover,.woocommerce.tinv-wishlist a.button:hover,.woocommerce.tinv-wishlist button.button:hover,.woocommerce.tinv-wishlist input.button:hover',
				'element'	 => 'background-color',
				'text'		 => __( 'Background Hover Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.woocommerce.tinv-wishlist #respond input#submit,.woocommerce.tinv-wishlist a.button,.woocommerce.tinv-wishlist button.button,.woocommerce.tinv-wishlist input.button',
				'element'	 => 'color',
				'text'		 => __( 'Text Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.woocommerce.tinv-wishlist #respond input#submit:hover,.woocommerce.tinv-wishlist a.button:hover,.woocommerce.tinv-wishlist button.button:hover,.woocommerce.tinv-wishlist input.button:hover',
				'element'	 => 'color',
				'text'		 => __( 'Text Hover Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'select',
				'selector'	 => '.woocommerce.tinv-wishlist #respond input#submit,.woocommerce.tinv-wishlist a.button,.woocommerce.tinv-wishlist button.button,.woocommerce.tinv-wishlist input.button',
				'element'	 => 'font-family',
				'text'		 => __( 'Font', 'ti-woocommerce-wishlist' ),
				'options'	 => $font_family,
			),
			array(
				'type'		 => 'text',
				'selector'	 => '.woocommerce.tinv-wishlist #respond input#submit,.woocommerce.tinv-wishlist a.button,.woocommerce.tinv-wishlist button.button,.woocommerce.tinv-wishlist input.button',
				'element'	 => 'font-size',
				'text'		 => __( 'Font Size', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'text',
				'selector'	 => '.woocommerce.tinv-wishlist #respond input#submit,.woocommerce.tinv-wishlist a.button,.woocommerce.tinv-wishlist button.button,.woocommerce.tinv-wishlist input.button',
				'element'	 => 'border-radius',
				'text'		 => __( 'Border Radius', 'ti-woocommerce-wishlist' ),
			),

			array(
				'type'		 => 'group',
				'title'		 => __( 'add to cart button', 'ti-woocommerce-wishlist' ),
				'show_names' => true,
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.woocommerce.tinv-wishlist #respond input#submit.alt,.woocommerce.tinv-wishlist a.button.alt,.woocommerce.tinv-wishlist button.button.alt,.woocommerce.tinv-wishlist input.button.alt',
				'element'	 => 'background-color',
				'text'		 => __( 'Background Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.woocommerce.tinv-wishlist #respond input#submit.alt:hover,.woocommerce.tinv-wishlist a.button.alt:hover,.woocommerce.tinv-wishlist button.button.alt:hover,.woocommerce.tinv-wishlist input.button.alt:hover',
				'element'	 => 'background-color',
				'text'		 => __( 'Background Hover Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.woocommerce.tinv-wishlist #respond input#submit.alt,.woocommerce.tinv-wishlist a.button.alt,.woocommerce.tinv-wishlist button.button.alt,.woocommerce.tinv-wishlist input.button.alt',
				'element'	 => 'color',
				'text'		 => __( 'Text Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.woocommerce.tinv-wishlist #respond input#submit.alt:hover,.woocommerce.tinv-wishlist a.button.alt:hover,.woocommerce.tinv-wishlist button.button.alt:hover,.woocommerce.tinv-wishlist input.button.alt:hover',
				'element'	 => 'color',
				'text'		 => __( 'Text Hover Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'select',
				'selector'	 => '.woocommerce.tinv-wishlist #respond input#submit.alt,.woocommerce.tinv-wishlist a.button.alt,.woocommerce.tinv-wishlist button.button.alt,.woocommerce.tinv-wishlist input.button.alt',
				'element'	 => 'font-family',
				'text'		 => __( 'Font', 'ti-woocommerce-wishlist' ),
				'options'	 => $font_family,
			),
			array(
				'type'		 => 'text',
				'selector'	 => '.woocommerce.tinv-wishlist #respond input#submit.alt,.woocommerce.tinv-wishlist a.button.alt,.woocommerce.tinv-wishlist button.button.alt,.woocommerce.tinv-wishlist input.button.alt',
				'element'	 => 'font-size',
				'text'		 => __( 'Font Size', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'text',
				'selector'	 => '.woocommerce.tinv-wishlist #respond input#submit.alt,.woocommerce.tinv-wishlist a.button.alt,.woocommerce.tinv-wishlist button.button.alt,.woocommerce.tinv-wishlist input.button.alt',
				'element'	 => 'border-radius',
				'text'		 => __( 'Border Radius', 'ti-woocommerce-wishlist' ),
			),

			array(
				'type'		 => 'group',
				'title'		 => __( 'table', 'ti-woocommerce-wishlist' ),
				'show_names' => true,
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist table,.tinv-wishlist table td',
				'element'	 => 'background-color',
				'text'		 => __( 'Background Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist table,.tinv-wishlist table th,.tinv-wishlist table td',
				'element'	 => 'border-color',
				'text'		 => __( 'Border Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist table th',
				'element'	 => 'background-color',
				'text'		 => __( 'Table Head Background Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist table th',
				'element'	 => 'color',
				'text'		 => __( 'Table Head Text Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'select',
				'selector'	 => '.tinv-wishlist table th',
				'element'	 => 'font-family',
				'text'		 => __( 'Table Head Font', 'ti-woocommerce-wishlist' ),
				'options'	 => $font_family,
			),
			array(
				'type'		 => 'text',
				'selector'	 => '.tinv-wishlist table th',
				'element'	 => 'font-size',
				'text'		 => __( 'Table Head Font Size', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist table td',
				'element'	 => 'color',
				'text'		 => __( 'Content Text Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'select',
				'selector'	 => '.tinv-wishlist table td',
				'element'	 => 'font-family',
				'text'		 => __( 'Content Text Font', 'ti-woocommerce-wishlist' ),
				'options'	 => $font_family,
			),
			array(
				'type'		 => 'text',
				'selector'	 => '.tinv-wishlist table td',
				'element'	 => 'font-size',
				'text'		 => __( 'Content Text Font Size', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist td.product-price',
				'element'	 => 'color',
				'text'		 => __( 'Price Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'select',
				'selector'	 => '.tinv-wishlist td.product-price',
				'element'	 => 'font-family',
				'text'		 => __( 'Price Font', 'ti-woocommerce-wishlist' ),
				'options'	 => $font_family,
			),
			array(
				'type'		 => 'text',
				'selector'	 => '.tinv-wishlist td.product-price',
				'element'	 => 'font-size',
				'text'		 => __( 'Price Font Size', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist td.product-price ins span.amount',
				'element'	 => 'color',
				'text'		 => __( 'Special Price Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist td.product-price ins span.amount',
				'element'	 => 'background-color',
				'text'		 => __( 'Special Price Background Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist .social-buttons li a',
				'element'	 => 'background-color',
				'text'		 => __( 'Social Icons Background Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist .social-buttons li a:hover',
				'element'	 => 'background-color',
				'text'		 => __( 'Social Icons Background Hover Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'select',
				'selector'	 => '.tinv-wishlist .social-buttons li a',
				'element'	 => '-ti-background',
				'text'		 => __( 'Social Icons Color', 'ti-woocommerce-wishlist' ),
				'options'	 => array(
					'dark'	 => __( 'Dark', 'ti-woocommerce-wishlist' ),
					'white'	 => __( 'White', 'ti-woocommerce-wishlist' ),
				),
				'validate'	 => FILTER_DEFAULT,
			),

			array(
				'type'		 => 'group',
				'title'		 => __( 'popups', 'ti-woocommerce-wishlist' ),
				'show_names' => true,
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist .tinv-modal .tinv-modal-inner',
				'element'	 => 'background-color',
				'text'		 => __( 'Background Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist .tinv-modal h2',
				'element'	 => 'color',
				'text'		 => __( 'Title Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'select',
				'selector'	 => '.tinv-wishlist .tinv-modal h2',
				'element'	 => 'font-family',
				'text'		 => __( 'Title Font', 'ti-woocommerce-wishlist' ),
				'options'	 => $font_family,
			),
			array(
				'type'		 => 'text',
				'selector'	 => '.tinv-wishlist .tinv-modal h2',
				'element'	 => 'font-size',
				'text'		 => __( 'Title Font Size', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist .tinv-modal .tinv-modal-inner',
				'element'	 => 'color',
				'text'		 => __( 'Content Text Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'select',
				'selector'	 => '.tinv-wishlist .tinv-modal .tinv-modal-inner,.tinv-wishlist .tinv-modal .tinv-modal-inner select',
				'element'	 => 'font-family',
				'text'		 => __( 'Content Text Font', 'ti-woocommerce-wishlist' ),
				'options'	 => $font_family,
			),
			array(
				'type'		 => 'text',
				'selector'	 => '.tinv-wishlist .tinv-modal .tinv-modal-inner,.tinv-wishlist .tinv-modal .tinv-modal-inner select',
				'element'	 => 'font-size',
				'text'		 => __( 'Content Text Font Size', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist .tinv-modal .tinv-modal-inner input,.tinv-wishlist .tinv-modal .tinv-modal-inner select,.tinv-wishlist .tinv-modal .tinv-modal-inner textarea',
				'element'	 => 'background-color',
				'text'		 => __( 'Fields Background Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist .tinv-modal .tinv-modal-inner input,.tinv-wishlist .tinv-modal .tinv-modal-inner select,.tinv-wishlist .tinv-modal .tinv-modal-inner textarea',
				'element'	 => 'border-color',
				'text'		 => __( 'Fields Border Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'text',
				'selector'	 => '.tinv-wishlist .tinv-modal .tinv-modal-inner input,.tinv-wishlist .tinv-modal .tinv-modal-inner select',
				'element'	 => 'border-radius',
				'text'		 => __( 'Fields Border Radius', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist .tinv-modal .tinv-modal-inner input,.tinv-wishlist .tinv-modal .tinv-modal-inner select,.tinv-wishlist .tinv-modal .tinv-modal-inner textarea',
				'element'	 => 'color',
				'text'		 => __( 'Fields Text Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist .tinv-modal .tinv-modal-inner input::-webkit-input-placeholder',
				'element'	 => 'color',
				'text'		 => __( 'Fields Placeholder Text Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist .tinv-modal button.button,.tinv-wishlist .tinv-modal .tinv-close-modal',
				'element'	 => 'background-color',
				'text'		 => __( 'Normal Buttons Background Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist .tinv-modal button.button:hover,.tinv-wishlist .tinv-modal .tinv-close-modal:hover',
				'element'	 => 'background-color',
				'text'		 => __( 'Normal Buttons Background Hover Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist .tinv-modal button.button,.tinv-wishlist .tinv-modal .tinv-close-modal',
				'element'	 => 'color',
				'text'		 => __( 'Normal Buttons Text Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist .tinv-modal button.button:hover,.tinv-wishlist .tinv-modal .tinv-close-modal:hover',
				'element'	 => 'color',
				'text'		 => __( 'Normal Buttons Text Hover Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist .tinv-modal button:not(.button)',
				'element'	 => 'background-color',
				'text'		 => __( 'Accent Buttons Background Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist .tinv-modal button:not(.button):hover,.tinv-wishlist .tinv-modal button:not(.button):active,.tinv-wishlist .tinv-modal button:not(.button):focus',
				'element'	 => 'background-color',
				'text'		 => __( 'Accent Buttons Background Hover Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist .tinv-modal button:not(.button)',
				'element'	 => 'color',
				'text'		 => __( 'Accent Buttons Text Color', 'ti-woocommerce-wishlist' ),
			),
			array(
				'type'		 => 'color',
				'selector'	 => '.tinv-wishlist .tinv-modal button:not(.button):hover,.tinv-wishlist .tinv-modal button:not(.button):active,.tinv-wishlist .tinv-modal button:not(.button):focus',
				'element'	 => 'color',
				'text'		 => __( 'Accent Buttons Text Hover Color', 'ti-woocommerce-wishlist' ),
			),
		);
	}
}
