<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
/**
 *
 * Field: Gallery
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if( ! class_exists( 'Codevz_Field_Gallery' ) ) {
  class Codevz_Field_Gallery extends Codevz_Fields {

    public function __construct( $field, $value = '', $unique = '', $where = '' ) {
      parent::__construct( $field, $value, $unique, $where );
    }

    public function output(){

      $value  = $this->element_value();
      $add    = ( ! empty( $this->field['add_title'] ) ) ? $this->field['add_title'] : esc_html__( 'Add Gallery', 'codevz-plus' );
      $edit   = ( ! empty( $this->field['edit_title'] ) ) ? $this->field['edit_title'] : esc_html__( 'Edit Gallery', 'codevz-plus' );
      $clear  = ( ! empty( $this->field['clear_title'] ) ) ? $this->field['clear_title'] : esc_html__( 'Clear', 'codevz-plus' );
      $hidden = ( empty( $value ) ) ? ' hidden' : '';

      echo '<ul>';

      if( ! empty( $value ) ) {

        $values = explode( ',', $value );

        foreach ( $values as $id ) {
          $attachment = wp_get_attachment_image_src( $id, 'thumbnail' );
          if ( ! empty( $attachment[0] ) ) {
            echo '<li><img src="'. esc_url( $attachment[0] ) .'" alt="Gallery" /></li>';
          }
        }

      }

      echo '</ul>';
      echo '<a href="#" class="button button-primary codevz-button">'. esc_html( $add ) .'</a>';
      echo '<a href="#" class="button codevz-edit-gallery'. esc_attr( $hidden ) .'">'. esc_html( $edit ) .'</a>';
      echo '<a href="#" class="button codevz-warning-primary codevz-clear-gallery'. esc_attr( $hidden ) .'">'. esc_html( $clear ) .'</a>';
      echo '<input type="text" name="'. esc_attr( $this->element_name() ) .'" value="'. esc_attr( $value ) .'"'. wp_kses_post( (string) $this->element_class() . $this->element_attributes() ) .'/>';

    }

  }
}
