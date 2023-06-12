<?php
/**
 * Deprecated filters plugin class
 *
 * @since             1.13.0
 * @package           TInvWishlist
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TInvWL_Deprecated_Filters
 *
 * This class handles deprecated filters in the plugin.
 */
class TInvWL_Deprecated_Filters extends TInvWL_Deprecated {

	/**
	 * @var array $deprecated_hooks An array of deprecated hooks that need to be handled.
	 */
	protected array $deprecated_hooks = [
		'tinvwl_load_frontend'                       => 'tinvwl-load_frontend',
		'tinvwl_default_wishlist_title'              => 'tinvwl-general-default_title',
		'tinvwl_removed_from_wishlist_text'          => 'tinvwl-general-text_removed_from',
		'tinvwl_added_to_wishlist_text'              => 'tinvwl-general-text_added_to',
		'tinvwl_added_to_wishlist_text_loop'         => 'tinvwl-add_to_wishlist_catalog-text',
		'tinvwl_view_wishlist_text'                  => 'tinvwl-general-text_browse',
		'tinvwl_already_in_wishlist_text'            => 'tinvwl-general-text_already_in',
		'tinvwl_allow_add_parent_variable_product'   => 'tinvwl-allow_parent_variable',
		'tinvwl_wishlist_products_counter_text'      => 'tinvwl-topline-text',
		'tinvwl_add_selected_to_cart_text'           => 'tinvwl-table-text_add_select_to_cart',
		'tinvwl_add_to_cart_text'                    => 'tinvwl-product_table-text_add_to_cart',
		'tinvwl_share_on_text'                       => 'tinvwl-social-share_on',
		'tinvwl_wishlist_products_counter_menu_html' => 'tinvwl-menu-item-title',
		'tinvwl_wc_cart_fragments_enabled'           => 'tinvwl-wc-cart-fragments',
		'tinvwl_add_all_to_cart_text'                => 'tinvwl-table-text_add_all_to_cart',
		'tinvwl_remove_from_wishlist_text_loop'      => 'tinvwl-add_to_wishlist_catalog-text_remove',
		'tinvwl_wishlist_get_item_data'              => 'tinv_wishlist_get_item_data',
		'tinvwl_message_placeholders'                => 'tinvwl_addtowishlist_message_placeholders',
	];

	/**
	 * @var array $deprecated_version An array of versions when each hook was deprecated.
	 */
	protected array $deprecated_version = [
		'tinvwl-load_frontend'                       => '1.13.0',
		'tinvwl-general-default_title'               => '1.13.0',
		'tinvwl-general-text_removed_from'           => '1.13.0',
		'tinvwl-general-text_added_to'               => '1.13.0',
		'tinvwl-add_to_wishlist_catalog-text'        => '1.13.0',
		'tinvwl-general-text_browse'                 => '1.13.0',
		'tinvwl-general-text_already_in'             => '1.13.0',
		'tinvwl-allow_parent_variable'               => '1.13.0',
		'tinvwl-topline-text'                        => '1.13.0',
		'tinvwl-table-text_add_select_to_cart'       => '1.13.0',
		'tinvwl-product_table-text_add_to_cart'      => '1.13.0',
		'tinvwl-social-share_on'                     => '1.13.0',
		'tinvwl-menu-item-title'                     => '1.13.0',
		'tinvwl-wc-cart-fragments'                   => '1.13.0',
		'tinvwl-table-text_add_all_to_cart'          => '1.13.0',
		'tinvwl-add_to_wishlist_catalog-text_remove' => '1.13.0',
		'tinv_wishlist_get_item_data'                => '1.13.0',
		'tinvwl_addtowishlist_message_placeholders'  => '2.5.0',
	];

	/**
	 * Hooks into the new hook so deprecated hooks can be handled once fired.
	 *
	 * @param string $hook_name The name of the hook.
	 */
	public function hook_in( string $hook_name ): void {
		add_filter( $hook_name, [ $this, 'maybe_handle_deprecated_hook' ], - 1000, 8 );
	}

	/**
	 * Triggers the old hook if it is in use.
	 *
	 * @param string $new_hook New hook name.
	 * @param string $old_hook Old hook name.
	 * @param array $new_callback_args New callback arguments.
	 * @param mixed $return_value The return value.
	 *
	 * @return mixed The return value after handling the deprecated hook.
	 */
	public function handle_deprecated_hook( string $new_hook, string $old_hook, array $new_callback_args, $return_value ) {
		if ( has_filter( $old_hook ) ) {
			$this->display_notice( $old_hook, $new_hook );
			$return_value = $this->trigger_hook( $old_hook, $new_callback_args );
		}

		return $return_value;
	}

	/**
	 * Triggers the old hook with its arguments.
	 *
	 * @param string $old_hook Old hook name.
	 * @param array $new_callback_args New callback arguments.
	 *
	 * @return mixed The return value of the filter after all hooks are applied to it.
	 */
	protected function trigger_hook( string $old_hook, array $new_callback_args ) {
		return apply_filters_ref_array( $old_hook, $new_callback_args );
	}
}
