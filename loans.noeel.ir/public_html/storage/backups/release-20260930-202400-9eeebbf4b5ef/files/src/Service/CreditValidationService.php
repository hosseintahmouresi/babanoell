<?php
declare(strict_types=1);
namespace Babanoell\Loans\Service;

use Babanoell\Loans\Domain\CreditValidationPolicy;
use Babanoell\Loans\Repository\PdoLoanRepository;
use InvalidArgumentException;

final class CreditValidationService
{
    private static function truncate(string $value, int $max): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }

    public function __construct(private readonly PdoLoanRepository $repo){}

    /** @return array<string,mixed> */
    public function snapshot(string $requestId): array
    {
        $request=$this->repo->getRequest($requestId);
        if($request===null) throw new InvalidArgumentException('پرونده پیدا نشد.');
        return [
            'validation'=>CreditValidationPolicy::summary($request),
            'latest_check'=>$this->repo->latestCreditCheck($requestId),
        ];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function record(string $requestId,string $actor,array $input): array
    {
        $request=$this->repo->getRequest($requestId);
        if($request===null) throw new InvalidArgumentException('پرونده پیدا نشد.');

        $provider=strtolower(trim((string)($input['provider']??'manual')));
        if($provider===''||preg_match('/^[a-z0-9][a-z0-9_.-]{1,63}$/',$provider)!==1) throw new InvalidArgumentException('نام سرویس اعتبارسنجی معتبر نیست.');

        $status=strtolower(trim((string)($input['status']??'')));
        if(!in_array($status,['pending','verified','failed','expired'],true)) throw new InvalidArgumentException('وضعیت اعتبارسنجی معتبر نیست.');

        $reference=self::truncate(trim((string)($input['external_report_id']??$input['reference']??'')),255);
        $score=null;
        if(isset($input['score'])&&$input['score']!==''){
            if(!is_numeric((string)$input['score'])) throw new InvalidArgumentException('امتیاز اعتبارسنجی معتبر نیست.');
            $score=(int)$input['score'];
        }
        $risk=self::truncate(trim((string)($input['risk']??'')),64);
        $summary=is_array($input['summary']??null)?$input['summary']:[];
        $responseHash=strtolower(trim((string)($input['response_hash']??'')));
        if($responseHash!==''&&preg_match('/^[a-f0-9]{64}$/',$responseHash)!==1) throw new InvalidArgumentException('هش پاسخ اعتبارسنجی معتبر نیست.');
        if($responseHash==='')$responseHash=hash('sha256',json_encode([
            'provider'=>$provider,'status'=>$status,'reference'=>$reference,'score'=>$score,'risk'=>$risk,'summary'=>$summary
        ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION));

        $check=$this->repo->recordCreditCheck($requestId,$actor,$provider,$status,$reference,$score,$risk,$summary,$responseHash);
        $fresh=$this->repo->getRequest($requestId)??$request;
        return ['validation'=>CreditValidationPolicy::summary($fresh),'check'=>$check];
    }
}
