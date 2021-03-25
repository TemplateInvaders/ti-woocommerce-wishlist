<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name AutomateWoo
 *
 * @version 5.3.0
 *
 * @slug automatewoo
 *
 * @url https://automatewoo.com
 *
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "automatewoo";

$name = "AutomateWoo";

$available = class_exists('AutomateWoo');

$tinvwl_integrations = is_array($tinvwl_integrations) ? $tinvwl_integrations : [];

$tinvwl_integrations[$slug] = array(
	'name' => $name,
	'available' => $available,
);

if (!tinv_get_option('integrations', $slug)) {
	return;
}

if (!$available) {
	return;
}

add_filter('automatewoo/triggers', 'tinvwl_automatewoo_triggers');

/**
 * @param array $triggers
 *
 * @return array
 */
function tinvwl_automatewoo_triggers($triggers)
{
	try {
		// AutomateWoo Wishlist class
		include_once 'automatewoo/wishlist.class.php';

		// Trigger wishlist item added.
		include_once 'automatewoo/trigger-wishlist-item-added.php';
		$triggers['tinvwl_wishlist_item_added'] = 'TINVWL_Trigger_Wishlist_Item_Added';

		// Trigger wishlist reminder.
		include_once 'automatewoo/trigger-wishlist-reminder.php';
		$triggers['tinvwl_wishlist_reminder'] = 'TINVWL_Trigger_Wishlist_Reminder';

		// Trigger wishlist item added to cart.
		include_once 'automatewoo/trigger-wishlist-item-added-to-cart.php';
		$triggers['tinvwl_wishlist_item_added_to_cart'] = 'TINVWL_Trigger_Wishlist_Item_Added_To_Cart';

		// Trigger wishlist item purchased.
		include_once 'automatewoo/trigger-wishlist-item-purchased.php';
		$triggers['tinvwl_wishlist_item_purchased'] = 'TINVWL_Trigger_Wishlist_Item_Purchased';

		// Trigger wishlist item removed.
		include_once 'automatewoo/trigger-wishlist-item-removed.php';
		$triggers['tinvwl_wishlist_item_removed'] = 'TINVWL_Trigger_Wishlist_Item_Removed';
	} catch (Exception $e) {

		error_log(print_r($e->getMessage(), true));
	}
	return $triggers;
}
