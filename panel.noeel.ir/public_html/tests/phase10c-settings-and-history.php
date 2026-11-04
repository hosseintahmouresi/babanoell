<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
 $pre='Babanoell\\Panel\\';if(!str_starts_with($class,$pre))return;
 $f=$root.'/src/'.str_replace('\\','/',substr($class,strlen($pre))).'.php';if(is_file($f))require$f;
});
use Babanoell\Panel\Reports\HistoricalReportsModule;
use Babanoell\Panel\System\OperationalPreferences;
$tests=0;
$check=static function(bool $ok,string $description)use(&$tests):void{$tests++;if(!$ok)throw new RuntimeException($description);};
$tmp=sys_get_temp_dir().'/noel-phase10c-'.bin2hex(random_bytes(5));
mkdir($tmp,0700,true);
try{
 $p=new OperationalPreferences($tmp);
 $check($p->get()['report_default_days']===30,'secure defaults');
 $settings=$p->save(['report_default_days'=>'14','report_max_days'=>'90','health_slow_threshold_ms'=>'1700'],0,'0123456789abcdef0123456789');
 $check($settings['revision']===1,'first revision');
 $check($p->get()['health_slow_threshold_ms']===1700,'persistent settings');
 $check(count($p->history())===1,'audited save');
 try{$p->save(['report_default_days'=>'30','report_max_days'=>'90','health_slow_threshold_ms'=>'1700'],0,'0123456789abcdef0123456789');throw new RuntimeException('stale write accepted');}catch(DomainException){$tests++;}
 try{OperationalPreferences::validate(['report_default_days'=>'90','report_max_days'=>'30','health_slow_threshold_ms'=>'1700']);throw new RuntimeException('invalid limits accepted');}catch(InvalidArgumentException){$tests++;}
 try{OperationalPreferences::validate(['report_default_days'=>'14','report_max_days'=>'90','health_slow_threshold_ms'=>'1700','NOEEL_SERVICE_SHARED_SECRET'=>'leak']);throw new RuntimeException('secret setting accepted');}catch(InvalidArgumentException){$tests++;}
 $today=gmdate('Y-m-d');
 [$from,$to]=HistoricalReportsModule::range([],$settings);
 $check($to===$today,'today as default end');
 $check($from===gmdate('Y-m-d',strtotime('today UTC -13 days')),'custom default period');
 foreach([['from'=>'2026-02-30','to'=>'2026-03-01'],['from'=>'2026-10-21','to'=>'2026-10-01'],['from'=>'2025-01-01','to'=>'2026-10-01']] as $q){try{HistoricalReportsModule::range($q,$settings);throw new RuntimeException('bad range accepted');}catch(InvalidArgumentException){$tests++;}}
 $data=['data'=>['from'=>'2026-10-20','to'=>'2026-10-21','series'=>['requests_created'=>[['day'=>'2026-10-20','count'=>3]]]]];
 $parsed=HistoricalReportsModule::parse('loans',200,$data,'2026-10-20','2026-10-21');
 $check($parsed['state']==='ok'&&$parsed['metrics']['requests_created']['total']===3,'valid daily payload');
 $check(HistoricalReportsModule::parse('loans',403,$data,'2026-10-20','2026-10-21')['state']==='unavailable','permission denied never zero');
 $bad=$data;$bad['data']['series']['requests_created'][0]['count']=-7;
 $check(HistoricalReportsModule::parse('loans',200,$bad,'2026-10-20','2026-10-21')['state']==='unavailable','negative count rejected');
 $rows=HistoricalReportsModule::rows(['loans'=>$parsed],'2026-10-20','2026-10-21');
 $check(count($rows)===2&&$rows[0][3]==='3'&&$rows[1][3]==='0','daily report gap filled');
 $check(HistoricalReportsModule::rows(['loans'=>['state'=>'unavailable','metrics'=>[]]],'2026-10-20','2026-10-21')===[],'unavailable sources never fabricated');
}finally{
 foreach(glob($tmp.'/storage/operational-preferences/*')?:[] as $f)unlink($f);
 @rmdir($tmp.'/storage/operational-preferences');@rmdir($tmp.'/storage');@rmdir($tmp);
}
echo 'Phase10C Panel tests: '.$tests.' passed'.PHP_EOL;
