<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
header('Cross-Origin-Resource-Policy: same-origin');
$allowed = [
 'platform.css'=>['text/css; charset=UTF-8','platform.css'],
 'panel.css'=>['text/css; charset=UTF-8','platform.css'],
 'babanoell-ui.css'=>['text/css; charset=UTF-8','platform.css'],
 'platform.js'=>['application/javascript; charset=UTF-8','platform.js'],
 'panel.js'=>['application/javascript; charset=UTF-8','platform.js'],
 'babanoell-ui.js'=>['application/javascript; charset=UTF-8','platform.js'],
 'favicon.ico'=>['image/x-icon','favicon.ico.json'],
 'favicon.gif'=>['image/gif','favicon.gif.json'],
 'favicon-navy.png'=>['image/png','favicon-navy.png'],
 'favicon-pale.png'=>['image/png','favicon-pale.png'],
 'apple-touch-icon.png'=>['image/png','apple-touch-icon.png'],
 'favicon.js'=>['application/javascript; charset=UTF-8','favicon.js'],
 'credit.js'=>['application/javascript; charset=UTF-8','credit.js'],
 'brand-logo.png'=>['image/png','brand-logo.png'],
 'YekanBakhFaNum-Regular.woff'=>['font/woff','YekanBakhFaNum-Regular.woff'],
 'YekanBakhFaNum-SemiBold.woff'=>['font/woff','YekanBakhFaNum-SemiBold.woff'],
 'YekanBakhFaNum-Bold.woff'=>['font/woff','YekanBakhFaNum-Bold.woff'],
 'noeel-icons.woff2'=>['font/woff2','noeel-icons.woff2'],
];
$key = is_string($_GET['file'] ?? null) ? $_GET['file'] : '';
if (!isset($allowed[$key])) { http_response_code(404); exit; }
[$type,$name] = $allowed[$key];
$path = __DIR__.'/assets/'.$name;
if (!is_file($path)) { http_response_code(404); exit; }
$bytes = file_get_contents($path);
if (in_array($name, ['brand-logo.json', 'favicon.ico.json', 'favicon.gif.json'], true)) {
 $data = json_decode((string)$bytes,true);
 $bytes = base64_decode((string)($data['base64'] ?? ''),true);
}
if (!is_string($bytes)) { http_response_code(500); exit; }
$etag = '"'.hash('sha256',$bytes).'"';
header('ETag: '.$etag);
header('Cache-Control: public, max-age=86400, must-revalidate');
if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) { http_response_code(304); exit; }
header('Content-Type: '.$type);
header('Content-Length: '.strlen($bytes));
echo $bytes;
