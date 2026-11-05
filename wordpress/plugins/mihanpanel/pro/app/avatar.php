<?php
namespace mihanpanel\pro\app;
use WP_Query;

class avatar
{
    static function delete_user_previous_avatar($user_id)
    {
        $args = [
            'author' => $user_id,
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => '-1',
            'meta_query' => [
                [
                    'key' => 'mw_attachment_avatar',
                    'value' => '',
                    'compare' => '!='
                ]
            ]
        ];
        $request = new WP_Query($args);
        if($request->have_posts())
        {
            while($request->have_posts())
            {
                $request->the_post();
                wp_delete_attachment(get_the_ID());
            }
        }
    }
    static function upload_and_set_avatar($file_input_index, $user_id)
    {
        if(!options::is_mihan_avatar_enable())
        {
            \mihanpanel\app\tools::do_redirect();
        }
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );

        // delete previous avatar file
        self::delete_user_previous_avatar($user_id);
        
        $attachment_id = media_handle_upload($file_input_index, null);
        if(is_wp_error($attachment_id))
        {
            $msg = esc_html__('Has error in uploading media', 'mihanpanel');
            \mihanpanel\app\notice::add_multiple_notice('error', $msg);
            return false;
        }
        // set avatar meta-key for attachment_id
        $attachment_meta_res = update_post_meta($attachment_id, 'mw_attachment_avatar', $user_id);
        // set attachment_id in user meta
        $attachment_user_meta_res = self::set_user_avatar_id($user_id, $attachment_id);
        $type = '';
        $msg = '';
        $res = $attachment_meta_res && $attachment_user_meta_res;
        if($res)
        {
            $msg = esc_html__('Avatar successfully saved!', 'mihanpanel');
            $type = 'success';
        }else{
            $msg = esc_html__('Has error in set avatar data', 'mihanpanel');
            $type = 'error';
        }
        \mihanpanel\app\notice::add_multiple_notice($type, $msg);
        return $res;
    }

    static function filter_avatar_url($avatar, $id_or_email, $args)
    {
        if(!options::is_mihan_avatar_enable())
        {
            return $avatar;
        }
        if(is_a($id_or_email, 'WP_Comment'))
        {
            $id_or_email = $id_or_email->user_id;
        }
        $mw_avatar = self::get_user_avatar_url($id_or_email);
        return $mw_avatar ? $mw_avatar : $avatar;
    }
    static function set_user_avatar_id($user_id, $attachment_id)
    {
        return $attachment_id ? update_user_meta($user_id, 'mw_avatar_attachment_id', $attachment_id) : false;
    }
    static function get_user_avatar_id($uid)
    {
        return $uid ? get_user_meta($uid, 'mw_avatar_attachment_id', true) : false;
    }
    static function get_user_avatar_url($uid)
    {
        if(is_string($uid) && is_email($uid))
        {
            // get user id by email
            $uid = get_user_by('email', $uid);
            $uid = $uid ? $uid->ID : false;
        }
        $attachment_id = self::get_user_avatar_id($uid);
        $attachment_url = wp_get_attachment_image_url($attachment_id);
        return $attachment_url;
    }
    static function remove_user_avatar($uid)
    {
        if(!options::is_mihan_avatar_enable())
        {
            \mihanpanel\app\tools::do_redirect();
        }
        $avatar_id = self::get_user_avatar_id($uid);
        // remove avatar user meta
        $remove_user_meta_res = delete_user_meta($uid, 'mw_avatar_attachment_id');
        
        // delete attachment post
        $remove_attachment_res = wp_delete_attachment($avatar_id);
        if($remove_user_meta_res && $remove_attachment_res)
        {
            $msg = esc_html__('Avatar successfully deleted!', 'mihanpanel');
            $type = 'success';
        }else{
            $msg = esc_html__('Has error in deleting avatar.', 'mihanpanel');
            $type = 'error';
        }
        \mihanpanel\app\notice::add_multiple_notice($type, $msg);
        \mihanpanel\app\tools::do_redirect();
    }
}