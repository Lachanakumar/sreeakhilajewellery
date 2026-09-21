<?php
/**
 * Server-side verification of a completed payment.
 *
 * This is the ONLY path (besides webhooks) that may mark an order paid.
 * Whatever the browser posts is treated as a hint; the gateway API is asked
 * for the real status, amount and currency before anything is trusted.
 */

require_once __DIR__ . '/_boot.php';

$user = pay_endpoint_guard();
pay_rate_limit('verify', 20, 60);

$paymentId = (int) ($_POST['payment_id'] ?? 0);
if ($paymentId <= 0) {
    json_response(false, 'Missing payment reference.');
}

[$payment, $order] = pay_require_own_payment($paymentId, $user);

// Already settled — return the happy path rather than re-verifying.
if ($payment['status'] === 'paid') {
    pay_finalize_order((int) $order['id']);
    json_response(true, 'Payment completed successfully', [
        'payment_status' => 'paid',
        'order_id'       => (int) $order['id'],
        'redirect'       => 'order-confirmation.php?order=' . urlencode($order['order_number']),
    ]);
}

$result = pay_verify((string) $payment['gateway'], $payment, $_POST);

pay_log((string) $payment['gateway'], 'verify.' . ($result['ok'] ? 'ok' : $result['status']), [
    'order_id'   => $order['id'],
    'payment_id' => $paymentId,
    'status'     => $result['status'],
]);

if ($result['ok']) {
    $fresh = getOrder((int) $order['id'], (int) $user['id']);
    json_response(true, 'Payment completed successfully', [
        'payment_status' => 'paid',
        'order_id'       => (int) $order['id'],
        'order_number'   => $fresh['order_number'],
        'redirect'       => 'order-confirmation.php?order=' . urlencode($fresh['order_number']),
    ]);
}

// Pending/processing is not a failure — the webhook will settle it.
$pending = in_array($result['status'], ['pending', 'processing'], true);

json_response(false, $result['message'], [
    'payment_status' => $result['status'],
    'order_id'       => (int) $order['id'],
    'payment_id'     => $paymentId,
    'can_retry'      => !$pending,
    'pending'        => $pending,
], 200);
