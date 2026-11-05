<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Xtra_Elementor_Widget_contact_form_7 extends Widget_Base {

	protected $id = 'cz_contact_form_7';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Contact Form 7', 'codevz-plus' );
	}
	
	public function get_icon() {
		return 'xtra-cf7';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Contact', 'codevz-plus' ),
			esc_html__( 'Form', 'codevz-plus' ),
			esc_html__( 'Email', 'codevz-plus' ),
			'7',

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
				'tab' 	=> Controls_Manager::TAB_CONTENT,
			]
		);

		$cf7 = get_posts( 'post_type="wpcf7_contact_form"&numberposts=-1' );
		$contact_forms = array( 0 => esc_html__( 'Select Contact Form', 'codevz-plus' ) );
		if ( $cf7 ) {
			foreach ( $cf7 as $cform ) {
				$contact_forms[ $cform->post_title ] = $cform->post_title;
			}
		} else {
			$contact_forms[ 0 ] = esc_html__( 'No contact forms found', 'codevz-plus' );
		}

		$this->add_control(
			'cf7',
			[
				'label' => esc_html__( 'Contact Form', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => '',
				'options' => $contact_forms,
				'admin_label' 	=> true,
				'save_always' 	=> true
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
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_cf7' ),
			]
		);

		$this->add_responsive_control(
			'sk_label',
			[
				'label' 	=> esc_html__( 'Labels', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'text-align', 'font-size' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_cf7 label' ),
			]
		);

		$this->add_responsive_control(
			'sk_input',
			[
				'label' 	=> esc_html__( 'Inputs', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'text-align', 'font-size', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_cf7 input:not([type="submit"]), .cz_cf7 input[type="date"], .cz_cf7 textarea, .cz_cf7 select' ),
			]
		);

		$this->add_responsive_control(
			'sk_button',
			[
				'label' 	=> esc_html__( 'Button', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'float', 'width', 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_cf7 input[type="submit"], .cz_cf7 button', '.cz_cf7 input[type="submit"]:hover, .cz_cf7 button:hover' ),
			]
		);

		$this->add_responsive_control(
			'sk_messages',
			[
				'label' 	=> esc_html__( 'Messages', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_cf7 .wpcf7-response-output' ),
			]
		);

		// Row Design
		$this->add_responsive_control(
			'sk_p',
			[
				'label' 	=> esc_html__( 'All rows', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'width', 'border'  ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_cf7 p' ),
			]
		);

		$this->add_responsive_control(
			'sk_p_1',
			[
				'label' 	=> esc_html__( 'Feild', 'codevz-plus' ) . ' 1',
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'width', 'float', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_cf7 p:nth-child(2)' ),
			]
		);

		$this->add_responsive_control(
			'sk_p_2',
			[
				'label' 	=> esc_html__( 'Feild', 'codevz-plus' ) . ' 2',
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'width', 'float', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_cf7 p:nth-child(3)' ),
			]
		);

		$this->add_responsive_control(
			'sk_p_3',
			[
				'label' 	=> esc_html__( 'Feild', 'codevz-plus' ) . ' 3',
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'width', 'float', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_cf7 p:nth-child(4)' ),
			]
		);

		$this->add_responsive_control(
			'sk_p_4',
			[
				'label' 	=> esc_html__( 'Feild', 'codevz-plus' ) . ' 4',
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'width', 'float', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_cf7 p:nth-child(5)' ),
			]
		);

		$this->add_responsive_control(
			'sk_p_5',
			[
				'label' 	=> esc_html__( 'Feild', 'codevz-plus' ) . ' 5',
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'width', 'float', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_cf7 p:nth-child(6)' ),
			]
		);

		$this->add_responsive_control(
			'sk_p_6',
			[
				'label' 	=> esc_html__( 'Feild', 'codevz-plus' ) . ' 6',
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'width', 'float', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_cf7 p:nth-child(7)' ),
			]
		);

		$this->add_responsive_control(
			'sk_p_7',
			[
				'label' 	=> esc_html__( 'Feild', 'codevz-plus' ) . ' 7',
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'width', 'float', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_cf7 p:nth-child(8)' ),
			]
		);

		$this->add_responsive_control(
			'sk_p_8',
			[
				'label' 	=> esc_html__( 'Feild', 'codevz-plus' ) . ' 8',
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'width', 'float', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_cf7 p:nth-child(9)' ),
			]
		);

		$this->add_responsive_control(
			'sk_p_9',
			[
				'label' 	=> esc_html__( 'Feild', 'codevz-plus' ) . ' 9',
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'width', 'float', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_cf7 p:nth-child(10)' ),
			]
		);

		$this->add_responsive_control(
			'sk_p_10',
			[
				'label' 	=> esc_html__( 'Feild', 'codevz-plus' ) . ' 10',
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'width', 'background', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_cf7 p:nth-child(11)' ),
			]
		);

		$this->add_responsive_control(
			'sk_p_11',
			[
				'label' 	=> esc_html__( 'Feild', 'codevz-plus' ) . ' 11',
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'width', 'background', 'border', 'box-shadow'],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_cf7 p:nth-child(12)' ),
			]
		);

		$this->add_responsive_control(
			'sk_p_12',
			[
				'label' 	=> esc_html__( 'Feild', 'codevz-plus' ) . ' 12',
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'width', 'background', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_cf7 p:nth-child(13)' ),
			]
		);

		$this->add_responsive_control(
			'sk_p_13',
			[
				'label' 	=> esc_html__( 'Feild', 'codevz-plus' ) . ' 13',
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'width', 'background', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_cf7 p:nth-child(14)' ),
			]
		);

		$this->add_responsive_control(
			'sk_p_14',
			[
				'label' 	=> esc_html__( 'Feild', 'codevz-plus' ) . ' 14',
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'width', 'background', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_cf7 p:nth-child(15)' ),
			]
		);

		$this->add_responsive_control(
			'sk_p_15',
			[
				'label' 	=> esc_html__( 'Field', 'codevz-plus' ) . ' 15',
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'width', 'background', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_cf7 p:nth-child(16)' ),
			]
		);

		$this->end_controls_section();
	}

	public function render() {

		$settings = $this->get_settings_for_display();

		// Classes
		$classes = array();
		$classes[] = 'cz_cf7 clr';

		// If plugin not installed
		if ( ! class_exists( 'WPCF7' ) ) {
			return '<pre>' . esc_html__( 'Plugin "Contact Form 7" not installed or activated', 'codevz-plus' ) . '</pre>';
		}

		$cf7 = Codevz_Plus::get_page_by_title( esc_html( $settings['cf7'] ), [ 'wpcf7_contact_form' ] );

		if ( ! $cf7 ) {

			$cf7 = get_posts(array(
				'numberposts' 	=> 1, 
				'post_type' 	=> 'wpcf7_contact_form'
			));

			$cf7 = isset( $cf7[0]->ID ) ? $cf7[0]->ID : 0;

		}

		Xtra_Elementor::parallax( $settings );

		?>
		<div<?php echo wp_kses_post( (string) Codevz_Plus::classes( [], $classes ) ); ?>>
			<div><?php echo do_shortcode( '[contact-form-7 id="' . esc_attr( $cf7 ) . '"]' ); ?></div>
		</div>
		<?php
		
		Xtra_Elementor::parallax( $settings, true );
	}
}