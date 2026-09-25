<?php
/**
 * Order placement + retrieval.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/cart-functions.php';
require_once __DIR__ . '/workflow.php';

const ORDER_STATUSES = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];

/**
 * Validate the cart against live stock/pricing. Returns [okBool, [errors], [lineItems]].
 */
function validateCartForCheckout() {
    $items = getCartItems();
    $errors = [];
    $lines = [];
    if (empty($items)) {
        return [false, ['Your cart is empty.'], []];
    }
    foreach ($items as $item) {
        $stock = $item['stock_quantity'];
        $variant = null;
        if ($item['variant_id']) {
            $variant = getVariant($item['variant_id']);
            $stock = $variant ? (int) $variant['stock_quantity'] : 0;
        }
        if (!$item['in_stock'] && !$variant) {
            $errors[] = $item['name'] . ' is out of stock.';
            continue;
        }
        if ($item['qty'] > $stock) {
            $errors[] = $item['name'] . ': only ' . $stock . ' in stock.';
            continue;
        }
        $lines[] = [
            'product_id'   => $item['id'],
            'variant_id'   => $item['variant_id'],
            'product_name' => $item['name'] . ($item['variant_label'] ? ' (' . $item['variant_label'] . ')' : ''),
            'sku'          => $item['sku'],
            'price'        => $item['unit_price'],
            'quantity'     => $item['qty'],
            'subtotal'     => $item['subtotal'],
        ];
    }
    return [empty($errors), $errors, $lines];
}

/**
 * Create an order for the logged-in user. Returns [okBool, orderIdOrErrorMessage].
 *
 * IMPORTANT: this NO LONGER reduces stock, consumes the coupon or clears the
 * cart. Those side effects are "fulfilment" and now happen exactly once, later:
 *   - online gateways -> pay_finalize_order() after server-side verification
 *   - cash on delivery -> pay_finalize_cod() straight after the order is made
 * (see includes/payments/manager.php). That is what lets a failed payment be
 * retried against the SAME order without double-charging inventory.
 *
 * @param string      $paymentMethod method label stored on the order (e.g. 'upi')
 * @param string|null $gateway       'cod' | 'razorpay' | 'stripe' | 'paypal' | 'phonepe'
 */
function placeOrder($userId, array $shipping, $paymentMethod = 'cod', $notes = '', $gateway = null) {
    [$ok, $errors, $lines] = validateCartForCheckout();
    if (!$ok) {
        return [false, implode(' ', $errors)];
    }

    $gateway  = $gateway ?: 'cod';
    $currency = function_exists('pay_currency') ? pay_currency() : 'INR';
    $totals   = cartTotals();
    $db       = getDB();

    $db->beginTransaction();
    try {
        $orderNumber = generateOrderNumber();
        $stmt = $db->prepare(
            'INSERT INTO orders
             (order_number, user_id, address_id, shipping_name, shipping_phone, shipping_address,
              subtotal, discount_amount, coupon_id, coupon_code, shipping_amount, total_amount, currency,
              payment_method, payment_gateway, payment_status, order_status, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $orderNumber,
            $userId,
            $shipping['address_id'] ?? null,
            $shipping['name'],
            $shipping['phone'],
            $shipping['address_text'],
            $totals['subtotal'],
            $totals['discount'],
            $totals['coupon']['id'] ?? null,
            $totals['coupon_code'],
            $totals['shipping'],
            $totals['total'],
            $currency,
            $paymentMethod,
            $gateway,
            'pending',
            'pending',
            $notes ?: null,
        ]);
        $orderId = (int) $db->lastInsertId();

        $itemStmt = $db->prepare(
            'INSERT INTO order_items (order_id, product_id, variant_id, product_name, sku, price, quantity, subtotal)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($lines as $line) {
            $itemStmt->execute([
                $orderId, $line['product_id'], $line['variant_id'], $line['product_name'],
                $line['sku'], $line['price'], $line['quantity'], $line['subtotal'],
            ]);
        }

        // Opening payment attempt. Stays 'pending' until the gateway confirms.
        $db->prepare(
            'INSERT INTO payments (order_id, user_id, gateway, method, amount, currency, payment_method, status, idempotency_key)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $orderId, $userId, $gateway, $gateway, $totals['total'], $currency,
            $paymentMethod, 'pending', bin2hex(random_bytes(16)),
        ]);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        return [false, 'We could not place your order. Please try again.'];
    }

    return [true, $orderId];
}

/**
 * Turn posted checkout fields into a shipping payload.
 * Shared by checkout.php and ajax/payment/create.php so the two can never drift.
 *
 * @return array{0: array|null, 1: string[]}  [shipping, errors]
 */
function resolveCheckoutShipping($userId, array $post) {
    $errors    = [];
    $addressId = (int) ($post['address_id'] ?? 0);

    if ($addressId > 0) {
        $addr = getAddress($addressId, $userId);
        if (!$addr) {
            return [null, ['Please choose a valid delivery address.']];
        }
        return [[
            'address_id'   => $addr['id'],
            'name'         => $addr['full_name'],
            'phone'        => $addr['phone'],
            'address_text' => formatAddressText($addr),
        ], []];
    }

    $required = ['full_name', 'phone', 'address_line1', 'city', 'state', 'postal_code'];
    $data = [];
    foreach ($required as $f) {
        $data[$f] = trim((string) ($post[$f] ?? ''));
        if ($data[$f] === '') {
            $errors[] = ucwords(str_replace('_', ' ', $f)) . ' is required.';
        }
    }
    if ($errors) {
        return [null, $errors];
    }

    $data['address_line2'] = trim((string) ($post['address_line2'] ?? ''));
    $data['country']       = trim((string) ($post['country'] ?? 'India'));
    $data['is_default']    = !empty($post['save_address']) && !empty($post['is_default']);

    $newId = !empty($post['save_address']) ? saveAddress($userId, $data) : null;

    return [[
        'address_id'   => $newId,
        'name'         => $data['full_name'],
        'phone'        => $data['phone'],
        'address_text' => $data['address_line1'] . "\n"
            . ($data['address_line2'] !== '' ? $data['address_line2'] . "\n" : '')
            . $data['city'] . ', ' . $data['state'] . ' ' . $data['postal_code'] . "\n" . $data['country'],
    ], []];
}

function getOrder($orderId, $userId = null) {
    $sql = 'SELECT * FROM orders WHERE id = ?';
    $params = [(int) $orderId];
    if ($userId !== null) {
        $sql .= ' AND user_id = ?';
        $params[] = (int) $userId;
    }
    $stmt = getDB()->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

function getOrderByNumber($number, $userId = null) {
    $sql = 'SELECT * FROM orders WHERE order_number = ?';
    $params = [$number];
    if ($userId !== null) {
        $sql .= ' AND user_id = ?';
        $params[] = (int) $userId;
    }
    $stmt = getDB()->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

function getOrderItems($orderId) {
    // Pull the product's primary image alongside each line so order views can
    // show a thumbnail. LEFT JOIN so deleted products still list their line.
    $stmt = getDB()->prepare(
        'SELECT oi.*,
                (SELECT pi.image_path FROM product_images pi
                  WHERE pi.product_id = oi.product_id
                  ORDER BY pi.is_primary DESC, pi.sort_order LIMIT 1) AS image,
                p.slug AS product_slug,
                p.status AS product_status
           FROM order_items oi
           LEFT JOIN products p ON p.id = oi.product_id
          WHERE oi.order_id = ?'
    );
    $stmt->execute([(int) $orderId]);
    return $stmt->fetchAll();
}

function getUserOrders($userId) {
    $stmt = getDB()->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([(int) $userId]);
    return $stmt->fetchAll();
}

function updateOrderStatus($orderId, $status) {
    if (!in_array($status, ORDER_STATUSES, true)) {
        return false;
    }
    $db = getDB();
    $order = getOrder($orderId);
    if (!$order) {
        return false;
    }
    /* Forward only. The dropdown no longer offers a backward step, but the
       bulk-action bar and any hand-made POST reach this same function, so the
       rule is enforced here rather than in the markup. See includes/workflow.php. */
    if (!workflow_allows('order', $order['order_status'], $status)) {
        return false;
    }
    $db->prepare('UPDATE orders SET order_status = ? WHERE id = ?')->execute([$status, (int) $orderId]);

    // Restock on cancellation/refund — but ONLY if this order was ever fulfilled.
    // Unpaid/abandoned orders never took stock, so returning it would inflate it.
    // Clearing fulfilled_at makes the restock itself idempotent.
    if (in_array($status, ['cancelled', 'refunded'], true)
        && !in_array($order['order_status'], ['cancelled', 'refunded'], true)
        && !empty($order['fulfilled_at'])) {

        $release = $db->prepare('UPDATE orders SET fulfilled_at = NULL WHERE id = ? AND fulfilled_at IS NOT NULL');
        $release->execute([(int) $orderId]);

        if ($release->rowCount() > 0) {
            foreach (getOrderItems($orderId) as $item) {
                if ($item['product_id']) {
                    $db->prepare('UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?')
                       ->execute([$item['quantity'], $item['product_id']]);
                }
                if ($item['variant_id']) {
                    $db->prepare('UPDATE product_variants SET stock_quantity = stock_quantity + ? WHERE id = ?')
                       ->execute([$item['quantity'], $item['variant_id']]);
                }
            }
        }
    }
    if ($status === 'refunded') {
        $db->prepare('UPDATE orders SET payment_status = "refunded" WHERE id = ?')->execute([(int) $orderId]);
    }

    /* Cancelling only moved order_status, so a cancelled order kept reporting
       its payment as "Pending" — as if money were still expected. Money that
       was actually taken stays 'paid' (a refund is its own action); anything
       still open is cancelled along with the order. */
    if ($status === 'cancelled') {
        $db->prepare("UPDATE orders SET payment_status = 'cancelled'
                       WHERE id = ? AND payment_status NOT IN ('paid', 'refunded', 'partially_refunded')")
           ->execute([(int) $orderId]);
        $db->prepare("UPDATE payments SET status = 'cancelled',
                             failure_reason = COALESCE(failure_reason, 'Order cancelled')
                       WHERE order_id = ? AND status IN ('pending', 'processing')")
           ->execute([(int) $orderId]);
    }
    // Cash collected on the doorstep — the COD order was already fulfilled when
    // it was placed, so this only records that the money actually arrived.
    if ($status === 'delivered' && ($order['payment_gateway'] ?? $order['payment_method']) === 'cod') {
        $db->prepare('UPDATE orders SET payment_status = "paid" WHERE id = ?')->execute([(int) $orderId]);
        $db->prepare('UPDATE payments SET status = "paid", paid_at = COALESCE(paid_at, NOW()) WHERE order_id = ? AND status <> "paid"')
           ->execute([(int) $orderId]);
    }
    return true;
}

/* ==================== STATUS LABELS ==================== */

/** True when this order is settled in cash on delivery rather than online. */
function order_is_cod(array $order) {
    return strtolower((string) ($order['payment_gateway'] ?: $order['payment_method'])) === 'cod';
}

/**
 * How an order's payment method should read.
 *
 * For COD both payment_method and payment_gateway hold 'cod', and pasting them
 * together produced "COD via Cash on Delivery" — the same fact said twice.
 * COD is a single choice, so it gets a single name; an online payment keeps the
 * useful pairing of what was used and who processed it ("UPI via Razorpay").
 */
function payment_method_label(array $order) {
    if (order_is_cod($order)) {
        return 'Cash on Delivery (COD)';
    }

    $method   = strtolower((string) ($order['payment_method'] ?? ''));
    $gateway  = strtolower((string) ($order['payment_gateway'] ?? ''));
    // config/payment.php is not loaded on every page that shows an order
    $gateways = function_exists('pay_gateways') ? pay_gateways() : [];

    $methodLabel = '';
    foreach ($gateways[$gateway]['methods'] ?? [] as $m) {
        if ($m['code'] === $method) {
            $methodLabel = $m['label'];
            break;
        }
    }
    if ($methodLabel === '') {
        $methodLabel = $method !== '' ? strtoupper($method) : 'Online payment';
    }

    $gatewayLabel = $gateways[$gateway]['label'] ?? ($gateway !== '' ? ucfirst($gateway) : '');
    if ($gatewayLabel === '' || strcasecmp($gatewayLabel, $methodLabel) === 0) {
        return $methodLabel;
    }
    return $methodLabel . ' via ' . $gatewayLabel;
}

/**
 * GST charged on an order.
 *
 * The orders table has no tax column — tax was folded into total_amount when
 * the order was placed — so it is whatever the total holds over and above the
 * line items, less the discount, plus shipping. Clamped at zero so a rounding
 * artefact can never render as a negative tax line.
 */
function order_tax_amount(array $order) {
    $base = (float) $order['subtotal']
          - (float) $order['discount_amount']
          + (float) $order['shipping_amount'];
    return round(max(0, (float) $order['total_amount'] - $base), 2);
}

/**
 * The rate that amount works out to, derived from the order itself rather than
 * read from the live tax_percent setting: an order placed at 3% keeps showing
 * 3% after the setting is changed to 5%.
 */
function order_tax_percent(array $order) {
    $taxable = (float) $order['subtotal'] - (float) $order['discount_amount'];
    $tax     = order_tax_amount($order);
    if ($taxable <= 0 || $tax <= 0) {
        return 0.0;
    }
    return round($tax / $taxable * 100, 2);
}

/**
 * What the customer should read for an order's payment state.
 *
 * Two things this fixes:
 *  - COD orders were labelled "Failed" after any earlier online attempt, which
 *    reads as a failed cash-on-delivery process. Cash owed on the doorstep is
 *    "Pending" / "In Progress", never failed.
 *  - "Pending" on a cancelled order implied money was still expected.
 *
 * @return array{label: string, tone: string, note: string}
 */
function payment_status_label(array $order) {
    $pay   = strtolower((string) $order['payment_status']);
    $order_status = strtolower((string) $order['order_status']);
    $isCod = order_is_cod($order);

    if (in_array($order_status, ['cancelled', 'refunded'], true) && $pay !== 'paid') {
        return [
            'label' => $pay === 'refunded' ? 'Refunded' : 'Cancelled',
            'tone'  => $pay === 'refunded' ? 'warn' : 'bad',
            'note'  => $pay === 'refunded'
                ? 'The amount has been returned to your original payment method. Your bank may take a few working days to show it.'
                : 'No payment was taken for this order.',
        ];
    }

    if ($isCod) {
        switch ($pay) {
            case 'paid':
                return ['label' => 'Paid on delivery', 'tone' => 'ok', 'note' => ''];
            case 'refunded':
                return ['label' => 'Refunded', 'tone' => 'warn', 'note' => ''];
            default:
                // pending / processing / failed / cancelled all mean the same
                // thing for COD: the cash has not been collected yet.
                return [
                    'label' => 'Pending (Cash on Delivery)',
                    'tone'  => 'warn',
                    'note'  => 'Please keep the amount ready for the delivery agent.',
                ];
        }
    }

    switch ($pay) {
        case 'paid':       return ['label' => 'Paid', 'tone' => 'ok', 'note' => ''];
        case 'processing': return ['label' => 'In Progress', 'tone' => 'warn', 'note' => 'Your bank is still confirming this payment.'];
        /* A refund is shown from the moment the gateway accepts it, which is
           ahead of the money actually landing — say so rather than leaving the
           customer to wonder why their statement disagrees. */
        case 'refunded':   return ['label' => 'Refunded', 'tone' => 'warn', 'note' => 'The amount has been returned to your original payment method. Your bank may take a few working days to show it.'];
        case 'partially_refunded': return ['label' => 'Partially refunded', 'tone' => 'warn', 'note' => 'Part of this order has been refunded to your original payment method.'];
        case 'failed':     return ['label' => 'Payment failed', 'tone' => 'bad', 'note' => 'No money was taken. You can retry the payment below.'];
        case 'cancelled':  return ['label' => 'Payment cancelled', 'tone' => 'bad', 'note' => 'No money was taken. You can retry the payment below.'];
        default:           return ['label' => 'Awaiting payment', 'tone' => 'warn', 'note' => 'We have not received a payment for this order yet.'];
    }
}

/**
 * An order the customer started paying for online, where the payment never
 * completed and nothing was ever fulfilled. It exists only so the gateway had
 * something to quote — it is not a placed order and should not be presented
 * as one.
 */
function order_is_abandoned_payment(array $order) {
    return empty($order['fulfilled_at'])
        && !order_is_cod($order)
        && in_array(strtolower((string) $order['payment_status']), ['failed', 'cancelled', 'pending'], true)
        && strtolower((string) $order['order_status']) === 'pending';
}

/* ==================== ADDRESSES ==================== */

function getUserAddresses($userId) {
    $stmt = getDB()->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC');
    $stmt->execute([(int) $userId]);
    return $stmt->fetchAll();
}

function getAddress($id, $userId = null) {
    $sql = 'SELECT * FROM addresses WHERE id = ?';
    $params = [(int) $id];
    if ($userId !== null) {
        $sql .= ' AND user_id = ?';
        $params[] = (int) $userId;
    }
    $stmt = getDB()->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

function saveAddress($userId, array $data, $addressId = null) {
    $db = getDB();
    $fields = [
        $data['full_name'], $data['phone'], $data['address_line1'], $data['address_line2'] ?: null,
        $data['city'], $data['state'], $data['postal_code'], $data['country'] ?: 'India',
        !empty($data['is_default']) ? 1 : 0,
    ];
    if (!empty($data['is_default'])) {
        $db->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?')->execute([(int) $userId]);
    }
    if ($addressId) {
        $stmt = $db->prepare('UPDATE addresses SET full_name=?, phone=?, address_line1=?, address_line2=?, city=?, state=?, postal_code=?, country=?, is_default=? WHERE id=? AND user_id=?');
        $stmt->execute(array_merge($fields, [(int) $addressId, (int) $userId]));
        return (int) $addressId;
    }
    $stmt = $db->prepare('INSERT INTO addresses (user_id, full_name, phone, address_line1, address_line2, city, state, postal_code, country, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute(array_merge([(int) $userId], $fields));
    return (int) $db->lastInsertId();
}

function deleteAddress($id, $userId) {
    getDB()->prepare('DELETE FROM addresses WHERE id = ? AND user_id = ?')->execute([(int) $id, (int) $userId]);
}

function formatAddressText(array $a) {
    $parts = array_filter([
        $a['address_line1'], $a['address_line2'],
        $a['city'] . ', ' . $a['state'] . ' ' . $a['postal_code'],
        $a['country'],
    ]);
    return implode("\n", $parts);
}
