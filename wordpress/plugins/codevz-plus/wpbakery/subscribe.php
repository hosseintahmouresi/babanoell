<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.

/**
 * Subscribe
 * 
 * @author Codevz
 * @link http://codevz.com/
 */

class Codevz_WPBakery_subscribe {

	public $name = false;

	public function __construct( $name ) {

		$this->name = $name;

		// Handle form AJAX request.
		add_action( 'wp_ajax_cz_subscribe_ajax', [ 'Codevz_Plus', 'subscribe_ajax' ] );
		add_action( 'wp_ajax_nopriv_cz_subscribe_ajax', [ 'Codevz_Plus', 'subscribe_ajax' ] );

		// Handle secret URL confirmation.
		add_action( 'init', [ 'Codevz_Plus', 'subscribe_confirm' ] );

	}

	/**
	 * Shortcode settings
	 */
	public function in( $wpb = false ) {
		add_shortcode( $this->name, [ $this, 'out' ] );

		$description = '<div class="cz-subscribe-desc">
			<span>' . esc_html__( 'All submitted emails will be stored as WordPress users with the', 'codevz-plus' ) . ' <strong>' . esc_html__( 'Subscriber', 'codevz-plus' ) . '</strong> ' . esc_html__( 'role. Users must confirm their subscription through a link sent to their email (double opt-in). Only confirmed emails will be added.', 'codevz-plus' ) . '</span>

			<span>' . esc_html__( 'You can manage these subscribers directly from the', 'codevz-plus' ) . ' <strong>' . esc_html__( 'Users > All Users', 'codevz-plus' ) . '</strong> ' . esc_html__( 'page in your WordPress dashboard. Use the role filter to view only subscribers.', 'codevz-plus' ) . '</span>

			<span>' . esc_html__( 'To send newsletters, you can use plugins like:', 'codevz-plus' ) . '</span>
			<ul>
				<li><strong>MailPoet</strong> – ' . esc_html__( 'send emails directly from your WordPress site.', 'codevz-plus' ) . '</li>
				<li><strong>Newsletter Plugin</strong> – ' . esc_html__( 'manage lists, campaigns, and automations.', 'codevz-plus' ) . '</li>
				<li><strong>Mailchimp for WP</strong> – ' . esc_html__( 'connect with your Mailchimp audience.', 'codevz-plus' ) . '</li>
			</ul>

			<span>' . esc_html__( 'Subscribers are created as real WordPress accounts. Do not assign sensitive capabilities to the "subscriber" role unless needed.', 'codevz-plus' ) . '</span>
		</div>';

		$settings = array(
			'category'		=> Codevz_Plus::$title,
			'base'			=> $this->name,
			'name'			=> esc_html__( 'Subscribe', 'codevz-plus' ),
			'description'	=> esc_html__( 'Custom newsletter form', 'codevz-plus' ),
			'icon'			=> 'czi',
			'params'		=> array(
				array(
					'type' 			=> 'cz_sc_id',
					'param_name' 	=> 'id',
					'save_always' 	=> true
				),
				array(
					'type'			=> 'dropdown',
					'heading'		=> esc_html__('Style', 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name'	=> 'style',
					'value'			=> array(
						esc_html__( '~ Default ~', 'codevz-plus' )		=> 'cz_subscribe_style_default',
						esc_html__( 'Style', 'codevz-plus' ) . ' 1'		=> 'cz_subscribe_square cz_subscribe_style_1',
						esc_html__( 'Style', 'codevz-plus' ) . ' 2'		=> 'cz_subscribe_round cz_subscribe_style_2',
						esc_html__( 'Style', 'codevz-plus' ) . ' 3'		=> 'cz_subscribe_round_2 cz_subscribe_style_3',
						esc_html__( 'Style', 'codevz-plus' ) . ' 4'		=> 'cz_subscribe_relative cz_subscribe_square cz_subscribe_style_4',
						esc_html__( 'Style', 'codevz-plus' ) . ' 5'		=> 'cz_subscribe_relative cz_subscribe_round cz_subscribe_style_5',
						esc_html__( 'Style', 'codevz-plus' ) . ' 6'		=> 'cz_subscribe_col cz_subscribe_square cz_subscribe_style_6',
						esc_html__( 'Style', 'codevz-plus' ) . ' 7'		=> 'cz_subscribe_col cz_subscribe_style_7',
					)
				),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__( "Name input", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "name"
				),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__( "Email input", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'value' 		=> esc_html__( 'Your email ...', 'codevz-plus' ),
					"param_name"  	=> "placeholder"
				),
				array(
					'type'			=> 'dropdown',
					'heading'		=> esc_html__('Position', 'codevz-plus' ),
					'description'	=> esc_html__('According to form width', 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name'	=> 'position',
					'value'		=> array(
						esc_html__( '~ Default ~', 'codevz-plus' ) 		=> '',
						esc_html__( 'Center', 'codevz-plus' ) 			=> 'center',
						esc_html__( 'Inverted', 'codevz-plus' ) 		=> 'right',
					),
				),
				array(
					'type'			=> 'dropdown',
					'heading'		=> esc_html__('Button Position', 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name'	=> 'btn_position',
					'value'		=> array(
						esc_html__( '~ Default ~', 'codevz-plus' )		=> '',
						esc_html__( 'Center', 'codevz-plus' )			=> 'cz_subscribe_btn_center',
						esc_html__( 'Inverted', 'codevz-plus' )			=> 'cz_subscribe_btn_right',
					)
				),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__("Button title", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'value' 		=> esc_html__( 'Join Now', 'codevz-plus' ),
					"param_name"  	=> "btn_title"
				),
				array(
					"type"        	=> "cz_icon",
					"heading"     	=> esc_html__("Button icon", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "icon"
				),

				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Method', 'codevz-plus' ),
				),
				array(
					'type'			=> 'dropdown',
					'heading'		=> esc_html__('Method', 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name'	=> 'ajax',
					'value'			=> array(
						esc_html__( 'Custom form', 'codevz-plus' )		=> '',
						esc_html__( 'Built-in AJAX', 'codevz-plus' )	=> '2'
					),
				),
				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'ajax_desc',
					'class' 		=> 'codevz-plus-important-desc',
					'content' 		=> $description,
					'dependency'	=> array(
						'element'		=> 'ajax',
						'value'			=> [ '2' ]
					),
				),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__("Action URL", 'codevz-plus' ),
					'description'	=> esc_html__('Mailchimp action or Google feedburner url', 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'value' 		=> '#',
					"param_name"  	=> "action",
					'dependency'	=> array(
						'element'		=> 'ajax',
						'value'			=> [ '' ]
					),
				),
				array(
					'type'			=> 'dropdown',
					'heading'		=> esc_html__('Type attribute', 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name'	=> 'type_attr',
					'value'		=> array(
						'email'		=> 'email',
						'text'		=> 'text',
						'number'	=> 'number',
						'search'	=> 'search',
						'tel'		=> 'tel',
						'time'		=> 'time',
						'date'		=> 'date',
						'url'		=> 'url',
						'password'	=> 'password',
					),
					'dependency'	=> array(
						'element'		=> 'ajax',
						'value'			=> [ '' ]
					),
				),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__( "Email attribute", 'codevz-plus' ),
					'description'	=> esc_html__( 'Example for MailChimp: MERGE0', 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'value' 		=> 'MERGE0',
					"param_name"  	=> "name_attr",
					'dependency'	=> array(
						'element'		=> 'ajax',
						'value'			=> [ '' ]
					),
				),
				array(
					'type'			=> 'dropdown',
					'heading'		=> esc_html__('Send method', 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name'	=> 'method',
					'std'			=> 'post',
					'value'			=> array(
						'post'			=> 'post',
						'get'			=> 'get',
					),
					'dependency'	=> array(
						'element'		=> 'ajax',
						'value'			=> [ '' ]
					),
				),
				array(
					'type'			=> 'dropdown',
					'heading'		=> esc_html__('Target', 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name'	=> 'target',
					'std'			=> '_blank',
					'value'			=> array(
						'_blank'		=> '_blank',
						'_self'			=> '_self',
					),
					'dependency'	=> array(
						'element'		=> 'ajax',
						'value'			=> [ '' ]
					),
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
					'settings' 		=> array( 'width', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_overall_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_overall_mobile' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_input',
					"heading"     	=> esc_html__( "Input", 'codevz-plus' ),
					'button' 		=> esc_html__( "Input", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'text-align', 'font-size', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_input_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_input_mobile' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_button',
					'hover_id'	 	=> 'sk_button_hover',
					"heading"     	=> esc_html__( "Button", 'codevz-plus' ),
					'button' 		=> esc_html__( "Button", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_button_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_button_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_button_hover' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_icon',
					"heading"     	=> esc_html__( "Icon", 'codevz-plus' ),
					'button' 		=> esc_html__( "Icon", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background' ),
					'dependency'	=> array(
						'element'		=> 'icon',
						'not_empty'		=> true
					),
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_icon_mobile' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_msg',
					"heading"     	=> esc_html__( "Message", 'codevz-plus' ),
					'button' 		=> esc_html__( "Message", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background' ),
					'dependency'	=> array(
						'element'		=> 'ajax',
						'value'			=> [ '2' ]
					),
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_msg_mobile' ),

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
				'sk_overall' 		=> array( $css_id, $custom ),
				'sk_brfx' 			=> $css_id . ':before',
				'sk_input' 			=> $css_id . ' input',
				'sk_button' 		=> $css_id . ' button',
				'sk_button_hover' 	=> $css_id . ' button:hover',
				'sk_icon' 			=> $css_id . ' button i',
				'sk_msg' 			=> $css_id . ' .cz_subscribe_msg',
			);

			$css 	= Codevz_Plus::sk_style( $atts, $css_array );
			$css_t 	= Codevz_Plus::sk_style( $atts, $css_array, '_tablet' );
			$css_m 	= Codevz_Plus::sk_style( $atts, $css_array, '_mobile' );
		} else {
			Codevz_Plus::load_font( $atts['sk_input'] );
			Codevz_Plus::load_font( $atts['sk_button'] );
		}

		// Button
		$btn_title = $atts['icon'] ? '<i class="' . $atts['icon'] . ( $atts['btn_title'] ? ' mr8' : '' ) . '"></i>' . $atts['btn_title'] : $atts['btn_title'];

		// Classes
		$classes = array();
		$classes[] = $atts['id'];
		$classes[] = 'cz_subscribe_elm clr';
		$classes[] = $atts['style'];
		$classes[] = $atts['btn_position'];
		$classes[] = $atts['position'] ? 'cz_subscribe_elm_' . $atts['position'] : '';
		$classes[] = empty( $atts['name'] ) ? '' : 'cz_subscribe_has_name';
		$classes[] = empty( $atts['ajax'] ) ? '' : 'cz_subscribe_ajax';

		// Custom desc
		$content = $content ? '<p class="xtra-subsbcribe-content">' . do_shortcode( $content ) . '</p>' : '';

		if ( ! $atts['action'] ) {
			$atts['action'] = get_site_url();
		}

		$name_input = '';
		$form_name = 'mc-embedded-subscribe-form';

		// Check AJAX method.
		if ( ! empty( $atts['ajax'] ) && $atts['ajax'] === '2' ) {

			$atts['action'] 	= '#';
			$atts['type_attr'] 	= 'email';
			$atts['name_attr'] 	= 'email';
			$atts['method'] 	= 'get';
			$atts['target'] 	= '_self';

			$form_name = 'codevz-plus-subscribe-ajax';

		}

		$name_input  = empty( $atts['name'] ) ? '' : '<input type="text" name="name" placeholder="' . esc_attr( $atts['name'] ) . '" required="required">';
		$email_input = '<input type="' . esc_attr( $atts['type_attr'] ) . '" name="' . esc_attr( $atts['name_attr'] ) . '" placeholder="' . esc_attr( $atts['placeholder'] ) . '" required="required"><input type="text" name="website" placeholder="Your website">';

		// Out
		$out = $content . '<form id="' . esc_attr( $atts['id'] ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'cz_subscribe_nonce' ) ) . '" action="' . esc_url( $atts['action'] ) . '" method="' . esc_attr( $atts['method'] ) . '" name="' . esc_attr( $form_name ) . '" target="' . esc_attr( $atts['target'] ) . '"' . Codevz_Plus::classes( $atts, $classes ) . Codevz_Plus::data_stlye( $css, $css_t, $css_m ) . '><div>' . do_shortcode( $name_input . $email_input ) . '</div><button name="subscribe" type="submit">' . do_shortcode( wp_kses_post( $btn_title ) ) . '</button><span class="clr"></span><p class="cz_subscribe_msg"></p></form><div class="clr"></div>';

		return Codevz_Plus::_out( $atts, $out, 'subscribe', $this->name );
	}

}