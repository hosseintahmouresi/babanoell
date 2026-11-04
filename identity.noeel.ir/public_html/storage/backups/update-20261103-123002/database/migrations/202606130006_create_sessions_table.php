<?php
declare(strict_types=1);

use Babanoell\Auth\Database\Migration;

return new class implements Migration {
    public function version(): string
    {
        return '202606130006';
    }

    public function name(): string
    {
        return 'create_sessions_table';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS sessions (
    id CHAR(26) NOT NULL,
    user_id CHAR(26) NOT NULL,
    refresh_token_hash VARCHAR(255) NOT NULL,
    token_family_id CHAR(26) NOT NULL,
    user_agent_hash VARCHAR(255) NULL,
    ip_address_hash VARCHAR(255) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    last_used_at DATETIME(6) NULL,
    access_expires_at DATETIME(6) NOT NULL,
    refresh_expires_at DATETIME(6) NOT NULL,
    revoked_at DATETIME(6) NULL,
    revoked_reason VARCHAR(128) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_auth_sessions_refresh_token_hash (refresh_token_hash),
    KEY idx_auth_sessions_user (user_id),
    KEY idx_auth_sessions_token_family (token_family_id),
    KEY idx_auth_sessions_refresh_expires (refresh_expires_at),
    KEY idx_auth_sessions_revoked_at (revoked_at),
    CONSTRAINT fk_auth_sessions_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
};
