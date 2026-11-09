<?php
declare(strict_types=1);

$requestPath = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
if (PHP_SAPI === 'cli-server' && preg_match('#^/assets/[\w.-]+$#', $requestPath) && is_file(__DIR__ . $requestPath)) {
    return false;
}

require __DIR__ . '/app/core.php';
require __DIR__ . '/app/registers.php';
require __DIR__ . '/app/documents.php';
require __DIR__ . '/app/bulk.php';
require __DIR__ . '/app/xlsx.php';
require __DIR__ . '/app/reports.php';
require __DIR__ . '/app/routes.php';
require __DIR__ . '/app/purchase_in.php';
require __DIR__ . '/app/sales_challan.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

set_exception_handler(function (Throwable $error) use ($requestPath) {
    error_log((string) $error);
    if (str_starts_with($requestPath, '/api/')) {
        json_response(['error' => 'Something went wrong. Please try again.'], 500);
    }
    http_response_code(500);
    exit('Something went wrong. Please try again.');
});

start_session();
handle_request($_SERVER['REQUEST_METHOD'] ?? 'GET', $requestPath);
