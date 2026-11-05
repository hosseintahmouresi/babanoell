<?php
namespace mihanpanel\pro\app\implemented;

use \mihanpanel\pro\app\contracts\sms_provider_interface;
use mihanpanel\pro\app\tools;

class smsir implements sms_provider_interface
{
    static function get_base_url()
    {
        return 'http://RestfulSms.com/api/';
    }
    static function get_token()
    {
        $api_key = self::get_api_key();
        $secret_key = self::get_secret_key();
        if(!$api_key || !$secret_key)
        {
            return false;
        }
        $url = self::get_base_url() . 'Token';
        $args = [
            "body" => [
                "UserApiKey" => $api_key,
                "SecretKey" => $secret_key
            ]
        ];
        $remote = wp_remote_post($url, $args);
        $response = json_decode(wp_remote_retrieve_body($remote));
        return isset($response->IsSuccessful) && $response->IsSuccessful && isset($response->TokenKey) ? $response->TokenKey : false;
    }
    static function get_credit()
    {
        $token = self::get_token();
        if(!$token)
        {
            return false;
        }
        $url = self::get_base_url() . 'credit';
        $args = [
            'headers' =>[
                'Content-Type' => 'application/json',
                'x-sms-ir-secure-token' => $token
            ]
        ];
        $remote = wp_remote_get($url, $args);
        $response = json_decode(wp_remote_retrieve_body($remote));
        return isset($response->IsSuccessful) && $response->IsSuccessful && isset($response->Credit) ? $response->Credit : false;
    }
    static function get_lines()
    {
        $token = self::get_token();
        if(!$token)
        {
            return false;
        }
        $url = self::get_base_url() . 'SMSLine';
        $args = [
            'headers' =>[
                'Content-Type' => 'application/json',
                'x-sms-ir-secure-token' => $token
            ],
        ];
        $remote = wp_remote_get($url, $args);
        $response = json_decode(wp_remote_retrieve_body($remote));
        return isset($response->IsSuccessful) && $response->IsSuccessful && isset($response->SMSLines) ? $response->SMSLines : false;
    }
    static function send($to, $msg)
    {
        $pattern_id = self::get_pattern_id();
        return $pattern_id ? self::send_with_pattern_mode($to, $msg, $pattern_id) : self::simple_send($to, $msg);
    }
    static function send_with_pattern_mode($to, $msg, $pattern_id)
    {
        
        $token = self::get_token();
        if(!$token)
        {
            return false;
        }
        // $msgs = explode(chr(10), $msg);
        $msgs = explode(PHP_EOL, $msg);
        $parameter = [];
        $counter = 0;
        foreach($msgs as $value)
        {
            list($key, $name) = explode("=", $value);
            $parameter[$counter]['Parameter'] = trim($key);
            $parameter[$counter]['ParameterValue'] = trim($name);
            $counter ++;
        }
        $url = self::get_base_url() . 'UltraFastSend';
        $to = tools::getValidatedPhoneNumber($to);
        $args = [
            'headers' =>[
                'Content-Type' => 'application/json',
                'x-sms-ir-secure-token' => $token
            ],
            "body" => json_encode([
                "ParameterArray" => $parameter,
                "Mobile" => $to,
                "TemplateId" => $pattern_id
            ])
        ];
        $remote = wp_remote_post($url, $args);
        return json_decode(wp_remote_retrieve_body($remote));
    }
    static function simple_send($to, $msg)
    {
        $token = self::get_token();
        if(!$token)
        {
            return false;
        }
        $gateway = self::get_gateway_number();
        $to = tools::getValidatedPhoneNumber($to);
        $msg = sanitize_textarea_field($msg);
        $url = self::get_base_url() . 'MessageSend';
        $args = [
            'headers' =>[
                'Content-Type' => 'application/json',
                'x-sms-ir-secure-token' => $token
            ],
            "body" => json_encode([
                "Messages" => [$msg],
                "MobileNumbers" => [$to],
                "LineNumber" => $gateway,
                "SendDateTime" => "",
                "CanContinueInCaseOfError" => false,
            ])
        ];
        $remote = wp_remote_post($url, $args);
        return json_decode(wp_remote_retrieve_body($remote));
    }
    
    static function get_secret_key()
    {
        return get_option('mw_smsir_secret_key');
    }
    static function get_api_key()
    {
        return get_option('mw_smsir_api_key');
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
        $secret_key = self::get_secret_key();
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
                <label for="mw_smsir_secret_key"><?php _e("Secret Key", "mihanpanel"); ?></label>
                <input dir="auto" value="<?php echo esc_attr($secret_key); ?>" type="text" name="mw_smsir_secret_key" id="mw_smsir_secret_key">
            </div>
            <div class="mp_option_field mp_row_field">
                <label for="mw_smsir_api_key"><?php _e("API Key", "mihanpanel"); ?></label>
                <input dir="auto" type="text" value="<?php echo esc_attr($api_key); ?>" name="mw_smsir_api_key" id="mw_smsir_api_key">
            </div>
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
            <?php if($lines): ?>
                <div class="mp_option_field mp_row_field">
                    <label><?php _e("Choose gateway number", "mihanpanel"); ?></label>
                    <select name="mw_smsir_gateway_number" id="mw_smsir_gateway_number">
                        <option value="0" selected disabled><?php _e("Choose gateway number", "mihanpanel"); ?></option>
                        <?php foreach($lines as $line): ?>
                            <option <?php selected($line->LineNumber, $gateway_number); ?> value="<?php echo esc_attr($line->LineNumber); ?>"><?php echo esc_html($line->LineNumber); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    static function get_provider_settings()
    {
        return [
            'mw_smsir_secret_key',
            'mw_smsir_api_key',
            'mw_smsir_gateway_number',
            'mw_smsir_pattern_id'
        ];
    }
    static function validate_send_message($response)
    {
        if(!isset($response->IsSuccessful))
        {
            return false;
        }
        $res['status'] = $response->IsSuccessful ? 200 : $response->IsSuccessful;
        $res['msg'] = isset($response->Message) ? $response->Message : false;
        return $res;
    }
}