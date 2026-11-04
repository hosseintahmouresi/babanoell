<?php
declare(strict_types=1);

use Babanoell\Auth\Database\Migration;

return new class implements Migration {
    public function version(): string
    {
        return '202606130008';
    }

    public function name(): string
    {
        return 'create_two_factor_challenges_table';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS two_factor_challenges (
    id CHAR(26) NOT NULL,
    user_id CHAR(26) NOT NULL,
    purpose VARCHAR(64) NOT NULL,
    challenge_hash VARCHAR(255) NOT NULL,
    delivery_channel VARCHAR(64) NOT NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    expires_at DATETIME(6) NOT NULL,
    verified_at DATETIME(6) NULL,
    locked_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    KEY idx_auth_2fa_user (user_id),
    KEY idx_auth_2fa_expires_at (expires_at),
    KEY idx_auth_2fa_verified_at (verified_at),
    CONSTRAINT fk_auth_2fa_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
};
