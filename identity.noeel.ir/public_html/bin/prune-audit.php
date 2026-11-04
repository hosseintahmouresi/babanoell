<?php
declare(strict_types=1);

/**
 * Auth audit log retention pruning.
 *
 * Deletes auth_audit_logs rows older than AUTH_AUDIT_RETENTION_DAYS
 * (default 365). Runs CLI-only so it can be wired to a cPanel cron job.
 *
 * Usage:
 *   php bin/prune-audit.php status    Show retention, cutoff, and row count (default).
 *   php bin/prune-audit.php execute   Delete rows older than the cutoff.
 *   php bin/prune-audit.php help
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Audit pruning is CLI-only.\n");
    exit(1);
}

$serviceRoot = dirname(__DIR__);

require_once $serviceRoot . '/config/env.php';
babanoell_auth_load_env();

require $serviceRoot . '/src/Database/Connection.php';

$command = $argv[1] ?? 'status';
$validCommands = ['status', 'execute', 'help'];

if (!in_array($command, $validCommands, true)) {
    fwrite(STDERR, "Unknown command: {$command}\n");
    printUsage();
    exit(1);
}

if ($command === 'help') {
    printUsage();
    exit(0);
}

$retentionDays = retentionDays();
$cutoff = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
    ->sub(new DateInterval('P' . $retentionDays . 'D'));

try {
    $config = require $serviceRoot . '/config/database.php';
    $pdo = Connection::fromConfig($config);

    $countStatement = $pdo->prepare('SELECT COUNT(*) FROM auth_audit_logs WHERE created_at < :cutoff');
    $countStatement->execute(['cutoff' => $cutoff->format('Y-m-d H:i:s.u')]);
    $affected = (int) $countStatement->fetchColumn();

    echo sprintf(
        "Retention: %d days\nCutoff (UTC): %s\nExpired audit rows: %d\n",
        $retentionDays,
        $cutoff->format('Y-m-d H:i:s'),
        $affected,
    );

    if ($command === 'execute') {
        $deleteStatement = $pdo->prepare('DELETE FROM auth_audit_logs WHERE created_at < :cutoff');
        $deleteStatement->execute(['cutoff' => $cutoff->format('Y-m-d H:i:s.u')]);

        echo sprintf("Deleted audit rows: %d\n", $deleteStatement->rowCount());
    } else {
        echo "Dry run only. Run with 'execute' to delete.\n";
    }

    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "Audit pruning failed: {$exception->getMessage()}\n");
    exit(1);
}

function printUsage(): void
{
    echo "Usage: php bin/prune-audit.php [status|execute]\n";
    echo "Commands:\n";
    echo "  status   Show retention days, cutoff timestamp, and expired row count.\n";
    echo "  execute  Delete auth_audit_logs rows older than the retention cutoff.\n";
    echo "Retention is configured with AUTH_AUDIT_RETENTION_DAYS (default 365).\n";
}

function retentionDays(): int
{
    $value = function_exists('babanoell_auth_env')
        ? babanoell_auth_env('AUTH_AUDIT_RETENTION_DAYS', '365')
        : (function_exists('getenv') ? getenv('AUTH_AUDIT_RETENTION_DAYS') : false);

    if ($value === false || $value === null || $value === '' || filter_var($value, FILTER_VALIDATE_INT) === false) {
        return 365;
    }

    return max(1, (int) $value);
}
