<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Utils;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Elementor\Icons_Manager;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;

class Xtra_Elementor_Widget_posts extends Widget_Base {

	protected $id = 'cz_posts';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Posts Grid', 'codevz-plus' );
	}

	public function get_icon() {
		return 'xtra-posts';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Grid', 'codevz-plus' ),
			esc_html__( 'Post', 'codevz-plus' ),
			esc_html__( 'Content', 'codevz-plus' ),
			esc_html__( 'News', 'codevz-plus' ),
			esc_html__( 'Magazine', 'codevz-plus' ),

		];

	}

	public function get_style_depends() {

		$array = [ $this->id, 'cz_gallery', 'cz_carousel', 'cz_parallax' ];

		if ( Codevz_Plus::$is_rtl ) {
			$array[] = $this->id . '_rtl';
		}

		return $array;

	}

	public function get_script_depends() {
		return [ $this->id, 'cz_gallery', 'cz_carousel', 'cz_parallax' ];
	}

	public function register_controls() {

		$free = Codevz_Plus::is_free();

		$this->start_controls_section(
			'section_content',
			[
				'label' => esc_html__( 'Layout', 'codevz-plus' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'layout',
			[
				'label' => esc_html__( 'Layout', 'codevz-plus' ),
				'type' => 'image_select',
				'label_block' => true,
				'default' => 'cz_grid_c4',
				'options' => array(
					'cz_justified' => [
						'title'=> 'Justified' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_1.png'
					],
					'cz_grid_c1 cz_grid_l1' => [
						'title'=> '3 ' . esc_html__( 'Rows', 'codevz-plus' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_2.png'
					],
					'cz_grid_c2 cz_grid_l2' => [
						'title'=> '2 ' . esc_html__( 'Columns', 'codevz-plus' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_3.png'
					],
					'cz_grid_c2' => [
						'title'=> '2 ' . esc_html__( 'Columns', 'codevz-plus' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_4.png'
					],
					'cz_grid_c3' => [
						'title'=> '3 ' . esc_html__( 'Columns', 'codevz-plus' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_5.png'
					],
					'cz_grid_c4' => [
						'title'=> '4 ' . esc_html__( 'Columns', 'codevz-plus' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_6.png'
					],
					'cz_grid_c5' => [
						'title'=> '5 ' . esc_html__( 'Columns', 'codevz-plus' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_7.png'
					],
					'cz_grid_c6' => [
						'title'=> '6 ' . esc_html__( 'Columns', 'codevz-plus' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_8.png'
					],
					'cz_grid_c7' => [
						'title'=> '7 ' . esc_html__( 'Columns', 'codevz-plus' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_9.png'
					],
					'cz_grid_c8' => [
						'title'=> '8 ' . esc_html__( 'Columns', 'codevz-plus' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_10.png'
					],
					'cz_hr_grid cz_grid_c2' => [
						'title'=> '2 ' . esc_html__( 'Columns', 'codevz-plus' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_11.png'
					],
					'cz_hr_grid cz_grid_c3' => [
						'title'=> '3 ' . esc_html__( 'Columns', 'codevz-plus' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_12.png'
					],
					'cz_hr_grid cz_grid_c4' => [
						'title'=> '4 ' . esc_html__( 'Columns', 'codevz-plus' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_13.png'
					],
					'cz_hr_grid cz_grid_c5' => [
						'title'=> '5 ' . esc_html__( 'Columns', 'codevz-plus' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_14.png'
					],
					'cz_masonry cz_grid_c2' => [
						'title'=> 'Masonry 2 ' . esc_html__( 'Columns', 'codevz-plus' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_15.png'
					],
					'cz_masonry cz_grid_c3' => [
						'title'=> 'Masonry 3 ' . esc_html__( 'Columns', 'codevz-plus' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_16.png'
					],
					'cz_masonry cz_grid_c4' => [
						'title'=> 'Masonry 4 ' . esc_html__( 'Columns', 'codevz-plus' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_17.png'
					],
					'cz_masonry cz_grid_c4 cz_grid_1big' => [
						'title'=> '1 Big Masonry 4 ' . esc_html__( 'Columns', 'codevz-plus' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_18.png'
					],
					'cz_masonry cz_grid_c5' => [
						'title'=> 'Masonry 5 ' . esc_html__( 'Columns', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_19.png'
					],
					'cz_metro_1 cz_grid_c4' => [
						'title'=> 'Metro1 4 ' . esc_html__( 'Columns', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_20.png'
					],
					'cz_metro_2 cz_grid_c4' => [
						'title'=> 'Metro2 4 ' . esc_html__( 'Columns', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_21.png'
					],
					'cz_metro_3 cz_grid_c4' => [
						'title'=> 'Metro3 4 ' . esc_html__( 'Columns', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_22.png'
					],
					'cz_metro_4 cz_grid_c4' => [
						'title'=> 'Metro4 4 ' . esc_html__( 'Columns', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_23.png'
					],
					'cz_metro_5 cz_grid_c3' => [
						'title'=> 'Metro5 3 ' . esc_html__( 'Columns', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_24.png'
					],
					'cz_metro_6 cz_grid_c3' => [
						'title'=> 'Metro6 3 ' . esc_html__( 'Columns', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_25.png'
					],
					'cz_metro_7 cz_grid_c7' => [
						'title' => 'Metro7 7 ' . esc_html__( 'Columns', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'url' 	=> Codevz_Plus::$url . 'assets/img/gallery_26.png'
					],
					'cz_metro_8 cz_grid_c4' => [
						'title' => 'Metro8 4 ' . esc_html__( 'Columns', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'url' 	=> Codevz_Plus::$url . 'assets/img/gallery_27.png'
					],
					'cz_metro_9 cz_grid_c6' => [
						'title' => 'Metro9 6 ' . esc_html__( 'Columns', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'url' 	=> Codevz_Plus::$url . 'assets/img/gallery_28.png'
					],
					'cz_metro_10 cz_grid_c6' => [
						'title' => 'Metro10 6 ' . esc_html__( 'Columns', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'url' 	=> Codevz_Plus::$url . 'assets/img/gallery_29.png'
					],
					'cz_posts_list_1' => [
						'title' => 'List 1' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'url' 	=> Codevz_Plus::$url . 'assets/img/posts_list_1.png'
					],
					'cz_posts_list_2' => [
						'title'=> 'List 2' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'url'=> Codevz_Plus::$url . 'assets/img/posts_list_2.png'
					],
					'cz_posts_list_3' => [
						'title'=> 'List 3' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'url'=> Codevz_Plus::$url . 'assets/img/posts_list_3.png'
					],
					'cz_posts_list_4' => [
						'title'=> 'List 4' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'url'=> Codevz_Plus::$url . 'assets/img/posts_list_4.png'
					],
					'cz_posts_list_5' => [
						'title'=> 'List 5' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'url'=> Codevz_Plus::$url . 'assets/img/posts_list_5.png'
					],
					'cz_grid_carousel' => [
						'title'=> esc_html__( 'Carousel', 'codevz-plus' ),
						'url'=> Codevz_Plus::$url . 'assets/img/gallery_30.png'
					],
				),
			]
		);

		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			[
				'name' => 'image', // Usage: `{name}_size` and `{name}_custom_dimension`, in this case `image_size` and `image_custom_dimension`.
				'default' => 'full',
				'separator' => 'none',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'custom_items_section',
			[
				'label' => esc_html__( 'Custom item(s)', 'codevz-plus' ),
			]
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'position',
			[
				'label' 	=> esc_html__( 'Position', 'codevz-plus' ),
				'type' 		=> Controls_Manager::NUMBER,
				'default' 	=> 1
			]
		);

		$repeater->add_responsive_control(
			'sk_item',
			[
				'label' 	=> esc_html__( 'Style', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '{{CURRENT_ITEM}} > div' ),
			]
		);

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

		$repeater->add_control(
			'xtra_elementor_template',
			[
				'label' 	=> esc_html__( 'Select template', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'options' 	=> Xtra_Elementor::get_templates(),
				'condition' => [
					'type' => 'template'
				],
			]
		);

		$this->add_control(
			'custom_items',
			[
				'label' => esc_html__( 'Custom item(s)', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::REPEATER,
				'prevent_empty' => false,
				'fields' => $repeater->get_controls()
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'settings',
			[
				'label' => esc_html__( 'Settings', 'codevz-plus' ),
			]
		);

		$this->add_control (
			'posts_per_page',
			[
				'label' => esc_html__( 'Posts count', 'codevz-plus' ),
				'type' => Controls_Manager::NUMBER,
				'default' => 5
			]
		);

		$this->add_responsive_control(
			'gap',
			[
				'label' => esc_html__( 'Gap', 'codevz-plus' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'default' => [
					'unit' => 'px',
					'size' => 30,
				],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 300,
						'step' => 1,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .cz_grid .slick-list' => 'margin-left: calc(-{{SIZE}}{{UNIT}} / 2);margin-right: calc(-{{SIZE}}{{UNIT}} / 2);margin-bottom: -{{SIZE}}{{UNIT}};width: calc(100% + {{SIZE}}{{UNIT}});',
					'{{WRAPPER}} .cz_grid .cz_grid_item > div' => 'margin:0 calc({{SIZE}}{{UNIT}} / 2) {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .slick-slide' => 'margin:0 calc({{SIZE}}{{UNIT}} / 2);',
				]
			]
		);

		$this->add_control(
			'two_columns_on_mobile',
			[
				'label' => esc_html__( 'Two columns on mobile?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER 
			]
		);

		$this->add_control(
			'hover',
			[
				'label' => esc_html__( 'Posts details style', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'cz_grid_1_no_icon',
				'options' => [
					'cz_grid_1_no_hover'  => esc_html__( 'No hover details', 'codevz-plus' ),
					'cz_grid_1_no_title cz_grid_1_no_desc' => esc_html__( 'Only icon on hover', 'codevz-plus' ),
					'cz_grid_1_no_desc' => esc_html__( 'Icon and Title on hover', 'codevz-plus' ),
					'cz_grid_1_yes_all' => esc_html__( 'Icon, Title and Meta on hover', 'codevz-plus' ),
					'cz_grid_1_no_icon cz_grid_1_no_desc' => esc_html__( 'Title on hover', 'codevz-plus' ),
					'cz_grid_1_no_icon' => esc_html__( 'Title and Meta on hover', 'codevz-plus' ),
					'cz_grid_1_no_icon cz_grid_1_has_excerpt cz_grid_1_no_desc' => esc_html__( 'Title and Excerpt on hover', 'codevz-plus' ),
					'cz_grid_1_no_icon cz_grid_1_has_excerpt' => esc_html__( 'Title, Meta and Excerpt on hover', 'codevz-plus' ),
					'cz_grid_1_title_sub_after cz_grid_1_no_hover' => esc_html__( 'No hover details, Title and Meta after Image', 'codevz-plus' ),
					'cz_grid_1_title_sub_after' => esc_html__( 'Icon on hover, Title and Meta after Image', 'codevz-plus' ),
					'cz_grid_1_title_sub_after cz_grid_1_has_excerpt' => esc_html__( 'Icon on hover, Title, Meta and Excerpt after Image', 'codevz-plus' ),
					'cz_grid_1_title_sub_after cz_grid_1_has_excerpt cz_grid_1_no_icon' => esc_html__( 'No Icon, Title, Meta and Excerpt after Image', 'codevz-plus' ),
					'cz_grid_1_title_sub_after cz_grid_1_subtitle_on_img' => esc_html__( 'Meta on image, Title after image', 'codevz-plus' ),
					'cz_grid_1_title_sub_after cz_grid_1_has_excerpt cz_grid_1_subtitle_on_img' => esc_html__( 'Meta on image, Title and Excerpt after image', 'codevz-plus' ),
					'cz_grid_1_title_sub_after cz_grid_1_no_image' => esc_html__( 'No image, Title and Meta', 'codevz-plus' ),
					'cz_grid_1_title_sub_after cz_grid_1_has_excerpt cz_grid_1_no_image' => esc_html__( 'No image, Title, Meta and Excerpt', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'title_tag',
			[
				'label' => esc_html__( 'Title Tag', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => 'h3',
				'options' => [
					'h3' 			=> 'h3',
					'h4' 			=> 'h4',
					'h5' 			=> 'h5',
					'h6' 			=> 'h6',
				],
			]
		);

		$this->add_control(
			'animation',
			[
				'label' => esc_html__( 'Intro animation', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'options' => [
					''  => esc_html__( 'Select', 'codevz-plus' ),
					'cz_grid_anim_fade_in' => esc_html__( 'Fade In', 'codevz-plus' ),
					'cz_grid_anim_move_up' => esc_html__( 'Move Up', 'codevz-plus' ),
					'cz_grid_anim_move_down' => esc_html__( 'Move Down', 'codevz-plus' ),
					'cz_grid_anim_move_right' => esc_html__( 'Move Right', 'codevz-plus' ),
					'cz_grid_anim_move_left' => esc_html__( 'Move Left', 'codevz-plus' ),
					'cz_grid_anim_zoom_in' => esc_html__( 'Zoom In', 'codevz-plus' ),
					'cz_grid_anim_zoom_out' => esc_html__( 'Zoom Out', 'codevz-plus' ),
					'cz_grid_anim_slant' => esc_html__( 'Slant', 'codevz-plus' ),
					'cz_grid_anim_helix' => esc_html__( 'Helix', 'codevz-plus' ),
					'cz_grid_anim_fall_perspective' => esc_html__( 'Fall Perspective', 'codevz-plus' ),
					'cz_grid_brfx_right' => esc_html__( 'Block reveal right', 'codevz-plus' ),
					'cz_grid_brfx_left' => esc_html__( 'Block reveal left', 'codevz-plus' ),
					'cz_grid_brfx_up' => esc_html__( 'Block reveal up', 'codevz-plus' ),
					'cz_grid_brfx_down' => esc_html__( 'Block reveal down', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'subtitle_pos',
			[
				'label' => esc_html__( 'Meta position?', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					''  => esc_html__( 'Select', 'codevz-plus' ),
					'cz_grid_1_title_rev' => esc_html__( 'Before title', 'codevz-plus' ),
					'cz_grid_1_sub_after_ex' => esc_html__( 'After Excerpt', 'codevz-plus' ),
				],
				'condition' => [
					'hover!' => [
						'cz_grid_1_no_hover', 
						'cz_grid_1_no_title', 
						'cz_grid_1_no_desc', 
						'cz_grid_1_title_sub_after cz_grid_1_has_excerpt cz_grid_1_subtitle_on_img', 
						'cz_grid_1_title_sub_after cz_grid_1_subtitle_on_img', 
						'cz_grid_1_no_icon cz_grid_1_no_desc',
					],
				],
			]
		);

		$this->add_control(
			'hover_pos',
			[
				'label' => esc_html__( 'Details align', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'cz_grid_1_bot tal',
				'options' => [
					'cz_grid_1_top tal'  => esc_html__( 'Top Left', 'codevz-plus' ),
					'cz_grid_1_top tac'  => esc_html__( 'Top Center', 'codevz-plus' ),
					'cz_grid_1_top tar'  => esc_html__( 'Top Right', 'codevz-plus' ),
					'cz_grid_1_mid tal'  => esc_html__( 'Middle Left', 'codevz-plus' ),
					'cz_grid_1_mid tac'  => esc_html__( 'Middle Center', 'codevz-plus' ),
					'cz_grid_1_mid tar'  => esc_html__( 'Middle Right', 'codevz-plus' ),
					'cz_grid_1_bot tal'  => esc_html__( 'Bottom Left', 'codevz-plus' ),
					'cz_grid_1_bot tac'  => esc_html__( 'Bottom Center', 'codevz-plus' ),
					'cz_grid_1_bot tar'  => esc_html__( 'Bottom Right', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'hover_vis',
			[
				'label' => esc_html__( 'Hover visibility?', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					''  => esc_html__( 'Show overlay on hover', 'codevz-plus' ),
					'cz_grid_1_hide_on_hover' => esc_html__( 'Hide overlay on hover', 'codevz-plus' ),
					'cz_grid_1_always_show' => esc_html__( 'Always show overlay', 'codevz-plus' ),
				],
				'condition' => [
					'hover!' => [
						'cz_grid_1_no_hover', 
						'cz_grid_1_title_sub_after cz_grid_1_no_hover', 
						'cz_grid_1_title_sub_after cz_grid_1_no_image', 
						'cz_grid_1_title_sub_after cz_grid_1_has_excerpt cz_grid_1_no_image',
					],
				],
			]
		);

		$this->add_control(
			'hover_fx',
			[
				'label' => esc_html__( 'Hover effect?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'options' => [
					''  => esc_html__( 'Fade in Top', 'codevz-plus' ),
					'cz_grid_fib' => esc_html__( 'Fade in Bottom', 'codevz-plus' ),
					'cz_grid_fil' => esc_html__( 'Fade in Left', 'codevz-plus' ),
					'cz_grid_fir' => esc_html__( 'Fade in Right', 'codevz-plus' ),
					'cz_grid_zin' => esc_html__( 'Zoom In', 'codevz-plus' ),
					'cz_grid_zou' => esc_html__( 'Zoom Out', 'codevz-plus' ),
					'cz_grid_siv' => esc_html__( 'Opening Vertical', 'codevz-plus' ),
					'cz_grid_sih' => esc_html__( 'Opening Horizontal', 'codevz-plus' ),
					'cz_grid_sil' => esc_html__( 'Slide in Left', 'codevz-plus' ),
					'cz_grid_sir' => esc_html__( 'Slide in Right', 'codevz-plus' ),
				],
				'condition' => [
					'hover!' => [
						'cz_grid_1_no_hover', 
						'cz_grid_1_title_sub_after cz_grid_1_no_hover', 
						'cz_grid_1_title_sub_after cz_grid_1_no_image', 
						'cz_grid_1_title_sub_after cz_grid_1_has_excerpt cz_grid_1_no_image',
					],
				],
			]
		);

		$this->add_control(
			'img_fx',
			[
				'label' => esc_html__( 'Hover image effect?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					''  => esc_html__( 'Select', 'codevz-plus' ),
					'cz_grid_inset_clip_1x' => esc_html__( 'Inset mask', 'codevz-plus' ) . ' 1',
					'cz_grid_inset_clip_2x' => esc_html__( 'Inset mask', 'codevz-plus' ) . ' 2',
					'cz_grid_inset_clip_3x' => esc_html__( 'Inset mask', 'codevz-plus' ) . ' 3',
					'cz_grid_zoom_mask' => esc_html__( 'Zoom Mask', 'codevz-plus' ),
					'cz_grid_scale' => esc_html__( 'Scale', 'codevz-plus' ),
					'cz_grid_scale2' => esc_html__( 'Scale', 'codevz-plus' ) . ' 2',
					'cz_grid_grayscale' => esc_html__( 'Grayscale', 'codevz-plus' ),
					'cz_grid_grayscale_on_hover' => esc_html__( 'Grayscale on hover', 'codevz-plus' ),
					'cz_grid_grayscale_remove' => esc_html__( 'Remove Grayscale', 'codevz-plus' ),
					'cz_grid_blur' => esc_html__( 'Blur', 'codevz-plus' ),
					'cz_grid_zoom_in' => esc_html__( 'Zoom In', 'codevz-plus' ),
					'cz_grid_zoom_out' => esc_html__( 'Zoom Out', 'codevz-plus' ),
					'cz_grid_zoom_rotate' => esc_html__( 'Zoom Rotate', 'codevz-plus' ),
					'cz_grid_flash' => esc_html__( 'Flash', 'codevz-plus' ),
					'cz_grid_shine' => esc_html__( 'Shine', 'codevz-plus' ),
				],
				'condition' => [
					'hover!' => [
						'cz_grid_1_title_sub_after cz_grid_1_no_image', 
						'cz_grid_1_title_sub_after cz_grid_1_has_excerpt cz_grid_1_no_image',
					],
				],
			]
		);

		$this->add_control(
			'css_position',
			[
				'label' => esc_html__( 'Position', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					''  => esc_html__( 'Select', 'codevz-plus' ),
					'cz_grid_inset_clip_1x' => esc_html__( 'Inset mask', 'codevz-plus' ) . ' 1',
					'cz_grid_inset_clip_2x' => esc_html__( 'Inset mask', 'codevz-plus' ) . ' 2',
					'cz_grid_inset_clip_3x' => esc_html__( 'Inset mask', 'codevz-plus' ) . ' 3',
					'cz_grid_zoom_mask' => esc_html__( 'Zoom Mask', 'codevz-plus' ),
					'cz_grid_scale' => esc_html__( 'Scale', 'codevz-plus' ),
					'cz_grid_scale2' => esc_html__( 'Scale', 'codevz-plus' ) . ' 2',
					'cz_grid_grayscale' => esc_html__( 'Grayscale', 'codevz-plus' ),
					'cz_grid_grayscale_on_hover' => esc_html__( 'Grayscale on hover', 'codevz-plus' ),
					'cz_grid_grayscale_remove' => esc_html__( 'Remove Grayscale', 'codevz-plus' ),
					'cz_grid_blur' => esc_html__( 'Blur', 'codevz-plus' ),
					'cz_grid_zoom_in' => esc_html__( 'Zoom In', 'codevz-plus' ),
					'cz_grid_zoom_out' => esc_html__( 'Zoom Out', 'codevz-plus' ),
					'cz_grid_zoom_rotate' => esc_html__( 'Zoom Rotate', 'codevz-plus' ),
					'cz_grid_flash' => esc_html__( 'Flash', 'codevz-plus' ),
					'cz_grid_shine' => esc_html__( 'Shine', 'codevz-plus' ),
				],
				'condition' => [
					'hover' => [
						'cz_grid_1_title_sub_after cz_grid_1_no_image', 
						'cz_grid_1_title_sub_after cz_grid_1_has_excerpt cz_grid_1_no_image',
					]
				]
			]
		);

		$this->add_control(
			'height',
			[
				'label' => esc_html__( 'Ideal height', 'codevz-plus' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 100,
						'max' => 400,
						'step' => 1,
					],
				],
				'condition' => [
					'layout' => 'cz_justified',
				]
			]
		);

		$this->add_control(
			'icon',
			[
				'label' => esc_html__( 'Icon', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'skin' 			=> 'inline',
				'label_block' 	=> false,
				'default' => [
					'value' => 'fa fa-search',
					'library' => 'solid',
				],
			]
		);

		$this->add_control(
			'overlay_outer_space',
			[
				'label' => esc_html__( 'Overlay scale', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					''  => esc_html__( '~ Default ~', 'codevz-plus' ),
					'cz_grid_overlay_5px' 	=> '1',
					'cz_grid_overlay_10px' 	=> '2',
					'cz_grid_overlay_15px' 	=> '3',
					'cz_grid_overlay_20px' 	=> '4',
				],
				'condition' => [
					'hover!' => [
						'cz_grid_1_no_hover', 
						'cz_grid_1_title_sub_after cz_grid_1_no_hover', 
						'cz_grid_1_title_sub_after cz_grid_1_no_image', 
						'cz_grid_1_title_sub_after cz_grid_1_no_image', 
						'cz_grid_1_title_sub_after cz_grid_1_has_excerpt cz_grid_1_no_image',
					],
				],
			]
		);

		$this->end_controls_section();

		// Style
		$this->start_controls_section(
			'section_style',
			[
				'label' => esc_html__( 'Style', 'codevz-plus' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'sk_container',
			[
				'label' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_grid_p' ),
			]
		);

		$this->add_responsive_control(
			'sk_overall',
			[
				'label' 	=> esc_html__( 'Posts', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_grid .cz_grid_item > div' ),
			]
		);

		$this->add_responsive_control(
			'sk_img',
			[
				'label' 	=> esc_html__( 'Images', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_grid .cz_grid_link', '.cz_grid .cz_grid_item:hover .cz_grid_link' ),
			]
		);

		$this->add_responsive_control(
			'sk_overlay',
			[
				'label' 	=> esc_html__( 'Overlay', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_grid .cz_grid_link:before', '.cz_grid .cz_grid_item:hover .cz_grid_link:before' ),
				'condition' => [
					'hover!' => [
						'cz_grid_1_no_hover', 
						'cz_grid_1_title_sub_after cz_grid_1_no_hover', 
						'cz_grid_1_title_sub_after cz_grid_1_no_image', 
						'cz_grid_1_title_sub_after cz_grid_1_no_image', 
						'cz_grid_1_title_sub_after cz_grid_1_has_excerpt cz_grid_1_no_image',
					],
				],
			]
		);

		$this->add_responsive_control(
			'sk_icon',
			[
				'label' 	=> esc_html__( 'Icon', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_grid .cz_grid_icon' )
			]
		);

		$this->add_responsive_control(
			'sk_content',
			[
				'label' 	=> esc_html__( 'Outer content', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_grid div > .cz_grid_details', '.cz_grid .cz_grid_item:hover div > .cz_grid_details' ),
			]
		);

		$this->add_responsive_control(
			'sk_title',
			[
				'label' 	=> esc_html__( 'Title', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'font-family', 'font-size', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_grid .cz_grid_details .cz_grid_post_title', '.cz_grid .cz_grid_item:hover .cz_grid_details .cz_grid_post_title' ),
			]
		);

		$this->add_responsive_control(
			'sk_meta',
			[
				'label' 	=> esc_html__( 'Meta', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'position', 'left', 'top', 'bottom', 'right', 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_grid .cz_grid_details small', '.cz_grid .cz_grid_item:hover .cz_grid_details small' ),
			]
		);

		$this->add_responsive_control(
			'sk_meta_icons',
			[
				'label' 	=> esc_html__( 'Meta icons', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_grid .cz_sub_icon', '.cz_grid .cz_grid_item:hover .cz_sub_icon' ),
			]
		);

		$this->add_responsive_control(
			'sk_excerpt',
			[
				'label' 	=> esc_html__( 'Excerpt', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'text-align', 'font-size' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_grid .cz_post_excerpt', '.cz_grid .cz_grid_item:hover .cz_post_excerpt' ),
			]
		);

		$this->add_responsive_control(
			'sk_readmore',
			[
				'label' 	=> esc_html__( 'Read more', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_grid .cz_post_excerpt .cz_readmore', '.cz_grid .cz_post_excerpt .cz_readmore:hover' ),
			]
		);

		$this->add_responsive_control(
			'sk_load_more',
			[
				'label' 	=> esc_html__( 'Load more', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_ajax_pagination a', '.cz_ajax_pagination a:hover' ),
			]
		);

		$this->add_responsive_control(
			'sk_load_more_active',
			[
				'label' 	=> esc_html__( 'Load more active', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'border-right-color', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_ajax_pagination .cz_ajax_loading' ),
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_filters',
			[
				'label' => esc_html__( 'Filters', 'codevz-plus' ),
				'tab' => Controls_Manager::TAB_STYLE
			]
		);

		$this->add_responsive_control(
			'sk_filters_con',
			[
				'label' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_grid_filters' ),
				'condition' => [
					'filters!' => '',
				]
			]
		);

		$this->add_responsive_control(
			'sk_filters',
			[
				'label' 	=> esc_html__( 'Filters', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'font-family', 'font-size', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_grid_filters li' ),
				'condition' => [
					'filters!' => '',
				]
			]
		);

		$this->add_responsive_control(
			'sk_filters_separator',
			[
				'label' 	=> esc_html__( 'Filters delimiter', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'content', 'color' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_grid_filters li:after' ),
				'condition' => [
					'filters!' => '',
				]
			]
		);

		$this->add_responsive_control(
			'sk_filter_active',
			[
				'label' 	=> esc_html__( 'Active filter', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_grid_filters .cz_active_filter' ),
				'condition' => [
					'filters!' => '',
				]
			]
		);

		$this->add_responsive_control(
			'sk_filters_items_count',
			[
				'label' 	=> esc_html__( 'Filter items count', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'font-size', 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_grid_filters li span', '.cz_grid_filters_count_a li span, cz_grid .cz_grid_filters_count li:hover span, cz_grid li.cz_active_filter span' ),
				'condition' => [
					'filters!' => '',
				]
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_carousel',
			[
				'label' => esc_html__( 'Carousel', 'codevz-plus' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
			]
		);

		$this->add_responsive_control(
			'sk_slides',
			[
				'label' 	=> esc_html__( 'Slides', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'grayscale', 'blur', 'background', 'opacity', 'z-index', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( 'div.slick-slide' ),
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
			]
		);

		$this->add_responsive_control(
			'sk_center',
			[
				'label' 	=> esc_html__( 'Center slide', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'grayscale', 'background', 'opacity', 'z-index', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( 'div.slick-center' ),
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
			]
		);

		$this->add_responsive_control(
			'sk_prev_icon',
			[
				'label' 	=> esc_html__( 'Previous icon', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.slick-prev' ),
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
			]
		);


		$this->add_responsive_control(
			'sk_next_icon',
			[
				'label' 	=> esc_html__( 'Next icon', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.slick-next' ),
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
			]
		);

		$this->add_responsive_control(
			'sk_dots_container',
			[
				'label' 	=> esc_html__( 'Dots Container', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.slick-dots' ),
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
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
					'layout' => 'cz_grid_carousel',
				]
			]
		);

		$this->end_controls_section();

		// Meta.
		$this->start_controls_section(
			'section_meta',
			[
				'label' => esc_html__( 'Meta', 'codevz-plus' ),
			]
		);

		$repeater = new Repeater();

		$repeater->add_control(
			't',
			[
				'label' => esc_html__( 'Type', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'date',
				'options' => [
					'date'  => esc_html__( 'Date', 'codevz-plus' ),
					'cats' => esc_html__( 'Categories', 'codevz-plus' ),
					'cats_2' => esc_html__( 'Categories', 'codevz-plus' ) . ' 2',
					'cats_3' => esc_html__( 'Categories', 'codevz-plus' ) . ' 3',
					'cats_4' => esc_html__( 'Categories', 'codevz-plus' ) . ' 4',
					'cats_5' => esc_html__( 'Categories', 'codevz-plus' ) . ' 5',
					'cats_6' => esc_html__( 'Categories', 'codevz-plus' ) . ' 6',
					'cats_7' => esc_html__( 'Categories', 'codevz-plus' ) . ' 7',
					'tags' => esc_html__( 'Tags', 'codevz-plus' ),
					'author' => esc_html__( 'Author', 'codevz-plus' ),
					'author_avatar' => esc_html__( 'Author Avatar', 'codevz-plus' ),
					'author_full_date' => esc_html__( 'Avatar, Author and Date', 'codevz-plus' ),
					'author_icon_date' => esc_html__( 'Icon, Author and Date', 'codevz-plus' ),
					'comments' => esc_html__( 'Comments', 'codevz-plus' ),
					'price' => esc_html__( 'Product Price', 'codevz-plus' ),
					'add_to_cart' => esc_html__( 'Product add to cart', 'codevz-plus' ),
					'custom_text' => esc_html__( 'Custom Text', 'codevz-plus' ),
					'custom_meta' => esc_html__( 'Custom Meta', 'codevz-plus' ),
				],
			]
		);

		$repeater->add_control(
			'r',
			[
				'label' => esc_html__( 'Position', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'' 					=> esc_html__( '~ Default ~', 'codevz-plus' ),
					'cz_post_data_r' 	=> esc_html__( 'Inverted', 'codevz-plus' ),
				],
			]
		);

		$repeater->add_control(
			'i',
			[
				'label' => esc_html__( 'Icon', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'skin' 			=> 'inline',
				'label_block' 	=> false,
				'condition' => [
					't!' => [
						'author_avatar', 
						'author_full_date,'
					],
				],
			]
		);

		$repeater->add_control(
			'p', [
				'label' => esc_html__( 'Prefix', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::TEXT,
				'condition' => [
					't' => [
						'date', 
						'cats', 
						'tags', 
						'author', 
						'comments',
					],
				],
			]
		);

		$repeater->add_control(
			'ct', [
				'label' => esc_html__( 'Custom text', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::TEXT,
				'condition' => [
					't' => 'custom_text',
				],
			]
		);

		$repeater->add_control(
			'cm', [
				'label' => esc_html__( 'Custom meta name', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::TEXT,
				'condition' => [
					't' => 'custom_meta',
				],
			]
		);

		$repeater->add_responsive_control (
			'tc',
			[
				'label' => esc_html__( 'Count', 'codevz-plus' ),
				'type' => Controls_Manager::NUMBER,
				'condition' => [
					't' => [
						'cats_2', 
						'cats_3', 
						'cats_4', 
						'cats_5', 
						'cats_6', 
						'cats_7', 
						'tags',
					],
				],
			]
		);
		
		$this->add_control(
			'subtitles',
			[
				'label' => esc_html__( 'Posts meta', 'codevz-plus' ),
				'type' => Controls_Manager::REPEATER,
				'prevent_empty' => false,
				'fields' => $repeater->get_controls(),
				'default' => [
					[
						't' => 'date',
						'r' => '',
						'p' => '',
					],
				],
			]
		);

		$this->end_controls_section();

		//Excerpt
		$this->start_controls_section(
			'section_excerpt',
			[
				'label' => esc_html__( 'Excerpt', 'codevz-plus' ),
			]
		);

		$this->add_control (
			'title_lenght',
			[
				'label' => esc_html__( 'Title length', 'codevz-plus' ),
				'type' => Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 100,
				'step' => 1,
			]
		);

		$this->add_control(
			'single_line_title',
			[
				'label' => esc_html__( 'Single line title', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER
			]
		);

		$this->add_control (
			'el',
			[
				'label' => esc_html__( 'Excerpt lenght', 'codevz-plus' ),
				'type' => Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 200,
				'step' => 1,
			]
		);

		$this->add_control(
			'excerpt_rm',
			[
				'label' => esc_html__( 'Read more', 'codevz-plus' ),
				'type' => Controls_Manager::SWITCHER
			]
		);

		

		$this->end_controls_section();

		//Load More
		$this->start_controls_section(
			'section_pagination',
			[
				'label' => esc_html__( 'Pagination', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'loadmore',
			[
				'label' => esc_html__( 'Type', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'options' => [
					''  => esc_html__( 'Select', 'codevz-plus' ),
					'loadmore' => esc_html__( 'Load More', 'codevz-plus' ),
					'infinite' => esc_html__( 'Infinite Scroll', 'codevz-plus' ),
					'pagination' => esc_html__( 'Pagination', 'codevz-plus' ),
					'older' => esc_html__( 'Older / Newer', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'loadmore_pos',
			[
				'label' => esc_html__( 'Position', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => 'tac',
				'options' => [
					''  	=> esc_html__( 'Select', 'codevz-plus' ),
					'tal' 	=> esc_html__( 'Left', 'codevz-plus' ),
					'tac' 	=> esc_html__( 'Center', 'codevz-plus' ),
					'tar' 	=> esc_html__( 'Right', 'codevz-plus' ),
					'cz_loadmore_block' => esc_html__( 'Block', 'codevz-plus' ),
				],
				$free ? 'codevz_pro_con' : 'condition' => [
					'loadmore' => [
						'loadmore', 
						'infinite',
					],
				],
			]
		);

		$this->add_control(
			'loadmore_title', [
				'label' => esc_html__( 'Title', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Load More', 'codevz-plus' ),
				'condition' => [
					'loadmore' => [
						'loadmore', 
						'infinite',
					],
				],
			]
		);

		$this->add_control(
			'loadmore_end', [
				'label' => esc_html__( 'End', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Not found more posts', 'codevz-plus' ),
				'condition' => [
					'loadmore' => [
						'loadmore', 
						'infinite',
					],
				],
			]
		);

		$this->add_control (
			'loadmore_lenght',
			[
				'label' => esc_html__( 'Posts count', 'codevz-plus' ),
				'type' => Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 10,
				'step' => 1,
				'condition' => [
					'loadmore' => [
						'loadmore', 
						'infinite',
					],
				],
			]
		);

		$this->end_controls_section();

		// Filter
		$this->start_controls_section(
			'section_filter',
			[
				'label' 	=> esc_html__( 'Filter and Search', 'codevz-plus' )
			]
		);

		$terms = [];

		foreach( get_terms() as $term ) {

			$taxonomy = get_taxonomy( $term->taxonomy );

			if ( isset( $taxonomy->object_type ) ) {
				$terms[ $term->term_id ] = $term->name . ' (' . $taxonomy->object_type[ 0 ] . ')';
			}

		}

		$this->add_control(
			'filters',
			[
				'label' 	=> esc_html__( 'Choose Filter', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SELECT2,
				'multiple' 	=> true,
				'options' 	=> $terms
			]
		);

		$this->add_control(
			'filters_tax',
			[
				'label' => esc_html__( 'Taxonomy', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'category',
				'options' => get_taxonomies()
			]
		);

		$this->add_control(
			'filters_pos',
			[
				'label' => esc_html__( 'Position', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					''  => esc_html__( 'Select', 'codevz-plus' ),
					'tal' => esc_html__( 'Left', 'codevz-plus' ),
					'tac' => esc_html__( 'Center', 'codevz-plus' ),
					'tar' => esc_html__( 'Right', 'codevz-plus' ),
				]
			]
		);

		$this->add_control(
			'browse_all',
			[
				'label' => esc_html__( 'Show All', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Show All', 'codevz-plus' ),
				'placeholder' => esc_html__( 'Show All', 'codevz-plus' )
			]
		);

		$this->add_control(
			'filters_items_count',
			[
				'label' => esc_html__( 'Filters items count?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'options' => [
					''  => esc_html__( 'Select', 'codevz-plus' ),
					'cz_grid_filters_count_a' => esc_html__( 'Above filters', 'codevz-plus' ),
					'cz_grid_filters_count_ah' => esc_html__( 'Above filters on hover', 'codevz-plus' ),
					'cz_grid_filters_count_i' => esc_html__( 'Inline beside filters', 'codevz-plus' ),
				]
			]
		);

		$this->end_controls_section();

		//Start WP_Query
		$this->start_controls_section(
			'section_query',
			[
				'label' => esc_html__( 'WP Query', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'post_type', [
				'label' => esc_html__( 'Post type(s)', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT
			]
		);

		$this->add_control(
			'orderby',
			[
				'label' => esc_html__( 'Orderby', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => 'date',
				'options' => [
					'date' => esc_html__( 'Date', 'codevz-plus' ),
					'ID' => esc_html__( 'ID', 'codevz-plus' ),
					'rand' => esc_html__( 'Random', 'codevz-plus' ),
					'author' => esc_html__( 'Author', 'codevz-plus' ),
					'title' => esc_html__( 'Title', 'codevz-plus' ),
					'name' => esc_html__( 'Name', 'codevz-plus' ),
					'type' => esc_html__( 'Type', 'codevz-plus' ),
					'modified' => esc_html__( 'Modified', 'codevz-plus' ),
					'parent' => esc_html__( 'Parent ID', 'codevz-plus' ),
					'comment_count' => esc_html__( 'Comment Count', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'order',
			[
				'label' 	=> esc_html__( 'Order', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' 	=> 'DESC',
				'options' 	=> [
					'DESC' 		=> esc_html__( 'Descending', 'codevz-plus' ),
					'ASC' 		=> esc_html__( 'Ascending', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'cat_tax',
			[
				'label' 	=> esc_html__( 'Category Taxonomy', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SELECT,
				'options' 	=> get_taxonomies(),
				'default' 	=> 'category'
			]
		);

		$this->add_control(
			'cat', 
			[
				'label' 	=> esc_html__( 'Category(s)', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SELECT2,
				'multiple' 	=> true,
				'options' 	=> $terms
			]
		);

		$this->add_control(
			'cat_exclude', 
			[
				'label' 	=> esc_html__( 'Exclude Category(s)', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SELECT2,
				'multiple' 	=> true,
				'options' 	=> $terms
			]
		);

		$this->add_control(
			'tag_tax',
			[
				'label' 	=> esc_html__( 'Tags Taxonomy', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SELECT,
				'options' 	=> get_taxonomies(),
				'default' 	=> 'post_tag'
			]
		);

		$this->add_control(
			'tag_id', 
			[
				'label' 	=> esc_html__( 'Tag ID', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SELECT2,
				'multiple' 	=> true,
				'options' 	=> $terms
			]
		);

		$this->add_control(
			'tag_exclude', 
			[
				'label' 	=> esc_html__( 'Exclude Tag ID', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SELECT2,
				'multiple' 	=> true,
				'options' 	=> $terms
			]
		);

		$this->add_control(
			'post__in', 
			[
				'label' 	=> esc_html__( 'Filter by posts', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::TEXT
			]
		);

		$this->add_control(
			'author__in', 
			[
				'label' 	=> esc_html__( 'Filter by authors', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::TEXT
			]
		);

		$this->add_control(
			's', 
			[
				'label' 	=> esc_html__( 'Search keyword', 'codevz-plus' ),
				'type' 		=> Controls_Manager::TEXT
			]
		);

		$this->end_controls_section();
		// End WP_Query

		// Carousel
		$this->start_controls_section(
			'section_carousel',
			[
				'label' => esc_html__( 'Carousel', 'codevz-plus' ),
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
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
				'label' 	=> esc_html__( 'Slides to show', 'codevz-plus' ),
				'type' 		=> Controls_Manager::NUMBER,
				'default' 	=> 3,
				'min' 		=> 1,
				'max' 		=> 10,
				'step' 		=> 1,
				'condition' => [
					'layout' 	=> 'cz_grid_carousel',
				]
			]
		);

		$this->add_responsive_control(
			'slidestoscroll',
			[
				'label' 	=> esc_html__( 'Slides to scroll', 'codevz-plus' ),
				'type' 		=> Controls_Manager::NUMBER,
				'default' 	=> 1,
				'min' 		=> 1,
				'max' 		=> 10,
				'step' 		=> 1,
				'condition' => [
					'layout' 	=> 'cz_grid_carousel',
				]
			]
		);

		$this->add_control(
			'infinite',
			[
				'label' => esc_html__( 'Infinite?', 'codevz-plus' ),
				'type' => Controls_Manager::SWITCHER,
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
			]
		);

		$this->add_control(
			'autoplay',
			[
				'label' 	=> esc_html__( 'Auto play?', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SWITCHER,
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
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
					'layout' => 'cz_grid_carousel',
				]
				
			]
		);

		$this->add_control(
			'centermode',
			[
				'label' 	=> esc_html__( 'Center mode?', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'layout' => 'cz_grid_carousel',
					'carousel_type' => ''
				]
			]
		);

		$this->add_responsive_control(
			'centerpadding',
			[
				'label' => esc_html__( 'Center padding', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SLIDER,
				'default' => [
					'unit' => 'px',
					'size' => 10,
				],
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 1,
						'max' => 100,
						'step' => 1,
					],
				],
				'condition' => [
					'layout' => 'cz_grid_carousel',
					'centermode!' => '',
					'carousel_type' => ''
				]
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_arrows',
			[
				'label' => esc_html__( 'Arrows', 'codevz-plus' ),
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
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
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
			]
		);

		$this->add_control(
			'arrows_inner',
			[
				'label' 	=> esc_html__( 'Arrows inside carousel?', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
			]
		);

		$this->add_control(
			'arrows_show_on_hover',
			[
				'label' 	=> esc_html__( 'Show on hover?', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'layout' => 'cz_grid_carousel',
				],
			]
		);

		$this->add_control(
			'prev_icon',
			[
				'label' => esc_html__( 'Previous icon', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'default' => [
					'value' => 'fa fa-chevron-left',
					'library' => 'solid',
				],
			]
		);

		$this->add_control(
			'next_icon',
			[
				'label' => esc_html__( 'Next icon', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'default' => [
					'value' => 'fa fa-chevron-right',
					'library' => 'solid',
				],
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
			]
		);
		
		$this->end_controls_section();

		 //Dots
		 $this->start_controls_section(
			'section_dots',
			[
				'label' => esc_html__( 'Dots', 'codevz-plus' ),
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
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
					'dots_vtl' => esc_html__( 'Vertical top left', 'codevz-plus' ),
					'dots_vml' => esc_html__( 'Vertical middle left', 'codevz-plus' ),
					'dots_vbl' => esc_html__( 'Vertical bottom left', 'codevz-plus' ),
					'dots_vtr' => esc_html__( 'Vertical top right', 'codevz-plus' ),
					'dots_vmr' => esc_html__( 'Vertical middle rigth', 'codevz-plus' ),
					'dots_vbr' => esc_html__( 'Vertical bottom right', 'codevz-plus' ),
				],
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
			]
		);

		$this->add_control(
			'dots_style',
			[
				'label' => esc_html__( 'Predefined style', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => '',
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
					'layout' => 'cz_grid_carousel',
				]
			]
		);

		$this->add_control(
			'dots_inner',
			[
				'label' => esc_html__( 'Dots inside carousel?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
			]
		);

		$this->add_control(
			'dots_show_on_hover',
			[
				'label' => esc_html__( 'Show on hover?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_advanced',
			[
				'label' => esc_html__( 'More carousel settings', 'codevz-plus' ),
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
			]
		);

		$this->add_control(
			'overflow_visible',
			[
				'label' => esc_html__( 'Overflow visible?', 'codevz-plus' ),
				'type' => Controls_Manager::SWITCHER,
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
			]
		);

		$this->add_control(
			'fade',
			[
				'label' => esc_html__( 'Fade mode?', 'codevz-plus' ),
				'type' => Controls_Manager::SWITCHER,
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
			]
		);

		$this->add_control(
			'mousewheel',
			[
				'label' => esc_html__( 'MouseWheel?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
			]
		);

		$this->add_control(
			'disable_links',
			[
				'label' => esc_html__( 'Disable slides links?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
			]
		);

		$this->add_control(
			'variablewidth',
			[
				'label' => esc_html__( 'Auto width detection?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
			]
		);

		$this->add_control(
			'vertical',
			[
				'label' => esc_html__( 'Vertical?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
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
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
			]
		);

		$this->add_control(
			'even_odd',
			[
				'label' => esc_html__( 'Custom position', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'options' => [
					'' => esc_html__( 'Select', 'codevz-plus' ),
					'even_odd' => esc_html__( 'Even / Odd', 'codevz-plus' ),
					'odd_even' => esc_html__( 'Odd / Even', 'codevz-plus' ),
				],
				'condition' => [
					'layout' => 'cz_grid_carousel',
				]
			]
		);

		$this->end_controls_section();

		//Cursor
		$this->start_controls_section(
			'section_cursor',
			[
				'label' => esc_html__( 'Cursor', 'codevz-plus' ),
			]
		);
		
		$this->add_control(
			'cursor',
			[
				'label' => esc_html__( 'Cursor', 'codevz-plus' ),
				'type' => Controls_Manager::MEDIA
			]
		);
		
		$this->add_control(
			'cursor_size',
			[
				'label' => esc_html__( 'Size', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => '0',
				'options' => [
					'0' => 'Default',
					'32' => '32x32',
					'36' => '36x36',
					'48' => '48x48',
					'64' => '64x64',
					'80' => '80x80',
					'128' => '128x128'
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_posts_more',
			[
				'label' => esc_html__( 'More', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'smart_details',
			[
				'label' => esc_html__( 'Smart details?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER 
			]
		);

		$this->end_controls_section();

		// Tilt controls.
		Xtra_Elementor::tilt_controls( $this );

		// Parallax settings.
		Xtra_Elementor::parallax_settings( $this );

	}

	public function render() {

		$settings = $this->get_settings_for_display();

		// Layout
		$layout = $settings['layout'];
		$carousel = Codevz_Plus::contains( $layout, 'carousel' );

		// List
		$is_list = 0;
		if ( Codevz_Plus::contains( $layout, 'cz_posts_list_' ) ) {
			$settings['hover'] = 'cz_grid_1_title_sub_after cz_grid_1_has_excerpt';
			$is_list = 1;
		}

		// Attributes
		$data = isset( $settings['height']['size'] ) ? ' data-height="' . $settings['height']['size'] . '"' : '';
		$data .= isset( $settings['gap']['size'] ) ? ' data-gap="' . (int) $settings['gap']['size'] . '"' : '';

		// Others var's
		$settings['post_class'] = 'cz_grid_item';
		$settings['post__in'] = $settings['post__in'] ? explode( ',', $settings['post__in'] ) : null;
		$settings['author__in'] = $settings['author__in'] ? explode( ',', $settings['author__in'] ) : null;

		// Tilt items
		$settings['tilt_data'] = Codevz_Plus::tilt( $settings );

		// Ajax data
		$ajax = array(
			'action'				=> 'cz_ajax_elementor_posts',
			'post_class'			=> $settings['post_class'],
			'post__in'				=> $settings['post__in'],
			'author__in'			=> $settings['author__in'],
			'nonce'					=> wp_create_nonce( 'posts' ),
			'nonce_id'				=> 'posts',
			'loadmore_end'			=> $settings['loadmore_end'],
			'layout'				=> $settings['layout'],
			'hover'					=> $settings['hover'],
			'subtitles'				=> $settings['subtitles'],
			'subtitle_pos'			=> $settings['subtitle_pos'],
			'icon'					=> $settings['icon'],
			'el'					=> $settings['el'],
			'title_lenght'			=> $settings['title_lenght'],
			'cat_tax'				=> $settings['cat_tax'],
			'cat'					=> $settings['cat'],
			'cat_exclude'			=> $settings['cat_exclude'],
			'tag_tax'				=> $settings['tag_tax'],
			'tag_id'				=> $settings['tag_id'],
			'tag_exclude'			=> $settings['tag_exclude'],
			'post_type'				=> $settings['post_type'],
			'posts_per_page'		=> $settings['loadmore_lenght'] ? $settings['loadmore_lenght'] : $settings['posts_per_page'],
			'order'					=> $settings['order'],
			'orderby'				=> $settings['orderby'],
			'tilt_data'				=> $settings['tilt_data'],
			'img_fx' 				=> $settings['img_fx'],
			'custom_size' 			=> '',
			'excerpt_rm' 			=> $settings['excerpt_rm'],
			'title_tag' 			=> $settings['title_tag']
		);

		// Search
		$input_search = Codevz_Plus::_GET( 's' );
		$settings['s'] = $ajax['s'] = $input_search ? $input_search : $settings['s'];

		// Archive
		global $wp_query;
		$query_vars = isset( $wp_query->query_vars ) ? $wp_query->query_vars : 0;
		$query_vars = is_array( $query_vars ) ? $query_vars : 0;
		$is_query = ( ! is_singular() && $query_vars );
		if ( $is_query ) {
			$cpt = get_post_type();
			$query_vars['post_type'] = $cpt;

			if ( isset( $query_vars['taxonomy'] ) && Codevz_Plus::contains( $query_vars['taxonomy'], '_cat' ) ) {
				$settings['cat_tax'] = $ajax['cat_tax'] = $query_vars['taxonomy'];
				$term = get_term_by( 'slug', $query_vars['term'], $query_vars['taxonomy'] );
				$settings['cat'] = $ajax['cat'] = isset( $term->term_id ) ? $term->term_id : 0;
			} else if ( isset( $query_vars['taxonomy'] ) && Codevz_Plus::contains( $query_vars['taxonomy'], '_tags' ) ) {
				$settings['tag_tax'] = $ajax['tag_tax'] = $query_vars['taxonomy'];
				$term = get_term_by( 'slug', $query_vars['term'], $query_vars['taxonomy'] );
				$settings['tag_id'] = $ajax['tag_id'] = isset( $term->term_id ) ? $term->term_id : 0;
			}

			$ajax = wp_parse_args( array_filter( $query_vars ), $ajax );
		}

		// Ajax data
		$data .= " data-atts='" . wp_json_encode( $ajax, JSON_HEX_APOS ) . "'";

		// Animation data
		$data .= ( $settings['animation'] && ! Codevz_Plus::contains( $layout, 'carousel' ) ) ? ' data-animation="' . $settings['animation'] . '"' : '';

		// Out
		$out = '<div class="cz_grid_p">';

		// Filters
		if ( is_array( $settings['filters'] ) && ! $carousel ) {

			$settings['filters_pos'] .= $settings['filters_items_count'] ? ' cz_grid_filters_count ' . $settings['filters_items_count'] : '';
			$out .= '<ul class="cz_grid_filters clr ' . $settings['filters_pos'] . '">';
			$out .= $settings['browse_all'] ? '<li class="cz_active_filter" data-filter=".cz_grid_item">' . $settings['browse_all'] . '</li>' : '';

			foreach( $settings['filters'] as $filter ) {

				$cat = ( $settings['post_type'] === 'post' ) ? 'category' : $settings['post_type'] . '_cat';
				$tag = ( $settings['post_type'] === 'post' ) ? 'post_tag' : $settings['post_type'] . '_tags';

				if ( isset( $settings[ 'filters_tax' ] ) && $settings[ 'filters_tax' ] !== 'category' ) {
					$cat = $settings[ 'filters_tax' ];
					$tag = $settings[ 'filters_tax' ];
				}

				if ( $cat == '_cat' ) {
					$cat = 'category';
				}

				$term = get_term_by( 'id', $filter, $cat );
				$term = $term ? $term : get_term_by( 'id', $filter, $tag );

				if ( ! empty( $term->slug ) ) {
					$term_slug = Codevz_Plus::contains( $term->slug, '%d' ) ? $term->term_id : $term->slug;
				} else {
					$term_slug = '';
				}

				$out .= is_object( $term ) ? '<li data-filter=".' . $term->taxonomy . '-' . $term_slug . '">' . ucfirst( $term->name ) . '</li>' : '';

			}

			$out .= '</ul>';

		}

		// Classes
		$classes = array();
		$classes[] = 'cz_grid cz_grid_1 clr';
		$classes[] = $layout;
		$classes[] = $settings['hover'];
		$classes[] = $settings['hover_pos'];
		$classes[] = $settings['hover_vis'];
		$classes[] = $settings['hover_fx'];
		$classes[] = $settings['overlay_outer_space'];
		$classes[] = $settings['subtitle_pos'];
		$classes[] = $settings['smart_details'] ? 'cz_smart_details' : '';
		$classes[] = $settings['tilt_data'] ? 'cz_grid_tilt' : '';
		$classes[] = $settings['single_line_title'] ? 'cz_single_line_title' : '';
		$classes[] = $settings['two_columns_on_mobile'] ? 'cz_grid_two_columns_on_mobile' : '';

		if ( isset( $settings['sk_overlay']['normal'] ) ) {
			$classes[] = Codevz_Plus::contains( $settings['sk_overlay']['normal'], 'border-color' ) ? 'cz_grid_overlay_border' : '';
		}

		$classes[] = Codevz_Plus::contains( $settings['hover_pos'], 'tac' ) ? 'cz_meta_all_center' : '';

		// Posts
		$out .= '<div' . Codevz_Plus::classes( [], $classes ) . $data . '>';
		$out .= ( $layout !== 'cz_justified' ) ? '<div class="cz_grid_item cz_grid_first"></div>' : '';
		if ( $is_query ) {
			$settings['wp_query'] = 1;
			$settings = wp_parse_args( array_filter( $query_vars ), $settings );
		}

		$get_posts = Xtra_Elementor::posts_grid_items( $settings );

		if ( ! $get_posts ) {
			$get_posts = '<div class="cz_grid_item">' . esc_html__( 'Not found any posts in this category.', 'codevz-plus' ) . '</div>';
		}

		$out .= $get_posts;
		$out .= '</div>';

		// Ajax pagination
		if ( $settings['layout'] !== 'cz_grid_carousel' && $settings['loadmore'] && $settings['loadmore'] !== 'pagination' && $settings['loadmore'] !== 'older' ) {
			$out .= '<div class="cz_ajax_pagination clr cz_ajax_' . $settings['loadmore'] . ' ' . $settings['loadmore_pos'] . '"><a href="#">' . $settings['loadmore_title'] . '</a></div>';
		}

		$out .= '</div>'; // ID

		// Carousel mode
		if ( $carousel ) {

			Xtra_Elementor::carousel_elementor( $settings, $out );

		} else {

			echo do_shortcode( $out );

		}

		if ( ! empty( $settings[ 'cursor' ][ 'id' ] ) ) {
			echo '<style>.cz_grid_link{cursor: url("' . esc_attr( Group_Control_Image_Size::get_attachment_image_src( $settings[ 'cursor' ][ 'id' ], 'cursor', $settings ) ) . '") ' . esc_attr( $settings[ 'cursor_size' ] / 2 . ' ' . $settings[ 'cursor_size' ] / 2 ) . ', auto}</style>';
		}

		Xtra_Elementor::render_js( 'grid' );

	}

}