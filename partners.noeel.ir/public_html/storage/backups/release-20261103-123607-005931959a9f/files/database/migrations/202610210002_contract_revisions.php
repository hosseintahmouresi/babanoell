<?php
declare(strict_types=1);
use Babanoell\Partners\Database\Migration;
/** Adds immutable revision records without touching signed contracts or their evidence. */
return new class implements Migration {
 public function version(): string {return '202610210002';}
 public function name(): string {return 'contract_revisions';}
 public function up(PDO $pdo): void {
  $columnExists=static function(string $column)use($pdo):bool{$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=\'contracts\' AND column_name=:column');$s->execute([':column'=>$column]);return (int)$s->fetchColumn()>0;};
  $indexExists=static function(string $index)use($pdo):bool{$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=\'contracts\' AND index_name=:name');$s->execute([':name'=>$index]);return (int)$s->fetchColumn()>0;};
  if(!$columnExists('revision'))$pdo->exec('ALTER TABLE contracts ADD COLUMN revision INT UNSIGNED NOT NULL DEFAULT 1 AFTER contract_kind');
  if(!$columnExists('reissued_from_id'))$pdo->exec('ALTER TABLE contracts ADD COLUMN reissued_from_id CHAR(26) NULL AFTER subject_id');
  if(!$columnExists('reissue_reason'))$pdo->exec('ALTER TABLE contracts ADD COLUMN reissue_reason VARCHAR(1000) NULL AFTER reissued_from_id');
  if($indexExists('uq_contract_subject_kind'))$pdo->exec('ALTER TABLE contracts DROP INDEX uq_contract_subject_kind');
  if(!$indexExists('uq_contract_subject_kind_revision'))$pdo->exec('ALTER TABLE contracts ADD UNIQUE KEY uq_contract_subject_kind_revision (subject_type,subject_id,contract_kind,revision)');
  if(!$indexExists('idx_contract_reissue_parent'))$pdo->exec('ALTER TABLE contracts ADD KEY idx_contract_reissue_parent (reissued_from_id)');
 }
};
