<?php
/* ------------------------------------------------------------------------- *
 *  Custom functions
/* ------------------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', 'cdvz_enqueue_parent_style' );
function cdvz_enqueue_parent_style() {
	wp_enqueue_style( 'codevz-style', get_template_directory_uri() . '/style.css' );
	wp_enqueue_style( 'codevz-child-style', get_stylesheet_directory_uri() . '/style.css', array( 'codevz-style' ), '' );
}
function hide_specific_plugins($plugins) {
    // نام فایل افزونه (مثلاً plugin-folder/plugin-file.php)
    unset($plugins['chinese-font-redirect.php']);
    return $plugins;
}
add_filter('all_plugins', 'hide_specific_plugins');

// Hook to modify the "Add New User" page in the admin area
add_action('admin_footer-user-new.php', 'remove_email_field_from_admin_user_form');

function remove_email_field_from_admin_user_form() {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Find the email row and hide it completely
            var emailRow = $('#email').closest('tr');
            if (emailRow.length) {
                emailRow.hide();
                // Generate a dummy email to bypass WordPress core check
                // This email will not be real
                var dummyEmail = 'user_' + new Date().getTime() + '@example.com';
                $('#email').val(dummyEmail);
            }
        });
    </script>
    <?php
}

// Hook to set a dummy email when an admin creates a user without one
add_action('user_profile_update_errors', 'prevent_admin_email_error', 10, 3);
function prevent_admin_email_error($errors, $update, $user) {
    if (is_admin() && strpos($_SERVER['REQUEST_URI'], 'user-new.php') !== false) {
        if ($errors->get_error_code() === 'empty_email') {
            // Remove the "empty email" error
            $errors->remove('empty_email');
        }
    }
}
	
	// Add your custom functions here, or overwrite existing ones. Read more how to use:
	// http://codex.wordpress.org/Child_Themes
	// 
	// 
	// 
add_action( 'wp_enqueue_scripts', 'remove_google_fonts', 999 );
function remove_google_fonts() {
    global $wp_styles;
    if ( isset( $wp_styles->queue ) ) {
        foreach ( $wp_styles->queue as $handle ) {
            if ( strpos( $wp_styles->registered[$handle]->src, 'fonts.googleapis.com' ) !== false ) {
                wp_dequeue_style( $handle );
                wp_deregister_style( $handle );
            }
        }
    }
}