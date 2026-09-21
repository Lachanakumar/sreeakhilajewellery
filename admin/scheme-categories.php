<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/../includes/engagement.php';
requireAdmin();

$db = getDB();

if (admin_post_ok('scheme-categories.php')) {
    $do = $_POST['do'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    if ($do === 'save') {
        $name = trim($_POST['name'] ?? '');
        // an untouched auto-slug follows a rename; a hand-written one does not
        $was  = $id ? getSchemeCategory($id) : null;
        $slug = resolve_slug($_POST['slug'] ?? '', $name, $was['slug'] ?? '', $was['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $sort = (int) ($_POST['sort_order'] ?? 0);
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        if ($name === '') {
            admin_err('Category name is required — nothing was saved.');
        } else {
            try {
                if ($id) {
                    $db->prepare('UPDATE scheme_categories SET name=?, slug=?, description=?, sort_order=?, status=? WHERE id=?')
                       ->execute([$name, $slug, $desc ?: null, $sort, $status, $id]);
                } else {
                    $db->prepare('INSERT INTO scheme_categories (name, slug, description, sort_order, status) VALUES (?,?,?,?,?)')
                       ->execute([$name, $slug, $desc ?: null, $sort, $status]);
                }
                admin_ok($id ? 'Scheme category updated.' : 'Scheme category created.');
            } catch (Throwable $e) {
                admin_err('Could not save — another scheme category already uses that slug.');
            }
        }
    }
    if ($do === 'delete') {
        $db->prepare('DELETE FROM scheme_categories WHERE id = ?')->execute([$id]);
        admin_ok('Scheme category deleted.');
    }
    redirect('scheme-categories.php');
}

$cats = getSchemeCategories(false);
foreach ($cats as &$__c) {
    $__c['scheme_count'] = count(getSchemes(['category_id' => $__c['id'], 'status' => 'any']));
}
unset($__c);
$cats = admin_list($cats, [
    'name'    => 'name',
    'slug'    => 'slug',
    'schemes' => fn($c) => (int) $c['scheme_count'],
    'status'  => 'status',
], 'name:asc', 20);
$edit = !empty($_GET['edit']) ? getSchemeCategory((int) $_GET['edit']) : null;

$adminPageTitle = 'Scheme Categories';
$adminNav = 'scheme_cats';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
admin_page_head('Scheme Categories', 'Group your savings schemes', '<a class="btn btn--ghost" href="schemes.php">Back to Schemes</a>');
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
                    <div><h3><?php echo $edit ? 'Edit Category' : 'Category Details'; ?></h3>
                        <p>Group related savings schemes under one heading.</p></div>
                </div>
                <div class="row3">
                    <div class="field"><label>Name <b class="req">*</b></label>
                        <input type="text" name="name" value="<?php echo e($edit['name'] ?? ''); ?>" placeholder="e.g. Gold Savings" required></div>
                    <div class="field"><label>Slug (optional)</label>
                        <input type="text" name="slug" value="<?php echo e($edit['slug'] ?? ''); ?>" placeholder="e.g. gold-savings"></div>
                    <div class="field"><label>Sort order</label>
                        <input type="number" name="sort_order" min="0" data-numeric value="<?php echo (int) ($edit['sort_order'] ?? 0); ?>"></div>
                </div>
                <div class="field"><label>Description</label>
                    <textarea name="description" placeholder="What kind of schemes belong here?"><?php echo e($edit['description'] ?? ''); ?></textarea></div>
            </div>
        </div>
        <?php echo admin_form_side($edit ? 'Update Category' : 'Add Category', $edit['status'] ?? 'active', $edit ? 'scheme-categories.php' : ''); ?>
    </div>
</form>
<div class="admin__card">
    <?php echo admin_list_head('All Scheme Categories', admin_list_total()); ?>
    <table class="admin__table">
        <thead><tr>
            <?php echo admin_th('name', 'Name'); ?>
            <?php echo admin_th('slug', 'Slug'); ?>
            <?php echo admin_th('schemes', 'Schemes'); ?>
            <?php echo admin_th('status', 'Status'); ?>
            <th class="col-act">Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($cats as $c): $n = (int) $c['scheme_count']; $cid = (int) $c['id']; ?>
            <tr>
                <td><strong><?php echo e($c['name']); ?></strong></td>
                <td><span class="cell__sub"><?php echo e($c['slug']); ?></span></td>
                <td><?php echo $n; ?></td>
                <td><?php echo status_pill($c['status']); ?></td>
                <?php echo admin_acts_open(); ?>
                    <?php echo admin_act_link('scheme-categories.php?edit=' . $cid, 'edit', 'Edit'); ?>
                    <?php echo admin_act_post('delete', $cid, 'trash', 'Delete', 'act--danger', 'Delete "' . $c['name'] . '"?'); ?>
                    <?php echo admin_act_menu([
                        ['label' => 'Schemes in this group', 'href' => 'schemes.php', 'icon' => 'tag'],
                    ]); ?>
                <?php echo admin_acts_close(); ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$cats): ?><tr><td colspan="5">No categories yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php echo admin_pager(); ?>
</div>
<?php admin_layout_end();
