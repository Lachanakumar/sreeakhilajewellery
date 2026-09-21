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

$db->beginTransaction();
try {
    $db->prepare('UPDATE product_images SET is_primary = 0 WHERE product_id = ?')->execute([$img['product_id']]);
    $db->prepare('UPDATE product_images SET is_primary = 1 WHERE id = ?')->execute([$imageId]);
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    json_response(false, 'Could not update the primary image.');
}

json_response(true, 'Primary image updated.', ['primary_id' => $imageId]);
