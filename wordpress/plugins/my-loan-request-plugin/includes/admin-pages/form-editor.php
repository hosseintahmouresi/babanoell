<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders the Cooperation Form Editor page.
 */
function mlr_form_editor_page() {
    if (!current_user_can('manage_options')) {
        wp_die('شما دسترسی لازم برای مشاهده این صفحه را ندارید.');
    }

    // Get saved options and configurations with defaults
    $default_options = [
        'activity_field_options' => ['پوشاک', 'لوازم خانگی', 'موبایل و کامپیوتر', 'طلا و جواهر', 'خدمات'], // Example defaults
        'how_heard_options' => ['جستجوی گوگل', 'شبکه‌های اجتماعی', 'معرفی دوستان', 'تبلیغات', 'سایر'], // Example defaults
        // Add default arrays for any other option keys you define
    ];
    // Merge saved options with defaults to ensure all keys exist
    $form_options = wp_parse_args(get_option('mlr_cooperation_form_options', []), $default_options);

    $all_fields = mlr_get_all_cooperation_fields();
    $default_config = [];
    foreach ($all_fields as $key => $info) {
        $default_config[$key] = [
            'visible' => $info['default_visible'] ?? true, // Default to true if not specified
            'required' => $info['default_required'] ?? false, // Default to false if not specified
        ];
    }
    // Merge saved config with defaults
    $form_config = wp_parse_args(get_option('mlr_cooperation_form_config', []), $default_config);

    ?>
    <div class="wrap mlr-admin-wrap mlr-form-editor-wrap">
        <div class="mlr-page-header">
            <h1>ویرایشگر فرم درخواست همکاری</h1>
             <p class="mlr-page-description">در این صفحه می‌توانید گزینه‌های فیلدهای انتخابی و وضعیت (اجباری/اختیاری، نمایش/عدم نمایش) فیلدهای فرم درخواست همکاری را مدیریت کنید.</p>
        </div>


        <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']) : // Check the value too ?>
            <div id="message" class="updated notice is-dismissible"><p>تنظیمات با موفقیت ذخیره شد.</p></div>
        <?php endif; ?>

        <form method="post" action="options.php">
            <?php settings_fields('mlr_cooperation_form_editor_group'); ?>

            <?php // Section for Managing Select Options ?>
            <div class="mlr-settings-card">
                <h2><span class="dashicons dashicons-forms" ></span> مدیریت گزینه‌های انتخابی</h2>
                <div class="mlr-options-section">
                     <div class="mlr-option-group">
                        <label for="activity_field_options">گزینه‌های "زمینه فعالیت"</label>
                        <textarea id="activity_field_options" name="mlr_cooperation_form_options[activity_field_options]" rows="6" class="widefat"><?php echo esc_textarea(implode("\n", $form_options['activity_field_options'] ?? [])); ?></textarea>
                        <p class="description">هر گزینه را در یک خط جدید وارد کنید.</p>
                     </div>
                     <div class="mlr-option-group">
                        <label for="how_heard_options">گزینه‌های "نحوه آشنایی با ما"</label>
                        <textarea id="how_heard_options" name="mlr_cooperation_form_options[how_heard_options]" rows="5" class="widefat"><?php echo esc_textarea(implode("\n", $form_options['how_heard_options'] ?? [])); ?></textarea>
                         <p class="description">هر گزینه را در یک خط جدید وارد کنید.</p>
                     </div>
                     <?php
                     // You can dynamically add more textareas here based on fields with 'options_key'
                     /*
                     foreach ($all_fields as $f_key => $f_info) {
                         if ($f_info['type'] === 'select' && isset($f_info['options_key']) && !in_array($f_info['options_key'], ['activity_field_options', 'how_heard_options'])) {
                             ?>
                             <div class="mlr-option-group">
                                <label for="<?php echo esc_attr($f_info['options_key']); ?>">گزینه‌های "<?php echo esc_html($f_info['label']); ?>"</label>
                                <textarea id="<?php echo esc_attr($f_info['options_key']); ?>" name="mlr_cooperation_form_options[<?php echo esc_attr($f_info['options_key']); ?>]" rows="5" class="widefat"><?php echo esc_textarea(implode("\n", $form_options[$f_info['options_key']] ?? [])); ?></textarea>
                                 <p class="description">هر گزینه را در یک خط جدید وارد کنید.</p>
                             </div>
                             <?php
                         }
                     }
                     */
                     ?>
                </div>
            </div>

            <?php // Section for Managing Field Configurations ?>
            <div class="mlr-settings-card">
                 <h2><span class="dashicons dashicons-admin-generic"></span> مدیریت وضعیت فیلدها</h2>
                 <p class="mlr-card-description">وضعیت نمایش و اجباری بودن فیلدهای فرم همکاری را تعیین کنید. فیلدهای غیر فایل همیشه نمایش داده می‌شوند.</p>

                 <div class="mlr-field-config-container">
                    <?php
                    // Group fields for better organization
                     $field_groups = [
                        'اطلاعات پایه (حقیقی)' => ['store_name', 'store_owner_name'],
                        'اطلاعات پایه (حقوقی)' => ['company_name', 'contact_person', 'email'],
                        'اطلاعات مشترک' => ['activity_field', 'how_heard', 'activity_description', 'postal_code', 'applicant_id_number', 'mobile_number', 'phone_number', 'website'],
                        'آدرس‌ها' => ['store_address', 'company_address', 'branches'],
                        'توضیحات تکمیلی' => ['description'],
                        'مدارک مشترک' => ['national_card', 'id_card', 'business_license', 'ownership_or_lease', 'thana_certificate', 'logo', 'check_image', 'signature_certificate', 'tax_info', 'enamad'],
                        'مدارک اختصاصی حقیقی' => ['store_images'],
                        'مدارک اختصاصی حقوقی' => ['company_docs', 'company_images'],
                    ];


                    foreach ($field_groups as $group_label => $group_fields) {
                        echo '<div class="mlr-field-group">';
                        echo '<h3 class="mlr-field-group-title">' . esc_html($group_label) . '</h3>';
                        echo '<div class="mlr-field-config-grid">'; // Grid within each group

                        foreach ($group_fields as $field_key) {
                            if (!isset($all_fields[$field_key])) continue; // Skip if field definition not found

                            $field_info = $all_fields[$field_key];
                            // Get current config, fall back to default from definition if not saved yet
                            // Ensure defaults from mlr_get_all_cooperation_fields are used if nothing is saved
                            $current_visible = isset($form_config[$field_key]['visible']) ? (bool)$form_config[$field_key]['visible'] : ($field_info['default_visible'] ?? true);
                            $current_required = isset($form_config[$field_key]['required']) ? (bool)$form_config[$field_key]['required'] : ($field_info['default_required'] ?? false);
                            $is_file_field = ($field_info['type'] === 'file');
                    ?>
                            <div class="mlr-field-config-item <?php echo $is_file_field ? 'is-document' : ''; ?>">
                                <span class="mlr-field-label" title="شناسه فیلد: <?php echo esc_attr($field_key); ?>">
                                     <?php echo esc_html($field_info['label']); ?>
                                     <small>(<?php
                                        // Display field type for clarity
                                        $type_map = ['text' => 'متن', 'select' => 'انتخابی', 'textarea' => 'متن طولانی', 'file' => 'فایل', 'email' => 'ایمیل'];
                                        echo $type_map[$field_info['type']] ?? esc_html($field_info['type']);
                                     ?>)</small>
                                </span>
                                <div class="mlr-field-controls">
                                    <?php // VISIBILITY TOGGLE (Only for file fields) ?>
                                    <?php if ($is_file_field): ?>
                                        <div class="mlr-control-pair">
                                            <label class="mlr-switch-label">نمایش:</label>
                                            <label class="mlr-switch">
                                                <input type="checkbox" name="mlr_cooperation_form_config[<?php echo esc_attr($field_key); ?>][visible]" value="1" <?php checked($current_visible); ?> class="mlr-visibility-toggle">
                                                <span class="mlr-slider"></span>
                                            </label>
                                        </div>
                                    <?php else: ?>
                                         <?php // Hidden input to ensure 'visible' is always set to 1 for non-file fields ?>
                                         <input type="hidden" name="mlr_cooperation_form_config[<?php echo esc_attr($field_key); ?>][visible]" value="1">
                                         <?php // Optional: Display a disabled 'Visible' status for non-file fields for consistency ?>
                                         <?php /*
                                         <div class="mlr-control-pair mlr-always-visible">
                                            <label class="mlr-switch-label mlr-disabled">نمایش:</label>
                                            <label class="mlr-switch mlr-disabled">
                                                <input type="checkbox" checked disabled>
                                                <span class="mlr-slider"></span>
                                            </label>
                                        </div>
                                         */ ?>
                                    <?php endif; ?>

                                    <?php // REQUIRED TOGGLE (Enabled only if visible) ?>
                                    <div class="mlr-control-pair">
                                         <label class="mlr-switch-label mlr-required-label <?php if (!$current_visible) echo 'mlr-disabled'; ?>">اجباری:</label>
                                         <label class="mlr-switch mlr-required-switch <?php if (!$current_visible) echo 'mlr-disabled'; ?>">
                                             <?php // Input name format: mlr_cooperation_form_config[field_key][required] ?>
                                             <input type="checkbox" name="mlr_cooperation_form_config[<?php echo esc_attr($field_key); ?>][required]" value="1" <?php checked($current_required); ?> <?php if (!$current_visible) echo 'disabled'; ?> >
                                             <span class="mlr-slider"></span>
                                         </label>
                                    </div>
                                </div>
                            </div>
                    <?php
                        } // end foreach field in group
                        echo '</div>'; // Close mlr-field-config-grid
                        echo '</div>'; // Close mlr-field-group
                    } // end foreach group
                    ?>
                 </div> <?php // Close mlr-field-config-container ?>
            </div>


            <?php submit_button('ذخیره تنظیمات فرم'); ?>
        </form>
    </div>

    <style>
        /* Modern Styles for Form Editor - V2 (Refined) */
        .mlr-form-editor-wrap .mlr-page-header { margin-bottom: 10px; }
        .mlr-form-editor-wrap .mlr-page-description { color: #50575e; margin-top: 5px; font-size: 14px; margin-bottom: 20px; }
        .mlr-form-editor-wrap .mlr-settings-card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04); overflow: hidden; }
        .mlr-form-editor-wrap .mlr-settings-card h2 { font-size: 16px; font-weight: 600; padding: 14px 20px; margin: 0; border-bottom: 1px solid #dcdcde; color: #1d2327; display: flex; align-items: center; gap: 8px; background-color: #f6f7f7; }
        .mlr-form-editor-wrap .mlr-settings-card h2 .dashicons { color: #50575e; font-size: 20px; height: auto; width: auto; }
        .mlr-form-editor-wrap .mlr-card-description { padding: 0 20px 15px; margin-top: -10px; color: #50575e; font-size: 13px; }

        /* Options Section */
        .mlr-options-section { padding: 20px 25px; display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; }
        .mlr-option-group label { font-weight: 600; margin-bottom: 8px; display: block; font-size: 13px; color: #1d2327; }
        .mlr-option-group textarea { border-color: #8c8f94; border-radius: 6px; min-height: 120px; font-family: inherit; font-size: 13px; }
        .mlr-option-group textarea:focus { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; outline: 2px solid transparent; }
        .mlr-option-group .description { font-size: 12px; color: #50575e; margin-top: 6px; }

        /* Field Config Section */
        .mlr-field-config-container { padding: 5px 15px 15px; }
        .mlr-field-group { margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px dashed #dcdcde; }
        .mlr-field-group:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .mlr-field-group-title { font-size: 14px; font-weight: 600; color: #1d2327; margin: 15px 10px; padding-bottom: 5px; border-bottom: 1px solid #e2e8f0;}
        .mlr-field-config-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px 20px; padding: 0 10px; }
        .mlr-field-config-item { display: flex; justify-content: space-between; align-items: center; background-color: #fcfdff; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 6px; transition: border-color 0.2s ease, box-shadow 0.1s ease; }
        .mlr-field-config-item:hover { border-color: #cbd5e0; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        .mlr-field-label { font-weight: 500; font-size: 13px; color: #2d3748; flex-grow: 1; margin-left: 10px; }
        .mlr-field-label small { color: #718096; font-size: 11px; margin-right: 5px; }
        .mlr-field-controls { display: flex; align-items: center; gap: 15px; flex-shrink: 0; }
        .mlr-control-pair { display: flex; align-items: center; gap: 6px; }

        /* Switch Styles (Copied from sms-settings.php styles) */
        .mlr-switch { position: relative; display: inline-block; width: 44px; height: 24px; vertical-align: middle; }
        .mlr-switch input { opacity: 0; width: 0; height: 0; }
        .mlr-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e0; transition: .4s; border-radius: 24px; }
        .mlr-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; box-shadow: 0 1px 3px rgba(0,0,0,0.2); transition: .4s; border-radius: 50%; }
        input:checked + .mlr-slider { background-color: #48bb78; /* Green */ }
        input:checked + .mlr-slider:before { transform: translateX(20px); }
        .mlr-switch-label { font-size: 12px; color: #4a5568; user-select: none; }

        /* Disabled state for required toggle */
        .mlr-required-switch.mlr-disabled .mlr-slider,
        .mlr-always-visible .mlr-slider { background-color: #e2e8f0; cursor: not-allowed; opacity: 0.7; }
        .mlr-required-switch.mlr-disabled .mlr-slider:before,
        .mlr-always-visible .mlr-slider:before { background-color: #f1f5f9; }
        .mlr-required-label.mlr-disabled,
        .mlr-always-visible .mlr-switch-label { color: #a0aec0; }
    </style>
    <?php
}