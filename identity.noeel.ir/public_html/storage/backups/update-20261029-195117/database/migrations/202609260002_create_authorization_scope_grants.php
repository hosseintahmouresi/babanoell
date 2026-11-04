<?php
declare(strict_types=1);
use Babanoell\Auth\Database\Migration;
return new class implements Migration {
 public function version(): string { return '202609260002'; }
 public function name(): string { return 'create_authorization_scope_grants'; }
 public function up(PDO $pdo): void {
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS authorization_scope_grants (
 id CHAR(26) NOT NULL,
 user_id CHAR(26) NOT NULL,
 scope_type VARCHAR(32) NOT NULL,
 scope_id VARCHAR(190) NOT NULL,
 effect VARCHAR(16) NOT NULL DEFAULT 'allow',
 attributes_json JSON NULL,
 expires_at DATETIME(6) NULL,
 created_by_user_id CHAR(26) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY (id),
 UNIQUE KEY uq_auth_scope_grant (user_id, scope_type, scope_id, effect),
 KEY idx_auth_scope_user (user_id, effect, expires_at),
 KEY idx_auth_scope_lookup (scope_type, scope_id, effect),
 CONSTRAINT fk_auth_scope_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_auth_scope_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
 CONSTRAINT chk_auth_scope_effect CHECK (effect IN ('allow','deny')),
 CONSTRAINT chk_auth_scope_type CHECK (scope_type IN ('global','organization','branch','merchant','loan_request'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
 }
};
