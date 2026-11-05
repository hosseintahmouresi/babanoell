<?php
namespace mihanpanel\pro\app;
class sundry
{
    static function handle_profile_files_fields_update($user_id)
    {
        $files_data = isset($_FILES['mw_fields_files']) ? $_FILES['mw_fields_files'] : false;
        // remove files fields value
        if(isset($_POST['remove_file_field_value']))
        {
            $remove_items = \mihanpanel\app\tools::sanitize_array_values($_POST['remove_file_field_value']);
            $remove_items = is_array($remove_items) ? array_filter($remove_items) : false;
            foreach($remove_items as $item_slug)
            {
                file_uploader::remove_file($item_slug, $user_id);
            }
        }
        // check files fields
        file_uploader::upload_field_handle($files_data, $user_id);
    }
    static function handle_register_form_extra_fields_error($methods)
    {
        $methods['file_uploader'] = [__CLASS__, 'handle_register_form_file_uploader_field_error'];
        return $methods;
    }
    static function handle_register_form_file_uploader_field_error($field, $errors)
    {
        if($field->type == 'file_uploader')
        {
            // check is any file send
            if(!isset($_FILES['mw_fields_files']))
            {
                $meta = $field->meta;
                $meta = $meta ? unserialize($meta) : false;
                $just_in_profile = isset($meta['data']['just_in_profile']);
                if($field->required == 'yes' && !$just_in_profile)
                {
                    $msg = sprintf(esc_html__('%1$s must not be empty.', 'mihanpanel'), $field->label);
                    $errors->add($field->slug . '_error', $msg);
                }
                return false;
            }
            // check for error
            if($error = $_FILES['mw_fields_files']['error'][$field->slug])
            {
                // check is empty and is required
                if($error == 4 && $field->required == 'no')
                {
                    return false;
                }
                $error_txt = file_uploader::get_error_text($error);
                $error_txt = sprintf('%1$s: %2$s', $field->label, $error_txt);
                $errors->add($field->slug . '_error', $error_txt);
                return false;
            }
            // validate file type
            $valdiate_file_type = file_uploader::validate_file_type($field, $_FILES['mw_fields_files']['type'][$field->slug]);
            if(!$valdiate_file_type)
            {
                $msg = sprintf(esc_html__('%1$s: Invalid file type', 'mihanpanel'), $field->label);
                $errors->add($field->slug . '_error', $msg);
                return false;
            }
            // validate file size
            $validate_file_size = file_uploader::validate_file_size($field, $_FILES['mw_fields_files']['size'][$field->slug]);
            if(!$validate_file_size)
            {
                $msg = sprintf(esc_html__('%1$s: Invalid file size', 'mihanpanel'), $field->label);
                $errors->add($field->slug . '_error', $msg);
                return false;
            }
        }
        return true;
        
    }
    static function handle_register_form_extra_fields_save_methods($methods)
    {
        $methods['file_uploader'] = [__CLASS__, 'handle_register_form_file_uploader_field_save_process'];
        return $methods;
    }
    static function handle_register_form_file_uploader_field_save_process($user_id, $field)
    {
        $files_data = isset($_FILES['mw_fields_files']) ? $_FILES['mw_fields_files'] : '';
        if($field->type == 'file_uploader' && $files_data)
        {
            file_uploader::upload_file($files_data['name'][$field->slug], $files_data['tmp_name'][$field->slug], $field->slug, $user_id);
        }
    }
    static function renderRecentProductsInPanel()
    {
        if(!options::is_wc_recent_products_section_enable())
        {
            return false;
        }
        $view = views::get('user.panel.wc_recent_products');
        if(!$view)
        {
            return false;
        }
        $args = [
            'orderby' => 'date',
            'order' => "DESC",
            'status' => 'publish',
            'stock_status' => 'instock',
        ];
        $sectionTitle = options::get_panel_wc_recent_products_section_title();
        if(!$sectionTitle)
        {
            $sectionTitle = __('Recent products', 'mihanpanel');
        }
        $sectionLink = options::get_panel_wc_recent_products_section_link();
        $count = options::get_panel_wc_recent_products_section_items_count();
        $categories = options::get_panel_wc_recent_products_section_items_categories();

        if($count)
        {
            $args['limit'] = $count;
        }
        $categoriesSlug = [];
        foreach($categories as $catItemID)
        {
            $categoriesSlug[] = get_term_by('id', $catItemID, 'product_cat')->slug;
        }
        if($categoriesSlug)
        {
            $args['category'] = $categoriesSlug;
        }
        
        $prList = wc_get_products($args);
        include_once $view;
    }
    static function handle_dashboard_news()
    {
        if(!options::is_blog_section_enable())
        {
            return false;
        }
        $title = options::get_panel_blog_section_title();
        $title_link = options::get_panel_blog_section_title_link();
        $category_id = options::get_panel_blog_section_posts_category();
        $posts_per_page = options::get_panel_blog_section_posts_count();
        $args = [
            'posts_per_page' => $posts_per_page,
            'cat' => $category_id,
        ];
        $query = new \WP_Query($args);
        wp_reset_postdata();
        ?>
        <div class="news-box">
            <h3 class="dashboard-title <?php echo $title_link ? 'cursor' : '';?>">
                <?php if($title_link): ?>
                    <a href="<?php echo esc_url($title_link)?>"><?php echo $title?></a>
                <?php else: echo $title;?>
                <?php endif; ?>
            </h3>
            <div class="post-items-wrapper">
                <?php if($query->have_posts()): ?>
                    <?php while($query->have_posts()): $query->the_post()?>
                    <article class="post-item">
                        <a href="<?php echo get_the_permalink()?>">
                            <div class="thumbnail">
                                <?php echo get_the_post_thumbnail(get_the_ID(), 'medium')?>
                            </div>
                            <header>
                                <h3><?php the_title()?></h3>
                            </header>
                        </a>
                    </article>
                    <?php endwhile;?>
                <?php endif;?>
            </div>
        </div>
        <?php
    }
    static function render_phone_field_in_admin_edit_profile($user)
    {
        $user_can_edit = current_user_can('edit_users');
        $user_phone = users::get_user_phone($user->ID);
        $action_title = '';
        
        $is_active = users::has_valid_phone($user->ID);
        if($is_active)
        {
            $bg_color= '#5ed85e';
            $color= '#165616';
        }else{
            $bg_color= '#d86363';
            $color= '#710b0b';
        }
        $title = $is_active ? esc_html__('Active', 'mihanpanel') : esc_html__('Inactive', 'mihanpanel');
        $style = 'background-color: '.$bg_color.'; color: '.$color.'; padding: 1px 10px; border-radius: 3px; font-weight: bold;';
		$status = '<span style="'.$style.'">'.$title.'</span>';
        
        if($user_can_edit)
        {
            $action_title = $is_active ? esc_html__('Deactivate', 'mihanpanel') : esc_html__('Activate', 'mihanpanel');
        }
        ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th><label for="mw_user_phone_number"><?php esc_html_e("Phone Number", "mihanpanel"); ?></label></th>
                    <td>
                        <input <?php echo !$user_can_edit && !options::user_can_change_phone() ? 'disabled' : ''; ?> value="<?php echo $user_phone ? esc_attr($user_phone) : false;?>" id="mw_user_phone_number" class="regular-text" name="mw_user_phone_number" type="text" <?php echo $is_active ? ' readonly' : '';?>>
                        <small><?php echo $status;?></small>
                        <?php if($user_can_edit): ?>
                            <button class="button mwp-change-phone-status" type="button" data-uid="<?php echo esc_attr($user->ID) ?>" data-status="<?php echo $is_active ? 'deactivate' : 'activate'; ?>" name="mw_change_phone_status"><?php echo esc_html($action_title);?></button>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }
    static function handle_phone_input_in_admin_edit_profile($uid)
    {
        if(!current_user_can('edit_users') && !options::user_can_change_phone())
        {
            return false;
        }
        $phone = isset($_POST['mw_user_phone_number']) && $_POST['mw_user_phone_number'] ? tools::getValidatedPhoneNumber(sanitize_text_field($_POST['mw_user_phone_number'])) : false;
        if(!$phone)
        {
            users::remove_user_phone($uid);
            users::remove_has_valid_phone($uid);
            return;
        }
        // check is phone used by another user
        if(!users::can_use_this_phone($phone, $uid))
        {
            return false;
        }
        users::set_user_phone($uid, $phone);

        if(current_user_can('edit_users'))
        {
            $action = isset($_POST['mw_change_phone_status']) ? sanitize_text_field($_POST['mw_change_phone_status']) : false;
            $is_active = users::has_valid_phone($uid);
            switch($action)
            {
                case 'activate':
                    if(!$is_active)
                    {
                        users::set_has_valid_phone($uid);
                    }
                break;
                case 'deactivate':
                    if($is_active)
                    {
                        users::remove_has_valid_phone($uid);
                    }
                break;
            }
        }
    }
    static function handle_edit_phone_number_error($error, $update, $user)
    {
        $phone = isset($_POST['mw_user_phone_number']) && $_POST['mw_user_phone_number'] ? tools::getValidatedPhoneNumber(sanitize_text_field($_POST['mw_user_phone_number'])) : false;
        if($phone && !users::can_use_this_phone($phone, $user->ID))
        {
            $error->add('mw_phone_number_exists', esc_html__("This phone number is used by another user", "mihanpanel"));                
        }
    }
    static function addMihanpanelMenuToNavMenu($items, $args)
    {
        $selectedNavMenuLocation = options::get_mihanpanel_wp_nav_menu_location();
        if($args->theme_location == $selectedNavMenuLocation)
        {
            $link = is_user_logged_in() ? \mihanpanel\app\options::get_panel_url() : \mihanpanel\app\options::getMihanPanelLoginUrl();
            $title = is_user_logged_in() ? __('Panel', 'mihanpanel') : __('Login / Register', 'mihanpanel');

            // Add a custom menu item
            $custom_item = new \stdClass();
            $custom_item->ID = 0;
            $custom_item->menu_item_parent = 0;
            $custom_item->url = $link;
            $custom_item->title = $title;
            $custom_item->menu_order = count($items) + 1;
            $custom_item->object_id = 0;
            $custom_item->type = 'custom';
            $custom_item->object = '';
            $custom_item->classes = array('menu-item');

            // Append the custom item to the menu
            $items[] = $custom_item;
        }
        return $items;
    }
    static function checkMihanPanelDbTablesForHealthNotice($tables)
    {
        // check mihanpanel_notifications table in db
        // check mihanpanel_notificationmeta table in db
        $tables[] = 'mihanpanel_notifications';
        $tables[] = 'mihanpanel_notificationmeta';
        return $tables;
    }
}