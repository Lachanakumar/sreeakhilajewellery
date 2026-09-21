<?php
/**
 * Record a gateway failure the browser observed.
 *
 * Razorpay reports a declined payment through its `payment.failed` event, which
 * only the browser sees — there is no server callback for it. Without this the
 * sequence was:
 *
 *   1. payment declines, the customer is shown the reason
 *   2. nothing reaches the server
 *   3. the customer closes the modal, which fires ondismiss
 *   4. the attempt is stored as "Closed by customer"
 *
 * so a genuine decline was filed as a cancellation and the gateway's own
 * description was lost. That is exactly the trail you need when a customer
 * says "it failed" and you have to explain why.
 *
 * The browser is not trusted here: this can only ever move an attempt to
 * failed, pay_mark_failed() refuses to touch a captured payment, and a webhook
 * arriving later can still correct the record.
 */

require_once __DIR__ . '/_boot.php';

$user = pay_endpoint_guard();
pay_rate_limit('fail', 20, 60);

$paymentId = (int) ($_POST['payment_id'] ?? 0);
if ($paymentId <= 0) {
    json_response(false, 'Missing payment reference.');
}

[$payment, $order] = pay_require_own_payment($paymentId, $user);

// Never downgrade something that already went through.
if ($payment['status'] === 'paid') {
    json_response(true, 'This payment already completed.', [
        'payment_status' => 'paid',
        'redirect'       => 'order-confirmation.php?order=' . urlencode($order['order_number']),
    ]);
}

$reason = trim((string) ($_POST['reason'] ?? ''));
$code   = trim((string) ($_POST['code'] ?? ''));
$step   = trim((string) ($_POST['step'] ?? ''));
$source = trim((string) ($_POST['source'] ?? ''));

// Keep the gateway's own payment reference when it gave us one — it is what
// you search for in the Razorpay dashboard.
$gatewayPaymentId = trim((string) ($_POST['gateway_payment_id'] ?? ''));
if ($gatewayPaymentId !== '' && preg_match('/^[A-Za-z0-9_]{1,120}$/', $gatewayPaymentId)) {
    getDB()->prepare('UPDATE payments SET gateway_payment_id = ? WHERE id = ? AND status <> ?')
           ->execute([$gatewayPaymentId, $paymentId, 'paid']);
}

$detail = $reason !== '' ? $reason : 'The payment could not be completed.';
if ($step !== '' || $source !== '') {
    $detail .= ' (' . trim($source . ' ' . $step) . ')';
}

pay_mark_failed($paymentId, $detail, $code !== '' ? $code : null);

json_response(true, 'Recorded.', [
    'payment_status' => 'failed',
    'order_id'       => (int) $order['id'],
    'payment_id'     => $paymentId,
    'can_retry'      => true,
]);
