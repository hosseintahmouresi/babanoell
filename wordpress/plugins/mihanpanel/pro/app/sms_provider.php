<?php
namespace mihanpanel\pro\app;

use mihanpanel\pro\app\implemented\kavenegar;
use mihanpanel\pro\app\implemented\melipayamak;
use mihanpanel\pro\app\implemented\sabanovin;
use mihanpanel\pro\app\implemented\smsir;
use mihanpanel\pro\app\implemented\smsir_new;
use mihanpanel\pro\app\implemented\wpsms;

class sms_provider
{
    private static $_providers = [];
    private static function load_provider_class($provider=false)
    {
        $all = self::get_providers_data();
        $provider = $provider ? $provider : options::get_active_sms_provider();
        if(isset($all[$provider]['class']) && class_exists($all[$provider]['class']))
        {
            return true;
        }
        $path = isset($all[$provider]['path']) ? $all[$provider]['path'] : false;
        if($path && file_exists($path) && is_readable($path))
        {
            include_once $path;
        }
    }
    public static function validate_provider($provider)
    {
        $_providers = self::get_providers_data();
        self::load_provider_class($provider);
        return in_array($provider, array_keys($_providers)) && isset($_providers[$provider]['class']) && $_providers[$provider]['class'] && class_exists($_providers[$provider]['class']) ? true : false;
    }
    static function send_message($to, $msg)
    {
        $_providers = self::get_providers_data();
        $active_provider = options::get_active_sms_provider();
        if(!self::validate_provider($active_provider))
        {
            return false;
        }
        return call_user_func([$_providers[$active_provider]['class'], "send"], $to, $msg);
    }
    static function validate_send_message($response)
    {
        $_providers = self::get_providers_data();
        $active_provider = options::get_active_sms_provider();
        if(!self::validate_provider($active_provider))
        {
            return false;
        }
        if(isset($_providers[$active_provider]['class']) && method_exists($_providers[$active_provider]['class'], 'validate_send_message'))
        {
            return call_user_func([$_providers[$active_provider]['class'], "validate_send_message"], $response);
        }
    }
    static function init_default_provider($providers)
    {
        $providers['sabanovin'] = [
            'title' => esc_html__('Sabanovin', 'mihanpanel'),
            'class' => sabanovin::class
        ];
        $providers['melipayamak'] = [
            'title' => esc_html__("Meli Payamak", 'mihanpanel'),
            'class' => melipayamak::class
        ];
        $providers['smsir'] = [
            'title' => esc_html__("SMS.ir", 'mihanpanel'),
            'class' => smsir::class
        ];
        $providers['smsir_new'] = [
            'title' => sprintf('%s [ %s ]', __('SMS.ir', 'mihanpanel'), __('New Panel', 'mihanpanel')),
            'class' => smsir_new::class,
        ];
        $providers['kavenegar'] = [
            'title' => esc_html__("Kavenegar", "mihanpanel"),
            'class' => kavenegar::class
        ];
        $providers['wpsms'] = [
            'title' => esc_html__('WP Sms', 'mihanpanel'),
            'class' => wpsms::class,
        ];
        return $providers;
    }
    private static function get_providers_data()
    {
        $providers_data = apply_filters('mihanpanel_sms_providers', self::$_providers);
        return $providers_data;
    }
    static function get_providers_title()
    {
        $all_providers = self::get_providers_data();
        $providers = [];
        foreach($all_providers as $provider_slug => $providers_data)
        {
            $providers[$provider_slug] = $providers_data['title'];
        }
        return $providers;
    }
    static function show_provider_pre_settings($provider)
    {
        ?>
        <div class="mp_option_section">
            <h3 class="mp_option_section_title"><?php _e('Test SMS', 'mihanpanel')?></h3>
            <p class="mp_option_section_description"><?php esc_html_e('Use this to send Test SMS to be sure your provider settings are correct.', 'mihanpanel')?></p>
            <p class="mp_option_section_description"><?php esc_html_e('The cost of this message will be deducted from your account.', 'mihanpanel')?></p>
            <div class="mp_option_field mp_row_field" mwpl_nonce="<?php echo esc_attr(wp_create_nonce('mwpl_send_test_sms'))?>">
                <label for="test_sms_phone_number"><?php esc_html_e('Enter phone number', 'mihanpanel') ?></label>
                <input id="test_sms_phone_number" dir="ltr" type="text" placeholder="<?php esc_attr_e('Your phone number', 'mihanpane')?>">
                <span><a href="#" id="test_sms_phone_number_btn" class="button-primary"><?php esc_html_e('Test SMS', 'mihanpanel')?></a></span>
            </div>
        </div>
        <?php
    }
    static function show_provider_settings($provider=false)
    {
        $provider = $provider ? $provider : options::get_active_sms_provider();
        if(!self::validate_provider($provider))
        {
            return false;
        }
        $_providers = self::get_providers_data();
        if(isset($_providers[$provider]['class']) && method_exists($_providers[$provider]['class'], 'render_settings'))
        {
            do_action('mwpl_option_panel/sms_provider_settings/before_render_provider_settings', $provider);
            return call_user_func([$_providers[$provider]['class'], 'render_settings']);
        }
    }
    static function add_provider_settings($setting_group)
    {
        $_providers = self::get_providers_data();
        foreach($_providers as $provider_key => $provider_data)
        {
            self::load_provider_class($provider_key);
            $settings = isset($_providers[$provider_key]['class']) && method_exists($_providers[$provider_key]['class'], 'get_provider_settings') ? call_user_func([$_providers[$provider_key]['class'], 'get_provider_settings']) : false;
            if($settings && is_array($settings))
            {
                foreach($settings as $setting)
                {
                    if(is_array($setting) && isset($setting['setting_name']) && isset($setting['sanitize_callback']))
                    {
                        register_setting($setting_group, $setting['setting_name'], ['sanitize_callback' => $setting["sanitize_callback"]]);
                    }elseif(is_string($setting)){
                        register_setting($setting_group, $setting);
                    }
                }
            }
        }
    }
}