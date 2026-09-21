<?php
/**
 * Admin authentication guard. require() this at the very top of every admin
 * page and every admin AJAX endpoint.
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/table.php';
require_once __DIR__ . '/ui.php';

function admin_id() {
    return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : 0;
}

function isAdminLoggedIn() {
    return admin_id() > 0;
}

function currentAdmin() {
    static $admin = null;
    if (!isAdminLoggedIn()) {
        return null;
    }
    if ($admin === null) {
        $stmt = getDB()->prepare('SELECT id, name, email, role, status FROM admins WHERE id = ? LIMIT 1');
        $stmt->execute([admin_id()]);
        $admin = $stmt->fetch() ?: null;
        if ($admin && $admin['status'] !== 'active') {
            adminLogout();
            return null;
        }
    }
    return $admin;
}

function adminLogin(array $admin) {
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_name'] = $admin['name'];
}

function adminLogout() {
    unset($_SESSION['admin_id'], $_SESSION['admin_name']);
}

function attemptAdminLogin($email, $password) {
    $stmt = getDB()->prepare('SELECT * FROM admins WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        return [false, 'Incorrect email or password.'];
    }
    if ($admin['status'] !== 'active') {
        return [false, 'This admin account is inactive.'];
    }
    adminLogin($admin);
    return [true, $admin];
}

/**
 * Call at the top of a protected page.
 */
function requireAdmin() {
    if (!isAdminLoggedIn() || !currentAdmin()) {
        // say why, instead of bouncing to a blank login screen
        flash_set('login_warn', 'Please sign in to continue — your session has ended.');
        redirect('login.php');
    }
}

/**
 * For admin AJAX endpoints.
 */
function requireAdminAjax() {
    if (!isAdminLoggedIn() || !currentAdmin()) {
        json_response(false, 'Not authorised.', [], 401);
    }
}
