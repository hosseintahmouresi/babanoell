<?php
add_shortcode('mlr_individual_request_form', 'mlr_individual_request_form_shortcode');
add_shortcode('mlr_legal_request_form', 'mlr_legal_request_form_shortcode');

function mlr_individual_request_form_shortcode() {
    return render_cooperation_form('individual');
}

function mlr_legal_request_form_shortcode() {
    return render_cooperation_form('legal');
}

/**
 * Renders the cooperation form based on type and saved configurations.
 *
 * @param string $request_type 'individual' or 'legal'.
 * @return string HTML output for the form.
 */
function render_cooperation_form($request_type) {

    // Enqueue and localize so ajax_url and nonce are always present (works when shortcode is in content, widget, or block)
    wp_enqueue_script('mlr-file-upload-helper');
    wp_enqueue_script('mlr-cooperation-form-js');
    wp_localize_script('mlr-cooperation-form-js', 'mlr_coop_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('mlr_cooperation_request_action'),
        'context'  => 'cooperation_request',
    ]);

    wp_enqueue_style('dashicons');
    wp_enqueue_style('mlr-frontend-css', MLR_PLUGIN_URL . 'assets/css/frontend.css', [], '6.6.0');


    // Get form options and configurations with defaults
    $default_options = [ 'activity_field_options' => [], 'how_heard_options' => [] ];
    $form_options = wp_parse_args(get_option('mlr_cooperation_form_options', []), $default_options);

    $all_fields = mlr_get_all_cooperation_fields();
    $default_config = [];
    foreach ($all_fields as $key => $info) {
        $default_config[$key] = ['visible' => $info['default_visible'] ?? true, 'required' => $info['default_required'] ?? false];
    }
    $form_config = wp_parse_args(get_option('mlr_cooperation_form_config', []), $default_config);

    ob_start();
    ?>
    <div class="mlr-cooperation-form-wrapper">
        <form id="mlr-cooperation-form-<?php echo esc_attr($request_type); ?>" class="mlr-cooperation-form" method="post" action="#" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="request_type" value="<?php echo esc_attr($request_type); ?>">
            <h3 class="mlr-form-title">درخواست همکاری <?php echo $request_type === 'individual' ? 'حقیقی' : 'حقوقی'; ?></h3>
            <p class="mlr-form-description">لطفا اطلاعات زیر را با دقت تکمیل و مدارک مورد نیاز را بارگذاری نمایید. فیلدهای ستاره‌دار (*) اجباری هستند.</p>

            <?php
            // Define field groups for display structure
             $field_groups = [
                'اطلاعات پایه' => ($request_type === 'individual')
                    ? ['store_name', 'store_owner_name']
                    : ['company_name', 'contact_person', 'email'],
                'اطلاعات تکمیلی' => ['activity_field', 'how_heard', 'activity_description', 'postal_code', 'applicant_id_number', 'mobile_number', 'phone_number', 'website'],
                'آدرس‌ها' => ($request_type === 'individual') ? ['store_address', 'branches'] : ['company_address', 'branches'],
                'توضیحات' => ['description'],
                'مدارک' => array_keys(array_filter($all_fields, function($field) { return $field['type'] === 'file'; })) // Get all file fields dynamically
            ];


            // Loop through groups and fields to render the form
            foreach ($field_groups as $group_label => $group_fields) {
                $has_visible_fields_in_group = false;
                $group_output = ''; // Buffer output for the group

                foreach ($group_fields as $field_key) {
                    if (!isset($all_fields[$field_key])) continue; // Skip if field definition doesn't exist

                    $field_info = $all_fields[$field_key];

                    // --- Check if field applies to the current form type ---
                    if ($field_info['form'] !== 'both' && $field_info['form'] !== $request_type) {
                        continue;
                    }

                    // --- Check visibility from config (only file fields can be hidden) ---
                    $is_visible = isset($form_config[$field_key]['visible']) ? (bool)$form_config[$field_key]['visible'] : ($field_info['default_visible'] ?? true);

                    // Skip rendering if it's a file field and configured to be hidden
                    if ($field_info['type'] === 'file' && !$is_visible) {
                        continue;
                    }

                    // Mark group as having content if at least one field is visible
                    $has_visible_fields_in_group = true;

                    // --- Check required status from config ---
                    // Field is required if config says so AND it's visible
                    $is_required = (isset($form_config[$field_key]['required']) ? (bool)$form_config[$field_key]['required'] : ($field_info['default_required'] ?? false)) && $is_visible;
                    $required_attr = $is_required ? 'required' : '';
                    $required_span = $is_required ? ' <span class="mlr-required">*</span>' : '';

                    // --- Build HTML for the field ---
                    $group_output .= '<div class="mlr-form-group field-' . esc_attr($field_key) . ' field-type-' . esc_attr($field_info['type']) . ($is_required ? ' is-required' : '') . '">';
                    $group_output .= '<label for="' . esc_attr($field_key) . '">' . esc_html($field_info['label']) . $required_span . '</label>';

                    switch ($field_info['type']) {
                        case 'text':
                        case 'email':
                            $input_type = ($field_info['type'] === 'email') ? 'email' : 'text';
                            // Add specific patterns for validation if needed (e.g., mobile, postal code)
                            $pattern = '';
                            if ($field_key === 'mobile_number') $pattern = 'pattern="09[0-9]{9}" title="شماره موبایل 11 رقمی با شروع 09"';
                            if ($field_key === 'postal_code') $pattern = 'pattern="[0-9]{10}" title="کد پستی 10 رقمی"';
                            $group_output .= '<input type="' . $input_type . '" id="' . esc_attr($field_key) . '" name="' . esc_attr($field_key) . '" class="mlr-input" ' . $required_attr . ' ' . $pattern . '>';
                            break;
                        case 'textarea':
                            $rows = ($field_key === 'branches' || $field_key === 'description') ? 3 : 4; // Adjust rows based on field
                            $group_output .= '<textarea id="' . esc_attr($field_key) . '" name="' . esc_attr($field_key) . '" class="mlr-textarea" rows="' . $rows . '" ' . $required_attr . '></textarea>';
                            break;
                        case 'select':
                            $options_key = $field_info['options_key'] ?? '';
                            // Get options from saved settings, fallback to default if key exists but no options saved
                            $options = $form_options[$options_key] ?? ($default_options[$options_key] ?? []);
                            $group_output .= '<select id="' . esc_attr($field_key) . '" name="' . esc_attr($field_key) . '" class="mlr-select" ' . $required_attr . '>';
                            $group_output .= '<option value="">-- انتخاب کنید --</option>';
                            if (!empty($options)) {
                                foreach ($options as $option) {
                                    $group_output .= '<option value="' . esc_attr($option) . '">' . esc_html($option) . '</option>';
                                }
                            }
                            $group_output .= '</select>';
                            break;
                        case 'file':
                            $group_output .= '<div class="mlr-coop-file-upload-group field-type-file' . ($is_required ? ' is-required' : '') . '" data-field-key="' . esc_attr($field_key) . '">';
                            $group_output .= '<input type="file" id="' . esc_attr($field_key) . '" name="' . esc_attr($field_key) . '[]" class="mlr-input-file mlr-coop-file-input" multiple ' . $required_attr . ' accept=".jpg,.jpeg,.png,.pdf">';
                            $group_output .= '<div class="mlr-upload-progress-wrap" style="display:none;"><div class="mlr-upload-progress-bar"><div class="mlr-upload-progress-fill"></div></div><span class="mlr-upload-progress-pct">0%</span></div>';
                            $group_output .= '<div class="mlr-uploaded-files-wrap"><span class="mlr-uploaded-files-title">فایل‌های آپلود شده:</span><div class="mlr-uploaded-files-list" aria-live="polite"></div></div>';
                            $group_output .= '<div class="mlr-upload-error" role="alert" style="display:none;"></div>';
                            $group_output .= '<input type="hidden" class="mlr-uploaded-urls" name="mlr_uploaded_' . esc_attr($field_key) . '" value="[]">';
                            $group_output .= '<p class="description">امکان انتخاب چند فایل وجود دارد. پسوندهای مجاز: jpg, jpeg, png, pdf. حداکثر حجم هر فایل: 5 مگابایت. فایل‌ها پس از انتخاب به‌صورت خودکار آپلود می‌شوند. می‌توانید فایل‌های بیشتری اضافه یا حذف کنید.</p>';
                            $group_output .= '</div>';
                            break;
                    }
                     // Add description if available in field_info (future enhancement)
                     // if (!empty($field_info['description'])) {
                     //    $group_output .= '<p class="description">' . esc_html($field_info['description']) . '</p>';
                     // }
                    $group_output .= '<span class="mlr-validation-message"></span>'; // Placeholder for JS validation messages
                    $group_output .= '</div>'; // Close mlr-form-group
                } // End foreach field_key

                // --- Output the group heading and its fields ---
                if ($has_visible_fields_in_group) {
                     echo '<h4 class="mlr-form-group-title">' . esc_html($group_label) . '</h4>';
                     echo $group_output; // Output buffered HTML for the group
                }

            } // End foreach field_group
            ?>

            <button type="submit" class="mlr-btn mlr-submit-button">ارسال درخواست</button>
        </form>

        <div class="mlr-submission-overlay" style="display: none;"></div>
        <div class="mlr-submission-modal mlr-success-modal" id="mlr-success-modal-<?php echo esc_attr($request_type); ?>" style="display: none;">
            <div class="mlr-modal-icon success">✓</div>
            <h3>درخواست شما ثبت شد</h3>
            <p></p> <?php /* Message will be added by JS */ ?>
            <button type="button" class="mlr-btn mlr-btn-primary mlr-close-success-modal-btn">متوجه شدم</button>
        </div>
        <div class="mlr-submission-modal mlr-error-modal" id="mlr-error-modal-<?php echo esc_attr($request_type); ?>" style="display: none;">
            <div class="mlr-modal-icon error">×</div>
            <h3>خطا در ارسال</h3>
            <div class="mlr-error-modal-message"></div> <?php /* Error messages added by JS */ ?>
            <button type="button" class="mlr-btn mlr-btn-secondary mlr-close-error-modal-btn">بستن</button>
        </div>
    </div>
    <?php /* Keep basic styles, rely on frontend.css or specific cooperation-form.css for more complex styling */ ?>
    <style>
        .mlr-cooperation-form-wrapper { max-width: 800px; margin: 30px auto; font-family: 'Vazirmatn', sans-serif; direction: rtl; }
        .mlr-cooperation-form-wrapper form { padding: 30px 35px; background: #fff; border-radius: 12px; box-shadow: 0 6px 20px rgba(0,0,0,0.08); border: 1px solid #e5e7eb; }
        .mlr-form-title { font-size: 22px; font-weight: 700; color: #1f2937; margin: 0 0 10px; text-align: center; }
        .mlr-form-description { font-size: 14px; color: #6b7280; text-align: center; margin-bottom: 30px; }
        .mlr-form-group-title { font-size: 16px; font-weight: 600; color: #374151; margin: 30px 0 15px; padding-bottom: 8px; border-bottom: 1px solid #e5e7eb; }
        .mlr-form-group-title:first-of-type { margin-top: 0; }
        .mlr-form-group { margin-bottom: 20px; }
        .mlr-form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 13px; color: #374151; }
        .mlr-required { color: #ef4444; margin-right: 2px; font-weight: bold; }
        .mlr-input, .mlr-select, .mlr-textarea, .mlr-input-file { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box; font-size: 14px; background-color: #fff; transition: border-color 0.2s, box-shadow 0.2s; }
        .mlr-input:focus, .mlr-select:focus, .mlr-textarea:focus, .mlr-input-file:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); }
        .mlr-input-file { padding: 8px 10px; background-color: #f9fafb;}
        .mlr-input-file::file-selector-button { margin-left: 10px; padding: 6px 12px; border: 1px solid #d1d5db; border-radius: 4px; background-color: #fff; cursor: pointer; transition: background-color 0.2s; font-family: inherit;}
        .mlr-input-file:hover::file-selector-button { background-color: #f3f4f6; }
        .mlr-form-group .description { font-size: 12px; color: #6b7280; margin-top: 5px; }
        .mlr-validation-message { color: #ef4444; font-size: 12px; margin-top: 4px; display: none; } /* For JS validation */
        .is-invalid .mlr-input, .is-invalid .mlr-select, .is-invalid .mlr-textarea, .is-invalid .mlr-input-file { border-color: #ef4444; }
        .is-invalid .mlr-validation-message { display: block; }
        .mlr-submit-button { width: 100%; padding: 12px; font-size: 16px; cursor: pointer; border-radius: 6px; border: none; background-color: #2563eb; color: #fff; font-weight: 600; transition: background-color 0.2s; margin-top: 25px; }
        .mlr-submit-button:hover { background-color: #1d4ed8; }
        .mlr-submit-button:disabled { background-color: #9ca3af; cursor: not-allowed; }
        /* Modal Styles (assuming defined in frontend.css or similar) */
        .mlr-submission-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; }
        .mlr-submission-modal { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; border-radius: 8px; padding: 30px 40px; z-index: 1001; text-align: center; min-width: 300px; max-width: 450px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .mlr-modal-icon { width: 60px; height: 60px; line-height: 60px; border-radius: 50%; margin: 0 auto 20px; font-size: 30px; color: #fff; }
        .mlr-modal-icon.success { background-color: #10b981; }
        .mlr-modal-icon.error { background-color: #ef4444; }
        .mlr-submission-modal h3 { font-size: 18px; margin: 0 0 10px; color: #1f2937; }
        .mlr-success-modal p, .mlr-error-modal-message { font-size: 14px; color: #4b5563; margin-bottom: 25px; line-height: 1.6; }
        .mlr-error-modal-message { text-align: right; }
        .mlr-btn { padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; border: 1px solid transparent; }
        .mlr-btn-primary { background-color: #2563eb; color: #fff; border-color: #2563eb; }
        .mlr-btn-primary:hover { background-color: #1d4ed8; border-color: #1d4ed8; }
        .mlr-btn-secondary { background-color: #e5e7eb; color: #374151; border-color: #d1d5db; }
        .mlr-btn-secondary:hover { background-color: #d1d5db; border-color: #9ca3af; }
        /* نوار پیشرفت آپلود فایل */
        .mlr-upload-progress-wrap { margin-top: 8px; display: flex; align-items: center; gap: 10px; }
        .mlr-upload-progress-bar { flex: 1; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden; }
        .mlr-upload-progress-fill { height: 100%; width: 0%; background: #2563eb; border-radius: 4px; transition: width 0.2s ease; }
        .mlr-upload-progress-pct { font-size: 12px; font-weight: 600; color: #374151; min-width: 36px; }
        /* لیست فایل‌های آپلود شده */
        .mlr-uploaded-files-wrap { margin-top: 10px; }
        .mlr-uploaded-files-title { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
        .mlr-uploaded-files-list { }
        .mlr-uploaded-file-item { font-size: 13px; color: #059669; margin-bottom: 6px; display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
        .mlr-uploaded-file-item::before { content: "✓"; font-weight: bold; }
        .mlr-uploaded-file-item .mlr-uploaded-file-name { flex: 0 1 auto; }
        .mlr-uploaded-file-item .mlr-view-file { font-size: 12px; color: #2563eb; text-decoration: none; }
        .mlr-uploaded-file-item .mlr-view-file:hover { text-decoration: underline; }
        .mlr-uploaded-file-item .mlr-remove-file { font-size: 12px; padding: 2px 8px; cursor: pointer; color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; border-radius: 4px; }
        .mlr-uploaded-file-item .mlr-remove-file:hover { background: #fee2e2; }
        .mlr-upload-status-pending { font-size: 13px; color: #2563eb; margin-bottom: 4px; }
        .mlr-upload-error { margin-top: 8px; padding: 8px 10px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; color: #b91c1c; font-size: 13px; }
    </style>
    <?php
    return ob_get_clean();
}