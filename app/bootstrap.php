<?php
declare(strict_types=1);

$configFile = dirname(__DIR__) . '/config/database.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Copy config/database.example.php to config/database.php and enter your local database settings.');
}

$config = require $configFile;
define('APP_NAME', 'Smart Project Submission & Evaluation Portal');
define('MAX_UPLOAD_BYTES', 10 * 1024 * 1024);

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $config['host'],
    (int) $config['port'],
    $config['database']
);

$secureCookie = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secureCookie,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$pdo = new PDO($dsn, $config['username'], $config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

define('UPLOAD_DIRECTORY', dirname(__DIR__) . '/storage/uploads');

final class AppException extends RuntimeException
{
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect_to(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function set_flash(string $message): void
{
    $_SESSION['flash'] = $message;
}

function take_flash(): ?string
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_string($message) ? $message : null;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $expected = $_SESSION['csrf_token'] ?? '';
    $actual = $_POST['csrf_token'] ?? '';
    if (!is_string($expected) || $expected === '' || !is_string($actual) || !hash_equals($expected, $actual)) {
        throw new AppException('The form expired. Please try again.');
    }
}

function current_user(PDO $pdo): ?array
{
    $userId = $_SESSION['user_id'] ?? null;
    if (!is_int($userId) && !ctype_digit((string) $userId)) {
        return null;
    }

    $statement = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
    $statement->execute([(int) $userId]);
    $user = $statement->fetch();
    return $user ?: null;
}

function require_role(PDO $pdo, string $role): array
{
    $user = current_user($pdo);
    if ($user === null) {
        throw new AppException('Please sign in first.');
    }
    if ($user['role'] !== $role) {
        throw new AppException('You do not have permission to do that.');
    }
    return $user;
}

function save_pdf(array $upload): array
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new AppException('Choose a PDF file to submit.');
    }
    if (($upload['size'] ?? 0) < 1 || $upload['size'] > MAX_UPLOAD_BYTES) {
        throw new AppException('The PDF must be 10 MB or smaller.');
    }
    if (!isset($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
        throw new AppException('The uploaded file could not be checked.');
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    if ($fileInfo->file($upload['tmp_name']) !== 'application/pdf') {
        throw new AppException('Only PDF files are accepted.');
    }

    if (!is_dir(UPLOAD_DIRECTORY) && !mkdir(UPLOAD_DIRECTORY, 0770, true) && !is_dir(UPLOAD_DIRECTORY)) {
        throw new AppException('The private upload folder could not be created.');
    }

    $storedName = bin2hex(random_bytes(16)) . '.pdf';
    if (!move_uploaded_file($upload['tmp_name'], UPLOAD_DIRECTORY . '/' . $storedName)) {
        throw new AppException('The uploaded file could not be saved.');
    }

    $originalName = basename(str_replace('\\', '/', (string) ($upload['name'] ?? 'report.pdf')));
    return [
        'stored_name' => $storedName,
        'original_name' => mb_substr($originalName, 0, 255),
    ];
}
