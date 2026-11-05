<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Utils;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Elementor\Icons_Manager;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;

class Xtra_Elementor_Widget_banner_group extends Widget_Base {

	protected $id = 'cz_banner_group';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Banner Group', 'codevz-plus' );
	}
	
	public function get_icon() {
		return 'xtra-banner-group';
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
			'type', [
				'label' 	=> esc_html__( 'Content type', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SELECT,
				'options' 	=> [
					'' 			=> esc_html__( 'Content', 'codevz-plus' ),
					'template' 	=> esc_html__( 'Saved template', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' )
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
				'default' 	=> [ 'url' => '#' ]
			]
		);

		$repeater->add_responsive_control(
			'sk_container',
			[
				'label' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '{{CURRENT_ITEM}}' ),
			]
		);

		$repeater->add_responsive_control(
			'sk_content',
			[
				'label' 	=> esc_html__( 'Content', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '{{CURRENT_ITEM}} > div' ),
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
						'content' => 'Item 1'
					],
					[
						'content' => 'Item 2'
					],
					[
						'content' => 'Item 3'
					],
					[
						'content' => 'Item 4'
					]
				]
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
					'{{WRAPPER}} .xtra-banner-group-item' => 'min-height: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'show_default_item',
			[
				'label' => esc_html__( 'Show default', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER
			]
		);

		$this->add_control(
			'not_visible',
			[
				'label' => esc_html__( 'Hide items on hover', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER
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
			'xtra_banner_border_size',
			[
				'label' => esc_html__( 'Border size', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 50,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .xtra-banner-group,{{WRAPPER}} .xtra-banner-group-item' => 'border-width: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'xtra_banner_border_color',
			[
				'label' => esc_html__( 'Color', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .xtra-banner-group,{{WRAPPER}} .xtra-banner-group-item' => 'border-color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'sk_container',
			[
				'label' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.xtra-banner-group' ),
			]
		);

		$this->add_responsive_control(
			'sk_items',
			[
				'label' 	=> esc_html__( 'Items', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.xtra-banner-group-item' ),
			]
		);

		$this->add_responsive_control(
			'sk_content',
			[
				'label' 	=> esc_html__( 'Content', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.xtra-banner-group-item > div' ),
			]
		);

		$this->add_responsive_control(
			'sk_icon',
			[
				'label' 	=> esc_html__( 'Icon', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.xtra-banner-group-icon' ),
			]
		);

		$this->end_controls_section();

	}

	public function render() {

		$atts = $this->get_settings_for_display();

		$classes = [];
		$classes[] = 'xtra-banner-group clr';
		$classes[] = $atts[ 'show_default_item' ] ? 'codevz-banner-group-show-default' : '';
		$classes[] = $atts[ 'not_visible' ] ? 'xtra-banner-group-not-visible' : '';

		$children = '';

		foreach( $atts[ 'items' ] as $index => $item ) {

			$icon = '';

			if ( $item['icon_type'] === 'image' ) {

				$img = Group_Control_Image_Size::get_attachment_image_html( $item );
				$icon = '<i class="cz-banner-group-icon">' . $img . '</i>';

			} else if ( $item['icon_type'] === 'icon' && $item['icon'] ) {

				ob_start();
				Icons_Manager::render_icon( $item['icon'], [ 'class' => 'cz-banner-group-icon' ] );
				$icon = ob_get_clean();

			}

			if ( $item[ 'type' ] === 'template' ) {

				$content = Codevz_Plus::get_page_as_element( $item[ 'xtra_elementor_template' ] );

			} else {

				$content = do_shortcode( $item[ 'content' ] );

			}

			$this->add_link_attributes( 'link' . $index, $item['link'] );

			$background = Group_Control_Image_Size::get_attachment_image_html( $item, 'background' );

			$children .= '<a class="xtra-banner-group-item elementor-repeater-item-' . esc_attr( $item[ '_id' ] ) . '" ' . $this->get_render_attribute_string( 'link' . $index ) . '><div>' . $icon . $content . '</div>' . $background . '</a>';

			$index++;
		}

		// Out
		echo '<div' . wp_kses_post( (string) Codevz_Plus::classes( [], $classes ) ) . '><div></div>' . do_shortcode( $children ) . '</div>';

	}

}