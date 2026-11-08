<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__);
$partner=is_dir(dirname($root).'/partners/src')?dirname($root).'/partners':dirname($root,2).'/partners.noeel.ir/public_html';
spl_autoload_register(static function(string $class)use($root,$partner):void{foreach(['Babanoell\\Loans\\'=>$root,'Babanoell\\Partners\\'=>$partner]as$prefix=>$base)if(str_starts_with($class,$prefix)){require_once $base.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';return;}});
use Babanoell\Loans\Domain\{ContractContext,PersianMoneyWords,ReferenceContractContext};
use Babanoell\Partners\Contract\ContractTemplateRenderer;
$checks=0;$assert=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks++;};
foreach([0=>'صفر',101=>'یکصد و یک',115=>'یکصد و پانزده',999=>'نهصد و نود و نه',1000001=>'یک میلیون و یک',-1000=>'منفی یک هزار'] as $v=>$expected)$assert(PersianMoneyWords::convert($v)===$expected,'Reference Persian money words');
$zone=new DateTimeZone('Asia/Tehran');
foreach(['2026-04-04'=>'1405/01/31','2026-04-05'=>'1405/02/31','2026-03-05'=>'1404/12/29']as$date=>$expected)$assert(ReferenceContractContext::firstDue(new DateTimeImmutable($date,$zone),1,'jalali_month_end_half')===$expected,'Jalali half-month first-due boundary '.$date);
$loan=['id'=>str_repeat('a',26),'request_no'=>'12001','full_name'=>'<script>synthetic</script>','father_name'=>'پدر','certificate_number'=>'123','certificate_city'=>'تهران','national_code'=>'0013543318','mobile_number'=>'09121234567','phone_number'=>'02112345678','birth_date'=>'1990-03-21','created_at'=>'2026-03-21 00:00:00','residential_address'=>'نشانی','postal_code'=>'1234567890','work_address'=>'کار','product_name'=>'کالا','retirement_code'=>'R123','retirement_organization'=>'سازمان','requested_amount'=>100000000,'facility_amount'=>70000000,'installment_amount'=>10000000,'total_payment'=>120000000,'installment_count'=>12,'installment_interval'=>1,'plan_settings'=>['interest_rate'=>26],'plan_rules'=>['facility_agreement_template_code'=>'hami_retirees_reference']];
$ctx=ContractContext::fromLoan($loan,new DateTimeImmutable('2026-03-21',$zone));
$assert($ctx['loan_amount']==='100,000,000'&&$ctx['cash_price']==='70,000,000'&&$ctx['installment_sale_price']==='120,000,000','Purchase, financed cash price and sale price retain distinct reference meanings');
$assert($ctx['payment_count']==='12'&&$ctx['contract_end_date']==='1406/01/01','Retirees end date uses Jalali months');
foreach(['hami_plus_reference'=>'hami-plus-digital-loan-contract.html','hami_retirees_reference'=>'hami-retirees-final-loan-contract.html']as$code=>$file){
 $html=ContractTemplateRenderer::render(file_get_contents($partner.'/resources/contracts/'.$file),$ctx,$code);
 $assert(!preg_match('/\[[a-z_]+\]/',$html)&&str_contains($html,'&lt;script&gt;synthetic&lt;/script&gt;'),'Complete reference HTML resolves all placeholders and escapes identity');
 $assert(str_contains($html,'70,000,000')||$code==='hami_retirees_reference','Correct financed amount appears in reference contract');
}
$missing=$ctx;$missing['certificate_city']='';
try{ContractTemplateRenderer::render('[full_name]',$missing,'hami_retirees_reference');throw new RuntimeException('Missing city signed');}catch(InvalidArgumentException){$checks++;}
foreach(['installment_interval'=>'2','annual_interest_rate'=>'23']as$key=>$bad){$invalid=$ctx;$invalid[$key]=$bad;try{ContractTemplateRenderer::render('[full_name]',$invalid,'hami_retirees_reference');throw new RuntimeException('Invalid contract economics signed');}catch(InvalidArgumentException){$checks++;}}
$assert(ContractTemplateRenderer::render('{{full_name}} [mobile_number]',['full_name'=>'{{mobile_number}}','mobile_number'=>'09'])==='{{mobile_number}} 09','Replacement values are not interpreted recursively');
try{ContractTemplateRenderer::render('[unknown_reference_field]',[]);throw new RuntimeException('Unknown placeholder accepted');}catch(InvalidArgumentException){$checks++;}
echo "PASS reference contract semantics: $checks checks\n";
