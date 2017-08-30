<?php
/**
 * The Template for displaying header for wishlist.
 *
 * @version             1.0.0
 * @package           TInvWishlist\Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<div class="tinv-header">
	<h2><?php echo esc_html( apply_filters( 'tinvwl_wishlist_header_title', $wishlist['title'], $wishlist ) ); ?></h2>
	<?php do_action( 'tinvwl_in_title_wishlist', $wishlist ); ?>
</div>
