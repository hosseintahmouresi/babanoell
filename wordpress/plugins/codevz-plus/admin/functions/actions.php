<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
/**
 *
 * Get icons from admin ajax
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if( ! function_exists( 'codevz_framework_get_icons' ) ) {
  function codevz_framework_get_icons() {

    $jsons = apply_filters( 'codevz/load/icons/json', glob( CODEVZ_FRAMEWORK_DIR . '/fields/icon/*.json' ) );

    if( ! empty( $jsons ) ) {

      foreach ( $jsons as $path ) {

        if ( Codevz_Plus::contains( $path, 'elementor.json' ) ) {
          continue;
        }

        $object = codevz_get_icon_fonts( $path );

        if( is_object( $object ) ) {

          echo ( count( $jsons ) >= 2 ) ? '<h4 class="codevz-icon-title">'. esc_html( $object->name ) .'</h4>' : '';

          foreach ( $object->icons as $icon ) {
            echo '<a class="codevz-icon-tooltip" data-codevz-icon="'. esc_attr( $icon ) .'" title="'. esc_attr( $icon ) .'"><span class="codevz-icon codevz-selector"><i class="'. esc_attr( $icon ) .'"></i></span></a>';
          }

        } else {
          echo '<h4 class="codevz-icon-title">AJAX error: can not load json file.</h4>';
        }

      }

    }

    wp_die();
  }
  add_action( 'wp_ajax_codevz-framework-get-icons', 'codevz_framework_get_icons' );
}

/**
 *
 * Export options
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if( ! function_exists( 'codevz_framework_export_options' ) ) {
  function codevz_framework_export_options() {

    if( isset( $_GET['export'] ) && isset( $_GET['wpnonce'] ) && wp_verify_nonce( $_GET['wpnonce'], 'csf_backup' ) ) {

      header('Content-Type: plain/text');
      header('Content-disposition: attachment; filename=backup-options-'. gmdate( 'd-m-Y' ) .'.txt');
      header('Content-Transfer-Encoding: binary');
      header('Pragma: no-cache');
      header('Expires: 0');

      $options = codevz_encode_string( get_option( $_GET['export'] ) );

      echo wp_kses_post( $options );

    }

    wp_die();
  }
  add_action( 'wp_ajax_codevz-framework-export-options', 'codevz_framework_export_options' );
}

/**
 *
 * Import options
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if( ! function_exists( 'codevz_framework_import_options' ) ) {
  function codevz_framework_import_options() {

    if ( isset( $_POST['unique'] ) && ! empty( $_POST['value'] ) && isset( $_POST['wpnonce'] ) && wp_verify_nonce( $_POST['wpnonce'], 'csf_backup' ) ) {

      $check = 'atheme.i'; // replace f.
      $options = codevz_decode_string( $_POST['value'] );

      // Fix RTL StyleKit.
      if ( $options && is_rtl() ) {

        // Fix RTL StyleKits.
        $options = json_encode( $options );
        $options = str_replace( ';RTL', ';', $options );

        // Fix links.
        $site = get_home_url();
        $parsed_url = parse_url( $site, PHP_URL_HOST );
        $domain = str_replace( 'www.', '', $parsed_url );

        if ( strpos( $domain, $check ) !== false ) {
          $options = str_replace( [ 'xtratheme.com', 'codevz.com', 'themetor.com' ], $domain, $options );
        }

        // Decode options.
        $options = json_decode( $options, true );

        // Fix translated old options.
        if ( strpos( $site, $check ) !== false ) {

          $translated = [
            'logo',
            'logo_2',
            'share_box_title',
            '404_msg',
            '404_btn',
            'mobile_menu_text',
            '_css_body_typo',
            '_css_all_headlines',
            'readmore',
            'not_found',
            'related_posts_post',
            'prev_post',
            'next_post',
            'no_comment',
            'comment',
            'comments',
            'cm_disabled',
            'search_title_prefix',
            'title_portfolio',
            'cat_title_portfolio',
            'tags_title_portfolio',
            'desc_portfolio',
            'readmore_portfolio',
            'related_posts_portfolio',
            'prev_portfolio',
            'next_portfolio',
            'woo_sold_out_title',
            'woo_cart',
            'woo_checkout',
            'woo_cart_footer',
            'woo_continue_shopping',
            'woo_no_products',
            'woo_after_product_meta',
            'woo_product_size_guide_tab_title',
            'woo_product_size_guide_tab_content',
            'woo_product_faq_tab_title',
            'woo_product_faq_tab_content',
            'woo_product_shipping_returns_tab_title',
            'woo_product_shipping_returns_tab_content'
          ];

          $old = get_option( 'codevz_theme_options' );

          foreach( $translated as $all => $option ) {

            if ( isset( $options[ $option ] ) && isset( $old[ $option ] ) ) {

              $options[ $option ] = $old[ $option ];

            }

          }

        }

      }

      update_option( $_POST['unique'], $options );
    
    }

    wp_die();
  }
  add_action( 'wp_ajax_codevz-framework-import-options', 'codevz_framework_import_options' );
}

/**
 *
 * Reset options
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if( ! function_exists( 'codevz_framework_reset_options' ) ) {
  function codevz_framework_reset_options() {

    if( isset( $_POST['unique'] ) && isset( $_POST['wpnonce'] ) && wp_verify_nonce( $_POST['wpnonce'], 'csf_backup' ) ) {

      delete_option( $_POST['unique'] );

      delete_option( 'codevz_primary_color' );
      delete_option( 'codevz_secondary_color' );

    }

    wp_die();
  }
  add_action( 'wp_ajax_codevz-framework-reset-options', 'codevz_framework_reset_options' );
}
