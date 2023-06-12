<?php
/**
 * Deprecated actions plugin class
 *
 * @since 1.13.0
 * @package TInvWishlist
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TInvWL_Deprecated_Actions
 *
 * This class handles deprecated actions in the plugin.
 */
class TInvWL_Deprecated_Actions extends TInvWL_Deprecated {

	/**
	 * @var array $deprecated_hooks An array of deprecated hooks that need to be handled.
	 */
	protected array $deprecated_hooks = [
		'tinvwl_wishlist_addtowishlist_button'    => 'tinv_wishlist_addtowishlist_button',
		'tinvwl_wishlist_addtowishlist_dialogbox' => 'tinv_wishlist_addtowishlist_dialogbox',
	];

	/**
	 * @var array $deprecated_version An array of versions when each hook was deprecated.
	 */
	protected array $deprecated_version = [
		'tinv_wishlist_addtowishlist_button'    => '1.13.0',
		'tinv_wishlist_addtowishlist_dialogbox' => '1.13.0',
	];

	/**
	 * Hooks into the new hook so deprecated hooks can be handled once fired.
	 *
	 * @param string $hook_name The name of the hook.
	 */
	public function hook_in( string $hook_name ): void {
		add_action( $hook_name, [ $this, 'maybe_handle_deprecated_hook' ], - 1000, 8 );
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
		if ( has_action( $old_hook ) ) {
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
	 * @return void
	 */
	protected function trigger_hook( string $old_hook, array $new_callback_args ): void {
		do_action_ref_array( $old_hook, $new_callback_args );
	}
}
