<?php
/**
 * Stripe webhook.  Dashboard -> Developers -> Webhooks -> <site>/webhooks/stripe.php
 * Subscribe to: payment_intent.succeeded, payment_intent.payment_failed,
 *               payment_intent.canceled, charge.refunded
 */

require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/../includes/payments/stripe.php';

hook_require_post();
hook_guard_size();

$raw = hook_raw_body();
$sig = hook_header('Stripe-Signature');

if (!pay_stripe_verify_webhook($raw, $sig)) {
    pay_log('stripe', 'webhook.bad_signature', ['error_message' => 'Signature verification failed']);
    hook_respond(400, 'Invalid signature');
}

$event = json_decode($raw, true);
if (!is_array($event) || empty($event['id']) || empty($event['type'])) {
    hook_respond(400, 'Malformed payload');
}

$eventId = (string) $event['id'];
$type    = (string) $event['type'];
$object  = $event['data']['object'] ?? [];

if (!pay_claim_event('stripe', $eventId, $type, [
    'gateway_payment_id' => $object['id'] ?? null,
    'signature_valid'    => true,
])) {
    hook_respond(200, 'Duplicate event ignored');
}

/** Resolve our payment row from the PaymentIntent id. */
$intentId = '';
if (strpos($type, 'payment_intent.') === 0) {
    $intentId = (string) ($object['id'] ?? '');
} elseif (strpos($type, 'charge.') === 0) {
    $intentId = (string) ($object['payment_intent'] ?? '');
}

$payment = $intentId !== '' ? pay_find_by_gateway_order('stripe', $intentId) : null;
if (!$payment && !empty($object['metadata']['payment_id'])) {
    $payment = pay_get_payment((int) $object['metadata']['payment_id']);
}

if (!$payment) {
    pay_mark_event_processed('stripe', $eventId, 'No matching payment');
    hook_respond(200, 'No matching payment');
}

switch ($type) {
    case 'payment_intent.succeeded':
        $mismatch = pay_check_amount(
            $payment,
            isset($object['amount_received']) ? (int) $object['amount_received'] : (int) ($object['amount'] ?? 0),
            (string) ($object['currency'] ?? '')
        );
        if ($mismatch !== '') {
            pay_log('stripe', 'webhook.amount_mismatch', [
                'order_id' => $payment['order_id'], 'payment_id' => $payment['id'],
                'error_message' => $mismatch,
            ]);
            pay_mark_event_processed('stripe', $eventId, 'Amount mismatch');
            hook_respond(200, 'Amount mismatch recorded');
        }
        pay_mark_paid(
            (int) $payment['id'],
            (string) ($object['latest_charge'] ?? $intentId),
            'card',
            $intentId
        );
        break;

    case 'payment_intent.payment_failed':
        pay_mark_failed(
            (int) $payment['id'],
            (string) ($object['last_payment_error']['message'] ?? 'Payment failed at Stripe'),
            (string) ($object['last_payment_error']['code'] ?? 'PAYMENT_FAILED')
        );
        break;

    case 'payment_intent.canceled':
        pay_mark_cancelled((int) $payment['id'], 'Cancelled at Stripe');
        break;

    case 'charge.refunded':
        $fresh    = pay_get_payment((int) $payment['id']);
        $refunded = (int) ($object['amount_refunded'] ?? 0);
        $already  = pay_to_minor((float) $fresh['amount_refunded'], (string) $fresh['currency']);
        $delta    = $refunded - $already;
        if ($delta > 0) {
            $ref = $object['refunds']['data'][0]['id'] ?? ('ch_' . ($object['id'] ?? ''));
            pay_record_refund(
                $fresh,
                pay_from_minor($delta, (string) $fresh['currency']),
                (string) $ref,
                'Refund confirmed by Stripe',
                null,
                'completed'
            );
        }
        break;
}

pay_mark_event_processed('stripe', $eventId, $type);
hook_respond(200, 'Processed');
