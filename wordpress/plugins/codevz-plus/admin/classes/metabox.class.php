<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
/**
 *
 * Metabox Class
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if( ! class_exists( 'Codevz_Framework_Metabox' ) ) {
  class Codevz_Framework_Metabox extends Codevz_Framework_Abstract {

    /**
     *
     * options
     * @access public
     * @var array
     *
     */
    public $options = array();

    // run metabox construct
    public function __construct( $options ) {

      $this->options  = apply_filters( 'codevz/options/metabox', $options );

      $this->addAction( 'add_meta_boxes', 'add_meta_box' );
      $this->addAction( 'save_post', 'save_meta_box', 999, 2 );

      $this->addEnqueue( $this->options );

    }

    // instance
    public static function instance( $options = array() ) {
      return new self( $options );
    }

    // add metabox
    public function add_meta_box( $post_type ) {

      foreach ( $this->options as $value ) {
        add_meta_box( $value['id'], $value['title'], array( &$this, 'add_meta_box_content' ), $value['post_type'], $value['context'], $value['priority'], $value );
      }

    }

    // add metabox content
    public function add_meta_box_content( $post, $callback ) {

      global $post, $codevz_framework, $typenow;

      wp_nonce_field( 'codevz-metabox', 'codevz-metabox-nonce' );

      $args       = $callback['args'];
      $unique     = $args['id'];
      $sections   = $args['sections'];
      $meta_value = get_post_meta( $post->ID, $unique, true );
      $has_nav    = ( count( $sections ) >= 2 && $args['context'] != 'side' ) ? true : false;
      $show_all   = ( ! $has_nav ) ? ' codevz-show-all' : '';
      $timenow    = round( microtime(true) );
      $errors     = ( isset( $meta_value['_transient']['errors'] ) ) ? $meta_value['_transient']['errors'] : array();
      $section    = ( isset( $meta_value['_transient']['section'] ) ) ? $meta_value['_transient']['section'] : false;
      $expires    = ( isset( $meta_value['_transient']['expires'] ) ) ? $meta_value['_transient']['expires'] : 0;
      $timein     = codevz_timeout( $timenow, $expires, 20 );
      $section_id = ( $timein && $section ) ? $section : '';
      $get_sec_id = Codevz_Plus::_GET( 'codevz-section' );
      $section_id = $get_sec_id ? $get_sec_id : $section_id;

      // add erros
      $codevz_framework['errors'] = ( $timein ) ? $errors : array();

      do_action( 'codevz/html/metabox/before' );

      echo '<div class="csf codevz-metabox">';

        echo '<input type="hidden" name="'. esc_attr( $unique ) .'[_transient][section]" class="codevz-section-id" value="'. esc_attr( $section_id ) .'">';

        echo '<div class="codevz-wrapper'. esc_attr( $show_all ) .'">';

          if( $has_nav ) {

            echo '<div class="codevz-nav">';

              echo '<ul>';
              $num = 0;
              foreach( $sections as $value ) {

                if( ! empty( $value['typenow'] ) && $value['typenow'] !== $typenow ) { continue; }

                $tab_icon = ( ! empty( $value['icon'] ) ) ? '<i class="codevz-icon '. $value['icon'] .'"></i>' : '';

                if( isset( $value['fields'] ) ) {
                  $active_section = ( ( empty( $section_id ) && $num === 0 ) || $section_id == $unique .'_'. $value['name'] ) ? ' class="codevz-section-active"' : '';
                  echo '<li><a href="#"'. do_shortcode( $active_section ) .' data-section="'. esc_attr( $unique .'_'. $value['name'] ) .'">'. do_shortcode( $tab_icon . $value['title'] ) .'</a></li>';
                } else {
                  echo '<li><div class="codevz-seperator">'. do_shortcode( $tab_icon ) . esc_html( isset( $value['title'] ) ? $value['title'] : '' ) .'</div></li>';
                }

                $num++;
              }
              echo '</ul>';

            echo '</div>';

          }

          echo '<div class="codevz-content">';

            echo '<div class="codevz-sections">';
            $num = 0;
            foreach( $sections as $v ) {

              if( ! empty( $v['typenow'] ) && $v['typenow'] !== $typenow ) { continue; }

              if( isset( $v['fields'] ) ) {

                $active_content = ( ( empty( $section_id ) && $num === 0 ) || $section_id === $unique .'_'. $v['name'] ) ? 'codevz-onload' : 'hidden';

                echo '<div id="codevz-tab-'. esc_attr( $unique .'_'. $v['name'] ) .'" class="codevz-section '. esc_attr( $active_content ) .'">';
                echo ( isset( $v['title'] ) ) ? '<div class="codevz-section-title"><h3>'. esc_html( $v['title'] ) .'</h3></div>' : '';

                foreach ( $v['fields'] as $field_key => $field ) {

                  $default    = ( isset( $field['default'] ) ) ? $field['default'] : '';
                  $elem_id    = ( isset( $field['id'] ) ) ? $field['id'] : '';
                  $elem_value = ( is_array( $meta_value ) && isset( $meta_value[$elem_id] ) ) ? $meta_value[$elem_id] : $default;

                  $field[ 'echo' ] = true;

                  codevz_add_field( $field, $elem_value, $unique, 'metabox' );

                }
                echo '</div>';

              }

              $num++;
            }
            echo '</div>';

            echo '<div class="clear"></div>';

            if( ! empty( $args['show_restore'] ) ) {

              echo '<div class=" codevz-metabox-restore">';
              echo '<label>';
              echo '<input type="checkbox" name="'. esc_attr( $unique ) .'[_restore]" />';
              echo '<span class="button codevz-button-restore">Restore</span>';
              echo '<span class="button codevz-button-cancel">Update post for restore</span>';
              echo '</label>';
              echo '</div>';

            }

          echo '</div>';

          if ( $has_nav ) {
            echo '<div class="codevz-nav-background"></div>';
          }

          echo '<div class="clear"></div>';

        echo '</div>';

      echo '</div>';

      do_action( 'codevz/html/metabox/after' );

    }

    // save metabox
    public function save_meta_box( $post_id, $post ) {

      if ( wp_verify_nonce( Codevz_Plus::_POST( 'codevz-metabox-nonce' ), 'codevz-metabox' ) ) {

        $errors = array();
        $post_type = Codevz_Plus::_POST( 'post_type' );

        foreach ( $this->options as $request_value ) {

          if ( in_array( $post_type, (array) $request_value['post_type'] ) ) {

            $request_key = $request_value['id'];
            $request = (array) filter_input( INPUT_POST, $request_key, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

            // ignore _nonce
            if( isset( $request['_nonce'] ) ) {
              unset( $request['_nonce'] );
            }

            // sanitize and validate
            foreach( $request_value['sections'] as $key => $section ) {

              if( ! empty( $section['fields'] ) ) {

                foreach( $section['fields'] as $field ) {

                  if( ! empty( $field['id'] ) ) {

                    // auto sanitize
                    if( ! isset( $request[$field['id']] ) || is_null( $request[$field['id']] ) ) {
                      $request[$field['id']] = '';
                    }

                  }

                }

              }

            }

            $request['_transient']['expires']  = round( microtime(true) );

            if( ! empty( $errors ) ) {
              $request['_transient']['errors'] = $errors;
            }

            $request = apply_filters( 'codevz/save/metabox', $request, $request_key, $post );

            if( empty( $request ) || ! empty( $request['_restore'] ) ) {

              delete_post_meta( $post_id, $request_key );

            } else {

              update_post_meta( $post_id, $request_key, $request );

            }

          }

        }

      }

    }

  }
}
