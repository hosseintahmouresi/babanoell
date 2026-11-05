<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Codevz_Plus as Codevz_Plus;
use Elementor\Utils;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Xtra_Elementor_Widget_content_box extends Widget_Base { 

	protected $id = 'cz_content_box';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Content Box', 'codevz-plus' );
	}
	
	public function get_icon() {
		return 'xtra-content-box';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Content', 'codevz-plus' ),
			esc_html__( 'Box', 'codevz-plus' ),

		];

	}

	public function get_style_depends() {
		return [ $this->id, 'cz_parallax' ];
	}

	public function get_script_depends() {
		return [ $this->id, 'cz_parallax' ];
	}

	public function register_controls() {

		$free = Codevz_Plus::is_free();

		$this->start_controls_section(
			'section_content',
			[
				'label' => esc_html__( 'Settings', 'codevz-plus' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'content_type', [
				'label' 	=> esc_html__( 'Content type', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SELECT,
				'options' 	=> [
					'' 			=> esc_html__( 'Content', 'codevz-plus' ),
					'template' 	=> esc_html__( 'Saved template', 'codevz-plus' ),
				],
				'default' 	=> 'template'
			]
		);

		$this->add_control(
			'xtra_elementor_template',
			[
				'label' 	=> esc_html__( 'Select template', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SELECT,
				'options' 	=> Xtra_Elementor::get_templates(),
				'condition' => [
					'content_type' 		=> 'template'
				],
			]
		);

		$this->add_control(
			'content', [
				'label' 	=> esc_html__( 'Content', 'codevz-plus' ),
				'type' 		=> Controls_Manager::WYSIWYG,
				'condition' => [
					'content_type' 		=> ''
				],
			]
		);

		$this->add_control(
			'shape',
			[
				'label' => esc_html__( 'Background stretch', 'codevz-plus' ),
				'description' => esc_html__( 'Background color for container is required.', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					'' 								=> esc_html__( '~ Select ~', 'codevz-plus' ),
					'cz_content_box_full_stretch' 	=> esc_html__( 'Stretch full', 'codevz-plus' ),
					'cz_content_box_full_before' 	=> esc_html__( 'Stretch to left', 'codevz-plus' ),
					'cz_content_box_full_after' 	=> esc_html__( 'Stretch to right', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'type',
			[
				'label' => esc_html__( 'Box type', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => '1',
				'options' => [
					'1'  => esc_html__( '~ Default ~', 'codevz-plus' ),
					'2' => esc_html__( 'Split box with image', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'link',
			[
				'label' => esc_html__( 'Clickable box?', 'codevz-plus' ),
				'type' => Controls_Manager::URL
			]
		);

		$this->add_control(
			'split_box_image',
			[
				'label' => esc_html__( 'Image', 'codevz-plus' ),
				'type' => Controls_Manager::MEDIA,
				'condition' => [
					'type' => '2',
				],
			]
		);

		$this->add_control(
			'split_box_position',
			[
				'label' => esc_html__( 'Image position', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => 'cz_split_box_right',
				'options' => [
					'cz_split_box_right'  => esc_html__( 'Right', 'codevz-plus' ),
					'cz_split_box_left'  => esc_html__( 'Left', 'codevz-plus' ),
					'cz_split_box_top'  => esc_html__( 'Top', 'codevz-plus' ),
					'cz_split_box_bottom'  => esc_html__( 'Bottom', 'codevz-plus' ),
					'cz_split_box_right cz_split_box_q'  => esc_html__( 'Right one third', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'cz_split_box_left cz_split_box_q'  => esc_html__( 'Left one third', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
				],
				'condition' => [
					'type' => '2',
				]
			]
		);
		
		$this->add_control(
			'split_box_hide_arrow',
			[
				'label' => esc_html__( 'Hide box arrow?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'type' => '2',
				],
			]
		);
		$this->end_controls_section();

		// Inverted radius controls.
		Xtra_Elementor::inverted_radius_controls( $this );

		$this->start_controls_section(
			'section_hover_effect',
			[
				'label' => esc_html__( 'Hover Effect', 'codevz-plus' )
			]
		);

		$this->add_control(
			'fx',
			[
				'label' => esc_html__( '~ Default ~', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'options' => array_flip( Codevz_Plus::fx() )
			]
		);
		
		$this->add_control(
			'fx_hover',
			[
				'label' 	=> esc_html__( 'Hover', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SELECT,
				'options' 	=> array_flip( Codevz_Plus::fx( '_hover' ) )
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_flip',
			[
				'label' => esc_html__( 'Flip', 'codevz-plus' ),
				'condition' => [
					'type' => '1',
				]
			]
		);

		$this->add_control(
			'back_box',
			[
				'label' => esc_html__( 'Back box?', 'codevz-plus' ),
				'type' => Controls_Manager::SWITCHER,
				'condition' => [
					'type' => '1',
				],
			]
		);
		
		$this->add_control(
			'back_title',
			[
				'label' => esc_html__( "Title", 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'condition' => [
					'type' => '1',
				]
			]
		);

		$this->add_control(
			'back_content',
			[
				'label' => esc_html__( 'Content', 'codevz-plus' ),
				'type' => Controls_Manager::TEXTAREA,
				'rows' => 10,
				'condition' => [
					'type' => '1',
				],
			]
		);

		$this->add_control(
			'back_btn_title',
			[
				'label' => esc_html__( "Button Title", 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'condition' => [
					'type' => '1',
				]
			]
		);

		$this->add_control(
			'back_btn_link',
			[
				'label' => esc_html__( 'Button Link', 'codevz-plus' ),
				'type' => Controls_Manager::URL,
				'default' 	=> [ 'url' => '#' ],
				'condition' => [
					'type' => '1',
				]
			]
		);

		$this->add_control(
			'back_content_position',
			[
				'label' => esc_html__( 'Content position', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => 'cz_box_back_pos_mc',
				'options' => [
					'cz_box_back_pos_tl'  => esc_html__( 'Top Left', 'codevz-plus' ),
					'cz_box_back_pos_tc'  => esc_html__( 'Top Center', 'codevz-plus' ),
					'cz_box_back_pos_tr'  => esc_html__( 'Top Right', 'codevz-plus' ),
					'cz_box_back_pos_ml'  => esc_html__( 'Middle Left', 'codevz-plus' ),
					'cz_box_back_pos_mc'  => esc_html__( 'Middle Center', 'codevz-plus' ),
					'cz_box_back_pos_mr'  => esc_html__( 'Middle Right', 'codevz-plus' ),
					'cz_box_back_pos_bl'  => esc_html__( 'Buttom Left', 'codevz-plus' ),
					'cz_box_back_pos_bc'  => esc_html__( 'Buttom Center', 'codevz-plus' ),
					'cz_box_back_pos_br'  => esc_html__( 'Buttom Right', 'codevz-plus' ),
				],
				'condition' => [
					'type' => '1',
				]
			]
		);

		$this->add_control(
			'fx_backed',
			[
				'label' => esc_html__( 'Hover effect', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'options' => [
					'fx_flip_h'  => esc_html__( 'Flip Horizontal', 'codevz-plus' ),
					'fx_flip_v'  => esc_html__( 'Flip Vertical', 'codevz-plus' ),
					'fx_backed_fade_out_in'  => esc_html__( 'Fade Out/In', 'codevz-plus' ),
					'fx_backed_fade_to_top'  => esc_html__( 'Fade To Top', 'codevz-plus' ),
					'fx_backed_fade_to_bottom'  => esc_html__( 'Fade To Bottom', 'codevz-plus' ),
					'fx_backed_fade_to_left'  => esc_html__( 'Fade To Left', 'codevz-plus' ),
					'fx_backed_fade_to_right'  => esc_html__( 'Fade To Right', 'codevz-plus' ),
					'fx_backed_zoom_in'  => esc_html__( 'Zoom In', 'codevz-plus' ),
					'fx_backed_zoom_out'  => esc_html__( 'Zoom Out', 'codevz-plus' ),
					'fx_backed_bend_in'  => esc_html__( 'Bend In', 'codevz-plus' ),
					'fx_backed_blurred'  => esc_html__( 'Blurred', 'codevz-plus' ),
				],
				'condition' => [
					'type' => '1',
				]
			]
		);

		$this->end_controls_section();

		// Tilt controls.
		Xtra_Elementor::tilt_controls( $this );

		// Parallax settings.
		Xtra_Elementor::parallax_settings( $this );

		$this->start_controls_section(
			'section_style',
			[
				'label' => esc_html__( 'Style', 'codevz-plus' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'sk_wrap',
			[
				'label' 	=> esc_html__( 'Wrap', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'background', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_content_box' ),
				'condition' => [
					'type' => '2',
				],
			]
		);

		$this->add_responsive_control(
			'sk_overall',
			[
				'label' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_box_front_inner' ),
			]
		);

		$this->add_responsive_control(
			'sk_content',
			[
				'label' 	=> esc_html__( 'Content', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_box_front_inner .xtra-cb-content' ),
				'condition' => [
					'content_type!' => 'template'
				],
			]
		);

		$this->add_responsive_control(
			'sk_image',
			[
				'label' 	=> esc_html__( 'Image', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'background', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( ' .cz_split_box, .cz_split_box img' ),
				'condition' => [
					'type' => '2',
				],
			]
		);

		$this->add_responsive_control(
			'svg_bg',
			[
				'label' 	=> esc_html__( 'Background layer', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'background', 'top', 'left', 'rotate' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_svg_bg:before' ),
			]
		);

		$this->add_responsive_control(
			'sk_back',
			[
				'label' 	=> esc_html__( 'Back', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_box_back_inner' ),
				'condition' => [
					'type' => '1',
				]
			]
		);

		$this->add_responsive_control(
			'sk_back_in',
			[
				'label' 	=> esc_html__( 'Back Content', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_box_back_inner_position' ),
				'condition' => [
					'type' => '1',
				]
			]
		);

		$this->add_responsive_control(
			'sk_back_title',
			[
				'label' 	=> esc_html__( 'Back Title', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_box_back_title' ),
				'condition' => [
					'type' => '1',
				]
			]
		);

		$this->add_responsive_control(
			'sk_back_btn',
			[
				'label' 	=> esc_html__( 'Back Button', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_box_back_btn' ),
				'condition' => [
					'type' => '1',
				]
			]
		);

		$this->end_controls_section();
	}

	public function render() {
		
		$settings = $this->get_settings_for_display();

		$split_before = $split_after = $split_pos = '';

		if ( $settings[ 'content_type' ] === 'template' || empty( $settings[ 'content' ] ) ) {

			$content = Codevz_Plus::get_page_as_element( $settings[ 'xtra_elementor_template' ] );

		} else {

			$content = do_shortcode( $settings[ 'content' ] );

		}

		// Front
		$front = '<div class="cz_box_front clr"><div class="cz_box_front_inner clr ' . $settings['shape'] . '">' .( $settings['split_box_hide_arrow'] ? '' : '<span></span>' ) . '<div class="xtra-cb-content">' . $content . '</div></div></div>';

		// Split box
		if ( $settings['type'] === '2' ) {
			$split_pos = $settings['split_box_position'];
			$split_img = isset( $settings['split_box_image']['url'] ) ? $settings['split_box_image']['url'] : '';
			if ( $split_pos === 'cz_split_box_top' || $split_pos === 'cz_split_box_bottom' ) {
				$split = '<div class="cz_split_box"><img src="' . $split_img . '" alt="#" /></div>';
			} else {
				$split = '<div class="cz_split_box" style="background-image: url(' . $split_img . ')"></div>';
			}

			if ( Codevz_Plus::contains( $split_pos, array( 'cz_split_box_right', 'cz_split_box_bottom' ) ) ) {
				$split_after = $split;
			} else {
				$split_before = $split;
			}
		}

		$this->add_link_attributes( 'link', $settings['link'] );
		$link = $this->get_render_attribute_string( 'link' );

		$back_btn_link = '';
		if ( $settings['back_btn_link'] ) {
			$this->add_link_attributes( 'back_btn_link', $settings['back_btn_link'] );
			$back_btn_link = $this->get_render_attribute_string( 'back_btn_link' );
		}

		// Backed
		$backed = '';
		if ( $settings['back_box'] && ! $split_pos ) {
			$backed_btn = $settings['back_btn_title'] ? '<a class="cz_box_back_btn" ' . $back_btn_link . '>' . $settings['back_btn_title'] . '</a>' : '';
			$backed = '<div class="cz_box_back clr">
				<div class="cz_box_back_inner clr">
					<div>
						<div class="cz_box_back_inner_position">
							<div class="cz_box_back_title">' . $settings['back_title'] . '</div>
							<div class="cz_box_back_content">' . Codevz_Plus::fix_extra_p( $settings['back_content'] ) . '</div>
							' . $backed_btn .'
						</div>
					</div>
				</div>
			</div>';
		}

		// Parent box classes
		$classes = array();
		$classes[] = 'cz_content_box cz_svg_bg clr';
		$classes[] = $split_pos;
		$classes[] = $settings['split_box_hide_arrow'] ? 'cz_box_hide_arrow' : '';
		$classes[] = empty( $settings['ir_position'] ) ? '' : 'codevz-plus-inverted-radius-' . esc_attr( $settings['ir_position'] );

		if ( $backed ) {
			$classes[] = $settings['fx_backed'];
			$classes[] = $settings['back_content_position'];
			$classes[] = 'cz_box_backed';
		}

		// All Contents
		$final_content = '<div class="cz_eqh cz_content_box_parent_fx ' . $settings['fx'] . '">';
		$final_content .= $settings['fx_hover'] ? '<div class="' . $settings['fx_hover'] . '">' : '';
		$final_content .= '<div' . Codevz_Plus::classes( [], $classes, 1 ) . Codevz_Plus::tilt( $settings ) . '>' . $split_before . $front . $backed . $split_after . '</div>';
		$final_content .= $settings['fx_hover'] ? '</div>' : '';
		$final_content .= '</div>';

		// Out
		$out = ( Codevz_Plus::contains( $link, 'href' ) && ! Codevz_Plus::contains( $link, '"#"' ) ) ? '<a class="cz_content_box_link"' . $link . '>' . str_replace( array( '<a ', '</a>' ), array( '<div ', '</div>' ), $final_content ) . '</a>' : $final_content;

		Xtra_Elementor::parallax( $settings );

		echo do_shortcode( $out );

		Xtra_Elementor::parallax( $settings, true );

	}

}