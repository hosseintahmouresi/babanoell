<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

/**
 * Codevz Pro control for elementor.
 *
 * @since v1.0.0
 * @uses `\Elementor\Base_Data_Control` class.
 */
class Xtra_Elementor_Control_Codevz_Pro extends \Elementor\Base_Data_Control {

	/**
	 * Control type.
	 *
	 * @return String
	 */
	public function get_type() {
		return 'codevz_pro';
	}

	public function enqueue() {

		echo '<style>div.elementor-control-type-codevz_pro{padding-top:5px!important;padding-bottom:20px!important;}.elementor-control-codevz-pro-wrapper .xtra-pro{top:3px;right:20px}.elementor-repeater-fields .elementor-control-codevz-pro-wrapper .xtra-pro {right: 10px}.rtl .elementor-control-codevz-pro-wrapper .xtra-pro {left: 20px;right: auto}.rtl .elementor-repeater-fields .elementor-control-codevz-pro-wrapper .xtra-pro {right: 10px}</style>';

	}

	/**
	 * Control template
	 *
	 * @return String template HTML content
	 */
	public function content_template() {

		?>
		<div class="elementor-control-field">
			<label class="elementor-control-title">{{{ data.label }}}</label>
			<div class="elementor-control-codevz-pro-wrapper">
				<?php echo wp_kses_post( (string) Codevz_Plus::pro_badge() ); ?>
				<input type="hidden" name="elementor-codevz-pro-{{ data.name }}-{{ data._cid }}" value="{{ data.value }}" data-setting="{{ data.name }}">
			</div>
		</div>
		<# if ( data.description ) { #>
			<div class="elementor-control-field-description">{{{ data.description }}}</div>
		<# } #>
		<?php

	}

}