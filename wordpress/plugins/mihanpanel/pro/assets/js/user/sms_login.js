jQuery(document).ready(function ($) {

    // variables
    let timer
    
    // functions
    function getCurrentUtcDate() {
        let currentLocal = new Date(),
            currentTime = new Date(
                currentLocal.getUTCFullYear(),
                currentLocal.getUTCMonth(),
                currentLocal.getUTCDate(),
                currentLocal.getUTCHours(),
                currentLocal.getUTCMinutes(),
                currentLocal.getSeconds()
            );
        return currentTime;
    }

    // events
    $(document).on('submit', 'form.mwpl-form-wrapper.mwpl-login-form', function (e) {
        e.preventDefault()
        let form = $(this),
            submitBtn = form.find('.mwpl-input-item input[type=submit]'),
            spinner = submitBtn.closest('.mwpl-input-item').find('.mwpl-spinner-wrapper'),
            formState = form.attr('mwpl-state'),
            recaptchaToken = form.getRecaptchaToken();

        if (formState == 'phone') {

            // phone step

            let phoneField = form.find('input[name=phone]')

            if (!(phoneField.val().length)) {
                showNotice(mwpl_data.msg.missing_phone_field, 'error')
                return false
            }

            disableLabels(form)
            submitBtn.addClass('hide');
            spinner.show()

            let ajaxData = `action=mwpl_login_form_sms_login&nonce=${mwpl_data.nonce}&` + form.serialize();

            $.ajax({
                url: mwpl_data.au,
                type: 'post',
                dataType: 'json',
                data: ajaxData,
                success: response => {
                    if (response.code == 200) {
                        // hide phone field
                        phoneField.closest('.mwpl-input-item').hide()

                        // show dynamic code field
                        enableLabels(form)
                        form.find('#dynamic_code').closest('.mwpl-input-item').removeClass('mwpl-hide')

                        // handle show 2fa otp
                        if(response.handle_2fa)
                        {
                            form.find('.mwpl-2fa-field-wrapper').removeClass('mwpl-hide')
                        }
                        
                        // show timer
                        if(response.expiration)
                        {
                            form.find('.mwpl-timer').removeClass('mwpl-hide')
                            startTimer(form, response.expiration)
                        }
                        

                        showNotice(response.msg, 'success')

                        // hide spinner
                        spinner.hide()

                        // change submit button text
                        submitBtn.attr('value', submitBtn.attr('mwpl-login-state-text'))

                        // show submit button
                        submitBtn.removeClass('hide')

                        // change form state
                        form.attr('mwpl-state', 'code')

                        // show change phone number text
                        let changePhoneBtn = $('<span>')
                        changePhoneBtn.attr('mwpl-action', 'back')
                        changePhoneBtn.text(response.change_phone_text)
                        form.find('.mwpl-notice-wrapper .mwpl-action-bar').html(changePhoneBtn)

                        form.closeCustomCaptcha();
                    } else {
                        enableLabels(form)
                        showNotice(response.msg, 'error')
                        
                        spinner.hide()
                        submitBtn.removeClass('hide')
                        resetSmsLoginRecaptcha();
                    }
                },
                error: err => {
                    // active fields
                    enableLabels(form)

                    spinner.hide()
                    submitBtn.removeClass('hide')
                    showNotice(mwpl_data.msg.has_error_in_request, 'error')
                    resetSmsLoginRecaptcha();
                },
                complete: () => {
                    // spinner.hide()
                    // submitBtn.removeClass('hide')
                },
            })
        } else {

            // code step

            codeField = form.find('input[name=dynamic_code]')
            if (!(codeField.val().length)) {
                showNotice(mwpl_data.msg.missing_code_field, 'error')
                return false
            }

            disableLabels(form)
            submitBtn.addClass('hide');
            spinner.show()

            let ajaxData = {
                action: 'mwpl_login_form_sms_login_validate_code_and_login',
                nonce: mwpl_data.nonce,
                code: codeField.val(),
            };

            if (recaptchaToken){
                ajaxData["g-recaptcha-response"] = recaptchaToken;
            }

            let twoFactorAutenticationField = form.find('input[name=otp_field]')
            if(twoFactorAutenticationField.length > 0)
            {
                ajaxData['two_factor_authentication_code'] = twoFactorAutenticationField.val()
            }

            $.ajax({
                url: mwpl_data.au,
                type: 'post',
                dataType: 'json',
                data: ajaxData,
                success: response => {
                    if (response.code == 200) {
                        // redirect user
                        showNotice(response.msg, 'success')
                        
                        let queryString = window.location.search,
                            params = new URLSearchParams(queryString),
                            redirect_to = params.get('redirect_to')

                        redirect_to = redirect_to ? redirect_to : (response.redirect_to ? response.redirect_to : false)

                        if (redirect_to) {
                            setTimeout(() => {
                                window.location.href = redirect_to
                            }, 200);
                        }
                    } else {
                        showNotice(response.msg, 'error')
                        enableLabels(form)
                        spinner.hide()
                        submitBtn.removeClass('hide')
                        resetSmsLoginRecaptcha();
                    }
                },
                error: err => {
                    // active fields
                    enableLabels(form)

                    spinner.hide()
                    submitBtn.removeClass('hide')
                    showNotice(mwpl_data.msg.has_error_in_request, 'error')
                    resetSmsLoginRecaptcha();
                },
            })
        }
    });
    $(document).on('click', 'form.mwpl-form-wrapper.mwpl-login-form .mwpl-action-bar > span', function (e) {
        let el = $(this),
            form = el.closest('form'),
            action = el.attr('mwpl-action'),
            submitBtn = form.find('.mwpl-input-item input[type=submit]')

        if(action == 'back')
        {
            // back form to phone step
            form.attr('mwpl-state', 'phone')

            // show phone field
            let phoneField = form.find('input[name=phone]')
            phoneField.closest('.mwpl-input-item').show()


            // hide timer
            clearInterval(timer)
            form.find('.mwpl-timer').addClass('mwpl-hide').text('')

            // hide code field
            form.find('#dynamic_code').closest('.mwpl-input-item').addClass('mwpl-hide')

            // hide 2fa field
            form.find('.mwpl-2fa-field-wrapper').addClass('mwpl-hide')

            // hide action bar
            el.closest('.mwpl-action-bar').html('')

            // remove notice
            emptyNotice()

            // change submit button text
            submitBtn.attr('value', submitBtn.attr('mwpl-code-request-state-text'))

            form.openCustomCaptcha();
        }
    })
    $(document).on('mwpl_login_timer_end', 'form.mwpl-form-wrapper.mwpl-login-form', function (e) {
        // hide timer
        let form = $(this),
            timerEl = form.find('.mwpl-timer')

        timerEl.text(mwpl_data.msg.resend_dynamic_code).attr('mwpl-resend-code', 1)
    })
    $(document).on('click', 'form.mwpl-form-wrapper.mwpl-login-form .mwpl-timer[mwpl-resend-code=1]', function (e) {
        let el = $(this),
            form = el.closest('form'),
            submitBtn = form.find('.mwpl-input-item input[type=submit]'),
            spinner = submitBtn.closest('.mwpl-input-item').find('.mwpl-spinner-wrapper')

        // empty notice
        emptyNotice()

        disableLabels(form)
        submitBtn.addClass('hide');
        spinner.show()

        // disabled resend code
        el.addClass('mwpl-disable-resend-code-btn')

        $.ajax({
            url: mwpl_data.au,
            type: 'post',
            dataType: 'json',
            data: {
                action: 'mwpl_login_form_sms_login_resend_dynamic_code',
                nonce: mwpl_data.nonce,
            },
            success: response => {
                if (response.code == 200) {
                    // show notice message
                    showNotice(response.msg, 'success')
                    
                    // remove disabled class in resend code wrapper
                    el.removeClass('mwpl-disable-resend-code-btn')

                    // show and start timer
                    if (response.expiration) {
                        el.removeAttr('mwpl-resend-code')
                        startTimer(form, response.expiration)
                        form.find('.mwpl-timer').removeClass('mwpl-hide')
                    }
                } else {
                    // enable resend code btn
                    el.removeClass('mwpl-disable-resend-code-btn')

                    // show notice
                    showNotice(response.msg, 'error')
                }
                // enable labels
                enableLabels(form)
                // hide spinner
                spinner.hide()
                // show submit button
                submitBtn.removeClass('hide')
            },
            error: err => {
                // active fields
                enableLabels(form)
                el.removeClass('mwpl-disable-resend-code-btn')

                spinner.hide()
                submitBtn.removeClass('hide')
                showNotice(mwpl_data.msg.has_error_in_request, 'error')
            },
            complete: () => {
                // spinner.hide()
                // submitBtn.removeClass('hide')
            },
        })

    })
})

const resetSmsLoginRecaptcha = function (){
    if (typeof resetGoogleRecaptcha === 'function'){
        resetGoogleRecaptcha();
    }
}