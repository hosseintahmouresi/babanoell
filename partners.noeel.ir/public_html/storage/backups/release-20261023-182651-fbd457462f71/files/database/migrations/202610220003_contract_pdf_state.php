<?php
declare(strict_types=1);
use Babanoell\Partners\Database\Migration;
return new class implements Migration {
 public function version():string{return '202610220003';}
 public function name():string{return 'contract_pdf_state';}
 public function up(PDO $pdo):void{
  foreach(['pdf_document_id'=>'CHAR(26) NULL','pdf_hash'=>'CHAR(64) NULL','pdf_stored_at'=>'DATETIME(6) NULL'] as $column=>$ddl){
   $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=\'contracts\' AND column_name=:column');
   $q->execute([':column'=>$column]);if((int)$q->fetchColumn()===0)$pdo->exec('ALTER TABLE contracts ADD COLUMN '.$column.' '.$ddl);
  }
 }
};
