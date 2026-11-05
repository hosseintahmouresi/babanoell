<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Utils;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Elementor\Icons_Manager;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;

class Xtra_Elementor_Widget_accordion extends Widget_Base { 

	protected $id = 'cz_accordion';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Accordion, Toggle', 'codevz-plus' );
	}
	
	public function get_icon() {
		return 'xtra-accordion';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Accordion', 'codevz-plus' ),
			esc_html__( 'Tabs', 'codevz-plus' ),
			esc_html__( 'Toggle', 'codevz-plus' ),

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

	public function register_controls(){

		$free = Codevz_Plus::is_free();

		$this->start_controls_section(
			'accordion',
			[
				'label' => esc_html__( 'Settings', 'codevz-plus' ),
			]
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'title', [
				'label' => esc_html__( 'Title', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT
			]
		);

		$repeater->add_control(
			'subtitle', [
				'label' => esc_html__( 'Subtitle', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::TEXT
			]
		);

		$repeater->add_control(
			'icon_type', [
				'label' 	=> esc_html__( 'Icon type', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' 	=> 'icon',
				'options' 	=> [
					'' 			=> esc_html__( 'Select', 'codevz-plus' ),
					'icon' 		=> esc_html__( 'Icon', 'codevz-plus' ),
					'image' 	=> esc_html__( 'Image', 'codevz-plus' ),
					'number' 	=> esc_html__( 'Number', 'codevz-plus' ),
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
			'number', [
				'label' => esc_html__( 'Number', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::TEXT,
				'condition' => [
					'icon_type' => 'number'
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

		$repeater->add_responsive_control(
			'sk_icon',
			[
				'label' 	=> esc_html__( 'Icon Style', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '{{CURRENT_ITEM}} .cz-acc-i', '{{CURRENT_ITEM}} .cz_acc_child:hover .cz-acc-i' ),
				'condition' => [
					'icon_type!' => ''
				],
			]
		);

		$repeater->add_control(
			'type', [
				'label' 	=> esc_html__( 'Content type', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SELECT,
				'options' 	=> [
					'' 			=> esc_html__( 'Content', 'codevz-plus' ),
					'template' 	=> esc_html__( 'Saved template', 'codevz-plus' )
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
			'items',
			[
				'label' => esc_html__( 'Item(s)', 'codevz-plus' ),
				'type' => Controls_Manager::REPEATER,
				'prevent_empty' => false,
				'fields' => $repeater->get_controls(),
				'default' => [
					[
						'title' 	=> esc_html__( 'Title', 'codevz-plus' ) . ' 1',
						'content' 	=> esc_html__( 'Content', 'codevz-plus' ),
					],
					[
						'title' 	=> esc_html__( 'Title', 'codevz-plus' ) . ' 2',
						'content' 	=> esc_html__( 'Content', 'codevz-plus' ),
					],
					[
						'title' 	=> esc_html__( 'Title', 'codevz-plus' ) . ' 3',
						'content' 	=> esc_html__( 'Content', 'codevz-plus' ),
					],
				],
			]
		);

		$this->add_control(
			'toggle',
			[
				'label' => esc_html__( 'Toggle mode?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'first_open',
			[
				'label' => esc_html__( '1st open?', 'codevz-plus' ),
				'type' => Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'icon_before',
			[
				'label' => esc_html__( 'Icons before title?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'subtitle_inline',
			[
				'label' => esc_html__( 'Inline subtitle?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'open_icon',
			[
				'label' => esc_html__( 'Default icon', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'skin' => 'inline',
				'label_block' => false,
				'default' => [
					'value' => 'fas fa-angle-down',
					'library' => 'solid',
				],
			]
		);

		$this->add_control(
			'close_icon',
			[
				'label' => esc_html__( 'Activated Icon', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'skin' => 'inline',
				'label_block' => false,
				'default' => [
					'value' => 'fas fa-angle-up',
					'library' => 'solid',
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
			'sk_overall',
			[
				'label' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_acc ' ),
			]
		);

		$this->add_responsive_control(
			'sk_open_icon',
			[
				'label' 	=> esc_html__( 'Default Icon', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_acc_open_icon' ),
			]
		);

		$this->add_responsive_control(
			'sk_close_icon',
			[
				'label' 	=> esc_html__( 'Activated Icon', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_acc .cz_acc_close_icon' ),
			]
		);

		$this->add_responsive_control(
			'sk_title',
			[
				'label' 	=> esc_html__( 'Titles', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_acc .cz_acc_child', '.cz_acc .cz_acc_child:hover' ),
			]
		);

		$this->add_responsive_control(
			'sk_active',
			[
				'label' 	=> esc_html__( 'Active title', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_acc .cz_isOpen .cz_acc_child' ),
			]
		);

		$this->add_responsive_control(
			'sk_subtitle',
			[
				'label' 	=> esc_html__( 'Subtitle', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_acc .cz_acc_child small' ),
			]
		);

		$this->add_responsive_control(
			'sk_content',
			[
				'label' 	=> esc_html__( 'Content', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_acc .cz_acc_child_content' ),
			]
		);

		$this->add_responsive_control(
			'sk_title_icons',
			[
				'label' 	=> esc_html__( 'Title icons', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_acc .cz-acc-i', '.cz_acc .cz_isOpen .cz-acc-i' ),
			]
		);
		
		$this->end_controls_section();

	}

	public function render() {

		$atts = $this->get_settings_for_display();

		// Arrows
		$arrows = [
			'open' 		=> $atts['open_icon'][ 'value' ],
			'close' 	=> $atts['close_icon'][ 'value' ]
		];

		// Classes
		$classes = [];
		$classes[] = 'cz_acc clr';
		$classes[] = $atts['subtitle_inline'] ? 'cz_acc_subtitle_inline' : '';
		$classes[] = $atts['toggle'] ? 'cz_acc_toggle' : '';
		$classes[] = $atts['icon_before'] ? 'cz_acc_icon_before' : '';
		$classes[] = $atts['first_open'] ? 'cz_acc_first_open' : '';

		$children = '';

		foreach( $atts[ 'items' ] as $item ) {

			// Icon
			$icon = '';

			if ( $item['icon_type'] === 'number' ) {

				$icon = '<i class="cz-acc-i cz-acc-number">' . $item['number'] . '</i>';

			} else if ( $item['icon_type'] === 'image' ) {

				$img = Group_Control_Image_Size::get_attachment_image_html( $item );
				$icon = '<i class="cz-acc-i cz-acc-image">' . $img . '</i>';

			} else if ( $item['icon'] ) {

				ob_start();
				Icons_Manager::render_icon( $item['icon'], [ 'class' => 'cz-acc-i cz-acc-icon' ] );
				$icon = ob_get_clean();

			}

			// Subtitle
			$subtitle = $item['subtitle'] ? '<small>' . $item['subtitle'] . '</small>' : '';

			if ( $item[ 'type' ] === 'template' ) {
				$content = Codevz_Plus::get_page_as_element( $item[ 'xtra_elementor_template' ] );
			} else {
				$content = do_shortcode( $item[ 'content' ] );
			}

			$children .= '<div class="elementor-repeater-item-' . esc_attr( $item[ '_id' ] ) . '"><span class="cz_acc_child">' . $icon . '<div>' . $item['title'] . $subtitle . '</div>' . '</span><div class="cz_acc_child_content clr">' . $content . '</div></div>';

		}

		Xtra_Elementor::parallax( $atts );

		echo '<div data-arrows=\'' . wp_json_encode( $arrows ) . '\'' . wp_kses_post( (string) Codevz_Plus::classes( [], $classes ) ) . '><div>' . do_shortcode( $children ) . '</div></div>';

		Xtra_Elementor::parallax( $atts, true );

		// Fix live preivew.
		Xtra_Elementor::render_js( 'accordion' );

	}

}