<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Repeater;
use Elementor\Widget_Base;
use Elementor\Icons_Manager;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;

class Xtra_Elementor_Widget_working_hours extends Widget_Base {

	protected $id = 'cz_working_hours';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Working Hours', 'codevz-plus' );
	}
	
	public function get_icon() {
		return 'xtra-working-hours';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}
	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Hours', 'codevz-plus' ),
			esc_html__( 'Work', 'codevz-plus' ),
			esc_html__( 'Job', 'codevz-plus' ),
			esc_html__( 'Menu', 'codevz-plus' ),
			esc_html__( 'Business', 'codevz-plus' ),
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
				'label' => esc_html__( 'Settings', 'codevz-plus' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'left_text', [
				'label' => esc_html__( 'Left text', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT
			]
		);

		$repeater->add_control(
			'right_text', [
				'label' => esc_html__( 'Right text', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT
			]
		);

		$repeater->add_control(
			'sub', [
				'label' => esc_html__( 'Subtitle', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::TEXT
			]
		);

		$repeater->add_control(
			'badge', [
				'label' => esc_html__( 'Badge', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::TEXT
			]
		);

		$repeater->add_control(
			'icon_type',
			[
				'label' 	=> esc_html__( 'Icon Type', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' 	=> 'icon',
				'options' 	=> [
					'icon' 		=> esc_html__( 'Icon', 'codevz-plus' ),
					'image' 	=> esc_html__( 'Image', 'codevz-plus' ),
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

		$this->add_control(
			'items',
			[
				'label' => esc_html__( 'Items', 'codevz-plus' ),
				'type' => Controls_Manager::REPEATER,
				'prevent_empty' => false,
				'fields' => $repeater->get_controls(),
				'default' => [
					[
						'left_text' => esc_html__( 'All days', 'codevz-plus' ),
						'right_text' => esc_html__( '9:00 to 16:30', 'codevz-plus' )
					],
					[
						'left_text' => esc_html__( 'Sunday', 'codevz-plus' ),
						'right_text' => esc_html__( '9:00 to 12:30', 'codevz-plus' )
					],
				],
			]
		);

		$this->add_control(
			'between_texts',
			[
				'label' => esc_html__( 'Line between texts?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER
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
			'sk_con',
			[
				'label' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_wh' ),
			]
		);
		
		$this->add_responsive_control(
			'sk_line',
			[
				'label' 	=> esc_html__( 'Line', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_wh .cz_wh_line' ),
			]
		);

		$this->add_responsive_control(
			'sk_left',
			[
				'label' 	=> esc_html__( 'Left text', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'font-family', 'font-size', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_wh .cz_wh_left' ),
			]
		);

		$this->add_responsive_control(
			'sk_right',
			[
				'label' 	=> esc_html__( 'Right text', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'font-family', 'font-size', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_wh .cz_wh_right' ),
			]
		);

		$this->add_responsive_control(
			'sk_badge',
			[
				'label' 	=> esc_html__( 'Badge', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'font-family', 'font-size', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_wh small' ),
			]
		);

		$this->add_responsive_control(
			'sk_sub',
			[
				'label' 	=> esc_html__( 'Subtitle', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_wh .cz_wh_sub' ),
			]
		);

		$this->add_responsive_control(
			'sk_icon',
			[
				'label' 	=> esc_html__( 'Icon', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_wh i' ),
			]
		);
		$this->end_controls_section();

	}

	protected function render() {

		$settings = $this->get_settings_for_display();

		$classes = array();
		$classes[] = 'cz_wh';
		$classes[] = $settings['between_texts'] ? 'cz_wh_line_between' : '';

		$content = isset( $settings['content'] ) ? wp_kses_post( (string) $settings['content'] ) : '';

		// Out
		echo '<div' . wp_kses_post( (string) Codevz_Plus::classes( [], $classes ) ) . '>';

		// Content.
		echo $content ? '<p>' . do_shortcode( $content ) . '</p>' : '';

		// Description.
		$content = $content ? '<p class="xtra-working-hours-content">' . do_shortcode( $content ) . '</p>' : '';

		// Group.
		$items = $settings['items'];
		foreach( $items as $index => $i ) {

			if ( isset( $i['icon_type'] ) && $i['icon_type'] === 'image' && ! empty( $i['image'] ) ) {

				$icon = '<i class="mr8">' . Group_Control_Image_Size::get_attachment_image_html( $i ) . '</i>';

			} else {

				ob_start();
				Icons_Manager::render_icon( $i['icon'], [ 'class' => 'mr8' ] );
				$icon = ob_get_clean();

			}

			$badge 	= empty( $i['badge'] ) ? '' : '<small>' . $i['badge'] . '</small>';
			$sub 	= empty( $i['sub'] ) ? '' : '<span class="cz_wh_sub">' . $i['sub'] . '</span>';
			$left 	= empty( $i['left_text'] ) ? '' : '<span class="cz_wh_left">' . $icon . '<b>' . $i['left_text'] . '</b>' . $badge . $sub . '</span>';
			$right 	= empty( $i['right_text'] ) ? '' : '<span class="cz_wh_right">' . $i['right_text'] . '</span>';

			echo '<div class="mb10 last0 clr"><div class="clr">' . do_shortcode( $left . $right ) . '</div><div class="cz_wh_line"></div></div>';
		}
		echo '</div>';
	}

	protected function content_template() {
		?>

		<#

			var iconHTML = elementor.helpers.renderIcon( view, settings.icon, { 'aria-hidden': true, 'class': 'mr8' }, 'i' , 'object' );

				classes = 'cz_wh', 
				classes = settings.between_texts ? classes + ' cz_wh_line_between' : classes,

				html = '';

			_.each( settings.items, function( i, index ) {

				if ( i.icon_type === 'image' && i.image.url ) {

					var image = {
						id: i.image.id,
						url: i.image.url,
						size: i.image_size,
						dimension: i.image_custom_dimension,
						model: view.getEditModel()
					};

					var image_url = elementor.imagesManager.getImageUrl( image );

					if ( ! image_url ) {
						return;
					}

					var icon = '<i class="mr8"><img src="' + image_url + '"></i>';
				} else {
					var icon = !i.icon.value ? ( iconHTML.value || '' ) : '<i class="' + i.icon.value + ' mr8"></i>';
				}

				var badge = !i.badge  ? '' : '<small>' + i.badge + '</small>',
					sub = !i.sub  ? '' : '<span class="cz_wh_sub">' + i.sub + '</span>',
					left = !i.left_text  ? '' : '<span class="cz_wh_left">' + icon + '<b>' + i.left_text + '</b>' + badge + sub + '</span>',
					right = !i.right_text ? '' : '<span class="cz_wh_right">' + i.right_text + '</span>';

				html = html + '<div class="mb10 last0 clr"><div class="clr">' + left + right + '</div><div class="cz_wh_line"></div></div>';

			});
		#>

		<div class="{{{classes}}}">
			{{{html}}}
		</div>

		<?php

	}

}