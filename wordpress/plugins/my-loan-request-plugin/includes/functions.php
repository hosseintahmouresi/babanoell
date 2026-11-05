<?php
if (!defined('ABSPATH')) {
    exit;
}

function mlr_render_user_requests_table($title, $allowed_statuses = [], $table_view = 'default') {
    if (!is_user_logged_in()) {
        return '<p>برای مشاهده این بخش، لطفاً ابتدا وارد شوید.</p>';
    }

    wp_enqueue_style('dashicons');
    
    $version = defined('MLR_VERSION') ? MLR_VERSION : '8.4.6.24';
    wp_enqueue_script('mlr-file-upload-helper', MLR_PLUGIN_URL . 'assets/js/mlr-file-upload-helper.js', [], $version, true);
    wp_enqueue_script('mlr-guarantor-form', MLR_PLUGIN_URL . 'assets/js/mlr-guarantor-form.js', ['jquery', 'mlr-file-upload-helper'], $version, true);
    wp_enqueue_script('mlr-dashboard-document-upload', MLR_PLUGIN_URL . 'assets/js/mlr-dashboard-document-upload.js', ['jquery', 'mlr-file-upload-helper'], $version, true);
    wp_enqueue_style('mlr-contract-print-css', MLR_PLUGIN_URL . 'assets/css/contract-print.css', [], $version);
    wp_enqueue_script('mlr-contract-print-js', MLR_PLUGIN_URL . 'assets/js/mlr-contract-print.js', [], $version, true);
    wp_enqueue_script('mlr-user-requests-js', MLR_PLUGIN_URL . 'assets/js/mlr-user-requests.js', ['jquery', 'mlr-jalali-datepicker', 'mlr-dashboard-document-upload', 'mlr-guarantor-form', 'mlr-contract-print-js'], $version, true);
    wp_localize_script('mlr-user-requests-js', 'mlr_ajax_obj', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'filter_nonce'    => wp_create_nonce('mlr_user_requests_nonce'),
        'details_nonce'   => wp_create_nonce('mlr_frontend_nonce'),
        'cycle_nonce'     => wp_create_nonce('mlr_user_cycle_nonce'),
        'allowed_statuses' => $allowed_statuses,
        'table_view'      => $table_view
    ]);
    
    wp_enqueue_style('mlr-frontend-css', MLR_PLUGIN_URL . 'assets/css/frontend.css', [], $version);

    $unique_id = uniqid('mlr-table-');
    
    $ordered_statuses = [
        'در حال بررسی', 'نقص مدارک', 'رد شده', 'تأیید کارشناس', 
        'تکمیل مدارک', 'تأیید مدارک', 'اعطا شده',
    ];

    ob_start();
    ?>
    <div class="mlr-glass-dashboard mlr-user-requests <?php if ($title === 'سیکل‌های تسویه حساب') echo 'mlr-settlement-cycles'; ?>" id="<?php echo esc_attr($unique_id); ?>">
        <div class="mlr-glass-blob blob-1"></div>
        <div class="mlr-glass-blob blob-2"></div>
        
        <div class="mlr-dashboard-wrapper">
        <?php if(!empty($title)): ?>
            <h2 class="mlr-dashboard-title"><span class="dashicons dashicons-list-view"></span> <?php echo esc_html($title); ?></h2>
        <?php endif; ?>

        <div class="mlr-glass-card mlr-filter-section">
            <div class="mlr-filter-form">
                <input type="text" id="mlr-search" class="mlr-glass-input" placeholder="کدملی، متقاضی، شماره موبایل، شناسه">
                <select id="mlr-status" class="mlr-glass-input">
                    <option value="">وضعیت درخواست</option>
                    <?php foreach ($ordered_statuses as $status): ?>
                        <option value="<?php echo esc_attr($status); ?>"><?php echo esc_html($status); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="mlr-settlement-status" class="mlr-glass-input">
                    <option value="">وضعیت تسویه</option>
                    <option value="منتظر تعیین تاریخ">منتظر تعیین تاریخ</option>
                    <option value="در انتظار تسویه">در انتظار تسویه</option>
                    <option value="تسویه شده">تسویه شده</option>
                </select>
                <input type="text" class="jalali-datepicker mlr-glass-input" id="mlr-date-from" placeholder="از تاریخ..." autocomplete="off" readonly>
                <input type="text" class="jalali-datepicker mlr-glass-input" id="mlr-date-to" placeholder="تا تاریخ..." autocomplete="off" readonly>
                <button type="button" id="mlr-filter-btn" class="mlr-btn mlr-btn-primary">فیلتر</button>
            </div>
        </div>

        <div class="mlr-glass-card mlr-table-section">
            <div class="mlr-table-responsive-wrapper">
                <table class="mlr-requests-table" id="mlr-requests-table" data-table-view="<?php echo esc_attr($table_view); ?>">
                    <thead>
                        <tr>
                            <?php if ($table_view === 'granted'): ?>
                                <th>شناسه</th> <th>متقاضی</th> <th>کد ملی</th> <th>مبلغ درخواستی</th> <th>مبلغ تسهیلات</th> <th>طرح</th> <th>وضعیت</th> <th>تسویه</th> <th>اعتبار خرید</th> <th>تاریخ اعطا</th> <th>تاریخ تسویه</th> <th>عملیات</th>
                            <?php else: ?>
                                <th>شناسه</th> <th>متقاضی</th> <th>شماره موبایل</th> <th>کد ملی</th>
                                <th>مبلغ درخواستی</th> <th>مبلغ تسهیلات</th> <th>طرح</th> <th>وضعیت</th> <th>تسویه</th>
                                <th>تاریخ ثبت</th> <th>تاریخ تسویه</th> <th>عملیات</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="mlr-requests-table-body" class="mlr-requests-table-body">
                        <tr><td colspan="12" style="text-align: center; padding: 20px;">در حال بارگذاری درخواست‌ها...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        </div>
        
        <div class="mlr-modal-overlay" style="display:none;"></div>
        <div class="mlr-modal" style="display:none;">
            <button class="mlr-modal-close">&times;</button>
            <div class="mlr-modal-content"></div>
        </div>
    </div>


    <?php
    return ob_get_clean();
}

/**
 * Renders a unified expert dashboard (Bank Expert / Verified Docs).
 *
 * @param array $args Configuration arguments.
 * @return string HTML output.
 */
function mlr_render_expert_dashboard_html($args = []) {
    $defaults = [
        'id' => uniqid('mlr-dashboard-'),
        'title' => 'داشبورد',
        'icon' => 'dashicons-dashboard',
        'filter_btn_id' => 'mlr-filter-btn',
        'export_btn_id' => 'mlr-export-excel',
        'table_id' => 'mlr-requests-table',
        'table_body_id' => 'mlr-requests-table-body',
        'extra_classes' => '',
        'show_export' => true,
        'columns' => [
            'شناسه', 'متقاضی', 'شماره موبایل', 'کد ملی', 'مبلغ (ریال)', 'طرح', 'وضعیت', 'تاریخ ارجاع/تأیید', 'عملیات'
        ],
    ];
    $args = wp_parse_args($args, $defaults);

    ob_start();
    ?>
    <div class="mlr-glass-dashboard mlr-user-requests <?php echo esc_attr($args['extra_classes']); ?>" id="<?php echo esc_attr($args['id']); ?>" data-mlr-dashboard="true">
        <div class="mlr-glass-blob blob-1"></div>
        <div class="mlr-glass-blob blob-2"></div>
        
        <div class="mlr-dashboard-wrapper">
            <h2 class="mlr-dashboard-title"><span class="dashicons <?php echo esc_attr($args['icon']); ?>"></span> <?php echo esc_html($args['title']); ?></h2>
        
            <div class="mlr-glass-card mlr-filter-section">
                <div class="mlr-filter-form">
                    <?php if (!empty($args['show_export'])): ?>
                        <button type="button" class="mlr-btn mlr-btn-secondary" id="<?php echo esc_attr($args['export_btn_id']); ?>">خروجی اکسل</button>
                    <?php endif; ?>
                    <input type="text" id="mlr-search" class="mlr-glass-input" placeholder="کدملی، متقاضی، شماره موبایل، شناسه">
                    <input type="text" class="jalali-datepicker mlr-glass-input" id="mlr-date-from" placeholder="از تاریخ..." autocomplete="off">
                    <input type="text" class="jalali-datepicker mlr-glass-input" id="mlr-date-to" placeholder="تا تاریخ..." autocomplete="off">
                    <button type="button" id="<?php echo esc_attr($args['filter_btn_id']); ?>" class="mlr-btn mlr-btn-primary">فیلتر</button>
                </div>
            </div>

            <div class="mlr-glass-card mlr-table-section">
                <div class="mlr-table-responsive-wrapper">
                    <table class="mlr-requests-table" id="<?php echo esc_attr($args['table_id']); ?>">
                        <thead>
                            <tr>
                                <?php foreach ((array) $args['columns'] as $column_label): ?>
                                    <th><?php echo esc_html($column_label); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody id="<?php echo esc_attr($args['table_body_id']); ?>" class="mlr-requests-table-body">
                            <tr><td colspan="<?php echo esc_attr(max(1, count((array) $args['columns']))); ?>" style="text-align: center; padding: 20px;">در حال بارگذاری درخواست‌ها...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="mlr-modal-overlay" style="display:none;"></div>
        <div class="mlr-modal" style="display:none;">
            <button class="mlr-modal-close">&times;</button>
            <div class="mlr-modal-content"></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function mlr_jalali_to_gregorian($jalali_date_str) {
    if (empty($jalali_date_str)) return null;
    if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $jalali_date_str, $matches)) {
        list($j_y, $j_m, $j_d) = array_map('intval', [$matches[1], $matches[2], $matches[3]]);
    } else { return null; }
    
    $j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
    $g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $jy = $j_y - 979; $jm = $j_m - 1; $jd = $j_d - 1;
    $j_day_no = 365 * $jy + floor($jy / 33) * 8 + floor((($jy % 33) + 3) / 4);
    for ($i = 0; $i < $jm; ++$i) { $j_day_no += $j_days_in_month[$i]; }
    $j_day_no += $jd; $g_day_no = $j_day_no + 79;
    $gy = 1600 + 400 * floor($g_day_no / 146097);
    $g_day_no = $g_day_no % 146097; $leap = true;
    if ($g_day_no >= 36525) { $g_day_no--; $gy += 100 * floor($g_day_no / 36524); $g_day_no = $g_day_no % 36524; if ($g_day_no >= 365) { $g_day_no++; } else { $leap = false; } }
    $gy += 4 * floor($g_day_no / 1461); $g_day_no %= 1461;
    if ($g_day_no >= 366) { $leap = false; $g_day_no--; $gy += floor($g_day_no / 365); $g_day_no = $g_day_no % 365; }
    for ($i = 0; $g_day_no >= $g_days_in_month[$i] + ($i == 1 && $leap); $i++) { $g_day_no -= $g_days_in_month[$i] + ($i == 1 && $leap); }
    $gm = $i + 1; $gd = $g_day_no + 1;
    return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
}

function mlr_gregorian_to_jalali($gregorian_date_str, $format = 'Y/m/d') {
    if (empty($gregorian_date_str) || $gregorian_date_str === '0000-00-00 00:00:00' || is_null($gregorian_date_str)) return '---';
    try {
        $g_date = new DateTime($gregorian_date_str, new DateTimeZone('Asia/Tehran'));
        $g_date->setTimezone(new DateTimeZone('Asia/Tehran')); // اطمینان از تبدیل به وقت ایران
        $g_y = (int)$g_date->format('Y'); $g_m = (int)$g_date->format('m'); $g_d = (int)$g_date->format('d');
    } catch (Exception $e) { return 'تاریخ نامعتبر'; }
    $g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
    $gy = $g_y - 1600; $gm = $g_m - 1; $gd = $g_d - 1;
    $g_day_no = 365 * $gy + floor(($gy + 3) / 4) - floor(($gy + 99) / 100) + floor(($gy + 399) / 400);
    for ($i = 0; $i < $gm; ++$i) { $g_day_no += $g_days_in_month[$i]; }
    if ($gm > 1 && (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0))) { $g_day_no++; }
    $g_day_no += $gd; $j_day_no = $g_day_no - 79; $j_np = floor($j_day_no / 12053);
    $j_day_no = $j_day_no % 12053; $jy = 979 + 33 * $j_np + 4 * floor($j_day_no / 1461);
    $j_day_no %= 1461; if ($j_day_no >= 366) { $jy += floor(($j_day_no - 1) / 365); $j_day_no = ($j_day_no - 1) % 365; }
    for ($i = 0; $i < 11 && $j_day_no >= $j_days_in_month[$i]; ++$i) { $j_day_no -= $j_days_in_month[$i]; }
    $jm = $i + 1; $jd = $j_day_no + 1;
    $final_format = str_replace(['Y', 'm', 'd', 'H', 'i'], [sprintf('%04d', $jy), sprintf('%02d', $jm), sprintf('%02d', $jd), $g_date->format('H'), $g_date->format('i')], $format);
    return $final_format;
}

function mlr_get_all_document_options() {
    return [
        'national_card_front' => 'کارت ملی (رو)', 'national_card_back' => 'کارت ملی (پشت)', 'birth_certificate' => 'شناسنامه (تمام صفحات)',
        'payslip' => 'فیش حقوقی', 'employment_certificate' => 'گواهی اشتغال به کار', 'business_license' => 'جواز کسب',
        'sana_certificate' => 'گواهی ثبت ثنا', 'blank_check_image' => 'تصویر چک صیادی', 'residence_document' => 'سند یا اجاره‌نامه محل سکونت',
        'workplace_document' => 'سند یا اجاره‌نامه محل کار', 'account_statement' => 'گردش حساب ۳ ماهه', 'activity_license' => 'مجوز فعالیت (سایر)',
        'additional_documents' => 'مدارک تکمیلی', 'completed_check_files' => 'چک‌های تکمیل شده', 'signed_contract_files' => 'قرارداد امضا شده',
        'signed_invoice_files' => 'فاکتور امضا شده', 'other_completed_files' => 'سایر مدارک نهایی'
    ];
}

if (!function_exists('mlr_get_calculation_field_aliases')) {
    function mlr_get_calculation_field_aliases($field_key) {
        $field_key = trim((string) $field_key);
        if ($field_key === '') {
            return [];
        }

        $aliases = [$field_key];
        if (strpos($field_key, '_') !== false) {
            $aliases[] = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $field_key))));
        } else {
            $aliases[] = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $field_key));
        }

        return array_values(array_unique(array_filter(array_map('strval', $aliases))));
    }
}

if (!function_exists('mlr_get_plan_display_fields_for_context')) {
    function mlr_get_plan_display_fields_for_context($plan_like, $context = '') {
        $display_fields = null;
        $display_fields_context = null;

        if (is_object($plan_like)) {
            foreach (['display_fields', 'plan_display_fields'] as $prop) {
                if (isset($plan_like->{$prop})) {
                    $display_fields = $plan_like->{$prop};
                    break;
                }
            }
            foreach (['display_fields_context', 'plan_display_fields_context'] as $prop) {
                if (isset($plan_like->{$prop})) {
                    $display_fields_context = $plan_like->{$prop};
                    break;
                }
            }
        } elseif (is_array($plan_like)) {
            foreach (['display_fields', 'plan_display_fields'] as $prop) {
                if (array_key_exists($prop, $plan_like)) {
                    $display_fields = $plan_like[$prop];
                    break;
                }
            }
            foreach (['display_fields_context', 'plan_display_fields_context'] as $prop) {
                if (array_key_exists($prop, $plan_like)) {
                    $display_fields_context = $plan_like[$prop];
                    break;
                }
            }
        }

        $display_fields = maybe_unserialize($display_fields);
        if (!is_array($display_fields)) {
            $decoded_display_fields = is_string($display_fields) ? json_decode($display_fields, true) : null;
            $display_fields = is_array($decoded_display_fields) ? $decoded_display_fields : [];
        }

        $display_fields_context = maybe_unserialize($display_fields_context);
        if (!is_array($display_fields_context)) {
            $decoded_display_fields_context = is_string($display_fields_context) ? json_decode($display_fields_context, true) : null;
            $display_fields_context = is_array($decoded_display_fields_context) ? $decoded_display_fields_context : [];
        }

        $context = trim((string) $context);
        if ($context !== '' && isset($display_fields_context[$context]) && is_array($display_fields_context[$context]) && !empty($display_fields_context[$context])) {
            return array_values(array_unique(array_filter(array_map('strval', $display_fields_context[$context]))));
        }

        return array_values(array_unique(array_filter(array_map('strval', $display_fields))));
    }
}

if (!function_exists('mlr_is_plan_calculation_field_enabled')) {
    function mlr_is_plan_calculation_field_enabled($plan_like, $field_key, $context = '') {
        $display_fields = mlr_get_plan_display_fields_for_context($plan_like, $context);

        if (empty($display_fields)) {
            return true;
        }

        foreach (mlr_get_calculation_field_aliases($field_key) as $alias) {
            if (in_array($alias, $display_fields, true)) {
                return true;
            }
        }

        return false;
    }
}

/**
 * Returns document category map for grouping uploaded documents in request details modals.
 * Keys are category slugs; values are arrays of document keys from required_documents.
 */
function mlr_get_document_category_map() {
    return [
        'identity' => [
            'national_card_front',
            'national_card_back',
            'birth_certificate',
            'sana_certificate',
        ],
        'job' => [
            'payslip',
            'employment_certificate',
            'business_license',
            'activity_license',
        ],
        'address' => [
            'residence_document',
            'workplace_document',
        ],
        'financial' => [
            'account_statement',
            'blank_check_image',
            'completed_check_files',
        ],
        'contracts' => [
            'signed_contract_files',
            'signed_invoice_files',
            'other_completed_files',
            'additional_documents',
        ],
    ];
}

if (!function_exists('mlr_extract_document_urls')) {
    function mlr_extract_document_urls($raw_value) {
        if ($raw_value === null) {
            return [];
        }

        if (is_object($raw_value)) {
            $raw_value = (array) $raw_value;
        }

        if (is_array($raw_value)) {
            $urls = [];

            if (isset($raw_value['url']) && is_string($raw_value['url'])) {
                $urls = array_merge($urls, mlr_extract_document_urls($raw_value['url']));
            } else {
                foreach ($raw_value as $value) {
                    $urls = array_merge($urls, mlr_extract_document_urls($value));
                }
            }

            return array_values(array_unique(array_filter(array_map('trim', $urls))));
        }

        if (!is_string($raw_value)) {
            return [];
        }

        $raw_value = trim($raw_value);
        if ($raw_value === '') {
            return [];
        }

        $maybe_unserialized = maybe_unserialize($raw_value);
        if ($maybe_unserialized !== $raw_value || is_array($maybe_unserialized) || is_object($maybe_unserialized)) {
            return mlr_extract_document_urls($maybe_unserialized);
        }

        $unslashed_value = stripslashes($raw_value);
        if ($unslashed_value !== $raw_value) {
            $maybe_unserialized_unslashed = maybe_unserialize($unslashed_value);
            if ($maybe_unserialized_unslashed !== $unslashed_value || is_array($maybe_unserialized_unslashed) || is_object($maybe_unserialized_unslashed)) {
                return mlr_extract_document_urls($maybe_unserialized_unslashed);
            }
        }

        $decoded = json_decode($raw_value, true);
        if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
            return mlr_extract_document_urls($decoded);
        }

        $decoded_unslashed = json_decode($unslashed_value, true);
        if (json_last_error() === JSON_ERROR_NONE && $decoded_unslashed !== null) {
            return mlr_extract_document_urls($decoded_unslashed);
        }

        if (strpos($raw_value, 'http') !== false && preg_match('/[\r\n,]+/', $raw_value)) {
            return array_values(array_unique(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw_value)))));
        }

        return [$raw_value];
    }
}

if (!function_exists('mlr_get_request_uploaded_documents')) {
    function mlr_get_request_uploaded_documents($raw_documents) {
        $documents = maybe_unserialize($raw_documents);

        if (is_string($documents)) {
            $unslashed_documents = stripslashes($documents);
            if ($unslashed_documents !== $documents) {
                $maybe_unserialized_unslashed = maybe_unserialize($unslashed_documents);
                if ($maybe_unserialized_unslashed !== $unslashed_documents || is_array($maybe_unserialized_unslashed) || is_object($maybe_unserialized_unslashed)) {
                    $documents = $maybe_unserialized_unslashed;
                }
            }
        }

        if (is_string($documents)) {
            $decoded = json_decode($documents, true);
            if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
                $documents = $decoded;
            } else {
                $decoded_unslashed = json_decode(stripslashes($documents), true);
                if (json_last_error() === JSON_ERROR_NONE && $decoded_unslashed !== null) {
                    $documents = $decoded_unslashed;
                }
            }
        }

        if (is_object($documents)) {
            $documents = (array) $documents;
        }

        if (!is_array($documents)) {
            return [];
        }

        $normalized = [];

        foreach ($documents as $doc_key => $doc_value) {
            $doc_key = is_string($doc_key) ? trim($doc_key) : '';
            if ($doc_key === '') {
                continue;
            }

            $urls = mlr_extract_document_urls($doc_value);
            if (empty($urls)) {
                continue;
            }

            $normalized[$doc_key] = count($urls) === 1 ? $urls[0] : $urls;
        }

        return $normalized;
    }
}

/**
 * Groups uploaded documents by category.
 *
 * @param array $uploaded_documents
 * @param array $labels
 * @return array [category_slug => [ ['key'=>..., 'label'=>..., 'urls'=>[...]], ... ], ...]
 */
function mlr_group_uploaded_documents_by_category($uploaded_documents, $labels = []) {
    if (!is_array($uploaded_documents)) {
        return [];
    }

    $map = mlr_get_document_category_map();
    $cat_of = [];

    foreach ($map as $cat => $keys) {
        foreach ($keys as $k) {
            $cat_of[$k] = $cat;
        }
    }

    $grouped = [];

    foreach ($uploaded_documents as $doc_key => $doc_value) {
        if (empty($doc_value)) {
            continue;
        }

        $urls = function_exists('mlr_extract_document_urls')
            ? mlr_extract_document_urls($doc_value)
            : array_values(array_filter(is_array($doc_value) ? $doc_value : [$doc_value]));
        if (empty($urls)) {
            continue;
        }

        $cat = $cat_of[$doc_key] ?? 'other';
        $label = isset($labels[$doc_key]) ? $labels[$doc_key] : ucfirst(str_replace('_', ' ', (string)$doc_key));

        if (!isset($grouped[$cat])) {
            $grouped[$cat] = [];
        }

        $grouped[$cat][] = [
            'key' => $doc_key,
            'label' => $label,
            'urls' => $urls,
        ];
    }

    // Keep a stable category order
    $order = ['identity', 'job', 'address', 'financial', 'contracts', 'other'];
    $sorted = [];
    foreach ($order as $cat) {
        if (!empty($grouped[$cat])) {
            $sorted[$cat] = $grouped[$cat];
        }
    }

    return $sorted;
}

/**
 * Returns display labels for document categories.
 */
function mlr_get_document_category_labels() {
    return [
        'identity' => 'مدارک هویتی',
        'job' => 'مدارک شغلی',
        'address' => 'مدارک سکونت',
        'financial' => 'مدارک مالی و چک',
        'contracts' => 'قرارداد و مدارک نهایی',
        'other' => 'سایر',
    ];
}

function mlr_get_all_valid_statuses() {
    return ['در حال بررسی', 'نقص مدارک', 'رد شده', 'تأیید کارشناس', 'تکمیل مدارک', 'تأیید مدارک', 'اعطا شده'];
}

if (!function_exists('mlr_simplify_status_key')) {
    function mlr_simplify_status_key($status) {
        if (!is_string($status)) {
            return '';
        }

        $status = trim($status);
        $status = str_replace(['ي', 'ك'], ['ی', 'ک'], $status);
        $status = str_replace(['أ', 'إ', 'آ'], 'ا', $status);
        $status = preg_replace('/\s+/u', ' ', $status);

        return $status;
    }
}

if (!function_exists('mlr_get_status_alias_groups')) {
    function mlr_get_status_alias_groups() {
        return [
            'در حال بررسی' => ['در حال بررسی'],
            'نقص مدارک' => ['نقص مدارک'],
            'رد شده' => ['رد شده'],
            'تأیید کارشناس' => ['تأیید کارشناس', 'تایید کارشناس'],
            'تکمیل مدارک' => ['تکمیل مدارک', 'در انتظار مدارک'],
            'تأیید مدارک' => ['تأیید مدارک', 'تایید مدارک', 'تأیید نهایی', 'تایید نهایی'],
            'اعطا شده' => ['اعطا شده'],
            'منتظر تعیین تاریخ' => ['منتظر تعیین تاریخ'],
            'در انتظار تسویه' => ['در انتظار تسویه'],
            'تسویه شده' => ['تسویه شده'],
        ];
    }
}

if (!function_exists('mlr_get_canonical_status_label')) {
    function mlr_get_canonical_status_label($status) {
        $status_key = mlr_simplify_status_key($status);
        if ($status_key === '') {
            return '';
        }

        foreach (mlr_get_status_alias_groups() as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if ($status_key === mlr_simplify_status_key($alias)) {
                    return $canonical;
                }
            }
        }

        return $status_key;
    }
}

if (!function_exists('mlr_get_status_sql_variants')) {
    function mlr_get_status_sql_variants($status) {
        $canonical = function_exists('mlr_normalize_status')
            ? mlr_normalize_status($status)
            : mlr_get_canonical_status_label($status);

        if ($canonical === '') {
            return [];
        }

        $variants = [$canonical];
        foreach (mlr_get_status_alias_groups() as $group_canonical => $aliases) {
            if ($group_canonical !== $canonical) {
                continue;
            }

            $variants = array_merge($variants, $aliases);
            break;
        }

        return array_values(array_unique(array_filter(array_map('trim', $variants))));
    }
}

if (!function_exists('mlr_add_status_where_clause')) {
    function mlr_add_status_where_clause($column_sql, $status, array &$where_clauses, array &$prepare_params) {
        $variants = function_exists('mlr_get_status_sql_variants') ? mlr_get_status_sql_variants($status) : [trim((string) $status)];
        $variants = array_values(array_filter($variants, static function ($variant) {
            return $variant !== '';
        }));

        if (empty($variants)) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($variants), '%s'));
        $where_clauses[] = "TRIM(COALESCE({$column_sql}, '')) IN ({$placeholders})";
        foreach ($variants as $variant) {
            $prepare_params[] = $variant;
        }
    }
}

if (!function_exists('mlr_repair_request_status_labels')) {
    function mlr_repair_request_status_labels() {
        global $wpdb;

        if (empty($wpdb)) {
            return false;
        }

        $table_requests = $wpdb->prefix . 'loan_requests';
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_requests));
        if ($table_exists !== $table_requests) {
            return false;
        }

        $ok = true;
        foreach (mlr_get_status_alias_groups() as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if ($alias === $canonical) {
                    continue;
                }

                $result = $wpdb->query($wpdb->prepare(
                    "UPDATE {$table_requests}
                     SET status = %s
                     WHERE TRIM(COALESCE(status, '')) = %s",
                    $canonical,
                    $alias
                ));
                if ($result === false) {
                    $ok = false;
                }

                $result = $wpdb->query($wpdb->prepare(
                    "UPDATE {$table_requests}
                     SET settlement_status = %s
                     WHERE TRIM(COALESCE(settlement_status, '')) = %s",
                    $canonical,
                    $alias
                ));
                if ($result === false) {
                    $ok = false;
                }
            }
        }
        return $ok;
    }
}

if (!function_exists('mlr_repair_request_status_labels_once')) {
    function mlr_repair_request_status_labels_once() {
        if (!is_admin()) {
            return false;
        }

        if (
            !current_user_can('manage_options')
            && !current_user_can('mlr_edit_settlement_date')
            && !current_user_can('mlr_amaliat_operations')
            && !current_user_can('mlr_mali_operations')
        ) {
            return false;
        }

        $version = defined('MLR_VERSION') ? MLR_VERSION : 'unknown';
        $option_name = 'mlr_status_label_repair_version';
        if (get_option($option_name) === $version) {
            return true;
        }

        $ok = mlr_repair_request_status_labels();
        if ($ok) {
            update_option($option_name, $version, false);
        } elseif (function_exists('mlr_log')) {
            mlr_log('Status label repair incomplete; it will be retried.', ['db_error' => $GLOBALS['wpdb']->last_error ?? '']);
        }
        return $ok;
    }
}
add_action('admin_init', 'mlr_repair_request_status_labels_once', 20);

if (!function_exists('mlr_get_document_receipt_status_options')) {
    function mlr_get_document_receipt_status_options() {
        return ['بررسی نشده', 'دریافت شده', 'عدم دریافت', 'عدم نیاز به دریافت مدارک'];
    }
}

if (!function_exists('mlr_normalize_document_receipt_status')) {
    function mlr_normalize_document_receipt_status($status) {
        $status = trim((string) $status);
        if ($status === 'عدم نیاز به مدارک') {
            $status = 'عدم نیاز به دریافت مدارک';
        }

        $valid_statuses = mlr_get_document_receipt_status_options();

        if ($status === '' || !in_array($status, $valid_statuses, true)) {
            return 'بررسی نشده';
        }

        return $status;
    }
}

if (!function_exists('mlr_get_document_receipt_status_label')) {
    function mlr_get_document_receipt_status_label($status) {
        return mlr_normalize_document_receipt_status($status);
    }
}

if (!function_exists('mlr_can_manage_document_receipt_status')) {
    function mlr_can_manage_document_receipt_status() {
        return current_user_can('manage_options') || current_user_can('mlr_amaliat_operations') || current_user_can('mlr_mali_operations');
    }
}

if (!function_exists('mlr_can_manage_transfer_receipts')) {
    function mlr_can_manage_transfer_receipts() {
        return current_user_can('manage_options') || current_user_can('mlr_mali_operations');
    }
}

if (!function_exists('mlr_can_view_leasing_deposit_amount')) {
    function mlr_can_view_leasing_deposit_amount($user_id = null) {
        $user = $user_id ? get_userdata((int) $user_id) : wp_get_current_user();
        if (!$user || empty($user->ID)) {
            return false;
        }

        if (function_exists('is_super_admin') && is_super_admin((int) $user->ID)) {
            return true;
        }

        $roles = (array) $user->roles;
        return in_array('administrator', $roles, true) || in_array('mali', $roles, true);
    }
}

if (!function_exists('mlr_get_leasing_deposit_amount')) {
    function mlr_get_leasing_deposit_amount($request) {
        if (!is_object($request) && !is_array($request)) {
            return null;
        }

        $get_value = static function ($key) use ($request) {
            if (is_array($request)) {
                return array_key_exists($key, $request) ? $request[$key] : null;
            }

            return isset($request->{$key}) ? $request->{$key} : null;
        };

        $stored_amount = $get_value('leasing_deposit_amount');
        if ($stored_amount !== null && $stored_amount !== '' && (float) $stored_amount > 0) {
            return (float) $stored_amount;
        }

        if (function_exists('mlr_recalculate_leasing_deposit_amount')) {
            return mlr_recalculate_leasing_deposit_amount($request, $get_value);
        }

        return null;
    }
}

if (!function_exists('mlr_recalculate_leasing_deposit_amount')) {
    function mlr_recalculate_leasing_deposit_amount($request, $get_value = null) {
        if (!is_object($request) && !is_array($request)) {
            return null;
        }

        if (!is_callable($get_value)) {
            $get_value = static function ($key) use ($request) {
                if (is_array($request)) {
                    return array_key_exists($key, $request) ? $request[$key] : null;
                }

                return isset($request->{$key}) ? $request->{$key} : null;
            };
        }

        $plan_id = (int) $get_value('installment_plan_id');
        $purchase_amount = (float) $get_value('loan_amount');
        $installment_count = (int) $get_value('installment_count');
        $installment_interval = max(1, (int) ($get_value('installment_interval') ?: 1));

        if ($plan_id <= 0 || $purchase_amount <= 0 || $installment_count <= 0) {
            return null;
        }

        global $wpdb;
        $plans_table = $wpdb->prefix . 'installment_plans';
        $plan = $wpdb->get_row($wpdb->prepare("SELECT type, settings FROM {$plans_table} WHERE id = %d", $plan_id));
        if (!$plan || strtolower(trim((string) $plan->type)) !== 'leasing') {
            return null;
        }

        if (!function_exists('calculateLeasingLoan')) {
            $calc_file = defined('MLR_PLUGIN_DIR') ? MLR_PLUGIN_DIR . 'includes/loan-calculations.php' : '';
            if ($calc_file && file_exists($calc_file)) {
                require_once $calc_file;
            }
        }

        if (!function_exists('calculateLeasingLoan')) {
            return null;
        }

        $settings = maybe_unserialize($plan->settings);
        if (!is_array($settings)) {
            $settings = [];
        }

        $results = calculateLeasingLoan($purchase_amount, $installment_count, $installment_interval, $settings, null);
        if (!is_array($results) || !isset($results['netLeasingPayment'])) {
            return null;
        }

        $amount = (float) $results['netLeasingPayment'];
        if ($amount <= 0) {
            return null;
        }

        $request_id = (int) $get_value('id');
        if ($request_id > 0) {
            $requests_table = $wpdb->prefix . 'loan_requests';
            $wpdb->update($requests_table, ['leasing_deposit_amount' => $amount], ['id' => $request_id], ['%f'], ['%d']);
        }

        return $amount;
    }
}

if (!function_exists('mlr_format_leasing_deposit_amount')) {
    function mlr_format_leasing_deposit_amount($request, $empty_value = '—') {
        if (function_exists('mlr_can_view_leasing_deposit_amount') && !mlr_can_view_leasing_deposit_amount()) {
            return $empty_value;
        }

        $amount = mlr_get_leasing_deposit_amount($request);

        return $amount === null ? $empty_value : number_format($amount);
    }
}

if (!function_exists('mlr_get_acceptor_settlement_amount')) {
    function mlr_get_acceptor_settlement_amount($request) {
        if (!is_object($request) && !is_array($request)) {
            return null;
        }

        $get_value = static function ($key) use ($request) {
            if (is_array($request)) {
                return array_key_exists($key, $request) ? $request[$key] : null;
            }

            return isset($request->{$key}) ? $request->{$key} : null;
        };

        $final_amount = $get_value('final_settlement_amount');
        if ($final_amount !== null && $final_amount !== '' && (float) $final_amount > 0) {
            return (float) $final_amount;
        }

        $acceptor_amount = $get_value('acceptor_payment_amount');
        if ($acceptor_amount !== null && $acceptor_amount !== '' && (float) $acceptor_amount > 0) {
            return (float) $acceptor_amount;
        }

        return null;
    }
}

if (!function_exists('mlr_format_acceptor_settlement_amount')) {
    function mlr_format_acceptor_settlement_amount($request, $empty_value = '—') {
        $amount = mlr_get_acceptor_settlement_amount($request);

        return $amount === null ? $empty_value : number_format($amount);
    }
}

if (!function_exists('mlr_get_transfer_receipt_files')) {
    function mlr_get_transfer_receipt_files($raw_value) {
        if (!function_exists('mlr_extract_document_urls')) {
            return [];
        }

        return mlr_extract_document_urls($raw_value);
    }
}

/**
 * محل‌های قابل کنترل برای نمایش یک طرح اقساطی در فرانت‌اند.
 *
 * کلیدها عمداً برابر نام شورتکد هستند تا هم رابط مدیریت و هم کنترل دسترسی
 * از یک شناسه واحد استفاده کنند.
 */
if (!function_exists('mlr_get_installment_plan_visibility_contexts')) {
    function mlr_get_installment_plan_visibility_contexts() {
        return [
            'mlr_loan_request_form' => [
                'label'       => 'فرم ثبت درخواست توسط پذیرنده',
                'panel'       => 'پنل پذیرنده',
                'shortcode'   => '[mlr_loan_request_form]',
                'description' => 'طرح در فرم ثبت درخواست مشتری توسط پذیرنده نمایش داده می‌شود. هر طرح فعالِ این مسیر به‌صورت پیش‌فرض برای همه پذیرنده‌ها در دسترس است، مگر اینکه در پروفایل همان پذیرنده غیرفعال شده باشد.',
            ],
            'mlr_applicant_loan_request_form' => [
                'label'       => 'فرم ثبت درخواست مستقیم متقاضی',
                'panel'       => 'پنل متقاضی',
                'shortcode'   => '[mlr_applicant_loan_request_form]',
                'description' => 'طرح در فرم مخصوص متقاضیان نمایش داده می‌شود تا کاربر متقاضی بتواند مستقیماً درخواست خود را ثبت کند.',
            ],
            'mlr_loan_calculator' => [
                'label'       => 'محاسبه‌گر عمومی اقساط',
                'panel'       => 'صفحه عمومی / محاسبه‌گر',
                'shortcode'   => '[mlr_loan_calculator]',
                'description' => 'طرح در محاسبه‌گر مستقل سایت نمایش داده می‌شود. برای حساب پذیرنده، طرح‌های غیرفعال‌شده در پروفایل همان پذیرنده از فهرست و محاسبات حذف می‌شوند.',
            ],
        ];
    }
}

if (!function_exists('mlr_get_plan_settings_array')) {
    function mlr_get_plan_settings_array($plan_like) {
        $settings = [];

        if (is_object($plan_like) && property_exists($plan_like, 'settings')) {
            $settings = $plan_like->settings;
        } elseif (is_array($plan_like) && array_key_exists('settings', $plan_like)) {
            $settings = $plan_like['settings'];
        } else {
            $settings = $plan_like;
        }

        $settings = maybe_unserialize($settings);
        if (!is_array($settings) && is_string($settings)) {
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : [];
        }

        return is_array($settings) ? $settings : [];
    }
}

/**
 * محل‌های نمایش مؤثر یک طرح را برمی‌گرداند.
 *
 * سازگاری با نسخه‌های قبلی:
 * - فرم پذیرنده و محاسبه‌گر قبلاً برای تمام طرح‌های فعال قابل استفاده بودند.
 * - فرم متقاضی با applicant_visible کنترل می‌شد.
 * تا قبل از اولین ذخیره در رابط جدید، همین رفتار حفظ می‌شود.
 */
if (!function_exists('mlr_get_plan_visibility_contexts')) {
    function mlr_get_plan_visibility_contexts($plan_like) {
        $settings = mlr_get_plan_settings_array($plan_like);
        $known_contexts = array_keys(mlr_get_installment_plan_visibility_contexts());

        if (array_key_exists('visibility_contexts', $settings)) {
            $configured = maybe_unserialize($settings['visibility_contexts']);
            if (!is_array($configured) && is_string($configured)) {
                $decoded = json_decode($configured, true);
                $configured = is_array($decoded) ? $decoded : preg_split('/\s*,\s*/', $configured, -1, PREG_SPLIT_NO_EMPTY);
            }
            if (!is_array($configured)) {
                $configured = [];
            }

            $configured = array_values(array_unique(array_filter(array_map('sanitize_key', $configured))));
            return array_values(array_intersect($known_contexts, $configured));
        }

        $legacy_contexts = ['mlr_loan_request_form', 'mlr_loan_calculator'];
        if (!empty($settings['applicant_visible'])) {
            $legacy_contexts[] = 'mlr_applicant_loan_request_form';
        }

        return array_values(array_intersect($known_contexts, $legacy_contexts));
    }
}

if (!function_exists('mlr_is_installment_plan_visible_in_context')) {
    function mlr_is_installment_plan_visible_in_context($plan_like, $context = '') {
        $context = sanitize_key($context);
        if ($context === '') {
            return true;
        }

        $known_contexts = mlr_get_installment_plan_visibility_contexts();
        if (!isset($known_contexts[$context])) {
            // مسیرهای داخلی ناشناخته را برای سازگاری نسخه‌های قبلی مسدود نکن.
            return true;
        }

        return in_array($context, mlr_get_plan_visibility_contexts($plan_like), true);
    }
}

if (!function_exists('mlr_is_user_installment_plan_restricted')) {
    /**
     * محدودیت اختصاصی طرح فقط برای حساب‌های پذیرنده اعمال می‌شود.
     * مدیر، مالی، عملیات، متقاضی و مهمان صرفاً از تنظیمات عمومی/محل نمایش طرح پیروی می‌کنند.
     */
    function mlr_is_user_installment_plan_restricted($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return false;
        }

        if (user_can($user_id, 'manage_options') || user_can($user_id, 'mlr_amaliat_operations') || user_can($user_id, 'mlr_mali_operations')) {
            return false;
        }

        if (function_exists('mlr_user_has_role')) {
            return mlr_user_has_role($user_id, 'acceptor');
        }

        $user = get_userdata($user_id);
        return $user && (bool) array_intersect((array) $user->roles, ['acceptor', 'pazirande']);
    }
}

if (!function_exists('mlr_normalize_installment_plan_id_list')) {
    function mlr_normalize_installment_plan_id_list($raw_value) {
        if (empty($raw_value)) {
            return [];
        }

        if (is_string($raw_value)) {
            $raw_value = maybe_unserialize($raw_value);
            if (is_string($raw_value)) {
                $decoded = json_decode($raw_value, true);
                $raw_value = is_array($decoded)
                    ? $decoded
                    : preg_split('/\s*,\s*/', $raw_value, -1, PREG_SPLIT_NO_EMPTY);
            }
        }

        if (!is_array($raw_value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('absint', $raw_value))));
    }
}

if (!function_exists('mlr_get_user_disabled_installment_plan_ids')) {
    /**
     * فهرست طرح‌هایی که فقط برای یک پذیرنده غیرفعال شده‌اند.
     *
     * سازگاری با نسخه 8.4.6.17 و قبل:
     * مقادیر کلید قدیمی mlr_allowed_installment_plan_ids طبق مدل معکوس جدید
     * به‌عنوان deny-list خوانده می‌شوند تا انتخاب‌های موجود بلافاصله غیرفعال شوند.
     * با اولین ذخیره پروفایل، کلید قدیمی حذف و کلید جدید ذخیره می‌شود.
     */
    function mlr_get_user_disabled_installment_plan_ids($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return [];
        }

        $new_meta_key = 'mlr_disabled_installment_plan_ids';
        if (metadata_exists('user', $user_id, $new_meta_key)) {
            return mlr_normalize_installment_plan_id_list(get_user_meta($user_id, $new_meta_key, true));
        }

        return mlr_normalize_installment_plan_id_list(
            get_user_meta($user_id, 'mlr_allowed_installment_plan_ids', true)
        );
    }
}

if (!function_exists('mlr_get_user_allowed_installment_plan_ids')) {
    /**
     * تابع سازگاری برای کدهای جانبی قدیمی.
     * در مدل جدید، همه طرح‌های تعریف‌شده به‌جز deny-list کاربر «مجاز» محسوب می‌شوند؛
     * فعال بودن و محل نمایش در مرحله دریافت طرح‌ها جداگانه کنترل می‌شود.
     */
    function mlr_get_user_allowed_installment_plan_ids($user_id) {
        global $wpdb;

        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return [];
        }

        $table_name = $wpdb->prefix . 'installment_plans';
        $plan_order_sql = function_exists('mlr_get_installment_plan_order_by_sql') ? mlr_get_installment_plan_order_by_sql() : 'ORDER BY id DESC';
        $all_plan_ids = $wpdb->get_col("SELECT id FROM `{$table_name}` {$plan_order_sql}");
        if (!is_array($all_plan_ids)) {
            return [];
        }

        $all_plan_ids = array_values(array_unique(array_filter(array_map('absint', $all_plan_ids))));
        $disabled_plan_ids = mlr_get_user_disabled_installment_plan_ids($user_id);

        return array_values(array_diff($all_plan_ids, $disabled_plan_ids));
    }
}

if (!function_exists('mlr_installment_plan_display_order_column_exists')) {
    function mlr_installment_plan_display_order_column_exists() {
        global $wpdb;
        static $exists = null;
        if ($exists !== null) return $exists;
        $table = $wpdb->prefix . 'installment_plans';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            $exists = false;
            return $exists;
        }
        $exists = (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", 'display_order'));
        return $exists;
    }
}

if (!function_exists('mlr_get_installment_plan_order_by_sql')) {
    /**
     * Canonical display order for every installment-plan list in the plugin.
     * Safe fallback keeps legacy id-desc ordering until migration 2.7 is available.
     */
    function mlr_get_installment_plan_order_by_sql($table_alias = '') {
        $prefix = '';
        $table_alias = trim((string) $table_alias);
        if ($table_alias !== '') {
            $prefix = preg_replace('/[^A-Za-z0-9_]/', '', $table_alias) . '.';
        }
        if (mlr_installment_plan_display_order_column_exists()) {
            return "ORDER BY {$prefix}`display_order` ASC, {$prefix}`id` DESC";
        }
        return "ORDER BY {$prefix}`id` DESC";
    }
}

if (!function_exists('mlr_get_available_installment_plans_for_user')) {
    function mlr_get_available_installment_plans_for_user($user_id = 0, $args = []) {
        global $wpdb;

        $args = wp_parse_args($args, [
            'active_only' => true,
            'fields' => ['id', 'name', 'type', 'settings'],
            'order_by' => 'display_order',
            // نام قدیمی برای سازگاری نگه داشته شده است؛ در مدل جدید یعنی اعمال deny-list کاربر.
            'restrict_to_allowed_user_plans' => true,
            'apply_user_plan_exclusions' => null,
            'visibility_context' => '',
        ]);

        $allowed_columns = ['id', 'name', 'type', 'settings', 'display_fields', 'display_fields_context', 'required_documents', 'is_active', 'display_order', 'created_at'];
        $requested_fields = is_array($args['fields']) ? $args['fields'] : ['id', 'name', 'type', 'settings'];
        $visibility_context = sanitize_key($args['visibility_context']);

        // برای فیلتر محل نمایش، settings باید در نتیجه کوئری موجود باشد.
        if ($visibility_context !== '' && !in_array('settings', $requested_fields, true)) {
            $requested_fields[] = 'settings';
        }

        $selected_fields = array_values(array_intersect($requested_fields, $allowed_columns));
        if (empty($selected_fields)) {
            $selected_fields = ['id', 'name', 'type', 'settings'];
        }

        $fields_sql = implode(', ', array_map(function($column) {
            return '`' . $column . '`';
        }, $selected_fields));

        $table_name = $wpdb->prefix . 'installment_plans';
        $where_clauses = ['1=1'];
        $prepare_params = [];

        if (!empty($args['active_only'])) {
            $where_clauses[] = 'is_active = 1';
        }

        $user_id = absint($user_id);
        $apply_user_plan_exclusions = $args['apply_user_plan_exclusions'] === null
            ? !empty($args['restrict_to_allowed_user_plans'])
            : (bool) $args['apply_user_plan_exclusions'];

        if ($apply_user_plan_exclusions && $user_id > 0 && function_exists('mlr_is_user_installment_plan_restricted') && mlr_is_user_installment_plan_restricted($user_id)) {
            $disabled_plan_ids = function_exists('mlr_get_user_disabled_installment_plan_ids')
                ? mlr_get_user_disabled_installment_plan_ids($user_id)
                : [];

            // خالی بودن deny-list یعنی هیچ محدودیت اختصاصی وجود ندارد و همه طرح‌های
            // فعالِ مجاز در محل نمایش انتخاب‌شده برای پذیرنده قابل استفاده‌اند.
            if (!empty($disabled_plan_ids)) {
                $plan_placeholders = implode(', ', array_fill(0, count($disabled_plan_ids), '%d'));
                $where_clauses[] = "id NOT IN ($plan_placeholders)";
                $prepare_params = array_merge($prepare_params, $disabled_plan_ids);
            }
        }

        switch ($args['order_by']) {
            case 'name_asc':
                $order_by_sql = 'ORDER BY `name` ASC';
                break;
            case 'id_asc':
                $order_by_sql = 'ORDER BY `id` ASC';
                break;
            case 'id_desc':
                $order_by_sql = 'ORDER BY `id` DESC';
                break;
            case 'display_order':
            default:
                $order_by_sql = mlr_get_installment_plan_order_by_sql();
                break;
        }

        $sql = "SELECT {$fields_sql} FROM `{$table_name}` WHERE " . implode(' AND ', $where_clauses) . " {$order_by_sql}";
        $plans = !empty($prepare_params)
            ? $wpdb->get_results($wpdb->prepare($sql, $prepare_params))
            : $wpdb->get_results($sql);

        if (!is_array($plans)) {
            return [];
        }

        if ($visibility_context !== '' && function_exists('mlr_is_installment_plan_visible_in_context')) {
            $plans = array_values(array_filter($plans, function($plan) use ($visibility_context) {
                return mlr_is_installment_plan_visible_in_context($plan, $visibility_context);
            }));
        }

        return $plans;
    }
}

if (!function_exists('mlr_user_can_access_installment_plan')) {
    function mlr_user_can_access_installment_plan($user_id, $plan_id, $active_only = true, $visibility_context = '') {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            return false;
        }

        $plans = function_exists('mlr_get_available_installment_plans_for_user')
            ? mlr_get_available_installment_plans_for_user($user_id, [
                'active_only' => (bool) $active_only,
                'fields' => ['id', 'settings'],
                'order_by' => 'id_desc',
                'visibility_context' => $visibility_context,
            ])
            : [];

        if (empty($plans)) {
            return false;
        }

        foreach ($plans as $plan) {
            if ((int) ($plan->id ?? 0) === $plan_id) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('mlr_get_plan_conditions_html')) {
    function mlr_get_plan_conditions_html($settings) {
        $settings = maybe_unserialize($settings);
        if (!is_array($settings)) {
            $settings = [];
        }

        $conditions = isset($settings['plan_conditions']) ? (string) $settings['plan_conditions'] : '';
        $conditions = trim(wp_kses_post($conditions));

        return $conditions !== '' ? wpautop($conditions) : '';
    }
}

if (!function_exists('mlr_prepare_plan_required_documents_payload')) {
    function mlr_prepare_plan_required_documents_payload($required_documents, $settings = [], $occupation = '') {
        $all_document_labels = function_exists('mlr_get_all_document_options') ? mlr_get_all_document_options() : [];

        $settings = maybe_unserialize($settings);
        if (!is_array($settings)) {
            $settings = [];
        }

        $required_document_keys = function_exists('mlr_get_plan_required_document_keys')
            ? mlr_get_plan_required_document_keys($required_documents, $settings, $occupation)
            : maybe_unserialize($required_documents);
        if (!is_array($required_document_keys)) {
            $required_document_keys = [];
        }

        $document_descriptions = $settings['required_document_descriptions'] ?? [];
        if (!is_array($document_descriptions)) {
            $document_descriptions = [];
        }

        $multi_upload_fields = ['birth_certificate', 'activity_license', 'account_statement', 'additional_documents'];
        $required_documents_payload = [];

        foreach ($required_document_keys as $document_key) {
            $document_key = sanitize_key($document_key);
            if ($document_key === '' || !isset($all_document_labels[$document_key])) {
                continue;
            }

            $description = isset($document_descriptions[$document_key])
                ? sanitize_text_field($document_descriptions[$document_key])
                : '';

            $required_documents_payload[] = [
                'key'         => $document_key,
                'label'       => $all_document_labels[$document_key],
                'description' => $description,
                'multiple'    => in_array($document_key, $multi_upload_fields, true),
            ];
        }

        return $required_documents_payload;
    }
}

if (!function_exists('mlr_prepare_frontend_js_plans')) {
    function mlr_prepare_frontend_js_plans($plans) {
        if (!is_array($plans)) {
            return [];
        }

        return array_map(function($plan) {
            $settings = maybe_unserialize($plan->settings ?? null);
            if (!is_array($settings)) {
                $settings = [];
            }
            $plan->view_settings = [
                'requires_bank_validation' => $settings['requires_bank_validation'] ?? 0,
                'min_amount' => $settings['min_amount'] ?? 0,
                'max_amount' => $settings['max_amount'] ?? 0,
                'active_installments' => $settings['active_installments'] ?? '',
                'min_interval' => $settings['min_interval'] ?? 1,
                'max_interval' => $settings['max_interval'] ?? 12,
                'prepayment_percent' => $settings['prepayment_percent'] ?? null,
            ];
            $plan->required_documents = mlr_prepare_plan_required_documents_payload($plan->required_documents ?? [], $settings);
            $plan->plan_conditions = mlr_get_plan_conditions_html($settings);
            unset($plan->settings);

            return $plan;
        }, $plans);
    }
}

/**
 * نرمال‌سازی شماره موبایل برای استفاده در پیامک/OTP (قرارداد همکاری و غیره).
 * اعداد فارسی و عربی را به انگلیسی تبدیل می‌کند؛ فاصله و نویسه‌های اضافه حذف می‌شوند.
 *
 * @param string|null $raw مقدار خام (ممکن است با ۰۹ یا 09 یا اعداد فارسی ذخیره شده باشد)
 * @return string|null شماره ۱۱ رقمی با پیش‌وند 09 یا null در صورت نامعتبر
 */
function mlr_normalize_mobile_for_cooperation($raw) {
    if ($raw === null || $raw === '') {
        return null;
    }
    $raw = (string) $raw;
    // تبدیل اعداد فارسی و عربی به انگلیسی (هر دو مجموعه را جداگانه به 0-9 map می‌کنیم)
    $persian_digits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $arabic_digits  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $english_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $raw = str_replace($persian_digits, $english_digits, $raw);
    $raw = str_replace($arabic_digits, $english_digits, $raw);
    // حذف هر نویسه‌ای غیر از رقم (فاصله، خط تیر، نویسه‌های پنهان و ...)
    $raw = preg_replace('/[^0-9]/', '', $raw);
    if (preg_match('/^0?9\d{9}$/', $raw)) {
        return (strlen($raw) === 10) ? '0' . $raw : $raw;
    }
    return null;
}

function mlr_get_calculation_labels() {
    return [
        'loanAmount' => ['label' => 'مبلغ تسهیلات', 'unit' => 'ریال'], 'installmentCount' => ['label' => 'تعداد اقساط', 'unit' => 'قسط'],
        'prepayment' => ['label' => 'پیش پرداخت', 'unit' => 'ریال'], 'totalPayment' => ['label' => 'مبلغ پرداخت نهایی', 'unit' => 'ریال'],
        'installmentAmount' => ['label' => 'مبلغ هر قسط', 'unit' => 'ریال'], 'profit' => ['label' => 'سود تسهیلات', 'unit' => 'ریال'],
        'operationCost' => ['label' => 'مبلغ هزینه عملیات', 'unit' => 'ریال'], 'walletCharge' => ['label' => 'اعتبار خرید', 'unit' => 'ریال'],
        'acceptorPayment' => ['label' => 'مبلغ واریزی برای پذیرنده', 'unit' => 'ریال'], 'guaranteeCheckPrincipal' => ['label' => 'مبلغ چک ضمانت (اصل)', 'unit' => 'ریال'],
        'guaranteeCheckTotal' => ['label' => 'مبلغ چک ضمانت (اصل و فرع)', 'unit' => 'ریال'], 'numberOfInstallments' => ['label' => 'تعداد قسط/چک', 'unit' => 'عدد'],
        'operationCostPercent' => ['label' => 'درصد هزینه عملیات', 'unit' => 'درصد'], 'operationCostWithVat' => ['label' => 'جمع هزینه عملیات + ارزش افزوده', 'unit' => 'ریال'],
        'netLeasingPayment' => ['label' => 'خالص واریزی لیزینگ', 'unit' => 'ریال'], 'acceptorCommission' => ['label' => 'کمیسیون پذیرنده', 'unit' => 'ریال'],
        'acceptorCommissionWithVat' => ['label' => 'کمیسیون پذیرنده + ارزش افزوده', 'unit' => 'ریال'],
    ];
}

/**
 * Returns a list of available placeholders for the INSTALLMENT CONTRACT.
 * Used in Admin Settings to show allowed variables.
 */
function mlr_get_installment_contract_placeholders() {
    return [
        '[request_id]'          => 'شناسه درخواست',
        '[contract_number]'     => 'شماره قرارداد (پیش‌فرض: شناسه درخواست)',
        '[full_name]'           => 'نام و نام خانوادگی',
        '[national_code]'       => 'کد ملی',
        '[mobile_number]'       => 'شماره موبایل',
        '[phone_number]'        => 'تلفن ثابت',
        '[father_name]'         => 'نام پدر',
        '[certificate_number]'  => 'شماره شناسنامه',
        '[certificate_city]'    => 'شهر صدور شناسنامه',
        '[birth_date]'          => 'تاریخ تولد',
        '[occupation]'          => 'شغل',
        '[residential_address]' => 'آدرس منزل',
        '[work_address]'        => 'آدرس محل کار',
        '[postal_code]'         => 'کد پستی',
        '[plan_name]'           => 'نام طرح',
        '[loan_amount]'         => 'مبلغ درخواستی (ریال)',
        '[facility_amount]'     => 'مبلغ تسهیلات نهایی (ریال)',
        '[total_payment]'       => 'مبلغ پرداخت نهایی (ریال)',
        '[prepayment]'          => 'مبلغ پیش پرداخت (ریال)',
        '[installment_amount]'  => 'مبلغ هر قسط (ریال)',
        '[installment_count]'   => 'مدت/تعداد انتخابی پرونده (فیلد قدیمی)',
        '[installment_term_months]' => 'مدت بازپرداخت قرارداد (ماه)',
        '[payment_count]'       => 'تعداد واقعی سررسید/قسط با توجه به فاصله اقساط',
        '[installment_interval]' => 'فاصله اقساط (ماه)',
        '[first_installment_amount]' => 'مبلغ قسط اول (ریال)',
        '[first_installment_amount_words]' => 'مبلغ قسط اول به حروف',
        '[remaining_installment_amount]' => 'مبلغ سایر اقساط (ریال)',
        '[remaining_installment_amount_words]' => 'مبلغ سایر اقساط به حروف',
        '[first_due_date]'      => 'تاریخ اولین سررسید',
        '[guarantee_check_principal]' => 'مبلغ چک ضمانت (اصل)',
        '[guarantee_check_total]'     => 'مبلغ چک ضمانت (اصل و فرع)',
        '[product_name]'        => 'نام کالا/محصول',
        '[invoice_number]'      => 'شماره فاکتور',
        '[invoice_date]'        => 'تاریخ فاکتور',
        '[retirement_code]'     => 'کد بازنشستگی (در صورت وجود در پرونده)',
        '[retirement_organization]' => 'سازمان بازنشستگی (در صورت وجود در پرونده)',
        '[total_price]'         => 'کل قیمت مورد معامله (ریال)',
        '[total_price_words]'   => 'کل قیمت مورد معامله به حروف',
        '[contract_date]'       => 'تاریخ عقد قرارداد',
        '[contract_start_date]' => 'تاریخ شروع قرارداد',
        '[contract_end_date]'   => 'تاریخ پایان قرارداد',
        '[contract_duration_months]' => 'مدت قرارداد (ماه)',
        '[delivery_date]'       => 'تاریخ تحویل کالا',
        '[annual_interest_rate]' => 'نرخ سود سالانه طرح',
        '[cash_price]'          => 'قیمت فروش نقدی (ریال)',
        '[cash_price_words]'    => 'قیمت فروش نقدی به حروف',
        '[installment_sale_price]' => 'قیمت فروش اقساطی / کل ثمن (ریال)',
        '[installment_sale_price_words]' => 'قیمت فروش اقساطی به حروف',
        '[created_at]'          => 'تاریخ ثبت درخواست',
    ];
}

/**
 * Returns a list of available placeholders for the CONTRACT (Distinct from SMS placeholders).
 */
function mlr_get_contract_placeholders() {
    return [
        '{{request_id}}' => 'شناسه درخواست',
        '{{request_type}}' => 'نوع درخواست (حقیقی/حقوقی)',
        '{{status}}' => 'وضعیت',
        '{{created_at}}' => 'تاریخ ثبت درخواست',
        '{{contract_signed_date}}' => 'تاریخ امضای قرارداد',
        '{{contract_expiry_date}}' => 'تاریخ انقضای قرارداد',
        // Fields from JSON data
        '{{store_name}}' => 'نام فروشگاه',
        '{{company_name}}' => 'نام شرکت',
        '{{store_owner_name}}' => 'نام مالک فروشگاه',
        '{{contact_person}}' => 'نام رابط',
        '{{activity_field}}' => 'زمینه فعالیت',
        '{{store_address}}' => 'آدرس فروشگاه',
        '{{company_address}}' => 'آدرس شرکت',
        '{{postal_code}}' => 'کد پستی',
        '{{mobile_number}}' => 'شماره همراه',
        '{{phone_number}}' => 'تلفن ثابت',
        '{{email}}' => 'ایمیل',
        '{{website}}' => 'وب‌سایت',
        '{{description}}' => 'توضیحات',
        '{{shaba_number}}' => 'شماره شبا', 
        '{{applicant_id_number}}' => 'کد ملی / شناسه ملی',
    ];
}

/**
 * Replaces placeholders in the contract text with actual data.
 * Uses {{...}} format to avoid conflict with SMS [[...]] format.
 */
function mlr_replace_contract_placeholders($content, $request) {
    if (empty($content)) return '';

    // Decode JSON data from the request
    $data = [];
    if (!empty($request->data)) {
        $data = json_decode($request->data, true);
    }
    if (!is_array($data)) $data = [];

    // Prepare replacements
    $replacements = [
        '{{request_id}}' => $request->id,
        '{{request_type}}' => ($request->request_type === 'real' || $request->request_type === 'individual') ? 'حقیقی' : 'حقوقی',
        '{{status}}' => $request->status,
        '{{created_at}}' => isset($request->created_at) ? mlr_gregorian_to_jalali($request->created_at) : '---',
        
        '{{store_name}}' => $data['store_name'] ?? '',
        '{{company_name}}' => $data['company_name'] ?? '',
        '{{store_owner_name}}' => $data['store_owner_name'] ?? '',
        '{{contact_person}}' => $data['contact_person'] ?? '',
        '{{activity_field}}' => $data['activity_field'] ?? '',
        '{{store_address}}' => $data['store_address'] ?? '',
        '{{company_address}}' => $data['company_address'] ?? '',
        '{{postal_code}}' => $data['postal_code'] ?? '',
        '{{mobile_number}}' => $data['mobile_number'] ?? '',
        '{{phone_number}}' => $data['phone_number'] ?? '',
        '{{email}}' => $data['email'] ?? '',
        '{{website}}' => $data['website'] ?? '',
        '{{description}}' => $data['description'] ?? '',
        '{{shaba_number}}' => $data['shaba_number'] ?? '',
        // Map applicant_id_number
        '{{applicant_id_number}}' => !empty($data['applicant_id_number']) ? $data['applicant_id_number'] : (!empty($data['national_code']) ? $data['national_code'] : ($data['national_id'] ?? '')),
    ];

    // Handle contract dates
    if (isset($request->contract_signed_at)) {
         $replacements['{{contract_signed_date}}'] = mlr_gregorian_to_jalali($request->contract_signed_at, 'Y/m/d');
    } else {
         $replacements['{{contract_signed_date}}'] = '---';
    }
    
    if (isset($request->contract_expires_at)) {
         $replacements['{{contract_expiry_date}}'] = mlr_gregorian_to_jalali($request->contract_expires_at, 'Y/m/d');
    } else {
         $replacements['{{contract_expiry_date}}'] = '---';
    }

    return str_replace(array_keys($replacements), array_values($replacements), $content);
}

if (!function_exists('mlr_validate_uploaded_file')) {
    function mlr_validate_uploaded_file($file, &$error_message = '') {
        if (empty($file) || !isset($file['name'], $file['tmp_name'], $file['error'])) {
            $error_message = 'Invalid upload data.';
            return false;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_message = 'Upload error.';
            return false;
        }

        // Sanitize filename
        $file['name'] = sanitize_file_name($file['name']);

        if (!is_uploaded_file($file['tmp_name'])) {
            $error_message = 'Invalid uploaded file.';
            return false;
        }

        $allowed_mimes = [
            'jpg|jpeg' => 'image/jpeg',
            'png'      => 'image/png',
            'pdf'      => 'application/pdf',
        ];

        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed_mimes);
        $ext   = isset($check['ext']) ? strtolower($check['ext']) : '';
        $type  = isset($check['type']) ? strtolower($check['type']) : '';

        if (empty($ext) || empty($type)) {
            $error_message = 'File type not allowed.';
            return false;
        }

        // Explicit extension whitelist
        if (!in_array($ext, ['jpg','jpeg','png','pdf'], true)) {
            $error_message = 'File extension not allowed.';
            return false;
        }

        // Ensure mime matches allowed set
        if (!in_array($type, array_values($allowed_mimes), true)) {
            $error_message = 'File mime type not allowed.';
            return false;
        }

        return $file;
    }
}

if (!function_exists('mlr_get_upload_error_message')) {
    /**
     * Returns a readable Persian message for PHP upload error codes.
     *
     * @param int $error_code PHP UPLOAD_ERR_* code.
     * @return string
     */
    function mlr_get_upload_error_message($error_code) {
        $error_code = (int) $error_code;

        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'حجم فایل از حد مجاز سرور بیشتر است.';
            case UPLOAD_ERR_PARTIAL:
                return 'فایل به‌صورت ناقص بارگذاری شد. لطفاً دوباره تلاش کنید.';
            case UPLOAD_ERR_NO_FILE:
                return 'فایلی برای آپلود انتخاب نشده است.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'پوشه موقت آپلود روی سرور در دسترس نیست.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'امکان ذخیره فایل روی سرور وجود ندارد.';
            case UPLOAD_ERR_EXTENSION:
                return 'آپلود فایل توسط یکی از افزونه‌های سرور متوقف شد.';
            case UPLOAD_ERR_OK:
                return '';
            default:
                return 'خطای آپلود فایل (کد: ' . $error_code . ')';
        }
    }
}

if (!function_exists('mlr_prepare_upload_file_name')) {
    /**
     * Builds a short ASCII filename before WordPress moves the uploaded file.
     *
     * @param array  $file_data Uploaded file data.
     * @param string $prefix Safe filename prefix.
     * @return array|WP_Error
     */
    function mlr_prepare_upload_file_name($file_data, $prefix = 'mlr-file') {
        if (empty($file_data) || !is_array($file_data)) {
            return new WP_Error('invalid_file_data', 'اطلاعات فایل نامعتبر است.');
        }

        $original_name = isset($file_data['name']) ? (string) $file_data['name'] : '';
        $extension = strtolower((string) pathinfo($original_name, PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension);

        if ($extension === '') {
            return new WP_Error('missing_file_extension', 'پسوند فایل قابل تشخیص نیست.');
        }

        $prefix = sanitize_key($prefix);
        if ($prefix === '') {
            $prefix = 'mlr-file';
        }

        $random_suffix = function_exists('wp_generate_password')
            ? strtolower(wp_generate_password(8, false, false))
            : strtolower(substr(md5(uniqid('', true)), 0, 8));

        $file_data['name'] = sprintf(
            '%s-%s-%s.%s',
            $prefix,
            gmdate('Ymd-His'),
            $random_suffix,
            $extension
        );

        return $file_data;
    }
}

if (!function_exists('mlr_handle_wp_upload')) {
    /**
     * Handles the upload of a single file using wp_handle_upload.
     *
     * @param array $file_data The file data from $_FILES array (e.g., $_FILES['my_file']).
     * @param array $overrides Optional. An array of overrides for wp_handle_upload.
     * @return string|WP_Error The URL of the uploaded file on success, or a WP_Error object on failure.
     */
    function mlr_handle_wp_upload($file_data, $overrides = []) {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        if (empty($file_data) || !is_array($file_data)) {
            return new WP_Error('no_file', 'فایلی برای آپلود انتخاب نشده است.');
        }

        $error_code = isset($file_data['error']) ? (int) $file_data['error'] : UPLOAD_ERR_NO_FILE;
        if ($error_code !== UPLOAD_ERR_OK) {
            $message = function_exists('mlr_get_upload_error_message')
                ? mlr_get_upload_error_message($error_code)
                : 'خطای آپلود فایل (کد: ' . $error_code . ')';

            return new WP_Error('upload_error', $message);
        }

        $filename_prefix = isset($overrides['mlr_filename_prefix']) ? (string) $overrides['mlr_filename_prefix'] : 'mlr-file';
        unset($overrides['mlr_filename_prefix']);

        if (function_exists('mlr_prepare_upload_file_name')) {
            $prepared_file = mlr_prepare_upload_file_name($file_data, $filename_prefix);
            if (is_wp_error($prepared_file)) {
                return $prepared_file;
            }

            $file_data = $prepared_file;
        }

        $default_overrides = [
            'test_form' => false,
            'mimes' => ['jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'],
        ];
        $final_overrides = array_merge($default_overrides, $overrides);

        $movefile = wp_handle_upload($file_data, $final_overrides);

        return ($movefile && !isset($movefile['error'])) ? $movefile['url'] : new WP_Error('wp_handle_upload_error', $movefile['error'] ?? 'خطای نامشخص در هنگام آپلود فایل.');
    }
}


// --- START: Request Events & Messages Helpers ---
if (!function_exists('mlr_verify_any_nonce')) {
    function mlr_verify_any_nonce($nonce_field = 'nonce') {
        $nonce = isset($_REQUEST[$nonce_field]) ? sanitize_text_field(wp_unslash($_REQUEST[$nonce_field])) : '';
        if (empty($nonce)) {
            return false;
        }
        // Try frontend nonce
        if (wp_verify_nonce($nonce, 'mlr_frontend_nonce')) return true;
        // Try admin nonce(s)
        if (wp_verify_nonce($nonce, 'mlr_ajax_nonce_action')) return true;
        if (wp_verify_nonce($nonce, 'mlr_ajax_nonce')) return true;
        if (wp_verify_nonce($nonce, 'mlr_user_requests_nonce')) return true;
        if (wp_verify_nonce($nonce, 'mlr_applicant_requests_nonce')) return true;
        return false;
    }
}

if (!function_exists('mlr_get_current_user_role_slug')) {
    function mlr_get_current_user_role_slug() {
        if (!is_user_logged_in()) return '';
        $u = wp_get_current_user();
        $roles = (array) $u->roles;
        return !empty($roles) ? sanitize_key($roles[0]) : '';
    }
}

if (!function_exists('mlr_is_applicant_user')) {
    function mlr_is_applicant_user($user_id = 0) {
        $user_id = $user_id ? absint($user_id) : get_current_user_id();
        if (!$user_id) {
            return false;
        }

        $user = get_userdata($user_id);
        return $user && in_array('mlr_applicant', (array) $user->roles, true);
    }
}

if (!function_exists('mlr_get_applicant_visible_installment_plans')) {
    function mlr_get_applicant_visible_installment_plans($args = []) {
        global $wpdb;

        $args = wp_parse_args($args, [
            'active_only' => true,
            'fields' => ['id', 'name', 'type', 'settings'],
            'order_by' => 'display_order',
        ]);

        $allowed_columns = ['id', 'name', 'type', 'settings', 'display_fields', 'display_fields_context', 'required_documents', 'is_active', 'display_order', 'created_at'];
        $requested_fields = is_array($args['fields']) ? $args['fields'] : ['id', 'name', 'type', 'settings'];
        if (!in_array('settings', $requested_fields, true)) {
            $requested_fields[] = 'settings';
        }
        $selected_fields = array_values(array_intersect($requested_fields, $allowed_columns));
        if (empty($selected_fields)) {
            $selected_fields = ['id', 'name', 'type', 'settings'];
        }

        $fields_sql = implode(', ', array_map(static function ($column) {
            return '`' . $column . '`';
        }, $selected_fields));

        $where = ['1=1'];
        if (!empty($args['active_only'])) {
            $where[] = 'is_active = 1';
        }

        switch ($args['order_by']) {
            case 'name_asc':
                $order_by_sql = 'ORDER BY `name` ASC';
                break;
            case 'id_asc':
                $order_by_sql = 'ORDER BY `id` ASC';
                break;
            case 'id_desc':
                $order_by_sql = 'ORDER BY `id` DESC';
                break;
            case 'display_order':
            default:
                $order_by_sql = mlr_get_installment_plan_order_by_sql();
                break;
        }

        $table_name = $wpdb->prefix . 'installment_plans';
        $plans = $wpdb->get_results("SELECT {$fields_sql} FROM `{$table_name}` WHERE " . implode(' AND ', $where) . " {$order_by_sql}");
        if (!is_array($plans)) {
            return [];
        }

        return array_values(array_filter($plans, static function ($plan) {
            return function_exists('mlr_is_installment_plan_visible_in_context')
                ? mlr_is_installment_plan_visible_in_context($plan, 'mlr_applicant_loan_request_form')
                : !empty(mlr_get_plan_settings_array($plan)['applicant_visible']);
        }));
    }
}

if (!function_exists('mlr_applicant_can_access_installment_plan')) {
    function mlr_applicant_can_access_installment_plan($plan_id, $active_only = true) {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0 || !is_user_logged_in()) {
            return false;
        }
        if (!current_user_can('manage_options') && !mlr_is_applicant_user()) {
            return false;
        }

        $plans = mlr_get_applicant_visible_installment_plans([
            'active_only' => (bool) $active_only,
            'fields' => ['id', 'settings'],
        ]);
        foreach ($plans as $plan) {
            if ((int) ($plan->id ?? 0) === $plan_id) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('mlr_is_applicant_origin_request')) {
    function mlr_is_applicant_origin_request($request_row) {
        if (!$request_row) {
            return false;
        }
        $origin = is_array($request_row) ? ($request_row['request_origin'] ?? '') : ($request_row->request_origin ?? '');
        return trim((string) $origin) === 'applicant_portal';
    }
}

if (!function_exists('mlr_get_request_acceptor_user_id')) {
    function mlr_get_request_acceptor_user_id($request_row) {
        if (!$request_row) {
            return 0;
        }

        $acceptor_user_id = is_array($request_row) ? ($request_row['acceptor_user_id'] ?? 0) : ($request_row->acceptor_user_id ?? 0);
        $acceptor_user_id = absint($acceptor_user_id);
        if ($acceptor_user_id > 0) {
            return $acceptor_user_id;
        }

        $origin = is_array($request_row) ? ($request_row['request_origin'] ?? '') : ($request_row->request_origin ?? '');
        if ($origin === '' || $origin === null || trim((string) $origin) === 'acceptor_portal') {
            $user_id = is_array($request_row) ? ($request_row['user_id'] ?? 0) : ($request_row->user_id ?? 0);
            return absint($user_id);
        }

        return 0;
    }
}

if (!function_exists('mlr_get_request_effective_acceptor_sql')) {
    function mlr_get_request_effective_acceptor_sql($table_alias = 'r') {
        $table_alias = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table_alias);
        if ($table_alias === '') {
            $table_alias = 'r';
        }

        return "(CASE WHEN COALESCE({$table_alias}.acceptor_user_id, 0) > 0 THEN {$table_alias}.acceptor_user_id WHEN COALESCE({$table_alias}.request_origin, 'acceptor_portal') <> 'applicant_portal' THEN {$table_alias}.user_id ELSE 0 END)";
    }
}

if (!function_exists('mlr_request_has_effective_acceptor')) {
    function mlr_request_has_effective_acceptor($request_row) {
        return function_exists('mlr_get_request_acceptor_user_id')
            ? mlr_get_request_acceptor_user_id($request_row) > 0
            : false;
    }
}

if (!function_exists('mlr_get_request_applicant_user_id')) {
    function mlr_get_request_applicant_user_id($request_row) {
        if (!$request_row) {
            return 0;
        }

        $applicant_user_id = is_array($request_row) ? ($request_row['applicant_user_id'] ?? 0) : ($request_row->applicant_user_id ?? 0);
        $applicant_user_id = absint($applicant_user_id);
        if ($applicant_user_id > 0) {
            return $applicant_user_id;
        }

        if (mlr_is_applicant_origin_request($request_row)) {
            $user_id = is_array($request_row) ? ($request_row['user_id'] ?? 0) : ($request_row->user_id ?? 0);
            return absint($user_id);
        }

        return 0;
    }
}

if (!function_exists('mlr_get_request_origin_label')) {
    function mlr_get_request_origin_label($request_row) {
        return mlr_is_applicant_origin_request($request_row)
            ? 'درخواست مستقیم متقاضی'
            : 'درخواست پذیرنده';
    }
}

if (!function_exists('mlr_get_applicant_portal_url')) {
    function mlr_get_applicant_portal_url() {
        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            's' => '[mlr_applicant_dashboard',
            'fields' => 'ids',
        ]);

        if (!empty($pages[0])) {
            return get_permalink($pages[0]);
        }

        $referer = wp_get_referer();
        if ($referer) {
            return wp_validate_redirect($referer, home_url('/'));
        }

        return home_url('/');
    }
}

if (!function_exists('mlr_get_acceptor_marketplace_settings')) {
    function mlr_get_acceptor_marketplace_settings() {
        $defaults = [
            'enabled' => 1,
            'allow_applicant_selected_acceptor' => 1,
            'allow_open_marketplace' => 1,
            'acceptor_cpt_slug' => 'seller',
            'acceptor_category_taxonomy' => 'seller-sub',
            'acceptor_location_taxonomy' => 'seller-cat',
            'require_category' => 1,
            'require_location' => 1,
            'allow_all_country' => 1,
            'show_claimed_cards_to_other_acceptors' => 1,
            'claimed_cards_visible_days' => 7,
            'enable_acceptor_invitation_links' => 1,
            'invitation_lock_days' => 30,
            'allow_override_existing_invitation_lock' => 0,
            'applicant_help_text' => '',
            'acceptor_shortcode_help_text' => '',
            'cities' => [],
            'activity_categories' => [],
        ];

        $settings = get_option('mlr_acceptor_marketplace_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }

        if (!array_key_exists('require_category', $settings) && array_key_exists('require_activity_category', $settings)) {
            $settings['require_category'] = !empty($settings['require_activity_category']) ? 1 : 0;
        }
        if (!array_key_exists('require_location', $settings) && array_key_exists('require_city_when_selected_scope', $settings)) {
            $settings['require_location'] = !empty($settings['require_city_when_selected_scope']) ? 1 : 0;
        }

        $settings = wp_parse_args($settings, $defaults);

        foreach ([
            'enabled',
            'allow_applicant_selected_acceptor',
            'allow_open_marketplace',
            'require_category',
            'require_location',
            'allow_all_country',
            'show_claimed_cards_to_other_acceptors',
            'enable_acceptor_invitation_links',
            'allow_override_existing_invitation_lock',
        ] as $bool_key) {
            $settings[$bool_key] = !empty($settings[$bool_key]) ? 1 : 0;
        }

        $settings['acceptor_cpt_slug'] = sanitize_key($settings['acceptor_cpt_slug'] ?: 'seller');
        $settings['acceptor_category_taxonomy'] = sanitize_key($settings['acceptor_category_taxonomy'] ?: 'seller-sub');
        $settings['acceptor_location_taxonomy'] = sanitize_key($settings['acceptor_location_taxonomy'] ?: 'seller-cat');
        $settings['claimed_cards_visible_days'] = max(1, min(90, absint($settings['claimed_cards_visible_days'])));
        $settings['invitation_lock_days'] = max(1, min(365, absint($settings['invitation_lock_days'])));
        $settings['applicant_help_text'] = sanitize_textarea_field((string) $settings['applicant_help_text']);
        $settings['acceptor_shortcode_help_text'] = sanitize_textarea_field((string) $settings['acceptor_shortcode_help_text']);

        foreach (['cities', 'activity_categories'] as $list_key) {
            if (!is_array($settings[$list_key])) {
                $settings[$list_key] = function_exists('mlr_marketplace_list_to_array')
                    ? mlr_marketplace_list_to_array($settings[$list_key])
                    : [];
            }
        }

        return $settings;
    }
}

if (!function_exists('mlr_marketplace_list_to_array')) {
    function mlr_marketplace_list_to_array($raw_value) {
        if (empty($raw_value)) {
            return [];
        }

        if (is_string($raw_value)) {
            $decoded = json_decode($raw_value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $raw_value = $decoded;
            } else {
                $maybe = maybe_unserialize($raw_value);
                if ($maybe !== $raw_value) {
                    $raw_value = $maybe;
                }
            }
        }

        if (is_string($raw_value)) {
            $raw_value = preg_split('/\s*[,،\n\r]+\s*/u', $raw_value, -1, PREG_SPLIT_NO_EMPTY);
        }

        if (!is_array($raw_value)) {
            return [];
        }

        $items = [];
        foreach ($raw_value as $item) {
            if (is_array($item)) {
                $item = reset($item);
            }
            if (!is_scalar($item)) {
                continue;
            }
            $item = trim(sanitize_text_field((string) $item));
            if ($item !== '') {
                $items[] = $item;
            }
        }

        return array_values(array_unique($items));
    }
}

if (!function_exists('mlr_get_acceptor_cpt_slug')) {
    function mlr_get_acceptor_cpt_slug() {
        $settings = function_exists('mlr_get_acceptor_marketplace_settings') ? mlr_get_acceptor_marketplace_settings() : [];
        return sanitize_key($settings['acceptor_cpt_slug'] ?? 'seller') ?: 'seller';
    }
}

if (!function_exists('mlr_get_acceptor_category_taxonomy')) {
    function mlr_get_acceptor_category_taxonomy() {
        $settings = function_exists('mlr_get_acceptor_marketplace_settings') ? mlr_get_acceptor_marketplace_settings() : [];
        $taxonomy = sanitize_key($settings['acceptor_category_taxonomy'] ?? 'seller-sub');
        return $taxonomy && taxonomy_exists($taxonomy) ? $taxonomy : '';
    }
}

if (!function_exists('mlr_get_acceptor_location_taxonomy')) {
    function mlr_get_acceptor_location_taxonomy() {
        $settings = function_exists('mlr_get_acceptor_marketplace_settings') ? mlr_get_acceptor_marketplace_settings() : [];
        $taxonomy = sanitize_key($settings['acceptor_location_taxonomy'] ?? 'seller-cat');
        return $taxonomy && taxonomy_exists($taxonomy) ? $taxonomy : '';
    }
}

if (!function_exists('mlr_get_acceptor_category_terms')) {
    function mlr_get_acceptor_category_terms($args = []) {
        $taxonomy = mlr_get_acceptor_category_taxonomy();
        if (!$taxonomy) {
            return [];
        }

        $terms = get_terms(wp_parse_args($args, [
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]));

        return is_wp_error($terms) ? [] : array_values($terms);
    }
}

if (!function_exists('mlr_get_acceptor_location_terms_tree')) {
    function mlr_get_acceptor_location_terms_tree() {
        $taxonomy = mlr_get_acceptor_location_taxonomy();
        if (!$taxonomy) {
            return [];
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);
        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        $nodes = [];
        foreach ($terms as $term) {
            $nodes[(int) $term->term_id] = [
                'term' => $term,
                'children' => [],
            ];
        }

        $tree = [];
        foreach ($nodes as $term_id => &$node) {
            $parent_id = (int) ($node['term']->parent ?? 0);
            if ($parent_id > 0 && isset($nodes[$parent_id])) {
                $nodes[$parent_id]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }
        unset($node);

        return $tree;
    }
}

if (!function_exists('mlr_flatten_acceptor_location_terms_tree')) {
    function mlr_flatten_acceptor_location_terms_tree($tree, $depth = 0) {
        $flat = [];
        foreach ((array) $tree as $node) {
            if (empty($node['term']) || !($node['term'] instanceof WP_Term)) {
                continue;
            }
            $flat[] = [
                'term' => $node['term'],
                'depth' => max(0, absint($depth)),
            ];
            if (!empty($node['children'])) {
                $flat = array_merge($flat, mlr_flatten_acceptor_location_terms_tree($node['children'], $depth + 1));
            }
        }
        return $flat;
    }
}

if (!function_exists('mlr_decode_marketplace_term_ids')) {
    function mlr_decode_marketplace_term_ids($raw_value, $taxonomy = '') {
        if (empty($raw_value)) {
            return [];
        }

        if (is_string($raw_value)) {
            $decoded = json_decode($raw_value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $raw_value = $decoded;
            } else {
                $maybe = maybe_unserialize($raw_value);
                if ($maybe !== $raw_value) {
                    $raw_value = $maybe;
                }
            }
        }

        if (is_string($raw_value)) {
            $raw_value = preg_split('/\s*[,،\n\r]+\s*/u', $raw_value, -1, PREG_SPLIT_NO_EMPTY);
        }

        if (!is_array($raw_value)) {
            return [];
        }

        $term_ids = [];
        foreach ($raw_value as $item) {
            if (is_array($item)) {
                $item = $item['term_id'] ?? $item['id'] ?? reset($item);
            }
            if (!is_scalar($item)) {
                continue;
            }

            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }

            if (ctype_digit($item)) {
                $term_ids[] = absint($item);
                continue;
            }

            if ($taxonomy && taxonomy_exists($taxonomy)) {
                $term = get_term_by('slug', sanitize_title($item), $taxonomy);
                if (!$term) {
                    $term = get_term_by('name', sanitize_text_field($item), $taxonomy);
                }
                if ($term && !is_wp_error($term)) {
                    $term_ids[] = absint($term->term_id);
                }
            }
        }

        if ($taxonomy && taxonomy_exists($taxonomy)) {
            $term_ids = array_values(array_filter(array_unique(array_map('absint', $term_ids)), static function ($term_id) use ($taxonomy) {
                return $term_id > 0 && term_exists($term_id, $taxonomy);
            }));
        } else {
            $term_ids = array_values(array_filter(array_unique(array_map('absint', $term_ids))));
        }

        return $term_ids;
    }
}

if (!function_exists('mlr_get_term_names_by_ids')) {
    function mlr_get_term_names_by_ids($term_ids, $taxonomy) {
        if (!$taxonomy || !taxonomy_exists($taxonomy)) {
            return [];
        }

        $names = [];
        foreach (array_values(array_unique(array_map('absint', (array) $term_ids))) as $term_id) {
            if ($term_id <= 0) {
                continue;
            }
            $term = get_term($term_id, $taxonomy);
            if ($term && !is_wp_error($term)) {
                $names[] = $term->name;
            }
        }

        return $names;
    }
}

if (!function_exists('mlr_get_request_activity_category_term_ids')) {
    function mlr_get_request_activity_category_term_ids($request_row) {
        $taxonomy = mlr_get_acceptor_category_taxonomy();
        $raw = is_array($request_row)
            ? ($request_row['requested_activity_categories'] ?? '')
            : ($request_row->requested_activity_categories ?? '');
        return mlr_decode_marketplace_term_ids($raw, $taxonomy);
    }
}

if (!function_exists('mlr_get_request_location_term_ids')) {
    function mlr_get_request_location_term_ids($request_row) {
        $taxonomy = mlr_get_acceptor_location_taxonomy();
        $raw = is_array($request_row)
            ? ($request_row['requested_location_terms'] ?? '')
            : ($request_row->requested_location_terms ?? '');

        $term_ids = mlr_decode_marketplace_term_ids($raw, $taxonomy);
        if (!empty($term_ids)) {
            return $term_ids;
        }

        $legacy_raw = is_array($request_row)
            ? ($request_row['requested_cities'] ?? '')
            : ($request_row->requested_cities ?? '');
        return mlr_decode_marketplace_term_ids($legacy_raw, $taxonomy);
    }
}

if (!function_exists('mlr_get_request_activity_category_names')) {
    function mlr_get_request_activity_category_names($request_row) {
        $taxonomy = mlr_get_acceptor_category_taxonomy();
        return mlr_get_term_names_by_ids(mlr_get_request_activity_category_term_ids($request_row), $taxonomy);
    }
}

if (!function_exists('mlr_get_request_location_names')) {
    function mlr_get_request_location_names($request_row) {
        $taxonomy = mlr_get_acceptor_location_taxonomy();
        $names = mlr_get_term_names_by_ids(mlr_get_request_location_term_ids($request_row), $taxonomy);
        if (!empty($names)) {
            return $names;
        }

        $legacy_raw = is_array($request_row)
            ? ($request_row['requested_cities'] ?? '')
            : ($request_row->requested_cities ?? '');
        return function_exists('mlr_marketplace_list_to_array') ? mlr_marketplace_list_to_array($legacy_raw) : [];
    }
}

if (!function_exists('mlr_expand_location_term_ids')) {
    function mlr_expand_location_term_ids($term_ids) {
        $taxonomy = mlr_get_acceptor_location_taxonomy();
        if (!$taxonomy) {
            return [];
        }

        $expanded = [];
        foreach (array_values(array_unique(array_map('absint', (array) $term_ids))) as $term_id) {
            if ($term_id <= 0 || !term_exists($term_id, $taxonomy)) {
                continue;
            }

            $expanded[] = $term_id;
            $ancestors = get_ancestors($term_id, $taxonomy, 'taxonomy');
            if (!empty($ancestors)) {
                $expanded = array_merge($expanded, array_map('absint', $ancestors));
            }

            $children = get_term_children($term_id, $taxonomy);
            if (!is_wp_error($children) && !empty($children)) {
                $expanded = array_merge($expanded, array_map('absint', $children));
            }
        }

        return array_values(array_unique(array_filter($expanded)));
    }
}

if (!function_exists('mlr_get_acceptor_user_id_from_seller_post')) {
    function mlr_get_acceptor_user_id_from_seller_post($post_id) {
        $post_id = absint($post_id);
        if ($post_id <= 0) {
            return 0;
        }

        foreach ([
            'acceptor_user_id',
            '_acceptor_user_id',
            'mlr_acceptor_user_id',
            '_mlr_acceptor_user_id',
            'seller_user_id',
            '_seller_user_id',
            'user_id',
            '_user_id',
            'vendor_id',
            '_vendor_id',
        ] as $meta_key) {
            $candidate = absint(get_post_meta($post_id, $meta_key, true));
            if ($candidate > 0 && get_userdata($candidate)) {
                return $candidate;
            }
        }

        $post = get_post($post_id);
        return $post ? absint($post->post_author) : 0;
    }
}

if (!function_exists('mlr_get_acceptor_profile_post_id')) {
    function mlr_get_acceptor_profile_post_id($acceptor_user_id) {
        $acceptor_user_id = absint($acceptor_user_id);
        $post_type = mlr_get_acceptor_cpt_slug();
        if ($acceptor_user_id <= 0 || !$post_type || !post_type_exists($post_type)) {
            return 0;
        }

        foreach ([
            'mlr_acceptor_profile_post_id',
            'acceptor_profile_post_id',
            'mlr_seller_post_id',
            'seller_post_id',
        ] as $meta_key) {
            $post_id = absint(get_user_meta($acceptor_user_id, $meta_key, true));
            if ($post_id > 0 && get_post_type($post_id) === $post_type) {
                return $post_id;
            }
        }

        $meta_query = ['relation' => 'OR'];
        foreach ([
            'acceptor_user_id',
            '_acceptor_user_id',
            'mlr_acceptor_user_id',
            '_mlr_acceptor_user_id',
            'seller_user_id',
            '_seller_user_id',
            'user_id',
            '_user_id',
            'vendor_id',
            '_vendor_id',
        ] as $meta_key) {
            $meta_query[] = [
                'key' => $meta_key,
                'value' => $acceptor_user_id,
                'compare' => '=',
            ];
        }

        $posts = get_posts([
            'post_type' => $post_type,
            'post_status' => ['publish', 'private', 'draft', 'pending'],
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => $meta_query,
            'orderby' => 'ID',
            'order' => 'DESC',
        ]);
        if (!empty($posts[0])) {
            return absint($posts[0]);
        }

        $posts = get_posts([
            'post_type' => $post_type,
            'post_status' => ['publish', 'private', 'draft', 'pending'],
            'posts_per_page' => 1,
            'fields' => 'ids',
            'author' => $acceptor_user_id,
            'orderby' => 'ID',
            'order' => 'DESC',
        ]);

        return !empty($posts[0]) ? absint($posts[0]) : 0;
    }
}

if (!function_exists('mlr_get_acceptor_terms_from_seller_post')) {
    function mlr_get_acceptor_terms_from_seller_post($acceptor_user_id, $taxonomy) {
        $post_id = mlr_get_acceptor_profile_post_id($acceptor_user_id);
        if ($post_id <= 0 || !$taxonomy || !taxonomy_exists($taxonomy)) {
            return [];
        }

        $terms = get_the_terms($post_id, $taxonomy);
        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        $rows = [];
        foreach ($terms as $term) {
            $rows[] = [
                'term_id' => absint($term->term_id),
                'name' => $term->name,
                'slug' => $term->slug,
                'parent' => absint($term->parent ?? 0),
            ];
        }

        return $rows;
    }
}

if (!function_exists('mlr_get_acceptor_categories_from_seller_post')) {
    function mlr_get_acceptor_categories_from_seller_post($acceptor_user_id) {
        return mlr_get_acceptor_terms_from_seller_post($acceptor_user_id, mlr_get_acceptor_category_taxonomy());
    }
}

if (!function_exists('mlr_get_acceptor_locations_from_seller_post')) {
    function mlr_get_acceptor_locations_from_seller_post($acceptor_user_id) {
        return mlr_get_acceptor_terms_from_seller_post($acceptor_user_id, mlr_get_acceptor_location_taxonomy());
    }
}

if (!function_exists('mlr_get_acceptor_category_term_ids')) {
    function mlr_get_acceptor_category_term_ids($acceptor_user_id) {
        return array_values(array_unique(array_filter(array_map('absint', wp_list_pluck(mlr_get_acceptor_categories_from_seller_post($acceptor_user_id), 'term_id')))));
    }
}

if (!function_exists('mlr_get_acceptor_location_term_ids')) {
    function mlr_get_acceptor_location_term_ids($acceptor_user_id) {
        return array_values(array_unique(array_filter(array_map('absint', wp_list_pluck(mlr_get_acceptor_locations_from_seller_post($acceptor_user_id), 'term_id')))));
    }
}

if (!function_exists('mlr_get_or_create_acceptor_invitation_code')) {
    function mlr_get_or_create_acceptor_invitation_code($acceptor_user_id) {
        $acceptor_user_id = absint($acceptor_user_id);
        if ($acceptor_user_id <= 0 || !get_userdata($acceptor_user_id)) {
            return '';
        }

        $code = trim((string) get_user_meta($acceptor_user_id, 'mlr_acceptor_invitation_code', true));
        if ($code !== '') {
            return $code;
        }

        for ($i = 0; $i < 8; $i++) {
            $candidate = strtolower(wp_generate_password(20, false, false));
            if (!mlr_resolve_acceptor_invitation_code($candidate)) {
                update_user_meta($acceptor_user_id, 'mlr_acceptor_invitation_code', $candidate);
                return $candidate;
            }
        }

        return '';
    }
}

if (!function_exists('mlr_get_applicant_form_url')) {
    function mlr_get_applicant_form_url() {
        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            's' => '[mlr_applicant_loan_request_form',
            'fields' => 'ids',
        ]);

        if (!empty($pages[0])) {
            return get_permalink($pages[0]);
        }

        $referer = wp_get_referer();
        if ($referer) {
            return wp_validate_redirect($referer, home_url('/'));
        }

        return home_url('/');
    }
}

if (!function_exists('mlr_get_acceptor_invitation_url')) {
    function mlr_get_acceptor_invitation_url($acceptor_user_id) {
        $code = mlr_get_or_create_acceptor_invitation_code($acceptor_user_id);
        if ($code === '') {
            return '';
        }

        return add_query_arg('mlr_invite', rawurlencode($code), mlr_get_applicant_form_url());
    }
}

if (!function_exists('mlr_resolve_acceptor_invitation_code')) {
    function mlr_resolve_acceptor_invitation_code($code) {
        $code = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $code);
        if ($code === '') {
            return 0;
        }

        $users = get_users([
            'meta_key' => 'mlr_acceptor_invitation_code',
            'meta_value' => $code,
            'number' => 1,
            'fields' => 'ID',
        ]);

        return !empty($users[0]) ? absint($users[0]) : 0;
    }
}

if (!function_exists('mlr_get_current_acceptor_invitation_code')) {
    function mlr_get_current_acceptor_invitation_code() {
        $code = isset($_GET['mlr_invite']) ? wp_unslash($_GET['mlr_invite']) : ($_COOKIE['mlr_acceptor_invitation_code'] ?? '');
        return preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $code);
    }
}

if (!function_exists('mlr_set_locked_acceptor_for_applicant')) {
    function mlr_set_locked_acceptor_for_applicant($applicant_user_id, $acceptor_user_id, $code) {
        $applicant_user_id = absint($applicant_user_id);
        $acceptor_user_id = absint($acceptor_user_id);
        $code = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $code);
        if ($applicant_user_id <= 0 || $acceptor_user_id <= 0 || $code === '') {
            return false;
        }

        $settings = function_exists('mlr_get_acceptor_marketplace_settings') ? mlr_get_acceptor_marketplace_settings() : [];
        $lock_days = max(1, absint($settings['invitation_lock_days'] ?? 30));
        update_user_meta($applicant_user_id, 'mlr_locked_acceptor_user_id', $acceptor_user_id);
        update_user_meta($applicant_user_id, 'mlr_locked_acceptor_invitation_code', $code);
        update_user_meta($applicant_user_id, 'mlr_locked_acceptor_expires_at', time() + ($lock_days * DAY_IN_SECONDS));

        return true;
    }
}

if (!function_exists('mlr_clear_locked_acceptor_for_applicant')) {
    function mlr_clear_locked_acceptor_for_applicant($applicant_user_id) {
        $applicant_user_id = absint($applicant_user_id);
        if ($applicant_user_id <= 0) {
            return;
        }

        delete_user_meta($applicant_user_id, 'mlr_locked_acceptor_user_id');
        delete_user_meta($applicant_user_id, 'mlr_locked_acceptor_invitation_code');
        delete_user_meta($applicant_user_id, 'mlr_locked_acceptor_expires_at');
    }
}

if (!function_exists('mlr_get_locked_acceptor_for_current_applicant')) {
    function mlr_get_locked_acceptor_for_current_applicant() {
        $settings = function_exists('mlr_get_acceptor_marketplace_settings') ? mlr_get_acceptor_marketplace_settings() : [];
        if (empty($settings['enable_acceptor_invitation_links'])) {
            return 0;
        }

        $current_user_id = is_user_logged_in() ? get_current_user_id() : 0;
        if ($current_user_id > 0) {
            $locked_acceptor_id = absint(get_user_meta($current_user_id, 'mlr_locked_acceptor_user_id', true));
            $expires_at = absint(get_user_meta($current_user_id, 'mlr_locked_acceptor_expires_at', true));
            if ($locked_acceptor_id > 0 && $expires_at >= time()) {
                return $locked_acceptor_id;
            }
            if ($locked_acceptor_id > 0 && $expires_at > 0 && $expires_at < time()) {
                mlr_clear_locked_acceptor_for_applicant($current_user_id);
            }
        }

        $code = mlr_get_current_acceptor_invitation_code();
        $invited_acceptor_id = $code !== '' ? mlr_resolve_acceptor_invitation_code($code) : 0;
        if ($invited_acceptor_id <= 0) {
            return 0;
        }

        if ($current_user_id > 0) {
            $existing_acceptor_id = absint(get_user_meta($current_user_id, 'mlr_locked_acceptor_user_id', true));
            if ($existing_acceptor_id > 0 && empty($settings['allow_override_existing_invitation_lock'])) {
                return $existing_acceptor_id;
            }
            mlr_set_locked_acceptor_for_applicant($current_user_id, $invited_acceptor_id, $code);
        }

        return $invited_acceptor_id;
    }
}

if (!function_exists('mlr_maybe_capture_acceptor_invitation')) {
    function mlr_maybe_capture_acceptor_invitation() {
        if (empty($_GET['mlr_invite'])) {
            return;
        }

        $code = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) wp_unslash($_GET['mlr_invite']));
        if ($code === '' || !mlr_resolve_acceptor_invitation_code($code)) {
            return;
        }

        $settings = function_exists('mlr_get_acceptor_marketplace_settings') ? mlr_get_acceptor_marketplace_settings() : [];
        $lock_days = max(1, absint($settings['invitation_lock_days'] ?? 30));
        setcookie('mlr_acceptor_invitation_code', $code, time() + ($lock_days * DAY_IN_SECONDS), COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), false);
        $_COOKIE['mlr_acceptor_invitation_code'] = $code;

        if (is_user_logged_in()) {
            $current_user_id = get_current_user_id();
            $existing_acceptor_id = absint(get_user_meta($current_user_id, 'mlr_locked_acceptor_user_id', true));
            if ($existing_acceptor_id <= 0 || !empty($settings['allow_override_existing_invitation_lock'])) {
                mlr_set_locked_acceptor_for_applicant($current_user_id, mlr_resolve_acceptor_invitation_code($code), $code);
            }
        }
    }
    add_action('init', 'mlr_maybe_capture_acceptor_invitation');
}

if (!function_exists('mlr_get_acceptor_display_name')) {
    function mlr_get_acceptor_display_name($acceptor_user_id) {
        $acceptor_user_id = absint($acceptor_user_id);
        if ($acceptor_user_id <= 0) {
            return '';
        }

        if (function_exists('mlr_get_acceptor_profile_post_id')) {
            $post_id = mlr_get_acceptor_profile_post_id($acceptor_user_id);
            if ($post_id > 0) {
                $title = trim((string) get_the_title($post_id));
                if ($title !== '') {
                    return $title;
                }
            }
        }

        $shop_name = trim((string) get_user_meta($acceptor_user_id, 'mlr_acceptor_shop_name', true));
        if ($shop_name === '') {
            $shop_name = trim((string) get_user_meta($acceptor_user_id, 'shopppp', true));
        }
        if ($shop_name !== '') {
            return $shop_name;
        }

        $user = get_userdata($acceptor_user_id);
        return $user ? $user->display_name : '';
    }
}

if (!function_exists('mlr_user_has_role')) {
    function mlr_user_has_role($user_id, $role) {
        $user_id = absint($user_id);
        $role = sanitize_key($role);
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $roles = (array) $user->roles;
        if (in_array($role, $roles, true)) {
            return true;
        }

        if ($role === 'acceptor' && array_intersect($roles, ['pazirande'])) {
            return true;
        }

        if ($role === 'acceptor' && function_exists('mlr_get_acceptor_profile_post_id')) {
            return mlr_get_acceptor_profile_post_id($user_id) > 0;
        }

        return false;
    }
}

if (!function_exists('mlr_get_acceptor_marketplace_status_text')) {
    function mlr_get_acceptor_marketplace_status_text($request_row) {
        $mode = is_array($request_row) ? ($request_row['acceptor_assignment_mode'] ?? '') : ($request_row->acceptor_assignment_mode ?? '');
        $status = is_array($request_row) ? ($request_row['marketplace_status'] ?? '') : ($request_row->marketplace_status ?? '');
        $acceptor_user_id = is_array($request_row) ? ($request_row['acceptor_user_id'] ?? 0) : ($request_row->acceptor_user_id ?? 0);

        if ($mode === 'open_acceptor_marketplace' && $status === 'open' && absint($acceptor_user_id) <= 0) {
            return 'در انتظار پذیرنده';
        }

        if ($mode === 'open_acceptor_marketplace' && $status === 'claimed') {
            return 'پذیرفته شده توسط پذیرنده';
        }

        if ($mode === 'applicant_selected_acceptor' && absint($acceptor_user_id) > 0) {
            return 'پذیرنده انتخاب شده';
        }

        if (absint($acceptor_user_id) > 0) {
            return 'پذیرنده دارد';
        }

        return 'بدون پذیرنده';
    }
}

if (!function_exists('mlr_acceptor_matches_applicant_request')) {
    function mlr_acceptor_matches_applicant_request($acceptor_user_id, $request_row, $return_reason = false) {
        $finish = static function ($matched, $code, $message, $extra = []) use ($return_reason) {
            if ($return_reason) {
                return array_merge([
                    'matched' => (bool) $matched,
                    'code' => $code,
                    'message' => $message,
                ], $extra);
            }
            return (bool) $matched;
        };

        $acceptor_user_id = absint($acceptor_user_id);
        if ($acceptor_user_id <= 0 || !$request_row) {
            return $finish(false, 'invalid_input', 'پذیرنده یا پرونده معتبر نیست.');
        }

        $is_admin_user = user_can($acceptor_user_id, 'manage_options');
        $is_acceptor_user = function_exists('mlr_user_has_role') && mlr_user_has_role($acceptor_user_id, 'acceptor');
        if (!$is_admin_user && !$is_acceptor_user) {
            return $finish(false, 'not_acceptor', 'کاربر نقش پذیرنده ندارد.');
        }

        if ($is_admin_user && !$is_acceptor_user) {
            return $finish(true, 'admin_bypass', 'ادمین همه درخواست‌های بازار را مشاهده می‌کند.');
        }

        if (get_user_meta($acceptor_user_id, 'mlr_acceptor_marketplace_enabled', true) === '0') {
            return $finish(false, 'marketplace_disabled', 'بازار پذیرنده برای این کاربر غیرفعال است.');
        }

        $seller_post_id = function_exists('mlr_get_acceptor_profile_post_id') ? mlr_get_acceptor_profile_post_id($acceptor_user_id) : 0;
        if ($seller_post_id <= 0) {
            return $finish(false, 'seller_missing', 'پست seller متصل به پذیرنده پیدا نشد.');
        }

        $plan_id = absint(is_array($request_row) ? ($request_row['installment_plan_id'] ?? 0) : ($request_row->installment_plan_id ?? 0));
        if ($plan_id > 0 && function_exists('mlr_user_can_access_installment_plan') && !mlr_user_can_access_installment_plan($acceptor_user_id, $plan_id, true)) {
            return $finish(false, 'plan_not_allowed', 'پذیرنده به طرح این درخواست دسترسی ندارد.');
        }

        $settings = function_exists('mlr_get_acceptor_marketplace_settings') ? mlr_get_acceptor_marketplace_settings() : [];
        $request_category_ids = function_exists('mlr_get_request_activity_category_term_ids') ? mlr_get_request_activity_category_term_ids($request_row) : [];
        $acceptor_category_ids = function_exists('mlr_get_acceptor_category_term_ids') ? mlr_get_acceptor_category_term_ids($acceptor_user_id) : [];

        if (!empty($settings['require_category']) && empty($request_category_ids)) {
            return $finish(false, 'request_category_missing', 'حوزه فعالیت درخواست مشخص نیست.');
        }
        if (!empty($request_category_ids)) {
            if (empty($acceptor_category_ids)) {
                return $finish(false, 'acceptor_category_missing', 'seller پذیرنده حوزه فعالیت ندارد.');
            }
            if (empty(array_intersect($request_category_ids, $acceptor_category_ids))) {
                return $finish(false, 'category_mismatch', 'حوزه فعالیت درخواست با seller پذیرنده همخوان نیست.', [
                    'request_categories' => $request_category_ids,
                    'acceptor_categories' => $acceptor_category_ids,
                ]);
            }
        }

        $request_scope = is_array($request_row) ? ($request_row['requested_service_scope'] ?? '') : ($request_row->requested_service_scope ?? '');
        $request_scope = in_array($request_scope, ['selected_locations', 'selected_cities'], true) ? 'selected_locations' : 'all_country';

        if ($request_scope === 'selected_locations') {
            $request_location_ids = function_exists('mlr_get_request_location_term_ids') ? mlr_get_request_location_term_ids($request_row) : [];
            $acceptor_location_ids = function_exists('mlr_get_acceptor_location_term_ids') ? mlr_get_acceptor_location_term_ids($acceptor_user_id) : [];

            if (!empty($settings['require_location']) && empty($request_location_ids)) {
                return $finish(false, 'request_location_missing', 'موقعیت جغرافیایی درخواست مشخص نیست.');
            }
            if (!empty($request_location_ids)) {
                if (empty($acceptor_location_ids)) {
                    return $finish(false, 'acceptor_location_missing', 'seller پذیرنده موقعیت جغرافیایی ندارد.');
                }

                $request_location_expanded = function_exists('mlr_expand_location_term_ids') ? mlr_expand_location_term_ids($request_location_ids) : $request_location_ids;
                $acceptor_location_expanded = function_exists('mlr_expand_location_term_ids') ? mlr_expand_location_term_ids($acceptor_location_ids) : $acceptor_location_ids;
                if (empty(array_intersect($request_location_expanded, $acceptor_location_expanded))) {
                    return $finish(false, 'location_mismatch', 'موقعیت جغرافیایی درخواست با seller پذیرنده همخوان نیست.', [
                        'request_locations' => $request_location_ids,
                        'acceptor_locations' => $acceptor_location_ids,
                    ]);
                }
            }
        }

        $max_amount = (float) get_user_meta($acceptor_user_id, 'mlr_acceptor_max_facility_amount', true);
        $request_amount = (float) (is_array($request_row) ? ($request_row['facility_amount'] ?? ($request_row['loan_amount'] ?? 0)) : ($request_row->facility_amount ?? ($request_row->loan_amount ?? 0)));
        if ($max_amount > 0 && $request_amount > $max_amount) {
            return $finish(false, 'amount_over_limit', 'مبلغ درخواست از سقف پذیرنده بیشتر است.');
        }

        return $finish(true, 'matched', 'درخواست با پذیرنده همخوان است.');
    }
}

if (!function_exists('mlr_can_access_loan_request')) {
    function mlr_can_access_loan_request($request_row, $user_id = 0) {
        if (!$request_row) {
            return false;
        }

        if (is_numeric($request_row)) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'loan_requests';
            $request_row = $wpdb->get_row($wpdb->prepare(
                "SELECT id, user_id, applicant_user_id, acceptor_user_id, request_origin, assigned_bank_user_id FROM {$table_name} WHERE id = %d",
                absint($request_row)
            ));
            if (!$request_row) {
                return false;
            }
        }

        $uid = absint($user_id);
        if ($uid <= 0) {
            if (!is_user_logged_in()) {
                return false;
            }
            $uid = get_current_user_id();
        }

        $user = get_userdata($uid);
        if (!$user) {
            return false;
        }

        if (user_can($uid, 'manage_options')) {
            return true;
        }

        $is_bank = in_array('bank', (array) $user->roles, true);
        if ($is_bank) {
            $assigned_bank_user_id = is_array($request_row) ? ($request_row['assigned_bank_user_id'] ?? 0) : ($request_row->assigned_bank_user_id ?? 0);
            return (intval($assigned_bank_user_id) === intval($uid));
        }

        $request_user_id = is_array($request_row) ? ($request_row['user_id'] ?? 0) : ($request_row->user_id ?? 0);
        if (intval($request_user_id) === intval($uid)) {
            return true;
        }
        if (function_exists('mlr_get_request_applicant_user_id') && mlr_get_request_applicant_user_id($request_row) === intval($uid)) {
            return true;
        }
        if (function_exists('mlr_get_request_acceptor_user_id') && mlr_get_request_acceptor_user_id($request_row) === intval($uid)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('mlr_add_request_event')) {
    function mlr_add_request_event($request_id, $event_type, $details = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'mlr_request_events';

        $user_id = is_user_logged_in() ? get_current_user_id() : null;
        $role = is_user_logged_in() ? mlr_get_current_user_role_slug() : '';

        $wpdb->insert($table, [
            'request_id' => absint($request_id),
            'user_id'    => $user_id ? absint($user_id) : null,
            'user_role'  => $role,
            'event_type' => sanitize_text_field($event_type),
            'details'    => is_scalar($details) ? wp_kses_post((string)$details) : wp_json_encode($details, JSON_UNESCAPED_UNICODE),
            'created_at' => current_time('mysql'),
        ], [
            '%d','%d','%s','%s','%s','%s'
        ]);
    }
}

if (!function_exists('mlr_get_request_events')) {
    function mlr_get_request_events($request_id, $limit = 200) {
        global $wpdb;
        $table = $wpdb->prefix . 'mlr_request_events';
        $limit = max(1, min(500, intval($limit)));
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE request_id = %d ORDER BY id ASC LIMIT %d", absint($request_id), $limit));
    }
}

if (!function_exists('mlr_add_request_message')) {
    function mlr_add_request_message($request_id, $message, $attachments_json = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'mlr_request_messages';

        $user_id = is_user_logged_in() ? get_current_user_id() : null;
        $role = is_user_logged_in() ? mlr_get_current_user_role_slug() : '';

        $data = [
            'request_id' => absint($request_id),
            'user_id'    => $user_id ? absint($user_id) : null,
            'user_role'  => $role,
            'message'    => wp_kses_post((string)$message),
            'created_at' => current_time('mysql'),
        ];
        $formats = ['%d', '%d', '%s', '%s', '%s'];

        if ($attachments_json !== null) {
            $data['attachments'] = $attachments_json;
            $formats[] = '%s';
        }

        $result = $wpdb->insert($table, $data, $formats);

        if ($result === false && function_exists('mlr_log')) {
            mlr_log("Error inserting request message: " . $wpdb->last_error);
        }

        return $wpdb->insert_id ? intval($wpdb->insert_id) : 0;
    }
}

if (!function_exists('mlr_get_request_messages')) {
    function mlr_get_request_messages($request_id, $limit = 500) {
        global $wpdb;
        $table = $wpdb->prefix . 'mlr_request_messages';
        $limit = max(1, min(1000, intval($limit)));
        // Changed ASC to DESC to fetch newest messages first
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE request_id = %d ORDER BY id DESC LIMIT %d", absint($request_id), $limit));
    }
}

if (!function_exists('mlr_render_request_messages_html')) {
    function mlr_render_request_messages_html($messages) {
        if (!is_array($messages) || empty($messages)) {
            return '<p style="text-align:center;color:#999;padding:10px;">پیامی وجود ندارد.</p>';
        }
        $out = '';
        foreach ($messages as $msg) {
            $author = 'کاربر';
            if (!empty($msg->user_id)) {
                $u = get_user_by('id', intval($msg->user_id));
                if ($u) {
                    $author = $u->display_name;
                }
            }
            $role = !empty($msg->user_role) ? esc_html($msg->user_role) : '';
            $date = !empty($msg->created_at) ? $msg->created_at : '';
            if (function_exists('mlr_gregorian_to_jalali') && !empty($date)) {
                $date = mlr_gregorian_to_jalali($date, 'Y/m/d H:i');
            }
            $out .= '<div class="mlr-comment-bubble mlr-message-bubble">';
            $out .= '<div class="mlr-comment-header">';
            $out .= '<strong class="mlr-comment-author">' . esc_html($author) . ($role ? ' <span style="opacity:.7;font-size:12px">(' . $role . ')</span>' : '') . '</strong>';
            $out .= '<span class="mlr-comment-date">' . esc_html($date) . '</span>';
            $out .= '</div>';
            $out .= '<div class="mlr-comment-body">' . wpautop(wp_kses_post($msg->message)) . '</div>';

            // --- START: Added Attachment Rendering for Frontend ---
            $attachments_json = $msg->attachments ?? null;
            $attachments = !empty($attachments_json) ? json_decode($attachments_json, true) : [];
            if (!empty($attachments) && is_array($attachments)) {
                $out .= '<div class="mlr-conv-attachments">';
                $out .= '<strong class="mlr-attachments-title">پیوست‌ها:</strong>';
                $out .= '<div class="mlr-attachments-list">';
                foreach ($attachments as $file) {
                    $file_url = $file['url'] ?? $file;
                    $file_name = $file['name'] ?? basename($file_url);
                    $out .= '<a href="' . esc_url($file_url) . '" target="_blank" class="mlr-view-file-btn"><span class="dashicons dashicons-download"></span> ' . esc_html($file_name) . '</a>';
                }
                $out .= '</div></div>';
            }
            // --- END: Added Attachment Rendering ---

            $out .= '</div>';
        }
        return $out;
    }
}

if (!function_exists('mlr_render_request_conversation_html')) {
    function mlr_render_request_conversation_html($comments, $messages) {
        $timeline = [];

        if (is_array($comments) && !empty($comments)) {
            foreach ($comments as $c) {
                if (!is_array($c)) { continue; }
                $ts_raw = isset($c['timestamp']) ? (string)$c['timestamp'] : '';
                $ts = $ts_raw ? strtotime($ts_raw) : 0;
                $timeline[] = [
                    'type' => 'note',
                    'ts' => $ts ?: 0,
                    'ts_raw' => $ts_raw,
                    'author' => isset($c['display_name']) ? (string)$c['display_name'] : 'کاربر',
                    'role' => 'note',
                    'message' => isset($c['text']) ? (string)$c['text'] : '',
                    'attachments' => isset($c['attachments']) && is_array($c['attachments']) ? wp_json_encode($c['attachments']) : null,
                ];
            }
        }

        if (is_array($messages) && !empty($messages)) {
            foreach ($messages as $m) {
                if (!is_object($m)) { continue; }
                $author = 'کاربر';
                if (!empty($m->user_id)) {
                    $u = get_user_by('id', intval($m->user_id));
                    if ($u) { $author = $u->display_name; }
                }
                $ts_raw = !empty($m->created_at) ? (string)$m->created_at : '';
                $ts = $ts_raw ? strtotime($ts_raw) : 0;
                $timeline[] = [
                    'type' => 'message',
                    'ts' => $ts ?: 0,
                    'ts_raw' => $ts_raw,
                    'author' => $author,
                    'role' => !empty($m->user_role) ? (string)$m->user_role : '',
                    'message' => !empty($m->message) ? (string)$m->message : '',
                    'attachments' => !empty($m->attachments) ? (string)$m->attachments : null,
                ];
            }
        }

        if (empty($timeline)) {
            return '<p style="text-align:center;color:#999;padding:10px;">گفتگویی وجود ندارد.</p>';
        }

        usort($timeline, function($a, $b){
            $at = isset($a['ts']) ? intval($a['ts']) : 0;
            $bt = isset($b['ts']) ? intval($b['ts']) : 0;
            if ($at === $bt) { return 0; }
            // Changed < to > for DESC sort (Newest first)
            return ($at > $bt) ? -1 : 1;
        });

        $out = '';
        foreach ($timeline as $item) {
            $date = !empty($item['ts_raw']) ? $item['ts_raw'] : '';
            if (function_exists('mlr_gregorian_to_jalali') && !empty($date)) {
                $date = mlr_gregorian_to_jalali($date, 'Y/m/d H:i');
            }
            $badge = ($item['type'] === 'note') ? 'یادداشت' : 'پیام';
            $badge_class = ($item['type'] === 'note') ? 'mlr-conv-badge-note' : 'mlr-conv-badge-message';

            $out .= '<div class="mlr-comment-bubble mlr-conv-bubble mlr-conv-type-' . esc_attr($item['type']) . '">';
            $out .= '<div class="mlr-comment-header">';
            $out .= '<strong class="mlr-comment-author">' . esc_html($item['author']) . '</strong>';
            $out .= '<span class="mlr-comment-date">' . esc_html($date) . '</span>';
            $out .= '</div>';
            $out .= '<div class="mlr-conv-meta"><span class="mlr-conv-badge ' . esc_attr($badge_class) . '">' . esc_html($badge) . '</span>';
            if (!empty($item['role']) && $item['type'] !== 'note') {
                $out .= ' <span class="mlr-conv-role">(' . esc_html($item['role']) . ')</span>';
            }
            $out .= '</div>';
            if ($item['type'] === 'note') {
                $out .= '<div class="mlr-comment-body">' . nl2br(esc_html($item['message'])) . '</div>';
            } else {
                $out .= '<div class="mlr-comment-body">' . wpautop(wp_kses_post($item['message'])) . '</div>';
            }

            // Render attachments for messages
            $attachments_json = $item['attachments'] ?? null;
            $attachments = !empty($attachments_json) ? json_decode($attachments_json, true) : [];
            if (!empty($attachments) && is_array($attachments)) {
                $out .= '<div class="mlr-conv-attachments">';
                $out .= '<strong class="mlr-attachments-title">پیوست‌ها:</strong>';
                $out .= '<div class="mlr-attachments-list">';
                foreach ($attachments as $file) {
                    $file_url = $file['url'] ?? $file; // Support old and new format
                    $file_name = $file['name'] ?? basename($file_url);
                    $out .= '<a href="' . esc_url($file_url) . '" target="_blank" class="mlr-view-file-btn"><span class="dashicons dashicons-download"></span> ' . esc_html($file_name) . '</a>';
                }
                $out .= '</div></div>';
            }

            $out .= '</div>';
        }

        return $out;
    }
}


if (!function_exists('mlr_render_request_events_html')) {
    function mlr_render_request_events_html($events) {
        if (!is_array($events) || empty($events)) {
            return '<p style="text-align:center;color:#999;padding:10px;">رویدادی ثبت نشده است.</p>';
        }
        $out = '<ul class="mlr-events-timeline" style="list-style:none; margin:0; padding:0;">';
        foreach ($events as $ev) {
            $date = !empty($ev->created_at) ? $ev->created_at : '';
            if (function_exists('mlr_gregorian_to_jalali') && !empty($date)) {
                $date = mlr_gregorian_to_jalali($date, 'Y/m/d H:i');
            }
            
            // Use 'details' column instead of 'description'
            $desc = !empty($ev->details) ? $ev->details : '';
            
            // Fallback if details is empty
            if (empty($desc) && !empty($ev->event_type)) {
                $desc = 'رویداد سیستمی (' . $ev->event_type . ')';
            }

            // Try to decode if it's JSON (though usually it's plain text)
            if (is_string($desc) && (substr($desc, 0, 1) === '{' || substr($desc, 0, 1) === '[')) {
                $decoded = json_decode($desc, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    if (is_array($decoded)) {
                        $desc = implode(', ', array_map(function($k, $v) { return "$k: $v"; }, array_keys($decoded), $decoded));
                    } else {
                        $desc = (string)$decoded;
                    }
                }
            }

            // User info
            $user_label = '';
            if (!empty($ev->user_id)) {
                $u = get_user_by('id', $ev->user_id);
                if ($u) {
                    $user_label = ' <span style="font-size:11px; color:#999; margin-right:5px;">(توسط: ' . esc_html($u->display_name) . ')</span>';
                }
            } elseif (!empty($ev->user_role)) {
                 $user_label = ' <span style="font-size:11px; color:#999; margin-right:5px;">(' . esc_html($ev->user_role) . ')</span>';
            }

            $out .= '<li style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;">';
            $out .= '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">';
            $out .= '<strong style="font-size:13px; color:#333;">' . wp_kses_post($desc) . '</strong>';
            $out .= '<span style="font-size:11px; color:#999; direction:ltr;">' . esc_html($date) . '</span>';
            $out .= '</div>';
            if ($user_label) {
                 $out .= '<div style="font-size:11px; color:#777;">' . $user_label . '</div>';
            }
            $out .= '</li>';
        }
        $out .= '</ul>';
        return $out;
    }
}

if (!function_exists('mlr_create_notification')) {
    /**
     * Create an admin notification for all admins. Call on: new loan, new cooperation,
     * new message, new note, status change, etc. Table is ensured by mlr_maybe_create_required_tables().
     */
    function mlr_create_notification($type, $message, $link = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'mlr_admin_notifications';

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            if (function_exists('mlr_log')) {
                mlr_log('mlr_create_notification: table missing ' . $table);
            }
            return false;
        }

        $data = [
            'type'       => sanitize_text_field($type),
            'message'    => wp_kses_post($message),
            'link'       => esc_url_raw($link),
            'is_read'    => 0,
            'read_by'    => '[]',
            'created_at' => current_time('mysql'),
        ];
        $formats = ['%s', '%s', '%s', '%d', '%s', '%s'];

        // If read_by column is missing (very old DB), insert without it
        $has_read_by = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", 'read_by'));
        if (empty($has_read_by)) {
            unset($data['read_by']);
            unset($formats[4]);
            $formats = array_values($formats);
        }

        $inserted = $wpdb->insert($table, $data, $formats);

        if ($inserted === false && function_exists('mlr_log')) {
            mlr_log('mlr_create_notification insert failed: ' . $wpdb->last_error);
        }
        return $inserted !== false;
    }
}

if (!function_exists('mlr_purge_daily_notifications')) {
    /** Clear all admin notifications (daily reset). */
    function mlr_purge_daily_notifications() {
        global $wpdb;
        $table = $wpdb->prefix . 'mlr_admin_notifications';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return;
        }
        $wpdb->query("DELETE FROM `{$table}`");
        if (function_exists('mlr_log')) {
            mlr_log('mlr_purge_daily_notifications: cleared all.');
        }
    }
}

// --- END: Request Events & Messages Helpers ---


// --- START: Human-friendly labels for keys (Frontend/Admin popups) ---
if (!function_exists('mlr_get_field_label')) {
    function mlr_get_field_label($key) {
        $key = (string) $key;

        $map = [
            'bank_validation_file' => 'فایل اعتبارسنجی بانک',
            'signature_image'      => 'تصویر امضا',
            'contract_file'        => 'فایل قرارداد',
            'check_images'         => 'تصاویر چک',
            'required_documents'   => 'مدارک مورد نیاز',
            'order_items'          => 'اقلام سفارش',
            'order_total'          => 'مبلغ کل سفارش',
            'product_name'         => 'نام کالا/خدمت',
            'full_name'            => 'نام کامل',
            'national_code'        => 'کد ملی',
            'mobile_number'        => 'شماره موبایل',
            'phone_number'         => 'تلفن ثابت',
            'birth_date'           => 'تاریخ تولد',
            'occupation'           => 'شغل',
            'residential_address'  => 'آدرس سکونت',
            'work_address'         => 'آدرس محل کار',
            'postal_code'          => 'کد پستی',
            'loan_amount'          => 'مبلغ درخواستی',
            'facility_amount'      => 'مبلغ تسهیلات',
            'installment_amount'   => 'مبلغ هر قسط',
            'installment_count'    => 'تعداد اقساط',
            'installment_interval' => 'فاصله اقساط',
            'prepayment'           => 'پیش‌پرداخت',
            'total_payment'        => 'مجموع پرداخت',
            'wallet_charge'        => 'اعتبار خرید',
            'profit'               => 'سود',
            'acceptor_payment_amount' => 'مبلغ تسویه',
            'final_settlement_amount' => 'مبلغ تسویه نهایی',
            'status'               => 'وضعیت',
            'created_at'           => 'تاریخ ثبت',
            'updated_at'           => 'تاریخ بروزرسانی',
        ];

        if (isset($map[$key])) {
            return $map[$key];
        }

        // Fallback: humanize snake_case to readable Persian-ish label (keeps English if unknown)
        $label = str_replace(['_', '-'], ' ', $key);
        $label = preg_replace('/\s+/', ' ', $label);
        $label = trim($label);

        $token_map = [
            'id' => 'شناسه',
            'front' => 'رو',
            'back' => 'پشت',
            'image' => 'تصویر',
            'file' => 'فایل',
            'doc' => 'مدرک',
            'documents' => 'مدارک',
            'address' => 'آدرس',
            'number' => 'شماره',
            'mobile' => 'موبایل',
            'phone' => 'تلفن',
            'national' => 'ملی',
            'code' => 'کد',
            'bank' => 'بانک',
            'contract' => 'قرارداد',
            'signature' => 'امضا',
            'check' => 'چک',
        ];

        $parts = explode(' ', strtolower($label));
        $out_parts = [];
        foreach ($parts as $p) {
            if ($p === '') continue;
            $out_parts[] = isset($token_map[$p]) ? $token_map[$p] : $p;
        }

        $final = trim(implode(' ', $out_parts));
        return $final !== '' ? $final : $key;
    }
}
// --- END: Human-friendly labels for keys ---

// ===================================================================
// MyCredit / ICS24 CREDIT VALIDATION API INTEGRATION
// Updated to MyCreditAPI document v3.1 (1404/08): token, request OTP,
// validate OTP, status polling, optional callback data, regenerate once,
// and report JSON retrieval.
// ===================================================================

if (!defined('MLR_ICS24_USERNAME')) define('MLR_ICS24_USERNAME', 'babanoellhiva');
if (!defined('MLR_ICS24_PASSWORD')) define('MLR_ICS24_PASSWORD', 'B@b93d5858000d458');
if (!defined('MLR_ICS24_BASE_URL')) define('MLR_ICS24_BASE_URL', 'https://mycredit.ics24.ir');
if (!defined('MLR_ICS24_VERSION')) define('MLR_ICS24_VERSION', '2.0');
if (!defined('MLR_ICS24_USER_AGENT')) define('MLR_ICS24_USER_AGENT', 'my-loan-request-plugin/' . (defined('MLR_VERSION') ? MLR_VERSION : '1.0'));

if (!function_exists('mlr_ics24_get_setting')) {
    function mlr_ics24_get_setting($option_name, $constant_name) {
        $value = get_option($option_name);
        if (is_string($value)) {
            $value = trim($value);
            // Some settings screens store masked placeholders when a password field is not changed.
            // Treat those as empty so the known constant/default can still be used.
            if ($value !== '' && !preg_match('/^\*{4,}$/', $value)) {
                return $value;
            }
        }
        return defined($constant_name) ? constant($constant_name) : '';
    }
}

if (!function_exists('mlr_ics24_normalize_credential_value')) {
    function mlr_ics24_normalize_credential_value($value, $type = 'username') {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        // Make pasted values like "user: xxx" / "pass: yyy" usable.
        if ($type === 'username') {
            $value = preg_replace('/^(user|username|نام\s*کاربری)\s*[:：=\-]\s*/iu', '', $value);
        } else {
            $value = preg_replace('/^(pass|password|رمز\s*عبور)\s*[:：=\-]\s*/iu', '', $value);
        }

        return trim((string) $value);
    }
}

if (!function_exists('mlr_ics24_get_credential_candidates')) {
    function mlr_ics24_get_credential_candidates() {
        $candidates = [];

        $option_pairs = [
            ['mlr_ics_username', 'mlr_ics_password'],
            ['mlr_ics24_username', 'mlr_ics24_password'],
            ['mlr_mycredit_username', 'mlr_mycredit_password'],
        ];

        // Prefer the bundled MyCredit credentials first. This plugin installation does not expose
        // these fields in WordPress settings, and older/bad option values must not shadow them.
        $constant_username = defined('MLR_ICS24_USERNAME') ? mlr_ics24_normalize_credential_value(MLR_ICS24_USERNAME, 'username') : '';
        $constant_password = defined('MLR_ICS24_PASSWORD') ? mlr_ics24_normalize_credential_value(MLR_ICS24_PASSWORD, 'password') : '';
        if ($constant_username !== '' && $constant_password !== '') {
            $candidates[] = [
                'username' => $constant_username,
                'password' => $constant_password,
                'source'   => 'bundled_constants',
            ];
        }

        foreach ($option_pairs as $pair) {
            $username = get_option($pair[0]);
            $password = get_option($pair[1]);
            $username = mlr_ics24_normalize_credential_value(is_string($username) ? $username : '', 'username');
            $password = mlr_ics24_normalize_credential_value(is_string($password) ? $password : '', 'password');
            if ($username !== '' && $password !== '' && !preg_match('/^\*{4,}$/', $password)) {
                $candidates[] = [
                    'username' => $username,
                    'password' => $password,
                    'source'   => $pair[0] . '/' . $pair[1],
                ];
            }
        }

        $unique = [];
        $seen = [];
        foreach ($candidates as $candidate) {
            $key = hash('sha256', $candidate['username'] . "\n" . $candidate['password']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $candidate;
        }

        return $unique;
    }
}

if (!function_exists('mlr_ics24_get_base_url')) {
    function mlr_ics24_get_base_url() {
        $base_url = mlr_ics24_get_setting('mlr_ics_base_url', 'MLR_ICS24_BASE_URL');
        $base_url = trim((string) $base_url);
        if ($base_url === '') {
            $base_url = 'https://mycredit.ics24.ir';
        }
        return untrailingslashit(esc_url_raw($base_url));
    }
}

if (!function_exists('mlr_ics24_get_api_version')) {
    function mlr_ics24_get_api_version() {
        $version = mlr_ics24_get_setting('mlr_ics_version', 'MLR_ICS24_VERSION');
        $version = trim((string) $version);
        return $version !== '' ? $version : '2.0';
    }
}

if (!function_exists('mlr_ics24_normalize_token_data')) {
    function mlr_ics24_normalize_token_data($token_data) {
        if (!is_array($token_data)) {
            return null;
        }

        $access_token = $token_data['accessToken'] ?? $token_data['AccessToken'] ?? $token_data['token'] ?? '';
        $refresh_token = $token_data['refreshToken'] ?? $token_data['RefreshToken'] ?? '';
        $api_key = $token_data['apiKey'] ?? $token_data['ApiKey'] ?? $token_data['api_key'] ?? '';
        $token_expires = $token_data['tokenExpires'] ?? $token_data['TokenExpires'] ?? '';
        $expires_at = isset($token_data['expiresAt']) ? absint($token_data['expiresAt']) : 0;

        if ($access_token === '' || $api_key === '') {
            return null;
        }

        if (!$expires_at && is_string($token_expires) && $token_expires !== '') {
            $parsed = strtotime($token_expires);
            if ($parsed) {
                $expires_at = (int) $parsed;
            }
        }

        return [
            'accessToken'  => (string) $access_token,
            'refreshToken' => (string) $refresh_token,
            'apiKey'       => (string) $api_key,
            'tokenExpires' => (string) $token_expires,
            'expiresAt'    => $expires_at,
        ];
    }
}

if (!function_exists('mlr_ics24_extract_message')) {
    function mlr_ics24_extract_message($result, $fallback = 'خطای ناشناخته در سرویس اعتبارسنجی.') {
        if (is_wp_error($result)) {
            return $result->get_error_message();
        }
        if (!is_array($result)) {
            return $fallback;
        }

        $messages = $result['messages'] ?? $result['Messages'] ?? $result['message'] ?? $result['Message'] ?? null;
        if (is_string($messages) && trim($messages) !== '') {
            return trim($messages);
        }
        if (is_array($messages)) {
            $parts = [];
            foreach ($messages as $message_row) {
                if (is_string($message_row) && trim($message_row) !== '') {
                    $parts[] = trim($message_row);
                    continue;
                }
                if (is_array($message_row)) {
                    $text = $message_row['message'] ?? $message_row['Message'] ?? $message_row['title'] ?? $message_row['Title'] ?? '';
                    $reason = $message_row['reason'] ?? $message_row['Reason'] ?? '';
                    $text = trim((string) $text);
                    $reason = trim((string) $reason);
                    if ($text !== '') {
                        $parts[] = $text;
                    } elseif ($reason !== '') {
                        $parts[] = $reason;
                    }
                }
            }
            if (!empty($parts)) {
                return implode('، ', array_unique($parts));
            }
        }
        if (!empty($result['error_description'])) {
            return sanitize_text_field($result['error_description']);
        }
        return $fallback;
    }
}

if (!function_exists('mlr_ics24_call_api')) {
    function mlr_ics24_call_api($endpoint, $method = 'POST', $data = [], $token_data = null) {
        $endpoint = '/' . ltrim((string) $endpoint, '/');
        $url = mlr_ics24_get_base_url() . $endpoint;
        $method = strtoupper((string) $method);
        $normalized_token = mlr_ics24_normalize_token_data($token_data);

        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'x-version'    => mlr_ics24_get_api_version(),
            'User-Agent'   => defined('MLR_ICS24_USER_AGENT') ? MLR_ICS24_USER_AGENT : 'my-loan-request-plugin',
        ];

        if ($normalized_token) {
            $headers['Authorization'] = 'Bearer ' . $normalized_token['accessToken'];
            $headers['x-apikey'] = $normalized_token['apiKey'];
        }

        $args = [
            'method'      => $method,
            'headers'     => $headers,
            'timeout'     => 30,
            'redirection' => 5,
            'sslverify'   => true,
        ];

        if ($method === 'GET') {
            if (!empty($data)) {
                $url = add_query_arg($data, $url);
            }
        } else {
            $args['body'] = !empty($data) ? http_build_query($data, '', '&') : '';
        }

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            if (function_exists('mlr_log')) mlr_log('MyCredit API WP_Error [' . $endpoint . ']: ' . $response->get_error_message());
            return new WP_Error('ics_http_error', 'خطای ارتباط با سرویس اعتبارسنجی: ' . $response->get_error_message());
        }

        $http_code = (int) wp_remote_retrieve_response_code($response);
        $raw_response = trim((string) wp_remote_retrieve_body($response));
        $result = [];
        if ($raw_response !== '') {
            $result = json_decode($raw_response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                if (function_exists('mlr_log')) mlr_log('MyCredit API Invalid JSON [' . $endpoint . '] (' . $http_code . ')');
                return new WP_Error('invalid_json', 'پاسخ سرویس اعتبارسنجی معتبر نیست.');
            }
        }

        if ($http_code < 200 || $http_code >= 300) {
            if (function_exists('mlr_log')) mlr_log('MyCredit API HTTP Error [' . $endpoint . '] (' . $http_code . ')');
        }

        return [
            'code' => $http_code,
            'data' => is_array($result) ? $result : [],
            'raw'  => $raw_response,
        ];
    }
}

if (!function_exists('mlr_ics24_refresh_token')) {
    function mlr_ics24_refresh_token($token_data) {
        $token_data = mlr_ics24_normalize_token_data($token_data);
        if (!$token_data || empty($token_data['refreshToken'])) {
            return new WP_Error('refresh_unavailable', 'توکن تازه‌سازی اعتبارسنجی در دسترس نیست.');
        }

        $response = mlr_ics24_call_api('/Connect/Token', 'PUT', [
            'AccessToken'  => $token_data['accessToken'],
            'RefreshToken' => $token_data['refreshToken'],
        ], $token_data);

        if (is_wp_error($response)) {
            return $response;
        }

        $data = $response['data'];
        if ($response['code'] >= 200 && $response['code'] < 300 && isset($data['hasError']) && $data['hasError'] === false) {
            $payload = $data['data'] ?? [];
            $new_token = [
                'accessToken'  => $payload['accessToken'] ?? $payload['AccessToken'] ?? $token_data['accessToken'],
                'refreshToken' => $payload['refreshToken'] ?? $payload['RefreshToken'] ?? $token_data['refreshToken'],
                'apiKey'       => $token_data['apiKey'],
                'tokenExpires' => $payload['tokenExpires'] ?? '',
                'expiresAt'    => time() + (11 * HOUR_IN_SECONDS),
            ];
            set_transient('mlr_ics24_credentials_v4', $new_token, 23 * HOUR_IN_SECONDS);
            return mlr_ics24_normalize_token_data($new_token);
        }

        delete_transient('mlr_ics24_credentials_v4');
        return new WP_Error('refresh_failed', mlr_ics24_extract_message($data, 'خطا در تازه‌سازی توکن اعتبارسنجی.'));
    }
}

if (!function_exists('mlr_ics24_get_token')) {
    function mlr_ics24_get_token() {
        $cached = get_transient('mlr_ics24_credentials_v4');
        $token_data = mlr_ics24_normalize_token_data($cached);
        if ($token_data) {
            $expires_at = absint($token_data['expiresAt'] ?? 0);
            if (!$expires_at || $expires_at > (time() + 5 * MINUTE_IN_SECONDS)) {
                return $token_data;
            }
            $refreshed = mlr_ics24_refresh_token($token_data);
            if (!is_wp_error($refreshed)) {
                return $refreshed;
            }
        }

        $credential_candidates = mlr_ics24_get_credential_candidates();
        if (empty($credential_candidates)) {
            return new WP_Error('ics_config_error', 'نام کاربری یا رمز عبور API اعتبارسنجی تنظیم نشده است.');
        }

        $last_response = null;
        $last_data = [];
        $last_message = 'خطای ناشناخته در دریافت توکن';

        $token_attempts = [
            [
                'endpoint' => '/Connect/Token',
                'fields'   => ['Username', 'Password'],
                'label'    => 'documented-uppercase',
            ],
            [
                'endpoint' => '/connect/token',
                'fields'   => ['Username', 'Password'],
                'label'    => 'lowercase-route-uppercase-fields',
            ],
            [
                'endpoint' => '/Connect/Token',
                'fields'   => ['username', 'password'],
                'label'    => 'uppercase-route-lowercase-fields',
            ],
        ];

        foreach ($credential_candidates as $candidate) {
            foreach ($token_attempts as $attempt) {
                $body = [
                    $attempt['fields'][0] => $candidate['username'],
                    $attempt['fields'][1] => $candidate['password'],
                ];
                $response = mlr_ics24_call_api($attempt['endpoint'], 'POST', $body);

                if (is_wp_error($response)) {
                    delete_transient('mlr_ics24_credentials_v4');
                    delete_transient('mlr_ics24_credentials_v3');
                    delete_transient('mlr_ics24_token');
                    $last_message = $response->get_error_message();
                    $last_response = $response;
                    continue;
                }

                $last_response = $response;
                $data = $response['data'];
                $last_data = $data;
                $last_message = mlr_ics24_extract_message($data, 'خطای ناشناخته در دریافت توکن');

                if ($response['code'] >= 200 && $response['code'] < 300 && isset($data['hasError']) && $data['hasError'] === false && !empty($data['data']['accessToken']) && !empty($data['data']['apiKey'])) {
                    $payload = $data['data'];
                    $expires_at = 0;
                    if (!empty($payload['tokenExpires'])) {
                        $parsed = strtotime($payload['tokenExpires']);
                        if ($parsed) {
                            $expires_at = (int) $parsed;
                        }
                    }
                    if (!$expires_at) {
                        $expires_at = time() + (11 * HOUR_IN_SECONDS);
                    }
                    $token_info = [
                        'accessToken'  => $payload['accessToken'],
                        'refreshToken' => $payload['refreshToken'] ?? '',
                        'apiKey'       => $payload['apiKey'],
                        'tokenExpires' => $payload['tokenExpires'] ?? '',
                        'expiresAt'    => $expires_at,
                    ];
                    set_transient('mlr_ics24_credentials_v4', $token_info, 23 * HOUR_IN_SECONDS);
                    delete_transient('mlr_ics24_credentials_v3');
                    delete_transient('mlr_ics24_token');
                    return mlr_ics24_normalize_token_data($token_info);
                }

                if (function_exists('mlr_log')) mlr_log('MyCredit Token Error using ' . sanitize_key($candidate['source']) . '/' . sanitize_key($attempt['label']) . ' (' . $response['code'] . ')');
            }
        }

        $code = is_array($last_response) ? (int) ($last_response['code'] ?? 0) : 0;
        return new WP_Error('api_error', 'خطای API (' . $code . '): ' . $last_message);
    }
}

if (!function_exists('mlr_ics24_call_authenticated_api')) {
    function mlr_ics24_call_authenticated_api($endpoint, $method = 'POST', $data = [], $retry = true) {
        $token_data = mlr_ics24_get_token();
        if (is_wp_error($token_data)) {
            return $token_data;
        }
        $response = mlr_ics24_call_api($endpoint, $method, $data, $token_data);
        if (!is_wp_error($response) && (int) ($response['code'] ?? 0) === 401 && $retry) {
            delete_transient('mlr_ics24_credentials_v4');
            $token_data = mlr_ics24_get_token();
            if (is_wp_error($token_data)) {
                return $token_data;
            }
            return mlr_ics24_call_api($endpoint, $method, $data, $token_data);
        }
        return $response;
    }
}

if (!function_exists('mlr_ics24_extract_report_code')) {
    function mlr_ics24_extract_report_code($report_link) {
        $report_link = trim((string) $report_link);
        if ($report_link === '') {
            return '';
        }
        $parts = wp_parse_url($report_link);
        $path = '';
        if (is_array($parts) && !empty($parts['path'])) {
            $path = $parts['path'];
        } elseif (strpos($report_link, '/report/') !== false) {
            $path = $report_link;
        }
        if ($path !== '' && preg_match('~/report/([^/?#&]+)~i', $path, $m)) {
            return sanitize_text_field(rawurldecode($m[1]));
        }
        $basename = basename(strtok($report_link, '?#'));
        return sanitize_text_field($basename);
    }
}

if (!function_exists('mlr_ics24_get_report_json')) {
    function mlr_ics24_get_report_json($reportCode, $token_data = null) {
        $reportCode = mlr_ics24_extract_report_code($reportCode);
        if ($reportCode === '') {
            return new WP_Error('missing_report_code', 'کد گزارش اعتبارسنجی دریافت نشد.');
        }
        $response = $token_data
            ? mlr_ics24_call_api('/report/' . rawurlencode($reportCode) . '/json', 'GET', [], $token_data)
            : mlr_ics24_call_authenticated_api('/report/' . rawurlencode($reportCode) . '/json', 'GET');
        if (is_wp_error($response)) {
            return $response;
        }
        $result = $response['data'];
        if ($response['code'] >= 200 && $response['code'] < 300 && isset($result['hasError']) && $result['hasError'] === false && isset($result['data'])) {
            return $result['data'];
        }
        return new WP_Error('report_error', mlr_ics24_extract_message($result, 'خطا در دریافت اطلاعات گزارش اعتبارسنجی.'));
    }
}

if (!function_exists('mlr_ics24_maybe_regenerate_report')) {
    function mlr_ics24_maybe_regenerate_report($hash_code) {
        $hash_code = sanitize_text_field((string) $hash_code);
        if ($hash_code === '') {
            return false;
        }
        $transient_key = 'mlr_ics24_regenerated_' . md5($hash_code);
        if (get_transient($transient_key)) {
            return false;
        }
        $response = mlr_ics24_call_authenticated_api('/b2b/api/request/' . rawurlencode($hash_code) . '/reGenerateReport', 'POST');
        if (!is_wp_error($response) && $response['code'] >= 200 && $response['code'] < 300 && isset($response['data']['hasError']) && $response['data']['hasError'] === false) {
            set_transient($transient_key, 1, HOUR_IN_SECONDS);
            return true;
        }
        return false;
    }
}

add_action('wp_ajax_mlr_credit_request_otp', 'mlr_credit_request_otp_callback');
add_action('wp_ajax_nopriv_mlr_credit_request_otp', 'mlr_credit_request_otp_callback');

function mlr_credit_request_otp_callback() {
    if (ob_get_length()) ob_clean();
    check_ajax_referer('mlr_frontend_nonce', 'nonce');

    $national_code = preg_replace('/\D+/', '', strtr(sanitize_text_field(wp_unslash($_POST['national_code'] ?? '')), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']));
    $mobile = preg_replace('/\D+/', '', strtr(sanitize_text_field(wp_unslash($_POST['mobile'] ?? '')), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']));

    if (!preg_match('/^\d{10}$/', $national_code)) {
        wp_send_json_error(['message' => 'کد ملی باید ۱۰ رقم باشد.']);
    }
    if (!preg_match('/^09\d{9}$/', $mobile)) {
        wp_send_json_error(['message' => 'شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.']);
    }

    if (function_exists('mlr_rate_limit_check')) {
        $limit_check = mlr_rate_limit_check('credit_request_otp', 5, 15 * MINUTE_IN_SECONDS, [$national_code, $mobile]);
        if (is_wp_error($limit_check)) {
            wp_send_json_error(['message' => $limit_check->get_error_message(), 'retry_after' => (int) (($limit_check->get_error_data()['retry_after'] ?? 0))]);
        }
    }

    $response = mlr_ics24_call_authenticated_api('/b2b/api/request', 'POST', [
        'RealPersonNationalCode' => $national_code,
        'MobileNumber'           => $mobile,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => $response->get_error_message()]);
    }

    $result = $response['data'];
    if ($response['code'] >= 200 && $response['code'] < 300 && isset($result['hasError']) && $result['hasError'] === false && !empty($result['data'])) {
        $hash_code = sanitize_text_field($result['data']);
        if (function_exists('mlr_credit_store_flow')) {
            mlr_credit_store_flow($hash_code, $national_code, $mobile);
        }
        wp_send_json_success([
            'hash_code' => $hash_code,
            'message' => 'کد تایید برای شماره موبایل متقاضی ارسال شد.',
        ]);
    }

    wp_send_json_error(['message' => mlr_ics24_extract_message($result, 'خطا در ارسال درخواست اعتبارسنجی.')]);
}

add_action('wp_ajax_mlr_credit_verify_otp', 'mlr_credit_verify_otp_callback');
add_action('wp_ajax_nopriv_mlr_credit_verify_otp', 'mlr_credit_verify_otp_callback');

function mlr_credit_verify_otp_callback() {
    if (ob_get_length()) ob_clean();
    check_ajax_referer('mlr_frontend_nonce', 'nonce');

    $hash_code = sanitize_text_field(wp_unslash($_POST['hash_code'] ?? ''));
    $otp = preg_replace('/\D+/', '', strtr(sanitize_text_field(wp_unslash($_POST['otp'] ?? '')), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']));

    if ($hash_code === '' || $otp === '') {
        wp_send_json_error(['message' => 'کد درخواست و رمز یکبار مصرف الزامی است.']);
    }
    if (function_exists('mlr_credit_get_flow') && !mlr_credit_get_flow($hash_code)) {
        wp_send_json_error(['message' => 'جریان اعتبارسنجی معتبر نیست یا منقضی شده است. لطفاً از ابتدا شروع کنید.']);
    }
    if (function_exists('mlr_rate_limit_check')) {
        $limit_check = mlr_rate_limit_check('credit_verify_otp', 8, 15 * MINUTE_IN_SECONDS, [$hash_code]);
        if (is_wp_error($limit_check)) {
            wp_send_json_error(['message' => $limit_check->get_error_message(), 'retry_after' => (int) (($limit_check->get_error_data()['retry_after'] ?? 0))]);
        }
    }

    $response = mlr_ics24_call_authenticated_api('/b2b/api/request/' . rawurlencode($hash_code) . '/validate', 'POST', [
        'Token' => $otp,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => $response->get_error_message()]);
    }

    $result = $response['data'];
    if ($response['code'] >= 200 && $response['code'] < 300 && isset($result['hasError']) && $result['hasError'] === false) {
        if (function_exists('mlr_credit_mark_otp_verified')) {
            mlr_credit_mark_otp_verified($hash_code);
        }
        $state = is_scalar($result['data'] ?? null) ? sanitize_text_field($result['data']) : 'InProcessing';
        wp_send_json_success([
            'status' => $state,
            'message' => $state === 'InProcessing' ? 'کد تایید شد. گزارش در صف پردازش قرار گرفت.' : 'کد تایید شد. در حال دریافت گزارش...',
        ]);
    }

    wp_send_json_error(['message' => mlr_ics24_extract_message($result, 'رمز وارد شده صحیح نیست یا منقضی شده است.')]);
}

add_action('wp_ajax_mlr_credit_check_status', 'mlr_credit_check_status_callback');
add_action('wp_ajax_nopriv_mlr_credit_check_status', 'mlr_credit_check_status_callback');

function mlr_credit_check_status_callback() {
    if (ob_get_length()) ob_clean();
    check_ajax_referer('mlr_frontend_nonce', 'nonce');

    $hash_code = sanitize_text_field(wp_unslash($_POST['hash_code'] ?? ''));
    if ($hash_code === '') {
        wp_send_json_error(['message' => 'کد درخواست اعتبارسنجی یافت نشد.']);
    }
    $credit_flow = function_exists('mlr_credit_get_flow') ? mlr_credit_get_flow($hash_code) : null;
    if (function_exists('mlr_credit_get_flow') && (!$credit_flow || empty($credit_flow['otp_verified']))) {
        wp_send_json_error(['message' => 'اعتبارسنجی این درخواست تأیید نشده یا منقضی شده است.']);
    }
    if (function_exists('mlr_rate_limit_check')) {
        $limit_check = mlr_rate_limit_check('credit_check_status', 180, 15 * MINUTE_IN_SECONDS, [$hash_code]);
        if (is_wp_error($limit_check)) {
            wp_send_json_error(['message' => $limit_check->get_error_message(), 'retry_after' => (int) (($limit_check->get_error_data()['retry_after'] ?? 0))]);
        }
    }

    $callback_cache = get_transient('mlr_ics24_callback_' . md5($hash_code));
    if (is_array($callback_cache) && !empty($callback_cache['report_link'])) {
        $report_data = mlr_ics24_get_report_json($callback_cache['report_link']);
        if (!is_wp_error($report_data)) {
            $report_data = mlr_convert_dates_recursive($report_data);
            if (function_exists('mlr_credit_store_report_proof')) {
                mlr_credit_store_report_proof($hash_code, $report_data);
            }
            $submission_token = function_exists('mlr_credit_issue_submission_token') ? mlr_credit_issue_submission_token($hash_code, $report_data) : '';
            wp_send_json_success(['status' => 'ready', 'data' => $report_data, 'source' => 'callback', 'proof_token' => $submission_token]);
        }
    }

    $response = mlr_ics24_call_authenticated_api('/b2b/api/request/' . rawurlencode($hash_code) . '/status', 'GET');
    if (is_wp_error($response)) {
        wp_send_json_error(['message' => $response->get_error_message()]);
    }

    $result = $response['data'];
    if (!($response['code'] >= 200 && $response['code'] < 300 && isset($result['hasError']) && $result['hasError'] === false && isset($result['data']) && is_array($result['data']))) {
        wp_send_json_error(['message' => mlr_ics24_extract_message($result, 'خطا در بررسی وضعیت گزارش اعتبارسنجی.')]);
    }

    $payload = $result['data'];
    $status = sanitize_text_field($payload['status'] ?? '');
    $status_title = sanitize_text_field($payload['statusTitle'] ?? 'در حال پردازش...');
    $report_link = isset($payload['reportLink']) ? (string) $payload['reportLink'] : '';

    if ($status === 'ReportGenerated') {
        if ($report_link === '') {
            wp_send_json_success(['status' => 'pending', 'message' => 'گزارش تولید شده اما لینک گزارش هنوز در خروجی سرویس نیست. چند لحظه دیگر بررسی می‌شود.', 'api_status' => $status]);
        }
        $report_data = mlr_ics24_get_report_json($report_link);
        if (is_wp_error($report_data)) {
            wp_send_json_error(['message' => $report_data->get_error_message()]);
        }
        $report_data = mlr_convert_dates_recursive($report_data);
        if (function_exists('mlr_credit_store_report_proof')) {
            mlr_credit_store_report_proof($hash_code, $report_data);
        }
        $submission_token = function_exists('mlr_credit_issue_submission_token') ? mlr_credit_issue_submission_token($hash_code, $report_data) : '';
        wp_send_json_success(['status' => 'ready', 'data' => $report_data, 'proof_token' => $submission_token]);
    }

    if ($status === 'ReportGenerationFailed' || $status === 'ReportGeneratedButDataLost' || $status === 'GenerateReportFailed' || $status === 'ReportDataLost') {
        $regenerated = mlr_ics24_maybe_regenerate_report($hash_code);
        if ($regenerated) {
            wp_send_json_success(['status' => 'pending', 'message' => 'تولید گزارش با خطا مواجه شد؛ درخواست گزارش‌گیری مجدد ارسال شد. لطفاً شکیبا باشید.', 'api_status' => $status, 'regenerated' => true]);
        }
        wp_send_json_error(['message' => $status_title ?: 'دریافت گزارش با خطا مواجه شد.']);
    }

    wp_send_json_success(['status' => 'pending', 'message' => $status_title ?: 'در حال پردازش...', 'api_status' => $status]);
}

add_action('rest_api_init', function () {
    register_rest_route('mlr/v1', '/mycredit-callback', [
        'methods' => ['GET', 'POST'],
        'callback' => 'mlr_ics24_rest_callback_handler',
        'permission_callback' => '__return_true',
    ]);
});

if (!function_exists('mlr_ics24_rest_callback_handler')) {
    function mlr_ics24_rest_callback_handler(WP_REST_Request $request) {
        $message = sanitize_text_field($request->get_param('Message') ?: $request->get_param('message'));
        $hash_code = sanitize_text_field($request->get_param('hashCode') ?: $request->get_param('HashCode'));
        $report_link = esc_url_raw($request->get_param('ReportLink') ?: $request->get_param('reportLink'));

        if ($message !== '') {
            if (!$hash_code && preg_match('/-([^@\s]+)(?:@|$)/', $message, $m)) {
                $hash_code = sanitize_text_field($m[1]);
            }
            if (!$report_link && preg_match('/@(.*)$/', $message, $m)) {
                $report_link = esc_url_raw(trim($m[1]));
            }
        }

        if ($hash_code !== '') {
            // Accept callbacks only for hashes that were issued through this server.
            // The external callback has no browser identity, so inspect the raw flow transient.
            $server_flow = function_exists('mlr_credit_flow_key') ? get_transient(mlr_credit_flow_key($hash_code)) : null;
            if (function_exists('mlr_credit_flow_key') && !is_array($server_flow)) {
                return new WP_REST_Response(['ok' => false, 'message' => 'unknown flow'], 404);
            }
            set_transient('mlr_ics24_callback_' . md5($hash_code), [
                'message' => $message,
                'hash_code' => $hash_code,
                'report_link' => $report_link,
                'received_at' => current_time('mysql'),
            ], 48 * HOUR_IN_SECONDS);
        }

        return new WP_REST_Response(['ok' => true], 200);
    }
}

if (!function_exists('mlr_convert_dates_recursive')) {
    function mlr_convert_dates_recursive($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = mlr_convert_dates_recursive($value);
            }
        } elseif (is_string($data)) {
            // تشخیص فرمت تاریخ میلادی (شروع با 19 یا 20)
            if (preg_match('/^(19|20)\d{2}-\d{2}-\d{2}/', $data)) {
                $format = (strpos($data, 'T') !== false || strpos($data, ':') !== false) ? 'Y/m/d H:i:s' : 'Y/m/d';
                $converted = mlr_gregorian_to_jalali($data, $format);
                if ($converted && $converted !== 'تاریخ نامعتبر') {
                    return $converted;
                }
            }
        }
        return $data;
    }
}

if (!function_exists('mlr_normalize_status')) {
    function mlr_normalize_status($status) {
        return function_exists('mlr_get_canonical_status_label')
            ? mlr_get_canonical_status_label($status)
            : mlr_simplify_status_key($status);
    }
}

if (!function_exists('mlr_get_document_flow_status_groups')) {
    function mlr_get_document_flow_status_groups() {
        return [
            'defect_documents' => [
                'defect_documents',
                'نقص مدارک',
            ],
            'completion_documents' => [
                'completion_documents',
                'تکمیل مدارک',
            ],
            'pending_documents' => [
                'pending_documents',
                'در انتظار مدارک',
                'در انتظار تکمیل',
                'در انتظار تکمیل مدارک',
            ],
        ];
    }
}

if (!function_exists('mlr_get_document_flow_stage')) {
    function mlr_get_document_flow_stage($status) {
        $normalized_status = function_exists('mlr_normalize_status')
            ? mlr_normalize_status((string) $status)
            : trim((string) $status);

        if ($normalized_status === '') {
            return '';
        }

        foreach (mlr_get_document_flow_status_groups() as $stage => $aliases) {
            foreach ($aliases as $alias) {
                $normalized_alias = function_exists('mlr_normalize_status')
                    ? mlr_normalize_status($alias)
                    : trim($alias);

                if ($normalized_status === $normalized_alias) {
                    return $stage;
                }
            }
        }

        return '';
    }
}

if (!function_exists('mlr_is_document_flow_stage')) {
    function mlr_is_document_flow_stage($status, $stages) {
        $stage = function_exists('mlr_get_document_flow_stage')
            ? mlr_get_document_flow_stage($status)
            : '';

        return in_array($stage, (array) $stages, true);
    }
}

if (!function_exists('mlr_get_allowed_document_upload_stages')) {
    function mlr_get_allowed_document_upload_stages() {
        return ['defect_documents', 'completion_documents', 'pending_documents'];
    }
}

if (!function_exists('mlr_validate_document_upload_request_state')) {
    function mlr_validate_document_upload_request_state($request_id, $allowed_stages = null) {
        $request_id = absint($request_id);
        if ($request_id <= 0) {
            return true;
        }

        $allowed_stages = is_array($allowed_stages)
            ? $allowed_stages
            : (function_exists('mlr_get_allowed_document_upload_stages') ? mlr_get_allowed_document_upload_stages() : ['defect_documents', 'completion_documents', 'pending_documents']);

        // The centralized service is loaded immediately after this file. At runtime it
        // is the source of truth for user_id, applicant_user_id and acceptor_user_id.
        if (function_exists('mlr_get_authorized_document_request')) {
            $request = mlr_get_authorized_document_request($request_id, 'auto', $allowed_stages);
            return is_wp_error($request) ? $request : true;
        }

        if (!is_user_logged_in()) {
            return new WP_Error('mlr_document_upload_login_required', 'برای بارگذاری مدارک، لطفاً ابتدا وارد شوید.');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'loan_requests';
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d LIMIT 1",
            $request_id
        ));

        if (!$request) {
            return new WP_Error('mlr_document_upload_request_not_found', 'درخواست مورد نظر یافت نشد.');
        }

        if (function_exists('mlr_can_access_loan_request') && !mlr_can_access_loan_request($request)) {
            return new WP_Error('mlr_document_upload_forbidden', 'شما دسترسی به این درخواست ندارید.');
        }

        $stage = function_exists('mlr_get_document_flow_stage') ? mlr_get_document_flow_stage($request->status) : '';
        if (!in_array($stage, $allowed_stages, true)) {
            return new WP_Error('mlr_document_upload_invalid_state', 'درخواست مورد نظر در وضعیت معتبر برای بارگذاری مدارک نیست.');
        }

        return true;
    }
}

if (!function_exists('mlr_get_request_settlement_date_key')) {
    function mlr_get_request_settlement_date_key($settlement_date) {
        if (!is_string($settlement_date)) {
            return '';
        }

        $settlement_date = trim($settlement_date);
        if ($settlement_date === '' || $settlement_date === '0000-00-00' || $settlement_date === '0000-00-00 00:00:00') {
            return '';
        }

        $settlement_date = str_replace(
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $settlement_date
        );

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $settlement_date, $matches)) {
            return $matches[0] === '0000-00-00' ? '' : $matches[0];
        }

        if (preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $settlement_date, $matches)) {
            $year = (int) $matches[1];
            if ($year >= 1700) {
                return sprintf('%04d-%02d-%02d', $year, (int) $matches[2], (int) $matches[3]);
            }
            if (function_exists('mlr_jalali_to_gregorian')) {
                $gregorian = mlr_jalali_to_gregorian($settlement_date);
                if (is_string($gregorian) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $gregorian)) {
                    return $gregorian;
                }
            }
        }

        $timestamp = strtotime($settlement_date);
        if ($timestamp === false) {
            return '';
        }

        return date('Y-m-d', $timestamp);
    }
}

if (!function_exists('mlr_get_settlement_workflow_statuses')) {
    function mlr_get_settlement_workflow_statuses() {
        return ['منتظر تعیین تاریخ', 'در انتظار تسویه', 'تسویه شده'];
    }
}

if (!function_exists('mlr_reconcile_request_workflow_state')) {
    function mlr_reconcile_request_workflow_state($request) {
        $request = is_array($request) ? $request : (array) $request;
        $updates = [];

        $raw_status = isset($request['status']) ? (string) $request['status'] : '';
        $raw_settlement_status = isset($request['settlement_status']) ? (string) $request['settlement_status'] : '';
        $status = function_exists('mlr_normalize_status') ? mlr_normalize_status($raw_status) : trim($raw_status);
        $settlement_status = function_exists('mlr_normalize_status') ? mlr_normalize_status($raw_settlement_status) : trim($raw_settlement_status);
        $settlement_stages = mlr_get_settlement_workflow_statuses();

        if ($status !== $raw_status) {
            $updates['status'] = $status;
        }

        if ($settlement_status !== $raw_settlement_status) {
            $updates['settlement_status'] = $settlement_status === '' ? null : $settlement_status;
        }

        // If a settlement stage was accidentally saved in the main status column, move it to settlement_status.
        if (in_array($status, $settlement_stages, true)) {
            $settlement_status = $status;
            $status = 'اعطا شده';
            $updates['status'] = $status;
            $updates['settlement_status'] = $settlement_status;
        }

        $settlement_cycle_id = isset($request['settlement_cycle_id']) ? (int) $request['settlement_cycle_id'] : 0;
        if ($settlement_cycle_id <= 0 && array_key_exists('settlement_cycle_id', $request) && !empty($request['settlement_cycle_id'])) {
            $updates['settlement_cycle_id'] = null;
            $settlement_cycle_id = 0;
        }

        $raw_settlement_date = isset($request['settlement_date']) ? (string) $request['settlement_date'] : '';
        $settlement_date_key = function_exists('mlr_get_request_settlement_date_key')
            ? mlr_get_request_settlement_date_key($raw_settlement_date)
            : trim($raw_settlement_date);

        if ($raw_settlement_date !== '' && $settlement_date_key === '') {
            $updates['settlement_date'] = null;
        }

        if ($status !== 'اعطا شده') {
            return $updates;
        }

        if ($settlement_cycle_id > 0) {
            $updates['settlement_status'] = 'تسویه شده';
            $updates['settlement_cycle_id'] = $settlement_cycle_id;
            return $updates;
        }

        $updates['settlement_cycle_id'] = null;
        if ($settlement_date_key !== '') {
            $updates['settlement_status'] = 'در انتظار تسویه';
        } else {
            $updates['settlement_status'] = 'منتظر تعیین تاریخ';
            $updates['settlement_date'] = null;
        }

        return $updates;
    }
}

if (!function_exists('mlr_get_canonical_granted_request_settlement_state')) {
    function mlr_get_canonical_granted_request_settlement_state($request) {
        $request = is_array($request) ? $request : (array) $request;

        $status = function_exists('mlr_normalize_status')
            ? mlr_normalize_status($request['status'] ?? '')
            : trim((string) ($request['status'] ?? ''));

        $settlement_status = function_exists('mlr_normalize_status')
            ? mlr_normalize_status($request['settlement_status'] ?? '')
            : trim((string) ($request['settlement_status'] ?? ''));

        $settlement_date = function_exists('mlr_get_request_settlement_date_key')
            ? mlr_get_request_settlement_date_key((string) ($request['settlement_date'] ?? ''))
            : trim((string) ($request['settlement_date'] ?? ''));
        $settlement_cycle_id = isset($request['settlement_cycle_id']) ? (int) $request['settlement_cycle_id'] : 0;

        $settlement_stages = function_exists('mlr_get_settlement_workflow_statuses') ? mlr_get_settlement_workflow_statuses() : [];
        if (in_array($status, $settlement_stages, true)) {
            $settlement_status = $status;
            $status = 'اعطا شده';
        }

        if ($status !== 'اعطا شده') {
            return [
                'settlement_status' => $settlement_status,
                'settlement_cycle_id' => $settlement_cycle_id > 0 ? $settlement_cycle_id : null,
            ];
        }

        if ($settlement_cycle_id > 0) {
            return [
                'settlement_status' => 'تسویه شده',
                'settlement_cycle_id' => $settlement_cycle_id,
            ];
        }

        if ($settlement_date !== '') {
            return [
                'settlement_status' => 'در انتظار تسویه',
                'settlement_cycle_id' => null,
            ];
        }

        return [
            'settlement_status' => 'منتظر تعیین تاریخ',
            'settlement_cycle_id' => null,
        ];
    }
}

if (!function_exists('mlr_repair_granted_request_settlement_states')) {
    function mlr_repair_granted_request_settlement_states() {
        global $wpdb;

        $table_requests = $wpdb->prefix . 'loan_requests';
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_requests));
        if ($table_exists !== $table_requests) {
            return false;
        }

        // Status-label repair is independently version-guarded and idempotent.
        if (function_exists('mlr_repair_request_status_labels_once')) {
            mlr_repair_request_status_labels_once();
        }

        // Financial-state normalization must be all-or-nothing. On an old MyISAM
        // installation we preserve data and retry later instead of performing a partial repair.
        if (!function_exists('mlr_table_supports_transactions') || !mlr_table_supports_transactions($table_requests)) {
            if (function_exists('mlr_log')) {
                mlr_log('Settlement state repair skipped because loan_requests is not transactional.');
            }
            return false;
        }

        if ($wpdb->query('START TRANSACTION') === false) {
            return false;
        }

        $ok = true;
        $queries = [
            "UPDATE {$table_requests} SET settlement_cycle_id = NULL WHERE settlement_cycle_id = 0",
            "UPDATE {$table_requests}
             SET settlement_date = NULL
             WHERE TRIM(COALESCE(settlement_date, '')) IN ('', '0000-00-00', '0000-00-00 00:00:00')",
            "UPDATE {$table_requests}
             SET status = 'اعطا شده'
             WHERE TRIM(COALESCE(status, '')) = 'اعطا شده'
               AND COALESCE(status, '') <> 'اعطا شده'",
        ];
        foreach ($queries as $query) {
            if ($wpdb->query($query) === false) {
                $ok = false;
                break;
            }
        }

        if ($ok) {
            foreach (mlr_get_settlement_workflow_statuses() as $settlement_stage) {
                $result = $wpdb->query($wpdb->prepare(
                    "UPDATE {$table_requests}
                     SET settlement_status = %s
                     WHERE TRIM(COALESCE(settlement_status, '')) = %s
                       AND COALESCE(settlement_status, '') <> %s",
                    $settlement_stage,
                    $settlement_stage,
                    $settlement_stage
                ));
                if ($result === false) {
                    $ok = false;
                    break;
                }

                $result = $wpdb->query($wpdb->prepare(
                    "UPDATE {$table_requests}
                     SET status = 'اعطا شده', settlement_status = %s
                     WHERE TRIM(COALESCE(status, '')) = %s",
                    $settlement_stage,
                    $settlement_stage
                ));
                if ($result === false) {
                    $ok = false;
                    break;
                }
            }
        }

        if ($ok) {
            foreach ([
                "UPDATE {$table_requests}
                 SET settlement_status = 'تسویه شده'
                 WHERE TRIM(COALESCE(status, '')) = 'اعطا شده'
                   AND settlement_cycle_id IS NOT NULL
                   AND settlement_cycle_id > 0
                   AND TRIM(COALESCE(settlement_status, '')) <> 'تسویه شده'",
                "UPDATE {$table_requests}
                 SET settlement_status = 'در انتظار تسویه'
                 WHERE TRIM(COALESCE(status, '')) = 'اعطا شده'
                   AND (settlement_cycle_id IS NULL OR settlement_cycle_id = 0)
                   AND settlement_date IS NOT NULL
                   AND settlement_date <> ''
                   AND TRIM(COALESCE(settlement_status, '')) <> 'در انتظار تسویه'",
                "UPDATE {$table_requests}
                 SET settlement_status = 'منتظر تعیین تاریخ'
                 WHERE TRIM(COALESCE(status, '')) = 'اعطا شده'
                   AND (settlement_cycle_id IS NULL OR settlement_cycle_id = 0)
                   AND (settlement_date IS NULL OR settlement_date = '')
                   AND TRIM(COALESCE(settlement_status, '')) <> 'منتظر تعیین تاریخ'",
            ] as $query) {
                if ($wpdb->query($query) === false) {
                    $ok = false;
                    break;
                }
            }
        }

        if (!$ok) {
            $wpdb->query('ROLLBACK');
            if (function_exists('mlr_log')) {
                mlr_log('Settlement state repair rolled back; it will be retried.', ['db_error' => $wpdb->last_error]);
            }
            return false;
        }

        if ($wpdb->query('COMMIT') === false) {
            $wpdb->query('ROLLBACK');
            if (function_exists('mlr_log')) {
                mlr_log('Settlement state repair commit failed; repair not marked complete.', ['db_error' => $wpdb->last_error]);
            }
            return false;
        }
        return true;
    }
}

if (!function_exists('mlr_repair_granted_request_settlement_states_once')) {
    function mlr_repair_granted_request_settlement_states_once() {
        if (!is_admin()) {
            return false;
        }
        if (
            !current_user_can('manage_options')
            && !current_user_can('mlr_edit_settlement_date')
            && !current_user_can('mlr_amaliat_operations')
            && !current_user_can('mlr_mali_operations')
        ) {
            return false;
        }

        $version = defined('MLR_VERSION') ? MLR_VERSION : 'unknown';
        $option_name = 'mlr_settlement_state_repair_version';
        if (get_option($option_name) === $version) {
            return true;
        }

        $ok = mlr_repair_granted_request_settlement_states();
        if ($ok) {
            update_option($option_name, $version, false);
        }
        return $ok;
    }
}
add_action('admin_init', 'mlr_repair_granted_request_settlement_states_once', 21);

if (!function_exists('mlr_generate_excel_export')) {
    /**
     * Generates an Excel export (HTML Table format) for a list of requests.
     * Supports RTL and UTF-8 natively without external libraries.
     */
    function mlr_generate_excel_export($requests) {
        $filename = 'loan-requests-' . date('Y-m-d-H-i') . '.xls';
        
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head>';
        echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
        echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Requests</x:Name><x:WorksheetOptions><x:DisplayRightToLeft/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
        echo '<style>
            body { font-family: Tahoma, Arial, sans-serif; direction: rtl; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #000; padding: 5px; text-align: center; vertical-align: middle; }
            th { background-color: #f0f0f0; font-weight: bold; }
            .text-cell { mso-number-format:"\@"; }
        </style>';
        echo '</head>';
        echo '<body>';
        echo '<table>';
        $can_view_leasing_deposit_amount = function_exists('mlr_can_view_leasing_deposit_amount') && mlr_can_view_leasing_deposit_amount();
        echo '<thead><tr>';
        echo '<th>شناسه</th>';
        echo '<th>پذیرنده</th>';
        echo '<th>متقاضی</th>';
        echo '<th>کد ملی</th>';
        echo '<th>طرح</th>';
        echo '<th>تعداد اقساط</th>';
        echo '<th>مبلغ تسهیلات (ریال)</th>';
        echo '<th>اعتبار خرید (ریال)</th>';
        echo '<th>مبلغ تسویه پذیرنده (ریال)</th>';
        if ($can_view_leasing_deposit_amount) { echo '<th>مبلغ (واریزی لیزینگ) (ریال)</th>'; }
        echo '<th>وضعیت</th>';
        echo '<th>دریافت مدارک</th>';
        echo '<th>وضعیت تسویه</th>';
        echo '<th>کارشناس بانک</th>';
        echo '<th>کارشناس بابانوئل</th>';
        echo '<th>تاریخ ثبت</th>';
        echo '<th>تاریخ ویرایش</th>';
        echo '<th>تاریخ اعطا</th>';
        echo '<th>تاریخ تسویه</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($requests as $req) {
            $created_at = function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($req->created_at, 'Y/m/d H:i') : $req->created_at;
            $last_edited_at = !empty($req->last_edited_at) && function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($req->last_edited_at, 'Y/m/d H:i') : (isset($req->last_edited_at) && $req->last_edited_at ? $req->last_edited_at : '—');
            $grant_date = ($req->status === 'اعطا شده' && !empty($req->last_edited_at)) ? (function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($req->last_edited_at, 'Y/m/d H:i') : $req->last_edited_at) : '—';
            $settlement_date_display = !empty($req->settlement_date) && function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($req->settlement_date, 'Y/m/d') : (isset($req->settlement_date) && $req->settlement_date ? $req->settlement_date : '—');
            $plan_name = isset($req->plan_name) ? $req->plan_name : '—';
            $display_name = isset($req->display_name) ? $req->display_name : '—';
            $settlement_status = trim((string)($req->settlement_status ?? '')) ?: '—';
            $document_receipt_status = function_exists('mlr_get_document_receipt_status_label')
                ? mlr_get_document_receipt_status_label($req->document_receipt_status ?? '')
                : 'بررسی نشده';
            $bank_expert_name = '—';
            if (!empty($req->assigned_bank_user_id)) {
                $u = get_userdata($req->assigned_bank_user_id);
                $bank_expert_name = $u ? $u->display_name : '—';
            }
            $babanoel_expert_name = '—';
            if (!empty($req->assigned_babanoel_user_id)) {
                $u = get_userdata($req->assigned_babanoel_user_id);
                $babanoel_expert_name = $u ? $u->display_name : '—';
            }
            $facility_excel = (isset($req->facility_amount) && $req->facility_amount !== '' && $req->facility_amount !== null) ? floatval($req->facility_amount) : floatval($req->loan_amount);
            $wallet_excel = (isset($req->wallet_charge) && $req->wallet_charge !== '' && $req->wallet_charge !== null) ? number_format((float)$req->wallet_charge) : '—';
            $acceptor_settlement_excel = function_exists('mlr_format_acceptor_settlement_amount') ? mlr_format_acceptor_settlement_amount($req) : '—';
            $leasing_amount_excel = function_exists('mlr_format_leasing_deposit_amount') ? mlr_format_leasing_deposit_amount($req) : '—';

            echo '<tr>';
            echo '<td>' . $req->id . '</td>';
            echo '<td>' . esc_html($display_name) . '</td>';
            echo '<td>' . esc_html($req->full_name) . '</td>';
            echo '<td class="text-cell">' . esc_html($req->national_code) . '</td>';
            echo '<td>' . esc_html($plan_name) . '</td>';
            echo '<td>' . esc_html(isset($req->installment_count) && $req->installment_count !== '' ? $req->installment_count : '—') . '</td>';
            echo '<td>' . number_format($facility_excel) . '</td>';
            echo '<td>' . $wallet_excel . '</td>';
            echo '<td>' . $acceptor_settlement_excel . '</td>';
            if ($can_view_leasing_deposit_amount) { echo '<td>' . $leasing_amount_excel . '</td>'; }
            echo '<td>' . esc_html($req->status) . '</td>';
            echo '<td>' . esc_html($document_receipt_status) . '</td>';
            echo '<td>' . esc_html($settlement_status) . '</td>';
            echo '<td>' . esc_html($bank_expert_name) . '</td>';
            echo '<td>' . esc_html($babanoel_expert_name) . '</td>';
            echo '<td class="text-cell">' . esc_html($created_at) . '</td>';
            echo '<td class="text-cell">' . esc_html($last_edited_at) . '</td>';
            echo '<td class="text-cell">' . esc_html($grant_date) . '</td>';
            echo '<td class="text-cell">' . esc_html($settlement_date_display) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</body>';
        echo '</html>';
    }
}

if (!function_exists('mlr_generate_excel_export_amaliat')) {
    /**
     * خروجی اکسل برای صفحه کارشناس عملیات: فقط ستون‌های شناسه، پذیرنده، متقاضی، کد ملی، طرح، مبلغ تسهیلات، اعتبار خرید، وضعیت، کارشناس بابا نوئل، کارشناس بانک، تاریخ ثبت، تاریخ تسویه.
     */
    function mlr_generate_excel_export_amaliat($requests) {
        $filename = 'loan-requests-amaliat-' . date('Y-m-d-H-i') . '.xls';
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
        echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Requests</x:Name><x:WorksheetOptions><x:DisplayRightToLeft/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
        echo '<style>body{font-family:Tahoma,Arial,sans-serif;direction:rtl;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #000;padding:5px;text-align:center;} th{background:#f0f0f0;font-weight:bold;} .text-cell{mso-number-format:"\@";}</style></head><body><table>';
        echo '<thead><tr>';
        echo '<th>شناسه</th><th>پذیرنده</th><th>متقاضی</th><th>کد ملی</th><th>طرح اقساطی</th><th>مبلغ تسهیلات (ریال)</th><th>اعتبار خرید (ریال)</th><th>وضعیت</th><th>دریافت مدارک</th><th>کارشناس بابا نوئل</th><th>کارشناس بانک</th><th>تاریخ ثبت</th><th>تاریخ تسویه (منتقل به تاریخ پرداخت)</th>';
        echo '</tr></thead><tbody>';
        foreach ($requests as $req) {
            $created_at = function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($req->created_at, 'Y/m/d H:i') : $req->created_at;
            $settlement_date_display = !empty($req->settlement_date) && function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($req->settlement_date, 'Y/m/d') : (isset($req->settlement_date) && $req->settlement_date ? $req->settlement_date : '—');
            $plan_name = isset($req->plan_name) ? $req->plan_name : '—';
            $display_name = isset($req->display_name) ? $req->display_name : '—';
            $document_receipt_status = function_exists('mlr_get_document_receipt_status_label')
                ? mlr_get_document_receipt_status_label($req->document_receipt_status ?? '')
                : 'بررسی نشده';
            $bank_expert_name = '—';
            if (!empty($req->assigned_bank_user_id)) {
                $u = get_userdata($req->assigned_bank_user_id);
                $bank_expert_name = $u ? $u->display_name : '—';
            }
            $babanoel_expert_name = '—';
            if (!empty($req->assigned_babanoel_user_id)) {
                $u = get_userdata($req->assigned_babanoel_user_id);
                $babanoel_expert_name = $u ? $u->display_name : '—';
            }
            $facility_excel = (isset($req->facility_amount) && $req->facility_amount !== '' && $req->facility_amount !== null) ? floatval($req->facility_amount) : floatval($req->loan_amount);
            $wallet_excel = (isset($req->wallet_charge) && $req->wallet_charge !== '' && $req->wallet_charge !== null) ? number_format((float)$req->wallet_charge) : '—';
            echo '<tr>';
            echo '<td>' . (int)$req->id . '</td>';
            echo '<td>' . esc_html($display_name) . '</td>';
            echo '<td>' . esc_html($req->full_name) . '</td>';
            echo '<td class="text-cell">' . esc_html($req->national_code) . '</td>';
            echo '<td>' . esc_html($plan_name) . '</td>';
            echo '<td>' . number_format($facility_excel) . '</td>';
            echo '<td>' . $wallet_excel . '</td>';
            echo '<td>' . esc_html($req->status) . '</td>';
            echo '<td>' . esc_html($document_receipt_status) . '</td>';
            echo '<td>' . esc_html($babanoel_expert_name) . '</td>';
            echo '<td>' . esc_html($bank_expert_name) . '</td>';
            echo '<td class="text-cell">' . esc_html($created_at) . '</td>';
            echo '<td class="text-cell">' . esc_html($settlement_date_display) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></body></html>';
    }
}

add_action('admin_init', 'mlr_process_excel_export', 1);

function mlr_process_excel_export() {
    if (!isset($_GET['export_excel']) || $_GET['export_excel'] != '1') {
        return;
    }
    if (!current_user_can('manage_options') && !current_user_can('mlr_amaliat_operations') && !current_user_can('mlr_mali_operations')) {
        return;
    }

    global $wpdb;
    $table_requests = $wpdb->prefix . 'loan_requests';
    $users_table = $wpdb->prefix . 'users';
    $plans_table = $wpdb->prefix . 'installment_plans';
    $effective_acceptor_sql = function_exists('mlr_get_request_effective_acceptor_sql')
        ? mlr_get_request_effective_acceptor_sql('r')
        : 'COALESCE(r.acceptor_user_id, r.user_id)';

    $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

    // صفحه کارشناس عملیات (amaliat): همان فیلترها، خروجی با ستون‌های کارشناس عملیات
    if (current_user_can('mlr_amaliat_operations') && $page === 'mlr-amaliat-operations') {
        $extra_where = [];
        if (function_exists('mlr_build_admin_request_filters')) {
            $filter_result = mlr_build_admin_request_filters('r', $extra_where);
            $sql_where = 'WHERE ' . $filter_result['where'];
            $prepare_params = $filter_result['params'];
            if (!empty($filter_result['acceptor_search_where'])) {
                $sql_where .= ' AND ' . $filter_result['acceptor_search_where'];
                $prepare_params = array_merge($prepare_params, $filter_result['acceptor_search_params']);
            }
        } else {
            $sql_where = 'WHERE 1=1';
            $prepare_params = [];
        }
        $export_query = "SELECT r.*, u.display_name, p.name as plan_name
                         FROM $table_requests r
                         LEFT JOIN $users_table u ON {$effective_acceptor_sql} = u.ID
                         LEFT JOIN $plans_table p ON r.installment_plan_id = p.id
                         $sql_where
                         ORDER BY r.created_at DESC";
        $requests_export = !empty($prepare_params) ? $wpdb->get_results($wpdb->prepare($export_query, $prepare_params)) : $wpdb->get_results($export_query);
        if (!is_array($requests_export)) { $requests_export = []; }
        if (function_exists('mlr_generate_excel_export_amaliat')) {
            while (ob_get_level()) { ob_end_clean(); }
            mlr_generate_excel_export_amaliat($requests_export);
            exit;
        }
    }

    // صفحه کارشناس مالی (mali): همان فیلترها، خروجی با ستون‌های کامل (مطابق جدول کارشناس مالی)
    if (current_user_can('mlr_mali_operations') && $page === 'mlr-mali-requests') {
        $extra_where = [];
        if (function_exists('mlr_build_admin_request_filters')) {
            $filter_result = mlr_build_admin_request_filters('r', $extra_where);
            $sql_where = 'WHERE ' . $filter_result['where'];
            $prepare_params = $filter_result['params'];
            if (!empty($filter_result['acceptor_search_where'])) {
                $sql_where .= ' AND ' . $filter_result['acceptor_search_where'];
                $prepare_params = array_merge($prepare_params, $filter_result['acceptor_search_params']);
            }
        } else {
            $sql_where = 'WHERE 1=1';
            $prepare_params = [];
        }
        $export_query = "SELECT r.*, u.display_name, p.name as plan_name
                         FROM $table_requests r
                         LEFT JOIN $users_table u ON {$effective_acceptor_sql} = u.ID
                         LEFT JOIN $plans_table p ON r.installment_plan_id = p.id
                         $sql_where
                         ORDER BY r.created_at DESC";
        $requests_export = !empty($prepare_params) ? $wpdb->get_results($wpdb->prepare($export_query, $prepare_params)) : $wpdb->get_results($export_query);
        if (!is_array($requests_export)) { $requests_export = []; }
        if (function_exists('mlr_generate_excel_export')) {
            while (ob_get_level()) { ob_end_clean(); }
            mlr_generate_excel_export($requests_export);
            exit;
        }
    }

    $status_submenu_pages = ['mlr-loan-requests-review', 'mlr-loan-requests-missing-docs', 'mlr-loan-requests-rejected', 'mlr-loan-requests-expert-approved', 'mlr-loan-requests-docs-complete', 'mlr-loan-requests-docs-approved'];
    $is_list_page = in_array($page, ['mlr-loan-requests', 'mlr-granted-requests', 'mlr-settled-requests']) || in_array($page, $status_submenu_pages);

    if ($is_list_page) {
        if (!current_user_can('manage_options') && !current_user_can('mlr_mali_operations')) {
            return;
        }

        $extra_where = [];
        if ($page === 'mlr-granted-requests') {
            $extra_where[] = "TRIM(COALESCE(r.status, '')) = 'اعطا شده'";
            $extra_where[] = "r.settlement_date IS NULL";
        } elseif ($page === 'mlr-settled-requests') {
            $extra_where[] = "TRIM(COALESCE(r.status, '')) = 'اعطا شده'";
            $extra_where[] = "TRIM(COALESCE(r.settlement_status, '')) = 'تسویه شده'";
        }
        if (function_exists('mlr_build_admin_request_filters')) {
            $filter_result = mlr_build_admin_request_filters('r', $extra_where);
            $sql_where = 'WHERE ' . $filter_result['where'];
            $prepare_params = $filter_result['params'];
            if (!empty($filter_result['acceptor_search_where'])) {
                $sql_where .= ' AND ' . $filter_result['acceptor_search_where'];
                $prepare_params = array_merge($prepare_params, $filter_result['acceptor_search_params']);
            }
        } else {
            $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
            $status_from_url = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
            $date_from_jalali = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
            $date_to_jalali = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
            $date_from_gregorian = !empty($date_from_jalali) ? mlr_jalali_to_gregorian($date_from_jalali) : '';
            $date_to_gregorian = !empty($date_to_jalali) ? mlr_jalali_to_gregorian($date_to_jalali) : '';
            $where_clauses = array_merge(['1=1'], $extra_where);
            $prepare_params = [];
            if (!empty($search)) {
                $search_like = '%' . $wpdb->esc_like($search) . '%';
                $where_clauses[] = "(r.full_name LIKE %s OR r.national_code LIKE %s OR r.mobile_number LIKE %s OR r.id = %d)";
                $prepare_params[] = $search_like;
                $prepare_params[] = $search_like;
                $prepare_params[] = $search_like;
                $prepare_params[] = is_numeric($search) ? intval($search) : 0;
            }
            if (!empty($status_from_url)) {
                $where_clauses[] = "r.status = %s";
                $prepare_params[] = $status_from_url;
            }
            if (!empty($date_from_gregorian)) {
                $where_clauses[] = "r.created_at >= %s";
                $prepare_params[] = $date_from_gregorian . ' 00:00:00';
            }
            if (!empty($date_to_gregorian)) {
                $where_clauses[] = "r.created_at <= %s";
                $prepare_params[] = $date_to_gregorian . ' 23:59:59';
            }
            $sql_where = 'WHERE ' . implode(' AND ', $where_clauses);
        }

        $export_query = "SELECT r.*, u.display_name, p.name as plan_name
                       FROM $table_requests r
                       LEFT JOIN $users_table u ON {$effective_acceptor_sql} = u.ID
                       LEFT JOIN $plans_table p ON r.installment_plan_id = p.id
                       $sql_where
                       ORDER BY r.created_at DESC";

        if (!empty($prepare_params)) {
            $requests_export = $wpdb->get_results($wpdb->prepare($export_query, $prepare_params));
        } else {
            $requests_export = $wpdb->get_results($export_query);
        }

        if ($requests_export === null && $wpdb->last_error && function_exists('mlr_log')) {
            mlr_log('MLR Excel export query error: ' . $wpdb->last_error);
        }
        if (!is_array($requests_export)) {
            $requests_export = [];
        }

        // اگر با فیلتر خالی ماند، برای صفحه «همه درخواست‌ها» / زیرمنوهای وضعیت یک بار بدون فیلتر امتحان کن (رفع باگ prepare/فیلتر)
        $main_list_pages = array_merge(['mlr-loan-requests'], $status_submenu_pages);
        if (in_array($page, $main_list_pages) && empty($requests_export)) {
            $fallback_query = "SELECT r.*, u.display_name, p.name as plan_name
                               FROM $table_requests r
                               LEFT JOIN $users_table u ON {$effective_acceptor_sql} = u.ID
                               LEFT JOIN $plans_table p ON r.installment_plan_id = p.id
                               WHERE 1=1
                               ORDER BY r.created_at DESC";
            $fallback = $wpdb->get_results($fallback_query);
            if (is_array($fallback) && !empty($fallback)) {
                $requests_export = $fallback;
                if (function_exists('mlr_log')) {
                    mlr_log('MLR Excel export: fallback query (no filters) returned ' . count($fallback) . ' rows.');
                }
            }
        }

        if (function_exists('mlr_generate_excel_export')) {
            while (ob_get_level()) {
                ob_end_clean();
            }
            mlr_generate_excel_export($requests_export);
            exit;
        }
    } elseif ($page === 'mlr-unsettled-requests') {
        // مدیریت خروجی برای صفحه درخواست‌های در انتظار تسویه
        $requests_export = $wpdb->get_results("
            SELECT r.*, u.display_name, p.name as plan_name
            FROM $table_requests r
            LEFT JOIN $users_table u ON {$effective_acceptor_sql} = u.ID
            LEFT JOIN $plans_table p ON r.installment_plan_id = p.id
            WHERE TRIM(COALESCE(r.status, '')) = 'اعطا شده'
              AND TRIM(COALESCE(r.settlement_status, '')) = 'در انتظار تسویه'
              AND (r.settlement_cycle_id IS NULL OR r.settlement_cycle_id = 0)
              AND {$effective_acceptor_sql} > 0
            ORDER BY u.display_name, r.settlement_date, r.id ASC
        ");
        if (function_exists('mlr_generate_excel_export')) {
            while (ob_get_level()) {
                ob_end_clean();
            }
            mlr_generate_excel_export(is_array($requests_export) ? $requests_export : []);
            exit;
        }
    }
}


if (!function_exists('mlr_can_manage_document_received_date')) {
    function mlr_can_manage_document_received_date() {
        return current_user_can('manage_options') || current_user_can('mlr_amaliat_operations');
    }
}

if (!function_exists('mlr_get_financier_payment_status_options')) {
    function mlr_get_financier_payment_status_options() {
        return ['بررسی نشده', 'پرداخت شده', 'عدم پرداخت'];
    }
}

if (!function_exists('mlr_normalize_financier_payment_status')) {
    function mlr_normalize_financier_payment_status($status) {
        $status = trim((string) $status);
        $allowed = mlr_get_financier_payment_status_options();
        return in_array($status, $allowed, true) ? $status : 'بررسی نشده';
    }
}

if (!function_exists('mlr_format_optional_jalali_datetime')) {
    function mlr_format_optional_jalali_datetime($value, $format = 'Y/m/d H:i') {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00 00:00:00' || $value === '0000-00-00') {
            return '—';
        }
        if (function_exists('mlr_gregorian_to_jalali')) {
            return mlr_gregorian_to_jalali($value, $format);
        }
        return $value;
    }
}
