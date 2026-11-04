<?php
declare(strict_types=1);
namespace Babanoell\Finance\Repository;
use Babanoell\Finance\Domain\CycleIntegrity;use Babanoell\Finance\Platform\RequestContext;use Babanoell\Finance\Support\Json;use Babanoell\Finance\Support\RandomId;use PDO;use RuntimeException;use Throwable;
final class PdoFinanceRepository
{
 public function __construct(private readonly PDO$pdo){}
 public function pdo():PDO{return$this->pdo;}
 public function stats():array{return[
  'bank_cycles'=>(int)$this->pdo->query('SELECT COUNT(*) FROM bank_settlement_cycles')->fetchColumn(),
  'bank_recorded'=>(int)$this->pdo->query("SELECT COUNT(*) FROM bank_settlements WHERE status='recorded'")->fetchColumn(),
  'merchant_pending'=>(int)$this->pdo->query("SELECT COUNT(*) FROM merchant_settlements WHERE status IN ('awaiting_date','pending')")->fetchColumn(),
  'open_cycles'=>(int)$this->pdo->query("SELECT COUNT(*) FROM settlement_cycles WHERE status='open'")->fetchColumn(),
  'pending_receipts'=>(int)$this->pdo->query("SELECT COUNT(*) FROM financial_receipts WHERE status='pending'")->fetchColumn(),
  'open_discrepancies'=>(int)$this->pdo->query("SELECT COUNT(*) FROM finance_reconciliation_items WHERE status='open'")->fetchColumn(),
 ];}
 public function snapshot(string$requestId):array{return[
  'bank_settlement'=>$this->one('SELECT * FROM bank_settlements WHERE request_id=:id',[':id'=>$requestId]),
  'merchant_settlement'=>$this->one('SELECT * FROM merchant_settlements WHERE request_id=:id',[':id'=>$requestId]),
  'financier_payment'=>$this->one('SELECT * FROM financier_payments WHERE request_id=:id',[':id'=>$requestId]),
  'receipts'=>$this->all('SELECT * FROM financial_receipts WHERE request_id=:id ORDER BY submitted_at DESC',[':id'=>$requestId]),
 ];}
 public function bankSettlementHistory(string$requestId):array{return$this->all('SELECT * FROM bank_settlement_history WHERE request_id=:id ORDER BY created_at DESC,id DESC',[':id'=>$requestId]);}
 public function merchantSettlementHistory(string$requestId):array{return$this->all('SELECT * FROM merchant_settlement_history WHERE request_id=:id ORDER BY created_at DESC,id DESC',[':id'=>$requestId]);}
 public function financierPaymentHistory(string$requestId):array{return$this->all('SELECT * FROM financier_payment_history WHERE request_id=:id ORDER BY created_at DESC,id DESC',[':id'=>$requestId]);}
 public function recordBankSettlement(string$requestId,string$status,?string$settledAt,string$note,string$actor,string$source='finance'):array{
  $this->pdo->beginTransaction();try{$existing=$this->lockOne('SELECT * FROM bank_settlements WHERE request_id=:id FOR UPDATE',[':id'=>$requestId]);$member=$this->lockOne('SELECT request_id FROM bank_settlement_cycle_items WHERE request_id=:id FOR UPDATE',[':id'=>$requestId]);if($member!==null)throw new RuntimeException('پرونده عضو سیکل ثبت‌شده بانک است و تسویه آن از این مسیر قابل تغییر نیست.');$id=is_array($existing)?(string)$existing['id']:RandomId::make();if($status==='recorded'&&$settledAt===null)throw new RuntimeException('تاریخ تسویه بانک الزامی است.');if($existing===null){$s=$this->pdo->prepare('INSERT INTO bank_settlements(id,request_id,status,settled_at,note,actor_user_id,source) VALUES(:id,:request,:status,:at,:note,:actor,:source)');}else{$s=$this->pdo->prepare('UPDATE bank_settlements SET status=:status,settled_at=:at,note=:note,actor_user_id=:actor,source=:source,row_version=row_version+1 WHERE id=:id');}$s->execute([':id'=>$id,':request'=>$requestId,':status'=>$status,':at'=>$settledAt,':note'=>$note!==''?$note:null,':actor'=>$actor,':source'=>$source]);$h=$this->pdo->prepare('INSERT INTO bank_settlement_history(id,request_id,status,settled_at,note,actor_user_id,source) VALUES(:id,:request,:status,:at,:note,:actor,:source)');$h->execute([':id'=>RandomId::make(),':request'=>$requestId,':status'=>$status,':at'=>$settledAt,':note'=>$note!==''?$note:null,':actor'=>$actor,':source'=>$source]);$this->event('bank_settlement.updated','loan_request',$requestId,$actor,['status'=>$status,'settled_at'=>$settledAt,'source'=>$source]);$this->pdo->commit();return$this->one('SELECT * FROM bank_settlements WHERE id=:id',[':id'=>$id])??[];}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}}
 public function recordMerchantSettlement(string$requestId,?string$merchantId,string$status,?string$date,?string$cycleId,string$note,string$actor,string$source='finance'):array{
  $this->pdo->beginTransaction();try{$existing=$this->lockOne('SELECT * FROM merchant_settlements WHERE request_id=:id FOR UPDATE',[':id'=>$requestId]);$id=is_array($existing)?(string)$existing['id']:RandomId::make();$membership=$this->lockOne('SELECT cycle_id FROM settlement_cycle_items WHERE request_id=:r FOR UPDATE',[':r'=>$requestId]);if($membership!==null||($existing!==null&&!empty($existing['cycle_id']))){if($existing===null||$status!==(string)$existing['status']||$date!==($existing['settlement_date']??null)||$cycleId!==($existing['cycle_id']??null))throw new RuntimeException('پرونده عضو سیکل است؛ تغییر وضعیت یا تاریخ تسویه بدون روال اصلاح سیکل ممنوع است.');}if($status==='settled'&&($date===null||$date===''))throw new RuntimeException('تاریخ تسویه پذیرنده الزامی است.');if($cycleId!==null){$c=$this->lockOne('SELECT id,status,merchant_id FROM settlement_cycles WHERE id=:id FOR UPDATE',[':id'=>$cycleId]);if($c===null)throw new RuntimeException('سیکل تسویه پیدا نشد.');if((string)$c['status']!=='open')throw new RuntimeException('سیکل بسته قابل تغییر نیست.');if($merchantId!==null&&(string)$c['merchant_id']!==$merchantId)throw new RuntimeException('پذیرنده پرونده با سیکل تسویه مطابقت ندارد.');}
   if($existing===null){$s=$this->pdo->prepare('INSERT INTO merchant_settlements(id,request_id,merchant_id,status,settlement_date,cycle_id,note,actor_user_id,source) VALUES(:id,:request,:merchant,:status,:date,:cycle,:note,:actor,:source)');}else{$s=$this->pdo->prepare('UPDATE merchant_settlements SET merchant_id=:merchant,status=:status,settlement_date=:date,cycle_id=:cycle,note=:note,actor_user_id=:actor,source=:source,row_version=row_version+1 WHERE id=:id');}$s->execute([':id'=>$id,':request'=>$requestId,':merchant'=>$merchantId,':status'=>$status,':date'=>$date,':cycle'=>$cycleId,':note'=>$note!==''?$note:null,':actor'=>$actor,':source'=>$source]);$h=$this->pdo->prepare('INSERT INTO merchant_settlement_history(id,request_id,merchant_id,status,settlement_date,cycle_id,note,actor_user_id,source) VALUES(:id,:request,:merchant,:status,:date,:cycle,:note,:actor,:source)');$h->execute([':id'=>RandomId::make(),':request'=>$requestId,':merchant'=>$merchantId,':status'=>$status,':date'=>$date,':cycle'=>$cycleId,':note'=>$note!==''?$note:null,':actor'=>$actor,':source'=>$source]);$this->event('merchant_settlement.updated','loan_request',$requestId,$actor,['status'=>$status,'settlement_date'=>$date,'cycle_id'=>$cycleId,'source'=>$source]);$this->pdo->commit();return$this->one('SELECT * FROM merchant_settlements WHERE id=:id',[':id'=>$id])??[];}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}}
 public function recordFinancierPayment(string$requestId,string$status,string$note,string$actor,string$source='finance'):array{$this->pdo->beginTransaction();try{$existing=$this->lockOne('SELECT * FROM financier_payments WHERE request_id=:id FOR UPDATE',[':id'=>$requestId]);$id=is_array($existing)?(string)$existing['id']:RandomId::make();if($existing===null){$s=$this->pdo->prepare('INSERT INTO financier_payments(id,request_id,status,note,actor_user_id,source) VALUES(:id,:request,:status,:note,:actor,:source)');}else{$s=$this->pdo->prepare('UPDATE financier_payments SET status=:status,note=:note,actor_user_id=:actor,source=:source,row_version=row_version+1 WHERE id=:id');}$s->execute([':id'=>$id,':request'=>$requestId,':status'=>$status,':note'=>$note!==''?$note:null,':actor'=>$actor,':source'=>$source]);$h=$this->pdo->prepare('INSERT INTO financier_payment_history(id,request_id,status,note,actor_user_id,source) VALUES(:id,:request,:status,:note,:actor,:source)');$h->execute([':id'=>RandomId::make(),':request'=>$requestId,':status'=>$status,':note'=>$note!==''?$note:null,':actor'=>$actor,':source'=>$source]);$this->event('financier_payment.updated','loan_request',$requestId,$actor,['status'=>$status,'source'=>$source]);$this->pdo->commit();return$this->one('SELECT * FROM financier_payments WHERE id=:id',[':id'=>$id])??[];}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}}
 public function listCycles(int$page,int$perPage,?string$status=null,?string$merchantId=null):array{$where=[];$p=[];if($status!==null&&$status!==''){$where[]='status=:status';$p[':status']=$status;}if($merchantId!==null&&$merchantId!==''){$where[]='merchant_id=:merchant';$p[':merchant']=$merchantId;}$w=$where?' WHERE '.implode(' AND ',$where):'';$c=$this->pdo->prepare('SELECT COUNT(*) FROM settlement_cycles'.$w);$c->execute($p);$total=(int)$c->fetchColumn();$q=$this->pdo->prepare('SELECT * FROM settlement_cycles'.$w.' ORDER BY created_at DESC LIMIT :lim OFFSET :off');foreach($p as$k=>$v)$q->bindValue($k,$v);$q->bindValue(':lim',$perPage,PDO::PARAM_INT);$q->bindValue(':off',($page-1)*$perPage,PDO::PARAM_INT);$q->execute();return['items'=>$q->fetchAll(),'page'=>$page,'per_page'=>$perPage,'total'=>$total];}
 public function cycle(string$id):?array{$c=$this->one('SELECT * FROM settlement_cycles WHERE id=:id',[':id'=>$id]);if($c===null)return null;$c['items']=$this->all('SELECT * FROM settlement_cycle_items WHERE cycle_id=:id ORDER BY created_at ASC',[':id'=>$id]);$c['receipts']=$this->all('SELECT * FROM financial_receipts WHERE cycle_id=:id ORDER BY submitted_at DESC',[':id'=>$id]);return$c;}
 public function createCycle(string$merchantId,?string$date,string$description,string$actor,string$key=''):array{
  $fingerprint=hash('sha256',Json::encode([$merchantId,$date,$description]));
  $this->pdo->beginTransaction();
  try{
   $previous=$key!==''?$this->claimOperation($key,'finance.cycle.create',$actor,$fingerprint):null;
   if($previous!==null){$this->pdo->commit();return$previous;}
   $id=RandomId::make();$no='SC-'.gmdate('Ymd-His').'-'.strtoupper(substr($id,-6));
   $stmt=$this->pdo->prepare('INSERT INTO settlement_cycles(id,cycle_no,merchant_id,settlement_date,description,created_by_user_id) VALUES(:id,:no,:merchant,:date,:description,:actor)');
   $stmt->execute([':id'=>$id,':no'=>$no,':merchant'=>$merchantId,':date'=>$date,':description'=>$description!==''?$description:null,':actor'=>$actor]);
   $this->event('settlement_cycle.created','settlement_cycle',$id,$actor,['merchant_id'=>$merchantId]);
   $result=$this->cycle($id)??[];
   if($key!=='')$this->finishOperation($key,'finance.cycle.create',$fingerprint,$result);
   $this->pdo->commit();return$result;
  }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
 }
 public function addCycleItem(string$cycleId,string$requestId,float$settlement,float$leasing,float$merchant,string$actor,string$loanMerchant,string$key=''):array{
  CycleIntegrity::validateItem($settlement,$leasing,$merchant);
  $fingerprint=hash('sha256',Json::encode([$cycleId,$requestId,$settlement,$leasing,$merchant,$loanMerchant]));
  $this->pdo->beginTransaction();
  try{
   $previous=$key!==''?$this->claimOperation($key,'finance.cycle.item.add',$actor,$fingerprint):null;
   if($previous!==null){$this->pdo->commit();return$previous;}
   $cycle=$this->lockOne('SELECT * FROM settlement_cycles WHERE id=:id FOR UPDATE',[':id'=>$cycleId]);
   if($cycle===null)throw new RuntimeException('سیکل تسویه پیدا نشد.');
   if((string)$cycle['status']!=='open')throw new RuntimeException('سیکل بسته قابل تغییر نیست.');
   if($loanMerchant===''||(string)$cycle['merchant_id']!==$loanMerchant)throw new RuntimeException('پذیرنده پرونده با پذیرنده سیکل مطابقت ندارد.');
   $linkedSettlement=$this->lockOne('SELECT id,cycle_id FROM merchant_settlements WHERE request_id=:r FOR UPDATE',[':r'=>$requestId]);
   if($linkedSettlement!==null&&!empty($linkedSettlement['cycle_id']))throw new RuntimeException('پرونده از قبل به سیکل تسویه پذیرنده متصل است.');
   $existing=$this->lockOne('SELECT * FROM settlement_cycle_items WHERE request_id=:r FOR UPDATE',[':r'=>$requestId]);
   if($existing!==null)throw new RuntimeException('این پرونده قبلاً به یک سیکل تسویه متصل شده است.');
   $insert=$this->pdo->prepare('INSERT INTO settlement_cycle_items(cycle_id,request_id,settlement_amount,leasing_amount,merchant_amount) VALUES(:c,:r,:s,:l,:m)');
   $insert->execute([':c'=>$cycleId,':r'=>$requestId,':s'=>$settlement,':l'=>$leasing,':m'=>$merchant]);
   $this->recalculateCycle($cycleId);
   $this->event('settlement_cycle.item_added','settlement_cycle',$cycleId,$actor,['request_id'=>$requestId,'settlement_amount'=>$settlement]);
   $result=$this->cycle($cycleId)??[];
   if($key!=='')$this->finishOperation($key,'finance.cycle.item.add',$fingerprint,$result);
   $this->pdo->commit();return$result;
  }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
 }
 public function closeCycle(string$id,string$actor):array{$this->pdo->beginTransaction();try{$c=$this->lockOne('SELECT * FROM settlement_cycles WHERE id=:id FOR UPDATE',[':id'=>$id]);if($c===null)throw new RuntimeException('سیکل تسویه پیدا نشد.');if((string)$c['status']==='closed')throw new RuntimeException('سیکل قبلاً بسته شده است.');$count=(int)$this->scalar('SELECT COUNT(*) FROM settlement_cycle_items WHERE cycle_id=:id',[':id'=>$id]);if($count<1)throw new RuntimeException('سیکل خالی قابل بستن نیست.');$items=$this->all('SELECT * FROM settlement_cycle_items WHERE cycle_id=:id FOR UPDATE',[':id'=>$id]);
 $receipts=$this->all('SELECT * FROM financial_receipts WHERE cycle_id=:id',[':id'=>$id]);
 $issues=CycleIntegrity::issues($c,$items,$receipts);
 foreach($issues as $issue)if($issue['severity']==='critical')throw new RuntimeException('سیکل دارای مغایرت مالی است؛ پیش از بستن، جمع مبالغ و اقلام را اصلاح کنید.');
 $this->recalculateCycle($id);$u=$this->pdo->prepare("UPDATE settlement_cycles SET status='closed',closed_by_user_id=:actor,closed_at=NOW(6),row_version=row_version+1 WHERE id=:id AND status='open'");$u->execute([':actor'=>$actor,':id'=>$id]);if($u->rowCount()!==1)throw new RuntimeException('سیکل هم‌زمان تغییر کرده است.');$this->event('settlement_cycle.closed','settlement_cycle',$id,$actor,[]);$this->pdo->commit();return$this->cycle($id)??[];}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}}
 public function reopenCycle(string$id,string$actor,string$reason):array{if(trim($reason)==='')throw new RuntimeException('دلیل بازگشایی الزامی است.');$this->pdo->beginTransaction();try{$c=$this->lockOne('SELECT * FROM settlement_cycles WHERE id=:id FOR UPDATE',[':id'=>$id]);if($c===null)throw new RuntimeException('سیکل تسویه پیدا نشد.');if((string)$c['status']!=='closed')throw new RuntimeException('فقط سیکل بسته قابل بازگشایی است.');if((int)$this->scalar('SELECT COUNT(*) FROM finance_greenpay_transfers WHERE cycle_id=:id',[':id'=>$id])>0)throw new RuntimeException('سیکل دارای درخواست پرداخت GreenPay است؛ بازگشایی آن مجاز نیست.');$u=$this->pdo->prepare("UPDATE settlement_cycles SET status='open',reopened_by_user_id=:actor,reopened_at=NOW(6),closed_by_user_id=NULL,closed_at=NULL,row_version=row_version+1 WHERE id=:id AND status='closed'");$u->execute([':actor'=>$actor,':id'=>$id]);if($u->rowCount()!==1)throw new RuntimeException('سیکل هم‌زمان تغییر کرده است.');$this->event('settlement_cycle.reopened','settlement_cycle',$id,$actor,['reason'=>$reason]);$this->pdo->commit();return$this->cycle($id)??[];}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}}
 public function createReceipt(?string$requestId,?string$cycleId,string$type,?float$amount,string$documentId,string$external,string$actor,string$key=''):array{
  $fingerprint=hash('sha256',Json::encode([$requestId,$cycleId,$type,$amount,$documentId,$external]));
  $this->pdo->beginTransaction();
  try{
   $previous=$key!==''?$this->claimOperation($key,'finance.receipt.create',$actor,$fingerprint):null;
   if($previous!==null){$this->pdo->commit();return$previous;}
   if($cycleId!==null){
    $cycle=$this->lockOne('SELECT id,status FROM settlement_cycles WHERE id=:id FOR UPDATE',[':id'=>$cycleId]);
    if($cycle===null)throw new RuntimeException('سیکل تسویه پیدا نشد.');
    if($requestId!==null){
     $member=$this->one('SELECT request_id FROM settlement_cycle_items WHERE cycle_id=:c AND request_id=:r',[':c'=>$cycleId,':r'=>$requestId]);
     if($member===null)throw new RuntimeException('رسید به پرونده‌ای خارج از سیکل نسبت داده شده است.');
    }
   }
   $existingDoc=$this->lockOne('SELECT id FROM financial_receipts WHERE document_id=:document FOR UPDATE',[':document'=>$documentId]);
   if($existingDoc!==null)throw new RuntimeException('این مدرک قبلاً به‌عنوان رسید مالی ثبت شده است.');
   $id=RandomId::make();
   $stmt=$this->pdo->prepare('INSERT INTO financial_receipts(id,request_id,cycle_id,receipt_type,amount,document_id,external_reference,submitted_by_user_id) VALUES(:id,:request,:cycle,:type,:amount,:document,:external,:actor)');
   $stmt->execute([':id'=>$id,':request'=>$requestId,':cycle'=>$cycleId,':type'=>$type,':amount'=>$amount,':document'=>$documentId,':external'=>$external!==''?$external:null,':actor'=>$actor]);
   $this->event('financial_receipt.created','financial_receipt',$id,$actor,['request_id'=>$requestId,'cycle_id'=>$cycleId,'document_id'=>$documentId]);
   $result=$this->one('SELECT * FROM financial_receipts WHERE id=:id',[':id'=>$id])??[];
   if($key!=='')$this->finishOperation($key,'finance.receipt.create',$fingerprint,$result);
   $this->pdo->commit();return$result;
  }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
 }
 public function reviewReceipt(string$id,string$status,string$note,string$actor):array{
  $this->pdo->beginTransaction();
  try{
   $old=$this->lockOne('SELECT * FROM financial_receipts WHERE id=:id FOR UPDATE',[':id'=>$id]);
   if($old===null)throw new RuntimeException('رسید پیدا نشد.');
   if((string)$old['status']!=='pending')throw new RuntimeException('رسید قبلاً بررسی شده و قابل تأیید مجدد نیست.');
   if((string)$old['submitted_by_user_id']===$actor)throw new RuntimeException('ثبت‌کننده رسید نمی‌تواند رسید خودش را تأیید یا رد کند.');
   if($status==='rejected'&&mb_strlen(trim($note))<10)throw new RuntimeException('برای رد رسید، دلیل حداقل ۱۰ کاراکتری لازم است.');
   $u=$this->pdo->prepare('UPDATE financial_receipts SET status=:status,reviewed_by_user_id=:actor,review_note=:note,reviewed_at=NOW(6) WHERE id=:id AND status=\'pending\'');
   $u->execute([':status'=>$status,':actor'=>$actor,':note'=>$note!==''?$note:null,':id'=>$id]);
   if($u->rowCount()!==1)throw new RuntimeException('وضعیت رسید همزمان تغییر کرده است.');
   $this->event('financial_receipt.reviewed','financial_receipt',$id,$actor,['before'=>'pending','after'=>$status,'note'=>$note]);
   $this->pdo->commit();
  }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
  return$this->one('SELECT * FROM financial_receipts WHERE id=:id',[':id'=>$id])??[];
 }
 public function listReceipts(int$page,int$perPage,string$status='pending'):array{
  if(!in_array($status,['pending','approved','rejected'],true))throw new \InvalidArgumentException('وضعیت رسید معتبر نیست.');
  $total=$this->scalar('SELECT COUNT(*) FROM financial_receipts WHERE status=:status',[':status'=>$status]);
  $q=$this->pdo->prepare('SELECT * FROM financial_receipts WHERE status=:status ORDER BY submitted_at DESC,id DESC LIMIT :lim OFFSET :off');
  $q->bindValue(':status',$status);$q->bindValue(':lim',$perPage,PDO::PARAM_INT);$q->bindValue(':off',($page-1)*$perPage,PDO::PARAM_INT);$q->execute();
  return ['items'=>$q->fetchAll(),'page'=>$page,'per_page'=>$perPage,'total'=>(int)$total];
 }
 public function listReconciliation(string$status='open',int$limit=100):array{$q=$this->pdo->prepare('SELECT * FROM finance_reconciliation_items WHERE status=:status ORDER BY created_at DESC LIMIT :lim');$q->bindValue(':status',$status);$q->bindValue(':lim',$limit,PDO::PARAM_INT);$q->execute();return$q->fetchAll();}
 public function resolveReconciliation(string$id,string$note,string$actor):array{
  if(mb_strlen(trim($note))<10||mb_strlen($note)>1000)throw new \InvalidArgumentException('برای رفع مغایرت دلیل ۱۰ تا ۱۰۰۰ کاراکتری الزامی است.');
  $this->pdo->beginTransaction();
  try{
   $old=$this->lockOne('SELECT * FROM finance_reconciliation_items WHERE id=:id FOR UPDATE',[':id'=>$id]);
   if($old===null||(string)$old['status']!=='open')throw new RuntimeException('مغایرت باز پیدا نشد.');
   $s=$this->pdo->prepare("UPDATE finance_reconciliation_items SET status='resolved',resolution_note=:note,resolved_by_user_id=:actor,resolved_at=NOW(6) WHERE id=:id AND status='open'");
   $s->execute([':note'=>$note,':actor'=>$actor,':id'=>$id]);
   if($s->rowCount()!==1)throw new RuntimeException('مغایرت همزمان تغییر کرده است.');
   $this->event('reconciliation.resolved','reconciliation',$id,$actor,['cycle_id'=>$old['cycle_id'],'discrepancy_type'=>$old['discrepancy_type'],'note'=>$note]);
   $this->pdo->commit();
  }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
  return$this->one('SELECT * FROM finance_reconciliation_items WHERE id=:id',[':id'=>$id])??[];
 }
 public function importShadow(array$row,string$actor):void{
  $request=(string)($row['id']??$row['request_id']??'');if($request==='')return;
  $merchant=trim((string)($row['merchant_id']??''))?:null;
  if($this->one('SELECT id FROM bank_settlements WHERE request_id=:id',[':id'=>$request])===null){
   $status=(string)($row['bank_settlement_status']??'not_recorded');
   if(!in_array($status,['not_recorded','recorded','cancelled'],true))$status='not_recorded';
   $at=trim((string)($row['bank_settled_at']??''))?:null;
   $this->recordBankSettlement($request,$status,$status==='recorded'?$at:null,(string)($row['bank_settlement_note']??''),$actor,'loans_shadow_import');
  }
  if($this->one('SELECT id FROM merchant_settlements WHERE request_id=:id',[':id'=>$request])===null){
   $status=(string)($row['settlement_status']??'not_started');
   if(!in_array($status,['not_started','awaiting_date','pending','settled'],true))$status='not_started';
   $date=trim((string)($row['settlement_date']??''))?:null;
   $this->recordMerchantSettlement($request,$merchant,$status,$status==='settled'?$date:null,null,'',$actor,'loans_shadow_import');
  }
  if($this->one('SELECT id FROM financier_payments WHERE request_id=:id',[':id'=>$request])===null){
   $status=(string)($row['financier_payment_status']??'unreviewed');
   if(!in_array($status,['unreviewed','paid','not_paid'],true))$status='unreviewed';
   $this->recordFinancierPayment($request,$status,(string)($row['financier_payment_note']??''),$actor,'loans_shadow_import');
  }
 }
 public function importLegacyCycle(array$c,string$actor):void{$id=(string)($c['id']??'');if($id==='')return;$exists=$this->one('SELECT id FROM settlement_cycles WHERE id=:id',[':id'=>$id]);if($exists===null){$s=$this->pdo->prepare('INSERT INTO settlement_cycles(id,cycle_no,merchant_id,status,settlement_date,total_amount,leasing_total,merchant_total,description,created_by_user_id,closed_by_user_id,closed_at,reopened_by_user_id,reopened_at,created_at,updated_at) VALUES(:id,:no,:merchant,:status,:date,:total,:leasing,:merchant_total,:description,:created_by,:closed_by,:closed_at,:reopened_by,:reopened_at,:created_at,:updated_at)');$s->execute([':id'=>$id,':no'=>(string)$c['cycle_no'],':merchant'=>(string)$c['merchant_id'],':status'=>in_array((string)$c['status'],['open','closed'],true)?(string)$c['status']:'open',':date'=>$c['settlement_date']?:null,':total'=>(float)($c['total_amount']??0),':leasing'=>(float)($c['leasing_total']??0),':merchant_total'=>(float)($c['merchant_total']??0),':description'=>$c['description']?:null,':created_by'=>(string)$c['created_by_user_id'],':closed_by'=>$c['closed_by_user_id']?:null,':closed_at'=>$c['closed_at']?:null,':reopened_by'=>$c['reopened_by_user_id']?:null,':reopened_at'=>$c['reopened_at']?:null,':created_at'=>$c['created_at']?:gmdate('Y-m-d H:i:s'),':updated_at'=>$c['updated_at']?:gmdate('Y-m-d H:i:s')]);}}
 public function importLegacyCycleItem(array$i):void{$s=$this->pdo->prepare('INSERT IGNORE INTO settlement_cycle_items(cycle_id,request_id,settlement_amount,leasing_amount,merchant_amount,created_at) VALUES(:cycle,:request,:settlement,:leasing,:merchant,:created_at)');$s->execute([':cycle'=>$i['cycle_id'],':request'=>$i['request_id'],':settlement'=>(float)($i['settlement_amount']??0),':leasing'=>(float)($i['leasing_amount']??0),':merchant'=>(float)($i['merchant_amount']??0),':created_at'=>$i['created_at']?:gmdate('Y-m-d H:i:s')]);}
 public function importLegacyReceipt(array$r,string$actor):void{$id=(string)($r['id']??'');if($id==='')return;$s=$this->pdo->prepare('INSERT IGNORE INTO financial_receipts(id,request_id,cycle_id,receipt_type,amount,document_id,status,external_reference,submitted_by_user_id,reviewed_by_user_id,review_note,submitted_at,reviewed_at) VALUES(:id,:request,:cycle,:type,:amount,:document,:status,:external,:submitted_by,:reviewed_by,:review_note,:submitted_at,:reviewed_at)');$s->execute([':id'=>$id,':request'=>$r['request_id']?:null,':cycle'=>$r['cycle_id']??null,':type'=>(string)($r['receipt_type']??'transfer'),':amount'=>$r['amount']!==null?(float)$r['amount']:null,':document'=>(string)$r['document_id'],':status'=>(string)($r['status']??'pending'),':external'=>$r['external_reference']?:null,':submitted_by'=>(string)($r['submitted_by_user_id']??$actor),':reviewed_by'=>$r['reviewed_by_user_id']?:null,':review_note'=>$r['review_note']?:null,':submitted_at'=>$r['submitted_at']?:gmdate('Y-m-d H:i:s'),':reviewed_at'=>$r['reviewed_at']?:null]);}
 public function event(string$type,string$aggregateType,?string$aggregateId,?string$actor,array$details=[],string$result='allowed',?string$ip=null,?string$ua=null):void{$meta=RequestContext::auditMeta();$s=$this->pdo->prepare('INSERT INTO finance_events(id,event_type,aggregate_type,aggregate_id,actor_user_id,result,request_id,caller_service,details_json,ip_address,user_agent) VALUES(:id,:event,:atype,:aid,:actor,:result,:request_id,:caller,:details,:ip,:ua)');$s->execute([':id'=>RandomId::make(),':event'=>$type,':atype'=>$aggregateType,':aid'=>$aggregateId,':actor'=>$actor,':result'=>$result,':request_id'=>$meta['request_id']??null,':caller'=>$meta['caller_service']??null,':details'=>$details!==[]?Json::encode($details):null,':ip'=>$ip,':ua'=>$ua]);}
 /** Removal is possible only from an open cycle with no linked receipt. */
 public function removeCycleItem(string$cycleId,string$requestId,string$reason,string$actor):array{
  if(mb_strlen(trim($reason))<10||mb_strlen($reason)>1000)throw new \InvalidArgumentException('دلیل حذف باید بین ۱۰ تا ۱۰۰۰ کاراکتر باشد.');
  $this->pdo->beginTransaction();
  try{
   $cycle=$this->lockOne('SELECT * FROM settlement_cycles WHERE id=:id FOR UPDATE',[':id'=>$cycleId]);
   if($cycle===null||(string)$cycle['status']!=='open')throw new RuntimeException('حذف فقط از سیکل باز مجاز است.');
   $item=$this->lockOne('SELECT * FROM settlement_cycle_items WHERE cycle_id=:c AND request_id=:r FOR UPDATE',[':c'=>$cycleId,':r'=>$requestId]);
   if($item===null)throw new RuntimeException('پرونده عضو این سیکل نیست.');
   $settlementState=$this->lockOne('SELECT cycle_id FROM merchant_settlements WHERE request_id=:r FOR UPDATE',[':r'=>$requestId]);
   if($settlementState!==null&&!empty($settlementState['cycle_id']))throw new RuntimeException('پرونده در وضعیت تسویه پذیرنده به سیکل متصل است؛ حذف مستقیم مجاز نیست.');
   $count=(int)$this->scalar('SELECT COUNT(*) FROM financial_receipts WHERE cycle_id=:c AND (request_id=:r OR request_id IS NULL)',[':c'=>$cycleId,':r'=>$requestId]);
   if($count!==0)throw new RuntimeException('برای این سیکل رسید مالی ثبت شده است؛ حذف مستقیم مجاز نیست.');
   $q=$this->pdo->prepare('DELETE FROM settlement_cycle_items WHERE cycle_id=:c AND request_id=:r');$q->execute([':c'=>$cycleId,':r'=>$requestId]);
   $this->recalculateCycle($cycleId);
   $this->event('settlement_cycle.item_removed','settlement_cycle',$cycleId,$actor,['request_id'=>$requestId,'before'=>$item,'reason'=>$reason]);
   $this->pdo->commit();
  }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
  return $this->cycle($cycleId)??[];
 }
 public function cycleIntegrity(string$id):array{
  $cycle=$this->cycle($id);if($cycle===null)throw new RuntimeException('سیکل تسویه پیدا نشد.');
  $issues=CycleIntegrity::issues($cycle,$cycle['items'],$cycle['receipts']);
  return ['cycle_id'=>$id,'issues'=>$issues,'critical_count'=>count(array_filter($issues,static fn(array$x):bool=>$x['severity']==='critical')),'checked_at'=>gmdate('c')];
 }
 public function scanClosedCycleDiscrepancies(string$actor,int$limit):array{
  $q=$this->pdo->prepare("SELECT id FROM settlement_cycles WHERE status='closed' ORDER BY updated_at DESC LIMIT :lim");
  $q->bindValue(':lim',min(200,max(1,$limit)),PDO::PARAM_INT);$q->execute();
  $count=0;$found=0;$new=0;
  foreach($q->fetchAll()as $row){
   $id=(string)$row['id'];$result=$this->cycleIntegrity($id);$count++;
   foreach($result['issues']as $issue){
    $found++;$type=$issue['type'];$key=substr(hash('sha256','finance:cycle:'.$id.':'.$type),0,26);
    $stmt=$this->pdo->prepare("INSERT IGNORE INTO finance_reconciliation_items(id,cycle_id,discrepancy_type,severity,expected_json,actual_json) VALUES(:id,:cycle,:type,:severity,:expected,:actual)");
    $stmt->execute([':id'=>$key,':cycle'=>$id,':type'=>$type,':severity'=>$issue['severity'],':expected'=>Json::encode($issue['expected']),':actual'=>Json::encode($issue['actual'])]);
    $new+=$stmt->rowCount();
   }
  }
  $this->event('finance.reconciliation.scan','finance',null,$actor,['scanned'=>$count,'issues'=>$found,'new'=>$new]);
  return ['scanned'=>$count,'issues'=>$found,'new'=>$new,'limit'=>$limit];
 }
 public function reportCycles(int$page,int$perPage,?string$status,?string$merchantId):array{
  $data=$this->listCycles($page,$perPage,$status,$merchantId);
  $q=$this->pdo->prepare('SELECT COUNT(*) item_count FROM settlement_cycle_items WHERE cycle_id=:id');
  $receipts=$this->pdo->prepare("SELECT status,COUNT(*) qty,COALESCE(SUM(amount),0) total FROM financial_receipts WHERE cycle_id=:id GROUP BY status");
  foreach($data['items']as &$c){
   $q->execute([':id'=>$c['id']]);$c['item_count']=(int)$q->fetchColumn();
   $receipts->execute([':id'=>$c['id']]);$c['receipt_summary']=['pending'=>0,'approved'=>0,'rejected'=>0];
   foreach($receipts->fetchAll()as$r)$c['receipt_summary'][$r['status']]=(int)$r['qty'];
  }unset($c);
  return $data;
 }
 /** Must be called inside a DB transaction; unique index serializes concurrent retries. */
 private function claimOperation(string$key,string$operation,string$actor,string$fingerprint):?array{
  $row=$this->lockOne('SELECT actor_user_id,response_json FROM finance_idempotency WHERE idempotency_key=:key AND operation=:op FOR UPDATE',[':key'=>$key,':op'=>$operation]);
  if($row!==null){
   $stored=Json::decode((string)($row['response_json']??''),[]);
   if((string)$row['actor_user_id']!==$actor||!is_array($stored)||!hash_equals((string)($stored['fingerprint']??''),$fingerprint)||!is_array($stored['result']??null))throw new RuntimeException('idempotency_conflict');
   return $stored['result'];
  }
  try{
   $stmt=$this->pdo->prepare('INSERT INTO finance_idempotency(idempotency_key,operation,actor_user_id,response_json,expires_at) VALUES(:key,:op,:actor,:response,NULL)');
   $stmt->execute([':key'=>$key,':op'=>$operation,':actor'=>$actor,':response'=>Json::encode(['fingerprint'=>$fingerprint,'result'=>null])]);
  }catch(\PDOException$e){if((string)$e->getCode()==='23000')throw new RuntimeException('idempotency_conflict');throw$e;}
  return null;
 }
 private function finishOperation(string$key,string$operation,string$fingerprint,array$result):void{
  $stmt=$this->pdo->prepare('UPDATE finance_idempotency SET response_json=:result WHERE idempotency_key=:key AND operation=:op');
  $stmt->execute([':key'=>$key,':op'=>$operation,':result'=>Json::encode(['fingerprint'=>$fingerprint,'result'=>$result])]);
  if($stmt->rowCount()!==1)throw new RuntimeException('ذخیره نتیجه یکتای عملیات ناموفق بود.');
 }
 private function recalculateCycle(string$id):void{$q=$this->pdo->prepare('SELECT COALESCE(SUM(settlement_amount),0)t,COALESCE(SUM(leasing_amount),0)l,COALESCE(SUM(merchant_amount),0)m FROM settlement_cycle_items WHERE cycle_id=:id');$q->execute([':id'=>$id]);$r=$q->fetch()?:['t'=>0,'l'=>0,'m'=>0];$u=$this->pdo->prepare('UPDATE settlement_cycles SET total_amount=:t,leasing_total=:l,merchant_total=:m,row_version=row_version+1 WHERE id=:id');$u->execute([':t'=>$r['t'],':l'=>$r['l'],':m'=>$r['m'],':id'=>$id]);}
 private function one(string$sql,array$p=[]):?array{$s=$this->pdo->prepare($sql);$s->execute($p);$r=$s->fetch();return is_array($r)?$r:null;}
 private function lockOne(string$sql,array$p=[]):?array{return$this->one($sql,$p);}
 private function all(string$sql,array$p=[]):array{$s=$this->pdo->prepare($sql);$s->execute($p);return$s->fetchAll();}
 private function scalar(string$sql,array$p=[]):mixed{$s=$this->pdo->prepare($sql);$s->execute($p);return$s->fetchColumn();}
 /** Exclusive administrative reversal of merchant settlement date, not bank settlement. */
 public function resetMerchantSettlement(string $requestId,?string $merchantId,string $actor,string $reason,int $expectedVersion,bool $confirmed):array
 {
  if(!$confirmed||$expectedVersion<0||mb_strlen(trim($reason))<10||mb_strlen($reason)>1000)throw new \InvalidArgumentException('تأیید صریح، نسخه وضعیت و دلیل حداقل ۱۰ کاراکتری لازم است.');
  $this->pdo->beginTransaction();
  try {
   $current=$this->lockOne('SELECT * FROM merchant_settlements WHERE request_id=:id FOR UPDATE',[':id'=>$requestId]);
   if(($current===null?0:(int)$current['row_version'])!==$expectedVersion)throw new RuntimeException('وضعیت تسویه همزمان تغییر کرده است؛ صفحه را تازه‌سازی کنید.');
   $membership=$this->lockOne('SELECT cycle_id FROM settlement_cycle_items WHERE request_id=:id FOR UPDATE',[':id'=>$requestId]);
   if($membership!==null||($current!==null&&!empty($current['cycle_id'])))throw new RuntimeException('پرونده به سیکل تسویه متصل است؛ ابتدا عضویت و اسناد مالی آن باید طبق روال بررسی شوند.');
   $before=$current===null?null:['status'=>$current['status'],'settlement_date'=>$current['settlement_date'],'cycle_id'=>$current['cycle_id'],'row_version'=>$current['row_version']];
   if($current===null){$id=RandomId::make();$s=$this->pdo->prepare("INSERT INTO merchant_settlements(id,request_id,merchant_id,status,settlement_date,cycle_id,note,actor_user_id,source) VALUES(:id,:request,:merchant,'awaiting_date',NULL,NULL,:note,:actor,'admin_reset')");$s->execute([':id'=>$id,':request'=>$requestId,':merchant'=>$merchantId,':note'=>$reason,':actor'=>$actor]);}
   else{$id=(string)$current['id'];$s=$this->pdo->prepare("UPDATE merchant_settlements SET status='awaiting_date',settlement_date=NULL,cycle_id=NULL,note=:note,actor_user_id=:actor,source='admin_reset',row_version=row_version+1 WHERE id=:id AND row_version=:version");$s->execute([':note'=>$reason,':actor'=>$actor,':id'=>$id,':version'=>$expectedVersion]);if($s->rowCount()!==1)throw new RuntimeException('تداخل تغییر همزمان در تسویه رخ داد.');}
   $h=$this->pdo->prepare("INSERT INTO merchant_settlement_history(id,request_id,merchant_id,status,settlement_date,cycle_id,note,actor_user_id,source) VALUES(:id,:request,:merchant,'awaiting_date',NULL,NULL,:note,:actor,'admin_reset')");
   $h->execute([':id'=>RandomId::make(),':request'=>$requestId,':merchant'=>$merchantId,':note'=>$reason,':actor'=>$actor]);
   $this->event('merchant_settlement.admin_reset','loan_request',$requestId,$actor,['before'=>$before,'after'=>['status'=>'awaiting_date','settlement_date'=>null,'cycle_id'=>null],'reason'=>$reason]);
   $this->pdo->commit();
  }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
  return $this->one('SELECT * FROM merchant_settlements WHERE request_id=:id',[':id'=>$requestId])??[];
 }

}
