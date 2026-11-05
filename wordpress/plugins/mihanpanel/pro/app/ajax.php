<?php

namespace mihanpanel\pro\app;

use mihanpanel\app\email;
use mihanpanel\app\session;
use mihanpanel\pro\app\email as EmailPro;

class ajax
{
    static function init()
    {
        add_action('wp_ajax_mw_mihanpanel_dismiss_license_admin_notice', [__CLASS__, 'dismissLicenseAdminNotice']);
        add_action('wp_ajax_mw_get_mobile_verification_code', [__CLASS__, 'get_mobile_verification_code']);
        add_action('wp_ajax_mw_verify_mobile_verification_code', [__CLASS__, 'verify_mobile_verification_code']);
        add_action('wp_ajax_mw_get_sms_provider_settings', [__CLASS__, 'get_sms_provider_settings']);
        add_action('wp_ajax_mw_send_test_sms', [__CLASS__, 'send_test_sms']);

        add_action('wp_ajax_mwpl_process_license', [__CLASS__, 'process_license']);

        add_action('wp_ajax_mwpl_admin_professional_login_check_admin_user_has_valid_phone', [__CLASS__, 'handleProfessionalTabCheckAdminHasValidPhone']);
        add_action('wp_ajax_mwpl_admin_professional_login_handle_admin_user_has_valid_phone_set_value', [__CLASS__, 'handleProfessionalTabCheckAdminSetValidPhone']);
        

        // google 2fa
        add_action('wp_ajax_nopriv_mwpl_google_2fa_render_field_in_login', [__CLASS__, 'renderGoogle2faField']);
        
        // live edit
        // TODO: Delete this, because this is in lite version source code
        // add_action('wp_ajax_mwpl_live_edit_tabs_fields_get_items', [__CLASS__, 'handle_live_edit_tabs_fields_get_items']);
        
        add_action('wp_ajax_mwpl_live_edit_tabs_fields_get_item', [__CLASS__, 'handle_live_edit_tabs_fields_get_item']);
        add_action('wp_ajax_mwpl_live_edit_tabs_fields_get_item_conten_fields', [__CLASS__, 'handle_live_edit_tabs_fields_get_item_conten_fields']);

        add_action('wp_ajax_mwpl_live_edit_tabs_fields_edit_item_title', [__CLASS__, 'handle_live_edit_tabs_fields_edit_item_title']);
        add_action('wp_ajax_mwpl_live_edit_tabs_fields_edit_item_icon', [__CLASS__, 'handle_live_edit_tabs_fields_edit_item_icon']);
        add_action('wp_ajax_mwpl_live_edit_tabs_fields_edit_item_content', [__CLASS__, 'handle_live_edit_tabs_fields_edit_item_content']);

        add_action('wp_ajax_mwpl_live_edit_tabs_fields_add_new_item', [__CLASS__, 'handle_live_edit_tabs_fields_add_new_item']);
        add_action('wp_ajax_mwpl_live_edit_tabs_fields_update_priorities', [__CLASS__, 'handle_live_edit_tabs_fields_update_priorities']);
        add_action('wp_ajax_mwpl_live_edit_tabs_fields_delete_item', [__CLASS__, 'handle_live_edit_tabs_fields_delete_item']);

        add_action('wp_ajax_mw_admin_delete_ghost_users', [__CLASS__, 'handle_delete_ghost_users']);
        add_action('wp_ajax_mw_update_user_phone_status', [__CLASS__, 'handle_update_user_phone_status']);

        if (options::is_dynamic_password_mode()){
            add_action('mwpl_register_form_before_create_new_user', [__CLASS__, 'handle_check_mobile_in_register_process']);
        }

        // custom login form
        add_action('wp_ajax_nopriv_mwpl_login_form_sms_login', [__CLASS__, 'handle_sms_login_check_phone']);
        add_action('wp_ajax_nopriv_mwpl_login_form_sms_login_validate_code_and_login', [__CLASS__, 'handle_sms_login_check_code_and_login']);
        add_action('wp_ajax_nopriv_mwpl_smart_login_form_check_username', [__CLASS__, 'handle_smart_login_form_check_username']);
        add_action('wp_ajax_nopriv_mwpl_smart_login_form_check_password', [__CLASS__, 'handle_smart_login_form_check_password_and_login']);
        add_action('wp_ajax_nopriv_mwpl_smart_login_form_validate_dynamic_code', [__CLASS__, 'handle_smart_login_form_check_dynamic_code_and_register']);
        add_action('wp_ajax_nopriv_mwpl_smart_login_form_request_dynamic_code_for_login', [__CLASS__, 'handle_smart_login_form_send_dynamic_code_for_login']);

        add_action('wp_ajax_nopriv_mwpl_login_form_sms_login_resend_dynamic_code', [__CLASS__, 'handle_sms_login_resend_dynamic_code']);
        add_action('wp_ajax_nopriv_mwpl_smart_login_resend_dynamic_code', [__CLASS__, 'handle_smart_login_resend_dynamic_code']);

        if (options::is_active_custom_captcha()){
            add_action('wp_ajax_nopriv_mp_generate_captcha', [__CLASS__, 'handle_generate_custom_captcha_token']);
        }
    }
    static function send_res($res)
    {
        die(json_encode($res));
    }
    static function checkNonce($action = 'mwpl_smart_login', $query = 'mwpl_nonce')
    {
        check_ajax_referer($action, $query);
    }
    static function send_test_sms()
    {
        self::checkNonce('mwpl_send_test_sms');
        $phone = isset($_POST['phone']) ? \mihanpanel\pro\app\tools::getValidatedPhoneNumber(sanitize_text_field($_POST['phone'])) : false;
        $res = [
            'msg' => esc_html__('Invalid phone number', 'mihanpanel'),
            'code' => 400
        ];
        if (!$phone) {
            self::send_res($res);
        }
        $user = wp_get_current_user();
        $sms_content = sms::get_login_dynamic_code_sms_content($user, '123456');
        $send_res = sms_provider::send_message($phone, $sms_content);
        $send_sms_validation = sms_provider::validate_send_message($send_res);
        // $res['validation'] = $send_sms_validation;
        $res['msg'] = $send_sms_validation['msg'];
        $res['code'] = $send_sms_validation['status'];
        self::send_res($res);
    }
    static function renderGoogle2faField()
    {
        $res['code'] = 400;
        $fieldType = isset($_POST['field']) && $_POST['field'] ? sanitize_text_field($_POST['field']) : false;
        $fieldValue = isset($_POST['value']) && $_POST['value'] ? sanitize_text_field($_POST['value']) : false;
        $user = false;
        switch($fieldType)
        {
            case 'username':
                $user = get_user_by('login', $fieldValue);
                break;
            case 'email':
                $user = get_user_by('email', $fieldValue);
                break;
            case 'phone':
                $user = users::get_user_by_phone($fieldValue);
                break;
        }
        if(!$user)
        {
            $res['msg'] = __('User not found', 'mihanpanel');
            self::send_res($res);
        }

        // check is 2fa active for this user
        $is2faEnable = \mihanpanel\app\options::get_smart_login_2fa_status() && \mihanpanel\app\users::isActive2FA($user->ID);
        if(!$is2faEnable)
        {
            $res['code'] = 200;
            self::send_res($res);
        }

        $res['code'] = 200;
        
        ob_start();
        $fieldView = views::get('user.2fa_login_field');
        include_once $fieldView;
        $res['field_view'] = ob_get_clean();
        
        self::send_res($res);
        
    }
    static function process_license()
    {
        check_ajax_referer('mwpl_license_process', 'mwpl_nonce');
        $res['status'] = 400;
        $res['msg'] = esc_html__('Has Error!', 'mihanpanel');
        $process_type = isset($_POST['process_type']) && $_POST['process_type'] ? sanitize_text_field($_POST['process_type']) : false;
        $method = 'process_license_' . $process_type;
        if (method_exists(__CLASS__, $method)) {
            call_user_func([__CLASS__, $method]);
        }
        die(json_encode($res));
    }
    static function process_license_activate()
    {
        $res['status'] = 400;
        $res['msg'] = esc_html__('Has Error!', 'mihanpanel');
        $license_key = isset($_POST['license_key']) && $_POST['license_key'] ? trim(sanitize_text_field($_POST['license_key'])) : false;
        if (!$license_key) {
            $res['msg'] = esc_html__("Invalid license key", 'mihanpanel');
            die(json_encode($res));
        }
        $res = license::activate_license_process($license_key);
        die(json_encode($res));
    }
    static function process_license_deactivate()
    {
        $res['status'] = 400;
        $res['msg'] = esc_html__('Has Error!', 'mihanpanel');
        $license_key = isset($_POST['license_key']) && $_POST['license_key'] ? trim(sanitize_text_field($_POST['license_key'])) : false;
        if (!$license_key) {
            $res['msg'] = esc_html__("Invalid license key", 'mihanpanel');
            die(json_encode($res));
        }
        $res = license::deactivate_license_process($license_key);
        die(json_encode($res));
    }
    static function get_mobile_verification_code()
    {
        $res['code'] = 400;
        $nonce = isset($_POST['mwpl_nonce']) ? sanitize_text_field($_POST['mwpl_nonce']) : false;
        if (!wp_verify_nonce($nonce, 'mwpl_mobile_verification')) {
            $res['msg'] = __('The operation failed due to security issues.', 'mihanpanel');
            die(json_encode($res));
        }
        $phone = isset($_POST['mobile']) && $_POST['mobile'] ? tools::getValidatedPhoneNumber(sanitize_text_field($_POST['mobile'])) : false;
        if (!$phone) {
            $res['msg'] = esc_html__('Please use valid phone number.', 'mihanpanel');
            die(json_encode($res));
        }
        $cuid = get_current_user_id();
        $old_phone = users::get_user_phone($cuid);
        if ($old_phone !== $phone) {
            // check is phone active on another account
            if (users::can_use_this_phone($phone, $cuid)) {
                users::set_user_phone($cuid, $phone);
            } else {
                $res['msg'] = esc_html__('This phone number is used in another account.', 'mihanpanel');
                die(json_encode($res));
            }
        }
        // check if has valid code
        if (dynamic_code::user_has_valid_code($cuid)) {
            $res['msg'] = esc_html__('Dynamic code was generated for you!', 'mihanpanel');
            die(json_encode($res));
        }
        // set new dynamic code
        $new_code_res = dynamic_code::set_user_new_code($cuid, $code, $expiration_time);
        if (!$new_code_res) {
            $res['msg'] = esc_html__("Dynamic code did not create for your account!", 'mihanpanel');
            die(json_encode($res));
        }
        // send dynamic code to user phone
        $user_data = get_user_by('id', $cuid);
        $send_res = sms::send_phone_validation_sms($user_data, $code);
        if ($send_res['status'] !== 200) {
            dynamic_code::remove_user_dynamic_code($cuid);
            $res['msg'] = esc_html__('Sory, has problem in send sms', 'mihanpanel');
            die(json_encode($res));
        }
        $res['code'] = 200;
        $res['expire_time'] = date('Y-m-d H:i:s', $expiration_time);
        $res['msg'] = esc_html__('Dynamic code send for you!', 'mihanpanel');
        die(json_encode($res));
    }
    static function verify_mobile_verification_code()
    {
        $res['code'] = 400;
        $nonce = isset($_POST['mwpl_nonce']) ? sanitize_text_field($_POST['mwpl_nonce']) : false;
        if (!wp_verify_nonce($nonce, 'mwpl_mobile_verification')) {
            $res['msg'] = __('The operation failed due to security issues.', 'mihanpanel');
            die(json_encode($res));
        }
        $code = isset($_POST['code']) && intval($_POST['code']) ? \mihanpanel\app\tools::convertNumberLocale(intval(sanitize_text_field($_POST['code']))) : false;
        if (!$code) {
            $res['msg'] = esc_html__('Please enter dynamic code', 'mihanpanel');
            die(json_encode($res));
        }
        $uid = get_current_user_id();
        $validate_code = dynamic_code::validate_code($uid, $code);
        if (!$validate_code) {
            $res['msg'] = esc_html__("Dynamic code was wrong", 'mihanpanel');
            die(json_encode($res));
        }
        $validate_phone = users::set_has_valid_phone($uid);
        if (!$validate_phone) {
            $res['msg'] = esc_html__('Sory, has problem in validate your phone.', 'mihanpanel');
            die(json_encode($res));
        }
        do_action('mihanpanel_after_validate_phone', $uid);
        $res['code'] = 200;
        $res['msg'] = esc_html__('Your phone successfully validate!', 'mihanpanel');
        die(json_encode($res));
    }
    static function get_sms_provider_settings()
    {
        $res['code'] = 400;
        $provider = isset($_POST['provider']) && $_POST['provider'] ? sanitize_text_field($_POST['provider']) : false;
        if (!$provider) {
            esc_html_e('Provider must send!', 'mihanpanel');
        }
        if (!sms_provider::validate_provider($provider)) {
            esc_html_e('Provider is not valid.', 'mihanpanel');
            die();
        }
        sms_provider::show_provider_settings($provider);
        die();
    }
    
    // TODO: Delete this line, because this is in lite version source code
    static function handle_live_edit_tabs_fields_get_items()
    {
        self::checkNonce('mwpl_ajax_update_live_edit_options');
        $res = [
            'status' => 400,
            'msg' => __('Has error!', 'mihanpanel'),
        ];
        $items = \mihanpanel\app\panel::get_tabs();
        if (!$items) {
            $res['msg'] = esc_html__('No any items found', 'mihanpanel');
            self::send_res($res);
        }
        ob_start();
        $base = \mihanpanel\app\options::get_panel_url();
        foreach ($items as $item) :
            $item_url = add_query_arg(['tab' => $item->id], $base); ?>
            <li tab-id="<?php echo esc_attr($item->id); ?>">
                <span class="movement-icon"></span>
                <a class="mwtaba" mwpl-href="<?php echo esc_url($item_url) ?>">
                    <?php \mihanpanel\app\presenter\tabs_menu::render_tab_item_icon($item->icon); ?>
                    <p>
                        <input type="text" value="<?php echo esc_html($item->name); ?>">
                    </p>
                </a>
                <span class="edit-icon"></span>
                <span class="remove-icon"></span>
            </li>
        <?php
        endforeach;
        $data = ob_get_clean();
        $res['msg'] = 'ok';
        $res['status'] = 200;
        $res['items'] = $items;
        $res['data'] = $data;
        self::send_res($res);
    }
    static function handle_live_edit_tabs_fields_get_item()
    {
        self::checkNonce('mwpl_ajax_update_live_edit_options');
        $res = [
            'status' => 400,
            'msg' => __('Has error!', 'mihanpanel'),
        ];
        $itemID = isset($_POST['item_id']) ? intval(sanitize_text_field($_POST['item_id'])) : false;
        if (!$itemID) {
            $res['msg'] = esc_html__('Invalid data', 'mihanpanel');
            self::send_res($res);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'mihanpaneltabs';
        $sql = "SELECT content from {$table_name} where id=%d";
        $fieldContent = $wpdb->get_var($wpdb->prepare($sql, $itemID));
        if (!$fieldContent) {
            $res['msg'] = esc_html__('Has error in get content', 'mihanpanel');
            self::send_res($res);
        }
        $res['content'] = $fieldContent;
        $res['msg'] = esc_html__('Successfully get data', 'mihanpanel');
        $res['status'] = 200;
        self::send_res($res);
    }
    static function handle_live_edit_tabs_fields_get_item_conten_fields()
    {
        self::checkNonce('mwpl_ajax_update_live_edit_options');
        $res = [
            'status' => 400,
            'msg' => __('Has error!', 'mihanpanel'),
        ];
        $itemID = isset($_POST['item_id']) ? intval(sanitize_text_field($_POST['item_id'])) : false;
        if (!$itemID) {
            $res['msg'] = esc_html__('Invalid data', 'mihanpanel');
            self::send_res($res);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'mihanpaneltabs';
        $sql = "SELECT id,content,link from {$table_name} where id=%d";
        $fieldData = $wpdb->get_row($wpdb->prepare($sql, $itemID));
        if (!$fieldData) {
            $res['msg'] = esc_html__('Has error in get content', 'mihanpanel');
            self::send_res($res);
        }
        $view = views::get('admin.live-edit.field-content-view');
        if (!$view) {
            $res['msg'] = esc_html__('Has error in get content', 'mihanpanel');
            self::send_res($res);
        }
        ob_start();
        include_once $view;
        $res['content'] = ob_get_clean();
        $res['msg'] = esc_html__('Successfully get data', 'mihanpanel');
        $res['status'] = 200;
        self::send_res($res);
    }
    static function handle_live_edit_tabs_fields_edit_item_title()
    {
        self::checkNonce('mwpl_ajax_update_live_edit_options');
        $res = [
            'status' => 400,
            'msg' => __('Has error!', 'mihanpanel'),
        ];
        $itemID = isset($_POST['item_id']) ? intval(sanitize_text_field($_POST['item_id'])) : false;
        if (!$itemID) {
            $res['msg'] = esc_html__('Invalid data', 'mihanpanel');
            self::send_res($res);
        }
        $title = sanitize_text_field($_POST['title']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'mihanpaneltabs';
        $update_res = $wpdb->update(
            $table_name,
            [
                'name' => $title,
            ],
            ['id' => $itemID],
        );
        if (!$update_res) {
            $res['msg'] = esc_html__('Has error in updating data', 'mihanpanel');
            self::send_res($res);
        }
        $res['msg'] = esc_html__('Successfully updated', 'mihanpanel');
        $res['status'] = 200;
        self::send_res($res);
    }
    static function handle_live_edit_tabs_fields_edit_item_icon()
    {
        self::checkNonce('mwpl_ajax_update_live_edit_options');
        $res = [
            'status' => 400,
            'msg' => __('Has error!', 'mihanpanel'),
        ];
        $itemID = isset($_POST['item_id']) ? intval(sanitize_text_field($_POST['item_id'])) : false;
        if (!$itemID) {
            $res['msg'] = esc_html__('Invalid data', 'mihanpanel');
            self::send_res($res);
        }
        $icon = sanitize_text_field($_POST['icon']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'mihanpaneltabs';
        $update_res = $wpdb->update(
            $table_name,
            [
                'icon' => $icon,
            ],
            ['id' => $itemID],
        );
        if (!$update_res) {
            $res['msg'] = esc_html__('Has error in updating data', 'mihanpanel');
            self::send_res($res);
        }
        $res['msg'] = esc_html__('Successfully updated', 'mihanpanel');
        $res['status'] = 200;
        self::send_res($res);
    }
    # TODO: Delete this line
    static function devmode_handle_live_edit_tabs_fields_edit_item_content()
    {
        self::checkNonce('mwpl_ajax_update_live_edit_options');
        $res = [
            'status' => 400,
            'msg' => __('Has error!', 'mihanpanel'),
        ];
        $itemID = isset($_POST['item_id']) ? intval(sanitize_text_field($_POST['item_id'])) : false;
        if (!$itemID) {
            $res['msg'] = esc_html__('Invalid data', 'mihanpanel');
            self::send_res($res);
        }
        $content = sanitize_textarea_field($_POST['content']);
        $needle = ['"', '\\'];
        $replacement = ["'", ''];
        $content = str_replace($needle, $replacement, $content);

        global $wpdb;
        $table_name = $wpdb->prefix . 'mihanpaneltabs';
        $update_res = $wpdb->update(
            $table_name,
            [
                'content' => $content,
            ],
            ['id' => $itemID],
        );
        if (!$update_res && $wpdb->last_error) {
            $res['msg'] = esc_html__('Has error in updating data', 'mihanpanel');
            self::send_res($res);
        }
        $res['msg'] = esc_html__('Successfully updated', 'mihanpanel');
        $res['status'] = 200;
        self::send_res($res);
    }

    static function handle_live_edit_tabs_fields_edit_item_content()
    {
        self::checkNonce('mwpl_ajax_update_live_edit_options');
        $res = [
            'status' => 400,
            'msg' => __('Has error!', 'mihanpanel'),
        ];
        $itemID = isset($_POST['item_id']) ? intval(sanitize_text_field($_POST['item_id'])) : false;
        if (!$itemID) {
            $res['msg'] = esc_html__('Invalid data', 'mihanpanel');
            self::send_res($res);
        }
        # HTHIS
        $data = isset($_POST['field_data']) ? $_POST['field_data'] : false;
        $field_data = [];
        parse_str($data, $field_data);
        $link_or_content = isset($field_data['linkorcontent']) ? $field_data['linkorcontent'] : false;
        if ($link_or_content === 'optionlink') {
            $link = isset($field_data['link']) ? $field_data['link'] : false;
        } elseif ($link_or_content === 'option_shortcode') {
            $content = isset($field_data['shortcode_list']) ? $field_data['shortcode_list'] : false;
            if ($content && $content === 'mwp_custom_shortcode') {
                $content = isset($field_data['custom_shortcode_value']) ? $field_data['custom_shortcode_value'] : false;
            }
        } else {
            $content = isset($field_data['item_content']) ? $field_data['item_content'] : false;
            $needle = ['"', '\\'];
            $replacement = ["'", ''];
            $content = str_replace($needle, $replacement, $content);
        }
        if (!$link && !$content) {
            $res['msg'] = esc_html__('Missing Data', 'mihanpanel');
            self::send_res($res);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'mihanpaneltabs';
        $update_res = $wpdb->update(
            $table_name,
            [
                'link' => $link,
                'content' => isset($content) ? $content : '',
            ],
            ['id' => $itemID],
        );
        if (!$update_res && $wpdb->last_error) {
            $res['msg'] = esc_html__('Has error in updating data', 'mihanpanel');
            self::send_res($res);
        }
        $res['msg'] = esc_html__('Successfully updated', 'mihanpanel');
        $res['status'] = 200;
        self::send_res($res);
    }
    static function handle_live_edit_tabs_fields_add_new_item()
    {
        self::checkNonce('mwpl_ajax_update_live_edit_options');
        $res = [
            'status' => 400,
            'msg' => __('Has error!', 'mihanpanel'),
        ];
        $name = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : false;
        $priority = isset($_POST['index']) ? sanitize_text_field($_POST['index']) : false;
        $icon = isset($_POST['icon']) ? sanitize_text_field($_POST['icon']) : false;
        if (!$name) {
            $res['msg'] = esc_html__('Missing name data', 'mihanpanel');
            self::send_res($res);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'mihanpaneltabs';
        $insert_res = $wpdb->insert(
            $table_name,
            [
                'name' => $name,
                'priority' => $priority,
                'icon' => $icon,
            ],
        );
        if (!$insert_res) {
            $res['msg'] = esc_html__('Has error in insert data', 'mihanpanel');
            self::send_res($res);
        }
        $res['msg'] = esc_html__('Successfully inserted', 'mihanpanel');
        $res['item_id'] = $wpdb->insert_id;
        $res['status'] = 200;
        self::send_res($res);
    }
    static function handle_live_edit_tabs_fields_update_priorities()
    {
        self::checkNonce('mwpl_ajax_update_live_edit_options');
        $res = [
            'status' => 400,
            'msg' => __('Has error!', 'mihanpanel'),
        ];
        # HTHIS
        $data = isset($_POST['data']) ? $_POST['data'] : false;
        if (!$data) {
            self::send_res($res);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'mihanpaneltabs';
        foreach ($data as $index => $value) {
            $wpdb->update(
                $table_name,
                [
                    'priority' => intval($index)
                ],
                [
                    'id' => sanitize_text_field($value),
                ]
            );
        }
    }
    static function handle_live_edit_tabs_fields_delete_item()
    {
        self::checkNonce('mwpl_ajax_update_live_edit_options');
        $res = [
            'status' => 400,
            'msg' => __('Has error!', 'mihanpanel'),
        ];
        $itemId = isset($_POST['item_id']) ? sanitize_text_field($_POST['item_id']) : false;
        if (!$itemId) {
            self::send_res($res);
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'mihanpaneltabs';
        $deleteRes = $wpdb->delete($table_name, ['id' => $itemId]);
        if (!$deleteRes) {
            $res['msg'] = esc_html__('Has error in delete', 'mihanpanel');
            self::send_res($res);
        }
        $res['status'] = 200;
        self::send_res($res);
    }
    static function handle_delete_ghost_users()
    {
        $res = [
            'status' => 400,
            'msg' => __('Has error!', 'mihanpanel'),
        ];
        $userID = isset($_POST['current_id']) ? intval(sanitize_text_field($_POST['current_id'])) : false;
        if(!$userID)
        {
            $res['msg'] = __('Missing user id', 'mihanpanel');
            self::send_res($res);
        }
        global $wpdb;
        // get end user
        $res['end_user'] = isset($_POST['end_user']) ? intval(sanitize_text_field($_POST['end_user'])) : false;
        if(!$res['end_user'])
        {
            $res['log'][] = sprintf('---------- %s ----------', __('Start of delete process', 'mihanpanel'));
            $sql = "SELECT id FROM {$wpdb->users} ORDER BY id DESC LIMIT 1;";
            $res['end_user'] = $wpdb->get_var($sql);
        }

        if($userID > $res['end_user'])
        {
            $res['log'][] = sprintf('---------- %s ----------', __('End of delete process', 'mihanpanel'));
            $res['stop'] = true;
            self::send_res($res);
        }
        $user = get_user_by('id', $userID);
        if(!$user)
        {
            $res['log'][] = sprintf(__('User ID %s not exists', 'mihanpanel'), $userID);
            self::send_res($res);
        }
        $user_role = reset($user->roles);
        if($user_role !== 'subscriber')
        {
            $res['log'][] = sprintf(__('User ID [ %s ] has not subscriber role', 'mihanpanel'), $userID);
            self::send_res($res);
        }
        // check comments count
        $commentsCountSql = "SELECT count(comment_ID) FROM {$wpdb->comments} WHERE user_id=%d";
        $commentsCount = $wpdb->get_var($wpdb->prepare($commentsCountSql, $userID));
        if($commentsCount > 0)
        {
            self::send_res($res);
        }
        
        // check orders count if woocommerce is active
        if(\mihanpanel\app\tools::is_woocommerce_active())
        {
            $ordersCount = wc_get_customer_order_count($userID);
            if($ordersCount > 0)
            {
                self::send_res($res);
            }
        }
        
        // check posts count
        $postsCount = count_user_posts($userID);
        if($postsCount > 0)
        {
            self::send_res($res);
        }
        // delete user
        if(!wp_delete_user($userID))
        {
            $res['log'][] = sprintf(__('Has error in delete user id %s', 'mihanpanel'), $userID);
            self::send_res($res);
        }
        $res['log'][] = sprintf(__('User ID %s was deleted!', 'mihanpanel'), $userID);
        self::send_res($res);
    }

    public static function handle_update_user_phone_status(){
        check_ajax_referer('mw_nonce', 'nonce');
        $user_id = sanitize_text_field($_POST['user_id']);
        $status = sanitize_text_field($_POST['status']);
        $phone = sanitize_text_field($_POST['phone']);
        $success = false;

        if(current_user_can('edit_users') && tools::getValidatedPhoneNumber($phone)){
            $is_active = users::has_valid_phone($user_id);
            if($status === 'activate'){
                if(!$is_active){
                    if(!users::can_use_this_phone($phone, $user_id)){
                        wp_send_json_error([
                            'msg' => __('The mobile number is already used.', 'mihanpanel')
                        ]);
                    }

                    $set_phone = users::set_user_phone($user_id, $phone);
                    $set = users::set_has_valid_phone($user_id);
                    if($set){
                        $success = true;
                    }
                }
            } elseif($status === 'deactivate'){
                if($is_active){
                    $set = users::remove_has_valid_phone($user_id);
                    if($set){
                        $success = true;
                    }
                }
            }
            if($success){
                wp_send_json_success();
            } else {
                wp_send_json_error();
            }
        } else {
            wp_send_json_error([
                    'msg' => __('Mobile number must start with zero.', 'mihanpanel')
            ]);
        }
    }

    public static function handle_check_mobile_in_register_process(){
        $mobile = isset($_POST['phone']) ? tools::getValidatedPhoneNumber($_POST['phone']) : false;
        $res = ['code' => 400];

        if (!$mobile){
            $res['msg'] = __('Invalid phone number!', 'mihanpanel');
        } elseif(users::get_user_by_phone($mobile)) {
            $res['msg'] = __('This phone number is used in another account.', 'mihanpanel');
        }

        if (!empty($res) && isset($res['msg'])){
            \mihanpanel\app\tools::send_json_response($res);
        }
    }

    static function handle_sms_login_check_phone()
    {
        self::checkNonce('mwpl_login_nonce', 'nonce');
        $res['code'] = 409;
        $phone = isset($_POST['phone']) && $_POST['phone'] ? sanitize_text_field($_POST['phone']) : false;

        do_action('mwpl_sms_login_start_process_response', $res);

        if(!$phone)
        {
            $res['msg'] = esc_html__('Please fill phone number field', 'mihanpanel');
            self::send_res($res);
        }

        $phone = tools::getValidatedPhoneNumber($phone);
        if(!$phone)
        {
            $res['msg'] = esc_html__('Invalid phone number', 'mihanpanel');
            self::send_res($res);
        }

        // get user by phone
        $user = users::get_user_by_phone($phone);
        if(!$user)
        {
            $res['msg'] = esc_html__('No any user found by this phone', 'mihanpanel');
            self::send_res($res);
        }
        $accountStatus = \mihanpanel\app\users::is_active_account($user->ID);
        if(!$accountStatus)
        {
            $res['msg'] = esc_html__('Your account is disable!', 'mihanpanel');
            self::send_res($res);
        }
        
        // store userID
        \mihanpanel\pro\app\login_system::storeUserID($user->ID);

        // check 2fa otp
        if(\mihanpanel\app\options::get_smart_login_2fa_status() && \mihanpanel\app\users::isActive2FA($user->ID))
        {
            $res['handle_2fa'] = true;
        }
        

        // check has valid phone
        $userPhone = \mihanpanel\pro\app\users::has_valid_phone($user->ID) ? \mihanpanel\pro\app\users::get_user_phone($user->ID) : false;
        if(!$userPhone)
        {
            $res['msg'] = esc_html__('Phone number for this user is invalid', 'mihanpanel');
            self::send_res($res);
        }
        $userDisplayPhone = tools::getPhoneNumberForDisplayMode($userPhone);

        // check has valid code
        if($codeData = \mihanpanel\pro\app\login_system::hasValidDynamicCode($user->ID))
        {
            $res['msg'] = __('Dynamic code was sent for you!', 'mihanpanel');
            $res['code'] = 200;
            $res['expiration'] = date('Y-m-d H:i:s', $codeData['expiration']);
            $res['change_phone_text'] = sprintf(__('This is not my phone %s, Change phone', 'mihanpanel'), $userDisplayPhone);
            self::send_res($res);
        }

        // set new dynamic code
        $setNewCodeData = \mihanpanel\pro\app\login_system::setUserDynamicCode($user->ID);
        if(!$setNewCodeData)
        {
            $res['msg'] = esc_html__("Dynamic code did not create for your account!", 'mihanpanel');
            self::send_res($res);
        }

        // send dynamic code to mobile
        $sendSmsResult = \mihanpanel\pro\app\sms::send_dynamic_code_sms($user, $setNewCodeData['code']);
        if(!$sendSmsResult || !isset($sendSmsResult['status']) || $sendSmsResult['status'] !== 200)
        {
            // remove dynamic code from session storage
            \mihanpanel\pro\app\login_system::deleteUserDynamicCode($user->ID);
            $res['msg'] = esc_html__('Has problem in send sms process', 'mihanpanel');
            self::send_res($res);
        }

        do_action('mwpl_sms_login_end_process_response', $res);

        $res['code'] = 200;
        $res['msg'] = esc_html__('Dynamic code was sent to your phone', 'mihanpanel');
        $res['expiration'] = date('Y-m-d H:i:s', $setNewCodeData['expiration']);
        $res['change_phone_text'] = sprintf(__('This is not my phone %s, Change phone', 'mihanpanel'), $userDisplayPhone);

        self::send_res($res);
    }

    static function handle_sms_login_check_code_and_login()
    {
        self::checkNonce('mwpl_login_nonce', 'nonce');
        $res['code'] = 409;
        $code = isset($_POST['code']) && $_POST['code'] ? \mihanpanel\app\tools::convertNumberLocale(sanitize_text_field($_POST['code'])) : false;
        if(!$code)
        {
            $res['msg'] = esc_html__('Please fill code field', 'mihanpanel');
            self::send_res($res);
        }

        // get stored userID
        $userID = \mihanpanel\pro\app\login_system::getStoredUserID();

        // validate code
        $codeValidation = \mihanpanel\pro\app\login_system::validateDynamicCode($code, $userID);
        if(!$codeValidation)
        {
            $res['msg'] = esc_html__('Invalid code value', 'mihanpanel');
            do_action('mwpl_login/sms_login/wrong_dynamic_code_value', $res);
            self::send_res($res);
        }

        // handle 2fa otp
        if(\mihanpanel\app\options::get_smart_login_2fa_status() && \mihanpanel\app\users::isActive2FA($userID))
        {
            $otpCodeValue = isset($_POST['two_factor_authentication_code']) && $_POST['two_factor_authentication_code'] ? sanitize_text_field($_POST['two_factor_authentication_code']) : false;
            if(!\mihanpanel\app\google_otp::verifyOtpCode($otpCodeValue, $userID))
            {
                $res['msg'] = __('2FA code is invalid', 'mihanpanel');
                self::send_res($res);
            }
        }

        // remove stored userID
        \mihanpanel\pro\app\login_system::deleteStoredUserID();

        // remove stored dynamic code
        \mihanpanel\pro\app\login_system::deleteUserDynamicCode($userID);
        
        // handle login process
        \mihanpanel\app\login::doLogin($userID, true);
        $res['redirect_to'] = apply_filters('mwpl_after_login_redirect_url', \mihanpanel\app\options::get_panel_url(), $userID);
        $res['msg'] = esc_html__('Successfully logged in...', 'mihanpanel');
        $res['code'] = 200;
        
        self::send_res($res);
    }

    static function handle_smart_login_form_check_username()
    {
        self::checkNonce('mwpl_login_nonce', 'nonce');
        $res['code'] = 409;
        $username = isset($_POST['username']) && $_POST['username'] ? sanitize_text_field($_POST['username']) : false;

        do_action('mwpl_smart_login_start_process_response', $res);

        if(!$username)
        {
            $res['msg'] = esc_html__('Please fill username field', 'mihanpanel');
            self::send_res($res);
        }
        // check is username found
        if(username_exists($username))
        {
            $user = get_user_by('login', $username);
            $accountStatus = \mihanpanel\app\users::is_active_account($user->ID);
            if(!$accountStatus)
            {
                $res['msg'] = esc_html__('Your account is disable!', 'mihanpanel');
                self::send_res($res);
            }
            // check 2fa otp
            if(\mihanpanel\app\options::get_smart_login_2fa_status() && \mihanpanel\app\users::isActive2FA($user->ID))
            {
                $res['handle_2fa'] = true;
            }
            // store userID
            \mihanpanel\pro\app\login_system::storeUserID($user->ID);

            do_action('mwpl_smart_login_username_process_response', $res);

            $res['login_type'] = 'username';
            $res['code'] = 200;

            self::send_res($res);
        }

        // check is email found
        if(filter_var($username, FILTER_VALIDATE_EMAIL))
        {
            $user = \mihanpanel\pro\app\users::get_user_by_email($username);
            if($user)
            {
                // check account status
                if(!\mihanpanel\app\users::is_active_account($user->ID))
                {
                    $res['msg'] = esc_html__('Your account is disable!', 'mihanpanel');
                    self::send_res($res);
                }
                // check 2fa otp
                if(\mihanpanel\app\options::get_smart_login_2fa_status() && \mihanpanel\app\users::isActive2FA($user->ID))
                {
                    $res['handle_2fa'] = true;
                }
                
                // store userID
                \mihanpanel\pro\app\login_system::storeUserID($user->ID);

                do_action('mwpl_smart_login_username_process_response', $res);
                
                $res['login_type'] = 'email';
                $res['code'] = 200;

                self::send_res($res);
            }else{
                #TODO create new user via email

                if(!options::canUsingEmailInSmartLoginForRegister())
                {
                    $res['msg'] = esc_html__('Can not using email for registeration', 'mihanpanel');
                    $res['code'] = 400;
                    self::send_res($res);
                }
                
                $tempUserData = [
                    'type' => 'email',
                    'value' => $username,
                ];

                \mihanpanel\pro\app\login_system::storeTempUserData($tempUserData);

                // check has valid code
                if($codeData = \mihanpanel\pro\app\login_system::hasTempUserValidDynamicCode())
                {
                    $res['msg'] = __('Dynamic code was sent for you!', 'mihanpanel');
                    $res['code'] = 200;
                    $res['register'] = true;
                    $res['expiration'] = date('Y-m-d H:i:s', $codeData['expiration']);
                    self::send_res($res);
                }

                // set new code
                $codeData = \mihanpanel\pro\app\login_system::generateAndStoreNewCodeDataForTempUser();
                if(!$codeData)
                {
                    $res['msg'] = esc_html__("Dynamic code did not create for your account!", 'mihanpanel');
                    self::send_res($res);
                }

                // send dynamic code to email
                $subject = \mihanpanel\pro\app\options::get_dynamic_code_email_subject();
                $message = \mihanpanel\pro\app\email::getFilteredDynamicCodeEmailContent($codeData['code']);
                $send_res = \mihanpanel\app\email::sendEmail($username, $subject, $message);
                if ($send_res['status'] !== 200) {
                    \mihanpanel\pro\app\login_system::deleteTempUserCodeData();
                    $res['msg'] = esc_html__('Has problem in send dynamic code to your email', 'mihanpanel');
                    die(json_encode($res));
                }
                $res['register'] = true;
                $res['expiration'] = date('Y-m-d H:i:s', $codeData['expiration']);
                $res['msg'] = esc_html__('Dynamic code was sent to your email', 'mihanpanel');
                $res['code'] = 200;
                self::send_res($res);
            }
        }

        // check is phone number found
        // phone number validation
        $validPhone = \mihanpanel\pro\app\tools::getValidatedPhoneNumber($username);
        if(!$validPhone)
        {
            do_action('mwpl_login/smart_login/no_user_found', $res);
            $res['msg'] = esc_html__('No any user found. Please register by your phone number first.', 'mihanpanel');
            self::send_res($res);
        }

        // check is user exists with phone number
        $user = \mihanpanel\pro\app\users::get_user_by_phone($validPhone);
        if($user)
        {
            // check account status
            if(!\mihanpanel\app\users::is_active_account($user->ID))
            {
                $res['msg'] = esc_html__('Your account is disable!', 'mihanpanel');
                self::send_res($res);
            }
            // check 2fa otp
            if(\mihanpanel\app\options::get_smart_login_2fa_status() && \mihanpanel\app\users::isActive2FA($user->ID))
            {
                $res['handle_2fa'] = true;
            }
            
            // store userID
            \mihanpanel\pro\app\login_system::storeUserID($user->ID);
            
            $res['login_type'] = 'phone';
            $res['code'] = 200;
            self::send_res($res);
        }

        // create user via phone number
        if(!options::canUsingPhoneInSmartLoginForRegister())
        {
            $res['msg'] = esc_html__('Can not using phone number for registeration', 'mihanpanel');
            $res['code'] = 400;
            self::send_res($res);
        }

        $tempUserData = [
            'type' => 'phone',
            'value' => $validPhone,
        ];
        \mihanpanel\pro\app\login_system::storeTempUserData($tempUserData);

        // check has valid code
        if(\mihanpanel\pro\app\login_system::hasTempUserValidDynamicCode())
        {
            $res['msg'] = __('Dynamic code was sent for you!', 'mihanpanel');
            self::send_res($res);
        }
        // set new dynamic code
        $codeData = \mihanpanel\pro\app\login_system::generateAndStoreNewCodeDataForTempUser();
        if(!$codeData)
        {
            $res['msg'] = esc_html__("Dynamic code did not create for your account!", 'mihanpanel');
            self::send_res($res);
        }

        // send dynamic code to mobile
        $sendSmsResult = \mihanpanel\pro\app\sms::send_smart_login_code($validPhone, $codeData['code']);
        if(!$sendSmsResult || !isset($sendSmsResult['status']) || $sendSmsResult['status'] !== 200)
        {
            // remove dynamic code from session storage
            \mihanpanel\pro\app\login_system::deleteTempUserCodeData();
            $res['msg'] = esc_html__('Has problem in send sms process', 'mihanpanel');
            self::send_res($res);
        }

        do_action('mwpl_smart_login_end_process_response', $res);

        $res['register'] = true;
        $res['msg'] = esc_html__('Dynamic code was sent to your phone', 'mihanpanel');
        $res['expiration'] = date('Y-m-d H:i:s', $codeData['expiration']);
        $res['code'] = 200;
        self::send_res($res);
    }

    static function handle_smart_login_form_check_password_and_login()
    {
        self::checkNonce('mwpl_login_nonce', 'nonce');
        $res['code'] = 409;
        $password = isset($_POST['password']) && $_POST['password'] ? sanitize_text_field($_POST['password']) : false;

        do_action('mwpl_smart_login_start_password_process_response', $res);

        if(!$password)
        {
            $res['msg'] = esc_html__('Missing password value', 'mihanpanel');
            self::send_res($res);
        }
        $userID = \mihanpanel\pro\app\login_system::getStoredUserID();
        if(!$userID)
        {
            $res['msg'] = __('Missing user data', 'mihanpanel');
            self::send_res($res);
        }
        $user = get_user_by('id', $userID);
        if(!$user)
        {
            do_action('mwpl_login/smart_login/wrong_username', $res);
            $res['msg'] = __('Not found any user with your data', 'mihanpanel');
            self::send_res($res);
        }
        // check password
        if(!wp_check_password($password, $user->user_pass, $user->ID))
        {
            $res['msg'] = esc_html__('Invalid password', 'mihanpanel');
            do_action('mwpl_login/smart_login/wrong_password', $res);
            self::send_res($res);
        }

        // handle 2fa otp
        if(\mihanpanel\app\options::get_smart_login_2fa_status() && \mihanpanel\app\users::isActive2FA($user->ID))
        {
            $otpCodeValue = isset($_POST['two_factor_authentication_code']) && $_POST['two_factor_authentication_code'] ? sanitize_text_field($_POST['two_factor_authentication_code']) : false;
            if(!\mihanpanel\app\google_otp::verifyOtpCode($otpCodeValue, $user->ID))
            {
                $res['msg'] = __('2FA code is invalid', 'mihanpanel');
                self::send_res($res);
            }
        }

        do_action('mwpl_smart_login_end_password_process_response', $res);

        // handle login process
        \mihanpanel\app\login::doLogin($user->ID, true);
        $res['redirect_to'] = apply_filters('mwpl_after_login_redirect_url', \mihanpanel\app\options::get_panel_url(), $user->ID);
        $res['msg'] = esc_html__('Successfully logged in...', 'mihanpanel');
        $res['code'] = 200;
        self::send_res($res);
    }
    
    static function handle_smart_login_form_check_dynamic_code_and_register()
    {
        self::checkNonce('mwpl_login_nonce', 'nonce');
        $res['code'] = 409;
        $code = isset($_POST['code']) && $_POST['code'] ? \mihanpanel\app\tools::convertNumberLocale(sanitize_text_field($_POST['code'])) : false;

        do_action('mwpl_smart_login_start_dynamic_code_process_response', $res);

        if(!$code)
        {
            $res['msg'] = esc_html__('Missing code value', 'mihanpanel');
            self::send_res($res);
        }
        // check is register process or login process
        $storedUserID = \mihanpanel\pro\app\login_system::getStoredUserID();
        if($storedUserID)
        {
            // login process
            // get user data
            $user = get_user_by('id', $storedUserID);
            if(!$user)
            {
                $res['msg'] = __('Not found any user with your data', 'mihanpanel');
                self::send_res($res);
            }

            // validate code
            $codeValidation = \mihanpanel\pro\app\login_system::validateDynamicCode($code, $user->ID);
            if(!$codeValidation)
            {
                $res['msg'] = esc_html__('Invalid code value', 'mihanpanel');
                do_action('mwpl_login/smart_login/wrong_dynamic_code_value', $res);
                self::send_res($res);
            }

            // handle 2fa otp
            if(\mihanpanel\app\options::get_smart_login_2fa_status() && \mihanpanel\app\users::isActive2FA($user->ID))
            {
                $otpCodeValue = isset($_POST['two_factor_authentication_code']) && $_POST['two_factor_authentication_code'] ? sanitize_text_field($_POST['two_factor_authentication_code']) : false;
                if(!\mihanpanel\app\google_otp::verifyOtpCode($otpCodeValue, $user->ID))
                {
                    $res['msg'] = __('2FA code is invalid', 'mihanpanel');
                    self::send_res($res);
                }
            }
            // remove stored userID
            \mihanpanel\pro\app\login_system::deleteStoredUserID();

            // remove stored dynamic code
            \mihanpanel\pro\app\login_system::deleteUserDynamicCode($user->ID);
            
            // handle login process
            \mihanpanel\app\login::doLogin($user->ID, true);
            $res['redirect_to'] = apply_filters('mwpl_after_login_redirect_url', \mihanpanel\app\options::get_panel_url(), $user->ID);
            $res['msg'] = esc_html__('Successfully logged in...', 'mihanpanel');
            $res['code'] = 200;
            
            self::send_res($res);

        }else{
            // get userClue
            $userDataClue = \mihanpanel\pro\app\login_system::getTempUserData();
            if(!$userDataClue)
            {
                $res['msg'] = __('Missing user data', 'mihanpanel');
                self::send_res($res);
            }
            switch($userDataClue['type'])
            {
                case 'email':
                    // validate code
                    if(!\mihanpanel\pro\app\login_system::validateTempUserDynamicCode($code))
                    {
                        $res['msg'] = __('Invalid dynamic code value', 'mihanpanel');
                        do_action('mwpl_login/smart_login/wrong_dynamic_code_value', $res);
                        self::send_res($res);
                    }

                    // create user via email
                    $newUserID = \mihanpanel\pro\app\login_system::createNewUserViaEmail($userDataClue['value']);
                    if(!$newUserID)
                    {
                        $res['msg'] = __('Has error in create your account', 'mihanpanel');
                        self::send_res($res);
                    }
                    session::store('required_change_password', 1);
                    \mihanpanel\app\notice::add_multiple_notice('primary4', __('Please set new password for account', 'mihanpanel'));

                    // handle login process
                    \mihanpanel\app\login::doLogin($newUserID, true);
                    $res['redirect_to'] = apply_filters('mwpl_after_login_redirect_url', \mihanpanel\app\options::get_panel_url(), $newUserID);
                    $res['msg'] = esc_html__('Successfully logged in...', 'mihanpanel');
                    $res['code'] = 200;
                    break;

                case 'phone';
                    // validate code
                    if(!\mihanpanel\pro\app\login_system::validateTempUserDynamicCode($code))
                    {
                        $res['msg'] = __('Invalid dynamic code value', 'mihanpanel');
                        do_action('mwpl_login/smart_login/wrong_dynamic_code_value', $res);
                        self::send_res($res);
                    }

                    // create user via phone number
                    $newUserID = \mihanpanel\pro\app\login_system::createNewUserViaPhoneNumber($userDataClue['value']);
                    if(!$newUserID)
                    {
                        $res['msg'] = __('Has error in create your account', 'mihanpanel');
                        self::send_res($res);
                    }
                    session::store('required_change_password', 1);
                    \mihanpanel\app\notice::add_multiple_notice('primary4', __('Please set new password for account', 'mihanpanel'));

                    do_action('mwpl_smart_login_end_dynamic_code_process_response', $res);

                    // handle login process
                    \mihanpanel\app\login::doLogin($newUserID, true);
                    $res['redirect_to'] = apply_filters('mwpl_after_login_redirect_url', \mihanpanel\app\options::get_panel_url(), $newUserID);
                    $res['msg'] = esc_html__('Successfully logged in...', 'mihanpanel');
                    $res['code'] = 200;
                    break;
            }
            self::send_res($res);
        }
        
    }

    static function handle_smart_login_form_send_dynamic_code_for_login()
    {
        self::checkNonce('mwpl_login_nonce', 'nonce');
        $res['code'] = 409;

        // get stored userID
        $userID = \mihanpanel\pro\app\login_system::getStoredUserID();
        $user = get_user_by('id', $userID);

        do_action('mwpl_smart_login_start_send_dynamic_code_process_response', $res);

        if(!$user)
        {
            $res['msg'] = __('User data not found', 'mihanpanel');
            self::send_res($res);
        }

        $userPhone = \mihanpanel\pro\app\users::has_valid_phone($userID) ? \mihanpanel\pro\app\users::get_user_phone($userID) : false;
        if(!$userPhone && !$user->user_email)
        {
            $res['msg'] = esc_html__('You are have not valid phone number or email address', 'mihanpanel');
            self::send_res($res);
        }
        

        $userDataClue = [
            'driver' => $userPhone ? 'phone' : 'email',
            'value' => $userPhone ? $userPhone : $user->user_email,
        ];
        $userDisplayPhone = tools::getPhoneNumberForDisplayMode($userPhone);
        $userDisplayEmail = $user->email ? tools::getEmailValueForDisplayMode($user->email) : '';

        // check has valid code
        if($codeData = \mihanpanel\pro\app\login_system::hasValidDynamicCode($userID))
        {
            $res['msg'] = __('Dynamic code was sent for you!', 'mihanpanel');
            $res['code'] = 200;
            $res['expiration'] = date('Y-m-d H:i:s', $codeData['expiration']);
            if($userDataClue['driver'] == 'phone')
            {
                $res['change_phone_text'] = sprintf(__('This is not my phone %s, Change phone', 'mihanpanel'), $userDisplayPhone);
            }else{
                $res['change_phone_text'] = sprintf(__('This is not my email %s, Change email address', 'mihanpanel'), $userDisplayEmail);
            }
            self::send_res($res);
        }

        
        // set new dynamic code
        $setNewCodeData = \mihanpanel\pro\app\login_system::setUserDynamicCode($userID);
        if(!$setNewCodeData)
        {
            $res['msg'] = esc_html__("Dynamic code did not create for your account!", 'mihanpanel');
            self::send_res($res);
        }
        if($userDataClue['driver'] == 'phone')
        {
            // send dynamic code to mobile
            $sendSmsResult = \mihanpanel\pro\app\sms::send_dynamic_code_sms($user, $setNewCodeData['code']);
            if(!$sendSmsResult || !isset($sendSmsResult['status']) || $sendSmsResult['status'] !== 200)
            {
                // remove dynamic code from session storage
                \mihanpanel\pro\app\login_system::deleteUserDynamicCode($user->ID);
                $res['msg'] = esc_html__('Has problem in send sms process', 'mihanpanel');
                self::send_res($res);
            }
            $res['msg'] = esc_html__('Dynamic code was sent to your phone', 'mihanpanel');
            $res['change_phone_text'] = sprintf(__('This is not my phone %s, Change phone', 'mihanpanel'), $userDisplayPhone);
        }else{
            // send dynamic code to email
            $subject = \mihanpanel\pro\app\options::get_dynamic_code_email_subject();
            $message = \mihanpanel\pro\app\email::getFilteredDynamicCodeEmailContent($setNewCodeData['code']);
            $send_res = \mihanpanel\app\email::sendEmail($user->user_email, $subject, $message);
            if ($send_res['status'] !== 200) {
                \mihanpanel\pro\app\login_system::deleteUserDynamicCode($user->ID);
                $res['msg'] = esc_html__('Has problem in send dynamic code to your email', 'mihanpanel');
                die(json_encode($res));
            }
            $res['msg'] = esc_html__('Dynamic code was sent to your email', 'mihanpanel');
            $res['change_phone_text'] = sprintf(__('This is not my email %s, Change email address', 'mihanpanel'), $userDisplayEmail);
        }

        do_action('mwpl_smart_login_end_send_dynamic_code_process_response', $res);
        
        $res['code'] = 200;
        $res['expiration'] = date('Y-m-d H:i:s', $setNewCodeData['expiration']);
        self::send_res($res);
    }
    static function handle_sms_login_resend_dynamic_code()
    {
        // check nonce
        self::checkNonce('mwpl_login_nonce', 'nonce');
        $res['code'] = 409;

        // get stored user id
        $storedUserID = \mihanpanel\pro\app\login_system::getStoredUserID();

        $user = get_user_by('id', $storedUserID);
        if(!$user)
        {
            $res['msg'] = __('User data not found', 'mihanpanel');
            self::send_res($res);
        }

        // check account status
        $accountStatus = \mihanpanel\app\users::is_active_account($user->ID);
        if(!$accountStatus)
        {
            $res['msg'] = esc_html__('Your account is disable!', 'mihanpanel');
            self::send_res($res);
        }

        // check has valid phone
        $userPhone = \mihanpanel\pro\app\users::has_valid_phone($user->ID) ? \mihanpanel\pro\app\users::get_user_phone($user->ID) : false;
        if(!$userPhone)
        {
            $res['msg'] = esc_html__('Phone number for this user is invalid', 'mihanpanel');
            self::send_res($res);
        }

        // check has valid code
        if($codeData = \mihanpanel\pro\app\login_system::hasValidDynamicCode($user->ID))
        {
            $res['msg'] = __('Dynamic code was sent for you!', 'mihanpanel');
            $res['code'] = 200;
            $res['expiration'] = date('Y-m-d H:i:s', $codeData['expiration']);
            self::send_res($res);
        }

        // set new dynamic code
        $setNewCodeData = \mihanpanel\pro\app\login_system::setUserDynamicCode($user->ID);
        if(!$setNewCodeData)
        {
            $res['msg'] = esc_html__("Dynamic code did not create for your account!", 'mihanpanel');
            self::send_res($res);
        }

        // send dynamic code to mobile
        $sendSmsResult = \mihanpanel\pro\app\sms::send_dynamic_code_sms($user, $setNewCodeData['code']);
        if(!$sendSmsResult || !isset($sendSmsResult['status']) || $sendSmsResult['status'] !== 200)
        {
            // remove dynamic code from session storage
            \mihanpanel\pro\app\login_system::deleteUserDynamicCode($user->ID);
            $res['msg'] = esc_html__('Has problem in send sms process', 'mihanpanel');
            self::send_res($res);
        }
        $res['code'] = 200;
        $res['msg'] = esc_html__('Dynamic code was sent to your phone', 'mihanpanel');
        $res['expiration'] = date('Y-m-d H:i:s', $setNewCodeData['expiration']);
        self::send_res($res);
    }
    static function handle_smart_login_resend_dynamic_code()
    {
        // check nonce
        self::checkNonce('mwpl_login_nonce', 'nonce');
        $res['code'] = 409;
        $whiteList = ['login', 'register'];
        $dynamic_code_for = isset($_POST['dynamic_code_for']) && in_array($_POST['dynamic_code_for'], $whiteList) ? sanitize_text_field($_POST['dynamic_code_for']) : false;
        
        $res['code_for'] = $dynamic_code_for;
        // get user data
        // check user has data or get data from tmp
        if($dynamic_code_for == 'login')
        {
            // code for login
            $storedUserID = \mihanpanel\pro\app\login_system::getStoredUserID();
            $user = get_user_by('id', $storedUserID);
            if(!$user)
            {
                $res['msg'] = __('User data not found', 'mihanpanel');
                self::send_res($res);
            }

            $userPhone = \mihanpanel\pro\app\users::has_valid_phone($user->ID) ? \mihanpanel\pro\app\users::get_user_phone($user->ID) : false;
            if(!$userPhone && !$user->user_email)
            {
                $res['msg'] = esc_html__('You are have not valid phone number or email address', 'mihanpanel');
                self::send_res($res);
            }

            $userDataClue = [
                'driver' => $userPhone ? 'phone' : 'email',
                'value' => $userPhone ? $userPhone : $user->user_email,
            ];
            $userDisplayPhone = tools::getPhoneNumberForDisplayMode($userPhone);
            $userDisplayEmail = $user->email ? tools::getEmailValueForDisplayMode($user->email) : '';

            // check has valid code
            if($codeData = \mihanpanel\pro\app\login_system::hasValidDynamicCode($user->ID))
            {
                $res['msg'] = __('Dynamic code was sent for you!', 'mihanpanel');
                $res['code'] = 200;
                $res['expiration'] = date('Y-m-d H:i:s', $codeData['expiration']);
                self::send_res($res);
            }
            
            // set new dynamic code
            $setNewCodeData = \mihanpanel\pro\app\login_system::setUserDynamicCode($user->ID);
            if(!$setNewCodeData)
            {
                $res['msg'] = esc_html__("Dynamic code did not create for your account!", 'mihanpanel');
                self::send_res($res);
            }

            // send code to mobile or email
            if($userDataClue['driver'] == 'phone')
            {
                // send dynamic code to mobile
                $sendSmsResult = \mihanpanel\pro\app\sms::send_dynamic_code_sms($user, $setNewCodeData['code']);
                if(!$sendSmsResult || !isset($sendSmsResult['status']) || $sendSmsResult['status'] !== 200)
                {
                    // remove dynamic code from session storage
                    \mihanpanel\pro\app\login_system::deleteUserDynamicCode($user->ID);
                    $res['msg'] = esc_html__('Has problem in send sms process', 'mihanpanel');
                    self::send_res($res);
                }
                $res['msg'] = esc_html__('Dynamic code was sent to your phone', 'mihanpanel');
                $res['change_phone_text'] = sprintf(__('This is not my phone %s, Change phone', 'mihanpanel'), $userDisplayPhone);
            }else{
                // send dynamic code to email
                $subject = \mihanpanel\pro\app\options::get_dynamic_code_email_subject();
                $message = \mihanpanel\pro\app\email::getFilteredDynamicCodeEmailContent($setNewCodeData['code']);
                $send_res = \mihanpanel\app\email::sendEmail($user->user_email, $subject, $message);
                if ($send_res['status'] !== 200) {
                    \mihanpanel\pro\app\login_system::deleteUserDynamicCode($user->ID);
                    $res['msg'] = esc_html__('Has problem in send dynamic code to your email', 'mihanpanel');
                    die(json_encode($res));
                }
                $res['msg'] = esc_html__('Dynamic code was sent to your email', 'mihanpanel');
                $res['change_phone_text'] = sprintf(__('This is not my email %s, Change email address', 'mihanpanel'), $userDisplayEmail);
            }
            $res['code'] = 200;
            $res['expiration'] = date('Y-m-d H:i:s', $setNewCodeData['expiration']);
            self::send_res($res);
        }elseif($dynamic_code_for == 'register'){
            // get data from tmp
            $userDataClue = \mihanpanel\pro\app\login_system::getTempUserData();
            if(!$userDataClue)
            {
                $res['msg'] = __('Missing user data', 'mihanpanel');
                self::send_res($res);
            }
            
            // check has valid code
            if($codeData = \mihanpanel\pro\app\login_system::hasTempUserValidDynamicCode())
            {
                $res['msg'] = __('Dynamic code was sent for you!', 'mihanpanel');
                $res['code'] = 200;
                self::send_res($res);
            }
            
            // set new code
            $codeData = \mihanpanel\pro\app\login_system::generateAndStoreNewCodeDataForTempUser();
            if(!$codeData)
            {
                $res['msg'] = esc_html__("Dynamic code did not create for your account!", 'mihanpanel');
                self::send_res($res);
            }

            // send code to email or phone number
            if($userDataClue['type'] == 'email')
            {
                // send dynamic code to email
                $subject = \mihanpanel\pro\app\options::get_dynamic_code_email_subject();
                $message = \mihanpanel\pro\app\email::getFilteredDynamicCodeEmailContent($codeData['code']);
                $send_res = \mihanpanel\app\email::sendEmail($userDataClue['value'], $subject, $message);
                if ($send_res['status'] !== 200) {
                    \mihanpanel\pro\app\login_system::deleteTempUserCodeData();
                    $res['msg'] = esc_html__('Has problem in send dynamic code to your email', 'mihanpanel');
                    self::send_res($res);
                }
                $res['expiration'] = date('Y-m-d H:i:s', $codeData['expiration']);
                $res['msg'] = esc_html__('Dynamic code was sent to your email', 'mihanpanel');
                $res['code'] = 200;
                self::send_res($res);
            }elseif($userDataClue['type'] == 'phone')
            {
                // send dynamic code to mobile
                $sendSmsResult = \mihanpanel\pro\app\sms::send_smart_login_code($userDataClue['value'], $codeData['code']);
                if(!$sendSmsResult || !isset($sendSmsResult['status']) || $sendSmsResult['status'] !== 200)
                {
                    // remove dynamic code from session storage
                    \mihanpanel\pro\app\login_system::deleteTempUserCodeData();
                    $res['msg'] = esc_html__('Has problem in send sms process', 'mihanpanel');
                    self::send_res($res);
                }
                $res['msg'] = esc_html__('Dynamic code was sent to your phone', 'mihanpanel');
                $res['expiration'] = date('Y-m-d H:i:s', $codeData['expiration']);
                $res['code'] = 200;
                self::send_res($res);
            }

            
        }
    }

    public static function handle_generate_custom_captcha_token(){
        $type = options::get_custom_captcha_type();
        $letters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';
        if ($type == 'number') {
            $str = $numbers;
        } elseif($type == 'letters'){
            $str = $letters;
        } else {
            $str = $letters . $numbers;
        }
        $captchaText = substr(str_shuffle($str), 0, 6);
        wp_send_json(forms_captcha::generateCaptchaImage(85, 50, $captchaText));
    }
    static function dismissLicenseAdminNotice()
    {
        $oneWeek = 60 * 60 * 24 * 7;
        $time = time() + $oneWeek;
        $domain = parse_url(get_option('siteurl'), PHP_URL_HOST);
        $res['res'] = setcookie('mw_mihanpanel_dismiss_license_admin_notice', $time, $time, '/', $domain, true, true);
        die(json_encode($res));
    }

    static function handleProfessionalTabCheckAdminHasValidPhone()
    {
        $res = ['status' => 400];
        
        $userID = isset($_POST['user_id']) ? intval($_POST['user_id']) : false;
        if(!$userID)
        {
            $res['msg'] = __('Missing user id', 'mihanpanel');
            self::send_res($res);
        }
        $res['status'] = 200;
        $res['has_valid_phone'] = \mihanpanel\pro\app\users::has_valid_phone($userID) ? true : false;
        self::send_res($res);
    }
    static function handleProfessionalTabCheckAdminSetValidPhone()
    {
        $res = ['code' => 400];

        $userID = isset($_POST['user_id']) ? intval($_POST['user_id']) : false;
        if(!$userID)
        {
            $res['msg'] = __('Missing user id', 'mihanpanel');
            self::send_res($res);
        }
        
        $phoneValue = isset($_POST['value']) ? intval($_POST['value']) : false;
        $phoneValue = $phoneValue ? \mihanpanel\pro\app\tools::getValidatedPhoneNumber($phoneValue) : false;
        if(!$phoneValue)
        {
            $res['msg'] = __('Missing valid phone number', 'mihanpanel');
            self::send_res($res);
        }

        $userData = users::get_user_by_phone($phoneValue);
        if(!$userData)
        {
            // set phone for this user
            users::set_user_phone($userID, $phoneValue);
            users::set_has_valid_phone($userID);
        }else{
            // check is userID equal to userData
            if($userData->ID == $userID)
            {
                // phone is for this user
                // just set has valid phone
                users::set_has_valid_phone($userID);
            }else{
                // show error message
                $res['code'] = 400;
                $res['msg'] = __('This phone number is used by another user', 'mihanpanel');
                self::send_res($res);
            }
        }

        $res['code'] = 200;
        self::send_res($res);
    }
}
