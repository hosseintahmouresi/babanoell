jQuery(document).ready(function($) {
    var $forms = $('.mlr-cooperation-form');
    if ($forms.length === 0) return;

    function showSubmissionModal(type, message, $form) {
        var $wrapper = ($form && $form.length) ? $form.closest('.mlr-cooperation-form-wrapper') : $('.mlr-cooperation-form-wrapper').last();
        $wrapper.find('.mlr-submission-overlay').fadeIn(200);
        if (type === 'success') {
            $wrapper.find('.mlr-success-modal').find('p').html(message);
            $wrapper.find('.mlr-success-modal').fadeIn(200);
        } else {
            $wrapper.find('.mlr-error-modal-message').html(message);
            $wrapper.find('.mlr-error-modal').fadeIn(200);
        }
    }

    if (typeof mlr_coop_ajax === 'undefined' || !mlr_coop_ajax.ajax_url) {
        console.error('MLR cooperation form: ajax config (mlr_coop_ajax) is missing. Refresh the page.');
        $(document).on('submit', '.mlr-cooperation-form', function(e) {
            e.preventDefault();
            e.stopPropagation();
            showSubmissionModal('error', 'تنظیمات فرم بارگذاری نشده است. لطفاً صفحه را رفرش کنید.', $(this));
            return false;
        });
        return;
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function escapeAttr(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function buildFileItemHtml(fileName, fileUrl) {
        return '<div class="mlr-uploaded-file-item" data-url="' + escapeAttr(fileUrl) + '">' +
            '<span class="mlr-uploaded-file-name">' + escapeHtml(fileName) + '</span>' +
            ' <a href="#" class="mlr-view-file" title="مشاهده فایل">مشاهده</a>' +
            ' <button type="button" class="mlr-remove-file" title="حذف فایل">حذف</button>' +
            '</div>';
    }

    function validateCooperationFileUploads($form) {
        if (typeof MLRFileUpload === 'undefined') return false;
        if (!$form || !$form.length) return false;
        var allOk = true;
        $form.find('.mlr-coop-file-upload-group').each(function() {
            var $group = $(this);
            if ($group.hasClass('mlr-upload-in-progress')) {
                allOk = false;
                return false;
            }
            if ($group.hasClass('is-required')) {
                var $hidden = $group.find('input.mlr-uploaded-urls');
                var urls = [];
                try {
                    urls = JSON.parse($hidden.val() || '[]');
                } catch (e) {}
                if (!urls.length) {
                    allOk = false;
                    return false;
                }
            }
        });
        return allOk;
    }

    function updateSubmitButton($form) {
        if (!$form || !$form.length) return;
        var submitBtn = $form.find('.mlr-submit-button');
        submitBtn.prop('disabled', !validateCooperationFileUploads($form));
    }

    if (typeof MLRFileUpload === 'undefined') {
        $forms.find('.mlr-coop-file-upload-group').each(function() {
            $(this).find('.mlr-upload-error').text('اسکریپت آپلود بارگذاری نشده است. لطفاً صفحه را رفرش کنید.').show();
        });
        $forms.each(function() { updateSubmitButton($(this)); });
    } else {
        $(document).on('change', '.mlr-cooperation-form .mlr-coop-file-input', function() {
            var $input = $(this);
            var $form = $input.closest('form.mlr-cooperation-form');
            var $group = $input.closest('.mlr-coop-file-upload-group');
            var fieldKey = $group.data('field-key');
            var $progressWrap = $group.find('.mlr-upload-progress-wrap');
            var $progressFill = $group.find('.mlr-upload-progress-fill');
            var $progressPct = $group.find('.mlr-upload-progress-pct');
            var $hidden = $group.find('input.mlr-uploaded-urls');
            var $list = $group.find('.mlr-uploaded-files-list');
            var $errorEl = $group.find('.mlr-upload-error');
            var files = this.files;
            if (!files || files.length === 0) return;

            $errorEl.hide().empty();
            $group.addClass('mlr-upload-in-progress');
            $progressWrap.show();
            $list.find('.mlr-upload-status-pending').remove();
            $list.append('<div class="mlr-upload-status-pending">در حال آپلود...</div>');
            updateSubmitButton($form);

            var opts = {
                ajax_url: mlr_coop_ajax.ajax_url,
                nonce: mlr_coop_ajax.nonce,
                context: 'cooperation_request',
                field_key: fieldKey
            };
            var total = files.length;
            var urls = [];
            try {
                urls = JSON.parse($hidden.val() || '[]');
            } catch (e) {
                urls = [];
            }

            function updateProgress(pct) {
                $progressFill.css('width', pct + '%');
                $progressPct.text(pct + '%');
            }

            function nextFile(index) {
                if (index >= total) {
                    $hidden.val(JSON.stringify(urls));
                    $group.removeClass('mlr-upload-in-progress');
                    $progressWrap.hide();
                    $progressFill.css('width', '0%');
                    $progressPct.text('0%');
                    $list.find('.mlr-upload-status-pending').remove();
                    if (urls.length > 0) {
                        $input.val('');
                    } else {
                        if ($errorEl.text() === '') {
                            $errorEl.text('هیچ فایلی آپلود نشد. نوع و حجم فایل را بررسی کرده و دوباره انتخاب کنید.').show();
                        }
                        $list.empty();
                    }
                    updateSubmitButton($form);
                    return;
                }
                var file = files[index];
                updateProgress(0);
                MLRFileUpload.processAndUploadFile(file, opts, function(pct) {
                    updateProgress(Math.round((index / total) * 100 + (pct / total)));
                }).then(function(result) {
                    var url = (result && result.url) ? result.url : null;
                    if (url) {
                        urls.push({url: url, token: (result && result.token) ? result.token : ''});
                        $list.find('.mlr-upload-status-pending').remove();
                        $list.append(buildFileItemHtml(file.name, url));
                        $hidden.val(JSON.stringify(urls));
                    }
                    nextFile(index + 1);
                }).catch(function(err) {
                    var errMsg = (err && err.message) ? err.message : 'خطای ناشناخته. نوع و حجم فایل را بررسی کنید.';
                    $errorEl.text('خطا در آپلود «' + escapeHtml(file.name) + '»: ' + errMsg).show();
                    $list.find('.mlr-upload-status-pending').remove();
                    nextFile(index + 1);
                });
            }
            nextFile(0);
        });
        $forms.each(function() { updateSubmitButton($(this)); });
    }

    $(document).on('submit', '.mlr-cooperation-form', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $form = $(this);
        if (!validateCooperationFileUploads($form)) {
            showSubmissionModal('error', 'لطفاً تمام فایل‌های اجباری را آپلود کنید و تا پایان آپلود صبر کنید.', $form);
            return false;
        }

        var formData = new FormData($form[0]);
        formData.set('action', 'mlr_submit_cooperation_request');
        formData.set('_wpnonce', mlr_coop_ajax.nonce);

        var submitBtn = $form.find('.mlr-submit-button');
        submitBtn.prop('disabled', true).text('در حال ارسال...');

        $.ajax({
            url: mlr_coop_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            timeout: 60000
        }).done(function(response) {
            if (response && response.success) {
                showSubmissionModal('success', (response.data && response.data.message) ? response.data.message : 'درخواست همکاری شما با موفقیت ثبت گردید.', $form);
                $form[0].reset();
                $form.find('input.mlr-uploaded-urls').val('[]');
                $form.find('.mlr-uploaded-files-list').empty();
                $form.find('.mlr-upload-error').hide().empty();
                updateSubmitButton($form);
            } else {
                var msg = (response && response.data && response.data.message) ? response.data.message : 'خطای ناشناخته رخ داد.';
                showSubmissionModal('error', msg, $form);
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            var msg = 'خطا در ارتباط با سرور. ';
            if (jqXHR.status === 0) {
                msg += 'اتصال اینترنت را بررسی کنید یا صفحه را رفرش کرده و دوباره تلاش نمایید.';
            } else if (jqXHR.status === 403) {
                msg += 'دسترسی مجاز نیست. صفحه را رفرش کرده و مجدداً ارسال کنید.';
            } else if (jqXHR.status >= 500) {
                msg += 'خطای سرور. لطفاً چند دقیقه دیگر تلاش کنید.';
            } else if (textStatus === 'parsererror' && jqXHR.responseText) {
                msg += 'پاسخ سرور نامعتبر است. در صورت تکرار، با مدیر سایت تماس بگیرید.';
            } else {
                msg += 'لطفاً اتصال اینترنت خود را بررسی کرده و مجدداً تلاش نمایید.';
            }
            showSubmissionModal('error', msg, $form);
        }).always(function() {
            submitBtn.prop('disabled', !validateCooperationFileUploads($form)).text('ارسال درخواست');
        });
        return false;
    });

    $(document).on('click', '.mlr-cooperation-form-wrapper .mlr-submission-overlay, .mlr-cooperation-form-wrapper .mlr-close-error-modal-btn, .mlr-cooperation-form-wrapper .mlr-close-success-modal-btn', function() {
        var $wrapper = $(this).closest('.mlr-cooperation-form-wrapper');
        $wrapper.find('.mlr-submission-overlay, .mlr-submission-modal').fadeOut(200);
    });

    $(document).on('click', '.mlr-cooperation-form-wrapper .mlr-submission-modal', function(e) {
        e.stopPropagation();
    });

    $(document).on('click', '.mlr-cooperation-form .mlr-view-file', function(e) {
        e.preventDefault();
        var url = $(this).closest('.mlr-uploaded-file-item').data('url');
        if (url) window.open(url, '_blank', 'noopener,noreferrer');
    });

    $(document).on('click', '.mlr-cooperation-form .mlr-remove-file', function(e) {
        e.preventDefault();
        var $item = $(this).closest('.mlr-uploaded-file-item');
        var urlToRemove = $item.data('url');
        var $group = $item.closest('.mlr-coop-file-upload-group');
        var $form = $group.closest('form.mlr-cooperation-form');
        var $hidden = $group.find('input.mlr-uploaded-urls');
        var urls = [];
        try { urls = JSON.parse($hidden.val() || '[]'); } catch (err) { urls = []; }
        urls = urls.filter(function(u) { return (typeof u === 'string' ? u : (u && u.url)) !== urlToRemove; });
        $hidden.val(JSON.stringify(urls));
        $item.remove();
        updateSubmitButton($form);
    });
});
