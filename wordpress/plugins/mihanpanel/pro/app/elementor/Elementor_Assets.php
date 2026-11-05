<?php
namespace mihanpanel\pro\app\elementor;

use Elementor\Plugin;

class Elementor_Assets
{
    /**
     *
     * Get the id of the pages used in the current state
     *
     * @return array|null
     */
    public static function get_page_ids()
    {
        global $wp_styles;
        $ids = [];
        if(function_exists('get_the_ID')){
            $ids[] = get_the_ID();
        }

        if(!empty($wp_styles)){
            foreach ($wp_styles->queue as $style){
                preg_match_all('!\d+!', $style, $matches);
                $id = implode('', $matches[0] ?? []);
                if (empty($id) || get_post_status($id) != 'publish')
                    continue;
                $ids[] = implode('', $matches[0]);
            }
        }
        return is_array($ids) && !empty($ids) ? array_unique($ids) : [];
    }

    /**
     *
     * Extract element types (name) from current page elements
     *
     * @param $elements
     * @return array
     */
    public static function extract_element_types($elements)
    {
        $types = [];

        array_walk_recursive($elements, function($value, $key) use (&$types) {
            if ($key === 'widgetType') {
                $types[] = $value;
            }
        });

        return $types;
    }

    /**
     *
     * Load current page assets
     *
     * @return false|void
     */
    public static function load_page_elements_asset()
    {
        $pages_id = self::get_page_ids();

        if (empty($pages_id) || !is_array($pages_id))
            return false;

        $widgets = Plugin::instance()->widgets_manager->get_widget_types();

        foreach ($pages_id as $id){
            $document = \Elementor\Plugin::$instance->documents->get($id);

            if (empty($document) || !method_exists($document, 'get_elements_data'))
                continue;

            $page_elements = $document->get_elements_data();

            if(!empty($page_elements) && is_array($page_elements)){
                $types = self::extract_element_types($page_elements);

                if (!empty($types)){
                    foreach ($types as $type){
                        $class = $widgets[$type] ?? null;
                        if (is_object($class) && method_exists($class, '__enqueue_assets')){
                            $class->__enqueue_assets();
                        }
                    }
                }
            }
        }
    }

    public static function enqueue_elementor_post_scripts(){
        self::load_page_elements_asset();
    }
}