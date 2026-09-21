<?php
/**
 * Razorpay webhook.  Dashboard -> Settings -> Webhooks -> <site>/webhooks/razorpay.php
 * Subscribe to: payment.captured, payment.failed, refund.processed
 */

require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/../includes/payments/razorpay.php';

hook_require_post();
hook_guard_size();

$raw = hook_raw_body();
$sig = hook_header('X-Razorpay-Signature');

if (!pay_razorpay_verify_webhook($raw, $sig)) {
    pay_log('razorpay', 'webhook.bad_signature', ['error_message' => 'Signature verification failed']);
    hook_respond(400, 'Invalid signature');
}

$event = json_decode($raw, true);
if (!is_array($event) || empty($event['event'])) {
    hook_respond(400, 'Malformed payload');
}

$type    = (string) $event['event'];
$entity  = $event['payload']['payment']['entity'] ?? [];
$refundE = $event['payload']['refund']['entity'] ?? [];
$eventId = hook_header('X-Razorpay-Event-Id') ?: ('rzp_' . hash('sha256', $raw));

// Duplicate delivery? Acknowledge and stop.
if (!pay_claim_event('razorpay', $eventId, $type, [
    'gateway_payment_id' => $entity['id'] ?? null,
    'signature_valid'    => true,
])) {
    hook_respond(200, 'Duplicate event ignored');
}

$gwPaymentId = (string) ($entity['id'] ?? '');
$gwOrderId   = (string) ($entity['order_id'] ?? '');

$payment = null;
if ($gwPaymentId !== '') {
    $payment = pay_find_by_gateway_payment('razorpay', $gwPaymentId);
}
if (!$payment && $gwOrderId !== '') {
    $payment = pay_find_by_gateway_order('razorpay', $gwOrderId);
}

if (!$payment) {
    pay_mark_event_processed('razorpay', $eventId, 'No matching payment');
    hook_respond(200, 'No matching payment');
}

switch ($type) {
    case 'payment.captured':
    case 'order.paid':
        $mismatch = pay_check_amount($payment, isset($entity['amount']) ? (int) $entity['amount'] : null, (string) ($entity['currency'] ?? ''));
        if ($mismatch !== '') {
            pay_log('razorpay', 'webhook.amount_mismatch', [
                'order_id' => $payment['order_id'], 'payment_id' => $payment['id'],
                'error_message' => $mismatch,
            ]);
            pay_mark_event_processed('razorpay', $eventId, 'Amount mismatch');
            hook_respond(200, 'Amount mismatch recorded');
        }
        pay_mark_paid((int) $payment['id'], $gwPaymentId, (string) ($entity['method'] ?? null), $gwOrderId);
        break;

    case 'payment.failed':
        pay_mark_failed(
            (int) $payment['id'],
            (string) ($entity['error_description'] ?? 'Payment failed at Razorpay'),
            (string) ($entity['error_code'] ?? 'PAYMENT_FAILED')
        );
        break;

    case 'refund.processed':
    case 'refund.created':
        if ($refundE) {
            $fresh = pay_get_payment((int) $payment['id']);
            pay_record_refund(
                $fresh,
                pay_from_minor((int) ($refundE['amount'] ?? 0), (string) $fresh['currency']),
                (string) ($refundE['id'] ?? ''),
                'Refund confirmed by Razorpay',
                null,
                ($refundE['status'] ?? 'processed') === 'processed' ? 'completed' : 'processing'
            );
        }
        break;
}

pay_mark_event_processed('razorpay', $eventId, $type);
hook_respond(200, 'Processed');
