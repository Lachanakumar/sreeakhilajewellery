<?php
/**
 * Editorial promo card above the shop grid. Uses the first active "wide" banner
 * when the admin has set one, otherwise falls back to static copy + a theme image.
 */
require_once __DIR__ . '/homepage.php';

// Deliberately not reading the homepage 'wide' banners: those are category tiles
// with dark art that fights this cream panel. Override here if you want it
// admin-managed, and give it a light image.
$promoTitle = "Pure Elegance\nIn Every Detail";
$promoLabel = 'Explore Collection';
$promoHref  = 'products.php?sort=best_selling';
$promoImg   = 'assets/img/product/items/bangles.jpg';
?>
<a class="shop__promo" href="<?php echo e($promoHref); ?>">
    <div class="shop__promo--content">
        <span class="shop__promo--eyebrow">Exclusive Collection</span>
        <h2 class="shop__promo--title"><?php echo multiline_html($promoTitle); ?></h2>
        <p class="shop__promo--tags">Traditional <i>|</i> Trendy <i>|</i> Timeless</p>
        <span class="shop__promo--btn">
            <?php echo e($promoLabel); ?>
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </span>
    </div>

    <div class="shop__promo--media">
        <img src="<?php echo e($promoImg); ?>" alt="" loading="lazy">
    </div>

    <div class="shop__promo--note">
        <span>Crafted<br>With Love</span>
        <i class="shop__promo--note__rule"></i>
    </div>
</a>
