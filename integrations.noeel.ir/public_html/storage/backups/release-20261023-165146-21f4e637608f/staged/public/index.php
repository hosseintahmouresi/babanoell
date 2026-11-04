<?php
declare(strict_types=1);
$root=dirname(__DIR__);require_once $root.'/src/Platform/ReleaseTransaction.php';\Babanoell\Integrations\Platform\ReleaseTransaction::guard($root);
if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)==='/'&&str_contains((string)($_SERVER['HTTP_ACCEPT']??''),'text/html')){header('Location: /update.php',true,302);exit;}
header('X-Frame-Options: DENY');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');
if(!is_file($root.'/storage/installed.lock')){http_response_code(503);header('Content-Type: application/json; charset=utf-8');echo json_encode(['service'=>'integrations','status'=>'not_installed'],JSON_UNESCAPED_SLASHES);exit;}
require $root.'/bootstrap.php';
try{$pdo=\Babanoell\Integrations\Support\Config::db();(new \Babanoell\Integrations\App\Router($pdo))->handle(\Babanoell\Integrations\Http\Request::fromGlobals());}
catch(\Throwable $e){error_log('[integrations] bootstrap_error '.get_class($e));\Babanoell\Integrations\Support\Response::fail(503,'service_unavailable','سرویس موقتاً در دسترس نیست.');}
