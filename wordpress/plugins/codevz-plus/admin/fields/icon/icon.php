<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
/**
 *
 * Field: Icon
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if( ! class_exists( 'Codevz_Field_icon' ) ) {
  class Codevz_Field_icon extends Codevz_Fields {

    public function __construct( $field, $value = '', $unique = '', $where = '' ) {
      parent::__construct( $field, $value, $unique, $where );
    }

    public function output() {

      $value  = $this->element_value();
      $hidden = ( empty( $value ) ) ? ' hidden' : '';

      // CODEVZ: Edit add / remove icons
      echo '<div class="codevz-icon-select">';
      echo '<span class="codevz-icon-preview'. esc_attr( $hidden ) .'"><i class="'. esc_attr( $value ) .'"></i></span>';
      echo '<a href="#" class="button button-primary codevz-icon-add">'. esc_html__( 'Choose', 'codevz-plus' ) .'</a>';
      echo '<a href="#" class="button codevz-warning-primary codevz-icon-remove'. esc_attr( $hidden ) .'"><span class="fa fa-remove"></span></a>';
      echo '<input type="text" name="'. esc_attr( $this->element_name() ) .'" value="'. esc_attr( $value ) .'"'. wp_kses_post( (string) $this->element_class( 'codevz-icon-value' ) . $this->element_attributes() ) .' />';
      echo '</div>';

    }

  }
}
