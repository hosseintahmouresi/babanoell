<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$fixture=json_decode((string)file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
if(!str_starts_with(basename($fixture['root']),'bb-e2e-')||!str_starts_with($fixture['socket'],$fixture['root'].'/'))throw new RuntimeException('Disposable fixture required');
$connect=static fn(string $name):PDO=>new PDO('mysql:unix_socket='.$fixture['socket'].';dbname='.$name.';charset=utf8mb4',$fixture['db_user'],'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$historical=str_repeat('e',26);$actor=substr(hash('sha256','integration:user:super_admin'),0,26);$applicant=substr(hash('sha256','integration:user:applicant'),0,26);
$evidenceFile=$fixture['root'].'/loans/storage/upgrade-evidence.json';
$artifact=static function(PDO $pdo,string $id)use($fixture):string{
 require_once $fixture['root'].'/partners/src/Contract/ContractRepository.php';$repo=new Babanoell\Partners\Contract\ContractRepository($pdo);
 $q=$pdo->prepare('SELECT * FROM contracts WHERE id=:id');$q->execute([':id'=>$id]);$row=$q->fetch();if(!is_array($row))throw new RuntimeException('Historical signature missing');
 return (new ReflectionMethod($repo,'artifactContent'))->invoke($repo,$row);
};
if(($argv[2]??'')==='seed'){
 $pdo=$connect('test_finance');$hasBasis=$pdo->query("SHOW COLUMNS FROM bank_settlement_cycles LIKE 'amount_basis'")->fetch()!==false;
 $q=$pdo->prepare("INSERT INTO bank_settlement_cycles(id,cycle_no,bank_user_id,created_by_user_id,settlement_date,notes,item_count,total_loan_amount,request_hash,idempotency_key,projection_status) VALUES(:id,'SYNTHETIC-HISTORY',:owner,:actor,'2026-01-01','Synthetic historical financial snapshot',1,70000000,:hash,'synthetic-historical-cycle','synced')");$q->execute([':id'=>$historical,':owner'=>$actor,':actor'=>$actor,':hash'=>str_repeat('a',64)]);
 if($hasBasis)$pdo->prepare("UPDATE bank_settlement_cycles SET amount_basis='facility_amount_legacy' WHERE id=:id")->execute([':id'=>$historical]);
 $q=$pdo->prepare("INSERT INTO bank_settlement_cycle_items(cycle_id,request_id,applicant_name,national_code,loan_amount) VALUES(:cycle,:request,'Synthetic historical applicant','',70000000)");$q->execute([':cycle'=>$historical,':request'=>str_repeat('d',26)]);
 if($hasBasis){
  $q=$pdo->prepare("INSERT INTO bank_settlement_cycles(id,cycle_no,bank_user_id,created_by_user_id,settlement_date,notes,item_count,total_loan_amount,request_hash,idempotency_key,projection_status,amount_basis) VALUES(:id,'SYNTHETIC-PURCHASE-100M',:owner,:actor,'2026-01-02','Synthetic reference-purchase snapshot',1,100000000,:hash,'synthetic-reference-cycle','synced','reference_purchase_amount')");$q->execute([':id'=>str_repeat('c',26),':owner'=>$actor,':actor'=>$actor,':hash'=>str_repeat('b',64)]);
 }
 $pdo=$connect('test_loans');$hasWorker=$pdo->query("SHOW TABLES LIKE 'notification_settings'")->fetch()!==false;$payload='{"synthetic":true}';
 $q=$pdo->prepare("INSERT INTO integration_outbox(id,event_type,aggregate_type,destination,payload_json,payload_hash,status) VALUES(:id,'synthetic.history','synthetic','notifications',:payload,:hash,'pending')");$q->execute([':id'=>$historical,':payload'=>$payload,':hash'=>hash('sha256',$payload)]);
 $q=$pdo->prepare("INSERT INTO notifications(id,recipient_user_id,notification_type,channel,title,body,target_url,status,metadata_json) VALUES(:id,:user,'synthetic.history','panel','Synthetic preserved notification','Synthetic history remains private',NULL,'queued',:meta)");$q->execute([':id'=>$historical,':user'=>$applicant,':meta'=>json_encode(['request_id'=>str_repeat('d',26)])]);
 // Create an actual baseline case through that installed revision's own business code.
 require $fixture['root'].'/loans/bootstrap.php';
 $plans=new Babanoell\Loans\Service\PlanService($repository,new Babanoell\Loans\Domain\PlanSettingsValidator());
 $plan=$plans->create($actor,['code'=>'SYNTHETIC-UPGRADE-PLAN','name'=>'Synthetic preserved upgrade plan','type'=>'market','settings'=>['min_amount'=>1,'max_amount'=>100000000,'active_installments'=>[6],'fixed_monthly_rate'=>2,'prepayment_percent'=>20],'required_documents'=>[]]);$plans->status($plan['id'],'active');
 $requests=new Babanoell\Loans\Service\RequestService($repository,new Babanoell\Loans\Service\CalculationService($repository,$calculators),$scheduleGenerator);
 $loan=$requests->create($actor,['plan_id'=>$plan['id'],'requested_amount'=>1000000,'installment_count'=>6,'installment_interval'=>1,'submit'=>false,'full_name'=>'Synthetic preserved case'],'synthetic-upgrade-case');
 $pdo=$connect('test_partners');$subject=str_repeat('f',26);
 $pdo->prepare("INSERT INTO cooperation_requests(id,request_no,applicant_user_id,request_type,status,contract_signed_status,contract_signed_at,data_json,created_by_user_id,created_at) VALUES(:id,'SYNTHETIC-SIGNED-COOP',:owner,'individual','approved','signed','2020-01-01 12:00:00',:data,:actor,'2020-01-01 00:00:00')")->execute([':id'=>$subject,':owner'=>$actor,':data'=>json_encode(['full_name'=>'Synthetic historic signer','mobile'=>'09121234567']),':actor'=>$actor]);
 $tpl=$pdo->query("SELECT * FROM contract_templates WHERE code='cooperation_default' ORDER BY version DESC LIMIT 1")->fetch();$content='<p>Synthetic signed historical agreement; immutable before and after upgrade.</p>';$signed=str_repeat('b',26);
 $pdo->prepare("INSERT INTO contracts(id,contract_no,contract_kind,subject_type,subject_id,signer_user_id,signer_mobile,mode,status,template_id,template_code,template_version,template_hash,rendered_content,rendered_hash,otp_verified_at,signed_at,artifact_status,created_by_user_id) VALUES(:id,'SYNTHETIC-SIGNED-HISTORY','cooperation_agreement','cooperation_request',:subject,:signer,'09121234567','digital','signed',:template,'cooperation_default',:version,:templatehash,:content,:hash,'2020-01-01 12:00:00','2020-01-01 12:00:00','not_required',:actor)")->execute([':id'=>$signed,':subject'=>$subject,':signer'=>$actor,':template'=>$tpl['id'],':version'=>$tpl['version'],':templatehash'=>$tpl['content_hash'],':content'=>$content,':hash'=>hash('sha256',$content),':actor'=>$actor]);
 $evidence=['has_reference_basis'=>$hasBasis,'outbox_expected'=>$hasWorker?'pending':'skipped','loan_id'=>$loan['id'],'request_no'=>$loan['request_no'],'calculation_result_hash'=>$loan['calculation_result_hash'],'signed_id'=>$signed,'signed_content_hash'=>hash('sha256',$content),'signed_artifact_hash'=>hash('sha256',$artifact($pdo,$signed))];
 file_put_contents($evidenceFile,json_encode($evidence,JSON_THROW_ON_ERROR));chmod($evidenceFile,0600);
 echo json_encode(['seeded'=>true]);exit;
}
if(($argv[2]??'')==='verify'){
 $e=json_decode((string)file_get_contents($evidenceFile),true,512,JSON_THROW_ON_ERROR);
 $pdo=$connect('test_finance');$q=$pdo->prepare('SELECT total_loan_amount,amount_basis FROM bank_settlement_cycles WHERE id=:id');$q->execute([':id'=>$historical]);$old=$q->fetch();
 if((string)$old['total_loan_amount']!=='70000000.00'||$old['amount_basis']!=='facility_amount_legacy')throw new RuntimeException('Historical bank snapshot changed');
 if($e['has_reference_basis']){$q->execute([':id'=>str_repeat('c',26)]);$new=$q->fetch();if((string)$new['total_loan_amount']!=='100000000.00'||$new['amount_basis']!=='reference_purchase_amount')throw new RuntimeException('Existing reference-purchase snapshot changed');}
 $pdo=$connect('test_loans');$q=$pdo->prepare('SELECT status,last_error FROM integration_outbox WHERE id=:id');$q->execute([':id'=>$historical]);$old=$q->fetch();
 if($old['status']!==$e['outbox_expected']||($e['outbox_expected']==='skipped'&&$old['last_error']!=='historical_event_before_worker'))throw new RuntimeException('Existing SMS queue state changed');
 $q=$pdo->prepare('SELECT COUNT(*) FROM notification_deliveries WHERE event_id=:id');$q->execute([':id'=>$historical]);if((int)$q->fetchColumn()!==0)throw new RuntimeException('Historical event turned into a new SMS');
 $q=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE id=:id AND recipient_user_id=:user AND read_at IS NULL');$q->execute([':id'=>$historical,':user'=>$applicant]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Existing private notification changed');
 $q=$pdo->prepare('SELECT request_no,calculation_run_id FROM loan_requests WHERE id=:id');$q->execute([':id'=>$e['loan_id']]);$loan=$q->fetch();if($loan['request_no']!==$e['request_no'])throw new RuntimeException('Existing business number changed');
 $q=$pdo->prepare('SELECT result_hash FROM calculation_runs WHERE id=:id');$q->execute([':id'=>$loan['calculation_run_id']]);if(!hash_equals($e['calculation_result_hash'],(string)$q->fetchColumn()))throw new RuntimeException('Historical calculation changed');
 $pdo=$connect('test_partners');$q=$pdo->prepare('SELECT rendered_hash,buyer_confirmation_token,platform_confirmation_token FROM contracts WHERE id=:id');$q->execute([':id'=>$e['signed_id']]);$c=$q->fetch();
 if($c['rendered_hash']!==$e['signed_content_hash']||$c['buyer_confirmation_token']!==null||$c['platform_confirmation_token']!==null||!hash_equals($e['signed_artifact_hash'],hash('sha256',$artifact($pdo,$e['signed_id']))))throw new RuntimeException('Historical signed proof or exact artifact bytes changed');
 echo json_encode(['historical_amount_unchanged'=>true,'reference_amount_unchanged'=>(bool)$e['has_reference_basis'],'old_amount_basis_labeled'=>true,'historical_sms_not_replayed'=>true,'private_notification_preserved'=>true,'business_number_unchanged'=>true,'calculation_snapshot_unchanged'=>true,'signed_artifact_bytes_unchanged'=>true,'no_historical_signature_tokens_manufactured'=>true]);exit;
}
if(($argv[2]??'')==='counts'){
 $name=(string)($argv[3]??'');if(!preg_match('/^(test|restore)_(identity|loans|documents|partners|finance|integrations|panel)$/D',$name))throw new RuntimeException('Synthetic database only');
 $pdo=$connect($name);$tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$counts=[];
 foreach($tables as $table){if(!preg_match('/^[a-z0-9_]+$/D',$table))throw new RuntimeException('Unexpected table identifier');$counts[$table]=(int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();}
 ksort($counts);echo json_encode($counts);exit;
}
throw new RuntimeException('Unknown fixture action');
