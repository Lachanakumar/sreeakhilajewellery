<?php
/**
 * PhonePe PG — hosted UPI checkout (redirect flow).
 * Docs: https://developer.phonepe.com/v1/reference/pay-api
 *
 * Every call is signed with X-VERIFY: SHA256(payload + endpoint + saltKey)###saltIndex.
 * The UPI PIN is entered in the PhonePe app — nothing sensitive touches us.
 */

require_once __DIR__ . '/http.php';

function pay_phonepe_cfg(): array
{
    return pay_credentials('phonepe');
}

/** X-VERIFY checksum for a request. */
function pay_phonepe_checksum(string $payloadOrEmpty, string $endpoint): string
{
    $cfg = pay_phonepe_cfg();
    return hash('sha256', $payloadOrEmpty . $endpoint . $cfg['salt_key']) . '###' . $cfg['salt_index'];
}

/**
 * merchantTransactionId must be unique per attempt and <= 38 chars.
 * Order id + payment id + short random keeps it unique across retries.
 */
function pay_phonepe_txn_id(array $order, array $payment): string
{
    $base = 'AJ' . $order['id'] . 'P' . $payment['id'] . strtoupper(bin2hex(random_bytes(5)));
    return substr($base, 0, 38);
}

function pay_phonepe_create(array $order, array $payment, ?string $method): array
{
    $cfg = pay_phonepe_cfg();
    if (strtoupper($payment['currency']) !== 'INR') {
        return ['ok' => false, 'message' => 'PhonePe supports INR payments only.', 'data' => []];
    }

    $txnId    = $payment['gateway_order_id'] ?: pay_phonepe_txn_id($order, $payment);
    $endpoint = '/pg/v1/pay';

    $payload = [
        'merchantId'            => $cfg['merchant_id'],
        'merchantTransactionId' => $txnId,
        'merchantUserId'        => 'CUST' . (int) $order['user_id'],
        'amount'                => pay_to_minor($payment['amount'], 'INR'), // paise
        'redirectUrl'           => pay_url('payment-return.php?gateway=phonepe&payment=' . $payment['id']),
        'redirectMode'          => 'REDIRECT',
        'callbackUrl'           => pay_url('webhooks/phonepe.php'),
        'mobileNumber'          => preg_replace('/\D/', '', (string) $order['shipping_phone']),
        'paymentInstrument'     => ['type' => 'PAY_PAGE'],
    ];

    $encoded = base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));

    $res = pay_http('POST', $cfg['api'] . $endpoint, [
        'json'    => true,
        'body'    => ['request' => $encoded],
        'headers' => ['X-VERIFY: ' . pay_phonepe_checksum($encoded, $endpoint)],
    ]);

    $redirect = $res['json']['data']['instrumentResponse']['redirectInfo']['url'] ?? '';
    if (!$res['ok'] || empty($res['json']['success']) || $redirect === '') {
        $msg = (string) ($res['json']['message'] ?? pay_http_error($res, 'Could not start the PhonePe payment.'));
        pay_log('phonepe', 'create.error', [
            'order_id' => $order['id'], 'payment_id' => $payment['id'],
            'reference' => $txnId, 'error_message' => $msg, 'status' => (string) $res['status'],
        ]);
        return ['ok' => false, 'message' => $msg, 'data' => []];
    }

    pay_set_gateway_order((int) $payment['id'], $txnId, $txnId);
    pay_log('phonepe', 'create.ok', [
        'order_id' => $order['id'], 'payment_id' => $payment['id'], 'reference' => $txnId,
    ]);

    return ['ok' => true, 'message' => '', 'data' => [
        'flow'         => 'redirect',
        'redirect_url' => $redirect,
        'txn_id'       => $txnId,
    ]];
}

/**
 * PhonePe is redirect-based: the browser tells us nothing trustworthy, so we
 * always ask PhonePe for the authoritative status.
 */
function pay_phonepe_verify(array $payment, array $input): array
{
    $cfg   = pay_phonepe_cfg();
    $txnId = (string) ($payment['gateway_order_id'] ?? '');
    if ($txnId === '') {
        return ['ok' => false, 'message' => 'No PhonePe transaction on record.', 'status' => 'failed'];
    }

    $endpoint = '/pg/v1/status/' . $cfg['merchant_id'] . '/' . $txnId;
    $res = pay_http('GET', $cfg['api'] . $endpoint, [
        'headers' => [
            'X-VERIFY: ' . pay_phonepe_checksum('', $endpoint),
            'X-MERCHANT-ID: ' . $cfg['merchant_id'],
        ],
    ]);

    if (!$res['ok'] || !isset($res['json']['code'])) {
        return ['ok' => false, 'message' => 'Could not confirm the payment with PhonePe.', 'status' => 'pending'];
    }

    $code = (string) $res['json']['code'];
    $data = $res['json']['data'] ?? [];

    if ($code === 'PAYMENT_PENDING') {
        getDB()->prepare("UPDATE payments SET status = 'processing' WHERE id = ? AND status <> 'paid'")
               ->execute([(int) $payment['id']]);
        return ['ok' => false, 'message' => 'Your UPI payment is still being confirmed. We will update the order shortly.', 'status' => 'processing'];
    }

    if ($code !== 'PAYMENT_SUCCESS') {
        $reason = (string) ($res['json']['message'] ?? 'Payment was not completed.');
        if ($code === 'PAYMENT_CANCELLED') {
            pay_mark_cancelled((int) $payment['id'], 'Cancelled in PhonePe');
            return ['ok' => false, 'message' => 'Payment was cancelled. Your order has not been charged.', 'status' => 'cancelled'];
        }
        pay_mark_failed((int) $payment['id'], $reason, $code);
        return ['ok' => false, 'message' => pay_failure_message(), 'status' => 'failed'];
    }

    $mismatch = pay_check_amount($payment, isset($data['amount']) ? (int) $data['amount'] : null, 'INR');
    if ($mismatch !== '') {
        pay_mark_failed((int) $payment['id'], $mismatch, 'AMOUNT_MISMATCH');
        return ['ok' => false, 'message' => 'Payment amount did not match the order.', 'status' => 'failed'];
    }

    $gwPaymentId = (string) ($data['transactionId'] ?? $txnId);
    $instrument  = (string) ($data['paymentInstrument']['type'] ?? 'UPI');

    $result = pay_mark_paid((int) $payment['id'], $gwPaymentId, strtolower($instrument), $txnId);
    return ['ok' => $result['ok'], 'message' => $result['message'], 'status' => 'paid'];
}

function pay_phonepe_refund(array $payment, float $amount, string $reason): array
{
    $cfg = pay_phonepe_cfg();
    if (empty($payment['gateway_order_id'])) {
        return ['ok' => false, 'message' => 'No PhonePe transaction on record.', 'data' => []];
    }

    $endpoint  = '/pg/v1/refund';
    $refundTxn = substr('RF' . $payment['id'] . strtoupper(bin2hex(random_bytes(6))), 0, 38);

    $payload = [
        'merchantId'                 => $cfg['merchant_id'],
        'merchantUserId'             => 'CUST' . (int) $payment['user_id'],
        'originalTransactionId'      => (string) $payment['gateway_order_id'],
        'merchantTransactionId'      => $refundTxn,
        'amount'                     => pay_to_minor($amount, 'INR'),
        'callbackUrl'                => pay_url('webhooks/phonepe.php'),
    ];
    $encoded = base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));

    $res = pay_http('POST', $cfg['api'] . $endpoint, [
        'json'    => true,
        'body'    => ['request' => $encoded],
        'headers' => ['X-VERIFY: ' . pay_phonepe_checksum($encoded, $endpoint)],
    ]);

    if (!$res['ok'] || empty($res['json']['success'])) {
        return ['ok' => false, 'message' => (string) ($res['json']['message'] ?? 'PhonePe refused the refund.'), 'data' => []];
    }

    $state = (string) ($res['json']['code'] ?? '');
    return ['ok' => true, 'message' => 'Refund submitted.', 'data' => [
        'refund_id' => $refundTxn,
        'status'    => $state === 'PAYMENT_SUCCESS' ? 'completed' : 'processing',
    ]];
}

/** Callback signature: SHA256(base64Response + saltKey)###saltIndex in X-VERIFY. */
function pay_phonepe_verify_webhook(string $base64Response, string $header): bool
{
    $cfg = pay_phonepe_cfg();
    if ($cfg['salt_key'] === '' || $header === '') {
        return false;
    }
    $expected = hash('sha256', $base64Response . $cfg['salt_key']) . '###' . $cfg['salt_index'];
    return hash_equals($expected, trim($header));
}
