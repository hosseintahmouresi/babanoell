<?php
declare(strict_types=1);

use Babanoell\Panel\Security\Csrf;
use Babanoell\Panel\Support\PersianDateTime;
use Babanoell\Panel\Update\UpdateTool;
use Babanoell\Panel\View\Renderer;

$root = dirname(__DIR__);
if (!is_file($root . '/storage/installed.lock')) {
    header('Location: /install.php', true, 302);
    exit;
}
require $root . '/bootstrap.php';

$ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null;
$ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 512) : null;
$identity = $panelSession->identity($ip, $ua);
if ($identity === null) {
    header('Location: /login', true, 302);
    exit;
}
if (!$panelSession->isSuperAdmin()) {
    http_response_code(403);
    echo Renderer::page(
        'دسترسی غیرمجاز',
        '<section class="bn-section bn-panel-error"><div class="bn-panel-error-code">۴۰۳</div><h1>دسترسی به مرکز به‌روزرسانی مجاز نیست</h1><p>فقط مدیر ارشد سامانه می‌تواند نسخه‌های جدید پنل را بررسی و اعمال کند.</p><a class="bn-btn bn-btn--primary" href="/dashboard">بازگشت به داشبورد</a></section>',
        $identity,
        'system',
    );
    exit;
}

header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store, max-age=0');

$storage = $root . '/storage';
$zipPath = $storage . '/update.zip';
$tool = new UpdateTool();
$message = '';
$error = '';
$plan = null;
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'POST') {
    if (!Csrf::valid(isset($_POST['_csrf']) ? (string) $_POST['_csrf'] : null)) {
        $error = 'اعتبار فرم منقضی شده است. صفحه را تازه‌سازی کنید و دوباره تلاش کنید.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'upload_validate') {
                if (isset($_FILES['package']) && is_array($_FILES['package']) && ($_FILES['package']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $tmp = (string) $_FILES['package']['tmp_name'];
                    if (!move_uploaded_file($tmp, $zipPath)) {
                        throw new RuntimeException('ذخیره فایل آپلودشده انجام نشد.');
                    }
                }
                $plan = $tool->plan($zipPath);
                $_SESSION['panel_update_sha'] = hash_file('sha256', $zipPath);
                $message = 'بسته با موفقیت بررسی شد و برای اعمال آماده است.';
            } elseif ($action === 'apply') {
                if (!is_file($zipPath) || !isset($_SESSION['panel_update_sha']) || !hash_equals((string) $_SESSION['panel_update_sha'], (string) hash_file('sha256', $zipPath))) {
                    throw new RuntimeException('فایل بسته بعد از مرحله بررسی تغییر کرده است. بسته را دوباره بررسی کنید.');
                }
                $result = $tool->apply($zipPath, $root, $storage);
                $installedVersion = is_file($root . '/config/version.php') ? require $root . '/config/version.php' : [];
                @file_put_contents($root . '/storage/installed.lock', json_encode([
                    'service'=>'panel','version'=>(string)($installedVersion['version']??'unknown'),'ui'=>(string)($installedVersion['ui']??'unknown'),'environment'=>$config->environment(),'updated_at'=>gmdate('c')
                ], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
                unset($_SESSION['panel_update_sha']);
                $message = 'نسخه جدید با موفقیت اعمال شد. ' . PersianDateTime::digits((string) $result['copied']) . ' فایل به‌روزرسانی شد.';
                $plan = null;
            }
        } catch (Throwable $throwable) {
            $error = $throwable->getMessage();
        }
    }
}

if ($plan === null && is_file($zipPath)) {
    try {
        $plan = $tool->plan($zipPath);
    } catch (Throwable) {
        // The visible error is shown only after an explicit validation request.
    }
}

$version = require $root . '/config/version.php';
$currentVersion = (string) ($version['version'] ?? 'نامشخص');

$body = '<div class="bn-pagehead"><div class="bn-pagehead-main"><div class="bn-eyebrow">مدیریت نسخه پنل</div><h1 class="bn-title">به‌روزرسانی پنل مدیریت</h1><p class="bn-subtitle">بسته رسمی Panel را ابتدا بررسی کنید و پس از تأیید ساختار، نسخه جدید را به‌صورت کنترل‌شده اعمال کنید.</p></div><div class="bn-actions"><a class="bn-btn bn-btn--secondary" href="/system">بازگشت به وضعیت سامانه</a></div></div>';

if ($error !== '') {
    $body .= '<div class="bn-alert bn-alert--error"><strong>به‌روزرسانی انجام نشد</strong><br>' . Renderer::e($error) . '</div>';
}
if ($message !== '') {
    $body .= '<div class="bn-alert bn-alert--success"><strong>عملیات با موفقیت انجام شد.</strong><br>' . Renderer::e($message) . '</div>';
}

$body .= '<div class="bn-stats">'
    . '<div class="bn-stat"><div class="bn-stat-label">نسخه فعلی پنل</div><div class="bn-stat-value">' . Renderer::e(PersianDateTime::digits($currentVersion)) . '</div><div class="bn-stat-caption">نسخه نصب‌شده روی این محیط</div></div>'
    . '<div class="bn-stat"><div class="bn-stat-label">وضعیت بسته</div><div class="bn-stat-value">' . ($plan !== null ? 'تأییدشده' : 'در انتظار بررسی') . '</div><div class="bn-stat-caption">' . ($plan !== null ? 'ساختار بسته معتبر است' : 'فایل ZIP را انتخاب کنید') . '</div></div>'
    . '<div class="bn-stat bn-stat--accent"><div class="bn-stat-label">فایل‌های قابل اعمال</div><div class="bn-stat-value">' . ($plan !== null ? Renderer::e(PersianDateTime::digits((string) count($plan['files']))) : '—') . '</div><div class="bn-stat-caption">پس از بررسی بسته</div></div>'
    . '</div>';

$body .= '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۱</span><div><h2 class="bn-sectiontitle">انتخاب و بررسی بسته</h2><p class="bn-sectiondesc">فقط ZIP رسمی Panel را انتخاب کنید. ساختار بسته قبل از هر تغییری کنترل می‌شود.</p></div></div>'
    . '<form method="post" enctype="multipart/form-data" data-submit-lock="1"><input type="hidden" name="_csrf" value="' . Renderer::e(Csrf::token()) . '"><input type="hidden" name="action" value="upload_validate">'
    . '<div class="bn-field"><label class="bn-label" for="package">فایل ZIP پنل</label><input id="package" type="file" name="package" accept=".zip,application/zip"><div class="bn-file-meta"><span data-file-name>فایلی انتخاب نشده است</span><span>در صورت وجود، storage/update.zip نیز بررسی می‌شود</span></div></div>'
    . '<div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit">بررسی و تأیید بسته</button></div></form></section>';

if ($plan !== null) {
    $body .= '<section class="bn-section bn-section--soft"><div class="bn-sectionhead"><span class="bn-step">۲</span><div><h2 class="bn-sectiontitle">اعمال نسخه تأییدشده</h2><p class="bn-sectiondesc">خلاصه تغییرات را مرور کنید و سپس نسخه بررسی‌شده را اعمال کنید.</p></div></div>'
        . '<div class="bn-table-wrap"><table class="bn-table"><tbody>'
        . '<tr><td>فایل‌های قابل اعمال</td><td><span class="bn-status bn-status--ok">' . Renderer::e(PersianDateTime::digits((string) count($plan['files']))) . ' فایل</span></td></tr>'
        . '<tr><td>فایل‌ها و مسیرهای محافظت‌شده</td><td><span class="bn-status bn-status--ok">' . Renderer::e(PersianDateTime::digits((string) $plan['protected'])) . ' مورد</span></td></tr>'
        . '</tbody></table></div>'
        . '<div class="bn-alert bn-alert--hint bn-alert--after"><strong>قبل از اعمال:</strong> یک بکاپ از فایل‌های سرویس در cPanel نگه دارید. Updater نیز از فایل‌هایی که جایگزین می‌شوند نسخه پشتیبان می‌سازد.</div>'
        . '<form method="post" data-submit-lock="1"><input type="hidden" name="_csrf" value="' . Renderer::e(Csrf::token()) . '"><input type="hidden" name="action" value="apply"><div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit">اعمال نسخه تأییدشده</button></div></form></section>';
}

echo Renderer::page('به‌روزرسانی پنل مدیریت', $body, $identity, 'system');
