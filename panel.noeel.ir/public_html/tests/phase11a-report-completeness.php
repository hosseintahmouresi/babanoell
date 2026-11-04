<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void {
    $prefix='Babanoell\\Panel\\';
    if(!str_starts_with($class,$prefix))return;
    $path=$root.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($path))require_once $path;
});
use Babanoell\Panel\Reports\ReportsModule;
use Babanoell\Panel\Reports\HistoricalReportsModule;
$check=static function(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);};
$ok=['state'=>'ok','metrics'=>[]];
$check(HistoricalReportsModule::complete(['loans'=>$ok,'partners'=>$ok,'finance'=>$ok]),'all owners healthy');
$check(!HistoricalReportsModule::complete(['loans'=>$ok,'finance'=>$ok]),'missing owner blocked');
$check(!HistoricalReportsModule::complete(['loans'=>$ok,'partners'=>['state'=>'unavailable'],'finance'=>$ok]),'unavailable owner blocked');
$check(!HistoricalReportsModule::complete(['loans'=>$ok,'partners'=>$ok,'finance'=>['state'=>'incomplete']]),'partial owner blocked');
$check(ReportsModule::completeForExport(['loans'=>['status'=>'ok']]),'scoped single allowed report');
$check(ReportsModule::completeForExport(['loans'=>['status'=>'ok'],'finance'=>['status'=>'ok']]),'scoped multi report');
$check(!ReportsModule::completeForExport([]),'empty sources blocked');
$check(!ReportsModule::completeForExport(['loans'=>['status'=>'ok'],'finance'=>['status'=>'incomplete']]),'partial source blocked');
$check(!ReportsModule::completeForExport(['loans'=>['status'=>'unavailable']]),'unavailable source blocked');
$src=file_get_contents($root.'/src/Reports/ReportsModule.php');
$check(str_contains($src,"in_array('super_admin'"),'history link role guarded');
$check(str_contains($src,'http_response_code(503)'),'report service errors are not CSV');
$src=file_get_contents($root.'/src/Reports/HistoricalReportsModule.php');
$check(str_contains($src,'if(!self::complete($sources))'),'daily CSV guard wired');
$check(str_contains($src,'http_response_code(503)'),'daily export fails closed');
echo "Phase11A report integrity: 13/13 passed\n";
