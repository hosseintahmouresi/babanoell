<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Utils;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Elementor\Icons_Manager;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;

class Xtra_Elementor_Widget_tabs extends Widget_Base {

	protected $id = 'cz_tabs';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Tabs', 'codevz-plus' );
	}
	
	public function get_icon() {
		return 'xtra-tabs';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Tabs', 'codevz-plus' ),

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
			'title', [
				'label' => esc_html__( 'Title', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT
			]
		);

		$repeater->add_control(
			'subtitle', [
				'label' => esc_html__( 'Subtitle', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT
			]
		);

		$repeater->add_control(
			'icon_type', [
				'label' 	=> esc_html__( 'Icon type', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SELECT,
				'default' 	=> 'icon',
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

		$repeater->add_responsive_control(
			'sk_icon',
			[
				'label' 	=> esc_html__( 'Icon Style', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '{{CURRENT_ITEM}} .cz_tab_a i', '{{CURRENT_ITEM}} .cz_tab_a:hover i' ),
				'condition' => [
					'icon_type!' => ''
				],
			]
		);

		$repeater->add_control(
			'link',
			[
				'label' 	=> esc_html__( 'Custom link', 'codevz-plus' ),
				'type' 		=> Controls_Manager::URL,
				'show_external' => true
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
				'type' 		=> Controls_Manager::SELECT,
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
				'fields' => $repeater->get_controls()
			]
		);

		$this->add_control(
			'style',
			[
				'label' => esc_html__( 'Style', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => 'cz_tabs_htl',
				'options' => [
					'cz_tabs_htl'  => esc_html__( 'Horizontal Top Left', 'codevz-plus' ),
					'cz_tabs_htc' => esc_html__( 'Horizontal Top Center', 'codevz-plus' ),
					'cz_tabs_htr' => esc_html__( 'Horizontal Top Right', 'codevz-plus' ),
					'cz_tabs_hbl' => esc_html__( 'Horizontal Bottom Left', 'codevz-plus' ),
					'cz_tabs_hbc' => esc_html__( 'Horizontal Bottom Center', 'codevz-plus' ),
					'cz_tabs_hbr' => esc_html__( 'Horizontal Bottom Right', 'codevz-plus' ),
					'cz_tabs_vl cz_tabs_is_v' => esc_html__( 'Vertical Left', 'codevz-plus' ),
					'cz_tabs_vr cz_tabs_is_v' => esc_html__( 'Vertical Right', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'fx',
			[
				'label' => esc_html__( 'Effect', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'options' => [
					''  => esc_html__( 'Select', 'codevz-plus' ),
					'cz_tabs_blur' => esc_html__( 'Blur', 'codevz-plus' ),
					'cz_tabs_flash' => esc_html__( 'Flash', 'codevz-plus' ),
					'cz_tabs_zoom_in' => esc_html__( 'Zoom In', 'codevz-plus' ),
					'cz_tabs_zoom_out' => esc_html__( 'Zoom Out', 'codevz-plus' ),
					'cz_tabs_fade_in_up' => esc_html__( 'From Down', 'codevz-plus' ),
					'cz_tabs_fade_in_down' => esc_html__( 'From Up', 'codevz-plus' ),
					'cz_tabs_fade_in_right' => esc_html__( 'From Left', 'codevz-plus' ),
					'cz_tabs_fade_in_left' => esc_html__( 'From Right', 'codevz-plus' ),
					'cz_tabs_rotate' => esc_html__( 'Rotate', 'codevz-plus' ),
					'cz_tabs_right_left' => esc_html__( 'Right then Left', 'codevz-plus' ),
					'cz_tabs_swing' => esc_html__( 'Swing', 'codevz-plus' ),
					'cz_tabs_bounce' => esc_html__( 'Bounce', 'codevz-plus' ),
					'cz_tabs_wobble_skew' => esc_html__( 'Wobble skew', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'on_hover',
			[
				'label' => esc_html__( 'Hover instead click?', 'codevz-plus' ),
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
			'sk_con',
			[
				'label' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_tabs' ),
			]
		);

		$this->add_responsive_control(
			'sk_row',
			[
				'label' 	=> esc_html__( 'Tabs Row', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_tabs .cz_tabs_nav div' ),
			]
		);

		$this->add_responsive_control(
			'sk_tabs',
			[
				'label' 	=> esc_html__( 'Tabs title', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'width', 'color', 'text-align', 'font-size', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_tabs .cz_tab_a' ),
			]
		);

		$this->add_responsive_control(
			'sk_tabs_subtitle',
			[
				'label' 	=> esc_html__( 'Tabs subtitle', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'width', 'color', 'text-align', 'font-size', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_tabs .cz_tab_in_title small' ),
			]
		);

		$this->add_responsive_control(
			'sk_active',
			[
				'label' 	=> esc_html__( 'Active Tab', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_tabs .cz_tab_a.active, .cz_tabs .cz_tab_a.cz_active' ),
			]
		);

		$this->add_responsive_control(
			'sk_active_subtitle',
			[
				'label' 	=> esc_html__( 'Active Tab subtitle', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'width', 'color', 'text-align', 'font-size', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_tabs .cz_tab_a.active .cz_tab_in_title small, .cz_tabs .cz_tab_a.cz_active .cz_tab_in_title small' ),
			]
		);

		$this->add_responsive_control(
			'sk_tabs_i',
			[
				'label' 	=> esc_html__( 'Icons', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_tabs .cz_tab_a i' ),
			]
		);

		$this->add_responsive_control(
			'sk_content',
			[
				'label' 	=> esc_html__( 'Content', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_tabs .cz_tab' ),
			]
		);

		$this->end_controls_section();

	}

	public function render() {

		$atts = $this->get_settings_for_display();

		// Classes
		$classes = array();
		$classes[] = 'cz_tabs clr';
		$classes[] = $atts['style'];
		$classes[] = $atts['fx'];
		$classes[] = $atts['on_hover'] ? 'cz_tabs_on_hover' : '';
		$classes[] = ( strpos( $atts['style'], 'hb' ) !== false ) ? 'cz_tabs_nav_after' : '';

		$children = '';

		foreach( array_reverse( $atts[ 'items' ] ) as $index => $item ) {

			// Icon
			$icon = '';
			$icon_class = ( $item['title'] ? ' mr8' : '' );

			if ( $item['icon_type'] === 'image' ) {

				$img = Group_Control_Image_Size::get_attachment_image_html( $item );
				$icon = '<i class="cz_tab_image' . $icon_class . '">' . $img . '</i>';

			} else if ( $item['icon'] ) {

				ob_start();
				Icons_Manager::render_icon( $item['icon'], [ 'class' => 'cz_tab_icon' . $icon_class ] );
				$icon = ob_get_clean();

			}

			// Subtitle
			$item['title'] .= $item['subtitle'] ? '<small>' . $item['subtitle'] . '</small>' : '';
			$item['title'] = $item['title'] ? '<span class="cz_tab_in_title">' . $item['title'] . '</span>' : '';

			// Content.
			if ( $item[ 'type' ] === 'template' ) {
				$content = Codevz_Plus::get_page_as_element( $item[ 'xtra_elementor_template' ] );
			} else {
				$content = do_shortcode( $item[ 'content' ] );
			}

			$this->add_link_attributes( 'link' . $index, $item['link'] );

			$children .= '<a class="cz_tab_a hide elementor-repeater-item-' . esc_attr( $item[ '_id' ] ) . '" data-tab="' . esc_attr( $item[ '_id' ] ) . '" ' . $this->get_render_attribute_string( 'link' . $index ) . '>' . $icon . $item['title'] . '</a><div id="' . esc_attr( $item[ '_id' ] ) . '" class="cz_tab"><div>' . $content . '</div></div>';

			$index++;
		}

		// Out
		echo '<div' . wp_kses_post( (string) Codevz_Plus::classes( [], $classes ) ) . '><div class="cz_tabs_content">' . do_shortcode( $children ) . '</div></div>';

		// Fix live preivew.
		Xtra_Elementor::render_js( 'tabs' );
	}

}