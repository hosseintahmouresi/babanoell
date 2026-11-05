<?php
add_shortcode('mlr_complete_request', 'mlr_complete_request_shortcode');
function mlr_complete_request_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>لطفاً ابتدا وارد شوید.</p>';
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'loan_requests';
    $plans_table = $wpdb->prefix . 'installment_plans';
    $user_id = get_current_user_id();

    if (!isset($_GET['mlr_complete']) || !isset($_GET['nonce'])) {
        return '<p>درخواست نامعتبر است.</p>';
    }

    $request_id = intval($_GET['mlr_complete']);
    $nonce = sanitize_text_field(wp_unslash($_GET['nonce']));

    if (!wp_verify_nonce($nonce, 'mlr_complete_' . $request_id)) {
        return '<p>توکن امنیتی نامعتبر است.</p>';
    }

    $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d AND status IN ('در انتظار مدارک', 'تکمیل مدارک', 'تأیید نهایی', 'تایید نهایی') LIMIT 1", $request_id));

    $can_complete = $request && (
        function_exists('mlr_is_acceptor_side_request_owner')
            ? mlr_is_acceptor_side_request_owner($request, $user_id)
            : intval($request->user_id ?? 0) === intval($user_id)
    );

    if (!$can_complete) {
        return '<p>درخواست یافت نشد یا قابل تکمیل نیست.</p>';
    }

    $plan = $wpdb->get_row($wpdb->prepare("SELECT issue_check, requires_national_card_front, requires_national_card_back FROM $plans_table WHERE id = %d", $request->installment_plan_id));
    if (!$plan) {
        $plan = (object) [
            'issue_check' => 0,
            'requires_national_card_front' => 0,
            'requires_national_card_back' => 0,
        ];
    }

    if (isset($_POST['mlr_complete_request'], $_POST['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'mlr_complete_request_action')) {
        $error_messages = [];
        $order_items = isset($_POST['order_items']) ? sanitize_textarea_field(wp_unslash($_POST['order_items'])) : '';
        $order_total = isset($_POST['order_total']) ? intval($_POST['order_total']) : 0;
        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
        $check_images = $request->check_images ?? '';
        $national_card_front = $request->national_card_front ?? '';
        $national_card_back = $request->national_card_back ?? '';
        $signature_image = $request->signature_image ?? '';
        $contract_file = $request->contract_file ?? '';
        $original_status = (string) ($request->status ?? '');

        if (empty($description)) {
            $error_messages[] = 'توضیحات الزامی است.';
        }

        // contract_file is required (either existing on request or newly uploaded)
        if (empty($request->contract_file) && (empty($_FILES['contract_file']) || empty($_FILES['contract_file']['name']))) {
            $error_messages[] = 'فایل فاکتور/قرارداد الزامی است.';
        }

        if ($plan->requires_national_card_front && empty($request->national_card_front) && (empty($_FILES['national_card_front']) || $_FILES['national_card_front']['error'] !== UPLOAD_ERR_OK)) {
            $error_messages[] = 'عکس کارت ملی (رو) الزامی است.';
        }
        if ($plan->requires_national_card_back && empty($request->national_card_back) && (empty($_FILES['national_card_back']) || $_FILES['national_card_back']['error'] !== UPLOAD_ERR_OK)) {
            $error_messages[] = 'عکس کارت ملی (پشت) الزامی است.';
        }
        if ($plan->issue_check && empty($request->check_images) && (empty($_FILES['check_images']['name'][0]) || $_FILES['check_images']['error'][0] !== UPLOAD_ERR_OK)) {
            $error_messages[] = 'تصاویر چک الزامی است.';
        }

        if (empty($request->signature_image) && (empty($_FILES['signature_image']) || $_FILES['signature_image']['error'] !== UPLOAD_ERR_OK)) {
            $error_messages[] = 'تصویر امضا الزامی است.';
        }

        if (!empty($error_messages)) {
            echo '<div class="error"><p>' . esc_html(implode(' | ', $error_messages)) . '</p></div>';
        } else {
            // Handle signature image upload
            if (!empty($_FILES['signature_image']['name'])) {
                $upload = mlr_handle_wp_upload($_FILES['signature_image']);
                if (is_wp_error($upload)) {
                    $error_messages[] = 'خطا در آپلود تصویر امضا: ' . $upload->get_error_message();
                } else {
                    $signature_image = $upload;
                }
            }
    
            // Handle contract file upload
            if (!empty($_FILES['contract_file']['name'])) {
                $upload = mlr_handle_wp_upload($_FILES['contract_file']);
                if (is_wp_error($upload)) {
                    $error_messages[] = 'خطا در آپلود فایل قرارداد: ' . $upload->get_error_message();
                } else {
                    $contract_file = $upload;
                }
            }
    
            // Handle national card front upload
            if ($plan->requires_national_card_front && !empty($_FILES['national_card_front']['name'])) {
                $upload = mlr_handle_wp_upload($_FILES['national_card_front']);
                if (is_wp_error($upload)) {
                    $error_messages[] = 'خطا در آپلود کارت ملی (رو): ' . $upload->get_error_message();
                } else {
                    $national_card_front = $upload;
                }
            }
    
            // Handle national card back upload
            if ($plan->requires_national_card_back && !empty($_FILES['national_card_back']['name'])) {
                $upload = mlr_handle_wp_upload($_FILES['national_card_back']);
                if (is_wp_error($upload)) {
                    $error_messages[] = 'خطا در آپلود کارت ملی (پشت): ' . $upload->get_error_message();
                } else {
                    $national_card_back = $upload;
                }
            }
    
            // Handle check images upload
            if ($plan->issue_check && !empty($_FILES['check_images']['name'][0])) {
                $check_urls = [];
                $files = $_FILES['check_images'];
                foreach ($files['name'] as $key => $value) {
                    if ($files['error'][$key] === UPLOAD_ERR_OK) {
                        $file = ['name' => $files['name'][$key], 'type' => $files['type'][$key], 'tmp_name' => $files['tmp_name'][$key], 'error' => $files['error'][$key], 'size' => $files['size'][$key]];
                        $upload = mlr_handle_wp_upload($file);
                        if (is_wp_error($upload)) {
                            $error_messages[] = 'خطا در آپلود چک: ' . $upload->get_error_message();
                        } else {
                            $check_urls[] = $upload;
                        }
                    }
                }
                if (!empty($check_urls)) {
                    $check_images = implode(',', $check_urls);
                }
            }

            if (!empty($error_messages)) {
                echo '<div class="error"><p>' . esc_html(implode(' | ', $error_messages)) . '</p></div>';
            } else {
                $updated = $wpdb->update(
                    $table_name,
                    [
                        'order_items' => $order_items,
                        'order_total' => $order_total,
                        'description' => $description,
                        'signature_image' => $signature_image,
                        'contract_file' => $contract_file,
                        'national_card_front' => $national_card_front,
                        'national_card_back' => $national_card_back,
                        'check_images' => $check_images,
                        'status' => 'تأیید مدارک',
                        'last_edited_at' => current_time('mysql')
                    ],
                    ['id' => $request_id, 'status' => $original_status]
                );
                if ($updated === false) {
                    echo '<div class="error"><p>خطایی در ثبت اطلاعات پرونده رخ داد.</p></div>';
                } elseif ($updated !== 1) {
                    echo '<div class="error"><p>وضعیت پرونده هم‌زمان تغییر کرده است؛ صفحه را تازه‌سازی کنید.</p></div>';
                } else {
                    echo '<div class="updated"><p>درخواست با موفقیت تکمیل شد و به حالت "تأیید مدارک" تغییر یافت.</p></div>';
                    // Refresh request data to show updated info.
                    $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d LIMIT 1", $request_id));
                }
            }
        }
    }

    ob_start();
    ?>
    <div class="mlr-complete-request">
        <style>
            .mlr-complete-request {
                background: #fff;
                padding: 30px;
                border-radius: 20px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.05);
                max-width: 800px;
                margin: 40px auto;
                border: 1px solid #e2e8f0;
                font-family: 'Vazirmatn', sans-serif;
            }
            .mlr-complete-request h2 {
                font-size: 24px;
                font-weight: 800;
                color: #1e293b;
                margin-bottom: 25px;
                border-bottom: 2px solid #4f46e5;
                padding-bottom: 15px;
            }
            .mlr-complete-request label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
                color: #334155;
            }
            .mlr-complete-request input[type="text"],
            .mlr-complete-request input[type="number"],
            .mlr-complete-request textarea {
                width: 100%;
                padding: 12px;
                border: 2px solid #e2e8f0;
                border-radius: 12px;
                font-size: 14px;
                transition: all 0.2s;
            }
            .mlr-complete-request input:focus, .mlr-complete-request textarea:focus {
                border-color: #4f46e5;
                box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
                outline: none;
            }
            .mlr-complete-request button {
                background: #4f46e5;
                color: #fff;
                padding: 14px 24px;
                border: none;
                border-radius: 12px;
                font-weight: 700;
                cursor: pointer;
                transition: all 0.2s;
                width: 100%;
                margin-top: 20px;
                font-size: 16px;
            }
            .mlr-complete-request button:hover {
                background: #4338ca;
                transform: translateY(-2px);
                box-shadow: 0 10px 20px -5px rgba(79, 70, 229, 0.4);
            }
        </style>
        <h2>تکمیل درخواست (شناسه: <?php echo $request_id; ?>)</h2>
        <p class="mlr-request-amounts" style="margin-bottom: 15px; padding: 12px; background: #f0f4ff; border-radius: 8px; font-size: 14px;">
            <strong>مبلغ درخواستی:</strong> <?php echo number_format((int)$request->loan_amount); ?> ریال
            &nbsp;|&nbsp;
            <strong>مبلغ تسهیلات:</strong> <?php echo (isset($request->facility_amount) && $request->facility_amount !== '') ? number_format((int)$request->facility_amount) : number_format((int)$request->loan_amount); ?> ریال
            &nbsp;|&nbsp;
            <strong>فاصله اقساط (تناوب):</strong> <?php echo isset($request->installment_interval) && $request->installment_interval !== '' ? esc_html($request->installment_interval) . ' ماه' : '—'; ?>
        </p>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('mlr_complete_request_action'); ?>
            <p><label>اقلام سفارش: <textarea name="order_items" required><?php echo esc_textarea($request->order_items); ?></textarea></label></p>
            <p><label>مبلغ کل سفارش (تومان): <input type="number" name="order_total" value="<?php echo esc_attr($request->order_total); ?>" required></label></p>
            <p><label>توضیحات: <textarea name="description" required><?php echo esc_textarea($request->description); ?></textarea></label></p>
            <p><label>تصویر امضا: <input type="file" name="signature_image" <?php echo $request->signature_image ? '' : 'required'; ?>></label></p>
            <p><label>فایل فاکتور/قرارداد: <input type="file" name="contract_file" <?php echo $request->contract_file ? '' : 'required'; ?>></label></p>
            <?php
            $pending_attachments = isset($request->pending_docs_attachments) && $request->pending_docs_attachments !== '' ? json_decode($request->pending_docs_attachments, true) : [];
            if (is_array($pending_attachments) && !empty($pending_attachments)) :
            ?>
            <div class="mlr-pending-docs-inline" style="margin: 16px 0; padding: 14px; background: rgba(79, 70, 229, 0.08); border: 1px solid rgba(79, 70, 229, 0.25); border-radius: 12px;">
                <p style="margin: 0 0 10px; font-weight: 700; font-size: 14px; color: #4f46e5;">فایل‌های پیوست‌شده از طرف کارشناس</p>
                <p style="margin: 0 0 12px; font-size: 13px; color: #64748b;">این ها فایل ها و تصاویر تکمیلی توسط کارشناس برای این پرونده ارسال شده. موارد را پرینت و مهر/اثرانگشت و امضا کنید و سپس در فیلد سایر مدارک بارگزاری نمائید.</p>
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <?php foreach ($pending_attachments as $pkey => $pitem) : ?>
                        <?php if (is_array($pitem) && !empty($pitem['url']) && strpos($pitem['url'], 'http') === 0) : ?>
                            <a href="<?php echo esc_url($pitem['url']); ?>" target="_blank" rel="noopener" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; background: #fff; border: 1px solid #e2e8f0; color: #4f46e5; font-size: 13px; font-weight: 600; text-decoration: none;"><?php echo esc_html(!empty($pitem['label']) ? $pitem['label'] : $pkey); ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($plan->requires_national_card_front) : ?>
            <p><label>عکس کارت ملی (رو): <input type="file" name="national_card_front" <?php echo $request->national_card_front ? '' : 'required'; ?>></label></p>
            <?php endif; ?>
            <?php if ($plan->requires_national_card_back) : ?>
            <p><label>عکس کارت ملی (پشت): <input type="file" name="national_card_back" <?php echo $request->national_card_back ? '' : 'required'; ?>></label></p>
            <?php endif; ?>
            <?php if ($plan->issue_check) : ?>
            <p><label>تصاویر چک: <input type="file" name="check_images[]" multiple <?php echo $request->check_images ? '' : 'required'; ?>></label></p>
            <?php endif; ?>
            <button type="submit" name="mlr_complete_request" class="button">ذخیره و تأیید مدارک</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
?>