<?php
declare(strict_types=1);
use Babanoell\Auth\Database\Migration;
return new class implements Migration{
 public function version():string{return'202609290005';}public function name():string{return'seed_partners_permissions_and_cooperation_role';}
 public function up(PDO$pdo):void{
  $permissions=[
   'partners.merchants.read'=>'مشاهده پذیرندگان مجاز','partners.merchants.manage'=>'ایجاد و مدیریت پذیرندگان','partners.memberships.manage'=>'مدیریت کاربران و عضویت‌های پذیرنده','partners.plan_access.manage'=>'مدیریت دسترسی پذیرنده به طرح‌ها','partners.directory.read'=>'جستجوی فهرست پذیرندگان فعال',
   'partners.cooperation.read'=>'مشاهده درخواست‌های همکاری مجاز','partners.cooperation.create'=>'ثبت درخواست همکاری','partners.cooperation.manage'=>'بررسی و گردش درخواست‌های همکاری',
   'partners.marketplace.read'=>'مشاهده بازار پرونده‌های متقاضی','partners.marketplace.claim'=>'پذیرش پرونده آزاد توسط پذیرنده','partners.marketplace.manage'=>'مدیریت بازار و دعوت پذیرندگان','partners.audit'=>'مشاهده تاریخچه عملیاتی شرکا'
  ];
  $findP=$pdo->prepare('SELECT id FROM permissions WHERE name=:name LIMIT 1');$insP=$pdo->prepare('INSERT INTO permissions(id,name,description) VALUES(:id,:name,:description)');$updP=$pdo->prepare('UPDATE permissions SET description=:description WHERE id=:id');
  foreach($permissions as$name=>$desc){$findP->execute([':name'=>$name]);$id=$findP->fetchColumn();if(!is_string($id)||$id===''){$id=bin2hex(random_bytes(13));$insP->execute([':id'=>$id,':name'=>$name,':description'=>$desc]);}else{$updP->execute([':id'=>$id,':description'=>$desc]);}}
  $findR=$pdo->prepare('SELECT id FROM roles WHERE name=:name LIMIT 1');$insR=$pdo->prepare('INSERT INTO roles(id,name,label,description,is_system) VALUES(:id,:name,:label,:description,1)');$updR=$pdo->prepare('UPDATE roles SET label=:label,description=:description,is_system=1 WHERE id=:id');
  $findR->execute([':name'=>'cooperation_operator']);$coopRole=$findR->fetchColumn();if(!is_string($coopRole)||$coopRole===''){$coopRole=bin2hex(random_bytes(13));$insR->execute([':id'=>$coopRole,':name'=>'cooperation_operator',':label'=>'کارشناس همکاری پذیرندگان',':description'=>'بررسی درخواست‌های همکاری، پذیرندگان و بازار پرونده‌ها.']);}else{$updR->execute([':id'=>$coopRole,':label'=>'کارشناس همکاری پذیرندگان',':description'=>'بررسی درخواست‌های همکاری، پذیرندگان و بازار پرونده‌ها.']);}
  $roleMap=[
   'super_admin'=>array_keys($permissions),'admin'=>array_keys($permissions),
   'operations_expert'=>['partners.merchants.read','partners.merchants.manage','partners.plan_access.manage','partners.directory.read','partners.cooperation.read','partners.cooperation.manage','partners.marketplace.read','partners.marketplace.manage','partners.audit'],
   'cooperation_operator'=>['partners.merchants.read','partners.merchants.manage','partners.plan_access.manage','partners.directory.read','partners.cooperation.read','partners.cooperation.create','partners.cooperation.manage','partners.marketplace.read','partners.marketplace.manage','partners.audit','documents.view','documents.download','documents.review','documents.audit'],
   'finance_expert'=>['partners.merchants.read','partners.directory.read'],
   'contract_expert'=>['partners.merchants.read','partners.cooperation.read'],
   'acceptor'=>['partners.merchants.read','partners.directory.read','partners.cooperation.read','partners.cooperation.create','partners.marketplace.read','partners.marketplace.claim'],
   'applicant'=>['partners.directory.read','partners.cooperation.read','partners.cooperation.create']
  ];
  $assign=$pdo->prepare('INSERT IGNORE INTO role_permissions(role_id,permission_id,created_by_user_id) VALUES(:r,:p,NULL)');
  foreach($roleMap as$role=>$perms){$findR->execute([':name'=>$role]);$rid=$findR->fetchColumn();if(!is_string($rid)||$rid==='')continue;foreach($perms as$p){$findP->execute([':name'=>$p]);$pid=$findP->fetchColumn();if(!is_string($pid)||$pid==='')throw new RuntimeException('Missing permission '.$p);$assign->execute([':r'=>$rid,':p'=>$pid]);}}
 }
};
