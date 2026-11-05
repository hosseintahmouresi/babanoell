<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
/**
 *
 * Field: Wysiwyg
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if( ! class_exists( 'Codevz_Field_Wysiwyg' ) ) {
  class Codevz_Field_Wysiwyg extends Codevz_Fields {

    public function __construct( $field, $value = '', $unique = '', $where = '' ) {
      parent::__construct( $field, $value, $unique, $where );
    }

    public function output() {

      $defaults = array(
        'textarea_rows' => 10,
        'textarea_name' => esc_attr( $this->element_name() )
      );

      $settings    = ( ! empty( $this->field['settings'] ) ) ? $this->field['settings'] : array();
      $settings    = wp_parse_args( $settings, $defaults );

      $field_id    = ( ! empty( $this->field['id'] ) ) ? $this->field['id'] : '';
      $field_value = $this->element_value();

      wp_editor( $field_value, $field_id, $settings );

    }

  }
}
