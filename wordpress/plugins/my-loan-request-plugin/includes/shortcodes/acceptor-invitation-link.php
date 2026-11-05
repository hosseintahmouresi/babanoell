<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('mlr_acceptor_invitation_link', 'mlr_acceptor_invitation_link_shortcode');

function mlr_acceptor_invitation_link_shortcode($atts = []) {
    $atts = shortcode_atts([
        'acceptor_id' => 0,
    ], $atts, 'mlr_acceptor_invitation_link');

    $settings = function_exists('mlr_get_acceptor_marketplace_settings') ? mlr_get_acceptor_marketplace_settings() : [];
    if (empty($settings['enable_acceptor_invitation_links'])) {
        return '<p>لینک اختصاصی ثبت درخواست در حال حاضر فعال نیست.</p>';
    }

    if (!is_user_logged_in()) {
        return '<p>برای مشاهده لینک اختصاصی ثبت درخواست، لطفاً ابتدا وارد شوید.</p>';
    }

    $acceptor_id = absint($atts['acceptor_id']);
    if ($acceptor_id > 0) {
        if (!current_user_can('manage_options')) {
            return '<p>دسترسی به لینک این پذیرنده مجاز نیست.</p>';
        }
    } else {
        $acceptor_id = get_current_user_id();
    }

    if ($acceptor_id <= 0 || !function_exists('mlr_user_has_role') || !mlr_user_has_role($acceptor_id, 'acceptor')) {
        return '<p>برای دریافت لینک اختصاصی باید حساب پذیرنده معتبر باشد.</p>';
    }

    $code = function_exists('mlr_get_or_create_acceptor_invitation_code') ? mlr_get_or_create_acceptor_invitation_code($acceptor_id) : '';
    $url = function_exists('mlr_get_acceptor_invitation_url') ? mlr_get_acceptor_invitation_url($acceptor_id) : '';
    if ($code === '' || $url === '') {
        return '<p>امکان ساخت لینک اختصاصی ثبت درخواست وجود ندارد.</p>';
    }

    $uid = uniqid('mlr-invite-');
    ob_start();
    ?>
    <div class="mlr-acceptor-invitation-link" id="<?php echo esc_attr($uid); ?>" dir="rtl">
        <h3>لینک اختصاصی ثبت درخواست</h3>
        <div class="mlr-invite-row">
            <label for="<?php echo esc_attr($uid); ?>-url">لینک اختصاصی ثبت درخواست</label>
            <input id="<?php echo esc_attr($uid); ?>-url" type="text" readonly value="<?php echo esc_attr($url); ?>">
            <button type="button" class="mlr-copy-invite" data-copy-target="<?php echo esc_attr($uid); ?>-url">کپی لینک</button>
        </div>
        <div class="mlr-invite-row">
            <label for="<?php echo esc_attr($uid); ?>-code">کد اختصاصی</label>
            <input id="<?php echo esc_attr($uid); ?>-code" type="text" readonly value="<?php echo esc_attr($code); ?>">
            <button type="button" class="mlr-copy-invite" data-copy-target="<?php echo esc_attr($uid); ?>-code">کپی کد</button>
        </div>
        <span class="mlr-invite-copy-msg" aria-live="polite"></span>
    </div>
    <style>
        #<?php echo esc_attr($uid); ?> { max-width: 720px; margin: 20px auto; background:#fff; border:1px solid #d8dee8; border-radius:8px; padding:18px; }
        #<?php echo esc_attr($uid); ?> .mlr-invite-row { display:grid; grid-template-columns: 150px minmax(0, 1fr) auto; gap:10px; align-items:center; margin-top:12px; }
        #<?php echo esc_attr($uid); ?> input { width:100%; direction:ltr; text-align:left; padding:10px; border:1px solid #ccd0d4; border-radius:6px; }
        #<?php echo esc_attr($uid); ?> button { cursor:pointer; border:0; border-radius:6px; padding:10px 14px; background:#1D3558; color:#fff; }
        #<?php echo esc_attr($uid); ?> .mlr-invite-copy-msg { display:block; margin-top:10px; color:#008a20; }
        @media (max-width: 640px) {
            #<?php echo esc_attr($uid); ?> .mlr-invite-row { grid-template-columns: 1fr; }
        }
    </style>
    <script>
    (function(){
        var root = document.getElementById('<?php echo esc_js($uid); ?>');
        if (!root) return;
        root.addEventListener('click', function(event) {
            var button = event.target.closest('.mlr-copy-invite');
            if (!button) return;
            var input = document.getElementById(button.getAttribute('data-copy-target'));
            var msg = root.querySelector('.mlr-invite-copy-msg');
            if (!input) return;
            input.select();
            input.setSelectionRange(0, input.value.length);
            var done = function(){ if (msg) msg.textContent = 'کپی شد.'; };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(input.value).then(done).catch(function(){ document.execCommand('copy'); done(); });
            } else {
                document.execCommand('copy');
                done();
            }
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
