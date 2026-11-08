<?php
declare(strict_types=1);
namespace Babanoell\Partners\Contract;

use InvalidArgumentException;

final class ContractTemplateRenderer
{
    public static function render(string $html,array $ctx,string $code=''): string
    {
        if($code==='hami_retirees_reference'){
            $labels=['full_name'=>'نام خریدار','father_name'=>'نام پدر','certificate_number'=>'شماره شناسنامه',
                'certificate_city'=>'محل صدور شناسنامه','birth_date'=>'تاریخ تولد','national_code'=>'کد ملی',
                'residential_address'=>'نشانی خریدار','postal_code'=>'کد پستی','phone_number'=>'تلفن ثابت',
                'mobile_number'=>'تلفن همراه','product_name'=>'نام کالا','invoice_number'=>'شماره فاکتور',
                'invoice_date'=>'تاریخ فاکتور','cash_price'=>'قیمت نقدی','installment_sale_price'=>'قیمت اقساطی',
                'installment_amount'=>'مبلغ قسط','payment_count'=>'تعداد اقساط','contract_date'=>'تاریخ قرارداد'];
            $missing=[];foreach($labels as $key=>$label){$v=trim((string)($ctx[$key]??''));if($v===''||in_array($v,['0','0.0','0.00'],true))$missing[]=$label;}
            if((int)($ctx['installment_interval']??0)!==1)throw new InvalidArgumentException('قرارداد نهایی حامی بازنشستگان بازپرداخت ماهانه دارد؛ فاصله اقساط باید ۱ ماه باشد.');
            if(abs((float)($ctx['annual_interest_rate']??0)-26.0)>0.0001)throw new InvalidArgumentException('نرخ سود طرح با متن قرارداد نهایی حامی بازنشستگان همخوان نیست؛ نرخ باید ۲۶ درصد باشد.');
            if($missing!==[])throw new InvalidArgumentException('اطلاعات الزامی قرارداد کامل نیست: '.implode('، ',$missing).'؛ ابتدا اطلاعات پرونده را تکمیل کنید.');
        }
        // One pass: placeholder-shaped user data is escaped, never interpreted again.
        return preg_replace_callback('/\{\{\s*([a-z][a-z0-9_]*)\s*\}\}|(?<!\[)\[([a-z][a-z0-9_]*)\](?!\])/i',static function(array $m)use($ctx):string{
            $key=$m[1]!==''?$m[1]:($m[2]??'');
            if(!array_key_exists($key,$ctx)||(!is_scalar($ctx[$key])&&$ctx[$key]!==null))throw new InvalidArgumentException('متغیر قرارداد پشتیبانی نمی‌شود: '.$key);
            return htmlspecialchars((string)$ctx[$key],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        },$html)??throw new InvalidArgumentException('قالب قرارداد قابل پردازش نیست.');
    }
}
