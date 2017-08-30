<?php
/**
 * The Template for displaying variation product data.
 *
 * @version             1.0.0
 * @package           TInvWishlist\Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<div class="variation">
	<?php foreach ( $item_data as $data ) : ?>
	<span class="variation-<?php echo sanitize_html_class( $data['key'] ); ?>"><?php echo wp_kses_post( $data['key'] ); ?> - </span>
		<span class="variation-<?php echo sanitize_html_class( $data['key'] ); ?>"><?php echo wp_kses_post( $data['display'] ); ?></span>
		<br>
	<?php endforeach; ?>
</div>
