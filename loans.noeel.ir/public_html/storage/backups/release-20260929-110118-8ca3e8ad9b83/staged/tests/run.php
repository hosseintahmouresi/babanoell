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
$add('migration files expose nineteen unique forward versions',function()use($root,$assert,$eq){require_once $root.'/src/Database/Migration.php';$files=glob($root.'/database/migrations/*.php')?:[];$seen=[];foreach($files as$f){$m=require$f;$assert($m instanceof Babanoell\Loans\Database\Migration);$assert(preg_match('/^\d{12}$/',$m->version())===1);$assert(!isset($seen[$m->version()]));$seen[$m->version()]=1;}$eq(19,count($seen));});

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
    $assert(in_array('partners',$c['planned_services'],true));$assert(in_array('finance',$c['planned_services'],true));$assert(in_array('integrations',$c['planned_services'],true));
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
    $c=\Babanoell\Loans\Platform\ServiceBoundary::describe();$assert(in_array('merchants',$c['grandfathered_out_of_boundary_tables'],true));foreach(['merchant_','settlement_','financial_','cooperation_','integration_']as$p)$assert(in_array($p,$c['forbidden_future_table_prefixes'],true));
    $baseline='202609290001';foreach(glob($root.'/database/migrations/*.php')?:[] as$f){$v=substr(basename($f),0,12);if($v<=$baseline)continue;$src=file_get_contents($f);foreach($c['forbidden_future_table_prefixes']as$prefix)$assert(!preg_match('/CREATE\\s+TABLE\\s+(?:IF\\s+NOT\\s+EXISTS\\s+)?`?'.preg_quote($prefix,'/').'/i',$src),'future Loans migration crosses service boundary: '.basename($f));}
});

$failed=0;foreach($tests as$n=>$f){try{$f();echo"[PASS] $n\n";$passed++;}catch(Throwable$e){echo"[FAIL] $n: {$e->getMessage()}\n";$failed++;}}echo"Loans tests: ".($passed+$failed)." run, $failed failed.\n";exit($failed?1:0);
