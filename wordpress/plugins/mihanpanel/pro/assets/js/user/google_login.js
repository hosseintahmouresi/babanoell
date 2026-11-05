jQuery(document).ready(function ($) {
    $(document).on('click', '.mwpl-login-form #google_login_btn', function (e) {
        e.preventDefault()

        if (typeof mwpl_data.au == 'undefined') {
            return
        }

        let el = $(this),
            formWrapper = el.closest('.mwpl-form-wrapper.mwpl-login-form'),
            submitBtns = formWrapper.find('.mwpl-input-item input[type=submit], .mwpl-input-item .mwpl-register-link, .mwpl-input-item .mwpl-btn-behavior'),
            spinner = formWrapper.find('.mwpl-spinner-wrapper')


        submitBtns.addClass('hide')
        spinner.show()

        let activateBtnState = true
        
        $.ajax({
            url: mwpl_data.au,
            type: 'post',
            dataType: 'json',
            data: {
                action: 'mwpl_google_login_init_request',
                nonce: $(this).attr('nonce'),
            },
            success: response => {
                let type = 'error'
                if (response.status == 200) {
                    activateBtnState = false
                    // redirect to google
                    type = 'success'
                    window.location.href = response.auth_url
                }
                showNotice(response.msg, type)
            },
            error: () => {
                let msg = typeof mwpl_data.msg.has_error_in_request == 'undefined' ? 'has error' : mwpl_data.msg.has_error_in_request
                showNotice(msg, 'error')
            },
            complete: () => {
                if(activateBtnState)
                {
                    spinner.hide()
                    submitBtns.removeClass('hide')
                }
            }
        })
    })
})