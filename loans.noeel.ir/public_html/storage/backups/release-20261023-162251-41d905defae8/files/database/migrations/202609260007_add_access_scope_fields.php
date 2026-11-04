<?php
declare(strict_types=1); use Babanoell\Loans\Database\Migration;
return new class implements Migration { public function version():string{return'202609260007';} public function name():string{return'add_access_scope_fields';} public function up(PDO$pdo):void{
 $exists=static function(string$c)use($pdo):bool{$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=\'loan_requests\' AND column_name=:c');$s->execute([':c'=>$c]);return(int)$s->fetchColumn()>0;};
 foreach(['organization_ref'=>'CHAR(26) NULL','branch_ref'=>'CHAR(26) NULL','bank_organization_ref'=>'CHAR(26) NULL','merchant_id'=>'CHAR(26) NULL']as$c=>$d)if(!$exists($c))$pdo->exec('ALTER TABLE loan_requests ADD COLUMN `'.$c.'` '.$d);
 foreach(['idx_loan_requests_org'=>'(organization_ref,status)','idx_loan_requests_branch'=>'(branch_ref,status)','idx_loan_requests_bank_org'=>'(bank_organization_ref,status)','idx_loan_requests_merchant_id'=>'(merchant_id,status)']as$n=>$cols){$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=\'loan_requests\' AND index_name=:n');$s->execute([':n'=>$n]);if(!(int)$s->fetchColumn())$pdo->exec('ALTER TABLE loan_requests ADD KEY `'.$n.'` '.$cols);}
 }};
