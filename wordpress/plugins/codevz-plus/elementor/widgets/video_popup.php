<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Utils;
use Elementor\Widget_Base;
use Elementor\Icons_Manager;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;

class Xtra_Elementor_Widget_video_popup extends Widget_Base {
	
	protected $id = 'cz_video_popup';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Video Player', 'codevz-plus' );
	}
	
	public function get_icon() {
		return 'xtra-video-popup';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Video', 'codevz-plus' ),
			esc_html__( 'Player', 'codevz-plus' ),
			esc_html__( 'Play', 'codevz-plus' ),
			esc_html__( 'Embed', 'codevz-plus' ),
			esc_html__( 'Youtube', 'codevz-plus' ),
			esc_html__( 'Vimeo', 'codevz-plus' ),
			esc_html__( 'Self hosted', 'codevz-plus' ),
			esc_html__( 'MP4', 'codevz-plus' ),

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

		$this->add_control(
			'type',
			[
				'label' => esc_html__( 'Video Type', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'tac',
				'options' => [
					'1' => esc_html__( 'Youtube or Vimeo', 'codevz-plus' ),
					'2' => esc_html__( 'Self-hosted MP4', 'codevz-plus' ),
					'3' => esc_html__( 'Custom code', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'video',
			[
				'label' 	=> esc_html__('Video URL', 'codevz-plus' ),
				'type' 		=> Controls_Manager::TEXT,
				'default' 	=> 'https://www.youtube.com/watch?v=fY85ck-pI5c',
				'placeholder' => esc_html__( 'Enter your URL', 'codevz-plus' ),
				'condition' => [
					'type' => '1',
				],
			]
		);

		$this->add_control(
			'mp4',
			[
				'label' => esc_html__( 'MP4', 'codevz-plus' ),
				'type' => Controls_Manager::MEDIA,
				'media_type' => 'video',
				'condition' => [
					'type' => '2'
				],
			]
		);

		$this->add_control(
			'content',
			[
				'label' 	=> esc_html__('Custom code', 'codevz-plus' ),
				'type' 		=> Controls_Manager::TEXTAREA,
				'condition' => [
					'type' => '3',
				],
			]
		);

		$this->add_control(
			'image',
			[
				'label' => esc_html__( 'Image placeholder', 'codevz-plus' ),
				'type' => Controls_Manager::MEDIA,
			]
		);

		$this->add_control(
			'inline',
			[
				'label' => esc_html__( 'Inline video?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'type' => '2'
				]
			]
		);

		$this->add_control(
			'title',
			[
				'label' => esc_html__('Title', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'Enter your Title', 'codevz-plus' )
			]
		);

		$this->add_control(
			'subtitle',
			[
				'label' => esc_html__('Sub title', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'Enter your subtitle', 'codevz-plus' )
			]
		);

		$this->add_control (
			'icon',
			[
				'label' => esc_html__( 'Icon', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'skin' => 'inline',
				'label_block' => false,
				'default' => [
					'value' 	=> 'fas fa-play',
					'library' 	=> 'solid'
				]
			]
		);

		$this->add_control(
			'position',
			[
				'label' => esc_html__( 'Icon Position', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'cz_video_popup_c',
				'options' => [
					'cz_video_popup_c' => esc_html__( 'Center', 'codevz-plus' ),
					'cz_video_popup_tl' => esc_html__( 'Top Left', 'codevz-plus' ),
					'cz_video_popup_tr' => esc_html__( 'Top Right', 'codevz-plus' ),
					'cz_video_popup_bl' => esc_html__( 'Bottom Left', 'codevz-plus' ),
					'cz_video_popup_br' => esc_html__( 'Bottom Right', 'codevz-plus' ),
				]
			]
		);

		$this->end_controls_section();

		// Inverted radius controls.
		Xtra_Elementor::inverted_radius_controls( $this );

		// Tilt controls.
		Xtra_Elementor::tilt_controls( $this );

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
				'settings' 	=> [ 'background', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_video_popup .cz_vp_c' ),
			]
		);

		$this->add_responsive_control(
			'sk_icon',
			[
				'label' 	=> esc_html__( 'Icon', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_video_popup span', '.cz_video_popup:hover span' ),
			]
		);

		$this->add_responsive_control(
			'sk_title',
			[
				'label' 	=> esc_html__( 'Title', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'font-family', 'font-size', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_video_popup .cz_vp_inner' ),
			]
		);

		$this->add_responsive_control(
			'sk_subtitle',
			[
				'label' 	=> esc_html__( 'Subtitle', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'font-family', 'font-size', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_video_popup .cz_vp_inner small' ),
			]
		);

		$this->add_responsive_control(
			'svg_bg',
			[
				'label' 	=> esc_html__( 'Background layer', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'background', 'top', 'left', 'border', 'width', 'height' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_svg_bg:before' ),
			]
		);

		$this->end_controls_section();

	}

	public function render() {

		$settings = $this->get_settings_for_display();

		ob_start();
		Icons_Manager::render_icon( $settings['icon'] );
		$icon = ob_get_clean();
		
		// Title.
		$settings['title'] = $settings['title'] ? '<h4>' . $settings['title'] . ( $settings['subtitle'] ? '<small>' . $settings['subtitle'] . '</small>' : '' ) . '</h4>' : '';

		// Classes.
		$classes = array();
		$classes[] = 'cz_video_popup clr';
		$classes[] = $settings['position'];
		$classes[] = empty( $settings['svg_bg'] ) ? '' : 'cz_svg_bg';
		$classes[] = $settings['inline'] ? 'cz_video_inline xtra-disable-lightbox' : '';
		$classes[] = empty( $settings['ir_position'] ) ? '' : 'codevz-plus-inverted-radius-' . esc_attr( $settings['ir_position'] );

		// MP4.
		$data_html = $content_html = $code = '';

		if ( $settings['type'] !== '1' ) {

			if ( $settings['type'] === '3' ) {

				$code = $settings[ 'content' ];

			} else if ( ! empty( $settings['mp4'][ 'url' ] ) ) {

				$code = '<video class="lg-video-object lg-html5" controls="" preload="metadata"><source src="' . esc_attr( $settings['mp4'][ 'url' ] ) . '" type="video/mp4">Your browser does not support HTML5 video.</video>';

			}

			$settings['video'] = '';
			$id = wp_rand( 1111, 9999 );
			$data_html = ' data-html="#' . $id . '_mp4"';
			$content_html = '<div style="display:none;" id="' . $id . '_mp4">' . do_shortcode( $code ) . '</div>';

		} else if ( ! $settings['video'] ) {

			$settings['video'] = 'https://www.youtube.com/watch?v=fY85ck-pI5c';

		}

		// Image
		if ( empty( $settings['image'][ 'url' ] ) && Codevz_Plus::contains( $settings['video'], 'youtube' ) ) {
			$yt = wp_parse_url( $settings['video'] );
			parse_str( $yt['query'], $yt );
			$image = '<img loading="lazy" src="https://img.youtube.com/vi/' . $yt['v'] . '/sddefault.jpg" />';
		} else {
			$settings[ 'image_size' ] = 'full';
			$image = Group_Control_Image_Size::get_attachment_image_html( $settings );
		}

		// Parallax.
		Xtra_Elementor::parallax( $settings );

		?>
		<div<?php echo wp_kses_post( (string) Codevz_Plus::classes( [], $classes ) );  ?>>
			<div<?php echo wp_kses_post( (string) Codevz_Plus::tilt( $settings ) ); ?>>
				<div class="cz_vp_c"><a href="<?php echo esc_url( $settings['video'] ); ?>" <?php echo esc_attr( $settings['inline'] ) ? 'class="xtra-disable-lightbox"' : ''; ?> <?php echo wp_kses_post( (string) $data_html ); ?>><?php echo wp_kses_post( (string) $image ); ?><div class="cz_vp_inner"><span><?php echo do_shortcode( $icon ); ?></span><?php echo wp_kses_post( (string) $settings['title'] ); ?></div></a></div>
			</div>
		</div><?php echo do_shortcode( $content_html ); ?>
		<?php
		
		
		// Close parallax.
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

		var iconHTML = elementor.helpers.renderIcon( view, settings.icon, { 'aria-hidden': true }, 'i' , 'object' ),
			icon = iconHTML.value || '<i class="fas fa-play"></i>';

		// Title.
		settings.title = settings.title ? '<h4>' + settings.title + ( settings.subtitle ? '<small>' + settings.subtitle + '</small>' : '' ) + '</h4>' : '';

		// Classes
		var classes = 'cz_video_popup clr', 
			classes = settings.position ? classes + ' ' + settings.position : classes,
			classes = settings.svg_bg ? classes + ' cz_svg_bg' : classes,
			classes = settings.inline ? classes + ' cz_video_inline xtra-disable-lightbox' : classes,
			classes = classes + ( settings.ir_position ? ' codevz-plus-inverted-radius-' + settings.ir_position : '' );

		// MP4.
		var data_html = content_html = '';
		if ( settings.type === '2' && settings.mp4 ) {
			settings.video = '';
			data_html = ' data-html="#x_mp4"';
			content_html = '<div style="display:none;" id="x_mp4"><video class="lg-video-object lg-html5" controls="" preload="metadata"><source src="' + settings.mp4 + '" type="video/mp4">Your browser does not support HTML5 video.</video></div>';
		} else if ( ! settings.video ) {
			settings.video = 'https://www.youtube.com/watch?v=fY85ck-pI5c';
		}

		// Image
		if ( ! image_url && settings.video.indexOf( 'youtube' ) >= 0  ) {
			var yt = settings.video;
			var video_id = yt.split('v=')[1];
			var ampersandPosition = video_id ? video_id.indexOf('&') : '';
			if(ampersandPosition != -1) {
				video_id = video_id ? video_id.substring(0, ampersandPosition) : '';
			}

			var image = '<img src="' + 'https://img.youtube.com/vi/' + video_id + '/sddefault.jpg' + '" />';
		} else {
			var image = '<img src="' + image_url + '" />';
		}

		var content = '',

			parallaxOpen = xtraElementorParallax( settings ),
			parallaxClose = xtraElementorParallax( settings, true ),

			tilt = xtraElementorTilt( settings ),
			svg_bg = settings.svg_bg ? ' class="cz_svg_bg"' : '',

			aclass = settings.inline ? 'class="xtra-disable-lightbox"' : '';

		#>
		{{{ parallaxOpen }}}

		<div class="{{{classes}}}">
			<div {{{tilt}}}>
				<div class="cz_vp_c"><a href="{{{settings.video}}}" {{{aclass}}} {{{data_html}}}>{{{image}}}<div class="cz_vp_inner"><span>{{{icon}}}</span>{{{settings.title}}}</div></a></div>
			</div>
		</div>{{{content.html}}}

		{{{ parallaxClose }}}
		<?php
	}

}