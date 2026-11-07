<?php
declare(strict_types=1);
namespace Babanoell\Panel\Acceptor;

use Babanoell\Panel\Http\HttpResponse;
use Babanoell\Panel\Http\LoansClient;
use Babanoell\Panel\Loans\DashboardSummary;
use Babanoell\Panel\Loans\CaseJourney;
use Babanoell\Panel\Support\FormOperation;
use Babanoell\Panel\Security\Csrf;
use Babanoell\Panel\Support\PersianDateTime;
use Babanoell\Panel\View\Renderer;

final class AcceptorModule
{
    public function __construct(
        private readonly LoansClient $client,
        private readonly string $bearer,
        private readonly array $identity,
    ) {}

    public function handle(string $path, string $method): bool
    {
        if (!$this->isAcceptor()) return false;
        if ($path === '/acceptor') { $this->overview(); return true; }
        if ($path === '/acceptor/calculator') { $this->calculator($method); return true; }
        if ($path === '/acceptor/requests') { $this->requests(); return true; }
        if ($path === '/acceptor/requests/new') { $method === 'POST' ? $this->createRequest() : $this->requestForm(); return true; }
        if (preg_match('#^/acceptor/requests/([a-f0-9]{26})/edit$#', $path, $m)) { $method === 'POST' ? $this->editRequest($m[1]) : $this->requestEditForm($m[1]); return true; }
        if (preg_match('#^/acceptor/requests/([a-f0-9]{26})/guarantors$#', $path, $m) && $method === 'POST') { $this->saveGuarantors($m[1]); return true; }
        if (preg_match('#^/acceptor/requests/([a-f0-9]{26})/submit$#', $path, $m) && $method === 'POST') { $this->submitRequest($m[1]); return true; }
        if (preg_match('#^/acceptor/requests/([a-f0-9]{26})/messages$#', $path, $m) && $method === 'POST') { $this->sendMessage($m[1]); return true; }
        if (preg_match('#^/acceptor/requests/([a-f0-9]{26})$#', $path, $m)) { $this->requestDetail($m[1]); return true; }
        return false;
    }

    private function isAcceptor(): bool
    {
        $roles=is_array($this->identity['roles']??null)?$this->identity['roles']:[];
        return in_array('acceptor',$roles,true)&&!in_array('super_admin',$roles,true)&&!in_array('admin',$roles,true);
    }

    private function overview(): void
    {
        $dashboard = $this->acceptorDashboardData();
        $caseNotices = is_array($dashboard['case_notifications'] ?? null) ? $dashboard['case_notifications'] : [];
        $generalNotices = is_array($dashboard['general_notifications'] ?? null) ? $dashboard['general_notifications'] : [];
        $notice = '';
        if (($dashboard['error'] ?? '') !== '') {
            $notice = $this->alert('error', 'اطلاعات داشبورد کامل نیست', (string)$dashboard['error']);
        }
        $content = $this->head('فضای پذیرندگی شما', 'سفارش‌ها و پرونده‌ها را پیگیری کنید و برای مشتریان خود تسهیلات ثبت کنید.', '<a class="bn-btn bn-btn--primary" href="/acceptor/requests/new">'.Renderer::icon('plus').' ثبت پرونده جدید</a>')
            . $this->takeFlash()
            . $notice
            . $this->notificationCards($caseNotices, $generalNotices)
            . '<div class="bn-stats bn-stats--merchant">'
            . $this->stat('ارزش سفارش‌های این ماه', DashboardSummary::money($dashboard['monthly_sales_amount']), 'ریال · ماه شمسی '.PersianDateTime::digits($dashboard['period_label']))
            . $this->stat('گرید پذیرنده', $dashboard['merchant_grade'], 'بر اساس ارزش سفارش‌ها', true)
            . $this->stat('ارزش کل سفارش‌ها', DashboardSummary::money($dashboard['total_sales_amount']), 'ریال · پرونده‌های قابل دسترسی در همه وضعیت‌ها')
            . $this->stat('مبلغ پرونده‌های در حال بررسی', DashboardSummary::money($dashboard['open_case_amount']), 'ریال')
            . '</div>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۱</span><div><h2 class="bn-sectiontitle">دسترسی سریع</h2><p class="bn-sectiondesc">عملیات اصلی تسهیلات را از این بخش آغاز کنید.</p></div></div>'
            . '<div class="bn-loans-actions">'
            . $this->actionCard('/acceptor/calculator', 'محاسبه تسهیلات', 'محاسبه اقساط فقط بر اساس طرح‌های فعال سامانه')
            . $this->actionCard('/acceptor/requests', 'پرونده‌های من', 'مشاهده و پیگیری پرونده‌هایی که با این حساب ثبت شده‌اند')
            . $this->actionCard('/acceptor/requests/new', 'ثبت پرونده جدید', 'ثبت اطلاعات متقاضی و ارسال پرونده برای بررسی')
            . '</div></section>';
        echo Renderer::page('تسهیلات', $content, $this->identity, 'acceptor');
    }

    private function plans(): void
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $status = trim((string)($_GET['status'] ?? ''));
        $type = trim((string)($_GET['type'] ?? ''));
        $q = ['page'=>$page, 'per_page'=>20];
        if ($status !== '') $q['status'] = $status;
        if ($type !== '') $q['type'] = $type;
        $r = $this->client->get('/api/v1/plans', $this->bearer, $q);
        if (!$r->ok()) { $this->errorPage('طرح‌های تسهیلات', $r); return; }
        $data = is_array($r->json['data'] ?? null) ? $r->json['data'] : [];
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $rows = '';
        foreach ($items as $p) {
            if (!is_array($p)) continue;
            $rows .= '<tr><td><a class="bn-table-link" href="/acceptor/plans/'.Renderer::e((string)$p['id']).'"><strong>'.Renderer::e((string)$p['name']).'</strong><small>'.Renderer::e((string)$p['code']).'</small></a></td>'
                . '<td>'.$this->typeLabel((string)$p['type']).'</td>'
                . '<td>'.$this->status((string)$p['status'], $this->planStatusLabel((string)$p['status'])).'</td>'
                . '<td>'.PersianDateTime::digits((string)$p['current_version']).'</td>'
                . '<td>'.Renderer::e(PersianDateTime::fromIso((string)($p['updated_at'] ?? ''), false) ?? '—').'</td></tr>';
        }
        if ($rows === '') $rows = '<tr><td colspan="5"><div class="bn-empty-inline"><strong>طرحی پیدا نشد</strong><span>فیلترها را تغییر دهید یا یک طرح جدید ایجاد کنید.</span></div></td></tr>';
        $content = $this->head('طرح‌های تسهیلات', 'طرح‌های بایگانی‌شده از فهرست عادی پنهان‌اند و از فیلتر وضعیت قابل مشاهده و فعال‌سازی مجدد هستند.', '<a class="bn-btn bn-btn--secondary" href="/acceptor/plans?status=archived">نمایش بایگانی</a><a class="bn-btn bn-btn--primary" href="/acceptor/plans/new">ایجاد طرح جدید</a>')
            . $this->takeFlash()
            . '<section class="bn-section"><form class="bn-filterbar" method="get" action="/acceptor/plans"><div class="bn-field"><label class="bn-label">نوع طرح</label><select name="type"><option value="">همه انواع</option>'.$this->option('market','بازاری',$type).$this->option('leasing','لیزینگی',$type).'</select></div><div class="bn-field"><label class="bn-label">وضعیت</label><select name="status"><option value=""'.($status === '' ? ' selected' : '').'>جاری (بدون بایگانی)</option>'.$this->option('active','فعال',$status).$this->option('inactive','غیرفعال',$status).$this->option('draft','پیش‌نویس',$status).$this->option('archived','بایگانی',$status).$this->option('all','همه وضعیت‌ها (با بایگانی)',$status).'</select></div><div class="bn-filter-actions"><button class="bn-btn bn-btn--primary" type="submit">اعمال فیلتر</button><a class="bn-btn bn-btn--secondary" href="/acceptor/plans">پاک‌کردن</a></div></form></section>'
            . '<section class="bn-section"><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>طرح</th><th>نوع</th><th>وضعیت</th><th>نسخه</th><th>آخرین تغییر</th></tr></thead><tbody>'.$rows.'</tbody></table></div>'.$this->pagination('/acceptor/plans', (int)($data['page'] ?? $page), (int)($data['per_page'] ?? 20), (int)($data['total'] ?? count($items)), ['status'=>$status,'type'=>$type]).'</section>';
        echo Renderer::page('طرح‌های تسهیلات', $content, $this->identity, 'acceptor');
    }

    private function planForm(?string $id = null, string $error = ''): void
    {
        $plan = null;
        if ($id !== null) {
            $r = $this->client->get('/api/v1/plans/'.$id, $this->bearer);
            if (!$r->ok()) { $this->errorPage('ویرایش طرح', $r); return; }
            $plan = $r->json['data']['plan'] ?? null;
        }
        $stored = is_array($plan['settings'] ?? null) ? $plan['settings'] : [];
        $type = (string)($_POST['type'] ?? ($plan['type'] ?? 'market'));
        if (!in_array($type, ['market','leasing'], true)) $type = 'market';
        $market = [
            'min_amount'=>10000000,'max_amount'=>500000000,'fixed_monthly_rate'=>3,'prepayment_percent'=>20,
            'supplier_costs_percent'=>1,'intermediary_deductions_percent'=>1,'babanovel_deductions_percent'=>1,'insurance_percent'=>1,
            'guarantee_check_percent'=>150,'acceptor_commission_percent'=>1,'additional_deductions_percent'=>1,
            'acceptor_deductions_amount'=>30000000,'vat_percent'=>10,'active_installments'=>'6,12,18,24','requires_bank_validation'=>false,
        ];
        $leasing = [
            'min_amount'=>100000000,'max_amount'=>1000000000,'min_interval'=>1,'max_interval'=>3,'interest_rate'=>23,'effective_interest_rate'=>25,
            'credit_share_percent'=>80,'supplier_costs_percent'=>1,'intermediary_deductions_percent'=>0.5,'babanovel_deductions_percent'=>1,'insurance_percent'=>0.5,
            'other_deductions_amount'=>0,'guarantee_check_percent'=>130,'acceptor_commission_percent'=>2,'additional_deductions_percent'=>0,
            'acceptor_deductions_amount'=>0,'vat_percent'=>10,'active_installments'=>'12,18,24,36','requires_bank_validation'=>false,
        ];
        $val = function(string $key, mixed $default = '') use ($stored): string {
            if (array_key_exists($key, $_POST)) return (string)$_POST[$key];
            if (array_key_exists($key, $stored)) return is_array($stored[$key]) ? implode(',', $stored[$key]) : (string)$stored[$key];
            return (string)$default;
        };
        $commonDefaults = $type === 'leasing' ? $leasing : $market;
        $common = $this->moneyField('حداقل مبلغ', 'min_amount', $val('min_amount', $commonDefaults['min_amount']), true)
            . $this->moneyField('حداکثر مبلغ', 'max_amount', $val('max_amount', $commonDefaults['max_amount']), true)
            . $this->percentField('هزینه تأمین‌کننده', 'supplier_costs_percent', $val('supplier_costs_percent', $commonDefaults['supplier_costs_percent']))
            . $this->percentField('کسورات واسطه', 'intermediary_deductions_percent', $val('intermediary_deductions_percent', $commonDefaults['intermediary_deductions_percent']))
            . $this->percentField('کسورات بابانوئل', 'babanovel_deductions_percent', $val('babanovel_deductions_percent', $commonDefaults['babanovel_deductions_percent']))
            . $this->percentField('بیمه تسهیلات', 'insurance_percent', $val('insurance_percent', $commonDefaults['insurance_percent']))
            . $this->percentField('چک ضمانت', 'guarantee_check_percent', $val('guarantee_check_percent', $commonDefaults['guarantee_check_percent']))
            . $this->percentField('کمیسیون پذیرنده', 'acceptor_commission_percent', $val('acceptor_commission_percent', $commonDefaults['acceptor_commission_percent']))
            . $this->percentField('سایر کسورات', 'additional_deductions_percent', $val('additional_deductions_percent', $commonDefaults['additional_deductions_percent']))
            . $this->moneyField('کسورات ثابت پذیرنده', 'acceptor_deductions_amount', $val('acceptor_deductions_amount', $commonDefaults['acceptor_deductions_amount']))
            . $this->percentField('مالیات بر ارزش افزوده', 'vat_percent', $val('vat_percent', $commonDefaults['vat_percent']))
            . $this->field('تعداد اقساط فعال', 'active_installments', $val('active_installments', $commonDefaults['active_installments']), 'text', 'numeric', 'مثال: ۶، ۱۲، ۱۸، ۲۴');
        $marketFields = $this->percentField('سود ثابت ماهیانه', 'fixed_monthly_rate', $val('fixed_monthly_rate', $market['fixed_monthly_rate']))
            . $this->percentField('پیش‌پرداخت', 'prepayment_percent', $val('prepayment_percent', $market['prepayment_percent']));
        $leasingFields = $this->percentField('نرخ سود اسمی', 'interest_rate', $val('interest_rate', $leasing['interest_rate']))
            . $this->percentField('نرخ سود مؤثر', 'effective_interest_rate', $val('effective_interest_rate', $leasing['effective_interest_rate']))
            . $this->percentField('سهم اعتباری', 'credit_share_percent', $val('credit_share_percent', $leasing['credit_share_percent']))
            . $this->field('حداقل فاصله اقساط (ماه)', 'min_interval', $val('min_interval', $leasing['min_interval']), 'number', 'numeric')
            . $this->field('حداکثر فاصله اقساط (ماه)', 'max_interval', $val('max_interval', $leasing['max_interval']), 'number', 'numeric')
            . $this->moneyField('سایر کسورات ثابت لیزینگ', 'other_deductions_amount', $val('other_deductions_amount', $leasing['other_deductions_amount']));
        $checked = array_key_exists('requires_bank_validation', $_POST)
            ? ((string)$_POST['requires_bank_validation'] === '1')
            : filter_var($stored['requires_bank_validation'] ?? $commonDefaults['requires_bank_validation'], FILTER_VALIDATE_BOOL);
        $requiredCatalog = $this->requiredDocumentLabels();
        $requiredSelected = is_array($_POST['required_documents'] ?? null)
            ? array_values(array_filter($_POST['required_documents'], 'is_string'))
            : (is_array($plan['required_documents'] ?? null) ? array_values(array_filter($plan['required_documents'], 'is_string')) : []);
        $requiredBoxes = '';
        foreach ($requiredCatalog as $code => $label) {
            $requiredBoxes .= '<label class="bn-checkline"><input type="checkbox" name="required_documents[]" value="'.Renderer::e($code).'"'.(in_array($code, $requiredSelected, true) ? ' checked' : '').'><span>'.Renderer::e($label).'</span></label>';
        }
        $content = $this->head($id ? 'ویرایش طرح' : 'ایجاد طرح تسهیلات', $id ? 'با ثبت تغییرات، نسخه جدید ساخته می‌شود و پرونده‌های قبلی به نسخه فعلی خود متصل می‌مانند.' : 'نوع طرح و قواعد محاسباتی را مشخص کنید. پس از بازبینی می‌توانید طرح را فعال کنید.')
            . ($error !== '' ? $this->alert('error', 'ذخیره انجام نشد', $error) : '')
            . '<form method="post" action="'.($id ? '/acceptor/plans/'.$id.'/edit' : '/acceptor/plans').'" data-submit-lock="1"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'">'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۱</span><div><h2 class="bn-sectiontitle">مشخصات طرح</h2><p class="bn-sectiondesc">نام و نوع طرح در تمام بخش‌های سامانه نمایش داده می‌شود.</p></div></div><div class="bn-grid bn-grid--2">'
            . $this->field('نام طرح', 'name', (string)($_POST['name'] ?? ($plan['name'] ?? '')), 'text', 'text', '', true)
            . ($id ? '<div class="bn-field"><label class="bn-label">کد طرح</label><input value="'.Renderer::e((string)$plan['code']).'" readonly aria-readonly="true"><span class="bn-helper">کد طرح بعد از ایجاد تغییر نمی‌کند.</span></div>' : $this->field('کد طرح', 'code', (string)($_POST['code'] ?? ''), 'text', 'latin', 'حروف انگلیسی، عدد، خط تیره یا زیرخط', true))
            . ($id
                ? '<div class="bn-field"><label class="bn-label">نوع طرح</label><input type="hidden" name="type" value="'.Renderer::e($type).'"><input value="'.Renderer::e($this->typeLabel($type)).'" readonly aria-readonly="true"><span class="bn-helper">نوع طرح بعد از ایجاد تغییر نمی‌کند؛ برای روش محاسبه متفاوت، طرح جدید بسازید.</span></div>'
                : '<div class="bn-field"><label class="bn-label" for="plan-type">نوع طرح <span class="bn-required">*</span></label><select id="plan-type" name="type" data-plan-type data-plan-defaults-market="'.Renderer::e(json_encode($market, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)).'" data-plan-defaults-leasing="'.Renderer::e(json_encode($leasing, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)).'"><option value="market"'.($type === 'market' ? ' selected' : '').'>بازاری</option><option value="leasing"'.($type === 'leasing' ? ' selected' : '').'>لیزینگی</option></select><span class="bn-helper" data-plan-type-hint>'.($type === 'market' ? 'اقساط ماهانه با نرخ ثابت طرح بازاری.' : 'محاسبه لیزینگی با نرخ سود و فاصله سررسید قابل تنظیم.').'</span></div>').'</div></section>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۲</span><div><h2 class="bn-sectiontitle">قواعد محاسبه</h2><p class="bn-sectiondesc">فقط فیلدهای مربوط به نوع طرح انتخاب‌شده در محاسبات استفاده می‌شوند.</p></div></div>'
            . '<div class="bn-plan-fields" data-plan-fields="market"'.($type !== 'market' ? ' hidden' : '').'><div class="bn-grid bn-grid--2">'.$marketFields.'</div></div>'
            . '<div class="bn-plan-fields" data-plan-fields="leasing"'.($type !== 'leasing' ? ' hidden' : '').'><div class="bn-grid bn-grid--2">'.$leasingFields.'</div></div>'
            . '<div class="bn-grid bn-grid--2 bn-plan-common">'.$common.'<div class="bn-field"><input type="hidden" name="requires_bank_validation" value="0"><label class="bn-checkline"><input type="checkbox" name="requires_bank_validation" value="1"'.($checked ? ' checked' : '').'><span>این طرح به اعتبارسنجی بانک نیاز دارد</span></label><span class="bn-helper">در صورت فعال‌بودن، مراحل بانکی پرونده باید پیش از تأیید تکمیل شوند.</span></div></div></section>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۳</span><div><h2 class="bn-sectiontitle">مدارک الزامی این نسخه</h2><p class="bn-sectiondesc">پرونده‌های ساخته‌شده با این نسخه، همین چک‌لیست را برای تکمیل مدارک حفظ می‌کنند.</p></div></div><div class="bn-required-doc-grid">'.$requiredBoxes.'</div><div class="bn-page-note">اگر مدرکی الزامی نیست، همه گزینه‌ها را خالی بگذارید. تغییر این فهرست در ویرایش طرح، نسخه جدید ایجاد می‌کند.</div></section>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۴</span><div><h2 class="bn-sectiontitle">شرایط طرح</h2><p class="bn-sectiondesc">شرایط مهمی که اپراتور هنگام انتخاب طرح باید بداند.</p></div></div>'.$this->area('شرایط و توضیحات', 'conditions', (string)($_POST['conditions'] ?? ($plan['conditions_text'] ?? ''))).'</section>'
            . '<div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit">'.($id ? 'ثبت نسخه جدید' : 'ایجاد طرح').'</button><a class="bn-btn bn-btn--secondary" href="/acceptor/plans">انصراف</a></div></form>';
        echo Renderer::page($id ? 'ویرایش طرح' : 'ایجاد طرح', $content, $this->identity, 'acceptor');
    }

    private function createPlan(): void
    {
        if (!Csrf::valid((string)($_POST['_csrf'] ?? ''))) { $this->planForm(null, 'اعتبار فرم منقضی شده است. صفحه را تازه‌سازی کنید.'); return; }
        $r = $this->client->post('/api/v1/plans', $this->bearer, $this->planPayload(true));
        if (!$r->ok()) { $this->planForm(null, $this->apiMessage($r)); return; }
        $id = (string)($r->json['data']['plan']['id'] ?? '');
        $this->flash('success', 'طرح با موفقیت ایجاد شد.');
        header('Location: /acceptor/plans/'.$id, true, 303); exit;
    }

    private function editPlan(string $id): void
    {
        if (!Csrf::valid((string)($_POST['_csrf'] ?? ''))) { $this->planForm($id, 'اعتبار فرم منقضی شده است. صفحه را تازه‌سازی کنید.'); return; }
        $r = $this->client->put('/api/v1/plans/'.$id, $this->bearer, $this->planPayload(false));
        if (!$r->ok()) { $this->planForm($id, $this->apiMessage($r)); return; }
        $this->flash('success', 'نسخه جدید طرح با موفقیت ثبت شد.');
        header('Location: /acceptor/plans/'.$id, true, 303); exit;
    }

    private function planPayload(bool $new): array
    {
        $type = (string)($_POST['type'] ?? 'market');
        $keys = ['min_amount','max_amount','supplier_costs_percent','intermediary_deductions_percent','babanovel_deductions_percent','insurance_percent','guarantee_check_percent','acceptor_commission_percent','additional_deductions_percent','acceptor_deductions_amount','vat_percent'];
        $keys = array_merge($keys, $type === 'market' ? ['fixed_monthly_rate','prepayment_percent'] : ['interest_rate','effective_interest_rate','credit_share_percent','min_interval','max_interval','other_deductions_amount']);
        $settings = [];
        foreach ($keys as $key) $settings[$key] = $this->numberish($_POST[$key] ?? '');
        $settings['active_installments'] = trim((string)($_POST['active_installments'] ?? ''));
        $settings['requires_bank_validation'] = (string)($_POST['requires_bank_validation'] ?? '0') === '1';
        $required=is_array($_POST['required_documents']??null)?array_values(array_filter($_POST['required_documents'],'is_string')):[];$payload = ['name'=>trim((string)($_POST['name'] ?? '')), 'type'=>$type, 'settings'=>$settings, 'required_documents'=>$required, 'conditions'=>trim((string)($_POST['conditions'] ?? ''))];
        if ($new) $payload['code'] = trim((string)($_POST['code'] ?? ''));
        return $payload;
    }

    private function planDetail(string $id): void
    {
        $r = $this->client->get('/api/v1/plans/'.$id, $this->bearer);
        if (!$r->ok()) { $this->errorPage('جزئیات طرح', $r); return; }
        $p = $r->json['data']['plan'] ?? [];
        if (!is_array($p)) { $this->errorPage('جزئیات طرح', new HttpResponse(500, null)); return; }
        $settings = '';
        foreach ((array)($p['settings'] ?? []) as $key => $value) {
            $settings .= '<tr><td>'.Renderer::e($this->settingLabels()[(string)$key] ?? (string)$key).'</td><td>'.$this->formatSetting((string)$key, $value).'</td></tr>';
        }
        $currentStatus = (string)($p['status'] ?? 'draft');
        $buttons = '';
        foreach (['active'=>'فعال','inactive'=>'غیرفعال','draft'=>'پیش‌نویس','archived'=>'بایگانی'] as $code=>$label) {
            $buttons .= '<form method="post" action="/acceptor/plans/'.$id.'/status" class="bn-inline-form"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="status" value="'.$code.'"><button type="submit" class="bn-btn bn-btn--secondary bn-btn--small"'.($code === $currentStatus ? ' disabled aria-disabled="true"' : '').'>'.$label.'</button></form>';
        }
        $conditions = trim((string)($p['conditions_text'] ?? ''));
        $requiredDocs = '';
        foreach ((array)($p['required_documents'] ?? []) as $code) {
            if (!is_string($code)) continue;
            $requiredDocs .= '<span class="bn-pill">'.Renderer::e($this->requiredDocumentLabels()[$code] ?? $code).'</span>';
        }
        if ($requiredDocs === '') $requiredDocs = '<span class="bn-muted">برای این نسخه مدرک الزامی تعریف نشده است.</span>';
        $content = $this->head((string)$p['name'], 'کد '.(string)$p['code'].' · نسخه '.PersianDateTime::digits((string)$p['current_version']), '<a class="bn-btn bn-btn--primary" href="/acceptor/plans/'.$id.'/edit">ویرایش و ساخت نسخه جدید</a>')
            . $this->takeFlash()
            . '<div class="bn-stats">'.$this->stat('نوع', $this->typeLabel((string)$p['type']), 'روش محاسبه').$this->stat('وضعیت', $this->planStatusLabel($currentStatus), 'وضعیت عملیاتی').$this->stat('نسخه', PersianDateTime::digits((string)$p['current_version']), 'نسخه جاری', true).'</div>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۱</span><div><h2 class="bn-sectiontitle">تنظیمات نسخه جاری</h2><p class="bn-sectiondesc">مقادیر این نسخه پس از استفاده در پرونده تغییر نمی‌کنند.</p></div></div><div class="bn-table-wrap"><table class="bn-table bn-table--keyvalue"><tbody>'.$settings.'</tbody></table></div></section>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۲</span><div><h2 class="bn-sectiontitle">مدارک الزامی نسخه جاری</h2><p class="bn-sectiondesc">پرونده‌های این نسخه فقط پس از تأیید این مدارک وارد مرحله تأیید مدارک می‌شوند.</p></div></div><div class="bn-pill-list">'.$requiredDocs.'</div></section>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۳</span><div><h2 class="bn-sectiontitle">شرایط طرح</h2></div></div><div class="bn-prose">'.($conditions !== '' ? nl2br(Renderer::e($conditions)) : 'شرایط تکمیلی برای این طرح ثبت نشده است.').'</div></section>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۴</span><div><h2 class="bn-sectiontitle">تغییر وضعیت</h2><p class="bn-sectiondesc">وضعیت جاری غیرفعال است. طرح بایگانی را می‌توانید دوباره فعال، غیرفعال یا به پیش‌نویس برگردانید.</p></div></div><div class="bn-status-actions">'.$buttons.'</div></section>';
        echo Renderer::page('جزئیات طرح', $content, $this->identity, 'acceptor');
    }

    private function planStatus(string $id): void
    {
        if (!Csrf::valid((string)($_POST['_csrf'] ?? ''))) { $this->flash('error', 'اعتبار فرم منقضی شده است.'); header('Location: /acceptor/plans/'.$id, true, 303); exit; }
        $r = $this->client->post('/api/v1/plans/'.$id.'/status', $this->bearer, ['status'=>(string)($_POST['status'] ?? '')]);
        $this->flash($r->ok() ? 'success' : 'error', $r->ok() ? 'وضعیت طرح با موفقیت به‌روزرسانی شد.' : $this->apiMessage($r));
        header('Location: /acceptor/plans/'.$id, true, 303); exit;
    }

    private function calculator(string $method): void
    {
        $plans = $this->client->get('/api/v1/plans', $this->bearer, ['status'=>'active','per_page'=>100,'context'=>'merchant_request']);
        if (!$plans->ok()) { $this->errorPage('محاسبه‌گر تسهیلات', $plans); return; }
        $items = is_array($plans->json['data']['items'] ?? null) ? $plans->json['data']['items'] : [];
        $result = null; $error = '';
        if ($method === 'POST') {
            if (!Csrf::valid((string)($_POST['_csrf'] ?? ''))) $error = 'اعتبار فرم منقضی شده است.';
            else {
                $planId=trim((string)($_POST['plan_id'] ?? ''));
                $amount=$this->numberish($_POST['amount'] ?? 0);
                $installmentCount=$this->integerish($_POST['installment_count'] ?? 0);
                $installmentInterval=$this->integerish($_POST['installment_interval'] ?? 1);
                if($planId==='')$error='انتخاب طرح فعال الزامی است.';
                elseif($amount<=0)$error='مبلغ باید بیشتر از صفر باشد.';
                elseif($installmentCount<1)$error='تعداد اقساط باید یک عدد صحیح مثبت باشد.';
                elseif($installmentInterval<1)$error='فاصله سررسیدها باید یک عدد صحیح مثبت باشد.';
                else{
                    $r = $this->client->post('/api/v1/calculations', $this->bearer, [
                        'plan_id'=>$planId,
                        'amount'=>$amount,
                        'installment_count'=>$installmentCount,
                        'installment_interval'=>$installmentInterval,
                    ]);
                    if ($r->ok()) $result = $r->json['data']['result'] ?? null; else $error = $this->apiMessage($r);
                }
            }
        }
        $selected = (string)($_POST['plan_id'] ?? '');
        $options = '<option value="">انتخاب کنید</option>';
        foreach ($items as $p) {
            if (!is_array($p)) continue;
            $s = is_array($p['settings'] ?? null) ? $p['settings'] : [];
            $active = is_array($s['active_installments'] ?? null) ? implode(',', $s['active_installments']) : (string)($s['active_installments'] ?? '');
            $options .= '<option value="'.Renderer::e((string)$p['id']).'" data-type="'.Renderer::e((string)$p['type']).'" data-installments="'.Renderer::e($active).'" data-min-interval="'.Renderer::e((string)($s['min_interval'] ?? 1)).'" data-max-interval="'.Renderer::e((string)($s['max_interval'] ?? 1)).'" data-min-amount="'.Renderer::e((string)($s['min_amount'] ?? '')).'" data-max-amount="'.Renderer::e((string)($s['max_amount'] ?? '')).'"'.($selected === (string)$p['id'] ? ' selected' : '').'>'.Renderer::e((string)$p['name']).' · '.$this->typeLabel((string)$p['type']).'</option>';
        }
        $content = $this->head('محاسبه تسهیلات', 'طرح فعال، مبلغ و تعداد اقساط را وارد کنید. نرخ‌ها و کمیسیون‌ها فقط از تنظیمات مصوب طرح خوانده می‌شوند.')
            . ($error !== '' ? $this->alert('error', 'محاسبه انجام نشد', $error) : '')
            . '<section class="bn-section"><form method="post" data-calculator-form><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><div class="bn-grid bn-grid--2"><div class="bn-field"><label class="bn-label">طرح فعال <span class="bn-required">*</span></label><select name="plan_id" required data-calculator-plan>'.$options.'</select><span class="bn-helper" data-calculator-plan-hint>با انتخاب طرح، محدوده مبلغ و اقساط مجاز نمایش داده می‌شود.</span></div>'
            . $this->moneyField('مبلغ خرید / درخواست', 'amount', (string)($_POST['amount'] ?? ''), true)
            . $this->field('تعداد اقساط', 'installment_count', (string)($_POST['installment_count'] ?? ''), 'number', 'numeric', '', true)
            . $this->field('فاصله سررسیدها (ماه)', 'installment_interval', (string)($_POST['installment_interval'] ?? '1'), 'number', 'numeric', 'برای طرح بازاری مقدار ۱ است.', true, 'data-calculator-interval')
            . '</div><div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit">محاسبه</button></div></form></section>';
        if (is_array($result)) {
            $rows = '';
            foreach ($result as $key=>$value) $rows .= '<tr><td>'.Renderer::e($this->calcLabels()[(string)$key] ?? (string)$key).'</td><td>'.$this->formatCalculation((string)$key, $value).'</td></tr>';
            $content .= '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step"><i class="bn-icon bn-icon--check" aria-hidden="true"></i></span><div><h2 class="bn-sectiontitle">نتیجه محاسبه</h2><p class="bn-sectiondesc">مبالغ مالی به ریال و درصدها با علامت ٪ نمایش داده می‌شوند.</p></div></div><div class="bn-table-wrap"><table class="bn-table bn-table--keyvalue"><tbody>'.$rows.'</tbody></table></div></section>';
        }
        echo Renderer::page('محاسبه‌گر تسهیلات', $content, $this->identity, 'acceptor-calculator');
    }

    private function requests(): void
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $status = trim((string)($_GET['status'] ?? ''));
        $search = trim((string)($_GET['q'] ?? ''));
        $r = $this->client->get('/api/v1/requests', $this->bearer, ['page'=>$page,'per_page'=>20,'status'=>$status,'q'=>$search]);
        if (!$r->ok()) { $this->errorPage('پرونده‌های تسهیلات', $r); return; }
        $data = is_array($r->json['data'] ?? null) ? $r->json['data'] : [];
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $rows = '';
        foreach ($items as $x) {
            if (!is_array($x)) continue;
            $rows .= '<tr><td><a class="bn-table-link" href="/acceptor/requests/'.Renderer::e((string)$x['id']).'"><strong>'.Renderer::e((string)$x['request_no']).'</strong><small>'.Renderer::e((string)($x['full_name'] ?? 'نام ثبت نشده')).'</small></a></td><td>'.Renderer::e((string)($x['national_code'] ?? '—')).'</td><td>'.$this->money((float)($x['requested_amount'] ?? 0)).'</td><td>'.$this->status((string)$x['status'], $this->requestStatusLabel((string)$x['status'])).'</td><td>'.Renderer::e(PersianDateTime::fromIso((string)($x['updated_at'] ?? ''), false) ?? '—').'</td></tr>';
        }
        if ($rows === '') $rows = '<tr><td colspan="5"><div class="bn-empty-inline"><strong>پرونده‌ای پیدا نشد</strong><span>عبارت جستجو یا وضعیت انتخاب‌شده را تغییر دهید.</span></div></td></tr>';
        $statusOptions = '<option value="">همه وضعیت‌ها</option>';
        foreach ($this->requestLabels() as $code=>$label) $statusOptions .= $this->option($code, $label, $status);
        $content = $this->head('پرونده‌های تسهیلات', 'پرونده‌ها را بر اساس شماره، نام، کد ملی، موبایل یا وضعیت پیدا و پیگیری کنید.', '<a class="bn-btn bn-btn--primary" href="/acceptor/requests/new">ثبت پرونده جدید</a>')
            . $this->takeFlash()
            . '<section class="bn-section"><form class="bn-filterbar" method="get" action="/acceptor/requests"><div class="bn-field bn-filter-search"><label class="bn-label">جستجو</label><input type="search" name="q" value="'.Renderer::e($search).'" placeholder="شماره پرونده، نام، کد ملی یا موبایل"></div><div class="bn-field"><label class="bn-label">وضعیت</label><select name="status">'.$statusOptions.'</select></div><div class="bn-filter-actions"><button class="bn-btn bn-btn--primary" type="submit">جستجو</button><a class="bn-btn bn-btn--secondary" href="/acceptor/requests">پاک‌کردن</a></div></form></section>'
            . '<section class="bn-section"><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>پرونده</th><th>کد ملی</th><th>مبلغ درخواست</th><th>وضعیت</th><th>آخرین تغییر</th></tr></thead><tbody>'.$rows.'</tbody></table></div>'.$this->pagination('/acceptor/requests', (int)($data['page'] ?? $page), (int)($data['per_page'] ?? 20), (int)($data['total'] ?? count($items)), ['status'=>$status,'q'=>$search]).'</section>';
        echo Renderer::page('پرونده‌های تسهیلات', $content, $this->identity, 'acceptor-requests');
    }

    private function requestForm(string $error = ''): void
    {
        $operationKey=FormOperation::reuseOrIssue('acceptor-create:'.(string)($this->identity['user']['id']??''),$_POST['_operation_key']??null);
        $plans = $this->client->get('/api/v1/plans', $this->bearer, ['status'=>'active','per_page'=>100,'context'=>'merchant_request']);
        if (!$plans->ok()) { $this->errorPage('ثبت پرونده', $plans); return; }
        $items = is_array($plans->json['data']['items'] ?? null) ? $plans->json['data']['items'] : [];
        $options = '<option value="">انتخاب کنید</option>';
        foreach ($items as $p) if (is_array($p)) $options .= '<option value="'.Renderer::e((string)$p['id']).'"'.(((string)($_POST['plan_id'] ?? '')) === (string)$p['id'] ? ' selected' : '').'>'.Renderer::e((string)$p['name']).' · '.$this->typeLabel((string)$p['type']).'</option>';
        $v = static fn(string $key, string $default=''): string => (string)($_POST[$key] ?? $default);
        $content = $this->head('ثبت پرونده تسهیلات', 'اطلاعات متقاضی، طرح و سفارش را ثبت کنید. می‌توانید ابتدا پیش‌نویس ذخیره کنید یا پرونده را مستقیم برای بررسی ارسال کنید.')
            . ($error !== '' ? $this->alert('error', 'ثبت انجام نشد', $error) : '')
            . '<form method="post" action="/acceptor/requests/new" data-submit-lock="1" data-intake-wizard><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="_operation_key" value="'.Renderer::e($operationKey).'">'
            . '<nav class="bn-intake-nav" data-intake-nav hidden aria-label="مراحل ثبت پرونده"><button type="button" data-intake-jump="0">۱. اطلاعات متقاضی</button><button type="button" data-intake-jump="1">۲. طرح و سفارش</button><button type="button" data-intake-jump="2">۳. بررسی و ثبت</button></nav>'
            . '<section class="bn-section" data-intake-step="0"><div class="bn-sectionhead"><span class="bn-step">۱</span><div><h2 class="bn-sectiontitle">هویت متقاضی</h2><p class="bn-sectiondesc">برای ارسال پرونده، اطلاعات هویتی و تماس باید کامل و معتبر باشند.</p></div></div><div class="bn-grid bn-grid--2">'
            . $this->field('نام و نام خانوادگی','full_name',$v('full_name')).$this->field('نام پدر','father_name',$v('father_name')).$this->field('شماره شناسنامه','certificate_number',$v('certificate_number')).$this->field('کد ملی','national_code',$v('national_code'),'text','numeric','۱۰ رقم').$this->field('شماره موبایل','mobile_number',$v('mobile_number'),'text','tel','با ۰۹ شروع شود').$this->field('شماره تلفن ثابت','phone_number',$v('phone_number'),'text','tel').$this->birthDateField($this->buildBirthDateFromPost()).$this->occupationField($v('occupation')).$this->field('کد پستی','postal_code',$v('postal_code'),'text','numeric','۱۰ رقم').'</div><div class="bn-grid bn-grid--2">'.$this->area('آدرس محل سکونت','residential_address',$v('residential_address')).$this->area('آدرس محل فعالیت','work_address',$v('work_address')).'</div></section>'
            . '<section class="bn-section" data-intake-step="1"><div class="bn-sectionhead"><span class="bn-step">۲</span><div><h2 class="bn-sectiontitle">طرح و سفارش</h2><p class="bn-sectiondesc">مبلغ سفارش باید با مبلغ درخواست برابر باشد.</p></div></div><div class="bn-grid bn-grid--2"><div class="bn-field"><label class="bn-label">طرح تسهیلات <span class="bn-required">*</span></label><select name="plan_id" required>'.$options.'</select></div>'.$this->field('نام محصول / خدمت','product_name',$v('product_name')).$this->moneyField('مبلغ سفارش','amount',$v('amount'),true).$this->field('تعداد اقساط','installment_count',$v('installment_count'),'number','numeric','',true).$this->field('فاصله سررسیدها (ماه)','installment_interval',$v('installment_interval','1'),'number','numeric','',true).$this->field('اولین سررسید شمسی','first_due_date',$v('first_due_date'),'text','numeric','مثال: ۱۴۰۵/۰۷/۰۱',true).'</div></section>'
            . '<section class="bn-section" data-intake-step="2"><h2 class="bn-sectiontitle">بررسی و ثبت</h2><p class="bn-sectiondesc">پس از ثبت، از صفحه پرونده می‌توانید مدارک، ضامن و قرارداد را تکمیل و نتیجه بررسی را پیگیری کنید.</p><dl class="bn-intake-review" data-intake-review></dl><p class="bn-helper">محاسبه نهایی اقساط و کنترل شرایط طرح در سرور انجام می‌شود.</p></section>'
            . '<div class="bn-form-actions"><span class="bn-intake-controls" data-intake-controls hidden><button class="bn-btn bn-btn--secondary" type="button" data-intake-prev>مرحله قبل</button><button class="bn-btn bn-btn--primary" type="button" data-intake-next>ادامه</button></span><button class="bn-btn bn-btn--secondary" type="submit" name="_action" value="draft" formnovalidate>ذخیره پیش‌نویس</button><button class="bn-btn bn-btn--primary" type="submit" name="_action" value="submit">ثبت و ارسال برای بررسی</button><a class="bn-btn bn-btn--ghost" href="/acceptor/requests">انصراف</a></div></form>';
        echo Renderer::page('ثبت پرونده', $content, $this->identity, 'acceptor-new');
    }

    private function createRequest(): void
    {
        if (!Csrf::valid((string)($_POST['_csrf'] ?? ''))) { $this->requestForm('اعتبار فرم منقضی شده است.'); return; }
        $operationScope='acceptor-create:'.(string)($this->identity['user']['id']??'');
        $operationKey=$_POST['_operation_key']??null;
        if(!FormOperation::valid($operationScope,$operationKey)){$this->requestForm('اعتبار این فرم ثبت منقضی شده است؛ اطلاعات را بررسی و دوباره ارسال کنید.');return;}
        $amount = $this->numberish($_POST['amount'] ?? 0);
        $name = trim((string)($_POST['product_name'] ?? ''));
        $submit = (string)($_POST['_action'] ?? 'submit') !== 'draft';
        $payload = [
            'plan_id'=>(string)($_POST['plan_id'] ?? ''), 'submit'=>$submit,
            'full_name'=>trim((string)($_POST['full_name'] ?? '')), 'father_name'=>trim((string)($_POST['father_name'] ?? '')),
            'certificate_number'=>trim((string)($_POST['certificate_number'] ?? '')), 'national_code'=>trim((string)($_POST['national_code'] ?? '')),
            'mobile_number'=>trim((string)($_POST['mobile_number'] ?? '')), 'phone_number'=>trim((string)($_POST['phone_number'] ?? '')),
            'birth_date'=>$this->buildBirthDateFromPost(), 'occupation'=>trim((string)($_POST['occupation'] ?? '')), 
            'postal_code'=>trim((string)($_POST['postal_code'] ?? '')), 'residential_address'=>trim((string)($_POST['residential_address'] ?? '')),
            'work_address'=>trim((string)($_POST['work_address'] ?? '')), 'product_name'=>$name, 'merchant_ref'=>(string)($this->identity['user']['id']??''),
            'requested_amount'=>$amount, 'order_total'=>$amount,
            'order_items'=>[['name'=>$name,'qty'=>1,'unit_price'=>$amount,'total_price'=>$amount]],
            'installment_count'=>$this->integerish($_POST['installment_count'] ?? 0), 'installment_interval'=>max(1,$this->integerish($_POST['installment_interval'] ?? 1)),
            'first_due_date'=>trim((string)($_POST['first_due_date'] ?? '')),
        ];
        if(!FormOperation::bind($operationScope,$operationKey,$payload)){$this->requestForm('نتیجه ارسال قبلی هنوز قطعی نیست؛ همان اطلاعات را دوباره ارسال کنید یا ابتدا پرونده‌های خود را بررسی کنید.');return;}
        $r = $this->client->post('/api/v1/requests', $this->bearer, $payload, $operationKey);
        if (!$r->ok()) {
            if($r->status===422&&($r->json['error']['code']??null)==='validation_failed')FormOperation::complete($operationScope,$operationKey);
            $this->requestForm($this->apiMessage($r)); return;
        }
        $id = (string)($r->json['data']['request']['id'] ?? '');
        if(!preg_match('/^[a-f0-9]{26}$/D',$id)){$this->requestForm('پاسخ ثبت پرونده قابل تأیید نیست؛ ارسال مجدد با همان شناسه عملیات انجام می‌شود.');return;}
        FormOperation::complete($operationScope,$operationKey);
        $this->flash('success', $submit ? 'پرونده ثبت و برای بررسی ارسال شد.' : 'پیش‌نویس پرونده ذخیره شد.');
        header('Location: /acceptor/requests/'.$id, true, 303); exit;
    }

    private function requestEditForm(string $id, string $error = ''): void
    {
        $r = $this->client->get('/api/v1/requests/'.$id, $this->bearer);
        if (!$r->ok()) { $this->errorPage('ویرایش پرونده', $r); return; }
        $x = $r->json['data']['request'] ?? [];
        if (!is_array($x)) { $this->errorPage('ویرایش پرونده', new HttpResponse(500, null)); return; }
        if (!in_array((string)$x['status'], ['draft','need_correction'], true)) {
            $this->flash('error', 'ویرایش اطلاعات فقط برای پیش‌نویس یا پرونده دارای نقص مجاز است.');
            header('Location: /acceptor/requests/'.$id, true, 303); exit;
        }
        $v = static function(string $key, array $request): string {
            if (array_key_exists($key, $_POST)) return (string)$_POST[$key];
            if ($key === 'birth_date') return PersianDateTime::dateOnly((string)($request[$key] ?? '')) ?? '';
            return (string)($request[$key] ?? '');
        };
        $content = $this->head('ویرایش اطلاعات پرونده', 'اصلاح اطلاعات هویتی و تماس در وضعیت فعلی مجاز است. تغییر مبلغ و طرح نیازمند پرونده جدید است.')
            . ($error !== '' ? $this->alert('error','ذخیره انجام نشد',$error) : '')
            . '<form method="post" action="/acceptor/requests/'.$id.'/edit" data-submit-lock="1"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><section class="bn-section"><div class="bn-grid bn-grid--2">'
            . $this->field('نام و نام خانوادگی','full_name',$v('full_name',$x)).$this->field('نام پدر','father_name',$v('father_name',$x)).$this->field('شماره شناسنامه','certificate_number',$v('certificate_number',$x)).$this->field('کد ملی','national_code',$v('national_code',$x),'text','numeric').$this->field('شماره موبایل','mobile_number',$v('mobile_number',$x),'text','tel').$this->field('شماره تلفن ثابت','phone_number',$v('phone_number',$x),'text','tel').$this->birthDateField($v('birth_date',$x)).$this->occupationField($v('occupation',$x)).$this->field('کد پستی','postal_code',$v('postal_code',$x),'text','numeric').$this->field('نام محصول / خدمت','product_name',$v('product_name',$x)).'</div><div class="bn-grid bn-grid--2">'.$this->area('آدرس محل سکونت','residential_address',$v('residential_address',$x)).$this->area('آدرس محل فعالیت','work_address',$v('work_address',$x)).'</div></section><div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit">ذخیره تغییرات</button><a class="bn-btn bn-btn--secondary" href="/acceptor/requests/'.$id.'">انصراف</a></div></form>';
        echo Renderer::page('ویرایش پرونده', $content, $this->identity, 'acceptor-requests');
    }

    private function editRequest(string $id): void
    {
        if (!Csrf::valid((string)($_POST['_csrf'] ?? ''))) { $this->requestEditForm($id, 'اعتبار فرم منقضی شده است.'); return; }
        $fields = [];
        foreach (['full_name','father_name','certificate_number','national_code','mobile_number','phone_number','occupation','residential_address','postal_code','work_address','product_name'] as $key) $fields[$key] = trim((string)($_POST[$key] ?? '')); $fields['birth_date'] = $this->buildBirthDateFromPost();
        $r = $this->client->patch('/api/v1/requests/'.$id, $this->bearer, $fields);
        if (!$r->ok()) { $this->requestEditForm($id, $this->apiMessage($r)); return; }
        $this->flash('success', 'اطلاعات پرونده با موفقیت به‌روزرسانی شد.');
        header('Location: /acceptor/requests/'.$id, true, 303); exit;
    }

    private function requestDetail(string $id): void
    {
        $r = $this->client->get('/api/v1/requests/'.$id, $this->bearer);
        if (!$r->ok()) { $this->errorPage('جزئیات پرونده', $r); return; }
        $data = is_array($r->json['data'] ?? null) ? $r->json['data'] : [];
        $x = is_array($data['request'] ?? null) ? $data['request'] : [];
        $allowed = is_array($data['allowed_transitions'] ?? null) ? $data['allowed_transitions'] : [];
        $scheduleResponse = $this->client->get('/api/v1/requests/'.$id.'/schedule', $this->bearer);
        $schedule = $scheduleResponse->ok() && is_array($scheduleResponse->json['data']['schedule'] ?? null) ? $scheduleResponse->json['data']['schedule'] : [];
        $historyResponse = $this->client->get('/api/v1/requests/'.$id.'/history', $this->bearer);
        $history = $historyResponse->ok() && is_array($historyResponse->json['data']['history'] ?? null) ? $historyResponse->json['data']['history'] : [];
        $events = $historyResponse->ok() && is_array($historyResponse->json['data']['events'] ?? null) ? $historyResponse->json['data']['events'] : [];
        $guarantorResponse = $this->client->get('/api/v1/requests/'.$id.'/guarantors', $this->bearer);
        $guarantors = $guarantorResponse->ok() && is_array($guarantorResponse->json['data']['guarantors'] ?? null) ? $guarantorResponse->json['data']['guarantors'] : [];
        $messagesResponse = $this->client->get('/api/v1/requests/'.$id.'/messages', $this->bearer);
        $messages = $messagesResponse->ok() && is_array($messagesResponse->json['data']['items'] ?? null) ? $messagesResponse->json['data']['items'] : [];
        $editable = in_array((string)($x['status'] ?? ''), ['draft','need_correction'], true);
        $submitAction=$editable?'<form method="post" action="/acceptor/requests/'.$id.'/submit" class="bn-inline-form" data-submit-lock="1"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><button class="bn-btn bn-btn--primary" type="submit">'.((string)($x['status']??'')==='need_correction'?'ارسال مجدد برای بررسی':'ارسال برای بررسی').'</button></form>':'';
        $actions = '<a class="bn-btn bn-btn--secondary" href="/loans/requests/'.$id.'/documents">مدارک پرونده</a>'.($editable ? '<a class="bn-btn bn-btn--secondary" href="/acceptor/requests/'.$id.'/edit">ویرایش اطلاعات</a>' : '').$submitAction;
        $content = $this->head('پرونده '.(string)($x['request_no'] ?? ''), (string)($x['full_name'] ?? 'متقاضی').' · '.$this->requestStatusLabel((string)($x['status'] ?? '')), $actions)
            . $this->takeFlash()
            . CaseJourney::render($this->client,$this->bearer,$id,$this->identity)
            . '<div class="bn-stats">'.$this->stat('مبلغ درخواست',$this->money((float)($x['requested_amount'] ?? 0)),'ریال').$this->stat('مبلغ تسهیلات',$this->money((float)($x['facility_amount'] ?? 0)),'ریال').$this->stat('مبلغ هر قسط',$this->money((float)($x['installment_amount'] ?? 0)),'ریال').$this->stat('وضعیت',$this->requestStatusLabel((string)($x['status'] ?? '')),'مرحله جاری',true).'</div>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۱</span><div><h2 class="bn-sectiontitle">اطلاعات پرونده</h2><p class="bn-sectiondesc">طرح '.Renderer::e((string)($x['plan_name'] ?? '—')).' · نسخه '.PersianDateTime::digits((string)($x['plan_version'] ?? '—')).'</p></div></div><div class="bn-detail-grid">'
            . $this->detail('کد ملی',(string)($x['national_code'] ?? '—')).$this->detail('موبایل',(string)($x['mobile_number'] ?? '—')).$this->detail('نام پدر',(string)($x['father_name'] ?? '—')).$this->detail('کد پستی',(string)($x['postal_code'] ?? '—')).$this->detail('تاریخ تولد',PersianDateTime::dateOnly((string)($x['birth_date'] ?? '')) ?? '—').$this->detail('محصول / خدمت',(string)($x['product_name'] ?? '—')).$this->detail('پیش‌پرداخت',$this->money((float)($x['prepayment'] ?? 0))).$this->detail('جمع بازپرداخت',$this->money((float)($x['total_payment'] ?? 0))).$this->detail('سود',$this->money((float)($x['profit'] ?? 0))).$this->detail('پرداخت پذیرنده',$this->money((float)($x['acceptor_payment_amount'] ?? 0))).'</div></section>'
            . $this->scheduleSection($schedule)
            . $this->guarantorsSection($id, $guarantors)
            . $this->messagesSection($id, $messages)
            . $this->historySection($history)
            . $this->eventsSection($events);
        echo Renderer::page('جزئیات پرونده', $content, $this->identity, 'acceptor-requests');
    }

    private function scheduleSection(array $schedule): string
    {
        $rows = '';
        foreach ($schedule as $row) {
            if (!is_array($row)) continue;
            $rows .= '<tr><td>'.PersianDateTime::digits((string)($row['sequence_no'] ?? '')).'</td><td>'.Renderer::e(PersianDateTime::dateOnly((string)($row['due_date'] ?? '')) ?? '—').'</td><td>'.$this->money((float)($row['amount'] ?? 0)).'</td><td>'.$this->money((float)($row['principal_amount'] ?? 0)).'</td><td>'.$this->money((float)($row['profit_amount'] ?? 0)).'</td></tr>';
        }
        if ($rows === '') $rows = '<tr><td colspan="5"><div class="bn-empty-inline">زمان‌بندی اقساط برای این پرونده ثبت نشده است.</div></td></tr>';
        return '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۲</span><div><h2 class="bn-sectiontitle">زمان‌بندی اقساط</h2><p class="bn-sectiondesc">سررسیدها به تاریخ شمسی نمایش داده می‌شوند.</p></div></div><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>قسط</th><th>سررسید</th><th>مبلغ</th><th>اصل</th><th>سود</th></tr></thead><tbody>'.$rows.'</tbody></table></div></section>';
    }

    private function guarantorsSection(string $id, array $guarantors): string
    {
        $rows = [];
        foreach ($guarantors as $g) if (is_array($g)) $rows[] = $g;
        while (count($rows) < max(2, min(4, count($rows)+1))) $rows[] = [];
        $fields = '';
        foreach ($rows as $i=>$g) {
            $fields .= '<div class="bn-guarantor-row" data-guarantor-row><input type="hidden" name="guarantors['.$i.'][id]" value="'.Renderer::e((string)($g['id']??'')).'"><div class="bn-guarantor-head"><strong>ضامن '.PersianDateTime::digits((string)($i+1)).'</strong><button class="bn-btn bn-btn--ghost bn-btn--small" type="button" data-remove-guarantor>حذف</button></div><div class="bn-grid bn-grid--2">'
                . $this->field('نام و نام خانوادگی', 'guarantors['.$i.'][full_name]', (string)($g['full_name'] ?? ''))
                . $this->field('کد ملی', 'guarantors['.$i.'][national_code]', (string)($g['national_code'] ?? ''), 'text', 'numeric')
                . $this->field('شماره موبایل', 'guarantors['.$i.'][mobile_number]', (string)($g['mobile_number'] ?? ''), 'text', 'tel')
                . $this->field('نسبت / توضیح', 'guarantors['.$i.'][relation_label]', (string)($g['relation_label'] ?? ''))
                . '</div></div>';
        }
        return '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۳</span><div><h2 class="bn-sectiontitle">ضامن‌ها</h2><p class="bn-sectiondesc">ردیف خالی ذخیره نمی‌شود. کد ملی و موبایل در صورت ورود اعتبارسنجی می‌شوند.</p></div></div><form method="post" action="/acceptor/requests/'.$id.'/guarantors" data-guarantors-form><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><div class="bn-guarantors" data-guarantors-container>'.$fields.'</div><template data-guarantor-template><div class="bn-guarantor-row" data-guarantor-row><div class="bn-guarantor-head"><strong>ضامن</strong><button class="bn-btn bn-btn--ghost bn-btn--small" type="button" data-remove-guarantor>حذف</button></div><div class="bn-grid bn-grid--2"><div class="bn-field"><label class="bn-label">نام و نام خانوادگی</label><input data-name="full_name"></div><div class="bn-field"><label class="bn-label">کد ملی</label><input inputmode="numeric" data-name="national_code"></div><div class="bn-field"><label class="bn-label">شماره موبایل</label><input inputmode="tel" data-name="mobile_number"></div><div class="bn-field"><label class="bn-label">نسبت / توضیح</label><input data-name="relation_label"></div></div></div></template><div class="bn-form-toolbar"><button type="button" class="bn-btn bn-btn--secondary bn-btn--small" data-add-guarantor>افزودن ضامن</button><button type="submit" class="bn-btn bn-btn--primary">ذخیره ضامن‌ها</button></div></form></section>';
    }

    private function saveGuarantors(string $id): void
    {
        if (!Csrf::valid((string)($_POST['_csrf'] ?? ''))) { $this->flash('error','اعتبار فرم منقضی شده است.'); header('Location: /acceptor/requests/'.$id, true, 303); exit; }
        $items = is_array($_POST['guarantors'] ?? null) ? $_POST['guarantors'] : [];
        $r = $this->client->put('/api/v1/requests/'.$id.'/guarantors', $this->bearer, ['guarantors'=>$items]);
        $this->flash($r->ok() ? 'success' : 'error', $r->ok() ? 'اطلاعات ضامن‌ها با موفقیت ذخیره شد.' : $this->apiMessage($r));
        header('Location: /acceptor/requests/'.$id, true, 303); exit;
    }

    private function messagesSection(string $id,array $messages): string
    {
        $items='';foreach($messages as$message){if(!is_array($message))continue;$who=(string)($message['sender_role']??'کاربر');$body=(string)($message['message']??'');$at=PersianDateTime::fromIso((string)($message['created_at']??''),true)??'—';$items.='<div class="bn-event-item"><span class="bn-event-dot"></span><div><strong>'.Renderer::e($who).'</strong><p>'.nl2br(Renderer::e($body)).'</p><small>'.Renderer::e($at).'</small></div></div>';}
        if($items==='')$items='<div class="bn-empty-inline">هنوز پیامی برای این پرونده ثبت نشده است.</div>';
        return '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">پیام</span><div><h2 class="bn-sectiontitle">پیام‌های پرونده</h2><p class="bn-sectiondesc">پیام‌های قابل مشاهده برای طرفین پرونده در این بخش ثبت می‌شوند.</p></div></div><div class="bn-event-list">'.$items.'</div><form method="post" action="/acceptor/requests/'.$id.'/messages" data-submit-lock="1"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><div class="bn-field"><label class="bn-label">پیام جدید</label><textarea name="message" rows="3" maxlength="5000" required></textarea></div><div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit">ارسال پیام</button></div></form></section>';
    }

    private function sendMessage(string $id): void
    {
        if(!Csrf::valid((string)($_POST['_csrf']??''))){$this->flash('error','اعتبار فرم منقضی شده است.');header('Location: /acceptor/requests/'.$id,true,303);exit;}
        $message=trim((string)($_POST['message']??''));if($message===''){$this->flash('error','متن پیام نمی‌تواند خالی باشد.');header('Location: /acceptor/requests/'.$id,true,303);exit;}
        $r=$this->client->post('/api/v1/requests/'.$id.'/messages',$this->bearer,['message'=>$message,'visibility'=>'participants']);$this->flash($r->ok()?'success':'error',$r->ok()?'پیام با موفقیت ارسال شد.':$this->apiMessage($r));header('Location: /acceptor/requests/'.$id,true,303);exit;
    }

    private function submitRequest(string $id): void
    {
        if(!Csrf::valid((string)($_POST['_csrf']??''))){$this->flash('error','اعتبار فرم منقضی شده است.');header('Location: /acceptor/requests/'.$id,true,303);exit;}
        $r=$this->client->post('/api/v1/requests/'.$id.'/submit',$this->bearer,[]);
        $this->flash($r->ok()?'success':'error',$r->ok()?'پرونده برای بررسی ارسال شد.':$this->apiMessage($r));header('Location: /acceptor/requests/'.$id,true,303);exit;
    }

    private function historySection(array $history): string
    {
        $rows = '';
        foreach ($history as $row) {
            if (!is_array($row)) continue;
            $from = trim((string)($row['from_status'] ?? ''));
            $rows .= '<tr><td>'.($from !== '' ? Renderer::e($this->requestStatusLabel($from)).' <i class="bn-icon bn-icon--arrow-left" aria-hidden="true"></i> ' : '').Renderer::e($this->requestStatusLabel((string)($row['to_status'] ?? ''))).'</td><td>'.Renderer::e(trim((string)($row['reason'] ?? '')) ?: '—').'</td><td>'.Renderer::e(PersianDateTime::fromIso((string)($row['created_at'] ?? ''), false) ?? '—').'</td></tr>';
        }
        if ($rows === '') $rows = '<tr><td colspan="3"><div class="bn-empty-inline">تاریخچه‌ای ثبت نشده است.</div></td></tr>';
        return '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۴</span><div><h2 class="bn-sectiontitle">تاریخچه وضعیت</h2><p class="bn-sectiondesc">تمام تغییرات وضعیت با زمان و توضیح ثبت می‌شوند.</p></div></div><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>تغییر</th><th>توضیح</th><th>زمان</th></tr></thead><tbody>'.$rows.'</tbody></table></div></section>';
    }

    private function eventsSection(array $events): string
    {
        $items = '';
        foreach (array_slice($events, 0, 20) as $event) {
            if (!is_array($event)) continue;
            $type = (string)($event['event_type'] ?? '');
            $result = (string)($event['result'] ?? '');
            $items .= '<li class="bn-event-item"><span class="bn-status '.($result === 'denied' ? 'bn-status--bad' : 'bn-status--neutral').'">'.($result === 'denied' ? 'ردشده' : 'ثبت‌شده').'</span><div><strong>'.Renderer::e($this->eventLabel($type)).'</strong><small>'.Renderer::e(PersianDateTime::fromIso((string)($event['created_at'] ?? ''), false) ?? '—').'</small></div></li>';
        }
        if ($items === '') $items = '<li class="bn-empty-inline">رویداد عملیاتی برای این پرونده ثبت نشده است.</li>';
        return '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۵</span><div><h2 class="bn-sectiontitle">رویدادهای ثبت‌شده</h2><p class="bn-sectiondesc">خلاصه آخرین عملیات و تلاش‌های مجاز یا ردشده روی پرونده.</p></div></div><ul class="bn-event-list">'.$items.'</ul></section>';
    }

    private function transitionSection(string $id, array $allowed, string $assignedBankUser): string
    {
        if ($allowed === []) return '<section class="bn-section"><div class="bn-empty-inline"><strong>اقدام بعدی در دسترس نیست</strong><span>این پرونده در وضعیت فعلی انتقال مجاز دیگری ندارد.</span></div></section>';
        $options = '<option value="">انتخاب کنید</option>';
        foreach ($allowed as $a) {
            if (!is_array($a)) continue;
            $code = (string)($a['code'] ?? '');
            $options .= '<option value="'.Renderer::e($code).'" data-requires-reason="'.(!empty($a['requires_reason']) ? '1' : '0').'" data-needs-bank="'.($code === 'expert_approved' ? '1' : '0').'">'.Renderer::e((string)($a['label'] ?? $code)).'</option>';
        }
        return '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۶</span><div><h2 class="bn-sectiontitle">اقدام بعدی پرونده</h2><p class="bn-sectiondesc">فقط انتقال‌های مجاز نمایش داده می‌شوند. برای رد، لغو یا اعلام نقص، ثبت دلیل الزامی است.</p></div></div><form method="post" action="/acceptor/requests/'.$id.'/transition" data-transition-form><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><div class="bn-grid bn-grid--2"><div class="bn-field"><label class="bn-label">وضعیت بعدی <span class="bn-required">*</span></label><select name="status" required data-transition-status>'.$options.'</select></div><div class="bn-field" data-bank-user-field hidden><label class="bn-label">شناسه کارشناس بانک <span class="bn-required">*</span></label><input name="assigned_bank_user_id" value="'.Renderer::e($assignedBankUser).'" autocomplete="off"><span class="bn-helper">برای تأیید کارشناس باید این شناسه مشخص باشد.</span></div><div class="bn-field bn-grid-span-2"><label class="bn-label">توضیح تغییر وضعیت <span class="bn-required" data-reason-required hidden>*</span></label><textarea name="reason" rows="3" data-transition-reason placeholder="توضیح کوتاه و قابل پیگیری ثبت کنید"></textarea></div></div><div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit">ثبت تغییر وضعیت</button></div></form></section>';
    }

    private function transition(string $id): void
    {
        if (!Csrf::valid((string)($_POST['_csrf'] ?? ''))) { $this->flash('error','اعتبار فرم منقضی شده است.'); header('Location: /acceptor/requests/'.$id, true, 303); exit; }
        $payload = ['status'=>(string)($_POST['status'] ?? ''), 'reason'=>trim((string)($_POST['reason'] ?? ''))];
        if (array_key_exists('assigned_bank_user_id', $_POST)) $payload['assigned_bank_user_id'] = trim((string)$_POST['assigned_bank_user_id']);
        $r = $this->client->post('/api/v1/requests/'.$id.'/transition', $this->bearer, $payload);
        $this->flash($r->ok() ? 'success' : 'error', $r->ok() ? 'وضعیت پرونده با موفقیت به‌روزرسانی شد.' : $this->apiMessage($r));
        header('Location: /acceptor/requests/'.$id, true, 303); exit;
    }

    private function head(string $title, string $subtitle, string $actions=''): string
    { return '<div class="bn-pagehead"><div class="bn-pagehead-main"><div class="bn-eyebrow">پنل پذیرنده</div><h1 class="bn-title">'.Renderer::e($title).'</h1><p class="bn-subtitle">'.Renderer::e($subtitle).'</p></div>'.($actions !== '' ? '<div class="bn-actions">'.$actions.'</div>' : '').'</div>'; }

    private function actionCard(string $href, string $title, string $description): string
    { return '<a class="bn-action-card" href="'.$href.'"><strong>'.Renderer::e($title).'</strong><span>'.Renderer::e($description).'</span></a>'; }

    private function stat(string $label, mixed $value, string $caption, bool $accent=false): string
    { return '<div class="bn-stat'.($accent ? ' bn-stat--accent' : '').'"><div class="bn-stat-label">'.Renderer::e($label).'</div><div class="bn-stat-value">'.Renderer::e(PersianDateTime::digits((string)$value)).'</div><div class="bn-stat-caption">'.Renderer::e($caption).'</div></div>'; }

    private function field(string $label, string $name, string $value, string $type='text', string $inputmode='text', string $helper='', bool $required=false, string $extra=''): string
    {
        $step = $type === 'number' ? ' step="0.01"' : '';
        return '<div class="bn-field"><label class="bn-label">'.Renderer::e($label).($required ? ' <span class="bn-required">*</span>' : '').'</label><input name="'.Renderer::e($name).'" type="'.$type.'" inputmode="'.$inputmode.'"'.$step.($required ? ' required' : '').($extra !== '' ? ' '.$extra : '').' value="'.Renderer::e($value).'">'.($helper !== '' ? '<span class="bn-helper">'.Renderer::e($helper).'</span>' : '').'</div>';
    }

    private function moneyField(string $label, string $name, string $value, bool $required=false): string
    { return $this->field($label.' (ریال)', $name, $value, 'text', 'numeric', 'مبلغ را به ریال وارد کنید.', $required, 'data-money-input'); }

    private function percentField(string $label, string $name, string $value, bool $required=true, string $helper=''): string
    { return $this->field($label.' (%)', $name, $value, 'number', 'decimal', $helper, $required); }

    private function area(string $label, string $name, string $value): string
    { return '<div class="bn-field"><label class="bn-label">'.Renderer::e($label).'</label><textarea name="'.Renderer::e($name).'" rows="4">'.Renderer::e($value).'</textarea></div>'; }

    private function detail(string $label, string $value): string
    { return '<div class="bn-detail-item"><span>'.Renderer::e($label).'</span><strong>'.Renderer::e(PersianDateTime::digits($value)).'</strong></div>'; }

    private function option(string $value, string $label, string $selected): string
    { return '<option value="'.Renderer::e($value).'"'.($value === $selected ? ' selected' : '').'>'.Renderer::e($label).'</option>'; }


    private function occupationField(string $value): string
    {
        return \Babanoell\Panel\Loans\OccupationField::render($value);
    }

    private function birthDateField(string $value): string
    {
        if(array_key_exists('birth_year',$_POST)||array_key_exists('birth_date',$_POST))$value=$this->buildBirthDateFromPost();
        [$year,$month,$day] = $this->splitBirthDate($value);
        $yearOptions = '<option value="">سال</option>';
        for ($y = 1415; $y >= 1300; $y--) {
            $yy = (string)$y;
            $yearOptions .= '<option value="'.$yy.'"'.($yy === $year ? ' selected' : '').'>'.Renderer::e(PersianDateTime::digits($yy)).'</option>';
        }
        $monthOptions = '<option value="">ماه</option>';
        foreach ($this->jalaliMonths() as $number => $label) {
            $monthOptions .= '<option value="'.Renderer::e((string)$number).'"'.((string)$number === $month ? ' selected' : '').'>'.Renderer::e($label).'</option>';
        }
        $dayOptions = '<option value="">روز</option>';
        for ($d = 1; $d <= 31; $d++) {
            $dd = (string)$d;
            $dayOptions .= '<option value="'.$dd.'"'.($dd === $day ? ' selected' : '').'>'.Renderer::e(PersianDateTime::digits($dd)).'</option>';
        }
        return '<div class="bn-field bn-field--birth"><label class="bn-label">تاریخ تولد شمسی <span class="bn-required">*</span></label><div class="bn-select-grid">'
            . '<div class="bn-select-cell"><select aria-label="روز تولد" name="birth_day" required>'.$dayOptions.'</select></div>'
            . '<div class="bn-select-cell"><select aria-label="ماه تولد" name="birth_month" required>'.$monthOptions.'</select></div>'
            . '<div class="bn-select-cell"><select aria-label="سال تولد" name="birth_year" required>'.$yearOptions.'</select></div>'
            . '</div><span class="bn-helper">روز، ماه و سال تولد را جداگانه انتخاب کنید.</span></div>';
    }

    /** @return array{0:string,1:string,2:string} */
    private function splitBirthDate(string $value): array
    {
        $plain = strtr(trim($value), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
        if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $plain, $m) === 1) {
            return [ltrim($m[1], '0') === '' ? '0' : (string)((int)$m[1]), (string)((int)$m[2]), (string)((int)$m[3])];
        }
        return ['','',''];
    }

    private function buildBirthDateFromPost(): string
    {
        $year = $this->integerish($_POST['birth_year'] ?? '');
        $month = $this->integerish($_POST['birth_month'] ?? '');
        $day = $this->integerish($_POST['birth_day'] ?? '');
        if ($year > 0 && $month > 0 && $day > 0) {
            return sprintf('%04d/%02d/%02d', $year, $month, $day);
        }
        return trim((string)($_POST['birth_date'] ?? ''));
    }


    /** @return array<int,string> */
    private function jalaliMonths(): array
    {
        return [1=>'فروردین',2=>'اردیبهشت',3=>'خرداد',4=>'تیر',5=>'مرداد',6=>'شهریور',7=>'مهر',8=>'آبان',9=>'آذر',10=>'دی',11=>'بهمن',12=>'اسفند'];
    }

    /** @return array<string,mixed> */
    private function acceptorDashboardData(): array
    {
        return DashboardSummary::load($this->client, $this->bearer) + ['general_notifications'=>[
            'اطلاعات پرونده‌ها پس از ثبت، در همان داشبورد قابل پیگیری است.',
            'در صورت نقص مدارک، وضعیت پرونده از بخش پرونده‌های من اطلاع‌رسانی می‌شود.',
            'برای پاسخ‌گویی سریع‌تر، از چت آنلاین پایین صفحه استفاده کنید.'
        ]];
    }

    private function merchantGrade(float $totalSales): string
    {
        if ($totalSales >= 5000000000) return 'A+';
        if ($totalSales >= 2000000000) return 'A';
        if ($totalSales >= 1000000000) return 'B';
        if ($totalSales >= 500000000) return 'C';
        return 'D';
    }

    /** @param list<string> $caseNotifications @param list<string> $generalNotifications */
    private function notificationCards(array $caseNotifications, array $generalNotifications): string
    {
        $caseItems = '';
        foreach ($caseNotifications as $message) {
            $caseItems .= '<li>'.Renderer::e($message).'</li>';
        }
        $generalItems = '';
        foreach ($generalNotifications as $message) {
            $generalItems .= '<li>'.Renderer::e($message).'</li>';
        }
        return '<div class="bn-notify-strip">'
            . '<details class="bn-notify-card" open><summary><span class="bn-notify-icon"><i class="bn-icon bn-icon--bell" aria-hidden="true"></i></span><span>اعلانات پرونده</span><strong>'.Renderer::e(PersianDateTime::digits((string)count($caseNotifications))).'</strong></summary><div class="bn-notify-body"><ul>'.$caseItems.'</ul></div></details>'
            . '<details class="bn-notify-card"><summary><span class="bn-notify-icon"><i class="bn-icon bn-icon--bullhorn" aria-hidden="true"></i></span><span>اعلانات عمومی</span><strong>'.Renderer::e(PersianDateTime::digits((string)count($generalNotifications))).'</strong></summary><div class="bn-notify-body"><ul>'.$generalItems.'</ul></div></details>'
            . '</div>';
    }

    private function pagination(string $path, int $page, int $perPage, int $total, array $params=[]): string
    {
        $pages = max(1, (int)ceil($total / max(1,$perPage)));
        if ($pages <= 1) return '<div class="bn-pagination-meta">'.PersianDateTime::digits((string)$total).' مورد</div>';
        $page = max(1, min($pages, $page));
        $link = function(int $target) use ($path,$params): string { $q = array_filter($params, static fn($v)=>$v !== '' && $v !== null); $q['page'] = $target; return $path.'?'.http_build_query($q); };
        return '<nav class="bn-pagination" aria-label="صفحه‌بندی"><a class="bn-btn bn-btn--secondary bn-btn--small'.($page <= 1 ? ' is-disabled' : '').'"'.($page > 1 ? ' href="'.Renderer::e($link($page-1)).'"' : ' aria-disabled="true"').'>قبلی</a><span>صفحه '.PersianDateTime::digits((string)$page).' از '.PersianDateTime::digits((string)$pages).' · '.PersianDateTime::digits((string)$total).' مورد</span><a class="bn-btn bn-btn--secondary bn-btn--small'.($page >= $pages ? ' is-disabled' : '').'"'.($page < $pages ? ' href="'.Renderer::e($link($page+1)).'"' : ' aria-disabled="true"').'>بعدی</a></nav>';
    }

    private function numberish(mixed $value): float
    {
        if (is_numeric($value)) return (float)$value;
        $x = strtr((string)$value, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
        $x = str_replace([',','٬',' '], '', $x);
        return is_numeric($x) ? (float)$x : 0.0;
    }

    private function integerish(mixed $value): int
    {
        if (is_int($value)) return $value;
        $x = strtr(trim((string)$value), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
        $x = str_replace([',','٬',' '], '', $x);
        return preg_match('/^-?\d+$/', $x) === 1 ? (int)$x : 0;
    }

    private function money(float $value): string
    { return PersianDateTime::digits(number_format($value, 0, '.', ',')); }

    private function status(string $code, string $label): string
    {
        $ok = in_array($code, ['active','granted','docs_verified'], true);
        $bad = in_array($code, ['rejected','inactive','cancelled'], true);
        $neutral = in_array($code, ['draft','archived'], true);
        return '<span class="bn-status '.($ok ? 'bn-status--ok' : ($bad ? 'bn-status--bad' : ($neutral ? 'bn-status--neutral' : 'bn-status--warn'))).'">'.Renderer::e($label).'</span>';
    }

    private function alert(string $type, string $title, string $message): string
    { return '<div class="bn-alert bn-alert--'.Renderer::e($type).'" role="alert"><strong>'.Renderer::e($title).'</strong><br>'.Renderer::e($message).'</div>'; }

    private function flash(string $type, string $message): void
    { $_SESSION['panel_loans_flash'] = ['type'=>$type, 'message'=>$message]; }

    private function takeFlash(): string
    {
        $flash = $_SESSION['panel_loans_flash'] ?? null;
        unset($_SESSION['panel_loans_flash']);
        if (!is_array($flash) || !is_string($flash['message'] ?? null)) return '';
        $type = ($flash['type'] ?? '') === 'success' ? 'success' : 'error';
        return $this->alert($type, $type === 'success' ? 'عملیات موفق بود' : 'عملیات انجام نشد', $flash['message']);
    }

    private function typeLabel(string $type): string { return $type === 'leasing' ? 'لیزینگی' : 'بازاری'; }
    private function planStatusLabel(string $status): string { return ['draft'=>'پیش‌نویس','active'=>'فعال','inactive'=>'غیرفعال','archived'=>'بایگانی'][$status] ?? $status; }
    /** @return array<string,string> */
    private function requestLabels(): array { return ['draft'=>'پیش‌نویس','submitted'=>'ثبت‌شده','under_review'=>'در حال بررسی','need_correction'=>'نقص مدارک','rejected'=>'ردشده','expert_approved'=>'تأیید کارشناس','docs_completion'=>'تکمیل مدارک','docs_verified'=>'تأیید مدارک','granted'=>'اعطا شده','cancelled'=>'لغوشده','archived'=>'بایگانی']; }
    private function requestStatusLabel(string $status): string { return $this->requestLabels()[$status] ?? $status; }

    /** @return array<string,string> */
    private function requiredDocumentLabels(): array
    {
        return [
            'national_card'=>'کارت ملی','birth_certificate'=>'شناسنامه','portrait'=>'تصویر چهره','employment_certificate'=>'گواهی اشتغال','business_license'=>'جواز کسب','payslip'=>'فیش حقوقی','bank_statement'=>'گردش حساب','tax_return'=>'اظهارنامه مالیاتی','primary_check'=>'چک اصلی','secondary_check'=>'چک تکمیلی','guarantor_identity'=>'مدارک هویتی ضامن','guarantor_income'=>'مدارک درآمدی ضامن','credit_report'=>'گزارش اعتبارسنجی','contract'=>'قرارداد','invoice'=>'فاکتور / پیش‌فاکتور','supplemental'=>'مدارک تکمیلی','case_receipt'=>'رسید پرونده','settlement_receipt'=>'رسید تسویه',
        ];
    }

    private function settingLabels(): array
    {
        return [
            'min_amount'=>'حداقل مبلغ','max_amount'=>'حداکثر مبلغ','fixed_monthly_rate'=>'نرخ ثابت ماهانه','prepayment_percent'=>'درصد پیش‌پرداخت',
            'supplier_costs_percent'=>'هزینه تأمین‌کننده','intermediary_deductions_percent'=>'کسورات واسطه','babanovel_deductions_percent'=>'کسورات بابانوئل',
            'insurance_percent'=>'بیمه','guarantee_check_percent'=>'درصد چک ضمانت','acceptor_commission_percent'=>'کمیسیون پذیرنده',
            'additional_deductions_percent'=>'کسورات تکمیلی','acceptor_deductions_amount'=>'کسورات ثابت پذیرنده','vat_percent'=>'مالیات بر ارزش افزوده',
            'active_installments'=>'اقساط فعال','interest_rate'=>'نرخ سود اسمی','effective_interest_rate'=>'نرخ سود مؤثر','credit_share_percent'=>'سهم اعتباری',
            'min_interval'=>'حداقل فاصله اقساط','max_interval'=>'حداکثر فاصله اقساط','other_deductions_amount'=>'سایر کسورات ثابت','requires_bank_validation'=>'نیاز به اعتبارسنجی بانک',
        ];
    }

    private function formatSetting(string $key, mixed $value): string
    {
        if ($key === 'requires_bank_validation') return !empty($value) ? 'بله' : 'خیر';
        if ($key === 'active_installments') {
            $items = is_array($value) ? $value : (preg_split('/\s*,\s*/', (string)$value) ?: []);
            return Renderer::e(PersianDateTime::digits(implode('، ', array_map('strval', $items))).' قسط');
        }
        if (in_array($key, ['min_amount','max_amount','acceptor_deductions_amount','other_deductions_amount'], true)) return $this->money((float)$value).' ریال';
        if (str_ends_with($key, '_percent') || in_array($key, ['fixed_monthly_rate','interest_rate','effective_interest_rate'], true)) return Renderer::e(PersianDateTime::digits($this->trimNumber((float)$value)).'٪');
        if (in_array($key, ['min_interval','max_interval'], true)) return Renderer::e(PersianDateTime::digits((string)$value).' ماه');
        return Renderer::e(PersianDateTime::digits(is_array($value) ? implode('، ', $value) : (string)$value));
    }

    /** @return array<string,string> */
    private function calcLabels(): array
    {
        return ['loanAmount'=>'مبلغ تسهیلات','installmentCount'=>'تعداد اقساط انتخابی','numberOfInstallments'=>'تعداد سررسیدها','prepayment'=>'پیش‌پرداخت','totalPayment'=>'جمع بازپرداخت','installmentAmount'=>'مبلغ هر قسط','profit'=>'سود کل','operationCost'=>'هزینه عملیات','operationCostPercent'=>'درصد هزینه عملیات','operationCostWithVat'=>'هزینه عملیات با مالیات','walletCharge'=>'شارژ کیف پول','netLeasingPayment'=>'خالص پرداخت لیزینگ','acceptorCommission'=>'کمیسیون پذیرنده','acceptorCommissionWithVat'=>'کمیسیون پذیرنده با مالیات','acceptorPayment'=>'مبلغ واریزی به پذیرنده','guaranteeCheckPrincipal'=>'چک ضمانت اصل','guaranteeCheckTotal'=>'چک ضمانت اصل و سود'];
    }

    private function formatCalculation(string $key, mixed $value): string
    {
        if (in_array($key, ['installmentCount','numberOfInstallments'], true)) return Renderer::e(PersianDateTime::digits((string)(int)$value).' قسط');
        if ($key === 'operationCostPercent') return Renderer::e(PersianDateTime::digits($this->trimNumber(((float)$value)*100, 4)).'٪');
        if (is_numeric($value)) return $this->money((float)$value).' ریال';
        return Renderer::e(PersianDateTime::digits((string)$value));
    }

    private function trimNumber(float $value, int $precision=2): string
    { return rtrim(rtrim(number_format($value, $precision, '.', ''), '0'), '.'); }

    private function eventLabel(string $type): string
    {
        return [
            'request.created'=>'ایجاد پرونده','request.updated'=>'ویرایش اطلاعات پرونده','request.transition'=>'تغییر وضعیت پرونده',
            'request.transition.denied'=>'تلاش ناموفق برای تغییر وضعیت','request.guarantors.updated'=>'به‌روزرسانی ضامن‌ها',
        ][$type] ?? 'رویداد پرونده';
    }

    private function apiMessage(HttpResponse $response): string
    {
        $message = $response->json['error']['message'] ?? null;
        if (is_string($message) && trim($message) !== '') return $message;
        return $response->status === 0 ? 'ارتباط با سرویس تسهیلات برقرار نشد. وضعیت سرویس را بررسی کنید.' : 'عملیات کامل نشد. دوباره تلاش کنید.';
    }

    private function errorPage(string $title, HttpResponse $response): void
    {
        $content = $this->head($title, 'این بخش در حال حاضر قابل نمایش نیست.')
            . $this->alert('error', 'دریافت اطلاعات انجام نشد', $this->apiMessage($response))
            . '<div class="bn-form-actions"><a class="bn-btn bn-btn--primary" href="/acceptor">بازگشت به پنل پذیرنده</a></div>';
        echo Renderer::page($title, $content, $this->identity, 'acceptor');
    }
}
