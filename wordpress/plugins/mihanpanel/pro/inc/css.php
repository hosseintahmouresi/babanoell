<?php
$login_body_bg_type = \mihanpanel\pro\app\options::get_login_page_background_option('type', 'simple');
$login_body_bg_color = \mihanpanel\pro\app\options::get_login_page_background_option('color', '#f0f0f0');
$login_body_bg_color2 = \mihanpanel\pro\app\options::get_login_page_background_option('color2', '#f0f0f0');
$login_body_bg_direction = \mihanpanel\pro\app\options::get_login_page_background_option('direction', '0');
$login_body_bg_image = \mihanpanel\pro\app\options::get_login_page_background_option();
$login_body_bg_repeat = \mihanpanel\pro\app\options::get_login_page_background_option('repeat', 'no-repeat');
$login_body_bg_size = \mihanpanel\pro\app\options::get_login_page_background_option('size', 'cover');
$login_body_bg_position = \mihanpanel\pro\app\options::get_login_page_background_option('position', 'center center');

$cover_bg_type = \mihanpanel\pro\app\options::get_login_form_cover_background_option('type', 'image');
$cover_bg_color = \mihanpanel\pro\app\options::get_login_form_cover_background_option('color', '#4f00d7');
$cover_bg_color2 = \mihanpanel\pro\app\options::get_login_form_cover_background_option('color2', '#4f00d7');
$cover_bg_direction = \mihanpanel\pro\app\options::get_login_form_cover_background_option('direction', '0');
$cover_bg_image = \mihanpanel\pro\app\options::get_login_form_cover_background_option();
$cover_bg_repeat = \mihanpanel\pro\app\options::get_login_form_cover_background_option('repeat', 'no-repeat');
$cover_bg_size = \mihanpanel\pro\app\options::get_login_form_cover_background_option('size', 'cover');
$cover_bg_position = \mihanpanel\pro\app\options::get_login_form_cover_background_option('position', 'center center');

$login_form_bg_type = \mihanpanel\pro\app\options::get_login_form_box_background_option('type', 'simple');
$login_form_bg_color = \mihanpanel\pro\app\options::get_login_form_box_background_option('color', '#fff');
$login_form_bg_color2 = \mihanpanel\pro\app\options::get_login_form_box_background_option('color2', '#fff');
$login_form_bg_direction = \mihanpanel\pro\app\options::get_login_form_box_background_option('direction', '0');
$login_form_bg_image = \mihanpanel\pro\app\options::get_login_form_box_background_option();
$login_form_bg_repeat = \mihanpanel\pro\app\options::get_login_form_box_background_option('repeat', 'no-repeat');
$login_form_bg_size = \mihanpanel\pro\app\options::get_login_form_box_background_option('size', 'cover');
$login_form_bg_position = \mihanpanel\pro\app\options::get_login_form_box_background_option('position', 'center center');
?>
<style>
    /* Mihan Panel Custom Css */
    .mwpl-login-body {
        <?php if ($login_body_bg_type === 'gradient'): ?>
            background: <?php echo $login_body_bg_color ?>;
            background: linear-gradient(<?php echo $login_body_bg_direction ?>deg, <?php echo $login_body_bg_color ?> 0%, <?php echo $login_body_bg_color2 ?> 100%);
        <?php elseif ($login_body_bg_type === 'image'): ?>
            background-color: <?php echo $login_body_bg_color ?>;
            <?php if (!empty($login_body_bg_image)): ?>
            background-image: url("<?php echo $login_body_bg_image ?>");
            <?php endif; ?>
            background-position: <?php echo $login_body_bg_position ?>;
            background-repeat: <?php echo $login_body_bg_repeat ?>;
            background-size: <?php echo $login_body_bg_size ?>;
        <?php else: ?>
            background-color: <?php echo $login_body_bg_color ?>;
        <?php endif; ?>
    }
    .mwpl-login-body .mwpl-form-wrapper {
        <?php if ($login_form_bg_type === 'gradient'): ?>
            background: <?php echo $login_form_bg_color ?>;
            background: linear-gradient(<?php echo $login_form_bg_direction ?>deg, <?php echo $login_form_bg_color ?> 0%, <?php echo $login_form_bg_color2 ?> 100%);
        <?php elseif ($login_form_bg_type === 'image'): ?>
            background-color: <?php echo $login_form_bg_color ?>;
            <?php if (!empty($login_form_bg_image)): ?>
            background-image: url("<?php echo $login_form_bg_image ?>");
            <?php endif; ?>
            background-position: <?php echo $login_form_bg_position ?>;
            background-repeat: <?php echo $login_form_bg_repeat ?>;
            background-size: <?php echo $login_form_bg_size ?>;
        <?php else: ?>
            background-color: <?php echo $login_form_bg_color ?>;
        <?php endif; ?>
    }
    .mwpl-login-body .mwpl-image-cover-wrapper {
        <?php if ($cover_bg_type === 'gradient'): ?>
            background: <?php echo $cover_bg_color ?>;
            background: linear-gradient(<?php echo $cover_bg_direction ?>deg, <?php echo $cover_bg_color ?> 0%, <?php echo $cover_bg_color2 ?> 100%);
        <?php elseif ($cover_bg_type === 'image'): ?>
            background-color: <?php echo $cover_bg_color ?>;
            <?php if (!empty($cover_bg_image)): ?>
            background-image: url("<?php echo $cover_bg_image ?>");
            <?php endif; ?>
            background-position: <?php echo $cover_bg_position ?>;
            background-repeat: <?php echo $cover_bg_repeat ?>;
            background-size: <?php echo $cover_bg_size ?>;
        <?php else: ?>
            background-color: <?php echo $cover_bg_color ?>;
        <?php endif; ?>
    }
</style>
