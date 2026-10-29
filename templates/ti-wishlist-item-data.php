<?php
/**
 * The Template for displaying variation product data.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/ti-wishlist-item-data.php.
 *
 * @version             2.11.0
 * @package           TInvWishlist\Template
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

?>
<dl class="variation">
	<?php foreach ($item_data as $data) : ?>
		<?php if ($data['key']) { ?>
			<dt class="variation-<?php echo sanitize_html_class($data['key']); ?>"><?php echo esc_html($data['key']); ?>
				:
			</dt>
		<?php } ?>
		<?php if (is_numeric($data['display']) || !empty($data['display'])) { ?>
			<dd class="variation-<?php echo sanitize_html_class($data['key']); ?>"><?php if ( true === ( $data['display_html'] ?? false ) ) {
				// Saved attachment previews may contain passive markup, never upload or form controls.
				echo wp_kses( $data['display'], array(
					'table' => array( 'class' => true ),
					'tbody' => array(), 'tr' => array(),
					'td' => array( 'class' => true, 'style' => true ),
					'a' => array( 'href' => true, 'title' => true, 'class' => true, 'itemprop' => true ),
					'img' => array( 'src' => true, 'alt' => true, 'title' => true, 'class' => true, 'style' => true, 'width' => true, 'height' => true ),
				) );
			} else { echo esc_html( $data['display'] ); } ?></dd>
		<?php } ?>
	<?php endforeach; ?>
</dl>
