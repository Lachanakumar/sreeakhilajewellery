<?php
/**
 * Return handler for redirect-style gateways.
 *
 * PhonePe always lands here; PayPal and Stripe land here only when the browser
 * had to leave the site (bank page / 3-D Secure). Nothing the browser sends is
 * trusted — the payment is re-verified against the gateway API before anything
 * is shown or fulfilled.
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/orders.php';
require_once 'includes/payments/manager.php';

requireLogin('account.php');
$user = currentUser();

$paymentId = (int) ($_REQUEST['payment'] ?? $_REQUEST['payment_id'] ?? 0);
$orderId   = (int) ($_REQUEST['order'] ?? $_REQUEST['order_id'] ?? 0);

$payment = $paymentId > 0 ? pay_get_payment($paymentId) : null;
if (!$payment && $orderId > 0) {
    $payment = pay_payment_for_order($orderId);
}

// Ownership: the order must belong to the logged-in customer.
$order = $payment ? getOrder((int) $payment['order_id'], (int) $user['id']) : null;
if (!$payment || !$order) {
    flash_set('error', 'We could not find that payment.');
    redirect('account.php');
}

/* ------------------------------------------------------------------
   Verify with the gateway (unless it is already settled).
   ------------------------------------------------------------------ */
$state   = $payment['status'];
$message = '';

if ($state === 'paid') {
    pay_finalize_order((int) $order['id']);
    redirect('order-confirmation.php?order=' . urlencode($order['order_number']));
}

if (in_array($state, ['pending', 'processing'], true)) {
    $result = pay_verify((string) $payment['gateway'], $payment, $_REQUEST);
    $payment = pay_get_payment((int) $payment['id']) ?: $payment;
    $order   = getOrder((int) $order['id'], (int) $user['id']);
    $state   = $result['ok'] ? 'paid' : (string) $result['status'];
    $message = (string) $result['message'];

    pay_log((string) $payment['gateway'], 'return.' . $state, [
        'order_id' => $order['id'], 'payment_id' => $payment['id'], 'status' => $state,
    ]);
}

if ($state === 'paid') {
    redirect('order-confirmation.php?order=' . urlencode($order['order_number']));
}

$pending = in_array($state, ['pending', 'processing'], true);

$gatewayLabel = pay_gateways()[$payment['gateway']]['label'] ?? ucfirst((string) $payment['gateway']);

$pageMetaTitle = SITE_NAME . ' - Payment Status';
$pageTitle     = $pending ? 'Confirming Your Payment' : 'Payment Not Completed';
$breadcrumbs   = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'Payment', 'url' => '']];
$pageStyles    = ['assets/css/payment.css'];
include 'includes/header.php';
?>
<main class="main__content_wrapper">
    <?php include 'includes/page-title.php'; ?>

    <section class="section--padding">
        <div class="container">
            <div class="payresult__wrap">
                <div class="payresult__card"
                     id="payReturn"
                     data-poll="<?php echo $pending ? '1' : '0'; ?>"
                     data-payment="<?php echo (int) $payment['id']; ?>">

                    <?php if ($pending): ?>
                        <div class="payresult__icon is-wait"><div class="pay__overlay--ring"></div></div>
                        <h2 class="payresult__title">Confirming your payment</h2>
                        <p class="payresult__text">
                            <?php echo e($message ?: 'Your payment is being confirmed by ' . $gatewayLabel . '. This usually takes a few seconds.'); ?>
                        </p>
                        <p class="payresult__text"><strong>Please do not close this page or pay again.</strong></p>
                    <?php elseif ($state === 'cancelled'): ?>
                        <div class="payresult__icon is-err">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </div>
                        <h2 class="payresult__title">Payment cancelled</h2>
                        <p class="payresult__text">You cancelled the payment at <?php echo e($gatewayLabel); ?>. <strong>Your order has not been charged.</strong></p>
                        <p class="payresult__text">Your items are still saved — you can complete the payment whenever you are ready.</p>
                    <?php else: ?>
                        <div class="payresult__icon is-err">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 8v5"/><circle cx="12" cy="16.5" r="1" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="9"/></svg>
                        </div>
                        <h2 class="payresult__title">Payment not completed</h2>
                        <p class="payresult__text"><?php echo e($message ?: pay_failure_message()); ?></p>
                    <?php endif; ?>

                    <ul class="payresult__meta">
                        <li><span>Order number</span><span><?php echo e($order['order_number']); ?></span></li>
                        <li><span>Amount</span><span><?php echo formatPrice($order['total_amount']); ?></span></li>
                        <li><span>Payment method</span><span><?php echo e($gatewayLabel); ?></span></li>
                        <?php if (!empty($payment['gateway_order_id'])): ?>
                            <li><span>Reference</span><span><?php echo e($payment['gateway_order_id']); ?></span></li>
                        <?php endif; ?>
                        <li><span>Status</span><span><?php echo e(ucfirst($state)); ?></span></li>
                    </ul>

                    <div class="payresult__actions">
                        <?php if (!$pending): ?>
                            <a class="primary__btn" href="checkout.php?retry=<?php echo (int) $order['id']; ?>">Try Payment Again</a>
                        <?php endif; ?>
                        <a class="pay__ghost--btn" href="order-details.php?order=<?php echo urlencode($order['order_number']); ?>">View Order</a>
                        <?php if (!$pending): ?>
                            <a class="pay__ghost--btn" href="contact.php">Need Help?</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php if ($pending): ?>
<script>
(function () {
    var card = document.getElementById('payReturn');
    if (!card || card.dataset.poll !== '1') { return; }

    var paymentId = card.dataset.payment;
    var token     = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var tries     = 0;
    var MAX       = 30; // 30 x 5s = 2.5 minutes

    function poll() {
        if (tries++ >= MAX) { return; }

        var body = new URLSearchParams();
        body.append('csrf_token', token);
        body.append('payment_id', paymentId);

        fetch('ajax/payment/status.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: body,
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            var d = (res && res.data) || {};
            if (d.redirect) { window.location.href = d.redirect; return; }
            if (d.settled)  { window.location.reload(); return; }
            setTimeout(poll, 5000);
        })
        .catch(function () { setTimeout(poll, 8000); });
    }

    setTimeout(poll, 4000);
})();
</script>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
