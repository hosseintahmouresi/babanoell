<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('mlr_acceptor_open_applicant_requests', 'mlr_acceptor_open_applicant_requests_shortcode');

function mlr_acceptor_open_applicant_requests_shortcode($atts = []) {
    $atts = shortcode_atts([
        'debug' => '0',
    ], $atts, 'mlr_acceptor_open_applicant_requests');

    if (!is_user_logged_in()) {
        return '<p>برای مشاهده درخواست‌های آزاد، لطفاً ابتدا وارد شوید.</p>';
    }

    $current_user_id = get_current_user_id();
    $is_acceptor = function_exists('mlr_user_has_role') && mlr_user_has_role($current_user_id, 'acceptor');
    if (!current_user_can('manage_options') && !$is_acceptor) {
        return '<p>دسترسی به درخواست‌های آزاد برای حساب شما فعال نیست.</p>';
    }

    wp_enqueue_style('mlr-frontend-css', MLR_PLUGIN_URL . 'assets/css/frontend.css', [], MLR_VERSION);
    wp_enqueue_script('mlr-acceptor-marketplace-js', MLR_PLUGIN_URL . 'assets/js/acceptor-open-applicant-requests.js', ['jquery'], MLR_VERSION, true);
    wp_localize_script('mlr-acceptor-marketplace-js', 'mlr_acceptor_marketplace_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mlr_acceptor_marketplace_nonce'),
    ]);

    global $wpdb;
    $table_requests = $wpdb->prefix . 'loan_requests';
    $plans_table = $wpdb->prefix . 'installment_plans';
    $settings = function_exists('mlr_get_acceptor_marketplace_settings') ? mlr_get_acceptor_marketplace_settings() : [];
    if (empty($settings['enabled']) || empty($settings['allow_open_marketplace'])) {
        return '<p>بازار درخواست‌های آزاد متقاضی در حال حاضر فعال نیست.</p>';
    }

    $show_claimed = !empty($settings['show_claimed_cards_to_other_acceptors']);
    $claimed_days = isset($settings['claimed_cards_visible_days']) ? max(1, absint($settings['claimed_cards_visible_days'])) : 7;
    $claimed_after = date('Y-m-d H:i:s', current_time('timestamp') - ($claimed_days * DAY_IN_SECONDS));
    $debug_enabled = current_user_can('manage_options') && in_array((string) $atts['debug'], ['1', 'true', 'yes'], true);

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT r.*, p.name AS plan_name
         FROM {$table_requests} r
         LEFT JOIN {$plans_table} p ON r.installment_plan_id = p.id
         WHERE r.request_origin = %s
           AND r.acceptor_assignment_mode = %s
           AND r.marketplace_status IN ('open', 'claimed')
           AND TRIM(COALESCE(r.status, '')) <> %s
           AND (
                r.marketplace_status = 'open'
                OR (r.marketplace_status = 'claimed' AND r.marketplace_claimed_at >= %s)
           )
         ORDER BY r.created_at DESC
         LIMIT 100",
        'applicant_portal',
        'open_acceptor_marketplace',
        'رد شده',
        $claimed_after
    ));

    $cards = [];
    $debug_rows = [];
    foreach ($rows as $row) {
        $is_claimed = $row->marketplace_status === 'claimed';
        $claimed_by_current = $is_claimed && absint($row->marketplace_claimed_by) === $current_user_id;
        if ($is_claimed && !$show_claimed && !$claimed_by_current) {
            continue;
        }

        $match_result = function_exists('mlr_acceptor_matches_applicant_request')
            ? mlr_acceptor_matches_applicant_request($current_user_id, $row, $debug_enabled)
            : true;
        $matched = is_array($match_result) ? !empty($match_result['matched']) : (bool) $match_result;
        if (!$matched) {
            if ($debug_enabled && is_array($match_result)) {
                $debug_rows[] = [
                    'id' => absint($row->id),
                    'code' => $match_result['code'] ?? '',
                    'message' => $match_result['message'] ?? '',
                ];
            }
            continue;
        }

        $cards[] = $row;
    }

    ob_start();
    ?>
    <div class="mlr-acceptor-marketplace" dir="rtl">
        <h2>درخواست‌های آزاد متقاضیان</h2>
        <?php if (!empty($settings['acceptor_shortcode_help_text'])): ?>
            <p class="mlr-step-description"><?php echo esc_html($settings['acceptor_shortcode_help_text']); ?></p>
        <?php endif; ?>
        <?php if (empty($cards)): ?>
            <div class="mlr-glass-card" style="padding:20px;text-align:center;">درخواست آزادی متناسب با شرایط شما یافت نشد.</div>
        <?php else: ?>
            <div class="mlr-marketplace-grid">
                <?php foreach ($cards as $request):
                    $locations = function_exists('mlr_get_request_location_names') ? mlr_get_request_location_names($request) : [];
                    $categories = function_exists('mlr_get_request_activity_category_names') ? mlr_get_request_activity_category_names($request) : [];
                    $is_claimed = $request->marketplace_status === 'claimed';
                    $claimed_by_current = $is_claimed && absint($request->marketplace_claimed_by) === $current_user_id;
                    $scope_value = (string) ($request->requested_service_scope ?? '');
                    $scope_label = in_array($scope_value, ['selected_locations', 'selected_cities'], true) ? implode('، ', $locations) : 'کل کشور';
                    ?>
                    <div class="mlr-marketplace-card<?php echo $is_claimed ? ' is-claimed' : ''; ?>" data-request-id="<?php echo esc_attr($request->id); ?>">
                        <div class="mlr-marketplace-card-head">
                            <strong>پرونده #<?php echo esc_html($request->id); ?></strong>
                            <span class="mlr-status-badge" data-status="<?php echo esc_attr($is_claimed ? 'claimed' : 'open'); ?>">
                                <?php echo esc_html($is_claimed ? ($claimed_by_current ? 'پذیرفته شده توسط شما' : 'پذیرفته شد') : 'آزاد'); ?>
                            </span>
                        </div>
                        <div class="mlr-marketplace-card-body">
                            <p><strong>طرح:</strong> <?php echo esc_html($request->plan_name ?: '-'); ?></p>
                            <p><strong>مبلغ:</strong> <?php echo esc_html(number_format((float) ($request->facility_amount ?: $request->loan_amount))); ?> ریال</p>
                            <p><strong>محدوده:</strong> <?php echo esc_html($scope_label ?: '-'); ?></p>
                            <p><strong>حوزه فعالیت:</strong> <?php echo esc_html(!empty($categories) ? implode('، ', $categories) : '-'); ?></p>
                            <p><strong>توضیح متقاضی:</strong> <?php echo esc_html($request->request_public_summary ?: '-'); ?></p>
                            <p><strong>تاریخ ثبت:</strong> <?php echo esc_html(function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($request->created_at, 'Y/m/d H:i') : $request->created_at); ?></p>
                        </div>
                        <div class="mlr-marketplace-card-actions">
                            <?php if ($is_claimed): ?>
                                <button type="button" class="mlr-btn mlr-btn-secondary" disabled><?php echo esc_html($claimed_by_current ? 'توسط شما پذیرفته شده' : 'پذیرفته شده'); ?></button>
                            <?php elseif (!$is_acceptor): ?>
                                <button type="button" class="mlr-btn mlr-btn-secondary" disabled>فقط حساب پذیرنده می‌تواند پرونده را بپذیرد</button>
                            <?php else: ?>
                                <button type="button" class="mlr-btn mlr-btn-primary mlr-claim-applicant-request" data-request-id="<?php echo esc_attr($request->id); ?>">پذیرش پرونده</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($debug_enabled && !empty($debug_rows)): ?>
            <div class="mlr-glass-card" style="margin-top:16px;padding:16px;">
                <strong>Debug match reasons</strong>
                <ul>
                    <?php foreach ($debug_rows as $debug_row): ?>
                        <li>#<?php echo esc_html($debug_row['id']); ?>: <?php echo esc_html($debug_row['code']); ?> - <?php echo esc_html($debug_row['message']); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
    <style>
        .mlr-acceptor-marketplace { max-width: 1100px; margin: 24px auto; }
        .mlr-marketplace-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
        .mlr-marketplace-card { background: rgba(255,255,255,.86); border: 1px solid rgba(29,53,88,.12); border-radius: 8px; padding: 16px; box-shadow: 0 10px 28px rgba(29,53,88,.08); position: relative; overflow: hidden; }
        .mlr-marketplace-card.is-claimed { opacity: .72; pointer-events: none; }
        .mlr-marketplace-card.is-claimed::after { content: 'پذیرفته شد'; position: absolute; top: 42%; left: 50%; transform: translate(-50%, -50%) rotate(-14deg); border: 3px solid rgba(190, 18, 60, .45); color: rgba(190, 18, 60, .50); border-radius: 10px; padding: 10px 28px; font-size: 26px; font-weight: 900; letter-spacing: .5px; background: rgba(255,255,255,.08); box-shadow: 0 0 0 2px rgba(190, 18, 60, .08) inset; z-index: 3; }
        .mlr-marketplace-card-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:12px; }
        .mlr-marketplace-card-body p { margin: 7px 0; line-height: 1.8; }
        .mlr-marketplace-card-actions { margin-top: 14px; }
        .mlr-marketplace-card-actions .mlr-btn { width: 100%; }
    </style>
    <?php
    return ob_get_clean();
}
