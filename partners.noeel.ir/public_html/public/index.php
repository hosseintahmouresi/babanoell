<?php
declare(strict_types=1);
use Babanoell\Partners\Domain\PartnerRules;
use Babanoell\Partners\Http\IdempotencyKey;
use Babanoell\Partners\Http\JsonResponse;
use Babanoell\Partners\Http\Pagination;
use Babanoell\Partners\Http\Request;
use Babanoell\Partners\Security\Authenticator;
use Babanoell\Partners\Security\Principal;
$root=dirname(__DIR__);require_once$root.'/src/Platform/ReleaseTransaction.php';\Babanoell\Partners\Platform\ReleaseTransaction::guard($root);
$rawPath=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';if(!is_file($root.'/storage/installed.lock')||!is_file($root.'/config/local.env.php')){if(str_starts_with($rawPath,'/api/'))JsonResponse::error(503,'service_not_installed','سرویس شرکا هنوز نصب نشده است.');header('Location:/install.php',true,302);exit;}
require$root.'/bootstrap.php';if(strtolower($config->environment())==='production'&&!\Babanoell\Partners\Platform\ProxyContext::secure($_SERVER))JsonResponse::error(403,'https_required','ارتباط HTTPS لازم است.');
$request=Request::fromGlobals();$method=$request->method();$path=$request->path();\Babanoell\Partners\Platform\RequestContext::initialize($request->header('X-Request-Id'),'partners');
if(str_starts_with($path,'/api/v1/')){$serviceAuth=\Babanoell\Partners\Platform\ServiceAuth::verify($request->header(\Babanoell\Partners\Platform\ServiceAuth::HEADER_SERVICE),$request->header(\Babanoell\Partners\Platform\ServiceAuth::HEADER_TIMESTAMP),$request->header(\Babanoell\Partners\Platform\ServiceAuth::HEADER_SIGNATURE),$method,$path,$request->rawBody(),$config->serviceAuthSecret(),$config->serviceAuthMode());if(!$serviceAuth['ok'])JsonResponse::error($serviceAuth['error']==='service_auth_not_configured'?503:401,(string)$serviceAuth['error'],'احراز هویت سرویس فراخوان نامعتبر است.');if($serviceAuth['authenticated']===true)\Babanoell\Partners\Platform\RequestContext::setCallerService(is_string($serviceAuth['service'])?$serviceAuth['service']:null);}
try{
 if($path==='/health')JsonResponse::send(200,['service'=>'partners','status'=>'ok','environment'=>$config->environment(),'version'=>(require $root.'/config/version.php')['version'],'timestamp'=>gmdate('c')]);
 if($path==='/healthz'){$dbOk=false;try{$dbOk=(int)$pdo->query('SELECT 1')->fetchColumn()===1;}catch(Throwable){}$ih=$identityClient->health();$lh=$loansClient->health();$dh=$documentsClient->health();$identityOk=$ih->ok()&&(($ih->json['status']??null)==='ok');$loansOk=$lh->ok()&&(($lh->json['status']??null)==='ok');$documentsOk=$dh->ok()&&(($dh->json['status']??null)==='ok');$ok=$dbOk&&$identityOk&&$loansOk&&$documentsOk;JsonResponse::send($ok?200:503,['service'=>'partners','status'=>$ok?'ok':'error','database'=>$dbOk?'ok':'error','identity'=>$identityOk?'ok':'unavailable','loans'=>$loansOk?'ok':'unavailable','documents'=>$documentsOk?'ok':'unavailable','environment'=>$config->environment(),'version'=>(require $root.'/config/version.php')['version'],'timestamp'=>gmdate('c')]);}
 if(preg_match('#^/contract/([A-Za-z0-9_-]{30,120})(/print)?$#',$path,$cm)&&$method==='GET'){
  \Babanoell\Partners\Contract\ContractPrintView::headers();
  try{$contract=$contractService->publicView($cm[1],$request->clientIp(),$request->userAgent());echo \Babanoell\Partners\Contract\ContractPrintView::render($contract,isset($cm[2])&&$cm[2]==='/print',$cm[1],(!$config->isProduction()||((int)\babanoell_partners_env('PARTNERS_SMS_CONTRACT_BODY_ID','0')>0&&strlen($config->serviceAuthSecret())>=32)));exit;}
  catch(Throwable $e){http_response_code(404);echo \Babanoell\Partners\Contract\ContractPrintView::notice('لینک قرارداد در دسترس نیست','لینک معتبر نیست یا مهلت آن به پایان رسیده است. لینک تازه را از صفحهٔ قرارداد دریافت کنید.');exit;}
 }
 if(preg_match('#^/contract/([A-Za-z0-9_-]{30,120})/otp/request$#',$path,$cm)&&$method==='POST'){
  \Babanoell\Partners\Contract\ContractPrintView::headers();
  try{
   $r=$contractService->requestOtp($cm[1],$request->clientIp(),$request->userAgent());
   $testOtp=!$config->isProduction()&&is_string($r['test_otp']??null)?$r['test_otp']:null;
   echo \Babanoell\Partners\Contract\ContractPrintView::notice('درخواست رمز ثبت شد','کد تأیید را در صفحهٔ قرارداد وارد کنید.',$cm[1],$testOtp);exit;
  }catch(Throwable$e){
   http_response_code(409);$message=preg_match('/[آ-ی]/u',$e->getMessage())?$e->getMessage():'درخواست رمز انجام نشد. اعتبار لینک و فاصلهٔ ارسال مجدد را بررسی کنید.';
   echo \Babanoell\Partners\Contract\ContractPrintView::notice('رمز ارسال نشد',$message,$cm[1]);exit;
  }
 }
 if(preg_match('#^/contract/([A-Za-z0-9_-]{30,120})/otp/verify$#',$path,$cm)&&$method==='POST'){
  \Babanoell\Partners\Contract\ContractPrintView::headers();
  try{$otp=(string)($_POST['otp']??'');$contractService->verifyOtp($cm[1],$otp,$request->clientIp(),$request->userAgent());header('Location:/contract/'.$cm[1],true,303);exit;}
  catch(Throwable$e){http_response_code(409);$message=preg_match('/[آ-ی]/u',$e->getMessage())?$e->getMessage():'کد تأیید معتبر نیست یا مهلت آن به پایان رسیده است.';echo \Babanoell\Partners\Contract\ContractPrintView::notice('امضا ثبت نشد',$message,$cm[1]);exit;}
 }
 if(!str_starts_with($path,'/api/v1/'))JsonResponse::error(404,'route_not_found','مسیر درخواستی وجود ندارد.');
 if($path==='/api/v1/meta/platform-contract'&&$method==='GET')JsonResponse::ok(['contract'=>\Babanoell\Partners\Platform\ServiceBoundary::describe(),'service_auth'=>['mode'=>$config->serviceAuthMode(),'configured'=>$config->serviceAuthSecret()!=='']]);
 if(preg_match('#^/api/v1/contracts/([a-f0-9]{26})/artifact-handoff/consume$#',$path,$cm)&&$method==='POST'){
  if(\Babanoell\Partners\Platform\RequestContext::callerService()!=='documents')JsonResponse::error(403,'service_access_denied','این عملیات فقط برای سرویس مدارک مجاز است.');$b=$request->json();$r=$contractRepository->consumeArtifactHandoff($cm[1],(string)($b['handoff_token']??''),(string)($b['sha256']??''));if($r===null)JsonResponse::error(404,'artifact_handoff_invalid','مجوز ذخیره قرارداد معتبر نیست یا منقضی شده است.');JsonResponse::ok($r);
 }
 if(preg_match('#^/api/v1/contracts/([a-f0-9]{26})/pdf-stored$#',$path,$cm)&&$method==='POST'){
  if(\Babanoell\Partners\Platform\RequestContext::callerService()!=='documents')JsonResponse::error(403,'service_access_denied','این عملیات فقط برای سرویس مدارک مجاز است.');
  $b=$request->json();$contractRepository->markPdfStored($cm[1],(string)($b['document_id']??''),(string)($b['sha256']??''));JsonResponse::ok(['stored'=>true]);
 }
 if(preg_match('#^/api/v1/contracts/([a-f0-9]{26})/artifact-stored$#',$path,$cm)&&$method==='POST'){
  if(\Babanoell\Partners\Platform\RequestContext::callerService()!=='documents')JsonResponse::error(403,'service_access_denied','این عملیات فقط برای سرویس مدارک مجاز است.');$b=$request->json();$contractRepository->markArtifact($cm[1],(string)($b['document_id']??''),(string)($b['sha256']??''));JsonResponse::ok(['stored'=>true]);
 }
 $auth=(new Authenticator($identityClient))->authenticate($request);if($auth['principal']===null){$e=$auth['error'];JsonResponse::error($e['status'],$e['code'],$e['message']);}/** @var Principal $principal */$principal=$auth['principal'];$bearer=(string)$request->bearer();
 $require=static function(string$p)use($principal):void{if(!$principal->can($p))JsonResponse::error(403,'permission_denied','برای انجام این عملیات دسترسی کافی ندارید.');};

 if($path==='/api/v1/admin/reports/daily'&&$method==='GET'){
  if(!$principal->isSuperAdmin())JsonResponse::error(403,'permission_denied','گزارش تاریخی فقط برای مدیر ارشد مجاز است.');
  $q=$request->query();
  try{JsonResponse::ok(\Babanoell\Partners\Reports\DailyReport::collect($pdo,(string)($q['from']??''),(string)($q['to']??'')));}
  catch(\InvalidArgumentException){JsonResponse::error(422,'invalid_report_range','بازه گزارش معتبر نیست.');}
 }

 $roles=$principal->roles;$staff=$principal->isSuperAdmin()||in_array('admin',$roles,true)||in_array('operations_expert',$roles,true)||in_array('cooperation_operator',$roles,true);
 $ownMerchant=$repository->merchantForUser($principal->userId);$allowedMerchantIds=$principal->ids('merchant');if(is_array($ownMerchant))$allowedMerchantIds[]=(string)$ownMerchant['id'];$allowedMerchantIds=array_values(array_unique($allowedMerchantIds));
 $canMerchant=static function(string$id)use($principal,$staff,$allowedMerchantIds):bool{return$principal->isSuperAdmin()||($staff&&$principal->can('partners.merchants.read'))||in_array($id,$allowedMerchantIds,true);};
 $canCooperation=static function(array$c)use($principal):bool{return$principal->can('partners.cooperation.manage')||((string)($c['applicant_user_id']??'')===$principal->userId)||((string)($c['created_by_user_id']??'')===$principal->userId);};
 $canReadContract=static function(array$c)use($repository,$canCooperation,$loansClient,$bearer,$principal):bool{$type=(string)($c['subject_type']??'');$sid=(string)($c['subject_id']??'');if($type==='cooperation_request'){$co=$repository->getCooperation($sid);return is_array($co)&&$canCooperation($co);}if($type==='loan_request'){if(!$principal->can('loans.requests.read')&&!$principal->can('loans.requests.contract')&&!$principal->isSuperAdmin())return false;return $loansClient->contractContext($sid,$bearer)->ok();}return false;};
 $canManageContract=static function(array$c)use($canReadContract,$principal):bool{if(!$canReadContract($c))return false;if((string)($c['subject_type']??'')==='cooperation_request')return $principal->can('partners.cooperation.manage')||$principal->isSuperAdmin();return $principal->can('loans.requests.contract')||$principal->isSuperAdmin();};
 if($path==='/api/v1/cooperation/form'){
  if($method==='GET'){if(!$principal->can('partners.cooperation.create')&&!$principal->can('partners.cooperation.read')&&!$principal->can('partners.cooperation.manage'))JsonResponse::error(403,'permission_denied','مشاهده فرم همکاری مجاز نیست.');JsonResponse::ok(['form'=>$partnerService->cooperationForm()]);}
  if(in_array($method,['POST','PUT'],true)){if(!$principal->isSuperAdmin()&&!in_array('admin',$roles,true))JsonResponse::error(403,'permission_denied','انتشار تنظیمات فرم فقط برای مدیر سامانه مجاز است.');JsonResponse::ok(['form'=>$partnerService->publishCooperationForm($principal->userId,$request->json())]);}
  JsonResponse::error(405,'method_not_allowed','روش درخواست پشتیبانی نمی‌شود.');
 }
 if($path==='/api/v1/contracts/templates'){
  if($method==='GET'){if(!$principal->can('partners.cooperation.read')&&!$principal->can('partners.cooperation.manage'))JsonResponse::error(403,'permission_denied','دسترسی به قالب قرارداد مجاز نیست.');$kind=trim((string)($request->query()['contract_kind']??''));JsonResponse::ok(['items'=>$contractRepository->listTemplates($kind)]);}
  if($method==='POST'){if(!$principal->can('partners.cooperation.manage')&&!$principal->isSuperAdmin())JsonResponse::error(403,'permission_denied','مدیریت قالب قرارداد مجاز نیست.');JsonResponse::ok(['template'=>$contractService->createTemplate($principal->userId,$request->json())],201);}
 }
 if(preg_match('#^/api/v1/cooperation/requests/([a-f0-9]{26})/contracts$#',$path,$cm)){
  $c=$repository->getCooperation($cm[1]);if($c===null)JsonResponse::error(404,'cooperation_not_found','درخواست همکاری پیدا نشد.');if(!$canCooperation($c))JsonResponse::error(403,'cooperation_access_denied','دسترسی به درخواست همکاری مجاز نیست.');
  if($method==='GET')JsonResponse::ok(['items'=>$contractRepository->bySubject('cooperation_request',$cm[1])]);
  if($method==='POST'){if(!$principal->can('partners.cooperation.manage')&&!$principal->isSuperAdmin())JsonResponse::error(403,'permission_denied','ایجاد قرارداد همکاری مجاز نیست.');JsonResponse::ok(['contract'=>$contractService->createForCooperation($principal->userId,$cm[1],$c,$request->json())],201);}
 }
 if(preg_match('#^/api/v1/loan-requests/([a-f0-9]{26})/contracts$#',$path,$cm)){
  if(!$principal->can('loans.requests.contract')&&!$principal->can('loans.requests.read')&&!$principal->isSuperAdmin())JsonResponse::error(403,'permission_denied','دسترسی به قرارداد پرونده مجاز نیست.');$ctx=$loansClient->contractContext($cm[1],$bearer);if(!$ctx->ok())JsonResponse::error($ctx->status===404?404:403,'loan_contract_access_denied','دسترسی به پرونده قرارداد مجاز نیست.');
  if($method==='GET')JsonResponse::ok(['items'=>$contractRepository->bySubject('loan_request',$cm[1])]);
  if($method==='POST'){if(!$principal->can('loans.requests.contract')&&!$principal->isSuperAdmin())JsonResponse::error(403,'permission_denied','ایجاد قرارداد پرونده مجاز نیست.');$b=$request->json();JsonResponse::ok(['contract'=>$contractService->createForLoan($principal->userId,$cm[1],(string)($b['contract_kind']??'facility_agreement'),$b,$bearer)],201);}
 }
 if(preg_match('#^/api/v1/contracts/([a-f0-9]{26})$#',$path,$cm)&&$method==='GET'){$c=$contractRepository->contract($cm[1]);if($c===null)JsonResponse::error(404,'contract_not_found','قرارداد پیدا نشد.');if(!$canReadContract($c))JsonResponse::error(403,'contract_access_denied','دسترسی به قرارداد مجاز نیست.');JsonResponse::ok(['contract'=>$c,'events'=>$contractRepository->events($cm[1])]);}
 if(preg_match('#^/api/v1/contracts/([a-f0-9]{26})/reissue$#',$path,$cm)&&$method==='POST'){
  $old=$contractRepository->contract($cm[1]);if($old===null)JsonResponse::error(404,'contract_not_found','قرارداد پیدا نشد.');
  if(!$canManageContract($old))JsonResponse::error(403,'contract_manage_denied','دسترسی بازصدور قرارداد مجاز نیست.');
  $new=$contractRevisionService->reissue($cm[1],$principal->userId,$request->json());JsonResponse::ok(['contract'=>$new],201);
 }
 if(preg_match('#^/api/v1/contracts/([a-f0-9]{26})/issue-link$#',$path,$cm)&&$method==='POST'){$c=$contractRepository->contract($cm[1]);if($c===null)JsonResponse::error(404,'contract_not_found','قرارداد پیدا نشد.');if(!$canManageContract($c))JsonResponse::error(403,'contract_manage_denied','مدیریت این قرارداد مجاز نیست.');$r=$contractService->issueLink($cm[1],$principal->userId);JsonResponse::ok(['contract'=>$r['contract'],'public_path'=>'/contract/'.$r['token'],'expires_at'=>$r['expires_at']]);}
 if(preg_match('#^/api/v1/contracts/([a-f0-9]{26})/signing-link$#',$path,$cm)&&$method==='POST'){
  $c=$contractRepository->contract($cm[1]);
  if(!is_array($c)||$c['subject_type']!=='cooperation_request'||$c['signer_user_id']!==$principal->userId||!$canReadContract($c))JsonResponse::error(403,'contract_signer_denied','امضای این قرارداد فقط برای صاحب آن مجاز است.');
  $latest=$contractRepository->bySubject('cooperation_request',$c['subject_id']);
  $revisions=array_filter($latest,static fn($row)=>$row['contract_kind']==='cooperation_agreement');
  if($revisions===[]||(int)$c['revision']!==max(array_column($revisions,'revision'))||$c['mode']!=='digital'||!in_array($c['status'],['pending','sent','viewed'],true))JsonResponse::error(409,'contract_not_signable','این نسخه قرارداد قابل امضا نیست.');
  $issued=$contractService->issueLink($cm[1],$principal->userId);JsonResponse::ok(['public_path'=>'/contract/'.$issued['token']]);
 }
 if(preg_match('#^/api/v1/contracts/([a-f0-9]{26})/sign-physical$#',$path,$cm)&&$method==='POST'){$c=$contractRepository->contract($cm[1]);if($c===null)JsonResponse::error(404,'contract_not_found','قرارداد پیدا نشد.');if(!$canManageContract($c))JsonResponse::error(403,'contract_manage_denied','ثبت امضای این قرارداد مجاز نیست.');JsonResponse::ok(['contract'=>$contractService->markPhysical($cm[1],$principal->userId,$request->clientIp(),$request->userAgent(),$request->json(),$bearer)]);}
 if(preg_match('#^/api/v1/contracts/([a-f0-9]{26})/artifact/retry$#',$path,$cm)&&$method==='POST'){$c=$contractRepository->contract($cm[1]);if($c===null)JsonResponse::error(404,'contract_not_found','قرارداد پیدا نشد.');if(!$canManageContract($c))JsonResponse::error(403,'contract_manage_denied','تلاش مجدد ذخیره قرارداد مجاز نیست.');JsonResponse::ok(['contract'=>$contractService->retryArtifact($cm[1])]);}
 if($path==='/api/v1/stats'&&$method==='GET'){$require('partners.merchants.read');JsonResponse::ok($repository->stats());}
 if($path==='/api/v1/admin/import-loans-shadow'&&$method==='POST'){if(!$principal->isSuperAdmin()&&!in_array('admin',$roles,true))JsonResponse::error(403,'permission_denied','انتقال داده‌های قبلی فقط برای مدیر سامانه مجاز است.');JsonResponse::ok(['import'=>$partnerService->importLoansShadow($principal->userId,$bearer)]);}
if($path==='/api/v1/directory/merchants'&&$method==='GET'){$require('partners.directory.read');$q=$request->query();$r=$repository->listMerchants((int)($q['page']??1),(int)($q['per_page']??25),trim((string)($q['q']??'')),'active');$r['items']=array_map(static fn(array$m):array=>['id'=>(string)$m['id'],'code'=>(string)$m['code'],'name'=>(string)$m['name'],'legal_name'=>(string)($m['legal_name']??''),'credit_level'=>(string)($m['credit_level']??'')],$r['items']);JsonResponse::ok($r,200,['pagination'=>Pagination::fromResult($r)]);}
if(preg_match('#^/api/v1/directory/merchants/([a-f0-9]{26})$#',$path,$m)&&$method==='GET'){$require('partners.directory.read');$merchant=$repository->getMerchant($m[1]);if($merchant===null||(string)$merchant['status']!=='active')JsonResponse::error(404,'merchant_not_found','پذیرنده فعال پیدا نشد.');JsonResponse::ok(['merchant'=>['id'=>(string)$merchant['id'],'code'=>(string)$merchant['code'],'name'=>(string)$merchant['name'],'legal_name'=>(string)($merchant['legal_name']??''),'credit_level'=>(string)($merchant['credit_level']??'')]]);}

 $marketplaceConfig=new \Babanoell\Partners\Marketplace\MarketplaceConfiguration($pdo);
 $invitationService=new \Babanoell\Partners\Marketplace\InvitationService($pdo,$marketplaceConfig);
 if($path==='/api/v1/me/invitation'&&$method==='POST'){
  if(!is_array($ownMerchant)||($ownMerchant['owner_user_id']??'')!==$principal->userId)JsonResponse::error(403,'invitation_owner_required','فقط مالک پذیرنده می‌تواند کد دعوت دریافت کند.');
  JsonResponse::ok(['invitation'=>$invitationService->code($ownMerchant)]);
 }
 if($path==='/api/v1/me/invitation-lock'&&$method==='GET')JsonResponse::ok(['lock'=>$invitationService->current($principal->userId)]);
 if($path==='/api/v1/invitations/apply'&&$method==='POST'){
  if(!in_array('applicant',$roles,true))JsonResponse::error(403,'applicant_required','این کد برای ثبت درخواست شخصی متقاضی است.');
  JsonResponse::ok(['lock'=>$invitationService->apply($principal->userId,trim((string)($request->json()['code']??'')))]);
 }
 if(preg_match('#^/api/v1/directory/merchants/([a-f0-9]{26})/assignment-context$#',$path,$assignment)&&$method==='GET'){
  $require('loans.requests.create');$merchant=$repository->getMerchant($assignment[1]);
  if($merchant===null||$merchant['status']!=='active'||$merchant['cooperation_status']!=='approved')JsonResponse::error(404,'merchant_not_active','پذیرنده فعال و تأییدشده پیدا نشد.');
  $permission=null;$plan=(string)($request->query()['plan_id']??'');
  foreach($repository->planPermissions($assignment[1]) as $item)if(($item['plan_id']??'')===$plan){$permission=$item;break;}
  JsonResponse::ok(['context'=>['merchant_id'=>$merchant['id'],'owner_user_id'=>$merchant['owner_user_id'],'plan_id'=>$plan,'permission'=>$permission]]);
 }
 if($path==='/api/v1/marketplace/catalog'&&$method==='GET'){
  if(!$principal->can('loans.requests.create')&&!$principal->can('partners.marketplace.read')&&!$principal->can('partners.merchants.read'))JsonResponse::error(403,'permission_denied','دسترسی به فهرست بازار مجاز نیست.');
  JsonResponse::ok(['catalog'=>$marketplaceConfig->current()]);
 }
 if($path==='/api/v1/marketplace/configuration'&&$method==='PUT'){
  if(!$principal->isSuperAdmin()&&!in_array('admin',$roles,true))JsonResponse::error(403,'permission_denied','مدیریت تنظیمات بازار فقط برای مدیر سامانه مجاز است.');
  JsonResponse::ok(['catalog'=>$marketplaceConfig->save($request->json(),$principal->userId)]);
 }
 if($path==='/api/v1/marketplace/context'&&$method==='GET'){
  $require('partners.marketplace.read');
  $context=$marketplaceConfig->context($ownMerchant,$ownMerchant!==null?$repository->planPermissions($ownMerchant['id']):[]);
  $context['can_claim']=$ownMerchant!==null&&in_array($ownMerchant['membership_role']??'',['owner','manager','operator'],true)&&$principal->can('partners.marketplace.claim');
  JsonResponse::ok(['context'=>$context]);
 }
 if(preg_match('#^/api/v1/merchants/([a-f0-9]{26})/marketplace$#',$path,$market)&&$method==='PUT'){
  $require('partners.merchants.manage');$merchant=$repository->getMerchant($market[1]);
  if($merchant===null)JsonResponse::error(404,'merchant_not_found','پذیرنده پیدا نشد.');
  if(!$canMerchant($market[1]))JsonResponse::error(403,'merchant_access_denied','دسترسی به پذیرنده مجاز نیست.');
  $b=$request->json();$metadata=$merchant['metadata'];$metadata['marketplace']=\Babanoell\Partners\Marketplace\MarketplaceConfiguration::profile($b,$marketplaceConfig->current());
  JsonResponse::ok($partnerService->updateMerchant($market[1],$principal->userId,['metadata'=>$metadata,'row_version'=>$b['row_version']??0],$bearer));
 }
 if($path==='/api/v1/me/merchant'&&$method==='GET'){JsonResponse::ok(['merchant'=>$ownMerchant]);}
 if($path==='/api/v1/merchants'){
  if($method==='GET'){$require('partners.merchants.read');if(!$staff&&!$principal->isSuperAdmin()){JsonResponse::ok(['items'=>$ownMerchant!==null?[$ownMerchant]:[],'page'=>1,'per_page'=>1,'total'=>$ownMerchant!==null?1:0,'pages'=>1]);}$q=$request->query();$r=$repository->listMerchants((int)($q['page']??1),(int)($q['per_page']??25),trim((string)($q['q']??'')),strtolower(trim((string)($q['status']??''))));JsonResponse::ok($r,200,['pagination'=>Pagination::fromResult($r)]);}
  if($method==='POST'){$require('partners.merchants.manage');JsonResponse::ok($partnerService->createMerchant($principal->userId,$request->json(),$bearer),201);}
 }
 if(preg_match('#^/api/v1/merchants/([a-f0-9]{26})$#',$path,$m)){$merchant=$repository->getMerchant($m[1]);if($merchant===null)JsonResponse::error(404,'merchant_not_found','پذیرنده پیدا نشد.');if(!$canMerchant($m[1]))JsonResponse::error(403,'merchant_access_denied','دسترسی به این پذیرنده مجاز نیست.');if($method==='GET'){$require('partners.merchants.read');JsonResponse::ok(['merchant'=>$merchant,'memberships'=>$repository->memberships($m[1]),'plan_permissions'=>$repository->planPermissions($m[1])]);}if(in_array($method,['PUT','PATCH'],true)){$require('partners.merchants.manage');JsonResponse::ok($partnerService->updateMerchant($m[1],$principal->userId,$request->json(),$bearer));}}
 if(preg_match('#^/api/v1/merchants/([a-f0-9]{26})/memberships$#',$path,$m)){$merchant=$repository->getMerchant($m[1]);if($merchant===null)JsonResponse::error(404,'merchant_not_found','پذیرنده پیدا نشد.');if(!$canMerchant($m[1]))JsonResponse::error(403,'merchant_access_denied','دسترسی به این پذیرنده مجاز نیست.');if($method==='GET'){$require('partners.merchants.read');JsonResponse::ok(['items'=>$repository->memberships($m[1])]);}if($method==='POST'){$require('partners.memberships.manage');JsonResponse::ok(['membership'=>$partnerService->setMembership($m[1],$principal->userId,$request->json())],201);}}
 if(preg_match('#^/api/v1/merchants/([a-f0-9]{26})/plan-permissions$#',$path,$m)&&$method==='GET'){$require('partners.merchants.read');if(!$canMerchant($m[1]))JsonResponse::error(403,'merchant_access_denied','دسترسی به این پذیرنده مجاز نیست.');JsonResponse::ok(['items'=>$repository->planPermissions($m[1])]);}
 if(preg_match('#^/api/v1/merchants/([a-f0-9]{26})/plan-permissions/([a-f0-9]{26})$#',$path,$m)){$merchant=$repository->getMerchant($m[1]);if($merchant===null)JsonResponse::error(404,'merchant_not_found','پذیرنده پیدا نشد.');if(!$canMerchant($m[1]))JsonResponse::error(403,'merchant_access_denied','دسترسی به این پذیرنده مجاز نیست.');if($method==='GET'){$require('partners.merchants.read');JsonResponse::ok(['permission'=>$repository->planPermission($m[1],$m[2])]);}if(in_array($method,['PUT','POST'],true)){$require('partners.plan_access.manage');JsonResponse::ok($partnerService->setPlanPermission($m[1],$m[2],$principal->userId,$request->json(),$bearer));}}
 if(preg_match('#^/api/v1/merchants/([a-f0-9]{26})/invitations$#',$path,$m)&&$method==='POST'){$require('partners.marketplace.manage');if(!$canMerchant($m[1]))JsonResponse::error(403,'merchant_access_denied','دسترسی به این پذیرنده مجاز نیست.');JsonResponse::ok(['invitation'=>$partnerService->createInvitation($m[1],$principal->userId,$request->json())],201);}
 if($path==='/api/v1/invitations/redeem'&&$method==='POST'){$b=$request->json();JsonResponse::ok(['invitation'=>$partnerService->redeemInvitation((string)($b['code']??''),$principal->userId)]);}

 if($path==='/api/v1/admin/cooperation/resolve-legacy'&&$method==='GET'){
  if(!$principal->isSuperAdmin()&&!in_array('admin',$roles,true))JsonResponse::error(403,'permission_denied','دسترسی مدیریتی لازم است.');
  $legacy=(string)($request->query()['legacy_id']??'');if(!preg_match('/^[1-9][0-9]{0,15}$/',$legacy))JsonResponse::error(422,'invalid_legacy_id','شناسه قدیمی معتبر نیست.');
  JsonResponse::ok(['request'=>$repository->cooperationByLegacyId((int)$legacy)]);
 }
 if(preg_match('#^/api/v1/admin/cooperation/([a-f0-9]{26})/profile-corrections$#',$path,$m)){
  if(!$principal->isSuperAdmin()&&!in_array('admin',$roles,true))JsonResponse::error(403,'permission_denied','اصلاح مدیریتی فقط برای مدیر سامانه مجاز است.');
  $old=$repository->getCooperation($m[1]);if($old===null)JsonResponse::error(404,'cooperation_not_found','درخواست همکاری پیدا نشد.');
  if($method==='GET')JsonResponse::ok(['request'=>$old,'corrections'=>$repository->cooperationCorrections($m[1])]);
  if($method==='POST'){
   $b=$request->json();$raw=is_array($b['fields']??null)?$b['fields']:[];
   $allowed=['full_name','father_name','company_name','legal_name','trade_name','national_code','applicant_id_number','national_id','registration_number','mobile','phone','postal_code','address','activity','representative_name'];
   if($raw===[]||array_diff(array_keys($raw),$allowed)!==[])JsonResponse::error(422,'invalid_fields','فقط مشخصات پروفایل همکاری قابل اصلاح است.');
   foreach(['national_code','applicant_id_number','national_id','mobile','postal_code'] as $k){if(!isset($raw[$k]))continue;$raw[$k]=strtr(trim((string)$raw[$k]),['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);if($raw[$k]!==''&&!preg_match('/^[0-9]{8,15}$/',$raw[$k]))JsonResponse::error(422,'invalid_identifier','شناسه یا شماره تماس واردشده معتبر نیست.');}
   JsonResponse::ok(['request'=>$repository->correctCooperationProfile($m[1],$principal->userId,$raw,(int)($b['row_version']??0),trim((string)($b['reason']??'')),($b['acknowledge_signed']??false)===true)]);
  }
 }

 if($path==='/api/v1/cooperation/requests'){
  if($method==='GET'){$require('partners.cooperation.read');$q=$request->query();$applicant=$principal->can('partners.cooperation.manage')?null:$principal->userId;$r=$repository->listCooperation((int)($q['page']??1),(int)($q['per_page']??25),strtolower(trim((string)($q['status']??''))),strtolower(trim((string)($q['request_type']??''))),trim((string)($q['q']??'')),$applicant);JsonResponse::ok($r,200,['pagination'=>Pagination::fromResult($r)]);}
  if($method==='POST'){$require('partners.cooperation.create');$key=IdempotencyKey::normalize($request->header('Idempotency-Key'));$body=$request->json();if(!$principal->isSuperAdmin()&&!in_array('admin',$roles,true))$body['applicant_user_id']=$principal->userId;JsonResponse::ok(['request'=>$partnerService->createCooperation($principal->userId,$body,$key)],201);}
 }
 if(preg_match('#^/api/v1/cooperation/requests/([a-f0-9]{26})$#',$path,$m)&&$method==='GET'){$require('partners.cooperation.read');$c=$repository->getCooperation($m[1]);if($c===null)JsonResponse::error(404,'cooperation_not_found','درخواست همکاری پیدا نشد.');if(!$canCooperation($c))JsonResponse::error(403,'cooperation_access_denied','دسترسی به درخواست همکاری مجاز نیست.');JsonResponse::ok(['request'=>$c,'activation'=>$partnerService->activationService()->state($m[1]),'events'=>$principal->can('partners.audit')?$repository->events('cooperation_request',$m[1]):[]]);}
 if(preg_match('#^/api/v1/cooperation/requests/([a-f0-9]{26})$#',$path,$m)&&$method==='PATCH'){
  $c=$repository->getCooperation($m[1]);if($c===null)JsonResponse::error(404,'cooperation_not_found','درخواست همکاری پیدا نشد.');
  if(!$canCooperation($c))JsonResponse::error(403,'cooperation_access_denied','دسترسی به درخواست همکاری مجاز نیست.');
  JsonResponse::ok(['request'=>$partnerService->updateCooperation($m[1],$principal->userId,$request->json())]);
 }
 if(preg_match('#^/api/v1/cooperation/requests/([a-f0-9]{26})/activation/retry$#',$path,$m)&&$method==='POST'){
  $require('partners.cooperation.manage');JsonResponse::ok($partnerService->retryCooperationActivation($m[1],$principal->userId,$bearer));
 }
 if(preg_match('#^/api/v1/cooperation/requests/([a-f0-9]{26})/transition$#',$path,$m)&&$method==='POST'){
  $body=$request->json();
  if(!$principal->can('partners.cooperation.manage')){
   $c=$repository->getCooperation($m[1]);
   if(!is_array($c)||$c['applicant_user_id']!==$principal->userId||$c['status']!=='need_correction'||($body['status']??'')!=='submitted')JsonResponse::error(403,'permission_denied','فقط ارسال دوباره درخواست شخصی پس از رفع نقص مجاز است.');
   unset($body['assigned_user_id']);
  }
  JsonResponse::ok($partnerService->transitionCooperation($m[1],$principal->userId,$body,$bearer));
 }
 if(preg_match('#^/api/v1/cooperation/requests/([a-f0-9]{26})/documents$#',$path,$m)&&$method==='POST'){$c=$repository->getCooperation($m[1]);if($c===null)JsonResponse::error(404,'cooperation_not_found','درخواست همکاری پیدا نشد.');if(!$canCooperation($c))JsonResponse::error(403,'cooperation_access_denied','دسترسی به درخواست همکاری مجاز نیست.');$b=$request->json();$document=PartnerRules::id((string)($b['document_id']??''),'شناسه مدرک');$relation=trim((string)($b['relation_code']??'attachment'));if($relation===''||mb_strlen($relation)>64)JsonResponse::error(422,'validation_failed','نوع ارتباط مدرک معتبر نیست.');JsonResponse::ok(['link'=>$repository->attachCooperationDocument($m[1],$document,$relation,$principal->userId)],201);}
 if($path==='/api/v1/marketplace/requests'&&$method==='GET'){$require('partners.marketplace.read');JsonResponse::ok($partnerService->marketplace($bearer,$request->query()));}
 if(preg_match('#^/api/v1/marketplace/requests/([a-f0-9]{26})/claim$#',$path,$m)&&$method==='POST'){$require('partners.marketplace.claim');JsonResponse::ok($partnerService->claimMarketplace($m[1],$principal->userId,$bearer),201);}
 if(preg_match('#^/api/v1/merchants/([a-f0-9]{26})/events$#',$path,$m)&&$method==='GET'){$require('partners.audit');if(!$canMerchant($m[1]))JsonResponse::error(403,'merchant_access_denied','دسترسی به این پذیرنده مجاز نیست.');JsonResponse::ok(['items'=>$repository->events('merchant',$m[1])]);}
 JsonResponse::error(404,'route_not_found','مسیر API در این نسخه وجود ندارد.');
}catch(\Babanoell\Partners\Domain\CooperationFormValidationException$e){JsonResponse::error(422,'validation_failed',$e->getMessage(),['fields'=>$e->errors]);}catch(InvalidArgumentException$e){JsonResponse::error(422,'validation_failed',$e->getMessage());}catch(PDOException$e){error_log('[partners] database error: '.$e->getMessage());JsonResponse::error(503,'database_error','عملیات پایگاه داده کامل نشد. گزارش فنی ثبت شده است.');}catch(RuntimeException$e){$known=['cooperation_activation_pending'=>[409,'پذیرنده باید از مسیر فعال‌سازی همکاری آماده شود؛ از گزینه تلاش مجدد استفاده کنید.'],'cooperation_edit_locked'=>[409,'ویرایش درخواست در این مرحله مجاز نیست.'],'cooperation_contract_locked'=>[409,'برای این درخواست قرارداد صادر شده است؛ اصلاح اطلاعات باید توسط کارشناس و با بازصدور قرارداد انجام شود.'],'cooperation_contract_incomplete'=>[409,'قرارداد همکاری باید امضا و فایل نهایی آن ذخیره شده باشد.'],'cooperation_contract_changed'=>[409,'قرارداد همکاری تغییر کرده است؛ پس از بررسی و امضای نسخه جدید، همکاری را دوباره تأیید کنید.'],'cooperation_revision_conflict'=>[409,'اطلاعات درخواست تغییر کرده است؛ صفحه را تازه‌سازی کنید.'],'cooperation_activation_missing'=>[409,'درخواست برای فعال‌سازی آماده نیست.'],'cooperation_activation_not_configured'=>[503,'ارتباط فعال‌سازی حساب تنظیم نشده است.'],'marketplace_revision_conflict'=>[409,'تنظیمات بازار هم‌زمان تغییر کرده است؛ صفحه را تازه‌سازی کنید.'],'marketplace_claim_conflict'=>[409,'پرونده با شرایط فعلی پذیرنده قابل پذیرش نیست یا قبلاً پذیرفته شده است؛ فهرست بازار را تازه‌سازی کنید.'],'form_revision_conflict'=>[409,'نسخه تنظیمات تغییر کرده است؛ صفحه را تازه‌سازی و ورودی‌ها را دوباره بررسی کنید.'],'cooperation_idempotency_conflict'=>[409,'این شناسه عملیات قبلاً با اطلاعات دیگری استفاده شده است.'],'cooperation_documents_unavailable'=>[503,'وضعیت مدارک همکاری قابل تأیید نیست؛ پس از برقراری ارتباط دوباره تلاش کنید.'],'cooperation_definition_invalid'=>[503,'تعریف فرم همکاری قابل تأیید نیست.']];$code=$e->getMessage();if(isset($known[$code]))JsonResponse::error($known[$code][0],$code,$known[$code][1]);error_log('[partners] runtime error: '.$e->getMessage());JsonResponse::error(409,'operation_failed','عملیات کامل نشد. وضعیت رکورد را تازه‌سازی کنید و دوباره تلاش کنید.');}catch(Throwable$e){error_log('[partners] unexpected error: '.get_class($e).': '.$e->getMessage());JsonResponse::error(500,'internal_error','خطای غیرمنتظره‌ای رخ داد. دوباره تلاش کنید.');}
