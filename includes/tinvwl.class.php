<?php
/**
 * Run plugin class
 *
 * @since             1.0.0
 * @package           TInvWishlist
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
	die;
}

/**
 * Run plugin class
 */
class TInvWL
{

	/**
	 * Plugin name
	 *
	 * @var string
	 */
	private $_name;
	/**
	 * Plugin version
	 *
	 * @var string
	 */
	private $_version;
	/**
	 * Admin class
	 *
	 * @var TInvWL_Admin_TInvWL
	 */
	public $object_admin;
	/**
	 * Public class
	 *
	 * @var TInvWL_Public_TInvWL
	 */
	public $object_public;
	/**
	 * Array of deprecated hook handlers.
	 *
	 * @var array of WC_Deprecated_Hooks
	 */
	public $deprecated_hook_handlers = array();

	/**
	 * Constructor
	 * Created admin and public class
	 */
	function __construct()
	{
		$this->_name = TINVWL_PREFIX;
		$this->_version = TINVWL_FVERSION;

		$this->set_locale();
		$this->maybe_update();
		$this->load_function();
		$this->define_hooks();
		$this->object_admin = new TInvWL_Admin_TInvWL($this->_name, $this->_version);

		// Allow to disable wishlist for frontend conditionally. Must be hooked on 'plugins_loaded' action.
		if (apply_filters('tinvwl_load_frontend', true)) {
			$this->object_public = TInvWL_Public_TInvWL::instance($this->_name, $this->_version);
		}
	}

	/**
	 * Run plugin
	 */
	function run()
	{
		if (is_null(get_option($this->_name . '_db_ver', null))) {
			TInvWL_Activator::activate();
		}

		$this->rename();

		TInvWL_View::_init($this->_name, $this->_version);
		TInvWL_Form::_init($this->_name);

		if (is_admin()) {
			new TInvWL_WizardSetup($this->_name, $this->_version);
			new TInvWL_Export($this->_name, $this->_version);
			$this->object_admin->load_function();
		} else {
			// Allow to disable wishlist for frontend conditionally. Must be hooked on 'plugins_loaded' action.
			if (apply_filters('tinvwl_load_frontend', true)) {
				$this->object_public->load_function();
			}
		}

		$this->deprecated_hook_handlers['actions'] = new TInvWL_Deprecated_Actions();
		$this->deprecated_hook_handlers['filters'] = new TInvWL_Deprecated_Filters();
		$this->rest_api = TInvWL_API::init();
	}


	/**
	 * Rename "wishlist" word across the plugin.
	 */
	private function rename()
	{
		$this->rename = tinv_get_option('rename', 'rename');
		$this->rename_single = tinv_get_option('rename', 'rename_single');
		$this->rename_plural = tinv_get_option('rename', 'rename_plural');

		if ($this->rename && $this->rename_single) {
			add_filter('gettext', array($this, 'translations'), 999, 3);
			add_filter('ngettext', array($this, 'translations_n'), 999, 5);
		}
	}


	function translations_n($translation, $single, $plural, $number, $domain)
	{
		return $this->translation_update($translation, $domain);
	}

	function translations($translation, $text, $domain)
	{
		return $this->translation_update($translation, $domain);
	}

	private function translation_update($text, $domain)
	{
		if ('ti-woocommerce-wishlist' === $domain) {

			$translations = ['wishlist' => [$this->rename_single, $this->rename_plural ? $this->rename_plural : $this->rename_single . 's']];

			$text = preg_replace_callback('~\b[a-z]+(?:(?<=(s)))?~i', function ($m) use ($translations) {
				$lower = strtolower($m[0]);
				$rep = $m[0];
				if (isset($translations[$lower])) {
					$rep = is_array($translations[$lower]) ? $translations[$lower][0] : $translations[$lower];
				} elseif (isset($m[1])) {
					$sing = substr($lower, 0, -1);
					if (isset($translations[$sing]))
						$rep = is_array($translations[$sing]) ? $translations[$sing][1] : $translations[$sing] . 's';
				} else {
					return $rep;
				}

				if ($m[0] == $lower)
					return $rep;
				elseif ($m[0] == strtoupper($lower))
					return strtoupper($rep);
				elseif ($m[0] == ucfirst($lower))
					return ucfirst($rep);

				return $rep;
			}, $text);

		}
		return $text;
	}


	/**
	 * Set localization
	 */
	private function set_locale()
	{
		if (function_exists('determine_locale')) {
			$locale = determine_locale();
		} else {
			$locale = is_admin() && function_exists('get_user_locale') ? get_user_locale() : get_locale();
		}

		$locale = apply_filters('plugin_locale', $locale, TINVWL_DOMAIN);

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
	 * Define hooks
	 */
	function define_hooks()
	{
		add_filter('plugin_action_links_' . plugin_basename(TINVWL_PATH . 'ti-woocommerce-wishlist.php'), array(
			$this,
			'action_links',
		));
		add_action('after_setup_theme', 'tinvwl_set_utm', 100);
	}

	/**
	 * Load function
	 */
	function load_function()
	{
	}

	/**
	 * Testing for the ability to update the functional
	 */
	function maybe_update()
	{
		$prev = get_option($this->_name . '_ver');
		if (false === $prev) {
			add_option($this->_name . '_ver', $this->_version);
			$prev = $this->_version;
		}
		if (version_compare($this->_version, $prev, 'gt')) {
			TInvWL_Activator::update();
			new TInvWL_Update($this->_version, $prev);
			update_option($this->_name . '_ver', $this->_version);
			do_action('tinvwl_updated', $this->_version, $prev);
		}
	}

	/**
	 * Action_links function.
	 *
	 * @access public
	 *
	 * @param mixed $links Links.
	 *
	 * @return array
	 */
	public function action_links($links)
	{
		$plugin_links[] = '<a href="' . admin_url('admin.php?page=tinvwl') . '">' . __('Settings', 'ti-woocommerce-wishlist') . '</a>';
		$plugin_links[] = '<a target="_blank" href="https://templateinvaders.com/product/ti-woocommerce-wishlist-wordpress-plugin/?utm_source=' . TINVWL_UTM_SOURCE . '&utm_campaign=' . TINVWL_UTM_CAMPAIGN . '&utm_medium=' . TINVWL_UTM_MEDIUM . '&utm_content=action_link&partner=' . TINVWL_UTM_SOURCE . '" style="color:#46b450;font-weight:700;">' . __('Premium Version', 'ti-woocommerce-wishlist') . '</a>';
		$plugin_links[] = '<a target="_blank" href="https://woocommercewishlist.com/preview/?utm_source=' . TINVWL_UTM_SOURCE . '&utm_campaign=' . TINVWL_UTM_CAMPAIGN . '&utm_medium=' . TINVWL_UTM_MEDIUM . '&utm_content=action_link&partner=' . TINVWL_UTM_SOURCE . '"  style="color:#515151">' . __('Live Demo', 'ti-woocommerce-wishlist') . '</a>';

		return array_merge($links, $plugin_links);
	}
}
