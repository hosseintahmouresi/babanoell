<?php
/**
 * Per-request loan contract reissue / replacement workflow.
 *
 * Design goals:
 * - Never overwrite a signed proof in-place.
 * - Archive the previous signed snapshot before activating a new dynamic template.
 * - Keep the public contract URL stable, but require a fresh OTP signature.
 * - Allow re-signing even when the request has moved past the normal document stage.
 * - Keep plan-level templates untouched; the override applies only to the selected request.
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mlr_lc_reissue_option_name')) {
    function mlr_lc_reissue_option_name($request_id) {
        return 'mlr_lc_reissue_' . absint($request_id);
    }
}

if (!function_exists('mlr_lc_reissue_counter_option_name')) {
    function mlr_lc_reissue_counter_option_name($request_id) {
        return 'mlr_lc_rev_' . absint($request_id);
    }
}

if (!function_exists('mlr_lc_reissue_index_option_name')) {
    function mlr_lc_reissue_index_option_name($request_id) {
        return 'mlr_lc_rev_index_' . absint($request_id);
    }
}

if (!function_exists('mlr_lc_reissue_archive_option_name')) {
    function mlr_lc_reissue_archive_option_name($request_id, $revision) {
        return 'mlr_lc_archive_' . absint($request_id) . '_' . max(1, absint($revision));
    }
}

if (!function_exists('mlr_lc_store_nonautoload_option')) {
    function mlr_lc_store_nonautoload_option($name, $value) {
        $missing = '__MLR_OPTION_MISSING__';
        if (get_option($name, $missing) === $missing) {
            return add_option($name, $value, '', false);
        }
        return update_option($name, $value, false);
    }
}

if (!function_exists('mlr_get_active_loan_contract_reissue')) {
    function mlr_get_active_loan_contract_reissue($request_id) {
        $request_id = absint($request_id);
        if ($request_id <= 0) return null;
        $data = get_option(mlr_lc_reissue_option_name($request_id), null);
        if (!is_array($data) || empty($data['contract_text'])) return null;
        $state = sanitize_key((string) ($data['state'] ?? 'pending'));
        if (!in_array($state, ['pending', 'signed'], true)) return null;
        return $data;
    }
}

if (!function_exists('mlr_contract_reissue_is_pending')) {
    function mlr_contract_reissue_is_pending($request_id) {
        $data = mlr_get_active_loan_contract_reissue($request_id);
        return is_array($data) && (($data['state'] ?? '') === 'pending');
    }
}

if (!function_exists('mlr_get_loan_contract_reissue_override')) {
    /**
     * Called from the canonical payload builder. A preview can temporarily override
     * the persisted value only for the current PHP request.
     */
    function mlr_get_loan_contract_reissue_override($request_id) {
        $request_id = absint($request_id);
        if ($request_id <= 0) return null;
        if (!empty($GLOBALS['mlr_lc_reissue_preview'][$request_id]) && is_array($GLOBALS['mlr_lc_reissue_preview'][$request_id])) {
            return $GLOBALS['mlr_lc_reissue_preview'][$request_id];
        }
        return mlr_get_active_loan_contract_reissue($request_id);
    }
}

if (!function_exists('mlr_lc_reissue_get_request')) {
    function mlr_lc_reissue_get_request($request_id) {
        global $wpdb;
        $request_id = absint($request_id);
        if ($request_id <= 0) return null;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$wpdb->prefix}loan_requests` WHERE id = %d LIMIT 1",
            $request_id
        ));
    }
}

if (!function_exists('mlr_lc_reissue_get_plan')) {
    function mlr_lc_reissue_get_plan($plan_id) {
        global $wpdb;
        $plan_id = absint($plan_id);
        if ($plan_id <= 0) return null;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, settings FROM `{$wpdb->prefix}installment_plans` WHERE id = %d LIMIT 1",
            $plan_id
        ));
    }
}

if (!function_exists('mlr_lc_reissue_archive_proof')) {
    function mlr_lc_reissue_archive_proof($request_id, $revision, array $proof, $reason, $next_revision) {
        $request_id = absint($request_id);
        $revision = max(1, absint($revision));
        $next_revision = max($revision + 1, absint($next_revision));
        if ($request_id <= 0 || empty($proof['signed_at']) || empty($proof['contract_digest'])) {
            return new WP_Error('mlr_lc_archive_invalid', 'نسخه امضاشده معتبر برای بایگانی یافت نشد.');
        }

        $archive_name = mlr_lc_reissue_archive_option_name($request_id, $revision);
        $existing = get_option($archive_name, null);
        if (is_array($existing) && !empty($existing['proof']['contract_digest'])) {
            if (!hash_equals((string) $existing['proof']['contract_digest'], (string) $proof['contract_digest'])) {
                return new WP_Error('mlr_lc_archive_conflict', 'برای این نسخه قبلاً یک سند بایگانی متفاوت ثبت شده است؛ عملیات برای جلوگیری از بازنویسی تاریخچه متوقف شد.');
            }
            return true;
        }

        $archive = [
            'request_id' => $request_id,
            'revision' => $revision,
            'replaced_by_revision' => $next_revision,
            'reason' => sanitize_textarea_field((string) $reason),
            'archived_at' => current_time('mysql'),
            'archived_by' => get_current_user_id(),
            'proof' => $proof,
        ];
        if (!add_option($archive_name, $archive, '', false)) {
            return new WP_Error('mlr_lc_archive_store_failed', 'ذخیره نسخه قبلی قرارداد در تاریخچه انجام نشد؛ هیچ تغییری روی امضای فعلی اعمال نشد.');
        }

        $index_name = mlr_lc_reissue_index_option_name($request_id);
        $index = get_option($index_name, []);
        if (!is_array($index)) $index = [];
        $index[(string) $revision] = [
            'revision' => $revision,
            'signed_at' => sanitize_text_field((string) ($proof['signed_at'] ?? '')),
            'contract_digest' => sanitize_text_field((string) ($proof['contract_digest'] ?? '')),
            'archived_at' => $archive['archived_at'],
            'archived_by' => $archive['archived_by'],
            'replaced_by_revision' => $next_revision,
            'reason' => $archive['reason'],
        ];
        ksort($index, SORT_NUMERIC);
        mlr_lc_store_nonautoload_option($index_name, $index);
        return true;
    }
}

if (!function_exists('mlr_start_loan_contract_reissue')) {
    function mlr_start_loan_contract_reissue($request_id, $raw_contract_text, $reason = '', $send_now = false) {
        $request_id = absint($request_id);
        if ($request_id <= 0) {
            return new WP_Error('mlr_lc_reissue_request_invalid', 'شناسه پرونده نامعتبر است.');
        }

        $request = mlr_lc_reissue_get_request($request_id);
        if (!$request) {
            return new WP_Error('mlr_lc_reissue_request_missing', 'پرونده‌ای با این شناسه یافت نشد.');
        }
        if (!function_exists('mlr_is_loan_contract_digital') || !mlr_is_loan_contract_digital($request, true)) {
            return new WP_Error('mlr_lc_reissue_not_digital', 'بازصدور فقط برای پرونده‌ای که قرارداد دیجیتال دارد مجاز است.');
        }

        $reason = sanitize_textarea_field((string) $reason);
        if (mb_strlen(trim($reason)) < 3) {
            return new WP_Error('mlr_lc_reissue_reason_required', 'برای ثبت قابل پیگیری، علت بازصدور قرارداد را وارد کنید.');
        }

        // Preserve dynamic placeholders even when they appear inside HTML attributes
        // (for example src="[fintech_logo_url]"). Rendering is sanitized later by
        // mlr_get_loan_contract_payload() after placeholder replacement.
        $contract_text = wp_check_invalid_utf8((string) $raw_contract_text, true);
        $contract_text = str_replace("\0", '', $contract_text);
        if (trim(wp_strip_all_tags($contract_text)) === '') {
            return new WP_Error('mlr_lc_reissue_text_empty', 'متن قرارداد جدید خالی است.');
        }

        $active_reissue = mlr_get_active_loan_contract_reissue($request_id);
        $current_proof = function_exists('mlr_get_loan_contract_signature') ? mlr_get_loan_contract_signature($request_id) : null;
        $counter_name = mlr_lc_reissue_counter_option_name($request_id);
        $counter = max(0, absint(get_option($counter_name, 0)));

        // Updating an already pending reissue does not create a fake extra revision.
        if (!is_array($current_proof) && is_array($active_reissue) && (($active_reissue['state'] ?? '') === 'pending')) {
            $new_revision = max(2, absint($active_reissue['revision'] ?? ($counter ?: 2)));
            $previous_revision = max(1, absint($active_reissue['previous_revision'] ?? ($new_revision - 1)));
        } else {
            if (!is_array($current_proof)) {
                return new WP_Error('mlr_lc_reissue_unsigned', 'این پرونده در حال حاضر نسخه امضاشده‌ای برای بازصدور ندارد. ابتدا وضعیت امضای قرارداد را بررسی کنید.');
            }
            $current_revision = $counter > 0 ? $counter : max(1, absint($current_proof['contract_revision'] ?? 1));
            $new_revision = $current_revision + 1;
            $previous_revision = $current_revision;

            $archived = mlr_lc_reissue_archive_proof($request_id, $current_revision, $current_proof, $reason, $new_revision);
            if (is_wp_error($archived)) return $archived;
        }

        $override = [
            'request_id' => $request_id,
            'revision' => $new_revision,
            'previous_revision' => $previous_revision,
            'state' => 'pending',
            'contract_text' => $contract_text,
            'reason' => $reason,
            'created_at' => is_array($active_reissue) && (($active_reissue['state'] ?? '') === 'pending')
                ? (string) ($active_reissue['created_at'] ?? current_time('mysql'))
                : current_time('mysql'),
            'created_by' => is_array($active_reissue) && (($active_reissue['state'] ?? '') === 'pending')
                ? absint($active_reissue['created_by'] ?? get_current_user_id())
                : get_current_user_id(),
            'updated_at' => current_time('mysql'),
            'updated_by' => get_current_user_id(),
        ];

        if (!mlr_lc_store_nonautoload_option(mlr_lc_reissue_option_name($request_id), $override)) {
            return new WP_Error('mlr_lc_reissue_store_failed', 'ذخیره قرارداد جدید انجام نشد.');
        }
        mlr_lc_store_nonautoload_option($counter_name, $new_revision);

        // From this exact point the new revision becomes the active unsigned contract.
        if (is_array($current_proof)) {
            delete_option(mlr_loan_contract_signature_option_name($request_id));
        }
        delete_transient(mlr_loan_contract_otp_transient_key($request_id));
        delete_option(mlr_loan_contract_otp_lock_name($request_id));

        if (function_exists('mlr_add_request_event')) {
            mlr_add_request_event($request_id, 'loan_contract_reissued', [
                'شرح' => 'نسخه جدید قرارداد برای امضای مجدد صادر شد.',
                'نسخه جدید' => (string) $new_revision,
                'نسخه قبلی' => (string) $previous_revision,
                'علت' => $reason,
                'کاربر مدیر' => (string) get_current_user_id(),
            ]);
        }
        if (function_exists('mlr_create_notification')) {
            mlr_create_notification(
                'loan_contract_reissued',
                'قرارداد پرونده #' . $request_id . ' برای امضای مجدد بازصدور شد (نسخه ' . $new_revision . ').',
                admin_url('admin.php?page=mlr-loan-contract-reissue&request_id=' . $request_id)
            );
        }
        if (function_exists('mlr_log')) {
            mlr_log('Loan contract reissued.', [
                'request_id' => $request_id,
                'revision' => $new_revision,
                'previous_revision' => $previous_revision,
                'admin_user_id' => get_current_user_id(),
            ]);
        }

        $result = [
            'request_id' => $request_id,
            'revision' => $new_revision,
            'previous_revision' => $previous_revision,
            'contract_url' => function_exists('mlr_get_loan_contract_view_url') ? mlr_get_loan_contract_view_url($request_id) : '',
            'sms_result' => null,
        ];

        if ($send_now) {
            // Reissue UX: notify the applicant with the preview link only.
            // OTP must NOT be generated here; it is generated later from the
            // public contract page after the applicant accepts the text.
            $result['sms_result'] = function_exists('mlr_lc_reissue_send_preview_link')
                ? mlr_lc_reissue_send_preview_link($request_id)
                : new WP_Error('mlr_lc_reissue_sms_unavailable', 'سرویس ارسال لینک پیش‌نمایش قرارداد در دسترس نیست. قرارداد جدید ذخیره شده و می‌توانید بعداً لینک را ارسال کنید.');
        }
        return $result;
    }
}

if (!function_exists('mlr_mark_contract_reissue_signed')) {
    function mlr_mark_contract_reissue_signed($request_id, array $proof) {
        $request_id = absint($request_id);
        $active = mlr_get_active_loan_contract_reissue($request_id);
        if (!is_array($active) || (($active['state'] ?? '') !== 'pending')) return;

        $active['state'] = 'signed';
        $active['signed_at'] = sanitize_text_field((string) ($proof['signed_at'] ?? current_time('mysql')));
        $active['contract_digest'] = sanitize_text_field((string) ($proof['contract_digest'] ?? ''));
        $active['signed_revision'] = max(1, absint($proof['contract_revision'] ?? $active['revision'] ?? 1));
        $active['updated_at'] = current_time('mysql');
        mlr_lc_store_nonautoload_option(mlr_lc_reissue_option_name($request_id), $active);
    }
}

if (!function_exists('mlr_lc_reissue_send_preview_link')) {
    function mlr_lc_reissue_send_preview_link($request_id) {
        $request_id = absint($request_id);
        if (!mlr_contract_reissue_is_pending($request_id)) {
            return new WP_Error('mlr_lc_reissue_not_pending', 'برای این پرونده نسخه بازصدورِ در انتظار امضا وجود ندارد.');
        }
        if (function_exists('mlr_is_loan_contract_signed') && mlr_is_loan_contract_signed($request_id)) {
            return new WP_Error('mlr_lc_reissue_already_signed', 'نسخه فعال این قرارداد قبلاً امضا شده است.');
        }
        $request = mlr_lc_reissue_get_request($request_id);
        if (!$request) return new WP_Error('mlr_lc_reissue_request_missing', 'پرونده یافت نشد.');
        if (!function_exists('mlr_send_loan_contract_link_for_request')) {
            return new WP_Error('mlr_lc_reissue_sms_unavailable', 'سرویس ارسال لینک پیش‌نمایش قرارداد در دسترس نیست.');
        }

        $sent = mlr_send_loan_contract_link_for_request($request);
        if (is_wp_error($sent)) return $sent;

        // Keep a lightweight audit trail on the active revision. This does not
        // affect the signed proof; it only records preview delivery activity.
        $active = mlr_get_active_loan_contract_reissue($request_id);
        if (is_array($active) && (($active['state'] ?? '') === 'pending')) {
            $active['preview_link_sent_at'] = current_time('mysql');
            $active['preview_link_sent_by'] = get_current_user_id();
            $active['preview_link_send_count'] = max(0, absint($active['preview_link_send_count'] ?? 0)) + 1;
            $active['updated_at'] = current_time('mysql');
            mlr_lc_store_nonautoload_option(mlr_lc_reissue_option_name($request_id), $active);
        }

        if (function_exists('mlr_add_request_event')) {
            mlr_add_request_event($request_id, 'loan_contract_reissue_preview_link_sent', [
                'شرح' => 'لینک پیش‌نمایش نسخه اصلاح‌شده قرارداد برای متقاضی ارسال شد؛ OTP در این مرحله صادر نشد.',
                'نسخه' => (string) max(1, absint($sent['contract_revision'] ?? ($active['revision'] ?? 1))),
                'کاربر مدیر' => (string) get_current_user_id(),
            ]);
        }
        return $sent;
    }
}

// Backward-compatible internal name used by the admin action in 8.4.6.77.
if (!function_exists('mlr_lc_reissue_send_pending')) {
    function mlr_lc_reissue_send_pending($request_id) {
        return mlr_lc_reissue_send_preview_link($request_id);
    }
}

if (!function_exists('mlr_lc_reissue_supported_placeholders')) {
    function mlr_lc_reissue_supported_placeholders() {
        return [
            '[request_id]','[contract_number]','[contract_date]','[contract_start_date]','[contract_end_date]','[contract_duration_months]',
            '[full_name]','[father_name]','[national_code]','[certificate_number]','[certificate_city]','[birth_date]',
            '[mobile_number]','[phone_number]','[residential_address]','[postal_code]','[work_address]','[occupation]',
            '[plan_name]','[product_name]','[invoice_number]','[invoice_date]','[delivery_date]',
            '[loan_amount]','[facility_amount]','[cash_price]','[cash_price_words]','[installment_sale_price]','[installment_sale_price_words]',
            '[installment_count]','[installment_term_months]','[payment_count]','[installment_interval]','[installment_amount]','[total_payment]',
            '[first_due_date]','[prepayment]','[annual_interest_rate]','[retirement_code]','[retirement_organization]',
            '[first_installment_amount]','[first_installment_amount_words]','[remaining_installment_amount]','[remaining_installment_amount_words]',
            '[marketing_development_amount]','[marketing_development_amount_words]',
            '[guarantee_check_principal]','[guarantee_check_total]','[total_price]','[total_price_words]','[fintech_logo_url]'
        ];
    }
}

/** 8.4.6.88: Keep the physical correction for one request separate from the plan template. */
if (!function_exists('mlr_lc_physical_draft_option_name')) {
    function mlr_lc_physical_draft_option_name($request_id) {
        return 'mlr_lc_physical_draft_' . absint($request_id);
    }
}

if (!function_exists('mlr_lc_get_physical_draft')) {
    function mlr_lc_get_physical_draft($request_id) {
        $draft = get_option(mlr_lc_physical_draft_option_name($request_id), null);
        return is_array($draft) && !empty($draft['contract_text']) ? $draft : null;
    }
}

if (!function_exists('mlr_lc_convert_digital_to_physical')) {
    /**
     * Change signing method ONLY for this request after first archiving the signed
     * digital evidence. The physical replacement is an unsigned draft: the operator
     * must later obtain a fresh wet signature outside this tool.
     */
    function mlr_lc_convert_digital_to_physical($request_id, $raw_text, $reason) {
        if (!current_user_can('manage_options')) {
            return new WP_Error('mlr_lc_no_permission', 'دسترسی لازم برای تغییر نوع قرارداد وجود ندارد.');
        }
        $request_id = absint($request_id);
        $request = mlr_lc_reissue_get_request($request_id);
        if (!$request) return new WP_Error('mlr_lc_missing_request', 'پرونده موردنظر یافت نشد.');
        if (!function_exists('mlr_get_loan_contract_mode_for_request') || mlr_get_loan_contract_mode_for_request($request, true) !== 'digital') {
            return new WP_Error('mlr_lc_already_physical', 'فقط پرونده‌ای که اکنون قرارداد دیجیتال دارد قابل تبدیل است.');
        }
        if (trim((string) ($request->contract_file ?? '')) !== '') {
            return new WP_Error('mlr_lc_physical_file_exists', 'این پرونده از قبل فایل قرارداد دارد. برای جلوگیری از تداخل سوابق، تبدیل خودکار متوقف شد.');
        }
        if (mlr_contract_reissue_is_pending($request_id)) {
            return new WP_Error('mlr_lc_pending_digital', 'یک بازصدور دیجیتال در انتظار امضا موجود است. برای جلوگیری از از بین رفتن اصلاحیه قبلی، ابتدا آن را تعیین تکلیف کنید.');
        }
        $reason = sanitize_textarea_field((string) $reason);
        if ((function_exists('mb_strlen') ? mb_strlen(trim($reason)) : strlen(trim($reason))) < 3) return new WP_Error('mlr_lc_reason_required', 'علت اصلاح و تبدیل را بنویسید (حداقل ۳ حرف).');
        $raw_text = str_replace("\0", '', wp_check_invalid_utf8((string) $raw_text, true));
        if (trim(wp_strip_all_tags($raw_text)) === '') {
            return new WP_Error('mlr_lc_physical_text_required', 'متن نسخه اصلاح‌شده نباید خالی باشد.');
        }
        // Preview and validate before any persistent change.
        $payload = mlr_lc_physical_build($request, $raw_text, true);
        if (is_wp_error($payload)) return $payload;
        if (!empty($payload['mlr_unresolved_tags'])) {
            return new WP_Error('mlr_lc_unknown_tags', 'متغیرهای ناشناخته در متن قرارداد: ' . implode('، ', $payload['mlr_unresolved_tags']));
        }
        $old = function_exists('mlr_get_loan_contract_signature') ? mlr_get_loan_contract_signature($request_id) : null;
        $counter_name = mlr_lc_reissue_counter_option_name($request_id);
        $current_counter = absint(get_option($counter_name, 0));
        $previous_revision = is_array($old) ? max(1, absint($old['contract_revision'] ?? 1), $current_counter) : $current_counter;
        $revision = max(2, $previous_revision + 1);
        $old_reissue = get_option(mlr_lc_reissue_option_name($request_id), null);
        // A stale draft from an earlier attempt must never be overwritten blindly.
        if (mlr_lc_get_physical_draft($request_id)) {
            return new WP_Error('mlr_lc_physical_draft_exists', 'پیش‌نویس تبدیل فیزیکی از قبل موجود است؛ برای حفظ سابقه بازنویسی انجام نشد.');
        }
        if (is_array($old)) {
            $archived = mlr_lc_reissue_archive_proof($request_id, max(1,$previous_revision), $old, $reason, $revision);
            if (is_wp_error($archived)) return $archived;
            $stored_archive = get_option(mlr_lc_reissue_archive_option_name($request_id, max(1,$previous_revision)), null);
            if (!is_array($stored_archive) || !is_array($stored_archive['proof'] ?? null) ||
                !hash_equals((string) ($old['contract_digest'] ?? ''), (string) ($stored_archive['proof']['contract_digest'] ?? ''))) {
                return new WP_Error('mlr_lc_archive_unverified', 'صحت نسخه بایگانی‌شده تأیید نشد؛ قرارداد فعلی تغییر نکرد.');
            }
        }
        $draft = [
            'request_id' => $request_id, 'revision' => $revision,
            'previous_revision' => $previous_revision,
            'contract_text' => $raw_text,
            'reason' => $reason, 'state' => 'awaiting_physical_signature',
            'created_at' => current_time('mysql'), 'created_by' => get_current_user_id(),
            'old_contract_mode' => 'digital', 'old_signed' => is_array($old),
            'prior_reissue' => is_array($old_reissue) ? $old_reissue : null,
        ];
        $draft_name = mlr_lc_physical_draft_option_name($request_id);
        if (!add_option($draft_name, $draft, '', false)) {
            return new WP_Error('mlr_lc_draft_write_failed', 'ذخیره اصلاحیه فیزیکی انجام نشد؛ قرارداد فعلی تغییر نکرد.');
        }
        $mode_name = mlr_loan_contract_mode_option_name($request_id);
        $mode_written = mlr_lc_store_nonautoload_option($mode_name, 'physical');
        if (!$mode_written && get_option($mode_name, null) !== 'physical') {
            delete_option($draft_name);
            return new WP_Error('mlr_lc_mode_write_failed', 'تغییر نوع قرارداد ذخیره نشد؛ نسخه دیجیتال فعلی بدون تغییر باقی ماند.');
        }
        // Important: changing the mode alone is insufficient. The old OTP proof
        // must stop counting as the signature of the new physical document.
        if (is_array($old)) {
            delete_option(mlr_loan_contract_signature_option_name($request_id));
            if (is_array(mlr_get_loan_contract_signature($request_id))) {
                mlr_lc_store_nonautoload_option($mode_name, 'digital');
                delete_option($draft_name);
                return new WP_Error('mlr_lc_signature_deactivation_failed', 'خارج کردن امضای قدیمی از نسخه جاری موفق نبود. نوع قرارداد دیجیتال باقی ماند.');
            }
        }
        if (is_array($old_reissue)) {
            $old_reissue['state'] = 'superseded_by_physical';
            $old_reissue['physical_revision'] = $revision;
            $old_reissue['updated_at'] = current_time('mysql');
            mlr_lc_store_nonautoload_option(mlr_lc_reissue_option_name($request_id), $old_reissue);
        }
        mlr_lc_store_nonautoload_option($counter_name, $revision);
        if (function_exists('mlr_loan_contract_otp_transient_key')) delete_transient(mlr_loan_contract_otp_transient_key($request_id));
        if (function_exists('mlr_loan_contract_otp_lock_name')) delete_option(mlr_loan_contract_otp_lock_name($request_id));
        if (function_exists('mlr_add_request_event')) {
            mlr_add_request_event($request_id, 'loan_contract_converted_to_physical', [
                'شرح' => 'صدور اصلاحیه فیزیکی؛ قرارداد قبلی در صورت امضاشده بودن بایگانی شده است. نسخه فیزیکی جدید هنوز امضا نشده است.',
                'نسخه جدید' => (string) $revision,
                'علت' => $reason,
                'مدیر' => (string) get_current_user_id(),
            ]);
        }
        return ['request_id'=>$request_id, 'revision'=>$revision, 'archived_signed'=>is_array($old)];
    }
}

/**
 * Physical contract generator (8.4.6.87).
 * PREVIEW/EXPORT ONLY: never writes a signature, changes request status, or
 * modifies the contract template configured in the plan.
 */
if (!function_exists('mlr_lc_is_physical_request')) {
    function mlr_lc_is_physical_request($request) {
        return $request && function_exists('mlr_get_loan_contract_mode_for_request')
            && mlr_get_loan_contract_mode_for_request($request, false) === 'physical';
    }
}

if (!function_exists('mlr_lc_physical_build')) {
    function mlr_lc_physical_build($request, $raw_contract_text, $allow_digital = false) {
        if (!$request || (!$allow_digital && !mlr_lc_is_physical_request($request))) {
            return new WP_Error('mlr_physical_wrong_mode', 'این قابلیت فقط برای قراردادهای فیزیکی است.');
        }
        $raw_contract_text = str_replace("\0", '', wp_check_invalid_utf8((string) $raw_contract_text, true));
        // Keep valid dynamic attributes intact until the canonical payload sanitizes the rendered HTML.
        if (trim(wp_strip_all_tags($raw_contract_text)) === '') {
            return new WP_Error('mlr_physical_empty', 'متن قرارداد خالی است. ابتدا متن قرارداد را وارد کنید.');
        }
        if (!function_exists('mlr_get_loan_contract_payload')) {
            return new WP_Error('mlr_physical_unavailable', 'موتور جایگذاری متغیرهای قرارداد در دسترس نیست.');
        }
        $id = absint($request->id ?? 0);
        if (!$id) return new WP_Error('mlr_physical_invalid', 'شناسه پرونده نامعتبر است.');
        $had_preview_global = array_key_exists('mlr_lc_reissue_preview', $GLOBALS);
        $previous = $GLOBALS['mlr_lc_reissue_preview'][$id] ?? null;
        $had_previous = isset($GLOBALS['mlr_lc_reissue_preview'][$id]);
        // The canonical builder uses this temporary in-memory override to resolve
        // every existing template variable with values from THIS loan request.
        $GLOBALS['mlr_lc_reissue_preview'][$id] = [
            'request_id' => $id,
            'revision' => 1,
            'state' => 'pending',
            'contract_text' => $raw_contract_text,
        ];
        try {
            $payload = mlr_get_loan_contract_payload($request, current_time('mysql'), false);
        } finally {
            if ($had_previous) $GLOBALS['mlr_lc_reissue_preview'][$id] = $previous;
            else unset($GLOBALS['mlr_lc_reissue_preview'][$id]);
            if (!$had_preview_global) unset($GLOBALS['mlr_lc_reissue_preview']);
        }
        if (is_wp_error($payload)) return $payload;
        if (!is_array($payload) || trim(wp_strip_all_tags((string) ($payload['contract_html'] ?? ''))) === '') {
            return new WP_Error('mlr_physical_render_empty', 'خروجی قرارداد قابل تولید نیست؛ متن طرح و اطلاعات پرونده را بررسی کنید.');
        }
        // Highlight unknown/misspelled dynamic tags; they should never silently
        // turn into apparently valid legal text.
        $unresolved = [];
        preg_match_all('/\[[a-z][a-z0-9_]*\]/i', (string) ($payload['contract_html'] ?? ''), $hits);
        if (!empty($hits[0])) $unresolved = array_values(array_unique($hits[0]));
        $payload['mlr_unresolved_tags'] = $unresolved;
        return $payload;
    }
}

if (!function_exists('mlr_lc_physical_print_markup')) {
    function mlr_lc_physical_print_markup(array $payload) {
        $header = esc_url((string) ($payload['letterhead_url'] ?? ''));
        $footer = esc_url((string) ($payload['footer_url'] ?? ''));
        $repeat = (string) ($payload['repeat_footer_html'] ?? '');
        ob_start(); ?>
        <table class="mlr-print-layout-table" dir="rtl">
            <thead><tr><td>
                <?php if ($header): ?><div class="mlr-print-header mlr-contract-header"><img class="mlr-contract-media-image" alt="سربرگ قرارداد" src="<?php echo esc_url($header); ?>"></div><?php endif; ?>
            </td></tr></thead>
            <tbody><tr><td><div class="mlr-contract-body" dir="rtl"><?php echo wp_kses_post(mlr_normalize_contract_markup_for_display((string) ($payload['contract_html'] ?? ''))); ?></div></td></tr></tbody>
            <tfoot><tr><td><div class="mlr-print-footer">
                <?php if ($repeat !== '') echo wp_kses_post($repeat); ?>
                <?php if ($footer): ?><div class="mlr-contract-footer"><img class="mlr-contract-media-image" alt="پابرگ قرارداد" src="<?php echo esc_url($footer); ?>"></div><?php endif; ?>
            </div></td></tr></tfoot>
        </table>
        <?php return trim((string) ob_get_clean());
    }
}

/** Embed on-site media inside the exported HTML for portability/offline printing. */
if (!function_exists('mlr_lc_physical_embed_images')) {
    function mlr_lc_physical_embed_images($html) {
        $uploads = wp_upload_dir();
        $plugin_root_file = dirname(__DIR__) . '/my-loan-request-plugin.php';
        $sources = [];
        if (empty($uploads['error']) && !empty($uploads['baseurl']) && !empty($uploads['basedir'])) {
            $sources[] = [trailingslashit($uploads['baseurl']), trailingslashit($uploads['basedir'])];
        }
        $sources[] = [plugin_dir_url($plugin_root_file), trailingslashit(dirname(__DIR__))];
        return preg_replace_callback('~(<img\b[^>]*?\bsrc\s*=\s*)(["\'])(.*?)\2~is', static function($m) use ($sources) {
            $url = html_entity_decode((string) $m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (str_starts_with($url, 'data:image/')) return $m[0];
            foreach ($sources as [$base_url, $base_dir]) {
                if (!str_starts_with($url, $base_url)) continue;
                $rel = rawurldecode(strtok(substr($url, strlen($base_url)), '?#'));
                $real_base = realpath($base_dir);
                $real = realpath($base_dir . $rel);
                if (!$real || !$real_base || !str_starts_with($real, $real_base . DIRECTORY_SEPARATOR) || !is_file($real)) continue;
                $size = filesize($real);
                if (!$size || $size > 4 * 1024 * 1024) continue;
                $extension = strtolower(pathinfo($real, PATHINFO_EXTENSION));
                $mime = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','webp'=>'image/webp'][$extension] ?? '';
                if (!$mime) continue;
                $bytes = file_get_contents($real);
                if ($bytes === false) continue;
                return $m[1] . $m[2] . 'data:' . $mime . ';base64,' . base64_encode($bytes) . $m[2];
            }
            // External images remain linked. Saving a PDF from the online admin
            // is recommended if a fully portable document is needed.
            return $m[0];
        }, (string) $html);
    }
}

if (!function_exists('mlr_lc_physical_download_html')) {
    function mlr_lc_physical_download_html() {
        if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز.', '', ['response' => 403]);
        check_admin_referer('mlr_lc_physical_download', 'mlr_lc_physical_nonce');
        $id = absint($_POST['request_id'] ?? 0);
        $request = mlr_lc_reissue_get_request($id);
        $html = isset($_POST['contract_text']) ? (string) wp_unslash($_POST['contract_text']) : '';
        $payload = mlr_lc_physical_build($request, $html);
        if (is_wp_error($payload)) wp_die(esc_html($payload->get_error_message()), 'خطای تهیه قرارداد', ['response' => 400]);
        if (!empty($payload['mlr_unresolved_tags'])) {
            wp_die('متغیرهای ناشناخته در متن باقی مانده‌اند: ' . esc_html(implode('، ', $payload['mlr_unresolved_tags'])), 'متغیرهای قرارداد', ['response' => 400]);
        }
        $css_file = dirname(__DIR__) . '/assets/css/contract-print.css';
        $css = is_readable($css_file) ? file_get_contents($css_file) : '';
        if ($css === '') wp_die('استایل چاپ قرارداد در دسترس نیست.', 'خطای تهیه قرارداد', ['response' => 500]);
        $print = mlr_lc_physical_embed_images(mlr_lc_physical_print_markup($payload));
        $file = 'physical-contract-' . $id . '-' . wp_date('Ymd-His') . '.html';
        nocache_headers();
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('X-Content-Type-Options: nosniff');
        // No scripts are included in this exported file. Browser print-to-PDF
        // supports the same table-page header/footer as the in-app print engine.
        echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>قرارداد فیزیکی پرونده ' . esc_html($id) . '</title><style>' . $css . "\n" .
            '@media screen{body{background:#edf1f5!important;}#mlr-isolated-print-root{max-width:210mm!important;margin:24px auto!important;background:#fff!important;box-shadow:0 8px 32px #0002!important;}}' .
            '</style></head><body class="mlr-contract-print-document"><div id="mlr-isolated-print-root">' . $print . '</div></body></html>';
        exit;
    }
}
add_action('admin_post_mlr_lc_physical_download', 'mlr_lc_physical_download_html');

add_action('admin_menu', function() {
    add_submenu_page(
        'mlr-loan-requests',
        'بازصدور قرارداد',
        'بازصدور قرارداد',
        'manage_options',
        'mlr-loan-contract-reissue',
        'mlr_loan_contract_reissue_page'
    );
}, 20);

add_action('admin_enqueue_scripts', function($hook) {
    if (empty($_GET['page']) || sanitize_key((string) $_GET['page']) !== 'mlr-loan-contract-reissue') return;
    // This CSS is designed for a *standalone* print frame and MUST NOT be
    // applied to wp-admin body (it would break the admin page layout).
    wp_enqueue_script('mlr-lc-physical-print', plugin_dir_url(__FILE__) . '../assets/js/mlr-contract-print.js', [], '8.4.6.88', true);
    wp_add_inline_script('mlr-lc-physical-print', 'window.mlrContractPrint=window.mlrContractPrint||{};window.mlrContractPrint.css_url=' . wp_json_encode(plugin_dir_url(__FILE__) . '../assets/css/contract-print.css') . ';', 'before');
});

if (!function_exists('mlr_lc_reissue_admin_notice')) {
    function mlr_lc_reissue_admin_notice($type, $message) {
        printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($type), wp_kses_post($message));
    }
}

if (!function_exists('mlr_lc_reissue_get_default_text')) {
    function mlr_lc_reissue_get_default_text($request) {
        $physical_draft = mlr_lc_get_physical_draft(absint($request->id ?? 0));
        if (mlr_lc_is_physical_request($request) && is_array($physical_draft)) return (string) ($physical_draft['contract_text'] ?? '');
        $active = mlr_get_active_loan_contract_reissue(absint($request->id ?? 0));
        if (is_array($active) && !empty($active['contract_text'])) return (string) $active['contract_text'];
        $plan = mlr_lc_reissue_get_plan(absint($request->installment_plan_id ?? 0));
        if (!$plan) return '';
        $settings = function_exists('mlr_get_plan_settings_array') ? mlr_get_plan_settings_array($plan) : maybe_unserialize($plan->settings);
        return is_array($settings) ? (string) ($settings['plan_contract_text'] ?? '') : '';
    }
}

if (!function_exists('mlr_loan_contract_reissue_page')) {
    function mlr_loan_contract_reissue_page() {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز.');
        }

        $request_id = isset($_REQUEST['request_id']) ? absint($_REQUEST['request_id']) : 0;
        $preview_html = '';
        $editor_text = '';
        $reason_value = '';
        $physical_preview_requested = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mlr_lc_reissue_action'])) {
            check_admin_referer('mlr_lc_reissue_action', 'mlr_lc_reissue_nonce');
            $action = sanitize_key((string) wp_unslash($_POST['mlr_lc_reissue_action']));
            $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
            $editor_text = isset($_POST['contract_text']) ? (string) wp_unslash($_POST['contract_text']) : '';
            $reason_value = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '';

            if ($action === 'physical_from_digital_preview' || $action === 'physical_from_digital_save') {
                $request = mlr_lc_reissue_get_request($request_id);
                if (!$request || mlr_lc_is_physical_request($request)) {
                    mlr_lc_reissue_admin_notice('error', 'پرونده یافت نشد یا از قبل فیزیکی است.');
                } elseif ($action === 'physical_from_digital_preview') {
                    $physical_preview_requested = true;
                    $payload = mlr_lc_physical_build($request, $editor_text, true);
                    if (is_wp_error($payload)) {
                        mlr_lc_reissue_admin_notice('error', esc_html($payload->get_error_message()));
                    } else {
                        $preview_html = mlr_lc_physical_print_markup($payload);
                        if (!empty($payload['mlr_unresolved_tags'])) {
                            mlr_lc_reissue_admin_notice('warning', 'متغیرهای ناشناخته: ' . esc_html(implode('، ', $payload['mlr_unresolved_tags'])));
                        } else {
                            mlr_lc_reissue_admin_notice('info', 'این پیش‌نمایشِ نسخه فیزیکی است؛ هنوز قرارداد دیجیتال پرونده تبدیل نشده است.');
                        }
                    }
                } elseif (empty($_POST['confirm_physical_conversion'])) {
                    mlr_lc_reissue_admin_notice('error', 'برای تغییر حالت قرارداد باید تأیید صریح تبدیل و بایگانی نسخه قدیمی را فعال کنید.');
                } else {
                    $result = mlr_lc_convert_digital_to_physical($request_id, $editor_text, $reason_value);
                    if (is_wp_error($result)) {
                        mlr_lc_reissue_admin_notice('error', esc_html($result->get_error_message()));
                    } else {
                        mlr_lc_reissue_admin_notice('success', 'پرونده #' . absint($request_id) . ' به قرارداد فیزیکی تغییر یافت. متن اصلاح‌شده برای این پرونده ذخیره شد؛ نسخه قبلی در صورت امضاشده بودن بایگانی شده و نسخه فیزیکی جدید هنوز امضا نشده است. هیچ OTP یا پیامکی ارسال نشد. اکنون پیش‌نمایش و فایل قرارداد را از همین صفحه تهیه کنید.');
                    }
                }
            } elseif ($action === 'physical_preview') {
                $request = mlr_lc_reissue_get_request($request_id);
                $payload = mlr_lc_physical_build($request, $editor_text);
                if (is_wp_error($payload)) {
                    mlr_lc_reissue_admin_notice('error', esc_html($payload->get_error_message()));
                } else {
                    $preview_html = mlr_lc_physical_print_markup($payload);
                    if (!empty($payload['mlr_unresolved_tags'])) {
                        mlr_lc_reissue_admin_notice('warning', 'متغیرهای ناشناخته: ' . esc_html(implode('، ', $payload['mlr_unresolved_tags'])) . '. قبل از دریافت فایل، متن را اصلاح کنید.');
                    } else {
                        mlr_lc_reissue_admin_notice('success', 'نسخه فیزیکی با اطلاعات این پرونده تولید شد. فقط پیش‌نمایش است و هیچ تغییری در پرونده یا امضا ثبت نشده است.');
                    }
                }
            } elseif ($action === 'preview') {
                $request = mlr_lc_reissue_get_request($request_id);
                if (!$request) {
                    mlr_lc_reissue_admin_notice('error', 'پرونده یافت نشد.');
                } elseif (trim(wp_strip_all_tags($editor_text)) === '') {
                    mlr_lc_reissue_admin_notice('error', 'متن قرارداد برای پیش‌نمایش خالی است.');
                } else {
                    $active = mlr_get_active_loan_contract_reissue($request_id);
                    $counter = max(0, absint(get_option(mlr_lc_reissue_counter_option_name($request_id), 0)));
                    $preview_revision = is_array($active) && (($active['state'] ?? '') === 'pending')
                        ? max(2, absint($active['revision'] ?? 2))
                        : max(2, ($counter > 0 ? $counter + 1 : 2));
                    $GLOBALS['mlr_lc_reissue_preview'][$request_id] = [
                        'request_id' => $request_id,
                        'revision' => $preview_revision,
                        'state' => 'pending',
                        'contract_text' => wp_check_invalid_utf8($editor_text, true),
                    ];
                    $payload = function_exists('mlr_get_loan_contract_payload') ? mlr_get_loan_contract_payload($request, current_time('mysql'), false) : null;
                    unset($GLOBALS['mlr_lc_reissue_preview'][$request_id]);
                    if (is_wp_error($payload)) {
                        mlr_lc_reissue_admin_notice('error', esc_html($payload->get_error_message()));
                    } elseif (is_array($payload)) {
                        $preview_html = (string) ($payload['contract_html'] ?? '');
                        mlr_lc_reissue_admin_notice('info', 'این فقط پیش‌نمایش است و هنوز هیچ نسخه‌ای جایگزین نشده است.');
                    }
                }
            } elseif ($action === 'save') {
                if (empty($_POST['confirm_reissue'])) {
                    mlr_lc_reissue_admin_notice('error', 'برای بازصدور، تأیید بایگانی نسخه قبلی و الزام امضای مجدد را فعال کنید.');
                } else {
                    $send_now = !empty($_POST['send_now']);
                    $result = mlr_start_loan_contract_reissue($request_id, $editor_text, $reason_value, $send_now);
                    if (is_wp_error($result)) {
                        mlr_lc_reissue_admin_notice('error', esc_html($result->get_error_message()));
                    } else {
                        $message = 'نسخه <strong>' . absint($result['revision']) . '</strong> برای پرونده <strong>#' . absint($request_id) . '</strong> فعال شد. نسخه امضاشده قبلی بدون تغییر در تاریخچه بایگانی شد و نسخه جدید تا تأیید و امضای OTP توسط متقاضی، امضانشده است.';
                        $sms = $result['sms_result'] ?? null;
                        if (is_wp_error($sms)) {
                            $message .= '<br><span style="color:#b32d2e">ارسال پیامک انجام نشد: ' . esc_html($sms->get_error_message()) . '</span>';
                        } elseif (is_array($sms) && !empty($sms['message'])) {
                            $message .= '<br><span style="color:#087a35">' . esc_html($sms['message']) . '</span>';
                        }
                        mlr_lc_reissue_admin_notice('success', $message);
                        $active = mlr_get_active_loan_contract_reissue($request_id);
                        $editor_text = is_array($active) ? (string) ($active['contract_text'] ?? '') : $editor_text;
                    }
                }
            } elseif ($action === 'send') {
                $sent = mlr_lc_is_physical_request(mlr_lc_reissue_get_request($request_id))
                    ? new WP_Error('mlr_physical_no_sms', 'برای قرارداد فیزیکی ارسال کد و لینک امضای دیجیتال فعال نیست.')
                    : mlr_lc_reissue_send_pending($request_id);
                if (is_wp_error($sent)) {
                    mlr_lc_reissue_admin_notice('error', esc_html($sent->get_error_message()));
                } else {
                    mlr_lc_reissue_admin_notice('success', esc_html((string) ($sent['message'] ?? 'لینک پیش‌نمایش قرارداد ارسال شد؛ کد تأیید هنوز صادر نشده است.')));
                }
            }
        }

        $view_revision = isset($_GET['view_revision']) ? absint($_GET['view_revision']) : 0;
        if ($request_id > 0 && $view_revision > 0) {
            $archive = get_option(mlr_lc_reissue_archive_option_name($request_id, $view_revision), null);
            echo '<div class="wrap" dir="rtl"><h1>نسخه بایگانی‌شده قرارداد</h1>';
            echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=mlr-loan-contract-reissue&request_id=' . $request_id)) . '">بازگشت</a></p>';
            if (!is_array($archive) || empty($archive['proof'])) {
                echo '<div class="notice notice-error"><p>نسخه بایگانی یافت نشد.</p></div></div>';
                return;
            }
            $proof = $archive['proof'];
            echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px;max-width:1100px">';
            echo '<p><strong>پرونده:</strong> #' . esc_html($request_id) . ' &nbsp; <strong>نسخه:</strong> ' . esc_html($view_revision) . ' &nbsp; <strong>زمان امضا:</strong> ' . esc_html((string) ($proof['signed_at'] ?? '—')) . '</p>';
            echo '<p><strong>علت جایگزینی:</strong> ' . esc_html((string) ($archive['reason'] ?? '—')) . '</p><hr>';
            echo wp_kses_post(mlr_normalize_contract_markup_for_display((string) ($proof['contract_html'] ?? '')));
            if (function_exists('mlr_render_loan_contract_signature_certificate')) {
                echo wp_kses_post(mlr_render_loan_contract_signature_certificate($proof));
            }
            echo '</div></div>';
            return;
        }

        $request = $request_id > 0 ? mlr_lc_reissue_get_request($request_id) : null;
        $is_physical = mlr_lc_is_physical_request($request);
        if ($request && $editor_text === '') {
            $editor_text = mlr_lc_reissue_get_default_text($request);
        }
        $plan = $request ? mlr_lc_reissue_get_plan(absint($request->installment_plan_id ?? 0)) : null;
        $proof = $request ? (function_exists('mlr_get_loan_contract_signature') ? mlr_get_loan_contract_signature($request_id) : null) : null;
        $active = $request ? mlr_get_active_loan_contract_reissue($request_id) : null;
        $index = $request ? get_option(mlr_lc_reissue_index_option_name($request_id), []) : [];
        if (!is_array($index)) $index = [];
        ?>
        <div class="wrap" dir="rtl">
            <h1>مدیریت بازصدور قرارداد پرونده</h1>
            <?php if (!$is_physical): ?>
            <div style="max-width:1180px;background:#fff8e5;border-right:4px solid #dba617;padding:14px 16px;margin:14px 0;border-radius:8px;line-height:2">
                این ابزار <strong>نسخه امضاشده قبلی را بازنویسی نمی‌کند</strong>. قبل از فعال شدن متن جدید، نسخه قبلی با هش، زمان امضا و متن کامل در تاریخچه بایگانی می‌شود. سپس نسخه جدید فقط برای همان پرونده فعال و امضای قبلی از حالت «نسخه جاری» خارج می‌شود تا متقاضی دوباره با OTP امضا کند.
            </div>

            <?php else: ?>
            <div style="max-width:1180px;background:#e9f7ef;border-right:4px solid #16804a;padding:14px 16px;margin:14px 0;border-radius:8px;line-height:2"><strong>قرارداد فیزیکی شناسایی شد.</strong> متن قرارداد همین پرونده/طرح با اطلاعات آن تکمیل می‌شود. این ابزار فقط پیش‌نمایش و خروجی تهیه می‌کند؛ هیچ پیامک یا OTP ارسال نمی‌شود. اگر این پرونده از دیجیتال به فیزیکی تبدیل شده باشد، اصلاحیه آن قبلاً در خود پرونده ذخیره شده است.</div>
            <?php endif; ?>
            <form method="get" style="display:flex;gap:10px;align-items:end;background:#fff;padding:14px;border:1px solid #dcdcde;border-radius:8px;max-width:760px">
                <input type="hidden" name="page" value="mlr-loan-contract-reissue">
                <div style="flex:1"><label for="request_id_search"><strong>شناسه پرونده</strong></label><input id="request_id_search" name="request_id" type="number" min="1" class="regular-text" value="<?php echo esc_attr($request_id ?: ''); ?>" style="width:100%;margin-top:6px"></div>
                <button class="button button-primary">بارگذاری پرونده</button>
            </form>

            <?php if ($request): ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:10px;max-width:1180px;margin-top:16px">
                    <?php
                    $cards = [
                        'شناسه' => '#' . $request_id,
                        'متقاضی' => (string) ($request->full_name ?? '—'),
                        'کد ملی' => (string) ($request->national_code ?? '—'),
                        'موبایل' => (string) ($request->mobile_number ?? '—'),
                        'طرح' => $plan ? (string) $plan->name : '—',
                        'وضعیت پرونده' => (string) ($request->status ?? '—'),
                        'روش قرارداد پرونده' => $is_physical ? 'فیزیکی' : 'دیجیتال',
                        'نسخه جاری' => ($is_physical && mlr_lc_get_physical_draft($request_id)) ? ('اصلاحیه فیزیکی نسخه ' . absint(mlr_lc_get_physical_draft($request_id)['revision'] ?? 1) . ' - در انتظار امضای دستی') : (is_array($active) ? ('نسخه ' . absint($active['revision'] ?? 1) . ' - ' . (($active['state'] ?? '') === 'pending' ? 'در انتظار امضا' : 'امضا شده')) : (is_array($proof) ? 'نسخه 1 - امضا شده' : 'بدون امضای دیجیتال')),
                    ];
                    foreach ($cards as $label => $value) {
                        echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px"><div style="color:#646970;font-size:12px">' . esc_html($label) . '</div><strong style="display:block;margin-top:4px">' . esc_html($value) . '</strong></div>';
                    }
                    ?>
                </div>

                <?php if (is_array($active) && (($active['state'] ?? '') === 'pending')): ?>
                    <div class="notice notice-warning inline" style="max-width:1140px;margin:16px 0 0"><p><strong>نسخه <?php echo esc_html(absint($active['revision'] ?? 0)); ?> در انتظار امضاست.</strong> تا زمان امضای جدید، قرارداد جاری این پرونده امضانشده محسوب می‌شود.</p><p>در بازصدور، ابتدا فقط لینک پیش‌نمایش برای متقاضی ارسال می‌شود. OTP زمانی صادر می‌شود که متقاضی لینک را باز کند، متن قرارداد را بپذیرد و خودش «ارسال کد تأیید» را انتخاب کند.</p></div>
                <?php endif; ?>

                <?php if ($is_physical): ?>
                <div style="max-width:1180px;margin-top:16px;background:white;border:1px solid #dcdcde;border-radius:12px;padding:20px;line-height:1.9">
                    <h2 style="margin:0 0 8px">تهیه نسخه جدید قرارداد فیزیکی</h2>
                    <p>متن زیر از <strong>قرارداد همین پرونده یا طرح</strong> بارگذاری شده است. می‌توانید متن و متغیرها را در همین ادیتور برای این پرونده اصلاح کنید. خروجی با اطلاعات واقعی پرونده جایگذاری می‌شود. <strong>دکمه‌های پیش‌نمایش و دریافت فایل چیزی را در دیتابیس تغییر نمی‌دهند.</strong></p>
                    <form method="post" id="mlr-physical-contract-form">
                        <?php wp_nonce_field('mlr_lc_reissue_action', 'mlr_lc_reissue_nonce'); ?>
                        <input type="hidden" name="request_id" value="<?php echo esc_attr($request_id); ?>">
                        <textarea name="contract_text" rows="26" spellcheck="false" style="width:100%;direction:rtl;text-align:right;font-family:Consolas,Monaco,monospace;font-size:13px;line-height:1.9;white-space:pre-wrap"><?php echo esc_textarea($editor_text); ?></textarea>
                        <details style="border:1px solid #dcdcde;border-radius:8px;padding:10px;margin:12px 0">
                            <summary style="cursor:pointer;font-weight:600">متغیرهای داینامیک قابل استفاده</summary>
                            <div dir="ltr" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px">
                            <?php foreach (mlr_lc_reissue_supported_placeholders() as $ph): ?><code><?php echo esc_html($ph); ?></code><?php endforeach; ?>
                            </div>
                        </details>
                        <p><button type="submit" class="button button-primary button-large" name="mlr_lc_reissue_action" value="physical_preview">ساخت پیش‌نمایش با اطلاعات پرونده</button></p>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="mlr-physical-download-form">
                        <input type="hidden" name="action" value="mlr_lc_physical_download">
                        <input type="hidden" name="request_id" value="<?php echo esc_attr($request_id); ?>">
                        <?php wp_nonce_field('mlr_lc_physical_download', 'mlr_lc_physical_nonce'); ?>
                        <textarea name="contract_text" id="mlr-physical-export-contents" hidden></textarea>
                        <button type="submit" class="button button-secondary button-large">دریافت فایل HTML تکمیل‌شده قرارداد</button>
                    </form>
                    <p style="color:#646970;margin-bottom:0">برای دریافت PDF، ابتدا «ساخت پیش‌نمایش» را بزنید و سپس «چاپ / ذخیره PDF» را انتخاب کنید. HTML دریافت‌شده نیز قابل بازکردن در مرورگر و چاپ است.</p>
                    <script>
                    (function(){
                        var form = document.getElementById('mlr-physical-download-form');
                        if (!form) return;
                        form.addEventListener('submit', function(){
                            var editor = document.querySelector('#mlr-physical-contract-form textarea[name="contract_text"]');
                            var field = document.getElementById('mlr-physical-export-contents');
                            if (editor && field) field.value = editor.value;
                        });
                    })();
                    </script>
                </div>
                <?php else: ?>
                <section style="max-width:1180px;margin-top:16px;background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px;line-height:1.9">
                    <h2 style="margin:0 0 8px">اصلاح قرارداد و تبدیل همین پرونده از دیجیتال به فیزیکی</h2>
                    <p>در این عملیات، <strong>فقط همین پرونده</strong> به روش فیزیکی تغییر می‌کند. متن اصلاح‌شده با متغیرهای پرونده ذخیره می‌شود و نسخه آماده چاپ تحویل شما خواهد شد. نسخه دیجیتال قبلاً امضاشده در تاریخچه با مدارک امضای آن باقی می‌ماند؛ امضای سابق به اصلاحیه جدید منتقل نمی‌شود.</p>
                    <form method="post">
                        <?php wp_nonce_field('mlr_lc_reissue_action', 'mlr_lc_reissue_nonce'); ?>
                        <input type="hidden" name="request_id" value="<?php echo esc_attr($request_id); ?>">
                        <textarea name="contract_text" rows="16" spellcheck="false" style="width:100%;direction:rtl;text-align:right;font-family:Consolas,Monaco,monospace;font-size:13px;line-height:1.9"><?php echo esc_textarea($editor_text); ?></textarea>
                        <p><label><strong>علت اصلاح و تغییر روش قرارداد</strong><textarea name="reason" rows="2" style="width:100%;margin-top:5px" placeholder="علت اصلاح قرارداد و درخواست امضای فیزیکی"><?php echo esc_textarea($reason_value); ?></textarea></label></p>
                        <p><button type="submit" class="button button-secondary" name="mlr_lc_reissue_action" value="physical_from_digital_preview">پیش‌نمایش قرارداد فیزیکی قبل از تبدیل</button></p>
                        <div style="background:#fff5e9;border:1px solid #edc47f;padding:12px;border-radius:7px">
                            <label><input type="checkbox" name="confirm_physical_conversion" value="1"> تأیید می‌کنم نسخه دیجیتال قبلی از حالت امضای جاری خارج و در صورت امضاشده بودن بایگانی شود؛ قرارداد جدید تا زمان اخذ امضای فیزیکی، <strong>امضانشده</strong> خواهد بود. تغییر فقط برای همین پرونده اعمال شود.</label>
                            <p style="margin:10px 0 0"><button type="submit" class="button button-primary" name="mlr_lc_reissue_action" value="physical_from_digital_save">ثبت اصلاحیه و تبدیل قرارداد پرونده به فیزیکی</button></p>
                        </div>
                        <p style="font-size:12px;color:#646970">بدون ارسال لینک، پیامک یا OTP. وضعیت مالی پرونده و تنظیمات طرح تغییر نمی‌کند.</p>
                    </form>
                </section>
                <form method="post" style="max-width:1180px;margin-top:16px;background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px">
                    <?php wp_nonce_field('mlr_lc_reissue_action', 'mlr_lc_reissue_nonce'); ?>
                    <input type="hidden" name="request_id" value="<?php echo esc_attr($request_id); ?>">
                    <h2 style="margin-top:0">متن داینامیک قرارداد جدید</h2>
                    <p>HTML را با همان متغیرهای قراردادهای طرح وارد کنید. این متن فقط روی پرونده #<?php echo esc_html($request_id); ?> اعمال می‌شود و متن اصلی طرح را تغییر نمی‌دهد.</p>
                    <textarea name="contract_text" rows="28" spellcheck="false" style="width:100%;direction:rtl;text-align:right;font-family:Consolas,Monaco,monospace;font-size:13px;line-height:1.8;white-space:pre"><?php echo esc_textarea($editor_text); ?></textarea>

                    <details style="margin-top:12px;border:1px solid #dcdcde;border-radius:8px;padding:10px 12px;background:#f6f7f7">
                        <summary style="cursor:pointer;font-weight:700">متغیرهای قابل استفاده</summary>
                        <div dir="ltr" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px">
                            <?php foreach (mlr_lc_reissue_supported_placeholders() as $ph): ?><code style="direction:ltr"><?php echo esc_html($ph); ?></code><?php endforeach; ?>
                        </div>
                    </details>

                    <p style="margin-top:16px"><label for="mlr_reissue_reason"><strong>علت بازصدور / توضیح داخلی</strong></label></p>
                    <textarea id="mlr_reissue_reason" name="reason" rows="3" style="width:100%" placeholder="مثلاً: اصلاح بند مشخصات شرکت طبق نسخه قرارداد جدید"><?php echo esc_textarea($reason_value ?: (is_array($active) ? (string) ($active['reason'] ?? '') : '')); ?></textarea>

                    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:16px">
                        <button type="submit" name="mlr_lc_reissue_action" value="preview" class="button button-secondary">پیش‌نمایش با اطلاعات همین پرونده</button>
                        <?php if (is_array($active) && (($active['state'] ?? '') === 'pending')): ?>
                            <button type="submit" name="mlr_lc_reissue_action" value="send" class="button">ارسال مجدد لینک پیش‌نمایش</button>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top:18px;padding:14px;background:#fff5f5;border:1px solid #f0b6b6;border-radius:8px">
                        <label style="display:flex;gap:8px;align-items:flex-start;line-height:1.9"><input type="checkbox" name="confirm_reissue" value="1" style="margin-top:7px"><span>تأیید می‌کنم نسخه امضاشده قبلی باید در تاریخچه بایگانی شود، نسخه جدید به‌عنوان قرارداد جاری این پرونده فعال شود و متقاضی برای این نسخه <strong>مجدداً امضای OTP</strong> انجام دهد.</span></label>
                        <label style="display:flex;gap:8px;align-items:flex-start;line-height:1.9;margin-top:8px"><input type="checkbox" name="send_now" value="1" checked style="margin-top:7px"><span>پس از ذخیره، فقط لینک پیش‌نمایش قرارداد همین حالا به موبایل متقاضی ارسال شود. کد ۶ رقمی در این مرحله ارسال نمی‌شود؛ متقاضی پس از باز کردن لینک، مطالعه و پذیرش قرارداد و زدن دکمه «ارسال کد تأیید» کد را دریافت می‌کند.</span></label>
                        <button type="submit" name="mlr_lc_reissue_action" value="save" class="button button-primary" style="margin-top:12px">ثبت نسخه جدید و آماده‌سازی امضای مجدد</button>
                    </div>
                </form>

                <?php endif; // end digital controls ?>

                <?php if ($preview_html !== ''): ?>
                    <div style="max-width:1180px;margin-top:18px;background:#fff;border:2px solid #72aee6;border-radius:10px;padding:18px">
                        <h2 style="margin-top:0">پیش‌نمایش قرارداد جدید</h2>
                        <?php if ($is_physical || $physical_preview_requested): ?>
                            <p><button type="button" class="button button-primary" onclick="if(window.mlrPrintContractNode){window.mlrPrintContractNode(document.getElementById('mlr-physical-print-area'),{title:'قرارداد فیزیکی پرونده <?php echo absint($request_id); ?>'});}else{window.alert('موتور چاپ قرارداد بارگذاری نشده است.');}">چاپ / ذخیره PDF</button></p>
                            <div id="mlr-physical-print-area" class="mlr-loan-contract-review-print" style="direction:rtl;background:white;line-height:1.9;max-width:210mm;margin:0 auto;padding:18px;overflow:hidden">
                                <?php echo wp_kses_post(mlr_normalize_contract_markup_for_display($preview_html)); ?>
                            </div>
                        <?php else: ?>
                            <div class="mlr-contract-body" style="direction:rtl;line-height:2"><?php echo wp_kses_post(mlr_normalize_contract_markup_for_display($preview_html)); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($index)): ?>
                    <div style="max-width:1180px;margin-top:18px;background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px">
                        <h2 style="margin-top:0">تاریخچه نسخه‌های جایگزین‌شده</h2>
                        <table class="widefat striped"><thead><tr><th>نسخه</th><th>زمان امضای نسخه</th><th>زمان بایگانی</th><th>علت</th><th>مشاهده</th></tr></thead><tbody>
                        <?php foreach ($index as $row): ?>
                            <tr>
                                <td><?php echo esc_html((string) ($row['revision'] ?? '')); ?></td>
                                <td><?php echo esc_html((string) ($row['signed_at'] ?? '—')); ?></td>
                                <td><?php echo esc_html((string) ($row['archived_at'] ?? '—')); ?></td>
                                <td><?php echo esc_html((string) ($row['reason'] ?? '—')); ?></td>
                                <td><a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=mlr-loan-contract-reissue&request_id=' . $request_id . '&view_revision=' . absint($row['revision'] ?? 0))); ?>">نسخه بایگانی</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody></table>
                    </div>
                <?php endif; ?>
            <?php elseif ($request_id > 0): ?>
                <div class="notice notice-error inline"><p>پرونده‌ای با شناسه واردشده یافت نشد.</p></div>
            <?php endif; ?>
        </div>
        <?php
    }
}
