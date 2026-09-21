<?php
/**
 * Product card used by the shop/category grid (products.php, category.php and
 * the AJAX filter endpoint). Richer than the homepage card: stock, rating and
 * always-visible actions. The homepage keeps includes/product-card.php.
 * Expects $product (hydrated array) in scope.
 */
if (!isset($product)) {
    return;
}
$__csrf     = csrf_token();
$__redirect = basename($_SERVER['SCRIPT_NAME'] ?? 'products.php');
$__href     = 'product-details.php?id=' . $product['id'];

// hydrate_product() sets Sale/New; Featured is a shop-only third state.
$badge = $product['badge'];
if ($badge === '' && !empty($product['is_featured'])) {
    $badge = 'Featured';
}
$badgeClass = $badge !== '' ? 'is-' . strtolower($badge) : '';
$wished = isInWishlist($product['id']);
?>
<div class="col mb-30">
    <article class="shop__card">
        <div class="shop__card--media">
            <a class="shop__card--link" href="<?php echo e($__href); ?>">
                <img class="shop__card--img" src="<?php echo e($product['image']); ?>" alt="<?php echo e($product['name']); ?>" loading="lazy">
            </a>
            <?php if ($badge !== ''): ?>
                <span class="shop__card--badge <?php echo e($badgeClass); ?>"><?php echo e($badge); ?></span>
            <?php endif; ?>
            <a class="shop__card--wish js-wishlist-toggle<?php echo $wished ? ' is-active' : ''; ?>"
               href="cart-actions.php?action=add_to_wishlist&product_id=<?php echo $product['id']; ?>&redirect=<?php echo e($__redirect); ?>&token=<?php echo e($__csrf); ?>"
               data-product-id="<?php echo $product['id']; ?>" title="Add to wishlist" aria-label="Add to wishlist">
                <svg viewBox="0 0 512 512" width="17" height="17"><path d="M352.92 80C288 80 256 144 256 144s-32-64-96.92-64c-52.76 0-94.54 44.14-95.08 96.81-1.1 109.33 86.73 187.08 183 252.42a16 16 0 0018 0c96.26-65.34 184.09-143.09 183-252.42-.54-52.67-42.32-96.81-95.08-96.81z" fill="<?php echo $wished ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32"></path></svg>
            </a>
        </div>

        <div class="shop__card--body">
            <span class="shop__card--cat"><?php echo e($product['category']); ?></span>
            <h3 class="shop__card--title"><a href="<?php echo e($__href); ?>"><?php echo e($product['name']); ?></a></h3>

            <div class="shop__card--price">
                <span class="current__price"><?php echo formatPrice($product['price'], 0); ?></span>
                <?php if (!empty($product['old_price'])): ?>
                    <span class="old__price"><?php echo formatPrice($product['old_price'], 0); ?></span>
                <?php endif; ?>
            </div>

            <p class="shop__card--stock<?php echo $product['in_stock'] ? '' : ' is-out'; ?>">
                <span class="shop__card--dot"></span>
                <?php if ($product['in_stock']): ?>
                    In Stock (<?php echo (int) $product['stock_quantity']; ?>)
                <?php else: ?>
                    Out of Stock
                <?php endif; ?>
            </p>

            <?php if (!empty($product['rating'])): ?>
                <p class="shop__card--rating">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor"><path d="m12 17.3-6.16 3.7 1.64-7.03L2 9.24l7.19-.61L12 2l2.81 6.63 7.19.61-5.48 4.73L18.16 21z"/></svg>
                    <strong><?php echo number_format((float) $product['rating'], 1); ?></strong>
                    <span>(<?php echo (int) $product['rating_count']; ?>)</span>
                </p>
            <?php endif; ?>

            <div class="shop__card--actions">
                <form action="cart-actions.php" method="post" class="js-cart-form">
                    <input type="hidden" name="csrf_token" value="<?php echo e($__csrf); ?>">
                    <input type="hidden" name="action" value="add_to_cart">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    <input type="hidden" name="redirect" value="cart.php">
                    <button type="submit" class="shop__card--cart"<?php echo $product['in_stock'] ? '' : ' disabled'; ?>>
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        <?php echo $product['in_stock'] ? 'Add to Cart' : 'Sold Out'; ?>
                    </button>
                </form>
                <a class="shop__card--view" href="<?php echo e($__href); ?>">View</a>
            </div>
        </div>
    </article>
</div>
