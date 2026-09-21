<?php
require_once __DIR__ . '/_bootstrap.php';

require_post();
csrf_verify_or_fail();

$op        = $_POST['op'] ?? 'add';
$productId = (int) ($_POST['product_id'] ?? 0);
$variantId = isset($_POST['variant_id']) && $_POST['variant_id'] !== '' ? (int) $_POST['variant_id'] : null;
$qty       = (int) ($_POST['qty'] ?? 1);
$rowId     = (int) ($_POST['row_id'] ?? 0);

/* Resolve a cart row id to its product/variant for update & remove ops. */
if ($rowId > 0 && in_array($op, ['update', 'remove'], true)) {
    foreach (getCartRows() as $row) {
        if ((int) $row['id'] === $rowId) {
            $productId = (int) $row['product_id'];
            $variantId = $row['variant_id'] !== null ? (int) $row['variant_id'] : null;
            break;
        }
    }
}

switch ($op) {
    case 'add':
        if ($productId <= 0) {
            json_response(false, 'Invalid product.');
        }
        [$ok, $msg] = addToCart($productId, max(1, $qty), $variantId);
        json_response($ok, $msg, cart_snapshot());
        break;

    case 'update':
        [$ok, $msg] = updateCartQty($productId, $qty, $variantId);
        json_response($ok, $msg, cart_snapshot());
        break;

    case 'remove':
        removeFromCart($productId, $variantId);
        json_response(true, 'Item removed from cart.', cart_snapshot());
        break;

    case 'clear':
        clearCart();
        json_response(true, 'Cart cleared.', cart_snapshot());
        break;

    default:
        json_response(false, 'Unknown cart operation.');
}
