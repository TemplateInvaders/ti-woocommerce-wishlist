<?php
/**
 * Admin settings class
 *
 * @package TInvWishlist\Admin
 * @subpackage Settings
 * @since 1.0.0
 */

// If this file is called directly, abort.
defined( 'ABSPATH' ) or exit;

/**
 * Admin settings class
 */
class TInvWL_Admin_Settings_Integrations extends TInvWL_Admin_BaseSection {

	/**
	 * Priority for admin menu
	 *
	 * @var int
	 */
	public int $priority = 110;

	/**
	 * This class
	 *
	 * @var TInvWL_Admin_Settings_Integrations
	 */
	protected static ?self $_instance = null;

	/**
	 * Get this class object
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $plugin_version Plugin version.
	 *
	 * @return TInvWL_Admin_Settings_Integrations
	 */
	public static function instance( string $plugin_name = TINVWL_PREFIX, string $plugin_version = TINVWL_FVERSION ): self {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $plugin_name, $plugin_version );
		}

		return self::$_instance;
	}

	/**
	 * Menu array
	 *
	 * @return array
	 */
	public function menu(): array {
		return [
			'title'      => __( 'Integrations', 'ti-woocommerce-wishlist' ),
			'page_title' => __( 'Wishlist Integrations with 3rd party plugins and themes', 'ti-woocommerce-wishlist' ),
			'method'     => [ $this, '_print_' ],
			'slug'       => 'integrations-settings',
			'capability' => 'tinvwl_integrations_settings',
		];
	}

	/**
	 * Create sections for this settings
	 *
	 * @return array
	 */
	public function constructor_data(): array {
		global $tinvwl_integrations;
		$fields = [];

		if ( is_array( $tinvwl_integrations ) ) {
			foreach ( $tinvwl_integrations as $slug => $settings ) {
				$disabled = ( $settings['available'] ) ? [] : [ 'disabled' => 'disabled' ];

				$fields[] = [
					'type'  => 'checkboxonoff',
					'name'  => $slug,
					'text'  => $settings['name'],
					'std'   => true,
					'extra' => $disabled,
				];
			}
		}

		$settings = [
			[
				'id'         => 'integrations',
				'title'      => __( 'Available Integrations', 'ti-woocommerce-wishlist' ),
				'show_names' => true,
				'fields'     => $fields,
				'desc'       => __( 'You can disable built-in integrations with 3rd party plugins and themes.', 'ti-woocommerce-wishlist' ),
			],
			[
				'id'     => 'save_buttons',
				'class'  => 'only-button',
				'noform' => true,
				'fields' => [
					[
						'type'  => 'button_submit',
						'name'  => 'setting_save',
						'std'   => '<span><i class="ftinvwl ftinvwl-check"></i></span>' . __( 'Save Settings', 'ti-woocommerce-wishlist' ),
						'extra' => [ 'class' => 'tinvwl-btn split status-btn-ok' ],
					],
					[
						'type' => 'button_submit_quick',
						'name' => 'setting_save_quick',
						'std'  => '<span><i class="ftinvwl ftinvwl-floppy-o"></i></span>' . __( 'Save', 'ti-woocommerce-wishlist' ),
					],
				],
			],
		];

		return $settings;
	}
}
