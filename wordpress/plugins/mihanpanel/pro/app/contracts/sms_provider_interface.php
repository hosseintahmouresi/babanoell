<?php
namespace mihanpanel\pro\app\contracts;
interface sms_provider_interface
{
    static function send($to, $msg);
    static function render_settings();
    static function get_provider_settings();
    static function validate_send_message($response);
}