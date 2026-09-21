<?php
require_once __DIR__ . '/_bootstrap.php';

require_post();
csrf_verify_or_fail();

$op        = $_POST['op'] ?? 'toggle';
$productId = (int) ($_POST['product_id'] ?? 0);

if ($productId <= 0 || !getProduct($productId)) {
    json_response(false, 'Invalid product.');
}

$inList = isInWishlist($productId);

switch ($op) {
    case 'add':
        addToWishlist($productId);
        $inList = true;
        $message = 'Added to your wishlist.';
        break;
    case 'remove':
        removeFromWishlist($productId);
        $inList = false;
        $message = 'Removed from your wishlist.';
        break;
    case 'move_to_cart':
        moveWishlistToCart($productId);
        $inList = false;
        json_response(true, 'Moved to cart.', [
            'in_wishlist'    => false,
            'wishlist_count' => getWishlistCount(),
            'cart_count'     => getCartCount(),
        ]);
        break;
    case 'toggle':
    default:
        if ($inList) {
            removeFromWishlist($productId);
            $inList = false;
            $message = 'Removed from your wishlist.';
        } else {
            addToWishlist($productId);
            $inList = true;
            $message = 'Added to your wishlist.';
        }
        break;
}

json_response(true, $message, [
    'in_wishlist'    => $inList,
    'wishlist_count' => getWishlistCount(),
    'cart_count'     => getCartCount(),
]);
