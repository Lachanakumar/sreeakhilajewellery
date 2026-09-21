<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/../includes/engagement.php';
require_once __DIR__ . '/includes/upload-image.php';
requireAdmin();

$db = getDB();

if (admin_post_ok('schemes.php')) {
    $do = $_POST['do'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    if ($do === 'save') {
        $name = trim($_POST['name'] ?? '');
        // an untouched auto-slug follows a rename; a hand-written one does not
        $was  = $id ? getScheme($id) : null;
        $slug = resolve_slug($_POST['slug'] ?? '', $name, $was['slug'] ?? '', $was['name'] ?? '');
        $catId = (int) ($_POST['category_id'] ?? 0) ?: null;
        $short = trim($_POST['short_description'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $terms = trim($_POST['terms'] ?? '');
        $benefits = trim($_POST['benefits'] ?? '');
        $dur = $_POST['duration_months'] !== '' ? (int) $_POST['duration_months'] : null;
        $amt = $_POST['monthly_amount'] !== '' ? (float) $_POST['monthly_amount'] : null;
        $sort = (int) ($_POST['sort_order'] ?? 0);
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        [$upOk, $imgPath] = admin_upload_image('image', 'schemes');
        if (!$upOk) {
            flash_set('admin_err', $imgPath);
            redirect('schemes.php' . ($id ? '?edit=' . $id : ''));
        }
        if ($name === '') {
            admin_err('Scheme name is required — nothing was saved.');
        } else {
            try {
                if ($id) {
                    $cur = getScheme($id);
                    $finalImg = $imgPath ?: ($cur['image'] ?? null);
                    if ($imgPath && !empty($cur['image'])) admin_delete_upload($cur['image']);
                    $db->prepare('UPDATE schemes SET category_id=?, name=?, slug=?, short_description=?, description=?, terms=?, benefits=?, duration_months=?, monthly_amount=?, image=?, sort_order=?, status=? WHERE id=?')
                       ->execute([$catId, $name, $slug, $short ?: null, $desc ?: null, $terms ?: null, $benefits ?: null, $dur, $amt, $finalImg, $sort, $status, $id]);
                } else {
                    $db->prepare('INSERT INTO schemes (category_id, name, slug, short_description, description, terms, benefits, duration_months, monthly_amount, image, sort_order, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                       ->execute([$catId, $name, $slug, $short ?: null, $desc ?: null, $terms ?: null, $benefits ?: null, $dur, $amt, $imgPath, $sort, $status]);
                }
                admin_ok($id ? 'Scheme updated.' : 'Scheme created.');
            } catch (Throwable $e) {
                admin_err('Could not save the scheme — another scheme already uses that slug.');
            }
        }
    }
    if ($do === 'delete') {
        $cur = getScheme($id);
        if ($cur) { admin_delete_upload($cur['image']); }
        $db->prepare('DELETE FROM schemes WHERE id = ?')->execute([$id]);
        admin_ok('Scheme deleted.');
    }
    redirect('schemes.php');
}

$schemes = admin_list(getSchemes(['status' => 'any']), [
    'name'     => 'name',
    'category' => 'category_name',
    'duration' => fn($s) => (int) $s['duration_months'],
    'monthly'  => fn($s) => (float) $s['monthly_amount'],
    'status'   => 'status',
], 'name:asc', 20);
$cats = getSchemeCategories(false);
$edit = !empty($_GET['edit']) ? getScheme((int) $_GET['edit']) : null;

$adminPageTitle = 'Schemes';
$adminNav = 'schemes';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
admin_page_head('Schemes', 'Savings & purchase plans', '<a class="btn btn--ghost" href="scheme-categories.php">Categories</a>');
?>
<?php if (!$cats): ?><div class="notice notice--warn">No scheme categories yet — <a href="scheme-categories.php">create one</a> to group your schemes. You can still add a scheme without it.</div><?php endif; ?>
<form method="post" data-validate enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
    <input type="hidden" name="do" value="save">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?php echo (int) $edit['id']; ?>"><?php endif; ?>
    <div class="pform">
        <div class="pform__main">
            <div class="admin__card fsec">
                <div class="fsec__head">
                    <span class="fsec__num">1</span>
                    <div><h3><?php echo $edit ? 'Edit Scheme' : 'Scheme Details'; ?></h3>
                        <p>Name the savings plan and where it is grouped.</p></div>
                </div>
                <div class="row3">
                    <div class="field"><label>Name <b class="req">*</b></label>
                        <input type="text" name="name" value="<?php echo e($edit['name'] ?? ''); ?>" placeholder="e.g. Gold Harvest 11+1" required></div>
                    <div class="field"><label>Category</label>
                        <select name="category_id">
                            <option value="">—</option>
                            <?php foreach ($cats as $c): ?><option value="<?php echo (int) $c['id']; ?>" <?php echo (($edit['category_id'] ?? '') == $c['id']) ? 'selected' : ''; ?>><?php echo e($c['name']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field"><label>Slug (optional)</label>
                        <input type="text" name="slug" value="<?php echo e($edit['slug'] ?? ''); ?>" placeholder="e.g. gold-harvest"></div>
                </div>
                <div class="field"><label>Short description <span class="fsec__opt">(card + list)</span></label>
                    <textarea name="short_description" style="min-height:74px" placeholder="One or two lines shown on the scheme card."><?php echo e($edit['short_description'] ?? ''); ?></textarea></div>
                <div class="field"><label>Full description</label>
                    <textarea name="description" placeholder="The detail shown on the scheme page."><?php echo e($edit['description'] ?? ''); ?></textarea></div>
            </div>

            <div class="admin__card fsec">
                <div class="fsec__head">
                    <span class="fsec__num">2</span>
                    <div><h3>Plan Terms</h3><p>How long it runs and what it costs each month.</p></div>
                </div>
                <div class="row2">
                    <div class="field"><label>Duration (months)</label>
                        <input type="number" min="1" name="duration_months" value="<?php echo e($edit['duration_months'] ?? ''); ?>" placeholder="e.g. 11"></div>
                    <div class="field"><label>Monthly amount</label>
                        <div class="money"><span><?php echo CURRENCY_SYMBOL; ?></span>
                            <input type="number" step="0.01" min="0" name="monthly_amount" value="<?php echo e($edit['monthly_amount'] ?? ''); ?>" placeholder="0.00"></div></div>
                </div>
                <div class="field"><label>Benefits <span class="fsec__opt">(one per line)</span></label>
                    <textarea name="benefits" placeholder="One benefit per line."><?php echo e($edit['benefits'] ?? ''); ?></textarea></div>
                <div class="field"><label>Terms &amp; conditions</label>
                    <textarea name="terms"><?php echo e($edit['terms'] ?? ''); ?></textarea></div>
            </div>
        </div>

        <?php ob_start(); ?>
        <div class="field"><label>Sort order</label>
            <input type="number" name="sort_order" min="0" data-numeric value="<?php echo (int) ($edit['sort_order'] ?? 0); ?>"></div>
        <div class="field"><label>Image<?php echo $edit ? ' (leave blank to keep)' : ''; ?></label>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp"></div>
        <?php if ($edit && $edit['image']): ?>
            <img class="side__preview" src="../<?php echo e($edit['image']); ?>" alt="">
        <?php endif; ?>
        <?php $sideExtra = ob_get_clean(); ?>
        <?php echo admin_form_side($edit ? 'Update Scheme' : 'Add Scheme', $edit['status'] ?? 'active', $edit ? 'schemes.php' : '',
                                   ['active' => 'Active', 'inactive' => 'Inactive'], $sideExtra); ?>
    </div>
</form>
<div class="admin__card">
    <?php echo admin_list_head('All Schemes', admin_list_total()); ?>
    <table class="admin__table">
        <thead><tr>
            <?php echo admin_th('name', 'Name'); ?>
            <?php echo admin_th('category', 'Category'); ?>
            <?php echo admin_th('duration', 'Duration'); ?>
            <?php echo admin_th('monthly', 'Monthly'); ?>
            <?php echo admin_th('status', 'Status'); ?>
            <th class="col-act">Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($schemes as $s): $sid = (int) $s['id']; ?>
            <tr>
                <td>
                    <div class="cell__media">
                        <?php echo admin_thumb($s['image'] ?? '', $s['name']); ?>
                        <div class="cell__title"><strong><?php echo e($s['name']); ?></strong>
                            <span class="cell__sub"><?php echo e($s['slug']); ?></span></div>
                    </div>
                </td>
                <td><?php echo e($s['category_name'] ?? '—'); ?></td>
                <td><?php echo $s['duration_months'] ? (int) $s['duration_months'] . ' mo' : '—'; ?></td>
                <td><span class="price__now"><?php echo $s['monthly_amount'] ? formatPrice($s['monthly_amount'], 0) : '—'; ?></span></td>
                <td><?php echo status_pill($s['status']); ?></td>
                <?php echo admin_acts_open(); ?>
                    <?php echo admin_act_link('schemes.php?edit=' . $sid, 'edit', 'Edit'); ?>
                    <?php echo admin_act_link('../scheme-details.php?slug=' . urlencode($s['slug']), 'external', 'View on site', '', true); ?>
                    <?php echo admin_act_post('delete', $sid, 'trash', 'Delete', 'act--danger', 'Delete "' . $s['name'] . '"?'); ?>
                <?php echo admin_acts_close(); ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$schemes): ?><tr><td colspan="6">No schemes yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php echo admin_pager(); ?>
</div>
<?php admin_layout_end();
