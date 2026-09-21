<?php
/**
 * PayPal — Orders v2 (REST) + JS SDK buttons.
 * Docs: https://developer.paypal.com/docs/api/orders/v2/
 *
 * The buyer authorises inside PayPal's own window; we capture server-side and
 * only then trust the result. PayPal-Request-Id makes the capture idempotent.
 */

require_once __DIR__ . '/http.php';

function pay_paypal_cfg(): array
{
    return pay_credentials('paypal');
}

/** OAuth2 client-credentials token (cached for the request). */
function pay_paypal_token(): ?string
{
    static $token = null;
    if ($token !== null) {
        return $token ?: null;
    }
    $cfg = pay_paypal_cfg();
    $res = pay_http('POST', $cfg['api'] . '/v1/oauth2/token', [
        'basic' => [$cfg['client_id'], $cfg['client_secret']],
        'body'  => ['grant_type' => 'client_credentials'],
    ]);
    $token = ($res['ok'] && !empty($res['json']['access_token'])) ? (string) $res['json']['access_token'] : '';
    if ($token === '') {
        pay_log('paypal', 'auth.error', ['error_message' => pay_http_error($res, 'PayPal authentication failed.')]);
    }
    return $token ?: null;
}

function pay_paypal_create(array $order, array $payment, ?string $method): array
{
    $cfg   = pay_paypal_cfg();
    $token = pay_paypal_token();
    if (!$token) {
        return ['ok' => false, 'message' => 'PayPal is temporarily unavailable.', 'data' => []];
    }

    $cur = strtoupper($payment['currency']);
    $res = pay_http('POST', $cfg['api'] . '/v2/checkout/orders', [
        'json'    => true,
        'bearer'  => $token,
        'headers' => ['PayPal-Request-Id: ' . (string) $payment['idempotency_key']],
        'body'    => [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $order['order_number'],
                'custom_id'    => (string) $order['id'],
                'invoice_id'   => (string) $order['order_number'] . '-' . $payment['id'],
                'description'  => 'Order ' . $order['order_number'],
                'amount'       => [
                    'currency_code' => $cur,
                    'value'         => number_format((float) $payment['amount'], 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'brand_name'          => SITE_NAME,
                'shipping_preference' => 'NO_SHIPPING',
                'user_action'         => 'PAY_NOW',
                'return_url'          => pay_url('payment-return.php?gateway=paypal&payment=' . $payment['id']),
                'cancel_url'          => pay_url('payment-return.php?gateway=paypal&cancel=1&payment=' . $payment['id']),
            ],
        ],
    ]);

    if (!$res['ok'] || empty($res['json']['id'])) {
        $msg = pay_http_error($res, 'Could not start the PayPal payment.');
        pay_log('paypal', 'create.error', [
            'order_id' => $order['id'], 'payment_id' => $payment['id'],
            'error_message' => $msg, 'status' => (string) $res['status'],
        ]);
        return ['ok' => false, 'message' => $msg, 'data' => []];
    }

    $ppOrderId = (string) $res['json']['id'];
    pay_set_gateway_order((int) $payment['id'], $ppOrderId, $ppOrderId);
    pay_log('paypal', 'create.ok', [
        'order_id' => $order['id'], 'payment_id' => $payment['id'], 'reference' => $ppOrderId,
    ]);

    return ['ok' => true, 'message' => '', 'data' => [
        'flow'          => 'paypal',
        'client_id'     => $cfg['client_id'],   // public by design
        'paypal_order_id' => $ppOrderId,
        'currency'      => $cur,
        'sdk_url'       => $cfg['sdk'] . '?client-id=' . rawurlencode($cfg['client_id'])
                            . '&currency=' . rawurlencode($cur) . '&intent=capture',
    ]];
}

/** Capture the approved order, then verify the capture is COMPLETED. */
function pay_paypal_verify(array $payment, array $input): array
{
    $cfg   = pay_paypal_cfg();
    $token = pay_paypal_token();
    if (!$token) {
        return ['ok' => false, 'message' => 'PayPal is temporarily unavailable.', 'status' => 'pending'];
    }

    $ppOrderId = trim((string) ($input['paypal_order_id'] ?? $input['orderID'] ?? $payment['gateway_order_id'] ?? ''));
    if ($ppOrderId === '' || !hash_equals((string) $payment['gateway_order_id'], $ppOrderId)) {
        return ['ok' => false, 'message' => 'This payment does not belong to your order.', 'status' => 'failed'];
    }

    // Capture. A replayed request with the same PayPal-Request-Id is safe.
    $cap = pay_http('POST', $cfg['api'] . '/v2/checkout/orders/' . rawurlencode($ppOrderId) . '/capture', [
        'json'    => true,
        'bearer'  => $token,
        'headers' => ['PayPal-Request-Id: cap_' . (string) $payment['idempotency_key']],
        'body'    => new stdClass(),
    ]);

    $data = $cap['json'] ?? null;

    // ORDER_ALREADY_CAPTURED -> read the order back instead of failing.
    if (!$cap['ok']) {
        $issue = (string) ($data['details'][0]['issue'] ?? '');
        if ($issue !== 'ORDER_ALREADY_CAPTURED') {
            $msg = pay_http_error($cap, 'PayPal could not complete the payment.');
            pay_mark_failed((int) $payment['id'], $msg, $issue ?: 'CAPTURE_FAILED');
            return ['ok' => false, 'message' => pay_failure_message(), 'status' => 'failed'];
        }
        $get = pay_http('GET', $cfg['api'] . '/v2/checkout/orders/' . rawurlencode($ppOrderId), ['bearer' => $token]);
        if (!$get['ok']) {
            return ['ok' => false, 'message' => 'Could not confirm the payment.', 'status' => 'pending'];
        }
        $data = $get['json'];
    }

    $capture = $data['purchase_units'][0]['payments']['captures'][0] ?? null;
    if (!$capture) {
        return ['ok' => false, 'message' => 'Could not confirm the payment.', 'status' => 'pending'];
    }

    $state = (string) ($capture['status'] ?? '');
    if ($state === 'PENDING') {
        return ['ok' => false, 'message' => 'PayPal is still reviewing this payment. We will update your order shortly.', 'status' => 'processing'];
    }
    if ($state !== 'COMPLETED') {
        pay_mark_failed((int) $payment['id'], 'PayPal capture status: ' . $state, $state);
        return ['ok' => false, 'message' => pay_failure_message(), 'status' => 'failed'];
    }

    $paidMinor = pay_to_minor((float) ($capture['amount']['value'] ?? 0), (string) ($capture['amount']['currency_code'] ?? ''));
    $mismatch  = pay_check_amount($payment, $paidMinor, (string) ($capture['amount']['currency_code'] ?? ''));
    if ($mismatch !== '') {
        pay_mark_failed((int) $payment['id'], $mismatch, 'AMOUNT_MISMATCH');
        return ['ok' => false, 'message' => 'Payment amount did not match the order.', 'status' => 'failed'];
    }

    $result = pay_mark_paid((int) $payment['id'], (string) $capture['id'], 'paypal', $ppOrderId);
    return ['ok' => $result['ok'], 'message' => $result['message'], 'status' => 'paid'];
}

function pay_paypal_refund(array $payment, float $amount, string $reason): array
{
    $cfg   = pay_paypal_cfg();
    $token = pay_paypal_token();
    if (!$token) {
        return ['ok' => false, 'message' => 'PayPal is temporarily unavailable.', 'data' => []];
    }
    if (empty($payment['gateway_payment_id'])) {
        return ['ok' => false, 'message' => 'No PayPal capture id on record.', 'data' => []];
    }

    $res = pay_http('POST', $cfg['api'] . '/v2/payments/captures/' . rawurlencode($payment['gateway_payment_id']) . '/refund', [
        'json'    => true,
        'bearer'  => $token,
        'headers' => ['PayPal-Request-Id: rf_' . $payment['id'] . '_' . pay_to_minor($amount, $payment['currency'])],
        'body'    => [
            'amount' => [
                'value'         => number_format($amount, 2, '.', ''),
                'currency_code' => strtoupper($payment['currency']),
            ],
            'note_to_payer' => mb_substr($reason, 0, 120),
        ],
    ]);

    if (!$res['ok'] || empty($res['json']['id'])) {
        return ['ok' => false, 'message' => pay_http_error($res, 'PayPal refused the refund.'), 'data' => []];
    }

    $r = $res['json'];
    return ['ok' => true, 'message' => 'Refund submitted.', 'data' => [
        'refund_id' => (string) $r['id'],
        'status'    => ($r['status'] ?? '') === 'COMPLETED' ? 'completed' : 'processing',
    ]];
}

/**
 * PayPal verifies its own webhook signatures server-side; we forward the
 * transmission headers plus the raw event to their verification endpoint.
 */
function pay_paypal_verify_webhook(string $raw, array $headers): bool
{
    $cfg   = pay_paypal_cfg();
    $token = pay_paypal_token();
    if (!$token || $cfg['webhook_id'] === '') {
        return false;
    }

    $get = static function (array $h, string $k): string {
        foreach ($h as $key => $v) {
            if (strcasecmp($key, $k) === 0) { return (string) $v; }
        }
        return '';
    };

    $event = json_decode($raw, true);
    if (!is_array($event)) {
        return false;
    }

    $res = pay_http('POST', $cfg['api'] . '/v1/notifications/verify-webhook-signature', [
        'json'   => true,
        'bearer' => $token,
        'body'   => [
            'auth_algo'         => $get($headers, 'PAYPAL-AUTH-ALGO'),
            'cert_url'          => $get($headers, 'PAYPAL-CERT-URL'),
            'transmission_id'   => $get($headers, 'PAYPAL-TRANSMISSION-ID'),
            'transmission_sig'  => $get($headers, 'PAYPAL-TRANSMISSION-SIG'),
            'transmission_time' => $get($headers, 'PAYPAL-TRANSMISSION-TIME'),
            'webhook_id'        => $cfg['webhook_id'],
            'webhook_event'     => $event,
        ],
    ]);

    return $res['ok'] && (($res['json']['verification_status'] ?? '') === 'SUCCESS');
}
