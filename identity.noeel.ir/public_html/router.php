<?php
declare(strict_types=1);

if ((parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/') === '/favicon.ico') {
    $_GET['file'] = 'favicon.ico';
    require __DIR__.'/public/ui-asset.php';
    return true;
}

/**
 * Router for PHP's built-in server (local development without Docker):
 *
 *   php -S 127.0.0.1:8081 -t public router.php     (run from the service root)
 *
 * Serves assets and the explicitly requested public PHP entry points from
 * public/, denies private service paths, and forwards application routes to
 * the front controller.
 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$publicDir = __DIR__ . '/public';
$file = $publicDir . $uri;

if (preg_match('#^/(config|storage|database|src|bin|tests|docs)(/|$)#', $uri)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden.\n";
    return true;
}

if ($uri !== '/' && is_file($file)) {
    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'php') {
        $_SERVER['SCRIPT_FILENAME'] = $file;
        $_SERVER['SCRIPT_NAME'] = $uri;
        require $file;
        return true;
    }

    return false; // let the built-in server stream static public assets
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
require $publicDir . '/index.php';
return true;
