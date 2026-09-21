<?php
/**
 * Create (or reuse) an order, open a payment attempt and hand the browser
 * whatever the chosen gateway needs to take over.
 *
 * POST: gateway, method, [order_id for a retry], + address fields on first try.
 */

require_once __DIR__ . '/_boot.php';

$user = pay_endpoint_guard();
pay_rate_limit('create', 12, 60);

$gateway = preg_replace('/[^a-z]/', '', strtolower((string) ($_POST['gateway'] ?? '')));
$method  = preg_replace('/[^a-z_]/', '', strtolower((string) ($_POST['method'] ?? '')));
$orderId = (int) ($_POST['order_id'] ?? 0);

// ---- gateway must be one we actually offer right now -------------------
$available = pay_available_gateways();
if ($gateway === '' || !isset($available[$gateway])) {
    json_response(false, 'Please choose a valid payment method.');
}

// ---- method must be one this gateway supports --------------------------
$allowed = array_column($available[$gateway]['methods'], 'code');
if ($method === '' || !in_array($method, $allowed, true)) {
    $method = $allowed[0];
}

/* ======================================================================
   1. Resolve the order — reuse on retry, create on the first attempt.
   ====================================================================== */
if ($orderId > 0) {
    $order = pay_require_own_order($orderId, $user);

    if ($order['payment_status'] === 'paid') {
        json_response(true, 'This order is already paid.', [
            'already_paid' => true,
            'redirect'     => 'order-confirmation.php?order=' . urlencode($order['order_number']),
        ]);
    }
    if (in_array($order['order_status'], ['cancelled', 'refunded'], true)) {
        json_response(false, 'This order can no longer be paid.');
    }
} else {
    [$ok, $errors, $lines] = validateCartForCheckout();
    if (!$ok) {
        json_response(false, implode(' ', $errors));
    }

    [$shipping, $shipErrors] = resolveCheckoutShipping((int) $user['id'], $_POST);
    if ($shipErrors) {
        json_response(false, implode(' ', $shipErrors));
    }

    $notes = mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 500);
    [$created, $result] = placeOrder((int) $user['id'], $shipping, $method, $notes, $gateway);
    if (!$created) {
        json_response(false, (string) $result);
    }

    $orderId = (int) $result;
    $order   = getOrder($orderId, (int) $user['id']);
}

/* ======================================================================
   2. Cash on delivery — no gateway, fulfil immediately.
   ====================================================================== */
if ($gateway === 'cod') {
    $db = getDB();

    /* Switching to COD after an online attempt failed used to leave
       payment_status = 'failed' on the order, so a perfectly good
       cash-on-delivery order was shown to the customer as a failed payment.
       Close the old attempt and put the order back to 'pending' — which for
       COD means "to be collected on delivery", not "something went wrong". */
    $db->prepare(
        "UPDATE payments SET status = 'cancelled',
                failure_reason = COALESCE(failure_reason, 'Superseded by cash on delivery')
          WHERE order_id = ? AND status <> 'paid'"
    )->execute([$orderId]);

    $db->prepare(
        "UPDATE orders
            SET payment_gateway = 'cod', payment_method = 'cod', payment_status = 'pending'
          WHERE id = ? AND payment_status <> 'paid'"
    )->execute([$orderId]);

    // the COD attempt the order is actually settled against
    $db->prepare(
        'INSERT INTO payments (order_id, user_id, gateway, method, amount, currency, payment_method, status, idempotency_key)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $orderId, (int) $user['id'], 'cod', 'cod',
        $order['total_amount'], strtoupper($order['currency'] ?? 'INR'),
        'cod', 'pending', bin2hex(random_bytes(16)),
    ]);

    pay_finalize_cod($orderId);

    json_response(true, 'Order placed.', [
        'flow'     => 'offline',
        'order_id' => $orderId,
        'redirect' => 'order-confirmation.php?order=' . urlencode($order['order_number']),
    ]);
}

/* ======================================================================
   3. Online gateway — open an attempt and create the gateway-side order.
   ====================================================================== */
$payment = pay_open_attempt($order, $gateway, $method);

// Attach the customer email for gateway prefill (never stored by us).
$order['customer_email'] = (string) ($user['email'] ?? '');

$res = pay_create_gateway_order($gateway, $order, $payment, $method);
if (!$res['ok']) {
    pay_mark_failed((int) $payment['id'], $res['message'], 'CREATE_FAILED');
    json_response(false, $res['message'] ?: pay_failure_message(), [
        'order_id'   => $orderId,
        'payment_id' => (int) $payment['id'],
        'can_retry'  => true,
    ]);
}

json_response(true, '', array_merge($res['data'], [
    'gateway'      => $gateway,
    'method'       => $method,
    'order_id'     => $orderId,
    'order_number' => $order['order_number'],
    'payment_id'   => (int) $payment['id'],
    'amount_html'  => formatPrice($order['total_amount']),
]));
