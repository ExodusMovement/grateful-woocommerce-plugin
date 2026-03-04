<?php
/**
 * Plugin Name: Grateful
 * Version: 0.1.3
 * Description: Stablecoin payment gateway
 * Author: Grateful
 * Author URI: https://merchant.grateful.me
 * Text Domain: grateful
 * Domain Path: /languages
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package extension
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MAIN_PLUGIN_FILE' ) ) {
	define( 'MAIN_PLUGIN_FILE', __FILE__ );
}

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

use GratefulPayments\Admin\Setup;
use GratefulPayments\Grateful_Payment_Gateway;
use GratefulPayments\Blocks_Support;

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce fallback notice.
 *
 * @since 0.1.0
 */
function grateful_payments_missing_wc_notice() {
	/* translators: %s WC download URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Grateful Payments requires WooCommerce to be installed and active. You can download %s here.', 'grateful_payments' ), '<a href="https://woo.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

register_activation_hook( __FILE__, 'grateful_payments_activate' );

/**
 * Activation hook.
 *
 * @since 0.1.0
 */
function grateful_payments_activate() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'grateful_payments_missing_wc_notice' );
		return;
	}
}

if ( ! class_exists( 'grateful_payments' ) ) :
	/**
	 * The grateful_payments class.
	 */
	class grateful_payments {
		/**
		 * This class instance.
		 *
		 * @var \grateful_payments single instance of this class.
		 */
		private static $instance;

		/**
		 * Constructor.
		 */
		public function __construct() {
			// Load textdomain
			add_action( 'init', array( $this, 'load_textdomain' ) );

			// Declare HPOS compatibility
			add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );

			// Initialize the plugin after all plugins are loaded
			add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
		}

		/**
		 * Load plugin textdomain
		 */
		public function load_textdomain() {
			load_plugin_textdomain( 'grateful-payments', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
		}

		/**
		 * Declare HPOS compatibility
		 */
		public function declare_hpos_compatibility() {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		}

		/**
		 * Initialize the plugin
		 */
		public function init() {
			// Check if WooCommerce is active
			if ( ! class_exists( 'WooCommerce' ) ) {
				add_action( 'admin_notices', 'grateful_payments_missing_wc_notice' );
				return;
			}

			// Load payment gateway
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );

			// Initialize blocks support only if blocks are available
			add_action( 'woocommerce_blocks_loaded', array( $this, 'init_blocks_support' ) );
			
			// Also try to initialize blocks support immediately if the class already exists
			if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
				$this->init_blocks_support();
			}

			// Initialize admin if in admin area
			if ( is_admin() ) {
				new Setup();
			}
		}

		/**
		 * Initialize blocks support
		 */
		public function init_blocks_support() {
			if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
				new Blocks_Support();
			}
		}

		/**
		 * Add payment gateway to WooCommerce
		 *
		 * @param array $gateways Payment gateways.
		 * @return array
		 */
		public function add_gateway( $gateways ) {
			$gateways[] = Grateful_Payment_Gateway::class;
			return $gateways;
		}

		/**
		 * Cloning is forbidden.
		 */
		public function __clone() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'grateful-payments' ), '0.1.0' );
		}

		/**
		 * Unserializing instances of this class is forbidden.
		 */
		public function __wakeup() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'grateful-payments' ), '0.1.0' );
		}

		/**
		 * Gets the main instance.
		 *
		 * Ensures only one instance can be loaded.
		 *
		 * @return \grateful_payments
		 */
		public static function instance() {

			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}
	}
endif;

// Initialize the plugin
grateful_payments::instance();
