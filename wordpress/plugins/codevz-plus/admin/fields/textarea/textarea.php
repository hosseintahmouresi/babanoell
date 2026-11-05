<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
/**
 *
 * Field: Textarea
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if( ! class_exists( 'Codevz_Field_textarea' ) ) {
  class Codevz_Field_textarea extends Codevz_Fields {

    public function __construct( $field, $value = '', $unique = '', $where = '' ) {
      parent::__construct( $field, $value, $unique, $where );
    }

    public function output() {

      echo '<textarea name="'. esc_attr( $this->element_name() ) .'"'. wp_kses_post( (string) $this->element_class() . $this->element_attributes() ) .'>'. esc_textarea( $this->element_value() ) .'</textarea>';

    }

  }
}
