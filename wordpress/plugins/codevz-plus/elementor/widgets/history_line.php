<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Xtra_Elementor_Widget_history_line extends Widget_Base {

	protected $id = 'cz_history_line';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'History Line', 'codevz-plus' );
	}

	public function get_icon() {
		return 'xtra-history-line';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'History', 'codevz-plus' ),
			esc_html__( 'Line', 'codevz-plus' ),
			esc_html__( 'Date', 'codevz-plus' ),
			esc_html__( 'Timeline', 'codevz-plus' )

		];

	}

	public function get_style_depends() {

		$array = [ $this->id, 'cz_parallax' ];

		if ( Codevz_Plus::$is_rtl ) {
			$array[] = $this->id . '_rtl';
		}

		return $array;

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

		$year = gmdate( "Y" );

		$this->add_control(
			'year',
			[
				'label' => esc_html__( 'Year', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'default' => $year,
				'placeholder' => $year
			]
		);

		$this->add_control(
			'type', [
				'label' 	=> esc_html__( 'Content type', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SELECT,
				'options' 	=> [
					'' 			=> esc_html__( 'Content', 'codevz-plus' ),
					'template' 	=> esc_html__( 'Saved template', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
				]
			]
		);

		$this->add_control(
			'content', [
				'label' 	=> esc_html__( 'Content', 'codevz-plus' ),
				'type' 		=> Controls_Manager::WYSIWYG,
				'default' 	=> 'Hello World ...',
				'placeholder' => 'Hello World ...',
				'condition' => [
					'type' 		=> ''
				],
			]
		);

		$this->add_control(
			'xtra_elementor_template',
			[
				'label' 	=> esc_html__( 'Select template', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'options' 	=> Xtra_Elementor::get_templates(),
				'condition' => [
					'type' => 'template'
				],
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
			'sk_overall',
			[
				'label' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_history_1' ),
			]
		);

		$this->add_responsive_control(
			'sk_line',
			[
				'label' 	=> esc_html__( 'Line', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'border-color' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_history_1:after' ),
			]
		);

		$this->add_responsive_control(
			'sk_year',
			[
				'label' 	=> esc_html__( 'Year', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'font-family', 'font-size', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_history_1 > span', '.cz_history_1:hover > span' ),
			]
		);

		$this->add_responsive_control(
			'sk_circle',
			[
				'label' 	=> esc_html__( 'Circle', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_history_1:before', '.cz_history_1:hover:before' ),
			]
		);

		$this->end_controls_section();

		// Parallax settings.
		Xtra_Elementor::parallax_settings( $this );
	}

	public function render() {

		$settings = $this->get_settings_for_display();

		$classes = array();
		$classes[] = 'cz_history_1';
		$classes[] = $settings['year'] ? 'cz_has_year' : '';

		$settings['year'] = $settings['year'] ? '<span>' . $settings['year'] . '</span>' : '';

		Xtra_Elementor::parallax( $settings );

		?>
		<div<?php echo wp_kses_post( (string) Codevz_Plus::classes( [], $classes ) ); ?>>

			<?php echo wp_kses_post( (string) $settings['year'] ); ?>

			<div>
				<?php

					if ( $settings[ 'type' ] === 'template' ) {
						echo do_shortcode( Codevz_Plus::get_page_as_element( esc_html( $settings[ 'xtra_elementor_template' ] ) ) );
					} else {
						echo do_shortcode( $settings[ 'content' ] );
					}

				?>
			</div>

		</div>
		<?php

		Xtra_Elementor::parallax( $settings, true );
	}
}