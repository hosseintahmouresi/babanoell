<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Canonical occupation options used by loan request forms and per-plan document rules.
 */
if (!function_exists('mlr_get_occupation_options')) {
    function mlr_get_occupation_options() {
        return [
            'official_employee' => 'کارمند رسمی',
            'contract_employee' => 'کارمند قراردادی',
            'worker'            => 'کارگر',
            'freelancer'        => 'آزاد',
            'retired'           => 'بازنشسته',
            'pensioner'         => 'مستمری بگیر',
            'employer'          => 'کارفرما',
            'other'             => 'سایر',
        ];
    }
}

if (!function_exists('mlr_normalize_occupation_key')) {
    function mlr_normalize_occupation_key($occupation) {
        $occupation = sanitize_key((string) $occupation);
        $options = mlr_get_occupation_options();
        return isset($options[$occupation]) ? $occupation : '';
    }
}

if (!function_exists('mlr_sanitize_required_document_keys')) {
    function mlr_sanitize_required_document_keys($document_keys) {
        $document_keys = maybe_unserialize($document_keys);
        if (!is_array($document_keys)) {
            return [];
        }

        $allowed = function_exists('mlr_get_all_document_options') ? mlr_get_all_document_options() : [];
        $clean = [];
        foreach ($document_keys as $document_key) {
            $document_key = sanitize_key((string) $document_key);
            if ($document_key !== '' && isset($allowed[$document_key])) {
                $clean[$document_key] = $document_key;
            }
        }
        return array_values($clean);
    }
}

if (!function_exists('mlr_get_plan_occupation_document_map')) {
    function mlr_get_plan_occupation_document_map($settings) {
        $settings = maybe_unserialize($settings);
        if (!is_array($settings)) {
            return [];
        }

        $raw_map = $settings['required_documents_by_occupation'] ?? [];
        if (!is_array($raw_map)) {
            return [];
        }

        $result = [];
        foreach (mlr_get_occupation_options() as $occupation_key => $label) {
            if (!array_key_exists($occupation_key, $raw_map)) {
                continue;
            }
            $result[$occupation_key] = mlr_sanitize_required_document_keys($raw_map[$occupation_key]);
        }
        return $result;
    }
}

if (!function_exists('mlr_plan_uses_occupation_documents')) {
    function mlr_plan_uses_occupation_documents($settings) {
        $settings = maybe_unserialize($settings);
        return is_array($settings) && !empty($settings['occupation_documents_enabled']);
    }
}

/**
 * Resolve the exact required document keys for a plan and occupation.
 *
 * Legacy plans (occupation mode disabled) always return required_documents.
 * When occupation mode is enabled and a valid occupation is supplied, only that
 * occupation's configured list is returned. If occupation is empty, the legacy
 * list is returned for non-submission previews (calculator/plan summary).
 */
if (!function_exists('mlr_get_plan_required_document_keys')) {
    function mlr_get_plan_required_document_keys($required_documents, $settings = [], $occupation = '') {
        $fallback = mlr_sanitize_required_document_keys($required_documents);
        if (!mlr_plan_uses_occupation_documents($settings)) {
            return $fallback;
        }

        $occupation = mlr_normalize_occupation_key($occupation);
        if ($occupation === '') {
            return $fallback;
        }

        $map = mlr_get_plan_occupation_document_map($settings);
        return array_key_exists($occupation, $map) ? $map[$occupation] : [];
    }
}

if (!function_exists('mlr_validate_plan_occupation')) {
    function mlr_validate_plan_occupation($settings, $occupation) {
        if (!mlr_plan_uses_occupation_documents($settings)) {
            return true;
        }

        $occupation = mlr_normalize_occupation_key($occupation);
        if ($occupation === '') {
            return new WP_Error('mlr_invalid_occupation', 'برای این طرح انتخاب شغل معتبر الزامی است.');
        }

        $map = mlr_get_plan_occupation_document_map($settings);
        if (!array_key_exists($occupation, $map) || empty($map[$occupation])) {
            return new WP_Error('mlr_occupation_documents_missing', 'مدارک شغل انتخاب‌شده برای این طرح تنظیم نشده است. لطفاً با پشتیبانی تماس بگیرید.');
        }

        return true;
    }
}
