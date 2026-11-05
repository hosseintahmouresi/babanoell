jQuery(document).ready(function($){
    $('.my-color-field[name=login_button_text_color]').on('mwpl_color_picker_value_changed', function(_e,el, newColor){
        let element = $(el),
            live_view_target_id = element.closest('[live_view_target_id]').attr('live_view_target_id')
        if(!live_view_target_id)
        {
            return false;
        }
        let live_view = $(`.mwpl_live_view_wrapper #${live_view_target_id}`),
            live_view_login_btn = live_view.find('.login-btn')
        live_view_login_btn.css('color', newColor)
    })
    $('.my-color-field[name=login_fields_bg_color]').on('mwpl_color_picker_value_changed', function(_e,el, newColor){
        let element = $(el),
            live_view_target_id = element.closest('[live_view_target_id]').attr('live_view_target_id')
        if(!live_view_target_id)
        {
            return false;
        }
        let live_view = $(`.mwpl_live_view_wrapper #${live_view_target_id}`),
            live_view_field = live_view.find('.field')
        live_view_field.css({
            'background-color': newColor,
        })
    })
    $('.my-color-field[name=login_fields_label_color]').on('mwpl_color_picker_value_changed', function(_e,el, newColor){
        let element = $(el),
            live_view_target_id = element.closest('[live_view_target_id]').attr('live_view_target_id')
        if(!live_view_target_id)
        {
            return false;
        }
        let live_view = $(`.mwpl_live_view_wrapper #${live_view_target_id}`),
            live_view_field = live_view.find('.label')
        live_view_field.css({
            'color': newColor,
        })
    })
    $('.my-color-field[name=login_fields_text_color]').on('mwpl_color_picker_value_changed', function(_e,el, newColor){
        let element = $(el),
            live_view_target_id = element.closest('[live_view_target_id]').attr('live_view_target_id')
        if(!live_view_target_id)
        {
            return false;
        }
        let live_view = $(`.mwpl_live_view_wrapper #${live_view_target_id}`),
            live_view_field = live_view.find('.field')
        live_view_field.css({
            'color': newColor,
        })
    })
    $(document).on('mwpl_before_show_live_view_field', '[live_view_target_id=mwpl_live_view_login_text_fields]', function(e){
        // get ui theme
        let live_view = $('.mwpl_live_view_wrapper #mwpl_live_view_login_text_fields'),
            login_theme = $('input[name=login_form_theme]:checked').val()
        if(!login_theme)
        {
            login_theme = 'light'
        }
        live_view.removeClass('dark light')
        live_view.addClass(login_theme)
        // handle field vlaue
        let fields_bg_color = $('[live_view_target_id=mwpl_live_view_login_text_fields] input[name=login_fields_bg_color]').val(),
            fields_label_color = $('[live_view_target_id=mwpl_live_view_login_text_fields] input[name=login_fields_label_color').val(),
            fields_text_color = $('[live_view_target_id=mwpl_live_view_login_text_fields] input[name=login_fields_text_color').val()
        live_view.find('.field').css({
            'background-color': fields_bg_color,
            color: fields_text_color
        })
        live_view.find('.label').css('color', fields_label_color)
    })
    // info messagebox
    $('.my-color-field[name=login_info_messagebox_bg_color]').on('mwpl_color_picker_value_changed', function(_e,el, newColor){
        let element = $(el),
            live_view_target_id = element.closest('[live_view_target_id]').attr('live_view_target_id')
        if(!live_view_target_id)
        {
            return false;
        }
        let live_view = $(`.mwpl_live_view_wrapper #${live_view_target_id}`),
            message_box = live_view.find('.info_messagebox')
        message_box.css({
            'background-color': newColor,
        })
    })
    $('.my-color-field[name=login_info_messagebox_text_color]').on('mwpl_color_picker_value_changed', function(_e,el, newColor){
        let element = $(el),
            live_view_target_id = element.closest('[live_view_target_id]').attr('live_view_target_id')
        if(!live_view_target_id)
        {
            return false;
        }
        let live_view = $(`.mwpl_live_view_wrapper #${live_view_target_id}`),
            message_box = live_view.find('.info_messagebox')
        message_box.css({
            'color': newColor,
        })
    })
    $(document).on('mwpl_before_show_live_view_field', '[live_view_target_id=mwpl_live_view_login_info_messagebox]', function(e){
        // get ui theme
        let live_view = $('.mwpl_live_view_wrapper #mwpl_live_view_login_info_messagebox'),
            login_theme = $('input[name=login_form_theme]:checked').val()
        if(!login_theme)
        {
            login_theme = 'light'
        }
        live_view.removeClass('dark light')
        live_view.addClass(login_theme)
        // handle field vlaue
        let message_box_bg_color = $('[live_view_target_id=mwpl_live_view_login_info_messagebox] input[name=login_info_messagebox_bg_color]').val(),
            message_box_text_color = $('[live_view_target_id=mwpl_live_view_login_info_messagebox] input[name=login_info_messagebox_text_color]').val()
        live_view.find('.info_messagebox').css({
            'background-color': message_box_bg_color,
            'color': message_box_text_color
        })
    })
});