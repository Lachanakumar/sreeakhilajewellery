<?php
require_once 'includes/config.php';
require_once 'includes/products.php';
require_once 'includes/cart-functions.php';
require_once 'includes/auth.php';
require_once 'includes/orders.php';

requireLogin();
$user = currentUser();
$order = getOrderByNumber($_GET['order'] ?? '', $user['id']);
if (!$order) {
    flash_set('error', 'We could not find that order on your account.');
    redirect('account.php?tab=orders');
}
$items = getOrderItems($order['id']);
$csrf = csrf_token();

// Customer-initiated cancellation while still pending/confirmed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'cancel') {
    if (!csrf_verify()) {
        flash_set('error', 'Your session expired — the order was not cancelled. Please try again.');
    } elseif (in_array($order['order_status'], ['pending', 'confirmed'], true)) {
        updateOrderStatus($order['id'], 'cancelled');
        flash_set('success', 'Your order has been cancelled. Any payment made will be refunded to the original method.');
    } else {
        flash_set('error', 'This order can no longer be cancelled. Please contact us and we will help.');
    }
    redirect('order-details.php?order=' . urlencode($order['order_number']));
}

/* ---- delivery progress ---- */
$flow   = ['pending' => 'Placed', 'confirmed' => 'Confirmed', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];
$status = $order['order_status'];
$dead   = in_array($status, ['cancelled', 'refunded'], true);
// "processing" sits between confirmed and shipped in the DB enum
$reached = ['pending' => 0, 'confirmed' => 1, 'processing' => 1, 'shipped' => 2, 'delivered' => 3];
$stepNow = $reached[$status] ?? 0;

$canCancel = in_array($status, ['pending', 'confirmed'], true);
$payState = payment_status_label($order);
// an online payment that never completed can be picked up again
$canRetryPayment = !$dead && !order_is_cod($order)
    && in_array($order['payment_status'], ['failed', 'cancelled', 'pending'], true);
$itemCount = 0;
foreach ($items as $it) { $itemCount += (int) $it['quantity']; }

// The orders table stores no tax column, but tax is included in total_amount.
// Show the remainder as its own line so the figures visibly add up.
// order_tax_amount() is shared with the admin order view, so both show the
// same GST figure for the same order.
$taxAmount = order_tax_amount($order);
$taxPct    = order_tax_percent($order);

$pageMetaTitle = SITE_NAME . ' - Order ' . $order['order_number'];
$pageTitle     = 'Order ' . $order['order_number'];
$breadcrumbs   = [
    ['name' => 'Home', 'url' => 'index.php'],
    ['name' => 'My Orders', 'url' => 'account.php?tab=orders'],
    ['name' => $order['order_number'], 'url' => ''],
];
$pageHeading   = false;  // the summary card carries the <h1>
$pageStyles    = ['assets/css/detail.css'];
include 'includes/header.php';
?>
<main class="main__content_wrapper">
    <?php include 'includes/page-title.php'; ?>

    <section class="odx">
        <div class="container">
            <div class="odx__grid">

                <div class="odx__main">
                    <!-- summary + progress -->
                    <div class="odx__card">
                        <div class="odx__head">
                            <div>
                                <h1 class="odx__num"><?php echo e($order['order_number']); ?></h1>
                                <p class="odx__placed">
                                    Placed <?php echo date('j F Y', strtotime($order['created_at'])); ?>
                                    at <?php echo date('g:i A', strtotime($order['created_at'])); ?>
                                    &middot; <?php echo $itemCount; ?> item<?php echo $itemCount === 1 ? '' : 's'; ?>
                                </p>
                            </div>
                            <span class="odx__status odx__status--<?php echo e($status); ?>"><?php echo e(ucfirst($status)); ?></span>
                        </div>

                        <?php if ($dead): ?>
                            <p class="odx__track--dead">
                                This order was <?php echo e($status); ?>.
                                <?php if ($status === 'refunded'): ?>
                                    The amount has been returned to your original payment method.
                                <?php else: ?>
                                    If this was not intended, please get in touch and we will help.
                                <?php endif; ?>
                            </p>
                        <?php else: ?>
                            <ol class="odx__track">
                                <?php $i = 0; foreach ($flow as $key => $label): ?>
                                    <li class="<?php echo $i <= $stepNow ? 'is-done' : ''; ?><?php echo $i === $stepNow ? ' is-current' : ''; ?>">
                                        <?php echo e($label); ?>
                                    </li>
                                <?php $i++; endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </div>

                    <!-- items -->
                    <div class="odx__card">
                        <h3>Items in this order</h3>
                        <?php foreach ($items as $it):
                            $img  = trim((string) ($it['image'] ?? ''));
                            $live = $it['product_id'] && ($it['product_status'] ?? '') === 'active';
                        ?>
                            <div class="odx__item">
                                <?php if ($img !== ''): ?>
                                    <img class="odx__item--img" src="<?php echo e($img); ?>" alt="<?php echo e($it['product_name']); ?>" loading="lazy">
                                <?php else: ?>
                                    <span class="odx__item--img"></span>
                                <?php endif; ?>
                                <div class="odx__item--body">
                                    <?php if ($live): ?>
                                        <a class="odx__item--name" href="product-details.php?id=<?php echo (int) $it['product_id']; ?>"><?php echo e($it['product_name']); ?></a>
                                    <?php else: ?>
                                        <span class="odx__item--name"><?php echo e($it['product_name']); ?></span>
                                    <?php endif; ?>
                                    <span class="odx__item--meta">
                                        <?php if (!empty($it['sku'])): ?><?php echo e($it['sku']); ?> &middot; <?php endif; ?>
                                        Qty <?php echo (int) $it['quantity']; ?> &times; <?php echo formatPrice($it['price']); ?>
                                    </span>
                                </div>
                                <div class="odx__item--money">
                                    <b><?php echo formatPrice($it['subtotal']); ?></b>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <ul class="odx__totals">
                            <li><span>Subtotal</span><span><?php echo formatPrice($order['subtotal']); ?></span></li>
                            <?php if ($order['discount_amount'] > 0): ?>
                                <li class="is-free">
                                    <span>Discount<?php echo $order['coupon_code'] ? ' (' . e($order['coupon_code']) . ')' : ''; ?></span>
                                    <span>&minus; <?php echo formatPrice($order['discount_amount']); ?></span>
                                </li>
                            <?php endif; ?>
                            <li class="<?php echo $order['shipping_amount'] > 0 ? '' : 'is-free'; ?>">
                                <span>Shipping</span>
                                <span><?php echo $order['shipping_amount'] > 0 ? formatPrice($order['shipping_amount']) : 'Free'; ?></span>
                            </li>
                            <?php if ($taxAmount > 0.009): ?>
                                <li><span>GST<?php echo $taxPct > 0 ? ' (' . rtrim(rtrim(number_format($taxPct, 2), '0'), '.') . '%)' : ''; ?></span><span><?php echo formatPrice($taxAmount); ?></span></li>
                            <?php endif; ?>
                            <li class="is-total"><span>Total</span><span><?php echo formatPrice($order['total_amount']); ?></span></li>
                        </ul>
                    </div>
                </div>

                <aside class="odx__side">
                    <div class="odx__card">
                        <h3>Delivery address</h3>
                        <p class="odx__addr">
                            <strong><?php echo e($order['shipping_name']); ?></strong>
                            <?php echo nl2br(e($order['shipping_address'])); ?><br>
                            <?php echo e($order['shipping_phone']); ?>
                        </p>
                    </div>

                    <div class="odx__card">
                        <h3>Payment</h3>
                        <div class="odx__pay">
                            <span>Method</span>
                            <b><?php echo e(payment_method_label($order)); ?></b>
                        </div>
                        <div class="odx__pay" style="margin-top:.8rem">
                            <span>Status</span>
                            <b class="odx__pay--<?php echo e($payState['tone']); ?>"><?php echo e($payState['label']); ?></b>
                        </div>
                        <?php if ($payState['note'] !== ''): ?>
                            <p class="odx__pay--note"><?php echo e($payState['note']); ?></p>
                        <?php endif; ?>
                        <?php if ($canRetryPayment): ?>
                            <a class="btn btn-primary w-100" style="margin-top:1.2rem" href="checkout.php?retry=<?php echo (int) $order['id']; ?>">Retry payment</a>
                        <?php endif; ?>
                    </div>

                    <div class="odx__card">
                        <h3>Need anything?</h3>
                        <div class="odx__btns">
                            <button type="button" class="btn btn-outline-primary" onclick="window.print()">Print this order</button>
                            <?php if ($canCancel): ?>
                                <form method="post" onsubmit="return confirm('Cancel order <?php echo e($order['order_number']); ?>? This cannot be undone.')">
                                    <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                                    <input type="hidden" name="do" value="cancel">
                                    <button type="submit" class="odx__btn--danger">Cancel this order</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <p class="odx__help" style="margin-top:1.4rem">
                            <?php if (!$canCancel && !$dead): ?>
                                This order is already on its way, so it can no longer be cancelled online.<br>
                            <?php endif; ?>
                            Questions about this order? <a href="contact.php">Contact us</a>
                            or call <?php echo site_phone_links(); ?>.
                        </p>
                    </div>
                </aside>

            </div>
        </div>
    </section>
</main>
<?php include 'includes/footer.php'; ?>
