<?php

/**
 * This class manages deprecated hooks for the plugin.
 *
 * It handles all deprecated hooks and provides support for older hooks to work with the new ones.
 *
 * @package TInvWishlist
 * @since 1.13.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TInvWL_Deprecated
 *
 * This class provides a mechanism to handle deprecated hooks.
 */
abstract class TInvWL_Deprecated {

	/**
	 * @var array $deprecated_hooks List of deprecated hooks.
	 */
	protected array $deprecated_hooks = [];

	/**
	 * @var array $deprecated_version Version number when hook was deprecated.
	 */
	protected array $deprecated_version = [];

	/**
	 * TInvWL_Deprecated constructor.
	 * Initialize hooks when the class is constructed.
	 */
	public function __construct() {
		$new_hooks = array_keys( $this->deprecated_hooks );
		array_walk( $new_hooks, [ $this, 'hook_in' ] );
	}

	/**
	 * Hooks into the new hook for handling deprecated hooks.
	 *
	 * @param string $hook_name Name of the hook.
	 */
	abstract public function hook_in( string $hook_name ): void;

	/**
	 * Returns a list of old hooks mapped to a new hook.
	 *
	 * @param string $new_hook Name of the new hook.
	 *
	 * @return array List of old hooks.
	 */
	public function get_old_hooks( string $new_hook ): array {
		$old_hooks = $this->deprecated_hooks[ $new_hook ] ?? [];

		return is_array( $old_hooks ) ? $old_hooks : [ $old_hooks ];
	}

	/**
	 * Handles the deprecated hook if it is triggered.
	 *
	 * @return mixed The return value of the handled deprecated hook.
	 */
	public function maybe_handle_deprecated_hook() {
		$new_hook          = current_filter();
		$old_hooks         = $this->get_old_hooks( $new_hook );
		$new_callback_args = func_get_args();
		$return_value      = $new_callback_args[0];

		foreach ( $old_hooks as $old_hook ) {
			$return_value = $this->handle_deprecated_hook( $new_hook, $old_hook, $new_callback_args, $return_value );
		}

		return $return_value;
	}

	/**
	 * Triggers the old hook if it is in use.
	 *
	 * @param string $new_hook New hook name.
	 * @param string $old_hook Old hook name.
	 * @param array $new_callback_args New callback args.
	 * @param mixed $return_value Returned value.
	 *
	 * @return mixed The return value after handling the deprecated hook.
	 */
	abstract public function handle_deprecated_hook( string $new_hook, string $old_hook, array $new_callback_args, $return_value );

	/**
	 * Returns version number when the hook was deprecated.
	 *
	 * @param string $old_hook Name of the old hook.
	 *
	 * @return string Version number.
	 */
	protected function get_deprecated_version( string $old_hook ): string {
		return $this->deprecated_version[ $old_hook ] ?? TINVWL_FVERSION;
	}

	/**
	 * Displays notice for deprecated hooks.
	 *
	 * @param string $old_hook Name of the old hook.
	 * @param string $new_hook Name of the new hook.
	 */
	protected function display_notice( string $old_hook, string $new_hook ): void {
		_deprecated_hook( esc_html( $old_hook ), esc_html( $this->get_deprecated_version( $old_hook ) ), esc_html( $new_hook ) );
	}

	/**
	 * Triggers the old hook with its arguments.
	 *
	 * @param string $old_hook Old hook name.
	 * @param array $new_callback_args New callback args.
	 *
	 * @return mixed The return value of the triggered old hook.
	 */
	abstract protected function trigger_hook( string $old_hook, array $new_callback_args );
}
