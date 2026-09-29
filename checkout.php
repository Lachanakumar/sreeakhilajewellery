<?php
require_once 'includes/config.php';
require_once 'includes/products.php';
require_once 'includes/cart-functions.php';
require_once 'includes/auth.php';
require_once 'includes/orders.php';
require_once 'includes/payments/manager.php';

requireLogin('checkout.php');
$user = currentUser();

$addresses = getUserAddresses($user['id']);
$csrf      = csrf_token();
$errors    = [];

/* ==================================================================
   Retry mode — ?retry=<order id> re-opens an unpaid order so the
   customer can pick another method without re-entering the address.
   ================================================================== */
$retryOrder = null;
$retryId    = (int) ($_GET['retry'] ?? 0);
if ($retryId > 0) {
    $retryOrder = getOrder($retryId, (int) $user['id']);
    if (!$retryOrder) {
        flash_set('error', 'We could not find that order.');
        redirect('account.php?tab=orders');
    }
    if ($retryOrder['payment_status'] === 'paid') {
        redirect('order-confirmation.php?order=' . urlencode($retryOrder['order_number']));
    }
    if (in_array($retryOrder['order_status'], ['cancelled', 'refunded'], true)) {
        flash_set('error', 'That order can no longer be paid.');
        redirect('order-details.php?order=' . urlencode($retryOrder['order_number']));
    }
}

// NB: includes/header.php later reassigns $cartItems for the mini-cart, so the
// order summary keeps its own variable.
$summaryItems = $retryOrder ? getOrderItems((int) $retryOrder['id']) : getCartItems();
$totals     = cartTotals();
$grandTotal = $retryOrder ? (float) $retryOrder['total_amount'] : (float) $totals['total'];

/* ==================================================================
   Gateways available right now (enabled + credentials + currency).
   ================================================================== */
$gateways    = pay_available_gateways();
$payCurrency = pay_currency();
$payMode     = pay_mode();
$envWarning  = pay_environment_warning();

// Cash on Delivery is the store's default choice whenever it is switched on;
// otherwise the first online gateway is pre-selected.
$defaultGateway = isset($gateways['cod']) ? 'cod' : ($gateways ? array_key_first($gateways) : '');

/* ==================================================================
   No-JavaScript fallback: cash on delivery only. Online gateways all
   need their SDKs, so they are never offered without JS.
   ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order_nojs'])) {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (!isset($gateways['cod'])) {
        $errors[] = 'Cash on delivery is not available at the moment.';
    } else {
        $orderId = $retryOrder ? (int) $retryOrder['id'] : 0;

        if (!$orderId) {
            [$shipping, $shipErrors] = resolveCheckoutShipping((int) $user['id'], $_POST);
            $errors = array_merge($errors, $shipErrors);
            if (!$errors) {
                $notes = mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 500);
                [$created, $result] = placeOrder((int) $user['id'], $shipping, 'cod', $notes, 'cod');
                if ($created) {
                    $orderId = (int) $result;
                } else {
                    $errors[] = (string) $result;
                }
            }
        }

        if ($orderId && !$errors) {
            /* Mirrors the COD branch of ajax/payment/create.php. This path only
               moved the gateway columns, so retrying as COD after an online
               attempt failed left payment_status = 'failed' on the order: a
               perfectly good cash-on-delivery order presented as a failed
               payment. Close the old attempt and reset the order to 'pending',
               which for COD means "to collect on delivery", then record the COD
               attempt the order is actually settled against. */
            $db = getDB();
            $db->prepare(
                "UPDATE payments SET status = 'cancelled',
                        failure_reason = COALESCE(failure_reason, 'Superseded by cash on delivery')
                  WHERE order_id = ? AND status <> 'paid'"
            )->execute([$orderId]);

            $db->prepare(
                "UPDATE orders
                    SET payment_gateway = 'cod', payment_method = 'cod', payment_status = 'pending'
                  WHERE id = ? AND payment_status <> 'paid'"
            )->execute([$orderId]);

            $codOrder = getOrder($orderId, (int) $user['id']);
            $db->prepare(
                'INSERT INTO payments (order_id, user_id, gateway, method, amount, currency, payment_method, status, idempotency_key)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $orderId, (int) $user['id'], 'cod', 'cod',
                $codOrder['total_amount'], strtoupper($codOrder['currency'] ?? 'INR'),
                'cod', 'pending', bin2hex(random_bytes(16)),
            ]);

            pay_finalize_cod($orderId);
            $placed = getOrder($orderId, (int) $user['id']);
            redirect('order-confirmation.php?order=' . urlencode($placed['order_number']));
        }
    }
}

/* Public gateway config for the browser — publishable ids only, no secrets. */
$payConfig = [
    'csrf'      => $csrf,
    'currency'  => $payCurrency,
    'mode'      => $payMode,
    'order_id'  => $retryOrder ? (int) $retryOrder['id'] : 0,
    'amount'    => $grandTotal,
    'gateways'  => [],
];
foreach ($gateways as $key => $meta) {
    $payConfig['gateways'][$key] = [
        'flow'   => $meta['flow'],
        'public' => pay_public_config($key),
    ];
}

$pageMetaTitle = SITE_NAME . ' - Checkout';
$pageTitle     = $retryOrder ? 'Complete Your Payment' : 'Checkout';
$breadcrumbs   = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'Cart', 'url' => 'cart.php'], ['name' => 'Checkout', 'url' => '']];
$pageStyles    = ['assets/css/payment.css'];
$pageScripts   = ['assets/js/checkout-payment.js'];
include 'includes/header.php';
?>
<main class="main__content_wrapper">
    <?php include 'includes/page-title.php'; ?>

    <section class="checkout__section section--padding">
        <div class="container-fluid">
            <?php if (!$retryOrder && empty($summaryItems)): ?>
                <div class="text-center py-5">
                    <h3>Your cart is empty</h3>
                    <a href="products.php" class="primary__btn mt-3">Browse Products</a>
                </div>
            <?php else: ?>
                <?php if ($errors): ?>
                    <div class="flash__bar is-error" style="border-radius:6px;margin-bottom:20px">
                        <?php echo implode('<br>', array_map('e', $errors)); ?>
                    </div>
                <?php endif; ?>

                <?php if ($envWarning !== ''): ?>
                    <div class="flash__bar is-error" style="border-radius:6px;margin-bottom:20px">
                        <?php echo e($envWarning); ?> Online payments are disabled until this is fixed.
                    </div>
                <?php endif; ?>

                <form action="checkout.php<?php echo $retryOrder ? '?retry=' . (int) $retryOrder['id'] : ''; ?>" method="post" id="checkoutForm" data-validate>
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                    <?php if ($retryOrder): ?>
                        <input type="hidden" name="order_id" value="<?php echo (int) $retryOrder['id']; ?>">
                    <?php endif; ?>

                    <div class="checkout__page--inner d-flex">
                        <div class="checkout__mian">
                            <div class="checkout__content--step section__shipping--address">
                                <div class="section__header mb-25"><h2 class="section__header--title h3">Delivery Address</h2></div>

                                <?php if ($retryOrder): ?>
                                    <div class="pay__gateway" style="cursor:default">
                                        <div class="pay__gateway--head">
                                            <div class="pay__gateway--body">
                                                <span class="pay__gateway--label"><?php echo e($retryOrder['shipping_name']); ?> &middot; <?php echo e($retryOrder['shipping_phone']); ?></span>
                                                <span class="pay__gateway--blurb"><?php echo nl2br(e($retryOrder['shipping_address'])); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="mt-3" style="font-size:1.25rem;color:#7d7469">
                                        Order <strong><?php echo e($retryOrder['order_number']); ?></strong> is waiting for payment.
                                        Nothing has been charged yet.
                                    </p>
                                <?php else: ?>
                                    <?php if ($addresses): ?>
                                        <div class="row mb-20">
                                            <?php foreach ($addresses as $i => $addr): ?>
                                                <div class="col-md-6 mb-15">
                                                    <label class="checkout__input--list d-block" style="border:1px solid #ddd;padding:14px;border-radius:8px;cursor:pointer">
                                                        <input type="radio" name="address_id" value="<?php echo (int) $addr['id']; ?>" <?php echo ($i === 0) ? 'checked' : ''; ?>>
                                                        <strong><?php echo e($addr['full_name']); ?></strong> &middot; <?php echo e($addr['phone']); ?><br>
                                                        <?php echo nl2br(e(formatAddressText($addr))); ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                            <div class="col-md-6 mb-15">
                                                <label class="checkout__input--list d-block" style="border:1px solid #ddd;padding:14px;border-radius:8px;cursor:pointer">
                                                    <input type="radio" name="address_id" value="0"> Use a new address
                                                </label>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="section__shipping--address__content" <?php echo $addresses ? 'id="newAddressBlock"' : ''; ?>>
                                        <div class="row">
                                            <div class="col-lg-6 col-md-6 mb-20"><div class="checkout__input--list"><label class="checkout__input--label">Full Name <span class="checkout__input--label__star">*</span></label><input class="checkout__input--field border-radius-5" name="full_name" value="<?php echo e($user['name']); ?>" type="text"></div></div>
                                            <div class="col-lg-6 col-md-6 mb-20"><div class="checkout__input--list"><label class="checkout__input--label">Phone <span class="checkout__input--label__star">*</span></label><input class="checkout__input--field border-radius-5" name="phone" value="<?php echo e($user['phone']); ?>" type="tel"></div></div>
                                            <div class="col-12 mb-20"><div class="checkout__input--list"><label class="checkout__input--label">Address line 1 <span class="checkout__input--label__star">*</span></label><input class="checkout__input--field border-radius-5" name="address_line1" type="text"></div></div>
                                            <div class="col-12 mb-20"><div class="checkout__input--list"><label class="checkout__input--label">Address line 2</label><input class="checkout__input--field border-radius-5" name="address_line2" type="text"></div></div>
                                            <div class="col-lg-4 col-md-6 mb-20"><div class="checkout__input--list"><label class="checkout__input--label">City <span class="checkout__input--label__star">*</span></label><input class="checkout__input--field border-radius-5" name="city" type="text"></div></div>
                                            <div class="col-lg-4 col-md-6 mb-20"><div class="checkout__input--list"><label class="checkout__input--label">State <span class="checkout__input--label__star">*</span></label><input class="checkout__input--field border-radius-5" name="state" type="text"></div></div>
                                            <div class="col-lg-4 col-md-6 mb-20"><div class="checkout__input--list"><label class="checkout__input--label">Postcode / ZIP <span class="checkout__input--label__star">*</span></label><input class="checkout__input--field border-radius-5" name="postal_code" type="text"></div></div>
                                            <div class="col-lg-4 col-md-6 mb-20"><div class="checkout__input--list"><label class="checkout__input--label">Country</label><input class="checkout__input--field border-radius-5" name="country" value="India" type="text"></div></div>
                                            <div class="col-12 mb-10"><label><input type="checkbox" name="save_address" value="1" checked> Save this address to my account</label></div>
                                            <div class="col-12 mb-20"><label><input type="checkbox" name="is_default" value="1"> Set as default address</label></div>
                                            <div class="col-12 mb-20"><div class="checkout__input--list"><label class="checkout__input--label">Order Notes (optional)</label><textarea class="checkout__input--field border-radius-5 checkout__input--textarea" name="notes"></textarea></div></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <aside class="checkout__sidebar">
                            <h3 class="checkout__title h4 mb-20">Your Order</h3>
                            <div class="checkout__order--summary" style="background:#f7f7f7; padding:20px; border-radius:10px;">
                                <table class="cart__table--inner" style="width:100%;">
                                    <thead><tr><th>Product</th><th class="text-right">Total</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($summaryItems as $item): ?>
                                            <tr>
                                                <td><?php echo e($retryOrder ? $item['product_name'] : $item['name']); ?> &times; <?php echo (int) ($retryOrder ? $item['quantity'] : $item['qty']); ?></td>
                                                <td class="text-right"><?php echo formatPrice($item['subtotal']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <?php if ($retryOrder): ?>
                                            <tr><td>Subtotal</td><td class="text-right"><?php echo formatPrice($retryOrder['subtotal']); ?></td></tr>
                                            <?php if ((float) $retryOrder['discount_amount'] > 0): ?>
                                                <tr><td>Discount<?php echo $retryOrder['coupon_code'] ? ' (' . e($retryOrder['coupon_code']) . ')' : ''; ?></td><td class="text-right">- <?php echo formatPrice($retryOrder['discount_amount']); ?></td></tr>
                                            <?php endif; ?>
                                            <tr><td>Shipping</td><td class="text-right"><?php echo (float) $retryOrder['shipping_amount'] > 0 ? formatPrice($retryOrder['shipping_amount']) : 'Free'; ?></td></tr>
                                        <?php else: ?>
                                            <tr><td>Subtotal</td><td class="text-right"><?php echo formatPrice($totals['subtotal']); ?></td></tr>
                                            <?php if ($totals['discount'] > 0): ?><tr><td>Discount (<?php echo e($totals['coupon_code']); ?>)</td><td class="text-right">- <?php echo formatPrice($totals['discount']); ?></td></tr><?php endif; ?>
                                            <tr><td>Shipping</td><td class="text-right"><?php echo $totals['shipping'] > 0 ? formatPrice($totals['shipping']) : 'Free'; ?></td></tr>
                                            <?php if ($totals['tax'] > 0): ?><tr><td>GST (<?php echo (float) $totals['tax_percent']; ?>%)</td><td class="text-right"><?php echo formatPrice($totals['tax']); ?></td></tr><?php endif; ?>
                                        <?php endif; ?>
                                        <tr><td><strong>Total</strong></td><td class="text-right"><strong><?php echo formatPrice($grandTotal); ?></strong></td></tr>
                                    </tfoot>
                                </table>

                                <!-- ============ Payment ============ -->
                                <div class="checkout__payment mt-20">
                                    <h4 class="mb-10">Payment Method</h4>

                                    <div class="pay__secure">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                        </svg>
                                        <span>Secure payment. Card and UPI details are entered on the gateway&rsquo;s own page &mdash; we never see or store them.</span>
                                        <?php if ($payMode === 'TEST'): ?><span class="pay__secure--mode">Test mode</span><?php endif; ?>
                                    </div>

                                    <?php if (!$gateways): ?>
                                        <div class="pay__alert is-shown is-error">
                                            No payment method is available right now. Please contact us to complete your order.
                                        </div>
                                    <?php else: ?>
                                        <div class="pay__gateways" id="payGateways">
                                            <?php foreach ($gateways as $key => $meta):
                                                $active = ($key === $defaultGateway);
                                                $blocked = ($key !== 'cod' && $envWarning !== '');
                                                if ($blocked) { continue; }
                                            ?>
                                                <div class="pay__gateway<?php echo $active ? ' is-active' : ''; ?>" data-gateway="<?php echo e($key); ?>" data-flow="<?php echo e($meta['flow']); ?>">
                                                    <label class="pay__gateway--head">
                                                        <input class="pay__gateway--radio" type="radio" name="gateway" value="<?php echo e($key); ?>" <?php echo $active ? 'checked' : ''; ?>>
                                                        <span class="pay__gateway--body">
                                                            <span class="pay__gateway--label">
                                                                <?php echo e($meta['label']); ?>
                                                                <?php if ($meta['flow'] === 'redirect'): ?><span class="pay__gateway--tag">Redirect</span><?php endif; ?>
                                                            </span>
                                                            <span class="pay__gateway--blurb"><?php echo e($meta['blurb']); ?></span>
                                                        </span>
                                                    </label>

                                                    <?php if (count($meta['methods']) > 1): ?>
                                                        <div class="pay__methods">
                                                            <?php foreach ($meta['methods'] as $mi => $m): ?>
                                                                <label class="pay__method">
                                                                    <input type="radio" name="method_<?php echo e($key); ?>" value="<?php echo e($m['code']); ?>" <?php echo $mi === 0 ? 'checked' : ''; ?>>
                                                                    <span><?php echo e($m['label']); ?></span>
                                                                </label>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <input type="hidden" name="method_<?php echo e($key); ?>" value="<?php echo e($meta['methods'][0]['code']); ?>">
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <!-- Stripe Payment Element mounts here -->
                                        <div class="pay__stripe" id="payStripeMount"></div>
                                        <!-- PayPal buttons render here -->
                                        <div class="pay__paypal" id="payPaypalMount"></div>

                                        <div class="pay__alert" id="payAlert" role="alert" aria-live="polite"></div>

                                        <button type="submit" name="place_order" value="1" class="primary__btn pay__submit" id="paySubmit">
                                            <span class="pay__submit--label">Place Order &middot; <?php echo formatPrice($grandTotal); ?></span>
                                        </button>

                                        <noscript>
                                            <p style="font-size:1.2rem;color:#b23b30;margin-top:10px">
                                                Online payment needs JavaScript. You can still place a cash-on-delivery order below.
                                            </p>
                                            <?php if (isset($gateways['cod'])): ?>
                                                <button type="submit" name="place_order_nojs" value="1" class="primary__btn" style="width:100%;margin-top:10px">
                                                    Place Cash on Delivery Order
                                                </button>
                                            <?php endif; ?>
                                        </noscript>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </aside>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </section>
</main>

<!-- Processing overlay -->
<div class="pay__overlay" id="payOverlay" aria-hidden="true">
    <div class="pay__overlay--card">
        <div class="pay__overlay--ring"></div>
        <h3 class="pay__overlay--title" id="payOverlayTitle">Processing your payment</h3>
        <p class="pay__overlay--text" id="payOverlayText">Please wait while we confirm this with your bank.</p>
        <p class="pay__overlay--note">Do not refresh or close this page.</p>
    </div>
</div>

<script type="application/json" id="payConfig"><?php echo json_encode($payConfig, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>

<?php if (!$retryOrder && $addresses): ?>
<script>
(function () {
    var block = document.getElementById('newAddressBlock');
    if (!block) { return; }
    function sync() {
        var sel = document.querySelector('input[name="address_id"]:checked');
        block.style.display = (sel && sel.value === '0') ? '' : 'none';
    }
    document.querySelectorAll('input[name="address_id"]').forEach(function (r) { r.addEventListener('change', sync); });
    sync();
})();
</script>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
