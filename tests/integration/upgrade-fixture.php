<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$fixture=json_decode((string)file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
if(!str_starts_with(basename($fixture['root']),'bb-e2e-')||!str_starts_with($fixture['socket'],$fixture['root'].'/'))throw new RuntimeException('Disposable fixture required');
$connect=static fn(string $name):PDO=>new PDO('mysql:unix_socket='.$fixture['socket'].';dbname='.$name.';charset=utf8mb4',$fixture['db_user'],'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$historical=str_repeat('e',26);$actor=substr(hash('sha256','integration:user:super_admin'),0,26);$applicant=substr(hash('sha256','integration:user:applicant'),0,26);
if(($argv[2]??'')==='seed'){
 $pdo=$connect('test_finance');
 $q=$pdo->prepare("INSERT INTO bank_settlement_cycles(id,cycle_no,bank_user_id,created_by_user_id,settlement_date,notes,item_count,total_loan_amount,request_hash,idempotency_key,projection_status) VALUES(:id,'SYNTHETIC-HISTORY',:owner,:actor,'2026-01-01','Synthetic historical financial snapshot',1,70000000,:hash,'synthetic-historical-cycle','synced')");$q->execute([':id'=>$historical,':owner'=>$actor,':actor'=>$actor,':hash'=>str_repeat('a',64)]);
 $q=$pdo->prepare("INSERT INTO bank_settlement_cycle_items(cycle_id,request_id,applicant_name,national_code,loan_amount) VALUES(:cycle,:request,'Synthetic historical applicant','',70000000)");$q->execute([':cycle'=>$historical,':request'=>str_repeat('d',26)]);
 $pdo=$connect('test_loans');$payload='{"synthetic":true}';
 $q=$pdo->prepare("INSERT INTO integration_outbox(id,event_type,aggregate_type,destination,payload_json,payload_hash,status) VALUES(:id,'synthetic.history','synthetic','notifications',:payload,:hash,'pending')");$q->execute([':id'=>$historical,':payload'=>$payload,':hash'=>hash('sha256',$payload)]);
 $q=$pdo->prepare("INSERT INTO notifications(id,recipient_user_id,notification_type,channel,title,body,target_url,status,metadata_json) VALUES(:id,:user,'synthetic.history','panel','Synthetic preserved notification','Synthetic history remains private',NULL,'queued',:meta)");$q->execute([':id'=>$historical,':user'=>$applicant,':meta'=>json_encode(['request_id'=>str_repeat('d',26)])]);
 echo json_encode(['seeded'=>true]);exit;
}
if(($argv[2]??'')==='verify'){
 $pdo=$connect('test_finance');$q=$pdo->prepare('SELECT total_loan_amount,amount_basis FROM bank_settlement_cycles WHERE id=:id');$q->execute([':id'=>$historical]);$old=$q->fetch();
 if((string)$old['total_loan_amount']!=='70000000.00'||$old['amount_basis']!=='facility_amount_legacy')throw new RuntimeException('Historical bank snapshot changed');
 $pdo=$connect('test_loans');$q=$pdo->prepare('SELECT status,last_error FROM integration_outbox WHERE id=:id');$q->execute([':id'=>$historical]);$old=$q->fetch();
 if($old['status']!=='skipped'||$old['last_error']!=='historical_event_before_worker')throw new RuntimeException('Historical SMS could be dispatched');
 $q=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE id=:id AND recipient_user_id=:user AND read_at IS NULL');$q->execute([':id'=>$historical,':user'=>$applicant]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Existing private notification changed');
 echo json_encode(['historical_amount_unchanged'=>true,'old_amount_basis_labeled'=>true,'historical_sms_not_replayed'=>true,'private_notification_preserved'=>true]);exit;
}
if(($argv[2]??'')==='counts'){
 $name=(string)($argv[3]??'');if(!preg_match('/^(test|restore)_(identity|loans|documents|partners|finance|integrations|panel)$/D',$name))throw new RuntimeException('Synthetic database only');
 $pdo=$connect($name);$tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$counts=[];
 foreach($tables as $table){if(!preg_match('/^[a-z0-9_]+$/D',$table))throw new RuntimeException('Unexpected table identifier');$counts[$table]=(int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();}
 ksort($counts);echo json_encode($counts);exit;
}
throw new RuntimeException('Unknown fixture action');
