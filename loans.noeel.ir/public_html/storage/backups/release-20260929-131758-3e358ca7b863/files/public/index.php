<?php
declare(strict_types=1);

use Babanoell\Loans\Domain\InputValidator;
use Babanoell\Loans\Domain\PlanRules;
use Babanoell\Loans\Domain\PlanSettingsValidator;
use Babanoell\Loans\Domain\CoreCatalog;
use Babanoell\Loans\Domain\CoreSchema;
use Babanoell\Loans\Domain\WorkflowCatalog;
use Babanoell\Loans\Domain\RequestOrigin;
use Babanoell\Loans\Domain\CreditValidationPolicy;
use Babanoell\Loans\Http\JsonResponse;
use Babanoell\Loans\Http\Request;
use Babanoell\Loans\Security\Authenticator;
use Babanoell\Loans\Security\Principal;
use Babanoell\Loans\Security\RequestAccessPolicy;
use Babanoell\Loans\Service\CalculationService;
use Babanoell\Loans\Service\PlanService;
use Babanoell\Loans\Service\RequestService;
use Babanoell\Loans\Service\WorkflowService;
use Babanoell\Loans\Service\CaseWorkflowService;
use Babanoell\Loans\Service\CreditValidationService;

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
    if($path==='/health') JsonResponse::send(200,['service'=>$config->serviceName(),'status'=>'ok','environment'=>$config->environment(),'version'=>'2.3.0','timestamp'=>gmdate('c')]);
    if($path==='/healthz'){
        $dbOk=false;$core=['ready'=>false,'missing'=>[]];try{$dbOk=(int)$pdo->query('SELECT 1')->fetchColumn()===1;if($dbOk)$core=CoreSchema::status($pdo);}catch(Throwable){}
        $ih=$identityClient->health();$identityOk=$ih->ok()&&(($ih->json['status']??null)==='ok');$dh=$documentsClient->health();$documentsOk=$dh->ok()&&(($dh->json['status']??null)==='ok');$ph=$partnersClient->health();$partnersOk=$ph->ok()&&(($ph->json['status']??null)==='ok');$ok=$dbOk&&($core['ready']??false)&&$identityOk&&$documentsOk&&$partnersOk;
        JsonResponse::send($ok?200:503,['service'=>$config->serviceName(),'status'=>$ok?'ok':'error','database'=>$dbOk?'ok':'error','core_schema'=>(($core['ready']??false)?'ok':'error'),'identity'=>$identityOk?'ok':'unavailable','documents'=>$documentsOk?'ok':'unavailable','partners'=>$partnersOk?'ok':'unavailable','environment'=>$config->environment(),'version'=>'2.3.0','timestamp'=>gmdate('c')]);
    }
    if(!str_starts_with($path,'/api/v1/')) JsonResponse::error(404,'route_not_found','مسیر درخواستی وجود ندارد.');
    if($path==='/api/v1/meta/platform-contract'&&$method==='GET') JsonResponse::ok(['contract'=>\Babanoell\Loans\Platform\ServiceBoundary::describe(),'service_auth'=>['mode'=>$config->serviceAuthMode(),'configured'=>$config->serviceAuthSecret()!=='']]);

    $auth=(new Authenticator($identityClient))->authenticate($request);if($auth['principal']===null){$e=$auth['error'];JsonResponse::error($e['status'],$e['code'],$e['message']);}
    /** @var Principal $principal */$principal=$auth['principal'];
    $planService=new PlanService($repository,new PlanSettingsValidator());
    $calcService=new CalculationService($repository,$calculators);
    $requestService=new RequestService($repository,$calcService,$scheduleGenerator);
    $workflow=new WorkflowService($repository,$statusMachine);
    $caseWorkflow=new CaseWorkflowService($repository);
    $creditValidation=new CreditValidationService($repository);

    $require=static function(string $permission)use($principal):void{if(!$principal->can($permission))JsonResponse::error(403,'permission_denied','برای انجام این عملیات دسترسی کافی ندارید.');};
    $roles=$principal->roles;
    $isPlatformAdmin=$principal->isSuperAdmin()||in_array('admin',$roles,true);
    $isAcceptor=in_array('acceptor',$roles,true)&&!$isPlatformAdmin;
    $isApplicant=in_array('applicant',$roles,true)&&!$isPlatformAdmin;
    $isSelfService=$isAcceptor||$isApplicant;
    $isBankExpert=in_array('bank_expert',$roles,true)&&!$isPlatformAdmin;
    $hasGlobalRequestAccess=$isPlatformAdmin||$principal->can('loans.requests.global');
    $canAssignBank=$isPlatformAdmin||$principal->can('loans.requests.assign_bank');
    $canManagePlans=$isPlatformAdmin||$principal->can('loans.plans.manage');
    $partnerMerchantIds=[];$partnerMerchant=null;
    if($isAcceptor){$pm=$partnersClient->myMerchant((string)($request->bearer()??''));if($pm->ok()){$pd=is_array($pm->json['data']??null)?$pm->json['data']:[];$partnerMerchant=is_array($pd['merchant']??null)?$pd['merchant']:null;if(is_array($partnerMerchant)&&preg_match('/^[a-f0-9]{26}$/',(string)($partnerMerchant['id']??'')))$partnerMerchantIds[]=(string)$partnerMerchant['id'];}}
    $visibilityScope=$principal->visibilityScope();if($partnerMerchantIds!==[])$visibilityScope['merchants']=array_values(array_unique(array_merge(is_array($visibilityScope['merchants']??null)?$visibilityScope['merchants']:[],$partnerMerchantIds)));
    $recordPolicy=new RequestAccessPolicy($principal,$partnerMerchantIds);
    $canReadRecord=static fn(array $record):bool=>$recordPolicy->canRead($record);
    $primaryRole=(string)($roles[0]??'');
    $loadCase=static function(string $id)use($repository,$canReadRecord):array{$loan=$repository->getRequest($id);if($loan===null)JsonResponse::error(404,'request_not_found','پرونده پیدا نشد.');if(!$canReadRecord($loan))JsonResponse::error(403,'record_access_denied','دسترسی به این پرونده مجاز نیست.');return$loan;};
    $visibleTransitions=static function(array $loan)use($workflow,$principal,$isBankExpert):array{
        if(!$principal->can('loans.requests.transition'))return[];
        $allowed=array_values(array_filter($workflow->allowed($loan),static fn(array $item):bool=>(string)($item['code']??'')!=='granted'));
        if(!$isBankExpert)return$allowed;
        $from=(string)($loan['status']??'');
        $bankAllowed=$from==='expert_approved'
            ?['docs_completion','need_correction','rejected']
            :($from==='docs_verified'?['docs_completion','rejected']:[]);
        return array_values(array_filter($allowed,static fn(array $item):bool=>in_array((string)($item['code']??''),$bankAllowed,true)));
    };

    if($path==='/api/v1/meta/statuses'&&$method==='GET'){$require('loans.requests.read');JsonResponse::ok(['statuses'=>$statusMachine->labels()]);}
    if($path==='/api/v1/meta/plans'&&$method==='GET'){$require('loans.plans.read');JsonResponse::ok(['statuses'=>['draft','active','inactive','archived'],'occupations'=>PlanRules::OCCUPATIONS,'visibility_contexts'=>PlanRules::VISIBILITY_CONTEXTS,'required_documents'=>\Babanoell\Loans\Domain\RequiredDocumentCatalog::all()]);}
    if($path==='/api/v1/meta/core'&&$method==='GET'){$require('loans.requests.read');JsonResponse::ok(['core'=>CoreCatalog::describe(),'schema'=>CoreSchema::status($pdo)]);}
    if($path==='/api/v1/stats'&&$method==='GET'){$require('loans.requests.read');JsonResponse::ok($repository->stats($hasGlobalRequestAccess?null:$visibilityScope));}

    if($path==='/api/v1/plans'&&$method==='GET'){
        $require('loans.plans.read');$q=$request->query();
        $status=$canManagePlans?(isset($q['status'])?(string)$q['status']:null):'active';
        $context=trim((string)($q['context']??''));
        if(!$canManagePlans&&$context==='')$context=$isApplicant?'applicant_request':($isAcceptor?'merchant_request':'');
        $result=$context!==''?$repository->listPlansForContext((int)($q['page']??1),(int)($q['per_page']??25),$context,isset($q['type'])?(string)$q['type']:null):$repository->listPlans((int)($q['page']??1),(int)($q['per_page']??25),$status,isset($q['type'])?(string)$q['type']:null);JsonResponse::ok($result,200,['pagination'=>\Babanoell\Loans\Http\Pagination::fromResult($result)]);
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
    if(preg_match('#^/api/v1/plans/([a-f0-9]{26})/documents$#',$path,$m)&&$method==='GET'){$require('loans.plans.read');$plan=$repository->getPlan($m[1]);if($plan===null)JsonResponse::error(404,'plan_not_found','طرح پیدا نشد.');$q=$request->query();JsonResponse::ok(['plan_id'=>$m[1],'occupation'=>(string)($q['occupation']??''),'required_documents'=>PlanRules::requiredDocuments($plan,(string)($q['occupation']??''))]);}

    if($path==='/api/v1/calculations/preview'&&$method==='POST'){
        $require('loans.plans.manage');$b=$request->json();$commission=isset($b['commission_override'])&&trim((string)$b['commission_override'])!==''?InputValidator::decimal($b['commission_override']):null;
        JsonResponse::ok($calcService->calculate($principal->userId,(string)($b['plan_id']??''),InputValidator::money($b['amount']??0),InputValidator::integer($b['installment_count']??0),max(1,InputValidator::integer($b['installment_interval']??1)),$commission,false));
    }

    if($path==='/api/v1/calculations'&&$method==='POST'){
        $require('loans.calculate');$b=$request->json();
        $commission=$canManagePlans&&isset($b['commission_override'])&&trim((string)$b['commission_override'])!==''?InputValidator::decimal($b['commission_override']):null;
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
    if($path==='/api/v1/marketplace/requests'&&$method==='GET'){
        if(!$principal->can('partners.marketplace.read')&&!$principal->can('loans.requests.marketplace'))JsonResponse::error(403,'permission_denied','اجازه مشاهده بازار پرونده‌ها را ندارید.');$q=$request->query();$r=$repository->listMarketplace((int)($q['page']??1),(int)($q['per_page']??25),trim((string)($q['q']??'')));JsonResponse::ok($r,200,['pagination'=>\Babanoell\Loans\Http\Pagination::fromResult($r)]);
    }
    if(preg_match('#^/api/v1/marketplace/requests/([a-f0-9]{26})/claim$#',$path,$m)&&$method==='POST'){
        if(!$principal->can('partners.marketplace.claim')&&!$principal->can('loans.requests.marketplace'))JsonResponse::error(403,'permission_denied','اجازه پذیرش پرونده بازار را ندارید.');if(!is_array($partnerMerchant))JsonResponse::error(409,'merchant_membership_required','حساب شما به پذیرنده فعال متصل نیست.');JsonResponse::ok(['request'=>$repository->claimMarketplace($m[1],$principal->userId,(string)$partnerMerchant['id'],$principal->userId)],201);
    }
    if(preg_match('#^/api/v1/marketplace/requests/([a-f0-9]{26})/invite$#',$path,$m)&&$method==='POST'){
        if(!$principal->can('partners.marketplace.manage')&&!$isPlatformAdmin)JsonResponse::error(403,'permission_denied','اجازه دعوت پذیرنده برای پرونده را ندارید.');$b=$request->json();$merchantId=strtolower(trim((string)($b['merchant_id']??'')));$code=substr(trim((string)($b['invitation_code']??'')),0,100);if(!preg_match('/^[a-f0-9]{26}$/',$merchantId)||$code==='')JsonResponse::error(422,'validation_failed','اطلاعات دعوت پذیرنده معتبر نیست.');JsonResponse::ok(['request'=>$repository->inviteMerchantToRequest($m[1],$merchantId,$code,$principal->userId)]);
    }

    if($path==='/api/v1/requests'&&$method==='GET'){
        $require('loans.requests.read');$q=$request->query();
        $result=$repository->listRequests((int)($q['page']??1),(int)($q['per_page']??25),isset($q['status'])?(string)$q['status']:null,trim((string)($q['q']??'')),null,$hasGlobalRequestAccess?null:$visibilityScope);JsonResponse::ok($result,200,['pagination'=>\Babanoell\Loans\Http\Pagination::fromResult($result)]);
    }
    if($path==='/api/v1/requests'&&$method==='POST'){
        $require('loans.requests.create');$key=\Babanoell\Loans\Http\IdempotencyKey::normalize($request->header('idempotency-key'));$body=$request->json();
        if(!$canManagePlans)unset($body['commission_override']);
        if(!$canAssignBank)unset($body['assigned_bank_user_id']);
        $origin=RequestOrigin::ADMIN;
        if($isApplicant){$origin=RequestOrigin::APPLICANT;$body['applicant_user_id']=$principal->userId;$body['plan_context']='applicant_request';unset($body['acceptor_user_id']);}
        elseif($isAcceptor){$origin=RequestOrigin::MERCHANT;$body['acceptor_user_id']=$principal->userId;$body['plan_context']='merchant_request';unset($body['applicant_user_id']);}
        elseif(!$isPlatformAdmin){unset($body['applicant_user_id'],$body['acceptor_user_id']);}
        unset($body['request_origin']);
        JsonResponse::ok(['request'=>$requestService->create($principal->userId,$body,$key,$origin)],201);
    }

    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})$#',$path,$m)){
        $loan=$repository->getRequest($m[1]);if($loan===null)JsonResponse::error(404,'request_not_found','پرونده پیدا نشد.');if(!$canReadRecord($loan))JsonResponse::error(403,'record_access_denied','دسترسی به این پرونده مجاز نیست.');
        if($method==='GET'){$require('loans.requests.read');JsonResponse::ok(['request'=>$loan,'allowed_transitions'=>$visibleTransitions($loan)]);}
        if(in_array($method,['PATCH','PUT'],true)){
            $require('loans.requests.update');if(!$recordPolicy->canUpdate($loan))JsonResponse::error(403,'record_update_denied','ویرایش این پرونده برای حساب شما مجاز نیست.');$payload=$request->json();if(!$canAssignBank)unset($payload['assigned_bank_user_id']);$fields=InputValidator::draftFields($payload);
            JsonResponse::ok(['request'=>$repository->updateDraft($m[1],$fields,$principal->userId)]);
        }
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/document-requirements$#',$path,$m)&&$method==='GET'){
        $require('loans.requests.read');$loan=$repository->getRequest($m[1]);if($loan===null)JsonResponse::error(404,'request_not_found','پرونده پیدا نشد.');if(!$canReadRecord($loan))JsonResponse::error(403,'record_access_denied','دسترسی به این پرونده مجاز نیست.');
        $required=is_array($loan['required_documents']??null)?$loan['required_documents']:[];
        JsonResponse::ok(['request_id'=>$m[1],'plan_id'=>(string)$loan['plan_id'],'plan_version_id'=>(string)$loan['plan_version_id'],'plan_name'=>(string)($loan['plan_name']??''),'plan_version'=>(int)($loan['plan_version']??0),'required_documents'=>$required,'guarantors'=>$repository->guarantors($m[1]),'credit_validation'=>CreditValidationPolicy::summary($loan)]);
    }

    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/submit$#',$path,$m)&&$method==='POST'){
        $require('loans.requests.update');$loan=$loadCase($m[1]);$selfOwned=(string)($loan['applicant_user_id']??'')===$principal->userId||(string)($loan['acceptor_user_id']??'')===$principal->userId||(string)($loan['created_by_user_id']??'')===$principal->userId;
        if(!$selfOwned)JsonResponse::error(403,'self_service_submit_denied','ارسال این پرونده از مسیر خودخدمتی مجاز نیست.');
        \Babanoell\Loans\Domain\InputValidator::assertStoredRequestSubmittable($loan);
        JsonResponse::ok(['request'=>$repository->submitForReview($m[1],$principal->userId,'self_service')]);
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
        JsonResponse::ok(['request'=>$workflow->transition($m[1],$principal->userId,$next,trim((string)($b['reason']??'')),array_key_exists('assigned_bank_user_id',$b)?(string)$b['assigned_bank_user_id']:null,'api')]);
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/credit-validation$#',$path,$m)){
        $loan=$loadCase($m[1]);
        if($method==='GET'){$require('loans.requests.read');JsonResponse::ok($creditValidation->snapshot($m[1]));}
        if($method==='POST'){
            if(!$principal->can('loans.requests.bank_review')&&!$principal->can('loans.requests.workflow_admin')&&!$isPlatformAdmin)JsonResponse::error(403,'credit_validation_denied','اجازه ثبت نتیجه اعتبارسنجی پرونده را ندارید.');
            JsonResponse::ok($creditValidation->record($m[1],$principal->userId,$request->json()),201);
        }
    }

    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/workflow$#',$path,$m)&&$method==='GET'){
        $require('loans.requests.read');$loan=$loadCase($m[1]);$includeInternal=$principal->can('loans.requests.internal_notes')||$principal->can('loans.requests.bank_review')||$principal->can('loans.requests.finance')||$principal->can('loans.requests.operations');
        JsonResponse::ok(['request'=>$loan,'workflow'=>$caseWorkflow->snapshot($m[1],$includeInternal)]);
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/messages$#',$path,$m)){
        $loan=$loadCase($m[1]);$require('loans.requests.message');
        if($method==='GET'){$internal=$principal->can('loans.requests.internal_notes');JsonResponse::ok(['items'=>$repository->messages($m[1],$internal)]);}
        if($method==='POST'){$b=$request->json();$visibility=strtolower(trim((string)($b['visibility']??'participants')));if($visibility==='internal'&&!$principal->can('loans.requests.internal_notes'))JsonResponse::error(403,'internal_message_denied','اجازه ثبت پیام داخلی را ندارید.');JsonResponse::ok(['message'=>$caseWorkflow->message($m[1],$principal->userId,$primaryRole,(string)($b['message']??''),is_array($b['attachment_document_ids']??null)?$b['attachment_document_ids']:[],$visibility)],201);}
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/notes$#',$path,$m)&&$method==='POST'){
        $loadCase($m[1]);$require('loans.requests.internal_notes');$b=$request->json();JsonResponse::ok(['note'=>$caseWorkflow->note($m[1],$principal->userId,(string)($b['comment']??''))],201);
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/assignment$#',$path,$m)&&$method==='POST'){
        $loadCase($m[1]);$b=$request->json();$type=(string)($b['type']??'bank_expert');if($type==='bank_expert'){if(!$canAssignBank&&!$principal->can('loans.requests.workflow_admin'))JsonResponse::error(403,'assignment_denied','اجازه ارجاع پرونده به کارشناس بانک را ندارید.');}elseif(!$principal->can('loans.requests.workflow_admin'))JsonResponse::error(403,'assignment_denied','اجازه مدیریت ارجاع پرونده را ندارید.');
        JsonResponse::ok(['request'=>$caseWorkflow->assign($m[1],$principal->userId,$type,(string)($b['user_id']??''),(string)($b['reason']??''))]);
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/document-receipt$#',$path,$m)&&$method==='POST'){
        $loadCase($m[1]);$require('loans.requests.document_receipt');$b=$request->json();JsonResponse::ok(['request'=>$caseWorkflow->documentReceipt($m[1],$principal->userId,(string)($b['status']??''),isset($b['received_at'])?(string)$b['received_at']:null,(string)($b['note']??''))]);
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/bank-review$#',$path,$m)&&$method==='POST'){
        $loan=$loadCase($m[1]);$require('loans.requests.bank_review');if($isBankExpert&&trim((string)($loan['assigned_bank_user_id']??''))!==$principal->userId)JsonResponse::error(403,'bank_review_denied','این پرونده به کارشناس بانک دیگری ارجاع شده است.');$b=$request->json();JsonResponse::ok(['review'=>$caseWorkflow->bankReview($m[1],$principal->userId,(string)($b['status']??''),(string)($b['reason']??''),(string)($b['note']??''),(string)($b['external_reference']??''))],201);
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/grant$#',$path,$m)&&$method==='POST'){
        $loan=$loadCase($m[1]);$require('loans.requests.grant');if((string)($loan['status']??'')!=='docs_verified')JsonResponse::error(409,'grant_status_invalid','ثبت اعطا فقط برای پرونده‌ای که مدارک آن تأیید شده مجاز است.');$token=(string)($request->bearer()??'');$completion=$documentsClient->completion($m[1],$token);if(!$completion->ok())JsonResponse::error(503,'documents_unavailable','سرویس مدارک برای کنترل نهایی در دسترس نیست.');$cd=is_array($completion->json['data']??null)?$completion->json['data']:[];if(($cd['complete']??false)!==true)JsonResponse::error(409,'required_documents_incomplete','همه مدارک الزامی تأیید نشده‌اند.',['missing'=>$cd['missing']??[],'pending'=>$cd['pending']??[],'rejected'=>$cd['rejected']??[]]);$b=$request->json();JsonResponse::ok($caseWorkflow->grant($m[1],$principal->userId,InputValidator::money($b['granted_amount']??$loan['facility_amount']??0),(string)($b['granted_at']??gmdate('Y-m-d H:i:s')),(string)($b['loan_number']??''),(string)($b['contract_number']??''),is_array($b['metadata']??null)?$b['metadata']:[]),201);
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/bank-settlement$#',$path,$m)&&$method==='POST'){
        $loadCase($m[1]);$require('loans.requests.bank_settlement');$b=$request->json();JsonResponse::ok(['request'=>$caseWorkflow->bankSettlement($m[1],$principal->userId,(string)($b['status']??''),isset($b['settled_at'])?(string)$b['settled_at']:null,(string)($b['note']??''))]);
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/merchant-settlement$#',$path,$m)&&$method==='POST'){
        $loadCase($m[1]);$require('loans.requests.merchant_settlement');$b=$request->json();JsonResponse::ok(['request'=>$caseWorkflow->merchantSettlement($m[1],$principal->userId,(string)($b['status']??''),isset($b['settlement_date'])?(string)$b['settlement_date']:null,isset($b['cycle_id'])?(string)$b['cycle_id']:null,(string)($b['note']??''))]);
    }
    if(preg_match('#^/api/v1/requests/([a-f0-9]{26})/financier-payment$#',$path,$m)&&$method==='POST'){
        $loadCase($m[1]);$require('loans.requests.finance');$b=$request->json();JsonResponse::ok(['request'=>$caseWorkflow->financierPayment($m[1],$principal->userId,(string)($b['status']??''),(string)($b['note']??''))]);
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
        if(in_array($method,['PUT','POST'],true)){$require('loans.requests.update');$b=$request->json();$guarantors=InputValidator::guarantors($b['guarantors']??[]);$repository->replaceGuarantors($m[1],$guarantors,$principal->userId);JsonResponse::ok(['guarantors'=>$repository->guarantors($m[1])]);}
    }
    JsonResponse::error(404,'route_not_found','مسیر API در این نسخه وجود ندارد.');
}catch(InvalidArgumentException $e){JsonResponse::error(422,'validation_failed',$e->getMessage());}
catch(PDOException $e){error_log('[loans] database error: '.$e->getMessage());JsonResponse::error(503,'database_error','عملیات پایگاه داده کامل نشد. گزارش فنی ثبت شده است.');}
catch(Throwable $e){error_log('[loans] unexpected error: '.get_class($e).': '.$e->getMessage());JsonResponse::error(500,'internal_error','خطای غیرمنتظره‌ای رخ داد. دوباره تلاش کنید.');}
