<?php
/**
 * PDO database connection (singleton)
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'akila_jewellery');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/* The store runs on Indian time. php.ini on this server says Europe/Berlin
   while MySQL runs on IST, so date('Y-m-d') and CURDATE() disagreed for
   several hours every night — the visitor counter rolled "today" over at the
   wrong time and counted the same visitor twice. */
date_default_timezone_set('Asia/Kolkata');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
