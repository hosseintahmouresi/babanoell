jQuery(document).ready(function ($) {
    function cupri_concat_url($url, $key, $value) {
        $key = encodeURIComponent($key);
        $value = encodeURIComponent($value);

        var $newurl = '';

        $url = $url.toString().trim();
        $key = $key.toString().trim();
        $value = $value.toString().trim();

        if ($url.indexOf('?') > -1) {
            // has ?
            $newurl = $url + '&' + $key + '=' + $value;

        } else {
            // no ? sign
            if (!$url.endsWith('/')) {
                $url = $url + '/';
            }
            $newurl = $url + '?' + $key + '=' + $value;

        }

        return $newurl;
    }

    $('#cupri_create_link').on('click', function (e) {
        e.preventDefault();
        $url = $('#cupri_link').val();
        $price = $('#cupri_price').val();
        $cupri_link_result = $('#cupri_link_result');
        $cupri_link_result.val(cupri_concat_url($url, 'cupri_fprice', $price));
    });
    $('._cupri_delete_row').on('click', function (e) {
        e.preventDefault();
        var ths = $(this);
        var post_id = $(this).data('post-id');
        if (confirm('اطمینان دارید؟')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'cupri_delete_post',
                    post_id: post_id,
                    nonce: $(this).data('nonce')
                },
            })
                .done(function (data) {
                    ths.closest('tr').fadeOut('slow');
                    console.log("success");
                })
                .fail(function () {
                    console.log("error");
                })
                .always(function () {
                    console.log("complete");
                });

        }
    });

    $('#cupri_send_test_sms_btn').on('click', function () {
        let cupri_send_test_sms_mobile = $('#cupri_send_test_sms_mobile').val();
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'cupri_send_test_sms',
                mobile: cupri_send_test_sms_mobile,
                nonce: $(this).data('nonce')
            },
        })
            .done(function (data) {
                alert(data);
                console.log("success");
            })
            .fail(function () {
                console.log("error");
            })
            .always(function () {
                console.log("complete");
            });
    });
});