<?php
/**
 * The Template for displaying admin premium features this plugin.
 *
 * @since             2.6.0
 * @package           TInvWishlist\Admin\Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Get the time difference in a human-readable format.
 *
 * @return string Time difference in a human-readable format.
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 */
function tinvwl_installed_ago(): string {
	global $wpdb;

	$lists_table = sprintf( '%s%s_%s', $wpdb->prefix, TINVWL_PREFIX, 'lists' );

	$date = $wpdb->get_var( "SELECT `date` FROM `{$lists_table}` ORDER BY `ID` ASC" );

	$timestamp = $date ? strtotime( $date ) : time();

	$date1 = new DateTime(); // current date
	$date2 = ( new DateTime() )->setTimestamp( $timestamp ); // your timestamp

	$interval = $date1->diff( $date2 );

	$years = $interval->y;
	$days  = $interval->d;

	// If the difference is 0 days, show as 1 day
	if ( $days === 0 ) {
		$days = 1;
	}

	if ( $years > 0 ) {
		$yearString = $years === 1 ? ' ' . esc_html__( 'year', 'ti-woocommerce-wishlist' ) . ' ' : ' ' . esc_html__( 'years', 'ti-woocommerce-wishlist' ) . ' ';
		$dayString  = $days === 1 ? ' ' . esc_html__( 'day', 'ti-woocommerce-wishlist' ) : ' ' . esc_html__( 'days', 'ti-woocommerce-wishlist' );

		return $years . $yearString . $days . $dayString;
	} else {
		$dayString = $days === 1 ? ' ' . esc_html__( 'day', 'ti-woocommerce-wishlist' ) : ' ' . esc_html__( 'days', 'ti-woocommerce-wishlist' );

		return $days . $dayString;
	}
}

/**
 * Get the total number of wishlists.
 *
 * @return string Total number of wishlists.
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 */
function tinvwl_wishlists_total(): string {
	global $wpdb;

	$lists_table = sprintf( '%s%s_%s', $wpdb->prefix, TINVWL_PREFIX, 'lists' );

	$count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$lists_table}`" );

	return (string) $count;
}

?>
<style>
    .tinvwl-content h2 a {
        color: #524737;
        font-weight: 700;
        display: inline-block;
        margin-bottom: 30px;
    }

    .tinvwl-content h2 a span {
        color: #ff5739;
    }

    .tinvwl-content h3 {
        color: #595858;
        margin-bottom: 20px;
    }

    .tinvwl-content h3 span {
        color: #524737;
        font-weight: 700;
    }

    .tinvwl-content .tinvwl-btn.red i {
        color: #ffdc00;
    }

    .tinvwl-title sup {
        color: #ff5739;
        font-size: .7em;
    }
</style>
<section class="tinvwl-panel">
    <div class="container-fluid">
        <div class="row">
            <div style="text-align: center; padding:10px 25px;">
                <h2>
                    <a href="<?php echo esc_url( "https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?utm_source=" . TINVWL_UTM_SOURCE . "&utm_campaign=" . TINVWL_UTM_CAMPAIGN . "&utm_medium=" . TINVWL_UTM_MEDIUM . "&utm_content=wishlists&partner=" . TINVWL_UTM_SOURCE ); ?>">
						<?php echo esc_html__( 'Unlock the Full Potential of Your Wishlists with', 'ti-woocommerce-wishlist' ); ?>
                        <span><?php echo esc_html__( 'Premium!', 'ti-woocommerce-wishlist' ); ?></span>
                    </a>
                </h2>

                <h3>
					<?php echo sprintf(
						esc_html__( "You've been enjoying the %s plugin for %s.", 'ti-woocommerce-wishlist' ),
						'<span>' . esc_html__( 'TI WooCommerce Wishlists', 'ti-woocommerce-wishlist' ) . '</span>',
						'<span>' . tinvwl_installed_ago() . '</span>'
					); ?>
                </h3>

                <h3>
					<?php echo sprintf(
						esc_html__( 'Your customers have created %s wishlists.', 'ti-woocommerce-wishlist' ),
						'<span>' . tinvwl_wishlists_total() . '</span>'
					); ?>
                </h3>

            </div>
        </div>
    </div>
</section>
<section class="tinvwl-panel" style="margin-top:-30px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8 col-md-offset-2 tinvwl-panel  w-shadow w-bg">
                <div style="text-align: center; padding:14px 0 10px;">
                    <a href="<?php echo esc_url( "https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?utm_source=" . TINVWL_UTM_SOURCE . "&utm_campaign=" . TINVWL_UTM_CAMPAIGN . "&utm_medium=" . TINVWL_UTM_MEDIUM . "&utm_content=upgrade&partner=" . TINVWL_UTM_SOURCE ); ?>">
                        <img src="<?php echo esc_url( TINVWL_URL . 'assets/img/wishlists_table.png' ); ?>"
                             alt="<?php echo esc_attr__( 'Unlock comprehensive statistics and take your insights to the next level!', 'ti-woocommerce-wishlist' ); ?>"/>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
<section class="tinvwl-panel">
    <div class="container-fluid">
        <div class="row">
            <div style="text-align: center; padding:10px 25px;">
                <h2>
                    <a href="<?php echo esc_url( "https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?utm_source=" . TINVWL_UTM_SOURCE . "&utm_campaign=" . TINVWL_UTM_CAMPAIGN . "&utm_medium=" . TINVWL_UTM_MEDIUM . "&utm_content=wishlists&partner=" . TINVWL_UTM_SOURCE ); ?>">
						<?php echo esc_html__( 'Unlock comprehensive statistics and take your insights to the next level!', 'ti-woocommerce-wishlist' ); ?>
                    </a>
                </h2>
				<?php echo sprintf(
					'<a class="tinvwl-btn red w-icon smaller-txt" href="%s"><i class="ftinvwl ftinvwl-star"></i><span class="tinvwl-txt">%s</span></a>',
					esc_url( "https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?utm_source=" . TINVWL_UTM_SOURCE . "&utm_campaign=" . TINVWL_UTM_CAMPAIGN . "&utm_medium=" . TINVWL_UTM_MEDIUM . "&utm_content=wishlists_upgrade&partner=" . TINVWL_UTM_SOURCE ),
					esc_html__( 'Upgrade to Premium', 'ti-woocommerce-wishlist' )
				); ?>
            </div>
        </div>
    </div>
</section>
