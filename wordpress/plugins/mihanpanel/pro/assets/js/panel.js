jQuery(document).ready(function($){
    $(document).on('change', 'input[name=mw_user_avatar]', function (e) {
        var img = $('#user_avatar_display'),
            alert = $("#avatar_field .alert");
        img.attr('src', URL.createObjectURL(e.target.files[0]));
        setTimeout(() => {
            alert.show();
        }, 500);
    });
});