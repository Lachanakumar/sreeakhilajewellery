<?php
require_once __DIR__ . '/_bootstrap.php';

require_post();
csrf_verify_or_fail();

if (!isLoggedIn()) {
    json_response(false, 'Please log in to write a review.', [], 401);
}

$productId = (int) ($_POST['product_id'] ?? 0);
$rating    = (int) ($_POST['rating'] ?? 0);
$title     = trim($_POST['title'] ?? '');
$comment   = trim($_POST['comment'] ?? '');

$product = getProduct($productId);
if (!$product) {
    json_response(false, 'Invalid product.');
}
if ($rating < 1 || $rating > 5) {
    json_response(false, 'Please select a rating between 1 and 5 stars.');
}
if ($comment === '') {
    json_response(false, 'Please write a short review.');
}
if (!userCanReview($productId, auth_user_id())) {
    json_response(false, 'You have already reviewed this product.');
}

addReview($productId, auth_user_id(), $rating, $title, $comment);

json_response(true, 'Thank you! Your review has been submitted and will appear once approved.');
