var $ = jQuery.noConflict();

$(document).ready(function() {
    if (typeof $ === 'undefined') {
        console.error('jQuery لود نشده است.');
        return;
    }

    let currentStep = 1;
    window.nextStep = function(step) {
        if (step > currentStep) {
            for (let i = 1; i <= step; i++) {
                document.getElementById('step-indicator-' + i).classList.add('completed');
            }
        } else {
            for (let i = step + 1; i <= 4; i++) {
                document.getElementById('step-indicator-' + i).classList.remove('completed');
            }
        }
        currentStep = step;
        document.getElementById('step1').style.display = step === 1 ? 'block' : 'none';
        document.getElementById('step2').style.display = step === 2 ? 'block' : 'none';
        document.getElementById('step3').style.display = step === 3 ? 'block' : 'none';
        document.getElementById('step4').style.display = step === 4 ? 'block' : 'none';
    };

    window.updatePlanFields = function() {
        const planSelect = document.getElementById('installment_plan_id');
        const selectedOption = planSelect.options[planSelect.selectedIndex];
        const minAmount = selectedOption.getAttribute('data-min-amount');
        const maxAmount = selectedOption.getAttribute('data-max-amount');
        const minInterval = selectedOption.getAttribute('data-min-interval');
        const maxInterval = selectedOption.getAttribute('data-max-interval');
        const minCount = selectedOption.getAttribute('data-min-count');
        const maxCount = selectedOption.getAttribute('data-max-count');

        const amountInput = document.getElementById('loan_amount');
        const intervalInput = document.getElementById('installment_interval');
        const countInput = document.getElementById('installment_count');

        amountInput.min = minAmount;
        amountInput.max = maxAmount;
        intervalInput.min = minInterval;
        intervalInput.max = maxInterval;
        countInput.min = minCount;
        countInput.max = maxCount;

        $.post(mlrAjax.ajaxurl, {
            action: 'mlr_get_plan_documents',
            nonce: mlrAjax.nonce,
            plan_id: selectedOption.value,
            context: 'mlr_loan_request_form'
        }, function(response) {
            if (response.success) {
                document.getElementById('dynamic-documents').innerHTML = response.data;
            } else {
                alert(mlrAjax.messages.planError || 'خطا در بارگذاری مدارک طرح.');
            }
        });
    };

    let verificationCounter = 0;
    $('#mlr-verify-identity').on('click', function(e) {
        e.preventDefault();
        let nationalCode = $('input[name="national_code_validation"]').val();
        let mobileNumber = $('input[name="mobile_number_validation"]').val();

        if (!nationalCode.match(/^[0-9]{10}$/) || !mobileNumber.match(/^09[0-9]{9}$/)) {
            $('#mlr-verification-result').html('<p style="color:red;">لطفاً کد ملی (10 رقم) و شماره موبایل (11 رقم با شروع 09) معتبر وارد کنید.</p>').show();
            return;
        }

        $('#mlr-verification-result').html('<p>در حال بررسی...</p>').show();
        $('#mlr-verify-identity').prop('disabled', true);

        setTimeout(function() {
            verificationCounter++;
            let isVerified = verificationCounter % 2 === 0;
            if (isVerified) {
                $('#mlr-verification-result').html('<p style="color:green;">تطابق کد ملی و شماره موبایل تأیید شد.</p>');
                $('#mlr-verification-checkbox').show();
                $('#mlr-next-step-1').show();
            } else {
                $('#mlr-verification-result').html('<p style="color:red;">تطابق کد ملی و شماره موبایل تأیید نشد. لطفاً دوباره تلاش کنید.</p>');
                $('#mlr-verification-checkbox').hide();
                $('#mlr-next-step-1').hide();
            }
            $('#mlr-verify-identity').prop('disabled', false);
        }, 3500);
    });

    function loadRequests() {
        let data = {
            action: 'mlr_filter_user_requests',
            nonce: mlrAjax.nonce,
            search: $('#mlr-search').val(),
            status: $('#mlr-status').val(),
            date_from: $('#mlr-date-from').val(),
            date_to: $('#mlr-date-to').val()
        };

        $.ajax({
            url: mlrAjax.ajaxurl,
            type: 'POST',
            data: data,
            beforeSend: function() {
                $('#mlr-requests-table').html('<tr><td colspan="9">در حال بارگذاری...</td></tr>');
            },
            success: function(response) {
                console.log('پاسخ AJAX برای بارگذاری درخواست‌ها:', response);
                if (response.success) {
                    $('#mlr-requests-table').html(response.data);
                } else {
                    console.error('خطا در بارگذاری درخواست‌ها:', response.data || 'پاسخ نامشخص');
                    alert(mlrAjax.messages.ajaxError || 'خطایی در بارگذاری درخواست‌ها رخ داد: ' + (response.data || 'پاسخ نامشخص'));
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('خطای AJAX:', textStatus, errorThrown);
                alert(mlrAjax.messages.serverError || 'خطا در ارتباط با سرور: ' + textStatus);
            }
        });
    }

    $(document).on('click', '.mlr-request-row', function(e) {
        e.preventDefault();
        e.stopPropagation();

        if ($(e.target).closest('form, a, button').length) {
            return;
        }

        let requestId = $(this).data('id');
        if (!requestId) {
            console.error('شناسه درخواست یافت نشد.');
            alert(mlrAjax.messages.noRequestId || 'شناسه درخواست یافت نشد.');
            return;
        }

        $('#mlr-actions-content').html('در حال بارگذاری...');
        $('#mlr-overlay').css('display', 'block');
        $('#mlr-actions-popup').css('display', 'block');

        $.ajax({
            url: mlrAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'mlr_get_request_details',
                nonce: mlrAjax.nonce,
                request_id: requestId
            },
            cache: false,
            success: function(response) {
                if (response.success) {
                    $('#mlr-actions-content').html(response.data);
                } else {
                    console.error('خطا در AJAX:', response.data || 'پاسخ نامشخص');
                    alert(mlrAjax.messages.ajaxError || 'خطایی رخ داد: ' + (response.data || 'پاسخ نامشخص'));
                    $('#mlr-overlay').css('display', 'none');
                    $('#mlr-actions-popup').css('display', 'none');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('خطای AJAX:', textStatus, errorThrown);
                alert(mlrAjax.messages.serverError || 'خطا در ارتباط با سرور: ' + textStatus);
                $('#mlr-overlay').css('display', 'none');
                $('#mlr-actions-popup').css('display', 'none');
            }
        });
    });

    $(document).on('click', '.mlr-actions-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        let requestId = $(this).data('id');
        $.ajax({
            url: mlrAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'mlr_get_user_actions',
                nonce: mlrAjax.nonce,
                request_id: requestId
            },
            beforeSend: function() {
                $('#mlr-actions-content').html('در حال بارگذاری...');
            },
            success: function(response) {
                console.log('پاسخ AJAX:', response);
                if (response.success) {
                    $('#mlr-actions-content').html(response.data);
                    $('#mlr-overlay').css('display', 'block');
                    $('#mlr-actions-popup').css('display', 'block');
                } else {
                    console.error('خطا در AJAX:', response.data || 'پاسخ نامشخص');
                    alert(mlrAjax.messages.ajaxError || 'خطایی رخ داد: ' + (response.data || 'پاسخ نامشخص'));
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('خطای AJAX:', textStatus, errorThrown);
                alert(mlrAjax.messages.serverError || 'خطا در ارتباط با سرور: ' + textStatus);
            }
        });
    });

    $('#mlr-filter-btn').on('click', function(e) {
        e.preventDefault();
        loadRequests();
    });

    $(document).on('click', '#mlr-close-actions, #mlr-overlay', function() {
        $('#mlr-overlay').css('display', 'none');
        $('#mlr-actions-popup').css('display', 'none');
    });

    $('#mlr-actions-popup').on('click', function(e) {
        e.stopPropagation();
    });

    loadRequests();
});