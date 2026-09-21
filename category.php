<?php
require_once 'includes/config.php';
require_once 'includes/shop-listing.php';
require_once 'includes/cart-functions.php';
require_once 'includes/auth.php';

$slug = $_GET['slug'] ?? '';
$category = $slug !== '' ? getCategoryBySlug($slug) : null;
if (!$category || $category['status'] !== 'active') {
    header('Location: products.php');
    exit;
}

$isMain        = $category['parent_id'] === null;
$subcategories = $isMain ? getSubcategories($category['id']) : [];
$forced        = $isMain ? ['main_category_id' => $category['id']] : ['category_id' => $category['id']];
$listing       = shop_listing($_GET, $forced);
$brands        = getBrands();
$priceRange    = getPriceRange();
$parent        = $isMain ? null : getCategory($category['parent_id']);

$pageMetaTitle = SITE_NAME . ' - ' . $category['name'];
$pageMetaDesc  = $category['description'] ?: ('Shop ' . $category['name'] . ' at ' . SITE_NAME);
$pageTitle     = $category['name'];
$breadcrumbs   = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'Shop', 'url' => 'products.php']];
if ($parent) {
    $breadcrumbs[] = ['name' => $parent['name'], 'url' => 'category.php?slug=' . $parent['slug']];
}
$breadcrumbs[] = ['name' => $category['name'], 'url' => ''];
$pageStyles    = ['assets/css/shop.css'];
include 'includes/header.php';
?>
<main class="main__content_wrapper">
    <section class="shop__section">
        <div class="container">
            <?php include 'includes/shop-crumbs.php'; ?>

            <div class="shop__head">
                <div class="shop__head--main">
                    <h1 class="shop__head--title"><?php echo e($category['name']); ?></h1>
                    <p class="shop__head--sub">Browse our <?php echo e(strtolower($category['name'])); ?> collection</p>
                </div>
            </div>

            <?php if ($isMain && $subcategories): ?>
                <div class="category__chips">
                    <?php foreach ($subcategories as $sub): ?>
                        <a href="category.php?slug=<?php echo e($sub['slug']); ?>"><?php echo e($sub['name']); ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($category['description']): ?>
                <p class="category__intro"><?php echo nl2br(e($category['description'])); ?></p>
            <?php endif; ?>

            <div class="shop__layout" data-shop-listing <?php echo $isMain ? 'data-force-main-category="' . (int) $category['id'] . '"' : 'data-force-category="' . (int) $category['id'] . '"'; ?>>
                <div class="shop__filters--backdrop"></div>
                <aside class="shop__filters">
                    <form data-shop-filters class="filter__form">
                        <?php
                        $filterCategoryTree = null; // locked to this category
                        $filterBrands = $brands;
                        $filterPriceRange = $priceRange;
                        $filterQuery = $_GET;
                        $filterSearchLabel = 'Search in ' . $category['name'];
                        include 'includes/shop-filters.php';
                        ?>
                    </form>
                </aside>

                <div class="shop__main">
                    <?php include 'includes/shop-toolbar.php'; ?>
                    <div class="row row-cols-xl-4 row-cols-lg-3 row-cols-md-2 row-cols-1 mb--n30" data-shop-grid>
                        <?php render_product_grid($listing['items']); ?>
                    </div>
                    <div class="mt-4" data-shop-pagination>
                        <?php render_pagination($listing['pagination']); ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/shop-trustbar.php'; ?>
</main>
<?php include 'includes/footer.php'; ?>
