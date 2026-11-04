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
        return is_bool($value)?$value:in_array(strtolower(trim((string)$value)),['1','true','yes','on'],true);
    }

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
        if(self::protects($target)&&!self::verified($request)){
            throw new InvalidArgumentException('این طرح نیازمند اعتبارسنجی بانکی معتبر است. ابتدا نتیجه اعتبارسنجی پرونده ثبت و تأیید شود.');
        }
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
        ];
    }
}
