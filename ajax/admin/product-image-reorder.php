<?php
require_once __DIR__ . '/_bootstrap.php';

$productId = (int) ($_POST['product_id'] ?? 0);
$order = $_POST['order'] ?? [];
if (!is_array($order) || !$order) {
    json_response(false, 'Nothing to reorder.');
}

$db = getDB();
// Validate every id belongs to this product.
$owned = $db->prepare('SELECT id FROM product_images WHERE product_id = ?');
$owned->execute([$productId]);
$ownedIds = array_map('intval', array_column($owned->fetchAll(), 'id'));

$pos = 0;
$db->beginTransaction();
try {
    foreach ($order as $imageId) {
        $imageId = (int) $imageId;
        if (!in_array($imageId, $ownedIds, true)) {
            continue;
        }
        $db->prepare('UPDATE product_images SET sort_order = ? WHERE id = ?')->execute([$pos++, $imageId]);
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    json_response(false, 'Could not save the new order.');
}

json_response(true, 'Order updated.');
