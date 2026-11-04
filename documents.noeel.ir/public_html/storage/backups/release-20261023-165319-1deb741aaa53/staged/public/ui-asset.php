<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');header('Cross-Origin-Resource-Policy: same-origin');
$allowed=['platform.css'=>['text/css; charset=UTF-8','platform.css'],'babanoell-ui.css'=>['text/css; charset=UTF-8','platform.css'],'panel.css'=>['text/css; charset=UTF-8','platform.css'],'platform.js'=>['application/javascript; charset=UTF-8','platform.js'],'babanoell-ui.js'=>['application/javascript; charset=UTF-8','platform.js'],'panel.js'=>['application/javascript; charset=UTF-8','platform.js'],'brand-logo.png'=>['image/png','brand-logo.png'],'vazirmatn.woff2'=>['font/woff2','vazirmatn-font.json']];
$key=is_string($_GET['file']??null)?$_GET['file']:'';
if(!isset($allowed[$key])){http_response_code(404);exit;}
[$type,$name]=$allowed[$key];$path=__DIR__.'/assets/'.$name;
if(!is_file($path)){http_response_code(404);exit;}
$bytes=file_get_contents($path);
if($key==='vazirmatn.woff2'){$data=json_decode((string)$bytes,true);$bytes=base64_decode($data['base64']??'',true);}
if(!is_string($bytes)){http_response_code(500);exit;}
$etag='"'.hash('sha256',$bytes).'"';header('ETag: '.$etag);header('Cache-Control: public,max-age=86400,must-revalidate');
if(trim((string)($_SERVER['HTTP_IF_NONE_MATCH']??''))===$etag){http_response_code(304);exit;}
header('Content-Type: '.$type);header('Content-Length: '.strlen($bytes));echo $bytes;
