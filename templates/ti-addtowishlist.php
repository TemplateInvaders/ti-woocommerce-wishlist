<?php
/**
 * The Template for displaying add to wishlist product button.
 *
 * @version             1.9.2
 * @package           TInvWishlist\Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
wp_enqueue_script( 'tinvwl' );
?>
<div class="tinv-wraper woocommerce tinv-wishlist <?php echo esc_attr( $class_postion ) ?>">
	<?php do_action( 'tinv_wishlist_addtowishlist_button' ); ?>
	<?php do_action( 'tinv_wishlist_addtowishlist_dialogbox' ); ?>
	<div class="tinvwl-tooltip"><?php echo esc_html( tinv_get_option( 'add_to_wishlist' . ( $loop ? '_catalog' : '' ), 'text' ) ); ?></div>
</div>
