<?php
/**
 * Customer dismissed the gateway window. Records the cancellation so the
 * order can be retried; it never touches stock and never clears the cart.
 */

require_once __DIR__ . '/_boot.php';

$user = pay_endpoint_guard();
pay_rate_limit('cancel', 20, 60);

$paymentId = (int) ($_POST['payment_id'] ?? 0);
if ($paymentId <= 0) {
    json_response(false, 'Missing payment reference.');
}

[$payment, $order] = pay_require_own_payment($paymentId, $user);

if ($payment['status'] === 'paid') {
    json_response(true, 'This payment already completed.', [
        'payment_status' => 'paid',
        'redirect'       => 'order-confirmation.php?order=' . urlencode($order['order_number']),
    ]);
}

pay_mark_cancelled($paymentId, 'Closed by customer');

json_response(true, 'Payment cancelled. Your order has not been charged.', [
    'payment_status' => 'cancelled',
    'order_id'       => (int) $order['id'],
    'can_retry'      => true,
]);
