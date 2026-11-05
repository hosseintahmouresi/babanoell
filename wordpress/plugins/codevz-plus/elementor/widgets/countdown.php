<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Xtra_Elementor_Widget_countdown extends Widget_Base {

	protected $id = 'cz_countdown';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Countdown', 'codevz-plus' );
	}
	
	public function get_icon() {
		return 'xtra-countdown';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Count', 'codevz-plus' ),
			esc_html__( 'Down', 'codevz-plus' ),
			esc_html__( 'Loop', 'codevz-plus' ),
			esc_html__( 'Date', 'codevz-plus' ),
			esc_html__( 'Time', 'codevz-plus' ),

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
				'label' => 'Countdown Settings',
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'type',
			[
				'label' 	=> esc_html__( 'Type', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SELECT,
				'default' 	=> 'down',
				'options' 	=> [
					'down' 		=> esc_html__( 'Count down', 'codevz-plus' ),
					'up' 		=> esc_html__( 'Count up', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'loop' 		=> esc_html__( 'Loop count down', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
				],
			]
		);

		$date = gmdate( 'Y/m/j H:i', strtotime("1 year") );

		$this->add_control(
			'date',
			[
				'label'     => esc_html__( 'Date', 'codevz-plus' ),
				'type'      => Controls_Manager::DATE_TIME,
				'picker_options' => [
					'dateFormat'    => 'Y/m/j H:i'
				],
				'default'   => $date,
				'placeholder' => $date,
				'condition' => [
					'type'      => [ 'down', 'up' ],
				],
			]
		);

		$this->add_control(
			'loop',
			[
				'label' => esc_html__( 'Minutes', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'default' => '120',
				'placeholder' => '120',
				'condition' => [
					'type' => 'loop',
				],
			]
		);

		$this->add_control(
			'pos',
			[
				'label' => esc_html__( 'Position', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'tac',
				'options' => [
					'tac' => esc_html__( 'Center', 'codevz-plus' ),
					'tal' => esc_html__( 'Left', 'codevz-plus' ),
					'tar' => esc_html__( 'Right', 'codevz-plus' ),
					'tac cz_countdown_center_v' => esc_html__( 'Center vertical', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'tal cz_countdown_left_v' => esc_html__( 'Left vertical', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'tal cz_countdown_right_v' => esc_html__( 'Right vertical', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'tac cz_countdown_inline' => esc_html__( 'Inline view', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
				],
			]
		);

		$this->add_control(
			'center_on_mobile' ,
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

		$this->start_controls_section(
			'translation',
			[
				'label' => esc_html__( 'Translation', 'codevz-plus' )
			]
		);

		$this->add_control(
			'year',
			[
				'label' => esc_html__( 'Year', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Year', 'codevz-plus' ),
				'placeholder' => esc_html__( 'Year', 'codevz-plus' )
			]
		);

		$this->add_control(
			'day',
			[
				'label' => esc_html__( 'Day', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Day', 'codevz-plus' ),
				'placeholder' => esc_html__( 'Day', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'hour',
			[
				'label' => esc_html__( 'Hour', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Hour', 'codevz-plus' ),
				'placeholder' => esc_html__( 'Hour', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'minute',
			[
				'label' => esc_html__( 'Minute', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Minute', 'codevz-plus' ),
				'placeholder' => esc_html__( 'Minute', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'second',
			[
				'label' => esc_html__( 'Second', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Second', 'codevz-plus' ),
				'placeholder' => esc_html__( 'Second', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'plus',
			[
				'label' => esc_html__( 'Apostrophe s', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 's', 'codevz-plus' ),
				'placeholder' => esc_html__( 's', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'expire',
			[
				'label' => esc_html__( 'Expire message', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'This event has been expired', 'codevz-plus' ),
				'placeholder' => esc_html__( 'This event has been expired', 'codevz-plus' ),
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
				'settings' 	=> [ 'background', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_countdown', '.cz_countdown:before' ),
			]
		);

		$this->add_responsive_control(
			'sk_cols',
			[
				'label' 	=> esc_html__( 'Columns', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'width', 'text-align', 'background', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_countdown li' ),
			]
		);

		$this->add_responsive_control(
			'sk_nums',
			[
				'label' 	=> esc_html__( 'Numbers', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'font-family', 'font-size', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_countdown span' ),
			]
		);

		$this->add_responsive_control(
			'sk_title',
			[
				'label' 	=> esc_html__( 'Titles', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'font-family', 'font-size', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_countdown p' ),
			]
		);

		$this->add_responsive_control(
			'sk_expired',
			[
				'label' 	=> esc_html__( 'Expired message', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'width', 'color', 'font-family', 'font-size', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_countdown expired' ),
			]
		);

		$this->end_controls_section();
	}

	public function render() {

		// Settings.
		$settings = $this->get_settings_for_display();

		$data = array(
			'type'	=> $settings['type'],
			'date'	=> ( $settings['type'] === 'loop' ) ? $settings['loop'] * 60 : strtotime( $settings['date'] ) - strtotime( current_time( 'Y/m/j H:i' ) ),
			'elapse'=> ( $settings['type'] === 'up' ) ? true : false,
			'y'		=> ( $settings['type'] === 'up' ) ? $settings['year'] : '',
			'd'		=> $settings['day'],
			'h'		=> $settings['hour'],
			'm'		=> $settings['minute'],
			's'		=> $settings['second'],
			'p'		=> ( $settings['plus'] && ! is_rtl() ) ? $settings['plus'] : '&nbsp;',
			'ex'	=> $settings['expire'] ? $settings['expire'] : '&nbsp;',
		);

		// Classes
		$classes = array();
		$classes[] = 'cz_countdown clr';
		$classes[] = $settings['pos'];
		$classes[] = empty( $settings['center_on_mobile'] ) ? '' : 'center_on_mobile';

		// Inner HTML.
		$html = '';
		$html .= ( $settings['type'] === 'up' && $settings['year'] ) ? '<li><span>00</span><p>' . esc_html( $settings['year'] ) . '</p></li>' : '';
		$html .= $settings['day'] ? '<li><span>00</span><p>' . esc_html( $settings['day'] ) . '</p></li>' : '';
		$html .= $settings['hour'] ? '<li><span>00</span><p>' . esc_html( $settings['hour'] ) . '</p></li>' : '';
		$html .= $settings['minute'] ? '<li><span>00</span><p>' . esc_html( $settings['minute'] ) . '</p></li>' : '';
		$html .= $settings['second'] ? '<li><span>00</span><p>' . esc_html( $settings['second'] ) . '</p></li>' : '';

		Xtra_Elementor::parallax( $settings );

		?>
		<ul data-countdown='<?php echo wp_json_encode( $data, JSON_HEX_APOS ); ?>'<?php echo wp_kses_post( (string) Codevz_Plus::classes( [], $classes ) ); ?>><?php echo wp_kses_post( (string) $html ); ?></ul>
		<?php

		Xtra_Elementor::parallax( $settings, true );

		// Fix live preivew.
		Xtra_Elementor::render_js( 'countdown' );
	}

	public function content_template() {
		?>
		<#
		var classes = 'cz_countdown clr', 
			classes = settings.pos ? classes + ' ' + settings.pos : classes,
			classes = settings.center_on_mobile ? classes + ' center_on_mobile' : classes,

			html = '',
			html = html + ( ( settings.type === 'up' && settings.year ) ? '<li><span>00</span><p>' + settings.year + '</p></li>' : '' ),
			html = html + ( settings.day ? '<li><span>00</span><p>' + settings.day + '</p></li>' : '' ),
			html = html + ( settings.hour ? '<li><span>00</span><p>' + settings.hour + '</p></li>' : '' ),
			html = html + ( settings.minute ? '<li><span>00</span><p>' + settings.minute + '</p></li>' : '' ),
			html = html + ( settings.second ? '<li><span>00</span><p>' + settings.second + '</p></li>' : '' ),

			currentdate = new Date(),
			dd = currentdate.getDate(),
			mm = currentdate.getMonth()+1, 
			yy = currentdate.getFullYear(), 
			hh = currentdate.getHours(), 
			mi = currentdate.getMinutes(),

			data = {
				'type'	: settings.type,
				'date'	: ( settings.type === 'loop' ) ? settings.loop * 60 : ( Date.parse( settings.date ) - Date.parse( yy + '/' + mm + '/' + dd + ' ' + hh + ':' + mi ) ) / 1000,
				'elapse': ( settings.type === 'up' ) ? true : false,
				'y'		: ( settings.type === 'up' ) ? settings.year : '',
				'd'		: settings.day,
				'h'		: settings.hour,
				'm'		: settings.minute,
				's'		: settings.second,
				'p'		: ( settings.plus && ! document.body.classList.contains( 'rtl' ) ) ? settings.plus : '&nbsp;',
				'ex'	: settings.expire ? settings.expire : '&nbsp;',
			},
			data = JSON.stringify( data ),

			parallaxOpen = xtraElementorParallax( settings ),
			parallaxClose = xtraElementorParallax( settings, true );

		#>

		{{{ parallaxOpen }}}

		<ul data-countdown='{{{data}}}' class="{{{classes}}}">{{{html}}}</ul>

		{{{ parallaxClose }}}
		<?php
	}
}