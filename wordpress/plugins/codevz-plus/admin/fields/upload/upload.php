<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
/**
 *
 * Field: Upload
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if ( ! class_exists( 'Codevz_Field_upload' ) ) {
  class Codevz_Field_upload extends Codevz_Fields {

    public function __construct( $field, $value = '', $unique = '', $where = '' ) {
      parent::__construct( $field, $value, $unique, $where );
    }

    public function output() {

      $value = $this->element_value();

      $upload_type  = ( ! empty( $this->field['settings']['upload_type']  ) ) ? $this->field['settings']['upload_type']  : '';
      $button_title = ( ! empty( $this->field['settings']['button_title'] ) ) ? $this->field['settings']['button_title'] : esc_html__( 'Upload', 'codevz-plus' );
      $frame_title  = ( ! empty( $this->field['settings']['frame_title']  ) ) ? $this->field['settings']['frame_title']  : esc_html__( 'Upload', 'codevz-plus' );
      $insert_title = ( ! empty( $this->field['settings']['insert_title'] ) ) ? $this->field['settings']['insert_title'] : esc_html__( 'Select', 'codevz-plus' );

      if( ! empty( $this->field['preview'] ) ) {

        // CODEVZ
        $image  = ( empty( $value ) ) ? '' : $value;
        $hidden = ( empty( $value ) ) ? ' hidden' : '';

        echo '<div class="codevz-image-preview'. esc_attr( $hidden ) .'">';
        echo '<div class="codevz-image-inner"><i class="fa fa-times codevz-image-remove"></i><img src="'. esc_url( $image ) .'" alt="preview" /></div>';
        echo '</div>';

      }

      echo '<div class="codevz-table">';
      echo '<div class="codevz-table-cell"><input type="text" name="'. esc_attr( $this->element_name() ) .'" value="'. esc_attr( $value ) .'"'. wp_kses_post( (string) $this->element_class() . $this->element_attributes() ) .'/></div>';
      echo '<div class="codevz-table-cell"><a href="#" class="button codevz-button" data-frame-title="'. esc_attr( $frame_title ) .'" data-upload-type="'. esc_attr( $upload_type ) .'" data-insert-title="'. esc_attr( $insert_title ) .'">'. esc_html( $button_title ) .'</a></div>';
      echo '</div>';

    }
  }
}
