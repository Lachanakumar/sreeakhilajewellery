<?php
/**
 * Razorpay — Orders API + Checkout.js.
 * Docs: https://razorpay.com/docs/api/orders/  |  /docs/payments/payment-gateway/web-integration/standard/
 *
 * Supports UPI, cards, net banking and wallets (chosen inside Razorpay's own
 * hosted Checkout — no card data ever reaches this server).
 */

require_once __DIR__ . '/http.php';

function pay_razorpay_cfg(): array
{
    return pay_credentials('razorpay');
}

/** Create a Razorpay Order so Checkout can be opened against it. */
function pay_razorpay_create(array $order, array $payment, ?string $method): array
{
    $cfg = pay_razorpay_cfg();
    $cur = strtoupper($payment['currency']);

    $res = pay_http('POST', $cfg['api'] . '/orders', [
        'json'  => true,
        'basic' => [$cfg['key_id'], $cfg['key_secret']],
        'body'  => [
            'amount'          => pay_to_minor($payment['amount'], $cur), // paise
            'currency'        => $cur,
            'receipt'         => (string) $order['order_number'],
            'payment_capture' => 1,
            'notes'           => [
                'order_id'     => (string) $order['id'],
                'order_number' => (string) $order['order_number'],
            ],
        ],
    ]);

    if (!$res['ok'] || empty($res['json']['id'])) {
        $msg = pay_http_error($res, 'Could not start the Razorpay payment.');
        pay_log('razorpay', 'create.error', [
            'order_id' => $order['id'], 'payment_id' => $payment['id'],
            'error_message' => $msg, 'status' => (string) $res['status'],
        ]);
        return ['ok' => false, 'message' => $msg, 'data' => []];
    }

    $rzpOrderId = (string) $res['json']['id'];
    pay_set_gateway_order((int) $payment['id'], $rzpOrderId, $rzpOrderId);
    pay_log('razorpay', 'create.ok', [
        'order_id' => $order['id'], 'payment_id' => $payment['id'], 'reference' => $rzpOrderId,
    ]);

    return ['ok' => true, 'message' => '', 'data' => [
        'flow'        => 'razorpay',
        'key'         => $cfg['key_id'],           // publishable — safe in the browser
        'rzp_order_id'=> $rzpOrderId,
        'amount'      => pay_to_minor($payment['amount'], $cur),
        'currency'    => $cur,
        'name'        => SITE_NAME,
        'description' => 'Order ' . $order['order_number'],
        'prefill'     => [
            'name'    => (string) $order['shipping_name'],
            'contact' => (string) $order['shipping_phone'],
            'email'   => (string) ($order['customer_email'] ?? ''),
        ],
        'method'      => $method,
    ]];
}

/**
 * Verify the Checkout handshake, then confirm with the Razorpay API.
 * The signature alone is not enough — we also re-fetch the payment so the
 * amount/currency/status come from Razorpay, not the browser.
 */
function pay_razorpay_verify(array $payment, array $input): array
{
    $cfg = pay_razorpay_cfg();
    $rzpOrderId   = trim((string) ($input['razorpay_order_id'] ?? ''));
    $rzpPaymentId = trim((string) ($input['razorpay_payment_id'] ?? ''));
    $signature    = trim((string) ($input['razorpay_signature'] ?? ''));

    if ($rzpOrderId === '' || $rzpPaymentId === '' || $signature === '') {
        return ['ok' => false, 'message' => 'Incomplete payment response.', 'status' => 'failed'];
    }
    if (!hash_equals((string) $payment['gateway_order_id'], $rzpOrderId)) {
        return ['ok' => false, 'message' => 'This payment does not belong to your order.', 'status' => 'failed'];
    }

    $expected = hash_hmac('sha256', $rzpOrderId . '|' . $rzpPaymentId, $cfg['key_secret']);
    if (!hash_equals($expected, $signature)) {
        pay_mark_failed((int) $payment['id'], 'Signature verification failed', 'SIGNATURE_MISMATCH');
        return ['ok' => false, 'message' => 'We could not verify this payment.', 'status' => 'failed'];
    }

    // Authoritative check straight from Razorpay.
    $res = pay_http('GET', $cfg['api'] . '/payments/' . rawurlencode($rzpPaymentId), [
        'basic' => [$cfg['key_id'], $cfg['key_secret']],
    ]);
    if (!$res['ok'] || empty($res['json']['status'])) {
        return ['ok' => false, 'message' => pay_http_error($res, 'Could not confirm the payment.'), 'status' => 'pending'];
    }

    $p     = $res['json'];
    $state = (string) $p['status'];

    if ($state === 'authorized') {
        // Capture explicitly if auto-capture did not run.
        $cap = pay_http('POST', $cfg['api'] . '/payments/' . rawurlencode($rzpPaymentId) . '/capture', [
            'json'  => true,
            'basic' => [$cfg['key_id'], $cfg['key_secret']],
            'body'  => ['amount' => (int) $p['amount'], 'currency' => (string) $p['currency']],
        ]);
        if ($cap['ok'] && !empty($cap['json']['status'])) {
            $p     = $cap['json'];
            $state = (string) $p['status'];
        }
    }

    if ($state !== 'captured') {
        $reason = (string) ($p['error_description'] ?? 'Payment was not completed.');
        pay_mark_failed((int) $payment['id'], $reason, (string) ($p['error_code'] ?? $state));
        return ['ok' => false, 'message' => pay_failure_message(), 'status' => 'failed'];
    }

    $mismatch = pay_check_amount($payment, (int) $p['amount'], (string) $p['currency']);
    if ($mismatch !== '') {
        pay_mark_failed((int) $payment['id'], $mismatch, 'AMOUNT_MISMATCH');
        return ['ok' => false, 'message' => 'Payment amount did not match the order.', 'status' => 'failed'];
    }

    $result = pay_mark_paid((int) $payment['id'], $rzpPaymentId, (string) ($p['method'] ?? null), $rzpOrderId);
    return ['ok' => $result['ok'], 'message' => $result['message'], 'status' => 'paid'];
}

function pay_razorpay_refund(array $payment, float $amount, string $reason): array
{
    $cfg = pay_razorpay_cfg();
    if (empty($payment['gateway_payment_id'])) {
        return ['ok' => false, 'message' => 'No Razorpay payment id on record.', 'data' => []];
    }

    $res = pay_http('POST', $cfg['api'] . '/payments/' . rawurlencode($payment['gateway_payment_id']) . '/refund', [
        'json'  => true,
        'basic' => [$cfg['key_id'], $cfg['key_secret']],
        'body'  => [
            'amount' => pay_to_minor($amount, $payment['currency']),
            'notes'  => ['reason' => mb_substr($reason, 0, 200)],
        ],
    ]);

    if (!$res['ok'] || empty($res['json']['id'])) {
        return ['ok' => false, 'message' => pay_http_error($res, 'Razorpay refused the refund.'), 'data' => []];
    }

    $r      = $res['json'];
    $status = ($r['status'] ?? 'processed') === 'processed' ? 'completed' : 'processing';
    return ['ok' => true, 'message' => 'Refund submitted.', 'data' => [
        'refund_id' => (string) $r['id'],
        'status'    => $status,
    ]];
}

/** Webhook signature: HMAC-SHA256 of the raw body with the webhook secret. */
function pay_razorpay_verify_webhook(string $raw, string $signature): bool
{
    $cfg = pay_razorpay_cfg();
    if ($cfg['webhook_secret'] === '' || $signature === '') {
        return false;
    }
    return hash_equals(hash_hmac('sha256', $raw, $cfg['webhook_secret']), $signature);
}
