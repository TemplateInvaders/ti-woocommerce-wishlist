<?php
/**
 * Wishlists function class
 *
 * @since             1.5.0
 * @package           TInvWishlist\Wishlists
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
	die;
}

/**
 * Wishlists function class
 */
class TInvWL_Wishlist
{

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
	 * User id
	 *
	 * @var integer
	 */
	public $user;
	/**
	 * Default share key for wishlist
	 *
	 * @var string
	 */
	public static $default_sharekey;
	/**
	 * Default name wishlist
	 *
	 * @var string
	 */
	private $default_name;
	/**
	 * Default privacy wishlist
	 *
	 * @var string
	 */
	private $default_privacy;
	/**
	 * Allowed privacy
	 *
	 * @var array
	 */
	private $privacy;
	/** Server-derived key while creating the one default for a verified guest identity. */
	private $guest_default_sharekey = null;

	/**
	 * Constructor
	 *
	 * @param string $plugin_name Plugin name.
	 *
	 * @global wpdb $wpdb
	 *
	 */
	function __construct($plugin_name = TINVWL_PREFIX)
	{
		global $wpdb;

		$this->_name = $plugin_name;
		$this->table = sprintf('%s%s_%s', $wpdb->prefix, $this->_name, 'lists');
		$this->default_name = apply_filters('tinvwl_default_wishlist_title', tinv_get_option('general', 'default_title'));
		$this->default_privacy = 'share';
		$this->privacy = array('public', 'share', 'private');
		if (!in_array($this->default_privacy, $this->privacy)) { // @codingStandardsIgnoreLine WordPress.PHP.StrictInArray.MissingTrueStrict
			$this->default_privacy = 'share';
		}
		$this->user = get_current_user_id();
	}

	/**
	 * Generate unique share key
	 *
	 * @return string
	 * @global wpdb $wpdb
	 */
	function unique_share_key()
	{
		global $wpdb;

		do {
			$share_key = bin2hex(random_bytes(16));
		} while ($wpdb->get_var($wpdb->prepare("SELECT `ID` FROM `{$this->table}` WHERE `share_key` = %s", $share_key)));

		return $share_key;
	}

	/**
	 * Accept existing share links as well as new 128-bit keys.
	 *
	 * @param mixed $share_key Share key.
	 * @return bool
	 */
	public static function is_valid_share_key($share_key)
	{
		return is_string($share_key) && 1 === preg_match('/\A(?:[a-f0-9]{6}|[a-f0-9]{32})\z/i', $share_key);
	}

	/**
	 * Check ownership independently of the public share key and is_owner flag.
	 *
	 * @param array $wishlist Wishlist data.
	 * @return bool
	 */
	public function is_owner($wishlist)
	{
		if (empty($wishlist['ID']) || !isset($wishlist['author'], $wishlist['share_key'])) {
			return false;
		}
		if (!empty($wishlist['author'])) {
			return is_user_logged_in() && (int) $wishlist['author'] === get_current_user_id();
		}

		$token = isset($_COOKIE['tinv_wishlist_owner']) ? $_COOKIE['tinv_wishlist_owner'] : '';
		if (!is_string($token) || !preg_match('/\A([1-9][0-9]*)\.([0-9]{10})\.([a-f0-9]{64})\z/', $token, $parts)) {
			return false;
		}
		if ((string) $wishlist['ID'] !== $parts[1] || (int) $parts[2] <= time()) {
			return false;
		}

		return hash_equals($this->guest_owner_signature($wishlist, $parts[2]), $parts[3]);
	}

	/**
	 * Apply the same privacy policy wherever a wishlist is read.
	 *
	 * @param array $wishlist Wishlist data.
	 * @return bool
	 */
	public function can_view($wishlist)
	{
		if (empty($wishlist['ID']) || !isset($wishlist['status'])) {
			return false;
		}

		return in_array($wishlist['status'], array('public', 'share'), true)
			|| $this->is_owner($wishlist)
			|| current_user_can('tinvwl_general_settings')
			|| current_user_can('manage_woocommerce')
			|| current_user_can('manage_options');
	}

	/**
	 * Sign a guest credential bound to this wishlist and its expiration.
	 */
	private function guest_owner_signature($wishlist, $expires)
	{
		$data = $this->_name . '|guest-owner|' . $wishlist['ID'] . '|' . $wishlist['share_key'] . '|' . $expires;
		return hash_hmac('sha256', $data, wp_salt('auth'));
	}

	/**
	 * Only creation may issue a guest credential; a share-key lookup must not.
	 */
	private function set_guest_owner($wishlist)
	{
		if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
		nocache_headers();
		$expires = time() + 31 * DAY_IN_SECONDS;
		$token = $wishlist['ID'] . '.' . $expires . '.' . $this->guest_owner_signature($wishlist, $expires);
		setcookie('tinv_wishlist_owner', $token, array(
			'expires' => $expires,
			'path' => COOKIEPATH ? COOKIEPATH : '/',
			'domain' => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
			'secure' => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		));
		$_COOKIE['tinv_wishlist_owner'] = $token;
	}

	/**
	 * Read an existing default wishlist without creating a list or issuing cookies.
	 *
	 * @param integer $user_id Can put 0 for the current user or guest.
	 * @return array|false
	 */
	public function get_default( $user_id = 0 ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			$user_id = $this->user;
		}
		$wishlists = $user_id ? $this->get_by_user_default( $user_id ) : $this->get_by_sharekey_default();
		return $wishlists ? array_shift( $wishlists ) : false;
	}

	/**
	 * Get/Create default wishlist
	 *
	 * @param integer $user_id Can put 0.
	 *
	 * @return boolean|array
	 */
	function add_user_default($user_id = 0)
	{
		$user_id = absint($user_id);
		if (empty($user_id)) {
			$user_id = $this->user;
		}
		if (empty($user_id)) {
			return $this->add_sharekey_default();
		}

		if (!current_user_can('tinvwl_general_settings') && ($user_id != get_current_user_id())) {
			return false;
		}

		global $wpdb;
		// Share the account mutex with guest transfers, which keep it through COMMIT.
		$lock = 'tinvwl_merge_' . md5($wpdb->prefix . '|' . $user_id);
		if ('1' !== (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock))) {
			return false;
		}
		try {
			$wl = $this->get_by_user_default( $user_id );
			if ( $wpdb->last_error ) {
				return false;
			}
			if ( $wl ) {
				return array_shift( $wl );
			}

			$wl = $this->add('', 'default', $this->default_privacy, $user_id);
			if (is_array($wl) && array_key_exists('share_key', $wl)) {
				$this->set_sharekey($wl['share_key']);

				return $wl;
			}

			return false;
		} finally {
			$error = $wpdb->last_error;
			$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
			$wpdb->last_error = $error;
		}
	}

	/**
	 * Get/Create default wishlist
	 *
	 * @param string $sharekey Can put empty.
	 *
	 * @return boolean|array
	 */
	function add_sharekey_default($sharekey = '')
	{
		global $wpdb;
		if ( empty( $sharekey ) ) { $sharekey = $this->get_sharekey(); }
		$wl = $this->get_by_sharekey_default( $sharekey );
		if ( self::is_valid_share_key( $sharekey ) && $wpdb->last_error ) { return false; }
		if ( $wl ) { return array_shift( $wl ); }

		$identity = TInvWL_Request_Nonce::guest_identity();
		if ( '' === $identity || is_user_logged_in() ) { return false; }
		$key = substr( hash_hmac( 'sha256', $this->_name . '|guest-default|' . get_current_blog_id() . '|' . $identity, wp_salt( 'auth' ) ), 0, 32 );
		$lock = 'tinvwl_guest_' . md5( $wpdb->prefix . '|' . $this->_name . '|' . $identity );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) { return false; }
		try {
			// A current read after the mutex sees a list committed by another first add.
			$wl = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE share_key = %s ORDER BY ID ASC LIMIT 1 FOR UPDATE", $key ), ARRAY_A );
			if ( $wpdb->last_error ) { return false; }
			if ( $wl ) {
				if ( ! empty( $wl['author'] ) || 'default' !== $wl['type'] ) { return false; }
				// Proof is the secret-derived key, never a public share-key lookup.
				$this->set_guest_owner( $wl );
				$wl['ID'] = absint( $wl['ID'] );
				$wl['author'] = 0;
				$wl['is_owner'] = true;
			} else {
				$this->guest_default_sharekey = $key;
				$wl = $this->add( array( 'title' => $this->default_name, 'type' => 'default', 'status' => $this->default_privacy, 'author' => 0, 'share_key' => $key ) );
			}
			if ( is_array( $wl ) && isset( $wl['share_key'] ) ) {
				$this->set_sharekey( $wl['share_key'] );
				return $wl;
			}
			return false;
		} finally {
			$this->guest_default_sharekey = null;
			$error = $wpdb->last_error;
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
			$wpdb->last_error = $error;
		}
	}

	/**
	 * Add wishlist
	 *
	 * @param mixed $data wishlist name or object.
	 * @param string $type List or default.
	 * @param string $status Public, Share, Private.
	 * @param integer $user_id Can put 0.
	 *
	 * @return boolean
	 * @global wpdb $wpdb
	 *
	 */
	function add($data, $type = 'list', $status = 'public', $user_id = 0)
	{
		$user_id = absint($user_id);
		if (empty($user_id)) {
			$user_id = $this->user;
		}

		$default = array(
			'author' => $user_id,
			'date' => current_time('Y-m-d H:i:s'),
			'status' => $this->default_privacy,
			'share_key' => $this->unique_share_key(),
			'title' => $this->default_name,
			'type' => 'list',
		);

		if (!is_array($data)) {
			$data = array(
				'title' => $data,
				'status' => $status,
				'type' => $type,
			);
		}
		$data = wp_parse_args($data, $default);
		$data = apply_filters('tinvwl_wishlist_add', $data);
		$data['title'] = sanitize_text_field($data['title']);
		$data['author'] = absint($data['author']);
		$data['type'] = self::normalize_type($data['type']);
		if (!in_array($data['status'], $this->privacy)) { // @codingStandardsIgnoreLine WordPress.PHP.StrictInArray.MissingTrueStrict
			$data['status'] = 'public';
		}
		if ($data['author'] && 'default' === $data['type']) {
			return $this->with_account_locks(array($data['author']), 0, function () use ($data) {
				global $wpdb;
				$existing = $this->account_default($data['author']);
				if ($wpdb->last_error) { return false; }
				if ($existing) {
					$existing['ID'] = absint($existing['ID']);
					$existing['author'] = absint($existing['author']);
					if ('' === (string) $existing['title']) { $existing['title'] = $this->default_name; }
					$existing['is_owner'] = $this->is_owner($existing);
					return apply_filters('tinvwl_wishlist_get', $existing);
				}
				return $this->insert_wishlist($data);
			});
		}
		return $this->insert_wishlist($data);
	}

	private function insert_wishlist($data)
	{
		global $wpdb;
		if ( null !== $this->guest_default_sharekey ) {
			if ( ! empty( $data['author'] ) || 'default' !== $data['type'] ) { return false; }
			$data['share_key'] = $this->guest_default_sharekey;
		}
		if ($wpdb->insert($this->table, $data)) { // @codingStandardsIgnoreLine WordPress.VIP.DirectDatabaseQuery.DirectQuery
			$data['ID'] = $wpdb->insert_id;
			if (empty($data['author']) && !is_user_logged_in()) {
				$this->set_guest_owner($data);
			}

			/* Run a 3rd party code when a new wishlist created.
			 *
			 * @param array $data A wishlist data.
			 * */
			do_action('tinvwl_wishlist_created', $data);

			return $data;
		}

		return false;
	}

	/** The model supports ordinary and default lists; normalize the documented type values. */
	private static function normalize_type($type)
	{
		return is_string($type) && 'default' === strtolower(trim($type)) ? 'default' : 'list';
	}

	/** Account creation, type changes and ownership transfers share one mutex order. */
	private function with_account_locks($owners, $wishlist_id, callable $write)
	{
		global $wpdb;
		$owners = array_unique(array_filter(array_map('absint', $owners)));
		sort($owners, SORT_NUMERIC);
		$locks = $acquired = array();
		foreach ($owners as $owner) {
			$locks[] = 'tinvwl_merge_' . md5($wpdb->prefix . '|' . $owner);
		}
		if ($wishlist_id) {
			$locks[] = 'tinvwl_item_' . md5($wpdb->prefix . $this->_name . '_items|' . absint($wishlist_id));
		}
		try {
			foreach ($locks as $lock) {
				if ('1' !== (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock))) { return false; }
				$acquired[] = $lock;
			}
			return $write();
		} finally {
			$error = $wpdb->last_error;
			foreach (array_reverse($acquired) as $lock) { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
			$wpdb->last_error = $error;
		}
	}

	/** A current, unfiltered read protects the invariant even inside an existing transaction snapshot. */
	private function account_default($owner, $exclude_id = 0)
	{
		global $wpdb;
		return $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$this->table}` WHERE author = %d AND type = 'default' AND ID <> %d ORDER BY ID ASC LIMIT 1 FOR UPDATE", $owner, $exclude_id), ARRAY_A);
	}

	/**
	 * Get default wishlist for user id
	 *
	 * @param integer $user_id Can put 0.
	 *
	 * @return array
	 */
	function get_by_user_default($user_id = 0)
	{
		$user_id = absint($user_id);
		if (empty($user_id)) {
			$user_id = $this->user;
		}

		$data = array(
			'author' => $user_id,
			'type' => 'default',
		);

		if (!current_user_can('tinvwl_general_settings') && (empty($this->user) || ($data['author'] != $this->user))) { // WPCS: loose comparison ok.
			$data['status'] = 'public';
		}

		return $this->get($data);
	}

	/**
	 * Get default wishlist for user id
	 *
	 * @param string $sharekey Can put empty.
	 *
	 * @return array
	 */
	function get_by_sharekey_default($sharekey = '')
	{
		if (empty($sharekey)) {
			$sharekey = $this->get_sharekey();
		}

		//validate sharekey
		if (!self::is_valid_share_key($sharekey)) {
			return array();
		}

		$data = array(
			'share_key' => $sharekey,
			'type' => 'default',
		);

		$wishlists = $this->get($data);
		// Default lists are used for writes and login transfers, not just viewing.
		return array_values(array_filter($wishlists, array($this, 'is_owner')));
	}

	/**
	 * Get wishlist for user id
	 *
	 * @param integer $user_id Can put 0.
	 * @param array $data Requset.
	 *
	 * @return array
	 */
	function get_by_user($user_id = 0)
	{
		$user_id = absint($user_id);
		if (empty($user_id)) {
			$user_id = $this->user;
		}
		$data = array(
			'author' => $user_id,
		);

		// Match the elevated read capabilities accepted by can_view() and the REST route.
		if (
			!current_user_can('tinvwl_general_settings')
			&& !current_user_can('manage_woocommerce')
			&& !current_user_can('manage_options')
			&& (empty($this->user) || ($data['author'] != $this->user)) // WPCS: loose comparison ok.
		) {
			$data['status'] = 'public';
		}

		return $this->get($data);
	}

	/**
	 * Get wishlist by id
	 *
	 * @param integer $id id database wishlist.
	 *
	 * @return array|bool
	 */
	function get_by_id($id)
	{
		$id = absint($id);
		if (empty($id)) {
			return false;
		}

		$wishlists = $this->get(array('ID' => $id));
		$wishlist = array_shift($wishlists);

		return $this->can_view($wishlist) ? $wishlist : null;
	}

	/**
	 * Get wishlist by share key
	 *
	 * @param string $share_key Share key.
	 *
	 * @return array
	 */
	function get_by_share_key($share_key)
	{
		if (!self::is_valid_share_key($share_key)) {
			return array();
		}
		$wishlists = $this->get(array('share_key' => $share_key));
		$wishlist = array_shift($wishlists);
		if (is_array($wishlist)) {
			$wishlist['is_owner'] = $this->is_owner($wishlist);
		}

		return $this->can_view($wishlist) ? $wishlist : null;
	}

	/**
	 * Get wishlist
	 *
	 * Low-level query for trusted internal callers. Request-controlled lookups
	 * must use get_by_id() or get_by_share_key(), which enforce privacy.
	 *
	 * @param array $data Requset.
	 *
	 * @return array
	 * @global wpdb $wpdb
	 *
	 */
	function get($data = array())
	{
		global $wpdb;

		$default = array(
			'count' => 10,
			'field' => null,
			'offset' => 0,
			'order' => 'ASC',
			'order_by' => 'title',
			'sql' => '',
		);

		foreach ($default as $_k => $_v) {
			if (array_key_exists($_k, $data)) {
				$default[$_k] = $data[$_k];
				unset($data[$_k]);
			}
		}

		//proper sanitizing
		$default['offset'] = absint($default['offset']);
		$default['count'] = absint($default['count']);
		//the order value is passed directly to the db so it needs to be protected against sql_injections
		$valid_order_values = array('ASC', 'DESC');
		if (!in_array(strtoupper($default['order']), $valid_order_values, true)) {
			$default['order'] = 'ASC';
		}

		if (is_array($default['field'])) {
			$default['field'] = '`' . implode('`,`', $default['field']) . '`';
		} elseif (is_string($default['field'])) {
			$default['field'] = array('ID', 'type', $default['field']);
			$default['field'] = '`' . implode('`,`', $default['field']) . '`';
		} else {
			$default['field'] = '*';
		}
		$sql = "SELECT {$default[ 'field' ]} FROM `{$this->table}`";

		$where = '1';
		if (!empty($data) && is_array($data)) {
			foreach ($data as $f => $v) {
				$s = is_array($v) ? ' IN ' : '=';
				if (is_array($v)) {
					foreach ($v as $_f => $_v) {
						$v[$_f] = $wpdb->prepare('%s', $_v);
					}
					$v = implode(',', $v);
					$v = "($v)";
				} else {
					$v = $wpdb->prepare('%s', $v);
				}
				$data[$f] = sprintf('`%s`%s%s', $f, $s, $v);
			}
			$where = ' WHERE ' . implode(' AND ', $data);
			$sql .= $where;
		}
		$sql .= sprintf(' ORDER BY `%s` %s LIMIT %d,%d;', $default['order_by'], $default['order'], $default['offset'], $default['count']);

		if (!empty($default['sql'])) {
			$replacer = $replace = array();
			$replace[0] = '{table}';
			$replacer[0] = $this->table;
			$replace[1] = '{where}';
			$replacer[1] = $where;

			foreach ($default as $key => $value) {
				$i = count($replace);

				$replace[$i] = '{' . $key . '}';
				$replacer[$i] = $value;
			}

			$sql = str_replace($replace, $replacer, $default['sql']);
		}

		$wls = $wpdb->get_results($sql, ARRAY_A); // WPCS: db call ok; no-cache ok; unprepared SQL ok.

		if (empty($wls)) {
			return array();
		}

		foreach ($wls as $k => $wl) {
			$wl['ID'] = absint($wl['ID']);
			if (array_key_exists('author', $wl)) {
				$wl['author'] = absint($wl['author']);
			}
			if ('default' === $wl['type'] && '' === (string) ($wl['title'] ?? '')) {
				$wl['title'] = $this->default_name;
			}

			if (is_array($wl)) {
				$wl['is_owner'] = $this->is_owner($wl);
			}

			$wls[$k] = apply_filters('tinvwl_wishlist_get', $wl);
		}

		return $wls;
	}

	/**
	 * Update wishlist
	 *
	 * @param integer $id id database wishlist.
	 * @param mixed $data wishlist name or object.
	 * @param string $type List or default.
	 * @param string $status Public, Share, Private.
	 *
	 * @return boolean
	 * @global wpdb $wpdb
	 *
	 */
	function update($id, $data, $type = 'list', $status = 'public')
	{
		global $wpdb;

		$id = absint($id);

		if (!is_array($data)) {
			$data = array(
				'title' => $data,
				'status' => $status,
				'type' => $type,
			);
		}
		$has_title = array_key_exists('title', $data);
		$data = filter_var_array($data, apply_filters('tinvwl_wishlist_fields_update', array(
			'title' => FILTER_UNSAFE_RAW,
			'status' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
			'type' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
			'author' => FILTER_VALIDATE_INT,
		)));

		$data = array_filter($data, static function ($value, $key) {
			return ('title' === $key && '0' === $value) || (bool) $value;
		}, ARRAY_FILTER_USE_BOTH);
		$data = apply_filters('tinvwl_wishlist_update', $data, $id);
		if (isset($data['title'])) {
			$data['title'] = sanitize_text_field($data['title']);
		}
		if ($has_title && !array_key_exists('title', $data)) {
			// Internal updates may run without the owner's browser session.
			$wishlist = $this->get(array('ID' => $id))[0] ?? null;
			if ($wishlist && 'default' === $wishlist['type']) {
				$data['title'] = '';
			}
		}

		if (array_key_exists('type', $data)) { $data['type'] = self::normalize_type($data['type']); }
		if (array_key_exists('author', $data)) { $data['author'] = absint($data['author']); }
		$sql = $wpdb->prepare("SELECT ID, author, type FROM `{$this->table}` WHERE ID = %d", $id);
		$parent = $wpdb->get_row($sql, ARRAY_A);
		if ($wpdb->last_error || !$parent) { return false; }
		$owner = (int) $parent['author'];
		$next_owner = array_key_exists('author', $data) ? $data['author'] : $owner;
		return $this->with_account_locks(array($owner, $next_owner), $id, function () use ($id, $data, $sql, $owner, $next_owner) {
			global $wpdb;
			$current = $wpdb->get_row($sql . ' FOR UPDATE', ARRAY_A);
			if ($wpdb->last_error || !$current || (int) $current['author'] !== $owner) { return false; }
			$type = $data['type'] ?? self::normalize_type($current['type']);
			if ($next_owner && 'default' === $type) {
				$existing = $this->account_default($next_owner, $id);
				// Reject the whole transition; API transfers then roll back both list and item ownership.
				if ($wpdb->last_error || $existing) { return false; }
			}
			return false !== $wpdb->update($this->table, $data, array('ID' => $id));
		});
	}

	/** Update a REST-owned list and its item ownership as one serialized transaction. */
	public function update_for_api( $id, array $data ) {
		global $wpdb;
		$id = absint( $id );
		$items = $wpdb->prefix . $this->_name . '_items';
		$sql = $wpdb->prepare( "SELECT * FROM `$this->table` WHERE ID = %d", $id );
		$parent = $wpdb->get_row( $sql, ARRAY_A );
		if ( $wpdb->last_error || ! $parent ) {
			return false;
		}
		$owner = (int) $parent['author'];
		$next_owner = isset( $data['author'] ) ? absint( $data['author'] ) : $owner;
		if ( $next_owner && ! get_userdata( $next_owner ) ) {
			return false;
		}
		$owners = array_unique( array_filter( array( $owner, $next_owner ) ) );
		sort( $owners, SORT_NUMERIC );
		$locks = array();
		foreach ( $owners as $author ) {
			$locks[] = 'tinvwl_merge_' . md5( $wpdb->prefix . '|' . $author );
		}
		$locks[] = 'tinvwl_item_' . md5( $items . '|' . $id );
		$acquired = array();
		$transaction = false;
		try {
			// Refuse nested transactions rather than commit a caller's unrelated changes.
			$suppressed = $wpdb->suppress_errors( true );
			try { $independent = $wpdb->query( 'SET TRANSACTION READ WRITE' ); }
			finally { $wpdb->suppress_errors( $suppressed ); }
			if ( false === $independent ) { return false; }
			$tables = $wpdb->get_results( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name IN (%s, %s)', $this->table, $items ), ARRAY_A );
			if ( $wpdb->last_error || ! is_array( $tables ) || count( $tables ) !== 2 ) { return false; }
			foreach ( $tables as $table ) {
				if ( ! in_array( strtolower( $table['Engine'] ), array( 'innodb', 'xtradb' ), true ) ) { return false; }
			}
			foreach ( $locks as $lock ) {
				if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) { return false; }
				$acquired[] = $lock;
			}
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return false; }
			$transaction = true;
			$current = $wpdb->get_row( $sql . ' FOR UPDATE', ARRAY_A );
			$manager = current_user_can( 'tinvwl_general_settings' ) || current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
			if ( $wpdb->last_error || ! $current || (int) $current['author'] !== $owner
				|| ( ! $manager && ( ! $this->is_owner( $current ) || $next_owner !== $owner ) ) ) {
				return false;
			}
			if ( ! $this->update( $id, $data ) ) { return false; }
			$saved_owner = $wpdb->get_var( $wpdb->prepare( "SELECT author FROM `$this->table` WHERE ID = %d", $id ) );
			if ( $wpdb->last_error || null === $saved_owner || (int) $saved_owner !== $next_owner ) { return false; }
			if ( false === $wpdb->update( $items, array( 'author' => $next_owner ), array( 'wishlist_id' => $id ) ) ) { return false; }
			if ( false === $wpdb->query( 'COMMIT' ) ) { return false; }
			$transaction = false;
			return true;
		} finally {
			$error = $wpdb->last_error;
			if ( $transaction ) { $wpdb->query( 'ROLLBACK' ); }
			foreach ( array_reverse( $acquired ) as $lock ) { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
			$wpdb->last_error = $error;
		}
	}

	/**
	 * Remove wishlist
	 *
	 * @param integer $id id database wishlist.
	 * @param int|null $expected_owner Owner captured by a scoped cleanup, or null for trusted deletion.
	 *
	 * @return boolean
	 * @global wpdb $wpdb
	 *
	 */
	public function remove( $id, $expected_owner = null, $empty_only = false ) {
		global $wpdb;
		if ( null !== $expected_owner ) {
			$expected_owner = filter_var( $expected_owner, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) );
			if ( false === $expected_owner ) { return false; }
		}
		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}
		$items = $wpdb->prefix . $this->_name . '_items';
		$owner = $wpdb->get_var( $wpdb->prepare( "SELECT author FROM `$this->table` WHERE ID = %d", $id ) );
		if ( $wpdb->last_error ) {
			return false;
		}
		$owner = null === $owner ? null : (int) $owner;
		$locks = $owner > 0 ? array( 'tinvwl_merge_' . md5( $wpdb->prefix . '|' . $owner ) ) : array();
		$locks[] = 'tinvwl_item_' . md5( $items . '|' . $id );
		$acquired = array();
		$transaction = $committed = false;
		try {
			$suppressed = $wpdb->suppress_errors( true );
			try {
				$independent = $wpdb->query( 'SET TRANSACTION READ WRITE' );
			} finally {
				$wpdb->suppress_errors( $suppressed );
			}
			if ( false === $independent ) {
				return false; // Never commit or roll back a caller's transaction.
			}
			$tables = $wpdb->get_results( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name IN (%s, %s)', $this->table, $items ), ARRAY_A );
			if ( $wpdb->last_error || ! is_array( $tables ) || count( $tables ) !== 2 ) {
				return false;
			}
			foreach ( $tables as $table ) {
				if ( ! in_array( strtolower( $table['Engine'] ), array( 'innodb', 'xtradb' ), true ) ) {
					return false;
				}
			}
			// Match ordinary upsert and transfer lock order, including reentrant callers.
			foreach ( $locks as $lock ) {
				if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
					return false;
				}
				$acquired[] = $lock;
			}
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
				return false;
			}
			$transaction = true;
			$wishlist = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$this->table` WHERE ID = %d FOR UPDATE", $id ), ARRAY_A );
			$current_owner = $wishlist ? (int) $wishlist['author'] : null;
			if ( $wpdb->last_error || $current_owner !== $owner || ( null !== $expected_owner && $current_owner !== $expected_owner ) ) {
				return false;
			}
			$products = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM `$items` WHERE wishlist_id = %d ORDER BY ID FOR UPDATE", $id ) );
			if ( $wpdb->last_error || ! is_array( $products ) ) {
				return false;
			}
			if ( $empty_only && ( $products || ! $wishlist || ! $this->is_owner( $wishlist ) ) ) {
				return false;
			}
			// Missing positive IDs remain idempotent successes, including existing orphan cleanup.
			if ( count( $products ) !== $wpdb->delete( $items, array( 'wishlist_id' => $id ) )
				|| ( $wishlist ? 1 : 0 ) !== $wpdb->delete( $this->table, $wishlist ? array( 'ID' => $id, 'author' => $owner ) : array( 'ID' => $id ) ) ) {
				return false;
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				return false;
			}
			$transaction = false;
			$committed = true;
		} finally {
			$error = $wpdb->last_error;
			if ( $transaction ) {
				$wpdb->query( 'ROLLBACK' );
			}
			foreach ( array_reverse( $acquired ) as $lock ) {
				$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
			}
			$wpdb->last_error = $error;
		}
		if ( $committed ) {
			if ( $wishlist && empty( $wishlist['author'] ) && $this->is_owner( $wishlist ) ) {
				TInvWL_Request_Nonce::rotate_guest_identity();
			}
			do_action( 'tinvwl_wishlist_removed', $id );
		}
		return $committed;
	}

	/** Roll back a list created by a failed add request without deleting concurrent additions. */
	public function remove_empty_for_owner( array $wishlist ) {
		if ( empty( $wishlist['ID'] ) || ! isset( $wishlist['author'] ) ) {
			return false;
		}
		return $this->remove( $wishlist['ID'], $wishlist['author'], true );
	}

	/**
	 * Remove an expired guest only after rechecking its current owner and item activity.
	 * Empty lists use their creation date; occupied lists keep the latest item activity.
	 * Candidate IDs from the daily scan are not deletion authority: login may have
	 * adopted the list, or an ordinary guest writer may have added a fresh item.
	 */
	public function remove_expired_guest( $id, $days ) {
		global $wpdb;
		$id = absint( $id );
		$days = absint( $days );
		if ( ! $id ) {
			return false;
		}
		$items = $wpdb->prefix . $this->_name . '_items';
		$lock = 'tinvwl_item_' . md5( $items . '|' . $id );
		$locked = $transaction = $committed = false;
		try {
			$suppressed = $wpdb->suppress_errors( true );
			try {
				$independent = $wpdb->query( 'SET TRANSACTION READ WRITE' );
			} finally {
				$wpdb->suppress_errors( $suppressed );
			}
			if ( false === $independent ) {
				return false; // Never commit or roll back a caller's transaction.
			}
			$tables = $wpdb->get_results( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name IN (%s, %s)', $this->table, $items ), ARRAY_A );
			if ( $wpdb->last_error || ! is_array( $tables ) || count( $tables ) !== 2 ) {
				return false;
			}
			foreach ( $tables as $table ) {
				if ( ! in_array( strtolower( $table['Engine'] ), array( 'innodb', 'xtradb' ), true ) ) {
					return false;
				}
			}
			if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) {
				return false; // An active ordinary writer can be reconsidered by the next scan.
			}
			$locked = true;
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
				return false;
			}
			$transaction = true;
			$wishlist = $wpdb->get_row( $wpdb->prepare( "SELECT ID, author, date, type, status, title, date < DATE_SUB(CURDATE(), INTERVAL %d DAY) AS expired FROM `$this->table` WHERE ID = %d FOR UPDATE", $days, $id ), ARRAY_A );
			if ( $wpdb->last_error || ! $wishlist || (int) $wishlist['author'] !== 0 ) {
				return false;
			}
			$products = $wpdb->get_results( $wpdb->prepare( "SELECT ID, author, date, date < DATE_SUB(CURDATE(), INTERVAL %d DAY) AS expired FROM `$items` WHERE wishlist_id = %d ORDER BY ID FOR UPDATE", $days, $id ), ARRAY_A );
			if ( $wpdb->last_error || ! is_array( $products ) ) {
				return false;
			}
			if ( ! $products ) {
				// Empty named or customized lists may be intentional; only prune automatic defaults.
				if ( 'default' !== $wishlist['type'] || 'share' !== $wishlist['status']
					|| ! in_array( (string) $wishlist['title'], array( '', sanitize_text_field( $this->default_name ) ), true ) ) {
					return false;
				}
				$date = (string) $wishlist['date'];
				$created = DateTime::createFromFormat( '!Y-m-d H:i:s', $date, new DateTimeZone( 'UTC' ) );
				if ( ! $created || $created->format( 'Y-m-d H:i:s' ) !== $date || (int) $created->format( 'Y' ) < 1000 || (string) $wishlist['expired'] !== '1' ) {
					return false; // Unknown/zero dates are never deletion authority.
				}
			}
			foreach ( $products as $product ) {
				if ( (int) $product['author'] !== 0 || (string) $product['expired'] !== '1' || (string) $product['date'] < '1000-01-01 00:00:00' ) {
					return false;
				}
			}
			if ( count( $products ) !== $wpdb->delete( $items, array( 'wishlist_id' => $id ) )
				|| 1 !== $wpdb->delete( $this->table, array( 'ID' => $id, 'author' => 0 ) ) ) {
				return false;
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				return false;
			}
			$transaction = false;
			$committed = true;
		} finally {
			$error = $wpdb->last_error;
			if ( $transaction ) {
				$wpdb->query( 'ROLLBACK' );
			}
			if ( $locked ) {
				$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
			}
			$wpdb->last_error = $error;
		}
		if ( $committed ) {
			do_action( 'tinvwl_wishlist_removed', $id );
		}
		return $committed;
	}

	/**
	 * Keep retry metadata separate for each site's wishlist tables.
	 *
	 * @return string
	 */
	public static function guest_transfer_pending_key()
	{
		$key = '_tinvwl_pending_guest_transfer';
		return is_multisite() ? $key . '_' . get_current_blog_id() : $key;
	}

	/**
	 * Set share key for default wishlist
	 *
	 * @param string $sharekey Sharekey for default wishlist.
	 *
	 * @return string
	 */
	function set_sharekey($sharekey = '')
	{
		global $wpdb, $tinvwl_wishlist_sharekey;

		if (self::is_valid_share_key($sharekey)) {
			$current = $this->get_sharekey();
			if ($current !== $sharekey && is_user_logged_in() && self::is_valid_share_key($current)) {
				$guest = $this->get_by_sharekey_default($current)[0] ?? null;
				if ($wpdb->last_error) {
					return $current; // A failed lookup cannot prove that the signed source is safe to replace.
				}
				if ($guest && empty($guest['author'])) {
					$pending = get_user_meta(get_current_user_id(), self::guest_transfer_pending_key(), true);
					$bound = is_array($pending) && (int) ($pending['user_id'] ?? 0) === get_current_user_id()
						&& (!is_multisite() || (int) ($pending['blog_id'] ?? 0) === get_current_blog_id());
					$sources = $bound ? ($pending['sources'] ?? array($pending)) : array();
					$captured = false;
					foreach ((array) $sources as $source) {
						if (is_array($source) && (int) ($source['ID'] ?? 0) === (int) $guest['ID']
							&& ($source['share_key'] ?? '') === $guest['share_key']) {
							$captured = true;
							break;
						}
					}
					if (!$captured) {
						$items = $wpdb->prefix . $this->_name . '_items';
						$item = $wpdb->get_var($wpdb->prepare("SELECT ID FROM `$items` WHERE wishlist_id = %d LIMIT 1", $guest['ID']));
						if (null !== $item || $wpdb->last_error) {
							return $current; // Retain the only proof while uncaptured source rows may remain.
						}
					}
				}
			}
			self::$default_sharekey = $tinvwl_wishlist_sharekey = $sharekey;
			@setcookie('tinv_wishlistkey', self::$default_sharekey, time() + 31 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
//			set_transient( '_tinvwl_update_wishlists_data', '1' );
		}

		return self::$default_sharekey;
	}

	/**
	 * Get variable for default share key
	 *
	 * @return string
	 */
	function get_sharekey()
	{
		global $tinvwl_wishlist_sharekey;

		if (empty(self::$default_sharekey)) {
			self::$default_sharekey = $tinvwl_wishlist_sharekey = filter_input(INPUT_COOKIE, 'tinv_wishlistkey', FILTER_VALIDATE_REGEXP, array(
				'options' => array(
					'regexp' => '/\A(?:[A-Fa-f0-9]{6}|[A-Fa-f0-9]{32})\z/',
					'default' => $tinvwl_wishlist_sharekey,
				),
			));
		}

		return self::$default_sharekey;
	}
}
