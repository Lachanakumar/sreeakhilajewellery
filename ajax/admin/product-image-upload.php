<?php
require_once __DIR__ . '/_bootstrap.php';

$productId = (int) ($_POST['product_id'] ?? 0);
$db = getDB();

$prod = $db->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');
$prod->execute([$productId]);
if (!$prod->fetch()) {
    json_response(false, 'Unknown product.');
}

if (empty($_FILES['image']) || !isset($_FILES['image']['tmp_name'])) {
    json_response(false, 'No file received.');
}

[$ok, $extOrError] = validate_uploaded_image($_FILES['image']);
if (!$ok) {
    json_response(false, $extOrError);
}

$dir = products_upload_dir();
if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
    json_response(false, 'Upload directory is not writable.');
}

$filename = unique_filename($extOrError);
$target = $dir . '/' . $filename;
if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
    json_response(false, 'Could not save the uploaded file.');
}

$relPath = 'uploads/products/' . $filename;

// First image for this product becomes primary.
$hasPrimary = $db->prepare('SELECT COUNT(*) FROM product_images WHERE product_id = ?');
$hasPrimary->execute([$productId]);
$isPrimary = $hasPrimary->fetchColumn() == 0 ? 1 : 0;

$nextSort = $db->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_images WHERE product_id = ?');
$nextSort->execute([$productId]);

$stmt = $db->prepare('INSERT INTO product_images (product_id, image_path, is_primary, sort_order) VALUES (?, ?, ?, ?)');
$stmt->execute([$productId, $relPath, $isPrimary, (int) $nextSort->fetchColumn()]);
$id = (int) $db->lastInsertId();

$row = $db->prepare('SELECT * FROM product_images WHERE id = ?');
$row->execute([$id]);

json_response(true, 'Image uploaded.', ['image' => image_payload($row->fetch())]);
