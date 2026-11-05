/**
 * Resilient guarantor form uploader.
 *
 * Files are uploaded one-by-one (chunked when needed) through MLRFileUpload.
 * The final guarantor save request is URL-encoded and contains only text plus
 * server-issued URL/token pairs, avoiding large multipart requests that can be
 * reset by HTTP/2/WAF/proxy layers.
 */
(function($, window) {
    'use strict';

    if (window.MLRGuarantorFormActive) {
        return;
    }
    window.MLRGuarantorFormActive = true;

    var FILE_FIELDS = [
        'national_card', 'id_card', 'payslip', 'sana', 'check',
        'residence_deed', 'work_deed', 'account_statement', 'extra_docs'
    ];
    var TEXT_FIELDS = [
        'full_name', 'national_code', 'father_name', 'id_number', 'issued_from',
        'birth_date', 'mobile', 'phone', 'job', 'postal_code', 'address'
    ];

    function valueFromGlobals(names, key) {
        for (var i = 0; i < names.length; i++) {
            var obj = window[names[i]];
            if (obj && obj[key]) return obj[key];
        }
        return '';
    }

    function resolveAjaxUrl(form) {
        var url = valueFromGlobals([
            'mlrAjax', 'mlr_applicant_ajax_obj', 'mlr_ajax_obj',
            'mlr_bank_ajax_obj', 'mlr_verified_ajax_obj'
        ], 'ajaxurl');
        if (url) return url;
        return valueFromGlobals([
            'mlr_applicant_ajax_obj', 'mlr_ajax_obj', 'mlr_bank_ajax_obj',
            'mlr_verified_ajax_obj'
        ], 'ajax_url') || '';
    }

    function resolveNonce(form) {
        return form.attr('data-details-nonce') ||
            form.find('input[name="nonce"]').val() ||
            valueFromGlobals(['mlrAjax'], 'nonce') ||
            valueFromGlobals(['mlr_applicant_ajax_obj', 'mlr_ajax_obj'], 'details_nonce') ||
            valueFromGlobals(['mlr_bank_ajax_obj', 'mlr_verified_ajax_obj'], 'nonce') || '';
    }

    function resolvePortalContext(form) {
        var explicit = String(form.attr('data-portal-context') || '').trim();
        if (explicit === 'acceptor' || explicit === 'applicant' || explicit === 'auto') {
            return explicit;
        }
        if (form.hasClass('mlr-guarantor-form-admin')) return 'auto';
        if (window.mlr_applicant_ajax_obj) return 'applicant';
        return 'acceptor';
    }

    function ensureRequestIdInUrl(url, requestId) {
        if (!url || !requestId) return url;
        var param = 'request_id=' + encodeURIComponent(String(requestId));
        var replaced = url.replace(/([?&])request_id=[^&]*/gi, '$1' + param);
        if (replaced !== url) return replaced;
        return url + (url.indexOf('?') !== -1 ? '&' : '?') + param;
    }

    function getSaveUrl(form, ajaxUrl, requestId) {
        var url = form.attr('data-save-url') || ajaxUrl;
        return ensureRequestIdInUrl(url, requestId);
    }

    function getFileLabel(input) {
        var label = $(input).closest('.mlr-form-group').find('label').first().text().trim();
        return label || input.name || 'مدرک';
    }

    function collectFiles(form) {
        var items = [];
        FILE_FIELDS.forEach(function(name) {
            var input = form.find('input[type="file"][name="' + name + '"]')[0];
            if (!input || !input.files || !input.files.length) return;
            if (input.files.length > 1) {
                throw new Error('برای «' + getFileLabel(input) + '» فقط یک فایل انتخاب کنید.');
            }
            items.push({ field: name, input: input, file: input.files[0], label: getFileLabel(input) });
        });
        return items;
    }

    function collectSaveData(form, requestId, nonce, guarantorIndex, staged) {
        var data = {
            action: 'mlr_save_guarantor_info',
            request_id: requestId,
            nonce: nonce,
            guarantor_index: guarantorIndex,
            guarantor_staged_uploads: JSON.stringify(staged || {})
        };
        var blogId = String(form.attr('data-blog-id') || form.find('input[name="blog_id"]').val() || '').trim();
        var dbPrefix = String(form.attr('data-db-prefix') || form.find('input[name="db_prefix"]').val() || '').trim();
        if (blogId) data.blog_id = blogId;
        if (dbPrefix) data.db_prefix = dbPrefix;

        TEXT_FIELDS.forEach(function(name) {
            var field = form.find('[name="' + name + '"]').first();
            data[name] = field.length ? (field.val() || '') : '';
        });
        return data;
    }

    function parseResponseMessage(res, fallback) {
        if (res && res.data && res.data.message) return res.data.message;
        if (res && res.message) return res.message;
        return fallback;
    }

    function uploadSequentially(items, opts, msg, guarantorIndex) {
        var staged = {};
        var chain = Promise.resolve();

        items.forEach(function(item, idx) {
            chain = chain.then(function() {
                msg.css('color', '#1f5f99').text('در حال بارگذاری «' + item.label + '» (' + (idx + 1) + ' از ' + items.length + ')...');
                return window.MLRFileUpload.processAndUploadFile(item.file, {
                    ajax_url: opts.ajaxUrl,
                    nonce: opts.nonce,
                    context: 'guarantor',
                    field_key: 'guarantor_' + guarantorIndex + '_' + item.field,
                    request_id: opts.requestId,
                    portal_context: opts.portalContext,
                    blog_id: opts.blogId || 0,
                    document_flow_state: ''
                }, function(percent) {
                    msg.css('color', '#1f5f99').text('در حال بارگذاری «' + item.label + '» (' + (idx + 1) + ' از ' + items.length + ') — ' + percent + '٪');
                }).then(function(result) {
                    if (!result || !result.url || !result.token) {
                        throw new Error('تأیید بارگذاری «' + item.label + '» از سرور دریافت نشد.');
                    }
                    staged[item.field] = { url: result.url, token: result.token };
                });
            });
        });
        return chain.then(function() { return staged; });
    }

    function finalSave(form, opts, staged, btn, btnLabel, msg) {
        msg.css('color', '#1f5f99').text('در حال ذخیره اطلاعات ضامن...');
        var saveData = collectSaveData(form, opts.requestId, opts.nonce, opts.guarantorIndex, staged);
        return $.ajax({
            url: getSaveUrl(form, opts.ajaxUrl, opts.requestId),
            type: 'POST',
            data: saveData,
            processData: true,
            contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
            dataType: 'json',
            headers: { 'X-MLR-Request-Id': String(opts.requestId) }
        }).then(function(res) {
            if (!res || !res.success) {
                throw new Error(parseResponseMessage(res, 'خطا در ذخیره اطلاعات ضامن.'));
            }
            msg.css('color', 'green').text(parseResponseMessage(res, 'اطلاعات و مدارک ضامن با موفقیت ذخیره شد.'));
            form.find('input[type="file"]').val('');
            $(document).trigger('mlr:guarantor:saved', [{
                requestId: opts.requestId,
                guarantorIndex: opts.guarantorIndex,
                portalContext: opts.portalContext,
                isAdmin: form.hasClass('mlr-guarantor-form-admin')
            }]);
            return res;
        });
    }

    $(document).off('submit.mlrGuarantorForm', '#mlr-guarantor-form, #mlr-guarantor-form-2')
        .on('submit.mlrGuarantorForm', '#mlr-guarantor-form, #mlr-guarantor-form-2', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();

            var form = $(this);
            var msg = form.find('.mlr-guarantor-msg');
            var btn = form.find('button[type="submit"]');
            if (btn.data('mlr-guarantor-busy')) return;

            var isSecond = form.attr('id') === 'mlr-guarantor-form-2';
            var guarantorIndex = isSecond ? 2 : 1;
            var btnLabel = btn.text() || (isSecond ? 'ذخیره اطلاعات ضامن دوم' : 'ذخیره اطلاعات ضامن');
            var requestId = parseInt(form.attr('data-request-id') || form.find('input[name="request_id"]').val() || '0', 10);
            var ajaxUrl = resolveAjaxUrl(form);
            var nonce = resolveNonce(form);
            var portalContext = resolvePortalContext(form);
            var blogId = parseInt(form.attr('data-blog-id') || form.find('input[name="blog_id"]').val() || '0', 10) || 0;

            if (!requestId) {
                msg.css('color', 'red').text('شناسه پرونده یافت نشد.');
                return;
            }
            if (!ajaxUrl || !nonce) {
                msg.css('color', 'red').text('اطلاعات ارتباط با سرور کامل نیست. صفحه را تازه‌سازی کنید.');
                return;
            }

            var files;
            try {
                files = collectFiles(form);
            } catch (err) {
                msg.css('color', 'red').text(err && err.message ? err.message : 'فایل انتخاب‌شده معتبر نیست.');
                return;
            }

            if (files.length && (!window.MLRFileUpload || typeof window.MLRFileUpload.processAndUploadFile !== 'function')) {
                msg.css('color', 'red').text('سرویس بارگذاری امن فایل در دسترس نیست. صفحه را تازه‌سازی کنید.');
                return;
            }

            btn.data('mlr-guarantor-busy', true).prop('disabled', true).text('در حال ذخیره...');
            msg.text('').css('color', '');

            var opts = {
                requestId: requestId,
                ajaxUrl: ajaxUrl,
                nonce: nonce,
                portalContext: portalContext,
                guarantorIndex: guarantorIndex,
                blogId: blogId
            };

            var promise = files.length
                ? uploadSequentially(files, opts, msg, guarantorIndex)
                : Promise.resolve({});

            promise
                .then(function(staged) { return finalSave(form, opts, staged, btn, btnLabel, msg); })
                .catch(function(err) {
                    var text = err && err.message ? err.message : 'خطا در ذخیره اطلاعات ضامن.';
                    msg.css('color', 'red').text(text);
                })
                .then(function() {
                    btn.data('mlr-guarantor-busy', false).prop('disabled', false).text(btnLabel);
                }, function() {
                    btn.data('mlr-guarantor-busy', false).prop('disabled', false).text(btnLabel);
                });
        });
})(jQuery, window);
