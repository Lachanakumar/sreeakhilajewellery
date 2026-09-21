<?php
require_once 'includes/config.php';
require_once 'includes/shop-listing.php';
require_once 'includes/cart-functions.php';
require_once 'includes/auth.php';

$listing      = shop_listing($_GET);
$categoryTree = getCategoryTree();
$brands       = getBrands();
$priceRange   = getPriceRange();

$pageMetaTitle = SITE_NAME . ' - Shop';
$pageMetaDesc  = 'Browse our collection of premium gold, silver, and diamond jewelry at ' . SITE_NAME;
$pageTitle     = 'Shop';
$breadcrumbs   = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'Products', 'url' => '']];
$pageStyles    = ['assets/css/shop.css'];
include 'includes/header.php';
?>
<main class="main__content_wrapper">
    <section class="shop__section">
        <div class="container">

            <?php include 'includes/shop-crumbs.php'; ?>

            <div class="shop__head">
                <div class="shop__head--main">
                    <h1 class="shop__head--title">Products</h1>
                    <p class="shop__head--sub">Discover our exquisite collection of jewellery</p>
                </div>
                <figure class="shop__head--quote">
                    <blockquote>&ldquo;Timeless Elegance<br>For Every Occasion&rdquo;</blockquote>
                    <span class="shop__head--quote__rule"></span>
                </figure>
            </div>

            <?php include 'includes/shop-promo.php'; ?>

            <div class="shop__layout" data-shop-listing>
                <div class="shop__filters--backdrop"></div>
                <aside class="shop__filters">
                    <form data-shop-filters class="filter__form">
                        <?php
                        $filterCategoryTree = $categoryTree;
                        $filterBrands = $brands;
                        $filterPriceRange = $priceRange;
                        $filterQuery = $_GET;
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
