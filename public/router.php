<?php
declare(strict_types=1);

$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (!is_string($requestPath) || $requestPath === '/') {
    return false;
}

$documentRoot = realpath(__DIR__);
$decodedPath = rawurldecode($requestPath);
$candidate = $documentRoot === false
    ? false
    : realpath($documentRoot . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $decodedPath), DIRECTORY_SEPARATOR));

if (
    $documentRoot !== false
    && $candidate !== false
    && str_starts_with($candidate, $documentRoot . DIRECTORY_SEPARATOR)
    && is_file($candidate)
) {
    return false;
}

http_response_code(404);
require __DIR__ . '/404.php';
