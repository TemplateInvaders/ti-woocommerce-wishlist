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
			<div class="tinvwl-pic-col col-lg-8">
				<div class="col-lg-2">
					<a href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?utm_source=<?php echo TINVWL_UTM_SOURCE;// WPCS: xss ok. ?>&utm_campaign=<?php echo TINVWL_UTM_CAMPAIGN;// WPCS: xss ok. ?>&utm_medium=<?php echo TINVWL_UTM_MEDIUM;// WPCS: xss ok. ?>&utm_content=premium_explore_logo&partner=<?php echo TINVWL_UTM_SOURCE;// WPCS: xss ok. ?>">
						<i class="premium_adv"></i>
					</a>
				</div>
				<div class="col-lg-4">
					<h2><?php esc_html_e( 'Premium version', 'ti-woocommerce-wishlist' ) ?></h2>
					<p><?php esc_html_e( 'benefit from all the features', 'ti-woocommerce-wishlist' ) ?></p>
				</div>
				<div class="col-lg-6">
					<a href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?utm_source=<?php echo TINVWL_UTM_SOURCE;// WPCS: xss ok. ?>&utm_campaign=<?php echo TINVWL_UTM_CAMPAIGN;// WPCS: xss ok. ?>&utm_medium=<?php echo TINVWL_UTM_MEDIUM;// WPCS: xss ok. ?>&utm_content=premium_explore&partner=<?php echo TINVWL_UTM_SOURCE;// WPCS: xss ok. ?>"
					   class="tinvwl-btn white round"><?php esc_html_e( 'check premium options', 'ti-woocommerce-wishlist' ) ?></a>
				</div>
			</div>
			<div class="tinvwl-sup-col col-lg-4">
				<div class="half-containers money-back">
					<a href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?utm_source=<?php echo TINVWL_UTM_SOURCE;// WPCS: xss ok. ?>&utm_campaign=<?php echo TINVWL_UTM_CAMPAIGN;// WPCS: xss ok. ?>&utm_medium=<?php echo TINVWL_UTM_MEDIUM;// WPCS: xss ok. ?>&utm_content=money_back&partner=<?php echo TINVWL_UTM_SOURCE;// WPCS: xss ok. ?>">
						<span>Money Back Guarantee</span>
					</a>
					<p><?php esc_html_e( '100% No-Risk 14-Days Money Back Guarantee', 'ti-woocommerce-wishlist' ) ?></p>
				</div>
			</div>
		</div>
	</div>
</section>
