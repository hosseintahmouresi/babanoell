<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
/**
 *
 * Field: Switcher
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if( ! class_exists( 'Codevz_Field_switcher' ) ) {
  class Codevz_Field_switcher extends Codevz_Fields {

    public function __construct( $field, $value = '', $unique = '', $where = '' ) {
      parent::__construct( $field, $value, $unique, $where );
    }

    public function output() {

      $label = ( isset( $this->field['label'] ) ) ? '<div class="codevz-text-desc">'. $this->field['label'] . '</div>' : '';
      echo '<label><input type="checkbox" name="'. esc_attr( $this->element_name() ) .'" value="1"'. wp_kses_post( (string) $this->element_class() . $this->element_attributes() . checked( $this->element_value(), 1, false ) ) .'/><em data-on="'. esc_html__( 'on', 'codevz-plus' ) .'" data-off="'. esc_html__( 'off', 'codevz-plus' ) .'"></em><span></span></label>' . wp_kses_post( (string) $label );

    }

  }
}
