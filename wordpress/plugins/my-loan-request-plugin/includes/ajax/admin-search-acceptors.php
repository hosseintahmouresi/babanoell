<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_admin_search_acceptors', 'mlr_admin_search_acceptors_callback');

function mlr_admin_search_acceptors_callback() {
    check_ajax_referer('mlr_ajax_nonce_action', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }

    $term = isset($_POST['term']) ? sanitize_text_field(wp_unslash($_POST['term'])) : '';
    $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
    $plan_id = isset($_POST['plan_id']) ? absint($_POST['plan_id']) : 0;

    if ($request_id > 0 && $plan_id <= 0) {
        global $wpdb;
        $table_requests = $wpdb->prefix . 'loan_requests';
        $plan_id = absint($wpdb->get_var($wpdb->prepare("SELECT installment_plan_id FROM {$table_requests} WHERE id = %d", $request_id)));
    }

    $candidate_ids = [];
    $user_query_args = [
        'number' => 50,
        'fields' => ['ID', 'display_name', 'user_login', 'user_email'],
        'orderby' => 'display_name',
        'order' => 'ASC',
    ];

    if ($term !== '') {
        $user_query_args['search'] = '*' . $term . '*';
        $user_query_args['search_columns'] = ['display_name', 'user_login', 'user_email'];
    } else {
        $user_query_args['role__in'] = ['pazirande', 'acceptor'];
    }

    foreach (get_users($user_query_args) as $user) {
        $candidate_ids[(int) $user->ID] = (int) $user->ID;
    }

    $post_type = function_exists('mlr_get_acceptor_cpt_slug') ? mlr_get_acceptor_cpt_slug() : 'seller';
    if ($term !== '' && $post_type && post_type_exists($post_type)) {
        $seller_posts = get_posts([
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => 30,
            's' => $term,
            'fields' => 'ids',
        ]);

        foreach ($seller_posts as $seller_post_id) {
            $acceptor_id = function_exists('mlr_get_acceptor_user_id_from_seller_post')
                ? mlr_get_acceptor_user_id_from_seller_post($seller_post_id)
                : absint(get_post_field('post_author', $seller_post_id));
            if ($acceptor_id > 0) {
                $candidate_ids[$acceptor_id] = $acceptor_id;
            }
        }
    }

    $items = [];
    foreach ($candidate_ids as $candidate_id) {
        if (!function_exists('mlr_user_has_role') || !mlr_user_has_role($candidate_id, 'acceptor')) {
            continue;
        }

        if ($plan_id > 0 && function_exists('mlr_user_can_access_installment_plan') && !mlr_user_can_access_installment_plan($candidate_id, $plan_id, true)) {
            continue;
        }

        $user = get_userdata($candidate_id);
        if (!$user) {
            continue;
        }

        $title = function_exists('mlr_get_acceptor_display_name')
            ? mlr_get_acceptor_display_name($candidate_id)
            : $user->display_name;
        $subtitle_parts = [];
        if ($user->display_name && $user->display_name !== $title) {
            $subtitle_parts[] = $user->display_name;
        }
        if (!empty($user->user_login)) {
            $subtitle_parts[] = $user->user_login;
        }

        $items[] = [
            'id' => $candidate_id,
            'title' => $title ?: $user->display_name,
            'subtitle' => implode(' - ', array_filter($subtitle_parts)),
        ];

        if (count($items) >= 20) {
            break;
        }
    }

    wp_send_json_success(['items' => $items]);
}
