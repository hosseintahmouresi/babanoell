<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_get_admin_notifications', 'mlr_get_admin_notifications_callback');

function mlr_get_admin_notifications_callback() {
    // بررسی نانس برای امنیت
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mlr_ajax_nonce_action')) {
         wp_send_json_error(['message' => 'Invalid nonce']);
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'mlr_admin_notifications';

    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        wp_send_json_success(['unread_count' => 0, 'notifications' => []]);
        return;
    }

    $user_id = get_current_user_id();

    // دریافت ۱۰۰ اعلان آخر (افزایش ظرفیت برای اطمینان)
    $notifications = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 100");

    $unread_count = 0;
    $output_notifications = [];

    // فرمت‌دهی برای نمایش
    foreach ($notifications as $n) {
        // Check read status for current user (read_by may be missing on old DB)
        $read_by = isset($n->read_by) && $n->read_by !== '' && $n->read_by !== null
            ? json_decode($n->read_by, true)
            : [];
        if (!is_array($read_by)) {
            $read_by = [];
        }
        
        $is_read_by_me = in_array($user_id, $read_by);
        if (!$is_read_by_me) {
            $unread_count++;
        }
        $n->is_read = $is_read_by_me ? 1 : 0; // Override global is_read with user specific status

        $n->time_ago = human_time_diff(strtotime($n->created_at), current_time('timestamp')) . ' پیش';
        // تعیین آیکون بر اساس نوع
        switch ($n->type) {
            case 'new_loan': $n->icon = 'dashicons-money-alt'; break;
            case 'new_coop': $n->icon = 'dashicons-groups'; break;
            case 'new_message': $n->icon = 'dashicons-email-alt'; break;
            case 'status_change': $n->icon = 'dashicons-update'; break;
            case 'expert_review': $n->icon = 'dashicons-businessman'; break;
            default: $n->icon = 'dashicons-bell'; break;
        }
        $output_notifications[] = $n;
    }

    wp_send_json_success([
        'unread_count' => $unread_count,
        'notifications' => $output_notifications
    ]);
}

add_action('wp_ajax_mlr_mark_notifications_read', 'mlr_mark_notifications_read_callback');

function mlr_mark_notifications_read_callback() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mlr_ajax_nonce_action')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
        return;
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'mlr_admin_notifications';
    $user_id = get_current_user_id();
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id > 0) {
        // Mark single notification as read for this user
        $row = $wpdb->get_row($wpdb->prepare("SELECT read_by FROM $table WHERE id = %d", $id));
        if ($row) {
            $read_by = !empty($row->read_by) ? json_decode($row->read_by, true) : [];
            if (!is_array($read_by)) $read_by = [];
            if (!in_array($user_id, $read_by)) {
                $read_by[] = $user_id;
                $wpdb->update($table, ['read_by' => json_encode($read_by)], ['id' => $id]);
            }
        }
    } else {
        // Mark all (last 100) as read for this user
        $notifications = $wpdb->get_results("SELECT id, read_by FROM $table ORDER BY created_at DESC LIMIT 100");
        foreach ($notifications as $n) {
            $read_by = !empty($n->read_by) ? json_decode($n->read_by, true) : [];
            if (!is_array($read_by)) $read_by = [];
            if (!in_array($user_id, $read_by)) {
                $read_by[] = $user_id;
                $wpdb->update($table, ['read_by' => json_encode($read_by)], ['id' => $n->id]);
            }
        }
    }

    wp_send_json_success();
}