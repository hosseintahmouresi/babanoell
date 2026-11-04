<?php
declare(strict_types=1);

use Babanoell\Auth\Database\Migration;

return new class implements Migration {
    public function version(): string
    {
        return '202606130004';
    }

    public function name(): string
    {
        return 'create_role_permissions_table';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id CHAR(26) NOT NULL,
    permission_id CHAR(26) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    created_by_user_id CHAR(26) NULL,
    PRIMARY KEY (role_id, permission_id),
    KEY idx_auth_role_permissions_permission (permission_id),
    KEY idx_auth_role_permissions_created_by (created_by_user_id),
    CONSTRAINT fk_auth_role_permissions_role
        FOREIGN KEY (role_id) REFERENCES roles (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_auth_role_permissions_permission
        FOREIGN KEY (permission_id) REFERENCES permissions (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_auth_role_permissions_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
};
