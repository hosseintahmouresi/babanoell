<?php
add_shortcode('mlr_invoice', 'mlr_invoice_shortcode');
function mlr_invoice_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<p>لطفاً ابتدا وارد شوید.</p>';
    }

    $atts = shortcode_atts(['id' => 0], $atts, 'mlr_invoice');
    $request_id = absint($atts['id']);

    if (!$request_id) {
        return '<p>شناسه درخواست معتبر نیست.</p>';
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'loan_requests';
    $plans_table = $wpdb->prefix . 'installment_plans';

    $request = $wpdb->get_row($wpdb->prepare(
        "SELECT r.*, p.name AS plan_name FROM {$table_name} r LEFT JOIN {$plans_table} p ON r.installment_plan_id = p.id WHERE r.id = %d",
        $request_id
    ));

    if (!$request) {
        return '<p>درخواست یافت نشد.</p>';
    }

    // Helper for Persian numbers
    $to_persian = function($str) {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        return str_replace($english, $persian, (string)$str);
    };

    $acceptor_user_data = null;
    if (!empty($request->user_id)) {
        $acceptor_user_data = get_userdata($request->user_id);
    }

    // Facilitator (System) info - can be overridden via filter
    $default_facilitator = [
        'title'       => 'مشخصات تسهیل‌گر',
        'name'        => 'آرمان فراز هیوا (بابانوئل)',
        'economic'    => '۱۴۰۱۱۱۲۶۶۵۳',
        'national_id' => '۱۴۰۱۱۱۲۶۶۵۳',
        'reg'         => '۷۰۹۳۵',
        'address'     => 'اصفهان - بلوار کشاورز - مجتمع اداری ملل - طبقه ۱ - واحد ۱۰۴',
        'postal'      => '۸۱۷۶۹۹۵۵۹۵',
        'phone'       => '91001055 - 031',
        'province'    => 'اصفهان',
        'city'        => 'اصفهان',
        'logo_url'    => '',
    ];
    $facilitator = apply_filters('mlr_invoice_facilitator_info', $default_facilitator, $request);

    // Seller/Initiator (Acceptor) info - can be overridden via filter
    $shop_name   = '';
    $sheba       = '';
    $address     = '';
    $postal      = '';
    $economic    = '';
    $national_id = '';
    $reg         = '';
    $phone       = '';

    if ($acceptor_user_data) {
        $uid = intval($acceptor_user_data->ID);
        $shop_name   = get_user_meta($uid, 'shopppp', true);
        $sheba       = get_user_meta($uid, 'shebaaa', true);
        $address     = get_user_meta($uid, 'adddresss', true);
        $postal      = get_user_meta($uid, 'postiii', true);
        $economic    = get_user_meta($uid, 'eghtesadiii', true);
        $national_id = get_user_meta($uid, 'meliii', true);
        $reg         = get_user_meta($uid, 'sabttt', true);

        // Try common phone meta keys if exist
        $phone = get_user_meta($uid, 'acceptor_mobile_number', true);
        if (empty($phone)) $phone = get_user_meta($uid, 'mobile', true);
        if (empty($phone)) $phone = get_user_meta($uid, 'phone', true);
        if (empty($phone)) $phone = get_user_meta($uid, 'billing_phone', true);
    }

    $default_acceptor = [
        'title'       => 'مشخصات فروشنده',
        'name'        => !empty($shop_name) ? $shop_name : ($acceptor_user_data ? $acceptor_user_data->display_name : '-'),
        'economic'    => !empty($economic) ? $economic : '-',
        'national_id' => !empty($national_id) ? $national_id : '-',
        'reg'         => !empty($reg) ? $reg : '-',
        'address'     => !empty($address) ? $address : '-',
        'postal'      => !empty($postal) ? $postal : '-',
        'phone'       => !empty($phone) ? $phone : '-',
        'province'    => '-',
        'city'        => '-',
        'sheba'       => !empty($sheba) ? $sheba : '-',
    ];
    $acceptor = apply_filters('mlr_invoice_acceptor_info', $default_acceptor, $request, $acceptor_user_data);

    // Buyer (Applicant) info - stored on request
    $buyer = apply_filters('mlr_invoice_buyer_info', [
        'title'       => 'مشخصات خریدار',
        'name'        => $request->full_name ?? '-',
        'economic'    => $request->national_code ?? '-',
        'national_id' => $request->national_code ?? '-',
        'reg'         => '-',
        'address'     => $request->residential_address ?? '-',
        'postal'      => $request->postal_code ?? '-',
        'phone'       => $request->mobile_number ?? ($request->phone_number ?? '-'),
        'province'    => '',
        'city'        => '',
    ], $request);

    // Invoice meta
    $invoice_number = apply_filters('mlr_invoice_number', strval($request->id), $request);
    $invoice_date = !empty($request->created_at) ? mlr_gregorian_to_jalali($request->created_at, 'Y/m/d') : '';
    $invoice_date = apply_filters('mlr_invoice_date', $invoice_date, $request);

    // Items
    $items = [];
    if (!empty($request->order_items)) {
        $decoded = json_decode($request->order_items, true);
        if (is_array($decoded)) {
            $items = $decoded;
        }
    }

    // Normalize items for rendering (support both submit-loan-request and update-invoice formats)
    $normalized_items = [];
    if (is_array($items)) {
        foreach ($items as $it) {
            if (!is_array($it)) continue;

            $code = $it['code'] ?? ($it['id'] ?? '-');
            $desc = $it['description'] ?? ($it['name'] ?? '-');
            $qty  = isset($it['quantity'])
                ? intval($it['quantity'])
                : (isset($it['qty']) ? intval($it['qty']) : (isset($it['order_qty']) ? intval($it['order_qty']) : 1));
            if ($qty <= 0) $qty = 1;

            $unit_price = 0;
            if (isset($it['unit_price'])) {
                $unit_price = floatval($it['unit_price']);
            } elseif (isset($it['price'])) {
                $unit_price = floatval($it['price']);
            }

            $total_price = 0;
            if (isset($it['total_price'])) {
                $total_price = floatval($it['total_price']);
            } elseif (isset($it['total'])) {
                $total_price = floatval($it['total']);
            } else {
                $total_price = $qty * $unit_price;
            }

            $normalized_items[] = [
                'code'       => $code !== '' ? $code : '-',
                'description'=> $desc !== '' ? $desc : '-',
                'quantity'   => $qty,
                'unit_price' => $unit_price,
                'total_price'=> $total_price,
                'unit'      => isset($it['unit']) ? sanitize_text_field($it['unit']) : '',
                'meta'      => $it['meta'] ?? '',
                'discount'  => isset($it['discount']) ? floatval($it['discount']) : 0,
                'tax'       => isset($it['tax']) ? floatval($it['tax']) : 0,
            ];
        }
    }

    if (empty($normalized_items)) {
        $normalized_items = [[
            'code' => '-',
            'description' => !empty($request->product_name) ? $request->product_name : 'کالا / خدمات',
            'quantity' => 1,
            'unit_price' => floatval($request->loan_amount ?? 0),
            'total_price' => floatval($request->loan_amount ?? 0),
            'unit' => '',
            'meta' => '',
            'discount' => 0,
            'tax' => 0,
        ]];
    }
// Calculate Grand Total based on the last column (Total with Tax)
    $grand_total_calculated = 0;
    foreach ($normalized_items as $it) {
        $unit_price = floatval($it['unit_price'] ?? 0);
        $qty = isset($it['quantity']) ? floatval($it['quantity']) : 1;
        $total_price = floatval($it['total_price'] ?? ($unit_price * $qty));
        $discount = floatval($it['discount'] ?? 0);
        $after_discount = max(0, $total_price - $discount);
        $tax = floatval($it['tax'] ?? 0);
        $with_tax = $after_discount + $tax;
        
        $grand_total_calculated += $with_tax;
    }
    $grand_total = $grand_total_calculated;

    ob_start();
    ?>
    <style>
        .mlr-invoice-wrapper{direction:rtl;font-family:tahoma,arial,sans-serif;color:#111;max-width:900px;margin:0 auto}
        .mlr-invoice-header{display:flex;justify-content:space-between;align-items:center;margin:10px 0 6px 0}
        .mlr-invoice-title{font-size:16px;font-weight:800;text-align:center;flex:1}
        .mlr-invoice-logo{width:120px;text-align:left}
        .mlr-invoice-logo img{max-width:120px;height:auto}
        .mlr-invoice-meta{width:220px;font-size:12px;line-height:1.8}
        .mlr-box{border:1pt solid #000;border-collapse:collapse;width:100%}
        .mlr-box th{border:1pt solid #000;padding:6px;text-align:center;font-size:12px}
        .mlr-box td{border:1pt solid #000;padding:6px;font-size:11px;vertical-align:top}
        .mlr-subtable{width:100%;border-collapse:collapse}
        .mlr-subtable td{border:0;padding:2px 0;font-size:11px}
        .mlr-items{width:100%;border-collapse:collapse;margin-top:10px}
        .mlr-items th,.mlr-items td{border:1pt solid #000;padding:5px;font-size:10px;text-align:center}
        .mlr-items thead th{font-size:9px}
        .mlr-items tfoot td{font-weight:800;background:#f2f2f2}
        .mlr-sign{width:100%;border-collapse:collapse;margin-top:10px}
        .mlr-sign td{border:1pt solid #000;padding:10px;font-size:11px;height:70px;vertical-align:top}
        .mlr-note{margin-top:8px;font-size:11px;line-height:1.9}
        .mlr-print-btn{margin:10px 0;display:inline-block}
        @media print{
            .mlr-print-btn{display:none!important}
            .mlr-invoice-wrapper{max-width:none;margin:0}
        }
    </style>

    <div class="mlr-invoice-wrapper">
        <div class="mlr-invoice-header">
            <div class="mlr-invoice-meta">
                <div><b>شماره:</b> <?php echo esc_html($to_persian($invoice_number)); ?></div>
                <div><b>تاریخ:</b> <?php echo esc_html($to_persian($invoice_date)); ?></div>
            </div>

            <div class="mlr-invoice-title">فاکتور فروش کالا و خدمات</div>

            <div class="mlr-invoice-logo">
                <?php if (!empty($facilitator['logo_url'])): ?>
                    <img src="<?php echo esc_url($facilitator['logo_url']); ?>" alt="Logo">
                <?php endif; ?>
            </div>
        </div>

        <table class="mlr-box" cellspacing="0">
            <tr><th><?php echo esc_html($facilitator['title']); ?></th></tr>
            <tr>
                <td>
                    <table class="mlr-subtable">
                        <tr>
                            <td style="width:33%"><b>نام:</b> <?php echo esc_html($facilitator['name']); ?></td>
                            <td style="width:33%"><b>شماره اقتصادی:</b> <?php echo esc_html($to_persian($facilitator['economic'])); ?></td>
                            <td style="width:33%"><b>شماره / شناسه ملی:</b> <?php echo esc_html($to_persian($facilitator['national_id'])); ?></td>
                        </tr>
                        <tr>
                            <td style="width:33%"><b>شماره ثبت:</b> <?php echo esc_html($to_persian($facilitator['reg'])); ?></td>
                            <td colspan="2"><b>نشانی:</b> <?php echo esc_html($facilitator['address']); ?></td>
                        </tr>
                        <tr>
                            <td style="width:33%"><b>کد پستی:</b> <?php echo esc_html($to_persian($facilitator['postal'])); ?></td>
                            <td style="width:33%"><b>شماره تماس:</b> <?php echo esc_html($to_persian($facilitator['phone'])); ?></td>
                            <td style="width:33%"></td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr><th><?php echo esc_html($acceptor['title']); ?></th></tr>
            <tr>
                <td>
                    <table class="mlr-subtable">
                        <tr>
                            <td style="width:33%"><b>نام:</b> <?php echo esc_html($acceptor['name']); ?></td>
                            <td style="width:33%"><b>شماره اقتصادی:</b> <?php echo esc_html($to_persian($acceptor['economic'])); ?></td>
                            <td style="width:33%"><b>شماره / شناسه ملی:</b> <?php echo esc_html($to_persian($acceptor['national_id'])); ?></td>
                        </tr>
                        <tr>
                            <td style="width:33%"><b>شماره ثبت:</b> <?php echo esc_html($to_persian($acceptor['reg'])); ?></td>
                            <td colspan="2"><b>نشانی:</b> <?php echo esc_html($acceptor['address']); ?></td>
                        </tr>
                        <tr>
                            <td style="width:33%"><b>کد پستی:</b> <?php echo esc_html($to_persian($acceptor['postal'])); ?></td>
                            <td style="width:33%"><b>شماره تماس:</b> <?php echo esc_html($to_persian($acceptor['phone'])); ?></td>
                            <td style="width:33%"></td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr><th><?php echo esc_html($buyer['title']); ?></th></tr>
            <tr>
                <td>
                    <table class="mlr-subtable">
                        <tr>
                            <td style="width:33%"><b>نام:</b> <?php echo esc_html($buyer['name']); ?></td>
                            <td style="width:33%"><b>شماره اقتصادی:</b> <?php echo esc_html($to_persian($buyer['economic'])); ?></td>
                            <td style="width:33%"><b>شناسه/کد ملی:</b> <?php echo esc_html($to_persian($buyer['national_id'])); ?></td>
                        </tr>
                        <tr>
                            <td style="width:33%"><b>شماره ثبت:</b> <?php echo esc_html($to_persian($buyer['reg'])); ?></td>
                            <td colspan="2"><b>نشانی:</b> <?php echo esc_html($buyer['address']); ?></td>
                        </tr>
                        <tr>
                            <td style="width:33%"><b>کد پستی:</b> <?php echo esc_html($to_persian($buyer['postal'])); ?></td>
                            <td style="width:33%"><b>شماره تماس:</b> <?php echo esc_html($to_persian($buyer['phone'])); ?></td>
                            <td style="width:33%"></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <p style="margin:10px 0 6px 0; font-size: 12px;">
            <b>مبلغ درخواستی:</b> <?php echo $to_persian(number_format((int)$request->loan_amount)); ?> ریال
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <b>مبلغ تسهیلات:</b> <?php echo $to_persian(isset($request->facility_amount) && $request->facility_amount !== '' ? number_format((int)$request->facility_amount) : number_format((int)$request->loan_amount)); ?> ریال
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <b>فاصله اقساط (تناوب):</b> <?php echo isset($request->installment_interval) && $request->installment_interval !== '' ? $to_persian($request->installment_interval) . ' ماه' : '—'; ?>
        </p>

        <p style="font-weight:800;margin:10px 0 6px 0">مشخصات کالا یا خدمات مورد معامله</p>

        <table class="mlr-items" cellspacing="0">
            <thead>
                <tr>
                    <th style="width:3%">ردیف</th>
                    <th style="width:6%">کد کالا</th>
                    <th style="width:24%">شرح کالا یا خدمات</th>
                    <th style="width:6%">تعداد/مقدار</th>
                    <th style="width:6%">واحد</th>
                    <th style="width:12%">مبلغ واحد (ریال)</th>
                    <th style="width:12%">مبلغ کل (ریال)</th>
                    <th style="width:8%">تخفیف</th>
                    <th style="width:12%">مبلغ پس از تخفیف (ریال)</th>
                    <th style="width:6%">مالیات و عوارض</th>
                    <th style="width:12%">جمع کل با مالیات (ریال)</th>
                </tr>
            </thead>
            <tbody>
                <?php $i=1; foreach ($normalized_items as $it): 
                    $code = isset($it['code']) ? $it['code'] : '-';
                    $desc = isset($it['description']) ? $it['description'] : '-';
                    $qty = isset($it['quantity']) ? $it['quantity'] : 1;
                    $unit = isset($it['unit']) && $it['unit'] !== '' ? $it['unit'] : 'عدد';
                    $meta = $it['meta'] ?? '';
                    $meta_lines = [];
                    if (is_array($meta)) {
                        foreach ($meta as $mk=>$mv) {
                            if (is_array($mv)) continue;
                            $mk_s = is_string($mk) ? trim((string)$mk) : '';
                            $mv_s = trim((string)$mv);
                            if ($mv_s === '') continue;
                            $meta_lines[] = ($mk_s !== '' ? ($mk_s . ': ' . $mv_s) : $mv_s);
                        }
                    } else {
                        $mstr = trim((string)$meta);
                        if ($mstr !== '') $meta_lines[] = $mstr;
                    }
                    $meta_html = '';
                    if (!empty($meta_lines)) {
                        $meta_html = '<div class="mlr-invoice-meta" style="font-size:12px;opacity:.8;margin-top:4px;">' . esc_html(implode(' | ', $meta_lines)) . '</div>';
                    }
                    $unit_price = floatval($it['unit_price'] ?? 0);
                    $total_price = floatval($it['total_price'] ?? ($unit_price * floatval($qty)));
                    $discount = floatval($it['discount'] ?? 0);
                    $after_discount = max(0, $total_price - $discount);
                    $tax = floatval($it['tax'] ?? 0);
                    $with_tax = $after_discount + $tax;
                ?>
                <tr>
                    <td><?php echo esc_html($to_persian($i)); ?></td>
                    <td><?php echo esc_html($to_persian($code)); ?></td>
                    <td style="text-align:right"><?php echo esc_html($desc); ?><?php echo $meta_html; ?></td>
                    <td><?php echo esc_html($to_persian($qty)); ?></td>
                    <td><?php echo esc_html($unit); ?></td>
                    <td><?php echo esc_html($to_persian(number_format($unit_price))); ?></td>
                    <td><?php echo esc_html($to_persian(number_format($total_price))); ?></td>
                    <td><?php echo esc_html($to_persian(number_format($discount))); ?></td>
                    <td><?php echo esc_html($to_persian(number_format($after_discount))); ?></td>
                    <td><?php echo esc_html($to_persian(number_format($tax))); ?></td>
                    <td><?php echo esc_html($to_persian(number_format($with_tax))); ?></td>
                </tr>
                <?php $i++; endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="11" style="text-align:right">جمع کل: <?php echo esc_html($to_persian(number_format($grand_total))); ?> ریال</td>
                </tr>
            </tfoot>
        </table>

        <div class="mlr-note">
            اینجانب <b><?php echo esc_html($buyer['name']); ?></b> به کد ملی <b><?php echo esc_html($to_persian($buyer['national_id'])); ?></b> به تاریخ <b><?php echo esc_html($to_persian($invoice_date)); ?></b> کالا / خدمات فوق را از فروشنده صحیح و سالم تحویل گرفته‌ام.
        </div>

        <table class="mlr-sign" cellspacing="0">
            <tr>
                <td style="width:33.33%"><b>مهر و امضاء تسهیل‌گر:</b></td>
                <td style="width:33.33%"><b>مهر و امضاء فروشنده:</b></td>
                <td style="width:33.33%"><b>امضاء و اثر انگشت خریدار:</b></td>
            </tr>
        </table>
</div>
    <?php
    return ob_get_clean();
}
