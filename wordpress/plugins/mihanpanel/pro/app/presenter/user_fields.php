<?php

namespace mihanpanel\pro\app\presenter;

use mihanpanel\app\tools;
use mihanpanel\pro\app\file_uploader;
use mihanpanel\pro\app\options;
use mihanpanel\pro\app\users;

class user_fields
{
    static function render_avatar_field()
    {
        if (!options::is_mihan_avatar_enable()) {
            return false;
        }
?>
        <div class="row" id="avatar_field">
            <div class="alert alert-primary4">
                <span><?php _e("Do you want to save this change?", 'mihanpanel'); ?></span>
                <input type="submit" name="submit" value="<?php _e("Yes", 'mihanpanel'); ?>">
            </div>
            <div class="col-md-12">
                <div class="form-group label-floating">
                    <div class="mw_avatar_section">
                        <div id="user_avatar_wrapper">
                            <img id="user_avatar_display" src="<?php echo esc_url(get_avatar_url(get_current_user_id())); ?>">
                        </div>
                        <label id="choose_avatar" for="user_avatar"><?php esc_html_e("Choose Image", 'mihanpanel'); ?></label>
                        <input name="mw_user_avatar" type="file" id="user_avatar" class="form-controls">
                        <input value="<?php esc_html_e("Remove", 'mihanpanel'); ?>" name="mw_remove_user_avatar" type="submit" id="remove_user_avatar" class="form-controls">
                    </div>
                </div>
            </div>
        </div>
    <?php
    }
    static function after_password_fields()
    {
        echo '<div class="row">';
        self::render_phone_field();
        self::render_display_name_field();
        echo '</div>';
    }
    static function renderUsernameField()
    {
        if(!apply_filters('mihanpanel/edit_profile/show_username_field_permission', true))
        {
            return false;
        }
        $current_user = wp_get_current_user();
        ?>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group label-floating">
                    <label><?php esc_html_e("Username", "mihanpanel"); ?></label>
                    <input disabled type="text" id="username"
                            value="<?php echo esc_attr($current_user->user_login); ?>"
                            class="form-control">
                </div>
            </div>
        </div>
        <?php
    }
    static function render_phone_field()
    {
        if (!\mihanpanel\pro\app\options::get_smart_login_status() && !options::is_dynamic_password_mode()) {
            return false;
        }
    ?>
        <div class="col-md-6">
            <div class="form-group label-floating">
                <label><?php _e("Phone Number", "mihanpanel"); ?></label>
                <input <?php echo !options::user_can_change_phone() ? 'disabled' : ''; ?> name="other[mw_phone_number]" type="text" id="mw_phone_number" value="<?php echo esc_attr(users::get_user_phone(get_current_user_id())); ?>" class="form-control">
            </div>
        </div>
    <?php
    }
    static function render_display_name_field()
    {
        if (!\mihanpanel\pro\app\options::get_smart_login_status()) {
            return false;
        }
        $display_name = users::get_display_name(get_current_user_id());
    ?>
        <div class="col-md-6">
            <div class="form-group label-floating">
                <label><?php _e("Display Name", "mihanpanel"); ?></label>
                <input name="other[mw_display_name]" type="text" id="mw_display_name" value="<?php echo esc_attr($display_name); ?>" class="form-control">
            </div>
        </div>
        <?php
    }
    static function filter_panel_user_field_render_methods($methods, $field_type)
    {
        $method_name = 'render_' . $field_type . '_field';
        if (method_exists(__CLASS__, $method_name)) {
            $methods[$field_type] = [__CLASS__, $method_name];
        }
        return $methods;
    }
    static function render_selectbox_field($view, $field, $current_user, $args = [])
    {
        $field_slug = $field->slug;
        $field_data = $current_user ? $current_user->{$field_slug} : '';
        $meta = isset($field->meta) ? unserialize($field->meta) : false;
        if (!$meta) {
            return false;
        }
        $prevent_edit_field = !\mihanpanel\app\users::is_admin_user() && isset($meta['data']['prevent_edit_field']) && $field_data;
        $classes = isset($args['classes']) ? $args['classes'] : '';
        if ($view == 'register-form') : ?>
            <label for="<?php echo esc_attr($field->slug) ?>">
                <span class="mwpl-field">
                    <span><?php echo esc_html($field->label) ?></span>
                    <select name="mw_fields[<?php echo esc_attr($field->slug) ?>]" id="<?php echo esc_attr($field->slug) ?>">
                        <option disabled selected value="0"><?php _e('Choose an item', 'mihanpanel'); ?></option>
                        <?php foreach ($meta as $key => $value) : ?>
                            <?php if ($key === 'data') {
                                continue;
                            }
                            ?>
                            <option <?php $field_data ? selected($field_data, $value['id']) : false; ?> value="<?php echo esc_attr($value['id']); ?>"><?php echo esc_html($value['title']); ?></option>
                        <?php endforeach; ?>
                    </select>

                </span>
            </label>
        <?php else : ?>
            <select <?php echo $prevent_edit_field ? 'disabled' : ''; ?> class="<?php echo esc_attr($classes); ?>" name="mw_fields[<?php echo esc_attr($field_slug) ?>]" id="mw_fields_<?php echo esc_attr($field_slug) ?>">
                <option disabled selected value="0"><?php _e('Choose an item', 'mihanpanel'); ?></option>
                <?php foreach ($meta as $key => $value) :
                    if ($key === 'data') {
                        continue;
                    }
                ?>
                    <option <?php $field_data ? selected($field_data, $value['id']) : false; ?> value="<?php echo esc_attr($value['id']); ?>"><?php echo esc_html($value['title']); ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif;
    }
    static function render_radio_field($view, $field, $current_user, $args)
    {
        $field_name = $field->slug;
        $field_data = $current_user ? $current_user->{$field_name} : '';
        $meta = isset($field->meta) ? unserialize($field->meta) : false;
        if (!$meta) {
            return false;
        }
        if ($view == 'register-form') : ?>
            <span class="mwpl-field-label"><?php echo $field->label ?></span>
            <?php foreach ($meta as $key => $item): ?>
                <div>
                    <input type="radio" name="mw_fields[<?php echo esc_attr($field_name) ?>]" id="<?php echo esc_attr('mw_fields_' . $field_name . '_' . $item['id']); ?>" value="<?php echo esc_attr($item['id']); ?>">
                    <label for="<?php echo esc_attr('mw_fields_' . $field_name . '_' . $item['id']); ?>"><?php echo esc_html($item['title']) ?></label>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <?php foreach ($meta as $key => $item) :
                if ($key === 'data') {
                    continue;
                }
                $prevent_edit_field = !\mihanpanel\app\users::is_admin_user() && isset($meta['data']['prevent_edit_field']) && $field_data;
            ?>
                <p class="radio-mode">
                    <input <?php echo $prevent_edit_field ? 'disabled' : ''; ?> <?php checked($field_data, $item['id']) ?> type="radio" name="mw_fields[<?php echo esc_attr($field_name) ?>]" id="<?php echo esc_attr('mw_fields_' . $field_name . '_' . $item['id']); ?>" value="<?php echo esc_attr($item['id']); ?>">
                    <label for="<?php echo esc_attr('mw_fields_' . $field_name . '_' . $item['id']); ?>"><?php echo esc_html($item['title']); ?></label>
                </p>
            <?php
            endforeach;
        endif;
    }
    static function render_datepicker_field($view, $field, $current_user, $args)
    {
        $value = $current_user ? $current_user->{$field->slug} : '';
        $date = $value ? tools::get_date('Y-m-d', $value) : '';
        $meta = isset($field->meta) ? unserialize($field->meta) : false;
        $prevent_edit_field = !\mihanpanel\app\users::is_admin_user() && isset($meta['data']['prevent_edit_field']) && $value;
        if ($view == 'register-form') : ?>
            <label for="<?php echo esc_attr($field->slug) ?>">
                <span class="mwpl-icon">
                    <img src="<?php echo \mihanpanel\app\assets::get_image_url('more-icon', 'svg') ?>" alt="password-icon">
                </span>
                <span class="mwpl-field mw_datepicker">
                    <span><?php echo esc_html($field->label) ?></span>
                    <input id="mw_fields_<?php echo esc_attr($field->slug) ?>" <?php echo $prevent_edit_field ? 'disabled' : ''; ?> type="text" value="<?php echo esc_attr($date); ?>" mw_target="mw_datepicker_alt_<?php echo esc_attr($field->slug); ?>" class="form-control">
                    <input <?php echo $prevent_edit_field ? 'disabled' : ''; ?> type="hidden" id="mw_datepicker_alt_<?php echo esc_attr($field->slug); ?>" name="mw_fields[<?php echo esc_attr($field->slug); ?>]">
                </span>
            </label>
        <?php else : ?>
            <div class="mw_datepicker">
                <input id="mw_fields_<?php echo esc_attr($field->slug) ?>" <?php echo $prevent_edit_field ? 'disabled' : ''; ?> type="text" value="<?php echo esc_attr($date); ?>" mw_target="mw_datepicker_alt_<?php echo esc_attr($field->slug); ?>" class="form-control">
                <input <?php echo $prevent_edit_field ? 'disabled' : ''; ?> type="hidden" id="mw_datepicker_alt_<?php echo esc_attr($field->slug); ?>" name="mw_fields[<?php echo esc_attr($field->slug); ?>]">
            </div>
        <?php endif;
    }
    static function render_file_uploader_field($view, $field, $current_user, $args)
    {
        $value = $current_user ? $current_user->{$field->slug} : '';
        $type_icon = file_uploader::get_type_icon($value);
        $meta = isset($field->meta) ? unserialize($field->meta) : false;
        $prevent_edit_field = !\mihanpanel\app\users::is_admin_user() && isset($meta['data']['prevent_edit_field']) && $value;
        if ($view == 'register-form') : ?>
            <label for="<?php echo esc_attr($field->slug) ?>">
                <span class="mwpl-icon">
                    <img src="<?php echo \mihanpanel\app\assets::get_image_url('more-icon', 'svg') ?>" alt="password-icon">
                </span>
                <span class="mwpl-field">
                    <span><?php esc_html_e('Choose File', 'mihanpanel'); ?></span>
                    <input <?php echo $prevent_edit_field ? 'disabled' : ''; ?> type="file" name="mw_fields_files[<?php echo esc_attr($field->slug); ?>]" id="mw_fields_file_uploader_<?php echo esc_attr($field->slug); ?>" class="form-control">
                </span>
            </label>
        <?php else : ?>
            <div class="mw_file_uploader">
                <div <?php echo $prevent_edit_field ? 'disabled' : ''; ?> class="type_preview_box" mw_field_slug="<?php echo esc_attr($field->slug); ?>">
                    <?php if ($view == 'edit-profile' || $view == 'wp-edit-profile') : ?>
                        <span class="type-icon"><i class="<?php echo esc_attr($type_icon); ?>"></i></span>
                        <?php if (!$prevent_edit_field && $field->required == 'no') : ?>
                            <span class="remove <?php echo $value ? 'has-file' : ''; ?>">
                                <span class="text"><?php esc_html_e('Delete File', 'mihanpanel'); ?></span>
                                <span class="icon"><i class="dashicons dashicons-no"></i></span>
                            </span>
                        <?php endif; ?>
                        <?php if ($value && current_user_can('manage_options')) :
                            $link = file_uploader::get_file_link($value);
                        ?>
                            <a href="<?php echo esc_url($link) ?>" target="_blank" class="show-file hover">
                                <span class="icon"><i class="mw_icon dashicons dashicons-archive"></i></span>
                                <span class="text"><?php esc_html_e('Show File', 'mihanpanel'); ?></span>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <label for="mw_fields_file_uploader_<?php echo esc_attr($field->slug); ?>"><?php esc_html_e('Choose File', 'mihanpanel'); ?></label>
                    <input <?php echo $prevent_edit_field ? 'disabled' : ''; ?> type="file" name="mw_fields_files[<?php echo esc_attr($field->slug); ?>]" id="mw_fields_file_uploader_<?php echo esc_attr($field->slug); ?>" class="form-control">
                    <input <?php echo $prevent_edit_field ? 'disabled' : ''; ?> type="hidden" class="remove_file_field_value" name="remove_file_field_value[<?php echo esc_attr($field->id) ?>]">
                </div>
            </div>
<?php endif;
    }
    static function handle_placeholder_value($placeholder, $field_meta, $field)
    {
        if (!$field_meta) {
            return $placeholder;
        }
        return isset($field_meta['placeholder']) ? $field_meta['placeholder'] : '';
    }
}
