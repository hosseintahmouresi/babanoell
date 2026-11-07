<?php
declare(strict_types=1);
use Babanoell\Partners\Database\Migration;
return new class implements Migration {
    public function version():string{return '202611070001';}
    public function name():string{return 'physical_contract_evidence';}
    public function up(PDO $pdo):void{
        foreach(['physical_document_id'=>'CHAR(26) NULL','physical_version_id'=>'CHAR(26) NULL','physical_sha256'=>'CHAR(64) NULL','physical_confirmed_by'=>'CHAR(26) NULL'] as $column=>$ddl){
            $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='contracts' AND column_name=:column");$q->execute([':column'=>$column]);
            if((int)$q->fetchColumn()===0)$pdo->exec('ALTER TABLE contracts ADD COLUMN '.$column.' '.$ddl);
        }
    }
};
