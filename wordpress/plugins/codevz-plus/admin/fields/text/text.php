<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
/**
 *
 * Field: Text
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if( ! class_exists( 'Codevz_Field_text' ) ) {
  class Codevz_Field_text extends Codevz_Fields {

    public function __construct( $field, $value = '', $unique = '', $where = '' ) {
      parent::__construct( $field, $value, $unique, $where );
    }

    public function output(){

      echo '<input type="'. esc_attr( $this->element_type() ) .'" name="'. esc_attr( $this->element_name() ) .'" value="'. esc_html( str_replace( '"', "'", $this->element_value() ) ) .'"'. wp_kses_post( (string) $this->element_class() . $this->element_attributes() ) .'/>';

    }

  }
}
