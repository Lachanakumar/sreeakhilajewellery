<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/../includes/homepage.php';
require_once __DIR__ . '/includes/upload-image.php';
requireAdmin();

$db = getDB();
$formats = ['strip' => 'Full-width strip', 'card' => 'Card (2 per row)'];
$positions = [
    'after_hero'     => 'After the hero slider',
    'after_banners'  => 'After the category banners',
    'mid_products'   => 'Between product sections',
    'before_footer'  => 'Above the footer',
];

if (admin_post_ok('ads.php')) {
    $do = $_POST['do'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($do === 'save') {
        $title = trim($_POST['title'] ?? '');
        $format = array_key_exists($_POST['format'] ?? '', $formats) ? $_POST['format'] : 'strip';
        $position = array_key_exists($_POST['position'] ?? '', $positions) ? $_POST['position'] : 'after_banners';
        $subtitle = trim($_POST['subtitle'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $buttonLabel = trim($_POST['button_label'] ?? '');
        $buttonUrl = trim($_POST['button_url'] ?? '');
        $bgColor = trim($_POST['bg_color'] ?? '');
        $textTheme = ($_POST['text_theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
        $startsAt = trim($_POST['starts_at'] ?? '');
        $expiresAt = trim($_POST['expires_at'] ?? '');
        $sort = (int) ($_POST['sort_order'] ?? 0);
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $startsAt = $startsAt !== '' ? str_replace('T', ' ', $startsAt) . ':00' : null;
        $expiresAt = $expiresAt !== '' ? str_replace('T', ' ', $expiresAt) . ':00' : null;

        $back    = 'ads.php' . ($id ? '?edit=' . $id : '');
        $current = $id ? getAdBanner($id) : null;
        $keepImg = (string) ($current['image'] ?? '');

        /* Validate everything before the file is moved — see the matching note
           in sliders.php. A rejected form must not leave an upload behind. */
        $errors = [];
        if ($title === '') {
            $errors[] = 'Offer banner title is required.';
        }
        if ($id && !$current) {
            $errors[] = 'That offer banner no longer exists.';
        }
        if (!admin_has_upload('image') && $keepImg === '') {
            $errors[] = 'A background image is required — choose a JPG, PNG or WebP file.';
        }

        /* Schedule. The picker keeps these in order on screen, but it compares
           whole days only and a hand-made POST skips it entirely, so the full
           timestamps are checked here.

           A start already in the past is refused only when it is being newly
           set: a banner that has been running for a week must stay editable
           without having to rewrite its own start date first. */
        $startTs = $startsAt !== null ? strtotime($startsAt) : null;
        $expTs   = $expiresAt !== null ? strtotime($expiresAt) : null;
        $prevStart = ($current['starts_at'] ?? null)
            ? date('Y-m-d H:i:s', strtotime($current['starts_at'])) : null;
        $prevExpiry = ($current['expires_at'] ?? null)
            ? date('Y-m-d H:i:s', strtotime($current['expires_at'])) : null;
        $startChanged = $startsAt !== $prevStart;
        $expiryChanged = $expiresAt !== $prevExpiry;

        if ($startsAt !== null && !$startTs) {
            $errors[] = 'The start date is not a valid date and time.';
        }
        if ($expiresAt !== null && !$expTs) {
            $errors[] = 'The expiry date is not a valid date and time.';
        }
        // 60s of slack: the clock moves on between rendering the form and posting it
        if ($startTs && $startChanged && $startTs < time() - 60) {
            $errors[] = 'The start date and time cannot be in the past.';
        }
        if ($startTs && $expTs && $expTs <= $startTs) {
            $errors[] = 'The expiry date and time must be later than the start date and time.';
        }
        /* Same "only when it changed" rule as the start: a banner that expired
           last week must still be editable — otherwise fixing its title first
           requires rewriting a date the admin did not come here to touch. */
        if ($expTs && !$startTs && $expiryChanged && $expTs <= time()) {
            $errors[] = 'That expiry date and time has already passed, so the banner would never show.';
        }

        if ($errors) {
            admin_err(implode(' ', $errors) . ' Nothing was saved.');
            redirect($back);
        }

        [$upOk, $imgPath] = admin_upload_image('image', 'homepage');
        if (!$upOk) {
            admin_err('The background image could not be uploaded: ' . $imgPath . ' The offer banner was not saved.');
            redirect($back);
        }

        $finalImg = $imgPath ?: $keepImg;

        try {
            if ($id) {
                $db->prepare('UPDATE ad_banners SET format=?, position=?, title=?, subtitle=?, description=?, button_label=?, button_url=?, image=?, bg_color=?, text_theme=?, starts_at=?, expires_at=?, sort_order=?, status=? WHERE id=?')
                   ->execute([$format, $position, $title, $subtitle ?: null, $description ?: null, $buttonLabel ?: null, $buttonUrl ?: null, $finalImg, $bgColor ?: null, $textTheme, $startsAt, $expiresAt, $sort, $status, $id]);
            } else {
                $db->prepare('INSERT INTO ad_banners (format, position, title, subtitle, description, button_label, button_url, image, bg_color, text_theme, starts_at, expires_at, sort_order, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                   ->execute([$format, $position, $title, $subtitle ?: null, $description ?: null, $buttonLabel ?: null, $buttonUrl ?: null, $finalImg, $bgColor ?: null, $textTheme, $startsAt, $expiresAt, $sort, $status]);
            }
        } catch (Throwable $e) {
            if ($imgPath) {
                admin_delete_upload($imgPath);
            }
            admin_err('The offer banner could not be saved. Please try again.');
            redirect($back);
        }

        if ($imgPath && $keepImg !== '') {
            admin_delete_upload($keepImg);
        }
        admin_ok($id ? 'Offer banner updated.' : 'Offer banner created.');
        redirect('ads.php');
    }

    if ($do === 'delete' && $id) {
        $current = getAdBanner($id);
        if ($current) {
            admin_delete_upload($current['image']);
            $db->prepare('DELETE FROM ad_banners WHERE id = ?')->execute([$id]);
            admin_ok('Offer banner deleted.');
        } else {
            admin_warn('That offer banner no longer exists — nothing was deleted.');
        }
        redirect('ads.php');
    }

    if ($do === 'toggle' && $id) {
        $current = getAdBanner($id);
        if (!$current) {
            admin_warn('That offer banner no longer exists.');
        } else {
            $db->prepare('UPDATE ad_banners SET status = IF(status = "active", "inactive", "active") WHERE id = ?')->execute([$id]);
            if ($current['status'] !== 'active') {
                $expired = $current['expires_at'] && strtotime($current['expires_at']) <= time();
                if ($expired) {
                    admin_warn('Offer banner activated, but its end date has already passed so it stays hidden.');
                } else {
                    admin_ok('Offer banner is now live on the homepage.');
                }
            } else {
                admin_ok('Offer banner is now hidden.');
            }
        }
        redirect('ads.php');
    }

    admin_warn('That action was not recognised, so nothing changed.');
    redirect('ads.php');
}

$ads = admin_list(getAdBanners(null, false), [
    'title'    => 'title',
    'format'   => 'format',
    'position' => 'position',
    'window'   => 'expires_at',
    'order'    => fn($a) => (int) $a['sort_order'],
    'status'   => 'status',
], 'order:asc', 20);
$edit = !empty($_GET['edit']) ? getAdBanner((int) $_GET['edit']) : null;
$dtVal = fn($v) => $v ? date('Y-m-d\TH:i', strtotime($v)) : '';

$adminPageTitle = 'Offer Banners';
$adminNav = 'ads';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
admin_page_head('Offer Banners', 'Promotional strips and cards across the homepage.');
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
                    <div><h3><?php echo $edit ? 'Edit Offer Banner' : 'Offer Content'; ?></h3>
                        <p>The wording customers see in the promotion.</p></div>
                </div>
                <div class="row2">
                    <div class="field"><label>Offer label</label>
                        <input type="text" name="subtitle" value="<?php echo e($edit['subtitle'] ?? ''); ?>" placeholder="LIMITED TIME OFFER"></div>
                    <div class="field"><label>Title <b class="req">*</b></label>
                        <textarea name="title" style="min-height:74px" placeholder="New line = line break" required><?php echo e($edit['title'] ?? ''); ?></textarea></div>
                </div>
                <div class="field"><label>Description <span class="fsec__opt">(strips only)</span></label>
                    <textarea name="description" placeholder="Extra detail shown on wide strips."><?php echo e($edit['description'] ?? ''); ?></textarea></div>
                <div class="row2">
                    <div class="field"><label>Button label</label>
                        <input type="text" name="button_label" value="<?php echo e($edit['button_label'] ?? 'Shop the Offer'); ?>"></div>
                    <div class="field"><label>Button link</label>
                        <input type="text" name="button_url" value="<?php echo e($edit['button_url'] ?? 'products.php?on_sale=1'); ?>"></div>
                </div>
            </div>

            <div class="admin__card fsec">
                <div class="fsec__head">
                    <span class="fsec__num">2</span>
                    <div><h3>Placement &amp; Look</h3><p>Where it appears and how it is styled.</p></div>
                </div>
                <div class="row3">
                    <div class="field"><label>Format</label>
                        <select name="format">
                            <?php foreach ($formats as $val => $label): ?><option value="<?php echo $val; ?>" <?php echo (($edit['format'] ?? 'strip') === $val) ? 'selected' : ''; ?>><?php echo e($label); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field"><label>Position on homepage</label>
                        <select name="position">
                            <?php foreach ($positions as $val => $label): ?><option value="<?php echo $val; ?>" <?php echo (($edit['position'] ?? 'after_banners') === $val) ? 'selected' : ''; ?>><?php echo e($label); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field"><label>Text theme <span class="fsec__opt">(strips)</span></label>
                        <select name="text_theme">
                            <option value="light" <?php echo (($edit['text_theme'] ?? 'light') === 'light') ? 'selected' : ''; ?>>Light text (dark image)</option>
                            <option value="dark" <?php echo (($edit['text_theme'] ?? '') === 'dark') ? 'selected' : ''; ?>>Dark text (light bg)</option>
                        </select>
                    </div>
                </div>
                <div class="row2">
                    <div class="field"><label>Background colour <span class="fsec__opt">(if no image)</span></label>
                        <input type="text" name="bg_color" value="<?php echo e($edit['bg_color'] ?? ''); ?>" placeholder="#1f1b16"
                               pattern="#?[0-9A-Fa-f]{3,8}" data-message="Enter a hex colour like #1f1b16."></div>
                </div>
            </div>

            <div class="admin__card fsec">
                <div class="fsec__head">
                    <span class="fsec__num">3</span>
                    <div><h3>Schedule <span class="fsec__opt">(Optional)</span></h3>
                        <p>Leave blank to show it immediately and indefinitely.</p></div>
                </div>
                <div class="row2">
                    <?php /* data-min pins the start to today; data-min-input ties the
                             expiry's floor to whatever start is picked, so the pair
                             cannot be put out of order. See assets/js/datepicker.js. */ ?>
                    <div class="field"><label>Starts</label>
                        <input type="text" data-datepicker="datetime" name="starts_at" data-min="<?php echo date('Y-m-d'); ?>"
                               data-max-input="expires_at" value="<?php echo $dtVal($edit['starts_at'] ?? null); ?>"
                               placeholder="Leave blank to start now">
                        <span class="hint">Cannot be in the past.</span></div>
                    <div class="field"><label>Expires</label>
                        <input type="text" data-datepicker="datetime" name="expires_at" data-min="<?php echo date('Y-m-d'); ?>"
                               data-min-input="starts_at" value="<?php echo $dtVal($edit['expires_at'] ?? null); ?>"
                               placeholder="Leave blank to run indefinitely">
                        <span class="hint">Must be after the start. Shows a countdown, then hides itself.</span></div>
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
        <?php echo admin_form_side($edit ? 'Update Banner' : 'Add Banner', $edit['status'] ?? 'active', $edit ? 'ads.php' : '',
                                   ['active' => 'Active', 'inactive' => 'Inactive'], $sideExtra); ?>
    </div>
</form>

<div class="admin__card">
    <?php echo admin_list_head('All Offer Banners', admin_list_total()); ?>
    <table class="admin__table">
        <thead><tr>
            <th>Image</th>
            <?php echo admin_th('title', 'Title'); ?>
            <?php echo admin_th('format', 'Format'); ?>
            <?php echo admin_th('position', 'Position'); ?>
            <?php echo admin_th('window', 'Window'); ?>
            <?php echo admin_th('order', 'Order'); ?>
            <?php echo admin_th('status', 'Status'); ?>
            <th class="col-act">Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($ads as $a): $expired = $a['expires_at'] && strtotime($a['expires_at']) <= time(); ?>
            <tr>
                <td><?php echo $a['image'] ? '<img class="thumb thumb--wide" src="../' . e($a['image']) . '" alt="" loading="lazy">' : '<span class="badge">colour</span>'; ?></td>
                <td>
                    <div class="cell__title">
                        <?php if ($a['subtitle']): ?><span class="cell__sub"><?php echo e($a['subtitle']); ?></span><?php endif; ?>
                        <strong><?php echo nl2br(e($a['title'])); ?></strong>
                    </div>
                </td>
                <td><span class="badge"><?php echo $a['format']; ?></span></td>
                <td><?php echo e($positions[$a['position']] ?? $a['position']); ?></td>
                <td>
                    <?php echo $a['starts_at'] ? date('d M', strtotime($a['starts_at'])) : 'now'; ?> &ndash;
                    <?php echo $a['expires_at'] ? date('d M Y', strtotime($a['expires_at'])) : '&infin;'; ?>
                    <?php echo $expired ? ' <span class="badge badge--red">expired</span>' : ''; ?>
                </td>
                <td><?php echo (int) $a['sort_order']; ?></td>
                <td>
                    <form method="post"><input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>">
                        <button class="pill pill--<?php echo $a['status'] === 'active' ? 'green' : 'grey'; ?>" type="submit" title="Toggle status"><?php echo e($a['status']); ?></button>
                    </form>
                </td>
                <?php echo admin_acts_open(); ?>
                    <?php echo admin_act_link('ads.php?edit=' . (int) $a['id'], 'edit', 'Edit'); ?>
                    <?php
                    /* Same liveness test the storefront query uses (getAdBanners),
                       so the tip cannot disagree with what the page renders. */
                    $notYet = $a['starts_at'] && strtotime($a['starts_at']) > time();
                    $why = $a['status'] !== 'active' ? 'this banner is inactive, so it is not on the homepage'
                         : ($expired ? 'this banner has expired, so it is not on the homepage'
                         : ($notYet ? 'this banner is scheduled and has not started yet' : ''));
                    echo admin_act_view_site('../index.php#ad-' . (int) $a['id'], $why);
                    ?>
                    <?php echo admin_act_post('delete', (int) $a['id'], 'trash', 'Delete', 'act--danger', 'Delete this banner?'); ?>
                <?php echo admin_acts_close(); ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$ads): ?><tr><td colspan="8">No offer banners yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php echo admin_pager(); ?>
</div>
<?php admin_layout_end();
