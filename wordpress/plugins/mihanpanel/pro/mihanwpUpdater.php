<?php
namespace mihanpanel\pro;
class mihanwpUpdater
{
    private static $_base_api_server;
    private static $_license_key;
    private static $_item_id;
    private static $_current_version;
    private static $_plugin_slug;
    private static $_license_status;

    private static $_new_version;
    private static $_package_url;
    
    static function init($args=[])
    {
        self::$_base_api_server = isset($args['base_api_server']) ? trailingslashit($args['base_api_server']) : false;
        self::$_license_key = isset($args['license_key']) ? $args['license_key'] : false;
        self::$_item_id = isset($args['item_id']) ? intval($args['item_id']) : false;
        self::$_current_version = isset($args['current_version']) ? $args['current_version'] : false;
        self::$_plugin_slug = isset($args['plugin_slug']) ? $args['plugin_slug'] : false;
        self::$_license_status = isset($args['license_status']) ? $args['license_status'] : false;
        self::setHooks();
    }
    static function setHooks()
    {
        if(self::$_license_status)
        {
            add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'checkUpdate']);
        }else{
            add_filter('plugin_row_meta', [__CLASS__, 'showUpdateBtnOnDeactiveLicenseMode'], 10, 4);
        }

        
    }
    static function showUpdateBtnOnDeactiveLicenseMode($pluginMeta, $pluginFile, $pluginData, $status)
    {
        if((basename(MW_MIHANPANEL_DIR) . '/' . basename(WP_MIHANPANEL_MAIN_APP)) == $pluginFile)
        {
            if(self::checkNewVersion())
            {
                $styles = [
                    'background-color: #3d4761',
                    'color: white',
                    'display: inline-block',
                    'padding: 10px',
                    'border-radius: 5px',
                ];
                $styles = implode('; ', $styles);
                $btn = '<a style="'.$styles.'" id="mihanpanel-update-btn" href="#">'.__('New version available', 'mihanpanel').'</a>';
                $pluginMeta[] = $btn;
                $msg = __('Please activate MihanPanel License first, then you can update plugin.', 'mihanpanel');
                ?>
                <script>
                    jQuery(document).ready(function($){
                        $(document).on('click', '#mihanpanel-update-btn', function(e){
                            e.preventDefault()
                            alert('<?php echo $msg?>')
                        })
                    })
                </script>
                <?php
            }
        }
        return $pluginMeta;
    }
    static function checkUpdate($transient)
    {
        if(empty($transient->checked))
        {
            return $transient;
        }
        // check is new version available
        if(self::checkNewVersion())
        {
            $transient->response[self::$_plugin_slug] = (object) [
                'new_version' => self::$_new_version,
                'package' => self::$_package_url,
                'slug' => self::$_plugin_slug
            ];
        }
        return $transient;
    }
    static function checkNewVersion()
    {
        $url = self::$_base_api_server;
        $url .= 'api/v2/' . self::$_license_key . '/update/info';
        $args = [
            'body' => [
                'product_id' => self::$_item_id
            ],
            'timeout' => 300
        ];
        $remote = wp_remote_get($url, $args);
        if(wp_remote_retrieve_response_code($remote) != 200)
        {
            return false;
        }
        $response = json_decode(wp_remote_retrieve_body($remote));
        self::$_new_version = isset($response->version) ? $response->version : false;
        self::$_package_url = isset($response->download_url) ? $response->download_url : false;
        return version_compare(self::$_new_version, self::$_current_version) == 1;
    }
}