<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Codevz_Plus as Codevz_Plus;
use Elementor\Utils;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Elementor\Icons_Manager;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;

class Xtra_Elementor_Widget_expanding_cards extends Widget_Base { 

	protected $id = 'cz_expanding_cards';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Expanding Cards', 'codevz-plus' );
	}

	public function get_icon() {
		return 'xtra-expanding-cards';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [
			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Image', 'codevz-plus' ),
			esc_html__( 'Accordion', 'codevz-plus' ),
			esc_html__( 'Toggle', 'codevz-plus' ),
			esc_html__( 'Expanding', 'codevz-plus' ),
			esc_html__( 'Open', 'codevz-plus' ),
			esc_html__( 'Cards', 'codevz-plus' )
		];

	}

	public function get_style_depends() {
		return [ $this->id, 'cz_parallax' ];
	}

	public function get_script_depends() {
		return [ $this->id, 'cz_parallax' ];
	}

	protected function register_controls() {

		$free = Codevz_Plus::is_free();

		$this->start_controls_section(
			'section_image',
			[
				'label' => esc_html__( 'Settings', 'codevz-plus' ),
			]
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'card_image',
			[
				'label' => esc_html__( 'Card image', 'codevz-plus' ),
				'type' => Controls_Manager::MEDIA
			]
		);

		$repeater->add_group_control(
			Group_Control_Image_Size::get_type(),
			[
				'name' => 'card_image',
				'default' => 'full',
				'separator' => 'none'
			]
		);

		$repeater->add_control(
			'title',
			[
				'label' 	=> esc_html__('Title', 'codevz-plus' ),
				'type' 		=> Controls_Manager::TEXT,
				'default' 	=> esc_html__('This is card headline', 'codevz-plus' ),
				'placeholder' => esc_html__('This is card headline', 'codevz-plus' ),
			]
		);

		$repeater->add_control(
			'subtitle',
			[
				'label' 	=> esc_html__('Subtitle', 'codevz-plus' ),
				'type' 		=> Controls_Manager::TEXT,
				'default' 	=> esc_html__('This is card subtitle', 'codevz-plus' ),
				'placeholder' => esc_html__('This is card subtitle', 'codevz-plus' ),
			]
		);

		$repeater->add_control(
			'icon_type',
			[
				'label' 	=> esc_html__( 'Icon Type', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SELECT,
				'default' 	=> 'icon',
				'options' 	=> [
					'icon' 		=> esc_html__( 'Icon', 'codevz-plus' ),
					'image' 	=> esc_html__( 'Image', 'codevz-plus' ),
					'number' 	=> esc_html__( 'Number', 'codevz-plus' ),
				],
			]
		);

		$repeater->add_control(
			'icon',
			[
				'label' => esc_html__( 'Icon', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'skin' => 'inline',
				'label_block' => false,
				'condition' => [
					'icon_type' => 'icon',
				],
			]
		);

		$repeater->add_control(
			'image',
			[
				'label' => esc_html__( 'Image', 'codevz-plus' ),
				'type' => Controls_Manager::MEDIA,
				'condition' => [
					'icon_type' => 'image',
				],
			]
		);

		$repeater->add_group_control(
			Group_Control_Image_Size::get_type(),
			[
				'name' => 'image',
				'default' => 'full',
				'separator' => 'none'
			]
		);

		$repeater->add_control(
			'number',
			[
				'label' => esc_html__( 'Number', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'condition' => [
					'icon_type' => 'number',
				],
			]
		);

		$repeater->add_responsive_control(
			'sk_icon',
			[
				'label' 	=> esc_html__( 'Icon', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'font-size', 'color', 'background', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '{{CURRENT_ITEM}} .codevz-plus-ex-card-icon' )
			]
		);

		$this->add_control(
			'items',
			[
				'label' => esc_html__( 'Cards', 'codevz-plus' ),
				'type' => Controls_Manager::REPEATER,
				'fields' => $repeater->get_controls(),
				'default' => [
					[
						'card_image' 	=> [
							'id' 			=> '1',
							'url' 			=> 'https://66.media.tumblr.com/6fb397d822f4f9f4596dff2085b18f2e/tumblr_nzsvb4p6xS1qho82wo1_1280.jpg'
						],
						'title' 		=> esc_html__( 'Card title', 'codevz-plus' ),
						'subtitle' 		=> esc_html__( 'This is card subtitle', 'codevz-plus' ),
						'icon_type' 	=> 'icon',
						'icon' 			=> [ 'library' => 'solid', 'value' => 'fas fa-walking' ],
						'sk_icon' 		=> [ 'normal' => 'color:#FC6E51;', 'hover' => '' ],
					],
					[
						'card_image' 	=> [
							'id' 			=> '0',
							'url' 			=> 'https://66.media.tumblr.com/8b69cdde47aa952e4176b4200052abf4/tumblr_o51p7mFFF21qho82wo1_1280.jpg',
						],
						'title' 		=> esc_html__( 'Card title', 'codevz-plus' ),
						'subtitle' 		=> esc_html__( 'This is card subtitle', 'codevz-plus' ),
						'icon_type' 	=> 'icon',
						'icon' 			=> [ 'library' => 'solid', 'value' => 'fas fa-snowflake' ],
						'sk_icon' 		=> [ 'normal' => 'color:#ED5565;', 'hover' => '' ],
					],
					[
						'card_image' 	=> [
							'id' 			=> '0',
							'url' 			=> 'https://66.media.tumblr.com/5af3f8303456e376ceda1517553ba786/tumblr_o4986gakjh1qho82wo1_1280.jpg',
						],
						'title' 		=> esc_html__( 'Card title', 'codevz-plus' ),
						'subtitle' 		=> esc_html__( 'This is card subtitle', 'codevz-plus' ),
						'icon_type' 	=> 'icon',
						'icon' 			=> [ 'library' => 'solid', 'value' => 'fas fa-tree' ],
						'sk_icon' 		=> [ 'normal' => 'color:#FFCE54;', 'hover' => '' ],
					],
					[
						'card_image' 	=> [
							'id' 			=> '0',
							'url' 			=> 'https://66.media.tumblr.com/f19901f50b79604839ca761cd6d74748/tumblr_o65rohhkQL1qho82wo1_1280.jpg',
						],
						'title' 		=> esc_html__( 'Card title', 'codevz-plus' ),
						'subtitle' 		=> esc_html__( 'This is card subtitle', 'codevz-plus' ),
						'icon_type' 	=> 'icon',
						'icon' 			=> [ 'library' => 'solid', 'value' => 'fas fa-sun' ],
						'sk_icon' 		=> [ 'normal' => 'color:#AC92EC;', 'hover' => '' ],
					],
				],
			]
		);

		$this->add_control(
			'cards_type',
			[
				'label' => esc_html__( 'Type', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'' 								=> esc_html__( 'On click', 'codevz-plus' ),
					'codevz-plus-ex-cards-hover' 	=> esc_html__( 'On hover', 'codevz-plus' ),
				]
			]
		);

		$this->add_control(
			'cards_radius',
			[
				'label' => esc_html__( 'Radius', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'options' => array(
					''                                 => esc_html__( "Round 1", 'codevz-plus' ),
					'codevz-plus-ex-cards-round-2'     => esc_html__( "Round 2", 'codevz-plus' ),
					'codevz-plus-ex-cards-round-3'     => esc_html__( "Round 3", 'codevz-plus' ),
					'codevz-plus-ex-cards-round-4'     => esc_html__( "Round 4", 'codevz-plus' ),
					'codevz-plus-ex-cards-square'      => esc_html__( "Square",  'codevz-plus' ),
				)
			]
		);

		$this->add_control(
			'cards_label_position',
			[
				'label' => esc_html__( 'Label position', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'options' => array(
					''                                 => esc_html__( "Bottom", 'codevz-plus' ),
					'codevz-plus-ex-cards-label-top'   => esc_html__( "Top", 'codevz-plus' ),
				)
			]
		);

		$this->add_control(
			'cards_size',
			[
				'label' => esc_html__( 'Background size', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'value' => 'codevz-plus-ex-cards-auto-2',
				'default' => 'codevz-plus-ex-cards-auto-2',
				'options' => array(
					''                                 => esc_html__( "Cover", 'codevz-plus' ),
					'codevz-plus-ex-cards-contain'     => esc_html__( "Contain", 'codevz-plus' ),
					'codevz-plus-ex-cards-auto'        => esc_html__( "Auto", 'codevz-plus' ),
					'codevz-plus-ex-cards-auto-2'      => esc_html__( "Auto + 100%", 'codevz-plus' ),
					'codevz-plus-ex-cards-auto-3'      => esc_html__( "100% + 100%", 'codevz-plus' ),
				)
			]
		);

		$this->end_controls_section();

		// Parallax settings.
		Xtra_Elementor::parallax_settings( $this );

		$this->start_controls_section(
			'section_style_image',
			[
				'label' => esc_html__( 'Styles', 'codevz-plus' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'sk_con',
			[
				'label' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'background', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.codevz-plus-ex-cards' ),
			]
		);

		$this->add_responsive_control(
			'sk_cards',
			[
				'label' 	=> esc_html__( 'Cards', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'background', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.codevz-plus-ex-card' ),
			]
		);

		$this->add_responsive_control(
			'sk_icons',
			[
				'label' 	=> esc_html__( 'Icon', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'font-size', 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.codevz-plus-ex-card-icon' ),
			]
		);

		$this->add_responsive_control(
			'sk_title',
			[
				'label' 	=> esc_html__( 'Title', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'font-size', 'color', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.codevz-plus-ex-card-info strong' ),
			]
		);

		$this->add_responsive_control(
			'sk_subtitle',
			[
				'label' 	=> esc_html__( 'Subtitle', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'font-size', 'color', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.codevz-plus-ex-card-info small' ),
			]
		);

	}

	public function render() {

		$settings = $this->get_settings_for_display();

		// Classes.
		$classes = array();
		$classes[] = 'codevz-plus-ex-cards clr';
		$classes[] = $settings['cards_type'];
		$classes[] = $settings['cards_size'];
		$classes[] = $settings['cards_radius'];
		$classes[] = $settings['cards_label_position'];

		// Parallax.
		Xtra_Elementor::parallax( $settings );

		// Content.
		ob_start();

		$items = (array) $settings['items'];

		foreach( $items as $key => $i ) {

			$card_image = '';

			if ( ! empty( $i['card_image'] ) ) {
				if ( ! empty( $i[ 'card_image' ][ 'url' ] ) ) {
					$card_image = esc_url( $i[ 'card_image' ][ 'url' ] );
				} else {
					$card_image = Group_Control_Image_Size::get_attachment_image_src( $i[ 'card_image' ][ 'id' ], 'card_image', $settings );
				}
			}

			echo '<div class="elementor-repeater-item-' . esc_attr( $i[ '_id' ] ) . ' codevz-plus-ex-card' . ( $key == 0 ? ' codevz-plus-ex-card-active' : '' ) . '" style="background-image:url(' . esc_url( $card_image ) . ');">';
			echo '<div class="codevz-plus-ex-card-label">';

			echo '<div class="codevz-plus-ex-card-icon codevz-plus-ex-card-icon-' . esc_attr( $i['icon_type'] ) . '">';

			if ( isset( $i['icon_type'] ) && $i['icon_type'] === 'image' && ! empty( $i['image'] ) ) {
				echo Group_Control_Image_Size::get_attachment_image_html( $i );
			} else if ( isset( $i['icon_type'] ) && $i['icon_type'] === 'number' && ! empty( $i['number'] ) ) {
				echo $i['number'];
			} else if ( ! empty( $i['icon'] ) ) {
				Icons_Manager::render_icon( $i['icon'] );
			}

			echo '</div>';
			echo '<div class="codevz-plus-ex-card-info">';

			echo empty( $i['title'] ) ? '' : '<strong>' . wp_kses_post( $i['title'] ) . '</strong>';
			echo empty( $i['subtitle'] ) ? '' : '<small>' . wp_kses_post( $i['subtitle'] ) . '</small>';

			echo '</div>';
			echo '</div>';
			echo '</div>';

		}

		$out = ob_get_clean();

		echo '<div' . Codevz_Plus::classes( [], $classes ) . '>' . do_shortcode( $out ) . '</div>';

		// Close parallax.
		Xtra_Elementor::parallax( $settings, true );

	}

}