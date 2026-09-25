<?php
/**
 * Payment transactions across every gateway.
 * Read-only list — money is moved from the order screen, never from here.
 */

require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/includes/charts.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/payments/manager.php';
requireAdmin();

$db = getDB();

$gatewayLabels = array_map(fn($g) => $g['label'], pay_gateways());
$statuses      = ['pending', 'processing', 'paid', 'failed', 'cancelled', 'refunded', 'partially_refunded'];

$fGateway = (string) ($_GET['gateway'] ?? '');
$fStatus  = (string) ($_GET['status'] ?? '');
/* Only real YYYY-MM-DD values reach the query, and a reversed range is put back
   in order rather than silently returning nothing. */
$ymd      = fn($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) && strtotime((string) $v) ? (string) $v : '';
$fFrom    = $ymd($_GET['from'] ?? '');
$fTo      = $ymd($_GET['to'] ?? '');
if ($fFrom !== '' && $fTo !== '' && $fTo < $fFrom) {
    [$fFrom, $fTo] = [$fTo, $fFrom];
}
$q        = trim((string) ($_GET['q'] ?? ''));

$where = ['1=1'];
$params = [];

if (isset($gatewayLabels[$fGateway])) { $where[] = 'p.gateway = ?'; $params[] = $fGateway; }
if (in_array($fStatus, $statuses, true)) { $where[] = 'p.status = ?'; $params[] = $fStatus; }
if ($fFrom !== '') { $where[] = 'p.created_at >= ?'; $params[] = $fFrom . ' 00:00:00'; }
if ($fTo !== '')   { $where[] = 'p.created_at <= ?'; $params[] = $fTo . ' 23:59:59'; }
if ($q !== '') {
    $where[] = '(o.order_number LIKE ? OR p.gateway_payment_id LIKE ? OR p.gateway_order_id LIKE ? OR p.transaction_id LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
    for ($i = 0; $i < 6; $i++) { $params[] = "%$q%"; }
}

/* refund_open counts refunds the gateway has accepted but not yet settled.
   The payment already reads Refunded — that is what the customer is told the
   moment the gateway accepts — so this is what separates a refund that has
   landed from one still on its way back to them. */
$sql = 'SELECT p.*, o.order_number, o.order_status, u.name AS customer, u.email,
               (SELECT COUNT(*) FROM payment_refunds r
                 WHERE r.payment_id = p.id AND r.status IN (\'pending\', \'processing\')) AS refund_open
          FROM payments p
          JOIN orders o ON o.id = p.order_id
          LEFT JOIN users u ON u.id = o.user_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY p.id DESC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* ---- headline numbers for the filtered set ---- */
$sumPaid = 0.0; $sumRefund = 0.0; $countPaid = 0; $countFailed = 0; $countOpen = 0;
$byGateway = [];
foreach ($rows as $r) {
    if (in_array($r['status'], ['paid', 'partially_refunded', 'refunded'], true)) {
        $sumPaid += (float) $r['amount'];
        $countPaid++;
        $byGateway[$r['gateway']] = ($byGateway[$r['gateway']] ?? 0) + (float) $r['amount'];
    }
    $sumRefund += (float) $r['amount_refunded'];
    if ($r['status'] === 'failed') { $countFailed++; }
    if (in_array($r['status'], ['pending', 'processing'], true)) { $countOpen++; }
}
arsort($byGateway);

$rows = admin_list($rows, [
    'date'     => 'created_at',
    'order'    => 'order_number',
    'customer' => 'customer',
    'gateway'  => 'gateway',
    'status'   => 'status',
    'amount'   => fn($r) => (float) $r['amount'],
], 'date:desc', 20);
$total = admin_list_total();

$adminPageTitle = 'Payments';
$adminNav = 'payments';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
admin_page_head(
    'Payments',
    'Every transaction across your gateways &mdash; currently in ' . pay_mode() . ' mode.',
    '<a class="btn btn--ghost" href="payment-settings.php">Gateway settings</a>'
);

echo admin_stat_cards([
    ['label' => 'Captured', 'value' => formatPrice($sumPaid, 0), 'icon' => 'rupee', 'tone' => 'brand',
     'hint' => $countPaid . ' payment' . ($countPaid === 1 ? '' : 's')],
    ['label' => 'Refunded', 'value' => formatPrice($sumRefund, 0), 'icon' => 'reset', 'tone' => 'blue', 'hint' => 'Across all gateways'],
    ['label' => 'Awaiting Confirmation', 'value' => number_format($countOpen), 'icon' => 'clock', 'tone' => 'amber', 'hint' => 'Pending or processing'],
    ['label' => 'Failed', 'value' => number_format($countFailed), 'icon' => 'draft', 'tone' => 'red', 'hint' => 'No money taken'],
]);
?>

<?php if ($byGateway): ?>
<div class="admin__card">
    <h3>Captured by gateway</h3>
    <ul class="gw__rows">
        <?php foreach ($byGateway as $g => $amt): ?>
            <li><span><?php echo e($gatewayLabels[$g] ?? strtoupper($g)); ?></span><span><strong><?php echo formatPrice($amt); ?></strong></span></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php
echo admin_filter_open();
echo admin_filter_search('q', $q, 'Order #, txn id or customer…');
?>
    <div class="field"><label>Gateway</label>
        <select name="gateway">
            <option value="">All Gateways</option>
            <?php foreach ($gatewayLabels as $k => $label): ?>
                <option value="<?php echo e($k); ?>" <?php echo $fGateway === $k ? 'selected' : ''; ?>><?php echo e($label); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field"><label>Status</label>
        <select name="status">
            <option value="">All Status</option>
            <?php foreach ($statuses as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $fStatus === $s ? 'selected' : ''; ?>><?php echo ucwords(str_replace('_', ' ', $s)); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php /* Both fields: same width, same placeholder wording, and tied to each
             other so To can never be earlier than From (assets/js/datepicker.js).
             Deliberately not defaulted to today — that would hide every older
             payment behind a filter nobody set. */ ?>
    <div class="field field--date"><label>From</label>
        <input type="text" data-datepicker name="from" data-max="<?php echo date('Y-m-d'); ?>"
               data-max-input="to" value="<?php echo e($fFrom); ?>" placeholder="Any date"></div>
    <div class="field field--date"><label>To</label>
        <input type="text" data-datepicker name="to" data-max="<?php echo date('Y-m-d'); ?>"
               data-min-input="from" value="<?php echo e($fTo); ?>" placeholder="Any date"></div>
<?php
echo admin_filter_close('payments.php');
?>
<div class="admin__card">
    <?php echo admin_list_head('Payments', $total); ?>
    <table class="admin__table">
        <thead><tr>
            <?php echo admin_th('date', 'Date'); ?>
            <?php echo admin_th('order', 'Order'); ?>
            <?php echo admin_th('customer', 'Customer'); ?>
            <?php echo admin_th('gateway', 'Gateway'); ?>
            <th>Reference</th>
            <?php echo admin_th('status', 'Status'); ?>
            <?php echo admin_th('amount', 'Amount'); ?>
            <th class="col-act">Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?php echo date('d M Y', strtotime($r['created_at'])); ?><br><small class="muted"><?php echo date('H:i', strtotime($r['created_at'])); ?></small></td>
                <td><?php echo e($r['order_number']); ?></td>
                <td><?php echo e($r['customer'] ?: '—'); ?><br><small class="muted"><?php echo e($r['email']); ?></small></td>
                <td><?php echo e($gatewayLabels[$r['gateway']] ?? strtoupper((string) $r['gateway'])); ?>
                    <?php if ($r['payment_method']): ?><br><small class="muted"><?php echo e(strtoupper($r['payment_method'])); ?></small><?php endif; ?>
                </td>
                <td><code><?php echo e($r['gateway_payment_id'] ?: ($r['gateway_order_id'] ?: '—')); ?></code></td>
                <td><?php echo status_pill($r['status']); ?>
                    <?php if ((int) $r['refund_open'] > 0): ?><br><small class="muted">refund submitted, awaiting the gateway</small><?php endif; ?>
                    <?php if ($r['failure_reason'] && $r['status'] !== 'paid'): ?><br><small class="muted"><?php echo e(mb_strimwidth($r['failure_reason'], 0, 46, '…')); ?></small><?php endif; ?>
                </td>
                <td><?php echo formatPrice($r['amount']); ?>
                    <?php if ((float) $r['amount_refunded'] > 0): ?><br><small class="muted">- <?php echo formatPrice($r['amount_refunded']); ?> refunded</small><?php endif; ?>
                </td>
                <?php echo admin_acts_open(); ?>
                    <?php echo admin_act_link('order-view.php?id=' . (int) $r['order_id'], 'edit', 'Open order'); ?>
                <?php echo admin_acts_close(); ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8">No payments match these filters.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php echo admin_pager(); ?>
</div>
<?php admin_layout_end();
