jQuery(document).ready(function($) {

    // =========================================================
    // SELF-HEALING MODAL SETUP (Ensures modal exists)
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

    // Attach close handler (Moved outside to ensure it works even if modal existed)
    $(document).off('click.mlrModalClose').on('click.mlrModalClose', '#mlr-global-modal-overlay, .mlr-modal-close', function() {
        $('#mlr-global-modal-overlay, #mlr-global-modal').fadeOut(200, function(){ 
            $('body').removeClass('mlr-modal-open'); 
            mlrResetModalPosition($('#mlr-global-modal'), $('#mlr-global-modal-overlay'));
        });
    });

// =========================================================
// MODAL POSITION FIX (Themes with transformed <body>)
// =========================================================
function mlrIsBodyTransformed() {
    try {
        var t = window.getComputedStyle(document.body).transform;
        return (t && t !== 'none');
    } catch (e) { return false; }
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

    $modal.css('display', 'flex');

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

    // Scope everything to the bank dashboard container to avoid conflicts
    // when multiple shortcodes are present on the same page.
    var $container = $('.mlr-user-requests[id^="mlr-bank-dashboard-"]').first();
    if ($container.length === 0) {
        return;
    }

    // --- Initialize Jalali Datepicker Safely ---
    try {
        if ($.fn.persianDatepicker && $('.jalali-datepicker').length > 0) {
            $('.jalali-datepicker').persianDatepicker({
                format: 'YYYY/MM/DD',
                autoClose: true
            });
        } else {
            console.warn("Persian Datepicker library not found.");
        }
    } catch (e) {
        console.log("Notice: Persian Datepicker init skipped or failed (non-critical).");
    }

    // --- Main function to load requests via AJAX ---
    function loadBankRequests() {
        var search = $container.find('#mlr-search').val();
        var dateFrom = $container.find('#mlr-date-from').val();
        var dateTo = $container.find('#mlr-date-to').val();
        var tableBody = $container.find('#mlr-requests-table-body');

        $.ajax({
            url: mlr_bank_ajax_obj.ajax_url,
            type: 'POST',
            data: {
                action: 'mlr_get_bank_expert_requests',
                nonce: mlr_bank_ajax_obj.nonce,
                search: search,
                date_from: dateFrom,
                date_to: dateTo
            },
            beforeSend: function() {
                tableBody.html('<tr><td colspan="9" style="text-align:center; padding: 20px;">در حال بارگذاری...</td></tr>');
            },
            success: function(response) {
                if (response.success) {
                    tableBody.html(response.data.html);
                } else {
                    tableBody.html('<tr><td colspan="9" style="text-align:center; padding: 20px; color: red;">' + (response.data.message || 'خطا در دریافت اطلاعات.') + '</td></tr>');
                }
            },
            error: function() {
                tableBody.html('<tr><td colspan="9" style="text-align:center; padding: 20px; color: red;">خطا در ارتباط با سرور.</td></tr>');
            }
        });
    }

    // --- Function to show the details modal (re-uses the same AJAX endpoint as regular users) ---
    function showDetailsModal(requestId) {
        // Use the global modal created by mlr-user-requests.js
        const modalOverlay = $('#mlr-global-modal-overlay');
        const modal = $('#mlr-global-modal');
        const modalContent = modal.find('.mlr-modal-content');

        if (!requestId) return;

        $('body').addClass('mlr-modal-open');
        modalOverlay.fadeIn(200);
        modal.css('display', 'flex'); // Keep flex for layout
        modal.fadeIn(200);
        mlrPositionOpenModal(modal, modalOverlay);
        modalContent.html('<p style="text-align:center; padding: 40px;">در حال بارگذاری جزئیات...</p>');

        $.ajax({
            url: mlr_bank_ajax_obj.ajax_url, 
            type: 'POST',
            data: {
                action: 'mlr_get_frontend_request_details', // The backend will handle permissions
                nonce: mlr_bank_ajax_obj.nonce,
                request_id: requestId
            },
            success: function(response) {
                if (response.success) {
                    modalContent.html(response.data.html).attr('data-current-request-id', requestId);
                    // Mark form as originating from bank expert dashboard
                    modalContent.find('.mlr-bank-expert-action-form').addClass('mlr-origin-bank-expert');
                } else {
                    modalContent.html('<p style="color:red; text-align:center; padding: 40px;">' + (response.data.message || 'خطا در دریافت جزئیات.') + '</p>');
                }
            },
            error: function() {
                modalContent.html('<p class="mlr-error">خطا در ارتباط با سرور.</p>');
            }
        });
    }

    // --- Initial Load ---
    loadBankRequests();

    // --- Event Handlers ---
    $container.on('click', '#mlr-filter-btn', function() {
        loadBankRequests();
    });

    $container.on('keyup', '#mlr-search', function(e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            loadBankRequests();
        }
    });

    // Open modal on row click, ensuring it doesn't fire on interactive elements.
    // This now matches the behavior in mlr-user-requests.js for consistency.
    $container.on('click', '#mlr-requests-table-body tr', function(e) {
        if (!$(e.target).closest('button, a, input, select, textarea').length) {
            showDetailsModal($(this).data('id'));
        }
    });

    
// =========================================================
// MESSAGES (Conversation) HANDLERS - Bank Expert Dashboard
// =========================================================
function mlrRefreshMessages(requestId, nonce) {
    var box = $('#mlr-messages-history-container');
    if (box.length === 0) return;
    $.post(mlr_bank_ajax_obj.ajax_url, {
        action: 'mlr_get_request_messages',
        nonce: nonce || mlr_bank_ajax_obj.nonce,
        request_id: requestId
    }).done(function(res) {
        if (res && res.success && res.data && typeof res.data.messages_html !== 'undefined') {
            box.html(res.data.messages_html);
        }
    });
}

    $container.on('click', '.mlr-refresh-messages-btn', function() {
    var requestId = $(this).data('request-id');
    var nonce = $(this).data('nonce') || mlr_bank_ajax_obj.nonce;
    mlrRefreshMessages(requestId, nonce);
});

// --- START: Attachment Management (Bank Expert) ---
var mlrBankAttachments = new DataTransfer();

function mlrResetBankAttachments() {
    mlrBankAttachments = new DataTransfer();
    $('#mlr-message-attachment-input-front').val('');
    $('#mlr-attachment-previews-front').html('');
}

function mlrUpdateBankAttachmentPreview() {
    var previewContainer = $('#mlr-attachment-previews-front');
    previewContainer.html('');
    var files = mlrBankAttachments.files;
    if (files.length > 0) {
        var fileList = $('<ul></ul>');
        for (var i = 0; i < files.length; i++) {
            fileList.append('<li><span class="dashicons dashicons-media-default"></span> ' + files[i].name + ' <span class="dashicons dashicons-dismiss mlr-remove-file-front" data-index="' + i + '" title="حذف"></span></li>');
        }
        previewContainer.append(fileList);
    }
}

// Handle file selection
$(document).on('change', '#mlr-message-attachment-input-front', function() {
    if (!$(this).closest('.mlr-context-bank-expert').length) return;

    for (var i = 0; i < this.files.length; i++) {
        mlrBankAttachments.items.add(this.files[i]);
    }
    this.files = mlrBankAttachments.files;
    mlrUpdateBankAttachmentPreview();
});

// Handle file removal
$(document).on('click', '.mlr-remove-file-front', function() {
    if (!$(this).closest('.mlr-context-bank-expert').length) return;

    var index = $(this).data('index');
    var dt = new DataTransfer();
    var files = mlrBankAttachments.files;
    for (var i = 0; i < files.length; i++) {
        if (i !== index) dt.items.add(files[i]);
    }
    mlrBankAttachments = dt;
    $('#mlr-message-attachment-input-front')[0].files = mlrBankAttachments.files;
    mlrUpdateBankAttachmentPreview();
});

$(document).on('click', '.mlr-send-message-btn', function(e) {
    if (!$(this).closest('.mlr-context-bank-expert').length) return;

    e.preventDefault();
    var btn = $(this);
    var requestId = btn.data('request-id');
    var nonce = btn.data('nonce') || mlr_bank_ajax_obj.nonce;
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

    $.ajax({
        url: mlr_bank_ajax_obj.ajax_url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
    }).done(function(res) {
        btn.prop('disabled', false).text('ارسال پیام');
        if (res && res.success) {
            textarea.val('');
            mlrResetBankAttachments(); // Reset files
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

// Reset attachments when opening modal
var originalShowDetailsModal = showDetailsModal;
showDetailsModal = function(requestId) {
    mlrResetBankAttachments();
    originalShowDetailsModal(requestId);
};

// --- Bank Expert Action Form Submission ---
    $(document).on('submit', '.mlr-bank-expert-action-form.mlr-origin-bank-expert', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var button = form.find('button[type="submit"]');
        var messageDiv = form.find('.mlr-action-message');
        var formData = {
            action: 'mlr_submit_bank_expert_review',
            nonce: mlr_bank_ajax_obj.nonce,
            request_id: form.find('input[name="request_id"]').val(),
            new_status: form.find('select[name="new_status"]').val(),
            comment_text: form.find('textarea[name="comment_text"]').val()
        };

        $.ajax({
            url: mlr_bank_ajax_obj.ajax_url,
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
                        loadBankRequests();
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

// --- Export Excel (CSV) ---
$container.on('click', '#mlr-export-excel', function() {
    var search = $container.find('#mlr-search').val() || '';
    var dateFrom = $container.find('#mlr-date-from').val() || '';
    var dateTo = $container.find('#mlr-date-to').val() || '';

    var url = mlr_bank_ajax_obj.ajax_url
        + '?action=mlr_export_bank_expert_requests'
        + '&nonce=' + encodeURIComponent(mlr_bank_ajax_obj.nonce)
        + '&status=' + encodeURIComponent('تأیید کارشناس')
        + '&search=' + encodeURIComponent(search)
        + '&date_from=' + encodeURIComponent(dateFrom)
        + '&date_to=' + encodeURIComponent(dateTo);

    window.location.href = url;
});

});