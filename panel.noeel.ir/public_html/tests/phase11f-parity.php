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
    if(str_contains($rendered,'platform.css&amp;v=2.15.0'))$fail[]='legacy cache version persists';
    echo $fail?'[FAIL] Panel asset metadata: '.implode(', ',$fail)."\n":"[PASS] Panel asset metadata follows installed UI version\n";
    $guest=\Babanoell\Panel\Platform\Ui::loginBody('<form></form>');
    if(!str_contains($guest,'هفت سرویس'))$fail[]='login text still says four services';
    echo str_contains($guest,'هفت سرویس')?"[PASS] Panel shows seven-service architecture\n":"[FAIL] Panel seven-service architecture copy\n";
}catch(Throwable $e){$fail[]=$e->getMessage();echo '[FAIL] '.$e->getMessage()."\n";}
exit($fail?1:0);
