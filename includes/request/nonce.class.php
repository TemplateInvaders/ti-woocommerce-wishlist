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

	public static function guest_identity() {
		$value = $_COOKIE[ self::cookie_name() ] ?? '';
		return is_string( $value ) && preg_match( '/^[a-f0-9]{64}$/D', $value ) ? $value : '';
	}

	private static function action( $action, $cookie = '' ) {
		$action .= '|site|' . get_current_blog_id();
		return is_user_logged_in() ? $action : $action . '|guest|' . hash( 'sha256', $cookie );
	}

	/** Rotate the secret when ownership/session changes, retaining HttpOnly protection. */
	public static function rotate_guest_identity() {
		return self::set_guest_identity( bin2hex( random_bytes( 32 ) ) );
	}

	private static function set_guest_identity( $cookie ) {
		if ( headers_sent() ) { return ''; }
		$created = setcookie( self::cookie_name(), $cookie, array(
			'expires' => time() + 30 * DAY_IN_SECONDS,
			'path' => '/', 'domain' => '', 'secure' => is_ssl(),
			'httponly' => true, 'samesite' => 'Lax',
		) );
		if ( ! $created ) { return ''; }
		$_COOKIE[ self::cookie_name() ] = $cookie;
		return $cookie;
	}

	/** Only a same-origin preflight may initialize the independent CSRF/guest secret. */
	public static function create( $action ) {
		$cookie = self::guest_identity();
		if ( ! is_user_logged_in() && '' === $cookie ) {
			$bootstrap = $_SERVER['HTTP_X_TINVWL_GUEST_BOOTSTRAP'] ?? '';
			if ( is_string( $bootstrap ) && preg_match( '/^[a-f0-9]{64}$/D', $bootstrap ) ) {
				// IndexedDB atomically shares this random bootstrap across virgin tabs.
				// The browser never learns the HttpOnly credential derived by the server.
				$cookie = hash_hmac( 'sha256', 'tinvwl-guest-bootstrap|' . $bootstrap, wp_salt( 'auth' ) );
			} elseif ( '1' === ( $_SERVER['HTTP_X_TINVWL_GUEST_LOCKED'] ?? '' ) ) {
				$cookie = bin2hex( random_bytes( 32 ) );
			} else {
				return ''; // Do not race independent initializers when browser storage is unavailable.
			}
			$cookie = self::set_guest_identity( $cookie );
			if ( '' === $cookie ) { return ''; }
		}
		return wp_create_nonce( self::action( $action, $cookie ) );
	}

	/** Verification never creates a cookie or grants wishlist ownership. */
	public static function verify( $nonce, $action ) {
		if ( ! is_string( $nonce ) || '' === $nonce ) {
			return false;
		}
		$cookie = self::guest_identity();
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
		$add_nonce = self::create( 'tinvwl_add_to_wishlist' );
		if ( '' === $add_nonce ) {
			wp_send_json( array( 'status' => false, 'error' => 'guest_identity_unavailable' ), 409 );
		}
		wp_send_json( array(
			'add_nonce' => $add_nonce,
			'cart_nonce' => self::create( 'tinvwl_save_cart' ),
			'rest_nonce' => wp_create_nonce( 'wp_rest' ),
		) );
	}
}
