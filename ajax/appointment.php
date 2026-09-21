<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/engagement.php';
require_once __DIR__ . '/../includes/mailer.php';

require_post();
csrf_verify_or_fail();

$name  = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$date  = trim($_POST['appointment_date'] ?? '');
$time  = trim($_POST['appointment_time'] ?? '');
$purpose = trim($_POST['purpose'] ?? '');
$message = trim($_POST['message'] ?? '');
$attach  = isset($_POST['attach_wishlist']) && $_POST['attach_wishlist'] === '1';

if ($name === '' || strlen(preg_replace('/\D/', '', $phone)) < 10) {
    json_response(false, 'Please provide your name and a valid mobile number.');
}
$ts = strtotime($date);
if (!$ts || $ts < strtotime('today')) {
    json_response(false, 'Please choose a valid future date.');
}
$slots = appointment_time_slots();
if ($slots && !in_array($time, $slots, true)) {
    json_response(false, 'Please choose an available time slot.');
}

$items = [];
if ($attach) {
    foreach (getWishlistItems() as $p) {
        $items[] = ['id' => $p['id'], 'name' => $p['name'], 'sku' => $p['sku']];
    }
}

$id = createAppointment([
    'user_id'          => isLoggedIn() ? auth_user_id() : null,
    'name'             => $name,
    'email'            => $email ?: null,
    'phone'            => $phone,
    'appointment_date' => date('Y-m-d', $ts),
    'appointment_time' => $time ?: 'Any time',
    'purpose'          => $purpose ?: null,
    'message'          => $message ?: null,
    'items'            => $items,
]);

/* Tell the store. Recipient comes from Admin -> Settings -> Notifications;
   the request is already saved, so a failed send is not the customer's
   problem and is never surfaced to them. */
send_notification('appointment', 'New appointment request from ' . $name, [
    'Name'       => $name,
    'Phone'      => $phone,
    'Email'      => $email,
    'Date'       => date('d M Y', $ts),
    'Time'       => $time ?: 'Any time',
    'Purpose'    => $purpose,
    'Message'    => $message,
    'Wishlist'   => $items ? count($items) . ' item(s) attached' : '',
    'Reference'  => '#' . $id,
], $email);

json_response(true, 'Your appointment request has been received. We will confirm shortly.', ['id' => $id]);
