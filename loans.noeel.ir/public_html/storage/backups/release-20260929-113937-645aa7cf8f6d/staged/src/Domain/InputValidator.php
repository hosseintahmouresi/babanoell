<?php
declare(strict_types=1);
namespace Babanoell\Loans\Domain;

use Babanoell\Loans\Support\PersianDate;
use InvalidArgumentException;

final class InputValidator
{
    public static function normalizeDigits(string $value): string
    {
        return strtr(trim($value), [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ]);
    }

    public static function money(mixed $value): float
    {
        if (is_int($value) || is_float($value)) return (float) $value;
        $v = str_replace([',','٬',' '], '', self::normalizeDigits((string)$value));
        return is_numeric($v) ? (float)$v : 0.0;
    }

    public static function decimal(mixed $value): float
    {
        if (is_int($value) || is_float($value)) return (float) $value;
        $v = str_replace(['٪','%','٫','،'], ['','','.', '.'], self::normalizeDigits((string)$value));
        $v = trim($v);
        return is_numeric($v) ? (float) $v : 0.0;
    }

    public static function integer(mixed $value): int
    {
        if (is_int($value)) return $value;
        $v = self::normalizeDigits((string) $value);
        return preg_match('/^-?\d+$/', $v) === 1 ? (int) $v : 0;
    }

    public static function validNationalCode(string $code): bool
    {
        $code = self::normalizeDigits($code);
        if (!preg_match('/^\d{10}$/', $code)) return false;
        // Reject obvious repeated digits even though some pass the checksum formula.
        if (preg_match('/^(\d)\1{9}$/', $code)) return false;
        $check = (int)$code[9];
        $sum = 0;
        for ($i=0;$i<9;$i++) $sum += (int)$code[$i] * (10-$i);
        $rem = $sum % 11;
        return ($rem < 2 && $check === $rem) || ($rem >= 2 && $check === 11-$rem);
    }

    public static function validMobile(string $mobile): bool
    {
        return preg_match('/^09\d{9}$/', self::normalizeDigits($mobile)) === 1;
    }

    /** @return array{items:array<int,array<string,mixed>>,total:float} */
    public static function cleanOrderItems(mixed $items): array
    {
        if (!is_array($items)) return ['items'=>[], 'total'=>0.0];
        $clean=[];$total=0.0;
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $qty = max(1, (int)($item['qty'] ?? $item['quantity'] ?? 1));
            $unit = self::money($item['price'] ?? $item['unit_price'] ?? 0);
            $rowTotal = array_key_exists('total_price',$item) ? self::money($item['total_price']) : $qty*$unit;
            if ($rowTotal <= 0) continue;
            $name = trim((string)($item['name'] ?? $item['description'] ?? ''));
            $clean[] = [
                'id'=>substr(trim((string)($item['id'] ?? '')),0,120),
                'name'=>substr($name,0,255),
                'code'=>substr(trim((string)($item['code'] ?? $item['id'] ?? '')),0,120),
                'description'=>substr(trim((string)($item['description'] ?? $name)),0,500),
                'qty'=>$qty,
                'quantity'=>$qty,
                'unit'=>substr(trim((string)($item['unit'] ?? '')),0,80),
                'price'=>$unit,
                'unit_price'=>$unit,
                'total_price'=>$rowTotal,
            ];
            $total += $rowTotal;
        }
        return ['items'=>$clean,'total'=>$total];
    }

    /** @return array<string,mixed> */
    public static function request(array $input, bool $submitted): array
    {
        $out=$input;
        foreach (['full_name','father_name','certificate_number','national_code','mobile_number','phone_number','occupation','residential_address','postal_code','work_address','product_name','merchant_ref','assigned_bank_user_id','acceptor_user_id','applicant_user_id'] as $key) {
            $out[$key] = trim((string)($input[$key] ?? ''));
        }
        foreach(['acceptor_user_id','applicant_user_id','assigned_bank_user_id'] as $idField){
            if($out[$idField]!==''&&preg_match('/^[a-f0-9]{26}$/',$out[$idField])!==1) throw new InvalidArgumentException('شناسه کاربری پرونده معتبر نیست.');
        }
        $out['national_code'] = self::normalizeDigits($out['national_code']);
        $out['mobile_number'] = self::normalizeDigits($out['mobile_number']);
        $out['postal_code'] = self::normalizeDigits($out['postal_code']);
        $out['requested_amount'] = self::money($input['requested_amount'] ?? $input['loan_amount'] ?? 0);
        $out['order_total'] = self::money($input['order_total'] ?? 0);
        $out['installment_count'] = self::integer($input['installment_count'] ?? 0);
        $out['installment_interval'] = max(1, self::integer($input['installment_interval'] ?? 1));
        $out['commission_override'] = isset($input['commission_override']) && trim((string)$input['commission_override']) !== '' ? self::decimal($input['commission_override']) : null;
        $cleanItems = self::cleanOrderItems($input['order_items'] ?? []);
        $out['order_items'] = $cleanItems['items'];
        if ($out['order_total'] <= 0) $out['order_total'] = $cleanItems['total'];
        if ($out['product_name'] === '' && $cleanItems['items'] !== []) {
            $out['product_name'] = implode('، ', array_values(array_filter(array_map(static fn(array $x): string => (string)($x['name']??''),$cleanItems['items']))));
        }
        $birth = trim((string)($input['birth_date'] ?? ''));
        $out['birth_date_jalali'] = $birth;
        $out['birth_date'] = $birth !== '' ? self::birthDateIso($birth) : null;
        $firstDue = trim((string)($input['first_due_date'] ?? ''));
        $out['first_due_date_jalali'] = $firstDue;
        $out['first_due_date'] = $firstDue !== '' ? PersianDate::jalaliToIso($firstDue) : null;

        if ($out['requested_amount'] <= 0) throw new InvalidArgumentException('مبلغ درخواستی باید بیشتر از صفر باشد.');
        if ($out['installment_count'] <= 0) throw new InvalidArgumentException('تعداد اقساط معتبر انتخاب کنید.');
        if ($submitted) {
            if ($out['full_name']==='') throw new InvalidArgumentException('نام و نام خانوادگی الزامی است.');
            if (!self::validNationalCode($out['national_code'])) throw new InvalidArgumentException('کد ملی واردشده معتبر نیست.');
            if (!self::validMobile($out['mobile_number'])) throw new InvalidArgumentException('شماره موبایل واردشده معتبر نیست.');
            if ($out['father_name']==='') throw new InvalidArgumentException('نام پدر الزامی است.');
            if ($out['certificate_number']==='') throw new InvalidArgumentException('شماره شناسنامه الزامی است.');
            if ($out['birth_date']===null) throw new InvalidArgumentException('تاریخ تولد کامل الزامی است.');
            if (!preg_match('/^\d{10}$/',$out['postal_code'])) throw new InvalidArgumentException('کد پستی ۱۰ رقمی معتبر وارد کنید.');
            if ($out['residential_address']==='' || $out['work_address']==='') throw new InvalidArgumentException('آدرس محل سکونت و آدرس محل فعالیت الزامی است.');
            if ($out['order_items']===[] || $cleanItems['total']<=0) throw new InvalidArgumentException('ثبت حداقل یک محصول معتبر الزامی است.');
            if (abs($cleanItems['total']-$out['requested_amount'])>1) throw new InvalidArgumentException('جمع مبلغ محصولات باید با مبلغ درخواستی برابر باشد.');
            if ($out['first_due_date']===null) throw new InvalidArgumentException('تاریخ اولین سررسید الزامی است.');
        }
        return $out;
    }
    /** @return array<string,mixed> */
    public static function draftFields(array $input): array
    {
        $allowed = ['full_name','father_name','certificate_number','national_code','mobile_number','phone_number','birth_date','occupation','residential_address','postal_code','work_address','product_name','merchant_ref','assigned_bank_user_id'];
        $out = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $input)) continue;
            $value = trim((string)$input[$key]);
            if ($key === 'national_code') {
                $value = self::normalizeDigits($value);
                if ($value !== '' && !self::validNationalCode($value)) throw new InvalidArgumentException('کد ملی واردشده معتبر نیست.');
            } elseif ($key === 'mobile_number') {
                $value = self::normalizeDigits($value);
                if ($value !== '' && !self::validMobile($value)) throw new InvalidArgumentException('شماره موبایل واردشده معتبر نیست.');
            } elseif ($key === 'postal_code') {
                $value = self::normalizeDigits($value);
                if ($value !== '' && preg_match('/^\d{10}$/', $value) !== 1) throw new InvalidArgumentException('کد پستی باید ۱۰ رقم باشد.');
            } elseif ($key === 'birth_date') {
                $value = $value !== '' ? self::birthDateIso($value) : '';
            }
            $out[$key] = $value;
        }
        return $out;
    }

    private static function birthDateIso(string $value): string
    {
        [$jy]=PersianDate::parseJalali($value);
        if($jy<1300||$jy>1500) throw new InvalidArgumentException('سال تولد واردشده معتبر نیست.');
        $iso=PersianDate::jalaliToIso($value);
        $today=gmdate('Y-m-d');
        if($iso<'1900-01-01'||$iso>$today) throw new InvalidArgumentException('تاریخ تولد واردشده معتبر نیست.');
        return $iso;
    }

    private static function truncate(string $value, int $max): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }

    /** @return array<int,array{id:string,full_name:string,national_code:string,mobile_number:string,relation_label:string}> */
    public static function guarantors(mixed $items): array
    {
        if (!is_array($items)) throw new InvalidArgumentException('فهرست ضامن‌ها معتبر نیست.');
        if (count($items) > 10) throw new InvalidArgumentException('حداکثر ۱۰ ضامن برای هر پرونده قابل ثبت است.');
        $out = []; $seenIds=[];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $id = strtolower(trim((string)($item['id'] ?? '')));
            if($id!==''&&preg_match('/^[a-f0-9]{26}$/',$id)!==1) throw new InvalidArgumentException('شناسه یکی از ضامن‌ها معتبر نیست.');
            if($id!==''&&isset($seenIds[$id])) throw new InvalidArgumentException('شناسه ضامن تکراری است.');
            if($id!=='')$seenIds[$id]=true;
            $name = trim((string)($item['full_name'] ?? ''));
            $national = self::normalizeDigits((string)($item['national_code'] ?? ''));
            $mobile = self::normalizeDigits((string)($item['mobile_number'] ?? ''));
            $relation = trim((string)($item['relation_label'] ?? ''));
            if ($name === '' && $national === '' && $mobile === '' && $relation === '') continue;
            if ($name === '') throw new InvalidArgumentException('نام و نام خانوادگی ضامن الزامی است.');
            if ($national !== '' && !self::validNationalCode($national)) throw new InvalidArgumentException('کد ملی یکی از ضامن‌ها معتبر نیست.');
            if ($mobile !== '' && !self::validMobile($mobile)) throw new InvalidArgumentException('شماره موبایل یکی از ضامن‌ها معتبر نیست.');
            $out[] = [
                'id' => $id,
                'full_name' => self::truncate($name, 190),
                'national_code' => $national,
                'mobile_number' => $mobile,
                'relation_label' => self::truncate($relation, 100),
            ];
        }
        return $out;
    }

    /** @param array<string,mixed> $request */
    public static function assertStoredRequestSubmittable(array $request): void
    {
        $fullName=trim((string)($request['full_name']??''));
        $father=trim((string)($request['father_name']??''));
        $certificate=trim((string)($request['certificate_number']??''));
        $national=self::normalizeDigits((string)($request['national_code']??''));
        $mobile=self::normalizeDigits((string)($request['mobile_number']??''));
        $postal=self::normalizeDigits((string)($request['postal_code']??''));
        $birth=trim((string)($request['birth_date']??''));
        $residential=trim((string)($request['residential_address']??''));
        $work=trim((string)($request['work_address']??''));
        $items=is_array($request['order_items']??null)?$request['order_items']:[];
        $requested=(float)($request['requested_amount']??0);
        $firstDue=trim((string)($request['first_due_date']??''));
        if($fullName==='')throw new InvalidArgumentException('نام و نام خانوادگی الزامی است.');
        if(!self::validNationalCode($national))throw new InvalidArgumentException('کد ملی واردشده معتبر نیست.');
        if(!self::validMobile($mobile))throw new InvalidArgumentException('شماره موبایل واردشده معتبر نیست.');
        if($father==='')throw new InvalidArgumentException('نام پدر الزامی است.');
        if($certificate==='')throw new InvalidArgumentException('شماره شناسنامه الزامی است.');
        if($birth===''||preg_match('/^\d{4}-\d{2}-\d{2}$/',$birth)!==1||$birth<'1900-01-01'||$birth>gmdate('Y-m-d'))throw new InvalidArgumentException('تاریخ تولد معتبر الزامی است.');
        if(preg_match('/^\d{10}$/',$postal)!==1)throw new InvalidArgumentException('کد پستی ۱۰ رقمی معتبر وارد کنید.');
        if($residential===''||$work==='')throw new InvalidArgumentException('آدرس محل سکونت و آدرس محل فعالیت الزامی است.');
        if($requested<=0||(int)($request['installment_count']??0)<=0)throw new InvalidArgumentException('اطلاعات مبلغ و اقساط پرونده کامل نیست.');
        if($items===[])throw new InvalidArgumentException('ثبت حداقل یک محصول معتبر الزامی است.');
        $total=0.0;foreach($items as$item){if(!is_array($item))continue;$total+=(float)($item['total_price']??$item['total']??0);}
        if($total<=0||abs($total-$requested)>1)throw new InvalidArgumentException('جمع مبلغ محصولات باید با مبلغ درخواستی برابر باشد.');
        if($firstDue===''||preg_match('/^\d{4}-\d{2}-\d{2}$/',$firstDue)!==1)throw new InvalidArgumentException('تاریخ اولین سررسید الزامی است.');
    }

}
