<?php
namespace mihanpanel\pro\app;
class option_panel
{
    static function general_live_view_wrapper_content()
    {
        $panel_general_view = views::get('admin.live-view.panel-general');
        include_once $panel_general_view;
    }
}