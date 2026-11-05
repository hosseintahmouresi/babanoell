<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
/**
 *
 * Taxonomy Class
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if( ! class_exists( 'Codevz_Framework_Taxonomy' ) ) {
  class Codevz_Framework_Taxonomy extends Codevz_Framework_Abstract {

    /**
     *
     * taxonomy options
     * @access public
     * @var array
     *
     */
    public $options = array();

    // run taxonomy construct
    public function __construct( $options ) {

      $this->options = apply_filters( 'codevz/options/taxonomy', $options );

      $this->addAction( 'admin_init', 'add_taxonomy_fields' );

      $this->addEnqueue( $this->options );

    }

    // instance
    public static function instance( $options = array() ) {
      return new self( $options );
    }

    // add taxonomy add/edit fields
    public function add_taxonomy_fields() {

      $get_taxonomy = Codevz_Plus::_GET( 'taxonomy' );

      foreach ( $this->options as $option ) {

        $opt_taxonomy = $option['taxonomy'];

        if ( $get_taxonomy == $opt_taxonomy ) {

          $this->addAction( $opt_taxonomy .'_add_form_fields', 'render_taxonomy_form_fields' );
          $this->addAction( $opt_taxonomy .'_edit_form', 'render_taxonomy_form_fields' );

        }

        $this->addAction( 'created_'. $opt_taxonomy, 'save_taxonomy', 999, 2 );
        $this->addAction( 'edited_'. $opt_taxonomy, 'save_taxonomy', 999, 2 );
        $this->addAction( 'delete_'. $opt_taxonomy, 'delete_taxonomy', 999, 2 );

      }

    }

    // render taxonomy add/edit form fields
    public function render_taxonomy_form_fields( $term ) {

      global $codevz_framework;

      $value     = '';
      $form_edit = ( is_object( $term ) && isset( $term->taxonomy ) ) ? true : false;
      $taxonomy  = ( $form_edit ) ? $term->taxonomy : $term;
      $classname = ( $form_edit ) ? 'edit' : 'add';

      wp_nonce_field( 'codevz-taxonomy', 'codevz-taxonomy-nonce' );

      do_action( 'codevz/html/taxonomy/before' );

      echo '<div class="csf codevz-taxonomy codevz-taxonomy-'. esc_attr( $classname ) .'-fields codevz-onload">';

        foreach( $this->options as $option ) {

          if( $taxonomy == $option['taxonomy'] ) {

            if( $form_edit ) {

              $value   = get_term_meta( $term->term_id, $option['id'], true );
              $timenow = round( microtime(true) );
              $expires = ( isset( $value['_transient']['expires'] ) ) ? $value['_transient']['expires'] : 0;
              $errors  = ( isset( $value['_transient']['errors'] ) ) ? $value['_transient']['errors'] : array();
              $timein  = codevz_timeout( $timenow, $expires, 30 );

              $codevz_framework['errors'] = ( $timein ) ? $errors : array();

            }

            foreach ( $option['fields'] as $field ) {

              $default    = ( isset( $field['default'] ) ) ? $field['default'] : '';
              $elem_id    = ( isset( $field['id'] ) ) ? $field['id'] : '';
              $elem_value = ( is_array( $value ) && isset( $value[$elem_id] ) ) ? $value[$elem_id] : $default;

              $field[ 'echo' ] = true;

              codevz_add_field( $field, $elem_value, $option['id'], 'taxonomy' );

            }

          }

        }

      echo '</div>';

      do_action( 'codevz/html/taxonomy/after' );

    }

    // save taxonomy form fields
    public function save_taxonomy( $term_id, $tax ) {

      if ( wp_verify_nonce( Codevz_Plus::_POST( 'codevz-taxonomy-nonce' ), 'codevz-taxonomy' ) ) {

        $errors = array();
        $taxonomy = Codevz_Plus::_POST( 'taxonomy' );

        foreach ( $this->options as $request_value ) {

          if( $taxonomy == $request_value['taxonomy'] ) {

            $request_key = $request_value['id'];
            $request = (array) filter_input( INPUT_POST, $request_key, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

            // ignore _nonce
            if( isset( $request['_nonce'] ) ) {
              unset( $request['_nonce'] );
            }

            // sanitize and validate
            if( ! empty( $request_value['fields'] ) ) {

              foreach( $request_value['fields'] as $field ) {

                if( ! empty( $field['id'] ) ) {

                  // auto sanitize
                  if( ! isset( $request[$field['id']] ) || is_null( $request[$field['id']] ) ) {
                    $request[$field['id']] = '';
                  }

                }

              }

            }

            $request['_transient']['expires']  = round( microtime(true) );

            if( ! empty( $errors ) ) {
              $request['_transient']['errors'] = $errors;
            }

            $request = apply_filters( 'codevz/save/taxonomy', $request, $request_key, $term_id );

            if( empty( $request ) ) {

              delete_term_meta( $term_id, $request_key );

            } else {

              update_term_meta( $term_id, $request_key, $request );

            }

          }

        }

        set_transient( 'codevz-taxonomy-transient', $errors, 10 );

      }

    }

    // delete taxonomy
    public function delete_taxonomy( $term_id, $tax ) {

      $taxonomy = Codevz_Plus::_POST( 'taxonomy' );

      if( ! empty( $taxonomy ) ) {

        foreach ( $this->options as $request_value ) {

          if( $taxonomy == $request_value['taxonomy'] ) {

            $request_key = $request_value['id'];

            delete_term_meta( $term_id, $request_key );

          }

        }

      }

    }

  }
}
