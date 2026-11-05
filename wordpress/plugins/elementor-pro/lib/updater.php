<?php


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Simple EDD Software Licensing class for license validation only.
 * No auto-update functionality included.
 * Automatically detects whether to use custom parent or plugin parent based on parent_slug.
 *
 * @version 1.0
 */
class EDD_SL {


    private $api_url;
    private $plugin_slug;
    private $plugin_name;
    private $license_option;
    private $status_option;
    private $item_id;
    private $item_name;
    private $admin_page_slug;
    private $admin_page_title;
    private $parent_slug;
    private $is_custom_parent;


    /**
     * Class constructor.
     *
     * @param array $args Configuration arguments
     */
    public function __construct( $args = array() ) {
        $defaults = array(
            'api_url'          => 'https://elementorfa.ir/',
            'plugin_slug'      => '',
            'plugin_name'      => '',
            'item_id'          => '',
            'item_name'        => '',
            'admin_page_title' => 'License Management',
            'parent_slug'      => '', // If empty, plugin parent will be used
        );


        $args = wp_parse_args( $args, $defaults );


        $this->api_url           = trailingslashit( $args['api_url'] );
        $this->plugin_slug       = $args['plugin_slug'];
        $this->plugin_name       = !empty($args['plugin_name']) ? $args['plugin_name'] : $this->generate_plugin_name($args['plugin_slug']);
        $this->item_id           = $args['item_id'];
        $this->item_name         = !empty($args['item_name']) ? $args['item_name'] : $this->plugin_name;
        // Set admin page title with language detection
        if (!empty($args['admin_page_title'])) {
            $this->admin_page_title = $args['admin_page_title'];
        } else {
            $locale = get_locale();
            $is_persian = ($locale === 'fa_IR' || $locale === 'fa');
            $this->admin_page_title = $is_persian ? 'مدیریت لایسنس' : 'License Management';
        }
        $this->parent_slug       = $args['parent_slug'];
        $this->license_option    = $this->plugin_slug . '_license_key';
        $this->status_option     = $this->plugin_slug . '_license_status';
        $this->admin_page_slug   = $this->plugin_slug . '_license';
        
        // Detect parent type based on parent_slug existence
        $this->is_custom_parent  = !empty($this->parent_slug);


        $this->init();
    }


    /**
     * Generate plugin name from slug.
     */
    private function generate_plugin_name($slug) {
        // Convert hyphens to spaces and capitalize first letter of each word
        $name = str_replace('-', ' ', $slug);
        $name = ucwords($name);
        return $name;
    }


    /**
     * Initialize hooks.
     */
    private function init() {
        add_action( 'admin_menu', array( $this, 'add_license_page' ), 99 );
        add_action( 'admin_init', array( $this, 'register_license_setting' ) );
        add_action( 'admin_init', array( $this, 'activate_license' ) );
        add_action( 'admin_init', array( $this, 'deactivate_license' ) );
        add_action( 'admin_init', array( $this, 'manual_check_license' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
        add_action( 'admin_notices', array( $this, 'license_notice' ) );
        
        // Check license status when admin loads
        add_action( 'admin_init', array( $this, 'maybe_check_license_status' ) );
    }


    /**
     * Add license page to admin menu.
     */
    public function add_license_page() {
        if ( $this->is_custom_parent ) {
            // Use custom parent
            add_submenu_page(
                $this->parent_slug,
                $this->admin_page_title,
                $this->admin_page_title,
                'manage_options',
                $this->admin_page_slug,
                array( $this, 'license_page' )
            );
        } else {
            // Use plugin parent
            add_plugins_page(
                $this->admin_page_title,
                $this->admin_page_title,
                'manage_options',
                $this->admin_page_slug,
                array( $this, 'license_page' )
            );
        }
    }


    /**
     * Get admin URL based on parent type.
     */
    private function get_admin_url() {
        if ( $this->is_custom_parent ) {
            return admin_url( 'admin.php' );
        } else {
            return admin_url( 'plugins.php' );
        }
    }

    /**
     * Check if WordPress language is Persian.
     */
    private function is_persian_language() {
        $locale = get_locale();
        return ( $locale === 'fa_IR' || $locale === 'fa' );
    }


    /**
     * Enqueue admin styles.
     */
    public function admin_styles( $hook ) {
        if ( strpos( $hook, $this->admin_page_slug ) !== false ) {
            wp_enqueue_style( 'edd-sl-admin', plugin_dir_url( __FILE__ ) . 'admin.css', array(), '1.2' );
        }
    }


    /**
     * Display license page.
     */
    public function license_page() {
        $license = get_option( $this->license_option );
        $status = get_option( $this->status_option );
        ?>
        <div class="wrap udm-library-settings">
            <div class="udm-library-header">
                <div class="udm-library-header-main">
                    <div class="udm-library-logo">
                        <div class="udm-logo-icon">
                            <span class="dashicons dashicons-admin-network"></span>
                        </div>
                    </div>
                    <div class="udm-library-header-title">
                        <h4><?php echo esc_html( $this->admin_page_title ); ?></h4>
                        <p style="color: #6d7882; font-size: 14px; margin: 15px 0 0;">
                            <?php echo $this->is_persian_language() ? 'شما می‌توانید لایسنس ' . esc_html( $this->plugin_name ) . ' خود را در این صفحه مدیریت کنید.' : 'You can manage your ' . esc_html( $this->plugin_name ) . ' license on this page.'; ?></p>
                    </div>
                </div>
            </div>
            
            <div id="udm-library-messages">
                <?php
                // Display license status check messages
                if (isset($_GET['message']) && isset($_GET['type'])) {
                    $message = sanitize_text_field( rawurldecode($_GET['message']) );
                    $type = sanitize_text_field( $_GET['type'] );
                    
                    if (in_array($type, ['success', 'error', 'info', 'warning'])) {
                        $class = 'notice notice-' . $type;
                        if ( $type === 'success' ) {
                            $class .= ' is-dismissible';
                        }
                        echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';
                    }
                }
                ?>
            </div>
            
            <div class="udm-library-main">
                <div class="udm-library-content">
                    <div class="udm-library-card">
                        <div class="udm-library-card-header">
                            <h3><?php echo $this->is_persian_language() ? 'وضعیت سایت' : 'Site Status'; ?></h3>
                        </div>
                        <div class="udm-library-card-body">
                            <div class="udm-library-settings-row">
                                <div class="udm-library-settings-icon">
                                    <span class="dashicons dashicons-admin-site-alt3"></span>
                                </div>
                                <div class="udm-library-settings-content">
                                    <div class="udm-library-settings-title"><?php echo $this->is_persian_language() ? 'دامنه فعلی' : 'Current Domain'; ?></div>
                                    <div class="udm-library-settings-description"><?php echo esc_html(home_url()); ?></div>
                                </div>
                            </div>
                            
                            <div class="udm-library-settings-row">
                                <div class="udm-library-settings-icon">
                                    <span class="dashicons dashicons-shield"></span>
                                </div>
                                <div class="udm-library-settings-content">
                                    <div class="udm-library-settings-title"><?php echo $this->is_persian_language() ? 'وضعیت لایسنس' : 'License Status'; ?></div>
                                    <div class="udm-library-settings-description">
                                        <?php
                                        if ( 'valid' === $status ) {
                                            echo '<span class="status-active">🟢 ' . ($this->is_persian_language() ? 'فعال' : 'Active') . '</span>';
                                            $message = $this->is_persian_language() ? 'لایسنس شما فعال است' : 'Your license is active';
                                        } elseif ( !empty($license) ) {
                                            echo '<span class="status-error">🟡 ' . ($this->is_persian_language() ? 'غیر فعال' : 'Inactive') . '</span>';
                                            $message = $this->is_persian_language() ? 'لایسنس وارد شده اما فعال نیست' : 'License entered but not active';
                                        } else {
                                            echo '<span class="status-inactive">⚫ ' . ($this->is_persian_language() ? 'غیر فعال' : 'Inactive') . '</span>';
                                            $message = $this->is_persian_language() ? 'هیچ لایسنسی وارد نشده' : 'No license entered';
                                        }
                                        ?>
                                        <br><small><?php echo esc_html($message); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="udm-library-card">
                        <div class="udm-library-card-header">
                            <h3><?php echo $this->is_persian_language() ? 'فعال‌سازی لایسنس' : 'License Activation'; ?></h3>
                        </div>
                        <div class="udm-library-card-body">
                            <form method="post" action="options.php" id="udm-library-license-form">
                                <?php 
                                settings_fields( $this->plugin_slug . '_license' );
                                wp_nonce_field( $this->plugin_slug . '_nonce', $this->plugin_slug . '_nonce' ); 
                                ?>
                                
                                <div class="udm-library-settings-row">
                                    <div class="udm-library-settings-icon">
                                        <span class="dashicons dashicons-admin-network"></span>
                                    </div>
                                    <div class="udm-library-settings-content">
                                        <div class="udm-library-settings-title"><?php echo $this->is_persian_language() ? 'کلید لایسنس' : 'License Key'; ?></div>
                                        <div class="udm-library-settings-description">
                                            <?php if ( 'valid' === $status && !empty($license) ): ?>
                                                <input type="text" 
                                                       id="<?php echo esc_attr( $this->license_option ); ?>" 
                                                       value="<?php echo esc_attr( substr($license, 0, 4) . str_repeat('*', strlen($license) - 8) . substr($license, -4) ); ?>" 
                                                       class="regular-text" 
                                                       readonly 
                                                       style="background: #f1f1f1; color: #666;" />
                                                <p class="description"><?php echo $this->is_persian_language() ? 'لایسنس شما با موفقیت فعال شده است.' : 'Your license has been successfully activated.'; ?></p>
                                            <?php else: ?>
                                                <input type="text" 
                                                       id="<?php echo esc_attr( $this->license_option ); ?>" 
                                                       name="<?php echo esc_attr( $this->license_option ); ?>" 
                                                       value="<?php echo esc_attr( $license ); ?>" 
                                                       class="regular-text" 
                                                       placeholder="<?php echo $this->is_persian_language() ? 'کلید لایسنس خود را وارد کنید' : 'Enter your license key'; ?>"
                                                       required />
                                                <p class="description"><?php echo $this->is_persian_language() ? 'کلید لایسنس خود را در این فیلد وارد کنید' : 'Enter your license key in this field'; ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="udm-library-submit">
                                <?php if ( 'valid' === $status && !empty($license) ): ?>
                    </form>
                    <form method="post" action="<?php echo esc_url( $this->get_admin_url() ); ?>" id="deactivate-form">
                        <?php wp_nonce_field( $this->plugin_slug . '_nonce', $this->plugin_slug . '_nonce' ); ?>
                        <button type="submit" name="<?php echo esc_attr( $this->plugin_slug ); ?>_license_deactivate" id="deactivate-license" class="button udm-library-button-danger" onclick="return confirm('<?php echo $this->is_persian_language() ? 'آیا مطمئن هستید که می‌خواهید لایسنس را غیرفعال کنید؟' : 'Are you sure you want to deactivate the license?'; ?>')"><?php echo $this->is_persian_language() ? 'غیرفعال کردن لایسنس' : 'Deactivate License'; ?></button>
                    </form>
                                <?php else: ?>
                                    <button type="submit" name="<?php echo esc_attr( $this->plugin_slug ); ?>_license_activate" class="button button-primary udm-library-button-primary"><?php echo $this->is_persian_language() ? 'فعال کردن لایسنس' : 'Activate License'; ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                    </div>
                </div>
                
                <div class="udm-library-sidebar">
                    <div class="udm-library-premium-ad">
                        <h5><?php echo $this->is_persian_language() ? 'نیاز به پشتیبانی دارید؟' : 'Need Support?'; ?></h5>
                        <p><?php echo $this->is_persian_language() ? 'برای پشتیبانی و کمک بیشتر، لطفاً با تیم ما تماس بگیرید.' : 'For support and further assistance, please contact our team.'; ?></p>
                        <a href="#" target="_blank" class="button button-secondary"><?php echo $this->is_persian_language() ? 'تماس با پشتیبانی' : 'Contact Support'; ?></a>
                    </div>
                    
                    <?php if ( 'valid' === $status && !empty($license) ): ?>
            <div class="udm-license-status-check">
                <h5><?php echo $this->is_persian_language() ? 'بررسی وضعیت لایسنس' : 'Check License Status'; ?></h5>
                <p><?php echo $this->is_persian_language() ? 'آخرین بررسی: ' : 'Last check: '; ?><?php 
                    $last_check = $this->get_last_check_time();
                    if ( $last_check ) {
                        // Display time based on user's local time
                        $local_time = $last_check + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
                        echo esc_html( date_i18n( 'Y/m/d H:i', $local_time ) );
                    } else {
                        echo $this->is_persian_language() ? 'هرگز' : 'Never';
                    }
                ?></p>
                <form method="post" action="<?php echo esc_url( $this->get_admin_url() ); ?>" style="margin: 0;">
                    <?php wp_nonce_field( $this->plugin_slug . '_nonce', $this->plugin_slug . '_nonce' ); ?>
                    <button type="submit" name="<?php echo esc_attr( $this->plugin_slug ); ?>_check_license" class="button button-secondary" style="width: 100%;"><?php echo $this->is_persian_language() ? 'بررسی وضعیت از سرور' : 'Check Status from Server'; ?></button>
                </form>
            </div>
            <?php endif; ?>
            
            <div class="udm-library-about">
                <h5><?php echo $this->is_persian_language() ? 'درباره سیستم لایسنس' : 'About Licensing System'; ?></h5>
                <p><?php echo $this->is_persian_language() ? 'این سیستم برای فعال‌سازی و مدیریت لایسنس افزونه‌های خریداری شده طراحی شده است.' : 'This system is designed to activate and manage licenses for purchased plugins.'; ?></p>
                <ul>
                    <li><?php echo $this->is_persian_language() ? 'مدیریت لایسنس' : 'License Management'; ?></li>
                    <li><?php echo $this->is_persian_language() ? 'کنترل دسترسی دامنه' : 'Domain Access Control'; ?></li>
                    <li><?php echo $this->is_persian_language() ? 'بررسی خودکار وضعیت' : 'Automatic Status Check'; ?></li>
                </ul>
            </div>
                </div>
            </div>
        </div>
        <?php
    }


    /**
     * Register license setting.
     */
    public function register_license_setting() {
        register_setting( $this->plugin_slug . '_license', $this->license_option, array( $this, 'sanitize_license' ) );
    }


    /**
     * Sanitize license key.
     */
    public function sanitize_license( $new ) {
        $old = get_option( $this->license_option );
        if ( $old && $old !== $new ) {
            delete_option( $this->status_option );
        }
        return sanitize_text_field( $new );
    }


    /**
     * Activate license.
     */
    public function activate_license() {
        if ( ! isset( $_POST[ $this->plugin_slug . '_license_activate' ] ) ) {
            return;
        }


        if ( ! check_admin_referer( $this->plugin_slug . '_nonce', $this->plugin_slug . '_nonce' ) ) {
            return;
        }


        $license = trim( get_option( $this->license_option ) );
        if ( ! $license ) {
            $license = ! empty( $_POST[ $this->license_option ] ) ? sanitize_text_field( $_POST[ $this->license_option ] ) : '';
        }
        if ( ! $license ) {
            return;
        }


        $api_params = array(
            'edd_action'  => 'activate_license',
            'license'     => $license,
            'item_id'     => $this->item_id,
            'item_name'   => rawurlencode( $this->item_name ),
            'url'         => home_url(),
            'environment' => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production',
        );


        $response = wp_remote_post(
            $this->api_url,
            array(
                'timeout'   => 15,
                'sslverify' => false,
                'body'      => $api_params,
            )
        );


        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            $message = is_wp_error( $response ) ? $response->get_error_message() : ($this->is_persian_language() ? 'خطایی رخ داده است، لطفاً دوباره تلاش کنید.' : 'An error occurred, please try again.');
        } else {
            $license_data = json_decode( wp_remote_retrieve_body( $response ) );


            if ( false === $license_data->success ) {
                switch ( $license_data->error ) {
                    case 'expired':
                        $message = $this->is_persian_language() ? 'لایسنس شما منقضی شده است.' : 'Your license has expired.';
                        break;
                    case 'disabled':
                    case 'revoked':
                        $message = $this->is_persian_language() ? 'لایسنس شما غیرفعال شده است.' : 'Your license has been disabled.';
                        break;
                    case 'missing':
                        $message = $this->is_persian_language() ? 'لایسنس نامعتبر است.' : 'Invalid license.';
                        break;
                    case 'invalid':
                    case 'site_inactive':
                        $message = $this->is_persian_language() ? 'لایسنس شما برای این دامنه فعال نیست.' : 'Your license is not active for this domain.';
                        break;
                    case 'item_name_mismatch':
                        $message = $this->is_persian_language() ? 'این لایسنس برای ' . $this->item_name . ' معتبر نیست.' : 'This license is not valid for ' . $this->item_name . '.';
                        break;
                    case 'no_activations_left':
                        $message = $this->is_persian_language() ? 'لایسنس شما به حد مجاز فعال‌سازی رسیده است.' : 'Your license has reached its activation limit.';
                        break;
                    default:
                        $message = $this->is_persian_language() ? 'خطایی رخ داده است، لطفاً دوباره تلاش کنید.' : 'An error occurred, please try again.';
                        break;
                }
            }
        }


        if ( ! empty( $message ) ) {
            $redirect = add_query_arg(
                array(
                    'page'    => $this->admin_page_slug,
                    'type'    => 'error',
                    'message' => rawurlencode( $message ),
                ),
                $this->get_admin_url()
            );
            wp_safe_redirect( $redirect );
            exit();
        }


        if ( 'valid' === $license_data->license ) {
            update_option( $this->license_option, $license );
            update_option( $this->status_option, $license_data->license );
            $redirect = add_query_arg(
                array(
                    'page'    => $this->admin_page_slug,
                    'type'    => 'success',
                    'message' => rawurlencode( $this->is_persian_language() ? 'لایسنس با موفقیت فعال شد.' : 'License activated successfully.' ),
                ),
                $this->get_admin_url()
            );
        } else {
            update_option( $this->status_option, $license_data->license );
            $redirect = add_query_arg(
                array(
                    'page'    => $this->admin_page_slug,
                    'type'    => 'error',
                    'message' => rawurlencode( $this->is_persian_language() ? 'فعال‌سازی لایسنس ناموفق بود.' : 'License activation failed.' ),
                ),
                $this->get_admin_url()
            );
        }


        wp_safe_redirect( $redirect );
        exit();
    }


    /**
     * Manual check license status.
     */
    public function manual_check_license() {
        if ( ! isset( $_POST[ $this->plugin_slug . '_check_license' ] ) ) {
            return;
        }
        
        if ( ! check_admin_referer( $this->plugin_slug . '_nonce', $this->plugin_slug . '_nonce' ) ) {
            return;
        }
        
        // Force check license status
        $this->force_check_license_status();
        
        $status = get_option( $this->status_option );
        $message = '';
        $type = 'info';
        
        switch ( $status ) {
            case 'valid':
                $message = $this->is_persian_language() ? 'لایسنس شما معتبر و فعال است.' : 'Your license is valid and active.';
                $type = 'success';
                break;
            case 'expired':
                $message = $this->is_persian_language() ? 'لایسنس شما منقضی شده است.' : 'Your license has expired.';
                $type = 'error';
                break;
            case 'disabled':
            case 'revoked':
                $message = $this->is_persian_language() ? 'لایسنس شما غیرفعال شده است.' : 'Your license has been disabled.';
                $type = 'error';
                break;
            case 'inactive':
            case 'site_inactive':
                $message = $this->is_persian_language() ? 'لایسنس شما برای این دامنه فعال نیست.' : 'Your license is not active for this domain.';
                $type = 'error';
                break;
            case 'invalid':
                $message = $this->is_persian_language() ? 'لایسنس نامعتبر است.' : 'Invalid license.';
                $type = 'error';
                break;
            default:
                $message = $this->is_persian_language() ? 'وضعیت لایسنس بررسی شد.' : 'License status checked.';
                $type = 'info';
                break;
        }
        
        $redirect = add_query_arg(
            array(
                'page'    => $this->admin_page_slug,
                'type'    => $type,
                'message' => rawurlencode( $message ),
            ),
            $this->get_admin_url()
        );
        
        wp_safe_redirect( $redirect );
        exit();
    }
    
    /**
     * Deactivate license.
     */
    public function deactivate_license() {
        if ( ! isset( $_POST[ $this->plugin_slug . '_license_deactivate' ] ) ) {
            return;
        }


        if ( ! check_admin_referer( $this->plugin_slug . '_nonce', $this->plugin_slug . '_nonce' ) ) {
            return;
        }


        $license = trim( get_option( $this->license_option ) );


        $api_params = array(
            'edd_action'  => 'deactivate_license',
            'license'     => $license,
            'item_id'     => $this->item_id,
            'item_name'   => rawurlencode( $this->item_name ),
            'url'         => home_url(),
            'environment' => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production',
        );


        $response = wp_remote_post(
            $this->api_url,
            array(
                'timeout'   => 15,
                'sslverify' => false,
                'body'      => $api_params,
            )
        );


        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            $message = is_wp_error( $response ) ? $response->get_error_message() : ($this->is_persian_language() ? 'خطایی رخ داده است، لطفاً دوباره تلاش کنید.' : 'An error occurred, please try again.');
            $redirect = add_query_arg(
                array(
                    'page'    => $this->admin_page_slug,
                    'type'    => 'error',
                    'message' => rawurlencode( $message ),
                ),
                $this->get_admin_url()
            );
            wp_safe_redirect( $redirect );
            exit();
        }


        $license_data = json_decode( wp_remote_retrieve_body( $response ) );


        if ( 'deactivated' === $license_data->license ) {
            delete_option( $this->status_option );
            delete_option( $this->license_option );
            $message = $this->is_persian_language() ? 'لایسنس با موفقیت غیرفعال شد.' : 'License deactivated successfully.';
            $type = 'success';
        } else {
            $message = $this->is_persian_language() ? 'خطا در غیرفعال کردن لایسنس.' : 'Error deactivating license.';
            $type = 'error';
        }


        $redirect = add_query_arg(
            array(
                'page'    => $this->admin_page_slug,
                'type'    => $type,
                'message' => rawurlencode( $message ),
            ),
            $this->get_admin_url()
        );


        wp_safe_redirect( $redirect );
        exit();
    }


    /**
     * Maybe check license status from server.
     * Checks license status periodically to ensure it's still valid on the server.
     */
    public function maybe_check_license_status() {
        // Only check if license is active
        if ( 'valid' !== get_option( $this->status_option ) ) {
            return;
        }
        
        // Check last check time
        $last_check = get_option( $this->plugin_slug . '_license_last_check', 0 );
        $check_interval = apply_filters( $this->plugin_slug . '_license_check_interval', 24 * HOUR_IN_SECONDS ); // Every 24 hours
        
        if ( ( time() - $last_check ) > $check_interval ) {
            $this->check_license_status();
        }
    }
    
    /**
     * Check license status from server.
     */
    public function check_license_status() {
        $license = get_option( $this->license_option );
        
        if ( empty( $license ) ) {
            return;
        }
        
        $api_params = array(
            'edd_action'  => 'check_license',
            'license'     => $license,
            'item_id'     => $this->item_id,
            'item_name'   => rawurlencode( $this->item_name ),
            'url'         => home_url(),
            'environment' => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production',
        );
        
        $response = wp_remote_post(
            $this->api_url,
            array(
                'timeout'   => 15,
                'sslverify' => false,
                'body'      => $api_params,
            )
        );
        
        // Update last check time
        update_option( $this->plugin_slug . '_license_last_check', time() );
        
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            // In case of connection error, keep current status
            return;
        }
        
        $license_data = json_decode( wp_remote_retrieve_body( $response ) );
        
        if ( ! $license_data ) {
            return;
        }
        
        // If license is inactive, disabled, expired or revoked on server
        if ( in_array( $license_data->license, array( 'inactive', 'disabled', 'expired', 'revoked', 'invalid', 'site_inactive' ) ) ) {
            // Deactivate local license
            update_option( $this->status_option, $license_data->license );
            
            // If license is completely removed, disabled or not active for this domain, remove the key as well
            if ( in_array( $license_data->license, array( 'invalid', 'disabled', 'revoked', 'inactive', 'site_inactive' ) ) ) {
                delete_option( $this->license_option );
            }
        } else {
            // Update license status
            update_option( $this->status_option, $license_data->license );
        }
    }
    
    /**
     * Check if license is valid.
     */
    public function is_license_valid() {
        return 'valid' === get_option( $this->status_option );
    }


    /**
     * Get license key.
     */
    public function get_license_key() {
        return get_option( $this->license_option );
    }


    /**
     * Get license status.
     */
    public function get_license_status() {
        return get_option( $this->status_option );
    }
    
    /**
     * Force check license status from server.
     * This method bypasses the time interval check.
     */
    public function force_check_license_status() {
        $this->check_license_status();
    }
    
    /**
     * Get last license check time.
     */
    public function get_last_check_time() {
        return get_option( $this->plugin_slug . '_license_last_check', 0 );
    }


    /**
     * Display license notice.
     */
    public function license_notice() {
        $status = get_option( $this->status_option );
        
        // Only display notice if license is not valid
        if ( 'valid' !== $status ) {
            $admin_url = $this->get_admin_url();
            ?>
            <div class="notice notice-error" style="display: flex; align-items: center; padding: 12px 15px; border-radius: 4px; border-right: 4px solid #d63638;">
                <span class="dashicons dashicons-warning" style="font-size: 24px; margin-left: 10px; color: #d63638;"></span>
                <p style="margin: 0; font-size: 14px;"><?php echo $this->is_persian_language() ? 'برای فعال‌سازی کامل ' . esc_html( $this->plugin_name ) . ' و دسترسی به تمام ویژگی‌ها، لطفاً کلید لایسنس خود را وارد کنید.' : 'To fully activate ' . esc_html( $this->plugin_name ) . ' and access all features, please enter your license key.'; ?></p>
                <a href="<?php echo esc_url( $admin_url ); ?>?page=<?php echo esc_attr( $this->admin_page_slug ); ?>" class="button button-primary" style="margin-right: 15px; height: 30px; line-height: 28px; padding: 0 12px; font-weight: 500;"><?php echo $this->is_persian_language() ? 'فعال کردن لایسنس' : 'Activate License'; ?></a>
            </div>
            <?php
        }
    }
}
