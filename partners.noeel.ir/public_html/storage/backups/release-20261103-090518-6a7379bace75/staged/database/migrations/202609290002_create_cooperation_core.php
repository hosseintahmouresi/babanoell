<?php
declare(strict_types=1);
use Babanoell\Partners\Database\Migration;
return new class implements Migration{
 public function version():string{return'202609290002';}public function name():string{return'create_cooperation_core';}
 public function up(PDO$pdo):void{
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS cooperation_requests (
 id CHAR(26) NOT NULL,
 request_no VARCHAR(40) NOT NULL,
 idempotency_key VARCHAR(128) NULL,
 legacy_id BIGINT UNSIGNED NULL,
 applicant_user_id CHAR(26) NULL,
 merchant_id CHAR(26) NULL,
 request_type VARCHAR(32) NOT NULL DEFAULT 'individual',
 status VARCHAR(32) NOT NULL DEFAULT 'submitted',
 contract_token_hash CHAR(64) NULL,
 contract_signed_status VARCHAR(24) NOT NULL DEFAULT 'pending',
 contract_signed_at DATETIME(6) NULL,
 contract_expires_at DATETIME(6) NULL,
 otp_hash VARCHAR(255) NULL,
 otp_expires_at DATETIME(6) NULL,
 data_json JSON NULL,
 decision_note TEXT NULL,
 assigned_user_id CHAR(26) NULL,
 row_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
 created_by_user_id CHAR(26) NULL,
 submitted_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 decided_at DATETIME(6) NULL,
 decided_by_user_id CHAR(26) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id),
 UNIQUE KEY uq_cooperation_request_no(request_no),
 UNIQUE KEY uq_cooperation_idempotency(created_by_user_id,idempotency_key),
 KEY idx_cooperation_status_type(status,request_type,updated_at),
 KEY idx_cooperation_applicant(applicant_user_id,created_at),
 KEY idx_cooperation_merchant(merchant_id,status),
 KEY idx_cooperation_assigned(assigned_user_id,status),
 KEY idx_cooperation_legacy(legacy_id),
 CONSTRAINT fk_cooperation_merchant FOREIGN KEY(merchant_id) REFERENCES merchants(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT chk_cooperation_type CHECK(request_type IN ('individual','legal')),
 CONSTRAINT chk_cooperation_status CHECK(status IN ('submitted','under_review','need_correction','approved','rejected','cancelled','archived')),
 CONSTRAINT chk_cooperation_contract_status CHECK(contract_signed_status IN ('pending','sent','signed','expired','cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS cooperation_documents (
 cooperation_request_id CHAR(26) NOT NULL,
 document_id CHAR(26) NOT NULL,
 relation_code VARCHAR(64) NOT NULL DEFAULT 'attachment',
 created_by_user_id CHAR(26) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(cooperation_request_id,document_id,relation_code),
 KEY idx_cooperation_documents_document(document_id),
 CONSTRAINT fk_cooperation_documents_request FOREIGN KEY(cooperation_request_id) REFERENCES cooperation_requests(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
 }
};
