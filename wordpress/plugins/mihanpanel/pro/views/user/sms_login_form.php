<?php do_action('mwpl_login_before_login_form_start'); ?>
<form class="mwpl-form-wrapper mwpl-login-form <?php echo implode(' ', apply_filters('mwpl_login_form/form_classes', []))?>" method="post" mwpl-state="phone">
    <?php do_action('mwpl_login_form_after_start_form'); ?>
    <div class="mwpl-form-fields">
        <div class="mwpl-input-item">
            <label for="phone">
                <span class="mwpl-icon">
                    <img src="<?php echo \mihanpanel\app\assets::get_image_url('phone-icon', 'svg') ?>" alt="phone-icon">
                </span>
                <span class="mwpl-field">
                    <input type="text" name="phone" id="phone">
                    <span class="mwpl-label-value"><?php echo apply_filters('mwpl_login_form_phone_field_label_value', __('Phone number', 'mihanpanel')); ?></span>
                </span>

            </label>
        </div>

        <div class="mwpl-input-item mwpl-hide">
            <label for="dynamic_code">
                <span class="mwpl-icon">
                    <img src="<?php echo \mihanpanel\app\assets::get_image_url('password-icon', 'svg') ?>" alt="password-icon">
                </span>
                <span class="mwpl-field">
                    <input type="text" name="dynamic_code" id="dynamic_code">
                    <span class="mwpl-label-value"><?php echo apply_filters('mwpl_login_form_dynamic_code_field_label_value', __('Dynamic code', 'mihanpanel')); ?></span>
                </span>

            </label>
        </div>

        <div class="mwpl-input-item mwpl-timer mwpl-hide" mwpl-exp="">
            <span class="mwpl-countdown">00:00</span>
        </div>

        <?php do_action('mwpl_login_form_before_submit_button'); ?>
        <div class="mwpl-notice-wrapper">
            <div class="mwpl-notice"></div>
            <div class="mwpl-action-bar"></div>
        </div>
        <div class="mwpl-input-item mwpl-spinner-field">
            <input mwpl-code-request-state-text="<?php esc_attr_e('Send code', 'mihanpanel') ?>" mwpl-login-state-text="<?php esc_attr_e('Login', 'mihanpanel') ?>" type="submit" name="login" value="<?php esc_attr_e('Send code', 'mihanpanel') ?>">
            <div class="mwpl-spinner-wrapper">
                <span class="mwpl-spinner"></span>
            </div>
        </div>
        <?php do_action('mwpl_login_form_after_submit_button'); ?>

        <?php if (\mihanpanel\app\options::get_user_can_register() && !isset($_GET['mwpl_register_status']) && !isset($_GET['activation_status'])) : ?>
            <div class="mwpl-input-item">
                <div class="mwpl-register-link mwpl-button mp-toggle-form-btn mp-toggle-form-btn">
                    <span><?php echo apply_filters('mwpl_login_form_register_button_text_value', __('Create new account', 'mihanpanel')); ?></span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</form>
<?php do_action('mwpl_login_after_login_form_end'); ?>