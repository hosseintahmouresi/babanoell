<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Codevz_Plus as Codevz_Plus;
use Elementor\Widget_Base;
use Elementor\Icons_Manager;
use Elementor\Controls_Manager;

class Xtra_Elementor_Widget_search extends Widget_Base {

	protected $id = 'cz_search';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Header - Search', 'codevz-plus' );
	}
	
	public function get_icon() {
		return 'xtra-search';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Search', 'codevz-plus' ),
			esc_html__( 'AJAX', 'codevz-plus' ),

		];

	}

	public function get_script_depends() {
		return [ $this->id, 'codevz-search' ];
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
			'search_type',
			[
				'label' 		=> esc_html__( 'Type', 'codevz-plus' ),
				'type' 			=> Controls_Manager::SELECT,
				'default' 		=> 'icon_dropdown',
				'options' 		=> [
					'icon_dropdown'	=> esc_html__( 'Dropdown', 'codevz-plus' ),
					'form' 			=> esc_html__( 'Form', 'codevz-plus' ),
					'form_2' 		=> esc_html__( 'Form', 'codevz-plus' ) . ' 2',
					'icon_full' 	=> esc_html__( 'Fullscreen', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			's_position',
			[
				'label' => esc_html__( 'Position', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'' 						=> esc_html__( '~ Default ~', 'codevz-plus' ),
					'cz_helm_pos_left' 		=> esc_html__( 'Left', 'codevz-plus' ),
					'cz_helm_pos_center' 	=> esc_html__( 'Center', 'codevz-plus' ),
					'cz_helm_pos_right' 	=> esc_html__( 'Right', 'codevz-plus' ),
				]
			]
		);

		$this->add_control(
			'search_icon',
			[
				'label' => esc_html__( 'Icon', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'skin' => 'inline',
				'label_block' => false,
				'default' 		=> [
					'value' 		=> 'fas fa-search',
					'library' 		=> 'fa-solid',
				]
			]
		);

		$this->add_control(
			'search_placeholder',
			[
				'label' 	=> esc_html__( 'Title', 'codevz-plus' ),
				'type' 		=> Controls_Manager::TEXT
			]
		);

		$this->add_control(
			'search_only_products',
			[
				'label' => esc_html__( 'Only products?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'ajax_search',
			[
				'label' => esc_html__( 'Ajax Search', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'search_no_thumbnail',
			[
				'label' => esc_html__( 'No Image', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'search_post_icon',
			[
				'label' => esc_html__( 'Icon', 'codevz-plus' ),
				'description' => esc_html__( 'Icon for posts without image', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'skin' => 'inline',
				'label_block' => false
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
			'sk_search_con',
			[
				'label' 	=> esc_html__( 'Search', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.outer_search' ),
			]
		);

		$this->add_responsive_control(
			'sk_search_input',
			[
				'label' 	=> esc_html__( 'Search Input', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.ajax_search_input' ),
			]
		);

		$this->add_responsive_control(
			'sk_search_icon',
			[
				'label' 	=> esc_html__( 'Search Icon', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.xtra-search-icon' ),
			]
		);

		$this->add_responsive_control(
			'sk_search_icon_in',
			[
				'label' 	=> esc_html__( 'Input Icon', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( 'button i' ),
			]
		);

		$this->add_responsive_control(
			'sk_search_ajax',
			[
				'label' 	=> esc_html__( 'AJAX Container', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.ajax_search_results' ),
			]
		);

		$this->add_responsive_control(
			'sk_search_post_icon',
			[
				'label' 	=> esc_html__( 'Posts Icon', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.xtra-ajax-search-post-icon' ),
			]
		);

		$this->end_controls_section();
	}

	public function render() {

		$settings = $this->get_settings_for_display();

		Xtra_Elementor::parallax( $settings );

		if ( $settings['s_position'] ) {
			echo '<div class="' . esc_attr( $settings['s_position'] ) . '">';
		}

		ob_start();
		Icons_Manager::render_icon( $settings['search_icon'], [ 'class' => 'xtra-search-icon', 'data-xtra-icon' => ( empty( $settings['search_icon'][ 'value' ] ) ? 'fa fa-search' : $settings['search_icon'][ 'value' ] ) ] );
		$icon = ob_get_clean();

		$ajax = empty( $settings['ajax_search'] ) ? '' : ' cz_ajax_search';

		$settings['search_type'] = empty( $settings['search_type'] ) ? 'form' : $settings['search_type'];
		$settings['search_placeholder'] = empty( $settings['search_placeholder'] ) ? '' : do_shortcode( $settings['search_placeholder'] );

		echo '<div class="xtra-inline-element search_with_icon search_style_' . esc_attr( $settings['search_type'] . $ajax ) . '">';

		echo Codevz_Plus::contains( esc_attr( $settings['search_type'] ), 'form' ) ? '' : do_shortcode( $icon );

		echo '<i class="fa czico-198-cancel cz_close_popup xtra-close-icon hide"></i>';

		echo '<div class="outer_search"><div class="search">'; ?>
			<form method="get" action="<?php echo esc_url( trailingslashit( get_home_url() ) ); ?>" autocomplete="off">
				<?php 
					if ( $settings['search_type'] === 'icon_full' ) {
						echo '<span>' . esc_html( $settings['search_placeholder'] ) . '</span>';
						$settings['search_placeholder'] = '';
					}

					if ( $ajax ) {
						echo '<input name="nonce" type="hidden" value="' . esc_attr( wp_create_nonce( 'ajax_search_nonce' ) ) . '" />';
					}

					if ( ! empty( $settings[ 'search_only_products' ] ) ) {
						echo '<input name="post_type" type="hidden" value="product" />';
					}

					if ( ! empty( $settings[ 'search_no_thumbnail' ] ) ) {
						echo '<input name="no_thumbnail" type="hidden" value="' . esc_attr( $settings['search_no_thumbnail'] ) . '" />';
					}

					if ( ! empty( $settings[ 'search_post_icon' ][ 'value' ] ) ) {
						echo '<input name="search_post_icon" type="hidden" value="' . esc_attr( $settings['search_post_icon'][ 'value' ] ) . '" />';
					}

					if ( ! empty( $settings[ 'sk_search_post_icon' ] ) ) {
						echo '<input name="sk_search_post_icon" type="hidden" value="' . esc_attr( $settings['sk_search_post_icon'] ) . '" />';
					}

					if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
						echo '<input name="lang" type="hidden" value="' . esc_attr( ICL_LANGUAGE_CODE ) . '" />';
					}

				?>

				<input class="ajax_search_input" name="s" type="text" placeholder="<?php echo esc_attr( $settings['search_placeholder'] ); ?>">
				<button type="submit"><?php echo do_shortcode( $icon ); ?></button>
			</form>
			<div class="ajax_search_results"></div>
		</div></div></div><?php

		if ( $settings['s_position'] ) {
			echo '</div>';
		}

		Xtra_Elementor::parallax( $settings, true );

	}

}