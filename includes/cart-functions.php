<?php
/**
 * Cart & wishlist: database-backed, keyed by user_id when logged in, else by the
 * PHP session id for guests. Compare stays session-only.
 *
 * Public function names are unchanged from the original theme so existing pages
 * keep working.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/products.php';

if (!isset($_SESSION['compare'])) {
    $_SESSION['compare'] = [];
}

/* ==================== OWNER KEY ==================== */

function cart_owner() {
    if (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0) {
        return ['column' => 'user_id', 'value' => (int) $_SESSION['user_id']];
    }
    return ['column' => 'session_id', 'value' => session_id()];
}

function cart_owner_sql($alias = '') {
    $o = cart_owner();
    $col = ($alias ? $alias . '.' : '') . $o['column'];
    return [$col . ' = ?', $o['value']];
}

/**
 * On login, fold any guest cart/wishlist rows into the user's own.
 */
function cart_merge_guest_into_user($userId) {
    $db = getDB();
    $sid = session_id();

    // Cart: merge quantities on matching product/variant, else reassign.
    $rows = $db->prepare('SELECT * FROM carts WHERE session_id = ?');
    $rows->execute([$sid]);
    foreach ($rows->fetchAll() as $row) {
        $find = $db->prepare('SELECT id, quantity FROM carts WHERE user_id = ? AND product_id = ? AND ' .
            ($row['variant_id'] === null ? 'variant_id IS NULL' : 'variant_id = ?') . ' LIMIT 1');
        $params = [$userId, $row['product_id']];
        if ($row['variant_id'] !== null) { $params[] = $row['variant_id']; }
        $find->execute($params);
        if ($existing = $find->fetch()) {
            $db->prepare('UPDATE carts SET quantity = quantity + ? WHERE id = ?')
               ->execute([$row['quantity'], $existing['id']]);
            $db->prepare('DELETE FROM carts WHERE id = ?')->execute([$row['id']]);
        } else {
            $db->prepare('UPDATE carts SET user_id = ?, session_id = NULL WHERE id = ?')
               ->execute([$userId, $row['id']]);
        }
    }

    // Wishlist: skip duplicates.
    $rows = $db->prepare('SELECT * FROM wishlists WHERE session_id = ?');
    $rows->execute([$sid]);
    foreach ($rows->fetchAll() as $row) {
        $find = $db->prepare('SELECT id FROM wishlists WHERE user_id = ? AND product_id = ? LIMIT 1');
        $find->execute([$userId, $row['product_id']]);
        if ($find->fetch()) {
            $db->prepare('DELETE FROM wishlists WHERE id = ?')->execute([$row['id']]);
        } else {
            $db->prepare('UPDATE wishlists SET user_id = ?, session_id = NULL WHERE id = ?')
               ->execute([$userId, $row['id']]);
        }
    }
}

/* ==================== CART ==================== */

function addToCart($productId, $qty = 1, $variantId = null) {
    $productId = (int) $productId;
    $qty = max(1, (int) $qty);
    $variantId = $variantId ? (int) $variantId : null;

    $product = getProduct($productId);
    if (!$product) {
        return [false, 'Product not found.'];
    }

    $stock = (int) $product['stock_quantity'];
    if ($variantId) {
        $variant = getVariant($variantId);
        if (!$variant || (int) $variant['product_id'] !== $productId) {
            return [false, 'Invalid product option.'];
        }
        $stock = (int) $variant['stock_quantity'];
    }
    // Guard the zero case explicitly: the clamp below is skipped when $stock is 0,
    // which would otherwise let an out-of-stock item into the cart.
    if ($stock <= 0) {
        return [false, 'This item is currently out of stock.'];
    }

    $db = getDB();
    [$ownerSql, $ownerVal] = cart_owner_sql();

    $sql = "SELECT id, quantity FROM carts WHERE $ownerSql AND product_id = ? AND " .
        ($variantId === null ? 'variant_id IS NULL' : 'variant_id = ?') . ' LIMIT 1';
    $params = [$ownerVal, $productId];
    if ($variantId !== null) { $params[] = $variantId; }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $existing = $stmt->fetch();

    $newQty = ($existing ? (int) $existing['quantity'] : 0) + $qty;
    if ($stock > 0 && $newQty > $stock) {
        $newQty = $stock;
        if ($newQty <= ($existing['quantity'] ?? 0)) {
            return [false, 'No more stock available for this item.'];
        }
    }

    $owner = cart_owner();
    if ($existing) {
        $db->prepare('UPDATE carts SET quantity = ? WHERE id = ?')->execute([$newQty, $existing['id']]);
    } else {
        $db->prepare('INSERT INTO carts (user_id, session_id, product_id, variant_id, quantity) VALUES (?, ?, ?, ?, ?)')
           ->execute([
               $owner['column'] === 'user_id' ? $owner['value'] : null,
               $owner['column'] === 'session_id' ? $owner['value'] : null,
               $productId,
               $variantId,
               $newQty,
           ]);
    }
    return [true, 'Added to cart.'];
}

function removeFromCart($productId, $variantId = null) {
    $db = getDB();
    [$ownerSql, $ownerVal] = cart_owner_sql();
    if ($variantId === null && func_num_args() < 2) {
        // Legacy call: remove every row for this product.
        $stmt = $db->prepare("DELETE FROM carts WHERE $ownerSql AND product_id = ?");
        $stmt->execute([$ownerVal, (int) $productId]);
        return;
    }
    $sql = "DELETE FROM carts WHERE $ownerSql AND product_id = ? AND " .
        ($variantId === null ? 'variant_id IS NULL' : 'variant_id = ?');
    $params = [$ownerVal, (int) $productId];
    if ($variantId !== null) { $params[] = (int) $variantId; }
    $db->prepare($sql)->execute($params);
}

function updateCartQty($productId, $qty, $variantId = null) {
    $qty = (int) $qty;
    if ($qty <= 0) {
        removeFromCart($productId, $variantId);
        return [true, 'Item removed.'];
    }
    $productId = (int) $productId;
    $product = getProduct($productId);
    if (!$product) {
        return [false, 'Product not found.'];
    }
    $stock = $product['stock_quantity'];
    if ($variantId) {
        $variant = getVariant($variantId);
        $stock = $variant ? (int) $variant['stock_quantity'] : $stock;
    }
    $capped = false;
    if ($stock > 0 && $qty > $stock) {
        $qty = $stock;
        $capped = true;
    }
    $db = getDB();
    [$ownerSql, $ownerVal] = cart_owner_sql();
    $sql = "UPDATE carts SET quantity = ? WHERE $ownerSql AND product_id = ? AND " .
        ($variantId === null ? 'variant_id IS NULL' : 'variant_id = ?');
    $params = [$qty, $ownerVal, $productId];
    if ($variantId !== null) { $params[] = (int) $variantId; }
    $db->prepare($sql)->execute($params);
    return [true, $capped ? 'Quantity adjusted to available stock.' : 'Cart updated.'];
}

function getCartRows() {
    $db = getDB();
    [$ownerSql, $ownerVal] = cart_owner_sql('c');
    $stmt = $db->prepare("SELECT c.* FROM carts c WHERE $ownerSql ORDER BY c.id ASC");
    $stmt->execute([$ownerVal]);
    return $stmt->fetchAll();
}

function getCartItems() {
    $items = [];
    foreach (getCartRows() as $row) {
        $product = getProduct($row['product_id']);
        if (!$product) {
            continue;
        }
        $unitPrice = $product['price'];
        $variantLabel = '';
        if ($row['variant_id']) {
            $variant = getVariant($row['variant_id']);
            if ($variant) {
                $unitPrice += (float) $variant['price_adjustment'];
                $variantLabel = $variant['variant_name'] . ': ' . $variant['variant_value'];
            }
        }
        $qty = (int) $row['quantity'];
        $product['cart_row_id'] = (int) $row['id'];
        $product['variant_id'] = $row['variant_id'] ? (int) $row['variant_id'] : null;
        $product['variant_label'] = $variantLabel;
        $product['qty'] = $qty;
        $product['unit_price'] = $unitPrice;
        $product['price'] = $unitPrice;
        $product['subtotal'] = $unitPrice * $qty;
        $items[] = $product;
    }
    return $items;
}

function getCartSubtotal() {
    $total = 0;
    foreach (getCartItems() as $item) {
        $total += $item['subtotal'];
    }
    return $total;
}

// Legacy name.
function getCartTotal() {
    return getCartSubtotal();
}

function getCartCount() {
    $db = getDB();
    [$ownerSql, $ownerVal] = cart_owner_sql();
    $stmt = $db->prepare("SELECT COALESCE(SUM(quantity),0) FROM carts WHERE $ownerSql");
    $stmt->execute([$ownerVal]);
    return (int) $stmt->fetchColumn();
}

function clearCart() {
    $db = getDB();
    [$ownerSql, $ownerVal] = cart_owner_sql();
    $db->prepare("DELETE FROM carts WHERE $ownerSql")->execute([$ownerVal]);
}

/* ==================== COUPON / SHIPPING / TOTALS ==================== */

/**
 * Where a coupon stands today, independent of any particular basket.
 *
 * starts_at and expires_at are DATE columns — whole days, no time of day — so
 * every comparison here is a date-string comparison. Running them through
 * strtotime() turns '2026-09-21' into midnight, which makes a coupon read as
 * expired for the whole of its own last day; that is why the admin list used to
 * stamp "expired" on coupons checkout was still happily accepting. A coupon
 * that starts and ends on the same day is valid for all of that day.
 *
 * @return string 'inactive' | 'scheduled' | 'expired' | 'used_up' | 'active'
 */
function coupon_state(array $coupon) {
    $today = date('Y-m-d');

    if (($coupon['status'] ?? '') !== 'active') {
        return 'inactive';
    }
    if (!empty($coupon['starts_at']) && $coupon['starts_at'] > $today) {
        return 'scheduled';
    }
    if (!empty($coupon['expires_at']) && $coupon['expires_at'] < $today) {
        return 'expired';
    }
    if ($coupon['usage_limit'] !== null && (int) $coupon['used_count'] >= (int) $coupon['usage_limit']) {
        return 'used_up';
    }
    return 'active';
}

/** How coupon_state() reads on screen. */
function coupon_state_label($state) {
    return [
        'inactive'  => 'Inactive',
        'scheduled' => 'Scheduled',
        'expired'   => 'Expired',
        'used_up'   => 'Limit reached',
        'active'    => 'Running',
    ][$state] ?? ucfirst($state);
}

function findValidCoupon($code, $subtotal) {
    $stmt = getDB()->prepare('SELECT * FROM coupons WHERE code = ? LIMIT 1');
    $stmt->execute([strtoupper(trim($code))]);
    $coupon = $stmt->fetch();
    if (!$coupon) {
        return [null, 'Invalid coupon code.'];
    }

    // one definition of validity, shared with the admin list
    switch (coupon_state($coupon)) {
        case 'inactive':  return [null, 'Invalid coupon code.'];
        case 'scheduled': return [null, 'This coupon is not active yet.'];
        case 'expired':   return [null, 'This coupon has expired.'];
        case 'used_up':   return [null, 'This coupon has reached its usage limit.'];
    }
    if ($subtotal < (float) $coupon['min_order_amount']) {
        return [null, 'Order must be at least ' . formatPrice($coupon['min_order_amount']) . ' to use this coupon.'];
    }
    return [$coupon, 'Coupon applied.'];
}

function couponDiscount(array $coupon, $subtotal) {
    if ($coupon['type'] === 'percent') {
        $discount = $subtotal * ((float) $coupon['value'] / 100);
        if ($coupon['max_discount'] !== null) {
            $discount = min($discount, (float) $coupon['max_discount']);
        }
    } else {
        $discount = (float) $coupon['value'];
    }
    return round(min($discount, $subtotal), 2);
}

function shippingAmount($subtotalAfterDiscount) {
    $threshold = (float) get_setting('free_shipping_threshold', 50000);
    $flat = (float) get_setting('shipping_flat_rate', 250);
    if ($subtotalAfterDiscount <= 0) {
        return 0;
    }
    return $subtotalAfterDiscount >= $threshold ? 0 : $flat;
}

/**
 * Full totals breakdown. Reads the applied coupon code from the session.
 */
function cartTotals() {
    $subtotal = getCartSubtotal();
    $discount = 0;
    $coupon = null;
    $couponMessage = null;

    if (!empty($_SESSION['coupon_code'])) {
        [$coupon, $couponMessage] = findValidCoupon($_SESSION['coupon_code'], $subtotal);
        if ($coupon) {
            $discount = couponDiscount($coupon, $subtotal);
        } else {
            unset($_SESSION['coupon_code']);
        }
    }

    $shipping = shippingAmount($subtotal - $discount);
    $taxPercent = (float) get_setting('tax_percent', 0);
    $tax = round((($subtotal - $discount) * $taxPercent) / 100, 2);
    $grand = max(0, $subtotal - $discount + $shipping + $tax);

    return [
        'subtotal'       => round($subtotal, 2),
        'discount'       => round($discount, 2),
        'coupon'         => $coupon,
        'coupon_code'    => $coupon['code'] ?? null,
        'coupon_message' => $couponMessage,
        'shipping'       => round($shipping, 2),
        'tax'            => $tax,
        'tax_percent'    => $taxPercent,
        'total'          => round($grand, 2),
    ];
}

function applyCouponToSession($code) {
    $subtotal = getCartSubtotal();
    [$coupon, $message] = findValidCoupon($code, $subtotal);
    if (!$coupon) {
        unset($_SESSION['coupon_code']);
        return [false, $message];
    }
    $_SESSION['coupon_code'] = $coupon['code'];
    return [true, $message];
}

function removeCouponFromSession() {
    unset($_SESSION['coupon_code']);
}

/* ==================== WISHLIST ==================== */

function addToWishlist($productId) {
    $db = getDB();
    [$ownerSql, $ownerVal] = cart_owner_sql();
    $stmt = $db->prepare("SELECT id FROM wishlists WHERE $ownerSql AND product_id = ? LIMIT 1");
    $stmt->execute([$ownerVal, (int) $productId]);
    if ($stmt->fetch()) {
        return;
    }
    $owner = cart_owner();
    $db->prepare('INSERT INTO wishlists (user_id, session_id, product_id) VALUES (?, ?, ?)')
       ->execute([
           $owner['column'] === 'user_id' ? $owner['value'] : null,
           $owner['column'] === 'session_id' ? $owner['value'] : null,
           (int) $productId,
       ]);
}

function removeFromWishlist($productId) {
    $db = getDB();
    [$ownerSql, $ownerVal] = cart_owner_sql();
    $db->prepare("DELETE FROM wishlists WHERE $ownerSql AND product_id = ?")
       ->execute([$ownerVal, (int) $productId]);
}

function getWishlistItems() {
    $db = getDB();
    [$ownerSql, $ownerVal] = cart_owner_sql('w');
    $stmt = $db->prepare("SELECT w.product_id FROM wishlists w WHERE $ownerSql ORDER BY w.id DESC");
    $stmt->execute([$ownerVal]);
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $product = getProduct($row['product_id']);
        if ($product) {
            $items[$product['id']] = $product;
        }
    }
    return $items;
}

function getWishlistCount() {
    $db = getDB();
    [$ownerSql, $ownerVal] = cart_owner_sql();
    $stmt = $db->prepare("SELECT COUNT(*) FROM wishlists WHERE $ownerSql");
    $stmt->execute([$ownerVal]);
    return (int) $stmt->fetchColumn();
}

function isInWishlist($productId) {
    $db = getDB();
    [$ownerSql, $ownerVal] = cart_owner_sql();
    $stmt = $db->prepare("SELECT id FROM wishlists WHERE $ownerSql AND product_id = ? LIMIT 1");
    $stmt->execute([$ownerVal, (int) $productId]);
    return (bool) $stmt->fetch();
}

function moveWishlistToCart($productId) {
    removeFromWishlist($productId);
    addToCart($productId, 1);
}

/* ==================== COMPARE (session only) ==================== */

function addToCompare($productId) {
    $productId = (int) $productId;
    if (!in_array($productId, $_SESSION['compare'], true) && count($_SESSION['compare']) < 4) {
        $_SESSION['compare'][] = $productId;
    }
}

function removeFromCompare($productId) {
    $key = array_search((int) $productId, $_SESSION['compare'], true);
    if ($key !== false) {
        unset($_SESSION['compare'][$key]);
        $_SESSION['compare'] = array_values($_SESSION['compare']);
    }
}

function getCompareItems() {
    $items = [];
    foreach ($_SESSION['compare'] as $id) {
        $product = getProduct($id);
        if ($product) {
            $items[$id] = $product;
        }
    }
    return $items;
}

function getCompareCount() {
    return count($_SESSION['compare']);
}

function isInCompare($productId) {
    return in_array((int) $productId, $_SESSION['compare'], true);
}
