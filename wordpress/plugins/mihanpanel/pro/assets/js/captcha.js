const mwplGenerateCatptcha = function(element_id = null, type = 'number'){
    let element = jQuery((element_id ? '#' + element_id : '.mwpl-captcha')),
        btn = element.parent().find('.mwpl-reload-captcha'),
        captchaImg = element.closest('.captcha-field-group').find('.captcha-img');

    jQuery.ajax({
        url: mwpl_captcha.ajax_url,
        data: {
            action: 'mp_generate_captcha',
        },
        type: 'POST',
        dataType: 'json',
        beforeSend: function (){
            btn.addClass('mp-show-reload');
        },
        success: function (response) {
            btn.removeClass('mp-show-reload');
            if (response && typeof response.image !== "undefined"){
                captchaImg.attr('src', 'data:image/png;base64,' + response.image).show();
            }
        },
        error: function () {
            btn.removeClass('mp-show-reload');
        }
    });
}
const mwplReGenerateCaptchaCodes = function (){
    mwplGenerateCatptcha();
}

jQuery(document).ready(function ($){
    mwplReGenerateCaptchaCodes();

    $(document).on('click', '.mwpl-reload-captcha', function (e) {
        e.preventDefault();
        let captchaID = $(this).closest('.captcha-field-group').find('.mwpl-captcha').attr('id');
        mwplGenerateCatptcha(captchaID);
    });

    $('.captcha-field-group').on("mwpl_open_custom_captcha", function() {
        let wrap = $(this),
            captchaID = wrap.find('.mwpl-captcha').attr('id');
        wrap.find('input').val('');
        mwplGenerateCatptcha(captchaID);
    });

    $(document).on('click', '.mp-toggle-form-btn', function() {
        let btn = $(this);
        setTimeout(function (){
            let form = $('.mwpl-form-wrapper:visible'),
                captchaID = form.find('.mwpl-captcha').attr('id');
            if (captchaID){
                mwplGenerateCatptcha(captchaID);
            }
        }, 1100);
    });
});
