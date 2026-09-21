<?php
require_once 'includes/config.php';
$pageMetaTitle = SITE_NAME . ' - Home';
$pageMetaDesc = SITE_NAME . ' - ' . SITE_TAGLINE;
require_once 'includes/products.php';
require_once 'includes/cart-functions.php';
require_once 'includes/auth.php';
require_once 'includes/homepage.php';
require_once 'includes/shop-listing.php';
require_once 'includes/engagement.php';

$heroSliders = getSliders();
/* One ordered list instead of the old feature/wide/tile mosaic — the rail below
   renders every active banner as an equal card, whatever slot it was filed under. */
$collections = getBanners();

/* home_only respects the admin's "show on homepage" flag on each product */
$featuredProducts = getProducts(['featured' => 1, 'home_only' => 1, 'limit' => 8])['items'];
$latestProducts   = getProducts(['sort' => 'latest', 'home_only' => 1, 'limit' => 8])['items'];
$bestSellers      = getProducts(['sort' => 'best_selling', 'home_only' => 1, 'limit' => 8])['items'];
$offerProducts    = getProducts(['on_sale' => 1, 'home_only' => 1, 'limit' => 8])['items'];

$homeCategories = array_values(array_filter(getCategoryTree(), fn($c) => empty($c['parent_id'])));
$catCounts      = shop_category_counts();
$homeSchemes    = array_slice(getSchemes(['status' => 'active']), 0, 3);
$lookbook       = array_slice($latestProducts ?: $featuredProducts, 0, 5);
$totalDesigns   = getProducts(['limit' => 1])['total'];

/* Testimonials come from real reviewed feedback; the block hides itself when empty. */
$homeQuotes = array_slice(array_filter(getFeedback('reviewed'), fn($f) => (int) $f['rating'] >= 4), 0, 3);

$pageStyles = ['assets/css/home.css'];
include 'includes/header.php';

/**
 * Art for a main category card.
 *
 * An image set in admin (Categories -> Image) always wins; otherwise fall back
 * to the bundled photo for that metal, and to a tonal card if there is none.
 * @return array{image:?string, tone:string}
 */
function home_cat_art($cat) {
    $slug = $cat['slug'];

    // is_file guards against a row pointing at an upload that is no longer on
    // disk — without it the card renders a broken image instead of the fallback.
    if (!empty($cat['image']) && is_file($cat['image'])) {
        return ['image' => $cat['image'], 'tone' => ''];
    }

    $photos = [
        'gold'    => 'assets/img/banner/haram.jpg',
        'silver'  => 'assets/img/banner/silver.png',
        'diamond' => 'assets/img/banner/diamond.png',
    ];
    if (isset($photos[$slug]) && is_file($photos[$slug])) {
        return ['image' => $photos[$slug], 'tone' => ''];
    }

    $tones = ['silver' => 'is-silver', 'diamond' => 'is-diamond'];
    return ['image' => null, 'tone' => $tones[$slug] ?? 'is-gold'];
}

/** One tab panel of product cards. */
function home_tab_panel($id, $items, $active = false) {
    if (empty($items)) return;
    echo '<div class="home__panel" id="' . e($id) . '" role="tabpanel"' . ($active ? '' : ' hidden') . '>';
    echo '<div class="row row-cols-xl-4 row-cols-lg-4 row-cols-md-3 row-cols-2 mb--n30">';
    foreach ($items as $product) {
        include __DIR__ . '/includes/product-card.php';
    }
    echo '</div></div>';
}

$tabs = array_filter([
    'featured' => ['Featured',      $featuredProducts],
    'new'      => ['New Arrivals',  $latestProducts],
    'best'     => ['Best Sellers',  $bestSellers],
    'offers'   => ['Special Offers', $offerProducts],
], fn($t) => !empty($t[1]));
?>

<main class="main__content_wrapper">

    <!-- Hero -->
    <?php if ($heroSliders): ?>
    <section class="hero__slider--section color-scheme-2" id="hero">
        <div class="hero__slider--inner hero__slider--activation swiper">
            <div class="hero__slider--wrapper swiper-wrapper">
                <?php foreach ($heroSliders as $i => $slide): ?>
                    <?php /* data-slide-id lets ?slide=<id> open on a specific slide */ ?>
                    <div class="swiper-slide" data-slide-id="<?php echo (int) $slide['id']; ?>">
                        <div class="hero__slider--items home2__slider--bg<?php echo $i % 2 ? ' two' : ''; ?>"<?php echo $slide['image'] ? ' style="background-image:url(\'' . e($slide['image']) . '\')"' : ''; ?>>
                            <div class="container-fluid">
                                <div class="hero__slider--items__inner hero__slider--bg2__inner">
                                    <div class="row row-cols-1">
                                        <div class="col">
                                            <div class="slider__content">
                                                <?php if ($slide['subtitle'] !== ''): ?><p class="slider__content--desc desc1 text__secondary2 mb-15"><?php echo e($slide['subtitle']); ?></p><?php endif; ?>
                                                <h2 class="slider__content--maintitle h1"<?php echo $slide['title_color'] ? ' style="color:' . e($slide['title_color']) . '"' : ''; ?>><?php echo multiline_html($slide['title']); ?></h2>
                                                <?php if ($slide['description'] !== ''): ?><p class="slider__content--desc desc2 d-sm-2-none mb-40"><?php echo multiline_html($slide['description']); ?></p><?php endif; ?>
                                                <?php if ($slide['button_label'] !== ''): ?>
                                                    <a class="bg__secondary2 slider__btn primary__btn" href="<?php echo e($slide['button_url'] !== '' ? $slide['button_url'] : 'products.php'); ?>"><?php echo e($slide['button_label']); ?>
                                                        <svg class="primary__btn--arrow__icon" xmlns="http://www.w3.org/2000/svg" width="20.2" height="12.2" viewBox="0 0 6.2 6.2"><path d="M7.1,4l-.546.546L8.716,6.713H4v.775H8.716L6.554,9.654,7.1,10.2,9.233,8.067,10.2,7.1Z" transform="translate(-4 -4)" fill="currentColor"></path></svg>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="swiper__nav--btn swiper-button-next"></div>
            <div class="swiper__nav--btn swiper-button-prev"></div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Trust ticker -->
    <div class="home__ticker" aria-hidden="true">
        <div class="home__ticker--track">
            <?php
            $ticker = ['BIS Hallmarked Gold', 'Certified Diamonds', 'Lifetime Exchange', 'Free Insured Shipping', 'Custom Bridal Design', 'Transparent Making Charges'];
            // printed twice so the -50% keyframe loops seamlessly
            foreach ([1, 2] as $pass) {
                foreach ($ticker as $t) { echo '<span>' . e($t) . '</span>'; }
            }
            ?>
        </div>
    </div>

    <?php renderAdBanners('after_hero'); ?>

    <!-- Shop by category -->
    <?php if ($homeCategories): ?>
    <section class="home__section">
        <div class="container">
            <div class="section__heading text-center mb-35">
                <h2 class="section__heading--maintitle style2">Shop by Category</h2>
            </div>
            <p class="home__lead mb-35">Explore our collections in gold, silver and diamond &mdash; each piece hallmarked and crafted to last.</p>
            <div class="home__cats">
                <?php foreach ($homeCategories as $cat): ?>
                    <?php $count = (int) ($catCounts[(int) $cat['id']] ?? 0); $art = home_cat_art($cat); ?>
                    <a class="home__cat <?php echo e($art['tone']); ?>" href="category.php?slug=<?php echo e($cat['slug']); ?>">
                        <?php if ($art['image']): ?>
                            <img src="<?php echo e($art['image']); ?>" alt="<?php echo e($cat['name']); ?>" loading="lazy">
                        <?php else: ?>
                            <span class="home__cat--gem" aria-hidden="true">
                                <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"><path d="M20 8h24l12 16-24 32L8 24z"/><path d="M8 24h48M20 8l4 16 8 32 8-32 4-16M24 24h16"/></svg>
                            </span>
                        <?php endif; ?>
                        <div class="home__cat--body">
                            <span class="home__cat--count"><?php echo $count > 0 ? $count . ' design' . ($count === 1 ? '' : 's') : 'Coming soon'; ?></span>
                            <h3 class="home__cat--title"><?php echo e($cat['name']); ?></h3>
                            <span class="home__cat--link">Explore
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Collections rail (admin-managed banners) -->
    <?php /* This whole section was wrapped in an HTML comment, so every banner
             added under Storefront > Category Banners was saved, queried and
             rendered — straight into a comment node, invisible on the page. */ ?>
    <?php if ($collections): ?>
    <section class="home__section home__section--tight">
        <div class="container">
            <div class="section__heading text-center mb-35">
                <h2 class="section__heading--maintitle style2">Our Collections</h2>
            </div>
            <p class="home__lead mb-35">Curated edits for weddings, festivals and everyday wear.</p>

            <div class="home__rail">
                <?php foreach ($collections as $ci => $b): ?>
                    <?php $href = $b['link_url'] !== '' ? $b['link_url'] : 'products.php'; ?>
                    <?php /* stable id so admin's "View on site" can deep-link this one */ ?>
                    <a class="home__coll" id="banner-<?php echo (int) $b['id']; ?>" href="<?php echo e($href); ?>">
                        <img src="<?php echo e(homepage_image_src($b['image'], 'assets/img/banner/newcollection.jpg')); ?>" alt="<?php echo e($b['title']); ?>" loading="lazy">
                        <span class="home__coll--num"><?php echo str_pad((string) ($ci + 1), 2, '0', STR_PAD_LEFT); ?></span>
                        <div class="home__coll--body">
                            <h3 class="home__coll--title"><?php echo multiline_html($b['title']); ?></h3>
                            <span class="home__coll--link">
                                <?php echo e($b['link_label'] !== '' ? $b['link_label'] : 'Shop now'); ?>
                                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php renderAdBanners('after_banners'); ?>

    <!-- Tabbed product showcase: one section instead of four stacked grids -->
    <?php if ($tabs): ?>
    <section class="home__section" id="homeShowcase">
        <div class="container-fluid">
            <div class="section__heading text-center mb-35">
                <h2 class="section__heading--maintitle style2">Our Collection</h2>
            </div>
            <div class="home__tabs" role="tablist">
                <?php $first = true; foreach ($tabs as $key => $tab): ?>
                    <button type="button" class="home__tab<?php echo $first ? ' is-active' : ''; ?>" role="tab"
                            aria-selected="<?php echo $first ? 'true' : 'false'; ?>"
                            aria-controls="panel-<?php echo e($key); ?>" data-home-tab="panel-<?php echo e($key); ?>">
                        <?php echo e($tab[0]); ?>
                    </button>
                <?php $first = false; endforeach; ?>
            </div>
            <?php $first = true; foreach ($tabs as $key => $tab): ?>
                <?php home_tab_panel('panel-' . $key, $tab[1], $first); $first = false; ?>
            <?php endforeach; ?>
            <div class="home__more"><a class="btn btn-outline-primary" href="products.php">View All Jewellery</a></div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Brand story -->
    <section class="home__section home__section--ivory">
        <div class="container">
            <div class="home__story">
                <div class="home__story--media">
                    <img src="assets/img/other/aboutus.jpg" alt="<?php echo e(SITE_NAME); ?> showroom" loading="lazy">
                    <div class="home__story--badge"><strong>25+</strong><span>Years</span></div>
                </div>
                <div>
                    <span class="home__story--eyebrow">Our Story</span>
                    <h2 class="home__story--title">Tradition, shaped by hand.<br>Worn for a lifetime.</h2>
                    <p>We celebrate the beauty of tradition blended with modern elegance. Every piece reflects fine artistry, precision and timeless appeal &mdash; from exquisite gold ornaments to intricately crafted bridal collections.</p>
                    <div class="home__stats">
                        <div class="home__stat"><strong><?php echo (int) $totalDesigns; ?>+</strong><span>Designs</span></div>
                        <div class="home__stat"><strong>100%</strong><span>Hallmarked</span></div>
                        <div class="home__stat"><strong>24K</strong><span>Pure Gold</span></div>
                    </div>
                    <a class="btn btn-primary" href="about.php">Read Our Story</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Savings schemes -->
    <?php if ($homeSchemes): ?>
    <section class="home__section">
        <div class="container">
            <div class="section__heading text-center mb-35">
                <h2 class="section__heading--maintitle style2">Savings Schemes</h2>
            </div>
            <p class="home__lead mb-35">Save a little each month and own the piece you have been planning for.</p>
            <div class="home__schemes">
                <?php foreach ($homeSchemes as $s): ?>
                    <div class="home__scheme">
                        <?php if ($s['category_name']): ?><span class="home__scheme--cat"><?php echo e($s['category_name']); ?></span><?php endif; ?>
                        <h3 class="home__scheme--title"><a href="scheme-details.php?slug=<?php echo e($s['slug']); ?>"><?php echo e($s['name']); ?></a></h3>
                        <p class="home__scheme--desc"><?php echo e($s['short_description']); ?></p>
                        <div class="home__scheme--meta">
                            <?php if ($s['duration_months']): ?>
                                <div><strong><?php echo (int) $s['duration_months']; ?></strong><span>Months</span></div>
                            <?php endif; ?>
                            <?php if ($s['monthly_amount']): ?>
                                <div><strong><?php echo formatPrice($s['monthly_amount'], 0); ?></strong><span>Per month</span></div>
                            <?php endif; ?>
                        </div>
                        <a class="btn btn-outline-primary" href="scheme-details.php?slug=<?php echo e($s['slug']); ?>">View Details</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php renderAdBanners('mid_products'); ?>

    <!-- Lookbook -->
    <?php if (count($lookbook) >= 3): ?>
    <section class="home__section home__section--tight">
        <div class="container">
            <div class="section__heading text-center mb-35">
                <h2 class="section__heading--maintitle style2">The Lookbook</h2>
            </div>
            <div class="home__look">
                <?php foreach ($lookbook as $p): ?>
                    <a href="product-details.php?id=<?php echo (int) $p['id']; ?>">
                        <img src="<?php echo e($p['image']); ?>" alt="<?php echo e($p['name']); ?>" loading="lazy">
                        <span class="home__look--tag"><?php echo e($p['name']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Testimonials (real feedback only) -->
    <?php if ($homeQuotes): ?>
    <section class="home__section home__section--ivory">
        <div class="container">
            <div class="section__heading text-center mb-35">
                <h2 class="section__heading--maintitle style2">What Our Customers Say</h2>
            </div>
            <div class="home__quotes">
                <?php foreach ($homeQuotes as $q): ?>
                    <div class="home__quote">
                        <div class="home__quote--stars"><?php echo str_repeat('&#9733;', max(1, min(5, (int) $q['rating']))); ?></div>
                        <p class="home__quote--text">&ldquo;<?php echo e($q['message']); ?>&rdquo;</p>
                        <span class="home__quote--who"><?php echo e($q['name']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Closing CTA -->
    <section class="home__section home__section--tight">
        <div class="container">
            <div class="home__cta" style="--cta-image:url('<?php echo e(asset_url('assets/img/banner/traditional.jpg')); ?>')">
                <h2 class="home__cta--title">Visit us in store</h2>
                <p>Book an appointment and our team will have your shortlist ready to try on &mdash; no queue, no rush.</p>
                <div class="home__cta--actions">
                    <a class="btn btn-primary" href="appointment.php">Book an Appointment</a>
                    <a class="btn home__cta--ghost" href="contact.php">Contact Us</a>
                </div>
            </div>
        </div>
    </section>

    <?php renderAdBanners('before_footer'); ?>
</main>

<script>
/* Product showcase tabs */
(function () {
    var tabs = document.querySelectorAll('[data-home-tab]');
    if (!tabs.length) return;
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) {
                t.classList.remove('is-active');
                t.setAttribute('aria-selected', 'false');
                var p = document.getElementById(t.getAttribute('data-home-tab'));
                if (p) p.hidden = true;
            });
            tab.classList.add('is-active');
            tab.setAttribute('aria-selected', 'true');
            var panel = document.getElementById(tab.getAttribute('data-home-tab'));
            if (panel) panel.hidden = false;
        });
    });
})();

/* Offer-banner countdown: updates [data-ad-expires] targets and hides expired banners. */
(function () {
    var nodes = document.querySelectorAll('[data-ad-expires]');
    if (!nodes.length) return;
    function tick() {
        var now = Date.now();
        nodes.forEach(function (node) {
            var end = new Date(node.getAttribute('data-ad-expires')).getTime();
            var target = node.querySelector('.ad-countdown');
            var diff = end - now;
            if (isNaN(end)) return;
            if (diff <= 0) { node.style.display = 'none'; return; }
            if (!target) return;
            var d = Math.floor(diff / 864e5),
                h = Math.floor(diff % 864e5 / 36e5),
                m = Math.floor(diff % 36e5 / 6e4),
                s = Math.floor(diff % 6e4 / 1e3);
            target.innerHTML =
                '<span>' + d + '<i>d</i></span><span>' + h + '<i>h</i></span>' +
                '<span>' + m + '<i>m</i></span><span>' + s + '<i>s</i></span>';
        });
    }
    tick();
    setInterval(tick, 1000);
})();
</script>

<?php include 'includes/footer.php'; ?>
