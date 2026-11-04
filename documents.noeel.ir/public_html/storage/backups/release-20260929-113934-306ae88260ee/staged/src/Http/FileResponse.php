<?php
declare(strict_types=1);
namespace Babanoell\Documents\Http;
final class FileResponse
{
 public static function stream(string$path,string$mime,string$name,bool$inline=false):never
 {
  if(!is_file($path)||!is_readable($path)){http_response_code(404);header('Content-Type: text/plain; charset=UTF-8');echo'فایل پیدا نشد.';exit;}
  $ascii=preg_replace('/[^A-Za-z0-9._-]+/','_',pathinfo($name,PATHINFO_FILENAME))?:'document';$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));$fallback=$ascii.($ext!==''?'.'.$ext:'');$encoded=rawurlencode($name);
  header('X-Content-Type-Options: nosniff');header('X-Frame-Options: SAMEORIGIN');header('Referrer-Policy: no-referrer');header('Cache-Control: private, no-store, max-age=0');header('Pragma: no-cache');header('Content-Type: '.$mime);header('Content-Length: '.(string)filesize($path));header('Content-Disposition: '.($inline?'inline':'attachment').'; filename="'.$fallback.'"; filename*=UTF-8\'\''.$encoded);readfile($path);exit;
 }
}
