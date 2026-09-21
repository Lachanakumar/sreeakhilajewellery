<?php
require_once 'includes/config.php';
require_once 'includes/products.php';
require_once 'includes/cart-functions.php';
require_once 'includes/auth.php';
require_once 'includes/auth-page.php';

$redirectTo = $_GET['redirect'] ?? $_POST['redirect'] ?? 'account.php';
if (isLoggedIn()) {
    redirect($redirectTo);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($email === '' || $password === '') {
            $errors[] = 'Please enter your email/mobile and password.';
        } else {
            [$ok, $result] = attemptLogin($email, $password);
            if ($ok) {
                flash_set('success', 'Welcome back!');
                redirect($redirectTo);
            }
            $errors[] = $result;
        }
    }
}

$pageMetaTitle = SITE_NAME . ' - Login';
$pageTitle     = 'Login';
$breadcrumbs   = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'Login', 'url' => '']];
include 'includes/header.php';
?>
<main class="main__content_wrapper">
    <?php include 'includes/page-title.php'; ?>
    <?php auth_page_open('Welcome back', 'Login to track your orders, schemes and wishlist.'); ?>
        <?php auth_error_box($errors); ?>
        <form method="post" action="login.php" data-validate>
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="redirect" value="<?php echo e($redirectTo); ?>">
            <div class="mb-20"><label class="checkout__input--label">Email or Mobile Number</label><input class="checkout__input--field border-radius-5 w-100" type="text" name="email" data-label="Email or mobile number" value="<?php echo e($_POST['email'] ?? ''); ?>" required></div>
            <div class="mb-20"><label class="checkout__input--label">Password</label><input class="checkout__input--field border-radius-5 w-100" type="password" name="password" data-label="Password" required></div>
            <button type="submit" class="primary__btn w-100">Login</button>
        </form>
        <p class="mt-20"><a href="forgot-password.php">Forgot your password?</a></p>
        <p>New customer? <a href="register.php">Create an account</a></p>
    <?php auth_page_close(); ?>
</main>
<?php include 'includes/footer.php'; ?>
