<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;

return new class implements Migration {
    public function version(): string { return '202609290001'; }
    public function name(): string { return 'complete_plan_engine'; }
    public function up(PDO $pdo): void
    {
        $columnExists=static function(string$t,string$c)use($pdo):bool{$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:t AND column_name=:c');$s->execute([':t'=>$t,':c'=>$c]);return(int)$s->fetchColumn()>0;};
        $indexExists=static function(string$t,string$i)use($pdo):bool{$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=:t AND index_name=:i');$s->execute([':t'=>$t,':i'=>$i]);return(int)$s->fetchColumn()>0;};
        $add=static function(string$t,string$c,string$d)use($pdo,$columnExists):void{if(!$columnExists($t,$c))$pdo->exec('ALTER TABLE `'.$t.'` ADD COLUMN `'.$c.'` '.$d);};
        $idx=static function(string$t,string$i,string$d)use($pdo,$indexExists):void{if(!$indexExists($t,$i))$pdo->exec('ALTER TABLE `'.$t.'` ADD KEY `'.$i.'` '.$d);};

        foreach([
            'rules_json'=>'JSON NULL',
            'eligibility_json'=>'JSON NULL',
            'configuration_hash'=>'CHAR(64) NULL',
            'published_at'=>'DATETIME(6) NULL'
        ] as$c=>$d)$add('installment_plan_versions',$c,$d);
        $idx('installment_plan_versions','idx_plan_versions_hash','(configuration_hash)');

        foreach([
            'engine_version'=>"VARCHAR(32) NOT NULL DEFAULT 'v1'",
            'plan_snapshot_json'=>'JSON NULL',
            'input_hash'=>'CHAR(64) NULL',
            'mode'=>"VARCHAR(24) NOT NULL DEFAULT 'operational'"
        ] as$c=>$d)$add('calculation_runs',$c,$d);
        $idx('calculation_runs','idx_calculation_runs_input_hash','(plan_id,plan_version_id,input_hash)');

        foreach([
            'required_documents_snapshot_json'=>'JSON NULL',
            'plan_rules_snapshot_json'=>'JSON NULL',
            'plan_snapshot_hash'=>'CHAR(64) NULL'
        ] as$c=>$d)$add('loan_requests',$c,$d);

        $add('installment_plans','archived_at','DATETIME(6) NULL');
        $add('installment_plans','published_at','DATETIME(6) NULL');
        $idx('installment_plans','idx_installment_plans_order_status','(display_order,status,updated_at)');

        $pdo->exec("UPDATE installment_plan_versions SET rules_json=JSON_OBJECT('visibility_contexts',JSON_ARRAY('merchant_request','applicant_request','calculator'),'merchant_access_mode','all','occupation_documents_enabled',FALSE,'secondary_check_required',TRUE,'check_images_required',TRUE,'requires_bank_validation',COALESCE(JSON_EXTRACT(settings_json,'$.requires_bank_validation'),FALSE),'calculation_schema_version','v1') WHERE rules_json IS NULL");
        $pdo->exec("UPDATE installment_plan_versions SET eligibility_json=JSON_OBJECT() WHERE eligibility_json IS NULL");
        $pdo->exec("UPDATE installment_plans SET published_at=COALESCE(published_at,updated_at) WHERE status='active' AND published_at IS NULL");
        $pdo->exec("UPDATE installment_plans SET archived_at=COALESCE(archived_at,updated_at) WHERE status='archived' AND archived_at IS NULL");
    }
};
