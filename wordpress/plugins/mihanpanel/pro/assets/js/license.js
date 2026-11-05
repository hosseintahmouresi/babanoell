jQuery(document).ready(function($){
    let license_field = $('#license-key');
    function in_loading(active)
    {
        let license_loading = $('.loading')
        if(active == true)
        {
            license_field.attr('disabled', true);
            license_loading.removeClass('display_none');
        }else{
            license_field.attr('disabled', false);
            license_loading.addClass('display_none')
        }
    }
    function show_notice(msg, type)
    {
        let notice = $('.mwpl_notice');
        type = type == undefined ? 'error' : type;
        notice.attr('notice-type', type);
        notice.text(msg);
    }
    $('.process_license').on('click', function(e){
        e.preventDefault();
        let mwpl_this = $(this),
            process_type = mwpl_this.attr('process-type')
        in_loading(true);
        let other_process_type = process_type == 'activate' ? 'deactivate' : 'activate',
            other_btn = mwpl_this.parent().find(`[process-type=${other_process_type}]`);

        $.ajax({
            url: mwpl_data.au,
            type: 'post',
            dataType: 'json',
            data: {
                action: 'mwpl_process_license',
                mwpl_nonce: mwpl_data.security,
                process_type: process_type,
                license_key: license_field.val()
            },
            success: response => {
                let type = 'error';
                if(response.status == 200)
                {
                    type = 'success';
                    let statusBarClass = 'deactive';
                    if(process_type == 'activate')
                    {
                        statusBarClass = 'active';
                    }
                    let statusBar = $('.license-box .status-bar');
                    statusBar.text(response.status_bar);
                    statusBar.removeClass('active deactive');
                    statusBar.addClass(statusBarClass)

                    other_btn.removeClass('display_none');
                    mwpl_this.addClass('display_none');
                }
                show_notice(response.msg, type);
                in_loading();
            },
            error: (xhr, status, error) => {
                show_notice(error)
                in_loading();
            }
        });
    })
});