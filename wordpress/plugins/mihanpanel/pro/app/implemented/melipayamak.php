<?php
namespace mihanpanel\pro\app\implemented;

use mihanpanel\pro\app\contracts\sms_provider_interface;
use mihanpanel\pro\app\tools;

class melipayamak implements sms_provider_interface
{
    static function base_url()
    {
        return 'https://rest.payamak-panel.com/api/SendSMS/';
    }
    static function send($to, $msg)
    {
        $pattern_id = self::get_pattern_id();
        return $pattern_id ? self::send_with_pattern_mode($to, $msg, $pattern_id) : self::simple_send($to, $msg);
    }
    static function send_with_pattern_mode($to, $msg, $pattern_id)
    {
        $url = self::base_url() . 'BaseServiceNumber';
        $to = tools::getValidatedPhoneNumber($to);
        $username = self::get_username();
        $password = self::get_password();
        if(!$username || !$password || !$msg || !$to)
        {
            return false;
        }
        $parameters = explode(PHP_EOL, $msg);
        $parameters = array_map('trim', $parameters);
        $parameters = implode(';', $parameters);
        $args = [
            "body" => [
                'username' => $username,
                'password' => $password,
                'to' => $to,
                'text' => $parameters,
                'bodyId' => $pattern_id
            ]
        ];
        $remote = wp_remote_post($url, $args);
        $response_code = wp_remote_retrieve_response_code($remote);
        return $response_code == 200 ? json_decode(wp_remote_retrieve_body($remote)) : $response_code;
    }
    static function simple_send($to, $msg){
        $url = self::base_url() . 'SendSMS';
        $to = tools::getValidatedPhoneNumber($to);
        $msg = sanitize_text_field($msg);
        $username = self::get_username();
        $password = self::get_password();
        $gateway_number = self::get_gateway_number();
        $args = [
            'body' => [
                'username' => $username,
                'password' => $password,
                'to' => $to,
                'from' => $gateway_number,
                'text' => $msg,
                'isFlash' => false
            ]
        ];
        $remote = wp_remote_post($url, $args);
        $response_code = wp_remote_retrieve_response_code($remote);
        return $response_code == 200 ? json_decode(wp_remote_retrieve_body($remote)) : $response_code;
    }
    static function get_credit()
    {
        $url = self::base_url() . 'GetCredit';
        $username = self::get_username();
        $password = self::get_password();
        $args = [
            "body" => [
                "username" => $username,
                "password" => $password
            ]
        ];
        $remote = wp_remote_post($url, $args);
        $response = json_decode(wp_remote_retrieve_body($remote));
        return wp_remote_retrieve_response_code($remote) == 200 && $response->RetStatus == 1 ? intval($response->Value) : false;
    }
    static function get_username()
    {
        return get_option('mw_melipayamak_username');
    }
    static function get_password()
    {
        return get_option('mw_melipayamak_password');
    }
    static function get_gateway_number()
    {
        return get_option('mw_melipayamak_gateway_number');
    }
    static function get_pattern_id()
    {
        return get_option('mw_melipayamak_pattern_id');
    }
    static function render_settings(){
        $username = self::get_username();
        $password = self::get_password();
        $gateway_number = self::get_gateway_number();
        $credit = self::get_credit();
        $pattern_id = self::get_pattern_id();
        ?>
        <div class="mp_option_section">
            <h3 class="mp_option_section_title"><?php _e('Mellipayamak settings', 'mihanpanel')?></h3>
            <div class="mp_option_field mp_row_field">
                <span><?php printf("%s: ", _e("Account Credit", 'mihanpanel')) ?></span>
                <span><?php echo esc_html($credit); ?></span>
            </div>
            <div class="mp_option_field mp_row_field">
                <label for="mw_melipayamak_username"><?php _e("Username", "mihanpanel"); ?></label>
                <input dir="auto" value="<?php echo esc_attr($username); ?>" type="text" name="mw_melipayamak_username" id="mw_melipayamak_username">
            </div>
            <div class="mp_option_field mp_row_field">
                <label for="mw_melipayamak_password"><?php _e("Password", "mihanpanel"); ?></label>
                <input dir="auto" type="text" value="<?php echo esc_attr($password); ?>" name="mw_melipayamak_password" id="mw_melipayamak_password">
            </div>
            <div class="mp_option_field mp_row_field">
                <label for="mw_melipayamak_gateway_number"><?php _e("Gateway Number", "mihanpanel"); ?></label>
                <input dir="auto" type="text" value="<?php echo esc_attr($gateway_number); ?>" name="mw_melipayamak_gateway_number" id="mw_melipayamak_gateway_number">
            </div>
            <div class="mp_option_field mp_row_field">
                <label for="mw_melipayamak_pattern_id"><?php _e("Template ID", "mihanpanel"); ?></label>
                <input dir="auto" type="text" value="<?php echo esc_attr($pattern_id); ?>" name="mw_melipayamak_pattern_id" id="mw_melipayamak_pattern_id">
            </div>
            <p class="mp_option_section_description"><?php _e("You can enter template id if this feature is enable in your sms provider account.", "mihanpanel"); ?></p>
            <p class="mp_option_section_description"><?php _e("If Use this feature, just enter mihanpanel pre-defined variables in new line according the variables order defined in sms provider templates for sms content field value; like this:", "mihanpanel"); ?></p>
            <p class="mp_option_section_description"><?php printf("%s", "[[user_login]]"); ?></p>
            <p class="mp_option_section_description"><?php printf("%s", "[[code]]"); ?></p>
        </div>
        <?php
    }
    static function get_provider_settings(){
        return [
            'mw_melipayamak_username',
            'mw_melipayamak_password',
            'mw_melipayamak_gateway_number',
            'mw_melipayamak_pattern_id'
        ];
    }
    static function validate_send_message($response)
    {
        if(!isset($response->RetStatus))
        {
            return false;
        }
        $res['status'] = $response->RetStatus == 1 ? 200 : $response->RetStatus;
        $res['msg'] = $response->StrRetStatus;
        return $res;
    }
}