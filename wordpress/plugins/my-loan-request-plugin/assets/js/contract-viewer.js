jQuery(document).ready(function($) {
    $(document).on('click', '.mlr-cooperation-contract-isolated-print', function(e) {
        e.preventDefault();
        var target = document.querySelector('.mlr-contract-container .mlr-print-layout-table');
        if (target && typeof window.mlrPrintContractNode === 'function') {
            window.mlrPrintContractNode(target, {title: 'قرارداد همکاری'});
            return;
        }
        window.print();
    });

    const signatureSection = $('.mlr-contract-signature-section');
    if (signatureSection.length === 0 || typeof mlr_contract_ajax === 'undefined') return;

    const requestToken = String(signatureSection.data('token') || '');
    const initialView = $('#mlr-sign-initial-view');
    const otpView = $('#mlr-sign-otp-view');
    const initiateBtn = $('#mlr-initiate-sign-btn');
    const otpInput = $('#mlr-otp-code');
    const verifyBtn = $('#mlr-verify-otp-btn');
    const timerDisplay = $('#mlr-otp-timer');
    const countdownSpan = $('#mlr-timer-countdown');
    const resendBtn = $('#mlr-resend-otp-btn');
    const messageDiv = $('#mlr-sign-message');

    const otpLength = parseInt(mlr_contract_ajax.otp_length, 10) || 6;
    const resendCooldown = parseInt(mlr_contract_ajax.resend_cooldown, 10) || 60;
    let timerInterval = null;
    let timerEndsAt = 0;
    let verifyInFlight = false;
    let sendInFlight = false;

    initiateBtn.on('click', function() {
        sendOtp();
    });

    resendBtn.on('click', function() {
        if ($(this).is(':disabled')) return;
        sendOtp();
    });

    verifyBtn.on('click', function() {
        verifyOtp();
    });

    otpInput.on('input', function() {
        this.value = normalizeOtpValue(this.value);
    });

    otpInput.on('keydown', function(e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            verifyOtp();
        }
    });

    function normalizeOtpValue(value) {
        const persian = '۰۱۲۳۴۵۶۷۸۹';
        const arabic = '٠١٢٣٤٥٦٧٨٩';
        return String(value || '')
            .replace(/[۰-۹]/g, function(ch) { return String(persian.indexOf(ch)); })
            .replace(/[٠-٩]/g, function(ch) { return String(arabic.indexOf(ch)); })
            .replace(/\D+/g, '')
            .slice(0, otpLength);
    }

    function responseData(response) {
        return response && response.data && typeof response.data === 'object' ? response.data : {};
    }

    function sendOtp() {
        if (sendInFlight || !requestToken) return;
        sendInFlight = true;
        setLoadingState(true, 'در حال ارسال کد...');

        $.ajax({
            url: mlr_contract_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mlr_send_cooperation_otp',
                nonce: mlr_contract_ajax.send_otp_nonce,
                request_token: requestToken
            },
            dataType: 'json'
        }).done(function(response) {
            const data = responseData(response);
            if (response && response.success) {
                initialView.hide();
                otpView.show();
                otpInput.val('').prop('disabled', false);
                startTimer(data.cooldown || resendCooldown);
                showMessage('success', data.message || 'کد تأیید ارسال شد.');
                otpInput.trigger('focus');
                return;
            }

            // If an OTP is already valid (for example after a refresh), let the user
            // enter that same code instead of forcing an unnecessary resend.
            if (data.otp_active) {
                initialView.hide();
                otpView.show();
                startTimer(data.cooldown || 1);
                showMessage('info', data.message || 'کد قبلی هنوز معتبر است. همان کد را وارد کنید.');
                otpInput.prop('disabled', false).trigger('focus');
                return;
            }

            showMessage('error', data.message || 'خطا در ارسال کد.');
        }).fail(function() {
            showMessage('error', 'خطای ارتباط با سرور هنگام ارسال کد.');
        }).always(function() {
            sendInFlight = false;
            setLoadingState(false);
        });
    }

    function verifyOtp() {
        if (verifyInFlight || !requestToken) return;

        const otpCode = normalizeOtpValue(otpInput.val());
        otpInput.val(otpCode);
        // Six digits are canonical. Five digits remain accepted only so a code issued
        // by an immediately previous plugin version is not invalidated during upgrade.
        if (otpCode.length !== otpLength && otpCode.length !== 5) {
            showMessage('error', 'کد تأیید باید ۶ رقم باشد.');
            return;
        }

        verifyInFlight = true;
        setLoadingState(true, 'در حال بررسی کد...');

        $.ajax({
            url: mlr_contract_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mlr_verify_cooperation_otp',
                nonce: mlr_contract_ajax.verify_otp_nonce,
                request_token: requestToken,
                otp_code: otpCode
            },
            dataType: 'json'
        }).done(function(response) {
            const data = responseData(response);
            if (response && response.success) {
                stopTimer();
                otpView.hide();
                otpInput.prop('disabled', true);
                verifyBtn.prop('disabled', true);
                resendBtn.prop('disabled', true);
                showMessage('success', data.message || 'قرارداد با موفقیت امضا شد.');
                setTimeout(function() { window.location.reload(); }, 1200);
                return;
            }

            if (data.otp_active === false) {
                expireOtpUi(false);
            } else if (data.otp_active && data.cooldown && !timerInterval) {
                startTimer(data.cooldown);
            }
            showMessage('error', data.message || 'خطا در تأیید کد.');
        }).fail(function() {
            showMessage('error', 'خطای ارتباط با سرور هنگام تأیید کد.');
        }).always(function() {
            verifyInFlight = false;
            setLoadingState(false);
        });
    }

    function startTimer(seconds) {
        stopTimer();
        const safeSeconds = Math.max(1, parseInt(seconds, 10) || resendCooldown);
        timerEndsAt = Date.now() + (safeSeconds * 1000);
        timerDisplay.show();
        resendBtn.prop('disabled', true);
        otpInput.prop('disabled', false);
        verifyBtn.prop('disabled', false);

        updateTimer();
        timerInterval = setInterval(updateTimer, 250);
    }

    function updateTimer() {
        const remaining = Math.max(0, Math.ceil((timerEndsAt - Date.now()) / 1000));
        countdownSpan.text(remaining);
        if (remaining <= 0) {
            expireOtpUi(true);
        }
    }

    function expireOtpUi(showNotice) {
        stopTimer();
        timerDisplay.hide();
        resendBtn.prop('disabled', false);
        otpInput.prop('disabled', true).val('');
        verifyBtn.prop('disabled', true);
        if (showNotice) {
            showMessage('info', 'زمان ورود کد به پایان رسید. برای دریافت مجدد، روی دکمه ارسال مجدد کلیک کنید.');
        }
    }

    function stopTimer() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        timerEndsAt = 0;
    }

    function setLoadingState(isLoading, message) {
        if (isLoading) {
            messageDiv.removeClass('error success info').addClass('loading').text(message || 'لطفاً صبر کنید...').slideDown();
            initiateBtn.prop('disabled', true);
            verifyBtn.prop('disabled', true);
            resendBtn.prop('disabled', true);
            otpInput.prop('disabled', true);
            return;
        }

        if (initialView.is(':visible')) {
            initiateBtn.prop('disabled', false).text('امضای دیجیتال قرارداد');
        }
        if (otpView.is(':visible')) {
            if (timerInterval) {
                verifyBtn.prop('disabled', false);
                otpInput.prop('disabled', false);
                resendBtn.prop('disabled', true);
            } else {
                verifyBtn.prop('disabled', true);
                otpInput.prop('disabled', true);
                resendBtn.prop('disabled', false);
            }
        }
    }

    function showMessage(type, text) {
        messageDiv.removeClass('loading error success info').addClass(type).text(text).stop(true, true).slideDown();
        if (type === 'error' || type === 'info') {
            setTimeout(function() {
                if (messageDiv.hasClass(type)) {
                    messageDiv.slideUp();
                }
            }, 5000);
        }
    }
});
