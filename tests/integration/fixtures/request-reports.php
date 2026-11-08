<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$f=json_decode((string)file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
if(!str_starts_with(basename($f['root']),'bb-e2e-')||!str_starts_with($f['socket'],$f['root'].'/'))throw new RuntimeException('Disposable fixture required');
$connect=static fn(string $s):PDO=>new PDO('mysql:unix_socket='.$f['socket'].';dbname=test_'.$s.';charset=utf8mb4',$f['db_user'],'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$id=static fn(string $s):string=>substr(hash('sha256',$s),0,26);$user=static fn(string $role):string=>$id('integration:user:'.$role);$pdo=$connect('loans');$action=$argv[2]??'';
if($action==='seed'||$action==='bulk'){
 $base=(string)($argv[3]??'');if(!preg_match('/^[a-f0-9]{26}$/D',$base))throw new RuntimeException('Synthetic base case invalid');
 $q=$pdo->prepare('SELECT * FROM loan_requests WHERE id=:id');$q->execute([':id'=>$base]);$row=$q->fetch();if(!is_array($row))throw new RuntimeException('Synthetic base case missing');
 $count=$action==='bulk'?5001:6;$prefix=$action==='bulk'?'RPT219-LIMIT':'RPT219-CASE';$keys=array_keys($row);$sql='INSERT INTO loan_requests (`'.implode('`,`',$keys).'`) VALUES ('.implode(',',array_fill(0,count($keys),'?')).')';$q=$pdo->prepare($sql);$ids=[];
 $pdo->beginTransaction();try{
  for($i=0;$i<$count;$i++){
   $r=$row;$r['id']=$id($prefix.'-'.$i);$r['request_no']=$prefix.'-'.$i;$r['idempotency_key']=null;$r['created_by_user_id']=$user('applicant');$r['applicant_user_id']=$user('applicant');$r['acceptor_user_id']=null;$r['merchant_id']=null;$r['assigned_bank_user_id']=$user('bank_expert');$r['assigned_operations_user_id']=$user('operations_expert');$r['organization_ref']=null;$r['branch_ref']=null;$r['bank_organization_ref']=null;$r['full_name']='Synthetic report audit';$r['status']=$i===3?'need_correction':($i===0?'under_review':'granted');$r['settlement_status']=$i===1?'settled':'pending';$r['bank_settlement_status']=$i===1?'recorded':'not_recorded';$r['financier_payment_status']=$i===1?'paid':'unreviewed';$r['requested_amount']=$i===2?'9007199254740993.25':'100.10';$r['order_total']=$i===5?'0':$r['requested_amount'];$r['facility_amount']='70.07';$r['created_at']=['2026-03-20 20:29:59.999999','2026-03-20 20:30:00','2026-03-21 20:29:59.999999','2026-03-21 20:30:00','2026-03-20 22:00:00','2026-03-20 22:01:00'][$i%6];$r['updated_at']=$r['created_at'];
   if($i===0)$r['full_name']='Synthetic %literal';if($i===1)$r['full_name']='Synthetic _literal';if($i===2)$r['full_name']='=SUM(1,1)';
   if($i===4&&$action==='seed'){$r['created_by_user_id']=$user('acceptor');$r['applicant_user_id']=$user('acceptor');}
   $q->execute(array_values($r));$ids[]=$r['id'];
  }
  $pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 echo json_encode(['ids'=>$ids]);exit;
}
if($action==='deny'){
 $pdo=$connect('identity');$uid=$argv[3]??'';$scope=$argv[4]??'';
 if(!preg_match('/^[a-f0-9]{26}$/D',$uid)||($scope!=='*'&&!preg_match('/^[a-f0-9]{26}$/D',$scope)))throw new RuntimeException('Synthetic scope invalid');
 $q=$pdo->prepare("INSERT INTO authorization_scope_grants(id,user_id,scope_type,scope_id,effect,created_by_user_id) VALUES(:id,:user,:type,:scope,'deny',:actor)");$q->execute([':id'=>$id('RPT219-SCOPE|'.$uid.'|'.$scope),':user'=>$uid,':type'=>$scope==='*'?'global':'loan_request',':scope'=>$scope,':actor'=>$user('super_admin')]);echo json_encode(['created'=>true]);exit;
}
if($action==='clear-denies'){
 $pdo=$connect('identity');$q=$pdo->prepare('DELETE FROM authorization_scope_grants WHERE id=:id');
 foreach(['admin','bank_expert'] as $role)foreach(['*',$id('RPT219-CASE-1')] as $scope)$q->execute([':id'=>$id('RPT219-SCOPE|'.$user($role).'|'.$scope)]);
 echo json_encode(['cleared'=>true]);exit;
}
if($action==='cleanup'){
 $pdo->exec("DELETE FROM loan_requests WHERE request_no LIKE 'RPT219-%'");echo json_encode(['cleared'=>true]);exit;
}
if($action==='role-policy'){
 require $f['root'].'/partners/bootstrap.php';$r=$identityClient->planPolicy($argv[3]);echo json_encode(['status'=>$r->status,'data'=>$r->json['data']??null]);exit;
}
throw new RuntimeException('Unknown fixture action');
