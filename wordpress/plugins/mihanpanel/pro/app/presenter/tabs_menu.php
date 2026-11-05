<?php
namespace mihanpanel\pro\app\presenter;
class tabs_menu
{
    static function render_tab_item_icon($icon_element, $icon)
    {
        // check is url
        if($icon_url = filter_var($icon, FILTER_VALIDATE_URL))
        {
            $icon_element = sprintf('<img src="%s" alt="%s" />', $icon_url, $icon_url);
        }
        return $icon_element;
    }
    static function render_tab_menu_id($attrs, $menu)
    {
        $meta = isset($menu->meta) && $menu->meta ? unserialize($menu->meta) : false;
        if(!$meta)
        {
            return $attrs;
        }
        $field_id = isset($meta['field_id']) ? $meta['field_id'] : '';
        if($field_id)
        {
            $attrs['id'] = $field_id;
        }
        return $attrs;
    }
    static function handle_render_menu_permission($permission, $menu)
    {
        $meta = isset($menu->meta) && $menu->meta ? unserialize($menu->meta) : false;
        if(!$meta)
        {
            return $permission;
        }
        $show_by_roles = isset($meta['show_by_roles']) ? $meta['show_by_roles'] : [];
        if(!$show_by_roles)
        {
            return $permission;
        }
        return \mihanpanel\app\users::permision_by_role($show_by_roles);
    }
}