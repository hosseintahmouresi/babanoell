<?php

namespace mihanpanel\pro\app;

use mihanpanel\app\fields_control;

class handle_view
{
    static function option_panel_content_middlewares($middlewares)
    {
        // check lciense
        $middlewares[] = [__CLASS__, 'check_license_for_option_panel_content'];
        return $middlewares;
    }
    static function check_license_for_option_panel_content()
    {
        if (!license::check_license()) {
            $address = sprintf('<strong>%s</strong>', __('MihanPanel Pro > License', 'mihanpanel'));
            $message = sprintf(__('License is not active! Please activate it from %s Thanks!', 'mihanpanel'), $address);
            $url = admin_url('admin.php?page=mihanpanel_license');
            $link_style = 'box-shadow: none;margin: 0 30px;color: #d70150;background: #fff;text-decoration: none;border-radius: 5px;padding: 5px 10px;';
            $link = sprintf('<a href="%s" style="%s">%s</a>', $url, $link_style, __('Get Started', 'mihanpanel'));
            $notice = sprintf('<p class="alert error">%s %s</p>', $message, $link);
            echo $notice;
        }
        return true;
    }
    static function panel_content_middleware($middlewares)
    {
        // check has valid phone if dynamic password enabled
        $middlewares[] = [__CLASS__, 'has_valid_phone'];
        return $middlewares;
    }
    static function has_valid_phone()
    {
        if (
            (
                options::is_dynamic_password_mode()
                || (options::get_smart_login_status() && options::get_is_force_obtain_phone_number_smart_login())
            )
            && !users::has_valid_phone(get_current_user_id())
        ) {
            assets::load_mobile_verification_assets();
            $view = views::get('user.validate_phone');
            include $view;
            return false;
        }
        return true;
    }
    static function panel_widget_middleware_register_day($middlewares)
    {
        $middlewares[] = ['\mihanpanel\pro\app\options', 'is_show_register_day_count_widget'];
        return $middlewares;
    }
    static function panel_widget_middleware_comment($middlewares)
    {
        $middlewares[] = ['\mihanpanel\pro\app\options', 'is_show_comment_widget'];
        return $middlewares;
    }
    static function panel_widget_middleware_edd($middlewares)
    {
        $middlewares[] = ['\mihanpanel\pro\app\options', 'is_show_edd_widget'];
        return $middlewares;
    }
    static function panel_widget_middleware_woocommerce($middlewares)
    {
        $middlewares[] = ['\mihanpanel\pro\app\options', 'is_show_woocommerce_widget'];
        return $middlewares;
    }
    static function panel_widget_middleware_awesome_support($middlewares)
    {
        $middlewares[] = ['\mihanpanel\pro\app\options', 'is_show_awesome_support_widget'];
        return $middlewares;
    }
    static function panel_widget_middleware_poshtvan($middlewares)
    {
        $middlewares[] = ['\mihanpanel\pro\app\options', 'is_show_poshtvan_widget'];
        return $middlewares;
    }

    static function handle_option_panel_login_theme_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_login_theme'];
    }
    static function handle_option_panel_login_form_position_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_login_form_position'];
    }
    static function handle_option_panel_login_button_text_color()
    {
        return [__CLASS__, 'render_option_panel_field_login_button_text_color'];
    }
    static function handle_option_panel_register_button_bg_color()
    {
        return [__CLASS__, 'render_option_panel_field_register_button_bg_color'];
    }
    static function handle_option_panel_register_button_text_color()
    {
        return [__CLASS__, 'render_option_panel_field_register_button_text_color'];
    }
    static function handle_option_panel_login_fields_bg_color()
    {
        return [__CLASS__, 'render_option_panel_field_login_fields_bg_color'];
    }
    static function handle_option_panel_login_fields_label_color()
    {
        return [__CLASS__, 'render_option_panel_field_login_fields_label_color'];
    }
    static function handle_option_panel_login_fields_text_color()
    {
        return [__CLASS__, 'render_option_panel_field_login_fields_text_color'];
    }
    static function handle_option_panel_login_info_messagebox_bg_color()
    {
        return [__CLASS__, 'render_option_panel_field_login_info_messagebox_bg_color'];
    }
    static function handle_option_panel_login_info_messagebox_text_color()
    {
        return [__CLASS__, 'render_option_panel_field_login_info_messagebox_text_color'];
    }

    static function handle_option_panel_redirect_normal_user_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_redirect_normal_user'];
    }
    static function handle_option_panel_use_mihan_avatar_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_use_mihan_avatar'];
    }
    static function handle_option_panel_panel_theme_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_panel_theme'];
    }
    static function handle_option_panel_panel_font_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_panel_font'];
    }
    static function handle_option_panel_off_code_text_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_off_code_text'];
    }
    static function handle_option_panel_sidebar_bg_color_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_sidebar_bg_color'];
    }
    static function handle_option_panel_sidebar_color_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_sidebar_color'];
    }
    static function handle_option_panel_is_show_register_day_count_widget_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_is_show_register_day_count_widget'];
    }
    static function handle_option_panel_is_show_comment_widget_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_is_show_comment_widget'];
    }
    static function handle_option_panel_is_show_woocommerce_widget_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_is_show_woocommerce_widget'];
    }
    static function handle_option_panel_is_show_edd_widget_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_is_show_edd_widget'];
    }
    static function handle_option_panel_is_show_awesome_support_widget_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_is_show_awesome_support_widget'];
    }
    static function handle_option_panel_is_show_poshtvan_widget_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_is_show_poshtvan_widget'];
    }
    static function handle_option_panel_ban_roles_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_ban_roles'];
    }
    static function handle_option_panel_register_text_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_register_text'];
    }
    static function handle_option_panel_roles_user_can_select_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_roles_user_can_select'];
    }
    static function handle_option_panel_smart_login_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_smart_login'];
    }
    static function handle_option_panel_smart_login_can_using_email_for_register_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_smart_login_can_using_email_for_register_field_render_method'];
    }
    static function handle_option_panel_smart_login_can_using_phone_for_register_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_smart_login_can_using_phone_for_register_field_render_method'];
    }
    static function handle_option_panel_is_redirect_wc_checkout_page_to_login_page_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_is_redirect_wc_checkout_page_to_login_page'];
    }
    static function handle_option_panel_smart_login_generate_random_username_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_smart_login_generate_random_username'];
    }
    static function handle_option_panel_login_with_google_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_login_with_google'];
    }
    static function handle_option_panel_password_type_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_password_type'];
    }
    static function handle_option_panel_hide_email_field_in_register_form_render_method()
    {
        return [__CLASS__, 'render_option_panel_hide_email_field_in_register_form'];
    }
    static function handle_option_panel_hide_username_field_in_register_form_render_method()
    {
        return [__CLASS__, 'render_option_panel_hide_username_field_in_register_form'];
    }
    static function handle_option_panel_hide_password_field_in_register_form_render_method()
    {
        return [__CLASS__, 'render_option_panel_hide_password_field_in_register_form'];
    }
    static function handle_option_panel_user_phone_section_render_method()
    {
        return [__CLASS__, 'render_option_panel_user_phone_section'];
    }
    static function handle_option_panel_sms_provider_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_sms_provider'];
    }
    static function handle_option_panel_phone_validation_sms_content_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_phone_validation_sms_content'];
    }
    static function handle_option_panel_dynamic_code_sms_content_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_dynamic_code_sms_content'];
    }
    static function render_option_panel_field_login_theme()
    {
        $loginFormTheme = \mihanpanel\pro\app\options::get_login_form_theme();
?>
        <div class="mwpl_checkbox">
            <label for="light">
                <input <?php checked('light', $loginFormTheme); ?> type="radio" name="login_form_theme" id="light" value="light">
                <span class="content">
                    <span class="preview">
                        <img src="<?php echo esc_url(\mihanpanel\pro\app\assets::get_img_url('login-theme-light', 'svg')) ?>" alt="login-theme-light">
                    </span>
                    <span class="title"><?php esc_html_e('Light', 'mihanpanel') ?></span>
                </span>
            </label>
        </div>
        <div class="mwpl_checkbox">
            <label for="dark">
                <input <?php checked('dark', $loginFormTheme); ?> type="radio" name="login_form_theme" id="dark" value="dark">
                <span class="content">
                    <span class="preview">
                        <img src="<?php echo esc_url(\mihanpanel\pro\app\assets::get_img_url('login-theme-dark', 'svg')) ?>" alt="login-theme-dark">
                    </span>
                    <span class="title"><?php esc_html_e('Dark', 'mihanpanel') ?></span>
                </span>
            </label>
        </div>
    <?php
    }
    static function render_option_panel_field_login_form_position()
    {
        $form_position = \mihanpanel\pro\app\options::get_login_form_position();
    ?>
        <div class="mwpl_checkbox">
            <label for="mwpl_login_form_position_right">
                <input <?php checked('right', $form_position); ?> type="radio" name="login_form_position" id="mwpl_login_form_position_right" value="right">
                <span class="content">
                    <span class="preview">
                        <img src="<?php echo esc_url(\mihanpanel\pro\app\assets::get_img_url('login-form-position-right', 'svg')) ?>" alt="login-form-position-right">
                    </span>
                    <span class="title"><?php esc_html_e('Right', 'mihanpanel') ?></span>
                </span>
            </label>
        </div>
        <div class="mwpl_checkbox">
            <label for="mwpl_login_form_position_center">
                <input <?php checked('center', $form_position); ?> type="radio" name="login_form_position" id="mwpl_login_form_position_center" value="center">
                <span class="content">
                    <span class="preview">
                        <img src="<?php echo esc_url(\mihanpanel\pro\app\assets::get_img_url('login-form-position-center', 'svg')) ?>" alt="login-form-position-center">
                    </span>
                    <span class="title"><?php esc_html_e('Center', 'mihanpanel') ?></span>
                </span>
            </label>
        </div>
        <div class="mwpl_checkbox">
            <label for="mwpl_login_form_position_left">
                <input <?php checked('left', $form_position); ?> type="radio" name="login_form_position" id="mwpl_login_form_position_left" value="left">
                <span class="content">
                    <span class="preview">
                        <img src="<?php echo esc_url(\mihanpanel\pro\app\assets::get_img_url('login-form-position-left', 'svg')) ?>" alt="login-theme-left">
                    </span>
                    <span class="title"><?php esc_html_e('Left', 'mihanpanel') ?></span>
                </span>
            </label>
        </div>
    <?php
    }
    static function render_option_panel_field_login_button_text_color()
    {
    ?>
        <div live_view_target_id="mwpl_live_view_login_button" class="mp_option_field">
            <label><?php esc_html_e("Login Button Text Color", "mihanpanel"); ?></label>
            <?php \mihanpanel\app\presenter\admin_fields::renderColorPickerField(\mihanpanel\pro\app\options::get_login_button_text_color(), 'login_button_text_color', 'login_button_text_color')?>
            <p class="description"><?php esc_html_e("Color of Login and Register button text", "mihanpanel"); ?></p>
        </div>
    <?php
    }
    static function render_option_panel_field_register_button_bg_color()
    {
    ?>
        <div class="mp_option_field">
            <label><?php esc_html_e("Register button background color", "mihanpanel"); ?></label>
            <?php \mihanpanel\app\presenter\admin_fields::renderColorPickerField(\mihanpanel\pro\app\options::get_register_button_bg_color(), 'register_button_bg_color', 'register_button_bg_color')?>
            <p class="description"><?php esc_html_e("You can change background color of register button.", "mihanpanel"); ?></p>
        </div>
    <?php
    }
    static function render_option_panel_field_register_button_text_color()
    {
    ?>
        <div class="mp_option_field">
            <label><?php esc_html_e("Register button text color", "mihanpanel"); ?></label>
            <?php \mihanpanel\app\presenter\admin_fields::renderColorPickerField(\mihanpanel\pro\app\options::get_register_button_text_color(), 'register_button_text_color', 'register_button_text_color')?>
            <p class="description"><?php esc_html_e("By changing text color, the border color will also change.", "mihanpanel"); ?></p>
        </div>
    <?php
    }
    static function render_option_panel_field_login_fields_bg_color()
    {
    ?>
        <div live_view_target_id="mwpl_live_view_login_text_fields" class="mp_option_field">
            <label><?php esc_html_e("Login Fields Background Color", "mihanpanel"); ?></label>
            <?php \mihanpanel\app\presenter\admin_fields::renderColorPickerField(\mihanpanel\pro\app\options::get_login_fields_bg_color(), 'login_fields_bg_color', 'login_fields_bg_color')?>
            <p class="description"><?php esc_html_e("Color of Login and Register fields", "mihanpanel"); ?></p>
        </div>
    <?php
    }
    static function render_option_panel_field_login_fields_label_color()
    {
    ?>
        <div live_view_target_id="mwpl_live_view_login_text_fields" class="mp_option_field">
            <label><?php esc_html_e("Login Field's Label Color", "mihanpanel"); ?></label>
            <?php \mihanpanel\app\presenter\admin_fields::renderColorPickerField(\mihanpanel\pro\app\options::get_login_fields_label_color(), 'login_fields_label_color', 'login_fields_label_color')?>
            <p class="description"><?php esc_html_e("Color of Login and Register labels", "mihanpanel"); ?></p>
        </div>
    <?php
    }
    static function render_option_panel_field_login_fields_text_color()
    {
    ?>
        <div live_view_target_id="mwpl_live_view_login_text_fields" class="mp_option_field">
            <label><?php esc_html_e("Login Fields Text Color", "mihanpanel"); ?></label>
            <?php \mihanpanel\app\presenter\admin_fields::renderColorPickerField(\mihanpanel\pro\app\options::get_login_fields_text_color(), 'login_fields_text_color', 'login_fields_text_color')?>
            <p class="description"><?php esc_html_e("Color of Login and Register fields text", "mihanpanel"); ?></p>
        </div>
    <?php
    }
    static function render_option_panel_field_login_info_messagebox_bg_color()
    {
    ?>
        <div live_view_target_id="mwpl_live_view_login_info_messagebox" class="mp_option_field">
            <label><?php esc_html_e("Login Info Messagebox Background Color", "mihanpanel"); ?></label>
            <?php \mihanpanel\app\presenter\admin_fields::renderColorPickerField(\mihanpanel\pro\app\options::get_login_info_messagebox_bg_color(), 'login_info_messagebox_bg_color', 'login_info_messagebox_bg_color')?>
            <p class="description"><?php esc_html_e("Background Color of Login and Register Messagebox", "mihanpanel"); ?></p>
        </div>
    <?php
    }
    static function render_option_panel_field_login_info_messagebox_text_color()
    {
    ?>
        <div live_view_target_id="mwpl_live_view_login_info_messagebox" class="mp_option_field">
            <label><?php esc_html_e("Login Info Messagebox Text Color", "mihanpanel"); ?></label>
            <?php \mihanpanel\app\presenter\admin_fields::renderColorPickerField(\mihanpanel\pro\app\options::get_login_info_messagebox_text_color(), 'login_info_messagebox_text_color', 'login_info_messagebox_text_color')?>
            <p class="description"><?php esc_html_e("Color of Login and Register Messagebox Text", "mihanpanel"); ?></p>
        </div>
    <?php
    }

    static function render_option_panel_field_redirect_normal_user()
    {
    ?>
        <input style="text-align:left;direction:ltr" id="mp_redirect_normal_users_to" type="text" name="mp_redirect_normal_users_to" value="<?php echo esc_attr(options::getNormalRolesAfterLoginRedirectUrl()); ?>" />
    <?php
    }
    static function render_option_panel_field_use_mihan_avatar()
    {
    ?>
        <input type="checkbox" id="mp_use_mihan_avatar" name="mp_use_mihan_avatar" <?php checked('on', options::is_mihan_avatar_enable()) ?>>
    <?php
    }
    static function render_option_panel_field_panel_theme()
    {
        $current_theme = options::get_panel_theme();
    ?>
        <div class="mwpl_checkbox">
            <label for="mwpl_panel_theme_default">
                <input <?php checked('0', $current_theme); ?> type="radio" name="mwpl_panel_theme" id="mwpl_panel_theme_default" value="0">
                <span class="content">
                    <span class="preview">
                        <img src="<?php echo esc_url(\mihanpanel\pro\app\assets::get_img_url('panel-theme-default')) ?>" alt="panel-theme-default">
                    </span>
                    <span class="title"><?php esc_html_e('Default', 'mihanpanel') ?></span>
                </span>
            </label>
        </div>
        <div class="mwpl_checkbox">
            <label for="mwpl_panel_theme_modern">
                <input <?php checked('modern', $current_theme); ?> type="radio" name="mwpl_panel_theme" id="mwpl_panel_theme_modern" value="modern">
                <span class="content">
                    <span class="preview">
                        <img src="<?php echo esc_url(\mihanpanel\pro\app\assets::get_img_url('panel-theme-modern')) ?>" alt="panel-theme-modern">
                    </span>
                    <span class="title"><?php esc_html_e('Modern', 'mihanpanel') ?></span>
                </span>
            </label>
        </div>
        <div class="mwpl_checkbox">
            <label for="mwpl_panel_theme_3d">
                <input <?php checked('3d', $current_theme); ?> type="radio" name="mwpl_panel_theme" id="mwpl_panel_theme_3d" value="3d">
                <span class="content">
                    <span class="preview">
                        <img src="<?php echo esc_url(\mihanpanel\pro\app\assets::get_img_url('panel-theme-3d')) ?>" alt="panel-theme-3d">
                    </span>
                    <span class="title"><?php esc_html_e('3D', 'mihanpanel') ?></span>
                </span>
            </label>
        </div>
    <?php
    }
    static function render_option_panel_field_panel_font()
    {
        $current_font_name = options::get_font_name();
    ?>
        <div class="mwpl_checkbox">
            <label for="mp_panelfont_iranyekan">
                <input <?php checked('IRANYekan', $current_font_name); ?> type="radio" name="mp_panelfont" id="mp_panelfont_iranyekan" value="IRANYekan">
                <span class="content">
                    <span class="preview">
                        <img src="<?php echo esc_url(\mihanpanel\pro\app\assets::get_img_url('panel-font-iranyekan')) ?>" alt="panel-font-iranyekan">
                    </span>
                    <span class="title"><?php esc_html_e('IRANYekan', 'mihanpanel') ?></span>
                </span>
            </label>
        </div>
        <div class="mwpl_checkbox">
            <label for="mp_panelfont_iransans">
                <input <?php checked('IRANSans', $current_font_name); ?> type="radio" name="mp_panelfont" id="mp_panelfont_iransans" value="IRANSans">
                <span class="content">
                    <span class="preview">
                        <img src="<?php echo esc_url(\mihanpanel\pro\app\assets::get_img_url('panel-font-iransans')) ?>" alt="panel-font-iransans">
                    </span>
                    <span class="title"><?php esc_html_e('IRANSans', 'mihanpanel') ?></span>
                </span>
            </label>
        </div>
    <?php
    }
    static function render_option_panel_field_off_code_text()
    {
    ?>
        <input type="text" value="<?php echo esc_attr(\mihanpanel\pro\app\options::get_raw_off_code_text()); ?>" name="mp_off_code_text" id="mp_off_code_text">
    <?php
    }
    static function render_option_panel_field_sidebar_bg_color()
    {
    ?>
        <?php \mihanpanel\app\presenter\admin_fields::renderColorPickerField(\mihanpanel\pro\app\options::get_panel_sidebar_bg_color(), 'mp_sidebar_bg_color', 'sidebar-bg-color-picker')?>
    <?php
    }
    static function render_option_panel_field_sidebar_color()
    {
    ?>
        <?php \mihanpanel\app\presenter\admin_fields::renderColorPickerField(\mihanpanel\pro\app\options::get_panel_sidebar_color(), 'mp_sidebar_color', 'sidebar-link-color-picker')?>
    <?php
    }
    static function render_option_panel_field_is_show_register_day_count_widget()
    {
    ?>
        <div class="mp_option_field">
            <div class="mp_option_field mp_row_field">
                <label><?php esc_html_e("Show register day count widget", "mihanpanel"); ?></label>
                <input class="checkbox-affected-toggle-elements" data-elements=".register_day_count_widget_text_field_wrapper" type="checkbox" name="mp_is_show_register_day_count_widget" <?php checked('on', options::is_show_register_day_count_widget()) ?>>
            </div>
            <div class="mp_option_field mp_row_field register_day_count_widget_text_field_wrapper no-border">
                <label for="register_day_count_widget_text"><?php _e('Widget title', 'mihanpanel')?></label>
                <input type="text" id="register_day_count_widget_text" name="mp_register_day_count_widget_text" value="<?php echo esc_attr(\mihanpanel\pro\app\options::getRegisterDayCountWidgetText())?>">
            </div>
        </div>
    <?php
    }
    static function filterRegisterDayCountWidgetText($text)
    {
        $optionValue = options::getRegisterDayCountWidgetText();
        return $optionValue ? $optionValue : $text;
    }
    static function render_option_panel_field_is_show_comment_widget()
    {
    ?>
        <div class="mp_option_field">
            <div class="mp_option_field mp_row_field">
                <label><?php esc_html_e("Show Comment widget", "mihanpanel"); ?></label>
                <input class="checkbox-affected-toggle-elements" data-elements=".comments_count_widget_text_field_wrapper" type="checkbox" name="mp_is_show_comment_count_widget" <?php checked('on', options::is_show_comment_widget()) ?>>
            </div>
            <div class="mp_option_field mp_row_field comments_count_widget_text_field_wrapper no-border">
                <label for="comments_count_widget_text"><?php _e('Widget title', 'mihanpanel')?></label>
                <input type="text" id="comments_count_widget_text" name="mp_comments_count_widget_text" value="<?php echo esc_attr(\mihanpanel\pro\app\options::getCommentsCountWidgetText())?>">
            </div>
        </div>
    <?php
    }
    static function filterCommentsCountWidgetText($text)
    {
        $optionValue = options::getCommentsCountWidgetText();
        return $optionValue ? $optionValue : $text;
    }

    static function render_option_panel_field_is_show_woocommerce_widget()
    {
    ?>
        <div class="mp_option_field">
            <div class="mp_option_field mp_row_field">
                <label><?php esc_html_e("Show Woocommerce widget", "mihanpanel"); ?></label>
                <input class="checkbox-affected-toggle-elements" data-elements=".woocommerce_orders_widget_text_field_wrapper" type="checkbox" name="mp_is_show_woocommerce_widget" <?php checked('on', options::is_show_woocommerce_widget()) ?>>
            </div>
            <div class="mp_option_field mp_row_field woocommerce_orders_widget_text_field_wrapper no-border">
                <label for="woocommerce_orders_widget_text"><?php _e('Widget title', 'mihanpanel')?></label>
                <input type="text" id="woocommerce_orders_widget_text" name="mp_woocommerce_orders_widget_text" value="<?php echo esc_attr(\mihanpanel\pro\app\options::getWoocommerceOrdersWidgetText())?>">
            </div>
        </div>
    <?php
    }
    static function filterWoocommerceOrdersCountWidgetText($text)
    {
        $optionValue = options::getWoocommerceOrdersWidgetText();
        return $optionValue ? $optionValue : $text;
    }
    static function render_option_panel_field_is_show_edd_widget()
    {
    ?>
        <div class="mp_option_field">
            <div class="mp_option_field mp_row_field">
                <label><?php esc_html_e("Show EDD widget", "mihanpanel"); ?></label>
                <input class="checkbox-affected-toggle-elements" data-elements=".edd_widget_text_field_wrapper" type="checkbox" name="mp_is_show_edd_widget" <?php checked('on', options::is_show_edd_widget()) ?>>
            </div>
            <div class="mp_option_field mp_row_field edd_widget_text_field_wrapper no-border">
                <label for="edd_widget_text"><?php _e('Widget title', 'mihanpanel')?></label>
                <input type="text" id="edd_widget_text" name="mp_edd_widget_text" value="<?php echo esc_attr(\mihanpanel\pro\app\options::getEddWidgetText())?>">
            </div>
        </div>
    <?php
    }
    static function filterEddWidgetText($text)
    {
        $optionValue = options::getEddWidgetText();
        return $optionValue ? $optionValue : $text;
    }

    static function render_option_panel_field_is_show_awesome_support_widget()
    {
    ?>
        <div class="mp_option_field">
            <div class="mp_option_field mp_row_field">
                <label><?php esc_html_e("Show Awesome Support widget", "mihanpanel"); ?></label>
                <input class="checkbox-affected-toggle-elements" data-elements=".awesome_support_widget_text_field_wrapper" type="checkbox" name="mp_is_show_awesome_support_widget" <?php checked('on', options::is_show_awesome_support_widget()) ?>>
            </div>
            <div class="mp_option_field mp_row_field awesome_support_widget_text_field_wrapper no-border">
                <label for="awesome_support_widget_text"><?php _e('Widget title', 'mihanpanel')?></label>
                <input type="text" id="awesome_support_widget_text" name="mp_awesome_support_widget_text" value="<?php echo esc_attr(\mihanpanel\pro\app\options::getAwesomeSupportWidgetText())?>">
            </div>
        </div>
    <?php
    }
    static function filterAwesomeSupportWidgetText($text)
    {
        $optionValue = options::getAwesomeSupportWidgetText();
        return $optionValue ? $optionValue : $text;
    }
    static function render_option_panel_field_is_show_poshtvan_widget()
    {
    ?>
        <div class="mp_option_field">
            <div class="mp_option_field mp_row_field">
                <label><?php esc_html_e("Show Poshtvan widget", "mihanpanel"); ?></label>
                <input class="checkbox-affected-toggle-elements" data-elements=".poshtvan_widget_text_field_wrapper" type="checkbox" name="mp_is_show_mihanticket_widget" <?php checked('on', options::is_show_poshtvan_widget()) ?>>
            </div>
            <div class="mp_option_field mp_row_field poshtvan_widget_text_field_wrapper no-border">
                <label for="poshtvan_widget_text"><?php _e('Widget title', 'mihanpanel')?></label>
                <input type="text" id="poshtvan_widget_text" name="mp_poshtvan_widget_text" value="<?php echo esc_attr(\mihanpanel\pro\app\options::getPoshtvanWidgetText())?>">
            </div>
        </div>
    <?php
    }
    static function filterPoshtvanWidgetText($text)
    {
        $optionValue = options::getPoshtvanWidgetText();
        return $optionValue ? $optionValue : $text;
    }

    static function render_option_panel_field_ban_roles()
    {
    ?>
        <div class="mp_option_field">
            <?php
            $all_roles = wp_roles()->get_names();
            $ban_roles = options::get_roles_cant_use_admin_list();
            foreach ($all_roles as $role_key => $role_name) :
                if ($role_key == 'administrator')
                    continue;
            ?>
                <p>
                    <label for="<?php echo esc_attr($role_key) ?>"><?php esc_html_e($role_name); ?></label>
                    <input <?php echo is_array($ban_roles) && in_array($role_key, $ban_roles) ? 'checked' : ''; ?> id="<?php echo esc_attr($role_key); ?>" type="checkbox" name="mp_role_cant_use_admin_panel[]" value="<?php echo esc_attr($role_key); ?>">
                </p>
            <?php endforeach; ?>
        </div>
    <?php
    }

    // register tab
    static function render_option_panel_field_register_text()
    {
    ?>
        <input id="mp_register_text" type="text" name="mp_register_text" value="<?php echo esc_attr(\mihanpanel\pro\app\options::get_register_head_text()); ?>" />
    <?php
    }
    static function render_option_panel_field_roles_user_can_select()
    {
    ?>
        <div class="mp_option_field">
            <?php $roles_name = wp_roles()->get_names();
            $selected_roles = options::get_roles_user_can_select();
            foreach ($roles_name as $role_key => $role_name) :
                if ($role_key == 'administrator')
                    continue;
            ?>
                <p>
                    <label for="<?php echo 'select_role_' . esc_attr($role_key); ?>"><?php echo esc_html(translate_user_role($role_name)); ?></label>
                    <input <?php echo is_array($selected_roles) && in_array($role_key, array_keys($selected_roles)) ? 'checked' : ''; ?> type="checkbox" id="<?php echo 'select_role_' . esc_attr($role_key); ?>" name="mp_roles_user_can_select[<?php echo esc_attr($role_key); ?>]" value="<?php echo esc_attr($role_name); ?>">
                </p>
            <?php endforeach; ?>
        </div>
    <?php
    }

    // login with sms tab
    static function render_option_panel_field_smart_login()
    {
    ?>
        <input data-elements=".box-for-smart-login-status" class="checkbox-affected-toggle-elements" type="checkbox" value="1" <?php checked(1, options::get_smart_login_status()); ?> name="mp_smart_login" id="mp_smart_login">
    <?php
    }
    static function render_option_panel_smart_login_can_using_email_for_register_field_render_method()
    {
        ?>
        <input type="checkbox" value="1" <?php checked(1, options::canUsingEmailInSmartLoginForRegister()); ?> name="mp_smart_login_can_using_email_for_register" id="mp_smart_login_can_using_email_for_register">
        <?php
    }
    static function render_option_panel_smart_login_can_using_phone_for_register_field_render_method()
    {
        ?>
        <input type="checkbox" value="1" <?php checked(1, options::canUsingPhoneInSmartLoginForRegister()); ?> name="mp_smart_login_can_using_phone_for_register" id="mp_smart_login_can_using_phone_for_register">
        <?php
    }
    static function render_option_panel_field_smart_login_generate_random_username()
    {
    ?>
        <input <?php disabled(!options::get_smart_login_status()) ?> type="checkbox" value="1" <?php checked(1, options::get_smart_login_generate_random_username_status()); ?> name="mp_smart_login_generate_random_username" id="mp_smart_login_generate_random_username">
    <?php
    }
    static function render_option_panel_field_is_redirect_wc_checkout_page_to_login_page()
    {
    ?>
        <input type="checkbox" value="1" <?php checked(1, options::get_is_redirect_wc_checkout_to_login_page()); ?> name="mp_wc_redirect_from_checkout_is_not_logged_in" id="mp_wc_redirect_from_checkout_is_not_logged_in">
    <?php
    }
    
    static function render_option_panel_field_login_with_google()
    {
    ?>
        <div class="mp_option_field">
            <div>
                <label for="is_login_with_google_active"><?php echo esc_html__('Activate login with Google', 'mihanpanel') ?></label>
                <input <?php checked(\mihanpanel\pro\app\options::isLoginWithGoogleActive())?> type="checkbox" data-elements=".box-for-login-with-google" class="checkbox-affected-toggle-elements" name="is_login_with_google_active" id="is_login_with_google_active" value="1">
            </div>
            <div class="box-for-login-with-google">
                <label for="login_with_google_btn_text"><?php _e('Button text', 'mihanpanel')?></label>
                <input dir="auto" type="text" id="login_with_google_btn_text" name="login_with_google_btn_text" value="<?php echo esc_attr(\mihanpanel\pro\app\options::getLoginWithGoogleBtnText())?>">
            </div>
            <div class="box-for-login-with-google">
                <label for="login_with_google_client_id">Client ID</label>
                <input dir="auto" type="text" id="login_with_google_client_id" name="login_with_google_client_id" value="<?php echo esc_attr(\mihanpanel\pro\app\options::getLoginWithGoogleClientID())?>">
            </div>
            <div class="box-for-login-with-google">
                <label for="login_with_google_client_secret">Client Secret</label>
                <input dir="auto" type="text" id="login_with_google_client_secret" name="login_with_google_client_secret" value="<?php echo esc_attr(\mihanpanel\pro\app\options::getLoginWithGoogleClientSecret())?>">
            </div>
        </div>
    <?php
    }

    static function render_option_panel_field_password_type()
    {
    ?>
        <div class="mp_option_field mp_row_field" style="gap: 55px">
            <div class="mwpl_modal_wrapper user_phone_modal_box">
                <div class="mwpl_modal_form">
                    <div class="input-item form-notice">
                        <span><?php _e("You don't have any active and accessible phone number, Please enter a phone number first, then you can activate SMS password mode on your site", 'mihanpanel')?></span>
                    </div>
                    <div class="input-item">
                        <label for="admin_profile_phone_number"><?php _e('Phone number', 'mihanpanel')?></label>
                        <input user_id="<?php echo esc_attr(get_current_user_id())?>" type="text" id="admin_profile_phone_number" name="admin_profile_phone_number" value="<?php echo esc_attr(\mihanpanel\pro\app\users::get_user_phone(get_current_user_id()))?>">
                    </div>
                    <div class="input-item">
                        <input type="submit" href="#" id="submit_admin_user_phone_value" value="<?php esc_attr_e('Using this phone number for my account', 'mihanpanel')?>">
                    </div>
                    <div class="input-item">
                        <button class="close_modal_box"><?php _e('Close', 'mihanpanel')?></button>
                    </div>
                </div>
            </div>
            <?php
            $password_types = options::get_password_types();
            $current_password_type = options::get_current_password_type();
            ?>
            <?php foreach ($password_types as $type => $name) : ?>
                <p class="mwpl_solid_checkbox">
                    <label for="mp_password_type_<?php echo esc_attr($type); ?>"><?php echo esc_html($name); ?></label>
                    <input <?php disabled(options::get_smart_login_status()) ?> <?php checked($type, $current_password_type); ?> type="radio" name="mp_password_type" value="<?php echo esc_attr($type); ?>" id="mp_password_type_<?php echo esc_attr($type) ?>">
                </p>
            <?php endforeach; ?>
        </div>
    <?php
    }
    static function render_option_panel_hide_email_field_in_register_form()
    {
    ?>
        <input <?php disabled(true, options::get_smart_login_status() || options::is_wp_default_password_mode()) ?> type="checkbox" value="1" <?php checked(1, options::is_hide_email_field_in_register_form()); ?> name="mp_hide_email_field_in_register_form" id="mp_hide_email_field_in_register_form">
    <?php
    }
    static function render_option_panel_hide_username_field_in_register_form()
    {
    ?>
        <input <?php disabled(true, options::get_smart_login_status() || options::is_wp_default_password_mode()); ?> type="checkbox" value="1" <?php checked(1, options::is_hide_username_field_in_register_form()); ?> name="mp_hide_username_field_in_register_form" id="mp_hide_username_field_in_register_form">
    <?php
    }
    static function render_option_panel_hide_password_field_in_register_form()
    {
    ?>
        <input <?php disabled(true, options::get_smart_login_status() || options::is_wp_default_password_mode()); ?> type="checkbox" value="1" <?php checked(1, options::is_hide_password_field_in_register_form()); ?> name="mp_hide_password_field_in_register_form" id="mp_hide_password_field_in_register_form">
    <?php
    }
    static function render_option_panel_user_phone_section()
    {
    ?>
        <div class="mp_option_field">
            <p>
                <label for="mp_auto_confirm_phone_after_register"><?php esc_html_e('Auto confirm phone number after user register', 'mihanpanel'); ?></label>
                <input <?php disabled(options::get_smart_login_status()) ?> <?php checked('1', options::is_auto_confirm_phone_after_resgister()); ?> value="1" type="checkbox" name="mp_auto_confirm_phone_after_register" id="mp_auto_confirm_phone_after_register">
            </p>
            <p>
                <label for="mp_can_change_phone"><?php esc_html_e('User can change phone number', 'mihanpanel'); ?></label>
                <input <?php checked('on', options::user_can_change_phone()); ?> type="checkbox" name="mp_can_change_phone" id="mp_can_change_phone">
            </p>
        </div>
    <?php
    }
    static function render_option_panel_field_sms_provider()
    {
        $providers = sms_provider::get_providers_title();
        $active_provider = options::get_active_sms_provider();
    ?>
        <select name="mp_sms_provider" id="mp_sms_provider">
            <?php if ($providers) : ?>
                <option value="0" disabled selected><?php esc_html_e('Select SMS Provider', 'mihanpanel'); ?></option>
                <?php foreach ($providers as $provider_slug => $provider_title) : ?>
                    <option <?php selected($active_provider, $provider_slug); ?> value="<?php echo esc_attr($provider_slug); ?>"><?php echo esc_html($provider_title); ?></option>
                <?php endforeach; ?>
            <?php else : ?>
                <option value="0" disabled selected><?php esc_html_e("Don't found any SMS provider!", 'mihanpanel'); ?></option>
            <?php endif; ?>
        </select>
    <?php
    }
    static function render_option_panel_field_phone_validation_sms_content()
    {
    ?>
        <textarea dir="auto" name="mp_phone_validation_sms_content" id="mp_phone_validation_sms_content" class="regular-text" cols="30" rows="8"><?php echo esc_textarea(options::get_phone_validation_sms_content()); ?></textarea>
    <?php
    }
    static function render_option_panel_field_dynamic_code_sms_content()
    {
    ?>
        <textarea dir="auto" name="mp_dynamic_code_sms_content" id="mp_dynamic_code_sms_content" class="regular-text" cols="30" rows="8"><?php echo esc_textarea(options::get_dynamic_code_sms_content()); ?></textarea>
    <?php
    }

    static function handle_admin_tabs_fields_field_id_render_method()
    {
        return [__CLASS__, 'render_admin_tabs_fields_field_id_content'];
    }
    static function render_admin_tabs_fields_field_id_content($menu)
    {
        $meta = $menu->meta;
        $meta = $meta ? unserialize($meta) : [];
        $value = isset($meta['field_id']) ? $meta['field_id'] : '';
    ?>
        <input value="<?php echo esc_attr($value) ?>" name="field_choosen_id" type="text" id="field_choosen_id_<?php echo esc_attr($menu->id); ?>">
    <?php
    }

    static function handle_admin_tabs_fields_is_show_by_roles_render_method()
    {
        return [__CLASS__, 'render_admin_tabs_fields_is_show_by_roles_content'];
    }
    static function render_admin_tabs_fields_is_show_by_roles_content($menu)
    {
        $meta = $menu->meta;
        $meta = $meta ? unserialize($meta) : [];
        $show_by_roles = isset($meta['show_by_roles']) ? $meta['show_by_roles'] : false;
    ?>
        <input <?php checked(true, is_array($show_by_roles)) ?> name="is_show_by_roles" type="checkbox" id="is_show_by_roles_<?php echo esc_attr($menu->id); ?>">
        <div class="row" depends="is_show_by_roles_<?php echo esc_attr($menu->id); ?>">
            <?php $all_roles = \mihanpanel\app\users::get_all_roles_name(); ?>
            <?php foreach ($all_roles as $role_key => $role_value) :
                if ($role_key == 'administrator') {
                    continue;
                }
            ?>
                <div>
                    <input <?php checked(true, is_array($show_by_roles) && in_array($role_key, $show_by_roles)) ?> type="checkbox" id="show_for_this_roles_<?php echo esc_attr($role_key) . '_' . esc_attr($menu->id)  ?>" name="show_for_this_roles[]" value="<?php echo esc_attr($role_key) ?>">
                    <label for="show_for_this_roles_<?php echo esc_attr($role_key) . '_' . esc_attr($menu->id)  ?>"><?php echo esc_html(translate_user_role($role_value)) ?></label>
                </div>
            <?php endforeach; ?>
        </div>
    <?php
    }

    static function handle_option_panel_login_header_additional_code_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_login_header_additional_code'];
    }
    static function render_option_panel_field_login_header_additional_code()
    {
        $value = options::get_login_header_additional_code();
    ?>
        <textarea dir="ltr" class="full-width" name="mp_login_header_additional_code" id="mp_login_header_additional_code" cols="30" rows="10"><?php echo $value ? esc_textarea($value) : ''; ?></textarea>
    <?php
    }

    static function handle_option_panel_transfer_digits_phone_numbers_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_transfer_digits_phone_numbers'];
    }
    static function render_option_panel_field_transfer_digits_phone_numbers()
    {
    ?>
        <input onclick="if(!confirm('<?php esc_attr_e('Are you sure you have backedup your site?', 'mihanpanel') ?>')){event.preventDefault()}" name="transfer_digits_phone_numbers" type="submit" value="<?php esc_html_e('Transfer', 'mihanpanel') ?>">
    <?php
    }
    static function handle_option_panel_delete_ghost_users_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_delete_ghost_users'];
    }
    static function render_option_panel_field_delete_ghost_users()
    {
    ?>
        <input prompt_text="<?php esc_attr_e("Are you sure you want to delete ghost users?", 'mihanpanel') ?>" name="delete_ghost_users" type="submit" value="<?php esc_html_e('Delete', 'mihanpanel') ?>">
        <input type="hidden" name="delete_ghost_users_current_id" value="1">
    <?php
    }

    static function handle_option_panel_is_blog_section_enable_field_render_method()
    {
        return [__CLASS__, 'render_option_field_is_blog_section_enable'];
    }
    static function render_option_field_is_blog_section_enable()
    {
        $value = options::is_blog_section_enable();
    ?>
        <input type="checkbox" value="1" id="mp_is_blog_section_enable" name="mp_is_blog_section_enable" <?php checked($value) ?>>
    <?php
    }

    static function handle_option_panel_blog_section_title_field_render_method()
    {
        return [__CLASS__, 'render_option_field_blog_section_title'];
    }
    static function render_option_field_blog_section_title()
    {
        $value = options::get_panel_blog_section_title();
    ?>
        <input type="text" name="mp_blog_section_title" id="mp_blog_section_title" value="<?php echo $value ? esc_attr($value) : ''; ?>">
    <?php
    }

    static function handle_option_panel_blog_section_title_link_field_render_method()
    {
        return [__CLASS__, 'render_option_field_blog_section_title_link'];
    }
    static function render_option_field_blog_section_title_link()
    {
        $value = options::get_panel_blog_section_title_link();
    ?>
        <input type="text" name="mp_blog_section_title_link" id="mp_blog_section_title_link" value="<?php echo $value ? esc_attr($value) : ''; ?>">
    <?php
    }

    static function handle_option_panel_blog_section_posts_category_field_render_method()
    {
        return [__CLASS__, 'render_option_field_blog_section_posts_category'];
    }
    static function render_option_field_blog_section_posts_category()
    {
        $categories = get_categories(['hide_empty' => false]);
        $value = options::get_panel_blog_section_posts_category();
    ?>
        <select name="mp_blog_section_posts_category" id="mp_blog_section_posts_category">
            <?php if ($categories) : ?>
                <option value="0" selected default><?php esc_html_e('Choose an item', 'mihanpanel') ?></option>
                <?php foreach ($categories as $category) : ?>
                    <option <?php selected($value, $category->term_id) ?> value="<?php echo esc_attr($category->term_id) ?>"><?php echo esc_html($category->name) ?></option>
                <?php endforeach; ?>
            <?php else : ?>
                <option value="0" selected><?php esc_html_e('No any category find', 'mihanpanel') ?></option>
            <?php endif; ?>
        </select>
    <?php
    }

    static function handle_option_panel_blog_section_posts_count_field_render_method()
    {
        return [__CLASS__, 'render_option_field_blog_section_posts_count'];
    }
    static function render_option_field_blog_section_posts_count()
    {
        $value = options::get_panel_blog_section_posts_count();
    ?>
        <input type="number" min="1" name="mp_blog_section_posts_count" id="mp_blog_section_posts_count" value="<?php echo esc_attr($value); ?>">
    <?php
    }
    // start recent products
    static function handle_option_panel_is_wc_recent_products_section_enable_field_render_method()
    {
        return [__CLASS__, 'render_option_field_is_wc_recent_products_section_enable'];
    }
    static function render_option_field_is_wc_recent_products_section_enable()
    {
        $value = options::is_wc_recent_products_section_enable();
    ?>
        <input mwpl_has_show_depends type="checkbox" value="1" id="mp_is_wc_recent_products_section_enable" name="mp_is_wc_recent_products_section_enable" <?php checked($value) ?>>
    <?php
    }
    static function handle_option_panel_wc_recent_products_section_title_field_render_method()
    {
        return [__CLASS__, 'render_option_field_wc_recent_products_section_title'];
    }
    static function render_option_field_wc_recent_products_section_title()
    {
        $value = options::get_panel_wc_recent_products_section_title();
    ?>
        <input type="text" name="mp_wc_recent_products_section_title" id="mp_wc_recent_products_section_title" value="<?php echo esc_attr($value); ?>">
    <?php
    }
    static function handle_option_panel_wc_recent_products_section_link_field_render_method()
    {
        return [__CLASS__, 'render_option_field_wc_recent_products_section_link'];
    }
    static function render_option_field_wc_recent_products_section_link()
    {
        $value = options::get_panel_wc_recent_products_section_link();
    ?>
        <input type="text" name="mp_wc_recent_products_section_link" id="mp_wc_recent_products_section_link" value="<?php echo esc_attr($value); ?>">
    <?php
    }
    static function handle_option_panel_wc_recent_products_section_items_category_field_render_method()
    {
        return [__CLASS__, 'render_option_field_wc_recent_products_section_items_category'];
    }
    static function render_option_field_wc_recent_products_section_items_category()
    {
        $args = [
            'taxonomy' => 'product_cat',
            'orderby' => 'name',
            'order' => 'asc',
            'hide_empty' => false,
        ];
        $categories = get_terms($args);
        $values = options::get_panel_wc_recent_products_section_items_categories();
    ?>
        <select class="mw_select2" multiple="multiple" name="mp_wc_recent_products_section_items_categories[]" id="mp_wc_recent_products_section_items_categories">
            <?php if ($categories) : ?>
                <option value="0" disabled="disabled"><?php esc_html_e('Choose an item', 'mihanpanel') ?></option>
                <?php foreach ($categories as $category) : ?>
                    <option <?php selected(in_array($category->term_id, $values)) ?> value="<?php echo esc_attr($category->term_id) ?>"><?php echo esc_html($category->name) ?></option>
                <?php endforeach; ?>
            <?php else : ?>
                <option value="0" selected><?php esc_html_e('No any category find', 'mihanpanel') ?></option>
            <?php endif; ?>
        </select>
    <?php
    }
    static function handle_option_panel_wc_recent_products_section_item_field_render_method()
    {
        return [__CLASS__, 'render_option_field_wc_recent_products_section_item'];
    }
    static function render_option_field_wc_recent_products_section_item()
    {
        $value = options::get_panel_wc_recent_products_section_items_count();
    ?>
        <input type="number" min="1" name="mp_wc_recent_products_section_items_count" id="mp_wc_recent_products_section_items_count" value="<?php echo esc_attr($value); ?>">
    <?php
    }
    // end recent products
    static function handle_option_panel_wpml_sms_just_in_persian_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_wpml_sms_just_in_persian'];
    }
    static function render_option_panel_field_wpml_sms_just_in_persian()
    {
        $value = options::get_wpml_sms_just_in_persian_status();
    ?>
        <input type="checkbox" value="1" <?php checked($value); ?> name="mp_wpml_sms_just_in_persian" id="mp_wpml_sms_just_in_persian">
    <?php
    }

    static function handle_option_panel_force_get_phone_number_in_smart_login_mode_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_force_get_phone_number_in_smart_login_mode'];
    }
    static function render_option_panel_field_force_get_phone_number_in_smart_login_mode()
    {
        $value = options::get_is_force_obtain_phone_number_smart_login();
    ?>
        <input <?php disabled(!options::get_smart_login_status()) ?> type="checkbox" value="1" <?php checked($value); ?> name="mp_force_get_phone_number_in_panel" id="mp_force_get_phone_number_in_panel">
    <?php
    }

    static function handle_option_panel_dynamic_code_email_subject_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_dynamic_code_email_subject'];
    }
    static function render_option_panel_field_dynamic_code_email_subject()
    {
        $value = options::get_raw_dynamic_code_email_subject();
    ?>
        <input type="text" name="dynamic_code_email_subject" id="dynamic_code_email_subject" value="<?php echo esc_attr($value); ?>">
    <?php
    }

    static function handle_option_panel_dynamic_code_email_content_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_dynamic_code_email_content'];
    }
    static function render_option_panel_field_dynamic_code_email_content()
    {
        $value = options::get_raw_dynamic_code_email_content();
    ?>
        <div class="mw_dashicons mw_editor_wrapper">
            <?php wp_editor($value, 'dynamic_code_email_content', ['textarea_rows' => 10]); ?>
        </div>

        <p><?php esc_html_e("You can use this:", "mihanpanel"); ?></p>
        <p>
            <span><?php esc_html_e("Dynamic Code", "mihanpanel"); ?>: </span><span>[[code]]</span>
        </p>
    <?php
    }


    static function handle_option_panel_login_form_top_message_render_method()
    {
        return [__CLASS__, 'render_option_field_login_form_top_message_text'];
    }
    static function render_option_field_login_form_top_message_text()
    {
        $value = options::get_login_form_top_message_text();
    ?>
        <input type="text" name="mp_login_form_top_message_text" id="mp_login_form_top_message_text" value="<?php echo esc_attr($value); ?>">
    <?php
    }

    public static function handle_option_panel_custom_captcha_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_custom_captcha'];
    }

    public static function render_option_panel_field_custom_captcha()
    {
        $is_active = options::is_active_custom_captcha();
        $case_sensitive = options::is_custom_captcha_case_sensitive();
        $type = options::get_custom_captcha_type();
        $in_login = options::is_active_custom_captcha_in_login();
        $in_register = options::is_active_custom_captcha_in_register();
        $in_reset_password = options::is_active_custom_captcha_in_reset_password();
    ?>
        <div class="mp_option_field">
            <?php if(!\mihanpanel\pro\app\forms_captcha::isGenerateImageAccessible()): ?>
                <div class="mp_option_notice_wrapper">
                    <p class="alert error"><?php _e('It seems that the GD library is not installed on your server and displaying the captcha code will be problematic. To solve this problem, contact the support of your hosting server.', 'mihanpanel')?></p>
                </div>
            <?php endif; ?>
            <div>
                <label for="is_active_custom_captcha"><?php echo esc_html__('Activate Captcha', 'mihanpanel') ?></label>
                <input type="checkbox" name="is_active_custom_captcha" id="is_active_custom_captcha" class="checkbox-affected-toggle-elements captcha-depend" data-elements=".box-for-captcha" value="1" <?php checked('1', $is_active) ?>>
            </div>
            <div class="box-for-captcha" style="display:<?php echo $is_active ? 'block' : 'none' ?>">
                <label for="custom_captcha_case_sensitive"><?php echo esc_html__('Case Sensitive', 'mihanpanel') ?></label>
                <input type="checkbox" name="custom_captcha_case_sensitive" id="custom_captcha_case_sensitive" value="1" <?php checked('1', $case_sensitive) ?>>
            </div>
            <div class="box-for-captcha" style="display:<?php echo $is_active ? 'block' : 'none' ?>">
                <label><?php echo esc_html__('Captcha Type', 'mihanpanel') ?></label>
                <span class="radio-group">
                    <label for="captcha_type_number"><?php echo esc_html__('Number', 'mihanpanel') ?></label>
                    <input type="radio" name="custom_captcha_type" id="captcha_type_number" value="number" <?php checked('number', $type) ?>>
                    <label for="captcha_type_letters"><?php echo esc_html__('Letters', 'mihanpanel') ?></label>
                    <input type="radio" name="custom_captcha_type" id="captcha_type_letters" value="letters" <?php checked('letters', $type) ?>>
                    <label for="captcha_type_both"><?php echo esc_html__('Both', 'mihanpanel') ?></label>
                    <input type="radio" name="custom_captcha_type" id="captcha_type_both" value="both" <?php checked('both', $type) ?>>
                </span>
            </div>
            <div class="box-for-captcha" style="display:<?php echo $is_active ? 'block' : 'none' ?>">
                <label><?php echo esc_html__('Active in Forms', 'mihanpanel') ?></label>
                <span class="radio-group">
                    <div>
                        <input type="checkbox" name="custom_captcha_active_in_login" id="custom_captcha_active_in_login" value="1" <?php checked('1', $in_login) ?>>
                        <label for="custom_captcha_active_in_login"><?php echo esc_html__('Login Form', 'mihanpanel') ?></label>
                    </div>
                    <div>
                        <input type="checkbox" name="custom_captcha_active_in_register" id="custom_captcha_active_in_register" value="1" <?php checked('1', $in_register) ?>>
                        <label for="custom_captcha_active_in_register"><?php echo esc_html__('Register Form', 'mihanpanel') ?></label>
                    </div>
                    <div>
                        <input type="checkbox" name="custom_captcha_active_in_reset_password" id="custom_captcha_active_in_reset_password" value="1" <?php checked('1', $in_reset_password) ?>>
                        <label for="custom_captcha_active_in_reset_password"><?php echo esc_html__('Reset Password Form', 'mihanpanel') ?></label>
                    </div>
                </span>
            </div>
        </div>
    <?php
    }

    static function handle_option_panel_google_recaptcha_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_google_recaptcha'];
    }

    public static function render_option_panel_field_google_recaptcha()
    {
        $is_active = options::is_active_google_recaptcha();
        $type = options::get_google_recaptcha_type();
        $in_login = options::is_active_google_recaptcha_in_login();
        $in_register = options::is_active_google_recaptcha_in_register();
        $in_reset_password = options::is_active_google_recaptcha_in_reset_password();
    ?>
        <div class="mp_option_field">
            <div>
                <label for="is_active_google_recaptcha"><?php echo esc_html__('Activate Recaptcha', 'mihanpanel') ?></label>
                <input type="checkbox" name="is_active_google_recaptcha" id="is_active_google_recaptcha" class="checkbox-affected-toggle-elements captcha-depend" data-elements=".box-for-grecaptcha" value="1" <?php checked('1', $is_active) ?>>
            </div>
            <div class="box-for-grecaptcha" style="display:<?php echo $is_active ? 'block' : 'none' ?>">
                <label><?php echo esc_html__('Recaptcha Type', 'mihanpanel') ?></label>
                <span class="radio-group">
                    <label for="recaptcha_type2"><?php echo esc_html__('Version 2', 'mihanpanel') ?></label>
                    <input type="radio" name="recaptcha_type" id="recaptcha_type2" value="v2" <?php checked('v2', $type) ?>>
                    <label for="recaptcha_type3"><?php echo esc_html__('Version 3', 'mihanpanel') ?></label>
                    <input type="radio" name="recaptcha_type" id="recaptcha_type3" value="v3" <?php checked('v3', $type) ?>>
                </span>
            </div>
            <div class="box-for-grecaptcha" style="display:<?php echo $is_active ? 'block' : 'none' ?>">
                <label for="recaptcha_site_key"><?php echo esc_html__('Site Key', 'mihanpanel') ?></label>
                <input type="text" name="recaptcha_site_key" id="recaptcha_site_key" dir="ltr" value="<?php echo options::get_google_recaptcha_site_key() ?>">
            </div>
            <div class="box-for-grecaptcha" style="display:<?php echo $is_active ? 'block' : 'none' ?>">
                <label for="recaptcha_secret_key"><?php echo esc_html__('Private Key', 'mihanpanel') ?></label>
                <input type="text" name="recaptcha_secret_key" id="recaptcha_secret_key" dir="ltr" value="<?php echo options::get_google_recaptcha_secret_key() ?>">
                <div>
                    <a href="https://www.google.com/recaptcha/admin" target="_blank" class="get-recaptcha-key-btn"><?php echo esc_html__('Get recaptcha access keys for this domain.', 'mihanpanel') ?></a>
                </div>
            </div>
            <div class="box-for-grecaptcha" style="display:<?php echo $is_active ? 'block' : 'none' ?>">
                <label><?php echo esc_html__('Active in Forms', 'mihanpanel') ?></label>
                <span class="radio-group">
                    <div>
                        <input type="checkbox" name="recaptcha_active_in_login" id="recaptcha_active_in_login" value="1" <?php checked('1', $in_login) ?>>
                        <label for="recaptcha_active_in_login"><?php echo esc_html__('Login Form', 'mihanpanel') ?></label>
                    </div>
                    <div>
                        <input type="checkbox" name="recaptcha_active_in_register" id="recaptcha_active_in_register" value="1" <?php checked('1', $in_register) ?>>
                        <label for="recaptcha_active_in_register"><?php echo esc_html__('Register Form', 'mihanpanel') ?></label>
                    </div>
                    <div>
                        <input type="checkbox" name="recaptcha_active_in_reset_password" id="recaptcha_active_in_reset_password" value="1" <?php checked('1', $in_reset_password) ?>>
                        <label for="recaptcha_active_in_reset_password"><?php echo esc_html__('Reset Password Form', 'mihanpanel') ?></label>
                    </div>
                </span>
            </div>
        </div>
    <?php
    }

    static function handle_option_panel_login_page_background_fields_render_method()
    {
        return [__CLASS__, 'render_option_panel_login_page_background_fields'];
    }

    public static function render_option_panel_login_page_background_fields()
    {
    ?>
        <div class="mp_option_single">
            <?php
            $bg_key = 'mp_page_bg';
            fields_control::renderControl(fields_control::BACKGROUND_GROUP, [
                'label' => __('Background', 'mihanpanel'),
                'name' => $bg_key,
                'supports' => ['video'],
                'values' => [
                    'type' => \mihanpanel\app\options::get_option($bg_key . '_type', 'simple'),
                    'color' => \mihanpanel\app\options::get_option($bg_key . '_color', '#f0f0f1'),
                    'color2' => \mihanpanel\app\options::get_option($bg_key . '_color2', '#f0f0f1'),
                    'direction' => \mihanpanel\app\options::get_option($bg_key . '_direction'),
                    'image' => \mihanpanel\app\options::get_option($bg_key . '_image'),
                    'video' => \mihanpanel\app\options::get_option($bg_key . '_video'),
                    'repeat' => \mihanpanel\app\options::get_option($bg_key . '_repeat', 'no-repeat'),
                    'size' => \mihanpanel\app\options::get_option($bg_key . '_size', 'cover'),
                    'position' => \mihanpanel\app\options::get_option($bg_key . '_position', 'center center'),
                ]
            ]);
            ?>
        </div>
    <?php
    }

    static function handle_option_panel_login_form_background_fields_render_method()
    {
        return [__CLASS__, 'render_option_panel_login_form_background_fields'];
    }

    public static function render_option_panel_login_form_background_fields()
    {
    ?>
        <div class="mp_option_single">
            <h3><?php echo __('Form Box', 'mihanpanel') ?></h3>
            <?php
            $bg_key = 'mp_form_bg';
            fields_control::renderControl(fields_control::BACKGROUND_GROUP, [
                'label' => __('Background', 'mihanpanel'),
                'name' => $bg_key,
                'values' => [
                    'type' => \mihanpanel\app\options::get_option($bg_key . '_type', 'simple'),
                    'color' => \mihanpanel\app\options::get_option($bg_key . '_color', '#fff'),
                    'color2' => \mihanpanel\app\options::get_option($bg_key . '_color2', '#fff'),
                    'direction' => \mihanpanel\app\options::get_option($bg_key . '_direction'),
                    'image' => \mihanpanel\app\options::get_option($bg_key . '_image'),
                    'repeat' => \mihanpanel\app\options::get_option($bg_key . '_repeat', 'no-repeat'),
                    'size' => \mihanpanel\app\options::get_option($bg_key . '_size', 'cover'),
                    'position' => \mihanpanel\app\options::get_option($bg_key . '_position', 'center center'),
                ]
            ]);
            ?>
        </div>
        <div class="mp_option_single">
            <h3><?php echo __('Form Cover', 'mihanpanel') ?></h3>
            <p>
                <label for="mp_is_active_login_form_cover_section"><?php _e('Activate cover', 'mihanpanel') ?></label>
                <input class="checkbox-affected-toggle-elements" data-elements="#mp_login_form_cover_settings" type="checkbox" value="1" <?php checked(1, options::isActiveLoginFormCoverSection()); ?> name="mp_is_active_login_form_cover_section" id="mp_is_active_login_form_cover_section">
            </p>
            <div id="mp_login_form_cover_settings">
                <?php
                $bg_key = 'mp_bg';
                fields_control::renderControl(fields_control::BACKGROUND_GROUP, [
                    'label' => __('Background', 'mihanpanel'),
                    'name' => $bg_key,
                    'supports' => ['video'],
                    'values' => [
                        'type' => \mihanpanel\app\options::get_option($bg_key . '_type', 'image'),
                        'color' => \mihanpanel\app\options::get_option($bg_key . '_color', '#4f00d7'),
                        'color2' => \mihanpanel\app\options::get_option($bg_key . '_color2', '#a200d7'),
                        'direction' => \mihanpanel\app\options::get_option($bg_key . '_direction'),
                        'image' => \mihanpanel\app\options::get_option($bg_key . '_image'),
                        'video' => \mihanpanel\app\options::get_option($bg_key . '_video'),
                        'repeat' => \mihanpanel\app\options::get_option($bg_key . '_repeat', 'no-repeat'),
                        'size' => \mihanpanel\app\options::get_option($bg_key . '_size', 'cover'),
                        'position' => \mihanpanel\app\options::get_option($bg_key . '_position', 'center center'),
                    ]
                ]);
                ?>
            </div>
        </div>
        <div class="mp_option_single">
            <?php
            $allowed_json = options::is_allowed_upload_json_file();
            $placeholder = '';
            $description = __('You can enter the file address or select a file from the website media, note that the file format of lottie must be JSON.', 'mihanpanel');
            if (!$allowed_json) {
                $link = sprintf("<a href='%s'>%s</a>", \mihanpanel\app\admin_menu::get_settings_tab_url('security'), __('security settings', 'mihanpanel'));
                $description = sprintf(__('For upload a JSON file, you must enable the option to upload this type of file from the %s, otherwise the file address must be entered.', 'mihanpanel'), $link);
                $placeholder = __('Enter file address', 'mihanpanel');
            }
            fields_control::renderControl(fields_control::FILE_UPLOADER, [
                'label' => __('Use Lottie File', 'mihanpanel'),
                'name' => 'mp_form_lottie_file',
                'value' => \mihanpanel\app\options::get_option('mp_form_lottie_file'),
                'placeholder' => $placeholder,
                'description' => $description,
                'allowed_upload' => $allowed_json,
            ]);
            ?>
            <a href="https://lottiefiles.com/featured" target="_blank"><?php echo __('View lottie files', 'mihanpanel') ?></a>
        </div>
    <?php
    }

    static function handle_option_panel_files_allowed_upload_render_method()
    {
        return [__CLASS__, 'render_option_panel_files_allowed_upload'];
    }

    public static function render_option_panel_files_allowed_upload()
    {
        $types = ['json'];
    ?>
        <div class="mp_option_field">
            <?php
            foreach ($types as $type) :
                $field_name = "mp_allowed_{$type}_file";
            ?>
                <div>
                    <label for="<?php echo esc_attr($field_name) ?>"><?php echo sprintf(__('Upload %s File', 'mihanpanel'), $type) ?></label>
                    <input type="checkbox" name="<?php echo esc_attr($field_name) ?>" id="<?php echo esc_attr($field_name) ?>" value="1" <?php echo apply_filters('mp_disable_reset_password_checked', checked('1', get_option($field_name), false)) ?>>
                </div>
            <?php endforeach; ?>
        </div>
    <?php
    }

    static function handle_option_panel_edit_username_label_in_login_form_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_edit_username_label_in_login_form'];
    }
    static function render_option_panel_field_edit_username_label_in_login_form()
    {
    ?>
        <input show_field_is_active field_target="mp_username_field_label_value_in_login_form" type="checkbox" value="1" <?php checked(1, options::is_edit_username_field_label_in_login_form()); ?> name="mp_edit_username_field_label_in_login_form" id="mp_edit_username_field_label_in_login_form">
    <?php
    }

    static function handle_option_panel_username_label_value_in_login_form_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_username_label_value_in_login_form'];
    }
    static function render_option_panel_field_username_label_value_in_login_form()
    {
        $value = options::get_username_label_value_in_login_form();
    ?>
        <input type="text" name="mp_username_field_label_value_in_login_form" id="mp_username_field_label_value_in_login_form" value="<?php echo esc_attr($value); ?>">
    <?php
    }

    static function handle_option_panel_edit_phone_label_in_login_form_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_edit_phone_label_in_login_form'];
    }
    static function render_option_panel_field_edit_phone_label_in_login_form()
    {
    ?>
        <input show_field_is_active field_target="mp_phone_field_label_value_in_login_form" type="checkbox" value="1" <?php checked(1, options::is_edit_phone_field_label_in_login_form()); ?> name="mp_edit_phone_field_label_in_login_form" id="mp_edit_phone_field_label_in_login_form">
    <?php
    }
    static function handle_option_panel_phone_label_value_in_login_form_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_phone_label_value_in_login_form'];
    }
    static function render_option_panel_field_phone_label_value_in_login_form()
    {
        $value = options::get_phone_label_value_in_login_form();
    ?>
        <input type="text" name="mp_phone_field_label_value_in_login_form" id="mp_phone_field_label_value_in_login_form" value="<?php echo esc_attr($value); ?>">
    <?php
    }
    static function handle_option_panel_edit_password_label_in_login_form_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_edit_password_label_in_login_form'];
    }
    static function render_option_panel_field_edit_password_label_in_login_form()
    {
    ?>
        <input show_field_is_active field_target="mp_password_field_label_value_in_login_form" type="checkbox" value="1" <?php checked(1, options::is_edit_password_field_label_in_login_form()); ?> name="mp_edit_password_field_label_in_login_form" id="mp_edit_password_field_label_in_login_form">
    <?php
    }
    static function handle_option_panel_password_label_value_in_login_form_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_password_label_value_in_login_form'];
    }
    static function render_option_panel_field_password_label_value_in_login_form()
    {
        $value = options::get_password_label_value_in_login_form();
    ?>
        <input type="text" name="mp_password_field_label_value_in_login_form" id="mp_password_field_label_value_in_login_form" value="<?php echo esc_attr($value); ?>">
    <?php
    }

    static function handle_option_panel_edit_dynamic_code_label_in_login_form_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_edit_dynamic_code_label_in_login_form'];
    }
    static function render_option_panel_field_edit_dynamic_code_label_in_login_form()
    {
    ?>
        <input show_field_is_active field_target="mp_dynamic_code_field_label_value_in_login_form" type="checkbox" value="1" <?php checked(1, options::is_edit_dynamic_code_field_label_in_login_form()); ?> name="mp_edit_dynamic_code_field_label_in_login_form" id="mp_edit_dynamic_code_field_label_in_login_form">
    <?php
    }
    static function handle_option_panel_dynamic_code_label_value_in_login_form_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_dynamic_code_label_value_in_login_form'];
    }
    static function render_option_panel_field_dynamic_code_label_value_in_login_form()
    {
        $value = options::get_dynamic_code_label_value_in_login_form();
    ?>
        <input type="text" name="mp_dynamic_code_field_label_value_in_login_form" id="mp_dynamic_code_field_label_value_in_login_form" value="<?php echo esc_attr($value); ?>">
    <?php
    }

    static function handle_option_panel_edit_login_button_text_in_login_form_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_edit_login_button_text_in_login_form'];
    }
    static function render_option_panel_field_edit_login_button_text_in_login_form()
    {
    ?>
        <input show_field_is_active field_target="mp_login_button_text_value_in_login_form" type="checkbox" value="1" <?php checked(1, options::is_edit_login_button_text_in_login_form()); ?> name="mp_edit_login_button_text_in_login_form" id="mp_edit_login_button_text_in_login_form">
    <?php
    }
    static function handle_option_panel_login_button_text_value_in_login_form_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_login_button_text_value_in_login_form'];
    }
    static function render_option_panel_field_login_button_text_value_in_login_form()
    {
        $value = options::get_login_button_text_value_in_login_form();
    ?>
        <input type="text" name="mp_login_button_text_value_in_login_form" id="mp_login_button_text_value_in_login_form" value="<?php echo esc_attr($value); ?>">
    <?php
    }

    static function handle_option_panel_edit_register_button_text_in_login_form_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_edit_register_button_text_in_login_form'];
    }
    static function render_option_panel_field_edit_register_button_text_in_login_form()
    {
    ?>
        <input show_field_is_active field_target="mp_register_button_text_value_in_login_form" type="checkbox" value="1" <?php checked(1, options::is_edit_register_button_text_in_login_form()); ?> name="mp_edit_register_button_text_in_login_form" id="mp_edit_register_button_text_in_login_form">
    <?php
    }
    static function handle_option_panel_register_button_text_value_in_login_form_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_register_button_text_value_in_login_form'];
    }
    static function render_option_panel_field_register_button_text_value_in_login_form()
    {
        $value = options::get_register_button_text_value_in_login_form();
    ?>
        <input type="text" name="mp_register_button_text_value_in_login_form" id="mp_register_button_text_value_in_login_form" value="<?php echo esc_attr($value); ?>">
    <?php
    }

    static function handle_option_panel_redirect_url_after_logout_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_redirect_url_after_logout'];
    }
    static function render_option_panel_field_redirect_url_after_logout()
    {
    ?>
        <input style="text-align:left;direction:ltr" id="mp_after_logout_redirect_url" type="text" name="mp_after_logout_redirect_url" value="<?php echo esc_attr(options::getAfterLogoutRedirectUrl()); ?>" />
    <?php
    }

    static function handle_option_panel_is_add_mihanpanel_to_wp_nav_menu_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_is_add_mihanpanel_to_wp_nav_menu'];
    }
    static function render_option_panel_field_is_add_mihanpanel_to_wp_nav_menu()
    {
    ?>
        <input mwpl_has_depends type="checkbox" <?php checked(1, options::get_is_add_mihanpanel_to_wp_nav_menu()) ?> value="1" name="mp_is_add_mihanpanel_item_to_wp_nav_menu" id="mp_is_add_mihanpanel_item_to_wp_nav_menu">
    <?php
    }

    static function handle_option_panel_mihanpanel_wp_nav_menu_field_render_method()
    {
        return [__CLASS__, 'render_option_panel_field_mihanpanel_wp_nav_menu'];
    }
    static function render_option_panel_field_mihanpanel_wp_nav_menu()
    {
        $value = options::get_mihanpanel_wp_nav_menu_location();
        $wpNavMenus = get_terms(array(
            'taxonomy' => 'nav_menu',
            'hide_empty' => true, // Set to true if you want to hide menus with no items
        ));
        // $themeLocations = get_nav_menu_locations();
        $registeredNavMenus = get_registered_nav_menus();
    ?>
        <select class="mw_select2" mwpl_depends_on="mp_is_add_mihanpanel_item_to_wp_nav_menu" name="mp_mihanpanel_nav_menus_item_menu" id="mp_mihanpanel_nav_menus_item_menu">
            <?php if ($wpNavMenus) : ?>
                    <option selected disabled value="0"><?php _e('Choose menu', 'mihanpanel')?></option>
                <?php foreach ($registeredNavMenus as $location => $name) : ?>
                    <option <?php selected($value, $location)?> value="<?php echo esc_attr($location); ?>"><?php echo esc_html($name); ?></option>
                <?php endforeach; ?>
            <?php else : ?>
                <option value="0"><?php esc_html_e("No any menus exists.", 'mihanpanel'); ?></option>
            <?php endif; ?>
        </select>
<?php
    }
    static function handle_option_panel_exclude_login_route_in_litespeed_cache()
    {
        return [__CLASS__, 'render_option_panel_field_exclude_login_route_in_litespeed_cache'];
    }
    static function render_option_panel_field_exclude_login_route_in_litespeed_cache()
    {
        if(!\mihanpanel\app\tools::isLiteSpeedCacheActive())
        {
            return false;
        }
        $litespeedCacheSettingsUrl = admin_url('admin.php?page=litespeed-cache#excludes');
        ?>
        <div class="mp_option_section_description">
            <p><?php printf(__('Please exclude your login route from here %s', 'mihanpanel'), sprintf('<a target="_blank" href="%s">%s</a>', $litespeedCacheSettingsUrl, __('Litespeed Cache settings', 'mihanpanel')))?></p>
        </div>
        <?php
    }
}
