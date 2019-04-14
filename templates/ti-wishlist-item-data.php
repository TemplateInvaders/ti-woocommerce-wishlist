<?php
/**
 * The Template for displaying variation product data.
 *
 * @version             1.10.1
 * @package           TInvWishlist\Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<dl class="variation">
	<?php foreach ( $item_data as $data ) : ?>
		<?php if ( $data['key'] ) { ?>
			<dt class="variation-<?php echo sanitize_html_class( $data['key'] ); ?>"><?php echo wp_kses_post( $data['key'] ); ?>:</dt>
		<?php } ?>
		<?php if ( $data['display'] ) { ?>
			<dd class="variation-<?php echo sanitize_html_class( $data['key'] ); ?>"><?php echo wp_kses_post( $data['display'] ); ?></dd>
		<?php } ?>
	<?php endforeach; ?>
</dl>
