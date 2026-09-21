<?php
/**
 * Poll the current payment status for an order.
 * Used while a redirect/pending payment settles (PhonePe UPI, Stripe SCA,
 * PayPal review) so the page can move on the moment the webhook lands.
 */

require_once __DIR__ . '/_boot.php';

$user = pay_endpoint_guard();
pay_rate_limit('status', 60, 60);

$paymentId = (int) ($_POST['payment_id'] ?? 0);
$orderId   = (int) ($_POST['order_id'] ?? 0);

if ($paymentId > 0) {
    [$payment, $order] = pay_require_own_payment($paymentId, $user);
} elseif ($orderId > 0) {
    $order   = pay_require_own_order($orderId, $user);
    $payment = pay_payment_for_order((int) $order['id']);
    if (!$payment) {
        json_response(false, 'No payment found for this order.', [], 404);
    }
} else {
    json_response(false, 'Missing payment reference.');
}

// Still open? Ask the gateway once — the webhook may not have arrived yet.
if (in_array($payment['status'], ['pending', 'processing'], true) && $payment['gateway'] !== 'cod') {
    $res = pay_verify((string) $payment['gateway'], $payment, []);
    if ($res['ok']) {
        $payment = pay_get_payment((int) $payment['id']);
    }
}

$payment = pay_get_payment((int) $payment['id']) ?: $payment;
$order   = getOrder((int) $order['id'], (int) $user['id']);
$paid    = $payment['status'] === 'paid';

json_response(true, '', [
    'payment_status' => $payment['status'],
    'order_status'   => $order['order_status'],
    'order_id'       => (int) $order['id'],
    'settled'        => $paid || in_array($payment['status'], ['failed', 'cancelled'], true),
    'failure_reason' => $paid ? null : ($payment['failure_reason'] ?: null),
    'redirect'       => $paid ? 'order-confirmation.php?order=' . urlencode($order['order_number']) : null,
]);
