<?php
declare(strict_types=1);
namespace Babanoell\Loans\Domain;

use InvalidArgumentException;

final class CreditValidationPolicy
{
    /** @var list<string> */
    private const PROTECTED_TARGETS=['expert_approved','docs_completion','docs_verified','granted'];

    public static function required(array $request): bool
    {
        $rules=is_array($request['plan_rules']??null)?$request['plan_rules']:[];
        $value=$rules['requires_bank_validation']??false;
        return self::toBool($value);
    }

    public static function identityRequired(array $request): bool
    {
        $rules=is_array($request['plan_rules']??null)?$request['plan_rules']:[];
        return self::toBool($rules['requires_identity_verification']??false);
    }

    public static function identityStatus(array $request): string
    {
        if(!self::identityRequired($request))return 'not_required';
        $status=strtolower(trim((string)($request['identity_verification_status']??'')));
        return in_array($status,['queued','pending','verified','failed','expired','cancelled'],true)?$status:'pending';
    }

    public static function identityVerified(array $request): bool
    { return !self::identityRequired($request)||self::identityStatus($request)==='verified'; }

    public static function effectiveStatus(array $request): string
    {
        if(!self::required($request)) return 'not_required';
        $status=strtolower(trim((string)($request['credit_validation_status']??'')));
        return in_array($status,['pending','verified','failed','expired'],true)?$status:'pending';
    }

    public static function verified(array $request): bool
    {
        return !self::required($request)||self::effectiveStatus($request)==='verified';
    }

    public static function protects(string $target): bool
    {
        return in_array($target,self::PROTECTED_TARGETS,true);
    }

    public static function assertForTarget(array $request,string $target): void
    {
        if(self::protects($target)&&!self::identityVerified($request))throw new InvalidArgumentException('این طرح نیازمند تطبیق هویت معتبر است. ابتدا نتیجه احراز هویت پرونده تأیید شود.');
        if(self::protects($target)&&!self::verified($request))throw new InvalidArgumentException('این طرح نیازمند اعتبارسنجی بانکی معتبر است. ابتدا نتیجه اعتبارسنجی پرونده ثبت و تأیید شود.');
    }

    /** @return array<string,mixed> */
    public static function summary(array $request): array
    {
        return [
            'required'=>self::required($request),
            'status'=>self::effectiveStatus($request),
            'verified'=>self::verified($request),
            'provider'=>isset($request['credit_validation_provider'])?(string)$request['credit_validation_provider']:null,
            'reference'=>isset($request['credit_validation_reference'])?(string)$request['credit_validation_reference']:null,
            'verified_at'=>isset($request['credit_validation_verified_at'])?(string)$request['credit_validation_verified_at']:null,
            'check_id'=>isset($request['credit_validation_check_id'])?(string)$request['credit_validation_check_id']:null,
            'identity'=>[
                'required'=>self::identityRequired($request),
                'status'=>self::identityStatus($request),
                'verified'=>self::identityVerified($request),
                'provider'=>isset($request['identity_verification_provider'])?(string)$request['identity_verification_provider']:null,
                'reference'=>isset($request['identity_verification_reference'])?(string)$request['identity_verification_reference']:null,
                'verified_at'=>isset($request['identity_verification_verified_at'])?(string)$request['identity_verification_verified_at']:null,
                'request_id'=>isset($request['identity_verification_request_id'])?(string)$request['identity_verification_request_id']:null,
            ],
        ];
    }

    private static function toBool(mixed $value): bool
    { return is_bool($value)?$value:in_array(strtolower(trim((string)$value)),['1','true','yes','on'],true); }
}
