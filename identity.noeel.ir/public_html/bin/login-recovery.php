<?php
declare(strict_types=1);

// Support-only: CLI access to the selected account's throttle counter.
// Not an HTTP endpoint; never accepts password, tokens, or secrets.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
$mode = $argv[1] ?? '--status';
if (!in_array($mode, ['--status', '--unlock'], true) || count($argv) > 2) {
    fwrite(STDERR, "Usage: php bin/login-recovery.php [--status|--unlock]\n");
    exit(2);
}
$path = dirname(__DIR__) . '/storage/.login-rate-limit.json';
if (is_link($path)) { fwrite(STDERR, "Refusing symlinked limiter state.\n"); exit(1); }
if (!is_dir(dirname($path)) || !is_writable(dirname($path))) {
    fwrite(STDERR, "Identity storage directory is missing or not writable by this PHP user. Check ownership and permissions.\n");
    exit(1);
}
if (!is_file($path)) { fwrite(STDOUT, "No persisted login throttle file exists. Check identity PHP logs if login still fails.\n"); exit(0); }
if (!is_writable($path) || !is_readable($path)) {
    fwrite(STDERR, "Limiter file is not readable/writable by this PHP user; fix ownership/permissions, not the security limit.\n");
    exit(1);
}
fwrite(STDOUT, "Enter the exact email/mobile used to sign in (not the password): ");
$identifier = fgets(STDIN);
if (!is_string($identifier) || trim($identifier) === '') { fwrite(STDERR, "Account identifier required.\n"); exit(2); }
$identifier = strtolower(trim($identifier));
$key = hash('sha256', 'auth.login:' . hash('sha256', $identifier));
unset($identifier);
$handle = fopen($path, 'c+b');
if (!is_resource($handle)) { fwrite(STDERR, "Cannot open limiter file.\n"); exit(1); }
try {
    if (!flock($handle, LOCK_EX)) throw new RuntimeException('Cannot lock limiter file');
    $raw = stream_get_contents($handle, 2097153);
    if (!is_string($raw) || strlen($raw) > 2097152) throw new RuntimeException('Limiter state is invalid or too large');
    $states = $raw === '' ? [] : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($states)) throw new RuntimeException('Limiter state is not an object');
    $entry = $states[$key] ?? null;
    if ($entry === null) { echo "No throttle entry for that account. No changes made.\n"; exit(0); }
    if (!is_array($entry) || !is_int($entry['until'] ?? null) || !is_int($entry['count'] ?? null)) {
        throw new RuntimeException('Limiter state is invalid; leaving all entries untouched');
    }
    $retry = max(0, $entry['until'] - time());
    echo 'Attempts in current window: ' . $entry['count'] . '; seconds until expiry: ' . $retry . "\n";
    if ($mode === '--status') { echo "Read-only inspection complete.\n"; exit(0); }
    echo "Type UNLOCK to clear only this account's throttle entry: ";
    if (trim((string) fgets(STDIN)) !== 'UNLOCK') { echo "Cancelled; no changes made.\n"; exit(2); }
    unset($states[$key]);
    $updated = json_encode($states, JSON_THROW_ON_ERROR);
    if (!rewind($handle) || fwrite($handle, $updated) !== strlen($updated) ||
        !ftruncate($handle, strlen($updated)) || !fflush($handle)) {
        throw new RuntimeException('Could not persist the updated limiter state');
    }
    echo "Selected account throttle cleared. All other accounts unchanged.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Recovery failed: ' . $e->getMessage() . "\n");
    exit(1);
} finally {
    flock($handle, LOCK_UN);
    fclose($handle);
}
