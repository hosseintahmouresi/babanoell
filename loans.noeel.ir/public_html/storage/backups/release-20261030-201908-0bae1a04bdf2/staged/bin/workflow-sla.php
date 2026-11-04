<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require $root.'/bootstrap.php';
use Babanoell\Loans\Workflow\TaskRepository;
try{
    $repo=new TaskRepository($pdo);
    $result=$repo->runSlaSweep('system:cron');
    fwrite(STDOUT,json_encode(['ok'=>true,'service'=>'loans','job'=>'workflow-sla','result'=>$result,'at'=>gmdate('c')],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit(0);
}catch(Throwable $e){
    fwrite(STDERR,json_encode(['ok'=>false,'service'=>'loans','job'=>'workflow-sla','error'=>$e->getMessage(),'at'=>gmdate('c')],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit(1);
}
