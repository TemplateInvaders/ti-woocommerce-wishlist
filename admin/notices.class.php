<?php
/**
 * Admin Notices plugin class
 *
 * @since 2.0.9
 * @package TInvWishlist
 */

defined( 'ABSPATH' ) || exit; // Abort if accessed directly

/**
 * Admin notices plugin class
 */
class TInvWL_Admin_Notices {
	/**
	 * Instance of this class.
	 *
	 * @var TInvWL_Admin_Notices|null
	 */
	protected static ?TInvWL_Admin_Notices $_instance = null;

	/**
	 * WordPress.org review URL.
	 *
	 * @var string
	 */
	private const TINVWL_REVIEW_URL = 'https://wordpress.org/support/plugin/ti-woocommerce-wishlist/reviews/?filter=5';

	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	private string $_name;

	/**
	 * Database table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Activation date.
	 *
	 * @var false|int
	 */
	private $activation_date;

	/**
	 * Get instance of this class.
	 *
	 * @return TInvWL_Admin_Notices
	 */
	public static function instance(): TInvWL_Admin_Notices {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}

		global $wpdb;
		$this->_name = TINVWL_PREFIX;
		$this->table = $wpdb->prefix . $this->_name . '_lists';

		$this->activation_date = get_option( 'tinvwl_activation_date' ) ?: $this->get_first_wishlist_date();

		add_action( 'admin_notices', [ $this, 'add_notices' ] );
		add_action( 'wp_ajax_tinvwl_admin_dismiss_notice', [ $this, 'ajax_dismiss_notice' ] );


		$this->postpone_notifications();
	}

	/**
	 * Dismiss admin notice.
	 *
	 * @return void
	 */
	public function ajax_dismiss_notice(): void {
		check_admin_referer( 'tinvwl_admin_dismiss_notice', 'nonce' ) && isset( $_REQUEST['tinvwl_type'] ) ?
			$this->update_notice_status( sanitize_key( $_REQUEST['tinvwl_type'] ) ) : wp_die();
	}

	/**
	 * Update the status of a notice.
	 *
	 * @param string $notice_type Notice type.
	 *
	 * @return void
	 */
	private function update_notice_status( string $notice_type ): void {
		update_user_meta( get_current_user_id(), $notice_type, true );
		set_transient( 'tinvwl-admin-notice-delay', true, 14 * DAY_IN_SECONDS );
		wp_send_json( $notice_type );
	}

	/**
	 * Get plugin activation data.
	 *
	 * @return false|int
	 */
	private function get_first_wishlist_date() {
		global $wpdb;
		$date      = $wpdb->get_var( "SELECT `date` FROM `{$this->table}` ORDER BY `ID` ASC" );
		$timestamp = $date ? strtotime( $date ) : strtotime( 'now' );
		add_option( 'tinvwl_activation_date', $timestamp );

		return $timestamp;
	}

	/**
	 * Postpone notifications for 14 days.
	 *
	 * @return void
	 */
	private function postpone_notifications(): void {
		if ( ! get_option( 'tinvwl_notifications_postponed' ) ) {
			$timestamp_in_14_days = time() + ( 14 * DAY_IN_SECONDS );
			add_option( 'tinvwl_notifications_postponed', '1' );

			// Schedule the event to call the disable_notifications method
			wp_schedule_single_event( $timestamp_in_14_days, 'tinvwl_disable_notifications_event' );
		}
	}

	/**
	 * Disable notifications.
	 *
	 * @return void
	 */
	public static function disable_notifications(): void {
		delete_option( 'tinvwl_notifications_postponed' );
//		get_transient( 'tinvwl-admin-notice-delay' );
		tinv_update_option( 'chat', 'enabled', true );
	}

	/**
	 * Check if it's not the time to display the notice.
	 *
	 * @return bool
	 */
	private function is_not_display_time(): bool {
		return strtotime( '14 days', $this->activation_date ) > strtotime( 'now' ) ||
			   get_transient( 'tinvwl-admin-notice-delay' );
	}

	/**
	 * Check if current date is during the Black Friday period.
	 *
	 * The Black Friday period is defined as the last Friday of November
	 * and the two following days (Saturday and Sunday).
	 *
	 * @return bool True if it's Black Friday period, false otherwise.
	 */
	private function is_black_friday_period(): bool {
		$year        = date( 'Y' );
		$blackFriday = date( 'Y-m-d', strtotime( "last friday of November $year" ) );

		return $this->is_date_between( date( 'Y-m-d' ), $blackFriday, date( 'Y-m-d', strtotime( "$blackFriday +2 days" ) ) );
	}

	/**
	 * Check if current date is during the Cyber Monday event.
	 *
	 * Cyber Monday is defined as the next Monday following the Black Friday weekend.
	 *
	 * @return bool True if it's Cyber Monday period, false otherwise.
	 */
	private function is_cyber_monday_period(): bool {
		$year        = date( 'Y' );
		$cyberMonday = date( 'Y-m-d', strtotime( "last friday of November $year +3 days" ) );

		return $this->is_date_between( date( 'Y-m-d' ), $cyberMonday, date( 'Y-m-d', strtotime( "$cyberMonday +6 days" ) ) );
	}

	/**
	 * Check if current date is during the Winter Holidays.
	 *
	 * @return bool True if it's Winter Holiday period, false otherwise.
	 */
	private function is_winter_holiday_period(): bool {
		$year = (int) date( 'Y' );

		return $this->is_date_between( date( 'Y-m-d' ), $year . "-12-15", ( $year + 1 ) . "-01-15" );
	}

	/**
	 * Check if a date is between two dates.
	 *
	 * @param string $date The date to check.
	 * @param string $start Start date.
	 * @param string $end End date.
	 *
	 * @return bool True if date is between start and end, false otherwise.
	 */
	private function is_date_between( string $date, string $start, string $end ): bool {
		return $date >= $start && $date <= $end;
	}

	/**
	 * Check if it's not the time to display the regular notices.
	 *
	 * @return bool True if it's an event period, false otherwise.
	 */
	private function is_event_period(): bool {
		return $this->is_black_friday_period() ||
			   $this->is_cyber_monday_period() ||
			   $this->is_winter_holiday_period();
	}

	/**
	 * Print admin notice.
	 *
	 * @return void
	 */
	public function add_notices(): void {
		if ( $this->is_event_period() ) {
			$this->display_notice_script();
			$this->display_event_notices();

			return;
		}

		if ( $this->is_not_display_time() ) {
			return;
		}
		$this->display_notice_script();
		$this->display_user_notices();
	}

	/**
	 * Display the notice JavaScript.
	 *
	 * @return void
	 */
	private function display_notice_script(): void {
		?>
		<script>
			(function ($) {
				$(document).on('click', '.tinvwl-admin-notice .notice-dismiss, .tinvwl-notice-dismiss', function () {
					var $box = $(this).closest('.tinvwl-admin-notice'),
						notice_type = $box.data('notice_type'),
						isLink = $(this).data('link') === 'follow';

					$box.fadeOut(700, function () {
						$box.remove();
					});

					if (!isLink) {
						$.post(ajaxurl, {
							tinvwl_type: notice_type,
							action: 'tinvwl_admin_dismiss_notice',
							nonce: '<?php echo esc_attr( wp_create_nonce( "tinvwl_admin_dismiss_notice" ) ); ?>'
						});
						return false;
					}
				});
			})(jQuery);
		</script>
		<?php
	}

	/**
	 * Display user notices.
	 *
	 * @return void
	 */
	private function display_user_notices(): void {
		global $current_user;
		$user_review  = ! get_user_meta( get_current_user_id(), 'tinvwl-user-review', true );
		$user_premium = ! get_user_meta( get_current_user_id(), 'tinvwl-user-premium', true );

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
						<a href="<?php echo self::TINVWL_REVIEW_URL; ?>" target="_blank" data-link="follow"
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

	/**
	 * Display event-specific notices.
	 *
	 * @return void
	 */
	private function display_event_notices(): void {
		$current_user_id = get_current_user_id();

		if ( $this->is_black_friday_period() && ! $this->has_user_seen_notice( $current_user_id, 'tinvwl-user-black-friday' ) ) {
			$this->display_black_friday_notice();
		} elseif ( $this->is_cyber_monday_period() && ! $this->has_user_seen_notice( $current_user_id, 'tinvwl-user-cyber-monday' ) ) {
			$this->display_cyber_monday_notice();
		} elseif ( $this->is_winter_holiday_period() && ! $this->has_user_seen_notice( $current_user_id, 'tinvwl-user-winter-holiday' ) ) {
			$this->display_winter_holiday_notice();
		}
	}

	/**
	 * Check if a user has already seen a specific notice.
	 *
	 * @param int $user_id User ID.
	 * @param string $meta_key Meta key to check.
	 *
	 * @return bool True if user has seen the notice, false otherwise.
	 */
	private function has_user_seen_notice( int $user_id, string $meta_key ): bool {
		return (bool) get_user_meta( $user_id, $meta_key, true );
	}

	/**
	 * Display the Black Friday notice.
	 *
	 * @return void
	 */
	private function display_black_friday_notice(): void {
		?>
		<style>
			.tinvwl-admin-notice-black-friday {
				padding: 0 !important;
				border: none;
				background-color: #ff5739;
			}

			.tinvwl-admin-notice-black-friday .notice-dismiss:before {
				color: #ffffff
			}

			.tinvwl-admin-notice-black-friday .notice-dismiss:hover:before {
				color: #000000;
			}

			.tinvwl-admin-notice-black-friday .notice-container > div {
				display: flex;
				align-items: center;
				justify-content: center;
				text-align: center;
			}

			.tinvwl-admin-notice-black-friday .notice-logo {
				padding: 15px;
				width: max-content;
				overflow: hidden;
			}

			.tinvwl-admin-notice-black-friday .notice-event-name {
				padding: 15px 50px;
				background-color: #000000;
				font-size: 2.5vw;
			}

			.tinvwl-admin-notice-black-friday .notice-event-name p {
				font-size: 2.5vw;
			}

			.tinvwl-admin-notice-black-friday .notice-content {
				padding: 15px;
				flex-grow: 1;
			}

			.tinvwl-admin-notice-black-friday .notice-content p {
				font-size: 1.5vw;
			}

			.tinvwl-admin-notice-black-friday .notice-container {
				display: flex;
				flex-wrap: wrap;
			}

			@media (max-width: 1200px) {
				.tinvwl-admin-notice-black-friday .notice-event-name {
					padding: 15px 25px;
				}

				.tinvwl-admin-notice-black-friday .notice-event-name p {
					font-size: 2vw;
				}

				.tinvwl-admin-notice-black-friday .notice-logo {
					padding: 0;
				}

				.tinvwl-admin-notice-black-friday .notice-logo a {
					max-width: 50%;
				}

				.tinvwl-admin-notice-black-friday .notice-logo a img {
					width: 100%;
				}
			}

			@media (max-width: 960px) {
				.tinvwl-admin-notice-black-friday .notice-content {
					width: 100%;
					order: 3;
				}

				.tinvwl-admin-notice-black-friday .notice-content p {
					font-size: 2vw;
				}

				.tinvwl-admin-notice-black-friday .notice-logo {
					order: 1;
					background-color: #000000;
					z-index: 2;
				}

				.tinvwl-admin-notice-black-friday .notice-event-name p {
					font-size: 3.5vw;
				}

				.tinvwl-admin-notice-black-friday .notice-event-name {
					order: 2;
					margin-left: -107px;
				}

				.tinvwl-admin-notice-black-friday .notice-event-name {
					flex-grow: 1;
				}
			}


			@media (max-width: 600px) {
				.tinvwl-admin-notice-black-friday .notice-event-name {
					width: 100%;
					order: 1;
					margin: 0;
					padding: 5px 15px;
				}

				.tinvwl-admin-notice-black-friday .notice-event-name p {
					font-size: 6vw;
				}

				.tinvwl-admin-notice-black-friday .notice-logo {
					order: 2;
					background-color: transparent;
					padding: 15px;
				}

				.tinvwl-admin-notice-black-friday .notice-logo a {
					max-width: 70%;

				}

				.tinvwl-admin-notice-black-friday .notice-content {
					width: min-content;
					order: 3;
				}

				.tinvwl-admin-notice-black-friday .notice-content p {
					font-size: 4vw;
				}
			}
		</style>
		<div class="tinvwl-admin-notice tinvwl-admin-notice-black-friday notice is-dismissible"
			 data-notice_type="tinvwl-user-black-friday">
			<div class="notice-container"
				 style="padding:0; display: flex; justify-content: left; align-items: stretch;">
				<div class="notice-logo">
					<a href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?apply_coupon=BLACKFRIDAY"
					   target="_blank" data-link="follow">
						<img
							src="<?php echo TINVWL_URL . '/assets/img/premium_logo.png'; ?>"
							alt="<?php echo esc_html( TINVWL_NAME ); ?>">
					</a>
				</div>
				<div class="notice-event-name">
					<p>
						<a href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?apply_coupon=BLACKFRIDAY"
						   target="_blank"
						   data-link="follow"
						   style="text-decoration: none;text-transform: uppercase;color: #ffffff;"><strong><?php esc_html_e( 'Black friday deal', 'ti-woocommerce-wishlist' ); ?></strong></a>
					</p>
				</div>
				<div class="notice-content">
					<p style="color:#ffffff;margin:0;">
						<?php printf( __( 'Get %s on %s!', 'ti-woocommerce-wishlist' ),
							'<strong>30% OFF</strong>',
							TINVWL_NAME . ' Premium'
						); ?>
						<br>
						<?php printf( '<span style="color: #000000;font-weight: 700;">%s</span> ', __( 'Use code:', 'ti-woocommerce-wishlist' ) ); ?>
						<a href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?apply_coupon=BLACKFRIDAY"
						   target="_blank" data-link="follow"
						   style="color:#ffffff;padding:3px 15px;display: inline-block;background-color: #000000;text-decoration: none;text-transform: uppercase;font-weight: 700;">BLACKFRIDAY</a>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Display the Cyber Monday notice.
	 *
	 * @return void
	 */
	private function display_cyber_monday_notice(): void {
		?>
		<style>
			.tinvwl-admin-notice-cyber-monday {
				padding: 0 !important;
				border: none;
				background-color: #0d1025;
			}

			.tinvwl-admin-notice-cyber-monday .notice-dismiss:before {
				color: #ffffff
			}

			.tinvwl-admin-notice-cyber-monday .notice-dismiss:hover:before {
				color: #000000;
			}

			.tinvwl-admin-notice-cyber-monday .notice-container > div {
				display: flex;
				align-items: center;
				justify-content: center;
				text-align: center;
			}

			.tinvwl-admin-notice-cyber-monday .notice-logo {
				padding: 15px;
				width: max-content;
				overflow: hidden;
			}

			.tinvwl-admin-notice-cyber-monday .notice-event-name {
				padding: 15px 50px;
				font-size: 2.5vw;
			}

			.tinvwl-admin-notice-cyber-monday .notice-event-name p {
				font-size: 2.5vw;
			}

			.tinvwl-admin-notice-cyber-monday .notice-content {
				padding: 15px;
				flex-grow: 1;
			}

			.tinvwl-admin-notice-cyber-monday .notice-content p {
				font-size: 1.5vw;
			}

			.tinvwl-admin-notice-cyber-monday .notice-container {
				display: flex;
				flex-wrap: wrap;
			}

			.tinvwl-admin-notice-cyber-monday .notice-event-name img {
				width: 23.5vw;
			}

			@media (max-width: 1200px) {
				.tinvwl-admin-notice-cyber-monday .notice-event-name {
					padding: 15px 25px;
				}

				.tinvwl-admin-notice-cyber-monday .notice-logo {
					padding: 10px 0;
				}

				.tinvwl-admin-notice-cyber-monday .notice-logo a {
					max-width: 50%;
				}

				.tinvwl-admin-notice-cyber-monday .notice-logo a img {
					width: 100%;
				}
			}

			@media (max-width: 960px) {
				.tinvwl-admin-notice-cyber-monday .notice-content {
					width: 100%;
					order: 3;
				}

				.tinvwl-admin-notice-cyber-monday .notice-content p {
					font-size: 3.5vw;
				}

				.tinvwl-admin-notice-cyber-monday .notice-logo {
					order: 1;
					z-index: 2;
				}

				.tinvwl-admin-notice-cyber-monday .notice-event-name img {
					width: 40vw;
				}

				.tinvwl-admin-notice-cyber-monday .notice-event-name {
					order: 2;
					margin-left: -107px;
				}

				.tinvwl-admin-notice-cyber-monday .notice-event-name {
					flex-grow: 1;
				}
			}


			@media (max-width: 600px) {
				.tinvwl-admin-notice-cyber-monday .notice-event-name {
					width: 100%;
					order: 1;
					margin: 0;
					padding: 5px 15px;
				}

				.tinvwl-admin-notice-cyber-monday .notice-logo {
					order: 2;
					background-color: transparent;
					padding: 15px;
				}

				.tinvwl-admin-notice-cyber-monday .notice-logo a {
					max-width: 70%;

				}

				.tinvwl-admin-notice-cyber-monday .notice-content {
					width: min-content;
					order: 3;
				}

				.tinvwl-admin-notice-cyber-monday .notice-content p {
					font-size: 4vw;
				}

				.tinvwl-admin-notice-cyber-monday .notice-event-name img {
					width: 60vw;
					margin-top: 20px;
				}
			}
		</style>
		<div class="tinvwl-admin-notice tinvwl-admin-notice-cyber-monday notice is-dismissible"
			 data-notice_type="tinvwl-user-cyber-monday">
			<div class="notice-container"
				 style="padding:0; display: flex; justify-content: left; align-items: stretch;">
				<div class="notice-logo">
					<a href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?apply_coupon=CYBERMONDAY"
					   target="_blank" data-link="follow">
						<img
							src="<?php echo TINVWL_URL . '/assets/img/premium_logo.png'; ?>"
							alt="<?php echo esc_html( TINVWL_NAME ); ?>">
					</a>
				</div>
				<div class="notice-event-name">
					<a href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?apply_coupon=CYBERMONDAY"
					   target="_blank" data-link="follow">
						<img
							src="<?php echo TINVWL_URL . '/assets/img/cyber_monday.png'; ?>"
							alt="<?php echo esc_html( TINVWL_NAME ); ?>">
					</a>
				</div>
				<div class="notice-content">
					<p style="color:#ffffff;margin:0;">
						<?php printf( __( 'Get %s on %s!', 'ti-woocommerce-wishlist' ),
							'<strong>30% OFF</strong>',
							TINVWL_NAME . ' Premium'
						); ?>
						<br>
						<?php printf( '<span style="text-transform: uppercase;color: #6699cc;font-weight: 700;">%s</span> ', __( 'Use code:', 'ti-woocommerce-wishlist' ) ); ?>
						<a href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?apply_coupon=CYBERMONDAY"
						   target="_blank" data-link="follow"
						   style="color:#ffffff;padding:3px 15px;display: inline-block;background-color: #ff6633;text-decoration: none;text-transform: uppercase;font-weight: 700;">CYBERMONDAY</a>
					</p>
				</div>
			</div>
		</div>
		<?php

	}

	/**
	 * Display the Winter Holiday notice.
	 *
	 * @return void
	 */
	private function display_winter_holiday_notice(): void {
		?>
		<style>
			.tinvwl-admin-notice-winter-holiday {
				padding: 0 !important;
				border: none;
				background-color: #3e5a96;
			}

			.tinvwl-admin-notice-winter-holiday .notice-dismiss:before {
				color: #ffffff
			}

			.tinvwl-admin-notice-winter-holiday .notice-dismiss:hover:before {
				color: #000000;
			}

			.tinvwl-admin-notice-winter-holiday .notice-container > div {
				display: flex;
				align-items: center;
				justify-content: center;
				text-align: center;
			}

			.tinvwl-admin-notice-winter-holiday .notice-logo {
				padding: 15px;
				width: max-content;
				overflow: hidden;
			}

			.tinvwl-admin-notice-winter-holiday .notice-event-name {
				padding: 15px 50px;
			}

			.tinvwl-admin-notice-winter-holiday .notice-event-name p {
				font-size: 2.05vw;
			}

			.tinvwl-admin-notice-winter-holiday .notice-content {
				padding: 15px;
				flex-grow: 1;
			}

			.tinvwl-admin-notice-winter-holiday .notice-content p {
				font-size: 1.5vw;
			}

			.tinvwl-admin-notice-winter-holiday .notice-container {
				display: flex;
				flex-wrap: wrap;
			}

			@media (max-width: 1200px) {
				.tinvwl-admin-notice-winter-holiday .notice-event-name {
					padding: 15px 25px;
				}

				.tinvwl-admin-notice-winter-holiday .notice-event-name p {
					font-size: 2vw;
				}

				.tinvwl-admin-notice-winter-holiday .notice-logo {
					padding: 0;
				}

				.tinvwl-admin-notice-winter-holiday .notice-logo a {
					max-width: 50%;
				}

				.tinvwl-admin-notice-winter-holiday .notice-logo a img {
					width: 100%;
				}
			}

			@media (max-width: 960px) {
				.tinvwl-admin-notice-winter-holiday .notice-content {
					width: 100%;
					order: 3;
				}

				.tinvwl-admin-notice-winter-holiday .notice-content p {
					font-size: 2vw;
				}

				.tinvwl-admin-notice-winter-holiday .notice-logo {
					order: 1;
					z-index: 2;
				}

				.tinvwl-admin-notice-winter-holiday .notice-event-name p {
					font-size: 3.5vw;
				}

				.tinvwl-admin-notice-winter-holiday .notice-event-name {
					order: 2;
					margin-left: -107px;
				}

				.tinvwl-admin-notice-winter-holiday .notice-event-name {
					flex-grow: 1;
				}
			}


			@media (max-width: 600px) {
				.tinvwl-admin-notice-winter-holiday .notice-event-name {
					width: 100%;
					order: 1;
					margin: 0;
					padding: 5px 15px;
				}

				.tinvwl-admin-notice-winter-holiday .notice-event-name p {
					font-size: 6vw;
				}

				.tinvwl-admin-notice-winter-holiday .notice-logo {
					order: 2;
					background-color: transparent;
					padding: 15px;
				}

				.tinvwl-admin-notice-winter-holiday .notice-logo a {
					max-width: 70%;

				}

				.tinvwl-admin-notice-winter-holiday .notice-content {
					width: min-content;
					order: 3;
				}

				.tinvwl-admin-notice-winter-holiday .notice-content p {
					font-size: 4vw;
				}
			}
		</style>
		<div class="tinvwl-admin-notice tinvwl-admin-notice-winter-holiday notice is-dismissible"
			 data-notice_type="tinvwl-user-winter-holiday">
			<div class="notice-container"
				 style="padding:0; display: flex; justify-content: left; align-items: stretch;">
				<div class="notice-logo">
					<a href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?apply_coupon=WINTER30"
					   target="_blank" data-link="follow">
						<img
							src="<?php echo TINVWL_URL . '/assets/img/premium_logo.png'; ?>"
							alt="<?php echo esc_html( TINVWL_NAME ); ?>">
					</a>
				</div>
				<div class="notice-event-name">
					<p>
						<a href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?apply_coupon=WINTER30"
						   target="_blank"
						   data-link="follow"
						   style="text-decoration: none;text-transform: uppercase;color: #ffffff;"><strong><?php esc_html_e( 'Winter wonders sale', 'ti-woocommerce-wishlist' ); ?></strong></a>
					</p>
				</div>
				<div class="notice-content">
					<p style="color:#ffffff;margin:0;">
						<?php printf( __( 'Get %s on %s!', 'ti-woocommerce-wishlist' ),
							'<strong>30% OFF</strong>',
							TINVWL_NAME . ' Premium'
						); ?>
						<br>
						<?php printf( '<span style="color: #000000;font-weight: 700;">%s</span> ', __( 'Use code:', 'ti-woocommerce-wishlist' ) ); ?>
						<a href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?apply_coupon=WINTER30"
						   target="_blank" data-link="follow"
						   style="color:#ffffff;padding:3px 15px;display: inline-block;background-color: #ff6341;text-decoration: none;text-transform: uppercase;font-weight: 700;">WINTER30</a>
					</p>
				</div>
			</div>
		</div>
		<?php

	}
}
