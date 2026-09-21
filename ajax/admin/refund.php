<?php
/**
 * Admin-initiated refund (full or partial).
 *
 * The gateway is the source of truth: we ask it to refund, and only write our
 * own bookkeeping once it accepts. A webhook may confirm the same refund later
 * — pay_record_refund() is keyed on (gateway, gateway_refund_id) so that is a
 * no-op rather than a double credit.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/orders.php';
require_once __DIR__ . '/../../includes/payments/manager.php';

$admin     = currentAdmin();
$paymentId = (int) ($_POST['payment_id'] ?? 0);
$amountIn  = trim((string) ($_POST['amount'] ?? ''));
$reason    = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 200);

if ($paymentId <= 0) {
    json_response(false, 'Missing payment reference.');
}

$payment = pay_get_payment($paymentId);
if (!$payment) {
    json_response(false, 'Payment not found.', [], 404);
}

if (!in_array($payment['status'], ['paid', 'partially_refunded'], true)) {
    json_response(false, 'Only a captured payment can be refunded.');
}

$paid      = (float) $payment['amount'];
$refunded  = (float) $payment['amount_refunded'];
$available = round($paid - $refunded, 2);

if ($available <= 0) {
    json_response(false, 'This payment has already been fully refunded.');
}

// Blank amount = refund whatever is left.
$amount = ($amountIn === '') ? $available : round((float) $amountIn, 2);

if ($amount <= 0) {
    json_response(false, 'Enter a refund amount greater than zero.');
}
if ($amount > $available + 0.001) {
    json_response(false, 'That is more than the ' . formatPrice($available) . ' still available to refund.');
}

if ($payment['gateway'] === 'cod') {
    json_response(false, 'Cash-on-delivery orders are refunded outside the gateway. Mark the order refunded instead.');
}

$res = pay_refund((string) $payment['gateway'], $payment, $amount, $reason ?: 'Refunded by admin');

pay_log((string) $payment['gateway'], 'refund.' . ($res['ok'] ? 'requested' : 'error'), [
    'order_id'      => $payment['order_id'],
    'payment_id'    => $payment['id'],
    'reference'     => $res['data']['refund_id'] ?? null,
    'status'        => $res['data']['status'] ?? null,
    'error_message' => $res['ok'] ? null : $res['message'],
]);

if (!$res['ok']) {
    json_response(false, $res['message'] ?: 'The gateway refused this refund.');
}

pay_record_refund(
    $payment,
    $amount,
    (string) ($res['data']['refund_id'] ?? ''),
    $reason ?: 'Refunded by admin',
    (int) $admin['id'],
    (string) ($res['data']['status'] ?? 'completed')
);

$fresh = pay_get_payment($paymentId);
$order = getOrder((int) $payment['order_id']);

json_response(true, 'Refund of ' . formatPrice($amount) . ' submitted.', [
    'payment_status'  => $fresh['status'],
    'amount_refunded' => (float) $fresh['amount_refunded'],
    'available'       => round((float) $fresh['amount'] - (float) $fresh['amount_refunded'], 2),
    'order_status'    => $order['order_status'],
]);
