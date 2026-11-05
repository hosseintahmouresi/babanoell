<?php
namespace mihanpanel\pro\app;
class shortcode
{
    static function init()
    {
        add_shortcode('mw_restrict', [__CLASS__, 'restrict_content']);
    }
    static function restrict_content($atts, $content='')
    {
        // [mw_restrict roles="administrator, subscriber"]
        $atts = shortcode_atts(['roles' => 'subscriber', 'alternative_text' => __('Unfortunately, you do not have access to this part of the content', 'mihanpanel')], $atts);
        $roles = str_replace(' ', '', $atts['roles']);
        $roles = explode(',', $roles);
        $user = wp_get_current_user();
        $user_role = $user->roles;
        $user_role = is_array($user_role) ? array_shift($user_role) : false;
        $permission = $user_role == 'administrator' || in_array($user_role, $roles);
        if(!$permission && isset($atts['alternative_text']) && $atts['alternative_text'])
        {
            $styles = [
                'background-color: #f9c7c7',
                'color: #511c1c',
                'padding: 20px 10px',
                'display: block',
                'text-align: center',
                'border-radius: 5px',
            ];
            $styles = implode('; ', $styles);
            $alternativeText = sprintf('<span style="%s">%s</span>', $styles, sanitize_text_field($atts['alternative_text']));
            return $alternativeText;
        }
        return $permission ? $content : false;
    }
    static function handle_woocommerce_orders_shortcode()
    {
        if (class_exists('WooCommerce')) {
            $user_id = get_current_user_id();
            if ($user_id == 0) {
                return do_shortcode('[woocommerce_my_account]');
            } else {
                ob_start();
                echo '<section class="responsive_table_wrapper">';
                $customer_orders = wc_get_orders(
                    apply_filters(
                        'woocommerce_my_account_my_orders_query',
                        array(
                            'customer' => get_current_user_id(),
                            // 'page'     => $current_page,
                            'paginate' => true,
                            'numberposts' => '-1',
                            // 'posts_per_page' => '-1',
                        )
                    )
                );
                wc_get_template(
                    'myaccount/orders.php',
                    array(
                        'customer_orders' => $customer_orders,
                        'has_orders'      => 0 < $customer_orders->total,
                        'wp_button_class' => wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '',
                    ),
                );
                echo '</section>';
                return ob_get_clean();
            }
        } else {
            echo '<div class="alert alert-error"><span>'.esc_html__("Please install Woocommerce plugin.", 'mihanpanel').'</span></div>';
        }
    }
    static function handle_woocommer_downloads_shortcode()
    {
        if(!\mihanpanel\app\tools::is_woocommerce_active())
        {
            return esc_html__("You need to install woocommerce for use this section!", "mihanpanel");
        }
        $downloads     = WC()->customer->get_downloadable_products();
        $has_downloads = (bool) $downloads;
        do_action( 'woocommerce_before_account_downloads', $has_downloads );
        if ( $has_downloads ) :
            // check if user has not email
            if(!wp_get_current_user()->user_email)
            {
                foreach($downloads as $key => $downloadItem)
                {
                    $downloads[$key]['download_url'] = str_replace('&email&', '&email=null&', $downloadItem['download_url']);
                }
            }
            do_action( 'woocommerce_before_available_downloads' );
            do_action( 'woocommerce_available_downloads', $downloads );
            do_action( 'woocommerce_after_available_downloads' );
        else : ?>
          <div class="woocommerce-Message woocommerce-Message--info woocommerce-info">
              <a class="woocommerce-Button button" href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>">
                  <?php esc_html_e( 'Go shop', 'woocommerce' ); ?>
              </a>
              <?php esc_html_e( 'No downloads available yet.', 'woocommerce' ); ?>
          </div>
      <?php endif;
      do_action( 'woocommerce_after_account_downloads', $has_downloads );
    }
}