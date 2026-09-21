<?php
require_once __DIR__ . '/_bootstrap.php';

$imageId = (int) ($_POST['image_id'] ?? 0);
$db = getDB();

$stmt = $db->prepare('SELECT * FROM product_images WHERE id = ? LIMIT 1');
$stmt->execute([$imageId]);
$img = $stmt->fetch();
if (!$img) {
    json_response(false, 'Image not found.');
}

// Remove the physical file (only inside our uploads tree).
if (strpos($img['image_path'], 'uploads/products/') === 0) {
    $path = project_root() . '/' . $img['image_path'];
    if (is_file($path)) {
        @unlink($path);
    }
}

$db->prepare('DELETE FROM product_images WHERE id = ?')->execute([$imageId]);

// If we removed the primary, promote the next image.
if ($img['is_primary']) {
    $next = $db->prepare('SELECT id FROM product_images WHERE product_id = ? ORDER BY sort_order, id LIMIT 1');
    $next->execute([$img['product_id']]);
    if ($nextId = $next->fetchColumn()) {
        $db->prepare('UPDATE product_images SET is_primary = 1 WHERE id = ?')->execute([$nextId]);
    }
}

$remaining = $db->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order, id');
$remaining->execute([$img['product_id']]);
json_response(true, 'Image deleted.', [
    'images' => array_map('image_payload', $remaining->fetchAll()),
]);
