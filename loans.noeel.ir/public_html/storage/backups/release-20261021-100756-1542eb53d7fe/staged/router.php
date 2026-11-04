<?php
declare(strict_types=1);
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';$public=__DIR__.'/public';$candidate=realpath($public.$path);if($candidate!==false&&str_starts_with($candidate,realpath($public).DIRECTORY_SEPARATOR)&&is_file($candidate)){return false;}require $public.'/index.php';
