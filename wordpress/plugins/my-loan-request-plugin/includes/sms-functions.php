<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class MLR_MeliPayamak_Provider
 * Handles interactions with MeliPayamak web service.
 */
class MLR_MeliPayamak_Provider {
    private $credentials;
    private $send_wsdl = 'https://api.payamak-panel.com/post/send.asmx?wsdl';

    public function __construct() {
        $settings = get_option('mlr_sms_settings', []);
        $this->credentials = [
            'username' => $settings['username'] ?? '',
            'password' => $settings['password'] ?? '',
        ];
    }

    public function send_pattern($to, $pattern_id, $variables) {
        if (!class_exists('SoapClient')) {
            mlr_log('SOAP Client class does not exist.');
            return new WP_Error('soap_inactive', 'افزونه SOAP در هاست شما فعال نیست.');
        }
        if (empty($this->credentials['username']) || empty($this->credentials['password'])) {
            mlr_log('SMS credentials are not set.');
            return new WP_Error('credentials_missing', 'اطلاعات پنل پیامک تنظیم نشده است.');
        }

        if (!preg_match('/^09\d{9}$/', $to)) {
             return new WP_Error('invalid_mobile', 'فرمت شماره موبایل گیرنده نامعتبر است.');
        }

        try {
            ini_set("soap.wsdl_cache_enabled", "0");
            $wsdl_primary = $this->send_wsdl;
            $wsdl_fallback = preg_replace('/^https:/i', 'http:', $wsdl_primary);

            try {
                $client = new SoapClient($wsdl_primary, [
                    'encoding' => 'UTF-8',
                    'connection_timeout' => 10, // Added timeout
                    'exceptions' => true,
                ]);
            } catch (Exception $e) {
                $client = new SoapClient($wsdl_fallback, [
                    'encoding' => 'UTF-8',
                    'connection_timeout' => 10, // Added timeout
                    'exceptions' => true,
                ]);
            }

            $parameters = [
                'username' => $this->credentials['username'],
                'password' => $this->credentials['password'],
                'to'       => $to,
                'bodyId'   => (int)$pattern_id,
                'text'     => implode(';', $variables),
            ];

            $result = $client->SendByBaseNumber2($parameters)->SendByBaseNumber2Result;

            if (is_numeric($result) && strlen($result) > 10) { 
                return true; 
            } else {
                return new WP_Error('sms_api_error', $this->get_pattern_error_message($result));
            }
        } catch (Exception $e) {
            mlr_log('SMS Exception: ' . $e->getMessage());
            return new WP_Error('soap_fault', 'خطا در اتصال به پنل پیامک: ' . $e->getMessage());
        }
    }

    public function get_credit() {
        if (empty($this->credentials['username'])) return new WP_Error('no_creds', 'تنظیمات پیامک ناقص است.');

        try {
            ini_set("soap.wsdl_cache_enabled", "0");
            $wsdl_primary = $this->send_wsdl;
            $wsdl_fallback = preg_replace('/^https:/i', 'http:', $wsdl_primary);

            try {
                $client = new SoapClient($wsdl_primary, [
                    'encoding' => 'UTF-8',
                    'connection_timeout' => 10, // Added timeout
                    'exceptions' => true,
                ]);
            } catch (Exception $e) {
                $client = new SoapClient($wsdl_fallback, [
                    'encoding' => 'UTF-8',
                    'connection_timeout' => 10, // Added timeout
                    'exceptions' => true,
                ]);
            }

            $response = $client->GetCredit(['username' => $this->credentials['username'], 'password' => $this->credentials['password']]);

            if (isset($response->GetCreditResult)) {
                $result = $response->GetCreditResult;
                if (is_numeric($result) && $result >= 0) {
                    return number_format(floatval($result));
                }
            }
            return new WP_Error('api_error', 'خطا در دریافت اعتبار');

        } catch (Exception $e) {
            mlr_log('Credit Check Exception: ' . $e->getMessage());
            return new WP_Error('connection_error', $e->getMessage());
        }
    }

    public function send_otp_pattern($to, $pattern_id, $variables) {
        return $this->send_pattern($to, $pattern_id, $variables);
    }

    private function get_pattern_error_message($code) {
        $messages = [
            // Shared service / SendByBaseNumber2 return codes. Keep both the
            // historical positive codes used by older responses and the documented
            // negative codes returned by the shared service endpoint.
            '-7' => 'خطا در خط فرستنده سرویس پیامک؛ با پشتیبانی پنل پیامک تماس بگیرید.',
            '-6' => 'خطای داخلی سرویس پیامک؛ لطفاً دوباره تلاش کنید.',
            '-5' => 'متغیرهای ارسال‌شده با قالب (Body ID) همخوانی ندارند.',
            '-4' => 'شناسه قالب (Body ID) صحیح نیست یا هنوز تأیید نشده است.',
            '-3' => 'خط فرستنده در سامانه پیامک تعریف نشده است.',
            '-2' => 'تعداد شماره‌های گیرنده برای این وب‌سرویس نامعتبر است.',
            '-1' => 'دسترسی این حساب به وب‌سرویس خدماتی غیرفعال است.',
            '0' => 'نام کاربری یا رمز عبور اشتباه است.',
            '1' => 'اعتبار کافی نیست.',
            '2' => 'اعتبار کافی نیست.',
            '4' => 'شناسه قالب (Body ID) اشتباه است.',
            '5' => 'تعداد متغیرها صحیح نیست.',
            '6' => 'سامانه در حال بروزرسانی است.',
            '7' => 'متن حاوی کلمات فیلتر شده است.',
            '10' => 'کاربر غیرفعال است.',
            '11' => 'ارسال نشده.',
            '12' => 'مدارک کاربر کامل نمی‌باشد.',
        ];
        return $messages[strval($code)] ?? 'خطای نامشخص (کد: ' . esc_html($code) . ')';
    }
}

// ===================================================================
// Cooperation Requests SMS Functions
// ===================================================================

function mlr_get_cooperation_statuses() {
    return ['در حال بررسی', 'نقص مدارک', 'تکمیل مدارک', 'تایید مدارک', 'عقد قرارداد', 'انصراف', 'رد شده'];
}

function mlr_get_cooperation_placeholders() {
    return [
        '[[request_id]]' => 'شناسه درخواست',
        '[[status]]' => 'وضعیت جدید',
        '[[store_name]]' => 'نام فروشگاه (برای حقیقی)',
        '[[store_owner_name]]' => 'نام مالک فروشگاه (برای حقیقی)',
        '[[company_name]]' => 'نام شرکت (برای حقوقی)',
        '[[contact_person]]' => 'نام رابط (برای حقوقی)',
        '[[applicant_name]]' => 'نام متقاضی (مالک/رابط)',
        '[[contract_link]]' => 'توکن (کد) قرارداد آنلاین',
        '[[otp_code]]' => 'کد تایید امضا (فقط برای الگوی OTP)',
    ];
}

function mlr_replace_cooperation_placeholders($string, $request, $otp_code = null) {
    $request_data = json_decode($request->data, true);
    if (!is_array($request_data)) $request_data = [];

    $applicant_name = ($request->request_type === 'individual')
        ? ($request_data['store_owner_name'] ?? '')
        : ($request_data['contact_person'] ?? '');

    $contract_token_only = $request->contract_token ?? '';

    $replacements = [
        '[[request_id]]'       => $request->id ?? 'N/A',
        '[[status]]'           => $request->status ?? 'N/A',
        '[[store_name]]'       => $request_data['store_name'] ?? '',
        '[[store_owner_name]]' => $request_data['store_owner_name'] ?? '',
        '[[company_name]]'     => $request_data['company_name'] ?? '',
        '[[contact_person]]'   => $request_data['contact_person'] ?? '',
        '[[applicant_name]]'   => $applicant_name,
        '[[contract_link]]'    => $contract_token_only,
        '[[otp_code]]'         => $otp_code ?? '',
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $string);
}

function mlr_send_sms_on_cooperation_status_change($request_id, $new_status, $request_object = null) {
    global $wpdb;

    if (is_null($request_object)) {
        $request_object = $wpdb->get_row($wpdb->prepare("SELECT id, request_type, status, data, contract_token FROM {$wpdb->prefix}cooperation_requests WHERE id = %d", $request_id));
    }

    if (!$request_object) {
        mlr_log("SMS Error: Cooperation request object not found for ID {$request_id}");
        return;
    }

    $request_object->status = $new_status;

    $sms_settings = get_option('mlr_sms_settings', []);
    $status_settings = $sms_settings['cooperation_statuses'][$new_status] ?? [];

    if (empty($status_settings['enabled']) || empty($status_settings['bodyId'])) {
        return;
    }

    $request_data = json_decode($request_object->data, true);
    if (!is_array($request_data)) $request_data = [];

    $raw = $request_data['mobile_number'] ?? $request_data['mobile'] ?? $request_data['phone_number'] ?? $request_data['phone'] ?? null;
    $mobile_number = mlr_normalize_mobile_for_cooperation($raw);

    if (!$mobile_number) {
        mlr_log("SMS Error: Invalid or missing mobile number for cooperation request ID {$request_id}.");
        return;
    }

    $text_variables = [];
    for ($i = 0; $i < 3; $i++) {
        $var_key = "var_{$i}";
        if (!empty($status_settings[$var_key])) {
            $replaced_text = mlr_replace_cooperation_placeholders($status_settings[$var_key], $request_object, null);
            $text_variables[] = $replaced_text;
        }
    }

    if (empty($text_variables)) {
        return;
    }

    $sms_provider = new MLR_MeliPayamak_Provider();
    $result = $sms_provider->send_pattern($mobile_number, $status_settings['bodyId'], $text_variables);

    if (is_wp_error($result)) {
        mlr_log('MLR Cooperation SMS Error for Request ID ' . $request_id . ': ' . $result->get_error_message());
    } else {
        mlr_log('MLR Cooperation SMS Sent Successfully for Request ID ' . $request_id);
    }
}

// ===================================================================
// Loan Requests SMS Functions
// ===================================================================

function mlr_get_loan_statuses() {
    return ['در حال بررسی', 'نقص مدارک', 'تکمیل مدارک', 'تأیید مدارک', 'اعطا شده', 'رد شده'];
}

function mlr_get_loan_placeholders() {
    return [
        '[[request_id]]' => 'شناسه درخواست',
        '[[status]]' => 'وضعیت جدید',
        '[[full_name]]' => 'نام کامل متقاضی',
        '[[national_code]]' => 'کد ملی متقاضی',
        '[[mobile_number]]' => 'شماره موبایل متقاضی',
        '[[loan_amount]]' => 'مبلغ تسهیلات (ریال)',
        '[[installment_count]]' => 'تعداد اقساط',
        '[[installment_amount]]' => 'مبلغ هر قسط (ریال)',
        '[[plan_name]]' => 'نام طرح تسهیلاتی',
        '[[acceptor_name]]' => 'نام پذیرنده (ثبت کننده درخواست)',
    ];
}

function mlr_replace_loan_placeholders($string, $request, $acceptor_user = null) {
    global $wpdb;
    $plan_name = '';
    if (!empty($request->installment_plan_id)) {
        $plan_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}installment_plans WHERE id = %d",
            $request->installment_plan_id
        ));
    }

    $acceptor_name = '---';
    if ($acceptor_user instanceof WP_User) {
        $acceptor_name = $acceptor_user->display_name;
    } elseif (function_exists('mlr_is_applicant_origin_request') && mlr_is_applicant_origin_request($request) && (!function_exists('mlr_get_request_acceptor_user_id') || !mlr_get_request_acceptor_user_id($request))) {
        $acceptor_name = 'درخواست مستقیم متقاضی';
    } elseif (!empty($request->user_id)) {
        $acceptor_id = function_exists('mlr_get_request_acceptor_user_id') ? mlr_get_request_acceptor_user_id($request) : (int) $request->user_id;
        $acceptor_info = $acceptor_id ? get_userdata($acceptor_id) : null;
        if ($acceptor_info) {
            $acceptor_name = $acceptor_info->display_name;
        }
    }

    $replacements = [
        '[[request_id]]'         => $request->id ?? 'N/A',
        '[[status]]'             => $request->status ?? 'N/A',
        '[[full_name]]'          => $request->full_name ?? '',
        '[[national_code]]'      => $request->national_code ?? '',
        '[[mobile_number]]'      => $request->mobile_number ?? '',
        '[[loan_amount]]'        => isset($request->loan_amount) ? number_format(floatval($request->loan_amount)) : '0',
        '[[installment_count]]'  => $request->installment_count ?? '0',
        '[[installment_amount]]' => isset($request->installment_amount) ? number_format(floatval($request->installment_amount)) : '0',
        '[[plan_name]]'          => $plan_name ?: '---',
        '[[acceptor_name]]'      => $acceptor_name,
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $string);
}

function mlr_send_sms_on_loan_status_change($request_id, $new_status) {
    global $wpdb;
    $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}loan_requests WHERE id = %d", $request_id));

    if (!$request) {
        return;
    }

    $request->status = $new_status;

    $sms_settings = get_option('mlr_sms_settings', []);
    $status_settings = $sms_settings['loan_statuses'][$new_status] ?? [];

    if (empty($status_settings['enabled']) || empty($status_settings['bodyId'])) {
        return;
    }

    $send_to_applicant = isset($status_settings['send_to_applicant']) ? (bool)$status_settings['send_to_applicant'] : true;
    $send_to_acceptor = isset($status_settings['send_to_acceptor']) ? (bool)$status_settings['send_to_acceptor'] : false;

    if (!$send_to_applicant && !$send_to_acceptor) {
        return;
    }

    $applicant_mobile = $request->mobile_number ?? null;
    $acceptor_mobile = null;
    $acceptor_user = null;

    $acceptor_user_id = function_exists('mlr_get_request_acceptor_user_id') ? mlr_get_request_acceptor_user_id($request) : (int) ($request->user_id ?? 0);

    if ($send_to_acceptor && $acceptor_user_id > 0) {
        $acceptor_user = get_userdata($acceptor_user_id);
        if ($acceptor_user) {
            $acceptor_mobile = get_user_meta($acceptor_user_id, 'acceptor_mobile_number', true);
            if (empty($acceptor_mobile)) {
                 $acceptor_mobile = get_user_meta($acceptor_user_id, 'billing_phone', true);
            }

            if ($acceptor_mobile) {
                 $acceptor_mobile = preg_replace('/[^0-9]/', '', $acceptor_mobile);
                 $acceptor_mobile = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], range(0,9), $acceptor_mobile);

                 if (!preg_match('/^09\d{9}$/', $acceptor_mobile)) {
                      $acceptor_mobile = null;
                 }
            }
        }
    }

    if ($send_to_applicant) {
        $applicant_mobile = preg_replace('/[^0-9]/', '', $applicant_mobile);
        $applicant_mobile = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], range(0,9), $applicant_mobile);
        if (!preg_match('/^09\d{9}$/', $applicant_mobile)) {
            $applicant_mobile = null;
        }
    } else {
         $applicant_mobile = null;
    }

    if (!$applicant_mobile && !$acceptor_mobile) {
        return;
    }

    $text_variables = [];
    for ($i = 0; $i < 3; $i++) {
        $var_key = "var_{$i}";
        if (isset($status_settings[$var_key])) {
            $replaced_text = mlr_replace_loan_placeholders($status_settings[$var_key], $request, $acceptor_user);
            $text_variables[] = $replaced_text;
        }
    }

    $sms_provider = new MLR_MeliPayamak_Provider();
    $pattern_id = $status_settings['bodyId'];

    if ($send_to_applicant && $applicant_mobile) {
        $result_applicant = $sms_provider->send_pattern($applicant_mobile, $pattern_id, $text_variables);
        if (is_wp_error($result_applicant)) {
            mlr_log('MLR Loan SMS Error (Applicant): ' . $result_applicant->get_error_message());
        }
    }

    if ($send_to_acceptor && $acceptor_mobile) {
        if ($acceptor_mobile !== $applicant_mobile || !$send_to_applicant || !$applicant_mobile) {
            $result_acceptor = $sms_provider->send_pattern($acceptor_mobile, $pattern_id, $text_variables);
            if (is_wp_error($result_acceptor)) {
                mlr_log('MLR Loan SMS Error (Acceptor): ' . $result_acceptor->get_error_message());
            }
        }
    }
}
?>
