<?php
if (!defined('ABSPATH')) { exit; }

add_action('wp_ajax_mlr_add_comment', 'mlr_add_comment_callback');

function mlr_add_comment_callback() {
    check_ajax_referer('mlr_ajax_nonce_action', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'شما دسترسی لازم برای این عملیات را ندارید.']);
        return;
    }

    $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
    $comment_text = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';
    if (!$request_id) {
        wp_send_json_error(['message' => 'شناسه درخواست نامعتبر است.']);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'loan_requests';
    if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM `{$table_name}` WHERE id = %d", $request_id))) {
        wp_send_json_error(['message' => 'درخواست یافت نشد.']);
        return;
    }

    // Authorization/request existence is complete before any file is written.
    $uploaded_files = [];
    $uploaded_urls = [];
    if (!empty($_FILES['attachments'])) {
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $files = $_FILES['attachments'];
        $file_ary = [];
        if (is_array($files['name'] ?? null)) {
            foreach ($files['name'] as $key => $value) {
                if (!empty($value)) {
                    $file_ary[] = [
                        'name' => $files['name'][$key],
                        'type' => $files['type'][$key] ?? '',
                        'tmp_name' => $files['tmp_name'][$key] ?? '',
                        'error' => $files['error'][$key] ?? UPLOAD_ERR_NO_FILE,
                        'size' => $files['size'][$key] ?? 0,
                    ];
                }
            }
        } elseif (!empty($files['name'])) {
            $file_ary[] = $files;
        }

        foreach ($file_ary as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $upload_error = '';
            $validated = function_exists('mlr_validate_uploaded_file') ? mlr_validate_uploaded_file($file, $upload_error) : $file;
            if (!$validated) {
                if (function_exists('mlr_log')) mlr_log('Admin note attachment rejected.', ['reason' => $upload_error]);
                continue;
            }
            $movefile = wp_handle_upload($validated, ['test_form' => false]);
            if ($movefile && empty($movefile['error'])) {
                $uploaded_files[] = ['url' => $movefile['url'], 'name' => basename($movefile['file'])];
                $uploaded_urls[] = $movefile['url'];
            } elseif (function_exists('mlr_log')) {
                mlr_log('Admin note attachment upload failed.', ['reason' => $movefile['error'] ?? 'unknown']);
            }
        }
    }

    if (trim($comment_text) === '' && empty($uploaded_files)) {
        if ($uploaded_urls && function_exists('mlr_delete_local_document_uploads')) mlr_delete_local_document_uploads($uploaded_urls);
        wp_send_json_error(['message' => 'متن یادداشت یا فایل پیوست الزامی است.']);
        return;
    }

    $transactional = function_exists('mlr_table_supports_transactions') && mlr_table_supports_transactions($table_name);
    $current_user = wp_get_current_user();
    $new_comment = [
        'user_id' => $current_user->ID,
        'display_name' => $current_user->display_name,
        'text' => $comment_text,
        'timestamp' => current_time('mysql'),
        'attachments' => $uploaded_files,
    ];
    $comments = [];
    $saved = false;

    if ($transactional) {
        // InnoDB: serialize concurrent note writes with a row lock.
        if ($wpdb->query('START TRANSACTION') !== false) {
            $existing_comments_json = $wpdb->get_var($wpdb->prepare(
                "SELECT comments FROM `{$table_name}` WHERE id = %d FOR UPDATE", $request_id
            ));
            if (!$wpdb->last_error) {
                $comments = !empty($existing_comments_json) ? json_decode($existing_comments_json, true) : [];
                if (!is_array($comments)) $comments = [];
                $comments[] = $new_comment;
                $json = wp_json_encode($comments, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                $updated = $json !== false ? $wpdb->update($table_name, ['comments' => $json], ['id' => $request_id], ['%s'], ['%d']) : false;
                if ($updated !== false && $wpdb->query('COMMIT') !== false) {
                    $saved = true;
                } else {
                    $wpdb->query('ROLLBACK');
                }
            } else {
                $wpdb->query('ROLLBACK');
            }
        }
    } else {
        // Legacy engines (such as MyISAM) do not support transactions. A single
        // compare-and-swap UPDATE is still atomic at statement level, so no
        // concurrent writer can overwrite a note that arrived in the meantime.
        // BINARY comparison is essential for exact JSON equality on CI collations.
        for ($attempt = 0; $attempt < 7; $attempt++) {
            $existing_comments_json = $wpdb->get_var($wpdb->prepare(
                "SELECT comments FROM `{$table_name}` WHERE id = %d", $request_id
            ));
            if ($wpdb->last_error) break;
            $comments = !empty($existing_comments_json) ? json_decode($existing_comments_json, true) : [];
            if (!is_array($comments)) $comments = [];
            $comments[] = $new_comment;
            $json = wp_json_encode($comments, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($json === false) break;
            if ($existing_comments_json === null) {
                $query = $wpdb->prepare(
                    "UPDATE `{$table_name}` SET comments = %s WHERE id = %d AND comments IS NULL",
                    $json, $request_id
                );
            } else {
                $query = $wpdb->prepare(
                    "UPDATE `{$table_name}` SET comments = %s WHERE id = %d AND BINARY comments = BINARY %s",
                    $json, $request_id, $existing_comments_json
                );
            }
            $affected = $wpdb->query($query);
            if ($affected === 1) { $saved = true; break; }
            if ($affected === false) break;
            // A second author won the race; reread and retry without losing notes.
        }
    }

    if (!$saved) {
        if ($uploaded_urls && function_exists('mlr_delete_local_document_uploads')) mlr_delete_local_document_uploads($uploaded_urls);
        wp_send_json_error(['message' => 'ذخیره یادداشت انجام نشد. مجدداً تلاش کنید؛ یادداشت‌های قبلی تغییر نکرده‌اند.']);
        return;
    }

    if (function_exists('mlr_create_notification')) {
        mlr_create_notification('new_message', 'یادداشت جدید برای درخواست #' . $request_id, admin_url('admin.php?page=mlr-loan-requests&s=' . $request_id));
    }

    ob_start(); ?>
    <div class="mlr-comment-bubble">
        <div class="mlr-comment-header">
            <strong class="mlr-comment-author"><?php echo esc_html($new_comment['display_name']); ?></strong>
            <span class="mlr-comment-date"><?php echo mlr_gregorian_to_jalali($new_comment['timestamp'], 'Y/m/d H:i'); ?></span>
        </div>
        <div class="mlr-comment-body"><?php echo nl2br(esc_html($new_comment['text'])); ?></div>
        <?php if (!empty($new_comment['attachments'])): ?>
            <div class="mlr-conv-attachments"><strong class="mlr-attachments-title">پیوست‌ها:</strong><div class="mlr-attachments-list">
            <?php foreach ($new_comment['attachments'] as $file): ?>
                <a href="<?php echo esc_url($file['url']); ?>" target="_blank" class="mlr-view-file-btn"><span class="dashicons dashicons-download"></span> <?php echo esc_html($file['name']); ?></a>
            <?php endforeach; ?>
            </div></div>
        <?php endif; ?>
    </div>
    <?php $new_comment_html = ob_get_clean();

    $request_messages = function_exists('mlr_get_request_messages') ? mlr_get_request_messages($request_id, 1000) : [];
    $conversation_html = function_exists('mlr_render_request_conversation_html') ? mlr_render_request_conversation_html($comments, $request_messages) : '';
    wp_send_json_success([
        'message' => 'یادداشت با موفقیت اضافه شد.',
        'new_comment_html' => $new_comment_html,
        'conversation_html' => $conversation_html,
    ]);
}
