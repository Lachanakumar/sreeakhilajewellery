<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/../includes/products.php';
require_once __DIR__ . '/includes/charts.php';
require_once __DIR__ . '/includes/upload-image.php';
requireAdmin();

$db = getDB();

if (admin_post_ok('categories.php')) {
    $do = $_POST['do'] ?? '';
    if ($do === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $parent = (int) ($_POST['parent_id'] ?? 0) ?: null;
        // an untouched auto-slug follows a rename (and a change of parent);
        // a hand-written one is left exactly as the admin typed it
        $was        = $id ? getCategory($id) : null;
        $newPrefix  = $parent ? (getCategory($parent)['slug'] ?? '') : '';
        $oldParent  = $was['parent_id'] ?? null;
        $oldPrefix  = $oldParent ? (getCategory((int) $oldParent)['slug'] ?? '') : '';
        $slug = resolve_slug($_POST['slug'] ?? '', $name,
            $was['slug'] ?? '', $was['name'] ?? '', $newPrefix, $oldPrefix);
        $desc = trim($_POST['description'] ?? '');
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $sort = (int) ($_POST['sort_order'] ?? 0);
        [$upOk, $imgPath] = admin_upload_image('image', 'categories');
        if (!$upOk) {
            flash_set('admin_err', $imgPath);
            redirect('categories.php' . ($id ? '?edit=' . $id : ''));
        }

        if ($name === '') {
            admin_err('Category name is required — nothing was saved.');
        } elseif ($parent && getCategory($parent) && getCategory($parent)['parent_id'] !== null) {
            admin_err('Only two levels of categories are supported — pick a main category as the parent.');
        } else {
            try {
                if ($id) {
                    $current  = getCategory($id);
                    $finalImg = $imgPath ?: ($current['image'] ?? null);
                    if ($imgPath && !empty($current['image'])) {
                        admin_delete_upload($current['image']);
                    }
                    $db->prepare('UPDATE categories SET name=?, slug=?, parent_id=?, description=?, image=?, status=?, sort_order=? WHERE id=?')
                       ->execute([$name, $slug, $parent, $desc ?: null, $finalImg, $status, $sort, $id]);
                } else {
                    $db->prepare('INSERT INTO categories (name, slug, parent_id, description, image, status, sort_order) VALUES (?,?,?,?,?,?,?)')
                       ->execute([$name, $slug, $parent, $desc ?: null, $imgPath, $status, $sort]);
                }
                admin_ok($id ? 'Category updated.' : 'Category created.');
                if ($status === 'inactive') {
                    admin_info('Status is Inactive, so this category is hidden from the storefront.');
                }
            } catch (Throwable $e) {
                admin_err('Could not save the category — another category already uses that slug.');
            }
        }
    }
    if ($do === 'delete') {
        $id = (int) $_POST['id'];
        $childCount = $db->prepare('SELECT COUNT(*) FROM categories WHERE parent_id = ?');
        $childCount->execute([$id]);
        $prodCount = $db->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $prodCount->execute([$id]);
        if ($childCount->fetchColumn() > 0 || $prodCount->fetchColumn() > 0) {
            admin_warn('That category still has sub-categories or products, so it was not deleted.');
        } else {
            $current = getCategory($id);
            if ($current) {
                admin_delete_upload($current['image']);
            }
            $db->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            admin_ok('Category deleted.');
        }
    }
    redirect('categories.php');
}

$mains = $db->query('SELECT * FROM categories WHERE parent_id IS NULL ORDER BY sort_order, name')->fetchAll();
$all = getAllCategoriesFlat();
$edit = !empty($_GET['edit']) ? getCategory((int) $_GET['edit']) : null;
$counts = [];
foreach ($db->query('SELECT category_id, COUNT(*) c FROM products GROUP BY category_id') as $r) {
    $counts[$r['category_id']] = $r['c'];
}

// Flatten main -> sub into one list so it can be sorted and paged as a table.
// The default order keeps the hierarchy; sorting a column flattens it, and the
// "Main"/"Sub" column still tells you which level each row belongs to.
$rows = [];
foreach ($mains as $m) {
    $rows[] = $m + ['_type' => 'Main', '_sub' => false, '_products' => (int) ($counts[$m['id']] ?? 0)];
    foreach ($all as $c) {
        if ($c['parent_id'] == $m['id']) {
            $rows[] = $c + ['_type' => 'Sub', '_sub' => true, '_products' => (int) ($counts[$c['id']] ?? 0)];
        }
    }
}
$rows = admin_list($rows, [
    'name'     => 'name',
    'type'     => '_type',
    'slug'     => 'slug',
    'products' => '_products',
    'status'   => 'status',
], '', 20);

$adminPageTitle = 'Categories';
$adminNav = 'categories';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
admin_page_head('Categories', 'Main categories and the sub-categories under them.');
?>
<form method="post" data-validate enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
    <input type="hidden" name="do" value="save">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?php echo (int) $edit['id']; ?>"><?php endif; ?>
    <div class="pform">
        <div class="pform__main">
            <div class="admin__card fsec">
                <div class="fsec__head">
                    <span class="fsec__num">1</span>
                    <div><h3><?php echo $edit ? 'Edit Category' : 'Category Details'; ?></h3>
                        <p>Leave the parent blank to create a main category.</p></div>
                </div>
                <div class="row3">
                    <div class="field"><label>Name <b class="req">*</b></label>
                        <input type="text" name="name" value="<?php echo e($edit['name'] ?? ''); ?>" placeholder="e.g. Necklaces" required></div>
                    <div class="field"><label>Parent category</label>
                        <select name="parent_id">
                            <option value="">— Main category —</option>
                            <?php foreach ($mains as $m): ?>
                                <?php if ($edit && $edit['id'] == $m['id']) continue; ?>
                                <option value="<?php echo (int) $m['id']; ?>" <?php echo (($edit['parent_id'] ?? '') == $m['id']) ? 'selected' : ''; ?>><?php echo e($m['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="hint">Only two levels are supported.</span>
                    </div>
                    <div class="field"><label>Slug (optional)</label>
                        <input type="text" name="slug" value="<?php echo e($edit['slug'] ?? ''); ?>" placeholder="e.g. necklaces"></div>
                </div>
                <div class="row2">
                    <div class="field"><label>Sort order</label>
                        <input type="number" name="sort_order" min="0" data-numeric value="<?php echo (int) ($edit['sort_order'] ?? 0); ?>"></div>
                </div>
                <div class="field"><label>Description</label>
                    <textarea name="description" placeholder="Shown on the category page."><?php echo e($edit['description'] ?? ''); ?></textarea></div>
            </div>
        </div>
        <?php ob_start(); ?>
        <div class="field"><label>Image<?php echo $edit ? ' (leave blank to keep)' : ''; ?></label>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
            <span class="hint">Used for the "Shop by Category" cards on the homepage. Landscape, at least 800&times;600.</span>
        </div>
        <?php if ($edit && !empty($edit['image'])): ?>
            <img class="side__preview" src="../<?php echo e($edit['image']); ?>" alt="">
        <?php endif; ?>
        <?php $sideExtra = ob_get_clean(); ?>
        <?php echo admin_form_side($edit ? 'Update Category' : 'Add Category', $edit['status'] ?? 'active', $edit ? 'categories.php' : '',
                                   ['active' => 'Active', 'inactive' => 'Inactive'], $sideExtra); ?>
    </div>
</form>

<div class="admin__card">
    <?php echo admin_list_head('All Categories', admin_list_total()); ?>
    <table class="admin__table">
        <thead><tr>
            <?php echo admin_th('name', 'Name'); ?>
            <?php echo admin_th('type', 'Type'); ?>
            <?php echo admin_th('slug', 'Slug'); ?>
            <?php echo admin_th('products', 'Products'); ?>
            <?php echo admin_th('status', 'Status'); ?>
            <th class="col-act">Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $c): ?>
            <tr>
                <td<?php echo $c['_sub'] ? ' style="padding-left:30px"' : ''; ?>>
                    <?php if ($c['_sub']): ?>&#8627; <?php echo e($c['name']); ?>
                    <?php else: ?><strong><?php echo e($c['name']); ?></strong><?php endif; ?>
                </td>
                <td><?php echo $c['_type']; ?></td>
                <td><?php echo e($c['slug']); ?></td>
                <td><?php echo $c['_products']; ?></td>
                <td><?php echo status_pill($c['status']); ?></td>
                <?php echo admin_acts_open(); ?>
                    <?php echo admin_act_link('categories.php?edit=' . (int) $c['id'], 'edit', 'Edit'); ?>
                    <?php echo admin_act_post('delete', (int) $c['id'], 'trash', 'Delete', 'act--danger', 'Delete "' . $c['name'] . '"?'); ?>
                    <?php echo admin_act_menu([
                        ['label' => 'Products in this category', 'href' => 'products.php?category=' . (int) $c['id'], 'icon' => 'box'],
                        ['label' => 'View on site', 'href' => '../category.php?slug=' . urlencode($c['slug']), 'icon' => 'external', 'blank' => true],
                    ]); ?>
                <?php echo admin_acts_close(); ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6">No categories yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php echo admin_pager(); ?>
</div>
<?php admin_layout_end();
