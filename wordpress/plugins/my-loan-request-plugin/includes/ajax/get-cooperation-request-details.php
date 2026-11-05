<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_get_cooperation_request_details', 'mlr_get_cooperation_request_details_callback');

function mlr_get_cooperation_request_details_callback() {
    check_ajax_referer('mlr_ajax_nonce_action', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'شما دسترسی لازم برای این عملیات را ندارید.']);
        return;
    }

    $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
    if (!$request_id) {
        wp_send_json_error(['message' => 'شناسه درخواست نامعتبر است.']);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'cooperation_requests';
    // Select all necessary fields including new contract status/date fields
    $request = $wpdb->get_row($wpdb->prepare(
        "SELECT id, request_type, status, data, files, contract_token, contract_signed_status, contract_signed_at, contract_expires_at
         FROM $table_name WHERE id = %d",
        $request_id
    ));


    if (!$request) {
        wp_send_json_error(['message' => 'درخواست مورد نظر یافت نشد.']);
        return;
    }

    $data = json_decode($request->data, true);
    $files = json_decode($request->files, true);

    // Labels for data fields
    $data_labels = [
        'store_name' => 'نام فروشگاه', 'store_owner_name' => 'نام مالک فروشگاه',
        'company_name' => 'نام شرکت', 'contact_person' => 'نام رابط',
        'activity_field' => 'زمینه فعالیت', 'activity_description' => 'شرح فعالیت',
        'store_address' => 'آدرس فروشگاه', 'company_address' => 'آدرس شرکت',
        'postal_code' => 'کد پستی', 'applicant_id_number' => 'کد ملی / شناسه ملی', 'mobile_number' => 'شماره همراه',
        'phone_number' => 'تلفن ثابت', 'email' => 'ایمیل',
        'website' => 'آدرس سایت', 'branches' => 'آدرس شعب', 'description' => 'توضیحات',
        'how_heard' => 'نحوه آشنایی با ما', // Label added here
    ];

    // Labels for file fields
    $file_labels = [
        'national_card' => 'کارت ملی', 'id_card' => 'شناسنامه', 'business_license' => 'جواز کسب',
        'ownership_or_lease' => 'سند مالکیت یا اجاره‌نامه', 'thana_certificate' => 'گواهی ثبت ثنا',
        'store_images' => 'تصاویر فروشگاه', 'company_images' => 'تصاویر شرکت',
        'company_docs' => 'اساسنامه و روزنامه رسمی', 'logo' => 'لوگو', 'check_image' => 'تصویر چک',
        'signature_certificate' => 'گواهی امضا', 'tax_info' => 'اطلاعات سامانه مالیاتی', 'enamad' => 'تصویر اینماد',
    ];

    // *** MODIFIED: Construct Contract Link if token exists ***
    $contract_link = '';
    // Generate the link as long as a token exists, regardless of current status
    if (!empty($request->contract_token)) {
        $contract_page = get_page_by_path('cooperation-contract');
        if ($contract_page) {
            $contract_page_url = get_permalink($contract_page->ID);
            if ($contract_page_url) {
                $contract_link = esc_url(add_query_arg('token', $request->contract_token, $contract_page_url));
            }
        }
    }
    // *** END Modification ***

    // Prepare Contract Signature Details
    $signed_status_display = ($request->contract_signed_status === 'signed') ? 'امضا شده' : 'امضا نشده';
    $signed_at_jalali = ($request->contract_signed_at) ? mlr_gregorian_to_jalali($request->contract_signed_at, 'Y/m/d H:i') : '---';
    $expires_at_jalali = ($request->contract_expires_at) ? mlr_gregorian_to_jalali($request->contract_expires_at, 'Y/m/d') : '---';


    ob_start(); // Start output buffering
    ?>
    <div class="mlr-uni-wrapper">
    <div class="mlr-uni-header">
        <div class="mlr-uni-title-group">
            <span class="dashicons dashicons-groups"></span>
            <h3 class="mlr-uni-title">جزئیات درخواست همکاری #<?php echo esc_html($request->id); ?></h3>
        </div>
        <span class="mlr-uni-badge status-<?php echo sanitize_html_class($request->status); ?>"><?php echo esc_html($request->status); ?></span>
    </div>
    <div class="mlr-uni-body">

        <?php // Display Contract Link Section (if link was generated) ?>
        <?php if ($contract_link): ?>
            <div class="mlr-uni-card" style="background:#f0fdf4; border-color:#bbf7d0;">
                 <h4 class="mlr-uni-card-title" style="color:#166534; border-color:#86efac;"><span class="dashicons dashicons-admin-links"></span> لینک قرارداد آنلاین</h4>
                 <div style="padding:10px;">
                    <p>این لینک یکتا برای مشاهده و امضای آنلاین قرارداد ایجاد شده است:</p>
                    <input type="text" value="<?php echo $contract_link; ?>" readonly class="mlr-uni-input" style="direction:ltr; text-align:left; margin-bottom:10px;">
                    <button type="button" class="mlr-uni-btn mlr-uni-btn-secondary mlr-copy-link-btn" data-link="<?php echo $contract_link; ?>">کپی لینک</button>
                    <a href="<?php echo $contract_link; ?>" target="_blank" class="mlr-uni-btn mlr-uni-btn-primary">مشاهده قرارداد</a>
                 </div>
            </div>
        <?php endif; ?>

        <div class="mlr-uni-card">
            <h4 class="mlr-uni-card-title"><span class="dashicons dashicons-info"></span> اطلاعات ثبت شده</h4>
            <table class="mlr-uni-table">
                <tbody>
                    <?php // Display Contract Signature Info (Always show status, show dates if signed) ?>
                    <tr>
                        <td style="width:30%;"><strong>وضعیت امضای قرارداد</strong></td>
                        <td>
                            <span class="mlr-uni-badge status-<?php echo esc_attr($request->contract_signed_status); ?>">
                                <?php echo esc_html($signed_status_display); ?>
                            </span>
                        </td>
                    </tr>
                    <?php if ($request->contract_signed_status === 'signed'): ?>
                        <tr>
                            <td><strong>تاریخ امضای قرارداد</strong></td>
                            <td><?php echo esc_html($signed_at_jalali); ?></td>
                        </tr>
                        <tr>
                            <td><strong>تاریخ انقضای قرارداد</strong></td>
                            <td><?php echo esc_html($expires_at_jalali); ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php // Loop through submitted data ?>
                    <?php if (is_array($data)): // Check if $data is an array ?>
                        <?php foreach ($data as $key => $value):
                            if (empty($value) && $value !== '0') continue; // Skip empty fields, but allow '0'
                            // Determine the label: use predefined, fallback to formatted key
                            $label = $data_labels[$key] ?? ucwords(str_replace('_', ' ', $key));
                        ?>
                            <tr>
                                <td style="width:30%;"><strong><?php echo esc_html($label); ?></strong></td>
                                <td><?php echo nl2br(esc_html($value)); // Use nl2br for textareas ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="2">اطلاعاتی برای نمایش وجود ندارد.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php // Display Uploaded Files ?>
        <?php if (!empty($files) && is_array($files)): // Check if $files is a non-empty array ?>
            <div class="mlr-uni-card">
                <h4 class="mlr-uni-card-title"><span class="dashicons dashicons-media-default"></span> فایل‌های بارگذاری شده</h4>
                <div class="mlr-uni-grid-3">
                    <?php foreach ($files as $key => $file_group):
                        if (empty($file_group)) continue; // Skip if no files for this key
                        // Determine the label
                        $label = $file_labels[$key] ?? ucwords(str_replace('_', ' ', $key));
                    ?>
                        <div class="mlr-uni-file-item">
                            <div class="mlr-uni-file-label"><?php echo esc_html($label); ?></div>
                            <div class="mlr-uni-file-actions">
                                <?php // Ensure $file_group is iterable ?>
                                <?php foreach ((array)$file_group as $index => $file_url): ?>
                                    <a href="<?php echo esc_url($file_url); ?>" target="_blank" rel="noopener noreferrer" class="mlr-uni-btn-sm">
                                        <span class="dashicons dashicons-download" style="font-size:14px;"></span>
                                        <?php // Provide better link text if multiple files ?>
                                        مشاهده فایل <?php echo (count((array)$file_group) > 1) ? ('#' . ($index + 1)) : ''; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    </div>
    <?php
    $html_output = ob_get_clean(); // Get buffered HTML

    // NOTE: Do not append <script> tags in AJAX HTML responses. Most DOM insertion methods
    // (including jQuery .html()) do NOT execute them. All modal interactions are handled
    // in the shared admin.js now (copy link, view files, etc.).

    wp_send_json_success(['html' => $html_output]);
}