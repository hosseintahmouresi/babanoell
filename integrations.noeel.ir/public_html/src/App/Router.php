<?php
declare(strict_types=1);
namespace Babanoell\Integrations\App;
use Babanoell\Integrations\Http\Request;
use Babanoell\Integrations\Http\Transport;
use Babanoell\Integrations\Database\Operations;
use Babanoell\Integrations\Provider\{Sms,Shahkar,Credit,Greenpay};
use Babanoell\Integrations\Support\{Config,Response,ProviderException,Validation};
use Babanoell\Integrations\Platform\{RequestContext,ServiceAuth,PayloadAuth};
final class Router {
 public function __construct(private readonly \PDO $pdo,private readonly ?\Babanoell\Integrations\Http\TransportInterface $creditTransport=null,private readonly ?\Babanoell\Integrations\Http\TransportInterface $greenpayTransport=null){}
 public function handle(Request $r):never {
  $method=$r->method();$path=$r->path();RequestContext::initialize($r->header('X-Request-Id'),'integrations');
  if($method==='GET'&&$path==='/health')Response::ok(['service'=>'integrations','version'=>(require dirname(__DIR__,2).'/config/version.php')['version'],'status'=>'ok']);
  if($method==='GET'&&$path==='/healthz'){$ready=false;try{$ready=(int)$this->pdo->query('SELECT 1')->fetchColumn()===1;}catch(\Throwable){}Response::json($ready?200:503,['service'=>'integrations','status'=>$ready?'ok':'degraded','database'=>$ready?'ok':'error']);}
  if(!str_starts_with($path,'/api/v1/'))Response::fail(404,'not_found','مسیر پیدا نشد.');
  if(Config::production() && !\Babanoell\Integrations\Platform\ProxyContext::secure($_SERVER))Response::fail(403,'https_required','HTTPS الزامی است.');
  if(strlen($r->rawBody())>32768)Response::fail(413,'payload_too_large','اندازه درخواست بیش از حد مجاز است.');
  try {$a=ServiceAuth::verify($r->header(ServiceAuth::HEADER_SERVICE),$r->header(ServiceAuth::HEADER_TIMESTAMP),$r->header(ServiceAuth::HEADER_SIGNATURE),$method,$path,$r->rawBody(),Config::requireSecret(),'required');}
  catch(\RuntimeException){Response::fail(503,'service_not_configured','احراز هویت بین سرویس‌ها تنظیم نشده است.');}
  if(!$a['ok']||!$a['authenticated']||!is_string($a['service']))Response::fail(401,'invalid_service_signature','امضای بین‌سرویسی معتبر نیست.');
  $caller=$a['service'];if(!PayloadAuth::verify($r->header('X-Noel-Payload-Signature'),$caller,(string)$r->header('X-Noel-Timestamp'),(string)$r->header('X-Request-Id'),$method,$path,$r->rawBody(),Config::requireSecret()))Response::fail(401,'payload_signature_invalid','امضای بدنه درخواست معتبر نیست.');$routes=[
   'POST /api/v1/sms/send-pattern'=>['partners','identity','loans','finance'],
   'POST /api/v1/shahkar/verify'=>['identity','loans'],
   'POST /api/v1/credit/request'=>['loans'],
   'POST /api/v1/credit/verify'=>['loans'],
   'POST /api/v1/credit/status'=>['loans'],
   'POST /api/v1/credit/report'=>['loans'],
   'POST /api/v1/credit/finalize'=>['loans'],
   'GET /api/v1/greenpay/readiness'=>['finance'],
   'GET /api/v1/greenpay/configuration'=>['finance'],
   'POST /api/v1/greenpay/request-debit'=>['finance'],
   'GET /api/v1/greenpay/transfer-capabilities'=>['finance'],
   'POST /api/v1/greenpay/send-otp'=>['finance'],
   'POST /api/v1/greenpay/transfer'=>['finance'],
  ];
  $route=$method.' '.$path;if(!isset($routes[$route]))Response::fail(404,'route_not_found','مسیر وجود ندارد.');
  if(!in_array($caller,$routes[$route],true))Response::fail(403,'caller_not_allowed','سرویس فراخوان مجاز نیست.');
  try {
   $b=$r->json();$http=new Transport();$ops=new Operations($this->pdo,Config::requireSecret());$key=$r->header('Idempotency-Key')??'';
   $run=function(string $operation,string $target,callable $handler,int $limit=5,int $window=3600)use($ops,$caller,$key,$b):array {
    $state=$ops->claim($caller,$operation,$key,$b,$target,$limit,$window);
    if($state['state']==='replay')return $state['result']+['idempotent_replay'=>true];
    try{$result=$handler();$ops->finish($state['id'],'succeeded',$result);return $result;}
    catch(ProviderException $e){$ops->finish($state['id'],in_array($e->publicCode,['provider_timeout','provider_transport_failure','sms_delivery_unconfirmed','greenpay_provider_rejected'],true)?'uncertain':'failed',[],$e->publicCode);throw $e;}
    catch(\Throwable $e){$ops->finish($state['id'],'uncertain',[],'unhandled');throw $e;}
   };
   switch($route){
    case 'POST /api/v1/sms/send-pattern':
     $to=Validation::mobile($b['to']??'');$pattern=filter_var($b['pattern_id']??null,FILTER_VALIDATE_INT);$vars=$b['variables']??null;
     if($pattern===false||$pattern<1||!is_array($vars)||array_values($vars)!==$vars)throw new \InvalidArgumentException('invalid_sms_payload');
     Response::ok($run('sms.pattern',$to,fn()=>(new Sms())->send($to,$pattern,$vars),6));
    case 'POST /api/v1/shahkar/verify':
     $n=Validation::national($b['national_code']??'');$m=Validation::mobile($b['mobile']??'');
     Response::ok($run('shahkar.verify',$n.'|'.$m,fn()=>(new Shahkar($http))->verify($m,$n),8));
    case 'POST /api/v1/credit/request':
     $n=Validation::national($b['national_code']??'');$m=Validation::mobile($b['mobile']??'');
     Response::ok($run('credit.request',$n.'|'.$m,fn()=>(new Credit($this->creditTransport??$http))->start($n,$m),5,900));
    case 'POST /api/v1/credit/verify':
     $ref=Validation::opaque($b['reference']??'');$otp=Validation::otp($b['otp']??'');
     Response::ok($run('credit.verify',$ref,fn()=>(new Credit($this->creditTransport??$http))->validate($ref,$otp),8,900));
    case 'POST /api/v1/credit/status':
     $ref=Validation::opaque($b['reference']??'');Response::ok((new Credit($this->creditTransport??$http))->status($ref));
    case 'POST /api/v1/credit/report':
     $ref=Validation::opaque($b['report_code']??'');Response::ok((new Credit($this->creditTransport??$http))->report($ref));
    case 'POST /api/v1/credit/finalize':
     $ref=Validation::opaque($b['reference']??'');
     // The reference implementation permits 180 status polls per fifteen minutes.
     $poll=$ops->claim($caller,'credit.poll',hash('sha256','credit-poll|'.RequestContext::id()),$b,$ref,180,900);
     if($poll['state']!=='new')throw new ProviderException('credit_poll_already_processed',409);
     try{
      $credit=new Credit($this->creditTransport??$http);$result=$credit->finalize($b,Config::requireSecret());
      if(($result['ready']??false)!==true&&in_array($result['provider_status']??'', ['ReportGenerationFailed','ReportGeneratedButDataLost','GenerateReportFailed','ReportDataLost'],true)){
       try{
        $regenerate=$ops->claim($caller,'credit.regenerate',hash('sha256','credit-regenerate|'.$ref.'|'.gmdate('YmdH')),['reference'=>$ref],$ref,1);
        if($regenerate['state']==='new'){
         try{$credit->regenerate($ref);$ops->finish($regenerate['id'],'succeeded',['accepted'=>true]);$result['regenerated']=true;}
         catch(\Throwable $error){$ops->finish($regenerate['id'],'uncertain',[],'credit_regeneration_unconfirmed');throw $error;}
        }
       }catch(ProviderException $error){if(!in_array($error->publicCode,['rate_limit_exceeded','prior_operation_not_confirmed'],true))throw $error;}
      }
      // Persist only the status; raw reports, identities and signed evidence are never operation logs.
      $ops->finish($poll['id'],'succeeded',['ready'=>($result['ready']??false)===true,'provider_status'=>$result['provider_status']??'']);
     }catch(\Throwable $error){$ops->finish($poll['id'],'failed',[],'credit_poll_failed');throw $error;}
     Response::ok($result);
    case 'GET /api/v1/greenpay/configuration':Response::ok((new Greenpay($this->greenpayTransport??$http))->configuration());
    case 'GET /api/v1/greenpay/readiness':Response::ok((new Greenpay($this->greenpayTransport??$http))->readiness());
    case 'GET /api/v1/greenpay/transfer-capabilities':
     $executionKey=trim(Config::get('INTEGRATIONS_GREENPAY_EXECUTION_KEY'));
     $provided=(string)($r->header('X-Noel-Greenpay-Authorization')??'');
     if(strlen($executionKey)<32||!preg_match('/^[a-f0-9]{64}$/D',$provided)||!hash_equals(hash_hmac('sha256','greenpay-transfer-v1||',$executionKey),$provided))throw new ProviderException('greenpay_transfer_authorization_invalid',403);
     Response::ok(['authorized'=>true,'transfers_enabled'=>Config::get('INTEGRATIONS_GREENPAY_TRANSFERS_ENABLED','0')==='1']);
    case 'POST /api/v1/greenpay/send-otp':
     $p=(string)($b['pocket_number']??'');$amount=filter_var($b['amount_rial']??null,FILTER_VALIDATE_INT);
     if(!preg_match('/^[0-9]{8,32}$/D',$p)||$amount===false||$amount<1)throw new \InvalidArgumentException('invalid_greenpay_request');
     Response::ok($run('greenpay.otp',$p,fn()=>(new Greenpay($this->greenpayTransport??$http))->sendOtp($p,$amount),5));
    case 'POST /api/v1/greenpay/request-debit':
    case 'POST /api/v1/greenpay/transfer':
     if(Config::get('INTEGRATIONS_GREENPAY_TRANSFERS_ENABLED','0')!=='1')throw new ProviderException('greenpay_transfer_disabled',503);
     $executionKey=trim(Config::get('INTEGRATIONS_GREENPAY_EXECUTION_KEY'));
     $intent=(string)($b['finance_intent_id']??'');
     if(strlen($executionKey)<32 || preg_match('/^[a-f0-9]{26}$/D',$intent)!==1 || !hash_equals($intent,(string)$key))throw new ProviderException('greenpay_transfer_authorization_missing',403);
     $expected=hash_hmac('sha256','greenpay-transfer-v1|'.$key.'|'.$r->rawBody(),$executionKey);
     $actual=(string)($r->header('X-Noel-Greenpay-Authorization')??'');
     if(!preg_match('/^[a-f0-9]{64}$/D',$actual)||!hash_equals($expected,$actual))throw new ProviderException('greenpay_transfer_authorization_invalid',403);
     $ref=Validation::opaque($b['reference']??'');
     if($ref!=='N'.$intent)throw new ProviderException('greenpay_reference_mismatch',403);
     Response::ok($run($route==='POST /api/v1/greenpay/request-debit'?'greenpay.request-debit':'greenpay.transfer',$ref,fn()=>$route==='POST /api/v1/greenpay/request-debit'?(new Greenpay($this->greenpayTransport??$http))->requestDebit($b):(new Greenpay($this->greenpayTransport??$http))->transfer($b),1));
   }
  }catch(\InvalidArgumentException){Response::fail(422,'validation_failed','داده‌های ورودی معتبر نیست.');}
   catch(ProviderException $e){Response::fail($e->httpStatus,$e->publicCode,'ارتباط با سرویس‌دهنده کامل نشد یا قابلیت فعال نیست.');}
   catch(\PDOException $e){error_log('[integrations] database_error '.get_class($e));Response::fail(503,'database_unavailable','ثبت عملیات انجام نشد.');}
   catch(\Throwable $e){error_log('[integrations] unexpected_error '.get_class($e));Response::fail(500,'internal_error','عملیات تکمیل نشد.');}
  Response::fail(404,'not_found','مسیر پیدا نشد.');
 }
}
