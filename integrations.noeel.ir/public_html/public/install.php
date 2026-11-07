<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root.'/src/Platform/Favicon.php';
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex');

/** Render safe, actionable setup errors without exposing credentials. */
function integrations_install_block(string $message, int $status = 503): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo \Babanoell\Integrations\Platform\Favicon::head().'<title>پیش‌نیاز نصب بابانوئل</title><link rel="stylesheet" href="/ui-asset.php?file=platform.css&amp;v=2.16.1"></head><body><main class="bn-shell bn-shell--narrow"><section class="bn-card bn-card--standalone">';
    echo '<h1 class="bn-title">نصب سرویس ارتباطات</h1><p class="bn-alert bn-alert--warning">'.htmlspecialchars($message,ENT_QUOTES,'UTF-8').'</p>';
    echo '<p class="bn-helper">بعد از اصلاح تنظیمات، صفحهٔ نصب را دوباره باز کنید.</p></section></main></body></html>';
    exit;
}

if (PHP_VERSION_ID < 80100) {
    integrations_install_block('نسخه PHP این ساب‌دامین باید 8.1 یا بالاتر باشد.');
}

try {
    require $root . '/bootstrap.php';
    $storage = $root . '/storage';

    if (is_file($storage . '/installed.lock')) {
        integrations_install_block('این سرویس قبلاً نصب شده است.', 403);
    }

    $configFile = $root . '/config/local.env.php';
    if (!is_file($configFile)) {
        integrations_install_block('فایل config/local.env.php پیدا نشد. از local.env.example.php یک کپی ایجاد و آن را تکمیل کنید.');
    }

    $installKey = \Babanoell\Integrations\Support\Config::get('INTEGRATIONS_INSTALL_KEY');
    if (strlen($installKey) < 32) {
        integrations_install_block('INTEGRATIONS_INSTALL_KEY باید در config/local.env.php یک مقدار تصادفی حداقل ۳۲ کاراکتری داشته باشد.');
    }

    // Do not call Config::requireSecret() here: it throws an uncaught exception
    // when a key is missing, producing a generic HTTP 500 instead of guidance.
    $sharedSecret = \Babanoell\Integrations\Support\Config::get('NOEEL_SERVICE_SHARED_SECRET');
    if (strlen($sharedSecret) < 32) {
        integrations_install_block('NOEEL_SERVICE_SHARED_SECRET تنظیم نشده یا کمتر از ۳۲ کاراکتر است. مقدار باید دقیقاً همان کلید مشترک سرویس‌های فعلی نوئل باشد؛ کلید جدید و متفاوت نسازید.');
    }
    unset($sharedSecret);

    foreach (['pdo_mysql', 'curl', 'soap'] as $requiredExtension) {
        if (!extension_loaded($requiredExtension)) {
            integrations_install_block('افزونه PHP مورد نیاز فعال نیست: ' . $requiredExtension . '. آن را برای PHP همین ساب‌دامین فعال کنید.');
        }
    }

    if (\Babanoell\Integrations\Support\Config::production()
        && empty($_SERVER['HTTPS'])
        && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') !== 'https') {
        integrations_install_block('در محیط عملیاتی، نصب فقط از طریق HTTPS مجاز است.', 403);
    }

    foreach ([$storage, $storage . '/tmp'] as $directory) {
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            integrations_install_block('امکان ایجاد پوشه storage/tmp وجود ندارد. مجوز نوشتن پوشه storage را بررسی کنید.');
        }
        if (!is_writable($directory)) {
            integrations_install_block('پوشه storage یا storage/tmp قابل نوشتن نیست. مجوز دسترسی PHP را بررسی کنید.');
        }
    }

    session_save_path($storage . '/tmp');
    session_name('noeel_integrations_install');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => \Babanoell\Integrations\Support\Config::production(),
        'samesite' => 'Strict',
    ]);
    if (!@session_start()) {
        integrations_install_block('ایجاد نشست نصب (PHP session) ممکن نشد؛ مجوز storage/tmp و تنظیمات session هاست را بررسی کنید.');
    }

    if (!isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
} catch (\ParseError $e) {
    error_log('[integrations] install_config_parse_error ' . basename($e->getFile()) . ':' . $e->getLine());
    integrations_install_block('خطای نگارشی PHP در فایل تنظیمات یا سورس وجود دارد. ابتدا فایل config/local.env.php را با PHP Lint بررسی کنید.');
} catch (\Throwable $e) {
    // Never print exception messages or config values to the browser.
    error_log('[integrations] install_preflight_error ' . get_class($e));
    integrations_install_block('آماده‌سازی نصب با خطا مواجه شد. error_log همین ساب‌دامین و ساختار config/local.env.php را بررسی کنید.');
}

$error = '';
$success = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $attempts = (int) ($_SESSION['attempts'] ?? 0);
    if ($attempts >= 10) {
        integrations_install_block('تعداد تلاش‌های ورود کلید بیش از حد مجاز است.', 429);
    }
    $_SESSION['attempts'] = $attempts + 1;

    if (!hash_equals((string) $_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $error = 'درخواست نامعتبر است.';
    } elseif (!hash_equals($installKey, (string) ($_POST['install_key'] ?? ''))) {
        $error = 'کلید نصب نامعتبر است.';
    } else {
        try {
            $pdo = \Babanoell\Integrations\Support\Config::db();
            $applied = \Babanoell\Integrations\Database\Migrator::up($pdo, $root);
            $lock = json_encode([
                'service' => 'integrations',
                'version' => '1.0.0',
                'installed_at' => gmdate('c'),
                'migrations' => $applied,
            ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if (file_put_contents($storage . '/installed.lock', $lock, LOCK_EX) === false) {
                throw new \RuntimeException('Cannot write lock');
            }
            @chmod($storage . '/installed.lock', 0600);
            $success = true;
            session_destroy();
        } catch (\Throwable $e) {
            error_log('[integrations] install_failed ' . get_class($e));
            $error = 'نصب تکمیل نشد؛ مشخصات دیتابیس، سطح دسترسی و لاگ PHP سرور بررسی شود.';
        }
    }
}
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>نصب Integrations | نوئل</title>
<?= \Babanoell\Integrations\Platform\Favicon::head() ?>
<link rel="stylesheet" href="/ui-asset.php?file=platform.css&amp;v=2.16.1">
<script defer src="/ui-asset.php?file=platform.js&amp;v=2.16.1"></script>
</head>
<body><main class="bn-shell bn-shell--narrow"><section class="bn-card bn-card--standalone">
<h1 class="bn-title">راه‌اندازی سرویس ارتباطات</h1>
<p>فایل <code>config/local.env.php</code> را با تنظیمات دیتابیس مستقل و کلیدهای سرویس تکمیل کنید.</p>
<?php if ($error !== ''): ?><p class="bn-alert bn-alert--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($success): ?>
<p class="bn-alert bn-alert--success">نصب دیتابیس و سرویس انجام شد. اکنون آدرس /health را بررسی کنید. فایل install.php را از دسترس خارج کنید.</p>
<?php else: ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= htmlspecialchars((string) $_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
<label for="key">کلید نصب اختصاصی</label>
<input id="key" name="install_key" type="password" autocomplete="off" required>
<button class="bn-btn bn-btn--primary" type="submit">نصب ایمن سرویس</button>
</form>
<?php endif; ?>
</section></main></body></html>
