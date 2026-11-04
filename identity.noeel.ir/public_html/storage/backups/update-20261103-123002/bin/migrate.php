<?php
declare(strict_types=1);

use Babanoell\Auth\Database\Connection;
use Babanoell\Auth\Database\MigrationRunner;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Auth migrations are CLI-only.\n");
    exit(1);
}

$serviceRoot = dirname(__DIR__);

require_once $serviceRoot . '/config/env.php';
babanoell_auth_load_env();

require $serviceRoot . '/src/Database/Connection.php';
require $serviceRoot . '/src/Database/Migration.php';
require $serviceRoot . '/src/Database/MigrationRunner.php';

$command = $argv[1] ?? 'help';
$validCommands = ['status', 'up', 'help'];

if (!in_array($command, $validCommands, true)) {
    fwrite(STDERR, "Unknown command: {$command}\n");
    printUsage();
    exit(1);
}

if ($command === 'help') {
    printUsage();
    exit(0);
}

try {
    $config = require $serviceRoot . '/config/database.php';
    $pdo = Connection::fromConfig($config);
    $runner = new MigrationRunner($pdo, $serviceRoot . '/database/migrations');

    if ($command === 'status') {
        printStatus($runner->status());
        exit(0);
    }

    $applied = $runner->up();

    if ($applied === []) {
        echo "No pending auth-service migrations.\n";
        exit(0);
    }

    foreach ($applied as $migration) {
        echo sprintf("Applied %s %s\n", $migration['version'], $migration['name']);
    }

    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "Auth migration command failed: {$exception->getMessage()}\n");
    exit(1);
}

function printUsage(): void
{
    echo "Usage: php services/auth-service/bin/migrate.php [status|up]\n";
    echo "Commands:\n";
    echo "  status  Show applied and pending auth-service migrations.\n";
    echo "  up      Apply pending auth-service migrations.\n";
}

/**
 * @param array{tracking_table_exists:bool,migrations:array<int,array{version:string,name:string,status:string}>} $status
 */
function printStatus(array $status): void
{
    echo "Auth-service migration status\n";
    echo 'Tracking table: ' . ($status['tracking_table_exists'] ? 'present' : 'missing') . "\n";
    echo str_pad('Version', 16) . str_pad('Status', 12) . "Name\n";

    foreach ($status['migrations'] as $migration) {
        echo str_pad($migration['version'], 16) . str_pad($migration['status'], 12) . $migration['name'] . "\n";
    }
}
