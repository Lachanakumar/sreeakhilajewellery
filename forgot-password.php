<?php
require_once 'includes/config.php';
require_once 'includes/products.php';
require_once 'includes/cart-functions.php';
require_once 'includes/auth.php';
require_once 'includes/auth-page.php';

$errors = [];
$done = false;
$devLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            $token = createPasswordResetToken($email);
            if ($token) {
                $devLink = sendPasswordResetEmail($email, $token);
            }
            // Always show the same message (no account enumeration).
            $done = true;
        }
    }
}

$pageMetaTitle = SITE_NAME . ' - Forgot Password';
$pageTitle     = 'Forgot Password';
$breadcrumbs   = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'Forgot Password', 'url' => '']];
include 'includes/header.php';
?>
<main class="main__content_wrapper">
    <?php include 'includes/page-title.php'; ?>
    <?php auth_page_open('Reset your password', 'Enter your email and we&rsquo;ll send you a reset link.'); ?>
        <?php auth_error_box($errors); ?>
        <?php if ($done): ?>
            <p>If an account exists for that email, a password reset link has been generated.</p>
            <?php if ($devLink): ?>
                <div class="flash__bar is-success" style="border-radius:6px;word-break:break-all">
                    Dev mode (no email configured): <a href="<?php echo e($devLink); ?>" style="color:#fff;text-decoration:underline"><?php echo e($devLink); ?></a>
                </div>
            <?php endif; ?>
            <p class="mt-20"><a href="login.php">Back to login</a></p>
        <?php else: ?>
            <form method="post" action="forgot-password.php" data-validate>
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <div class="mb-20"><label class="checkout__input--label">Email</label><input class="checkout__input--field border-radius-5 w-100" type="email" name="email" data-label="Email" required></div>
                <button type="submit" class="primary__btn w-100">Send Reset Link</button>
            </form>
            <p class="mt-20"><a href="login.php">Back to login</a></p>
        <?php endif; ?>
    <?php auth_page_close(); ?>
</main>
<?php include 'includes/footer.php'; ?>
