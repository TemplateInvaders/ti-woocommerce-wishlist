<?php
/**
 * The Template for displaying add to wishlist product button.
 *
 * @version             1.0.0
 * @package           TInvWishlist\Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<input type="hidden" name="product_id" value="<?php echo esc_attr( version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->id : ( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() ) ); ?>" />
<?php if ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->variation_id : ( $product->is_type( 'variation' ) ? $product->get_id() : 0 ) ) { ?>
	<input type="hidden" name="variation_id" value="<?php echo esc_attr( version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->variation_id : ( $product->is_type( 'variation' ) ? $product->get_id() : 0 ) ); ?>" />
<?php } ?>
<div class="tinv-wraper woocommerce tinv-wishlist <?php echo esc_attr( $class_postion )?>">
	<?php do_action( 'tinv_wishlist_addtowishlist_button' ); ?>
	<?php do_action( 'tinv_wishlist_addtowishlist_dialogbox' ); ?>
	<div class="tinvwl-tooltip"><?php esc_html_e( 'Add to Wishlist', 'ti-woocommerce-wishlist' ); ?></div>
</div>
