<?php
namespace mihanpanel\pro\app;
class user_fields
{
    static function load_fields_deps($field_type, $field)
    {
        $method = 'load_' . $field_type . '_assets';
        if (method_exists(__CLASS__, $method)) {
            call_user_func([__CLASS__, $method], $field_type, $field);
        }
    }
    static function load_datepicker_assets($field_type, $field)
    {
        $persian_date = assets::get_js_url('persian-date.min');
        $persian_date_picker = assets::get_js_url('persian-datepicker.min');
        $persian_date_picker_css = assets::get_css_url('persian-datepicker');
        $mw_persian_date_picker = assets::get_js_url('mw_persian_datepicker');

        assets::enqueue_style('persian_date_picker_lib', $persian_date_picker_css);
        assets::enqueue_script('persian_date_lib', $persian_date);
        assets::enqueue_script('persian_date_picker_lib', $persian_date_picker);
        assets::enqueue_script('persian_date_picker', $mw_persian_date_picker, ['jquery', assets::get_handle_name('persian_date_lib'), assets::get_handle_name('persian_date_picker_lib')]);
        $calendar_type = \mihanpanel\app\tools::is_persian_locale() ? 'persian' : 'gregorian';
        assets::localize_script('persian_date_picker', 'mwp_data', ['calendar_type' => $calendar_type]);
    }
    static function load_file_uploader_assets()
    {
        $uploader_js = assets::get_js_url('mw_fields_uploader');
        wp_enqueue_style('dashicons');
        assets::enqueue_script('fields_uploader', $uploader_js);
    }
    static function render_fields_permission($permission, $field, $view)
    {
        $meta = $field->meta;
        $meta = $meta ? unserialize($meta) : false;
        return $view == 'register-form' ? !isset($meta['data']['just_in_profile']) : !isset($meta['data']['just_in_register_form']);
    }
    static function getEditProfileWordpressDefaultFields()
    {
        return [
            'username' => sprintf('%s (%s)', __('Username', 'mihanpanel'), __('Users cannot change its value', 'mihanpanel')),
            'first_name' => __('First name', 'mihanpanel'),
            'last_name' => __('Last name', 'mihanpanel'),
            'password' => __('Password', 'mihanpanel'),
            'bio' => __('Bio', 'mihanpanel'),
        ];
    }
    static function setActiveWordpressDefaultFields(array $values)
    {
        $values = serialize($values);
        return update_option('mw_wp_default_fields', $values);
    }
    static function clearActiveWordpressDefaultFields()
    {
        return delete_option('mw_wp_default_fields');
    }
    static function getActiveWordpressDefaultFields()
    {
        $fields = get_option('mw_wp_default_fields');
        return $fields ? unserialize($fields) : [];
    }

}