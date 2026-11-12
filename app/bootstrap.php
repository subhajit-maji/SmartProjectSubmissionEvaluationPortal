<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
date_default_timezone_set('UTC');

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
ini_set('session.cookie_httponly', '1');

$isHttps = isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; object-src 'none'; frame-src 'none'; style-src 'self'; img-src 'self' data:; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store, private');
header_remove('X-Powered-By');
if ($isHttps) {
    header('Strict-Transport-Security: max-age=31536000');
}

$configPath = APP_ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
if (!is_file($configPath)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Database configuration is missing. Copy config/database.example.php to config/database.php and set local credentials.\n";
    exit;
}

$database = require $configPath;
$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $database['host'],
    (int) $database['port'],
    $database['database'],
    $database['charset']
);

try {
    $timezone = (string) ($database['timezone'] ?? '+00:00');
    $collation = (string) ($database['collation'] ?? 'utf8mb4_0900_ai_ci');
    if (!preg_match('/\A[+-]\d{2}:\d{2}\z/', $timezone) || !preg_match('/\A[a-z0-9_]+\z/i', $collation)) {
        throw new RuntimeException('Invalid database timezone or collation configuration.');
    }
    $options = $database['options'];
    $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET time_zone = '" . $timezone . "', collation_connection = '" . $collation . "'";
    $pdo = new PDO($dsn, $database['username'], $database['password'], $options);
} catch (Throwable $exception) {
    error_log('Portal database connection failed: ' . $exception->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "The portal database is unavailable. Check the database configuration and server logs.\n";
    exit;
}
