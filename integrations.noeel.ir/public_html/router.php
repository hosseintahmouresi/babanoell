<?php
declare(strict_types=1);

if ((parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/') === '/favicon.ico') {
    $_GET['file'] = 'favicon.ico';
    require __DIR__.'/public/ui-asset.php';
    return true;
}

/** Local PHP development router; production continues to use the existing web-server rules. */
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
if(preg_match('#^/(config|storage|database|src|bin|tests|docs)(/|$)#',$path)){
    http_response_code(403);header('Content-Type: text/plain; charset=utf-8');echo 'Forbidden';return true;
}
if(in_array($path,['/ui-asset.php','/update.php','/install.php'],true)){
    $_SERVER['SCRIPT_NAME']=$path;
    require __DIR__.'/public'.$path;
    return true;
}
if(str_starts_with($path,'/assets/')){
    $file=realpath(__DIR__.'/public'.$path);
    if(is_string($file)&&str_starts_with($file,__DIR__.'/public/assets/')&&is_file($file))return false;
}
$_SERVER['SCRIPT_NAME']='/index.php';
require __DIR__.'/public/index.php';
return true;
