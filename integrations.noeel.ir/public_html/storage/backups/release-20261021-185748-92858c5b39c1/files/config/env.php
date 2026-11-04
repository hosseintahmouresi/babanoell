<?php
declare(strict_types=1);
function noeel_integrations_env(string $key, string $default=''): string {
    $file=__DIR__.'/local.env.php';
    static $local=null;
    if($local===null) {$v=is_file($file)?require $file:[];$local=is_array($v)?$v:[];}
    if(isset($local[$key])&&is_scalar($local[$key])&&(string)$local[$key]!=='')return (string)$local[$key];
    $runtime=getenv($key);return $runtime!==false && $runtime!=='' ? (string)$runtime : $default;
}
