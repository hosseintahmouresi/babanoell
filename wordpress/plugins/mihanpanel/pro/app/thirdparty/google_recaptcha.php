<?php
namespace mihanpanel\pro\app\thirdparty;

use mihanpanel\pro\app\options;

class google_recaptcha
{
    const RECAPTCHA_VERSION = '1.2';

    public static function get_captcha_type(){
        return options::get_google_recaptcha_type();
    }

    public static function get_site_key(){
        return options::get_google_recaptcha_site_key();
    }

    public static function get_secret_key(){
        return options::get_google_recaptcha_secret_key();
    }

    public static function get_api_url($type){
        $site_key = self::get_site_key();

        if ('v2' === $type) {
            return 'https://www.google.com/recaptcha/api.js?hl=' . esc_attr(get_locale()) . '&onload=mwpAgrLoad&render=explicit';
        } elseif ('v3' === $type) {
            return 'https://www.google.com/recaptcha/api.js?onload=mwpAgrV3&render=' . esc_attr($site_key);
        }

        return null;
    }

    public static function validate_recaptcha($response = null){
        $secret_key = self::get_secret_key();

        $recaptcha_response = !empty($response) ? $response : $_POST['g-recaptcha-response'];

        $captcha_obj = new \ReCaptcha\ReCaptcha($secret_key);

        $resp = $captcha_obj->setExpectedHostname($_SERVER['SERVER_NAME'])->verify($recaptcha_response, $_SERVER['REMOTE_ADDR']);

        return $resp->isSuccess();
    }
}