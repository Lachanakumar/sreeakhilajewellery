<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/../includes/products.php';
require_once __DIR__ . '/includes/charts.php';
requireAdmin();

$db = getDB();

if (admin_post_ok('brands.php')) {
    $do = $_POST['do'] ?? '';
    if ($do === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        // an untouched auto-slug follows a rename; a hand-written one does not
        $was  = $id ? getBrand($id) : null;
        $slug = resolve_slug($_POST['slug'] ?? '', $name, $was['slug'] ?? '', $was['name'] ?? '');
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        if ($name === '') {
            admin_err('Brand name is required — nothing was saved.');
        } else {
            try {
                if ($id) {
                    $db->prepare('UPDATE brands SET name=?, slug=?, status=? WHERE id=?')->execute([$name, $slug, $status, $id]);
                } else {
                    $db->prepare('INSERT INTO brands (name, slug, status) VALUES (?,?,?)')->execute([$name, $slug, $status]);
                }
                admin_ok($id ? 'Brand updated.' : 'Brand created.');
            } catch (Throwable $e) {
                admin_err('Could not save the brand — another brand already uses that name or slug.');
            }
        }
    }
    if ($do === 'delete') {
        $stmt = $db->prepare('DELETE FROM brands WHERE id = ?');
        $stmt->execute([(int) $_POST['id']]);
        // rowCount tells us whether anything was actually there to delete
        if ($stmt->rowCount() > 0) {
            admin_ok('Brand deleted.');
        } else {
            admin_warn('That brand no longer exists — nothing was deleted.');
        }
    }
    redirect('brands.php');
}

$brands = admin_list(
    $db->query('SELECT b.*, (SELECT COUNT(*) FROM products p WHERE p.brand_id = b.id) AS product_count FROM brands b ORDER BY b.name')->fetchAll(),
    ['name' => 'name', 'slug' => 'slug', 'products' => fn($b) => (int) $b['product_count'], 'status' => 'status'],
    'name:asc', 20
);
$edit = !empty($_GET['edit']) ? getBrand((int) $_GET['edit']) : null;

$adminPageTitle = 'Brands';
$adminNav = 'brands';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
admin_page_head('Brands', 'Group your catalogue by maker or collection.');
?>
<form method="post" data-validate>
    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
    <input type="hidden" name="do" value="save">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?php echo (int) $edit['id']; ?>"><?php endif; ?>
    <div class="pform">
        <div class="pform__main">
            <div class="admin__card fsec">
                <div class="fsec__head">
                    <span class="fsec__num">1</span>
                    <div><h3><?php echo $edit ? 'Edit Brand' : 'Brand Details'; ?></h3>
                        <p>Name the brand and choose how it appears in your catalogue.</p></div>
                </div>
                <div class="row2">
                    <div class="field"><label>Brand Name <b class="req">*</b></label>
                        <input type="text" name="name" value="<?php echo e($edit['name'] ?? ''); ?>" placeholder="e.g. Anand Signature" required></div>
                    <div class="field"><label>Slug (optional)</label>
                        <input type="text" name="slug" value="<?php echo e($edit['slug'] ?? ''); ?>" placeholder="e.g. anand-signature">
                        <span class="hint">Leave blank and it is generated from the name.</span></div>
                </div>
            </div>
        </div>
        <?php echo admin_form_side($edit ? 'Update Brand' : 'Add Brand', $edit['status'] ?? 'active', $edit ? 'brands.php' : ''); ?>
    </div>
</form>
<div class="admin__card">
    <?php echo admin_list_head('All Brands', admin_list_total()); ?>
    <table class="admin__table">
        <thead><tr>
            <?php echo admin_th('name', 'Name'); ?>
            <?php echo admin_th('slug', 'Slug'); ?>
            <?php echo admin_th('products', 'Products'); ?>
            <?php echo admin_th('status', 'Status'); ?>
            <th class="col-act">Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($brands as $b): $bid = (int) $b['id']; ?>
            <tr>
                <td><strong><?php echo e($b['name']); ?></strong></td>
                <td><span class="cell__sub"><?php echo e($b['slug']); ?></span></td>
                <td><?php echo (int) $b['product_count']; ?></td>
                <td><?php echo status_pill($b['status']); ?></td>
                <?php echo admin_acts_open(); ?>
                    <?php echo admin_act_link('brands.php?edit=' . $bid, 'edit', 'Edit'); ?>
                    <?php echo admin_act_post('delete', $bid, 'trash', 'Delete', 'act--danger', 'Delete "' . $b['name'] . '"?'); ?>
                    <?php echo admin_act_menu([
                        ['label' => 'Products in this brand', 'href' => 'products.php?q=' . urlencode($b['name']), 'icon' => 'box'],
                    ]); ?>
                <?php echo admin_acts_close(); ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$brands): ?><tr><td colspan="5">No brands yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php echo admin_pager(); ?>
</div>
<?php admin_layout_end();
