<?php
declare(strict_types=1);
$root=dirname(__DIR__);header('X-Frame-Options: DENY');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');
if(!is_file($root.'/storage/installed.lock')){http_response_code(503);header('Content-Type: application/json; charset=utf-8');echo json_encode(['service'=>'integrations','status'=>'not_installed'],JSON_UNESCAPED_SLASHES);exit;}
require $root.'/bootstrap.php';
try{$pdo=\Babanoell\Integrations\Support\Config::db();(new \Babanoell\Integrations\App\Router($pdo))->handle(\Babanoell\Integrations\Http\Request::fromGlobals());}
catch(\Throwable $e){error_log('[integrations] bootstrap_error '.get_class($e));\Babanoell\Integrations\Support\Response::fail(503,'service_unavailable','سرویس موقتاً در دسترس نیست.');}
