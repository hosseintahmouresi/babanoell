<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.

/**
 * Image
 * 
 * @author Codevz
 * @link http://codevz.com/
 */

class Codevz_WPBakery_expanding_cards {

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
			'name'			=> esc_html__( 'Expanding Cards', 'codevz-plus' ), 
			'description'	=> esc_html__( 'Toggle expanding cards', 'codevz-plus' ),
			'icon'			=> 'czi',
			'params'		=> array(
				array(
					'type' 			=> 'cz_sc_id',
					'param_name' 	=> 'id',
					'save_always' 	=> true
				),
				array(
					'type' 			=> 'param_group',
					'heading' 		=> esc_html__( 'Cards', 'codevz-plus' ),
					'param_name' 	=> 'items',
					'params' 		=> array(
						array(
							"type" 				=> "attach_image",
							"heading" 			=> esc_html__("Card image",'codevz-plus'),
							"param_name" 		=> "card_image",
							'edit_field_class' 	=> 'vc_col-xs-99'
						),
						array(
							'type' 			=> 'textfield',
							'heading' 		=> esc_html__( 'Title', 'codevz-plus' ),
							'param_name' 	=> 'title',
							'value'			=> 'This is card headline',
							'edit_field_class' => 'vc_col-xs-99',
							'admin_label'	=> true
						),
						array(
							'type' 			=> 'textfield',
							'heading' 		=> esc_html__( 'Subtitle', 'codevz-plus' ),
							'value'			=> esc_html__( 'This is card subtitle', 'codevz-plus' ),
							'edit_field_class' => 'vc_col-xs-99',
							'param_name' 	=> 'subtitle',
						),
						array(
							"type" 			=> "dropdown",
							"holder" 		=> "div",
							"heading" 		=> esc_html__("Icon type",'codevz-plus'),
							"param_name" 	=> "icon_type",
							'edit_field_class' => 'vc_col-xs-99',
							"value" 	=> array(
								esc_html__( "Icon",'codevz-plus' ) => 'icon',
								esc_html__( "Image",'codevz-plus' ) => 'image',
								esc_html__( "Number",'codevz-plus' ) => 'number',
							),
						),
						array(
							"type"        	=> "cz_icon",
							"heading"     	=> esc_html__("Icon", 'codevz-plus' ),
							'edit_field_class' => 'vc_col-xs-99',
							"param_name"  	=> "icon",
							'dependency'	=> array(
								'element'		=> 'icon_type',
								'value'			=> array( 'icon' )
							)
						),
						array(
							"type" => "attach_image",
							"heading" => esc_html__("Image",'codevz-plus'),
							"param_name" => "image",
							'edit_field_class' => 'vc_col-xs-99',
							'dependency' => array(
								'element' 	=> 'icon_type',
								'value' 	=> array( 'image' )
							),
						),
						array(
							"type" => "textfield",
							"heading" => esc_html__("Number",'codevz-plus'),
							"param_name" => "number",
							'edit_field_class' => 'vc_col-xs-99',
							'dependency' => array(
								'element' 	=> 'icon_type',
								'value' 	=> array( 'number' )
							),
						),
						array(
							'type' 			=> 'cz_sk',
							'param_name' 	=> 'sk_icon',
							"heading"     	=> esc_html__( "Icon", 'codevz-plus' ),
							'button' 		=> esc_html__( "Icon", 'codevz-plus' ),
							"edit_field_class" => 'vc_col-xs-99',
							'settings' 		=> array( 'color', 'background', 'border', 'box-shadow', 'opacity' )
						),
					),
					'value' => rawurlencode( wp_json_encode( [
						[
							'card_image' 	=> 'https://66.media.tumblr.com/6fb397d822f4f9f4596dff2085b18f2e/tumblr_nzsvb4p6xS1qho82wo1_1280.jpg',
							'title' 		=> esc_html__( 'Card title', 'codevz-plus' ),
							'subtitle' 		=> esc_html__( 'This is card subtitle', 'codevz-plus' ),
							'icon_type' 	=> 'icon',
							'icon' 			=> 'fas fa-walking',
							'sk_icon' 		=> 'color:#FC6E51;',
						],
						[
							'card_image' 	=> 'https://66.media.tumblr.com/8b69cdde47aa952e4176b4200052abf4/tumblr_o51p7mFFF21qho82wo1_1280.jpg',
							'title' 		=> esc_html__( 'Card title', 'codevz-plus' ),
							'subtitle' 		=> esc_html__( 'This is card subtitle', 'codevz-plus' ),
							'icon_type' 	=> 'icon',
							'icon' 			=> 'fas fa-snowflake',
							'sk_icon' 		=> 'color:#ED5565;',
						],
						[
							'card_image' 	=> 'https://66.media.tumblr.com/5af3f8303456e376ceda1517553ba786/tumblr_o4986gakjh1qho82wo1_1280.jpg',
							'title' 		=> esc_html__( 'Card title', 'codevz-plus' ),
							'subtitle' 		=> esc_html__( 'This is card subtitle', 'codevz-plus' ),
							'icon_type' 	=> 'icon',
							'icon' 			=> 'fas fa-tree',
							'sk_icon' 		=> 'color:#FFCE54;',
						],
						[
							'card_image' 	=> 'https://66.media.tumblr.com/f19901f50b79604839ca761cd6d74748/tumblr_o65rohhkQL1qho82wo1_1280.jpg',
							'title' 		=> esc_html__( 'Card title', 'codevz-plus' ),
							'subtitle' 		=> esc_html__( 'This is card subtitle', 'codevz-plus' ),
							'icon_type' 	=> 'icon',
							'icon' 			=> 'fas fa-sun',
							'sk_icon' 		=> 'color:#AC92EC;',
						],
					] ) ),
				),
				array(
					"type" 			=> "dropdown",
					"holder" 		=> "div",
					"heading" 		=> esc_html__("Type",'codevz-plus'),
					"param_name" 	=> "cards_type",
					'edit_field_class' => 'vc_col-xs-99',
					"value" 	=> array(
						esc_html__( "On click",'codevz-plus' ) 	=> '',
						esc_html__( "On hover",'codevz-plus' ) 	=> 'codevz-plus-ex-cards-hover'
					),
				),
				array(
					"type" 			=> "dropdown",
					"holder" 		=> "div",
					"heading" 		=> esc_html__("Radius",'codevz-plus'),
					"param_name" 	=> "cards_radius",
					'edit_field_class' => 'vc_col-xs-99',
					"value" 	=> array(
						esc_html__( "Round 1",'codevz-plus' ) 	=> '',
						esc_html__( "Round 2",'codevz-plus' ) 	=> 'codevz-plus-ex-cards-round-2',
						esc_html__( "Round 3",'codevz-plus' ) 	=> 'codevz-plus-ex-cards-round-3',
						esc_html__( "Round 4",'codevz-plus' ) 	=> 'codevz-plus-ex-cards-round-4',
						esc_html__( "Square",'codevz-plus' ) 	=> 'codevz-plus-ex-cards-square',
					),
				),
				array(
					"type" 			=> "dropdown",
					"holder" 		=> "div",
					"heading" 		=> esc_html__("Label position",'codevz-plus'),
					"param_name" 	=> "cards_label_position",
					'edit_field_class' => 'vc_col-xs-99',
					"value" 	=> array(
						esc_html__( "Bottom",'codevz-plus' ) 	=> '',
						esc_html__( "Top",'codevz-plus' ) 		=> 'codevz-plus-ex-cards-label-top',
					),
				),
				array(
					"type" 			=> "dropdown",
					"holder" 		=> "div",
					"heading" 		=> esc_html__("Background size",'codevz-plus'),
					"param_name" 	=> "cards_size",
					'edit_field_class' => 'vc_col-xs-99',
					'std' 			=> 'codevz-plus-ex-cards-auto-2',
					'default' 		=> 'codevz-plus-ex-cards-auto-2',
					"value" 		=> array(
						esc_html__( "Cover",'codevz-plus' ) 		=> '',
						esc_html__( "Contain",'codevz-plus' ) 		=> 'codevz-plus-ex-cards-contain',
						esc_html__( "Auto",'codevz-plus' ) 			=> 'codevz-plus-ex-cards-auto',
						esc_html__( "Auto + 100%",'codevz-plus' ) 	=> 'codevz-plus-ex-cards-auto-2',
						esc_html__( "100% + 100%",'codevz-plus' ) 	=> 'codevz-plus-ex-cards-auto-3',
					),
				),

				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Styling', 'codevz-plus' )
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_con',
					"heading"     	=> esc_html__( "Container", 'codevz-plus' ),
					'button' 		=> esc_html__( "Container", 'codevz-plus' ),
					"edit_field_class" => 'vc_col-xs-99',
					'settings' 		=> array( 'background', 'width', 'height', 'border', 'box-shadow', 'opacity' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_con_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_con_mobile' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_cards',
					"heading"     	=> esc_html__( "Cards", 'codevz-plus' ),
					'button' 		=> esc_html__( "Cards", 'codevz-plus' ),
					"edit_field_class" => 'vc_col-xs-99',
					'settings' 		=> array( 'background', 'border', 'box-shadow', 'opacity' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_cards_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_cards_mobile' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_icons',
					"heading"     	=> esc_html__( "Icons", 'codevz-plus' ),
					'button' 		=> esc_html__( "Icons", 'codevz-plus' ),
					"edit_field_class" => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background', 'border', 'box-shadow', 'opacity' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_icons_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_icons_mobile' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_title',
					"heading"     	=> esc_html__( "Title", 'codevz-plus' ),
					'button' 		=> esc_html__( "Title", 'codevz-plus' ),
					"edit_field_class" => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'font-style', 'background', 'border', 'box-shadow', 'opacity' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_title_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_title_mobile' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_subtitle',
					"heading"     	=> esc_html__( "Subtitle", 'codevz-plus' ),
					'button' 		=> esc_html__( "Subtitle", 'codevz-plus' ),
					"edit_field_class" => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'font-style', 'background', 'border', 'box-shadow', 'opacity' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_subtitle_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_subtitle_mobile' ),

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

			$css_array = array(
				'sk_con' 			=> $css_id,
				'sk_cards' 			=> $css_id . ' .codevz-plus-ex-card',
				'sk_icons' 			=> $css_id . ' .codevz-plus-ex-card-icon',
				'sk_title' 			=> $css_id . ' .codevz-plus-ex-card-info strong',
				'sk_subtitle' 		=> $css_id . ' .codevz-plus-ex-card-info small',
			);

			$css 	= Codevz_Plus::sk_style( $atts, $css_array );
			$css_t 	= Codevz_Plus::sk_style( $atts, $css_array, '_tablet' );
			$css_m 	= Codevz_Plus::sk_style( $atts, $css_array, '_mobile' );

			$css .= $atts['anim_delay'] ? $css_id . '{animation-delay:' . $atts['anim_delay'] . '}' : '';

		}

		// Classes
		$classes = array();
		$classes[] = $atts['id'];
		$classes[] = 'codevz-plus-ex-cards clr';
		$classes[] = $atts['cards_type'];
		$classes[] = $atts['cards_size'];
		$classes[] = $atts['cards_radius'];
		$classes[] = $atts['cards_label_position'];

		ob_start();

		// Items.
		$items = json_decode( urldecode( $atts[ 'items' ] ), true );
		foreach( (array) $items as $key => $i ) {

			$card_image = '';

			if ( ! empty( $i['card_image'] ) ) {

				if ( Codevz_Plus::contains( $i['card_image'], '.' ) ) {
					$card_image = $i['card_image'];
				} else {
					$card_image = Codevz_Plus::get_image( $i['card_image'], 'full', 1 );
				}

			}

			echo '<div class="codevz-plus-ex-card' . ( $key == 0 ? ' codevz-plus-ex-card-active' : '' ) . '" style="background-image:url(' . esc_url( $card_image ) . ');">';
			echo '<div class="codevz-plus-ex-card-label">';

			$style = $i['sk_icon'] ? ' style="' . Codevz_Plus::sk_inline_style( $i['sk_icon'] ) . '"' : '';

			echo '<div class="codevz-plus-ex-card-icon codevz-plus-ex-card-icon-' . esc_attr( $i['icon_type'] ) . '"' . wp_kses_post( $style ) . '>';

			if ( isset( $i['icon_type'] ) && $i['icon_type'] === 'image' && ! empty( $i['image'] ) ) {
				echo Codevz_Plus::get_image( $i['image'], 'full', 0 );
			} else if ( isset( $i['icon_type'] ) && $i['icon_type'] === 'number' && ! empty( $i['number'] ) ) {
				echo $i['number'];
			} else if ( ! empty( $i['icon'] ) ) {
				echo '<i class="' . $i['icon'] . '"></i>';
			}

			echo '</div>';
			echo '<div class="codevz-plus-ex-card-info">';

			echo empty( $i['title'] ) ? '' : '<strong>' . wp_kses_post( $i['title'] ) . '</strong>';
			echo empty( $i['subtitle'] ) ? '' : '<small>' . wp_kses_post( $i['subtitle'] ) . '</small>';

			echo '</div>';
			echo '</div>';
			echo '</div>';
		}

		$out = ob_get_clean();

		// Out
		$out = '<div id="' . $atts['id'] . '" ' . Codevz_Plus::classes( $atts, $classes ) . Codevz_Plus::data_stlye( $css, $css_t, $css_m ) . '>' . $out . '</div>';

		return Codevz_Plus::_out( $atts, $out, 'expanding_cards', $this->name );
	}
}