<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
 $prefix='Babanoell\\Partners\\';if(str_starts_with($class,$prefix)){ $file=$root.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require $file;}
});
use Babanoell\Partners\Marketplace\MarketplaceConfiguration as Marketplace;
$checks=0;
$assert=static function(bool $ok,string $label)use(&$checks):void{$checks++;if(!$ok)throw new RuntimeException($label);};
$rejects=static function(callable $run,string $label)use($assert):void{
 $rejected=false;try{$run();}catch(InvalidArgumentException){$rejected=true;}$assert($rejected,$label);
};
$config=Marketplace::normalize(['categories'=>[['id'=>'goods','label'=>'کالا']],
 'locations'=>[['id'=>'tehran','label'=>'تهران'],['id'=>'rey','label'=>'ری','parent_id'=>'tehran'],['id'=>'city','label'=>'تهران شهر','parent_id'=>'tehran'],['id'=>'fars','label'=>'فارس']]]);
$assert($config['enabled']&&$config['require_category']&&$config['require_location'],'WordPress required-field defaults');
$matched=Marketplace::matchingLocations(['rey'],$config['locations']);sort($matched);
$assert($matched===['city','rey','tehran'],'siblings match through their common ancestor, as in WordPress');
$rejects(static fn()=>Marketplace::normalize(['locations'=>[['id'=>'x','label'=>'X','parent_id'=>'missing']]]),'orphan parent rejected');
$rejects(static fn()=>Marketplace::normalize(['locations'=>[['id'=>'x','label'=>'X','parent_id'=>'y'],['id'=>'y','label'=>'Y','parent_id'=>'x']]]),'cycles rejected');
$rejects(static fn()=>Marketplace::normalize(['categories'=>[['id'=>'x','label'=>'X'],['id'=>'x','label'=>'Y']]]),'duplicate ids rejected');
$rejects(static fn()=>Marketplace::normalize(['enabled'=>'unknown']),'invalid booleans rejected');
$rejects(static fn()=>Marketplace::profile(['category_ids'=>['unknown']],$config),'unknown profile category rejected');
$rejects(static fn()=>Marketplace::profile(['max_facility_amount'=>-1],$config),'negative ceiling rejected');
$rejects(static fn()=>Marketplace::profile(['max_facility_amount'=>'NaN'],$config),'nonnumeric ceiling rejected');
$assert(Marketplace::profile(['max_facility_amount'=>0],$config)['max_facility_amount']===0.0,'zero means no dedicated ceiling');

// Compare the optimized predicate against the original expansion algorithm on forests.
mt_srand(20261105);
for($case=0;$case<100;$case++){
 $terms=[];$count=mt_rand(5,50);
 for($i=0;$i<$count;$i++)$terms[]=['id'=>'node-'.$i,'label'=>'Node '.$i,'parent_id'=>$i>0&&mt_rand(0,3)>0?'node-'.mt_rand(0,$i-1):'','active'=>mt_rand(0,12)!==0];
 $selected=['node-'.mt_rand(0,$count-1),'node-'.mt_rand(0,$count-1)];
 $expanded=Marketplace::expandLocations($selected,$terms);$expected=[];
 foreach($terms as$term)if($term['active']&&array_intersect($expanded,Marketplace::expandLocations([$term['id']],$terms))!==[])$expected[]=$term['id'];
 $actual=Marketplace::matchingLocations($selected,$terms);sort($actual);sort($expected);
 $assert($expected===$actual,'location expansion parity case '.$case);
}
$large=[];for($i=0;$i<2500;$i++)$large[]=['id'=>'n'.$i,'label'=>'Node '.$i,'parent_id'=>$i>0?'n'.($i-1):'','active'=>true];
$assert(count(Marketplace::matchingLocations(['n2499'],$large))===2500,'deep catalogue supports nonrecursive matching');
echo 'Marketplace policy: '.$checks." checks passed\n";
