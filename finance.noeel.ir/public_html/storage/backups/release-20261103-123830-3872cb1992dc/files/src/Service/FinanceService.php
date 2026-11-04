<?php
declare(strict_types=1);
namespace Babanoell\Finance\Service;
use Babanoell\Finance\Domain\FinanceRules;use Babanoell\Finance\Domain\CycleIntegrity;use Babanoell\Finance\Http\DocumentsClient;use Babanoell\Finance\Http\LoansClient;use Babanoell\Finance\Http\PartnersClient;use Babanoell\Finance\Repository\PdoFinanceRepository;use InvalidArgumentException;use RuntimeException;
final class FinanceService
{
 public function __construct(private readonly PdoFinanceRepository$repo,private readonly LoansClient$loans,private readonly PartnersClient$partners,private readonly DocumentsClient$documents){}
 public function requestContext(string$id,string$bearer):array{$r=$this->loans->request(FinanceRules::id($id,'شناسه پرونده'),$bearer);if($r->status===404)throw new InvalidArgumentException('پرونده پیدا نشد.');if($r->status===403)throw new RuntimeException('record_access_denied');if(!$r->ok())throw new RuntimeException('loans_unavailable');$loan=$r->json['data']['request']??null;if(!is_array($loan))throw new RuntimeException('loans_response_invalid');return$loan;}
 public function snapshot(string$id,string$bearer):array{$loan=$this->requestContext($id,$bearer);return['request'=>$loan,'finance'=>$this->repo->snapshot($id),'history'=>['bank'=>$this->repo->bankSettlementHistory($id),'merchant'=>$this->repo->merchantSettlementHistory($id),'financier'=>$this->repo->financierPaymentHistory($id)]];}
 public function bankSettlement(string$id,string$bearer,string$actor,array$b):array{$loan=$this->requestContext($id,$bearer);$status=FinanceRules::bankSettlementStatus((string)($b['status']??''));$at=trim((string)($b['settled_at']??''));if($status==='recorded'){if((string)($loan['status']??'')!=='granted')throw new InvalidArgumentException('ثبت تسویه بانک فقط برای پرونده اعطاشده مجاز است.');if($at===''||strtotime($at)===false)throw new InvalidArgumentException('تاریخ تسویه بانک معتبر نیست.');$at=date('Y-m-d H:i:s',strtotime($at));}else$at=null;return$this->repo->recordBankSettlement($id,$status,$at,mb_substr(trim((string)($b['note']??'')),0,1000),$actor);}
 public function merchantSettlement(string$id,string$bearer,string$actor,array$b):array
 {
  $loan=$this->requestContext($id,$bearer);
  $status=FinanceRules::merchantSettlementStatus((string)($b['status']??''));
  $date=trim((string)($b['settlement_date']??''));
  if($status==='settled'){
   if((string)($loan['status']??'')!=='granted')throw new InvalidArgumentException('ثبت تسویه پذیرنده فقط برای پرونده اعطاشده مجاز است.');
   if($date===''||strtotime($date)===false)throw new InvalidArgumentException('تاریخ تسویه معتبر نیست.');
   $date=date('Y-m-d',strtotime($date));
  }else{$date=$date!==''&&strtotime($date)!==false?date('Y-m-d',strtotime($date)):null;}
  $merchant=trim((string)($loan['merchant_id']??''));$merchant=$merchant!==''?$merchant:null;
  $cycle=trim((string)($b['cycle_id']??''));$cycle=$cycle!==''?FinanceRules::id($cycle,'شناسه سیکل'):null;
  return$this->repo->recordMerchantSettlement($id,$merchant,$status,$date,$cycle,mb_substr(trim((string)($b['note']??'')),0,1000),$actor);
 }
 public function financierPayment(string$id,string$bearer,string$actor,array$b):array{$this->requestContext($id,$bearer);$status=FinanceRules::financierPaymentStatus((string)($b['status']??''));return$this->repo->recordFinancierPayment($id,$status,mb_substr(trim((string)($b['note']??'')),0,1000),$actor);}
 public function createCycle(string$actor,array$b,string$bearer,string$key=''):array{$merchant=FinanceRules::id((string)($b['merchant_id']??''),'شناسه پذیرنده');$r=$this->partners->merchant($merchant,$bearer);if(!$r->ok())throw new InvalidArgumentException('پذیرنده معتبر یا قابل دسترسی نیست.');$date=trim((string)($b['settlement_date']??''));if($date!==''&&strtotime($date)===false)throw new InvalidArgumentException('تاریخ سیکل معتبر نیست.');return$this->repo->createCycle($merchant,$date!==''?date('Y-m-d',strtotime($date)):null,mb_substr(trim((string)($b['description']??'')),0,5000),$actor,$key);}
 public function addCycleItem(string$cycle,string$actor,array$b,string$bearer,string$key=''):array{$request=FinanceRules::id((string)($b['request_id']??''),'شناسه پرونده');$loan=$this->requestContext($request,$bearer);if((string)($loan['status']??'')!=='granted')throw new InvalidArgumentException('فقط پرونده اعطاشده قابل افزودن به سیکل تسویه است.');if(!empty($loan['settlement_cycle_id']))throw new InvalidArgumentException('پرونده قبلاً در سیکل قدیمی Loans ثبت شده است؛ ابتدا باید مغایرت انتقال رفع شود.');$settlement=FinanceRules::money($b['settlement_amount']??0,'مبلغ تسویه');$leasing=FinanceRules::money($b['leasing_amount']??0,'مبلغ لیزینگ');$merchant=FinanceRules::money($b['merchant_amount']??0,'مبلغ پذیرنده');CycleIntegrity::validateItem($b['settlement_amount']??0,$b['leasing_amount']??0,$b['merchant_amount']??0);$loanMerchant=(string)($loan['merchant_id']??'');return$this->repo->addCycleItem(FinanceRules::id($cycle,'شناسه سیکل'),$request,$settlement,$leasing,$merchant,$actor,$loanMerchant,$key);}
 public function createReceipt(string$actor,array$b,string$bearer,string$key=''):array{$request=trim((string)($b['request_id']??''));$cycle=trim((string)($b['cycle_id']??''));if($request===''&&$cycle==='')throw new InvalidArgumentException('رسید باید به پرونده یا سیکل متصل باشد.');if($request!=='')$this->requestContext(FinanceRules::id($request,'شناسه پرونده'),$bearer);if($cycle!=='')FinanceRules::id($cycle,'شناسه سیکل');$doc=FinanceRules::id((string)($b['document_id']??''),'شناسه مدرک');$dr=$this->documents->document($doc,$bearer);if(!$dr->ok())throw new InvalidArgumentException('مدرک رسید معتبر یا قابل دسترسی نیست.');$amount=array_key_exists('amount',$b)&&$b['amount']!==null&&$b['amount']!==''?FinanceRules::money($b['amount'],'مبلغ رسید'):null;return$this->repo->createReceipt($request!==''?$request:null,$cycle!==''?$cycle:null,mb_substr(trim((string)($b['receipt_type']??'transfer')),0,32),$amount,$doc,mb_substr(trim((string)($b['external_reference']??'')),0,190),$actor,$key);}
 public function importLoansShadow(string$actor):array{$page=1;$imported=0;do{$r=$this->loans->financeShadow($page,100);if(!$r->ok())throw new RuntimeException('Loans finance shadow unavailable.');$data=$r->json['data']??[];$items=is_array($data['items']??null)?$data['items']:[];foreach($items as$row)if(is_array($row)){$this->repo->importShadow($row,$actor);$imported++;}$total=(int)($data['total']??0);$page++;}while(($page-1)*100<$total&&$page<=1000);$cycles=0;$cr=$this->loans->financeCyclesShadow();if($cr->ok()){foreach((array)($cr->json['data']['cycles']??[])as$c)if(is_array($c)){$this->repo->importLegacyCycle($c,$actor);$cycles++;}foreach((array)($cr->json['data']['items']??[])as$i)if(is_array($i))$this->repo->importLegacyCycleItem($i);}$receipts=0;$rr=$this->loans->financeReceiptsShadow();if($rr->ok())foreach((array)($rr->json['data']['items']??[])as$r)if(is_array($r)){$this->repo->importLegacyReceipt($r,$actor);$receipts++;}$this->repo->event('finance.shadow_imported','finance',null,$actor,['requests'=>$imported,'cycles'=>$cycles,'receipts'=>$receipts]);return['requests'=>$imported,'cycles'=>$cycles,'receipts'=>$receipts];}
 public function adminResetMerchantSettlement(string$id,string$bearer,string$actor,array$b):array
 {
  $loan=$this->requestContext($id,$bearer);
  if((string)($loan['status']??'')!=='granted')throw new InvalidArgumentException('بازنشانی تسویه فقط برای پرونده اعطاشده مجاز است.');
  if(!empty($loan['settlement_cycle_id']))throw new InvalidArgumentException('این پرونده هنوز به سیکل قدیمی Loans متصل است؛ پیش از بازنشانی باید رفع مغایرت شود.');
  $merchant=trim((string)($loan['merchant_id']??''));
  $reason=trim((string)($b['reason']??''));
  $settlement=$this->repo->resetMerchantSettlement($id,$merchant!==''?$merchant:null,$actor,$reason,(int)($b['row_version']??-1),($b['confirm']??false)===true);
  $sync=$this->loans->syncMerchantSettlementResetShadow($id,$actor,$reason);
  return ['settlement'=>$settlement,'shadow_sync'=>['ok'=>$sync->ok(),'detail'=>$sync->ok()?($sync->json['data']['projection']??[]):null,'retry_required'=>!$sync->ok()]];
 }


 public function retryMerchantSettlementShadowSync(string$id,string$bearer,string$actor,array$b):array
 {
  $loan=$this->requestContext($id,$bearer);
  if((string)($loan['status']??'')!=='granted'||!empty($loan['settlement_cycle_id']))throw new InvalidArgumentException('پرونده برای بازهمگام‌سازی امن نیست.');
  $current=$this->repo->snapshot($id)['merchant_settlement']??null;
  if(!is_array($current)||(string)$current['status']!=='awaiting_date'||$current['settlement_date']!==null||!empty($current['cycle_id']))throw new InvalidArgumentException('وضعیت جاری Finance با بازنشانی تسویه تطابق ندارد.');
  if(($b['confirm']??false)!==true)throw new InvalidArgumentException('تأیید مجدد مدیر الزامی است.');
  $reason=trim((string)($b['reason']??''));if(mb_strlen($reason)<10||mb_strlen($reason)>1000)throw new InvalidArgumentException('دلیل حداقل ۱۰ کاراکتری الزامی است.');
  $sync=$this->loans->syncMerchantSettlementResetShadow($id,$actor,$reason);
  if(!$sync->ok())throw new RuntimeException('همگام‌سازی Loans موفق نبود؛ پس از بررسی دسترسی سرویس‌ها دوباره تلاش کنید.');
  return ['ok'=>true,'projection'=>$sync->json['data']['projection']??[]];
 }

}
