<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Repeater;
use Elementor\Widget_Base;
use Elementor\Icons_Manager;
use Elementor\Controls_Manager;

class Xtra_Elementor_Widget_timeline extends Widget_Base {

	protected $id = 'cz_timeline';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Timeline', 'codevz-plus' );
	}
	
	public function get_icon() {
		return 'xtra-timeline';
	}
	
	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Timeline', 'codevz-plus' ),

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
			'timeline',
			[
				'label' 	=> esc_html__( 'Timeline', 'codevz-plus' ),
				'tab' 		=> Controls_Manager::TAB_CONTENT,
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
			'position',
			[
				'label' => esc_html__( 'Block Position', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'cz_tl_left',
				'options' => [
					'cz_tl_left' => esc_html__( 'Left', 'codevz-plus' ),
					'cz_tl_center' => esc_html__( 'Full Center', 'codevz-plus' ),
					'cz_tl_right' => esc_html__( 'Right', 'codevz-plus' ),
				],
			]
		);

		$repeater->add_responsive_control(
			'content_width',
			[
				'label' => esc_html__( 'Content Width', 'codevz-plus' ),
				'type' => Controls_Manager::SLIDER,
				'default' => [
					'unit' => 'px',
					'size' => 50,
				],
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 50,
						'max' => 500,
						'step' => 1,
					],
				],
				'selectors' => [
					'{{WRAPPER}} {{CURRENT_ITEM}} .cz_tl_center .cz_timeline-content' => 'width: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'position' => 'cz_tl_center',
				]
			]
		);

		$repeater->add_control(
			'icon',
			[
				'label' => esc_html__( 'Icon', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'skin' => 'inline',
				'label_block' => false,
			]
		);

		$repeater->add_control(
			'date',
			[
				'label' => esc_html__( 'Date', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT
			]
		);

		$repeater->add_responsive_control(
			'sk_icon',
			[
				'label' 	=> esc_html__( 'Icon', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'text-align', 'font-size', 'font-weight', 'line-height', 'letter-spacing', 'background', 'border', 'box-shadow', 'text-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '{{CURRENT_ITEM}} .cz_tl_icon', '{{CURRENT_ITEM}}:hover .cz_tl_icon' ),
			]
		);

		$repeater->add_responsive_control(
			'sk_content',
			[
				'label' 	=> esc_html__( 'Content', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border', 'box-shadow', 'text-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '{{CURRENT_ITEM}} .cz_timeline-content' ),
			]
		);

		$repeater->add_responsive_control(
			'sk_date',
			[
				'label' 	=> esc_html__( 'Date', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'font-family', 'font-size', 'font-weight', 'line-height', 'letter-spacing', 'background', 'border', 'box-shadow', 'text-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '{{CURRENT_ITEM}} .cz_date' ),
			]
		);

		$this->add_control(
			'items',
			[
				'label' => esc_html__( 'Items', 'codevz-plus' ),
				'type' => Controls_Manager::REPEATER,
				'prevent_empty' => false,
				'fields' => $repeater->get_controls(),
				'default' => [
					[
						'content' 	=> '<h4>' . esc_html__( 'Timeline title', 'codevz-plus' ) . '</h4><p>' . esc_html__( 'Timeline custom content ...', 'codevz-plus' ) . '</p>',
						'position'  => 'cz_tl_left'
					],
					[
						'content' 	=> '<h4>' . esc_html__( 'Timeline title', 'codevz-plus' ) . '</h4><p>' . esc_html__( 'Timeline custom content ...', 'codevz-plus' ) . '</p>',
						'position'  => 'cz_tl_right'
					],
					[
						'content' 	=> '<h4>' . esc_html__( 'Timeline title', 'codevz-plus' ) . '</h4><p>' . esc_html__( 'Timeline custom content ...', 'codevz-plus' ) . '</p>',
						'position'  => 'cz_tl_center'
					],
				],
			]
		);

		$this->add_control(
			'style',
			[
				'label' => esc_html__( 'Style', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => 'cz_tl_1',
				'options' => [
					'cz_tl_1' => esc_html__( 'Style', 'codevz-plus' ) . ' 1',
					'cz_tl_2' => esc_html__( 'Style', 'codevz-plus' ) . ' 2',
					'cz_tl_3' => esc_html__( 'Style', 'codevz-plus' ) . ' 3',
					'cz_tl_4' => esc_html__( 'Style', 'codevz-plus' ) . ' 4',
					'cz_tl_5' => esc_html__( 'Style', 'codevz-plus' ) . ' 5',
				],
			]
		);

		$this->add_control(
			'align',
			[
				'label' => esc_html__( 'Align', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'cz_a_c',
				'options' => [
					'cz_a_c' => esc_html__( 'Center', 'codevz-plus' ),
					'cz_a_l' => esc_html__( 'Left', 'codevz-plus' ),
					'cz_a_r' => esc_html__( 'Right', 'codevz-plus' ),
				],
			]
		);

		$this->end_controls_section();

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
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_timeline_container' ),
			]
		);

		$this->add_responsive_control(
			'sk_vline',
			[
				'label' 	=> esc_html__( 'Line', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_timeline_container:before' ),
			]
		);

		$this->add_responsive_control(
			'sk_icon',
			[
				'label' 	=> esc_html__( 'Icon', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'text-align', 'font-size', 'font-weight', 'line-height', 'letter-spacing', 'background', 'border', 'box-shadow', 'text-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_tl_icon', '.cz_timeline-block:hover .cz_tl_icon' ),
			]
		);

		$this->add_responsive_control(
			'sk_content',
			[
				'label' 	=> esc_html__( 'Content', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border', 'box-shadow', 'text-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_timeline-content' ),
			]
		);

		$this->add_responsive_control(
			'sk_date',
			[
				'label' 	=> esc_html__( 'Date', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'font-family', 'font-size', 'font-weight', 'line-height', 'letter-spacing', 'background', 'border', 'box-shadow', 'text-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_date' ),
			]
		);

		$this->end_controls_section();

	}

	public function render() {

		// Settings.
		$settings = $this->get_settings_for_display();

		$content = '';

		foreach( $settings[ 'items' ] as $item ) {

			// Icon.
			ob_start();
			Icons_Manager::render_icon( $item['icon'], [ 'class' => 'cz_tl_icon' ] );
			$icon = ob_get_clean();

			// Position
			if ( $item['position'] === 'cz_tl_center' ) {
				$border_pos = 'bottom';
			} else if ( $item['position'] === 'cz_tl_right' ) {
				$border_pos = 'right';
			} else {
				$border_pos = 'left';
			}

			// Content.
			if ( $item[ 'type' ] === 'template' ) {
				$inner_content = Codevz_Plus::get_page_as_element( $item[ 'xtra_elementor_template' ] );
			} else {
				$inner_content = do_shortcode( $item[ 'content' ] );
			}

			// Classes
			$classes = array();
			$classes[] = 'cz_timeline-block';
			$classes[] = 'elementor-repeater-item-' . esc_attr( $item[ '_id' ] );
			$classes[] = $item['position'];

			// Out
			$content .= '<div' . Codevz_Plus::classes( [], $classes ) . '><div class="cz_timeline-i" >' . $icon . '</div><div class="cz_timeline-content">' . $inner_content . '<span class="cz_date">'. $item['date'] .'</span></div></div>';

		}

		// Classes
		$classes = [];
		$classes[] = 'cz_timeline_container';
		$classes[] = $settings['style'];
		$classes[] = $settings['align'];

		echo '<section' . wp_kses_post( (string) Codevz_Plus::classes( [], $classes ) ) . '><div>' . do_shortcode( $content ) . '</div></section>';

	}

}