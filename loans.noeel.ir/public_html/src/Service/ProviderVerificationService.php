<?php
declare(strict_types=1);
namespace Babanoell\Loans\Service;
use Babanoell\Loans\Http\IntegrationsClient;
use Babanoell\Loans\Repository\PdoLoanRepository;
use InvalidArgumentException;
use RuntimeException;

/** Manual, permission-gated dispatch; a provider request is never retried automatically after uncertainty. */
final class ProviderVerificationService {
 public function __construct(private readonly PdoLoanRepository $repo,private readonly BankCreditService $bank,private readonly IntegrationsClient $client){}
 private function existing(string $requestId,string $verificationId,string $type):array {
  $v=$this->repo->getVerificationRequest($verificationId);
  if(!is_array($v)||(string)$v['request_id']!==$requestId||(string)$v['verification_type']!==$type)throw new InvalidArgumentException('درخواست اعتبارسنجی این پرونده پیدا نشد.');
  if(!in_array((string)$v['provider'],[$type==='identity_match'?'shahkar':'ics24'],true))throw new InvalidArgumentException('ارائه‌دهنده این درخواست توسط اتصال فعلی پشتیبانی نمی‌شود.');
  return $v;
 }
 private function requireProviderResponse(\Babanoell\Loans\Http\HttpResponse $r):array {
  if(!$r->ok() || !is_array($r->json['data']??null))throw new RuntimeException('integrations_unavailable');
  return $r->json['data'];
 }
 private static function idem(string $verificationId,string $suffix):string{return hash('sha256','noel-verification|'.$verificationId.'|'.$suffix);}
 private static function digest(array $data):string{return hash('sha256',json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));}
 public function dispatch(string $requestId,string $vid,string $actor):array {
  $v=$this->repo->getVerificationRequest($vid);
  if(!is_array($v)||(string)$v['request_id']!==$requestId)throw new InvalidArgumentException('درخواست اعتبارسنجی معتبر نیست.');
  $type=(string)$v['verification_type'];if(!in_array($type,['identity_match','credit_report'],true))throw new InvalidArgumentException('نوع اعتبارسنجی پشتیبانی نمی‌شود.');
  $v=$this->existing($requestId,$vid,$type);
  if($v['status']!=='queued')throw new RuntimeException('verification_already_dispatched');
  // Verification deadlines are UTC, including when the web runtime uses Tehran time.
  if(!empty($v['expires_at'])&&strtotime((string)$v['expires_at'].' UTC')<time())throw new RuntimeException('verification_expired');
  $national=(string)($v['national_code']??'');$mobile=(string)($v['mobile_number']??'');
  if(!preg_match('/^[0-9]{10}$/D',$national)||!preg_match('/^09[0-9]{9}$/D',$mobile))throw new InvalidArgumentException('کد ملی یا موبایل پرونده معتبر نیست.');
  // Durable claim happens BEFORE network call: unknown provider results must not trigger an automatic duplicate request.
  $this->repo->claimProviderVerification($vid,$actor);
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
  $v=$this->existing($requestId,$vid,'credit_report');
  if((string)$v['status']!=='pending'||!preg_match('/^[A-Za-z0-9_-]{6,180}$/D',(string)($v['provider_reference']??'')))throw new RuntimeException('credit_otp_not_ready');
  if(!preg_match('/^[0-9]{4,8}$/D',$otp))throw new InvalidArgumentException('کد تأیید معتبر نیست.');
  if(!preg_match('/^[A-Za-z0-9_-]{16,120}$/D',$key))throw new InvalidArgumentException('کلید عملیات معتبر نیست.');
  $r=$this->client->creditVerify((string)$v['provider_reference'],$otp,$key);
  $data=$this->requireProviderResponse($r);if(($data['accepted']??false)!==true)throw new RuntimeException('credit_otp_not_accepted');
  // OTP acceptance DOES NOT verify a credit score or approve a loan.
  return ['verification'=>$this->repo->noteProviderVerification($vid,$actor,['stage'=>'otp_accepted','otp_verified'=>true]),'credit_status'=>'pending'];
 }
 public function refresh(string $requestId,string $vid,string $actor):array {
  $v=$this->existing($requestId,$vid,'credit_report');
  if((string)$v['status']!=='pending'||!preg_match('/^[A-Za-z0-9_-]{6,180}$/D',(string)($v['provider_reference']??'')))throw new RuntimeException('credit_status_not_ready');
  $r=$this->client->creditStatus((string)$v['provider_reference']);$data=$this->requireProviderResponse($r);
  $safe=is_scalar($data['status']??null)?substr((string)$data['status'],0,64):'provider_response_received';
  return ['verification'=>$this->repo->noteProviderVerification($vid,$actor,['stage'=>'status_checked','provider_status'=>$safe,'status_checked_at'=>gmdate('c')]),'credit_status'=>'pending'];
 }
}
