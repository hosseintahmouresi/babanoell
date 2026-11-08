<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
date_default_timezone_set('UTC');umask(0077);$root=dirname(__DIR__);$storage=$root.'/storage';
if(($argv[1]??'')==='--check'){echo json_encode(['php'=>PHP_VERSION,'bootstrap'=>is_file($root.'/bootstrap.php'),'storage_writable'=>is_writable($storage),'timezone'=>date_default_timezone_get()]),"\n";exit;}
if(is_link($storage.'/greenpay-request.lock')||is_link($storage.'/update.lock')||is_link($storage.'/greenpay-request.log'))exit(1);
$lock=@fopen($storage.'/greenpay-request.lock','c+b');if($lock===false||!flock($lock,LOCK_EX|LOCK_NB))exit;chmod($storage.'/greenpay-request.lock',0600);
$update=@fopen($storage.'/update.lock','c+b');if($update===false||!flock($update,LOCK_SH|LOCK_NB))exit;
require_once $root.'/src/Platform/ReleaseTransaction.php';if(Babanoell\Finance\Platform\ReleaseTransaction::pending($storage))exit;
try{
 require $root.'/bootstrap.php';$pdo->exec("SET time_zone = '+00:00'");$counts=['otp_requested'=>0,'completed_test'=>0,'skipped'=>0,'held'=>0];
 if((string)babanoell_finance_env('FINANCE_GREENPAY_AUTO_REQUEST_OTP','0')==='1'&&$config->greenpayOtpEnabled()){
  $rows=$pdo->query("SELECT request_id,row_version,created_by FROM finance_greenpay_request_debits WHERE eligible=1 AND status='not_started' ORDER BY updated_at LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
  foreach($rows as $row){
   try{$loan=$loansClient->greenpayContext($row['request_id']);if(!$loan->ok()||($loan->json['data']['request']['status']??'')!=='docs_verified'){$counts['skipped']++;continue;}
    $state=$requestDebitService->sendOtp($row['request_id'],$row['created_by'],(int)$row['row_version']);$counts[$state['status']==='awaiting_otp'?'otp_requested':($state['status']==='success'?'completed_test':'held')]++;
   }catch(Throwable){$counts['held']++;}
  }
 }
 $entry=['ok'=>true,'job'=>'greenpay-request-otp','result'=>$counts,'at'=>gmdate('c')];
}catch(Throwable $e){$entry=['ok'=>false,'job'=>'greenpay-request-otp','error_type'=>get_class($e),'at'=>gmdate('c')];}
try{
 $path=$storage.'/greenpay-request.log';if(is_file($path)&&filesize($path)>1048576&&!rename($path,$path.'.1'))exit(1);
 $out=@fopen($path,'ab');if($out===false)exit(1);chmod($path,0600);if(flock($out,LOCK_EX)){fwrite($out,json_encode($entry,JSON_THROW_ON_ERROR)."\n");flock($out,LOCK_UN);}fclose($out);
}finally{flock($update,LOCK_UN);fclose($update);flock($lock,LOCK_UN);fclose($lock);}
exit($entry['ok']?0:1);
