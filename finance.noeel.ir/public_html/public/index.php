<?php
declare(strict_types=1);
use Babanoell\Finance\Domain\FinanceRules;use Babanoell\Finance\Http\IdempotencyKey;use Babanoell\Finance\Http\JsonResponse;use Babanoell\Finance\Http\Pagination;use Babanoell\Finance\Http\Request;use Babanoell\Finance\Security\Authenticator;use Babanoell\Finance\Security\Principal;
$root=dirname(__DIR__);require_once$root.'/src/Platform/ReleaseTransaction.php';\Babanoell\Finance\Platform\ReleaseTransaction::guard($root);$rawPath=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';if(!is_file($root.'/storage/installed.lock')||!is_file($root.'/config/local.env.php')){if(str_starts_with($rawPath,'/api/'))JsonResponse::error(503,'service_not_installed','سرویس مالی هنوز نصب نشده است.');header('Location:/install.php',true,302);exit;}require$root.'/bootstrap.php';if($config->isProduction()&&!\Babanoell\Finance\Platform\ProxyContext::secure($_SERVER))JsonResponse::error(403,'https_required','ارتباط HTTPS لازم است.');$request=Request::fromGlobals();$method=$request->method();$path=$request->path();\Babanoell\Finance\Platform\RequestContext::initialize($request->header('X-Request-Id'),'finance');
if(str_starts_with($path,'/api/v1/')){$sa=\Babanoell\Finance\Platform\ServiceAuth::verify($request->header(\Babanoell\Finance\Platform\ServiceAuth::HEADER_SERVICE),$request->header(\Babanoell\Finance\Platform\ServiceAuth::HEADER_TIMESTAMP),$request->header(\Babanoell\Finance\Platform\ServiceAuth::HEADER_SIGNATURE),$method,$path,$request->rawBody(),$config->serviceAuthSecret(),$config->serviceAuthMode());if(!$sa['ok'])JsonResponse::error($sa['error']==='service_auth_not_configured'?503:401,(string)$sa['error'],'احراز هویت سرویس فراخوان نامعتبر است.');if($sa['authenticated']===true)\Babanoell\Finance\Platform\RequestContext::setCallerService(is_string($sa['service'])?$sa['service']:null);}
try{
 if($path==='/health')JsonResponse::send(200,['service'=>'finance','status'=>'ok','environment'=>$config->environment(),'version'=>(require $root.'/config/version.php')['version'],'timestamp'=>gmdate('c')]);
 if($path==='/healthz'){$db=false;try{$db=(int)$pdo->query('SELECT 1')->fetchColumn()===1;}catch(Throwable){}$ih=$identityClient->health();$lh=$loansClient->health();$ph=$partnersClient->health();$dh=$documentsClient->health();$ok=$db&&$ih->ok()&&$lh->ok()&&$ph->ok()&&$dh->ok();JsonResponse::send($ok?200:503,['service'=>'finance','status'=>$ok?'ok':'error','database'=>$db?'ok':'error','identity'=>$ih->ok()?'ok':'unavailable','loans'=>$lh->ok()?'ok':'unavailable','partners'=>$ph->ok()?'ok':'unavailable','documents'=>$dh->ok()?'ok':'unavailable','version'=>(require $root.'/config/version.php')['version'],'timestamp'=>gmdate('c')]);}
 if(!str_starts_with($path,'/api/v1/'))JsonResponse::error(404,'route_not_found','مسیر درخواستی وجود ندارد.');
 if($path==='/api/v1/meta/platform-contract'&&$method==='GET')JsonResponse::ok(['contract'=>\Babanoell\Finance\Platform\ServiceBoundary::describe(),'service_auth'=>['mode'=>$config->serviceAuthMode(),'configured'=>$config->serviceAuthSecret()!=='']]);
 $auth=(new Authenticator($identityClient))->authenticate($request);if($auth['principal']===null){$e=$auth['error'];JsonResponse::error($e['status'],$e['code'],$e['message']);}/** @var Principal $principal */$principal=$auth['principal'];$bearer=(string)$request->bearer();$require=static function(string$p)use($principal):void{if(!$principal->can($p))JsonResponse::error(403,'permission_denied','برای انجام این عملیات دسترسی کافی ندارید.');};$admin=$principal->isSuperAdmin()||in_array('admin',$principal->roles,true);


 if($path==='/api/v1/admin/reports/daily'&&$method==='GET'){
  if(!$principal->isSuperAdmin())JsonResponse::error(403,'permission_denied','گزارش تاریخی فقط برای مدیر ارشد مجاز است.');
  $q=$request->query();
  try{JsonResponse::ok(\Babanoell\Finance\Reports\DailyReport::collect($pdo,(string)($q['from']??''),(string)($q['to']??'')));}
  catch(\InvalidArgumentException){JsonResponse::error(422,'invalid_report_range','بازه گزارش معتبر نیست.');}
 }

 if(preg_match('#^/api/v1/admin/requests/([a-f0-9]{26})/merchant-settlement/reset$#',$path,$m)&&$method==='POST'){
  if(!$admin)JsonResponse::error(403,'permission_denied','بازنشانی تسویه فقط برای مدیر سامانه مجاز است.');
  $b=$request->json();
  JsonResponse::ok(['merchant_settlement'=>$financeService->adminResetMerchantSettlement($m[1],$bearer,$principal->userId,$b)]);
 }


 if(preg_match('#^/api/v1/admin/requests/([a-f0-9]{26})/merchant-settlement/resync$#',$path,$m)&&$method==='POST'){
  if(!$admin)JsonResponse::error(403,'permission_denied','این عملیات فقط برای مدیر مجاز است.');
  JsonResponse::ok(['shadow_sync'=>$financeService->retryMerchantSettlementShadowSync($m[1],$bearer,$principal->userId,$request->json())]);
 }
 if($path==='/api/v1/greenpay/requests'&&$method==='GET'){
  if(!$admin)JsonResponse::error(403,'permission_denied','مدیریت کسری GreenPay فقط برای مدیر سامانه مجاز است.');
  JsonResponse::ok(['debits'=>$requestDebitService->listing((int)($request->query()['page']??1)),'enabled'=>(string)\babanoell_finance_env('FINANCE_GREENPAY_REQUEST_DEBITS_ENABLED','0')==='1']);
 }
 if(preg_match('#^/api/v1/greenpay/requests/([a-f0-9]{26})(?:/(enrollment|send-otp|execute|reconcile))?$#',$path,$debit)){
  if(!$admin)JsonResponse::error(403,'permission_denied','مدیریت کسری GreenPay فقط برای مدیر سامانه مجاز است.');
  $action=$debit[2]??'';
  if($method==='GET'&&$action==='')JsonResponse::ok(['debit'=>$requestDebitService->get($debit[1])]);
  if($method==='POST'){
   $body=$request->json();$version=(int)($body['row_version']??0);
   if($action==='enrollment')JsonResponse::ok(['debit'=>$requestDebitService->enroll($debit[1],$principal->userId,$body)]);
   if($action==='send-otp'){
    if(!$config->greenpayOtpEnabled())JsonResponse::error(503,'greenpay_otp_disabled','ارسال رمز مالی در تنظیمات غیرفعال است.');
    JsonResponse::ok(['debit'=>$requestDebitService->sendOtp($debit[1],$principal->userId,$version)]);
   }
   if($action==='execute')JsonResponse::ok(['debit'=>$requestDebitService->execute($debit[1],$principal->userId,$version,(string)($body['otp']??''))]);
   if($action==='reconcile')JsonResponse::ok(['debit'=>$requestDebitService->reconcile($debit[1],$principal->userId,$version,$body)]);
  }
 }
 if($path==='/api/v1/greenpay/readiness'&&$method==='GET'){
  if(!$admin&&!$principal->can('finance.reconciliation.read'))JsonResponse::error(403,'permission_denied','اجازه مشاهده وضعیت اتصال مالی ندارید.');
  $gp=new \Babanoell\Finance\Service\GreenpayBridgeService($pdo,$integrationsClient,$repository,$config->serviceAuthSecret());
  JsonResponse::ok(['greenpay'=>$gp->readiness(),'transfers_enabled'=>(string)\babanoell_finance_env('FINANCE_GREENPAY_TRANSFERS_ENABLED','0')==='1','otp_enabled'=>$config->greenpayOtpEnabled()]);
 }
 if($path==='/api/v1/greenpay/send-otp'&&$method==='POST'){
  if(!$admin)JsonResponse::error(403,'permission_denied','درخواست OTP مالی فقط برای مدیر ارشد مجاز است.');
  if(!$config->greenpayOtpEnabled())JsonResponse::error(503,'greenpay_otp_disabled','ارسال OTP مالی به‌صورت پیش‌فرض غیرفعال است.');
  $b=$request->json();$key=IdempotencyKey::normalize($request->header('idempotency-key'));
  $amount=filter_var($b['amount_rial']??null,FILTER_VALIDATE_INT);if($amount===false||$amount<1)JsonResponse::error(422,'invalid_greenpay_amount','مبلغ ریالی معتبر نیست.');
  if($key==='')JsonResponse::error(422,'idempotency_required','کلید یکتای عملیات الزامی است.');
  $gp=new \Babanoell\Finance\Service\GreenpayBridgeService($pdo,$integrationsClient,$repository,$config->serviceAuthSecret());
  JsonResponse::ok(['greenpay'=>$gp->sendOtp($principal->userId,(string)($b['pocket_number']??''),(int)$amount,$key)]);
 }
 // Transfers are Finance-owned, append-only and require an independent second approver.
 $transferService=new \Babanoell\Finance\Service\GreenpayTransferService($pdo,$repository,$integrationsClient,\Babanoell\Finance\Service\GreenpayTransferService::encryptionKeyFromEnvironment(),(string)\babanoell_finance_env('FINANCE_GREENPAY_TRANSFERS_ENABLED','0')==='1');
 if($path==='/api/v1/greenpay/transfers'&&$method==='GET'){
  if(!$admin&&!$principal->can('finance.reconciliation.read'))JsonResponse::error(403,'permission_denied','اجازه مشاهده تراکنش‌ها وجود ندارد.');
  $q=$request->query();JsonResponse::ok(['transfers'=>$transferService->list((int)($q['page']??1),(int)($q['per_page']??25),trim((string)($q['status']??'')))]);
 }
 if($path==='/api/v1/greenpay/transfers'&&$method==='POST'){
  if(!$admin)JsonResponse::error(403,'permission_denied','ثبت انتقال فقط برای مدیر مجاز است.');
  $key=IdempotencyKey::normalize($request->header('idempotency-key'));
  if($key==='')JsonResponse::error(422,'idempotency_required','کلید یکتای درخواست الزامی است.');
  JsonResponse::ok(['transfer'=>$transferService->prepare($principal->userId,$request->json(),$key)],201);
 }
 if(preg_match('#^/api/v1/greenpay/transfers/([a-f0-9]{26})$#',$path,$m)&&$method==='GET'){
  if(!$admin&&!$principal->can('finance.reconciliation.read'))JsonResponse::error(403,'permission_denied','دسترسی مجاز نیست.');
  JsonResponse::ok(['transfer'=>$transferService->get($m[1])]);
 }
 if(preg_match('#^/api/v1/greenpay/transfers/([a-f0-9]{26})/(approve|send-otp|execute|reconcile)$#',$path,$m)&&$method==='POST'){
  if(!$admin)JsonResponse::error(403,'permission_denied','این عملیات فقط برای مدیر سامانه مجاز است.');
  $b=$request->json();
  if($m[2]==='approve')JsonResponse::ok(['transfer'=>$transferService->approve($m[1],$principal->userId,(string)($b['note']??''))]);
  if($m[2]==='send-otp'){if(!$config->greenpayOtpEnabled())JsonResponse::error(503,'greenpay_otp_disabled','ارسال OTP مالی غیرفعال است.');$key=IdempotencyKey::normalize($request->header('idempotency-key'));if($key==='')JsonResponse::error(422,'idempotency_required','کلید یکتا الزامی است.');JsonResponse::ok(['otp'=>$transferService->sendOtp($m[1],$principal->userId,$key)]);}
  if($m[2]==='execute')JsonResponse::ok(['transfer'=>$transferService->dispatch($m[1],$principal->userId,(string)($b['otp']??''))]);
  JsonResponse::ok(['transfer'=>$transferService->reconcile($m[1],$principal->userId,(string)($b['decision']??''),(string)($b['note']??''))]);
 }
 if($path==='/api/v1/greenpay/transfer'&&$method==='POST')JsonResponse::error(410,'transfer_endpoint_moved','برای انتقال وجه باید ابتدا درخواست مالی ثبت و مستقل تأیید شود.');
 if($path==='/api/v1/bank-cycles'&&$method==='GET'){
  $require('finance.bank_settlement.read');$q=$request->query();
  $globalBankRead=$admin||in_array('finance_expert',$principal->roles,true)||in_array('operations_expert',$principal->roles,true);
  $owner=$globalBankRead?trim((string)($q['bank_user_id']??'')):$principal->userId;
  if($owner!==''&&!preg_match('/^[a-f0-9]{26}$/D',$owner))JsonResponse::error(422,'invalid_bank_owner','شناسه کارشناس معتبر نیست.');
  $date=trim((string)($q['settlement_date']??''));if($date!=='')$date=\Babanoell\Finance\Service\BankCycleService::parseDate($date);
  $search=mb_substr(trim((string)($q['q']??'')),0,50);
  $bRepo=new \Babanoell\Finance\Repository\BankCycleRepository($pdo);
  JsonResponse::ok(['cycles'=>$bRepo->list(max(1,(int)($q['page']??1)),min(100,max(1,(int)($q['per_page']??25))),$owner,$date,$search)]);
 }
 if($path==='/api/v1/bank-cycles'&&$method==='POST'){
  $require('finance.bank_settlement.manage');
  $key=IdempotencyKey::normalize($request->header('idempotency-key'));
  if($key==='')JsonResponse::error(422,'idempotency_required','کلید یکتای عملیات الزامی است.');
  $service=new \Babanoell\Finance\Service\BankCycleService(new \Babanoell\Finance\Repository\BankCycleRepository($pdo),$loansClient);
  JsonResponse::ok(['cycle'=>$service->create($principal->userId,$bearer,$request->json(),$key)],201);
 }
 if(preg_match('#^/api/v1/bank-cycles/([a-f0-9]{26})$#',$path,$m)&&$method==='GET'){
  $require('finance.bank_settlement.read');$bRepo=new \Babanoell\Finance\Repository\BankCycleRepository($pdo);$cycle=$bRepo->get($m[1]);
  if(!$cycle)JsonResponse::error(404,'bank_cycle_not_found','سیکل بانک یافت نشد.');
  if(!$admin&&!in_array('finance_expert',$principal->roles,true)&&!in_array('operations_expert',$principal->roles,true)&&$cycle['bank_user_id']!==$principal->userId)JsonResponse::error(403,'bank_cycle_scope_denied','مشاهده این سیکل مجاز نیست.');
  JsonResponse::ok(['cycle'=>$cycle]);
 }
 if(preg_match('#^/api/v1/bank-cycles/([a-f0-9]{26})/retry-sync$#',$path,$m)&&$method==='POST'){
  $require('finance.bank_settlement.manage');$bRepo=new \Babanoell\Finance\Repository\BankCycleRepository($pdo);$cycle=$bRepo->get($m[1]);
  if(!$cycle)JsonResponse::error(404,'bank_cycle_not_found','سیکل بانک یافت نشد.');
  if(!$admin&&$cycle['bank_user_id']!==$principal->userId)JsonResponse::error(403,'bank_cycle_scope_denied','اجازه بازهمگام‌سازی ندارید.');
  $service=new \Babanoell\Finance\Service\BankCycleService($bRepo,$loansClient);JsonResponse::ok(['cycle'=>$service->retry($m[1])]);
 }
 if($path==='/api/v1/stats'&&$method==='GET'){$require('finance.dashboard.read');JsonResponse::ok(['stats'=>$repository->stats()]);}
 if($path==='/api/v1/import/loans-shadow'&&$method==='POST'){if(!$admin&&!$principal->can('finance.migration.manage'))JsonResponse::error(403,'permission_denied','اجازه انتقال داده مالی قدیمی را ندارید.');JsonResponse::ok(['import'=>$financeService->importLoansShadow($principal->userId)]);}
 if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/snapshot$#',$path,$m)&&$method==='GET'){$require('finance.dashboard.read');JsonResponse::ok($financeService->snapshot($m[1],$bearer));}
 if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/bank-settlement$#',$path,$m)){
  if($method==='GET'){$require('finance.bank_settlement.read');JsonResponse::ok($financeService->snapshot($m[1],$bearer));}
  if($method==='POST'){$require('finance.bank_settlement.manage');$key=IdempotencyKey::normalize($request->header('idempotency-key'));JsonResponse::ok(['bank_settlement'=>$financeService->bankSettlement($m[1],$bearer,$principal->userId,$request->json()),'idempotency_key'=>$key]);}
 }
 if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/merchant-settlement$#',$path,$m)){
  if($method==='GET'){$require('finance.merchant_settlement.read');JsonResponse::ok($financeService->snapshot($m[1],$bearer));}
  if($method==='POST'){$require('finance.merchant_settlement.manage');$key=IdempotencyKey::normalize($request->header('idempotency-key'));JsonResponse::ok(['merchant_settlement'=>$financeService->merchantSettlement($m[1],$bearer,$principal->userId,$request->json()),'idempotency_key'=>$key]);}
 }
 if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/financier-payment$#',$path,$m)){
  if($method==='GET'){$require('finance.financier_payment.read');JsonResponse::ok($financeService->snapshot($m[1],$bearer));}
  if($method==='POST'){$require('finance.financier_payment.manage');$key=IdempotencyKey::normalize($request->header('idempotency-key'));JsonResponse::ok(['financier_payment'=>$financeService->financierPayment($m[1],$bearer,$principal->userId,$request->json()),'idempotency_key'=>$key]);}
 }
 if($path==='/api/v1/cycles'){
  if($method==='GET'){$require('finance.cycles.read');$q=$request->query();$page=max(1,(int)($q['page']??1));$per=min(100,max(1,(int)($q['per_page']??20)));$r=$repository->listCycles($page,$per,trim((string)($q['status']??'')),trim((string)($q['merchant_id']??'')));JsonResponse::ok(array_merge($r,['pagination'=>Pagination::fromResult($r)]));}
  if($method==='POST'){$require('finance.cycles.manage');$key=IdempotencyKey::normalize($request->header('idempotency-key'));if($key==='')JsonResponse::error(422,'idempotency_required','کلید یکتای ایجاد سیکل الزامی است.');JsonResponse::ok(['cycle'=>$financeService->createCycle($principal->userId,$request->json(),$bearer,$key)],201);}
 }
 if(preg_match('#^/api/v1/cycles/([a-f0-9]{26})$#',$path,$m)&&$method==='GET'){$require('finance.cycles.read');$c=$repository->cycle($m[1]);if($c===null)JsonResponse::error(404,'cycle_not_found','سیکل تسویه پیدا نشد.');JsonResponse::ok(['cycle'=>$c]);}
 if(preg_match('#^/api/v1/cycles/([a-f0-9]{26})/items$#',$path,$m)&&$method==='POST'){$require('finance.cycles.manage');$key=IdempotencyKey::normalize($request->header('idempotency-key'));if($key==='')JsonResponse::error(422,'idempotency_required','کلید یکتای افزودن پرونده الزامی است.');JsonResponse::ok(['cycle'=>$financeService->addCycleItem($m[1],$principal->userId,$request->json(),$bearer,$key)]);}
 if(preg_match('#^/api/v1/cycles/([a-f0-9]{26})/integrity$#',$path,$m)&&$method==='GET'){
  $require('finance.cycles.read');JsonResponse::ok(['integrity'=>$repository->cycleIntegrity($m[1])]);
 }
 if(preg_match('#^/api/v1/cycles/([a-f0-9]{26})/items/([a-f0-9]{26})/remove$#',$path,$m)&&$method==='POST'){
  if(!$admin)JsonResponse::error(403,'permission_denied','حذف پرونده از سیکل فقط برای مدیر مجاز است.');
  $b=$request->json();JsonResponse::ok(['cycle'=>$repository->removeCycleItem($m[1],$m[2],(string)($b['reason']??''),$principal->userId)]);
 }
 if($path==='/api/v1/reports/cycles'&&$method==='GET'){
  $require('finance.cycles.read');$q=$request->query();$page=max(1,(int)($q['page']??1));$per=min(100,max(1,(int)($q['per_page']??25)));
  JsonResponse::ok(['report'=>$repository->reportCycles($page,$per,trim((string)($q['status']??'')),trim((string)($q['merchant_id']??'')))]);
 }
 if($path==='/api/v1/reconciliation/scan'&&$method==='POST'){
  if(!$admin)JsonResponse::error(403,'permission_denied','اسکن مغایرت مالی فقط برای مدیر مجاز است.');
  $body=$request->json();if(($body['confirm']??false)!==true)JsonResponse::error(422,'confirmation_required','تأیید اجرای اسکن الزامی است.');
  JsonResponse::ok(['result'=>$repository->scanClosedCycleDiscrepancies($principal->userId,(int)($body['limit']??100))]);
 }
 if(preg_match('#^/api/v1/cycles/([a-f0-9]{26})/close$#',$path,$m)&&$method==='POST'){$require('finance.cycles.manage');JsonResponse::ok(['cycle'=>$repository->closeCycle($m[1],$principal->userId)]);}
 if(preg_match('#^/api/v1/cycles/([a-f0-9]{26})/reopen$#',$path,$m)&&$method==='POST'){if(!$admin&&!$principal->can('finance.reconciliation.manage'))JsonResponse::error(403,'permission_denied','بازگشایی سیکل مجاز نیست.');$b=$request->json();JsonResponse::ok(['cycle'=>$repository->reopenCycle($m[1],$principal->userId,(string)($b['reason']??''))]);}
 if($path==='/api/v1/receipts'&&$method==='GET'){
  $require('finance.receipts.manage');$q=$request->query();$page=max(1,(int)($q['page']??1));$per=min(100,max(1,(int)($q['per_page']??25)));
  JsonResponse::ok(['receipts'=>$repository->listReceipts($page,$per,trim((string)($q['status']??'pending')))]);
 }
 if($path==='/api/v1/receipts'&&$method==='POST'){$require('finance.receipts.manage');$key=IdempotencyKey::normalize($request->header('idempotency-key'));if($key==='')JsonResponse::error(422,'idempotency_required','کلید یکتای ثبت رسید الزامی است.');JsonResponse::ok(['receipt'=>$financeService->createReceipt($principal->userId,$request->json(),$bearer,$key)],201);}
 if(preg_match('#^/api/v1/receipts/([a-f0-9]{26})/review$#',$path,$m)&&$method==='POST'){$require('finance.receipts.manage');$b=$request->json();JsonResponse::ok(['receipt'=>$repository->reviewReceipt($m[1],FinanceRules::receiptStatus((string)($b['status']??'')),(string)($b['note']??''),$principal->userId)]);}
 if($path==='/api/v1/reconciliation'&&$method==='GET'){$require('finance.reconciliation.read');JsonResponse::ok(['items'=>$repository->listReconciliation((string)($request->query()['status']??'open'))]);}
 if(preg_match('#^/api/v1/reconciliation/([a-f0-9]{26})/resolve$#',$path,$m)&&$method==='POST'){$require('finance.reconciliation.manage');$b=$request->json();JsonResponse::ok(['item'=>$repository->resolveReconciliation($m[1],(string)($b['note']??''),$principal->userId)]);}
 JsonResponse::error(404,'route_not_found','مسیر API در این نسخه وجود ندارد.');
}catch(\Babanoell\Finance\Service\RequestDebitException $e){JsonResponse::error($e->httpStatus,$e->publicCode,$e->getMessage());}catch(InvalidArgumentException$e){JsonResponse::error(422,'validation_failed',$e->getMessage());}catch(RuntimeException$e){$m=$e->getMessage();if($m==='record_access_denied')JsonResponse::error(403,'record_access_denied','دسترسی به پرونده مجاز نیست.');if(in_array($m,['greenpay_encryption_not_configured','greenpay_transfer_disabled','greenpay_execution_key_not_configured','greenpay_provider_not_ready','greenpay_provider_execution_not_authorized'],true))JsonResponse::error(503,$m,'اجرای انتقال وجه هنوز در تنظیمات امن فعال نشده است.');if(in_array($m,['greenpay_otp_unconfirmed','greenpay_auth_unavailable'],true))JsonResponse::error(503,$m,'وضعیت ارسال OTP نامشخص است؛ بدون بررسی مجدد درخواست را تکرار نکنید.');if(in_array($m,['greenpay_idempotency_conflict','greenpay_idempotency_in_progress','greenpay_cycle_already_has_transfer','greenpay_transfer_already_handled','idempotency_conflict','bank_case_already_recorded','bank_case_already_in_cycle'],true))JsonResponse::error(409,$m,'عملیات با این کلید قبلاً ثبت شده است.');if(in_array($m,['loans_unavailable','loans_response_invalid','loan_case_unavailable_or_denied','loan_case_invalid'],true))JsonResponse::error(503,$m,'سرویس تسهیلات برای بررسی عملیات مالی در دسترس نیست.');JsonResponse::error(409,'finance_operation_failed',$m);}catch(PDOException$e){error_log('[finance] db: '.$e->getMessage());JsonResponse::error(503,'database_error','عملیات پایگاه داده کامل نشد.');}catch(Throwable$e){error_log('[finance] unexpected '.get_class($e).': '.$e->getMessage());JsonResponse::error(500,'internal_error','خطای غیرمنتظره رخ داد.');}
