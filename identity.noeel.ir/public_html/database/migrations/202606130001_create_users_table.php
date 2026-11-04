<?php
declare(strict_types=1);

use Babanoell\Auth\Database\Migration;

return new class implements Migration {
    public function version(): string
    {
        return '202606130001';
    }

    public function name(): string
    {
        return 'create_users_table';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id CHAR(26) NOT NULL,
    mobile VARCHAR(32) NULL,
    email VARCHAR(255) NULL,
    display_name VARCHAR(255) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    password_hash VARCHAR(255) NULL,
    password_changed_at DATETIME(6) NULL,
    last_login_at DATETIME(6) NULL,
    locked_until DATETIME(6) NULL,
    disabled_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_auth_users_mobile (mobile),
    UNIQUE KEY uq_auth_users_email (email),
    KEY idx_auth_users_status (status),
    KEY idx_auth_users_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
};
