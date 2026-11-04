<?php
declare(strict_types=1);

namespace Babanoell\Auth\Database;

use PDO;
use RuntimeException;
use Throwable;

final class MigrationRunner
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $migrationsPath
    ) {
    }

    /**
     * @return array{tracking_table_exists:bool,migrations:array<int,array{version:string,name:string,status:string}>}
     */
    public function status(): array
    {
        $trackingTableExists = $this->hasSchemaMigrationsTable();
        $appliedVersions = $trackingTableExists ? $this->appliedVersions() : [];
        $migrations = [];

        foreach ($this->loadMigrations() as $migration) {
            $migrations[] = [
                'version' => $migration->version(),
                'name' => $migration->name(),
                'status' => isset($appliedVersions[$migration->version()]) ? 'applied' : 'pending',
            ];
        }

        return [
            'tracking_table_exists' => $trackingTableExists,
            'migrations' => $migrations,
        ];
    }

    /**
     * @return array<int,array{version:string,name:string}>
     */
    public function up(): array
    {
        $this->ensureSchemaMigrationsTable();

        $appliedVersions = $this->appliedVersions();
        $appliedNow = [];

        foreach ($this->loadMigrations() as $migration) {
            if (isset($appliedVersions[$migration->version()])) {
                continue;
            }

            try {
                $migration->up($this->pdo);
                $this->recordMigration($migration);
                $appliedNow[] = [
                    'version' => $migration->version(),
                    'name' => $migration->name(),
                ];
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    sprintf('Migration %s (%s) failed: %s', $migration->version(), $migration->name(), $exception->getMessage()),
                    0,
                    $exception
                );
            }
        }

        return $appliedNow;
    }

    private function hasSchemaMigrationsTable(): bool
    {
        $statement = $this->pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'schema_migrations'"
        );

        return (int) $statement->fetchColumn() === 1;
    }

    private function ensureSchemaMigrationsTable(): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(32) NOT NULL,
    name VARCHAR(255) NOT NULL,
    applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    /**
     * @return array<string,true>
     */
    private function appliedVersions(): array
    {
        $versions = [];
        $statement = $this->pdo->query('SELECT version FROM schema_migrations ORDER BY version ASC');

        foreach ($statement->fetchAll() as $row) {
            $versions[(string) $row['version']] = true;
        }

        return $versions;
    }

    /**
     * @return array<int,Migration>
     */
    private function loadMigrations(): array
    {
        if (!is_dir($this->migrationsPath)) {
            throw new RuntimeException("Migration directory does not exist: {$this->migrationsPath}");
        }

        $files = glob($this->migrationsPath . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files, SORT_STRING);

        $migrations = [];
        $versions = [];

        foreach ($files as $file) {
            $migration = require $file;

            if (!$migration instanceof Migration) {
                throw new RuntimeException("Migration file must return an implementation of Migration: {$file}");
            }

            if (!preg_match('/^\d{12}$/', $migration->version())) {
                throw new RuntimeException("Migration version must be a 12 digit timestamp-like value: {$file}");
            }

            if (isset($versions[$migration->version()])) {
                throw new RuntimeException("Duplicate migration version found: {$migration->version()}");
            }

            $versions[$migration->version()] = true;
            $migrations[] = $migration;
        }

        return $migrations;
    }

    private function recordMigration(Migration $migration): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO schema_migrations (version, name) VALUES (:version, :name)'
        );

        $statement->execute([
            'version' => $migration->version(),
            'name' => $migration->name(),
        ]);
    }
}
