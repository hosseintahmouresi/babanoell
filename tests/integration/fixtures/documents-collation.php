<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$f=json_decode((string)file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
if(!str_starts_with(basename($f['root']),'bb-e2e-')||!str_starts_with($f['socket'],$f['root'].'/'))throw new RuntimeException('Disposable fixture required');
$root=$f['root'].'/documents';require $root.'/bootstrap.php';
$action=$argv[2]??'';$id=str_repeat('d',26);
if($action==='seed'){
 $charset=$argv[3]??'';$allowed=['latin1_swedish_ci'=>'latin1','utf8mb4_general_ci'=>'utf8mb4','utf8mb4_unicode_ci'=>'utf8mb4'];
 if(!isset($allowed[$charset]))throw new RuntimeException('Test collation invalid');
 $pdo->exec('ALTER DATABASE test_documents CHARACTER SET '.$allowed[$charset].' COLLATE '.$charset);
 $pdo->prepare("INSERT INTO documents(id,document_type,category,label,owner_user_id,created_by_user_id) VALUES(:id,'national_card_front','identity','Synthetic preserved document',:owner,:actor)")->execute([':id'=>$id,':owner'=>$id,':actor'=>$id]);
 file_put_contents($root.'/storage/synthetic-existing-document.bin','Synthetic immutable original file bytes.');
 echo json_encode(['seeded'=>true]);exit;
}
if($action==='apply'){
 $zip=$argv[3];$failure=null;
 try{$r=Babanoell\Documents\Platform\ReleaseTransaction::apply($zip,$root,$root.'/storage',hash_file('sha256',$zip),static fn():array=>(new Babanoell\Documents\Database\MigrationRunner($pdo,$root.'/database/migrations'))->up());}
 catch(Throwable $e){$failure=$e->getMessage();}
 echo json_encode(['failure'=>$failure,'result'=>$r??null,'state'=>Babanoell\Documents\Platform\ReleaseTransaction::state($root.'/storage')],JSON_THROW_ON_ERROR);exit;
}
if($action==='interrupt'){
 (new Babanoell\Documents\Database\MigrationRunner($pdo,$root.'/database/migrations'))->up();
 $file=$root.'/storage/update-state.json';$state=Babanoell\Documents\Platform\ReleaseTransaction::state($root.'/storage');$state['phase']='migrating';$state['forward_repair']='documents-resumable-collation-1.23.1';file_put_contents($file,json_encode($state,JSON_THROW_ON_ERROR));echo json_encode(['interrupted'=>true]);exit;
}
if($action==='state'){
 $tables=$pdo->query("SELECT TABLE_NAME,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'chunk_upload%' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_KEY_PAIR);
 $q=$pdo->prepare('SELECT label FROM documents WHERE id=:id');$q->execute([':id'=>$id]);
 echo json_encode(['state'=>Babanoell\Documents\Platform\ReleaseTransaction::state($root.'/storage'),'migrations'=>(new Babanoell\Documents\Database\MigrationRunner($pdo,$root.'/database/migrations'))->status()['migrations'],'tables'=>$tables,'document_label'=>$q->fetchColumn(),'file_hash'=>hash_file('sha256',$root.'/storage/synthetic-existing-document.bin'),'version'=>require $root.'/config/version.php']);exit;
}
if($action==='fk'){
 $pdo->prepare("INSERT INTO chunk_uploads(id,actor_id,client_key,request_hash,entity_type,entity_id,fields_json,file_name,total_bytes,checksum_sha256,chunk_size,total_chunks,expires_at) VALUES(:id,:actor,:key,:hash,'loan_request',:entity,'{}','synthetic.png',1,:checksum,262144,1,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))")->execute([':id'=>$id,':actor'=>$id,':key'=>str_repeat('a',64),':hash'=>str_repeat('a',64),':entity'=>$id,':checksum'=>str_repeat('b',64)]);
 $pdo->prepare('INSERT INTO chunk_upload_parts(upload_id,part_index,size_bytes,checksum_sha256) VALUES(:id,0,1,:hash)')->execute([':id'=>$id,':hash'=>str_repeat('b',64)]);
 $orphanRejected=false;try{$pdo->prepare('INSERT INTO chunk_upload_parts(upload_id,part_index,size_bytes,checksum_sha256) VALUES(:id,0,1,:hash)')->execute([':id'=>str_repeat('e',26),':hash'=>str_repeat('b',64)]);}catch(PDOException $e){$orphanRejected=$e->getCode()==='23000';}
 $pdo->prepare('DELETE FROM chunk_uploads WHERE id=:id')->execute([':id'=>$id]);
 echo json_encode(['orphan_rejected'=>$orphanRejected,'cascade_removed_parts'=>(int)$pdo->query('SELECT COUNT(*) FROM chunk_upload_parts')->fetchColumn()===0,'rerun'=>(new Babanoell\Documents\Database\MigrationRunner($pdo,$root.'/database/migrations'))->up()]);exit;
}
throw new RuntimeException('Unknown fixture action');
