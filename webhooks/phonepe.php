<?php
/**
 * PhonePe S2S callback.  Registered as `callbackUrl` on every /pg/v1/pay call.
 * Body: {"response":"<base64 json>"}   Header: X-VERIFY: sha256(base64+salt)###index
 */

require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/../includes/payments/phonepe.php';

hook_require_post();
hook_guard_size();

$raw  = hook_raw_body();
$body = json_decode($raw, true);

// PhonePe posts JSON, but tolerate a form-encoded `response` too.
$encoded = (string) ($body['response'] ?? $_POST['response'] ?? '');
if ($encoded === '') {
    hook_respond(400, 'Malformed payload');
}

if (!pay_phonepe_verify_webhook($encoded, hook_header('X-VERIFY'))) {
    pay_log('phonepe', 'webhook.bad_signature', ['error_message' => 'Checksum verification failed']);
    hook_respond(400, 'Invalid checksum');
}

$decoded = json_decode(base64_decode($encoded, true) ?: '', true);
if (!is_array($decoded) || empty($decoded['code'])) {
    hook_respond(400, 'Malformed payload');
}

$code  = (string) $decoded['code'];
$data  = $decoded['data'] ?? [];
$txnId = (string) ($data['merchantTransactionId'] ?? '');

if ($txnId === '') {
    hook_respond(400, 'Missing transaction reference');
}

// PhonePe has no event id, so build a stable one from the txn + outcome.
$eventId = 'php_' . $txnId . '_' . $code;

if (!pay_claim_event('phonepe', $eventId, $code, [
    'gateway_payment_id' => $data['transactionId'] ?? null,
    'signature_valid'    => true,
])) {
    hook_respond(200, 'Duplicate event ignored');
}

$payment = pay_find_by_gateway_order('phonepe', $txnId);
if (!$payment) {
    pay_mark_event_processed('phonepe', $eventId, 'No matching payment');
    hook_respond(200, 'No matching payment');
}

switch ($code) {
    case 'PAYMENT_SUCCESS':
        $mismatch = pay_check_amount($payment, isset($data['amount']) ? (int) $data['amount'] : null, 'INR');
        if ($mismatch !== '') {
            pay_log('phonepe', 'webhook.amount_mismatch', [
                'order_id' => $payment['order_id'], 'payment_id' => $payment['id'],
                'error_message' => $mismatch,
            ]);
            pay_mark_event_processed('phonepe', $eventId, 'Amount mismatch');
            hook_respond(200, 'Amount mismatch recorded');
        }
        pay_mark_paid(
            (int) $payment['id'],
            (string) ($data['transactionId'] ?? $txnId),
            strtolower((string) ($data['paymentInstrument']['type'] ?? 'upi')),
            $txnId
        );
        break;

    case 'PAYMENT_PENDING':
        getDB()->prepare("UPDATE payments SET status = 'processing' WHERE id = ? AND status <> 'paid'")
               ->execute([(int) $payment['id']]);
        break;

    case 'PAYMENT_CANCELLED':
        pay_mark_cancelled((int) $payment['id'], 'Cancelled in PhonePe');
        break;

    default: // PAYMENT_ERROR, PAYMENT_DECLINED, TIMED_OUT, ...
        pay_mark_failed(
            (int) $payment['id'],
            (string) ($decoded['message'] ?? 'Payment failed at PhonePe'),
            $code
        );
        break;
}

pay_mark_event_processed('phonepe', $eventId, $code);
hook_respond(200, 'Processed');
