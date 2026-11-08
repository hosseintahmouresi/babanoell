<?php
declare(strict_types=1);

use Babanoell\Loans\Domain\InputValidator;
use Babanoell\Loans\Domain\PlanRules;
use Babanoell\Loans\Domain\PlanSettingsValidator;
use Babanoell\Loans\Domain\CoreCatalog;
use Babanoell\Loans\Domain\CoreSchema;
use Babanoell\Loans\Domain\WorkflowCatalog;
use Babanoell\Loans\Domain\RequestOrigin;
use Babanoell\Loans\Domain\NoteAttachmentPolicy;
use Babanoell\Loans\Domain\CreditValidationPolicy;
use Babanoell\Loans\Http\JsonResponse;
use Babanoell\Loans\Http\Request;
use Babanoell\Loans\Security\Authenticator;
use Babanoell\Loans\Security\Principal;
use Babanoell\Loans\Security\AccessDeniedException;
use Babanoell\Loans\Security\RequestAccessPolicy;
use Babanoell\Loans\Service\CalculationService;
use Babanoell\Loans\Service\PlanService;
use Babanoell\Loans\Service\RequestService;
use Babanoell\Loans\Service\WorkflowService;
use Babanoell\Loans\Service\CaseWorkflowService;
use Babanoell\Loans\Service\CreditValidationService;
use Babanoell\Loans\Service\BankCreditService;
use Babanoell\Loans\Workflow\TaskRepository;
use Babanoell\Loans\Workflow\TaskService;

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
\Babanoell\Loans\Platform\RequestContext::initialize($request->header('X-Request-Id'),'loans');
if(str_starts_with($path,'/api/v1/')){
$serviceAuth=\Babanoell\Loans\Platform\ServiceAuth::verify($request->header(\Babanoell\Loans\Platform\ServiceAuth::HEADER_SERVICE),$request->header(\Babanoell\Loans\Platform\ServiceAuth::HEADER_TIMESTAMP),$request->header(\Babanoell\Loans\Platform\ServiceAuth::HEADER_SIGNATURE),$method,$path,$request->rawBody(),$config->serviceAuthSecret(),$config->serviceAuthMode());
if(!$serviceAuth['ok']) JsonResponse::error($serviceAuth['error']==='service_auth_not_configured'?503:401,(string)$serviceAuth['error'],'احراز هویت سرویس فراخوان نامعتبر است.');
if($serviceAuth['authenticated']===true) \Babanoell\Loans\Platform\RequestContext::setCallerService(is_string($serviceAuth['service'])?$serviceAuth['service']:null);
}

try{
    if($path==='/health') JsonResponse::send(200,['service'=>$config->serviceName(),'status'=>'ok','environment'=>$config->environment(),'version'=>(require $root.'/config/version.php')['version'],'timestamp'=>gmdate('c')]);
    if($path==='/healthz'){
        $dbOk=false;$core=['ready'=>false,'missing'=>[]];try{$dbOk=(int)$pdo->query('SELECT 1')->fetchColumn()===1;if($dbOk)$core=CoreSchema::status($pdo);}catch(Throwable){}
        $ih=$identityClient->health();$identityOk=$ih->ok()&&(($ih->json['status']??null)==='ok');$dh=$documentsClient->health();$documentsOk=$dh->ok()&&(($dh->json['status']??null)==='ok');$ph=$partnersClient->health();$partnersOk=$ph->ok()&&(($ph->json['status']??null)==='ok');$fh=$financeClient->health();$financeOk=$fh->ok()&&(($fh->json['status']??null)==='ok');$ok=$dbOk&&($core['ready']??false)&&$identityOk&&$documentsOk&&$partnersOk&&$financeOk;
        JsonResponse::send($ok?200:503,['service'=>$config->serviceName(),'status'=>$ok?'ok':'error','database'=>$dbOk?'ok':'error','core_schema'=>(($core['ready']??false)?'ok':'error'),'identity'=>$identityOk?'ok':'unavailable','documents'=>$documentsOk?'ok':'unavailable','partners'=>$partnersOk?'ok':'unavailable','finance'=>$financeOk?'ok':'unavailable','environment'=>$config->environment(),'version'=>(require $root.'/config/version.php')['version'],'timestamp'=>gmdate('c')]);
    }
    if(!str_starts_with($path,'/api/v1/')) JsonResponse::error(404,'route_not_found','مسیر درخواستی وجود ندارد.');
    if($path==='/api/v1/meta/platform-contract'&&$method==='GET') JsonResponse::ok(['contract'=>\Babanoell\Loans\Platform\ServiceBoundary::describe(),'service_auth'=>['mode'=>$config->serviceAuthMode(),'configured'=>$config->serviceAuthSecret()!=='']]);

    if(str_starts_with($path,'/api/v1/admin/finance-shadow')){
        if(($serviceAuth['authenticated']??false)!==true||\Babanoell\Loans\Platform\RequestContext::callerService()!=='finance')JsonResponse::error(401,'service_auth_required','این مسیر فقط برای سرویس Finance با احراز هویت سرویس معتبر است.');
        if($path==='/api/v1/admin/finance-shadow'&&$method==='GET'){ $q=$request->query();JsonResponse::ok($repository->financeShadowPage((int)($q['page']??1),(int)($q['per_page']??100))); }

        if($path==='/api/v1/admin/finance-shadow/merchant-reset-sync'&&$method==='POST'){
            $body=$request->json();$id=(string)($body['request_id']??'');
            if(!preg_match('/^[a-f0-9]{26}$/',$id))JsonResponse::error(422,'invalid_request','شناسه پرونده نامعتبر است.');
            $actor=(string)($body['actor_user_id']??'');
            if(!preg_match('/^[a-f0-9]{26}$/',$actor))JsonResponse::error(422,'invalid_actor','شناسه مدیر نامعتبر است.');
            JsonResponse::ok(['projection'=>$repository->syncMerchantSettlementResetShadow($id,$actor,trim((string)($body['reason']??'')))]);
        }
        if(preg_match('#^/api/v1/admin/finance-shadow/greenpay-context/([a-f0-9]{26})$#',$path,$debitLoan)&&$method==='GET'){
            $loan=$repository->getRequest($debitLoan[1]);if($loan===null)JsonResponse::error(404,'loan_not_found','پرونده پیدا نشد.');
            JsonResponse::ok(['request'=>array_intersect_key($loan,array_flip(['id','request_no','status','requested_amount','facility_amount','plan_id','plan_version_id','row_version']))]);
        }
        if($path==='/api/v1/admin/finance-shadow/bank-cycle-project'&&$method==='POST'){
            $b=$request->json();$cycle=(string)($b['cycle_id']??'');$owner=(string)($b['bank_user_id']??'');$date=(string)($b['settlement_date']??'');$notes=trim((string)($b['notes']??''));$ids=$b['request_ids']??null;
            if(!preg_match('/^[a-f0-9]{26}$/D',$cycle)||!preg_match('/^[a-f0-9]{26}$/D',$owner))JsonResponse::error(422,'invalid_bank_cycle','شناسه سیکل یا کارشناس معتبر نیست.');
            if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date))JsonResponse::error(422,'invalid_date','تاریخ نامعتبر است.');
            [$y,$mon,$day]=array_map('intval',explode('-',$date));if(!checkdate($mon,$day,$y))JsonResponse::error(422,'invalid_date','تاریخ نامعتبر است.');
            if(!is_array($ids)||count($ids)<1||count($ids)>100||count(array_unique($ids))!==count($ids))JsonResponse::error(422,'invalid_items','فهرست پرونده‌ها نامعتبر است.');
            foreach($ids as $id)if(!is_string($id)||!preg_match('/^[a-f0-9]{26}$/D',$id))JsonResponse::error(422,'invalid_items','شناسه پرونده نامعتبر است.');
            if(mb_strlen($notes)>3000)JsonResponse::error(422,'invalid_note','توضیحات طولانی است.');
            sort($ids,SORT_STRING);
            $projection=$repository->projectBankCycle($cycle,$owner,$date,$notes,$ids);
            $bankTasks=new TaskService(new TaskRepository($pdo),$repository);
            foreach($ids as $id)$bankTasks->completeWorkflowCode($id,'bank_settlement',$owner,'تکمیل با سیکل تسویه بانک: '.$cycle);
            JsonResponse::ok(['projection'=>$projection]);
        }
        if($path==='/api/v1/admin/finance-shadow/cycles'&&$method==='GET')JsonResponse::ok($repository->financeShadowCycles());
        if($path==='/api/v1/admin/finance-shadow/receipts'&&$method==='GET')JsonResponse::ok($repository->financeShadowReceipts());
        JsonResponse::error(404,'route_not_found','مسیر انتقال مالی وجود ندارد.');
    }

    if(preg_match('#^/api/v1/integrations/verifications/([a-f0-9]{26})/(context|result)$#',$path,$im)){
        if(($serviceAuth['authenticated']??false)!==true||\Babanoell\Loans\Platform\RequestContext::callerService()!=='integrations')JsonResponse::error(401,'service_auth_required','این مسیر فقط برای سرویس Integrations با احراز هویت سرویس معتبر است.');
        $bankCreditIntegration=new BankCreditService($repository);
        if($im[2]==='context'&&$method==='GET')JsonResponse::ok(['verification'=>$bankCreditIntegration->integrationContext($im[1])]);
        if($im[2]==='result'&&$method==='POST'){
            $payload=$request->json();$context=$repository->getVerificationRequest($im[1]);if(($context['provider']??'')==='ics24'&&($payload['status']??'')==='verified')$verification=$bankCreditIntegration->completeProviderReport($im[1],$payload,$config->serviceAuthSecret());else $verification=$bankCreditIntegration->complete($im[1],'service:integrations',$payload);
            $taskServiceIntegration=new TaskService(new TaskRepository($pdo),$repository);
            $taskServiceIntegration->syncForRequest((string)$verification['request_id'],'service:integrations','request.verification.completed');
            JsonResponse::ok(['verification'=>$verification]);
        }
        JsonResponse::error(405,'method_not_allowed','متد این مسیر معتبر نیست.');
    }

    $auth=(new Authenticator($identityClient))->authenticate($request);if($auth['principal']===null){$e=$auth['error'];JsonResponse::error($e['status'],$e['code'],$e['message']);}
    /** @var Principal $principal */$principal=$auth['principal'];
    $planService=new PlanService($repository,new PlanSettingsValidator());
    $calcService=new CalculationService($repository,$calculators);
    $requestService=new RequestService($repository,$calcService,$scheduleGenerator);
    $workflow=new WorkflowService($repository,$statusMachine);
    $caseWorkflow=new CaseWorkflowService($repository);
    $creditValidation=new CreditValidationService($repository);
    $bankCredit=new BankCreditService($repository);
    $taskRepository=new TaskRepository($pdo);
    $taskService=new TaskService($taskRepository,$repository);

    $require=static function(string $permission)use($principal):void{if(!$principal->can($permission))JsonResponse::error(403,'permission_denied','برای انجام این عملیات دسترسی کافی ندارید.');};
    $roles=$principal->roles;

    if($path==='/api/v1/admin/reports/daily'&&$method==='GET'){
        if(!$principal->isSuperAdmin())JsonResponse::error(403,'permission_denied','این گزارش فقط برای مدیر ارشد مجاز است.');
        $q=$request->query();$from=(string)($q['from']??'');$to=(string)($q['to']??'');
        try{JsonResponse::ok(\Babanoell\Loans\Reports\DailyReport::collect($pdo,$from,$to));}
        catch(\InvalidArgumentException){JsonResponse::error(422,'invalid_report_range','بازه زمانی گزارش معتبر نیست یا بیشتر از ۱۸۰ روز است.');}
    }

    $isPlatformAdmin=$principal->isSuperAdmin()||in_array('admin',$roles,true);
    $isAcceptor=in_array('acceptor',$roles,true)&&!$isPlatformAdmin;
    $isApplicant=in_array('applicant',$roles,true)&&!$isPlatformAdmin;
    $isSelfService=$isAcceptor||$isApplicant;
    $isBankExpert=in_array('bank_expert',$roles,true)&&!$isPlatformAdmin;
    $hasGlobalRequestAccess=$principal->isSuperAdmin();
    $canAssignBank=$isPlatformAdmin||$principal->can('loans.requests.assign_bank');
    $canManagePlans=$isPlatformAdmin||$principal->can('loans.plans.manage');
    $partnerMerchantIds=[];$partnerMerchant=null;$partnerMembershipVerified=false;$partnerCommission=null;$partnerPlanPolicy=null;
    if($isAcceptor){
        $pm=$partnersClient->myMerchant((string)($request->bearer()??''));
        if($pm->ok()&&is_array($pm->json['data']??null)){
            $pd=$pm->json['data'];
            if(is_array($pd['disabled_plan_ids']??null)&&is_array($pd['plan_permissions']??null)){
                $disabled=$pd['disabled_plan_ids'];$permissions=[];$valid=count($disabled)<=1000;
                foreach($disabled as $id)if(!is_string($id)||!preg_match('/^[a-f0-9]{26}$/D',$id))$valid=false;
                foreach($pd['plan_permissions'] as $item){if(!is_array($item)||!is_string($item['plan_id']??null)||!preg_match('/^[a-f0-9]{26}$/D',$item['plan_id'])||!in_array($item['status']??null,['active','inactive'],true)){$valid=false;continue;}$permissions[$item['plan_id']]=$item['status'];}
                if($valid)$partnerPlanPolicy=['disabled_plan_ids'=>$disabled,'permissions'=>$permissions];
            }

            if(array_key_exists('merchant',$pd)&&$pd['merchant']===null)$partnerMembershipVerified=true;
            elseif(is_array($pd['merchant']??null)&&is_string($pd['merchant']['id']??null)&&preg_match('/^[a-f0-9]{26}$/D',$pd['merchant']['id'])&&($pd['merchant']['status']??null)==='active'){
                $partnerMembershipVerified=true;$partnerMerchant=$pd['merchant'];$partnerMerchantIds[]=$partnerMerchant['id'];$partnerCommission=$pd['commission_percent']??null;if($partnerCommission!==null&&(!is_numeric($partnerCommission)||(float)$partnerCommission<0||(float)$partnerCommission>100))JsonResponse::error(503,'merchant_commission_invalid','کمیسیون پذیرنده از مرجع قابل تأیید نیست.');
            }
        }
    }
    $visibilityScope=$principal->visibilityScope();if($partnerMerchantIds!==[])$visibilityScope['merchants']=array_values(array_unique(array_merge(is_array($visibilityScope['merchants']??null)?$visibilityScope['merchants']:[],$partnerMerchantIds)));
    if($principal->can('loans.requests.global'))$visibilityScope['global'][]='*';
    $recordPolicy=new RequestAccessPolicy($principal,$partnerMerchantIds);
    $canReadRecord=static fn(array $record):bool=>$recordPolicy->canRead($record);
    $primaryRole=(string)($roles[0]??'');
    $loadCase=static function(string $id)use($repository,$canReadRecord):array{$loan=$repository->getRequest($id);if($loan===null)JsonResponse::error(404,'request_not_found','پرونده پیدا نشد.');if(!$canReadRecord($loan))JsonResponse::error(403,'record_access_denied','دسترسی به این پرونده مجاز نیست.');return$loan;};
    $visibleTransitions=static function(array $loan)use($workflow,$principal,$isBankExpert,$repository):array{
        if(!$principal->can('loans.requests.transition'))return[];
        $allowed=array_values(array_filter($workflow->allowed($loan),static fn(array $item):bool=>(string)($item['code']??'')!=='granted'));
        if(in_array((string)($loan['status']??''),['draft','need_correction'],true)&&!$repository->creditSubmissionState($loan)['ready'])$allowed=array_values(array_filter($allowed,static fn(array $item):bool=>!in_array((string)($item['code']??''),['submitted','under_review'],true)));
        if(!$isBankExpert)return$allowed;
        $from=(string)($loan['status']??'');
        $bankAllowed=$from==='expert_approved'
            ?['docs_completion','need_correction','rejected']
            :($from==='docs_verified'?['docs_completion','rejected']:[]);
        return array_values(array_filter($allowed,static fn(array $item):bool=>in_array((string)($item['code']??''),$bankAllowed,true)));
    };

    $notificationRepository=new \Babanoell\Loans\Notification\NotificationRepository($pdo);
    if($path==='/api/v1/notifications'&&$method==='GET')JsonResponse::ok($notificationRepository->inbox($principal->userId,(int)($request->query()['page']??1)));
    if(preg_match('#^/api/v1/notifications/([a-f0-9]{26})/read$#',$path,$notice)&&$method==='POST'){
        $notificationRepository->markRead($notice[1],$principal->userId);JsonResponse::ok(['read'=>true]);
    }
    if(str_starts_with($path,'/api/v1/admin/notifications')){
        if(!$isPlatformAdmin)JsonResponse::error(403,'notification_admin_denied','مدیریت ارسال‌ها فقط برای مدیر سامانه مجاز است.');
        if($path==='/api/v1/admin/notifications/settings'){
            if($method==='GET')JsonResponse::ok($notificationRepository->settings());
            if($method==='POST'){
                $body=$request->json();
                try{$saved=$notificationRepository->saveSettings((int)($body['revision']??0),(array)($body['settings']??[]),$principal->userId);}
                catch(\DomainException $error){JsonResponse::error(409,'notification_revision_conflict',$error->getMessage());}
                $repository->recordEvent(null,'notification.settings.updated',$principal->userId,'allowed',['revision'=>$saved['revision']]);JsonResponse::ok($saved);
            }
        }
        if($path==='/api/v1/admin/notifications/deliveries'&&$method==='GET')JsonResponse::ok($notificationRepository->deliveries((int)($request->query()['page']??1)));
        if(preg_match('#^/api/v1/admin/notifications/deliveries/([a-f0-9]{26})/retry$#',$path,$delivery)&&$method==='POST'){
            try{$notificationRepository->retry($delivery[1],$principal->userId);}
            catch(\DomainException $error){JsonResponse::error(409,'notification_retry_denied',$error->getMessage());}
            JsonResponse::ok(['queued'=>true]);
        }
        if(preg_match('#^/api/v1/admin/notifications/deliveries/([a-f0-9]{26})/reconcile$#',$path,$delivery)&&$method==='POST'){
            try{$notificationRepository->reconcile($delivery[1],$principal->userId,$request->json());}
            catch(\DomainException $error){JsonResponse::error(409,'notification_reconciliation_denied',$error->getMessage());}
            JsonResponse::ok(['reconciled'=>true]);
        }
        JsonResponse::error(404,'notification_route_not_found','مسیر اعلان پیدا نشد.');
    }

    if($path==='/api/v1/admin/request-numbering'){
        if(!$isPlatformAdmin)JsonResponse::error(403,'numbering_admin_denied','تنظیم شماره پرونده فقط برای مدیر مجاز است.');
        $numbers=new \Babanoell\Loans\Repository\RequestNumberRepository($pdo);
        if($method==='GET')JsonResponse::ok(['sequence'=>$numbers->settings()]);
        if($method==='POST'){$body=$request->json();$step=filter_var($body['step']??null,FILTER_VALIDATE_INT);if($step===false)JsonResponse::error(422,'invalid_number_step','گام معتبر وارد کنید.');
            try{$saved=$numbers->save((int)($body['revision']??0),$step,$principal->userId);}catch(\DomainException $e){JsonResponse::error(409,'numbering_revision_conflict',$e->getMessage());}JsonResponse::ok(['sequence'=>$saved]);}
    }

    if($path==='/api/v1/meta/statuses'&&$method==='GET'){$require('loans.requests.read');JsonResponse::ok(['statuses'=>$statusMachine->labels()]);}
    if($path==='/api/v1/meta/plans'&&$method==='GET'){$require('loans.plans.read');JsonResponse::ok(['statuses'=>['draft','active','inactive','archived'],'occupations'=>PlanRules::OCCUPATIONS,'visibility_contexts'=>PlanRules::VISIBILITY_CONTEXTS,'required_documents'=>\Babanoell\Loans\Domain\RequiredDocumentCatalog::all(),'contract_modes'=>PlanRules::CONTRACT_MODES]);}
    if($path==='/api/v1/meta/core'&&$method==='GET'){$require('loans.requests.read');JsonResponse::ok(['core'=>CoreCatalog::describe(),'schema'=>CoreSchema::status($pdo)]);}
    if($path==='/api/v1/stats'&&$method==='GET'){$require('loans.requests.read');JsonResponse::ok($repository->stats($hasGlobalRequestAccess?null:$visibilityScope));}
    if($path==='/api/v1/dashboard/summary'){
        $require('loans.requests.read');
        if($method!=='GET')JsonResponse::error(405,'method_not_allowed','این مسیر فقط برای دریافت آمار است.');
        JsonResponse::ok((new \Babanoell\Loans\Service\DashboardService($repository))->summary($principal,$partnerMerchantIds));
    }

    if($path==='/api/v1/tasks/summary'&&$method==='GET'){$require('loans.requests.read');$taskRepository->runSlaSweep('system:auto');JsonResponse::ok($taskService->summaryFor($principal));}
    if($path==='/api/v1/tasks'&&$method==='GET'){$require('loans.requests.read');$taskRepository->runSlaSweep('system:auto');$result=$taskService->listFor($principal,$request->query());JsonResponse::ok($result,200,['pagination'=>\Babanoell\Loans\Http\Pagination::fromResult($result)]);}
    if($path==='/api/v1/tasks/sla/run'&&$method==='POST'){JsonResponse::ok($taskService->runSla($principal));}
    if($path==='/api/v1/tasks/sla/policies'&&$method==='GET'){JsonResponse::ok(['items'=>$taskService->policies($principal)]);}
    if(preg_match('#^/api/v1/tasks/sla/policies/([a-z0-9_\-]{2,64})$#',$path,$m)&&in_array($method,['POST','PUT','PATCH'],true)){JsonResponse::ok(['policy'=>$taskService->updatePolicy($principal,$m[1],$request->json())]);}
    if($path==='/api/v1/tasks/report'&&$method==='GET'){JsonResponse::ok($taskService->report($principal,(int)($request->query()['days']??30)));}
    if(preg_match('#^/api/v1/tasks/([a-f0-9]{26})$#',$path,$m)&&$method==='GET'){$require('loans.requests.read');JsonResponse::ok(['task'=>$taskService->getFor($principal,$m[1])]);}
    if(preg_match('#^/api/v1/tasks/([a-f0-9]{26})/(claim|start|complete|release|reassign|cancel)$#',$path,$m)&&$method==='POST'){$require('loans.requests.read');$b=$request->json();$action=$m[2];$task=$action==='claim'?$taskService->claim($principal,$m[1]):($action==='start'?$taskService->start($principal,$m[1]):($action==='complete'?$taskService->complete($principal,$m[1],(string)($b['note']??'')):($action==='release'?$taskService->release($principal,$m[1],(string)($b['note']??'')):($action==='reassign'?$taskService->reassign($principal,$m[1],isset($b['user_id'])?(string)$b['user_id']:null,(string)($b['note']??'')):$taskService->cancel($principal,$m[1],(string)($b['note']??''))))));JsonResponse::ok(['task'=>$task]);}

    if($path==='/api/v1/plans'&&$method==='GET'){
        $require('loans.plans.read');$q=$request->query();
        $status=$canManagePlans?(isset($q['status'])?(string)$q['status']:null):'active';
        $context=trim((string)($q['context']??''));
        if(!$canManagePlans&&$context==='')$context=$isApplicant?'applicant_request':($isAcceptor?'merchant_request':'');
        $policy=null;if($isAcceptor&&in_array($context,['merchant_request','calculator'],true)){
            if(!$partnerMembershipVerified||$partnerPlanPolicy===null)JsonResponse::error(503,'merchant_plan_policy_unavailable','محدودیت طرح‌های پذیرنده از سرویس شرکا قابل تأیید نیست.');$policy=$partnerPlanPolicy;
        }
        $result=$context!==''?$repository->listPlansForContext((int)($q['page']??1),(int)($q['per_page']??25),$context,isset($q['type'])?(string)$q['type']:null,$policy):$repository->listPlans((int)($q['page']??1),(int)($q['per_page']??25),$status,isset($q['type'])?(string)$q['type']:null);JsonResponse::ok($result,200,['pagination'=>\Babanoell\Loans\Http\Pagination::fromResult($result)]);
    }
    if($path==='/api/v1/plans/validate-selection'&&$method==='POST'){
        $require('loans.plans.manage');$ids=$request->json()['plan_ids']??null;
        if(!is_array($ids)||count($ids)>1000)throw new \InvalidArgumentException('فهرست طرح‌ها معتبر نیست.');
        foreach($ids as $id)if(!is_string($id)||!preg_match('/^[a-f0-9]{26}$/D',$id))throw new \InvalidArgumentException('شناسهٔ طرح معتبر نیست.');
        $ids=array_values(array_unique($ids));$found=[];
        if($ids!==[]){$q=$pdo->prepare('SELECT id FROM installment_plans WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).')');$q->execute($ids);$found=$q->fetchAll(PDO::FETCH_COLUMN);}
        if(count($found)!==count($ids))JsonResponse::error(422,'invalid_plan_selection','یک یا چند طرح انتخاب‌شده موجود نیست.');
        JsonResponse::ok(['valid_ids'=>$found]);
    }
    if($path==='/api/v1/plans'&&$method==='POST'){$require('loans.plans.manage');JsonResponse::ok(['plan'=>$planService->create($principal->userId,$request->json())],201);}
    if(preg_match('#^/api/v1/plans/([a-f0-9]{26})$#',$path,$m)){
        $id=$m[1];
        if($method==='GET'){
            $require('loans.plans.read');$plan=$repository->getPlan($id);
            if($plan===null||(!$canManagePlans&&(string)($plan['status']??'')!=='active'))JsonResponse::error(404,'plan_not_found','طرح پیدا نشد.');
            JsonResponse::ok(['plan'=>$plan]);
        }
        if(in_array($method,['PUT','PATCH'],true)){$require('loans.plans.manage');JsonResponse::ok(['plan'=>$planService->revise($id,$principal->userId,$request->json())]);}
    }
    if(preg_match('#^/api/v1/plans/([a-f0-9]{26})/status$#',$path,$m)&&$method==='POST'){$require('loans.plans.manage');$body=$request->json();JsonResponse::ok(['plan'=>$planService->status($m[1],(string)($body['status']??''))]);}

    if(preg_match('#^/api/v1/plans/([a-f0-9]{26})/versions$#',$path,$m)&&$method==='GET'){$require('loans.plans.manage');JsonResponse::ok(['items'=>$repository->listPlanVersions($m[1])]);}
    if(preg_match('#^/api/v1/plans/([a-f0-9]{26})/duplicate$#',$path,$m)&&$method==='POST'){$require('loans.plans.manage');JsonResponse::ok(['plan'=>$planService->duplicate($m[1],$principal->userId,$request->json())],201);}
    if(preg_match('#^/api/v1/plans/([a-f0-9]{26})/order$#',$path,$m)&&$method==='POST'){$require('loans.plans.manage');$body=$request->json();JsonResponse::ok(['plan'=>$planService->order($m[1],(int)($body['display_order']??0))]);}
    if(preg_match('#^/api/v1/plans/([a-f0-9]{26})/merchant-access$#',$path,$m)&&$method==='POST'){$require('loans.plans.manage');JsonResponse::ok(['permission'=>$planService->merchantAccess($m[1],$principal->userId,$request->json())]);}
    if(preg_match('#^/api/v1/plans/([a-f0-9]{26})/documents$#',$path,$m)&&$method==='GET'){$require('loans.plans.read');$plan=$repository->getPlan($m[1]);if($plan===null)JsonResponse::error(404,'plan_not_found','طرح پیدا نشد.');$q=$request->query();JsonResponse::ok(['plan_id'=>$m[1],'occupation'=>(string)($q['occupation']??''),'required_documents'=>PlanRules::requiredDocuments($plan,(string)($q['occupation']??'')),'required_document_descriptions'=>$plan['rules']['required_document_descriptions']??[]]);}

    if($path==='/api/v1/calculations/preview'&&$method==='POST'){
        $require('loans.plans.manage');$b=$request->json();$commission=isset($b['commission_override'])&&trim((string)$b['commission_override'])!==''?InputValidator::decimal($b['commission_override']):null;
        JsonResponse::ok($calcService->calculate($principal->userId,(string)($b['plan_id']??''),InputValidator::money($b['amount']??0),InputValidator::integer($b['installment_count']??0),max(1,InputValidator::integer($b['installment_interval']??1)),$commission,false));
    }

    if($path==='/api/v1/calculations'&&$method==='POST'){
        $require('loans.calculate');$b=$request->json();
        $context=$b['plan_context']??($isApplicant&&!$isAcceptor?'applicant_request':($isAcceptor?'merchant_request':'calculator'));
        if(!is_string($context)||!isset(PlanRules::VISIBILITY_CONTEXTS[$context])||(!$canManagePlans&&$context==='merchant_request'&&!$isAcceptor)||(!$canManagePlans&&$context==='applicant_request'&&!$isApplicant))JsonResponse::error(422,'invalid_plan_context','مسیر محاسبه معتبر نیست.');
        $plan=$repository->getPlan((string)($b['plan_id']??''));
        if($plan===null||!PlanRules::visibleIn($plan,$context))JsonResponse::error(422,'plan_not_available','طرح در این مسیر قابل استفاده نیست.');
        if($isAcceptor&&in_array($context,['merchant_request','calculator'],true)){
            if(!$partnerMembershipVerified||$partnerPlanPolicy===null)JsonResponse::error(503,'merchant_plan_policy_unavailable','محدودیت طرح‌های پذیرنده قابل تأیید نیست.');
            if(!PlanRules::availableToMerchant($plan,$partnerPlanPolicy))JsonResponse::error(403,'plan_access_denied','این طرح برای حساب پذیرنده غیرفعال است.');
        }
        $commission=$canManagePlans&&isset($b['commission_override'])&&trim((string)$b['commission_override'])!==''?InputValidator::decimal($b['commission_override']):null;
        if($isAcceptor&&(!$isApplicant||($b['plan_context']??'merchant_request')==='merchant_request')){if(!$partnerMembershipVerified||$partnerMerchant===null)JsonResponse::error(503,'merchant_commission_unavailable','پذیرنده و کمیسیون از سرویس شرکا قابل تأیید نیست.');$commission=$partnerCommission===null?null:(float)$partnerCommission;}
        JsonResponse::ok($calcService->calculate($principal->userId,(string)($b['plan_id']??''),InputValidator::money($b['amount']??0),InputValidator::integer($b['installment_count']??0),max(1,InputValidator::integer($b['installment_interval']??1)),$commission,true),201);
    }

    if($path==='/api/v1/compat/partners/export'&&$method==='GET'){
        if(!$isPlatformAdmin)JsonResponse::error(403,'permission_denied','انتقال داده‌های قبلی شرکا فقط برای مدیر سامانه مجاز است.');
        $caller=\Babanoell\Loans\Platform\RequestContext::callerService();if($caller!==null&&!in_array($caller,['partners','panel'],true))JsonResponse::error(403,'caller_service_denied','این مسیر فقط برای انتقال کنترل‌شده شرکا مجاز است.');
        $q=$request->query();$entity=strtolower(trim((string)($q['entity']??'')));$r=$repository->exportPartnerShadow($entity,(int)($q['page']??1),(int)($q['per_page']??200));JsonResponse::ok($r,200,['pagination'=>\Babanoell\Loans\Http\Pagination::fromResult($r)]);
    }
    if($path==='/api/v1/compat/partners/merchant'&&$method==='POST'){
        if(!$principal->can('partners.merchants.manage')&&!$isPlatformAdmin)JsonResponse::error(403,'permission_denied','اجازه همگام‌سازی پذیرنده را ندارید.');
        $caller=\Babanoell\Loans\Platform\RequestContext::callerService();if($caller!==null&&$caller!=='partners')JsonResponse::error(403,'caller_service_denied','این مسیر فقط برای سرویس شرکا مجاز است.');
        JsonResponse::ok(['merchant'=>$repository->upsertShadowMerchant($request->json())]);
    }
    if($path==='/api/v1/compat/partners/plan-permission'&&$method==='POST'){
        if(!$principal->can('partners.plan_access.manage')&&!$isPlatformAdmin)JsonResponse::error(403,'permission_denied','اجازه همگام‌سازی دسترسی طرح را ندارید.');
        $caller=\Babanoell\Loans\Platform\RequestContext::callerService();if($caller!==null&&$caller!=='partners')JsonResponse::error(403,'caller_service_denied','این مسیر فقط برای سرویس شرکا مجاز است.');
        $b=$request->json();$merchantId=strtolower(trim((string)($b['merchant_id']??'')));$planId=strtolower(trim((string)($b['plan_id']??'')));$status=strtolower(trim((string)($b['status']??'active')));if(!preg_match('/^[a-f0-9]{26}$/',$merchantId)||!preg_match('/^[a-f0-9]{26}$/',$planId)||!in_array($status,['active','inactive'],true))JsonResponse::error(422,'validation_failed','اطلاعات دسترسی طرح معتبر نیست.');
        JsonResponse::ok(['permission'=>$repository->setMerchantPlanPermission($merchantId,$planId,$status,is_array($b['limits']??null)?$b['limits']:[],$principal->userId)]);
    }
    if($path==='/api/v1/bank/summary'&&$method==='GET'){
        if(!$principal->can('loans.requests.bank_review')&&!$principal->can('loans.requests.workflow_admin')&&!$isPlatformAdmin)JsonResponse::error(403,'permission_denied','اجازه مشاهده کارتابل بانک را ندارید.');
        JsonResponse::ok($repository->bankSummary($principal->userId,$isPlatformAdmin||$principal->can('loans.requests.workflow_admin')||$principal->can('loans.requests.global'),$principal->isSuperAdmin()?null:$visibilityScope));
    }
    if($path==='/api/v1/bank/cases'&&$method==='GET'){
        if(!$principal->can('loans.requests.bank_review')&&!$principal->can('loans.requests.workflow_admin')&&!$isPlatformAdmin)JsonResponse::error(403,'permission_denied','اجازه مشاهده کارتابل بانک را ندارید.');
        $q=$request->query();$result=$repository->bankCases((int)($q['page']??1),(int)($q['per_page']??25),$principal->userId,$isPlatformAdmin||$principal->can('loans.requests.workflow_admin')||$principal->can('loans.requests.global'),$q,$principal->isSuperAdmin()?null:$visibilityScope);JsonResponse::ok($result,200,['pagination'=>\Babanoell\Loans\Http\Pagination::fromResult($result)]);
    }

    if($path==='/api/v1/bank/export'&&$method==='GET'){
        if(!$principal->can('loans.requests.bank_review')&&!$principal->can('loans.requests.workflow_admin')&&!$isPlatformAdmin)JsonResponse::error(403,'permission_denied','اجازه خروجی کارتابل بانک را ندارید.');
        $q=$request->query();$global=$isPlatformAdmin||$principal->can('loans.requests.workflow_admin')||$principal->can('loans.requests.global');
        try{$r=$repository->bankCases(1,100,$principal->userId,$global,$q,$principal->isSuperAdmin()?null:$visibilityScope,true);}catch(\LengthException $e){JsonResponse::error(413,'bank_export_too_large',$e->getMessage());}
        JsonResponse::ok(['items'=>$r['items'],'total'=>$r['total'],'truncated'=>false]);
    }

    $loadMarketContext=static function()use($partnersClient,$request,$principal):array{
        $response=$partnersClient->marketplaceContext((string)$request->bearer());
        $context=$response->json['data']['context']??null;
        if(!$response->ok()||!is_array($context)||!is_bool($context['enabled']??null)||!is_bool($context['can_claim']??null))JsonResponse::error(503,'partners_unavailable','قواعد بازار از سرویس شرکا قابل تأیید نیست؛ دوباره تلاش کنید.');
        $context['actor_user_id']=$principal->userId;return $context;
    };
    if($path==='/api/v1/marketplace/catalog'&&$method==='GET'){
        $response=$partnersClient->marketplaceCatalog((string)$request->bearer());
        if(!$response->ok())JsonResponse::error($response->status===403?403:503,'marketplace_catalog_unavailable','فهرست حوزه‌های فعالیت و موقعیت‌ها دریافت نشد.');
        JsonResponse::ok($response->json['data']??[]);
    }
    if($path==='/api/v1/marketplace/requests'&&$method==='GET'){
        if(!$principal->can('partners.marketplace.read')&&!$principal->can('loans.requests.marketplace'))JsonResponse::error(403,'permission_denied','اجازه مشاهده بازار پرونده‌ها را ندارید.');
        $context=$isPlatformAdmin?null:$loadMarketContext();$q=$request->query();
        $r=$repository->listMarketplace((int)($q['page']??1),(int)($q['per_page']??25),trim((string)($q['q']??'')),$context);
        JsonResponse::ok($r,200,['pagination'=>\Babanoell\Loans\Http\Pagination::fromResult($r)]);
    }
    if(preg_match('#^/api/v1/marketplace/requests/([a-f0-9]{26})/claim$#',$path,$m)&&$method==='POST'){
        if(!$principal->can('partners.marketplace.claim')&&!$principal->can('loans.requests.marketplace'))JsonResponse::error(403,'permission_denied','اجازه پذیرش پرونده بازار را ندارید.');
        if(!$partnerMembershipVerified)JsonResponse::error(503,'partners_unavailable','وضعیت عضویت پذیرنده قابل تأیید نیست؛ دوباره تلاش کنید.');
        if(!is_array($partnerMerchant))JsonResponse::error(409,'merchant_membership_required','حساب شما به پذیرنده فعال متصل نیست.');
        try{$claimed=$repository->claimMarketplace($m[1],$principal->userId,(string)$partnerMerchant['id'],$principal->userId,$loadMarketContext());}
        catch(\DomainException $e){JsonResponse::error(409,'marketplace_claim_conflict',$e->getMessage());}
        JsonResponse::ok(['request'=>$claimed],201);
    }
    if(preg_match('#^/api/v1/marketplace/requests/([a-f0-9]{26})/invite$#',$path,$m)&&$method==='POST'){
        if(!$principal->can('partners.marketplace.manage')&&!$isPlatformAdmin)JsonResponse::error(403,'permission_denied','اجازه دعوت پذیرنده برای پرونده را ندارید.');$b=$request->json();$merchantId=strtolower(trim((string)($b['merchant_id']??'')));$code=substr(trim((string)($b['invitation_code']??'')),0,100);if(!preg_match('/^[a-f0-9]{26}$/',$merchantId)||$code==='')JsonResponse::error(422,'validation_failed','اطلاعات دعوت پذیرنده معتبر نیست.');JsonResponse::ok(['request'=>$repository->inviteMerchantToRequest($m[1],$merchantId,$code,$principal->userId)]);
    }

    if(in_array($path,['/api/v1/reports/requests','/api/v1/reports/requests/export'],true)&&$method==='GET'){
        $require('loans.requests.read');
        try{$r=$repository->requestReport($request->query(),$hasGlobalRequestAccess?null:$visibilityScope,str_ends_with($path,'/export'));}
        catch(\LengthException $e){JsonResponse::error(413,'request_export_too_large',$e->getMessage());}
        JsonResponse::ok($r);
    }
    if($path==='/api/v1/requests'&&$method==='GET'){
        $require('loans.requests.read');$q=$request->query();
        $filters=\Babanoell\Loans\Domain\RequestFilters::normalize($q);$result=$repository->listRequests((int)($q['page']??1),(int)($q['per_page']??25),$filters['status']??null,$filters['q']??'',null,$hasGlobalRequestAccess?null:$visibilityScope,$filters);JsonResponse::ok($result,200,['pagination'=>\Babanoell\Loans\Http\Pagination::fromResult($result)]);
    }
    if($path==='/api/v1/requests'&&$method==='POST'){
        $require('loans.requests.create');$key=\Babanoell\Loans\Http\IdempotencyKey::normalize($request->header('idempotency-key'));$body=$request->json();
        if(!$canManagePlans)unset($body['commission_override']);
        if(!$canAssignBank)unset($body['assigned_bank_user_id']);
        $origin=RequestOrigin::ADMIN;
        if($isApplicant&&(!$isAcceptor||($body['plan_context']??'')==='applicant_request')){$origin=RequestOrigin::APPLICANT;$body['applicant_user_id']=$principal->userId;$body['plan_context']='applicant_request';unset($body['acceptor_user_id']);}
        elseif($isAcceptor){
            if(!$partnerMembershipVerified)JsonResponse::error(503,'partners_unavailable','وضعیت عضویت پذیرنده از سرویس شرکا قابل تأیید نیست؛ بعداً دوباره تلاش کنید.');
            if(!is_array($partnerMerchant)||!preg_match('/^[a-f0-9]{26}$/D',(string)($partnerMerchant['id']??'')))JsonResponse::error(409,'merchant_membership_required','حساب شما به پذیرنده فعال متصل نیست.');
            $merchantId=(string)$partnerMerchant['id'];$requestedMerchant=trim((string)($body['merchant_id']??''));
            if($requestedMerchant!==''&&$requestedMerchant!==$merchantId)JsonResponse::error(403,'merchant_binding_denied','ثبت پرونده برای پذیرنده دیگر مجاز نیست.');
            $body['merchant_id']=$merchantId;
            $origin=RequestOrigin::MERCHANT;$body['acceptor_user_id']=$principal->userId;$body['plan_context']='merchant_request';unset($body['applicant_user_id']);
        }
        elseif(!$isPlatformAdmin){unset($body['applicant_user_id'],$body['acceptor_user_id']);}
        $merchantContext=null;$replay=$key!==''&&$repository->findRequestByIdempotency($principal->userId,$key)!==null;
        if($origin===RequestOrigin::MERCHANT&&!$replay){
            if($partnerPlanPolicy===null)JsonResponse::error(503,'merchant_plan_policy_unavailable','محدودیت طرح‌های پذیرنده قابل تأیید نیست.');
            $selected=$repository->getPlan((string)($body['plan_id']??''));if($selected!==null&&!PlanRules::availableToMerchant($selected,$partnerPlanPolicy))JsonResponse::error(403,'plan_access_denied','این طرح برای حساب پذیرنده غیرفعال است.');
        }
        if($origin===RequestOrigin::APPLICANT&&!$replay){
            $lockResponse=$partnersClient->invitationLock((string)$request->bearer());
            if(!$lockResponse->ok()||!array_key_exists('lock',$lockResponse->json['data']??[]))JsonResponse::error(503,'invitation_lock_unavailable','وضعیت دعوت پذیرنده قابل تأیید نیست؛ کمی بعد دوباره تلاش کنید.');
            $lock=$lockResponse->json['data']['lock'];
            if(is_array($lock)){
                if(($lock['status']??'')!=='active'||($lock['cooperation_status']??'')!=='approved')JsonResponse::error(409,'invited_merchant_unavailable','پذیرندهٔ دعوت‌کننده فعلاً فعال نیست؛ با پشتیبانی تماس بگیرید.');
                $body['merchant_id']=$lock['merchant_id'];$body['acceptor_assignment_mode']='applicant_selected_acceptor';
            }
        }
        if(!$replay&&trim((string)($body['merchant_id']??''))!==''){
            $authority=$partnersClient->assignmentContext((string)$body['merchant_id'],(string)($body['plan_id']??''),(string)$request->bearer());
            $merchantContext=$authority->json['data']['context']??null;
            if(!$authority->ok()||!is_array($merchantContext))JsonResponse::error(in_array($authority->status,[403,404],true)?422:503,'selected_merchant_unavailable','پذیرندهٔ انتخابی فعال یا قابل تأیید نیست.');
        }
        if($origin===RequestOrigin::APPLICANT&&($body['acceptor_assignment_mode']??'')==='open_acceptor_marketplace'&&($key===''||$repository->findRequestByIdempotency($principal->userId,$key)===null)){
            $catalogResponse=$partnersClient->marketplaceCatalog((string)$request->bearer());$catalog=$catalogResponse->json['data']['catalog']??null;
            if(!$catalogResponse->ok()||!is_array($catalog))JsonResponse::error(503,'marketplace_catalog_unavailable','فهرست بازار برای بررسی درخواست در دسترس نیست.');
            \Babanoell\Loans\Domain\MarketplaceRequest::validate($body,$catalog);
        }
        unset($body['request_origin']);
        JsonResponse::ok(['request'=>$requestService->create($principal->userId,$body,$key,$origin,$merchantContext)],201);
    }


    if(preg_match('#^/api/v1/admin/requests/([a-f0-9]{26})/profile-corrections$#',$path,$m)){
        if(!$isPlatformAdmin)JsonResponse::error(403,'permission_denied','اصلاح مدیریتی پرونده فقط برای مدیر سامانه مجاز است.');
        $loan=$repository->getRequest($m[1]);if($loan===null)JsonResponse::error(404,'request_not_found','پرونده پیدا نشد.');
        if($method==='GET')JsonResponse::ok(['request'=>$loan,'corrections'=>$repository->profileCorrections($m[1])]);
        if($method==='POST'){
            $body=$request->json();$raw=is_array($body['fields']??null)?$body['fields']:[];
            $whitelist=['full_name','father_name','certificate_number','certificate_city','retirement_code','retirement_organization','delivery_date','national_code','mobile_number','phone_number','birth_date','occupation','residential_address','postal_code','work_address'];
            if($raw===[]||array_diff(array_keys($raw),$whitelist)!==[])JsonResponse::error(422,'invalid_fields','تنها فیلدهای اطلاعات هویتی و تماس مجاز هستند.');
            if(isset($raw['birth_date'])&&preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$raw['birth_date'])){
                $date=(string)$raw['birth_date'];$dt=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
                if(!$dt||$dt->format('Y-m-d')!==$date||$date>gmdate('Y-m-d'))JsonResponse::error(422,'invalid_date','تاریخ تولد معتبر نیست.');
                $birth=$date;unset($raw['birth_date']);$valid=\Babanoell\Loans\Domain\InputValidator::draftFields($raw);$valid['birth_date']=$birth;
            }else{$valid=\Babanoell\Loans\Domain\InputValidator::draftFields($raw);}
            $reason=trim((string)($body['reason']??''));$version=(int)($body['row_version']??0);
            JsonResponse::ok(['request'=>$repository->correctProfile($m[1],$principal->userId,$valid,$version,$reason,($body['acknowledge_signed']??false)===true)]);
        }
    }
    if($path==='/api/v1/admin/requests/resolve-legacy'&&$method==='GET'){
        if(!$isPlatformAdmin)JsonResponse::error(403,'permission_denied','دسترسی مدیریتی لازم است.');
        $legacy=(string)($request->query()['legacy_id']??'');
        if(!preg_match('/^[1-9][0-9]{0,15}$/',$legacy))JsonResponse::error(422,'invalid_legacy_id','شناسه قدیمی معتبر نیست.');
        JsonResponse::ok(['request'=>$repository->findByLegacyId((int)$legacy)]);
    }

    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})$#',$path,$m)){
        $loan=$repository->getRequest($m[1]);if($loan===null)JsonResponse::error(404,'request_not_found','پرونده پیدا نشد.');if(!$canReadRecord($loan))JsonResponse::error(403,'record_access_denied','دسترسی به این پرونده مجاز نیست.');
        if($method==='GET'){$require('loans.requests.read');JsonResponse::ok(['request'=>$loan,'allowed_transitions'=>$visibleTransitions($loan)]);}
        if(in_array($method,['PATCH','PUT'],true)){
            $require('loans.requests.update');if(!$recordPolicy->canUpdate($loan))JsonResponse::error(403,'record_update_denied','ویرایش این پرونده برای حساب شما مجاز نیست.');$payload=$request->json();if(!$canAssignBank)unset($payload['assigned_bank_user_id']);$fields=InputValidator::draftFields($payload);
            JsonResponse::ok(['request'=>$repository->updateDraft($m[1],$fields,$principal->userId)]);
        }
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/contract-context$#',$path,$m)&&$method==='GET'){
        $require('loans.requests.read');$loan=$repository->getRequest($m[1]);if($loan===null)JsonResponse::error(404,'request_not_found','پرونده پیدا نشد.');if(!$canReadRecord($loan))JsonResponse::error(403,'record_access_denied','دسترسی به این پرونده مجاز نیست.');
        JsonResponse::ok(['contract_context'=>\Babanoell\Loans\Domain\ContractContext::fromLoan($loan)]);
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/readiness$#',$path,$m)){
        $require('loans.requests.read');$loan=$loadCase($m[1]);
        if($method!=='GET')JsonResponse::error(405,'method_not_allowed','این مسیر فقط برای مشاهده پیشرفت پرونده است.');
        JsonResponse::ok(['readiness'=>(new \Babanoell\Loans\Service\CaseReadinessService($repository,$documentsClient,$partnersClient))->inspect($loan,(string)$request->bearer())]);
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/document-requirements$#',$path,$m)&&$method==='GET'){
        $require('loans.requests.read');$loan=$repository->getRequest($m[1]);if($loan===null)JsonResponse::error(404,'request_not_found','پرونده پیدا نشد.');if(!$canReadRecord($loan))JsonResponse::error(403,'record_access_denied','دسترسی به این پرونده مجاز نیست.');
        $required=PlanRules::caseDocumentRequirements($loan);
        JsonResponse::ok(['request_id'=>$m[1],'plan_id'=>(string)$loan['plan_id'],'plan_version_id'=>(string)$loan['plan_version_id'],'plan_name'=>(string)($loan['plan_name']??''),'plan_version'=>(int)($loan['plan_version']??0),'required_documents'=>$required,'required_document_descriptions'=>$loan['plan_rules']['required_document_descriptions']??[],'completed_check_files_enabled'=>$loan['plan_rules']['completed_check_files_enabled']??true,'completion_documents_required'=>$loan['plan_rules']['completion_documents_required']??false,'guarantors'=>$repository->guarantors($m[1]),'credit_validation'=>CreditValidationPolicy::summary($loan)]);
    }

    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/submit$#',$path,$m)&&$method==='POST'){
        $require('loans.requests.update');$loan=$loadCase($m[1]);$selfOwned=(string)($loan['applicant_user_id']??'')===$principal->userId||(string)($loan['acceptor_user_id']??'')===$principal->userId||(string)($loan['created_by_user_id']??'')===$principal->userId;
        if(!$selfOwned)JsonResponse::error(403,'self_service_submit_denied','ارسال این پرونده از مسیر خودخدمتی مجاز نیست.');
        \Babanoell\Loans\Domain\InputValidator::assertStoredRequestSubmittable($loan);
        $updated=$repository->submitForReview($m[1],$principal->userId,'self_service');$taskService->syncForRequest($m[1],$principal->userId,'request.submitted');JsonResponse::ok(['request'=>$updated]);
    }

    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/transition$#',$path,$m)&&$method==='POST'){
        $require('loans.requests.transition');$loan=$repository->getRequest($m[1]);if($loan===null)JsonResponse::error(404,'request_not_found','پرونده پیدا نشد.');if(!$canReadRecord($loan))JsonResponse::error(403,'record_access_denied','دسترسی به این پرونده مجاز نیست.');if(!$recordPolicy->canTransition($loan))JsonResponse::error(403,'record_transition_denied','تغییر وضعیت این پرونده برای حساب شما مجاز نیست.');
        $b=$request->json();$next=(string)($b['status']??'');if($next==='granted')JsonResponse::error(409,'grant_record_required','اعطای اعتبار باید از عملیات «ثبت اعطا» انجام شود تا اطلاعات اعطا و تاریخچه مالی به‌صورت اتمیک ثبت شوند.');
        $visible=$visibleTransitions($loan);$visibleCodes=array_map(static fn(array $item):string=>(string)($item['code']??''),$visible);if($isBankExpert&&!in_array($next,$visibleCodes,true))JsonResponse::error(403,'bank_transition_denied','این تغییر وضعیت در گردش کار کارشناس بانک مجاز نیست.');
        if(array_key_exists('assigned_bank_user_id',$b)&&!$canAssignBank)JsonResponse::error(403,'bank_assignment_denied','اجازه تغییر کارشناس بانک این پرونده را ندارید.');
        if(in_array($next,['docs_verified','granted'],true)){
            $token=(string)($request->bearer()??'');$completion=$documentsClient->completion($m[1],$token);
            if(!$completion->ok()){JsonResponse::error($completion->status===0||$completion->status>=500?503:409,$completion->status===0||$completion->status>=500?'documents_unavailable':'required_documents_incomplete',$completion->status===0||$completion->status>=500?'سرویس مدارک برای بررسی تکمیل پرونده در دسترس نیست.':'امکان تأیید مدارک وجود ندارد؛ وضعیت مدارک پرونده را بررسی کنید.');}
            $cd=is_array($completion->json['data']??null)?$completion->json['data']:[];if(($cd['complete']??false)!==true)JsonResponse::error(409,'required_documents_incomplete','همه مدارک الزامی نسخه طرح تأیید نشده‌اند. ابتدا مدارک پرونده را تکمیل و بازبینی کنید.',['missing'=>$cd['missing']??[],'pending'=>$cd['pending']??[],'rejected'=>$cd['rejected']??[]]);
        }
        $updated=$workflow->transition($m[1],$principal->userId,$next,trim((string)($b['reason']??'')),array_key_exists('assigned_bank_user_id',$b)?(string)$b['assigned_bank_user_id']:null,'api');$taskService->syncForRequest($m[1],$principal->userId,'request.transition');JsonResponse::ok(['request'=>$updated]);
    }
    // Applicant/merchant consent and reports share the same server-owned evidence as the bank desk.
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/credit(?:/(.*))?$#',$path,$creditRoute)){
        $require('loans.requests.read');$loan=$loadCase($creditRoute[1]);$suffix=$creditRoute[2]??'';
        $own=in_array($principal->userId,array_map('strval',[$loan['created_by_user_id']??'', $loan['applicant_user_id']??'', $loan['acceptor_user_id']??'']),true);
        $bank=$principal->can('loans.requests.bank_review')||$principal->can('loans.requests.workflow_admin')||$isPlatformAdmin;
        if(!$own&&!$bank)JsonResponse::error(403,'credit_access_denied','دسترسی به اعتبارسنجی این پرونده مجاز نیست.');
        if(!$own&&$isBankExpert&&trim((string)($loan['assigned_bank_user_id']??''))!==$principal->userId)JsonResponse::error(403,'credit_access_denied','پرونده به کارشناس بانک دیگری ارجاع شده است.');
        if($suffix===''&&$method==='GET')JsonResponse::ok(['request'=>$loan]+$bankCredit->snapshot($creditRoute[1]));
        if(preg_match('#^reports/([a-f0-9]{26})$#',$suffix,$reportMatch)&&$method==='GET'){
            $evidence=$repository->creditReportEvidence($reportMatch[1]);
            if($evidence===null||(string)$evidence['request_id']!==$creditRoute[1])JsonResponse::error(404,'credit_report_not_found','گزارش این پرونده موجود نیست.');
            $canonical=(string)$evidence['canonical_json'];
            if(!hash_equals((string)$evidence['report_sha256'],hash('sha256',$canonical)))JsonResponse::error(503,'credit_report_integrity_failed','صحت فایل گزارش قابل تأیید نیست.');
            $repository->recordEvent($creditRoute[1],'request.credit_report.viewed',$principal->userId,'allowed',['verification_id'=>$reportMatch[1],'report_sha256'=>$evidence['report_sha256']]);
            JsonResponse::ok(['report'=>json_decode($canonical,true,64,JSON_THROW_ON_ERROR),'canonical_json'=>$canonical,
                'report_code'=>$evidence['report_code'],'sha256'=>$evidence['report_sha256'],'verified_at'=>$evidence['verified_at'],'valid_until'=>$evidence['valid_until']]);
        }
        if($method!=='POST')JsonResponse::error(404,'route_not_found','مسیر اعتبارسنجی پیدا نشد.');
        if(($own&&!$bank&&!$principal->can('loans.requests.update'))
            ||in_array((string)$loan['status'],['granted','archived','cancelled'],true)
            ||($own&&!$bank&&!in_array((string)$loan['status'],['draft','need_correction'],true)))JsonResponse::error(403,'credit_action_denied','اعتبارسنجی در این مرحله قابل تغییر نیست.');
        if($suffix==='request'){
            $key=\Babanoell\Loans\Http\IdempotencyKey::normalize($request->header('idempotency-key'));
            $verification=$bankCredit->queue($creditRoute[1],$principal->userId,'credit_report','ics24',$key);
            JsonResponse::ok(['verification'=>$verification],201);
        }
        if(preg_match('#^verifications/([a-f0-9]{26})/(dispatch|recover|otp|refresh)$#',$suffix,$creditAction)){
            $verification=$repository->getVerificationRequest($creditAction[1]);
            if($verification===null||(string)$verification['request_id']!==$creditRoute[1]||$verification['verification_type']!=='credit_report'||$verification['provider']!=='ics24')JsonResponse::error(404,'verification_not_found','درخواست اعتبارسنجی پیدا نشد.');
            $provider=new \Babanoell\Loans\Service\ProviderVerificationService($repository,$bankCredit,$integrationsClient,$config->serviceAuthSecret());
            try{
                $result=match($creditAction[2]){
                    'dispatch'=>$provider->dispatch($creditRoute[1],$creditAction[1],$principal->userId),
                    'recover'=>$provider->dispatch($creditRoute[1],$creditAction[1],$principal->userId,true),
                    'otp'=>$provider->verifyOtp($creditRoute[1],$creditAction[1],$principal->userId,(string)($request->json()['otp']??''),\Babanoell\Loans\Http\IdempotencyKey::normalize($request->header('idempotency-key'))),
                    'refresh'=>$provider->refresh($creditRoute[1],$creditAction[1],$principal->userId),
                };
                $taskService->syncForRequest($creditRoute[1],$principal->userId,'request.verification.provider_updated');
                JsonResponse::ok($result);
            }catch(RuntimeException $error){
                $code=$error->getMessage();$http=in_array($code,['integrations_unavailable','integrations_response_invalid'],true)?503:409;
                JsonResponse::error($http,'verification_provider_'.$code,'اعتبارسنجی کامل نشد؛ وضعیت ذخیره شده است و ارسال دوباره به ارائه‌دهنده خودکار انجام نمی‌شود.');
            }
        }
        JsonResponse::error(404,'route_not_found','مسیر اعتبارسنجی پیدا نشد.');
    }

    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/credit-validation$#',$path,$m)){
        $loan=$loadCase($m[1]);
        if($method==='GET'){$require('loans.requests.read');JsonResponse::ok($creditValidation->snapshot($m[1]));}
        if($method==='POST'){
            if(!$principal->can('loans.requests.bank_review')&&!$principal->can('loans.requests.workflow_admin')&&!$isPlatformAdmin)JsonResponse::error(403,'credit_validation_denied','اجازه ثبت نتیجه اعتبارسنجی پرونده را ندارید.');
            $result=$creditValidation->record($m[1],$principal->userId,$request->json());$taskService->syncForRequest($m[1],$principal->userId,'request.credit_validation.updated');JsonResponse::ok($result,201);
        }
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/bank-credit$#',$path,$m)&&$method==='GET'){
        $loan=$loadCase($m[1]);if(!$principal->can('loans.requests.bank_review')&&!$principal->can('loans.requests.workflow_admin')&&!$isPlatformAdmin)JsonResponse::error(403,'bank_credit_denied','اجازه مشاهده اعتبارسنجی بانکی را ندارید.');if($isBankExpert&&trim((string)($loan['assigned_bank_user_id']??''))!==$principal->userId)JsonResponse::error(403,'bank_credit_denied','این پرونده به کارشناس بانک دیگری ارجاع شده است.');JsonResponse::ok($bankCredit->snapshot($m[1]));
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/bank-credit/request$#',$path,$m)&&$method==='POST'){
        $loan=$loadCase($m[1]);if(!$principal->can('loans.requests.bank_review')&&!$principal->can('loans.requests.workflow_admin')&&!$isPlatformAdmin)JsonResponse::error(403,'bank_credit_denied','اجازه ایجاد درخواست اعتبارسنجی ندارید.');if($isBankExpert&&trim((string)($loan['assigned_bank_user_id']??''))!==$principal->userId)JsonResponse::error(403,'bank_credit_denied','این پرونده به کارشناس بانک دیگری ارجاع شده است.');$b=$request->json();$key=\Babanoell\Loans\Http\IdempotencyKey::normalize($request->header('idempotency-key'));$verification=$bankCredit->queue($m[1],$principal->userId,(string)($b['verification_type']??''),(string)($b['provider']??''),$key);$taskService->syncForRequest($m[1],$principal->userId,'request.verification.queued');JsonResponse::ok(['verification'=>$verification],201);
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/bank-credit/verifications/([a-f0-9]{26})/(dispatch|otp|refresh)$#',$path,$bridge)&&$method==='POST'){
        $loan=$loadCase($bridge[1]);
        if(!$principal->can('loans.requests.bank_review')&&!$principal->can('loans.requests.workflow_admin')&&!$isPlatformAdmin)JsonResponse::error(403,'bank_credit_denied','اجازه اجرای اعتبارسنجی ندارید.');
        if($isBankExpert&&trim((string)($loan['assigned_bank_user_id']??''))!==$principal->userId)JsonResponse::error(403,'bank_credit_denied','این پرونده به کارشناس بانک دیگری ارجاع شده است.');
        $bridgeService=new \Babanoell\Loans\Service\ProviderVerificationService($repository,$bankCredit,$integrationsClient,$config->serviceAuthSecret());
        try {
            if($bridge[3]==='dispatch'){$out=$bridgeService->dispatch($bridge[1],$bridge[2],$principal->userId);$taskService->syncForRequest($bridge[1],$principal->userId,'request.verification.provider_updated');JsonResponse::ok($out);}
            if($bridge[3]==='otp'){
                $b=$request->json();$key=\Babanoell\Loans\Http\IdempotencyKey::normalize($request->header('idempotency-key'));
                if($key==='')JsonResponse::error(422,'idempotency_required','کلید یکتای عملیات الزامی است.');
                $out=$bridgeService->verifyOtp($bridge[1],$bridge[2],$principal->userId,(string)($b['otp']??''),$key);JsonResponse::ok($out);
            }
            $out=$bridgeService->refresh($bridge[1],$bridge[2],$principal->userId);$taskService->syncForRequest($bridge[1],$principal->userId,'request.verification.provider_updated');JsonResponse::ok($out);
        }catch(RuntimeException $e){
            $code=$e->getMessage();$http=in_array($code,['integrations_unavailable','integrations_response_invalid'],true)?503:409;
            JsonResponse::error($http,'verification_provider_'.$code,'درخواست ارائه‌دهنده نیازمند بررسی است؛ برای جلوگیری از تکرار عملیات، ارسال مجدد خودکار انجام نمی‌شود.');
        }
    }

    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/bank-credit/result$#',$path,$m)&&$method==='POST'){
        $loan=$loadCase($m[1]);if(!$principal->can('loans.requests.workflow_admin')&&!$isPlatformAdmin)JsonResponse::error(403,'bank_credit_override_denied','ثبت دستی نتیجه اعتبارسنجی فقط به‌عنوان override مدیریتی مجاز است.');$b=$request->json();$overrideReason=trim((string)($b['override_reason']??''));if($overrideReason==='')JsonResponse::error(422,'override_reason_required','برای override دستی اعتبارسنجی، ثبت دلیل الزامی است.');$vid=(string)($b['verification_id']??'');$vr=$repository->getVerificationRequest($vid);if($vr===null||(string)$vr['request_id']!==$m[1])JsonResponse::error(404,'verification_not_found','درخواست اعتبارسنجی این پرونده پیدا نشد.');$meta=is_array($b['metadata']??null)?$b['metadata']:[];$meta['source']='administrative_override';$meta['override_reason']=$overrideReason;$b['metadata']=$meta;$verification=$bankCredit->complete($vid,$principal->userId,$b);$taskService->syncForRequest($m[1],$principal->userId,'request.verification.override');JsonResponse::ok(['verification'=>$verification]);
    }

    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/workflow$#',$path,$m)&&$method==='GET'){
        $require('loans.requests.read');$loan=$loadCase($m[1]);$includeInternal=$principal->can('loans.requests.internal_notes')||$principal->can('loans.requests.bank_review')||$principal->can('loans.requests.finance')||$principal->can('loans.requests.operations');
        JsonResponse::ok(['request'=>$loan,'workflow'=>$caseWorkflow->snapshot($m[1],$includeInternal,$principal->can('loans.requests.internal_notes'))]);
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/messages$#',$path,$m)){
        $loan=$loadCase($m[1]);$require('loans.requests.message');
        if($method==='GET'){$internal=$principal->can('loans.requests.internal_notes');JsonResponse::ok(['items'=>$repository->messages($m[1],$internal)]);}
        if($method==='POST'){$b=$request->json();$visibility=strtolower(trim((string)($b['visibility']??'participants')));if($visibility==='internal'&&!$principal->can('loans.requests.internal_notes'))JsonResponse::error(403,'internal_message_denied','اجازه ثبت پیام داخلی را ندارید.');JsonResponse::ok(['message'=>$caseWorkflow->message($m[1],$principal->userId,$primaryRole,(string)($b['message']??''),is_array($b['attachment_document_ids']??null)?$b['attachment_document_ids']:[],$visibility)],201);}
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/notes$#',$path,$m)&&$method==='POST'){
        $loadCase($m[1]);$require('loans.requests.internal_notes');$b=$request->json();
        $ids=NoteAttachmentPolicy::normalize($b['attachment_document_ids']??[]);
        foreach($ids as $documentId){
            $response=$documentsClient->document($documentId,(string)$request->bearer());
            if(!$response->ok())JsonResponse::error($response->status===403?403:($response->status===404?422:503),'note_attachment_unavailable','پیوست برای این کاربر/پرونده قابل تأیید نیست.');
            $document=$response->json['data']['document']??null;
            if(!is_array($document))JsonResponse::error(503,'document_response_invalid','پاسخ اطلاعات پیوست معتبر نیست.');
            NoteAttachmentPolicy::assertDocument($document,$m[1],$principal->userId);
        }
        $opKey=trim((string)($b['operation_key']??''));
        JsonResponse::ok(['note'=>$caseWorkflow->note($m[1],$principal->userId,(string)($b['comment']??''),$ids,$opKey)],201);
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/notes/([a-f0-9]{26})$#',$path,$m)&&$method==='PATCH'){
        $loadCase($m[1]);$require('loans.requests.internal_notes');$b=$request->json();
        JsonResponse::ok(['note'=>$caseWorkflow->editNote($m[1],$m[2],$principal->userId,(string)($b['comment']??''),(int)($b['expected_version']??0),$principal->isSuperAdmin()||$principal->can('loans.requests.workflow_admin'))]);
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/notes/([a-f0-9]{26})/history$#',$path,$m)&&$method==='GET'){
        $loadCase($m[1]);$require('loans.requests.internal_notes');JsonResponse::ok(['items'=>$repository->noteHistory($m[1],$m[2])]);
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/assignment$#',$path,$m)&&$method==='POST'){
        $loadCase($m[1]);$b=$request->json();$type=(string)($b['type']??'bank_expert');if($type==='bank_expert'){if(!$canAssignBank&&!$principal->can('loans.requests.workflow_admin'))JsonResponse::error(403,'assignment_denied','اجازه ارجاع پرونده به کارشناس بانک را ندارید.');}elseif(!$principal->can('loans.requests.workflow_admin'))JsonResponse::error(403,'assignment_denied','اجازه مدیریت ارجاع پرونده را ندارید.');
        $updated=$caseWorkflow->assign($m[1],$principal->userId,$type,(string)($b['user_id']??''),(string)($b['reason']??''));$taskService->syncForRequest($m[1],$principal->userId,'request.assignment.changed');JsonResponse::ok(['request'=>$updated]);
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/document-receipt$#',$path,$m)&&$method==='POST'){
        $loadCase($m[1]);$require('loans.requests.document_receipt');$b=$request->json();JsonResponse::ok(['request'=>$caseWorkflow->documentReceipt($m[1],$principal->userId,(string)($b['status']??''),isset($b['received_at'])?(string)$b['received_at']:null,(string)($b['note']??''))]);
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/bank-review$#',$path,$m)&&$method==='POST'){
        $loan=$loadCase($m[1]);$require('loans.requests.bank_review');if($isBankExpert&&trim((string)($loan['assigned_bank_user_id']??''))!==$principal->userId)JsonResponse::error(403,'bank_review_denied','این پرونده به کارشناس بانک دیگری ارجاع شده است.');$b=$request->json();if((string)($b['status']??'')==='approved')$bankCredit->assertBankApprovalAllowed($m[1]);$review=$caseWorkflow->bankReview($m[1],$principal->userId,(string)($b['status']??''),(string)($b['reason']??''),(string)($b['note']??''),(string)($b['external_reference']??''));if((string)($b['status']??'')==='approved')$taskService->completeWorkflowCode($m[1],'bank_review',$principal->userId,'تکمیل با تأیید بررسی بانک');JsonResponse::ok(['review'=>$review],201);
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/grant$#',$path,$m)&&$method==='POST'){
        $loan=$loadCase($m[1]);$require('loans.requests.grant');
        if((string)($loan['status']??'')!=='docs_verified')JsonResponse::error(409,'grant_status_invalid','ثبت اعطا فقط پس از تأیید مدارک مجاز است.');
        $readiness=(new \Babanoell\Loans\Service\CaseReadinessService($repository,$documentsClient,$partnersClient))->inspect($loan,(string)$request->bearer());
        if(!$readiness['ready_for_grant']){
            $unknown=count(array_filter($readiness['steps'],static fn(array$step):bool=>$step['status']==='unknown'))>0;
            JsonResponse::error($unknown?503:409,'grant_prerequisites_incomplete','پیش‌نیازهای اعطا کامل نیست؛ چک‌لیست پرونده را بررسی کنید.',['blockers'=>$readiness['blockers']]);
        }
        $b=$request->json();$metadata=is_array($b['metadata']??null)?$b['metadata']:[];
        $metadata['readiness']=['checked_at'=>$readiness['checked_at'],'loan_row_version'=>$readiness['loan_row_version'],'evidence'=>$readiness['evidence']];
        $result=$caseWorkflow->grant($m[1],$principal->userId,InputValidator::money($b['granted_amount']??$loan['facility_amount']??0),
            (string)($b['granted_at']??gmdate('Y-m-d H:i:s')),(string)($b['loan_number']??''),(string)($b['contract_number']??''),$metadata,$readiness['loan_row_version']);
        $taskService->syncForRequest($m[1],$principal->userId,'request.granted');JsonResponse::ok($result,201);
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/bank-settlement$#',$path,$m)&&$method==='POST'){
        $loadCase($m[1]);$require('loans.requests.bank_settlement');$b=$request->json();$key=$request->header('idempotency-key');$fr=$financeClient->bankSettlement($m[1],(string)$request->bearer(),$b,$key);if(!$fr->ok())JsonResponse::error($fr->status>=400&&$fr->status<500?$fr->status:503,'finance_unavailable',(string)($fr->json['error']['message']??'سرویس مالی برای ثبت تسویه بانک در دسترس نیست.'));$settlementStatus=(string)($b['status']??'');$updated=$caseWorkflow->bankSettlement($m[1],$principal->userId,$settlementStatus,isset($b['settled_at'])?(string)$b['settled_at']:null,(string)($b['note']??''));if($settlementStatus==='recorded')$taskService->completeWorkflowCode($m[1],'bank_settlement',$principal->userId,'تکمیل خودکار با ثبت تسویه بانک در Finance');else$taskService->syncForRequest($m[1],$principal->userId,'request.bank_settlement.updated');JsonResponse::ok(['request'=>$updated,'finance'=>$fr->json['data']??null]);
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/merchant-settlement$#',$path,$m)&&$method==='POST'){
        $loadCase($m[1]);$require('loans.requests.merchant_settlement');$b=$request->json();$fr=$financeClient->merchantSettlement($m[1],(string)$request->bearer(),$b,$request->header('idempotency-key'));if(!$fr->ok())JsonResponse::error($fr->status>=400&&$fr->status<500?$fr->status:503,'finance_unavailable',(string)($fr->json['error']['message']??'سرویس مالی برای ثبت تسویه پذیرنده در دسترس نیست.'));$updated=$caseWorkflow->merchantSettlement($m[1],$principal->userId,(string)($b['status']??''),isset($b['settlement_date'])?(string)$b['settlement_date']:null,isset($b['cycle_id'])?(string)$b['cycle_id']:null,(string)($b['note']??''));JsonResponse::ok(['request'=>$updated,'finance'=>$fr->json['data']??null]);
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/financier-payment$#',$path,$m)&&$method==='POST'){
        $loadCase($m[1]);$require('loans.requests.finance');$b=$request->json();$fr=$financeClient->financierPayment($m[1],(string)$request->bearer(),$b,$request->header('idempotency-key'));if(!$fr->ok())JsonResponse::error($fr->status>=400&&$fr->status<500?$fr->status:503,'finance_unavailable',(string)($fr->json['error']['message']??'سرویس مالی برای ثبت پرداخت تأمین‌کننده در دسترس نیست.'));$paymentStatus=(string)($b['status']??'');$updated=$caseWorkflow->financierPayment($m[1],$principal->userId,$paymentStatus,(string)($b['note']??''));if($paymentStatus==='paid')$taskService->completeWorkflowCode($m[1],'finance_review',$principal->userId,'تکمیل خودکار با ثبت پرداخت در Finance');else$taskService->syncForRequest($m[1],$principal->userId,'request.financier_payment.updated');JsonResponse::ok(['request'=>$updated,'finance'=>$fr->json['data']??null]);
    }

    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/history$#',$path,$m)&&$method==='GET'){$require('loans.requests.audit');$loan=$repository->getRequest($m[1]);if($loan===null)JsonResponse::error(404,'request_not_found','پرونده پیدا نشد.');if(!$canReadRecord($loan))JsonResponse::error(403,'record_access_denied','دسترسی به این پرونده مجاز نیست.');JsonResponse::ok(['history'=>$repository->history($m[1]),'events'=>$repository->events($m[1])]);}
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/schedule$#',$path,$m)&&$method==='GET'){$require('loans.requests.read');$loan=$repository->getRequest($m[1]);if($loan===null)JsonResponse::error(404,'request_not_found','پرونده پیدا نشد.');if(!$canReadRecord($loan))JsonResponse::error(403,'record_access_denied','دسترسی به این پرونده مجاز نیست.');JsonResponse::ok(['schedule'=>$repository->schedule($m[1])]);}
    if(preg_match('#^/api/v1/guarantors/([a-f0-9]{26})$#',$path,$m)&&$method==='GET'){
        $require('loans.requests.read');$guarantor=$repository->getGuarantor($m[1]);if($guarantor===null||(string)($guarantor['status']??'active')!=='active')JsonResponse::error(404,'guarantor_not_found','ضامن پیدا نشد.');
        $parent=$repository->getRequest((string)$guarantor['request_id']);if($parent===null)JsonResponse::error(404,'request_not_found','پرونده پیدا نشد.');if(!$canReadRecord($parent))JsonResponse::error(403,'record_access_denied','دسترسی به این ضامن مجاز نیست.');
        JsonResponse::ok(['guarantor'=>$guarantor,'request'=>$parent]);
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/guarantors$#',$path,$m)){
        $loan=$repository->getRequest($m[1]);if($loan===null)JsonResponse::error(404,'request_not_found','پرونده پیدا نشد.');if(!$canReadRecord($loan))JsonResponse::error(403,'record_access_denied','دسترسی به این پرونده مجاز نیست.');
        if($method==='GET'){$require('loans.requests.read');JsonResponse::ok(['guarantors'=>$repository->guarantors($m[1])]);}
        if(in_array($method,['PUT','POST'],true)){$require('loans.requests.update');if($isSelfService&&!in_array((string)$loan['status'],['draft','need_correction','docs_completion'],true))JsonResponse::error(403,'guarantor_edit_denied','ویرایش ضامن‌ها فقط در مرحلهٔ تکمیل اطلاعات یا رفع نقص مجاز است.');$b=$request->json();$guarantors=InputValidator::guarantors($b['guarantors']??[]);$repository->replaceGuarantors($m[1],$guarantors,$principal->userId);JsonResponse::ok(['guarantors'=>$repository->guarantors($m[1])]);}
    }
    JsonResponse::error(404,'route_not_found','مسیر API در این نسخه وجود ندارد.');
}catch(AccessDeniedException $e){JsonResponse::error(403,'permission_denied',$e->getMessage());}
catch(InvalidArgumentException $e){JsonResponse::error(422,'validation_failed',$e->getMessage());}
catch(PDOException $e){error_log('[loans] database error: '.$e->getMessage());JsonResponse::error(503,'database_error','عملیات پایگاه داده کامل نشد. گزارش فنی ثبت شده است.');}
catch(Throwable $e){error_log('[loans] unexpected error: '.get_class($e).': '.$e->getMessage());JsonResponse::error(500,'internal_error','خطای غیرمنتظره‌ای رخ داد. دوباره تلاش کنید.');}
