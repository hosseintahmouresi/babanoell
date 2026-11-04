<?php
declare(strict_types=1);
use Babanoell\Partners\Database\Migration;
return new class implements Migration{
 public function version():string{return'202609290003';}public function name():string{return'create_marketplace_and_audit_core';}
 public function up(PDO$pdo):void{
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS marketplace_claims (
 id CHAR(26) NOT NULL,
 loan_request_id CHAR(26) NOT NULL,
 merchant_id CHAR(26) NOT NULL,
 claimed_by_user_id CHAR(26) NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'claimed',
 source VARCHAR(32) NOT NULL DEFAULT 'marketplace',
 claimed_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 released_at DATETIME(6) NULL,
 released_by_user_id CHAR(26) NULL,
 release_reason VARCHAR(500) NULL,
 metadata_json JSON NULL,
 PRIMARY KEY(id),
 UNIQUE KEY uq_marketplace_request(loan_request_id),
 KEY idx_marketplace_claims_merchant(merchant_id,status,claimed_at),
 CONSTRAINT fk_marketplace_claim_merchant FOREIGN KEY(merchant_id) REFERENCES merchants(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT chk_marketplace_claim_status CHECK(status IN ('claimed','released','cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS merchant_invitations (
 id CHAR(26) NOT NULL,
 merchant_id CHAR(26) NOT NULL,
 invitation_code_hash CHAR(64) NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'active',
 created_by_user_id CHAR(26) NOT NULL,
 used_by_user_id CHAR(26) NULL,
 loan_request_id CHAR(26) NULL,
 expires_at DATETIME(6) NULL,
 used_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id),
 UNIQUE KEY uq_merchant_invitation_hash(invitation_code_hash),
 KEY idx_merchant_invitations_merchant(merchant_id,status,created_at),
 CONSTRAINT fk_merchant_invitation_merchant FOREIGN KEY(merchant_id) REFERENCES merchants(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT chk_merchant_invitation_status CHECK(status IN ('active','used','revoked','expired'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS partner_events (
 id CHAR(26) NOT NULL,
 aggregate_type VARCHAR(64) NOT NULL,
 aggregate_id CHAR(26) NULL,
 event_type VARCHAR(100) NOT NULL,
 actor_user_id CHAR(26) NULL,
 result VARCHAR(24) NOT NULL DEFAULT 'allowed',
 details_json JSON NULL,
 request_id VARCHAR(128) NULL,
 caller_service VARCHAR(32) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id),
 KEY idx_partner_events_aggregate(aggregate_type,aggregate_id,created_at),
 KEY idx_partner_events_actor(actor_user_id,created_at),
 KEY idx_partner_events_request(request_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
 }
};
