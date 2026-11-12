<?php
declare(strict_types=1);

function send_password_reset_email(string $recipient, string $token): bool
{
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || !preg_match('/\A[a-f0-9]{64}\z/i', $token)) {
        return false;
    }
    $configPath = APP_ROOT . '/config/mail.php';
    if (!is_file($configPath)) {
        return false;
    }
    $mailConfig = require $configPath;
    $from = filter_var($mailConfig['from'] ?? '', FILTER_VALIDATE_EMAIL);
    $baseUrl = filter_var($mailConfig['base_url'] ?? '', FILTER_VALIDATE_URL);
    $parts = is_string($baseUrl) ? parse_url($baseUrl) : false;
    if (!$from || !$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
        error_log('Password recovery mail configuration is invalid.');
        return false;
    }

    $url = rtrim($baseUrl, '/') . '/?page=reset&token=' . rawurlencode($token);
    $body =
        "A password reset was requested for your Project Portal account.\r\n\r\n" .
        "Use the link below within 30 minutes to choose a new password:\r\n" .
        $url . "\r\n\r\n" .
        "If you did not request this reset, you can ignore this message.";
    $headers = [
        'From' => $from,
        'Content-Type' => 'text/plain; charset=UTF-8',
        'X-Content-Type-Options' => 'nosniff',
    ];

    try {
        return mail($recipient, 'Project Portal password reset', $body, $headers);
    } catch (Throwable $exception) {
        error_log('Password recovery email could not be queued: ' . $exception->getMessage());
        return false;
    }
}
