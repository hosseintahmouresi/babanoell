<?php
namespace mihanpanel\pro\app\form;
class admin_tabs_fields
{
    static function render_upload_button()
    {
        ?>
        <span tooltip="<?php esc_attr_e('Upload', 'mihanpanel')?>" mwpl_media_uploader class="mwpl_action_btn mw_icon dashicons dashicons-cloud-upload"></span>
        <?php
    }
    static function filter_tabs_fields_meta($meta, $field_data=null)
    {
        if(!$field_data)
        {
            $field_data = $_POST;
        }
        $meta = $meta && is_serialized($meta) ? unserialize($meta) : $meta;
        if(!$meta || !is_array($meta))
        {
            $meta = [];
        }
        $meta = self::handle_field_id_meta($meta, $field_data);
        $meta = self::handle_show_by_roles_meta($meta, $field_data);
        if($meta)
        {
            $meta = serialize($meta);
        }
        return $meta;
    }
    static function handle_field_id_meta($meta, $field_data)
    {
        $field_id = isset($field_data['field_choosen_id']) && $field_data['field_choosen_id'] ? sanitize_text_field($field_data['field_choosen_id']) : false;
        if($field_id)
        {
            $meta['field_id'] = $field_id;
        }
        return $meta;
    }
    static function handle_show_by_roles_meta($meta, $field_data)
    {
        if(!isset($field_data['is_show_by_roles']) || !isset($field_data['show_for_this_roles']))
        {
            return $meta;
        }
        $meta['show_by_roles'] = $field_data['show_for_this_roles'];
        return $meta;
    }

    static function render_meta_fields_in_tabs_fields_new_record_form()
    {
        self::render_field_id_in_new_record_form();
        self::render_show_by_roles_in_new_record_form();
    }
    static function render_field_id_in_new_record_form()
    {
        ?>
        <div class="row">
            <label for="field_choosen_id"><?php esc_html_e('Field ID', 'mihanpanel')?></label>
            <input type="text" id="field_choosen_id" name="field_choosen_id" placeholder="<?php esc_attr_e("Field ID", "mihanpanel"); ?>"/>
        </div>
        <?php
    }
    static function render_show_by_roles_in_new_record_form()
    {
        ?>
        <div class="row">
            <label for="is_show_by_roles"><?php esc_html_e('Show by roles', 'mihanpanel')?></label>
            <input name="is_show_by_roles" type="checkbox" id="is_show_by_roles">
        </div>
        <div class="row" depends="is_show_by_roles">
        <?php $all_roles = \mihanpanel\app\users::get_all_roles_name();?>
            <?php foreach($all_roles as $role_key => $role_value):
                if($role_key == 'administrator')
                {
                    continue;
                }
                ?>
                <div>
                    <input type="checkbox" id="new_record_show_for_this_roles_<?php echo esc_attr($role_key)?>" name="show_for_this_roles[]" value="<?php echo esc_attr($role_key)?>">
                    <label for="new_record_show_for_this_roles_<?php echo esc_attr($role_key)?>"><?php echo esc_html(translate_user_role($role_value))?></label>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
}