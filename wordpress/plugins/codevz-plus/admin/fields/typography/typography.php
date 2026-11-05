<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
/**
 *
 * Field: Typography
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if( ! class_exists( 'Codevz_Field_typography' ) ) {
  class Codevz_Field_typography extends Codevz_Fields {

    public function __construct( $field, $value = '', $unique = '', $where = '' ) {
      parent::__construct( $field, $value, $unique, $where );
    }

    public function output() {

      $defaults_value = apply_filters( 'csf/field/fonts/defaults', array(
        'family' => 'Arial',
        'weight' => '',
      ));

      $weights = apply_filters( 'csf/field/fonts/weights', array(
        ''    => 'Default',
        '100' => '100 | Thin',
        '200' => '200 | Extra Light',
        '300' => '300 | Light',
        '400' => '400 | Normal',
        '500' => '500 | Medium',
        '600' => '600 | Semi Bold',
        '700' => '700 | Bold',
        '800' => '800 | Extra Bold',
      ));

      $websafe_fonts = Codevz_Plus::web_safe_fonts();

      $value        = wp_parse_args( $this->element_value(), $defaults_value );
      $family_value = $value['family'];
      $weight_value = $value['weight'];
      $is_weight    = ( isset( $this->field['weight'] ) && $this->field['weight'] === false ) ? false : true;
      $is_chosen    = ( isset( $this->field['chosen'] ) && $this->field['chosen'] === false ) ? '' : 'chosen ';
      $google_json  = codevz_get_google_fonts();
      $chosen_rtl   = ( is_rtl() && ! empty( $is_chosen ) ) ? 'chosen-rtl ' : '';

      if( is_object( $google_json ) ) {

        echo '<label class="csf-typography-family">';
        echo '<select name="'. esc_attr( $this->element_name( '[family]' ) ) .'" class="'. esc_attr( $is_chosen . $chosen_rtl ) .'csf-typo-family" data-atts="family"'. wp_kses_post( (string) $this->element_attributes() ) .'>';

        do_action( 'csf/typography/family', $family_value, $this );

        echo '<optgroup label="Web Safe Fonts">';
        foreach ( $websafe_fonts as $websafe_font => $i ) {

          if ( is_int( $websafe_font ) ) {
            continue;
          }

          echo '<option value="'. esc_attr( $websafe_font ) .'">'. esc_html( $websafe_font ) .'</option>';

        }
        echo '</optgroup>';

        echo '<optgroup label="'. esc_html__( 'Google Fonts', 'codevz-plus' ) .'">';
        foreach ( $google_json->items as $google_font ) {
          echo '<option value="'. esc_attr( $google_font->family ) .'"'. wp_kses_post( (string) selected( $google_font->family, $family_value, true ) ) .'>'. esc_html( $google_font->family ) .'</option>';
        }
        echo '</optgroup>';

        echo '</select>';
        echo '</label>';

        if( ! empty( $is_weight ) ) {

          echo '<label class="csf-typography-weight">';
          echo '<select name="'. esc_attr( $this->element_name( '[weight]' ) ) .'" class="'. esc_attr( $is_chosen . $chosen_rtl ) .'csf-typo-weight" data-atts="weight">';
          foreach ( $weights as $weight_key => $weight ) {
            echo '<option value="'. esc_attr( $weight_key ) .'"'. wp_kses_post( (string) $this->checked( $weight_value, $weight_key, 'selected' ) ) .'>'. esc_html( $weight ) .'</option>';
          }
          echo '</select>';
          echo '</label>';

        }

      } else {

        echo 'Field error: can not load json file.';

      }

    }

  }
}
