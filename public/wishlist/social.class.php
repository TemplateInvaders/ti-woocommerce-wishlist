<?php
/**
 * Social actions buttons functional
 *
 * @since             1.0.0
 * @package           TInvWishlist\Public
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Social actions buttons functional
 */
class TInvWL_Public_Wishlist_Social {

	/**
	 * Share url this wishlist
	 *
	 * @var string
	 */
	static $url;

	/**
	 * Image url
	 *
	 * @deprecated 0.0.2
	 * @var string
	 */
	static $image;

	/**
	 * First run method
	 *
	 * @param array $wishlist Set from action.
	 *
	 * @return boolean
	 */
	public static function init( $wishlist ) {
		if ( empty( $wishlist ) || 'private' === $wishlist['status'] ) {
			return false;
		}

		self::prepare( $wishlist );
		self::htmloutput( $wishlist );
	}

	/**
	 * Prepare data for social buttons
	 *
	 * @param array $wishlist Set from action.
	 */
	public static function prepare( $wishlist ) {
		self::$url   = tinv_url_wishlist( $wishlist['share_key'] );
		$wlp         = new TInvWL_Product( $wishlist );
		$products    = $wlp->get_wishlist( array(
			'count'    => 1,
			'order_by' => 'date',
			'order'    => 'DESC',
		) );
		$product     = array_shift( $products );
		self::$image = '';
		if ( ! empty( $product ) && ! empty( $product['data'] ) ) {
			list( $url, $width, $height, $is_intermediate ) = wp_get_attachment_image_src( $product['data']->get_image_id(), 'full' );
			self::$image = $url;
		}
	}

	/**
	 * Output social buttons
	 *
	 * @param array $wishlist Set from action.
	 */
	public static function htmloutput( $wishlist ) {

		$social = tinv_get_option( 'social' );

		$share_on = apply_filters( 'tinvwl-social-share_on', tinv_get_option( 'social', 'share_on' ) );

		foreach ( $social as $name => $soc_network ) {
			if ( $soc_network && method_exists( __CLASS__, $name ) ) {
				$social[ $name ] = self::$name();
			} else {
				$social[ $name ] = '';
			}
		}

		$social = apply_filters( 'tinvwl_view_social', $social, array(
			'wishlist' => $wishlist,
			'image'    => self::$image,
			'url'      => self::$url,
		) );
		$social = array_filter( $social );
		if ( empty( $social ) ) {
			return false;
		}
		$data = array(
			'social'   => $social,
			'share_on' => $share_on,
		);
		tinv_wishlist_template( 'ti-wishlist-social.php', $data );
	}

	/**
	 * Create facebook share url
	 *
	 * @return string
	 */
	public static function facebook() {
		$data = array(
			'u' => self::$url,
		);

		return 'https://www.facebook.com/sharer/sharer.php?' . http_build_query( $data );
	}

	/**
	 * Create twitter share url
	 *
	 * @return string
	 */
	public static function twitter() {
		$data = array(
			'url' => self::$url,
		);

		return 'https://twitter.com/share?' . http_build_query( $data );
	}

	/**
	 * Create pinterest share url
	 *
	 * @return string
	 */
	public static function pinterest() {
		$data = array(
			'url'   => self::$url,
			'media' => self::$image,
		);

		return 'http://pinterest.com/pin/create/button/?' . http_build_query( $data );
	}

	/**
	 * Create google++ share url
	 *
	 * @return string
	 */
	public static function google() {
		$data = array(
			'url' => self::$url,
		);

		return 'https://plus.google.com/share?' . http_build_query( $data );
	}

	/**
	 * Create email share url
	 *
	 * @return string
	 */
	public static function email() {
		$data = array(
			'body' => self::$url,
		);

		return 'mailto:?' . http_build_query( $data );
	}
}
