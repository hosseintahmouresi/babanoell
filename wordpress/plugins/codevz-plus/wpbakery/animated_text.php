<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Cannot access pages directly.

/**
 * Animated Text
 * 
 * @author Codevz
 * @copyright Codevz
 * @link http://codevz.com/
 */

class Codevz_WPBakery_animated_text {

	public $name = false;

	public function __construct( $name ) {
		$this->name = $name;
	}

	/**
	 * Shortcode settings
	 */
	public function in( $wpb = false ) {
		add_shortcode( $this->name, [ $this, 'out' ] );

		$settings = array(
			'category'		=> Codevz_Plus::$title,
			'base'			=> $this->name,
			'name'			=> esc_html__( 'Animated Text', 'codevz-plus' ),
			'description'	=> esc_html__( 'Awesome text animation', 'codevz-plus' ),
			'icon'			=> 'czi',
			'params'		=> array(
				array(
					'type' 			=> 'cz_sc_id',
					'param_name' 	=> 'id',
					'save_always' 	=> true
				),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__("Prefix", 'codevz-plus' ),
					"param_name"  	=> "before_text",
					'edit_field_class' => 'vc_col-xs-99',
					'value'			=> 'This is'
				),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__("Words", 'codevz-plus' ),
					"description"   => "e.g. word1,word2,word3",
					"param_name"  	=> "words",
					'edit_field_class' => 'vc_col-xs-99',
					'value'			=> 'Awesome,Fantastic,Wonderful'
				),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__("Suffix", 'codevz-plus' ),
					"param_name"  	=> "after_text",
					'edit_field_class' => 'vc_col-xs-99',
					'value'			=> 'Theme!'
				),
				array(
					'type' 			=> 'dropdown',
					'heading' 		=> esc_html__('Effect', 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name' 	=> 'fx',
					'value' 		=> array(
						esc_html__( "Rotate", 'codevz-plus' ) . ' 1' 	=> 'rotate-1',
						esc_html__( "Type", 'codevz-plus' ) 			=> 'letters_type',
						esc_html__( "Rotate", 'codevz-plus' ) . ' 2' 	=> 'letters_rotate-2',
						esc_html__( "Bar", 'codevz-plus' ) 			=> 'loading-bar',
						esc_html__( "Slide", 'codevz-plus' ) 			=> 'slide',
						esc_html__( "Clip", 'codevz-plus' ) 			=> 'clip_is-full-width',
						esc_html__( "Zoom", 'codevz-plus' ) 			=> 'zoom',
						esc_html__( "Rotate", 'codevz-plus' ) . ' 3' 	=> 'letters_rotate-3',
						esc_html__( "Scale", 'codevz-plus' ) 			=> 'letters_scale',
						esc_html__( "Push", 'codevz-plus' ) 			=> 'push',
					)
				),
				array(
					'type' 			=> 'dropdown',
					'heading' 		=> esc_html__('HTML tag', 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name' 	=> 'tag',
					'value'			=> array(
						'H2' 		=> 'h2',
						'H1' 		=> 'h1',
						'H3' 		=> 'h3',
						'H4' 		=> 'h4',
						'H5' 		=> 'h5',
						'H6' 		=> 'h6',
						'Span' 		=> 'span',
						'Div' 		=> 'div',
						'P' 		=> 'p',
					)
				),
				array(
					"type"        	=> "cz_slider",
					"heading"     	=> esc_html__("Animation Delay", 'codevz-plus' ),
					"description"   => "e.g. 3000",
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "time",
					"value"			=>"3000",
              		'options' => array( 'unit' => '', 'step' => 500, 'min' => 0, 'max' => 10000 ),
				),

				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Styling', 'codevz-plus' ),
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_overall',
					"heading"     	=> esc_html__( "Container", 'codevz-plus' ),
					'button' 		=> esc_html__( "Container", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'text-align', 'font-family', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_overall_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_overall_mobile' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_words',
					"heading"     	=> esc_html__( "Animated words", 'codevz-plus' ),
					'button' 		=> esc_html__( "Animated words", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'font-family', 'font-size', 'background' ),
					'dependency'	=> array(
						'element'		=> 'words',
						'not_empty'		=> true
					),
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_words_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_words_mobile' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_before',
					"heading"     	=> esc_html__( "Prefix", 'codevz-plus' ),
					'button' 		=> esc_html__( "Prefix", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color' ),
					'dependency'	=> array(
						'element'		=> 'before_text',
						'not_empty'		=> true
					),
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_before_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_before_mobile' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_after',
					"heading"     	=> esc_html__( "Suffix", 'codevz-plus' ),
					'button' 		=> esc_html__( "Suffix", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color' ),
					'dependency'	=> array(
						'element'		=> 'after_text',
						'not_empty'		=> true
					),
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_after_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_after_mobile' ),

				// Advanced
				array(
					'type' 			=> 'checkbox',
					'heading' 		=> esc_html__( 'Hide on Desktop?', 'codevz-plus' ),
					'param_name' 	=> 'hide_on_d',
					'edit_field_class' => 'vc_col-xs-4',
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				), 
				array(
					'type' 			=> 'checkbox',
					'heading' 		=> esc_html__( 'Hide on Tablet?', 'codevz-plus' ),
					'param_name' 	=> 'hide_on_t',
					'edit_field_class' => 'vc_col-xs-4',
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				), 
				array(
					'type' 			=> 'checkbox',
					'heading' 		=> esc_html__( 'Hide on Mobile?', 'codevz-plus' ),
					'param_name' 	=> 'hide_on_m',
					'edit_field_class' => 'vc_col-xs-4',
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Parallax', 'codevz-plus' ),
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				array(
					"type"        	=> "dropdown",
					"heading"     	=> esc_html__( "Parallax", 'codevz-plus' ),
					"param_name"  	=> "parallax_h",
					'edit_field_class' => 'vc_col-xs-99',
					'value'		=> array(
						esc_html__( 'Select', 'codevz-plus' )					=> '',
						
						esc_html__( 'Vertical', 'codevz-plus' )					=> 'v',
						esc_html__( 'Vertical + Mouse parallax', 'codevz-plus' )		=> 'vmouse',
						esc_html__( 'Horizontal', 'codevz-plus' )				=> 'true',
						esc_html__( 'Horizontal + Mouse parallax', 'codevz-plus' )	=> 'truemouse',
						esc_html__( 'Mouse parallax', 'codevz-plus' )				=> 'mouse',
					),
					"group"  		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				array(
					"type"        	=> "cz_slider",
					"heading"     	=> esc_html__( "Parallax speed", 'codevz-plus' ),
					"description"   => esc_html__( "Parallax is according to page scrolling", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "parallax",
					"value"  		=> "0",
					'options' 		=> array( 'unit' => '', 'step' => 1, 'min' => -50, 'max' => 50 ),
					'dependency'	=> array(
						'element'		=> 'parallax_h',
						'value'			=> array( 'v', 'vmouse', 'true', 'truemouse' )
					),
					"group"  		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				array(
					'type' 			=> 'checkbox',
					'heading' 		=> esc_html__( 'Stop when done', 'codevz-plus' ),
					'param_name' 	=> 'parallax_stop',
					'edit_field_class' => 'vc_col-xs-99',
					'dependency'	=> array(
						'element'		=> 'parallax_h',
						'value'			=> array( 'v', 'vmouse', 'true', 'truemouse' )
					),
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				), 
				array(
					"type"        	=> "cz_slider",
					"heading"     	=> esc_html__("Mouse speed", 'codevz-plus' ),
					"description"   => esc_html__( "Mouse parallax is according to mouse move", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "mparallax",
					"value"  		=> "0",
					'options' 		=> array( 'unit' => '', 'step' => 1, 'min' => -30, 'max' => 30 ),
					'dependency'	=> array(
						'element'		=> 'parallax_h',
						'value'			=> array( 'vmouse', 'truemouse', 'mouse' )
					),
					"group"  		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Animation & Class', 'codevz-plus' ),
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				Codevz_Plus::wpb_animation_tab( false ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_brfx',
					"heading"     	=> esc_html__( "Block Reveal", 'codevz-plus' ),
					'button' 		=> esc_html__( "Block Reveal", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99 hidden',
					'group' 	=> esc_html__( 'Advanced', 'codevz-plus' ),
					'settings' 		=> array( 'background' )
				),
				array(
					"type"        	=> "cz_slider",
					"heading"     	=> esc_html__("Animation Delay", 'codevz-plus' ),
					"description" 	=> 'e.g. 500ms',
					"param_name"  	=> "anim_delay",
					'options' 		=> array( 'unit' => 'ms', 'step' => 100, 'min' => 0, 'max' => 5000 ),
					'edit_field_class' => 'vc_col-xs-6',
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__( "Extra Class", 'codevz-plus' ),
					"param_name"  	=> "class",
					'edit_field_class' => 'vc_col-xs-6',
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				),

			)
		);

		return $wpb ? vc_map( $settings ) : $settings;
	}

	/**
	 *
	 * Shortcode output
	 * 
	 * @return string
	 * 
	 */
	public function out( $atts, $content = '' ) {

		// Settings.
		$atts = Codevz_Plus::shortcode_atts( $this, $atts );

		// ID
		if ( ! $atts['id'] ) {
			$atts['id'] = Codevz_Plus::uniqid();
			$public = 1;
		}

		// Styles
		if ( isset( $public ) || Codevz_Plus::$vc_editable || Codevz_Plus::$is_admin ) {
			$css_id = '#' . $atts['id'];
			$custom = $atts['anim_delay'] ? 'animation-delay:' . $atts['anim_delay'] . ';' : '';

			$css_array = array(
				'sk_overall' 	=> array( $css_id, $custom ),
				'sk_brfx' 		=> $css_id . ':before',
				'sk_words' 		=> $css_id . ' .cz_words-wrapper',
				'sk_before' 	=> $css_id . ' .cz_before_text',
				'sk_after' 		=> $css_id . ' .cz_after_text',
			);

			$css 	= Codevz_Plus::sk_style( $atts, $css_array );
			$css_t 	= Codevz_Plus::sk_style( $atts, $css_array, '_tablet' );
			$css_m 	= Codevz_Plus::sk_style( $atts, $css_array, '_mobile' );

		} else {
			Codevz_Plus::load_font( $atts['sk_words'] );
			Codevz_Plus::load_font( $atts['sk_before'] );
		}

		// Classes
		$classes = array();
		$classes[] = $atts['id'];
		$classes[] = 'cz_headline';
		$classes[] = str_replace( '_', ' ', $atts['fx'] );

		// Out
		$out = '<' . $atts['tag'] . ' id="' . $atts['id'] . '" data-time="' . $atts['time'] . '"' . Codevz_Plus::classes( $atts, $classes ) . Codevz_Plus::data_stlye( $css, $css_t, $css_m ) . '>';
		$out .= $atts['before_text'] ? '<span class="cz_before_text">' . $atts['before_text'] . '</span>' : '';
		
		$out .='<span class="cz_words-wrapper">';
		$i = 1;
		$words = (array) explode( ',', $atts['words'] );
		foreach ( $words as $word ) {
			$visible = ( $i !== 1 ) ? ' class="is-hidden"' : ' class="is-visible"';
			$out .= '<b' . $visible . '>' . $word . '</b>';
			$i++;
		}
		$out .='</span>';
		
		$out .= $atts['after_text'] ? '<span class="cz_after_text">' . $atts['after_text'] . '</span>' : '';
		$out .= '</'. $atts['tag'] .'>';

		wp_enqueue_script( 'codevz-modernizer' );
		
		return Codevz_Plus::_out( $atts, $out, 'animated_text', $this->name );
	}
}