/**
 * Reliable dashboard document uploader.
 * Uploads files one-by-one (chunked when needed), then finalizes the request
 * with signed staged-upload tokens. This avoids total multipart/post_max_size
 * failures and keeps acceptor/applicant behavior identical.
 */
(function($, window) {
    'use strict';

    window.MLRDashboardDocumentUploadActive = true;

    function getConfig(action) {
        if (String(action || '').indexOf('mlr_applicant_') === 0) {
            return window.mlr_applicant_ajax_obj || {};
        }
        return window.mlr_ajax_obj || {};
    }

    function getPortalContext(action) {
        return String(action || '').indexOf('mlr_applicant_') === 0 ? 'applicant' : 'acceptor';
    }

    function getMessageBox($form) {
        var $box = $form.find('.mlr-upload-status').first();
        if (!$box.length) {
            $box = $('<div class="mlr-upload-status" style="display:none; margin:10px 0; padding:10px; border-radius:8px;"></div>');
            $form.prepend($box);
        }
        return $box;
    }

    function showMessage($box, type, message) {
        var styles = {
            loading: {'background':'#e8f4fd', 'color':'#1d4ed8', 'border':'1px solid #93c5fd'},
            success: {'background':'#d4edda', 'color':'#155724', 'border':'1px solid #86efac'},
            error: {'background':'#fde8e8', 'color':'#b91c1c', 'border':'1px solid #fca5a5'}
        };
        $box.show().removeClass('loading success error').addClass(type).css(styles[type] || {}).text(message);
    }

    function normalizeState(state) {
        if (window.MLRFileUpload && typeof window.MLRFileUpload.normalizeDocumentFlowState === 'function') {
            return window.MLRFileUpload.normalizeDocumentFlowState(state);
        }
        var aliases = {
            'نقص مدارک': 'defect_documents',
            'تکمیل مدارک': 'completion_documents',
            'در انتظار مدارک': 'pending_documents',
            'در انتظار تکمیل': 'pending_documents',
            'در انتظار تکمیل مدارک': 'pending_documents'
        };
        state = String(state || '').trim();
        return aliases[state] || state;
    }

    function getState($form) {
        var $body = $form.closest('.mlr-modal-body[data-document-flow-stage]');
        return normalizeState($body.attr('data-document-flow-stage') || $form.attr('data-document-flow-stage') || '');
    }

    function fileArray(input) {
        if (!input || !input.files) return [];
        return Array.prototype.slice.call(input.files);
    }

    function validateFileExtension(file) {
        var name = String(file && file.name || '').toLowerCase();
        return /\.(jpe?g|png|webp|pdf)$/.test(name);
    }

    function uploadFilesSequentially(files, opts, $message, progress) {
        var results = [];
        var chain = Promise.resolve();
        var total = files.length;

        files.forEach(function(file, index) {
            chain = chain.then(function() {
                if (!validateFileExtension(file)) {
                    throw new Error('فرمت فایل «' + file.name + '» مجاز نیست. فقط JPG، PNG، WEBP و PDF مجاز است.');
                }

                showMessage($message, 'loading', 'در حال بارگذاری فایل ' + (progress.offset + index + 1) + ' از ' + progress.total + ': ' + file.name);
                return window.MLRFileUpload.processAndUploadFile(file, opts, function(percent) {
                    showMessage($message, 'loading', 'در حال بارگذاری فایل ' + (progress.offset + index + 1) + ' از ' + progress.total + ' — ' + percent + '٪');
                });
            }).then(function(result) {
                if (!result || !result.url || !result.token) {
                    throw new Error('تأیید امنیتی فایل «' + file.name + '» دریافت نشد. صفحه را تازه‌سازی کنید.');
                }
                results.push({url: result.url, token: result.token});
            });
        });

        return chain.then(function() { return results; });
    }

    function ajaxFinalize(config, data) {
        return new Promise(function(resolve, reject) {
            $.ajax({
                url: config.ajax_url,
                method: 'POST',
                data: data,
                dataType: 'json',
                timeout: 120000
            }).done(function(response) {
                if (response && response.success) {
                    resolve(response);
                    return;
                }
                reject(new Error(response && response.data && response.data.message ? response.data.message : 'ثبت نهایی مدارک انجام نشد.'));
            }).fail(function(xhr) {
                var message = 'ارتباط با سرور برقرار نشد.';
                if (xhr && xhr.status === 413) {
                    message = 'حجم مجموع درخواست از محدودیت سرور بیشتر است. صفحه را تازه‌سازی کرده و دوباره تلاش کنید.';
                } else if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    message = xhr.responseJSON.data.message;
                }
                reject(new Error(message));
            });
        });
    }

    function finishSuccess($form, $message, response) {
        showMessage($message, 'success', response.data && response.data.message ? response.data.message : 'مدارک با موفقیت ثبت شد.');
        $form.find('button, input, textarea, select').prop('disabled', true);
        setTimeout(function() {
            $('#mlr-global-modal-overlay, #mlr-global-modal').fadeOut(200);
            $(document).trigger('mlr:requests:reload');
        }, 1600);
    }

    function resetAfterError($form, $message, error, originalButtonText) {
        showMessage($message, 'error', error && error.message ? error.message : 'خطای نامشخص در بارگذاری مدارک.');
        $form.data('mlr-upload-busy', false);
        $form.find('input, textarea, select').prop('disabled', false);
        $form.find('button[type="submit"]').prop('disabled', false).text(originalButtonText);
    }

    function getPortalConfig(portalContext) {
        return String(portalContext || '') === 'applicant'
            ? (window.mlr_applicant_ajax_obj || {})
            : (window.mlr_ajax_obj || {});
    }

    function normalizeOtpDigits(value) {
        var fa = '۰۱۲۳۴۵۶۷۸۹';
        var ar = '٠١٢٣٤٥٦٧٨٩';
        return String(value || '').replace(/[۰-۹٠-٩]/g, function(ch) {
            var i = fa.indexOf(ch);
            if (i < 0) i = ar.indexOf(ch);
            return i >= 0 ? String(i) : ch;
        }).replace(/\D+/g, '').slice(0, 6);
    }

    function showContractOtpMessage($box, type, message) {
        var $message = $box.find('.mlr-loan-contract-otp-message').first();
        var styles = {
            success: {'display':'block', 'background':'#f0fdf4', 'color':'#166534', 'border':'1px solid #bbf7d0'},
            error: {'display':'block', 'background':'#fef2f2', 'color':'#b91c1c', 'border':'1px solid #fecaca'},
            loading: {'display':'block', 'background':'#eff6ff', 'color':'#1d4ed8', 'border':'1px solid #bfdbfe'}
        };
        $message.css(styles[type] || styles.loading).text(message || '');
    }

    function contractTimerKey($box) {
        return String($box.attr('data-portal-context') || '') + ':' + String($box.attr('data-request-id') || '');
    }

    var contractOtpTimers = {};

    function clearContractOtpTimer($box) {
        var key = contractTimerKey($box);
        if (contractOtpTimers[key]) {
            window.clearInterval(contractOtpTimers[key]);
            delete contractOtpTimers[key];
        }
    }

    function startContractOtpTimer($box, seconds) {
        clearContractOtpTimer($box);
        var remaining = Math.max(0, parseInt(seconds, 10) || 0);
        var $send = $box.find('.mlr-loan-contract-send-otp').first();
        var $timer = $box.find('.mlr-loan-contract-otp-timer').first();

        function render() {
            if (remaining > 0) {
                $send.prop('disabled', true).text('ارسال مجدد تا ' + remaining + ' ثانیه');
                $timer.text('اعتبار کد: ' + remaining + ' ثانیه');
                remaining--;
                return;
            }
            clearContractOtpTimer($box);
            $timer.text('');
            $send.text('ارسال مجدد کد').prop('disabled', !$box.find('.mlr-loan-contract-consent').is(':checked'));
        }

        render();
        if (remaining > 0) {
            contractOtpTimers[contractTimerKey($box)] = window.setInterval(render, 1000);
        }
    }

    function markLoanContractSigned($box, signedAt, message, contractUrl) {
        clearContractOtpTimer($box);
        $box.attr('data-signed', '1').data('signed', 1);

        var $success = $('<div class="mlr-loan-contract-sign-success" style="display:flex;gap:10px;align-items:flex-start;color:#166534;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px 14px;"></div>');
        $success.append('<span class="dashicons dashicons-yes-alt" style="margin-top:2px;"></span>');
        var $content = $('<div></div>');
        $('<strong style="display:block;margin-bottom:4px;"></strong>').text(message || 'قرارداد دریافت تسهیلات به‌صورت دیجیتال تأیید و امضا شده است.').appendTo($content);
        if (signedAt) {
            $('<small style="display:block;margin-bottom:8px;"></small>').text('این قرارداد در تاریخ و ساعت ' + signedAt + ' امضا و تأیید شده است.').appendTo($content);
        }
        if (contractUrl) {
            $('<a class="mlr-btn mlr-btn-secondary mlr-loan-contract-signed-link" target="_blank" rel="noopener noreferrer" style="display:inline-flex;margin-top:4px;">مشاهده قرارداد امضاشده</a>')
                .attr('href', String(contractUrl))
                .appendTo($content);
        }
        $success.append($content);
        $box.empty().append($success);

        var $completion = $box.closest('.mlr-completion-documents-box');
        var $form = $completion.find('.mlr-completed-docs-form').first();
        $form.attr('data-contract-signed', '1').data('contract-signed', 1);
        $form.find('.mlr-completed-docs-submit').prop('disabled', false);
        $form.find('.mlr-contract-required-note').remove();
    }

    $(document).on('change', '.mlr-loan-contract-consent', function() {
        var $box = $(this).closest('.mlr-loan-contract-sign-box');
        if (String($box.attr('data-signed')) === '1') return;
        var key = contractTimerKey($box);
        if (!contractOtpTimers[key]) {
            $box.find('.mlr-loan-contract-send-otp').prop('disabled', !$(this).is(':checked'));
        }
    });

    $(document).on('input', '.mlr-loan-contract-otp-input', function() {
        var normalized = normalizeOtpDigits($(this).val());
        if ($(this).val() !== normalized) $(this).val(normalized);
    });

    $(document).on('keydown', '.mlr-loan-contract-otp-input', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            $(this).closest('.mlr-loan-contract-sign-box').find('.mlr-loan-contract-verify-otp').trigger('click');
        }
    });

    $(document).on('click', '.mlr-loan-contract-send-otp', function() {
        var $button = $(this);
        var $box = $button.closest('.mlr-loan-contract-sign-box');
        if (String($box.attr('data-signed')) === '1') return;
        if (!$box.find('.mlr-loan-contract-consent').is(':checked')) {
            showContractOtpMessage($box, 'error', 'ابتدا مطالعه و پذیرش متن قرارداد را تأیید کنید.');
            return;
        }

        var portal = String($box.attr('data-portal-context') || '');
        var requestId = String($box.attr('data-request-id') || '');
        var config = getPortalConfig(portal);
        if (!config.ajax_url || !config.details_nonce || !requestId) {
            showContractOtpMessage($box, 'error', 'تنظیمات ارتباط با سرور در دسترس نیست. صفحه را تازه‌سازی کنید.');
            return;
        }

        $button.prop('disabled', true).text('در حال ارسال...');
        showContractOtpMessage($box, 'loading', 'در حال ارسال کد تأیید...');
        $.ajax({
            url: config.ajax_url,
            method: 'POST',
            dataType: 'json',
            timeout: 30000,
            data: {
                action: 'mlr_send_loan_contract_otp',
                nonce: config.details_nonce,
                request_id: requestId,
                portal_context: portal,
                accepted: '1'
            }
        }).done(function(response) {
            var data = response && response.data ? response.data : {};
            if (response && response.success) {
                if (data.signed) {
                    markLoanContractSigned($box, data.signed_at || '', data.message || 'قرارداد قبلاً تأیید شده است.');
                    return;
                }
                $box.find('.mlr-loan-contract-otp-panel').show();
                $box.find('.mlr-loan-contract-otp-input').prop('disabled', false).trigger('focus');
                showContractOtpMessage($box, 'success', data.message || 'کد تأیید ارسال شد.');
                startContractOtpTimer($box, data.cooldown || 120);
                return;
            }

            showContractOtpMessage($box, 'error', data.message || 'ارسال کد تأیید انجام نشد.');
            if (data.otp_active) {
                $box.find('.mlr-loan-contract-otp-panel').show();
                $box.find('.mlr-loan-contract-otp-input').prop('disabled', false).trigger('focus');
                startContractOtpTimer($box, data.cooldown || 1);
            } else {
                clearContractOtpTimer($box);
                $button.text('ارسال کد تأیید').prop('disabled', !$box.find('.mlr-loan-contract-consent').is(':checked'));
            }
        }).fail(function(xhr) {
            var message = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                ? xhr.responseJSON.data.message
                : 'ارتباط با سرور برای ارسال کد برقرار نشد.';
            showContractOtpMessage($box, 'error', message);
            clearContractOtpTimer($box);
            $button.text('ارسال کد تأیید').prop('disabled', !$box.find('.mlr-loan-contract-consent').is(':checked'));
        });
    });

    $(document).on('click', '.mlr-loan-contract-verify-otp', function() {
        var $button = $(this);
        var $box = $button.closest('.mlr-loan-contract-sign-box');
        var $input = $box.find('.mlr-loan-contract-otp-input').first();
        var otp = normalizeOtpDigits($input.val());
        $input.val(otp);
        if (otp.length !== 6) {
            showContractOtpMessage($box, 'error', 'کد تأیید باید دقیقاً ۶ رقم باشد.');
            $input.trigger('focus');
            return;
        }

        var portal = String($box.attr('data-portal-context') || '');
        var requestId = String($box.attr('data-request-id') || '');
        var config = getPortalConfig(portal);
        if (!config.ajax_url || !config.details_nonce || !requestId) {
            showContractOtpMessage($box, 'error', 'تنظیمات ارتباط با سرور در دسترس نیست. صفحه را تازه‌سازی کنید.');
            return;
        }

        $button.prop('disabled', true).text('در حال تأیید...');
        $input.prop('disabled', true);
        showContractOtpMessage($box, 'loading', 'در حال بررسی کد و ثبت امضای دیجیتال...');
        $.ajax({
            url: config.ajax_url,
            method: 'POST',
            dataType: 'json',
            timeout: 30000,
            data: {
                action: 'mlr_verify_loan_contract_otp',
                nonce: config.details_nonce,
                request_id: requestId,
                portal_context: portal,
                otp_code: otp
            }
        }).done(function(response) {
            var data = response && response.data ? response.data : {};
            if (response && response.success && data.signed) {
                markLoanContractSigned($box, data.signed_at || '', data.message || 'قرارداد با موفقیت به‌صورت دیجیتال تأیید شد.', data.contract_url || '');
                return;
            }

            showContractOtpMessage($box, 'error', data.message || 'کد تأیید پذیرفته نشد.');
            $button.prop('disabled', false).text('تأیید و امضای قرارداد');
            $input.prop('disabled', false).val('').trigger('focus');
            if (data.otp_active) {
                $box.find('.mlr-loan-contract-otp-panel').show();
                if (data.cooldown) startContractOtpTimer($box, data.cooldown);
            } else {
                clearContractOtpTimer($box);
                $box.find('.mlr-loan-contract-otp-panel').hide();
                $box.find('.mlr-loan-contract-send-otp').text('ارسال کد جدید').prop('disabled', !$box.find('.mlr-loan-contract-consent').is(':checked'));
            }
        }).fail(function(xhr) {
            var message = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                ? xhr.responseJSON.data.message
                : 'ارتباط با سرور برای تأیید کد برقرار نشد.';
            showContractOtpMessage($box, 'error', message);
            $button.prop('disabled', false).text('تأیید و امضای قرارداد');
            $input.prop('disabled', false).trigger('focus');
        });
    });

    $(document).on('submit', '.mlr-completed-docs-form', function(event) {
        event.preventDefault();
        event.stopImmediatePropagation();

        var $form = $(this);
        if ($form.data('mlr-upload-busy')) return;

        var action = $form.attr('data-upload-action') || $form.find('input[name="action"]').val() || 'mlr_submit_completed_docs';
        var config = getConfig(action);
        var requestId = String($form.find('input[name="request_id"]').val() || '').trim();
        var state = getState($form);
        var $message = getMessageBox($form);
        var $button = $form.find('button[type="submit"]').first();
        var originalButtonText = $button.text();

        if (!config.ajax_url || !config.details_nonce) {
            showMessage($message, 'error', 'تنظیمات ارتباط با سرور بارگذاری نشده است. صفحه را تازه‌سازی کنید.');
            return;
        }
        if (!requestId) {
            showMessage($message, 'error', 'شناسه پرونده یافت نشد.');
            return;
        }
        if (state !== 'completion_documents' && state !== 'pending_documents') {
            showMessage($message, 'error', 'این فرم فقط در مرحله تکمیل مدارک فعال است.');
            return;
        }
        if (!window.MLRFileUpload || typeof window.MLRFileUpload.processAndUploadFile !== 'function') {
            showMessage($message, 'error', 'سرویس بارگذاری امن فایل در دسترس نیست. صفحه را تازه‌سازی کنید.');
            return;
        }

        var checks = fileArray($form.find('input[name="completed_check_files[]"]')[0]);
        var contracts = fileArray($form.find('input[name="signed_contract"]')[0]);
        var invoices = fileArray($form.find('input[name="signed_invoice"]')[0]);
        var others = fileArray($form.find('input[name="other_completed_files[]"]')[0]);
        var contractMode = String($form.attr('data-contract-mode') || 'physical');
        var contractSigned = String($form.attr('data-contract-signed') || '0') === '1';
        var checkFilesEnabled = String($form.attr('data-check-files-enabled') || '1') !== '0';

        if (!checkFilesEnabled) {
            checks = [];
        } else if (!checks.length) {
            showMessage($message, 'error', 'حداقل یک تصویر یا فایل چک انتخاب کنید.');
            return;
        }
        if (contractMode === 'digital') {
            contracts = [];
            if (!contractSigned) {
                showMessage($message, 'error', 'ابتدا قرارداد دریافت تسهیلات را با کد تأیید به‌صورت دیجیتال امضا کنید.');
                return;
            }
        } else if (contracts.length !== 1) {
            showMessage($message, 'error', 'فایل قرارداد امضا‌شده را انتخاب کنید.');
            return;
        }
        if (invoices.length !== 1) {
            showMessage($message, 'error', 'فایل فاکتور امضا/مهر‌شده را انتخاب کنید.');
            return;
        }
        if (checks.length > 20 || others.length > 20) {
            showMessage($message, 'error', 'حداکثر ۲۰ فایل در هر بخش چندفایلی مجاز است.');
            return;
        }

        var totalFiles = checks.length + contracts.length + invoices.length + others.length;
        var offset = 0;
        var commonOpts = {
            ajax_url: config.ajax_url,
            nonce: config.details_nonce,
            context: 'loan_request',
            request_id: requestId,
            portal_context: getPortalContext(action),
            document_flow_state: state
        };
        var staged = {};

        $form.data('mlr-upload-busy', true);
        $form.find('input, textarea, select').prop('disabled', true);
        $button.prop('disabled', true).text('در حال ارسال مدارک...');

        uploadFilesSequentially(checks, $.extend({}, commonOpts, {field_key: 'completed_check_files'}), $message, {offset: offset, total: totalFiles})
            .then(function(result) {
                staged.completed_check_uploads = result;
                offset += checks.length;
                return uploadFilesSequentially(contracts, $.extend({}, commonOpts, {field_key: 'signed_contract'}), $message, {offset: offset, total: totalFiles});
            })
            .then(function(result) {
                staged.signed_contract_uploads = result;
                offset += contracts.length;
                return uploadFilesSequentially(invoices, $.extend({}, commonOpts, {field_key: 'signed_invoice'}), $message, {offset: offset, total: totalFiles});
            })
            .then(function(result) {
                staged.signed_invoice_uploads = result;
                offset += invoices.length;
                return uploadFilesSequentially(others, $.extend({}, commonOpts, {field_key: 'other_completed_files'}), $message, {offset: offset, total: totalFiles});
            })
            .then(function(result) {
                staged.other_completed_uploads = result;
                showMessage($message, 'loading', 'بارگذاری فایل‌ها کامل شد؛ در حال ثبت نهایی پرونده...');
                return ajaxFinalize(config, {
                    action: action,
                    nonce: config.details_nonce,
                    request_id: requestId,
                    document_flow_state: state,
                    completed_check_uploads: JSON.stringify(staged.completed_check_uploads || []),
                    signed_contract_uploads: JSON.stringify(staged.signed_contract_uploads || []),
                    signed_invoice_uploads: JSON.stringify(staged.signed_invoice_uploads || []),
                    other_completed_uploads: JSON.stringify(staged.other_completed_uploads || [])
                });
            })
            .then(function(response) {
                finishSuccess($form, $message, response);
            })
            .catch(function(error) {
                resetAfterError($form, $message, error, originalButtonText);
            });
    });

    $(document).on('submit', '.mlr-universal-upload-form', function(event) {
        event.preventDefault();
        event.stopImmediatePropagation();

        var $form = $(this);
        if ($form.data('mlr-upload-busy')) return;

        var action = $form.attr('data-upload-action') || 'mlr_upload_additional_docs';
        var config = getConfig(action);
        var requestId = String($form.find('input[name="request_id"]').val() || '').trim();
        var state = getState($form);
        var $message = getMessageBox($form);
        var $button = $form.find('button[type="submit"]').first();
        var originalButtonText = $button.text();
        var input = $form.find('input[type="file"]').first()[0];
        var files = fileArray(input);

        if (!config.ajax_url || !config.details_nonce) {
            showMessage($message, 'error', 'تنظیمات ارتباط با سرور بارگذاری نشده است. صفحه را تازه‌سازی کنید.');
            return;
        }
        if (!requestId) {
            showMessage($message, 'error', 'شناسه پرونده یافت نشد.');
            return;
        }
        if (state !== 'defect_documents') {
            showMessage($message, 'error', 'این فرم فقط در مرحله نقص مدارک فعال است.');
            return;
        }
        if (!files.length) {
            showMessage($message, 'error', 'لطفاً حداقل یک فایل انتخاب کنید.');
            return;
        }
        if (files.length > 20) {
            showMessage($message, 'error', 'حداکثر ۲۰ فایل قابل ارسال است.');
            return;
        }
        if (!window.MLRFileUpload || typeof window.MLRFileUpload.processAndUploadFile !== 'function') {
            showMessage($message, 'error', 'سرویس بارگذاری امن فایل در دسترس نیست. صفحه را تازه‌سازی کنید.');
            return;
        }

        $form.data('mlr-upload-busy', true);
        $form.find('input, textarea, select').prop('disabled', true);
        $button.prop('disabled', true).text('در حال ارسال مدارک...');

        uploadFilesSequentially(files, {
            ajax_url: config.ajax_url,
            nonce: config.details_nonce,
            context: 'loan_request',
            field_key: 'additional_documents',
            request_id: requestId,
            portal_context: getPortalContext(action),
            document_flow_state: state
        }, $message, {offset: 0, total: files.length})
            .then(function(result) {
                showMessage($message, 'loading', 'بارگذاری فایل‌ها کامل شد؛ در حال ثبت رفع نقص...');
                return ajaxFinalize(config, {
                    action: action,
                    nonce: config.details_nonce,
                    request_id: requestId,
                    document_flow_state: state,
                    user_note: $form.find('textarea[name="user_note"]').val() || '',
                    additional_uploads: JSON.stringify(result)
                });
            })
            .then(function(response) {
                finishSuccess($form, $message, response);
            })
            .catch(function(error) {
                resetAfterError($form, $message, error, originalButtonText);
            });
    });

})(jQuery, window);
