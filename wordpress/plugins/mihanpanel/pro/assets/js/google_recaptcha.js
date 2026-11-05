"use strict";

/** START RECAPTCHA FUNCTIONS -- Don`t edit functions name **/

 function mwpAgrLoad(){
    if (!isExistsGoogleRecaptcha()) return false;

    let button = jQuery('input[type="submit"], button[type="submit"]');
    const recaptcha = document.getElementsByClassName('agr-recaptcha-wrapper');
    for (let i = 0; i < recaptcha.length; i++) {
        grecaptcha.render(recaptcha.item(i), {
            sitekey: agrRecaptcha.site_key,
            callback: function (){
                button.removeClass('no-pointer');
            }, // success
            'expired-callback': function (e){
                if (isVisibleRecaptcha()){
                    button.addClass('no-pointer');
                }
                grecaptcha.reset();
            },
            'error-callback': function (e){
                if (isVisibleRecaptcha()){
                    button.addClass('no-pointer');
                }
                grecaptcha.reset();
            }
        });
    }
}

function mwpAgrV3(){
    if (!isExistsGoogleRecaptcha()) return false;

    let button = jQuery('input[type="submit"], button[type="submit"]');
    grecaptcha.ready(function() {
        grecaptcha.execute(agrRecaptcha.site_key, {
            action: 'validate_recaptchav3'
        }).then(function (token) {
            button.removeClass('no-pointer');
            document.querySelectorAll('.g-recaptcha-response').forEach(function (elem) {
                elem.value = token;
            });
        }).catch(function(e) {
            if (!isVisibleRecaptcha()) return false;
            button.addClass('no-pointer');
        });
    });
}

/** END RECAPTCHA FUNCTIONS **/

const isExistsGoogleRecaptcha = function (){
    return typeof grecaptcha === 'object';
}

const resetGoogleRecaptcha = function (){
    if (!isExistsGoogleRecaptcha()) return false;
    grecaptcha.reset();
}

const isVisibleRecaptcha = function(){
    let wrapper = jQuery('.agr-recaptcha-wrapper');
    return wrapper.is(':visible');
}