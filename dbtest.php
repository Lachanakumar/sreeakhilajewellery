<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/config/database.php';

try {
    $db = getDB();
    echo "DB connection OK<br>";
    $n = $db->query('SELECT COUNT(*) FROM settings')->fetchColumn();
    echo "settings rows: " . $n;
} catch (Throwable $e) {
    echo "<pre>DB ERROR: " . htmlspecialchars($e->getMessage()) . "</pre>";
}
