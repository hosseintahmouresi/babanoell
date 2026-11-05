<?php

use mihanpanel\app\notice;
use mihanpanel\pro\app\notifications;

$notifications = notifications::getAllNotifications();
?>
<div class="wrap">
    <h2>
        <?php _e('Targeted Notifications', 'mihanpanel') ?>
        <a href="<?php echo esc_url(add_query_arg(['type' => 'new'], menu_page_url('mihanpanel_notifications', false)))?>" class="page-title-action"><?php _e("New notification", 'mihanpanel')?></a>
    </h2>
    <?php
        if($notice = notice::once_get_notice()): ?>
        <div class="notice notice-<?php echo esc_attr($notice['type']); ?>">
            <p><?php echo esc_html($notice['msg']); ?></p>
        </div>
    <?php endif; ?>
    <table class="wp-list-table widefat fixed striped table-view-list posts">
        <thead>
            <tr>
                <td scope="col" id="title"><?php _e('Title', 'mihanpanel')?></td>
                <td scope="col" id="condition_type"><?php _e('Condition type', 'mihanpanel')?></td>
                <td scope="col" id="status"><?php _e('Status', 'mihanpanel')?></td>
                <td scope="col" id="action"><?php _e('Action', 'mihanpanel')?></td>
            </tr>
        </thead>

        <tbody id="the-list">
            <?php if($notifications): ?>
                <?php foreach($notifications as $item): ?>
                    <tr id="notif-<?php echo esc_attr($item->id)?>">
                        <th><a href="<?php echo esc_url(add_query_arg(['type' => 'edit', 'id' => $item->id]))?>"><?php echo esc_html($item->title); ?></a></th>
                        <td><?php echo esc_html(notifications::getConditionTypeTitle(notifications::getMeta($item->id, 'condition_type'))); ?></td>
                        <td><?php echo esc_html(notifications::getStatusTitle($item->status))?></td>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg(['type' => 'edit', 'id' => $item->id]))?>"><span class="dashicons dashicons-edit"></span></a>
                            <a href="<?php echo esc_url(add_query_arg(['type' => 'delete', 'id' => $item->id]))?>"><span class="dashicons dashicons-trash"></span></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <th colspan="4"><?php _e('Nothing found', 'mihanpanel')?></th>
                </tr>
            <?php endif; ?>
        </tbody>

        <tfoot>
            <tr>
                <td scope="col"><?php _e('Title', 'mihanpanel')?></td>
                <td scope="col"><?php _e('Condition type', 'mihanpanel')?></td>
                <td scope="col"><?php _e('Status', 'mihanpanel')?></td>
                <td scope="col" id="action"><?php _e('Action', 'mihanpanel')?></td>
            </tr>
        </tfoot>

    </table>
</div>