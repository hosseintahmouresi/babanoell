<?php
declare(strict_types=1);
namespace Babanoell\Integrations\Provider;
use Babanoell\Integrations\Http\TransportInterface;
use Babanoell\Integrations\Platform\CreditReportProof;
use Babanoell\Integrations\Support\Config;
use Babanoell\Integrations\Support\ProviderException;
use Babanoell\Integrations\Support\Validation;
final class Credit {
 private ?array $token=null;
 public function __construct(private readonly TransportInterface $http){}
 public function enabled():bool{return Config::get('INTEGRATIONS_ICS24_USERNAME')!==''&&Config::get('INTEGRATIONS_ICS24_PASSWORD')!=='';}
 private function base():string {$s=rtrim(Config::get('INTEGRATIONS_ICS24_URL','https://mycredit.ics24.ir'),'/');if($s!=='https://mycredit.ics24.ir')throw new ProviderException('credit_endpoint_not_allowed',503);return $s;}
 private function auth():array {
  if($this->token!==null)return $this->token;
  if(!$this->enabled())throw new ProviderException('credit_not_configured',503);
  $r=$this->http->request('POST',$this->base().'/Connect/Token',['Username'=>Config::get('INTEGRATIONS_ICS24_USERNAME'),'Password'=>Config::get('INTEGRATIONS_ICS24_PASSWORD')],['x-version'=>'2.0'],24,'form',['mycredit.ics24.ir']);
  $obj=$r['data']['data']??[];
  if($r['status']!==200||($r['data']['hasError']??true)!==false||!is_array($obj)||!is_string($obj['accessToken']??null)||!is_string($obj['apiKey']??null)||$obj['accessToken']===''||$obj['apiKey']==='')throw new ProviderException('credit_auth_failed',502);
  return $this->token=['accessToken'=>$obj['accessToken'],'apiKey'=>$obj['apiKey']];
 }
 private function call(string $method,string $path,array $payload=[]):array {
  $auth=$this->auth();$r=$this->http->request($method,$this->base().$path,$method==='GET'?null:$payload,['Authorization'=>'Bearer '.$auth['accessToken'],'x-apikey'=>$auth['apiKey'],'x-version'=>'2.0'],28,'form',['mycredit.ics24.ir']);
  if($r['status']<200||$r['status']>=300||$r['valid_json']!==true||($r['data']['hasError']??true)!==false)throw new ProviderException('credit_provider_rejected',502);
  return $r['data'];
 }
 public function start(string $national,string $mobile):array {
  $r=$this->call('POST','/b2b/api/request',['RealPersonNationalCode'=>Validation::national($national),'MobileNumber'=>Validation::mobile($mobile)]);
  $v=$r['data']??null;if(!is_string($v)||!preg_match('/^[A-Za-z0-9_-]{6,180}$/D',$v))throw new ProviderException('credit_reference_missing',502);
  return ['reference'=>$v,'provider'=>'ics24','status'=>'otp_requested'];
 }
 public function validate(string $reference,string $otp):array {
  $ref=Validation::opaque($reference);$r=$this->call('POST','/b2b/api/request/'.rawurlencode($ref).'/validate',['Token'=>Validation::otp($otp)]);
  return ['accepted'=>true,'status'=>is_scalar($r['data']??null)?(string)$r['data']:'InProcessing'];
 }
 public function status(string $reference):array {
  $ref=Validation::opaque($reference);$r=$this->call('GET','/b2b/api/request/'.rawurlencode($ref).'/status');
  $data=$r['data']??null;
  if(!is_array($data)||!is_string($data['status']??null)||strlen($data['status'])>64)throw new ProviderException('credit_status_invalid',502);
  $title=is_string($data['statusTitle']??null)?(function_exists('mb_substr')?mb_substr($data['statusTitle'],0,300):substr($data['statusTitle'],0,600)):'';
  $link=$data['reportLink']??'';
  if(!is_string($link))throw new ProviderException('credit_report_link_invalid',502);
  return ['status'=>$data['status'],'status_title'=>$title,'report_code'=>$link===''?'':self::reportCode($link)];
 }
 public static function reportCode(string $link):string {
  $parts=parse_url($link);
  if($parts===false||isset($parts['user'])||isset($parts['pass'])||isset($parts['fragment'])||(isset($parts['scheme'])&&!isset($parts['host'])))throw new \InvalidArgumentException('credit_report_link_invalid');
  if(isset($parts['host'])&&(strtolower($parts['host'])!=='mycredit.ics24.ir'||strtolower($parts['scheme']??'')!=='https'||isset($parts['port'])))throw new \InvalidArgumentException('credit_report_link_invalid');
  $path=(string)($parts['path']??'');
  if(preg_match('~^/?report/([A-Za-z0-9_-]{6,180})(?:/json)?/?$~D',$path,$match))return $match[1];
  if(!isset($parts['host'])&&preg_match('/^[A-Za-z0-9_-]{6,180}$/D',$path))return $path;
  throw new \InvalidArgumentException('credit_report_link_invalid');
 }
 public function report(string $reportCode):array {
  $ref=Validation::opaque($reportCode);$r=$this->call('GET','/report/'.rawurlencode($ref).'/json');
  $report=$r['data']??null;
  if(is_string($report)){$report=json_decode($report,true,64,JSON_THROW_ON_ERROR);}
  if(!is_array($report))throw new ProviderException('credit_report_invalid',502);
  CreditReportProof::canonical($report);
  return ['report'=>$report];
 }
 public function regenerate(string $reference):array {
  $this->call('POST','/b2b/api/request/'.rawurlencode(Validation::opaque($reference)).'/reGenerateReport');
  return ['accepted'=>true];
 }
 public function finalize(array $context,string $secret):array {
  $national=Validation::national($context['national_code']??'');$mobile=Validation::mobile($context['mobile']??'');
  $fingerprint=hash('sha256',$national.'|'.$mobile);
  if(!is_string($context['subject_fingerprint']??null)||!hash_equals($fingerprint,$context['subject_fingerprint']))throw new \InvalidArgumentException('credit_subject_mismatch');
  $status=$this->status(Validation::opaque($context['reference']??''));
  if($status['status']!=='ReportGenerated'||$status['report_code']==='')return ['ready'=>false,'provider_status'=>$status['status'],'status_title'=>$status['status_title']];
  $report=$this->report($status['report_code'])['report'];
  CreditReportProof::assertSubject($report,$national);CreditReportProof::summary($report);
  $binding=array_intersect_key($context,array_flip(['verification_id','request_id','request_hash','subject_fingerprint','reference']));
  $binding['report_code']=$status['report_code'];
  return ['ready'=>true,'provider_status'=>'ReportGenerated','report'=>$report,'proof'=>CreditReportProof::issue($binding,$report,$secret)];
 }
}
