<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_applicant_search_acceptors', 'mlr_applicant_search_acceptors_callback');

function mlr_applicant_search_acceptors_callback() {
    check_ajax_referer('mlr_applicant_acceptor_search_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'برای جستجوی پذیرنده ابتدا وارد شوید.']);
    }

    $current_user_id = get_current_user_id();
    if (!current_user_can('manage_options') && function_exists('mlr_is_applicant_user') && !mlr_is_applicant_user($current_user_id)) {
        wp_send_json_error(['message' => 'دسترسی به این بخش برای حساب شما فعال نیست.']);
    }

    $settings = function_exists('mlr_get_acceptor_marketplace_settings') ? mlr_get_acceptor_marketplace_settings() : [];
    if (empty($settings['enabled']) || empty($settings['allow_applicant_selected_acceptor'])) {
        wp_send_json_success(['acceptors' => []]);
    }

    $term = isset($_POST['term']) ? trim(sanitize_text_field(wp_unslash($_POST['term']))) : '';
    $plan_id = isset($_POST['installment_plan_id']) ? absint($_POST['installment_plan_id']) : 0;

    $term_length = function_exists('mb_strlen') ? mb_strlen($term) : strlen($term);
    if ($term_length < 2) {
        wp_send_json_error(['message' => 'حداقل دو کاراکتر برای جستجو وارد کنید.']);
    }

    if ($plan_id <= 0) {
        wp_send_json_error(['message' => 'ابتدا طرح تسهیلات را انتخاب کنید.']);
    }

    if (function_exists('mlr_applicant_can_access_installment_plan') && !mlr_applicant_can_access_installment_plan($plan_id, true)) {
        wp_send_json_error(['message' => 'طرح انتخاب‌شده برای حساب شما مجاز نیست.']);
    }

    $post_type = function_exists('mlr_get_acceptor_cpt_slug') ? mlr_get_acceptor_cpt_slug() : 'seller';
    if (!$post_type || !post_type_exists($post_type)) {
        wp_send_json_success(['acceptors' => []]);
    }

    $term_lc = function_exists('mb_strtolower') ? mb_strtolower($term) : strtolower($term);
    $seller_posts = get_posts([
        'post_type' => $post_type,
        'post_status' => ['publish', 'private', 'draft', 'pending'],
        'posts_per_page' => 300,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);

    $results = [];
    foreach ($seller_posts as $seller_post) {
        $acceptor_id = function_exists('mlr_get_acceptor_user_id_from_seller_post') ? mlr_get_acceptor_user_id_from_seller_post($seller_post->ID) : absint($seller_post->post_author);
        if ($acceptor_id <= 0) {
            continue;
        }

        $candidate = get_userdata($acceptor_id);
        if (!$candidate || !function_exists('mlr_user_has_role') || !mlr_user_has_role($acceptor_id, 'acceptor')) {
            continue;
        }

        if (get_user_meta($acceptor_id, 'mlr_acceptor_marketplace_enabled', true) === '0') {
            continue;
        }

        if (function_exists('mlr_user_can_access_installment_plan') && !mlr_user_can_access_installment_plan($acceptor_id, $plan_id, true)) {
            continue;
        }

        $locations = function_exists('mlr_get_acceptor_locations_from_seller_post') ? mlr_get_acceptor_locations_from_seller_post($acceptor_id) : [];
        $categories = function_exists('mlr_get_acceptor_categories_from_seller_post') ? mlr_get_acceptor_categories_from_seller_post($acceptor_id) : [];
        $location_names = wp_list_pluck($locations, 'name');
        $category_names = wp_list_pluck($categories, 'name');

        $haystack_parts = [
            $seller_post->post_title,
            $candidate->display_name,
            $candidate->user_login,
            implode(' ', $location_names),
            implode(' ', $category_names),
        ];
        $haystack = function_exists('mb_strtolower') ? mb_strtolower(implode(' ', $haystack_parts)) : strtolower(implode(' ', $haystack_parts));
        if (strpos($haystack, $term_lc) === false) {
            continue;
        }

        $results[] = [
            'user_id' => $acceptor_id,
            'title' => function_exists('mlr_get_acceptor_display_name') ? mlr_get_acceptor_display_name($acceptor_id) : $seller_post->post_title,
            'city' => !empty($location_names) ? implode('، ', $location_names) : 'موقعیت ثبت نشده',
            'categories' => !empty($category_names) ? implode('، ', $category_names) : '',
            'plan_allowed' => true,
        ];

        if (count($results) >= 20) {
            break;
        }
    }

    if (empty($results)) {
        // Fallback: some installations connect seller posts to acceptor users only through user meta.
        $acceptor_users = get_users([
            'role' => 'acceptor',
            'number' => 100,
            'fields' => ['ID', 'display_name', 'user_login'],
            'search' => '*' . $term . '*',
            'search_columns' => ['user_login', 'display_name'],
        ]);
        foreach ($acceptor_users as $candidate_user) {
            $acceptor_id = absint($candidate_user->ID);
            if ($acceptor_id <= 0 || get_user_meta($acceptor_id, 'mlr_acceptor_marketplace_enabled', true) === '0') {
                continue;
            }
            if (function_exists('mlr_user_can_access_installment_plan') && !mlr_user_can_access_installment_plan($acceptor_id, $plan_id, true)) {
                continue;
            }
            $profile_post_id = function_exists('mlr_get_acceptor_profile_post_id') ? mlr_get_acceptor_profile_post_id($acceptor_id) : 0;
            if ($profile_post_id <= 0) {
                continue;
            }
            $locations = function_exists('mlr_get_acceptor_locations_from_seller_post') ? mlr_get_acceptor_locations_from_seller_post($acceptor_id) : [];
            $categories = function_exists('mlr_get_acceptor_categories_from_seller_post') ? mlr_get_acceptor_categories_from_seller_post($acceptor_id) : [];
            $location_names = wp_list_pluck($locations, 'name');
            $category_names = wp_list_pluck($categories, 'name');
            $results[] = [
                'user_id' => $acceptor_id,
                'title' => function_exists('mlr_get_acceptor_display_name') ? mlr_get_acceptor_display_name($acceptor_id) : $candidate_user->display_name,
                'city' => !empty($location_names) ? implode('، ', $location_names) : 'موقعیت ثبت نشده',
                'categories' => !empty($category_names) ? implode('، ', $category_names) : '',
                'plan_allowed' => true,
            ];
            if (count($results) >= 20) {
                break;
            }
        }
    }

    wp_send_json_success(['acceptors' => $results]);
}
