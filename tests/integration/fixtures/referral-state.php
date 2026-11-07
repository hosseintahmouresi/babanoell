<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$fixture=json_decode((string)file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
if(!str_starts_with(basename($fixture['root']),'bb-e2e-')||!str_starts_with($fixture['socket'],$fixture['root'].'/'))throw new RuntimeException('Disposable fixture required');
$id=(string)($argv[3]??'');if(!preg_match('/^[a-f0-9]{26}$/D',$id))throw new RuntimeException('Invalid synthetic ID');
$partners=($argv[2]??'')==='expire-lock';
$pdo=new PDO('mysql:unix_socket='.$fixture['socket'].';dbname=test_'.($partners?'partners':'loans').';charset=utf8mb4',$fixture['db_user'],'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$sql=$partners?'UPDATE applicant_invitation_locks SET expires_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 MINUTE) WHERE applicant_user_id=:id':'UPDATE loan_requests SET marketplace_claimed_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 8 DAY) WHERE id=:id';
$q=$pdo->prepare($sql);$q->execute([':id'=>$id]);echo json_encode(['changed'=>$q->rowCount()]);
