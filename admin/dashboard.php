<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/../includes/products.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/engagement.php';
require_once __DIR__ . '/includes/charts.php';
requireAdmin();

$db = getDB();
$q = function ($sql, $params = []) use ($db) {
    try { $s = $db->prepare($sql); $s->execute($params); return $s->fetchColumn(); }
    catch (Throwable $e) { return 0; }
};

/* ---- KPI values + 7-day trend deltas ---- */
$rev7   = (float) $q("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE order_status NOT IN ('cancelled','refunded') AND created_at >= (NOW() - INTERVAL 7 DAY)");
$revPrev7 = (float) $q("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE order_status NOT IN ('cancelled','refunded') AND created_at >= (NOW() - INTERVAL 14 DAY) AND created_at < (NOW() - INTERVAL 7 DAY)");
$ord7   = (int) $q("SELECT COUNT(*) FROM orders WHERE created_at >= (NOW() - INTERVAL 7 DAY)");
$ordPrev7 = (int) $q("SELECT COUNT(*) FROM orders WHERE created_at >= (NOW() - INTERVAL 14 DAY) AND created_at < (NOW() - INTERVAL 7 DAY)");

$totalSales   = (float) $q("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE order_status NOT IN ('cancelled','refunded')");
$orderCount   = (int) $q('SELECT COUNT(*) FROM orders');
$customerCount = (int) $q('SELECT COUNT(*) FROM users');
$aov = $orderCount ? $totalSales / $orderCount : 0;
$pendingOrders = (int) $q("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'");
$lowStock     = (int) $q('SELECT COUNT(*) FROM products WHERE stock_quantity <= 3');
$pendingReviews = (int) $q("SELECT COUNT(*) FROM reviews WHERE status = 'pending'");
$newEnquiries = (int) $q("SELECT COUNT(*) FROM enquiries WHERE status = 'new'");
$pendingAppts = (int) $q("SELECT COUNT(*) FROM appointments WHERE status = 'pending'");
$newFeedback  = (int) $q("SELECT COUNT(*) FROM feedback WHERE status = 'new'");
$visits = visit_totals();

function pct_delta($now, $prev) {
    if ($prev <= 0) return $now > 0 ? ['+100%', 'up'] : ['0%', 'flat'];
    $d = round((($now - $prev) / $prev) * 100);
    return [($d >= 0 ? '+' : '') . $d . '%', $d > 0 ? 'up' : ($d < 0 ? 'down' : 'flat')];
}
[$revDelta, $revDir] = pct_delta($rev7, $revPrev7);
[$ordDelta, $ordDir] = pct_delta($ord7, $ordPrev7);

/* ---- 14-day revenue + orders series ---- */
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i day"))] = ['label' => date('j M', strtotime("-$i day")), 'rev' => 0, 'ord' => 0];
}
try {
    $rows = $db->query("SELECT DATE(created_at) d, COUNT(*) c, COALESCE(SUM(total_amount),0) s
        FROM orders WHERE created_at >= (CURDATE() - INTERVAL 13 DAY) AND order_status NOT IN ('cancelled','refunded')
        GROUP BY DATE(created_at)")->fetchAll();
    foreach ($rows as $r) {
        if (isset($days[$r['d']])) { $days[$r['d']]['rev'] = (float) $r['s']; $days[$r['d']]['ord'] = (int) $r['c']; }
    }
} catch (Throwable $e) {}
$revSeries = array_values(array_map(fn($d) => $d['rev'], $days));
$barData = array_map(fn($d) => ['label' => $d['label'], 'value' => round($d['rev'])], array_values($days));
// thin the bar labels
foreach ($barData as $i => &$b) { if ($i % 2) $b['label'] = ''; } unset($b);

/* ---- order status donut ---- */
$statusColors = ['pending' => '#d99a2b', 'confirmed' => '#3b82f6', 'processing' => '#6366f1', 'shipped' => '#8b5cf6', 'delivered' => '#16a34a', 'cancelled' => '#dc2626', 'refunded' => '#9ca3af'];
$donutSegs = [];
try {
    foreach ($db->query('SELECT order_status, COUNT(*) c FROM orders GROUP BY order_status') as $r) {
        $donutSegs[] = ['label' => ucfirst($r['order_status']), 'value' => (int) $r['c'], 'color' => $statusColors[$r['order_status']] ?? '#9ca3af'];
    }
} catch (Throwable $e) {}

$recentOrders = [];
$topProducts = [];
try {
    $recentOrders = $db->query('SELECT o.*, u.name AS customer FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC LIMIT 6')->fetchAll();
    $topProducts = $db->query('SELECT oi.product_name, SUM(oi.quantity) qty, SUM(oi.subtotal) rev
        FROM order_items oi JOIN orders o ON o.id = oi.order_id
        WHERE o.order_status NOT IN ("cancelled","refunded")
        GROUP BY oi.product_name ORDER BY qty DESC LIMIT 5')->fetchAll();
} catch (Throwable $e) {}

$adminPageTitle = 'Dashboard';
$adminNav = 'dashboard';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
$adminNm = currentAdmin()['name'] ?? 'Admin';
admin_page_head('Good day, ' . explode(' ', $adminNm)[0], 'Here is what is happening across your store today.', '<a class="btn btn--ghost" href="reports.php">Full reports</a>');
?>
<div class="kpi__grid">
    <div class="kpi spot">
        <div class="kpi__head"><span>Revenue (7 days)</span><span class="kpi__delta kpi__delta--<?php echo $revDir; ?>"><?php echo $revDelta; ?></span></div>
        <div class="kpi__value"><?php echo formatPrice($rev7, 0); ?></div>
        <?php echo chart_spark($revSeries, 240, 46, THEME_PRIMARY); ?>
    </div>
    <div class="kpi spot">
        <div class="kpi__head"><span>Orders (7 days)</span><span class="kpi__delta kpi__delta--<?php echo $ordDir; ?>"><?php echo $ordDelta; ?></span></div>
        <div class="kpi__value"><?php echo (int) $ord7; ?></div>
        <?php echo chart_spark(array_map(fn($d) => $d['ord'], array_values($days)), 240, 46, '#3b82f6'); ?>
    </div>
    <div class="kpi spot">
        <div class="kpi__head"><span>Avg Order Value</span></div>
        <div class="kpi__value"><?php echo formatPrice($aov, 0); ?></div>
        <div class="kpi__sub"><?php echo (int) $orderCount; ?> orders all-time &middot; <?php echo formatPrice($totalSales, 0); ?></div>
    </div>
    <div class="kpi spot">
        <div class="kpi__head"><span>Site Visits</span></div>
        <div class="kpi__value"><?php echo number_format($visits['total']); ?></div>
        <div class="kpi__sub"><?php echo number_format($visits['today']); ?> today &middot; <?php echo (int) $customerCount; ?> members</div>
    </div>
</div>

<div class="bento">
    <div class="admin__card b-8">
        <div class="admin__card--head"><h3>Revenue &mdash; last 14 days</h3><span class="admin__muted"><?php echo formatPrice(array_sum($revSeries), 0); ?> total</span></div>
        <?php echo chart_bars($barData, THEME_PRIMARY, 200); ?>
    </div>
    <div class="admin__card b-4">
        <div class="admin__card--head"><h3>Orders by status</h3></div>
        <?php echo chart_donut($donutSegs); ?>
    </div>
</div>

<div class="action__strip">
    <a class="action__chip <?php echo $pendingOrders ? 'is-hot' : ''; ?>" href="orders.php?status=pending"><b><?php echo $pendingOrders; ?></b> pending orders</a>
    <a class="action__chip <?php echo $newEnquiries ? 'is-hot' : ''; ?>" href="enquiries.php?status=new"><b><?php echo $newEnquiries; ?></b> new enquiries</a>
    <a class="action__chip <?php echo $pendingAppts ? 'is-hot' : ''; ?>" href="appointments.php?status=pending"><b><?php echo $pendingAppts; ?></b> appointment requests</a>
    <a class="action__chip <?php echo $newFeedback ? 'is-hot' : ''; ?>" href="feedback.php?status=new"><b><?php echo $newFeedback; ?></b> new feedback</a>
    <a class="action__chip <?php echo $pendingReviews ? 'is-hot' : ''; ?>" href="reviews.php?status=pending"><b><?php echo $pendingReviews; ?></b> reviews to moderate</a>
    <a class="action__chip <?php echo $lowStock ? 'is-hot' : ''; ?>" href="inventory.php"><b><?php echo $lowStock; ?></b> low stock</a>
</div>

<div class="bento">
    <div class="admin__card b-8">
        <div class="admin__card--head"><h3>Recent orders</h3><a class="btn btn--ghost btn--sm" href="orders.php">All orders</a></div>
        <table class="admin__table">
            <thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Status</th><th>Total</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($recentOrders as $o): ?>
                <tr>
                    <td><?php echo e($o['order_number']); ?></td>
                    <td><?php echo e($o['customer']); ?></td>
                    <td><?php echo date('d M', strtotime($o['created_at'])); ?></td>
                    <td><?php echo status_pill($o['order_status']); ?></td>
                    <td><?php echo formatPrice($o['total_amount']); ?></td>
                    <td><a class="btn btn--sm btn--ghost" href="order-view.php?id=<?php echo (int) $o['id']; ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recentOrders): ?><tr><td colspan="6" class="admin__muted">No orders yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="admin__card b-4">
        <div class="admin__card--head"><h3>Top products</h3></div>
        <ol class="rank__list">
            <?php foreach ($topProducts as $t): ?>
                <li><span class="rank__name"><?php echo e($t['product_name']); ?></span><span class="rank__val"><?php echo (int) $t['qty']; ?> sold</span></li>
            <?php endforeach; ?>
            <?php if (!$topProducts): ?><li class="admin__muted">No sales yet.</li><?php endif; ?>
        </ol>
    </div>
</div>
<?php admin_layout_end();
