<?php
namespace mihanpanel\pro\app;

use mihanpanel\app\tools as AppTools;

class tools
{
    static function clear_phone_number($phone)
    {
        return self::getValidatedPhoneNumber($phone);
    }
    static function is_phone_number($phone)
    {
        return self::getValidatedPhoneNumber($phone);
    }
    static function getValidatedPhoneNumber($phone)
    {
        $phone = AppTools::convertNumberLocale($phone);
        if(!is_numeric($phone))
        {
            return false;
        }
        $pattern = '/^(0|98|\+98)?(90|91|92|93|99)([0-9]{1})([0-9]{3})([0-9]{4})$/';
        $validation = preg_match($pattern, $phone, $matches);
        if(!$validation)
        {
            return false;
        }
        return preg_replace('/^(0|98|\+98)/', '', $phone);
    }
    static function getPhoneNumberForDisplayMode($phone)
    {
        $phone = self::getValidatedPhoneNumber($phone);
        if(!$phone)
        {
            return $phone;
        }
        return substr_replace($phone, '***', -7, 3);
    }
    static function getEmailValueForDisplayMode($email)
    {
        if(!$email)
        {
            return false;
        }
        $email = explode('@', $email);
        $emailProvider = array_pop($email);
        $email = implode('@', $email);
        $len = floor(strlen($email) / 2);
        return substr_replace($email, str_repeat('*', $len), $len) . '@' . $emailProvider;
    }
}