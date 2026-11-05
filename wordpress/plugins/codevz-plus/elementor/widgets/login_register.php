<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;

class Xtra_Elementor_Widget_login_register extends Widget_Base {

	protected $id = 'cz_login_register';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Login, Register', 'codevz-plus' );
	}
	
	public function get_icon() {
		return 'xtra-login-register';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_style_depends() {
		return [ $this->id ];
	}

	public function get_script_depends() {
		return [ $this->id ];
	}

	public function register_controls() {

		$free = Codevz_Plus::is_free();

		$this->start_controls_section(
			'section_login_register',
			[
				'label' => esc_html__( 'Settings', 'codevz-plus' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'login',
			[
				'label' => esc_html__( 'Login form?', 'codevz-plus' ),
				'type' => Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'register',
			[
				'label' => esc_html__( 'Registration form?', 'codevz-plus' ),
				'type' => Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'pass_r',
			[
				'label' => esc_html__( 'Password recovery?', 'codevz-plus' ),
				'type' => Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'show',
			[
				'label' => esc_html__( 'Show for admin?', 'codevz-plus' ),
				'type' => Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'banner',
			[
				'label' => esc_html__( 'Banner', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					'' 					=> esc_html__( '~ Select ~', 'codevz-plus' ),
					'cz_lrpr_banner cz_lrpr_banner_left' 	=> esc_html__( 'Left', 'codevz-plus' ),
					'cz_lrpr_banner cz_lrpr_banner_right' 	=> esc_html__( 'Right', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'banner_visibility',
			[
				'label' => esc_html__( 'Banner visibility', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					''  => esc_html__( 'Hide for logged in users', 'codevz-plus' ),
					'1' => esc_html__( 'Show for logged in users', 'codevz-plus' ),
				],
				'condition' => [
					'banner!' => '',
				]
			]
		);

		$this->add_control(
			'image',
			[
				'label' => esc_html__( 'Banner image', 'codevz-plus' ),
				'type' => Controls_Manager::MEDIA,
				'default' => [
					'url' => Codevz_Plus::$url . 'assets/img/p.svg',
				],
				'condition' => [
					'banner!' => '',
				]
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_title',
			[
				'label' => esc_html__( 'Title', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'content_l',
			[
				'label' => esc_html__( 'Login form title', 'codevz-plus' ),
				'type' => Controls_Manager::TEXTAREA,
				'rows' => 10,
			]
		);

		$this->add_control(
			'content_r',
			[
				'label' => esc_html__( 'Registration form title', 'codevz-plus' ),
				'type' => Controls_Manager::TEXTAREA,
				'rows' => 10,
			]
		);

		$this->add_control(
			'content_pr',
			[
				'label' => esc_html__( 'Password recovery title', 'codevz-plus' ),
				'type' => Controls_Manager::TEXTAREA,
				'rows' => 10,
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_url',
			[
				'label' => esc_html__( 'More', 'codevz-plus' )
			]
		);
		
		$this->add_control(
			'redirect',
			[
				'label' => esc_html__( 'Redirect URL', 'codevz-plus' ),
				'type' 	=> $free ? 'codevz_pro' : Controls_Manager::TEXT
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_styling',
			[
				'label' => esc_html__( 'Styling', 'codevz-plus' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'css_width',
			[
				'label' => esc_html__( 'Container width', 'codevz-plus' ),
				'type' => Controls_Manager::SLIDER,
				'default' => [
					'unit' => 'px',
				],
				'range' => [
					'px' => [
						'min' => 320,
						'max' => 1400
					]
				],
				'selectors' => [
					'{{WRAPPER}} .cz_lrpr' => 'max-width: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'sk_con',
			[
				'label' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_lrpr' ),
			]
		);

		$this->add_control(
			'sk_forms',
			[
				'label' => esc_html__( 'Forms', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_lrpr form' ),
			]
		);

		$this->add_control(
			'sk_content',
			[
				'label' => esc_html__( 'Forms title', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'text-align', 'font-family', 'font-size' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_lrpr .cz_lrpr_title' ),
			]
		);

		$this->add_control(
			'sk_inputs',
			[
				'label' => esc_html__( 'Inputs', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'text-align', 'font-size', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_lrpr input:not([type="submit"])' ),
			]
		);

		$this->add_control(
			'sk_buttons',
			[
				'label' => esc_html__( 'Buttons', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_lrpr input[type="submit"]' ),
			]
		);

		$this->add_control(
			'sk_btn_active',
			[
				'label' => esc_html__( 'Loading circle', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'border-right-color' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_lrpr input.cz_loader' ),
			]
		);

		$this->add_control(
			'sk_links',
			[
				'label' => esc_html__( 'Links', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_lrpr form a' ),
			]
		);

		$this->add_control(
			'sk_button_after_login',
			[
				'label' => esc_html__( 'Buttons after login', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'font-size' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_lrpr_buttons a' ),
			]
		);

		$this->add_control(
			'sk_msg',
			[
				'label' => esc_html__( 'Messages', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'text-align', 'font-size', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_lrpr .cz_msg' ),
			]
		);

		$this->end_controls_section();

		// Parallax settings.
		Xtra_Elementor::parallax_settings( $this );

	}

	public function render() {

		// Settings.
		$settings = $this->get_settings_for_display();

		$is_user_logged_in = is_user_logged_in();

		$banner = empty( $settings['banner'] ) ? '' : $settings['banner'];
		$banner_visibility = ( ! $is_user_logged_in || ( $is_user_logged_in && ! empty( $settings['banner_visibility'] ) ) );

		// Classes
		$classes = array();
		$classes[] = 'cz_lrpr clr';
		$classes[] = $banner;
		$classes[] = $banner_visibility ? '' : 'cz_lrpr_hide_banner';
		$classes[] = $settings['login'] ? ' cz_vl' : ( $settings['register'] ? ' cz_vr' : ' cz_vpr' );

		// Out
		$out = '<div data-redirect="' . $settings['redirect'] . '"' . Codevz_Plus::classes( [], $classes ) . '>';

		if ( $banner ) {

			$out .= '<div>';
			if ( ! empty( $settings['image'] ) && $banner_visibility ) {
				$out .= Group_Control_Image_Size::get_attachment_image_html( $settings );
			}
			$out .= '</div><div>';

		}

		if ( $is_user_logged_in && ! $settings['show'] ) {

			$user = wp_get_current_user();

			$out .= '<div style="min-width: 250px;display:block">';
			$out .= '<div class="codevz-account-avatar">';
			$out .= '<img loading="lazy" src="' . esc_url( get_avatar_url( $user->ID, [ 'size' => 150 ] ) ) . '">';
			$out .= '<strong>' . esc_html( ucwords( $user->first_name . ' ' . $user->last_name ) ) . '</strong>';
			$out .= '<span>' . esc_html( $user->user_email ) . '</span>';
			$out .= '</div>';
			$out .= '<div class="cz_lrpr_buttons">';
			$out .= function_exists( 'wc_get_page_permalink' ) ? '<a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '"><i class="fa fa-user"></i> ' . esc_html__( 'My Account', 'codevz-plus' ) . '</a>' : '';
			$out .= function_exists( 'is_woocommerce' ) ? '<a href="' . esc_url( trailingslashit( get_home_url() ) . 'wishlist' ) . '"><i class="fa fa-heart"></i> ' . esc_html__( 'Wishlist', 'codevz-plus' ) . '</a>' : '';
			$out .= '<a href="' . esc_url( wp_logout_url( home_url() ) ) . '"><i class="fa fa-sign-out"></i> ' . esc_html__( 'Logout', 'codevz-plus' ) . '</a>';
			$out .= '</div>';
			$out .= '</div>';

		} else {

			// Var's
			$action 	= '<input name="action" type="hidden" value="cz_ajax_lrpr" />';
			$user 		= '<input name="username" type="text" placeholder="' . esc_html__( 'Username', 'codevz-plus' ) . '" />';
			$email 		= '<input name="email" type="email" placeholder="' . esc_html__( 'Email', 'codevz-plus' ) . '" />';
			$pass 		= '<input name="password" type="password" placeholder="' . esc_html__( 'Password', 'codevz-plus' ) . '" />';
			$pass_r 	= '<input name="pass_r" type="text" placeholder="' . esc_html__( 'Email', 'codevz-plus' ) . '" />';
			$msg 		= '<div class="cz_msg"></div>';
			$login_t 	= $settings['login'] ? '<a href="#cz_l">' . esc_html__( 'Already registered? Sign In', 'codevz-plus' ) . '</a>' : '';
			$register_t = $settings['register'] ? '<div class="clr"></div><a href="#cz_r">' . esc_html__( 'Not registered? Create an account', 'codevz-plus' ) . '</a>' : '';
			$f_pass_t 	= $settings['pass_r'] ? '<a href="#cz_pr">' . esc_html__( 'Forgot your password? Get help', 'codevz-plus' ) . '</a>' : '';

			if ( $settings['login'] ) {
				$cl = $settings['content_l'] ? '<div class="cz_lrpr_title mb30">' . do_shortcode( $settings['content_l'] ) . '</div>' : '';
				$out .= '<form id="cz_l">' . $cl . $action . $user . $pass . self::security( 'login' ) . '<input type="submit" value="' . esc_html__( 'Login now', 'codevz-plus' ) . '">' . $msg . $f_pass_t . $register_t . '</form>';
			}

			if ( $settings['register'] ) {
				$cr = $settings['content_r'] ? '<div class="cz_lrpr_title mb30">' . do_shortcode( $settings['content_r'] ) . '</div>' : '';
				$out .= '<form id="cz_r">' . $cr . $action . $user . $email . $pass . self::security( 'register' ) . '<input type="submit" value="' . esc_html__( 'Signup', 'codevz-plus' ) . '">' . $msg . $login_t . '</form>';
			}

			if ( $settings['pass_r'] ) {
				$cpr = $settings['content_pr'] ? '<div class="cz_lrpr_title mb30">' . do_shortcode( $settings['content_pr'] ) . '</div>' : '';
				$out .= '<form id="cz_pr">' . $cpr . $action . $pass_r . self::security( 'password' ) . '<input type="submit" value="' . esc_html__( 'Send my password', 'codevz-plus' ) . '">' . $msg . $login_t . '</form>';
			}

			$out .= do_action( 'wordpress_social_login' );
		}

		if ( $banner ) {
			$out .= '</div>';
		}

		$out .= '</div>';

		echo do_shortcode( $out );
	}

	/**
	 *
	 * Generate security input
	 * 
	 * @return string
	 * 
	 */
	public static function security( $i ) {
		$num_a = wp_rand( 1, 10 );
		$num_b = wp_rand( 1, 10 );
		return '<input name="security_' . $i . '" type="text" placeholder="' . $num_a . ' + ' . $num_b . ' ?" /><input name="security_' . $i . '_a" type="hidden" value="' . md5( $num_a + $num_b ) . '" />';
	}

}