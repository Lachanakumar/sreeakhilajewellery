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
    redirect('account.php?tab=orders');
}
$items = getOrderItems($order['id']);

$pageMetaTitle = SITE_NAME . ' - Order Confirmed';
$pageTitle     = 'Order Confirmed';
$breadcrumbs   = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'Order Confirmation', 'url' => '']];
include 'includes/header.php';
?>
<main class="main__content_wrapper">
    <?php include 'includes/page-title.php'; ?>
    <section class="section--padding">
        <div class="container">
            <div class="text-center mb-40">
                <h2 style="color:#1e7e4f">&#10003; Thank you! Your order is confirmed.</h2>
                <p>Order number <strong><?php echo e($order['order_number']); ?></strong> &middot; placed <?php echo date('F j, Y', strtotime($order['created_at'])); ?></p>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div style="background:#f7f7f7;border-radius:10px;padding:24px">
                        <h4 class="mb-15">Items</h4>
                        <table class="cart__table--inner" style="width:100%">
                            <tbody>
                                <?php foreach ($items as $it): ?>
                                    <tr><td><?php echo e($it['product_name']); ?> &times; <?php echo $it['quantity']; ?></td><td class="text-right"><?php echo formatPrice($it['subtotal']); ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr><td>Subtotal</td><td class="text-right"><?php echo formatPrice($order['subtotal']); ?></td></tr>
                                <?php if ($order['discount_amount'] > 0): ?><tr><td>Discount</td><td class="text-right">- <?php echo formatPrice($order['discount_amount']); ?></td></tr><?php endif; ?>
                                <tr><td>Shipping</td><td class="text-right"><?php echo $order['shipping_amount'] > 0 ? formatPrice($order['shipping_amount']) : 'Free'; ?></td></tr>
                                <tr><td><strong>Total</strong></td><td class="text-right"><strong><?php echo formatPrice($order['total_amount']); ?></strong></td></tr>
                            </tfoot>
                        </table>
                        <h4 class="mt-25 mb-10">Delivery Address</h4>
                        <p><?php echo e($order['shipping_name']); ?><br><?php echo e($order['shipping_phone']); ?><br><?php echo nl2br(e($order['shipping_address'])); ?></p>
                        <h4 class="mt-20 mb-10">Payment</h4>
                        <p><?php echo strtoupper($order['payment_method']); ?> &middot; <?php echo ucfirst($order['payment_status']); ?></p>
                        <a href="account.php?tab=orders" class="primary__btn mt-15">View My Orders</a>
                        <a href="products.php" class="primary__btn mt-15">Continue Shopping</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
<?php include 'includes/footer.php'; ?>
