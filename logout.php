<?php
require_once 'includes/auth.php';
logoutUser();
flash_set('success', 'You have been logged out.');
redirect('index.php');
