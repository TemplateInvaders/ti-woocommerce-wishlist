<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class TINVWL_AutomateWoo_Wishlist extends AutomateWoo\Wishlist {

	public $id;
	public $owner_id;
	public $items;
	public $date;


	/**
	 * @return int
	 */
	function get_id() {
		return absint( $this->id );
	}

	/**
	 * @return int
	 */
	function get_user_id() {
		return absint( $this->owner_id );
	}

	/**
	 * @return Customer|bool
	 */
	function get_customer() {
		return AutomateWoo\Customer_Factory::get_by_user_id( $this->get_user_id() );
	}

	/**
	 * @return string
	 */
	function get_integration() {
		return 'tinv';
	}

	/** Resolve the saved variation without silently substituting its parent or a different family. */
	public static function get_event_product( $data ) {
		$product_id = absint( $data['product_id'] ?? 0 );
		$variation_id = absint( $data['variation_id'] ?? 0 );
		if ( ! $product_id ) { return false; }
		$product = wc_get_product( $variation_id ? $variation_id : $product_id );
		if ( ! $product instanceof WC_Product ) { return false; }
		if ( $variation_id && ( ! $product instanceof WC_Product_Variation || (int) $product->get_parent_id() !== $product_id ) ) {
			return false;
		}
		return $product;
	}

	/** Resolve a trusted workflow's wishlist independently of the browser session. */
	private function get_owned_wishlist() {
		$id = $this->get_id();
		$owner_id = $this->get_user_id();
		$owner = $owner_id ? get_userdata( $owner_id ) : false;
		if ( ! $id || ! $owner || ! $owner->exists() ) {
			return false;
		}
		$wishlists = ( new TInvWL_Wishlist() )->get( array( 'ID' => $id, 'author' => $owner_id, 'count' => 1 ) );
		$wishlist = array_shift( $wishlists );
		return is_array( $wishlist ) && (int) $wishlist['ID'] === $id && (int) $wishlist['author'] === $owner_id ? $wishlist : false;
	}

	/**
	 * @return array
	 */
	function get_items() {

		if ( isset( $this->items ) ) {
			return $this->items;
		}

		$this->items = [];

		$wishlist = $this->get_owned_wishlist();
		$products = $wishlist ? ( new TInvWL_Product( $wishlist ) )->get_wishlist( array( 'count' => 9999999 ) ) : array();
		if ( $products ) {
			foreach ( $products as $product ) {
				$this->items[] = $product['product_id'];
			}
		}

		$this->items = array_unique( $this->items );

		return $this->items;
	}

	/**
	 * @return string
	 */
	function get_link() {
		$wishlist = $this->get_owned_wishlist();
		return $wishlist ? tinv_url_wishlist_by_key( $wishlist['share_key'] ) : '';
	}


	/**
	 * @return string
	 */
	protected function get_date_created_option_name() {
		return '_automatewoo_wishlist_date_created_' . $this->get_id();
	}


	/**
	 * @return DateTime|false UTC
	 */
	function get_date_created() {
		$val = get_option( $this->get_date_created_option_name() );
		if ( ! $val ) {
			return false;
		}

		return new DateTime( $val );
	}


	/**
	 * @param DateTime $date UTC
	 */
	function set_date_created( $date ) {
		if ( ! is_a( $date, 'DateTime' ) ) {
			return;
		}
		update_option( $this->get_date_created_option_name(), $date->to_mysql_string(), false );
	}

}
