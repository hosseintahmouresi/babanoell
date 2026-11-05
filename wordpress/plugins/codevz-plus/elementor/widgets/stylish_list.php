<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Utils;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Elementor\Icons_Manager;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;

class Xtra_Elementor_Widget_stylish_list extends Widget_Base {

	protected $id = 'cz_stylish_list';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Stylish List', 'codevz-plus' );
	}
	
	public function get_icon() {
		return 'xtra-stylish-list';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Stylish List', 'codevz-plus' ),
			esc_html__( 'Style', 'codevz-plus' ),
			esc_html__( 'List', 'codevz-plus' ),

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
			'settings',
			[
				'label' 	=> esc_html__( 'Settings', 'codevz-plus' ),
				'tab' 		=> Controls_Manager::TAB_CONTENT,
			]
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'title',
			[
				'label' 	=> esc_html__('Title', 'codevz-plus' ),
				'type' 		=> Controls_Manager::TEXT,
				'default' 	=> esc_html__('This is list item', 'codevz-plus' ),
				'placeholder' => esc_html__('This is list item', 'codevz-plus' ),
			]
		);

		$repeater->add_control(
			'subtitle',
			[
				'label' 	=> esc_html__('Subtitle', 'codevz-plus' ),
				'type' 		=> Controls_Manager::TEXT
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
			'icon_color',
			[
				'label' => esc_html__( 'Icon color', 'codevz-plus' ),
				'type' => Controls_Manager::COLOR,
				'condition' => [
					'icon_type' => 'icon',
				],
				'selectors' => [
					'{{WRAPPER}} {{CURRENT_ITEM}} .cz_sl_icon i' => 'color: {{VALUE}};',
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

		$repeater->add_control(
			'link',
			[
				'label' => esc_html__( 'Link', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::URL,
				'show_external' => true
			]
		);

		$this->add_control(
			'items',
			[
				'label' => esc_html__( 'Lists', 'codevz-plus' ),
				'type' => Controls_Manager::REPEATER,
				'prevent_empty' => false,
				'fields' => $repeater->get_controls(),
				'default' => [
					[
						'title' => esc_html__( 'This is list item', 'codevz-plus' )
					],
					[
						'title' => esc_html__( 'This is list item', 'codevz-plus' )
					],
					[
						'title' => esc_html__( 'This is list item', 'codevz-plus' )
					],
					[
						'title' => esc_html__( 'This is list item', 'codevz-plus' )
					],
				],
			]
		);

		$this->add_control(
			'icon_type',
			[
				'label' => esc_html__( 'Default icon', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'icon',
				'options' => [
					'icon' => esc_html__( 'Icon', 'codevz-plus' ),
					'image' => esc_html__( 'Image', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'default_icon',
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

		$this->add_control(
			'image',
			[
				'label' => esc_html__( 'Image', 'codevz-plus' ),
				'type' => Controls_Manager::MEDIA,
				'condition' => [
					'icon_type' => 'image',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			[
				'name' => 'image',
				'default' => 'full',
				'separator' => 'none'
			]
		);

		$this->add_control(
			'icon_hover_fx',
			[
				'label' => esc_html__( 'Icons hover', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => 'cz_sl_icon_hover_none',
				'options' => [
					'cz_sl_icon_hover_none' => esc_html__( 'None', 'codevz-plus' ),
					'cz_sl_icon_hover_zoom_in' => esc_html__( 'Zoom In', 'codevz-plus' ),
					'cz_sl_icon_hover_zoom_out' => esc_html__( 'Zoom Out', 'codevz-plus' ),
					'cz_sl_icon_hover_blur' => esc_html__( 'Blur', 'codevz-plus' ),
					'cz_sl_icon_hover_flash' => esc_html__( 'Flash', 'codevz-plus' ),
					'cz_sl_icon_hover_absorber' => esc_html__( 'Absorber', 'codevz-plus' ),
					'cz_sl_icon_hover_wobble' => esc_html__( 'Wobble', 'codevz-plus' ),
					'cz_sl_icon_hover_zoom_in_fade' => esc_html__( 'Zoom in fade', 'codevz-plus' ),
					'cz_sl_icon_hover_zoom_out_fade' => esc_html__( 'Zoom out fade', 'codevz-plus' ),
					'cz_sl_icon_hover_zoom_out_push' => esc_html__( 'Push in', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'text_center' ,
			[
				'label'        	=> esc_html__( 'Center on mobile?', 'codevz-plus' ),
				'type' 			=> $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'default' 		=> '',
				'label_on' 		=> esc_html__( 'Yes', 'codevz-plus' ),
				'label_off'		=> esc_html__( 'No', 'codevz-plus' ),
				'return_value' 	=> 'center_on_mobile',
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
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_stylish_list' ),
			]
		);

		$this->add_responsive_control(
			'sk_lists',
			[
				'label' 	=> esc_html__( 'List items', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'width', 'float', 'display', 'font-size' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_stylish_list li', '.cz_stylish_list li:hover' ),
			]
		);

		$this->add_responsive_control(
			'sk_subtitle',
			[
				'label' 	=> esc_html__( 'Subtitle', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_stylish_list small' ),
			]
		);

		$this->add_responsive_control(
			'sk_icons',
			[
				'label' 	=> esc_html__( 'Icons', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_stylish_list i', '.cz_stylish_list li:hover i' ),
			]
		);

		$this->end_controls_section();

	}

	public function render() {

		$settings = $this->get_settings_for_display();

		// Classes
		$classes = array();
		$classes[] = 'cz_stylish_list clr';
		$classes[] = $settings['text_center'];
		$classes[] = $settings['icon_hover_fx'];

		// Icon
		$default_icon = '';
		if ( $settings['icon_type'] === 'image' && $settings['image'] ) {

			$image = Group_Control_Image_Size::get_attachment_image_html( $settings );
			$default_icon = '<div class="cz_sl_icon cz_sl_image"><i class="mr10">' . $image . '</i></div>';

		} else if ( $settings['default_icon'] ) {

			ob_start();
			Icons_Manager::render_icon( $settings['default_icon'], [ 'class' => 'mr10' ] );
			$icon = ob_get_clean();
			$default_icon = $icon ? '<div class="cz_sl_icon">' . $icon . '</div>' : '';

		}

		// Description.
		$content = '';
		$content = $content ? '<p class="xtra-stylish-list-content">' . do_shortcode( $content ) . '</p>' : '';

		Xtra_Elementor::parallax( $settings );

		// Out
		$out = $content . '<ul' . Codevz_Plus::classes( [], $classes ) .'>';

		$items = (array) $settings['items'];

		foreach( $items as $index => $i ) {

			if ( isset( $i['icon_type'] ) && $i['icon_type'] === 'image' && ! empty( $i['image'] ) ) {

				$ico = '<div class="cz_sl_icon cz_sl_image"><i class="mr10">' . Group_Control_Image_Size::get_attachment_image_html( $i ) . '</i></div>';

			} else if ( isset( $i['icon_type'] ) && $i['icon_type'] === 'number' && ! empty( $i['number'] ) ) {

				$ico = '<div class="cz_sl_icon"><i class="xtra-sl-number mr10">' . $i['number'] . '</i></div>';

			} else if ( ! empty( $i['icon']['value'] ) ) {

				ob_start();
				Icons_Manager::render_icon( $i['icon'], [ 'class' => 'mr10' ] );
				$icon = ob_get_clean();
				$ico = '<div class="cz_sl_icon">' . $icon . '</div>';

			} else {

				$ico = $default_icon;

			}

			$sub = empty( $i['subtitle'] ) ? '' : '<small>' . $i['subtitle'] . '</small>';
			if ( $i['link'] ) {
				$this->add_link_attributes( 'link' . $index, $i['link'] );
			}
			$link = empty( $this->get_render_attribute_string( 'link' . $index ) ) ? '' : '<a ' . $this->get_render_attribute_string( 'link' . $index ) . '>';
			$link = Codevz_Plus::contains( $link, 'href' ) ? $link : '';
			$out .= empty( $i['title'] ) ? '' : '<li class="elementor-repeater-item-' . esc_attr( $i[ '_id' ] ) . ' clr">' . $link . $ico . '<div><span>' . $i['title'] . $sub . '</span></div>' . ( $link ? '</a>' : '' ) . '</li>';

			$index++;
		}
		$out .= '</ul>';

		echo do_shortcode( $out );

		Xtra_Elementor::parallax( $settings, true );

	}

	public function content_template() {
		?>
		<#

		if ( settings.image.url ) {
			var image = {
				id: settings.image.id,
				url: settings.image.url,
				size: settings.image_size,
				dimension: settings.image_custom_dimension,
				model: view.getEditModel()
			};

			var image_url = elementor.imagesManager.getImageUrl( image );

			if ( ! image_url ) {
				return;
			}
		}

		var iconHTML = elementor.helpers.renderIcon( view, settings.default_icon, { 'class': 'mr10' }, 'i' , 'object' ),

			classes = 'cz_stylish_list clr', 
			classes = settings.text_center ? classes + ' ' + settings.text_center : classes;
			classes = settings.icon_hover_fx ? classes + ' ' + settings.icon_hover_fx : classes,

			default_icon = '';

		if ( settings.icon_type === 'image' && settings.image.url ) {
			var image_url = '<img src="' + image_url + '">';
			default_icon = '<div class="cz_sl_icon cz_sl_image"><i class="mr10">' + image_url + '</i></div>';
		} else if ( settings.default_icon && iconHTML.value ) {
			default_icon = '<div class="cz_sl_icon">' + iconHTML.value + '</div>';
		} else {
			var image_url = '';
		}

		var items = '';
			parallaxOpen = xtraElementorParallax( settings ),
			parallaxClose = xtraElementorParallax( settings, true );

		_.each( settings.items, function( i ) {

			if ( i.icon_type === 'image' && i.image.url ) {

				var i_image = {
					id: i.image.id,
					url: i.image.url,
					size: i.image_size,
					dimension: i.image_custom_dimension,
					model: view.getEditModel()
				};

				var i_image_url = elementor.imagesManager.getImageUrl( i_image ),
					image_url = i_image_url ? '<img src="' + i_image_url + '">' : image_url;

				ico = '<div class="cz_sl_icon cz_sl_image"><i class="mr10">' + image_url + '</i></div>';

			} else if ( i.icon_type === 'number' && i.number ) {
				ico = '<div class="cz_sl_icon"><i class="xtra-sl-number mr10">' + i.number + '</i></div>';
			} else if ( i.icon.value ) {
				var iconsHTML = elementor.helpers.renderIcon( view, i.icon, { 'class': 'mr10' }, 'i' , 'object' );
				ico = iconsHTML.value ? '<div class="cz_sl_icon">' + iconsHTML.value + '</div>' : '';
			} else {
				ico = default_icon;
			}

			var sub = i.subtitle ? '<small>' + i.subtitle + '</small>' : '',
				link = i.link.url ? '<a href="' + i.link.url + '">' : '';
				
			items = items + '<li class="elementor-repeater-item-' + i._id + ' clr">' + link + ico + '<div><span>' + i.title + sub + '</span></div>' + ( link ? '</a>' : '' ) + '</li>';

		});

		#>

		{{{ parallaxOpen }}}

		<ul class="{{{classes}}}">{{{ items }}}</ul>

		{{{ parallaxClose }}}

		<?php
		
	}
}
