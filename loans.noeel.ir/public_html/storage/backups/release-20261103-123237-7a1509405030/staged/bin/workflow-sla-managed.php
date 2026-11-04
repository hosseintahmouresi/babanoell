<?php
declare(strict_types=1);
// Managed Loans-only scheduler. No browser route, provider calls or cross-service DB.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
date_default_timezone_set('UTC');umask(0077);
$root=dirname(__DIR__);
if(($argv[1]??'')==='--check'){
    echo json_encode(['php'=>PHP_VERSION,'binary'=>PHP_BINARY,'bootstrap'=>is_file($root.'/bootstrap.php'),'job'=>is_file($root.'/bin/workflow-sla.php'),'storage_writable'=>is_writable($root.'/storage'),'timezone'=>date_default_timezone_get()]),"\n";exit;
}
$storage=$root.'/storage';$lockPath=$storage.'/workflow-sla.lock';$logPath=$storage.'/workflow-sla.log';
if(is_link($lockPath)||is_link($logPath)||is_link($storage.'/update.lock'))exit(1);
$jobLock=@fopen($lockPath,'c+b');if($jobLock===false)exit(1);@chmod($lockPath,0600);
if(!flock($jobLock,LOCK_EX|LOCK_NB)){fclose($jobLock);exit;}
$updateLock=@fopen($storage.'/update.lock','c+b');
if($updateLock===false||!flock($updateLock,LOCK_SH|LOCK_NB))exit;
require_once$root.'/src/Platform/ReleaseTransaction.php';
if(Babanoell\Loans\Platform\ReleaseTransaction::pending($storage))exit;
try{
    require$root.'/bootstrap.php';
    $pdo->exec("SET time_zone = '+00:00'");
    $result=(new Babanoell\Loans\Workflow\TaskRepository($pdo))->runSlaSweep('system:cron');
    $entry=['ok'=>true,'service'=>'loans','job'=>'workflow-sla','result'=>$result,'at'=>gmdate('c')];
}catch(Throwable$e){$entry=['ok'=>false,'service'=>'loans','job'=>'workflow-sla','error_type'=>get_class($e),'at'=>gmdate('c')];}
try{
    $log=@fopen($logPath,'ab');if($log===false)throw new RuntimeException('Private job log unavailable');
    try{if(!chmod($logPath,0600))throw new RuntimeException('Private job log permissions unavailable');if(flock($log,LOCK_EX)){fwrite($log,json_encode($entry,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");fflush($log);flock($log,LOCK_UN);}}
    finally{fclose($log);}
}finally{flock($updateLock,LOCK_UN);fclose($updateLock);flock($jobLock,LOCK_UN);fclose($jobLock);}
exit($entry['ok']?0:1);
