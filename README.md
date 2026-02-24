# Grateful for WooCommerce

## Installation

1. Upload the plugin files to `/wp-content/plugins/grateful-woocommerce-plugin`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the payment provider in WooCommerce > Settings > Payments

## Grateful Account Setup

Before configuring the plugin, you'll need to set up your Grateful account and create an integration:

1. **Create Account**: Sign up for a Grateful account at [grateful.me](https://merchant.grateful.me)
2. **Access Dashboard**: Log in to your Grateful dashboard
3. **Create Integration**: Navigate to [Integrations](https://merchant.grateful.me/settings/integrations) and create a new integration for your WooCommerce store. Integration type should be Online. You'll need to configure the notification URL later (provided in WooCommerce settings)
4. **Get API Credentials**: Copy your API key and secret key from the integration settings

Keep your API credentials secure and ready for the next configuration step.

## Configuration

1. Go to **WooCommerce > Settings > Payments**
2. Find "Grateful" and click "Manage"
3. Enable the provider
4. Enter your Grateful API key and secret key
5. Copy the notification URL displayed in the settings page and configure it in your Grateful dashboard
6. Save changes

## Payment Flow

1. **Order Placement**: Customer places an order and selects Grateful
2. **Payment Creation**: WooCommerce creates a payment in Grateful using the API
3. **Redirection**: Customer is redirected to Grateful checkout flow to complete payment
4. **Payment**: Customer completes the payment in Grateful
5. **Return**: Customer is redirected back to WooCommerce
6. **Order Completion**: Order status is updated based on payment result

## API Integration

The plugin integrates with the Grateful.me API:

- **Endpoint**: `https://merchant.grateful.me/api/payments/new`
- **Method**: POST
- **Headers**: 
  - `Content-Type: application/json`
  - `x-api-key: YOUR_API_KEY`

### Request Payload
```json
{
  "fiatAmount": 99.99,
  "fiatCurrency": "USD",
  "externalReferenceId": "123",
  "callbackUrl": "https://yoursite.com/wc-api/grateful_payment_return?order_id=123"
}
```

### Response
```json
{
  "id": "payment_id",
  "url": "https://merchant.grateful.me/payment/...",
  "status": "pending"
}
```
