<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
/**
 * EDITED BY CODEVZ
 * Field: Background
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if( ! class_exists( 'Codevz_Field_background' ) ) {
  class Codevz_Field_background extends Codevz_Fields {

    public function __construct( $field, $value = '', $unique = '', $where = '' ) {
      parent::__construct( $field, $value, $unique, $where );
    }

    public function output() {

      $value_defaults = array(
        'image'       => '',
        'repeat'      => '',
        'position'    => '',
        'attachment'  => '',
        'size'        => '',
        'color'       => '',
        'color2'      => '',
        'color3'      => '',
        'orientation' => '90deg',
      );

      $this->value  = wp_parse_args( $this->element_value(), $value_defaults );

      if( isset( $this->field['settings'] ) ) { extract( $this->field['settings'] ); }

      $upload_type  = ( isset( $upload_type  ) ) ? $upload_type  : 'image';
      $button_title = ( isset( $button_title ) ) ? $button_title : esc_html__( 'Upload', 'codevz-plus' );
      $frame_title  = ( isset( $frame_title  ) ) ? $frame_title  : esc_html__( 'Upload', 'codevz-plus' );
      $insert_title = ( isset( $insert_title ) ) ? $insert_title : esc_html__( 'Select', 'codevz-plus' );
      $wrap_class   = ( isset( $this->field['wrap_class'] ) ) ? $this->field['wrap_class'] : '';

      echo '<div class="clr mb10">';

      echo '<div class="col s6"><div class="codevz-field">';
      echo '<div class="codevz-title"><h4>' . esc_html__( 'Color(s)', 'codevz-plus' ) . '</h4></div>';
      echo '<div class="col s3 col_first">';
      codevz_add_field( array(
          'echo'        => true,
          'wrap_class'  => $wrap_class,
          'pseudo'      => true,
          'id'          => $this->field['id'].'_color',
          'type'        => 'color_picker',
          'name'        => $this->element_name('[color]'),
          'attributes'  => array(
            'data-atts' => 'bgcolor',
          ),
          'default'     => ( isset( $this->field['default']['color'] ) ) ? $this->field['default']['color'] : '',
          'rgba'        => ( isset( $this->field['rgba'] ) && $this->field['rgba'] === false ) ? false : '',
      ), $this->value['color'], '', 'field/background' );
      echo '</div>';

      echo '<div class="col s3">';
      codevz_add_field( array(
          'echo'        => true,
          'wrap_class'  => $wrap_class,
          'pseudo'      => true,
          'id'          => $this->field['id'].'_color2',
          'type'        => 'color_picker',
          'name'        => $this->element_name('[color2]'),
          'attributes'  => array(
            'data-atts' => 'bgcolor',
          ),
          'default'     => ( isset( $this->field['default']['color2'] ) ) ? $this->field['default']['color2'] : '',
          'rgba'        => ( isset( $this->field['rgba'] ) && $this->field['rgba'] === false ) ? false : '',
          'dependency'  => array( 'background_color', '!=', '' ),
      ), $this->value['color2'], '', 'field/background' );
      echo '</div>';

      echo '<div class="col s3">';
      codevz_add_field( array(
          'echo'        => true,
          'wrap_class'  => $wrap_class,
          'pseudo'      => true,
          'id'          => $this->field['id'].'_color3',
          'type'        => 'color_picker',
          'name'        => $this->element_name('[color3]'),
          'attributes'  => array(
            'data-atts' => 'bgcolor',
          ),
          'default'     => ( isset( $this->field['default']['color3'] ) ) ? $this->field['default']['color3'] : '',
          'rgba'        => ( isset( $this->field['rgba'] ) && $this->field['rgba'] === false ) ? false : '',
          'dependency'  => array( 'background_color', '!=', '' ),
      ), $this->value['color3'], '', 'field/background' );
      echo '</div>';
      echo '</div></div>';

      echo '<div class="col s6"><div class="codevz-field codevz-field-upload codevz-pseudo-field '.  esc_attr( $wrap_class ) .'">';
      echo '<div class="codevz-title"><h4>' . esc_html__( 'Image', 'codevz-plus' ) . '<img src="#" class="sk_preview_image_inner codevz-field-cz_sk_hidden"></h4></div>';
      echo '<div class="codevz-fieldset">';
      echo '<div class="codevz-table-cell"><input type="text" name="'. esc_attr( $this->element_name( '[image]' ) ) .'" value="'. esc_attr( $this->value['image'] ) .'"'. wp_kses_post( (string) $this->element_class() . $this->element_attributes() ) .'/></div>';
      echo '<div class="codevz-table-cell"><a href="#" class="button codevz-button" data-title="' . esc_html__( 'Media library', 'codevz-plus' ) . '" data-frame-title="'. esc_attr( $frame_title ) .'" data-upload-type="'. esc_attr( $upload_type ) .'" data-insert-title="'. esc_attr( $insert_title ) .'"><i class="fas fa-photo-film"></i></a></div>';
      echo '</div></div></div>';

      echo '</div>';

      echo '<div class="clr cz_bg_advanced" style="display:none">';

      echo '<div class="clr cz_hr"></div>';

      // background attributes
      echo '<div class="codevz-plus-sk-adv-flex">';

      echo '<div data-title="Background Type">';
      codevz_add_field( array(
          'echo'        => true,
          'wrap_class'  => $wrap_class,
          'pseudo'      => true,
          'type'        => 'select',
          'title'       => '<i>Type</i>',
          'name'        => $this->element_name( '[layer]' ),
          'options'     => array(
            ''  => '~',
            '1' => esc_html__( 'Image on color', 'codevz-plus' ),
          ),
          'attributes'  => array(
            'data-atts' => 'repeat',
          ),
          'dependency' => array( 'background', '!=', '' ),
      ), $this->value['repeat'], '', 'field/background' );
      echo '</div>';

      echo '<div data-title="Background Repeat">';
      codevz_add_field( array(
          'echo'        => true,
          'wrap_class'  => $wrap_class,
          'pseudo'      => true,
          'type'        => 'select',
          'title'       => '<i>Repeat</i>',
          'name'        => $this->element_name( '[repeat]' ),
          'options'     => array(
            ''          => '~',
            'repeat'    => 'repeat',
            'repeat-x'  => 'repeat-x',
            'repeat-y'  => 'repeat-y',
            'no-repeat' => 'no-repeat',
            'inherit'   => 'inherit',
          ),
          'attributes'  => array(
            'data-atts' => 'repeat',
          ),
          'dependency' => array( 'background', '!=', '' ),
      ), $this->value['repeat'], '', 'field/background' );
      echo '</div>';

      echo '<div data-title="Background Position">';
      codevz_add_field( array(
          'echo'            => true,
          'wrap_class'      => $wrap_class,
          'pseudo'          => true,
          'type'            => 'select',
          'title'           => '<i>Position</i>',
          'name'            => $this->element_name( '[position]' ),
          'options'         => array(
            ''              => '~',
            'left top'      => 'left top',
            'left center'   => 'left center',
            'left bottom'   => 'left bottom',
            'right top'     => 'right top',
            'right center'  => 'right center',
            'right bottom'  => 'right bottom',
            'center top'    => 'center top',
            'center center' => 'center center',
            'center bottom' => 'center bottom'
          ),
          'attributes'      => array(
            'data-atts'     => 'position',
          ),
          'dependency'  => array( 'background', '!=', '' ),
      ), $this->value['position'], '', 'field/background' );
      echo '</div>';

      echo '<div data-title="Background Mode">';
      codevz_add_field( array(
          'echo'        => true,
          'wrap_class'  => $wrap_class,
          'pseudo'      => true,
          'type'        => 'select',
          'title'       => '<i>Mode</i>',
          'name'        => $this->element_name( '[attachment]' ),
          'options'     => array(
            ''          => '~',
            'scroll'    => 'scroll',
            'fixed'     => 'fixed',
          ),
          'attributes'  => array(
            'data-atts' => 'attachment',
          ),
          'dependency'  => array( 'background', '!=', '' ),
      ), $this->value['attachment'], '', 'field/background' );
      echo '</div>';

      echo '<div data-title="Background Size">';
      codevz_add_field( array(
          'echo'        => true,
          'wrap_class'  => $wrap_class,
          'pseudo'      => true,
          'type'        => 'select',
          'title'       => '<i>Size</i>',
          'name'        => $this->element_name( '[size]' ),
          'options'     => array(
            ''            => '~',
            'cover'       => 'cover',
            'contain'     => 'contain',
            'inherit'     => 'inherit',
            'initial'     => 'initial',
          ),
          'attributes'  => array(
            'data-atts' => 'size',
          ),
          'dependency'  => array( 'background', '!=', '' ),
      ), $this->value['size'], '', 'field/background' );
      echo '</div>';

      echo '<div data-title="Background Orientation">';
      codevz_add_field( array(
          'echo'        => true,
          'wrap_class'  => $wrap_class,
          'pseudo'      => true,
          'id'          => $this->field['id'].'_orientation',
          'type'        => 'slider',
          'title'       => '<i>Angle</i>',
          'name'        => $this->element_name('[orientation]'),
          'options'     => array( 'unit' => 'deg', 'step' => 1, 'min' => 0, 'max' => 360 ),
          'default'     => '90deg',
          'rgba'        => ( isset( $this->field['rgba'] ) && $this->field['rgba'] === false ) ? false : '',
          'dependency'  => array( 'background_color', '!=', '' ),
      ), $this->value['orientation'], '', 'field/background' );
      echo '</div>';

      echo '</div></div>';

      echo '<a class="button cz_advance_bg" href="#">' . esc_html__( 'Advanced', 'codevz-plus' ) . '<i class="fas fa-angle-down"></i></a>';

    }
  }
}
