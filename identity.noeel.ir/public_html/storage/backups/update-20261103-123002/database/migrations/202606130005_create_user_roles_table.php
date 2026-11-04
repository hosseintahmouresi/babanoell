<?php
declare(strict_types=1);

use Babanoell\Auth\Database\Migration;

return new class implements Migration {
    public function version(): string
    {
        return '202606130005';
    }

    public function name(): string
    {
        return 'create_user_roles_table';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_roles (
    user_id CHAR(26) NOT NULL,
    role_id CHAR(26) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    created_by_user_id CHAR(26) NULL,
    expires_at DATETIME(6) NULL,
    PRIMARY KEY (user_id, role_id),
    KEY idx_auth_user_roles_role (role_id),
    KEY idx_auth_user_roles_created_by (created_by_user_id),
    KEY idx_auth_user_roles_expires_at (expires_at),
    CONSTRAINT fk_auth_user_roles_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_auth_user_roles_role
        FOREIGN KEY (role_id) REFERENCES roles (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_auth_user_roles_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
};
