<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/../includes/products.php';
requireAdmin();

$db = getDB();
$productId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$isEdit = $productId > 0;
$product = $isEdit ? $db->query('SELECT * FROM products WHERE id = ' . $productId)->fetch() : null;
if ($isEdit && !$product) {
    admin_warn('That product no longer exists — it may have been deleted.');
    redirect('products.php');
}
$errors = [];
$expired = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'save') {
    if (!csrf_verify()) {
        $expired = true;
    } else {
        $name = trim($_POST['name'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $slug = trim($_POST['slug'] ?? '') ?: slugify($name);
        $categoryId = (int) ($_POST['category_id'] ?? 0) ?: null;
        $brandId = (int) ($_POST['brand_id'] ?? 0) ?: null;
        $regular = (float) ($_POST['regular_price'] ?? 0);
        $sale = $_POST['sale_price'] !== '' ? (float) $_POST['sale_price'] : null;
        $stock = (int) ($_POST['stock_quantity'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive', 'draft'], true) ? $_POST['status'] : 'active';
        $featured = !empty($_POST['is_featured']) ? 1 : 0;
        $shortDesc = trim($_POST['short_description'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $specs = trim($_POST['specifications'] ?? '');
        $weight = trim($_POST['weight'] ?? '');
        $purity = trim($_POST['purity'] ?? '');
        $making = ($_POST['making_charge'] ?? '') !== '' ? (float) $_POST['making_charge'] : null;
        $onHome = !empty($_POST['show_on_homepage']) ? 1 : 0;

        if ($name === '') $errors[] = 'Name is required.';
        if ($sku === '') $errors[] = 'SKU is required.';
        if ($regular <= 0) $errors[] = 'Regular price must be greater than zero.';
        if ($sale !== null && $sale >= $regular) $errors[] = 'Sale price must be below the regular price.';

        // Unique SKU / slug check. Only compare the parts that were actually
        // filled in: on a blank form $sku and $slug are both '', which matches
        // any product stored with an empty one and reports a clash that does not
        // exist, on top of the "is required" messages.
        $dupeCols = [];
        $dupeArgs = [];
        if ($sku  !== '') { $dupeCols[] = 'sku = ?';  $dupeArgs[] = $sku; }
        if ($slug !== '') { $dupeCols[] = 'slug = ?'; $dupeArgs[] = $slug; }
        if ($dupeCols) {
            $dupeArgs[] = $productId;
            $dupe = $db->prepare('SELECT id FROM products WHERE (' . implode(' OR ', $dupeCols) . ') AND id <> ?');
            $dupe->execute($dupeArgs);
            if ($dupe->fetch()) $errors[] = 'Another product already uses that SKU or slug.';
        }

        if (!$errors) {
            if ($isEdit) {
                $db->prepare('UPDATE products SET category_id=?, brand_id=?, name=?, slug=?, sku=?, short_description=?, description=?, specifications=?, regular_price=?, sale_price=?, stock_quantity=?, weight=?, purity=?, making_charge=?, status=?, is_featured=?, show_on_homepage=? WHERE id=?')
                   ->execute([$categoryId, $brandId, $name, $slug, $sku, $shortDesc ?: null, $desc ?: null, $specs ?: null, $regular, $sale, $stock, $weight ?: null, $purity ?: null, $making, $status, $featured, $onHome, $productId]);
            } else {
                $db->prepare('INSERT INTO products (category_id, brand_id, name, slug, sku, short_description, description, specifications, regular_price, sale_price, stock_quantity, weight, purity, making_charge, status, is_featured, show_on_homepage) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                   ->execute([$categoryId, $brandId, $name, $slug, $sku, $shortDesc ?: null, $desc ?: null, $specs ?: null, $regular, $sale, $stock, $weight ?: null, $purity ?: null, $making, $status, $featured, $onHome]);
                $productId = (int) $db->lastInsertId();
            }

            // Variants: arrays name[], value[], adjust[], vstock[], suffix[], vid[]
            $vNames = $_POST['v_name'] ?? [];
            $keepIds = [];
            foreach ($vNames as $i => $vn) {
                $vn = trim($vn);
                $vv = trim($_POST['v_value'][$i] ?? '');
                if ($vn === '' || $vv === '') continue;
                $adjust = (float) ($_POST['v_adjust'][$i] ?? 0);
                $vstock = (int) ($_POST['v_stock'][$i] ?? 0);
                $suffix = trim($_POST['v_suffix'][$i] ?? '');
                $vid = (int) ($_POST['v_id'][$i] ?? 0);
                if ($vid) {
                    $db->prepare('UPDATE product_variants SET variant_name=?, variant_value=?, price_adjustment=?, stock_quantity=?, sku_suffix=? WHERE id=? AND product_id=?')
                       ->execute([$vn, $vv, $adjust, $vstock, $suffix ?: null, $vid, $productId]);
                    $keepIds[] = $vid;
                } else {
                    $db->prepare('INSERT INTO product_variants (product_id, variant_name, variant_value, price_adjustment, stock_quantity, sku_suffix) VALUES (?,?,?,?,?,?)')
                       ->execute([$productId, $vn, $vv, $adjust, $vstock, $suffix ?: null]);
                    $keepIds[] = (int) $db->lastInsertId();
                }
            }
            // Delete removed variants
            $existing = getProductVariants($productId);
            foreach ($existing as $ev) {
                if (!in_array((int) $ev['id'], $keepIds, true)) {
                    $db->prepare('DELETE FROM product_variants WHERE id = ?')->execute([(int) $ev['id']]);
                }
            }

            // Images chosen before the product existed are posted with the form
            // and attached here; already-saved products also use the AJAX manager.
            $added = 0;
            $attempted = 0;   // files the admin actually picked
            $imageProblem = false;
            if (!empty($_FILES['images']['name'][0])) {
                $dir = __DIR__ . '/../uploads/products/';
                if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
                $existingCount = count(getProductImages($productId));
                foreach ($_FILES['images']['name'] as $i => $origName) {
                    if (($_FILES['images']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    $attempted++;
                    $one = [
                        'name'     => $origName,
                        'type'     => $_FILES['images']['type'][$i],
                        'tmp_name' => $_FILES['images']['tmp_name'][$i],
                        'error'    => $_FILES['images']['error'][$i],
                        'size'     => $_FILES['images']['size'][$i],
                    ];
                    // admin_err() queues a list; flash_set() would keep only the
                    // last message when several images fail.
                    [$ok, $extOrMsg] = validate_uploaded_image($one);
                    if (!$ok) {
                        admin_err($origName . ': ' . $extOrMsg);
                        $imageProblem = true;
                        continue;
                    }
                    $fname = unique_filename($extOrMsg);
                    if (!move_uploaded_file($one['tmp_name'], $dir . $fname)) {
                        admin_err('Could not store ' . $origName . '.');
                        $imageProblem = true;
                        continue;
                    }
                    $db->prepare('INSERT INTO product_images (product_id, image_path, is_primary, sort_order) VALUES (?,?,?,?)')
                       ->execute([$productId, 'uploads/products/' . $fname, ($existingCount + $added) === 0 ? 1 : 0, $existingCount + $added]);
                    $added++;
                }
            }

            $failed = $attempted - $added;

            admin_ok(($isEdit ? 'Product updated.' : 'Product created.')
                . ($added ? " $added image" . ($added === 1 ? '' : 's') . ' uploaded.' : ''));

            /* Account for images that were picked but rejected. Without this the
               admin saw "Product created." sitting next to "No images yet — add
               at least one", which reads as though they forgot to choose a file
               when in fact the file they chose was turned away. */
            if ($failed > 0) {
                admin_warn($failed . ' of ' . $attempted . ' image' . ($attempted === 1 ? '' : 's')
                    . ' could not be added, so the product was saved without '
                    . ($failed === 1 ? 'it' : 'them') . '. The reason for each one is listed above.');
            }

            // context the admin would otherwise only notice on the storefront.
            // Only nudge when nothing was attached at all — a rejected upload
            // already has its own message and is not the same as forgetting.
            if (!$isEdit && !$added && $attempted === 0) {
                admin_warn('No images yet — add at least one so it looks right in the shop.');
            }
            if ($status === 'draft') {
                admin_info('Saved as a draft, so it stays hidden from the storefront.');
            } elseif ($status === 'inactive') {
                admin_info('Status is Inactive, so this product is hidden from the storefront.');
            } elseif ($stock <= 0) {
                admin_warn('Stock is 0 — customers will see this product as out of stock.');
            }

            /* Every other admin module returns to its list after a save;
               the product form was the one exception, which is why editing a
               product appeared to go nowhere. Two cases still stay on the
               form on purpose:
                 - a brand new product, because images and variants can only
                   be attached once it has an id (the notice above asks for
                   an image, and the list page is no place to act on that)
                 - a save where an image was rejected, so the upload can be
                   retried next to the file picker rather than a page away */
            if ($isEdit && !$imageProblem) {
                redirect('products.php');
            }
            redirect('product-edit.php?id=' . $productId);
        }
    }
    // repopulate on error
    $product = array_merge((array) $product, $_POST);
}

$cats = getAllCategoriesFlat();
$brands = getBrands(false);
$images = $isEdit ? getProductImages($productId) : [];
$variants = $isEdit ? getProductVariants($productId) : [];
$v = fn($k, $d = '') => e($product[$k] ?? $d);

$purities = ['22K', '24K', '18K', '14K', '925 Silver', 'Platinum 950'];
$attrs    = ['Size', 'Length', 'Weight', 'Colour', 'Purity', 'Finish'];

// Queue the save problems BEFORE the layout starts — admin_layout_start()
// drains the queue into toasts, so they surface the same way every other
// action does (and still appear in the <noscript> fallback).
/* A file input cannot be refilled by the server — no browser will let a page
   put a file back into one. So any save that does not go through loses the
   images the admin had picked: the queue comes back empty on the redisplayed
   form, and the next save creates the product with none. That is silent unless
   we say it, and the first sign of it is the placeholder image on the shop. */
$lostImages = 0;
foreach ((array) ($_FILES['images']['error'] ?? []) as $__e) {
    if ($__e !== UPLOAD_ERR_NO_FILE) { $lostImages++; }
}
function product_image_reselect_warning($n) {
    admin_warn($n . ' selected image' . ($n === 1 ? '' : 's')
        . ' could not be carried over — browsers never let a page refill a file box.'
        . ' Please choose ' . ($n === 1 ? 'it' : 'them') . ' again before you save.');
}

if ($expired) {
    admin_warn('Your session expired before the product could be saved. Nothing was changed — please try again.');
    if ($lostImages) { product_image_reselect_warning($lostImages); }
}
foreach ($errors as $err) {
    admin_err($err);
}
if ($errors) {
    admin_warn(($isEdit ? 'The product was not updated' : 'The product was not created')
        . ' — fix the ' . count($errors) . ' problem' . (count($errors) === 1 ? '' : 's') . ' above and save again.');
    if ($lostImages) { product_image_reselect_warning($lostImages); }
}

$adminPageTitle = $isEdit ? 'Edit Product' : 'Add Product';
$adminNav = 'products';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
admin_page_head(
    $isEdit ? 'Edit Product' : 'Add New Product',
    $isEdit ? 'Update this product and keep your catalogue current.' : 'Create a new jewellery product and add it to your catalogue.',
    '<a class="btn btn--ghost" href="products.php">&#8592; Back to Products</a>'
);
?>
<form method="post" data-validate id="productForm" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
    <input type="hidden" name="do" value="save">
    <input type="hidden" name="id" value="<?php echo (int) $productId; ?>">

    <div class="pform">
    <div class="pform__main">

        <!-- 1. Basic information -->
        <div class="admin__card fsec">
            <div class="fsec__head">
                <span class="fsec__num">1</span>
                <div><h3>Basic Information</h3><p>Enter the main details about the product.</p></div>
            </div>
            <div class="row2">
                <div class="field"><label for="p_name">Product Name <b class="req">*</b></label>
                    <input type="text" id="p_name" name="name" value="<?php echo $v('name'); ?>" placeholder="e.g. Gold Necklace Set" required></div>
                <div class="field"><label for="p_sku">SKU <b class="req">*</b></label>
                    <input type="text" id="p_sku" name="sku" value="<?php echo $v('sku'); ?>" placeholder="e.g. SJ-N001" required></div>
            </div>
            <div class="row3">
                <div class="field"><label for="p_slug">Slug (optional)</label>
                    <input type="text" id="p_slug" name="slug" value="<?php echo $v('slug'); ?>" placeholder="e.g. gold-necklace-set"></div>
                <div class="field"><label>Category <b class="req">*</b></label>
                    <select name="category_id" required>
                        <option value="">Select category</option>
                        <?php foreach ($cats as $c): ?>
                            <option value="<?php echo (int) $c['id']; ?>" <?php echo (($product['category_id'] ?? '') == $c['id']) ? 'selected' : ''; ?>><?php echo ($c['parent_id'] ? '— ' : '') . e($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field"><label>Brand</label>
                    <select name="brand_id">
                        <option value="">Select brand</option>
                        <?php foreach ($brands as $b): ?>
                            <option value="<?php echo (int) $b['id']; ?>" <?php echo (($product['brand_id'] ?? '') == $b['id']) ? 'selected' : ''; ?>><?php echo e($b['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="field">
                <label for="p_short">Short Description <b class="req">*</b></label>
                <textarea id="p_short" name="short_description" maxlength="300" required
                          data-count="shortCount" placeholder="Brief product description (shown in product list)…"
                          style="min-height:84px"><?php echo $v('short_description'); ?></textarea>
                <span class="field__count" id="shortCount">0/300</span>
            </div>
            <div class="field">
                <label>Full Description</label>
                <textarea name="description" data-editor placeholder="Write a detailed description about the product…"><?php echo $v('description'); ?></textarea>
            </div>
        </div>

        <!-- 2. Pricing & inventory -->
        <div class="admin__card fsec">
            <div class="fsec__head">
                <span class="fsec__num">2</span>
                <div><h3>Pricing &amp; Inventory</h3><p>Set the price, stock and other product details.</p></div>
            </div>
            <div class="row3">
                <div class="field"><label for="p_regular">Regular Price <b class="req">*</b></label>
                    <div class="money"><span><?php echo CURRENCY_SYMBOL; ?></span>
                        <input type="number" id="p_regular" step="0.01" min="0.01" name="regular_price" value="<?php echo $v('regular_price'); ?>"
                               data-label="Regular price" data-min-message="Regular price must be greater than zero." placeholder="0.00" required></div>
                </div>
                <div class="field"><label>Sale Price</label>
                    <div class="money"><span><?php echo CURRENCY_SYMBOL; ?></span>
                        <input type="number" step="0.01" min="0" name="sale_price" value="<?php echo e($product['sale_price'] ?? ''); ?>"
                               data-label="Sale price" data-lt="#p_regular" data-lt-message="Sale price must be below the regular price."
                               placeholder="0.00"></div>
                </div>
                <div class="field"><label>Stock Quantity</label>
                    <input type="number" min="0" name="stock_quantity" value="<?php echo $v('stock_quantity', '0'); ?>" data-label="Stock quantity" placeholder="0"></div>
            </div>
            <div class="row3">
                <div class="field"><label>Weight (grams)</label>
                    <input type="text" name="weight" value="<?php echo $v('weight'); ?>" placeholder="0.00"></div>
                <div class="field"><label>Purity</label>
                    <select name="purity">
                        <option value="">Select purity</option>
                        <?php
                        $curPurity = (string) ($product['purity'] ?? '');
                        $opts = $purities;
                        if ($curPurity !== '' && !in_array($curPurity, $opts, true)) { $opts[] = $curPurity; }
                        foreach ($opts as $p): ?>
                            <option value="<?php echo e($p); ?>" <?php echo $curPurity === $p ? 'selected' : ''; ?>><?php echo e($p); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field"><label>Making Charge (optional)</label>
                    <div class="money"><span><?php echo CURRENCY_SYMBOL; ?></span>
                        <input type="number" step="0.01" min="0" name="making_charge" value="<?php echo e($product['making_charge'] ?? ''); ?>" placeholder="0.00"></div>
                    <span class="hint">Recorded against the product; it does not change cart totals.</span>
                </div>
            </div>
        </div>

        <!-- 3. Variants -->
        <div class="admin__card fsec">
            <div class="fsec__head">
                <span class="fsec__num">3</span>
                <div><h3>Variants <span class="fsec__opt">(Optional)</span></h3><p>Add different variations like size, weight, etc.</p></div>
            </div>
            <table class="admin__table" id="variantTable">
                <thead><tr>
                    <th>Attribute</th><th>Value</th><th>Price +/- (<?php echo CURRENCY_SYMBOL; ?>)</th>
                    <th>Stock</th><th>SKU Suffix</th><th class="col-act">Action</th>
                </tr></thead>
                <tbody>
                <?php
                $rows = $variants ?: [];
                $rows[] = ['id' => 0, 'variant_name' => '', 'variant_value' => '', 'price_adjustment' => '', 'stock_quantity' => '', 'sku_suffix' => ''];
                foreach ($rows as $vr):
                    $vAttrs = $attrs;
                    if ($vr['variant_name'] !== '' && !in_array($vr['variant_name'], $vAttrs, true)) { $vAttrs[] = $vr['variant_name']; }
                ?>
                    <tr>
                        <td>
                            <input type="hidden" name="v_id[]" value="<?php echo (int) $vr['id']; ?>">
                            <select name="v_name[]">
                                <?php foreach ($vAttrs as $a): ?>
                                    <option value="<?php echo e($a); ?>" <?php echo $vr['variant_name'] === $a ? 'selected' : ''; ?>><?php echo e($a); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="text" name="v_value[]" value="<?php echo e($vr['variant_value']); ?>" placeholder="e.g. 18"></td>
                        <td><input type="number" step="0.01" name="v_adjust[]" value="<?php echo e($vr['price_adjustment']); ?>" placeholder="0.00"></td>
                        <td><input type="number" name="v_stock[]" value="<?php echo e($vr['stock_quantity']); ?>" placeholder="0"></td>
                        <td><input type="text" name="v_suffix[]" value="<?php echo e($vr['sku_suffix']); ?>" placeholder="e.g. -18"></td>
                        <td class="col-act"><button type="button" class="act act--danger" data-del-variant title="Remove row" aria-label="Remove row"><?php echo admin_ui_icon('trash', 15); ?></button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" class="btn btn--soft btn--sm" data-add-variant><?php echo admin_ui_icon('plus', 14); ?> Add Variant</button>
        </div>

        <!-- 4. Additional details -->
        <div class="admin__card fsec">
            <div class="fsec__head">
                <span class="fsec__num">4</span>
                <div><h3>Additional Details <span class="fsec__opt">(Optional)</span></h3><p>Add extra information about the product.</p></div>
            </div>
            <div class="field"><label>Specifications / Additional Information</label>
                <textarea name="specifications" placeholder="e.g. Material, Dimensions, Warranty, etc.…"><?php echo $v('specifications'); ?></textarea></div>
        </div>
    </div>

    <aside class="pform__side">
        <!-- Product images -->
        <div class="admin__card fsec">
            <div class="fsec__head">
                <span class="fsec__ic"><?php echo admin_ui_icon('image', 18); ?></span>
                <div><h3>Product Images</h3><p>Add product images (first image will be the main image).</p></div>
            </div>

            <div class="dropzone" data-dropzone tabindex="0" role="button" aria-label="Add product images">
                <?php echo admin_ui_icon('upload', 26); ?>
                <strong>Drop images here or click to browse</strong>
                <span>PNG, JPG, WEBP (Max 5MB each)</span>
            </div>
            <input type="file" id="newImages" name="images[]" accept="image/jpeg,image/pjpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" multiple hidden>
            <div class="imgq" id="newImageGrid" hidden></div>

            <?php /* Only once there is something to manage. .imgmgr is a 2px dashed
                     box with 22px of padding, so an empty one painted a blank
                     dashed placeholder directly under the image just added —
                     it read as a second, broken image slot. Adding images is
                     the dropzone's job above; this panel is for reordering,
                     deleting and choosing the main one. */ ?>
            <?php if ($isEdit && $images): ?>
                <div class="imgmgr" id="imageManager" data-product-id="<?php echo (int) $productId; ?>">
                    <div class="imgmgr__progress" id="imageProgress"><div></div></div>
                    <div class="imgmgr__grid" id="imageGrid">
                        <?php foreach ($images as $img): ?>
                            <div class="imgmgr__item<?php echo $img['is_primary'] ? ' is-primary' : ''; ?>" data-id="<?php echo (int) $img['id']; ?>" draggable="true">
                                <?php if ($img['is_primary']): ?><span class="imgmgr__flag">Main</span><?php endif; ?>
                                <img src="../<?php echo e($img['image_path']); ?>" alt="">
                                <div class="imgmgr__bar">
                                    <button type="button" data-act="primary" title="Set as main">&#9733;</button>
                                    <button type="button" data-act="delete" title="Delete">&#128465;</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($images) > 1): ?><span class="hint">Drag thumbnails to reorder.</span><?php endif; ?>
                </div>
                <input type="hidden" id="imageInput">
            <?php endif; ?>
        </div>

        <!-- Publish -->
        <div class="admin__card fsec">
            <div class="fsec__head">
                <span class="fsec__ic"><?php echo admin_ui_icon('send', 18); ?></span>
                <div><h3>Publish</h3></div>
            </div>
            <div class="field"><label for="p_status">Status</label>
                <select name="status" id="p_status">
                    <?php foreach (['active' => 'Active', 'inactive' => 'Inactive', 'draft' => 'Draft'] as $s => $lbl): ?>
                        <option value="<?php echo $s; ?>" <?php echo (($product['status'] ?? 'active') === $s) ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label><input type="checkbox" name="is_featured" value="1" <?php echo !empty($product['is_featured']) ? 'checked' : ''; ?>>
                    Featured product
                    <span class="tip" title="Shows this product in the homepage &quot;Featured Products&quot; row.">?</span>
                </label>
            </div>
            <div class="field">
                <label><input type="checkbox" name="show_on_homepage" value="1" <?php echo (!$isEdit || !empty($product['show_on_homepage'])) ? 'checked' : ''; ?>>
                    Show in homepage
                    <span class="tip" title="Allows this product to appear in the homepage rows (New Arrivals, Best Sellers, Special Offers).">?</span>
                </label>
            </div>

            <button class="btn btn--block" type="submit"><?php echo admin_ui_icon('check', 16); ?> <?php echo $isEdit ? 'Save Product' : 'Create Product'; ?></button>
            <button class="btn btn--ghost btn--block" type="submit" data-save-draft><?php echo admin_ui_icon('edit', 16); ?> Save as Draft</button>
            <?php if ($isEdit): ?>
                <a class="btn btn--ghost btn--block" href="../product-details.php?id=<?php echo (int) $productId; ?>" target="_blank" rel="noopener"><?php echo admin_ui_icon('external', 16); ?> Preview on site</a>
            <?php endif; ?>
        </div>
    </aside>
    </div>
</form>

<script src="../assets/js/admin/editor.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/admin/editor.js') ?: date('Ymd'); ?>"></script>
<script src="../assets/js/admin/product-form.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/admin/product-form.js') ?: date('Ymd'); ?>"></script>
<?php if ($isEdit): ?><script src="../assets/js/admin/image-upload.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/admin/image-upload.js') ?: date('Ymd'); ?>"></script><?php endif; ?>
<?php admin_layout_end();
