<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Utils;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Elementor\Icons_Manager;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;

class Xtra_Elementor_Widget_menu_background extends Widget_Base {

	protected $id = 'cz_menu_background';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Menu Background', 'codevz-plus' );
	}
	
	public function get_icon() {
		return 'xtra-menu-background';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Banner', 'codevz-plus' ),
			esc_html__( 'Background', 'codevz-plus' ),
			esc_html__( 'Content', 'codevz-plus' ),
			esc_html__( 'Group', 'codevz-plus' ),

		];

	}
	
	public function get_style_depends() {

		$array = [ $this->id, 'cz_parallax' ];

		if ( Codevz_Plus::$is_rtl ) {
			$array[] = $this->id . '_rtl';
		}

		return $array;

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

		$repeater = new Repeater();

		$repeater->add_control(
			'background',
			[
				'label' => esc_html__( 'Background', 'codevz-plus' ),
				'type' => Controls_Manager::MEDIA
			]
		);

		$repeater->add_control(
			'title', [
				'label' 	=> esc_html__( 'Title', 'codevz-plus' ),
				'type' 		=> Controls_Manager::TEXT
			]
		);

		$repeater->add_control(
			'icon_type', [
				'label' 	=> esc_html__( 'Icon type', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'options' 	=> [
					'' 			=> esc_html__( 'Select', 'codevz-plus' ),
					'icon' 		=> esc_html__( 'Icon', 'codevz-plus' ),
					'image' 	=> esc_html__( 'Image', 'codevz-plus' ),
				],
			]
		);

		$repeater->add_control (
			'icon',
			[
				'label' => esc_html__( 'Icon', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'skin' => 'inline',
				'label_block' => false,
				'condition' => [
					'icon_type' => 'icon'
				],
			]
		);

		$repeater->add_control(
			'image',
			[
				'label' => esc_html__( 'Image', 'codevz-plus' ),
				'type' => Controls_Manager::MEDIA,
				'condition' => [
					'icon_type' => 'image'
				],
			]
		);

		$repeater->add_group_control(
			Group_Control_Image_Size::get_type(),
			[
				'name' 		=> 'image',
				'default' 	=> 'full',
				'separator' => 'none',
				'condition' => [
					'icon_type' => 'image'
				],
			]
		);

		$repeater->add_control(
			'link',
			[
				'label' 	=> esc_html__( 'Link', 'codevz-plus' ),
				'type' 		=> Controls_Manager::URL,
				'placeholder' => 'https://yoursite.com',
				'show_external' => true
			]
		);

		$repeater->add_responsive_control(
			'sk_menu',
			[
				'label' 	=> esc_html__( 'Menu', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '{{CURRENT_ITEM}}' ),
			]
		);

		$repeater->add_responsive_control(
			'sk_icon',
			[
				'label' 	=> esc_html__( 'Icon', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '{{CURRENT_ITEM}} i', '{{CURRENT_ITEM}}:hover i' ),
				'condition' => [
					'icon_type!' => ''
				],
			]
		);

		$this->add_control(
			'items',
			[
				'label' 	=> esc_html__( 'Item(s)', 'codevz-plus' ),
				'type' 		=> Controls_Manager::REPEATER,
				'prevent_empty' => false,
				'fields' 	=> $repeater->get_controls(),
				'default' 	=> [
					[
						'title' => 'Menu item 1',
						'link' 	=> [ 'url' => '#' ]
					],
					[
						'title' => 'Menu item 2',
						'link' 	=> [ 'url' => '#' ]
					]
				]
			]
		);

		$this->add_control(
			'first_item',
			[
				'label' 	=> esc_html__( 'Activate first item', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SWITCHER
			]
		);

		$this->add_responsive_control(
			'min_height',
			[
				'label' 	=> esc_html__( 'Minimum height', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range' 	=> [
					'px' 		=> [
						'min' 		=> 100,
						'max' 		=> 1200,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .xtra-menu-background' => 'min-height: {{SIZE}}{{UNIT}};',
				],
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
			'sk_container',
			[
				'label' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.xtra-menu-background' ),
			]
		);

		$this->add_responsive_control(
			'sk_menus',
			[
				'label' 	=> esc_html__( 'Menus', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.xtra-menu-background a' ),
			]
		);

		$this->add_responsive_control(
			'sk_icon',
			[
				'label' 	=> esc_html__( 'Icons', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.xtra-menu-background i' ),
			]
		);

		$this->end_controls_section();

	}

	public function render() {

		$atts = $this->get_settings_for_display();

		$classes = [];
		$classes[] = 'xtra-menu-background clr';
		$classes[] = $atts[ 'first_item' ] ? 'xtra-menu-background-first' : '';

		$children = '';

		foreach( $atts[ 'items' ] as $index => $item ) {

			$icon = '';

			if ( $item['icon_type'] === 'image' ) {

				$img = Group_Control_Image_Size::get_attachment_image_html( $item );
				$icon = '<i class="xtra-menu-background-icon">' . $img . '</i>';

			} else if ( $item['icon'] ) {

				ob_start();
				Icons_Manager::render_icon( $item['icon'], [ 'class' => 'xtra-menu-background-icon' ] );
				$icon = ob_get_clean();

			}

			$this->add_link_attributes( 'link' . $index, $item['link'] );

			$background = Group_Control_Image_Size::get_attachment_image_html( $item, 'background' );

			$children .= '<div><a class="xtra-menu-background-item elementor-repeater-item-' . esc_attr( $item[ '_id' ] ) . '" ' . $this->get_render_attribute_string( 'link' . $index ) . '>' . $icon . esc_html( $item[ 'title' ] ) . '</a>' . $background . '</div>';

			$index++;
		}

		// Out
		echo '<div' . wp_kses_post( (string) Codevz_Plus::classes( [], $classes ) ) . '><div class="xtra-menu-background-items">' . do_shortcode( $children ) . '<div class="xtra-menu-background-glass"></div></div></div>';

	}

}