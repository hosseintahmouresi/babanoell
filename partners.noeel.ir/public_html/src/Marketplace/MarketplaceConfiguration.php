<?php
declare(strict_types=1);
namespace Babanoell\Partners\Marketplace;

use Babanoell\Partners\Support\Json;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/** Canonical marketplace catalogue and merchant eligibility, owned by Partners. */
final class MarketplaceConfiguration
{
 public function __construct(private readonly PDO $pdo){}

 public function current():array
 {
  $r=$this->pdo->query('SELECT revision,definition_json FROM marketplace_configuration WHERE id=1')->fetch(PDO::FETCH_ASSOC);
  if(!is_array($r))throw new RuntimeException('marketplace_configuration_missing');
  return ['revision'=>(int)$r['revision']]+self::normalize(Json::decode($r['definition_json']));
 }

 public function save(array $input,string $actor):array
 {
  $revision=filter_var($input['expected_revision']??null,FILTER_VALIDATE_INT);
  if($revision===false||$revision<1)throw new InvalidArgumentException('نسخه تنظیمات بازار معتبر نیست.');
  $definition=self::normalize($input);
  $this->pdo->beginTransaction();
  try{
   $s=$this->pdo->prepare('UPDATE marketplace_configuration SET definition_json=:definition,revision=revision+1,updated_by_user_id=:actor WHERE id=1 AND revision=:revision');
   $s->execute(['definition'=>Json::encode($definition),'actor'=>$actor,'revision'=>$revision]);
   if($s->rowCount()!==1)throw new RuntimeException('marketplace_revision_conflict');
   $s=$this->pdo->prepare("INSERT INTO partner_events(id,aggregate_type,aggregate_id,event_type,actor_user_id,result,details_json) VALUES(:id,'marketplace',NULL,'marketplace.configuration.updated',:actor,'allowed',:details)");
   $s->execute(['id'=>bin2hex(random_bytes(13)),'actor'=>$actor,'details'=>Json::encode(['revision'=>$revision+1,'definition'=>$definition])]);
   $this->pdo->commit();
  }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
  return $this->current();
 }

 public static function normalize(array $input):array
 {
  $out=[];
  foreach(['enabled'=>true,'require_category'=>true,'require_location'=>true,'allow_all_country'=>true]as$key=>$default){
   $v=$input[$key]??$default;$flag=filter_var($v,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE);
   if($flag===null)throw new InvalidArgumentException('تنظیم روشن/خاموش بازار معتبر نیست.');
   $out[$key]=$flag;
  }
  foreach(['categories','locations']as$kind){
   $rows=$input[$kind]??[];if(!is_array($rows)||!array_is_list($rows)||count($rows)>2500)throw new InvalidArgumentException('فهرست دسته‌ها یا موقعیت‌ها معتبر نیست.');
   $seen=[];$terms=[];
   foreach($rows as$row){
    if(!is_array($row))throw new InvalidArgumentException('مشخصات دسته یا موقعیت معتبر نیست.');
    $id=trim((string)($row['id']??''));$label=trim((string)($row['label']??''));$parent=trim((string)($row['parent_id']??''));
    if(!preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D',$id)||isset($seen[$id])||$label===''||mb_strlen($label)>120)throw new InvalidArgumentException('نام یا شناسه دسته و موقعیت معتبر یا یکتا نیست.');
    $active=filter_var($row['active']??true,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE);
    if($active===null)throw new InvalidArgumentException('وضعیت دسته یا موقعیت معتبر نیست.');
    if($kind==='categories')$parent='';
    $seen[$id]=true;$terms[$id]=['id'=>$id,'label'=>$label,'parent_id'=>$parent,'active'=>$active];
   }
   foreach($terms as$id=>$term){
    $path=[$id=>true];$parent=$term['parent_id'];
    while($parent!==''){
     if(!isset($terms[$parent])||isset($path[$parent]))throw new InvalidArgumentException('درخت موقعیت‌ها والد نامعتبر یا چرخه دارد.');
     $path[$parent]=true;$parent=$terms[$parent]['parent_id'];
    }
   }
   $out[$kind]=array_values($terms);
  }
  return $out;
 }

 public static function profile(array $input,array $catalog):array
 {
  $enabled=filter_var($input['enabled']??true,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE);
  $amount=$input['max_facility_amount']??0;
  if($enabled===null||!is_numeric($amount)||!is_finite((float)$amount)||(float)$amount<0||(float)$amount>999999999999999)throw new InvalidArgumentException('وضعیت یا سقف مبلغ پذیرنده معتبر نیست.');
  $out=['enabled'=>$enabled,'max_facility_amount'=>(float)$amount];
  foreach(['category_ids'=>'categories','location_ids'=>'locations']as$key=>$kind){
   $raw=$input[$key]??[];if(!is_array($raw)||count($raw)>50)throw new InvalidArgumentException('حداکثر ۵۰ دسته یا موقعیت انتخاب کنید.');
   $valid=array_column($catalog[$kind],'id');$ids=[];
   foreach($raw as$id){if(!is_string($id)||!in_array($id,$valid,true))throw new InvalidArgumentException('دسته یا موقعیت انتخاب‌شده در فهرست بازار وجود ندارد.');$ids[$id]=true;}
   $out[$key]=array_keys($ids);
  }
  return $out;
 }

 /** WordPress expands each selected location to itself, ancestors and descendants. */
 public static function expandLocations(array $ids,array $terms):array
 {
  $parents=[];foreach($terms as$t)if($t['active'])$parents[$t['id']]=$t['parent_id'];
  $expanded=[];
  foreach($ids as$id){
   if(!isset($parents[$id]))continue;$expanded[$id]=true;
   $parent=$parents[$id];$seen=[];
   while($parent!==''&&isset($parents[$parent])&&!isset($seen[$parent])){$seen[$parent]=true;$expanded[$parent]=true;$parent=$parents[$parent];}
   foreach($parents as$child=>$p){$seen=[];while($p!==''&&isset($parents[$p])&&!isset($seen[$p])){if($p===$id){$expanded[$child]=true;break;}$seen[$p]=true;$p=$parents[$p];}}
  }
  return array_keys($expanded);
 }

 public function context(?array $merchant,array $permissions):array
 {
  $catalog=$this->current();$profile=is_array($merchant['metadata']['marketplace']??null)?$merchant['metadata']['marketplace']:[];
  $categories=array_column(array_filter($catalog['categories'],static fn($t)=>$t['active']),'id');
  $matching=self::matchingLocations((array)($profile['location_ids']??[]),$catalog['locations']);
  return ['enabled'=>$catalog['enabled']&&is_array($merchant)&&$merchant['status']==='active'&&$merchant['cooperation_status']==='approved'&&($profile['enabled']??true)===true,
   'merchant_id'=>$merchant['id']??null,'require_category'=>$catalog['require_category'],'require_location'=>$catalog['require_location'],
   'allow_all_country'=>$catalog['allow_all_country'],'category_ids'=>array_values(array_intersect($categories,(array)($profile['category_ids']??[]))),
   'location_ids'=>$matching,'max_facility_amount'=>(float)($profile['max_facility_amount']??0),'plan_permissions'=>$permissions];
 }

 public static function matchingLocations(array $ids,array $terms):array
 {
  // Expanding both sides to ancestors means siblings match through their common root.
  // Cache roots rather than expanding every pair of nodes in a large city catalogue.
  $parents=[];foreach($terms as$t)if($t['active'])$parents[$t['id']]=$t['parent_id'];
  $cache=[];
  $root=static function(string $id)use($parents,&$cache):?string{
   if(!isset($parents[$id]))return null;$path=[];$cursor=$id;
   while(!isset($cache[$cursor])){
    if(isset($path[$cursor]))throw new InvalidArgumentException('درخت موقعیت‌ها چرخه دارد.');
    $path[$cursor]=true;$parent=$parents[$cursor];
    if($parent===''||!isset($parents[$parent]))break;$cursor=$parent;
   }
   $result=$cache[$cursor]??$cursor;foreach($path as$key=>$_)$cache[$key]=$result;return $result;
  };
  $roots=[];foreach($ids as$id){$r=$root((string)$id);if($r!==null)$roots[$r]=true;}
  $matching=[];foreach($parents as$id=>$_)if(isset($roots[$root((string)$id)]))$matching[]=(string)$id;
  return $matching;
 }
}
