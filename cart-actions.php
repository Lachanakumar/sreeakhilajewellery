<?php
/**
 * Non-JS fallback handler for cart / wishlist / compare.
 * Primary interactions go through /ajax/*.php; this keeps the site usable
 * without JavaScript. State-changing requests require a valid CSRF token.
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/products.php';
require_once __DIR__ . '/includes/cart-functions.php';

/**
 * Keep a redirect on this site.
 *
 * The target arrives from the request, so an absolute URL, a protocol-relative
 * "//evil.test" or a backslash variant would otherwise send the customer off
 * the site — from a link that looks like it belongs to the shop. Only a bare
 * local path is allowed through.
 */
function cart_safe_redirect($target, $fallback = 'index.php') {
    $target = trim((string) $target);
    if ($target === '') {
        return $fallback;
    }
    // Anything with a scheme ("https:", "javascript:") leaves the site.
    if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $target)) {
        return $fallback;
    }
    // A leading slash or backslash can be protocol-relative ("//evil.test") or
    // an absolute path. Compared directly rather than with a character class,
    // where escaping the backslash is easy to get subtly wrong.
    $first = substr($target, 0, 1);
    if ($first === '/' || $first === '\\') {
        return $fallback;
    }
    $path = parse_url($target, PHP_URL_PATH);
    if (!$path || !preg_match('~^[A-Za-z0-9._-]+\.php$~', basename($path))) {
        return $fallback;
    }
    $query = parse_url($target, PHP_URL_QUERY);
    return basename($path) . ($query ? '?' . $query : '');
}

$redirectTarget = cart_safe_redirect($_POST['redirect'] ?? $_GET['redirect'] ?? '');

$token = $_POST['csrf_token'] ?? $_GET['token'] ?? '';
if (!csrf_verify($token)) {
    flash_set('error', 'Your session expired. Please try again.');
    redirect($redirectTarget);
}

$action     = $_POST['action']     ?? $_GET['action']     ?? '';
$productId  = (int) ($_POST['product_id'] ?? $_GET['product_id'] ?? 0);
$variantId  = ($_POST['variant_id'] ?? $_GET['variant_id'] ?? null);
$variantId  = $variantId !== null && $variantId !== '' ? (int) $variantId : null;
$qty        = (int) ($_POST['qty'] ?? $_GET['qty'] ?? 1);
$redirect   = $redirectTarget;

// "Buy now" posts the product form with buy_now=1: same add, then straight to
// checkout. Staying on the product page when the add fails keeps the error visible.
if (!empty($_POST['buy_now'])) {
    $action = 'buy_now';
}

switch ($action) {
    case 'add_to_cart':
        [$ok, $msg] = addToCart($productId, $qty, $variantId);
        flash_set($ok ? 'success' : 'error', $msg);
        break;
    case 'buy_now':
        [$ok, $msg] = addToCart($productId, $qty, $variantId);
        if ($ok) {
            $redirect = 'checkout.php';
        } else {
            flash_set('error', $msg);
            $redirect = 'product-details.php?id=' . $productId;
        }
        break;
    case 'remove_from_cart':
        removeFromCart($productId, $variantId);
        flash_set('success', 'Item removed from cart.');
        break;
    case 'update_cart':
        updateCartQty($productId, $qty, $variantId);
        break;
    case 'clear_cart':
        clearCart();
        break;
    case 'add_to_wishlist':
        addToWishlist($productId);
        flash_set('success', 'Added to wishlist.');
        break;
    case 'remove_from_wishlist':
        removeFromWishlist($productId);
        flash_set('success', 'Removed from wishlist.');
        break;
    case 'move_to_cart':
        moveWishlistToCart($productId);
        flash_set('success', 'Moved to cart.');
        $redirect = 'cart.php';
        break;
    case 'add_to_compare':
        addToCompare($productId);
        break;
    case 'remove_from_compare':
        removeFromCompare($productId);
        break;
    case 'apply_coupon':
        [$ok, $msg] = applyCouponToSession($_POST['coupon_code'] ?? '');
        flash_set($ok ? 'success' : 'error', $msg);
        break;
    case 'remove_coupon':
        removeCouponFromSession();
        break;
    case 'add_to_cart_checkout':
        addToCart($productId, $qty, $variantId);
        $redirect = 'checkout.php';
        break;
}

redirect($redirect);
