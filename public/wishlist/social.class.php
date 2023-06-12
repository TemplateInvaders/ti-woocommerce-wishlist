<?php
/**
 * Social actions buttons functionality.
 *
 * @since             1.0.0
 * @package           TInvWishlist\Public
 */

defined( 'ABSPATH' ) || exit;

/**
 * Social actions buttons functionality.
 */
class TInvWL_Public_Wishlist_Social {
	/**
	 * Share URL of this wishlist.
	 *
	 * @var string|null
	 */
	private static ?string $url;

	/**
	 * Image URL.
	 *
	 * @var string|null
	 */
	private static ?string $image;

	/**
	 * First run method.
	 *
	 * @param array $wishlist Set from action.
	 *
	 * @return void
	 */
	public static function init( array $wishlist ): void {
		if ( empty( $wishlist ) || 'private' === $wishlist['status'] ) {
			return;
		}

		self::$image = TInvWL_Public_Wishlist_View::instance()->social_image;
		self::$url   = TInvWL_Public_Wishlist_View::instance()->wishlist_url;

		self::htmloutput( $wishlist );
	}

	/**
	 * Outputs social buttons.
	 *
	 * @param array $wishlist Set from action.
	 *
	 * @return void
	 */
	public static function htmloutput( array $wishlist ): void {
		$social = tinv_get_option( 'social' );

		$share_on      = apply_filters( 'tinvwl_share_on_text', tinv_get_option( 'social', 'share_on' ) );
		$social_titles = [];

		foreach ( $social as $name => $soc_network ) {
			if ( $soc_network && method_exists( self::class, $name ) ) {
				$social[ $name ]        = self::$name();
				$social_titles[ $name ] = self::$name( true );
				if ( 'clipboard' === $name ) {
					wp_enqueue_script( 'tinvwl-clipboard' );
				}
			} else {
				unset( $social[ $name ] );
			}
		}

		$social = apply_filters( 'tinvwl_view_social', $social, [
			'wishlist' => $wishlist,
			'image'    => self::$image,
			'url'      => self::$url
		] );

		if ( empty( $social ) ) {
			return;
		}

		$data = [
			'social'        => $social,
			'social_titles' => $social_titles,
			'share_on'      => $share_on,
		];

		tinv_wishlist_template( 'ti-wishlist-social.php', apply_filters( 'tinvwl_social_share_data', $data, $wishlist ) );
	}

	/**
	 * Creates Facebook share URL.
	 *
	 * @param bool $title Return title for translation.
	 *
	 * @return string
	 */
	public static function facebook( bool $title = false ): string {
		return $title ? esc_html__( 'Facebook', 'ti-woocommerce-wishlist' ) : 'https://www.facebook.com/sharer/sharer.php?' . http_build_query( apply_filters( 'tinvwl_social_link_facebook', [ 'u' => self::$url ] ) );
	}

	/**
	 * Creates Twitter share URL.
	 *
	 * @param bool $title Return title for translation.
	 *
	 * @return string
	 */
	public static function twitter( bool $title = false ): string {
		return $title ? esc_html__( 'Twitter', 'ti-woocommerce-wishlist' ) : 'https://twitter.com/share?' . http_build_query( apply_filters( 'tinvwl_social_link_twitter', [ 'url' => self::$url ] ) );
	}

	/**
	 * Creates Pinterest share URL.
	 *
	 * @param bool $title Return title for translation.
	 *
	 * @return string
	 */
	public static function pinterest( bool $title = false ): string {
		return $title ? esc_html__( 'Pinterest', 'ti-woocommerce-wishlist' ) : 'http://pinterest.com/pin/create/button/?' . http_build_query( apply_filters( 'tinvwl_social_link_pinterest', [
				'url'   => self::$url,
				'media' => self::$image
			] ) );
	}

	/**
	 * Creates email share URL.
	 *
	 * @param bool $title Return title for translation.
	 *
	 * @return string
	 */
	public static function email( bool $title = false ): string {
		return $title ? esc_html__( 'Email', 'ti-woocommerce-wishlist' ) : 'mailto:' . apply_filters( 'tinvwl_social_link_email_recepient', '' ) . '?' . http_build_query( apply_filters( 'tinvwl_social_link_email', [ 'body' => self::$url ] ) );
	}

	/**
	 * Creates clipboard URL.
	 *
	 * @param bool $title Return title for translation.
	 *
	 * @return string
	 */
	public static function clipboard( bool $title = false ): string {
		return $title ? esc_html__( 'Clipboard', 'ti-woocommerce-wishlist' ) : self::$url;
	}

	/**
	 * Creates WhatsApp share URL.
	 *
	 * @param bool $title Return title for translation.
	 *
	 * @return string
	 */
	public static function whatsapp( bool $title = false ): string {
		return $title ? esc_html__( 'WhatsApp', 'ti-woocommerce-wishlist' ) : 'https://api.whatsapp.com/send?' . http_build_query( apply_filters( 'tinvwl_social_link_whatsapp', [ 'text' => self::$url ] ) );
	}
}
