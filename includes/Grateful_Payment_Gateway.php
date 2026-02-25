<?php
/**
 * Grateful Payment Gateway Class
 *
 * @package GratefulPayments
 */

namespace GratefulPayments;

defined( 'ABSPATH' ) || exit;

/**
 * Grateful Payment Gateway
 */
class Grateful_Payment_Gateway extends \WC_Payment_Gateway {

	/**
	 * API Key
	 *
	 * @var string
	 */
	public $api_key;

	/**
	 * Secret Key
	 *
	 * @var string
	 */
	public $secret_key;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                 = 'grateful_payment';
		$this->icon 							= apply_filters( 'woocommerce_gateway_icon', 'https://merchant.grateful.me/favicon-32x32.png' );
		$this->has_fields         = false;
		$this->method_title       = __( 'Grateful - Stablecoins', 'grateful-payments' );
		$this->method_description = __( 'Accept stablecoins directly, with no fees or intermediaries.', 'grateful-payments' );
		$this->supports           = array(
			'products',
			'refunds',
		);

		$this->init_form_fields();
		$this->init_settings();

		$this->enabled    = $this->get_option( 'enabled', 'yes' );
		$this->title = 'Grateful - Stablecoins';
  	$this->description = 'Take a moment to express gratitude before completing your purchase. This practice helps cultivate mindfulness and appreciation.';
		$this->api_key    = $this->get_option( 'api_key' );
		$this->secret_key = $this->get_option( 'secret_key' );



		// Hooks.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_' . $this->id, array( $this, 'webhook' ) );
		add_action( 'woocommerce_api_grateful_payment_return', array( $this, 'handle_return' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Initialize form fields
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'      => array(
				'title'   => __( 'Enable/Disable', 'grateful-payments' ),
				'label'   => __( 'Enable Grateful Payment', 'grateful-payments' ),
				'type'    => 'checkbox',
				'description' => '',
				'default' => 'yes',
			),
			'api_key'      => array(
				'title'       => __( 'API Key', 'grateful-payments' ),
				'type'        => 'password',
				'description' => __( 'Enter your Grateful API key. This is used to create payments in Grateful with checkout data from WooCommerce.', 'grateful-payments' ),
				'default'     => '',
				'desc_tip'    => true,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'secret_key'   => array(
				'title'       => __( 'Secret Key', 'grateful-payments' ),
				'type'        => 'password',
				'description' => __( 'Enter the secret key from your Grateful dashboard for signature verification.', 'grateful-payments' ),
				'default'     => '',
				'desc_tip'    => true,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'webhook_url'  => array(
				'title'       => __( 'Notification URL', 'grateful-payments' ),
				'type'        => 'text',
				'description' => __( 'Add this URL to your Grateful dashboard notifications settings to receive payment status updates.', 'grateful-payments' ),
				'default'     => home_url( '/wc-api/grateful_payment' ),
				'custom_attributes' => array(
					'readonly' => 'readonly',
				),
			),
		);
	}

	/**
	 * Check if API key is configured
	 *
	 * @return bool
	 */
	public function is_api_key_configured() {
		return ! empty( $this->api_key );
	}

	/**
	 * Process payment
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment($order_id) {
		$order = wc_get_order($order_id);
		
		// Create payment in Grateful and redirect user
		$order_data = array(
				'total' => $order->get_total(),
				'order_id' => $order_id,
				'callback_url' => home_url('/wc-api/grateful_payment_return?order_id=' . $order_id),
		);
		
		$grateful_payment = $this->create_grateful_payment($order_data);
		
		if ($grateful_payment && isset($grateful_payment['url'])) {
				// Payment created successfully in Grateful, redirect user to Grateful
				$order->update_status('pending', 'Payment created in Grateful. Redirecting user to complete payment. Payment ID: ' . ($grateful_payment['id'] ?? 'N/A'));
				
				// Store Grateful payment ID in order meta for reference
				if (isset($grateful_payment['id'])) {
						$order->update_meta_data('_grateful_payment_id', $grateful_payment['id']);
						$order->save();
				}
				
				// Empty cart
				WC()->cart->empty_cart();
				
				return array(
						'result'   => 'success',
						'redirect' => $grateful_payment['url']
				);
		} else {
				error_log('Grateful Payment: Failed to create payment in Grateful');
				// Failed to create payment in Grateful
				$order->update_status('failed', 'Failed to create payment in Grateful. Please check API key and try again.');
				
				return array(
						'result'   => 'failure',
						'redirect' => wc_get_checkout_url()
				);
		}
}

	/**
	 * Handle webhook from Grateful
	 */
	public function webhook() {
		$body = file_get_contents( 'php://input' );
		$data = json_decode( $body, true );

		if ( ! $data ) {
			http_response_code( 400 );
			exit;
		}

		// Verify webhook signature if available.
		$signature = $_SERVER['HTTP_X_GRATEFUL_SIGNATURE'] ?? null;
		if ( $signature && $this->secret_key ) {
			$expected_signature = hash_hmac( 'sha256', $body, $this->secret_key );
			if ( ! hash_equals( $expected_signature, $signature ) ) {
				http_response_code( 401 );
				exit;
			}
		}

		// Extract order ID from external reference.
		$external_reference_id = $data['externalReferenceId'] ?? $data['external_reference_id'] ?? null;
		if ( ! $external_reference_id ) {
			http_response_code( 400 );
			exit;
		}

		$order = wc_get_order( $external_reference_id );
		if ( ! $order ) {
			http_response_code( 404 );
			exit;
		}

		// Process the webhook based on status.
		$status = $data['status'] ?? '';
		switch ( $status ) {
			case 'completed':
			case 'success':
				$order->payment_complete();
				$order->add_order_note( __( 'Payment completed via Grateful Payment.', 'grateful-payments' ) );
				break;

			case 'failed':
			case 'error':
				$order->update_status( 'failed', __( 'Payment failed in Grateful.', 'grateful-payments' ) );
				break;

			case 'pending':
				$order->update_status( 'pending', __( 'Payment is pending in Grateful.', 'grateful-payments' ) );
				break;
		}

		http_response_code( 200 );
		exit;
	}

    /**
     * Handle return from Grateful payment
     */
    public function handle_return() {
			// Get the order ID from the URL
			$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
			$payment_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
			
			if (!$order_id) {
					wp_redirect(wc_get_checkout_url());
					exit;
			}
			
			$order = wc_get_order($order_id);
			if (!$order) {
					wp_redirect(wc_get_checkout_url());
					exit;
			}
			
			// Check if this is a Grateful payment order
			$grateful_payment_id = $order->get_meta('_grateful_payment_id');
			if (!$grateful_payment_id) {
					wp_redirect(wc_get_checkout_url());
					exit;
			}

			// NEW: Validate order status using Grateful API
			$api_status = $this->get_grateful_payment_status($grateful_payment_id);
			
			if ($api_status) {
					// Use API status instead of URL parameter
					$validated_status = $api_status['status'] ?? $payment_status;
					
					// Update order status based on API response if needed
					$this->update_order_from_api_status($order, $api_status);
					
					// Handle return based on validated status
					switch (strtolower($validated_status)) {
							case 'success':
									wp_redirect($this->get_return_url($order));
									break;
									
							case 'expired':
							case 'failed':
									wp_redirect(wc_get_checkout_url());
									break;
									
							case 'pending':
							case 'processing':
									wp_redirect($this->get_return_url($order));
									break;
									
							default:
									// Unknown status - fallback to URL parameter or redirect to thank you page
									error_log('Grateful Payment Return: Unknown API status "' . $validated_status . '" for order ' . $order_id);
									wp_redirect($this->get_return_url($order));
									break;
					}
			} else {
					error_log('Grateful Payment Return: API status validation failed for order ' . $order_id . ', falling back to URL parameter');
					
					// Fallback to original URL parameter logic if API call fails
					switch ($payment_status) {
							case 'success':
									wp_redirect($this->get_return_url($order));
									break;
									
							case 'expired':
							case 'failed':
									wp_redirect(wc_get_checkout_url());
									break;
									
							default:
									wp_redirect($this->get_return_url($order));
									break;
					}
			}
			
			exit;
	}

	/**
	 * Get payment status from Grateful API
	 */
	public function get_grateful_payment_status($payment_id) {
			$api_key = $this->get_api_key();
			
			if (!$api_key || !$payment_id) {
					error_log('Grateful Payment: API key or payment ID missing for status check');
					return false;
			}
			
			$status_url = 'https://merchant.grateful.me/api/payments/' . $payment_id . '/status';
			
			$response = wp_remote_get($status_url, array(
					'headers' => array(
							'Content-Type' => 'application/json',
							'x-api-key' => $api_key,
					),
					'timeout' => 15,
			));
			
			if (is_wp_error($response)) {
					error_log('Grateful Payment Status API Error: ' . $response->get_error_message());
					return false;
			}
			
			$response_code = wp_remote_retrieve_response_code($response);
			$body = wp_remote_retrieve_body($response);
			$data = json_decode($body, true);
			
			if ($response_code !== 200) {
					error_log('Grateful Payment Status API Error: HTTP ' . $response_code . ' - ' . $body);
					return false;
			}
			
			if (!$data || !isset($data['status'])) {
					error_log('Grateful Payment Status API Error: Invalid response format');
					return false;
			}
			
			return $data;
	}

	/**
	 * Update order status based on API response
	 */
	private function update_order_from_api_status($order, $api_status) {
			$payment_status = strtolower($api_status['status'] ?? '');
			$payment_id = $order->get_meta('_grateful_payment_id');
			$current_status = $order->get_status();
			
			switch ($payment_status) {
					case 'success':
							$order->payment_complete($payment_id);
							$order->add_order_note('Payment status confirmed via API. Payment ID: ' . $payment_id);
							break;
							
					case 'failed':
							$order->update_status('failed', 'Payment failed confirmed via API. Payment ID: ' . $payment_id);
							break;
							
					case 'pending':
					case 'processing':
							$order->update_status('pending', 'Payment pending confirmed via API. Payment ID: ' . $payment_id);
							break;
			}
			
			$order->save();
	}

	    /**
     * Get API Key securely for payment processing
     * This method ensures the API key is only accessible from the backend
     */
	public function get_api_key() {
		// Return the API key - it's needed for payment processing
		return $this->api_key;
}

	/**
	 * Create payment in Grateful
	 *
	 * @param array $order_data Order data.
	 * @return array|false
	 */
	public function create_grateful_payment($order_data) {
		$api_key = $this->get_api_key();
		
		if (!$api_key) {
				error_log('Grateful Payment: API key not available or not accessible from frontend');
				return false;
		}
		
		// Prepare the payment data for Grateful API
		$payment_data = array(
				'fiatAmount' => $order_data['total'],
				'fiatCurrency' => get_woocommerce_currency(),
				'externalReferenceId' => (string) $order_data['order_id'],
				'callbackUrl' => $order_data['callback_url'],
		);
		
		$response = wp_remote_post('https://merchant.grateful.me/api/payments/new', array(
				'headers' => array(
						'Content-Type' => 'application/json',
						'x-api-key' => $api_key,
				),
				'body' => json_encode($payment_data),
				'timeout' => 30,
		));
		
		if (is_wp_error($response)) {
				error_log('Grateful Payment API Error: ' . $response->get_error_message());
				return false;
		}
		
		$response_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);
		
		if ($response_code !== 200) {
				error_log('Grateful Payment API Error: HTTP ' . $response_code . ' - ' . $body);
				return false;
		}
		
		// Check if we have the required URL field in the response
		if (!isset($data['url'])) {
				error_log('Grateful Payment API Error: Missing "url" field in response');
				return false;
		}
		
		return $data;
}

	/**
	 * Display admin notices
	 */
	public function admin_notices() {
		if ( 'yes' === $this->enabled && ! $this->is_api_key_configured() ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'Grateful Payment is enabled but no API key has been set. Please configure your API key in the payment settings.', 'grateful-payments' );
			echo '</p></div>';
		}
	}

	/**
	 * Process refund
	 *
	 * @param int    $order_id Order ID.
	 * @param float  $amount   Refund amount.
	 * @param string $reason   Refund reason.
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		// Get the Grateful payment ID from order meta.
		$grateful_payment_id = $order->get_meta( '_grateful_payment_id' );

		if ( ! $grateful_payment_id ) {
			return new \WP_Error( 'grateful_refund_error', __( 'Grateful payment ID not found for this order.', 'grateful-payments' ) );
		}

		// Implement refund logic with Grateful API here.
		// This is a placeholder - replace with actual Grateful API integration.

		return true;
	}
} 