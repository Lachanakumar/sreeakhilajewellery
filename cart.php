<?php
require_once 'includes/config.php';
require_once 'includes/products.php';
require_once 'includes/cart-functions.php';
require_once 'includes/auth.php';

// Remove a line, and the non-JS bulk quantity update. Both post to this page
// so they always carry the token this page was rendered with.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['update_cart']) || isset($_POST['remove_row']))) {
    if (!csrf_verify()) {
        flash_set('error', 'Your session expired. Please try again.');
        redirect('cart.php');
    }

    if (isset($_POST['remove_row'])) {
        $rowId = (int) $_POST['remove_row'];
        $removed = false;
        foreach (getCartRows() as $row) {
            if ((int) $row['id'] === $rowId) {
                removeFromCart((int) $row['product_id'], $row['variant_id'] !== null ? (int) $row['variant_id'] : null);
                $removed = true;
                break;
            }
        }
        flash_set($removed ? 'success' : 'error', $removed
            ? 'Item removed from cart.'
            : 'That item is no longer in your cart.');
        redirect('cart.php');
    }

    foreach (($_POST['qty'] ?? []) as $rowId => $qty) {
        foreach (getCartRows() as $row) {
            if ((int) $row['id'] === (int) $rowId) {
                updateCartQty($row['product_id'], (int) $qty, $row['variant_id'] ? (int) $row['variant_id'] : null);
            }
        }
    }
    flash_set('success', 'Cart updated.');
    redirect('cart.php');
}

$cartItems = getCartItems();
$totals    = cartTotals();
$csrf      = csrf_token();

$pageMetaTitle = SITE_NAME . ' - Cart';
$pageTitle     = 'Shopping Cart';
$breadcrumbs   = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'Cart', 'url' => '']];
include 'includes/header.php';
?>
<main class="main__content_wrapper">
    <?php include 'includes/page-title.php'; ?>

    <section class="cart__section section--padding">
        <div class="container-fluid">
            <?php if (empty($cartItems)): ?>
                <div class="text-center py-5">
                    <h3>Your cart is empty</h3>
                    <p class="mt-3">Browse our products and add items to your cart.</p>
                    <a href="products.php" class="primary__btn mt-3">Continue Shopping</a>
                </div>
            <?php else: ?>
                <div class="cart__section--inner">
                    <form action="cart.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                        <h2 class="cart__title mb-30">Your Cart</h2>
                        <div class="cart__table">
                            <table class="cart__table--inner">
                                <thead class="cart__table--header">
                                    <tr class="cart__table--header__items">
                                        <th class="cart__table--header__list">Product</th>
                                        <th class="cart__table--header__list">Price</th>
                                        <th class="cart__table--header__list">Quantity</th>
                                        <th class="cart__table--header__list">Total</th>
                                        <th class="cart__table--header__list">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="cart__table--body">
                                    <?php foreach ($cartItems as $item): ?>
                                        <tr class="cart__table--body__items">
                                            <td class="cart__table--body__list">
                                                <div class="cart__product d-flex align-items-center">
                                                    <div class="cart__thumbnail"><a href="product-details.php?id=<?php echo $item['id']; ?>"><img class="border-radius-5" src="<?php echo e($item['image']); ?>" alt="<?php echo e($item['name']); ?>"></a></div>
                                                    <div class="cart__content">
                                                        <h3 class="cart__content--title h4"><a href="product-details.php?id=<?php echo $item['id']; ?>"><?php echo e($item['name']); ?></a></h3>
                                                        <span class="cart__content--variant"><?php echo e($item['category']); ?></span>
                                                        <?php if ($item['variant_label']): ?><br><span class="cart__content--variant"><?php echo e($item['variant_label']); ?></span><?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="cart__table--body__list"><span class="cart__price"><?php echo formatPrice($item['unit_price']); ?></span></td>
                                            <td class="cart__table--body__list">
                                                <div class="quantity__box">
                                                    <button type="button" class="quantity__value decrease">-</button>
                                                    <label><input type="number" name="qty[<?php echo $item['cart_row_id']; ?>]" class="quantity__number js-cart-qty" data-row-id="<?php echo $item['cart_row_id']; ?>" value="<?php echo $item['qty']; ?>" min="1" data-counter/></label>
                                                    <button type="button" class="quantity__value increase">+</button>
                                                </div>
                                            </td>
                                            <td class="cart__table--body__list"><span class="cart__price end"><?php echo formatPrice($item['subtotal']); ?></span></td>
                                            <td class="cart__table--body__list">
                                                <button type="submit" name="remove_row" value="<?php echo (int) $item['cart_row_id']; ?>" class="cart__remove--btn js-cart-remove" data-row-id="<?php echo (int) $item['cart_row_id']; ?>" title="Remove" aria-label="Remove <?php echo e($item['name']); ?> from the cart">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 512 512"><path fill="currentColor" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M368 368L144 144M368 144L144 368"/></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="continue__shopping d-flex justify-content-between mt-3">
                            <a class="continue__shopping--link" href="products.php">Continue Shopping</a>
                            <button type="submit" name="update_cart" value="1" class="primary__btn">Update Cart</button>
                        </div>
                    </form>

                    <div class="row justify-content-end mt-4">
                        <div class="col-lg-5">
                            <form class="js-coupon-form d-flex mb-3" style="gap:10px">
                                <input type="text" name="code" placeholder="Coupon code" value="<?php echo e($totals['coupon_code'] ?? ''); ?>" style="flex:1">
                                <button type="submit" class="primary__btn">Apply</button>
                            </form>
                            <div class="cart__summary border-radius-10">
                                <div class="cart__summary--total mb-20">
                                    <table class="cart__summary--total__table" style="width:100%">
                                        <tbody>
                                            <tr class="cart__summary--total__list"><td class="cart__summary--total__title">Subtotal</td><td class="cart__summary--amount" style="text-align:right"><?php echo formatPrice($totals['subtotal']); ?></td></tr>
                                            <?php if ($totals['discount'] > 0): ?>
                                                <tr class="cart__summary--total__list"><td class="cart__summary--total__title">Discount (<?php echo e($totals['coupon_code']); ?>)</td><td class="cart__summary--amount" style="text-align:right">- <?php echo formatPrice($totals['discount']); ?></td></tr>
                                            <?php endif; ?>
                                            <tr class="cart__summary--total__list"><td class="cart__summary--total__title">Shipping</td><td class="cart__summary--amount" style="text-align:right"><?php echo $totals['shipping'] > 0 ? formatPrice($totals['shipping']) : 'Free'; ?></td></tr>
                                            <?php if ($totals['tax'] > 0): ?>
                                                <tr class="cart__summary--total__list"><td class="cart__summary--total__title">GST (<?php echo (float) $totals['tax_percent']; ?>%)</td><td class="cart__summary--amount" style="text-align:right"><?php echo formatPrice($totals['tax']); ?></td></tr>
                                            <?php endif; ?>
                                            <tr class="cart__summary--total__list"><td class="cart__summary--total__title"><strong>Grand Total</strong></td><td class="cart__summary--amount" style="text-align:right"><strong><?php echo formatPrice($totals['total']); ?></strong></td></tr>
                                        </tbody>
                                    </table>
                                </div>
                                <a class="cart__summary--footer__btn primary__btn w-100" href="checkout.php">Proceed to Checkout</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php include 'includes/footer.php'; ?>
