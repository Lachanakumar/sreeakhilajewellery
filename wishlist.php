<?php
require_once 'includes/config.php';
require_once 'includes/products.php';
require_once 'includes/cart-functions.php';
require_once 'includes/auth.php';

// Remove / move-to-cart post back to this page so the token is always the one
// this page was rendered with — the old GET links carried a token in the query
// string, which went stale and produced "Your session expired".
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wishlist_action'])) {
    if (!csrf_verify()) {
        flash_set('error', 'Your session expired. Please try again.');
        redirect('wishlist.php');
    }
    $pid = (int) ($_POST['product_id'] ?? 0);
    if ($_POST['wishlist_action'] === 'move_to_cart') {
        moveWishlistToCart($pid);
        flash_set('success', 'Moved to cart.');
        redirect('cart.php');
    }
    removeFromWishlist($pid);
    flash_set('success', 'Removed from wishlist.');
    redirect('wishlist.php');
}

$wishlistItems = getWishlistItems();
$csrf = csrf_token();

$pageMetaTitle = SITE_NAME . ' - Wishlist';
$pageTitle     = 'Wishlist';
$breadcrumbs   = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'Wishlist', 'url' => '']];
include 'includes/header.php';
?>
<main class="main__content_wrapper">
    <?php include 'includes/page-title.php'; ?>

    <section class="cart__section section--padding">
        <div class="container-fluid">
            <?php if (empty($wishlistItems)): ?>
                <div class="text-center py-5">
                    <h3>Your wishlist is empty</h3>
                    <p class="mt-3">Browse products and add your favorites!</p>
                    <a href="products.php" class="primary__btn mt-3">Browse Products</a>
                </div>
            <?php else: ?>
                <div class="cart__section--inner">
                    <div class="d-flex justify-content-between align-items-center flex-wrap mb-30" style="gap:12px">
                        <h2 class="cart__title m-0">My Wishlist</h2>
                        <a class="btn btn-primary" href="appointment.php?wishlist=1">Book an appointment for these pieces</a>
                    </div>
                    <div class="cart__table">
                        <table class="cart__table--inner">
                            <thead class="cart__table--header">
                                <tr class="cart__table--header__items">
                                    <th class="cart__table--header__list">Product</th>
                                    <th class="cart__table--header__list">Price</th>
                                    <th class="cart__table--header__list">Status</th>
                                    <th class="cart__table--header__list text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="cart__table--body">
                                <?php foreach ($wishlistItems as $item): ?>
                                    <tr class="cart__table--body__items">
                                        <td class="cart__table--body__list">
                                            <div class="cart__product d-flex align-items-center">
                                                <div class="cart__thumbnail"><a href="product-details.php?id=<?php echo $item['id']; ?>"><img class="border-radius-5" src="<?php echo e($item['image']); ?>" alt="<?php echo e($item['name']); ?>"></a></div>
                                                <div class="cart__content">
                                                    <h3 class="cart__content--title h4"><a href="product-details.php?id=<?php echo $item['id']; ?>"><?php echo e($item['name']); ?></a></h3>
                                                    <span class="cart__content--variant"><?php echo e($item['category']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="cart__table--body__list"><span class="cart__price"><?php echo formatPrice($item['price']); ?></span></td>
                                        <td class="cart__table--body__list"><span class="cart__price" style="color:<?php echo $item['in_stock'] ? 'green' : '#c0392b'; ?>;"><?php echo $item['in_stock'] ? 'In Stock' : 'Out of Stock'; ?></span></td>
                                        <td class="cart__table--body__list text-right">
                                            <form method="post" action="wishlist.php" class="d-flex align-items-center justify-content-center" style="gap:10px;">
                                                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                                                <input type="hidden" name="product_id" value="<?php echo (int) $item['id']; ?>">
                                                <?php if ($item['in_stock']): ?>
                                                    <button type="submit" name="wishlist_action" value="move_to_cart" class="wishlist__cart--btn primary__btn">Add to Cart</button>
                                                <?php endif; ?>
                                                <button type="submit" name="wishlist_action" value="remove" class="cart__remove--btn" title="Remove" aria-label="Remove <?php echo e($item['name']); ?> from the wishlist">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 512 512"><path fill="currentColor" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M368 368L144 144M368 144L144 368"/></svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php include 'includes/footer.php'; ?>
