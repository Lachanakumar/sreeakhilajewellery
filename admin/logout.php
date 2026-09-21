<?php
require_once __DIR__ . '/includes/admin-auth.php';

// adminLogout() clears the admin session keys, so tell login.php first —
// the notice lives in the session and would otherwise be dropped.
adminLogout();
flash_set('login_info', 'You have been signed out.');
redirect('login.php');
