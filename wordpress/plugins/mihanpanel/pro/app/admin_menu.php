<?php

namespace mihanpanel\pro\app;

use ahura\inc\widgets\breadcrumb;
use mihanpanel\app\form\admin_tabs;
use mihanpanel\app\notice;

class admin_menu
{
    static function handle_tabs_menu_content()
    {
        if (!empty($_POST)) {
            admin_tabs::do();
        }
?>
        <div class="mihanpanel-admin">
            <?php if ($notice = \mihanpanel\app\notice::once_get_notice()) : ?>
                <p class="alert <?php echo esc_attr($notice['type']) ?>"><?php echo esc_html($notice['msg']) ?></p>
            <?php endif; ?>
            <div class="mw_update_sortable_notice notice inline notice-info notice-alt">
                <p>
                    <span><?php esc_html_e('Do you want to save changes?', 'mihanpanel') ?></span>
                    <span><input mwpl_nonce="<?php esc_attr_e(wp_create_nonce('mwpl_ajax_update_tabs_fields_data')) ?>" class="mw_submit mw_ajax_update_fields_data" data-mw_type="tabs" type="submit" name="save_priority" value="<?php esc_attr_e("yes", "mihanpanel") ?>"></span>
                </p>
            </div>
            <div class="mw_notice_box notice inline notice-alt"></div>
            <?php admin_tabs::render_fields() ?>

            <div class="new_record_wrapper" style="display: none;">
                <h2><?php esc_html_e('Create new field', 'mihanpanel'); ?><span class="close_new_field_section action_btn"><?php esc_html_e('Close', 'mihanpanel') ?></span></h2>
                <form method="post">
                    <?php admin_tabs::render_new_record_fields() ?>
                </form>
            </div>
        </div>
<?php
    }
    static function handle_admin_tools_menu_submission()
    {
        if (isset($_POST['transfer_digits_phone_numbers'])) {
            $digits_phone_key = 'digits_phone';
            $usersData = get_users([
                'number' => -1,
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => $digits_phone_key,
                        'value' => '',
                        'compare' => '!=',
                    ],
                    [
                        'key' => users::$_user_phone_option,
                        'compare' => 'NOT EXISTS',
                    ]
                ]
            ]);
            $processCount = 0;
            foreach ($usersData as $item) {
                $userDigitsPhone = get_user_meta($item->ID, $digits_phone_key, true);
                if ($userDigitsPhone) {
                    $setPhoneRes = users::set_user_phone($item->ID, $userDigitsPhone);
                    if ($setPhoneRes) {
                        $setHasValidPhone = users::set_has_valid_phone($item->ID);
                        if ($setHasValidPhone) {
                            $processCount++;
                        }
                    }
                }
            }
            $msg = sprintf(__('%s out of %s items were transferred', 'mihanpanel'), $processCount, count($usersData));
            \mihanpanel\app\notice::add_notice('info', $msg);
        }
        if (isset($_POST['mp_login_header_additional_code'])) {
            options::set_login_header_additional_code(sanitize_textarea_field($_POST['mp_login_header_additional_code']));
        }
    }
    static function handleNotificationsMenuContent()
    {
        $whiteList = ['list', 'new', 'edit'];
        $action = isset($_GET['type']) && in_array($_GET['type'], $whiteList) ? sanitize_text_field($_GET['type']) : reset($whiteList);
        $view = views::get('admin.notifications.' . $action);
        if (isset($_POST['submit_new'])) {
            notifications::addNewNotificationHandler();
        }
        if (isset($_POST['submit_edit'])) {
            notifications::editNotificationHandler();
        }
        if (isset($_GET['type']) && $_GET['type'] == 'delete' && isset($_GET['id'])) {
            if (notifications::delete(intval($_GET['id']))) {
                notice::add_notice('success', __('Successfully deleted!', 'mihanpanel'));
            } else {
                notice::add_notice('error', __('Has error in delete notification', 'mihanpanel'));
            }
            wp_safe_redirect(menu_page_url('mihanpanel_notifications', false));
            exit;
        }
        // do_action('mihanpanel/pro/notifications/before_render_view', $action);
        $view ? include_once $view : null;
    }
}
