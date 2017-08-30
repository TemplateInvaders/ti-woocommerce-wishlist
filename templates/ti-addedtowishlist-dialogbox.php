<?php
/**
 * The Template for displaying dialog for message added to wishlist product.
 *
 * @version             1.0.0
 * @package           TInvWishlist\Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<div class="tinvwl_added_to_wishlist tinv-modal tinv-modal-open">
	<div class="tinv-overlay"></div>
	<div class="tinv-table">
		<div class="tinv-cell">
			<div class="tinv-modal-inner">
				<i class="<?php echo esc_attr( $icon ); ?>"></i>
				<div class="tinv-txt"><?php echo $msg; // WPCS: xss ok. ?></div>
				<div class="tinvwl-buttons-group tinv-wishlist-clear">
					<button class="button tinvwl_button_close" type="button"><i class="fa fa-times"></i><?php esc_html_e( 'Close', 'ti-woocommerce-wishlist' ); ?></button>
					<?php if ( isset( $wishlist_url ) ) : ?>
						<button class="button tinvwl_button_view tinvwl-btn-onclick" data-url="<?php echo esc_url( $wishlist_url ); ?>" type="button"><i class="fa fa-heart-o"></i><?php echo esc_html( tinv_get_option( 'general', 'text_browse' ) ); ?></button>
					<?php endif; ?>
				</div>
				<div class="tinv-wishlist-clear"></div>
			</div>
		</div>
	</div>
</div>
