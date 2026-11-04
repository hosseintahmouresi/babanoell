<?php
declare(strict_types=1);
namespace Babanoell\Panel\View;

use Babanoell\Panel\Support\PersianDateTime;

final class Renderer
{
    /** Cache-busting version comes from the installed release, never a stale constant. */
    private static function assetVersion(): string
    {
        $release = \Babanoell\Panel\Platform\Ui::version();
        $version = (string) ($release['ui'] ?? $release['version'] ?? '1');
        return rawurlencode($version);
    }

    /** @param array<string,mixed>|null $identity */
    public static function page(string $title, string $content, ?array $identity = null, string $active = 'dashboard'): string
    {
        return $identity === null
            ? self::guestPage($title, $content)
            : self::panelPage($title, $content, $identity, $active);
    }

    public static function guestFrame(string $title, string $content, string $tag = 'پنل مدیریت'): string
    {
        if(str_contains($content,'name="identifier"')&&!str_contains($content,'bn-login-grid'))$content=\Babanoell\Panel\Platform\Ui::loginBody($content);
        return \Babanoell\Panel\Platform\Ui::head($title,$tag).$content.\Babanoell\Panel\Platform\Ui::end();
    }

    private static function guestPage(string $title, string $content): string
    {
        return self::guestFrame($title, $content, 'ورود به پنل');
    }

    /** @param array<string,mixed> $identity */
    private static function panelPage(string $title, string $content, array $identity, string $active): string
    {
        $rawName = (string) ($identity['user']['display_name'] ?? 'کاربر سامانه');
        $name = self::e($rawName);
        $role = self::e(self::roleLabel($identity['roles'] ?? []));
        $nowDate = PersianDateTime::now();
        $now = self::e(PersianDateTime::full($nowDate));

        return self::documentHead($title)
            . '<body class="bn-panel-body"><a class="bn-skip-link" href="#main-content">رفتن به محتوای اصلی</a><div class="bn-panel-layout">'
            . '<aside class="bn-panel-sidebar" data-panel-sidebar>'
            . '<div class="bn-panel-sidebar-brand"><span class="bn-logo"><img src="/ui-asset.php?file=brand-logo.png&amp;v=' . self::assetVersion() . '" alt="لوگوی بابانوئل" width="104" height="35"></span>'
            . '<div><div class="bn-panel-sidebar-title">مرکز مدیریت سرویس‌ها</div><div class="bn-panel-sidebar-meta">پنل یکپارچه پلتفرم بابانوئل</div></div></div>'
            . self::sidebar($active, $identity)
            . '<div class="bn-panel-sidebar-foot"><span class="bn-panel-live-dot"></span><span>نشست امن سمت سرور</span></div>'
            . '</aside>'
            . '<main class="bn-panel-main">'
            . '<header class="bn-panel-topbar">'
            . '<button class="bn-panel-menu-toggle" type="button" aria-label="نمایش منوی پنل" data-menu-toggle>☰</button>'
            . '<time class="bn-panel-top-meta" datetime="' . self::e($nowDate->format(DATE_ATOM)) . '">' . $now . '</time>'
            . '<div class="bn-topbar-tools">'
            . '<form method="post" action="/logout" class="bn-inline-form bn-inline-form--logout"><input type="hidden" name="_csrf" value="' . self::e(\Babanoell\Panel\Security\Csrf::token()) . '"><button class="bn-topbar-tool" type="submit" title="خروج" aria-label="خروج"><span>⎋</span><small>خروج</small></button></form>'
            . '</div>'
            . '<div class="bn-panel-user"><span class="bn-panel-user-avatar">' . self::e(self::initial($rawName)) . '</span><span><span class="bn-panel-user-name">' . $name . '</span><span class="bn-panel-user-role">' . $role . '</span></span></div>'
            . '</header>'
            . '<div class="bn-panel-content" id="main-content" tabindex="-1">' . $content . self::footer('پنل یکپارچه مدیریت') . '</div>'
            . '</main><button class="bn-chat-fab" type="button" data-goftino-open aria-label="چت آنلاین">💬<span>چت آنلاین</span></button><div class="bn-panel-overlay" data-menu-overlay></div></div>'
            . self::script() . '</body></html>';
    }

    private static function documentHead(string $title): string
    {
        return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
            . '<meta name="robots" content="noindex, nofollow, noarchive">'
            . '<meta name="theme-color" content="#1D3559"><meta name="color-scheme" content="light">'
            . '<title>' . self::e($title) . ' | پلتفرم بابانوئل</title>'
            . '<link rel="icon" type="image/png" href="/ui-asset.php?file=brand-logo.png&amp;v=' . self::assetVersion() . '">'
            . '<link rel="stylesheet" href="/ui-asset.php?file=platform.css&amp;v=' . self::assetVersion() . '">'
            . '</head>';
    }

    private static function brandBar(string $tag): string
    {
        return '<header class="bn-brandbar">'
            . '<div class="bn-lockup"><span class="bn-logo"><img src="/ui-asset.php?file=brand-logo.png&amp;v=' . self::assetVersion() . '" alt="لوگوی بابانوئل" width="118" height="40"></span>'
            . '<div class="bn-brandtext"><div class="bn-brandtitle">مرکز مدیریت سرویس‌ها</div><div class="bn-brandmeta">پنل یکپارچه پلتفرم بابانوئل</div></div></div>'
            . '<div class="bn-brandtag"><span class="bn-brandtag-dot"></span><span>' . self::e($tag) . '</span><span class="bn-version">Panel</span></div>'
            . '</header>';
    }

    private static function footer(string $context): string
    {
        return \Babanoell\Panel\Platform\Ui::footer();
    }

    private static function script(): string
    {
        return '<script src="/ui-asset.php?file=platform.js&amp;v=' . self::assetVersion() . '" defer></script>';
    }

    /** @param array<string,mixed> $identity */
    private static function sidebar(string $active, array $identity): string
    {
        $roles=is_array($identity['roles']??null)?$identity['roles']:[];
        $permissions=is_array($identity['permissions']??null)?$identity['permissions']:[];
        $super=in_array('super_admin',$roles,true);
        $acceptor=in_array('acceptor',$roles,true)&&!$super&&!in_array('admin',$roles,true);
        $applicant=in_array('applicant',$roles,true)&&!$super&&!in_array('admin',$roles,true);
        $canLoans=$super||in_array('loans.plans.read',$permissions,true)||in_array('loans.requests.read',$permissions,true);
        $canDocuments=$super||in_array('documents.view',$permissions,true)||in_array('documents.manage',$permissions,true);
        $canBank=$super||in_array('loans.requests.bank_review',$permissions,true)||in_array('loans.requests.workflow_admin',$permissions,true);
        $canPartners=$super||in_array('partners.merchants.read',$permissions,true)||in_array('partners.cooperation.read',$permissions,true)||in_array('partners.cooperation.create',$permissions,true)||in_array('partners.marketplace.read',$permissions,true);
        $canFinance=$super||in_array('finance.dashboard.read',$permissions,true)||in_array('finance.cycles.read',$permissions,true)||in_array('finance.bank_settlement.read',$permissions,true)||in_array('finance.merchant_settlement.read',$permissions,true);
        $item=static fn(string$key,string$href,string$icon,string$label):string=>'<a class="bn-panel-nav-item'.($active===$key?' is-active':'').'" href="'.$href.'"><span class="bn-panel-nav-icon">'.$icon.'</span><span>'.$label.'</span></a>';
        if($acceptor){
            return '<nav class="bn-panel-nav"><div class="bn-panel-nav-label">پنل پذیرنده</div>'
                .$item('acceptor','/acceptor','⌂','داشبورد پذیرنده')
                .$item('acceptor-calculator','/acceptor/calculator','◈','محاسبه تسهیلات')
                .$item('acceptor-requests','/acceptor/requests','▤','پرونده‌های من')
                .$item('acceptor-new','/acceptor/requests/new','＋','ثبت پرونده جدید')
                .($canPartners?$item('partners','/partners/marketplace','◇','بازار پرونده‌ها'):'')
                .'<div class="bn-panel-nav-label">دسترسی پرونده</div><span class="bn-panel-nav-note">مدارک هر پرونده از داخل همان پرونده در دسترس است.</span></nav>';
        }
        if($applicant){
            return '<nav class="bn-panel-nav"><div class="bn-panel-nav-label">پنل متقاضی</div>'
                .$item('applicant','/applicant','⌂','داشبورد متقاضی')
                .$item('applicant-calculator','/applicant/calculator','◈','محاسبه تسهیلات')
                .$item('applicant-requests','/applicant/requests','▤','پرونده‌های من')
                .$item('applicant-new','/applicant/requests/new','＋','ثبت درخواست جدید')
                .($canPartners?$item('partners','/partners/cooperation','◇','همکاری با بابانوئل'):'')
                .'<div class="bn-panel-nav-label">دسترسی پرونده</div><span class="bn-panel-nav-note">مدارک و ضامن‌های هر پرونده از داخل همان پرونده قابل مدیریت هستند.</span></nav>';
        }
        $html='<nav class="bn-panel-nav"><div class="bn-panel-nav-label">فضای کاری</div>'.$item('dashboard','/dashboard','⌂','داشبورد');
        if($super)$html.=$item('system','/system','⚙','وضعیت سامانه').$item('settings','/system/settings','⚒','تنظیمات سرویس‌ها').$item('updates','/system/updates','⇧','مرکز به‌روزرسانی').$item('users','/users','♙','کاربران و نقش‌ها');
        $html.='<div class="bn-panel-nav-label">ماژول‌های سامانه</div>';
        if($canLoans){$html.=$item('loans','/loans','◈','تسهیلات').$item('tasks','/loans/tasks?scope=mine','✓','کارتابل من');}
        else{$html.='<span class="bn-panel-nav-item is-disabled"><span class="bn-panel-nav-icon">◈</span><span>تسهیلات</span></span>';}
        if($canBank)$html.=$item('bank','/bank','▦','کارتابل بانک');
        if($canDocuments){$html.=$item('documents','/documents','▣','مدارک');}
        else{$html.='<span class="bn-panel-nav-item is-disabled"><span class="bn-panel-nav-icon">▣</span><span>مدارک</span></span>';}
        if($canPartners)$html.=$item('partners','/partners','◇','شرکا');else$html.='<span class="bn-panel-nav-item is-disabled"><span class="bn-panel-nav-icon">◇</span><span>شرکا</span></span>';
        if($canFinance)$html.=$item('finance','/finance','◫','مالی');else$html.='<span class="bn-panel-nav-item is-disabled"><span class="bn-panel-nav-icon">◫</span><span>مالی</span></span>';
        $canReports=$super||in_array('loans.requests.read',$permissions,true)||in_array('partners.merchants.read',$permissions,true)||in_array('finance.dashboard.read',$permissions,true);
        if($canReports)$html.=$item('reports','/reports','▤','گزارش‌های مدیریتی');
        if($super)$html.=$item('reports-history','/reports/history','▥','گزارش تاریخی');
        return$html.'</nav>';
    }

    /** @param mixed $roles */
    private static function roleLabel(mixed $roles): string
    {
        if (!is_array($roles)) return 'کاربر سامانه';
        $labels = [
            'super_admin'=>'مدیر ارشد سامانه',
            'admin'=>'مدیر سامانه',
            'operations_expert'=>'کارشناس عملیات بابانوئل',
            'finance_expert'=>'کارشناس مالی بابانوئل',
            'contract_expert'=>'کارشناس قرارداد بابانوئل',
            'bank_expert'=>'کارشناس بانک',
            'cooperation_operator'=>'کارشناس همکاری پذیرندگان',
            'acceptor'=>'پذیرنده',
            'applicant'=>'متقاضی تسهیلات',
        ];
        foreach ($labels as $name=>$label) if (in_array($name,$roles,true)) return $label;
        return 'کاربر سامانه';
    }

    private static function initial(string $name): string
    {
        $clean = strip_tags(trim($name));
        if ($clean === '') {
            return 'ک';
        }
        return preg_match('/^./us', $clean, $match) === 1 ? $match[0] : substr($clean, 0, 1);
    }

    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
