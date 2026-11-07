<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/bootstrap.php';
use Babanoell\Integrations\Http\TransportInterface;
use Babanoell\Integrations\Platform\CreditReportProof;
use Babanoell\Integrations\Provider\Credit;

$loansRoot=dirname(__DIR__,3).'/loans.noeel.ir/public_html';
if(!is_dir($loansRoot))$loansRoot=dirname(__DIR__,2).'/loans';
require_once $loansRoot.'/src/Platform/CreditReportProof.php';
$checks=0;
$check=static function(bool $ok,string $name)use(&$checks):void{if(!$ok)throw new RuntimeException($name);$checks++;};
$reject=static function(callable $call,string $name)use($check):void{try{$call();}catch(Throwable){$check(true,$name);return;}$check(false,$name);};
$secret=str_repeat('synthetic-key-',4);
$binding=['verification_id'=>str_repeat('a',26),'request_id'=>str_repeat('b',26),'request_hash'=>str_repeat('c',64),
 'subject_fingerprint'=>hash('sha256','1234567890|09123456789'),'reference'=>'reference_123','report_code'=>'report_123'];
$report=['score'=>['score'=>610,'risk'=>'B1','personInformation'=>['nationalCode'=>'1234567890']],
 'report'=>['summaryInformationRelatedToContractsSum'=>[['totalAmount'=>1000000,'inProgressContractsCount'=>1]]]];
$reject(static fn()=>CreditReportProof::canonical(['score'=>[], 'report'=>[]]),'Empty report sections are not final evidence');
$now=1800000000;
$proof=CreditReportProof::issue($binding,$report,$secret,$now);
$expected=array_intersect_key($binding,array_flip(['verification_id','request_id','request_hash','subject_fingerprint','reference']));
$verified=\Babanoell\Loans\Platform\CreditReportProof::verify($proof,$report,$expected,$secret,$now+10);
$check($verified['report_sha256']===hash('sha256',CreditReportProof::canonical($report)),'Cross-service proof and digest agree');
$reordered=['report'=>$report['report'],'score'=>array_reverse($report['score'],true)];
$check(CreditReportProof::canonical($report)===CreditReportProof::canonical($reordered),'Object key order does not change the immutable digest');
$bad=$report;$bad['score']['score']=900;
$reject(fn()=>\Babanoell\Loans\Platform\CreditReportProof::verify($proof,$bad,$expected,$secret,$now+10),'Browser-edited score is rejected');
foreach(['verification_id','request_id','request_hash','subject_fingerprint','reference'] as $key){
 $other=$expected;$other[$key]=$key==='reference'?'another_reference':str_repeat('d',strlen($other[$key]));
 $reject(fn()=>\Babanoell\Loans\Platform\CreditReportProof::verify($proof,$report,$other,$secret,$now+10),'Cross-request/subject binding '.$key);
}
$reject(fn()=>\Babanoell\Loans\Platform\CreditReportProof::verify($proof,$report,$expected,str_repeat('wrong',8),$now+10),'Wrong shared key');
$reject(fn()=>\Babanoell\Loans\Platform\CreditReportProof::verify($proof,$report,$expected,$secret,$now+7200),'Expired proof');
$reject(fn()=>\Babanoell\Loans\Platform\CreditReportProof::verify($proof,$report,$expected,$secret,$now-60),'Future proof');
$bad=$report;$bad['score']['personInformation']['nationalCode']='1111111111';
$reject(fn()=>CreditReportProof::assertSubject($bad,'1234567890'),'Mismatched report identity');
$check(CreditReportProof::summary($report)===['score'=>610,'risk'=>'B1'],'Reference score/risk schema');
foreach(['report_123','/report/report_123','https://mycredit.ics24.ir/report/report_123/json?view=1'] as $link)$check(Credit::reportCode($link)==='report_123','Reference report link '.$link);
foreach(['https://evil.example/report/report_123','https://user:pass@mycredit.ics24.ir/report/report_123','/report/../settings','/report/encoded%2Fslash','https://127.0.0.1/report/report_123'] as $link)$reject(fn()=>Credit::reportCode($link),'Unsafe report link');
foreach([[],['message'=>'not a report'],['score'=>['score'=>'NaN']]] as $bad)$reject(fn()=>CreditReportProof::summary($bad),'Invalid report shape/score');

// No external requests: exercise the exact MyCredit endpoints with a scripted transport.
putenv('INTEGRATIONS_ICS24_USERNAME=synthetic-user');putenv('INTEGRATIONS_ICS24_PASSWORD=synthetic-password');
$transport=new class($report) implements TransportInterface {
 public array $calls=[];
 public function __construct(private readonly array $report){}
 public function request(string $method,string $url,?array $payload=null,array $headers=[],int $timeout=18,string $encoding='json',array $hosts=[]):array {
  $this->calls[]=[$method,parse_url($url,PHP_URL_PATH),$payload];
  $data=match(parse_url($url,PHP_URL_PATH)){
   '/Connect/Token'=>['accessToken'=>'synthetic-token','apiKey'=>'synthetic-api-key'],
   '/b2b/api/request/reference_123/status'=>['status'=>'ReportGenerated','statusTitle'=>'آماده','reportLink'=>'https://mycredit.ics24.ir/report/report_123'],
   '/report/report_123/json'=>$this->report,
   default=>throw new RuntimeException('Unexpected provider endpoint'),
  };
  return ['status'=>200,'valid_json'=>true,'data'=>['hasError'=>false,'data'=>$data]];
 }
};
$context=$expected+['national_code'=>'1234567890','mobile'=>'09123456789'];
$result=(new Credit($transport))->finalize($context,$secret);
$check($result['ready']===true&&count($transport->calls)===3,'Status leads to a server-fetched report with signed evidence');
$check($transport->calls[2][1]==='/report/report_123/json','No arbitrary report URL is fetched');
\Babanoell\Loans\Platform\CreditReportProof::verify($result['proof'],$result['report'],$expected,$secret);
$check(true,'Finalized report verifies in Loans');
echo $checks." credit-report protocol checks passed; no live provider calls.\n";
