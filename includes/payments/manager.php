<?php
/**
 * Payment engine — gateway-agnostic state machine, logging and fulfilment.
 *
 * Golden rules enforced here:
 *   • A frontend "success" is never trusted; only the verify and webhook
 *     paths call pay_mark_paid().
 *   • Stock, coupon usage and cart clearing happen exactly once per order,
 *     guarded by an atomic compare-and-set on orders.fulfilled_at.
 *   • Amount + currency are re-checked against the order before marking paid.
 */

require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../orders.php';
require_once __DIR__ . '/../cart-functions.php';
require_once __DIR__ . '/../../config/payment.php';
require_once __DIR__ . '/http.php';

/* ==================================================================
   Currency helpers
   ================================================================== */

/** Currencies whose smallest unit == the unit itself (no minor units). */
const PAY_ZERO_DECIMAL = ['JPY', 'KRW', 'VND', 'CLP', 'ISK', 'XAF', 'XOF'];

/** Rupees -> paise, dollars -> cents, etc. */
function pay_to_minor($amount, string $currency): int
{
    $currency = strtoupper($currency);
    if (in_array($currency, PAY_ZERO_DECIMAL, true)) {
        return (int) round((float) $amount);
    }
    return (int) round(((float) $amount) * 100);
}

function pay_from_minor(int $minor, string $currency): float
{
    $currency = strtoupper($currency);
    if (in_array($currency, PAY_ZERO_DECIMAL, true)) {
        return (float) $minor;
    }
    return $minor / 100;
}

/* ==================================================================
   Logging  (never receives card data, PINs, keys or tokens)
   ================================================================== */

function pay_log(string $gateway, string $event, array $ctx = []): void
{
    $safe = [];
    // Belt and braces: drop anything that even looks sensitive.
    $blocked = '/(card|cvv|cvc|pin|secret|password|token|salt|key|authoriz)/i';
    foreach ($ctx as $k => $v) {
        if (preg_match($blocked, (string) $k)) {
            continue;
        }
        if (is_scalar($v) || $v === null) {
            $safe[$k] = is_string($v) ? mb_substr($v, 0, 200) : $v;
        }
    }
    try {
        getDB()->prepare(
            'INSERT INTO payment_logs (gateway, order_id, payment_id, reference, event, status, error_code, error_message, context)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $gateway ?: null,
            isset($ctx['order_id']) ? (int) $ctx['order_id'] : null,
            isset($ctx['payment_id']) ? (int) $ctx['payment_id'] : null,
            isset($ctx['reference']) ? mb_substr((string) $ctx['reference'], 0, 190) : null,
            mb_substr($event, 0, 80),
            isset($ctx['status']) ? mb_substr((string) $ctx['status'], 0, 40) : null,
            isset($ctx['error_code']) ? mb_substr((string) $ctx['error_code'], 0, 80) : null,
            isset($ctx['error_message']) ? mb_substr((string) $ctx['error_message'], 0, 500) : null,
            $safe ? json_encode($safe, JSON_UNESCAPED_SLASHES) : null,
        ]);
    } catch (Throwable $e) {
        // Logging must never break a payment.
    }
}

/* ==================================================================
   Payment records
   ================================================================== */

function pay_get_payment(int $id): ?array
{
    $s = getDB()->prepare('SELECT * FROM payments WHERE id = ? LIMIT 1');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

/** Latest payment attempt for an order. */
function pay_payment_for_order(int $orderId): ?array
{
    $s = getDB()->prepare('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1');
    $s->execute([$orderId]);
    return $s->fetch() ?: null;
}

function pay_find_by_gateway_payment(string $gateway, string $gwPaymentId): ?array
{
    $s = getDB()->prepare('SELECT * FROM payments WHERE gateway = ? AND gateway_payment_id = ? LIMIT 1');
    $s->execute([$gateway, $gwPaymentId]);
    return $s->fetch() ?: null;
}

function pay_find_by_gateway_order(string $gateway, string $gwOrderId): ?array
{
    $s = getDB()->prepare('SELECT * FROM payments WHERE gateway = ? AND gateway_order_id = ? ORDER BY id DESC LIMIT 1');
    $s->execute([$gateway, $gwOrderId]);
    return $s->fetch() ?: null;
}

/**
 * Get the open payment row for an order, or create one.
 * Retrying a failed payment REUSES the order and opens a fresh attempt row,
 * so we never create duplicate orders (spec §8).
 */
function pay_open_attempt(array $order, string $gateway, ?string $method = null): array
{
    $db  = getDB();
    $cur = strtoupper($order['currency'] ?? pay_currency());

    $existing = pay_payment_for_order((int) $order['id']);
    if ($existing && in_array($existing['status'], ['pending', 'processing'], true) && $existing['gateway'] === $gateway) {
        // Same gateway, still open — reuse it (idempotent retry).
        $db->prepare('UPDATE payments SET payment_method = ?, amount = ?, currency = ? WHERE id = ?')
           ->execute([$method, $order['total_amount'], $cur, $existing['id']]);
        return pay_get_payment((int) $existing['id']);
    }

    // Close any other still-open attempt before starting a new one.
    if ($existing && in_array($existing['status'], ['pending', 'processing'], true)) {
        $db->prepare("UPDATE payments SET status = 'cancelled', failure_reason = 'Superseded by a new attempt' WHERE id = ?")
           ->execute([$existing['id']]);
    }

    $db->prepare(
        'INSERT INTO payments (order_id, user_id, gateway, method, amount, currency, payment_method, status, idempotency_key)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        (int) $order['id'],
        (int) $order['user_id'],
        $gateway,
        $gateway,
        $order['total_amount'],
        $cur,
        $method,
        'pending',
        bin2hex(random_bytes(16)),
    ]);

    $id = (int) $db->lastInsertId();
    $db->prepare('UPDATE orders SET payment_gateway = ?, payment_method = ?, payment_status = ? WHERE id = ?')
       ->execute([$gateway, $method ?: $gateway, 'pending', (int) $order['id']]);

    pay_log($gateway, 'attempt.open', ['order_id' => $order['id'], 'payment_id' => $id, 'status' => 'pending']);
    return pay_get_payment($id);
}

function pay_set_gateway_order(int $paymentId, string $gwOrderId, ?string $ref = null): void
{
    getDB()->prepare(
        "UPDATE payments SET gateway_order_id = ?, gateway_response_reference = COALESCE(?, gateway_response_reference),
                status = CASE WHEN status = 'pending' THEN 'processing' ELSE status END
         WHERE id = ?"
    )->execute([$gwOrderId, $ref, $paymentId]);

    $p = pay_get_payment($paymentId);
    if ($p) {
        getDB()->prepare("UPDATE orders SET payment_status = 'processing' WHERE id = ? AND payment_status = 'pending'")
               ->execute([(int) $p['order_id']]);
    }
}

/* ==================================================================
   Verification guards
   ================================================================== */

/**
 * The gateway told us it captured X. Confirm X matches what we asked for.
 * @return string '' when valid, else the reason it is not.
 */
function pay_check_amount(array $payment, $paidMinor, string $paidCurrency): string
{
    $expectMinor = pay_to_minor($payment['amount'], $payment['currency']);
    if ($paidMinor !== null && (int) $paidMinor !== $expectMinor) {
        return 'Amount mismatch (expected ' . $expectMinor . ', gateway reported ' . (int) $paidMinor . ').';
    }
    if ($paidCurrency !== '' && strtoupper($paidCurrency) !== strtoupper($payment['currency'])) {
        return 'Currency mismatch (expected ' . $payment['currency'] . ', gateway reported ' . $paidCurrency . ').';
    }
    return '';
}

/* ==================================================================
   State transitions
   ================================================================== */

/**
 * Mark a payment captured and fulfil the order. Idempotent: a duplicate
 * webhook or a double verify call is a no-op.
 *
 * @return array{ok:bool,already:bool,message:string}
 */
function pay_mark_paid(int $paymentId, string $gwPaymentId, ?string $method = null, ?string $ref = null): array
{
    $db = getDB();
    $payment = pay_get_payment($paymentId);
    if (!$payment) {
        return ['ok' => false, 'already' => false, 'message' => 'Payment record not found.'];
    }
    if ($payment['status'] === 'paid') {
        // Already captured — make sure fulfilment definitely ran, then stop.
        pay_finalize_order((int) $payment['order_id']);
        return ['ok' => true, 'already' => true, 'message' => 'Payment already recorded.'];
    }

    // Atomic compare-and-set: only one concurrent request wins this UPDATE.
    $upd = $db->prepare(
        "UPDATE payments
            SET status = 'paid', gateway_payment_id = ?, transaction_id = ?,
                payment_method = COALESCE(?, payment_method),
                gateway_response_reference = COALESCE(?, gateway_response_reference),
                paid_at = NOW(), failure_reason = NULL
          WHERE id = ? AND status <> 'paid'"
    );
    $upd->execute([$gwPaymentId, $gwPaymentId, $method, $ref, $paymentId]);
    if ($upd->rowCount() === 0) {
        pay_finalize_order((int) $payment['order_id']);
        return ['ok' => true, 'already' => true, 'message' => 'Payment already recorded.'];
    }

    pay_log($payment['gateway'], 'payment.paid', [
        'order_id' => $payment['order_id'], 'payment_id' => $paymentId,
        'reference' => $gwPaymentId, 'status' => 'paid',
    ]);

    pay_finalize_order((int) $payment['order_id']);
    return ['ok' => true, 'already' => false, 'message' => 'Payment confirmed.'];
}

function pay_mark_failed(int $paymentId, string $reason, ?string $code = null): void
{
    $payment = pay_get_payment($paymentId);
    if (!$payment || $payment['status'] === 'paid') {
        return; // never downgrade a captured payment
    }
    getDB()->prepare("UPDATE payments SET status = 'failed', failure_reason = ? WHERE id = ? AND status <> 'paid'")
           ->execute([mb_substr($reason, 0, 255), $paymentId]);
    getDB()->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ? AND payment_status <> 'paid'")
           ->execute([(int) $payment['order_id']]);

    pay_log($payment['gateway'], 'payment.failed', [
        'order_id' => $payment['order_id'], 'payment_id' => $paymentId,
        'status' => 'failed', 'error_code' => $code, 'error_message' => $reason,
    ]);
}

function pay_mark_cancelled(int $paymentId, string $reason = 'Cancelled by customer'): void
{
    $payment = pay_get_payment($paymentId);
    if (!$payment || $payment['status'] === 'paid') {
        return;
    }
    getDB()->prepare("UPDATE payments SET status = 'cancelled', failure_reason = ? WHERE id = ? AND status <> 'paid'")
           ->execute([mb_substr($reason, 0, 255), $paymentId]);
    getDB()->prepare("UPDATE orders SET payment_status = 'cancelled' WHERE id = ? AND payment_status <> 'paid'")
           ->execute([(int) $payment['order_id']]);

    pay_log($payment['gateway'], 'payment.cancelled', [
        'order_id' => $payment['order_id'], 'payment_id' => $paymentId, 'status' => 'cancelled',
    ]);
}

/* ==================================================================
   Fulfilment — runs exactly once per order
   ================================================================== */

/**
 * Reduce stock, consume the coupon, clear the cart and move the order to
 * paid/confirmed. Guarded by an atomic UPDATE on fulfilled_at so concurrent
 * verify + webhook calls cannot double-decrement inventory.
 */
function pay_finalize_order(int $orderId): bool
{
    $db = getDB();

    // Claim the order. Whoever flips fulfilled_at from NULL owns fulfilment.
    $claim = $db->prepare('UPDATE orders SET fulfilled_at = NOW() WHERE id = ? AND fulfilled_at IS NULL');
    $claim->execute([$orderId]);
    if ($claim->rowCount() === 0) {
        // Someone already fulfilled it; just make sure the status is right.
        $db->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?")->execute([$orderId]);
        return false;
    }

    $order = getOrder($orderId);
    if (!$order) {
        return false;
    }

    $db->beginTransaction();
    try {
        foreach (getOrderItems($orderId) as $item) {
            if ($item['product_id']) {
                $db->prepare('UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?')
                   ->execute([(int) $item['quantity'], (int) $item['product_id']]);
            }
            if ($item['variant_id']) {
                $db->prepare('UPDATE product_variants SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?')
                   ->execute([(int) $item['quantity'], (int) $item['variant_id']]);
            }
        }

        if (!empty($order['coupon_id'])) {
            $db->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?')
               ->execute([(int) $order['coupon_id']]);
        }

        /* payment_status only. Fulfilment means "the money is in and the stock
           is reserved" — it is not the shop agreeing to the order. Advancing
           order_status to 'confirmed' here meant every new order appeared in
           the admin list already confirmed, so there was nothing left for staff
           to act on and "Awaiting Action" was always zero. Confirming is now an
           explicit step an admin takes (pending -> confirmed in the workflow). */
        $db->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?")->execute([$orderId]);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        // Release the claim so a retry/webhook can fulfil it later.
        $db->prepare('UPDATE orders SET fulfilled_at = NULL WHERE id = ?')->execute([$orderId]);
        pay_log((string) ($order['payment_gateway'] ?? ''), 'fulfil.error', [
            'order_id' => $orderId, 'error_message' => $e->getMessage(),
        ]);
        return false;
    }

    // Cart clearing only makes sense inside the buyer's own session (a webhook
    // has no session), so it is deliberately outside the transaction.
    if (session_status() === PHP_SESSION_ACTIVE && (int) ($_SESSION['user_id'] ?? 0) === (int) $order['user_id']) {
        clearCart();
        removeCouponFromSession();
    }

    pay_log((string) ($order['payment_gateway'] ?? ''), 'order.fulfilled', [
        'order_id' => $orderId, 'status' => 'paid',
    ]);
    return true;
}

/**
 * COD: no gateway, but the order still needs stock/coupon/cart handling.
 * Payment stays 'pending' (collected on delivery) — only fulfilment runs.
 */
function pay_finalize_cod(int $orderId): void
{
    $db = getDB();
    $claim = $db->prepare('UPDATE orders SET fulfilled_at = NOW() WHERE id = ? AND fulfilled_at IS NULL');
    $claim->execute([$orderId]);
    if ($claim->rowCount() === 0) {
        return;
    }

    $order = getOrder($orderId);
    if (!$order) { return; }

    $db->beginTransaction();
    try {
        foreach (getOrderItems($orderId) as $item) {
            if ($item['product_id']) {
                $db->prepare('UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?')
                   ->execute([(int) $item['quantity'], (int) $item['product_id']]);
            }
            if ($item['variant_id']) {
                $db->prepare('UPDATE product_variants SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?')
                   ->execute([(int) $item['quantity'], (int) $item['variant_id']]);
            }
        }
        if (!empty($order['coupon_id'])) {
            $db->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?')
               ->execute([(int) $order['coupon_id']]);
        }
        /* Deliberately leaves order_status alone — a placed COD order is
           'pending' until an admin confirms it. See pay_finalize_order(). */
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        $db->prepare('UPDATE orders SET fulfilled_at = NULL WHERE id = ?')->execute([$orderId]);
        return;
    }

    clearCart();
    removeCouponFromSession();
    pay_log('cod', 'order.fulfilled', ['order_id' => $orderId, 'status' => 'pending']);
}

/* ==================================================================
   Webhook de-duplication
   ================================================================== */

/**
 * Record a webhook event. Returns false when this event was already seen,
 * which is the caller's signal to stop (spec §7/§9: no double processing).
 */
function pay_claim_event(string $gateway, string $eventId, ?string $type = null, array $meta = []): bool
{
    try {
        getDB()->prepare(
            'INSERT INTO payment_events (gateway, event_id, event_type, payment_id, order_id, gateway_payment_id, signature_valid)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $gateway,
            mb_substr($eventId, 0, 190),
            $type ? mb_substr($type, 0, 80) : null,
            $meta['payment_id'] ?? null,
            $meta['order_id'] ?? null,
            isset($meta['gateway_payment_id']) ? mb_substr((string) $meta['gateway_payment_id'], 0, 120) : null,
            !empty($meta['signature_valid']) ? 1 : 0,
        ]);
        return true;
    } catch (Throwable $e) {
        return false; // UNIQUE(gateway, event_id) tripped => replay
    }
}

function pay_mark_event_processed(string $gateway, string $eventId, ?string $note = null): void
{
    try {
        getDB()->prepare('UPDATE payment_events SET processed = 1, notes = ? WHERE gateway = ? AND event_id = ?')
               ->execute([$note ? mb_substr($note, 0, 255) : null, $gateway, mb_substr($eventId, 0, 190)]);
    } catch (Throwable $e) {
    }
}

/* ==================================================================
   Driver dispatch
   ================================================================== */

function pay_driver(string $gateway): ?string
{
    $file = __DIR__ . '/' . preg_replace('/[^a-z]/', '', $gateway) . '.php';
    if (!is_file($file)) {
        return null;
    }
    require_once $file;
    return 'pay_' . $gateway . '_';
}

/**
 * Ask the gateway to create its own order/intent.
 * @return array{ok:bool,message:string,data:array}
 */
function pay_create_gateway_order(string $gateway, array $order, array $payment, ?string $method): array
{
    $fn = pay_driver($gateway);
    if (!$fn || !function_exists($fn . 'create')) {
        return ['ok' => false, 'message' => 'That payment method is not available.', 'data' => []];
    }
    return call_user_func($fn . 'create', $order, $payment, $method);
}

/**
 * Server-side verification of a completed payment.
 * @return array{ok:bool,message:string,status:string}
 */
function pay_verify(string $gateway, array $payment, array $input): array
{
    $fn = pay_driver($gateway);
    if (!$fn || !function_exists($fn . 'verify')) {
        return ['ok' => false, 'message' => 'Verification is not available for this gateway.', 'status' => 'failed'];
    }
    return call_user_func($fn . 'verify', $payment, $input);
}

/** @return array{ok:bool,message:string,data:array} */
function pay_refund(string $gateway, array $payment, float $amount, string $reason): array
{
    $fn = pay_driver($gateway);
    if (!$fn || !function_exists($fn . 'refund')) {
        return ['ok' => false, 'message' => 'This gateway does not support API refunds.', 'data' => []];
    }
    return call_user_func($fn . 'refund', $payment, $amount, $reason);
}

/* ==================================================================
   Refund bookkeeping
   ================================================================== */

/** Record a gateway-confirmed refund and move the order/payment along. */
function pay_record_refund(array $payment, float $amount, string $gatewayRefundId, string $reason, ?int $adminId = null, string $status = 'completed'): void
{
    $db = getDB();
    $db->prepare(
        'INSERT INTO payment_refunds (payment_id, order_id, gateway, gateway_refund_id, amount, currency, reason, status, admin_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE status = VALUES(status), amount = VALUES(amount)'
    )->execute([
        (int) $payment['id'], (int) $payment['order_id'], $payment['gateway'],
        $gatewayRefundId ?: null, $amount, $payment['currency'],
        mb_substr($reason, 0, 255), $status, $adminId,
    ]);

    if ($status !== 'completed') {
        return;
    }

    $refunded = (float) $payment['amount_refunded'] + $amount;
    $full     = $refunded + 0.001 >= (float) $payment['amount'];
    $newState = $full ? 'refunded' : 'partially_refunded';

    $db->prepare('UPDATE payments SET amount_refunded = ?, status = ? WHERE id = ?')
       ->execute([$refunded, $newState, (int) $payment['id']]);

    // A fully refunded order goes through the existing cancellation path so the
    // goods return to stock exactly once — updateOrderStatus() releases the
    // fulfilment claim atomically, so a repeat refund webhook cannot restock twice.
    if ($full) {
        updateOrderStatus((int) $payment['order_id'], 'refunded');
    }
    $db->prepare('UPDATE orders SET payment_status = ? WHERE id = ?')
       ->execute([$newState, (int) $payment['order_id']]);

    pay_log($payment['gateway'], 'refund.recorded', [
        'order_id' => $payment['order_id'], 'payment_id' => $payment['id'],
        'reference' => $gatewayRefundId, 'status' => $newState,
    ]);
}

function pay_refunds_for_order(int $orderId): array
{
    $s = getDB()->prepare('SELECT * FROM payment_refunds WHERE order_id = ? ORDER BY id DESC');
    $s->execute([$orderId]);
    return $s->fetchAll();
}

/** Customer-safe copy for a failed payment. */
function pay_failure_message(string $detail = ''): string
{
    $base = 'Payment failed. Your order has not been charged. Please try again or choose another payment method.';
    return $detail !== '' ? $detail . ' ' . $base : $base;
}
