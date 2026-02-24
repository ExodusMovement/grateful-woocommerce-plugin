<?php
/**
 * Blocks Support
 *
 * @package GratefulPayments
 */

namespace GratefulPayments;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Grateful Payment Blocks Support
 */
class Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * Payment method name
	 *
	 * @var string
	 */
	protected $name = 'grateful_payment';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'woocommerce_blocks_payment_method_type_registration', array( $this, 'register' ) );
	}

	/**
	 * Register the payment method type
	 *
	 * @param object $payment_method_registry Payment method registry.
	 */
	public function register( $payment_method_registry ) {
		$payment_method_registry->register( $this );
	}

	/**
	 * Initialize the payment method type
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_grateful_payment_settings', array() );
	}

	/**
	 * Returns if this payment method should be active
	 *
	 * @return boolean
	 */
	public function is_active() {
		$gateway = new Grateful_Payment_Gateway();
		return $gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$script_path       = '/build/checkout.js';
		$script_asset_path = plugin_dir_path( MAIN_PLUGIN_FILE ) . 'build/checkout.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => filemtime( plugin_dir_path( MAIN_PLUGIN_FILE ) . $script_path ),
			);
		$script_url        = plugins_url( $script_path, MAIN_PLUGIN_FILE );

		wp_register_script(
			'grateful-payments-checkout',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		wp_set_script_translations(
			'grateful-payments-checkout',
			'grateful-payments',
			plugin_dir_path( MAIN_PLUGIN_FILE ) . 'languages'
		);

		return array( 'grateful-payments-checkout' );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$gateway = new Grateful_Payment_Gateway();

		return array(
			'title'       => $gateway->get_option( 'title', __( 'Grateful - Stablecoins', 'grateful-payments' ) ),
			'description' => $gateway->get_option( 'description', __( 'Take a moment to express gratitude before completing your purchase.', 'grateful-payments' ) ),
			'supports'    => $gateway->supports ?? array( 'products' ),
		);
	}
} 