<?php
namespace mihanpanel\pro\app;

use mihanpanel\app\notice;

class notifications
{
    private static $mainTableName = 'mihanpanel_notifications';
    private static $metaTableName = 'mihanpanel_notificationmeta';

    const STATUS_ACTIVE = 1;
    const STATUS_INACTIVE = 2;

    private const TYPE_SUCCESS = 1;
    private const TYPE_INFO = 2;
    private const TYPE_WARNING = 3;
    private const TYPE_ERROR = 4;
    
    private const CONDITION_TYPE_SHOW_TO_ALL = 0;
    private const CONDITION_TYPE_USERNAME = 1;
    private const CONDITION_TYPE_USER_ROLE = 2;


    private static function get_db(&$mainTableName=null, &$metaTableName=null)
    {
        global $wpdb;
        $mainTableName = $wpdb->prefix . self::$mainTableName;
        $metaTableName = $wpdb->prefix . self::$metaTableName;
        return $wpdb;
    }
    static function createNotificationsTable()
    {
        // create table
        $db = self::get_db($table_name);
        $charset_collate = $db->get_charset_collate();
        $command = "CREATE TABLE IF NOT EXISTS {$table_name} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(250) NOT NULL,
            `text` longtext NOT NULL,
            `status` tinyint(4) NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status)
          ) ENGINE=InnoDB {$charset_collate};";
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta($command);
        
    }
    static function createNotificationMetaTable()
    {
        // create table
        $db = self::get_db($mainTableName, $metaTableName);
        $charset_collate = $db->get_charset_collate();
        $command = "CREATE TABLE IF NOT EXISTS {$metaTableName} (
            `meta_id` int(11) NOT NULL AUTO_INCREMENT,
            `notif_id` int(11) NOT NULL,
            `meta_key` varchar(255) NOT NULL,
            `meta_value` longtext DEFAULT NULL,
            PRIMARY KEY  (meta_id),
            KEY notif_id (notif_id),
            KEY meta_key (meta_key)
          ) ENGINE=InnoDB {$charset_collate};";
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta($command);
    }
    static function getAllNotifications()
    {
        $connection = self::get_db($mainTableName, $metaTableName);
        return $connection->get_results("SELECT * FROM {$mainTableName}");
    }
    static function getNotification($id)
    {
        $connection = self::get_db($mainTableName, $metaTableName);
        $sql = "SELECT * FROM {$mainTableName} WHERE id=%d";
        return $connection->get_row($connection->prepare($sql, $id));
    }
    static function addNew($title, $text, $status, $args=[])
    {
        if(!$title || !$text)
        {
            return false;
        }
        $connection = self::get_db($mainTableName);
        $res = $connection->insert(
            $mainTableName,
            [
                'title' => $title,
                'text' => $text,
                'status' => $status ? self::STATUS_ACTIVE : self::STATUS_INACTIVE,
            ]
        );
        if(!$res)
        {
            return false;
        }
        if($args)
        {
            $newID = $connection->insert_id;
            foreach($args as $key => $value)
            {
                self::addMeta($newID, $key, $value);
            }
        }
        return true;
    }
    static function edit($id, $title, $text, $status)
    {
        if(!$id || !$title || !$text)
        {
            return false;
        }
        $connection = self::get_db($mainTableName, $metaTableName);
        $updateRes = $connection->update(
            $mainTableName,
            [
                'title' => $title,
                'text' => $text,
                'status' => $status ? self::STATUS_ACTIVE : self::STATUS_INACTIVE,
            ],
            [
                'id' => $id,
            ]
        );
        return $updateRes || !$connection->last_error;
    }
    static function delete($id)
    {
        if(!$id)
        {
            return false;
        }
        $connection = self::get_db($mainTableName, $metaTableName);
        // delete main item
        $mainRes = $connection->delete(
            $mainTableName,
            [
                'id' => $id,
            ]
        );
        if(!$mainRes)
        {
            return false;
        }
        // delete items meta
        return $connection->delete(
            $metaTableName,
            [
                'notif_id' => $id,
            ]
        );
    }
    static function addMeta($notifID, $key, $value)
    {
        if(!$notifID || !$key)
        {
            return false;
        }
        $value = maybe_serialize($value);

        $connection = self::get_db($mainTableName, $metaTableName);
        return $connection->insert(
            $metaTableName,
            [
                'notif_id' => $notifID,
                'meta_key' => $key,
                'meta_value' => $value,
            ],
        );
    }
    static function updateMeta($notifID, $key, $value)
    {
        if(!$notifID || !$key)
        {
            return false;
        }

        $connection = self::get_db($mainTableName, $metaTableName);
        // get last item id
        $sql = "SELECT meta_id
                FROM {$metaTableName}
                WHERE
                    notif_id=%d
                    AND meta_key=%s
                ";
        $lastItem = $connection->get_var($connection->prepare($sql, $notifID, $key));
        if($lastItem)
        {
            // update
            return $connection->update(
                $metaTableName,
                [
                    'meta_value' => maybe_serialize($value),
                ],
                [
                    'notif_id' => $notifID,
                    'meta_id' => $lastItem,
                ]
            );
        }else{
            // add new
            return self::addMeta($notifID, $key, $value);
        }
    }
    static function getMeta($notifID, $key)
    {
        if(!$notifID || !$key)
        {
            return false;
        }
        $connection = self::get_db($mainTableName, $metaTableName);
        $value = $connection->get_var($connection->prepare("SELECT meta_value FROM {$metaTableName} WHERE notif_id=%d AND meta_key=%s", $notifID, $key));
        return maybe_unserialize($value);
    }
    static function deleteMeta($notifID, $key)
    {
        if(!$notifID || !$key)
        {
            return false;
        }
        $connection = self::get_db($mainTableName, $metaTableName);
        return $connection->delete(
            $metaTableName,
            [
                'notif_id' => $notifID,
                'meta_key' => $key
            ]
        );
    }
    static function getTypesList()
    {
        return [
            self::TYPE_SUCCESS => [
                'name' => 'success',
                'title' => __('Success', 'mihanpanel')
            ],
            self::TYPE_INFO => [
                'name' => 'info',
                'title' => __('Info', 'mihanpanel')
            ],
            self::TYPE_WARNING => [
                'name' => 'warning',
                'title' => __('Warning', 'mihanpanel')
            ],
            self::TYPE_ERROR => [
                'name' => 'error',
                'title' => __('Error', 'mihanpanel')
            ],
        ];
    }
    static function getTypeName($code)
    {
        $all = self::getTypesList();
        return isset($all[$code]) ? $all[$code]['name'] : false;
    }
    static function last_getTypesList()
    {
        return [
            self::TYPE_SUCCESS => __('Success', 'mihanpanel'),
            self::TYPE_INFO => __('Info', 'mihanpanel'),
            self::TYPE_WARNING => __('Warning', 'mihanpanel'),
            self::TYPE_ERROR => __('Error', 'mihanpanel'),
        ];
    }
    static function getConditionTypesList()
    {
        return [
            self::CONDITION_TYPE_SHOW_TO_ALL => __('Show to all', 'mihanpanel'),
            self::CONDITION_TYPE_USERNAME => __('Username', 'mihanpanel'),
            self::CONDITION_TYPE_USER_ROLE => __('Role', 'mihanpanel'),
        ];
    }
    static function getConditionTypeTitle($id)
    {
        $all = self::getConditionTypesList();
        return isset($all[$id]) ? $all[$id] : reset($all);
    }
    static function getStatusTitle($status)
    {
        return $status == self::STATUS_ACTIVE ? __('Active', 'mihanpanel') : __('Inactive', 'mihanpanel');
    }
    static function addNewNotificationHandler()
    {
        $typesWhiteList = array_keys(self::getTypesList());
        $conditionTypeWhiteList = array_keys(self::getConditionTypesList());
        $args = [];

        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : false;
        $status = isset($_POST['status']);
        $args['type'] = isset($_POST['type']) && in_array($_POST['type'], $typesWhiteList) ? intval($_POST['type']) : reset($typesWhiteList);
        $args['condition_type'] = isset($_POST['condition_type']) && in_array($_POST['condition_type'], $conditionTypeWhiteList) ? intval($_POST['condition_type']) : reset($conditionTypeWhiteList);
        if($args['condition_type'] == self::CONDITION_TYPE_USERNAME)
        {
            $username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : false;
            if($username && username_exists($username))
            {
                $user = get_user_by('login', $username);
                $args['user_id'] = $user->ID;
            }
        }elseif($args['condition_type'] == self::CONDITION_TYPE_USER_ROLE)
        {
            $args['roles'] = isset($_POST['roles']) && is_array($_POST['roles']) ? \mihanpanel\app\tools::sanitize_array_values($_POST['roles']) : false;
        }
        $content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : false;

        $result = self::addNew($title, $content, $status, $args);
        if(!$result)
        {
            notice::add_notice('error', __('Has error in save notification data', 'mihanpanel'));
            return false;
        }
        notice::add_notice('success', __('Successfully save notification data', 'mihanpanel'));
        return true;
    }
    static function editNotificationHandler()
    {
        $typesWhiteList = array_keys(self::getTypesList());
        $conditionTypeWhiteList = array_keys(self::getConditionTypesList());

        $id = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : false;
        if(!$id)
        {
            notice::add_notice('error', __('Invalid notification', 'mihanpanel'));
            return false;
        }

        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : false;
        $content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : false;
        $status = isset($_POST['status']);
        
        $type = isset($_POST['type']) && in_array($_POST['type'], $typesWhiteList) ? intval($_POST['type']) : reset($typesWhiteList);
        $condition_type = isset($_POST['condition_type']) && in_array($_POST['condition_type'], $conditionTypeWhiteList) ? intval($_POST['condition_type']) : reset($conditionTypeWhiteList);
        $username = $condition_type == self::CONDITION_TYPE_USERNAME && isset($_POST['username']) ? sanitize_text_field($_POST['username']) : false;
        $roles = $condition_type == self::CONDITION_TYPE_USER_ROLE && isset($_POST['roles']) && is_array($_POST['roles']) ? \mihanpanel\app\tools::sanitize_array_values(array_keys($_POST['roles'])) : false;

        $result = self::edit($id, $title, $content, $status);
        if(!$result)
        {
            notice::add_notice('error', __('Has error in edit notification', 'mihanpanel'));
            return false;
        }

        self::updateMeta($id, 'type', $type);
        self::updateMeta($id, 'condition_type', $condition_type);
        if($username)
        {
            $user = get_user_by('login', $username);
            self::updateMeta($id, 'user_id', $user->ID);
            self::deleteMeta($id, 'roles');
        }elseif($roles)
        {
            self::updateMeta($id, 'roles', $roles);
            self::deleteMeta($id, 'user_id');
        }
        notice::add_notice('success', __('Successfully save notification data', 'mihanpanel'));
        return true;
    }
    static function getUserNotifications($userID)
    {
        if(!$userID)
        {
            return false;
        }
        $userData = get_user_by('id', $userID);
        if(!$userData)
        {
            return false;
        }
        $userRole = $userData->roles ? reset($userData->roles) : false;
        if(!$userRole)
        {
            return false;
        }

        $connection = self::get_db($mainTableName, $metaTableName);
        // [x] active
        // [x] get show to all notifications
        // [x] get username notifiations
        // [x] get user roles notifications
        $sql = "SELECT main.id, main.title, main.text
                -- SELECT *
                FROM {$metaTableName} as meta
                JOIN {$mainTableName} as main
                ON meta.notif_id=main.id
                WHERE
                    main.status=%d
                    AND (
                        ( meta_key=%s AND meta_value=%d )
                        OR ( meta_key=%s AND meta_value=%s )
                        OR ( meta_key=%s AND meta_value LIKE %s)
                    )
                ";
        $prepareData = [
            self::STATUS_ACTIVE,
            'condition_type',
            self::CONDITION_TYPE_SHOW_TO_ALL,
            'user_id',
            $userData->ID,
            'roles',
            '%' . $userRole . '%',
        ];
        return $connection->get_results($connection->prepare($sql, $prepareData));
    }
    static function renderNotificationInPanel()
    {
        $notifications = self::getUserNotifications(get_current_user_id());
        ?>
        <?php if($notifications): ?>
            <?php foreach($notifications as $item):
                $type = self::getTypeName(self::getMeta($item->id, 'type'));
                ?>
                <div class="notification-box <?php echo esc_attr($type)?>"><?php echo esc_html($item->text)?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php
    }
}