jQuery(document).ready(function($){
    var mw_btn = $("#mw_get_dynamic_code"),
        mw_request_code_txt = $("#mw_request_code_text"),
        mw_timer = $("#mw_timer"),
        mw_submit_icon = $("#mw_submit_icon"),
        mw_code_input = $('input.mw_dyna_code');
        mw_response_msg = $('.mw_dynamic_code_wrapper small.res_msg');
    function getCurrentUtcDate()
    {
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
    function start_timer(expiration_time)
    {
        var hour_miliseconds = 60 * 60 * 1000,
            minutes_miliseconds = 60 * 1000;
        
        if (typeof(expiration_time) == 'undefined')
        {
            expiration_time = mw_timer.attr('exp');
        }
        expiration_time = new Date(expiration_time).getTime();
        if (!expiration_time)
        {
            return false;
        }
        var timer = setInterval(() => {
            var current_time = getCurrentUtcDate().getTime(),
                distance = parseInt(expiration_time) - parseInt(current_time);
            var minutes = Math.floor((distance % hour_miliseconds) / minutes_miliseconds),
                seconds = Math.floor((distance % minutes_miliseconds) / 1000);
            
            if (minutes.toString().length == 1)
            {
                minutes = '0' + minutes.toString();
            }
            if (seconds.toString().length == 1)
            {
                seconds = '0' + seconds.toString();
            }
            mw_timer.html('<span id="mw_minutes">' + minutes + '</span> : <span id="mw_second">' + seconds + '</span>');
            if (distance <= 0)
            {
                clearInterval(timer);
                mw_timer.html('00 : 00');
                mw_timer.hide();
                mw_request_code_txt.show();
                mw_submit_icon.hide();
                mw_btn.removeAttr('disabled');
                mw_btn.attr('mw_mode', 'request');
            }
        }, 1000);
    }
    start_timer();
    $("input.mw_dyna_code").on('input', function(e){
        if(this.value.length > 6)
        {
            this.value = this.value.slice(0, 6);
        }
        if(this.value.length == 6)
        {
            mw_btn.click()
        }
        if(mw_btn.attr('mw_mode') !== 'request' && this.value.length == 6)
        {
            // mw_btn.removeAttr('disabled');
            mw_submit_icon.show();
        }else{
            if(mw_btn.attr('mw_mode') !== 'request')
            {
                mw_btn.attr('disabled', 1);
            }
        }
    });
    $("#mw_get_dynamic_code").on('click', function(e){
        e.preventDefault();
        var mw_this = $(this),
            mw_mode = mw_this.attr('mw_mode'),
            mwpl_nonce = mwp_data.nonce;
        
        mw_btn.attr('disabled', 1);
        if(mw_mode == 'request')
        {
            var mw_phone = $("#mw_phone_number").val();
            if (!mw_phone.length)
            {
                mw_response_msg.text(mwp_data.errors.use_valid_phone);
                mw_btn.removeAttr('disabled');
                return false;
            }
            // send ajax
            $.ajax({
                url: mwp_data.au,
                dataType: 'json',
                type: 'post',
                data: {
                    action: 'mw_get_mobile_verification_code',
                    mobile: mw_phone,
                    mwpl_nonce: mwpl_nonce
                },
                success: function (response)
                {
                    if (response.code == 200)
                    {
                        mw_request_code_txt.hide();
                        $("#mw_timer").show();
                        start_timer(response.expire_time);
                        mw_this.attr('mw_mode', 'validate');
                        mw_response_msg.text(response.msg);
                    } else {
                        mw_response_msg.text(response.msg);
                        mw_btn.removeAttr('disabled');
                    }
                },
                error: function(response){
                    alert('Has error in your request')
                    mw_btn.removeAttr('disabled');
                }
            });
        }
        if (mw_mode == 'validate')
        {
            var mw_code = mw_code_input.val();
            mw_code_input.attr('disabled', 1)
            $.ajax({
                url: mwp_data.au,
                dataType: 'json',
                type: 'post',
                data: {
                    action: 'mw_verify_mobile_verification_code',
                    code: mw_code,
                    mwpl_nonce: mwpl_nonce
                },
                success: function (response) {
                    if (response.code == 200)
                    {
                        setTimeout(() => {
                            window.location.href = window.location.href;
                        }, 1000);
                        mw_submit_icon.addClass('dashicons-yes');
                    } else {
                        // mw_btn.removeAttr('disabled');
                        mw_code_input.removeAttr('disabled')
                    }
                    mw_response_msg.text(response.msg);
                }
            });
        }
    });
    $('.mw_dynamic_code_input_box').on('click', function(){
        $('input.mw_dyna_code').focus();
    });
});