<?php
declare(strict_types=1);
use Babanoell\Documents\Domain\ApiException;
use Babanoell\Documents\Domain\DocumentCatalog;
use Babanoell\Documents\Http\FileResponse;
use Babanoell\Documents\Http\JsonResponse;
use Babanoell\Documents\Http\Request;
use Babanoell\Documents\Security\Authenticator;
use Babanoell\Documents\Security\Principal;

$root=dirname(__DIR__);
require_once $root.'/src/Platform/ReleaseTransaction.php';
\Babanoell\Documents\Platform\ReleaseTransaction::guard($root);

$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
if(!is_file($root.'/storage/installed.lock')||!is_file($root.'/config/local.env.php')){
    if(str_starts_with($path,'/api/'))JsonResponse::error(503,'service_not_installed','سرویس مدارک هنوز نصب نشده است.');
    header('Location: /install.php',true,302);exit;
}
require$root.'/bootstrap.php';
if(strtolower($config->environment())==='production'&&!\Babanoell\Documents\Platform\ProxyContext::secure($_SERVER)) JsonResponse::error(403,'https_required','ارتباط HTTPS لازم است.');
$request=Request::fromGlobals();$method=$request->method();$path=$request->path();
\Babanoell\Documents\Platform\RequestContext::initialize($request->header('X-Request-Id'),'documents');
if(str_starts_with($path,'/api/v1/')){
$serviceAuth=\Babanoell\Documents\Platform\ServiceAuth::verify($request->header(\Babanoell\Documents\Platform\ServiceAuth::HEADER_SERVICE),$request->header(\Babanoell\Documents\Platform\ServiceAuth::HEADER_TIMESTAMP),$request->header(\Babanoell\Documents\Platform\ServiceAuth::HEADER_SIGNATURE),$method,$path,$request->rawBody(),$config->serviceAuthSecret(),$config->serviceAuthMode());
if(!$serviceAuth['ok']) JsonResponse::error($serviceAuth['error']==='service_auth_not_configured'?503:401,(string)$serviceAuth['error'],'احراز هویت سرویس فراخوان نامعتبر است.');
if($serviceAuth['authenticated']===true) \Babanoell\Documents\Platform\RequestContext::setCallerService(is_string($serviceAuth['service'])?$serviceAuth['service']:null);
}

try{
    if($path==='/health')JsonResponse::send(200,['service'=>$config->serviceName(),'status'=>'ok','environment'=>$config->environment(),'version'=>'1.9.0','timestamp'=>gmdate('c')]);
    if($path==='/healthz'){
        $dbOk=false;$storageOk=is_dir($root.'/storage/private')&&is_writable($root.'/storage/private');
        try{$dbOk=(int)$pdo->query('SELECT 1')->fetchColumn()===1;}catch(Throwable){}
        $ih=$identityClient->health();$lh=$loansClient->health();$identityOk=$ih->ok()&&(($ih->json['status']??null)==='ok');$loansOk=$lh->ok()&&(($lh->json['status']??null)==='ok');$ok=$dbOk&&$storageOk&&$identityOk&&$loansOk;
        JsonResponse::send($ok?200:503,['service'=>'documents','status'=>$ok?'ok':'error','database'=>$dbOk?'ok':'error','private_storage'=>$storageOk?'ok':'error','identity'=>$identityOk?'ok':'unavailable','loans'=>$loansOk?'ok':'unavailable','environment'=>$config->environment(),'version'=>'1.9.0','timestamp'=>gmdate('c')]);
    }

    // Short-lived one-time access token. It is intentionally the only unauthenticated file route.
    if(preg_match('#^/api/v1/access/(download|preview)/([A-Za-z0-9_-]{30,120})$#',$path,$m)&&$method==='GET'){
        $purpose=$m[1];$token=$repository->consumeAccessToken($m[2],$purpose);if($token===null)JsonResponse::error(404,'access_token_invalid','لینک دریافت معتبر نیست یا منقضی شده است.');
        $version=$repository->versionById((string)$token['document_id'],(string)$token['version_id']);if($version===null)JsonResponse::error(404,'document_version_not_found','نسخه فایل پیدا نشد.');
        $file=$privateStorage->path((string)$version['storage_key']);$hash=hash_file('sha256',$file);if(!is_string($hash)||!hash_equals((string)$version['checksum_sha256'],$hash))JsonResponse::error(503,'document_integrity_failed','صحت فایل ذخیره‌شده تأیید نشد.');
        $repository->audit((string)$token['document_id'],(string)$token['version_id'],null,null,$purpose==='preview'?'document.previewed_by_token':'document.downloaded_by_token',(string)$token['issued_to_user_id'],'allowed',null,['purpose'=>$purpose],$request->clientIp(),$request->userAgent());
        FileResponse::stream($file,(string)$version['mime_type'],(string)$version['original_name'],$purpose==='preview');
    }

    if(!str_starts_with($path,'/api/v1/'))JsonResponse::error(404,'route_not_found','مسیر درخواستی وجود ندارد.');
    if($path==='/api/v1/meta/platform-contract'&&$method==='GET')JsonResponse::ok(['contract'=>\Babanoell\Documents\Platform\ServiceBoundary::describe(),'service_auth'=>['mode'=>$config->serviceAuthMode(),'configured'=>$config->serviceAuthSecret()!=='']]);
    $auth=(new Authenticator($identityClient))->authenticate($request);if($auth['principal']===null){$e=$auth['error'];JsonResponse::error($e['status'],$e['code'],$e['message']);}
    /** @var Principal $principal */$principal=$auth['principal'];$bearer=(string)$request->bearer();
    $require=static function(string$permission)use($principal):void{if(!$principal->can($permission))JsonResponse::error(403,'permission_denied','برای انجام این عملیات دسترسی کافی ندارید.');};

    if($path==='/api/v1/categories'&&$method==='GET'){$require('documents.view');$items=[];foreach(DocumentCatalog::all()as$code=>$item)$items[]=['code'=>$code]+$item;JsonResponse::ok(['items'=>$items,'max_upload_bytes'=>$config->maxUploadBytes()]);}
    if($path==='/api/v1/stats'&&$method==='GET'){$require('documents.view');JsonResponse::ok($repository->stats());}
    if($path==='/api/v1/documents'&&$method==='GET'){
        if(!$principal->isSuperAdmin()&&!$principal->can('documents.manage'))JsonResponse::error(403,'permission_denied','فهرست سراسری مدارک فقط برای مدیران مجاز است.');$q=$request->query();$status=strtolower(trim((string)($q['status']??'active')));if(!in_array($status,['active','archived','all'],true))$status='active';$result=$repository->listAll((int)($q['page']??1),(int)($q['per_page']??25),trim((string)($q['q']??'')),trim((string)($q['category']??'')),trim((string)($q['review_status']??'')),$status);JsonResponse::ok($result,200,['pagination'=>\Babanoell\Documents\Http\Pagination::fromResult($result)]);
    }

    if(preg_match('#^/api/v1/entities/([a-z_]{2,64})/([a-f0-9]{26})/documents$#',$path,$m)){
        $entityType=$m[1];$entityId=$m[2];$entityAccess->assert($entityType,$entityId,$bearer,$principal);
        if($method==='GET'){$require('documents.view');$rawInclude=strtolower(trim((string)($request->query()['include_archived']??'')));$includeArchived=in_array($rawInclude,['1','true','yes','on'],true)&&($principal->isSuperAdmin()||$principal->can('documents.manage'));JsonResponse::ok(['items'=>$repository->listByEntity($entityType,$entityId,$includeArchived)]);}
        if($method==='POST'){$file=$request->file('file');if($file===null)JsonResponse::error(422,'file_required','فایل مدرک ارسال نشده است.');JsonResponse::ok(['document'=>$documentService->upload($principal,$bearer,$entityType,$entityId,$file,$request->form(),$request->clientIp(),$request->userAgent())],201);}
    }
    if(preg_match('#^/api/v1/entities/loan_request/([a-f0-9]{26})/completion$#',$path,$m)&&$method==='GET'){$require('documents.view');JsonResponse::ok($documentService->completion($principal,$bearer,$m[1]));}

    if(preg_match('#^/api/v1/files/([a-f0-9]{26})$#',$path,$m)&&$method==='GET'){$require('documents.view');JsonResponse::ok(['document'=>$documentService->requireAccessible($principal,$bearer,$m[1])]);}
    if(preg_match('#^/api/v1/files/([a-f0-9]{26})/versions$#',$path,$m)&&$method==='POST'){$file=$request->file('file');if($file===null)JsonResponse::error(422,'file_required','فایل جایگزین ارسال نشده است.');JsonResponse::ok(['document'=>$documentService->replace($principal,$bearer,$m[1],$file,$request->clientIp(),$request->userAgent())],201);}
    if(preg_match('#^/api/v1/files/([a-f0-9]{26})/review$#',$path,$m)&&$method==='POST'){$b=$request->json();JsonResponse::ok(['document'=>$documentService->review($principal,$bearer,$m[1],(string)($b['status']??''),trim((string)($b['reason']??'')),$request->clientIp(),$request->userAgent())]);}
    if(preg_match('#^/api/v1/files/([a-f0-9]{26})/archive$#',$path,$m)&&$method==='POST'){$b=$request->json();JsonResponse::ok(['document'=>$documentService->archive($principal,$bearer,$m[1],trim((string)($b['reason']??'')),$request->clientIp(),$request->userAgent())]);}
    if(preg_match('#^/api/v1/files/([a-f0-9]{26})/audit$#',$path,$m)&&$method==='GET'){$require('documents.audit');$documentService->requireAccessible($principal,$bearer,$m[1]);JsonResponse::ok(['reviews'=>$repository->reviews($m[1]),'audit'=>$repository->audits($m[1])]);}
    if(preg_match('#^/api/v1/files/([a-f0-9]{26})/access-token$#',$path,$m)&&$method==='POST'){
        $b=$request->json();$purpose=strtolower(trim((string)($b['purpose']??'download')));if(!in_array($purpose,['download','preview'],true))JsonResponse::error(422,'validation_failed','کاربرد لینک دسترسی معتبر نیست.');$versionId=trim((string)($b['version_id']??''));$read=$documentService->fileForRead($principal,$bearer,$m[1],$versionId!==''?$versionId:null);$token=$repository->issueAccessToken($m[1],(string)$read['version']['id'],$principal->userId,$purpose,120);$repository->audit($m[1],(string)$read['version']['id'],null,null,'document.access_token_issued',$principal->userId,'allowed',null,['expires_in'=>120,'purpose'=>$purpose],$request->clientIp(),$request->userAgent());JsonResponse::ok(['token'=>$token,'expires_in'=>120,'purpose'=>$purpose,'path'=>'/api/v1/access/'.$purpose.'/'.$token],201);
    }
    if(preg_match('#^/api/v1/files/([a-f0-9]{26})/(download|preview)$#',$path,$m)&&$method==='GET'){
        $versionId=trim((string)($request->query()['version_id']??''));$read=$documentService->fileForRead($principal,$bearer,$m[1],$versionId!==''?$versionId:null);$version=$read['version'];$event=$m[2]==='preview'?'document.previewed':'document.downloaded';$repository->audit($m[1],(string)$version['id'],null,null,$event,$principal->userId,'allowed',null,['version'=>(int)$version['version']],$request->clientIp(),$request->userAgent());FileResponse::stream((string)$read['path'],(string)$version['mime_type'],(string)$version['original_name'],$m[2]==='preview');
    }
    JsonResponse::error(404,'route_not_found','مسیر API در این نسخه وجود ندارد.');
}catch(ApiException$e){JsonResponse::error($e->status,$e->errorCode,$e->getMessage());}
catch(InvalidArgumentException$e){JsonResponse::error(422,'validation_failed',$e->getMessage());}
catch(PDOException$e){error_log('[documents] database error: '.$e->getMessage());JsonResponse::error(503,'database_error','عملیات پایگاه داده کامل نشد. گزارش فنی ثبت شده است.');}
catch(RuntimeException$e){error_log('[documents] runtime error: '.$e->getMessage());JsonResponse::error(409,'operation_failed','عملیات کامل نشد. وضعیت رکورد را تازه‌سازی کنید و دوباره تلاش کنید.');}
catch(Throwable$e){error_log('[documents] unexpected error: '.get_class($e).': '.$e->getMessage());JsonResponse::error(500,'internal_error','خطای غیرمنتظره‌ای رخ داد. دوباره تلاش کنید.');}
