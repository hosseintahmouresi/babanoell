<?php
namespace mihanpanel\pro\app\adapter;
class woo
{
    static function set_active_fields(array $value)
    {
        $value = serialize($value);
        return update_option('mw_woo_fields', $value);
    }
    static function clear_active_fields()
    {
        return delete_option('mw_woo_fields');
    }
    static function filter_active_fields($active_fields)
    {
        $value = get_option('mw_woo_fields');
        return $value ? unserialize($value) : [];
    }
}