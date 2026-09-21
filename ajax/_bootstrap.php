<?php
/**
 * Shared bootstrap for storefront AJAX endpoints.
 * Every endpoint returns {"success": bool, "message": string, "data": {...}}.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/products.php';
require_once __DIR__ . '/../includes/cart-functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

function require_post() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(false, 'Method not allowed.', [], 405);
    }
}

/**
 * Standard cart snapshot returned after any cart mutation so the UI can refresh
 * counters and the mini-cart without a reload.
 */
function cart_snapshot() {
    $items = getCartItems();
    $totals = cartTotals();
    $rendered = [];
    foreach ($items as $item) {
        $rendered[] = [
            'row_id'        => $item['cart_row_id'],
            'id'            => $item['id'],
            'name'          => $item['name'],
            'url'           => 'product-details.php?id=' . $item['id'],
            'image'         => $item['image'],
            'category'      => $item['category'],
            'variant_label' => $item['variant_label'],
            'qty'           => $item['qty'],
            'price'         => $item['unit_price'],
            'price_html'    => formatPrice($item['unit_price']),
            'subtotal'      => $item['subtotal'],
            'subtotal_html' => formatPrice($item['subtotal']),
        ];
    }
    return [
        'cart_count'     => getCartCount(),
        'wishlist_count' => getWishlistCount(),
        'items'          => $rendered,
        'totals'         => [
            'subtotal'      => $totals['subtotal'],
            'subtotal_html' => formatPrice($totals['subtotal']),
            'discount'      => $totals['discount'],
            'discount_html' => formatPrice($totals['discount']),
            'shipping'      => $totals['shipping'],
            'shipping_html' => $totals['shipping'] > 0 ? formatPrice($totals['shipping']) : 'Free',
            'tax'           => $totals['tax'],
            'tax_html'      => formatPrice($totals['tax']),
            'tax_percent'   => $totals['tax_percent'],
            'total'         => $totals['total'],
            'total_html'    => formatPrice($totals['total']),
            'coupon_code'   => $totals['coupon_code'],
        ],
    ];
}
