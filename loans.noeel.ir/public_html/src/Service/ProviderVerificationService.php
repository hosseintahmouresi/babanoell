<?php
declare(strict_types=1);
namespace Babanoell\Loans\Service;
use Babanoell\Loans\Http\IntegrationsClient;
use Babanoell\Loans\Repository\PdoLoanRepository;
use InvalidArgumentException;
use RuntimeException;

/** Manual, permission-gated dispatch; a provider request is never retried automatically after uncertainty. */
final class ProviderVerificationService {
 public function __construct(private readonly PdoLoanRepository $repo,private readonly BankCreditService $bank,private readonly IntegrationsClient $client,private readonly string $proofSecret=''){}
 private function existing(string $requestId,string $verificationId,string $type):array {
  $v=$this->repo->getVerificationRequest($verificationId);
  if(!is_array($v)||(string)$v['request_id']!==$requestId||(string)$v['verification_type']!==$type)throw new InvalidArgumentException('درخواست اعتبارسنجی این پرونده پیدا نشد.');
  if(!in_array((string)$v['provider'],[$type==='identity_match'?'shahkar':'ics24'],true))throw new InvalidArgumentException('ارائه‌دهنده این درخواست توسط اتصال فعلی پشتیبانی نمی‌شود.');
  if(in_array($v['status'],['queued','pending'],true))$this->repo->assertCurrentVerification($v);
  $fingerprint=hash('sha256',\Babanoell\Loans\Domain\InputValidator::normalizeDigits((string)($v['national_code']??'')).'|'.\Babanoell\Loans\Domain\InputValidator::normalizeDigits((string)($v['mobile_number']??'')));
  if(!hash_equals((string)$v['subject_fingerprint'],$fingerprint))throw new RuntimeException('verification_subject_changed');
  if(in_array($v['status'],['queued','pending'],true)&&!empty($v['expires_at'])&&strtotime((string)$v['expires_at'].' UTC')<=time())throw new RuntimeException('verification_expired');
  return $v;
 }
 private function requireProviderResponse(\Babanoell\Loans\Http\HttpResponse $r):array {
  if(!$r->ok() || !is_array($r->json['data']??null))throw new RuntimeException('integrations_unavailable');
  return $r->json['data'];
 }
 private static function idem(string $verificationId,string $suffix):string{return hash('sha256','noel-verification|'.$verificationId.'|'.$suffix);}
 private static function digest(array $data):string{return hash('sha256',json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));}
 public function dispatch(string $requestId,string $vid,string $actor,bool $recover=false):array {
  $v=$this->repo->getVerificationRequest($vid);
  if(!is_array($v)||(string)$v['request_id']!==$requestId)throw new InvalidArgumentException('درخواست اعتبارسنجی معتبر نیست.');
  $type=(string)$v['verification_type'];if(!in_array($type,['identity_match','credit_report'],true))throw new InvalidArgumentException('نوع اعتبارسنجی پشتیبانی نمی‌شود.');
  $v=$this->existing($requestId,$vid,$type);
  if(!$recover&&$v['status']!=='queued')throw new RuntimeException('verification_already_dispatched');
  if($recover&&($v['status']!=='pending'||!empty($v['provider_reference'])))throw new RuntimeException('verification_recovery_not_needed');
  // Verification deadlines are UTC, including when the web runtime uses Tehran time.
  if(!empty($v['expires_at'])&&strtotime((string)$v['expires_at'].' UTC')<time())throw new RuntimeException('verification_expired');
  $national=(string)($v['national_code']??'');$mobile=(string)($v['mobile_number']??'');
  if(!preg_match('/^[0-9]{10}$/D',$national)||!preg_match('/^09[0-9]{9}$/D',$mobile))throw new InvalidArgumentException('کد ملی یا موبایل پرونده معتبر نیست.');
  // Durable claim happens BEFORE network call: unknown provider results must not trigger an automatic duplicate request.
  if(!$recover)$this->repo->claimProviderVerification($vid,$actor);
  // Recovery uses the ORIGINAL key: Integrations replays a completed result, refuses
  // uncertain/pending work, or sends the first request if the original never arrived.
  $key=self::idem($vid,'dispatch');
  $r=$type==='identity_match'?$this->client->shahkar($national,$mobile,$key):$this->client->creditStart($national,$mobile,$key);
  try {$data=$this->requireProviderResponse($r);}catch(RuntimeException $e){$this->repo->noteProviderVerification($vid,$actor,['delivery'=>'unconfirmed','http_status'=>$r->status,'code'=>$r->errorCode()??$r->networkError??'unknown']);throw $e;}
  if($type==='identity_match'){
   if(!is_bool($data['matched']??null))throw new RuntimeException('integrations_response_invalid');
   $payload=['status'=>$data['matched']?'verified':'failed','provider_reference'=>(string)($data['tracking_id']??''),'response_hash'=>self::digest(['matched'=>$data['matched'],'tracking_id'=>$data['tracking_id']??null]),'metadata'=>['source'=>'integrations','matched'=>$data['matched'],'provider'=>'shahkar']];
   return ['verification'=>$this->bank->complete($vid,'service:integrations',$payload),'step'=>'completed'];
  }
  $reference=(string)($data['reference']??'');if(!preg_match('/^[A-Za-z0-9_-]{6,180}$/D',$reference))throw new RuntimeException('integrations_response_invalid');
  return ['verification'=>$this->repo->noteProviderVerification($vid,$actor,['delivery'=>'accepted','stage'=>'otp_requested'], $reference),'step'=>'awaiting_otp'];
 }
 public function verifyOtp(string $requestId,string $vid,string $actor,string $otp,string $key):array {
  $otp=\Babanoell\Loans\Domain\InputValidator::normalizeDigits(trim($otp));
  $v=$this->existing($requestId,$vid,'credit_report');
  if((string)$v['status']!=='pending'||!preg_match('/^[A-Za-z0-9_-]{6,180}$/D',(string)($v['provider_reference']??'')))throw new RuntimeException('credit_otp_not_ready');
  if(!preg_match('/^[0-9]{4,8}$/D',$otp))throw new InvalidArgumentException('کد تأیید معتبر نیست.');
  if(!preg_match('/^[A-Za-z0-9_-]{16,120}$/D',$key))throw new InvalidArgumentException('کلید عملیات معتبر نیست.');
  $r=$this->client->creditVerify((string)$v['provider_reference'],$otp,$key);
  if(!$r->ok()&&$r->errorCode()==='credit_provider_rejected')throw new InvalidArgumentException('کد تأیید صحیح نیست یا مهلت آن پایان یافته است.');
  $data=$this->requireProviderResponse($r);if(($data['accepted']??false)!==true)throw new RuntimeException('credit_otp_not_accepted');
  // OTP acceptance DOES NOT verify a credit score or approve a loan.
  return ['verification'=>$this->repo->noteProviderVerification($vid,$actor,['stage'=>'otp_accepted','otp_verified'=>true]),'credit_status'=>'pending'];
 }
 public function refresh(string $requestId,string $vid,string $actor):array {
  $v=$this->existing($requestId,$vid,'credit_report');
  if($v['status']==='verified'&&$this->repo->creditReportEvidence($vid)!==null)return ['verification'=>$v,'credit_status'=>'verified','step'=>'completed'];
  if((string)$v['status']!=='pending'||!preg_match('/^[A-Za-z0-9_-]{6,180}$/D',(string)($v['provider_reference']??'')))throw new RuntimeException('credit_status_not_ready');
  if(($v['metadata']['otp_verified']??false)!==true)throw new RuntimeException('credit_otp_required');
  $context=['verification_id'=>$vid,'request_id'=>$requestId,'request_hash'=>(string)$v['request_hash'],
    'subject_fingerprint'=>(string)$v['subject_fingerprint'],'reference'=>(string)$v['provider_reference'],
    'national_code'=>(string)$v['national_code'],'mobile'=>(string)$v['mobile_number']];
  $r=$this->client->creditFinalize($context);$data=$this->requireProviderResponse($r);
  if(($data['ready']??false)===true){
   try{$completed=$this->bank->completeProviderReport($vid,$data,$this->proofSecret);}
   catch(InvalidArgumentException){throw new RuntimeException('integrations_response_invalid');}
   return ['verification'=>$completed,'credit_status'=>'verified','step'=>'completed'];
  }
  $safe=is_string($data['provider_status']??null)?substr($data['provider_status'],0,64):'InProcessing';
  $details=['stage'=>($data['regenerated']??false)===true?'report_regenerating':'report_pending','provider_status'=>$safe,'status_checked_at'=>gmdate('c')];
  return ['verification'=>$this->repo->noteProviderVerification($vid,$actor,$details),'credit_status'=>'pending'];
 }
}
