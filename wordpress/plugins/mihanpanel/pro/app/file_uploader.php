<?php
namespace mihanpanel\pro\app;

use mihanpanel\app\notice;

class file_uploader
{
    static function get_upload_dir_path()
    {
        $upload_dir = wp_upload_dir();
        $upload_dir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'mihanpanel' . DIRECTORY_SEPARATOR;
        if(!file_exists($upload_dir))
        {
            @mkdir($upload_dir);
        }
        return $upload_dir;
    }
    static function get_upload_dir_url()
    {
        $upload_dir = wp_upload_dir();
        $upload_dir = $upload_dir['baseurl'] . '/mihanpanel/';
        return $upload_dir;
    }
    static function get_error_text($code)
    {
        $codes = [
            1 => esc_html__('The uploaded file exceeds the upload_max_filesize directive in php.ini', 'mihanpanel'),
            2 => esc_html__('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form', 'mihanpanel'),
            3 => esc_html__('The uploaded file was only partially uploaded', 'mihanpanel'),
            4 => esc_html__('No file was uploaded', 'mihanpanel'),
            6 => esc_html__('Missing a temporary folder', 'mihanpanel'),
            7 => esc_html__('Failed to write file to disk.', 'mihanpanel'),
            8 => esc_html__('A PHP extension stopped the file upload.', 'mihanpanel'),
        ];
        return isset($codes[$code]) ? $codes[$code] : false;
    }
    static function get_types($sort=false)
    {
        $image = [
            'image/gif' => 'dashicons dashicons-format-image',
            'image/jpeg' => 'dashicons dashicons-format-image',
            'image/png' => 'dashicons dashicons-format-image',
            'image/webp' => 'dashicons dashicons-format-image',
        ];
        $video =
        [
            'video/x-msvideo' => 'dashicons dashicons-video-alt2',
            'video/mp4' => 'dashicons dashicons-video-alt2',
            'video/quicktime' => 'dashicons dashicons-video-alt2',
            'video/x-ms-wmv' => 'dashicons dashicons-video-alt2',
            'video/x-ms-asf' => 'dashicons dashicons-video-alt2',
            'video/mpeg' => 'dashicons dashicons-video-alt2',
            'video/ogg' => 'dashicons dashicons-video-alt2',
            'video/webm' => 'dashicons dashicons-video-alt2',
            'video/3gpp' => 'dashicons dashicons-video-alt2',
        ];
        $audio =
        [
            'audio/aac' => 'dashicons dashicons-format-audio',
            'audio/mpeg' => 'dashicons dashicons-format-audio',
            'audio/ogg' => 'dashicons dashicons-format-audio',
            'audio/wav' => 'dashicons dashicons-format-audio',
            'audio/webm' => 'dashicons dashicons-format-audio',
            'audio/3gpp' => 'dashicons dashicons-format-audio',
        ];
        $text =
        [
            'text/css' => 'dashicons dashicons-format-aside',
            'text/csv' => 'dashicons dashicons-format-aside',
            'application/msword' => 'dashicons dashicons-format-aside',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'dashicons dashicons-format-aside',
            'text/plain' => 'dashicons dashicons-format-aside',
        ];
        $compressed =
        [
            // compressed file
            'application/zip' => 'dashicons dashicons-format-aside',
            'application/gzip' => 'dashicons dashicons-format-aside',
            'application/vnd.rar' => 'dashicons dashicons-format-aside',
        ];
        $pdf =
        [
            'application/pdf' => 'dashicons dashicons-format-aside',
        ];
        if($sort)
        {
            return[
                'image' => $image,
                'audio' => $audio,
                'video' => $video,
                'text' => $text,
                'compressed' => $compressed,
                'pdf' => $pdf
            ];
        }
        return array_merge($image, $audio, $video, $text, $compressed, $pdf);
    }
    static function validate_file_type($field, $type)
    {
        // default types
        $default_types = self::get_types(true);
        // types white list
        $meta = $field->meta;
        $meta = unserialize($meta);
        $white_list = [];
        foreach($meta['file_type'] as $type_data)
        {
            $white_list = array_merge($white_list, $default_types[$type_data]);
        }
        return in_array($type, array_keys($white_list));
    }
    static function validate_file_size($field, $size)
    {
        $meta = $field->meta;
        $meta = unserialize($meta);
        $valid_size = isset($meta['file_size']) ? intval($meta['file_size']) * 1024 * 1024 : 0;
        return $valid_size >= $size;
    }
    static function get_type_icon($file_name)
    {
        if(!$file_name)
        {
            return false;
        }
        $types = self::get_types();
        $file_path = self::get_upload_dir_path() . $file_name;
        if(!file_exists($file_path))
        {
            return false;
        }
        $mime_type_content = mime_content_type($file_path);
        return isset($types[$mime_type_content]) ? $types[$mime_type_content] : 'dashicons dashicons-format-aside';
    }
    static function get_all_fields($cols=[])
    {
        global $wpdb;
        $tbl_name = $wpdb->prefix . 'mihanpanelfields';
        $cols = $cols && is_array($cols) ? implode(', ', $cols) : '*';
        $sql = "SELECT {$cols} FROM {$tbl_name} WHERE type='file_uploader'";
        return $wpdb->get_results($sql);
    }
    static function upload_field_handle($data, $uid)
    {
        $all_file_uploader_fields = self::get_all_fields();
        foreach($all_file_uploader_fields as $field)
        {
            // check has last value
            $last_value = get_user_meta($uid, $field->slug, true);
            $field_meta = isset($field->meta) ? unserialize($field->meta) : false;
            $prevent_edit_fields = !\mihanpanel\app\users::is_admin_user() && $last_value && $field_meta['data']['prevent_edit_field'];
            if($prevent_edit_fields)
            {
                continue;
            }
            // check is any data send
            if(!$data)
            {
                if(!$last_value && $field->required == 'yes' && !current_user_can('manage_options'))
                {
                    $msg = sprintf(esc_html__('%1$s must not be empty.', 'mihanpanel'), $field->label);
                    notice::add_multiple_notice('error', $msg);
                }
                continue;
            }
            // check is has any error
            if($error_code = $data['error'][$field->slug])
            {
                if($error_code == 4)
                {
                    if(!$last_value && $field->required == 'yes' && !current_user_can('manage_options'))
                    {
                        $msg = sprintf(esc_html__('%1$s must not be empty.', 'mihanpanel'), $field->label);
                        notice::add_multiple_notice('error', $msg);
                    }
                }else{
                    $msg = self::get_error_text($error_code);
                    $msg = sprintf('%1$s: %2$s', $field->label, $msg);
                    notice::add_multiple_notice('error', $msg);
                }
                continue;
            }
            // validate file type
            $validate_file_type = self::validate_file_type($field, $data['type'][$field->slug]);
            if(!$validate_file_type)
            {
                $msg = sprintf(esc_html__('%1$s: Invalid file type', 'mihanpanel'), $field->label);
                notice::add_multiple_notice('error', $msg);
                continue;
            }
            // validate file size
            $validate_file_size = self::validate_file_size($field, $data['size'][$field->slug]);
            if(!$validate_file_size)
            {
                $msg = sprintf(esc_html__('%1$s: Invalid file size', 'mihanpanel'), $field->label);
                notice::add_multiple_notice('error', $msg);
                continue;
            }
            // remove user last file
            $upload_path = self::get_upload_dir_path();
            if($last_value && file_exists($upload_path . $last_value))
            {
                $remove_res = @unlink($upload_path . $last_value);
                if($remove_res)
                {
                    delete_user_meta($uid, $field->slug);
                }
            }
            // upload new file
            $upload_res = self::upload_file($data['name'][$field->slug], $data['tmp_name'][$field->slug], $field->slug, $uid);
            if(!$upload_res)
            {
                $msg = sprintf(esc_html__('%1$s: Has error in upload file.', 'mihanpanel'), $field->label);
                notice::add_multiple_notice('error', $msg);
                continue;
            }
        }
    }
    static function upload_file($file_name, $file_tmp_name, $field_slug, $uid)
    {
        $extension = pathinfo($file_name, PATHINFO_EXTENSION);
        $upload_path = self::get_upload_dir_path();
        $file_name = md5($uid . $field_slug . $file_name) . '.' . $extension;
        $upload_res = move_uploaded_file($file_tmp_name, $upload_path . $file_name);
        return $upload_res ? update_user_meta($uid, $field_slug, $file_name) : false;
    }
    static function remove_handler($data, $uid)
    {
        $data = array_filter($data);
        if(!$data)
        {
            return false;
        }
        foreach($data as $item_slug)
        {
            // check is required field
            $remove_permission = self::verify_remove_file_permission($item_slug);
            if(!$remove_permission)
            {
                continue;
            }
            // remove file
            self::remove_file($remove_permission->slug, $uid);
        }
    }
    static function verify_remove_file_permission($field_slug)
    {
        global $wpdb;
        $tbl_name = $wpdb->prefix . 'mihanpanelfields';
        $sql = "SELECT slug, label, required From {$tbl_name} where slug=%s";
        $field = $wpdb->get_row($wpdb->prepare($sql, $field_slug));
        $permission = true;
        $msg = '';
        $field_meta = isset($field->meta) ? unserialize($field->meta) : false;
        $prevent_edit_fields = !\mihanpanel\app\users::is_admin_user() && isset($field_meta['data']['prevent_edit_field']);
        if($prevent_edit_fields)
        {
            return false;
        }
        if($field->required == 'yes' && !current_user_can('manage_options'))
        {
            $msg = sprintf(esc_html__('%1$s must not be empty.', 'mihanpanel'), $field->label);
            notice::add_multiple_notice('error', $msg);
            $permission = false;
        }
        return $permission ? $field : false;
    }
    static function remove_file($field_slug, $uid)
    {
        $permission = self::verify_remove_file_permission($field_slug);
        if(!$permission)
        {
            return false;
        }
        $file_name = get_user_meta($uid, $field_slug, true);
        if(!$file_name)
        {
            return false;
        }
        $file_name = self::get_upload_dir_path() . $file_name;
        if(file_exists($file_name))
        {
            @unlink($file_name);
        }
        delete_user_meta($uid, $field_slug);
    }
    static function get_file_link($file_name)
    {
        $upload_dir = self::get_upload_dir_path();
        $upload_dir_url = self::get_upload_dir_url();
        $file_path = $upload_dir . $file_name;
        return file_exists($file_path) ? $upload_dir_url . $file_name : false;
    }
}