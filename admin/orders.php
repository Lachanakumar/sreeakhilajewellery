<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/includes/charts.php';
requireAdmin();

$db = getDB();

/* Apply used to sit behind a bare csrf_verify(): an expired token skipped the
   whole block and re-rendered the page silently, which reads as "Bulk Action
   does nothing". admin_post_ok() says so instead. */
if (admin_post_ok('orders.php') && ($_POST['do'] ?? '') === 'bulk') {
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])))));
    $act = $_POST['bulk_action'] ?? '';

    if (!$ids) {
        admin_err('No orders were selected — tick the rows you want to act on.');
    } elseif (!in_array($act, ORDER_STATUSES, true)) {
        admin_err('Choose an action to apply.');
    } else {
        /* One order at a time through updateOrderStatus(), not a blanket
           UPDATE. That function is what enforces the forward-only workflow,
           restocks a cancelled or refunded order and keeps payment_status in
           step — the blanket UPDATE did none of it, so a bulk cancel quietly
           left the stock held. Orders that cannot legally reach $act are left
           untouched, and are now named rather than just counted. */
        $done = 0; $blocked = [];
        foreach ($ids as $oid) {
            if (updateOrderStatus($oid, $act)) {
                $done++;
                continue;
            }
            $row = getOrder($oid);
            $blocked[] = $row ? $row['order_number'] . ' (' . workflow_label($row['order_status']) . ')' : '#' . $oid;
        }

        if ($done) {
            admin_ok($done . ' of ' . count($ids) . ' order(s) moved to ' . workflow_label($act) . '.');
        }
        if ($blocked) {
            // naming them is the difference between "it did nothing" and
            // "these particular orders are already past that step"
            $names = array_slice($blocked, 0, 5);
            admin_warn(count($blocked) . ' order(s) could not move to ' . workflow_label($act) . ': '
                . implode(', ', $names) . (count($blocked) > 5 ? ' and ' . (count($blocked) - 5) . ' more' : '')
                . '. An order only moves forward through the workflow.');
        }
    }
    redirect('orders.php' . admin_qs());
}

$statusFilter = $_GET['status'] ?? '';
$q = trim($_GET['q'] ?? '');
$where = ['1=1']; $params = [];
if (in_array($statusFilter, ORDER_STATUSES, true)) { $where[] = 'o.order_status = ?'; $params[] = $statusFilter; }
if ($q !== '') { $where[] = '(o.order_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }

$stmt = $db->prepare('SELECT o.*, u.name AS customer, u.email FROM orders o JOIN users u ON u.id = o.user_id WHERE ' . implode(' AND ', $where) . ' ORDER BY o.created_at DESC');
$stmt->execute($params);
$allRows = $stmt->fetchAll();

/* A row is written before the customer is sent to the gateway, so every
   abandoned UPI/card attempt — "Authentication failed", modal closed, bank
   declined — left a pending order sitting in this list as if it had been
   placed. Nothing was ever paid, stocked or fulfilled for those. They are kept
   (the payment trail matters) but held behind ?show=unpaid rather than padding
   the working list. order_is_abandoned_payment() is the same test the customer's
   own order list uses, so neither side invents its own idea of a real order. */
$showUnpaid = ($_GET['show'] ?? '') === 'unpaid';
$orders = [];
$unpaidCount = 0;
foreach ($allRows as $row) {
    if (order_is_abandoned_payment($row)) {
        $unpaidCount++;
        if (!$showUnpaid) {
            continue;
        }
    }
    $orders[] = $row;
}

$orders = admin_list($orders, [
    'order'    => 'order_number',
    'customer' => 'customer',
    'date'     => 'created_at',
    'payment'  => 'payment_status',
    'status'   => 'order_status',
    'total'    => fn($o) => (float) $o['total_amount'],
], 'date:desc', 20);
$total = admin_list_total();

$oc = function ($sql) use ($db) {
    try { return $db->query($sql)->fetchColumn(); } catch (Throwable $e) { return 0; }
};
/* The same exclusion the list applies, in SQL: an order that was never
   fulfilled, is not COD and never got past a failed/cancelled/pending online
   payment is a checkout that did not complete, so counting it as an order (or
   as work "awaiting action") overstated both. */
$notAbandoned = "NOT (o.fulfilled_at IS NULL
                      AND LOWER(COALESCE(NULLIF(o.payment_gateway,''), o.payment_method)) <> 'cod'
                      AND o.payment_status IN ('failed','cancelled','pending')
                      AND o.order_status = 'pending')";

$ordAll     = (int) $oc("SELECT COUNT(*) FROM orders o WHERE $notAbandoned");
$ordPending = (int) $oc("SELECT COUNT(*) FROM orders o WHERE o.order_status = 'pending' AND $notAbandoned");
$ordShipped = (int) $oc("SELECT COUNT(*) FROM orders o WHERE o.order_status IN ('shipped','delivered')");
$ordRevenue = (float) $oc("SELECT COALESCE(SUM(o.total_amount),0) FROM orders o WHERE o.order_status NOT IN ('cancelled','refunded') AND $notAbandoned");

$adminPageTitle = 'Orders';
$adminNav = 'orders';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
admin_page_head('Orders', 'Track, fulfil and update customer orders.');

echo admin_stat_cards([
    ['label' => 'Total Orders', 'value' => number_format($ordAll), 'icon' => 'cart', 'tone' => 'brand'],
    ['label' => 'Fulfilled', 'value' => number_format($ordShipped), 'icon' => 'check', 'tone' => 'green', 'hint' => 'Shipped or delivered'],
    ['label' => 'Awaiting Action', 'value' => number_format($ordPending), 'icon' => 'clock', 'tone' => 'amber', 'hint' => 'Still pending'],
    ['label' => 'Net Revenue', 'value' => formatPrice($ordRevenue, 0), 'icon' => 'rupee', 'tone' => 'violet', 'hint' => 'Excludes cancelled & refunded'],
]);

echo admin_filter_open();
echo admin_filter_search('q', $q, 'Order #, name or email…');
?>
    <div class="field"><label>Status</label>
        <select name="status">
            <option value="">All Status</option>
            <?php foreach (ORDER_STATUSES as $s): ?><option value="<?php echo $s; ?>" <?php echo $statusFilter === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option><?php endforeach; ?>
        </select>
    </div>
<?php
echo admin_filter_close('orders.php');

/* Only the moves at least one listed order can actually make. The menu used to
   offer all seven statuses regardless — including "Mark Pending", which no
   order can ever go back to — so picking one and clicking Apply changed
   nothing and looked broken. The workflow map is the authority either way;
   this just stops offering what it will refuse. */
$reachable = [];
foreach ($orders as $o) {
    foreach (workflow_next('order', $o['order_status']) as $next) {
        $reachable[$next] = true;
    }
}
// listed in workflow order, not in whichever order the rows happened to fall
$bulk = [];
foreach (ORDER_STATUSES as $s) {
    if (isset($reachable[$s])) {
        $bulk[$s] = 'Mark ' . workflow_label($s);
    }
}
?>
<?php if ($unpaidCount): ?>
    <div class="admin__card" style="margin-bottom:16px">
        <?php if ($showUnpaid): ?>
            <p style="margin:0">Showing <strong><?php echo number_format($unpaidCount); ?></strong> incomplete checkout<?php echo $unpaidCount === 1 ? '' : 's'; ?> alongside the real orders. Nothing was charged or reserved for these.
                <a href="<?php echo e(admin_qs(['show' => null, 'page' => null])); ?>">Hide them</a>.</p>
        <?php else: ?>
            <p style="margin:0"><strong><?php echo number_format($unpaidCount); ?></strong> checkout<?php echo $unpaidCount === 1 ? ' was' : 's were'; ?> started but never paid for — a failed or abandoned online payment. They are not counted as orders.
                <a href="<?php echo e(admin_qs(['show' => 'unpaid', 'page' => null])); ?>">Show them</a>.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>
<div class="admin__card">
    <?php echo admin_list_head('Orders', $total, admin_bulk_bar($bulk)); ?>
    <table class="admin__table">
        <thead><tr>
            <?php echo admin_check_all(); ?>
            <?php echo admin_th('order', 'Order'); ?>
            <?php echo admin_th('customer', 'Customer'); ?>
            <?php echo admin_th('date', 'Date'); ?>
            <?php echo admin_th('payment', 'Payment'); ?>
            <?php echo admin_th('status', 'Status'); ?>
            <?php echo admin_th('total', 'Total'); ?>
            <th class="col-act">Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($orders as $o): $oid = (int) $o['id']; ?>
            <tr>
                <?php echo admin_check_row($oid); ?>
                <td><strong><?php echo e($o['order_number']); ?></strong></td>
                <td>
                    <div class="cell__title"><strong><?php echo e($o['customer']); ?></strong>
                        <span class="cell__sub"><?php echo e($o['email']); ?></span></div>
                </td>
                <td><?php echo date('d M Y', strtotime($o['created_at'])); ?></td>
                <?php /* payment_status_label() rather than the raw column: a cancelled
                         order used to read "Pending" as if money were still due, and a
                         COD order that followed a failed card attempt read "Failed".
                         Same helper the customer's order page uses, so the two agree. */ ?>
                <?php $pay = payment_status_label($o); ?>
                <td><?php echo e(payment_method_label($o)); ?><br><span class="cell__sub"><?php echo e($pay['label']); ?></span></td>
                <td><?php echo status_pill($o['order_status']); ?>
                    <?php if (order_is_abandoned_payment($o)): ?><br><span class="cell__sub">never paid</span><?php endif; ?></td>
                <td><span class="price__now"><?php echo formatPrice($o['total_amount']); ?></span></td>
                <?php echo admin_acts_open(); ?>
                    <?php echo admin_act_link('order-view.php?id=' . $oid, 'edit', 'Manage order'); ?>
                    <?php echo admin_act_menu([
                        ['label' => 'Open order', 'href' => 'order-view.php?id=' . $oid, 'icon' => 'external'],
                        ['label' => 'View customer', 'href' => 'customer-view.php?id=' . (int) $o['user_id'], 'icon' => 'users'],
                    ]); ?>
                <?php echo admin_acts_close(); ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?><tr><td colspan="8">No orders match these filters.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php echo admin_pager(); ?>
</div>
<?php admin_layout_end();
