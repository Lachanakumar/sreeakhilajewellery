<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/includes/charts.php';   // status_pill()
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/payments/manager.php';
requireAdmin();

$db = getDB();
$orderId = (int) ($_GET['id'] ?? 0);
$order = getOrder($orderId);
if (!$order) {
    admin_warn('That order could not be found — it may have been deleted.');
    redirect('orders.php');
}

if (admin_post_ok('order-view.php?id=' . $orderId)) {
    if (($_POST['do'] ?? '') === 'status') {
        if (updateOrderStatus($orderId, $_POST['status'] ?? '')) {
            flash_set('admin_ok', 'Order status updated.');
        } else {
            flash_set('admin_err', 'Invalid status.');
        }
    }

    /**
     * Manual payment state change — for offline methods (cash, bank transfer)
     * or to clear up a stuck attempt. Online captures always come from the
     * gateway itself; this never invents one.
     */
    if (($_POST['do'] ?? '') === 'payment') {
        $payment = pay_payment_for_order($orderId);
        $target  = (string) ($_POST['payment_status'] ?? '');

        if (!$payment) {
            flash_set('admin_err', 'This order has no payment record.');
        } elseif ($target === 'paid') {
            if ($payment['gateway'] !== 'cod' && !in_array($payment['gateway'], ['bank', 'manual'], true) && $payment['status'] !== 'paid') {
                flash_set('admin_err', 'Online payments can only be marked paid by the gateway. Use the gateway dashboard, or wait for the webhook.');
            } else {
                $ref = 'MANUAL-' . $payment['id'];
                pay_mark_paid((int) $payment['id'], $ref, (string) ($payment['payment_method'] ?: $payment['gateway']), $ref);
                flash_set('admin_ok', 'Payment marked as received and the order fulfilled.');
            }
        } elseif ($target === 'failed') {
            pay_mark_failed((int) $payment['id'], 'Marked failed by admin', 'ADMIN');
            flash_set('admin_ok', 'Payment marked as failed.');
        } elseif ($target === 'cancelled') {
            pay_mark_cancelled((int) $payment['id'], 'Cancelled by admin');
            flash_set('admin_ok', 'Payment marked as cancelled.');
        } elseif ($target === 'pending') {
            $db->prepare("UPDATE payments SET status = 'pending', failure_reason = NULL WHERE id = ? AND status <> 'paid'")
               ->execute([(int) $payment['id']]);
            $db->prepare("UPDATE orders SET payment_status = 'pending' WHERE id = ? AND payment_status <> 'paid'")
               ->execute([$orderId]);
            flash_set('admin_ok', 'Payment reset to pending.');
        } elseif ($target === 'refunded') {
            /* Recording a refund that was settled outside the gateway — cash
               handed back on a COD order, a bank transfer reversed by hand.
               The Refund panel talks to the gateway and explicitly refuses COD,
               so without this there was no way at all to mark such an order
               refunded: the dropdown had no Refunded option and this branch
               fell through to "use the Refund panel".

               It goes through pay_record_refund() rather than writing the
               columns directly, so it takes the same path a gateway refund
               does — a payment_refunds row for the audit trail, payments and
               orders both moved to 'refunded', and the stock returned once via
               updateOrderStatus(). */
            /* Follow the money, not the clock: an order keeps a row per
               attempt, so the most recent one can be a cancelled retry sitting
               behind the capture. A refund may only touch the captured row. */
            $payment = pay_captured_payment_for_order($orderId) ?: $payment;

            if ($payment['status'] === 'refunded') {
                flash_set('admin_err', 'This payment is already marked refunded.');
            } elseif (!in_array($payment['status'], ['paid', 'partially_refunded'], true)) {
                flash_set('admin_err', 'Only a captured payment can be refunded — this one was never paid.');
            } else {
                $outstanding = round((float) $payment['amount'] - (float) $payment['amount_refunded'], 2);
                pay_record_refund(
                    $payment,
                    $outstanding,
                    'MANUAL-' . $payment['id'],
                    'Refunded outside the gateway by admin',
                    (int) (currentAdmin()['id'] ?? 0)
                );
                flash_set('admin_ok', 'Payment marked refunded and the order moved to Refunded.');
            }
        } else {
            flash_set('admin_err', 'Unsupported payment status.');
        }
    }
    redirect('order-view.php?id=' . $orderId);
}

$items = getOrderItems($orderId);
$cust = $db->prepare('SELECT name, email, phone FROM users WHERE id = ?');
$cust->execute([$order['user_id']]);
$customer = $cust->fetch();

/* ---- payment context -------------------------------------------------- */
$payStmt = $db->prepare('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC');
$payStmt->execute([$orderId]);
$attempts = $payStmt->fetchAll();
$payment  = $attempts[0] ?? null;

$refunds = pay_refunds_for_order($orderId);

$logStmt = $db->prepare('SELECT * FROM payment_logs WHERE order_id = ? ORDER BY id DESC LIMIT 25');
$logStmt->execute([$orderId]);
$logs = $logStmt->fetchAll();

$gatewayLabels = array_map(fn($g) => $g['label'], pay_gateways());

// GST is not a column on orders — it falls out of the other figures
$orderTax    = order_tax_amount($order);
$orderTaxPct = order_tax_percent($order);
// what the customer is told about this order's payment, so the two agree
$payState    = payment_status_label($order);
$refundable    = $payment && in_array($payment['status'], ['paid', 'partially_refunded'], true) && $payment['gateway'] !== 'cod';
$available     = $payment ? round((float) $payment['amount'] - (float) $payment['amount_refunded'], 2) : 0.0;

$adminPageTitle = 'Order ' . $order['order_number'];
$adminNav = 'orders';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
admin_page_head('Order ' . $order['order_number'], 'Placed ' . date('d M Y', strtotime($order['created_at'])), '<a class="btn btn--ghost" href="orders.php">&#8592; All orders</a>');
?>
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px" class="row2">
    <div>
        <div class="admin__card">
            <h3>Items</h3>
            <table class="admin__table">
                <thead><tr><th>Product</th><th>SKU</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr><td><?php echo e($it['product_name']); ?></td><td><?php echo e($it['sku']); ?></td><td><?php echo formatPrice($it['price']); ?></td><td><?php echo (int) $it['quantity']; ?></td><td><?php echo formatPrice($it['subtotal']); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><td colspan="4">Subtotal</td><td><?php echo formatPrice($order['subtotal']); ?></td></tr>
                    <?php if ($order['discount_amount'] > 0): ?><tr><td colspan="4">Discount (<?php echo e($order['coupon_code']); ?>)</td><td>- <?php echo formatPrice($order['discount_amount']); ?></td></tr><?php endif; ?>
                    <tr><td colspan="4">Shipping</td><td><?php echo formatPrice($order['shipping_amount']); ?></td></tr>
                    <?php /* Derived, not stored — see order_tax_amount(). Hidden when the
                             order carries no tax, so zero-rated orders gain no empty row. */ ?>
                    <?php if ($orderTax > 0): ?>
                        <tr><td colspan="4">GST<?php echo $orderTaxPct > 0 ? ' (' . rtrim(rtrim(number_format($orderTaxPct, 2), '0'), '.') . '%)' : ''; ?></td><td><?php echo formatPrice($orderTax); ?></td></tr>
                    <?php endif; ?>
                    <tr><td colspan="4"><strong>Total</strong></td><td><strong><?php echo formatPrice($order['total_amount']); ?></strong></td></tr>
                </tfoot>
            </table>
        </div>

        <!-- ============ Payment ============ -->
        <div class="admin__card">
            <h3>Payment</h3>
            <?php if (!$payment): ?>
                <p class="muted">No payment record for this order.</p>
            <?php else: ?>
                <table class="admin__table admin__table--kv">
                    <tbody>
                        <tr><td>Gateway</td><td><strong><?php echo e($gatewayLabels[$payment['gateway']] ?? strtoupper((string) $payment['gateway'])); ?></strong></td></tr>
                        <tr><td>Method</td><td><?php echo e(payment_method_label($order)); ?></td></tr>
                        <?php /* Two different facts: where this attempt got to, and what the
                                 order as a whole reports — which is what the customer reads
                                 and what the Orders list shows. */ ?>
                        <tr><td>Status</td><td><?php echo status_pill($payment['status']); ?> <span class="cell__sub">this attempt</span></td></tr>
                        <tr><td>Order payment state</td><td><strong><?php echo e($payState['label']); ?></strong><?php echo $payState['note'] ? '<br><span class="cell__sub">' . e($payState['note']) . '</span>' : ''; ?></td></tr>
                        <tr><td>Amount</td><td><?php echo formatPrice($payment['amount']); ?> <span class="muted"><?php echo e($payment['currency']); ?></span></td></tr>
                        <?php if ((float) $payment['amount_refunded'] > 0): ?>
                            <tr><td>Refunded</td><td><?php echo formatPrice($payment['amount_refunded']); ?></td></tr>
                        <?php endif; ?>
                        <tr><td>Gateway order id</td><td><code><?php echo e($payment['gateway_order_id'] ?: '—'); ?></code></td></tr>
                        <tr><td>Gateway payment id</td><td><code><?php echo e($payment['gateway_payment_id'] ?: '—'); ?></code></td></tr>
                        <tr><td>Transaction id</td><td><code><?php echo e($payment['transaction_id'] ?: '—'); ?></code></td></tr>
                        <tr><td>Created</td><td><?php echo date('d M Y, H:i', strtotime($payment['created_at'])); ?></td></tr>
                        <tr><td>Paid at</td><td><?php echo $payment['paid_at'] ? date('d M Y, H:i', strtotime($payment['paid_at'])) : '—'; ?></td></tr>
                        <tr><td>Fulfilled at</td><td><?php echo $order['fulfilled_at'] ? date('d M Y, H:i', strtotime($order['fulfilled_at'])) : '<span class="muted">not yet</span>'; ?></td></tr>
                        <?php if ($payment['failure_reason']): ?>
                            <tr><td>Failure reason</td><td><span class="text-danger"><?php echo e($payment['failure_reason']); ?></span></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if (count($attempts) > 1): ?>
                    <details class="mt-3">
                        <summary>Earlier attempts (<?php echo count($attempts) - 1; ?>)</summary>
                        <table class="admin__table mt-2">
                            <thead><tr><th>#</th><th>Gateway</th><th>Status</th><th>Reference</th><th>When</th></tr></thead>
                            <tbody>
                            <?php foreach (array_slice($attempts, 1) as $a): ?>
                                <tr>
                                    <td><?php echo (int) $a['id']; ?></td>
                                    <td><?php echo e($a['gateway']); ?></td>
                                    <td><?php echo status_pill($a['status']); ?></td>
                                    <td><code><?php echo e($a['gateway_order_id'] ?: '—'); ?></code></td>
                                    <td><?php echo date('d M Y, H:i', strtotime($a['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </details>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- ============ Refunds ============ -->
        <?php if ($payment && ($refundable || $refunds)): ?>
        <div class="admin__card" id="refundCard" data-payment="<?php echo (int) $payment['id']; ?>">
            <h3>Refunds</h3>

            <?php if ($refunds): ?>
                <table class="admin__table">
                    <thead><tr><th>Amount</th><th>Status</th><th>Reference</th><th>Reason</th><th>When</th></tr></thead>
                    <tbody>
                    <?php foreach ($refunds as $r): ?>
                        <tr>
                            <td><?php echo formatPrice($r['amount']); ?></td>
                            <td><?php echo status_pill($r['status']); ?></td>
                            <td><code><?php echo e($r['gateway_refund_id'] ?: '—'); ?></code></td>
                            <td><?php echo e($r['reason']); ?></td>
                            <td><?php echo date('d M Y, H:i', strtotime($r['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ($refundable): ?>
                <form id="refundForm" class="filters mt-3" autocomplete="off">
                    <div class="field">
                        <label>Amount (blank = full <?php echo formatPrice($available); ?>)</label>
                        <input type="number" step="0.01" min="0.01" max="<?php echo $available; ?>" name="amount" placeholder="<?php echo $available; ?>">
                    </div>
                    <div class="field" style="flex:1 1 240px">
                        <label>Reason</label>
                        <input type="text" name="reason" maxlength="200" placeholder="e.g. Customer returned the item">
                    </div>
                    <button class="btn" type="submit" id="refundBtn">Refund</button>
                </form>
                <p class="muted" style="margin-top:8px">
                    <?php echo formatPrice($available); ?> is still available to refund through
                    <?php echo e($gatewayLabels[$payment['gateway']] ?? $payment['gateway']); ?>.
                </p>
            <?php elseif ($payment['gateway'] === 'cod'): ?>
                <p class="muted">Cash-on-delivery orders are refunded outside the gateway.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ============ Gateway activity ============ -->
        <?php if ($logs): ?>
        <div class="admin__card">
            <h3>Gateway Activity</h3>
            <p class="muted" style="margin-top:-6px">Diagnostic trail only — no card, UPI or key data is ever recorded.</p>
            <table class="admin__table">
                <thead><tr><th>When</th><th>Gateway</th><th>Event</th><th>Reference</th><th>Note</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td><?php echo date('d M, H:i:s', strtotime($l['created_at'])); ?></td>
                        <td><?php echo e($l['gateway']); ?></td>
                        <td><code><?php echo e($l['event']); ?></code></td>
                        <td><code><?php echo e($l['reference'] ?: '—'); ?></code></td>
                        <td><?php echo e($l['error_message'] ?: ($l['status'] ?: '—')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div>
        <div class="admin__card">
            <h3>Status</h3>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="do" value="status">
                <div class="field">
                    <?php /* only the steps that may follow this one — see includes/workflow.php */ ?>
                    <?php echo workflow_select('order', $order['order_status']); ?>
                </div>
                <?php if (workflow_is_final('order', $order['order_status'])): ?>
                    <p class="hint">This order is <?php echo e(workflow_label($order['order_status'])); ?> — its status cannot change any further.</p>
                <?php else: ?>
                    <button class="btn" type="submit">Update Status</button>
                <?php endif; ?>
            </form>

            <form method="post" style="margin-top:14px">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="do" value="payment">
                <div class="field"><label>Payment</label>
                    <select name="payment_status">
                        <?php foreach (['pending', 'paid', 'failed', 'cancelled', 'refunded'] as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo $order['payment_status'] === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn--ghost" type="submit">Update Payment</button>
                <p class="muted" style="margin-top:8px;font-size:12px">
                    Online payments are confirmed by the gateway. Use this for cash or bank transfer only.
                    <?php /* Refunded here books a refund settled outside the gateway; the
                             Refund panel above is the one that actually moves money. */ ?>
                    Marking <strong>Refunded</strong> records a refund you have already paid
                    back by hand &mdash; it does not ask the gateway for one.
                </p>
            </form>
        </div>

        <div class="admin__card">
            <h3>Customer</h3>
            <p><?php echo e($customer['name']); ?><br><?php echo e($customer['email']); ?><br><?php echo e($customer['phone']); ?></p>
            <h4>Shipping Address</h4>
            <p><?php echo e($order['shipping_name']); ?><br><?php echo e($order['shipping_phone']); ?><br><?php echo nl2br(e($order['shipping_address'])); ?></p>
            <p><strong>Payment method:</strong> <?php echo e(payment_method_label($order)); ?></p>
            <p><strong>Payment status:</strong> <?php echo e($payState['label']); ?></p>
            <?php if ($order['notes']): ?><p><strong>Notes:</strong> <?php echo nl2br(e($order['notes'])); ?></p><?php endif; ?>
        </div>
    </div>
</div>
<a class="btn btn--ghost" href="orders.php">Back to orders</a>

<?php if ($payment && $refundable): ?>
<script>
(function () {
    var card = document.getElementById('refundCard');
    var form = document.getElementById('refundForm');
    var btn  = document.getElementById('refundBtn');
    if (!form) { return; }

    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var amount = form.amount.value.trim();
        var label  = amount === '' ? 'the full remaining amount' : amount;
        if (!confirm('Refund ' + label + '? This cannot be undone.')) { return; }

        btn.disabled = true;
        btn.textContent = 'Refunding…';

        var body = new URLSearchParams();
        body.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        body.append('payment_id', card.dataset.payment);
        body.append('amount', amount);
        body.append('reason', form.reason.value);

        fetch('../ajax/admin/refund.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: body,
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (window.adminToast) { window.adminToast(res.success ? 'ok' : 'err', res.message); }
            else { alert(res.message); }
            if (res.success) { setTimeout(function () { location.reload(); }, 900); return; }
            btn.disabled = false;
            btn.textContent = 'Refund';
        })
        .catch(function () {
            if (window.adminToast) { window.adminToast('err', 'The refund request could not be sent.'); }
            btn.disabled = false;
            btn.textContent = 'Refund';
        });
    });
})();
</script>
<?php endif; ?>
<?php admin_layout_end();
