<?php
declare(strict_types=1);
ob_start();
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
    $prefix='Babanoell\\Panel\\';if(!str_starts_with($class,$prefix))return;
    $path=$root.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($path))require $path;
});
use Babanoell\Panel\App\Config;
use Babanoell\Panel\Http\HttpResponse;
use Babanoell\Panel\Http\HttpTransportInterface;
use Babanoell\Panel\Http\LoansClient;
use Babanoell\Panel\Http\FinanceClient;
use Babanoell\Panel\Http\PartnersClient;
use Babanoell\Panel\Http\IntegrationsClient;
use Babanoell\Panel\Reports\ReportsModule;

final class Phase8Transport implements HttpTransportInterface {
    public array $calls=[];
    public array $responses=[];
    public function request(string $method,string $url,array $headers=[],?string $body=null,int $timeoutSeconds=6):HttpResponse {
        $this->calls[]=['method'=>$method,'url'=>$url,'headers'=>$headers];
        return $this->responses[$url]??new HttpResponse(503,['error'=>['code'=>'service_unavailable']]);
    }
}
$checks=0;$fails=0;
$check=static function(bool $ok,string $message)use(&$checks,&$fails):void{$checks++;echo ($ok?'PASS ':'FAIL ').$message."\n";if(!$ok)$fails++;};
$super=['roles'=>['super_admin'],'permissions'=>[],'user'=>['display_name'=>'مدیر']];
$loanOnly=['roles'=>['bank_expert'],'permissions'=>['loans.requests.read'],'user'=>['display_name'=>'بانک']];
$none=['roles'=>['applicant'],'permissions'=>[]];
$check(ReportsModule::allowedSources($none)===[],'no report access for unauthorized actor');
$check(ReportsModule::allowedSources($loanOnly)===['loans'],'only permitted service visible');
$check(count(ReportsModule::allowedSources($super))===3,'super admin sees all three sources');
$loanData=['active_plans'=>3,'requests'=>8,'in_progress'=>5,'granted'=>1];
$r=ReportsModule::parseResponse('loans',new HttpResponse(200,['data'=>$loanData]));
$check($r['status']==='ok'&&$r['metrics']['requests']['value']===8,'valid loans totals');
$r=ReportsModule::parseResponse('finance',new HttpResponse(200,['data'=>['stats'=>['open_cycles'=>4,'pending_receipts'=>0,'bank_recorded'=>3,'merchant_pending'=>7,'open_discrepancies'=>1]]]));
$check($r['status']==='ok'&&$r['metrics']['pending_receipts']['value']===0,'zero is valid only if API returns zero');
$r=ReportsModule::parseResponse('finance',new HttpResponse(200,['data'=>['stats'=>['open_cycles'=>2]]]));
$check($r['status']==='incomplete'&&$r['metrics']['merchant_pending']['value']===null,'missing counts are not zero');
$r=ReportsModule::parseResponse('partners',new HttpResponse(200,['data'=>['merchants'=>10,'active_merchants'=>8,'cooperation_open'=>2,'marketplace_claims'=>1,'contract_versions'=>12,'contract_signed_versions'=>7,'contract_pending_versions'=>4]]));
$check($r['status']==='ok'&&$r['metrics']['contract_signed_versions']['value']===7,'contract counters are version-based and isolated from loans');
$r=ReportsModule::parseResponse('partners',new HttpResponse(403,['error'=>['code'=>'permission_denied']]));
$check($r['status']==='unavailable'&&$r['metrics']['merchants']['value']===null,'forbidden results are not numbers');
$r=ReportsModule::parseResponse('partners',new HttpResponse(0,null,'','network_down'));
$check($r['status']==='unavailable','network failure is distinct from zero');
$csv=ReportsModule::csv(['test'=>['title'=>'=HYPERLINK("bad")','status'=>'ok','url'=>'/reports','metrics'=>['a'=>['label'=>'+SUM(1,1)','value'=>null]]]],'2026-10-22 UTC');
$check(str_starts_with($csv,"\xEF\xBB\xBF")&&str_contains($csv,"'=HYPERLINK")&&str_contains($csv,"'+SUM"),'CSV neutralizes spreadsheet formulas');
$check(!str_contains($csv,'access-token')&&str_contains($csv,'وضعیت منبع'),'export only includes intended columns');
$transport=new Phase8Transport();
$config=new Config('panel','staging','https://identity.noeel.ir','sample-secret',6,1800,28800,'https://loans.noeel.ir','https://documents.noeel.ir','', 'https://partners.noeel.ir','https://finance.noeel.ir','https://integrations.noeel.ir');
$transport->responses['https://loans.noeel.ir/api/v1/stats']=new HttpResponse(200,['data'=>$loanData]);
$report=new ReportsModule(new LoansClient($config,$transport),new PartnersClient($config,$transport),new FinanceClient($config,$transport),new IntegrationsClient($config,$transport),$config,'bearer123',$loanOnly);
ob_start();$handled=$report->handle('/reports/export.csv','GET');$output=ob_get_clean();
$check($handled&&str_contains($output,'تمام پرونده‌های قابل مشاهده')&&count($transport->calls)===1,'single authorized backend call for restricted report');
$check(str_contains($transport->calls[0]['url'],'loans.noeel.ir')&&isset($transport->calls[0]['headers']['Authorization']),'token passed to permitted source only');
$transport->calls=[];
ob_start();$handled=$report->handle('/system/settings','GET');ob_end_clean();
$check($handled&&count($transport->calls)===0,'settings gate never calls services for non super admin');
$noneReport=new ReportsModule(new LoansClient($config,$transport),new PartnersClient($config,$transport),new FinanceClient($config,$transport),new IntegrationsClient($config,$transport),$config,'bearer123',$none);
ob_start();$handled=$noneReport->handle('/reports/export.csv','GET');$forbidden=ob_get_clean();
$check($handled&&count($transport->calls)===0&&!str_contains($forbidden,'تمام پرونده‌ها'),'forbidden report does not access data');
$check($config->isReady()&&$config->integrationsBaseUrl()==='https://integrations.noeel.ir','integrations has configurable URL');
$release=require $root.'/config/version.php';
$check(version_compare((string)($release['version']??'0'),'2.12.0','>='),'phase8 metadata');
echo "Phase8: ".($checks-$fails)." passed, $fails failed\n";
ob_end_flush();
exit($fails?1:0);
