<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/../includes/products.php';
requireAdmin();

$db = getDB();
$products = $db->query('SELECT p.id, p.name, p.sku, p.stock_quantity,
    (SELECT COALESCE(SUM(stock_quantity),0) FROM product_variants v WHERE v.product_id = p.id) AS variant_stock,
    (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id) AS variant_count
    FROM products p ORDER BY p.stock_quantity ASC')->fetchAll();

$products = admin_list($products, [
    'product' => 'name',
    'sku'     => 'sku',
    'stock'   => fn($p) => (int) $p['stock_quantity'],
    'variant' => fn($p) => (int) $p['variant_stock'],
], 'stock:asc', 20);

$adminPageTitle = 'Inventory';
$adminNav = 'inventory';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
// admin_page_head() escapes the subtitle, so use real characters not entities
admin_page_head('Inventory', 'Edit a stock value and press Enter or Tab — it saves without a reload.');

$ic = function ($sql) use ($db) {
    try { return (int) $db->query($sql)->fetchColumn(); } catch (Throwable $e) { return 0; }
};
echo admin_stat_cards([
    ['label' => 'Products Tracked', 'value' => number_format($ic('SELECT COUNT(*) FROM products')), 'icon' => 'box', 'tone' => 'brand'],
    ['label' => 'Healthy Stock', 'value' => number_format($ic('SELECT COUNT(*) FROM products WHERE stock_quantity > 3')), 'icon' => 'check', 'tone' => 'green', 'hint' => 'More than 3 in stock'],
    ['label' => 'Low Stock', 'value' => number_format($ic('SELECT COUNT(*) FROM products WHERE stock_quantity BETWEEN 1 AND 3')), 'icon' => 'alert', 'tone' => 'amber'],
    ['label' => 'Out of Stock', 'value' => number_format($ic('SELECT COUNT(*) FROM products WHERE stock_quantity <= 0')), 'icon' => 'draft', 'tone' => 'red'],
]);
?>
<div class="admin__card">
    <?php echo admin_list_head('Stock Levels', admin_list_total()); ?>
    <table class="admin__table">
        <thead><tr>
            <?php echo admin_th('product', 'Product'); ?>
            <?php echo admin_th('sku', 'SKU'); ?>
            <?php echo admin_th('stock', 'Base Stock'); ?>
            <?php echo admin_th('variant', 'Variant Stock'); ?>
        </tr></thead>
        <tbody>
        <?php foreach ($products as $p): ?>
            <tr class="<?php echo $p['stock_quantity'] <= 3 ? '' : ''; ?>">
                <td><?php echo e($p['name']); ?> <?php echo $p['stock_quantity'] <= 3 ? '<span class="badge badge--red">Low</span>' : ''; ?></td>
                <td><?php echo e($p['sku']); ?></td>
                <td><input type="number" min="0" class="js-stock" data-type="product" data-id="<?php echo (int) $p['id']; ?>" data-name="<?php echo e($p['name']); ?>" value="<?php echo (int) $p['stock_quantity']; ?>" style="width:90px"></td>
                <td><?php echo $p['variant_count'] ? (int) $p['variant_stock'] . ' (across ' . (int) $p['variant_count'] . ' variants)' : '—'; ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$products): ?><tr><td colspan="4">No products yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php echo admin_pager(); ?>
</div>
<script>
var CSRF = document.querySelector('meta[name="csrf-token"]').content;
document.querySelectorAll('.js-stock').forEach(function (input) {
    var last = input.value;
    input.addEventListener('change', function () {
        var name = input.dataset.name || 'Stock';
        var value = input.value;
        if (value === '' || isNaN(Number(value)) || Number(value) < 0) {
            input.classList.add('is-invalid');
            adminToast(name + ': enter a stock number of 0 or more.', 'err');
            input.value = last;
            input.classList.remove('is-invalid');
            return;
        }
        var body = new URLSearchParams();
        body.append('csrf_token', CSRF);
        body.append('type', input.dataset.type);
        body.append('id', input.dataset.id);
        body.append('stock_quantity', value);
        input.classList.add('is-saving');
        fetch('../ajax/admin/stock-update.php', { method: 'POST', headers: { 'X-CSRF-Token': CSRF }, body: body })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                input.classList.remove('is-saving');
                if (res.success) {
                    last = value;
                    input.classList.remove('is-invalid');
                    input.classList.add('is-saved');
                    setTimeout(function () { input.classList.remove('is-saved'); }, 1600);
                    adminToast(name + ' stock set to ' + value + '.', 'ok');
                    if (Number(value) === 0) {
                        adminToast(name + ' is now out of stock and cannot be ordered.', 'warn');
                    } else if (Number(value) <= 3) {
                        adminToast(name + ' is running low (' + value + ' left).', 'warn');
                    }
                } else {
                    input.classList.add('is-invalid');
                    input.value = last;
                    adminToast(res.message || 'Could not update the stock level.', 'err');
                }
            })
            .catch(function () {
                input.classList.remove('is-saving');
                input.classList.add('is-invalid');
                input.value = last;
                adminToast('Network problem — the stock level was not saved.', 'err');
            });
    });
});
</script>
<?php admin_layout_end();
