<?php
namespace mihanpanel\pro\app\form;

use mihanpanel\app\adapter\woo;
use mihanpanel\app\fields_control;use mihanpanel\app\tools;
use mihanpanel\app\user_fields as liteUserFields;
use mihanpanel\pro\app\user_fields;

class admin_user_fields
{
    static function renderDefaultWordpressFields()
    {
        $fields = user_fields::getEditProfileWordpressDefaultFields();
        $activeFields = \mihanpanel\pro\app\user_fields::getActiveWordpressDefaultFields();
        ?>
        <div>
            <h2 drop_down_target_id="wordpress_field_form_wrapper" class="mwpl_drop_down"><?php esc_html_e("Wordpress Fields", 'mihanpanel'); ?><span class="mw_icon dashicons dashicons-arrow-down-alt2"></span></h2>
            <form mwpl_drop_down_content id="wordpress_field_form_wrapper" method="post">
                <?php foreach($fields as $key => $value): ?>
                    <p class="item">
                        <input <?php checked(true, in_array($key, $activeFields))?> name="mw_wp_fields[<?php echo esc_attr($key) ?>]" type="checkbox" id="<?php esc_attr(printf("mw_wp_%s", $key)); ?>">
                        <label for="<?php printf("mw_wp_%s", $key); ?>"><?php echo esc_html($value); ?></label>
                    </p>
                <?php endforeach; ?>
                <input type="submit" name="save_wordpress_fields_data" value="<?php esc_attr_e("Save", 'mihanpanel'); ?>">
            </form>
        </div>
        <?php
    }
    static function render_woocommerce_fields()
    {
        if(!tools::is_woocommerce_active())
        {
            return false;
        }
        $fields = woo::get_billing_fields();
        $active_fields = woo::get_active_fields();
        ?>
        <div class="mwpl_woocommerce_fields">
            <h2 drop_down_target_id="woocommerce_field_form_wrapper" class="mwpl_drop_down"><?php esc_html_e("Woocommerce Fields", 'mihanpanel'); ?><span class="mw_icon dashicons dashicons-arrow-down-alt2"></span></h2>
            <form mwpl_drop_down_content id="woocommerce_field_form_wrapper" method="post">
                <?php foreach($fields as $key => $field):
                    $label = isset($field['label']) && $field['label'] ? $field['label'] : false;
                    if(!$label && $key == 'billing_address_2')
                    {
                        $label = esc_html__("Address 2", 'mihanpanel');
                    }
                    ?>
                    <p class="item">
                        <input <?php checked(true, in_array($key, $active_fields)); ?> name="mw_wc_fields[<?php echo esc_attr($key) ?>]" type="checkbox" id="<?php esc_attr(printf("mw_wc_%s", $key)); ?>">
                        <label for="<?php printf("mw_wc_%s", $key); ?>"><?php echo esc_html($label); ?></label>
                    </p>
                <?php endforeach; ?>
                <input type="submit" name="save_woocommerce_fields_data" value="<?php esc_attr_e("Save", 'mihanpanel'); ?>">
            </form>
        </div>
        <hr>
        <?php
    }
    static function handle_form_submit()
    {
        if(isset($_POST['save_woocommerce_fields_data']))
        {
            self::save_woocommerce_fields();
        }
        if(isset($_POST['save_wordpress_fields_data']))
        {
            self::saveWordpressDefaultFields();
        }
    }
    static function saveWordpressDefaultFields()
    {
        if(!isset($_POST['mw_wp_fields']))
        {
            // remove
            \mihanpanel\pro\app\user_fields::clearActiveWordpressDefaultFields();
            return false;
        }
        $sanitized_keys = [];
        foreach($_POST['mw_wp_fields'] as $key => $value)
        {
            $sanitized_keys[] = sanitize_text_field($key);
        }
        \mihanpanel\pro\app\user_fields::setActiveWordpressDefaultFields($sanitized_keys);
    }
    static function save_woocommerce_fields()
    {
        if(!isset($_POST['mw_wc_fields']))
        {
            // remove
            \mihanpanel\pro\app\adapter\woo::clear_active_fields();
            return false;
        }
        $sanitized_keys = [];
        foreach($_POST['mw_wc_fields'] as $key => $value)
        {
            $sanitized_keys[] = sanitize_text_field($key);
        }
        \mihanpanel\pro\app\adapter\woo::set_active_fields($sanitized_keys);
    }
    static function filter_user_fields_meta($meta, $form_data=null)
    {
        if(!$form_data)
        {
            $form_data = $_POST;
        }
        if($form_data['type'] == 'selectbox' || $form_data['type'] == 'radio')
        {
            $meta = self::select_box_meta($form_data['sub_item']);
        }
        if ($form_data['type'] == 'file_uploader') {
            $meta = self::file_uploader_meta($form_data['file_meta']);
        }
        if($form_data['mwpl_where_render'])
        {
            $meta = unserialize($meta);
            $meta = $meta ? $meta : [];
            if($form_data['mwpl_where_render'] == 'just_in_profile')
            {
                $meta['data']['just_in_profile'] = true;                
            }
            if($form_data['mwpl_where_render'] == 'just_in_register_form')
            {
                $meta['data']['just_in_register_form'] = true;
            }
            $meta = serialize($meta);
        }
        if(isset($form_data['mwpl_prevent_edit_this_field']))
        {
            $meta = unserialize($meta);
            $meta = $meta ? $meta : [];
            $meta['data']['prevent_edit_field'] = true;
            $meta = serialize($meta);
        }
        if (array_key_exists('field_icon', $form_data)){
            $meta = unserialize($meta);
            $meta = $meta ? $meta : [];
            $meta['data']['field_icon'] = $form_data['field_icon'];
            $meta = serialize($meta);
        }
        $meta = self::placeholder_box_meta($meta, $form_data);
        return $meta;
    }
    static function placeholder_box_meta($meta, $form_data)
    {
        $type_white_list = [
            'text',
            'textarea',
            'email',
            'number'
        ];
        if(in_array($form_data['type'], $type_white_list))
        {
            $meta = unserialize($meta);
            $meta = $meta ? $meta : [];
            $meta['placeholder'] = $form_data['mwpl_field_placeholder'];
            $meta = serialize($meta);
        }
        return $meta;
    }
    static function select_box_meta($select_type)
    {
        $data = [];
        if(!$select_type || !is_array($select_type) || tools::is_empty_array($select_type))
        {
            return false;
        }
        foreach($select_type as $id => $field)
        {
            if($field['id'] && $field['title'])
            {
                $data[$id]['id'] = sanitize_text_field($field['id']);
                $data[$id]['title'] = sanitize_text_field($field['title']);
            }
        }
        return serialize($data);
    }
    static function file_uploader_meta($file_meta)
    {
        if(!$file_meta || !is_array($file_meta))
        {
            return false;
        }
        return serialize($file_meta);
    }

    static function render_meta_after_render_user_field($field)
    {
        $field_id = isset($field->id) ? 'meta_fields_wrapper_' . $field->id : false;
        echo '<div id="'.$field_id.'" mwpl_drop_down_content class="meta_fields_wrapper">';
        self::render_extra_fields($field);
        self::render_selectbox_fields($field);
        self::render_file_uploader_fields($field);

        echo '</div>';
    }

    public static function render_uploader_icon_after_render_user_field($field)
    {
        $meta = unserialize($field->meta);
        $meta = $meta ? $meta : [];
        $field_icon = isset($meta['data']) && isset($meta['data']['field_icon']) ? $meta['data']['field_icon'] : null;
        ?>
        <div class="row">
            <?php fields_control::renderControl(fields_control::ICON_UPLOADER, ['value' => $field_icon]); ?>
        </div>
    <?php
    }

    public static function render_uploader_icon_after_render_new_user_field()
    {
        ?>
        <div class="row">
            <?php fields_control::renderControl(fields_control::ICON_UPLOADER); ?>
        </div>
    <?php
    }

    static function render_file_uploader_fields($field)
    {
        $display = $field->type == 'file_uploader' ? 'block' : 'none';
        $meta_data = unserialize($field->meta);
        if(!$meta_data || $field->type != 'file_uploader')
        {
            $meta_data = [];
        }
        ?>
            <div type_file_uploader class="field-meta-wrapper file-uploader" style="display: <?php echo esc_attr($display); ?>;">
                <div class="mw_sub_row">
                    <h3 class="sub_row_title"><?php esc_html_e("Allow upload this types", 'mihanpanel'); ?></h3>
                </div>
                <div class="mw_sub_row">
                    <div class="mw_td mw_small_width"></div>
                    <div class="field">
                        <input <?php checked(true, in_array('audio', isset($meta_data['file_type']) ? $meta_data['file_type'] : [])); ?> type="checkbox" id="file_type_audio_<?php echo esc_attr($field->id); ?>" name="file_meta[file_type][]" value="audio">
                        <label for="file_type_audio_<?php echo esc_attr($field->id); ?>"><?php _e('Audio', 'mihanpanel'); ?></label>
                    </div>

                    <div class="field">
                        <input <?php checked(true, in_array('image', isset($meta_data['file_type']) ? $meta_data['file_type'] : [])); ?> type="checkbox" id="file_type_image_<?php echo esc_attr($field->id); ?>" name="file_meta[file_type][]" value="image">
                        <label for="file_type_image_<?php echo esc_attr($field->id); ?>"><?php esc_html_e('Image', 'mihanpanel'); ?></label>
                    </div>
                    <div class="field">
                        <input type="checkbox" <?php checked(true, in_array('video', isset($meta_data['file_type']) ? $meta_data['file_type'] : []))?> id="file_type_video_<?php echo esc_attr($field->id)?>" name="file_meta[file_type][]" value="video">
                        <label for="file_type_video_<?php echo esc_attr($field->id); ?>"><?php esc_html_e('Video', 'mihanpanel'); ?></label>
                    </div>
                    <div class="field">
                        <input <?php checked(true, in_array('pdf', isset($meta_data['file_type']) ? $meta_data['file_type'] : [])); ?> type="checkbox" id="file_type_pdf_<?php echo esc_attr($field->id); ?>" name="file_meta[file_type][]" value="pdf">
                        <label for="file_type_pdf_<?php echo esc_attr($field->id); ?>"><?php esc_html_e('PDF', 'mihanpanel'); ?></label>
                    </div>
                    <div class="field">
                        <input <?php checked(true, in_array('compressed', isset($meta_data['file_type']) ? $meta_data['file_type'] : [])); ?> type="checkbox" id="file_type_compressed_<?php echo esc_attr($field->id); ?>" name="file_meta[file_type][]" value="compressed">
                        <label for="file_type_compressed_<?php echo esc_attr($field->id); ?>"><?php esc_html_e('Compressed', 'mihanpanel'); ?></label>
                    </div>
                    <div class="field">
                        <input <?php checked(true, in_array('text', isset($meta_data['file_type']) ? $meta_data['file_type'] : [])); ?> type="checkbox" id="file_type_text_<?php echo esc_attr($field->id); ?>" name="file_meta[file_type][]" value="text">
                        <label for="file_type_text_<?php echo esc_attr($field->id); ?>"><?php esc_html_e('Text', 'mihanpanel'); ?></label>                        
                    </div>

                    
                </div>
                
                <div class="mw_sub_row">
                    <h3 class="sub_row_title"><?php esc_html_e('Maximum file size', 'mihanpanel');echo ' (', __('MB', 'mihanpanel'), ')'; ?></h3>
                </div>
                <div class="mw_sub_row">
                    <div class="mw_td mw_small_width"></div>
                    <div class="mw_td mw_large_width">
                        <input type="number" name="file_meta[file_size]" min="1" value="<?php echo isset($meta_data['file_size']) ? esc_attr($meta_data['file_size']) : ''; ?>">
                    </div>
                </div>
            </div>
        <?php
    }
    static function render_selectbox_fields($field)
    {
        $display = $field->type == 'selectbox' || $field->type == 'radio' ? 'block' : 'none';
        $meta_data = unserialize($field->meta);
        if(!$meta_data && !is_array($meta_data))
        {
            $meta_data = [];
        }
        $count = count($meta_data);
        $key = $count ? $count - 1 : 0;
        $type = $field->type;
        $title = $type == 'selectbox' || $type == 'radio' ? liteUserFields::get_field_type_title($field->type) : '';
        ?>
        <div type_selectbox type_radio class="field-meta-wrapper" style="display: <?php echo esc_attr($display); ?>">
            <input type="hidden" name="select-index" value="<?php echo esc_attr($key);?>">            
            <div class="add-new-wrapper mw_sub_row">
                <div class="add-new">
                    <a href="#"><i class="mw_icon dashicons dashicons-plus-alt"></i></a>
                </div>
            </div>
            <div class="mw_sub_row">
                <h3 class="sub_row_title"><?php echo esc_html($title);?></h3>
            </div>
            <div class="mw_sub_row">
                <div class="mw_td mw_small_width"></div>
                <div class="mw_td mw_large_width">
                    <span><?php esc_html_e('Field id', 'mihanpanel'); ?></span>
                </div>
                <div class="mw_td mw_large_width">
                    <span><?php esc_html_e('Title', 'mihanpanel'); ?></span>
                </div>
            </div>
        <?php
        if($meta_data && ($type == 'selectbox'  || $type == 'radio')):
            foreach($meta_data as  $index => $item)
            {
                ?>
                <div class="mw_sub_row field-item">
                    <div class="mw_td mw_small_width"></div>
                    <div class="mw_td mw_large_width id-field">
                        <input type="text" value="<?php echo isset($item['id']) ? esc_attr($item['id']) : ''; ?>" name="sub_item[<?php echo esc_attr($index); ?>][id]">
                    </div>
                    <div class="mw_td mw_large_width title-field">
                        <input type="text" value="<?php echo isset($item['title']) ? esc_attr($item['title']) : ''; ?>" name="sub_item[<?php echo esc_attr($index); ?>][title]">
                    </div>
                    <div class="mw_td">
                        <a class="remove-btn" href="#"><i class="mw_icon dashicons dashicons-trash"></i></a>
                    </div>
                </div>
                <?php
            }
        else:
            ?>
            <div class="mw_sub_row field-item">
                <div class="mw_td mw_small_width"></div>
                <div class="mw_td mw_large_width id-field">
                    <input type="text" name="sub_item[0][id]">
                </div>
                <div class="mw_td mw_large_width title-field">
                    <input type="text" name="sub_item[0][title]">
                </div>
                <div class="mw_td">
                    <a class="remove-btn" href="#"><i class="mw_icon dashicons dashicons-trash"></i></a>
                </div>
            </div>
            <?php
        endif;
        echo '</div>';
    }
    static function render_extra_fields($field)
    {
        echo '<div class="extra_fields_wrapper">';
        do_action('mwpl_option_panel/user_fields/edit_form/before_render_submit_button', $field);
        echo '</div>';
    }
    static function render_meta_fields_in_user_fields_new_record_form()
    {
        ?>
        <div type_selectbox type_radio class="field-meta-wrapper" style="display: none;">
            <input type="hidden" name="select-index" value="0">            
            <div class="add-new-wrapper mw_sub_row">
                <div class="add-new">
                    <a href="#"><i class="mw_icon dashicons dashicons-plus-alt"></i></a>
                </div>
            </div>
            <div class="mw_sub_row">
                <h3 class="sub_row_title"></h3>
            </div>
            <div class="mw_sub_row">
                <div class="mw_td"></div>
                <div class="mw_td">
                    <span><?php esc_html_e('Field id', 'mihanpanel'); ?></span>
                </div>
                <div class="mw_td">
                    <span><?php esc_html_e('Title', 'mihanpanel'); ?></span>
                </div>
            </div>
            <div class="mw_sub_row field-item">
                <div class="mw_td"></div>
                <div class="mw_td id-field">
                    <input type="text" name="sub_item[0][id]">
                </div>
                <div class="mw_td title-field">
                    <input type="text" name="sub_item[0][title]">
                </div>
                <div class="mw_td">
                    <a class="remove-btn" href="#"><i class="mw_icon dashicons dashicons-trash"></i></a>
                </div>
            </div>
                
        </div>
        <div type_file_uploader class="field-meta-wrapper file-uploader" style="display: none;">
            <div class="mw_sub_row">
                <h3 class="sub_row_title"><?php _e("Allow upload this types", 'mihanpanel'); ?></h3>
            </div>
            <div class="mw_sub_row">
                <div class="mw_td"></div>
                <div class="field">
                    <input type="checkbox" id="new_file_type_audio" name="file_meta[file_type][]" value="audio">
                    <label for="new_file_type_audio"><?php esc_html_e('Audio', 'mihanpanel'); ?></label>
                </div>

                <div class="field">
                    <input type="checkbox" id="new_file_type_image" name="file_meta[file_type][]" value="image">
                    <label for="new_file_type_image"><?php esc_html_e('Image', 'mihanpanel'); ?></label>
                </div>
                <div class="field">
                    <input type="checkbox" id="new_file_type_video" name="file_meta[file_type][]" value="video">
                    <label for="new_file_type_video"><?php esc_html_e('Video', 'mihanpanel'); ?></label>
                </div>
                <div class="field">
                    <input type="checkbox" id="new_file_type_pdf" name="file_meta[file_type][]" value="pdf">
                    <label for="new_file_type_pdf"><?php esc_html_e('PDF', 'mihanpanel'); ?></label>
                </div>
                <div class="field">
                    <input type="checkbox" id="new_file_type_compressed" name="file_meta[file_type][]" value="compressed">
                    <label for="new_file_type_compressed"><?php esc_html_e('Compressed', 'mihanpanel'); ?></label>
                </div>
                <div class="field">
                    <input type="checkbox" id="new_file_type_text" name="file_meta[file_type][]" value="text">
                    <label for="new_file_type_text"><?php esc_html_e('Text', 'mihanpanel'); ?></label>                        
                </div>
            </div>
            <div class="mw_sub_row">
                <h3 class="sub_row_title"><?php esc_html_e('Maximum file size', 'mihanpanel');echo ' (', __('MB', 'mihanpanel'), ')'; ?></h3>
            </div>
            <div class="mw_sub_row">
                <div class="mw_td"></div>
                <div class="mw_td">
                    <input type="number" name="file_meta[file_size]" min="1" value="1">
                </div>
            </div>
        </div>
        <div type_text type_textarea type_email type_number class="row field-meta-wrapper">
            <label class="block" for="mwpl_field_placeholder"><?php esc_html_e('Placeholder', 'mihanpanel')?></label>
            <input type="text" name="mwpl_field_placeholder" id="mwpl_field_placeholder">
        </div>
        <?php
    }
    static function user_fields_extra_fields_edit_form($field)
    {
        self::is_prevent_edit_this_field($field);
        self::render_where_show_field($field);
        self::render_place_holder_field($field);
    }
    static function user_fields_extra_fields_new_record_form()
    {
        self::is_prevent_edit_this_field(false);
        self::render_where_show_field(false);
    }
    static function render_where_show_field($field)
    {
        $value = '';
        $field_id = '';
        $just_in_profile = '';
        $just_in_register = '';
        if($field)
        {
            $field_meta = unserialize($field->meta);
            $just_in_profile = isset($field_meta['data']['just_in_profile']) ? true : false;
            $just_in_register = isset($field_meta['data']['just_in_register_form']);
            $field_id = isset($field->id) ? '_' . $field->id : '';
        }
        ?>
        <div class="row">
            <label class="block" for="mwpl_where_render<?php echo esc_attr($field_id)?>"><?php esc_html_e('Where show field', 'mihanpanel')?></label>
            <select name="mwpl_where_render" id="mwpl_where_render<?php echo esc_attr($field_id)?>">
                <option selected value="0"><?php esc_html_e('Profile & Register', 'mihanpanel')?></option>
                <option <?php selected($just_in_profile); ?> value="just_in_profile"><?php esc_html_e('Just in profile', 'mihanpanel')?></option>
                <option <?php selected($just_in_register)?> value="just_in_register_form"><?php esc_html_e('Just in register form', 'mihanpanel')?></option>
            </select>
        </div>
        <?php
    }
    static function is_prevent_edit_this_field($field)
    {
        $value = '';
        $field_id = '';
        if($field)
        {
            $field_meta = unserialize($field->meta);
            $value = isset($field_meta['data']['prevent_edit_field']);
            $field_id = isset($field->id) ? '_' . $field->id : '';
        }
        ?>
        <div class="row">
            <label for="mwpl_prevent_edit_this_field<?php echo esc_attr($field_id);?>"><?php esc_html_e('Prevent edit this field', 'mihanpanel')?></label>
            <input <?php checked($value); ?> type="checkbox" name="mwpl_prevent_edit_this_field" id="mwpl_prevent_edit_this_field<?php echo esc_attr($field_id)?>">
        </div>
        <?php
    }
    static function render_place_holder_field($field)
    {
        $type_white_list = [
            'text',
            'textarea',
            'email',
            'number'
        ];
        $attrs = array_map(function($value){
            return 'type_'.$value;
        }, $type_white_list);
        $attrs = implode(' ', $attrs);
        
        $display = in_array($field->type, $type_white_list) ? 'block' : 'none';
        
        $value = '';
        $field_id = '';
        if($field)
        {
            $field_meta = unserialize($field->meta);
            $value = isset($field_meta['placeholder']) ? $field_meta['placeholder'] : false;
            $field_id = isset($field->id) ? '_' . $field->id : '';
        }
        
        ?>
        <div <?php echo esc_attr($attrs)?> class="row field-meta-wrapper" style="display: <?php echo esc_attr($display)?>">
            <label class="block" for="mwpl_field_placeholder<?php echo esc_attr($field_id)?>"><?php esc_html_e('Placeholder', 'mihanpanel')?></label>
            <input value="<?php echo esc_attr($value)?>" type="text" name="mwpl_field_placeholder" id="mwpl_field_placeholder<?php echo esc_attr($field_id)?>">
        </div>
        <?php
    }
}