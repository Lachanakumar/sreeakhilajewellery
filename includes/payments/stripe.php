<?php
/**
 * Stripe — PaymentIntents + Payment Element.
 * Docs: https://stripe.com/docs/api/payment_intents  |  /docs/payments/payment-element
 *
 * Card data is entered inside Stripe's iframed Payment Element and is never
 * posted to this server. 3-D Secure / SCA is handled by stripe.js, which
 * returns here only after the intent reaches a terminal state.
 */

require_once __DIR__ . '/http.php';

function pay_stripe_cfg(): array
{
    return pay_credentials('stripe');
}

/** Stripe wants x-www-form-urlencoded with PHP-style bracket nesting. */
function pay_stripe_form(array $data, string $prefix = ''): array
{
    $out = [];
    foreach ($data as $k => $v) {
        $key = $prefix === '' ? (string) $k : $prefix . '[' . $k . ']';
        if (is_array($v)) {
            $out += pay_stripe_form($v, $key);
        } elseif (is_bool($v)) {
            $out[$key] = $v ? 'true' : 'false';
        } elseif ($v !== null) {
            $out[$key] = (string) $v;
        }
    }
    return $out;
}

function pay_stripe_create(array $order, array $payment, ?string $method): array
{
    $cfg = pay_stripe_cfg();
    $cur = strtoupper($payment['currency']);

    $body = pay_stripe_form([
        'amount'   => pay_to_minor($payment['amount'], $cur),
        'currency' => strtolower($cur),
        'automatic_payment_methods' => ['enabled' => true],
        'description' => 'Order ' . $order['order_number'] . ' — ' . SITE_NAME,
        'metadata' => [
            'order_id'     => (string) $order['id'],
            'order_number' => (string) $order['order_number'],
            'payment_id'   => (string) $payment['id'],
        ],
    ]);

    $res = pay_http('POST', $cfg['api'] . '/payment_intents', [
        'bearer'  => $cfg['secret_key'],
        'body'    => $body,
        // Same key on a retry returns the SAME intent instead of a second charge.
        'headers' => ['Idempotency-Key: ' . (string) $payment['idempotency_key']],
    ]);

    if (!$res['ok'] || empty($res['json']['id']) || empty($res['json']['client_secret'])) {
        $msg = pay_http_error($res, 'Could not start the Stripe payment.');
        pay_log('stripe', 'create.error', [
            'order_id' => $order['id'], 'payment_id' => $payment['id'],
            'error_message' => $msg, 'status' => (string) $res['status'],
        ]);
        return ['ok' => false, 'message' => $msg, 'data' => []];
    }

    $intentId = (string) $res['json']['id'];
    pay_set_gateway_order((int) $payment['id'], $intentId, $intentId);
    pay_log('stripe', 'create.ok', [
        'order_id' => $order['id'], 'payment_id' => $payment['id'], 'reference' => $intentId,
    ]);

    return ['ok' => true, 'message' => '', 'data' => [
        'flow'            => 'stripe',
        'publishable_key' => $cfg['publishable_key'],  // pk_ — safe in the browser
        'client_secret'   => (string) $res['json']['client_secret'],
        'intent_id'       => $intentId,
        'amount'          => pay_to_minor($payment['amount'], $cur),
        'currency'        => $cur,
        'return_url'      => pay_url('payment-return.php?gateway=stripe&payment=' . $payment['id']),
    ]];
}

/**
 * Confirm against the Stripe API. We ignore whatever the browser claims and
 * read the PaymentIntent's real status.
 */
function pay_stripe_verify(array $payment, array $input): array
{
    $cfg      = pay_stripe_cfg();
    $intentId = (string) ($input['payment_intent'] ?? $payment['gateway_order_id'] ?? '');

    if ($intentId === '' || !hash_equals((string) $payment['gateway_order_id'], $intentId)) {
        return ['ok' => false, 'message' => 'This payment does not belong to your order.', 'status' => 'failed'];
    }

    $res = pay_http('GET', $cfg['api'] . '/payment_intents/' . rawurlencode($intentId), [
        'bearer' => $cfg['secret_key'],
    ]);
    if (!$res['ok'] || empty($res['json']['status'])) {
        return ['ok' => false, 'message' => pay_http_error($res, 'Could not confirm the payment.'), 'status' => 'pending'];
    }

    $pi     = $res['json'];
    $status = (string) $pi['status'];

    if ($status === 'requires_action' || $status === 'processing') {
        return ['ok' => false, 'message' => 'Your bank is still confirming this payment. We will update the order shortly.', 'status' => 'processing'];
    }
    if ($status === 'canceled') {
        pay_mark_cancelled((int) $payment['id'], 'Cancelled at Stripe');
        return ['ok' => false, 'message' => 'Payment was cancelled. Your order has not been charged.', 'status' => 'cancelled'];
    }
    if ($status !== 'succeeded') {
        $reason = (string) ($pi['last_payment_error']['message'] ?? 'Payment was not completed.');
        pay_mark_failed((int) $payment['id'], $reason, (string) ($pi['last_payment_error']['code'] ?? $status));
        return ['ok' => false, 'message' => pay_failure_message(), 'status' => 'failed'];
    }

    $mismatch = pay_check_amount($payment, (int) ($pi['amount_received'] ?? $pi['amount']), (string) $pi['currency']);
    if ($mismatch !== '') {
        pay_mark_failed((int) $payment['id'], $mismatch, 'AMOUNT_MISMATCH');
        return ['ok' => false, 'message' => 'Payment amount did not match the order.', 'status' => 'failed'];
    }

    $charge = (string) ($pi['latest_charge'] ?? $intentId);
    $brand  = $pi['charges']['data'][0]['payment_method_details']['type'] ?? 'card';

    $result = pay_mark_paid((int) $payment['id'], $charge, (string) $brand, $intentId);
    return ['ok' => $result['ok'], 'message' => $result['message'], 'status' => 'paid'];
}

function pay_stripe_refund(array $payment, float $amount, string $reason): array
{
    $cfg = pay_stripe_cfg();
    if (empty($payment['gateway_order_id'])) {
        return ['ok' => false, 'message' => 'No Stripe PaymentIntent on record.', 'data' => []];
    }

    $res = pay_http('POST', $cfg['api'] . '/refunds', [
        'bearer'  => $cfg['secret_key'],
        'headers' => ['Idempotency-Key: rf_' . $payment['id'] . '_' . pay_to_minor($amount, $payment['currency'])],
        'body'    => pay_stripe_form([
            'payment_intent' => (string) $payment['gateway_order_id'],
            'amount'         => pay_to_minor($amount, $payment['currency']),
            'metadata'       => ['reason' => mb_substr($reason, 0, 200)],
        ]),
    ]);

    if (!$res['ok'] || empty($res['json']['id'])) {
        return ['ok' => false, 'message' => pay_http_error($res, 'Stripe refused the refund.'), 'data' => []];
    }

    $r = $res['json'];
    return ['ok' => true, 'message' => 'Refund submitted.', 'data' => [
        'refund_id' => (string) $r['id'],
        'status'    => ($r['status'] ?? '') === 'succeeded' ? 'completed' : 'processing',
    ]];
}

/**
 * Webhook signature: header is `t=<ts>,v1=<sig>`; the signed payload is
 * "<ts>.<raw body>" HMAC-SHA256'd with the endpoint secret.
 * Also enforces a 5-minute tolerance to block replay.
 */
function pay_stripe_verify_webhook(string $raw, string $header): bool
{
    $cfg = pay_stripe_cfg();
    if ($cfg['webhook_secret'] === '' || $header === '') {
        return false;
    }

    $ts = null; $sigs = [];
    foreach (explode(',', $header) as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) !== 2) { continue; }
        if ($kv[0] === 't') { $ts = $kv[1]; }
        if ($kv[0] === 'v1') { $sigs[] = $kv[1]; }
    }
    if ($ts === null || !$sigs) {
        return false;
    }
    if (abs(time() - (int) $ts) > 300) {
        return false;
    }

    $expected = hash_hmac('sha256', $ts . '.' . $raw, $cfg['webhook_secret']);
    foreach ($sigs as $sig) {
        if (hash_equals($expected, $sig)) {
            return true;
        }
    }
    return false;
}
