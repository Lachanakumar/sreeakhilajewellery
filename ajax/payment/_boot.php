<?php
/**
 * Shared bootstrap for the storefront payment AJAX endpoints.
 * Every response is {"success":bool,"message":string,"data":{...}}.
 */

// A library, never an endpoint.
if (basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/orders.php';
require_once __DIR__ . '/../../includes/payments/manager.php';

/** All payment endpoints are POST + CSRF + logged-in. */
function pay_endpoint_guard(): array
{
    require_post();
    csrf_verify_or_fail();

    if (!isLoggedIn()) {
        json_response(false, 'Please log in to continue.', ['redirect' => 'login.php'], 401);
    }
    $user = currentUser();
    if (!$user) {
        json_response(false, 'Please log in to continue.', ['redirect' => 'login.php'], 401);
    }

    $warn = pay_environment_warning();
    if ($warn !== '') {
        json_response(false, $warn, [], 500);
    }
    return $user;
}

/**
 * Crude per-session throttle so a stuck button cannot hammer a gateway.
 * @param int $max attempts allowed inside $window seconds
 */
function pay_rate_limit(string $bucket, int $max = 12, int $window = 60): void
{
    $key = 'pay_rl_' . $bucket;
    $now = time();
    $hits = array_values(array_filter(
        (array) ($_SESSION[$key] ?? []),
        static fn($t) => ($now - (int) $t) < $window
    ));
    if (count($hits) >= $max) {
        json_response(false, 'Too many attempts. Please wait a moment and try again.', [], 429);
    }
    $hits[] = $now;
    $_SESSION[$key] = $hits;
}

/** Load an order the current user actually owns, or bail out. */
function pay_require_own_order(int $orderId, array $user): array
{
    $order = getOrder($orderId, (int) $user['id']);
    if (!$order) {
        json_response(false, 'Order not found.', [], 404);
    }
    return $order;
}

/** Load the payment row and confirm it belongs to the order + user. */
function pay_require_own_payment(int $paymentId, array $user): array
{
    $payment = pay_get_payment($paymentId);
    if (!$payment) {
        json_response(false, 'Payment not found.', [], 404);
    }
    $order = getOrder((int) $payment['order_id'], (int) $user['id']);
    if (!$order) {
        json_response(false, 'Payment not found.', [], 404);
    }
    return [$payment, $order];
}
