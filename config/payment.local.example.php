<?php
/**
 * COPY THIS FILE TO  config/payment.local.php  AND FILL IN YOUR KEYS.
 *
 *   copy config\payment.local.example.php config\payment.local.php
 *
 * config/payment.local.php is git-ignored and blocked from the web by
 * config/.htaccess (`Require all denied`). Never commit real keys.
 *
 * You only need the TEST keys to start; LIVE keys are read when
 * Admin -> Payments -> Mode is switched to LIVE.
 */

// Only pay_local_secrets() may load this file.
if (!defined('AJ_CONFIG_LOADER')) {
    http_response_code(404);
    exit;
}

return [
    // Absolute public URL of the site. Required in LIVE so the gateways can
    // build correct return/webhook URLs. Leave blank to auto-detect locally.
    'APP_URL' => '',

    // ------------------------------------------------------------------
    // Razorpay — dashboard.razorpay.com -> Settings -> API Keys
    // ------------------------------------------------------------------
    'RAZORPAY_KEY_ID_TEST'         => '',   // rzp_test_xxxxxxxx
    'RAZORPAY_KEY_SECRET_TEST'     => '',
    'RAZORPAY_WEBHOOK_SECRET_TEST' => '',   // Settings -> Webhooks -> secret

    'RAZORPAY_KEY_ID_LIVE'         => '',   // rzp_live_xxxxxxxx
    'RAZORPAY_KEY_SECRET_LIVE'     => '',
    'RAZORPAY_WEBHOOK_SECRET_LIVE' => '',

    // ------------------------------------------------------------------
    // Stripe — dashboard.stripe.com -> Developers -> API keys
    // ------------------------------------------------------------------
    'STRIPE_PUBLISHABLE_KEY_TEST' => '',    // pk_test_xxxx  (safe in the browser)
    'STRIPE_SECRET_KEY_TEST'      => '',    // sk_test_xxxx  (server only)
    'STRIPE_WEBHOOK_SECRET_TEST'  => '',    // whsec_xxxx

    'STRIPE_PUBLISHABLE_KEY_LIVE' => '',
    'STRIPE_SECRET_KEY_LIVE'      => '',
    'STRIPE_WEBHOOK_SECRET_LIVE'  => '',

    // ------------------------------------------------------------------
    // PayPal — developer.paypal.com -> Apps & Credentials
    // ------------------------------------------------------------------
    'PAYPAL_CLIENT_ID_TEST'     => '',
    'PAYPAL_CLIENT_SECRET_TEST' => '',
    'PAYPAL_WEBHOOK_ID_TEST'    => '',      // from the webhook you register

    'PAYPAL_CLIENT_ID_LIVE'     => '',
    'PAYPAL_CLIENT_SECRET_LIVE' => '',
    'PAYPAL_WEBHOOK_ID_LIVE'    => '',

    // ------------------------------------------------------------------
    // PhonePe — business.phonepe.com merchant dashboard
    // ------------------------------------------------------------------
    'PHONEPE_MERCHANT_ID_TEST' => '',       // e.g. PGTESTPAYUAT
    'PHONEPE_SALT_KEY_TEST'    => '',
    'PHONEPE_SALT_INDEX_TEST'  => '1',

    'PHONEPE_MERCHANT_ID_LIVE' => '',
    'PHONEPE_SALT_KEY_LIVE'    => '',
    'PHONEPE_SALT_INDEX_LIVE'  => '1',
];
