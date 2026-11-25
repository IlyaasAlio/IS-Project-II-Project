<?php
session_start();
date_default_timezone_set('Africa/Nairobi');

define('DEV_MODE', true);          // true = show reset link on screen. Set false in production.
define('LOW_STOCK_THRESHOLD', 20); // quantities at or below this are flagged "Low" for facilities

// Work out the app's URL path (e.g. /drug_alert) so links and redirects work from any folder.
$__root = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
$__dir  = str_replace('\\', '/', (string) realpath(__DIR__));
$__base = ($__root !== '' && strpos($__dir, $__root) === 0) ? substr($__dir, strlen($__root)) : '/drug_alert';
define('BASE_PATH', rtrim($__base, '/'));

// Full URL used in password-reset links. In production, replace with your fixed https:// address.
$__https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
define('BASE_URL', ($__https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_PATH);

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=drug_alert;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed.');
}

require __DIR__ . '/functions.php';
