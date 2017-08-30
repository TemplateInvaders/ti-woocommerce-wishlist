<?php
/**
 * The Template for displaying admin premium features notice this plugin.
 *
 * @since             1.0.0
 * @package           TInvWishlist\Admin\Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<section class="tinvwl-premium-feat tinvwl-panel w-shadow w-bg">
	<div class="container-fluid">
		<div class="row">
			<div class="tinvwl-pic-col col-lg-4">
				<div class="tinvwl-feat-col-inner">
					<h3><?php esc_html_e( 'benefit from all the', 'ti-woocommerce-wishlist' ) ?></h3>
					<h2><?php esc_html_e( 'features', 'ti-woocommerce-wishlist' ) ?></h2>
					<i class="premium_adv"></i>
					<a href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?utm_source=<?php echo TINVWL_UTM_SOURCE;// WPCS: xss ok. ?>&utm_campaign=<?php echo TINVWL_UTM_CAMPAIGN;// WPCS: xss ok. ?>&utm_medium=<?php echo TINVWL_UTM_MEDIUM;// WPCS: xss ok. ?>&utm_content=premium_explore&partner=<?php echo TINVWL_UTM_SOURCE;// WPCS: xss ok. ?>"
					   class="tinvwl-btn red round"><?php esc_html_e( 'explore premium features', 'ti-woocommerce-wishlist' ) ?></a>
				</div>
			</div>
			<div class="tinvwl-feat-col col-lg-4">
				<div class="tinvwl-feat-col-inner w-bg-grey">
					<h3><?php esc_html_e( 'some of the premium features', 'ti-woocommerce-wishlist' ) ?></h3>
					<ul class="tinvwl-features">
						<li>
							<a href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?utm_source=<?php echo TINVWL_UTM_SOURCE; // WPCS: xss ok. ?>&utm_campaign=<?php echo TINVWL_UTM_CAMPAIGN; // WPCS: xss ok. ?>&utm_medium=<?php echo TINVWL_UTM_MEDIUM; // WPCS: xss ok. ?>&utm_content=premium_features&partner=<?php echo TINVWL_UTM_SOURCE; // WPCS: xss ok. ?>#manage"><i
									class="fa fa-check"></i><span><?php esc_html_e( 'Multi-Wishlist - allow customer to create wshlists.', 'ti-woocommerce-wishlist' ) ?></span></a>
						</li>
						<li>
							<a href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?utm_source=<?php echo TINVWL_UTM_SOURCE; // WPCS: xss ok. ?>&utm_campaign=<?php echo TINVWL_UTM_CAMPAIGN; // WPCS: xss ok. ?>&utm_medium=<?php echo TINVWL_UTM_MEDIUM; // WPCS: xss ok. ?>&utm_content=premium_features&partner=<?php echo TINVWL_UTM_SOURCE; // WPCS: xss ok. ?>#follow"><i
									class="fa fa-check"></i><span><?php esc_html_e( 'Follow - customers can follow each others wishlists', 'ti-woocommerce-wishlist' ) ?></span></a>
						</li>
						<li>
							<a href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?utm_source=<?php echo TINVWL_UTM_SOURCE; // WPCS: xss ok. ?>&utm_campaign=<?php echo TINVWL_UTM_CAMPAIGN; // WPCS: xss ok. ?>&utm_medium=<?php echo TINVWL_UTM_MEDIUM; // WPCS: xss ok. ?>&utm_content=premium_features&partner=<?php echo TINVWL_UTM_SOURCE; // WPCS: xss ok. ?>#other"><i
									class="fa fa-check"></i><span><?php esc_html_e( 'Advanced Processing Options', 'ti-woocommerce-wishlist' ) ?></span></a>
						</li>
						<li>
							<a href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?utm_source=<?php echo TINVWL_UTM_SOURCE; // WPCS: xss ok. ?>&utm_campaign=<?php echo TINVWL_UTM_CAMPAIGN; // WPCS: xss ok. ?>&utm_medium=<?php echo TINVWL_UTM_MEDIUM; // WPCS: xss ok. ?>&utm_content=premium_features&partner=<?php echo TINVWL_UTM_SOURCE; // WPCS: xss ok. ?>#shortcodes"><i
									class="fa fa-check"></i><span><?php esc_html_e( 'Shortcodes & Widgets: Search, Recent Wishlists', 'ti-woocommerce-wishlist' ) ?></span></a>
						</li>
						<li>
							<a href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?utm_source=<?php echo TINVWL_UTM_SOURCE; // WPCS: xss ok. ?>&utm_campaign=<?php echo TINVWL_UTM_CAMPAIGN; // WPCS: xss ok. ?>&utm_medium=<?php echo TINVWL_UTM_MEDIUM; // WPCS: xss ok. ?>&utm_content=premium_features&partner=<?php echo TINVWL_UTM_SOURCE; // WPCS: xss ok. ?>#manage"><i
									class="fa fa-check"></i><span><?php esc_html_e( 'Allow to change product quantity in wishlist table', 'ti-woocommerce-wishlist' ) ?></span></a>
						</li>
						<li>
							<a href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?utm_source=<?php echo TINVWL_UTM_SOURCE; // WPCS: xss ok. ?>&utm_campaign=<?php echo TINVWL_UTM_CAMPAIGN; // WPCS: xss ok. ?>&utm_medium=<?php echo TINVWL_UTM_MEDIUM; // WPCS: xss ok. ?>&utm_content=premium_features&partner=<?php echo TINVWL_UTM_SOURCE; // WPCS: xss ok. ?>#login"><i
									class="fa fa-check"></i><span><?php esc_html_e( 'Allow guests to create wishlists', 'ti-woocommerce-wishlist' ) ?></span></a>
						</li>
						<li>
							<a href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?utm_source=<?php echo TINVWL_UTM_SOURCE; // WPCS: xss ok. ?>&utm_campaign=<?php echo TINVWL_UTM_CAMPAIGN; // WPCS: xss ok. ?>&utm_medium=<?php echo TINVWL_UTM_MEDIUM; // WPCS: xss ok. ?>&utm_content=premium_features&partner=<?php echo TINVWL_UTM_SOURCE; // WPCS: xss ok. ?>#emails"><i
									class="fa fa-check"></i><span><?php esc_html_e( 'Ask for an Estimate', 'ti-woocommerce-wishlist' ) ?></span></a>
						</li>
						<li>
							<a href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?utm_source=<?php echo TINVWL_UTM_SOURCE; // WPCS: xss ok. ?>&utm_campaign=<?php echo TINVWL_UTM_CAMPAIGN; // WPCS: xss ok. ?>&utm_medium=<?php echo TINVWL_UTM_MEDIUM; // WPCS: xss ok. ?>&utm_content=premium_features&partner=<?php echo TINVWL_UTM_SOURCE; // WPCS: xss ok. ?>#button"><i
									class="fa fa-check"></i><span><?php esc_html_e( 'A lot of other features...', 'ti-woocommerce-wishlist' ) ?></span></a>
						</li>
					</ul>
				</div>
			</div>
			<div class="tinvwl-sup-col col-lg-4">
				<div class="tinvwl-feat-col-inner">
					<div class="tinvwl-img-w-desc tinvwl-table auto-width"><i class="admin-rescue"></i>
						<div class="tinvwl-cell">
							<h5><?php esc_html_e( 'Dedicated Support', 'ti-woocommerce-wishlist' ) ?></h5>
							<div
								class="tinvwl-desc"><?php esc_html_e( 'Direct help from our qualified support team', 'ti-woocommerce-wishlist' ) ?></div>
						</div>
					</div>

					<div class="tinvwl-img-w-desc tinvwl-table auto-width"><i class="admin-update"></i>
						<div class="tinvwl-cell">
							<h5><?php esc_html_e( 'Live Updates', 'ti-woocommerce-wishlist' ) ?></h5>
							<div
								class="tinvwl-desc"><?php esc_html_e( 'Stay up to date with automatic updates', 'ti-woocommerce-wishlist' ) ?></div>
						</div>
					</div>
					<div class="tinvwl-desc">
						<?php esc_html_e( 'By purchasing the premium version of our plugin, you will not only take advantage of the premium features but also get dedicated support and free updates directly to your admin panel.', 'ti-woocommerce-wishlist' ) ?>
						<div class="tinv-wishlist-clear"></div>
						<a href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?utm_source=<?php echo TINVWL_UTM_SOURCE; // WPCS: xss ok. ?>&utm_campaign=<?php echo TINVWL_UTM_CAMPAIGN; // WPCS: xss ok. ?>&utm_medium=<?php echo TINVWL_UTM_MEDIUM; // WPCS: xss ok. ?>&utm_content=premium_features&partner=<?php echo TINVWL_UTM_SOURCE; // WPCS: xss ok. ?>"><?php esc_html_e( 'Get Support & Premium Features', 'ti-woocommerce-wishlist' ) ?></a>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>
