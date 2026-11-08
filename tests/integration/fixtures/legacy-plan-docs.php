<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);$f=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
if(!str_starts_with(basename($f['root']),'bb-e2e-')||!str_starts_with($f['socket'],$f['root'].'/')||!preg_match('/^[a-f0-9]{26}$/D',$argv[2]))throw new RuntimeException('Disposable synthetic plan required');
$p=new PDO('mysql:unix_socket='.$f['socket'].';dbname=test_loans;charset=utf8mb4',$f['db_user'],'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$q=$p->prepare("UPDATE installment_plan_versions v JOIN installment_plans p ON p.id=v.plan_id SET v.rules_json=JSON_REMOVE(v.rules_json,'$.completion_documents_required','$.completed_check_files_enabled','$.required_document_descriptions') WHERE p.id=? AND p.code='REFERENCE-PHASE-EVIDENCE'");$q->execute([$argv[2]]);if($q->rowCount()<1)throw new RuntimeException('Synthetic reference plan missing');
echo 'Synthetic legacy plan metadata prepared';
