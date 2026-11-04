<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{$p='Babanoell\\Loans\\';if(!str_starts_with($class,$p))return;$f=$root.'/src/'.str_replace('\\','/',substr($class,strlen($p))).'.php';if(is_file($f))require$f;});
use Babanoell\Loans\Calculation\CalculatorRegistry;
use Babanoell\Loans\Domain\InputValidator;
use Babanoell\Loans\Domain\PlanSettingsValidator;
use Babanoell\Loans\Domain\ScheduleGenerator;
use Babanoell\Loans\Domain\StatusMachine;
use Babanoell\Loans\Support\PersianDate;
use Babanoell\Loans\Update\UpdateTool;
$tests=[];$add=static function(string$n,callable$f)use(&$tests){$tests[$n]=$f;};$assert=static function(bool$v,string$m='assertion failed'){if(!$v)throw new RuntimeException($m);};$eq=static function(mixed$a,mixed$b,string$m='not equal'){if($a!==$b)throw new RuntimeException($m.' '.var_export($a,true).' !== '.var_export($b,true));};
$add('golden calculations reproduce legacy exactly',function()use($root,$assert,$eq){$cases=json_decode((string)file_get_contents($root.'/tests/fixtures/golden-calculations.json'),true,512,JSON_THROW_ON_ERROR);$r=new CalculatorRegistry();foreach($cases as$c){$actual=$r->calculate($c['type'],(float)$c['amount'],(int)$c['installments'],(int)$c['interval'],$c['settings'],$c['commission']===null?null:(float)$c['commission']);foreach($c['expected']as$k=>$v){$assert(array_key_exists($k,$actual),$c['name'].' missing '.$k);if(is_numeric($v)&&is_numeric($actual[$k])){$assert(abs((float)$actual[$k]-(float)$v)<1e-9,$c['name'].' numeric mismatch '.$k);}else{$eq($v,$actual[$k],$c['name'].' '.$k);}}}});
$add('invalid and allowed workflow transitions are fail closed',function()use($assert){$m=new StatusMachine();$assert($m->can('under_review','granted')[0]===false);$assert($m->can('under_review','expert_approved',['assigned_bank_user_id'=>''])[0]===false);$assert($m->can('under_review','expert_approved',['assigned_bank_user_id'=>'bank-1'])[0]===true);$assert($m->can('docs_verified','rejected')[0]===true);$assert($m->can('draft','submitted',[])[0]===true);$assert($m->can('draft','draft',[])[0]===false);$assert($m->requiresReason('rejected'));$assert($m->requiresReason('need_correction'));$assert($m->requiresReason('cancelled'));$assert(!$m->requiresReason('submitted'));});
$add('plan normalization preserves legacy bank validation setting',function()use($assert){$v=new PlanSettingsValidator();$s=$v->normalized(['active_installments'=>'6,12,18','requires_bank_validation'=>'on']);$assert($s['active_installments']===[6,12,18]);$assert($s['requires_bank_validation']===true);});
$add('Iranian validators normalize Persian digits',function()use($assert){$assert(InputValidator::validMobile('۰۹۱۲۱۲۳۴۵۶۷'));$assert(InputValidator::money('۱۲۳٬۴۵۶')===123456.0);});
$add('numeric API inputs accept Persian digits and Persian decimal separator',function()use($assert,$eq){$eq(12,InputValidator::integer('۱۲'));$assert(abs(InputValidator::decimal('۲٫۵٪')-2.5)<1e-9);$v=new PlanSettingsValidator();$s=$v->normalized(['active_installments'=>'۶،۱۲،۱۸','requires_bank_validation'=>'1']);$eq([6,12,18],$s['active_installments']);$errors=$v->validate('market',['min_amount'=>'۰','max_amount'=>'۱۰۰۰۰۰۰','active_installments'=>'۶،۱۲','fixed_monthly_rate'=>'۲٫۵','prepayment_percent'=>'۲۰']);$eq([], $errors);});
$add('Jalali conversion roundtrip and month addition are stable',function()use($eq){$iso=PersianDate::jalaliToIso('1405/06/04');$eq('1405/06/04',PersianDate::isoToJalali($iso));$eq('1405/07/30',PersianDate::addMonths('1405/06/31',1));});
$add('schedule preserves total payment and count',function()use($assert,$eq){$r=(new CalculatorRegistry())->calculate('market',100000000,6,1,['fixed_monthly_rate'=>3,'prepayment_percent'=>20,'supplier_costs_percent'=>1,'intermediary_deductions_percent'=>1,'babanovel_deductions_percent'=>1,'insurance_percent'=>1,'guarantee_check_percent'=>150,'acceptor_commission_percent'=>1,'additional_deductions_percent'=>1,'acceptor_deductions_amount'=>30000000,'vat_percent'=>10]);$rows=(new ScheduleGenerator())->generate('market',$r,1,'1405/07/01');$eq(6,count($rows));$eq((int)$r['totalPayment'],array_sum(array_column($rows,'amount')));$eq('1405/12/01',$rows[5]['due_date_jalali']);});
$add('migration files expose twenty-four unique forward versions',function()use($root,$assert,$eq){require_once $root.'/src/Database/Migration.php';$files=glob($root.'/database/migrations/*.php')?:[];$seen=[];foreach($files as$f){$m=require$f;$assert($m instanceof Babanoell\Loans\Database\Migration);$assert(preg_match('/^\d{12}$/',$m->version())===1);$assert(!isset($seen[$m->version()]));$seen[$m->version()]=1;}$eq(24,count($seen));});

$add('release 1.1.1 enforces canonical plan lifecycle and archive visibility',function()use($root,$assert){
    $service=file_get_contents($root.'/src/Service/PlanService.php');
    foreach(["'draft'","'active'","'inactive'","'archived'"] as $status)$assert(str_contains($service,$status),'missing canonical status '.$status);
    $repo=file_get_contents($root.'/src/Repository/PdoLoanRepository.php');
    $assert(str_contains($repo,"p.status <> 'archived'"));
    $assert(str_contains($repo,"\$status==='all'") || str_contains($repo,"\$status === 'all'"));
    $migration=file_get_contents($root.'/database/migrations/202609220001_enforce_installment_plan_statuses.php');
    $assert(str_contains($migration,'chk_installment_plans_status'));
    $assert(str_contains($migration,"status='inactive'"));
});
$add('updater package contract is loans scoped and protects private state',function()use($root,$assert){$src=file_get_contents($root.'/src/Update/UpdateTool.php');$assert(str_contains($src,"str_starts_with(\$entry,'loans/')"));$assert(str_contains($src,"'config/local.env.php'"));$assert(str_contains($src,"str_starts_with(\$relative,'storage/')"));$assert(!str_contains($src,'پوشه panel/'));});
$add('UI is external, RTL-ready and uses shared palette',function()use($root,$assert){$css=file_get_contents($root.'/public/assets/babanoell-ui.css');foreach(['#1D3559','#D0E2F2','#EDA900']as$n)$assert(str_contains($css,$n));foreach([$root.'/public/install.php',$root.'/public/update.php']as$f){$src=file_get_contents($f);$assert(!preg_match('/<style\b/i',$src));}});

$add('workflow denial is audited before validation exception escapes',function()use($root,$assert){$src=file_get_contents($root.'/src/Service/WorkflowService.php');$posAudit=strpos($src,"'request.transition.denied'");$posThrow=strpos($src,'throw new InvalidArgumentException($message)');$assert($posAudit!==false&&$posThrow!==false&&$posAudit<$posThrow);});
$add('request pins immutable plan version and calculation reference',function()use($root,$assert){$migration=file_get_contents($root.'/database/migrations/202608260004_create_loan_requests_table.php');$repo=file_get_contents($root.'/src/Repository/PdoLoanRepository.php');$assert(str_contains($migration,'plan_version_id CHAR(26) NOT NULL'));$assert(str_contains($migration,'calculation_run_id CHAR(26) NULL'));$assert(str_contains($repo,"current_version_id"));});
$add('market requests cannot create non-monthly schedules',function()use($root,$assert){$src=file_get_contents($root.'/src/Service/CalculationService.php');$assert(str_contains($src,"if(\$interval!==1)throw new InvalidArgumentException('برای طرح بازاری فاصله اقساط باید یک ماه باشد.')"));});


$add('Loans uses isolated migration tracking and blocks Identity database',function()use($root,$assert){$runner=file_get_contents($root.'/src/Database/MigrationRunner.php');$installer=file_get_contents($root.'/public/install.php');$guard=file_get_contents($root.'/src/Database/DatabaseIsolationGuard.php');$assert(str_contains($runner,'loans_schema_migrations'));$assert(!str_contains($runner,'INSERT INTO schema_migrations'));$assert(str_contains($installer,'DatabaseIsolationGuard::assertLoansDatabase'));foreach(['users','roles','permissions','sessions','auth_audit_logs']as$marker)$assert(str_contains($guard,"'".$marker."'"));});

$add('service-root htaccess exposes public routes and blocks private internals',function()use($root,$assert){$h=file_get_contents($root.'/.htaccess');$assert(str_contains($h,'^(bin|config|database|storage|src|tests)'));$assert(str_contains($h,'public/index.php'));$assert(str_contains($h,'public/ui-asset.php'));});


$add('draft and guarantor validators reject malformed Iranian identity data',function()use($assert){
    $draft=InputValidator::draftFields(['mobile_number'=>'۰۹۱۲۱۲۳۴۵۶۷','postal_code'=>'۱۲۳۴۵۶۷۸۹۰','birth_date'=>'۱۴۰۰/۰۱/۰۱']);
    $assert($draft['mobile_number']==='09121234567');
    $assert($draft['postal_code']==='1234567890');
    $assert($draft['birth_date']==='2021-03-21');
    $failed=false;try{InputValidator::guarantors([['full_name'=>'ضامن','national_code'=>'123','mobile_number'=>'09121234567']]);}catch(InvalidArgumentException){$failed=true;}$assert($failed);
});
$add('request list access filtering happens in SQL before pagination',function()use($root,$assert){$src=file_get_contents($root.'/src/Repository/PdoLoanRepository.php');$assert(str_contains($src,'created_by_user_id=:principal'));$assert(str_contains($src,'applicant_user_id=:principal'));$assert(str_contains($src,'assigned_bank_user_id=:principal'));});
$add('bank assignment and status update are one atomic repository transition',function()use($root,$assert){$src=file_get_contents($root.'/src/Repository/PdoLoanRepository.php');$assert(str_contains($src,'assigned_bank_user_id=CASE WHEN :bank_supplied=1 THEN :bank_user ELSE assigned_bank_user_id END'));$workflow=file_get_contents($root.'/src/Service/WorkflowService.php');$assert(!str_contains($workflow,'setAssignedBankUser('));});
$add('request access indexes are added with an idempotent migration',function()use($root,$assert){$m=file_get_contents($root.'/database/migrations/202608270001_add_request_access_indexes.php');foreach(['idx_loan_requests_created_by','idx_loan_requests_applicant','information_schema.statistics']as$n)$assert(str_contains($m,$n));});


$add('phase 3 required document catalog normalizes and rejects unknown types',function()use($eq){
    $r=Babanoell\Loans\Domain\RequiredDocumentCatalog::normalize(['national_card',['code'=>'contract'],'national_card']);
    $eq(['national_card','contract'],$r);
    $failed=false;try{Babanoell\Loans\Domain\RequiredDocumentCatalog::normalize(['unknown_document']);}catch(InvalidArgumentException){$failed=true;}if(!$failed)throw new RuntimeException('unknown document type accepted');
});
$add('phase 3 pins required documents in immutable plan versions',function()use($root,$assert){
    $migration=file_get_contents($root.'/database/migrations/202608260002_create_installment_plan_versions_table.php');
    $repo=file_get_contents($root.'/src/Repository/PdoLoanRepository.php');
    $service=file_get_contents($root.'/src/Service/PlanService.php');
    $assert(str_contains($migration,'required_documents_json JSON NULL'));
    $assert(str_contains($repo,'v.required_documents_json'));
    $assert(str_contains($repo,'Json::encode($requiredDocuments)'));
    $assert(str_contains($service,'RequiredDocumentCatalog::normalize'));
});
$add('phase 3 exposes document requirements and gates verified/granted transitions',function()use($root,$assert){
    $index=file_get_contents($root.'/public/index.php');
    foreach(['/document-requirements','docs_verified','granted','required_documents_incomplete','documents_unavailable'] as$n)$assert(str_contains($index,$n),'missing '.$n);
    $assert(str_contains($index,'$documentsClient->completion'));
});
$add('phase 3 Documents integration is HTTP-only and health-aware',function()use($root,$assert){
    $cfg=file_get_contents($root.'/src/App/Config.php');$client=file_get_contents($root.'/src/Http/DocumentsClient.php');$index=file_get_contents($root.'/public/index.php');
    $assert(str_contains($cfg,'https://documents.noeel.ir'));
    $assert(str_contains($client,"/api/v1/entities/loan_request/"));
    $assert(str_contains($index,"'documents'=>"));
    $assert(!str_contains($cfg,'DOCUMENTS_DB_'));
    $assert(!str_contains($client,'PDO'));
});


$add('phase 3 runtime config exposes Documents service endpoint contract',function()use($root,$assert){
    $cfg=file_get_contents($root.'/src/App/Config.php');
    $example=file_get_contents($root.'/config/local.env.example.php');
    foreach(['LOANS_DOCUMENTS_BASE_URL','https://documents.noeel.ir'] as $needle)$assert(str_contains($cfg.$example,$needle),'missing '.$needle);
});

$passed=0;
$add('stage 1 core models the legacy WordPress business surface without cross-service foreign keys',function()use($root,$assert){
    $files=['202609260001_extend_core_entities.php','202609260002_create_merchant_core.php','202609260003_create_workflow_communication_core.php','202609260004_create_review_grant_core.php','202609260005_create_finance_settlement_core.php','202609260006_create_cooperation_notification_core.php'];
    foreach($files as$f){$src=file_get_contents($root.'/database/migrations/'.$f);$assert(is_string($src)&&$src!=='');$assert(!str_contains($src,'REFERENCES users('));$assert(!str_contains($src,'REFERENCES documents('));}
    $catalog=file_get_contents($root.'/src/Domain/CoreCatalog.php');foreach(['merchants','workflow','bank_credit','finance','cooperation','integrations']as$n)$assert(str_contains($catalog,$n));
});

$add('stage 2 record access policy is fail-closed and scoped',function()use($root,$assert){require_once $root.'/src/Security/Principal.php';require_once $root.'/src/Security/RequestAccessPolicy.php';$uid='aaaaaaaaaaaaaaaaaaaaaaaaaa';$other='bbbbbbbbbbbbbbbbbbbbbbbbbb';$p=new Babanoell\Loans\Security\Principal($uid,'x',['bank_expert'],['loans.requests.read'],[]);$policy=new Babanoell\Loans\Security\RequestAccessPolicy($p);$assert($policy->canRead(['id'=>'11111111111111111111111111','assigned_bank_user_id'=>$uid]));$assert(!$policy->canRead(['id'=>'22222222222222222222222222','assigned_bank_user_id'=>$other]));$scoped=new Babanoell\Loans\Security\Principal($uid,'x',['bank_expert'],['loans.requests.read'],['grants'=>[['scope_type'=>'organization','scope_id'=>'org-a','effect'=>'allow'],['scope_type'=>'loan_request','scope_id'=>'33333333333333333333333333','effect'=>'deny']]]);$sp=new Babanoell\Loans\Security\RequestAccessPolicy($scoped);$assert($sp->canRead(['id'=>'44444444444444444444444444','organization_ref'=>'org-a']));$assert(!$sp->canRead(['id'=>'33333333333333333333333333','organization_ref'=>'org-a','assigned_bank_user_id'=>$uid]));});
$add('legacy Persian statuses have deterministic canonical mappings',function()use($root,$assert,$eq){
    require_once $root.'/src/Domain/LegacyStatusMap.php';$eq('under_review',Babanoell\Loans\Domain\LegacyStatusMap::request('در حال بررسی'));$eq('granted',Babanoell\Loans\Domain\LegacyStatusMap::request('اعطا شده'));$assert(Babanoell\Loans\Domain\LegacyStatusMap::request('unknown')===null);
});

$add('phase 2A request context and signed service identity contract are stable',function()use($assert,$eq){
    \Babanoell\Loans\Platform\RequestContext::set('req_phase2a_123456');
    $h=\Babanoell\Loans\Platform\ServiceAuth::outbound('loans','POST','/api/v1/example','{"x":1}','shared-secret-123');
    $eq('req_phase2a_123456',$h['X-Request-Id']);$eq('loans',$h['X-Noel-Service']);$assert(isset($h['X-Noel-Signature']));
    $v=\Babanoell\Loans\Platform\ServiceAuth::verify($h['X-Noel-Service'],$h['X-Noel-Timestamp'],$h['X-Noel-Signature'],'POST','/api/v1/example','{"x":1}','shared-secret-123','required');
    $assert($v['ok']===true&&$v['authenticated']===true);
});
$add('phase 2A idempotency key contract validates client keys',function()use($assert,$eq){
    $eq('case:12345678',\Babanoell\Loans\Http\IdempotencyKey::normalize(' case:12345678 '));
    $failed=false;try{\Babanoell\Loans\Http\IdempotencyKey::normalize('bad key');}catch(InvalidArgumentException){$failed=true;}$assert($failed);
});
$add('phase 2A Loans boundary excludes future partner finance and integration ownership',function()use($assert,$eq){
    $c=\Babanoell\Loans\Platform\ServiceBoundary::describe();$eq('loans',$c['service']);foreach(['merchants','settlements','cooperation','provider_integrations']as$n)$assert(!in_array($n,$c['owns'],true));
    $assert(($c['external_services']['partners']??null)==='active');$assert(($c['external_services']['finance']??null)==='active');$assert(($c['external_services']['integrations']??null)==='planned');
});
$add('phase 2A API response and clients expose request-id service contract',function()use($root,$assert){
    $json=file_get_contents($root.'/src/Http/JsonResponse.php');$assert(str_contains($json,'X-Request-Id'));$assert(str_contains($json,'responseMeta'));
    foreach(['IdentityClient.php','DocumentsClient.php'] as$f){$src=file_get_contents($root.'/src/Http/'.$f);$assert(str_contains($src,'ServiceAuth::outbound'));}
    $index=file_get_contents($root.'/public/index.php');$assert(str_contains($index,'/api/v1/meta/platform-contract'));$assert(str_contains($index,'IdempotencyKey::normalize'));
});

$add('phase 2A pagination and audit contracts are standardized',function()use($root,$assert,$eq){
    $eq(['page'=>2,'per_page'=>25,'total'=>51,'total_pages'=>3],\Babanoell\Loans\Http\Pagination::fromResult(['page'=>2,'per_page'=>25,'total'=>51]));
    $repo=file_get_contents($root.'/src/Repository/PdoLoanRepository.php');$assert(str_contains($repo,'RequestContext::auditMeta()'));
    $index=file_get_contents($root.'/public/index.php');$assert(str_contains($index,"'pagination'=>\\Babanoell\\Loans\\Http\\Pagination::fromResult"));
});
$add('phase 2A boundary guard marks grandfathered tables and forbids future cross-domain table families',function()use($root,$assert){
    $c=\Babanoell\Loans\Platform\ServiceBoundary::describe();$assert(in_array('merchants',$c['grandfathered_shadow_tables'],true));foreach(['merchant_','settlement_','financial_','cooperation_','integration_']as$p)$assert(in_array($p,$c['forbidden_future_table_prefixes'],true));
    $baseline='202609290001';foreach(glob($root.'/database/migrations/*.php')?:[] as$f){$v=substr(basename($f),0,12);if($v<=$baseline)continue;$src=file_get_contents($f);foreach($c['forbidden_future_table_prefixes']as$prefix)$assert(!preg_match('/CREATE\\s+TABLE\\s+(?:IF\\s+NOT\\s+EXISTS\\s+)?`?'.preg_quote($prefix,'/').'/i',$src),'future Loans migration crosses service boundary: '.basename($f));}
});


$add('phase 2B canonical request origins are server-safe and deterministic',function()use($assert,$eq){
    $eq('merchant',\Babanoell\Loans\Domain\RequestOrigin::normalize('acceptor'));
    $eq('admin',\Babanoell\Loans\Domain\RequestOrigin::normalize('panel'));
    $eq('applicant',\Babanoell\Loans\Domain\RequestOrigin::normalize('applicant'));
    $failed=false;try{\Babanoell\Loans\Domain\RequestOrigin::normalize('browser-value');}catch(InvalidArgumentException){$failed=true;}$assert($failed);
});
$add('phase 2B credit validation blocks protected workflow targets when required',function()use($assert,$eq){
    $request=['plan_rules'=>['requires_bank_validation'=>true],'credit_validation_status'=>'pending'];
    $eq('pending',\Babanoell\Loans\Domain\CreditValidationPolicy::effectiveStatus($request));
    $assert(!\Babanoell\Loans\Domain\CreditValidationPolicy::verified($request));
    $failed=false;try{\Babanoell\Loans\Domain\CreditValidationPolicy::assertForTarget($request,'expert_approved');}catch(InvalidArgumentException){$failed=true;}$assert($failed);
    $request['credit_validation_status']='verified';\Babanoell\Loans\Domain\CreditValidationPolicy::assertForTarget($request,'granted');
});
$add('phase 2B core completion migration canonicalizes origin and stabilizes guarantors',function()use($root,$assert){
    $m=file_get_contents($root.'/database/migrations/202609290002_core_completion.php');
    foreach(["DEFAULT 'admin'",'credit_validation_status','credit_validation_check_id','position_no','idx_guarantors_active_position'] as$n)$assert(str_contains($m,$n),'missing '.$n);
    $repo=file_get_contents($root.'/src/Repository/PdoLoanRepository.php');$assert(str_contains($repo,"status='inactive',removed_at=NOW(6)"));$assert(str_contains($repo,'guarantor_ids'));
});
$add('phase 2B birth dates reject future or absurd values without mutating historical rows',function()use($assert){
    $failed=false;try{InputValidator::draftFields(['birth_date'=>'۱۵۹۹/۰۱/۰۱']);}catch(InvalidArgumentException){$failed=true;}$assert($failed);
    $failed=false;try{InputValidator::draftFields(['birth_date'=>'۱۲۹۹/۱۲/۲۹']);}catch(InvalidArgumentException){$failed=true;}$assert($failed);
});
$add('phase 2B exposes guarantor identity and provider-neutral credit-validation APIs',function()use($root,$assert){
    $index=file_get_contents($root.'/public/index.php');foreach(['/api/v1/guarantors/','/credit-validation','CreditValidationService','RequestOrigin::APPLICANT','RequestOrigin::MERCHANT']as$n)$assert(str_contains($index,$n),'missing '.$n);
    $req=file_get_contents($root.'/src/Service/RequestService.php');$assert(str_contains($req,'RequestOrigin::normalize'));$assert(str_contains($req,"'applicant_request'"));$assert(str_contains($req,"'merchant_request'"));
});
$add('phase 2B document requirements expose active guarantors and frozen credit validation state',function()use($root,$assert){
    $index=file_get_contents($root.'/public/index.php');$assert(str_contains($index,"'guarantors'=>\$repository->guarantors"));$assert(str_contains($index,"'credit_validation'=>CreditValidationPolicy::summary"));
});
$add('phase 2B guarantor ids are immutable and cannot be reused twice in one replacement',function()use($root,$assert){
    $repo=file_get_contents($root.'/src/Repository/PdoLoanRepository.php');
    $assert(!str_contains($repo,'$existing[$position-1]'),'guarantor identity must never be inferred by list position');
    $assert(str_contains($repo,'!in_array($givenId,$kept,true)'),'duplicate guarantor ids must not be applied twice');
});


$add('phase 2B self service submission validates stored drafts before review',function()use($assert){
    $valid=['full_name'=>'علی رضایی','father_name'=>'حسن','certificate_number'=>'123','national_code'=>'0013543318','mobile_number'=>'09121234567','postal_code'=>'1234567890','birth_date'=>'1990-01-01','residential_address'=>'تهران','work_address'=>'تهران','requested_amount'=>1000000,'installment_count'=>12,'first_due_date'=>'2026-10-01','order_items'=>[['total_price'=>1000000]]];
    InputValidator::assertStoredRequestSubmittable($valid);
    $failed=false;try{$bad=$valid;$bad['mobile_number']='';InputValidator::assertStoredRequestSubmittable($bad);}catch(InvalidArgumentException){$failed=true;}$assert($failed);
});
$add('phase 2B guarantor ids cannot be duplicated or position-rebound',function()use($root,$assert){
    $id='aaaaaaaaaaaaaaaaaaaaaaaaaa';$failed=false;try{InputValidator::guarantors([['id'=>$id,'full_name'=>'یک'],['id'=>$id,'full_name'=>'دو']]);}catch(InvalidArgumentException){$failed=true;}$assert($failed);
    $repo=file_get_contents($root.'/src/Repository/PdoLoanRepository.php');$assert(!str_contains($repo,'$existing[$position-1]'));$assert(str_contains($repo,"status='inactive',removed_at=NOW(6)"));
});
$add('phase 2B self service submit is atomic and context plan listing is server filtered',function()use($root,$assert){
    $repo=file_get_contents($root.'/src/Repository/PdoLoanRepository.php');$index=file_get_contents($root.'/public/index.php');
    foreach(['submitForReview','FOR UPDATE',"status='under_review'",'request.resubmitted','listPlansForContext','PlanRules::visibleIn']as$n)$assert(str_contains($repo,$n),'missing '.$n);
    foreach(['/submit','self_service_submit_denied','assertStoredRequestSubmittable','listPlansForContext']as$n)$assert(str_contains($index,$n),'missing '.$n);
});
$add('phase 2B legacy origin migration prefers actor ownership over nullable associations',function()use($root,$assert){
    $m=file_get_contents($root.'/database/migrations/202609290002_core_completion.php');foreach(['created_by_user_id=applicant_user_id','created_by_user_id=acceptor_user_id',"request_origin IN ('merchant','acceptor')"]as$n)$assert(str_contains($m,$n),'missing '.$n);
});


$add('phase 3 Loans activates Partners dependency and keeps merchant tables shadow-only',function()use($root,$assert){$c=\Babanoell\Loans\Platform\ServiceBoundary::describe();$assert(($c['external_services']['partners']??null)==='active');foreach(['merchants','merchant_user_memberships','merchant_plan_permissions','cooperation_requests','cooperation_documents']as$t)$assert(in_array($t,$c['grandfathered_shadow_tables'],true));});
$add('phase 3 Loans exposes Partners compatibility and marketplace routes',function()use($root,$assert){$s=file_get_contents($root.'/public/index.php');$b=file_get_contents($root.'/bootstrap.php');foreach(['/api/v1/compat/partners/merchant','/api/v1/compat/partners/plan-permission','/api/v1/marketplace/requests']as$n)$assert(str_contains($s,$n));$assert(str_contains($b,'PartnersClient'));$assert(str_contains($b,'partnersClient'));});
$add('phase 3 applicant marketplace route is privacy limited and claim is atomic',function()use($root,$assert){$s=file_get_contents($root.'/src/Repository/PdoLoanRepository.php');$a=strpos($s,'function listMarketplace');$b=strpos($s,'function claimMarketplace');$segment=substr($s,$a,$b-$a);foreach(['national_code','mobile_number','residential_address','work_address']as$n)$assert(!str_contains($segment,$n));$assert(str_contains($s,"marketplace_status='open'"));$assert(str_contains($s,'row_version=row_version+1'));});
$add('phase 3 applicant routing supports selected merchant or open marketplace',function()use($root,$assert){$v=file_get_contents($root.'/src/Domain/InputValidator.php');$svc=file_get_contents($root.'/src/Service/RequestService.php');foreach(['applicant_selected_acceptor','open_acceptor_marketplace']as$n){$assert(str_contains($v,$n));$assert(str_contains($svc,$n));}$assert(str_contains($svc,'configureApplicantRouting'));});

$add('phase 3 shadow export is paginated and strips active contract challenge hashes',function()use($root,$assert){$r=file_get_contents($root.'/src/Repository/PdoLoanRepository.php');$i=file_get_contents($root.'/public/index.php');foreach(['exportPartnerShadow','cooperation_documents','plan_permissions']as$n)$assert(str_contains($r,$n));$assert(str_contains($i,'/api/v1/compat/partners/export'));$a=strpos($r,'function exportPartnerShadow');$b=strpos($r,'function upsertShadowMerchant');$segment=substr($r,$a,$b-$a);$assert(str_contains($segment,"otp_hash"));$assert(str_contains($segment,"contract_token_hash"));});

$add('phase 3.0.1 backfills pre-partners acceptors into deterministic merchants',function()use($root,$assert){$m=file_get_contents($root.'/database/migrations/202609290003_backfill_legacy_acceptor_merchants.php');foreach(['legacy-acceptor:','acceptor_user_id','merchant_user_memberships','merchant_id=m.id','loan_request_acceptor_backfill'] as $n)$assert(str_contains($m,$n),'missing '.$n);});
$add('phase 4 plan rules freeze physical digital contract policy and template codes',function()use($root,$assert){$s=file_get_contents($root.'/src/Domain/PlanRules.php');foreach(['CONTRACT_MODES','contract_mode','loan_application_contract_enabled','facility_agreement_contract_enabled','loan_application_template_code','facility_agreement_template_code']as$n)$assert(str_contains($s,$n),$n);});
$add('phase 4 contract context is derived from frozen request plan rules',function()use($root,$assert){$s=file_get_contents($root.'/public/index.php');foreach(['/contract-context','plan_rules','contract_policy','loan_application_template_code','facility_agreement_template_code']as$n)$assert(str_contains($s,$n),$n);});
$add('phase 4 keeps contract aggregate and OTP storage outside Loans database',function()use($root,$assert){foreach(glob($root.'/database/migrations/*.php')?:[]as$f){$s=file_get_contents($f);$assert(!str_contains($s,'contract_otp_challenges'));$assert(!preg_match('/CREATE TABLE IF NOT EXISTS contracts\s*\(/i',$s));}});


$add('phase 5 task catalog creates role queues from actionable request stages',function()use($assert,$eq){
    $items=\Babanoell\Loans\Workflow\TaskCatalog::desiredForRequest(['status'=>'under_review','assigned_operations_user_id'=>'aaaaaaaaaaaaaaaaaaaaaaaaaa','assigned_bank_user_id'=>'bbbbbbbbbbbbbbbbbbbbbbbbbb']);
    $codes=array_column($items,'task_code');sort($codes);$eq(['bank_review','operations_review'],$codes);$assert($items[0]['required_permission']!==''&&$items[0]['sla_policy_code']!=='');
    $granted=\Babanoell\Loans\Workflow\TaskCatalog::desiredForRequest(['status'=>'granted']);$codes=array_column($granted,'task_code');sort($codes);$eq(['bank_settlement','finance_review'],$codes);
});
$add('phase 5 migration adds task sla tables indexes and active-task uniqueness',function()use($root,$assert){$m=file_get_contents($root.'/database/migrations/202609300001_workflow_task_sla_engine.php');foreach(['workflow_sla_policies','workflow_tasks','workflow_task_history','uq_workflow_tasks_active','assigned_user_id','due_at','warning_at','escalation_at','migration.backfill']as$n)$assert(str_contains($m,$n),'missing '.$n);});
$add('phase 5 exposes task inbox lifecycle sla policy and reporting APIs',function()use($root,$assert){$i=file_get_contents($root.'/public/index.php');foreach(['/api/v1/tasks/summary',"/api/v1/tasks'",'/api/v1/tasks/sla/run','/api/v1/tasks/sla/policies','/api/v1/tasks/report','claim|start|complete|release|reassign|cancel','syncForRequest']as$n)$assert(str_contains($i,$n),'missing '.$n);});
$add('phase 5 workflow task engine is self healing and audit based',function()use($root,$assert){$repo=file_get_contents($root.'/src/Workflow/TaskRepository.php');$service=file_get_contents($root.'/src/Workflow/TaskService.php');foreach(['workflow_task_history','active_key','runSlaSweep','sla_warning','sla_escalated','completeWorkflowCode']as$n)$assert(str_contains($repo.$service,$n),'missing '.$n);$assert(is_file($root.'/bin/workflow-sla.php'));});
$add('phase 5 core schema requires workflow task tables',function()use($root,$assert){$src=file_get_contents($root.'/src/Domain/CoreSchema.php');foreach(['workflow_sla_policies','workflow_tasks','workflow_task_history']as$n)$assert(str_contains($src,"'{$n}'"),'missing '.$n);});


$add('phase 6 migration adds provider-neutral verification requests and request snapshots',function()use($root,$assert){
    $m=file_get_contents($root.'/database/migrations/202609300002_bank_credit_core.php');
    foreach(['verification_requests','identity_verification_status','verification_request_id','proof_hash','subject_fingerprint','request_hash','identity_verification','credit_validation']as$n)$assert(str_contains($m,$n),'missing '.$n);
});
$add('phase 6 policy blocks bank approval until required identity and credit proofs verify',function()use($assert){
    $r=['plan_rules'=>['requires_identity_verification'=>true,'requires_bank_validation'=>true],'identity_verification_status'=>'pending','credit_validation_status'=>'pending'];
    $failed=false;try{\Babanoell\Loans\Domain\CreditValidationPolicy::assertForTarget($r,'expert_approved');}catch(InvalidArgumentException){$failed=true;}$assert($failed);
    $r['identity_verification_status']='verified';$failed=false;try{\Babanoell\Loans\Domain\CreditValidationPolicy::assertForTarget($r,'expert_approved');}catch(InvalidArgumentException){$failed=true;}$assert($failed);
    $r['credit_validation_status']='verified';\Babanoell\Loans\Domain\CreditValidationPolicy::assertForTarget($r,'expert_approved');
});
$add('phase 6 bank credit APIs expose scoped inbox export and verification lifecycle',function()use($root,$assert){
    $s=file_get_contents($root.'/public/index.php');foreach(['/api/v1/bank/summary','/api/v1/bank/cases','/api/v1/bank/export','/bank-credit/request','/bank-credit/result','/bank-review']as$n)$assert(str_contains($s,$n),'missing '.$n);
    $assert(str_contains($s,"trim((string)(\$loan['assigned_bank_user_id']??''))!==\$principal->userId"),'assigned bank ownership guard missing');
});
$add('phase 6 future Integrations callbacks require signed service identity',function()use($root,$assert){
    $s=file_get_contents($root.'/public/index.php');foreach(['/api/v1/integrations/verifications/','callerService()!==\'integrations\'','service_auth_required','integrationContext']as$n)$assert(str_contains($s,$n),'missing '.$n);
});
$add('phase 6 verification outbox never carries raw national code or mobile',function()use($root,$assert){
    $repo=file_get_contents($root.'/src/Repository/PdoLoanRepository.php');$a=strpos($repo,'function createVerificationRequest');$b=strpos($repo,'function completeVerificationRequest');$segment=substr($repo,$a,$b-$a);$outbox=strpos($segment,"queueOutbox('verification.requested'");$assert($outbox!==false);$tail=substr($segment,$outbox,450);$assert(!str_contains($tail,'national_code'));$assert(!str_contains($tail,'mobile_number'));$assert(str_contains($tail,'verification_id'));$assert(str_contains($tail,'provider'));
});
$add('phase 6 task catalog adds bank identity and credit work only when required',function()use($assert){
    $base=['status'=>'under_review','assigned_bank_user_id'=>'bbbbbbbbbbbbbbbbbbbbbbbbbb','plan_rules'=>['requires_identity_verification'=>true,'requires_bank_validation'=>true],'identity_verification_status'=>'pending','credit_validation_status'=>'pending'];
    $items=\Babanoell\Loans\Workflow\TaskCatalog::desiredForRequest($base);$codes=array_column($items,'task_code');$assert(in_array('bank_review',$codes,true));$assert(in_array('identity_verification',$codes,true));$assert(in_array('credit_validation',$codes,true));
});
$add('phase 6 verification results cannot be forged by ordinary bank experts',function()use($root,$assert){
    $s=file_get_contents($root.'/public/index.php');$a=strpos($s,"/bank-credit/result$#");$b=strpos($s,"/workflow$#",$a);$segment=substr($s,$a,$b-$a);$assert(str_contains($segment,"workflow_admin"));$assert(str_contains($segment,"bank_credit_override_denied"));$assert(str_contains($segment,"override_reason_required"));$assert(str_contains($segment,"administrative_override"));
});
$add('phase 6 Integrations callback reconciles workflow tasks after provider result',function()use($root,$assert){$s=file_get_contents($root.'/public/index.php');$a=strpos($s,"/api/v1/integrations/verifications/");$b=strpos($s,'$auth=',$a);$segment=substr($s,$a,$b-$a);foreach(['TaskService','TaskRepository','syncForRequest','request.verification.completed']as$n)$assert(str_contains($segment,$n),'missing '.$n);});
$add('phase 6 provider-specific HTTP remains outside Loans core',function()use($root,$assert){
    $svc=file_get_contents($root.'/src/Service/BankCreditService.php');$assert(!preg_match('#https?://#i',$svc));foreach(['shahkar','ics24']as$n)$assert(!str_contains(strtolower($svc),$n),'provider transport leaked into domain service');
});

$add('phase 7 activates Finance boundary and keeps legacy finance tables shadow-only',function()use($root,$assert){$c=\Babanoell\Loans\Platform\ServiceBoundary::describe();$assert(($c['external_services']['finance']??null)==='active');foreach(['financial_receipts','financier_payment_history','bank_settlement_history','merchant_settlement_history','settlement_cycles','settlement_cycle_items','settlement_cycle_receipts']as$t)$assert(in_array($t,$c['grandfathered_out_of_boundary_tables'],true));});
$add('phase 7 exposes service-authenticated finance shadow export only',function()use($root,$assert){$s=file_get_contents($root.'/public/index.php');foreach(['/api/v1/admin/finance-shadow',"callerService()!=='finance'",'financeShadowPage','financeShadowCycles','financeShadowReceipts']as$n)$assert(str_contains($s,$n),$n);});
$add('phase 7 proxies financial writes to Finance before compatibility shadow',function()use($root,$assert){$s=file_get_contents($root.'/public/index.php');foreach(['$financeClient->bankSettlement','$financeClient->merchantSettlement','$financeClient->financierPayment','finance_unavailable']as$n)$assert(str_contains($s,$n),$n);});
$add('phase 7 Loans config exposes Finance endpoint',function()use($root,$assert){$s=file_get_contents($root.'/src/App/Config.php');$assert(str_contains($s,'LOANS_FINANCE_BASE_URL'));$assert(str_contains($s,'financeBaseUrl'));});

$add('batch 1 admin corrections lock row and audit before after with version',function()use($root,$assert){$repo=file_get_contents($root.'/src/Repository/PdoLoanRepository.php');$idx=file_get_contents($root.'/public/index.php');$mig=file_get_contents($root.'/database/migrations/202610210001_create_profile_corrections.php');foreach(['function correctProfile','FOR UPDATE','row_version=:version','loan_profile_corrections','row_version_before','row_version_after','request.profile_corrected']as $n)$assert(str_contains($repo,$n),$n);foreach(['profile-corrections','!$isPlatformAdmin','acknowledge_signed','InputValidator::draftFields'] as $n)$assert(str_contains($idx,$n),$n);$assert(str_contains($mig,'ON DELETE RESTRICT'));});
$add('batch 1 comments serialize against locked parent before note insertion',function()use($root,$assert){$r=file_get_contents($root.'/src/Repository/PdoLoanRepository.php');$a=strpos($r,'function addComment(');$z=strpos($r,'function ', $a+12);$s=substr($r,$a,$z-$a);foreach(['beginTransaction','FOR UPDATE','INSERT INTO request_comments','commit']as $n)$assert(str_contains($s,$n),$n);});
$add('batch 1 finance compatibility shadow accepts only signed finance service',function()use($root,$assert){$idx=file_get_contents($root.'/public/index.php');$repo=file_get_contents($root.'/src/Repository/PdoLoanRepository.php');foreach(["callerService()!=='finance'",'merchant-reset-sync','syncMerchantSettlementResetShadow']as $n)$assert(str_contains($idx,$n),$n);foreach(['settlement_cycle_items','settlement_status=','settlement_date=NULL','beginTransaction','FOR UPDATE','already_current']as $n)$assert(str_contains($repo,$n),$n);});
$failed=0;foreach($tests as$n=>$f){try{$f();echo"[PASS] $n\n";$passed++;}catch(Throwable$e){echo"[FAIL] $n: {$e->getMessage()}\n";$failed++;}}echo"Loans tests: ".($passed+$failed)." run, $failed failed.\n";exit($failed?1:0);

