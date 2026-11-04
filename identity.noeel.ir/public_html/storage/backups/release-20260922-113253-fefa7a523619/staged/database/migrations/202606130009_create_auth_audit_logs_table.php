<?php
declare(strict_types=1);

use Babanoell\Auth\Database\Migration;

return new class implements Migration {
    public function version(): string
    {
        return '202606130009';
    }

    public function name(): string
    {
        return 'create_auth_audit_logs_table';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS auth_audit_logs (
    id CHAR(26) NOT NULL,
    event_type VARCHAR(128) NOT NULL,
    actor_user_id CHAR(26) NULL,
    target_user_id CHAR(26) NULL,
    session_id CHAR(26) NULL,
    ip_address_hash VARCHAR(255) NULL,
    user_agent_hash VARCHAR(255) NULL,
    result VARCHAR(32) NOT NULL,
    metadata_json JSON NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_auth_audit_event_type (event_type),
    KEY idx_auth_audit_actor_user (actor_user_id),
    KEY idx_auth_audit_target_user (target_user_id),
    KEY idx_auth_audit_session (session_id),
    KEY idx_auth_audit_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
};
