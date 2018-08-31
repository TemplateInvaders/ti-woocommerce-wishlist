<?php
/**
 * The Template for displaying add to wishlist product button.
 *
 * @version             1.8.8
 * @package           TInvWishlist\Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
wp_enqueue_script( 'tinvwl' );
?>
<div class="tinv-wraper woocommerce tinv-wishlist <?php echo esc_attr( $class_postion ) ?>">
	<input type="hidden" name="product_id"
	       value="<?php echo esc_attr( ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->get_id() : ( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() ) ) ); ?>"/>
	<?php if ( $variation_id ) { ?>
		<input type="hidden" name="variation_id" value="<?php echo esc_attr( $variation_id ); ?>"/>
		<?php

		$_variation = wc_get_product( $variation_id );
		$attributes = $_variation->get_variation_attributes();

		foreach ( $attributes as $name => $value ) {
			echo '<input type="hidden" name="' . $name . '" value="' . $value . '" />';// WPCS: XSS ok.
		}

		?>
	<?php } ?>
	<?php do_action( 'tinv_wishlist_addtowishlist_button' ); ?>
	<?php do_action( 'tinv_wishlist_addtowishlist_dialogbox' ); ?>
	<div
		class="tinvwl-tooltip"><?php echo esc_html( tinv_get_option( 'add_to_wishlist' . ( $loop ? '_catalog' : '' ), 'text' ) ); ?></div>
</div>
