<?php
declare(strict_types=1);

use Babanoell\Auth\Database\Migration;

return new class implements Migration {
    public function version(): string
    {
        return '202608250001';
    }

    public function name(): string
    {
        return 'add_access_token_hash_to_sessions_table';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
ALTER TABLE sessions
    ADD COLUMN access_token_hash VARCHAR(255) NULL AFTER refresh_token_hash
SQL);

        $pdo->exec(<<<'SQL'
CREATE UNIQUE INDEX uq_auth_sessions_access_token_hash
    ON sessions (access_token_hash)
SQL);
    }
};
