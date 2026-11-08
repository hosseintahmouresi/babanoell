<?php
declare(strict_types=1);
$root=dirname(__DIR__);spl_autoload_register(static function(string $class)use($root):void{$prefix='Babanoell\\Loans\\';if(str_starts_with($class,$prefix)){ $file=$root.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require $file;}});
use Babanoell\Loans\Domain\PlanRules;
use Babanoell\Loans\Domain\RequestFilters;
$f=json_decode((string)file_get_contents(__DIR__.'/fixtures/reference-plan-visibility.json'),true,512,JSON_THROW_ON_ERROR);$count=0;
function assertRule(bool $ok,string $name):void{global$count;$count++;if(!$ok)throw new RuntimeException($name);echo 'PASS '.$name."\n";}
foreach($f['cases'] as $i=>$case){$actual=PlanRules::normalize([],$case['settings'])['visibility_contexts'];$expected=$case['expected'];sort($actual);sort($expected);assertRule($actual===$expected,'Reference visibility case '.$i);}
foreach(['digital','physical'] as $mode)assertRule(PlanRules::normalize([],['contract_signing_mode'=>$mode])['contract_mode']===$mode,'Reference legacy contract mode '.$mode);
assertRule(PlanRules::normalize(['rules'=>['contract_mode'=>'physical']],['contract_signing_mode'=>'digital'])['contract_mode']==='physical','Explicit canonical mode wins over the legacy alias');
$f=RequestFilters::normalize(['date_from'=>'۱۴۰۵/۰۱/۰۱','date_to'=>'1405/01/01']);$where=[];$params=[];RequestFilters::append($where,$params,$f);
assertRule($params[':filter_date_from']==='2026-03-20 20:30:00'&&$params[':filter_date_to']==='2026-03-21 20:30:00','Tehran date includes the whole Nowruz day with an exclusive UTC end');
foreach([['date_from'=>'2026-02-30'],['date_from'=>'1405/12/30'],['date_from'=>'2026-03-22','date_to'=>'2026-03-21'],['status'=>['draft']],['plan_id'=>'bad']] as $bad){$rejected=false;try{RequestFilters::normalize($bad);}catch(InvalidArgumentException){$rejected=true;}assertRule($rejected,'Malformed report input is rejected');}
$fresh=['status'=>'under_review','plan_rules'=>PlanRules::normalize([],[])];
assertRule(!in_array('signed_invoice_files',PlanRules::caseDocumentRequirements($fresh),true),'Signed invoice is deferred before completion');
$fresh['status']='docs_completion';$late=PlanRules::caseDocumentRequirements($fresh);
assertRule(in_array('signed_invoice_files',$late,true)&&in_array('completed_check_files',$late,true),'New default snapshot requires both phase-specific evidence types');
$fresh['plan_rules']['check_images_required']=false;
assertRule(in_array('completed_check_files',PlanRules::caseDocumentRequirements($fresh),true),'Completion check requirement is independent of the initial blank-check switch');
$fresh['plan_rules']['completed_check_files_enabled']=false;
assertRule(!in_array('completed_check_files',PlanRules::caseDocumentRequirements($fresh),true),'Explicit check-image opt-out is honored');
$legacy=['status'=>'docs_completion','required_documents'=>['national_card_front'],'plan_rules'=>['required_documents'=>['national_card_front']]];
assertRule(PlanRules::caseDocumentRequirements($legacy)===['national_card_front'],'Historical raw snapshots do not acquire newly introduced completion requirements');
echo 'Reference rules checks: '.$count."\n";
