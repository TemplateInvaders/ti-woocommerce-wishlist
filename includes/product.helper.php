<?php
/**
 * Product function class
 *
 * @since             1.5.0
 * @package           TInvWishlist\Products
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Product function class
 */
class TInvWL_Product {

	/**
	 * Table name
	 *
	 * @var string
	 */
	private $table;
	/**
	 * Plugin name
	 *
	 * @var string
	 */
	private $_name;
	/**
	 * Wishlist object
	 *
	 * @var array
	 */
	public $wishlist;
	/**
	 * User id
	 *
	 * @var integer
	 */
	public $user;

	/** Public controllers must opt in; storage, admin and background integrations stay trusted. */
	protected $public_context = false;
	/** Locks held only for this model's current public write callbacks. */
	private $owned_mutations = array();
	/** REST writers retain their existing elevated capability policy only in this context. */
	private $api_context = false;
	/** Internal transfers read stored values, independently of frontend quantity and display settings. */
	private $storage_context = false;
	/** Only the transfer receiver buffers post-write actions; normal writes dispatch immediately. */
	private $deferred_product_actions = null;

	/**
	 * Constructor
	 *
	 * @param array $wishlist Object wishlist.
	 * @param string $plugin_name Plugin name.
	 *
	 * @global wpdb $wpdb
	 *
	 */
	function __construct( $wishlist = array(), $plugin_name = TINVWL_PREFIX, $public_context = false ) {
		global $wpdb;

		$this->wishlist = (array) $wishlist;
		$this->_name    = $plugin_name;
		$this->public_context = (bool) $public_context;
		$this->table    = sprintf( '%s%s_%s', $wpdb->prefix, $this->_name, 'items' );
		$this->user     = $this->wishlist_author();
		if ( empty( $this->user ) ) {
			$user = wp_get_current_user();
			if ( $user->exists() ) {
				$this->user = $user->ID;
			}
		}

		add_filter( 'tinvwl_addtowishlist_add_form', array( $this, 'clean_meta' ), 10, 1 );
	}

	/**
	 * Create a trusted storage model for ownership transfers, never from a public request flag.
	 * Write validation, metadata preparation and write filters still run normally.
	 */
	public static function for_storage( $wishlist = array(), $plugin_name = TINVWL_PREFIX ) {
		$product = new self( $wishlist, $plugin_name );
		$product->storage_context = true;
		return $product;
	}


	/** Create a REST write model while retaining public product visibility and validation. */
	public static function for_api( $wishlist = array(), $plugin_name = TINVWL_PREFIX ) {
		$product = new self( $wishlist, $plugin_name, true );
		$product->api_context = true;
		return $product;
	}

	/** Public writes keep the saved parent stable while rechecking the current caller. */
	private function with_current_owner( $wishlist_id, callable $write ) {
		global $wpdb;
		$wishlist_id = absint( $wishlist_id );
		if ( ! $wishlist_id ) {
			return false;
		}
		$parents = $wpdb->prefix . $this->_name . '_lists';
		$sql = $wpdb->prepare( "SELECT * FROM `$parents` WHERE ID = %d", $wishlist_id );
		$parent = $wpdb->get_row( $sql, ARRAY_A );
		if ( $wpdb->last_error || ! $parent ) {
			return false;
		}
		$owner = (int) $parent['author'];
		$locks = $owner ? array( 'tinvwl_merge_' . md5( $wpdb->prefix . '|' . $owner ) ) : array();
		$locks[] = 'tinvwl_item_' . md5( $this->table . '|' . $wishlist_id );
		$acquired = array();
		$previous_wishlist = $this->wishlist;
		$previous_user = $this->user;
		try {
			foreach ( $locks as $lock ) {
				if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
					return false;
				}
				$acquired[] = $lock;
			}
			// A current read also works when the caller already has a repeatable-read snapshot.
			$current = $wpdb->get_row( $sql . ' FOR UPDATE', ARRAY_A );
			$api_manager = $this->api_context && ( current_user_can( 'tinvwl_general_settings' ) || current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) );
			if ( $wpdb->last_error || ! $current || (int) $current['author'] !== $owner || ( ! $api_manager && ! ( new TInvWL_Wishlist( $this->_name ) )->is_owner( $current ) ) ) {
				return false;
			}
			$this->wishlist['ID'] = $wishlist_id;
			$this->wishlist['author'] = (int) $current['author'];
			$this->user = (int) $current['author'];
			$this->owned_mutations[ $wishlist_id ] = true;
			return $write();
		} finally {
			$error = $wpdb->last_error;
			unset( $this->owned_mutations[ $wishlist_id ] );
			$this->wishlist = $previous_wishlist;
			$this->user = $previous_user;
			foreach ( array_reverse( $acquired ) as $lock ) {
				$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
			}
			$wpdb->last_error = $error;
		}
	}

	private function storage_quantity( $quantity ) {
		$quantity = filter_var( $quantity, FILTER_VALIDATE_FLOAT );
		return false !== $quantity && is_finite( $quantity ) && $quantity >= 0 ? $quantity : false;
	}

	/** Begin an instance-local action buffer without changing validation or write filters. */
	public function defer_product_actions() {
		$this->deferred_product_actions = array();
	}

	/** Clear the buffer; the transaction owner dispatches it only after a successful COMMIT. */
	public function release_product_actions() {
		$actions = $this->deferred_product_actions;
		$this->deferred_product_actions = null;
		return (array) $actions;
	}

	private function product_action( $hook, $data ) {
		if ( null !== $this->deferred_product_actions ) {
			$this->deferred_product_actions[] = array( $hook, $data );
		} else {
			do_action( $hook, $data );
		}
	}

	/**
	 * Catalog visibility and stock do not grant or revoke access to a product.
	 * Private products follow WordPress read permission; previews require edit permission.
	 */
	public static function can_read_product( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return false;
		}
		$post = get_post( $product->get_id() );
		if ( ! $post || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			return false;
		}
		if ( 'publish' !== $post->post_status && ! current_user_can( 'private' === $post->post_status ? 'read_post' : 'edit_post', $post->ID ) ) {
			return false;
		}
		if ( $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product->get_parent_id() );
			return $parent && ! $parent->is_type( 'variation' ) && self::can_read_product( $parent );
		}
		return true;
	}

	/** Check both the requested product and a variation's actual parent, including stored rows. */
	public static function can_read_product_ids( $product_id, $variation_id = 0 ) {
		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );
		$product      = wc_get_product( $product_id );
		if ( ! self::can_read_product( $product ) ) {
			return false;
		}
		if ( ! $variation_id ) {
			return true;
		}
		$variation = wc_get_product( $variation_id );
		if ( ! self::can_read_product( $variation ) ) {
			return false;
		}
		if ( $variation->is_type( 'variation' ) ) {
			return $product_id === (int) $variation->get_parent_id();
		}
		// Legacy grouped POSTs store product_id=group and variation_id=simple child.
		return $product->is_type( 'grouped' ) && in_array( $variation_id, array_map( 'absint', $product->get_children() ), true );
	}

	/** Apply access before LIMIT/COUNT, including custom aggregate queries using {where}. */
	private function public_product_where( $where ) {
		global $wpdb;
		// Keep normal published lists/counts in SQL; only previews and legacy grouped pairs need capabilities/objects.
		$published = "EXISTS ( SELECT 1 FROM `{$wpdb->posts}` AS tinvwl_access_product
			WHERE tinvwl_access_product.ID = product_id AND tinvwl_access_product.post_status = 'publish'
			AND ( ( tinvwl_access_product.post_type = 'product' AND ( variation_id = 0 OR EXISTS (
				SELECT 1 FROM `{$wpdb->posts}` AS tinvwl_access_variation
				WHERE tinvwl_access_variation.ID = variation_id AND tinvwl_access_variation.post_type = 'product_variation'
				AND tinvwl_access_variation.post_parent = tinvwl_access_product.ID AND tinvwl_access_variation.post_status = 'publish'
			) ) ) OR ( tinvwl_access_product.post_type = 'product_variation' AND variation_id = 0 AND EXISTS (
				SELECT 1 FROM `{$wpdb->posts}` AS tinvwl_access_parent
				WHERE tinvwl_access_parent.ID = tinvwl_access_product.post_parent AND tinvwl_access_parent.post_type = 'product'
				AND tinvwl_access_parent.post_status = 'publish'
			) ) ) )";
		$pairs   = $wpdb->get_results( "SELECT DISTINCT `product_id`, `variation_id` FROM `{$this->table}` WHERE ($where) AND NOT ($published)", ARRAY_A );
		$allowed = array();
		foreach ( (array) $pairs as $pair ) {
			if ( self::can_read_product_ids( $pair['product_id'], $pair['variation_id'] ) ) {
				$allowed[] = $wpdb->prepare( '( `product_id` = %d AND `variation_id` = %d )', $pair['product_id'], $pair['variation_id'] );
			}
		}
		return '(' . $where . ') AND (' . $published . ( $allowed ? ' OR ' . implode( ' OR ', $allowed ) : '' ) . ')';
	}

	public static function filter_readable_products( $products ) {
		return array_filter( $products, static function ( $product ) {
			return ! isset( $product['product_id'] ) || ( self::can_read_product_ids( $product['product_id'], $product['variation_id'] ?? 0 ) && ( empty( $product['data'] ) || self::can_read_product( $product['data'] ) ) );
		} );
	}

	/**
	 * Get wishlist id
	 *
	 * @return int
	 */
	function wishlist_id() {
		if ( is_array( $this->wishlist ) && array_key_exists( 'ID', $this->wishlist ) ) {
			return $this->wishlist['ID'];
		}

		return 0;
	}

	/**
	 * Get author wishlist
	 *
	 * @return int
	 */
	function wishlist_author() {
		if ( is_array( $this->wishlist ) && array_key_exists( 'author', $this->wishlist ) ) {
			return $this->wishlist['author'];
		}

		return 0;
	}

	/**
	 * Add\Update product
	 *
	 * @param array $data Object product.
	 * @param array $meta Object meta form data.
	 *
	 * @return boolean
	 */
	function add_product( $data = array(), $meta = array() ) {
		$target = absint( $data['wishlist_id'] ?? 0 ) ?: absint( $this->wishlist_id() );
		if ( $this->public_context && ! isset( $this->owned_mutations[ $target ] ) ) {
			return $this->with_current_owner( $target, function () use ( $data, $meta ) { return $this->add_product( $data, $meta ); } );
		}
		$_data = filter_var_array( $data, array(
			'product_id'   => FILTER_VALIDATE_INT,
			'variation_id' => FILTER_VALIDATE_INT,
			'wishlist_id'  => FILTER_VALIDATE_INT,
			'quantity'     => FILTER_VALIDATE_INT,
		) );
		if ( empty( $_data['quantity'] ) ) {
			$_data['quantity'] = 1;
		}
		if ( $this->storage_context ) {
			$quantity = $this->storage_quantity( $data['quantity'] ?? 1 );
			if ( false === $quantity ) {
				return false;
			}
			$data['quantity'] = $_data['quantity'] = $quantity;
		}
		if ( empty( $_data['wishlist_id'] ) ) {
			$_data['wishlist_id'] = $this->wishlist_id();
		}
		if ( $_data['wishlist_id'] <= 0 ) {
			return false;
		}
		global $wpdb;
		// Lock the actual saved target, including callers without a constructor wishlist.
		$owner_sql = $wpdb->prepare( "SELECT author FROM `{$wpdb->prefix}{$this->_name}_lists` WHERE ID = %d", $_data['wishlist_id'] );
		$owner = $wpdb->get_var( $owner_sql );
		if ( $wpdb->last_error || null === $owner ) {
			return false;
		}
		$owner = (int) $owner;
		// Keep the same lock order as account transfers; guests lock only this saved list.
		$locks = $owner > 0 ? array( 'tinvwl_merge_' . md5( $wpdb->prefix . '|' . $owner ) ) : array();
		$locks[] = 'tinvwl_item_' . md5( $this->table . '|' . $_data['wishlist_id'] );
		$acquired = array();
		try {
			foreach ( $locks as $lock ) {
				if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
					return false;
				}
				$acquired[] = $lock;
			}
			// Adoption may have changed the owner while this writer waited for its locks.
			$current_owner = $wpdb->get_var( $owner_sql );
			if ( $wpdb->last_error || null === $current_owner || (int) $current_owner !== $owner ) {
				return false;
			}
			$product_data = $this->check_product( $_data['product_id'], $_data['variation_id'], $_data['wishlist_id'], $meta );
			if ( false === $product_data ) {
				return false;
			}
			if ( $product_data ) {
				if ( $this->storage_context ) {
					$quantity = $this->storage_quantity( $product_data['quantity'] );
					if ( false === $quantity ) {
						return false;
					}
					$data['quantity'] = $quantity + $_data['quantity'];
					unset( $data['order'] ); // The target configuration keeps its saved position.
				}
				return $this->update( $data, $meta, $product_data['ID'] );
			} else {
				return $this->add( $data, $meta );
			}
		} finally {
			$error = $wpdb->last_error;
			foreach ( array_reverse( $acquired ) as $lock ) {
				$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
			}
			$wpdb->last_error = $error;
		}
	}

	/**
	 * Add product
	 *
	 * @param array $data Object product.
	 * @param array $meta Object meta form data.
	 *
	 * @return boolean
	 * @global wpdb $wpdb
	 *
	 */
	function add( $data = array(), $meta = array() ) {
		$target = absint( $data['wishlist_id'] ?? 0 ) ?: absint( $this->wishlist_id() );
		if ( $this->public_context && ! isset( $this->owned_mutations[ $target ] ) ) {
			return $this->with_current_owner( $target, function () use ( $data, $meta ) { return $this->add( $data, $meta ); } );
		}

		$default = array(
			'wishlist_id'  => $this->wishlist_id(),
			'product_id'   => 0,
			'variation_id' => 0,
			'formdata'     => '',
			'author'       => $this->user,
			'date'         => current_time( 'Y-m-d H:i:s' ),
			'quantity'     => 1,
			'price'        => 0,
			'in_stock'     => 1,
		);
		$data    = apply_filters( 'tinvwl_wishlist_product_add_field', filter_var_array( $data, array(
			'author'       => FILTER_VALIDATE_INT,
			'product_id'   => FILTER_VALIDATE_INT,
			'quantity'     => $this->storage_context ? FILTER_VALIDATE_FLOAT : FILTER_VALIDATE_INT,
			'variation_id' => FILTER_VALIDATE_INT,
			'wishlist_id'  => FILTER_VALIDATE_INT,
		) ) );
		$quantity = $this->storage_context && isset( $data['quantity'] ) ? $this->storage_quantity( $data['quantity'] ) : null;
		if ( false === $quantity ) {
			return false;
		}
		$data    = array_filter( $data );
		if ( null !== $quantity ) {
			$data['quantity'] = $quantity;
		}

		$data = tinv_array_merge( $default, $data );

		if ( empty( $data['wishlist_id'] ) || empty( $data['product_id'] ) ) {
			return false;
		}

		$product_data = $this->product_data( $data['product_id'], $data['variation_id'] );

		if ( ( $this->storage_context ? $data['quantity'] < 0 : $data['quantity'] <= 0 ) || ! $product_data ) {
			return false;
		}

		if ( $product_data->is_sold_individually() ) {
			$data['quantity'] = 1;
		}

		$data                 = apply_filters( 'tinvwl_wishlist_product_add', $data );
		$data['product_id']   = $product_data->is_type( 'variation' ) ? $product_data->get_parent_id() : $product_data->get_id();
		$data['variation_id'] = $product_data->is_type( 'variation' ) ? $product_data->get_id() : 0;
		$data['in_stock']     = $product_data->is_in_stock();

		$data['formdata'] = $this->prepare_save_meta( $meta, $data['product_id'], $data['variation_id'] );

		if ( $product_data->is_type( 'variable' ) ) {
			$data['price'] = filter_var( $product_data->get_variation_price( 'max', false ), FILTER_VALIDATE_FLOAT );
		} else {
			$data['price'] = filter_var( ( $product_data->get_price() ), FILTER_VALIDATE_FLOAT );
		}

		global $wpdb;
		if ( $wpdb->insert( $this->table, $data ) ) { // @codingStandardsIgnoreLine WordPress.VIP.DirectDatabaseQuery.DirectQuery
			$id = $wpdb->insert_id;

			/* Run a 3rd party code when product added to a wishlist.
			 *
			 * @param array $data product data including author and wishlist IDs.
			 * */
			$this->product_action( 'tinvwl_product_added', $data );
			$wla = new TInvWL_Analytics( $this->wishlist );
			$wla->cart_product( $data['product_id'], $data['variation_id'] );

			return $id;
		}

		return false;
	}

	/**
	 * Get products by wishlist
	 *
	 * @param array $data Request.
	 *
	 * @return array
	 */
	function get_wishlist( $data = array(), $count = false ) {
		if ( ! array_key_exists( 'wishlist_id', $data ) ) {
			$data['wishlist_id'] = $this->wishlist_id();
		}
		if ( empty( $data['wishlist_id'] ) ) {
			return array();
		}
		// Omitted meta lists all configurations; explicit empty meta means a plain item.
		if ( array_key_exists( 'meta', $data ) ) {
			$product_id = isset( $data['product_id'] ) && is_scalar( $data['product_id'] ) ? absint( $data['product_id'] ) : 0;
			$variation_id = isset( $data['variation_id'] ) && is_scalar( $data['variation_id'] ) ? absint( $data['variation_id'] ) : 0;
			$formdata = $this->configuration_formdata( $data['meta'], $product_id, $variation_id );
			unset( $data['meta'] );
			if ( empty( $formdata ) ) {
				return array();
			}
			$data['formdata'] = $formdata;
		}

		return $this->get( $data, $count );
	}

	/** Match legacy encodings only when they represent the authoritative write configuration. */
	private function configuration_formdata( $meta, $product_id, $variation_id ) {
		$canonical = $this->prepare_save_meta( $meta, $product_id, $variation_id );
		if ( false === $canonical ) {
			return array();
		}
		$formdata = array( $canonical );
		if ( is_array( $meta ) && array_key_exists( 'tinvwl-hidden-fields', $meta ) ) {
			unset( $meta['tinvwl-hidden-fields'] );
			$alternate = $this->prepare_save_meta( $meta, $product_id, $variation_id );
			$configuration = json_decode( $canonical, true );
			if ( is_array( $configuration ) ) {
				unset( $configuration['tinvwl-hidden-fields'] );
				if ( is_string( $alternate ) && '' !== $alternate && $configuration === json_decode( $alternate, true ) ) {
					$formdata[] = $alternate;
				}
			}
		}
		// A prepare hook can derive real addons from the marker; stripping it must never grant a plain fallback.
		if ( '' === $canonical || '{"tinvwl-hidden-fields":"[]"}' === $canonical ) {
			$formdata[] = '';
			$formdata[] = '{"tinvwl-hidden-fields":"[]"}';
			$formdata[] = null;
		}
		return $formdata;
	}

	/** JSON configuration values and keys are case-sensitive regardless of the table collation. */
	private function configuration_where( $formdata ) {
		global $wpdb;
		$values = is_array( $formdata ) ? $formdata : array( $formdata );
		$clauses = array();
		foreach ( $values as $value ) {
			$clauses[] = null === $value ? '`formdata` IS NULL' : $wpdb->prepare( 'BINARY `formdata` = %s', $value );
		}
		return '(' . ( $clauses ? implode( ' OR ', $clauses ) : '0' ) . ')';
	}

	/** wpdb::update() uses the column collation, so configuration updates need an explicit predicate. */
	private function update_configuration_rows( $data, $where ) {
		global $wpdb;
		if ( array_key_exists( 'formdata', $where ) ) {
			$where['formdata'] = $this->legacy_configuration_formdata( $where['formdata'], $where );
			if ( false === $where['formdata'] ) {
				return false;
			}
		}
		$set = $conditions = array();
		foreach ( $data as $field => $value ) {
			$column = '`' . str_replace( '`', '``', $field ) . '`';
			$set[] = $column . ( null === $value ? ' = NULL' : $wpdb->prepare( ' = %s', $value ) );
		}
		foreach ( $where as $field => $value ) {
			$conditions[] = 'formdata' === $field ? $this->configuration_where( $value ) : $wpdb->prepare( '`' . str_replace( '`', '``', $field ) . '` = %s', $value );
		}
		if ( ! $set || ! $conditions ) {
			return false;
		}
		return $wpdb->query( "UPDATE `{$this->table}` SET " . implode( ', ', $set ) . ' WHERE ' . implode( ' AND ', $conditions ) );
	}

	/** Sort object keys recursively; the order of numeric lists remains meaningful. */
	private static function canonicalize_configuration( $value ) {
		$is_object = $value instanceof stdClass;
		if ( ! is_array( $value ) && ! $is_object ) {
			return $value;
		}
		$values = (array) $value;
		foreach ( $values as $key => $item ) {
			$values[ $key ] = self::canonicalize_configuration( $item );
		}
		if ( $is_object || ( $values && array_keys( $values ) !== range( 0, count( $values ) - 1 ) ) ) {
			ksort( $values, SORT_STRING );
		}
		return $is_object ? (object) $values : $values;
	}

	private static function canonical_stored_configuration( $formdata ) {
		if ( ! is_string( $formdata ) || '' === $formdata ) {
			return false;
		}
		$value = json_decode( $formdata );
		if ( JSON_ERROR_NONE !== json_last_error() || ( ! is_array( $value ) && ! $value instanceof stdClass ) ) {
			return false;
		}
		return json_encode( self::canonicalize_configuration( $value ) );
	}

	/** Reuse old key orders without rewriting stored addons or running prepare hooks on saved values. */
	private function legacy_configuration_formdata( $formdata, $scope ) {
		global $wpdb;
		$formdata = is_array( $formdata ) ? $formdata : array( $formdata );
		$identities = array();
		foreach ( $formdata as $value ) {
			$canonical = self::canonical_stored_configuration( $value );
			if ( false !== $canonical ) {
				$identities[ $canonical ] = true;
			}
		}
		if ( ! $identities ) {
			return $formdata;
		}
		$where = array();
		foreach ( array( 'ID', 'wishlist_id', 'product_id', 'variation_id', 'author' ) as $field ) {
			if ( ! array_key_exists( $field, $scope ) ) {
				continue;
			}
			$values = is_array( $scope[ $field ] ) ? $scope[ $field ] : array( $scope[ $field ] );
			if ( ! $values ) {
				return array();
			}
			$values = array_map( static function ( $value ) use ( $wpdb ) { return $wpdb->prepare( '%s', $value ); }, $values );
			$where[] = '`' . $field . '` IN (' . implode( ',', $values ) . ')';
		}
		// DISTINCT would use the case-insensitive column collation and discard distinct configurations.
		$rows = $wpdb->get_results( "SELECT `formdata` FROM `{$this->table}` WHERE " . ( $where ? implode( ' AND ', $where ) : '1' ), ARRAY_A );
		if ( $wpdb->last_error ) {
			return false;
		}
		foreach ( (array) $rows as $row ) {
			$canonical = self::canonical_stored_configuration( $row['formdata'] );
			if ( false !== $canonical && isset( $identities[ $canonical ] ) && ! in_array( $row['formdata'], $formdata, true ) ) {
				$formdata[] = $row['formdata'];
			}
		}
		return $formdata;
	}

	/**
	 * Check existing product
	 *
	 * @param integer $product_id Product id.
	 * @param integer $variation_id Product variaton id.
	 * @param integer $wishlist_id If exist wishlist object, you can put 0.
	 * @param array $meta Object meta form data.
	 *
	 * @return mixed
	 */
	function check_product( $product_id, $variation_id = 0, $wishlist_id = 0, $meta = array() ) {
		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );
		$wishlist_id  = absint( $wishlist_id );

		if ( empty( $wishlist_id ) ) {
			$wishlist_id = $this->wishlist_id();
		}
		if ( empty( $wishlist_id ) || empty( $product_id ) ) {
			return false;
		}

		$product_data = $this->product_data( $product_id, $variation_id );

		if ( ! $product_data ) {
			return false;
		}

		$product_id   = $product_data->is_type( 'variation' ) ? $product_data->get_parent_id() : $product_data->get_id();
		$variation_id = $product_data->is_type( 'variation' ) ? $product_data->get_id() : 0;

		global $wpdb;
		$products = $this->get( array(
			'product_id'   => $product_id,
			'variation_id' => $variation_id,
			'wishlist_id'  => $wishlist_id,
			'formdata'     => $this->configuration_formdata( $meta, $product_id, $variation_id ),
			'count'        => 1,
			'external'     => false,
		) );

		if ( $wpdb->last_error ) {
			return false; // A failed existence read is not permission to insert a duplicate.
		}

		return array_shift( $products );
	}

	/**
	 * Get products
	 *
	 * @param array $data Request.
	 * @param bool $count COUNT QUERY.
	 *
	 * @return array
	 * @global wpdb $wpdb
	 *
	 */
	function get( $data = array(), $count = false ) {
		global $wpdb;
		if ( array_key_exists( 'formdata', $data ) ) {
			$data['formdata'] = $this->legacy_configuration_formdata( $data['formdata'], $data );
			if ( false === $data['formdata'] ) {
				return array();
			}
		}

		$default = array(
			'count'    => 10,
			'field'    => null,
			'offset'   => 0,
			'order'    => 'DESC',
			'order_by' => 'date',
			'external' => true,
			'sql'      => '',
		);

		foreach ( $default as $_k => $_v ) {
			if ( array_key_exists( $_k, $data ) ) {
				$default[ $_k ] = $data[ $_k ];
				unset( $data[ $_k ] );
			}
		}

		$default['offset'] = absint( $default['offset'] );
		$default['count']  = absint( $default['count'] );
		//the order value is passed directly to the db so it needs to be protected against sql_injections
		$valid_order_values = array( 'ASC', 'DESC' );
		if ( ! in_array( strtoupper( $default['order'] ), $valid_order_values, true ) ) {
			$default['order'] = 'DESC';
		}

		if ( is_array( $default['field'] ) ) {
			$default['field'] = '`' . implode( '`,`', $default['field'] ) . '`';
		} elseif ( is_string( $default['field'] ) ) {
			$default['field'] = array( 'ID', $default['field'] );
			$default['field'] = '`' . implode( '`,`', $default['field'] ) . '`';
		} else {
			$default['field'] = '*';
		}
		if ( $count ) {
			$default['field'] = 'COUNT(`ID`) as `count`';
		}

		$sql   = "SELECT {$default[ 'field' ]} FROM `{$this->table}`";
		$where = '1';
		if ( ! empty( $data ) && is_array( $data ) ) {
			if ( array_key_exists( 'meta', $data ) ) {
				unset( $data['meta'] );
			}
			foreach ( $data as $f => $v ) {
				if ( 'formdata' === $f ) {
					$data[ $f ] = $this->configuration_where( $v );
					continue;
				}
				$s = is_array( $v ) ? ' IN ' : '=';
				if ( is_array( $v ) ) {
					foreach ( $v as $_f => $_v ) {
						$v[ $_f ] = $wpdb->prepare( '%s', $_v );
					}
					$v = implode( ',', $v );
					$v = "($v)";
				} else {
					$v = $wpdb->prepare( '%s', $v );
				}
				$data[ $f ] = sprintf( '`%s`%s%s', $f, $s, $v );
			}
			$where = implode( ' AND ', $data );
		}
		if ( $this->public_context ) {
			$where = $this->public_product_where( $where );
			if ( $wpdb->last_error ) {
				return array(); // Preserve a failed access lookup instead of masking it with another query.
			}
		}
		$sql .= ' WHERE ' . $where;

		$sql .= sprintf( ' ORDER BY `%s` %s LIMIT %d,%d;', $default['order_by'], $default['order'], $default['offset'], $default['count'] );
		if ( ! empty( $default['sql'] ) ) {
			$replacer    = $replace = array();
			$replace[0]  = '{table}';
			$replacer[0] = $this->table;
			$replace[1]  = '{where}';
			$replacer[1] = $where;

			foreach ( $default as $key => $value ) {
				$i = count( $replace );

				$replace[ $i ]  = '{' . $key . '}';
				$replacer[ $i ] = $value;
			}

			$sql = str_replace( $replace, $replacer, $default['sql'] );
		}
		$products = $wpdb->get_results( $sql, ARRAY_A ); // WPCS: db call ok; no-cache ok; unprepared SQL ok.

		if ( empty( $products ) || is_wp_error( $products ) ) {
			return array();
		}

		if ( $count ) {
			return $products[0]['count'];
		}
		if ( $this->storage_context ) {
			foreach ( $products as &$product ) {
				$meta = $product['formdata'] ?? '';
				$product['meta'] = '' === $meta || null === $meta ? array() : json_decode( $meta, true );
				if ( ! is_array( $product['meta'] ) ) {
					throw new RuntimeException( 'Could not decode stored wishlist item metadata.' );
				}
				// Preserve the write-prepare hook contract without running display/unprepare filters.
				if ( ! empty( $product['meta'] ) && ! empty( $product['product_id'] ) ) {
					$product['meta']['add-to-cart'] = $product['product_id'];
					$product['meta']['product_id'] = $product['product_id'];
					$product['meta']['quantity'] = $product['quantity'];
					if ( ! empty( $product['variation_id'] ) ) {
						$product['meta']['variation_id'] = $product['variation_id'];
					}
				}
			}
			unset( $product );
			return $products;
		}
		$ids                = array();
		$stored_product_ids = array();
		foreach ( $products as $k => $product ) {
			// Display and translation filters must not decide which stored product has been deleted.
			$stored_product_ids[ $k ] = array(
				'ID'           => absint( $product['ID'] ?? 0 ),
				'product_id'   => absint( $product['product_id'] ?? 0 ),
				'variation_id' => absint( $product['variation_id'] ?? 0 ),
			);
			if ( empty( $default['sql'] ) ) {
				$product             = filter_var_array( $product, array(
					'ID'           => FILTER_VALIDATE_INT,
					'wishlist_id'  => FILTER_VALIDATE_INT,
					'product_id'   => FILTER_VALIDATE_INT,
					'variation_id' => FILTER_VALIDATE_INT,
					'author'       => FILTER_VALIDATE_INT,
					'date'         => FILTER_DEFAULT,
					'formdata'     => FILTER_DEFAULT,
					'quantity'     => FILTER_VALIDATE_INT,
					'price'        => array(
						'filter' => FILTER_SANITIZE_NUMBER_FLOAT,
						'flags'  => FILTER_FLAG_ALLOW_FRACTION,
					),
					'in_stock'     => FILTER_VALIDATE_BOOLEAN,
				) );
				$product['quantity'] = 1;
			}

			if ( $default['external'] ) {
				if ( isset( $product['product_id'] ) ) {
					$ids[] = ( apply_filters( 'wpml_object_id', $product['product_id'], 'product', false ) ) ? apply_filters( 'wpml_object_id', $product['product_id'], 'product', false ) : $product['product_id'];
				}
			}
			$product['meta'] = array();
			if ( array_key_exists( 'formdata', $product ) ) {
				$meta = $product['formdata'];
				unset( $product['formdata'] );

				$product['meta'] = $this->prepare_retrun_meta( $meta, $product['product_id'], $product['variation_id'], $product['quantity'] );
			}
			$products[ $k ] = apply_filters( 'tinvwl_wishlist_product_get', $product );
		}

		if ( ! empty( $ids ) ) {
			$args      = array(
				'include' => $ids,
				'limit'   => count( $ids ),
			);
			if ( $this->public_context ) {
				$args['status'] = get_post_stati(); // Access was checked above; include authorized previews as well.
			}
			$_products = wc_get_products( apply_filters( 'tinvwl_wishlist_product_query_args', $args ) );
			foreach ( $_products as $_product ) {

				foreach ( $products as $key => $wlproduct ) {
					if ( ! isset( $wlproduct['product_id'] ) ) {
						continue;
					}

					if ( $_product->get_id() === absint( ( apply_filters( 'wpml_object_id', $wlproduct['product_id'], 'product', false ) ) ? apply_filters( 'wpml_object_id', $wlproduct['product_id'], 'product', false ) : $wlproduct['product_id'] ) ) {
						if ( in_array( $_product->get_type(), array( 'variable', 'grouped' ) ) ) {
							$use_original_id = false;

							if ( function_exists( 'pll_is_translated_post_type' ) ) {
								$use_original_id = true;
							}

							if ( function_exists( 'wpml_get_current_language' ) ) {

								global $sitepress;

								if ( $sitepress && $sitepress instanceof SitePress ) {
									$wpml_settings = $sitepress->get_settings();
									if ( isset( $wpml_settings['custom_posts_sync_option'] ) && isset( $wpml_settings['custom_posts_sync_option']['product_variation'] ) && in_array( $wpml_settings['custom_posts_sync_option']['product_variation'], array(
											1,
											2,
										) ) ) {
										$use_original_id = true;
									}
								}
							}

							$products[ $key ]['data'] = $wlproduct['variation_id'] ? wc_get_product( apply_filters( 'wpml_object_id', $wlproduct['variation_id'], 'product_variation', $use_original_id ) ) : $_product;
						} else {
							$products[ $key ]['data'] = $_product;
						}
					}
				}
			}

			// An omitted Woo result can mean a read error or a presentation filter, not deletion.
			if ( $default['external'] && ! function_exists( 'wpml_get_current_language' ) ) {
				$unavailable = $check_ids = array();
				foreach ( $products as $key => $product ) {
					if ( empty( $product['data'] ) ) {
						unset( $products[ $key ] );
						$stored = $stored_product_ids[ $key ];
						if ( $stored['ID'] && $stored['product_id'] ) {
							$unavailable[ $key ] = $product;
							$check_ids[ $stored['product_id'] ] = $stored['product_id'];
							if ( $stored['variation_id'] ) {
								$check_ids[ $stored['variation_id'] ] = $stored['variation_id'];
							}
						}
					}
				}
				if ( $check_ids ) {
					// Check original posts directly, without catalog, access, translation or object-cache filters.
					$existing_ids = $wpdb->get_col( "SELECT ID FROM `{$wpdb->posts}` WHERE ID IN (" . implode( ',', $check_ids ) . ')' );
					if ( '' === $wpdb->last_error ) {
						$existing_ids = array_fill_keys( $existing_ids, true );
						foreach ( $unavailable as $key => $product ) {
							$stored = $stored_product_ids[ $key ];
							if ( ! isset( $existing_ids[ $stored['product_id'] ] ) || ( $stored['variation_id'] && ! isset( $existing_ids[ $stored['variation_id'] ] ) ) ) {
								$product['ID'] = $stored['ID'];
								self::for_storage( $this->wishlist, $this->_name )->remove( $product );
							}
						}
					}
				}
			}
		}

		// Filter wishlist products
		$products = apply_filters( 'tinvwl_wishlist_get_products', $products, $this );
		if ( $this->public_context ) {
			$products = self::filter_readable_products( $products );
		}


		return $products;
	}

	/**
	 * Get product info
	 *
	 * @param integer $product_id Product id.
	 * @param integer $variation_id Product variation id.
	 *
	 * @return mixed
	 */
	function product_data( $product_id, $variation_id = 0 ) {
		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );
		if ( $this->public_context && ! self::can_read_product_ids( $product_id, $variation_id ) ) {
			return null;
		}

		$product_data = apply_filters( 'tinvwl_product_data', wc_get_product( $variation_id ? $variation_id : $product_id ), $product_id, $variation_id );

		if ( ! $product_data || 'trash' === get_post( $product_data->get_id() )->post_status || ( $this->public_context && ! self::can_read_product( $product_data ) ) ) {
			return null;
		}

		return $product_data;
	}

	/**
	 * Update product
	 *
	 * @param array $data Object product.
	 * @param array $meta Object meta form data.
	 * @param int $id Wishlist item ID.
	 *
	 * @return boolean
	 * @global wpdb $wpdb
	 *
	 */
	function update( $data = array(), $meta = array(), $id = 0 ) {
		$target = absint( $data['wishlist_id'] ?? 0 ) ?: absint( $this->wishlist_id() );
		if ( $this->public_context && ! isset( $this->owned_mutations[ $target ] ) ) {
			return $this->with_current_owner( $target, function () use ( $data, $meta, $id ) { return $this->update( $data, $meta, $id ); } );
		}
		$has_quantity = array_key_exists( 'quantity', $data );
		if ( empty( $meta ) && array_key_exists( 'meta', $data ) && ! empty( $data['meta'] ) ) {
			$meta = $data['meta'];
		}

		$data = filter_var_array( $data, apply_filters( 'tinvwl_wishlist_product_update_field', array(
			'product_id'   => FILTER_VALIDATE_INT,
			'quantity'     => $this->storage_context ? FILTER_VALIDATE_FLOAT : FILTER_VALIDATE_INT,
			'variation_id' => FILTER_VALIDATE_INT,
			'wishlist_id'  => FILTER_VALIDATE_INT,
			'author'       => FILTER_VALIDATE_INT,
		) ) );
		$quantity = $this->storage_context && $has_quantity ? $this->storage_quantity( $data['quantity'] ?? null ) : null;
		if ( false === $quantity ) {
			return false;
		}
		$data = array_filter( $data );
		if ( null !== $quantity ) {
			$data['quantity'] = $quantity;
		}

		if ( ! array_key_exists( 'wishlist_id', $data ) ) {
			$data['wishlist_id'] = $this->wishlist_id();
		}
		if ( ! array_key_exists( 'variation_id', $data ) ) {
			$data['variation_id'] = 0;
		}

		if ( empty( $data['wishlist_id'] ) || empty( $data['product_id'] ) ) {
			return false;
		}
		$product_data = $this->product_data( $data['product_id'], $data['variation_id'] );
		if ( ! $product_data ) {
			return false;
		}

		if ( $product_data->is_sold_individually() ) {
			$data['quantity'] = 1;
		}

		$data             = apply_filters( 'tinvwl_wishlist_product_update', $data );
		$data['in_stock'] = $product_data->is_in_stock();

		if ( $product_data->is_type( 'variable' ) ) {
			$data['price'] = filter_var( $product_data->get_variation_price( 'max', false ), FILTER_VALIDATE_FLOAT );
		} else {
			$data['price'] = filter_var( $product_data->get_price(), FILTER_VALIDATE_FLOAT );
		}

		global $wpdb;

		$res_update = $this->update_configuration_rows( $data, array(
			'product_id'   => $data['product_id'],
			'variation_id' => $data['variation_id'],
			'wishlist_id'  => $data['wishlist_id'],
			'formdata'     => $this->configuration_formdata( $meta, $data['product_id'], $data['variation_id'] ),
		) );

		if ( $res_update !== false ) { // @codingStandardsIgnoreLine WordPress.VIP.DirectDatabaseQuery.DirectQuery

			/* Run a 3rd party code when product updated on a wishlist.
			 *
			 * @param array $data product data including author and wishlist IDs.
			 * */
			$this->product_action( 'tinvwl_product_updated', $data );

			return ( $id ) ?: true;
		}

		return false;
	}

	/**
	 * Remove product from wishlist.
	 *
	 * @param int $wishlist_id If exist wishlist object, you can put 0.
	 * @param int $product_id Product ID.
	 * @param int $variation_id Product variation ID.
	 * @param array $meta Object meta form data.
	 *
	 * @return bool
	 */
	function remove_product_from_wl( int $wishlist_id = 0, int $product_id = 0, int $variation_id = 0, array $meta = [] ): bool {
		$target = absint( $wishlist_id ) ?: absint( $this->wishlist_id() );
		if ( $this->public_context && ! isset( $this->owned_mutations[ $target ] ) ) {
			return $this->with_current_owner( $target, function () use ( $wishlist_id, $product_id, $variation_id, $meta ) { return $this->remove_product_from_wl( $wishlist_id, $product_id, $variation_id, $meta ); } );
		}
		global $wpdb;

		$wishlist_id  = absint( $wishlist_id );
		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );

		if ( empty( $wishlist_id ) ) {
			$wishlist_id = $this->wishlist_id();
		}
		if ( empty( $wishlist_id ) ) {
			return false;
		}
		if ( empty( $product_id ) ) {
			return false !== $wpdb->delete( $this->table, [ 'wishlist_id' => $wishlist_id ] ); // WPCS: db call ok; no-cache ok; unprepared SQL ok.
		}

		$sql  = "DELETE FROM $this->table WHERE `wishlist_id` = %d AND `product_id` = %d AND `variation_id` = %d";
		$args = [ $wishlist_id, $product_id, $variation_id ];

		// Legacy encodings may match the same configuration, never an unrelated plain item.
		$formdata = $this->configuration_formdata( $meta, $product_id, $variation_id );
		$formdata = $this->legacy_configuration_formdata( $formdata, array( 'wishlist_id' => $wishlist_id, 'product_id' => $product_id, 'variation_id' => $variation_id ) );
		if ( empty( $formdata ) ) {
			return false;
		}
		$prepared_sql = $wpdb->prepare( $sql, $args ) . ' AND ' . $this->configuration_where( $formdata );
		$result       = $wpdb->query( $prepared_sql );

		if ( $result ) {
			do_action( 'tinvwl_wishlist_product_removed_from_wishlist', $wishlist_id, $product_id, $variation_id );
		}

		return (bool) $result;
	}

	/** Read authoritative source configuration/quantity without display or request preparation. */
	public function get_parent_source( $item_id, $product_id, $current_read = false ) {
		global $wpdb;
		$item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE ID = %d AND wishlist_id = %d AND product_id = %d AND variation_id = 0", absint( $item_id ), absint( $this->wishlist_id() ), absint( $product_id ) ) . ( $current_read ? ' FOR UPDATE' : '' ), ARRAY_A );
		if ( $wpdb->last_error || ! $item ) { return false; }
		$meta = null === $item['formdata'] || '' === $item['formdata'] ? array() : json_decode( $item['formdata'], true );
		if ( ! is_array( $meta ) ) { return false; }
		if ( isset( $meta['tinvwl-hidden-fields'] ) && '[]' === $meta['tinvwl-hidden-fields'] ) {
			unset( $meta['tinvwl-hidden-fields'] );
		}
		$parent = wc_get_product( $product_id );
		return ! $meta && $parent && $parent->is_type( array( 'variable', 'variable-subscription' ) ) ? $item : false;
	}

	/** Consume an owned, unconfigured parent row after a native variation add. */
	public function consume_parent_source( $item_id, $product_id, $quantity ) {
		$target = absint( $this->wishlist_id() );
		$quantity = filter_var( $quantity, FILTER_VALIDATE_FLOAT );
		if ( ! $target || ! $item_id || ! $product_id || false === $quantity || ! is_finite( $quantity ) || $quantity <= 0 ) {
			return false;
		}
		// Always enforce public ownership, even if a trusted storage model called this API.
		if ( ! isset( $this->owned_mutations[ $target ] ) ) {
			return $this->with_current_owner( $target, function () use ( $item_id, $product_id, $quantity ) { return $this->consume_parent_source( $item_id, $product_id, $quantity ); } );
		}
		$item = $this->get_parent_source( $item_id, $product_id, true );
		if ( ! $item ) { return false; }
		return $this->remove_item_from_wl( $item );
	}

	/** Remove one selected row, scoped to this wishlist and its stored product identity. */
	function remove_item_from_wl( array $item ): bool {
		$target = absint( $this->wishlist_id() );
		if ( $this->public_context && ! isset( $this->owned_mutations[ $target ] ) ) {
			return $this->with_current_owner( $target, function () use ( $item ) { return $this->remove_item_from_wl( $item ); } );
		}
		$wishlist_id  = absint( $this->wishlist_id() );
		$item_id      = absint( $item['ID'] ?? 0 );
		$product_id   = absint( $item['product_id'] ?? 0 );
		$variation_id = absint( $item['variation_id'] ?? 0 );
		if ( ! $wishlist_id || ! $item_id || ! $product_id || ( isset( $item['wishlist_id'] ) && absint( $item['wishlist_id'] ) !== $wishlist_id ) ) {
			return false;
		}
		global $wpdb;
		$result = $wpdb->delete( $this->table, array( 'ID' => $item_id, 'wishlist_id' => $wishlist_id, 'product_id' => $product_id, 'variation_id' => $variation_id ) );
		if ( $result ) {
			do_action( 'tinvwl_wishlist_product_removed_from_wishlist', $wishlist_id, $product_id, $variation_id );
		}
		return (bool) $result;
	}

	/**
	 * Remove product
	 *
	 * @param integer $product_id Product id.
	 *
	 * @return boolean
	 * @global wpdb $wpdb
	 *
	 */
	function remove_product( $product_id = 0 ) {
		$target = absint( $this->wishlist_id() );
		if ( $this->public_context && ! isset( $this->owned_mutations[ $target ] ) ) {
			return $this->with_current_owner( $target, function () use ( $product_id ) { return $this->remove_product( $product_id ); } );
		}
		global $wpdb;
		if ( empty( $product_id ) ) {
			return false;
		}
		$product_id = absint( $product_id );

		$where = array( 'product_id' => $product_id );
		if ( $this->public_context ) {
			$where['wishlist_id'] = $target;
		}
		$result = false !== $wpdb->delete( $this->table, $where ); // WPCS: db call ok; no-cache ok; unprepared SQL ok.
		if ( $result ) {
			do_action( 'tinvwl_wishlist_product_removed_by_product', $product_id );
		}

		return $result;
	}

	/**
	 * Get wishlist data by product from wishlist
	 *
	 * @param integer $product_id Product id.
	 *
	 * @return bool|array
	 * @global wpdb $wpdb
	 *
	 */
	function get_wishlist_by_product_id( $product_id = 0 ) {
		global $wpdb;
		if ( empty( $product_id ) ) {
			return false;
		}
		$product_id = absint( $product_id );

		$prepared_sql = $wpdb->prepare( "SELECT `wishlist_id` FROM `{$this->table}` WHERE `ID`=%d", $product_id );
		$result       = $wpdb->get_results( $prepared_sql, ARRAY_A );

		if ( ! $result ) {
			return false;
		}

		$wl = new TInvWL_Wishlist();

		return $wl->get_by_id( $result[0]['wishlist_id'] );
	}

	/**
	 * Remove product by ID
	 *
	 * @param array $data Product data.
	 *
	 * @return boolean
	 * @global wpdb $wpdb
	 *
	 */
	function remove( $data ) {
		if ( $this->public_context ) {
			global $wpdb;
			$target = $wpdb->get_var( $wpdb->prepare( "SELECT wishlist_id FROM `{$this->table}` WHERE ID = %d", absint( $data['ID'] ?? 0 ) ) );
			$expected = absint( $data['wishlist_id'] ?? 0 ) ?: absint( $this->wishlist_id() );
			if ( $wpdb->last_error || ! $target || ( $expected && (int) $target !== $expected ) ) {
				return false;
			}
			if ( ! isset( $this->owned_mutations[ $target ] ) ) {
				return $this->with_current_owner( $target, function () use ( $data ) { return $this->remove( $data ); } );
			}
		}

		if ( ! isset( $data['ID'] ) || empty( $data['ID'] ) ) {
			return false;
		}

		global $wpdb;
		$where = array( 'ID' => $data['ID'] );
		if ( $this->public_context ) {
			$where['wishlist_id'] = (int) $target;
		}
		$result = false !== $wpdb->delete( $this->table, $where ); // WPCS: db call ok; no-cache ok; unprepared SQL ok.
		if ( $result ) {
			/* Run a 3rd party code when product removed from a wishlist.
			 *
			 * @param array $data product data including author and wishlist IDs.
			 * */
			do_action( 'tinvwl_product_removed', $data );
		}

		return $result;
	}

	/**
	 * Clean meta from default values.
	 *
	 * @param array $meta Meta array.
	 *
	 * @return array
	 */
	function clean_meta( $meta ) {

		foreach (
			array(
				'add-to-cart',
				'product_id',
				'variation_id',
				'quantity',
				'undefined',
				'product_sku',
			) as $field
		) {
			if ( array_key_exists( $field, $meta ) ) {
				unset( $meta[ $field ] );
			}
		}
		$meta = array_filter( $meta, static function ( $value ) {
			return is_numeric( $value ) || ! empty( $value );
		} );

		return $meta;
	}

	/**
	 * Prepare to save meta in database
	 *
	 * @param array $meta Meta array.
	 * @param int $product_id Woocommerce product ID.
	 * @param int $variation_id Woocommerce product variation ID.
	 *
	 * @return string
	 */
	function prepare_save_meta( $meta = array(), $product_id = 0, $variation_id = 0 ) {
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		$meta = apply_filters( 'tinvwl_product_prepare_meta', $meta, $product_id, $variation_id );
		foreach (
			array(
				'add-to-cart',
				'product_id',
				'variation_id',
				'quantity',
				'undefined',
				'product_sku',
			) as $field
		) {
			if ( array_key_exists( $field, $meta ) ) {
				unset( $meta[ $field ] );
			}
		}
		$meta = array_filter( $meta, static function ( $value ) {
			return is_numeric( $value ) || ! empty( $value );
		} );
		if ( empty( $meta ) ) {
			return '';
		}

		return json_encode( self::canonicalize_configuration( $meta ) );
	}

	/**
	 * Convert meta string to array
	 *
	 * @param string $meta Meta array.
	 * @param integer $product_id Product ID.
	 * @param integer $variation_id Variation product ID.
	 * @param integer $quantity Quantity product.
	 *
	 * @return array
	 */
	function prepare_retrun_meta( $meta = '', $product_id = 0, $variation_id = 0, $quantity = 1 ) {
		if ( empty( $meta ) ) {
			return array();
		}
		$meta = @json_decode( $meta, true );
		if ( empty( $meta ) || ! is_array( $meta ) ) {
			return array();
		}
		if ( ! empty( $product_id ) ) {
			$meta['add-to-cart'] = $product_id;
			$meta['product_id']  = $product_id;
			$meta['quantity']    = $quantity;
			if ( ! empty( $variation_id ) ) {
				$meta['variation_id'] = $variation_id;
			}
		}

		return apply_filters( 'tinvwl_wishlist_product_unprepare_meta', $meta );
	}
}
