<?php
declare(strict_types=1);

use Babanoell\Auth\Database\Migration;

return new class implements Migration {
    public function version(): string { return '202609260001'; }
    public function name(): string { return 'create_business_scope_core'; }

    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS organizations (
    id CHAR(26) NOT NULL,
    code VARCHAR(80) NOT NULL,
    name VARCHAR(190) NOT NULL,
    organization_type VARCHAR(32) NOT NULL DEFAULT 'business',
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    metadata_json JSON NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_auth_organizations_code (code),
    KEY idx_auth_organizations_status_type (status, organization_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS branches (
    id CHAR(26) NOT NULL,
    organization_id CHAR(26) NOT NULL,
    code VARCHAR(80) NOT NULL,
    name VARCHAR(190) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    metadata_json JSON NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_auth_branches_org_code (organization_id, code),
    KEY idx_auth_branches_org_status (organization_id, status),
    CONSTRAINT fk_auth_branches_organization FOREIGN KEY (organization_id) REFERENCES organizations(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_organization_memberships (
    user_id CHAR(26) NOT NULL,
    organization_id CHAR(26) NOT NULL,
    branch_id CHAR(26) NULL,
    title VARCHAR(190) NULL,
    membership_status VARCHAR(24) NOT NULL DEFAULT 'active',
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    attributes_json JSON NULL,
    created_by_user_id CHAR(26) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (user_id, organization_id),
    KEY idx_auth_memberships_organization (organization_id, membership_status),
    KEY idx_auth_memberships_branch (branch_id, membership_status),
    KEY idx_auth_memberships_primary (user_id, is_primary),
    CONSTRAINT fk_auth_memberships_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_auth_memberships_organization FOREIGN KEY (organization_id) REFERENCES organizations(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_auth_memberships_branch FOREIGN KEY (branch_id) REFERENCES branches(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_auth_memberships_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_external_identities (
    id CHAR(26) NOT NULL,
    user_id CHAR(26) NOT NULL,
    provider VARCHAR(64) NOT NULL,
    external_id VARCHAR(190) NOT NULL,
    metadata_json JSON NULL,
    linked_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    last_verified_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_auth_external_identity (provider, external_id),
    KEY idx_auth_external_identity_user (user_id, provider),
    CONSTRAINT fk_auth_external_identity_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
};
