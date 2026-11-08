<?php
declare(strict_types=1);
$root=dirname(__DIR__);spl_autoload_register(static function(string $class)use($root):void{$prefix='Babanoell\\Panel\\';if(str_starts_with($class,$prefix)){$file=$root.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require$file;}});
use Babanoell\Panel\Reports\RequestReportModule as Report;
use Babanoell\Panel\Support\PersianDateTime;
function checkReport(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo 'PASS '.$label."\n";}
$row=['id'=>str_repeat('a',26),'request_no'=>'123','full_name'=>'=SUM(1,1)','national_code'=>'0012345678','mobile_number'=>'09121234567','plan_name'=>'Synthetic plan','plan_type'=>'market','requested_amount'=>'9007199254740993.25','facility_amount'=>'70.00','wallet_charge'=>'60.00','acceptor_payment_amount'=>'-10.25','installment_count'=>6,'installment_amount'=>'20.00','total_payment'=>'120.00','status'=>'granted','settlement_status'=>'pending','settlement_date'=>null,'bank_settlement_status'=>'not_recorded','financier_payment_status'=>'unreviewed','created_at'=>'2026-03-20 20:30:00'];
$d=['items'=>[$row],'total'=>1,'page'=>1,'per_page'=>25,'statuses'=>[['status'=>'granted','count'=>1]],'totals'=>['total'=>1,'requested_amount'=>$row['requested_amount'],'facility_amount'=>'70.00','wallet_charge'=>'60.00','missing_facility_count'=>0]];
checkReport(Report::validData($d,false),'Complete scoped financial report is accepted');
foreach(['totals','statuses','page'] as $key){$bad=$d;unset($bad[$key]);checkReport(!Report::validData($bad,false),'Incomplete report cannot fabricate a missing '.$key);}
$bad=$d;$bad['statuses'][0]['count']=2;checkReport(!Report::validData($bad,false),'Inconsistent status totals cannot be displayed as a complete report');
$csvData=['items'=>[$row],'total'=>1,'truncated'=>false];checkReport(Report::validData($csvData,true),'Complete single-snapshot export is accepted');
$bad=$csvData;unset($bad['items'][0]['wallet_charge']);checkReport(!Report::validData($bad,true),'A partial financial row cannot be exported silently');
$bad=$csvData;$bad['truncated']=true;checkReport(!Report::validData($bad,true),'Truncated export is rejected');
$csv=Report::csv([$row]);$f=fopen('php://temp','w+');fwrite($f,substr($csv,3));rewind($f);$head=fgetcsv($f,0,',','"','');$values=fgetcsv($f,0,',','"','');fclose($f);
checkReport(str_starts_with($csv,"\xEF\xBB\xBF")&&in_array("'=SUM(1,1)",$values,true),'Text formulas are escaped in the UTF-8 CSV');
checkReport(in_array('-10.25',$values,true)&&in_array('9007199254740993.25',$values,true)&&in_array('0012345678',$values,true),'Numeric signs, exact large amounts and identifier digits are preserved');
checkReport(Report::money('9007199254740993.25')==='۹,۰۰۷,۱۹۹,۲۵۴,۷۴۰,۹۹۳.۲۵','Display groups exact decimal digits without floating point conversion');
date_default_timezone_set('Asia/Tehran');$date=PersianDateTime::fromIso('2026-03-20 20:30:00');checkReport(is_string($date)&&str_contains($date,'۱۴۰۵')&&str_contains($date,'۰۰:۰۰'),'Naive UTC persistence timestamps display the correct Tehran midnight regardless of PHP timezone');
