<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/../includes/homepage.php';
require_once __DIR__ . '/includes/upload-image.php';
requireAdmin();

$db = getDB();
$slots = ['feature' => 'Feature (large, left)', 'wide' => 'Wide (top-right, max 2)', 'tile' => 'Tile (bottom-right, max 2)'];

if (admin_post_ok('banners.php')) {
    $do = $_POST['do'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($do === 'save') {
        $title = trim($_POST['title'] ?? '');
        $slot = array_key_exists($_POST['slot'] ?? '', $slots) ? $_POST['slot'] : 'tile';
        $linkLabel = trim($_POST['link_label'] ?? '');
        $linkUrl = trim($_POST['link_url'] ?? '');
        $sort = (int) ($_POST['sort_order'] ?? 0);
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        $back    = 'banners.php' . ($id ? '?edit=' . $id : '');
        $current = $id ? getBanner($id) : null;
        $keepImg = (string) ($current['image'] ?? '');

        /* Validate everything before the file is moved — see the matching note
           in sliders.php. A rejected form must not leave an upload behind. */
        $errors = [];
        if ($title === '') {
            $errors[] = 'Banner title is required.';
        }
        if ($id && !$current) {
            $errors[] = 'That banner no longer exists.';
        }
        // a category banner is an image tile; without one there is nothing to show
        if (!admin_has_upload('image') && $keepImg === '') {
            $errors[] = 'A background image is required — choose a JPG, PNG or WebP file.';
        }
        if ($errors) {
            admin_err(implode(' ', $errors) . ' Nothing was saved.');
            redirect($back);
        }

        [$upOk, $imgPath] = admin_upload_image('image', 'homepage');
        if (!$upOk) {
            admin_err('The background image could not be uploaded: ' . $imgPath . ' The banner was not saved.');
            redirect($back);
        }

        $finalImg = $imgPath ?: $keepImg;

        try {
            if ($id) {
                $db->prepare('UPDATE banners SET slot=?, title=?, link_label=?, link_url=?, image=?, sort_order=?, status=? WHERE id=?')
                   ->execute([$slot, $title, $linkLabel ?: 'SHOP NOW', $linkUrl ?: null, $finalImg, $sort, $status, $id]);
            } else {
                $db->prepare('INSERT INTO banners (slot, title, link_label, link_url, image, sort_order, status) VALUES (?,?,?,?,?,?,?)')
                   ->execute([$slot, $title, $linkLabel ?: 'SHOP NOW', $linkUrl ?: null, $finalImg, $sort, $status]);
            }
        } catch (Throwable $e) {
            if ($imgPath) {
                admin_delete_upload($imgPath);
            }
            admin_err('The banner could not be saved. Please try again.');
            redirect($back);
        }

        if ($imgPath && $keepImg !== '') {
            admin_delete_upload($keepImg);
        }
        admin_ok($id ? 'Banner updated.' : 'Banner created.');
        redirect('banners.php');
    }

    if ($do === 'delete' && $id) {
        $current = getBanner($id);
        if ($current) {
            admin_delete_upload($current['image']);
            $db->prepare('DELETE FROM banners WHERE id = ?')->execute([$id]);
            admin_ok('Banner deleted.');
        } else {
            admin_warn('That banner no longer exists — nothing was deleted.');
        }
        redirect('banners.php');
    }

    if ($do === 'toggle' && $id) {
        $current = getBanner($id);
        if (!$current) {
            admin_warn('That banner no longer exists.');
        } else {
            $db->prepare('UPDATE banners SET status = IF(status = "active", "inactive", "active") WHERE id = ?')->execute([$id]);
            admin_ok('Banner is now ' . ($current['status'] === 'active' ? 'hidden' : 'live') . ' on the homepage.');
        }
        redirect('banners.php');
    }

    admin_warn('That action was not recognised, so nothing changed.');
    redirect('banners.php');
}

$banners = admin_list(getBanners(null, false), [
    'title'  => 'title',
    'slot'   => 'slot',
    'link'   => 'link_label',
    'order'  => fn($b) => (int) $b['sort_order'],
    'status' => 'status',
], 'order:asc', 20);
$edit = !empty($_GET['edit']) ? getBanner((int) $_GET['edit']) : null;

$adminPageTitle = 'Category Banners';
$adminNav = 'banners';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
admin_page_head('Category Banners', 'The "Shop by Categories" area on the homepage.');
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
                    <div><h3><?php echo $edit ? 'Edit Banner' : 'Banner Details'; ?></h3>
                        <p>The homepage area shows 1 feature, up to 2 wide and up to 2 tile banners, in sort order.</p></div>
                </div>
                <div class="row2">
                    <div class="field"><label>Title <b class="req">*</b></label>
                        <textarea name="title" style="min-height:74px" placeholder="e.g. Bridal Gold&#10;Collection — press Enter for a second line" required><?php echo e($edit['title'] ?? ''); ?></textarea>
                        <span class="hint">Shown over the banner image. Each new line becomes a line break.</span></div>
                    <div class="field"><label>Slot</label>
                        <select name="slot">
                            <?php foreach ($slots as $val => $label): ?>
                                <option value="<?php echo $val; ?>" <?php echo (($edit['slot'] ?? 'tile') === $val) ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="hint">Where on the homepage this banner sits.</span>
                    </div>
                </div>
            </div>

            <div class="admin__card fsec">
                <div class="fsec__head">
                    <span class="fsec__num">2</span>
                    <div><h3>Link <span class="fsec__opt">(Optional)</span></h3>
                        <p>Where the banner sends the customer.</p></div>
                </div>
                <div class="row2">
                    <div class="field"><label>Link label</label>
                        <input type="text" name="link_label" value="<?php echo e($edit['link_label'] ?? 'SHOP NOW'); ?>" placeholder="SHOP NOW"></div>
                    <div class="field"><label>Link URL</label>
                        <input type="text" name="link_url" value="<?php echo e($edit['link_url'] ?? 'products.php'); ?>" placeholder="products.php"></div>
                </div>
            </div>
        </div>

        <?php ob_start(); ?>
        <div class="field"><label>Sort order</label>
            <input type="number" name="sort_order" min="0" data-numeric value="<?php echo (int) ($edit['sort_order'] ?? 1); ?>"></div>
        <?php $imgRequired = !$edit || empty($edit['image']); ?>
        <div class="field"><label>Background image <?php echo $imgRequired ? '<b class="req">*</b>' : '<span class="fsec__opt">(leave blank to keep)</span>'; ?></label>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp"<?php echo $imgRequired ? ' required' : ''; ?>
                   data-message="Choose a background image — JPG, PNG or WebP, up to 5MB.">
            <span class="hint">JPG, PNG or WebP, up to 5MB.</span></div>
        <?php if ($edit && $edit['image']): ?>
            <img class="side__preview" src="../<?php echo e($edit['image']); ?>" alt="">
        <?php endif; ?>
        <?php $sideExtra = ob_get_clean(); ?>
        <?php echo admin_form_side($edit ? 'Update Banner' : 'Add Banner', $edit['status'] ?? 'active', $edit ? 'banners.php' : '',
                                   ['active' => 'Active', 'inactive' => 'Inactive'], $sideExtra); ?>
    </div>
</form>

<div class="admin__card">
    <?php echo admin_list_head('All Banners', admin_list_total()); ?>
    <table class="admin__table">
        <thead><tr>
            <th>Image</th>
            <?php echo admin_th('title', 'Title'); ?>
            <?php echo admin_th('slot', 'Slot'); ?>
            <?php echo admin_th('link', 'Link'); ?>
            <?php echo admin_th('order', 'Order'); ?>
            <?php echo admin_th('status', 'Status'); ?>
            <th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($banners as $b): $bid = (int) $b['id']; ?>
            <tr>
                <td><?php echo $b['image'] ? '<img class="thumb thumb--wide" src="../' . e($b['image']) . '" alt="" loading="lazy">' : '<span class="thumb thumb--wide thumb--empty">' . admin_ui_icon('box', 16) . '</span>'; ?></td>
                <td><strong><?php echo nl2br(e($b['title'])); ?></strong></td>
                <td><span class="badge"><?php echo e($b['slot']); ?></span></td>
                <td><?php echo e($b['link_label']); ?><br><span class="cell__sub"><?php echo e($b['link_url']); ?></span></td>
                <td><?php echo (int) $b['sort_order']; ?></td>
                <td>
                    <form method="post"><input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="<?php echo $bid; ?>">
                        <button class="pill pill--<?php echo $b['status'] === 'active' ? 'green' : 'grey'; ?>" type="submit" title="Toggle status"><?php echo e($b['status']); ?></button>
                    </form>
                </td>
                <?php echo admin_acts_open(); ?>
                    <?php echo admin_act_link('banners.php?edit=' . $bid, 'edit', 'Edit'); ?>
                    <?php echo admin_act_view_site('../index.php#banner-' . $bid,
                        $b['status'] === 'active' ? '' : 'this banner is inactive, so it is not on the homepage'); ?>
                    <?php echo admin_act_post('delete', $bid, 'trash', 'Delete', 'act--danger', 'Delete this banner?'); ?>
                <?php echo admin_acts_close(); ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$banners): ?><tr><td colspan="7">No banners yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php echo admin_pager(); ?>
</div>
<?php admin_layout_end();
