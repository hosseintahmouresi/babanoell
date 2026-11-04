<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
header('Cross-Origin-Resource-Policy: same-origin');
$assets=[
 'babanoell-ui.css'=>['text/css; charset=UTF-8','babanoell-ui.css'],
 'platform.css'=>['text/css; charset=UTF-8','babanoell-ui.css'],
 'panel.css'=>['text/css; charset=UTF-8','babanoell-ui.css'],
 'babanoell-ui.js'=>['application/javascript; charset=UTF-8','babanoell-ui.js'],
 'platform.js'=>['application/javascript; charset=UTF-8','babanoell-ui.js'],
 'panel.js'=>['application/javascript; charset=UTF-8','babanoell-ui.js'],
 'brand-logo.png'=>['image/png','brand-logo.json'],
];
$key=is_string($_GET['file']??null)?$_GET['file']:'';
if(!isset($assets[$key])){http_response_code(404);exit;}
[$type,$file]=$assets[$key];$path=__DIR__.'/assets/'.$file;
if(!is_file($path)){http_response_code(404);exit;}
$tag='"'.hash_file('sha256',$path).'"';
header('ETag: '.$tag);header('Cache-Control: public,max-age=86400,must-revalidate');
if(trim((string)($_SERVER['HTTP_IF_NONE_MATCH']??''))===$tag){http_response_code(304);exit;}
$bytes=file_get_contents($path);
if($key==='brand-logo.png'){$data=json_decode((string)$bytes,true);$bytes=base64_decode((string)($data['base64']??''),true);}
if(!is_string($bytes)){http_response_code(500);exit;}
header('Content-Type: '.$type);header('Content-Length: '.strlen($bytes));echo $bytes;
