<?php
declare(strict_types=1);
use Babanoell\Partners\Database\Migration;
return new class implements Migration {
 public function version():string{return '202611050002';}
 public function name():string{return 'cooperation_activations';}
 public function up(PDO $pdo):void{
  $columns=$pdo->query("SHOW COLUMNS FROM contracts LIKE 'subject_snapshot_hash'")->fetchAll();
  if($columns===[])$pdo->exec('ALTER TABLE contracts ADD COLUMN subject_snapshot_hash CHAR(64) NULL');
  $pdo->exec("CREATE TABLE IF NOT EXISTS cooperation_activations (
   cooperation_id CHAR(26) NOT NULL PRIMARY KEY,
   merchant_id CHAR(26) NOT NULL UNIQUE,
   applicant_user_id CHAR(26) NOT NULL,
   contract_id CHAR(26) NOT NULL,
   contract_hash CHAR(64) NOT NULL,
   evidence_json JSON NOT NULL,
   status VARCHAR(16) NOT NULL DEFAULT 'pending',
   approved_by_user_id CHAR(26) NOT NULL,
   attempts INT UNSIGNED NOT NULL DEFAULT 0,
   last_error VARCHAR(64) NULL,
   identity_confirmed_at DATETIME(6) NULL,
   loans_confirmed_at DATETIME(6) NULL,
   activated_at DATETIME(6) NULL,
   created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
   updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
   CONSTRAINT fk_coop_activation_request FOREIGN KEY(cooperation_id) REFERENCES cooperation_requests(id) ON DELETE RESTRICT,
   CONSTRAINT fk_coop_activation_merchant FOREIGN KEY(merchant_id) REFERENCES merchants(id) ON DELETE RESTRICT,
   CONSTRAINT chk_coop_activation_status CHECK(status IN ('pending','active'))
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 }
};
