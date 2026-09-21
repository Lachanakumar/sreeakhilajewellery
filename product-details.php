<?php
require_once 'includes/config.php';
require_once 'includes/products.php';
require_once 'includes/cart-functions.php';
require_once 'includes/auth.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$product = getProduct($id);
if (!$product || $product['status'] !== 'active') {
    header('Location: products.php');
    exit;
}
incrementProductViews($id);

$gallery   = $product['gallery'];
$variants  = getProductVariants($id);
$variantGroups = [];
foreach ($variants as $v) {
    $variantGroups[$v['variant_name']][] = $v;
}
$reviews       = getProductReviews($id);
$reviewSummary = getReviewSummary($id);

// 5..1 star counts for the ratings breakdown bars
$reviewDist = array_fill_keys([5, 4, 3, 2, 1], 0);
foreach ($reviews as $rv) {
    $star = max(1, min(5, (int) $rv['rating']));
    $reviewDist[$star]++;
}
$relatedProducts = getRelatedProducts($product['category'], $product['id'], 8);
$csrf = csrf_token();

$pageMetaTitle = SITE_NAME . ' - ' . $product['name'];
$pageMetaDesc  = $product['description'];
$pageTitle     = $product['name'];
$pageHeading   = false;  // the info column already carries the <h1>
$pageStyles    = ['assets/css/detail.css'];

// price context
$hasSale  = !empty($product['old_price']) && (float) $product['old_price'] > (float) $product['price'];
$saveAmt  = $hasSale ? (float) $product['old_price'] - (float) $product['price'] : 0;
$savePct  = $hasSale ? round(($saveAmt / (float) $product['old_price']) * 100) : 0;
$stockQty = (int) $product['stock_quantity'];
$lowStock = $product['in_stock'] && $stockQty <= 3;

$breadcrumbs   = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'Shop', 'url' => 'products.php']];
if ($product['main_category']) {
    $breadcrumbs[] = ['name' => $product['main_category']['name'], 'url' => 'category.php?slug=' . $product['main_category']['slug']];
}
$breadcrumbs[] = ['name' => $product['name'], 'url' => ''];
include 'includes/header.php';
?>
<main class="main__content_wrapper">
    <?php include 'includes/page-title.php'; ?>
    <section class="pdx">
        <div class="container">
            <div class="pdx__grid">

                <!-- gallery -->
                <div class="pdx__media">
                    <div class="pdx__stage swiper" id="pdPreview">
                        <div class="pdx__badges">
                            <?php if ($hasSale): ?><span class="pdx__badge pdx__badge--sale"><?php echo $savePct; ?>% off</span><?php endif; ?>
                            <?php if (!$product['in_stock']): ?><span class="pdx__badge pdx__badge--out">Sold out</span><?php endif; ?>
                        </div>
                        <div class="swiper-wrapper">
                            <?php foreach ($gallery as $img): ?>
                                <div class="swiper-slide">
                                    <a class="glightbox" data-gallery="product-media-preview" href="<?php echo e($img); ?>">
                                        <img src="<?php echo e($img); ?>" alt="<?php echo e($product['name']); ?>">
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <a class="pdx__zoom glightbox" href="<?php echo e($gallery[0] ?? ''); ?>" data-gallery="product-media-preview" aria-label="View larger">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5M11 8v6M8 11h6"/></svg>
                        </a>
                        <div class="swiper__nav--btn swiper-button-next"></div>
                        <div class="swiper__nav--btn swiper-button-prev"></div>
                    </div>
                    <?php if (count($gallery) > 1): ?>
                        <div class="pdx__thumbs swiper" id="pdThumbs">
                            <div class="swiper-wrapper">
                                <?php foreach ($gallery as $img): ?>
                                    <div class="swiper-slide">
                                        <div class="pdx__thumb"><img src="<?php echo e($img); ?>" alt="thumbnail"></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- info -->
                <div class="pdx__info">
                    <form action="cart-actions.php" method="post" class="js-cart-form" id="pdCartForm" data-validate>
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <input type="hidden" name="action" value="add_to_cart">
                        <input type="hidden" name="redirect" value="cart.php">
                        <input type="hidden" name="variant_id" value="">

                        <?php if ($product['category']): ?>
                            <span class="pdx__eyebrow"><?php echo e($product['category']); ?></span>
                        <?php endif; ?>
                        <h1 class="pdx__title"><?php echo e($product['name']); ?></h1>

                        <div class="pdx__ratingline">
                            <?php echo renderStars($reviewSummary['average']); ?>
                            <?php if ($reviewSummary['count'] > 0): ?>
                                <a href="#reviews" data-jump-reviews><?php echo (int) $reviewSummary['count']; ?> review<?php echo $reviewSummary['count'] === 1 ? '' : 's'; ?></a>
                            <?php else: ?>
                                <a href="#reviews" data-jump-reviews>Be the first to review</a>
                            <?php endif; ?>
                        </div>

                        <div class="pdx__pricebox">
                            <span class="pdx__price" id="pdPrice"><?php echo formatPrice($product['price']); ?></span>
                            <?php if ($hasSale): ?>
                                <span class="pdx__price--was"><?php echo formatPrice($product['old_price']); ?></span>
                                <span class="pdx__save">Save <?php echo formatPrice($saveAmt, 0); ?></span>
                            <?php endif; ?>
                            <p class="pdx__taxnote">Inclusive of all taxes. Making charges may vary by design.</p>
                        </div>

                        <?php if (!empty($product['description'])): ?>
                            <p class="pdx__desc"><?php echo e($product['description']); ?></p>
                        <?php endif; ?>

                        <dl class="pdx__specs">
                            <div class="pdx__spec"><dt>SKU</dt><dd><?php echo e($product['sku']); ?></dd></div>
                            <?php if ($product['purity']): ?><div class="pdx__spec"><dt>Purity</dt><dd><?php echo e($product['purity']); ?></dd></div><?php endif; ?>
                            <?php if ($product['weight']): ?><div class="pdx__spec"><dt>Weight</dt><dd><?php echo e($product['weight']); ?></dd></div><?php endif; ?>
                        </dl>

                        <div class="pdx__stock <?php echo !$product['in_stock'] ? 'out' : ($lowStock ? 'low' : 'in'); ?>">
                            <?php if (!$product['in_stock']): ?>
                                Currently out of stock
                            <?php elseif ($lowStock): ?>
                                Only <?php echo $stockQty; ?> left &mdash; order soon
                            <?php else: ?>
                                In stock &middot; ready to ship
                            <?php endif; ?>
                        </div>

                        <?php if ($variantGroups): ?>
                            <?php foreach ($variantGroups as $groupName => $opts): ?>
                                <div class="pdx__variant" data-variant-group>
                                    <span class="pdx__variant--label"><?php echo e($groupName); ?></span>
                                    <div class="pdx__opts">
                                        <?php foreach ($opts as $opt): ?>
                                            <label class="pdx__opt pd__variant--option<?php echo (int) $opt['stock_quantity'] <= 0 ? ' is-disabled' : ''; ?>">
                                                <input type="radio" name="variant_option" value="<?php echo (int) $opt['id']; ?>"
                                                       data-price-adjust="<?php echo (float) $opt['price_adjustment']; ?>"
                                                       data-stock="<?php echo (int) $opt['stock_quantity']; ?>"
                                                       <?php echo (int) $opt['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>
                                                <?php echo e($opt['variant_value']); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <div class="pdx__buy" data-error-anchor>
                            <div class="quantity__box">
                                <button type="button" class="quantity__value quickview__value--quantity decrease" aria-label="Decrease quantity">-</button>
                                <label><input type="number" name="qty" class="quantity__number quickview__value--number" value="1" data-counter min="1"
                                              max="<?php echo max(1, $stockQty); ?>"
                                              data-label="Quantity" data-stock-input
                                              data-max-message="Only <?php echo $stockQty; ?> in stock." /></label>
                                <button type="button" class="quantity__value quickview__value--quantity increase" aria-label="Increase quantity">+</button>
                            </div>
                            <button class="quickview__cart--btn primary__btn" type="submit" data-add-cart<?php echo $product['in_stock'] ? '' : ' disabled'; ?>>Add To Cart</button>
                            <button class="pdx__buynow pd__buynow--btn" type="submit" name="buy_now" value="1" data-buy-now<?php echo $product['in_stock'] ? '' : ' disabled'; ?>>
                                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h8l-1 8 10-12h-8z"/></svg>
                                Buy Now
                            </button>
                        </div>
                        <p class="pdx__stockmsg pd__stock--msg" data-stock-msg hidden></p>

                        <div class="pdx__actions">
                            <a class="pdx__action js-wishlist-toggle<?php echo isInWishlist($product['id']) ? ' is-active' : ''; ?>" data-product-id="<?php echo $product['id']; ?>" href="cart-actions.php?action=add_to_wishlist&product_id=<?php echo $product['id']; ?>&redirect=product-details.php%3Fid%3D<?php echo $product['id']; ?>&token=<?php echo e($csrf); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 512 512"><path d="M352.92 80C288 80 256 144 256 144s-32-64-96.92-64c-52.76 0-94.54 44.14-95.08 96.81-1.1 109.33 86.73 187.08 183 252.42a16 16 0 0018 0c96.26-65.34 184.09-143.09 183-252.42-.54-52.67-42.32-96.81-95.08-96.81z" fill="<?php echo isInWishlist($product['id']) ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" /></svg>
                                <span><?php echo isInWishlist($product['id']) ? 'In Wishlist' : 'Add to Wishlist'; ?></span>
                            </a>
                            <a class="pdx__action" href="cart-actions.php?action=add_to_compare&product_id=<?php echo $product['id']; ?>&redirect=product-details.php%3Fid%3D<?php echo $product['id']; ?>&token=<?php echo e($csrf); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 512 512"><path d="M255.66 112c-77.94 0-157.89 45.11-220.83 135.33a16 16 0 00-.27 17.77C82.92 340.8 161.8 400 255.66 400c92.84 0 173.34-59.38 221.79-135.25a16.14 16.14 0 000-17.47C428.89 172.28 347.8 112 255.66 112z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" /><circle cx="256" cy="256" r="80" fill="none" stroke="currentColor" stroke-miterlimit="10" stroke-width="32" /></svg>
                                <?php echo isInCompare($product['id']) ? 'In Compare' : 'Add to Compare'; ?>
                            </a>
                            <button type="button" class="pdx__action" data-enquiry-toggle>
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.5 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7A8.4 8.4 0 0 1 4 11.5 8.4 8.4 0 0 1 12.5 3 8.4 8.4 0 0 1 21 11.5z"/></svg>
                                Ask about this piece
                            </button>
                        </div>

                        <div class="pdx__trust">
                            <div class="pdx__trust--item">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 6v6c0 5 3.4 8.9 8 10 4.6-1.1 8-5 8-10V6z"/><path d="m9 12 2 2 4-4"/></svg>
                                <span><strong>Hallmarked</strong>Certified purity on every piece</span>
                            </div>
                            <div class="pdx__trust--item">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 2.6-6.4"/><path d="M3 4v5h5"/></svg>
                                <span><strong>Easy exchange</strong>Visit the store with your bill</span>
                            </div>
                            <div class="pdx__trust--item">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                <span><strong>Secure checkout</strong>Protected payment options</span>
                            </div>
                        </div>
                    </form>

                    <div class="pdx__enquiry">
                        <div class="pdx__enquiry--box" hidden>
                            <div class="flash__bar is-success js-form-success" hidden style="border-radius:6px;margin-bottom:12px"></div>
                            <form class="js-ajax-form" action="ajax/enquiry.php" method="post" data-validate>
                                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                                <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                                <input type="hidden" name="subject" value="Product enquiry: <?php echo e($product['name']); ?>">
                                <div class="row">
                                    <div class="col-sm-6 mb-10"><input class="checkout__input--field w-100" name="name" placeholder="Your name" value="<?php echo e($currentUser['name'] ?? ''); ?>" required></div>
                                    <div class="col-sm-6 mb-10"><input class="checkout__input--field w-100" type="tel" name="phone" placeholder="Mobile number" value="<?php echo e($currentUser['phone'] ?? ''); ?>" required></div>
                                    <div class="col-12 mb-10"><input class="checkout__input--field w-100" type="email" name="email" placeholder="Email (optional)" value="<?php echo e($currentUser['email'] ?? ''); ?>"></div>
                                    <div class="col-12 mb-10"><textarea class="checkout__input--field w-100" name="message" rows="3" placeholder="Ask about price, availability, customisation..."></textarea></div>
                                </div>
                                <button type="submit" class="btn btn-primary">Send Enquiry</button>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- sticky buy bar (mobile) -->
    <div class="pdx__stickybar" id="pdStickyBar">
        <span class="pdx__stickybar--price"><?php echo formatPrice($product['price']); ?></span>
        <button type="submit" form="pdCartForm" class="primary__btn" data-add-cart<?php echo $product['in_stock'] ? '' : ' disabled'; ?>>Add To Cart</button>
    </div>
    <script>
    (function () {
        /* enquiry panel */
        var box = document.querySelector('.pdx__enquiry--box');
        document.querySelectorAll('[data-enquiry-toggle]').forEach(function (t) {
            t.addEventListener('click', function () {
                if (!box) { return; }
                box.hidden = !box.hidden;
                if (!box.hidden) { box.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
            });
        });

        /* rating line jumps to the reviews tab */
        document.querySelectorAll('[data-jump-reviews]').forEach(function (a) {
            a.addEventListener('click', function (ev) {
                ev.preventDefault();
                var tab = document.querySelector('[data-target="#reviews"]');
                if (tab) { tab.click(); }
                var sec = document.getElementById('pdxTabs');
                if (sec) { sec.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
            });
        });

        /* sticky mobile buy bar: show once the real button scrolls away */
        var bar = document.getElementById('pdStickyBar');
        var buy = document.querySelector('.pdx__buy');
        if (bar && buy && 'IntersectionObserver' in window) {
            new IntersectionObserver(function (entries) {
                bar.classList.toggle('is-on', !entries[0].isIntersecting && entries[0].boundingClientRect.top < 0);
            }, { threshold: 0 }).observe(buy);
        }
    })();
    </script>

    <!-- Tabs -->
    <section class="pdx__tabs" id="pdxTabs">
        <div class="container">
            <div class="row row-cols-1">
                <div class="col">
                    <ul class="pdx__tabnav product__details--tab d-flex">
                        <li class="product__details--tab__list active" data-toggle="tab" data-target="#description">Description</li>
                        <li class="product__details--tab__list" data-toggle="tab" data-target="#reviews">Reviews (<?php echo $reviewSummary['count']; ?>)</li>
                        <li class="product__details--tab__list" data-toggle="tab" data-target="#information">Additional Info</li>
                    </ul>
                    <div class="pdx__tabbody product__details--tab__inner">
                        <div class="tab_content">
                            <div id="description" class="tab_pane active show">
                                <div class="pdx__prose rte">
                                    <?php if (trim((string) $product['long_description']) !== ''): ?>
                                        <?php echo sanitize_html($product['long_description']); ?>
                                    <?php else: ?>
                                        <p><?php echo e($product['description'] ?: 'No description has been added for this piece yet.'); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div id="reviews" class="tab_pane">
                                <div class="rvw">

                                    <?php if ($reviewSummary['count'] > 0): ?>
                                        <div class="rvw__summary">
                                            <div class="rvw__score">
                                                <span class="rvw__score--num"><?php echo number_format((float) $reviewSummary['average'], 1); ?></span>
                                                <span class="rvw__score--out">out of 5</span>
                                                <div class="rvw__stars" aria-hidden="true">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <svg class="<?php echo $i <= round($reviewSummary['average']) ? 'is-on' : ''; ?>" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="m12 17.3-6.16 3.7 1.64-7.03L2 9.24l7.19-.61L12 2l2.81 6.63 7.19.61-5.48 4.73L18.16 21z"/></svg>
                                                    <?php endfor; ?>
                                                </div>
                                                <span class="rvw__score--count"><?php echo (int) $reviewSummary['count']; ?> review<?php echo $reviewSummary['count'] === 1 ? '' : 's'; ?></span>
                                            </div>

                                            <ul class="rvw__bars">
                                                <?php foreach ($reviewDist as $star => $n): ?>
                                                    <?php $pct = $reviewSummary['count'] ? round($n / $reviewSummary['count'] * 100) : 0; ?>
                                                    <li class="rvw__bar">
                                                        <span class="rvw__bar--label"><?php echo $star; ?><svg viewBox="0 0 24 24" width="11" height="11" fill="currentColor"><path d="m12 17.3-6.16 3.7 1.64-7.03L2 9.24l7.19-.61L12 2l2.81 6.63 7.19.61-5.48 4.73L18.16 21z"/></svg></span>
                                                        <span class="rvw__bar--track"><span class="rvw__bar--fill" style="width:<?php echo $pct; ?>%"></span></span>
                                                        <span class="rvw__bar--n"><?php echo $n; ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (empty($reviews)): ?>
                                        <div class="rvw__empty">
                                            <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.3"><path d="m12 17.3-6.16 3.7 1.64-7.03L2 9.24l7.19-.61L12 2l2.81 6.63 7.19.61-5.48 4.73L18.16 21z"/></svg>
                                            <p>No reviews yet</p>
                                            <span>Be the first to share how this piece looks and wears.</span>
                                        </div>
                                    <?php else: ?>
                                        <ul class="rvw__list">
                                            <?php foreach ($reviews as $rv): ?>
                                                <li class="rvw__item">
                                                    <span class="rvw__avatar" aria-hidden="true"><?php echo e(mb_strtoupper(mb_substr($rv['user_name'], 0, 1))); ?></span>
                                                    <div class="rvw__body">
                                                        <div class="rvw__head">
                                                            <strong class="rvw__who"><?php echo e($rv['user_name']); ?></strong>
                                                            <time class="rvw__when" datetime="<?php echo e(date('Y-m-d', strtotime($rv['created_at']))); ?>"><?php echo date('j M Y', strtotime($rv['created_at'])); ?></time>
                                                        </div>
                                                        <div class="rvw__stars rvw__stars--sm" aria-label="<?php echo (int) $rv['rating']; ?> out of 5">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <svg class="<?php echo $i <= (int) $rv['rating'] ? 'is-on' : ''; ?>" viewBox="0 0 24 24" width="13" height="13" fill="currentColor"><path d="m12 17.3-6.16 3.7 1.64-7.03L2 9.24l7.19-.61L12 2l2.81 6.63 7.19.61-5.48 4.73L18.16 21z"/></svg>
                                                            <?php endfor; ?>
                                                        </div>
                                                        <?php if ($rv['title']): ?><h4 class="rvw__title"><?php echo e($rv['title']); ?></h4><?php endif; ?>
                                                        <p class="rvw__text"><?php echo nl2br(e($rv['comment'])); ?></p>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>

                                    <div id="writereview" class="rvw__write">
                                        <h3 class="rvw__write--title">Write a review</h3>
                                        <?php if (!isLoggedIn()): ?>
                                            <p class="rvw__write--note"><a href="login.php?redirect=<?php echo urlencode('product-details.php?id=' . $product['id']); ?>">Log in</a> to share your experience with this piece.</p>
                                        <?php elseif (!userCanReview($product['id'], auth_user_id())): ?>
                                            <p class="rvw__write--note">You have already reviewed this product &mdash; thank you.</p>
                                        <?php else: ?>
                                            <?php if ($rejectedReview = rejectedReviewFor($product['id'], auth_user_id())): ?>
                                                <p class="rvw__write--note rvw__write--note__warn">
                                                    Your earlier review was not published. You are welcome to write another one.
                                                </p>
                                            <?php endif; ?>
                                            <form action="ajax/review.php" method="post" class="js-review-form" data-validate>
                                                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <input type="hidden" name="rating" value="0">
                                                <div class="rvw__pick">
                                                    <span class="rvw__pick--label">Your rating</span>
                                                    <div class="rvw__pick--stars">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <span class="js-star" data-value="<?php echo $i; ?>" role="button" tabindex="0" aria-label="<?php echo $i; ?> star<?php echo $i === 1 ? '' : 's'; ?>"><svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="m12 17.3-6.16 3.7 1.64-7.03L2 9.24l7.19-.61L12 2l2.81 6.63 7.19.61-5.48 4.73L18.16 21z"/></svg></span>
                                                        <?php endfor; ?>
                                                    </div>
                                                </div>
                                                <div class="rvw__field"><input class="checkout__input--field w-100" placeholder="Review title (optional)" type="text" name="title" data-label="Review title"></div>
                                                <div class="rvw__field"><textarea class="checkout__input--field w-100" rows="4" placeholder="How does it look and wear? What stood out?" name="comment" data-label="Your review" minlength="10" required></textarea></div>
                                                <button class="btn btn-primary" type="submit">Submit Review</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div id="information" class="tab_pane">
                                <table class="pdx__spectable">
                                    <tbody>
                                        <tr><th>SKU</th><td><?php echo e($product['sku']); ?></td></tr>
                                        <?php if ($product['category']): ?><tr><th>Category</th><td><?php echo e($product['category']); ?></td></tr><?php endif; ?>
                                        <?php if ($product['purity']): ?><tr><th>Purity</th><td><?php echo e($product['purity']); ?></td></tr><?php endif; ?>
                                        <?php if ($product['weight']): ?><tr><th>Weight</th><td><?php echo e($product['weight']); ?></td></tr><?php endif; ?>
                                        <?php if (!empty($product['making_charge'])): ?><tr><th>Making charge</th><td><?php echo formatPrice($product['making_charge']); ?></td></tr><?php endif; ?>
                                        <tr><th>Availability</th><td><?php echo $product['in_stock'] ? 'In stock' : 'Out of stock'; ?></td></tr>
                                    </tbody>
                                </table>
                                <?php if (trim((string) $product['specifications']) !== ''): ?>
                                    <div class="pdx__prose" style="margin-top:2.4rem"><?php echo nl2br(e($product['specifications'])); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Related -->
    <?php if ($relatedProducts): ?>
        <section class="product__section product__section--style3 mb-20">
            <div class="container product3__section--container">
                <div class="section__heading text-center mb-50">
                    <h2 class="section__heading--maintitle">You may also like</h2>
                </div>
                <div class="product__section--inner product__swiper--column4__activation swiper">
                    <div class="swiper-wrapper">
                        <?php foreach ($relatedProducts as $related): ?>
                            <div class="swiper-slide">
                                <?php $product = $related; include 'includes/product-card-bare.php'; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="swiper__nav--btn swiper-button-next"></div>
                    <div class="swiper__nav--btn swiper-button-prev"></div>
                </div>
            </div>
        </section>
        <?php $product = getProduct($id); ?>
    <?php endif; ?>

    <section class="shipping__section2 shipping__style3 section--padding pt-5">
        <div class="container">
            <div class="shipping__section2--inner shipping__style3--inner d-flex justify-content-between">
                <div class="shipping__items2 d-flex align-items-center"><div class="shipping__items2--icon"><img src="assets/img/other/shipping1.png" alt=""></div><div class="shipping__items2--content"><h2 class="shipping__items2--content__title h3">Shipping</h2><p class="shipping__items2--content__desc">Free above <?php echo formatPrice(get_setting('free_shipping_threshold', 50000), 0); ?></p></div></div>
                <div class="shipping__items2 d-flex align-items-center"><div class="shipping__items2--icon"><img src="assets/img/other/shipping2.png" alt=""></div><div class="shipping__items2--content"><h2 class="shipping__items2--content__title h3">Payment</h2><p class="shipping__items2--content__desc">Secure checkout</p></div></div>
                <div class="shipping__items2 d-flex align-items-center"><div class="shipping__items2--icon"><img src="assets/img/other/shipping3.png" alt=""></div><div class="shipping__items2--content"><h2 class="shipping__items2--content__title h3">Return</h2><p class="shipping__items2--content__desc">Easy returns</p></div></div>
                <div class="shipping__items2 d-flex align-items-center"><div class="shipping__items2--icon"><img src="assets/img/other/shipping4.png" alt=""></div><div class="shipping__items2--content"><h2 class="shipping__items2--content__title h3">Support</h2><p class="shipping__items2--content__desc">Dedicated support</p></div></div>
            </div>
        </div>
    </section>
</main>

<script>
(function () {
    var basePrice = <?php echo json_encode((float) $product['price']); ?>;
    var symbol = <?php echo json_encode(get_setting('currency_symbol', '&#8377;')); ?>;
    function fmt(n) { return symbol + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    var form = document.getElementById('pdCartForm');
    if (form) {
        form.addEventListener('change', function (e) {
            if (e.target.name !== 'variant_option') return;
            document.querySelectorAll('.pd__variant--option').forEach(function (l) { l.classList.remove('is-checked'); });
            e.target.closest('.pd__variant--option').classList.add('is-checked');
            var chosen = form.querySelectorAll('input[name="variant_option"]:checked');
            var vid = '', adjust = 0;
            chosen.forEach(function (c) { vid = c.value; adjust += parseFloat(c.getAttribute('data-price-adjust')) || 0; });
            form.querySelector('input[name="variant_id"]').value = vid;
            var pr = document.getElementById('pdPrice');
            if (pr) pr.textContent = fmt(basePrice + adjust);
            syncStock();
        });
    }

    /* ---------- stock validation ---------- */
    var productStock = <?php echo (int) $product['stock_quantity']; ?>;
    var qtyInput = form ? form.querySelector('[data-stock-input]') : null;
    var stockMsg = document.querySelector('[data-stock-msg]');
    var addBtn   = form ? form.querySelector('[data-add-cart]') : null;
    var buyBtn   = form ? form.querySelector('[data-buy-now]') : null;

    /** Stock of the chosen variant, or the product's own when no variant is picked. */
    function currentStock() {
        var picked = form ? form.querySelector('input[name="variant_option"]:checked') : null;
        return picked ? parseInt(picked.getAttribute('data-stock'), 10) || 0 : productStock;
    }

    function note(text) {
        if (!stockMsg) return;
        stockMsg.textContent = text || '';
        stockMsg.hidden = !text;
    }

    /* Re-point max/messages at whatever is selected, and clamp the qty into range. */
    function syncStock() {
        if (!qtyInput) return;
        var stock = currentStock();
        qtyInput.max = Math.max(1, stock);
        qtyInput.setAttribute('data-max-message', 'Only ' + stock + ' in stock.');

        var soldOut = stock <= 0;
        if (addBtn) addBtn.disabled = soldOut;
        if (buyBtn) buyBtn.disabled = soldOut;

        /* The theme's +/- buttons write the value straight in without honouring
           min, so 1 then "-" left a 0 in the box (and a validation error). */
        var val = parseInt(qtyInput.value, 10);
        if (isNaN(val) || val < 1) {
            qtyInput.value = 1;
            if (window.formValidate) { window.formValidate.setError(qtyInput, ''); }
        }

        if (soldOut) { note('This option is out of stock.'); return; }
        if (parseInt(qtyInput.value, 10) > stock) {
            qtyInput.value = stock;
            note('Only ' + stock + ' in stock — quantity reduced to ' + stock + '.');
            return;
        }
        note('');
    }

    if (qtyInput) {
        /* the theme's +/- buttons write the value directly, so re-check after the click */
        form.addEventListener('click', function (e) {
            if (e.target.closest('.quantity__value')) setTimeout(syncStock, 0);
        });
        qtyInput.addEventListener('input', syncStock);
        qtyInput.addEventListener('change', syncStock);
        syncStock();
    }

    /* Buy Now: skip the AJAX add-to-cart in shop.js and let the browser POST
       normally, so cart-actions.php can add the item and send us to checkout.
       Capture phase runs before shop.js's document-level submit listener. */
    if (form && buyBtn) {
        var buyNow = false;
        buyBtn.addEventListener('click', function () { buyNow = true; });
        addBtn && addBtn.addEventListener('click', function () { buyNow = false; });

        form.addEventListener('submit', function (e) {
            if (!buyNow) return;
            buyNow = false;

            var stock = currentStock();
            var want  = parseInt(qtyInput ? qtyInput.value : '1', 10) || 1;
            if (stock <= 0 || want > stock) {
                e.preventDefault();
                e.stopImmediatePropagation();
                syncStock();
                return;
            }
            /* valid: stop shop.js from hijacking it, let the native POST run */
            e.stopImmediatePropagation();
        }, true);
    }
    /* Swiper is loaded by includes/footer.php, i.e. AFTER this inline script,
       so window.Swiper is still undefined here — the gallery has to be built
       once the document (and the plugin) is ready. */
    function initGallery() {
        var previewEl = document.getElementById('pdPreview');
        if (!window.Swiper || !previewEl || previewEl.swiper) { return; }
        var thumbsEl = document.getElementById('pdThumbs');
        /* observer/observeParents: if Swiper is built while the stage still has
           no usable width (phones hit this — the column is laid out after the
           script runs) every slide is sized 0 and the gallery paints as an empty
           grey box. These make Swiper re-measure itself when the box changes. */
        var thumbs = thumbsEl ? new Swiper('#pdThumbs', {
            slidesPerView: 'auto',
            spaceBetween: 10,
            watchSlidesProgress: true,
            slideToClickedSlide: true,
            observer: true,
            observeParents: true
        }) : null;
        var preview = new Swiper('#pdPreview', {
            spaceBetween: 0,
            slidesPerView: 1,
            loop: false,
            navigation: { nextEl: '#pdPreview .swiper-button-next', prevEl: '#pdPreview .swiper-button-prev' },
            keyboard: { enabled: true },
            observer: true,
            observeParents: true,
            thumbs: thumbs ? { swiper: thumbs } : undefined
        });
        /* Swiper 7 has no `swiper-initialized` class (that landed in v8), so the
           CSS fallback that shows slide 1 un-sliced keys off this instead. */
        previewEl.classList.add('is-ready');
        if (thumbsEl) { thumbsEl.classList.add('is-ready'); }

        /* the images have no intrinsic box until they decode; re-measure then,
           and on rotate, so the first slide is never left at a stale width */
        previewEl.querySelectorAll('img').forEach(function (im) {
            if (!im.complete) { im.addEventListener('load', function () { preview.update(); }, { once: true }); }
        });
        window.addEventListener('resize', function () { preview.update(); if (thumbs) { thumbs.update(); } });
        window.addEventListener('orientationchange', function () { preview.update(); });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGallery);
    } else {
        initGallery();
    }
    window.addEventListener('load', initGallery);   // belt and braces
})();
</script>
<?php include 'includes/footer.php'; ?>
