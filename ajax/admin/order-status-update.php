<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/orders.php';

$orderId = (int) ($_POST['order_id'] ?? 0);
$status  = $_POST['status'] ?? '';

if (!in_array($status, ORDER_STATUSES, true)) {
    json_response(false, 'Invalid status.');
}
if (!getOrder($orderId)) {
    json_response(false, 'Order not found.');
}
if (!updateOrderStatus($orderId, $status)) {
    json_response(false, 'Could not update the order.');
}
$order = getOrder($orderId);
json_response(true, 'Order status updated to ' . $status . '.', [
    'order_status'   => $order['order_status'],
    'payment_status' => $order['payment_status'],
]);
