<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/../includes/homepage.php';
require_once __DIR__ . '/includes/upload-image.php';
requireAdmin();

$db = getDB();

if (admin_post_ok('sliders.php')) {
    $do = $_POST['do'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($do === 'save') {
        $title = trim($_POST['title'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $buttonLabel = trim($_POST['button_label'] ?? '');
        $buttonUrl = trim($_POST['button_url'] ?? '');
        $titleColor = trim($_POST['title_color'] ?? '');
        $sort = (int) ($_POST['sort_order'] ?? 0);
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        $back    = 'sliders.php' . ($id ? '?edit=' . $id : '');
        $current = $id ? getSlider($id) : null;
        $keepImg = (string) ($current['image'] ?? '');

        /* Every field is checked before the upload is touched. The old order
           moved the file first and validated after, so a slide rejected for a
           missing title still left its image orphaned in uploads/. */
        $errors = [];
        if ($title === '') {
            $errors[] = 'Slider title is required.';
        }
        if ($id && !$current) {
            $errors[] = 'That slider no longer exists.';
        }
        // the slide IS its background image — there is nothing to show without one
        if (!admin_has_upload('image') && $keepImg === '') {
            $errors[] = 'A background image is required — choose a JPG, PNG or WebP file.';
        }
        if ($errors) {
            admin_err(implode(' ', $errors) . ' Nothing was saved.');
            redirect($back);
        }

        [$upOk, $imgPath] = admin_upload_image('image', 'homepage');
        if (!$upOk) {
            // $imgPath carries the reason when the upload failed
            admin_err('The background image could not be uploaded: ' . $imgPath . ' The slider was not saved.');
            redirect($back);
        }

        $finalImg = $imgPath ?: $keepImg;

        /* The old image is removed and success is reported only once the write
           has actually gone through; a failed write used to leave the record
           untouched but still announce "Slider updated." */
        try {
            if ($id) {
                $db->prepare('UPDATE sliders SET subtitle=?, title=?, description=?, button_label=?, button_url=?, image=?, title_color=?, sort_order=?, status=? WHERE id=?')
                   ->execute([$subtitle ?: null, $title, $description ?: null, $buttonLabel ?: null, $buttonUrl ?: null, $finalImg, $titleColor ?: null, $sort, $status, $id]);
            } else {
                $db->prepare('INSERT INTO sliders (subtitle, title, description, button_label, button_url, image, title_color, sort_order, status) VALUES (?,?,?,?,?,?,?,?,?)')
                   ->execute([$subtitle ?: null, $title, $description ?: null, $buttonLabel ?: null, $buttonUrl ?: null, $finalImg, $titleColor ?: null, $sort, $status]);
            }
        } catch (Throwable $e) {
            if ($imgPath) {
                admin_delete_upload($imgPath);   // nothing references it now
            }
            admin_err('The slider could not be saved. Please try again.');
            redirect($back);
        }

        if ($imgPath && $keepImg !== '') {
            admin_delete_upload($keepImg);
        }
        admin_ok($id ? 'Slider updated.' : 'Slider created.');
        redirect('sliders.php');
    }

    if ($do === 'delete' && $id) {
        $current = getSlider($id);
        if ($current) {
            admin_delete_upload($current['image']);
            $db->prepare('DELETE FROM sliders WHERE id = ?')->execute([$id]);
            admin_ok('Slider deleted.');
        } else {
            admin_warn('That slider no longer exists — nothing was deleted.');
        }
        redirect('sliders.php');
    }

    if ($do === 'toggle' && $id) {
        $current = getSlider($id);
        if (!$current) {
            admin_warn('That slider no longer exists.');
        } else {
            $db->prepare('UPDATE sliders SET status = IF(status = "active", "inactive", "active") WHERE id = ?')->execute([$id]);
            $now = $current['status'] === 'active' ? 'hidden' : 'live';
            admin_ok('Slider is now ' . $now . ' on the homepage.');
        }
        redirect('sliders.php');
    }

    admin_warn('That action was not recognised, so nothing changed.');
    redirect('sliders.php');
}

$sliderCount = count(getSliders(false));
$sliders = admin_list(getSliders(false), [
    'title'  => 'title',
    'button' => 'button_label',
    'order'  => fn($s) => (int) $s['sort_order'],
    'status' => 'status',
], 'order:asc', 20);
$edit = !empty($_GET['edit']) ? getSlider((int) $_GET['edit']) : null;

$adminPageTitle = 'Hero Sliders';
$adminNav = 'sliders';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
admin_page_head('Hero Sliders', 'The rotating banner at the top of the homepage.');
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
                    <div><h3><?php echo $edit ? 'Edit Slide' : 'Slide Content'; ?></h3>
                        <p>The wording that sits over the background image.</p></div>
                </div>
                <div class="row2">
                    <div class="field"><label>Subtitle</label>
                        <input type="text" name="subtitle" value="<?php echo e($edit['subtitle'] ?? ''); ?>" placeholder="Small line above the title"></div>
                    <div class="field"><label>Title <b class="req">*</b></label>
                        <textarea name="title" style="min-height:74px" placeholder="Use a new line for a line break" required><?php echo e($edit['title'] ?? ''); ?></textarea></div>
                </div>
                <div class="field"><label>Description</label>
                    <textarea name="description" placeholder="Optional supporting line."><?php echo e($edit['description'] ?? ''); ?></textarea></div>
            </div>

            <div class="admin__card fsec">
                <div class="fsec__head">
                    <span class="fsec__num">2</span>
                    <div><h3>Call to Action <span class="fsec__opt">(Optional)</span></h3>
                        <p>Where the slide's button sends the customer.</p></div>
                </div>
                <div class="row3">
                    <div class="field"><label>Button label</label>
                        <input type="text" name="button_label" value="<?php echo e($edit['button_label'] ?? 'Shop Now'); ?>" placeholder="Shop Now"></div>
                    <div class="field"><label>Button link</label>
                        <input type="text" name="button_url" value="<?php echo e($edit['button_url'] ?? 'products.php'); ?>" placeholder="products.php or category.php?slug=gold"></div>
                    <div class="field"><label>Title colour</label>
                        <input type="text" name="title_color" value="<?php echo e($edit['title_color'] ?? ''); ?>" placeholder="#c7a047"
                               pattern="#?[0-9A-Fa-f]{3,8}" data-message="Enter a hex colour like #c7a047."></div>
                </div>
            </div>
        </div>

        <?php ob_start(); ?>
        <div class="field"><label>Sort order</label>
            <input type="number" name="sort_order" min="0" data-numeric value="<?php echo (int) ($edit['sort_order'] ?? ($sliderCount + 1)); ?>"></div>
        <?php /* required only when there is no image to fall back on, so editing
                 a slide without re-picking the file still works */ ?>
        <?php $imgRequired = !$edit || empty($edit['image']); ?>
        <div class="field"><label>Background image <?php echo $imgRequired ? '<b class="req">*</b>' : '<span class="fsec__opt">(leave blank to keep)</span>'; ?></label>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp"<?php echo $imgRequired ? ' required' : ''; ?>
                   data-message="Choose a background image — JPG, PNG or WebP, up to 5MB.">
            <span class="hint">Landscape works best. JPG, PNG or WebP, up to 5MB.</span></div>
        <?php if ($edit && $edit['image']): ?>
            <img class="side__preview" src="../<?php echo e($edit['image']); ?>" alt="">
        <?php endif; ?>
        <?php $sideExtra = ob_get_clean(); ?>
        <?php echo admin_form_side($edit ? 'Update Slider' : 'Add Slider', $edit['status'] ?? 'active', $edit ? 'sliders.php' : '',
                                   ['active' => 'Active', 'inactive' => 'Inactive'], $sideExtra); ?>
    </div>
</form>

<div class="admin__card">
    <?php echo admin_list_head('All Sliders', admin_list_total()); ?>
    <table class="admin__table">
        <thead><tr>
            <th>Image</th>
            <?php echo admin_th('title', 'Title'); ?>
            <?php echo admin_th('button', 'Button'); ?>
            <?php echo admin_th('order', 'Order'); ?>
            <?php echo admin_th('status', 'Status'); ?>
            <th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($sliders as $s): $sid = (int) $s['id']; ?>
            <tr>
                <td><?php echo $s['image'] ? '<img class="thumb thumb--wide" src="../' . e($s['image']) . '" alt="" loading="lazy">' : '<span class="thumb thumb--wide thumb--empty">' . admin_ui_icon('box', 16) . '</span>'; ?></td>
                <td>
                    <div class="cell__title">
                        <?php if ($s['subtitle']): ?><span class="cell__sub"><?php echo e($s['subtitle']); ?></span><?php endif; ?>
                        <strong><?php echo nl2br(e($s['title'])); ?></strong>
                    </div>
                </td>
                <td><?php echo $s['button_label'] ? e($s['button_label']) . '<br><span class="cell__sub">' . e($s['button_url']) . '</span>' : '&mdash;'; ?></td>
                <td><?php echo (int) $s['sort_order']; ?></td>
                <td>
                    <form method="post"><input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="<?php echo $sid; ?>">
                        <button class="pill pill--<?php echo $s['status'] === 'active' ? 'green' : 'grey'; ?>" type="submit" title="Toggle status"><?php echo e($s['status']); ?></button>
                    </form>
                </td>
                <?php echo admin_acts_open(); ?>
                    <?php echo admin_act_link('sliders.php?edit=' . $sid, 'edit', 'Edit'); ?>
                    <?php /* ?slide= opens the hero on this exact slide rather than slide 1 */ ?>
                    <?php echo admin_act_view_site('../index.php?slide=' . $sid . '#hero',
                        $s['status'] === 'active' ? '' : 'this slide is inactive, so it is not on the homepage'); ?>
                    <?php echo admin_act_post('delete', $sid, 'trash', 'Delete', 'act--danger', 'Delete this slider?'); ?>
                <?php echo admin_acts_close(); ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$sliders): ?><tr><td colspan="6">No sliders yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php echo admin_pager(); ?>
</div>
<?php admin_layout_end();
