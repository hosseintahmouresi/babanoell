<?php
declare(strict_types=1);

use Babanoell\Loans\Domain\InputValidator;
use Babanoell\Loans\Domain\PlanSettingsValidator;
use Babanoell\Loans\Http\JsonResponse;
use Babanoell\Loans\Http\Request;
use Babanoell\Loans\Security\Authenticator;
use Babanoell\Loans\Security\Principal;
use Babanoell\Loans\Service\CalculationService;
use Babanoell\Loans\Service\PlanService;
use Babanoell\Loans\Service\RequestService;
use Babanoell\Loans\Service\WorkflowService;

$root=dirname(__DIR__);
require_once $root.'/src/Platform/ReleaseTransaction.php';
\Babanoell\Loans\Platform\ReleaseTransaction::guard($root);

if(!is_file($root.'/storage/installed.lock')||!is_file($root.'/config/local.env.php')){
    if(str_starts_with(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/','/api/')) JsonResponse::error(503,'service_not_installed','سرویس تسهیلات هنوز نصب نشده است.');
    header('Location: /install.php',true,302);exit;
}
require $root.'/bootstrap.php';
if(strtolower($config->environment())==='production'&&!\Babanoell\Loans\Platform\ProxyContext::secure($_SERVER)) JsonResponse::error(403,'https_required','ارتباط HTTPS لازم است.');
$request=Request::fromGlobals();$method=$request->method();$path=$request->path();

try{
    if($path==='/health') JsonResponse::send(200,['service'=>$config->serviceName(),'status'=>'ok','environment'=>$config->environment(),'version'=>'1.5.0','timestamp'=>gmdate('c')]);
    if($path==='/healthz'){
        $dbOk=false;try{$dbOk=(int)$pdo->query('SELECT 1')->fetchColumn()===1;}catch(Throwable){}
        $ih=$identityClient->health();$identityOk=$ih->ok()&&(($ih->json['status']??null)==='ok');$dh=$documentsClient->health();$documentsOk=$dh->ok()&&(($dh->json['status']??null)==='ok');$ok=$dbOk&&$identityOk&&$documentsOk;
        JsonResponse::send($ok?200:503,['service'=>$config->serviceName(),'status'=>$ok?'ok':'error','database'=>$dbOk?'ok':'error','identity'=>$identityOk?'ok':'unavailable','documents'=>$documentsOk?'ok':'unavailable','environment'=>$config->environment(),'version'=>'1.5.0','timestamp'=>gmdate('c')]);
    }
    if(!str_starts_with($path,'/api/v1/')) JsonResponse::error(404,'route_not_found','مسیر درخواستی وجود ندارد.');

    $auth=(new Authenticator($identityClient))->authenticate($request);if($auth['principal']===null){$e=$auth['error'];JsonResponse::error($e['status'],$e['code'],$e['message']);}
    /** @var Principal $principal */$principal=$auth['principal'];
    $planService=new PlanService($repository,new PlanSettingsValidator());
    $calcService=new CalculationService($repository,$calculators);
    $requestService=new RequestService($repository,$calcService,$scheduleGenerator);
    $workflow=new WorkflowService($repository,$statusMachine);

    $require=static function(string $permission)use($principal):void{if(!$principal->can($permission))JsonResponse::error(403,'permission_denied','برای انجام این عملیات دسترسی کافی ندارید.');};
    $canReadRecord=static function(array $r)use($principal):bool{return$principal->isSuperAdmin()||$principal->can('loans.requests.read')&&(in_array($principal->userId,[(string)($r['created_by_user_id']??''),(string)($r['applicant_user_id']??''),(string)($r['assigned_bank_user_id']??'')],true)||in_array('admin',$principal->roles,true));};

    if($path==='/api/v1/meta/statuses'&&$method==='GET'){$require('loans.requests.read');JsonResponse::ok(['statuses'=>$statusMachine->labels()]);}
    if($path==='/api/v1/stats'&&$method==='GET'){$require('loans.requests.read');JsonResponse::ok($repository->stats());}

    if($path==='/api/v1/plans'&&$method==='GET'){$require('loans.plans.read');$q=$request->query();JsonResponse::ok($repository->listPlans((int)($q['page']??1),(int)($q['per_page']??25),isset($q['status'])?(string)$q['status']:null,isset($q['type'])?(string)$q['type']:null));}
    if($path==='/api/v1/plans'&&$method==='POST'){$require('loans.plans.manage');JsonResponse::ok(['plan'=>$planService->create($principal->userId,$request->json())],201);}
    if(preg_match('#^/api/v1/plans/([a-f0-9]{26})$#',$path,$m)){
        $id=$m[1];if($method==='GET'){$require('loans.plans.read');$plan=$repository->getPlan($id);if($plan===null)JsonResponse::error(404,'plan_not_found','طرح پیدا نشد.');JsonResponse::ok(['plan'=>$plan]);}
        if(in_array($method,['PUT','PATCH'],true)){$require('loans.plans.manage');JsonResponse::ok(['plan'=>$planService->revise($id,$principal->userId,$request->json())]);}
    }
    if(preg_match('#^/api/v1/plans/([a-f0-9]{26})/status$#',$path,$m)&&$method==='POST'){$require('loans.plans.manage');$body=$request->json();JsonResponse::ok(['plan'=>$planService->status($m[1],(string)($body['status']??''))]);}

    if($path==='/api/v1/calculations'&&$method==='POST'){$require('loans.calculate');$b=$request->json();JsonResponse::ok($calcService->calculate($principal->userId,(string)($b['plan_id']??''),InputValidator::money($b['amount']??0),InputValidator::integer($b['installment_count']??0),max(1,InputValidator::integer($b['installment_interval']??1)),isset($b['commission_override'])&&trim((string)$b['commission_override'])!==''?InputValidator::decimal($b['commission_override']):null,true),201);}

    if($path==='/api/v1/requests'&&$method==='GET'){$require('loans.requests.read');$q=$request->query();$global=$principal->isSuperAdmin()||in_array('admin',$principal->roles,true);$result=$repository->listRequests((int)($q['page']??1),(int)($q['per_page']??25),isset($q['status'])?(string)$q['status']:null,trim((string)($q['q']??'')),$global?null:$principal->userId);JsonResponse::ok($result);}
    if($path==='/api/v1/requests'&&$method==='POST'){$require('loans.requests.create');$key=trim((string)($request->header('idempotency-key')??''));JsonResponse::ok(['request'=>$requestService->create($principal->userId,$request->json(),$key)],201);}

    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})$#',$path,$m)){
        $loan=$repository->getRequest($m[1]);if($loan===null)JsonResponse::error(404,'request_not_found','پرونده پیدا نشد.');if(!$canReadRecord($loan)&&!$principal->isSuperAdmin())JsonResponse::error(403,'record_access_denied','دسترسی به این پرونده مجاز نیست.');
        if($method==='GET'){$require('loans.requests.read');JsonResponse::ok(['request'=>$loan,'allowed_transitions'=>$workflow->allowed($loan)]);}
        if(in_array($method,['PATCH','PUT'],true)){$require('loans.requests.update');$fields=InputValidator::draftFields($request->json());JsonResponse::ok(['request'=>$repository->updateDraft($m[1],$fields,$principal->userId)]);}
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/document-requirements$#',$path,$m)&&$method==='GET'){$require('loans.requests.read');$loan=$repository->getRequest($m[1]);if($loan===null)JsonResponse::error(404,'request_not_found','پرونده پیدا نشد.');if(!$canReadRecord($loan)&&!$principal->isSuperAdmin())JsonResponse::error(403,'record_access_denied','دسترسی به این پرونده مجاز نیست.');$required=is_array($loan['required_documents']??null)?$loan['required_documents']:[];JsonResponse::ok(['request_id'=>$m[1],'plan_id'=>(string)$loan['plan_id'],'plan_version_id'=>(string)$loan['plan_version_id'],'plan_name'=>(string)($loan['plan_name']??''),'plan_version'=>(int)($loan['plan_version']??0),'required_documents'=>$required]);}

    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/transition$#',$path,$m)&&$method==='POST'){$require('loans.requests.transition');$loan=$repository->getRequest($m[1]);if($loan===null)JsonResponse::error(404,'request_not_found','پرونده پیدا نشد.');if(!$canReadRecord($loan)&&!$principal->isSuperAdmin())JsonResponse::error(403,'record_access_denied','دسترسی به این پرونده مجاز نیست.');$b=$request->json();$next=(string)($b['status']??'');if(in_array($next,['docs_verified','granted'],true)){$token=(string)($request->bearer()??'');$completion=$documentsClient->completion($m[1],$token);if(!$completion->ok()){JsonResponse::error($completion->status===0||$completion->status>=500?503:409,$completion->status===0||$completion->status>=500?'documents_unavailable':'required_documents_incomplete',$completion->status===0||$completion->status>=500?'سرویس مدارک برای بررسی تکمیل پرونده در دسترس نیست.':'امکان تأیید مدارک وجود ندارد؛ وضعیت مدارک پرونده را بررسی کنید.');}$cd=is_array($completion->json['data']??null)?$completion->json['data']:[];if(($cd['complete']??false)!==true)JsonResponse::error(409,'required_documents_incomplete','همه مدارک الزامی نسخه طرح تأیید نشده‌اند. ابتدا مدارک پرونده را تکمیل و بازبینی کنید.',['missing'=>$cd['missing']??[],'pending'=>$cd['pending']??[],'rejected'=>$cd['rejected']??[]]);}JsonResponse::ok(['request'=>$workflow->transition($m[1],$principal->userId,$next,trim((string)($b['reason']??'')),array_key_exists('assigned_bank_user_id',$b)?(string)$b['assigned_bank_user_id']:null,'api')]);}
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/history$#',$path,$m)&&$method==='GET'){$require('loans.requests.audit');$loan=$repository->getRequest($m[1]);if($loan===null)JsonResponse::error(404,'request_not_found','پرونده پیدا نشد.');if(!$canReadRecord($loan)&&!$principal->isSuperAdmin())JsonResponse::error(403,'record_access_denied','دسترسی به این پرونده مجاز نیست.');JsonResponse::ok(['history'=>$repository->history($m[1]),'events'=>$repository->events($m[1])]);}
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/schedule$#',$path,$m)&&$method==='GET'){$require('loans.requests.read');$loan=$repository->getRequest($m[1]);if($loan===null)JsonResponse::error(404,'request_not_found','پرونده پیدا نشد.');if(!$canReadRecord($loan)&&!$principal->isSuperAdmin())JsonResponse::error(403,'record_access_denied','دسترسی به این پرونده مجاز نیست.');JsonResponse::ok(['schedule'=>$repository->schedule($m[1])]);}
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/guarantors$#',$path,$m)){
        $loan=$repository->getRequest($m[1]);if($loan===null)JsonResponse::error(404,'request_not_found','پرونده پیدا نشد.');if(!$canReadRecord($loan)&&!$principal->isSuperAdmin())JsonResponse::error(403,'record_access_denied','دسترسی به این پرونده مجاز نیست.');
        if($method==='GET'){$require('loans.requests.read');JsonResponse::ok(['guarantors'=>$repository->guarantors($m[1])]);}
        if(in_array($method,['PUT','POST'],true)){$require('loans.requests.update');$b=$request->json();$guarantors=InputValidator::guarantors($b['guarantors']??[]);$repository->replaceGuarantors($m[1],$guarantors,$principal->userId);JsonResponse::ok(['guarantors'=>$repository->guarantors($m[1])]);}
    }
    JsonResponse::error(404,'route_not_found','مسیر API در این نسخه وجود ندارد.');
}catch(InvalidArgumentException $e){JsonResponse::error(422,'validation_failed',$e->getMessage());}
catch(PDOException $e){error_log('[loans] database error: '.$e->getMessage());JsonResponse::error(503,'database_error','عملیات پایگاه داده کامل نشد. گزارش فنی ثبت شده است.');}
catch(Throwable $e){error_log('[loans] unexpected error: '.get_class($e).': '.$e->getMessage());JsonResponse::error(500,'internal_error','خطای غیرمنتظره‌ای رخ داد. دوباره تلاش کنید.');}
