<?php
declare(strict_types=1);
namespace Babanoell\Finance\Service;

use Babanoell\Finance\Http\LoansClient;
use Babanoell\Finance\Repository\BankCycleRepository;
use Babanoell\Finance\Domain\FinanceRules;
use Babanoell\Finance\Support\PersianDate;
use InvalidArgumentException;
use RuntimeException;

/** Immutable, bank-to-platform settlement batches. Merchant cycles are not used. */
final class BankCycleService {
    public function __construct(private readonly BankCycleRepository $repo, private readonly LoansClient $loans) {}

    public static function parseDate(string $date): string {
        $date=PersianDate::normalize($date);
        if(preg_match('#^\d{4}[/-]\d{1,2}[/-]\d{1,2}$#D',$date)&&str_starts_with($date,'14'))return PersianDate::jalaliToIso($date);
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date))throw new InvalidArgumentException('تاریخ باید به قالب YYYY-MM-DD باشد.');
        [$y,$m,$d]=array_map('intval',explode('-',$date));
        if(!checkdate($m,$d,$y))throw new InvalidArgumentException('تاریخ تسویه معتبر نیست.');
        return $date;
    }
    public static function normalizeRequestIds(mixed $ids): array {
        if(!is_array($ids)||count($ids)<1||count($ids)>100)throw new InvalidArgumentException('تعداد پرونده‌ها باید بین ۱ تا ۱۰۰ باشد.');
        $result=[];
        foreach($ids as $id){if(!is_string($id))throw new InvalidArgumentException('شناسه پرونده معتبر نیست.');$id=FinanceRules::id($id,'شناسه پرونده');$result[$id]=$id;}
        if(count($result)!==count($ids))throw new InvalidArgumentException('پرونده تکراری در فهرست وجود دارد.');
        $result=array_values($result);sort($result,SORT_STRING);return $result;
    }
    public static function amountCents(mixed $raw): int {
        $str=trim((string)$raw);
        if(!preg_match('/^(0|[1-9][0-9]{0,13})(?:\.([0-9]{1,2}))?$/D',$str,$matches))throw new InvalidArgumentException('مبلغ پرونده معتبر نیست.');
        $amount=((int)$matches[1])*100+(int)str_pad((string)($matches[2]??''),2,'0');
        if($amount<=0)throw new InvalidArgumentException('مبلغ پرونده باید بزرگ‌تر از صفر باشد.');
        return $amount;
    }
    public function create(string $actor,string $bearer,array $body,string $key): array {
        $actor=FinanceRules::id($actor,'شناسه کارشناس');
        if($key===''||strlen($key)>128)throw new InvalidArgumentException('کلید یکتای عملیات لازم است.');
        $ids=self::normalizeRequestIds($body['request_ids']??null);
        $date=self::parseDate((string)($body['settlement_date']??''));
        $notes=trim((string)($body['notes']??''));
        if(mb_strlen($notes)>3000)throw new InvalidArgumentException('توضیحات نباید بیش از ۳۰۰۰ نویسه باشد.');
        $fingerprint=hash('sha256',json_encode([$actor,$ids,$date,$notes],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        $prior=$this->repo->findByKey($actor,$key);
        if($prior!==null){if(!hash_equals((string)$prior['request_hash'],$fingerprint))throw new RuntimeException('idempotency_conflict');return $this->tryProjection($prior);}
        $items=[];
        foreach($ids as $id){
            $reply=$this->loans->request($id,$bearer);
            if(!$reply->ok())throw new RuntimeException('loan_case_unavailable_or_denied');
            $case=$reply->json['data']['request']??null;
            if(!is_array($case)||($case['id']??null)!==$id)throw new RuntimeException('loan_case_invalid');
            if(($case['status']??'')!=='granted'||($case['assigned_bank_user_id']??'')!==$actor)
                throw new InvalidArgumentException('تمام پرونده‌ها باید اعطاشده و متعلق به همان کارشناس بانک باشند.');
            if(($case['bank_settlement_status']??'not_recorded')==='recorded'||!empty($case['bank_settlement_cycle_id']))
                throw new InvalidArgumentException('حداقل یک پرونده قبلاً تسویه بانک شده است.');
            // The server-side loan amount, never the browser-supplied amount, is authoritative.
            $cents=self::amountCents($case['facility_amount']??$case['requested_amount']??'');
            $items[]=['request_id'=>$id,'loan_cents'=>$cents,'applicant_name'=>mb_substr((string)($case['full_name']??''),0,255),
                'national_code'=>mb_substr((string)($case['national_code']??''),0,20)];
        }
        $cycle=$this->repo->create($actor,$date,$notes,$items,$key,$fingerprint);
        return $this->tryProjection($cycle);
    }
    public function retry(string $id):array {$cycle=$this->repo->get($id);if($cycle===null)throw new InvalidArgumentException('سیکل بانک یافت نشد.');return $this->tryProjection($cycle);}
    private function tryProjection(array $cycle):array {
        if(($cycle['projection_status']??'')==='synced')return $cycle;
        $ids=array_column($cycle['items'],'request_id');
        $reply=$this->loans->syncBankCycleProjection((string)$cycle['id'],(string)$cycle['bank_user_id'],(string)$cycle['settlement_date'],(string)($cycle['notes']??''),$ids);
        $this->repo->recordProjectionAttempt((string)$cycle['id'],$reply->ok());
        return $this->repo->get((string)$cycle['id'])??$cycle;
    }
}
