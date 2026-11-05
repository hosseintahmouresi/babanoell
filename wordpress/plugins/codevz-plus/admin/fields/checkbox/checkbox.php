<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
/**
 *
 * Field: Checkbox
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if( ! class_exists( 'Codevz_Field_checkbox' ) ) {
  class Codevz_Field_checkbox extends Codevz_Fields {

    public function __construct( $field, $value = '', $unique = '', $where = '' ) {
      parent::__construct( $field, $value, $unique, $where );
    }

    public function output() {

      if( isset( $this->field['options'] ) ) {

        $options = $this->field['options'];
        $options = ( is_array( $options ) ) ? $options : array_filter( $this->element_data( $options ) );

        if( ! empty( $options ) ) {

          echo '<ul>';
          foreach ( $options as $key => $value ) {
            echo '<li><label><input type="checkbox" name="'. esc_attr( $this->element_name( '[]' ) ) .'" value="'. esc_attr( $key ) .'"'. wp_kses_post( (string) $this->element_attributes( $key ) . $this->checked( $this->element_value(), $key ) ) .'/> '.esc_html( $value ).'</label></li>';
          }
          echo '</ul>';
        }

      } else {
        $label = ( isset( $this->field['label'] ) ) ? $this->field['label'] : '';
        echo '<label><input type="checkbox" name="'. esc_attr( $this->element_name() ) .'" value="1"'. wp_kses_post( (string) $this->element_class() . $this->element_attributes() . checked( $this->element_value(), 1, false ) ) .'/> '. esc_html( $label ) .'</label>';
      }

    }

  }
}
