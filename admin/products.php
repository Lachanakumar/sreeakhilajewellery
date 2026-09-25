<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/../includes/products.php';
require_once __DIR__ . '/includes/charts.php';
requireAdmin();

$db = getDB();

if (admin_post_ok('products.php')) {
    $do = $_POST['do'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    if ($do === 'delete' && $id) {
        // Remove image files then the row (cascade clears image rows).
        foreach (getProductImages($id) as $img) {
            $path = __DIR__ . '/../' . $img['image_path'];
            if (strpos($img['image_path'], 'uploads/products/') === 0 && is_file($path)) {
                @unlink($path);
            }
        }
        $db->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
        flash_set('admin_ok', 'Product deleted.');
    }
    if ($do === 'status' && $id) {
        $new = in_array($_POST['status'] ?? '', ['active', 'inactive', 'draft'], true) ? $_POST['status'] : 'active';
        $db->prepare('UPDATE products SET status = ? WHERE id = ?')->execute([$new, $id]);
        flash_set('admin_ok', 'Status updated.');
    }
    if ($do === 'feature' && $id) {
        $db->prepare('UPDATE products SET is_featured = 1 - is_featured WHERE id = ?')->execute([$id]);
        flash_set('admin_ok', 'Featured flag updated.');
    }
    if ($do === 'bulk') {
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
        $act = $_POST['bulk_action'] ?? '';
        if (!$ids || $act === '') {
            flash_set('admin_err', 'Select at least one product and an action.');
        } else {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $n = count($ids);
            if (in_array($act, ['active', 'inactive', 'draft'], true)) {
                $db->prepare("UPDATE products SET status = ? WHERE id IN ($in)")->execute(array_merge([$act], $ids));
                flash_set('admin_ok', "$n product(s) set to $act.");
            } elseif ($act === 'feature' || $act === 'unfeature') {
                $db->prepare("UPDATE products SET is_featured = ? WHERE id IN ($in)")->execute(array_merge([$act === 'feature' ? 1 : 0], $ids));
                flash_set('admin_ok', "$n product(s) " . ($act === 'feature' ? 'featured.' : 'un-featured.'));
            } elseif ($act === 'delete') {
                foreach ($ids as $pid) {
                    foreach (getProductImages($pid) as $img) {
                        $path = __DIR__ . '/../' . $img['image_path'];
                        if (strpos($img['image_path'], 'uploads/products/') === 0 && is_file($path)) {
                            @unlink($path);
                        }
                    }
                }
                $db->prepare("DELETE FROM products WHERE id IN ($in)")->execute($ids);
                flash_set('admin_ok', "$n product(s) deleted.");
            }
        }
    }
    if ($do === 'duplicate' && $id) {
        $src = getDB()->query('SELECT * FROM products WHERE id = ' . $id)->fetch();
        if ($src) {
            $newSku = $src['sku'] . '-COPY-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $newSlug = $src['slug'] . '-copy-' . substr(bin2hex(random_bytes(2)), 0, 4);
            $db->prepare('INSERT INTO products (category_id, brand_id, name, slug, sku, short_description, description, specifications, regular_price, sale_price, stock_quantity, weight, purity, status, is_featured)
                          SELECT category_id, brand_id, CONCAT(name, " (Copy)"), ?, ?, short_description, description, specifications, regular_price, sale_price, stock_quantity, weight, purity, "draft", is_featured FROM products WHERE id = ?')
               ->execute([$newSlug, $newSku, $id]);
            $newId = (int) $db->lastInsertId();
            foreach (getProductImages($id) as $img) {
                $db->prepare('INSERT INTO product_images (product_id, image_path, is_primary, sort_order) VALUES (?,?,?,?)')
                   ->execute([$newId, $img['image_path'], $img['is_primary'], $img['sort_order']]);
            }
            foreach (getProductVariants($id) as $v) {
                $db->prepare('INSERT INTO product_variants (product_id, variant_name, variant_value, price_adjustment, stock_quantity, sku_suffix) VALUES (?,?,?,?,?,?)')
                   ->execute([$newId, $v['variant_name'], $v['variant_value'], $v['price_adjustment'], $v['stock_quantity'], $v['sku_suffix']]);
            }
            flash_set('admin_ok', 'Product duplicated as a draft.');
            redirect('product-edit.php?id=' . $newId);
        }
    }
    // keep the admin on the same filtered page they acted from
    redirect('products.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

$search = trim($_GET['q'] ?? '');
$catFilter = (int) ($_GET['category'] ?? 0);
$statusFilter = $_GET['status'] ?? '';
$where = ['1=1']; $params = [];
if ($search !== '') { $where[] = '(p.name LIKE ? OR p.sku LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($catFilter) { $where[] = 'p.category_id = ?'; $params[] = $catFilter; }
if (in_array($statusFilter, ['active', 'inactive', 'draft'], true)) { $where[] = 'p.status = ?'; $params[] = $statusFilter; }

$stmt = $db->prepare('SELECT p.*, c.name AS category_name,
    (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order LIMIT 1) AS thumb
    FROM products p LEFT JOIN categories c ON c.id = p.category_id
    WHERE ' . implode(' AND ', $where) . ' ORDER BY p.created_at DESC');
$stmt->execute($params);
$products = $stmt->fetchAll();
$cats = getAllCategoriesFlat();

/* ---- headline counts (whole catalogue, not just the filtered view) ---- */
$count = function ($sql) use ($db) {
    try { return (int) $db->query($sql)->fetchColumn(); } catch (Throwable $e) { return 0; }
};
$allProducts = $count('SELECT COUNT(*) FROM products');
$activeCount = $count("SELECT COUNT(*) FROM products WHERE status = 'active'");
$lowStock    = $count('SELECT COUNT(*) FROM products WHERE stock_quantity <= 3');
$draftCount  = $count("SELECT COUNT(*) FROM products WHERE status = 'draft'");
$added30     = $count('SELECT COUNT(*) FROM products WHERE created_at >= (NOW() - INTERVAL 30 DAY)');
$addedPrev30 = $count('SELECT COUNT(*) FROM products WHERE created_at >= (NOW() - INTERVAL 60 DAY) AND created_at < (NOW() - INTERVAL 30 DAY)');
$trend = null;
if ($added30 || $addedPrev30) {
    $delta = $addedPrev30 > 0 ? round((($added30 - $addedPrev30) / $addedPrev30) * 100) : 100;
    $trend = ['label' => ($delta >= 0 ? '+' : '') . $delta . '%', 'dir' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat')];
}

$products = admin_list($products, [
    'name'     => 'name',
    'sku'      => 'sku',
    'category' => 'category_name',
    'price'    => fn($p) => (float) ($p['sale_price'] ?: $p['regular_price']),
    'stock'    => fn($p) => (int) $p['stock_quantity'],
    'status'   => 'status',
    'created'  => 'created_at',
], 'created:desc', 10);
$total = admin_list_total();

$adminPageTitle = 'Products';
$adminNav = 'products';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
admin_page_head(
    'Products',
    'Manage your jewellery products, stock and pricing.',
    '<a class="btn" href="product-add.php">' . admin_ui_icon('plus', 16) . ' Add Product</a>'
);

echo admin_stat_cards([
    ['label' => 'Total Products', 'value' => $allProducts, 'icon' => 'box', 'tone' => 'brand',
     'trend' => $trend['label'] ?? '', 'trend_dir' => $trend['dir'] ?? 'flat',
     'hint' => $added30 . ' added in the last 30 days'],
    ['label' => 'Active Products', 'value' => $activeCount, 'icon' => 'check', 'tone' => 'green'],
    ['label' => 'Low Stock', 'value' => $lowStock, 'icon' => 'alert', 'tone' => 'amber', 'hint' => '3 or fewer in stock'],
    ['label' => 'Draft Products', 'value' => $draftCount, 'icon' => 'draft', 'tone' => 'red'],
]);

echo admin_filter_open();
echo admin_filter_search('q', $search, 'Product name or SKU…');
?>
    <div class="field"><label>Category</label>
        <select name="category">
            <option value="">All Categories</option>
            <?php foreach ($cats as $c): ?>
                <option value="<?php echo (int) $c['id']; ?>" <?php echo $catFilter === (int) $c['id'] ? 'selected' : ''; ?>><?php echo ($c['parent_id'] ? '— ' : '') . e($c['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field"><label>Status</label>
        <select name="status">
            <option value="">All Status</option>
            <?php foreach (['active', 'inactive', 'draft'] as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $statusFilter === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
<?php
echo admin_filter_close('products.php');
?>

<div class="admin__card">
    <?php echo admin_list_head('Products', $total, admin_bulk_bar([
        'active'    => 'Set Active',
        'inactive'  => 'Set Inactive',
        'draft'     => 'Move to Draft',
        'feature'   => 'Mark Featured',
        'unfeature' => 'Remove Featured',
        'delete'    => 'Delete',
    ])); ?>

    <table class="admin__table">
        <thead><tr>
            <?php echo admin_check_all(); ?>
            <?php echo admin_th('name', 'Product'); ?>
            <?php echo admin_th('sku', 'SKU'); ?>
            <?php echo admin_th('category', 'Category'); ?>
            <?php echo admin_th('price', 'Price'); ?>
            <?php echo admin_th('stock', 'Stock'); ?>
            <?php echo admin_th('status', 'Status'); ?>
            <th class="col-act">Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($products as $p): $pid = (int) $p['id']; ?>
            <tr>
                <?php echo admin_check_row($pid); ?>
                <td>
                    <div class="cell__media">
                        <?php echo admin_thumb($p['thumb'], $p['name'], product_placeholder_image()); ?>
                        <div class="cell__title">
                            <strong><?php echo e($p['name']); ?></strong>
                            <?php if ($p['is_featured']): ?><span class="badge badge--amber">Featured</span><?php endif; ?>
                        </div>
                    </div>
                </td>
                <td><?php echo e($p['sku']); ?></td>
                <td><?php echo e($p['category_name'] ?? '—'); ?></td>
                <td>
                    <span class="price__now"><?php echo formatPrice($p['sale_price'] ?: $p['regular_price'], 0); ?></span>
                    <?php if ($p['sale_price']): ?><span class="price__was"><?php echo formatPrice($p['regular_price'], 0); ?></span><?php endif; ?>
                </td>
                <td><?php echo (int) $p['stock_quantity']; ?></td>
                <td><?php echo status_pill($p['status']); ?></td>
                <?php echo admin_acts_open(); ?>
                    <?php echo admin_act_link('product-edit.php?id=' . $pid, 'edit', 'Edit'); ?>
                    <?php echo admin_act_post('duplicate', $pid, 'copy', 'Duplicate'); ?>
                    <?php echo admin_act_post('delete', $pid, 'trash', 'Delete', 'act--danger', 'Delete "' . $p['name'] . '"? This cannot be undone.'); ?>
                    <?php echo admin_act_menu([
                        ['label' => 'View on site', 'href' => '../product-details.php?id=' . $pid, 'icon' => 'external', 'blank' => true],
                        ['label' => $p['is_featured'] ? 'Remove from featured' : 'Mark as featured', 'do' => 'feature', 'id' => $pid, 'icon' => 'star'],
                        ['sep' => true],
                        ['label' => 'Set Active',    'do' => 'status', 'id' => $pid, 'fields' => ['status' => 'active'],   'icon' => 'check'],
                        ['label' => 'Set Inactive',  'do' => 'status', 'id' => $pid, 'fields' => ['status' => 'inactive'], 'icon' => 'draft'],
                        ['label' => 'Move to Draft', 'do' => 'status', 'id' => $pid, 'fields' => ['status' => 'draft'],    'icon' => 'edit'],
                    ]); ?>
                <?php echo admin_acts_close(); ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$products): ?><tr><td colspan="8">No products match these filters.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php echo admin_pager(); ?>
</div>
<?php admin_layout_end();
