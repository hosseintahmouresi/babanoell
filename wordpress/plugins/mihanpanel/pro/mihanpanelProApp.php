<?php
namespace mihanpanel\pro;

use mihanpanel\pro\app\ajax;
use mihanpanel\pro\app\hooks;
use mihanpanel\pro\app\license;
use mihanpanel\pro\app\live_edit;
use mihanpanel\pro\app\login_system;

final class mihanpanelProApp
{
    static function init()
    {
        self::do_define();
        
        license::init();
        hooks::init();
        ajax::init();
        login_system::init();
        live_edit::getInstance();
        self::handle_updater();
    }
    static function do_define()
    {
        self::define('MIHANPANEL_PRO_DIR_PATH', plugin_dir_path(__FILE__));
        self::define('MIHANPANEL_PRO_DIR_URL', plugin_dir_url(__FILE__));
    }
    static function define($name, $value)
    {
        if(!defined($name))
        {
            define($name, $value);
        }
    }
    static function handle_updater()
    {
        $mw_plugin_data = get_file_data(WP_MIHANPANEL_MAIN_APP, array('Version' => 'Version'), false);
        $mw_plugin_version = $mw_plugin_data['Version'];
        $mw_license_key = license::get_license_key();
        $updaterArgs = [
            'base_api_server' => \mihanpanel\app\tools::getRemoteServerByLicenseKey($mw_license_key),
            'license_key' => $mw_license_key,
            'item_id' => \mihanpanel\app\tools::getRemoteProductId($mw_license_key),
            'current_version' => $mw_plugin_version,
            'plugin_slug' => plugin_basename(WP_MIHANPANEL_MAIN_APP),
            'license_status' => license::check_license(),
        ];
        mihanwpUpdater::init($updaterArgs);
    }
}
