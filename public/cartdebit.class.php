<?php
/** Durable, idempotent wishlist debits, independent of successful native cart additions. */
defined( 'ABSPATH' ) || exit;

class TInvWL_Public_CartDebit {
	private static $installed = array();

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . TINVWL_PREFIX . '_cart_debits';
	}

	private static function log( $message ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( $message, array( 'source' => 'tinvwl-cart-debits' ) );
		} else { error_log( 'TI Wishlist cart debits: ' . $message ); }
	}

	/** Install before any native cart effect, and never commit a caller's transaction. */
	public static function maybe_install() {
		global $wpdb;
		$table = self::table();
		if ( isset( self::$installed[ $table ] ) ) { return true; }
		$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ), ARRAY_A );
		if ( $wpdb->last_error ) { return false; }
		if ( ! $status ) {
			$previous = $wpdb->suppress_errors( true );
			try { $independent = $wpdb->query( 'SET TRANSACTION READ WRITE' ); }
			finally { $wpdb->suppress_errors( $previous ); }
			if ( false === $independent ) { return false; }
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$collate = $wpdb->get_charset_collate();
			dbDelta( "CREATE TABLE `$table` (
				ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				operation_key char(64) NOT NULL,
				wishlist_id bigint(20) unsigned NOT NULL,
				item_id bigint(20) unsigned NOT NULL,
				payload longtext NOT NULL,
				state varchar(12) NOT NULL DEFAULT 'prepared',
				attempts int(10) unsigned NOT NULL DEFAULT 0,
				available_at bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at bigint(20) unsigned NOT NULL DEFAULT 0,
				updated_at bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (ID),
				UNIQUE KEY operation_key (operation_key),
				KEY ready (state,available_at,ID)
			) ENGINE=InnoDB $collate;" );
			$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ), ARRAY_A );
		}
		if ( $wpdb->last_error || ! $status || ! in_array( strtolower( $status['Engine'] ), array( 'innodb', 'xtradb' ), true ) ) { return false; }
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM `$table`" );
		if ( $wpdb->last_error || array_diff( array( 'ID', 'operation_key', 'wishlist_id', 'item_id', 'payload', 'state', 'attempts', 'available_at', 'created_at', 'updated_at' ), (array) $columns ) ) { return false; }
		self::$installed[ $table ] = true;
		return true;
	}

	public static function init() {
		add_action( 'tinvwl_cart_debit_retry', array( __CLASS__, 'run' ) );
		add_action( 'tinvwl_cart_debits', array( __CLASS__, 'run' ) );
		add_action( 'woocommerce_cart_loaded_from_session', array( __CLASS__, 'resume_session' ) );
		if ( ! wp_next_scheduled( 'tinvwl_cart_debits' ) ) { wp_schedule_event( time() + 60, 'hourly', 'tinvwl_cart_debits' ); }
	}

	private static function schedule( $at ) {
		$next = wp_next_scheduled( 'tinvwl_cart_debit_retry' );
		if ( ! $next || $next > $at ) {
			if ( $next ) { wp_unschedule_event( $next, 'tinvwl_cart_debit_retry' ); }
			wp_schedule_single_event( max( time() + 1, (int) $at ), 'tinvwl_cart_debit_retry' );
		}
	}

	/** Exact non-negative decimal arithmetic keeps shared BIGINT/DECIMAL contributions intact. */
	private static function decimal( $value ) {
		$value = (string) $value;
		if ( ! preg_match( '/\A(\d*(?:\.\d*)?)(?:[eE]([+-]?\d+))?\z/D', $value, $m ) || ! is_numeric( $value ) || ! is_finite( (float) $value ) || (float) $value < 0 ) { throw new RuntimeException( 'Invalid contribution quantity.' ); }
		$parts = explode( '.', $m[1] ); $digits = implode( '', $parts );
		$point = strlen( $parts[0] ) + (int) ( $m[2] ?? 0 );
		if ( $point < 0 ) { $digits = str_repeat( '0', -$point ) . $digits; $point = 0; }
		if ( $point > strlen( $digits ) ) { $digits .= str_repeat( '0', $point - strlen( $digits ) ); }
		$integer = ltrim( substr( $digits, 0, $point ), '0' ); $fraction = rtrim( substr( $digits, $point ), '0' );
		return ( '' === $integer ? '0' : $integer ) . ( '' === $fraction ? '' : '.' . $fraction );
	}

	private static function decimal_parts( $a, $b ) {
		$a = explode( '.', self::decimal( $a ) ); $b = explode( '.', self::decimal( $b ) );
		$scale = max( strlen( $a[1] ?? '' ), strlen( $b[1] ?? '' ) );
		$a = $a[0] . str_pad( $a[1] ?? '', $scale, '0' ); $b = $b[0] . str_pad( $b[1] ?? '', $scale, '0' );
		$length = max( strlen( $a ), strlen( $b ) );
		return array( str_pad( $a, $length, '0', STR_PAD_LEFT ), str_pad( $b, $length, '0', STR_PAD_LEFT ), $scale );
	}

	private static function decimal_compare( $a, $b ) {
		list( $a, $b ) = self::decimal_parts( $a, $b ); return strcmp( $a, $b );
	}

	private static function decimal_sum( $a, $b, $subtract = false ) {
		list( $a, $b, $scale ) = self::decimal_parts( $a, $b ); $carry = 0; $digits = '';
		if ( $subtract && strcmp( $a, $b ) < 0 ) { throw new RuntimeException( 'Contribution underflow.' ); }
		for ( $i = strlen( $a ) - 1; $i >= 0; --$i ) {
			$n = (int) $a[$i] + ( $subtract ? -(int) $b[$i] : (int) $b[$i] ) + $carry;
			$carry = $subtract ? ( $n < 0 ? -1 : 0 ) : ( $n >= 10 ? 1 : 0 );
			$digits = (string) ( ( $n + 10 ) % 10 ) . $digits;
		}
		if ( $carry > 0 ) { $digits = $carry . $digits; }
		if ( $scale ) { $digits = substr( $digits, 0, -$scale ) . '.' . substr( $digits, -$scale ); }
		return self::decimal( $digits );
	}

	private static function same_source( $payload, $wishlist, $item ) {
		$identity = TInvWL_Product::saved_configuration_identity( $item['meta'] );
		return false !== $identity && is_array( $payload ) && isset( $payload['author'], $payload['share_key'], $payload['product_id'], $payload['variation_id'] )
			&& (int) $wishlist['author'] === (int) $payload['author'] && hash_equals( (string) $wishlist['share_key'], (string) $payload['share_key'] )
			&& (int) $payload['product_id'] === (int) $item['product_id'] && (int) $payload['variation_id'] === (int) $item['variation_id']
			&& TInvWL_Product::saved_configuration_identity( $payload['meta'] ?? null ) === $identity;
	}

	/** Under the item mutex and transaction, register only newly observed quantity as a new contribution. */
	private static function contributions( $wishlist, $item, $register = true ) {
		global $wpdb; $table = self::table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$table` WHERE wishlist_id = %d AND item_id = %d AND state = 'contribution' ORDER BY ID FOR UPDATE", $wishlist['ID'], $item['ID'] ), ARRAY_A );
		if ( $wpdb->last_error || ! is_array( $rows ) ) { throw new RuntimeException( 'Could not read contribution bounds.' ); }
		$available = self::decimal( $item['quantity'] ); $keys = array();
		foreach ( $rows as $row ) {
			$payload = json_decode( $row['payload'], true );
			if ( ! self::same_source( $payload, $wishlist, $item ) ) { continue; }
			$remaining = self::decimal( $payload['remaining'] ?? '' );
			$kept = self::decimal_compare( $remaining, $available ) > 0 ? $available : $remaining;
			if ( $remaining !== $kept ) {
				$payload['remaining'] = $kept;
				if ( 1 !== $wpdb->update( $table, array( 'payload' => wp_json_encode( $payload ), 'updated_at' => time() ), array( 'ID' => $row['ID'] ) ) ) { throw new RuntimeException( 'Could not reduce contribution bounds.' ); }
			}
			$available = self::decimal_sum( $available, $kept, true ); $keys[] = $row['operation_key'];
		}
		if ( $register && ( '0' !== $available || ! $keys ) ) {
			$key = bin2hex( random_bytes( 32 ) );
			$payload = array( 'author' => (int) $wishlist['author'], 'share_key' => $wishlist['share_key'], 'product_id' => (int) $item['product_id'], 'variation_id' => (int) $item['variation_id'], 'meta' => $item['meta'], 'remaining' => $available );
			if ( 1 !== $wpdb->insert( $table, array( 'operation_key' => $key, 'wishlist_id' => $wishlist['ID'], 'item_id' => $item['ID'], 'payload' => wp_json_encode( $payload ), 'state' => 'contribution', 'created_at' => time(), 'updated_at' => time() ) ) ) { throw new RuntimeException( 'Could not save contribution bounds.' ); }
			$keys[] = $key;
		}
		return $keys;
	}

	/** Spend shared source authority in the same transaction as the physical debit and completion receipt. */
	public static function bounded_payload( $wishlist, $operation, $payload ) {
		global $wpdb;
		// Legacy operations without a provable original bound remain pending rather than consume a merged list.
		if ( empty( $payload['contributions'] ) || ! is_array( $payload['contributions'] ) ) {
			self::log( 'An older cart debit has no original quantity bound and remains pending to protect saved quantities.' );
			return false;
		}
		$table = $wpdb->prefix . TINVWL_PREFIX . '_items';
		$item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table` WHERE ID = %d AND wishlist_id = %d FOR UPDATE", $operation['item_id'], $wishlist['ID'] ), ARRAY_A );
		if ( $wpdb->last_error ) { return false; }
		$amount = '0';
		if ( $item ) {
			$item['meta'] = empty( $item['formdata'] ) ? array() : json_decode( $item['formdata'], true );
			if ( ! is_array( $item['meta'] ) || ! self::same_source( $payload, $wishlist, $item ) ) { return false; }
			self::contributions( $wishlist, $item, false );
			$wanted = self::decimal( 'remove' === $payload['strategy'] ? $payload['remove_quantity'] : $payload['quantity'] );
			foreach ( array_unique( $payload['contributions'] ) as $key ) {
				$row = self::get( $key );
				if ( $wpdb->last_error || ! $row || 'contribution' !== $row['state'] || (int) $row['wishlist_id'] !== (int) $wishlist['ID'] || (int) $row['item_id'] !== (int) $item['ID'] ) { return false; }
				$bound = json_decode( $row['payload'], true );
				if ( ! self::same_source( $bound, $wishlist, $item ) ) { return false; }
				$remaining = self::decimal( $bound['remaining'] ?? '' );
				$take = self::decimal_compare( $wanted, $remaining ) < 0 ? $wanted : $remaining;
				if ( '0' !== $take ) {
					$bound['remaining'] = self::decimal_sum( $remaining, $take, true );
					if ( 1 !== $wpdb->update( self::table(), array( 'payload' => wp_json_encode( $bound ), 'updated_at' => time() ), array( 'ID' => $row['ID'], 'state' => 'contribution' ) ) ) { return false; }
					$amount = self::decimal_sum( $amount, $take ); $wanted = self::decimal_sum( $wanted, $take, true );
				}
			}
		}
		$payload['remove_empty'] = $item && 'remove' === $payload['strategy'] && '0' === self::decimal( $payload['remove_quantity'] ) && '0' === self::decimal( $item['quantity'] );
		$payload['remove_quantity'] = $amount; $payload['quantity'] = $amount;
		return $payload;
	}

	/** Reserve a receipt first: a failed reservation must not add anything to the cart. */
	public static function prepare( $wishlist, $source, $strategy = 'decrement' ) {
		global $wpdb;
		for ( $attempt = 0; $attempt < 3; ++$attempt ) {
			$key = self::prepare_attempt( $wishlist, $source, $strategy );
			if ( $key || ! preg_match( '/Record has changed|Deadlock found|Lock wait timeout/i', $wpdb->last_error ) ) { return $key; }
		}
		return false;
	}

	private static function prepare_attempt( $wishlist, $source, $strategy ) {
		global $wpdb;
		if ( ! in_array( $strategy, array( 'remove', 'decrement' ), true ) ) { return false; }
		if ( empty( $source['ID'] ) || empty( $source['wishlist_id'] ) || (int) $source['wishlist_id'] !== (int) ( $wishlist['ID'] ?? 0 ) || ! is_array( $source['meta'] ?? null ) ) { return false; }
		if ( ! self::maybe_install() ) { return false; }
		$previous = $wpdb->suppress_errors( true );
		try { $independent = $wpdb->query( 'SET TRANSACTION READ WRITE' ); }
		finally { $wpdb->suppress_errors( $previous ); }
		if ( false === $independent ) { return false; }
		$items = $wpdb->prefix . TINVWL_PREFIX . '_items';
		$lock = 'tinvwl_item_' . md5( $items . '|' . $source['wishlist_id'] );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) { return false; }
		$transaction = false;
		try {
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return false; }
			$transaction = true;
			// A login transfer cannot pass this mutex while an old-source reservation is being inserted.
			$current = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . $wpdb->prefix . TINVWL_PREFIX . '_lists` WHERE ID = %d', $source['wishlist_id'] ), ARRAY_A );
			if ( $wpdb->last_error || ! $current || (int) $current['author'] !== (int) $wishlist['author'] || ! hash_equals( (string) $current['share_key'], (string) $wishlist['share_key'] ) ) { return false; }
			$saved = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$items` WHERE ID = %d AND wishlist_id = %d FOR UPDATE", $source['ID'], $source['wishlist_id'] ), ARRAY_A );
			if ( $wpdb->last_error || ! $saved || (int) $saved['product_id'] !== (int) $source['product_id'] || (int) $saved['variation_id'] !== (int) $source['variation_id'] ) { return false; }
			if ( isset( $source['expected_quantity'] ) && 0 !== self::decimal_compare( $saved['quantity'], $source['expected_quantity'] ) ) { return false; }
			$saved['meta'] = empty( $saved['formdata'] ) ? array() : json_decode( $saved['formdata'], true );
			if ( ! is_array( $saved['meta'] ) || TInvWL_Product::saved_configuration_identity( $saved['meta'] ) !== TInvWL_Product::saved_configuration_identity( $source['meta'] ) ) { return false; }
			$contributions = self::contributions( $current, $saved );
			$as_owner = ( new TInvWL_Wishlist( TINVWL_PREFIX ) )->is_owner( $current );
			$payload = wp_json_encode( array( 'author' => (int) $current['author'], 'share_key' => $current['share_key'],
				'as_owner' => (bool) $as_owner, 'product_id' => (int) $source['product_id'], 'variation_id' => (int) $source['variation_id'],
				'meta' => $source['meta'], 'quantity' => 0, 'contributions' => $contributions, 'strategy' => $strategy, 'remove_quantity' => 'remove' === $strategy ? (string) $saved['quantity'] : '0' ) );
			if ( false === $payload ) { return false; }
			$key = bin2hex( random_bytes( 32 ) );
			$now = time();
			$result = $wpdb->query( $wpdb->prepare( 'INSERT INTO `' . self::table() . "` (operation_key,wishlist_id,item_id,payload,state,available_at,created_at,updated_at) VALUES (%s,%d,%d,%s,'prepared',%d,%d,%d)", $key, $source['wishlist_id'], $source['ID'], $payload, $now, $now, $now ) );
			if ( 1 !== $result || false === $wpdb->query( 'COMMIT' ) ) { return false; }
			$transaction = false;
			return $key;
		} catch ( Throwable $error ) {
			return false;
		} finally {
			$error = $wpdb->last_error;
			if ( $transaction ) { $wpdb->query( 'ROLLBACK' ); }
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
			$wpdb->last_error = $error;
		}
	}

	/** The verified guest transfer holds both ownership locks and its transaction. */
	public static function transfer_guest_item( $source, $target, $item, $target_item_id ) {
		if ( (int) $source['author'] !== 0 || empty( $target['author'] ) || empty( $target_item_id ) || (int) $item['wishlist_id'] !== (int) $source['ID'] ) { return false; }
		return self::transfer_moved_item( $source, $target, $item, $target_item_id ) && self::record_order_source( $source, $target, $item );
	}

	/** The caller owns both parent/item locks and the transaction that moves this exact configuration. */
	public static function transfer_moved_item( $source, $target, $item, $target_item_id ) {
		global $wpdb;
		if ( empty( $target_item_id ) || (int) $item['wishlist_id'] !== (int) $source['ID'] ) { return false; }
		// Shrinking the source before a move must not turn its missing quantity into authority over the target.
		try { self::contributions( $source, $item, false ); } catch ( Throwable $error ) { return false; }
		$table = self::table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$table` WHERE wishlist_id = %d AND item_id = %d AND state IN ('prepared','pending','contribution') ORDER BY ID FOR UPDATE", $source['ID'], $item['ID'] ), ARRAY_A );
		if ( $wpdb->last_error || ! is_array( $rows ) ) { return false; }
		foreach ( $rows as $row ) {
			$payload = json_decode( $row['payload'], true );
			if ( ! is_array( $payload ) || ! isset( $payload['author'], $payload['share_key'], $payload['product_id'], $payload['variation_id'] )
				|| (int) $source['author'] !== (int) $payload['author'] || ! hash_equals( (string) $source['share_key'], (string) $payload['share_key'] )
				|| (int) $payload['product_id'] !== (int) $item['product_id'] || (int) $payload['variation_id'] !== (int) $item['variation_id']
				|| TInvWL_Product::saved_configuration_identity( $payload['meta'] ?? null ) !== TInvWL_Product::saved_configuration_identity( $item['meta'] ) ) { continue; }
			$payload['author'] = (int) $target['author'];
			$payload['share_key'] = $target['share_key'];
			$encoded = wp_json_encode( $payload );
			if ( false === $encoded || 1 !== $wpdb->update( $table, array( 'wishlist_id' => $target['ID'], 'item_id' => $target_item_id, 'payload' => $encoded, 'available_at' => time(), 'updated_at' => time() ), array( 'ID' => $row['ID'], 'state' => $row['state'] ) ) ) { return false; }
		}
		return true;
	}

	/** Persist the exact source configuration in the same transaction as its verified guest merge. */
	private static function record_order_source( $source, $target, $item ) {
		$key = self::order_source_key( $source['share_key'], $item['product_id'], $item['variation_id'], $item['meta'] );
		if ( false === $key ) { return false; }
		global $wpdb;
		$payload = wp_json_encode( array( 'source_id' => (int) $source['ID'], 'source_key' => $source['share_key'],
			'author' => (int) $target['author'], 'target_id' => (int) $target['ID'], 'target_key' => $target['share_key'] ) );
		if ( false === $payload ) { return false; }
		$existing = self::get( $key );
		if ( $wpdb->last_error ) { return false; }
		if ( $existing ) { return 'transferred' === $existing['state'] && $payload === $existing['payload']; }
		return 1 === $wpdb->insert( self::table(), array( 'operation_key' => $key, 'wishlist_id' => $source['ID'],
			'item_id' => $item['ID'], 'payload' => $payload, 'state' => 'transferred', 'created_at' => time(), 'updated_at' => time() ) );
	}

	private static function order_source_key( $share_key, $product_id, $variation_id, $meta ) {
		$configuration = TInvWL_Product::saved_configuration_identity( $meta );
		if ( false === $configuration || ! is_string( $share_key ) || '' === $share_key || ! $product_id ) { return false; }
		return hash( 'sha256', 'guest-order-source|' . $share_key . '|' . (int) $product_id . '|' . (int) $variation_id . '|' . $configuration );
	}

	/** Resolve a transferred configuration only for its account or a checkout-verified guest source. */
	public static function transferred_order_source( $share_key, $user_id, $guest_id, $product_id, $variation_id, $meta ) {
		$key = self::order_source_key( $share_key, $product_id, $variation_id, $meta );
		if ( false === $key ) { return null; }
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . self::table() . '` WHERE operation_key = %s FOR UPDATE', $key ), ARRAY_A );
		if ( $wpdb->last_error ) { throw new RuntimeException( 'Could not read a transferred order source.' ); }
		if ( ! $row || 'transferred' !== $row['state'] ) { return null; }
		$proof = json_decode( $row['payload'], true );
		if ( ! is_array( $proof ) || empty( $proof['source_id'] ) || empty( $proof['author'] ) || empty( $proof['target_id'] )
			|| ! isset( $proof['source_key'], $proof['target_key'] ) || ! hash_equals( (string) $proof['source_key'], (string) $share_key )
			|| (int) $row['wishlist_id'] !== (int) $proof['source_id']
			|| ! ( ( $user_id > 0 && (int) $user_id === (int) $proof['author'] ) || ( 0 === (int) $user_id && is_int( $guest_id ) && $guest_id === (int) $proof['source_id'] ) ) ) { return null; }
		$lists = $wpdb->prefix . TINVWL_PREFIX . '_lists';
		$source = $wpdb->get_row( $wpdb->prepare( "SELECT ID,author,share_key FROM `$lists` WHERE ID = %d FOR UPDATE", $proof['source_id'] ), ARRAY_A );
		if ( $wpdb->last_error ) { throw new RuntimeException( 'Could not verify a transferred order source.' ); }
		$source_owner = (int) $proof['source_id'] === (int) $proof['target_id'] ? (int) $proof['author'] : 0;
		if ( $source && ( (int) $source['author'] !== $source_owner || ! hash_equals( (string) $source['share_key'], (string) $share_key ) ) ) { return null; }
		$target = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$lists` WHERE ID = %d FOR UPDATE", $proof['target_id'] ), ARRAY_A );
		if ( $wpdb->last_error ) { throw new RuntimeException( 'Could not verify the transferred order destination.' ); }
		return $target && (int) $target['author'] === (int) $proof['author'] && hash_equals( (string) $target['share_key'], (string) $proof['target_key'] ) ? $target : null;
	}

	/** The caller locks ownership, receipt and source row in the same transaction. */
	public static function remove_saved_contribution( $wishlist, $payload, $item_id ) {
		global $wpdb;
		$outcome = array( 'complete' => false, 'applied' => 0.0, 'actions' => array() );
		$amount = (string) ( $payload['remove_quantity'] ?? '' );
		// A validated numeric SQL literal retains bigint/decimal precision when preserving later additions.
		if ( ! preg_match( '/\A(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?\z/D', $amount ) || ! is_finite( (float) $amount ) ) { return $outcome; }
		$table = $wpdb->prefix . TINVWL_PREFIX . '_items';
		$saved = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table` WHERE ID = %d AND wishlist_id = %d FOR UPDATE", $item_id, $wishlist['ID'] ), ARRAY_A );
		if ( $wpdb->last_error ) { return $outcome; }
		if ( ! $saved ) { $outcome['complete'] = true; return $outcome; }
		$meta = empty( $saved['formdata'] ) ? array() : json_decode( $saved['formdata'], true );
		if ( ! is_array( $meta ) ) { return $outcome; }
		if ( (int) $saved['product_id'] !== (int) $payload['product_id'] || (int) $saved['variation_id'] !== (int) $payload['variation_id'] || TInvWL_Product::saved_configuration_identity( $meta ) !== TInvWL_Product::saved_configuration_identity( $payload['meta'] ) ) { $outcome['complete'] = true; return $outcome; }
		$model = TInvWL_Product::for_storage( $wishlist ); $model->defer_product_actions();
		$has_later_additions = $wpdb->get_var( $wpdb->prepare( "SELECT quantity > $amount FROM `$table` WHERE ID = %d AND wishlist_id = %d", $item_id, $wishlist['ID'] ) );
		if ( $wpdb->last_error || null === $has_later_additions ) { return $outcome; }
		if ( ! $has_later_additions ) {
			if ( ! $model->remove_item_from_wl( $saved ) ) { return $outcome; }
			$outcome['actions'] = $model->release_product_actions();
			$outcome['applied'] = (float) $saved['quantity'];
		} elseif ( (float) $amount > 0 ) {
			if ( 1 !== $wpdb->query( $wpdb->prepare( "UPDATE `$table` SET quantity = quantity - $amount WHERE ID = %d AND wishlist_id = %d AND quantity > $amount", $item_id, $wishlist['ID'] ) ) ) { return $outcome; }
			$remaining = $wpdb->get_var( $wpdb->prepare( "SELECT quantity FROM `$table` WHERE ID = %d AND wishlist_id = %d", $item_id, $wishlist['ID'] ) );
			if ( $wpdb->last_error || null === $remaining ) { return $outcome; }
			$saved['quantity'] = $remaining;
			$outcome['actions'] = array( array( 'tinvwl_product_updated', $saved ), array( 'tinvwl_changed_wishlist', 8, $wishlist, (int) $saved['product_id'], (int) $saved['variation_id'], (float) $remaining ) );
			$outcome['applied'] = (float) $amount;
		}
		$outcome['complete'] = true;
		return $outcome;
	}

	public static function get( $key ) {
		global $wpdb;
		if ( ! is_string( $key ) || ! preg_match( '/^[a-f0-9]{64}$/D', $key ) ) { return false; }
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . self::table() . '` WHERE operation_key = %s', $key ), ARRAY_A );
		return $wpdb->last_error ? false : $row;
	}

	/** Retain the native cart success if confirmation storage needs to recover later. */
	public static function confirm( $key, $quantity ) {
		global $wpdb;
		if ( ! is_numeric( $quantity ) || ! is_finite( (float) $quantity ) || (float) $quantity <= 0 ) { return false; }
		$row = self::get( $key );
		if ( ! $row ) { return false; }
		$payload = json_decode( $row['payload'], true );
		if ( ! is_array( $payload ) || ! isset( $payload['quantity'] ) ) { return false; }
		if ( 'prepared' !== $row['state'] ) { return in_array( $row['state'], array( 'pending', 'applied', 'discarded' ), true ) && (float) $payload['quantity'] === (float) $quantity; }
		$payload['quantity'] = (float) $quantity;
		$encoded = wp_json_encode( $payload );
		if ( false === $encoded ) { return false; }
		// Do not overwrite a guest-transfer rebind that committed after the read above.
		$result = $wpdb->query( $wpdb->prepare( 'UPDATE `' . self::table() . "` SET payload = %s, state = 'pending', available_at = %d, updated_at = %d WHERE operation_key = %s AND state = 'prepared' AND BINARY payload = BINARY %s", $encoded, time(), time(), $key, $row['payload'] ) );
		if ( 1 !== $result ) { return false; }
		self::schedule( time() + 1 );
		return true;
	}

	public static function cancel( $key ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE `' . self::table() . "` SET state = 'discarded', updated_at = %d WHERE operation_key = %s AND state = 'prepared'", time(), $key ) );
	}

	public static function remember_success( $key, $quantity ) {
		if ( ! WC()->session ) { return; }
		$proofs = (array) WC()->session->get( 'tinvwl_pending_cart_debits', array() );
		$proofs[ $key ] = $quantity;
		WC()->session->set( 'tinvwl_pending_cart_debits', $proofs );
	}

	public static function forget_success( $key ) {
		if ( ! WC()->session ) { return; }
		$proofs = (array) WC()->session->get( 'tinvwl_pending_cart_debits', array() );
		unset( $proofs[ $key ] );
		WC()->session->set( 'tinvwl_pending_cart_debits', $proofs );
	}

	public static function resume_session() {
		if ( ! WC()->session ) { return; }
		foreach ( (array) WC()->session->get( 'tinvwl_pending_cart_debits', array() ) as $key => $quantity ) {
			if ( self::confirm( $key, $quantity ) && self::process( $key ) ) { self::forget_success( $key ); }
		}
	}

	/** The receipt and the item delta commit in the same transaction in Cart. */
	public static function process( $key ) {
		global $wpdb;
		try { $result = TInvWL_Public_Cart::apply_cart_debit( $key ); }
		catch ( Throwable $error ) { self::log( 'A wishlist debit remains pending after an unsuccessful attempt.' ); $result = false; }
		if ( ! $result ) {
			$row = self::get( $key );
			if ( $row && in_array( $row['state'], array( 'applied', 'discarded' ), true ) ) { return true; }
			if ( $row && 'pending' === $row['state'] ) {
				$delay = min( 3600, 30 * ( 2 ** min( 7, (int) $row['attempts'] ) ) );
				$wpdb->query( $wpdb->prepare( 'UPDATE `' . self::table() . "` SET attempts = attempts + 1, available_at = %d, updated_at = %d WHERE operation_key = %s AND state = 'pending'", time() + $delay, time(), $key ) );
				self::schedule( time() + $delay );
			}
		}
		return $result;
	}

	public static function run() {
		global $wpdb;
		if ( ! self::maybe_install() ) { return false; }
		$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT operation_key FROM `' . self::table() . "` WHERE state = 'pending' AND available_at <= %d ORDER BY ID ASC LIMIT 25", time() ) );
		if ( $wpdb->last_error ) { return false; }
		foreach ( $rows as $key ) { self::process( $key ); }
		$next = $wpdb->get_var( 'SELECT MIN(available_at) FROM `' . self::table() . "` WHERE state = 'pending'" );
		if ( $next ) { self::schedule( max( time() + 1, (int) $next ) ); }
		return true;
	}

	public static function uninstall() {
		global $wpdb;
		$table = self::table();
		$wpdb->query( "DROP TABLE IF EXISTS `$table`" );
		wp_unschedule_hook( 'tinvwl_cart_debit_retry' );
		wp_unschedule_hook( 'tinvwl_cart_debits' );
		unset( self::$installed[ $table ] );
	}
}
