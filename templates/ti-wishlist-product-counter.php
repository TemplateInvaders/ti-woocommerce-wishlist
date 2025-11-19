<?php
/**
 * The Template for displaying dropdown wishlist products.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/ti-wishlist-product-counter.php.
 *
 * @version             2.11.0
 * @package           TInvWishlist\Template
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

wp_enqueue_script('tinvwl');

$text_plain = isset($text) ? (string)$text : '';

$icon_img_html = '';
if ($icon_class && 'custom' === $icon && !empty($icon_upload)) {
	$icon_img_html = sprintf(
		'<img alt="%s" src="%s" /> ',
		apply_filters('tinvwl_default_wishlist_title', tinv_get_option('general', 'default_title')),
		esc_url($icon_upload)
	);
}
$text_attr = wp_strip_all_tags($text_plain);
?>
<a href="<?php echo esc_url(tinv_url_wishlist_default()); ?>"
   name="<?php echo esc_attr(sanitize_title($text_attr)); ?>"
   aria-label="<?php echo esc_attr($text_attr); ?>"
   class="wishlist_products_counter<?php echo ' ' . $icon_class . ' ' . $icon_style . (empty($text_attr) ? ' no-txt' : '') . (0 < $counter ? ' wishlist-counter-with-products' : ''); ?>">
	<?php
	if ($icon_img_html) {
		echo $icon_img_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	?>
	<span class="wishlist_products_counter_text"><?php echo esc_html($text_attr); ?></span>
	<?php if ($show_counter) : ?>
		<span class="wishlist_products_counter_number"></span>
	<?php endif; ?>
</a>
