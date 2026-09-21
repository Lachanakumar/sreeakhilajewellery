<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/engagement.php';
require_once __DIR__ . '/../includes/mailer.php';

require_post();
csrf_verify_or_fail();

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$category = $_POST['category'] ?? 'other';
$rating = (int) ($_POST['rating'] ?? 0);
$message = trim($_POST['message'] ?? '');

if ($name === '' || $message === '') {
    json_response(false, 'Please enter your name and your feedback.');
}

createFeedback([
    'user_id'  => isLoggedIn() ? auth_user_id() : null,
    'name'     => $name,
    'email'    => $email ?: null,
    'phone'    => $phone ?: null,
    'category' => $category,
    'rating'   => $rating ?: null,
    'message'  => $message,
]);

send_notification('feedback', 'New customer feedback from ' . $name, [
    'Name'     => $name,
    'Phone'    => $phone,
    'Email'    => $email,
    'Category' => $category,
    'Rating'   => $rating ? $rating . ' / 5' : 'not given',
    'Feedback' => $message,
], $email);

json_response(true, 'Thank you for your feedback!');
