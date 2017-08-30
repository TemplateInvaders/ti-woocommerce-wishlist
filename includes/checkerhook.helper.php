<?php
/**
 * Checker hook plugin class
 *
 * @since             1.0.0
 * @package           TInvWishlist\Helper
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Checker hook plugin class
 */
class TInvWL_CheckerHook {

	/**
	 * Filter current
	 *
	 * @var string
	 */
	private $filter;
	/**
	 * Filter list
	 *
	 * @var array
	 */
	private $filters;
	/**
	 * Filter message list
	 *
	 * @var array
	 */
	private $filters_message;
	/**
	 * Default message
	 *
	 * @var string
	 */
	public $message = '';
	/**
	 * This class
	 *
	 * @var \TInvWL_CheckerHook
	 */
	protected static $_instance = null;


	/**
	 * Get this class object
	 *
	 * @return \TInvWL_CheckerHook
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		self::$_instance->clear_action();
		return self::$_instance;
	}

	/**
	 * Creating class
	 */
	function __construct() {
		$this->define_hook();
	}

	/**
	 * Define hook for check current hook.
	 */
	function define_hook() {
		$filters = get_option( 'ti_checker_hooks', array() );
		if ( ! empty( $filters ) && is_array( $filters ) ) {
			$checker = new TInvWL_CheckerHook_Checker();
			foreach ( $filters as $tag ) {
				$result = get_option( 'ti_checker__' . $tag );
				if ( ! empty( $result ) ) {
					add_filter( $tag, array( $checker, $tag ) );
				}
			}
		}
	}

	/**
	 * Default callback function
	 *
	 * @param array $data Array data.
	 * @return mixed
	 */
	public static function get_remote_data( $data ) {
		$result = false;
		if ( array_key_exists( 'template', $data ) && ! empty( $data['template'] ) ) {
			$args = array();
			if ( array_key_exists( 'template_args', $data ) && is_array( $data['template_args'] ) ) {
				$args = $data['template_args'];
			}
			if ( is_array( $data['template'] ) ) {
				foreach ( $data['template'] as $template ) {
					if ( wc_get_template_html( $template, $args ) ) {
						$result = true;
					}
				}
			} else {
				if ( wc_get_template_html( $data['template'], $args ) ) {
					$result = true;
				}
			}
		}
		if ( array_key_exists( 'function', $data ) && ! empty( $data['function'] ) ) {
			if ( function_exists( $data['function'] ) ) {
				$args = array();
				if ( array_key_exists( 'function_args', $data ) && is_array( $data['function_args'] ) ) {
					$args = $data['function_args'];
				}
				ob_start();
				call_user_func_array( $data['function'], $args );
				ob_get_clean();
				$result = true;
			}
		}
		if ( array_key_exists( 'url', $data ) && ! empty( $data['url'] ) ) {
			$response = wp_remote_get( $data['url'] );
			if ( ! is_wp_error( $response ) ) {
				$result = $result || 200 === $response['response']['code'];
			}
		}
		return $result;
	}

	/**
	 * Add action for checking
	 *
	 * @param string $tag Name action for checking.
	 * @param string $message Message for error.
	 * @return \TInvWL_CheckerHook
	 */
	function add_action( $tag, $message = null ) {
		if ( is_array( $tag ) ) {
			foreach ( $tag as $_tag ) {
				$this->filters[ $_tag ] = $message;
			}
		} else {
			$this->filters[ $tag ] = $message;
		}
		return $this;
	}

	/**
	 * Clear checking filters.
	 *
	 * @return \TInvWL_CheckerHook
	 */
	function clear_action() {
		$this->filters = array();
		return $this;
	}

	/**
	 * Set default message error.
	 *
	 * @param string $message Message.
	 * @return \TInvWL_CheckerHook
	 */
	function set_message( $message ) {
		$this->message = $message;
		return $this;
	}

	/**
	 * Show message
	 *
	 * @param string $message Message.
	 * @param string $action Name hook.
	 * @return string
	 */
	function show_message( $message, $action = '' ) {
		return sprintf( $message, $action );
	}

	/**
	 * Run checking
	 *
	 * @param array $arg Array data for callback function.
	 * @return array
	 */
	/**
	 * Run checking
	 *
	 * @param array $arg Array data for callback function.
	 * @param mixed $fuction_to_check Function for run check hooks.
	 * @return type
	 */
	function run( $arg = array(), $fuction_to_check = null ) {
		if ( empty( $fuction_to_check ) ) {
			$fuction_to_check = array( __CLASS__, 'get_remote_data' );
		}

		$tags = array_keys( $this->filters );

		update_option( 'ti_checker_hooks', $tags, false );
		$checker = new TInvWL_CheckerHook_Checker();
		foreach ( $tags as $tag ) {
			update_option( 'ti_checker__' . $tag, 1, false );
			wp_cache_delete( 'ti_checker__' . $tag, 'options' );
			add_filter( $tag, array( $checker, $tag ) );
		}
		wp_cache_delete( 'alloptions', 'options' );
		$return = call_user_func( $fuction_to_check, $arg );
		delete_option( 'ti_checker_hooks' );
		$result = array();
		if ( $return ) {
			foreach ( $tags as $tag ) {
				remove_filter( $tag, array( $checker, $tag ) );
				$_result = absint( get_option( 'ti_checker__' . $tag, 1 ) );
				if ( $_result ) {
					$message = empty( $this->filters[ $tag ] ) ? $this->message : $this->filters[ $tag ] ;
					$result[ $tag ] = $this->show_message( $message, $tag );
				}
				delete_option( 'ti_checker__' . $tag );
			}
		}
		return $result;
	}

}

if ( ! class_exists( 'TInvWL_CheckerHook_Checker' ) ) {

	/**
	 * Class for apply check hook
	 */
	class TInvWL_CheckerHook_Checker {

		/**
		 * Run check actions
		 *
		 * @param string $name Hook name.
		 * @param array  $arguments Argument for hook.
		 * @return mixed
		 */
		public function __call( $name, $arguments ) {
			update_option( 'ti_checker__' . $name, 0, false );
			return array_shift( $arguments );
		}

	}
}

TInvWL_CheckerHook::instance();
