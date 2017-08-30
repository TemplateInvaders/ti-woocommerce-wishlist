<?php
/**
 * The Template for displaying not found wishlist.
 *
 * @version             1.0.0
 * @package           TInvWishlist\Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<p class="cart-empty">
	<?php esc_html_e( 'Wishlist is not found!', 'ti-woocommerce-wishlist' ); ?>
</p>

<?php do_action( 'tinvwl_wishlist_is_null' ); ?>

<p class="return-to-shop">
	<a class="button wc-backward" href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>"><?php esc_html_e( 'Return To Shop', 'ti-woocommerce-wishlist' ); ?></a>
</p>
