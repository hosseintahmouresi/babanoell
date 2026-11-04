<?php
declare(strict_types=1);
ob_start();
if (PHP_SAPI!=='cli') exit(1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void {
    $p='Babanoell\\Panel\\'; if(!str_starts_with($class,$p)) return;
    $file=$root.'/src/'.str_replace('\\','/',substr($class,strlen($p))).'.php';
    if(is_file($file)) require $file;
});
use Babanoell\Panel\App\Config;
use Babanoell\Panel\Http\HttpResponse;
use Babanoell\Panel\Http\HttpTransportInterface;
use Babanoell\Panel\Http\FinanceClient;
use Babanoell\Panel\Http\IntegrationsClient;
use Babanoell\Panel\Http\LoansClient;
use Babanoell\Panel\Http\PartnersClient;
use Babanoell\Panel\Reports\ReportsModule;
use Babanoell\Panel\System\HealthAudit;
use Babanoell\Panel\Platform\ServiceBoundary;
final class P10Transport implements HttpTransportInterface {
    public array $calls=[];
    public function request(string $method,string $url,array $headers=[],?string $body=null,int $timeoutSeconds=6):HttpResponse {
        $this->calls[]=compact('method','url','headers','body','timeoutSeconds');
        return match($url){
            'https://identity.noeel.ir/healthz'=>new HttpResponse(200,['status'=>'ok']),
            'https://loans.noeel.ir/health'=>new HttpResponse(200,['status'=>'degraded']),
            'https://documents.noeel.ir/health'=>new HttpResponse(503,['status'=>'unavailable']),
            'https://partners.noeel.ir/health'=>new HttpResponse(200,['status'=>'ok']),
            'https://finance.noeel.ir/health'=>new HttpResponse(200,['data'=>['status'=>'ok']]),
            'https://integrations.noeel.ir/health'=>new HttpResponse(200,['data'=>['service'=>'integrations']]),
            default=>throw new RuntimeException('Unexpected URL: '.$url),
        };
    }
}
$passed=0;$failed=0;
$check=static function(bool $ok,string $message)use(&$passed,&$failed):void {
    echo ($ok?'[PASS] ':'[FAIL] ').$message."\n";
    $ok?$passed++:$failed++;
};
$config=new Config('panel','production','https://identity.noeel.ir','sample-session-secret',2,1800,28800,
  'https://loans.noeel.ir','https://documents.noeel.ir','sample-shared-secret',
  'https://partners.noeel.ir','https://finance.noeel.ir','https://integrations.noeel.ir');
$transport=new P10Transport();
$values=(new HealthAudit($config,$transport))->probe();
$check(count($values)===7,'Seven services including panel are visible');
$check(count($transport->calls)===6,'Exactly six read-only remote checks');
$check(count(array_filter($transport->calls,static fn($c)=>$c['method']==='GET' && $c['body']===null && $c['timeoutSeconds']===2))===6,'All probes are GET with two-second timeout');
$check($values[0]['state']==='healthy','Local config distinguished from remote network');
$check($values[1]['state']==='healthy'&&$values[1]['http_status']===200,'Healthy identity');
$check($values[2]['state']==='degraded','Explicit degraded state respected');
$check($values[3]['state']==='unavailable'&&$values[3]['http_status']===503,'HTTP failure is not healthy');
$check($values[6]['state']==='reachable','HTTP 200 without health status does not imply healthy');
$check(HealthAudit::state(new HttpResponse(0,null,'','down'))==='unavailable','Network errors fail closed');
$check(HealthAudit::state(new HttpResponse(200,['status'=>'error']))==='degraded','Error in 200 body detected');
$check(HealthAudit::state(new HttpResponse(200,['status'=>['bad']]))==='reachable','Malformed status cannot cause notice or false healthy');
$check(HealthAudit::isAllowedUrl('https://identity.noeel.ir'),'Authorized service host accepted');
$check(!HealthAudit::isAllowedUrl('http://identity.noeel.ir'),'Insecure URL rejected');
$check(!HealthAudit::isAllowedUrl('https://identity.noeel.ir@127.0.0.1'),'Host spoofing rejected');
$check(!HealthAudit::isAllowedUrl('https://127.0.0.1'),'Internal host rejected');
$check(!HealthAudit::isAllowedUrl('https://identity.noeel.ir/some/path'),'Unexpected probe path rejected');
$check(!HealthAudit::isAllowedUrl('https://identity.noeel.ir?url=http://127.0.0.1'),'Query parameters rejected');
$check((ServiceBoundary::describe()['external_services']['integrations']??null)==='active','Integrations correctly marked active');
$super=['roles'=>['super_admin'],'permissions'=>[],'user'=>['display_name'=>'مدیر']];
$restricted=['roles'=>['applicant'],'permissions'=>[],'user'=>['display_name'=>'کاربر']];
$report=new ReportsModule(new LoansClient($config,$transport),new PartnersClient($config,$transport),new FinanceClient($config,$transport),new IntegrationsClient($config,$transport),$config,'sensitive-token',$restricted,$transport);
$before=count($transport->calls);
ob_start();$handled=$report->handle('/system/health','GET');$forbidden=ob_get_clean();
$check($handled&&count($transport->calls)===$before&&http_response_code()===403,'Non-admin request denied without network probes');
$report=new ReportsModule(new LoansClient($config,$transport),new PartnersClient($config,$transport),new FinanceClient($config,$transport),new IntegrationsClient($config,$transport),$config,'sensitive-token',$super,$transport);
ob_start();$handled=$report->handle('/system/health','GET');$html=ob_get_clean();
$check($handled&&str_contains($html,'وضعیت سرویس‌های نوئل')&&str_contains($html,'در دسترس؛ وضعیت نامشخص'),'Super admin receives diagnostic interface');
$check(!str_contains($html,'sensitive-token')&&!str_contains($html,'sample-shared-secret'),'Diagnostics do not leak credentials');
$check($report->handle('/system/health','POST')===true&&http_response_code()===405,'State-changing method rejected');
echo "Phase10 health: $passed passed, $failed failed\n";
ob_end_flush();
exit($failed?1:0);
