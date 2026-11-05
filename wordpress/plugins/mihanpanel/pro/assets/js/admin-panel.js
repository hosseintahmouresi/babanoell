jQuery(document).ready(function($){
    // functions
    function handleSetAdminPhoneNumberModalProcess(el)
    {
        let userID = el.closest('.mp_option_field').find('.mwpl_modal_wrapper .mwpl_modal_form #admin_profile_phone_number').attr('user_id')

        $.ajax({
            url: mwp_data.au,
            type: 'post',
            dataType: 'json',
            data: {
                action: 'mwpl_admin_professional_login_check_admin_user_has_valid_phone',
                user_id: userID,
            },
            success: response => {
                if(response.status == 200)
                {
                    if(!response.has_valid_phone)
                    {
                        // show modal
                        el.closest('.mp_option_field').find('.mwpl_modal_wrapper').fadeIn()
                    }
                }
            },
        })
    }
    
    $(document).on('change', 'select[name=mp_sms_provider]', function (e) {
        let settings_wrapper = $(".mw_sms_provider_settings"),
            tmp = $('<span>')

        tmp.addClass('loading')
        tmp.text(mwp_pro_data.texts.sms_provider_settings_loading)
        tmp.css({
            padding: '20px',
            display: 'flex',
            'justify-content': 'center',
            'font-size': '30px',
            opacity: '.5',
        })
        settings_wrapper.html(tmp);
        $.ajax({
            url: mwp_data.au,
            type: 'post',
            data: {
                action: 'mw_get_sms_provider_settings',
                provider: this.value
            },
            success: function (response) {
                settings_wrapper.html(response);
            }       
        });
    });

    $(document).on('change', '.captcha-depend', function (e) {
        let input = $(this),
            inputs = $(`.captcha-depend:not([name="${input.attr('name')}"])`);
        if (input.is(':checked')){
            inputs.prop('checked', false).change();
        }
    });

    function showMsg(msg)
    {
        let msgBoxWrapper = $(document).find('.mwpl_notification_box_wrapper')
        if(msgBoxWrapper.length === 0)
        {
            msgBoxWrapper = $('<div class="mwpl_notification_box_wrapper">')
            $('body').prepend(msgBoxWrapper)
        }
        let msgBox = $('<div class="mwpl_notification_box">')
        msgBox.text(msg)
        msgBoxWrapper.prepend(msgBox)
        setTimeout(() => {
            msgBox.fadeOut('slow', function(){
                msgBox.remove()
            })
        }, 3000);
    }
    // handle test sms btn
    $(document).on('click', '#test_sms_phone_number_btn', function(e){
        e.preventDefault()
        let mw_this = $(this),
            parent = mw_this.closest('.mp_option_field'),
            phone = parent.find('#test_sms_phone_number'),
            nonce = parent.attr('mwpl_nonce')
        $.ajax({
            url: mwp_data.au,
            type: 'post',
            dataType: 'json',
            data: {
                action: 'mw_send_test_sms',
                mwpl_nonce: nonce,
                phone: phone.val()
            },
            success: function (response) {
                console.log(response)
                showMsg(response.msg)
                if(response.code == 200)
                {

                }else{
                }
            },
            error: err => {
                console.log(err)
            }
        });
    });
    $(document).on('keypress', '#test_sms_phone_number', function(e){
        let keyCode = e.keyCode || e.which
        if(keyCode == 13)
        {
            e.preventDefault();
            $(this).closest('.mp_option_field').find('#test_sms_phone_number_btn').click()
        }
    });
    $(document).on('change', 'input[name=mp_smart_login]', function(e){
        let isChecked = e.target.checked,
            password_type_field = $('input[name=mp_password_type'),
            hide_email_field = $('input[name=mp_hide_email_field_in_register_form]'),
            hide_username_field = $('input[name=mp_hide_username_field_in_register_form]'),
            hide_password_field = $('input[name=mp_hide_password_field_in_register_form]'),
            auto_confirm_phone_field = $('input[name=mp_auto_confirm_phone_after_register]')
            force_obtain_phone_number_in_panel = $('input[name=mp_force_get_phone_number_in_panel]'),
            generate_random_username = $('input[name=mp_smart_login_generate_random_username]')
        if(isChecked)
        {
            password_type_field.prop('disabled', true)
            hide_email_field.prop('disabled', true)
            hide_username_field.prop('disabled', true)
            hide_password_field.prop('disabled', true)
            auto_confirm_phone_field.prop('disabled', true)

            hide_email_field.prop('checked', false)
            hide_username_field.prop('checked', false)
            hide_password_field.prop('checked', false)
            auto_confirm_phone_field.prop('checked', false)

            force_obtain_phone_number_in_panel.prop('disabled', false)
            generate_random_username.prop('disabled', false)
        }else
        {
            if($('input[name=mp_password_type]:checked').val() !== 'default')
            {
                hide_email_field.prop('disabled', false)
                hide_username_field.prop('disabled', false)
                hide_password_field.prop('disabled', false)
            }
            password_type_field.prop('disabled', false)
            auto_confirm_phone_field.prop('disabled', false)
            
            force_obtain_phone_number_in_panel.prop('checked', false)
            force_obtain_phone_number_in_panel.prop('disabled', true)
            generate_random_username.prop('checked', false)
            generate_random_username.prop('disabled', true)
        }
    })
    $(document).on('change', 'input[name=mp_password_type]', function(e){
        let value = e.target.value,
            hide_email_field = $('input[name=mp_hide_email_field_in_register_form]'),
            hide_username_field = $('input[name=mp_hide_username_field_in_register_form]'),
            hide_password_field = $('input[name=mp_hide_password_field_in_register_form]')
        switch(value)
        {
            case 'dynamic':
            case 'both':
                hide_email_field.prop('disabled', false)
                hide_username_field.prop('disabled', false)
                hide_password_field.prop('disabled', false)

                handleSetAdminPhoneNumberModalProcess($(this))
                break;
            default:
                hide_email_field.prop('checked', false)
                hide_email_field.prop('disabled', true)
                hide_username_field.prop('checked', false)
                hide_username_field.prop('disabled', true)
                hide_password_field.prop('checked', false)
                hide_password_field.prop('disabled', true)
        }
    })
    $(document).on('click', '.mwpl_modal_wrapper .mwpl_modal_form .close_modal_box', function(e){
        e.preventDefault()
        $(document).find('input[type=radio][name=mp_password_type]#mp_password_type_default').prop('checked', true)
        $(this).closest('.mwpl_modal_wrapper').fadeOut()
    })
    $(document).on('click', '.mwpl_modal_wrapper .mwpl_modal_form input[type=submit]#submit_admin_user_phone_value', function(e){
        e.preventDefault()
        let el = $(this),
            phoneNumberField = el.closest('.mwpl_modal_form').find('input#admin_profile_phone_number'),
            userID = el.closest('.mwpl_modal_form').find('#admin_profile_phone_number').attr('user_id')


        if(!phoneNumberField.val())
        {
            phoneNumberField.css('border', '1px solid red')
            return
        }
        el.css({
            opacity: '.5',
            'pointer-events': 'none',
        })
        $.ajax({
            url: mwp_data.au,
            type: 'post',
            dataType: 'json',
            data: {
                action: 'mwpl_admin_professional_login_handle_admin_user_has_valid_phone_set_value',
                value: phoneNumberField.val(),
                user_id: userID,
            },
            success: response => {
                if(response.code == 200)
                {
                    // hide modal
                    el.closest('.mwpl_modal_wrapper').fadeOut()
                }else{
                    // show error
                    el.closest('.mwpl_modal_wrapper').find('.form-notice > span').text(response.msg)
                }
            },
            complete: () => {
                el.removeAttr('style')
            }
        })
    })
    function deleteUserAjax(userID, endUser=false)
    {
        let logs = $(document).find('textarea#delete_ghost_users_log')
        let data = {
            action: 'mw_admin_delete_ghost_users',
            current_id: userID,
        }
        if(endUser)
        {
            data['end_user'] = endUser
        }
        $.ajax({
            url: mwp_data.au,
            type: 'post',
            dataType: 'json',
            data: data,
            success: function (response) {
                if(response.log)
                {
                    if(typeof response.log == 'object')
                    {
                        response.log.forEach(value => {
                            logs.val(logs.val() + value + '\r\n')
                        })
                    }else{
                        logs.val(logs.val() + response.log + '\r\n')
                    }
                    logs.scrollTop(logs[0].scrollHeight)
                }
                if(!response.stop)
                {
                    endUser = response.end_user ? response.end_user : false;
                    deleteUserAjax(parseInt(userID) + 1, endUser)
                }
                if(response.stop && response.stop !== undefined)
                {
                    $(document).find('input[name=delete_ghost_users]').removeAttr('disabled')
                }
                
            },
            error: err => {
                console.log(err)
            },
            complete: () => {
            }
        });
    }
    $(document).on('click', 'input[name=delete_ghost_users]', function(e){
        e.preventDefault();
        if(!confirm($(this).attr('prompt_text')))
        {
            return false;
        }
        let el = $(this),
            currentID = el.parent().find('input[name=delete_ghost_users_current_id]').val()
        let logsWrapper = $(document).find('#delete_ghost_users_log')
        if(!logsWrapper.length)
        {
            logsWrapper = $('<textarea id="delete_ghost_users_log" cols="30" rows="10">')
            let fieldItem = $('<div class="field-item">')
            fieldItem.append(logsWrapper)
            fieldItem.css('margin-top', 20)
            el.closest('.field-item').after(fieldItem)
        }
        el.attr('disabled', 'disabled')
        deleteUserAjax(currentID)
    })
    setTimeout(() => {
        $(document).find('input[type=checkbox][show_field_is_active]').change()
    }, 400);
    $(document).on('change', 'input[type=checkbox][show_field_is_active]', function(e){
        let el = $(this),
            targetField = $(document).find('#' + el.attr('field_target'))
        
        if(el.is(':checked'))
        {
            // show target field
            targetField.removeAttr('disabled')
            targetField.closest('p').slideDown()
        }else{
            // hide target field
            targetField.attr('disabled', 'disabled')
            targetField.closest('p').slideUp()
        }
    })
});