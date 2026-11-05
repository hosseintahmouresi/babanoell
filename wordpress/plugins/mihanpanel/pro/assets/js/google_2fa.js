jQuery(document).ready(function($){
    $('form[name=loginform]').on('mwpl_show_password_field', function(e, usernameData){
        // render OTP field
        $.ajax({
            url: mwp_data.au,
            type: 'post',
            dataType: 'json',
            data: {
                action: 'mwpl_google_2fa_render_field_in_login',
                field: usernameData.field,
                value: usernameData.value,
            },
            success: response => {
                console.log(response);
                if(response.code == 200 && response.field_view)
                {
                    $('form[name=loginform]').find('#smart_login_code_wrapper').after(response.field_view)
                }
            },
            error: () => {
                alert("Has error")
            }
        })
    })
    $('form[name=loginform]').on('while_handle_back_btn', function(e){
        $('form[name=loginform]').find("#mwpl_google_2fa_field_wrapper").remove()
    })
})