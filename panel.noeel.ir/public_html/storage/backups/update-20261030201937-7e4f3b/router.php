<?php
declare(strict_types=1);
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$public = __DIR__ . '/public';
$file = $public . $uri;
if (preg_match('#^/(config|storage|src|tests)(/|$)#', $uri)) { http_response_code(403); echo 'Forbidden'; return true; }
if ($uri !== '/' && is_file($file)) {
    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'php') {
        $_SERVER['SCRIPT_FILENAME'] = $file;
        require $file;
        return true;
    }
    $types = ['css'=>'text/css; charset=UTF-8','js'=>'application/javascript; charset=UTF-8','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','svg'=>'image/svg+xml','woff'=>'font/woff','woff2'=>'font/woff2'];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (isset($types[$ext])) header('Content-Type: '.$types[$ext]);
    readfile($file);
    return true;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $public . '/index.php';
return true;
