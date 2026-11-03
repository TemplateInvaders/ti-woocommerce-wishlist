<?php
/**
 * Activation/Deactivation plugin class
 *
 * @since             1.0.0
 * @package           TInvWishlist
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
	die;
}

/**
 * Activation/Deactivation plugin class
 */
class TInvWL_Activator
{

	/**
	 * Plugin name
	 *
	 * @var string Using defined constant.
	 */
	static $_name = TINVWL_PREFIX;

	/**
	 * Prefix database
	 *
	 * @see wpdb
	 * @var string
	 */
	static $wpdb_prefix;

	/**
	 * Database version
	 *
	 * @var string
	 */
	static $_version = TINVWL_FVERSION;

	/**
	 * Current installed database version
	 *
	 * @var string
	 */
	static $_prev;

	/**
	 * Regular expression for sorting database version function
	 *
	 * @var string
	 */
	const REGEXP = '/^database_/i';

	/** State writes and DDL share one database-scoped advisory lock. */
	private static $schema_lock = null;
	private static $schema_failure = null;
	private static $schema_ddl_failure = null;
	private static $schema_last_captured_error = '';
	private static $schema_context = array();
	private static $schema_deferred = false;
	private static $schema_recording = false;
	private static $schema_created = false;

	private static function schema_database_option() {
		return self::$_name . '_db_ver';
	}

	/** GET_LOCK names are server-wide; editions sharing tables must share this name. */
	public static function schema_lock_name() {
		global $wpdb;
		$database = $wpdb->get_var( 'SELECT DATABASE()' );
		if ( $wpdb->last_error || ! is_string( $database ) || '' === $database ) {
			self::schema_failure( array( 'stage' => 'acquire_lock', 'attribute' => 'database', 'expected' => 'selected database', 'actual' => $database ) );
			return false;
		}
		return 'tinvwl_schema_' . md5( serialize( array( $database, $wpdb->prefix, self::$_name ) ) );
	}

	/** Read the shared checkpoint after acquiring the lock, bypassing request caches. */
	private static function schema_state() {
		global $wpdb;
		$keys = array( self::schema_database_option(), self::schema_marker(), self::schema_marker() . '_error', self::$_name . '_db_ver' );
		$keys = array_values( array_unique( $keys ) );
		$placeholders = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );
		self::schema_stage( 'read_checkpoint', $wpdb->options );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM `$wpdb->options` WHERE option_name IN ($placeholders)", $keys ), ARRAY_A );
		if ( $wpdb->last_error || ! is_array( $rows ) ) {
			self::schema_failure( array( 'stage' => 'read_checkpoint' ) );
			return false;
		}
		$state = array_fill_keys( $keys, false );
		foreach ( $rows as $row ) { $state[ $row['option_name'] ] = maybe_unserialize( $row['option_value'] ); }
		return $state;
	}

	private static function schema_state_ready( $state ) {
		$version = (string) ( $state[ self::schema_database_option() ] ?? '' );
		$revision = (string) ( $state[ self::schema_marker() ] ?? '' );
		return $version && version_compare( $version, self::$_version, '>=' )
			&& '2:' === substr( $revision, 0, 2 ) && version_compare( substr( $revision, 2 ), self::$_version, '>=' )
			&& empty( $state[ self::schema_marker() . '_error' ] );
	}

	private static function schema_stage( $stage, $table = '', $details = array() ) {
		self::capture_schema_query_error( '' );
		self::$schema_context = array_merge( array( 'stage' => $stage, 'table' => $table ), $details );
	}

	/** dbDelta issues multiple queries: capture an error before its next query clears it. */
	public static function capture_schema_query_error( $query ) {
		global $wpdb;
		if ( ! self::$schema_recording && self::$schema_context && $wpdb->last_error ) {
			$identity = md5( $wpdb->num_queries . '|' . $wpdb->last_query . '|' . $wpdb->last_error );
			if ( self::$schema_last_captured_error === $identity ) { return $query; }
			self::$schema_last_captured_error = $identity;
			$details = self::$schema_context;
			if ( preg_match( '/^ALTER TABLE\s+`?([a-z0-9_]+)`?/i', $wpdb->last_query, $table ) ) {
				$details['table'] = $table[1];
				if ( preg_match( '/\b(?:ADD|DROP)\s+(?:UNIQUE\s+)?(?:KEY|INDEX)\s+`?([a-z0-9_]+)/i', $wpdb->last_query, $index ) ) { $details['stage'] = 'alter_index'; $details['index'] = $index[1]; }
				elseif ( preg_match( '/\b(?:MODIFY|CHANGE|ADD)\s+(?:COLUMN\s+)?`([a-z0-9_]+)`/i', $wpdb->last_query, $column ) ) { $details['stage'] = 'alter_column'; $details['column'] = $column[1]; }
			}
			self::schema_failure( $details );
			if ( preg_match( '/^(?:CREATE|ALTER) TABLE\b/i', $wpdb->last_query )
				&& ! ( 1061 === self::schema_sql_errno() && isset( $details['index'] ) && in_array( $details['index'], self::$schema_context['repairable_indexes'] ?? array(), true ) )
				&& ! self::$schema_ddl_failure ) { self::$schema_ddl_failure = self::$schema_failure; }
		}
		return $query;
	}

	private static function schema_sql_errno() {
		global $wpdb;
		try {
			return class_exists( 'mysqli', false ) && $wpdb->dbh instanceof mysqli ? mysqli_errno( $wpdb->dbh ) : 0;
		} catch ( Throwable $ignored ) {
			return 0; // A disconnected mysqli handle must not hide the original failure.
		}
	}

	/** Store structure only: never a SQL statement, row data, cookies or share keys. */
	private static function schema_failure( $details = array(), $exception = null ) {
		global $wpdb;
		$details = array_merge( self::$schema_context, $details );
		$message = $exception ? $exception->getMessage() : (string) $wpdb->last_error;
		// Engine errors can include duplicate values or connection usernames in quotes.
		$message = preg_replace( '/\x27[^\x27]*\x27|"[^"]*"|[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[redacted]', $message );
		$record = array( 'schema_version' => (string) self::$_version, 'attempted_at' => gmdate( 'c' ), 'sql_errno' => self::schema_sql_errno(), 'message' => substr( strip_tags( $message ), 0, 400 ) );
		foreach ( array( 'stage', 'table', 'column', 'index', 'attribute', 'expected', 'actual' ) as $key ) {
			if ( array_key_exists( $key, $details ) ) {
				$value = $details[ $key ];
				if ( is_array( $value ) ) { $value = wp_json_encode( array_slice( $value, 0, 8 ) ); }
				$record[ $key ] = null === $value || is_bool( $value ) ? $value : substr( strip_tags( (string) $value ), 0, 180 );
			}
		}
		if ( $exception ) { $record['exception'] = get_class( $exception ); }
		self::$schema_failure = $record;
		return false;
	}

	/** Bounded, throttled operational logging; logging must not change migration outcome. */
	private static function log_schema_failure( $record ) {
		$identity = $record;
		unset( $identity['attempted_at'] );
		$fingerprint = md5( wp_json_encode( $identity ) );
		$key = self::schema_marker() . '_last_log';
		self::$schema_recording = true;
		try {
			$previous = get_option( $key, array() );
			if ( is_array( $previous ) && ( $previous['fingerprint'] ?? '' ) === $fingerprint && (int) ( $previous['time'] ?? 0 ) > time() - 300 ) { return; }
			update_option( $key, array( 'fingerprint' => $fingerprint, 'time' => time() ), false );
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error( wp_json_encode( $record ), array( 'source' => 'tinvwl-schema' ) );
			} else { error_log( 'TI Wishlist schema: ' . wp_json_encode( $record ) ); }
		} catch ( Throwable $ignored ) {
			// The original schema diagnostic is already captured.
		} finally { self::$schema_recording = false; }
	}

	private static function owns_schema_lock() {
		global $wpdb;
		if ( self::$schema_lock && '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT IS_USED_LOCK(%s) = CONNECTION_ID()', self::$schema_lock ) ) ) { return true; }
		return self::schema_failure( array( 'stage' => 'lock_ownership', 'expected' => 'current connection owns lock', 'actual' => 'lock lost' ) );
	}

	/** true = completed, null = deferred, false = failed. Never return truthy status objects. */
	private static function with_schema_lock( $callback ) {
		global $wpdb;
		if ( self::$schema_lock ) { return $callback(); }
		self::$schema_failure = null;
		self::$schema_ddl_failure = null;
		self::$schema_last_captured_error = '';
		self::$schema_context = array();
		self::$schema_deferred = false;
		$lock = false;
		$locked = null;
		try {
			$lock = self::schema_lock_name();
			$locked = false === $lock ? null : $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) );
		} catch ( Throwable $error ) {
			self::schema_failure( array( 'stage' => 'acquire_lock' ), $error );
		}
		if ( ! $wpdb->last_error && '0' === (string) $locked ) { return null; }
		if ( false === $lock || $wpdb->last_error || '1' !== (string) $locked ) {
			if ( ! self::$schema_failure ) { self::schema_failure( array( 'stage' => 'acquire_lock', 'expected' => 1, 'actual' => $locked ) ); }
			$failure = self::$schema_failure;
			// Lock diagnostics are separate from protected migration status. A request
			// that never owned the lock cannot write the permanent migration error.
			update_option( self::schema_marker() . '_lock_error', $failure, false );
			self::log_schema_failure( $failure );
			return false;
		}
		self::$schema_lock = $lock;
		$prefix = self::$wpdb_prefix;
		self::$wpdb_prefix = $wpdb->prefix;
		$previous = $wpdb->suppress_errors( true );
		add_filter( 'query', array( __CLASS__, 'capture_schema_query_error' ), PHP_INT_MAX );
		try {
			$result = $callback();
			self::capture_schema_query_error( '' );
			if ( self::$schema_deferred ) { return null; }
			if ( ! in_array( $result, array( true, false, null ), true ) ) { self::schema_failure( array( 'attribute' => 'return_contract', 'expected' => 'true/false/null', 'actual' => gettype( $result ) ) ); return self::schema_error(); }
			return false === $result ? self::schema_error() : $result;
		} catch ( Throwable $error ) {
			self::schema_failure( array(), $error );
			return self::schema_error();
		} finally {
			remove_filter( 'query', array( __CLASS__, 'capture_schema_query_error' ), PHP_INT_MAX );
			$error = $wpdb->last_error;
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
			$wpdb->last_error = $error;
			$wpdb->suppress_errors( $previous );
			self::$schema_lock = null;
			self::$wpdb_prefix = $prefix;
		}
	}

	/** Verify required service writes in SQL, rather than trusting option-cache values. */
	private static function schema_checkpoint( $key, $value ) {
		global $wpdb;
		if ( ! self::owns_schema_lock() ) { return false; }
		self::schema_stage( 'write_checkpoint', $wpdb->options, array( 'attribute' => $key, 'expected' => null === $value ? 'absent' : 'stored checkpoint' ) );
		$stored = maybe_serialize( $value );
		// Guard the write itself: wpdb may reconnect after a lost connection, which
		// releases its advisory lock. A stale connection must then affect no row.
		if ( null === $value ) {
			$sql = $wpdb->prepare( "DELETE FROM `$wpdb->options` WHERE option_name = %s AND IS_USED_LOCK(%s) = CONNECTION_ID()", $key, self::$schema_lock );
		} else {
			$sql = $wpdb->prepare( "INSERT INTO `$wpdb->options` (option_name, option_value, autoload) SELECT %s, %s, %s FROM DUAL WHERE IS_USED_LOCK(%s) = CONNECTION_ID() ON DUPLICATE KEY UPDATE option_value = IF(IS_USED_LOCK(%s) = CONNECTION_ID(), VALUES(option_value), option_value)", $key, $stored, self::schema_database_option() === $key ? 'yes' : 'no', self::$schema_lock, self::$schema_lock );
		}
		$result = $wpdb->query( $sql );
		if ( false === $result ) { return self::schema_failure(); }
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		$actual = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM `$wpdb->options` WHERE option_name = %s", $key ) );
		if ( $wpdb->last_error || ( null === $value ? null !== $actual : (string) $stored !== (string) $actual ) ) {
			return self::schema_failure( array( 'actual' => null === $actual ? 'absent' : 'checkpoint mismatch' ) );
		}
		return self::owns_schema_lock();
	}


	/**
	 * Method activation plugin.
	 */
	public static function activate() {
		// Existing installations never fall through to fresh setup after a deferred/failed upgrade.
		if ( get_option( self::schema_database_option() ) ) { return self::update(); }
		self::$schema_created = false;
		$result = self::database();
		if ( true !== $result ) { return $result; }
		if ( self::$schema_created ) {
			self::load_data();
			TInvWL_Public_TInvWL::update_rewrite_rules();
			TInvWL_WizardSetup::setup();
		}
		return true;
	}

	/**
	 * Method deactivation plugin.
	 */
	public static function uninstall()
	{
		TInvWL_Public_CartDebit::uninstall();
		self::database('destroy');
		self::unload_data();
		self::remove_capabities();
	}

	/**
	 * Method update plugin.
	 */
	public static function update() {
		$current_version = get_option( self::schema_database_option() );

		if ( ! $current_version ) { return self::activate(); }
		if ( version_compare( self::$_version, $current_version, 'gt' ) ) {
			$result = self::database( 'upgrade', $current_version );
			if ( true === $result ) {
				self::upgrade_data();
				TInvWL_Public_TInvWL::update_rewrite_rules();
			}
			return $result;
		}
		$ready = self::schema_ready();
		$result = self::maybe_repair_schema();
		if ( true === $result && ! $ready ) { self::upgrade_data(); }
		return $result;
	}

	/**
	 * Require function database and apply method
	 *
	 * @param string $action create|destroy.
	 * @param string $prev Current installed version.
	 */
	private static function database( $action = 'create', $prev = '0' ) {
		if ( 'destroy' === $action ) { return self::destroy( self::current_tables() ); }
		return self::with_schema_lock( static function () use ( $action, $prev ) {
			$state = self::schema_state();
			if ( false === $state ) { return false; }
			if ( self::schema_state_ready( $state ) ) { return true; }
			$current = $state[ self::schema_database_option() ];
			$revision = (string) $state[ self::schema_marker() ];
			if ( ( $current && version_compare( $current, self::$_version, '>' ) )
				|| ( '2:' === substr( $revision, 0, 2 ) && version_compare( substr( $revision, 2 ), self::$_version, '>' ) ) ) { return null; }
			if ( $current && version_compare( $current, self::$_version, '>=' ) ) {
				return self::reconcile_schema( self::current_tables() ) && self::schema_repaired();
			}
			if ( $current ) { $action = 'upgrade'; $prev = $current; }
			elseif ( 'create' !== $action ) { return null; }
			self::$_prev = 'create' === $action ? '0' : $prev;
			$methods = array_filter( get_class_methods( __CLASS__ ), array( __CLASS__, 'filter_database' ) );
			uasort( $methods, array( __CLASS__, 'sort_database' ) );
			$tables = array();
			foreach ( $methods as $method ) { $tables = self::merge_database( $tables, $method ); }
			if ( isset( $tables['items']['field']['quantity'] ) ) { $tables['items'] = self::compatible_items_table( 'items', $tables['items'] ); }
			$result = self::$action( $tables );
			self::$schema_created = true === $result && 'create' === $action;
			return $result;
		} );
	}

	/**
	 * Merge table with upgrade attributes
	 *
	 * @param array $tables1 Collecting tables.
	 * @param string $method Method for get new tables.
	 *
	 * @return array
	 */
	public static function merge_database($tables1, $method)
	{
		$tables2 = self::$method();
		foreach ((array)$tables2 as $name => $table) {
			if (empty($table)) {
				continue;
			}
			if (array_key_exists('upgrade', $table)) {
				$_upgrade = $table['upgrade'];
				$table['upgrade'] = array();
				$table['upgrade'][self::pre_database($method)] = $_upgrade;
			}
			if (array_key_exists($name, $tables1)) {
				if (!array_key_exists('upgrade', $tables1[$name])) {
					$tables1[$name]['upgrade'] = array();
				}
				if (!array_key_exists('upgrade', $table)) {
					$table['upgrade'] = array();
				}
				$table['upgrade'] = tinv_array_merge($tables1[$name]['upgrade'], $table['upgrade']);
			}
			if (empty($table['field'])) {
				$tables1[$name]['upgrade'] = $table['upgrade'];
			} else {
				$tables1[$name] = $table;
			}
		}

		return $tables1;
	}

	/**
	 * Filter methods for creating database
	 *
	 * @param string $method Method name from this class.
	 *
	 * @return boolean
	 */
	public static function filter_database($method)
	{
		if (!preg_match(self::REGEXP, $method)) {
			return false;
		}
		if (version_compare(self::$_prev, self::pre_database($method), 'ge')) {
			return false;
		}

		return version_compare(self::$_version, self::pre_database($method), 'ge');
	}

	/**
	 * Sort methods for creating database
	 *
	 * @param string $method1 Method name first from this class.
	 * @param string $method2 Method name second from this class.
	 *
	 * @return bool
	 */
	public static function sort_database($method1, $method2)
	{
		return version_compare(self::pre_database($method1), self::pre_database($method2));
	}

	/**
	 * Convert method name to version
	 *
	 * @param string $method Method name from this class.
	 *
	 * @return string
	 */
	public static function pre_database($method)
	{
		$method = preg_replace(self::REGEXP, '', $method);
		$method = str_replace('_', '.', $method);

		return $method;
	}

	/**
	 * Creation SQL request for creation table
	 *
	 * @param string $name Name Table.
	 * @param array $table Structured array table.
	 *            array    'field'        Array fields.
	 *            string    'charset'    Charset table.
	 *            string    'key'        Primary key.
	 *            string    'index'       Custom index.
	 *            string    'post'        Custom elements in format SQL.
	 *
	 * @return string
	 */
	public static function table($name, $table)
	{
		$table = self::compatible_items_table( $name, $table );
		$name = self::$wpdb_prefix . self::$_name . '_' . $name;
		$fields = $table['field'];
		$index = (isset($table['index'])) ? $table['index'] : null;
		$table = filter_var_array($table, array(
			'charset' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
			'key' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
			'post' => FILTER_DEFAULT,
		));

		global $wpdb;
		$table['charset'] = empty( $table['charset'] ) ? $wpdb->get_charset_collate() : sprintf( 'DEFAULT CHARSET=%s', $table['charset'] );

		$keys = array_keys($fields);
		if (!in_array($table['key'], $keys)) { // @codingStandardsIgnoreLine WordPress.PHP.StrictInArray.MissingTrueStrict
			$table['key'] = null;
		}
		$t = self::column_database();
		foreach ($fields as $key => $flags) {
			if (is_string($flags)) {
				if (array_key_exists($flags, $t)) {
					$flags = $t[$flags];
				} else {
					$flags = $t['text'];
				}
			}
			$fields[$key] = $flags;
		}
		if (empty($table['key'])) {
			foreach ($fields as $key => $flags) {
				if (array_key_exists(4, $flags)) {
					if ($flags[4]) {
						$table['key'] = $key;
						break;
					}
				}
			}
			if (empty($table['key'])) {
				foreach ($keys as $key) {
					if (preg_match('/id$/i', $key)) {
						$table['key'] = $key;
						break;
					}
				}
			}
		}
		unset($keys);
		if (empty($table['key'])) {
			$table['key'] = '';
		} else {
			$table['key'] = sprintf( ",\nPRIMARY KEY  (`%s`)", $table['key'] );
		}

		if (empty($table['post'])) {
			$table['post'] = '';
		} else {
			$table['post'] = ",\n" . $table['post'];
		}
		$indexes = '';
		if ($index) {
			foreach ($index as $index_name => $columns) {
				$indexes .= sprintf( ",\nKEY `%s` (%s)", $index_name, $columns );
			}
		}

		foreach ($fields as $key => $format) {
			$fields[$key] = self::field($key, $format);
		}
		$fields = implode( ",\n", $fields );

		$sql = sprintf( "CREATE TABLE `%s` (\n%s%s%s%s\n) ENGINE=InnoDB %s;", $name, $fields, $table['key'], $indexes, $table['post'], $table['charset'] );

		return $sql;
	}

	/**
	 * Created SQL field
	 *
	 * @param string $name Name field.
	 * @param array $newformat Structure field.
	 *            string            0    Data Types column.
	 *            integer|string    1    Length or Size column.
	 *            boolean            2    is NULL?
	 *            integer|string    3    Specifies a default value for a column.
	 *            boolean            4    is AUTO_INCREMENT?.
	 *
	 * @return string
	 */
	public static function field($name, $newformat)
	{
		$format = array('TEXT', null, false, null, false);

		foreach (array_keys($format) as $key) {
			if (array_key_exists($key, $newformat)) {
				$format[$key] = $newformat[$key];
			}
		}

		$format[0] = strtolower( $format[0] );

		if (!is_null($format[1])) {
			$format[1] = sprintf('(%s)', $format[1]);
		}

		$format[2] = (filter_var($format[2], FILTER_VALIDATE_BOOLEAN) ? '' : 'NOT ') . 'NULL';

		if (!is_null($format[3])) {
			if (!in_array($format[3], array('CURRENT_TIMESTAMP'))) { // @codingStandardsIgnoreLine WordPress.PHP.StrictInArray.MissingTrueStrict
				$format[3] = "'" . str_replace( "'", "''", (string) $format[3] ) . "'";
			}
			$format[3] = sprintf('DEFAULT %s', $format[3]);
		}

		$format[4] = (filter_var($format[4], FILTER_VALIDATE_BOOLEAN) ? 'AUTO_INCREMENT' : null);

		array_unshift($format, '`' . $name . '`');
		$format = array_filter($format);

		return implode(' ', $format);
	}

	/**
	 * Created tables from array
	 *
	 * @param array $tables Array tables.
	 *
	 * @return boolean
	 * @global wpdb $wpdb
	 *
	 */
	public static function create( $tables, $checkpoint = true ) {
		if ( ! self::$schema_lock ) { return self::with_schema_lock( static function () use ( $tables, $checkpoint ) { return self::create( $tables, $checkpoint ); } ); }
		global $wpdb;
		self::$wpdb_prefix = $wpdb->prefix;
		if ( ! self::can_alter_schema() ) { return false; }
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		foreach ( $tables as $name => $table ) {
			if ( ! empty( $table['field'] ) ) {
				$previous = $wpdb->suppress_errors( true );
				self::$schema_ddl_failure = null;
				self::schema_stage( 'create_table', self::$wpdb_prefix . self::$_name . '_' . $name, array( 'repairable_indexes' => array_keys( $table['index'] ?? array() ) ) );
				try { dbDelta( self::table( $name, $table ) ); self::capture_schema_query_error( '' ); if ( self::$schema_ddl_failure ) { return false; } }
				finally { $wpdb->suppress_errors( $previous ); }
			}
		}
		if ( ! $checkpoint ) {
			return true; // Existing upgrade actions may still need to add or modify fields.
		}
		if ( ! self::reconcile_schema( self::current_tables() ) ) {
			return false;
		}
		return self::schema_repaired();
	}

	/**
	 * Upgrade tables from array
	 *
	 * @param array $tables Array tables.
	 *
	 * @return boolean
	 * @global wpdb $wpdb
	 *
	 */
	public static function upgrade( $tables ) {
		if ( ! self::$schema_lock ) { return self::with_schema_lock( static function () use ( $tables ) { return self::upgrade( $tables ); } ); }
		if ( ! self::create( $tables, false ) ) {
			return false;
		}
		foreach ( $tables as $name => $table ) {
			if ( isset( $table['upgrade'] ) && ! self::upgrade_action( $name, $table ) ) {
				return false;
			}
		}
		if ( ! self::reconcile_schema( self::current_tables() ) ) {
			return false;
		}
		return self::schema_repaired();
	}

	/** A repair revision is independent of historical application/database checkpoints. */
	private static function schema_marker() {
		return self::$_name . '_schema_revision_f';
	}

	private static function schema_repaired() {
		if ( ! self::owns_schema_lock() ) { return false; }
		// The DB version is the final checkpoint; a failed write remains retryable.
		return self::schema_checkpoint( self::schema_marker(), '2:' . self::$_version )
			&& self::schema_checkpoint( self::schema_marker() . '_error', null )
			&& self::schema_checkpoint( self::schema_marker() . '_lock_error', null )
			&& self::schema_checkpoint( self::schema_database_option(), self::$_version );
	}

	private static function schema_error() {
		// In particular, callers must never publish an error after releasing the lock.
		$failure = self::$schema_ddl_failure ?: self::$schema_failure;
		if ( ! self::owns_schema_lock() ) {
			self::log_schema_failure( self::$schema_failure );
			return false;
		}
		if ( ! self::$schema_failure ) { self::schema_failure( array( 'stage' => 'final_validation', 'attribute' => 'schema', 'expected' => 'required definitions', 'actual' => 'validation failed' ) ); }
		$failure = $failure ?: self::$schema_failure;
		self::$schema_recording = true;
		try { self::schema_checkpoint( self::schema_marker() . '_error', $failure ); }
		finally { self::$schema_recording = false; }
		self::log_schema_failure( $failure );
		return false;
	}

	/** MySQL DDL must never implicitly commit a caller's transaction. */
	private static function can_alter_schema() {
		global $wpdb;
		$previous = $wpdb->suppress_errors( true );
		try {
			if ( false !== $wpdb->query( 'SET TRANSACTION READ WRITE' ) ) { return true; }
			if ( 1568 === self::schema_sql_errno() ) { self::$schema_deferred = true; }
			return self::schema_failure( array( 'stage' => 'ddl_preflight', 'attribute' => 'caller_transaction', 'expected' => 'no active transaction', 'actual' => 'DDL postponed or unavailable' ) );
		} finally { $wpdb->suppress_errors( $previous ); }
	}

	/** Repair only declared columns, retaining extension columns and existing data. */
	private static function repair_columns( $name, $table ) {
		global $wpdb;
		self::schema_stage( 'alter_column', $name );
		$columns = $wpdb->get_results( "SHOW FULL COLUMNS FROM `$name`", ARRAY_A );
		if ( $wpdb->last_error || ! $columns ) { return false; }
		$columns = array_column( $columns, null, 'Field' );
		$aliases = self::column_database();
		$changes = array();
		foreach ( $table['field'] as $field => $flags ) {
			$flags = is_string( $flags ) ? ( $aliases[ $flags ] ?? $aliases['text'] ) : $flags;
			$flags = array_replace( array( 'TEXT', null, false, null, false ), $flags );
			if ( ! isset( $columns[ $field ] ) ) { return false; }
			$column = $columns[ $field ];
			if ( self::column_matches( $column, $flags ) ) { continue; }
			// Keep an existing text encoding until the separate, verified charset migration runs.
			if ( ! empty( $column['Collation'] ) ) {
				$charset = explode( '_', $column['Collation'] )[0];
				if ( ! preg_match( '/\A[a-z0-9_]+\z/i', $column['Collation'] ) ) { return false; }
				$flags[0] .= ( null === $flags[1] ? '' : '(' . $flags[1] . ')' ) . " CHARACTER SET $charset COLLATE " . $column['Collation'];
				$flags[1] = null;
			}
			$definition = self::field( $field, $flags );
			if ( ! empty( $column['Comment'] ) ) { $definition .= $wpdb->prepare( ' COMMENT %s', $column['Comment'] ); }
			$changes[] = 'MODIFY ' . $definition;
		}
		return ! $changes || ( self::can_alter_schema() && false !== $wpdb->query( "ALTER TABLE `$name` " . implode( ', ', $changes ) ) );
	}

	/** Replace only mismatching required indexes; retain all extension indexes. */
	private static function repair_indexes( $name, $table ) {
		global $wpdb;
		self::schema_stage( 'alter_index', $name );
		$rows = $wpdb->get_results( "SHOW INDEX FROM `$name`", ARRAY_A );
		if ( $wpdb->last_error || ! is_array( $rows ) ) { return false; }
		$existing = $unique = $prefixes = array();
		foreach ( $rows as $row ) {
			$index = $row['Key_name'];
			$existing[ $index ][ (int) $row['Seq_in_index'] ] = $row['Column_name'];
			$unique[ $index ] = (int) $row['Non_unique'];
			if ( null !== $row['Sub_part'] ) { $prefixes[ $index ] = true; }
		}
		$required = $table['index'] ?? array();
		$primary = $table['key'] ?? '';
		$aliases = self::column_database();
		if ( ! $primary ) {
			foreach ( $table['field'] as $field => $flags ) {
				$flags = is_string( $flags ) ? ( $aliases[ $flags ] ?? $aliases['text'] ) : $flags;
				if ( ! empty( $flags[4] ) ) { $primary = $field; break; }
			}
		}
		if ( ! $primary ) {
			foreach ( array_keys( $table['field'] ) as $field ) {
				if ( preg_match( '/id$/i', $field ) ) { $primary = $field; break; }
			}
		}
		if ( $primary ) { $required['PRIMARY'] = $primary; }
		foreach ( $required as $index => $columns ) {
			$expected = array_map( static fn( $field ) => trim( $field, " `\t\n\r" ), explode( ',', $columns ) );
			if ( isset( $existing[ $index ] ) ) {
				ksort( $existing[ $index ] );
				if ( array_values( $existing[ $index ] ) === $expected && ! isset( $prefixes[ $index ] ) && $unique[ $index ] === ( 'PRIMARY' === $index ? 0 : 1 ) ) { continue; }
			}
			$changes = array();
			if ( isset( $existing[ $index ] ) ) { $changes[] = 'PRIMARY' === $index ? 'DROP PRIMARY KEY' : "DROP INDEX `$index`"; }
			$definition = '`' . implode( '`, `', $expected ) . '`';
			$changes[] = 'PRIMARY' === $index ? "ADD PRIMARY KEY ($definition)" : "ADD KEY `$index` ($definition)";
			self::schema_stage( 'alter_index', $name, array( 'index' => $index, 'expected' => $expected ) );
			if ( ! self::can_alter_schema() || false === $wpdb->query( "ALTER TABLE `$name` " . implode( ', ', $changes ) ) ) { return self::schema_failure(); }
		}
		return true;
	}

	/** Reconcile definitions without replaying historical DROP/TRUNCATE/data migrations. */
	private static function reconcile_schema( $tables ) {
		global $wpdb;
		if ( ! self::schema_matches( $tables ) && ! self::create( $tables, false ) ) {
			return false;
		}
		foreach ( $tables as $name => $table ) {
			if ( empty( $table['field'] ) ) { continue; }
			$name = self::$wpdb_prefix . self::$_name . '_' . $name;
			if ( ! self::repair_indexes( $name, $table ) || ! self::repair_columns( $name, $table ) ) { return false; }
			$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $name ), ARRAY_A );
			if ( $wpdb->last_error || ! $status || empty( $status['Engine'] ) ) { return false; }
			self::schema_stage( 'alter_engine', $name, array( 'attribute' => 'engine', 'expected' => 'InnoDB/XtraDB', 'actual' => $status['Engine'] ) );
			$engine = strtolower( $status['Engine'] );
			if ( in_array( $engine, array( 'innodb', 'xtradb' ), true ) ) { continue; }
			// Existing MyISAM data is retained; unsupported engines require administrator attention.
			if ( 'myisam' !== $engine || ! self::can_alter_schema()
				|| false === $wpdb->query( "ALTER TABLE `$name` ENGINE=InnoDB" ) ) { return self::schema_failure(); }
			$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $name ), ARRAY_A );
			if ( $wpdb->last_error || ! $status || ! in_array( strtolower( $status['Engine'] ), array( 'innodb', 'xtradb' ), true ) ) { return self::schema_failure( array( 'actual' => $status['Engine'] ?? null ) ); }
		}
		return self::schema_matches( $tables );
	}

	public static function schema_ready() {
		return self::schema_state_ready( array(
			self::schema_database_option() => get_option( self::schema_database_option() ),
			self::schema_marker() => get_option( self::schema_marker() ),
			self::schema_marker() . '_error' => get_option( self::schema_marker() . '_error' ),
		) );
	}

	/** Verify once per release; failed or interrupted repairs remain eligible for retry. */
	public static function maybe_repair_schema() {
		if ( self::schema_ready() ) { return true; }
		return self::database( 'repair' );
	}

	/** Explain persistent repair failures instead of silently disabling transactional operations. */
	public static function schema_notice() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$failure = get_option( self::schema_marker() . '_error' );
		if ( ! $failure && ! self::schema_ready() ) { $failure = get_option( self::schema_marker() . '_lock_error' ); }
		if ( ! $failure ) { return; }
		echo '<div class="notice notice-error"><p>' . esc_html__( 'TI Wishlist could not finish updating its database tables. The update will be retried automatically. Possible causes include a schema mismatch, a database error, insufficient ALTER/CREATE permissions or unsupported table engines. Some wishlist operations are unavailable until verification succeeds.', TINVWL_DOMAIN ) . '</p>';
		if ( is_array( $failure ) ) {
			echo '<p><code>' . esc_html( substr( wp_json_encode( $failure ), 0, 2400 ) ) . '</code></p>';
		}
		echo '</div>';
	}

	/** Keep existing fractional storage in both dbDelta DDL and required-column repairs. */
	private static function compatible_items_table( $name, $table ) {
		if ( 'items' !== $name || ! isset( $table['field']['quantity'] ) ) { return $table; }
		global $wpdb;
		$physical = $wpdb->prefix . self::$_name . '_items';
		$column = $wpdb->get_row( $wpdb->prepare( 'SELECT COLUMN_TYPE AS type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s', $physical, 'quantity' ), ARRAY_A );
		if ( $wpdb->last_error ) { throw new RuntimeException( 'Could not inspect saved quantity storage.' ); }
		if ( $column && preg_match( '/\A(?:float|double|decimal|numeric)(?:\(\d+(?:,\d+)?\))?(?: unsigned)?(?: zerofill)?\z/i', $column['type'] ) ) {
			$flags = $table['field']['quantity'];
			$aliases = self::column_database();
			$flags = is_string( $flags ) ? $aliases[ $flags ] : $flags;
			$flags[0] = $column['type'];
			$flags[1] = null;
			$table['field']['quantity'] = $flags;
		}
		return $table;
	}

	/** Collect the current descriptors without replaying historical migration actions. */
	private static function current_tables() {
		$methods = array_filter( get_class_methods( __CLASS__ ), static function ( $method ) {
			return preg_match( self::REGEXP, $method ) && version_compare( self::$_version, self::pre_database( $method ), 'ge' );
		} );
		uasort( $methods, array( __CLASS__, 'sort_database' ) );
		$tables = array();
		foreach ( $methods as $method ) {
			$tables = self::merge_database( $tables, $method );
		}
		if ( isset( $tables['items']['field']['quantity'] ) ) { $tables['items'] = self::compatible_items_table( 'items', $tables['items'] ); }
		return $tables;
	}

	/** Upgrade existing tables even when the plugin release version is unchanged. */
	public static function maybe_upgrade_charset() {
		global $wpdb;
		if ( 'utf8mb4' !== $wpdb->charset || ! $wpdb->has_cap( 'utf8mb4' ) ) {
			return false;
		}
		$marker = self::$_name . '_utf8mb4_schema_f';
		if ( '1' === (string) get_option( $marker ) ) {
			return true;
		}
		return self::with_schema_lock( static function () use ( $marker ) {
			global $wpdb;
			$state = self::schema_state();
			if ( false === $state ) { return false; }
			if ( ! self::schema_state_ready( $state ) ) { return null; }
			$tables = self::current_tables();
			if ( ! self::schema_matches( $tables ) ) {
				return false;
			}
			foreach ( $tables as $name => $table ) {
				if ( ! empty( $table['field'] ) && ! self::upgrade_table_charset( $name, $table ) ) {
					return false;
				}
			}
			if ( ! self::schema_matches( $tables ) ) {
				return false;
			}
			return self::schema_checkpoint( $marker, '1' );
		} );
	}

	/** Change only known UTF-8 fields; retain types, indexes and extension columns. */
	private static function upgrade_table_charset( $name, $table ) {
		global $wpdb;
		$name = self::$wpdb_prefix . self::$_name . '_' . $name;
		$quoted = str_replace( '`', '``', $name );
		self::schema_stage( 'alter_charset', $name, array( 'attribute' => 'charset', 'expected' => 'utf8mb4' ) );
		$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $name ), ARRAY_A );
		if ( $wpdb->last_error || ! $status ) {
			return self::schema_failure();
		}
		$columns = $wpdb->get_results( "SHOW FULL COLUMNS FROM `$quoted`", ARRAY_A );
		if ( $wpdb->last_error || ! $columns ) {
			return self::schema_failure();
		}
		self::schema_stage( 'alter_charset', $name, array( 'attribute' => 'charset', 'expected' => 'utf8mb4', 'actual' => $status['Collation'] ) );
		$charset = strtolower( explode( '_', (string) $status['Collation'] )[0] );
		if ( ! in_array( $charset, array( 'utf8', 'utf8mb3', 'utf8mb4' ), true ) ) {
			return self::schema_failure();
		}
		$collate = $wpdb->collate ? $wpdb->collate : 'utf8mb4_unicode_ci';
		if ( ! preg_match( '/\Autf8mb4_[a-z0-9_]+\z/i', $collate ) ) {
			return self::schema_failure();
		}
		$changes = 'utf8mb4' === $charset ? array() : array( "DEFAULT CHARACTER SET utf8mb4 COLLATE $collate" );
		$aliases = self::column_database();
		foreach ( $columns as $column ) {
			$field = $column['Field'];
			if ( ! isset( $table['field'][ $field ] ) || ! $column['Collation'] ) {
				continue;
			}
			$charset = strtolower( explode( '_', $column['Collation'] )[0] );
			if ( 'utf8mb4' === $charset ) {
				continue;
			}
			if ( ! in_array( $charset, array( 'utf8', 'utf8mb3' ), true ) ) {
				return self::schema_failure();
			}
			$flags = $table['field'][ $field ];
			$flags = is_string( $flags ) ? $aliases[ $flags ] : $flags;
			$flags = array_replace( array( 'TEXT', null, false, null, false ), $flags );
			$type = $flags[0] . ( null === $flags[1] ? '' : '(' . $flags[1] . ')' );
			$flags[0] = "$type CHARACTER SET utf8mb4 COLLATE $collate";
			$flags[1] = null;
			$definition = self::field( $field, $flags );
			if ( '' !== $column['Comment'] ) {
				$definition .= $wpdb->prepare( ' COMMENT %s', $column['Comment'] );
			}
			$changes[] = 'MODIFY ' . $definition;
		}
		if ( $changes && ( ! self::can_alter_schema() || false === $wpdb->query( "ALTER TABLE `$quoted` " . implode( ', ', $changes ) ) ) ) {
			return self::schema_failure();
		}
		$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $name ), ARRAY_A );
		if ( $wpdb->last_error || ! $status || 0 !== strpos( $status['Collation'], 'utf8mb4_' ) ) {
			return self::schema_failure();
		}
		$columns = $wpdb->get_results( "SHOW FULL COLUMNS FROM `$quoted`", ARRAY_A );
		if ( $wpdb->last_error || ! $columns ) {
			return self::schema_failure();
		}
		foreach ( $columns as $column ) {
			if ( isset( $table['field'][ $column['Field'] ] ) && $column['Collation'] && 0 !== strpos( $column['Collation'], 'utf8mb4_' ) ) {
				return self::schema_failure( array( 'column' => $column['Field'], 'actual' => $column['Collation'] ) );
			}
		}
		return true;
	}


	/** MySQL 8 omits integer display widths that MariaDB still reports. */
	private static function schema_type( $type ) {
		$type = strtolower( preg_replace( '/\s+/', ' ', trim( $type ) ) );
		return preg_replace( '/\b(tinyint|smallint|mediumint|int|integer|bigint)\(\d+\)/', '$1', $type );
	}

	/** Compare numeric defaults exactly, including server-added scale and exponent notation. */
	private static function schema_numeric_default( $value ) {
		if ( ! preg_match( '/^([+-]?)(\d*)(?:\.(\d*))?(?:[eE]([+-]?\d+))?$/D', (string) $value, $parts ) || ( '' === $parts[2] && '' === ( $parts[3] ?? '' ) ) ) { return (string) $value; }
		$fraction = $parts[3] ?? '';
		$digits = ltrim( $parts[2] . $fraction, '0' );
		if ( '' === $digits ) { return '0'; }
		$trimmed = rtrim( $digits, '0' );
		$power = (int) ( $parts[4] ?? 0 ) - strlen( $fraction ) + strlen( $digits ) - strlen( $trimmed );
		return ( '-' === $parts[1] ? '-' : '' ) . $trimmed . 'e' . $power;
	}

	/** Compare required column attributes without relying on dbDelta defaults/nullability support. */
	private static function column_matches( $column, $flags ) {
		return ! self::column_mismatch( $column, $flags );
	}

	/** Use exactly the validator's existing normalization for diagnostic comparisons. */
	private static function column_mismatch( $column, $flags ) {
		$type = $flags[0] . ( null === $flags[1] ? '' : '(' . $flags[1] . ')' );
		$default = $column['Default'];
		if ( is_string( $default ) && strlen( $default ) >= 2 && "'" === $default[0] && "'" === substr( $default, -1 ) ) {
			$default = str_replace( "''", "'", substr( $default, 1, -1 ) );
		}
		$expected_default = $flags[3];
		if ( 'CURRENT_TIMESTAMP' === $expected_default ) { $default = strtoupper( str_replace( '()', '', (string) $default ) ); }
		if ( null !== $default && null !== $expected_default && preg_match( '/^(?:tinyint|smallint|mediumint|int|integer|bigint|decimal|numeric|float|double|real|bit)\b/i', $type ) ) {
			$default = self::schema_numeric_default( $default );
			$expected_default = self::schema_numeric_default( $expected_default );
		}
		if ( self::schema_type( $column['Type'] ) !== self::schema_type( $type ) ) { return array( 'attribute' => 'type', 'expected' => $type, 'actual' => $column['Type'] ); }
		if ( ( 'YES' === $column['Null'] ) !== (bool) $flags[2] ) { return array( 'attribute' => 'nullable', 'expected' => (bool) $flags[2], 'actual' => $column['Null'] ); }
		if ( ( null === $default ? null : (string) $default ) !== ( null === $expected_default ? null : (string) $expected_default ) ) { return array( 'attribute' => 'default', 'expected' => $flags[3], 'actual' => $column['Default'] ); }
		if ( ( false !== stripos( $column['Extra'], 'auto_increment' ) ) !== (bool) $flags[4] ) { return array( 'attribute' => 'auto_increment', 'expected' => (bool) $flags[4], 'actual' => $column['Extra'] ); }
		return array();
	}

	/** Verify required definitions; leave unrelated extension columns/indexes untouched. */
	private static function schema_matches( $tables ) {
		global $wpdb;
		$aliases = self::column_database();
		foreach ( $tables as $name => $table ) {
			if ( empty( $table['field'] ) ) { continue; }
			$name = self::$wpdb_prefix . self::$_name . '_' . $name;
			self::schema_stage( 'final_validation', $name );
			$columns = self::upgrade_get_columns( $name );
			if ( $wpdb->last_error || ! $columns ) { return self::schema_failure( array( 'attribute' => 'table', 'expected' => 'required columns', 'actual' => 'missing or unreadable' ) ); }
			$primary = isset( $table['key'], $table['field'][ $table['key'] ] ) ? $table['key'] : '';
			foreach ( $table['field'] as $field => $flags ) {
				$flags = is_string( $flags ) ? ( $aliases[ $flags ] ?? $aliases['text'] ) : $flags;
				$flags = array_replace( array( 'TEXT', null, false, null, false ), $flags );
				if ( ! isset( $columns[ $field ] ) ) { return self::schema_failure( array( 'column' => $field, 'attribute' => 'column', 'expected' => 'present', 'actual' => 'missing' ) ); }
				$mismatch = self::column_mismatch( $columns[ $field ], $flags );
				if ( $mismatch ) { return self::schema_failure( array_merge( array( 'column' => $field ), $mismatch ) ); }
				if ( ! $primary && $flags[4] ) { $primary = $field; }
			}
			if ( ! $primary ) {
				foreach ( array_keys( $table['field'] ) as $field ) { if ( preg_match( '/id$/i', $field ) ) { $primary = $field; break; } }
			}
			$rows = $wpdb->get_results( "SHOW INDEX FROM `$name`", ARRAY_A );
			if ( $wpdb->last_error || ! is_array( $rows ) ) { return self::schema_failure( array( 'attribute' => 'indexes', 'expected' => 'readable', 'actual' => 'unreadable' ) ); }
			$indexes = $index_flags = array();
			foreach ( $rows as $row ) {
				$indexes[ $row['Key_name'] ][ (int) $row['Seq_in_index'] ] = $row['Column_name'];
				$index_flags[ $row['Key_name'] ] = min( $index_flags[ $row['Key_name'] ] ?? 1, (int) $row['Non_unique'] );
				if ( null !== $row['Sub_part'] ) { $index_flags[ $row['Key_name'] ] = -1; }
			}
			$required = $table['index'] ?? array();
			if ( $primary ) { $required['PRIMARY'] = $primary; }
			foreach ( $required as $index => $fields ) {
				$expected = array_map( static fn( $field ) => trim( $field, " `\t\n\r" ), explode( ',', $fields ) );
				if ( ! isset( $indexes[ $index ] ) ) { return self::schema_failure( array( 'index' => $index, 'attribute' => 'columns', 'expected' => $expected, 'actual' => 'missing' ) ); }
				ksort( $indexes[ $index ] );
				if ( array_values( $indexes[ $index ] ) !== $expected ) { return self::schema_failure( array( 'index' => $index, 'attribute' => 'columns', 'expected' => $expected, 'actual' => array_values( $indexes[ $index ] ) ) ); }
				if ( $index_flags[ $index ] !== ( 'PRIMARY' === $index ? 0 : 1 ) ) { return self::schema_failure( array( 'index' => $index, 'attribute' => 'non_unique_or_prefix', 'expected' => 'PRIMARY' === $index ? 0 : 1, 'actual' => $index_flags[ $index ] ) ); }
			}
			$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $name ), ARRAY_A );
			if ( $wpdb->last_error || ! $status || ! in_array( strtolower( $status['Engine'] ?? '' ), array( 'innodb', 'xtradb' ), true ) ) { return self::schema_failure( array( 'attribute' => 'engine', 'expected' => 'InnoDB/XtraDB', 'actual' => $status['Engine'] ?? null ) ); }
		}
		return true;
	}

	/**
	 * Get columns for exist table.
	 *
	 * @param string $name Table name.
	 *
	 * @return array
	 * @global wpdb $wpdb
	 *
	 */
	public static function upgrade_get_columns($name)
	{
		global $wpdb;

		$_fields = array();
		$previous = $wpdb->suppress_errors( true );
		try { $fields = $wpdb->get_results( "DESCRIBE `{$name}`", ARRAY_A ); }
		finally { $wpdb->suppress_errors( $previous ); } // WPCS: db call ok; no-cache ok; unprepared SQL ok.
		foreach ($fields as $field) {
			$_fields[$field['Field']] = $field;
		}

		return $_fields;
	}

	/**
	 * Apply upgrade action
	 *
	 * @param string $name Name Table.
	 * @param array $table Structured array table.
	 *
	 * @return boolean
	 * @global wpdb $wpdb
	 *
	 */
	public static function upgrade_action($name, $table)
	{

		if (!array_key_exists('upgrade', $table)) {
			return false;
		}

		global $wpdb;
		$t = self::column_database();
		foreach ($table['field'] as $key => $flags) {
			if (is_string($flags)) {
				if (array_key_exists($flags, $t)) {
					$flags = $t[$flags];
				} else {
					$flags = $t['text'];
				}
				$table['field'][$key] = $flags;
			}
		}
		$name = self::$wpdb_prefix . self::$_name . '_' . $name;
		$upgrades = (array)$table['upgrade'];
		foreach ($upgrades as $ver_upgrades) {
			foreach ($ver_upgrades as $upgrade) {
				$action = $upgrade;
				if (is_array($upgrade)) {
					$action = $upgrade['action'];
					unset($upgrade['action']);
				}
				if (!is_string($action)) {
					continue;
				}
				$action = preg_replace('/[^a-z0-9_]/i', '', (string)$action);
				if (empty($action)) {
					continue;
				}

				if (method_exists(__CLASS__, __FUNCTION__ . '_' . $action)) {
					self::schema_stage( 'historical_' . $action, $name );
					$result = call_user_func(array(__CLASS__, __FUNCTION__ . '_' . $action), $name, $table, $upgrade);
					if ( false === $result || $wpdb->last_error ) {
						return false;
					}
				}
			}
		}
		return true;
	}

	/**
	 * Apply upgrade action
	 * Truncate table
	 *
	 * @param string $name Table name.
	 *
	 * @global wpdb $wpdb
	 *
	 */
	public static function upgrade_action_truncate($name)
	{
		global $wpdb;
		$wpdb->query("TRUNCATE `{$name}`"); // WPCS: db call ok; no-cache ok; unprepared SQL ok.
	}

	/**
	 * Apply upgrade action
	 * Drop table
	 *
	 * @param string $name Table name.
	 *
	 * @global wpdb $wpdb
	 *
	 */
	public static function upgrade_action_drop($name)
	{
		global $wpdb;
		$sql = sprintf('DROP TABLE IF EXISTS `%s`;', $name);
		$wpdb->query($sql); // WPCS: db call ok; no-cache ok; unprepared SQL ok.
	}

	/**
	 * Apply upgrade action
	 * Rename table
	 *
	 * @param string $name Table name.
	 * @param array $table Not used.
	 * @param array $upgrade Upgrade fields.
	 *
	 * @return boolean
	 * @global wpdb $wpdb
	 *
	 */
	public static function upgrade_action_rename_table($name, $table, $upgrade)
	{
		global $wpdb;
		if (!array_key_exists('from', $upgrade)) {
			return false;
		}
		$_name = self::$wpdb_prefix . self::$_name . '_' . $upgrade['from'];

		$_t_name = $wpdb->get_var("SHOW TABLES LIKE '{$_name}'"); // WPCS: db call ok; no-cache ok; unprepared SQL ok.
		if ($_t_name == $_name) { // WPCS: loose comparison ok.
			self::upgrade_action_drop($name);
		}
		$wpdb->query(sprintf('RENAME TABLE `%s` TO `%s`;', $_name, $name)); // WPCS: db call ok; no-cache ok; unprepared SQL ok.
	}

	/**
	 * Apply upgrade action
	 * Update fields table
	 *
	 * @param string $name Table name.
	 * @param array $table Table array.
	 *
	 * @global wpdb $wpdb
	 *
	 */
	public static function upgrade_action_update_fields($name, $table)
	{
		global $wpdb;

		$_fields = self::upgrade_get_columns($name);
		if ( $wpdb->last_error ) {
			return false;
		}
		$fields = $table['field'];

		// Keep unrelated extension columns while upgrading declared fields.
		$prev_field = '';

		foreach ($fields as $field => $attr) {
			$attr = self::field($field, $attr);

			$sql = sprintf('ALTER TABLE `%s` MODIFY %s;', $name, $attr);
			if (!array_key_exists($field, $_fields)) {
				$_prev_field = empty($prev_field) ? '' : " AFTER `{$prev_field}`";
				$sql = sprintf('ALTER TABLE `%s` ADD %s;', $name, $attr . $_prev_field);
			}
			if ( false === $wpdb->query( $sql ) ) {
				return false;
			}

			$prev_field = $field;
		}
		return true;
	}

	/**
	 * Apply upgrade action
	 * Add table index
	 *
	 * @param string $name Table name.
	 * @param array $table Table array.
	 *
	 * @global wpdb $wpdb
	 *
	 */
	public static function upgrade_action_add_index( $name, $table ) {
		// Existing required indexes are repaired by the same path as reconciliation.
		return self::repair_indexes( $name, array( 'field' => array(), 'index' => $table['index'] ?? array() ) );
	}

	/**
	 * Apply upgrade action
	 * Update field table
	 *
	 * @param string $name Table name.
	 * @param array $table Table array.
	 * @param array $upgrade Upgrade fields.
	 *
	 * @return boolean
	 * @global wpdb $wpdb
	 *
	 */
	public static function upgrade_action_update_field($name, $table, $upgrade)
	{
		global $wpdb;
		if (!array_key_exists('field', $upgrade)) {
			return false;
		}
		$name_field = $upgrade['field'];

		$_fields = self::upgrade_get_columns($name);
		if ( $wpdb->last_error ) {
			return false;
		}
		$fields = $table['field'];

		$sql = '';

		if (array_key_exists($name_field, $fields)) {
			$prev_field = '';
			foreach ($fields as $field => $attr) {
				if ($name_field == $field) { // WPCS: loose comparison ok.
					break;
				}
				$prev_field = $field;
			}

			$attr = self::field($name_field, $fields[$name_field]);

			$sql = sprintf('ALTER TABLE `%s` MODIFY %s;', $name, $attr);
			if (!array_key_exists($name_field, $_fields)) {
				$_prev_field = empty($prev_field) ? '' : " AFTER `{$prev_field}`";
				$sql = sprintf('ALTER TABLE `%s` ADD %s;', $name, $attr . $_prev_field);
			}
		} else {
			if (!array_key_exists($name_field, $fields)) {
				$sql = sprintf('ALTER TABLE `%s` DROP COLUMN `%s`;', $name, $name_field);
			}
		}
		if (!empty($sql)) {
			$wpdb->query($sql); // WPCS: db call ok; no-cache ok; unprepared SQL ok.
		}
	}

	/**
	 * Apply upgrade action
	 * Rename field.
	 *
	 * @param string $name Table name.
	 * @param array $table Table array.
	 * @param array $upgrade Upgrade fields.
	 *
	 * @return boolean
	 * @global wpdb $wpdb
	 *
	 */
	public static function upgrade_action_rename_field($name, $table, $upgrade)
	{
		global $wpdb;
		if (!array_key_exists('from', $upgrade) || !array_key_exists('to', $upgrade)) {
			return false;
		}
		$old_field = $upgrade['from'];
		$new_field = $upgrade['to'];

		$_fields = self::upgrade_get_columns($name);
		if ( $wpdb->last_error ) {
			return false;
		}
		$fields = $table['field'];

		if (!array_key_exists($old_field, $_fields) || !array_key_exists($new_field, $fields)) {
			return false;
		}

		if (array_key_exists($new_field, $_fields)) {
			$sql = sprintf('ALTER TABLE `%s` DROP COLUMN `%s`;', $name, $new_field);
			$wpdb->query($sql); // WPCS: db call ok; no-cache ok; unprepared SQL ok.
		}

		$attr = self::field($new_field, $fields[$new_field]);

		$sql = sprintf('ALTER TABLE `%s` CHANGE `%s` %s;', $name, $old_field, $attr);
		$wpdb->query($sql); // WPCS: db call ok; no-cache ok; unprepared SQL ok.

		return true;
	}

	/**
	 * Apply upgrade action
	 * Use sql.
	 *
	 * @param string $name Table name.
	 * @param array $table Not used.
	 * @param array $upgrade Upgrade fields.
	 *
	 * @return boolean
	 * @global wpdb $wpdb
	 *
	 */
	public static function upgrade_action_sql($name, $table, $upgrade)
	{
		global $wpdb;
		if (!array_key_exists('sql', $upgrade)) {
			return false;
		}
		$name = self::$wpdb_prefix . self::$_name . '_' . $name;

		$wpdb->query(str_replace('{table_name}', $name, $upgrade['sql'])); // WPCS: db call ok; no-cache ok; unprepared SQL ok.
	}

	/**
	 * Destroy tables from array
	 *
	 * @param array $tables Array tables.
	 *
	 * @return boolean
	 * @global wpdb $wpdb
	 *
	 */
	public static function destroy($tables)
	{
		global $wpdb;
		self::$wpdb_prefix = $wpdb->prefix;

		foreach ($tables as $name => $table) {
			$table = self::$wpdb_prefix . self::$_name . '_' . $name;
			$sql = sprintf('DROP TABLE IF EXISTS `%s`;', $table);
			$wpdb->query($sql); // WPCS: db call ok; no-cache ok; unprepared SQL ok.
		}

		delete_option(self::$_name . '_db_ver');
		delete_option( self::$_name . '_utf8mb4_schema_f' );
		delete_option( self::schema_marker() );
		delete_option( self::schema_marker() . '_error' );
		delete_option( self::schema_marker() . '_lock_error' );
		delete_option( self::schema_marker() . '_last_log' );

		return true;
	}

	/**
	 * Predefined fields attributes
	 *
	 * @return array
	 */
	public static function column_database()
	{
		/**
		 * Array format for fields
		 *
		 * @param string            TYPE
		 * @param integer|string    SIZE
		 * @param boolean            NULL
		 * @param integer|string    DEFAULT
		 * @param boolean            AUTO_INCREMENT
		 */
		return array(
			'first++' => array('BIGINT', null, false, null, true),
			'int_0' => array('BIGINT', null, false, 0),
			'int_1' => array('BIGINT', null, false, 1),
			'text' => array('TEXT', null, true, null),
			'longtext' => array('LONGTEXT'),
			'date' => array('DATETIME', null, false, '0000-00-00 00:00:00'),
			'bool' => array('TINYINT', 1, false, 1),
		);
	}

	/**
	 * Database
	 *
	 * @return array
	 * @since             1.0.0
	 */
	private static function database_1_0_0()
	{
		$t = array(
			'status' => array('VARCHAR', 20, false, 'public'),
			'type' => array('VARCHAR', 20, false, 'list'),
			'key_elem' => array('VARCHAR', 45),
			'key_user' => array('TINYINT', 1, false, 7),
		);

		return array(
			'lists' => array(
				'field' => array(
					'ID' => 'first++',
					'author' => 'int_0',
					'date' => 'date',
					'title' => 'text',
					'status' => $t['status'],
					'type' => $t['type'],
					'share_key' => $t['key_elem'],
				),
			),
			'items' => array(
				'field' => array(
					'ID' => 'first++',
					'wishlist_id' => 'int_0',
					'product_id' => 'int_0',
					'variation_id' => 'int_0',
					'author' => 'int_0',
					'date' => 'date',
					'quantity' => 'int_1',
					'price' => $t['key_elem'],
					'in_stock' => 'bool',
				),
			),
		);
	}

	/**
	 * Database
	 *
	 * @return array
	 * @since             1.5.0
	 */
	private static function database_1_5_0()
	{
		return array(
			'items' => array(
				'field' => array(
					'ID' => 'first++',
					'wishlist_id' => 'int_0',
					'product_id' => 'int_0',
					'variation_id' => 'int_0',
					'formdata' => 'text',
					'author' => 'int_0',
					'date' => 'date',
					'quantity' => 'int_1',
					'price' => array('VARCHAR', 255),
					'in_stock' => 'bool',
				),
				'upgrade' => array(
					array(
						'action' => 'update_fields',
					),
				),
			),
		);
	}

	/**
	 * Database
	 *
	 * @return array
	 * @since             1.8.13
	 */
	private static function database_1_8_13()
	{
		$t = array(
			'status' => array('VARCHAR', 20, false, 'public'),
			'type' => array('VARCHAR', 20, false, 'list'),
			'key_elem' => array('VARCHAR', 45),
			'key_user' => array('TINYINT', 1, false, 7),
		);

		return array(
			'lists' => array(
				'field' => array(
					'ID' => 'first++',
					'author' => 'int_0',
					'date' => 'date',
					'title' => 'text',
					'status' => $t['status'],
					'type' => $t['type'],
					'share_key' => $t['key_elem'],
				),
				'upgrade' => array(
					array(
						'action' => 'update_fields',
					),
				),
			),
			'items' => array(
				'field' => array(
					'ID' => 'first++',
					'wishlist_id' => 'int_0',
					'product_id' => 'int_0',
					'variation_id' => 'int_0',
					'formdata' => 'text',
					'author' => 'int_0',
					'date' => 'date',
					'quantity' => 'int_1',
					'price' => array('VARCHAR', 255),
					'in_stock' => 'bool',
				),
				'upgrade' => array(
					array(
						'action' => 'update_fields',
					),
				),
			),
		);
	}

	/**
	 * Database
	 *
	 * @return array
	 * @since             1.9.16
	 */
	private static function database_1_9_16()
	{

		return array(
			'items' => array(
				'field' => array(
					'ID' => 'first++',
					'wishlist_id' => 'int_0',
					'product_id' => 'int_0',
					'variation_id' => 'int_0',
					'formdata' => 'text',
					'author' => 'int_0',
					'date' => 'date',
					'quantity' => 'int_1',
					'price' => array('VARCHAR', 255, false, 0),
					'in_stock' => 'bool',
				),
				'upgrade' => array(
					array(
						'action' => 'update_fields',
					),
				),
			),
		);
	}

	/**
	 * Database
	 *
	 * @return array
	 * @since             1.10.0
	 */
	private static function database_1_10_0()
	{

		return array(
			'analytics' => array(
				'field' => array(
					'ID' => array('VARCHAR', 32),
					'wishlist_id' => 'int_0',
					'product_id' => 'int_0',
					'variation_id' => 'int_0',
					'visite_author' => 'int_0',
					'visite' => 'int_0',
					'click_author' => 'int_0',
					'click' => 'int_0',
					'cart' => 'int_0',
					'sell_of_wishlist' => 'int_0',
					'sell_as_gift' => 'int_0',
				),
				'key' => 'ID',
				'index' => array(
					'unique_product' => 'wishlist_id, product_id, variation_id',
				),
			),
		);
	}

	/** Add a covering index for statistics scoped to displayed products. */
	private static function database_2_14_0() {
		$table = self::database_1_9_16()['items'];
		$table['index'] = array( 'product_stats' => 'product_id, variation_id, wishlist_id' );
		$table['upgrade'] = array( array( 'action' => 'add_index' ) );
		return array( 'items' => $table );
	}

	/**
	 * Set localisation
	 */
	private static function set_locale()
	{
		$locale = apply_filters('plugin_locale', get_locale(), TINVWL_DOMAIN);
		$mofile = sprintf('%1$s-%2$s.mo', TINVWL_DOMAIN, $locale);
		$mofiles = array();

		$mofiles[] = WP_LANG_DIR . DIRECTORY_SEPARATOR . basename(TINVWL_PATH) . DIRECTORY_SEPARATOR . $mofile;
		$mofiles[] = WP_LANG_DIR . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . $mofile;
		$mofiles[] = TINVWL_PATH . 'languages' . DIRECTORY_SEPARATOR . $mofile;
		foreach ($mofiles as $mofile) {
			if (file_exists($mofile) && load_textdomain(TINVWL_DOMAIN, $mofile)) {
				return;
			}
		}

		load_plugin_textdomain(TINVWL_DOMAIN, false, basename(TINVWL_PATH) . DIRECTORY_SEPARATOR . 'languages');
	}

	/**
	 * Load default data
	 */
	public static function load_data()
	{
		self::set_locale();
		$settings = tinv_get_option_defaults('all');
		foreach ($settings as $setting => $array) {
			add_option(sprintf('%s-%s', self::$_name, $setting), $array);
		}
	}

	/**
	 * Upgrade default data
	 */
	public static function upgrade_data()
	{
		self::set_locale();
		$settings = tinv_get_option_defaults('all');
		foreach ($settings as $setting => $array) {
			$_array = get_option(sprintf('%s-%s', self::$_name, $setting));
			if (false === $_array) {
				add_option(sprintf('%s-%s', self::$_name, $setting), $array);
			} else {
				$need_upgrade = false;
				foreach ((array)$array as $key => $value) {
					if (!array_key_exists($key, (array)$_array)) {
						$_array[$key] = $value;
						$need_upgrade = true;
					}
				}
				if ($need_upgrade) {
					update_option(sprintf('%s-%s', self::$_name, $setting), $_array);
				}
			}
		}
	}

	/**
	 * Unload default data
	 */
	public static function unload_data()
	{
		$settings = array_keys(tinv_get_option_defaults('all'));
		foreach ($settings as $setting) {
			delete_option(sprintf('%s-%s', self::$_name, $setting));
		}
		delete_option(self::$_name . '_ver');
		delete_option(self::$_name . '_wizard');
	}

	/**
	 * Remove 'administrator' role capabilities
	 */
	public static function remove_capabities()
	{
		global $wp_roles;

		$role = $wp_roles->get_role('administrator');
		if ($role) {
			foreach ($role->capabilities as $key => $value) {
				if (strpos($key, self::$_name) === 0) {
					$wp_roles->remove_cap('administrator', $key);
				}
			}
		}
	}
}
