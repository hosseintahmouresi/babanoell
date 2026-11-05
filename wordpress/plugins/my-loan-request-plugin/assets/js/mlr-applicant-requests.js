jQuery(document).ready(function($) {
    
    // =========================================================
    // 1. GLOBAL MODAL SETUP
    // =========================================================
    if ($('#mlr-global-modal-overlay').length === 0) {
        $('body').append(`
            <div id="mlr-global-modal-overlay" class="mlr-modal-overlay" style="display:none;"></div>
            <div id="mlr-global-modal" class="mlr-modal" style="display:none;">
                <button class="mlr-modal-close">&times;</button>
                <div class="mlr-modal-content"></div>
            </div>
        `);
    }
    // Remove old modals inside container if any exist to prevent duplicates
    $('.mlr-user-requests .mlr-modal, .mlr-user-requests .mlr-modal-overlay').remove(); 

    // =========================================================
    // 1.2 DOCUMENTS CATEGORY TABS (Modal)
    // =========================================================
    function mlrInitDocsCategoryTabs(context) {
        var $ctx = context ? $(context) : $(document);
        $ctx.find('.mlr-docs-card').each(function() {
            var $card = $(this);
            var $btns = $card.find('.mlr-doc-cat-btn');
            var $sections = $card.find('.mlr-doc-cat-section');
            if ($btns.length === 0 || $sections.length === 0) return;

            var defaultCat = $card.attr('data-default-cat') || $btns.first().data('cat');
            if (!defaultCat) defaultCat = $btns.first().data('cat');

            $btns.removeClass('is-active');
            $sections.hide();

            $btns.filter('[data-cat="' + defaultCat + '"]').addClass('is-active');
            $sections.filter('[data-cat="' + defaultCat + '"]').show();
        });
    }

    function mlrNormalizeDocumentFlowState(state) {
        state = (state || '').toString().trim();
        var aliases = {
            'defect_documents': 'defect_documents',
            'نقص مدارک': 'defect_documents',
            'completion_documents': 'completion_documents',
            'تکمیل مدارک': 'completion_documents',
            'pending_documents': 'pending_documents',
            'در انتظار مدارک': 'pending_documents',
            'در انتظار تکمیل': 'pending_documents',
            'در انتظار تکمیل مدارک': 'pending_documents'
        };

        return aliases[state] || state;
    }

    function mlrGetDocumentFlowState(context) {
        var $ctx = context ? $(context) : $(document);
        var $modalBody = $ctx.is('.mlr-modal-body[data-document-flow-stage]')
            ? $ctx
            : $ctx.closest('.mlr-modal-body[data-document-flow-stage]');
        var state = $modalBody.attr('data-document-flow-stage') ||
            $ctx.attr('data-document-flow-stage') ||
            $ctx.find('.mlr-modal-body[data-document-flow-stage]').first().attr('data-document-flow-stage') ||
            '';

        return mlrNormalizeDocumentFlowState(state);
    }

    function mlrToggleDocumentBox($box, shouldShow) {
        $box.toggle(shouldShow);
        $box.find('input, select, textarea, button').prop('disabled', !shouldShow);
    }

    function mlrSyncDocumentFlowBoxes(context) {
        var $scope = context ? $(context) : $(document);
        var $bodies = $scope.is('.mlr-modal-body[data-document-flow-stage]')
            ? $scope
            : $scope.find('.mlr-modal-body[data-document-flow-stage]');

        $bodies.each(function() {
            var $body = $(this);
            var state = mlrGetDocumentFlowState($body);
            mlrToggleDocumentBox($body.find('.mlr-defect-documents-box'), state === 'defect_documents');
            mlrToggleDocumentBox($body.find('.mlr-completion-documents-box'), state === 'completion_documents' || state === 'pending_documents');
        });
    }

    function mlrShowUploadStateError(form, message) {
        var messageDiv = form.find('.mlr-upload-status');
        if (!messageDiv.length) {
            form.prepend('<div class="mlr-upload-status" style="display:none; margin:10px 0; padding:10px;"></div>');
            messageDiv = form.find('.mlr-upload-status');
        }
        messageDiv.show().removeClass('loading success').addClass('error').css({'background':'#fde8e8', 'color':'#c0392b', 'border':'1px solid #c0392b'}).html(message);
    }


    // =========================================================
    // 1.3 ACCORDION TOGGLES (Modal)
    // =========================================================
    function mlrInitAccordions(context) {
        var $ctx = context ? jQuery(context) : jQuery(document);
        // Ensure accordions start collapsed unless explicitly opened.
        $ctx.find('.mlr-acc-content, .mlr-glass-acc-content').each(function(){
            var $c = jQuery(this);
            if ($c.hasClass('is-open')) { $c.show(); }
            else { $c.hide(); }
        });

        $ctx.find('.mlr-acc-btn, .mlr-glass-acc-btn').each(function(){
            var $btn = jQuery(this);
            var target = $btn.attr('data-target');
            if (!target) return;
            var $content = jQuery('#' + target);
            if ($content.length) {
                // keep initial state collapsed
            }
        });
    }

    // Accordion toggles (delegated) - works for dynamically loaded modal HTML.
    $(document).on('click', '.mlr-acc-btn, .mlr-glass-acc-btn', function(e){
        e.preventDefault();
        var $btn = $(this);
        var targetId = $btn.attr('data-target');
        if (!targetId) return;
        var $content = $('#' + targetId);
        if ($content.length === 0) return;

        var isOpen = $content.hasClass('is-open');
        if (isOpen) {
            $content.removeClass('is-open').slideUp(150);
            $btn.removeClass('is-open');
        } else {
            $content.addClass('is-open').slideDown(150);
            $btn.addClass('is-open');
        }
    });

    // Print buttons (delegated) - prints the HTML inside the target element.
    $(document).on('click', '[data-print]', function(e){
        e.preventDefault();
        var targetId = $(this).attr('data-print');
        if (!targetId) return;
        var target = document.getElementById(targetId);
        if (!target) return;

        mlrPrintElement(target);
    });

    function mlrPrintElement(s) {
        var $source = $(s);
        var sourceEl = $source.length ? $source[0] : null;
        var isContract = !!(sourceEl && (
            $source.is('.mlr-print-area,.mlr-loan-contract-review-print,.mlr-print-layout-table,.mlr-hami-retirees-contract') ||
            $source.find('.mlr-print-layout-table,.mlr-hami-retirees-contract,.mlr-contract-document').length
        ));
        if (isContract && typeof window.mlrPrintContractNode === 'function') {
            window.mlrPrintContractNode(sourceEl, {title: 'چاپ قرارداد'});
            return;
        }

        var c = $source.html();
        if (!c) { alert('محتوا یافت نشد'); return; }

        var w = window.open('', '', 'height=760,width=980');
        if (!w) {
            alert('مرورگر اجازه باز کردن پنجره چاپ را نداد. لطفاً Pop-up را برای این سایت فعال کنید.');
            return;
        }

        var headAssets = '<base href="' + String(document.baseURI || window.location.href).replace(/"/g, '&quot;') + '">';
        document.querySelectorAll('link[rel="stylesheet"], style').forEach(function(node) {
            headAssets += node.outerHTML;
        });
        var isHami = $source.is('.mlr-hami-retirees-contract') || $source.find('.mlr-hami-retirees-contract').length > 0;
        var bodyClass = isHami ? 'mlr-print-hami-retirees' : '';
        var printOverrides = '<style>' +
            'html,body{background:#fff!important;color:#111!important;direction:rtl;text-align:right;margin:0;padding:0;}' +
            'body{font-family:"Yekan Bakh","YekanBakh","Vazirmatn",Tahoma,Arial,sans-serif!important;}' +
            '.mlr-print-area,.mlr-loan-contract-review-print{padding:0!important;max-height:none!important;overflow:visible!important;}' +
            '.mlr-no-print,.mlr-print-actions{display:none!important;}' +
            '.mlr-print-layout-table,.mlr-print-layout-table td,.mlr-print-layout-table th{border:0!important;padding:0!important;}' +
            'img{max-width:100%;height:auto;}' +
            '@media print{.mlr-print-layout-table{width:100%;border-collapse:collapse}.mlr-print-layout-table thead{display:table-header-group}.mlr-print-layout-table tfoot{display:table-footer-group}.mlr-print-layout-table tr{break-inside:auto;page-break-inside:auto}}' +
            '</style>';

        w.document.open();
        w.document.write('<!doctype html><html dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>چاپ قرارداد</title>' + headAssets + printOverrides + '</head><body class="' + bodyClass + '">' + c + '</body></html>');
        w.document.close();
        w.focus();

        var printWhenReady = function() {
            var images = Array.prototype.slice.call(w.document.images || []);
            var waits = images.map(function(img) {
                if (img.complete) return Promise.resolve();
                return new Promise(function(resolve) {
                    img.addEventListener('load', resolve, {once:true});
                    img.addEventListener('error', resolve, {once:true});
                });
            });
            if (w.document.fonts && w.document.fonts.ready) waits.push(w.document.fonts.ready.catch(function(){}));
            Promise.all(waits).then(function(){ setTimeout(function(){ w.print(); }, 120); });
        };
        setTimeout(printWhenReady, 150);
    }

    $(document).on('click', '.mlr-doc-cat-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $card = $btn.closest('.mlr-docs-card');
        var cat = $btn.data('cat');

        $card.find('.mlr-doc-cat-btn').removeClass('is-active');
        $btn.addClass('is-active');

        $card.find('.mlr-doc-cat-section').hide();
        $card.find('.mlr-doc-cat-section[data-cat="' + cat + '"]').show();
    });

    // =========================================================
    // 1.1 MODAL POSITION FIX (Themes with transformed <body>)
    // =========================================================
    function mlrIsBodyTransformed() {
        try {
            var t = window.getComputedStyle(document.body).transform;
            return (t && t !== 'none');
        } catch (e) {
            return false;
        }
    }

    function mlrPositionOpenModal($modal, $overlay) {
        var needsAbsolute = mlrIsBodyTransformed();
        var update = function() {
            var scrollTop = $(window).scrollTop();
            var winH = $(window).height();
            var docH = $(document).height();

            if (needsAbsolute) {
                $overlay.css({
                    position: 'absolute',
                    top: scrollTop,
                    left: 0,
                    width: '100%',
                    height: docH
                });

                $modal.css({
                    position: 'absolute',
                    top: (scrollTop + (winH / 2)),
                    left: '50%'
                });
            } else {
                $overlay.css({
                    position: 'fixed',
                    top: 0,
                    left: 0,
                    width: '100%',
                    height: '100%'
                });

                $modal.css({
                    position: 'fixed',
                    top: '50%',
                    left: '50%'
                });
            }
        };

        // Bind only once per open
        $(window).off('scroll.mlrModalPos resize.mlrModalPos').on('scroll.mlrModalPos resize.mlrModalPos', function() {
            if ($overlay.is(':visible') && $modal.is(':visible')) {
                update();
            }
        });

        update();
    }

    function mlrResetModalPosition($modal, $overlay) {
        $(window).off('scroll.mlrModalPos resize.mlrModalPos');
        $overlay.css({ position: 'fixed', top: 0, left: 0, width: '100%', height: '100%' });
        $modal.css({ position: 'fixed', top: '50%', left: '50%' });
    }


    

// =========================================================
// 2.1 REQUEST MESSAGES (CHAT)
// =========================================================
function mlrRefreshMessages(requestId, nonce) {
    var box = $('#mlr-messages-history-container');
    if (box.length === 0) return;
    $.post(mlr_applicant_ajax_obj.ajax_url, {
        action: 'mlr_applicant_get_request_messages',
        nonce: nonce || mlr_applicant_ajax_obj.details_nonce,
        request_id: requestId
    }).done(function(res) {
        if (res && res.success && res.data && typeof res.data.messages_html !== 'undefined') {
            box.html(res.data.messages_html);
        }
    });
}

$(document).on('click', '.mlr-refresh-messages-btn', function() {
    var requestId = $(this).data('request-id');
    var nonce = $(this).data('nonce') || mlr_applicant_ajax_obj.details_nonce;
    mlrRefreshMessages(requestId, nonce);
});

// --- START: Attachment Management (Frontend) ---
var mlrFrontAttachments = new DataTransfer();

function mlrResetFrontAttachments() {
    mlrFrontAttachments = new DataTransfer();
    $('#mlr-message-attachment-input-front').val('');
    $('#mlr-attachment-previews-front').html('');
}

function mlrUpdateFrontAttachmentPreview() {
    var previewContainer = $('#mlr-attachment-previews-front');
    previewContainer.html('');
    var files = mlrFrontAttachments.files;
    if (files.length > 0) {
        var fileList = $('<ul></ul>');
        for (var i = 0; i < files.length; i++) {
            fileList.append('<li><span class="dashicons dashicons-media-default"></span> ' + files[i].name + ' <span class="dashicons dashicons-dismiss mlr-remove-file-front" data-index="' + i + '" title="حذف"></span></li>');
        }
        previewContainer.append(fileList);
    }
}

// --- START: Attachment Preview Handler (Frontend) ---
$(document).on('change', '.mlr-context-user #mlr-message-attachment-input-front', function() {
    for (var i = 0; i < this.files.length; i++) {
        mlrFrontAttachments.items.add(this.files[i]);
    }
    this.files = mlrFrontAttachments.files;
    mlrUpdateFrontAttachmentPreview();
});

$(document).on('click', '.mlr-context-user .mlr-send-message-btn', function(e) {
    e.preventDefault();
    var btn = $(this);
    var requestId = btn.data('request-id');
    var nonce = btn.data('nonce') || mlr_applicant_ajax_obj.details_nonce;
    var textarea = btn.closest('.mlr-add-message-form').find('.mlr-new-message-text');
    var message = textarea.val() || '';

    var fileInput = btn.closest('.mlr-add-message-form').find('#mlr-message-attachment-input-front')[0]; // Synced
    var files = fileInput ? fileInput.files : [];

    if (!message.trim() && files.length === 0) {
        alert('متن پیام خالی است.');
        return;
    }

    btn.prop('disabled', true).text('در حال ارسال...');

    var formData = new FormData();
    formData.append('action', 'mlr_applicant_add_request_message');
    formData.append('nonce', nonce);
    formData.append('request_id', requestId);
    formData.append('message', message);
    for (var i = 0; i < files.length; i++) {
        formData.append('attachments[]', files[i]);
    }

    $.ajax({
        url: mlr_applicant_ajax_obj.ajax_url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
    }).done(function(res) {
        btn.prop('disabled', false).text('ارسال پیام');
        if (res && res.success) {
            textarea.val('');
            mlrResetFrontAttachments();
            mlrRefreshMessages(requestId, nonce);
        } else {
            var msg = (res && res.data && res.data.message) ? res.data.message : 'خطا در ارسال پیام.';
            alert(msg);
        }
    }).fail(function() {
        btn.prop('disabled', false).text('ارسال پیام');
        alert('خطا در ارتباط با سرور.');
    });

});

// Remove attachment handler (Frontend)
$(document).on('click', '.mlr-context-user #mlr-attachment-previews-front .mlr-remove-file-front', function() {
    var index = $(this).data('index');
    var dt = new DataTransfer();
    var files = mlrFrontAttachments.files;
    for (var i = 0; i < files.length; i++) {
        if (i !== index) dt.items.add(files[i]);
    }
    mlrFrontAttachments = dt;
    $('#mlr-message-attachment-input-front')[0].files = mlrFrontAttachments.files;
    mlrUpdateFrontAttachmentPreview();
});

// =========================================================
    // 2. CUSTOM JALALI DATEPICKER V4
    // =========================================================
    (function() {
        if ($('.jalali-datepicker').length === 0) return;
        let activeInput = null, datepicker, yearSelect, monthSelect, grid, currentYear, currentMonth;
        const dayNames = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
        const monthNames = ["فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور", "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند"];

        function createDatePickerUI() {
            if ($('.mlr-custom-datepicker').length > 0) return;
            let yearOptions = '<option value="" disabled>سال</option>';
            const currentJalaliYear = parseInt(new Date().toLocaleDateString('fa-IR-u-nu-latn').split('/')[0]) || 1403;
            for (let y = currentJalaliYear + 1; y >= 1350; y--) yearOptions += `<option value="${y}">${y}</option>`;
            let monthOptions = '<option value="" disabled>ماه</option>';
            monthNames.forEach((name, index) => monthOptions += `<option value="${index + 1}">${name}</option>`);

            const dp_html = `
                <div class="mlr-custom-datepicker" style="display:none; position:absolute; z-index:2147483647; background:#fff; border:1px solid #ccc; border-radius:4px; padding:10px; width:220px; box-shadow: 0 3px 6px rgba(0,0,0,0.1);">
                    <div class="mlr-dp-header" style="display:flex; justify-content:space-between; margin-bottom:10px;">
                        <select class="mlr-dp-select year-select" required style="width:48%; padding: 4px; border:1px solid #ddd; border-radius:3px;">${yearOptions}</select>
                        <select class="mlr-dp-select month-select" required style="width:48%; padding: 4px; border:1px solid #ddd; border-radius:3px;">${monthOptions}</select>
                    </div>
                    <div class="mlr-dp-grid" style="display:grid; grid-template-columns:repeat(7, 1fr); gap:2px; text-align:center;"></div>
                </div>`;
            $('body').append(dp_html);
            datepicker = $('.mlr-custom-datepicker');
            yearSelect = datepicker.find('.year-select');
            monthSelect = datepicker.find('.month-select');
            grid = datepicker.find('.mlr-dp-grid');

            yearSelect.on('change', () => { currentYear = parseInt(yearSelect.val()); if (currentMonth) renderCalendar(); });
            monthSelect.on('change', () => { currentMonth = parseInt(monthSelect.val()); if (currentYear) renderCalendar(); });
            grid.on('click', '.mlr-dp-day:not(.disabled)', selectDate);
            $(document).on('click', function(e) {
                if (datepicker.is(":visible") && !$(e.target).closest('.mlr-custom-datepicker').length && !$(e.target).is(activeInput)) {
                    datepicker.fadeOut(200); activeInput = null;
                }
            });
        }
        function isJalaliLeap(year) { return [1, 5, 9, 13, 17, 22, 26, 30].indexOf(year % 33) > -1; }
        function toGregorian(jy, jm, jd) {
            var sal_a, gy, gm, gd, days; jy += 1595; days = -355668 + (365 * jy) + (~~(jy / 33) * 8) + ~~(((jy % 33) + 3) / 4) + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
            gy = 400 * ~~(days / 146097); days %= 146097; if (days > 36524) { gy += 100 * ~~(--days / 36524); days %= 36524; if (days >= 365) days++; }
            gy += 4 * ~~(days / 1461); days %= 1461; if (days > 365) { gy += ~~((days - 1) / 365); days = (days - 1) % 365; }
            gd = days + 1; sal_a = [0, 31, ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
            for (gm = 0; gm < 13 && gd > sal_a[gm]; gm++) gd -= sal_a[gm];
            return new Date(gy, gm - 1, gd);
        }
        function renderCalendar() {
            grid.empty(); dayNames.forEach(name => grid.append(`<span class="mlr-dp-day-name" style="font-size:12px; font-weight:bold; color:#666; padding:4px 0;">${name}</span>`));
            const daysInMonth = (currentMonth <= 6) ? 31 : (currentMonth <= 11) ? 30 : (isJalaliLeap(currentYear)) ? 30 : 29;
            const startDayIndex = (new Date(toGregorian(currentYear, currentMonth, 1)).getDay() + 1) % 7;
            for (let i = 0; i < startDayIndex; i++) grid.append('<span class="disabled"></span>');
            for (let day = 1; day <= daysInMonth; day++) grid.append($(`<span class="mlr-dp-day" style="padding:6px 2px; cursor:pointer; border-radius:3px;">${day}</span>`).data('day', day).hover(function(){ $(this).css('background','#f0f0f0'); }, function(){ $(this).css('background','transparent'); }));
        }
        function selectDate() {
            if (!activeInput) return;
            $(activeInput).val(`${currentYear}/${String(currentMonth).padStart(2, '0')}/${String($(this).data('day')).padStart(2, '0')}`);
            datepicker.fadeOut(200); activeInput = null;
        }
        $(document).on('click', '.jalali-datepicker', function() {
            activeInput = this; grid.empty();
            const existingDate = $(this).val(), now = new Date(), todayJalali = new Intl.DateTimeFormat('fa-IR-u-nu-latn', { year: 'numeric', month: 'numeric' }).format(now).split('/');
            if (existingDate && existingDate.match(/^\d{4}\/\d{2}\/\d{2}$/)) { const parts = existingDate.split('/'); currentYear = parseInt(parts[0]); currentMonth = parseInt(parts[1]); } else { currentYear = parseInt(todayJalali[0]); currentMonth = parseInt(todayJalali[1]); }
            yearSelect.val(currentYear); monthSelect.val(currentMonth); renderCalendar();
            const inputPos = $(activeInput).offset();
            datepicker.css({ top: inputPos.top + $(activeInput).outerHeight() + 5, left: inputPos.left, right: 'auto' }).fadeIn(200);
        });
        createDatePickerUI();
    })();

    // =========================================================
    // 3. CORE: LOAD REQUESTS & MODALS
    // =========================================================
    function loadRequests() {
        var container = $('.mlr-user-requests:not(.mlr-settlement-cycles)');
        if (!container.length) return;

        var data = {
            action: 'mlr_applicant_get_requests',
            nonce: mlr_applicant_ajax_obj.filter_nonce,
            search: container.find('#mlr-search').val() || '',
            status: container.find('#mlr-status').val() || '',
            settlement_status: container.find('#mlr-settlement-status').val() || '',
            date_from: container.find('#mlr-date-from').val() || '',
            date_to: container.find('#mlr-date-to').val() || '',
            allowed_statuses: mlr_applicant_ajax_obj.allowed_statuses || [],
            table_view: mlr_applicant_ajax_obj.table_view || 'default'
        };
        var colCount = 12;

        $.ajax({
            url: mlr_applicant_ajax_obj.ajax_url, 
            type: 'POST', 
            data: data,
            beforeSend: function() {
                container.find('#mlr-requests-table-body').html('<tr><td colspan="' + colCount + '" style="text-align:center; padding: 20px;">در حال بارگذاری لیست...</td></tr>');
            },
            success: function(res) {
                if (res.success) {
                    container.find('#mlr-requests-table-body').html(res.data.html);
                    container.find('#mlr-requests-table-body tr[data-id]').addClass('mlr-request-row-frontend');
                } else {
                    container.find('#mlr-requests-table-body').html('<tr><td colspan="' + colCount + '" style="text-align:center; color: red;">' + (res.data.message || 'خطا') + '</td></tr>');
                }
            },
            error: function() {
                container.find('#mlr-requests-table-body').html('<tr><td colspan="' + colCount + '" style="text-align:center; color: red;">خطای ارتباط با سرور.</td></tr>');
            }
        });
    }

    function showDetailsModal(requestId) {
        const modal = $('#mlr-global-modal');
        const content = modal.find('.mlr-modal-content');
        $('body').addClass('mlr-modal-open');
        $('#mlr-global-modal-overlay').fadeIn(200);
        modal.css('display', 'flex').hide().fadeIn(200); // Ensure flex display before fading in
        mlrPositionOpenModal(modal, $('#mlr-global-modal-overlay'));
        content.html('<p style="text-align:center; padding: 40px; font-size:16px;">در حال دریافت اطلاعات پرونده...</p>');

        $.post(mlr_applicant_ajax_obj.ajax_url, {
            action: 'mlr_applicant_get_request_details',
            nonce: mlr_applicant_ajax_obj.details_nonce,
            request_id: requestId
        })
        .done(function(res) {
            if (res.success) {
                content.html(res.data.html).attr('data-current-request-id', requestId);
                content.addClass('mlr-context-user'); // Add context class for isolation
                mlrInitDocsCategoryTabs(content);
                mlrSyncDocumentFlowBoxes(content);
                // Init accordions (contract/invoice)
                mlrInitAccordions(content);
                // Auto-load messages thread (if present)
                mlrRefreshMessages(requestId, mlr_applicant_ajax_obj.details_nonce);
                if (typeof $.fn.persianDatepicker === 'function') {
                    content.find('.jalali-datepicker').persianDatepicker({ format: 'YYYY/MM/DD', autoClose: true });
                }
                mlrResetFrontAttachments(); // Reset attachments on open
            } else {
                 content.html(`<p style="color:red; text-align:center; padding:20px;">${res.data.message}</p>`);
            }
        })
        .fail(function() {
            content.html('<p class="mlr-error" style="text-align:center; padding:20px;">خطای برقراری ارتباط با سرور.</p>');
        });
    }

    // Close modal handler - Namespaced to prevent duplicates if multiple scripts load
    $(document).off('click.mlrModalClose').on('click.mlrModalClose', '#mlr-global-modal-overlay, .mlr-modal-close', function() {
        $('#mlr-global-modal-overlay, #mlr-global-modal').fadeOut(200, function() {
            $('body').removeClass('mlr-modal-open');
            // Reset position for the next time it opens
            mlrResetModalPosition($('#mlr-global-modal'), $('#mlr-global-modal-overlay'));
        });
    });

    $(document).on('click', '#mlr-requests-table-body tr.mlr-request-row-frontend', function(e) {
        if (!$(e.target).closest('button, a, input, select, textarea').length) {
            showDetailsModal($(this).data('id'));
        }
    });

    $(document).on('click', '#mlr-requests-table-body .mlr-view-details-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var row = $(this).closest('tr');
        var id = row.data('id');
        if (id) showDetailsModal(id);
    });

    $(document).on('mlr:requests:reload', function() { loadRequests(); });

    // =========================================================
    // 4. FIX: UPLOAD ADDITIONAL DOCS - MANUAL APPEND
    // =========================================================
    $(document).on('submit', '.mlr-universal-upload-form', function(e) {
        e.preventDefault();
        if (window.MLRDashboardDocumentUploadActive) return;
        
        var form = $(this);
        var submitBtn = form.find('button[type="submit"]');
        var messageDiv = form.find('.mlr-upload-status');
        var documentState = mlrGetDocumentFlowState(form);

        mlrSyncDocumentFlowBoxes(form.closest('.mlr-modal-content'));
        if (documentState !== 'defect_documents') {
            mlrShowUploadStateError(form, 'این فرم فقط برای مرحله نقص مدارک فعال است.');
            return;
        }
        
        // 1. Locate the file input
        var fileInput = form.find('input[type="file"]')[0];

        // 2. Validate existence
        if (!fileInput || fileInput.files.length === 0) {
            messageDiv.show().removeClass('loading success').addClass('error').css({'background':'#fde8e8', 'color':'#c0392b', 'border':'1px solid #c0392b'}).html('لطفاً حداقل یک فایل را انتخاب کنید.');
            return;
        }

        // 3. Manually build FormData
        var formData = new FormData();
        formData.append('action', 'mlr_applicant_upload_additional_docs');
        formData.append('nonce', mlr_applicant_ajax_obj.details_nonce);
        formData.append('request_id', form.find('input[name="request_id"]').val());
        formData.append('document_flow_state', 'defect_documents');
        
        var userNote = form.find('textarea[name="user_note"]').val();
        if(userNote) formData.append('user_note', userNote);

        // 4. Append files manually
        $.each(fileInput.files, function(i, file) {
            formData.append('additional_files[]', file);
        });

        // 5. Send AJAX
        $.ajax({
            url: mlr_applicant_ajax_obj.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                messageDiv.show().removeClass('error success').addClass('loading').css({'background':'#e8f4fd', 'color':'#2980b9', 'border':'1px solid #2980b9'}).html('در حال آپلود و پردازش... لطفاً شکیبا باشید.');
                submitBtn.prop('disabled', true).text('در حال ارسال...');
            },
            success: function(response) {
                if (response.success) {
                    messageDiv.removeClass('error loading').addClass('success').css({'background':'#d4edda', 'color':'#155724', 'border':'1px solid #155724'}).html(response.data.message);
                    submitBtn.text('ارسال شد');
                    setTimeout(function() {
                        $('#mlr-global-modal-overlay, #mlr-global-modal').fadeOut(200);
                        loadRequests(); // Refresh the list
                    }, 2500);
                } else {
                    messageDiv.removeClass('success loading').addClass('error').css({'background':'#fde8e8', 'color':'#c0392b', 'border':'1px solid #c0392b'}).html(response.data.message);
                    submitBtn.prop('disabled', false).text('ارسال مدارک و بررسی مجدد');
                }
            },
            error: function() {
                messageDiv.removeClass('success loading').addClass('error').css({'background':'#fde8e8', 'color':'#c0392b', 'border':'1px solid #c0392b'}).html('خطای غیرمنتظره در ارتباط با سرور.');
                submitBtn.prop('disabled', false).text('ارسال مدارک و بررسی مجدد');
            }
        });
    });
    
    // =========================================================
    // 5. COMPLETE DOCS FORM
    // =========================================================
    $(document).on('submit', '#mlr-complete-docs-form, .mlr-completed-docs-form', function(e) {
        e.preventDefault();
        if (window.MLRDashboardDocumentUploadActive) return;
        var form = $(this);
        var messageDiv = form.find('.mlr-upload-status');
        if(!messageDiv.length) { form.prepend('<div class="mlr-upload-status" style="display:none; margin:10px 0; padding:10px;"></div>'); messageDiv = form.find('.mlr-upload-status'); }
        var documentState = mlrGetDocumentFlowState(form);

        mlrSyncDocumentFlowBoxes(form.closest('.mlr-modal-content'));
        if (documentState !== 'completion_documents' && documentState !== 'pending_documents') {
            mlrShowUploadStateError(form, 'این فرم فقط برای مرحله تکمیل مدارک فعال است.');
            return;
        }
        
        var formData = new FormData(this);
        formData.append('action', 'mlr_applicant_submit_completed_docs');
        formData.append('nonce', mlr_applicant_ajax_obj.details_nonce);
        formData.append('document_flow_state', documentState);

        $.ajax({
            url: mlr_applicant_ajax_obj.ajax_url, type: 'POST', data: formData, processData: false, contentType: false,
            beforeSend: () => { messageDiv.show().removeClass().addClass('mlr-upload-status loading').css({'background':'#e8f4fd', 'color':'#2980b9', 'border':'1px solid #2980b9'}).html('در حال ارسال...'); form.find('button').prop('disabled', true); },
            success: (res) => {
                if (res.success) {
                    messageDiv.removeClass().addClass('mlr-upload-status success').css({'background':'#d4edda', 'color':'#155724', 'border':'1px solid #155724'}).html(res.data.message);
                    setTimeout(() => { $('#mlr-global-modal-overlay, #mlr-global-modal').fadeOut(200); loadRequests(); }, 2500);
                } else {
                    messageDiv.removeClass().addClass('mlr-upload-status error').css({'background':'#fde8e8', 'color':'#c0392b', 'border':'1px solid #c0392b'}).html(res.data.message);
                    form.find('button').prop('disabled', false);
                }
            },
            error: () => { messageDiv.removeClass().addClass('mlr-upload-status error').css({'background':'#fde8e8', 'color':'#c0392b', 'border':'1px solid #c0392b'}).html('خطای سرور.'); form.find('button').prop('disabled', false); }
        });
    });

    // کمکی: اضافه/جایگزینی request_id در URL برای اطمینان از دریافت در سرور
    function mlrEnsureRequestIdInUrl(url, requestId) {
        if (!url || !requestId) return url;
        var param = 'request_id=' + encodeURIComponent(String(requestId));
        var replaced = url.replace(/([?&])request_id=[^&]*/gi, '$1' + param);
        if (replaced !== url) return replaced;
        return url + (url.indexOf('?') !== -1 ? '&' : '?') + param;
    }

    // Guarantor forms are handled centrally by mlr-guarantor-form.js.
    $(document).on('mlr:guarantor:saved', function(e, data) {
        if (data && data.requestId) {
            setTimeout(function() { showDetailsModal(parseInt(data.requestId, 10)); }, 450);
        }
    });

    // =========================================================
    // 6. OTHER EVENTS
    // =========================================================
    // View uploaded files/documents in a new tab.
    // Only intercept clicks when an href exists.
    $(document).on('click', '.mlr-view-file-btn', function(e) {
        var href = $(this).attr('href');
        if (!href) return; // Let other handlers run.
        e.preventDefault();
        window.open(href, '_blank', 'noopener');
    });
    $(document).on('click', '.mlr-print-contract-step-btn', function() { mlrPrintElement('.printable-contract-area'); });
    $(document).on('click', '.mlr-print-invoice-step-btn', function() { mlrPrintElement('.printable-invoice-area'); });
    $(document).on('click', '.mlr-print-btn', function() { mlrPrintElement('.mlr-modal-content'); });
    
    if ($('.mlr-user-requests:not(.mlr-settlement-cycles)').length > 0) loadRequests();
    $(document).on('click', '#mlr-filter-btn', () => { if (!$('#mlr-search').closest('.mlr-settlement-cycles').length) loadRequests(); });
    $(document).on('keyup', '#mlr-search', (e) => { if (e.key === 'Enter' && !$(e.target).closest('.mlr-settlement-cycles').length) loadRequests(); });

    $(document).on('click', '.mlr-next-step-btn', function() {
        var form = $(this).closest('form'), current = form.find('.mlr-step-pane.active'), valid = true;
        current.find('input[required], select[required], textarea[required]').each(function() {
             if (!$(this).val() && (!$(this)[0].files || !$(this)[0].files.length)) { valid = false; $(this).css('border-color', 'red'); } else { $(this).css('border-color', ''); }
        });
        if(!valid) { alert('لطفاً فیلدهای اجباری را پر کنید.'); return; }
        var next = current.next('.mlr-step-pane');
        if (next.length) { current.removeClass('active'); next.addClass('active'); var step = next.data('step-pane'); form.closest('.mlr-complete-docs-section').find(`.mlr-step[data-step="${step-1}"]`).addClass('completed'); form.closest('.mlr-complete-docs-section').find(`.mlr-step[data-step="${step}"]`).addClass('active'); updateStepper(next); }
    });
    $(document).on('click', '.mlr-prev-step-btn', function() {
        var form = $(this).closest('form'), current = form.find('.mlr-step-pane.active'), prev = current.prev('.mlr-step-pane');
        if (prev.length) { current.removeClass('active'); prev.addClass('active'); var step = current.data('step-pane'); form.closest('.mlr-complete-docs-section').find(`.mlr-step[data-step="${step}"]`).removeClass('active'); updateStepper(prev); }
    });
    function updateStepper(p) { var f = p.closest('form'); f.find('.mlr-prev-step-btn').toggle(!p.is(':first-child')); f.find('.mlr-next-step-btn').toggle(!p.is(':last-child')); f.find('.mlr-submit-btn').toggle(p.is(':last-child')); }

    $(document).on('click', '.mlr-cycle-row-frontend', function() {
        var row = $(this), id = row.data('cycle-id'), det = $('#cycle-details-frontend-' + id), con = det.find('.cycle-details-container');
        row.toggleClass('open'); det.toggle();
        if (!row.data('loaded') && det.is(':visible')) {
            con.find('.spinner-container').show();
            $.post(mlr_applicant_ajax_obj.ajax_url, { action: 'mlr_get_user_cycle_details', nonce: mlr_applicant_ajax_obj.cycle_nonce, cycle_id: id }).done((res) => {
                if (res.success) { con.html(res.data.html).find('tr[data-id]').addClass('mlr-request-row-frontend'); row.data('loaded', true); } else { con.html(res.data.message); }
            });
        }
    });
});
