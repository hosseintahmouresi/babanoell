<?php
add_shortcode('mlr_edit_requests', 'mlr_edit_requests_shortcode');

function mlr_edit_requests_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>برای دسترسی به این بخش، لطفاً ابتدا وارد شوید.</p>';
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'loan_requests';
    $user_id = get_current_user_id();

    if (!isset($_GET['mlr_edit']) || !isset($_GET['nonce'])) {
        return '<p>لینک ویرایش نامعتبر است.</p>';
    }

    $request_id = intval($_GET['mlr_edit']);
    $nonce = sanitize_text_field(wp_unslash($_GET['nonce']));

    if (!wp_verify_nonce($nonce, 'mlr_edit_' . $request_id)) {
        return '<p>خطای امنیتی. لطفاً از طریق پنل کاربری خود اقدام کنید.</p>';
    }

    $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d AND status = 'نقص مدارک' LIMIT 1", $request_id));

    $can_edit = $request && (
        function_exists('mlr_is_acceptor_side_request_owner')
            ? mlr_is_acceptor_side_request_owner($request, $user_id)
            : intval($request->user_id ?? 0) === intval($user_id)
    );

    if (!$can_edit) {
        return '<p>درخواست مورد نظر یافت نشد یا در حال حاضر قابل ویرایش نیست.</p>';
    }

    $plan_docs_table = $wpdb->prefix . 'installment_plans';
    $plan_for_documents = $wpdb->get_row($wpdb->prepare(
        "SELECT required_documents, settings FROM $plan_docs_table WHERE id = %d LIMIT 1",
        $request->installment_plan_id
    ));
    $plan_document_settings = $plan_for_documents ? maybe_unserialize($plan_for_documents->settings) : [];
    if (!is_array($plan_document_settings)) {
        $plan_document_settings = [];
    }
    $occupation_documents_enabled = function_exists('mlr_plan_uses_occupation_documents')
        && mlr_plan_uses_occupation_documents($plan_document_settings);
    $existing_uploaded_documents = function_exists('mlr_get_request_uploaded_documents')
        ? mlr_get_request_uploaded_documents($request->required_documents)
        : maybe_unserialize($request->required_documents);
    if (!is_array($existing_uploaded_documents)) {
        $existing_uploaded_documents = [];
    }

    if (isset($_POST['mlr_update_request']) && check_admin_referer('mlr_edit_request_action')) {
        $preserve_text_value = function($key, $fallback) {
            if (!isset($_POST[$key])) {
                return $fallback;
            }

            $value = is_string($_POST[$key]) ? wp_unslash($_POST[$key]) : $_POST[$key];
            $value = is_scalar($value) ? trim((string) $value) : '';

            return $value === '' && $fallback !== '' && $fallback !== null ? $fallback : $value;
        };
        
        $data = [
            'full_name' => sanitize_text_field($preserve_text_value('full_name', $request->full_name)),
            'national_code' => sanitize_text_field($preserve_text_value('national_code', $request->national_code)),
            'mobile_number' => sanitize_text_field($preserve_text_value('mobile_number', $request->mobile_number)),
            'phone_number' => sanitize_text_field($preserve_text_value('phone_number', $request->phone_number)),
            'birth_date' => sanitize_text_field($preserve_text_value('birth_date', $request->birth_date)),
            'occupation' => sanitize_text_field($preserve_text_value('occupation', $request->occupation)),
            'residential_address' => sanitize_textarea_field($preserve_text_value('residential_address', $request->residential_address)),
            'postal_code' => sanitize_text_field($preserve_text_value('postal_code', $request->postal_code)),
            'work_address' => sanitize_textarea_field($preserve_text_value('work_address', $request->work_address)),
            'product_name' => sanitize_textarea_field($preserve_text_value('product_name', $request->product_name)),
            'status' => 'در حال بررسی',
            'last_edited_at' => current_time('mysql')
        ];

        $submitted_occupation_key = sanitize_key((string) $data['occupation']);
        if (function_exists('mlr_validate_plan_occupation')) {
            $occupation_validation = mlr_validate_plan_occupation($plan_document_settings, $submitted_occupation_key);
            if (is_wp_error($occupation_validation)) {
                echo '<div class="error"><p>' . esc_html($occupation_validation->get_error_message()) . '</p></div>';
                return;
            }
        }
        if ($occupation_documents_enabled && function_exists('mlr_normalize_occupation_key')) {
            $data['occupation'] = mlr_normalize_occupation_key($submitted_occupation_key);
        }
        $required_docs_keys_for_submit = $plan_for_documents && function_exists('mlr_get_plan_required_document_keys')
            ? mlr_get_plan_required_document_keys($plan_for_documents->required_documents, $plan_document_settings, $submitted_occupation_key)
            : ($plan_for_documents ? maybe_unserialize($plan_for_documents->required_documents) : []);
        if (!is_array($required_docs_keys_for_submit)) {
            $required_docs_keys_for_submit = [];
        }
        $allowed_doc_lookup = array_fill_keys(array_map('sanitize_key', $required_docs_keys_for_submit), true);

        $form_data = [];
        $can_sync_form_data = true;
        if (!empty($request->form_data)) {
            $decoded_form_data = json_decode((string) $request->form_data, true);
            if (!is_array($decoded_form_data)) {
                $decoded_form_data = json_decode(stripslashes((string) $request->form_data), true);
            }

            if (is_array($decoded_form_data)) {
                $form_data = $decoded_form_data;
            } else {
                $can_sync_form_data = false;
            }
        }

        if ($can_sync_form_data) {
            foreach (['full_name', 'national_code', 'mobile_number', 'phone_number', 'birth_date', 'occupation', 'residential_address', 'postal_code', 'work_address', 'product_name'] as $field_key) {
                $form_data[$field_key] = $data[$field_key];
            }

            $data['form_data'] = wp_json_encode($form_data, JSON_UNESCAPED_UNICODE);
        }

        $uploaded_documents = $existing_uploaded_documents;
        if (!empty($_FILES)) {
            foreach ($_FILES as $file_key => $file) {
                $document_key = strpos($file_key, 'doc_file_') === 0 ? substr($file_key, strlen('doc_file_')) : $file_key;
                $document_key = sanitize_key($document_key);
                if ($document_key === '' || !isset($allowed_doc_lookup[$document_key])) {
                    continue;
                }
                if (!empty($file['name'])) {
                    $upload = mlr_handle_wp_upload($file);
                    if (isset($upload['url'])) {
                        $uploaded_documents[$document_key] = $upload['url'];
                    } else {
                        echo '<div class="error"><p>خطا در آپلود فایل ' . esc_html($document_key) . ': ' . esc_html($upload['error']) . '</p></div>';
                        return;
                    }
                }
            }
        }
        $missing_documents = [];
        $document_labels = function_exists('mlr_get_all_document_options') ? mlr_get_all_document_options() : [];
        foreach ($required_docs_keys_for_submit as $required_key) {
            $required_key = sanitize_key((string) $required_key);
            if ($required_key === '') continue;
            $value = $uploaded_documents[$required_key] ?? null;
            $has_value = is_array($value) ? !empty(array_filter($value)) : (is_string($value) && trim($value) !== '');
            if (!$has_value) {
                $missing_documents[] = $document_labels[$required_key] ?? $required_key;
            }
        }
        if (!empty($missing_documents)) {
            echo '<div class="error"><p>بارگذاری مدارک اجباری این شغل کامل نیست: ' . esc_html(implode('، ', $missing_documents)) . '</p></div>';
            return;
        }

        $data['required_documents'] = serialize($uploaded_documents);
        
        $updated = $wpdb->update($table_name, $data, ['id' => $request_id, 'status' => 'نقص مدارک']);
        if ($updated === false) {
            echo '<div class="error"><p>خطا در ذخیره تغییرات: ' . esc_html($wpdb->last_error) . '</p></div>';
            return;
        }

        if ($updated !== 1) {
            echo '<div class="error"><p>وضعیت پرونده هم‌زمان تغییر کرده است؛ صفحه را تازه‌سازی کنید.</p></div>';
            return;
        }

        $fresh_request = $wpdb->get_row($wpdb->prepare("SELECT full_name FROM $table_name WHERE id = %d LIMIT 1", $request_id));
        if (!$fresh_request || trim((string) $fresh_request->full_name) !== trim((string) $data['full_name'])) {
            echo '<div class="error"><p>ذخیره انجام نشد. لطفاً دوباره تلاش کنید.</p></div>';
            return;
        }
        
        mlr_send_sms_on_loan_status_change($request_id, 'در حال بررسی');
        
        echo '<div class="updated"><p>درخواست شما با موفقیت ویرایش و برای بررسی مجدد ارسال شد.</p></div>';
        
        return;
    }

    $occupations = function_exists('mlr_get_occupation_options') ? mlr_get_occupation_options() : [
        'official_employee' => 'کارمند رسمی', 'contract_employee' => 'کارمند قراردادی', 'worker' => 'کارگر',
        'freelancer' => 'آزاد', 'retired' => 'بازنشسته', 'pensioner' => 'مستمری بگیر',
        'employer' => 'کارفرما', 'other' => 'سایر'
    ];

    ob_start();
    ?>
    <div class="mlr-edit-request-form">
        <h2>ویرایش درخواست (شناسه: <?php echo $request_id; ?>)</h2>
        <p>لطفاً اطلاعات زیر را تکمیل یا اصلاح کرده و مدارک مورد نیاز را بارگذاری نمایید.</p>
        
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('mlr_edit_request_action'); ?>
            
            <h3>اطلاعات فردی و تماس</h3>
            <p><label>نام کامل: <input type="text" name="full_name" value="<?php echo esc_attr($request->full_name); ?>" required></label></p>
            <p><label>کد ملی: <input type="text" name="national_code" value="<?php echo esc_attr($request->national_code); ?>" required pattern="\d{10}"></label></p>
            <p><label>شماره موبایل: <input type="text" name="mobile_number" value="<?php echo esc_attr($request->mobile_number); ?>" required pattern="09\d{9}"></label></p>
            <p><label>شماره تلفن ثابت: <input type="text" name="phone_number" value="<?php echo esc_attr($request->phone_number); ?>"></label></p>
            <p><label>تاریخ تولد: <input type="date" name="birth_date" value="<?php echo esc_attr($request->birth_date); ?>"></label></p>
            <p><label>شغل: 
                <select name="occupation" id="mlr_edit_occupation" required>
                    <option value="">انتخاب کنید...</option>
                    <?php foreach ($occupations as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($request->occupation, $key); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label></p>

            <h3>اطلاعات آدرس و محصول</h3>
            <p><label>آدرس محل سکونت: <textarea name="residential_address" rows="3"><?php echo esc_textarea($request->residential_address); ?></textarea></label></p>
            <p><label>کد پستی: <input type="text" name="postal_code" value="<?php echo esc_attr($request->postal_code); ?>" pattern="\d{10}"></label></p>
            <p><label>آدرس محل فعالیت: <textarea name="work_address" rows="3"><?php echo esc_textarea($request->work_address); ?></textarea></label></p>
            <p><label>نام کامل محصول: <textarea name="product_name" rows="2"><?php echo esc_textarea($request->product_name); ?></textarea></label></p>

            <h3>بارگذاری مجدد مدارک (فقط در صورت نیاز)</h3>
            <?php
            $doc_labels = mlr_get_all_document_options();
            if ($occupation_documents_enabled && $plan_for_documents) :
                foreach ($occupations as $occupation_key => $occupation_label) :
                    $occupation_required_docs = function_exists('mlr_get_plan_required_document_keys')
                        ? mlr_get_plan_required_document_keys($plan_for_documents->required_documents, $plan_document_settings, $occupation_key)
                        : [];
                    $is_current_occupation = $request->occupation === $occupation_key;
                    ?>
                    <div class="mlr-edit-occupation-docs-section" data-occupation="<?php echo esc_attr($occupation_key); ?>" style="<?php echo $is_current_occupation ? '' : 'display:none;'; ?>">
                        <h4>مدارک مخصوص <?php echo esc_html($occupation_label); ?></h4>
                        <?php foreach ($occupation_required_docs as $doc_key) :
                            $label = $doc_labels[$doc_key] ?? ('مدرک ' . $doc_key);
                            $has_existing = !empty($existing_uploaded_documents[$doc_key]);
                            ?>
                            <p><label><?php echo esc_html($label); ?>:
                                <input type="file" name="doc_file_<?php echo esc_attr($doc_key); ?>" <?php echo $is_current_occupation ? '' : 'disabled'; ?>>
                                <?php if ($has_existing): ?><small style="color:#2271b1">مدرک فعلی موجود است؛ فقط در صورت نیاز فایل جدید انتخاب کنید.</small><?php endif; ?>
                            </label></p>
                        <?php endforeach; ?>
                    </div>
                    <?php
                endforeach;
            else:
                $required_docs_keys = $plan_for_documents ? maybe_unserialize($plan_for_documents->required_documents) : [];
                if (!empty($required_docs_keys)) :
                    foreach ($required_docs_keys as $doc_key) :
                        $label = $doc_labels[$doc_key] ?? ('مدرک ' . $doc_key);
                        ?>
                        <p><label><?php echo esc_html($label); ?>: <input type="file" name="doc_file_<?php echo esc_attr($doc_key); ?>"></label></p>
                        <?php
                    endforeach;
                endif;
            endif;
            ?>

            <?php if ($occupation_documents_enabled): ?>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var select = document.getElementById('mlr_edit_occupation');
                if (!select) return;
                function syncOccupationDocuments() {
                    document.querySelectorAll('.mlr-edit-occupation-docs-section').forEach(function (section) {
                        var active = section.getAttribute('data-occupation') === select.value;
                        section.style.display = active ? '' : 'none';
                        section.querySelectorAll('input[type="file"]').forEach(function (input) { input.disabled = !active; });
                    });
                }
                select.addEventListener('change', syncOccupationDocuments);
                syncOccupationDocuments();
            });
            </script>
            <?php endif; ?>
            
            <button type="submit" name="mlr_update_request" class="button button-primary">ذخیره تغییرات و ارسال مجدد</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
