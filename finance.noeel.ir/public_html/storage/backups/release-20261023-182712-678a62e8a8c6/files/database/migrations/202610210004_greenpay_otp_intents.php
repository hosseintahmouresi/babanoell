<?php
declare(strict_types=1);
use Babanoell\Finance\Database\Migration;
return new class implements Migration {
 public function version():string{return '202610210004';}
 public function name():string{return 'greenpay_otp_intents';}
 public function up(PDO $pdo):void {
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS finance_greenpay_otp_intents (
 id CHAR(26) NOT NULL, actor_user_id CHAR(26) NOT NULL, idempotency_key VARCHAR(120) NOT NULL,
 request_fingerprint CHAR(64) NOT NULL, pocket_hash CHAR(64) NOT NULL, amount_rial BIGINT UNSIGNED NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'pending', provider_code VARCHAR(64) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), finished_at DATETIME(6) NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_fin_greenpay_otp_actor_key(actor_user_id,idempotency_key),
 KEY idx_fin_greenpay_otp_status(status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
 }
};
