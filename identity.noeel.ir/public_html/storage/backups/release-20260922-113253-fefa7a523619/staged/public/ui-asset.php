<?php
declare(strict_types=1);

$assets = [
    'babanoell-ui.css' => ['type' => 'text/css; charset=utf-8', 'path' => __DIR__ . '/assets/babanoell-ui.css'],
    'babanoell-ui.js' => ['type' => 'application/javascript; charset=utf-8', 'path' => __DIR__ . '/assets/babanoell-ui.js'],
    'brand-logo.png' => ['type' => 'image/png', 'path' => __DIR__ . '/assets/brand-logo.png'],
];

$key = isset($_GET['file']) && is_string($_GET['file']) ? $_GET['file'] : '';
$asset = $assets[$key] ?? null;

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cross-Origin-Resource-Policy: same-origin');

if ($asset === null || !is_file($asset['path']) || !is_readable($asset['path'])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'فایل رابط کاربری پیدا نشد.';
    exit;
}

$etag = '"' . hash_file('sha256', $asset['path']) . '"';
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim((string) $_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    header('ETag: ' . $etag);
    header('Cache-Control: public, max-age=31536000, immutable');
    exit;
}

header('Content-Type: ' . $asset['type']);
header('Content-Length: ' . (string) filesize($asset['path']));
header('Cache-Control: public, max-age=31536000, immutable');
header('ETag: ' . $etag);
readfile($asset['path']);
