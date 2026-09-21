<?php
require_once __DIR__ . '/../includes/shop-listing.php';
require_once __DIR__ . '/../includes/cart-functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$forced = [];
if (!empty($_GET['force_category'])) {
    $forced['category_id'] = (int) $_GET['force_category'];
}
if (!empty($_GET['force_main_category'])) {
    $forced['main_category_id'] = (int) $_GET['force_main_category'];
}

$listing = shop_listing($_GET, $forced);

ob_start();
render_product_grid($listing['items']);
$grid = ob_get_clean();

ob_start();
render_pagination($listing['pagination']);
$pager = ob_get_clean();

$total = (int) $listing['total'];
$page  = (int) $listing['pagination']['current_page'];

json_response(true, '', [
    'grid_html'       => $grid,
    'pagination_html' => $pager,
    'total'           => $total,
    'showing'         => count($listing['items']),
    'from'            => $total ? (($page - 1) * SHOP_PER_PAGE) + 1 : 0,
    'to'              => min($page * SHOP_PER_PAGE, $total),
    'page'            => $page,
    'total_pages'     => $listing['pagination']['total_pages'],
]);
