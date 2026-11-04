<?php
declare(strict_types=1);

use Babanoell\Auth\Database\Migration;

return new class implements Migration {
    public function version(): string
    {
        return '202606130007';
    }

    public function name(): string
    {
        return 'create_password_resets_table';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS password_resets (
    id CHAR(26) NOT NULL,
    user_id CHAR(26) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    requested_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    expires_at DATETIME(6) NOT NULL,
    used_at DATETIME(6) NULL,
    request_ip_hash VARCHAR(255) NULL,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_auth_password_resets_token_hash (token_hash),
    KEY idx_auth_password_resets_user (user_id),
    KEY idx_auth_password_resets_expires_at (expires_at),
    CONSTRAINT fk_auth_password_resets_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
};
