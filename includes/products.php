<?php
/**
 * Product data layer (MySQL-backed).
 *
 * Product arrays returned here keep the same keys the original static theme used
 * (image, image2, big_image, price, old_price, badge, rating, category, ...) so
 * the existing storefront markup keeps working unchanged.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

/* ==================== HYDRATION ==================== */

function product_placeholder_image() {
    return 'assets/img/product/items/ring.jpg';
}

/**
 * Turn a DB products row into the shape the theme expects.
 */
function hydrate_product(array $row) {
    $db = getDB();

    // Images
    $stmt = $db->prepare('SELECT image_path, is_primary FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC');
    $stmt->execute([$row['id']]);
    $images = $stmt->fetchAll();
    $paths = array_column($images, 'image_path');
    if (empty($paths)) {
        $paths = [product_placeholder_image()];
    }
    $primary = $paths[0];
    $secondary = $paths[1] ?? $primary;

    // Category label "Sub, Main"
    $categoryLabel = '';
    $mainCategory = null;
    $subCategory = null;
    if (!empty($row['category_id'])) {
        $cstmt = $db->prepare('SELECT c.id, c.name, c.slug, c.parent_id, p.name AS parent_name, p.slug AS parent_slug
                               FROM categories c LEFT JOIN categories p ON p.id = c.parent_id
                               WHERE c.id = ? LIMIT 1');
        $cstmt->execute([$row['category_id']]);
        if ($cat = $cstmt->fetch()) {
            $subCategory = ['id' => (int) $cat['id'], 'name' => $cat['name'], 'slug' => $cat['slug']];
            if ($cat['parent_id']) {
                $mainCategory = ['id' => (int) $cat['parent_id'], 'name' => $cat['parent_name'], 'slug' => $cat['parent_slug']];
                $categoryLabel = $cat['name'] . ', ' . $cat['parent_name'];
            } else {
                $mainCategory = $subCategory;
                $categoryLabel = $cat['name'];
            }
        }
    }

    // Rating aggregate
    $rstmt = $db->prepare('SELECT COUNT(*) AS cnt, COALESCE(AVG(rating),0) AS avg FROM reviews WHERE product_id = ? AND status = "approved"');
    $rstmt->execute([$row['id']]);
    $ragg = $rstmt->fetch();
    $ratingAvg = round((float) $ragg['avg'], 1);
    $ratingCount = (int) $ragg['cnt'];

    $hasSale = $row['sale_price'] !== null && (float) $row['sale_price'] > 0 && (float) $row['sale_price'] < (float) $row['regular_price'];
    $price = $hasSale ? (float) $row['sale_price'] : (float) $row['regular_price'];
    $oldPrice = $hasSale ? (float) $row['regular_price'] : null;
    $discountPct = $hasSale ? (int) round((($row['regular_price'] - $row['sale_price']) / $row['regular_price']) * 100) : 0;

    $badge = '';
    if ($hasSale) {
        $badge = 'Sale';
    } elseif (strtotime($row['created_at']) > strtotime('-30 days')) {
        $badge = 'New';
    }

    return [
        'id'               => (int) $row['id'],
        'name'             => $row['name'],
        'slug'             => $row['slug'],
        'sku'              => $row['sku'],
        'barcode'          => $row['sku'],
        'size'             => '',
        'category'         => $categoryLabel,
        'category_id'      => $row['category_id'] ? (int) $row['category_id'] : null,
        'main_category'    => $mainCategory,
        'sub_category'     => $subCategory,
        'brand_id'         => $row['brand_id'] ? (int) $row['brand_id'] : null,
        'price'            => $price,
        'old_price'        => $oldPrice,
        'regular_price'    => (float) $row['regular_price'],
        'sale_price'       => $hasSale ? (float) $row['sale_price'] : null,
        'discount_percent' => $discountPct,
        'badge'            => $badge,
        'is_featured'      => (int) $row['is_featured'],
        'stock_quantity'   => (int) $row['stock_quantity'],
        'in_stock'         => (int) $row['stock_quantity'] > 0,
        'image'            => $primary,
        'image2'           => $secondary,
        'big_image'        => $primary,
        'big_image2'       => $secondary,
        'gallery'          => $paths,
        'description'      => $row['short_description'],
        'short_description' => $row['short_description'],
        'long_description' => $row['description'],
        'additional_info'  => $row['specifications'],
        'specifications'   => $row['specifications'],
        'weight'           => $row['weight'],
        'purity'           => $row['purity'],
        // metadata only — making charge does not take part in cart/checkout totals
        'making_charge'    => isset($row['making_charge']) && $row['making_charge'] !== null ? (float) $row['making_charge'] : null,
        'show_on_homepage' => isset($row['show_on_homepage']) ? (int) $row['show_on_homepage'] : 1,
        'vendor'           => SITE_NAME,
        'type'             => $subCategory['name'] ?? '',
        'rating'           => $ratingAvg > 0 ? $ratingAvg : 0,
        'rating_count'     => $ratingCount,
        'views'            => (int) ($row['views'] ?? 0),
        'created_at'       => $row['created_at'],
        'status'           => $row['status'],
    ];
}

function hydrate_products(array $rows) {
    $out = [];
    foreach ($rows as $row) {
        $p = hydrate_product($row);
        $out[$p['id']] = $p;
    }
    return $out;
}

/* ==================== QUERIES ==================== */

/**
 * Flexible product listing.
 * $filters: category_id (int|array), main_category_id, brand_id, search,
 *           min_price, max_price, sort, on_sale, featured, status,
 *           limit, offset. Returns ['items'=>[], 'total'=>int].
 */
function getProducts(array $filters = []) {
    $db = getDB();
    $where = [];
    $params = [];

    $status = $filters['status'] ?? 'active';
    if ($status !== 'any') {
        $where[] = 'p.status = ?';
        $params[] = $status;
    }

    if (!empty($filters['category_id'])) {
        $ids = (array) $filters['category_id'];
        $where[] = 'p.category_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        foreach ($ids as $id) { $params[] = (int) $id; }
    }

    if (!empty($filters['main_category_id'])) {
        $where[] = 'p.category_id IN (SELECT id FROM categories WHERE parent_id = ? OR id = ?)';
        $params[] = (int) $filters['main_category_id'];
        $params[] = (int) $filters['main_category_id'];
    }

    if (!empty($filters['brand_id'])) {
        $where[] = 'p.brand_id = ?';
        $params[] = (int) $filters['brand_id'];
    }

    if (!empty($filters['search'])) {
        $where[] = '(p.name LIKE ? OR p.short_description LIKE ? OR p.sku LIKE ?)';
        $like = '%' . $filters['search'] . '%';
        $params[] = $like; $params[] = $like; $params[] = $like;
    }

    $priceExpr = 'COALESCE(NULLIF(p.sale_price,0), p.regular_price)';
    if (isset($filters['min_price']) && $filters['min_price'] !== '') {
        $where[] = "$priceExpr >= ?";
        $params[] = (float) $filters['min_price'];
    }
    if (isset($filters['max_price']) && $filters['max_price'] !== '') {
        $where[] = "$priceExpr <= ?";
        $params[] = (float) $filters['max_price'];
    }

    if (!empty($filters['on_sale'])) {
        $where[] = 'p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.regular_price';
    }
    // homepage rows only show products the admin flagged for the homepage
    if (!empty($filters['home_only'])) {
        $where[] = 'p.show_on_homepage = 1';
    }

    if (isset($filters['featured'])) {
        $where[] = 'p.is_featured = ?';
        $params[] = $filters['featured'] ? 1 : 0;
    }
    if (!empty($filters['in_stock'])) {
        $where[] = 'p.stock_quantity > 0';
    }
    // "New" matches the badge rule in hydrate_product(): created in the last 30 days.
    if (!empty($filters['is_new'])) {
        $where[] = 'p.created_at > (NOW() - INTERVAL 30 DAY)';
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    switch ($filters['sort'] ?? 'latest') {
        case 'price_low':  $orderSql = "ORDER BY $priceExpr ASC"; break;
        case 'price_high': $orderSql = "ORDER BY $priceExpr DESC"; break;
        case 'name_asc':   $orderSql = 'ORDER BY p.name ASC'; break;
        case 'name_desc':  $orderSql = 'ORDER BY p.name DESC'; break;
        case 'best_selling':
            $orderSql = 'ORDER BY (SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi WHERE oi.product_id = p.id) DESC, p.id DESC';
            break;
        case 'oldest':     $orderSql = 'ORDER BY p.created_at ASC'; break;
        case 'latest':
        default:           $orderSql = 'ORDER BY p.created_at DESC, p.id DESC'; break;
    }

    $countStmt = $db->prepare("SELECT COUNT(*) FROM products p $whereSql");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $limitSql = '';
    if (isset($filters['limit'])) {
        $limitSql = 'LIMIT ' . (int) $filters['limit'];
        if (isset($filters['offset'])) {
            $limitSql .= ' OFFSET ' . (int) $filters['offset'];
        }
    }

    $stmt = $db->prepare("SELECT p.* FROM products p $whereSql $orderSql $limitSql");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    return ['items' => hydrate_products($rows), 'total' => $total];
}

/**
 * Backwards-compatible: return every active product keyed by id.
 */
function getAllProducts() {
    return getProducts(['limit' => 500])['items'];
}

function getProduct($id) {
    $stmt = getDB()->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $id]);
    $row = $stmt->fetch();
    return $row ? hydrate_product($row) : null;
}

function getProductBySlug($slug) {
    $stmt = getDB()->prepare('SELECT * FROM products WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ? hydrate_product($row) : null;
}

function incrementProductViews($id) {
    $stmt = getDB()->prepare('UPDATE products SET views = views + 1 WHERE id = ?');
    $stmt->execute([(int) $id]);
}

/* The four homepage rows honour the product's "Show in homepage" switch. */
function getFeaturedProducts($limit = 8) {
    return getProducts(['featured' => 1, 'home_only' => 1, 'limit' => $limit])['items'];
}

function getLatestProducts($limit = 8) {
    return getProducts(['sort' => 'latest', 'home_only' => 1, 'limit' => $limit])['items'];
}

function getBestSellingProducts($limit = 8) {
    return getProducts(['sort' => 'best_selling', 'home_only' => 1, 'limit' => $limit])['items'];
}

function getOfferProducts($limit = 8) {
    return getProducts(['on_sale' => 1, 'home_only' => 1, 'limit' => $limit])['items'];
}

/**
 * Related products: same sub-category, else same main category.
 */
function getRelatedProducts($categoryOrId, $excludeId, $limit = 4) {
    // Accept either a category label (legacy) or a product array/id.
    $product = null;
    if (is_numeric($excludeId)) {
        $product = getProduct($excludeId);
    }
    $excludeId = (int) $excludeId;

    /* Widen out in stages. A sub-category here often holds only one or two
       other pieces, which left the row nearly empty — and a near-empty looping
       carousel is what made the same product appear several times over. Each
       stage only tops up what the previous one could not fill, and the array is
       keyed by product id throughout so nothing can be added twice. */
    $stages = [];
    if ($product && $product['sub_category']) {
        $stages[] = ['category_id' => $product['sub_category']['id']];
    }
    if ($product && $product['main_category']) {
        $stages[] = ['main_category_id' => $product['main_category']['id']];
    }
    $stages[] = [];  // anything else that is live

    $items = [];
    foreach ($stages as $stage) {
        if (count($items) >= $limit) {
            break;
        }
        // over-fetch: the current product and anything already collected get
        // dropped below, so asking for exactly $limit would come up short
        $stage['limit'] = $limit + count($items) + 1;
        $stage['sort']  = 'latest';

        foreach (getProducts($stage)['items'] as $id => $candidate) {
            $id = (int) $id;
            if ($id === $excludeId || isset($items[$id])) {
                continue;
            }
            $items[$id] = $candidate;
            if (count($items) >= $limit) {
                break;
            }
        }
    }

    return array_slice($items, 0, $limit, true);
}

/* ==================== CATEGORIES ==================== */

function getCategoryTree($onlyActive = true) {
    $db = getDB();
    $statusSql = $onlyActive ? "WHERE status = 'active'" : '';
    $rows = $db->query("SELECT * FROM categories $statusSql ORDER BY sort_order ASC, name ASC")->fetchAll();
    $mains = [];
    $subs = [];
    foreach ($rows as $r) {
        if ($r['parent_id'] === null) {
            $r['children'] = [];
            $mains[$r['id']] = $r;
        } else {
            $subs[] = $r;
        }
    }
    foreach ($subs as $s) {
        if (isset($mains[$s['parent_id']])) {
            $mains[$s['parent_id']]['children'][] = $s;
        }
    }
    return array_values($mains);
}

function getAllCategoriesFlat($onlyActive = false) {
    $db = getDB();
    $statusSql = $onlyActive ? "WHERE status = 'active'" : '';
    return $db->query("SELECT * FROM categories $statusSql ORDER BY COALESCE(parent_id, id), sort_order")->fetchAll();
}

function getCategoryBySlug($slug) {
    $stmt = getDB()->prepare('SELECT * FROM categories WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

function getCategory($id) {
    $stmt = getDB()->prepare('SELECT * FROM categories WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $id]);
    return $stmt->fetch() ?: null;
}

function getSubcategories($parentId) {
    $stmt = getDB()->prepare("SELECT * FROM categories WHERE parent_id = ? AND status = 'active' ORDER BY sort_order, name");
    $stmt->execute([(int) $parentId]);
    return $stmt->fetchAll();
}

function getPriceRange() {
    $row = getDB()->query('SELECT MIN(COALESCE(NULLIF(sale_price,0), regular_price)) AS min, MAX(regular_price) AS max FROM products WHERE status = "active"')->fetch();
    return [
        'min' => (float) ($row['min'] ?? 0),
        'max' => (float) ($row['max'] ?? 0),
    ];
}

/* ==================== BRANDS ==================== */

function getBrands($onlyActive = true) {
    $sql = 'SELECT * FROM brands';
    if ($onlyActive) {
        $sql .= " WHERE status = 'active'";
    }
    $sql .= ' ORDER BY name';
    return getDB()->query($sql)->fetchAll();
}

function getBrand($id) {
    $stmt = getDB()->prepare('SELECT * FROM brands WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $id]);
    return $stmt->fetch() ?: null;
}

/* ==================== VARIANTS ==================== */

function getProductVariants($productId) {
    $stmt = getDB()->prepare('SELECT * FROM product_variants WHERE product_id = ? ORDER BY variant_name, id');
    $stmt->execute([(int) $productId]);
    return $stmt->fetchAll();
}

function getVariant($id) {
    $stmt = getDB()->prepare('SELECT * FROM product_variants WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $id]);
    return $stmt->fetch() ?: null;
}

function getProductImages($productId) {
    $stmt = getDB()->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC');
    $stmt->execute([(int) $productId]);
    return $stmt->fetchAll();
}

/* ==================== REVIEWS ==================== */

function getProductReviews($productId, $status = 'approved') {
    $stmt = getDB()->prepare(
        'SELECT r.*, u.name AS user_name FROM reviews r
         JOIN users u ON u.id = r.user_id
         WHERE r.product_id = ? AND r.status = ?
         ORDER BY r.created_at DESC'
    );
    $stmt->execute([(int) $productId, $status]);
    return $stmt->fetchAll();
}

function getReviewSummary($productId) {
    $stmt = getDB()->prepare(
        'SELECT COUNT(*) AS cnt, COALESCE(AVG(rating),0) AS avg FROM reviews WHERE product_id = ? AND status = "approved"'
    );
    $stmt->execute([(int) $productId]);
    $row = $stmt->fetch();
    return ['count' => (int) $row['cnt'], 'average' => round((float) $row['avg'], 1)];
}

/**
 * May this customer write a review for this product?
 *
 * A rejected review does not count as "already reviewed" — the admin turned
 * that one down, so the customer has to be able to write another. Only a
 * pending or approved review blocks a second submission.
 */
function userCanReview($productId, $userId) {
    $stmt = getDB()->prepare(
        "SELECT id FROM reviews
          WHERE product_id = ? AND user_id = ? AND status <> 'rejected'
          LIMIT 1"
    );
    $stmt->execute([(int) $productId, (int) $userId]);
    return !$stmt->fetch();
}

/** The customer's own rejected review for this product, if there is one. */
function rejectedReviewFor($productId, $userId) {
    $stmt = getDB()->prepare(
        "SELECT * FROM reviews
          WHERE product_id = ? AND user_id = ? AND status = 'rejected'
          ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([(int) $productId, (int) $userId]);
    return $stmt->fetch() ?: null;
}

function addReview($productId, $userId, $rating, $title, $comment) {
    $rating = max(1, min(5, (int) $rating));

    // Reuse the rejected row rather than stacking a second one per customer,
    // so the "one review per customer per product" rule still holds.
    $existing = rejectedReviewFor($productId, $userId);
    if ($existing) {
        getDB()->prepare(
            "UPDATE reviews SET rating = ?, title = ?, comment = ?, status = 'pending', created_at = NOW()
              WHERE id = ?"
        )->execute([$rating, $title ?: null, $comment ?: null, (int) $existing['id']]);
        return (int) $existing['id'];
    }

    $stmt = getDB()->prepare(
        'INSERT INTO reviews (product_id, user_id, rating, title, comment, status) VALUES (?, ?, ?, ?, ?, "pending")'
    );
    $stmt->execute([(int) $productId, (int) $userId, $rating, $title ?: null, $comment ?: null]);
    return (int) getDB()->lastInsertId();
}

/* ==================== RENDER HELPERS (kept from theme) ==================== */

if (!function_exists('renderStars')) {
    function renderStars($rating) {
        $html = '<ul class="rating product__rating d-flex justify-content-center">';
        for ($i = 1; $i <= 5; $i++) {
            $color = ($i <= round($rating)) ? 'currentColor' : '#ccc';
            $html .= '<li class="rating__list">
                <span class="rating__list--icon">
                    <svg class="rating__list--icon__svg" xmlns="http://www.w3.org/2000/svg" width="14.105" height="14.732" viewBox="0 0 10.105 9.732">
                    <path data-name="star - Copy" d="M9.837,3.5,6.73,3.039,5.338.179a.335.335,0,0,0-.571,0L3.375,3.039.268,3.5a.3.3,0,0,0-.178.514L2.347,6.242,1.813,9.4a.314.314,0,0,0,.464.316L5.052,8.232,7.827,9.712A.314.314,0,0,0,8.292,9.4L7.758,6.242l2.257-2.231A.3.3,0,0,0,9.837,3.5Z" transform="translate(0 -0.018)" fill="' . $color . '"></path>
                    </svg>
                </span>
            </li>';
        }
        $html .= '</ul>';
        return $html;
    }
}
