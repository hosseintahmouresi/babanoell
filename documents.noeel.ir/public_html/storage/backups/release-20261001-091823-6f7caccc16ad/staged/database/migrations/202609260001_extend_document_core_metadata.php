<?php
declare(strict_types=1);
use Babanoell\Documents\Database\Migration;
return new class implements Migration {
    public function version(): string { return '202609260001'; }
    public function name(): string { return 'extend_document_core_metadata'; }
    public function up(PDO $pdo): void
    {
        $columnExists=static function(string $column)use($pdo):bool{$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=\'documents\' AND column_name=:column_name');$s->execute([':column_name'=>$column]);return(int)$s->fetchColumn()>0;};
        $indexExists=static function(string $index)use($pdo):bool{$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=\'documents\' AND index_name=:index_name');$s->execute([':index_name'=>$index]);return(int)$s->fetchColumn()>0;};
        $columns=[
            'source_service'=>"VARCHAR(64) NOT NULL DEFAULT 'panel'",
            'legacy_reference'=>'VARCHAR(190) NULL',
            'metadata_json'=>'JSON NULL',
            'retention_until'=>'DATETIME(6) NULL'
        ];
        foreach($columns as$name=>$definition)if(!$columnExists($name))$pdo->exec('ALTER TABLE documents ADD COLUMN `'.$name.'` '.$definition);
        if(!$indexExists('idx_documents_legacy_reference'))$pdo->exec('ALTER TABLE documents ADD KEY idx_documents_legacy_reference (legacy_reference)');
        if(!$indexExists('idx_documents_source'))$pdo->exec('ALTER TABLE documents ADD KEY idx_documents_source (source_service, created_at)');
        if(!$indexExists('idx_documents_retention'))$pdo->exec('ALTER TABLE documents ADD KEY idx_documents_retention (retention_until, status)');
    }
};
