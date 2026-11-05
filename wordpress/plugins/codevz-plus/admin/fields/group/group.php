<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
/**
 *
 * Field: Group
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if( ! class_exists( 'Codevz_Field_group' ) ) {
  class Codevz_Field_group extends Codevz_Fields {

    public function __construct( $field, $value = '', $unique = '', $where = '' ) {
      parent::__construct( $field, $value, $unique, $where );
    }

    public function output() {

      $unallows    = array( 'wysiwyg', 'group', 'repeater' );
      $limit       = ( ! empty( $this->field['limit'] ) ) ? $this->field['limit'] : 0;
      $fields      = array_values( $this->field['fields'] );
      $acc_title   = ( isset( $this->field['accordion_title'] ) ) ? $this->field['accordion_title'] : esc_html__( 'Adding', 'codevz-plus' );
      $field_title = ( isset( $fields[0]['title'] ) ) ? $fields[0]['title'] : $fields[1]['title'];
      $field_id    = ( isset( $fields[0]['id'] ) ) ? $fields[0]['id'] : $fields[1]['id'];
      $unique_id   = ( ! empty( $this->unique ) ) ? $this->unique : $this->field['id'];
      $search_id   = codevz_array_search( $fields, 'id', $acc_title );

      if( ! empty( $search_id ) ) {
        $acc_title = ( isset( $search_id[0]['title'] ) ) ? $search_id[0]['title'] : $acc_title;
        $field_id  = ( isset( $search_id[0]['id'] ) ) ? $search_id[0]['id'] : $field_id;
      }

      echo '<div class="codevz-cloneable-item codevz-cloneable-hidden codevz-no-script">';

      echo '<div class="codevz-cloneable-helper">';
      echo '<i class="codevz-cloneable-pending fa fa-circle" title="' . esc_html__( 'Pending', 'codevz-plus' ) . '"></i>';
      echo '<i class="codevz-cloneable-clone fa fa-clone" title="' . esc_html__( 'Clone', 'codevz-plus' ) . '"></i>';
      echo '<i class="codevz-cloneable-remove fa fa-times" title="' . esc_html__( 'Remove', 'codevz-plus' ) . '"></i>';
      echo '</div>';

        echo '<h4 class="codevz-cloneable-title"><span class="codevz-cloneable-text">'. wp_kses_post( (string) $acc_title ) .'</span></h4>';
        echo '<div class="codevz-cloneable-content">';
        foreach ( $fields as $field ) {

          if( in_array( $field['type'], $unallows ) ) { $field['_notice'] = true; }

          $field['sub'] = true;
          $field['wrap_class'] = ( ! empty( $field['wrap_class'] ) ) ? $field['wrap_class'] .' codevz-no-script' : 'codevz-no-script';

          $unique = ( ! empty( $this->unique ) ) ? '_nonce['. $this->field['id'] .'][num]' : '_nonce[num]';
          $field_default = ( isset( $field['default'] ) ) ? $field['default'] : '';

          $field['echo'] = true;

          codevz_add_field( $field, $field_default, $unique, 'field/group' );

        }

        echo '</div>';

      echo '</div>';

      echo '<div class="codevz-cloneable-wrapper">';

        if( ! empty( $this->value ) ) {

          // CODEVZ FIX
          $this->value = json_decode( wp_json_encode( $this->value ), true );

          $num = 0;

          foreach( $this->value as $key => $value ) {

            $title = ( isset( $this->value[$key][$field_id] ) ) ? $this->value[$key][$field_id] : '';

            if ( is_array( $title ) && isset( $this->multilang ) ) {
              $lang  = codevz_language_defaults();
              $title = $title[$lang['current']];
              $title = is_array( $title ) ? $title[0] : $title;
            }

            $field_title = ( ! empty( $search_id ) ) ? $acc_title : $field_title;

            echo '<div class="codevz-cloneable-item">';

            echo '<div class="codevz-cloneable-helper">';
            echo '<i class="codevz-cloneable-pending fa fa-circle" title="' . esc_html__( 'Pending', 'codevz-plus' ) . '"></i>';
            echo '<i class="codevz-cloneable-clone fa fa-clone" title="' . esc_html__( 'Clone', 'codevz-plus' ) . '"></i>';
            echo '<i class="codevz-cloneable-remove fa fa-times" title="' . esc_html__( 'Remove', 'codevz-plus' ) . '"></i>';
            echo '</div>';

            $acc_title = ucwords( $title );

            echo '<h4 class="codevz-cloneable-title"><span class="codevz-cloneable-text">'. wp_kses_post( (string) $acc_title ) .'</span></h4>';

            echo '<div class="codevz-cloneable-content">';

            foreach ( $fields as $field ) {

              if( in_array( $field['type'], $unallows ) ) { $field['_notice'] = true; }

              $field['sub'] = true;
              $field['wrap_class'] = ( ! empty( $field['wrap_class'] ) ) ? $field['wrap_class'] .' codevz-no-script' : 'codevz-no-script';

              $unique = ( ! empty( $this->unique ) ) ? $this->unique .'['. $this->field['id'] .']['. $num .']' : $this->field['id'] .'['. $num .']';
              $value  = ( isset( $field['id'] ) && isset( $this->value[$key][$field['id']] ) ) ? $this->value[$key][$field['id']] : '';

              $field['echo'] = true;

              codevz_add_field( $field, $value, $unique, 'field/group' );
            }

            echo '</div>';
            echo '</div>';

            $num++;

          }

        }

      echo '</div>';

      echo '<div class="codevz-cloneable-data" data-unique-id="'. esc_attr( $unique_id ) .'" data-limit="'. esc_attr( $limit ) .'">'. esc_html__( 'You can not add more than', 'codevz-plus' ) .' '. esc_html( $limit ) .'</div>';

      echo '<a href="#" class="button button-primary codevz-cloneable-add">'. wp_kses_post( (string) $this->field['button_title'] ) .'</a>';

    }

  }
}
