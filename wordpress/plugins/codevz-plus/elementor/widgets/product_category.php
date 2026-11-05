<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Utils;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Elementor\Icons_Manager;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;

class Xtra_Elementor_Widget_product_category extends Widget_Base {

	protected $id = 'cz_product_category';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Products by Category', 'codevz-plus' );
	}

	public function get_icon() {
		return 'xtra-product-category';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Grid', 'codevz-plus' ),
			esc_html__( 'category', 'codevz-plus' ),
			esc_html__( 'categories', 'codevz-plus' ),
			esc_html__( 'Product', 'codevz-plus' ),
			esc_html__( 'Woocommerce', 'codevz-plus' ),
			esc_html__( 'Shop', 'codevz-plus' ),
			esc_html__( 'Store', 'codevz-plus' ),

		];

	}

	public function get_product_category() {

		$args = [
			'taxonomy'     => 'product_cat',
			'orderby'      => 'name',
			'show_count'   => 0,
			'pad_counts'   => 0,
			'hierarchical' => 1,
			'title_li'     => '',
			'hide_empty'   => 0
		];

		$options = [];

		$options[] = esc_html__( '~ Select ~', 'codevz-plus' );

		$all_categories = get_categories( $args );

		foreach( $all_categories as $cat ) {
			$options[ $cat->term_id ] = $cat->name;
		}

		return $options;

	}

	public function register_controls() {

		$free = Codevz_Plus::is_free();

		$this->start_controls_section(
			'settings',
			[
				'label' => esc_html__( 'Settings', 'codevz-plus' ),
				'tab' 	=> Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'product_category',
			[
				'label' 	=> esc_html__( 'Select category', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SELECT,
				'options' 	=> $this->get_product_category()
			]
		);

		$this->add_control(
			'limit',
			[
				'label' 	=> esc_html__( 'Limit', 'codevz-plus' ),
				'type' 		=> Controls_Manager::NUMBER,
				'default' 	=> 6
			]
		);
	
		$this->add_control(
			'columns',
			[
				'label' 	=> esc_html__( 'Columns', 'codevz-plus' ),
				'type' 		=> Controls_Manager::NUMBER,
				'default' 	=> 4,
				'condition' => [
					'carousel' => '',
				]
			]
		);

		$this->add_control(
			'hide_empty',
			[
				'label' 	=> esc_html__( 'Hide Empty', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				
			]
		);

		$this->add_control(
			'parent',
			[
				'label' 	=> esc_html__( 'Parent', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'orderby',
			[
				'label' 	=> esc_html__( 'Orderby', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' 	=> 'date',
				'options' 	=> [
					'date' 		=> esc_html__( 'Date', 'codevz-plus' ),
					'ID' 		=> esc_html__( 'ID', 'codevz-plus' ),
					'author' 	=> esc_html__( 'Popularity', 'codevz-plus' ),
					'title' 	=> esc_html__( 'Rand', 'codevz-plus' ),
					'name' 		=> esc_html__( 'Rating', 'codevz-plus' ),
					'type' 		=> esc_html__( 'Title', 'codevz-plus' ),

				],
			]
		);

		$this->end_controls_section();

		// Carousel
		$this->start_controls_section(
			'section_carousel',
			[
				'label' => esc_html__( 'Carousel', 'codevz-plus' )
			]
		);

		$this->add_control(
			'carousel',
			[
				'label' => esc_html__( 'Carousel?', 'codevz-plus' ),
				'type' => Controls_Manager::SWITCHER
			]
		);

		$this->add_control(
			'carousel_type',
			[
				'label' => esc_html__( 'Type', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'' 	=> esc_html__( '~ Default ~', 'codevz-plus' ),
					'2' => esc_html__( '3D Panorama', 'codevz-plus' ),
				],
				'condition' 	=> [
					'carousel!' => ''
				]
			]
		);

		$this->add_control(
			'panorama_notice',
			[
				'type' 			=> Controls_Manager::NOTICE,
				'notice_type' 	=> 'warning',
				'dismissible' 	=> false,
				'heading' 		=> esc_html__( '3D Panorama', 'codevz-plus' ),
				'content' 		=> esc_html__( 'The 3D Panorama effect is only supported when the number of slides to show is set to an odd number (1, 3, 5, or 7), It will not work correctly with even numbers, and same images size recommended', 'codevz-plus' ),
				'condition' 	=> [
					'carousel!' => '',
					'carousel_type' => '2'
				]
			]
		);

		$this->add_responsive_control(
			'slidestoshow',
			[
				'label' 	=> esc_html__( 'Slides to show', 'codevz-plus' ),
				'type' 		=> Controls_Manager::NUMBER,
				'default' 	=> 3,
				'min' 		=> 1,
				'max' 		=> 10,
				'step' 		=> 1,
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_responsive_control(
			'slidestoscroll',
			[
				'label' 	=> esc_html__( 'Slides to scroll', 'codevz-plus' ),
				'type' 		=> Controls_Manager::NUMBER,
				'default' 	=> 1,
				'min' 		=> 1,
				'max' 		=> 10,
				'step' 		=> 1,
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_control(
			'infinite',
			[
				'label' => esc_html__( 'Infinite?', 'codevz-plus' ),
				'type' => Controls_Manager::SWITCHER,
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_control(
			'autoplay',
			[
				'label' 	=> esc_html__( 'Auto play?', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SWITCHER,
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_control(
			'autoplayspeed',
			[
				'label' => esc_html__( 'Autoplay delay (ms)', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::NUMBER,
				'default' => 4000,
				'min' => 1000,
				'max' => 6000,
				'step' => 500,
				'condition' => [
					'carousel!' => '',
				]
				
			]
		);

		$this->add_responsive_control(
			'gap',
			[
				'label' => esc_html__( 'Gap', 'codevz-plus' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 300,
						'step' => 1,
					],
				],
				'default' => [
					'unit' => 'px',
					'size' => 30
				],
				'selectors' => [
					'{{WRAPPER}} .slick-list' => 'margin-left: calc(-{{SIZE}}{{UNIT}} / 2);margin-right: calc(-{{SIZE}}{{UNIT}} / 2);margin-bottom: -{{SIZE}}{{UNIT}};width: calc(100% + {{SIZE}}{{UNIT}});',
					'{{WRAPPER}} .slick-slide > div > .product' => 'margin:0 calc({{SIZE}}{{UNIT}} / 2) {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .slick-slide' => 'margin:0 calc({{SIZE}}{{UNIT}} / 2);',
				],
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_control(
			'centermode',
			[
				'label' 	=> esc_html__( 'Center mode?', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'carousel!' => '',
					'carousel_type' => ''
				]
			]
		);

		$this->add_responsive_control(
			'centerpadding',
			[
				'label' => esc_html__( 'Center padding', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SLIDER,
				'default' => [
					'unit' => 'px',
					'size' => 10,
				],
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 1,
						'max' => 100,
						'step' => 1,
					],
				],
				'condition' => [
					'carousel!' 	=> '',
					'centermode!' 	=> '',
					'carousel_type' => ''
				]
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_arrows',
			[
				'label' => esc_html__( 'Arrows', 'codevz-plus' ),
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_control(
			'arrows_position',
			[
				'label' => esc_html__( 'Arrows position', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'arrows_mlr',
				'options' => [
					'no_arrows' => esc_html__( 'None', 'codevz-plus' ),
					'arrows_tl' => esc_html__( 'Both top left', 'codevz-plus' ),
					'arrows_tc' => esc_html__( 'Both top center', 'codevz-plus' ),
					'arrows_tr' => esc_html__( 'Both top right', 'codevz-plus' ),
					'arrows_tlr' => esc_html__( 'Top left / right', 'codevz-plus' ),
					'arrows_mlr' => esc_html__( 'Middle left / right', 'codevz-plus' ),
					'arrows_blr' => esc_html__( 'Bottom left / right', 'codevz-plus' ),
					'arrows_bl' => esc_html__( 'Both bottom left', 'codevz-plus' ),
					'arrows_bc' => esc_html__( 'Both bottom center', 'codevz-plus' ),
					'arrows_br' => esc_html__( 'Both bottom right', 'codevz-plus' ),
					
				],
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_control(
			'arrows_inner',
			[
				'label' 	=> esc_html__( 'Arrows inside carousel?', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_control(
			'arrows_show_on_hover',
			[
				'label' 	=> esc_html__( 'Show on hover?', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'carousel!' => '',
				],
			]
		);

		$this->add_control(
			'prev_icon',
			[
				'label' => esc_html__( 'Previous icon', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'default' => [
					'value' => 'fa fa-chevron-left',
					'library' => 'solid',
				],
			]
		);

		$this->add_control(
			'next_icon',
			[
				'label' => esc_html__( 'Next icon', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'default' => [
					'value' => 'fa fa-chevron-right',
					'library' => 'solid',
				],
				'condition' => [
					'carousel!' => '',
				]
			]
		);
		
		$this->end_controls_section();

		 //Dots
		 $this->start_controls_section(
			'section_dots',
			[
				'label' => esc_html__( 'Dots', 'codevz-plus' ),
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_control(
			'dots_position',
			[
				'label' => esc_html__( 'Dots position', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'no_dots',
				'options' => [
					'no_dots' => esc_html__( 'None', 'codevz-plus' ),
					'dots_tl' => esc_html__( 'Top left', 'codevz-plus' ),
					'dots_tc' => esc_html__( 'Top center', 'codevz-plus' ),
					'dots_tr' => esc_html__( 'Top right', 'codevz-plus' ),
					'dots_bl' => esc_html__( 'Bottom left', 'codevz-plus' ),
					'dots_bc' => esc_html__( 'Bottom center', 'codevz-plus' ),
					'dots_br' => esc_html__( 'Bottom right', 'codevz-plus' ),
					'dots_vtl' => esc_html__( 'Vertical top left', 'codevz-plus' ),
					'dots_vml' => esc_html__( 'Vertical middle left', 'codevz-plus' ),
					'dots_vbl' => esc_html__( 'Vertical bottom left', 'codevz-plus' ),
					'dots_vtr' => esc_html__( 'Vertical top right', 'codevz-plus' ),
					'dots_vmr' => esc_html__( 'Vertical middle rigth', 'codevz-plus' ),
					'dots_vbr' => esc_html__( 'Vertical bottom right', 'codevz-plus' ),
				],
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_control(
			'dots_style',
			[
				'label' => esc_html__( 'Predefined style', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					'' => esc_html__( '~ Default ~', 'codevz-plus' ),
					'dots_circle' => esc_html__( 'Circle', 'codevz-plus' ),
					'dots_circle dots_circle_2' => esc_html__( 'Circle 2', 'codevz-plus' ),
					'dots_circle_outline' => esc_html__( 'Circle outline', 'codevz-plus' ),
					'dots_square' => esc_html__( 'Square', 'codevz-plus' ),
					'dots_lozenge' => esc_html__( 'Lozenge', 'codevz-plus' ),
					'dots_tiny_line' => esc_html__( 'Tiny line', 'codevz-plus' ),
					'dots_drop' => esc_html__( 'Drop', 'codevz-plus' ),
				],
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_control(
			'dots_inner',
			[
				'label' => esc_html__( 'Dots inside carousel?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_control(
			'dots_show_on_hover',
			[
				'label' => esc_html__( 'Show on hover?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_advanced',
			[
				'label' => esc_html__( 'More carousel settings', 'codevz-plus' ),
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_control(
			'overflow_visible',
			[
				'label' => esc_html__( 'Overflow visible?', 'codevz-plus' ),
				'type' => Controls_Manager::SWITCHER,
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_control(
			'fade',
			[
				'label' => esc_html__( 'Fade mode?', 'codevz-plus' ),
				'type' => Controls_Manager::SWITCHER,
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_control(
			'mousewheel',
			[
				'label' => esc_html__( 'MouseWheel?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_control(
			'disable_links',
			[
				'label' => esc_html__( 'Disable slides links?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_control(
			'variablewidth',
			[
				'label' => esc_html__( 'Auto width detection?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_control(
			'vertical',
			[
				'label' => esc_html__( 'Vertical?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_control(
			'rows',
			[
				'label' => esc_html__( 'Number of rows', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 5,
				'step' => 1,
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_control(
			'even_odd',
			[
				'label' => esc_html__( 'Custom position', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'options' => [
					'' => esc_html__( 'Select', 'codevz-plus' ),
					'even_odd' => esc_html__( 'Even / Odd', 'codevz-plus' ),
					'odd_even' => esc_html__( 'Odd / Even', 'codevz-plus' ),
				],
				'condition' => [
					'carousel!' => '',
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
			'sk_con',
			[
				'label' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.woocommerce ul.products li.product' ),
			]
		);


		$this->add_responsive_control(
			'sk_title',
			[
				'label' 	=> esc_html__( 'Title', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.woocommerce ul.products li.product .woocommerce-loop-category__title, .woocommerce ul.products li.product .woocommerce-loop-product__title, .woocommerce ul.products li.product h3,.woocommerce.woo-template-2 ul.products li.product .woocommerce-loop-category__title, .woocommerce.woo-template-2 ul.products li.product .woocommerce-loop-product__title, .woocommerce.woo-template-2 ul.products li.product h3', '.woocommerce ul.products li.product:hover .woocommerce-loop-category__title, .woocommerce ul.products li.product:hover .woocommerce-loop-product__title, .woocommerce ul.products li.product:hover h3,.woocommerce.woo-template-2 ul.products li.product:hover .woocommerce-loop-category__title, .woocommerce.woo-template-2 ul.products li.product:hover .woocommerce-loop-product__title, .woocommerce.woo-template-2 ul.products li.product:hover h3' ),
			]
		);
		
		$this->add_responsive_control(
			'sk_image',
			[
				'label' 	=> esc_html__( 'Image', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.woocommerce ul.products li.product a img' ),
			]
		);

		$this->add_responsive_control(
			'sk_rate',
			[
				'label' 	=> esc_html__( 'Rating Stars', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.woocommerce ul.products li.product .star-rating' ),
			]
		);
	
		$this->add_responsive_control(
			'sk_price',
			[
				'label' 	=> esc_html__( 'Price', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.woocommerce ul.products li.product .price', '.woocommerce ul.products li.product:hover .price' ),
			]
		);

		$this->add_responsive_control(
			'sk_onsale',
			[
				'label' 	=> esc_html__( 'Sale Badge', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.woocommerce span.onsale, .woocommerce ul.products li.product .onsale,.woocommerce.single span.onsale, .woocommerce.single ul.products li.product .onsale' ),
			]
		);

		$this->add_responsive_control(
			'sk_sale_price',
			[
				'label' 	=> esc_html__( 'Sale Price', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.woocommerce ul.products li.product .price del span', '.woocommerce ul.products li.product:hover .price del span' ),
			]
		);

		$this->add_responsive_control(
			'sk_add_to_cart',
			[
				'label' 	=> esc_html__( 'Add to cart', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( 'ul.products li.product .button.add_to_cart_button, ul.products li.product .button[class*="product_type_"]', '.woocommerce ul.products li.product .button.add_to_cart_button:hover, .woocommerce ul.products li.product .button[class*="product_type_"]:hover' ),
			]
		);

		$this->add_responsive_control(
			'sk_view_cart',
			[
				'label' 	=> esc_html__( 'View cart', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.woocommerce a.added_to_cart' ),
			]
		);

		$this->add_responsive_control(
			'sk_icons',
			[
				'label' 	=> esc_html__( 'Icons', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.products .product .xtra-product-icons' ),
			]
		);

		$this->add_responsive_control(
			'sk_quick_view',
			[
				'label' 	=> esc_html__( 'Quick view', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.products .product .xtra-product-quick-view' ),
			]
		);
		
		$this->add_responsive_control(
			'sk_wishlist',
			[
				'label' 	=> esc_html__( 'Wishlist', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.products .product .xtra-add-to-wishlist' ),
			]
		);


		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_carousel',
			[
				'label' => esc_html__( 'Carousel', 'codevz-plus' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_responsive_control(
			'sk_slides',
			[
				'label' 	=> esc_html__( 'Slides', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'grayscale', 'blur', 'background', 'opacity', 'z-index', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( 'div.slick-slide' ),
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_responsive_control(
			'sk_center',
			[
				'label' 	=> esc_html__( 'Center slide', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'grayscale', 'background', 'opacity', 'z-index', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( 'div.slick-center' ),
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_responsive_control(
			'sk_prev_icon',
			[
				'label' 	=> esc_html__( 'Previous icon', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.slick-prev' ),
				'condition' => [
					'carousel!' => '',
				]
			]
		);


		$this->add_responsive_control(
			'sk_next_icon',
			[
				'label' 	=> esc_html__( 'Next icon', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.slick-next' ),
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_responsive_control(
			'sk_dots_container',
			[
				'label' 	=> esc_html__( 'Dots Container', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.slick-dots' ),
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->add_responsive_control(
			'sk_dots',
			[
				'label' 	=> esc_html__( 'Dots', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.slick-dots li button' ),
				'condition' => [
					'carousel!' => '',
				]
			]
		);

		$this->end_controls_section();

	}

	protected function render() {

		$settings = $this->get_settings_for_display();

		Xtra_Elementor::parallax( $settings );

		$hide_empty = $settings['hide_empty'] == 'yes' ? 1 : 0;

		$out = do_shortcode( '[product_category category="' . esc_attr( $settings[ 'product_category' ] ) . '" columns="' . esc_attr( $settings['columns'] ) . '" limit="' . esc_attr( $settings['limit'] ) . '" hide_empty="' . esc_attr( $hide_empty ) . '" parent="' . esc_attr( $settings['parent'] ) . '" orderby="' . esc_attr( $settings['orderby'] ) . '"]' );

		if ( ! empty( $settings[ 'carousel' ] ) ) {

			Xtra_Elementor::carousel_elementor( $settings, $out );

		} else {

			echo do_shortcode( $out );

		}

		Xtra_Elementor::parallax( $settings, true );

	}

}