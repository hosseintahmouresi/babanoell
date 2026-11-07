<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
    $prefix='Babanoell\\Panel\\';
    if(str_starts_with($class,$prefix)){
        $f=$root.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
        if(is_file($f))require$f;
    }
});
$version=require $root.'/config/version.php';
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
$fail=[];
try{
    $rendered=\Babanoell\Panel\View\Renderer::page('کارتابل','<strong>محتوا</strong>', ['user'=>['display_name'=>'مدیر'],'roles'=>['super_admin'],'permissions'=>[]]);
    $v=(string)$version['ui'];
    if(!str_contains($rendered,'platform.css&amp;v='.$v))$fail[]='asset stylesheet cache version mismatched';
    if(!str_contains($rendered,'platform.js&amp;v='.$v))$fail[]='asset JS cache version mismatched';
    preg_match_all('/platform\.(?:css|js)&amp;v=([^"<>]+)/',$rendered,$assets);
    if(array_values(array_unique($assets[1]))!==[$v])$fail[]='mixed asset cache versions';
    echo $fail?'[FAIL] Panel asset metadata: '.implode(', ',$fail)."\n":"[PASS] Panel asset metadata follows installed UI version\n";
    $guest=\Babanoell\Panel\Platform\Ui::loginBody('<form></form>');
    if(!str_contains($guest,'همراه شما در مسیر تسهیلات'))$fail[]='login task guidance is missing';
    if(str_contains($guest,'سرویس هماهنگ'))$fail[]='technical service topology leaks into login copy';
    echo str_contains($guest,'همراه شما در مسیر تسهیلات')?"[PASS] Panel login uses task-focused Persian guidance\n":"[FAIL] Panel login guidance\n";
}catch(Throwable $e){$fail[]=$e->getMessage();echo '[FAIL] '.$e->getMessage()."\n";}
exit($fail?1:0);
