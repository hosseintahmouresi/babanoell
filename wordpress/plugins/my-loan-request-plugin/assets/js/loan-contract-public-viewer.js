(function($){
    'use strict';
    var cfg = window.mlr_loan_contract_public_ajax || {};
    var timerId = null;

    function digits(value) {
        var fa = '۰۱۲۳۴۵۶۷۸۹', ar = '٠١٢٣٤٥٦٧٨٩';
        return String(value || '').replace(/[۰-۹٠-٩]/g, function(ch){
            var i = fa.indexOf(ch);
            if (i < 0) i = ar.indexOf(ch);
            return i >= 0 ? String(i) : ch;
        }).replace(/\D+/g, '').slice(0, 6);
    }

    function message($root, type, text) {
        var $box = $root.find('.mlr-public-loan-message');
        $box.removeClass('success error loading').addClass(type || '').text(text || '').show();
    }

    function startTimer($root, seconds) {
        if (timerId) clearInterval(timerId);
        var remaining = Math.max(1, parseInt(seconds, 10) || 120);
        var $send = $root.find('.mlr-public-loan-send-otp');
        var $timer = $root.find('.mlr-public-loan-timer');
        function render(){
            if (remaining > 0) {
                $send.prop('disabled', true).text('ارسال مجدد');
                $timer.text('ارسال مجدد تا ' + remaining + ' ثانیه دیگر');
                remaining--;
            } else {
                clearInterval(timerId); timerId = null;
                $timer.text('');
                $send.text('ارسال کد جدید').prop('disabled', !$root.find('.mlr-public-loan-consent').is(':checked'));
            }
        }
        render();
        timerId = setInterval(render, 1000);
    }


    $(document).on('click', '.mlr-loan-contract-isolated-print', function(e){
        e.preventDefault();
        var $root = $(this).closest('.mlr-loan-public-contract');
        var target = $root.find('.mlr-print-layout-table').get(0) || $root.get(0);
        if (typeof window.mlrPrintContractNode === 'function') {
            window.mlrPrintContractNode(target, {title: 'قرارداد دریافت تسهیلات'});
            return;
        }
        window.alert('ماژول چاپ قرارداد بارگذاری نشده است. صفحه را یک‌بار تازه‌سازی کنید.');
    });

    $(document).on('change', '.mlr-public-loan-consent', function(){
        var $root = $(this).closest('.mlr-loan-public-contract');
        if (!timerId) $root.find('.mlr-public-loan-send-otp').prop('disabled', !$(this).is(':checked'));
    });

    $(document).on('input', '.mlr-public-loan-otp', function(){
        $(this).val(digits($(this).val()));
    });

    $(document).on('keydown', '.mlr-public-loan-otp', function(e){
        if (e.key === 'Enter') {
            e.preventDefault();
            $(this).closest('.mlr-loan-public-contract').find('.mlr-public-loan-verify-otp').trigger('click');
        }
    });

    $(document).on('click', '.mlr-public-loan-send-otp', function(){
        var $btn = $(this), $root = $btn.closest('.mlr-loan-public-contract');
        if (!$root.find('.mlr-public-loan-consent').is(':checked')) {
            message($root, 'error', 'ابتدا مطالعه و پذیرش متن قرارداد را تأیید کنید.');
            return;
        }
        if (!cfg.ajax_url || !cfg.nonce) {
            message($root, 'error', 'تنظیمات ارتباط با سرور در دسترس نیست.');
            return;
        }
        $btn.prop('disabled', true).text('در حال ارسال...');
        message($root, 'loading', 'در حال ارسال کد تأیید...');
        $.post(cfg.ajax_url, {
            action: 'mlr_send_public_loan_contract_otp',
            nonce: cfg.nonce,
            contract_token: $root.attr('data-loan-contract-token') || '',
            accepted: '1'
        }, null, 'json').done(function(res){
            var data = res && res.data ? res.data : {};
            if (res && res.success) {
                if (data.signed) { window.location.reload(); return; }
                $root.find('.mlr-public-loan-otp-panel').show();
                $root.find('.mlr-public-loan-otp').prop('disabled', false).focus();
                message($root, 'success', data.message || 'کد تأیید ارسال شد.');
                startTimer($root, data.cooldown || 120);
                return;
            }
            message($root, 'error', data.message || 'ارسال کد تأیید انجام نشد.');
            if (data.otp_active) {
                $root.find('.mlr-public-loan-otp-panel').show();
                startTimer($root, data.cooldown || 1);
            } else {
                $btn.text('ارسال کد تأیید').prop('disabled', !$root.find('.mlr-public-loan-consent').is(':checked'));
            }
        }).fail(function(){
            message($root, 'error', 'ارتباط با سرور برای ارسال کد برقرار نشد.');
            $btn.text('ارسال کد تأیید').prop('disabled', !$root.find('.mlr-public-loan-consent').is(':checked'));
        });
    });

    $(document).on('click', '.mlr-public-loan-verify-otp', function(){
        var $btn = $(this), $root = $btn.closest('.mlr-loan-public-contract');
        var $input = $root.find('.mlr-public-loan-otp');
        var otp = digits($input.val());
        $input.val(otp);
        if (otp.length !== 6) {
            message($root, 'error', 'کد تأیید باید دقیقاً ۶ رقم باشد.');
            $input.focus();
            return;
        }
        $btn.prop('disabled', true).text('در حال تأیید...');
        $input.prop('disabled', true);
        message($root, 'loading', 'در حال بررسی کد و ثبت امضای دیجیتال...');
        $.post(cfg.ajax_url, {
            action: 'mlr_verify_public_loan_contract_otp',
            nonce: cfg.nonce,
            contract_token: $root.attr('data-loan-contract-token') || '',
            otp_code: otp
        }, null, 'json').done(function(res){
            var data = res && res.data ? res.data : {};
            if (res && res.success && data.signed) {
                message($root, 'success', data.message || 'قرارداد با موفقیت امضا شد.');
                window.setTimeout(function(){ window.location.reload(); }, 700);
                return;
            }
            message($root, 'error', data.message || 'کد تأیید پذیرفته نشد.');
            $btn.prop('disabled', false).text('تأیید کد و امضا');
            $input.prop('disabled', false).val('').focus();
            if (data.otp_active && data.cooldown) startTimer($root, data.cooldown);
        }).fail(function(){
            message($root, 'error', 'ارتباط با سرور برای تأیید کد برقرار نشد.');
            $btn.prop('disabled', false).text('تأیید کد و امضا');
            $input.prop('disabled', false).focus();
        });
    });
})(jQuery);
