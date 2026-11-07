<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$fixture=json_decode((string)file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
if(!str_starts_with(basename($fixture['root']),'bb-e2e-')||!str_starts_with($fixture['socket'],$fixture['root'].'/'))throw new RuntimeException('Disposable fixture required');
$id=(string)($argv[3]??'');if(!preg_match('/^[a-f0-9]{26}$/D',$id))throw new RuntimeException('Invalid synthetic ID');
$pdo=new PDO('mysql:unix_socket='.$fixture['socket'].';dbname=test_loans;charset=utf8mb4',$fixture['db_user'],'',[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
switch($argv[2]){
 case 'lose-reference':
  $q=$pdo->prepare("UPDATE verification_requests SET provider_reference=NULL,metadata_json=JSON_SET(metadata_json,'$.delivery','unconfirmed') WHERE id=:id AND status='pending'");$q->execute([':id'=>$id]);break;
 case 'expire-evidence':
  $q=$pdo->prepare('UPDATE credit_report_evidence SET valid_until=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 MINUTE) WHERE verification_id=:id');$q->execute([':id'=>$id]);break;
 case 'evidence-count':
  $q=$pdo->prepare('SELECT COUNT(*) FROM credit_report_evidence WHERE verification_id=:id');$q->execute([':id'=>$id]);echo json_encode(['count'=>(int)$q->fetchColumn()]);exit;
 case 'safe-logs':
  $integration=new PDO('mysql:unix_socket='.$fixture['socket'].';dbname=test_integrations;charset=utf8mb4',$fixture['db_user'],'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $rows=$integration->query('SELECT result_json FROM integration_operations')->fetchAll(PDO::FETCH_COLUMN);$safe=true;
  foreach($rows as$row){$json=json_decode((string)$row,true);if(!is_array($json))continue;foreach(['otp','Token','national_code','mobile','report','proof','canonical_json'] as$key)if(array_key_exists($key,$json))$safe=false;}
  echo json_encode(['safe'=>$safe]);exit;
 default:throw new RuntimeException('Unknown fixture action');
}
echo json_encode(['changed'=>$q->rowCount()]);
