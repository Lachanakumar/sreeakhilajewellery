<?php
/**
 * Customer authentication + password-reset helpers.
 */

require_once __DIR__ . '/functions.php';

function auth_user_id() {
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
}

function isLoggedIn() {
    return auth_user_id() > 0;
}

/**
 * The signed-in customer, or null.
 *
 * $fresh = true re-reads the row (used right after a profile update, where the
 * static cache would otherwise hand back the pre-save values).
 */
function currentUser($fresh = false) {
    static $user = null;
    static $loaded = false;

    if (!isLoggedIn()) {
        return null;
    }
    if ($fresh) {
        $loaded = false;
    }
    if (!$loaded) {
        $stmt = getDB()->prepare('SELECT id, name, email, phone, status, created_at, membership_no, membership_tier, dob FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([auth_user_id()]);
        $user = $stmt->fetch() ?: null;
        $loaded = true;

        // Account deleted, or blocked by an admin while the customer was still
        // signed in. Drop the session here so no page goes on to dereference a
        // null user — that is what printed a raw PHP warning on the storefront.
        if (!$user || $user['status'] === 'blocked') {
            $blocked = ($user !== null);
            $user = null;
            logoutUser();
            flash_set('error', $blocked
                ? 'Your account has been blocked, so you have been signed out. Please contact us if you think this is a mistake.'
                : 'Your session has ended. Please log in again.');
        }
    }
    return $user;
}

function loginUser(array $user) {
    /* Fold the guest cart in BEFORE the id changes: guest rows are keyed by
       session_id(), so merging after session_regenerate_id() looked for rows
       under the new id and silently orphaned the basket. */
    if (function_exists('cart_merge_guest_into_user')) {
        cart_merge_guest_into_user((int) $user['id']);
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = $user['name'];
}

function logoutUser() {
    unset($_SESSION['user_id'], $_SESSION['user_name']);
    // a stale cart/wishlist owner key would otherwise follow the next visitor
    // on this session id
    unset($_SESSION['coupon_code']);
}

function requireLogin($redirectTo = null) {
    // currentUser() is the real check: the session can still hold a user_id for
    // an account that has since been blocked or removed.
    if (!isLoggedIn() || !currentUser()) {
        $target = $redirectTo ?? ($_SERVER['REQUEST_URI'] ?? 'account.php');
        if (!flash_has('error')) {
            flash_set('error', 'Please log in to continue.');
        }
        redirect('login.php?redirect=' . urlencode($target));
    }
}

/* ==================== PASSWORD STRENGTH ==================== */

/**
 * Server-side twin of passwordProblem() in assets/js/validate.js. A length
 * check alone let "12345678" through, so the same weak-password rules are
 * applied here — the browser check is a convenience, this one is the gate.
 *
 * @return string  '' when acceptable, otherwise the reason it is not.
 */
function password_problem($password) {
    $password = (string) $password;
    $common = [
        'password', 'passw0rd', 'p@ssword', '12345678', '123456789', '1234567890',
        'qwertyui', 'qwerty123', 'iloveyou', 'admin123', 'welcome1', 'abc12345',
        'letmein1', 'jewellery', 'shivani123',
    ];

    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (in_array(strtolower($password), $common, true)) {
        return 'That password is too common. Please choose something harder to guess.';
    }
    if (preg_match('/^(.)\1+$/u', $password)) {
        return 'Password cannot be the same character repeated.';
    }
    if (preg_match('/^\d+$/', $password)) {
        return 'Password cannot be digits only — add letters too.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Password must include at least one letter and one number.';
    }
    return '';
}

/* ==================== REGISTRATION / LOGIN ==================== */

function normalize_phone($phone) {
    return preg_replace('/[^\d]/', '', (string) $phone);
}

/**
 * Register a customer. Mobile number is required and unique; a membership
 * number is issued automatically. Email is optional.
 */
function registerUser($name, $email, $phone, $password, $dob = null) {
    $db = getDB();
    $phoneDigits = normalize_phone($phone);

    if ($email) {
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return [false, 'An account with that email already exists.'];
        }
    }
    // Match on the last 10 digits so +91 / 0-prefixes still collide.
    $stmt = $db->prepare("SELECT id FROM users WHERE RIGHT(REPLACE(REPLACE(phone,'+',''),' ',''), 10) = ? LIMIT 1");
    $stmt->execute([substr($phoneDigits, -10)]);
    if ($stmt->fetch()) {
        return [false, 'An account with that mobile number already exists.'];
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO users (name, email, phone, password_hash, dob) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$name, $email ?: null, $phone, password_hash($password, PASSWORD_DEFAULT), $dob ?: null]);
        $id = (int) $db->lastInsertId();
        $db->prepare('UPDATE users SET membership_no = ? WHERE id = ?')
           ->execute(['AJM' . str_pad((string) $id, 6, '0', STR_PAD_LEFT), $id]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        return [false, 'Could not create the account. Please try again.'];
    }
    return [true, $id];
}

/**
 * Log in with either an email address or a mobile number.
 */
function attemptLogin($identifier, $password) {
    $identifier = trim($identifier);
    $db = getDB();
    if (strpos($identifier, '@') !== false) {
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$identifier]);
    } else {
        $digits = normalize_phone($identifier);
        $stmt = $db->prepare("SELECT * FROM users WHERE RIGHT(REPLACE(REPLACE(phone,'+',''),' ',''), 10) = ? LIMIT 1");
        $stmt->execute([substr($digits, -10)]);
    }
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return [false, 'Incorrect login or password.'];
    }
    /* Worded the same as the mid-session check in currentUser(), so a customer
       blocked while signed in and one blocked between visits are told the same
       thing rather than two different stories. */
    if ($user['status'] === 'blocked') {
        return [false, 'Your account has been blocked, so you cannot sign in. Please contact us if you think this is a mistake.'];
    }
    loginUser($user);
    return [true, $user];
}

/* ==================== PASSWORD RESET ==================== */

/**
 * Create a reset token. Returns the plaintext token (to build the link) or null
 * if the email is unknown.
 */
function createPasswordResetToken($email) {
    $db = getDB();
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        return null;
    }
    $token = bin2hex(random_bytes(32));
    $stmt = $db->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([
        $user['id'],
        hash('sha256', $token),
        date('Y-m-d H:i:s', time() + 3600),
    ]);
    return $token;
}

function findValidPasswordReset($token) {
    $stmt = getDB()->prepare(
        'SELECT * FROM password_resets
         WHERE token_hash = ? AND used = 0 AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([hash('sha256', $token)]);
    return $stmt->fetch() ?: null;
}

function completePasswordReset($token, $newPassword) {
    $reset = findValidPasswordReset($token);
    if (!$reset) {
        return [false, 'This reset link is invalid or has expired.'];
    }
    $db = getDB();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $reset['user_id']]);
        $stmt = $db->prepare('UPDATE password_resets SET used = 1 WHERE id = ?');
        $stmt->execute([$reset['id']]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        return [false, 'Could not reset the password. Please try again.'];
    }
    return [true, 'Your password has been updated. You can now log in.'];
}

/**
 * Stand-in for a real transactional email. Logs the reset link to a file and
 * returns it so the page can also display it (no SMTP on local XAMPP).
 * Swap the body of this function for mail()/PHPMailer to go live.
 */
function sendPasswordResetEmail($email, $token) {
    $link = rtrim(
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        . dirname($_SERVER['SCRIPT_NAME']), '/'
    ) . '/reset-password.php?token=' . urlencode($token);

    $logDir = __DIR__ . '/../storage';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    @file_put_contents(
        $logDir . '/password-resets.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $email . ' => ' . $link . PHP_EOL,
        FILE_APPEND
    );
    return $link;
}
