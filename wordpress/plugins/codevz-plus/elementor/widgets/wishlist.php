<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Codevz_Plus as Codevz_Plus;
use Elementor\Widget_Base;
use Elementor\Icons_Manager;
use Elementor\Controls_Manager;

class Xtra_Elementor_Widget_wishlist extends Widget_Base {

	protected $id = 'cz_wishlist';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Header - Wishlist', 'codevz-plus' );
	}
	
	public function get_icon() {
		return 'xtra-wishlist';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'codevz', 'codevz-plus' ),
			esc_html__( 'Shop', 'codevz-plus' ),
			esc_html__( 'Cart', 'codevz-plus' ),
			esc_html__( 'wishlist', 'codevz-plus' ),
			esc_html__( 'Ajax', 'codevz-plus' ),

		];

	}

	public function register_controls() {

		$this->start_controls_section(
			'section_content',
			[
				'label' 	=> esc_html__( 'Settings', 'codevz-plus' ),
				'tab' 		=> Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'wishlist_icon',
			[
				'label' => esc_html__( 'Icon', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'skin' => 'inline',
				'label_block' => false,
				'default' 		=> [
					'value' 		=> 'fas fa-heart',
					'library' 		=> 'fa-solid',
				]
			]
		);

		$this->add_control(
			'i_position',
			[
				'label' => esc_html__( 'Position', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'' 						=> esc_html__( '~ Default ~', 'codevz-plus' ),
					'cz_helm_pos_left' 		=> esc_html__( 'Left', 'codevz-plus' ),
					//'cz_helm_pos_center' 	=> esc_html__( 'Center', 'codevz-plus' ),
					'cz_helm_pos_right' 	=> esc_html__( 'Right', 'codevz-plus' ),
				]
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
			'sk_wishlist_icon',
			[
				'label' 	=> esc_html__( 'Icon', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.wishlist_icon i' ),
			]
		);

		$this->add_responsive_control(
			'sk_wishlist_count',
			[
				'label' 	=> esc_html__( 'Count', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_wishlist_count' ),
			]
		);

		$this->end_controls_section();
	}

	public function render() {

		$settings = $this->get_settings_for_display();

		Xtra_Elementor::parallax( $settings );

		//$icon = empty( $settings['wishlist_icon'] ) ? 'fa fa-heart-o' : $settings['wishlist_icon'];

		ob_start();
		Icons_Manager::render_icon( $settings['wishlist_icon'], [ 'class' => 'xtra-search-icon', 'data-xtra-icon' => ( empty( $settings['search_icon'][ 'value' ] ) ? 'fa fa-heart-o' : $settings['search_icon'][ 'value' ] ) ] );
		$icon = ob_get_clean();

		$i['wishlist_page'] = Codevz_Plus::option( 'woo_wishlist_page', 'Wishlist' );

		$link = Codevz_Plus::get_page_by_title( $i['wishlist_page'] );

		if ( ! $link ) {
			$link = get_site_url() . '/wishlist';
		}
		
		$wishlist_title = $i['wishlist_page'];

		echo '<div class="' . esc_attr( $settings[ 'i_position' ] ? $settings[ 'i_position' ] . ' ' : '' ) . 'xtra-inline-element elms_wishlist">';
		echo '<a class="wishlist_icon" href="' . esc_url( $link ) . '" title="' . esc_attr( $wishlist_title ) . '">' . do_shortcode( $icon ) . '</a>';
		echo '<span class="cz_wishlist_count"></span>';
		echo '</div>';

		Xtra_Elementor::parallax( $settings, true );

	}

}