<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Cannot access pages directly.

// Disable preview on quick theme options.
if ( isset( $_SERVER['HTTP_REFERER'] ) && Codevz_Plus::contains( $_SERVER['HTTP_REFERER'], 'codevz_quick_options' ) ) {
      add_action('template_redirect', function() {
      	if ( is_customize_preview() ) {
            echo '<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Custom Preview Disabled</title>
                <style>
                    body {
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        height: 100vh;
                        margin: 0;
                        font-family: "Open Sans", Tahoma, Arial, sans-serif;
                        background-color: #2f2f36
                    }
                    .custom-message {
                        text-align: center;
                        background: white;
                        padding: 50px;
                        border: 1px solid #ccc;
                        border-radius: 6px;
                        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                    }
                    .custom-message h1 {
                        margin-top: 0;
                        font-size: 27px;
                    }
                    .custom-message p {
                        font-size: 18px;
                    		display: table;
                    		opacity: .7;
                    		line-height: 1.4;
                        margin: 10px auto;
                        width: 70%
                    }
                    .custom-message a {
                        display: inline-block;
                        margin-top: 50px;
                        padding: 10px 20px;
                        background-color: #0073aa;
                        color: white;
                        text-decoration: none;
                        border-radius: 5px;
                    }
                </style>
            </head>
            <body>
                <div class="custom-message">
                    <h1>' . esc_html__( 'Preview Mode Disabled', 'codevz-plus' ) . '</h1>
                    <p>' . esc_html__( 'You are in the quick theme options mode without preview for quick access to options', 'codevz-plus' ) . '</p>
                </div>
            </body>
            </html>';
            exit; // Stop further execution of the script
      	}
	});
}

/* Admin assets */
function codevz_plus_admin_enqueue_scripts() {

	// Is our admin pages?
	$allowed = Codevz_Plus::admin_enqueue();

	if ( $allowed ) {

		wp_enqueue_script ( 'jquery-ui-dialog' );
		wp_enqueue_script( 'cz-fields', CODEVZ_FRAMEWORK_URL . '/fields/codevz_fields/codevz_fields.js', [], Codevz_Plus::$ver, true );
		wp_enqueue_style( 'cz-fields', CODEVZ_FRAMEWORK_URL . '/fields/codevz_fields/codevz_fields.css', false, Codevz_Plus::$ver );
		wp_enqueue_style( 'cz-icons-pack', CODEVZ_FRAMEWORK_URL . '/fields/codevz_fields/icons/czicons.css', false, Codevz_Plus::$ver );

		if ( is_rtl() ) {
			wp_enqueue_style( 'cz-fields-rtl', CODEVZ_FRAMEWORK_URL . '/fields/codevz_fields/codevz_fields.rtl.css', false, Codevz_Plus::$ver );
		}

		// Translations.
		wp_localize_script( 'cz-fields', 'sk_aiL10n', array(
			'close' 				=> esc_html__( 'Close', 'codevz-plus' ),
			'normal' 				=> esc_html__( 'Normal', 'codevz-plus' ),
			'focus' 				=> esc_html__( 'Focus', 'codevz-plus' ),
			'hover' 				=> esc_html__( 'Hover', 'codevz-plus' ),
			'styles' 				=> esc_html__( 'Styles', 'codevz-plus' ),
			'desktop' 				=> esc_html__( 'Desktop', 'codevz-plus' ),
			'tablet' 				=> esc_html__( 'Tablet', 'codevz-plus' ),
			'mobile' 				=> esc_html__( 'Mobile', 'codevz-plus' ),
			'copy' 					=> esc_html__( 'Copy', 'codevz-plus' ),
			'copied' 				=> esc_html__( 'Copied', 'codevz-plus' ),
			'paste' 				=> esc_html__( 'Paste', 'codevz-plus' ),
			'advanced' 				=> esc_html__( 'Advanced', 'codevz-plus' ),
			'reset' 				=> esc_html__( 'Reset', 'codevz-plus' ),
			'reset_confirm' 		=> esc_html__( 'Are you sure you want reset this StyleKit?', 'codevz-plus' ),
			'paste_confirm' 		=> esc_html__( 'Are you sure you want paste on this StyleKit?', 'codevz-plus' ),
			'load_more' 			=> esc_html__( 'Load more', 'codevz-plus' ),
			'pro' 					=> esc_html__( 'Activate your theme with purchase code to access this feature.', 'codevz-plus' ),
			'search' 				=> esc_html__( 'Search', 'codevz-plus' ),
			'search_result' 		=> esc_html__( 'Result', 'codevz-plus' ),
			'search_not' 			=> esc_html__( 'Not found any options', 'codevz-plus' ),
			'search_pl' 			=> esc_html__( 'Type a keyword ...', 'codevz-plus' ),
			'custom_header' 		=> esc_html__( 'Custom header is ON', 'codevz-plus' ),
			'custom_footer' 		=> esc_html__( 'Custom footer is ON', 'codevz-plus' ),
		) );

		// RTL mode.
		$rtl 	= [ 'ar', 'fa', 'he', 'ur' ];
		$locale = get_locale();

		if ( in_array( substr( $locale, 0, 2 ), $rtl ) ) {
		    wp_add_inline_script( 'cz-fields', "! function( $ ) {console.log(11); $( 'html, body' ).addClass( 'site-rtl-user-ltr' ); }( jQuery );");
		}

		// Classic options CSS.
		if ( $allowed === 'codevz-theme-options' ) {
			wp_enqueue_style( 'codevz-dashboard-font', 'https://fonts.googleapis.com/css?family=Poppins:400,500,600,700' );
			wp_enqueue_style( 'codevz-plus-classic-theme-options', CODEVZ_FRAMEWORK_URL . '/fields/codevz_fields/classic-theme-options.css', false, Codevz_Plus::$ver );
		}

	}

}
add_action( 'admin_enqueue_scripts', 'codevz_plus_admin_enqueue_scripts', 99999 );
add_action( 'elementor/editor/before_enqueue_scripts', 'codevz_plus_admin_enqueue_scripts', 99999 );

// Frontend styles.
function codevz_plus_wp_enqueue_scripts() {

	if ( is_admin() ) {
		return;
	}

	if ( Codevz_Plus::$vc_editable ) {

		wp_enqueue_style( 'cz-fields', CODEVZ_FRAMEWORK_URL . '/fields/codevz_fields/codevz_fields.css', false, Codevz_Plus::$ver );

		if ( is_rtl() ) {
			wp_enqueue_style( 'cz-fields-rtl', CODEVZ_FRAMEWORK_URL . '/fields/codevz_fields/codevz_fields.rtl.css', false, Codevz_Plus::$ver );
		}

		wp_enqueue_style( 'codevz-wpb', CODEVZ_FRAMEWORK_URL . '/fields/codevz_fields/wpb.frontend.css', false, Codevz_Plus::$ver );

	}

	wp_enqueue_style( 'cz-icons-pack', CODEVZ_FRAMEWORK_URL . '/fields/codevz_fields/icons/czicons.css', false, Codevz_Plus::$ver );

}
add_action( 'wp_enqueue_scripts', 'codevz_plus_wp_enqueue_scripts' );

// Customizer preview.
function codevz_plus_customize_preview_init() {

	wp_enqueue_script( 'codevz-customize', CODEVZ_FRAMEWORK_URL . '/fields/codevz_fields/codevz_customizer.js', array( 'customize-preview' ), Codevz_Plus::$ver, true );

}
add_action( 'customize_preview_init', 'codevz_plus_customize_preview_init' );

// Customizer footer.
function codevz_customize_controls() {
	wp_enqueue_script( 'codevz-customize-controls', CODEVZ_FRAMEWORK_URL . '/fields/codevz_fields/codevz_customizer_controls.js', [], Codevz_Plus::$ver, true );
}
add_action( 'customize_controls_print_footer_scripts', 'codevz_customize_controls' );

function codevz_disable_plugins_notifications() {

	?><script>
		jQuery( function( $ ) {
			// Hide plugins notifications
			if ( $( '.wp-admin' ).length ) {
			  var mute_plugins = $( '.plugins' ).find( '[data-slug="slider-revolution"], [data-slug="wpbakery-visual-composer"], [data-slug="revslider"], [data-slug="js_composer"]' );
			  if ( mute_plugins.length ) {
				mute_plugins.each(function() {
				  $( this ).next( '.plugin-update-tr' ).next( '.plugin-update-tr' ).hide();
				});
			  }
			  $( 'tr#revslider-update, .wpb-notice' ).hide();
			  $( '#message.rs-update-notice-wrap, #vc_license-activation-notice' ).hide().find( 'a' ).trigger( 'click' );
			  $( '.update-nag' ).each(function() {
				var string = $( this ).html();
				if ( string && string.toLowerCase().indexOf("visual") >= 0 ) {
				  $( this ).hide();
				}
			  });
			}
		});
	</script><?php

}
add_action( 'admin_footer', 'codevz_disable_plugins_notifications' );

/* Field: Slider */
if( ! class_exists( 'Codevz_Field_slider' ) ) {
  class Codevz_Field_slider extends Codevz_Fields {

	public function __construct( $field, $value = '', $unique = '', $where = '' ) {
	  parent::__construct( $field, $value, $unique, $where );
	}

	public function output() {

	  if ( isset( $this->field['options'] ) ) {
		$options = $this->field['options'];
	  } else {
		$options = array( 'step' => 1, 'unit' => 'px', 'min' => 0, 'max' => 100 );
	  }

	  echo '<div style="position:relative">';
	  echo '<input type="text" name="'. esc_attr( $this->element_name() ) .'" autocomplete="off" value="'. esc_attr( $this->element_value() ) .'"'. wp_kses_post( (string) $this->element_class() . $this->element_attributes() ) .'/>';
	  echo '<div class="codevz-slider" data-options=\'' . wp_json_encode( $options ) . '\'></div>';
	  echo '</div>';

	}

  }
}

/* Field: 4 Sizes */
if( ! class_exists( 'Codevz_Field_codevz_sizes' ) ) {
  class Codevz_Field_codevz_sizes extends Codevz_Fields {

	public function __construct( $field, $value = '', $unique = '', $where = '' ) {
	  parent::__construct( $field, $value, $unique, $where );
	}

	public function output() {

	  $default_options = array( 'step' => 1, 'unit' => 'px', 'min' => 0, 'max' => 50 );
	  $options = isset( $this->field['options'] ) ? wp_parse_args( $this->field['options'], $default_options ) : $default_options;

	  echo '<fieldset>';

	  if ( isset( $this->field[ 'split' ] ) ) {

		  $value_defaults = array( 'top' => '', 'right' => '', 'bottom' => '', 'left' => '' );
		  $this->value  	= wp_parse_args( [], $value_defaults );

	  	$top 		= 'top';
	  	$right 	= 'right';
	  	$bottom = 'bottom';
	  	$left 	= 'left';

	  } else {

		  $value_defaults = array( 'top' => '', 'right' => '', 'bottom' => '', 'left' => '' );
		  $this->value  	= wp_parse_args( $this->element_value(), $value_defaults );

	  	$top 		= $this->element_name( '[top]' );
	  	$right 	= $this->element_name( '[right]' );
	  	$bottom = $this->element_name( '[bottom]' );
	  	$left 	= $this->element_name( '[left]' );

	  }

	  codevz_add_field( array(
		  'echo'    => true,
		  'type'    => 'slider',
		  'name'    => $top,
		  'before'  => '<i class="fa fa-angle-up"></i>',
		  'options' => $options,
		  'attributes' => [ 'data-title' => esc_html__( 'Top', 'codevz-plus' ) ]
	  ), $this->value['top'], '', 'field/codevz_sizes' );

	  codevz_add_field( array(
		  'echo'    => true,
		  'type'    => 'slider',
		  'name'    => $right,
		  'before'  => '<i class="fa fa-angle-right"></i>',
		  'options' => $options,
		  'attributes' => [ 'data-title' => esc_html__( 'Right', 'codevz-plus' ) ]
	  ), $this->value['right'], '', 'field/codevz_sizes' );

	  codevz_add_field( array(
		  'echo'    => true,
		  'type'    => 'slider',
		  'name'    => $bottom,
		  'before'  => '<i class="fa fa-angle-down"></i>',
		  'options' => $options,
		  'attributes' => [ 'data-title' => esc_html__( 'Bottom', 'codevz-plus' ) ]
	  ), $this->value['bottom'], '', 'field/codevz_sizes' );

	  codevz_add_field( array(
		  'echo'    => true,
		  'type'    => 'slider',
		  'name'    => $left,
		  'before'  => '<i class="fa fa-angle-left"></i>',
		  'options' => $options,
		  'attributes' => [ 'data-title' => esc_html__( 'Left', 'codevz-plus' ) ]
	  ), $this->value['left'], '', 'field/codevz_sizes' );

	  echo '<i class="fa fa-link" data-title="Connect all"></i></fieldset>';

	}
  }
}

/* Field: Box Shadow 3 size field + color */
if( ! class_exists( 'Codevz_Field_codevz_box_shadow' ) ) {
  class Codevz_Field_codevz_box_shadow extends Codevz_Fields {

	public function __construct( $field, $value = '', $unique = '', $where = '' ) {
	  parent::__construct( $field, $value, $unique, $where );
	}

	public function output() {

	  $value_defaults = array( 'x' => '', 'y' => '', 'blur' => '', 'color' => '', 'inset' => '' );
	  $this->value  = wp_parse_args( $this->element_value(), $value_defaults );

	  $options = array( 'step' => 1, 'unit' => 'px', 'min' => 0, 'max' => 100 );

	  $is_box = ( isset( $this->field['id'] ) && Codevz_Plus::contains( $this->field['id'], 'box' ) );

	  echo '<fieldset>';

	  codevz_add_field( array(
	  'echo'    => true,
		  'type'    => 'slider',
		  'name'    => $this->element_name( '[x]' ),
		  'before'  => '<i>X</i>',
		  'options' => $options,
		  'dependency' => array( '_shadow_' . $this->field['id'], '!=', 'none' ),
	  ), $this->value['x'], '', 'field/codevz_box_shadow' );

	  codevz_add_field( array(
	  'echo'    => true,
		  'type'    => 'slider',
		  'name'    => $this->element_name( '[y]' ),
		  'before'  => '<i>Y</i>',
		  'options' => $options,
		  'dependency' => array( '_shadow_' . $this->field['id'], '!=', 'none' ),
	  ), $this->value['y'], '', 'field/codevz_box_shadow' );

	  codevz_add_field( array(
	  'echo'    => true,
		  'type'    => 'slider',
		  'name'    => $this->element_name( '[blur]' ),
		  'before'  => '<i>' . esc_html__( 'Blur', 'codevz-plus' ) . '</i>',
		  'options' => $options,
		  'dependency' => array( '_shadow_' . $this->field['id'], '!=', 'none' ),
	  ), $this->value['blur'], '', 'field/codevz_box_shadow' );

	  if ( $is_box ) {
			codevz_add_field( array(
				'echo'    => true,
				'type'    => 'slider',
				'name'    => $this->element_name( '[spread]' ),
				'before'  => '<i>' . esc_html__( 'Spread', 'codevz-plus' ) . '</i>',
				'dependency' => array( '_shadow_' . $this->field['id'], '!=', 'none' ),
			), ( isset( $this->value['spread'] ) ? $this->value['spread'] : '' ), '', 'field/codevz_box_shadow' );
	  }

		if ( Codevz_Plus::is_free() && $this->field['id'] == 'text-shadow' ) {

			echo do_shortcode( Codevz_Plus::pro_badge() );

		} else {

		  codevz_add_field( array(
		'echo'    => true,
			  'type'    => 'color_picker',
			  'name'    => $this->element_name( '[color]' ),
			  'before' 	=> '<i>' . esc_html__( 'Color', 'codevz-plus' ) . '</i>',
			  'attributes' => array(
				'data-rgba' => '#000'
			  ),
			  'dependency' => array( '_shadow_' . $this->field['id'], '!=', 'none' ),
		  ), $this->value['color'], '', 'field/codevz_box_shadow' );

	  }

	  echo '</fieldset>';

	}
  }
}

/* Add new customize/complex - array field value */
function codevz_sizes_filter_customize( $i ) {
  $i[] = 'codevz_sizes';
  $i[] = 'codevz_box_shadow';
  $i[] = 'sk';

  return $i;
}
add_filter( 'codevz/customize/complex', 'codevz_sizes_filter_customize' );

/* Field: Font select */
if( ! class_exists( 'Codevz_Field_select_font' ) ) {
  class Codevz_Field_select_font extends Codevz_Fields {

	public function __construct( $field, $value = '', $unique = '', $where = '' ) {
	  parent::__construct( $field, $value, $unique, $where );
	}

	public function output() {

	  $value = $this->element_value();
	  $hidden = ( empty( $value ) ) ? ' hidden' : '';

	  echo '<div class="codevz-font-select">';
	  echo '<input type="text" name="'. esc_attr( $this->element_name() ) .'" value="'. esc_html( $value ) .'" style="font-family:\''. esc_html( $value ) . '\'"'. wp_kses_post( (string) $this->element_class( 'codevz-font-value wpb_vc_param_value' ) . $this->element_attributes() ) .' />';
	  echo '<a href="#" class="button button-primary codevz-font-add" data-title="' . esc_html__( 'Fonts library', 'codevz-plus' ) . '"><i class="far fa-folder-open"></i></a>';
	  echo '<a href="#" class="codevz-warning-primary codevz-font-remove'. esc_html( $hidden ) .'" data-title="' . esc_html__( 'Remove', 'codevz-plus' ) . '"><i class="fa fa-remove"></i></a>';
	  echo '</div>';
	}

  }
}

/* Font selector get fonts by ajax */
if( ! function_exists( 'codevz_get_fonts' ) ) {
  function codevz_get_fonts() {

	// Websafe fonts
	$websafe = Codevz_Plus::web_safe_fonts();

	// Custom fonts
	$custom_fonts = (array) Codevz_Plus::option( 'upload_custom_fonts' );

	$custom = [];
	foreach ( $custom_fonts as $a ) {
		if ( ! empty( $a['font'] ) ) {
			$custom[ $a['font'] ] = $a['font'];
		}
	}

	$websafe = array_merge( $custom, $websafe );

	unset( $websafe['initial'] );
	unset( $websafe['inherit'] );
	unset( $websafe['czicons'] );
	unset( $websafe['fontelo'] );
	unset( $websafe['FontAwesome'] );
	unset( $websafe['Font Awesome 6 Free'] );

	echo '<strong><span>' . esc_html__( 'Websafe and custom added fonts', 'codevz-plus' ) . '</span></strong>';

	foreach ( $websafe as $f => $i ) {

		if ( is_int( $f ) ) {
			continue;
		}

		echo '<a class="websafe_font" style="font-family: ' . esc_attr( $f ) . '"><span>' . esc_html( $f ) . '</span><div class="cz_preview"></div></a>';

	}

	echo '<strong><span>' . esc_html__( 'Google Fonts', 'codevz-plus' ) . '</span></strong>';

	// Google fonts
	$fonts = codevz_get_google_fonts();
	foreach ( $fonts->items as $n => $item ) {
	  $f = $item->family;

	  $params = '';
	  foreach ( $item->variants as $p ) {
		if ( ! Codevz_Plus::contains( $p, 'italic' ) ) {
		  $v = ( $p === 'regular' ) ? '400' : $p;
		  $params .= '<label class="cz_font_variants"><input type="checkbox" name="' . $p . '" value="' . $v . '">' . $p . '</label>';
		}
	  }
	  foreach ( $item->subsets as $p ) {
		if ( $p !== 'latin' ) {
		  $params .= '<label class="cz_font_subsets"><input type="checkbox" name="' . $p . '" value="' . $p . '">' . $p . '</label>';
		}
	  }

	  //echo '<a class="cz_font"><span>' . esc_html( $f ) . '</span><div class="cz_preview"></div><i class="fa fa-cog"></i></a><div class="cz_font_params"><div>' . do_shortcode( $params ) . '</div></div>';
	  echo '<a class="cz_font"><span>' . esc_html( $f ) . '</span><div class="cz_preview"></div></a>';
	}

	wp_die();
  }
  add_action( 'wp_ajax_codevz-get-fonts', 'codevz_get_fonts' );
}

/* Field: StyleKit */
if( ! class_exists( 'Codevz_Field_cz_sk' ) ) {

  class Codevz_Field_cz_sk extends Codevz_Fields {

	public function __construct( $field, $value = '', $unique = '', $where = '' ) {
	  parent::__construct( $field, $value, $unique, $where );
	}

	public function output() {
	  $val = $this->element_value();
	  $val = ( is_array( $this->element_value() ) || $val === 'Array' ) ? '' : $this->element_value();
	  $hover = isset( $this->field['hover_id'] ) ? ' data-hover_id="' . $this->field['hover_id'] . '"' : '';
	  echo '<input type="hidden" name="'. esc_attr( $this->element_name() ) .'"' . wp_kses_post( (string) $hover ) . ' value="' . wp_kses_post( (string) $val ) . '" data-fields="' . wp_kses_post( (string) implode( ' ', $this->field['settings'] ) ) . '"' . wp_kses_post( (string) $this->element_attributes() ) . ' />';
	  $is_active = $val ? ' active_stylekit' : '';

	  $bg = '';
	  if ( Codevz_Plus::contains( $val, 'http' ) ) {
		preg_match_all( '/(http|https):\/\/[^ ]+(\.gif|\.jpg|\.jpeg|\.png)/', $val, $img );
		$bg = isset( $img[0][0] ) ? ' style="background-image:url(' . $img[0][0] . ')"' : '';
	  }

	  echo '<a href="#" class="button cz_sk_btn ' . esc_attr( $is_active ) . '" title="' . esc_html( $this->field['button'] ) . '"><span class="cz_skico cz'. esc_attr( $this->field['id'] )  .'"></span><span class="cz_sk_btn_text">' . esc_html( $this->field['button'] ) . '</span></a><div class="sk_btn_preview_image"' . wp_kses_post( (string) $bg ) . '></div>';
	}
  }
}

if( ! class_exists( 'Codevz_Field_cz_sk_free' ) ) {

	class Codevz_Field_cz_sk_free extends Codevz_Fields {

		public function __construct( $field, $value = '', $unique = '', $where = '' ) {
			parent::__construct( $field, $value, $unique, $where );
		}

		public function output() {

		  $val = $this->element_value();
		  $val = ( is_array( $this->element_value() ) || $val === 'Array' ) ? '' : $this->element_value();
		  $hover = isset( $this->field['hover_id'] ) ? ' data-hover_id="' . $this->field['hover_id'] . '"' : '';
		  echo '<input type="hidden" name="'. esc_attr( $this->element_name() ) .'"' . wp_kses_post( (string) $hover ) . ' value="' . wp_kses_post( (string) $val ) . '" data-fields="' . wp_kses_post( (string) implode( ' ', $this->field['settings'] ) ) . '"' . wp_kses_post( (string) $this->element_attributes() ) . ' />';

			echo '<a href="#" class="button cz_sk_btn cz_sk_free_btn"><span class="cz_skico cz'. esc_attr( $this->field['id'] )  .'"></span>' . wp_kses_post( (string) $this->field['button'] ) . '</a>';

		}

  }

}

/* Field: StyleKit hidden for responsive */
if( ! class_exists( 'Codevz_Field_cz_sk_hidden' ) ) {
  class Codevz_Field_cz_sk_hidden extends Codevz_Fields {
	public function __construct( $field, $value = '', $unique = '', $where = '' ) {
	  parent::__construct( $field, $value, $unique, $where );
	}

	public function output() {
	  $val = $this->element_value();
	  $val = ( is_array( $this->element_value() ) || $val === 'Array' ) ? '' : $this->element_value();
	  echo '<input type="hidden" name="'. esc_attr( $this->element_name() ) .'" value="' . wp_kses_post( (string) $val ) . '"' . wp_kses_post( (string) $this->element_attributes() ) . ' />';
	}
  }
}

/* Style kit HTML */
if( ! function_exists( 'codevz_hidden_modals' ) ) {
  function codevz_hidden_modals() {

	if ( Codevz_Plus::admin_enqueue() ) {

  ?><div id="cz_modal_kit" title="Styling">
	  <div>
		<form>
		  <?php 

			$free = Codevz_Plus::is_free();

			// Start
			echo '<div class="cz_sk_row cz_sk_content_row clr">';
			echo '<h4>' . esc_html__( 'Content', 'codevz-plus' ) . '</h4>';

			echo '<div class="col s12">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'type'    => 'text',
			  'id'      => 'live_id',
			  'name'    => 'live_id',
			  'title'   => 'ID',
			), '' );
			echo '</div>';

			echo '<div class="col s12">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'      => 'content',
			  'name'    => 'content',
			  'type'    => 'text',
			  'title'   => esc_html__( 'Content', 'codevz-plus' ),
			  'help'    => esc_html__( 'Any charachters or HTML symbols are allowed', 'codevz-plus' )
			), '' );
			echo '</div>';
			echo '</div>'; // Start

			// Indicator
			echo '<div class="cz_sk_row cz_sk_indicator_row clr">';
			echo '<h4>' . esc_html__( 'Indicator', 'codevz-plus' ) . '</h4>';
			echo '<div class="col s12">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'      => '_class_indicator',
			  'name'    => '_class_indicator',
			  'type'    => 'icon',
			  'title'   => ''
			), '' );
			echo '</div>';
			echo '</div>'; // Indicator

			// Shape
			echo '<div class="cz_sk_row cz_sk_shape_row clr">';
			echo '<h4>' . esc_html__( 'Shape', 'codevz-plus' ) . '</h4>';
			echo '<div class="col s12">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'      => '_class_shape',
			  'name'    => '_class_shape',
			  'type'    => 'select',
			  'title'   => esc_html__( 'Select', 'codevz-plus' ),
			  'help'    => esc_html__( 'This option required background color', 'codevz-plus' ),
			  'options'  => array(
				''                                => esc_html__( '~ Default ~', 'codevz-plus' ),
				'cz_row_shape_none'               => esc_html__( 'None', 'codevz-plus' ),
				'cz_row_shape_full_filled_left cz_row_shape_no_right'   => esc_html__( 'Filled left', 'codevz-plus' ),
				'cz_row_shape_full_filled_right cz_row_shape_no_left'  => esc_html__( 'Filled right', 'codevz-plus' ),

				'cz_row_shape_1'  => esc_html__( 'Shape', 'codevz-plus' ) . ' 1',
				'cz_row_shape_2'  => esc_html__( 'Shape', 'codevz-plus' ) . ' 2',

				'cz_row_shape_3'  => esc_html__( 'Shape', 'codevz-plus' ) . ' 3',
				'cz_row_shape_3 cz_row_shape_full_filled_left'  => esc_html__( 'Filled left', 'codevz-plus' ) . ' 3',
				'cz_row_shape_3 cz_row_shape_full_filled_right' => esc_html__( 'Filled right', 'codevz-plus' ) . ' 3',

				'cz_row_shape_4'  => esc_html__( 'Shape', 'codevz-plus' ) . ' 4',
				'cz_row_shape_4 cz_row_shape_full_filled_left'  => esc_html__( 'Filled left', 'codevz-plus' ) . ' 4',
				'cz_row_shape_4 cz_row_shape_full_filled_right' => esc_html__( 'Filled right', 'codevz-plus' ) . ' 4',
			  )
			), '' );
			echo '</div>';
			echo '</div>'; // Shape

			// FX Menu
			echo '<div class="cz_sk_row cz_sk_fx_row clr">';
			echo '<h4>' . esc_html__( 'Hover', 'codevz-plus' ) . '</h4>';
			echo '<div class="col s12">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'      => '_class_menu_fx',
			  'name'    => '_class_menu_fx',
			  'type'    => 'select',
			  'title'   => esc_html__( 'Select', 'codevz-plus' ),
			  'help'    => esc_html__( 'You can customize shape by changing settings background, width, height, left, bottom, etc.', 'codevz-plus' ),
			  'options' => array(
				''                          => esc_html__( '~ Default ~', 'codevz-plus' ),
				'cz_menu_fx_none'           => esc_html__( 'None', 'codevz-plus' ),
				'cz_menu_fx_left_to_right'  => esc_html__( 'Left To Right', 'codevz-plus' ),
				'cz_menu_fx_left_to_right_l'=> esc_html__( 'Left To Right Long', 'codevz-plus' ),
				'cz_menu_fx_right_to_left'  => esc_html__( 'Right To Left', 'codevz-plus' ),
				'cz_menu_fx_right_to_left_l'=> esc_html__( 'Right To Left Long', 'codevz-plus' ),
				'cz_menu_fx_center_to_sides'=> esc_html__( 'Center To Sides', 'codevz-plus' ),
				'cz_menu_fx_top_to_bottom'  => esc_html__( 'Top To Bottom', 'codevz-plus' ),
				'cz_menu_fx_bottom_to_top'  => esc_html__( 'Bottom To Top', 'codevz-plus' ),
				'cz_menu_fx_fade_in'    => esc_html__( 'Fade In', 'codevz-plus' ),
				'cz_menu_fx_zoom_in'    => esc_html__( 'Zoom In', 'codevz-plus' ),
				'cz_menu_fx_zoom_out'   => esc_html__( 'Zoom Out', 'codevz-plus' ),
				'cz_menu_fx_unroll'     => esc_html__( 'Unroll Vertical', 'codevz-plus' ),
				'cz_menu_fx_unroll_h'   => esc_html__( 'Unroll Horizontal', 'codevz-plus' ),
			  )
			), '' );
			echo '</div>';
			echo '</div>'; // FX Menu

			// Typography
			echo '<div class="cz_sk_row cz_sk_typo_row clr">';
			echo '<h4>' . esc_html__( 'Typography', 'codevz-plus' ) . '</h4>';

			echo '<div class="col s2">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'      => 'color',
			  'name'    => 'color',
			  'type'    => 'color_picker',
			  'title'   => esc_html__( 'Color', 'codevz-plus' ),
			), '' );
			echo '</div>';

			echo '<div class="col s5">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'      => 'font-size',
			  'name'    => 'font-size',
			  'type'    => 'slider',
			  'title'   => esc_html__( 'Font Size', 'codevz-plus' ),
			  'options' => array( 'unit' => 'px', 'step' => 1, 'min' => 0, 'max' => 130 )
			), '' );
			echo '</div>';

			echo '<div class="col s5 codevz-plus-last-field ' . ( $free ? 'xtra-readonly' : '' ) . '">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'      => 'font-family',
			  'name'    => 'font-family',
			  'type'    => 'select_font',
			  'title'   => esc_html__( 'Font Family', 'codevz-plus' ) . ( $free ? Codevz_Plus::pro_badge() : '' ),
			), '' );
			echo '</div>';

			// Advanced typography.
			echo '<div class="clr"></div>';
			echo '<a class="cz_advance_typo" href="#">' . esc_html__( 'Advanced', 'codevz-plus' ) . '<i class="fas fa-angle-down"></i></a>';

			echo '<div class="codevz-plus-advanced-fields">';
			echo '<div class="clr cz_hr"></div>';
			echo '<div class="codevz-plus-sk-adv-flex">';

			echo '<div data-title="Text Align">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'      => 'text-align',
			  'name'    => 'text-align',
			  'type'    => 'select',
			  'title'   => '<i class="fa fa-align-justify"></i>',
			  'options' => array(
				'left'    => esc_html__( 'Left', 'codevz-plus' ),
				'right'   => esc_html__( 'Right', 'codevz-plus' ),
				'center'  => esc_html__( 'Center', 'codevz-plus' ),
				'justify' => esc_html__( 'Justify', 'codevz-plus' ),
			  ),
			  'default_option' => '~',
			), '' );
			echo '</div>';

			echo '<div data-title="Font Weight">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'      => 'font-weight',
			  'name'    => 'font-weight',
			  'type'    => 'select',
			  'title'   => '<i class="fa fa-bold"></i>',
			  'options' => array(
				'100' => esc_html__( '100 | Thin', 'codevz-plus' ),
				'200' => esc_html__( '200 | Extra Light', 'codevz-plus' ),
				'300' => esc_html__( '300 | Light', 'codevz-plus' ),
				'400' => esc_html__( '400 | Normal', 'codevz-plus' ),
				'500' => esc_html__( '500 | Medium', 'codevz-plus' ),
				'600' => esc_html__( '600 | Semi Bold', 'codevz-plus' ),
				'700' => esc_html__( '700 | Bold', 'codevz-plus' ),
				'800' => esc_html__( '800 | Extra Bold', 'codevz-plus' ),
				'900' => esc_html__( '900 | High Bold', 'codevz-plus' ),
			  ),
			  'default_option' => '~',
			), '' );
			echo '</div>';

			echo '<div data-title="Font Style">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'      => 'font-style',
			  'name'    => 'font-style',
			  'type'    => 'select',
			  'title'   => '<i class="fa fa-italic"></i>',
			  'options' => array(
				'normal'  => esc_html__( 'Normal', 'codevz-plus' ),
				'italic'  => esc_html__( 'Italic', 'codevz-plus' ),
				'oblique' => esc_html__( 'Oblique', 'codevz-plus' ),
			  ),
			  'default_option' => '~',
			), '' );
			echo '</div>';

			echo '<div data-title="Text Transform">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'      => 'text-transform',
			  'name'    => 'text-transform',
			  'type'    => 'select',
			  'title'   => '<i class="fa fa-font"></i>',
			  'options' => array(
				'none'      => esc_html__( 'None', 'codevz-plus' ),
				'uppercase' => esc_html__( 'Uppercase', 'codevz-plus' ),
				'lowercase' => esc_html__( 'Lowercase', 'codevz-plus' ),
				'capitalize' => esc_html__( 'Capitalize', 'codevz-plus' )
			  ),
			  'default_option' => '~',
			), '' );
			echo '</div>';

			echo '<div data-title="Line Height">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'      => 'line-height',
			  'name'    => 'line-height',
			  'type'    => 'slider',
			  'title'   => '<i class="fa fa-text-height"></i>',
			  'options' => array( 'unit' => '', 'step' => 1, 'min' => 0, 'max' => 80 ),
			), '' );
			echo '</div>';

			echo '<div data-title="Letter Spacing">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'      => 'letter-spacing',
			  'name'    => 'letter-spacing',
			  'type'    => 'slider',
			  'title'   => '<i class="fa fa-text-width"></i>',
			  'options' => array( 'unit' => 'px', 'step' => 1, 'min' => -5, 'max' => 20 ),
			), '' );
			echo '</div>';

			echo '</div></div>'; // Typography advanced.

			echo '</div>'; // Typography

			// SVG
			echo '<div class="cz_sk_row cz_sk_svg_row clr">';
			echo '<h4>' . esc_html__( 'SVG', 'codevz-plus' ) . '</h4>';
			echo '<div class="col s12">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'        => '_class_svg_type',
			  'name'      => '_class_svg_type',
			  'type'      => 'select',
			  'title'     => esc_html__( 'SVG', 'codevz-plus' ),
			  'options'   => array(
				'dots'      => esc_html__( 'Dots', 'codevz-plus' ),
				'circle'    => esc_html__( 'Circle', 'codevz-plus' ),
				'line'      => esc_html__( 'Lines', 'codevz-plus' ),
				'x'         => 'X',
				'empty'     => esc_html__( 'Empty', 'codevz-plus' ),
			  ),
			  'default_option' => esc_html__( 'Select', 'codevz-plus' ),
			  'attributes' => array(
				'data-depend-id' => '_class_svg_type',
			  ),
			), '' );
			echo '</div>';

			echo '<div class="col s12">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'        => '_class_svg_size',
			  'name'      => '_class_svg_size',
			  'type'      => 'slider',
			  'title'     => esc_html__( 'SVG Size', 'codevz-plus' ),
			  'options'   => array( 'unit' => '', 'step' => 1, 'min' => 1, 'max' => 10 ),
			), '' );
			echo '</div>';

			echo '<div class="col s12">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'        => '_class_svg_color',
			  'name'      => '_class_svg_color',
			  'type'      => 'color_picker',
			  'title'     => esc_html__( 'Color', 'codevz-plus' )
			), '' );
			echo '</div>';

			echo '</div>'; // SVG

			// Background
			echo '<div class="cz_sk_row cz_sk_bg_row clr">';
			echo '<h4>' . esc_html__( 'Background', 'codevz-plus' ) . '</h4><div class="col s12">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'      => 'background',
			  'name'    => 'background',
			  'type'    => 'background',
			  'title'   => ''
			), '' );
			echo '</div></div>';

			// Sizes
			echo '<div class="cz_sk_row cz_sk_size_row clr">';
			echo '<h4>' . esc_html__( 'Sizes', 'codevz-plus' ) . '</h4>';
			echo '<div class="col s6">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'      => 'width',
			  'name'    => 'width',
			  'type'    => 'slider',
			  'title'   => esc_html__( 'Width', 'codevz-plus' ),
			  'options' => array( 'unit' => 'px', 'step' => 1, 'min' => 0, 'max' => 1200 ),
			), '' );
			echo '</div>';
			echo '<div class="col s6">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'      => 'height',
			  'name'    => 'height',
			  'type'    => 'slider',
			  'title'   => esc_html__( 'Height', 'codevz-plus' ),
			  'options' => array( 'unit' => 'px', 'step' => 1, 'min' => 0, 'max' => 600 ),
			), '' );
			echo '</div>';
			echo '</div>'; // Sizes

			// Spaces
			echo '<div class="cz_sk_row cz_sk_spaces_row clr">';
			
			echo '<h4>' . esc_html__( 'Spaces', 'codevz-plus' ) . '</h4>';

			echo '<div class="col s12">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'        => 'padding',
			  'name'      => 'padding',
			  'type'      => 'codevz_sizes',
			  'title'     => esc_html__( 'Padding', 'codevz-plus' ),
			  'desc'      => esc_html__( 'Inner gap', 'codevz-plus' ),
			  'options'   => array( 'unit' => 'px', 'step' => 1, 'min' => 0, 'max' => 100 ),
			  'help'      => esc_html__( 'Creating space around an element, INSIDE of any defined margins and borders. Can set using px, %, em, ...', 'codevz-plus' )
			), '' );
			echo '</div>';
			echo '<div class="clr cz_hr"></div>';

			echo '<div class="col s12">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'        => 'margin',
			  'name'      => 'margin',
			  'type'      => 'codevz_sizes',
			  'title'     => esc_html__( 'Margin', 'codevz-plus' ),
			  'desc'      => esc_html__( 'Outer gap', 'codevz-plus' ),
			  'options'   => array( 'unit' => 'px', 'step' => 1, 'min' => -50, 'max' => 100 ),
			  'help'      => esc_html__( 'Creating space around an element, OUTSIDE of any defined borders. Can set using px, %, em, auto, ...', 'codevz-plus' )
			), '' );
			echo '</div>';

			echo '</div>'; // Spaces

			// Border
			echo '<div class="cz_sk_row cz_sk_border_row clr">';
			echo '<h4>' . esc_html__( 'Border', 'codevz-plus' ) . '</h4>';

			echo '<div class="col s4" style="position:relative;z-index:99">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'        => 'border-color',
			  'name'      => 'border-color',
			  'type'      => 'color_picker',
			  'title'     => esc_html__( 'Color', 'codevz-plus' ),
			  'dependency' => array( 'border-style|border-style', '!=|!=', '|none' ),
			), '' );
			echo '</div>';

			echo '<div class="col s4">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'        => 'border-style',
			  'name'      => 'border-style',
			  'type'      => 'select',
			  'title'     => esc_html__( 'Border', 'codevz-plus' ),
			  'options'   => array(
				'solid'     => esc_html__( 'Solid', 'codevz-plus' ),
				'dotted'    => esc_html__( 'Dotted', 'codevz-plus' ),
				'dashed'    => esc_html__( 'Dashed', 'codevz-plus' ),
				'double'    => esc_html__( 'Double', 'codevz-plus' ),
				'none'      => esc_html__( 'None', 'codevz-plus' ),
			  ),
			  'default_option' => esc_html__( '~ Default ~', 'codevz-plus' ),
			  'attributes' => array(
				'data-depend-id' => 'border-style',
			  ),
			), '' );
			echo '</div>';

			echo '<div class="col s4 codevz-plus-last-field">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'        => 'border-radius',
			  'name'      => 'border-radius',
			  'type'      => 'slider',
			  'title'     => esc_html__( 'Radius', 'codevz-plus' ),
			  'help'      => esc_html__( 'Generate the arc for lines around element, e.g. 10px or manually set with this four positions respectively: <br />Top Right Bottom Left <br/><br/>e.g. 10px 10px 10px 10px', 'codevz-plus' )
			), '' );
			echo '</div>';

			echo '<div class="clr cz_hr"></div>';

			echo '<div class="col s12">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'        => 'border-width',
			  'name'      => 'border-width',
			  'type'      => 'codevz_sizes',
			  'options'   => array( 'unit' => 'px', 'step' => 1, 'min' => 1, 'max' => 100 ),
			  'title'     => esc_html__( 'Width', 'codevz-plus' ),
			  'desc'      => esc_html__( 'Around element', 'codevz-plus' ),
			  'help'      => esc_html__( 'Border size around element.', 'codevz-plus' ),
			  'dependency' => array( 'border-style|border-style', '!=|!=', '|none' ),
			), '' );

			codevz_add_field( array(
				'echo' 		=> true,
			  'id'        => 'border-right-color',
			  'name'      => 'border-right-color',
			  'type'      => 'color_picker',
			  'title'     => esc_html__( 'Border right color', 'codevz-plus' )
			), '' );
			echo '</div>';

			echo '</div>'; // Border.

			// Shadows.
			echo '<div class="cz_sk_row cz_sk_shadow_row clr">';
			echo '<h4>' . esc_html__( 'Shadow', 'codevz-plus' ) . '</h4>';

			echo '<div class="col s12">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'        => 'box-shadow',
			  'name'      => 'box-shadow',
			  'type'      => 'codevz_box_shadow',
			  'title'     => esc_html__( 'Box Shadow', 'codevz-plus' ),
			  'desc'      => '<label><span>Inset</span> <input type="checkbox" name="box-shadow[inset]" value="" /></label>'
			), '' );
			echo '</div>';

			echo '</div>'; // Shadows

			// Advanced
			echo '<div class="cz_sk_row cz_sk_advance_row clr">';
			echo '<h4>' . esc_html__( 'Display & Position', 'codevz-plus' ) . '</h4>';

			echo '<div class="col s4">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'      => 'display',
			  'name'    => 'display',
			  'type'    => 'select',
			  'title'   => esc_html__( 'Display', 'codevz-plus' ),
			  'options' => array(
					'none'                => esc_html__( 'None', 'codevz-plus' ),
					'block'               => esc_html__( 'Block', 'codevz-plus' ),
					'inline'              => esc_html__( 'Inline', 'codevz-plus' ),
					'inline-block'        => esc_html__( 'Inline Block', 'codevz-plus' ),
					'flex'                => esc_html__( 'Flex', 'codevz-plus' ),
					'flow-root'           => esc_html__( 'Flow root', 'codevz-plus' ),
					'list-item'           => esc_html__( 'List item', 'codevz-plus' ),
					'table'               => esc_html__( 'Table', 'codevz-plus' ),
					'table-cell'          => esc_html__( 'Table Cell', 'codevz-plus' ),
			  ),
			  'default_option' => esc_html__( '~ Default ~', 'codevz-plus' ),
			), '' );
			echo '</div>';

			echo '<div class="col s4">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'      => 'float',
			  'name'    => 'float',
			  'type'    => 'select',
			  'title'   => esc_html__( 'Float', 'codevz-plus' ),
			  'options' => array(
				'none'      => esc_html__( 'None', 'codevz-plus' ),
				'left'      => esc_html__( 'Left', 'codevz-plus' ),
				'right'     => esc_html__( 'Right', 'codevz-plus' ),
			  ),
			  'default_option' => esc_html__( '~ Default ~', 'codevz-plus' ),
			), '' );
			echo '</div>';

			echo '<div class="col s4 codevz-plus-last-field">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'      => 'position',
			  'name'    => 'position',
			  'type'    => 'select',
			  'title'   => esc_html__( 'Position', 'codevz-plus' ),
			  'options' => array(
				'static'        => esc_html__( 'Static', 'codevz-plus' ),
				'relative'      => esc_html__( 'Relative', 'codevz-plus' ),
				'absolute'      => esc_html__( 'Absolute', 'codevz-plus' ),
			  ),
			  'default_option' => esc_html__( '~ Default ~', 'codevz-plus' ),
			), '' );
			echo '</div>';

			echo '<div class="clr cz_hr"></div>';

			echo '<div class="col s12">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'        => 'positions',
			  'name'      => 'positions',
			  'type'      => 'codevz_sizes',
			  'split'     => true,
			  'title'     => esc_html__( 'Positions', 'codevz-plus' ),
			  'desc'      => esc_html__( 'Element position', 'codevz-plus' ),
			  'options'   => array( 'unit' => 'px', 'step' => 1, 'min' => -50, 'max' => 100 ),
			  'help'      => esc_html__( 'The position CSS property sets how an element is positioned in the container. The top, right, bottom, and left properties determine the final location of positioned element', 'codevz-plus' )
			), '' );
			echo '</div>';

			echo '</div>';

			echo '<div class="cz_sk_row cz_sk_advance_row clr ' . ( $free ? 'xtra-readonly' : '' ) . '">';
			echo '<h4>' . esc_html__( 'Advanced', 'codevz-plus' ) . do_shortcode( $free ? Codevz_Plus::pro_badge() : '' ) . '</h4>';

			echo '<div class="col s4">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'      => 'z-index',
			  'name'    => 'z-index',
			  'type'    => 'select',
			  'title'   => esc_html__( 'z-index', 'codevz-plus' ),
			  'options' => array(
				'-2'  => '-2',
				'-1'  => '-1',
				'0'   => '0',
				'1'   => '1',
				'2'   => '2',
				'3'   => '3',
				'4'   => '4',
				'5'   => '5',
				'6'   => '6',
				'7'   => '7',
				'8'   => '8',
				'9'   => '9',
				'10'  => '10',
				'99'  => '99',
				'999' => '999',
				'9999' => '9999',
			  ),
			  'default_option' => esc_html__( '~ Default ~', 'codevz-plus' ),
			), '' );
			echo '</div>';

			echo '<div class="col s4">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'      => 'opacity',
			  'name'    => 'opacity',
			  'type'    => 'select',
			  'title'   => esc_html__( 'Opacity', 'codevz-plus' ),
			  'options' => array(
				'1'      => '1',
				'0.95'   => '0.95',
				'0.9'    => '0.9',
				'0.85'   => '0.85',
				'0.8'    => '0.8',
				'0.75'   => '0.75',
				'0.7'    => '0.7',
				'0.65'   => '0.65',
				'0.6'    => '0.6',
				'0.55'   => '0.55',
				'0.5'    => '0.5',
				'0.45'   => '0.45',
				'0.4'    => '0.4',
				'0.35'   => '0.35',
				'0.3'    => '0.3',
				'0.25'   => '0.25',
				'0.2'    => '0.2',
				'0.15'   => '0.15',
				'0.1'    => '0.1',
				'0.05'   => '0.05',
				'0.0'    => '0.0',
			  ),
			  'default_option' => esc_html__( '~ Default ~', 'codevz-plus' ),
			), '' );
			echo '</div>';

			echo '<div class="col s4 codevz-plus-last-field">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'        => 'transform',
			  'name'      => 'transform',
			  'type'      => 'slider',
			  'title'     => esc_html__( 'Rotate', 'codevz-plus' ),
			  'options'   => array( 'unit' => 'deg', 'step' => 1, 'min' => 0, 'max' => 360 ),
			), '' );
			echo '</div>';

			echo '</div>'; // Advanced Settings

			// Custom CSS.
			echo '<div class="cz_sk_row cz_sk_custom clr ' . ( $free ? 'xtra-readonly' : '' ) . '" style="display: none">';
			echo '<h4>' . esc_html__( 'Custom CSS', 'codevz-plus' ) . do_shortcode( $free ? Codevz_Plus::pro_badge() : '' ) . '</h4>';
			echo '<div class="col s12">';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'      => $free ? 'xxx' : 'custom',
			  'name'    => 'custom',
			  'type'    => 'textarea',
			  'title'   => '',
			  'attributes' => array(
					'rows' 				=> '2',
					'cols' 				=> '5',
					'placeholder' 		=> 'property: value;',
				),
			  'help'    => esc_html__( 'You can add custom css for this element.', 'codevz-plus' ) . '<br /><br />e.g.<br /><br />transform: rotate(10deg);'
			), '' );
			echo '</div>';
			echo '</div>'; // Custom CSS.

			// Custom RTL
			echo '<div class="cz_sk_row cz_custom_rtl clr">';
			echo '<h4>' . esc_html__( 'RTL mode', 'codevz-plus' ) . '</h4>';
			echo '<div class="col s12"><a href="#" class="xtra-auto-rtl button button-primary" style="margins-bottom: 15px;display:inline-block">Auto RTL Convert</a>';
			codevz_add_field( array(
				'echo' 		=> true,
			  'id'      => 'rtl',
			  'name'    => 'rtl',
			  'type'    => 'textarea',
			  'title'   => '',
			  'attributes' => array(
				'rows' => '2',
				'cols' => '5',
			  ),
			), '' );
			echo '</div>';
			echo '</div>'; // Custom RTL.

		  ?>
		</form>
	  </div>
	</div>

			<div id="codevz-modal-font" class="codevz-modal codevz-modal-font">
			  <div class="codevz-modal-table">
				<div class="codevz-modal-table-cell">
				  <div class="codevz-modal-overlay"></div>
				  <div class="codevz-modal-inner">
					<div class="codevz-modal-title">
					  <?php esc_html_e( 'Fonts library', 'codevz-plus' ); ?>
					  <div class="codevz-modal-close codevz-font-close"></div>
					</div>
					<div class="codevz-modal-header codevz-text-center">
					  <input type="text" placeholder="<?php esc_html_e( 'Search', 'codevz-plus' ); ?>" class="codevz-font-search" />
					  <input type="text" placeholder="<?php esc_html_e( 'Preview', 'codevz-plus' ); ?>" class="codevz-font-placeholder">
					</div>
					<div class="codevz-modal-content"><div class="codevz-font-loading"></div></div>
				  </div>
				</div>
			  </div>
			</div>

		<div id="codevz-modal-icon" class="codevz-modal codevz-modal-icon">
		  <div class="codevz-modal-table">
			<div class="codevz-modal-table-cell">
			  <div class="codevz-modal-overlay"></div>
			  <div class="codevz-modal-inner">
				<div class="codevz-modal-title">
				  <?php esc_html_e( 'Add Icon', 'codevz-plus' ); ?>
				  <div class="codevz-modal-close codevz-icon-close"></div>
				</div>
				<div class="codevz-modal-header codevz-text-center">
				  <input type="text" placeholder="<?php esc_html_e( 'Type a keyword ...', 'codevz-plus' ); ?>" class="codevz-icon-search" />
				</div>
				<div class="codevz-modal-content"><div class="codevz-icon-loading"><?php esc_html_e( 'Loading...', 'codevz-plus' ); ?></div></div>
			  </div>
			</div>
		  </div>
		</div>

	<?php
		}
  }
  add_action( 'admin_footer', 'codevz_hidden_modals' );
  add_action( 'customize_controls_print_footer_scripts', 'codevz_hidden_modals' );
  add_action( 'elementor/editor/footer', 'codevz_hidden_modals' );
}


/* Field: codevz image dropdown */
if( ! class_exists( 'Codevz_Field_codevz_image_select' ) ) {
  class Codevz_Field_codevz_image_select extends Codevz_Fields {
	public function __construct( $field, $value = '', $unique = '', $where = '' ) {
	  parent::__construct( $field, $value, $unique, $where );
	}

	public function output() {

	  $val = $this->element_value();

	  if ( $val ) {
		$default_id = $val;
	  } else if ( isset( $this->field['default'] ) ) {
		$default_id = $this->field['default'];
	  } else {
		foreach ( $this->field['options'] as $key => $item ) {
		  $default_id = $key;
		  break;
		}
	  }

	  $default_title = isset( $this->field['options'][ $default_id ][0] ) ? $this->field['options'][ $default_id ][0] : '...';
	  $default_image = isset( $this->field['options'][ $default_id ][1] ) ? $this->field['options'][ $default_id ][1] : '';

	  echo '<div class="codevz_image_select">';
	  echo '<div data-id="' . esc_attr( $default_id ) . '">';
	  echo '<img src="' . esc_attr( $default_image ) . '" /><span><b>' . esc_html( $this->field['title'] ) . '</b><span>' . esc_html( $default_title ) . '</span></span>';
	  echo '<i class="fa fa-angle-down"></i>';
	  echo '</div>';

	  echo '<ul>';
	  foreach ( $this->field['options'] as $key => $item ) {
		$class = Codevz_Plus::contains( $item[0], '[' ) ? 'xtra-li-red' : '';
			echo '<li class="' . esc_attr( $class ) . '" data-id="' . esc_attr( $key ) . '" data-title="' . esc_attr( $item[0] ) . '"><img src="' . esc_attr( $item[1] ) . '" /></li>';
	  }
	  echo '</ul>';

	  echo '<input type="hidden" name="'. esc_attr( $this->element_name() ) .'" value="'. esc_attr( $val ) .'"'. wp_kses_post( (string) $this->element_class() . $this->element_attributes() ) .'/>';

	  echo '</div>';
	}
  }
}

