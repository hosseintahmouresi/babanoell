<?php
add_shortcode('mlr_user_stats', 'mlr_user_stats_shortcode');

// Register the style handle once when the plugin loads
add_action('wp_enqueue_scripts', 'mlr_register_user_stats_styles_v6');
function mlr_register_user_stats_styles_v6() {
    // Register a handle for the inline styles
    wp_register_style('mlr-user-stats-inline-style-v6', false);
    wp_enqueue_style('mlr-user-stats-inline-style-v6');

    // Define the updated CSS rules - Matching the provided image exactly
    $user_stats_css_v6 = "
        /* Styles for User Stats Shortcode - v6 (Image Match) */
        .mlr-user-stats-wrapper-v6 * {
            box-sizing: border-box !important;
            margin: 0 !important; /* Reset margin */
            padding: 0 !important; /* Reset padding */
        }
        .mlr-user-stats-wrapper-v6 {
            padding: 10px 0 !important;
            overflow: hidden !important;
            width: 100% !important;
        }
        .mlr-user-stats-wrapper-v6 .mlr-stats-container {
            display: grid !important;
            grid-template-columns: repeat(1, 1fr) !important; /* Default: 1 column */
            gap: 25px !important;
            font-family: 'Vazirmatn', 'IRANSans', 'Tahoma', sans-serif !important;
            direction: rtl !important;
            width: 100% !important;
            max-width: 100% !important;
            margin: 25px 0 !important;
            padding: 0 !important;
            border: none !important;
            background: none !important;
        }
        /* Media query for 2 columns */
        @media (min-width: 576px) {
            .mlr-user-stats-wrapper-v6 .mlr-stats-container {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }
        /* Media query for 3 columns */
        @media (min-width: 992px) {
            .mlr-user-stats-wrapper-v6 .mlr-stats-container {
                grid-template-columns: repeat(3, 1fr) !important;
            }
        }

        .mlr-user-stats-wrapper-v6 .mlr-stat-card {
            background: #ffffff !important;
            padding: 0 !important;
            border-radius: 24px !important; /* More rounded corners like image */
            text-align: center !important;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.07) !important; /* Soft shadow */
            border: 1px solid #f0f0f5 !important; /* Lighter border */
            transition: transform 0.2s ease-out, box-shadow 0.2s ease-out !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: stretch !important;
            justify-content: flex-start !important;
            min-height: auto !important;
            position: relative !important;
            overflow: hidden !important;
        }
        .mlr-user-stats-wrapper-v6 .mlr-stat-card:hover {
             transform: translateY(-4px) !important;
             box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1) !important;
        }
        /* Top Orange Section */
        .mlr-user-stats-wrapper-v6 .mlr-card-top-accent {
            background-color: #FFA500 !important; /* Solid Orange color */
            padding: 25px 20px !important; /* Padding for the orange area */
            border-radius: 20px !important; /* Rounded corners for the orange part */
            margin: 10px 10px 0 10px !important; /* Inset effect */
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        .mlr-user-stats-wrapper-v6 .mlr-stat-card .icon-container {
            line-height: 0 !important; /* Remove extra space for img */
            margin: 0 !important;
            padding: 0 !important;
            border: none !important;
            background: none !important;
            filter: none !important;
            height: 40px !important; /* Set fixed height for icon container */
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        .mlr-user-stats-wrapper-v6 .mlr-stat-card .icon-container img {
             width: 40px !important; /* Control SVG size */
             height: 40px !important;
             display: block !important;
             margin: 0 !important;
             padding: 0 !important;
        }
        /* Content Area Below Orange */
        .mlr-user-stats-wrapper-v6 .mlr-card-content-area {
            padding: 25px 20px 30px 20px !important; /* Padding for the white area */
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
            flex-grow: 1 !important;
            background: #ffffff !important;
        }
        /* Line containing number and unit */
        .mlr-user-stats-wrapper-v6 .mlr-stat-card .value-line {
            display: flex !important;
            align-items: baseline !important;
            justify-content: center !important;
            gap: 8px !important; /* Slightly more gap */
            margin-bottom: 10px !important; /* Space below value */
            color: #1a202c !important; /* Dark text */
            line-height: 1.2 !important;
        }
        .mlr-user-stats-wrapper-v6 .mlr-stat-card .value-number {
            font-size: 34px !important; /* Large number like image */
            font-weight: 700 !important;
        }
        .mlr-user-stats-wrapper-v6 .mlr-stat-card .value-unit {
            font-size: 18px !important; /* Adjusted unit size */
            font-weight: 600 !important;
            color: #1a202c !important; /* Dark unit */
        }
        /* Label Styling */
        .mlr-user-stats-wrapper-v6 .mlr-stat-card .label {
            display: block !important;
            font-size: 14px !important; /* Label size like image */
            color: #6b7280 !important; /* Gray subtitle */
            line-height: 1.5 !important;
            max-width: 90% !important;
            margin: 0 auto !important;
            padding: 0 !important;
            border: none !important;
            background: none !important;
        }
        /* Remove pseudo element */
        .mlr-user-stats-wrapper-v6 .mlr-stat-card::before {
            display: none !important;
        }
    ";
    // Add the new CSS using wp_add_inline_style
    wp_add_inline_style('mlr-user-stats-inline-style-v6', $user_stats_css_v6);

    // No need to enqueue Dashicons if using img
    // wp_enqueue_style('dashicons');
}


function mlr_user_stats_shortcode() {
    if (!is_user_logged_in()) {
        return '<p class="mlr-error">لطفاً ابتدا وارد شوید.</p>';
    }

    // --- Database Queries (Same as before) ---
    global $wpdb;
    $table_name = $wpdb->prefix . 'loan_requests';
    $user_id = get_current_user_id();
    $start_of_month = date_i18n('Y-m-01 00:00:00');
    $now = current_time('mysql');

    $monthly_requests = $wpdb->get_row($wpdb->prepare( "SELECT COUNT(*) as count, SUM(loan_amount) as total_amount FROM $table_name WHERE user_id = %d AND created_at >= %s AND created_at <= %s", $user_id, $start_of_month, $now));
    $monthly_count = $monthly_requests->count ?: 0;
    $monthly_amount = $monthly_requests->total_amount ?: 0;
    $pending_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND status = %s", $user_id, 'در حال بررسی')) ?: 0;
    $granted_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND status = %s", $user_id, 'اعطا شده')) ?: 0;
    $total_amount = $wpdb->get_var($wpdb->prepare("SELECT SUM(loan_amount) FROM $table_name WHERE user_id = %d", $user_id )) ?: 0;

    // --- Prepare Data (Modified structure + Specific Icon URL) ---
    // Using a placeholder icon URL for all cards based on your link.
    // If different icons are needed per card, this array needs modification.
    $icon_url = 'https://babanoell.com/wp-content/plugins/mihanpanel/img/cup.svg';

    $stats_data = [
        [ 'icon_url' => $icon_url, 'number' => esc_html(number_format_i18n($monthly_count)), 'unit' => 'درخواست', 'label' => 'درخواست‌های این ماه' ],
        [ 'icon_url' => $icon_url, 'number' => esc_html(number_format_i18n($monthly_amount)), 'unit' => 'ریال', 'label' => 'مبلغ درخواست‌های این ماه' ],
        [ 'icon_url' => $icon_url, 'number' => esc_html(number_format_i18n($pending_count)), 'unit' => 'درخواست', 'label' => 'درخواست‌های در حال بررسی' ],
        [ 'icon_url' => $icon_url, 'number' => esc_html(number_format_i18n($granted_count)), 'unit' => 'درخواست', 'label' => 'درخواست‌های اعطا شده' ],
        [ 'icon_url' => $icon_url, 'number' => esc_html(number_format_i18n($total_amount)), 'unit' => 'ریال', 'label' => 'مبلغ کل درخواست‌ها' ],
    ];

    // --- Enqueue the registered style ---
    wp_enqueue_style('mlr-user-stats-inline-style-v6');

    // --- Generate HTML Output (Using img tag) ---
    ob_start();
    ?>
    <div class="mlr-user-stats-wrapper-v6">
        <div class="mlr-stats-container">
            <?php foreach ($stats_data as $stat): ?>
                <div class="mlr-stat-card">
                    <div class="mlr-card-top-accent">
                         <div class="icon-container">
                             <img src="<?php echo esc_url($stat['icon_url']); ?>" alt="">
                         </div>
                    </div>
                    <div class="mlr-card-content-area">
                        <div class="value-line">
                             <span class="value-number"><?php echo $stat['number']; ?></span>
                             <span class="value-unit"><?php echo esc_html($stat['unit']); ?></span>
                        </div>
                        <span class="label"><?php echo esc_html($stat['label']); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>