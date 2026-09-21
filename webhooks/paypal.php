<?php
/**
 * PayPal webhook.  Developer dashboard -> Webhooks -> <site>/webhooks/paypal.php
 * Subscribe to: PAYMENT.CAPTURE.COMPLETED, PAYMENT.CAPTURE.DENIED,
 *               PAYMENT.CAPTURE.REFUNDED, CHECKOUT.ORDER.APPROVED
 */

require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/../includes/payments/paypal.php';

hook_require_post();
hook_guard_size();

$raw = hook_raw_body();

// PayPal verifies its own signature server-side.
if (!pay_paypal_verify_webhook($raw, hook_headers())) {
    pay_log('paypal', 'webhook.bad_signature', ['error_message' => 'Signature verification failed']);
    hook_respond(400, 'Invalid signature');
}

$event = json_decode($raw, true);
if (!is_array($event) || empty($event['id']) || empty($event['event_type'])) {
    hook_respond(400, 'Malformed payload');
}

$eventId  = (string) $event['id'];
$type     = (string) $event['event_type'];
$resource = $event['resource'] ?? [];

if (!pay_claim_event('paypal', $eventId, $type, [
    'gateway_payment_id' => $resource['id'] ?? null,
    'signature_valid'    => true,
])) {
    hook_respond(200, 'Duplicate event ignored');
}

/**
 * Locate our payment. A capture resource links back to the PayPal order via
 * supplementary_data; custom_id carries our own order id as a fallback.
 */
$captureId = (string) ($resource['id'] ?? '');
$ppOrderId = (string) ($resource['supplementary_data']['related_ids']['order_id'] ?? '');
$customId  = (string) ($resource['custom_id'] ?? '');

$payment = null;
if ($captureId !== '') {
    $payment = pay_find_by_gateway_payment('paypal', $captureId);
}
if (!$payment && $ppOrderId !== '') {
    $payment = pay_find_by_gateway_order('paypal', $ppOrderId);
}
if (!$payment && ctype_digit($customId)) {
    $payment = pay_payment_for_order((int) $customId);
    if ($payment && $payment['gateway'] !== 'paypal') {
        $payment = null;
    }
}

if (!$payment) {
    pay_mark_event_processed('paypal', $eventId, 'No matching payment');
    hook_respond(200, 'No matching payment');
}

switch ($type) {
    case 'PAYMENT.CAPTURE.COMPLETED':
        $value    = (float) ($resource['amount']['value'] ?? 0);
        $currency = (string) ($resource['amount']['currency_code'] ?? '');
        $mismatch = pay_check_amount($payment, pay_to_minor($value, $currency), $currency);
        if ($mismatch !== '') {
            pay_log('paypal', 'webhook.amount_mismatch', [
                'order_id' => $payment['order_id'], 'payment_id' => $payment['id'],
                'error_message' => $mismatch,
            ]);
            pay_mark_event_processed('paypal', $eventId, 'Amount mismatch');
            hook_respond(200, 'Amount mismatch recorded');
        }
        pay_mark_paid((int) $payment['id'], $captureId, 'paypal', $ppOrderId ?: (string) $payment['gateway_order_id']);
        break;

    case 'PAYMENT.CAPTURE.DENIED':
    case 'PAYMENT.CAPTURE.DECLINED':
        pay_mark_failed((int) $payment['id'], 'Payment denied by PayPal', $type);
        break;

    case 'PAYMENT.CAPTURE.REFUNDED':
        $fresh = pay_get_payment((int) $payment['id']);
        pay_record_refund(
            $fresh,
            (float) ($resource['amount']['value'] ?? 0),
            $captureId,
            'Refund confirmed by PayPal',
            null,
            'completed'
        );
        break;
}

pay_mark_event_processed('paypal', $eventId, $type);
hook_respond(200, 'Processed');
