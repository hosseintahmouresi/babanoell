<?php
declare(strict_types=1);
use Babanoell\Finance\Database\Migration;
return new class implements Migration {
 public function version():string{return '202610220005';}
 public function name():string{return 'greenpay_transfer_intents';}
 public function up(PDO $pdo):void{
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS finance_greenpay_transfers (
 id CHAR(26) NOT NULL, cycle_id CHAR(26) NOT NULL, created_by CHAR(26) NOT NULL,
 approved_by CHAR(26) NULL, reviewed_by CHAR(26) NULL, idempotency_key VARCHAR(128) NOT NULL,
 request_hash CHAR(64) NOT NULL, external_reference VARCHAR(48) NOT NULL,
 from_pocket_encrypted TEXT NOT NULL, to_pocket_encrypted TEXT NOT NULL,
 from_pocket_mask VARCHAR(8) NOT NULL, to_pocket_mask VARCHAR(8) NOT NULL,
 amount_rial BIGINT UNSIGNED NOT NULL, status VARCHAR(32) NOT NULL DEFAULT 'prepared',
 reason VARCHAR(1000) NOT NULL, decision_note VARCHAR(1000) NULL, provider_error VARCHAR(80) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), approved_at DATETIME(6) NULL,
 dispatch_started_at DATETIME(6) NULL, provider_accepted_at DATETIME(6) NULL,
 reviewed_at DATETIME(6) NULL, updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id), UNIQUE KEY uq_greenpay_cycle(cycle_id),
 UNIQUE KEY uq_greenpay_idempotency(created_by,idempotency_key),
 UNIQUE KEY uq_greenpay_reference(external_reference), KEY idx_greenpay_status(status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS finance_greenpay_transfer_events (
 id CHAR(26) NOT NULL, transfer_id CHAR(26) NOT NULL, actor_user_id CHAR(26) NOT NULL,
 action VARCHAR(48) NOT NULL, previous_status VARCHAR(32) NULL, new_status VARCHAR(32) NOT NULL,
 note VARCHAR(1000) NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id), KEY idx_greenpay_transfer_events(transfer_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
 }
};
