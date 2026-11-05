<?php
namespace mihanpanel\pro\app\implemented;

use mihanpanel\pro\app\contracts\sms_provider_interface;

class wpsms implements sms_provider_interface
{
    static function send($to, $msg)
    {
        $to = !is_array($to) ? [$to] : $to;
        return wp_sms_send($to, $msg);
    }
    static function render_settings()
    {
        ?>
        <div class="mp_option_section">
            <div class="mp_option_field">
                <p style="background: #0191d7;color: white;padding: 15px 15px;border-radius: 5px;"><?php esc_html_e('Config your sms provider settings from WpSms dashboard', 'mihanpanel')?></p>
            </div>
        </div>
        <?php
    }
    static function get_provider_settings()
    {

    }
    static function validate_send_message($response)
    {
        if(is_wp_error($response))
        {
            return [
                'msg' => esc_html__('Has error in send sms', 'mihanpanel'),
                'status' => 400
            ];
        }
        return ['status' => 200, 'msg' => esc_html__('Sms send successfully', 'mihanpanel')];
    }
}