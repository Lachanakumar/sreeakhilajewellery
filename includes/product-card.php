<?php
/**
 * Reusable product grid card (editorial style).
 * Expects $product (hydrated array) in scope. Keeps the theme's JS hook classes
 * (js-cart-form, js-wishlist-toggle) and the non-JS form fallback.
 */
if (!isset($product)) {
    return;
}
$__csrf = csrf_token();
$__redirect = basename($_SERVER['SCRIPT_NAME'] ?? 'products.php');
?>
<div class="col mb-30">
    <article class="product__items">
        <div class="product__items--thumbnail">
            <a class="product__items--link" href="product-details.php?id=<?php echo $product['id']; ?>">
                <img class="product__items--img product__primary--img" src="<?php echo e($product['image']); ?>" alt="<?php echo e($product['name']); ?>" loading="lazy">
                <img class="product__items--img product__secondary--img" src="<?php echo e($product['image2']); ?>" alt="<?php echo e($product['name']); ?>" loading="lazy">
            </a>
            <?php if (!empty($product['badge'])): ?>
                <span class="product__badge--items"><?php echo e($product['badge']); ?></span>
            <?php endif; ?>
            <a class="product__wish js-wishlist-toggle<?php echo isInWishlist($product['id']) ? ' is-active' : ''; ?>"
               href="cart-actions.php?action=add_to_wishlist&product_id=<?php echo $product['id']; ?>&redirect=<?php echo e($__redirect); ?>&token=<?php echo e($__csrf); ?>"
               data-product-id="<?php echo $product['id']; ?>" title="Add to wishlist" aria-label="Add to wishlist">
                <svg viewBox="0 0 512 512" width="18" height="18"><path d="M352.92 80C288 80 256 144 256 144s-32-64-96.92-64c-52.76 0-94.54 44.14-95.08 96.81-1.1 109.33 86.73 187.08 183 252.42a16 16 0 0018 0c96.26-65.34 184.09-143.09 183-252.42-.54-52.67-42.32-96.81-95.08-96.81z" fill="<?php echo isInWishlist($product['id']) ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32"></path></svg>
            </a>
            <form action="cart-actions.php" method="post" class="js-cart-form product__addbar">
                <input type="hidden" name="csrf_token" value="<?php echo e($__csrf); ?>">
                <input type="hidden" name="action" value="add_to_cart">
                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                <input type="hidden" name="redirect" value="cart.php">
                <button type="submit"<?php echo $product['in_stock'] ? '' : ' disabled'; ?>>
                    <?php echo $product['in_stock'] ? 'Add to Cart' : 'Out of Stock'; ?>
                </button>
            </form>
        </div>
        <div class="product__items--content">
            <span class="product__items--content__subtitle"><?php echo e($product['category']); ?></span>
            <h3 class="product__items--content__title"><a href="product-details.php?id=<?php echo $product['id']; ?>"><?php echo e($product['name']); ?></a></h3>
            <div class="product__items--price">
                <span class="current__price"><?php echo formatPrice($product['price'], 0); ?></span>
                <?php if (!empty($product['old_price'])): ?><span class="old__price"><?php echo formatPrice($product['old_price'], 0); ?></span><?php endif; ?>
            </div>
        </div>
    </article>
</div>
