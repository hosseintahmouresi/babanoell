<?php
namespace mihanpanel\pro\app;
class license
{
    const LICENSE_KEY_OPTION_NAME = 'mw_455491_license_key';
    const LICENSE_STATUS_OPTION_NAME = 'mw_455491_license_status';
    const LICENSE_MENU_SLUG = 'mihanpanel_license';

    private static function getBaseApiServer($license_key)
    {
        $server = \mihanpanel\app\tools::getRemoteServerByLicenseKey($license_key);
        return $server . 'api/v2/';
    }
    static function init()
    {
        // license notices
        add_action( 'admin_notices', [__CLASS__, 'handleAdminNoticesWithCookie'] );
        add_filter('mwpl_before_redirect_to_option_panel_url', [__CLASS__, 'filter_redirect_to_option_panel_url_after_activation']);
        if(!defined('MIHANPANEL_LICENSE_KEY'))
        {
            add_action('admin_menu', [__CLASS__, 'license_menu']);
            add_action('admin_init', [__CLASS__, 'register_settings']);
        }
        
        add_action('admin_init', [__CLASS__, 'init_check_license_schedule_event']);
        add_action('mwpl_check_license_event', [__CLASS__, 'check_license_event_handler']);
    }
    static function filter_redirect_to_option_panel_url_after_activation($url)
    {
        if(!self::check_license())
        {
            $url = admin_url('admin.php?page=' . self::LICENSE_MENU_SLUG);
        }
        return $url;
    }
    static function init_check_license_schedule_event()
    {
        if(!wp_next_scheduled('mwpl_check_license_event'))
        {
            $hour = mt_rand(0, 23);
            $minutes = mt_rand(0, 59);
            $seconds = mt_rand(0, 59);
            $time = date("Y-m-d {$hour}:{$minutes}:{$seconds}", strtotime("+1 day"));
            wp_schedule_event(strtotime($time), 'daily', 'mwpl_check_license_event');
        }
    }
    static function check_license_event_handler()
    {
        if(self::check_license())
        {
            $license_key = self::get_license_key();
            if(!$license_key)
            {
                self::deactivate_license();
                return;
            }
            $from_server = self::check_license_from_server($license_key);
            if(!isset($from_server['status']) || !$from_server['status'])
            {
                self::deactivate_license();
                return;
            }
            $server_data = json_decode($from_server['body']);
            if(!$server_data || !isset($server_data->result) || !$server_data->result)
            {
                self::deactivate_license();
            }
        }
    }
    static function get_license_key()
    {
        return defined('MIHANPANEL_LICENSE_KEY') ? MIHANPANEL_LICENSE_KEY : get_option(self::LICENSE_KEY_OPTION_NAME);
    }
    static function activate_license()
    {
        return update_option(self::LICENSE_STATUS_OPTION_NAME, 'valid');
    }
    static function deactivate_license()
    {
        self::deleteLicenseCookieData();
        return delete_option(self::LICENSE_STATUS_OPTION_NAME);
    }
    static function deleteLicenseCookieData()
    {
        $time = -10;
        $domain = parse_url(get_option('siteurl'), PHP_URL_HOST);
        setcookie('mw_mihanpanel_dismiss_license_admin_notice', $time, $time, '/', $domain, true, true);
        unset($_COOKIE['mw_mihanpanel_dismiss_license_admin_notice']);
    }
    public static function check_license()
    {
        $license_status = get_option(self::LICENSE_STATUS_OPTION_NAME);
        return $license_status == 'valid' ? true : false;
    }
    static function handleAdminNoticesWithCookie()
    {
        if((isset($_GET['page']) && ($_GET['page'] == 'mihanpanel' || $_GET['page'] == 'mihanpanel_license')) || self::check_license())
        {
            return false;
        }
        $closedTime = isset($_COOKIE['mw_mihanpanel_dismiss_license_admin_notice']) ? $_COOKIE['mw_mihanpanel_dismiss_license_admin_notice'] : false;
        $oneWeek = 60 * 60 * 24 * 7;
        if(!$closedTime || (time() - $closedTime) > $oneWeek)
        {
            $notice = __('MihanPanel license has been deactivated, please activate it', 'mihanpanel');
            $link = admin_url('admin.php?page=mihanpanel_license');
            $wrapperStyles = [
                "position: relative",
                "margin: 20px 0",
                "width: 100%",
                "max-width:100%",
                "font-family:IRANSans",
                "font-size: 16px",
                "box-sizing: border-box",
                "border-radius: 10px",
                "font-weight: 100",
                'display: flex',
                'align-items: center',
                'gap: 25px',
                'padding: 30px 20px',
                'background: white',
                'border: 1px solid #e4e4e4',
                'color: black',
            ];
            $logoWrapperStyle = [
                'font-size: 25px',
                'font-weight: bold',
            ];
            $logoImgStyle = [
                'border-radius: 50%',
                'width: 65px',
            ];

            $noticeTextStyle = [
                'flex: auto',
            ];
            $noticeBoxLine = [
                'position: absolute',
                'width: 5px',
                'height: 85%',
                'background-color: #d70150',
                'border-radius: 20px',
            ];

            $btnStyles = [
                'box-shadow: none',
                'color:#d70150',
                'text-decoration:none',
                'border-radius:5px',
                'padding:10px 20px',
                'background-color: #f8eef1',
                'font-weight: bold',
            ];
            $dismissBtn = [
                'box-shadow: none',
                'color: black',
                'text-decoration: none',
                'padding: 10px 20px',
                'cursor: pointer',
            ];

            if(is_rtl())
            {
                $noticeBoxLine[] = 'right: 0';
                $logoWrapperStyle[] = 'border-left: 2px solid #e4e3e3';
                $logoWrapperStyle[] = 'padding-left: 20px';
            }else{
                $noticeBoxLine[] = 'left: 0';
                $logoWrapperStyle[] = 'border-right: 2px solid #e4e3e3';
                $logoWrapperStyle[] = 'padding-right: 20px';
            }
            $wrapperStyles = implode(';', $wrapperStyles);
            $logoWrapperStyle = implode(';', $logoWrapperStyle);
            $logoImgStyle = implode(';', $logoImgStyle);
            $noticeTextStyle = implode(';', $noticeTextStyle);
            $noticeBoxLine = implode(';', $noticeBoxLine);
            $btnStyles = implode(';', $btnStyles);
            $dismissBtn = implode(';', $dismissBtn);
            ?>
            <div class="wrap">
                <div class="mw-mihanpanel-license-admin-notice" style="<?php echo esc_attr($wrapperStyles)?>">
                    <span style="<?php echo esc_attr($noticeBoxLine)?>"></span>
                    <span style="<?php echo esc_attr($logoWrapperStyle)?>"><img style="<?php echo esc_attr($logoImgStyle)?>" src="<?php echo MW_MIHANPANEL_URL . 'img/mp-menu.png'?>" alt="mihanpanel-logo"></span>
                    <span style="<?php echo esc_attr($noticeTextStyle)?>"><?php echo $notice;?></span>
                    <span class="mw-notice-buttons">
                        <a style="<?php echo esc_attr($btnStyles)?>" href="<?php echo esc_url($link)?>"><?php _e('Get Started','mihanpanel');?></a>
                        <span class="mw-close-notice" style="<?php echo esc_attr($dismissBtn)?>"><?php _e("Remind later", 'mihanpanel');?></span>
                    </span>
                </div>
            </div>
            <script>
                jQuery(document).ready(function($) {
                    $('.mw-mihanpanel-license-admin-notice .mw-close-notice').on('click', function() {
                        // Set a cookie to store the closed time
                        $(this).closest('.mw-mihanpanel-license-admin-notice').fadeOut()
                        $.ajax({
                            url: ajaxurl,
                            type: 'post',
                            dataType: 'json',
                            data: {
                                action: 'mw_mihanpanel_dismiss_license_admin_notice',
                            },
                        });
                    });
                });
            </script>
            <?php
        }
    }
    static function register_settings()
    {
        register_setting('mw_455491_license', self::LICENSE_KEY_OPTION_NAME, [__CLASS__, 'handle_license_field_value']);
    }
    static function handle_license_field_value($new_value)
    {
        $old = get_option( self::LICENSE_KEY_OPTION_NAME );
        if( $old && $old != $new_value ) {
            delete_option( self::LICENSE_STATUS_OPTION_NAME );
        }
        return $new_value;
    }
    static function license_menu()
    {
        $title = esc_html__("License", "mihanpanel");
        $suffix = add_submenu_page('mihanpanel', $title, $title, 'manage_options', self::LICENSE_MENU_SLUG, [__CLASS__, 'license_menu_c']);
        add_action('load-' . $suffix, ['\mihanpanel\pro\app\assets', 'load_license_assets']);
    }
    static function license_menu_c()
    {
        $views = views::get('admin.license');
        $license_key = get_option(self::LICENSE_KEY_OPTION_NAME);
        $views ? include_once $views : null;
    }
    private static function check_license_from_server($license_key)
    {
        $res['status'] = false;
        $res['body'] = '';
        $api_url = self::getBaseApiServer($license_key);
        $api_url .= $license_key . '/license/status';
        $args = [
            'body' => [
                'product_id' => \mihanpanel\app\tools::getRemoteProductId($license_key),
            ],
            'timeout' => 900
        ];
        $remote = wp_remote_get($api_url, $args);
        if(is_wp_error($remote) || wp_remote_retrieve_response_code($remote) !== 200)
        {
            $res['msg'] = is_wp_error($remote) && !empty($remote->get_error_message()) ? $remote->get_error_message() : esc_html__('An error occurred!', 'mihanpanel');
            return $res;
        }
        $res['status'] = true;
        $res['body'] = wp_remote_retrieve_body($remote);
        return $res;
    }
    static function get_license_status()
    {
        $res['class'] = 'deactive';
        $res['msg'] = '';
        // if(!self::check_license())
        // {
        //     $res['msg'] = esc_html__('License is inactive.', 'mihanpanel');
        //     return $res;
        // }
        $license_data = self::get_license_status_data();
        if($license_data['status'] === 200)
        {
            self::activate_license();
            $res['class'] = 'active';
            $res['msg'] = $license_data['status_bar'];
        }else{
            self::deactivate_license();
            $res['msg'] = $license_data['msg'];
        }
        return $res;
    }
    static function get_license_status_data()
    {
        $res['status'] = 400;
        $res['msg'] = esc_html__('Missing license key', 'mihanpanel');
        $license_key = self::get_license_key();
        if(!$license_key)
        {
            return $res;
        }
        $license_data = self::check_license_from_server($license_key);
        if(!$license_data['status'])
        {
            $res['msg'] = $license_data['msg'];
            return $res;
        }
        $license_data = json_decode($license_data['body']);
        if($license_data->result === false)
        {
            $res['msg'] = esc_html__('License is inactive.', 'mihanpanel');
            return $res;
        }
        if($license_data->result == true)
        {
            $res['status'] = 200;
            $res['msg'] = esc_html__('MihanPanel is active with this license key', 'mihanpanel');
            $res['status_bar'] = $res['msg'];
        }
        return $res;
    }
    static function activate_license_process($license_key)
    {
        $res['status'] = 400;
        $res['msg'] = esc_html__('Has Error!', 'mihanpanel');
        self::deactivate_license();
        update_option(self::LICENSE_KEY_OPTION_NAME, $license_key);
        $api_url = self::getBaseApiServer($license_key);
        $api_url .= $license_key . '/license/activate';
        $args = [
            'body' => [
                'product_id' => \mihanpanel\app\tools::getRemoteProductId($license_key),
            ],
            'timeout' => 900
        ];
        $remote = wp_remote_get($api_url, $args);
        if(is_wp_error($remote) || wp_remote_retrieve_response_code($remote) !== 200)
        {
            $res['msg'] = is_wp_error($remote) && !empty($remote->get_error_message()) ? $remote->get_error_message() : esc_html__('An error occurred!', 'mihanpanel');
            return $res;
        }
        $response = json_decode(wp_remote_retrieve_body($remote));
        if(isset($response->message->success) && $response->message->success && isset($response->message->activated))
        {
            self::activate_license();
            $res['msg'] = esc_html__('Successfully activated.', 'mihanpanel');
            $res['status_bar'] = esc_html__('MihanPanel is active with this license key', 'mihanpanel');
            $res['status'] = 200;
        }else{
            $res['msg'] = esc_html__('License key is invalid or the number of activation has exceeded the allowed limit.', 'mihanpanel');
        }
        die(json_encode($res));
    }
    static function deactivate_license_process($license_key)
    {
        $res['status'] = 400;
        $res['msg'] = esc_html__('Has Error!', 'mihanpanel');
        $api_url = self::getBaseApiServer($license_key);
        $api_url .= $license_key . '/license/deactivate';
        $args = [
            'body' => [
                'product_id' => \mihanpanel\app\tools::getRemoteProductId($license_key),
            ],
            'timeout' => 900
        ];
        $remote = wp_remote_get($api_url, $args);
        if(is_wp_error($remote) || wp_remote_retrieve_response_code($remote) !== 200)
        {
            $res['msg'] = is_wp_error($remote) && !empty($remote->get_error_message()) ? $remote->get_error_message() : esc_html__('An error occurred!', 'mihanpanel');
            return $res;
        }
        $response = json_decode(wp_remote_retrieve_body($remote));
        if(isset($response->message->success) && $response->message->success && isset($response->message->deactivated))
        {
            $res['status'] = 200;
            $res['msg'] = esc_html__('Successfully deactivated.', 'mihanpanel');
            $res['status_bar'] = esc_html__('License is inactive', 'mihanpanel');
            self::deactivate_license();
        }else{
            $res['msg'] = $response->message->data->error;
        }
        return $res;
    }
}