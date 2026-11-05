<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('mlr_loan_calculator', 'mlr_loan_calculator_shortcode');

function mlr_loan_calculator_shortcode($atts = []) {
    $atts = shortcode_atts([
        'selector_mode' => '',
    ], $atts, 'mlr_loan_calculator');

    $selector_mode = sanitize_key($atts['selector_mode']);
    if (!in_array($selector_mode, ['tabs', 'dropdown'], true)) {
        $selector_mode = get_option('mlr_calculator_plan_selector_mode', 'tabs');
    }
    $selector_mode = in_array($selector_mode, ['tabs', 'dropdown'], true) ? $selector_mode : 'tabs';

    $user_id = is_user_logged_in() ? get_current_user_id() : 0;
    if (function_exists('mlr_get_available_installment_plans_for_user')) {
        $plans = mlr_get_available_installment_plans_for_user($user_id, [
            'active_only' => true,
            'fields' => ['id', 'name', 'type', 'settings', 'display_fields', 'required_documents'],
            'order_by' => 'display_order',
            'visibility_context' => 'mlr_loan_calculator',
        ]);
    } else {
        global $wpdb;
        $table_name = $wpdb->prefix . 'installment_plans';
        $plan_order_sql = function_exists('mlr_get_installment_plan_order_by_sql') ? mlr_get_installment_plan_order_by_sql() : 'ORDER BY id DESC';
        $plans = $wpdb->get_results("SELECT id, name, type, settings, display_fields, required_documents FROM $table_name WHERE is_active = 1 {$plan_order_sql}");
    }

    if (empty($plans)) {
        return '<p>در حال حاضر هیچ طرح فعال و قابل نمایشی برای این حساب در دسترس نیست.</p>';
    }

    $js_plans = array_map(function($plan) {
        $plan->settings = maybe_unserialize($plan->settings);
        if (!is_array($plan->settings)) {
            $plan->settings = [];
        }
        $plan->display_fields = maybe_unserialize($plan->display_fields ?? null);
        if (!is_array($plan->display_fields)) {
            $plan->display_fields = [];
        }
        $plan->required_documents = function_exists('mlr_prepare_plan_required_documents_payload')
            ? mlr_prepare_plan_required_documents_payload($plan->required_documents ?? [], $plan->settings)
            : [];
        $plan->plan_conditions = function_exists('mlr_get_plan_conditions_html')
            ? mlr_get_plan_conditions_html($plan->settings)
            : '';
        return $plan;
    }, $plans);

    $user_commission = null;
    if ($user_id > 0) {
        $user_commission = get_user_meta($user_id, 'acceptor_commission_percent', true);
    }

    wp_enqueue_script('mlr-calculator-js', MLR_PLUGIN_URL . 'assets/js/loan-calculator.js', ['jquery'], MLR_VERSION, true);
    wp_localize_script('mlr-calculator-js', 'mlrCalculatorAjax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('mlr_frontend_calc_nonce'),
        'plans'   => $js_plans,
        'user_commission' => $user_commission,
        'selector_mode' => $selector_mode,
    ]);

    ob_start();
    ?>
    <div class="mlr-calc-wrapper mlr-calc-selector-<?php echo esc_attr($selector_mode); ?>" data-plan-selector-mode="<?php echo esc_attr($selector_mode); ?>">
        <div class="mlr-calc-container">
            <div class="mlr-calc-inputs">
                <h3>محاسبه‌گر اقساط</h3>
                <p>طرح، مبلغ درخواستی و مدت زمان بازپرداخت را مشخص کنید.</p>

                <div class="mlr-calc-group">
                    <label>انتخاب طرح تسهیلات</label>
                    <div id="mlr_plan_tabs_container" class="mlr-calc-tabs plan-tabs">
                        </div>
                </div>

                <div class="mlr-plan-info-accordions mlr-calc-plan-info-accordions" style="display:none;">
                    <div class="mlr-plan-docs-accordion mlr-calc-plan-docs-accordion" data-state="collapsed">
                        <button type="button" class="mlr-plan-docs-toggle" aria-expanded="false">
                            <span class="mlr-plan-docs-toggle-title">مدارک مورد نیاز طرح</span>
                            <span class="mlr-plan-docs-toggle-icon" aria-hidden="true"></span>
                        </button>
                        <div class="mlr-plan-docs-panel" hidden>
                            <div class="mlr-plan-docs-status" style="display:none;"></div>
                            <div class="mlr-plan-docs-list"></div>
                        </div>
                    </div>
                    <div class="mlr-plan-docs-accordion mlr-plan-conditions-accordion mlr-calc-plan-conditions-accordion" data-state="collapsed">
                        <button type="button" class="mlr-plan-docs-toggle" aria-expanded="false">
                            <span class="mlr-plan-docs-toggle-title">شرایط طرح</span>
                            <span class="mlr-plan-docs-toggle-icon" aria-hidden="true"></span>
                        </button>
                        <div class="mlr-plan-docs-panel" hidden>
                            <div class="mlr-plan-conditions-status" style="display:none;"></div>
                            <div class="mlr-plan-conditions-content"></div>
                        </div>
                    </div>
                </div>

                <div class="mlr-calc-group">
                    <label id="mlr_amount_label">مبلغ (ریال)</label>
                    <div class="mlr-calc-amount-input-new">
                         <button class="mlr-calc-control" data-action="minus">&#8722;</button>
                         <input type="text" id="mlr_loan_amount" disabled>
                         <button class="mlr-calc-control" data-action="plus">&#43;</button>
                    </div>
                </div>

                <div class="mlr-calc-group">
                    <input type="range" id="mlr_loan_slider" disabled>
                    <div class="mlr-calc-slider-labels">
                        <span id="mlr_slider_min_label"></span>
                        <span id="mlr_slider_max_label"></span>
                    </div>
                </div>

                <div class="mlr-calc-group">
                    <label>مدت زمان بازپرداخت</label>
                    <div id="mlr_installments_tabs" class="mlr-calc-tabs installment-tabs">
                        </div>
                </div>
                 
                <div id="calc_interval_wrapper_front" style="display:none;" class="mlr-calc-group">
                    <label for="calc_interval_front">فاصله اقساط (ماه)</label>
                    <div id="mlr_interval_tabs_container" class="mlr-calc-tabs interval-tabs">
                        </div>
                </div>

                <button id="mlr_calculate_btn" class="mlr-calc-button calculate-btn">محاسبه اقساط</button>
            </div>

            <div class="mlr-calc-results">
                <div class="mlr-calc-result-content">
                    <img src="https://babanoell.com/wp-content/uploads/لوگو-واتر-سایت-1.png" alt="Babanoell Logo" class="mlr-calc-logo"/>
                    <div id="mlr_result_display">
                        <p class="mlr-calc-placeholder">لطفا یک طرح را برای مشاهده نتایج انتخاب کنید.</p>
                    </div>
					<a class="mlr-calc-button request-btn" style="background-color: #1D3559; color: #FFFFFF" href="https://babanoell.com/panel/">
					ثبت درخواست تسهیلات
					</a>
                </div>
            </div>
        </div>
    </div>
    <style>
        .mlr-calc-wrapper { font-family: 'IRANSans', 'Vazir', 'Tahoma', sans-serif; direction: rtl; }
        .mlr-calc-container { display: flex; flex-wrap: wrap; max-width: 1024px; margin: 40px auto; background: #fff; border-radius: 24px; box-shadow: 0 15px 40px rgba(0,0,0,0.1); overflow: hidden; }
        .mlr-calc-inputs { flex: 60%; background: #fff; padding: 40px; }
        .mlr-calc-results { flex: 40%; background: #d0e2f2; padding: 40px; }
        .mlr-calc-inputs h3 { font-size: 24px; color: #1d3558; margin-top: 0; }
        .mlr-calc-inputs p { font-size: 16px; color: #5a789a; margin-bottom: 30px; }
        .mlr-calc-group { margin-bottom: 25px; }
        .mlr-calc-group label { display: block; font-weight: bold; margin-bottom: 12px; font-size: 14px; color: #1d3558; }
        .mlr-calc-tabs { display: flex; flex-wrap: wrap; gap: 10px; }
        .mlr-calc-tab { padding: 10px 15px; background: #d0e2f2; border: 1px solid #b9cde0; color: #1d3558; border-radius: 8px; cursor: pointer; font-size: 14px; transition: all 0.2s ease; }
        .mlr-calc-tab.active { background: #1d3558; color: #d0e2f2; border-color: #1d3558; font-weight: bold; }
        .mlr-calc-amount-input-new { display: flex; align-items: stretch; border: 1px solid #b9cde0; border-radius: 8px; background: #fff; }
        .mlr-calc-amount-input-new input { flex-grow: 1; border: none; font-size: 20px; font-weight: bold; color: #1a1a1a; width: 70%; padding: 12px 15px; text-align: center; background: transparent; }
        .mlr-calc-amount-input-new input:focus { outline: none; }
        .mlr-calc-control { border: none; background: #d0e2f2; width: 44px; cursor: pointer; color: #1d3558; font-size: 24px; line-height: 1; }
        .mlr-calc-control:first-of-type { border-left: 1px solid #b9cde0; border-top-right-radius: 8px; border-bottom-right-radius: 8px;}
        .mlr-calc-control:last-of-type { border-right: 1px solid #b9cde0; border-top-left-radius: 8px; border-bottom-left-radius: 8px;}
        input[type=range] { -webkit-appearance: none; width: 100%; background: transparent; margin-top: 15px; }
        input[type=range]::-webkit-slider-runnable-track { height: 8px; background: #d0e2f2; border-radius: 4px; }
        input[type=range]::-webkit-slider-thumb { -webkit-appearance: none; height: 24px; width: 24px; border-radius: 50%; background: #1d3558; cursor: pointer; margin-top: -8px; }
        .mlr-calc-slider-labels { display: flex; justify-content: space-between; font-size: 12px; color: #5a789a; margin-top: 8px; }
        .mlr-calc-result-content { display: flex; flex-direction: column; height: 100%; text-align: center; }
        .mlr-calc-logo { max-width: 150px !important; height: auto; display: block; margin: 0 auto 30px auto; }
        #mlr_result_display { flex-grow: 1; }
        .mlr-calc-placeholder { color: #1d3558; margin-top: 40px; }
        .mlr-calc-result-item { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid #b9cde0; }
        .mlr-calc-result-item:last-child { border-bottom: none; }
        .mlr-calc-result-item .label { font-size: 14px; color: #5a789a; }
        .mlr-calc-result-item .value { font-size: 18px; font-weight: bold; color: #1d3558; }
        .mlr-calc-result-item .value.loading { color: #888; }
        .mlr-calc-button { display: flex; align-items: center; justify-content: center; height: 60px; text-align: center; width: 100%; padding: 16px; border: none; border-radius: 8px; font-size: 18px; font-weight: bold; cursor: pointer; text-decoration: none; }
        .mlr-calc-button.calculate-btn { margin-top: 20px; background: #1d3558; color: #d0e2f2; }
        .mlr-calc-button.request-btn { margin-top: auto; background: #1d3558; color: #d0e2f2; }

        .mlr-calc-plan-dropdown { position: relative; width: 100%; }
        .mlr-calc-plan-trigger { width: 100%; min-height: 52px; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; background: #d0e2f2; border: 1px solid #b9cde0; color: #1d3558; border-radius: 12px; cursor: pointer; font-family: inherit; font-size: 14px; font-weight: 700; text-align: right; transition: all 0.2s ease; }
        .mlr-calc-plan-trigger:hover,
        .mlr-calc-plan-dropdown.is-open .mlr-calc-plan-trigger { background: #c4d9ec; border-color: #1d3558; box-shadow: 0 8px 22px rgba(29, 53, 88, 0.12); }
        .mlr-calc-plan-trigger-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mlr-calc-plan-trigger-icon { width: 36px; height: 36px; min-width: 36px; border-radius: 50%; background: #1d3558; color: #d0e2f2; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 6px 14px rgba(29, 53, 88, 0.22); transition: transform 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease; }
        .mlr-calc-plan-trigger-chevron { width: 25px; height: 25px; display: block; fill: currentColor; }
        .mlr-calc-plan-trigger:hover .mlr-calc-plan-trigger-icon,
        .mlr-calc-plan-dropdown.is-open .mlr-calc-plan-trigger-icon { background: #142943; box-shadow: 0 8px 18px rgba(29, 53, 88, 0.28); }
        .mlr-calc-plan-dropdown.is-open .mlr-calc-plan-trigger-icon { transform: rotate(180deg); }
        .mlr-calc-plan-menu { position: absolute; top: calc(100% + 8px); right: 0; left: 0; z-index: 20; display: none; max-height: 290px; overflow-y: auto; padding: 8px; background: #fff; border: 1px solid #b9cde0; border-radius: 14px; box-shadow: 0 18px 42px rgba(29, 53, 88, 0.18); }
        .mlr-calc-plan-dropdown.is-open .mlr-calc-plan-menu { display: block; }
        .mlr-calc-plan-menu .plan-tab { width: 100%; display: block; margin: 0 0 6px 0; text-align: right; border-radius: 10px; }
        .mlr-calc-plan-menu .plan-tab:last-child { margin-bottom: 0; }
        .mlr-calc-plan-menu .plan-tab.active { background: #1d3558; color: #d0e2f2; }
        .mlr-calc-plan-menu::-webkit-scrollbar { width: 8px; }
        .mlr-calc-plan-menu::-webkit-scrollbar-track { background: #eef6fc; border-radius: 10px; }
        .mlr-calc-plan-menu::-webkit-scrollbar-thumb { background: #b9cde0; border-radius: 10px; }
        .mlr-calc-selector-dropdown #mlr_plan_tabs_container.mlr-calc-tabs { display: block; }
        .mlr-plan-info-accordions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; align-items: start; margin: -10px 0 25px; }
        .mlr-plan-docs-accordion { margin: -10px 0 25px; border: 1px solid #b9cde0; border-radius: 12px; background: #eef6fc; box-shadow: 0 10px 28px rgba(29, 53, 88, 0.08); direction: rtl; overflow: hidden; }
        .mlr-plan-info-accordions .mlr-plan-docs-accordion { margin: 0; height: fit-content; }
        .mlr-plan-docs-toggle { width: 100%; min-height: 54px; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; border: 0; background: #d0e2f2; color: #1d3558; cursor: pointer; font-family: inherit; text-align: right; }
        .mlr-plan-docs-toggle-title { font-size: 15px; font-weight: 800; line-height: 1.6; }
        .mlr-plan-docs-toggle-icon { width: 30px; height: 30px; min-width: 30px; border-radius: 999px; background: #1d3558; color: #d0e2f2; position: relative; transition: transform 0.2s ease; }
        .mlr-plan-docs-toggle-icon::before,
        .mlr-plan-docs-toggle-icon::after { content: ""; position: absolute; background: currentColor; border-radius: 2px; top: 50%; left: 50%; transform: translate(-50%, -50%); }
        .mlr-plan-docs-toggle-icon::before { width: 12px; height: 2px; }
        .mlr-plan-docs-toggle-icon::after { width: 2px; height: 12px; transition: opacity 0.2s ease; }
        .mlr-plan-docs-accordion[data-state="expanded"] .mlr-plan-docs-toggle-icon { transform: rotate(180deg); }
        .mlr-plan-docs-accordion[data-state="expanded"] .mlr-plan-docs-toggle-icon::after { opacity: 0; }
        .mlr-plan-docs-panel { padding: 16px; background: #f7fbff; border-top: 1px solid #c9dced; }
        .mlr-plan-docs-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .mlr-plan-info-accordions .mlr-plan-docs-list { grid-template-columns: 1fr; }
        .mlr-plan-doc-item { display: flex; align-items: center; gap: 9px; min-width: 0; min-height: 44px; padding: 10px 12px; border: 1px solid #d3e4f4; border-radius: 10px; background: #ffffff; color: #1d3558; font-size: 13px; font-weight: 700; line-height: 1.6; }
        .mlr-plan-doc-item.has-description { cursor: help; }
        .mlr-plan-doc-item.has-description:focus { outline: 2px solid #1d3558; outline-offset: 2px; }
        .mlr-plan-doc-text { display: flex; flex: 1; min-width: 0; flex-direction: column; }
        .mlr-plan-doc-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mlr-plan-doc-description { display: none; margin-top: 2px; color: #476582; font-size: 12px; font-weight: 500; line-height: 1.7; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mlr-plan-doc-item.has-description:hover .mlr-plan-doc-description,
        .mlr-plan-doc-item.has-description:focus .mlr-plan-doc-description { display: block; }
        .mlr-plan-doc-hint { display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; min-width: 20px; border-radius: 999px; background: #d0e2f2; color: #1d3558; font-size: 12px; font-weight: 900; }
        .mlr-plan-doc-check { width: 18px; height: 18px; min-width: 18px; border-radius: 999px; background: #1d3558; position: relative; }
        .mlr-plan-doc-check::after { content: ""; position: absolute; width: 8px; height: 4px; border-right: 2px solid #d0e2f2; border-bottom: 2px solid #d0e2f2; top: 5px; right: 4px; transform: rotate(45deg); }
        .mlr-plan-docs-status,
        .mlr-plan-docs-empty { padding: 12px 14px; border-radius: 10px; background: #ffffff; color: #476582; border: 1px dashed #b9cde0; font-size: 13px; line-height: 1.8; }
        .mlr-plan-docs-status.is-error { background: #fce8e6; color: #c5221f; border-color: #f6c9c5; }
        .mlr-plan-conditions-content { color: #1d3558; font-size: 13px; line-height: 1.9; }
        .mlr-plan-conditions-body { padding: 12px 14px; border: 1px solid #d3e4f4; border-radius: 10px; background: #ffffff; }
        .mlr-plan-conditions-body p { margin: 0 0 10px; }
        .mlr-plan-conditions-body p:last-child { margin-bottom: 0; }
        .mlr-plan-conditions-body ul,
        .mlr-plan-conditions-body ol { margin: 0; padding-right: 20px; }
        @media (max-width: 640px) { .mlr-plan-info-accordions { grid-template-columns: 1fr; } .mlr-plan-docs-list { grid-template-columns: 1fr; } .mlr-plan-doc-description { white-space: normal; } }
    </style>
    <?php
    return ob_get_clean();
}
