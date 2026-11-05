<?php
namespace mihanpanel\pro\app\implemented;

use \mihanpanel\pro\app\contracts\sms_provider_interface;
use mihanpanel\pro\app\tools;

class sabanovin implements sms_provider_interface
{
    private static $_provider_settings = [
        [
            'setting_name' => 'mw_sabanovin_provider_gateway_number',
            'sanitize_callback' => [__CLASS__, 'sanitize_gateway_number']
        ],
        'mw_sabanovin_provider_gateway_api_key'
    ];
    private static function base_url()
    {
        $api = self::get_gateway_api_key();
        $url = 'https://api.sabanovin.com/v1/'.$api.'/';
        return $url;
    }
    static function get_credit_raw()
    {
        $url = self::base_url() . 'account/balance.json';
        $remote = wp_remote_get($url);
        $response = json_decode(wp_remote_retrieve_body($remote));
        return wp_remote_retrieve_response_code($remote) === 200 && $response->status->code == 200 ? $response->entry->balance : false;
    }
    static function get_credit()
    {
        $credit = self::get_credit_raw();
        return is_int($credit) ? sprintf(__("%s Toman", 'mihanpanel'), number_format($credit)) : false;
    }
    static function sanitize_gateway_number($input=null)
    {
        if(!$input)
        {
            return false;
        }
        if(is_numeric($input))
        {
            return $input;
        }
        add_settings_error('mp_save_setting_msg', 'saba_novin_gateway_number_res', __("Please use latin numbers as the gateway number", 'mihanpanel'), 'error');
    }
    static function validate_send_message($response)
    {
        if(!isset($response->status) || !$response->status)
        {
            return false;
        }
        $res['status'] = $response->status->code;
        $res['msg'] = $response->status->message;
        return $res;
    }
    static function send($to, $msg)
    {
        $gateway = self::get_gateway_number();
        $to = tools::getValidatedPhoneNumber($to);
        $msg = sanitize_textarea_field($msg);
        $request = self::base_url() . "sms/send.json?gateway={$gateway}&to={$to}&text={$msg}";
        $remote = wp_remote_get($request);
        $response_code = wp_remote_retrieve_response_code($remote);
        return $response_code == 200 ? json_decode(wp_remote_retrieve_body($remote)) : $response_code;
    }
    static function get_provider_settings()
    {
        return self::$_provider_settings;
    }
    static function get_gateway_number()
    {
        return get_option('mw_sabanovin_provider_gateway_number');
    }
    static function get_gateway_api_key()
    {
        return get_option('mw_sabanovin_provider_gateway_api_key');
    }
    static function render_settings()
    {
        $gateway_number = self::get_gateway_number();
        $gateway_api_key = self::get_gateway_api_key();
        $credit = self::get_credit();
        ?>
        <div class="mp_option_section">
            <h3 class="mp_option_section_title"><?php _e('Sabanovin settings', 'mihanpanel')?></h3>
            <div class="mp_option_field mp_row_field">
                <span><?php printf("%s: ", _e("Account Credit", 'mihanpanel')) ?></span>
                <span><?php echo esc_html($credit); ?></span>
            </div>
            <div class="mp_option_field mp_row_field">
                <label for="mw_sabanovin_provider_gateway_number"><?php _e("Gateway Number", "mihanpanel"); ?></label>
                <input dir="auto" value="<?php echo esc_attr($gateway_number); ?>" type="text" name="mw_sabanovin_provider_gateway_number" id="mw_sabanovin_provider_gateway_number">
            </div>
            <div class="mp_option_field mp_row_field">
                <label for="mw_sabanovin_provider_gateway_api_key"><?php _e("Gateway API Key", "mihanpanel"); ?></label>
                <input dir="auto" type="text" value="<?php echo esc_attr($gateway_api_key); ?>" name="mw_sabanovin_provider_gateway_api_key" id="mw_sabanovin_provider_gateway_api_key">
            </div>
        </div>
        <?php
    }
}