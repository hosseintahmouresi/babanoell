<?php
declare(strict_types=1);
namespace Babanoell\Loans\Service;

use Babanoell\Loans\Domain\BankCreditCatalog;
use Babanoell\Loans\Domain\CreditValidationPolicy;
use Babanoell\Loans\Repository\PdoLoanRepository;
use InvalidArgumentException;

final class BankCreditService
{
    public function __construct(private readonly PdoLoanRepository $repo){}

    /** @return array<string,mixed> */
    public function snapshot(string $requestId): array
    {
        $request=$this->repo->getRequest($requestId);if($request===null)throw new InvalidArgumentException('پرونده پیدا نشد.');
        return ['validation'=>CreditValidationPolicy::summary($request),'latest_credit_check'=>$this->repo->latestCreditCheck($requestId),'verification_requests'=>$this->repo->verificationRequests($requestId),'submission'=>$this->repo->creditSubmissionState($request)];
    }

    /** @return array<string,mixed> */
    public function queue(string $requestId,string $actor,string $type,string $provider,string $idempotencyKey): array
    {
        $request=$this->repo->getRequest($requestId);if($request===null)throw new InvalidArgumentException('پرونده پیدا نشد.');
        $type=BankCreditCatalog::type($type);$provider=BankCreditCatalog::provider($provider,$type);$idempotencyKey=trim($idempotencyKey);if($idempotencyKey==='')throw new InvalidArgumentException('Idempotency-Key برای درخواست اعتبارسنجی الزامی است.');
        $national=self::digits((string)($request['national_code']??''));$mobile=self::digits((string)($request['mobile_number']??''));
        if($type==='identity_match'&&($national===''||$mobile===''))throw new InvalidArgumentException('برای احراز هویت، کد ملی و شماره موبایل پرونده الزامی است.');
        if($type==='credit_report'&&$national==='')throw new InvalidArgumentException('برای اعتبارسنجی اعتباری، کد ملی پرونده الزامی است.');
        $fingerprint=hash('sha256',$national.'|'.$mobile);$requestHash=hash('sha256',$requestId.'|'.$type.'|'.$provider.'|'.$fingerprint);
        return $this->repo->createVerificationRequest($requestId,$type,$provider,substr($idempotencyKey,0,128),$fingerprint,$requestHash,$actor,120);
    }

    /** @return array<string,mixed> */
    public function complete(string $verificationId,string $actor,array $payload): array
    {
        $verification=$this->repo->getVerificationRequest($verificationId);if($verification===null)throw new InvalidArgumentException('درخواست اعتبارسنجی پیدا نشد.');
        // Repository deadlines are written with gmdate, independently of PHP's display timezone.
        $status=BankCreditCatalog::status((string)($payload['status']??''));if(in_array($status,['queued','pending'],true))throw new InvalidArgumentException('برای نتیجه نهایی، وضعیت باید verified، failed، expired یا cancelled باشد.');if($status==='verified'&&!empty($verification['expires_at'])&&strtotime((string)$verification['expires_at'].' UTC')!==false&&strtotime((string)$verification['expires_at'].' UTC')<time())throw new InvalidArgumentException('مهلت این درخواست اعتبارسنجی به پایان رسیده است.');
        $reference=self::cut(trim((string)($payload['provider_reference']??$payload['reference']??'')),255);$score=null;if(isset($payload['score'])&&$payload['score']!==''){if(!is_numeric((string)$payload['score']))throw new InvalidArgumentException('امتیاز اعتبارسنجی معتبر نیست.');$score=(int)$payload['score'];}
        $risk=self::cut(trim((string)($payload['risk']??'')),64);$metadata=is_array($payload['metadata']??null)?$payload['metadata']:[];$responseHash=strtolower(trim((string)($payload['response_hash']??'')));
        if($responseHash!==''&&preg_match('/^[a-f0-9]{64}$/',$responseHash)!==1)throw new InvalidArgumentException('هش پاسخ اعتبارسنجی معتبر نیست.');
        if($responseHash==='')$responseHash=hash('sha256',json_encode(['verification_id'=>$verificationId,'status'=>$status,'reference'=>$reference,'score'=>$score,'risk'=>$risk,'metadata'=>$metadata],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION));
        return$this->repo->completeVerificationRequest($verificationId,$actor,$status,$reference,$responseHash,$score,$risk,$metadata);
    }

    /** @return array<string,mixed> */
    public function integrationContext(string $verificationId): array
    {
        $v=$this->repo->getVerificationRequest($verificationId);if($v===null)throw new InvalidArgumentException('درخواست اعتبارسنجی پیدا نشد.');
        return ['verification_id'=>$verificationId,'request_id'=>(string)$v['request_id'],'verification_type'=>(string)$v['verification_type'],'provider'=>(string)$v['provider'],'request_hash'=>(string)$v['request_hash'],'subject'=>['national_code'=>(string)($v['national_code']??''),'mobile_number'=>(string)($v['mobile_number']??''),'full_name'=>(string)($v['full_name']??'')]];
    }

    public function completeProviderReport(string $verificationId,array $payload,string $secret): array
    {
        $verification=$this->repo->getVerificationRequest($verificationId);
        if($verification===null||$verification['provider']!=='ics24'||$verification['verification_type']!=='credit_report')throw new InvalidArgumentException('credit_report_verification_invalid');
        if(!is_array($payload['report']??null)||!is_array($payload['proof']??null))throw new InvalidArgumentException('credit_report_proof_required');
        if(($verification['metadata']['otp_verified']??false)!==true)throw new InvalidArgumentException('credit_otp_required');
        if(!empty($verification['expires_at'])&&strtotime((string)$verification['expires_at'].' UTC')<=time())throw new InvalidArgumentException('credit_verification_expired');
        $expected=['verification_id'=>$verificationId,'request_id'=>(string)$verification['request_id'],
            'request_hash'=>(string)$verification['request_hash'],'subject_fingerprint'=>(string)$verification['subject_fingerprint'],
            'reference'=>(string)$verification['provider_reference']];
        $claims=\Babanoell\Loans\Platform\CreditReportProof::verify($payload['proof'],$payload['report'],$expected,$secret);
        \Babanoell\Loans\Platform\CreditReportProof::assertSubject($payload['report'],self::digits((string)$verification['national_code']));
        $summary=\Babanoell\Loans\Platform\CreditReportProof::summary($payload['report']);
        $metadata=['source'=>'integrations','stage'=>'report_verified','otp_verified'=>true,'report_code'=>$claims['report_code'],
            'report_sha256'=>$claims['report_sha256'],'evidence_id'=>$verificationId];
        return $this->repo->completeVerificationRequest($verificationId,'service:integrations','verified',
            $expected['reference'],$claims['report_sha256'],$summary['score'],self::cut($summary['risk'],64),$metadata,$payload);
    }

    public function assertBankApprovalAllowed(string $requestId): void
    { $r=$this->repo->getRequest($requestId);if($r===null)throw new InvalidArgumentException('پرونده پیدا نشد.');CreditValidationPolicy::assertForTarget($r,'expert_approved'); }

    private static function digits(string $v):string{return strtr(trim($v),['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);}
    private static function cut(string $v,int $max):string{return function_exists('mb_substr')?mb_substr($v,0,$max,'UTF-8'):substr($v,0,$max);}
}
