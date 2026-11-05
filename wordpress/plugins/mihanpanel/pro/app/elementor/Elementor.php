<?php
namespace mihanpanel\pro\app\elementor;

use mihanpanel\pro\app\assets;

class Elementor
{
    public static function register_widgets($widgets)
    {
        $widgets[] = '\mihanpanel\pro\app\elementor\widget\Login_Button';

        return $widgets;
    }
}