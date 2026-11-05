<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
/**
 *
 * Options class
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if( ! class_exists( 'Codevz_Framework_Options' ) ) {
  class Codevz_Framework_Options extends Codevz_Framework_Abstract {

    /**
     *
     * unique
     * @access public
     * @var string
     *
     */
    public $unique = '';

    /**
     *
     * notice
     * @access public
     * @var boolean
     *
     */
    public $notice = false;

    /**
     *
     * settings
     * @access public
     * @var array
     *
     */
    public $settings = array();

    /**
     *
     * options
     * @access public
     * @var array
     *
     */
    public $options = array();

    /**
     *
     * sections
     * @access public
     * @var array
     *
     */
    public $sections = array();

    /**
     *
     * fields
     * @access public
     * @var array
     *
     */
    public $fields = array();

    /**
     *
     * options store
     * @access public
     * @var array
     *
     */
    public $db_option = array();
    public $installed = false;

    // run framework construct
    public function __construct( $settings = array(), $options = array() ) {

      $this->settings  = apply_filters( 'codevz/settings/framework', $settings );
      $this->options   = apply_filters( 'codevz/options/framework', $options );

      $this->unique    = $this->settings['option_name'];
      $this->sections  = $this->getSections( $this->options );
      $this->fields    = $this->getFields( $this->options );
      $this->db_option = get_option( $this->unique );
      $this->installed = get_option( $this->unique . '_installed' );

      $this->addAction( 'admin_init', 'setup' );
      $this->addAction( 'admin_menu', 'add_admin_menu' );

      $this->addEnqueue( $this->options );

    }

    // instance of framework
    public static function instance( $settings = array(), $options = array() ) {
      return new self( $settings, $options );
    }

    // wp settings api
    public function setup() {

      foreach( $this->sections as $section ) {

        $unique = $this->unique .'_'. $section['name'];

        register_setting( $this->unique .'_group', $this->unique, array( &$this,'validate_save' ) );

        if( ! empty( $section['fields'] ) ) {

          add_settings_section( $unique .'_section', $section['title'], '', $unique .'_section_group' );

          foreach( $section['fields'] as $key => $field ) {

            add_settings_field( $key .'_field', '', array( &$this, 'field_callback' ), $unique .'_section_group', $unique .'_section', $field );

            // set default option if isset
            if( isset( $field['id'] ) ) {

              $field_default = ( isset( $field['default'] ) ) ? $field['default'] : '';
              $field_value   = ( isset( $this->db_option[$field['id']] ) ) ? $this->db_option[$field['id']] : $field_default;

              $this->db_option[$field['id']] = $field_value;

            }

          }

        }

      }

      // check for is saved defaults in database
      if( $this->settings['save_defaults'] && empty( $this->installed ) ) {
        update_option( $this->unique, $this->db_option );
        update_option( $this->unique .'_installed', true );
      }

    }

    // section fields validate in save
    public function validate_save( $request ) {

      $add_errors = array();
      $section_id = ( ! empty( $request['_transient']['section'] ) ) ? $request['_transient']['section'] : '';

      // ignore nonce requests
      if( isset( $request['_nonce'] ) ) { unset( $request['_nonce'] ); }

      // import
      if ( isset( $request['import'] ) && ! empty( $request['import'] ) ) {
        $decode_string = codevz_decode_string( $request['import'] );
        if( is_array( $decode_string ) ) {
          return $decode_string;
        }
        $add_errors[] = $this->add_settings_error( 'Success. Imported backup options.', 'updated' );
      }

      // reset all options
      if ( isset( $request['resetall'] ) ) {
        $add_errors[] = $this->add_settings_error( 'Default options restored.', 'updated' );
        return;
      }

      // restore section
      if ( isset( $request['restore'] ) && ! empty( $section_id ) ) {
        foreach ( $this->sections as $value ) {
          if( $this->unique .'_'. $value['name'] == $section_id ) {
            foreach ( $value['fields'] as $field ) {
              if( isset( $field['id'] ) ) {
                if( isset( $field['default'] ) ) {
                  $request[$field['id']] = $field['default'];
                } else {
                  unset( $request[$field['id']] );
                }
              }
            }
          }
        }
        $add_errors[] = $this->add_settings_error( 'Default options restored for only this section.', 'updated' );
      }

      // sanitize and validate
      foreach( $this->fields as $field ) {

        if( ! empty( $field['id'] ) ) {

          // sanitize
          if( ! empty( $field['sanitize'] ) ) {

            $sanitize = $field['sanitize'];

            if( function_exists( $sanitize ) ) {

              $value_sanitize = isset( $request[$field['id']] ) ? $request[$field['id']] : '';
              $request[$field['id']] = call_user_func( $sanitize, $value_sanitize );

            }

          }

          // validate
          if( ! empty( $field['validate'] ) ) {

            $validate = $field['validate'];

            if( function_exists( $validate ) ) {

              $value_validate = isset( $request[$field['id']] ) ? $request[$field['id']] : '';
              $has_validated  = call_user_func( $validate, array( 'value' => $value_validate, 'field' => $field ) );

              if( ! empty( $has_validated ) ) {
                $add_errors[] = $this->add_settings_error( $has_validated, 'error', $field['id'] );
                $request[$field['id']] = ( isset( $this->db_option[$field['id']] ) ) ? $this->db_option[$field['id']] : '';
              }

            }

          }

          // auto sanitize
          if( ! isset( $request[$field['id']] ) || is_null( $request[$field['id']] ) ) {
            $request[$field['id']] = '';
          }

        }

      }

      $request = apply_filters( 'codevz/save/validate', $request );

      do_action( 'codevz/save/validate/after', $request );

      // set transient
      $request['_transient']['expires'] = round( microtime(true) );
      $request['_transient']['errors']  = $add_errors;
      $request['_transient']['section'] = $section_id;

      return $request;

    }

    // field callback
    public function field_callback( $field ) {

      $value = ( isset( $field['id'] ) && isset( $this->db_option[$field['id']] ) ) ? $this->db_option[$field['id']] : '';
      
      $field[ 'echo' ] = true;

      codevz_add_field( $field, $value, $this->unique, 'options' );

    }

    // wp api: settings sections
    public function do_settings_sections( $page ) {

      global $wp_settings_sections, $wp_settings_fields;

      if ( ! isset( $wp_settings_sections[$page] ) ){
        return;
      }

      foreach ( $wp_settings_sections[$page] as $section ) {

        if ( $section['callback'] ){
          call_user_func( $section['callback'], $section );
        }

        if ( ! isset( $wp_settings_fields ) || !isset( $wp_settings_fields[$page] ) || !isset( $wp_settings_fields[$page][$section['id']] ) ){
          continue;
        }

        $this->do_settings_fields( $page, $section['id'] );

      }

    }

    // wp api: settings fields
    public function do_settings_fields( $page, $section ) {

      global $wp_settings_fields;

      if ( ! isset( $wp_settings_fields[$page][$section] ) ) {
        return;
      }

      foreach ( $wp_settings_fields[$page][$section] as $field ) {
        call_user_func($field['callback'], $field['args']);
      }

    }


    // custom add settings error
    public function add_settings_error( $message, $type = 'error', $id = 'global' ) {
      return array( 'setting' => 'codevz-errors', 'code' => $id, 'message' => $message, 'type' => $type );
    }

    // custom transient
    public function transient( $option = '', $default = '' ) {
      return ( ! empty( $this->db_option['_transient'][$option] ) ) ? $this->db_option['_transient'][$option] : $default;
    }

    // wp api: admin menu
    public function add_admin_menu() {

      $defaults = array(
        'menu_parent'     => '',
        'menu_title'      => '',
        'menu_type'       => '',
        'menu_slug'       => '',
        'menu_icon'       => '',
        'menu_capability' => 'manage_options',
        'menu_position'   => null,
      );

      $args = wp_parse_args( $this->settings, $defaults );

      if( $args['menu_type'] == 'submenu' ) {
        call_user_func( 'add_'. $args['menu_type'] .'_page', $args['menu_parent'], $args['menu_title'], $args['menu_title'], $args['menu_capability'], $args['menu_slug'], array( &$this, 'add_options_html' ) );
      } else {
        call_user_func( 'add_'. $args['menu_type'] .'_page', $args['menu_title'], $args['menu_title'], $args['menu_capability'], $args['menu_slug'], array( &$this, 'add_options_html' ), $args['menu_icon'], $args['menu_position'] );
      }

    }

    public function convert_size( $size ) {
      $unit = strtolower(substr($size, -1));
      $bytes = (int) $size;
      if ( $unit === 'g' ) {
        $bytes *= 1024;
      }
      if ( $unit === 'm' ) {
        $bytes *= 1024;
      }
      if ( $unit === 'k' ) {
        $bytes *= 1024;
      }
      return $bytes;
    }

    // option page html output
    public function add_options_html() {

      $input_vars  = (int) ini_get( 'max_input_vars' );
      $post_size   = (int) $this->convert_size( ini_get( 'post_max_size' ) );

      $warnings = [];

      if ( $input_vars < 5000 ) {
        $warnings[] = sprintf(
          /* translators: %s: current max_input_vars value */
          esc_html__( 'Your server max_input_vars is %s, recommended: %s', 'codevz-plus'),
          '<strong>' . $input_vars . '</strong>',
          '<code>5000-8000</code>'
        );
      }

      if ( $post_size < 8 ) {
        $warnings[] = sprintf(
          /* translators: %s: current post_max_size value */
          esc_html__( 'Your server post_max_size is %s, recommended: %s', 'codevz-plus'),
          '<strong>' . ini_get( 'post_max_size' ) . '</strong>',
          '<code>8M</code>'
        );
      }

      if ( isset( $warnings[0] ) ) {
        $warnings[] = esc_html__('This is very important, Before saving theme options we recommend increase your server max_input_vars and post_max_size to prevent any future problems.', 'codevz-plus');

        echo '<br /><div class="notice notice-warning"><h2>' . esc_html__( '⚠️ Server Limits Warning:', 'codevz-plus' ) . '</h2><ul><li>' .
             implode('</li><br /><li>', $warnings) .
             '</li></ul></div>';

        echo '<style>
          .csf.codevz-options:after {
            position: absolute;
            content: "";
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            z-index: 999;
            background: #f0f0f187
          }
        </style>';
      }

      echo "<script>
        document.addEventListener('DOMContentLoaded', function () {
          const form = document.querySelector('form[action=\"options.php\"]');
          if (!form) {
            return;
          }

          form.addEventListener('submit', function () {
            const inputs = form.querySelectorAll('input, select, textarea');

            inputs.forEach(function (input) {
              if (
                input.type !== 'checkbox' && 
                input.type !== 'radio' && 
                input.value.trim() === ''
              ) {
                input.disabled = true;
              }
            });
          });
        });
      </script>";

      $timenow      = round( microtime(true) );
      $timein       = codevz_timeout( $timenow, $this->transient( 'expires', 0 ), 20 );
      $section_data = $this->transient( 'section' );
      $errors       = $this->transient( 'errors' );
      $has_nav      = ( count( $this->options ) <= 1 ) ? ' codevz-show-all' : '';
      $section_id   = ( ! empty( $section_data ) && $timein ) ? $section_data : $this->unique .'_'. $this->sections[0]['name'];
      $get_sec_id   = Codevz_Plus::_GET( 'codevz-section' );
      $section_id   = $get_sec_id ? $get_sec_id : $section_id;
      $ajax_class   = ( $this->settings['ajax_save'] ) ? ' codevz-save-ajax' : '';
      $sticky_class = ( $this->settings['sticky_header'] ) ? ' codevz-sticky-header' : '';

      do_action( 'codevz/html/options/before' );

      echo '<div class="csf codevz-options">';

        echo '<form method="post" action="options.php" enctype="multipart/form-data" id="CSF_form">';
        echo '<input type="hidden" class="codevz-section-id" name="'. esc_attr( $this->unique ) .'[_transient][section]" value="'. esc_attr( $section_id ) .'">';

        if( $this->settings['ajax_save'] !== true && ! empty( $errors ) && $timein ) {

          global $codevz_framework;

          foreach ( $errors as $error ) {

            if( in_array( $error['setting'], array( 'general', 'codevz-errors' ) ) ) {

              echo '<div class="codevz-settings-error '. esc_attr( $error['type'] ) .'">';
              echo '<p><strong>'. esc_html( $error['message'] ) .'</strong></p>';
              echo '</div>';

            }

            $codevz_framework['errors'] = $errors;

          }

        }

        settings_fields( $this->unique . '_group' );

        echo '<div class="codevz-header'. esc_attr( $sticky_class ) .'">';
        echo '<div class="codevz-header-inner">';

          echo '<div class="codevz-header-left">';
          echo '<h1>'. wp_kses_post( $this->settings['framework_title'] ) .'</h1>';
          echo '</div>';

          echo '<div class="codevz-header-right">';
            if ( $this->settings['show_search'] ) {
              echo '<div class="codevz-search"><input type="text" placeholder="Search option(s)" /></div>';
            }
            echo '<div class="codevz-buttons">';

            if ( empty( $warnings ) ) {
              submit_button( esc_html__( 'Save Theme Options', 'codevz-plus' ), 'primary codevz-save'. esc_attr( $ajax_class ), 'save', false, array( 'data-save' => 'Saving...' ) );
              submit_button( esc_html__( 'Reset options', 'codevz-plus' ), 'secondary codevz-restore codevz-confirm', esc_attr( $this->unique ) .'[restore]', false );
            } else {
              submit_button( esc_html__( 'Save disabled', 'codevz-plus' ), 'primary codevz-save', false, false, array( 'disabled' => 'disabled' ) );
            }

            if( $this->settings['show_reset'] ) {
              submit_button( 'Reset all options', 'secondary codevz-reset codevz-warning-primary codevz-confirm', esc_attr( $this->unique ) .'[resetall]', false );
            }

            echo '</div>';
            echo ( empty( $has_nav ) && $this->settings['show_all_options'] ) ? '<a href="#" class="codevz-expand-all"><i class="fa fa-eye-slash"></i> Show all options</a>' : '';

          echo '</div>';

          echo '<div class="clear"></div>';
          echo '</div>';
        echo '</div>';

        echo '<div class="codevz-wrapper'. esc_attr( $has_nav ) .'">';

          echo '<div class="codevz-nav">';

            echo '<ul>';
            foreach ( $this->options as $key => $tab ) {
              if( ! empty( $tab['sections'] ) ) {

                $tab_active   = codevz_array_search( $tab['sections'], 'name', str_replace( $this->unique.'_', '', $section_id ) );
                $active_list  = ( ! empty( $tab_active ) ) ? ' codevz-tab-active' : '';
                $tab_icon     = ( ! empty( $tab['icon'] ) ) ? '<i class="'. $tab['icon'] .'"></i>' : '';

                echo '<li class="codevz-sub'. esc_attr( $active_list ) .'">';

                  echo '<a href="#" class="codevz-arrow">'. do_shortcode( $tab_icon . $tab['title'] ) .'</a>';

                  echo '<ul>';
                  foreach ( $tab['sections'] as $tab_section ) {

                    $active_tab = ( $section_id == $this->unique .'_'. $tab_section['name'] ) ? 'codevz-section-active' : '';
                    $icon = ( ! empty( $tab_section['icon'] ) ) ? '<i class="'. $tab_section['icon'] .'"></i>' : '';

                    echo '<li><a href="#" class="'. esc_attr( $active_tab ) .'" data-section="'. esc_attr( $this->unique .'_'. $tab_section['name'] ) .'">'. do_shortcode( $icon . $tab_section['title'] ) .'</a></li>';

                  }
                  echo '</ul>';

                echo '</li>';

              } else {

                $icon = ( ! empty( $tab['icon'] ) ) ? '<i class="'. esc_attr( $tab['icon'] ) .'"></i>' : '';

                if( ! empty( $tab['fields'] ) ) {

                  $active_list = ( $section_id == $this->unique .'_'. $tab['name'] ) ? 'codevz-section-active' : '';
                  echo '<li><a href="#" class="'. esc_attr( $active_list ) .'" data-section="'. esc_attr( $this->unique .'_'. $tab['name'] ) .'">'. do_shortcode( $icon . $tab['title'] ) .'</a></li>';

                } else {

                  echo '<li><div class="codevz-seperator">'. do_shortcode( $icon . $tab['title'] ) .'</div></li>';

                }

              }
            }
            echo '</ul>';

          echo '</div>';

          echo '<div class="codevz-content">';

            echo '<div class="codevz-sections">';
            foreach( $this->sections as $section ) {
              if( ! empty( $section['fields'] ) ) {

                $active_content = ( $section_id !== $this->unique .'_'. $section['name'] ) ? 'hidden' : 'codevz-onload';
                echo '<div id="codevz-tab-'. esc_attr( $this->unique .'_'. $section['name'] ) .'" class="codevz-section '. esc_attr( $active_content ) .'">';
                echo ( ! empty( $section['title'] ) && empty( $has_nav ) ) ? '<div class="codevz-section-title"><h3>'. esc_html( $section['title'] ) .'</h3></div>' : '';
                $this->do_settings_sections( esc_attr( $this->unique .'_'. $section['name'] ) . '_section_group' );
                echo '</div>';

              }
            }

            echo '</div>';

            echo '<div class="clear"></div>';

          echo '</div>';

          echo '<div class="codevz-nav-background"></div>';

        echo '</div>';

        if( $this->settings['show_footer'] ) {
          echo '<div class="codevz-footer">';
          echo '<div class="codevz-buttons">';

          if ( empty( $warnings ) ) {
            submit_button( esc_html__( 'Save Theme Options', 'codevz-plus' ), 'primary codevz-save'. esc_attr( $ajax_class ), 'save', false, array( 'data-save' => 'Saving...' ) );
            submit_button( esc_html__( 'Reset options', 'codevz-plus' ), 'secondary codevz-restore codevz-confirm', esc_attr( $this->unique ) .'[restore]', false );
          } else {
            submit_button( esc_html__( 'Save disabled', 'codevz-plus' ), 'primary codevz-save', false, false, array( 'disabled' => 'disabled' ) );
          }

          if( $this->settings['show_reset'] ) {
            submit_button( 'Reset all options', 'secondary codevz-reset codevz-warning-primary codevz-confirm', esc_attr( $this->unique ) .'[resetall]', false );
          }

          echo '</div>';
          echo '<div class="codevz-copyright">© ' . do_shortcode( '[codevz_year]' ) . ' Theme options powered by Codevz</div>';
          echo '<div class="clear"></div>';
          echo '</div>';
        }

        echo '</form>';

        echo '<div class="clear"></div>';

      echo '</div>';

      do_action( 'codevz/html/options/after' );

    }

  }
}
