<?php
namespace mihanpanel\pro\app\implemented;

use \mihanpanel\pro\app\contracts\sms_provider_interface;
use mihanpanel\pro\app\tools;

class smsir_new implements sms_provider_interface
{
    static function get_base_url()
    {
        return 'https://api.sms.ir/v1/';
    }
    static function get_credit()
    {
        $apiKey = self::get_api_key();
        if(!$apiKey)
        {
            return false;
        }
        $url = self::get_base_url() . 'credit';
        $args = [
            'headers' =>[
                'Content-Type' => 'application/json',
                'X-API-KEY' => $apiKey,
            ]
        ];
        $remote = wp_remote_get($url, $args);
        $response = json_decode(wp_remote_retrieve_body($remote));
        return isset($response->status) && $response->status == 1 && isset($response->data) ? $response->data : false;
    }
    static function get_lines()
    {
        $api = self::get_api_key();
        if(!$api)
        {
            return false;
        }
        $url = self::get_base_url() . 'line';
        $args = [
            'headers' =>[
                'Content-Type' => 'application/json',
                'X-API-KEY' => $api,
            ],
        ];
        $remote = wp_remote_get($url, $args);
        $response = json_decode(wp_remote_retrieve_body($remote));
        return isset($response->status) && $response->status == 1 && isset($response->data) ? $response->data : false;
    }
    static function send($to, $msg)
    {
        $pattern_id = self::get_pattern_id();
        return $pattern_id ? self::send_with_pattern_mode($to, $msg, $pattern_id) : self::simple_send($to, $msg);
    }
    static function send_with_pattern_mode($to, $msg, $pattern_id)
    {
        
        $api = self::get_api_key();
        $patternID = self::get_pattern_id();
        if(!$api | !$patternID)
        {
            return false;
        }
        $msgs = explode("\r\n", $msg);
        $parameter = [];
        foreach($msgs as $item)
        {
            list($key, $value) = explode('=', $item);
            $parameter[] = [
                'name' => $key,
                'value' => $value,
            ];
        }
        $url = self::get_base_url() . 'send/verify';
        $to = tools::getValidatedPhoneNumber($to);
        $args = [
            'headers' =>[
                'Content-Type' => 'application/json',
                'X-API-KEY' => $api,
            ],
            "body" => json_encode([
                "Parameters" => $parameter,
                "Mobile" => $to,
                "TemplateId" => $pattern_id
            ])
        ];
        $remote = wp_remote_post($url, $args);
        return json_decode(wp_remote_retrieve_body($remote));
    }
    static function simple_send($to, $msg)
    {
        $api = self::get_api_key();
        $username = self::get_username();
        if(!$api || !$username)
        {
            return false;
        }
        $gateway = self::get_gateway_number();
        $to = tools::getValidatedPhoneNumber($to);
        $msg = sanitize_textarea_field($msg);
        $url = self::get_base_url() . 'send';
        $args = [
            'headers' =>[
                'Content-Type' => 'application/json',
            ],
            "body" => json_encode([
                'username' => $username,
                'password' => $api,
                "line" => $gateway,
                "mobile" => $to,
                "message" => $msg,
            ])
        ];
        $remote = wp_remote_get($url, $args);
        return json_decode(wp_remote_retrieve_body($remote));
    }
    
    static function get_username()
    {
        return get_option('mw_smsir_new_username');
    }
    static function get_api_key()
    {
        return get_option('mw_smsir_new_api_key');
    }
    static function get_gateway_number()
    {
        return get_option('mw_smsir_gateway_number');
    }
    static function get_pattern_id()
    {
        return get_option('mw_smsir_pattern_id');
    }
    static function render_settings()
    {

        $username = self::get_username();
        $api_key = self::get_api_key();
        $gateway_number = self::get_gateway_number();
        $lines = self::get_lines();
        $credit = self::get_credit();
        $pattern_id = self::get_pattern_id();
        ?>
        <div class="mp_option_section">
            <h3 class="mp_option_section_title"><?php _e('Sms.ir settings', 'mihanpanel')?></h3>
            <div class="mp_option_field mp_row_field">
                <span><?php printf("%s: ", _e("Account Credit", 'mihanpanel')) ?></span>
                <span><?php echo $credit ? esc_html($credit) : __("Not connected to sms provider", 'mihanpanel'); ?></span>
            </div>
            <div class="mp_option_field mp_row_field">
                <label for="mw_smsir_new_username"><?php _e("Username", "mihanpanel"); ?></label>
                <input dir="auto" value="<?php echo esc_attr($username); ?>" type="text" name="mw_smsir_new_username" id="mw_smsir_new_username">
            </div>
            <div class="mp_option_field mp_row_field">
                <label for="mw_smsir_new_api_key"><?php _e("API Key", "mihanpanel"); ?></label>
                <input dir="auto" type="text" value="<?php echo esc_attr($api_key); ?>" name="mw_smsir_new_api_key" id="mw_smsir_new_api_key">
            </div>
            <?php if($lines): ?>
                <div class="mp_option_field mp_row_field">
                    <label><?php _e("Choose gateway number", "mihanpanel"); ?></label>
                    <select name="mw_smsir_gateway_number" id="mw_smsir_gateway_number">
                        <option value="0" selected disabled><?php _e("Choose gateway number", "mihanpanel"); ?></option>
                        <?php foreach($lines as $line): ?>
                            <option <?php selected($line, $gateway_number); ?> value="<?php echo esc_attr($line); ?>"><?php echo esc_html($line); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="mp_option_field mp_row_field">
                <label for="mw_smsir_pattern_id"><?php _e("Template ID", "mihanpanel"); ?></label>
                <input dir="auto" type="text" value="<?php echo esc_attr($pattern_id); ?>" name="mw_smsir_pattern_id" id="mw_smsir_pattern_id">
            </div>
            <div class="mp_description_box">
                <p class="mp_option_section_description"><?php _e("You can enter template id if this feature is enable in your sms provider account.", "mihanpanel"); ?></p>
                <p class="mp_option_section_description"><?php _e("If Use this feature, sms content field value must be like this (Put each item in new line):", "mihanpanel"); ?></p>
                <p class="mp_option_section_description"><?php printf("%s", "username=[[user_login]]"); ?></p>
                <p class="mp_option_section_description"><?php printf("%s", "verification_code=[[code]]"); ?></p>
                <p class="mp_option_section_description"><?php _e('Instead of "username" and "verification_code" user your own field_name that defined in your sms provider account.', 'mihanpanel'); ?></p>
            </div>
        </div>
        <?php
    }
    static function get_provider_settings()
    {
        return [
            'mw_smsir_new_username',
            'mw_smsir_new_api_key',
            'mw_smsir_pattern_id',
            'mw_smsir_gateway_number',
        ];
    }
    static function validate_send_message($response)
    {
        if(!isset($response->status))
        {
            return false;
        }
        $res['status'] = $response->status == 1 && isset($response->data) && isset($response->data->messageId) ? 200 : 400;
        $res['msg'] = isset($response->message) ? $response->message : false;
        return $res;
    }
}