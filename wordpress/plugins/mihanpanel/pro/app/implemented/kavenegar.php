<?php
namespace mihanpanel\pro\app\implemented;

use mihanpanel\pro\app\contracts\sms_provider_interface;
use mihanpanel\pro\app\tools;

class kavenegar implements sms_provider_interface
{
    static function get_base_url()
    {
        $api = self::get_api_key();
        return $api ? "https://api.kavenegar.com/v1/{$api}/" : false;
    }
    static function get_credit_raw()
    {
        $url = self::get_base_url() . 'account/info.json';
        if(!$url)
        {
            return false;
        }
        $remote = wp_remote_get($url);
        if(is_wp_error($remote))
        {
            return false;
        }
        $response = json_decode(wp_remote_retrieve_body($remote));
        return isset($response->return->status) && $response->return->status == 200 ? $response->entries->remaincredit : false;
    }
    static function get_credit()
    {
        $credit = self::get_credit_raw();
        return $credit ? sprintf(__("%s Rial", 'mihanpanel'), number_format($credit)) : false;
    }
    static function send($to, $msg)
    {
        $template_name = self::get_template_name();
        return $template_name ? self::send_with_template_mode($to, $msg, $template_name) : self::simple_send($to, $msg);
    }
    static function send_with_template_mode($to, $msg, $template_name)
    {
        $url = self::get_base_url() . 'verify/lookup.json';
        $to = tools::getValidatedPhoneNumber($to);
        $parameters = explode(PHP_EOL, $msg);
        $parameters = array_map('trim', $parameters);
        $args = [
            "body" => [
                "receptor" => $to,
                "template" => $template_name,
            ]
        ];
        for($i=1; $i<=count($parameters); $i++)
        {
            $token_index = $i > 1 ? $i : '';
            $args['body']['token' . $token_index] = $parameters[$i-1];
        }
        $remote = wp_remote_post($url, $args);
        $response = json_decode(wp_remote_retrieve_body($remote));
        return $response;
    }
    static function simple_send($to, $msg)
    {
        $url = self::get_base_url() . 'sms/send.json';
        $to = tools::getValidatedPhoneNumber($to);
        $msg = sanitize_textarea_field($msg);
        $args = [
            "body" => [
                "receptor" => $to,
                "message" => $msg,
            ]
        ];
        if($gateway_number = self::get_gateway_number())
        {
            $args['body']["sender"] = $gateway_number;
        }
        $remote = wp_remote_post($url, $args);
        $response = json_decode(wp_remote_retrieve_body($remote));
        return $response;
    }
    static function render_settings(){
        $api_key = self::get_api_key();
        $credit = self::get_credit();
        $gateway_number = self::get_gateway_number();
        $template_name = self::get_template_name();
        ?>
        <div class="mp_option_section">
            <h3 class="mp_option_section_title"><?php _e('Sms.ir settings', 'mihanpanel')?></h3>
            <div class="mp_option_field mp_row_field">
                <span><?php printf("%s: ", _e("Account Credit", 'mihanpanel')) ?></span>
                <span><?php echo esc_html($credit); ?></span>
            </div>
            <div class="mp_option_field mp_row_field">
                <label for="mw_kavenegar_api_key"><?php _e("API Key", "mihanpanel"); ?></label>
                <input dir="auto" type="text" value="<?php echo esc_attr($api_key); ?>" name="mw_kavenegar_api_key" id="mw_kavenegar_api_key">
            </div>
            <div class="mp_option_field mp_row_field">
                <label for="mw_kavenegar_gateway_number"><?php printf('%s (%s)', __("Gateway Number", "mihanpanel"), __("It's can be empty", "mihanpanel")); ?></label>
                <input dir="auto" type="text" value="<?php echo esc_attr($gateway_number); ?>" name="mw_kavenegar_gateway_number" id="mw_kavenegar_gateway_number">
            </div>
            <div class="mp_option_field mp_row_field">
                <label for="mw_kavenegar_template_name"><?php _e("Template Name", "mihanpanel"); ?></label>
                <input dir="auto" type="text" value="<?php echo esc_attr($template_name); ?>" name="mw_kavenegar_template_name" id="mw_kavenegar_template_name">
            </div>
            <div class="mp_description_box">
                <p class="mp_option_section_description"><?php _e("You can enter template id if this feature is enable in your sms provider account.", "mihanpanel"); ?></p>
                <p class="mp_option_section_description"><?php _e("If Use this feature, just enter mihanpanel pre-defined variables in new line according the variables order defined in sms provider templates for sms content field value; like this:", "mihanpanel"); ?></p>
                <p class="mp_option_section_description"><?php printf("%s", "[[user_login]]"); ?></p>
                <p class="mp_option_section_description"><?php printf("%s", "[[code]]"); ?></p>
            </div>
        </div>
        <?php
    }
    static function get_api_key()
    {
        return get_option('mw_kavenegar_api_key');
    }
    static function get_gateway_number()
    {
        return get_option('mw_kavenegar_gateway_number');
    }
    static function get_template_name()
    {
        return get_option('mw_kavenegar_template_name');
    }
    static function get_provider_settings(){
        return [
            'mw_kavenegar_api_key',
            'mw_kavenegar_gateway_number',
            'mw_kavenegar_template_name'
        ];
    }
    static function validate_send_message($response)
    {
        if(!isset($response->return->status))
        {
            return false;
        }
        $res['status'] = $response->return->status;
        $res['msg'] = $response->return->message;
        return $res;
    }
}