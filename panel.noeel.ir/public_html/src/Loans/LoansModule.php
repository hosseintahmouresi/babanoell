<?php
declare(strict_types=1);
namespace Babanoell\Panel\Loans;

use Babanoell\Panel\Http\HttpResponse;
use Babanoell\Panel\Http\LoansClient;
use Babanoell\Panel\Http\DocumentsClient;
use Babanoell\Panel\Support\IniSize;
use Babanoell\Panel\Security\Csrf;
use Babanoell\Panel\Support\PersianDateTime;
use Babanoell\Panel\View\Renderer;

final class LoansModule
{
    public function __construct(
        private readonly LoansClient $client,
        private readonly string $bearer,
        private readonly array $identity,
        private readonly ?DocumentsClient $documents=null,
    ) {}

    public function handle(string $path, string $method): bool
    {
        if ($path === '/loans') { if (!$this->can('loans.requests.read')) return $this->deny('تسهیلات'); $this->overview(); return true; }
        if ($path === '/loans/plans') {
            if ($method === 'POST') { if (!$this->can('loans.plans.manage')) return $this->deny('مدیریت طرح‌ها'); $this->createPlan(); }
            else { if (!$this->can('loans.plans.read')) return $this->deny('طرح‌های تسهیلات'); $this->plans(); }
            return true;
        }
        if ($path === '/loans/plans/new') { if (!$this->can('loans.plans.manage')) return $this->deny('ایجاد طرح'); $this->planForm(); return true; }
        if (preg_match('#^/loans/plans/([a-f0-9]{26})$#', $path, $m)) { if (!$this->can('loans.plans.read')) return $this->deny('جزئیات طرح'); $this->planDetail($m[1]); return true; }
        if (preg_match('#^/loans/plans/([a-f0-9]{26})/edit$#', $path, $m)) { if (!$this->can('loans.plans.manage')) return $this->deny('ویرایش طرح'); $method === 'POST' ? $this->editPlan($m[1]) : $this->planForm($m[1]); return true; }
        if (preg_match('#^/loans/plans/([a-f0-9]{26})/status$#', $path, $m) && $method === 'POST') { if (!$this->can('loans.plans.manage')) return $this->deny('تغییر وضعیت طرح'); $this->planStatus($m[1]); return true; }
        if (preg_match('#^/loans/plans/([a-f0-9]{26})/duplicate$#', $path, $m) && $method === 'POST') { if (!$this->can('loans.plans.manage')) return $this->deny('تکثیر طرح'); $this->planDuplicate($m[1]); return true; }
        if (preg_match('#^/loans/plans/([a-f0-9]{26})/order$#', $path, $m) && $method === 'POST') { if (!$this->can('loans.plans.manage')) return $this->deny('ترتیب طرح'); $this->planOrder($m[1]); return true; }
        if (preg_match('#^/loans/plans/([a-f0-9]{26})/merchant-access$#', $path, $m) && $method === 'POST') { if (!$this->can('loans.plans.manage')) return $this->deny('دسترسی پذیرنده'); $this->planMerchantAccess($m[1]); return true; }
        if (preg_match('#^/loans/plans/([a-f0-9]{26})/preview$#', $path, $m) && $method === 'POST') { if (!$this->can('loans.plans.manage')) return $this->deny('پیش‌نمایش محاسبه'); $this->planPreview($m[1]); return true; }
        if ($path === '/loans/calculator') { if (!$this->can('loans.calculate')) return $this->deny('محاسبه‌گر تسهیلات'); $this->calculator($method); return true; }
        if ($path === '/loans/tasks/report') { if (!($this->can('loans.requests.workflow_admin') || $this->can('loans.requests.operations') || $this->can('loans.requests.finance'))) return $this->deny('گزارش گردش‌کار'); $this->taskReport(); return true; }
        if ($path === '/loans/tasks/settings') { if (!$this->can('loans.requests.workflow_admin')) return $this->deny('تنظیمات SLA'); $method === 'POST' ? $this->saveSlaSettings() : $this->slaSettings(); return true; }
        if ($path === '/loans/tasks') { if (!$this->can('loans.requests.read')) return $this->deny('کارتابل وظایف'); $this->tasks(); return true; }
        if ($path === '/loans/tasks/sla/run' && $method === 'POST') { if (!$this->can('loans.requests.workflow_admin')) return $this->deny('پایش SLA'); $this->runSla(); return true; }
        if (preg_match('#^/loans/tasks/([a-f0-9]{26})/(claim|start|complete|release|reassign|cancel)$#', $path, $m) && $method === 'POST') { if (!$this->can('loans.requests.read')) return $this->deny('عملیات تسک'); $this->taskAction($m[1], $m[2]); return true; }
        if (preg_match('#^/loans/tasks/([a-f0-9]{26})$#', $path, $m)) { if (!$this->can('loans.requests.read')) return $this->deny('جزئیات تسک'); $this->taskDetail($m[1]); return true; }
        if ($path === '/loans/requests') { if (!$this->can('loans.requests.read')) return $this->deny('پرونده‌های تسهیلات'); $this->requests(); return true; }
        if ($path === '/loans/requests/new') { if (!$this->can('loans.requests.create')) return $this->deny('ثبت پرونده جدید'); $method === 'POST' ? $this->createRequest() : $this->requestForm(); return true; }
        if (preg_match('#^/loans/requests/([a-f0-9]{26})/correct$#', $path, $m)) { if (!$this->isAdminForCorrections()) return $this->deny('اصلاح مدیریتی'); $method === 'POST' ? $this->correctRequest($m[1]) : $this->correctionForm($m[1]); return true; }
        if ($path === '/loans/requests/resolve-legacy' && $method === 'GET') { if (!$this->isAdminForCorrections()) return $this->deny('جست‌وجوی شناسه قدیمی'); $this->resolveLegacyRequest(); return true; }
        if (preg_match('#^/loans/requests/([a-f0-9]{26})/edit$#', $path, $m)) { if (!$this->can('loans.requests.update')) return $this->deny('ویرایش پرونده'); $method === 'POST' ? $this->editRequest($m[1]) : $this->requestEditForm($m[1]); return true; }
        if (preg_match('#^/loans/requests/([a-f0-9]{26})/guarantors$#', $path, $m) && $method === 'POST') { if (!$this->can('loans.requests.update')) return $this->deny('ویرایش ضامن‌ها'); $this->saveGuarantors($m[1]); return true; }
        if (preg_match('#^/loans/requests/([a-f0-9]{26})/transition$#', $path, $m) && $method === 'POST') { if (!$this->can('loans.requests.transition')) return $this->deny('تغییر وضعیت پرونده'); $this->transition($m[1]); return true; }
        if (preg_match('#^/loans/requests/([a-f0-9]{26})/notes/([a-f0-9]{26})/history$#', $path, $m) && $method === 'GET') { $this->internalNoteHistory($m[1],$m[2]); return true; }
        if (preg_match('#^/loans/requests/([a-f0-9]{26})/notes/([a-f0-9]{26})/edit$#', $path, $m) && $method === 'POST') { $this->editInternalNote($m[1],$m[2]);return true; }
        if (preg_match('#^/loans/requests/([a-f0-9]{26})/(messages|notes|assignment|document-receipt|bank-review|grant|bank-settlement|merchant-settlement|financier-payment)$#', $path, $m) && $method === 'POST') { $this->workflowAction($m[1], $m[2]); return true; }
        if (preg_match('#^/loans/requests/([a-f0-9]{26})$#', $path, $m)) { if (!$this->can('loans.requests.read')) return $this->deny('جزئیات پرونده'); $this->requestDetail($m[1]); return true; }
        return false;
    }

    private function overview(): void
    {
        $r = $this->client->get('/api/v1/stats', $this->bearer);
        $data = $r->ok() && is_array($r->json['data'] ?? null) ? $r->json['data'] : [];
        $taskResponse=$this->client->get('/api/v1/tasks/summary',$this->bearer);
        $taskSummary=$taskResponse->ok()&&is_array($taskResponse->json['data']??null)?$taskResponse->json['data']:[];
        $notice = $r->ok() ? '' : $this->alert('error', 'اطلاعات آماری در دسترس نیست', $this->apiMessage($r));
        $content = $this->head('تسهیلات', 'مدیریت طرح‌ها، محاسبه اقساط و پیگیری پرونده‌های تسهیلات از یک فضای کاری یکپارچه.')
            . $this->takeFlash() . $notice
            . '<div class="bn-stats">'
            . $this->stat('طرح‌های فعال', $data['active_plans'] ?? '—', 'طرح قابل استفاده')
            . $this->stat('کل پرونده‌ها', $data['requests'] ?? '—', 'پرونده ثبت‌شده')
            . $this->stat('در حال پیگیری', $data['in_progress'] ?? '—', 'پرونده باز')
            . $this->stat('اعطا شده', $data['granted'] ?? '—', 'پرونده تکمیل‌شده', true)
            . $this->stat('وظایف من', $taskSummary['mine'] ?? '—', 'تسک‌های ارجاع‌شده')
            . $this->stat('SLA عبورکرده', $taskSummary['overdue'] ?? '—', 'نیازمند اقدام فوری')
            . '</div>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۱</span><div><h2 class="bn-sectiontitle">دسترسی سریع</h2><p class="bn-sectiondesc">عملیات اصلی تسهیلات را از این بخش آغاز کنید.</p></div></div>'
            . '<div class="bn-loans-actions">'
            . ($this->can('loans.plans.read') ? $this->actionCard('/loans/plans', 'طرح‌های تسهیلات', $this->can('loans.plans.manage') ? 'تعریف، نسخه‌بندی و مدیریت وضعیت طرح‌ها' : 'مشاهده طرح‌های فعال تسهیلات') : '')
            . ($this->can('loans.calculate') ? $this->actionCard('/loans/calculator', 'محاسبه‌گر تسهیلات', 'محاسبه دقیق اقساط با قواعد مصوب سامانه') : '')
            . ($this->can('loans.requests.read') ? $this->actionCard('/loans/tasks?scope=mine', 'کارتابل من', 'وظایف ارجاع‌شده، SLA و موارد نیازمند اقدام') : '')
            . ($this->can('loans.requests.read') ? $this->actionCard('/loans/requests', 'پرونده‌های تسهیلات', 'جستجو، بررسی و پیگیری پرونده‌های مجاز') : '')
            . ($this->can('loans.requests.create') ? $this->actionCard('/loans/requests/new', 'ثبت پرونده جدید', 'ثبت متقاضی، سفارش و برنامه اقساط') : '')
            . '</div></section>';
        echo Renderer::page('تسهیلات', $content, $this->identity, 'loans');
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
            $rows .= '<tr><td><a class="bn-table-link" href="/loans/plans/'.Renderer::e((string)$p['id']).'"><strong>'.Renderer::e((string)$p['name']).'</strong><small>'.Renderer::e((string)$p['code']).'</small></a></td>'
                . '<td>'.$this->typeLabel((string)$p['type']).'</td>'
                . '<td>'.$this->status((string)$p['status'], $this->planStatusLabel((string)$p['status'])).'</td>'
                . '<td>'.PersianDateTime::digits((string)$p['current_version']).'</td>'
                . '<td>'.Renderer::e(PersianDateTime::fromIso((string)($p['updated_at'] ?? ''), false) ?? '—').'</td></tr>';
        }
        if ($rows === '') $rows = '<tr><td colspan="5"><div class="bn-empty-inline"><strong>طرحی پیدا نشد</strong><span>فیلترها را تغییر دهید یا یک طرح جدید ایجاد کنید.</span></div></td></tr>';
        $planActions = $this->can('loans.plans.manage') ? '<a class="bn-btn bn-btn--secondary" href="/loans/plans?status=archived">نمایش بایگانی</a><a class="bn-btn bn-btn--primary" href="/loans/plans/new">ایجاد طرح جدید</a>' : '';
        $content = $this->head('طرح‌های تسهیلات', $this->can('loans.plans.manage') ? 'طرح‌های بایگانی‌شده از فهرست عادی پنهان‌اند و از فیلتر وضعیت قابل مشاهده و فعال‌سازی مجدد هستند.' : 'طرح‌های فعال قابل مشاهده برای نقش فعلی.', $planActions)
            . $this->takeFlash()
            . '<section class="bn-section"><form class="bn-filterbar" method="get" action="/loans/plans"><div class="bn-field"><label class="bn-label">نوع طرح</label><select name="type"><option value="">همه انواع</option>'.$this->option('market','بازاری',$type).$this->option('leasing','لیزینگی',$type).'</select></div><div class="bn-field"><label class="bn-label">وضعیت</label><select name="status"><option value=""'.($status === '' ? ' selected' : '').'>جاری (بدون بایگانی)</option>'.$this->option('active','فعال',$status).$this->option('inactive','غیرفعال',$status).$this->option('draft','پیش‌نویس',$status).$this->option('archived','بایگانی',$status).$this->option('all','همه وضعیت‌ها (با بایگانی)',$status).'</select></div><div class="bn-filter-actions"><button class="bn-btn bn-btn--primary" type="submit">اعمال فیلتر</button><a class="bn-btn bn-btn--secondary" href="/loans/plans">پاک‌کردن</a></div></form></section>'
            . '<section class="bn-section"><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>طرح</th><th>نوع</th><th>وضعیت</th><th>نسخه</th><th>آخرین تغییر</th></tr></thead><tbody>'.$rows.'</tbody></table></div>'.$this->pagination('/loans/plans', (int)($data['page'] ?? $page), (int)($data['per_page'] ?? 20), (int)($data['total'] ?? count($items)), ['status'=>$status,'type'=>$type]).'</section>';
        echo Renderer::page('طرح‌های تسهیلات', $content, $this->identity, 'loans');
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
        $rules = is_array($plan['rules'] ?? null) ? $plan['rules'] : [];
        $eligibility = is_array($plan['eligibility'] ?? null) ? $plan['eligibility'] : [];
        $type = (string)($_POST['type'] ?? ($plan['type'] ?? 'market'));
        if (!in_array($type, ['market','leasing'], true)) $type = 'market';

        $metaResponse = $this->client->get('/api/v1/meta/plans', $this->bearer);
        $meta = $metaResponse->ok() && is_array($metaResponse->json['data'] ?? null) ? $metaResponse->json['data'] : [];
        $occupations = is_array($meta['occupations'] ?? null) ? $meta['occupations'] : $this->occupationLabels();
        $visibilityCatalog = is_array($meta['visibility_contexts'] ?? null) ? $meta['visibility_contexts'] : $this->visibilityLabels();
        $requiredCatalog = is_array($meta['required_documents'] ?? null) ? $meta['required_documents'] : $this->requiredDocumentLabels();

        $market = [
            'min_amount'=>10000000,'max_amount'=>500000000,'fixed_monthly_rate'=>3,'prepayment_percent'=>20,
            'supplier_costs_percent'=>1,'intermediary_deductions_percent'=>1,'babanovel_deductions_percent'=>1,'insurance_percent'=>1,
            'guarantee_check_percent'=>150,'acceptor_commission_percent'=>1,'additional_deductions_percent'=>1,
            'acceptor_deductions_amount'=>30000000,'vat_percent'=>10,'active_installments'=>'6,12,18,24','requires_bank_validation'=>false,'requires_identity_verification'=>false,
        ];
        $leasing = [
            'min_amount'=>100000000,'max_amount'=>1000000000,'min_interval'=>1,'max_interval'=>3,'interest_rate'=>23,'effective_interest_rate'=>25,
            'credit_share_percent'=>80,'supplier_costs_percent'=>1,'intermediary_deductions_percent'=>0.5,'babanovel_deductions_percent'=>1,'insurance_percent'=>0.5,
            'other_deductions_amount'=>0,'guarantee_check_percent'=>130,'acceptor_commission_percent'=>2,'additional_deductions_percent'=>0,
            'acceptor_deductions_amount'=>0,'vat_percent'=>10,'active_installments'=>'12,18,24,36','requires_bank_validation'=>false,'requires_identity_verification'=>false,
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

        $boolValue = static function(string $name, bool $fallback): bool {
            if (array_key_exists($name, $_POST)) return (string)$_POST[$name] === '1';
            return $fallback;
        };
        $bankValidation = $boolValue('requires_bank_validation', !empty($rules['requires_bank_validation'] ?? $stored['requires_bank_validation'] ?? $commonDefaults['requires_bank_validation']));
        $identityVerification = $boolValue('requires_identity_verification', !empty($rules['requires_identity_verification'] ?? $stored['requires_identity_verification'] ?? $commonDefaults['requires_identity_verification']));
        $checkImagesRequired = $boolValue('check_images_required', !array_key_exists('check_images_required',$rules) || !empty($rules['check_images_required']));
        $secondaryCheckRequired = $boolValue('secondary_check_required', !array_key_exists('secondary_check_required',$rules) || !empty($rules['secondary_check_required']));
        $occupationDocsEnabled = $boolValue('occupation_documents_enabled', !empty($rules['occupation_documents_enabled']));
        $contractMode=(string)($_POST['contract_mode']??($rules['contract_mode']??'physical'));if(!in_array($contractMode,['physical','digital'],true))$contractMode='physical';
        $loanApplicationEnabled=$boolValue('loan_application_contract_enabled', !array_key_exists('loan_application_contract_enabled',$rules)||!empty($rules['loan_application_contract_enabled']));
        $facilityAgreementEnabled=$boolValue('facility_agreement_contract_enabled', !array_key_exists('facility_agreement_contract_enabled',$rules)||!empty($rules['facility_agreement_contract_enabled']));
        $loanApplicationTemplate=(string)($_POST['loan_application_template_code']??($rules['loan_application_template_code']??'loan_application_default'));
        $facilityAgreementTemplate=(string)($_POST['facility_agreement_template_code']??($rules['facility_agreement_template_code']??'facility_agreement_default'));

        $requiredSelected = array_key_exists('required_documents_present', $_POST)
            ? (is_array($_POST['required_documents'] ?? null) ? array_values(array_filter($_POST['required_documents'], 'is_string')) : [])
            : (is_array($plan['required_documents'] ?? null) ? array_values(array_filter($plan['required_documents'], 'is_string')) : []);
        $requiredBoxes = '<input type="hidden" name="required_documents_present" value="1">';
        foreach ($requiredCatalog as $code => $label) {
            $requiredBoxes .= '<label class="bn-checkline"><input type="checkbox" name="required_documents[]" value="'.Renderer::e((string)$code).'"'.(in_array((string)$code, $requiredSelected, true) ? ' checked' : '').'><span>'.Renderer::e((string)$label).'</span></label>';
        }

        $selectedVisibility = array_key_exists('visibility_contexts_present', $_POST)
            ? (is_array($_POST['visibility_contexts'] ?? null) ? array_values(array_filter($_POST['visibility_contexts'], 'is_string')) : [])
            : (is_array($rules['visibility_contexts'] ?? null) ? $rules['visibility_contexts'] : array_keys($visibilityCatalog));
        $visibilityBoxes = '<input type="hidden" name="visibility_contexts_present" value="1">';
        foreach ($visibilityCatalog as $code=>$label) $visibilityBoxes .= '<label class="bn-checkline"><input type="checkbox" name="visibility_contexts[]" value="'.Renderer::e((string)$code).'"'.(in_array((string)$code,$selectedVisibility,true)?' checked':'').'><span>'.Renderer::e((string)$label).'</span></label>';

        $merchantMode = (string)($_POST['merchant_access_mode'] ?? ($rules['merchant_access_mode'] ?? 'all'));
        if (!in_array($merchantMode,['all','allowlist'],true)) $merchantMode='all';
        $displayOrder = (string)($_POST['display_order'] ?? ($plan['display_order'] ?? '0'));

        $occupationMap = array_key_exists('occupation_documents_present', $_POST)
            ? (is_array($_POST['occupation_documents'] ?? null) ? $_POST['occupation_documents'] : [])
            : (is_array($rules['required_documents_by_occupation'] ?? null) ? $rules['required_documents_by_occupation'] : []);
        $occupationSections = '<input type="hidden" name="occupation_documents_present" value="1"><div class="bn-occupation-docs-grid">';
        foreach ($occupations as $occupationCode=>$occupationLabel) {
            $selected = is_array($occupationMap[$occupationCode] ?? null) ? $occupationMap[$occupationCode] : [];
            $boxes='';
            foreach ($requiredCatalog as $docCode=>$docLabel) {
                $boxes .= '<label class="bn-checkline bn-checkline--compact"><input type="checkbox" name="occupation_documents['.Renderer::e((string)$occupationCode).'][]" value="'.Renderer::e((string)$docCode).'"'.(in_array((string)$docCode,$selected,true)?' checked':'').'><span>'.Renderer::e((string)$docLabel).'</span></label>';
            }
            $occupationSections .= '<details class="bn-occupation-docs"><summary><strong>'.Renderer::e((string)$occupationLabel).'</strong><span>'.PersianDateTime::digits((string)count($selected)).' مدرک</span></summary><div class="bn-required-doc-grid">'.$boxes.'</div></details>';
        }
        $occupationSections .= '</div>';

        $eligibilityValue = static function(string $key, string $fallback='') use ($eligibility): string {
            if (array_key_exists($key,$_POST)) return (string)$_POST[$key];
            return isset($eligibility[$key]) ? (string)$eligibility[$key] : $fallback;
        };
        $allowedOccupations = is_array($_POST['allowed_occupations'] ?? null) ? $_POST['allowed_occupations'] : (is_array($eligibility['allowed_occupations'] ?? null) ? $eligibility['allowed_occupations'] : []);
        $blockedOccupations = is_array($_POST['blocked_occupations'] ?? null) ? $_POST['blocked_occupations'] : (is_array($eligibility['blocked_occupations'] ?? null) ? $eligibility['blocked_occupations'] : []);
        $allowedOptions=''; $blockedOptions='';
        foreach($occupations as $code=>$label){
            $allowedOptions.='<option value="'.Renderer::e((string)$code).'"'.(in_array((string)$code,$allowedOccupations,true)?' selected':'').'>'.Renderer::e((string)$label).'</option>';
            $blockedOptions.='<option value="'.Renderer::e((string)$code).'"'.(in_array((string)$code,$blockedOccupations,true)?' selected':'').'>'.Renderer::e((string)$label).'</option>';
        }

        $content = $this->head($id ? 'ویرایش طرح' : 'ایجاد طرح تسهیلات', $id ? 'ثبت تغییرات، نسخه جدید و غیرقابل‌تغییر می‌سازد و پرونده‌های قبلی به Snapshot نسخه خود متصل می‌مانند.' : 'طرح را با قواعد محاسبه، نمایش، مدارک و دسترسی پذیرنده به‌صورت کامل تعریف کنید.')
            . ($error !== '' ? $this->alert('error', 'ذخیره انجام نشد', $error) : '')
            . '<form method="post" action="'.($id ? '/loans/plans/'.$id.'/edit' : '/loans/plans').'" data-submit-lock="1"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'">'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۱</span><div><h2 class="bn-sectiontitle">مشخصات و نمایش طرح</h2><p class="bn-sectiondesc">هویت طرح، ترتیب و محل‌هایی که کاربران می‌توانند آن را انتخاب کنند.</p></div></div><div class="bn-grid bn-grid--2">'
            . $this->field('نام طرح', 'name', (string)($_POST['name'] ?? ($plan['name'] ?? '')), 'text', 'text', '', true)
            . ($id ? '<div class="bn-field"><label class="bn-label">کد طرح</label><input value="'.Renderer::e((string)$plan['code']).'" readonly aria-readonly="true"><span class="bn-helper">کد طرح بعد از ایجاد تغییر نمی‌کند.</span></div>' : $this->field('کد طرح', 'code', (string)($_POST['code'] ?? ''), 'text', 'latin', 'حروف انگلیسی، عدد، خط تیره یا زیرخط', true))
            . ($id ? '<div class="bn-field"><label class="bn-label">نوع طرح</label><input type="hidden" name="type" value="'.Renderer::e($type).'"><input value="'.Renderer::e($this->typeLabel($type)).'" readonly aria-readonly="true"></div>' : '<div class="bn-field"><label class="bn-label" for="plan-type">نوع طرح <span class="bn-required">*</span></label><select id="plan-type" name="type" data-plan-type data-plan-defaults-market="'.Renderer::e(json_encode($market, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)).'" data-plan-defaults-leasing="'.Renderer::e(json_encode($leasing, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)).'"><option value="market"'.($type === 'market' ? ' selected' : '').'>بازاری</option><option value="leasing"'.($type === 'leasing' ? ' selected' : '').'>لیزینگی</option></select><span class="bn-helper" data-plan-type-hint>'.($type === 'market' ? 'اقساط ماهانه با نرخ ثابت طرح بازاری.' : 'محاسبه لیزینگی با نرخ سود و فاصله سررسید قابل تنظیم.').'</span></div>')
            . $this->field('ترتیب نمایش', 'display_order', $displayOrder, 'number', 'numeric', 'عدد کمتر، طرح را بالاتر نمایش می‌دهد.')
            . '<div class="bn-field"><label class="bn-label">دسترسی پذیرنده‌ها</label><select name="merchant_access_mode"><option value="all"'.($merchantMode==='all'?' selected':'').'>همه پذیرنده‌ها؛ با امکان مسدودسازی موردی</option><option value="allowlist"'.($merchantMode==='allowlist'?' selected':'').'>فقط پذیرنده‌های مجازشده</option></select></div></div><div class="bn-field bn-field--wide"><label class="bn-label">محل‌های نمایش طرح</label><div class="bn-required-doc-grid">'.$visibilityBoxes.'</div></div></section>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۲</span><div><h2 class="bn-sectiontitle">قواعد محاسبه</h2><p class="bn-sectiondesc">پارامترهای مالی نسخه جاری؛ تغییر این بخش در ویرایش، نسخه جدید می‌سازد.</p></div></div><div class="bn-plan-fields" data-plan-fields="market"'.($type !== 'market' ? ' hidden' : '').'><div class="bn-grid bn-grid--2">'.$marketFields.'</div></div><div class="bn-plan-fields" data-plan-fields="leasing"'.($type !== 'leasing' ? ' hidden' : '').'><div class="bn-grid bn-grid--2">'.$leasingFields.'</div></div><div class="bn-grid bn-grid--2 bn-plan-common">'.$common.'</div></section>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۳</span><div><h2 class="bn-sectiontitle">قواعد بانکی و چک</h2><p class="bn-sectiondesc">این سوییچ‌ها مستقیماً روی چک‌لیست مدارک و گردش پرونده اثر دارند.</p></div></div><div class="bn-required-doc-grid"><input type="hidden" name="requires_bank_validation" value="0"><label class="bn-checkline"><input type="checkbox" name="requires_bank_validation" value="1"'.($bankValidation?' checked':'').'><span>نیاز به اعتبارسنجی بانک</span></label><input type="hidden" name="requires_identity_verification" value="0"><label class="bn-checkline"><input type="checkbox" name="requires_identity_verification" value="1"'.($identityVerification?' checked':'').'><span>نیاز به تطبیق هویت شاهکار</span></label><input type="hidden" name="check_images_required" value="0"><label class="bn-checkline"><input type="checkbox" name="check_images_required" value="1"'.($checkImagesRequired?' checked':'').'><span>تصاویر چک الزامی باشد</span></label><input type="hidden" name="secondary_check_required" value="0"><label class="bn-checkline"><input type="checkbox" name="secondary_check_required" value="1"'.($secondaryCheckRequired?' checked':'').'><span>چک تکمیلی الزامی باشد</span></label></div></section>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۴</span><div><h2 class="bn-sectiontitle">قراردادهای طرح</h2><p class="bn-sectiondesc">نوع قرارداد و کد قالب در Snapshot هر پرونده منجمد می‌شود.</p></div></div><div class="bn-grid bn-grid--2"><div class="bn-field"><label class="bn-label">نوع قرارداد</label><select name="contract_mode"><option value="physical"'.($contractMode==='physical'?' selected':'').'>فیزیکی</option><option value="digital"'.($contractMode==='digital'?' selected':'').'>دیجیتال</option></select></div>'.$this->field('کد قالب درخواست تسهیلات','loan_application_template_code',$loanApplicationTemplate).$this->field('کد قالب قرارداد دریافت تسهیلات','facility_agreement_template_code',$facilityAgreementTemplate).'</div><div class="bn-required-doc-grid"><input type="hidden" name="loan_application_contract_enabled" value="0"><label class="bn-checkline"><input type="checkbox" name="loan_application_contract_enabled" value="1"'.($loanApplicationEnabled?' checked':'').'><span>درخواست تسهیلات فعال باشد</span></label><input type="hidden" name="facility_agreement_contract_enabled" value="0"><label class="bn-checkline"><input type="checkbox" name="facility_agreement_contract_enabled" value="1"'.($facilityAgreementEnabled?' checked':'').'><span>قرارداد دریافت تسهیلات فعال باشد</span></label></div></section>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۴</span><div><h2 class="bn-sectiontitle">مدارک الزامی عمومی نسخه</h2><p class="bn-sectiondesc">این فهرست، چک‌لیست پیش‌فرض نسخه است و در صورت فعال‌کردن مدارک شغلی، نقش fallback را دارد.</p></div></div><div class="bn-required-doc-grid">'.$requiredBoxes.'</div></section>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۵</span><div><h2 class="bn-sectiontitle">مدارک وابسته به شغل</h2><p class="bn-sectiondesc">با فعال‌بودن این گزینه، برای هر شغل فقط مدارک همان شغل مطالبه می‌شود.</p></div></div><input type="hidden" name="occupation_documents_enabled" value="0"><label class="bn-checkline bn-checkline--feature"><input type="checkbox" name="occupation_documents_enabled" value="1"'.($occupationDocsEnabled?' checked':'').'><span><strong>مدارک شغلی فعال باشد</strong><small>برای هر شغل حداقل یک مدرک انتخاب کنید.</small></span></label>'.$occupationSections.'</section>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۶</span><div><h2 class="bn-sectiontitle">شرایط احراز صلاحیت</h2><p class="bn-sectiondesc">محدودیت سن، تابعیت و شغل قبل از ایجاد پرونده enforce می‌شود.</p></div></div><div class="bn-grid bn-grid--2">'.$this->field('حداقل سن', 'min_age', $eligibilityValue('min_age'), 'number', 'numeric').$this->field('حداکثر سن', 'max_age', $eligibilityValue('max_age'), 'number', 'numeric').$this->field('تابعیت', 'nationality', $eligibilityValue('nationality'), 'text', 'text', 'اختیاری؛ مانند IR').'<div class="bn-field"><label class="bn-label">مشاغل مجاز</label><select name="allowed_occupations[]" multiple size="8">'.$allowedOptions.'</select><span class="bn-helper">خالی = بدون محدودیت از این نوع</span></div><div class="bn-field"><label class="bn-label">مشاغل غیرمجاز</label><select name="blocked_occupations[]" multiple size="8">'.$blockedOptions.'</select><span class="bn-helper">در صورت تعارض، محدودیت‌های موتور بررسی می‌شوند.</span></div></div></section>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۷</span><div><h2 class="bn-sectiontitle">شرایط و توضیحات</h2></div></div>'.$this->area('شرایط و توضیحات', 'conditions', (string)($_POST['conditions'] ?? ($plan['conditions_text'] ?? ''))).'</section>'
            . '<div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit">'.($id ? 'ثبت نسخه جدید' : 'ایجاد طرح').'</button><a class="bn-btn bn-btn--secondary" href="/loans/plans">انصراف</a></div></form>';
        echo Renderer::page($id ? 'ویرایش طرح' : 'ایجاد طرح', $content, $this->identity, 'loans');
    }

    private function createPlan(): void
    {
        if (!Csrf::valid((string)($_POST['_csrf'] ?? ''))) { $this->planForm(null, 'اعتبار فرم منقضی شده است. صفحه را تازه‌سازی کنید.'); return; }
        $r = $this->client->post('/api/v1/plans', $this->bearer, $this->planPayload(true));
        if (!$r->ok()) { $this->planForm(null, $this->apiMessage($r)); return; }
        $id = (string)($r->json['data']['plan']['id'] ?? '');
        $this->flash('success', 'طرح با موفقیت ایجاد شد.');
        header('Location: /loans/plans/'.$id, true, 303); exit;
    }

    private function editPlan(string $id): void
    {
        if (!Csrf::valid((string)($_POST['_csrf'] ?? ''))) { $this->planForm($id, 'اعتبار فرم منقضی شده است. صفحه را تازه‌سازی کنید.'); return; }
        $r = $this->client->put('/api/v1/plans/'.$id, $this->bearer, $this->planPayload(false));
        if (!$r->ok()) { $this->planForm($id, $this->apiMessage($r)); return; }
        $this->flash('success', 'نسخه جدید طرح با موفقیت ثبت شد.');
        header('Location: /loans/plans/'.$id, true, 303); exit;
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
        $settings['requires_identity_verification'] = (string)($_POST['requires_identity_verification'] ?? '0') === '1';

        $required = is_array($_POST['required_documents'] ?? null) ? array_values(array_filter($_POST['required_documents'],'is_string')) : [];
        $visibility = is_array($_POST['visibility_contexts'] ?? null) ? array_values(array_filter($_POST['visibility_contexts'],'is_string')) : [];
        $occupationMap=[];
        if (is_array($_POST['occupation_documents'] ?? null)) {
            foreach ($_POST['occupation_documents'] as $occupation=>$documents) {
                if (!is_string($occupation) || !is_array($documents)) continue;
                $occupationMap[$occupation]=array_values(array_filter($documents,'is_string'));
            }
        }
        $rules=[
            'visibility_contexts'=>$visibility,
            'merchant_access_mode'=>(string)($_POST['merchant_access_mode'] ?? 'all'),
            'occupation_documents_enabled'=>(string)($_POST['occupation_documents_enabled'] ?? '0')==='1',
            'required_documents_by_occupation'=>$occupationMap,
            'secondary_check_required'=>(string)($_POST['secondary_check_required'] ?? '0')==='1',
            'check_images_required'=>(string)($_POST['check_images_required'] ?? '0')==='1',
            'requires_bank_validation'=>(string)($_POST['requires_bank_validation'] ?? '0')==='1',
            'requires_identity_verification'=>(string)($_POST['requires_identity_verification'] ?? '0')==='1',
            'contract_mode'=>(string)($_POST['contract_mode']??'physical'),
            'loan_application_contract_enabled'=>(string)($_POST['loan_application_contract_enabled']??'0')==='1',
            'facility_agreement_contract_enabled'=>(string)($_POST['facility_agreement_contract_enabled']??'0')==='1',
            'loan_application_template_code'=>trim((string)($_POST['loan_application_template_code']??'loan_application_default')),
            'facility_agreement_template_code'=>trim((string)($_POST['facility_agreement_template_code']??'facility_agreement_default')),
            'calculation_schema_version'=>'v1',
        ];
        $eligibility=[];
        foreach(['min_age','max_age'] as $key){$raw=trim((string)($_POST[$key]??''));if($raw!=='')$eligibility[$key]=$this->integerish($raw);}
        $nationality=trim((string)($_POST['nationality']??''));if($nationality!=='')$eligibility['nationality']=$nationality;
        foreach(['allowed_occupations','blocked_occupations'] as $key){if(is_array($_POST[$key]??null))$eligibility[$key]=array_values(array_filter($_POST[$key],'is_string'));}
        $payload = [
            'name'=>trim((string)($_POST['name'] ?? '')),
            'type'=>$type,
            'settings'=>$settings,
            'required_documents'=>$required,
            'conditions'=>trim((string)($_POST['conditions'] ?? '')),
            'display_order'=>max(0,$this->integerish($_POST['display_order'] ?? 0)),
            'rules'=>$rules,
            'eligibility'=>$eligibility,
        ];
        if ($new) $payload['code'] = trim((string)($_POST['code'] ?? ''));
        return $payload;
    }

    private function planDetail(string $id, ?array $previewResult=null, string $previewError=''): void
    {
        $r = $this->client->get('/api/v1/plans/'.$id, $this->bearer);
        if (!$r->ok()) { $this->errorPage('جزئیات طرح', $r); return; }
        $p = $r->json['data']['plan'] ?? [];
        if (!is_array($p)) { $this->errorPage('جزئیات طرح', new HttpResponse(500, null)); return; }
        $versionsResponse=$this->client->get('/api/v1/plans/'.$id.'/versions',$this->bearer);
        $versions=$versionsResponse->ok()&&is_array($versionsResponse->json['data']['items']??null)?$versionsResponse->json['data']['items']:[];
        $rules=is_array($p['rules']??null)?$p['rules']:[];
        $eligibility=is_array($p['eligibility']??null)?$p['eligibility']:[];
        $settings = '';
        foreach ((array)($p['settings'] ?? []) as $key => $value) $settings .= '<tr><td>'.Renderer::e($this->settingLabels()[(string)$key] ?? (string)$key).'</td><td>'.$this->formatSetting((string)$key, $value).'</td></tr>';
        $currentStatus = (string)($p['status'] ?? 'draft');
        $buttons = '';
        foreach (['active'=>'فعال','inactive'=>'غیرفعال','archived'=>'بایگانی','draft'=>'پیش‌نویس'] as $code=>$label) $buttons .= '<form method="post" action="/loans/plans/'.$id.'/status" class="bn-inline-form"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="status" value="'.$code.'"><button type="submit" class="bn-btn bn-btn--secondary bn-btn--small"'.($code === $currentStatus ? ' disabled aria-disabled="true"' : '').'>'.$label.'</button></form>';
        $conditions = trim((string)($p['conditions_text'] ?? ''));
        $requiredDocs = '';
        foreach ((array)($p['required_documents'] ?? []) as $code) if (is_string($code)) $requiredDocs .= '<span class="bn-pill">'.Renderer::e($this->requiredDocumentLabels()[$code] ?? $code).'</span>';
        if ($requiredDocs === '') $requiredDocs = '<span class="bn-muted">برای این نسخه مدرک عمومی تعریف نشده است.</span>';

        $contexts='';foreach((array)($rules['visibility_contexts']??[]) as$code){if(is_string($code))$contexts.='<span class="bn-pill">'.Renderer::e($this->visibilityLabels()[$code]??$code).'</span>';}
        if($contexts==='')$contexts='<span class="bn-muted">محل نمایش مشخص نشده است.</span>';
        $occupationSummary='';$occupationMap=is_array($rules['required_documents_by_occupation']??null)?$rules['required_documents_by_occupation']:[];
        foreach($occupationMap as$occupation=>$docs){if(!is_array($docs))continue;$occupationSummary.='<details class="bn-occupation-docs"><summary><strong>'.Renderer::e($this->occupationLabels()[(string)$occupation]??(string)$occupation).'</strong><span>'.PersianDateTime::digits((string)count($docs)).' مدرک</span></summary><div class="bn-pill-list">';foreach($docs as$doc)if(is_string($doc))$occupationSummary.='<span class="bn-pill">'.Renderer::e($this->requiredDocumentLabels()[$doc]??$doc).'</span>';$occupationSummary.='</div></details>';}
        if($occupationSummary==='')$occupationSummary='<span class="bn-muted">مدارک وابسته به شغل برای این نسخه تعریف نشده است.</span>';

        $versionsRows='';
        foreach($versions as$v){if(!is_array($v))continue;$versionsRows.='<tr><td>'.PersianDateTime::digits((string)($v['version']??'—')).'</td><td><code dir="ltr">'.Renderer::e(substr((string)($v['configuration_hash']??''),0,16)).'</code></td><td>'.Renderer::e(PersianDateTime::fromIso((string)($v['created_at']??''),false)??'—').'</td><td>'.(!empty($v['published_at'])?'منتشرشده':'ثبت‌شده').'</td></tr>';}
        if($versionsRows==='')$versionsRows='<tr><td colspan="4"><span class="bn-muted">تاریخچه نسخه در دسترس نیست.</span></td></tr>';

        $eligibilityRows='';foreach(['min_age'=>'حداقل سن','max_age'=>'حداکثر سن','nationality'=>'تابعیت'] as$key=>$label){if(isset($eligibility[$key])&&$eligibility[$key]!=='')$eligibilityRows.='<tr><td>'.$label.'</td><td>'.Renderer::e(PersianDateTime::digits((string)$eligibility[$key])).'</td></tr>';}
        foreach(['allowed_occupations'=>'مشاغل مجاز','blocked_occupations'=>'مشاغل غیرمجاز'] as$key=>$label){$vals=is_array($eligibility[$key]??null)?$eligibility[$key]:[];if($vals!==[]){$labels=[];foreach($vals as$v)$labels[]=$this->occupationLabels()[(string)$v]??(string)$v;$eligibilityRows.='<tr><td>'.$label.'</td><td>'.Renderer::e(implode('، ',$labels)).'</td></tr>';}}
        if($eligibilityRows==='')$eligibilityRows='<tr><td colspan="2"><span class="bn-muted">محدودیت احراز صلاحیت تعریف نشده است.</span></td></tr>';

        $previewHtml='';
        if($previewError!=='')$previewHtml.=$this->alert('error','پیش‌نمایش محاسبه انجام نشد',$previewError);
        if(is_array($previewResult)){$rows='';foreach($previewResult as$key=>$value)$rows.='<tr><td>'.Renderer::e($this->calcLabels()[(string)$key]??(string)$key).'</td><td>'.$this->formatCalculation((string)$key,$value).'</td></tr>';$previewHtml.='<div class="bn-table-wrap bn-table-wrap--after"><table class="bn-table bn-table--keyvalue"><tbody>'.$rows.'</tbody></table></div>';}

        $actions=$this->can('loans.plans.manage')?'<a class="bn-btn bn-btn--primary" href="/loans/plans/'.$id.'/edit">ویرایش و ساخت نسخه جدید</a>':'';
        $content = $this->head((string)$p['name'], 'کد '.(string)$p['code'].' · نسخه '.PersianDateTime::digits((string)$p['current_version']), $actions)
            . $this->takeFlash()
            . '<div class="bn-stats">'.$this->stat('نوع', $this->typeLabel((string)$p['type']), 'روش محاسبه').$this->stat('وضعیت', $this->planStatusLabel($currentStatus), 'وضعیت عملیاتی').$this->stat('نسخه', PersianDateTime::digits((string)$p['current_version']), 'نسخه جاری').$this->stat('ترتیب', PersianDateTime::digits((string)($p['display_order']??0)), 'اولویت نمایش', true).'</div>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۱</span><div><h2 class="bn-sectiontitle">تنظیمات نسخه جاری</h2><p class="bn-sectiondesc">مقادیر این نسخه و Hash پیکربندی برای پرونده‌های تاریخی ثابت می‌مانند.</p></div></div><div class="bn-table-wrap"><table class="bn-table bn-table--keyvalue"><tbody>'.$settings.'<tr><td>Hash پیکربندی</td><td><code dir="ltr">'.Renderer::e((string)($p['configuration_hash']??'—')).'</code></td></tr></tbody></table></div></section>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۲</span><div><h2 class="bn-sectiontitle">نمایش، دسترسی و چک‌ها</h2></div></div><div class="bn-detail-grid">'.$this->detail('دسترسی پذیرنده',(string)($rules['merchant_access_mode']??'all')==='allowlist'?'فقط فهرست مجاز':'همه پذیرنده‌ها').$this->detail('اعتبارسنجی بانک',!empty($rules['requires_bank_validation'])?'الزامی':'غیرالزامی').$this->detail('تطبیق هویت شاهکار',!empty($rules['requires_identity_verification'])?'الزامی':'غیرالزامی').$this->detail('تصاویر چک',!empty($rules['check_images_required'])?'الزامی':'غیرالزامی').$this->detail('چک تکمیلی',!empty($rules['secondary_check_required'])?'الزامی':'غیرالزامی').$this->detail('نوع قرارداد',(string)($rules['contract_mode']??'physical')==='digital'?'دیجیتال':'فیزیکی').$this->detail('درخواست تسهیلات',!array_key_exists('loan_application_contract_enabled',$rules)||!empty($rules['loan_application_contract_enabled'])?'فعال':'غیرفعال').$this->detail('قرارداد دریافت تسهیلات',!array_key_exists('facility_agreement_contract_enabled',$rules)||!empty($rules['facility_agreement_contract_enabled'])?'فعال':'غیرفعال').'</div><div class="bn-pill-list">'.$contexts.'</div></section>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۳</span><div><h2 class="bn-sectiontitle">مدارک نسخه</h2><p class="bn-sectiondesc">مدارک عمومی و قواعد شغلی در Snapshot پرونده ذخیره می‌شوند.</p></div></div><h3 class="bn-subsection-title">مدارک عمومی</h3><div class="bn-pill-list">'.$requiredDocs.'</div><h3 class="bn-subsection-title">مدارک شغلی</h3>'.$occupationSummary.'</section>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۴</span><div><h2 class="bn-sectiontitle">شرایط احراز صلاحیت</h2></div></div><div class="bn-table-wrap"><table class="bn-table bn-table--keyvalue"><tbody>'.$eligibilityRows.'</tbody></table></div></section>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۵</span><div><h2 class="bn-sectiontitle">تاریخچه نسخه‌ها</h2><p class="bn-sectiondesc">هر ویرایش مالی یا Rule یک نسخه مستقل می‌سازد.</p></div></div><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>نسخه</th><th>Hash</th><th>زمان ایجاد</th><th>وضعیت</th></tr></thead><tbody>'.$versionsRows.'</tbody></table></div></section>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۶</span><div><h2 class="bn-sectiontitle">شرایط طرح</h2></div></div><div class="bn-prose">'.($conditions !== '' ? nl2br(Renderer::e($conditions)) : 'شرایط تکمیلی برای این طرح ثبت نشده است.').'</div></section>';

        if($this->can('loans.plans.manage')){
            $content.='<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۷</span><div><h2 class="bn-sectiontitle">پیش‌نمایش محاسبه</h2><p class="bn-sectiondesc">محاسبه آزمایشی بدون ایجاد رکورد عملیاتی در calculation_runs.</p></div></div>'.$previewHtml.'<form method="post" action="/loans/plans/'.$id.'/preview"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><div class="bn-grid bn-grid--2">'.$this->moneyField('مبلغ','amount',(string)($_POST['amount']??''),true).$this->field('تعداد اقساط','installment_count',(string)($_POST['installment_count']??''),'number','numeric','',true).$this->field('فاصله سررسید (ماه)','installment_interval',(string)($_POST['installment_interval']??'1'),'number','numeric','',true).$this->percentField('کمیسیون Override','commission_override',(string)($_POST['commission_override']??''),false,'اختیاری').'</div><div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit">اجرای پیش‌نمایش</button></div></form></section>'
                .'<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۸</span><div><h2 class="bn-sectiontitle">عملیات مدیریتی طرح</h2></div></div><div class="bn-admin-grid"><div class="bn-admin-card"><h3>تغییر وضعیت</h3><div class="bn-status-actions">'.$buttons.'</div></div><div class="bn-admin-card"><h3>ترتیب نمایش</h3><form method="post" action="/loans/plans/'.$id.'/order"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'">'.$this->field('ترتیب','display_order',(string)($p['display_order']??0),'number','numeric').'<button class="bn-btn bn-btn--secondary" type="submit">ذخیره ترتیب</button></form></div><div class="bn-admin-card"><h3>تکثیر طرح</h3><form method="post" action="/loans/plans/'.$id.'/duplicate"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'">'.$this->field('کد طرح جدید','code','','text','latin','',true).$this->field('نام طرح جدید','name',(string)$p['name'].' - کپی','text','text','',true).'<button class="bn-btn bn-btn--secondary" type="submit">ساخت کپی پیش‌نویس</button></form></div><div class="bn-admin-card"><h3>دسترسی پذیرنده</h3><form method="post" action="/loans/plans/'.$id.'/merchant-access"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'">'.$this->field('شناسه پذیرنده','merchant_id','','text','latin','شناسه ۲۶ کاراکتری پذیرنده',true).'<div class="bn-field"><label class="bn-label">وضعیت</label><select name="status"><option value="active">مجاز</option><option value="inactive">غیرفعال / مسدود</option></select></div><button class="bn-btn bn-btn--secondary" type="submit">ثبت دسترسی</button></form></div></div></section>';
        }
        echo Renderer::page('جزئیات طرح', $content, $this->identity, 'loans');
    }

    private function planStatus(string $id): void
    {
        if (!Csrf::valid((string)($_POST['_csrf'] ?? ''))) { $this->flash('error', 'اعتبار فرم منقضی شده است.'); header('Location: /loans/plans/'.$id, true, 303); exit; }
        $r = $this->client->post('/api/v1/plans/'.$id.'/status', $this->bearer, ['status'=>(string)($_POST['status'] ?? '')]);
        $this->flash($r->ok() ? 'success' : 'error', $r->ok() ? 'وضعیت طرح با موفقیت به‌روزرسانی شد.' : $this->apiMessage($r));
        header('Location: /loans/plans/'.$id, true, 303); exit;
    }

    private function planDuplicate(string $id): void
    {
        if(!Csrf::valid((string)($_POST['_csrf']??''))){$this->flash('error','اعتبار فرم منقضی شده است.');header('Location: /loans/plans/'.$id,true,303);exit;}
        $r=$this->client->post('/api/v1/plans/'.$id.'/duplicate',$this->bearer,['code'=>trim((string)($_POST['code']??'')),'name'=>trim((string)($_POST['name']??''))]);
        if($r->ok()){$newId=(string)($r->json['data']['plan']['id']??'');$this->flash('success','کپی مستقل طرح با موفقیت ساخته شد.');header('Location: /loans/plans/'.($newId!==''?$newId:$id),true,303);exit;}
        $this->flash('error',$this->apiMessage($r));header('Location: /loans/plans/'.$id,true,303);exit;
    }

    private function planOrder(string $id): void
    {
        if(!Csrf::valid((string)($_POST['_csrf']??''))){$this->flash('error','اعتبار فرم منقضی شده است.');header('Location: /loans/plans/'.$id,true,303);exit;}
        $r=$this->client->post('/api/v1/plans/'.$id.'/order',$this->bearer,['display_order'=>max(0,$this->integerish($_POST['display_order']??0))]);
        $this->flash($r->ok()?'success':'error',$r->ok()?'ترتیب نمایش طرح ذخیره شد.':$this->apiMessage($r));header('Location: /loans/plans/'.$id,true,303);exit;
    }

    private function planMerchantAccess(string $id): void
    {
        if(!Csrf::valid((string)($_POST['_csrf']??''))){$this->flash('error','اعتبار فرم منقضی شده است.');header('Location: /loans/plans/'.$id,true,303);exit;}
        $r=$this->client->post('/api/v1/plans/'.$id.'/merchant-access',$this->bearer,['merchant_id'=>trim((string)($_POST['merchant_id']??'')),'status'=>(string)($_POST['status']??'active'),'limits'=>[]]);
        $this->flash($r->ok()?'success':'error',$r->ok()?'دسترسی پذیرنده برای این طرح ثبت شد.':$this->apiMessage($r));header('Location: /loans/plans/'.$id,true,303);exit;
    }

    private function planPreview(string $id): void
    {
        if(!Csrf::valid((string)($_POST['_csrf']??''))){$this->planDetail($id,null,'اعتبار فرم منقضی شده است.');return;}
        $commission=trim((string)($_POST['commission_override']??''));
        $payload=['plan_id'=>$id,'amount'=>$this->numberish($_POST['amount']??0),'installment_count'=>$this->integerish($_POST['installment_count']??0),'installment_interval'=>max(1,$this->integerish($_POST['installment_interval']??1)),'commission_override'=>$commission!==''?$this->numberish($commission):null];
        $r=$this->client->post('/api/v1/calculations/preview',$this->bearer,$payload);
        if(!$r->ok()){$this->planDetail($id,null,$this->apiMessage($r));return;}
        $result=is_array($r->json['data']['result']??null)?$r->json['data']['result']:[];$this->planDetail($id,$result,'');
    }

    private function calculator(string $method): void
    {
        $plans = $this->client->get('/api/v1/plans', $this->bearer, ['status'=>'active','per_page'=>100]);
        if (!$plans->ok()) { $this->errorPage('محاسبه‌گر تسهیلات', $plans); return; }
        $items = is_array($plans->json['data']['items'] ?? null) ? $plans->json['data']['items'] : [];
        $result = null; $error = '';
        if ($method === 'POST') {
            if (!Csrf::valid((string)($_POST['_csrf'] ?? ''))) $error = 'اعتبار فرم منقضی شده است.';
            else {
                $commission = trim((string)($_POST['commission_override'] ?? ''));
                $planId=trim((string)($_POST['plan_id'] ?? ''));
                $amount=$this->numberish($_POST['amount'] ?? 0);
                $installmentCount=$this->integerish($_POST['installment_count'] ?? 0);
                $installmentInterval=$this->integerish($_POST['installment_interval'] ?? 1);
                if($planId==='')$error='انتخاب طرح فعال الزامی است.';
                elseif($amount<=0)$error='مبلغ باید بیشتر از صفر باشد.';
                elseif($installmentCount<1)$error='تعداد اقساط باید یک عدد صحیح مثبت باشد.';
                elseif($installmentInterval<1)$error='فاصله سررسیدها باید یک عدد صحیح مثبت باشد.';
                else{
                    $endpoint=$this->can('loans.plans.manage')?'/api/v1/calculations/preview':'/api/v1/calculations';
                    $r = $this->client->post($endpoint, $this->bearer, [
                        'plan_id'=>$planId,
                        'amount'=>$amount,
                        'installment_count'=>$installmentCount,
                        'installment_interval'=>$installmentInterval,
                        'commission_override'=>$commission !== '' ? $this->numberish($commission) : null,
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
        $content = $this->head('محاسبه‌گر تسهیلات', $this->can('loans.plans.manage') ? 'حالت مدیریت: محاسبه به‌صورت Preview و بدون ثبت رکورد عملیاتی انجام می‌شود.' : 'طرح، مبلغ و تعداد اقساط را وارد کنید تا خروجی مالی مطابق قواعد ثبت‌شده همان نسخه طرح محاسبه شود.')
            . ($error !== '' ? $this->alert('error', 'محاسبه انجام نشد', $error) : '')
            . '<section class="bn-section"><form method="post" data-calculator-form><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><div class="bn-grid bn-grid--2"><div class="bn-field"><label class="bn-label">طرح فعال <span class="bn-required">*</span></label><select name="plan_id" required data-calculator-plan>'.$options.'</select><span class="bn-helper" data-calculator-plan-hint>با انتخاب طرح، محدوده مبلغ و اقساط مجاز نمایش داده می‌شود.</span></div>'
            . $this->moneyField('مبلغ خرید / درخواست', 'amount', (string)($_POST['amount'] ?? ''), true)
            . $this->field('تعداد اقساط', 'installment_count', (string)($_POST['installment_count'] ?? ''), 'number', 'numeric', '', true)
            . $this->field('فاصله سررسیدها (ماه)', 'installment_interval', (string)($_POST['installment_interval'] ?? '1'), 'number', 'numeric', 'برای طرح بازاری مقدار ۱ است.', true, 'data-calculator-interval')
            . ($this->can('loans.plans.manage') ? $this->percentField('کمیسیون اختصاصی پذیرنده', 'commission_override', (string)($_POST['commission_override'] ?? ''), false, 'اختیاری؛ در صورت خالی‌بودن مقدار طرح استفاده می‌شود.') : '')
            . '</div><div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit">محاسبه</button></div></form></section>';
        if (is_array($result)) {
            $rows = '';
            foreach ($result as $key=>$value) $rows .= '<tr><td>'.Renderer::e($this->calcLabels()[(string)$key] ?? (string)$key).'</td><td>'.$this->formatCalculation((string)$key, $value).'</td></tr>';
            $content .= '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">✓</span><div><h2 class="bn-sectiontitle">نتیجه محاسبه</h2><p class="bn-sectiondesc">مبالغ مالی به ریال و درصدها با علامت ٪ نمایش داده می‌شوند.</p></div></div><div class="bn-table-wrap"><table class="bn-table bn-table--keyvalue"><tbody>'.$rows.'</tbody></table></div></section>';
        }
        echo Renderer::page('محاسبه‌گر تسهیلات', $content, $this->identity, 'loans');
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
            $rows .= '<tr><td><a class="bn-table-link" href="/loans/requests/'.Renderer::e((string)$x['id']).'"><strong>'.Renderer::e((string)$x['request_no']).'</strong><small>'.Renderer::e((string)($x['full_name'] ?? 'نام ثبت نشده')).'</small></a></td><td>'.Renderer::e((string)($x['national_code'] ?? '—')).'</td><td>'.$this->money((float)($x['requested_amount'] ?? 0)).'</td><td>'.$this->status((string)$x['status'], $this->requestStatusLabel((string)$x['status'])).'</td><td>'.Renderer::e(PersianDateTime::fromIso((string)($x['updated_at'] ?? ''), false) ?? '—').'</td></tr>';
        }
        if ($rows === '') $rows = '<tr><td colspan="5"><div class="bn-empty-inline"><strong>پرونده‌ای پیدا نشد</strong><span>عبارت جستجو یا وضعیت انتخاب‌شده را تغییر دهید.</span></div></td></tr>';
        $statusOptions = '<option value="">همه وضعیت‌ها</option>';
        foreach ($this->requestLabels() as $code=>$label) $statusOptions .= $this->option($code, $label, $status);
        $requestActions = $this->can('loans.requests.create') ? '<a class="bn-btn bn-btn--primary" href="/loans/requests/new">ثبت پرونده جدید</a>' : '';
        if($this->isAdminForCorrections())$requestActions.='<form method="get" action="/loans/requests/resolve-legacy" class="bn-form-actions"><input name="legacy_id" inputmode="numeric" required placeholder="شناسه قدیمی"><button class="bn-btn bn-btn--secondary">اصلاح با شناسه قدیمی</button></form>';
        $content = $this->head('پرونده‌های تسهیلات', 'پرونده‌ها را بر اساس شماره، نام، کد ملی، موبایل یا وضعیت پیدا و پیگیری کنید.', $requestActions)
            . $this->takeFlash()
            . '<section class="bn-section"><form class="bn-filterbar" method="get" action="/loans/requests"><div class="bn-field bn-filter-search"><label class="bn-label">جستجو</label><input type="search" name="q" value="'.Renderer::e($search).'" placeholder="شماره پرونده، نام، کد ملی یا موبایل"></div><div class="bn-field"><label class="bn-label">وضعیت</label><select name="status">'.$statusOptions.'</select></div><div class="bn-filter-actions"><button class="bn-btn bn-btn--primary" type="submit">جستجو</button><a class="bn-btn bn-btn--secondary" href="/loans/requests">پاک‌کردن</a></div></form></section>'
            . '<section class="bn-section"><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>پرونده</th><th>کد ملی</th><th>مبلغ درخواست</th><th>وضعیت</th><th>آخرین تغییر</th></tr></thead><tbody>'.$rows.'</tbody></table></div>'.$this->pagination('/loans/requests', (int)($data['page'] ?? $page), (int)($data['per_page'] ?? 20), (int)($data['total'] ?? count($items)), ['status'=>$status,'q'=>$search]).'</section>';
        echo Renderer::page('پرونده‌های تسهیلات', $content, $this->identity, 'loans');
    }

    private function requestForm(string $error = ''): void
    {
        $plans = $this->client->get('/api/v1/plans', $this->bearer, ['status'=>'active','per_page'=>100]);
        if (!$plans->ok()) { $this->errorPage('ثبت پرونده', $plans); return; }
        $items = is_array($plans->json['data']['items'] ?? null) ? $plans->json['data']['items'] : [];
        $options = '<option value="">انتخاب کنید</option>';
        foreach ($items as $p) if (is_array($p)) $options .= '<option value="'.Renderer::e((string)$p['id']).'"'.(((string)($_POST['plan_id'] ?? '')) === (string)$p['id'] ? ' selected' : '').'>'.Renderer::e((string)$p['name']).' · '.$this->typeLabel((string)$p['type']).'</option>';
        $v = static fn(string $key, string $default=''): string => (string)($_POST[$key] ?? $default);
        $content = $this->head('ثبت پرونده تسهیلات', 'اطلاعات متقاضی، طرح و سفارش را ثبت کنید. می‌توانید ابتدا پیش‌نویس ذخیره کنید یا پرونده را مستقیم برای بررسی ارسال کنید.')
            . ($error !== '' ? $this->alert('error', 'ثبت انجام نشد', $error) : '')
            . '<form method="post" action="/loans/requests/new" data-submit-lock="1"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'">'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۱</span><div><h2 class="bn-sectiontitle">هویت متقاضی</h2><p class="bn-sectiondesc">برای ارسال پرونده، اطلاعات هویتی و تماس باید کامل و معتبر باشند.</p></div></div><div class="bn-grid bn-grid--2">'
            . $this->field('نام و نام خانوادگی','full_name',$v('full_name')).$this->field('نام پدر','father_name',$v('father_name')).$this->field('شماره شناسنامه','certificate_number',$v('certificate_number')).$this->field('کد ملی','national_code',$v('national_code'),'text','numeric','۱۰ رقم').$this->field('شماره موبایل','mobile_number',$v('mobile_number'),'text','tel','با ۰۹ شروع شود').$this->field('شماره تلفن ثابت','phone_number',$v('phone_number'),'text','tel').$this->birthDateField($v('birth_date')).$this->occupationField($v('occupation')).$this->field('کد پستی','postal_code',$v('postal_code'),'text','numeric','۱۰ رقم').'</div><div class="bn-grid bn-grid--2">'.$this->area('آدرس محل سکونت','residential_address',$v('residential_address')).$this->area('آدرس محل فعالیت','work_address',$v('work_address')).'</div></section>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۲</span><div><h2 class="bn-sectiontitle">طرح و سفارش</h2><p class="bn-sectiondesc">مبلغ سفارش باید با مبلغ درخواست برابر باشد.</p></div></div><div class="bn-grid bn-grid--2"><div class="bn-field"><label class="bn-label">طرح تسهیلات <span class="bn-required">*</span></label><select name="plan_id" required>'.$options.'</select></div>'.$this->field('نام محصول / خدمت','product_name',$v('product_name')).$this->moneyField('مبلغ سفارش','amount',$v('amount'),true).$this->field('تعداد اقساط','installment_count',$v('installment_count'),'number','numeric','',true).$this->field('فاصله سررسیدها (ماه)','installment_interval',$v('installment_interval','1'),'number','numeric','',true).$this->field('اولین سررسید شمسی','first_due_date',$v('first_due_date'),'text','numeric','مثال: ۱۴۰۵/۰۷/۰۱',true).$this->field('شناسه پذیرنده','merchant_ref',$v('merchant_ref'),'text','text','اختیاری؛ تا زمان اتصال سرویس شرکا').'</div></section>'
            . '<div class="bn-form-actions"><button class="bn-btn bn-btn--secondary" type="submit" name="_action" value="draft">ذخیره پیش‌نویس</button><button class="bn-btn bn-btn--primary" type="submit" name="_action" value="submit">ثبت و ارسال برای بررسی</button><a class="bn-btn bn-btn--ghost" href="/loans/requests">انصراف</a></div></form>';
        echo Renderer::page('ثبت پرونده', $content, $this->identity, 'loans');
    }

    private function createRequest(): void
    {
        if (!Csrf::valid((string)($_POST['_csrf'] ?? ''))) { $this->requestForm('اعتبار فرم منقضی شده است.'); return; }
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
            'work_address'=>trim((string)($_POST['work_address'] ?? '')), 'product_name'=>$name, 'merchant_ref'=>trim((string)($_POST['merchant_ref'] ?? '')),
            'requested_amount'=>$amount, 'order_total'=>$amount,
            'order_items'=>[['name'=>$name,'qty'=>1,'unit_price'=>$amount,'total_price'=>$amount]],
            'installment_count'=>(int)($_POST['installment_count'] ?? 0), 'installment_interval'=>max(1,(int)($_POST['installment_interval'] ?? 1)),
            'first_due_date'=>trim((string)($_POST['first_due_date'] ?? '')),
        ];
        $r = $this->client->post('/api/v1/requests', $this->bearer, $payload, 'panel-'.bin2hex(random_bytes(16)));
        if (!$r->ok()) { $this->requestForm($this->apiMessage($r)); return; }
        $id = (string)($r->json['data']['request']['id'] ?? '');
        $this->flash('success', $submit ? 'پرونده ثبت و برای بررسی ارسال شد.' : 'پیش‌نویس پرونده ذخیره شد.');
        header('Location: /loans/requests/'.$id, true, 303); exit;
    }

    private function requestEditForm(string $id, string $error = ''): void
    {
        $r = $this->client->get('/api/v1/requests/'.$id, $this->bearer);
        if (!$r->ok()) { $this->errorPage('ویرایش پرونده', $r); return; }
        $x = $r->json['data']['request'] ?? [];
        if (!is_array($x)) { $this->errorPage('ویرایش پرونده', new HttpResponse(500, null)); return; }
        if (!in_array((string)$x['status'], ['draft','need_correction'], true)) {
            $this->flash('error', 'ویرایش اطلاعات فقط برای پیش‌نویس یا پرونده دارای نقص مجاز است.');
            header('Location: /loans/requests/'.$id, true, 303); exit;
        }
        $v = static function(string $key, array $request): string {
            if (array_key_exists($key, $_POST)) return (string)$_POST[$key];
            if ($key === 'birth_date') return PersianDateTime::dateOnly((string)($request[$key] ?? '')) ?? '';
            return (string)($request[$key] ?? '');
        };
        $content = $this->head('ویرایش اطلاعات پرونده', 'اصلاح اطلاعات هویتی و تماس در وضعیت فعلی مجاز است. تغییر مبلغ و طرح نیازمند پرونده جدید است.')
            . ($error !== '' ? $this->alert('error','ذخیره انجام نشد',$error) : '')
            . '<form method="post" action="/loans/requests/'.$id.'/edit" data-submit-lock="1"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><section class="bn-section"><div class="bn-grid bn-grid--2">'
            . $this->field('نام و نام خانوادگی','full_name',$v('full_name',$x)).$this->field('نام پدر','father_name',$v('father_name',$x)).$this->field('شماره شناسنامه','certificate_number',$v('certificate_number',$x)).$this->field('کد ملی','national_code',$v('national_code',$x),'text','numeric').$this->field('شماره موبایل','mobile_number',$v('mobile_number',$x),'text','tel').$this->field('شماره تلفن ثابت','phone_number',$v('phone_number',$x),'text','tel').$this->birthDateField($v('birth_date',$x)).$this->occupationField($v('occupation',$x)).$this->field('کد پستی','postal_code',$v('postal_code',$x),'text','numeric').$this->field('شناسه پذیرنده','merchant_ref',$v('merchant_ref',$x)).$this->field('نام محصول / خدمت','product_name',$v('product_name',$x)).($this->can('loans.requests.assign_bank') ? $this->field('شناسه کارشناس بانک','assigned_bank_user_id',$v('assigned_bank_user_id',$x)) : '').'</div><div class="bn-grid bn-grid--2">'.$this->area('آدرس محل سکونت','residential_address',$v('residential_address',$x)).$this->area('آدرس محل فعالیت','work_address',$v('work_address',$x)).'</div></section><div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit">ذخیره تغییرات</button><a class="bn-btn bn-btn--secondary" href="/loans/requests/'.$id.'">انصراف</a></div></form>';
        echo Renderer::page('ویرایش پرونده', $content, $this->identity, 'loans');
    }

    private function editRequest(string $id): void
    {
        if (!Csrf::valid((string)($_POST['_csrf'] ?? ''))) { $this->requestEditForm($id, 'اعتبار فرم منقضی شده است.'); return; }
        $fields = [];
        foreach (['full_name','father_name','certificate_number','national_code','mobile_number','phone_number','occupation','residential_address','postal_code','work_address','product_name','merchant_ref','assigned_bank_user_id'] as $key) $fields[$key] = trim((string)($_POST[$key] ?? '')); $fields['birth_date'] = $this->buildBirthDateFromPost();
        $r = $this->client->patch('/api/v1/requests/'.$id, $this->bearer, $fields);
        if (!$r->ok()) { $this->requestEditForm($id, $this->apiMessage($r)); return; }
        $this->flash('success', 'اطلاعات پرونده با موفقیت به‌روزرسانی شد.');
        header('Location: /loans/requests/'.$id, true, 303); exit;
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
        $workflowResponse = $this->client->get('/api/v1/requests/'.$id.'/workflow', $this->bearer);
        $workflow = $workflowResponse->ok() && is_array($workflowResponse->json['data']['workflow'] ?? null) ? $workflowResponse->json['data']['workflow'] : [];
        $editable = $this->can('loans.requests.update') && in_array((string)($x['status'] ?? ''), ['draft','need_correction'], true);
        $actions = ($this->can('documents.view') ? '<a class="bn-btn bn-btn--primary" href="/loans/requests/'.$id.'/documents">مدارک پرونده</a>' : '').'<a class="bn-btn bn-btn--secondary" href="/contracts/loan/'.$id.'">قراردادها</a><a class="bn-btn bn-btn--secondary" href="/loans/tasks?request_id='.$id.'&status=all">وظایف پرونده</a>'.($editable ? '<a class="bn-btn bn-btn--secondary" href="/loans/requests/'.$id.'/edit">ویرایش اطلاعات</a>' : '').($this->isAdminForCorrections()?'<a class="bn-btn bn-btn--secondary" href="/loans/requests/'.$id.'/correct">اصلاح مدیریتی</a>':'');
        $content = $this->head('پرونده '.(string)($x['request_no'] ?? ''), (string)($x['full_name'] ?? 'متقاضی').' · '.$this->requestStatusLabel((string)($x['status'] ?? '')), $actions)
            . $this->takeFlash()
            . CaseJourney::render($this->client,$this->bearer,$id,$this->identity)
            . '<div class="bn-stats">'.$this->stat('مبلغ درخواست',$this->money((float)($x['requested_amount'] ?? 0)),'ریال').$this->stat('مبلغ تسهیلات',$this->money((float)($x['facility_amount'] ?? 0)),'ریال').$this->stat('مبلغ هر قسط',$this->money((float)($x['installment_amount'] ?? 0)),'ریال').$this->stat('وضعیت',$this->requestStatusLabel((string)($x['status'] ?? '')),'مرحله جاری',true).'</div>'
            . '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۱</span><div><h2 class="bn-sectiontitle">اطلاعات پرونده</h2><p class="bn-sectiondesc">طرح '.Renderer::e((string)($x['plan_name'] ?? '—')).' · نسخه '.PersianDateTime::digits((string)($x['plan_version'] ?? '—')).'</p></div></div><div class="bn-detail-grid">'
            . $this->detail('کد ملی',(string)($x['national_code'] ?? '—')).$this->detail('موبایل',(string)($x['mobile_number'] ?? '—')).$this->detail('نام پدر',(string)($x['father_name'] ?? '—')).$this->detail('کد پستی',(string)($x['postal_code'] ?? '—')).$this->detail('تاریخ تولد',PersianDateTime::dateOnly((string)($x['birth_date'] ?? '')) ?? '—').$this->detail('محصول / خدمت',(string)($x['product_name'] ?? '—')).$this->detail('پیش‌پرداخت',$this->money((float)($x['prepayment'] ?? 0))).$this->detail('جمع بازپرداخت',$this->money((float)($x['total_payment'] ?? 0))).$this->detail('سود',$this->money((float)($x['profit'] ?? 0))).$this->detail('پرداخت پذیرنده',$this->money((float)($x['acceptor_payment_amount'] ?? 0))).'</div></section>'
            . $this->scheduleSection($schedule)
            . $this->guarantorsSection($id, $guarantors)
            . $this->caseWorkflowSection($id, $x, $workflow)
            . ($this->can('loans.requests.audit') ? $this->historySection($history).$this->eventsSection($events) : '')
            . ($this->can('loans.requests.transition') ? $this->transitionSection($id, $allowed, (string)($x['assigned_bank_user_id'] ?? '')) : '');
        echo Renderer::page('جزئیات پرونده', $content, $this->identity, 'loans');
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
        if (!$this->can('loans.requests.update')) {
            $body = '';
            foreach ($rows as $g) {
                $body .= '<tr><td>'.Renderer::e((string)($g['full_name'] ?? '—')).'</td><td>'.Renderer::e((string)($g['national_code'] ?? '—')).'</td><td>'.Renderer::e((string)($g['mobile_number'] ?? '—')).'</td><td>'.Renderer::e((string)($g['relation_label'] ?? '—')).'</td></tr>';
            }
            if ($body === '') $body = '<tr><td colspan="4"><div class="bn-empty-inline">ضامنی برای این پرونده ثبت نشده است.</div></td></tr>';
            return '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۳</span><div><h2 class="bn-sectiontitle">ضامن‌ها</h2><p class="bn-sectiondesc">اطلاعات ضامن‌های ثبت‌شده در پرونده.</p></div></div><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>نام</th><th>کد ملی</th><th>موبایل</th><th>نسبت / توضیح</th></tr></thead><tbody>'.$body.'</tbody></table></div></section>';
        }
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
        return '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۳</span><div><h2 class="bn-sectiontitle">ضامن‌ها</h2><p class="bn-sectiondesc">ردیف خالی ذخیره نمی‌شود. کد ملی و موبایل در صورت ورود اعتبارسنجی می‌شوند.</p></div></div><form method="post" action="/loans/requests/'.$id.'/guarantors" data-guarantors-form><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><div class="bn-guarantors" data-guarantors-container>'.$fields.'</div><template data-guarantor-template><div class="bn-guarantor-row" data-guarantor-row><div class="bn-guarantor-head"><strong>ضامن</strong><button class="bn-btn bn-btn--ghost bn-btn--small" type="button" data-remove-guarantor>حذف</button></div><div class="bn-grid bn-grid--2"><div class="bn-field"><label class="bn-label">نام و نام خانوادگی</label><input data-name="full_name"></div><div class="bn-field"><label class="bn-label">کد ملی</label><input inputmode="numeric" data-name="national_code"></div><div class="bn-field"><label class="bn-label">شماره موبایل</label><input inputmode="tel" data-name="mobile_number"></div><div class="bn-field"><label class="bn-label">نسبت / توضیح</label><input data-name="relation_label"></div></div></div></template><div class="bn-form-toolbar"><button type="button" class="bn-btn bn-btn--secondary bn-btn--small" data-add-guarantor>افزودن ضامن</button><button type="submit" class="bn-btn bn-btn--primary">ذخیره ضامن‌ها</button></div></form></section>';
    }

    private function saveGuarantors(string $id): void
    {
        if (!Csrf::valid((string)($_POST['_csrf'] ?? ''))) { $this->flash('error','اعتبار فرم منقضی شده است.'); header('Location: /loans/requests/'.$id, true, 303); exit; }
        $items = is_array($_POST['guarantors'] ?? null) ? $_POST['guarantors'] : [];
        $r = $this->client->put('/api/v1/requests/'.$id.'/guarantors', $this->bearer, ['guarantors'=>$items]);
        $this->flash($r->ok() ? 'success' : 'error', $r->ok() ? 'اطلاعات ضامن‌ها با موفقیت ذخیره شد.' : $this->apiMessage($r));
        header('Location: /loans/requests/'.$id, true, 303); exit;
    }

    private function historySection(array $history): string
    {
        $rows = '';
        foreach ($history as $row) {
            if (!is_array($row)) continue;
            $from = trim((string)($row['from_status'] ?? ''));
            $rows .= '<tr><td>'.($from !== '' ? Renderer::e($this->requestStatusLabel($from)).' ← ' : '').Renderer::e($this->requestStatusLabel((string)($row['to_status'] ?? ''))).'</td><td>'.Renderer::e(trim((string)($row['reason'] ?? '')) ?: '—').'</td><td>'.Renderer::e(PersianDateTime::fromIso((string)($row['created_at'] ?? ''), false) ?? '—').'</td></tr>';
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
        $bankField = $this->can('loans.requests.assign_bank') ? '<div class="bn-field" data-bank-user-field hidden><label class="bn-label">شناسه کارشناس بانک <span class="bn-required">*</span></label><input name="assigned_bank_user_id" value="'.Renderer::e($assignedBankUser).'" autocomplete="off"><span class="bn-helper">برای تأیید کارشناس باید این شناسه مشخص باشد.</span></div>' : '';
        return '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۶</span><div><h2 class="bn-sectiontitle">اقدام بعدی پرونده</h2><p class="bn-sectiondesc">فقط انتقال‌های مجاز نمایش داده می‌شوند. برای رد، لغو یا اعلام نقص، ثبت دلیل الزامی است.</p></div></div><form method="post" action="/loans/requests/'.$id.'/transition" data-transition-form><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><div class="bn-grid bn-grid--2"><div class="bn-field"><label class="bn-label">وضعیت بعدی <span class="bn-required">*</span></label><select name="status" required data-transition-status>'.$options.'</select></div>'.$bankField.'<div class="bn-field bn-grid-span-2"><label class="bn-label">توضیح تغییر وضعیت <span class="bn-required" data-reason-required hidden>*</span></label><textarea name="reason" rows="3" data-transition-reason placeholder="توضیح کوتاه و قابل پیگیری ثبت کنید"></textarea></div></div><div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit">ثبت تغییر وضعیت</button></div></form></section>';
    }

    private function transition(string $id): void
    {
        if (!Csrf::valid((string)($_POST['_csrf'] ?? ''))) { $this->flash('error','اعتبار فرم منقضی شده است.'); header('Location: /loans/requests/'.$id, true, 303); exit; }
        $payload = ['status'=>(string)($_POST['status'] ?? ''), 'reason'=>trim((string)($_POST['reason'] ?? ''))];
        if (array_key_exists('assigned_bank_user_id', $_POST)) $payload['assigned_bank_user_id'] = trim((string)$_POST['assigned_bank_user_id']);
        $r = $this->client->post('/api/v1/requests/'.$id.'/transition', $this->bearer, $payload);
        $this->flash($r->ok() ? 'success' : 'error', $r->ok() ? 'وضعیت پرونده با موفقیت به‌روزرسانی شد.' : $this->apiMessage($r));
        header('Location: /loans/requests/'.$id, true, 303); exit;
    }


    private function caseWorkflowSection(string $id, array $request, array $workflow): string
    {
        $catalogs=is_array($workflow['catalogs']??null)?$workflow['catalogs']:[];
        $messages=is_array($workflow['messages']??null)?$workflow['messages']:[];
        $comments=is_array($workflow['comments']??null)?$workflow['comments']:[];
        $reviews=is_array($workflow['bank_reviews']??null)?$workflow['bank_reviews']:[];
        $grant=is_array($workflow['grant']??null)?$workflow['grant']:null;
        $html='<section class="bn-section" id="case-workflow"><div class="bn-sectionhead"><span class="bn-step">۴</span><div><h2 class="bn-sectiontitle">گردش عملیاتی پرونده</h2><p class="bn-sectiondesc">پیام‌ها، کنترل مدارک، بررسی بانک، اعطا و تسویه در یک جریان واحد ثبت می‌شوند.</p></div></div>';
        $html.='<div class="bn-detail-grid">'.$this->detail('دریافت مدارک',$this->catalogLabel($catalogs,'document_receipt',(string)($request['document_receipt_status']??'unreviewed'))).$this->detail('تسویه بانک',$this->catalogLabel($catalogs,'bank_settlement',((string)($request['bank_settlement_status']??'')!==''?(string)$request['bank_settlement_status']:'not_recorded'))).$this->detail('تسویه پذیرنده',$this->catalogLabel($catalogs,'merchant_settlement',(string)($request['settlement_status']??'not_started'))).$this->detail('پرداخت تأمین‌کننده',$this->catalogLabel($catalogs,'financier_payment',(string)($request['financier_payment_status']??'unreviewed'))).'</div>';
        if($this->can('loans.requests.workflow_admin')||$this->can('loans.requests.assign_bank')){$html.='<div class="bn-workflow-block"><h3>ارجاع کارشناس بانک</h3><form method="post" action="/loans/requests/'.$id.'/assignment"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="type" value="bank_expert"><div class="bn-grid bn-grid--2">'.$this->field('شناسه کارشناس بانک','user_id',(string)($request['assigned_bank_user_id']??''),'text','text','شناسه کاربر سرویس Identity',true).$this->field('دلیل ارجاع','reason','').'</div><button class="bn-btn bn-btn--secondary" type="submit">ثبت ارجاع</button></form></div>';}

        if($this->can('loans.requests.message')){
            $items='';foreach(array_slice($messages,-20)as$m){if(!is_array($m))continue;$items.='<div class="bn-message"><strong>'.Renderer::e((string)($m['sender_role']??'کاربر')).'</strong><p>'.nl2br(Renderer::e((string)($m['message']??''))).'</p><small>'.Renderer::e(PersianDateTime::fromIso((string)($m['created_at']??''),false)??'—').'</small></div>';}
            if($items==='')$items='<div class="bn-empty-inline">هنوز پیامی ثبت نشده است.</div>';
            $html.='<div class="bn-workflow-block"><h3>مکالمات پرونده</h3>'.$items.'<form method="post" action="/loans/requests/'.$id.'/messages"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><div class="bn-field"><label class="bn-label">پیام جدید</label><textarea name="message" rows="3" required></textarea></div><button class="bn-btn bn-btn--primary" type="submit">ارسال پیام</button></form></div>';
        }
        if($this->can('loans.requests.internal_notes')){
            $notes='';foreach(array_slice($comments,-20)as$c){
                if(!is_array($c))continue;
                $attachments='';$files=is_array($c['attachment_document_ids']??null)?$c['attachment_document_ids']:[];
                foreach($files as $pos=>$fileId){if(!is_string($fileId)||preg_match('/^[a-f0-9]{26}$/D',$fileId)!==1)continue;$attachments.='<a class="bn-btn bn-btn--secondary" href="/documents/files/'.Renderer::e($fileId).'/download" rel="nofollow">پیوست '.Renderer::e((string)($pos+1)).'</a> ';}
                $editable=($this->can('loans.requests.workflow_admin')||in_array('super_admin',(array)($this->identity['roles']??[]),true)||(string)($c['author_user_id']??'')===(string)($this->identity['user']['id']??''));
                $editForm=$editable?'<details><summary>اصلاح یادداشت (با حفظ تاریخچه)</summary><form method="post" action="/loans/requests/'.$id.'/notes/'.Renderer::e((string)$c['id']).'/edit"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="expected_version" value="'.(int)($c['version_no']??1).'"><textarea name="comment" rows="3" maxlength="5000" required>'.Renderer::e((string)($c['comment']??'')).'</textarea><button class="bn-btn bn-btn--secondary" type="submit">ذخیره اصلاح</button></form></details>':'';
                $history=((int)($c['version_no']??1)>1)?'<a href="/loans/requests/'.$id.'/notes/'.Renderer::e((string)$c['id']).'/history">مشاهده تاریخچه اصلاحات</a>':'';
                $notes.='<div class="bn-note"><p>'.nl2br(Renderer::e((string)($c['comment']??''))).'</p>'.$attachments.'<small>'.Renderer::e(PersianDateTime::fromIso((string)($c['created_at']??''),false)??'—').' · نسخه '.(int)($c['version_no']??1).'</small>'.$history.$editForm.'</div>';
            }
            if($notes==='')$notes='<div class="bn-empty-inline">یادداشت داخلی ثبت نشده است.</div>';
            $fileInput=$this->documents!==null && $this->can('documents.upload') && $this->can('documents.view') && $this->can('documents.download')?'<div class="bn-field"><label class="bn-label">پیوست‌ها (حداکثر ۵ فایل JPG، PNG، WEBP یا PDF)</label><input type="file" name="note_files[]" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple><span class="bn-helper">هر فایل حداکثر ۱۰ مگابایت؛ مدارک محرمانه و فقط برای کاربران داخلی مجاز قابل دریافت‌اند.</span></div>':'';
            $html.='<div class="bn-workflow-block"><h3>یادداشت‌های داخلی</h3>'.$notes.'<form method="post" enctype="multipart/form-data" action="/loans/requests/'.$id.'/notes"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="operation_key" value="'.bin2hex(random_bytes(16)).'"><div class="bn-field"><textarea name="comment" rows="2" maxlength="5000" placeholder="یادداشت فقط برای عوامل داخلی"></textarea></div>'.$fileInput.'<button class="bn-btn bn-btn--secondary" type="submit">ثبت یادداشت</button></form></div>';
        }
        if($this->can('loans.requests.document_receipt')){
            $html.='<div class="bn-workflow-block"><h3>دریافت مدارک</h3><form method="post" action="/loans/requests/'.$id.'/document-receipt"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><div class="bn-grid bn-grid--2"><div class="bn-field"><label class="bn-label">وضعیت</label><select name="status">'.$this->catalogOptions($catalogs,'document_receipt',(string)($request['document_receipt_status']??'unreviewed')).'</select></div>'.$this->field('تاریخ دریافت','received_at',$this->dateTimeLocal((string)($request['document_received_at']??'')),'datetime-local').'<div class="bn-field bn-grid-span-2"><label class="bn-label">یادداشت</label><textarea name="note" rows="2">'.Renderer::e((string)($request['document_receipt_note']??'')).'</textarea></div></div><button class="bn-btn bn-btn--secondary" type="submit">ذخیره وضعیت دریافت</button></form></div>';
        }
        if($this->can('loans.requests.bank_review')){
            $reviewRows='';foreach(array_slice($reviews,0,8)as$rv){if(!is_array($rv))continue;$reviewRows.='<tr><td>'.Renderer::e($this->catalogLabel($catalogs,'bank_review',(string)($rv['status']??''))).'</td><td>'.Renderer::e((string)($rv['reason']??'—')).'</td><td>'.Renderer::e(PersianDateTime::fromIso((string)($rv['created_at']??''),false)??'—').'</td></tr>';}
            if($reviewRows==='')$reviewRows='<tr><td colspan="3"><div class="bn-empty-inline">بررسی بانکی ثبت نشده است.</div></td></tr>';
            $html.='<div class="bn-workflow-block"><h3>بررسی کارشناس بانک</h3><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>نتیجه</th><th>دلیل</th><th>زمان</th></tr></thead><tbody>'.$reviewRows.'</tbody></table></div><form method="post" action="/loans/requests/'.$id.'/bank-review"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><div class="bn-grid bn-grid--2"><div class="bn-field"><label class="bn-label">نتیجه</label><select name="status">'.$this->catalogOptions($catalogs,'bank_review','noted').'</select></div>'.$this->field('مرجع بانک','external_reference','').'<div class="bn-field"><label class="bn-label">دلیل</label><textarea name="reason" rows="2"></textarea></div><div class="bn-field"><label class="bn-label">یادداشت</label><textarea name="note" rows="2"></textarea></div></div><button class="bn-btn bn-btn--secondary" type="submit">ثبت بررسی بانک</button></form></div>';
        }
        if($grant!==null){$html.='<div class="bn-workflow-block"><h3>اعطای ثبت‌شده</h3><div class="bn-detail-grid">'.$this->detail('مبلغ',$this->money((float)($grant['granted_amount']??0))).$this->detail('شماره تسهیلات',(string)($grant['loan_number']??'—')).$this->detail('شماره قرارداد',(string)($grant['contract_number']??'—')).$this->detail('تاریخ اعطا',PersianDateTime::fromIso((string)($grant['granted_at']??''),false)??'—').'</div></div>';}
        elseif($this->can('loans.requests.grant')&&(string)($request['status']??'')==='docs_verified'){$html.='<div class="bn-workflow-block"><h3>ثبت نهایی اعطا</h3><form method="post" action="/loans/requests/'.$id.'/grant"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><div class="bn-grid bn-grid--2">'.$this->moneyField('مبلغ اعطا','granted_amount',(string)($request['facility_amount']??''),true).$this->field('تاریخ و زمان اعطا','granted_at',date('Y-m-d\TH:i'),'datetime-local','text','',true).$this->field('شماره تسهیلات','loan_number','').$this->field('شماره قرارداد','contract_number','').'</div><button class="bn-btn bn-btn--primary" type="submit">ثبت اعطا</button></form></div>';}
        if($this->can('loans.requests.bank_settlement')){$html.='<div class="bn-workflow-block"><h3>تسویه بانک با بابانوئل</h3><form method="post" action="/loans/requests/'.$id.'/bank-settlement"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><div class="bn-grid bn-grid--2"><div class="bn-field"><label class="bn-label">وضعیت</label><select name="status">'.$this->catalogOptions($catalogs,'bank_settlement',((string)($request['bank_settlement_status']??'')!==''?(string)$request['bank_settlement_status']:'not_recorded')).'</select></div>'.$this->field('تاریخ تسویه','settled_at',$this->dateTimeLocal((string)($request['bank_settled_at']??'')),'datetime-local').'<div class="bn-field bn-grid-span-2"><label class="bn-label">یادداشت داخلی</label><textarea name="note" rows="2">'.Renderer::e((string)($request['bank_settlement_note']??'')).'</textarea></div></div><button class="bn-btn bn-btn--secondary" type="submit">ثبت تسویه بانک</button></form></div>';}
        if($this->can('loans.requests.merchant_settlement')){$html.='<div class="bn-workflow-block"><h3>تسویه پذیرنده</h3><form method="post" action="/loans/requests/'.$id.'/merchant-settlement"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><div class="bn-grid bn-grid--2"><div class="bn-field"><label class="bn-label">وضعیت</label><select name="status">'.$this->catalogOptions($catalogs,'merchant_settlement',(string)($request['settlement_status']??'not_started')).'</select></div>'.$this->field('تاریخ تسویه','settlement_date',$this->dateTimeLocal((string)($request['settlement_date']??'')),'datetime-local').$this->field('شناسه دوره تسویه','cycle_id',(string)($request['settlement_cycle_id']??'')).'<div class="bn-field"><label class="bn-label">یادداشت</label><textarea name="note" rows="2"></textarea></div></div><button class="bn-btn bn-btn--secondary" type="submit">ذخیره تسویه پذیرنده</button></form></div>';}
        if($this->can('loans.requests.finance')){$html.='<div class="bn-workflow-block"><h3>پرداخت تأمین‌کننده مالی</h3><form method="post" action="/loans/requests/'.$id.'/financier-payment"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><div class="bn-grid bn-grid--2"><div class="bn-field"><label class="bn-label">وضعیت</label><select name="status">'.$this->catalogOptions($catalogs,'financier_payment',(string)($request['financier_payment_status']??'unreviewed')).'</select></div><div class="bn-field"><label class="bn-label">یادداشت</label><textarea name="note" rows="2">'.Renderer::e((string)($request['financier_payment_note']??'')).'</textarea></div></div><button class="bn-btn bn-btn--secondary" type="submit">ذخیره وضعیت پرداخت</button></form></div>';}
        $html.='</section>';return$html;
    }

    private function tasks(): void
    {
        $page=max(1,(int)($_GET['page']??1));$scope=trim((string)($_GET['scope']??'mine'));$status=trim((string)($_GET['status']??''));$queue=trim((string)($_GET['queue']??''));$requestId=trim((string)($_GET['request_id']??''));$search=trim((string)($_GET['search']??''));$overdue=(string)($_GET['overdue']??'')==='1';
        $q=['page'=>$page,'per_page'=>25];if($scope!=='')$q['scope']=$scope;if($status!=='')$q['status']=$status;if($queue!=='')$q['queue']=$queue;if($requestId!=='')$q['request_id']=$requestId;if($search!=='')$q['search']=$search;if($overdue)$q['overdue']=1;
        $r=$this->client->get('/api/v1/tasks',$this->bearer,$q);$sr=$this->client->get('/api/v1/tasks/summary',$this->bearer);
        if(!$r->ok()){$this->errorPage('کارتابل وظایف',$r);return;}$data=is_array($r->json['data']??null)?$r->json['data']:[];$items=is_array($data['items']??null)?$data['items']:[];$summary=$sr->ok()&&is_array($sr->json['data']??null)?$sr->json['data']:[];
        $rows='';foreach($items as$t){if(!is_array($t))continue;$id=(string)($t['id']??'');$due=(string)($t['due_at']??'');$over=$due!==''&&strtotime($due)!==false&&strtotime($due)<time()&&in_array((string)($t['status']??''),['open','in_progress'],true);$rows.='<tr class="'.($over?'bn-task-overdue':'').'"><td><a class="bn-table-link" href="/loans/tasks/'.Renderer::e($id).'"><strong>'.Renderer::e((string)($t['title']??'تسک')).'</strong><small>'.Renderer::e((string)($t['task_code']??'')).'</small></a></td><td><a class="bn-table-link" href="/loans/requests/'.Renderer::e((string)($t['request_id']??'')).'"><strong>'.Renderer::e((string)($t['request_no']??'—')).'</strong><small>'.Renderer::e((string)($t['full_name']??'')).'</small></a></td><td>'.$this->taskQueue((string)($t['queue_code']??'')).'</td><td>'.$this->taskStatus((string)($t['status']??'')).'</td><td>'.$this->taskPriority((string)($t['priority']??'normal')).'</td><td>'.Renderer::e($due!==''?(PersianDateTime::fromIso($due,false)??$due):'—').($over?'<small class="bn-task-overdue-label">عبور از SLA</small>':'').'</td></tr>';}
        if($rows==='')$rows='<tr><td colspan="6"><div class="bn-empty-inline"><strong>تسکی پیدا نشد</strong><span>فیلترها را تغییر دهید یا منتظر ایجاد وظیفه بعدی گردش‌کار باشید.</span></div></td></tr>';
        $actions='';if($this->can('loans.requests.workflow_admin')||$this->can('loans.requests.operations')||$this->can('loans.requests.finance'))$actions.='<a class="bn-btn bn-btn--secondary" href="/loans/tasks/report">گزارش گردش‌کار</a>';if($this->can('loans.requests.workflow_admin'))$actions.='<a class="bn-btn bn-btn--secondary" href="/loans/tasks/settings">تنظیمات SLA</a><form method="post" action="/loans/tasks/sla/run" class="bn-inline-form"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><button class="bn-btn bn-btn--secondary" type="submit">اجرای پایش SLA</button></form>';
        $content=$this->head('کارتابل وظایف','وظایف عملیاتی، بانک و مالی با مهلت و SLA قابل پیگیری.',$actions).$this->takeFlash().'<div class="bn-stats">'.$this->stat('وظایف قابل مشاهده',$summary['accessible']??'—','بر اساس سطح دسترسی').$this->stat('وظایف من',$summary['mine']??'—','ارجاع مستقیم').$this->stat('در حال انجام',$summary['in_progress']??'—','وظایف شروع‌شده').$this->stat('عبور از SLA',$summary['overdue']??'—','نیازمند اقدام',true).'</div>'
            .'<section class="bn-section"><form class="bn-filterbar" method="get" action="/loans/tasks"><div class="bn-field"><label class="bn-label">نمایش</label><select name="scope">'.$this->option('mine','وظایف من',$scope).$this->option('unassigned','بدون مسئول',$scope).$this->option('','همه قابل مشاهده',$scope).'</select></div><div class="bn-field"><label class="bn-label">صف</label><select name="queue"><option value="">همه صف‌ها</option>'.$this->option('operations','عملیات',$queue).$this->option('bank','بانک',$queue).$this->option('finance','مالی',$queue).'</select></div><div class="bn-field"><label class="bn-label">وضعیت</label><select name="status"><option value="">فعال/همه مجاز</option>'.$this->option('open','باز',$status).$this->option('in_progress','در حال انجام',$status).$this->option('completed','تکمیل‌شده',$status).$this->option('cancelled','لغوشده',$status).$this->option('all','همه',$status).'</select></div><div class="bn-field"><label class="bn-label">جستجو</label><input name="search" value="'.Renderer::e($search).'" placeholder="شماره پرونده / نام / عنوان"></div><label class="bn-check"><input type="checkbox" name="overdue" value="1"'.($overdue?' checked':'').'><span>فقط SLA عبورکرده</span></label>'.($requestId!==''?'<input type="hidden" name="request_id" value="'.Renderer::e($requestId).'">':'').'<div class="bn-filter-actions"><button class="bn-btn bn-btn--primary" type="submit">اعمال فیلتر</button><a class="bn-btn bn-btn--secondary" href="/loans/tasks?scope=mine">پاک‌کردن</a></div></form></section>'
            .'<section class="bn-section"><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>وظیفه</th><th>پرونده</th><th>صف</th><th>وضعیت</th><th>اولویت</th><th>مهلت</th></tr></thead><tbody>'.$rows.'</tbody></table></div>'.$this->pagination('/loans/tasks',(int)($data['page']??$page),(int)($data['per_page']??25),(int)($data['total']??count($items)),['scope'=>$scope,'status'=>$status,'queue'=>$queue,'search'=>$search,'request_id'=>$requestId,'overdue'=>$overdue?'1':'']).'</section>';
        echo Renderer::page('کارتابل وظایف',$content,$this->identity,'tasks');
    }

    private function taskReport(): void
    {
        $days=max(1,min(365,(int)($_GET['days']??30)));$r=$this->client->get('/api/v1/tasks/report',$this->bearer,['days'=>$days]);if(!$r->ok()){$this->errorPage('گزارش گردش‌کار',$r);return;}$d=is_array($r->json['data']??null)?$r->json['data']:[];
        $queueRows='';foreach((array)($d['queues']??[])as$x){if(!is_array($x))continue;$queueRows.='<tr><td>'.$this->taskQueue((string)($x['queue_code']??'')).'</td><td>'.PersianDateTime::digits((string)($x['total']??0)).'</td><td>'.PersianDateTime::digits((string)($x['active']??0)).'</td><td>'.PersianDateTime::digits((string)($x['completed']??0)).'</td><td>'.PersianDateTime::digits((string)($x['overdue']??0)).'</td><td>'.PersianDateTime::digits((string)($x['avg_completion_minutes']??'—')).'</td></tr>';}$queueRows=$queueRows?:'<tr><td colspan="6">داده‌ای وجود ندارد.</td></tr>';
        $assigneeRows='';foreach((array)($d['assignees']??[])as$x){if(!is_array($x))continue;$assigneeRows.='<tr><td>'.Renderer::e((string)($x['assigned_user_id']??'—')).'</td><td>'.PersianDateTime::digits((string)($x['total']??0)).'</td><td>'.PersianDateTime::digits((string)($x['active']??0)).'</td><td>'.PersianDateTime::digits((string)($x['completed']??0)).'</td><td>'.PersianDateTime::digits((string)($x['overdue']??0)).'</td><td>'.PersianDateTime::digits((string)($x['avg_completion_minutes']??'—')).'</td></tr>';}$assigneeRows=$assigneeRows?:'<tr><td colspan="6">داده‌ای وجود ندارد.</td></tr>';
        $stageRows='';foreach((array)($d['current_stages']??[])as$x){if(!is_array($x))continue;$stageRows.='<tr><td>'.Renderer::e($this->requestStatusLabel((string)($x['status']??''))).'</td><td>'.PersianDateTime::digits((string)($x['cases']??0)).'</td><td>'.PersianDateTime::digits((string)($x['avg_current_stage_minutes']??'—')).'</td><td>'.PersianDateTime::digits((string)($x['max_current_stage_minutes']??'—')).'</td></tr>';}$stageRows=$stageRows?:'<tr><td colspan="4">داده‌ای وجود ندارد.</td></tr>';$sla=is_array($d['sla_events']??null)?$d['sla_events']:[];
        $content=$this->head('گزارش گردش‌کار','نمای عملیاتی تسک‌ها، SLA و زمان ماندگاری مرحله‌ها.','<a class="bn-btn bn-btn--secondary" href="/loans/tasks?scope=mine">بازگشت به کارتابل</a>').$this->takeFlash().'<section class="bn-section"><form class="bn-filterbar" method="get" action="/loans/tasks/report"><div class="bn-field"><label class="bn-label">بازه گزارش</label><select name="days">'.$this->option('7','۷ روز',(string)$days).$this->option('30','۳۰ روز',(string)$days).$this->option('90','۹۰ روز',(string)$days).$this->option('180','۱۸۰ روز',(string)$days).$this->option('365','۳۶۵ روز',(string)$days).'</select></div><div class="bn-filter-actions"><button class="bn-btn bn-btn--primary" type="submit">نمایش</button></div></form></section><div class="bn-stats">'.$this->stat('هشدار SLA',$sla['warnings']??0,'در بازه انتخابی').$this->stat('Escalation SLA',$sla['escalations']??0,'در بازه انتخابی',true).'</div><section class="bn-section"><h2 class="bn-sectiontitle">عملکرد صف‌ها</h2><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>صف</th><th>کل</th><th>فعال</th><th>تکمیل</th><th>عبور SLA</th><th>میانگین تکمیل (دقیقه)</th></tr></thead><tbody>'.$queueRows.'</tbody></table></div></section><section class="bn-section"><h2 class="bn-sectiontitle">عملکرد کارشناسان</h2><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>شناسه کاربر</th><th>کل</th><th>فعال</th><th>تکمیل</th><th>عبور SLA</th><th>میانگین تکمیل (دقیقه)</th></tr></thead><tbody>'.$assigneeRows.'</tbody></table></div></section><section class="bn-section"><h2 class="bn-sectiontitle">زمان ماندگاری مرحله جاری پرونده‌ها</h2><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>مرحله</th><th>تعداد</th><th>میانگین (دقیقه)</th><th>بیشترین (دقیقه)</th></tr></thead><tbody>'.$stageRows.'</tbody></table></div></section>';
        echo Renderer::page('گزارش گردش‌کار',$content,$this->identity,'tasks');
    }

    private function slaSettings(): void
    {
        $r=$this->client->get('/api/v1/tasks/sla/policies',$this->bearer);if(!$r->ok()){$this->errorPage('تنظیمات SLA',$r);return;}$items=is_array($r->json['data']['items']??null)?$r->json['data']['items']:[];$cards='';foreach($items as$p){if(!is_array($p))continue;$code=(string)($p['code']??'');$cards.='<form method="post" action="/loans/tasks/settings" class="bn-section"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="code" value="'.Renderer::e($code).'"><div class="bn-sectionhead"><div><h2 class="bn-sectiontitle">'.Renderer::e((string)($p['name']??$code)).'</h2><p class="bn-sectiondesc">'.Renderer::e((string)($p['task_code']??'')).' · '.$this->taskQueue((string)($p['queue_code']??'')).'</p></div></div><div class="bn-grid bn-grid--3">'.$this->field('مهلت انجام (دقیقه)','due_minutes',(string)($p['due_minutes']??'1440'),'number').$this->field('هشدار قبل از سررسید (دقیقه)','warning_minutes',(string)($p['warning_minutes']??'180'),'number').$this->field('Escalation بعد از سررسید (دقیقه)','escalation_minutes',(string)($p['escalation_minutes']??'60'),'number').'</div><label class="bn-check"><input type="checkbox" name="apply_existing" value="1"><span>مهلت تسک‌های فعال موجود نیز بر اساس تاریخ ایجاد دوباره محاسبه شود</span></label><div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit">ذخیره سیاست SLA</button></div></form>';}
        $content=$this->head('تنظیمات SLA','مهلت، زمان هشدار و Escalation هر نوع وظیفه.','<a class="bn-btn bn-btn--secondary" href="/loans/tasks?scope=mine">بازگشت به کارتابل</a>').$this->takeFlash().$cards;echo Renderer::page('تنظیمات SLA',$content,$this->identity,'tasks');
    }

    private function saveSlaSettings(): void
    {
        if(!Csrf::valid((string)($_POST['_csrf']??''))){$this->flash('error','اعتبار فرم منقضی شده است.');header('Location: /loans/tasks/settings',true,303);exit;}$code=trim((string)($_POST['code']??''));$payload=['due_minutes'=>(int)($_POST['due_minutes']??0),'warning_minutes'=>(int)($_POST['warning_minutes']??0),'escalation_minutes'=>(int)($_POST['escalation_minutes']??0),'apply_existing'=>isset($_POST['apply_existing'])];$r=$this->client->patch('/api/v1/tasks/sla/policies/'.rawurlencode($code),$this->bearer,$payload);$this->flash($r->ok()?'success':'error',$r->ok()?'سیاست SLA ذخیره شد.':$this->apiMessage($r));header('Location: /loans/tasks/settings',true,303);exit;
    }

    private function taskDetail(string $id): void
    {
        $r=$this->client->get('/api/v1/tasks/'.$id,$this->bearer);if(!$r->ok()){$this->errorPage('جزئیات تسک',$r);return;}$task=is_array($r->json['data']['task']??null)?$r->json['data']['task']:[];$history=is_array($task['history']??null)?$task['history']:[];$me=(string)($this->identity['user']['id']??'');$assigned=(string)($task['assigned_user_id']??'');$active=in_array((string)($task['status']??''),['open','in_progress'],true);$canWork=$this->can((string)($task['required_permission']??''))||$this->can('loans.requests.workflow_admin');
        $actions='';if($active&&$canWork){if($assigned==='')$actions.=$this->taskPostButton($id,'claim','برداشتن تسک','bn-btn--primary');elseif($assigned===$me){if((string)$task['status']==='open')$actions.=$this->taskPostButton($id,'start','شروع انجام','bn-btn--primary');$actions.=$this->taskPostButton($id,'complete','تکمیل تسک','bn-btn--primary',true).$this->taskPostButton($id,'release','آزاد کردن','bn-btn--secondary',true);}}
        if($active&&$this->can('loans.requests.workflow_admin'))$actions.='<form method="post" action="/loans/tasks/'.$id.'/reassign" class="bn-task-reassign"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><div class="bn-field"><label class="bn-label">شناسه کاربر جدید</label><input name="user_id" value="'.Renderer::e($assigned).'" placeholder="خالی = بدون مسئول"></div><div class="bn-field"><label class="bn-label">دلیل</label><input name="note" placeholder="دلیل ارجاع مجدد"></div><button class="bn-btn bn-btn--secondary" type="submit">ارجاع مجدد</button></form>'.$this->taskPostButton($id,'cancel','لغو تسک','bn-btn--secondary',true);
        $historyRows='';foreach($history as$h){if(!is_array($h))continue;$historyRows.='<tr><td>'.Renderer::e($this->taskActionLabel((string)($h['action']??''))).'</td><td>'.Renderer::e((string)($h['actor_user_id']??'سیستم')).'</td><td>'.Renderer::e((string)($h['note']??'—')).'</td><td>'.Renderer::e(PersianDateTime::fromIso((string)($h['created_at']??''),false)??'—').'</td></tr>';}$historyRows=$historyRows?:'<tr><td colspan="4">تاریخچه‌ای ثبت نشده است.</td></tr>';
        $due=(string)($task['due_at']??'');$content=$this->head('جزئیات وظیفه',Renderer::e((string)($task['title']??'')),'<a class="bn-btn bn-btn--secondary" href="/loans/tasks?scope=mine">بازگشت به کارتابل</a><a class="bn-btn bn-btn--primary" href="/loans/requests/'.Renderer::e((string)($task['request_id']??'')).'">مشاهده پرونده</a>').$this->takeFlash().'<section class="bn-section"><div class="bn-detail-grid"><div><span>شماره پرونده</span><strong>'.Renderer::e((string)($task['request_no']??'—')).'</strong></div><div><span>متقاضی</span><strong>'.Renderer::e((string)($task['full_name']??'—')).'</strong></div><div><span>صف</span><strong>'.$this->taskQueue((string)($task['queue_code']??'')).'</strong></div><div><span>وضعیت</span><strong>'.$this->taskStatus((string)($task['status']??'')).'</strong></div><div><span>اولویت</span><strong>'.$this->taskPriority((string)($task['priority']??'normal')).'</strong></div><div><span>مهلت</span><strong>'.Renderer::e($due!==''?(PersianDateTime::fromIso($due,false)??$due):'—').'</strong></div><div><span>مسئول</span><strong>'.Renderer::e($assigned!==''?$assigned:'بدون مسئول').'</strong></div><div><span>مجوز لازم</span><strong>'.Renderer::e((string)($task['required_permission']??'—')).'</strong></div></div>'.($actions!==''?'<div class="bn-task-actions">'.$actions.'</div>':'').'</section><section class="bn-section"><div class="bn-sectionhead"><div><h2 class="bn-sectiontitle">تاریخچه تسک</h2><p class="bn-sectiondesc">تمام ارجاع‌ها، شروع، تکمیل و رویدادهای SLA.</p></div></div><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>رویداد</th><th>عامل</th><th>یادداشت</th><th>زمان</th></tr></thead><tbody>'.$historyRows.'</tbody></table></div></section>';
        echo Renderer::page('جزئیات تسک',$content,$this->identity,'tasks');
    }

    private function taskAction(string $id,string $action): void
    {
        if(!Csrf::valid((string)($_POST['_csrf']??''))){$this->flash('error','اعتبار فرم منقضی شده است.');header('Location: /loans/tasks/'.$id,true,303);exit;}$payload=['note'=>trim((string)($_POST['note']??''))];if($action==='reassign')$payload['user_id']=trim((string)($_POST['user_id']??''));$r=$this->client->post('/api/v1/tasks/'.$id.'/'.$action,$this->bearer,$payload);$this->flash($r->ok()?'success':'error',$r->ok()?'عملیات تسک ثبت شد.':$this->apiMessage($r));header('Location: /loans/tasks/'.$id,true,303);exit;
    }

    private function runSla(): void
    {
        if(!Csrf::valid((string)($_POST['_csrf']??''))){$this->flash('error','اعتبار فرم منقضی شده است.');header('Location: /loans/tasks?scope=mine',true,303);exit;}$r=$this->client->post('/api/v1/tasks/sla/run',$this->bearer,[]);$d=is_array($r->json['data']??null)?$r->json['data']:[];$msg=$r->ok()?'پایش SLA انجام شد؛ هشدار: '.(int)($d['warnings']??0).'، Escalation: '.(int)($d['escalations']??0):$this->apiMessage($r);$this->flash($r->ok()?'success':'error',$msg);header('Location: /loans/tasks?scope=mine',true,303);exit;
    }

    private function taskPostButton(string $id,string $action,string $label,string $class='bn-btn--secondary',bool $note=false):string
    { return'<form method="post" action="/loans/tasks/'.$id.'/'.$action.'" class="bn-inline-form"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'">'.($note?'<input name="note" class="bn-task-note" placeholder="یادداشت اختیاری">':'').'<button class="bn-btn '.$class.'" type="submit">'.Renderer::e($label).'</button></form>'; }
    private function taskStatus(string $v):string{return$this->status($v,['open'=>'باز','in_progress'=>'در حال انجام','completed'=>'تکمیل‌شده','cancelled'=>'لغوشده'][$v]??$v);}
    private function taskPriority(string $v):string{return'<span class="bn-task-priority bn-task-priority--'.Renderer::e($v).'">'.Renderer::e(['low'=>'کم','normal'=>'عادی','high'=>'زیاد','urgent'=>'فوری'][$v]??$v).'</span>';}
    private function taskQueue(string $v):string{return Renderer::e(['operations'=>'عملیات','bank'=>'بانک','finance'=>'مالی'][$v]??$v);}
    private function taskActionLabel(string $v):string{return['created'=>'ایجاد','claimed'=>'برداشت','started'=>'شروع','completed'=>'تکمیل','cancelled'=>'لغو','released'=>'آزادسازی','reassigned'=>'ارجاع مجدد','sla_warning'=>'هشدار SLA','sla_escalated'=>'Escalation SLA'][$v]??$v;}

    private function workflowAction(string $id,string $action): void
    {
        if(!Csrf::valid((string)($_POST['_csrf']??''))){$this->flash('error','اعتبار فرم منقضی شده است.');header('Location: /loans/requests/'.$id,true,303);exit;}
        $endpoint='';$payload=[];$permission='loans.requests.read';
        if($action==='messages'){$permission='loans.requests.message';$endpoint='messages';$payload=['message'=>trim((string)($_POST['message']??'')),'visibility'=>'participants'];}
        elseif($action==='notes'){$permission='loans.requests.internal_notes';$endpoint='notes';$payload=['comment'=>trim((string)($_POST['comment']??'')),'operation_key'=>trim((string)($_POST['operation_key']??''))];}
        elseif($action==='document-receipt'){$permission='loans.requests.document_receipt';$endpoint='document-receipt';$payload=['status'=>(string)($_POST['status']??''),'received_at'=>(string)($_POST['received_at']??''),'note'=>(string)($_POST['note']??'')];}
        elseif($action==='bank-review'){$permission='loans.requests.bank_review';$endpoint='bank-review';$payload=['status'=>(string)($_POST['status']??''),'reason'=>(string)($_POST['reason']??''),'note'=>(string)($_POST['note']??''),'external_reference'=>(string)($_POST['external_reference']??'')];}
        elseif($action==='grant'){$permission='loans.requests.grant';$endpoint='grant';$payload=['granted_amount'=>(string)($_POST['granted_amount']??''),'granted_at'=>(string)($_POST['granted_at']??''),'loan_number'=>(string)($_POST['loan_number']??''),'contract_number'=>(string)($_POST['contract_number']??'')];}
        elseif($action==='bank-settlement'){$permission='loans.requests.bank_settlement';$endpoint='bank-settlement';$payload=['status'=>(string)($_POST['status']??''),'settled_at'=>(string)($_POST['settled_at']??''),'note'=>(string)($_POST['note']??'')];}
        elseif($action==='merchant-settlement'){$permission='loans.requests.merchant_settlement';$endpoint='merchant-settlement';$payload=['status'=>(string)($_POST['status']??''),'settlement_date'=>(string)($_POST['settlement_date']??''),'cycle_id'=>(string)($_POST['cycle_id']??''),'note'=>(string)($_POST['note']??'')];}
        elseif($action==='financier-payment'){$permission='loans.requests.finance';$endpoint='financier-payment';$payload=['status'=>(string)($_POST['status']??''),'note'=>(string)($_POST['note']??'')];}
        elseif($action==='assignment'){$permission='loans.requests.workflow_admin';$endpoint='assignment';$payload=['type'=>(string)($_POST['type']??'bank_expert'),'user_id'=>(string)($_POST['user_id']??''),'reason'=>(string)($_POST['reason']??'')];}
        if($action==='assignment' && !($this->can('loans.requests.workflow_admin') || $this->can('loans.requests.assign_bank'))){$this->flash('error','دسترسی لازم برای این عملیات وجود ندارد.');header('Location: /loans/requests/'.$id,true,303);exit;}
        if($action!=='assignment' && !$this->can($permission)){$this->flash('error','دسترسی لازم برای این عملیات وجود ندارد.');header('Location: /loans/requests/'.$id,true,303);exit;}
        if($action==='notes'){
            if(IniSize::requestExceedsPostLimit()){$this->flash('error','حجم فایل‌ها از محدودیت PHP بیشتر است.');header('Location: /loans/requests/'.$id,true,303);exit;}
            $files=$_FILES['note_files']??null;$uploads=[];
            if(is_array($files)&&is_array($files['name']??null)){
                $names=(array)$files['name'];if(count($names)>5){$this->flash('error','حداکثر پنج پیوست مجاز است.');header('Location: /loans/requests/'.$id,true,303);exit;}
                foreach(array_keys($names) as $i){
                    $file=['name'=>(string)($files['name'][$i]??''),'tmp_name'=>(string)($files['tmp_name'][$i]??''),'size'=>(int)($files['size'][$i]??0),'error'=>(int)($files['error'][$i]??UPLOAD_ERR_NO_FILE)];
                    if($file['error']===UPLOAD_ERR_NO_FILE)continue;
                    if($file['error']!==UPLOAD_ERR_OK||$file['size']>10485760||!$this->can('documents.upload')||!$this->can('documents.view')||!$this->can('documents.download')||$this->documents===null){$this->flash('error','بارگذاری پیوست مجاز یا کامل نیست.');header('Location: /loans/requests/'.$id,true,303);exit;}
                    $uploads[]=$file;
                }
            }
            $payload['attachment_document_ids']=[];
            foreach($uploads as $file){
                $response=$this->documents->upload('loan_request',$id,$this->bearer,$file,['document_type'=>'internal_note_attachment','relation_code'=>'internal_note']);
                $docId=(string)($response->json['data']['document']['id']??'');
                if(!$response->ok()||preg_match('/^[a-f0-9]{26}$/D',$docId)!==1){$this->flash('error','بارگذاری یکی از پیوست‌ها ناموفق بود؛ یادداشت ثبت نشد. فایل‌های بارگذاری‌شده قبلی برای پاک‌سازی مدیریتی باقی می‌مانند. '.$this->apiMessage($response));header('Location: /loans/requests/'.$id,true,303);exit;}
                $payload['attachment_document_ids'][]=$docId;
            }
        }
        $r=$this->client->post('/api/v1/requests/'.$id.'/'.$endpoint,$this->bearer,$payload);$this->flash($r->ok()?'success':'error',$r->ok()?'عملیات با موفقیت ثبت شد.':$this->apiMessage($r));header('Location: /loans/requests/'.$id,true,303);exit;
    }

    private function internalNoteHistory(string $requestId,string $noteId):void
    {
        if(!$this->can('loans.requests.internal_notes')){$this->deny('تاریخچه یادداشت');return;}
        $r=$this->client->get('/api/v1/requests/'.$requestId.'/notes/'.$noteId.'/history',$this->bearer);
        if(!$r->ok()){$this->errorPage('تاریخچه یادداشت',$r);return;}
        $items=is_array($r->json['data']['items']??null)?$r->json['data']['items']:[];
        $rows='';foreach($items as $revision){if(!is_array($revision))continue;$rows.='<tr><td>'.(int)($revision['previous_version']??0).'</td><td>'.nl2br(Renderer::e((string)($revision['previous_comment']??''))).'</td><td>'.Renderer::e(PersianDateTime::fromIso((string)($revision['created_at']??''),false)??'—').'</td><td>'.Renderer::e((string)($revision['changed_by_user_id']??'—')).'</td></tr>';}
        if($rows==='')$rows='<tr><td colspan="4">هنوز اصلاحی ثبت نشده است.</td></tr>';
        $body=$this->head('تاریخچه اصلاح یادداشت','نسخه‌های پیشین فقط برای کاربران دارای مجوز یادداشت داخلی قابل مشاهده‌اند.','<a class="bn-btn bn-btn--secondary" href="/loans/requests/'.$requestId.'">بازگشت به پرونده</a>').'<section class="bn-section"><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>نسخه قبلی</th><th>متن قبلی</th><th>زمان اصلاح</th><th>ویرایش‌کننده</th></tr></thead><tbody>'.$rows.'</tbody></table></div></section>';
        echo Renderer::page('تاریخچه یادداشت',$body,$this->identity,'loans');
    }

    private function editInternalNote(string $requestId,string $noteId):void
    {
        if(!Csrf::valid((string)($_POST['_csrf']??''))||!$this->can('loans.requests.internal_notes')){$this->flash('error','مجوز یا اعتبار فرم معتبر نیست.');header('Location: /loans/requests/'.$requestId,true,303);exit;}
        $r=$this->client->patch('/api/v1/requests/'.$requestId.'/notes/'.$noteId,$this->bearer,['comment'=>(string)($_POST['comment']??''),'expected_version'=>(int)($_POST['expected_version']??0)]);
        $this->flash($r->ok()?'success':'error',$r->ok()?'یادداشت اصلاح و تاریخچه ثبت شد.':$this->apiMessage($r));header('Location: /loans/requests/'.$requestId,true,303);exit;
    }

    private function dateTimeLocal(string $value): string
    {
        $value = trim($value);
        if ($value === '') return '';
        $timestamp = strtotime($value);
        return $timestamp === false ? '' : date('Y-m-d\TH:i', $timestamp);
    }

    private function catalogLabel(array $catalogs,string $name,string $value): string
    { $items=is_array($catalogs[$name]??null)?$catalogs[$name]:[];return(string)($items[$value]??$value); }

    private function catalogOptions(array $catalogs,string $name,string $selected): string
    { $items=is_array($catalogs[$name]??null)?$catalogs[$name]:[];$out='';foreach($items as$k=>$v)$out.='<option value="'.Renderer::e((string)$k).'"'.((string)$k===$selected?' selected':'').'>'.Renderer::e((string)$v).'</option>';return$out; }

    private function can(string $permission): bool
    {
        $roles = is_array($this->identity['roles'] ?? null) ? $this->identity['roles'] : [];
        $permissions = is_array($this->identity['permissions'] ?? null) ? $this->identity['permissions'] : [];
        return in_array('super_admin', $roles, true) || in_array($permission, $permissions, true);
    }

    private function deny(string $title): bool
    {
        $content = $this->head($title, 'این بخش برای نقش کاربری فعلی فعال نیست.')
            . $this->alert('error', 'دسترسی محدود است', 'برای انجام این عملیات مجوز لازم به نقش کاربری شما اختصاص داده نشده است.')
            . '<div class="bn-form-actions"><a class="bn-btn bn-btn--primary" href="/dashboard">بازگشت به داشبورد</a></div>';
        echo Renderer::page($title, $content, $this->identity, 'loans');
        return true;
    }

    private function head(string $title, string $subtitle, string $actions=''): string
    { return '<div class="bn-pagehead"><div class="bn-pagehead-main"><div class="bn-eyebrow">مدیریت تسهیلات</div><h1 class="bn-title">'.Renderer::e($title).'</h1><p class="bn-subtitle">'.Renderer::e($subtitle).'</p></div>'.($actions !== '' ? '<div class="bn-actions">'.$actions.'</div>' : '').'</div>'; }

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

    private function occupationField(string $value): string
    {
        return OccupationField::render($value);
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
            $monthOptions .= '<option value="'.Renderer::e((string)$number).'"'.((string)$number === $month ? ' selected' : '').'>'.Renderer::e(PersianDateTime::digits((string)$number).' - '.$label).'</option>';
        }
        $dayOptions = '<option value="">روز</option>';
        for ($d = 1; $d <= 31; $d++) {
            $dd = (string)$d;
            $dayOptions .= '<option value="'.$dd.'"'.($dd === $day ? ' selected' : '').'>'.Renderer::e(PersianDateTime::digits($dd)).'</option>';
        }
        return '<div class="bn-field bn-field--birth"><label class="bn-label">تاریخ تولد شمسی <span class="bn-required">*</span></label><div class="bn-select-grid">'
            . '<select name="birth_day" required>'.$dayOptions.'</select>'
            . '<select name="birth_month" required>'.$monthOptions.'</select>'
            . '<select name="birth_year" required>'.$yearOptions.'</select>'
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


    private function percentField(string $label, string $name, string $value, bool $required=true, string $helper=''): string
    { return $this->field($label.' (%)', $name, $value, 'number', 'decimal', $helper, $required); }

    private function area(string $label, string $name, string $value): string
    { return '<div class="bn-field"><label class="bn-label">'.Renderer::e($label).'</label><textarea name="'.Renderer::e($name).'" rows="4">'.Renderer::e($value).'</textarea></div>'; }

    private function detail(string $label, string $value): string
    { return '<div class="bn-detail-item"><span>'.Renderer::e($label).'</span><strong>'.Renderer::e(PersianDateTime::digits($value)).'</strong></div>'; }

    private function option(string $value, string $label, string $selected): string
    { return '<option value="'.Renderer::e($value).'"'.($value === $selected ? ' selected' : '').'>'.Renderer::e($label).'</option>'; }

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
            'national_card'=>'کارت ملی','national_card_front'=>'کارت ملی (رو)','national_card_back'=>'کارت ملی (پشت)','birth_certificate'=>'شناسنامه','portrait'=>'تصویر چهره','sana_certificate'=>'گواهی ثبت ثنا','employment_certificate'=>'گواهی اشتغال','business_license'=>'جواز کسب','activity_license'=>'مجوز فعالیت','payslip'=>'فیش حقوقی','bank_statement'=>'گردش حساب','account_statement'=>'گردش حساب ۳ ماهه','tax_return'=>'اظهارنامه / اطلاعات مالیاتی','residence_document'=>'سند یا اجاره‌نامه محل سکونت','workplace_document'=>'سند یا اجاره‌نامه محل کار','signature_certificate'=>'گواهی امضا','primary_check'=>'چک اصلی','secondary_check'=>'چک تکمیلی','blank_check_image'=>'تصویر چک صیادی','guarantor_identity'=>'مدارک هویتی ضامن','guarantor_income'=>'مدارک درآمدی ضامن','guarantor_check'=>'چک ضامن','credit_report'=>'گزارش اعتبارسنجی','bank_validation'=>'فایل اعتبارسنجی بانک','contract'=>'قرارداد','signed_contract'=>'قرارداد امضا شده','invoice'=>'فاکتور / پیش‌فاکتور','supplemental'=>'مدارک تکمیلی','completed_check'=>'چک تکمیل شده','completed_check_files'=>'چک‌های تکمیل شده','signed_contract_files'=>'قرارداد امضا شده','signed_invoice_files'=>'فاکتور امضا شده','other_completed_files'=>'سایر مدارک نهایی','additional_documents'=>'مدارک تکمیلی','case_receipt'=>'رسید واریزی پرونده','transfer_receipt'=>'رسید انتقال وجه','settlement_receipt'=>'رسید سیکل تسویه','bank_settlement_receipt'=>'رسید تسویه بانک با بابانوئل','cooperation_identity'=>'مدارک هویتی همکاری','cooperation_license'=>'مجوز همکاری / فعالیت','cooperation_contract'=>'قرارداد همکاری',
        ];
    }

    /** @return array<string,string> */
    private function occupationLabels(): array
    { return OccupationField::LABELS; }

    /** @return array<string,string> */
    private function visibilityLabels(): array
    { return ['merchant_request'=>'فرم ثبت درخواست پذیرنده','applicant_request'=>'فرم ثبت درخواست متقاضی','calculator'=>'محاسبه‌گر']; }

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
            . '<div class="bn-form-actions"><a class="bn-btn bn-btn--primary" href="/loans">بازگشت به تسهیلات</a></div>';
        echo Renderer::page($title, $content, $this->identity, 'loans');
    }
    private function isAdminForCorrections(): bool
    {
        $roles=(array)($this->identity['roles']??[]);
        return in_array('super_admin',$roles,true)||in_array('admin',$roles,true);
    }
    private function resolveLegacyRequest(): void
    {
        $legacy=trim((string)($_GET['legacy_id']??''));
        if(!preg_match('/^[1-9][0-9]{0,15}$/',$legacy)){ $this->flash('error','شناسه قدیمی معتبر نیست.');header('Location: /loans/requests',true,303);exit; }
        $r=$this->client->get('/api/v1/admin/requests/resolve-legacy',$this->bearer,['legacy_id'=>$legacy]);
        $id=(string)($r->json['data']['request']['id']??'');
        if($r->ok()&&preg_match('/^[a-f0-9]{26}$/',$id)){header('Location: /loans/requests/'.$id.'/correct',true,303);exit;}
        $this->flash('error',$r->ok()?'پرونده‌ای با شناسه قدیمی پیدا نشد.':$this->apiMessage($r));header('Location: /loans/requests',true,303);exit;
    }
    private function correctionForm(string $id): void
    {
        $r=$this->client->get('/api/v1/admin/requests/'.$id.'/profile-corrections',$this->bearer);
        if(!$r->ok()){$this->errorPage('اصلاح مدیریتی پرونده',$r);return;}
        $request=(array)($r->json['data']['request']??[]);$history=(array)($r->json['data']['corrections']??[]);
        $fields=['full_name'=>'نام و نام خانوادگی','father_name'=>'نام پدر','certificate_number'=>'شماره شناسنامه','national_code'=>'کد ملی','mobile_number'=>'موبایل','phone_number'=>'تلفن ثابت','birth_date'=>'تاریخ تولد (میلادی YYYY-MM-DD)','occupation'=>'شغل','residential_address'=>'آدرس سکونت','postal_code'=>'کد پستی','work_address'=>'آدرس محل کار'];
        $form='';foreach($fields as $key=>$label){$v=(string)($request[$key]??'');$form.='<div class="bn-field"><label class="bn-label">'.Renderer::e($label).'</label><input name="fields['.Renderer::e($key).']" value="'.Renderer::e($v).'" autocomplete="off"></div>';}
        $rows='';foreach($history as $item){if(!is_array($item))continue;$changed=(array)($item['changed_fields']??[]);$rows.='<tr><td>'.Renderer::e((string)($item['actor_user_id']??'')).'</td><td>'.Renderer::e(implode('، ',$changed)).'</td><td>'.Renderer::e((string)($item['reason']??'')).'</td><td>'.Renderer::e((string)($item['created_at']??'')).'</td></tr>';}
        if($rows==='')$rows='<tr><td colspan="4">اصلاح قبلی ثبت نشده است.</td></tr>';
        $content=$this->head('اصلاح مدیریتی پرونده '.(string)($request['request_no']??''),'فقط اطلاعات هویتی و تماس تغییر می‌کنند؛ ارقام مالی، وضعیت پرونده و قراردادها ثابت می‌مانند.','<a class="bn-btn bn-btn--secondary" href="/loans/requests/'.$id.'">بازگشت</a>').$this->takeFlash();
        $content.='<section class="bn-section"><form method="post" action="/loans/requests/'.$id.'/correct" data-submit-lock="1"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="row_version" value="'.(int)($request['row_version']??0).'"><div class="bn-grid bn-grid--2">'.$form.'</div><div class="bn-field"><label class="bn-label">دلیل اصلاح (الزامی)</label><textarea name="reason" minlength="10" maxlength="1000" required></textarea></div><label><input type="checkbox" name="acknowledge_signed" value="1"> تأیید می‌کنم سندهای امضاشده قبلی دست‌نخورده می‌مانند و اصلاح فقط در پروفایل انجام می‌شود.</label><div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit">ثبت اصلاح</button></div></form></section>';
        $content.='<section class="bn-section"><h2 class="bn-sectiontitle">تاریخچه اصلاحات</h2><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>مدیر</th><th>فیلدها</th><th>دلیل</th><th>زمان</th></tr></thead><tbody>'.$rows.'</tbody></table></div></section>';
        echo Renderer::page('اصلاح پرونده',$content,$this->identity,'loans');
    }
    private function correctRequest(string $id): void
    {
        if(!Csrf::valid((string)($_POST['_csrf']??''))){$this->flash('error','اعتبار فرم منقضی شده است.');header('Location: /loans/requests/'.$id.'/correct',true,303);exit;}
        $r=$this->client->post('/api/v1/admin/requests/'.$id.'/profile-corrections',$this->bearer,['fields'=>is_array($_POST['fields']??null)?$_POST['fields']:[],'reason'=>trim((string)($_POST['reason']??'')),'row_version'=>(int)($_POST['row_version']??0),'acknowledge_signed'=>isset($_POST['acknowledge_signed'])]);
        $this->flash($r->ok()?'success':'error',$r->ok()?'اصلاح پروفایل ثبت شد؛ قراردادهای قبلی تغییر نکردند.':$this->apiMessage($r));
        header('Location: /loans/requests/'.$id.'/correct',true,303);exit;
    }

}
