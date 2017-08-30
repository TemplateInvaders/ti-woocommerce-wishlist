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
class TInvWL_Admin_Settings_General extends TInvWL_Admin_BaseSection {

	/**
	 * Priority for admin menu
	 *
	 * @var integer
	 */
	public $priority = 20;

	/**
	 * Menu array
	 *
	 * @return array
	 */
	function menu() {
		return array(
			'title'  => __( 'General Settings', 'ti-woocommerce-wishlist' ),
			'method' => array( $this, '_print_' ),
			'slug'   => '',
		);
	}

	/**
	 * Create sections for this settings
	 *
	 * @return array
	 */
	function constructor_data() {
		add_action( $this->_n . '_section_general_before', array( $this, 'premium_features' ) );
		$lists     = get_pages( array( 'number' => 999999 ) ); // @codingStandardsIgnoreLine WordPress.VIP.RestrictedFunctions.get_pages
		$page_list = array( '' => '' );
		foreach ( $lists as $list ) {
			$page_list[ $list->ID ] = $list->post_title;
		}

		return array(
			array(
				'id'         => 'general',
				'title'      => __( 'General Settings', 'ti-woocommerce-wishlist' ),
				'desc'       => __( 'Wishlist page need to be selected so the plugin knows where it is. This page should have been created upon installation of the plugin, if not you will need to create it manually.', 'ti-woocommerce-wishlist' ),
				'show_names' => true,
				'fields'     => array(
					array(
						'type' => 'text',
						'name' => 'default_title',
						'text' => __( 'Default Wishlist Name', 'ti-woocommerce-wishlist' ),
						'std'  => 'Default wishlist',
					),
					array(
						'type'     => 'select',
						'name'     => 'page_wishlist',
						'text'     => __( 'Wishlist Page', 'ti-woocommerce-wishlist' ),
						'std'      => '',
						'options'  => $page_list,
						'validate' => FILTER_VALIDATE_INT,
					),
					array(
						'type' => 'checkboxonoff',
						'name' => 'link_in_myaccount',
						'text' => __( 'Show Link to Wishlist in my account', 'ti-woocommerce-wishlist' ),
						'std'  => true,
					),
					array(
						'type'  => 'checkboxonoff',
						'name'  => 'processing_autoremove',
						'text'  => __( 'Remove Product from Wishlist if added to cart', 'ti-woocommerce-wishlist' ),
						'std'   => true,
						'extra' => array( 'tiwl-show' => '.tiwl-processing-autoremove-anyone' ),
					),
					array(
						'type'  => 'checkboxonoff',
						'name'  => 'processing_autoremove_anyone',
						'text'  => __( 'Remove by anyone', 'ti-woocommerce-wishlist' ),
						'std'   => false,
						'class' => 'tiwl-processing-autoremove-anyone',
					),
					array(
						'type'  => 'checkboxonoff',
						'name'  => 'simple_flow',
						'text'  => __( 'Remove product from Wishlist on second click', 'ti-woocommerce-wishlist' ),
						'std'   => false,
						'extra' => array(
							'tiwl-show' => '.tiwl-button-simple-flow',
						),
					),
					array(
						'type'	 => 'group',
						'id'	 => 'show_notice',
					),
					array(
						'type'  => 'checkboxonoff',
						'name'  => 'show_notice',
						'text'  => __( 'Show successful notice in popup', 'ti-woocommerce-wishlist' ),
						'desc'  => __( 'This option allows to show/hide a popup with successful or error notices after addition or removing products from a Wishlist.', 'ti-woocommerce-wishlist' ),
						'std'   => true,
						'extra' => array(
							'tiwl-show' => '.tiwl-button-show-notice',
						),
					),
					array(
						'type'  => 'text',
						'name'  => 'text_browse',
						'text'  => __( '"View Wishlist" button Text', 'ti-woocommerce-wishlist' ),
						'std'   => 'View Wishlist',
						'class' => 'tiwl-button-show-notice',
					),
					array(
						'type'  => 'checkboxonoff',
						'name'  => 'redirect',
						'text'  => __( 'Redirect to Wishlist', 'ti-woocommerce-wishlist' ),
						'desc'  => __( 'If enabled, user will be redirected to wishlist page after 5 sec from adding product to wishlist.', 'ti-woocommerce-wishlist' ),
						'std'   => true,
						'class' => 'tiwl-button-show-notice',
					),
					array(
						'type'  => 'text',
						'name'  => 'text_removed_from',
						'text'  => __( '"Product removed from Wishlist" Text', 'ti-woocommerce-wishlist' ),
						'desc'  => __( 'This notification will be shown once the product is removed from Wishlist on a single or a catalog page.', 'ti-woocommerce-wishlist' ),
						'std'   => 'Product removed from Wishlist',
						'class' => 'tiwl-button-show-notice',
					),
					array(
						'type'  => 'text',
						'name'  => 'text_added_to',
						'text'  => __( '"Product added to Wishlist" Text', 'ti-woocommerce-wishlist' ),
						'std'   => 'Product added to Wishlist',
						'class' => 'tiwl-button-show-notice',
					),
					array(
						'type'	 => 'text',
						'name'	 => 'text_already_in',
						'text'	 => __( '"Product already in Wishlist" Text', 'ti-woocommerce-wishlist' ),
						'desc'	 => __( 'This notification will be shown if user will try to add product that already in wishlist when quantity on wishlist page is disabled.', 'ti-woocommerce-wishlist' ),
						'std'	 => 'Product already in Wishlist',
						'class'	 => 'tiwl-button-show-notice',
					),
				),
			),
			array(
				'id'         => 'page',
				'title'      => __( 'Wishlist Page Options', 'ti-woocommerce-wishlist' ),
				'desc'       => __( 'Coming soon', 'ti-woocommerce-wishlist' ),
				'show_names' => true,
				'style'      => 'display:none;',
				'fields'     => array(
					array(
						'type'     => 'select',
						'name'     => 'wishlist',
						'text'     => __( 'My Wishlist', 'ti-woocommerce-wishlist' ),
						'std'      => '',
						'options'  => $page_list,
						'validate' => FILTER_VALIDATE_INT,
					),
				),
			),
			array(
				'id'         => 'processing',
				'title'      => __( 'Wishlist Processing Options', 'ti-woocommerce-wishlist' ),
				'desc'       => __( 'Coming soon', 'ti-woocommerce-wishlist' ),
				'style'      => 'display:none;',
				'show_names' => true,
				'fields'     => array(
					array(
						'type'  => 'checkboxonoff',
						'name'  => 'autoremove',
						'text'  => __( 'Automatic removal', 'ti-woocommerce-wishlist' ),
						'std'   => true,
						'extra' => array( 'tiwl-show' => '.tiwl-processing-autoremove' ),
					),
					array(
						'type'    => 'select',
						'name'    => 'autoremove_status',
						'text'    => __( 'Remove condition', 'ti-woocommerce-wishlist' ),
						'std'     => 'tinvwl-addcart',
						'options' => array(
							'tinvwl-addcart' => __( 'Add to Card', 'ti-woocommerce-wishlist' ),
						),
						'class'   => 'tiwl-processing-autoremove',
					),
					array(
						'type'  => 'checkboxonoff',
						'name'  => 'autoremove_anyone',
						'text'  => __( 'Remove by anyone', 'ti-woocommerce-wishlist' ),
						'std'   => false,
						'class' => 'tiwl-processing-autoremove',
					),
				),
			),
			array(
				'id'         => 'add_to_wishlist',
				'title'      => __( 'Product page "Add to Wishlist" Button Settings', 'ti-woocommerce-wishlist' ),
				'show_names' => true,
				'fields'     => array(
					array(
						'type'    => 'select',
						'name'    => 'position',
						'text'    => __( 'Button position', 'ti-woocommerce-wishlist' ),
						'desc'    => __( 'Add this shortcode <code>[ti_wishlists_addtowishlist]</code> anywhere on product page, if you have chosen custom position for product button. You will have to do this for each product.', 'ti-woocommerce-wishlist' ),
						'std'     => 'after',
						'options' => array(
							'after'     => __( 'After "Add to Cart" button', 'ti-woocommerce-wishlist' ),
							'before'    => __( 'Before "Add to Cart" button', 'ti-woocommerce-wishlist' ),
							'shortcode' => __( 'Custom position with code', 'ti-woocommerce-wishlist' ),
						),
					),
					array(
						'type'    => 'select',
						'name'    => 'type',
						'text'    => __( 'Button type', 'ti-woocommerce-wishlist' ),
						'std'     => 'link',
						'options' => array(
							'link'   => __( 'Link', 'ti-woocommerce-wishlist' ),
							'button' => __( 'Button', 'ti-woocommerce-wishlist' ),
						),
					),
					array(
						'type'    => 'select',
						'name'    => 'icon',
						'text'    => __( '"Add to Wishlist" Icon', 'ti-woocommerce-wishlist' ),
						'desc'    => __( 'You can choose from our predefined icons or upload your custom icon. Custom icon size is limited to 16x16 px.', 'ti-woocommerce-wishlist' ),
						'std'     => '',
						'options' => array(
							''           => __( 'None', 'ti-woocommerce-wishlist' ),
							'heart'      => __( 'Heart', 'ti-woocommerce-wishlist' ),
							'heart-plus' => __( 'Heart+', 'ti-woocommerce-wishlist' ),
							'custom'     => __( 'Custom', 'ti-woocommerce-wishlist' ),
						),
						'extra'   => array(
							'class'      => 'tiwl-button-icon',
							'tiwl-show'  => '.tiwl-button-icon-custom',
							'tiwl-hide'  => '.tiwl-button-icon-style',
							'tiwl-value' => 'custom',
						),
					),
					array(
						'type'  => 'uploadfile',
						'name'  => 'icon_upload',
						'std'   => '',
						'text'  => ' ',
						'class' => 'tiwl-button-icon-custom',
						'extra' => array(
							'button' => array(
								'value' => __( 'Upload', 'ti-woocommerce-wishlist' ),
							),
							'type'   => array( 'image' ),
						),
					),
					array(
						'type'    => 'select',
						'name'    => 'icon_style',
						'std'     => 'black',
						'text'    => __( '"Add to Wishlist" Icon Color', 'ti-woocommerce-wishlist' ),
						'options' => array(
							'black' => __( 'Black', 'ti-woocommerce-wishlist' ),
							'white' => __( 'White', 'ti-woocommerce-wishlist' ),
						),
						'class'   => 'tiwl-button-icon-style',
					),
					array(
						'type' => 'text',
						'name' => 'text',
						'text' => __( '"Add to Wishlist" button Text', 'ti-woocommerce-wishlist' ),
						'std'  => 'Add to Wishlist',
					),
					array(
						'type'	 => 'text',
						'name'	 => 'text_remove',
						'text'	 => __( '"Remove from Wishlist" Button Text', 'ti-woocommerce-wishlist' ),
						'std'	 => 'Remove from Wishlist',
						'class'	 => 'tiwl-button-simple-flow',
					),
				),
			),
			array(
				'id'         => 'add_to_wishlist_catalog',
				'title'      => __( 'Product listing Button Settings', 'ti-woocommerce-wishlist' ),
				'desc'       => __( 'This is separate settings for "Add to wishlist" button on product listing (Shop page, categories, etc.). You can also adjust button and text colors, size, etc. in <code>TI Wishlist > Style Options.</code>', 'ti-woocommerce-wishlist' ),
				'show_names' => true,
				'fields'     => array(
					array(
						'type'  => 'checkboxonoff',
						'name'  => 'show_in_loop',
						'text'  => __( 'Show in Product Listing', 'ti-woocommerce-wishlist' ),
						'std'   => true,
						'extra' => array(
							'tiwl-show' => '.tiwl-buttoncat-button',
						),
					),
					array(
						'type'  => 'group',
						'id'    => 'add_to_wishlist_catalog',
						'class' => 'tiwl-buttoncat-button',
						'style' => 'border-top: 0px; padding-top: 0px;',
					),
					array(
						'type'    => 'select',
						'name'    => 'position',
						'text'    => __( 'Button position', 'ti-woocommerce-wishlist' ),
						'std'     => 'after',
						'options' => array(
							'after'     => __( 'After "Add to Cart" button', 'ti-woocommerce-wishlist' ),
							'before'    => __( 'Before "Add to Cart" button', 'ti-woocommerce-wishlist' ),
							'shortcode' => __( 'Custom position with code', 'ti-woocommerce-wishlist' ),
						),
						'desc'  => __( 'Note: if "Custom position with code" option is applied, the "Add to Wishlist" button should be added into template using <code>do_shortcode()</code> function like this:<br /><code>do_shortcode("[ti_wishlists_addtowishlist loop=yes]")</code>', 'ti-woocommerce-wishlist' ),
					),
					array(
						'type'    => 'select',
						'name'    => 'type',
						'text'    => __( 'Button type', 'ti-woocommerce-wishlist' ),
						'std'     => 'button',
						'options' => array(
							'link'   => __( 'Link', 'ti-woocommerce-wishlist' ),
							'button' => __( 'Button', 'ti-woocommerce-wishlist' ),
						),
					),
					array(
						'type'    => 'select',
						'name'    => 'icon',
						'text'    => __( '"Add to Wishlist" Icon', 'ti-woocommerce-wishlist' ),
						'std'     => '',
						'options' => array(
							''           => __( 'None', 'ti-woocommerce-wishlist' ),
							'heart'      => __( 'Heart', 'ti-woocommerce-wishlist' ),
							'heart-plus' => __( 'Heart+', 'ti-woocommerce-wishlist' ),
							'custom'     => __( 'Custom', 'ti-woocommerce-wishlist' ),
						),
						'extra'   => array(
							'tiwl-show'  => '.tiwl-buttoncat-icon-custom',
							'tiwl-hide'  => '.tiwl-buttoncat-icon-style',
							'tiwl-value' => 'custom',
						),
					),
					array(
						'type'  => 'uploadfile',
						'name'  => 'icon_upload',
						'std'   => '',
						'text'  => ' ',
						'class' => 'tiwl-buttoncat-icon-custom',
						'extra' => array(
							'button' => array(
								'value' => __( 'Upload', 'ti-woocommerce-wishlist' ),
							),
							'type'   => array( 'image' ),
						),
					),
					array(
						'type'    => 'select',
						'name'    => 'icon_style',
						'std'     => 'black',
						'text'    => __( '"Add to Wishlist" Icon Color', 'ti-woocommerce-wishlist' ),
						'options' => array(
							'black' => __( 'Black', 'ti-woocommerce-wishlist' ),
							'white' => __( 'White', 'ti-woocommerce-wishlist' ),
						),
						'class'   => 'tiwl-buttoncat-icon-style',
					),
					array(
						'type' => 'text',
						'name' => 'text',
						'text' => __( '"Add to Wishlist" Text', 'ti-woocommerce-wishlist' ),
						'std'  => 'Add to Wishlist',
					),
					array(
						'type'	 => 'text',
						'name'	 => 'text_remove',
						'text'	 => __( '"Remove from Wishlist" Button Text', 'ti-woocommerce-wishlist' ),
						'std'	 => 'Remove from Wishlist',
						'class'	 => 'tiwl-button-simple-flow',
					),
				),
			),
			array(
				'id'         => 'product_table',
				'title'      => __( 'Wishlist Product Settings', 'ti-woocommerce-wishlist' ),
				'desc'       => __( 'Following options allows you to choose what information/functionality to show/enable in wishlist table on wishlist page.', 'ti-woocommerce-wishlist' ),
				'show_names' => true,
				'fields'     => array(
					array(
						'type'  => 'checkboxonoff',
						'name'  => 'add_to_card',
						'text'  => __( 'Show "Add to Cart" button', 'ti-woocommerce-wishlist' ),
						'std'   => true,
						'extra' => array( 'tiwl-show' => '.tiwl-table-action-addcart' ),
					),
					array(
						'type'  => 'text',
						'name'  => 'text_add_to_card',
						'text'  => __( '"Add to Cart" Text', 'ti-woocommerce-wishlist' ),
						'std'   => 'Add to Cart',
						'class' => 'tiwl-table-action-addcart',
					),
					array(
						'type' => 'checkboxonoff',
						'name' => 'colm_price',
						'text' => __( 'Show Unit price', 'ti-woocommerce-wishlist' ),
						'std'  => true,
					),
					array(
						'type' => 'checkboxonoff',
						'name' => 'colm_stock',
						'text' => __( 'Show Stock status', 'ti-woocommerce-wishlist' ),
						'std'  => true,
					),
					array(
						'type' => 'checkboxonoff',
						'name' => 'colm_date',
						'text' => __( 'Show Date of addition', 'ti-woocommerce-wishlist' ),
						'std'  => true,
					),
				),
			),
			array(
				'id'         => 'table',
				'title'      => __( 'Wishlist Table Settings', 'ti-woocommerce-wishlist' ),
				'desc'       => __( 'Following options will help user to manage and add products to cart from wishlist table in bulk.', 'ti-woocommerce-wishlist' ),
				'show_names' => true,
				'fields'     => array(
					array(
						'type'  => 'checkboxonoff',
						'name'  => 'colm_checkbox',
						'text'  => __( 'Show Checkboxes', 'ti-woocommerce-wishlist' ),
						'std'   => true,
						'extra' => array( 'tiwl-show' => '.tiwl-table-cb-button' ),
					),
					array(
						'type'  => 'group',
						'id'    => 'cb_button',
						'class' => 'tiwl-table-cb-button',
					),
					array(
						'type' => 'checkboxonoff',
						'name' => 'colm_actions',
						'text' => __( 'Show Actions button', 'ti-woocommerce-wishlist' ),
						'desc' => __( 'Bulk actions drop down at the bottom of wishlist table', 'ti-woocommerce-wishlist' ),
						'std'  => true,
					),
					array(
						'type'  => 'checkboxonoff',
						'name'  => 'add_select_to_card',
						'text'  => __( 'Show "Add Selected to Cart" button', 'ti-woocommerce-wishlist' ),
						'std'   => true,
						'extra' => array( 'tiwl-show' => '.tiwl-table-addcart-sel' ),
					),
					array(
						'type'  => 'text',
						'name'  => 'text_add_select_to_card',
						'text'  => __( '"Add Selected to Cart" Button Text', 'ti-woocommerce-wishlist' ),
						'std'   => 'Add Selected to Cart',
						'class' => 'tiwl-table-addcart-sel',
					),
					array(
						'type' => 'group',
						'id'   => '_button',
					),
					array(
						'type'  => 'checkboxonoff',
						'name'  => 'add_all_to_card',
						'text'  => __( 'Show "Add All to Cart" button', 'ti-woocommerce-wishlist' ),
						'std'   => true,
						'extra' => array( 'tiwl-show' => '.tiwl-table-addcart-all' ),
					),
					array(
						'type'  => 'text',
						'name'  => 'text_add_all_to_card',
						'text'  => __( '"Add All to Cart" Button Text', 'ti-woocommerce-wishlist' ),
						'std'   => 'Add All to Cart',
						'class' => 'tiwl-table-addcart-all',
					),
				),
			),
			array(
				'id'         => 'social',
				'show_names' => true,
				'fields'     => array(
					array(
						'type'  => 'group',
						'id'    => 'social',
						'desc'  => __( 'Following options enable/disable Social share icons below wishlist table on wishlist page. Wishlist owner can easily share their wishlists using this button on social networks. Wishlist privacy should be set to public or shared status, private wishlists can\'t be shared.', 'ti-woocommerce-wishlist' ),
						'class' => 'tinvwl-info-top',
					),
					array(
						'type'  => 'html',
						'name'  => 'social',
						'text'  => __( 'Social Networks Sharing Options', 'ti-woocommerce-wishlist' ),
						'class' => 'tinvwl-header-row tinvwl-line-border',
					),
					array(
						'type' => 'checkboxonoff',
						'name' => 'facebook',
						'text' => __( 'Show "Share on Facebook" Button', 'ti-woocommerce-wishlist' ),
						'std'  => true,
					),
					array(
						'type' => 'checkboxonoff',
						'name' => 'twitter',
						'text' => __( 'Show "Tweet on Twitter" Button', 'ti-woocommerce-wishlist' ),
						'std'  => true,
					),
					array(
						'type' => 'checkboxonoff',
						'name' => 'pinterest',
						'text' => __( 'Show "Pin on Pinterest" Button', 'ti-woocommerce-wishlist' ),
						'std'  => true,
					),
					array(
						'type' => 'checkboxonoff',
						'name' => 'google',
						'text' => __( 'Show "Share on Google+" Button', 'ti-woocommerce-wishlist' ),
						'std'  => true,
					),
					array(
						'type' => 'checkboxonoff',
						'name' => 'email',
						'text' => __( 'Show "Share on Email" Button', 'ti-woocommerce-wishlist' ),
						'std'  => true,
					),
					array(
						'type' => 'text',
						'name' => 'share_on',
						'text' => __( '"Share on" Text', 'ti-woocommerce-wishlist' ),
						'std'  => 'Share on',
					),
				),
			),
			array(
				'id'         => 'topline',
				'title'      => __( 'Wishlist Products Counter', 'ti-woocommerce-wishlist' ),
				'desc'		 => sprintf( __( 'Add this shortcode <code>[ti_wishlist_products_counter]</code> anywhere into a page content to show Wishlist Counter.<br/><br/>It can be also added as a widget <code>Wishlist Products Counter</code> under the <a href="%s">Appearance -> Widgets</a> section.', 'ti-woocommerce-wishlist' ), esc_url( admin_url( 'widgets.php' ) ) ),
				'show_names' => true,
				'fields'     => array(
					array(
						'type'    => 'select',
						'name'    => 'icon',
						'text'    => __( '"Wishlist" Icon', 'ti-woocommerce-wishlist' ),
						'std'     => '',
						'options' => array(
							''           => __( 'None', 'ti-woocommerce-wishlist' ),
							'heart'      => __( 'Heart', 'ti-woocommerce-wishlist' ),
							'heart-plus' => __( 'Heart+', 'ti-woocommerce-wishlist' ),
							'custom'     => __( 'Custom', 'ti-woocommerce-wishlist' ),
						),
						'desc'	 => __( 'You can choose from our predefined icons or upload your custom icon. Custom icon size is limited to 16x16 px.', 'ti-woocommerce-wishlist' ),
						'extra'   => array(
							'tiwl-show'  => '.tiwl-dropdown-icon-custom',
							'tiwl-hide'  => '.tiwl-dropdown-icon-style',
							'tiwl-value' => 'custom',
						),
					),
					array(
						'type'  => 'uploadfile',
						'name'  => 'icon_upload',
						'std'   => '',
						'text'  => ' ',
						'class' => 'tiwl-dropdown-icon-custom',
						'extra' => array(
							'button' => array(
								'value' => __( 'Upload', 'ti-woocommerce-wishlist' ),
							),
							'type'   => array( 'image' ),
						),
					),
					array(
						'type'    => 'select',
						'name'    => 'icon_style',
						'std'     => 'black',
						'text'    => __( '"Wishlist" Icon Color', 'ti-woocommerce-wishlist' ),
						'options' => array(
							'black' => __( 'Black', 'ti-woocommerce-wishlist' ),
							'white' => __( 'White', 'ti-woocommerce-wishlist' ),
						),
						'class'   => 'tiwl-dropdown-icon-style',
					),
					array(
						'type'  => 'checkboxonoff',
						'name'  => 'show_text',
						'text'  => __( 'Show counter text', 'ti-woocommerce-wishlist' ),
						'std'   => true,
						'extra' => array(
							'tiwl-show' => '.tiwl-dropdown-text',
						),
					),
					array(
						'type'  => 'text',
						'name'  => 'text',
						'text'  => __( 'Counter Text', 'ti-woocommerce-wishlist' ),
						'std'   => __( 'Wishlist - ', 'ti-woocommerce-wishlist' ),
						'class' => 'tiwl-dropdown-text',
					),
				),
			),
			array(
				'id'     => 'save_buttons',
				'class'  => 'only-button',
				'noform' => true,
				'fields' => array(
					array(
						'type'  => 'button_submit',
						'name'  => 'setting_save',
						'std'   => '<span><i class="fa fa-check"></i></span>' . __( 'Save Settings', 'ti-woocommerce-wishlist' ),
						'extra' => array( 'class' => 'tinvwl-btn split status-btn-ok' ),
					),
					array(
						'type' => 'button_submit_quick',
						'name' => 'setting_save_quick',
						'std'  => '<span><i class="fa fa-floppy-o"></i></span>' . __( 'Save', 'ti-woocommerce-wishlist' ),
					),
				),
			),
		);
	}

	/**
	 * Load value from database
	 *
	 * @param array $sections Sections array.
	 *
	 * @return array
	 */
	function constructor_load( $sections ) {
		$data                                            = parent::constructor_load( $sections );
		$data['general']['page_wishlist']                = $data['page']['wishlist'];
		$data['general']['processing_autoremove']        = $data['processing']['autoremove'];
		$data['general']['processing_autoremove_anyone'] = $data['processing']['autoremove_anyone'];

		return $data;
	}

	/**
	 * Save value to database and flush rewrite.
	 *
	 * @param array $data Post section data.
	 */
	function constructor_save( $data ) {
		parent::constructor_save( $data );
		if ( empty( $data ) || ! is_array( $data ) ) {
			return false;
		}
		tinv_update_option( 'page', 'wishlist', $data['general']['page_wishlist'] );
		tinv_update_option( 'processing', 'autoremove', $data['general']['processing_autoremove'] );
		tinv_update_option( 'processing', 'autoremove_anyone', $data['general']['processing_autoremove_anyone'] );
		tinv_update_option( 'processing', 'autoremove_status', 'tinvwl-addcart' );
		TInvWL_Public_TInvWL::update_rewrite_rules();
	}

	/**
	 * Show Premium Features sections
	 */
	function premium_features() {
		TInvWL_View::view( 'premium-features' );
	}
}
