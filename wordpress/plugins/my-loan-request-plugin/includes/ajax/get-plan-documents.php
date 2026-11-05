<?php
/**
 * AJAX Handler for fetching the required documents for a specific installment plan.
 * This is used by the front-end form to dynamically display file upload fields.
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_get_plan_documents', 'mlr_get_plan_documents_callback');
add_action('wp_ajax_nopriv_mlr_get_plan_documents', 'mlr_get_plan_documents_callback');

function mlr_get_plan_documents_callback() {
    // 1. Security Check - --- START: ویرایش و اصلاح نانس ---
    // پاکسازی بافر خروجی برای جلوگیری از خطای JSON
    if (ob_get_length()) ob_clean();
    check_ajax_referer('mlr_frontend_nonce', 'nonce');
    // --- END: ویرایش و اصلاح نانس ---

    // 2. Input Validation
    if (!isset($_POST['plan_id'])) {
        wp_send_json_error(['message' => 'خطا: شناسه طرح ارسال نشده است.']);
        return;
    }
    $plan_id = intval($_POST['plan_id']);
    $occupation = isset($_POST['occupation']) ? sanitize_key(wp_unslash($_POST['occupation'])) : '';
    $requested_context = isset($_POST['context']) ? sanitize_key(wp_unslash($_POST['context'])) : '';
    $allowed_contexts = ['mlr_loan_request_form', 'mlr_loan_calculator'];
    $context = in_array($requested_context, $allowed_contexts, true) ? $requested_context : 'mlr_loan_request_form';

    if (function_exists('mlr_user_can_access_installment_plan') && !mlr_user_can_access_installment_plan(get_current_user_id(), $plan_id, true, $context)) {
        wp_send_json_error(['message' => 'طرح انتخاب‌شده در این فرم یا برای حساب شما در دسترس نیست.']);
        return;
    }

    // 3. Database Query
    global $wpdb;
    $table_name = $wpdb->prefix . 'installment_plans';
    $plan = $wpdb->get_row($wpdb->prepare("SELECT required_documents, settings FROM $table_name WHERE id = %d AND is_active = 1", $plan_id));

    if (!$plan) {
        wp_send_json_error(['message' => 'طرح انتخاب شده یافت نشد یا در حال حاضر فعال نیست.']);
        return;
    }

    // 4. Prepare Response
    $settings = maybe_unserialize($plan->settings);
    if (!is_array($settings)) {
        $settings = [];
    }

    $documents_to_send = function_exists('mlr_prepare_plan_required_documents_payload')
        ? mlr_prepare_plan_required_documents_payload($plan->required_documents, $settings, $occupation)
        : [];
    $occupation_documents_enabled = function_exists('mlr_plan_uses_occupation_documents') && mlr_plan_uses_occupation_documents($settings);
    $normalized_occupation = function_exists('mlr_normalize_occupation_key') ? mlr_normalize_occupation_key($occupation) : $occupation;
    if ($occupation !== '' && $occupation_documents_enabled && $normalized_occupation === '') {
        wp_send_json_error(['message' => 'شغل انتخاب‌شده معتبر نیست.']);
        return;
    }
    $occupation_options = function_exists('mlr_get_occupation_options') ? mlr_get_occupation_options() : [];
    $occupation_label = $normalized_occupation !== '' && isset($occupation_options[$normalized_occupation]) ? $occupation_options[$normalized_occupation] : '';
    $occupation_required = $occupation_documents_enabled && $normalized_occupation === '';
    if ($occupation_required) {
        // Do not expose a generic/default upload list in occupation mode. The form
        // must first know the applicant's job before showing required documents.
        $documents_to_send = [];
    }

    $plan_conditions = function_exists('mlr_get_plan_conditions_html')
        ? mlr_get_plan_conditions_html($settings)
        : '';

    // 5. Send JSON Response
    wp_send_json([
        'success'            => true,
        'data'               => $documents_to_send,
        'documents'          => $documents_to_send,
        'required_documents' => $documents_to_send,
        'plan_conditions'    => $plan_conditions,
        'occupation_documents_enabled' => $occupation_documents_enabled ? 1 : 0,
        'occupation'         => $normalized_occupation,
        'occupation_label'   => $occupation_label,
        'occupation_required'=> $occupation_required ? 1 : 0,
    ]);
}
