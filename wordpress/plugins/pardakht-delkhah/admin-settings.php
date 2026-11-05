<?php
defined('ABSPATH') or die('No script kiddies please!');
?>
<?php
wp_enqueue_style('wp-color-picker');
wp_enqueue_script('wp-color-picker');
?>
<div class="wrap">
    <style type="text/css">
        .admin_fields {
            margin: 40px 0;
        }

        .cupri_gateways {
            border: 1px dotted #ddd;
            padding: 10px;
            margin: 5px;

        }

        .cupri_gateways .fields {
        }

        .cupri_gateways textarea {
            display: block;
        }
    </style>
    <h2></h2>
    <?php
    if (isset($_POST['cupri_general']) && !empty($_POST['cupri_general']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'cupri_general_settings_form')) {
        foreach ($_POST['cupri_general'] as $key => $value) {
            switch ($key) {
                case 'mobiles':
                    {
                        if (isset($_POST['cupri_general']['mobile'])) {
                            $_POST['cupri_general']['mobile'] = sanitize_text_field($_POST['cupri_general']['mobile']);
                        }
                    }
                    break;
                case 'active_sms_notification':
                    {
                        $_POST['cupri_general']['active_sms_notification'] = (int)($_POST['cupri_general']['active_sms_notification']);
                        if ($_POST['cupri_general']['active_sms_notification'] > 1) {
                            $_POST['cupri_general']['active_sms_notification'] = 1;
                        }

                    }
                    break;
                case 'active_email_notification':
                    {
                        $_POST['cupri_general']['active_email_notification'] = (int)($_POST['cupri_general']['active_email_notification']);
                        if ($_POST['cupri_general']['active_email_notification'] > 1) {
                            $_POST['cupri_general']['active_email_notification'] = 1;
                        }

                    }
                    break;
                case 'admin_sms_format':
                    {
                        $_POST['cupri_general']['admin_sms_format'] = sanitize_textarea_field($_POST['cupri_general']['admin_sms_format']);

                    }
                    break;
                case 'admin_email_format':
                    {
                        $_POST['cupri_general']['admin_email_format'] = wp_kses_post($_POST['cupri_general']['admin_email_format']);

                    }
                    break;
                case 'form_color':
                    {
                        $_POST['cupri_general']['form_color'] = sanitize_text_field($_POST['cupri_general']['form_color']);
                    }
                    break;
            }
        }

        if (!isset($_POST['cupri_general']['disable_default_style'])) {
            $_POST['cupri_general']['disable_default_style'] = 0;
        }
        if (!isset($_POST['cupri_general']['disable_readonly_predefined_values'])) {
            $_POST['cupri_general']['disable_readonly_predefined_values'] = 0;
        }
        if (!isset($_POST['cupri_general']['active_sms_notification'])) {
            $_POST['cupri_general']['active_sms_notification'] = 0;
        }
        if (!isset($_POST['cupri_general']['active_email_notification'])) {
            $_POST['cupri_general']['active_email_notification'] = 0;
        }
        if (!isset($_POST['cupri_general']['active_user_receipt_with_email'])) {
            $_POST['cupri_general']['active_user_receipt_with_email'] = 0;
        }

        $cupri_general = cupri_array_map_recursive('sanitize_textarea_field', $_POST['cupri_general']);
        update_option('cupri_general_settings', $cupri_general);
    }
    $cupri_general = cupri_get_opt();

    ?>
    <h1><?php _e('General Settings', 'cupri'); ?></h1>
    <hr>
    <form method="post">
        <?php
        wp_nonce_field('cupri_general_settings_form');
        ?>
        <div class="cupri_gateways">
            <h2> :: <?php _e('General', 'cupri'); ?></h2>
            <p class="admin_fields">
                <strong><?php _e('Form Page', 'cupri') ?></strong><br>
                <select name="cupri_general[form_page_id]" id="cupri_general[form_page_id]">
                    <option <?php selected($cupri_general['form_page_id'] ?? '', '', true); ?> value="">
                        -- <?php _e('select', 'cupri'); ?> --
                    </option>
                    <?php
                    $pages = get_pages();
                    foreach ($pages as $page) {
                        $option = '<option ' . selected($cupri_general['form_page_id'] ?? '', $page->ID, false) . ' value="' . $page->ID . '">';
                        $option .= $page->post_title;
                        $option .= '</option>';
                        echo $option;
                    }
                    ?>
                </select>
                <span class="desc"><?php _e('The page where the shortcode is placed', 'cupri'); ?></span>
            </p>
            <h2> :: <?php _e('Notifications', 'cupri'); ?></h2>
            <h5><?php _e('SMS Notifications', 'cupri'); ?></h5>
            <p class="admin_fields">
                <strong><?php _e('Admin Mobile(s)', 'cupri') ?></strong><br>
                <input value="<?php echo isset($cupri_general['mobiles']) ? esc_html($cupri_general['mobiles']) : ''; ?>"
                       type="text" name="cupri_general[mobiles]">
                <span class="desc"><?php _e('Seperate more mobiles with ,', 'cupri') ?></span>
            </p>
            <p class="admin_fields">
                <strong><?php _e('Active notification with sms ?', 'cupri') ?></strong><br>
                <input <?php isset($cupri_general['active_sms_notification']) ? (checked($cupri_general['active_sms_notification'], 1, true)) : ''; ?>
                        type="checkbox" value="1" name="cupri_general[active_sms_notification]">
                <span class="desc"><?php _e('You need to install and configure this plugin:', 'cupri'); ?>  <a
                            href="https://wordpress.org/plugins/wp-sms/" target="_blank">wp-sms</a></span>
            </p>
            <p class="admin_fields">
                <strong><?php _e('Send text sms , enter your mobile number', 'cupri') ?></strong>
                <input type="number" id="cupri_send_test_sms_mobile" placeholder="0900000000">

                <span data-nonce="<?= wp_create_nonce('cupri_send_test_sms_mobile_nonce'); ?>" class="button-secondary"
                      id="cupri_send_test_sms_btn"><?= _e('send', 'cupri'); ?></span>
                <br>
            </p>
            <p class="admin_fields">
                <strong><?php _e('SMS format', 'cupri') ?></strong><br>
                <textarea name="cupri_general[admin_sms_format]"
                          rows="6" cols="50"><?php echo esc_textarea($cupri_general['admin_sms_format']); ?></textarea>
                <span class="desc"><?php _e('Possible formats', 'cupri'); ?>: <?= cupri_get_possible_notification_variables() ?></span>
            </p>
            <p class="admin_fields">
                <strong><?php _e('Send SMS receipt to user mobile?', 'cupri') ?></strong><br>
                <input <?php checked($cupri_general['active_user_receipt_with_sms'], 1, true); ?> type="checkbox"
                                                                                                  value="1"
                                                                                                  name="cupri_general[active_user_receipt_with_sms]">
                <span class="desc"><?= __('If the user has entered their mobile, a payment receipt will be sent to them', 'cupri') ?></span>
            </p>

            <p class="admin_fields">
                <strong><?php _e('User SMS format', 'cupri') ?></strong><br>
                <textarea name="cupri_general[user_sms_format]"
                          rows="6" cols="50"><?php echo esc_textarea($cupri_general['user_sms_format']); ?></textarea>
                <span class="desc"><?php _e('Possible formats', 'cupri'); ?>: <?= cupri_get_possible_notification_variables() ?></span>
            </p>

            <h5><?php _e('Email Notifications', 'cupri'); ?></h5>
            <p class="admin_fields">
                <strong><?php _e('Admin Email(s)', 'cupri') ?></strong><br>
                <input value="<?php echo esc_attr($cupri_general['emails']); ?>" type="text"
                       name="cupri_general[emails]">
                <span class="desc"><?php _e('Seperate more emails with ,', 'cupri') ?></span>
            </p>
            <p class="admin_fields">
                <strong><?php _e('Active notification with email ?', 'cupri') ?></strong><br>
                <input <?php checked($cupri_general['active_email_notification'], 1, true); ?> type="checkbox" value="1"
                                                                                               name="cupri_general[active_email_notification]">
                <span class="desc"></span>
            </p>
            <p class="admin_fields">
                <strong><?php _e('Email format', 'cupri') ?></strong><br>
                <span class="desc"><?php _e('Possible formats', 'cupri'); ?>: <?= cupri_get_possible_notification_variables() ?></span>
                <?php
                $content = $cupri_general['admin_email_format'];
                $editor_id = 'cupri_general_admin_email_format';
                $settings = array(
                    'media_buttons' => false,
                    'textarea_name' => 'cupri_general[admin_email_format]',
                    'textarea_rows' => 10,
                );
                wp_editor($content, $editor_id, $settings);
                ?>
            </p>
            <p class="admin_fields">
                <strong><?php _e('Send payment receipt to user email?', 'cupri') ?></strong><br>
                <input <?php checked($cupri_general['active_user_receipt_with_email'], 1, true); ?> type="checkbox"
                                                                                                    value="1"
                                                                                                    name="cupri_general[active_user_receipt_with_email]">
                <span class="desc"><?= __('If the user has entered their email, a payment receipt will be sent to them', 'cupri') ?></span>
            </p>

            <p class="admin_fields">
                <strong><?php _e('User email format', 'cupri') ?></strong><br>
                <span class="desc"><?php _e('Possible formats', 'cupri'); ?>: <?= cupri_get_possible_notification_variables() ?></span>
                <?php
                $content = $cupri_general['user_email_format'];
                $editor_id = 'cupri_general_user_email_format';
                $settings = array(
                    'media_buttons' => false,
                    'textarea_name' => 'cupri_general[user_email_format]',
                    'textarea_rows' => 10,
                );
                wp_editor($content, $editor_id, $settings);
                ?>
            </p>
        </div>
        <div class="cupri_gateways">
            <h2> :: <?php _e('Form', 'cupri'); ?></h2>
            <p class="admin_fields">
                <strong><?php _e('change form color', 'cupri') ?></strong><br>
                <input type="text" data-default-color="#51cbee"
                       value="<?php echo esc_attr($cupri_general['form_color']); ?>"
                       name="cupri_general[form_color]" id="cupri_general_form_color">
                <span class="desc"></span>
            </p>
            <p class="admin_fields">
                <strong><?php _e('Disable default style?', 'cupri') ?></strong><br>
                <input <?php isset($cupri_general['disable_default_style']) ? (checked($cupri_general['disable_default_style'], 1, true)) : ''; ?>
                        type="checkbox" value="1" name="cupri_general[disable_default_style]">
                <span class="desc"></span>
            </p>
            <p class="admin_fields">
                <strong><?php _e('Form Scheme', 'cupri') ?></strong><br>
                <?php
                $form_scheme = isset($cupri_general['form_scheme']) ? $cupri_general['form_scheme'] : 1;
                $schemes = [1, 2, 3, 4];
                ?>
                <select name="cupri_general[form_scheme]">
                    <?php
                    foreach ($schemes as $scheme) {
                        echo '<option ' . selected($form_scheme, $scheme) . ' value="' . esc_attr($scheme) . '">' . esc_html($scheme) . '</option>';
                    }
                    ?>
                </select>
                <span class="desc"></span>
            </p>
            <p class="admin_fields">
                <strong><?php _e('Disable readonly fields for pre-defined values?', 'cupri') ?></strong><br>
                <input <?php isset($cupri_general['disable_readonly_predefined_values']) ? (checked($cupri_general['disable_readonly_predefined_values'], 1, true)) : ''; ?>
                        type="checkbox" value="1" name="cupri_general[disable_readonly_predefined_values]">
                <span class="desc"><?= __('If enabled, predefined values in forms will not be read-only.', 'cupri'); ?></span>
            </p>
            <p class="admin_fields">
                <strong><?php _e('Enable anonymously pay tick?', 'cupri') ?></strong><br>
                <input <?php isset($cupri_general['anonymously_pay_tick']) ? (checked($cupri_general['anonymously_pay_tick'], 1, true)) : ''; ?>
                        type="checkbox" value="1" name="cupri_general[anonymously_pay_tick]">
                <span class="desc"></span>
            </p>
            <p class="admin_fields">
                <strong><?php _e('anonymously pay text', 'cupri') ?></strong><br>
                <input type="text"
                       value="<?php echo isset($cupri_general['anonymously_pay_text']) ? esc_html($cupri_general['anonymously_pay_text']) : 'حمایت به صورت ناشناس'; ?>"
                       name="cupri_general[anonymously_pay_text]">
                <span class="desc"></span>
            </p>
            <p class="admin_fields">
                <strong><?php _e('PreDefined Prices', 'cupri') ?></strong><br>
                <input type="text"
                       value="<?php echo isset($cupri_general['predefined_prices']) ? esc_html($cupri_general['predefined_prices']) : ''; ?>"
                       name="cupri_general[predefined_prices]">
                <span class="desc">مبالغ آماده که کاربر به جای نوشتن انتخاب کند.مبلغ ها را با , از هم جدا کنید ، مثال : 10000,50000
                برای غیرفعال کردن خالی بگذارید</span>
            </p>
            <p class="admin_fields">
                <strong><?php _e('Submit button text', 'cupri') ?></strong><br>
                <input type="text"
                       value="<?php echo isset($cupri_general['submit_button_text']) ? esc_attr($cupri_general['submit_button_text']) : ''; ?>"
                       name="cupri_general[submit_button_text]">
                <span class="desc"></span>
            </p>
            <p class="admin_fields">
                <strong><?php _e('Success redirect page', 'cupri') ?></strong><br>
                <input type="text"
                       value="<?php echo isset($cupri_general['success_redirect_page']) ? esc_url($cupri_general['success_redirect_page']) : ''; ?>"
                       name="cupri_general[success_redirect_page]">
                <span class="desc"><?php _e('If payment was successful user redirect to this url , to disable leave it empty', 'cupri') ?></span>
            </p>
            <p class="admin_fields">
                <strong><?php _e('Fail redirect page', 'cupri') ?></strong><br>
                <input type="text"
                       value="<?php echo isset($cupri_general['failed_redirect_page']) ? esc_url($cupri_general['failed_redirect_page']) : ''; ?>"
                       name="cupri_general[failed_redirect_page]">
                <span class="desc"><?php _e('If payment was not successful user redirect to this url , to disable leave it empty', 'cupri') ?></span>
            </p>
            <p class="admin_fields">
                <strong><?php _e('Coutdown value', 'cupri') ?></strong><br>
                <input type="number"
                       value="<?php echo isset($cupri_general['coutdown_value']) ? (int)$cupri_general['coutdown_value'] : ''; ?>"
                       name="cupri_general[coutdown_value]">
                <span class="desc"></span>
            </p>

        </div>
        <button class="button-primary"><?php _e('Save'); ?></button>
    </form>

</div>
<script type="text/javascript">
    jQuery(document).ready(function ($) {
        $('#cupri_general_form_color').wpColorPicker({defaultColor: "#51cbee"});
    });
</script>
