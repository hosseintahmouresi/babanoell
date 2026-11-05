<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
/**
 *
 * Field: Number
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if( ! class_exists( 'Codevz_Field_number' ) ) {
  class Codevz_Field_number extends Codevz_Fields {

    public function __construct( $field, $value = '', $unique = '', $where = '' ) {
      parent::__construct( $field, $value, $unique, $where );
    }

    public function output() {

      echo '<input type="number" name="'. esc_attr( $this->element_name() ) .'" value="'. esc_attr( $this->element_value() ) .'"'. wp_kses_post( (string) $this->element_class() . $this->element_attributes() ) .'/>'. ( isset( $this->field['unit'] ) ? '<em>'. esc_html( $this->field['unit'] ) .'</em>' : '' );

    }

  }
}
