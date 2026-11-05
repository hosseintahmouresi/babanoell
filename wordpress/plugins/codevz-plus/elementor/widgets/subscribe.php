<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Widget_Base;
use Elementor\Icons_Manager;
use Elementor\Controls_Manager;

class Xtra_Elementor_Widget_subscribe extends Widget_Base { 

	protected $id = 'cz_subscribe';

	public function __construct( $data = [], $args = null ) {

		// Run parent.
		parent::__construct( $data, $args );

		// Handle form AJAX request.
		add_action( 'wp_ajax_cz_subscribe_ajax', [ 'Codevz_Plus', 'subscribe_ajax' ] );
		add_action( 'wp_ajax_nopriv_cz_subscribe_ajax', [ 'Codevz_Plus', 'subscribe_ajax' ] );

		// Handle secret URL confirmation.
		add_action( 'init', [ 'Codevz_Plus', 'subscribe_confirm' ] );

	}

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Subscribe', 'codevz-plus' );
	}

	public function get_icon() {
		return 'xtra-subscribe';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Subscribe', 'codevz-plus' ),
			esc_html__( 'Newsletter', 'codevz-plus' ),
			esc_html__( 'Mailchimp', 'codevz-plus' ),
			esc_html__( 'Feed', 'codevz-plus' ),

		];

	}
	
	public function get_style_depends() {
		return [ $this->id, 'cz_parallax' ];
	}

	public function get_script_depends() {
		return [ $this->id, 'subscribe', 'cz_parallax' ];
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

		$this->add_control(
			'style',
			[
				'label' => esc_html__( 'Style', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					'cz_subscribe_style_default' 									=> esc_html__( '~ Default ~', 'codevz-plus' ),
					'cz_subscribe_square cz_subscribe_style_1' 						=> esc_html__( 'Style', 'codevz-plus' ) . ' 1',
					'cz_subscribe_round cz_subscribe_style_2' 						=> esc_html__( 'Style', 'codevz-plus' ) . ' 2',
					'cz_subscribe_round_2 cz_subscribe_style_3' 					=> esc_html__( 'Style', 'codevz-plus' ) . ' 3',
					'cz_subscribe_relative cz_subscribe_square cz_subscribe_style_4' => esc_html__( 'Style', 'codevz-plus' ) . ' 4',
					'cz_subscribe_relative cz_subscribe_round cz_subscribe_style_5' => esc_html__( 'Style', 'codevz-plus' ) . ' 5',
					'cz_subscribe_col cz_subscribe_square cz_subscribe_style_6' 	=> esc_html__( 'Style', 'codevz-plus' ) . ' 6',
					'cz_subscribe_col cz_subscribe_style_7' 						=> esc_html__( 'Style', 'codevz-plus' ) . ' 7',
				],
			]
		);

		$this->add_control(
			'name',
			[
				'label' => esc_html__( 'Name input', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
			]
		);
		$this->add_control(
			'placeholder',
			[
				'label' => esc_html__( 'Email input', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Your email ...', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'position',
			[
				'label' => esc_html__( 'Position', 'codevz-plus' ),
				'help' => esc_html__( 'According to form width', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'' => esc_html__( 'Left', 'codevz-plus' ),
					'center' => esc_html__( 'Center', 'codevz-plus' ),
					'right' => esc_html__( 'Right', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'btn_position',
			[
				'label' => esc_html__( 'Button Position', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'' 							=> esc_html__( 'Left', 'codevz-plus' ),
					'cz_subscribe_btn_center' 	=> esc_html__( 'Center', 'codevz-plus' ),
					'cz_subscribe_btn_right' 	=> esc_html__( 'Right', 'codevz-plus' ),
				]
			]
		);

		$this->add_control(
			'btn_title',
			[
				'label' => esc_html__('Button Title', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__('join now', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'icon',
			[
				'label' => esc_html__( 'Icon', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'skin' => 'inline',
				'label_block' => false
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_method',
			[
				'label' => esc_html__( 'Method', 'codevz-plus' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'ajax',
			[
				'label' 	=> esc_html__( 'Method', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SELECT,
				'options' 	=> [
					'' 			=> esc_html__( 'Custom form', 'codevz-plus' ),
					'2' 		=> esc_html__( 'Built-in AJAX', 'codevz-plus' ),
				],
			]
		);

		$description = '<div class="cz-subscribe-desc">
			<span>' . esc_html__( 'All submitted emails will be stored as WordPress users with the', 'codevz-plus' ) . ' <strong>' . esc_html__( 'Subscriber', 'codevz-plus' ) . '</strong> ' . esc_html__( 'role. Users must confirm their subscription through a link sent to their email (double opt-in). Only confirmed emails will be added.', 'codevz-plus' ) . '</span>

			<span>' . esc_html__( 'You can manage these subscribers directly from the', 'codevz-plus' ) . ' <strong>' . esc_html__( 'Users > All Users', 'codevz-plus' ) . '</strong> ' . esc_html__( 'page in your WordPress dashboard. Use the role filter to view only subscribers.', 'codevz-plus' ) . '</span>

			<span>' . esc_html__( 'To send newsletters, you can use plugins like:', 'codevz-plus' ) . '</span>
			<ul>
				<li><strong>MailPoet</strong> – ' . esc_html__( 'send emails directly from your WordPress site.', 'codevz-plus' ) . '</li>
				<li><strong>Newsletter Plugin</strong> – ' . esc_html__( 'manage lists, campaigns, and automations.', 'codevz-plus' ) . '</li>
				<li><strong>Mailchimp for WP</strong> – ' . esc_html__( 'connect with your Mailchimp audience.', 'codevz-plus' ) . '</li>
			</ul>

			<span>' . esc_html__( 'Subscribers are created as real WordPress accounts. Do not assign sensitive capabilities to the "subscriber" role unless needed.', 'codevz-plus' ) . '</span>
		</div>';

		$this->add_control(
			'ajax_notice',
			[
				'type' 			=> Controls_Manager::NOTICE,
				'notice_type' 	=> 'warning',
				'dismissible' 	=> false,
				'heading' 		=> esc_html__( 'Built-in AJAX', 'codevz-plus' ),
				'content' 		=> $description,
				'condition' 	=> [
					'ajax' 			=> '2'
				]
			]
		);

		$this->add_control(
			'action',
			[
				'label' => esc_html__( 'Action URL', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'condition' => [
					'ajax' => ''
				]
			]
		);

		$this->add_control(
			'type_attr',
			[
				'label' => esc_html__( 'Type attributes', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'email',
				'options' => [
					'email' => esc_html__( 'Email', 'codevz-plus' ),
					'text' => esc_html__( 'Text', 'codevz-plus' ),
					'number' => esc_html__( 'Number', 'codevz-plus' ),
					'search' => esc_html__( 'Search', 'codevz-plus' ),
					'tel' => esc_html__( 'Tel', 'codevz-plus' ),
					'time' => esc_html__( 'Time', 'codevz-plus' ),
					'date' => esc_html__( 'Date', 'codevz-plus' ),
					'url' => esc_html__( 'url', 'codevz-plus' ),
					'password' => esc_html__( 'Password', 'codevz-plus' ),
				],
				'condition' => [
					'ajax' => ''
				]
			]
		);

		$this->add_control(
			'name_attr',
			[
				'label' => esc_html__('Email attributes', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'default' => 'MERGE0',
				'condition' => [
					'ajax' => ''
				]
			]
		);

		$this->add_control(
			'method',
			[
				'label' => esc_html__( 'Method', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'post',
				'options' => [
					'post' => esc_html__( 'Post', 'codevz-plus' ),
					'get' => esc_html__( 'Get', 'codevz-plus' ),
				],
				'condition' => [
					'ajax' => ''
				]
			]
		);

		$this->add_control(
			'target',
			[
				'label' => esc_html__( 'Target', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => '_blank',
				'options' => [
					'_blank' => '_blank',
					'_self' => '_self',
				],
				'condition' => [
					'ajax' => ''
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
			'sk_overall',
			[
				'label' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'width', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_subscribe_elm' ),
			]
		);

		$this->add_responsive_control(
			'sk_input',
			[
				'label' 	=> esc_html__( 'Input', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'text-align', 'font-size', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_subscribe_elm input' ),
			]
		);

		$this->add_responsive_control(
			'sk_button',
			[
				'label' 	=> esc_html__( 'Button', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_subscribe_elm button', '.cz_subscribe_elm button:hover' ),
			]
		);

		$this->add_responsive_control(
			'sk_icon',
			[
				'label' 	=> esc_html__( 'Icon', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background' ],
				'condition' => [
					'icon[value]!' => ''
				],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_subscribe_elm button i', '.cz_subscribe_elm button:hover i' ),
			]
		);

		$this->add_responsive_control(
			'sk_msg',
			[
				'label' 	=> esc_html__( 'Message', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background' ],
				'condition' => [
					'ajax' 		=> '2'
				],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_subscribe_msg' ),
			]
		);

	}

	public function render() {

		$settings = $this->get_settings_for_display();

		ob_start();
		Icons_Manager::render_icon( $settings['icon'], [ 'class' => $settings['btn_title'] ? 'mr8' : '' ] );
		$icon = ob_get_clean();

		$btn_title = $icon ? $icon . $settings['btn_title'] : $settings['btn_title'];

		$classes = [];
		$classes[] = 'cz_subscribe_elm clr';
		$classes[] = $settings['style'];
		$classes[] = $settings['btn_position'];
		$classes[] = $settings['position'] ? 'cz_subscribe_elm_' . $settings['position'] : '';
		$classes[] = empty( $settings['name'] ) ? '' : 'cz_subscribe_has_name';
		$classes[] = empty( $settings['ajax'] ) ? '' : 'cz_subscribe_ajax';

		Xtra_Elementor::parallax( $settings );

		if ( ! $settings['action'] ) {
			$settings['action'] = get_site_url();
		}

		$name_input = '';
		$form_name = 'mc-embedded-subscribe-form';

		// Check AJAX method.
		if ( ! empty( $settings['ajax'] ) && $settings['ajax'] === '2' ) {

			$settings['action'] 	= '#';
			$settings['type_attr'] 	= 'email';
			$settings['name_attr'] 	= 'email';
			$settings['method'] 	= 'get';
			$settings['target'] 	= '_self';

			$form_name = 'codevz-plus-subscribe-ajax';

		}

		$name_input  = empty( $settings['name'] ) ? '' : '<input type="text" name="name" placeholder="' . esc_attr( $settings['name'] ) . '" required="required">';
		$email_input = '<input type="' . esc_attr( $settings['type_attr'] ) . '" name="' . esc_attr( $settings['name_attr'] ) . '" placeholder="' . esc_attr( $settings['placeholder'] ) . '" required="required">';

		?>

		<form<?php echo wp_kses_post( (string) Codevz_Plus::classes( [], $classes ) ); ?> data-nonce="<?php echo esc_attr( wp_create_nonce( 'cz_subscribe_nonce' ) ); ?>" action="<?php echo esc_url( $settings['action'] ); ?>" method="<?php echo esc_attr( $settings['method'] ); ?>" name="<?php echo esc_attr( $form_name ); ?>" target="<?php echo esc_url( $settings['target'] ); ?>">
			<div>
				<?php echo do_shortcode( $name_input ); ?>
				<?php echo do_shortcode( $email_input ); ?>
				<input type="text" name="website" placeholder="Your website">
			</div>
			<button name="subscribe" type="submit"><?php echo do_shortcode( wp_kses_post( $btn_title ) ); ?></button>
			<span class="clr"></span>
			<p class="cz_subscribe_msg"></p>
		</form>

		<?php

		Xtra_Elementor::parallax( $settings, true );

	}

}
