<?php
/** Session-bound nonces for ordinary wishlist POST requests. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TInvWL_Request_Nonce {
	const COOKIE = 'tinvwl_csrf';
	const SECURE_COOKIE = '__Host-tinvwl_csrf';

	private static function cookie_name() {
		return is_ssl() ? self::SECURE_COOKIE : self::COOKIE;
	}

	private static function cookie() {
		$value = $_COOKIE[ self::cookie_name() ] ?? '';
		return is_string( $value ) && preg_match( '/^[a-f0-9]{64}$/D', $value ) ? $value : '';
	}

	private static function action( $action, $cookie = '' ) {
		$action .= '|site|' . get_current_blog_id();
		return is_user_logged_in() ? $action : $action . '|guest|' . hash( 'sha256', $cookie );
	}

	/** Only rendering or the same-origin preflight may issue the independent CSRF cookie. */
	public static function create( $action ) {
		$cookie = self::cookie();
		if ( ! is_user_logged_in() && '' === $cookie ) {
			if ( headers_sent() ) {
				return '';
			}
			$cookie = bin2hex( random_bytes( 32 ) );
			$created = setcookie( self::cookie_name(), $cookie, array(
				'expires' => time() + 30 * DAY_IN_SECONDS,
				'path' => '/',
				'domain' => '',
				'secure' => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			) );
			if ( ! $created ) {
				return '';
			}
			$_COOKIE[ self::cookie_name() ] = $cookie;
		}
		return wp_create_nonce( self::action( $action, $cookie ) );
	}

	/** Verification never creates a cookie or grants wishlist ownership. */
	public static function verify( $nonce, $action ) {
		if ( ! is_string( $nonce ) || '' === $nonce ) {
			return false;
		}
		$cookie = self::cookie();
		if ( ! is_user_logged_in() && '' === $cookie ) {
			return false;
		}
		return false !== wp_verify_nonce( $nonce, self::action( $action, $cookie ) );
	}

	/** Fresh nonces stay outside cached product pages and cannot be read cross-origin. */
	public static function endpoint() {
		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private' );
		send_nosniff_header();
		header_remove( 'Access-Control-Allow-Origin' );
		header_remove( 'Access-Control-Allow-Credentials' );
		header( 'Vary: Cookie', false );
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_send_json( array( 'status' => false, 'error' => 'invalid_method' ), 405 );
		}
		wp_send_json( array(
			'add_nonce' => self::create( 'tinvwl_add_to_wishlist' ),
			'cart_nonce' => self::create( 'tinvwl_save_cart' ),
		) );
	}
}
