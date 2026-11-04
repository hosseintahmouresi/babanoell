<?php
declare(strict_types=1);
use Babanoell\Finance\Database\Migration;
return new class implements Migration{
 public function version():string{return'202610010003';}public function name():string{return'create_reconciliation_audit';}
 public function up(PDO$pdo):void{
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS finance_reconciliation_items (
 id CHAR(26) NOT NULL,request_id CHAR(26) NULL,cycle_id CHAR(26) NULL,discrepancy_type VARCHAR(64) NOT NULL,severity VARCHAR(16) NOT NULL DEFAULT 'warning',expected_json JSON NULL,actual_json JSON NULL,status VARCHAR(32) NOT NULL DEFAULT 'open',resolution_note VARCHAR(1000) NULL,resolved_by_user_id CHAR(26) NULL,resolved_at DATETIME(6) NULL,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),PRIMARY KEY(id),KEY idx_fin_recon_status(status,severity,created_at),KEY idx_fin_recon_request(request_id),KEY idx_fin_recon_cycle(cycle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS finance_events (
 id CHAR(26) NOT NULL,event_type VARCHAR(96) NOT NULL,aggregate_type VARCHAR(64) NOT NULL,aggregate_id CHAR(26) NULL,actor_user_id CHAR(26) NULL,result VARCHAR(32) NOT NULL DEFAULT 'allowed',request_id VARCHAR(128) NULL,caller_service VARCHAR(32) NULL,details_json JSON NULL,ip_address VARCHAR(64) NULL,user_agent VARCHAR(500) NULL,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),PRIMARY KEY(id),KEY idx_fin_events_aggregate(aggregate_type,aggregate_id,created_at),KEY idx_fin_events_actor(actor_user_id,created_at),KEY idx_fin_events_request(request_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS finance_idempotency (
 idempotency_key VARCHAR(190) NOT NULL,operation VARCHAR(96) NOT NULL,actor_user_id CHAR(26) NOT NULL,response_json JSON NULL,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),expires_at DATETIME(6) NULL,PRIMARY KEY(idempotency_key,operation),KEY idx_fin_idempotency_expiry(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
 }
};
