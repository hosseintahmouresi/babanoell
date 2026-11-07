<?php
declare(strict_types=1);

use Babanoell\Auth\Audit\AuditContext;
use Babanoell\Auth\Audit\AuditEvent;
use Babanoell\Auth\Audit\AuditEventType;
use Babanoell\Auth\Audit\PdoAuditWriter;
use Babanoell\Auth\Update\UpdateTool;
use Babanoell\Auth\Update\ExtractedPackageCollectorV122;
use Babanoell\Auth\Support\PersianDateTime;
use Babanoell\Auth\Support\IniSize;
use Babanoell\Auth\Platform\Ui;

/**
 * Identity (auth) service — secure web updater for shared hosting (cPanel).
 *
 * Replaces manual file uploading with a guarded, auditable flow:
 *
 *   1. Login restricted to the super_admin account (rate-limited, CSRF).
 *   2. The release ZIP must be placed at storage/update.zip (or uploaded here).
 *   3. Every ZIP entry is validated (identity/ prefix, no traversal, safe
 *      extensions, 5000 entries / 50 MiB limits, no secrets inside).
 *   4. Existing files that will be replaced are backed up under
 *      storage/backups/update-<timestamp>/ (last 3 backups are kept).
 *   5. New files are copied, then pending migrations run.
 *   6. On any failure the previous files are restored automatically.
 *   7. The ZIP is deleted after a successful update; everything is logged to
 *      storage/update.log (no secrets, no credentials).
 *
 * Protected paths are never overwritten: config/local.env.php, storage/,
 * public/install.php, public/install.php.locked.
 *
 * Login and all other auth feature flags stay untouched by this tool.
 */

date_default_timezone_set('Asia/Tehran');

$serviceRoot = dirname(__DIR__);
$storageDir = $serviceRoot . '/storage';
$installedLock = $storageDir . '/installed.lock';
$localEnvFile = $serviceRoot . '/config/local.env.php';

require_once $serviceRoot . '/config/env.php';
babanoell_auth_load_env();

spl_autoload_register(static function (string $class) use ($serviceRoot): void {
    $prefix = 'Babanoell\\Auth\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $serviceRoot . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store, max-age=0');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; img-src 'self' data:; font-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'; connect-src 'self'");

@set_time_limit(900);
@ini_set('memory_limit', '256M');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_name('identity_update');
session_start();
require_once $serviceRoot.'/src/Platform/ProxyContext.php';
require_once $serviceRoot.'/src/Sso/UpdaterBridge.php';
NoelUpdaterSso::dispatch('identity', $serviceRoot);


$errors = [];
$notice = '';
$result = null;

try {
    if (!is_file($installedLock) || !is_file($localEnvFile)) {
        render_not_installed_page($serviceRoot);
        exit(0);
    }

    $action = (string) ($_POST['action'] ?? $_GET['action'] ?? '');
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method === 'POST') {
        if ($action === 'login') {
            $errors = handle_login($serviceRoot);
        } elseif ($action === 'logout') {
            if (!csrf_ok()) {
                $errors = ['درخواست خروج معتبر نبود. صفحه را دوباره بارگذاری کنید و دوباره تلاش کنید.'];
            } else {
                $_SESSION = [];
                session_regenerate_id(true);
                $notice = 'با موفقیت از بخش مدیریت خارج شدید.';
            }
        }
    }

    $admin = $_SESSION['update_admin'] ?? null;

    if (!is_array($admin) || !isset($admin['id'])) {
        render_login_page($errors, $notice);
        exit(0);
    }

    if ($method === 'POST') {
        if ($action === 'validate') {
            $result = handle_validate($storageDir, $serviceRoot);
        } elseif ($action === 'apply') {
            $result = handle_apply($storageDir, $serviceRoot);
        }
    }

    render_dashboard_page($serviceRoot, $storageDir, $result, $errors, $notice);
    exit(0);
} catch (Throwable $throwable) {
    render_error_page($throwable->getMessage());
    exit(0);
}

/* ---------------------------------------------------------------------
 * Handlers
 * ------------------------------------------------------------------- */

/**
 * @return list<string>
 */
function handle_login(string $serviceRoot): array
{
    try {
        $decision = rate_limit_check($serviceRoot . '/storage');

        if (!$decision['allowed']) {
            return ['به‌دلیل چند تلاش ناموفق، ورود موقتاً محدود شده است. حدود ' . PersianDateTime::digits((string) max(1, (int) ceil($decision['retry_after'] / 60))) . ' دقیقه دیگر دوباره تلاش کنید.'];
        }
    } catch (Throwable) {
        // Storage issues must never widen the attack surface silently:
        // fall back to a per-request limiter on top of CSRF.
    }

    if (!csrf_ok()) {
        rate_limit_record($serviceRoot . '/storage', true);

        return ['اعتبار این صفحه منقضی شده است. صفحه را تازه‌سازی کنید و دوباره تلاش کنید.'];
    }

    $identifier = normalize_identifier((string) ($_POST['identifier'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        return ['موبایل یا ایمیل مدیر و رمز عبور را کامل وارد کنید.'];
    }

    $verificationReason = null;

    try {
        $admin = verify_admin($serviceRoot, $identifier, $password, $verificationReason);
    } catch (Throwable $throwable) {
        // Installation can succeed on shared hosting even when later runtime
        // env loading fails (for example when putenv() is disabled). Never
        // misreport that situation as a bad password.
        UpdateTool::appendLog(
            $serviceRoot . '/storage',
            'updater auth database/runtime error: ' . get_class($throwable) . ': ' . $throwable->getMessage()
        );

        return [
            'ارتباط سرویس با پایگاه داده برقرار نشد؛ اطلاعات ورود شما بررسی نشد. '
            . 'گزارش فنی در storage/update.log ثبت شده است.',
        ];
    }

    if ($admin === null) {
        rate_limit_record($serviceRoot . '/storage', true);
        UpdateTool::appendLog(
            $serviceRoot . '/storage',
            'updater auth rejected: ' . ($verificationReason ?? 'invalid_credentials')
        );
        write_updater_audit($serviceRoot, null, 'failed', $verificationReason ?? 'invalid_credentials', client_source());

        return ['اطلاعات ورود صحیح نیست یا این حساب نقش مدیر ارشد سامانه را ندارد.'];
    }

    rate_limit_reset($serviceRoot . '/storage', $identifier);
    write_updater_audit($serviceRoot, $admin['id'], 'succeeded', 'login', client_source());

    session_regenerate_id(true);
    $_SESSION['update_admin'] = [
        'id' => $admin['id'],
        'name' => $admin['name'],
        'login_at' => gmdate('c'),
    ];

    return [];
}

/**
 * @return array{path:string,name:string,size:int}|null
 */
function find_update_zip(string $storageDir): ?array
{
    $candidates = [];

    foreach (['update.zip'] as $name) {
        $path = $storageDir . '/' . $name;

        if (is_file($path) && is_readable($path)) {
            $candidates[] = ['path' => $path, 'name' => $name, 'size' => (int) filesize($path)];
        }
    }

    $others = glob($storageDir . '/*.zip') ?: [];
    sort($others, SORT_STRING);

    foreach ($others as $path) {
        $name = basename($path);

        if (!in_array($name, ['update.zip'], true) && is_readable($path)) {
            $candidates[] = ['path' => $path, 'name' => $name, 'size' => (int) filesize($path)];
        }
    }

    if ($candidates === []) {
        return null;
    }

    // Prefer update.zip; otherwise the newest candidate by name.
    usort($candidates, static fn (array $a, array $b): int => strcmp($b['name'], $a['name']));

    foreach ($candidates as $candidate) {
        if ($candidate['name'] === 'update.zip') {
            return $candidate;
        }
    }

    return $candidates[0];
}

/**
 * @return array{ok:bool,plan:array<string,mixed>|null,error:?string,zip:array{path:string,name:string,size:int}|null,preserved:list<string>}
 */
function handle_validate(string $storageDir, string $serviceRoot): array
{
    if (!csrf_ok()) {
        return ['ok' => false, 'plan' => null, 'error' => 'اعتبار این صفحه منقضی شده است. صفحه را تازه‌سازی کنید و دوباره تلاش کنید.', 'zip' => null, 'preserved' => []];
    }

    try {
        $zip = save_uploaded_zip($storageDir);

        if ($zip === null) {
            $zip = find_update_zip($storageDir);
        }

        if ($zip === null) {
            return [
                'ok' => false,
                'plan' => null,
                'error' => 'بسته به‌روزرسانی پیدا نشد. فایل ZIP را انتخاب کنید یا آن را با نام storage/update.zip روی هاست قرار دهید.',
                'zip' => null,
                'preserved' => [],
            ];
        }

        $plan = UpdateTool::readArchive($zip['path']);
        $_SESSION['update_zip_path'] = $zip['path'];
        $_SESSION['update_zip_signature'] = hash_file('sha256', $zip['path']) ?: '';
        $_SESSION['update_plan_count'] = count($plan['files']);

        return [
            'ok' => true,
            'plan' => $plan,
            'error' => null,
            'zip' => $zip,
            'preserved' => ['config/local.env.php', 'storage/ (تمام محتوا)', 'public/install.php', 'public/install.php.locked'],
        ];
    } catch (Throwable $throwable) {
        return [
            'ok' => false,
            'plan' => null,
            'error' => 'بسته قابل تأیید نبود: ' . $throwable->getMessage(),
            'zip' => $zip ?? null,
            'preserved' => [],
        ];
    }
}

/**
 * @return array{ok:bool,applied:list<array{version:string,name:string}>,migration_names:list<string>,migration_total:int,copied:int,backup:string,error:?string,restored:bool}
 */
function handle_apply(string $storageDir, string $serviceRoot): array
{
    $payload = [
        'ok' => false,
        'applied' => [],
        'migration_names' => [],
        'migration_total' => 0,
        'copied' => 0,
        'backup' => '',
        'error' => null,
        'restored' => false,
    ];

    if (!csrf_ok()) {
        $payload['error'] = 'اعتبار این صفحه منقضی شده است. صفحه را تازه‌سازی کنید و دوباره تلاش کنید.';

        return $payload;
    }

    if (($_POST['backup_confirmed'] ?? '') !== '1') {
        $payload['error'] = 'قبل از اعمال نسخه، تهیه نسخه پشتیبان کامل از پایگاه داده را تأیید کنید.';

        return $payload;
    }

    $zip = find_update_zip($storageDir);

    if ($zip === null) {
        $payload['error'] = 'بسته به‌روزرسانی پیدا نشد. فایل ZIP را دوباره انتخاب کنید یا storage/update.zip را بررسی کنید.';

        return $payload;
    }

    $expectedSignature = (string) ($_SESSION['update_zip_signature'] ?? '');
    $currentSignature = hash_file('sha256', $zip['path']) ?: '';

    if ($expectedSignature === '' || $currentSignature === '' || !hash_equals($expectedSignature, $currentSignature)) {
        $payload['error'] = 'فایل بسته پس از بررسی قبلی تغییر کرده است یا هنوز تأیید نشده. دوباره بسته را بررسی کنید و سپس اعمال را انجام دهید.';

        return $payload;
    }

    $lockPath = $storageDir . '/update.lock';
    $lockHandle = fopen($lockPath, 'c');

    if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
        $payload['error'] = 'یک عملیات به‌روزرسانی دیگر در حال اجراست. پس از پایان آن دوباره تلاش کنید.';

        if (is_resource($lockHandle)) {
            fclose($lockHandle);
        }

        return $payload;
    }

    $workDir = '';
    $backupDir = '';

    try {
        UpdateTool::ensureStorageProtection($storageDir);
        UpdateTool::appendLog($storageDir, 'update started: ' . $zip['name']);

        // Re-validate the archive at apply time (the file may have changed).
        $plan = UpdateTool::readArchive($zip['path']);
        $payload['migration_total'] = $plan['migration_count'];

        $workDir = $storageDir . '/update-work-' . gmdate('YmdHis');
        @mkdir($workDir, 0755, true);

        $archive = new ZipArchive();

        if ($archive->open($zip['path']) !== true) {
            throw new RuntimeException('unable to open the ZIP archive');
        }

        try {
            if (!$archive->extractTo($workDir)) {
                throw new RuntimeException('unable to extract the ZIP archive');
            }
        } finally {
            $archive->close();
        }

        $packageRoot = $workDir . '/' . rtrim(UpdateTool::PACKAGE_PREFIX, '/');

        if (!is_dir($packageRoot)) {
            throw new RuntimeException('the identity folder is missing inside the ZIP');
        }

        $files = ExtractedPackageCollectorV122::collect($packageRoot);

        $targets = array_map(static fn (array $file): string => $file['target'], $files);
        $backupDir = UpdateTool::backupFiles($storageDir, $serviceRoot, $targets);
        $payload['backup'] = $backupDir;

        $copied = UpdateTool::copyFiles($files, $serviceRoot);
        $payload['copied'] = $copied;
        UpdateTool::appendLog($storageDir, 'files copied: ' . $copied);

        // Run migrations with the (now updated) code on disk.
        $pdo = db_pdo($serviceRoot);
        load_db_classes($serviceRoot);
        $runner = new Babanoell\Auth\Database\MigrationRunner($pdo, $serviceRoot . '/database/migrations');
        $applied = $runner->up();
        $payload['applied'] = $applied;
        $payload['migration_names'] = array_map(static fn (array $row): string => $row['version'] . ' ' . $row['name'], $applied);

        foreach ($applied as $row) {
            UpdateTool::appendLog($storageDir, 'migration applied: ' . $row['version'] . ' ' . $row['name']);
        }

        // Clean up the package and the work directory; delete the ZIP.
        UpdateTool::removePath($workDir);
        @unlink($zip['path']);
        UpdateTool::appendLog($storageDir, 'update finished successfully');

        $payload['ok'] = true;
        unset($_SESSION['update_zip_path'], $_SESSION['update_zip_signature'], $_SESSION['update_plan_count']);

        return $payload;
    } catch (Throwable $throwable) {
        // Restore previous files (the database itself is forward-only and
        // must be restored from the operator's cPanel backup if migrations
        // partially failed).
        $restored = false;

        if ($backupDir !== '' && is_dir($backupDir)) {
            try {
                $restored = UpdateTool::restoreFiles($backupDir, $serviceRoot) > 0;
            } catch (Throwable $restoreFailure) {
                UpdateTool::appendLog($storageDir, 'restore failed: ' . $restoreFailure->getMessage());
            }
        }

        if ($workDir !== '') {
            UpdateTool::removePath($workDir);
        }

        $payload['error'] = 'به‌روزرسانی ناموفق بود: ' . $throwable->getMessage();
        $payload['restored'] = $restored;
        UpdateTool::appendLog($storageDir, 'update failed: ' . $throwable->getMessage() . ($restored ? '; files restored' : '; files NOT restored'));

        return $payload;
    } finally {
        if (is_resource($lockHandle)) {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }
}

/**
 * @return array{id:string,name:string}|null
 */
function verify_admin(string $serviceRoot, string $identifier, string $password, ?string &$reason = null): ?array
{
    $reason = null;
    $pdo = db_pdo($serviceRoot);

    // Force a real round-trip before credential checks so a runtime/DB failure
    // is never silently converted into "wrong password".
    $pdo->query('SELECT 1')->fetchColumn();

    $statement = $pdo->prepare(
        'SELECT u.id, u.display_name, u.status, u.disabled_at, u.locked_until, u.password_hash
         FROM users u
         WHERE u.mobile = :mobile_identifier OR u.email = :email_identifier
         LIMIT 1'
    );
    $statement->execute([
        'mobile_identifier' => $identifier,
        'email_identifier' => $identifier,
    ]);
    $user = $statement->fetch();

    if ($user === false) {
        $reason = 'user_not_found';
        return null;
    }

    if ((string) $user['status'] !== 'active' || $user['disabled_at'] !== null) {
        $reason = 'account_inactive';
        return null;
    }

    if ($user['locked_until'] !== null && strtotime((string) $user['locked_until']) > time()) {
        $reason = 'account_locked';
        return null;
    }

    $storedHash = (string) $user['password_hash'];

    if ($storedHash === '' || !password_verify($password, $storedHash)) {
        $reason = 'password_mismatch';
        return null;
    }

    // Automatic hash upgrade after a successful login.
    $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;

    if (password_needs_rehash($storedHash, $algorithm)) {
        $upgradedHash = password_hash($password, $algorithm);

        if ($upgradedHash === false) {
            throw new RuntimeException('unable to create upgraded password hash');
        }

        $upgrade = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $upgrade->execute(['hash' => $upgradedHash, 'id' => $user['id']]);
    }

    $roleStatement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM user_roles ur
         JOIN roles r ON r.id = ur.role_id
         WHERE ur.user_id = :user_id AND r.name = :role_name'
    );
    $roleStatement->execute(['user_id' => $user['id'], 'role_name' => 'super_admin']);

    if ((int) $roleStatement->fetchColumn() < 1) {
        $reason = 'missing_super_admin_role';
        return null;
    }

    $reason = 'ok';

    return [
        'id' => (string) $user['id'],
        'name' => (string) $user['display_name'],
    ];
}

function write_updater_audit(string $serviceRoot, ?string $userId, string $result, string $reason, ?string $ip): void
{
    try {
        load_audit_classes($serviceRoot);
        $writer = new PdoAuditWriter(db_pdo($serviceRoot));

        $writer->write(new AuditEvent(
            $result === 'succeeded' ? AuditEventType::UpdaterLoginSucceeded : AuditEventType::UpdaterLoginFailed,
            $result,
            AuditContext::fromRawRequestData(
                actorUserId: $userId,
                targetUserId: $userId,
                ipAddress: $ip,
                userAgent: $_SERVER['HTTP_USER_AGENT'] ?? null,
            ),
            ['reason' => $reason],
        ));
    } catch (Throwable) {
        // Audit is best-effort for the updater; the file log still records it.
        UpdateTool::appendLog($serviceRoot . '/storage', 'audit write skipped: updater ' . $reason);
    }
}

function db_pdo(string $serviceRoot): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    require_once $serviceRoot . '/config/database.php';

    if (!class_exists('Babanoell\\Auth\\Database\\Connection')) {
        require_once $serviceRoot . '/src/Database/Connection.php';
    }

    $config = require $serviceRoot . '/config/database.php';
    $pdo = Babanoell\Auth\Database\Connection::fromConfig($config);

    return $pdo;
}

/**
 * Loads the migration runner classes exactly once (they may already be the
 * just-copied files when called after an update).
 */
function load_db_classes(string $serviceRoot): void
{
    $files = [
        'Migration' => $serviceRoot . '/src/Database/Migration.php',
        'MigrationRunner' => $serviceRoot . '/src/Database/MigrationRunner.php',
    ];

    foreach ($files as $class => $file) {
        if (!class_exists('Babanoell\\Auth\\Database\\' . $class)) {
            require_once $file;
        }
    }
}

function load_audit_classes(string $serviceRoot): void
{
    $files = [
        'AuditContext' => $serviceRoot . '/src/Audit/AuditContext.php',
        'AuditEvent' => $serviceRoot . '/src/Audit/AuditEvent.php',
        'AuditEventType' => $serviceRoot . '/src/Audit/AuditEventType.php',
        'PdoAuditWriter' => $serviceRoot . '/src/Audit/PdoAuditWriter.php',
    ];

    foreach ($files as $class => $file) {
        if (!class_exists('Babanoell\\Auth\\Audit\\' . $class)) {
            require_once $file;
        }
    }
}

/**
 * @return array{applied:int,pending:list<string>,error:?string}
 */
function migration_status(string $serviceRoot): array
{
    try {
        $pdo = db_pdo($serviceRoot);
        load_db_classes($serviceRoot);
        $runner = new Babanoell\Auth\Database\MigrationRunner($pdo, $serviceRoot . '/database/migrations');
        $status = $runner->status();
        $pending = [];

        foreach ($status['migrations'] as $migration) {
            if ($migration['status'] === 'pending') {
                $pending[] = $migration['version'] . ' — ' . $migration['name'];
            }
        }

        return [
            'applied' => count($status['migrations']) - count($pending),
            'pending' => $pending,
            'error' => null,
        ];
    } catch (Throwable $throwable) {
        return [
            'applied' => 0,
            'pending' => [],
            'error' => $throwable->getMessage(),
        ];
    }
}

/**
 * @return array{path:string,name:string,size:int}|null
 */
function save_uploaded_zip(string $storageDir): ?array
{
    if (!isset($_FILES['update_zip']) || !is_array($_FILES['update_zip'])) {
        return null;
    }

    $file = $_FILES['update_zip'];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('آپلود فایل ناموفق بود (کد خطا: ' . $file['error'] . ').');
    }

    if (($file['size'] ?? 0) > 52428800) {
        throw new RuntimeException('حجم فایل آپلودی بیشتر از ۵۰ مگابایت است.');
    }

    $temporary = (string) ($file['tmp_name'] ?? '');

    if ($temporary === '' || !is_uploaded_file($temporary)) {
        throw new RuntimeException('فایل آپلودی معتبر نیست.');
    }

    UpdateTool::ensureStorageProtection($storageDir);
    $target = $storageDir . '/update.zip';

    if (!move_uploaded_file($temporary, $target)) {
        throw new RuntimeException('ذخیرهٔ فایل آپلودی در storage ناموفق بود.');
    }

    @chmod($target, 0640);

    return ['path' => $target, 'name' => 'update.zip', 'size' => (int) filesize($target)];
}

/* ---------------------------------------------------------------------
 * Rate limiting (persisted in storage/)
 * ------------------------------------------------------------------- */

/**
 * @return array{allowed:bool,remaining:int,retry_after:int}
 */
function rate_limit_check(string $storageDir): array
{
    $state = rate_limit_state($storageDir);
    $key = rate_limit_key();
    $now = time();
    $window = 900;
    $max = 5;

    $entries = $state['entries'] ?? [];
    $entry = $entries[$key] ?? ['count' => 0, 'window_started_at' => $now, 'locked_until' => 0];

    if (($entry['locked_until'] ?? 0) > $now) {
        return ['allowed' => false, 'remaining' => 0, 'retry_after' => (int) $entry['locked_until'] - $now];
    }

    if (($entry['window_started_at'] ?? 0) < $now - $window) {
        $entry = ['count' => 0, 'window_started_at' => $now, 'locked_until' => 0];
    }

    $remaining = max(0, $max - (int) $entry['count']);

    return ['allowed' => $remaining > 0, 'remaining' => $remaining, 'retry_after' => 0];
}

function rate_limit_record(string $storageDir, bool $failure): void
{
    $state = rate_limit_state($storageDir);
    $key = rate_limit_key();
    $entry = $state['entries'][$key] ?? ['count' => 0, 'window_started_at' => time(), 'locked_until' => 0];

    if (($entry['window_started_at'] ?? 0) < time() - 900) {
        $entry = ['count' => 0, 'window_started_at' => time(), 'locked_until' => 0];
    }

    if ($failure) {
        $entry['count'] = (int) $entry['count'] + 1;

        if ($entry['count'] >= 5) {
            $entry['locked_until'] = time() + 900;
        }
    }

    $state['entries'][$key] = $entry;

    // Keep the file small: drop stale entries.
    foreach ($state['entries'] as $existingKey => $existingEntry) {
        if (($existingEntry['locked_until'] ?? 0) < time() - 1800 && ($existingEntry['window_started_at'] ?? 0) < time() - 1800) {
            unset($state['entries'][$existingKey]);
        }
    }

    rate_limit_save($storageDir, $state);
}

function rate_limit_reset(string $storageDir, string $identifier): void
{
    $state = rate_limit_state($storageDir);
    unset($state['entries'][rate_limit_key()]);
    rate_limit_save($storageDir, $state);
}

/**
 * @return array{entries:array<string,array{count:int,window_started_at:int,locked_until:int}>}
 */
function rate_limit_state(string $storageDir): array
{
    $file = $storageDir . '/.update-rate-limit.json';

    if (!is_file($file)) {
        return ['entries' => []];
    }

    $decoded = json_decode((string) file_get_contents($file), true);

    return is_array($decoded) && isset($decoded['entries']) && is_array($decoded['entries'])
        ? $decoded
        : ['entries' => []];
}

/**
 * @param array{entries:array<string,array{count:int,window_started_at:int,locked_until:int}>} $state
 */
function rate_limit_save(string $storageDir, array $state): void
{
    UpdateTool::ensureStorageProtection($storageDir);
    file_put_contents($storageDir . '/.update-rate-limit.json', json_encode(
        $state,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ), LOCK_EX);
}

function rate_limit_key(): string
{
    return substr(hash('sha256', 'identity-update|' . client_source()), 0, 32);
}

/* ---------------------------------------------------------------------
 * CSRF / helpers
 * ------------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['update_csrf'])) {
        $_SESSION['update_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['update_csrf'];
}

function csrf_ok(): bool
{
    $sessionToken = $_SESSION['update_csrf'] ?? '';

    return $sessionToken !== '' && hash_equals($sessionToken, (string) ($_POST['csrf'] ?? ''));
}

function client_source(): ?string
{
    $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

    if (is_string($forwardedFor) && trim($forwardedFor) !== '') {
        return trim(explode(',', $forwardedFor)[0]);
    }

    $realIp = $_SERVER['HTTP_X_REAL_IP'] ?? '';

    if (is_string($realIp) && trim($realIp) !== '') {
        return trim($realIp);
    }

    $remote = $_SERVER['REMOTE_ADDR'] ?? '';

    return is_string($remote) && $remote !== '' ? $remote : null;
}

function normalize_identifier(string $value): string
{
    $digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    $value = str_replace($persian, $digits, $value);
    $value = str_replace($arabic, $digits, $value);

    return strtolower(trim($value));
}

function human_size(int $bytes): string
{
    if ($bytes >= 1073741824) {
        return PersianDateTime::digits(number_format($bytes / 1073741824, 1)) . ' گیگابایت';
    }

    if ($bytes >= 1048576) {
        return PersianDateTime::digits(number_format($bytes / 1048576, 1)) . ' مگابایت';
    }

    if ($bytes >= 1024) {
        return PersianDateTime::digits(number_format($bytes / 1024, 1)) . ' کیلوبایت';
    }

    return PersianDateTime::digits((string) $bytes) . ' بایت';
}

function fa_number(int|string $value): string
{
    return PersianDateTime::digits((string) $value);
}

function direct_upload_limit_bytes(): int
{
    $upload = IniSize::toBytes(ini_get('upload_max_filesize'));
    $post = IniSize::toBytes(ini_get('post_max_size'));

    if ($upload <= 0) {
        $upload = 2 * 1024 * 1024;
    }
    if ($post <= 0) {
        $post = 8 * 1024 * 1024;
    }

    return min($upload, $post);
}

function admin_initial(string $name): string
{
    $name = trim($name);

    if ($name === '') {
        return 'م';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($name, 0, 1, 'UTF-8');
    }

    return substr($name, 0, 1);
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function render_technical_details(string $message): string
{
    return '<details class="bn-details"><summary>نمایش جزئیات فنی</summary><div class="bn-details-body"><code>' . e($message) . '</code></div></details>';
}

function render_footer(string $context): string
{
    $now = PersianDateTime::full(PersianDateTime::now());

    return '<footer class="bn-footer">'
        . '<span><strong>پلتفرم بابانوئل</strong> · ' . e($context) . '</span>'
        . '<time class="bn-footer-time" datetime="' . e(PersianDateTime::now()->format(DATE_ATOM)) . '">' . e($now) . '</time>'
        . '<span class="bn-footer-version">Identity ' . e((string) Ui::version()['version']) . ' · UI ' . e((string) Ui::version()['ui']) . '</span>'
        . '</footer>';
}

/* ---------------------------------------------------------------------
 * Rendering
 * ------------------------------------------------------------------- */

function render_head(string $title): string
{
    return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
        . '<meta name="robots" content="noindex, nofollow, noarchive">'
        . '<meta name="theme-color" content="#1D3559">'
        . '<meta name="color-scheme" content="light">'
        . '<title>' . e($title) . '</title>'
        . '<link rel="stylesheet" href="ui-asset.php?file=babanoell-ui.css&amp;v=' . e((string) Ui::version()['ui']) . '">'
        . '<script defer src="ui-asset.php?file=babanoell-ui.js&amp;v=' . e((string) Ui::version()['ui']) . '"></script>'
        . '</head><body><main class="bn-shell">'
        . '<header class="bn-brandbar">'
        . '<div class="bn-lockup"><span class="bn-logo"><img src="ui-asset.php?file=brand-logo.png&amp;v=' . e((string) Ui::version()['ui']) . '" alt="لوگوی بابانوئل" width="118" height="40"></span>'
        . '<div class="bn-brandtext"><div class="bn-brandtitle">مرکز مدیریت سرویس‌ها</div><div class="bn-brandmeta">زیرساخت احراز هویت و دسترسی</div></div></div>'
        . '<div class="bn-brandtag"><span class="bn-brandtag-dot"></span><span>سرویس احراز هویت</span><span class="bn-version">Identity</span></div>'
        . '</header><section class="bn-card">';
}

/**
 * @param list<string> $errors
 */
function render_login_page(array $errors, string $notice): void
{
    echo render_head('ورود به مدیریت سرویس احراز هویت | بابانوئل');
    echo '<div class="bn-login-grid">';
    echo '<aside class="bn-login-aside"><div><div class="bn-eyebrow bn-eyebrow--inverse">مدیریت نسخه و نگهداری</div>'
        . '<h2>مرکز به‌روزرسانی<br>سرویس احراز هویت</h2>'
        . '<p>این بخش فقط برای مدیر ارشد سامانه است و تمام مراحل به‌روزرسانی را پیش از اعمال کنترل می‌کند.</p>'
        . '<div class="bn-login-points">'
        . '<div class="bn-login-point"><i>۱</i><span>بررسی ساختار و سلامت بسته قبل از اعمال</span></div>'
        . '<div class="bn-login-point"><i>۲</i><span>تهیه نسخه پشتیبان از فایل‌های در حال تغییر</span></div>'
        . '<div class="bn-login-point"><i>۳</i><span>بازگردانی فایل‌ها در صورت خطای اجرایی</span></div>'
        . '</div></div><div class="bn-version">Identity ' . e((string) Ui::version()['version']) . ' · UI ' . e((string) Ui::version()['ui']) . '</div></aside>';
    echo '<div class="bn-login-form"><div class="bn-eyebrow">ورود مدیر ارشد</div><h1>ورود به بخش مدیریت</h1>'
        . '<p class="bn-subtitle">موبایل یا ایمیل حساب مدیر ارشد و رمز عبور را وارد کنید.</p>';

    if ($notice !== '') {
        echo '<div class="bn-alert bn-alert--info">' . e($notice) . '</div>';
    }
    if ($errors !== []) {
        echo '<div class="bn-alert bn-alert--error"><strong>ورود انجام نشد</strong><ul>';
        foreach ($errors as $error) {
            echo '<li>' . e($error) . '</li>';
        }
        echo '</ul></div>';
    }

    echo '<form method="post" autocomplete="off" data-submit-lock="1">';
    echo '<input type="hidden" name="action" value="login"><input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
    echo '<div class="bn-field"><label class="bn-label" for="identifier">موبایل یا ایمیل مدیر</label><input type="text" id="identifier" name="identifier" autocomplete="username" inputmode="text" placeholder="مثلاً 09121234567" value="' . e((string) ($_POST['identifier'] ?? '')) . '"></div>';
    echo '<div class="bn-field"><label class="bn-label" for="password">رمز عبور</label><input type="password" id="password" name="password" autocomplete="current-password" placeholder="رمز عبور حساب مدیر"></div>';
    echo '<button class="bn-btn bn-btn--primary" type="submit">ورود به مدیریت سرویس</button>';
    echo '</form>';
    echo NoelUpdaterSso::loginLink();
    echo '<div class="bn-alert bn-alert--hint"><strong>پیش از هر به‌روزرسانی:</strong> یک خروجی کامل از پایگاه داده در cPanel نگه دارید.</div>';
    echo '</div></div>';
    echo '</section>' . render_footer('مدیریت امن سرویس احراز هویت') . '</main></body></html>';
}

/**
 * @param array<string,mixed>|null $result
 * @param list<string> $errors
 */
function render_dashboard_page(string $serviceRoot, string $storageDir, ?array $result, array $errors, string $notice): void
{
    $admin = $_SESSION['update_admin'];
    $requirements = update_requirements($serviceRoot, $storageDir);
    $migrations = migration_status($serviceRoot);
    $zip = find_update_zip($storageDir);
    $allRequiredOk = update_requirements_all_ok($requirements);
    $requirementsOkCount = 0;

    foreach ($requirements as $requirement) {
        if ($requirement['ok']) {
            $requirementsOkCount++;
        }
    }

    $adminName = (string) ($admin['name'] ?? 'مدیر ارشد');
    $loginTime = PersianDateTime::fromIso(isset($admin['login_at']) ? (string) $admin['login_at'] : null)
        ?? PersianDateTime::full(PersianDateTime::now());
    $uploadLimit = direct_upload_limit_bytes();

    echo render_head('مدیریت به‌روزرسانی Identity | بابانوئل');
    echo '<div class="bn-pagehead"><div class="bn-pagehead-main"><div class="bn-eyebrow">مدیریت نسخه سرویس</div>'
        . '<h1 class="bn-title">به‌روزرسانی سرویس احراز هویت</h1>'
        . '<p class="bn-subtitle">بسته رسمی سرویس را بررسی و اعمال کنید. پیش از هر تغییر، وضعیت محیط اجرا، پایگاه داده و ساختار بسته کنترل می‌شود.</p></div>';
    echo '<div class="bn-actions"><div class="bn-admin-chip"><span class="bn-admin-avatar">' . e(admin_initial($adminName)) . '</span><span>'
        . '<div class="bn-admin-name">' . e($adminName) . '</div><div class="bn-admin-time">ورود: ' . e($loginTime) . '</div></span></div>';
    echo '<form method="post" class="bn-inline-form"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf" value="' . e(csrf_token()) . '"><button type="submit" class="bn-btn bn-btn--secondary">خروج</button></form></div></div>';

    if ($notice !== '') {
        echo '<div class="bn-alert bn-alert--info">' . e($notice) . '</div>';
    }
    if ($errors !== []) {
        echo '<div class="bn-alert bn-alert--error"><strong>این درخواست انجام نشد</strong><ul>';
        foreach ($errors as $error) {
            echo '<li>' . e($error) . '</li>';
        }
        echo '</ul></div>';
    }
    if ($result !== null) {
        render_result_box($result);
    }

    $migrationValue = 'خطا در بررسی';
    $migrationCaption = 'اتصال پایگاه داده را بررسی کنید';
    if ($migrations['error'] === null) {
        if ($migrations['pending'] === []) {
            $migrationValue = 'به‌روز';
            $migrationCaption = fa_number((int) $migrations['applied']) . ' تغییر ساختاری اعمال شده';
        } else {
            $migrationValue = fa_number(count($migrations['pending'])) . ' مورد در انتظار';
            $migrationCaption = 'پیش از پایان به‌روزرسانی اجرا می‌شود';
        }
    }

    echo '<div class="bn-stats">'
        . '<div class="bn-stat"><div class="bn-stat-label">آمادگی محیط اجرا</div><div class="bn-stat-value">' . ($allRequiredOk ? 'آماده' : 'نیازمند بررسی') . '</div><div class="bn-stat-caption">' . fa_number($requirementsOkCount) . ' از ' . fa_number(count($requirements)) . ' کنترل تأیید شده</div></div>'
        . '<div class="bn-stat"><div class="bn-stat-label">وضعیت پایگاه داده</div><div class="bn-stat-value">' . e($migrationValue) . '</div><div class="bn-stat-caption">' . e($migrationCaption) . '</div></div>'
        . '<div class="bn-stat bn-stat--accent"><div class="bn-stat-label">بسته به‌روزرسانی</div><div class="bn-stat-value">' . ($zip !== null ? 'آماده برای بررسی' : 'در انتظار بسته') . '</div><div class="bn-stat-caption">' . ($zip !== null ? human_size((int) $zip['size']) : 'فایل ZIP انتخاب نشده') . '</div></div>'
        . '</div>';

    echo '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۱</span><div><h2 class="bn-sectiontitle">وضعیت محیط اجرا</h2><p class="bn-sectiondesc">پیش‌نیازهای لازم برای اجرای امن به‌روزرسانی به‌صورت خودکار کنترل می‌شوند.</p></div></div>';
    echo '<div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>کنترل</th><th>نتیجه</th></tr></thead><tbody>';
    foreach ($requirements as $requirement) {
        $statusClass = $requirement['ok'] ? 'bn-status--ok' : (($requirement['critical'] ?? false) ? 'bn-status--bad' : 'bn-status--warn');
        echo '<tr><td>' . e($requirement['label']) . '</td><td><span class="bn-status ' . $statusClass . '">' . e($requirement['detail']) . '</span></td></tr>';
    }
    echo '</tbody></table></div>';
    if (!$allRequiredOk) {
        echo '<div class="bn-alert bn-alert--warning bn-alert--after"><strong>اقدام لازم:</strong> موارد الزامی ناموفق را برطرف کنید؛ تا آن زمان اعمال بسته غیرفعال می‌ماند.</div>';
    }
    echo '</section>';

    echo '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۲</span><div><h2 class="bn-sectiontitle">ساختار پایگاه داده</h2><p class="bn-sectiondesc">نسخه ساختار دیتابیس و تغییرات در انتظار اجرا را بررسی کنید.</p></div></div>';
    if ($migrations['error'] !== null) {
        echo '<div class="bn-alert bn-alert--error"><strong>وضعیت دیتابیس قابل بررسی نیست.</strong>' . render_technical_details((string) $migrations['error']) . '</div>';
    } else {
        echo '<div class="bn-table-wrap"><table class="bn-table"><tbody>'
            . '<tr><td>تغییرات ساختاری اعمال‌شده</td><td><span class="bn-status bn-status--ok">' . fa_number((int) $migrations['applied']) . ' مورد</span></td></tr>'
            . '<tr><td>تغییرات ساختاری در انتظار</td><td><span class="bn-status ' . ($migrations['pending'] === [] ? 'bn-status--ok' : 'bn-status--warn') . '">' . ($migrations['pending'] === [] ? 'هیچ موردی در انتظار نیست' : fa_number(count($migrations['pending'])) . ' مورد') . '</span></td></tr>'
            . '</tbody></table></div>';
        if ($migrations['pending'] !== []) {
            echo '<div class="bn-alert bn-alert--warning bn-alert--after"><strong>تغییرات در انتظار اجرا:</strong><ul>';
            foreach ($migrations['pending'] as $pending) {
                echo '<li><code>' . e((string) $pending) . '</code></li>';
            }
            echo '</ul></div>';
        }
    }
    echo '</section>';

    echo '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۳</span><div><h2 class="bn-sectiontitle">بسته نسخه جدید</h2><p class="bn-sectiondesc">فایل ZIP رسمی را انتخاب کنید. بسته قبل از اعمال از نظر ساختار، مسیرها و محدودیت‌های امنیتی بررسی می‌شود.</p></div></div>';
    if ($zip !== null) {
        echo '<div class="bn-alert bn-alert--info"><strong>بسته آماده بررسی است.</strong><span class="bn-alert-meta"><code>' . e($zip['name']) . '</code> · ' . human_size((int) $zip['size']) . '</span></div>';
    } else {
        echo '<div class="bn-alert bn-alert--hint">می‌توانید فایل ZIP را از همین صفحه انتخاب کنید یا آن را با نام <code>storage/update.zip</code> در هاست قرار دهید.</div>';
    }
    echo '<form method="post" enctype="multipart/form-data" data-submit-lock="1">';
    echo '<input type="hidden" name="action" value="validate"><input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
    echo '<div class="bn-field"><label class="bn-label" for="update_zip">انتخاب فایل ZIP <span class="bn-label-note">اختیاری؛ در صورت انتخاب، update.zip جایگزین می‌شود</span></label>'
        . '<input type="file" id="update_zip" name="update_zip" accept=".zip,application/zip"><div class="bn-file-meta"><span data-file-name>فایلی انتخاب نشده است</span><span>سقف آپلود مستقیم: ' . human_size($uploadLimit) . '</span></div></div>';
    echo '<div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit" ' . ($allRequiredOk ? '' : 'disabled') . '>بررسی و تأیید بسته</button></div></form>';
    echo '<div class="bn-alert bn-alert--hint bn-alert--after-lg"><strong>قبل از اعمال نسخه:</strong> از پایگاه داده یک Export کامل بگیرید. فایل‌های تغییرپذیر به‌صورت خودکار پشتیبان‌گیری می‌شوند، اما تغییرات دیتابیس نیازمند بکاپ شما هستند.</div>';
    echo '</section>';
    echo render_footer('مرکز به‌روزرسانی سرویس احراز هویت');
    echo '</section></main></body></html>';
}

/**
 * @param array<string,mixed> $result
 */
function render_result_box(array $result): void
{
    if (array_key_exists('plan', $result)) {
        if (($result['ok'] ?? false) !== true) {
            $message = (string) ($result['error'] ?? 'علت خطا مشخص نشد.');
            echo '<div class="bn-alert bn-alert--error"><strong>بسته تأیید نشد.</strong><br>فایل انتخاب‌شده قابل اعمال نیست یا بررسی آن کامل نشده است.' . render_technical_details($message) . '</div>';

            return;
        }

        $plan = (array) ($result['plan'] ?? []);
        $zip = is_array($result['zip'] ?? null) ? $result['zip'] : null;
        $preserved = (array) ($result['preserved'] ?? []);
        echo '<div class="bn-alert bn-alert--success"><strong>بسته با موفقیت تأیید شد.</strong> ساختار فایل امن و آماده اعمال است.</div>';
        echo '<section class="bn-section bn-section--soft">'
            . '<div class="bn-sectionhead"><span class="bn-step"><i class="bn-icon bn-icon--check" aria-hidden="true"></i></span><div><h2 class="bn-sectiontitle">خلاصه بسته تأییدشده</h2><p class="bn-sectiondesc">پیش از اعمال، تعداد فایل‌ها، حجم بسته و تغییرات دیتابیس را یک‌بار مرور کنید.</p></div></div>'
            . '<div class="bn-stats">'
            . '<div class="bn-stat"><div class="bn-stat-label">فایل‌های قابل اعمال</div><div class="bn-stat-value">' . fa_number(count((array) ($plan['files'] ?? []))) . '</div><div class="bn-stat-caption">فایل تأییدشده</div></div>'
            . '<div class="bn-stat"><div class="bn-stat-label">حجم استخراج‌شده</div><div class="bn-stat-value">' . human_size((int) ($plan['total_size'] ?? 0)) . '</div><div class="bn-stat-caption">پس از کنترل ساختار</div></div>'
            . '<div class="bn-stat bn-stat--accent"><div class="bn-stat-label">تغییرات دیتابیس</div><div class="bn-stat-value">' . fa_number((int) ($plan['migration_count'] ?? 0)) . '</div><div class="bn-stat-caption">تغییر ساختاری داخل بسته</div></div>'
            . '</div>';
        if ($zip !== null) {
            echo '<div class="bn-alert bn-alert--info"><strong>فایل تأییدشده</strong><span class="bn-alert-meta"><code>' . e((string) ($zip['name'] ?? 'update.zip')) . '</code> · ' . human_size((int) ($zip['size'] ?? 0)) . '</span></div>';
        }
        if ($preserved !== []) {
            echo '<div class="bn-alert bn-alert--hint"><strong>مسیرهایی که در به‌روزرسانی تغییر نمی‌کنند:</strong><ul>';
            foreach ($preserved as $item) {
                echo '<li><code>' . e((string) $item) . '</code></li>';
            }
            echo '</ul></div>';
        }
        echo '<form method="post" data-submit-lock="1">'
            . '<input type="hidden" name="action" value="apply"><input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'
            . '<label class="bn-checkline"><input type="checkbox" name="backup_confirmed" value="1" required><span><strong>نسخه پشتیبان کامل پایگاه داده را تهیه کرده‌ام.</strong><br><span class="bn-muted">اگر این نسخه ساختار پایگاه داده را تغییر دهد، بازگردانی دیتابیس فقط از طریق نسخه پشتیبان امکان‌پذیر است.</span></span></label>'
            . '<div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit">اعمال نسخه تأییدشده</button></div></form>';
        echo '</section>';

        return;
    }

    if (($result['ok'] ?? false) === true) {
        echo '<div class="bn-alert bn-alert--success"><strong>به‌روزرسانی کامل شد.</strong> نسخه جدید با موفقیت روی سرویس اعمال شده است.'
            . '<div class="bn-divider"></div><div class="bn-grid">'
            . '<div><span class="bn-muted">فایل‌های اعمال‌شده</span><br><strong>' . fa_number((int) ($result['copied'] ?? 0)) . ' فایل</strong></div>'
            . '<div><span class="bn-muted">تغییرات دیتابیس اجراشده</span><br><strong>' . fa_number(count((array) ($result['applied'] ?? []))) . ' مورد</strong></div></div>';
        $applied = (array) ($result['applied'] ?? []);
        if ($applied !== []) {
            echo '<div class="bn-divider"></div><strong>تغییرات دیتابیس اجراشده:</strong><ul>';
            foreach ($applied as $row) {
                echo '<li><code>' . e((string) ($row['version'] ?? '')) . '</code> — ' . e((string) ($row['name'] ?? '')) . '</li>';
            }
            echo '</ul>';
        }
        echo '<div class="bn-divider"></div><strong>کنترل پایانی:</strong> صفحه‌های <code>/health</code> و <code>/healthz</code> را باز کنید؛ هر دو باید وضعیت سالم سرویس را نشان دهند.</div>';
    } else {
        $message = (string) ($result['error'] ?? 'علت خطا مشخص نشد.');
        echo '<div class="bn-alert bn-alert--error"><strong>به‌روزرسانی انجام نشد.</strong><br>';
        if (($result['restored'] ?? false) === true) {
            echo 'فایل‌های نسخه قبلی به‌صورت خودکار بازگردانده شدند. اگر تغییری در دیتابیس اجرا شده است، وضعیت آن را با بکاپ خود بررسی کنید.';
        } else {
            echo 'بازگردانی خودکار فایل‌ها کامل نشد. از نسخه‌های موجود در <code>storage/backups</code> یا بکاپ cPanel استفاده کنید.';
        }
        echo render_technical_details($message) . '</div>';
    }
}

/**
 * @return list<array{label:string,ok:bool,detail:string,critical:bool}>
 */
function update_requirements(string $serviceRoot, string $storageDir): array
{
    $uploadMax = direct_upload_limit_bytes();
    $configExists = is_file($serviceRoot . '/config/local.env.php');

    return [
        ['label' => 'نسخه PHP ۸.۱ یا بالاتر', 'ok' => PHP_VERSION_ID >= 80100, 'detail' => PersianDateTime::digits(PHP_VERSION) . (PHP_VERSION_ID >= 80300 ? ' — مناسب' : ' — نسخه ۸.۳ پیشنهاد می‌شود'), 'critical' => true],
        ['label' => 'افزونه اتصال MySQL (pdo_mysql)', 'ok' => extension_loaded('pdo_mysql'), 'detail' => extension_loaded('pdo_mysql') ? 'فعال' : 'غیرفعال', 'critical' => true],
        ['label' => 'افزونه خواندن فایل ZIP', 'ok' => extension_loaded('zip'), 'detail' => extension_loaded('zip') ? 'فعال' : 'غیرفعال؛ از بخش Select PHP Version فعال کنید', 'critical' => true],
        ['label' => 'افزونه OpenSSL', 'ok' => extension_loaded('openssl'), 'detail' => extension_loaded('openssl') ? 'فعال' : 'غیرفعال', 'critical' => false],
        ['label' => 'افزونه JSON', 'ok' => extension_loaded('json'), 'detail' => extension_loaded('json') ? 'فعال' : 'غیرفعال', 'critical' => false],
        ['label' => 'افزونه mbstring', 'ok' => extension_loaded('mbstring'), 'detail' => extension_loaded('mbstring') ? 'فعال' : 'غیرفعال؛ برای پردازش بهتر فارسی پیشنهاد می‌شود', 'critical' => false],
        ['label' => 'دسترسی نوشتن در فضای نگهداری سرویس', 'ok' => is_writable($storageDir), 'detail' => is_writable($storageDir) ? 'قابل نوشتن' : 'دسترسی نوشتن وجود ندارد', 'critical' => true],
        ['label' => 'پیکربندی محلی سرویس', 'ok' => $configExists, 'detail' => $configExists ? 'موجود' : 'فایل config/local.env.php پیدا نشد', 'critical' => true],
        ['label' => 'ظرفیت آپلود مستقیم بسته', 'ok' => $uploadMax >= 52428800, 'detail' => 'سقف فعلی هاست: ' . human_size($uploadMax), 'critical' => false],
    ];
}

/**
 * @param list<array{label:string,ok:bool,detail:string,critical:bool}> $requirements
 */
function update_requirements_all_ok(array $requirements): bool
{
    foreach ($requirements as $requirement) {
        if (($requirement['critical'] ?? false) && !$requirement['ok']) {
            return false;
        }
    }

    return true;
}

function render_not_installed_page(string $serviceRoot): void
{
    echo render_head('Identity هنوز نصب نشده است | بابانوئل');
    echo '<div class="bn-pagehead"><div class="bn-pagehead-main"><div class="bn-eyebrow">وضعیت سرویس</div><h1 class="bn-title">نصب اولیه هنوز تکمیل نشده است</h1><p class="bn-subtitle">مرکز به‌روزرسانی بعد از پایان نصب اولیه و ایجاد پیکربندی محلی فعال می‌شود.</p></div></div>';
    echo '<div class="bn-alert bn-alert--info">برای ادامه، <code>/install.php</code> را باز کنید و مراحل نصب را کامل کنید. پس از نصب موفق، این صفحه به‌صورت خودکار قابل استفاده خواهد بود.</div>';
    echo render_footer('مرکز به‌روزرسانی سرویس احراز هویت') . '</section></main></body></html>';
}

function render_error_page(string $message): void
{
    echo render_head('خطا در مدیریت سرویس احراز هویت | بابانوئل');
    echo '<div class="bn-pagehead"><div class="bn-pagehead-main"><div class="bn-eyebrow">خطای اجرایی</div><h1 class="bn-title">درخواست متوقف شد</h1><p class="bn-subtitle">این درخواست کامل نشد. در این مرحله تغییر جدیدی از طرف صفحه مدیریت اعمال نشده است.</p></div></div>';
    echo '<div class="bn-alert bn-alert--error"><strong>امکان ادامه عملیات وجود ندارد.</strong><br>جزئیات فنی زیر را برای بررسی نگه دارید.' . render_technical_details($message) . '</div>';
    echo render_footer('مرکز به‌روزرسانی سرویس احراز هویت') . '</section></main></body></html>';
}
