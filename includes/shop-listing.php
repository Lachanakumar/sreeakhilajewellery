<?php
/**
 * Shared shop listing logic used by products.php, category.php and
 * ajax/filter-products.php so the filter/sort/paginate behaviour is identical.
 */

require_once __DIR__ . '/products.php';

const SHOP_PER_PAGE = 12;

/**
 * @param array $query  Usually $_GET.
 * @return array {items, total, pagination, filters, applied}
 */
function shop_listing(array $query, array $forced = []) {
    $filters = [
        'status' => 'active',
        'search' => trim($query['search'] ?? ''),
        'sort'   => $query['sort'] ?? 'latest',
        'limit'  => SHOP_PER_PAGE,
    ];

    if (isset($query['min_price']) && $query['min_price'] !== '') {
        $filters['min_price'] = (float) $query['min_price'];
    }
    if (!empty($query['max_price'])) {
        $filters['max_price'] = (float) $query['max_price'];
    }
    if (!empty($query['brand'])) {
        $filters['brand_id'] = (int) $query['brand'];
    }
    if (!empty($query['on_sale'])) {
        $filters['on_sale'] = 1;
    }
    if (!empty($query['in_stock'])) {
        $filters['in_stock'] = 1;
    }
    if (!empty($query['featured'])) {
        $filters['featured'] = 1;
    }
    if (!empty($query['is_new'])) {
        $filters['is_new'] = 1;
    }

    // Category: sub (category) or main (main_category) slug/id.
    $activeCategory = null;
    if (!empty($forced['main_category_id'])) {
        $filters['main_category_id'] = (int) $forced['main_category_id'];
    } elseif (!empty($forced['category_id'])) {
        $filters['category_id'] = (int) $forced['category_id'];
    } elseif (!empty($query['category'])) {
        $cat = ctype_digit((string) $query['category'])
            ? getCategory((int) $query['category'])
            : getCategoryBySlug($query['category']);
        if ($cat) {
            $activeCategory = $cat;
            if ($cat['parent_id'] === null) {
                $filters['main_category_id'] = (int) $cat['id'];
            } else {
                $filters['category_id'] = (int) $cat['id'];
            }
        }
    }

    $page = max(1, (int) ($query['page'] ?? 1));
    $filters['offset'] = ($page - 1) * SHOP_PER_PAGE;

    $result = getProducts($filters);
    $pagination = paginate($result['total'], SHOP_PER_PAGE, $page, shop_base_url($query));

    return [
        'items'          => $result['items'],
        'total'          => $result['total'],
        'pagination'     => $pagination,
        'filters'        => $filters,
        'active_category' => $activeCategory,
        'query'          => $query,
    ];
}

function shop_base_url(array $query) {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? 'products.php');
    $keep = array_filter([
        'category'   => $query['category'] ?? null,
        'search'     => $query['search'] ?? null,
        'sort'       => $query['sort'] ?? null,
        'min_price'  => $query['min_price'] ?? null,
        'max_price'  => $query['max_price'] ?? null,
        'brand'      => $query['brand'] ?? null,
        'on_sale'    => $query['on_sale'] ?? null,
        'in_stock'   => $query['in_stock'] ?? null,
        'featured'   => $query['featured'] ?? null,
        'is_new'     => $query['is_new'] ?? null,
    ], fn($v) => $v !== null && $v !== '');
    return $script . ($keep ? '?' . http_build_query($keep) : '');
}

/**
 * Active-product count per category id, with each main category also counting
 * its children. Used for the "(n)" numbers in the filter sidebar.
 * @return array<int,int>
 */
function shop_category_counts() {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $rows = getDB()->query(
            "SELECT c.id, c.parent_id, COUNT(p.id) AS cnt
               FROM categories c
               LEFT JOIN products p ON p.category_id = c.id AND p.status = 'active'
              WHERE c.status = 'active'
              GROUP BY c.id, c.parent_id"
        )->fetchAll();
    } catch (Throwable $e) {
        return $cache = [];
    }
    $own = $parent = [];
    foreach ($rows as $r) {
        $own[(int) $r['id']]    = (int) $r['cnt'];
        $parent[(int) $r['id']] = $r['parent_id'] !== null ? (int) $r['parent_id'] : null;
    }
    $totals = $own;
    foreach ($own as $id => $cnt) {
        $p = $parent[$id];
        if ($p !== null && isset($totals[$p])) {
            $totals[$p] += $cnt;
        }
    }
    return $cache = $totals;
}

/**
 * Counts behind the availability facets in the sidebar.
 * @return array{all:int,in_stock:int,on_sale:int,featured:int,is_new:int}
 */
function shop_facet_counts() {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $empty = ['all' => 0, 'in_stock' => 0, 'on_sale' => 0, 'featured' => 0, 'is_new' => 0];
    try {
        $r = getDB()->query(
            "SELECT COUNT(*) AS all_c,
                    SUM(stock_quantity > 0) AS stock_c,
                    SUM(sale_price IS NOT NULL AND sale_price > 0 AND sale_price < regular_price) AS sale_c,
                    SUM(is_featured = 1) AS feat_c,
                    SUM(created_at > (NOW() - INTERVAL 30 DAY)) AS new_c
               FROM products WHERE status = 'active'"
        )->fetch();
    } catch (Throwable $e) {
        return $cache = $empty;
    }
    return $cache = [
        'all'      => (int) $r['all_c'],
        'in_stock' => (int) $r['stock_c'],
        'on_sale'  => (int) $r['sale_c'],
        'featured' => (int) $r['feat_c'],
        'is_new'   => (int) $r['new_c'],
    ];
}

/**
 * Render the product grid (list of .col cards) for a set of items.
 */
function render_product_grid(array $items) {
    if (empty($items)) {
        echo '<div class="col-12"><div class="shop__empty">'
           . '<svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>'
           . '<p>No products match your selection.</p>'
           . '<span>Try clearing a filter or widening the price range.</span>'
           . '</div></div>';
        return;
    }
    foreach ($items as $product) {
        include __DIR__ . '/product-card-shop.php';
    }
}

/**
 * Render pagination in the theme's markup.
 */
function render_pagination(array $p) {
    if ($p['total_pages'] <= 1) {
        return;
    }
    echo '<div class="pagination__area bg__gray--color pb-5"><nav class="pagination justify-content-center"><ul class="pagination__wrapper d-flex align-items-center justify-content-center">';
    if ($p['has_prev']) {
        echo '<li class="pagination__list"><a class="pagination__item--arrow link__hover" href="' . e(pagination_url($p['base_url'], $p['current_page'] - 1)) . '" aria-label="Previous">&laquo;</a></li>';
    }
    for ($i = 1; $i <= $p['total_pages']; $i++) {
        $active = $i === $p['current_page'] ? ' pagination__item--current' : '';
        echo '<li class="pagination__list' . $active . '"><a class="pagination__item link__hover" href="' . e(pagination_url($p['base_url'], $i)) . '">' . $i . '</a></li>';
    }
    if ($p['has_next']) {
        echo '<li class="pagination__list"><a class="pagination__item--arrow link__hover" href="' . e(pagination_url($p['base_url'], $p['current_page'] + 1)) . '" aria-label="Next">&raquo;</a></li>';
    }
    echo '</ul></nav></div>';
}
