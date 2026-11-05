<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Repeater;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Xtra_Elementor_Widget_carousel extends Widget_Base {

	protected $id = 'cz_carousel';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Carousel', 'codevz-plus' );
	}

	public function get_icon() {
		return 'xtra-carousel';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Carousel', 'codevz-plus' ),
			esc_html__( 'Slider', 'codevz-plus' ),

		];

	}

	public function get_style_depends() {
		return [ $this->id, 'cz_parallax' ];
	}

	public function get_script_depends() {
		return [ $this->id, 'cz_parallax' ];
	}

	protected function register_controls() {

		$free = Codevz_Plus::is_free();

		// General
		$this->start_controls_section(
			'section_carousel',
			[
				'label' => esc_html__( 'Carousel', 'codevz-plus' ),
			]
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'type', [
				'label' 	=> esc_html__( 'Content type', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SELECT,
				'options' 	=> [
					'' 			=> esc_html__( 'Content', 'codevz-plus' ),
					'template' 	=> esc_html__( 'Saved template', 'codevz-plus' ),
				]
			]
		);

		$repeater->add_control(
			'xtra_elementor_template',
			[
				'label' 	=> esc_html__( 'Select template', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'options' 	=> Xtra_Elementor::get_templates(),
				'condition' => [
					'type' 		=> 'template'
				],
			]
		);

		$repeater->add_responsive_control(
			'sk_slide',
			[
				'label' 	=> esc_html__( 'Slide Style', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '{{CURRENT_ITEM}}' )
			]
		);

		$repeater->add_control(
			'content', [
				'label' 	=> esc_html__( 'Content', 'codevz-plus' ),
				'type' 		=> Controls_Manager::WYSIWYG,
				'default' 	=> 'Hello World ...',
				'placeholder' => 'Hello World ...',
				'condition' => [
					'type' 		=> ''
				],
			]
		);

		$this->add_control(
			'items',
			[
				'label' 	=> esc_html__( 'Add Slide(s)', 'codevz-plus' ),
				'type' 		=> Controls_Manager::REPEATER,
				'prevent_empty' => false,
				'fields' 	=> $repeater->get_controls(),
				'default' => [
					[
						'content' 	=> '<h4 style="padding:20px;margin: 20px auto;display:table">' . esc_html__( 'Carousel item', 'codevz-plus' ) . ' 1</h4>',
					],
					[
						'content' 	=> '<h4 style="padding:20px;margin: 20px auto;display:table">' . esc_html__( 'Carousel item', 'codevz-plus' ) . ' 2</h4>',
					],
					[
						'content' 	=> '<h4 style="padding:20px;margin: 20px auto;display:table">' . esc_html__( 'Carousel item', 'codevz-plus' ) . ' 3</h4>',
					],
					[
						'content' 	=> '<h4 style="padding:20px;margin: 20px auto;display:table">' . esc_html__( 'Carousel item', 'codevz-plus' ) . ' 4</h4>',
					],
				],
			]
		);

		$this->add_control(
			'carousel_type',
			[
				'label' => esc_html__( 'Type', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'' 	=> esc_html__( '~ Default ~', 'codevz-plus' ),
					'2' => esc_html__( '3D Panorama', 'codevz-plus' ),
				]
			]
		);

		$this->add_control(
			'panorama_notice',
			[
				'type' 			=> Controls_Manager::NOTICE,
				'notice_type' 	=> 'warning',
				'dismissible' 	=> false,
				'heading' 		=> esc_html__( '3D Panorama', 'codevz-plus' ),
				'content' 		=> esc_html__( 'The 3D Panorama effect is only supported when the number of slides to show is set to an odd number (1, 3, 5, or 7), It will not work correctly with even numbers, and same images size recommended', 'codevz-plus' ),
				'condition' 	=> [
					'carousel_type' => '2'
				]
			]
		);

		$this->add_responsive_control(
			'slidestoshow',
			[
				'label' => esc_html__( 'Slides to show', 'codevz-plus' ),
				'type' => Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 10,
				'step' => 1,
				'default' => 3,
				'tablet_default' => 2,
				'mobile_default' => 1,
			]
		);

		$this->add_responsive_control(
			'slidestoscroll',
			[
				'label' => esc_html__( 'Slides to scroll', 'codevz-plus' ),
				'type' => Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 10,
				'step' => 1,
				'default' => 1,
			]
		);

		$this->add_responsive_control(
			'gap',
			[
				'label' => esc_html__( 'Slides gap', 'codevz-plus' ),
				'type' => Controls_Manager::SLIDER,
				'default' => [
					'unit' => 'px',
					'size' => 30,
				],
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 1,
						'max' => 100,
						'step' => 1
					]
				],
				'selectors' => [
					'{{WRAPPER}} .slick-list' 	=> 'margin: 0 -calc({{SIZE}}{{UNIT}} / 2);clip-path:inset(0 calc({{SIZE}}{{UNIT}} / 2) 0 calc({{SIZE}}{{UNIT}} / 2));',
					'{{WRAPPER}} .slick-slide' 	=> 'margin: 0 calc({{SIZE}}{{UNIT}} / 2);',
				],
			]
		);

		$this->add_control(
			'infinite',
			[
				'label' 	=> esc_html__( 'Infinite?', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'autoplay',
			[
				'label' 	=> esc_html__( 'Autoplay?', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'autoplayspeed',
			[
				'label' => esc_html__( 'Autoplay delay (ms)', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::NUMBER,
				'default' => 4000,
				'min' => 1000,
				'max' => 6000,
				'step' => 500,
				'condition' => [
					'autoplay!' => ''
				]
			]
		);

		$this->add_control(
			'centermode',
			[
				'label' 	=> esc_html__( 'Center mode?', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'carousel_type' => ''
				]
			]
		);

		$this->add_control(
			'centerpadding',
			[
				'label' => esc_html__( 'Center padding', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 1,
						'max' => 100,
						'step' => 1,
					],
				],
				'condition' => [
					'centermode!' => '',
					'carousel_type' => ''
				]
			]
		);

		$this->end_controls_section();

		//Arrows
		$this->start_controls_section(
			'section_arrows',
			[
				'label' => esc_html__( 'Arrows', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'arrows_position',
			[
				'label' => esc_html__( 'Arrows position', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'arrows_mlr',
				'options' => [
					'no_arrows' => esc_html__( 'None', 'codevz-plus' ),
					'arrows_tl' => esc_html__( 'Both top left', 'codevz-plus' ),
					'arrows_tc' => esc_html__( 'Both top center', 'codevz-plus' ),
					'arrows_tr' => esc_html__( 'Both top right', 'codevz-plus' ),
					'arrows_tlr' => esc_html__( 'Top left / right', 'codevz-plus' ),
					'arrows_mlr' => esc_html__( 'Middle left / right', 'codevz-plus' ),
					'arrows_blr' => esc_html__( 'Bottom left / right', 'codevz-plus' ),
					'arrows_bl' => esc_html__( 'Both bottom left', 'codevz-plus' ),
					'arrows_bc' => esc_html__( 'Both bottom center', 'codevz-plus' ),
					'arrows_br' => esc_html__( 'Both bottom right', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'arrows_inner',
			[
				'label' 	=> esc_html__( 'Arrows inside carousel?', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'arrows_position' => [
						'arrows_tl',
						'arrows_tc',
						'arrows_tr',
						'arrows_tlr',
						'arrows_mlr',
						'arrows_blr',
						'arrows_bl',
						'arrows_bc',
						'arrows_br',
					],
				],
			]
		);

		$this->add_control(
			'arrows_show_on_hover',
			[
				'label' 	=> esc_html__( 'Show on hover?', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'arrows_position' => [
						'arrows_tl',
						'arrows_tc',
						'arrows_tr',
						'arrows_tlr',
						'arrows_mlr',
						'arrows_blr',
						'arrows_bl',
						'arrows_bc',
						'arrows_br',
					],
				],
			]
		);

		$this->add_control(
			'prev_icon',
			[
				'label' => esc_html__( 'Previous icon', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'skin' => 'inline',
				'label_block' => false,
				'default' => [
					'value' => 'fa fa-chevron-left',
					'library' => 'solid',
				],
				'condition' => [
					'arrows_position' => [
						'arrows_tl',
						'arrows_tc',
						'arrows_tr',
						'arrows_tlr',
						'arrows_mlr',
						'arrows_blr',
						'arrows_bl',
						'arrows_bc',
						'arrows_br',
					],
				],
			]
		);

		$this->add_control(
			'next_icon',
			[
				'label' => esc_html__( 'Next icon', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'skin' => 'inline',
				'label_block' => false,
				'default' => [
					'value' => 'fa fa-chevron-right',
					'library' => 'solid',
				],
				'condition' => [
					'arrows_position' => [
						'arrows_tl',
						'arrows_tc',
						'arrows_tr',
						'arrows_tlr',
						'arrows_mlr',
						'arrows_blr',
						'arrows_bl',
						'arrows_bc',
						'arrows_br',
					],
				],
			]
		);
		
		$this->end_controls_section();

		// Dots
		$this->start_controls_section(
			'section_counts',
			[
				'label' => esc_html__( 'Counts', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'counts',
			[
				'label' => esc_html__( 'Counts', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER
			]
		);
		
		$this->end_controls_section();

		// Dots
		$this->start_controls_section(
			'section_dots',
			[
				'label' => esc_html__( 'Dots', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'dots_position',
			[
				'label' => esc_html__( 'Dots position', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'no_dots',
				'options' => [
					'no_dots' => esc_html__( 'None', 'codevz-plus' ),
					'dots_tl' => esc_html__( 'Top left', 'codevz-plus' ),
					'dots_tc' => esc_html__( 'Top center', 'codevz-plus' ),
					'dots_tr' => esc_html__( 'Top right', 'codevz-plus' ),
					'dots_bl' => esc_html__( 'Bottom left', 'codevz-plus' ),
					'dots_bc' => esc_html__( 'Bottom center', 'codevz-plus' ),
					'dots_br' => esc_html__( 'Bottom right', 'codevz-plus' ),
					'dots_vtl' => esc_html__( 'Vertical top left', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'dots_vml' => esc_html__( 'Vertical middle left', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'dots_vbl' => esc_html__( 'Vertical bottom left', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'dots_vtr' => esc_html__( 'Vertical top right', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'dots_vmr' => esc_html__( 'Vertical middle rigth', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'dots_vbr' => esc_html__( 'Vertical bottom right', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' )
				],
			]
		);

		$this->add_control(
			'dots_style',
			[
				'label' => esc_html__( 'Predefined style', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'options' => [
					'' => esc_html__( '~ Default ~', 'codevz-plus' ),
					'dots_circle' => esc_html__( 'Circle', 'codevz-plus' ),
					'dots_circle dots_circle_2' => esc_html__( 'Circle 2', 'codevz-plus' ),
					'dots_circle_outline' => esc_html__( 'Circle outline', 'codevz-plus' ),
					'dots_square' => esc_html__( 'Square', 'codevz-plus' ),
					'dots_lozenge' => esc_html__( 'Lozenge', 'codevz-plus' ),
					'dots_tiny_line' => esc_html__( 'Tiny line', 'codevz-plus' ),
					'dots_drop' => esc_html__( 'Drop', 'codevz-plus' ),
				],
				'condition' => [
					'dots_position' => [
						'dots_tl',
						'dots_tc',
						'dots_tr',
						'dots_bl',
						'dots_bc',
						'dots_br',
						'dots_vtl',
						'dots_vml',
						'dots_vbl',
						'dots_vtr',
						'dots_vmr',
						'dots_vbr',
					],
				],
			]
		);

		$this->add_control(
			'dots_inner',
			[
				'label' => esc_html__( 'Dots inside carousel?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'dots_position' => [
						'dots_tl',
						'dots_tc',
						'dots_tr',
						'dots_bl',
						'dots_bc',
						'dots_br',
						'dots_vtl',
						'dots_vml',
						'dots_vbl',
						'dots_vtr',
						'dots_vmr',
						'dots_vbr',
					],
				],
			]
		);

		$this->add_control(
			'dots_show_on_hover',
			[
				'label' => esc_html__( 'Show on hover?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'dots_position' => [
						'dots_tl',
						'dots_tc',
						'dots_tr',
						'dots_bl',
						'dots_bc',
						'dots_br',
						'dots_vtl',
						'dots_vml',
						'dots_vbl',
						'dots_vtr',
						'dots_vmr',
						'dots_vbr',
					],
				],
			]
		);

		$this->end_controls_section();

		//Column
		$this->start_controls_section(
			'section_more_settings',
			[
				'label' => esc_html__( 'More settings', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'overflow_visible',
			[
				'label' => esc_html__( 'Overflow visible?', 'codevz-plus' ),
				'type' => Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'fade',
			[
				'label' => esc_html__( 'Fade mode?', 'codevz-plus' ),
				'description' => esc_html__('Only works when slide to show is 1', 'codevz-plus' ),
				'type' => Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'mousewheel',
			[
				'label' => esc_html__( 'MouseWheel?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'disable_links',
			[
				'label' => esc_html__( 'Disable slides links?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'variablewidth',
			[
				'label' => esc_html__( 'Auto width detection?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'vertical',
			[
				'label' => esc_html__( 'Vertical?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'rows',
			[
				'label' => esc_html__( 'Number of rows', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 5,
				'step' => 1,
			]
		);

		$this->add_control(
			'even_odd',
			[
				'label' => esc_html__( 'Custom position', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					'' => esc_html__( 'Select', 'codevz-plus' ),
					'even_odd' => esc_html__( 'Even / Odd', 'codevz-plus' ),
					'odd_even' => esc_html__( 'Odd / Even', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'selector', [
				'label' => esc_html__( 'Sync class', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::TEXT
			]
		);

		$this->add_control(
			'sync', [
				'label' => esc_html__( 'Sync to', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::TEXT
			]
		);

		$this->end_controls_section();

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
			'sk_overall',
			[
				'label' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.slick' ),
			]
		);

		$this->add_responsive_control(
			'sk_slides',
			[
				'label' 	=> esc_html__( 'Slides', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'grayscale', 'blur', 'background', 'opacity', 'z-index', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( 'div.slick-slide' ),
			]
		);

		$this->add_responsive_control(
			'sk_center',
			[
				'label' 	=> esc_html__( 'Center slide', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'grayscale', 'background', 'opacity', 'z-index', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( 'div.slick-center' ),
			]
		);

		$this->add_responsive_control(
			'sk_prev_icon',
			[
				'label' 	=> esc_html__( 'Previous icon', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.slick-prev' )
			]
		);

		$this->add_responsive_control(
			'sk_next_icon',
			[
				'label' 	=> esc_html__( 'Next icon', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.slick-next' )
			]
		);

		$this->add_responsive_control(
			'sk_dots_container',
			[
				'label' 	=> esc_html__( 'Dots container', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.slick-dots' ),
				'condition' => [
					'dots_position' => [
						'dots_tl',
						'dots_tc',
						'dots_tr',
						'dots_bl',
						'dots_bc',
						'dots_br',
						'dots_vtl',
						'dots_vml',
						'dots_vbl',
						'dots_vtr',
						'dots_vmr',
						'dots_vbr',
					],
				],
			]
		);

		$this->add_responsive_control(
			'sk_dots',
			[
				'label' 	=> esc_html__( 'Dots', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.slick-dots li button' ),
				'condition' => [
					'dots_position' => [
						'dots_tl',
						'dots_tc',
						'dots_tr',
						'dots_bl',
						'dots_bc',
						'dots_br',
						'dots_vtl',
						'dots_vml',
						'dots_vbl',
						'dots_vtr',
						'dots_vmr',
						'dots_vbr',
					],
				],
			]
		);

		$this->add_responsive_control(
			'sk_counts',
			[
				'label' 	=> esc_html__( 'Counts', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.xtra-slick-counts' ),
				'condition' => [
					'counts!' => '',
				],
			]
		);

		$this->add_responsive_control(
			'sk_counts_numbers',
			[
				'label' 	=> esc_html__( 'Counts numbers', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.xtra-slick-counts .xtra-slick-current, .xtra-slick-counts .xtra-slick-all' ),
				'condition' => [
					'counts!' => '',
				],
			]
		);

		$this->add_responsive_control(
			'sk_counts_seperator',
			[
				'label' 	=> esc_html__( 'Counts seperator', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.xtra-slick-counts .xtra-slick-separator' ),
				'condition' => [
					'counts!' => '',
				],
			]
		);

		$this->end_controls_section();
	}

	public function render() {

		$settings = $this->get_settings_for_display();

		Xtra_Elementor::carousel_elementor( $settings );

	}

}