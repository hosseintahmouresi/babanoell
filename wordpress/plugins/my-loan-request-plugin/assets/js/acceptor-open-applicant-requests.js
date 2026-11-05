jQuery(document).ready(function($) {
    $('.mlr-acceptor-marketplace').on('click', '.mlr-claim-applicant-request', function(e) {
        e.preventDefault();

        const btn = $(this);
        const card = btn.closest('.mlr-marketplace-card');
        const requestId = parseInt(btn.data('request-id'), 10);
        if (!requestId || btn.prop('disabled')) {
            return;
        }

        btn.prop('disabled', true).text('در حال پذیرش...');
        $.post(mlr_acceptor_marketplace_ajax.ajax_url, {
            action: 'mlr_acceptor_claim_applicant_request',
            nonce: mlr_acceptor_marketplace_ajax.nonce,
            request_id: requestId
        }).done(function(response) {
            if (response && response.success) {
                card.addClass('is-claimed');
                card.find('.mlr-status-badge').attr('data-status', 'claimed').text('پذیرفته شده توسط شما');
                btn.removeClass('mlr-btn-primary').addClass('mlr-btn-secondary').prop('disabled', true).text('توسط شما پذیرفته شده');
            } else {
                const message = response && response.data && response.data.message ? response.data.message : 'خطا در پذیرش پرونده.';
                alert(message);
                btn.prop('disabled', false).text('پذیرش پرونده');
            }
        }).fail(function() {
            alert('خطا در ارتباط با سرور.');
            btn.prop('disabled', false).text('پذیرش پرونده');
        });
    });
});
