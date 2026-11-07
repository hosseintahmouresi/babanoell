<?php
declare(strict_types=1);
namespace Babanoell\Partners\Contract;
use Babanoell\Partners\Http\DocumentsClient;use Babanoell\Partners\Http\IntegrationsClient;use Babanoell\Partners\Http\LoansClient;use InvalidArgumentException;use RuntimeException;
final class ContractService
{
 public const KINDS=['cooperation_agreement','loan_application','facility_agreement'];
 public function __construct(private readonly ContractRepository $repo,private readonly LoansClient $loans,private readonly DocumentsClient $documents,private readonly bool $production=false,private readonly ?IntegrationsClient $integrations=null){}
 public function createTemplate(string $actor,array $b):array{$code=strtolower(trim((string)($b['code']??'')));$name=trim((string)($b['name']??''));$kind=strtolower(trim((string)($b['contract_kind']??'')));$html=trim((string)($b['content_html']??''));$status=strtolower(trim((string)($b['status']??'draft')));if(!preg_match('/^[a-z0-9_\-]{3,80}$/',$code)||$name===''||!in_array($kind,self::KINDS,true)||$html===''||!in_array($status,['draft','active'],true))throw new InvalidArgumentException('اطلاعات قالب قرارداد معتبر نیست.');return $this->repo->createTemplate($actor,['code'=>$code,'name'=>$name,'contract_kind'=>$kind,'content_html'=>$html,'status'=>$status,'variables'=>is_array($b['variables']??null)?$b['variables']:[]]);}
 public function createForCooperation(string $actor,string $id,array $cooperation,array $b):array
 {
  $pdo=$this->repo->pdo();$pdo->beginTransaction();
  try{
   $s=$pdo->prepare('SELECT * FROM cooperation_requests WHERE id=:id FOR UPDATE');$s->execute(['id'=>$id]);$cooperation=$s->fetch(\PDO::FETCH_ASSOC);
   if(!is_array($cooperation))throw new InvalidArgumentException('درخواست همکاری پیدا نشد.');
   $data=\Babanoell\Partners\Support\Json::decode($cooperation['data_json']);$kind='cooperation_agreement';$mode=$this->mode($b['mode']??'digital');$tpl=$this->template((string)($b['template_code']??'cooperation_default'),$kind);
   $ctx=['request_no'=>(string)$cooperation['request_no'],'signer_name'=>(string)($data['full_name']??$data['company_name']??$data['legal_name']??'متقاضی'),'signer_mobile'=>(string)($data['mobile']??$data['phone']??'')];
   $hash=\Babanoell\Partners\Domain\CooperationFormDefinition::fingerprint($cooperation['request_type'],$data);
   $contract=$this->create($actor,$kind,'cooperation_request',$id,(string)($cooperation['applicant_user_id']??''),$ctx['signer_mobile'],$mode,$tpl,$ctx,$hash);
   $pdo->commit();return $contract;
  }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
 public function createForLoan(string $actor,string $id,string $kind,array $b,string $bearer):array{if(!in_array($kind,['loan_application','facility_agreement'],true))throw new InvalidArgumentException('نوع قرارداد تسهیلات معتبر نیست.');$r=$this->loans->contractContext($id,$bearer);if(!$r->ok())throw new RuntimeException('اطلاعات قرارداد پرونده از سرویس تسهیلات دریافت نشد.');$ctx=is_array($r->json['data']['contract_context']??null)?$r->json['data']['contract_context']:[];$policy=is_array($ctx['contract_policy']??null)?$ctx['contract_policy']:[];$enabledKey=$kind==='loan_application'?'loan_application_enabled':'facility_agreement_enabled';if(array_key_exists($enabledKey,$policy)&&empty($policy[$enabledKey]))throw new InvalidArgumentException('این قرارداد برای نسخه طرح پرونده فعال نیست.');$mode=$this->mode($policy['mode']??($b['mode']??'physical'));$templateDefault=$kind==='loan_application'?'loan_application_default':'facility_agreement_default';$templateCode=(string)($policy[$kind.'_template_code']??$b['template_code']??$templateDefault);$tpl=$this->template($templateCode,$kind);return $this->create($actor,$kind,'loan_request',$id,(string)($ctx['applicant_user_id']??''),(string)($ctx['mobile']??''),$mode,$tpl,$ctx);}
 public function publicView(string $token,?string $ip,?string $ua):array{$c=$this->repo->byPublicToken($token);if($c===null)throw new InvalidArgumentException('لینک قرارداد معتبر نیست یا منقضی شده است.');$this->repo->markViewed((string)$c['id'],$ip,$ua);return $this->repo->contract((string)$c['id'])??$c;}
 public function requestOtp(string $token,?string $ip,?string $ua):array {
  $c=$this->repo->byPublicToken($token);if($c===null)throw new InvalidArgumentException('لینک قرارداد معتبر نیست یا منقضی شده است.');
  if((string)$c['mode']!=='digital')throw new InvalidArgumentException('این قرارداد برای امضای دیجیتال تنظیم نشده است.');
  $otp=(string)random_int(100000,999999);$id=(string)$c['id'];
  // Reserve an OTP first, so a second parallel call is blocked by the database cooldown.
  $challenge=$this->repo->issueOtp($id,password_hash($otp,PASSWORD_DEFAULT),300,60,$ip,$ua);
  if(!$this->production)return ['queued'=>true,'delivery'=>'test_only','expires_in'=>300,'test_otp'=>$otp];
  $mobile=trim((string)($c['signer_mobile']??''));
  try {
   if($this->integrations===null||!$this->integrations->sendContractOtp($mobile,$otp,$id))throw new RuntimeException('ارسال پیامک تأیید نشد.');
  }catch(\Throwable $e){
   $this->repo->invalidateOtp((string)($challenge['id']??''),$id,$ip,$ua);
   throw new RuntimeException('ارسال کد تأیید انجام نشد. تنظیمات پیامک را بررسی کنید.');
  }
  return ['queued'=>true,'delivery'=>'provider_accepted','expires_in'=>300,'test_otp'=>null];
 }
 public function verifyOtp(string $token,string $otp,?string $ip,?string $ua):array{$c=$this->repo->byPublicToken($token);if($c===null)throw new InvalidArgumentException('لینک قرارداد معتبر نیست یا منقضی شده است.');$otp=$this->normalizeDigits(trim($otp));if(!preg_match('/^\d{6}$/',$otp))throw new InvalidArgumentException('رمز تأیید باید شش رقم باشد.');$signed=$this->repo->verifyOtpAndSign((string)$c['id'],$otp,$ip,$ua);try{$this->storeArtifact((string)$c['id']);}catch(\Throwable $e){error_log('[partners] signed contract artifact pending: '.$e->getMessage());}return $this->repo->contract((string)$c['id'])??$signed;}
 public function markPhysical(string $id,string $actor,?string $ip,?string $ua):array{$c=$this->repo->markPhysicalSigned($id,$actor,$ip,$ua);try{$this->storeArtifact($id);}catch(\Throwable $e){error_log('[partners] physical contract artifact pending: '.$e->getMessage());}return $this->repo->contract($id)??$c;}
 public function issueLink(string $id,string $actor):array{$c=$this->repo->contract($id);if($c===null)throw new RuntimeException('قرارداد پیدا نشد.');$siblings=$this->repo->bySubject((string)$c['subject_type'],(string)$c['subject_id']);foreach($siblings as $other){if((string)$other['contract_kind']===(string)$c['contract_kind']&&(int)($other['revision']??1)>(int)($c['revision']??1))throw new RuntimeException('صدور لینک جدید برای نسخه قدیمی مجاز نیست.');}return $this->repo->issuePublicToken($id,$actor);}
 public function retryArtifact(string $id):array{$this->storeArtifact($id);return $this->repo->contract($id)??[];}
 private function storeArtifact(string $id):void{$h=$this->repo->createArtifactHandoff($id);$r=$this->documents->storeContractArtifact($id,(string)$h['token'],(string)$h['sha256'],(string)$h['content'],(string)$h['filename'],(string)$h['mime_type']);if($r->ok()){ $doc=(string)($r->json['data']['document']['id']??'');if($doc!=='')$this->repo->markArtifact($id,$doc,(string)$h['sha256']);}}
 private function create(string $actor,string $kind,string $subjectType,string $subjectId,string $signer,string $mobile,string $mode,array $tpl,array $ctx,?string $subjectHash=null):array{foreach($this->repo->bySubject($subjectType,$subjectId) as $existing){if((string)($existing['contract_kind']??'')===$kind)return $existing;}$render=$this->render((string)$tpl['content_html'],$ctx);return $this->repo->createContract($actor,['contract_kind'=>$kind,'subject_type'=>$subjectType,'subject_id'=>$subjectId,'signer_user_id'=>$signer,'signer_mobile'=>$mobile,'mode'=>$mode,'status'=>'pending','template_id'=>(string)$tpl['id'],'template_code'=>(string)$tpl['code'],'template_version'=>(int)$tpl['version'],'template_hash'=>(string)$tpl['content_hash'],'rendered_content'=>$render,'subject_snapshot_hash'=>$subjectHash]);}
 private function template(string $code,string $kind):array{$tpl=$this->repo->activeTemplate($code);if(!is_array($tpl)||(string)$tpl['contract_kind']!==$kind)throw new InvalidArgumentException('قالب فعال قرارداد پیدا نشد.');return $tpl;}
 private function render(string $html,array $ctx):string{foreach($ctx as$k=>$v){if(is_scalar($v)||$v===null)$html=str_replace('{{'.$k.'}}',htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'),$html);}return $html;}
 private function mode(mixed $v):string{$m=strtolower(trim((string)$v));if(!in_array($m,['physical','digital'],true))throw new InvalidArgumentException('نوع قرارداد باید فیزیکی یا دیجیتال باشد.');return$m;}
 private function normalizeDigits(string$s):string{return strtr($s,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);}
}
