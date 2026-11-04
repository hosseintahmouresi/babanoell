<?php
declare(strict_types=1);
use Babanoell\Auth\Database\Migration;
return new class implements Migration {
 public function version(): string { return '202609260003'; }
 public function name(): string { return 'seed_production_authorization_matrix'; }
 public function up(PDO $pdo): void {
  $permissions=[
   'identity.users.read'=>'مشاهده کاربران سازمانی','identity.users.manage'=>'ایجاد و مدیریت کاربران سازمانی','identity.roles.read'=>'مشاهده نقش‌ها و مجوزها','identity.memberships.read'=>'مشاهده دامنه دسترسی کاربران','identity.memberships.manage'=>'مدیریت عضویت و دامنه دسترسی کاربران',
   'loans.plans.read'=>'مشاهده طرح‌های تسهیلات','loans.plans.manage'=>'مدیریت طرح‌های تسهیلات','loans.calculate'=>'محاسبه تسهیلات','loans.requests.read'=>'مشاهده پرونده‌های مجاز','loans.requests.create'=>'ایجاد پرونده','loans.requests.update'=>'ویرایش پرونده مجاز','loans.requests.transition'=>'گردش وضعیت پرونده','loans.requests.audit'=>'مشاهده تاریخچه پرونده','loans.requests.global'=>'مشاهده سراسری پرونده‌ها','loans.requests.assign_bank'=>'ارجاع پرونده به کارشناس بانک','loans.requests.bank_review'=>'عملیات تخصصی کارشناس بانک','loans.requests.operations'=>'عملیات اجرایی بابانوئل','loans.requests.finance'=>'عملیات مالی بابانوئل','loans.requests.contract'=>'عملیات قرارداد','loans.requests.bank_settlement'=>'ثبت و مشاهده تسویه بانک با بابانوئل','loans.requests.merchant_settlement'=>'ثبت و مشاهده تسویه پذیرنده','loans.requests.cooperation'=>'مدیریت درخواست همکاری پذیرندگان','loans.requests.marketplace'=>'دسترسی بازار درخواست‌های متقاضی',
   'documents.view'=>'مشاهده مدارک','documents.upload'=>'بارگذاری مدارک','documents.download'=>'دریافت مدارک','documents.review'=>'بررسی مدارک','documents.replace'=>'جایگزینی مدارک','documents.archive'=>'بایگانی مدارک','documents.audit'=>'مشاهده تاریخچه مدارک','documents.manage'=>'مدیریت سراسری مدارک'
  ];
  $findP=$pdo->prepare('SELECT id FROM permissions WHERE name=:name LIMIT 1');
  $insP=$pdo->prepare('INSERT INTO permissions (id,name,description) VALUES (:id,:name,:description)');
  $updP=$pdo->prepare('UPDATE permissions SET description=:description WHERE id=:id');
  foreach($permissions as$name=>$description){$findP->execute([':name'=>$name]);$id=$findP->fetchColumn();if(!is_string($id)||$id===''){$id=bin2hex(random_bytes(13));$insP->execute([':id'=>$id,':name'=>$name,':description'=>$description]);}else{$updP->execute([':id'=>$id,':description'=>$description]);}}
  $roles=[
   'super_admin'=>['مدیر ارشد سامانه','مالک و مدیر ارشد پلتفرم.',array_keys($permissions)],
   'admin'=>['مدیر سامانه','مدیریت عملیاتی سامانه و کاربران غیرسیستمی.',array_values(array_filter(array_keys($permissions),static fn($p)=>!in_array($p,['identity.memberships.manage'],true)))],
   'operations_expert'=>['کارشناس عملیات بابانوئل','مدیریت گردش اجرایی و ارجاع پرونده‌ها.',['loans.plans.read','loans.requests.read','loans.requests.update','loans.requests.transition','loans.requests.audit','loans.requests.global','loans.requests.assign_bank','loans.requests.operations','loans.requests.cooperation','documents.view','documents.download','documents.review','documents.audit']],
   'finance_expert'=>['کارشناس مالی بابانوئل','عملیات مالی و تسویه بانک و پذیرنده.',['loans.plans.read','loans.requests.read','loans.requests.audit','loans.requests.global','loans.requests.finance','loans.requests.bank_settlement','loans.requests.merchant_settlement','documents.view','documents.download','documents.audit']],
   'contract_expert'=>['کارشناس قرارداد بابانوئل','دسترسی پرونده و مدارک موردنیاز قرارداد.',['loans.plans.read','loans.requests.read','loans.requests.audit','loans.requests.global','loans.requests.contract','documents.view','documents.download','documents.audit']],
   'bank_expert'=>['کارشناس بانک','دسترسی فقط به پرونده‌های ارجاع‌شده و عملیات بانکی آن‌ها.',['loans.plans.read','loans.requests.read','loans.requests.transition','loans.requests.audit','loans.requests.bank_review','loans.requests.bank_settlement','documents.view','documents.download','documents.review','documents.audit']],
   'acceptor'=>['پذیرنده','ثبت و پیگیری پرونده‌های مرتبط با پذیرنده.',['loans.plans.read','loans.calculate','loans.requests.read','loans.requests.create','loans.requests.update','loans.requests.audit','loans.requests.marketplace','documents.view','documents.upload','documents.download','documents.replace']],
   'applicant'=>['متقاضی تسهیلات','ثبت و پیگیری پرونده شخصی.',['loans.plans.read','loans.calculate','loans.requests.read','loans.requests.create','loans.requests.update','documents.view','documents.upload','documents.download','documents.replace']]
  ];
  $findR=$pdo->prepare('SELECT id FROM roles WHERE name=:name LIMIT 1');$insR=$pdo->prepare('INSERT INTO roles (id,name,label,description,is_system) VALUES (:id,:name,:label,:description,1)');$updR=$pdo->prepare('UPDATE roles SET label=:label,description=:description,is_system=1 WHERE id=:id');
  $pid=$pdo->prepare('SELECT id FROM permissions WHERE name=:name LIMIT 1');$clear=$pdo->prepare("DELETE rp FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=:role_id AND (p.name LIKE 'identity.%' OR p.name LIKE 'loans.%' OR p.name LIKE 'documents.%')");$assign=$pdo->prepare('INSERT IGNORE INTO role_permissions (role_id,permission_id,created_by_user_id) VALUES (:role_id,:permission_id,NULL)');
  foreach($roles as$name=>$r){$findR->execute([':name'=>$name]);$rid=$findR->fetchColumn();if(!is_string($rid)||$rid===''){$rid=bin2hex(random_bytes(13));$insR->execute([':id'=>$rid,':name'=>$name,':label'=>$r[0],':description'=>$r[1]]);}else{$updR->execute([':id'=>$rid,':label'=>$r[0],':description'=>$r[1]]);} $clear->execute([':role_id'=>$rid]);foreach($r[2] as$p){$pid->execute([':name'=>$p]);$x=$pid->fetchColumn();if(!is_string($x)||$x==='')throw new RuntimeException('Missing permission '.$p);$assign->execute([':role_id'=>$rid,':permission_id'=>$x]);}}
 }
};
