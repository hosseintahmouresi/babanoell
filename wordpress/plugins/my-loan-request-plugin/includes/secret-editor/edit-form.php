<?php
if (!defined('ABSPATH')) exit;
$r = $row;
$statuses = function_exists('mlr_get_all_valid_statuses')
    ? mlr_get_all_valid_statuses()
    : ['در حال بررسی', 'نقص مدارک', 'رد شده', 'تأیید کارشناس', 'تکمیل مدارک', 'تأیید مدارک', 'اعطا شده'];
$settlement_statuses = ['', 'منتظر تعیین تاریخ', 'در انتظار تسویه', 'تسویه شده'];
$document_receipt_statuses = function_exists('mlr_get_document_receipt_status_options')
    ? mlr_get_document_receipt_status_options()
    : ['بررسی نشده', 'دریافت شده', 'عدم دریافت', 'عدم نیاز به دریافت مدارک'];
$current_status = function_exists('mlr_normalize_status') ? mlr_normalize_status($r['status'] ?? '') : trim((string)($r['status'] ?? ''));
$current_settlement_status = function_exists('mlr_normalize_status') ? mlr_normalize_status($r['settlement_status'] ?? '') : trim((string)($r['settlement_status'] ?? ''));
$current_document_receipt_status = function_exists('mlr_get_document_receipt_status_label') ? mlr_get_document_receipt_status_label($r['document_receipt_status'] ?? '') : trim((string)($r['document_receipt_status'] ?? ''));
if ($current_status !== '' && !in_array($current_status, $statuses, true)) {
    array_unshift($statuses, $current_status);
}
if ($current_settlement_status !== '' && !in_array($current_settlement_status, $settlement_statuses, true)) {
    array_unshift($settlement_statuses, $current_settlement_status);
}
if ($current_document_receipt_status !== '' && !in_array($current_document_receipt_status, $document_receipt_statuses, true)) {
    array_unshift($document_receipt_statuses, $current_document_receipt_status);
}
?>
<form id="mlr-secret-edit-form" class="mlr-secret-form" data-id="<?php echo esc_attr($r['id']); ?>">
    <input type="hidden" name="id" value="<?php echo esc_attr($r['id']); ?>">
    <div class="mlr-secret-form-grid">
        <section class="mlr-secret-form-section">
            <h3><span class="dashicons dashicons-admin-users"></span> اطلاعات متقاضی</h3>
            <div class="mlr-secret-fields">
                <div class="mlr-secret-field"><label>user_id</label><input type="number" name="user_id" value="<?php echo esc_attr($r['user_id'] ?? ''); ?>"></div>
                <div class="mlr-secret-field"><label>نام کامل</label><input type="text" name="full_name" value="<?php echo esc_attr($r['full_name'] ?? ''); ?>"></div>
                <div class="mlr-secret-field"><label>کد ملی</label><input type="text" name="national_code" value="<?php echo esc_attr($r['national_code'] ?? ''); ?>"></div>
                <div class="mlr-secret-field"><label>موبایل</label><input type="text" name="mobile_number" value="<?php echo esc_attr($r['mobile_number'] ?? ''); ?>"></div>
                <div class="mlr-secret-field"><label>تلفن</label><input type="text" name="phone_number" value="<?php echo esc_attr($r['phone_number'] ?? ''); ?>"></div>
                <div class="mlr-secret-field"><label>تاریخ تولد</label><input type="text" name="birth_date" value="<?php echo esc_attr($r['birth_date'] ?? ''); ?>"></div>
                <div class="mlr-secret-field"><label>شغل</label><input type="text" name="occupation" value="<?php echo esc_attr($r['occupation'] ?? ''); ?>"></div>
                <div class="mlr-secret-field"><label>آدرس</label><textarea name="residential_address" rows="2"><?php echo esc_textarea($r['residential_address'] ?? ''); ?></textarea></div>
                <div class="mlr-secret-field"><label>کد پستی</label><input type="text" name="postal_code" value="<?php echo esc_attr($r['postal_code'] ?? ''); ?>"></div>
                <div class="mlr-secret-field"><label>آدرس کار</label><textarea name="work_address" rows="2"><?php echo esc_textarea($r['work_address'] ?? ''); ?></textarea></div>
            </div>
        </section>
        <section class="mlr-secret-form-section">
            <h3><span class="dashicons dashicons-money-alt"></span> مبالغ و طرح</h3>
            <div class="mlr-secret-fields">
                <div class="mlr-secret-field">
                    <label>طرح اقساط</label>
                    <select name="installment_plan_id">
                        <?php foreach ($plans as $p): ?>
                            <option value="<?php echo esc_attr($p->id); ?>" <?php selected($r['installment_plan_id'] ?? 0, $p->id); ?>><?php echo esc_html($p->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mlr-secret-field"><label>نام محصول</label><input type="text" name="product_name" value="<?php echo esc_attr($r['product_name'] ?? ''); ?>"></div>
                <div class="mlr-secret-field"><label>مبلغ درخواستی (ریال)</label><input type="number" name="loan_amount" value="<?php echo esc_attr($r['loan_amount'] ?? ''); ?>"></div>
                <div class="mlr-secret-field"><label>مبلغ تسهیلات (ریال)</label><input type="number" name="facility_amount" value="<?php echo esc_attr($r['facility_amount'] ?? ''); ?>" placeholder="خالی = همان مبلغ درخواستی"></div>
                <div class="mlr-secret-field"><label>پیش پرداخت</label><input type="number" name="prepayment" value="<?php echo esc_attr($r['prepayment'] ?? ''); ?>"></div>
                <div class="mlr-secret-field"><label>اعتبار خرید</label><input type="number" name="wallet_charge" value="<?php echo esc_attr($r['wallet_charge'] ?? ''); ?>"></div>
                <div class="mlr-secret-field"><label>مبلغ تسویه</label><input type="number" name="acceptor_payment_amount" value="<?php echo esc_attr($r['acceptor_payment_amount'] ?? ''); ?>"></div>
                <div class="mlr-secret-field"><label>مبلغ تسویه نهایی</label><input type="number" name="final_settlement_amount" value="<?php echo esc_attr($r['final_settlement_amount'] ?? ''); ?>"></div>
                <div class="mlr-secret-field"><label>فاصله اقساط</label><input type="number" name="installment_interval" value="<?php echo esc_attr($r['installment_interval'] ?? ''); ?>"></div>
                <div class="mlr-secret-field"><label>تعداد اقساط</label><input type="number" name="installment_count" value="<?php echo esc_attr($r['installment_count'] ?? ''); ?>"></div>
                <div class="mlr-secret-field"><label>مبلغ هر قسط</label><input type="number" name="installment_amount" value="<?php echo esc_attr($r['installment_amount'] ?? ''); ?>"></div>
                <div class="mlr-secret-field"><label>مجموع پرداخت</label><input type="number" name="total_payment" value="<?php echo esc_attr($r['total_payment'] ?? ''); ?>"></div>
                <div class="mlr-secret-field"><label>سود</label><input type="number" name="profit" value="<?php echo esc_attr($r['profit'] ?? ''); ?>"></div>
                <div class="mlr-secret-field"><label>مبلغ سفارش</label><input type="number" name="order_total" value="<?php echo esc_attr($r['order_total'] ?? ''); ?>"></div>
            </div>
        </section>
        <section class="mlr-secret-form-section">
            <h3><span class="dashicons dashicons-yes-alt"></span> وضعیت و تسویه</h3>
            <div class="mlr-secret-fields">
                <div class="mlr-secret-field">
                    <label>وضعیت</label>
                    <select name="status">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?php echo esc_attr($s); ?>" <?php selected($current_status, $s); ?>><?php echo esc_html($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mlr-secret-field">
                    <label>دریافت مدارک</label>
                    <select name="document_receipt_status">
                        <?php foreach ($document_receipt_statuses as $document_receipt_status): ?>
                            <option value="<?php echo esc_attr($document_receipt_status); ?>" <?php selected($current_document_receipt_status, $document_receipt_status); ?>><?php echo esc_html($document_receipt_status); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mlr-secret-field">
                    <label>وضعیت تسویه</label>
                    <select name="settlement_status">
                        <?php foreach ($settlement_statuses as $ss): ?>
                            <option value="<?php echo esc_attr($ss); ?>" <?php selected($current_settlement_status, $ss); ?>><?php echo esc_html($ss ?: '—'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mlr-secret-field"><label>کارشناس بانک (ID)</label><input type="number" name="assigned_bank_user_id" value="<?php echo esc_attr($r['assigned_bank_user_id'] ?? ''); ?>"></div>
                <div class="mlr-secret-field"><label>سیکل تسویه (ID)</label><input type="number" name="settlement_cycle_id" value="<?php echo esc_attr($r['settlement_cycle_id'] ?? ''); ?>"></div>
                <div class="mlr-secret-field"><label>تاریخ تسویه</label><input type="text" name="settlement_date" value="<?php echo esc_attr($r['settlement_date'] ?? ''); ?>" placeholder="YYYY-MM-DD HH:MM:SS"></div>
                <div class="mlr-secret-field"><label>تاریخ ثبت</label><input type="text" name="created_at" value="<?php echo esc_attr($r['created_at'] ?? ''); ?>" placeholder="YYYY-MM-DD HH:MM:SS"></div>
                <div class="mlr-secret-field"><label>آخرین ویرایش</label><input type="text" name="last_edited_at" value="<?php echo esc_attr($r['last_edited_at'] ?? ''); ?>" placeholder="YYYY-MM-DD HH:MM:SS"></div>
            </div>
        </section>
        <section class="mlr-secret-form-section mlr-secret-form-section-full">
            <h3><span class="dashicons dashicons-media-document"></span> متون و JSON</h3>
            <div class="mlr-secret-fields">
                <div class="mlr-secret-field"><label>اقلام سفارش</label><textarea name="order_items" rows="3"><?php echo esc_textarea($r['order_items'] ?? ''); ?></textarea></div>
                <div class="mlr-secret-field"><label>توضیحات</label><textarea name="comments" rows="3"><?php echo esc_textarea($r['comments'] ?? ''); ?></textarea></div>
                <div class="mlr-secret-field"><label>form_data (JSON)</label><textarea name="form_data" rows="4" class="mlr-secret-code"><?php echo esc_textarea(is_string($r['form_data'] ?? '') ? $r['form_data'] : json_encode($r['form_data'] ?? [], JSON_UNESCAPED_UNICODE)); ?></textarea></div>
                <div class="mlr-secret-field"><label>guarantor_info (JSON)</label><textarea name="guarantor_info" rows="4" class="mlr-secret-code"><?php echo esc_textarea(is_string($r['guarantor_info'] ?? '') ? $r['guarantor_info'] : json_encode($r['guarantor_info'] ?? [], JSON_UNESCAPED_UNICODE)); ?></textarea></div>
                <div class="mlr-secret-field"><label>pending_docs_attachments (JSON)</label><textarea name="pending_docs_attachments" rows="3" class="mlr-secret-code"><?php echo esc_textarea(is_string($r['pending_docs_attachments'] ?? '') ? $r['pending_docs_attachments'] : json_encode($r['pending_docs_attachments'] ?? [], JSON_UNESCAPED_UNICODE)); ?></textarea></div>
                <div class="mlr-secret-field"><label>required_documents</label><textarea name="required_documents" rows="2"><?php echo esc_textarea($r['required_documents'] ?? ''); ?></textarea></div>
                <div class="mlr-secret-field"><label>فایل‌ها (مسیر)</label> bank_validation: <input type="text" name="bank_validation_file" value="<?php echo esc_attr($r['bank_validation_file'] ?? ''); ?>"><br>signature: <input type="text" name="signature_image" value="<?php echo esc_attr($r['signature_image'] ?? ''); ?>"><br>contract: <input type="text" name="contract_file" value="<?php echo esc_attr($r['contract_file'] ?? ''); ?>"></div>
                <div class="mlr-secret-field"><label>check_images</label><textarea name="check_images" rows="2"><?php echo esc_textarea(is_string($r['check_images'] ?? '') ? $r['check_images'] : ''); ?></textarea></div>
            </div>
        </section>
    </div>
    <div class="mlr-secret-form-actions">
        <button type="submit" class="mlr-secret-btn mlr-secret-btn-primary">ذخیره تغییرات</button>
        <button type="button" class="mlr-secret-btn mlr-secret-modal-close">انصراف</button>
        <span class="mlr-secret-form-msg"></span>
    </div>
</form>
