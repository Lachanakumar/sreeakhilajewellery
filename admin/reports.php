<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/charts.php';
requireAdmin();

$db = getDB();

/* The picker keeps To >= From on screen, but a hand-edited URL or a client
   without JS can still arrive reversed or malformed — BETWEEN would then
   quietly report an empty range instead of an obvious error. */
$ymd  = fn($v, $fallback) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) && strtotime((string) $v)
    ? (string) $v : $fallback;
$from = $ymd($_GET['from'] ?? null, date('Y-m-01'));
$to   = $ymd($_GET['to'] ?? null, date('Y-m-d'));
if ($to < $from) {
    $to = $from;
}
$rangeStart = $from . ' 00:00:00';
$rangeEnd   = $to . ' 23:59:59';

$paidClause = "order_status NOT IN ('cancelled','refunded')";

$summary = $db->prepare("SELECT COUNT(*) AS orders, COALESCE(SUM(total_amount),0) AS revenue,
    COALESCE(SUM(discount_amount),0) AS discounts, COALESCE(AVG(total_amount),0) AS aov
    FROM orders WHERE $paidClause AND created_at BETWEEN ? AND ?");
$summary->execute([$rangeStart, $rangeEnd]);
$s = $summary->fetch();

$byDay = $db->prepare("SELECT DATE(created_at) d, COUNT(*) orders, SUM(total_amount) revenue
    FROM orders WHERE $paidClause AND created_at BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY d");
$byDay->execute([$rangeStart, $rangeEnd]);
$days = $byDay->fetchAll();

/* What actually sold on each of those days, so Daily Sales can show the line
   items behind a day's total rather than just the total. Grouped by sku as
   well as name: two SKUs may share a product name, and ONLY_FULL_GROUP_BY
   rejects selecting a column that is not grouped. */
$byDayItems = $db->prepare("SELECT DATE(o.created_at) d, oi.product_name, oi.sku,
    SUM(oi.quantity) qty, SUM(oi.subtotal) revenue
    FROM order_items oi JOIN orders o ON o.id = oi.order_id
    WHERE o.$paidClause AND o.created_at BETWEEN ? AND ?
    GROUP BY DATE(o.created_at), oi.product_name, oi.sku
    ORDER BY d, qty DESC, oi.product_name");
$byDayItems->execute([$rangeStart, $rangeEnd]);
$dayProducts = [];
foreach ($byDayItems->fetchAll() as $it) {
    $dayProducts[$it['d']][] = $it;
}

$topProducts = $db->prepare("SELECT oi.product_name, SUM(oi.quantity) qty, SUM(oi.subtotal) revenue
    FROM order_items oi JOIN orders o ON o.id = oi.order_id
    WHERE o.$paidClause AND o.created_at BETWEEN ? AND ?
    GROUP BY oi.product_name ORDER BY qty DESC LIMIT 10");
$topProducts->execute([$rangeStart, $rangeEnd]);
$top = $topProducts->fetchAll();

/* Scoped to the picked range like every other panel on this page — it used to
   count every order ever placed, so the figures never moved when the filter
   did. No $paidClause here: cancelled and refunded are the point of a status
   breakdown, not noise to leave out. */
$statusQ = $db->prepare('SELECT order_status, COUNT(*) c, COALESCE(SUM(total_amount),0) revenue
    FROM orders WHERE created_at BETWEEN ? AND ? GROUP BY order_status');
$statusQ->execute([$rangeStart, $rangeEnd]);
$statusBreak = $statusQ->fetchAll();

$days = admin_list($days, [
    'date'    => 'd',
    'orders'  => fn($r) => (int) $r['orders'],
    'revenue' => fn($r) => (float) $r['revenue'],
], 'date:asc', 20, 'd');
$top = admin_list($top, [
    'product' => 'product_name',
    'units'   => fn($r) => (int) $r['qty'],
    'revenue' => fn($r) => (float) $r['revenue'],
], 'units:desc', 10, 't');
$statusBreak = admin_list($statusBreak, [
    'status' => 'order_status',
    'count'  => fn($r) => (int) $r['c'],
], 'count:desc', 10, 's');

$adminPageTitle = 'Reports';
$adminNav = 'reports';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
admin_page_head('Reports', 'Sales performance across the range you pick.');

echo admin_filter_open();
?>
    <?php /* data-max-input / data-min-input keep the pair in order — see assets/js/datepicker.js.
             The wrapper makes the two boxes one grid cell, so they sit against
             each other as a single range control instead of being pushed to
             opposite ends of the bar by the auto-fit columns. */ ?>
    <div class="field__daterange">
        <div class="field field--date"><label>From</label><input type="text" data-datepicker name="from" data-max-input="to" value="<?php echo e($from); ?>"></div>
        <div class="field field--date"><label>To</label><input type="text" data-datepicker name="to" data-min-input="from" value="<?php echo e($to); ?>"></div>
    </div>
<?php
echo admin_filter_close('reports.php');
echo admin_stat_cards([
    ['label' => 'Revenue', 'value' => formatPrice($s['revenue'], 0), 'icon' => 'rupee', 'tone' => 'brand', 'hint' => 'In the selected range'],
    ['label' => 'Orders', 'value' => number_format((int) $s['orders']), 'icon' => 'cart', 'tone' => 'green'],
    ['label' => 'Avg Order Value', 'value' => formatPrice($s['aov'], 0), 'icon' => 'check', 'tone' => 'blue'],
    ['label' => 'Discounts Given', 'value' => formatPrice($s['discounts'], 0), 'icon' => 'tag', 'tone' => 'amber'],
]); ?>
<div class="admin__card">
    <?php echo admin_list_head('Daily Sales', admin_list_total('d')); ?>
    <table class="admin__table">
        <thead><tr>
            <?php echo admin_th('date', 'Date', 'd', 'col--date'); ?>
            <?php echo admin_th('', 'Product Details', 'd', 'col--grow'); ?>
            <?php echo admin_th('orders', 'Orders', 'd', 'th--num col--fig'); ?>
            <?php echo admin_th('revenue', 'Revenue', 'd', 'th--num col--fig'); ?>
        </tr></thead>
        <tbody>
        <?php foreach ($days as $d): $items = $dayProducts[$d['d']] ?? []; ?>
            <tr>
                <td class="col--date"><strong><?php echo date('d M Y', strtotime($d['d'])); ?></strong><br>
                    <span class="cell__sub"><?php echo date('l', strtotime($d['d'])); ?></span></td>
                <td>
                    <?php if ($items): ?>
                        <ul class="rep__items">
                            <?php /* a busy day can run to dozens of lines; the rest stay one click away in Top Products */ ?>
                            <?php foreach (array_slice($items, 0, 5) as $it): ?>
                                <li>
                                    <span class="rep__item-name"><?php echo e($it['product_name']); ?></span>
                                    <span class="cell__sub"><?php echo $it['sku'] ? e($it['sku']) . ' &middot; ' : ''; ?>&times;<?php echo (int) $it['qty']; ?> &middot; <?php echo formatPrice($it['revenue'], 0); ?></span>
                                </li>
                            <?php endforeach; ?>
                            <?php if (count($items) > 5): ?>
                                <li class="rep__items-more"><?php echo count($items) - 5; ?> more product<?php echo count($items) - 5 === 1 ? '' : 's'; ?></li>
                            <?php endif; ?>
                        </ul>
                    <?php else: ?><span class="cell__sub">&mdash;</span><?php endif; ?>
                </td>
                <td class="is-num"><?php echo number_format((int) $d['orders']); ?></td>
                <td class="is-num"><?php echo formatPrice($d['revenue'], 0); ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$days): ?><tr><td colspan="4">No sales in this range.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php echo admin_pager('d'); ?>
</div>
<div class="admin__card">
    <?php echo admin_list_head('Top Products', admin_list_total('t')); ?>
    <table class="admin__table">
        <thead><tr>
            <?php echo admin_th('product', 'Product', 't', 'col--grow'); ?>
            <?php echo admin_th('units', 'Units', 't', 'th--num col--fig'); ?>
            <?php echo admin_th('revenue', 'Revenue', 't', 'th--num col--fig'); ?>
        </tr></thead>
        <tbody>
        <?php foreach ($top as $t): ?><tr><td><span class="rep__item-name"><?php echo e($t['product_name']); ?></span></td><td class="is-num"><?php echo number_format((int) $t['qty']); ?></td><td class="is-num"><?php echo formatPrice($t['revenue'], 0); ?></td></tr><?php endforeach; ?>
        <?php if (!$top): ?><tr><td colspan="3">No sales in this range.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php echo admin_pager('t'); ?>
</div>
<div class="admin__card">
    <?php echo admin_list_head('Orders by Status', admin_list_total('s')); ?>
    <table class="admin__table">
        <thead><tr>
            <?php echo admin_th('status', 'Status', 's', 'col--grow'); ?>
            <?php echo admin_th('count', 'Count', 's', 'th--num col--fig'); ?>
            <?php echo admin_th('', 'Revenue', 's', 'th--num col--fig'); ?>
        </tr></thead>
        <tbody>
        <?php foreach ($statusBreak as $b): ?><tr><td><?php echo status_pill($b['order_status']); ?></td><td class="is-num"><?php echo number_format((int) $b['c']); ?></td><td class="is-num"><?php echo formatPrice($b['revenue'], 0); ?></td></tr><?php endforeach; ?>
        <?php if (!$statusBreak): ?><tr><td colspan="3">No orders in this range.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php echo admin_pager('s'); ?>
</div>
<?php admin_layout_end();
