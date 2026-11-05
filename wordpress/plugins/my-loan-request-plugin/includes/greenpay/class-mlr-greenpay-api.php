<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * GreenPay/BKI API client used by the Babanoell loan workflow.
 *
 * - Uses the WordPress HTTP API with TLS verification enabled.
 * - Does not persist tokens/OTP values.
 * - Receives the effective ToPocketNumber per transaction so queued financial
 *   snapshots are not silently replaced by later global-setting changes.
 */
class MLR_GreenPay_API {
    private $settings = [];
    private $environment = 'test';
    private $auth_base_url = '';
    private $purchase_base_url = '';
    private $username = '';
    private $password = '';
    private $api_key = '';
    private $terminal_number = '';
    private $merchant_id = '';
    private $timeout = 25;

    public function __construct($settings = null) {
        if (!is_array($settings)) {
            $settings = function_exists('mlr_greenpay_get_settings') ? mlr_greenpay_get_settings() : [];
        }
        if (function_exists('mlr_greenpay_apply_test_profile_1023')) {
            $settings = mlr_greenpay_apply_test_profile_1023($settings);
        }

        $this->settings = $settings;
        $this->environment = (($settings['environment'] ?? 'test') === 'live') ? 'live' : 'test';
        $this->auth_base_url = rtrim((string) ($settings['auth_base_url'] ?? ''), '/');
        $this->purchase_base_url = rtrim((string) ($settings['purchase_base_url'] ?? ''), '/');
        $this->username = trim((string) ($settings['username'] ?? ''));
        $this->password = (string) ($settings['password'] ?? '');
        $this->api_key = trim((string) ($settings['api_key'] ?? ''));
        $this->terminal_number = trim((string) ($settings['terminal_number'] ?? ''));
        $this->merchant_id = trim((string) ($settings['merchant_id'] ?? ''));
        $this->timeout = max(5, min(60, absint($settings['request_timeout'] ?? 25)));

        // Defense in depth for custom sandbox profiles: supplied profile 1023 already forces password=1.
        if ($this->environment === 'test' && $this->password === '') {
            $this->password = '1';
        }
    }

    public function validate_configuration() {
        $missing = [];
        foreach ([
            'username' => $this->username,
            'password' => $this->password,
            'api_key' => $this->api_key,
            'terminal_number' => $this->terminal_number,
            'merchant_id' => $this->merchant_id,
            'auth_base_url' => $this->auth_base_url,
            'purchase_base_url' => $this->purchase_base_url,
        ] as $key => $value) {
            if ($value === '') {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            return new WP_Error('greenpay_config_missing', 'تنظیمات GreenPay ناقص است: ' . implode(', ', $missing));
        }

        foreach ([$this->auth_base_url, $this->purchase_base_url] as $url) {
            if (!wp_http_validate_url($url)) {
                return new WP_Error('greenpay_invalid_url', 'آدرس API گرین‌پی معتبر نیست.');
            }
            if ($this->environment === 'live' && strtolower((string) wp_parse_url($url, PHP_URL_SCHEME)) !== 'https') {
                return new WP_Error('greenpay_https_required', 'در محیط عملیاتی GreenPay استفاده از HTTPS الزامی است.');
            }
        }

        return true;
    }

    public function get_token() {
        $valid = $this->validate_configuration();
        if (is_wp_error($valid)) {
            return $valid;
        }

        $response = $this->post_json(
            $this->auth_base_url . '/submit',
            [
                'Username' => $this->username,
                'Password' => $this->password,
                'ApiKey'   => $this->api_key,
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        if ((int) ($response['http_code'] ?? 0) !== 200) {
            $http_code = (int) ($response['http_code'] ?? 0);
            $fallback = 'خطا در دریافت توکن GreenPay';
            if ($http_code === 401 || $http_code === 403) {
                $fallback = 'احراز هویت GreenPay رد شد؛ Username، Password و API Key را بررسی کنید';
            } elseif ($http_code === 422) {
                $fallback = 'GreenPay اطلاعات احراز هویت را از نظر اعتبار/ترکیب فیلدها رد کرد';
            } elseif ($http_code === 404) {
                $fallback = 'Endpoint دریافت توکن GreenPay پیدا نشد؛ آدرس Auth API نادرست است';
            } elseif ($http_code === 405) {
                $fallback = 'متد درخواست Token توسط Endpoint GreenPay پذیرفته نشد';
            } elseif ($http_code >= 500) {
                $fallback = 'سرور GreenPay هنگام دریافت Token خطای داخلی برگرداند';
            }
            if ($http_code > 0) {
                $fallback .= ' (HTTP ' . $http_code . ')';
            }
            return new WP_Error(
                'greenpay_token_http_error',
                $this->extract_error_message($response, $fallback),
                $response
            );
        }

        $token = trim((string) ($response['Token'] ?? $response['token'] ?? ''));
        if ($token === '') {
            $content_type = trim((string) ($response['content_type'] ?? ''));
            $fallback = 'GreenPay پاسخ HTTP موفق داد اما فیلد Token در پاسخ وجود نداشت';
            if ($content_type !== '') {
                $fallback .= ' (Content-Type: ' . sanitize_text_field($content_type) . ')';
            }
            return new WP_Error(
                'greenpay_token_missing',
                $this->extract_error_message($response, $fallback),
                $response
            );
        }

        return $token;
    }

    public function send_otp($token, $pocket_number, $amount) {
        // Supplied sandbox profile 1023 explicitly does not send OTP SMS.
        if ($this->environment === 'test' && !empty($this->settings['test_profile_1023'])) {
            return [
                'http_code' => 200,
                'Status' => 'success',
                'Success' => true,
                'OtpBypassed' => true,
            ];
        }
        return $this->post_json(
            $this->purchase_base_url . '/sendPurchaseOTP',
            [
                'PocketNumber'   => (string) $pocket_number,
                'Amount'         => (int) $amount,
                'ApiKey'         => $this->api_key,
                'TerminalNumber' => $this->terminal_number,
                'MerchantID'     => (int) $this->merchant_id,
            ],
            $token
        );
    }

    public function confirm_debit($token, $from_pocket_number, $to_pocket_number, $otp, $amount, $ref_number, $send_time) {
        // Defense in depth: even a stale/legacy queued test row cannot override the
        // supplied online-purchase-1023 sandbox contract.
        if ($this->environment === 'test' && !empty($this->settings['test_profile_1023'])) {
            $from_pocket_number = '1262010000002551';
            $to_pocket_number = '';
            $otp = '123456';
        }
        return $this->post_json(
            $this->purchase_base_url . '/transferByPocket',
            [
                'ApiKey'           => $this->api_key,
                'FromPocketNumber' => (string) $from_pocket_number,
                'ToPocketNumber'   => (string) $to_pocket_number,
                // Babanoell stores the facility amount in Rial; never multiply by 10.
                'Amount'           => (string) ((int) $amount),
                'TerminalNumber'   => $this->terminal_number,
                'OTP'              => (string) $otp,
                'SendTime'         => (string) $send_time,
                'RefNumber'        => (string) $ref_number,
            ],
            $token
        );
    }

    public function is_ready($token, $value = '') {
        // Match the reference GreenPay plugin contract exactly. Its isReady
        // health check uses the literal value `test123`; some deployments
        // reject arbitrary route values even though the endpoint looks generic.
        $value = $value !== '' ? $value : 'test123';
        $url = $this->auth_base_url . '/isReady/' . rawurlencode($value);
        $response = $this->request('GET', $url, null, $token);
        if (is_wp_error($response)) {
            return $response;
        }
        $http_code = (int) ($response['http_code'] ?? 0);
        if ($http_code < 200 || $http_code >= 300) {
            return new WP_Error('greenpay_readiness_http_error', 'سرویس isReady گرین‌پی پاسخ موفق HTTP نداد (HTTP ' . $http_code . ').', $response);
        }
        if (!isset($response['Value'])) {
            return new WP_Error('greenpay_readiness_invalid', 'پاسخ سرویس isReady گرین‌پی قابل تأیید نیست.', $response);
        }
        return (string) $response['Value'] === (string) $value;
    }

    private function post_json($url, array $payload, $token = null) {
        return $this->request('POST', $url, $payload, $token);
    }

    private function request($method, $url, $payload = null, $token = null) {
        if (!wp_http_validate_url($url)) {
            return new WP_Error('greenpay_invalid_url', 'آدرس API گرین‌پی معتبر نیست.');
        }

        if ($this->environment === 'live' && strtolower((string) wp_parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            return new WP_Error('greenpay_https_required', 'در محیط عملیاتی GreenPay استفاده از HTTPS الزامی است.');
        }

        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json; charset=utf-8',
        ];
        if (is_string($token) && $token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $args = [
            'method'      => strtoupper($method),
            'timeout'     => $this->timeout,
            'redirection' => 2,
            'headers'     => $headers,
            'sslverify'   => true,
            'user-agent'  => 'Babanoell-MLR/' . (defined('MLR_VERSION') ? MLR_VERSION : 'unknown') . '; ' . home_url('/'),
        ];
        if ($payload !== null) {
            $args['body'] = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $raw = wp_remote_request($url, $args);
        if (is_wp_error($raw)) {
            $data = [
                'transport_error' => true,
                'error_code'      => $raw->get_error_code(),
                'error_message'   => $raw->get_error_message(),
                'url_host'        => (string) wp_parse_url($url, PHP_URL_HOST),
            ];
            return new WP_Error('greenpay_transport_error', 'خطا در ارتباط با GreenPay: ' . $raw->get_error_message(), $data);
        }

        $http_code = (int) wp_remote_retrieve_response_code($raw);
        $body = trim((string) wp_remote_retrieve_body($raw));
        $content_type = (string) wp_remote_retrieve_header($raw, 'content-type');
        // RFC 7231 Date is generated by the remote HTTP server and gives us an
        // authoritative clock source when the hosting server clock is skewed.
        $provider_date_header = trim((string) wp_remote_retrieve_header($raw, 'date'));

        if ($body === '') {
            return [
                'http_code'            => $http_code,
                'content_type'         => $content_type,
                'provider_date_header' => $provider_date_header,
                'raw_response'         => '',
            ];
        }

        $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return [
                'http_code'            => $http_code,
                'content_type'         => $content_type,
                'provider_date_header' => $provider_date_header,
                'raw_response'         => substr($body, 0, 2000),
                'json_error'           => json_last_error_msg(),
            ];
        }

        $decoded['http_code'] = $http_code;
        if ($provider_date_header !== '') {
            $decoded['provider_date_header'] = $provider_date_header;
        }
        return $decoded;
    }

    private function extract_error_message(array $response, $fallback) {
        // Provider responses are not fully consistent. In particular, HTTP 422 from
        // ASP.NET-style APIs commonly returns lowercase `errors` as a field=>messages map.
        foreach (['Errors', 'errors'] as $errors_key) {
            if (empty($response[$errors_key]) || !is_array($response[$errors_key])) {
                continue;
            }
            $messages = [];
            foreach ($response[$errors_key] as $field => $value) {
                $field = is_string($field) ? sanitize_key($field) : '';
                $values = is_array($value) ? $value : [$value];
                foreach ($values as $item) {
                    if (is_array($item)) {
                        foreach (['ErrorMessage', 'Message', 'message', 'error', 'detail'] as $key) {
                            if (!empty($item[$key]) && is_scalar($item[$key])) {
                                $msg = sanitize_text_field((string) $item[$key]);
                                if ($msg !== '') {
                                    $messages[] = ($field !== '' ? $field . ': ' : '') . $msg;
                                }
                                break;
                            }
                        }
                    } elseif (is_scalar($item)) {
                        $msg = sanitize_text_field((string) $item);
                        if ($msg !== '') {
                            $messages[] = ($field !== '' ? $field . ': ' : '') . $msg;
                        }
                    }
                }
            }
            if (!empty($messages)) {
                return implode(' | ', array_slice(array_unique($messages), 0, 6));
            }
        }
        foreach (['Message', 'message', 'ErrorMessage', 'error', 'detail', 'title'] as $key) {
            if (!empty($response[$key]) && is_scalar($response[$key])) {
                return sanitize_text_field((string) $response[$key]);
            }
        }
        if (!empty($response['raw_response']) && is_scalar($response['raw_response'])) {
            $raw = sanitize_text_field((string) $response['raw_response']);
            if ($raw !== '') {
                // Never expose credentials/pockets from a raw provider response.
                if (function_exists('mlr_greenpay_redact_string')) {
                    $raw = mlr_greenpay_redact_string($raw);
                }
                return mb_substr($raw, 0, 700);
            }
        }
        return $fallback;
    }
}
