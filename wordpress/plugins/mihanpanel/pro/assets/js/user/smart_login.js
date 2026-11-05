jQuery(document).ready(function ($) {
    let dynamic_code_type
    
    // events
    $(document).on('submit', 'form.mwpl-form-wrapper.mwpl-login-form', function (e) {
        e.preventDefault()
        let form = $(this),
            submitBtn = form.find('.mwpl-input-item input[type=submit]'),
            recaptchaToken = form.getRecaptchaToken(),
            spinner = submitBtn.closest('.mwpl-input-item').find('.mwpl-spinner-wrapper'),
            formState = form.attr('mwpl-state')

        emptyNotice()
        if (formState == 'username') {

            // username step

            let usernameField = form.find('input[name=username]')

            if (!(usernameField.val().length)) {
                showNotice(mwpl_data.msg.missing_username_field, 'error')
                return false
            }

            disableLabels(form)
            submitBtn.addClass('hide');
            spinner.show()

            let ajaxData = `action=mwpl_smart_login_form_check_username&nonce=${mwpl_data.nonce}&` + form.serialize();

            $.ajax({
                url: mwpl_data.au,
                type: 'post',
                dataType: 'json',
                data: ajaxData,
                success: response => {
                    if (response.code == 200) {
                        // hide username field
                        usernameField.closest('.mwpl-input-item').addClass('mwpl-hide')

                        if (response.login_type) {
                            dynamic_code_type = 'login'

                            // change form state
                            form.attr('mwpl-state', 'password')

                            // show password field
                            form.find('input[name=password]').closest('.mwpl-input-item').removeClass('mwpl-hide')
                            form.find('input[type=password]').focus()

                            // handle show 2fa otp
                            if (response.handle_2fa) {
                                form.find('.mwpl-2fa-field-wrapper').removeClass('mwpl-hide')
                            }

                            // show back btn and request code btn
                            let backBtn = $('<span>'),
                                requestCodeBtn = $('<span>')

                            backBtn.attr('mwpl-action', 'back')
                            backBtn.text(mwpl_data.msg.back)
                            requestCodeBtn.attr('mwpl-action', 'send_code')
                            requestCodeBtn.text(mwpl_data.msg.send_dynamic_code)

                            form.find('.mwpl-action-bar').append(requestCodeBtn, backBtn)
                        } else if (response.register) {
                            dynamic_code_type = 'register'

                            // change form state
                            form.attr('mwpl-state', 'code')

                            // check is send dynamic code

                            // show dynamic code field
                            form.find('input[name=dynamic_code]').closest('.mwpl-input-item').removeClass('mwpl-hide')
                            form.find('input[name=dynamic_code]').focus()

                            // show timer
                            startTimer(form, response.expiration)
                            form.find('.mwpl-timer').removeClass('mwpl-hide')
                        }

                        // enable labels
                        enableLabels(form)
                        // hide spinner
                        spinner.hide()
                        // show submit button
                        submitBtn.removeClass('hide')
                        showNotice(response.msg, 'success')
                        form.closeCustomCaptcha();
                        form.closeRecaptcha();
                    } else {
                        enableLabels(form)

                        // hide spinner
                        spinner.hide()

                        // show submit button
                        submitBtn.removeClass('hide')

                        showNotice(response.msg, 'error')
                        resetSmartLoginRecaptcha();
                    }
                },
                error: err => {
                    // active fields
                    enableLabels(form)

                    spinner.hide()
                    submitBtn.removeClass('hide')
                    showNotice(mwpl_data.msg.has_error_in_request, 'error')
                    resetSmartLoginRecaptcha();
                },
                complete: () => {
                    // spinner.hide()
                    // submitBtn.removeClass('hide')
                },
            })
        } else if (formState == 'password') {
            // userID was stored in session

            let passwordField = form.find('input[name=password]')
            if (!(passwordField.val().length)) {
                showNotice(mwpl_data.msg.missing_password_field, 'error')
                return false
            }

            disableLabels(form)
            submitBtn.addClass('hide');
            spinner.show()
            let data = {
                action: 'mwpl_smart_login_form_check_password',
                nonce: mwpl_data.nonce,
                password: passwordField.val(),
            }

            let twoFactorAutenticationField = form.find('input[name=otp_field]')
            if (twoFactorAutenticationField.length > 0) {
                data['two_factor_authentication_code'] = twoFactorAutenticationField.val()
            }

            $.ajax({
                url: mwpl_data.au,
                type: 'post',
                dataType: 'json',
                data: data,
                success: response => {
                    if (response.code == 200) {
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
                        enableLabels(form)
                        // hide spinner
                        spinner.hide()
                        // show submit button
                        submitBtn.removeClass('hide')
                        showNotice(response.msg, 'error')
                        resetSmartLoginRecaptcha();
                    }
                },
                error: err => {
                    // active fields
                    enableLabels(form)

                    spinner.hide()
                    submitBtn.removeClass('hide')
                    showNotice(mwpl_data.msg.has_error_in_request, 'error')
                    resetSmartLoginRecaptcha();
                },
                complete: () => {
                    // spinner.hide()
                    // submitBtn.removeClass('hide')
                },
            })

        } else if (formState == 'code') {

            let codeField = form.find('input[name=dynamic_code]')
            if (!(codeField.val().length)) {
                showNotice(mwpl_data.msg.missing_code_field, 'error')
                return false
            }

            disableLabels(form)
            submitBtn.addClass('hide');
            spinner.show()

            let ajaxData = {
                action: 'mwpl_smart_login_form_validate_dynamic_code',
                nonce: mwpl_data.nonce,
                code: codeField.val(),
            };

            let twoFactorAutenticationField = form.find('input[name=otp_field]')
            if (twoFactorAutenticationField.length > 0) {
                ajaxData['two_factor_authentication_code'] = twoFactorAutenticationField.val()
            }

            $.ajax({
                url: mwpl_data.au,
                type: 'post',
                dataType: 'json',
                data: ajaxData,
                success: response => {
                    if (response.code == 200) {
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
                        enableLabels(form)
                        // hide spinner
                        spinner.hide()
                        // show submit button
                        submitBtn.removeClass('hide')
                        showNotice(response.msg, 'error')
                        resetSmartLoginRecaptcha();
                    }
                },
                error: err => {
                    // active fields
                    enableLabels(form)

                    spinner.hide()
                    submitBtn.removeClass('hide')
                    showNotice(mwpl_data.msg.has_error_in_request, 'error')
                    resetSmartLoginRecaptcha();
                },
                complete: () => {
                    // spinner.hide()
                    // submitBtn.removeClass('hide')
                },
            })
        }
    });

    $(document).on('click', 'form.mwpl-form-wrapper.mwpl-login-form .mwpl-action-bar > span', function (e) {
        let el = $(this),
            form = el.closest('form'),
            action = el.attr('mwpl-action'),
            submitBtn = form.find('.mwpl-input-item input[type=submit]'),
            spinner = submitBtn.closest('.mwpl-input-item').find('.mwpl-spinner-wrapper'),
            recaptchaToken = form.getRecaptchaToken();

        emptyNotice()
        if (action == 'back') {
            // back to the username field
            // change form state
            form.attr('mwpl-state', 'username')

            // hide all steps
            // form.find('.mwpl-input-item').addClass('mwpl-hide')
            form.find('.mwpl-password-field-wrapper').addClass('mwpl-hide')
            form.find('.mwpl-code-field-wrapper').addClass('mwpl-hide')
            form.find('.mwpl-timer').addClass('mwpl-hide')

            // hide 2fa field
            form.find('.mwpl-2fa-field-wrapper').addClass('mwpl-hide')

            // show username field
            form.find('input[name=username]').closest('.mwpl-input-item').removeClass('mwpl-hide')

            // empty action bar
            form.find('.mwpl-action-bar').html('')

            form.openCustomCaptcha();
        } else if (action == 'send_code') {
            disableLabels(form)
            submitBtn.addClass('hide');
            spinner.show()

            let ajaxData = {
                action: 'mwpl_smart_login_form_request_dynamic_code_for_login',
                nonce: mwpl_data.nonce,
            };

            if (recaptchaToken) {
                ajaxData["g-recaptcha-response"] = recaptchaToken;
            }

            $.ajax({
                url: mwpl_data.au,
                type: 'post',
                dataType: 'json',
                data: ajaxData,
                success: response => {
                    if (response.code == 200) {
                        showNotice(response.msg, 'success')

                        // change form state
                        form.attr('mwpl-state', 'code')

                        // hide password field
                        form.find('.mwpl-password-field-wrapper').addClass('mwpl-hide')

                        // show dynamic code field
                        form.find('input[name=dynamic_code]').closest('.mwpl-input-item').removeClass('mwpl-hide')
                        form.find('input[name=dynamic_code]').focus()

                        // show and start timer
                        if (response.expiration) {
                            startTimer(form, response.expiration)
                            form.find('.mwpl-timer').removeClass('mwpl-hide')
                        }

                        // show change phone number text
                        if (response.change_phone_text) {
                            let changePhoneBtn = $('<span>')
                            changePhoneBtn.attr('mwpl-action', 'back')
                            changePhoneBtn.text(response.change_phone_text)
                            form.find('.mwpl-notice-wrapper .mwpl-action-bar').html(changePhoneBtn)
                        }

                    } else {

                        // show notice
                        showNotice(response.msg, 'error')
                        resetSmartLoginRecaptcha();
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

                    spinner.hide()
                    submitBtn.removeClass('hide')
                    showNotice(mwpl_data.msg.has_error_in_request, 'error')
                    resetSmartLoginRecaptcha();
                },
                complete: () => {
                    // spinner.hide()
                    // submitBtn.removeClass('hide')
                },
            })
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
            spinner = submitBtn.closest('.mwpl-input-item').find('.mwpl-spinner-wrapper'),
            recaptchaToken = form.getRecaptchaToken();

        // empty notice
        emptyNotice()

        disableLabels(form)
        submitBtn.addClass('hide');
        spinner.show()

        // disabled resend code
        el.addClass('mwpl-disable-resend-code-btn')

        let ajaxData = {
            action: 'mwpl_smart_login_resend_dynamic_code',
            nonce: mwpl_data.nonce,
            dynamic_code_for: dynamic_code_type
        };

        if (recaptchaToken) {
            ajaxData["g-recaptcha-response"] = recaptchaToken;
        }

        $.ajax({
            url: mwpl_data.au,
            type: 'post',
            dataType: 'json',
            data: ajaxData,
            success: response => {
                if (response.code == 200) {
                    // show notice message
                    showNotice(response.msg, 'success')
                    
                    // remove disabled class from resend code wrapper
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
                    resetSmartLoginRecaptcha();
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
                resetSmartLoginRecaptcha();
            },
            complete: () => {
                // spinner.hide()
                // submitBtn.removeClass('hide')
            },
        })

    })

})

const resetSmartLoginRecaptcha = function () {
    if (typeof resetGoogleRecaptcha === 'function') {
        resetGoogleRecaptcha();
    }
}