<?php
declare(strict_types=1);
use Babanoell\Finance\Database\Migration;
return new class implements Migration{
 public function version():string{return'202610010002';}public function name():string{return'create_cycles_receipts';}
 public function up(PDO$pdo):void{
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS settlement_cycles (
 id CHAR(26) NOT NULL,cycle_no VARCHAR(40) NOT NULL,merchant_id CHAR(26) NOT NULL,status VARCHAR(32) NOT NULL DEFAULT 'open',settlement_date DATE NULL,total_amount DECIMAL(20,2) NOT NULL DEFAULT 0,leasing_total DECIMAL(20,2) NOT NULL DEFAULT 0,merchant_total DECIMAL(20,2) NOT NULL DEFAULT 0,description TEXT NULL,created_by_user_id CHAR(26) NOT NULL,closed_by_user_id CHAR(26) NULL,closed_at DATETIME(6) NULL,reopened_by_user_id CHAR(26) NULL,reopened_at DATETIME(6) NULL,row_version BIGINT UNSIGNED NOT NULL DEFAULT 1,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),PRIMARY KEY(id),UNIQUE KEY uq_settlement_cycles_no(cycle_no),KEY idx_settlement_cycles_merchant(merchant_id,status,settlement_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS settlement_cycle_items (
 cycle_id CHAR(26) NOT NULL,request_id CHAR(26) NOT NULL,settlement_amount DECIMAL(20,2) NOT NULL,leasing_amount DECIMAL(20,2) NOT NULL DEFAULT 0,merchant_amount DECIMAL(20,2) NOT NULL DEFAULT 0,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),PRIMARY KEY(cycle_id,request_id),UNIQUE KEY uq_settlement_item_request(request_id),KEY idx_settlement_item_request(request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS financial_receipts (
 id CHAR(26) NOT NULL,request_id CHAR(26) NULL,cycle_id CHAR(26) NULL,receipt_type VARCHAR(32) NOT NULL DEFAULT 'transfer',amount DECIMAL(20,2) NULL,document_id CHAR(26) NOT NULL,status VARCHAR(32) NOT NULL DEFAULT 'pending',external_reference VARCHAR(190) NULL,submitted_by_user_id CHAR(26) NOT NULL,reviewed_by_user_id CHAR(26) NULL,review_note VARCHAR(1000) NULL,submitted_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),reviewed_at DATETIME(6) NULL,PRIMARY KEY(id),KEY idx_financial_receipts_request(request_id,status,submitted_at),KEY idx_financial_receipts_cycle(cycle_id,status,submitted_at),KEY idx_financial_receipts_document(document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
 }
};
