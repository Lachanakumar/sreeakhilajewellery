<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/../includes/theme.php';
require_once __DIR__ . '/includes/layout.php'; // defines admin_brand_asset() only; renders nothing

if (isAdminLoggedIn()) {
    redirect('dashboard.php');
}

$errors = [];
$notices = [];
if ($m = flash_get('login_info'))  { $notices[] = ['type' => 'info', 'msg' => $m]; }
if ($m = flash_get('login_warn'))  { $notices[] = ['type' => 'warn', 'msg' => $m]; }

$email  = $_COOKIE['aj_admin_email'] ?? '';
$remember = $email !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $remember = !empty($_POST['remember']);
    if (!csrf_verify()) {
        $errors[] = 'Your session expired before sign-in could complete. Please try again.';
    } else {
        // Remember only the email so it prefills next time — never the password.
        if ($remember) {
            setcookie('aj_admin_email', $email, time() + 60 * 60 * 24 * 60, '/');
        } else {
            setcookie('aj_admin_email', '', time() - 3600, '/');
        }
        [$ok, $result] = attemptAdminLogin($email, $_POST['password'] ?? '');
        if ($ok) {
            redirect('dashboard.php');
        }
        $errors[] = $result;
    }
}

$initial = strtoupper(substr(trim(SITE_NAME), 0, 1));
$brandLogo = admin_brand_asset('logo');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sign in &middot; <?php echo e(SITE_NAME); ?> Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" href="../<?php echo e(SITE_FAVICON); ?>">
    <script>
    /* Same preference the console uses. Light is the default — dark only when
       it was explicitly chosen, so the sign-in screen reads light out of the box. */
    (function () {
        try {
            var t = localStorage.getItem('aj-admin-theme');
            document.documentElement.setAttribute('data-theme', t === 'dark' ? 'dark' : 'light');
        } catch (e) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    })();
    </script>
    <?php echo theme_google_fonts_link(); ?>
    <link rel="stylesheet" href="../assets/css/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/admin.css') ?: date('Ymd'); ?>">
    <?php
    $ap = THEME_PRIMARY;
    $rgb = hex_to_rgb($ap);
    echo '<style>:root{--a-gold:' . e($ap) . ';--a-gold-2:' . e($ap) . ';--a-gold-tint:rgba(' . $rgb . ',.12);'
       . '--a-serif:' . theme_font_stack(THEME_FONT_HEADING, 'serif') . ';--serif:' . theme_font_stack(THEME_FONT_HEADING, 'serif') . ';'
       . '--font:' . theme_font_stack(THEME_FONT_BODY, 'sans-serif') . ';}</style>';
    ?>
</head>
<body class="admin is-login">
<button class="icon__btn login__theme" type="button" data-theme-toggle title="Switch theme" aria-label="Switch theme">
    <svg class="i-moon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
    <svg class="i-sun" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
</button>
<div class="login">

    <section class="login__stage">
        <div class="login__mark<?php echo $brandLogo ? ' has-logo' : ''; ?>">
            <?php if ($brandLogo): ?>
                <img src="<?php echo e($brandLogo); ?>" alt="<?php echo e(SITE_NAME); ?>">
            <?php else: ?>
                <i><?php echo e($initial); ?></i><?php echo e(SITE_NAME); ?>
            <?php endif; ?>
        </div>

        <div class="login__pitch">
            <span class="login__eyebrow">Admin Console</span>
            <h1>Run the <em>whole</em> store from one place.</h1>
            <p>Catalogue, orders, appointments, schemes and daily metal rates &mdash; every part of <?php echo e(SITE_NAME); ?> in a single console.</p>
            <ul class="login__list">
                <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m7 14 3-3 3 3 5-6"/></svg> Live revenue, orders and visitor insights</li>
                <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7 12 3 4 7v10l8 4 8-4z"/><path d="m4 7 8 4 8-4M12 11v10"/></svg> Products, stock, banners and offers</li>
                <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 16l2 2 4-4"/></svg> Appointments, enquiries and feedback</li>
            </ul>
        </div>

        <div class="login__foot">
            <span>&copy; <?php echo date('Y'); ?> <?php echo e(SITE_NAME); ?></span>
            <a href="../index.php" target="_blank" rel="noopener">View storefront &#8599;</a>
        </div>
    </section>

    <section class="login__panel">
        <div class="login__box">
            <div class="login__brandsm<?php echo $brandLogo ? ' has-logo' : ''; ?>">
                <?php if ($brandLogo): ?>
                    <img src="<?php echo e($brandLogo); ?>" alt="<?php echo e(SITE_NAME); ?>">
                <?php else: ?>
                    <i><?php echo e($initial); ?></i><?php echo e(SITE_NAME); ?>
                <?php endif; ?>
            </div>

            <h2>Welcome back</h2>
            <p>Sign in to your admin account to continue.</p>

            <?php foreach ($notices as $n): ?>
                <div class="notice notice--<?php echo e($n['type']); ?>"><?php echo e($n['msg']); ?></div>
            <?php endforeach; ?>
            <?php foreach ($errors as $err): ?>
                <div class="notice notice--err"><?php echo e($err); ?></div>
            <?php endforeach; ?>

            <form method="post" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

                <div class="field">
                    <label for="lg-email">Email address</label>
                    <input type="email" id="lg-email" name="email" value="<?php echo e($email); ?>"
                           placeholder="you@example.com" autocomplete="username" required
                           <?php echo $email === '' ? 'autofocus' : ''; ?>>
                </div>

                <div class="field">
                    <label for="lg-pass">Password</label>
                    <div class="login__pw">
                        <input type="password" id="lg-pass" name="password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                               autocomplete="current-password" required <?php echo $email !== '' ? 'autofocus' : ''; ?>>
                        <button type="button" data-pw-toggle aria-label="Show password">
                            <svg class="i-on" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1.5 12S5 5.5 12 5.5 22.5 12 22.5 12 19 18.5 12 18.5 1.5 12 1.5 12z"/><circle cx="12" cy="12" r="3.2"/></svg>
                            <svg class="i-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4l16 16"/><path d="M9.9 5.9A9.9 9.9 0 0 1 12 5.5c7 0 10.5 6.5 10.5 6.5a17 17 0 0 1-3.6 4.4M6.6 7.6A17 17 0 0 0 1.5 12S5 18.5 12 18.5a10 10 0 0 0 3.4-.6"/><path d="M9.9 9.9a3.2 3.2 0 0 0 4.3 4.3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="login__row">
                    <label class="login__check">
                        <input type="checkbox" name="remember" value="1" <?php echo $remember ? 'checked' : ''; ?>>
                        Remember my email
                    </label>
                </div>

                <button class="btn btn--block" type="submit">Sign in to console</button>
            </form>

            <a class="login__back" href="../index.php">&larr; Back to <?php echo e(SITE_NAME); ?></a>
        </div>
    </section>

</div>

<script>
(function () {
    var wrap = document.querySelector('.login__pw');
    if (wrap) {
        var btn = wrap.querySelector('[data-pw-toggle]');
        var input = wrap.querySelector('input');
        btn.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            wrap.classList.toggle('is-shown', show);
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            input.focus();
        });
    }
    // shares localStorage with the console, so the choice carries over after sign-in
    var themeBtn = document.querySelector('[data-theme-toggle]');
    if (themeBtn) {
        themeBtn.addEventListener('click', function () {
            var root = document.documentElement;
            var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            try { localStorage.setItem('aj-admin-theme', next); } catch (e) {}
        });
    }
})();
</script>
</body>
</html>
