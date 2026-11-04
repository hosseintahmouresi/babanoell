<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;

return new class implements Migration {
    public function version(): string { return '202609260002'; }
    public function name(): string { return 'create_merchant_core'; }
    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS merchants (
    id CHAR(26) NOT NULL,
    code VARCHAR(80) NOT NULL,
    name VARCHAR(190) NOT NULL,
    legal_name VARCHAR(255) NULL,
    owner_user_id CHAR(26) NULL,
    organization_ref CHAR(26) NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    cooperation_status VARCHAR(32) NOT NULL DEFAULT 'approved',
    credit_level VARCHAR(32) NULL,
    financial_settings_json JSON NULL,
    metadata_json JSON NULL,
    legacy_user_id BIGINT UNSIGNED NULL,
    legacy_profile_ref VARCHAR(190) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_merchants_code (code),
    KEY idx_merchants_owner (owner_user_id, status),
    KEY idx_merchants_organization (organization_ref, status),
    KEY idx_merchants_legacy_user (legacy_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS merchant_user_memberships (
    merchant_id CHAR(26) NOT NULL,
    user_id CHAR(26) NOT NULL,
    membership_role VARCHAR(32) NOT NULL DEFAULT 'operator',
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    created_by_user_id CHAR(26) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (merchant_id, user_id),
    KEY idx_merchant_memberships_user (user_id, status),
    CONSTRAINT fk_merchant_memberships_merchant FOREIGN KEY (merchant_id) REFERENCES merchants(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS merchant_plan_permissions (
    merchant_id CHAR(26) NOT NULL,
    plan_id CHAR(26) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    limits_json JSON NULL,
    created_by_user_id CHAR(26) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (merchant_id, plan_id),
    KEY idx_merchant_plan_permissions_plan (plan_id, status),
    CONSTRAINT fk_merchant_plan_permissions_merchant FOREIGN KEY (merchant_id) REFERENCES merchants(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_merchant_plan_permissions_plan FOREIGN KEY (plan_id) REFERENCES installment_plans(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
};
