<?php
/**
 * Update plugin class
 *
 * @since             1.0.0
 * @package           TInvWishlist
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Update plugin class.
 */
class TInvWL_Update {
	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	private string $_name;

	/**
	 * Current version.
	 *
	 * @var string
	 */
	private string $_version;

	/**
	 * Previous version.
	 *
	 * @var string
	 */
	private string $_prev;

	/**
	 * Regular expression for sorting version function.
	 *
	 * @var string
	 */
	private const REGEXP = '/^up_/i';

	/**
	 * TInvWL_Update constructor.
	 *
	 * Get update methods and apply.
	 *
	 * @param string $version Current version.
	 * @param string $previous_version Previous version.
	 */
	public function __construct( string $version, string $previous_version = '0' ) {
		$this->_name    = TINVWL_PREFIX;
		$this->_version = $version;
		$this->_prev    = $previous_version;
		$methods        = array_filter( get_class_methods( $this ), [ $this, 'filter' ] );

		if ( ! empty( $methods ) ) {
			uasort( $methods, [ $this, 'sort' ] );
			foreach ( $methods as $method ) {
				call_user_func( [ $this, $method ], $previous_version );
			}
		}
	}

	/**
	 * Filter methods.
	 *
	 * @param string $method Method name from this class.
	 *
	 * @return bool
	 */
	public function filter( string $method ): bool {
		if ( ! preg_match( self::REGEXP, $method ) ||
		     version_compare( $this->_prev, $this->prepare( $method ), 'ge' ) ) {
			return false;
		}

		return version_compare( $this->_version, $this->prepare( $method ), 'ge' );
	}

	/**
	 * Sort methods.
	 *
	 * @param string $method1 Method name first from this class.
	 * @param string $method2 Method name second from this class.
	 *
	 * @return int
	 */
	public function sort( string $method1, string $method2 ): int {
		return version_compare( $this->prepare( $method1 ), $this->prepare( $method2 ) );
	}

	/**
	 * Convert method name to version.
	 *
	 * @param string $method Method name from this class.
	 *
	 * @return string
	 */
	public function prepare( string $method ): string {
		return str_replace( '_', '.', preg_replace( self::REGEXP, '', $method ) );
	}

	/**
	 * Example of the method updating.
	 *
	 * @param string $previous_version Previous version.
	 */
	public function up_0_0_0( string $previous_version = '0' ): void {
		// Empty method used for demonstration.
	}

	/**
	 * Set runed wizard.
	 *
	 * @param string $previous_version Previous version.
	 */
	public function up_1_1_10_1( string $previous_version = '0' ): void {
		update_option( 'tinvwl_wizard', true );
	}

	/**
	 * Fix name field.
	 */
	public function up_p_1_5_4(): void {
		$options = [
			'add_to_card'             => 'add_to_cart',
			'text_add_to_card'        => 'text_add_to_cart',
			'add_select_to_card'      => 'add_select_to_cart',
			'text_add_select_to_card' => 'text_add_select_to_cart',
			'add_all_to_card'         => 'add_all_to_cart',
			'text_add_all_to_card'    => 'text_add_all_to_cart'
		];

		foreach ( $options as $oldOption => $newOption ) {
			if ( $value = tinv_get_option( 'product_table', $oldOption ) ) {
				tinv_update_option( 'product_table', $newOption, $value );
			}
			if ( $value = tinv_get_option( 'table', $oldOption ) ) {
				tinv_update_option( 'table', $newOption, $value );
			}
		}
	}

	/**
	 * Clean up empty wishlists.
	 */
	public function up_p_1_6_1(): void {
		global $wpdb;
		$wishlistTable      = sprintf( '%s%s_%s', $wpdb->prefix, $this->_name, 'lists' );
		$wishlistItemsTable = sprintf( '%s%s_%s', $wpdb->prefix, $this->_name, 'items' );
		$sql                = "DELETE FROM wl USING `{$wishlistTable}` AS wl WHERE NOT EXISTS( SELECT * FROM `{$wishlistItemsTable}` WHERE {$wishlistItemsTable}.wishlist_id = wl.ID ) AND wl.type='default'";
		$wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Buttons class fallback.
	 */
	public function up_p_1_8_9(): void {
		$catalogClass  = tinv_get_option( 'add_to_wishlist_catalog', 'class' );
		$wishlistClass = tinv_get_option( 'add_to_wishlist', 'class' );

		if ( 'button' == tinv_get_option( 'add_to_wishlist_catalog', 'type' ) && empty( $catalogClass ) ) {
			tinv_update_option( 'add_to_wishlist_catalog', 'class', 'button tinvwl-button' );
		}

		if ( 'button' == tinv_get_option( 'add_to_wishlist', 'type' ) && empty( $wishlistClass ) ) {
			tinv_update_option( 'add_to_wishlist', 'class', 'button tinvwl-button' );
		}
	}

	/**
	 * Schedule event to flush rewrite rules.
	 */
	public function up_1_16_1(): void {
		wp_schedule_single_event( time(), 'tinvwl_flush_rewrite_rules' );
	}

}
