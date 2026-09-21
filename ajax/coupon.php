<?php
require_once __DIR__ . '/_bootstrap.php';

require_post();
csrf_verify_or_fail();

$op = $_POST['op'] ?? 'apply';

if ($op === 'remove') {
    removeCouponFromSession();
    json_response(true, 'Coupon removed.', cart_snapshot());
}

$code = trim($_POST['code'] ?? '');
if ($code === '') {
    json_response(false, 'Please enter a coupon code.');
}

[$ok, $msg] = applyCouponToSession($code);
json_response($ok, $msg, cart_snapshot());
