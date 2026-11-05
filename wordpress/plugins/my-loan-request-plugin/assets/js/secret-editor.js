(function($) {
    'use strict';

    var $tbody = $('#mlr-secret-requests-tbody');
    var $modal = $('#mlr-secret-modal');
    var $overlay = $('#mlr-secret-modal-overlay');
    var $modalBody = $('#mlr-secret-modal-body');
    var $modalTitle = $('#mlr-secret-modal-title');
    var ajaxUrl = typeof mlr_secret_editor !== 'undefined' ? mlr_secret_editor.ajax_url : '';
    var nonce = typeof mlr_secret_editor !== 'undefined' ? mlr_secret_editor.nonce : '';

    function loadList() {
        var search = $('#mlr-secret-search').val();
        var status = $('#mlr-secret-status').val();
        $tbody.html('<tr><td colspan="7" class="mlr-secret-loading">در حال بارگذاری...</td></tr>');
        $.post(ajaxUrl, {
            action: 'mlr_secret_editor_list',
            nonce: nonce,
            search: search,
            status: status
        }).done(function(res) {
            if (res.success && res.data.html) {
                $tbody.html(res.data.html);
            } else {
                $tbody.html('<tr><td colspan="7" class="mlr-secret-empty">خطا در بارگذاری.</td></tr>');
            }
        }).fail(function() {
            $tbody.html('<tr><td colspan="7" class="mlr-secret-empty">خطای ارتباط با سرور.</td></tr>');
        });
    }

    function openModal(title) {
        $modalTitle.text(title || 'ویرایش درخواست');
        $modalBody.html('<p style="text-align:center; padding: 40px;">در حال بارگذاری...</p>');
        $overlay.add($modal).fadeIn(200);
    }

    function closeModal() {
        $overlay.add($modal).fadeOut(200);
    }

    function openEditForm(id) {
        openModal('ویرایش درخواست #' + id);
        $.post(ajaxUrl, {
            action: 'mlr_secret_editor_form',
            nonce: nonce,
            id: id
        }).done(function(res) {
            if (res.success && res.data.html) {
                $modalBody.html(res.data.html);
                $modalBody.find('#mlr-secret-edit-form').on('submit', function(e) {
                    e.preventDefault();
                    var $form = $(this);
                    var $msg = $form.find('.mlr-secret-form-msg');
                    $msg.removeClass('success error').text('در حال ذخیره...');
                    var data = { action: 'mlr_secret_editor_save', nonce: nonce };
                    $form.find('input, select, textarea').each(function() {
                        var $el = $(this);
                        if ($el.attr('name') && $el.attr('name') !== '') {
                            data[$el.attr('name')] = $el.val();
                        }
                    });
                    $.post(ajaxUrl, data).done(function(r) {
                        if (r.success) {
                            $msg.addClass('success').text(r.data.message || 'ذخیره شد.');
                            loadList();
                        } else {
                            $msg.addClass('error').text(r.data.message || 'خطا');
                        }
                    }).fail(function() {
                        $msg.addClass('error').text('خطای ارتباط.');
                    });
                });
            } else {
                $modalBody.html('<p class="error">' + (res.data && res.data.message ? res.data.message : 'خطا') + '</p>');
            }
        }).fail(function() {
            $modalBody.html('<p class="error">خطای ارتباط با سرور.</p>');
        });
    }

    function deleteRequest(id) {
        if (!confirm('آیا از حذف کامل این درخواست مطمئن هستید؟ این عمل قابل بازگشت نیست.')) {
            return;
        }
        $.post(ajaxUrl, {
            action: 'mlr_secret_editor_delete',
            nonce: nonce,
            id: id
        }).done(function(res) {
            if (res.success) {
                $tbody.find('tr[data-id="' + id + '"]').fadeOut(200, function() { $(this).remove(); });
                if ($tbody.find('tr[data-id]').length === 0) {
                    $tbody.html('<tr><td colspan="7" class="mlr-secret-empty">هیچ درخواستی یافت نشد.</td></tr>');
                }
            } else {
                alert(res.data && res.data.message ? res.data.message : 'خطا در حذف');
            }
        }).fail(function() {
            alert('خطای ارتباط با سرور.');
        });
    }

    $('#mlr-secret-filter').on('click', loadList);
    $('#mlr-secret-search').on('keypress', function(e) {
        if (e.which === 13) loadList();
    });

    $tbody.on('click', '.mlr-secret-edit-btn', function() {
        var id = $(this).data('id');
        if (id) openEditForm(id);
    });

    $tbody.on('click', '.mlr-secret-delete-btn', function() {
        var id = $(this).data('id');
        if (id) deleteRequest(id);
    });

    $overlay.add('.mlr-secret-modal-close').on('click', closeModal);
    $modal.on('click', function(e) { e.stopPropagation(); });

    loadList();
})(jQuery);
