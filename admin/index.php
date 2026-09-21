<?php
require_once __DIR__ . '/includes/admin-auth.php';
redirect(isAdminLoggedIn() ? 'dashboard.php' : 'login.php');
