<?php
require_once 'includes/config.php';
require_once 'includes/products.php';
require_once 'includes/cart-functions.php';
require_once 'includes/auth.php';
require_once 'includes/auth-page.php';

if (isLoggedIn()) {
    redirect('account.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';
        $phoneDigits = preg_replace('/\D/', '', $phone);

        if ($name === '') $errors[] = 'Please enter your name.';
        if (strlen($phoneDigits) < 10) $errors[] = 'Please enter a valid mobile number (at least 10 digits).';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address (or leave it blank).';
        $weak = password_problem($password);
        if ($weak !== '') $errors[] = $weak;
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';

        if (!$errors) {
            [$ok, $result] = registerUser($name, $email, $phone, $password, $dob ?: null);
            if ($ok) {
                attemptLogin($phone, $password);
                flash_set('success', 'Your account and membership have been created.');
                redirect('account.php');
            }
            $errors[] = $result;
        }
    }
}

$pageMetaTitle = SITE_NAME . ' - Register';
$pageTitle     = 'Create Account';
$breadcrumbs   = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'Create Account', 'url' => '']];
include 'includes/header.php';
?>
<main class="main__content_wrapper">
    <?php include 'includes/page-title.php'; ?>
    <?php auth_page_open('Create your account', 'Takes a minute &mdash; and activates your membership.'); ?>
        <?php auth_error_box($errors); ?>
        <form method="post" action="register.php" data-validate>
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <div class="mb-20"><label class="checkout__input--label">Full Name</label><input class="checkout__input--field border-radius-5 w-100" type="text" name="name" data-label="Full name" minlength="2" value="<?php echo e($_POST['name'] ?? ''); ?>" required></div>
            <div class="mb-20"><label class="checkout__input--label">Mobile Number</label><input class="checkout__input--field border-radius-5 w-100" type="tel" name="phone" data-label="Mobile number" data-rule="phone" value="<?php echo e($_POST['phone'] ?? ''); ?>" placeholder="10-digit mobile number" required></div>
            <div class="mb-20"><label class="checkout__input--label">Email <span style="font-weight:400">(optional)</span></label><input class="checkout__input--field border-radius-5 w-100" type="email" name="email" value="<?php echo e($_POST['email'] ?? ''); ?>"></div>
            <div class="mb-20" data-error-anchor><label class="checkout__input--label">Date of Birth <span style="font-weight:400">(optional &ndash; for birthday offers)</span></label><input class="checkout__input--field border-radius-5 w-100" type="text" data-datepicker name="dob" value="<?php echo e($_POST['dob'] ?? ''); ?>" data-max="<?php echo date('Y-m-d'); ?>" placeholder="Pick a date"></div>
            <div class="mb-20"><label class="checkout__input--label">Password</label><input class="checkout__input--field border-radius-5 w-100" type="password" name="password" id="regPassword" data-label="Password" data-rule="password" required></div>
            <div class="mb-20"><label class="checkout__input--label">Confirm Password</label><input class="checkout__input--field border-radius-5 w-100" type="password" name="password_confirm" data-label="Confirm password" data-match="#regPassword" data-match-message="Passwords do not match." required></div>
            <button type="submit" class="primary__btn w-100">Create Account</button>
        </form>
        <p class="mt-20">Already have an account? <a href="login.php">Login</a></p>
    <?php auth_page_close(); ?>
</main>
<?php include 'includes/footer.php'; ?>
