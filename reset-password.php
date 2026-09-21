<?php
require_once 'includes/config.php';
require_once 'includes/products.php';
require_once 'includes/cart-functions.php';
require_once 'includes/auth.php';
require_once 'includes/auth-page.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$errors = [];
$done = false;
$valid = (bool) findValidPasswordReset($token);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';
        $weak = password_problem($password);
        if ($weak !== '') $errors[] = $weak;
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';
        if (!$errors) {
            [$ok, $msg] = completePasswordReset($token, $password);
            if ($ok) {
                flash_set('success', $msg);
                redirect('login.php');
            }
            $errors[] = $msg;
        }
    }
}

$pageMetaTitle = SITE_NAME . ' - Reset Password';
$pageTitle     = 'Reset Password';
$breadcrumbs   = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'Reset Password', 'url' => '']];
include 'includes/header.php';
?>
<main class="main__content_wrapper">
    <?php include 'includes/page-title.php'; ?>
    <?php auth_page_open('Choose a new password', 'At least 8 characters, with a letter and a number.'); ?>
        <?php auth_error_box($errors); ?>
        <?php if (!$valid): ?>
            <p>This reset link is invalid or has expired.</p>
            <p><a href="forgot-password.php">Request a new link</a></p>
        <?php else: ?>
            <form method="post" action="reset-password.php" data-validate>
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="token" value="<?php echo e($token); ?>">
                <div class="mb-20"><label class="checkout__input--label">New Password</label><input class="checkout__input--field border-radius-5 w-100" type="password" name="password" id="newPassword" data-label="New password" data-rule="password" required></div>
                <div class="mb-20"><label class="checkout__input--label">Confirm Password</label><input class="checkout__input--field border-radius-5 w-100" type="password" name="password_confirm" data-label="Confirm password" data-match="#newPassword" data-match-message="Passwords do not match." required></div>
                <button type="submit" class="primary__btn w-100">Update Password</button>
            </form>
        <?php endif; ?>
    <?php auth_page_close(); ?>
</main>
<?php include 'includes/footer.php'; ?>
