<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Codevz_Plus as Codevz_Plus;
use Elementor\Widget_Base;
use Elementor\Icons_Manager;
use Elementor\Controls_Manager;

class Xtra_Elementor_Widget_wpml extends Widget_Base {

	protected $id = 'cz_wpml';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Header - WPML', 'codevz-plus' );
	}
	
	public function get_icon() {
		return 'xtra-wpml';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'codevz', 'codevz-plus' ),
			esc_html__( 'wpml', 'codevz-plus' ),
			esc_html__( 'Language', 'codevz-plus' ),
			esc_html__( 'Ajax', 'codevz-plus' ),

		];

	}

	public function register_controls() {

		$free = Codevz_Plus::is_free();

		$this->start_controls_section(
			'section_content',
			[
				'label' 	=> esc_html__( 'Settings', 'codevz-plus' ),
				'tab' 		=> Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'wpml_title',
			[
				'label' 		=> esc_html__( 'Type', 'codevz-plus' ),
				'type' 			=> Controls_Manager::SELECT,
				'options' 		=> [
					'translated_name' 	=> esc_html__( 'Translated Name', 'codevz-plus' ),
					'language_code' 	=> esc_html__( 'Language code', 'codevz-plus' ),
					'native_name' 		=> esc_html__( 'Native name', 'codevz-plus' ),
					'translated_name' 	=> esc_html__( 'Translated name', 'codevz-plus' ),
					'no_title' 			=> esc_html__( 'No title', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'wpml_flag',
			[
				'label' => esc_html__( 'Flag', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'wpml_current_color',
			[
				'label' => esc_html__( 'Current Language', 'codevz-plus' ),
				'type' => Controls_Manager::COLOR
			]
		);

		$this->add_control(
			'wpml_background',
			[
				'label' => esc_html__( 'Background', 'codevz-plus' ),
				'type' => Controls_Manager::COLOR
			]
		);

		$this->add_control(
			'wpml_color',
			[
				'label' => esc_html__( 'Inner Color', 'codevz-plus' ),
				'type' => Controls_Manager::COLOR
			]
		);

		$this->add_control(
			'wpml_opposite',
			[
				'label' => esc_html__( 'Toggle Mode', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
			]
		);

		$this->end_controls_section();

		// Parallax settings.
		Xtra_Elementor::parallax_settings( $this );
	}

	public function render() {

		$settings = $this->get_settings_for_display();

		Xtra_Elementor::parallax( $settings );

		if ( function_exists( 'icl_get_languages' ) ) {

			$wpml = icl_get_languages( 'skip_missing=N&orderby=KEY&order=DIR&link_empty_to=str' );

			$wpml_opposite = empty( $settings['wpml_opposite'] ) ? '' : ' data-cz-style=".cz_language_switcher a { display: none } .cz_language_switcher div { display: block; position: static; transform: none; } .cz_language_switcher div a { display: block; }"';

			if ( is_array( $wpml ) ) {
				$bg = empty( $settings['wpml_background'] ) ? '' : 'background: ' . esc_attr( $settings['wpml_background'] ) . '';
				echo '<div class="xtra-inline-element cz_language_switcher"' . wp_kses_post( (string) $wpml_opposite ) . '><div style="' . esc_attr( $bg ) . '">';
				foreach( $wpml as $lang => $vals ) {
					if ( ! empty( $vals ) ) {

						$class = $vals['active'] ? 'cz_current_language' : '';
						if ( empty( $settings['wpml_title'] ) ) {
							$title = $vals['translated_name'];
						} else if ( $settings['wpml_title'] !== 'no_title' ) {
							$title = ucwords( $vals[ $settings['wpml_title'] ] );
						} else {
							$title = '';
						}

						$color = '';
						if ( $class && ! empty( $settings['wpml_color'] ) ) {
							$color = 'color: ' . esc_attr( $settings['wpml_current_color'] );
						} else if ( ! $class && ! empty( $settings['wpml_color'] ) ) {
							$color = 'color: ' . esc_attr( $settings['wpml_color'] );
						}

						if ( !empty( $settings['wpml_flag'] ) ) {
							echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $vals['url'] ) . '" style="' . esc_attr( $color ) . '"><img loading="lazy" src="' . esc_url( $vals['country_flag_url'] ) . '" alt="#" width="200" height="200" class="' . esc_attr( $title ? 'mr8' : '' ) . '" />' . esc_html( $title ) . '</a>';
						} else {
							echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $vals['url'] ) . '" style="' . esc_attr( $color ) . '">' . esc_html( $title ) . '</a>';
						}

					}
				}
				echo '</div></div>';
			}

		} else {

			echo esc_html__( 'Please install WPML plugin.', 'codevz-plus' );

		}

		Xtra_Elementor::parallax( $settings, true );

	}

}