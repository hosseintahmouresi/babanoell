jQuery(document).ready(function($) {

    // =========================================================
    // SELF-HEALING MODAL SETUP
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

    $(document).off('click.mlrModalClose').on('click.mlrModalClose', '#mlr-global-modal-overlay, .mlr-modal-close', function() {
        $('#mlr-global-modal-overlay, #mlr-global-modal').fadeOut(200, function(){ 
            $('body').removeClass('mlr-modal-open'); 
            // Assuming mlrResetModalPosition is defined in scope or we define it below (it is defined below in this file)
             if (typeof mlrResetModalPosition === 'function') { mlrResetModalPosition($('#mlr-global-modal'), $('#mlr-global-modal-overlay')); }
        });
    });

    function mlrIsBodyTransformed() {
        try { var t = window.getComputedStyle(document.body).transform; return (t && t !== 'none'); } catch (e) { return false; }
    }

    function mlrPositionOpenModal($modal, $overlay) {
        var needsAbsolute = mlrIsBodyTransformed();
        var update = function() {
            var scrollTop = $(window).scrollTop();
            var winH = $(window).height();
            var docH = $(document).height();
            if (needsAbsolute) {
                $overlay.css({ position: 'absolute', top: scrollTop, left: 0, width: '100%', height: docH });
                $modal.css({ position: 'absolute', top: (scrollTop + (winH / 2)), left: '50%' });
            } else {
                $overlay.css({ position: 'fixed', top: 0, left: 0, width: '100%', height: '100%' });
                $modal.css({ position: 'fixed', top: '50%', left: '50%' });
            }
        };
        // $modal.css('display', 'flex'); // Handled in showDetailsModal
        $(window).off('scroll.mlrModalPos resize.mlrModalPos').on('scroll.mlrModalPos resize.mlrModalPos', function() {
            if ($overlay.is(':visible') && $modal.is(':visible')) { update(); }
        });
        update();
    }

    function mlrResetModalPosition($modal, $overlay) {
        $(window).off('scroll.mlrModalPos resize.mlrModalPos');
        $overlay.css({ position: 'fixed', top: 0, left: 0, width: '100%', height: '100%' });
        $modal.css({ position: 'fixed', top: '50%', left: '50%' });
    }

    // --- Initialize Jalali Datepicker ---
    try {
        if ($.fn.persianDatepicker && $('.jalali-datepicker').length > 0) {
            $('.jalali-datepicker').persianDatepicker({ format: 'YYYY/MM/DD', autoClose: true });
        }
    } catch (e) { console.log("Notice: Persian Datepicker init skipped."); }

    function mlrPrintContractReviewTarget(targetId) {
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
        mlrPrintContractReviewTarget($(this).attr('data-mlr-print-target'));
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
    // UI HELPERS (Tabs, Accordions, Files)
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

    function mlrInitAccordions(context) {
        var $ctx = context ? $(context) : $(document);
        $ctx.find('.mlr-acc-content, .mlr-glass-acc-content').each(function(){
            if (!$(this).hasClass('is-open')) $(this).hide();
        });
    }

    // Event Delegation
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

    $(document).on('click', '.mlr-acc-btn, .mlr-glass-acc-btn', function(e){
        e.preventDefault();
        var $btn = $(this);
        var targetId = $btn.attr('data-target');
        if (!targetId) return;
        var $content = $('#' + targetId);
        if ($content.length === 0) return;
        if ($content.is(':visible')) {
            $content.slideUp(150);
            $btn.removeClass('is-open');
        } else {
            $content.slideDown(150);
            $btn.addClass('is-open');
        }
    });

    $(document).on('click', '.mlr-view-file-btn', function(e) {
        var href = $(this).attr('href');
        if (!href) return;
        e.preventDefault();
        window.open(href, '_blank', 'noopener');
    });

    // --- START: Messages & Attachments Logic (Shared for Expert Dashboards) ---
    function mlrRefreshMessages(requestId, nonce) {
        var box = $('#mlr-messages-history-container');
        if (box.length === 0) return;
        
        var ajaxUrl = '';
        if (typeof mlr_expert_ajax !== 'undefined') ajaxUrl = mlr_expert_ajax.ajax_url;
        else if (typeof mlrAjax !== 'undefined') ajaxUrl = mlrAjax.ajaxurl;
        
        $.post(ajaxUrl, {
            action: 'mlr_get_request_messages',
            nonce: nonce,
            request_id: requestId
        }).done(function(res) {
            if (res && res.success && res.data && typeof res.data.messages_html !== 'undefined') {
                box.html(res.data.messages_html);
            }
        });
    }

    var mlrExpertAttachments = new DataTransfer();

    function mlrResetExpertAttachments() {
        mlrExpertAttachments = new DataTransfer();
        $('#mlr-message-attachment-input-front').val('');
        $('#mlr-attachment-previews-front').html('');
    }

    function mlrUpdateExpertAttachmentPreview() {
        var previewContainer = $('#mlr-attachment-previews-front');
        previewContainer.html('');
        var files = mlrExpertAttachments.files;
        if (files.length > 0) {
            var fileList = $('<ul></ul>');
            for (var i = 0; i < files.length; i++) {
                fileList.append('<li><span class="dashicons dashicons-media-default"></span> ' + files[i].name + ' <span class="dashicons dashicons-dismiss mlr-remove-file-front" data-index="' + i + '" title="حذف"></span></li>');
            }
            previewContainer.append(fileList);
        }
    }

    $(document).on('change', '#mlr-message-attachment-input-front', function() {
        // Check if we are in the expert context
        if (!$(this).closest('.mlr-context-expert').length) return;

        for (var i = 0; i < this.files.length; i++) {
            mlrExpertAttachments.items.add(this.files[i]);
        }
        this.files = mlrExpertAttachments.files;
        mlrUpdateExpertAttachmentPreview();
    });

    $(document).on('click', '.mlr-remove-file-front', function() {
        if (!$(this).closest('.mlr-context-expert').length) return;

        var index = $(this).data('index');
        var dt = new DataTransfer();
        var files = mlrExpertAttachments.files;
        for (var i = 0; i < files.length; i++) {
            if (i !== index) dt.items.add(files[i]);
        }
        mlrExpertAttachments = dt;
        $('#mlr-message-attachment-input-front')[0].files = mlrExpertAttachments.files;
        mlrUpdateExpertAttachmentPreview();
    });

    $(document).on('click', '.mlr-refresh-messages-btn', function() {
        if (!$(this).closest('.mlr-context-expert').length) return;

        var requestId = $(this).data('request-id');
        var nonce = $(this).data('nonce'); 
        if(!nonce && typeof mlr_expert_ajax !== 'undefined') nonce = mlr_expert_ajax.nonce;
        mlrRefreshMessages(requestId, nonce);
    });

    $(document).on('click', '.mlr-send-message-btn', function(e) {
        if (!$(this).closest('.mlr-context-expert').length) return;
        
        e.preventDefault();
        var btn = $(this);
        var requestId = btn.data('request-id');
        var nonce = btn.data('nonce');
        if(!nonce && typeof mlr_expert_ajax !== 'undefined') nonce = mlr_expert_ajax.nonce;
        
        var textarea = btn.closest('.mlr-add-message-form').find('.mlr-new-message-text');
        var message = textarea.val() || '';
        
        var fileInput = btn.closest('.mlr-add-message-form').find('#mlr-message-attachment-input-front')[0];
        var files = fileInput ? fileInput.files : [];

        if (!message.trim() && files.length === 0) {
            alert('متن پیام یا فایل پیوست الزامی است.');
            return;
        }

        btn.prop('disabled', true).text('در حال ارسال...');

        var formData = new FormData();
        formData.append('action', 'mlr_add_request_message');
        formData.append('nonce', nonce);
        formData.append('request_id', requestId);
        formData.append('message', message);
        
        for (var i = 0; i < files.length; i++) {
            formData.append('attachments[]', files[i]);
        }

        var ajaxUrl = '';
        if (typeof mlr_expert_ajax !== 'undefined') ajaxUrl = mlr_expert_ajax.ajax_url;
        else if (typeof mlrAjax !== 'undefined') ajaxUrl = mlrAjax.ajaxurl;

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
        }).done(function(res) {
            btn.prop('disabled', false).text('ارسال پیام');
            if (res && res.success) {
                textarea.val('');
                mlrResetExpertAttachments();
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
    // --- END: Messages & Attachments Logic ---

    // --- Generic Dashboard Logic ---
    $('.mlr-user-requests[id^="mlr-expert-dashboard-"]').each(function() {
        var $container = $(this);
        var config = $container.data('config') || {};
        var ajaxAction = config.ajax_action || 'mlr_get_bank_expert_requests';
        var exportAction = (typeof config.export_action !== 'undefined') ? config.export_action : 'mlr_export_bank_expert_requests';
        var detailsContext = config.details_context || 'expert_dashboard';
        var readOnly = config.read_only === true || config.read_only === 1 || config.read_only === '1';
        var exportStatus = config.export_status || 'تأیید کارشناس';
        var nonce = config.nonce || '';
        var ajaxUrl = config.ajax_url || '';
        var columnCount = parseInt(config.column_count || 9, 10);
        if (!columnCount || columnCount < 1) columnCount = 9;

        function loadRequests() {
            var search = $container.find('#mlr-search').val();
            var dateFrom = $container.find('#mlr-date-from').val();
            var dateTo = $container.find('#mlr-date-to').val();
            var tableBody = $container.find('#mlr-requests-table-body');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: ajaxAction,
                    nonce: nonce,
                    search: search,
                    date_from: dateFrom,
                    date_to: dateTo
                },
                beforeSend: function() {
                    tableBody.html('<tr><td colspan="' + columnCount + '" style="text-align:center; padding: 20px;">در حال بارگذاری...</td></tr>');
                },
                success: function(response) {
                    if (response.success) {
                        tableBody.html(response.data.html);
                    } else {
                        tableBody.html('<tr><td colspan="' + columnCount + '" style="text-align:center; padding: 20px; color: red;">' + (response.data.message || 'خطا در دریافت اطلاعات.') + '</td></tr>');
                    }
                },
                error: function() {
                    tableBody.html('<tr><td colspan="' + columnCount + '" style="text-align:center; padding: 20px; color: red;">خطا در ارتباط با سرور.</td></tr>');
                }
            });
        }

        function showDetailsModal(requestId) {
            const modalOverlay = $('#mlr-global-modal-overlay');
            const modal = $('#mlr-global-modal');
            const modalContent = modal.find('.mlr-modal-content');

            if (!requestId) return;

            $('body').addClass('mlr-modal-open');
            modalOverlay.fadeIn(200);
            modal.css('display', 'flex').hide().fadeIn(200); // Fixed: Hide first to allow fadeIn animation
            mlrPositionOpenModal(modal, modalOverlay);
            modalContent.html('<p style="text-align:center; padding: 40px;">در حال بارگذاری جزئیات...</p>');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mlr_get_frontend_request_details',
                    nonce: nonce,
                    request_id: requestId,
                    context: detailsContext
                },
                success: function(response) {
                    if (response.success) {
                        modalContent.html(response.data.html);
                        modalContent.addClass('mlr-context-expert'); // Add context class
                        if (readOnly) {
                            modalContent.addClass('mlr-context-bank-readonly');
                        } else {
                            // Write controls only exist on the workflow-specific dashboards.
                            modalContent.find('.mlr-bank-expert-action-form').addClass('mlr-origin-expert-dashboard').data('dashboard-id', $container.attr('id'));
                            modalContent.find('.mlr-bank-settlement-form').data('dashboard-id', $container.attr('id'));
                        }
                        
                        // Initialize UI components
                        mlrInitDocsCategoryTabs(modalContent);
                        mlrInitAccordions(modalContent);
                        
                        // Only dashboards that actually render the conversation section need
                        // message/attachment state. The settlement dashboard is intentionally
                        // minimal and must not fetch case conversations in the background.
                        if (modalContent.find('.mlr-messages-history').length) {
                            mlrResetExpertAttachments();
                            mlrRefreshMessages(requestId, nonce);
                        }
                        
                        if ($.fn.persianDatepicker) {
                             modalContent.find('.jalali-datepicker').persianDatepicker({ format: 'YYYY/MM/DD', autoClose: true });
                        }
                    } else {
                        modalContent.html('<p style="color:red; text-align:center; padding: 40px;">' + (response.data.message || 'خطا در دریافت جزئیات.') + '</p>');
                    }
                },
                error: function() { modalContent.html('<p class="mlr-error">خطا در ارتباط با سرور.</p>'); }
            });
        }

        // Initial Load
        loadRequests();

        // Bind Events
        $container.on('click', '#mlr-filter-btn', loadRequests);
        $container.on('keyup', '#mlr-search', function(e) { if (e.key === 'Enter') loadRequests(); });
        
        $container.on('click', '#mlr-requests-table-body tr', function(e) {
            if (!$(e.target).closest('button, a, input, select, textarea').length) {
                showDetailsModal($(this).data('id'));
            }
        });

        $container.on('click', '#mlr-export-excel', function() {
            if (!exportAction) return;
            var search = $container.find('#mlr-search').val() || '';
            var dateFrom = $container.find('#mlr-date-from').val() || '';
            var dateTo = $container.find('#mlr-date-to').val() || '';
            var url = ajaxUrl + '?action=' + exportAction + '&nonce=' + encodeURIComponent(nonce) + '&status=' + encodeURIComponent(exportStatus) + '&search=' + encodeURIComponent(search) + '&date_from=' + encodeURIComponent(dateFrom) + '&date_to=' + encodeURIComponent(dateTo);
            window.location.href = url;
        });
    });

    // --- Shared Action Form Submission ---
    $(document).on('submit', '.mlr-bank-expert-action-form.mlr-origin-expert-dashboard', function(e) {
        e.preventDefault();
        var form = $(this);
        var dashboardId = form.data('dashboard-id');
        var $dashboard = $('#' + dashboardId);
        var config = $dashboard.length ? $dashboard.data('config') : { ajax_url: mlr_expert_ajax.ajax_url, nonce: mlr_expert_ajax.nonce }; // Fallback

        var button = form.find('button[type="submit"]');
        var messageDiv = form.find('.mlr-action-message');
        var formData = {
            action: 'mlr_submit_bank_expert_review',
            nonce: config.nonce,
            request_id: form.find('input[name="request_id"]').val(),
            new_status: form.find('select[name="new_status"]').val(),
            comment_text: form.find('textarea[name="comment_text"]').val()
        };

        $.ajax({
            url: config.ajax_url,
            type: 'POST',
            data: formData,
            beforeSend: function() {
                button.prop('disabled', true).text('در حال ثبت...');
                messageDiv.removeClass().addClass('mlr-action-message loading').text('لطفا صبر کنید...').slideDown();
            },
            success: function(response) {
                if (response.success) {
                    messageDiv.removeClass().addClass('mlr-action-message success').text(response.data.message);
                    setTimeout(function() {
                        $('#mlr-global-modal-overlay, #mlr-global-modal').fadeOut(200, function(){ $('body').removeClass('mlr-modal-open'); });
                        if ($dashboard.length) $dashboard.find('#mlr-filter-btn').click(); // Reload list
                    }, 2000);
                } else {
                    messageDiv.removeClass().addClass('mlr-action-message error').text(response.data.message || 'خطای ناشناخته.');
                    button.prop('disabled', false).text('ثبت بررسی');
                }
            },
            error: function() {
                messageDiv.removeClass().addClass('mlr-action-message error').text('خطا در ارتباط با سرور.');
                button.prop('disabled', false).text('ثبت بررسی');
            }
        });
    });

    // --- Bank-side settlement form (independent from acceptor settlement cycles) ---
    $(document).on('submit', '.mlr-bank-settlement-form', function(e) {
        e.preventDefault();

        var form = $(this);
        var button = form.find('button[type="submit"]');
        var messageDiv = form.find('.mlr-bank-settlement-message');
        var dashboardId = form.data('dashboard-id') || form.closest('.mlr-modal-content').find('.mlr-origin-expert-dashboard').data('dashboard-id') || '';
        var $dashboard = dashboardId ? $('#' + dashboardId) : $();
        var config = $dashboard.length ? ($dashboard.data('config') || {}) : {};
        var ajaxUrl = config.ajax_url || (typeof mlr_expert_ajax !== 'undefined' ? mlr_expert_ajax.ajax_url : '');
        var nonce = config.nonce || (typeof mlr_expert_ajax !== 'undefined' ? mlr_expert_ajax.nonce : '');

        if (!ajaxUrl || !nonce) {
            messageDiv.removeClass().addClass('mlr-bank-settlement-message error').text('تنظیمات ارتباط با سرور در دسترس نیست.').show();
            return;
        }

        var isRecorded = form.find('input[name="bank_settled"]').is(':checked');
        var settlementDate = $.trim(form.find('input[name="bank_settlement_date"]').val() || '');
        if (isRecorded && !settlementDate) {
            messageDiv.removeClass().addClass('mlr-bank-settlement-message error').text('تاریخ تسویه بانک با بابانوئل را وارد کنید.').show();
            return;
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'mlr_update_bank_settlement',
                nonce: nonce,
                request_id: form.find('input[name="request_id"]').val(),
                settled: isRecorded ? '1' : '0',
                settlement_date: settlementDate,
                note: form.find('textarea[name="bank_settlement_note"]').val() || ''
            },
            beforeSend: function() {
                button.prop('disabled', true).text('در حال ثبت...');
                messageDiv.removeClass().addClass('mlr-bank-settlement-message loading').text('لطفاً صبر کنید...').show();
            },
            success: function(response) {
                if (response && response.success) {
                    messageDiv.removeClass().addClass('mlr-bank-settlement-message success').text(response.data.message || 'اطلاعات تسویه بانک با بابانوئل ثبت شد.').show();
                    var statusText = response.data.status_display || '';
                    var dateText = response.data.settled_at_display || '—';
                    var $settlementCard = form.closest('.mlr-bank-settlement-card');
                    $settlementCard.find('.mlr-bank-settlement-current-status').text(statusText);
                    $settlementCard.find('.mlr-bank-settlement-current-date').text(dateText);
                    if (!isRecorded) {
                        form.find('input[name="bank_settlement_date"]').val('');
                    }
                    if ($dashboard.length) {
                        $dashboard.find('#mlr-filter-btn').trigger('click');
                    }
                } else {
                    messageDiv.removeClass().addClass('mlr-bank-settlement-message error').text((response && response.data && response.data.message) ? response.data.message : 'ثبت اطلاعات تسویه بانک با بابانوئل انجام نشد.').show();
                }
            },
            error: function() {
                messageDiv.removeClass().addClass('mlr-bank-settlement-message error').text('خطا در ارتباط با سرور.').show();
            },
            complete: function() {
                button.prop('disabled', false).text('ذخیره اطلاعات تسویه');
            }
        });
    });

});