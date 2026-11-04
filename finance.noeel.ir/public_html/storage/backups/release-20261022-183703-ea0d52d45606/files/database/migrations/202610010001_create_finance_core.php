<?php
declare(strict_types=1);
use Babanoell\Finance\Database\Migration;
return new class implements Migration{
 public function version():string{return'202610010001';}public function name():string{return'create_finance_core';}
 public function up(PDO$pdo):void{
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS bank_settlements (
 id CHAR(26) NOT NULL,request_id CHAR(26) NOT NULL,status VARCHAR(32) NOT NULL DEFAULT 'not_recorded',settled_at DATETIME(6) NULL,note VARCHAR(1000) NULL,actor_user_id CHAR(26) NOT NULL,source VARCHAR(32) NOT NULL DEFAULT 'finance',row_version BIGINT UNSIGNED NOT NULL DEFAULT 1,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),PRIMARY KEY(id),UNIQUE KEY uq_bank_settlement_request(request_id),KEY idx_bank_settlement_status(status,settled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS bank_settlement_history (
 id CHAR(26) NOT NULL,request_id CHAR(26) NOT NULL,status VARCHAR(32) NOT NULL,settled_at DATETIME(6) NULL,note VARCHAR(1000) NULL,actor_user_id CHAR(26) NOT NULL,source VARCHAR(32) NOT NULL DEFAULT 'finance',created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),PRIMARY KEY(id),KEY idx_bank_settlement_history_request(request_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS merchant_settlements (
 id CHAR(26) NOT NULL,request_id CHAR(26) NOT NULL,merchant_id CHAR(26) NULL,status VARCHAR(32) NOT NULL DEFAULT 'not_started',settlement_date DATE NULL,cycle_id CHAR(26) NULL,note VARCHAR(1000) NULL,actor_user_id CHAR(26) NOT NULL,source VARCHAR(32) NOT NULL DEFAULT 'finance',row_version BIGINT UNSIGNED NOT NULL DEFAULT 1,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),PRIMARY KEY(id),UNIQUE KEY uq_merchant_settlement_request(request_id),KEY idx_merchant_settlement_merchant(merchant_id,status,settlement_date),KEY idx_merchant_settlement_cycle(cycle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS merchant_settlement_history (
 id CHAR(26) NOT NULL,request_id CHAR(26) NOT NULL,merchant_id CHAR(26) NULL,status VARCHAR(32) NOT NULL,settlement_date DATE NULL,cycle_id CHAR(26) NULL,note VARCHAR(1000) NULL,actor_user_id CHAR(26) NOT NULL,source VARCHAR(32) NOT NULL DEFAULT 'finance',created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),PRIMARY KEY(id),KEY idx_merchant_settlement_history_request(request_id,created_at),KEY idx_merchant_settlement_history_cycle(cycle_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS financier_payments (
 id CHAR(26) NOT NULL,request_id CHAR(26) NOT NULL,status VARCHAR(32) NOT NULL DEFAULT 'unreviewed',note VARCHAR(1000) NULL,actor_user_id CHAR(26) NOT NULL,source VARCHAR(32) NOT NULL DEFAULT 'finance',row_version BIGINT UNSIGNED NOT NULL DEFAULT 1,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),PRIMARY KEY(id),UNIQUE KEY uq_financier_payment_request(request_id),KEY idx_financier_payment_status(status,updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS financier_payment_history (
 id CHAR(26) NOT NULL,request_id CHAR(26) NOT NULL,status VARCHAR(32) NOT NULL,note VARCHAR(1000) NULL,actor_user_id CHAR(26) NOT NULL,source VARCHAR(32) NOT NULL DEFAULT 'finance',created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),PRIMARY KEY(id),KEY idx_financier_payment_history_request(request_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
 }
};
