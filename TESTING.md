# Local Testing Guide

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) running
- Node.js 18+ and npm
- A Grateful merchant account at [merchant.grateful.me](https://merchant.grateful.me)

---

## 1. Install dependencies and build

```bash
npm install
npm run build
```

---

## 2. Start the local WordPress environment

```bash
npx @wordpress/env start
```

First run takes a few minutes to pull Docker images. Subsequent starts are fast.

| URL | Credentials |
|-----|-------------|
| Site: http://localhost:8888 | — |
| Admin: http://localhost:8888/wp-admin | `admin` / `password` |

The plugin and WooCommerce are automatically installed and activated.

---

## 3. Complete WooCommerce setup

WooCommerce requires a store address and currency before it will process payments.

1. Go to **WooCommerce > Settings > General**
2. Set a store address (any address works for testing)
3. Set **Currency** to `USD` (or whichever currency your Grateful integration uses)
4. Click **Save changes**

---

## 4. Configure the Grateful plugin

### Get your API credentials

1. Log in to [merchant.grateful.me](https://merchant.grateful.me)
2. Go to **Settings > Integrations** and create a new integration
   - Type: **Online**
   - Name: anything (e.g. "Local Test")
3. Copy the **API Key** and **Secret Key**

### Enter credentials in WooCommerce

1. Go to **WooCommerce > Settings > Payments**
2. Find **Grateful - Stablecoins** and click **Manage**
3. Check **Enable Grateful Payment**
4. Paste your **API Key** and **Secret Key**
5. Copy the **Notification URL** shown on that page — you'll need it in the next step
6. Click **Save changes**

### Configure the webhook in Grateful

The local environment runs at `localhost:8888`, which Grateful's servers can't reach directly. To receive webhooks you need a tunnel:

```bash
# Using ngrok (install from https://ngrok.com)
ngrok http 8888
```

ngrok will give you a public URL like `https://abc123.ngrok.io`. Use it to build the notification URL:

```
https://abc123.ngrok.io/wc-api/grateful_payment
```

Paste this into your Grateful integration's **Notification URL** field and save.

> If you don't set up a tunnel, the payment flow still works — orders will redirect back correctly — but the webhook won't fire and order status won't auto-update to "Processing". You can update it manually in WooCommerce > Orders.

---

## 5. Create a test product

1. Go to **Products > Add New**
2. Give it a name (e.g. "Test Product") and set a price (e.g. `10.00`)
3. Click **Publish**

---

## 6. Test the full checkout flow

1. Visit http://localhost:8888 and add the test product to your cart
2. Go to **Checkout**
3. Fill in billing details (any valid-looking data works)
4. Select **Grateful - Stablecoins** as the payment method
5. Click **Place Order**

You should be redirected to the Grateful payment page. Complete the payment there.

After payment:
- You'll be redirected back to the WooCommerce order confirmation page
- The order status in **WooCommerce > Orders** should update to **Processing** (via webhook) or remain **Pending** if no tunnel is configured

---

## 7. Test the webhook manually

If you can't use ngrok, you can trigger the webhook manually with curl to verify the handler works:

```bash
# Replace 123 with your actual WooCommerce order ID
ORDER_ID=123
SECRET_KEY=your_secret_key_here
PAYLOAD='{"status":"completed","externalReferenceId":"'$ORDER_ID'"}'
SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET_KEY" | awk '{print $2}')

curl -X POST http://localhost:8888/wc-api/grateful_payment \
  -H "Content-Type: application/json" \
  -H "X-Grateful-Signature: $SIGNATURE" \
  -d "$PAYLOAD"
```

Expected: HTTP 200 and the order status changes to **Processing**.

---

## 8. Stopping and resetting the environment

```bash
# Stop containers (preserves data)
npx @wordpress/env stop

# Restart
npx @wordpress/env start

# Full reset — wipes all data and reinstalls from scratch
npx @wordpress/env destroy
npx @wordpress/env start
```

---

## Troubleshooting

**Plugin not appearing in WooCommerce > Payments**
- Make sure `npm run build` has been run — the block checkout script must exist at `build/checkout.js`
- Check **Plugins > Installed Plugins** and confirm "Grateful" is active
- Check **WooCommerce > Settings > General** has a store address set

**"Failed to create payment" error at checkout**
- Verify your API key is correct in **WooCommerce > Settings > Payments > Grateful**
- Check WP debug log: `npx @wordpress/env run cli wp eval 'echo WP_CONTENT_DIR;'` to find the log path, then look at `wp-content/debug.log`

**Order stays in Pending after returning from Grateful**
- This is expected if no webhook tunnel is set up — see step 4
- Manually update the order status in **WooCommerce > Orders**

**Environment won't start**
- Make sure Docker Desktop is running
- Try `npx @wordpress/env destroy` then `npx @wordpress/env start` for a clean slate
