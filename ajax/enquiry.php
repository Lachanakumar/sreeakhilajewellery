<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/engagement.php';
require_once __DIR__ . '/../includes/mailer.php';

require_post();
csrf_verify_or_fail();

$name  = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');
$productId = (int) ($_POST['product_id'] ?? 0) ?: null;
$schemeId  = (int) ($_POST['scheme_id'] ?? 0) ?: null;
$subject   = trim($_POST['subject'] ?? '');

if ($name === '' || strlen(preg_replace('/\D/', '', $phone)) < 10) {
    json_response(false, 'Please provide your name and a valid mobile number.');
}
if ($productId && !getProduct($productId)) {
    $productId = null;
}

createEnquiry([
    'product_id' => $productId,
    'scheme_id'  => $schemeId,
    'user_id'    => isLoggedIn() ? auth_user_id() : null,
    'name'       => $name,
    'email'      => $email ?: null,
    'phone'      => $phone,
    'subject'    => $subject ?: null,
    'message'    => $message ?: null,
]);

send_notification('enquiry', 'New enquiry from ' . $name, [
    'Name'    => $name,
    'Phone'   => $phone,
    'Email'   => $email,
    'Subject' => $subject,
    'Product' => $productId ? ('#' . $productId) : '',
    'Scheme'  => $schemeId ? ('#' . $schemeId) : '',
    'Message' => $message,
], $email);

json_response(true, 'Thank you! Our team will contact you shortly.');
