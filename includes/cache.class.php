<?php
/** Cache policy for personal wishlist responses, independent of browser cookies. */
defined( 'ABSPATH' ) || exit;

class TInvWL_Cache {

	public static function init(): void {
		add_action( 'wp_loaded', array( __CLASS__, 'request' ), 0 );
		add_action( 'send_headers', array( __CLASS__, 'page' ), 0 );
		add_action( 'wp', array( __CLASS__, 'page' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'before_template' ), 0 );
		add_filter( 'pre_do_shortcode_tag', array( __CLASS__, 'shortcode' ), 0, 2 );
		add_action( 'woocommerce_before_template_part', array( __CLASS__, 'template' ), 0 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'rest' ), 10, 3 );
	}

	/** Never undo cache restrictions imposed by WordPress, WooCommerce or extensions. */
	public static function prevent(): void {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! headers_sent() ) {
			nocache_headers();
			header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private' );
		}
		do_action( 'litespeed_control_set_nocache', 'Personal wishlist response' );
		do_action( 'tinvwl_no_cache' );
	}

	/** Personal endpoints must also be protected for a visitor without credentials. */
	public static function request(): void {
		$ajax = $_GET['wc-ajax'] ?? '';
		if ( in_array( $ajax, array( 'tinvwl', 'tinvwl_request_nonce' ), true ) || isset( $_GET['tinvwl_download_wishlist'] )
			|| isset( $_POST['tinv_wishlist_id'] ) || isset( $_POST['tinvwl-action'] )
			|| isset( $_POST['cart_to_wishlist_id'] ) || isset( $_POST['cart_item_to_wishlist_id'] )
			|| isset( $_POST['tinvwl-action-create'] ) || isset( $_POST['tinvwl-action-notifications'] )
			|| isset( $_GET['tinv-miniwishlist-action'] ) || isset( $_GET['tiwp'] ) ) {
			self::prevent();
		}
	}

	private static function personal_shortcodes(): array {
		return array( 'ti_wishlistsview', 'ti_wishlists_manage_lists', 'ti_wishlists_create', 'ti_wishlists_notifications', 'ti_miniwishlist' );
	}

	/** Explicit URI exclusions for caches which serve files before WordPress starts. */
	public static function reject_urls( $urls ): array {
		$ids = array_filter( array_map( 'absint', array(
			tinv_get_option( 'page', 'wishlist' ), tinv_get_option( 'page', 'manage' ), tinv_get_option( 'page', 'create' ),
		) ) );
		$languages = apply_filters( 'wpml_active_languages', array(), array( 'skip_missing' => 0 ) );
		foreach ( $ids as $id ) {
			foreach ( (array) $languages as $language ) {
				if ( ! empty( $language['language_code'] ) ) {
					$ids[] = absint( apply_filters( 'wpml_object_id', $id, 'page', true, $language['language_code'] ) );
				}
			}
		}
		if ( tinv_get_option( 'general', 'my_account_endpoint' ) && function_exists( 'wc_get_page_id' ) ) {
			$ids[] = wc_get_page_id( 'myaccount' );
		}
		$permalinks = array_map( 'get_permalink', array_unique( array_filter( $ids ) ) );
		if ( tinv_get_option( 'general', 'my_account_endpoint' ) && function_exists( 'wc_get_endpoint_url' ) ) {
			$permalinks[] = wc_get_endpoint_url( tinv_get_option( 'general', 'my_account_endpoint_slug' ), '', wc_get_page_permalink( 'myaccount' ) );
		}
		foreach ( $permalinks as $url ) {
			// Query-style permalinks share '/' with the catalog; never exclude that
			// whole path for ?page_id=N. Runtime guards and query cache rules apply.
			if ( wp_parse_url( $url, PHP_URL_QUERY ) ) {
				continue;
			}
			$path = wp_parse_url( $url, PHP_URL_PATH );
			if ( is_string( $path ) && '' !== $path ) {
				// Restrict home/translated-home exclusions to real TI routes, not
				// the whole language or installation directory. Capturing (/...)
				// also lets Rocket strip its home_root before adding it back.
				$base = preg_quote( untrailingslashit( $path ), '#' );
				// Rocket passes rules through esc_url_raw(): [] are URL-encoded,
				// and braces/backslashes are removed. Use URL-safe regex syntax.
				$hex = '(a|b|c|d|e|f|0|1|2|3|4|5|6|7|8|9)';
				// Count only characters inside the key segment, never its slash/pagination.
				$key = '(?=(' . str_repeat( '(?!/).', 6 ) . '|' . str_repeat( '(?!/).', 32 ) . ')(/|$))' . $hex . '+';
				$number = '(0|1|2|3|4|5|6|7|8|9)+';
				$route = '(' . $key . '(/(wl_page|page)/' . $number . ')?|(wl_page|page)/' . $number . ')';
				$urls[] = '' === $base ? '/(' . $route . '/?)?' : $base . '(/' . $route . ')?/?';
			}
		}
		return array_values( array_unique( $urls ) );
	}

	/** Query context is available before the template emits any HTML. No owner lookup. */
	public static function page(): void {
		foreach ( array( 'wishlist', 'manage', 'create' ) as $setting ) {
			$id = absint( tinv_get_option( 'page', $setting ) );
			$translated = absint( apply_filters( 'wpml_object_id', $id, 'page', true ) );
			if ( ( $id && is_page( $id ) ) || ( $translated && is_page( $translated ) ) ) {
				self::prevent();
				return;
			}
		}
		$endpoint = tinv_get_option( 'general', 'my_account_endpoint_slug' );
		if ( tinv_get_option( 'general', 'my_account_endpoint' ) && is_string( $endpoint ) && '' !== $endpoint && null !== get_query_var( $endpoint, null ) ) {
			self::prevent();
			return;
		}
		global $wp_query;
		foreach ( (array) ( $wp_query->posts ?? array() ) as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			foreach ( self::personal_shortcodes() as $tag ) {
				if ( has_shortcode( $post->post_content, $tag ) ) {
					self::prevent();
					return;
				}
			}
		}
	}

	/** Keep headers mutable for personal fragments called directly by a theme/widget. */
	public static function before_template(): void {
		self::page();
		$buffer = ob_get_status();
		if ( ! is_admin() && ! wp_doing_ajax() && ! is_feed() && ! is_trackback() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			&& in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true )
			&& ! headers_sent() && ( ! $buffer || ! empty( $buffer['chunk_size'] ) ) ) {
			// WordPress flushes at shutdown; an existing unlimited cache buffer suffices.
			ob_start();
		}
	}

	public static function shortcode( $return, $tag ) {
		if ( in_array( $tag, self::personal_shortcodes(), true ) ) {
			self::prevent();
		}
		return $return;
	}

	/** Runs for native calls and theme overrides, including empty and not-found views. */
	public static function template( $name ): void {
		if ( ( 0 === strpos( basename( $name ), 'ti-wishlist' ) && 'ti-wishlist-product-counter.php' !== basename( $name ) )
			|| 'ti-miniwishlist.php' === basename( $name ) || 'ti-addtowishlist-dialogbox.php' === basename( $name ) ) {
			self::prevent();
		}
	}

	public static function rest( $response, $server, $request ) {
		if ( preg_match( '#^/wc/v3/wishlist(?:/|$)#i', $request->get_route() ) ) {
			self::prevent();
			$response = rest_ensure_response( $response );
			if ( $response instanceof WP_HTTP_Response ) {
				$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private' );
			}
		}
		return $response;
	}
}
