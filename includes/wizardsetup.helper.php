<?php
/**
 * Wizard installation plugin helper
 *
 * @since             1.0.0
 * @package           TInvWishlist
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Wizard installation plugin helper.
 */
class TInvWL_WizardSetup {
	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	private string $_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private string $_version;

	/**
	 * TInvWL_WizardSetup constructor.
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $version Plugin version.
	 */
	public function __construct( string $plugin_name, string $version ) {
		$this->_name    = $plugin_name;
		$this->_version = $version;
		add_action( 'init', [ $this, 'load' ] );
		add_action( 'admin_init', [ $this, 'redirect' ] );
	}

	/**
	 * Setup trigger for show wizard installation.
	 */
	public static function setup(): void {
		set_transient( '_tinvwl_activation_redirect', 1, 30 );
	}

	/**
	 * Load wizard.
	 */
	public function load(): void {
		$page = filter_input( INPUT_GET, 'page' );
		if ( ! empty( $page ) && 'tinvwl-wizard' === $page ) {
			new TInvWL_Wizard( $this->_name, $this->_version );
		}
	}

	/**
	 * Apply redirect to wizard.
	 */
	public function redirect(): void {
		if ( ! get_transient( '_tinvwl_activation_redirect' ) ) {
			return;
		}
		delete_transient( '_tinvwl_activation_redirect' );

		$page     = filter_input( INPUT_GET, 'page' );
		$activate = filter_input( INPUT_GET, 'activate-multi' );

		if ( in_array( $page, [ 'tinvwl-wizard' ], true ) ||
		     is_network_admin() ||
		     ! is_null( $activate ) ||
		     apply_filters( 'tinvwl_prevent_automatic_wizard_redirect', false ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'index.php?page=tinvwl-wizard' ) );
		exit;
	}
}
