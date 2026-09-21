# Payment Gateway Setup

Razorpay · Stripe · PayPal · PhonePe UPI · Cash on Delivery
Core PHP + MySQL, no framework, no Composer — every gateway is called through
its official REST API over cURL with TLS verification always on.

---

## 1. Run the migration (once)

```
mysql -u root anand_jewellery < database/payments.sql
```

It extends the existing `orders` and `payments` tables and adds
`payment_events`, `payment_refunds` and `payment_logs`. It is safe to re-run.

## 2. Add your credentials

**Admin → Payment Gateways → API Keys.** Paste your keys and your site URL,
pick the **Test keys** or **Live keys** tab, and save.

* The form writes straight into `config/payment.local.php` — the same
  protected file you would edit by hand. **Nothing is stored in the database.**
* The file is **git-ignored**, blocked from the web (`config/.htaccess`), and
  refuses to execute unless the application loads it.
* **Secret fields are write-only.** They render empty and show only
  "Configured" / "Not set"; the stored value is never sent back to the browser.
  Leave a secret blank to keep what is already saved, or tick *Remove this
  value* to clear it. Publishable ids (Razorpay key id, Stripe publishable key,
  PayPal client id, PhonePe merchant id) are shown because the gateways
  themselves publish them in the browser.
* Test and Live are **separate key sets**. Switching the mode swaps which set
  is used — you never edit code to go live.
* Prefer not to use the form at all? Copy the template and edit it by hand:
  ```
  copy config\payment.local.example.php config\payment.local.php
  ```
  Environment variables of the same names also work, and are used whenever the
  file has no value for a key.
* If the page says the file is **not writable**, give the web server write
  permission on `config/payment.local.php` (or on `config/`), or edit the file
  by hand — the form will not save otherwise.

**Site URL** is the `APP_URL` field at the top of the form. Set it to the
public address of the shop (e.g. `https://www.yourshop.com`, or your ngrok
tunnel while testing). Gateways use it to build return and webhook URLs; leave
it blank only on localhost, where it is auto-detected.

| Gateway  | Where to get the keys |
|----------|------------------------|
| Razorpay | dashboard.razorpay.com → Settings → API Keys |
| Stripe   | dashboard.stripe.com → Developers → API keys |
| PayPal   | developer.paypal.com → Apps & Credentials |
| PhonePe  | business.phonepe.com merchant dashboard |

## 3. Register the webhooks

Admin → **Payment Gateways** prints the exact URL for each gateway. Paste each
into the matching dashboard and subscribe to these events:

| Gateway  | URL | Events |
|----------|-----|--------|
| Razorpay | `/webhooks/razorpay.php` | `payment.captured`, `payment.failed`, `refund.processed` |
| Stripe   | `/webhooks/stripe.php`   | `payment_intent.succeeded`, `payment_intent.payment_failed`, `payment_intent.canceled`, `charge.refunded` |
| PayPal   | `/webhooks/paypal.php`   | `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.DENIED`, `PAYMENT.CAPTURE.REFUNDED` |
| PhonePe  | `/webhooks/phonepe.php`  | set automatically as the `callbackUrl` on every payment |

Webhooks need a publicly reachable HTTPS URL. On localhost use a tunnel
(ngrok / cloudflared) and set `APP_URL` in `config/payment.local.php` to the
tunnel address so the gateways build the right return URLs.

## 4. Switch on the gateways

Admin → **Payment Gateways**:

* **Mode** — TEST or LIVE.
* **Store currency** — a gateway only appears at checkout when it settles this
  currency. PayPal does not settle INR, so it stays hidden on an INR store.
* **Offer at checkout** — per-gateway on/off.

A gateway reaches the checkout only when it is switched on **and** its
credentials exist for the current mode **and** it settles the store currency.
LIVE mode additionally requires HTTPS.

---

## How the money flow works

```
customer picks a gateway
        │
        ▼
ajax/payment/create.php ──► creates the ORDER (no stock taken yet)
        │                   opens a PAYMENT attempt
        │                   asks the gateway for its own order/intent
        ▼
gateway's own hosted UI  (Razorpay Checkout / Stripe Payment Element /
        │                 PayPal Buttons / PhonePe page)
        ▼
ajax/payment/verify.php ──► re-asks the GATEWAY what really happened
        │                   checks amount + currency against the order
        ▼
pay_mark_paid() ──► pay_finalize_order()
                    · reduce stock
                    · consume the coupon
                    · clear the cart
                    · order → paid / confirmed
```

`webhooks/*.php` runs the same `pay_mark_paid()` path independently, so the
order still completes if the customer closes the browser mid-payment.

### The guarantees

* **Nothing sensitive is ever collected by this server.** Card numbers, CVV,
  card PINs and UPI PINs are typed into the gateway's own component. We store
  only the gateway's opaque payment/order ids.
* **A frontend "success" is never trusted.** The browser result only triggers a
  server-side re-check against the gateway API.
* **Stock is never reduced before payment is verified.** `placeOrder()` creates
  the order and nothing else; fulfilment happens once, later.
* **Fulfilment can only happen once per order.** It is claimed by an atomic
  `UPDATE orders SET fulfilled_at = NOW() WHERE id = ? AND fulfilled_at IS NULL`,
  so a verify call and a webhook racing each other cannot double-decrement stock.
* **Replayed webhooks are ignored.** `payment_events` has `UNIQUE(gateway,
  event_id)`; the second delivery of an event is a no-op that still returns 200.
* **Amount and currency are re-checked** against the order before any payment is
  marked paid — a tampered amount is rejected and logged.
* **Every signature is verified**: Razorpay HMAC-SHA256, Stripe `t=`/`v1=` with a
  5-minute tolerance, PayPal's `verify-webhook-signature` API, PhonePe's
  `SHA256(payload + saltKey)###saltIndex`.
* **Logs are redacted.** `pay_log()` drops any context key matching
  card / cvv / pin / secret / password / token / salt / key / authoriz before
  writing to `payment_logs`.
* **The browser only ever receives publishable ids** (`rzp_…`, `pk_…`, PayPal
  client id) via `pay_public_config()`, which whitelists them explicitly.

---

## Day-to-day admin

| Task | Where |
|------|-------|
| All transactions, filter by gateway/status/date | **Sales → Payments** |
| One order's gateway ids, attempts and activity log | **Sales → Orders → Manage** |
| Refund (full or partial) | the order's **Refunds** panel |
| API keys, site URL, mode, currency, on/off switches, webhook URLs | **Insights → Payment Gateways** |

Refunds are sent to the gateway first and only recorded once it accepts. A full
refund returns the goods to stock exactly once, through the same guarded path a
cancellation uses.

A failed or abandoned payment leaves the order in place; the customer can retry
from **My Orders**, or you can send them to `checkout.php?retry=<order id>`.

---

## Files

```
config/payment.php                  gateway matrix, credential + mode resolution
config/payment.local.php            YOUR KEYS — git-ignored, web-blocked
includes/payments/http.php          hardened cURL wrapper (TLS verify always on)
includes/payments/manager.php       state machine, fulfilment, logging, refunds
includes/payments/{razorpay,stripe,paypal,phonepe}.php   per-gateway REST drivers
ajax/payment/{create,verify,status,cancel}.php           storefront endpoints
webhooks/{razorpay,stripe,paypal,phonepe}.php            server-to-server events
payment-return.php                  return handler for redirect flows
assets/js/checkout-payment.js       checkout controller (SDKs + AJAX)
assets/css/payment.css              checkout + return-page styling
admin/payments.php                  transaction list
admin/payment-settings.php          mode, currency, gateway switches
ajax/admin/refund.php               admin refund endpoint
database/payments.sql               migration
```
