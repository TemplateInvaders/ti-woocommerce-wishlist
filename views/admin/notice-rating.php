<?php
/**
 * The Template for displaying admin premium features notice this plugin.
 *
 * @since             1.0.0
 * @package           TInvWishlist\Admin\Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<div id="message" class="notice notice-info tinv-notice-rating" style="position: relative;">
	<?php echo wp_kses_post( wpautop( $message ) ); ?>
	<p>
		<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'ti-hide-notice', $name, add_query_arg( 'ti-hide-notice-trigger', $key, add_query_arg( 'ti-redirect', 'true' ) ) ), 'ti_hide', '_ti_notice_nonce' ) ); ?>" class="button-primary"><?php esc_html_e( "It's awesome!", 'ti-woocommerce-wishlist' ); ?> &#9733;&#9733;&#9733;&#9733;&#9733;</a>
		<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'ti-hide-notice', $name, add_query_arg( 'ti-hide-notice-trigger', $key ) ), 'ti_hide', '_ti_notice_nonce' ) ); ?>" class="button-secondary"><?php esc_html_e( 'I want to test a bit more', 'ti-woocommerce-wishlist' ); ?></a>
		<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'ti-hide-notice', $name, add_query_arg( 'ti-hide-notice-trigger', $key ) ), 'ti_remove', '_ti_notice_nonce' ) ); ?>" class="notice-dismiss" style="text-decoration: none;"><span class="screen-reader-text"><?php esc_html_e( 'Meh, do not bother me', 'ti-woocommerce-wishlist' ); ?></span></a>
	</p>
</div>
