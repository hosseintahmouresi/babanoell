jQuery(document).ready(function($){
    // change form enctype
    $('.mw_file_uploader input[type=file]').on('change', function(e){
        let mw_this = $(this),
            img_wrapper = mw_this.parent(),
            file_data = e.target.files[0]
            mw_this.closest('form').attr('enctype', 'multipart/form-data')
            // check file type
            let types = {
                'image/jpeg': 'dashicons-format-image',
                'image/png': 'dashicons-format-image',

                'video/x-msvideo': 'dashicons dashicons-video-alt2',
                'video/mp4': 'dashicons dashicons-video-alt2',
                'video/quicktime': 'dashicons dashicons-video-alt2',
                'video/x-ms-wmv': 'dashicons dashicons-video-alt2',
                'video/x-ms-asf': 'dashicons dashicons-video-alt2',
                'video/mpeg': 'dashicons dashicons-video-alt2',
                'video/ogg': 'dashicons dashicons-video-alt2',
                'video/webm': 'dashicons dashicons-video-alt2',
                'video/3gpp': 'dashicons dashicons-video-alt2',

                'audio/mpeg': 'dashicons-format-audio',
                'audio/mp3': 'dashicons-format-audio',
                'audio/ogg': 'dashicons-format-audio',
                'audio/mpeg': 'dashicons-format-audio',
            }
            let icon = types.hasOwnProperty(file_data.type) ? types[file_data.type] : 'dashicons-format-aside';
            icon = 'dashicons ' + icon
            img_wrapper.find('.type-icon > i').attr('class', icon)
            img_wrapper.find('.remove').addClass('has-file');
            img_wrapper.find('input[name="remove_file_field_value[]"]').val('')
            // check file size
    })
    $('.mw_file_uploader .remove').on('click', function(e){
        let wrapper = $(this).parent(),
            field_slug = wrapper.attr('mw_field_slug')
        // clear file field value
        wrapper.find('input[type=file]').val('');
        wrapper.find('input.remove_file_field_value').val(field_slug);
        wrapper.find('.remove').removeClass('has-file')
        wrapper.find('.show-file').remove();
        wrapper.find('.type-icon > i').attr('class', '')
    })
});