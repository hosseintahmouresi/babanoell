<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
/**
 *
 * Add framework element
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if ( ! function_exists( 'codevz_add_field' ) ) {
  function codevz_add_field( $field = array(), $value = '', $unique = '', $where = '' ) {

    // Check for unallow fields
    if( ! empty( $field['_notice'] ) ) {

      $field_type = $field['type'];

      $field            = array();
      /* translators: 1. field type name */
      $field['content'] = 'Ooops! This field type (<strong>'. $field_type .'</strong>) can not be used here, yet.';
      $field['type']    = 'notice';
      $field['class']   = 'warning';

    }

    $output     = '';
    $depend     = '';
    $sub        = ( ! empty( $field['sub'] ) ) ? 'sub-': '';
    $unique     = ( ! empty( $unique ) ) ? $unique : '';
    $languages  = codevz_language_defaults();
    $class      = 'Codevz_Field_' . $field['type'];
    $wrap_class = ( ! empty( $field['wrap_class'] ) ) ? ' ' . $field['wrap_class'] : '';
    $el_class   = ( ! empty( $field['title'] ) ) ? sanitize_title( $field['title'] ) : 'no-title';
    $hidden     = ( ! empty( $field['show_only_language'] ) && ( $field['show_only_language'] != $languages['current'] ) ) ? ' hidden' : '';
    $is_pseudo  = ( ! empty( $field['pseudo'] ) ) ? ' codevz-pseudo-field' : '';

    if ( ! empty( $field['dependency'] ) ) {
      $hidden  = ' hidden';
      $depend .= ' data-'. $sub .'controller="'. $field['dependency'][0] .'"';
      $depend .= ' data-'. $sub .'condition="'. $field['dependency'][1] .'"';
      $depend .= ' data-'. $sub .'value="'. $field['dependency'][2] .'"';
    }

    $output .= '<div class="codevz-field codevz-field-key-'. $el_class .' codevz-field-'. $field['type'] . $is_pseudo . $wrap_class . $hidden .'"'. $depend .'>';

    if( ! empty( $field['title'] ) ) {
      $field_desc = ( ! empty( $field['desc'] ) ) ? '<p class="codevz-text-desc">'. $field['desc'] .'</p>' : '';
      $help = ( isset( $field['help'] ) ) ? '<span class="codevz-help" data-title="'. $field['help'] .'"><span class="fa fa-question-circle"></span></span>' : '';
      $output .= '<div class="codevz-title"><h4>' . $field['title'] . $help . '</h4>'. $field_desc .'</div>';
    }

    if( ! empty( $field['before'] ) ) {
      $output .= $field['before'];
    }

    $output .= ( ! empty( $field['title'] ) ) ? '<div class="codevz-fieldset">' : '';

    $value   = ( ! isset( $value ) && isset( $field['default'] ) ) ? $field['default'] : $value;
    $value   = ( isset( $field['value'] ) ) ? $field['value'] : $value;

    if( class_exists( $class ) ) {
      ob_start();
      $element = new $class( $field, $value, $unique, $where );
      $element->output();
      $output .= ob_get_clean();
    } else {
      $output .= '<p>This field class is not available!</p>';
    }

    $output .= ( ! empty( $field['title'] ) ) ? '</div>' : '';

    if( ! empty( $field['after'] ) ) {
      $output .= $field['after'];
    }

    $output .= '<div class="clear"></div>';
    $output .= '</div>';

    if ( isset( $field[ 'echo' ] ) ) {
      echo $output;
    } else {
      return $output;
    }
  }
}

/**
 *
 * Encode string for backup options
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if ( ! function_exists( 'codevz_encode_string' ) ) {
  function codevz_encode_string( $string ) {
    return rtrim( strtr( call_user_func( 'base'. '64' .'_encode', addslashes( gzcompress( serialize( $string ), 9 ) ) ), '+/', '-_' ), '=' );
  }
}

/**
 *
 * Decode string for backup options
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if ( ! function_exists( 'codevz_decode_string' ) ) {
  function codevz_decode_string( $string ) {
    return unserialize( gzuncompress( stripslashes( call_user_func( 'base'. '64' .'_decode', rtrim( strtr( $string, '-_', '+/' ), '=' ) ) ) ) );
  }
}

/**
 *
 * Get google font from json file
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if ( ! function_exists( 'codevz_get_google_fonts' ) ) {
  function codevz_get_google_fonts() {

    global $codevz_google_fonts;

    if( ! empty( $codevz_google_fonts ) ) {

      return $codevz_google_fonts;

    } else {

      ob_start();
      Codevz_Framework::locate_template( 'fields/typography/google-fonts.json' );
      $json = ob_get_clean();

      $codevz_google_fonts = json_decode( $json );

      return $codevz_google_fonts;
    }

  }
}

/**
 *
 * Get icon fonts from json file
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if ( ! function_exists( 'codevz_get_icon_fonts' ) ) {
  function codevz_get_icon_fonts( $file, $array = false ) {

    ob_start();
    load_template( $file, true );
    $json = ob_get_clean();

    return json_decode( $json, $array );

  }
}

/**
 *
 * Array search key & value
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if ( ! function_exists( 'codevz_array_search' ) ) {
  function codevz_array_search( $array, $key, $value ) {

    $results = array();

    if ( is_array( $array ) ) {
      if ( isset( $array[$key] ) && $array[$key] == $value ) {
        $results[] = $array;
      }

      foreach ( $array as $sub_array ) {
        $results = array_merge( $results, codevz_array_search( $sub_array, $key, $value ) );
      }

    }

    return $results;

  }
}

/**
 *
 * Between Microtime
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if ( ! function_exists( 'codevz_timeout' ) ) {
  function codevz_timeout( $timenow, $starttime, $timeout = 30 ) {

    return ( ( $timenow - $starttime ) < $timeout ) ? true : false;

  }
}

/**
 *
 * Get language defaults
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if ( ! function_exists( 'codevz_language_defaults' ) ) {
  function codevz_language_defaults() {

    $multilang = array();

    if( class_exists( 'SitePress' ) || class_exists( 'Polylang' ) || function_exists( 'qtrans_getSortedLanguages' ) ) {

      if( class_exists( 'SitePress' ) ) {

        global $sitepress;
        $multilang['default']   = $sitepress->get_default_language();
        $multilang['current']   = $sitepress->get_current_language();
        $multilang['languages'] = $sitepress->get_active_languages();

      } else if( class_exists( 'Polylang' ) ) {

        global $polylang;
        $current    = pll_current_language();
        $default    = pll_default_language();
        $current    = ( empty( $current ) ) ? $default : $current;
        $poly_langs = $polylang->model->get_languages_list();
        $languages  = array();

        foreach ( $poly_langs as $p_lang ) {
          $languages[$p_lang->slug] = $p_lang->slug;
        }

        $multilang['default']   = $default;
        $multilang['current']   = $current;
        $multilang['languages'] = $languages;

      } else if( function_exists( 'qtrans_getSortedLanguages' ) ) {

        global $q_config;
        $multilang['default']   = $q_config['default_language'];
        $multilang['current']   = $q_config['language'];
        $multilang['languages'] = array_flip( qtrans_getSortedLanguages() );

      }

    }

    $multilang = apply_filters( 'codevz/language/defaults', $multilang );

    return ( ! empty( $multilang ) ) ? $multilang : false;

  }
}
