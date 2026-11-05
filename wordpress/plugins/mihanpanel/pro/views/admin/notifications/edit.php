<?php

use mihanpanel\app\notice;
use mihanpanel\pro\app\notifications;
$id = isset($_GET['id']) && $_GET['id'] ? intval($_GET['id']) : false;
if(!$id)
{
    wp_safe_redirect(menu_page_url('mihanpanel_notifications', false));
    exit;
}
$allRoles = wp_roles()->get_names();
$typesList = notifications::getTypesList();
$conditionTypes = notifications::getConditionTypesList();
$notification = notifications::getNotification($id);
if(!$notification)
{
    notice::add_notice('error', __('Notification ID is invalid', 'mihanpanel'));
    wp_safe_redirect(menu_page_url('mihanpanel_notifications', false));
    exit;
}
?>
<div class="wrap">
    <h2><?php _e('Edit notification', 'mihanpanel')?></h2>
    <?php if($notice = notice::once_get_notice()): ?>
        <div class="notice notice-<?php echo esc_attr($notice['type']); ?>">
            <p><?php echo esc_html($notice['msg']); ?></p>
        </div>
    <?php endif; ?>
    <form method="post">
        <table class="form-table">
            <tbody>
                <input type="hidden" name="notification_id" value="<?php echo esc_attr($notification->id)?>">
                <tr>
                    <th>
                        <label for="title"><?php _e('Notification title', 'mihanpanel')?></label>
                    </th>
                    <td>
                        <input type="text" id="title" name="title" class="regular-text" value="<?php echo esc_attr($notification->title)?>">
                    </td>
                </tr>

                <tr>
                    <th>
                        <?php _e('Status', 'mihanpanel')?>
                    </th>
                    <td>
                        <label for="status">
                            <input <?php checked(notifications::STATUS_ACTIVE, $notification->status)?> type="checkbox" name="status" id="status">
                            <?php _e('Show in panel', 'mihanpanel')?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th>
                        <label for="type"><?php _e('Type', 'mihanpanel')?></label>
                    </th>
                    <td>
                        <select class="regular-text" name="type" id="type">
                            <?php foreach($typesList as $key => $value): ?>
                            <option <?php selected($key, notifications::getMeta($notification->id, 'type'))?> value="<?php echo esc_attr($key); ?>"><?php echo esc_html($value['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th>
                        <label for="condition_type"><?php _e('Condition type', 'mihanpanel')?></label>
                    </th>
                    <td>
                        <select class="regular-text" name="condition_type" id="condition_type">
                            <?php foreach($conditionTypes as $key => $value): ?>
                                <option <?php selected($key, notifications::getMeta($notification->id, 'condition_type'))?> value="<?php echo esc_attr($key)?>"><?php echo esc_html($value)?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Choose who can see this notification', 'mihanpanel')?></p>
                    </td>
                </tr>

                <tr handle_render style="display: none;">
                    <th>
                        <label for="username"><?php _e('Username', 'mihanpanel')?></label>
                    </th>
                    <td>
                        <?php
                        $userID = notifications::getMeta($notification->id, 'user_id');
                        $userData = get_user_by('id', $userID);
                        ?>
                        <input type="text" name="username" id="username" class="regular-text" value="<?php echo esc_attr($userData->user_login)?>">
                    </td>
                </tr>

                <tr handle_render style="display: none;">
                    <th>
                        <label for="roles"><?php _e('Roles', 'mihanpanel')?></label>
                    </th>
                    <td>
                        <?php foreach($allRoles as $roleKey => $roleName):
                            $rolesValue = notifications::getMeta($notification->id, 'roles');
                            if(!$rolesValue)
                            {
                                $rolesValue = [];
                            }
                            ?>
                            <p>
                                <label for="<?php echo esc_attr($roleKey)?>">
                                    <input <?php checked(in_array($roleKey, $rolesValue))?> type="checkbox" name="roles[<?php echo esc_attr($roleKey)?>]" id="<?php echo esc_attr($roleKey)?>" value="<?php echo esc_attr($roleKey)?>">
                                    <span><?php echo esc_html(translate_user_role($roleName)); ?></span>
                                </label>
                            </p>
                        <?php endforeach; ?>
                    </td>
                </tr>
                
                <tr>
                    <th>
                        <label for="content"><?php _e('Notification content', 'mihanpanel')?></label>
                    </th>
                    <td>
                        <textarea name="content" id="content" cols="30" rows="10" class="regular-text"><?php echo esc_textarea($notification->text)?></textarea>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php submit_button(__('Save notification', 'mihanpanel'), 'primary', 'submit_edit')?>
    </form>
</div>