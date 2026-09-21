<?php
/**
 * Contact Us form handler (AJAX, JSON only).
 *
 * Two things used to go wrong here:
 *  1. mail() emits a PHP warning when no SMTP server is reachable (the default
 *     on XAMPP). display_errors printed that warning ahead of the JSON body, so
 *     the browser's r.json() threw and the page fell into its catch branch,
 *     showing "Something went wrong" even though the submission was fine.
 *  2. The message only ever went out by email — nothing was stored, so a failed
 *     send lost the enquiry outright.
 *
 * Now the enquiry is written to the `enquiries` table first (that is what the
 * customer is told about), email is a best-effort notification on top, and the
 * response is always well-formed JSON.
 */

// Never let a notice/warning leak into the response body.
@ini_set('display_errors', '0');
ob_start();

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/engagement.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

/** Emit JSON and stop, discarding anything PHP may have printed first. */
function contact_respond($success, $message) {
    if (ob_get_length() !== false) {
        ob_end_clean();
    }
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => (bool) $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    contact_respond(false, 'Invalid request method.');
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    contact_respond(false, 'Your session expired. Please refresh the page and try again.');
}

$firstname = strip_tags(trim((string) ($_POST['firstname'] ?? '')));
$lastname  = strip_tags(trim((string) ($_POST['lastname'] ?? '')));
$phone     = strip_tags(trim((string) ($_POST['phone'] ?? '')));
$email     = strip_tags(trim((string) ($_POST['email'] ?? '')));
$message   = strip_tags(trim((string) ($_POST['message'] ?? '')));

if ($firstname === '' || $lastname === '' || $phone === '' || $email === '' || $message === '') {
    contact_respond(false, 'Please fill in all required fields.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    contact_respond(false, 'Please enter a valid email address.');
}
// Indian mobile: 10 digits starting 6-9, with any +91 / 0 prefix tolerated.
$digits = preg_replace('/\D/', '', $phone);
$last10 = substr($digits, -10);
if (!preg_match('/^[6-9]\d{9}$/', $last10)) {
    contact_respond(false, 'Please enter a valid 10-digit Indian mobile number.');
}

$fullName = trim($firstname . ' ' . $lastname);

try {
    createEnquiry([
        'user_id' => isLoggedIn() ? auth_user_id() : null,
        'name'    => mb_substr($fullName, 0, 120),
        'email'   => mb_substr($email, 0, 150),
        'phone'   => mb_substr($phone, 0, 30),
        'subject' => 'Contact form',
        'message' => $message,
    ]);
} catch (Throwable $e) {
    error_log('contact-process: could not store enquiry - ' . $e->getMessage());
    contact_respond(false, 'We could not send your message just now. Please call us instead.');
}

/* Best-effort notification to whoever Admin -> Settings -> Notifications names
   for the contact form. The return value is deliberately ignored: the enquiry
   is already stored, so a mail transport that is down must not be reported to
   the customer as a failed submission. */
send_notification('contact', 'New Contact Form Submission from ' . $fullName, [
    'Name'    => $fullName,
    'Phone'   => $phone,
    'Email'   => $email,
    'Message' => $message,
], $email);

contact_respond(true, 'Thank you! Your message has been received — our team will get back to you shortly.');
