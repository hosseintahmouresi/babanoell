<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Cannot access pages directly.

/**
 * Before and After
 * 
 * @author Codevz
 * @link http://codevz.com/
 */

class Codevz_WPBakery_banner_group {

	public $name = false;

	public function __construct( $name ) {
		$this->name = $name;
	}

	/**
	 * Shortcode settings ( vc_map )
	 */
	public function in( $wpb = false ) {
		add_shortcode( $this->name, [ $this, 'out' ] );

		$pages = get_posts( 'post_type="page"&numberposts=-1' );
		$list = array( esc_html__( '~ Select ~', 'codevz-plus' ) => 0 );
		if ( $pages ) {
			foreach ( $pages as $page ) {
				$list[ $page->post_title ] = $page->ID;
			}
		} else {
			$list[ esc_html__( 'Not found', 'codevz-plus' ) ] = 0;
		}

		$settings = array(
			'category'		=> Codevz_Plus::$title,
			'base'			=> $this->name,
			'name'			=> esc_html__( 'Banner Group', 'codevz-plus' ),
			'description'	=> esc_html__( 'Banner Group', 'codevz-plus' ),
			'icon'			=> 'czi',
			'params'		=> array(
				array(
					'type' 			=> 'cz_sc_id',
					'param_name' 	=> 'id',
					'save_always' 	=> true
				),

				array(
					'type' => 'param_group',
					'heading' => esc_html__( 'Item(s)', 'codevz-plus' ),
					'param_name' => 'items',
					'params' => array(
						array(
							"type"        	=> "attach_image",
							"heading"     	=> esc_html__("Background", 'codevz-plus' ),
							"param_name"  	=> "background",
							"edit_field_class" => 'vc_col-xs-99'
						),
						array(
							"type" => "dropdown",
							"holder" => "div",
							"heading" => esc_html__("Content type",'codevz-plus'),
							"param_name" => "type",
							'edit_field_class' => 'vc_col-xs-99',
							"value" => array(
								esc_html__( "Content", 'codevz-plus' ) 			=> '',
								esc_html__( "Saved template", 'codevz-plus' ) 	=> 'content',
							),
						),
						array(
							'type' 			=> 'textarea',
							'heading' 		=> esc_html__( 'Content', 'codevz-plus' ),
							'param_name' 	=> 'content',
							'value' 		=> $list,
							'edit_field_class' => 'vc_col-xs-99',
							'dependency'	=> array(
								'element'				=> 'type',
								'value_not_equal_to'	=> [ 'content' ]
							),
						),
						array(
							'type' 			=> 'dropdown',
							'heading' 		=> esc_html__( 'Select content', 'codevz-plus' ),
							'param_name' 	=> 'template',
							'value' 		=> $list,
							'edit_field_class' => 'vc_col-xs-99',
							'dependency'	=> array(
								'element'		=> 'type',
								'value'			=> [ 'content' ]
							),
						),
						array(
							"type" => "dropdown",
							"holder" => "div",
							"heading" => esc_html__("Icon type",'codevz-plus'),
							"param_name" => "icon_type",
							'edit_field_class' => 'vc_col-xs-99',
							"value" => array(
								esc_html__( "Select", 'codevz-plus' ) 	=> '',
								esc_html__( "Icon", 'codevz-plus' ) 	=> 'icon',
								esc_html__( "Image", 'codevz-plus' ) 	=> 'image',
							),
						),
						array(
							"type"        	=> "cz_icon",
							"heading"     	=> esc_html__("Icon", 'codevz-plus' ),
							'edit_field_class' => 'vc_col-xs-99',
							"param_name"  	=> "icon",
							'value' 		=> 'fa fa-globe',
							'dependency'	=> array(
								'element'		=> 'icon_type',
								'value'			=> [ 'icon' ]
							),
						),
						array(
							"type"        	=> "attach_image",
							"heading"     	=> esc_html__("Image", 'codevz-plus' ),
							"param_name"  	=> "image",
							"edit_field_class" => 'vc_col-xs-99',
							'dependency'	=> array(
								'element'		=> 'icon_type',
								'value'			=> [ 'image' ]
							),
						),
						array(
							"type"        	=> "textfield",
							"heading"     	=> esc_html__("Link", 'codevz-plus' ),
							'edit_field_class' => 'vc_col-xs-99',
							"param_name"  	=> "link"
						),
					)
				),
				array(
					"type"        	=> "cz_slider",
					"heading"     	=> esc_html__("Minimum height", 'codevz-plus' ),
					"param_name"  	=> "min_height",
					'options' 		=> array( 'unit' => 'px', 'step' => 1, 'min' => 100, 'max' => 1200 ),
					'edit_field_class' => 'vc_col-xs-99'
				),
				array(
					"type"        	=> "checkbox",
					"heading"     	=> esc_html__("Show default", 'codevz-plus' ),
					"param_name"  	=> "show_default_item",
					'edit_field_class' => 'vc_col-xs-99'
				),
				array(
					"type"        	=> "checkbox",
					"heading"     	=> esc_html__("Hide items on hover", 'codevz-plus' ),
					"param_name"  	=> "not_visible",
					'edit_field_class' => 'vc_col-xs-99'
				),
				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Styling', 'codevz-plus' ),
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_container',
					"heading"     	=> esc_html__( "Container", 'codevz-plus' ),
					'button' 		=> esc_html__( "Container", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'background', 'border', 'box-shadow' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_container_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_container_mobile' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_items',
					"heading"     	=> esc_html__( "Items", 'codevz-plus' ),
					'button' 		=> esc_html__( "Items", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'text-align', 'font-size', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_items_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_items_mobile' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_content',
					"heading"     	=> esc_html__( "Content", 'codevz-plus' ),
					'button' 		=> esc_html__( "Content", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_content_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_content_mobile' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_icon',
					"heading"     	=> esc_html__( "Icon", 'codevz-plus' ),
					'button' 		=> esc_html__( "Icon", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'font-family', 'font-size', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_icon_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_icon_mobile' ),

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
	 * Shortcode output
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
			$custom_item = $atts['min_height'] ? 'min-height:' . $atts['min_height'] . ';' : '';

			$css_array = array(
				'sk_container' 	=> [ $css_id, $custom ],
				'sk_items' 		=> [ $css_id . ' .xtra-banner-group-item', $custom_item ],
				'sk_content' 	=> $css_id . ' .xtra-banner-group-item > div',
				'sk_icon' 		=> $css_id . ' .xtra-banner-group-icon',
			);

			$css 	= Codevz_Plus::sk_style( $atts, $css_array );
			$css_t 	= Codevz_Plus::sk_style( $atts, $css_array, '_tablet' );
			$css_m 	= Codevz_Plus::sk_style( $atts, $css_array, '_mobile' );
		} else {
			Codevz_Plus::load_font( $atts['sk_items'] );
			Codevz_Plus::load_font( $atts['sk_content'] );
		}

		// Classes
		$classes = [];
		$classes[] = $atts['id'];
		$classes[] = 'xtra-banner-group clr';
		$classes[] = $atts[ 'show_default_item' ] ? 'codevz-banner-group-show-default' : '';
		$classes[] = $atts[ 'not_visible' ] ? 'xtra-banner-group-not-visible' : '';

		// Items.
		$items = (array) json_decode( urldecode( $atts[ 'items' ] ), true );
		ob_start();

		foreach( $items as $index => $item ) {

			$item = wp_parse_args( $item,
				[
					'background' 	=> '',
					'type' 			=> '',
					'content' 		=> '',
					'template' 		=> '',
					'icon_type' 	=> '',
					'icon' 			=> '',
					'image' 		=> '',
					'link' 			=> '',
				]
			);

			$icon = '';

			if ( $item['icon_type'] === 'image' ) {

				$icon = '<i class="cz-banner-group-icon">' . Codevz_Plus::get_image( $item['image'] ) . '</i>';

			} else if ( $item['icon_type'] === 'icon' && $item['icon'] ) {

				$icon = '<i class="cz-banner-group-icon ' . esc_attr( $item['icon'] ) . '"></i>';

			}

			if ( $item[ 'type' ] === 'template' ) {

				$content = $item[ 'template' ] ? Codevz_Plus::get_page_as_element( $item[ 'template' ] ) : '';

			} else {

				$content = '<p>' . do_shortcode( $item[ 'content' ] ) . '</p>';

			}

			$background = Codevz_Plus::get_image( $item['background'] );

			echo '<a href="' . esc_url( $item['link'] ) . '" class="xtra-banner-group-item xtra-banner-group-item-' . esc_attr( $index ) . '"><div>' . $icon . $content . '</div>' . $background . '</a>';

			$index++;

		}

		$out = ob_get_clean();

		// Out
		$out = '<div id="' . esc_attr( $atts['id'] ) . '"' . Codevz_Plus::classes( $atts, $classes ) . Codevz_Plus::data_stlye( $css, $css_t, $css_m ) . '>' . do_shortcode( $out ) . '</div>';

		return Codevz_Plus::_out( $atts, $out, 'banner_group', $this->name );
	}
}