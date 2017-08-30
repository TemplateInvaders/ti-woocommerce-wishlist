<?php
/**
 * The Template for displaying social buttons.
 *
 * @version             1.0.0
 * @package           TInvWishlist\Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<div class="social-buttons">
	<?php if ( ! empty( $share_on ) ) : ?>
		<span><?php echo esc_html( $share_on )?></span>
	<?php endif; ?>
	<ul>
		<?php foreach ( $social as $social_name => $social_url ) {
			?>
		<li><a target="__blank" title="<?php echo esc_attr( $social_name ); ?>" href="<?php echo esc_url( $social_url ); ?>" class="social social-<?php echo esc_attr( $social_name ) . ' ' . esc_attr( tinv_style( '.tinv-wishlist .social-buttons li a', '-ti-background' ) ); ?>"><?php echo esc_html( substr( $social_name, 0, 1 ) ); ?></a></li>
			<?php } ?>
	</ul>
</div>
