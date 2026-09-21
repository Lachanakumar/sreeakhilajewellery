<?php
require_once __DIR__ . '/_bootstrap.php';

$type = $_POST['type'] ?? 'product';
$id   = (int) ($_POST['id'] ?? 0);
$qty  = (int) ($_POST['stock_quantity'] ?? -1);

if ($qty < 0) {
    json_response(false, 'Enter a valid stock quantity.');
}

$db = getDB();
$table = $type === 'variant' ? 'product_variants' : 'products';
$stmt = $db->prepare("UPDATE $table SET stock_quantity = ? WHERE id = ?");
$stmt->execute([$qty, $id]);

if ($stmt->rowCount() === 0) {
    // rowCount can be 0 if the value was unchanged; verify the row exists.
    $check = $db->prepare("SELECT id FROM $table WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        json_response(false, 'Item not found.');
    }
}

json_response(true, 'Stock updated.', ['id' => $id, 'stock_quantity' => $qty]);
