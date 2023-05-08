<?php
/**
 * Admin Notices plugin class
 *
 * @since             2.0.9
 * @package           TInvWishlist
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Admin notices plugin class
 */
class TInvWL_Admin_Notices {
	/**
	 * This class
	 *
	 * @var TInvWL_Admin_Notices
	 */
	protected static $_instance = null;

	/**
	 * WordPress.org review URL
	 */
	const REVIEW_URL = 'https://wordpress.org/support/plugin/ti-woocommerce-wishlist/reviews/?filter=5';

	/**
	 * Get this class object
	 *
	 * @return TInvWL_Admin_Notices
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Constructor
	 */
	function __construct( $plugin_name = TINVWL_PREFIX ) {
		global $wpdb;

		$this->_name = $plugin_name;
		$this->table = sprintf( '%s%s_%s', $wpdb->prefix, $this->_name, 'lists' );

		if ( ! $this->activation_date = get_option( 'tinvwl_activation_date' ) ) {
			$this->activation_date = $this->get_first_wishlist_date();
		}

		add_action( 'admin_notices', array( $this, 'add_notices' ) );

		add_action( 'wp_ajax_tinvwl_admin_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
	}

	/**
	 * Dismiss admin notice
	 *
	 * @return void
	 */
	function ajax_dismiss_notice() {
		if ( check_admin_referer( 'tinvwl_admin_dismiss_notice', 'nonce' ) && isset( $_REQUEST['tinvwl_type'] ) ) {

			$notice_type = sanitize_key( $_REQUEST['tinvwl_type'] );

			update_user_meta( get_current_user_id(), $notice_type, true );
			set_transient( 'tinvwl-admin-notice-delay', true, 14 * DAY_IN_SECONDS );

			wp_send_json( $notice_type );
		}

		wp_die();
	}

	/**
	 * Get plugin activation data
	 *
	 * @return false|int
	 */
	function get_first_wishlist_date() {
		global $wpdb;

		$date = $wpdb->get_var( "SELECT `date` FROM `{$this->table}` ORDER BY `ID` ASC" );

		$timestamp = $date ? strtotime( $date ) : strtotime( 'now' );

		add_option( 'tinvwl_activation_date', $timestamp );

		return $timestamp;
	}

	/**
	 * Print admin notice
	 *
	 * @return void
	 */
	function add_notices() {
		global $current_user;

		if ( strtotime( '14 days', $this->activation_date ) > strtotime( 'now' ) ) {
			return;
		}

		if ( get_transient( 'tinvwl-admin-notice-delay' ) ) {
			return;
		}

		$user_review  = ! get_user_meta( get_current_user_id(), 'tinvwl-user-review', true );
		$user_premium = ! get_user_meta( get_current_user_id(), 'tinvwl-user-premium', true );

		if ( ! ( $user_premium || $user_review ) ) {
			return;
		}

		?>
		<script>
			(function ($) {

				$(document).on('click', '.tinvwl-admin-notice .notice-dismiss, .tinvwl-notice-dismiss', function () {
					var $box = $(this).closest('.tinvwl-admin-notice'),
						isLink = $(this).attr('data-link') === 'follow' ? true : false,
						notice_type = $box.data('notice_type');

					$box.fadeOut(700);

					$.ajax({
						type: 'POST',
						url: ajaxurl,
						data: {
							tinvwl_type: notice_type,
							action: 'tinvwl_admin_dismiss_notice',
							nonce: '<?php echo esc_attr( wp_create_nonce( 'tinvwl_admin_dismiss_notice' ) ); ?>'
						}
					}).done(function (data) {

						setTimeout(function () {
							$box.remove();
						}, 700);

					});

					if (!isLink) {
						return false;
					}
				});
			})(jQuery);
		</script>
		<?php

		if ( $user_review ) {
			?>
			<div class="tinvwl-admin-notice notice notice-info is-dismissible"
				 data-notice_type="tinvwl-user-review">
				<div class="notice-container"
					 style="padding-top: 10px; padding-bottom: 10px; display: flex; justify-content: left; align-items: center;">
					<div class="notice-image">
						<img
							src="<?php echo TINVWL_URL . '/assets/img/premium_logo.png'; ?>"
							alt="<?php echo esc_html( TINVWL_NAME ); ?>">
					</div>
					<div class="notice-content" style="margin-left: 15px;">
						<p>
							<?php printf( __( "Hey %s, it's Stan from %s. You have used this free plugin for some time now, and I hope you like it!", 'ti-woocommerce-wishlist' ),
								'<strong>' . $current_user->display_name . '</strong>',
								'<strong>' . TINVWL_NAME . '</strong>'
							); ?>
							<br/>
							<?php _e( "Could you please give it a 5-star rating on WordPress? Your feedback will boost our motivation and help us promote and continue to improve this product.", 'ti-woocommerce-wishlist' ); ?>
						</p>
						<a href="<?php echo self::REVIEW_URL; ?>" target="_blank" data-link="follow"
						   class="button-secondary tinvwl-notice-dismiss" style="margin-right: 10px;">
							<span class="dashicons dashicons-star-filled"
								  style="color: #E6B800;font-size: 14px;line-height: 1.9;margin-left: -4px;"></span>
							<?php printf( __( "Review %s", 'ti-woocommerce-wishlist' ), TINVWL_NAME ); ?>
						</a>
						<a href="#" class="button-secondary tinvwl-notice-dismiss">
							<span class="dashicons dashicons-no-alt"
								  style="color: rgb(220, 58, 58);line-height: 2;font-size: 14px;margin-left: -4px;"></span>
							<?php _e( "No thanks", 'ti-woocommerce-wishlist' ); ?>
						</a>
					</div>
				</div>
			</div>
			<?php
			return;
		}

		if ( ! $user_review && $user_premium ) {
			?>
			<div class="tinvwl-admin-notice notice notice-info is-dismissible"
				 data-notice_type="tinvwl-user-premium">
				<div class="notice-container"
					 style="padding-top: 10px; padding-bottom: 10px; display: flex; justify-content: left; align-items: center;">
					<div class="notice-image">
						<img
							src="<?php echo TINVWL_URL . '/assets/img/premium_logo.png'; ?>"
							alt="<?php echo esc_html( TINVWL_NAME ); ?>">
					</div>
					<div class="notice-content" style="margin-left: 15px;">
						<p>
							<strong><?php esc_html_e( 'Hello! We have a special gift!', 'ti-woocommerce-wishlist' ); ?></strong>
							<br/>
							<?php printf( __( 'Today we want to make you a special gift. Using the %s coupon code before the next 48 hours you can get a %s on the premium version of the %s plugin.', 'ti-woocommerce-wishlist' ),
								'<strong>UPGRADE</strong>',
								'<strong>20% OFF</strong>',
								TINVWL_NAME . ' Premium'
							); ?>
						</p>
						<a href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?apply_coupon=UPGRADE"
						   target="_blank" data-link="follow"
						   class="button-secondary" style="margin-right: 10px;">
							<span class="dashicons dashicons-info"
								  style="color: #2271b1;font-size: 14px;line-height: 2;margin-left: -4px;"></span>
							<?php _e( "More info", 'ti-woocommerce-wishlist' ); ?>
						</a>
					</div>
				</div>
			</div>
			<?php
		}
	}
}
