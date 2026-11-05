jQuery(document).ready(function($) {

    // =========================================================
    // MODAL POSITION FIX (Admin / themes that transform <body>)
    // =========================================================
    function mlrAdminIsBodyTransformed() {
        try {
            var t = window.getComputedStyle(document.body).transform;
            return (t && t !== 'none');
        } catch (e) { return false; }
    }

    function mlrPositionAdminModal($modal, $overlay) {
        var needsAbsolute = mlrAdminIsBodyTransformed();
        var update = function() {
            var scrollTop = $(window).scrollTop();
            var winH = $(window).height();
            var docH = $(document).height();

            if (needsAbsolute) {
                $overlay.css({ position: 'absolute', top: scrollTop, left: 0, width: '100%', height: docH });
                $modal.css({ position: 'absolute', top: (scrollTop + (winH / 2)), left: '50%', transform: 'translate(-50%, -50%)', right: 'auto', bottom: 'auto', margin: 0 });
            } else {
                $overlay.css({ position: 'fixed', top: 0, left: 0, width: '100%', height: '100%' });
                $modal.css({ position: 'fixed', top: '50%', left: '50%', transform: 'translate(-50%, -50%)', right: 'auto', bottom: 'auto', margin: 0 });
            }
        };

        // Ensure flex layout so inner sticky header and scrolling behave correctly
        // $modal.css('display', 'flex'); // Handled in click event

        $(window).off('scroll.mlrAdminModalPos resize.mlrAdminModalPos').on('scroll.mlrAdminModalPos resize.mlrAdminModalPos', function() {
            if ($overlay.is(':visible') && $modal.is(':visible')) { update(); }
        });

        update();
    }

    function mlrResetAdminModalPosition($modal, $overlay) {
        $(window).off('scroll.mlrAdminModalPos resize.mlrAdminModalPos');
        $overlay.css({ position: 'fixed', top: 0, left: 0, width: '100%', height: '100%' });
        $modal.css({ position: 'fixed', top: '50%', left: '50%', transform: 'translate(-50%, -50%)', right: 'auto', bottom: 'auto', margin: 0 });
    }




    function mlrAdminPrintContractReviewTarget(targetId) {
        if (!targetId) return;
        var $target = $('#' + targetId);
        if (!$target.length) return;
        if (typeof window.mlrPrintContractNode === 'function') {
            window.mlrPrintContractNode($target[0], {title: 'قرارداد دریافت تسهیلات'});
            return;
        }
        var popup = window.open('', '_blank', 'width=980,height=760');
        if (!popup) {
            alert('مرورگر اجازه باز کردن پنجره چاپ را نداد. لطفاً Pop-up را برای این سایت فعال کنید.');
            return;
        }

        var headAssets = '<base href="' + String(document.baseURI || window.location.href).replace(/"/g, '&quot;') + '">';
        document.querySelectorAll('link[rel="stylesheet"], style').forEach(function(node) {
            headAssets += node.outerHTML;
        });
        var isHami = $target.is('.mlr-hami-retirees-contract') || $target.find('.mlr-hami-retirees-contract').length > 0;
        var bodyClass = isHami ? 'mlr-print-hami-retirees' : '';
        var printOverrides = '<style>' +
            'html,body{background:#fff!important;color:#111!important;direction:rtl;text-align:right;margin:0;padding:0;}' +
            'body{font-family:"Yekan Bakh","YekanBakh","Vazirmatn",Tahoma,Arial,sans-serif!important;}' +
            '.mlr-loan-contract-review-print,.mlr-print-area{padding:0!important;max-height:none!important;overflow:visible!important;}' +
            '.mlr-no-print,.mlr-loan-contract-review-actions{display:none!important;}' +
            'img{max-width:100%;height:auto;}' +
            '</style>';

        popup.document.open();
        popup.document.write('<!doctype html><html dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>قرارداد دریافت تسهیلات</title>' + headAssets + printOverrides + '</head><body class="' + bodyClass + '">' + $target.html() + '</body></html>');
        popup.document.close();
        popup.focus();

        setTimeout(function() {
            var images = Array.prototype.slice.call(popup.document.images || []);
            var waits = images.map(function(img) {
                if (img.complete) return Promise.resolve();
                return new Promise(function(resolve) {
                    img.addEventListener('load', resolve, {once:true});
                    img.addEventListener('error', resolve, {once:true});
                });
            });
            if (popup.document.fonts && popup.document.fonts.ready) waits.push(popup.document.fonts.ready.catch(function(){}));
            Promise.all(waits).then(function() { setTimeout(function() { popup.print(); }, 120); });
        }, 150);
    }

    $(document).on('click', '[data-mlr-print-target]', function(e) {
        e.preventDefault();
        mlrAdminPrintContractReviewTarget($(this).attr('data-mlr-print-target'));
    });

    $(document).on('click', '[data-mlr-contract-details]', function(e) {
        e.preventDefault();
        var targetId = $(this).attr('data-mlr-contract-details');
        var el = document.getElementById(targetId);
        if (!el) return;
        el.open = true;
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    // =========================================================
    // DOCUMENTS CATEGORY TABS (Details Modal)
    // =========================================================
    function mlrAdminInitDocsCategoryTabs(context) {
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

    // --- START: SHARED JALALI DATEPICKER (ADMIN VERSION) ---
    (function() {
        // If centralized datepicker is present, do not initialize the admin fallback to avoid duplicates
        if (window.MLR_JALALI_DATEPICKER_ACTIVE) { return; }

        // جلوگیری از اجرای چندباره
        if ($('#mlr-admin-datepicker').length > 0) return;

        let activeInput = null;
        let datepicker, yearSelect, monthSelect, grid;
        let currentYear, currentMonth;

        const dayNames = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
        const monthNames = ["فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور", "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند"];

        // 1. ساخت HTML تقویم (فقط یکبار)
        function initDatepicker() {
            // محاسبه سال جاری
            const now = new Date();
            const todayJalali = new Intl.DateTimeFormat('fa-IR-u-nu-latn', { year: 'numeric' }).format(now);
            const thisYear = parseInt(todayJalali) || 1403;

            let yearOptions = '<option value="" disabled>سال</option>';
            for (let y = thisYear + 1; y >= 1350; y--) {
                yearOptions += `<option value="${y}">${y}</option>`;
            }

            let monthOptions = '<option value="" disabled>ماه</option>';
            monthNames.forEach((name, index) => {
                monthOptions += `<option value="${index + 1}">${name}</option>`;
            });

            // استایل Inline برای تضمین نمایش صحیح در ادمین
            const html = `
                <div id="mlr-admin-datepicker" style="display:none; position:absolute; z-index:9999999; background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:10px; width:220px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); font-family: Tahoma, sans-serif; direction: rtl;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                        <select class="year-select" style="width:48%; height:30px; font-size:12px;">${yearOptions}</select>
                        <select class="month-select" style="width:48%; height:30px; font-size:12px;">${monthOptions}</select>
                    </div>
                    <div class="mlr-dp-grid" style="display:grid; grid-template-columns:repeat(7, 1fr); gap:2px; text-align:center;"></div>
                </div>`;

            $('body').append(html);

            datepicker = $('#mlr-admin-datepicker');
            yearSelect = datepicker.find('.year-select');
            monthSelect = datepicker.find('.month-select');
            grid = datepicker.find('.mlr-dp-grid');

            // رویدادهای داخلی تقویم
            yearSelect.on('change', function(e) { e.stopPropagation(); currentYear = parseInt($(this).val()); renderCalendar(); });
            monthSelect.on('change', function(e) { e.stopPropagation(); currentMonth = parseInt($(this).val()); renderCalendar(); });
            
            // انتخاب روز
            grid.on('click', '.day-cell', function(e) {
                e.stopPropagation();
                if (!activeInput) return;
                
                const day = $(this).data('day');
                const formattedDate = `${currentYear}/${String(currentMonth).padStart(2, '0')}/${String(day).padStart(2, '0')}`;
                
                $(activeInput).val(formattedDate).trigger('change'); // تریگر change برای فرم‌ها
                datepicker.fadeOut(100);
                activeInput = null;
            });

            // کلیک روی خود تقویم باعث بسته شدن نشود
            datepicker.on('click', function(e){ e.stopPropagation(); });
        }

        // 2. محاسبات تقویم
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
            if(!currentYear || !currentMonth) return;
            grid.empty();
            // هدر روزها
            dayNames.forEach(d => grid.append(`<span style="font-size:11px; font-weight:bold; color:#777; padding:4px 0;">${d}</span>`));
            
            const daysInMonth = (currentMonth <= 6) ? 31 : (currentMonth <= 11) ? 30 : (isJalaliLeap(currentYear)) ? 30 : 29;
            const startDayIndex = (new Date(toGregorian(currentYear, currentMonth, 1)).getDay() + 1) % 7;
            
            // روزهای خالی قبل از شروع ماه
            for (let i = 0; i < startDayIndex; i++) grid.append('<span></span>');
            
            // روزهای ماه
            for (let day = 1; day <= daysInMonth; day++) {
                grid.append($(`<span class="day-cell" style="padding:5px; cursor:pointer; border-radius:3px; font-size:12px; transition:0.2s;">${day}</span>`)
                    .data('day', day)
                    .hover(
                        function(){ $(this).css('background', '#f0f0f1'); }, 
                        function(){ $(this).css('background', 'transparent'); }
                    )
                );
            }
        }

        // 3. تابع نمایش تقویم
        function openDatepicker(inputEl) {
            initDatepicker(); // اطمینان از وجود تقویم
            
            activeInput = inputEl;
            const val = $(activeInput).val();
            
            // استخراج تاریخ فعلی یا امروز
            if (val && val.match(/^\d{4}\/\d{2}\/\d{2}$/)) {
                const parts = val.split('/');
                currentYear = parseInt(parts[0]);
                currentMonth = parseInt(parts[1]);
            } else {
                const now = new Date();
                const todayParts = new Intl.DateTimeFormat('fa-IR-u-nu-latn', { year: 'numeric', month: 'numeric' }).format(now).split('/');
                currentYear = parseInt(todayParts[0]);
                currentMonth = parseInt(todayParts[1]);
            }
            
            yearSelect.val(currentYear);
            monthSelect.val(currentMonth);
            renderCalendar();

            // موقعیت‌دهی دقیق زیر فیلد
            const offset = $(activeInput).offset();
            const height = $(activeInput).outerHeight();
            
            datepicker.css({
                top: (offset.top + height + 2) + 'px',
                left: 'auto',
                right: ($(window).width() - (offset.left + $(activeInput).outerWidth())) + 'px',
                display: 'block'
            });
        }

        // 4. اتصال رویدادها (Delegation)
        // کلیک روی اینپوت یا آیکون تقویم
        $(document).on('click', '.jalali-datepicker, .mlr-datepicker-trigger', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            let targetInput;
            if ($(this).hasClass('mlr-datepicker-trigger')) {
                // اگر روی آیکون کلیک شد، اینپوت قبلی‌اش را پیدا کن
                targetInput = $(this).prev('.jalali-datepicker');
            } else {
                targetInput = $(this);
            }
            
            if (targetInput.length) {
                openDatepicker(targetInput[0]);
            }
        });

        // بستن با کلیک بیرون
        $(document).on('click', function(e) {
            if (datepicker && datepicker.is(':visible')) {
                datepicker.fadeOut(100);
                activeInput = null;
            }
        });

        // اجرا در شروع کار
        initDatepicker();

    })();
    // --- END DATEPICKER ---


    // --- Other Admin Logics (Status, Cycles, Modals...) ---
    $(document).on('change', '.mlr-status-select', function() {
        var select = $(this), rid = select.data('id'), status = select.val(), spinner = select.next('.spinner');
        spinner.addClass('is-active'); select.prop('disabled', true);
        $.post(mlrAjax.ajaxurl, { action: 'mlr_update_cooperation_status', nonce: mlrAjax.nonce, request_id: rid, status: status }, function(res) {
            spinner.removeClass('is-active'); select.prop('disabled', false);
            if(res.success) select.data('original-value', status);
            else { alert(res.data.message || 'Error'); select.val(select.data('original-value')); }
        }).fail(() => { spinner.removeClass('is-active'); select.prop('disabled', false); alert('Error'); });
    });

    $(document).on('click', '.mlr-cycle-row', function(e) {
        e.stopPropagation();
        var row = $(this), id = row.data('cycle-id'), details = $('#cycle-details-' + id), container = details.find('.cycle-details-container');
        row.toggleClass('open'); details.toggle();
        if(!row.data('loaded') && details.is(':visible')) {
            container.html('<div style="text-align:center;padding:15px;"><span class="spinner is-active"></span></div>');
            $.post(mlrAjax.ajaxurl, { action: 'mlr_get_cycle_details', nonce: mlrAjax.nonce, cycle_id: id }, function(res) {
                container.html(res.success ? res.data.html : res.data.message);
                if(res.success) row.data('loaded', true);
            });
        }
    });

    $(document).on('click', '.mlr-actions-btn-icon', function(e) {
        e.stopPropagation();
        var row = $('#actions-row-' + $(this).data('id'));
        $('.mlr-actions-row').not(row).hide();
        row.toggle();
    });

    function mlrOpenRequestDetailsModal(rid, action) {
        action = action || 'mlr_get_request_details';
        if (!rid) return;
        var $overlay = $('#mlr-overlay');
        var $modal = $('#mlr-details-content');
        var $inner = $('#mlr-details-inner');
        if (!$overlay.length || !$modal.length || !$inner.length) {
            return;
        }
        $('body').addClass('mlr-modal-open');
        $overlay.fadeIn(200);
        if (typeof mlrPositionAdminModal === 'function') mlrPositionAdminModal($modal, $('#mlr-overlay'));
        $modal.css('display', 'flex').hide().fadeIn(200);
        $inner.html('<div style="text-align:center;padding:40px;"><span class="spinner is-active"></span></div>');
        $.post(mlrAjax.ajaxurl, { action: action, nonce: mlrAjax.nonce, request_id: rid }, function(res) {
            $inner.html(res.success ? res.data.html : 'Error');
            if (res && res.success && typeof mlrAdminInitDocsCategoryTabs === 'function') mlrAdminInitDocsCategoryTabs($inner);
            if (typeof mlrResetAdminAttachments === 'function') mlrResetAdminAttachments();
        });
    }
    window.mlrOpenDetailsModal = function(id) { mlrOpenRequestDetailsModal(id, 'mlr_get_request_details'); };

    $(document).on('click', '.mlr-view-details-granted', function(e) {
        e.preventDefault();
        e.stopPropagation();
        mlrOpenRequestDetailsModal($(this).data('id'), 'mlr_get_request_details');
    });

    $(document).on('click', 'tr.mlr-request-row, tr.mlr-cooperation-request-row', function(e) {
        if ($(e.target).closest('a, button, input, select, .dashicons, .mlr-actions-container').length) return;
        if ($(this).closest('#mlr-settlement-form').length) return;
        var action = $(this).hasClass('mlr-cooperation-request-row') ? 'mlr_get_cooperation_request_details' : 'mlr_get_request_details';
        var rid = $(this).data('id');
        mlrOpenRequestDetailsModal(rid, action);
    });

    // Close modal on overlay or close button click. This is a safe, targeted handler.
    $(document).on('click', '#mlr-overlay, #mlr-close-details', function() {
        $('#mlr-overlay, #mlr-details-content').fadeOut(200, function(){
            $('body').removeClass('mlr-modal-open');
            mlrResetAdminModalPosition($('#mlr-details-content'), $('#mlr-overlay'));
        });
    });

    // Auto-open modal / highlight row when coming from notification link (?s=ID یا ?request_id=ID)
    (function autoOpenFromQuery() {
        var search = window.location.search || '';
        if (!search) return;
        var params;
        try {
            params = new URLSearchParams(search);
        } catch (e) {
            return;
        }

        var id = params.get('mlr_request_id') || params.get('request_id') || params.get('cooperation_id') || '';
        if (!id) {
            var sVal = params.get('s') || '';
            if (/^\d+$/.test(sVal)) {
                id = sVal;
            }
        }
        if (!id || !/^\d+$/.test(id)) return;
        id = parseInt(id, 10);
        if (!id) return;

        // Try to find corresponding row in current table
        var $row = $('tr.mlr-request-row[data-id="' + id + '"], tr.mlr-cooperation-request-row[data-id="' + id + '"]').first();
        if (!$row.length) return;

        // Highlight and scroll to row
        $row.addClass('mlr-row-highlight');
        try {
            var top = $row.offset().top - 140;
            if (top < 0) top = 0;
            $('html, body').animate({ scrollTop: top }, 400);
        } catch (e) {}

        // Determine correct action for modal and open it shortly after load
        var action = $row.hasClass('mlr-cooperation-request-row') ? 'mlr_get_cooperation_request_details' : 'mlr_get_request_details';
        setTimeout(function() {
            mlrOpenRequestDetailsModal(id, action);
        }, 250);
    })();

    $(document).on('click', '#mlr-add-comment-btn', function(e) {
        e.preventDefault();
        var btn = $(this);
        var rid = btn.data('id');
        var spinner = btn.closest('.mlr-conversation-actions').find('.spinner');
        var txt = $('#mlr_new_comment_text').val();
        var files = $('#mlr-message-attachment-input')[0].files;

        if (!txt.trim() && files.length === 0) {
            return alert('متن یادداشت یا فایل پیوست الزامی است.');
        }

        var formData = new FormData();
        formData.append('action', 'mlr_add_comment');
        formData.append('nonce', mlrAjax.nonce);
        formData.append('request_id', rid);
        formData.append('comment', txt);

        for (var i = 0; i < files.length; i++) {
            formData.append('attachments[]', files[i]);
        }

        btn.prop('disabled', true);
        spinner.addClass('is-active');

        $.ajax({
            url: mlrAjax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
        }).done(function(res) {
            if (res.success) {
                $('#mlr_new_comment_text').val('');
                mlrResetAdminAttachments(); // This function resets the file input
                if (res.data && typeof res.data.conversation_html !== 'undefined' && $('#mlr-conversation-timeline-container').length) {
                    $('#mlr-conversation-timeline-container').html(res.data.conversation_html);
                }
            } else {
                alert((res && res.data && res.data.message) ? res.data.message : 'خطا در ثبت یادداشت.');
            }
        }).fail(function() { alert('خطا در ارتباط با سرور.'); }).always(function() { btn.prop('disabled', false); spinner.removeClass('is-active'); });
    });
    // Guarantor forms are handled centrally by mlr-guarantor-form.js.
    $(document).on('mlr:guarantor:saved', function(e, data) {
        if (!data || !data.requestId || !data.isAdmin) return;
        setTimeout(function() {
            $.post(mlrAjax.ajaxurl, {
                action: 'mlr_get_request_details',
                nonce: mlrAjax.nonce,
                request_id: data.requestId
            }, function(r) {
                if (r && r.success && r.data && r.data.html) {
                    $('#mlr-details-inner').html(r.data.html);
                    if (typeof mlrAdminInitDocsCategoryTabs === 'function') {
                        mlrAdminInitDocsCategoryTabs($('#mlr-details-inner'));
                    }
                }
            });
        }, 450);
    });

    // پیوست‌های مرحله در انتظار مدارک (ذخیره و ریلود مودال)
    $(document).on('submit', '#mlr-pending-docs-attachments-form', function(e) {
        e.preventDefault();
        var form = $(this);
        var msg = form.find('.mlr-pending-docs-msg');
        var btn = form.find('button[type="submit"]');
        var rid = (form.attr('data-request-id') || form.find('input[name="request_id"]').val() || '').toString().trim();
        if (!rid) {
            msg.css('color', 'red').text('شناسه پرونده یافت نشد.');
            return;
        }
        var formData = new FormData(form[0]);
        formData.set('action', 'mlr_save_pending_docs_attachments');
        formData.set('request_id', rid);
        formData.set('nonce', mlrAjax.nonce);
        var ajaxUrl = mlrAjax.ajaxurl + (mlrAjax.ajaxurl.indexOf('?') >= 0 ? '&' : '?') + 'request_id=' + encodeURIComponent(rid);
        btn.prop('disabled', true).text('در حال ذخیره...');
        msg.text('').css('color', '');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function(res) {
            btn.prop('disabled', false).text('ذخیره پیوست‌ها');
            var text = (res && res.data && res.data.message) ? res.data.message : (res && res.message) ? res.message : '';
            if (res && res.success) {
                msg.css('color', 'green').text(text || 'ذخیره شد.');
                if (rid) {
                    setTimeout(function() {
                        $.post(mlrAjax.ajaxurl, { action: 'mlr_get_request_details', nonce: mlrAjax.nonce, request_id: rid }, function(r) {
                            if (r.success && r.data && r.data.html) {
                                $('#mlr-details-inner').html(r.data.html);
                                if (typeof mlrAdminInitDocsCategoryTabs === 'function') mlrAdminInitDocsCategoryTabs($('#mlr-details-inner'));
                            }
                        });
                    }, 500);
                }
            } else {
                msg.css('color', 'red').text(text || 'خطا در ذخیره.');
            }
        }).fail(function(xhr, status, err) {
            btn.prop('disabled', false).text('ذخیره پیوست‌ها');
            var t = 'خطای ارتباط با سرور.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) t = xhr.responseJSON.data.message;
            else if (xhr && xhr.responseText) {
                try {
                    var j = JSON.parse(xhr.responseText);
                    if (j.data && j.data.message) t = j.data.message;
                    else if (j.message) t = j.message;
                } catch (e) {
                    if (xhr.responseText && xhr.responseText.length < 500 && xhr.responseText.indexOf('</') === -1) t = xhr.responseText;
                }
            }
            msg.css('color', 'red').text(t);
        });
    });

    // تاگل حذف پیوست (مرحله در انتظار مدارک): روشن = این پیوست حذف می‌شود
    $(document).on('click', '.mlr-pending-doc-remove-toggle', function() {
        var btn = $(this);
        var row = btn.closest('.mlr-pending-docs-row');
        var hidden = row.find('.mlr-pending-doc-remove-input');
        var isOn = btn.attr('aria-checked') === 'true';
        isOn = !isOn;
        btn.attr('aria-checked', isOn ? 'true' : 'false');
        btn.find('.mlr-toggle-label').text(isOn ? 'حذف می‌شود' : 'خاموش');
        hidden.val(isOn ? '1' : '0');
    });

    $(document).on('submit', '#mlr-transfer-receipts-form', function(e) {
        e.preventDefault();
        var form = $(this);
        var msg = form.find('.mlr-transfer-receipts-msg');
        var btn = form.find('button[type="submit"]');
        var rid = (form.attr('data-request-id') || form.find('input[name="request_id"]').val() || '').toString().trim();
        if (!rid) {
            msg.css('color', 'red').text('شناسه پرونده یافت نشد.');
            return;
        }

        var fileInput = form.find('input[name="transfer_receipt_files[]"]')[0];
        var financierStatusSelect = form.find('.mlr-financier-payment-status-select');
        var hasFiles = !!(fileInput && fileInput.files && fileInput.files.length);
        var currentStatus = (financierStatusSelect.data('current') || 'بررسی نشده').toString();
        var selectedFinancierStatus = financierStatusSelect.length ? (financierStatusSelect.val() || 'بررسی نشده').toString() : 'بررسی نشده';
        if (!hasFiles && selectedFinancierStatus === currentStatus) {
            msg.css('color', 'red').text('برای ذخیره، یک رسید جدید بارگذاری کنید یا وضعیت پرداخت تأمین‌کننده مالی را تغییر دهید.');
            return;
        }

        var formData = new FormData(form[0]);
        formData.set('action', 'mlr_save_transfer_receipts');
        formData.set('request_id', rid);
        formData.set('nonce', mlrAjax.nonce);
        var ajaxUrl = mlrAjax.ajaxurl + (mlrAjax.ajaxurl.indexOf('?') >= 0 ? '&' : '?') + 'request_id=' + encodeURIComponent(rid);
        btn.prop('disabled', true).text('در حال بارگذاری...');
        msg.text('').css('color', '');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function(res) {
            btn.prop('disabled', false).text('ذخیره رسیدهای واریزی');
            var text = (res && res.data && res.data.message) ? res.data.message : (res && res.message) ? res.message : '';
            if (res && res.success) {
                if (financierStatusSelect.length && res.data && res.data.financier_payment_status) {
                    financierStatusSelect.data('current', res.data.financier_payment_status);
                }
                msg.css('color', 'green').text(text || 'رسیدهای واریزی ذخیره شد.');
                setTimeout(function() {
                    $.post(mlrAjax.ajaxurl, { action: 'mlr_get_request_details', nonce: mlrAjax.nonce, request_id: rid }, function(r) {
                        if (r.success && r.data && r.data.html) {
                            $('#mlr-details-inner').html(r.data.html);
                            if (typeof mlrAdminInitDocsCategoryTabs === 'function') mlrAdminInitDocsCategoryTabs($('#mlr-details-inner'));
                        }
                    });
                }, 500);
            } else {
                msg.css('color', 'red').text(text || 'خطا در ذخیره رسیدهای واریزی.');
            }
        }).fail(function(xhr) {
            btn.prop('disabled', false).text('ذخیره رسیدهای واریزی');
            var t = 'خطای ارتباط با سرور.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) t = xhr.responseJSON.data.message;
            else if (xhr && xhr.responseText) {
                try {
                    var j = JSON.parse(xhr.responseText);
                    if (j.data && j.data.message) t = j.data.message;
                    else if (j.message) t = j.message;
                } catch (e) {
                    if (xhr.responseText && xhr.responseText.length < 500 && xhr.responseText.indexOf('</') === -1) t = xhr.responseText;
                }
            }
            msg.css('color', 'red').text(t);
        });
    });

    $(document).on('submit', '#mlr-document-receipt-status-form', function(e) {
        e.preventDefault();

        var form = $(this);
        var requestId = parseInt(form.data('request-id'), 10) || 0;
        var select = form.find('.mlr-document-receipt-status-select');
        var dateInput = form.find('.mlr-document-received-date-input');
        var msg = form.find('.mlr-document-receipt-status-msg');
        var button = form.find('button[type="submit"]');
        var selectedStatus = select.val() || 'بررسی نشده';
        var documentReceivedDate = dateInput.length ? (dateInput.val() || '') : '';

        if (!requestId) {
            msg.css('color', 'red').text('شناسه پرونده نامعتبر است.');
            return;
        }

        button.prop('disabled', true);
        select.prop('disabled', true);
        dateInput.prop('disabled', true);
        msg.css('color', '').text('در حال ذخیره...');

        $.post(mlrAjax.ajaxurl, {
            action: 'mlr_update_document_receipt_status',
            nonce: mlrAjax.nonce,
            request_id: requestId,
            document_receipt_status: selectedStatus,
            document_received_date: documentReceivedDate
        }).done(function(res) {
            if (res && res.success) {
                var displayValue = (res.data && res.data.document_receipt_status_display) ? res.data.document_receipt_status_display : selectedStatus;
                $('#mlr-details-inner').find('.mlr-document-receipt-status-value').text(displayValue);
                $('tr.mlr-request-row[data-id="' + requestId + '"]').find('.column-document-receipt-status').text(displayValue);
                if (dateInput.length && res.data && typeof res.data.document_received_date_display !== 'undefined') {
                    $('#mlr-details-inner').find('.mlr-document-received-date-value').text(res.data.document_received_date_display);
                }
                msg.css('color', 'green').text((res.data && res.data.message) ? res.data.message : 'تنظیمات پرونده ذخیره شد.');
            } else {
                msg.css('color', 'red').text((res && res.data && res.data.message) ? res.data.message : 'خطا در ذخیره وضعیت دریافت مدارک.');
            }
        }).fail(function() {
            msg.css('color', 'red').text('خطا در ارتباط با سرور.');
        }).always(function() {
            button.prop('disabled', false);
            select.prop('disabled', false);
            dateInput.prop('disabled', false);
        });
    });

    function mlrToggleMarketplaceAcceptorPicker(form) {
        var action = form.find('.mlr-marketplace-admin-action').val();
        form.find('.mlr-marketplace-acceptor-picker').toggle(action === 'assign_acceptor');
    }

    $(document).on('change', '.mlr-marketplace-admin-action', function() {
        mlrToggleMarketplaceAcceptorPicker($(this).closest('#mlr-marketplace-admin-form'));
    });

    $(document).on('focus', '.mlr-marketplace-admin-form .mlr-marketplace-acceptor-search', function() {
        mlrToggleMarketplaceAcceptorPicker($(this).closest('#mlr-marketplace-admin-form'));
    });

    var mlrMarketplaceAcceptorSearchTimer = null;
    $(document).on('input', '.mlr-marketplace-admin-form .mlr-marketplace-acceptor-search', function() {
        var input = $(this);
        var form = input.closest('#mlr-marketplace-admin-form');
        var picker = input.closest('.mlr-marketplace-acceptor-picker');
        var results = picker.find('.mlr-marketplace-acceptor-results');
        var term = $.trim(input.val() || '');

        picker.find('.mlr-marketplace-selected-acceptor-id').val('');
        if (mlrMarketplaceAcceptorSearchTimer) {
            clearTimeout(mlrMarketplaceAcceptorSearchTimer);
        }

        if (term.length < 2) {
            results.empty();
            return;
        }

        results.html('<div class="mlr-marketplace-acceptor-result">در حال جستجو...</div>');
        mlrMarketplaceAcceptorSearchTimer = setTimeout(function() {
            $.post(mlrAjax.ajaxurl, {
                action: 'mlr_admin_search_acceptors',
                nonce: mlrAjax.nonce,
                term: term,
                request_id: form.data('request-id') || picker.data('request-id') || 0,
                plan_id: picker.data('plan-id') || 0
            }).done(function(res) {
                results.empty();
                if (!res || !res.success || !res.data || !res.data.items || !res.data.items.length) {
                    results.html('<div class="mlr-marketplace-acceptor-result">پذیرنده‌ای پیدا نشد.</div>');
                    return;
                }

                $.each(res.data.items, function(_, item) {
                    var btn = $('<button type="button" class="mlr-marketplace-acceptor-result"></button>');
                    btn.attr('data-id', item.id || '');
                    btn.attr('data-title', item.title || '');
                    btn.append($('<strong></strong>').text((item.title || 'پذیرنده') + ' (#' + item.id + ')'));
                    if (item.subtitle) {
                        btn.append($('<small></small>').text(item.subtitle));
                    }
                    results.append(btn);
                });
            }).fail(function() {
                results.html('<div class="mlr-marketplace-acceptor-result">خطا در جستجوی پذیرنده.</div>');
            });
        }, 300);
    });

    $(document).on('click', '.mlr-marketplace-admin-form .mlr-marketplace-acceptor-result[data-id]', function() {
        var btn = $(this);
        var picker = btn.closest('.mlr-marketplace-acceptor-picker');
        var selectedId = btn.data('id') || '';
        var selectedTitle = btn.data('title') || 'پذیرنده';

        picker.find('.mlr-marketplace-selected-acceptor-id').val(selectedId);
        picker.find('.mlr-marketplace-acceptor-search').val(selectedTitle);
        picker.find('.mlr-marketplace-selected-acceptor').text('پذیرنده انتخاب‌شده: ' + selectedTitle + ' (#' + selectedId + ')');
        picker.find('.mlr-marketplace-acceptor-results').empty();
    });

    $(document).on('submit', '#mlr-marketplace-admin-form', function(e) {
        e.preventDefault();

        var form = $(this);
        var requestId = parseInt(form.data('request-id'), 10) || 0;
        var msg = form.find('.mlr-marketplace-admin-msg');
        var button = form.find('button[type="submit"]');
        var originalText = button.text();
        var adminAction = form.find('.mlr-marketplace-admin-action').val();

        if (!requestId) {
            msg.css('color', 'red').text('شناسه پرونده نامعتبر است.');
            return;
        }

        if (adminAction === 'assign_acceptor' && !parseInt(form.find('.mlr-marketplace-selected-acceptor-id').val(), 10)) {
            msg.css('color', 'red').text('برای اتصال پرونده، ابتدا یک پذیرنده را از نتایج جستجو انتخاب کنید.');
            return;
        }

        button.prop('disabled', true).text('در حال ذخیره...');
        msg.css('color', '').text('');

        $.post(mlrAjax.ajaxurl, form.serialize() + '&' + $.param({
            action: 'mlr_update_acceptor_marketplace_request',
            nonce: mlrAjax.nonce,
            request_id: requestId
        })).done(function(res) {
            if (res && res.success) {
                msg.css('color', 'green').text((res.data && res.data.message) ? res.data.message : 'تنظیمات اتصال پذیرنده ذخیره شد.');
                setTimeout(function() {
                    $.post(mlrAjax.ajaxurl, { action: 'mlr_get_request_details', nonce: mlrAjax.nonce, request_id: requestId }, function(r) {
                        if (r.success && r.data && r.data.html) {
                            $('#mlr-details-inner').html(r.data.html);
                            if (typeof mlrAdminInitDocsCategoryTabs === 'function') mlrAdminInitDocsCategoryTabs($('#mlr-details-inner'));
                        }
                    });
                }, 400);
            } else {
                msg.css('color', 'red').text((res && res.data && res.data.message) ? res.data.message : 'خطا در ذخیره اتصال پذیرنده.');
            }
        }).fail(function() {
            msg.css('color', 'red').text('خطا در ارتباط با سرور.');
        }).always(function() {
            button.prop('disabled', false).text(originalText);
        });
    });

    $('.mlr-status-select').each(function() { $(this).data('original-value', $(this).val()); });


// ============================
// Messages (Admin)
// ============================
function mlrAdminRefreshMessages(requestId) {
    var box = jQuery('#mlr-messages-history-container');
    var timeline = jQuery('#mlr-conversation-timeline-container');
    
    if (box.length === 0 && timeline.length === 0) return;

    jQuery.post(mlrAjax.ajaxurl, {
        action: 'mlr_get_request_messages',
        nonce: mlrAjax.nonce,
        request_id: requestId
    }).done(function(res){
        if (res && res.success && res.data) {
            if (typeof res.data.messages_html !== 'undefined' && box.length) {
                box.html(res.data.messages_html);
            }
            if (res.data && typeof res.data.conversation_html !== 'undefined' && timeline.length) {
                timeline.html(res.data.conversation_html);
            }
        }
    });
}

jQuery(document).on('click', '.mlr-refresh-messages-btn', function(){
    var requestId = jQuery(this).data('request-id');
    mlrAdminRefreshMessages(requestId);
});

// --- START: Attachment Management (Admin) ---
var mlrAdminAttachments = new DataTransfer();

function mlrResetAdminAttachments() {
    mlrAdminAttachments = new DataTransfer();
    $('#mlr-message-attachment-input').val('');
    $('#mlr-attachment-previews').html('');
}

function mlrUpdateAdminAttachmentPreview() {
    var previewContainer = $('#mlr-attachment-previews');
    previewContainer.html('');
    var files = mlrAdminAttachments.files;
    if (files.length > 0) {
        var fileList = $('<ul></ul>');
        for (var i = 0; i < files.length; i++) {
            fileList.append('<li><span class="dashicons dashicons-media-default"></span> ' + files[i].name + ' <span class="dashicons dashicons-dismiss mlr-remove-file" data-index="' + i + '" title="حذف"></span></li>');
        }
        previewContainer.append(fileList);
    }
}

// --- START: Attachment Preview Handler ---
$(document).on('change', '#mlr-message-attachment-input', function() {
    for (var i = 0; i < this.files.length; i++) {
        mlrAdminAttachments.items.add(this.files[i]);
    }
    this.files = mlrAdminAttachments.files; // Sync input
    mlrUpdateAdminAttachmentPreview();
});

jQuery(document).on('click', '.mlr-send-message-btn', function(){
    var btn = jQuery(this);
    var requestId = btn.data('request-id');
    var spinner = btn.closest('.mlr-conversation-actions').find('.spinner');
    
    var textarea = jQuery('#mlr_new_comment_text');
    var message = textarea.val() || '';

    var fileInput = $('#mlr-message-attachment-input')[0]; // This is now synced with mlrAdminAttachments
    var files = fileInput.files;

    if (!message.trim() && files.length === 0) {
        alert('متن پیام یا فایل پیوست الزامی است.');
        return;
    }

    var formData = new FormData();
    formData.append('action', 'mlr_add_request_message');
    formData.append('nonce', mlrAjax.nonce);
    formData.append('request_id', requestId);
    formData.append('message', message);

    for (var i = 0; i < files.length; i++) {
        formData.append('attachments[]', files[i]);
    }

    btn.prop('disabled', true);
    spinner.addClass('is-active');

    $.ajax({
        url: mlrAjax.ajaxurl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
    }).done(function(res) {
        if (res && res.success) {
            textarea.val('');
            mlrResetAdminAttachments();
            mlrAdminRefreshMessages(requestId);
        } else {
            alert((res && res.data && res.data.message) ? res.data.message : 'خطا در ارسال پیام.');
        }
    }).fail(function() {
        alert('خطا در ارتباط با سرور.');
    }).always(function() {
        btn.prop('disabled', false);
        spinner.removeClass('is-active');
    });
});

// Remove attachment handler
$(document).on('click', '#mlr-attachment-previews .mlr-remove-file', function() {
    var index = $(this).data('index');
    var dt = new DataTransfer();
    var files = mlrAdminAttachments.files;
    for (var i = 0; i < files.length; i++) {
        if (i !== index) dt.items.add(files[i]);
    }
    mlrAdminAttachments = dt;
    $('#mlr-message-attachment-input')[0].files = mlrAdminAttachments.files;
    mlrUpdateAdminAttachmentPreview();
});

// View uploaded files/documents in a new tab.
// We only intercept clicks when an href exists.
// (Some non-file buttons previously reused the same class, which broke their click handlers.)
jQuery(document).on('click', '.mlr-view-file-btn', function(e){
    var href = jQuery(this).attr('href');
    if(!href) return; // Let other click handlers (delegated) run.
    e.preventDefault();
    window.open(href, '_blank', 'noopener');
});

// Copy contract link button inside the cooperation request details modal.
jQuery(document).on('click', '.mlr-copy-link-btn', function(e){
    e.preventDefault();
    var $btn = jQuery(this);
    var link = $btn.data('link') || '';
    if (!link) return;

    var done = function(){
        var original = $btn.text();
        $btn.text('کپی شد!').prop('disabled', true);
        setTimeout(function(){ $btn.text(original).prop('disabled', false); }, 1500);
    };

    // Modern clipboard API (preferred)
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(link).then(done).catch(function(){
            // Fallback
            var $tmp = jQuery('<input>').val(link).appendTo('body').select();
            try { document.execCommand('copy'); } catch (err) {}
            $tmp.remove();
            done();
        });
    } else {
        // Fallback for non-secure contexts
        var $tmp = jQuery('<input>').val(link).appendTo('body').select();
        try { document.execCommand('copy'); } catch (err) {}
        $tmp.remove();
        done();
    }
});

// Export single request dossier to Excel from modal
$(document).on('click', '.mlr-export-single-excel-btn', function(e) {
    e.preventDefault();
    var rid = $(this).data('id');
    window.location.href = mlrAjax.ajaxurl + '?action=mlr_export_dossier_excel&nonce=' + mlrAjax.nonce + '&request_id=' + rid;
});
});
