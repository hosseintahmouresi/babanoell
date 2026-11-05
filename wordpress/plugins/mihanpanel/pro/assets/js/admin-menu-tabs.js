jQuery(document).ready(function($){
    let mediaUploaderBtn = $(".mwpl_action_btn[mwpl_media_uploader]")
    mediaUploaderBtn.each((index, item) => {
        let mediaUploader
        $(item).on('click', function(e){
            let mwpl_this_btn = $(this)
            if(mediaUploader)
            {
                mediaUploader.open()
                return
            }
            mediaUploader = wp.media({
                multiple: false
            })
            mediaUploader.open()
            mediaUploader.on('select', (e) => {
                let attachment = mediaUploader.state().get('selection').first().toJSON()
                mwpl_this_btn.parent().find('.preview_icon').html('<img src="'+attachment.url+'" alt="'+attachment.url+'" />')
                mwpl_this_btn.parent().find('input[name=icon]').val(attachment.url).change()
            })
        })
    });
})