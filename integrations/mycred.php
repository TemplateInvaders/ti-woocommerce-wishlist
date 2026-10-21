<?php
/**
 * TI WooCommerce Wishlist integration with:
 *
 * @name myCRED
 *
 * @version 4.0.0
 *
 * @slug mycred
 *
 * @url https://wordpress.org/plugins/mycred/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load integration depends on current settings.
global $tinvwl_integrations;

$slug = "mycred";

$name = "myCRED";

$available = defined( 'myCRED_VERSION' );

$tinvwl_integrations = is_array( $tinvwl_integrations ) ? $tinvwl_integrations : [];

$tinvwl_integrations[ $slug ] = array(
	'name'      => $name,
	'available' => $available,
);

if ( ! tinv_get_option( 'integrations', $slug ) ) {
	return;
}

if ( ! $available ) {
	return;
}

// myCred hooks
if ( defined( 'myCRED_VERSION' ) ) {

	/**
	 * Register MyCred hook for the WooCommerce Wishlist.
	 *
	 * @param array $installed The list of installed MyCred hooks.
	 *
	 * @return array The updated list of installed MyCred hooks.
	 */
	function tinvwl_mycred_register_ti_woocommerce_wishlist_hook( array $installed ): array {
		$installed['tinvwl'] = array(
			'title'       => __( 'WooCommerce Wishlist', 'ti-woocommerce-wishlist' ),
			'description' => __( 'Awards %_plural% for users adding products to their wishlist and purchased products from their wishlist.', 'ti-woocommerce-wishlist' ),
			'callback'    => array( 'myCRED_Hook_TinvWL' ),
		);

		return $installed;
	}

	add_filter( 'mycred_setup_hooks', 'tinvwl_mycred_register_ti_woocommerce_wishlist_hook', 100 );


	/**
	 * Load MyCred hook for the WooCommerce Wishlist.
	 */
	function tinvwl_mycred_load_ti_woocommerce_wishlist_hook() {
		// If the hook has been replaced or if the plugin is not installed, exit now.
		if ( class_exists( 'myCRED_Hook_TinvWL' ) ) {
			return;
		}

		class myCRED_Hook_TinvWL extends myCRED_Hook {

			/**
			 * Constructor.
			 *
			 * @param array $hook_prefs The hook preferences.
			 * @param string $type The point type to use.
			 */
			public function __construct( $hook_prefs, $type = MYCRED_DEFAULT_TYPE_KEY ) {
				parent::__construct( array(
					'id'       => 'tinvwl',
					'defaults' => array(
						'tinvwl_added'     => array(
							'creds' => 1,
							'log'   => '%plural% for adding a product to a wishlist',
							'limit' => '0/x',
						),
						'tinvwl_purchased' => array(
							'creds' => 1,
							'log'   => '%plural% for purchasing a product from a wishlist',
							'limit' => '0/x',
						),
					),
				), $hook_prefs, $type );
			}

			/**
			 * Run the hook.
			 */
			public function run() {
				add_action( 'tinvwl_product_added', array( $this, 'added' ) );
				add_action( 'tinvwl_product_purchased', array( $this, 'purchased' ), 10, 3 );
				add_action( 'woocommerce_payment_complete', array( $this, 'paid_order' ) );
				add_action( 'woocommerce_order_status_changed', array( $this, 'paid_order' ), 20 );
			}

			/**
			 * Award points to the user who added a product to their wishlist.
			 *
			 * @param array $data Product data, including author and wishlist IDs.
			 */
			public function added( $data ) {

				// Must be logged in
				if ( ! is_user_logged_in() ) {
					return;
				}

				$user_id = get_current_user_id();

				global $wpdb;
				// Share the purchase lock: both native award paths read and then write this balance.
				$lock = 'tinvwl_mycred_' . md5( $wpdb->dbname . '|' . $wpdb->prefix . '|' . $user_id . '|' . $this->mycred_type );
				if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
					return;
				}
				try {
					if ( ! $this->refresh_purchase_balances( $user_id ) || $wpdb->last_error || $this->prefs['tinvwl_added']['creds'] === 0 || $this->core->exclude_user( $user_id ) ) {
						return;
					}
					$limited = $this->over_hook_limit( 'tinvwl_added', 'added_to_wishlist', $user_id );
					if ( $wpdb->last_error || $limited ) {
						return;
					}
					$exists = $this->core->has_entry( 'added_to_wishlist', $data['product_id'], $user_id );
					if ( $wpdb->last_error || $exists ) {
						return;
					}
					$this->core->add_creds(
						'added_to_wishlist',
						$user_id,
						$this->prefs['tinvwl_added']['creds'],
						$this->prefs['tinvwl_added']['log'],
						$data['product_id'],
						array( 'ref_type' => 'post' ),
						$this->mycred_type
					);
				} finally {
					$error = $wpdb->last_error;
					$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
					$wpdb->last_error = $error;
				}
			}

			/** Award purchase points after payment using the source already saved on order items. */
			public function paid_order( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( ! $order instanceof WC_Order || ! $order->is_paid() ) {
					return;
				}

				foreach ( $order->get_items() as $item ) {
					if ( $item instanceof WC_Order_Item_Product && $item->get_meta( '_tinvwl_wishlist_cart', true ) ) {
						$this->purchased( $order, $item, null );
					}
				}
			}

			/**
			 * Award points to user who purchased a product from a wishlist.
			 *
			 * @param WC_Order $order Order object.
			 * @param WC_Order_Item_Product $item Order item product object.
			 * @param array $wishlist A wishlist data where product added from.
			 */
			public function purchased( $order, $item, $wishlist ) {
				if ( ! $order instanceof WC_Order || ! $item instanceof WC_Order_Item_Product || ! $order->is_paid() ) {
					return;
				}

				// Payment callbacks may run without the buyer's browser session.
				$user_id = (int) $order->get_customer_id();
				if ( ! $user_id || ! get_user_by( 'id', $user_id ) ) {
					return;
				}

				global $wpdb;
				// The vendor updates balances by reading and writing, so serialize this buyer's awards.
				$lock = 'tinvwl_mycred_' . md5( $wpdb->dbname . '|' . $wpdb->prefix . '|' . $user_id . '|' . $this->mycred_type );
				if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
					return;
				}
				try {
					if ( ! $this->refresh_purchase_balances( $user_id ) || $wpdb->last_error || $this->prefs['tinvwl_purchased']['creds'] === 0 || $this->core->exclude_user( $user_id ) ) {
						return;
					}
					$limited = $this->over_hook_limit( 'tinvwl_purchased', 'purchased_from_wishlist', $user_id );
					if ( $wpdb->last_error || $limited ) {
						return;
					}
					$exists = $this->has_purchase_receipt( $item->get_id(), $user_id );
					if ( $wpdb->last_error || $exists ) {
						return;
					}
					$this->core->add_creds(
						'purchased_from_wishlist',
						$user_id,
						$this->prefs['tinvwl_purchased']['creds'],
						$this->prefs['tinvwl_purchased']['log'],
						$item->get_id(),
						array( 'ref_type' => 'post' ),
						$this->mycred_type
					);
				} finally {
					$error = $wpdb->last_error;
					$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
					$wpdb->last_error = $error;
				}
			}

			/** Refresh only this recipient's native caches after waiting for another award. */
			private function refresh_purchase_balances( $user_id ) {
				global $wpdb;
				wp_cache_delete( $user_id, 'user_meta' );
				$balances = array();
				foreach ( array( 'mycred_current_account', 'mycred_account' ) as $name ) {
					$account = $GLOBALS[ $name ] ?? null;
					if ( $account instanceof myCRED_Account && $account->user_id === $user_id && isset( $account->balance[ $this->mycred_type ] ) && $account->balance[ $this->mycred_type ] instanceof myCRED_Balance ) {
						$balances[] = array( $account, $account->balance[ $this->mycred_type ] );
						// Both native balance getters recognize false as an uncached balance.
						$account->balance[ $this->mycred_type ] = false;
					}
				}
				if ( ! $balances ) {
					return true;
				}
				try {
					$current = $this->core->get_users_balance( $user_id, $this->mycred_type );
					if ( $wpdb->last_error ) {
						return false;
					}
					$total = $this->core->get_users_total_balance( $user_id, $this->mycred_type );
					if ( $wpdb->last_error ) {
						return false;
					}
				} finally {
					foreach ( $balances as list( $account, $balance ) ) {
						$account->balance[ $this->mycred_type ] = $balance;
					}
				}
				foreach ( $balances as list( $account, $balance ) ) {
					$balance->set( 'current', $current );
					$balance->set( 'accumulated', $total );
				}
				return true;
			}

			/** Keep the vendor receipt/filter contract, using a current read inside a caller transaction. */
			private function has_purchase_receipt( $item_id, $user_id ) {
				global $wpdb;
				$query = $wpdb->prepare(
					"SELECT id FROM {$this->core->log_table} WHERE ref = %s AND ref_id = %d AND user_id = %d AND ctype = %s;",
					'purchased_from_wishlist', $item_id, $user_id, $this->mycred_type
				);
				$current_read = static function ( $sql ) use ( $query ) {
					return $sql === $query ? substr( $sql, 0, -1 ) . ' FOR UPDATE;' : $sql;
				};
				add_filter( 'query', $current_read, PHP_INT_MAX );
				try {
					return $this->core->has_entry( 'purchased_from_wishlist', $item_id, $user_id, null, $this->mycred_type );
				} finally {
					remove_filter( 'query', $current_read, PHP_INT_MAX );
				}
			}

			/**
			 * Preferences
			 */
			public function preferences() {

				$prefs = $this->prefs;

				?>
				<div class="hook-instance">
					<h3><?php _e( 'Adding Product to Wishlist', 'ti-woocommerce-wishlist' ); ?></h3>
					<div class="row">
						<div class="col-lg-2 col-md-6 col-sm-6 col-xs-12">
							<div class="form-group">
								<label
									for="<?php echo $this->field_id( array( 'tinvwl_added' => 'creds' ) ); ?>"><?php _e( 'Points', 'ti-woocommerce-wishlist' ); ?></label>
								<input type="text"
									   name="<?php echo $this->field_name( array( 'tinvwl_added' => 'creds' ) ); ?>"
									   id="<?php echo $this->field_id( array( 'tinvwl_added' => 'creds' ) ); ?>"
									   value="<?php echo $this->core->number( $prefs['tinvwl_added']['creds'] ); ?>"
									   class="form-control"/>
							</div>
						</div>
						<div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
							<div class="form-group">
								<label for="<?php echo $this->field_id( array(
									'tinvwl_added',
									'limit',
								) ); ?>"><?php _e( 'Limit', 'ti-woocommerce-wishlist' ); ?></label>
								<?php echo $this->hook_limit_setting( $this->field_name( array(
									'tinvwl_added',
									'limit',
								) ), $this->field_id( array(
									'tinvwl_added',
									'limit',
								) ), $prefs['tinvwl_added']['limit'] ); ?>
							</div>
						</div>
						<div class="col-lg-6 col-md-12 col-sm-12 col-xs-12">
							<div class="form-group">
								<label
									for="<?php echo $this->field_id( array( 'tinvwl_added' => 'log' ) ); ?>"><?php _e( 'Log Template', 'ti-woocommerce-wishlist' ); ?></label>
								<input type="text"
									   name="<?php echo $this->field_name( array( 'tinvwl_added' => 'log' ) ); ?>"
									   id="<?php echo $this->field_id( array( 'tinvwl_added' => 'log' ) ); ?>"
									   placeholder="<?php _e( 'required', 'ti-woocommerce-wishlist' ); ?>"
									   value="<?php echo esc_attr( $prefs['tinvwl_added']['log'] ); ?>"
									   class="form-control"/>
								<span class="description"><?php echo $this->available_template_tags( array(
										'general',
										'post',
									) ); ?></span>
							</div>
						</div>
					</div>
					<h3><?php _e( 'Purchasing Product from Wishlist', 'ti-woocommerce-wishlist' ); ?></h3>
					<div class="row">
						<div class="col-lg-2 col-md-6 col-sm-6 col-xs-12">
							<div class="form-group">
								<label
									for="<?php echo $this->field_id( array( 'tinvwl_purchased' => 'creds' ) ); ?>"><?php _e( 'Points', 'ti-woocommerce-wishlist' ); ?></label>
								<input type="text"
									   name="<?php echo $this->field_name( array( 'tinvwl_purchased' => 'creds' ) ); ?>"
									   id="<?php echo $this->field_id( array( 'tinvwl_purchased' => 'creds' ) ); ?>"
									   value="<?php echo $this->core->number( $prefs['tinvwl_purchased']['creds'] ); ?>"
									   class="form-control"/>
							</div>
						</div>
						<div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
							<div class="form-group">
								<label for="<?php echo $this->field_id( array(
									'tinvwl_purchased',
									'limit',
								) ); ?>"><?php _e( 'Limit', 'ti-woocommerce-wishlist' ); ?></label>
								<?php echo $this->hook_limit_setting( $this->field_name( array(
									'tinvwl_purchased',
									'limit',
								) ), $this->field_id( array(
									'tinvwl_purchased',
									'limit',
								) ), $prefs['tinvwl_purchased']['limit'] ); ?>
							</div>
						</div>
						<div class="col-lg-6 col-md-12 col-sm-12 col-xs-12">
							<div class="form-group">
								<label
									for="<?php echo $this->field_id( array( 'tinvwl_purchased' => 'log' ) ); ?>"><?php _e( 'Log Template', 'ti-woocommerce-wishlist' ); ?></label>
								<input type="text"
									   name="<?php echo $this->field_name( array( 'tinvwl_purchased' => 'log' ) ); ?>"
									   id="<?php echo $this->field_id( array( 'tinvwl_purchased' => 'log' ) ); ?>"
									   placeholder="<?php _e( 'required', 'ti-woocommerce-wishlist' ); ?>"
									   value="<?php echo esc_attr( $prefs['tinvwl_purchased']['log'] ); ?>"
									   class="form-control"/>
								<span class="description"><?php echo $this->available_template_tags( array(
										'general',
										'post',
									) ); ?></span>
							</div>
						</div>
					</div>
				</div>

				<?php

			}

			/**
			 * Sanitize preferences for this hook.
			 *
			 * @param array $data The preferences to sanitize.
			 *
			 * @return array The sanitized preferences.
			 */
			public function sanitise_preferences( $data ): array {
				if ( isset( $data['tinvwl_added']['limit'] ) && isset( $data['tinvwl_added']['limit_by'] ) ) {
					$limit = sanitize_text_field( $data['tinvwl_added']['limit'] );
					if ( $limit == '' ) {
						$limit = 0;
					}
					$data['tinvwl_added']['limit'] = $limit . '/' . $data['tinvwl_added']['limit_by'];
					unset( $data['tinvwl_added']['limit_by'] );
				}

				if ( isset( $data['tinvwl_purchased']['limit'] ) && isset( $data['tinvwl_purchased']['limit_by'] ) ) {
					$limit = sanitize_text_field( $data['tinvwl_purchased']['limit'] );
					if ( $limit == '' ) {
						$limit = 0;
					}
					$data['tinvwl_purchased']['limit'] = $limit . '/' . $data['tinvwl_purchased']['limit_by'];
					unset( $data['tinvwl_purchased']['limit_by'] );
				}

				return $data;
			}

		}

	}

	add_action( 'mycred_load_hooks', 'tinvwl_mycred_load_ti_woocommerce_wishlist_hook', 100 );

	/**
	 * Add custom references to MyCred all references.
	 *
	 * @param array $references The MyCred all references.
	 *
	 * @return array The updated MyCred all references.
	 */
	function tinvwl_mycred_references( array $references ): array {
		$references['purchased_from_wishlist'] = __( 'Purchased From Wishlist', 'ti-woocommerce-wishlist' );
		$references['added_to_wishlist']       = __( 'Added To Wishlist', 'ti-woocommerce-wishlist' );

		return $references;
	}

	add_filter( 'mycred_all_references', 'tinvwl_mycred_references' );

}
