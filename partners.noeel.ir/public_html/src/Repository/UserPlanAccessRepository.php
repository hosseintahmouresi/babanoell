<?php
declare(strict_types=1);
namespace Babanoell\Partners\Repository;

final class UserPlanAccessRepository
{
 public function __construct(private readonly \PDO $pdo){}
 public static function appliesTo(array $roles):bool{return in_array('acceptor',$roles,true)&&!array_intersect(['super_admin','admin','operations_expert','finance_expert'],$roles);}
 public function get(string $user):array {
  $q=$this->pdo->prepare('SELECT disabled_plan_ids_json,revision,updated_at FROM partner_user_plan_exclusions WHERE user_id=:id');$q->execute([':id'=>$user]);$r=$q->fetch(\PDO::FETCH_ASSOC);
  return ['disabled_plan_ids'=>is_array($r)?json_decode($r['disabled_plan_ids_json'],true,512,JSON_THROW_ON_ERROR):[],'revision'=>is_array($r)?(int)$r['revision']:0,'updated_at'=>$r['updated_at']??null];
 }
 public static function ids(mixed $input):array {
  if(!is_array($input)||count($input)>1000)throw new \InvalidArgumentException('فهرست طرح‌های غیرفعال معتبر نیست.');$out=[];
  foreach($input as $id){if(!is_string($id)||!preg_match('/^[a-f0-9]{26}$/D',$id))throw new \InvalidArgumentException('شناسهٔ طرح معتبر نیست.');$out[$id]=true;}return array_keys($out);
 }
 public function save(string $user,string $actor,int $revision,array $ids):array {
  $ids=self::ids($ids);sort($ids,SORT_STRING);$this->pdo->beginTransaction();
  try{
   $q=$this->pdo->prepare("INSERT IGNORE INTO partner_user_plan_exclusions(user_id,disabled_plan_ids_json,revision,updated_by,updated_at) VALUES(:id,'[]',0,:actor,UTC_TIMESTAMP(6))");$q->execute([':id'=>$user,':actor'=>$actor]);
   $q=$this->pdo->prepare('SELECT revision FROM partner_user_plan_exclusions WHERE user_id=:id FOR UPDATE');$q->execute([':id'=>$user]);$old=$q->fetchColumn();
   if((int)$old!==$revision)throw new \DomainException('تنظیم طرح‌ها هم‌زمان تغییر کرده است؛ صفحه را تازه کنید.');
   $json=json_encode($ids,JSON_THROW_ON_ERROR);$q=$this->pdo->prepare('UPDATE partner_user_plan_exclusions SET disabled_plan_ids_json=:ids,revision=revision+1,updated_by=:actor,updated_at=UTC_TIMESTAMP(6) WHERE user_id=:id');
   $q->execute([':id'=>$user,':ids'=>$json,':actor'=>$actor]);(new PdoPartnerRepository($this->pdo))->event('user_plan_access',$user,'user.plan_exclusions.updated',$actor,'allowed',['disabled_plan_ids'=>$ids,'revision'=>$revision+1]);$this->pdo->commit();return $this->get($user);
  }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();if($e instanceof \PDOException&&(string)$e->getCode()==='23000')throw new \DomainException('تنظیم طرح‌ها هم‌زمان تغییر کرده است؛ صفحه را تازه کنید.');throw $e;}
 }
}
