<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Widget_Base;
use Elementor\Icons_Manager;
use Elementor\Controls_Manager;

class Xtra_Elementor_Widget_popup extends Widget_Base {

	protected $id = 'cz_popup';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Popup', 'codevz-plus' );
	}
	
	public function get_icon() {
		return 'xtra-popup';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Popup', 'codevz-plus' ),
			esc_html__( 'Modal', 'codevz-plus' ),
			esc_html__( 'Box', 'codevz-plus' ),
			esc_html__( 'Dialog', 'codevz-plus' ),

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
				'label' => esc_html__( 'Popup', 'codevz-plus' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'id_popup',
			[
				'label' => esc_html__( 'Unique ID', 'codevz-plus' ),
				'description' => esc_html__( 'You can call this popup with this ID, Example if ID is cz_popup_1, anywhere in page you can add #cz_popup_1 instead button URL', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'default' => 'cz_popup_1',
				'placeholder' => 'cz_popup_1',
			]
		);

		$this->add_control(
			'type', [
				'label' 	=> esc_html__( 'Content type', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SELECT,
				'options' 	=> [
					'' 			=> esc_html__( 'Content', 'codevz-plus' ),
					'template' 	=> esc_html__( 'Saved template', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
				]
			]
		);

		$this->add_control(
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
			'visibility',
			[
				'label' => esc_html__( 'Visibility mode?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'options' => [
					''  => esc_html__( 'Select', 'codevz-plus' ),
					'cz_popup_page_start cz_popup_show_once' => esc_html__( 'Once open on page start loading', 'codevz-plus' ),
					'cz_popup_page_start cz_popup_show_always' => esc_html__( 'Everytime open on page start loading', 'codevz-plus' ),
					'cz_popup_page_loaded cz_popup_show_once' => esc_html__( 'Once open when page fully loaded', 'codevz-plus' ),
					'cz_popup_page_loaded cz_popup_show_always' => esc_html__( 'Everytime open when page fully loaded', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'settimeout',
			[
				'label' => esc_html__( 'Popup after set seconds', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 60,
				'step' => 1,
			]
		);

		$this->add_control(
			'after_scroll',
			[
				'label' => esc_html__( 'Popup after % scroll', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 100,
				'step' => 1,
			]
		);

		$this->add_control(
			'icon',
			[
				'label' 	=> esc_html__( 'Close icon', 'codevz-plus' ),
				'type' 		=> Controls_Manager::ICONS,
				'skin' 		=> 'inline',
				'label_block' => false,
				'default' 	=> [
					'value'  	=> 'fas fa-times',
					'library'  	=> 'fa-solid'
				]
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			[
				'label' => esc_html__( 'Style', 'codevz-plus' ),
				'tab' 	=> Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'overlay_bg',
			[
				'label' => esc_html__( 'Overlay background', 'codevz-plus' ),
				'type' 	=> $free ? 'codevz_pro' : Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .cz_overlay' => 'background-color: {{VALUE}} !important',
				],
			]
		);

		$this->add_responsive_control(
			'sk_popup',
			[
				'label' 	=> esc_html__( 'Popup', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'width', 'height' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_popup_in' ),
			]
		);

		$this->add_responsive_control(
			'sk_icon',
			[
				'label' 	=> esc_html__( 'Close icon', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'box-shadow', 'top', 'right' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_close_popup' ),
			]
		);

		$this->end_controls_section();

	}

	public function render() {

		// Settings.
		$atts = $this->get_settings_for_display();

		// Data.
		$data = $atts['settimeout'] 	? ' data-settimeout="' . $atts['settimeout'] . '000"' : '';
		$data .= $atts['after_scroll'] 	? ' data-after-scroll="' . $atts['after_scroll'] . '"' : '';
		$data .= empty( $atts['overlay_bg'] ) 	? '' : ' data-overlay-bg="' . $atts['overlay_bg'] . '"';

		// Content.
		if ( $atts[ 'type' ] === 'template' ) {
			$content = Codevz_Plus::get_page_as_element( $atts[ 'xtra_elementor_template' ] );
		} else {
			$content = do_shortcode( $atts[ 'content' ] );
		}

		// Classes
		$classes = array();
		$classes[] = 'cz_popup_modal clr';
		$classes[] = $atts['visibility'];

		$preview_btn = \Elementor\Plugin::$instance->editor->is_edit_mode() ? '<a class="codevz-preview-popup" href="#' . esc_attr( $atts['id_popup'] ) . '">' . esc_html__( 'Popup', 'codevz' ) . ' #' . esc_attr( $atts['id_popup'] ) . '</a>' : '';

		// Out
		echo '<div class="xtra-elementor-popup">' . wp_kses_post( $preview_btn ) . '<div id="' . esc_attr( $atts['id_popup'] ) . '"' . wp_kses_post( (string) ( Codevz_Plus::classes( [], $classes ) . $data ) ) . '>';
		echo '<div class="cz_popup_in"><div>' . do_shortcode( (string) $content ) . '</div>';

		Icons_Manager::render_icon( $atts['icon'], [ 'class' => 'cz_close_popup' ] );

		echo '</div><div class="cz_overlay"></div></div></div>';

		// Fix live preivew.
		Xtra_Elementor::render_js( 'countdown' );

	}

}