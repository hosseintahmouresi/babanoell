<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
/**
 *
 * Field: Image Select
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if( ! class_exists( 'Codevz_Field_image_select' ) ) {
  class Codevz_Field_image_select extends Codevz_Fields {

    public function __construct( $field, $value = '', $unique = '', $where = '' ) {
      parent::__construct( $field, $value, $unique, $where );
    }

    public function output() {

      $input_type  = ( ! empty( $this->field['radio'] ) ) ? 'radio' : 'checkbox';
      $input_attr  = ( ! empty( $this->field['multi_select'] ) ) ? '[]' : '';

      echo empty( $input_attr ) ? '<div class="codevz-field-image-selector">' : '';

      if( isset( $this->field['options'] ) ) {
        $options  = $this->field['options'];

        $class = '';

        foreach ( $options as $key => $value ) {

          if ( ! $key ) {

            continue;

          } else if ( $key === 'pro' ) {

            $class = 'xtra-image-select-pro';

          } else {

            echo '<label class="' . esc_attr( $class ) . '"><input' . ( esc_attr( $class ) ? 'disabled="disabled"' : '' ) . ' type="'. esc_attr( $input_type ) .'" name="'. esc_attr( $this->element_name( $input_attr ) ) .'" value="'. esc_attr( $key ) .'"'. wp_kses_post( (string) $this->element_class() . $this->element_attributes( $key ) . $this->checked( $this->element_value(), $key ) ) .'/><img src="'. esc_url( $value ) .'" alt="'. esc_attr( $key ) .'" /></label>';

          }

        }

      }

      echo empty( $input_attr ) ? '</div>' : '';

    }

  }
}
