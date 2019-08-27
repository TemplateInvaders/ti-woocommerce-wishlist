<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name WP Grid Builder
 *
 * @version 1.0.3
 *
 * @slug wp-grid-builder
 *
 * @url https://www.wpgridbuilder.com
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( defined( 'WPGB_VERSION' ) ) {

	/**
	 * Add input, select, option to allowed wp_kses_post tags
	 *
	 * @param array $tags Allowed tags, attributes, and/or entities.
	 * @param string $context Context to judge allowed tags by. Allowed values are 'post'.
	 *
	 * @return array
	 */
	function tinvwl_wpkses_post_tags( $tags, $context ) {
		if ( 'post' === $context ) {
			// form fields - input
			$tags['input'] = array(
				'class' => array(),
				'id'    => array(),
				'name'  => array(),
				'value' => array(),
				'type'  => array(),
			);
			// select
			$tags['select'] = array(
				'class' => array(),
				'id'    => array(),
				'name'  => array(),
				'value' => array(),
				'type'  => array(),
			);
			// select options
			$tags['option'] = array(
				'selected' => array(),
			);
		}

		return $tags;
	}

	add_action( 'wp_grid_builder/card/wrapper_start', 'tinvwl_wpkses_fix' );

	function tinvwl_wpkses_fix() {
		add_filter( 'wp_kses_allowed_html', 'tinvwl_wpkses_post_tags', 10, 2 );
	}

	add_action( 'wp_grid_builder/card/wrapper_end', 'tinvwl_wpkses_fix_remove' );

	function tinvwl_wpkses_fix_remove() {
		remove_filter( 'wp_kses_allowed_html', 'tinvwl_wpkses_post_tags', 10, 2 );
	}

}
