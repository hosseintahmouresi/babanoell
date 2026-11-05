<?php
/**
 * Digital signing helpers for loan/facility contracts.
 *
 * Backward compatibility rules:
 * - Existing plans default to physical signing unless explicitly switched to digital.
 * - Request rows and the loan_requests schema are not modified for signature state.
 * - The per-request signing mode and the final signature proof are stored as non-autoloaded
 *   WordPress options so changing a plan later cannot silently rewrite a historical request.
 */
if (!defined('ABSPATH')) {
    exit;
}


if (!function_exists('mlr_loan_contract_view_token')) {
    /**
     * Create a tamper-resistant public-view token without adding DB columns/options.
     * The request id is authenticated with the site's auth salt, so ids cannot be forged.
     */
    function mlr_loan_contract_view_token($request_id) {
        $request_id = absint($request_id);
        if ($request_id <= 0) {
            return '';
        }
        $signature = hash_hmac('sha256', 'mlr-loan-contract-view|' . $request_id, wp_salt('auth'));
        return 'lc' . $request_id . '_' . substr($signature, 0, 48);
    }
}

if (!function_exists('mlr_parse_loan_contract_view_token')) {
    function mlr_parse_loan_contract_view_token($token) {
        $token = trim((string) $token);
        if (!preg_match('/^lc([1-9][0-9]*)_([a-f0-9]{48})$/i', $token, $matches)) {
            return 0;
        }
        $request_id = absint($matches[1]);
        $expected = mlr_loan_contract_view_token($request_id);
        return $expected !== '' && hash_equals(strtolower($expected), strtolower($token)) ? $request_id : 0;
    }
}

if (!function_exists('mlr_get_loan_contract_view_url')) {
    function mlr_get_loan_contract_view_url($request_id) {
        $token = mlr_loan_contract_view_token($request_id);
        if ($token === '') {
            return '';
        }
        $page = get_page_by_path('cooperation-contract');
        $base_url = $page ? get_permalink($page->ID) : home_url('/cooperation-contract/');
        return esc_url_raw(add_query_arg('token', $token, $base_url));
    }
}

if (!function_exists('mlr_completed_check_files_enabled_from_plan')) {
    /** Existing plans keep the legacy behavior (check images required) until explicitly disabled. */
    function mlr_completed_check_files_enabled_from_plan($plan_id) {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            return true;
        }
        global $wpdb;
        $raw_settings = $wpdb->get_var($wpdb->prepare(
            "SELECT settings FROM `{$wpdb->prefix}installment_plans` WHERE id = %d LIMIT 1",
            $plan_id
        ));
        $settings = function_exists('mlr_get_plan_settings_array')
            ? mlr_get_plan_settings_array($raw_settings)
            : maybe_unserialize($raw_settings);
        if (!is_array($settings) || !array_key_exists('completed_check_files_enabled', $settings)) {
            return true;
        }
        return !empty($settings['completed_check_files_enabled']);
    }
}

if (!function_exists('mlr_completed_check_files_enabled_for_request')) {
    function mlr_completed_check_files_enabled_for_request($request) {
        if (!$request) {
            return true;
        }
        $plan_id = absint(is_array($request) ? ($request['installment_plan_id'] ?? 0) : ($request->installment_plan_id ?? 0));
        return mlr_completed_check_files_enabled_from_plan($plan_id);
    }
}

if (!function_exists('mlr_loan_contract_mode_option_name')) {
    function mlr_loan_contract_mode_option_name($request_id) {
        return 'mlr_lc_mode_' . absint($request_id);
    }
}

if (!function_exists('mlr_loan_contract_signature_option_name')) {
    function mlr_loan_contract_signature_option_name($request_id) {
        return 'mlr_lc_sig_' . absint($request_id);
    }
}

if (!function_exists('mlr_loan_contract_otp_transient_key')) {
    function mlr_loan_contract_otp_transient_key($request_id) {
        return 'mlr_lc_otp_' . substr(hash('sha256', (string) absint($request_id)), 0, 32);
    }
}

if (!function_exists('mlr_loan_contract_otp_lock_name')) {
    function mlr_loan_contract_otp_lock_name($request_id) {
        return 'mlr_lc_otp_lock_' . absint($request_id);
    }
}

if (!function_exists('mlr_get_loan_contract_mode_from_plan')) {
    function mlr_get_loan_contract_mode_from_plan($plan_id) {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            return 'physical';
        }

        global $wpdb;
        $plans_table = $wpdb->prefix . 'installment_plans';
        $raw_settings = $wpdb->get_var($wpdb->prepare(
            "SELECT settings FROM `{$plans_table}` WHERE id = %d LIMIT 1",
            $plan_id
        ));

        $settings = function_exists('mlr_get_plan_settings_array')
            ? mlr_get_plan_settings_array($raw_settings)
            : maybe_unserialize($raw_settings);
        if (!is_array($settings)) {
            $settings = [];
        }

        $mode = sanitize_key((string) ($settings['contract_signing_mode'] ?? 'physical'));
        return $mode === 'digital' ? 'digital' : 'physical';
    }
}

if (!function_exists('mlr_snapshot_loan_contract_mode')) {
    /**
     * Freeze the signing mode for a request without modifying the request row.
     */
    function mlr_snapshot_loan_contract_mode($request_id, $plan_id = 0) {
        $request_id = absint($request_id);
        if ($request_id <= 0) {
            return 'physical';
        }

        $option_name = mlr_loan_contract_mode_option_name($request_id);
        $existing = get_option($option_name, null);
        if (in_array($existing, ['physical', 'digital'], true)) {
            return $existing;
        }

        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            global $wpdb;
            $plan_id = absint($wpdb->get_var($wpdb->prepare(
                "SELECT installment_plan_id FROM `{$wpdb->prefix}loan_requests` WHERE id = %d LIMIT 1",
                $request_id
            )));
        }

        $mode = mlr_get_loan_contract_mode_from_plan($plan_id);
        // add_option is atomic for a given option_name. If another request wins the race,
        // read the stored value instead of overwriting it.
        if (!add_option($option_name, $mode, '', false)) {
            $stored = get_option($option_name, 'physical');
            if (in_array($stored, ['physical', 'digital'], true)) {
                return $stored;
            }
        }
        return $mode;
    }
}

if (!function_exists('mlr_get_loan_contract_mode_for_request')) {
    function mlr_get_loan_contract_mode_for_request($request, $freeze = true) {
        if (!$request) {
            return 'physical';
        }
        $request_id = absint(is_array($request) ? ($request['id'] ?? 0) : ($request->id ?? 0));
        $plan_id = absint(is_array($request) ? ($request['installment_plan_id'] ?? 0) : ($request->installment_plan_id ?? 0));

        if ($request_id > 0) {
            $stored = get_option(mlr_loan_contract_mode_option_name($request_id), null);
            if (in_array($stored, ['physical', 'digital'], true)) {
                return $stored;
            }
        }

        if ($freeze && $request_id > 0) {
            return mlr_snapshot_loan_contract_mode($request_id, $plan_id);
        }
        return mlr_get_loan_contract_mode_from_plan($plan_id);
    }
}

if (!function_exists('mlr_is_loan_contract_digital')) {
    function mlr_is_loan_contract_digital($request, $freeze = true) {
        return mlr_get_loan_contract_mode_for_request($request, $freeze) === 'digital';
    }
}

if (!function_exists('mlr_get_loan_contract_signature')) {
    function mlr_get_loan_contract_signature($request_id) {
        $request_id = absint($request_id);
        if ($request_id <= 0) {
            return null;
        }
        $proof = get_option(mlr_loan_contract_signature_option_name($request_id), null);
        return is_array($proof) && !empty($proof['signed_at']) && !empty($proof['contract_digest']) ? $proof : null;
    }
}

if (!function_exists('mlr_is_loan_contract_signed')) {
    function mlr_is_loan_contract_signed($request_id) {
        return is_array(mlr_get_loan_contract_signature($request_id));
    }
}

if (!function_exists('mlr_loan_contract_mask_mobile')) {
    function mlr_loan_contract_mask_mobile($mobile) {
        $mobile = function_exists('mlr_normalize_mobile_for_cooperation')
            ? mlr_normalize_mobile_for_cooperation($mobile)
            : preg_replace('/\D+/', '', (string) $mobile);
        if (!$mobile || strlen($mobile) !== 11) {
            return '';
        }
        return substr($mobile, 0, 4) . '***' . substr($mobile, -4);
    }
}

if (!function_exists('mlr_loan_contract_signature_mobile')) {
    /**
     * Return the full verified buyer mobile for the printable signature certificate.
     * New signatures persist it directly. Legacy signatures are backfilled at render time
     * from the immutable request identity row so old proofs do not need a DB migration.
     */
    function mlr_loan_contract_signature_mobile($proof) {
        if (!is_array($proof)) return '';

        $mobile = trim((string) ($proof['signer_mobile'] ?? ''));
        $mobile = function_exists('mlr_normalize_mobile_for_cooperation')
            ? mlr_normalize_mobile_for_cooperation($mobile)
            : preg_replace('/\D+/', '', $mobile);
        if ($mobile) return $mobile;

        $request_id = absint($proof['request_id'] ?? 0);
        if ($request_id <= 0) return '';

        global $wpdb;
        $raw = $wpdb->get_var($wpdb->prepare(
            "SELECT mobile_number FROM `{$wpdb->prefix}loan_requests` WHERE id = %d LIMIT 1",
            $request_id
        ));
        $resolved = function_exists('mlr_normalize_mobile_for_cooperation')
            ? (string) mlr_normalize_mobile_for_cooperation($raw)
            : (string) preg_replace('/\D+/', '', (string) $raw);

        // Legacy proofs stored only a masked mobile. Never expose a later-edited request
        // mobile unless it still matches the mask captured at signing time.
        $signed_mask = trim((string) ($proof['signer_mobile_masked'] ?? ''));
        if ($resolved !== '' && $signed_mask !== '' && function_exists('mlr_loan_contract_mask_mobile')) {
            $resolved_mask = mlr_loan_contract_mask_mobile($resolved);
            if ($resolved_mask !== '' && !hash_equals($signed_mask, $resolved_mask)) {
                return $signed_mask;
            }
        }
        return $resolved;
    }
}

if (!function_exists('mlr_loan_contract_occupation_label')) {
    function mlr_loan_contract_occupation_label($occupation) {
        $occupations = [
            'official_employee' => 'کارمند رسمی',
            'contract_employee' => 'کارمند قراردادی',
            'worker' => 'کارگر',
            'freelancer' => 'آزاد',
            'retired' => 'بازنشسته',
            'pensioner' => 'مستمری بگیر',
            'employer' => 'کارفرما',
            'other' => 'سایر',
        ];
        $occupation = (string) $occupation;
        return $occupations[$occupation] ?? $occupation;
    }
}


if (!function_exists('mlr_loan_contract_form_value')) {
    function mlr_loan_contract_form_value(array $form_data, array $keys, $default = '') {
        foreach ($keys as $key) {
            if (array_key_exists($key, $form_data) && is_scalar($form_data[$key])) {
                $value = trim((string) $form_data[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }
        return (string) $default;
    }
}

if (!function_exists('mlr_number_to_persian_words')) {
    /** Convert integer monetary values to Persian words for printable contract clauses. */
    function mlr_number_to_persian_words($number) {
        $number = (int) round((float) $number);
        if ($number === 0) {
            return 'صفر';
        }
        if ($number < 0) {
            return 'منفی ' . mlr_number_to_persian_words(abs($number));
        }

        $ones = ['', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'];
        $teens = [10=>'ده',11=>'یازده',12=>'دوازده',13=>'سیزده',14=>'چهارده',15=>'پانزده',16=>'شانزده',17=>'هفده',18=>'هجده',19=>'نوزده'];
        $tens = [2=>'بیست',3=>'سی',4=>'چهل',5=>'پنجاه',6=>'شصت',7=>'هفتاد',8=>'هشتاد',9=>'نود'];
        $hundreds = [1=>'یکصد',2=>'دویست',3=>'سیصد',4=>'چهارصد',5=>'پانصد',6=>'ششصد',7=>'هفتصد',8=>'هشتصد',9=>'نهصد'];
        $scales = ['', 'هزار', 'میلیون', 'میلیارد', 'تریلیون', 'کوادریلیون', 'کوینتیلیون'];

        $under_thousand = static function($n) use ($ones, $teens, $tens, $hundreds) {
            $parts = [];
            $h = intdiv($n, 100);
            $r = $n % 100;
            if ($h > 0) $parts[] = $hundreds[$h];
            if ($r >= 10 && $r <= 19) {
                $parts[] = $teens[$r];
            } else {
                $t = intdiv($r, 10);
                $o = $r % 10;
                if ($t >= 2) $parts[] = $tens[$t];
                if ($o > 0) $parts[] = $ones[$o];
            }
            return implode(' و ', $parts);
        };

        $groups = [];
        $scale = 0;
        while ($number > 0 && $scale < count($scales)) {
            $chunk = $number % 1000;
            if ($chunk > 0) {
                $words = $under_thousand($chunk);
                if ($scales[$scale] !== '') $words .= ' ' . $scales[$scale];
                array_unshift($groups, $words);
            }
            $number = intdiv($number, 1000);
            $scale++;
        }
        return implode(' و ', $groups);
    }
}

if (!function_exists('mlr_loan_contract_add_months')) {
    function mlr_loan_contract_add_months($mysql_datetime, $months) {
        $months = max(0, absint($months));
        $timestamp = strtotime((string) $mysql_datetime);
        if ($timestamp === false) return '';
        if ($months > 0) {
            $timestamp = strtotime('+' . $months . ' months', $timestamp);
        }
        return date('Y-m-d H:i:s', $timestamp);
    }
}

if (!function_exists('mlr_loan_contract_display_date')) {
    function mlr_loan_contract_display_date($mysql_datetime) {
        $mysql_datetime = trim((string) $mysql_datetime);
        if ($mysql_datetime === '') return '';
        return function_exists('mlr_gregorian_to_jalali')
            ? mlr_gregorian_to_jalali($mysql_datetime, 'Y/m/d')
            : date('Y/m/d', strtotime($mysql_datetime));
    }
}

if (!function_exists('mlr_get_hami_plus_contract_template')) {
    function mlr_get_hami_plus_contract_template() {
        $path = defined('MLR_PLUGIN_DIR') ? MLR_PLUGIN_DIR . 'templates/hami-plus-digital-loan-contract.html' : '';
        if ($path === '' || !is_readable($path)) return '';
        $html = file_get_contents($path);
        return is_string($html) ? trim($html) : '';
    }
}

if (!function_exists('mlr_get_hami_retirees_contract_template')) {
    /** Canonical template transcribed from the employer-approved "قرارداد نهایی" document. */
    function mlr_get_hami_retirees_contract_template() {
        $path = defined('MLR_PLUGIN_DIR') ? MLR_PLUGIN_DIR . 'templates/hami-retirees-final-loan-contract.html' : '';
        if ($path === '' || !is_readable($path)) return '';
        $html = file_get_contents($path);
        return is_string($html) ? trim($html) : '';
    }
}

if (!function_exists('mlr_render_hami_retirees_page_footer')) {
    /** Repeating print footer matching the signature/address block of the approved Hami retirees contract. */
    function mlr_render_hami_retirees_page_footer($buyer_name = '') {
        $buyer_name = trim((string) $buyer_name);
        ob_start();
        ?>
        <div class="mlr-print-only-contract-footer mlr-hami-retirees-repeat-footer" dir="rtl">
            <div class="mlr-retirees-signature-table">
                <div><strong>شرکت اقتصاد فناور شرقی</strong><span>محمدعلی کاشانی مقدم وشنوه</span><span>(علی اکبر تیموری)</span></div>
                <div><strong>شرکت آرمان فراز هیوا</strong><span>مهدی مشتاقی</span></div>
                <div><strong>خریدار/ضامن</strong><?php if ($buyer_name !== ''): ?><span><?php echo esc_html($buyer_name); ?></span><?php endif; ?></div>
            </div>
            <div class="mlr-retirees-address-footer">تهران،بخش مرکزی،شهرتهران،شهرک راه آهن، خیابان کامیاب، کوچه کاوش، پلاک0، ساختمان اداری تریتا، طبقه8 واحد 8a</div>
        </div>
        <?php
        return trim((string) ob_get_clean());
    }
}

if (!function_exists('mlr_apply_hami_footer_policy')) {
    /**
     * 8.4.6.84 — A plan-selected footer image replaces Hami's built-in signature/address footer.
     * This is presentation-only: the Hami default footer is restored automatically when the
     * plan footer image is cleared. Hami Plus is always isolated from the Hami retirees footer.
     */
    function mlr_apply_hami_footer_policy($contract_html, $footer_url = '', $repeat_footer_html = '', $buyer_name = '', $plan_name = '') {
        $contract_html = (string) $contract_html;
        $footer_url = trim((string) $footer_url);
        $repeat_footer_html = (string) $repeat_footer_html;
        $plan_name = (string) $plan_name;

        $is_hami_plus = function_exists('mlr_is_hami_plus_plan_name') && mlr_is_hami_plus_plan_name($plan_name);
        $is_hami_retirees = !$is_hami_plus && strpos($contract_html, 'mlr-hami-retirees-contract') !== false;

        if ($is_hami_plus) {
            return [
                'contract_html' => $contract_html,
                'repeat_footer_html' => '',
                'custom_footer_active' => false,
            ];
        }
        if (!$is_hami_retirees) {
            return [
                'contract_html' => $contract_html,
                'repeat_footer_html' => $repeat_footer_html,
                'custom_footer_active' => false,
            ];
        }

        if ($footer_url !== '') {
            // Mark the Hami root so the screen-only built-in footer is hidden in every preview.
            if (strpos($contract_html, 'mlr-hami-custom-footer-active') === false) {
                $contract_html = preg_replace_callback(
                    '/class=(["\\\'])([^"\\\']*\\bmlr-hami-retirees-contract\\b[^"\\\']*)\\1/i',
                    static function($m) {
                        $classes = trim((string) $m[2]);
                        if (strpos(' ' . $classes . ' ', ' mlr-hami-custom-footer-active ') === false) {
                            $classes .= ' mlr-hami-custom-footer-active';
                        }
                        return 'class=' . $m[1] . trim($classes) . $m[1];
                    },
                    $contract_html,
                    1
                );
            }
            return [
                'contract_html' => $contract_html,
                'repeat_footer_html' => '',
                'custom_footer_active' => true,
            ];
        }

        // No custom image: revert any stale runtime marker and use the approved Hami footer.
        $contract_html = preg_replace('/\\s+mlr-hami-custom-footer-active\\b/', '', $contract_html);
        if ($repeat_footer_html === '' && function_exists('mlr_render_hami_retirees_page_footer')) {
            $repeat_footer_html = mlr_render_hami_retirees_page_footer((string) $buyer_name);
        }
        return [
            'contract_html' => $contract_html,
            'repeat_footer_html' => $repeat_footer_html,
            'custom_footer_active' => false,
        ];
    }
}

if (!function_exists('mlr_loan_contract_jalali_month_length')) {
    function mlr_loan_contract_jalali_month_length($year, $month) {
        $year = (int) $year;
        $month = (int) $month;
        if ($year <= 0 || $month < 1 || $month > 12) return 0;
        if ($month <= 6) return 31;
        if ($month <= 11) return 30;
        if (!function_exists('mlr_jalali_to_gregorian') || !function_exists('mlr_gregorian_to_jalali')) return 29;
        $next_year_first = mlr_jalali_to_gregorian(sprintf('%04d/01/01', $year + 1));
        if (!$next_year_first) return 29;
        try {
            $dt = new DateTime($next_year_first . ' 00:00:00', new DateTimeZone('Asia/Tehran'));
            $dt->modify('-1 day');
            $last = mlr_gregorian_to_jalali($dt->format('Y-m-d H:i:s'), 'Y/m/d');
            if (preg_match('/^\d{4}\/12\/(29|30)$/', (string) $last, $m)) {
                return (int) $m[1];
            }
        } catch (Throwable $e) {
            // Fall through to the conservative 29-day Esfand fallback.
        }
        return 29;
    }
}

if (!function_exists('mlr_loan_contract_add_jalali_months')) {
    /** Add calendar months in the Jalali calendar while preserving the local clock time. */
    function mlr_loan_contract_add_jalali_months($mysql_datetime, $months) {
        $months = max(0, absint($months));
        if (!function_exists('mlr_gregorian_to_jalali') || !function_exists('mlr_jalali_to_gregorian')) {
            return mlr_loan_contract_add_months($mysql_datetime, $months);
        }
        $jalali = mlr_gregorian_to_jalali($mysql_datetime, 'Y/m/d');
        if (!preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', (string) $jalali, $m)) {
            return mlr_loan_contract_add_months($mysql_datetime, $months);
        }
        $year = (int) $m[1];
        $month = (int) $m[2];
        $day = (int) $m[3];
        $month_index = ($year * 12 + ($month - 1)) + $months;
        $target_year = intdiv($month_index, 12);
        $target_month = ($month_index % 12) + 1;
        $target_day = min($day, mlr_loan_contract_jalali_month_length($target_year, $target_month));
        $g = mlr_jalali_to_gregorian(sprintf('%04d/%02d/%02d', $target_year, $target_month, $target_day));
        if (!$g) return mlr_loan_contract_add_months($mysql_datetime, $months);
        $time = '00:00:00';
        try {
            $dt = new DateTime((string) $mysql_datetime, new DateTimeZone('Asia/Tehran'));
            $time = $dt->format('H:i:s');
        } catch (Throwable $e) {
        }
        return $g . ' ' . $time;
    }
}

if (!function_exists('mlr_loan_contract_first_due_date_mysql')) {
    /** Resolve the plan-specific first-installment rule without changing legacy plans. */
    function mlr_loan_contract_first_due_date_mysql($contract_start_mysql, $installment_interval, $rule = 'interval') {
        $rule = sanitize_key((string) $rule);
        $installment_interval = max(1, absint($installment_interval));
        if ($rule !== 'jalali_month_end_half' || !function_exists('mlr_gregorian_to_jalali') || !function_exists('mlr_jalali_to_gregorian')) {
            return mlr_loan_contract_add_months($contract_start_mysql, $installment_interval);
        }
        $jalali = mlr_gregorian_to_jalali($contract_start_mysql, 'Y/m/d');
        if (!preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', (string) $jalali, $m)) {
            return mlr_loan_contract_add_months($contract_start_mysql, $installment_interval);
        }
        $year = (int) $m[1];
        $month = (int) $m[2];
        $day = (int) $m[3];
        if ($day >= 16) {
            $month++;
            if ($month > 12) {
                $month = 1;
                $year++;
            }
        }
        $last_day = mlr_loan_contract_jalali_month_length($year, $month);
        $g = mlr_jalali_to_gregorian(sprintf('%04d/%02d/%02d', $year, $month, $last_day));
        return $g ? ($g . ' 23:59:59') : mlr_loan_contract_add_months($contract_start_mysql, $installment_interval);
    }
}

if (!function_exists('mlr_validate_hami_retirees_contract_values')) {
    /** Fail closed before OTP so the approved legal template is never signed with material blanks. */
    function mlr_validate_hami_retirees_contract_values(array $values, array $settings) {
        $labels = [
            'full_name' => 'نام و نام خانوادگی خریدار',
            'father_name' => 'نام پدر',
            'certificate_number' => 'شماره شناسنامه',
            'certificate_city' => 'محل صدور شناسنامه',
            'birth_date' => 'تاریخ تولد',
            'national_code' => 'کد ملی',
            'residential_address' => 'نشانی خریدار',
            'postal_code' => 'کد پستی',
            'phone_number' => 'تلفن ثابت',
            'mobile_number' => 'تلفن همراه',
            'product_name' => 'نام کالا',
            'invoice_number' => 'شماره فاکتور',
            'invoice_date' => 'تاریخ فاکتور',
            'cash_price' => 'قیمت فروش نقدی',
            'installment_sale_price' => 'قیمت فروش اقساطی',
            'installment_amount' => 'مبلغ هر قسط',
            'payment_count' => 'تعداد اقساط',
            'contract_date' => 'تاریخ قرارداد',
        ];
        $missing = [];
        foreach ($labels as $key => $label) {
            $value = trim((string) ($values[$key] ?? ''));
            if ($value === '' || in_array($value, ['0', '0.0', '0.00'], true)) {
                $missing[] = $label;
            }
        }
        if ((int) ($values['installment_interval'] ?? 0) !== 1) {
            return new WP_Error('mlr_hami_retirees_interval_invalid', 'قرارداد نهایی حامی بازنشستگان صراحتاً بازپرداخت ماهانه دارد؛ فاصله اقساط این پرونده باید ۱ ماه باشد.');
        }
        $configured_rate = (float) ($values['annual_interest_rate_numeric'] ?? 0);
        if (abs($configured_rate - 26.0) > 0.0001) {
            return new WP_Error('mlr_hami_retirees_rate_invalid', 'نرخ سود طرح با متن قرارداد نهایی حامی بازنشستگان همخوان نیست؛ نرخ قرارداد باید ۲۶ درصد باشد.');
        }
        if (!empty($missing)) {
            return new WP_Error('mlr_hami_retirees_contract_incomplete', 'امضای دیجیتال متوقف شد چون اطلاعات الزامی قرارداد کامل نیست: ' . implode('، ', $missing) . '.');
        }
        return true;
    }
}

if (!function_exists('mlr_generate_loan_contract_party_token')) {
    /** Generate a visually distinct, non-OTP confirmation token for each signing party. */
    function mlr_generate_loan_contract_party_token($party = 'buyer') {
        $party = $party === 'platform' ? 'platform' : 'buyer';
        $prefix = $party === 'platform' ? 'PLT' : 'BUY';
        try {
            $random = bin2hex(random_bytes(12));
        } catch (Throwable $e) {
            $random = wp_generate_password(24, false, false);
        }
        $random = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $random));
        if (strlen($random) < 20) {
            $random .= strtoupper(substr(hash('sha256', uniqid($prefix, true) . wp_salt('auth')), 0, 24));
        }
        return $prefix . '-' . substr($random, 0, 24);
    }
}

if (!function_exists('mlr_loan_contract_party_token')) {
    /**
     * Return the persisted party-specific token. Legacy signatures get a stable,
     * deterministic buyer/platform token at render time without schema migration.
     */
    function mlr_loan_contract_party_token($proof, $party = 'buyer') {
        if (!is_array($proof)) return '';
        $party = $party === 'platform' ? 'platform' : 'buyer';
        $field = $party === 'platform' ? 'platform_confirmation_token' : 'buyer_confirmation_token';
        $prefix = $party === 'platform' ? 'PLT' : 'BUY';

        $stored = strtoupper(preg_replace('/[^A-Z0-9\-]/i', '', (string) ($proof[$field] ?? '')));
        if ($stored !== '') return $stored;

        $request_id = absint($proof['request_id'] ?? 0);
        $signed_at = sanitize_text_field((string) ($proof['signed_at'] ?? ''));
        $digest = sanitize_text_field((string) ($proof['contract_digest'] ?? ''));
        if ($request_id <= 0 || $signed_at === '' || $digest === '') return '';

        $seed = $request_id . '|' . $signed_at . '|' . $digest . '|' . $party;
        $secret = function_exists('wp_salt') ? wp_salt('auth') : 'mlr-loan-contract-party-token';
        $token = strtoupper(substr(hash_hmac('sha256', $seed, $secret), 0, 24));
        return $prefix . '-' . $token;
    }
}



if (!function_exists('mlr_normalize_contract_markup_for_display')) {
    /**
     * Fix visual flow of legal clauses without touching plan templates, canonical
     * payloads, signature proofs, or signature digests. Only the *rendered* HTML
     * is normalized, in all preview, public-viewer and A4 print surfaces.
     */
    function mlr_normalize_contract_markup_for_display($html) {
        $html = (string) $html;
        if ($html === '' || strpos($html, 'mlr-hami-retirees-contract') === false) {
            return $html;
        }

        // Legal lead-ins: the three parties, numbered notes and article subsections.
        // Strong/bold tags are required to avoid accidentally splitting normal prose.
        $lead = '(?:[الفبج]\s*[-–—‑ـ]\s*(?:تسهیلات\s*دهنده|فروشنده|خریدار)|تبصره\s*[\p{Nd}]+|[\p{Nd}]+\s*[-–—‑]\s*[\p{Nd}]+)';
        $label = '~<(strong|b)\b[^>]*>\s*' . $lead . '\s*[:：]?\s*</\1>\s*[:：]?~iu';

        // Step 1: split consecutive legal clauses when they were merged by an
        // editor or prior normalization into ONE <p> / paragraph DIV.
        $split_block = static function($full, $attrs, $inner, $tag) use ($label) {
            if (!preg_match_all($label, $inner, $found, PREG_OFFSET_CAPTURE)) {
                return $full;
            }
            $breaks = [];
            foreach ($found[0] as $match) {
                $pos = (int) $match[1];
                $prefix = substr($inner, 0, $pos);
                if (trim(html_entity_decode(strip_tags($prefix), ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== '') {
                    $breaks[] = $pos;
                }
            }
            if (!$breaks) return $full;
            $segments = [];
            $last = 0;
            foreach ($breaks as $pos) {
                $segments[] = substr($inner, $last, $pos - $last);
                $last = $pos;
            }
            $segments[] = substr($inner, $last);
            $output = [];
            foreach ($segments as $segment) {
                if (trim($segment) === '') continue;
                $output[] = '<' . $tag . $attrs . '>' . trim($segment) . '</' . $tag . '>';
            }
            return $output ? implode("\n", $output) : $full;
        };

        if (stripos($html, '<p') !== false) {
            $candidate = preg_replace_callback('~<p\b([^>]*)>(.*?)</p\s*>~isu', static function($m) use ($split_block) {
                return $split_block($m[0], $m[1], $m[2], 'p');
            }, $html);
            if (is_string($candidate)) $html = $candidate;
        }
        // Already normalized snapshots can contain DIVs instead of paragraphs.
        if (strpos($html, 'mlr-contract-paragraph') !== false) {
            $candidate = preg_replace_callback('~<div\b([^>]*\bclass\s*=\s*["\'][^"\']*\bmlr-contract-paragraph\b[^"\']*["\'][^>]*)>(.*?)</div\s*>~isu', static function($m) use ($split_block) {
                return $split_block($m[0], $m[1], $m[2], 'div');
            }, $html);
            if (is_string($candidate)) $html = $candidate;
        }

        // Step 2: deal with HTML where TinyMCE stripped <p> altogether and
        // left the lettered clauses as loose, inline text. Insert an actual
        // line break only if content precedes a label since the last block.
        // This also covers unexpected inline content outside paragraphs.
        $offset = 0;
        while (preg_match($label, $html, $found, PREG_OFFSET_CAPTURE, $offset)) {
            $pos = (int) $found[0][1];
            $matched_length = strlen($found[0][0]);
            $before = substr($html, 0, $pos);
            $boundaries = [
                '~</?(?:p|div|h[1-6]|table|tr|td|th|section|article|ul|ol|li|blockquote)\b[^>]*>~iu',
                '~<br\b[^>]*>~iu',
            ];
            $last_boundary = 0;
            foreach ($boundaries as $boundary) {
                if (preg_match_all($boundary, $before, $matches, PREG_OFFSET_CAPTURE)) {
                    $last = end($matches[0]);
                    $last_boundary = max($last_boundary, $last[1] + strlen($last[0]));
                }
            }
            $text = substr($before, $last_boundary);
            $has_text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== '';
            if ($has_text) {
                $br = '<br class="mlr-legal-clause-break" />';
                $html = substr($html, 0, $pos) . $br . substr($html, $pos);
                $offset = $pos + strlen($br) + $matched_length;
            } else {
                $offset = $pos + $matched_length;
            }
        }

        // Step 3: ensure paragraphs in this contract render as actual block
        // boxes even in WordPress modal and print contexts with overriding p CSS.
        if (stripos($html, '<p') !== false) {
            $html = preg_replace_callback('~<p\b([^>]*)>~iu', static function($m) {
                $attrs = (string) $m[1];
                $count = 0;
                $attrs = preg_replace_callback('~\bclass\s*=\s*(["\'])(.*?)\1~isu',
                    static function($c) { return 'class=' . $c[1] . 'mlr-contract-paragraph ' . $c[2] . $c[1]; },
                    $attrs, 1, $count);
                if (!$count) $attrs = ' class="mlr-contract-paragraph"' . $attrs;
                return '<div' . $attrs . '>';
            }, $html);
            $html = preg_replace('~</p\s*>~iu', '</div>', $html);
        }
        return $html;
    }
}

if (!function_exists('mlr_repair_legacy_mixed_contract_markup_for_display')) {
    /**
     * Repair an early mixed-markup Hami Plus snapshot where structural headings/divs
     * existed but most legal paragraphs were left as newline-delimited text nodes.
     *
     * This is intentionally presentation-only. It preserves every character/value in
     * the signed snapshot, only adding missing paragraph/span wrappers in the rendered
     * copy so historical contracts remain readable without changing stored evidence.
     */
    function mlr_repair_legacy_mixed_contract_markup_for_display($html) {
        $original_html = (string) $html;
        if ($original_html === '') return $original_html;

        // Only target the exact early mixed-rich shape. Fully structured snapshots and
        // the older plain contract family are left completely untouched byte-for-byte.
        $is_mixed_legacy = strpos($original_html, 'mlr-contract-document') !== false
            && substr_count($original_html, '<h2') >= 5
            && substr_count($original_html, '<p') < 10;
        if (!$is_mixed_legacy) {
            return $original_html;
        }

        $html = str_replace(["\r\n", "\r"], "\n", $original_html);

        // The early template also lost the direct <span> children required by the
        // three-column header CSS. Recreate those wrappers from the existing lines.
        foreach (['mlr-contract-meta-row', 'mlr-contract-parties-bar'] as $class_name) {
            $pattern = '~(<div\b[^>]*class=["\'][^"\']*\b' . preg_quote($class_name, '~') . '\b[^"\']*["\'][^>]*>)(.*?)(</div>)~isu';
            $html = preg_replace_callback($pattern, static function($matches) {
                $inner = (string) ($matches[2] ?? '');
                if ($inner === '' || preg_match('/<span\b/i', $inner)) {
                    return $matches[0];
                }
                $lines = preg_split('/\n+/u', $inner);
                $spans = [];
                foreach ((array) $lines as $line) {
                    $line = trim((string) $line);
                    if ($line === '') continue;
                    $spans[] = '<span>' . $line . '</span>';
                }
                return !empty($spans)
                    ? $matches[1] . implode('', $spans) . $matches[3]
                    : $matches[0];
            }, $html);
        }

        // Re-wrap only loose text groups. Existing block markup (<p>, headings,
        // formula divs, subsections, etc.) is kept verbatim.
        $lines = preg_split('/\n/u', $html);
        $out = [];
        $loose = [];
        $protected_tag = '';

        $flush_loose = static function() use (&$loose, &$out) {
            if (empty($loose)) return;
            $parts = [];
            foreach ($loose as $line) {
                $line = trim((string) $line);
                if ($line !== '') {
                    $parts[] = $line;
                }
            }
            if (!empty($parts)) {
                $out[] = '<p class="mlr-contract-legacy-paragraph">' . implode('<br>', $parts) . '</p>';
            }
            $loose = [];
        };

        foreach ((array) $lines as $line) {
            $trimmed = trim((string) $line);

            if ($protected_tag !== '') {
                $flush_loose();
                $out[] = $line;
                if (preg_match('~</' . preg_quote($protected_tag, '~') . '\s*>~i', $trimmed)) {
                    $protected_tag = '';
                }
                continue;
            }

            if ($trimmed === '') {
                $flush_loose();
                continue;
            }

            // Preserve pre-existing paragraph/list/table cells, including multiline ones.
            if (preg_match('~^<(p|li|td|th|pre|blockquote)\b~i', $trimmed, $block_match)) {
                $flush_loose();
                $out[] = $line;
                $tag = strtolower((string) $block_match[1]);
                if (!preg_match('~</' . preg_quote($tag, '~') . '\s*>~i', $trimmed)) {
                    $protected_tag = $tag;
                }
                continue;
            }

            // Structural block tags delimit a loose paragraph but are never rewritten.
            if (preg_match('~^</?(?:div|h[1-6]|table|thead|tbody|tfoot|tr|ul|ol|section|article|header|footer)\b~i', $trimmed)) {
                $flush_loose();
                $out[] = $line;
                continue;
            }

            $loose[] = $line;
        }
        $flush_loose();

        $repaired = implode("\n", $out);
        $repaired = preg_replace(
            '/class=(["\'])mlr-contract-document\s+/i',
            'class=$1mlr-contract-document mlr-contract-legacy-layout-repaired ',
            $repaired,
            1
        );
        return is_string($repaired) && $repaired !== '' ? $repaired : $html;
    }
}

if (!function_exists('mlr_normalize_legacy_signed_loan_contract_for_display')) {
    /**
     * Apply narrowly-scoped display compatibility to historical signed snapshots.
     *
     * IMPORTANT: this never updates the stored signature option, digest, token, request
     * row, or plan. The original signed snapshot remains untouched in the database.
     * Only the HTML returned to viewers is normalized so contracts signed before the
     * later presentation/legal-detail fixes render consistently with the current UI.
     */
    function mlr_normalize_legacy_signed_loan_contract_for_display($proof, $request = null) {
        if (!is_array($proof)) return $proof;

        $html = (string) ($proof['contract_html'] ?? '');
        if ($html === '') return $proof;

        $plan_name = (string) ($proof['plan_name'] ?? '');
        $normalized_plan_name = function_exists('mlr_normalize_installment_plan_name_for_contract')
            ? mlr_normalize_installment_plan_name_for_contract($plan_name)
            : preg_replace('/[\x{200c}\x{200d}\s_\-–—]+/u', '', $plan_name);
        $is_hami_plus = strpos((string) $normalized_plan_name, 'حامیپلاس') !== false;
        $changes = [];

        if ($is_hami_plus && function_exists('mlr_repair_legacy_mixed_contract_markup_for_display')) {
            $layout_repaired = mlr_repair_legacy_mixed_contract_markup_for_display($html);
            if ($layout_repaired !== $html) {
                $html = $layout_repaired;
                $changes[] = 'legacy_layout_spacing';
            }
        }

        if ($is_hami_plus) {
            // 8.4.6.55+: only the top three-party label was requested to be "پلتفرم".
            $header_fixed = str_replace(
                [
                    'پلتفرم/فروشنده/ضامن:',
                    'پلتفرم / فروشنده / ضامن:',
                    'پلتفرم/ فروشنده/ ضامن:',
                    'پلتفرم /فروشنده/ضامن:',
                ],
                'پلتفرم:',
                $html
            );
            if ($header_fixed !== $html) {
                $html = $header_fixed;
                $changes[] = 'header_platform_label';
            }

            // 8.4.6.59+: legal gazette details requested for clause 1(b).
            $gazette_variants = [
                'مطابق روزنامه رسمی شماره ........... مورخ ..............',
                'مطابق روزنامه رسمی شماره ............ مورخ ..............',
                'مطابق روزنامه رسمی شماره ........... مورخ ...........',
                'مطابق روزنامه رسمی شماره ............ مورخ ...........',
            ];
            $gazette_fixed = str_replace(
                $gazette_variants,
                'مطابق روزنامه رسمی شماره 23644 مورخ 1405/03/11',
                $html
            );
            if ($gazette_fixed === $html) {
                $gazette_fixed = preg_replace(
                    '/مطابق\s+روزنامه\s+رسمی\s+شماره\s*(?:\.{3,}|…+)\s*مورخ\s*(?:\.{3,}|…+)/u',
                    'مطابق روزنامه رسمی شماره 23644 مورخ 1405/03/11',
                    $html
                );
            }
            if (is_string($gazette_fixed) && $gazette_fixed !== $html) {
                $html = $gazette_fixed;
                $changes[] = 'official_gazette_23644';
            }

            // Re-check the explicit Hami Plus duration clause against the signed contract
            // date and the historical request term. This is display-only and only runs when
            // all source values are available and the exact structured clause is present.
            $request_id = absint($proof['request_id'] ?? 0);
            if (!$request && $request_id > 0) {
                global $wpdb;
                if (isset($wpdb) && is_object($wpdb)) {
                    $request = $wpdb->get_row($wpdb->prepare(
                        "SELECT id, installment_count FROM `{$wpdb->prefix}loan_requests` WHERE id = %d LIMIT 1",
                        $request_id
                    ));
                }
            }
            $installment_count = 0;
            if (is_array($request)) {
                $installment_count = absint($request['installment_count'] ?? 0);
            } elseif (is_object($request)) {
                $installment_count = absint($request->installment_count ?? 0);
            }
            $contract_date_jalali = sanitize_text_field((string) ($proof['contract_date'] ?? ''));
            if ($installment_count > 0 && preg_match('/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}$/', $contract_date_jalali)
                && function_exists('mlr_jalali_to_gregorian')
                && function_exists('mlr_loan_contract_add_months')
                && function_exists('mlr_loan_contract_display_date')) {
                $start_gregorian = mlr_jalali_to_gregorian($contract_date_jalali);
                if ($start_gregorian) {
                    $end_mysql = mlr_loan_contract_add_months($start_gregorian . ' 00:00:00', $installment_count);
                    $expected_end = mlr_loan_contract_display_date($end_mysql);
                    if ($expected_end !== '') {
                        $duration_pattern = '/(مدت\s+اين\s+قرارداد\s*(?:<[^>]+>)*\s*' . preg_quote((string) $installment_count, '/') . '\s*(?:<[^>]+>)*\s*ماه.*?از\s+تاريخ.*?)(\d{4}\/\d{2}\/\d{2})(.*?در\s+تاريخ.*?)(\d{4}\/\d{2}\/\d{2})(.*?خاتمه\s*مي\s*يابد)/us';
                        $date_changed = false;
                        $date_fixed = preg_replace_callback($duration_pattern, static function($m) use ($expected_end, &$date_changed) {
                            if ((string) $m[4] === (string) $expected_end) return $m[0];
                            $date_changed = true;
                            return $m[1] . $m[2] . $m[3] . $expected_end . $m[5];
                        }, $html, 1);
                        if ($date_changed && is_string($date_fixed)) {
                            $html = $date_fixed;
                            $changes[] = 'contract_end_date';
                        }
                    }
                }
            }
        }

        if (function_exists('mlr_normalize_contract_markup_for_display')) {
            $html = mlr_normalize_contract_markup_for_display($html);
        }

        $proof['contract_html'] = $html;
        if (!empty($changes)) {
            $proof['_display_compat_version'] = '8.4.6.61';
            $proof['_display_compat_changes'] = array_values(array_unique($changes));
        }
        return $proof;
    }
}

if (!function_exists('mlr_get_current_loan_contract_presentation_assets')) {
    /**
     * Resolve the CURRENT plan letterhead/footer used only for presentation/printing.
     *
     * Contract text/signature snapshots remain immutable. The plan's current media
     * settings are authoritative for display so a selected header/footer appears in
     * every portal, while a cleared field does not resurrect a stale snapshot asset.
     */
    function mlr_get_current_loan_contract_presentation_assets($request = null, $fallback = []) {
        $fallback = is_array($fallback) ? $fallback : [];
        $result = [
            'letterhead_url' => esc_url_raw((string) ($fallback['letterhead_url'] ?? '')),
            'footer_url' => esc_url_raw((string) ($fallback['footer_url'] ?? '')),
            'plan_id' => absint($fallback['plan_id'] ?? 0),
            'source' => 'snapshot_fallback',
        ];

        $get = static function($source, $key, $default = '') {
            if (is_array($source)) return array_key_exists($key, $source) ? $source[$key] : $default;
            return is_object($source) && isset($source->{$key}) ? $source->{$key} : $default;
        };

        $plan_id = absint($get($request, 'installment_plan_id', 0));
        if ($plan_id <= 0) $plan_id = absint($get($request, 'plan_id', 0));
        if ($plan_id <= 0) $plan_id = absint($fallback['plan_id'] ?? 0);
        if ($plan_id <= 0) return $result;

        global $wpdb;
        $plans_table = $wpdb->prefix . 'installment_plans';
        $plan = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, settings FROM `{$plans_table}` WHERE id = %d LIMIT 1",
            $plan_id
        ));
        if (!$plan) return $result;

        $settings = function_exists('mlr_get_plan_settings_array')
            ? mlr_get_plan_settings_array($plan)
            : maybe_unserialize($plan->settings);
        if (!is_array($settings)) $settings = [];

        // Current plan fields are authoritative even when empty. This is deliberate:
        // clearing an asset in the plan must remove it from every preview/print view.
        return [
            'letterhead_url' => $plan_letterhead_url,
            'footer_url' => $plan_footer_url,
            'plan_id' => $plan_id,
            'source' => 'current_plan',
        ];
    }
}

if (!function_exists('mlr_get_loan_contract_signature_for_display')) {
    /** Read a signature proof and normalize only its rendered copy; never persist changes. */
    function mlr_get_loan_contract_signature_for_display($request_id, $request = null) {
        $proof = function_exists('mlr_get_loan_contract_signature')
            ? mlr_get_loan_contract_signature($request_id)
            : null;
        if (!is_array($proof)) return $proof;

        if (function_exists('mlr_normalize_legacy_signed_loan_contract_for_display')) {
            $proof = mlr_normalize_legacy_signed_loan_contract_for_display($proof, $request);
        }

        if (function_exists('mlr_get_current_loan_contract_presentation_assets')) {
            $assets = mlr_get_current_loan_contract_presentation_assets($request, $proof);
            $proof['letterhead_url'] = (string) ($assets['letterhead_url'] ?? '');
            $proof['footer_url'] = (string) ($assets['footer_url'] ?? '');
            $proof['_presentation_assets_source'] = (string) ($assets['source'] ?? '');
        }
        if (function_exists('mlr_apply_hami_footer_policy')) {
            $footer_policy = mlr_apply_hami_footer_policy(
                (string) ($proof['contract_html'] ?? ''),
                (string) ($proof['footer_url'] ?? ''),
                (string) ($proof['repeat_footer_html'] ?? ''),
                (string) ($proof['signer_name'] ?? ''),
                (string) ($proof['plan_name'] ?? '')
            );
            $proof['contract_html'] = (string) ($footer_policy['contract_html'] ?? ($proof['contract_html'] ?? ''));
            $proof['repeat_footer_html'] = (string) ($footer_policy['repeat_footer_html'] ?? '');
            $proof['_hami_custom_footer_active'] = !empty($footer_policy['custom_footer_active']);
        }
        return $proof;
    }
}

if (!function_exists('mlr_render_loan_contract_signature_certificate')) {
    /** Printable dual-party proof block appended to the immutable signed contract snapshot. */
    function mlr_render_loan_contract_signature_certificate($proof) {
        if (!is_array($proof) || empty($proof['signed_at'])) return '';
        $signed_at = function_exists('mlr_loan_contract_signed_display_date')
            ? mlr_loan_contract_signed_display_date($proof)
            : (string) ($proof['signed_at'] ?? '');
        $request_id = absint($proof['request_id'] ?? 0);
        $name = sanitize_text_field((string) ($proof['signer_name'] ?? ''));
        $mobile = function_exists('mlr_loan_contract_signature_mobile') ? mlr_loan_contract_signature_mobile($proof) : '';
        $buyer_token = function_exists('mlr_loan_contract_party_token')
            ? mlr_loan_contract_party_token($proof, 'buyer')
            : '';
        $platform_token = function_exists('mlr_loan_contract_party_token')
            ? mlr_loan_contract_party_token($proof, 'platform')
            : '';
        ob_start();
        ?>
        <div class="mlr-digital-signature-stack">
            <div class="mlr-digital-signature-certificate mlr-digital-signature-buyer">
                <div class="mlr-digital-signature-icon">✓</div>
                <div class="mlr-digital-signature-content">
                    <strong>تأیید و امضای دیجیتال خریدار</strong>
                    <span>این قرارداد در تاریخ و ساعت <?php echo esc_html($signed_at); ?> با کد تأیید پیامکی توسط <?php echo esc_html($name !== '' ? $name : 'خریدار'); ?> امضا و تأیید شده است.</span>
                    <div class="mlr-digital-signature-meta">
                        <?php if ($request_id > 0): ?><span>شناسه پرونده: <?php echo esc_html($request_id); ?></span><?php endif; ?>
                        <?php if ($mobile !== ''): ?><span>موبایل تأییدشده: <bdi dir="ltr" class="mlr-contract-mobile-ltr"><?php echo esc_html($mobile); ?></bdi></span><?php endif; ?>
                        <?php if ($buyer_token !== ''): ?><span>توکن تأیید دیجیتال قرارداد: <bdi dir="ltr" class="mlr-contract-token-ltr"><?php echo esc_html($buyer_token); ?></bdi></span><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="mlr-digital-signature-certificate mlr-digital-signature-company">
                <div class="mlr-digital-signature-icon">✓</div>
                <div class="mlr-digital-signature-content">
                    <strong>تأیید و امضای دیجیتال پلتفرم</strong>
                    <span>این قرارداد در تاریخ و ساعت <?php echo esc_html($signed_at); ?> توسط پلتفرم امضا و تأیید شده است.</span>
                    <div class="mlr-digital-signature-meta">
                        <?php if ($request_id > 0): ?><span>شناسه پرونده: <?php echo esc_html($request_id); ?></span><?php endif; ?>
                        <span>شناسه ملی: 14011126653</span>
                        <?php if ($platform_token !== ''): ?><span>توکن تأیید دیجیتال قرارداد: <bdi dir="ltr" class="mlr-contract-token-ltr"><?php echo esc_html($platform_token); ?></bdi></span><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return trim((string) ob_get_clean());
    }
}

if (!function_exists('mlr_get_loan_contract_payload')) {
    /**
     * Build the exact contract content used for the digital-signature digest.
     * $contract_date_mysql may be supplied by the OTP snapshot so verification can
     * detect plan/content changes without a date rollover changing the digest.
     */
    function mlr_get_loan_contract_payload($request, $contract_date_mysql = '', $strict_validation = false) {
        if (!$request) {
            return new WP_Error('mlr_contract_request_missing', 'اطلاعات پرونده برای قرارداد یافت نشد.');
        }

        global $wpdb;
        $request_id = absint(is_array($request) ? ($request['id'] ?? 0) : ($request->id ?? 0));
        $plan_id = absint(is_array($request) ? ($request['installment_plan_id'] ?? 0) : ($request->installment_plan_id ?? 0));
        if ($request_id <= 0 || $plan_id <= 0) {
            return new WP_Error('mlr_contract_request_invalid', 'اطلاعات پرونده یا طرح قرارداد نامعتبر است.');
        }

        $plans_table = $wpdb->prefix . 'installment_plans';
        $plan = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, settings FROM `{$plans_table}` WHERE id = %d LIMIT 1",
            $plan_id
        ));
        if (!$plan) {
            return new WP_Error('mlr_contract_plan_missing', 'طرح مربوط به قرارداد یافت نشد.');
        }

        $settings = function_exists('mlr_get_plan_settings_array')
            ? mlr_get_plan_settings_array($plan)
            : maybe_unserialize($plan->settings);
        if (!is_array($settings)) {
            $settings = [];
        }

        $get = static function($source, $key, $default = '') {
            if (is_array($source)) {
                return array_key_exists($key, $source) ? $source[$key] : $default;
            }
            return isset($source->{$key}) ? $source->{$key} : $default;
        };

        $form_data_raw = $get($request, 'form_data', '');
        $form_data = is_array($form_data_raw) ? $form_data_raw : json_decode((string) $form_data_raw, true);
        if (!is_array($form_data)) {
            $form_data = [];
        }

        $product_name_display = (string) $get($request, 'product_name', '');
        if ($product_name_display === '' && !empty($form_data['order_items'])) {
            $items = is_string($form_data['order_items']) ? json_decode($form_data['order_items'], true) : $form_data['order_items'];
            if (is_array($items)) {
                $names = array_map(static function($item) {
                    return is_array($item) ? (string) ($item['name'] ?? '') : (string) $item;
                }, $items);
                $product_name_display = implode('، ', array_filter($names, static function($name) { return $name !== ''; }));
            }
        }

        $contract_date_mysql = trim((string) $contract_date_mysql);
        if ($contract_date_mysql === '') {
            $contract_date_mysql = current_time('mysql');
        }
        $contract_date_display = function_exists('mlr_gregorian_to_jalali')
            ? mlr_gregorian_to_jalali($contract_date_mysql, 'Y/m/d')
            : date('Y/m/d', strtotime($contract_date_mysql));

        $created_at = (string) $get($request, 'created_at', '');
        $birth_date = (string) $get($request, 'birth_date', '');
        $installment_count = absint($get($request, 'installment_count', 0));
        $installment_interval = max(1, absint($get($request, 'installment_interval', 1)));
        $installment_amount_value = (float) $get($request, 'installment_amount', 0);
        $loan_amount_value = (float) $get($request, 'loan_amount', 0);
        $facility_amount_value = (float) $get($request, 'facility_amount', 0);
        if ($facility_amount_value <= 0) $facility_amount_value = $loan_amount_value;
        $total_payment_value = (float) $get($request, 'total_payment', 0);
        $order_total_value = (float) $get($request, 'order_total', 0);
        $template_key = sanitize_key((string) ($settings['plan_contract_template_key'] ?? ''));

        $contract_number = (string) apply_filters('mlr_loan_contract_number', (string) $request_id, $request, $plan);
        $invoice_number = (string) apply_filters('mlr_invoice_number', (string) $request_id, $request);
        $invoice_date = $created_at !== '' ? mlr_loan_contract_display_date($created_at) : $contract_date_display;
        $invoice_date = (string) apply_filters('mlr_invoice_date', $invoice_date, $request);
        $contract_duration_months = max(1, $installment_count);
        $payment_count = max(1, (int) round($installment_count / $installment_interval));
        if ($total_payment_value <= 0 && $installment_amount_value > 0 && $payment_count > 0) {
            $total_payment_value = $installment_amount_value * $payment_count;
        }
        $total_price_value = $total_payment_value > 0 ? $total_payment_value : ($order_total_value > 0 ? $order_total_value : $loan_amount_value);
        $cash_price_value = $facility_amount_value > 0 ? $facility_amount_value : ($order_total_value > 0 ? $order_total_value : $loan_amount_value);
        $installment_sale_price_value = $total_payment_value > 0 ? $total_payment_value : $total_price_value;
        $marketing_development_amount_value = function_exists('mlr_parse_decimal_number')
            ? (float) mlr_parse_decimal_number($settings['contract_marketing_development_amount'] ?? 0)
            : (float) ($settings['contract_marketing_development_amount'] ?? 0);
        $contract_start_date_mysql = $contract_date_mysql;
        $contract_end_date_mysql = in_array($template_key, ['hami-retirees-final-1405-v1', 'hami-retirees-final-1405-v2'], true)
            ? mlr_loan_contract_add_jalali_months($contract_start_date_mysql, $contract_duration_months)
            : mlr_loan_contract_add_months($contract_start_date_mysql, $contract_duration_months);
        $first_due_rule = sanitize_key((string) ($settings['contract_first_due_rule'] ?? 'interval'));
        $first_due_date_mysql = mlr_loan_contract_first_due_date_mysql($contract_start_date_mysql, $installment_interval, $first_due_rule);
        $delivery_date = mlr_loan_contract_form_value($form_data, ['delivery_date', 'product_delivery_date', 'goods_delivery_date'], '');
        if ($delivery_date === '') $delivery_date = $contract_date_display;
        $delivery_date = (string) apply_filters('mlr_loan_contract_delivery_date', $delivery_date, $request, $plan);
        $retirement_code = mlr_loan_contract_form_value($form_data, ['retirement_code', 'pension_code', 'retired_code'], '');
        $retirement_organization = mlr_loan_contract_form_value($form_data, ['retirement_organization', 'pension_organization', 'retirement_org', 'organization_name'], '');
        $annual_interest_rate = (float) ($settings['interest_rate'] ?? 0);
        if ($annual_interest_rate <= 0) $annual_interest_rate = 26.0;
        $occupation_label = mlr_loan_contract_occupation_label($get($request, 'occupation', ''));
        $work_address_display = trim((string) $get($request, 'work_address', ''));
        if ($work_address_display === '' && $occupation_label !== '') {
            $work_address_display = $occupation_label;
        }
        $fintech_logo_url = defined('MLR_PLUGIN_URL') ? MLR_PLUGIN_URL . 'assets/images/etn-fin-tech.png' : '';

        $validation_values = [
            'full_name' => (string) $get($request, 'full_name', ''),
            'father_name' => (string) ($form_data['father_name'] ?? ''),
            'certificate_number' => (string) ($form_data['certificate_number'] ?? ''),
            'certificate_city' => (string) ($form_data['certificate_city'] ?? ''),
            'birth_date' => $birth_date !== '' ? mlr_loan_contract_display_date($birth_date) : '',
            'national_code' => (string) $get($request, 'national_code', ''),
            'residential_address' => (string) $get($request, 'residential_address', ''),
            'postal_code' => (string) $get($request, 'postal_code', ''),
            'phone_number' => (string) $get($request, 'phone_number', ''),
            'mobile_number' => (string) $get($request, 'mobile_number', ''),
            'product_name' => $product_name_display,
            'invoice_number' => $invoice_number,
            'invoice_date' => $invoice_date,
            'cash_price' => $cash_price_value,
            'installment_sale_price' => $installment_sale_price_value,
            'installment_amount' => $installment_amount_value,
            'payment_count' => $payment_count,
            'installment_interval' => $installment_interval,
            'contract_date' => $contract_date_display,
            'marketing_development_amount' => $marketing_development_amount_value,
            'annual_interest_rate_numeric' => $annual_interest_rate,
        ];
        if ($strict_validation && in_array($template_key, ['hami-retirees-final-1405-v1', 'hami-retirees-final-1405-v2'], true)) {
            $validation = mlr_validate_hami_retirees_contract_values($validation_values, $settings);
            if (is_wp_error($validation)) {
                return $validation;
            }
        }

        $plain_placeholder_map = [
            '[request_id]'          => (string) $request_id,
            '[contract_number]'     => $contract_number,
            '[full_name]'           => (string) $get($request, 'full_name', ''),
            '[national_code]'       => (string) $get($request, 'national_code', ''),
            '[mobile_number]'       => (string) $get($request, 'mobile_number', ''),
            '[loan_amount]'         => number_format($loan_amount_value),
            '[facility_amount]'     => number_format($facility_amount_value),
            '[installment_count]'   => (string) $installment_count, // legacy field: selected repayment term
            '[installment_term_months]' => (string) $installment_count,
            '[payment_count]'       => (string) $payment_count,
            '[installment_interval]'=> (string) $installment_interval,
            '[installment_amount]'  => number_format($installment_amount_value),
            '[total_payment]'       => number_format($total_payment_value),
            '[plan_name]'           => (string) $plan->name,
            '[created_at]'          => $created_at !== '' ? mlr_loan_contract_display_date($created_at) : '',
            '[father_name]'         => (string) ($form_data['father_name'] ?? ''),
            '[certificate_number]'  => (string) ($form_data['certificate_number'] ?? ''),
            '[certificate_city]'    => (string) ($form_data['certificate_city'] ?? ''),
            '[birth_date]'          => $birth_date !== '' ? mlr_loan_contract_display_date($birth_date) : '',
            '[residential_address]' => (string) $get($request, 'residential_address', ''),
            '[postal_code]'         => (string) $get($request, 'postal_code', ''),
            '[work_address]'        => $work_address_display,
            '[phone_number]'        => (string) $get($request, 'phone_number', ''),
            '[prepayment]'          => number_format((float) $get($request, 'prepayment', 0)),
            '[guarantee_check_principal]' => number_format((float) ($form_data['guarantee_check_principal'] ?? 0)),
            '[guarantee_check_total]'     => number_format((float) ($form_data['guarantee_check_total'] ?? 0)),
            '[contract_date]'       => $contract_date_display,
            '[contract_start_date]' => mlr_loan_contract_display_date($contract_start_date_mysql),
            '[contract_end_date]'   => mlr_loan_contract_display_date($contract_end_date_mysql),
            '[contract_duration_months]' => (string) $contract_duration_months,
            '[occupation]'          => $occupation_label,
            '[product_name]'        => $product_name_display,
            '[invoice_number]'      => $invoice_number,
            '[invoice_date]'        => $invoice_date,
            '[retirement_code]'     => $retirement_code,
            '[retirement_organization]' => $retirement_organization,
            '[total_price]'         => number_format($total_price_value),
            '[total_price_words]'   => mlr_number_to_persian_words($total_price_value),
            '[first_installment_amount]' => number_format($installment_amount_value),
            '[first_installment_amount_words]' => mlr_number_to_persian_words($installment_amount_value),
            '[remaining_installment_amount]' => number_format($installment_amount_value),
            '[remaining_installment_amount_words]' => mlr_number_to_persian_words($installment_amount_value),
            '[first_due_date]'      => mlr_loan_contract_display_date($first_due_date_mysql),
            '[delivery_date]'       => $delivery_date,
            '[annual_interest_rate]'=> rtrim(rtrim(number_format($annual_interest_rate, 2, '.', ''), '0'), '.'),
            '[cash_price]'          => number_format($cash_price_value),
            '[cash_price_words]'    => mlr_number_to_persian_words($cash_price_value),
            '[installment_sale_price]' => number_format($installment_sale_price_value),
            '[installment_sale_price_words]' => mlr_number_to_persian_words($installment_sale_price_value),
            '[marketing_development_amount]' => number_format($marketing_development_amount_value),
            '[marketing_development_amount_words]' => mlr_number_to_persian_words($marketing_development_amount_value),
            '[fintech_logo_url]'    => $fintech_logo_url,
        ];
        $placeholder_map = [];
        foreach ($plain_placeholder_map as $placeholder => $value) {
            $placeholder_map[$placeholder] = esc_html((string) $value);
        }

        $reissue_override = function_exists('mlr_get_loan_contract_reissue_override')
            ? mlr_get_loan_contract_reissue_override($request_id)
            : null;
        $contract_revision = is_array($reissue_override) ? max(1, absint($reissue_override['revision'] ?? 1)) : 1;
        $contract_text_raw = is_array($reissue_override) && !empty($reissue_override['contract_text'])
            ? (string) $reissue_override['contract_text']
            : (string) ($settings['plan_contract_text'] ?? '');
        $contract_text_rendered = $contract_text_raw;
        if ($contract_text_rendered !== '') {
            $contract_text_rendered = strtr($contract_text_rendered, $placeholder_map);
            // Rich contract templates already contain intentional block layout. Avoid wpautop
            // adding stray paragraphs around those blocks; keep legacy plain-text templates unchanged.
            if (!preg_match('/<(?:div|p|h[1-6]|table|ul|ol|blockquote)\b/i', $contract_text_rendered)) {
                $contract_text_rendered = wpautop($contract_text_rendered);
            }
        }

        $is_hami_retirees_v2 = ($template_key === 'hami-retirees-final-1405-v2')
            && (!function_exists('mlr_is_hami_plus_plan_name') || !mlr_is_hami_plus_plan_name((string) $plan->name));
        $plan_letterhead_url = esc_url_raw(trim((string) ($settings['plan_letterhead_url'] ?? '')));
        $plan_footer_url = esc_url_raw(trim((string) ($settings['plan_footer_url'] ?? '')));
        $repeat_footer_html = ($is_hami_retirees_v2 && $plan_footer_url === '' && function_exists('mlr_render_hami_retirees_page_footer'))
            ? mlr_render_hami_retirees_page_footer((string) $get($request, 'full_name', ''))
            : '';
        if (function_exists('mlr_apply_hami_footer_policy')) {
            $footer_policy = mlr_apply_hami_footer_policy(
                $contract_text_rendered,
                $plan_footer_url,
                $repeat_footer_html,
                (string) $get($request, 'full_name', ''),
                (string) $plan->name
            );
            $contract_text_rendered = (string) ($footer_policy['contract_html'] ?? $contract_text_rendered);
            $repeat_footer_html = (string) ($footer_policy['repeat_footer_html'] ?? '');
        }
        $canonical = [
            'version' => 1,
            'request_id' => $request_id,
            'plan_id' => $plan_id,
            'plan_name' => (string) $plan->name,
            'contract_revision' => $contract_revision,
            'contract_date' => $contract_date_display,
            'contract_html' => wp_kses_post($contract_text_rendered),
            // Plan-selected presentation assets are supported for every loan contract,
            // including Hami. Hami's internal logo/repeating signature footer remains
            // part of its contract template, while these optional images wrap the page.
            'letterhead_url' => esc_url_raw(trim((string) ($settings['plan_letterhead_url'] ?? ''))),
            'footer_url' => esc_url_raw(trim((string) ($settings['plan_footer_url'] ?? ''))),
            'repeat_footer_html' => wp_kses_post($repeat_footer_html),
        ];
        $encoded = wp_json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $digest = hash('sha256', is_string($encoded) ? $encoded : serialize($canonical));

        return [
            'request_id' => $request_id,
            'plan_id' => $plan_id,
            'plan_name' => (string) $plan->name,
            'contract_revision' => $contract_revision,
            'settings' => $settings,
            'letterhead_url' => $canonical['letterhead_url'],
            'footer_url' => $canonical['footer_url'],
            'repeat_footer_html' => $canonical['repeat_footer_html'],
            'contract_text_raw' => $contract_text_raw,
            'contract_html' => $canonical['contract_html'],
            'contract_date_mysql' => $contract_date_mysql,
            'contract_date_display' => $contract_date_display,
            'contract_digest' => $digest,
        ];
    }
}

if (!function_exists('mlr_loan_contract_get_otp_settings')) {
    /**
     * Loan-contract OTP deliberately mirrors the proven cooperation-sign flow.
     * The OTP is sent with the exact cooperation_sign Body ID and the exact
     * configured variable count; the contract link is sent in a separate SMS
     * through the cooperation "تایید مدارک" pattern, just like cooperation.
     */
    function mlr_loan_contract_get_otp_settings() {
        $sms_options = get_option('mlr_sms_settings', []);
        $shared = $sms_options['otp_settings']['cooperation_sign'] ?? [];
        if (!is_array($shared)) $shared = [];
        $shared['_source'] = 'cooperation_sign';
        return $shared;
    }
}

if (!function_exists('mlr_loan_contract_sms_replacements')) {
    function mlr_loan_contract_sms_replacements($request, $plan_name, $otp_code = '') {
        $get = static function($source, $key, $default = '') {
            if (is_array($source)) {
                return array_key_exists($key, $source) ? $source[$key] : $default;
            }
            return isset($source->{$key}) ? $source->{$key} : $default;
        };
        $full_name = (string) $get($request, 'full_name', '');
        $request_id = absint($get($request, 'id', 0));
        $contract_token = function_exists('mlr_loan_contract_view_token') ? mlr_loan_contract_view_token($request_id) : '';
        $contract_url = function_exists('mlr_get_loan_contract_view_url') ? mlr_get_loan_contract_view_url($request_id) : '';

        return [
            '[[otp_code]]' => (string) $otp_code,
            '[[request_id]]' => (string) $request_id,
            '[[applicant_name]]' => $full_name,
            '[[full_name]]' => $full_name,
            '[[store_name]]' => $full_name,
            '[[store_owner_name]]' => $full_name,
            '[[company_name]]' => $full_name,
            '[[contact_person]]' => $full_name,
            '[[mobile_number]]' => (string) $get($request, 'mobile_number', ''),
            '[[national_code]]' => (string) $get($request, 'national_code', ''),
            '[[plan_name]]' => (string) $plan_name,
            // Cooperation link patterns already contain the public page URL and
            // expect only the token in this placeholder. The shared public route
            // recognizes lc... tokens and renders the facility contract.
            '[[contract_link]]' => (string) $contract_token,
            '[[contract_url]]' => (string) $contract_url,
        ];
    }
}

if (!function_exists('mlr_loan_contract_build_otp_sms_variables')) {
    /** Build OTP variables with the exact same rules as cooperation signing. */
    function mlr_loan_contract_build_otp_sms_variables(array $otp_settings, $request, $plan_name, $otp_code) {
        $configured = [];
        $contains_otp_placeholder = false;
        $replacements = mlr_loan_contract_sms_replacements($request, $plan_name, $otp_code);

        for ($i = 0; $i < 3; $i++) {
            $key = 'var_' . $i;
            $template = isset($otp_settings[$key]) ? trim((string) $otp_settings[$key]) : '';
            if ($template === '') continue;
            if (strpos($template, '[[otp_code]]') !== false) {
                $contains_otp_placeholder = true;
            }
            $configured[] = strtr($template, $replacements);
        }

        if (!empty($configured) && $contains_otp_placeholder) {
            return $configured;
        }

        // This is intentionally identical to cooperation: a legacy/misconfigured
        // variable map falls back to one OTP variable instead of appending a link
        // and changing the Body ID variable count.
        return [(string) $otp_code];
    }
}

if (!function_exists('mlr_loan_contract_get_link_sms_settings')) {
    /**
     * Reuse the exact cooperation status pattern that already delivers the
     * contract link successfully in production.
     */
    function mlr_loan_contract_get_link_sms_settings() {
        $sms_options = get_option('mlr_sms_settings', []);
        $statuses = is_array($sms_options['cooperation_statuses'] ?? null)
            ? $sms_options['cooperation_statuses']
            : [];
        foreach (['تایید مدارک', 'تأیید مدارک'] as $status_key) {
            $settings = $statuses[$status_key] ?? [];
            if (is_array($settings) && !empty($settings['bodyId'])) {
                $settings['_source'] = 'cooperation_status_' . $status_key;
                return $settings;
            }
        }
        return [];
    }
}

if (!function_exists('mlr_loan_contract_build_link_sms_variables')) {
    /** Build link-SMS variables using the cooperation status pattern mapping. */
    function mlr_loan_contract_build_link_sms_variables(array $link_settings, $request, $plan_name) {
        $variables = [];
        $replacements = mlr_loan_contract_sms_replacements($request, $plan_name, '');

        // Match the established cooperation-status sender exactly: every
        // configured non-empty var_N is sent, in the same order, and no extra
        // variable is appended. This keeps the variable count aligned with the
        // configured Body ID and prevents false "variables/Body ID" errors.
        for ($i = 0; $i < 3; $i++) {
            $template = trim((string) ($link_settings['var_' . $i] ?? ''));
            if ($template === '') continue;
            $variables[] = strtr($template, $replacements);
        }

        if (!empty($variables)) {
            return $variables;
        }

        // Backward compatibility for very old installations that have the
        // approved two-variable Body ID but no saved variable map.
        return [
            (string) ($replacements['[[applicant_name]]'] ?? ''),
            (string) ($replacements['[[contract_link]]'] ?? ''),
        ];
    }
}

if (!function_exists('mlr_loan_contract_signing_stage_allowed')) {
    function mlr_loan_contract_signing_stage_allowed($status) {
        $stage = function_exists('mlr_get_document_flow_stage') ? mlr_get_document_flow_stage($status) : '';
        return in_array($stage, ['completion_documents', 'pending_documents'], true);
    }
}

if (!function_exists('mlr_loan_contract_acquire_otp_lock')) {
    function mlr_loan_contract_acquire_otp_lock($request_id) {
        $option_name = mlr_loan_contract_otp_lock_name($request_id);
        $token = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('mlr-lc-', true);
        $payload = ['token' => $token, 'created_at' => time()];
        if (add_option($option_name, $payload, '', false)) {
            return $token;
        }

        $current = get_option($option_name, []);
        if (is_array($current) && !empty($current['created_at']) && (time() - absint($current['created_at'])) > 30) {
            delete_option($option_name);
            if (add_option($option_name, $payload, '', false)) {
                return $token;
            }
        }
        return false;
    }
}

if (!function_exists('mlr_loan_contract_release_otp_lock')) {
    function mlr_loan_contract_release_otp_lock($request_id, $token) {
        $option_name = mlr_loan_contract_otp_lock_name($request_id);
        $current = get_option($option_name, []);
        if (is_array($current) && isset($current['token']) && hash_equals((string) $current['token'], (string) $token)) {
            delete_option($option_name);
        }
    }
}

if (!function_exists('mlr_apply_hami_plus_contract_header_84655')) {
    /** Update only the requested top three-party label in existing Hami Plus plan text. */
    function mlr_apply_hami_plus_contract_header_84655() {
        if (get_option('mlr_hami_plus_contract_header_84655_done', false)) return;

        global $wpdb;
        $plans_table = $wpdb->prefix . 'installment_plans';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $plans_table)) !== $plans_table) return;

        $plans = $wpdb->get_results("SELECT id, name, settings FROM `{$plans_table}`");
        if (!is_array($plans)) return;

        $matched = [];
        foreach ($plans as $plan) {
            $normalized = function_exists('mlr_normalize_installment_plan_name_for_contract')
                ? mlr_normalize_installment_plan_name_for_contract($plan->name ?? '')
                : preg_replace('/\s+/u', '', (string) ($plan->name ?? ''));
            if ($normalized !== 'حامیپلاس') continue;

            $settings = function_exists('mlr_get_plan_settings_array')
                ? mlr_get_plan_settings_array($plan)
                : maybe_unserialize($plan->settings ?? '');
            if (!is_array($settings)) $settings = [];
            $html = (string) ($settings['plan_contract_text'] ?? '');
            if ($html === '') continue;

            $updated_html = str_replace(
                ['پلتفرم/فروشنده/ضامن:', 'پلتفرم / فروشنده / ضامن:', 'پلتفرم/ فروشنده/ ضامن:'],
                'پلتفرم:',
                $html
            );
            if ($updated_html !== $html) {
                $settings['plan_contract_text'] = wp_kses_post($updated_html);
                $result = $wpdb->update(
                    $plans_table,
                    ['settings' => serialize($settings)],
                    ['id' => absint($plan->id)],
                    ['%s'],
                    ['%d']
                );
                if ($result === false) continue;
            }
            $matched[] = absint($plan->id);
        }

        // Mark complete only after the target plan exists; otherwise retry on a later admin load.
        if (!empty($matched)) {
            update_option('mlr_hami_plus_contract_header_84655_done', [
                'version' => '8.4.6.56',
                'plan_ids' => array_values(array_unique($matched)),
                'updated_at' => current_time('mysql'),
            ], false);
        }
    }
    add_action('admin_init', 'mlr_apply_hami_plus_contract_header_84655', 26);
}


if (!function_exists('mlr_apply_hami_plus_legal_details_84659')) {
    /**
     * Patch only the explicitly requested legal detail in the existing Hami Plus
     * contract text. Signed snapshots remain immutable and are never rewritten.
     */
    function mlr_apply_hami_plus_legal_details_84659() {
        if (get_option('mlr_hami_plus_legal_details_84659_done', false)) return;

        global $wpdb;
        $plans_table = $wpdb->prefix . 'installment_plans';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $plans_table)) !== $plans_table) return;

        $plans = $wpdb->get_results("SELECT id, name, settings FROM `{$plans_table}`");
        if (!is_array($plans)) return;

        $matched = [];
        foreach ($plans as $plan) {
            $normalized = function_exists('mlr_normalize_installment_plan_name_for_contract')
                ? mlr_normalize_installment_plan_name_for_contract($plan->name ?? '')
                : preg_replace('/\s+/u', '', (string) ($plan->name ?? ''));
            if ($normalized !== 'حامیپلاس') continue;

            $settings = function_exists('mlr_get_plan_settings_array')
                ? mlr_get_plan_settings_array($plan)
                : maybe_unserialize($plan->settings ?? '');
            if (!is_array($settings)) $settings = [];

            $html = (string) ($settings['plan_contract_text'] ?? '');
            if ($html === '') continue;

            $updated_html = str_replace(
                [
                    'مطابق روزنامه رسمی شماره ........... مورخ ..............',
                    'مطابق روزنامه رسمی شماره ............ مورخ ..............',
                    'مطابق روزنامه رسمی شماره ........... مورخ ...........',
                ],
                'مطابق روزنامه رسمی شماره 23644 مورخ 1405/03/11',
                $html
            );

            if ($updated_html !== $html) {
                $settings['plan_contract_text'] = wp_kses_post($updated_html);
                $settings['plan_contract_template_key'] = 'hami-plus-1405-v2';
                $result = $wpdb->update(
                    $plans_table,
                    ['settings' => serialize($settings)],
                    ['id' => absint($plan->id)],
                    ['%s'],
                    ['%d']
                );
                if ($result === false) continue;
            }
            $matched[] = absint($plan->id);
        }

        if (!empty($matched)) {
            update_option('mlr_hami_plus_legal_details_84659_done', [
                'version' => '8.4.6.59',
                'plan_ids' => array_values(array_unique($matched)),
                'updated_at' => current_time('mysql'),
            ], false);
        }
    }
    add_action('admin_init', 'mlr_apply_hami_plus_legal_details_84659', 28);
}

if (!function_exists('mlr_get_loan_contract_review_context')) {
    /**
     * Build a read-only review snapshot for bank/internal users.
     * Signed digital contracts always use the immutable signature snapshot; unsigned
     * contracts use the current canonical renderer. No request data/schema is mutated.
     */
    function mlr_get_loan_contract_review_context($request) {
        if (!$request) {
            return ['available' => false, 'error' => 'اطلاعات پرونده برای قرارداد در دسترس نیست.'];
        }

        $get = static function($source, $key, $default = '') {
            if (is_array($source)) {
                return array_key_exists($key, $source) ? $source[$key] : $default;
            }
            return isset($source->{$key}) ? $source->{$key} : $default;
        };

        $request_id = absint($get($request, 'id', 0));
        $mode = function_exists('mlr_get_loan_contract_mode_for_request')
            ? mlr_get_loan_contract_mode_for_request($request, true)
            : 'physical';
        $is_digital = ($mode === 'digital');
        $proof = ($is_digital && function_exists('mlr_get_loan_contract_signature_for_display'))
            ? mlr_get_loan_contract_signature_for_display($request_id, $request)
            : (($is_digital && function_exists('mlr_get_loan_contract_signature')) ? mlr_get_loan_contract_signature($request_id) : null);
        $signed = is_array($proof);

        $contract_html = '';
        $letterhead_url = '';
        $footer_url = '';
        $repeat_footer_html = '';
        $error = '';

        if ($signed) {
            $contract_html = (string) ($proof['contract_html'] ?? '');
            $letterhead_url = (string) ($proof['letterhead_url'] ?? '');
            $footer_url = (string) ($proof['footer_url'] ?? '');
            $repeat_footer_html = (string) ($proof['repeat_footer_html'] ?? '');
            $proof_plan_name = (string) ($proof['plan_name'] ?? '');
            if (function_exists('mlr_apply_hami_footer_policy')) {
                $footer_policy = mlr_apply_hami_footer_policy(
                    $contract_html,
                    $footer_url,
                    $repeat_footer_html,
                    (string) ($proof['signer_name'] ?? $get($request, 'full_name', '')),
                    $proof_plan_name
                );
                $contract_html = (string) ($footer_policy['contract_html'] ?? $contract_html);
                $repeat_footer_html = (string) ($footer_policy['repeat_footer_html'] ?? '');
            }
        } elseif (function_exists('mlr_get_loan_contract_payload')) {
            $payload = mlr_get_loan_contract_payload($request);
            if (is_wp_error($payload)) {
                $error = $payload->get_error_message();
            } elseif (is_array($payload)) {
                $contract_html = (string) ($payload['contract_html'] ?? '');
                $letterhead_url = (string) ($payload['letterhead_url'] ?? '');
                $footer_url = (string) ($payload['footer_url'] ?? '');
                $repeat_footer_html = (string) ($payload['repeat_footer_html'] ?? '');
            }
        }

        $certificate = ($signed && function_exists('mlr_render_loan_contract_signature_certificate'))
            ? mlr_render_loan_contract_signature_certificate($proof)
            : '';
        $signed_at = ($signed && function_exists('mlr_loan_contract_signed_display_date'))
            ? mlr_loan_contract_signed_display_date($proof)
            : '';
        // External public link is exposed only after signature; unsigned review stays read-only.
        $view_url = ($signed && function_exists('mlr_get_loan_contract_view_url'))
            ? mlr_get_loan_contract_view_url($request_id)
            : '';
        $physical_file_url = esc_url_raw((string) $get($request, 'contract_file', ''));

        return [
            'available' => ($contract_html !== '' || $physical_file_url !== ''),
            'request_id' => $request_id,
            'mode' => $mode,
            'is_digital' => $is_digital,
            'signed' => $signed,
            'signed_at' => $signed_at,
            'contract_html' => $contract_html,
            'letterhead_url' => $letterhead_url,
            'footer_url' => $footer_url,
            'repeat_footer_html' => $repeat_footer_html,
            'signature_certificate' => $certificate,
            'view_url' => $view_url,
            'physical_file_url' => $physical_file_url,
            'error' => $error,
        ];
    }
}

if (!function_exists('mlr_render_loan_contract_review_card')) {
    /** Read-only contract card for bank experts and internal staff. */
    function mlr_render_loan_contract_review_card($request, $args = []) {
        $ctx = mlr_get_loan_contract_review_context($request);
        if (empty($ctx['available']) && empty($ctx['error'])) {
            return '';
        }

        $args = wp_parse_args($args, [
            'title' => 'قرارداد دریافت تسهیلات',
            'context' => 'internal',
        ]);
        $uniq = uniqid('mlr_contract_review_');
        $print_id = $uniq . '_print';
        $is_digital = !empty($ctx['is_digital']);
        $signed = !empty($ctx['signed']);

        if ($is_digital && $signed) {
            $status_title = 'قرارداد دیجیتال امضا و تأیید شده است';
            $status_text = !empty($ctx['signed_at'])
                ? 'تاریخ و ساعت امضا: ' . $ctx['signed_at']
                : 'امضای دیجیتال ثبت شده است.';
            $status_style = 'background:#f0fdf4;border-color:#bbf7d0;color:#166534;';
        } elseif ($is_digital) {
            $status_title = 'قرارداد دیجیتال هنوز امضا نشده است';
            $status_text = 'نسخه فعلی قرارداد برای مطالعه نمایش داده می‌شود و پس از امضا، همین بخش نسخه امضاشده را نشان می‌دهد.';
            $status_style = 'background:#fff7ed;border-color:#fed7aa;color:#9a3412;';
        } else {
            $status_title = 'قرارداد این پرونده فیزیکی است';
            $status_text = !empty($ctx['physical_file_url']) ? 'فایل قرارداد بارگذاری‌شده نیز از همین بخش قابل مشاهده است.' : 'نسخه متنی قرارداد برای مطالعه در دسترس است.';
            $status_style = 'background:#f8fafc;border-color:#cbd5e1;color:#334155;';
        }

        ob_start();
        ?>
        <div class="mlr-glass-card mlr-loan-contract-review-card" data-contract-mode="<?php echo esc_attr($ctx['mode']); ?>" data-contract-signed="<?php echo $signed ? '1' : '0'; ?>">
            <h4 class="mlr-card-title"><span class="dashicons dashicons-media-text"></span> <?php echo esc_html($args['title']); ?></h4>
            <div class="mlr-loan-contract-review-status" style="padding:12px 14px;border:1px solid;border-radius:10px;margin-bottom:14px;<?php echo esc_attr($status_style); ?>">
                <strong style="display:block;margin-bottom:4px;"><?php echo esc_html($status_title); ?></strong>
                <span style="font-size:13px;line-height:1.8;"><?php echo esc_html($status_text); ?></span>
            </div>

            <div class="mlr-loan-contract-review-actions" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                <button type="button" class="mlr-btn mlr-btn-primary" data-mlr-contract-details="<?php echo esc_attr($uniq); ?>">مطالعه قرارداد</button>
                <?php if (!empty($ctx['contract_html'])): ?>
                    <button type="button" class="mlr-btn mlr-btn-secondary" data-mlr-print-target="<?php echo esc_attr($print_id); ?>">چاپ / ذخیره PDF</button>
                <?php endif; ?>
                <?php if ($signed && !empty($ctx['view_url'])): ?>
                    <a href="<?php echo esc_url($ctx['view_url']); ?>" target="_blank" rel="noopener noreferrer" class="mlr-btn mlr-btn-secondary">باز کردن نسخه امضاشده</a>
                <?php endif; ?>
                <?php if (!empty($ctx['physical_file_url'])): ?>
                    <a href="<?php echo esc_url($ctx['physical_file_url']); ?>" target="_blank" rel="noopener noreferrer" class="mlr-btn mlr-btn-secondary">مشاهده فایل قرارداد</a>
                <?php endif; ?>
            </div>

            <details id="<?php echo esc_attr($uniq); ?>" class="mlr-loan-contract-review-details" style="border:1px solid #e2e8f0;border-radius:12px;background:#fff;overflow:hidden;">
                <summary style="cursor:pointer;font-weight:700;padding:12px 14px;background:#f8fafc;">متن کامل قرارداد</summary>
                <div id="<?php echo esc_attr($print_id); ?>" class="mlr-loan-contract-review-print" style="padding:16px;direction:rtl;text-align:right;">
                    <table class="mlr-print-layout-table">
                        <thead>
                            <tr><td>
                                <?php if (!empty($ctx['letterhead_url'])): ?>
                                    <div class="mlr-print-header" style="text-align:center;margin-bottom:12px;"><img src="<?php echo esc_url($ctx['letterhead_url']); ?>" alt="سربرگ قرارداد" class="mlr-contract-media-image" style="max-width:100%;height:auto;"></div>
                                <?php endif; ?>
                            </td></tr>
                        </thead>
                        <tbody>
                            <tr><td>
                                <?php if (!empty($ctx['contract_html'])): ?>
                                    <div class="mlr-contract-body<?php echo $signed ? ' mlr-contract-body-signed' : ''; ?>" style="font-size:13px;line-height:2;">
                                        <?php echo wp_kses_post(mlr_normalize_contract_markup_for_display($ctx['contract_html'])); ?>
                                        <?php if (!empty($ctx['signature_certificate'])): ?>
                                            <?php echo wp_kses_post($ctx['signature_certificate']); ?>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif (!empty($ctx['error'])): ?>
                                    <p style="color:#b91c1c;"><?php echo esc_html($ctx['error']); ?></p>
                                <?php else: ?>
                                    <p>متن قرارداد برای این طرح تنظیم نشده است.</p>
                                <?php endif; ?>
                            </td></tr>
                        </tbody>
                        <tfoot>
                            <tr><td>
                                <div class="mlr-print-footer">
                                    <?php if (!empty($ctx['repeat_footer_html'])): ?>
                                        <?php echo wp_kses_post($ctx['repeat_footer_html']); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($ctx['footer_url'])): ?>
                                        <div style="text-align:center;margin-top:12px;"><img src="<?php echo esc_url($ctx['footer_url']); ?>" alt="پابرگ قرارداد" class="mlr-contract-media-image" style="max-width:100%;height:auto;"></div>
                                    <?php endif; ?>
                                </div>
                            </td></tr>
                        </tfoot>
                    </table>
                </div>
            </details>
        </div>
        <?php
        return trim((string) ob_get_clean());
    }
}

if (!function_exists('mlr_loan_contract_signed_display_date')) {
    function mlr_loan_contract_signed_display_date($proof) {
        if (!is_array($proof) || empty($proof['signed_at'])) {
            return '';
        }
        if (function_exists('mlr_gregorian_to_jalali')) {
            return mlr_gregorian_to_jalali($proof['signed_at'], 'Y/m/d H:i');
        }
        return (string) $proof['signed_at'];
    }
}

if (!function_exists('mlr_normalize_installment_plan_name_for_contract')) {
    function mlr_normalize_installment_plan_name_for_contract($name) {
        $name = trim((string) $name);
        $name = strtr($name, [
            'ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'ۀ' => 'ه', 'ة' => 'ه',
        ]);
        $name = preg_replace('/[\x{200c}\x{200d}\s_\-–—]+/u', '', $name);
        $name = preg_replace('/^طرح/u', '', (string) $name);
        return (string) $name;
    }
}


if (!function_exists('mlr_is_hami_plus_plan_name')) {
    /** Strict plan-family guard: Hami Plus must never inherit Hami-retirees-only contract assets. */
    function mlr_is_hami_plus_plan_name($name) {
        $normalized = function_exists('mlr_normalize_installment_plan_name_for_contract')
            ? mlr_normalize_installment_plan_name_for_contract($name)
            : preg_replace('/[\x{200c}\x{200d}\s_\-–—]+/u', '', (string) $name);
        return strpos((string) $normalized, 'حامیپلاس') !== false;
    }
}

if (!function_exists('mlr_is_hami_retirees_plan_name')) {
    /** Hami retirees family explicitly excludes every Hami Plus name/variant. */
    function mlr_is_hami_retirees_plan_name($name) {
        $normalized = function_exists('mlr_normalize_installment_plan_name_for_contract')
            ? mlr_normalize_installment_plan_name_for_contract($name)
            : preg_replace('/[\x{200c}\x{200d}\s_\-–—]+/u', '', (string) $name);
        if (strpos((string) $normalized, 'حامیپلاس') !== false) return false;
        return in_array($normalized, ['حامیبازنشستگان', 'حامیبازنشسته'], true)
            || (strpos((string) $normalized, 'حامی') !== false && strpos((string) $normalized, 'بازنشست') !== false);
    }
}

if (!function_exists('mlr_apply_hami_plus_digital_contract_84653')) {
    /**
     * One-time, narrowly-scoped configuration requested for the existing Hami Plus plan.
     * It preserves every unrelated setting and never changes the DB schema.
     * Existing requests that already uploaded a physical contract are intentionally left untouched.
     */
    function mlr_apply_hami_plus_digital_contract_84653() {
        if (get_option('mlr_hami_plus_contract_84653_done', false)) {
            return;
        }

        $template = mlr_get_hami_plus_contract_template();
        if ($template === '') {
            if (function_exists('mlr_log')) mlr_log('Hami Plus digital contract migration skipped: template unreadable.');
            return;
        }

        global $wpdb;
        $plans_table = $wpdb->prefix . 'installment_plans';
        $requests_table = $wpdb->prefix . 'loan_requests';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $plans_table)) !== $plans_table) {
            return;
        }

        $plans = $wpdb->get_results("SELECT id, name, settings FROM `{$plans_table}`");
        if (!is_array($plans)) {
            return;
        }

        $exact_plans = [];
        $contains_plans = [];
        foreach ($plans as $plan) {
            $normalized_name = mlr_normalize_installment_plan_name_for_contract($plan->name ?? '');
            if ($normalized_name === 'حامیپلاس') {
                $exact_plans[] = $plan;
            } elseif (strpos($normalized_name, 'حامیپلاس') !== false) {
                $contains_plans[] = $plan;
            }
        }
        // Prefer the exact plan name. If the installation uses a suffix/prefix variant,
        // only auto-select it when there is exactly one unambiguous Hami Plus candidate.
        $target_plans = !empty($exact_plans) ? $exact_plans : (count($contains_plans) === 1 ? $contains_plans : []);
        if (empty($target_plans)) {
            return;
        }

        $matched_plan_ids = [];
        foreach ($target_plans as $plan) {
            $settings = function_exists('mlr_get_plan_settings_array')
                ? mlr_get_plan_settings_array($plan)
                : maybe_unserialize($plan->settings ?? '');
            if (!is_array($settings)) $settings = [];

            // Only the requested contract fields are changed. All financial/document settings remain intact.
            $settings['contract_signing_mode'] = 'digital';
            $settings['plan_contract_text'] = wp_kses_post($template);
            $settings['plan_contract_template_key'] = 'hami-plus-1405-v1';

            $updated = $wpdb->update(
                $plans_table,
                ['settings' => serialize($settings)],
                ['id' => absint($plan->id)],
                ['%s'],
                ['%d']
            );
            if ($updated === false) {
                if (function_exists('mlr_log')) {
                    mlr_log('Hami Plus digital contract migration failed while saving plan.', [
                        'plan_id' => absint($plan->id),
                        'db_error' => $wpdb->last_error,
                    ]);
                }
                continue;
            }

            $plan_id = absint($plan->id);
            $matched_plan_ids[] = $plan_id;

            // If an eligible in-progress request had already frozen the old physical mode but no
            // physical contract was uploaded, move only that request to the newly selected digital mode.
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $requests_table)) === $requests_table) {
                $requests = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, status, contract_file FROM `{$requests_table}` WHERE installment_plan_id = %d",
                    $plan_id
                ));
                if (is_array($requests)) {
                    foreach ($requests as $request) {
                        $request_id = absint($request->id ?? 0);
                        if ($request_id <= 0 || trim((string) ($request->contract_file ?? '')) !== '') continue;
                        // A manually converted per-request physical contract must never be
                        // reverted to digital by a legacy one-time plan migration.
                        if (function_exists('mlr_lc_get_physical_draft') && mlr_lc_get_physical_draft($request_id)) continue;
                        if (function_exists('mlr_is_loan_contract_signed') && mlr_is_loan_contract_signed($request_id)) continue;
                        if (!mlr_loan_contract_signing_stage_allowed($request->status ?? '')) continue;
                        update_option(mlr_loan_contract_mode_option_name($request_id), 'digital', false);
                    }
                }
            }
        }

        if (!empty($matched_plan_ids)) {
            update_option('mlr_hami_plus_contract_84653_done', [
                'version' => '8.4.6.53',
                'plan_ids' => array_values(array_unique(array_map('absint', $matched_plan_ids))),
                'configured_at' => current_time('mysql'),
            ], false);
            if (function_exists('mlr_log')) {
                mlr_log('Hami Plus digital contract configured.', ['plan_ids' => $matched_plan_ids]);
            }
        }
        // If the plan has not been created yet, do not mark the migration complete; a later admin page
        // load will retry without affecting any other plan.
    }
    add_action('admin_init', 'mlr_apply_hami_plus_digital_contract_84653', 24);
}

if (!function_exists('mlr_apply_hami_retirees_final_contract_84668')) {
    /**
     * Install the employer-approved final retirees contract only on the matching Hami retirees plan.
     * Every unrelated financial/document setting is preserved. The previous contract body is backed up
     * to an option before replacement so the migration is reversible without touching historical proofs.
     */
    function mlr_apply_hami_retirees_final_contract_84668() {
        if (get_option('mlr_hami_retirees_contract_84668_done', false)) {
            return;
        }

        $template = mlr_get_hami_retirees_contract_template();
        if ($template === '') {
            if (function_exists('mlr_log')) mlr_log('Hami retirees contract migration skipped: template unreadable.');
            return;
        }

        global $wpdb;
        $plans_table = $wpdb->prefix . 'installment_plans';
        $requests_table = $wpdb->prefix . 'loan_requests';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $plans_table)) !== $plans_table) {
            return;
        }

        $plans = $wpdb->get_results("SELECT id, name, settings FROM `{$plans_table}`");
        if (!is_array($plans)) return;

        $exact = [];
        $candidates = [];
        foreach ($plans as $plan) {
            $normalized = mlr_normalize_installment_plan_name_for_contract($plan->name ?? '');
            if (in_array($normalized, ['حامیبازنشستگان', 'حامیبازنشسته'], true)) {
                $exact[] = $plan;
            } elseif (strpos($normalized, 'حامی') !== false && strpos($normalized, 'بازنشست') !== false) {
                $candidates[] = $plan;
            }
        }
        $targets = !empty($exact) ? $exact : (count($candidates) === 1 ? $candidates : []);
        if (empty($targets)) return;

        $configured = [];
        foreach ($targets as $plan) {
            $settings = function_exists('mlr_get_plan_settings_array')
                ? mlr_get_plan_settings_array($plan)
                : maybe_unserialize($plan->settings ?? '');
            if (!is_array($settings)) $settings = [];

            $old_contract = (string) ($settings['plan_contract_text'] ?? '');
            if ($old_contract !== '' && trim($old_contract) !== trim($template)) {
                $backup_key = 'mlr_hami_retirees_contract_backup_84668_' . absint($plan->id);
                if (get_option($backup_key, null) === null) {
                    add_option($backup_key, [
                        'plan_id' => absint($plan->id),
                        'plan_name' => (string) ($plan->name ?? ''),
                        'contract_text' => $old_contract,
                        'template_key' => (string) ($settings['plan_contract_template_key'] ?? ''),
                        'backed_up_at' => current_time('mysql'),
                    ], '', false);
                }
            }

            $settings['contract_signing_mode'] = 'digital';
            $settings['plan_contract_text'] = wp_kses_post($template);
            $settings['plan_contract_template_key'] = 'hami-retirees-final-1405-v1';
            $settings['contract_first_due_rule'] = 'jalali_month_end_half';
            if (!array_key_exists('contract_marketing_development_amount', $settings)) {
                $settings['contract_marketing_development_amount'] = 0;
            }

            $updated = $wpdb->update(
                $plans_table,
                ['settings' => serialize($settings)],
                ['id' => absint($plan->id)],
                ['%s'],
                ['%d']
            );
            if ($updated === false) {
                if (function_exists('mlr_log')) {
                    mlr_log('Hami retirees contract migration failed while saving plan.', [
                        'plan_id' => absint($plan->id),
                        'db_error' => $wpdb->last_error,
                    ]);
                }
                continue;
            }

            $plan_id = absint($plan->id);
            $configured[] = $plan_id;

            // Match the Hami Plus safety rule: only unsigned, no-file, signing-stage requests can switch mode.
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $requests_table)) === $requests_table) {
                $requests = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, status, contract_file FROM `{$requests_table}` WHERE installment_plan_id = %d",
                    $plan_id
                ));
                if (is_array($requests)) {
                    foreach ($requests as $request) {
                        $request_id = absint($request->id ?? 0);
                        if ($request_id <= 0) continue;
                        if (function_exists('mlr_lc_get_physical_draft') && mlr_lc_get_physical_draft($request_id)) continue;
                        if (function_exists('mlr_is_loan_contract_signed') && mlr_is_loan_contract_signed($request_id)) continue;

                        $mode_option = mlr_loan_contract_mode_option_name($request_id);
                        $frozen_mode = get_option($mode_option, null);
                        $has_physical_file = trim((string) ($request->contract_file ?? '')) !== '';
                        if ($has_physical_file) {
                            // A request that already owns a physical contract file must never be reclassified by this migration.
                            if (!in_array($frozen_mode, ['physical', 'digital'], true)) {
                                add_option($mode_option, 'physical', '', false);
                            }
                            continue;
                        }
                        if (!function_exists('mlr_loan_contract_signing_stage_allowed') || !mlr_loan_contract_signing_stage_allowed($request->status ?? '')) continue;
                        update_option($mode_option, 'digital', false);
                    }
                }
            }
        }

        if (!empty($configured)) {
            update_option('mlr_hami_retirees_contract_84668_done', [
                'version' => '8.4.6.68',
                'plan_ids' => array_values(array_unique(array_map('absint', $configured))),
                'configured_at' => current_time('mysql'),
            ], false);
            if (function_exists('mlr_log')) {
                mlr_log('Hami retirees final contract configured.', ['plan_ids' => $configured]);
            }
        }
    }
    add_action('admin_init', 'mlr_apply_hami_retirees_final_contract_84668', 25);
}


if (!function_exists('mlr_apply_hami_retirees_contract_layout_84674')) {
    /**
     * 8.4.6.74: apply the employer-requested customer-facing Hami retirees revision.
     * - no customer-visible marketing/development amount
     * - stable contract layout/print footer
     * - keep signed historical snapshots untouched
     */
    function mlr_apply_hami_retirees_contract_layout_84674() {
        if (get_option('mlr_hami_retirees_contract_84674_done', false)) {
            return;
        }

        $template = mlr_get_hami_retirees_contract_template();
        if ($template === '') {
            if (function_exists('mlr_log')) mlr_log('Hami retirees 8.4.6.74 migration skipped: template unreadable.');
            return;
        }

        global $wpdb;
        $plans_table = $wpdb->prefix . 'installment_plans';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $plans_table)) !== $plans_table) {
            return;
        }

        $plans = $wpdb->get_results("SELECT id, name, settings FROM `{$plans_table}`");
        if (!is_array($plans)) return;

        $configured = [];
        foreach ($plans as $plan) {
            $normalized = mlr_normalize_installment_plan_name_for_contract($plan->name ?? '');
            $is_target = function_exists('mlr_is_hami_retirees_plan_name')
                ? mlr_is_hami_retirees_plan_name((string) ($plan->name ?? ''))
                : (strpos($normalized, 'حامیپلاس') === false && (
                    in_array($normalized, ['حامیبازنشستگان', 'حامیبازنشسته'], true)
                    || (strpos($normalized, 'حامی') !== false && strpos($normalized, 'بازنشست') !== false)
                ));
            if (!$is_target) continue;

            $settings = function_exists('mlr_get_plan_settings_array')
                ? mlr_get_plan_settings_array($plan)
                : maybe_unserialize($plan->settings ?? '');
            if (!is_array($settings)) $settings = [];

            $old_contract = (string) ($settings['plan_contract_text'] ?? '');
            $backup_key = 'mlr_hami_retirees_contract_backup_84674_' . absint($plan->id);
            if ($old_contract !== '' && get_option($backup_key, null) === null) {
                add_option($backup_key, [
                    'plan_id' => absint($plan->id),
                    'plan_name' => (string) ($plan->name ?? ''),
                    'contract_text' => $old_contract,
                    'template_key' => (string) ($settings['plan_contract_template_key'] ?? ''),
                    'backed_up_at' => current_time('mysql'),
                ], '', false);
            }

            $settings['contract_signing_mode'] = 'digital';
            $settings['plan_contract_text'] = wp_kses_post($template);
            $settings['plan_contract_template_key'] = 'hami-retirees-final-1405-v2';
            $settings['contract_first_due_rule'] = 'jalali_month_end_half';
            unset($settings['contract_marketing_development_amount']);

            $updated = $wpdb->update(
                $plans_table,
                ['settings' => serialize($settings)],
                ['id' => absint($plan->id)],
                ['%s'],
                ['%d']
            );
            if ($updated !== false) {
                $configured[] = absint($plan->id);
            } elseif (function_exists('mlr_log')) {
                mlr_log('Hami retirees 8.4.6.74 migration failed while saving plan.', [
                    'plan_id' => absint($plan->id),
                    'db_error' => $wpdb->last_error,
                ]);
            }
        }

        if (!empty($configured)) {
            update_option('mlr_hami_retirees_contract_84674_done', [
                'version' => '8.4.6.74',
                'plan_ids' => array_values(array_unique(array_map('absint', $configured))),
                'configured_at' => current_time('mysql'),
            ], false);
            if (function_exists('mlr_log')) {
                mlr_log('Hami retirees 8.4.6.74 contract revision configured.', ['plan_ids' => $configured]);
            }
        }
    }
    add_action('admin_init', 'mlr_apply_hami_retirees_contract_layout_84674', 26);
}


if (!function_exists('mlr_repair_hami_plus_contract_isolation_84676')) {
    /**
     * 8.4.6.76: repair the 8.4.6.74 cross-plan regression.
     *
     * 8.4.6.74 used a broad "Hami + retiree" matcher and could therefore overwrite a
     * plan named e.g. "Hami Plus retirees ..." with the Hami-retirees template/key.
     * That template key also activates the Hami-only repeating signature footer at print time.
     * Restore only polluted Hami Plus plans, preferably from the automatic 8.4.6.74 backup.
     * Generic letterhead/footer settings are deliberately preserved exactly as the admin set them.
     */
    function mlr_repair_hami_plus_contract_isolation_84676() {
        if (get_option('mlr_hami_plus_contract_isolation_84676_done', false)) return;

        global $wpdb;
        $plans_table = $wpdb->prefix . 'installment_plans';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $plans_table)) !== $plans_table) return;

        $plans = $wpdb->get_results("SELECT id, name, settings FROM `{$plans_table}`");
        if (!is_array($plans)) return;

        $hami_plus_seen = false;
        $repaired = [];
        foreach ($plans as $plan) {
            if (!function_exists('mlr_is_hami_plus_plan_name') || !mlr_is_hami_plus_plan_name((string) ($plan->name ?? ''))) continue;
            $hami_plus_seen = true;

            $settings = function_exists('mlr_get_plan_settings_array')
                ? mlr_get_plan_settings_array($plan)
                : maybe_unserialize($plan->settings ?? '');
            if (!is_array($settings)) $settings = [];

            $template_key = (string) ($settings['plan_contract_template_key'] ?? '');
            $contract_html = (string) ($settings['plan_contract_text'] ?? '');
            $polluted = in_array($template_key, ['hami-retirees-final-1405-v1', 'hami-retirees-final-1405-v2'], true)
                || strpos($contract_html, 'mlr-hami-retirees-contract') !== false;
            if (!$polluted) continue;

            $backup = get_option('mlr_hami_retirees_contract_backup_84674_' . absint($plan->id), null);
            $restore_html = '';
            $restore_key = '';
            if (is_array($backup)) {
                $backup_html = (string) ($backup['contract_text'] ?? '');
                $backup_key = (string) ($backup['template_key'] ?? '');
                if ($backup_html !== '' && strpos($backup_html, 'mlr-hami-retirees-contract') === false
                    && strpos($backup_key, 'hami-plus-') === 0) {
                    $restore_html = $backup_html;
                    $restore_key = $backup_key;
                }
            }

            if ($restore_html === '' && function_exists('mlr_get_hami_plus_contract_template')) {
                $restore_html = mlr_get_hami_plus_contract_template();
                $restore_key = 'hami-plus-1405-v2';
            }
            if ($restore_html === '') {
                if (function_exists('mlr_log')) mlr_log('Hami Plus 8.4.6.76 repair skipped: no safe Hami Plus template available.', ['plan_id' => absint($plan->id)]);
                continue;
            }

            $settings['contract_signing_mode'] = 'digital';
            $settings['plan_contract_text'] = wp_kses_post($restore_html);
            $settings['plan_contract_template_key'] = $restore_key !== '' ? $restore_key : 'hami-plus-1405-v2';
            // Hami Plus uses the ordinary installment interval for the first due date.
            $settings['contract_first_due_rule'] = 'interval';

            $updated = $wpdb->update(
                $plans_table,
                ['settings' => serialize($settings)],
                ['id' => absint($plan->id)],
                ['%s'],
                ['%d']
            );
            if ($updated !== false) {
                $repaired[] = absint($plan->id);
            } elseif (function_exists('mlr_log')) {
                mlr_log('Hami Plus 8.4.6.76 isolation repair failed.', ['plan_id' => absint($plan->id), 'db_error' => $wpdb->last_error]);
            }
        }

        if ($hami_plus_seen) {
            update_option('mlr_hami_plus_contract_isolation_84676_done', [
                'version' => '8.4.6.76',
                'repaired_plan_ids' => array_values(array_unique(array_map('absint', $repaired))),
                'repaired_at' => current_time('mysql'),
            ], false);
        }
    }
    add_action('admin_init', 'mlr_repair_hami_plus_contract_isolation_84676', 30);
}
