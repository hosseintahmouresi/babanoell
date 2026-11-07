<?php
declare(strict_types=1);

use Babanoell\Panel\Security\Csrf;
use Babanoell\Panel\Support\PersianDateTime;
use Babanoell\Panel\Loans\LoansModule;
use Babanoell\Panel\Acceptor\AcceptorModule;
use Babanoell\Panel\Applicant\ApplicantModule;
use Babanoell\Panel\Users\UsersModule;
use Babanoell\Panel\Documents\DocumentsModule;
use Babanoell\Panel\Contracts\ContractsModule;
use Babanoell\Panel\Partners\PartnersModule;
use Babanoell\Panel\Bank\BankModule;
use Babanoell\Panel\Finance\FinanceModule;
use Babanoell\Panel\Reports\ReportsModule;
use Babanoell\Panel\Reports\HistoricalReportsModule;
use Babanoell\Panel\System\OperationalPreferences;
use Babanoell\Panel\View\Renderer;

$root = dirname(__DIR__);
$release = is_file($root . '/config/version.php') ? require $root . '/config/version.php' : [];
$releaseVersion = (string)($release['version'] ?? 'unknown');
$uiVersion = (string)($release['ui'] ?? 'unknown');
require_once $root.'/src/Platform/ReleaseTransaction.php';
if((parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/')!=='/login') \Babanoell\Panel\Platform\ReleaseTransaction::guard($root);

if (!is_file($root . '/storage/installed.lock') || !is_file($root . '/config/local.env.php')) {
    header('Location: /install.php', true, 302);
    exit;
}
require $root . '/bootstrap.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if($path==='/applicant/invitation'&&$method==='GET'&&is_string($_GET['code']??null)&&preg_match('/^[A-Za-z0-9_-]{8,40}$/D',$_GET['code']))$_SESSION['pending_referral_code']=$_GET['code'];
$ip = \Babanoell\Panel\Platform\ProxyContext::clientIp($_SERVER);
$ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 512) : null;
header('X-Request-Id: '.\Babanoell\Panel\Platform\RequestContext::id());
header('X-Noel-Service: panel');

if ($path === '/platform-contract') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['data'=>['contract'=>\Babanoell\Panel\Platform\ServiceBoundary::describe(),'service_auth'=>['configured'=>$config->serviceAuthSecret()!=='']],'meta'=>\Babanoell\Panel\Platform\RequestContext::responseMeta()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

if ($path === '/health') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'service' => $config->serviceName(),
        'status' => 'ok',
        'environment' => $config->environment(),
        'timestamp' => gmdate('c'),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($path === '/healthz') {
    $identityHealth = $identityClient->health();
    $loansHealth = $loansClient->health();
    $documentsHealth = $documentsClient->health();
    $partnersHealth = $partnersClient->health();
    $financeHealth = $financeClient->health();
    $integrationsHealth = $integrationsClient->health();
    $identityOk = $identityHealth->ok() && (($identityHealth->json['status'] ?? null) === 'ok');
    $loansOk = $loansHealth->ok() && (($loansHealth->json['status'] ?? null) === 'ok');
    $documentsOk = $documentsHealth->ok() && (($documentsHealth->json['status'] ?? null) === 'ok');
    $partnersOk = $partnersHealth->ok() && (($partnersHealth->json['status'] ?? null) === 'ok');
    $financeOk = $financeHealth->ok() && (($financeHealth->json['status'] ?? null) === 'ok');
    $integrationsOk = $integrationsHealth->ok() && (($integrationsHealth->json['data']['status'] ?? $integrationsHealth->json['status'] ?? null) === 'ok');
    $ok = $identityOk && $loansOk && $documentsOk && $partnersOk && $financeOk && $integrationsOk;
    http_response_code($ok ? 200 : 503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'service' => $config->serviceName(),
        'status' => $ok ? 'ok' : 'error',
        'identity' => $identityOk ? 'ok' : 'unavailable',
        'loans' => $loansOk ? 'ok' : 'unavailable',
        'documents' => $documentsOk ? 'ok' : 'unavailable',
        'partners' => $partnersOk ? 'ok' : 'unavailable',
        'finance' => $financeOk ? 'ok' : 'unavailable',
        'integrations' => $integrationsOk ? 'ok' : 'unavailable',
        'environment' => $config->environment(),
        'version' => $releaseVersion,
        'timestamp' => gmdate('c'),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if (in_array($path, ['/sso/authorize', '/sso/exchange', '/sso/check',
    '/sso/user/authorize', '/sso/user/exchange', '/sso/user/check'], true)) {
    if (strlen($config->serviceAuthSecret()) < 32 || !\Babanoell\Panel\Platform\ProxyContext::secure($_SERVER)) {
        http_response_code(503);exit('SSO prerequisites unavailable.');
    }
    try {
        $store=new \Babanoell\Panel\Sso\GrantStore($root, new \Babanoell\Panel\Security\TokenVault($config->sessionEncryptionKey()));
        if (str_starts_with($path, '/sso/user/')) {
            $broker=new \Babanoell\Panel\Sso\UserBroker($store,$config,$identityClient);
            if ($path==='/sso/user/authorize' && $method==='GET') $broker->authorize($panelSession,$ip,$ua);
            if ($path==='/sso/user/exchange' || $path==='/sso/user/check') $broker->api($path);
        } else {
            $broker=new \Babanoell\Panel\Sso\Broker($store,$config,$identityClient);
            if ($path==='/sso/authorize' && $method==='GET') $broker->authorize($panelSession,$ip,$ua);
            if ($path==='/sso/exchange' || $path==='/sso/check') $broker->api($path);
        }
        http_response_code(405);exit('Method not allowed.');
    } catch (\Throwable $error) {
        error_log('[noeel-sso] '.get_class($error));
        http_response_code(503);exit('SSO temporarily unavailable.');
    }
}

if ($path === '/login') {
    $existing = $panelSession->identity($ip, $ua);
    if ($method === 'GET' && $existing !== null) {
        header('Location: /dashboard', true, 302);
        exit;
    }

    $error = '';
    if ($method === 'POST') {
        if (!Csrf::valid(isset($_POST['_csrf']) ? (string) $_POST['_csrf'] : null)) {
            $error = 'اعتبار فرم ورود منقضی شده است. صفحه را تازه‌سازی کنید و دوباره تلاش کنید.';
        } else {
            $result = $panelSession->login(
                (string) ($_POST['identifier'] ?? ''),
                (string) ($_POST['password'] ?? ''),
                $ip,
                $ua,
            );
            if ($result['ok']) {
                $continue = $_SESSION['panel_sso_continue'] ?? null;
                unset($_SESSION['panel_sso_continue']);
                $destination = (is_string($continue) && (str_starts_with($continue, '/sso/authorize?') || str_starts_with($continue, '/sso/user/authorize?')) && strlen($continue)<500) ? $continue : (!empty($_SESSION['pending_referral_code'])?'/applicant/invitation':'/dashboard');
                header('Location: '.$destination, true, 303);
                exit;
            }
            $error = (string) $result['message'];
        }
    }

    $alert = $error !== ''
        ? '<div class="bn-alert bn-alert--error"><strong>ورود انجام نشد</strong><br>' . Renderer::e($error) . '</div>'
        : '';

    $content = '<div class="bn-login-grid">'
        . '<aside class="bn-login-aside"><div><div class="bn-eyebrow bn-eyebrow--inverse">همراه شما در مسیر تسهیلات</div>'
        . '<h2>از شروع درخواست،<br>تا قدم آخر</h2>'
        . '<p>درخواست‌ها، مدارک و قراردادهای خود را در یک فضای روشن و منظم پیگیری کنید.</p>'
        . '<div class="bn-login-points">'
        . '<div class="bn-login-point"><i>۱</i><span>یک حساب برای پیگیری همهٔ کارها</span></div>'
        . '<div class="bn-login-point"><i>۲</i><span>حفظ امنیت اطلاعات شما</span></div>'
        . '<div class="bn-login-point"><i>۳</i><span>دسترسی سریع به اقدام بعدی</span></div>'
        . '</div></div><div class="bn-version">Panel '.Renderer::e($releaseVersion).' · UI '.Renderer::e($uiVersion).'</div></aside>'
        . '<div class="bn-login-form"><div class="bn-eyebrow">ورود به سامانه</div><h1>به بابانوئل خوش آمدید</h1>'
        . '<p class="bn-subtitle">شماره موبایل یا ایمیل حساب و رمز عبورتان را وارد کنید.</p>'
        . $alert
        . '<form method="post" action="/login" autocomplete="on" data-submit-lock="1">'
        . '<input type="hidden" name="_csrf" value="' . Renderer::e(Csrf::token()) . '">'
        . '<div class="bn-field"><label class="bn-label" for="identifier">موبایل یا ایمیل</label>'
        . '<input id="identifier" name="identifier" type="text" inputmode="text" autocomplete="username" maxlength="255" required placeholder="مثلاً 09121234567"></div>'
        . '<div class="bn-field"><label class="bn-label" for="password">رمز عبور</label><div class="bn-password-wrap">'
        . '<input id="password" name="password" type="password" autocomplete="current-password" maxlength="4096" required placeholder="رمز عبور حساب کاربری">'
        . '<button class="bn-password-toggle" type="button" data-password-toggle aria-label="نمایش یا پنهان‌کردن رمز عبور">نمایش</button></div></div>'
        . '<button class="bn-btn bn-btn--primary" type="submit">ورود به حساب</button></form>'
        . '<div class="bn-panel-login-note"><span class="bn-panel-login-note-mark"><i class="bn-icon bn-icon--shield-halved" aria-hidden="true"></i></span><span>برای حفظ امنیت حساب، از دستگاه شخصی استفاده کنید.</span></div>'
        . '</div></div>';

    echo Renderer::page('ورود به پنل مدیریت', $content);
    exit;
}

if ($path === '/logout' && $method === 'POST') {
    if (Csrf::valid(isset($_POST['_csrf']) ? (string) $_POST['_csrf'] : null)) {
        $credential = $panelSession->serviceCredential();
        $browserId=$_SESSION['panel_user_sso_browser_id']??null;
        if ($credential !== null || is_string($browserId)) {
            try {
                $store=new \Babanoell\Panel\Sso\GrantStore($root, new \Babanoell\Panel\Security\TokenVault($config->sessionEncryptionKey()));
                if ($credential !== null) $store->revokeAccess($credential);
                if (is_string($browserId)) $store->revokeUserBrowser($browserId);
            } catch (\Throwable $error) {
                error_log('[noeel-sso] local revocation unavailable '.get_class($error));
            }
        }
        $panelSession->logout($ip, $ua, false);
    }
    header('Location: /login', true, 303);
    exit;
}

if ($path==='/logout/confirm' && $method==='GET') {
    $loggedIn=$panelSession->identity($ip,$ua)!==null;
    if (!$loggedIn) { header('Location: /login',true,302);exit; }
    $content='<section class="bn-section"><h1 class="bn-title">خروج یکپارچه</h1>'
        .'<p>با خروج از پنل مرکزی، مجوز ورود سرویس‌های متصل به این نشست نیز باطل خواهد شد.</p>'
        .'<form action="/logout" method="post"><input type="hidden" name="_csrf" value="'.\Babanoell\Panel\View\Renderer::e(Csrf::token()).'">'
        .'<button class="bn-btn bn-btn--primary" type="submit">خروج از همه سرویس‌ها</button> '
        .'<a class="bn-btn bn-btn--secondary" href="/dashboard">انصراف</a></form></section>';
    echo Renderer::page('خروج یکپارچه',$content,$panelSession->cachedIdentity());exit;
}

$identity = $panelSession->identity($ip, $ua);
if ($identity === null) {
    header('Location: /login', true, 302);
    exit;
}

$serviceCredential = $panelSession->serviceCredential();
if ($serviceCredential === null || $serviceCredential === '') {
    $panelSession->logout($ip, $ua, false);
    header('Location: /login', true, 302);
    exit;
}

$roles = is_array($identity['roles'] ?? null) ? $identity['roles'] : [];
$isSuperAdmin = in_array('super_admin', $roles, true);
$isAcceptor = in_array('acceptor', $roles, true) && !$isSuperAdmin && !in_array('admin', $roles, true);
$isApplicant = in_array('applicant', $roles, true) && !$isSuperAdmin && !in_array('admin', $roles, true);
$isBankExpert = in_array('bank_expert', $roles, true) && !$isSuperAdmin && !in_array('admin', $roles, true);

$documentsModule = new DocumentsModule($documentsClient, $loansClient, $serviceCredential, $identity);
if ($documentsModule->handle($path, $method)) {
    exit;
}

$usersModule = new UsersModule($identityClient, $serviceCredential, $identity);
if ($usersModule->handle($path, $method)) {
    exit;
}

$contractsModule = new ContractsModule($partnersClient, $serviceCredential, $identity, $documentsClient);
if ($contractsModule->handle($path, $method)) { exit; }

$partnersModule = new PartnersModule($partnersClient, $documentsClient, $loansClient, $serviceCredential, $identity);
if ($partnersModule->handle($path, $method)) {
    exit;
}

$referralModule = new \Babanoell\Panel\Partners\ReferralModule($partnersClient, $serviceCredential, $identity);
if ($referralModule->handle($path, $method)) { exit; }

$notificationModule = new \Babanoell\Panel\Notification\NotificationModule($loansClient, $serviceCredential, $identity);
if ($notificationModule->handle($path, $method)) { exit; }

$creditModule = new \Babanoell\Panel\Credit\CreditModule($loansClient, $serviceCredential, $identity);
if ($creditModule->handle($path, $method)) { exit; }

$acceptorModule = new AcceptorModule($loansClient, $serviceCredential, $identity);
if ($acceptorModule->handle($path, $method)) {
    exit;
}

$applicantModule = new ApplicantModule($loansClient, $partnersClient, $serviceCredential, $identity);
if ($applicantModule->handle($path, $method)) {
    exit;
}

$bankModule = new BankModule($loansClient, $serviceCredential, $identity);
if ($bankModule->handle($path, $method)) {
    exit;
}

$financeModule = new FinanceModule($financeClient, $serviceCredential, $identity);
if ($financeModule->handle($path, $method)) {
    exit;
}

$operationalPreferences = new OperationalPreferences($root);
$historyModule = new HistoricalReportsModule($loansClient,$partnersClient,$financeClient,$serviceCredential,$identity,$operationalPreferences);
if ($historyModule->handle($path, $method)) { exit; }
$reportsModule = new ReportsModule($loansClient, $partnersClient, $financeClient, $integrationsClient, $config, $serviceCredential, $identity, null, $operationalPreferences);
if ($reportsModule->handle($path, $method)) {
    exit;
}

if (!$isAcceptor && !$isApplicant) {
    $loansModule = new LoansModule($loansClient, $serviceCredential, $identity, $documentsClient);
    if ($loansModule->handle($path, $method)) {
        exit;
    }
}

if (($path === '/' || $path === '/dashboard') && $isAcceptor) {
    header('Location: /acceptor', true, 302);
    exit;
}
if (($path === '/' || $path === '/dashboard') && $isApplicant) {
    header('Location: /applicant', true, 302);
    exit;
}
if (($path === '/' || $path === '/dashboard') && $isBankExpert) {
    header('Location: /bank', true, 302);
    exit;
}

if ($path === '/' || $path === '/dashboard') {
    $name = Renderer::e((string) ($identity['user']['display_name'] ?? 'کاربر سامانه'));
    $roles = is_array($identity['roles'] ?? null) ? $identity['roles'] : [];
    $permissions = is_array($identity['permissions'] ?? null) ? $identity['permissions'] : [];
    $super = in_array('super_admin', $roles, true);
    $loansHealth = $loansClient->health();
    $documentsHealth = $documentsClient->health();
    $partnersHealth = $partnersClient->health();
    $financeHealth = $financeClient->health();
    $loansOk = $loansHealth->ok() && (($loansHealth->json['status'] ?? null) === 'ok');
    $documentsOk = $documentsHealth->ok() && (($documentsHealth->json['status'] ?? null) === 'ok');
    $partnersOk = $partnersHealth->ok() && (($partnersHealth->json['status'] ?? null) === 'ok');
    $financeOk = $financeHealth->ok() && (($financeHealth->json['status'] ?? null) === 'ok');

    $content = '<div class="bn-pagehead"><div class="bn-pagehead-main"><div class="bn-eyebrow">نمای کلی پنل</div>'
        . '<h1 class="bn-title">خوش آمدید، ' . $name . '</h1>'
        . '<p class="bn-subtitle">احراز هویت، تسهیلات و مدیریت امن مدارک در یک فضای کاری یکپارچه در دسترس هستند.</p></div>'
        . '<div class="bn-actions"><form method="post" action="/logout" class="bn-inline-form"><input type="hidden" name="_csrf" value="' . Renderer::e(Csrf::token()) . '"><button class="bn-btn bn-btn--secondary" type="submit">خروج از حساب</button></form></div></div>';

    $content .= '<div class="bn-stats">'
        . '<div class="bn-stat"><div class="bn-stat-label">وضعیت نشست</div><div class="bn-stat-value">فعال</div><div class="bn-stat-caption">نشست امن سمت سرور</div></div>'
        . '<div class="bn-stat"><div class="bn-stat-label">سرویس تسهیلات</div><div class="bn-stat-value">' . ($loansOk ? 'آماده' : 'نیازمند بررسی') . '</div><div class="bn-stat-caption">طرح و پرونده</div></div>'
        . '<div class="bn-stat"><div class="bn-stat-label">سرویس مدارک</div><div class="bn-stat-value">' . ($documentsOk ? 'آماده' : 'نیازمند بررسی') . '</div><div class="bn-stat-caption">فایل و بازبینی امن</div></div>'
        . '<div class="bn-stat"><div class="bn-stat-label">سرویس شرکا</div><div class="bn-stat-value">' . ($partnersOk ? 'آماده' : 'نیازمند بررسی') . '</div><div class="bn-stat-caption">پذیرنده و همکاری</div></div>'
        . '<div class="bn-stat"><div class="bn-stat-label">سرویس مالی</div><div class="bn-stat-value">' . ($financeOk ? 'آماده' : 'نیازمند بررسی') . '</div><div class="bn-stat-caption">تسویه و مغایرت</div></div>'
        . '<div class="bn-stat bn-stat--accent"><div class="bn-stat-label">دسترسی‌های فعال</div><div class="bn-stat-value">' . Renderer::e(PersianDateTime::digits((string) count($permissions))) . '</div><div class="bn-stat-caption">مجوز ثبت‌شده برای این حساب</div></div>'
        . '</div>';

    $canLoansOverview=$super || in_array('loans.requests.read',$permissions,true) || in_array('loans.plans.read',$permissions,true);
    $canDocumentsOverview=$super || in_array('documents.view',$permissions,true) || in_array('documents.manage',$permissions,true);
    $canReportsOverview=$super || in_array('loans.requests.read',$permissions,true) || in_array('partners.merchants.read',$permissions,true) || in_array('finance.dashboard.read',$permissions,true);
    $content .= '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۱</span><div><h2 class="bn-sectiontitle">فضای کاری عملیاتی</h2><p class="bn-sectiondesc">امکانات مطابق مجوزهای ثبت‌شده در هویت مرکزی در دسترس‌اند.</p></div></div><div class="bn-action-grid">';
    if ($canLoansOverview) {
        if($super || in_array('loans.plans.read',$permissions,true)) $content .= '<a class="bn-action-card" href="/loans/plans"><strong>طرح‌های تسهیلات</strong><span>تعریف و مدیریت طرح‌ها.</span></a>';
        if($super || in_array('loans.requests.read',$permissions,true)) $content .= '<a class="bn-action-card" href="/loans/requests"><strong>پرونده‌های تسهیلات</strong><span>گردش وضعیت و تاریخچه پرونده.</span></a>';
        $content .= '<a class="bn-action-card" href="/loans/calculator"><strong>محاسبه تسهیلات</strong><span>محاسبه اقساط براساس قواعد ثبت‌شده.</span></a>';
    }
    if($canDocumentsOverview) $content .= '<a class="bn-action-card" href="/documents"><strong>مدیریت مدارک</strong><span>نسخه‌بندی و بازبینی امن مدارک.</span></a>';
    if($super) $content .= '<a class="bn-action-card" href="/users"><strong>کاربران و نقش‌ها</strong><span>مدیریت دسترسی کاربران سازمانی.</span></a>';
    if($canReportsOverview) $content .= '<a class="bn-action-card" href="/reports"><strong>گزارش‌های مدیریتی</strong><span>آخرین آمار سرویس‌های مجاز.</span></a>';
    $content .= '</div></section>';
    $portalCards='';
    foreach (['identity'=>'احراز هویت','loans'=>'تسهیلات','documents'=>'مدارک','partners'=>'پذیرندگان و همکاری','finance'=>'مالی','integrations'=>'اتصالات'] as $slug=>$label) {
        if (\Babanoell\Panel\Sso\UserRolePolicy::allowed($slug,$roles,$permissions)) {
            $portalCards.='<a class="bn-action-card" href="https://'.$slug.'.noeel.ir/portal.php"><strong>'.Renderer::e($label).'</strong><span>ورود امن بدون دریافت مجدد رمز</span></a>';
        }
    }
    if ($portalCards!=='') $content.='<section class="bn-section"><div class="bn-sectionhead"><div><h2 class="bn-sectiontitle">دسترسی یکپارچه سرویس‌ها</h2>'
        .'<p class="bn-sectiondesc">ورود با نشست معتبر Identity و کنترل مجوز در هر سرویس.</p></div></div><div class="bn-action-grid">'.$portalCards.'</div></section>';

    echo Renderer::page('داشبورد', $content, $identity, 'dashboard');
    exit;
}

if ($path === '/system') {
    if (!$panelSession->isSuperAdmin()) {
        http_response_code(403);
        echo Renderer::page(
            'دسترسی غیرمجاز',
            '<section class="bn-section bn-panel-error"><div class="bn-panel-error-code">۴۰۳</div><h1>دسترسی به این بخش مجاز نیست</h1><p>این صفحه فقط برای مدیر ارشد سامانه در دسترس است.</p><a class="bn-btn bn-btn--primary" href="/dashboard">بازگشت به داشبورد</a></section>',
            $identity,
            'system',
        );
        exit;
    }

    $identityHealth = $identityClient->health();
    $loansHealth = $loansClient->health();
    $documentsHealth = $documentsClient->health();
    $financeHealth = $financeClient->health();
    $partnersHealth = $partnersClient->health();
    $integrationsHealth = $integrationsClient->health();
    $identityOk = $identityHealth->ok() && (($identityHealth->json['status'] ?? null) === 'ok');
    $loansOk = $loansHealth->ok() && (($loansHealth->json['status'] ?? null) === 'ok');
    $documentsOk = $documentsHealth->ok() && (($documentsHealth->json['status'] ?? null) === 'ok');
    $financeOk = $financeHealth->ok() && (($financeHealth->json['status'] ?? null) === 'ok');
    $partnersOk = $partnersHealth->ok() && (($partnersHealth->json['status'] ?? null) === 'ok');
    $integrationsOk = $integrationsHealth->ok() && (($integrationsHealth->json['data']['status'] ?? $integrationsHealth->json['status'] ?? null) === 'ok');
    $content = '<div class="bn-pagehead"><div class="bn-pagehead-main"><div class="bn-eyebrow">کنترل زیرساخت</div><h1 class="bn-title">وضعیت سامانه</h1>'
        . '<p class="bn-subtitle">سلامت سرویس‌های اصلی سامانه را از یک نقطه بررسی کنید.</p></div></div>'
        . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۱</span><div><h2 class="bn-sectiontitle">سرویس‌های سامانه</h2><p class="bn-sectiondesc">عدم دسترسی هر سرویس در این محیط باید پیش از ادامه توسعه برطرف شود.</p></div></div>'
        . '<div class="bn-panel-health-row"><div class="bn-panel-health-main"><span class="bn-panel-health-dot ' . ($identityOk ? '' : 'is-bad') . '"></span><strong>سرویس احراز هویت</strong><p>ورود، نشست، نقش و مجوزها</p></div><span class="bn-status ' . ($identityOk ? 'bn-status--ok' : 'bn-status--bad') . '">' . ($identityOk ? 'در دسترس' : 'در دسترس نیست') . '</span></div>'
        . '<div class="bn-panel-health-row"><div class="bn-panel-health-main"><span class="bn-panel-health-dot ' . ($loansOk ? '' : 'is-bad') . '"></span><strong>سرویس تسهیلات</strong><p>طرح‌ها، محاسبات، پرونده و اقساط</p></div><span class="bn-status ' . ($loansOk ? 'bn-status--ok' : 'bn-status--bad') . '">' . ($loansOk ? 'در دسترس' : 'در دسترس نیست') . '</span></div>'
        . '<div class="bn-panel-health-row"><div class="bn-panel-health-main"><span class="bn-panel-health-dot ' . ($documentsOk ? '' : 'is-bad') . '"></span><strong>سرویس مدارک</strong><p>ذخیره خصوصی، نسخه‌بندی، بازبینی و دسترسی امن</p></div><span class="bn-status ' . ($documentsOk ? 'bn-status--ok' : 'bn-status--bad') . '">' . ($documentsOk ? 'در دسترس' : 'در دسترس نیست') . '</span></div>'
        . '<div class="bn-panel-health-row"><div class="bn-panel-health-main"><span class="bn-panel-health-dot ' . ($financeOk ? '' : 'is-bad') . '"></span><strong>سرویس مالی</strong><p>تسویه، سیکل، رسید و مغایرت</p></div><span class="bn-status ' . ($financeOk ? 'bn-status--ok' : 'bn-status--bad') . '">' . ($financeOk ? 'در دسترس' : 'در دسترس نیست') . '</span></div>'
        . '<div class="bn-panel-health-row"><div class="bn-panel-health-main"><span class="bn-panel-health-dot ' . ($partnersOk ? '' : 'is-bad') . '"></span><strong>سرویس شرکا</strong><p>همکاری، پذیرندگان و قراردادها</p></div><span class="bn-status ' . ($partnersOk ? 'bn-status--ok' : 'bn-status--bad') . '">' . ($partnersOk ? 'در دسترس' : 'در دسترس نیست') . '</span></div>'
        . '<div class="bn-panel-health-row"><div class="bn-panel-health-main"><span class="bn-panel-health-dot ' . ($integrationsOk ? '' : 'is-bad') . '"></span><strong>سرویس ارتباطات</strong><p>پیامک، اعتبارسنجی و GreenPay</p></div><span class="bn-status ' . ($integrationsOk ? 'bn-status--ok' : 'bn-status--bad') . '">' . ($integrationsOk ? 'در دسترس' : 'در دسترس نیست') . '</span></div>'
        . '<div class="bn-panel-health-row"><div class="bn-panel-health-main"><span class="bn-panel-health-dot"></span><strong>نسخه پنل</strong><p>نسخه یکپارچه مدیریت تسهیلات و مدارک</p></div><span class="bn-status bn-status--ok">'.Renderer::e(PersianDateTime::digits((require $root.'/config/version.php')['version'])).'</span></div>'
        . '</section>';

    echo Renderer::page('وضعیت سامانه', $content, $identity, 'system');
    exit;
}

http_response_code(404);
echo Renderer::page(
    'صفحه پیدا نشد',
    '<section class="bn-section bn-panel-error"><div class="bn-panel-error-code">۴۰۴</div><h1>صفحه موردنظر پیدا نشد</h1><p>آدرس واردشده در پنل وجود ندارد یا به بخش دیگری منتقل شده است.</p><a class="bn-btn bn-btn--primary" href="/dashboard">بازگشت به داشبورد</a></section>',
    $identity,
);
