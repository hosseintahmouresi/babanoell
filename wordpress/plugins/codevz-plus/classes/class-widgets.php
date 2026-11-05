<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

/**
 * Codevz custom widgets
 * 
 * @author Codevz
 * @link http://codevz.com/
 */

class Codevz_Widget {

	public static function settings( $widget, $data, $fields = null ) {
		$fields = $fields ? $fields : $widget->fields();

		foreach( $fields as $field ) {
			$name = $field[ 'name' ];
			$field[ 'id' ] = $field[ 'name' ] = $widget->get_field_name( $name );

			if ( isset( $field[ 'hover' ] ) ) {
				$field[ 'hover_id' ] = $widget->get_field_name( $name ) . $field[ 'hover' ];
			}

			$field[ 'echo' ] = true;
			$default = ( isset( $field[ 'default' ] ) ? $field[ 'default' ] : '' );

			if ( isset( $field[ 'split' ] ) ) {
				echo '<div class="cz-w2 codevz-field clearfix">';
			}

			codevz_add_field( $field, ( isset( $data[ $name ] ) ? $data[ $name ] : $default ) );

			if ( isset( $field[ 'split' ] ) ) {
				echo '</div>';
			}

		}
	}

	public static function update( $widget, $data ) {
		foreach( $widget->fields() as $field ) {
			$name = $field[ 'name' ];
			$new[ $name ] = isset( $new[ $name ] ) ? $new[ $name ] : '';
		}

		return $new;
	}

	public static function output( $shortcode, $args, $data, $out = 9 ) {

		// Shortcode.
		if ( $shortcode && $data ) {
			$out = '[' . $shortcode . ' ';

			foreach( $data as $key => $value ) {
				if ( $value && $key !== 'title' ) {

					if ( is_array( $value ) && $key === 'items' && $shortcode === 'cz_stylish_list' ) {
						$value = json_decode( wp_json_encode( $value ), true );

						foreach ( $value as $val => $v ) {
							if ( ! empty( $value[ $val ]['link'] ) ) {
								$value[ $val ]['link'] = 'url:' . urlencode( $value[ $val ]['link'] ) . '|||';
							}
						}
					}

					if ( is_array( $value ) && $key === 'social' && $shortcode === 'cz_social_icons' ) {
						$value = json_decode( wp_json_encode( $value ), true );
					}

					$value = is_array( $value ) ? urlencode( wp_json_encode( $value ) ) : $value;

					$out .= $key . '="' . $value . '" ';
				}
			}

			// Shortcode content.
			if ( isset( $data['content'] ) ) {
				$out .= ']' . $data['content'];
			} else {
				$out .= ']';
			}

			// Close shortcode.
			$out .= '[/' . $shortcode . ']';
		}

		// Output.
		if ( $out !== 9 ) {

			extract( $args );

			$output = $before_widget;

			if ( ! empty( $data['title'] ) ) {
				$output .= $before_title . apply_filters( 'widget_title', $data['title'] ) . $after_title;
			}

			$output .= $out . $after_widget;

			echo do_shortcode( $output );
		}
	}
}

/**
 *
 * Add new options for widgets
 *
 */
function codevz_all_widgets_add_options( $widget, $return, $data ) {

	$free = Codevz_Plus::$is_free;

	// Widget search: button StyleKit
	if ( $widget->id_base === 'search' ) {

		Codevz_Widget::settings( $widget, $data, [
			[
				'name'  	=> 'czsk_button',
				'hover'  	=> '_hover',
				'type' 		=> $free ? 'content' : 'cz_sk',
				'content' 	=> Codevz_Plus::pro_badge(),
				'title' 	=> esc_html__( 'Button', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Button', 'codevz-plus' ),
				'settings' 	=> [ 'color', 'background', 'border' ]
			],
			[
				'name'  	=> 'czsk_button_hover',
				'type'  	=> 'cz_sk_hidden'
			],
			[
				'name'  	=> 'czsk_button_tablet',
				'type'  	=> 'cz_sk_hidden'
			],
			[
				'name'  	=> 'czsk_button_mobile',
				'type'  	=> 'cz_sk_hidden'
			],
		]);

	}

	// General StyleKit for all widgets.
	Codevz_Widget::settings( $widget, $data, [
		[
			'name'  	=> 'hide_widget_title',
			'type' 		=> $free ? 'content' : 'switcher',
			'content' 	=> Codevz_Plus::pro_badge(),
			'title' 	=> esc_html__( 'Hide title?', 'codevz-plus' ),
		],
		[
			'name'  	=> 'unbox_widget',
			'type' 		=> $free ? 'content' : 'switcher',
			'content' 	=> Codevz_Plus::pro_badge(),
			'title' 	=> esc_html__( 'Remove container?', 'codevz-plus' ),
		],
		[
			'name'  	=> 'c_on_mobile',
			'type' 		=> $free ? 'content' : 'switcher',
			'content' 	=> Codevz_Plus::pro_badge(),
			'default'  	=> '1',
			'title' 	=> esc_html__( 'Center on mobile?', 'codevz-plus' ),
		],
		[
			'name'  	=> 'hide_on_mobile',
			'type' 		=> $free ? 'content' : 'switcher',
			'content' 	=> Codevz_Plus::pro_badge(),
			'title' 	=> esc_html__( 'Hide on Mobile?', 'codevz-plus' ),
		],
		[
			'name'  	=> 'czsk',
			'hover'  	=> '_hover',
			'type' 		=> $free ? 'content' : 'cz_sk',
			'content' 	=> Codevz_Plus::pro_badge(),
			'title' 	=> esc_html__( 'Container', 'codevz-plus' ),
			'button' 	=> esc_html__( 'Container', 'codevz-plus' ),
			'settings' 	=> [ 'color', 'background', 'border' ]
		],
		[
			'name'  	=> 'czsk_hover',
			'type'  	=> 'cz_sk_hidden'
		],
		[
			'name'  	=> 'czsk_tablet',
			'type'  	=> 'cz_sk_hidden'
		],
		[
			'name'  	=> 'czsk_mobile',
			'type'  	=> 'cz_sk_hidden'
		],
	]);

}
add_filter( 'in_widget_form', 'codevz_all_widgets_add_options', 10, 3 );

/**
 * Save custom options for widgets
 */
function codevz_widget_update_callback( $current, $new ) {

	if ( ! isset( $new['hide_on_mobile'] ) ) {
		$new['hide_on_mobile'] = '';
	}

	if ( ! isset( $new['c_on_mobile'] ) ) {
		$new['c_on_mobile'] = '';
	}

	if ( ! isset( $new['hide_widget_title'] ) ) {
		$new['hide_widget_title'] = '';
	}

	if ( ! isset( $new['unbox_widget'] ) ) {
		$new['unbox_widget'] = '';
	}

	foreach( $new as $key => $value ) {
		
		$current[ $key ] = $value;

	}

	return $current;
}
add_filter( 'widget_update_callback', 'codevz_widget_update_callback', 10, 2 );

/**
 * Output of custom options for widget
 */
function codevz_widget_display_callback( $data, $widget_class, $args ) {

	if ( $data == false ) {
		return $data;
	}

	$css = $inline = '';
	if ( ! empty( $widget_class->id ) ) {
		$id = $widget_class->id;

		if ( ! empty( $data['czsk'] ) ) {
			$css .= '#' . $id . '{' . Codevz_Plus::sk_inline_style( $data['czsk'] ) . '}';
		}
		if ( ! empty( $data['czsk_hover'] ) ) {
			$css .= '#' . $id . ':hover{' . Codevz_Plus::sk_inline_style( $data['czsk_hover'] ) . '}';
		}
		if ( ! empty( $data['czsk_tablet'] ) ) {
			$css .= '@media screen and (max-width:768px){#' . $id . '{' . Codevz_Plus::sk_inline_style( $data['czsk_tablet'] ) . '}}';
		}
		if ( ! empty( $data['czsk_mobile'] ) ) {
			$css .= '@media screen and (max-width:480px){#' . $id . '{' . Codevz_Plus::sk_inline_style( $data['czsk_mobile'] ) . '}}';
		}

		if ( ! empty( $data['czsk_button'] ) ) {
			$css .= '#' . $id . ' form button{' . Codevz_Plus::sk_inline_style( $data['czsk_button'] ) . '}';
		}
		if ( ! empty( $data['czsk_button_hover'] ) ) {
			$css .= '#' . $id . ' form button:hover{' . Codevz_Plus::sk_inline_style( $data['czsk_button_hover'] ) . '}';
		}

		// CSS output.
		if ( is_customize_preview() ) {
			$args['after_widget'] = '<style>' . $css . '</style>' . $args['after_widget'];
			$css = '';
		} else {
			$css = $css ? 'data-cz-style="' . $css . '" ' : '';
		}
	}

	// Widget classes.
	$new_class = $css . 'class="';
	$new_class .= empty( $data['hide_on_mobile'] ) ? '' : 'hide_on_mobile ';

	if ( ! isset( $data['c_on_mobile'] ) || ! empty( $data['c_on_mobile'] ) ) {
		$new_class .= 'center_on_mobile ';
	}

	// Hide title.
	if ( ! empty( $data['hide_widget_title'] ) ) {

		$args['before_title'] = '<div class="hidden" aria-hidden="true">';
		$args['after_title'] = '</div>';

	}

	// Unbox.
	if ( ! empty( $data['unbox_widget'] ) ) {

		$args['before_widget'] = str_replace( 'widget clr', 'mb40 clr', $args['before_widget'] );

	}

	// New CSS class.
	$args['before_widget'] = str_replace( 'class="', $new_class, $args['before_widget'] );

	// Content div after title if title exists.
	if ( ! empty( $data[ 'title' ] ) ) {
		$args['after_title'] .= '<div class="codevz-widget-content clr">';
		$args['after_widget'] .= '</div>';
	}

	// new output.
	$widget_class->widget( $args, $data );

	return false;
}
add_filter( 'widget_display_callback', 'codevz_widget_display_callback', 10, 3 );

/**
 *
 * Widget: Working hours
 * 
 */
class Codevz_Widget_Working_Hours extends WP_Widget {

	public function __construct() {
		parent::__construct( 
			false, 
			'- ' . esc_html__( 'Working hours', 'codevz-plus' ), 
			[ 
				'customize_selective_refresh' => true, 
				'classname' => 'codevz-widget-working-hours' 
			]
		);
	}

	// Output
	public function widget( $args, $data ) {
		Codevz_Widget::output( 'cz_working_hours', $args, $data );
	}
	
	// Update
	public function update( $data, $new ) {
		Codevz_Widget::update( $this, $new );
	}

	// Settings
	public function form( $data ) {
		Codevz_Widget::settings( $this, $data );
	}

	// Fields
	public function fields() {
		return [
			[
				'name'		=> 'title',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Title', 'codevz-plus' ),
				'default' 	=> esc_html__( 'Working hours', 'codevz-plus' )
			],
			[
				'name'		=> 'content',
				'type'		=> 'textarea',
				'title'		=> esc_html__( 'Description', 'codevz-plus' )
			],
			[
				'name'            => 'items',
				'type'            => 'group',
				'title' 		  => '',
				'button_title'    => esc_html__( 'Add', 'codevz-plus' ),
				'fields'          => [
					[
						'id' 			=> 'left_text',
						'type' 			=> 'text',
						'title' 		=> esc_html__('Left', 'codevz-plus' ),
						'default' 		=> 'Monday',
					],
					[
						'id' 			=> 'right_text',
						'type' 			=> 'text',
						'title' 		=> esc_html__('Right', 'codevz-plus' ),
						'default' 		=> '9:00 to 16:30',
					],
					[
						'id' 			=> 'sub',
						'type' 			=> Codevz_Plus::$is_free ? 'content' : 'icon',
						'content' 		=> Codevz_Plus::pro_badge(),
						'title' 		=> esc_html__('Subtitle', 'codevz-plus' )
					],
					[
						'id' 			=> 'badge',
						'type' 			=> Codevz_Plus::$is_free ? 'content' : 'icon',
						'content' 		=> Codevz_Plus::pro_badge(),
						'title' 		=> esc_html__('Badge', 'codevz-plus' )
					],
					[
						'id' 			=> 'icon',
						'type' 			=> Codevz_Plus::$is_free ? 'content' : 'icon',
						'content' 		=> Codevz_Plus::pro_badge(),
						'title' 		=> esc_html__('Icon', 'codevz-plus' )
					],
				],
			],
			[
				'name' 		=> 'between_texts',
				'type' 		=> 'switcher',
				'title' 	=> esc_html__( 'Line between', 'codevz-plus' )
			],
			[
				'name'  	=> 'sk_con',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'settings' 	=> [ 'background', 'border' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_con_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_con_mobile' ],
			[
				'name'  	=> 'sk_line',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Line', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Line', 'codevz-plus' ),
				'settings' 	=> [ 'border', 'border-color' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_line_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_line_mobile' ],
			[
				'name'  	=> 'sk_left',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Left', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Left', 'codevz-plus' ),
				'settings' 	=> [ 'color', 'font-family', 'font-size', 'background' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_left_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_left_mobile' ],
			[
				'name'  	=> 'sk_right',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Right', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Right', 'codevz-plus' ),
				'settings' 	=> [ 'color', 'font-family', 'font-size', 'background' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_right_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_right_mobile' ],
			[
				'name'  	=> 'sk_badge',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Badge', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Badge', 'codevz-plus' ),
				'settings' 	=> [ 'color', 'background' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_badge_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_badge_mobile' ],
			[
				'name'  	=> 'sk_sub',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Subtitle', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Subtitle', 'codevz-plus' ),
				'settings' 	=> [ 'color', 'background' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_sub_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_sub_mobile' ],
			[
				'name'  	=> 'sk_icon',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Icon', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Icon', 'codevz-plus' ),
				'settings' 	=> [ 'color', 'background' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_icon_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_icon_mobile' ],
		];
	}
}

/**
 *
 * Widget: Stylish List
 * 
 */
class Codevz_Widget_Stylish_List extends WP_Widget {

	public function __construct() {
		parent::__construct( 
			false, 
			'- ' . esc_html__( 'Stylish list', 'codevz-plus' ), 
			[ 
				'customize_selective_refresh' => true, 
				'classname' => 'codevz-widget-stylish-list' 
			]
		);
	}

	// Output
	public function widget( $args, $data ) {
		Codevz_Widget::output( 'cz_stylish_list', $args, $data );
	}
	
	// Update
	public function update( $data, $new ) {
		Codevz_Widget::update( $this, $new );
	}

	// Settings
	public function form( $data ) {
		Codevz_Widget::settings( $this, $data );
	}

	// Widget fields
	public function fields() {
		return [
			[
				'name'		=> 'title',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Title', 'codevz-plus' ),
				'default' 	=> esc_html__( 'Stylish list', 'codevz-plus' )
			],
			[
				'name'		=> 'content',
				'type'		=> 'textarea',
				'title'		=> esc_html__( 'Description', 'codevz-plus' )
			],
			[
				'name'            => 'items',
				'type'            => 'group',
				'title' 		  => '',
				'button_title'    => esc_html__( 'Add', 'codevz-plus' ),
				'fields'          => [
					[
						'id'          => 'title',
						'type'        => 'text',
						'title'       => esc_html__('Title', 'codevz-plus' )
					],
					[
						'id'          => 'subtitle',
						'type'        => 'text',
						'title'       => esc_html__('Subtitle', 'codevz-plus' )
					],
					[
						'id'          => 'icon',
						'type'        => 'icon',
						'title'       => esc_html__('Icon', 'codevz-plus' )
					],
					[
						'id'          => 'link',
						'type'        => 'text',
						'title'       => esc_html__('Link', 'codevz-plus' )
					],
					[
						'id'          => 'link_target',
						'type' 		  => Codevz_Plus::$is_free ? 'content' : 'switcher',
						'content' 	  => Codevz_Plus::pro_badge(),
						'title'       => esc_html__('Open in new page?', 'codevz-plus' )
					],
				],
				'default' => [
					[
						'title' => esc_html__('Title', 'codevz-plus' )
					]
				]
			],
			[
				'name'        => 'default_icon',
				'type'        => 'icon',
				'title'       => esc_html__('Default icon', 'codevz-plus' )
			],
			[
				'name'  	=> 'sk_overall',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'settings' 	=> [ 'background', 'border', 'box-shadow' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_overall_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_overall_mobile' ],
			[
				'name'  	=> 'sk_lists',
				'hover'  	=> '_hover',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Title', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Title', 'codevz-plus' ),
				'settings' 	=> [ 'color' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_lists_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_lists_mobile' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_lists_hover' ],
			[
				'name'  	=> 'sk_subtitle',
				'hover'  	=> '_hover',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Subtitle', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Subtitle', 'codevz-plus' ),
				'settings' 	=> [ 'color' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_subtitle_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_subtitle_mobile' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_subtitle_hover' ],
			[
				'name'  	=> 'sk_icons',
				'hover'  	=> '_hover',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Icons', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Icons', 'codevz-plus' ),
				'settings' 	=> [ 'color', 'background' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_icons_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_icons_mobile' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_icons_hover' ],
			[
				'name'  	=> 'icon_hover_fx',
				'type'  	=> 'select',
				'title' 	=> esc_html__( 'Hover', 'codevz-plus' ),
				'options' 	=> [
					'cz_sl_icon_hover_none' 		=> esc_html__( 'None', 'codevz-plus' ),
					'cz_sl_icon_hover_zoom_in' 		=> esc_html__( 'Zoom In', 'codevz-plus' ),
					'cz_sl_icon_hover_zoom_out' 	=> esc_html__( 'Zoom Out', 'codevz-plus' ),
					'cz_sl_icon_hover_blur' 		=> esc_html__( 'Blur', 'codevz-plus' ),
					'cz_sl_icon_hover_flash' 		=> esc_html__( 'Flash', 'codevz-plus' ),
					'cz_sl_icon_hover_absorber' 	=> esc_html__( 'Absorber', 'codevz-plus' ),
					'cz_sl_icon_hover_wobble' 		=> esc_html__( 'Wobble', 'codevz-plus' ),
					'cz_sl_icon_hover_zoom_in_fade' => esc_html__( 'Zoom in fade', 'codevz-plus' ),
					'cz_sl_icon_hover_zoom_out_fade' => esc_html__( 'Zoom out fade', 'codevz-plus' ),
					'cz_sl_icon_hover_push_in' 		=> esc_html__( 'Push in', 'codevz-plus' ),
				]
			],
		];
	}
}

/**
 * Widget: Social Icons
 */
class Codevz_Widget_Social_Icons extends WP_Widget {

	public function __construct() {
		parent::__construct( 
			false, 
			'- ' . esc_html__( 'Social icons', 'codevz-plus' ), 
			[ 
				'customize_selective_refresh' => true, 
				'classname' => 'codevz-widget-social-icons' 
			]
		);
	}

	// Output
	public function widget( $args, $data ) {

		// Fix icons hover.
		if ( isset( $data['sk_icons_hover'] ) ) {
			$data['sk_hover'] = Codevz_Plus::sk_inline_style( $data['sk_icons_hover'] );
		}

		Codevz_Widget::output( 'cz_social_icons', $args, $data );
	}
	
	// Update
	public function update( $data, $new ) {
		Codevz_Widget::update( $this, $new );
	}

	// Settings
	public function form( $data ) {
		Codevz_Widget::settings( $this, $data );
	}

	// Widget fields
	public function fields() {
		return [
			[
				'name'		=> 'title',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Title', 'codevz-plus' ),
				'default' 	=> esc_html__( 'Social icons', 'codevz-plus' )
			],
			[
				'name'            => 'social',
				'type'            => 'group',
				'title' 		  => '',
				'button_title'    => esc_html__( 'Icons', 'codevz-plus' ),
				'fields'          => [
					[
						'id'          => 'title',
						'type'        => 'text',
						'title'       => esc_html__('Title', 'codevz-plus' )
					],
					[
						'id'          => 'icon',
						'type'        => 'icon',
						'title'       => esc_html__('Icon', 'codevz-plus' )
					],
					[
						'id'          => 'link',
						'type'        => 'text',
						'title'       => esc_html__('Link', 'codevz-plus' )
					],
					[
						'id'          => 'link_target',
						'type'        => 'switcher',
						'title'       => esc_html__('Open in same page?', 'codevz-plus' )
					]
				]
			],
			[
				'name'        => 'position',
				'type'        => 'select',
				'title'       => esc_html__('Position', 'codevz-plus' ),
				'options'	  => [
					'tal' 		=> esc_html__('Left', 'codevz-plus' ),
					'tac' 		=> esc_html__('Center', 'codevz-plus' ),
					'tar' 		=> esc_html__('Right', 'codevz-plus' ),
				]
			],
			[
				'name'        => 'tooltip',
				'type'        => 'select',
				'title'       => esc_html__('Tooltip', 'codevz-plus' ),
				'help' 		  => esc_html__( 'StyleKit located in Theme Options > General > Colors & Styles', 'codevz-plus' ),
				'options'	  => [
					'' 							 	=> esc_html__('~ Default ~', 'codevz-plus' ),
					'cz_tooltip cz_tooltip_up' 	 	=> esc_html__('Up', 'codevz-plus' ),
					'cz_tooltip cz_tooltip_down' 	=> esc_html__('Down', 'codevz-plus' ),
					'cz_tooltip cz_tooltip_left' 	=> esc_html__('Left', 'codevz-plus' ),
					'cz_tooltip cz_tooltip_right' 	=> esc_html__('Right', 'codevz-plus' ),
				]
			],
			[
				'name'        => 'fx',
				'type' 		  => Codevz_Plus::$is_free ? 'content' : 'select',
				'content' 	  => Codevz_Plus::pro_badge(),
				'title'       => esc_html__( 'Hover', 'codevz-plus' ),
				'options'	  => [
					'' 					=> esc_html__('~ Default ~', 'codevz-plus' ),
					'cz_social_fx_0' 	 => esc_html__('Zoom In', 'codevz-plus' ),
					'cz_social_fx_1' 	 => esc_html__('Zoom Out', 'codevz-plus' ),
					'cz_social_fx_2' 	 => esc_html__('Bottom to Top', 'codevz-plus' ),
					'cz_social_fx_3' 	 => esc_html__('Top to Bottom', 'codevz-plus' ),
					'cz_social_fx_4' 	 => esc_html__('Left to Right', 'codevz-plus' ),
					'cz_social_fx_5' 	 => esc_html__('Right to Left', 'codevz-plus' ),
					'cz_social_fx_6' 	 => esc_html__('Rotate', 'codevz-plus' ),
					'cz_social_fx_7' 	 => esc_html__('Infinite Shake', 'codevz-plus' ),
					'cz_social_fx_8' 	 => esc_html__('Infinite Wink', 'codevz-plus' ),
					'cz_social_fx_9' 	 => esc_html__('Quick Bob', 'codevz-plus' ),
					'cz_social_fx_10' 	 => esc_html__('Flip Horizontal', 'codevz-plus' ),
					'cz_social_fx_11' 	 => esc_html__('Flip Vertical', 'codevz-plus' ),
				]
			],
			[
				'name'        => 'inline_title',
				'type' 		=> Codevz_Plus::$is_free ? 'content' : 'switcher',
				'content' 	=> Codevz_Plus::pro_badge(),
				'title'       => esc_html__('Inline title', 'codevz-plus' )
			],
			[
				'name'        => 'color_mode',
				'type'        => 'select',
				'title'       => esc_html__( 'Color mode', 'codevz-plus' ),
				'options'	  => [
					'' 							=> esc_html__('~ Default ~', 'codevz-plus' ),
					'cz_social_colored' 		=> esc_html__( 'Brand Colors', 'codevz-plus' ),
					'cz_social_colored_hover' 	=> esc_html__( 'Brand Colors on Hover', 'codevz-plus' ),
					'cz_social_colored_bg' 		=> esc_html__( 'Brand Background', 'codevz-plus' ),
					'cz_social_colored_bg_hover' => esc_html__( 'Brand Background on Hover', 'codevz-plus' ),
				]
			],
			[
				'name'  	=> 'sk_con',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'settings' 	=> [ 'background', 'border', 'box-shadow' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_con_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_con_mobile' ],
			[
				'name'  	=> 'sk_icons',
				'hover'  	=> '_hover',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Icons', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Icons', 'codevz-plus' ),
				'settings' 	=> [ 'width', 'color', 'background', 'border' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_icons_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_icons_mobile' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_icons_hover' ],
			[
				'name'  	=> 'sk_inner_icon',
				'hover'  	=> '_hover',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Inner icons', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Inner icons', 'codevz-plus' ),
				'settings' 	=> [ 'width', 'height', 'color', 'line-height', 'font-size', 'background', 'border' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_inner_icon_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_inner_icon_mobile' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_inner_icon_hover' ],
			[
				'name'  	=> 'sk_title',
				'hover'  	=> '_hover',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Inline title', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Inline title', 'codevz-plus' ),
				'settings' 	=> [ 'color', 'font-family', 'font-size' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_title_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_title_mobile' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_title_hover' ],
		];
	}
}

/**
 * Widget: Social Icons
 */
class Codevz_Widget_Login extends WP_Widget {

	public function __construct() {
		parent::__construct( 
			false, 
			'- ' . esc_html__( 'Login / Register', 'codevz-plus' ), 
			[ 
				'customize_selective_refresh' => true, 
				'classname' => 'codevz-widget-login-register' 
			]
		);
	}

	// Output
	public function widget( $args, $data ) {

		// Fix icons hover.
		if ( isset( $data['sk_icons_hover'] ) ) {
			$data['sk_hover'] = Codevz_Plus::sk_inline_style( $data['sk_icons_hover'] );
		}

		Codevz_Widget::output( 'cz_login_register', $args, $data );
	}
	
	// Update
	public function update( $data, $new ) {
		Codevz_Widget::update( $this, $new );
	}

	// Settings
	public function form( $data ) {
		Codevz_Widget::settings( $this, $data );
	}

	// Widget fields
	public function fields() {
		return [
			[
				'name'		=> 'title',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Title', 'codevz-plus' ),
				'default' 	=> esc_html__( 'Login', 'codevz-plus' )
			],
			[
				'name'		=> 'login',
				'type'		=> 'switcher',
				'default' 	=> 1,
				'title'		=> esc_html__( 'Login form?', 'codevz-plus' )
			],
			[
				'name'		=> 'register',
				'type'		=> 'switcher',
				'title'		=> esc_html__( 'Registeration form?', 'codevz-plus' )
			],
			[
				'name'		=> 'pass_r',
				'type'		=> 'switcher',
				'title'		=> esc_html__( 'Password reset?', 'codevz-plus' )
			],
			[
				'name'		=> 'show',
				'type'		=> 'switcher',
				'title'		=> esc_html__( 'Show form for admin?', 'codevz-plus' )
			],
			[
				'name'		=> 'redirect',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Redirect URL', 'codevz-plus' )
			],
			[
				'name'		=> 'gdpr',
				'type'		=> 'text',
				'title'		=> esc_html__( 'GDPR confirmation', 'codevz-plus' )
			],
			[
				'name'		=> 'gdpr_error',
				'type'		=> 'text',
				'title'		=> esc_html__( 'GDPR error', 'codevz-plus' )
			],
			[
				'name'		=> 'username',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Username', 'codevz-plus' )
			],
			[
				'name'		=> 'password',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Password', 'codevz-plus' )
			],
			[
				'name'		=> 'email',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Your email', 'codevz-plus' )
			],
			[
				'name'		=> 'e_or_p',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Email', 'codevz-plus' )
			],
			[
				'name'		=> 'login_btn',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Login button', 'codevz-plus' )
			],
			[
				'name'		=> 'register_btn',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Register button', 'codevz-plus' )
			],
			[
				'name'		=> 'pass_r_btn',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Password recovery button', 'codevz-plus' )
			],
			[
				'name'		=> 'login_t',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Custom login link', 'codevz-plus' )
			],
			[
				'name'		=> 'f_pass_t',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Forgot password link', 'codevz-plus' )
			],
			[
				'name'		=> 'register_t',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Regisration link', 'codevz-plus' )
			],
			[
				'name'		=> 'logout',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Logout', 'codevz-plus' )
			],
			[
				'name'  	=> 'sk_con',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'settings' 	=> [ 'background', 'border' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_con_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_con_mobile' ],
			[
				'name'  	=> 'sk_inputs',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Inputs', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Inputs', 'codevz-plus' ),
				'settings' 	=> [ 'color', 'text-align', 'font-size', 'background', 'border' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_inputs_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_inputs_mobile' ],
			[
				'name'  	=> 'sk_buttons',
				'hover'  	=> '_hover',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Buttons', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Buttons', 'codevz-plus' ),
				'settings' 	=> [ 'color', 'background', 'border' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_buttons_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_buttons_mobile' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_buttons_hover' ],
			[
				'name'  	=> 'sk_links',
				'hover'  	=> '_hover',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Links', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Links', 'codevz-plus' ),
				'settings' 	=> [ 'color', 'background', 'border' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_links_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_links_mobile' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_links_hover' ],
		];
	}
}


/**
 * Widget: Newsletter
 */
class Xtra_Widget_Newsletter extends WP_Widget {

	public function __construct() {
		parent::__construct( 
			false, 
			'- ' . esc_html__( 'Newsletter', 'codevz-plus' ), 
			[ 
				'customize_selective_refresh' => true, 
				'classname' => 'xtra-widget-newsletter' 
			]
		);
	}

	// Output
	public function widget( $args, $data ) {
		Codevz_Widget::output( 'cz_subscribe', $args, $data );
	}
	
	// Update
	public function update( $data, $new ) {
		Codevz_Widget::update( $this, $new );
	}

	// Settings
	public function form( $data ) {
		Codevz_Widget::settings( $this, $data );
	}

	// Widget fields
	public function fields() {
		return [
			[
				'name'		=> 'title',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Title', 'codevz-plus' ),
				'default' 	=> esc_html__( 'Newsletter', 'codevz-plus' )
			],
			[
				'name'		=> 'content',
				'type'		=> 'textarea',
				'title'		=> esc_html__( 'Description', 'codevz-plus' )
			],
			[
				'name'        => 'style',
				'type'        => 'select',
				'title'       => esc_html__( 'Style', 'codevz-plus' ),
				'options'	  => [
					'' 						=> esc_html__( 'Square', 'codevz-plus' ),
					'cz_subscribe_round' 	=> esc_html__( 'Round', 'codevz-plus' ),
					'cz_subscribe_round_2' 	=> esc_html__( 'Round', 'codevz-plus' ) . ' 2',
					'cz_subscribe_relative' => esc_html__( 'Square, Button next line', 'codevz-plus' ),
					'cz_subscribe_relative cz_subscribe_round' 	=> esc_html__( 'Round, Button next line', 'codevz-plus' ),
				],
				'default' 	=> 'cz_subscribe_relative'
			],
			[
				'name'        => 'position',
				'type'        => 'select',
				'title'       => esc_html__( 'Position', 'codevz-plus' ),
				'options'	  => [
					'' 			=> esc_html__( '~ Default ~', 'codevz-plus' ),
					'center' 	=> esc_html__( 'Center', 'codevz-plus' ),
					'right' 	=> esc_html__( 'Right', 'codevz-plus' ),
				]
			],
			[
				'name'        => 'btn_position',
				'type'        => 'select',
				'title'       => esc_html__( 'Button position', 'codevz-plus' ),
				'options'	  => [
					'' 							=> esc_html__( '~ Default ~', 'codevz-plus' ),
					'cz_subscribe_btn_center' 	=> esc_html__( 'Center', 'codevz-plus' ),
					'cz_subscribe_btn_right' 	=> esc_html__( 'Right', 'codevz-plus' ),
				]
			],
			[
				'name' 		=> 'action',
				'type' 		=> 'text',
				'title' 	=> esc_html__( 'Action URL', 'codevz-plus' ),
				'help' 		=> esc_html__( 'Mailchimp action or Google feedburner url', 'codevz-plus' ),
			],
			[
				'name' 		=> 'placeholder',
				'type' 		=> 'text',
				'title' 	=> esc_html__( 'Placeholder', 'codevz-plus' ),
				'default' 	=> esc_html__( 'Enter your email ...', 'codevz-plus' ),
			],
			[
				'name'        => 'input_attr',
				'type'        => 'select',
				'title'       => esc_html__( 'Type', 'codevz-plus' ),
				'options'	  => [
					'email'		=> 'email',
					'text'		=> 'text',
					'number'	=> 'number',
					'search'	=> 'search',
					'tel'		=> 'tel',
					'time'		=> 'time',
					'date'		=> 'date',
					'url'		=> 'url',
					'password'	=> 'password',
				]
			],
			[
				'name' 		=> 'name_attr',
				'type' 		=> 'text',
				'title' 	=> esc_html__( 'Name attribute', 'codevz-plus' ),
				'default' 	=> 'MERGE0',
				'help' 		=> esc_html__( 'This is useful for mailchip, You can get your form input name from mailchip', 'codevz-plus' )
			],
			[
				'name' 		=> 'btn_title',
				'type' 		=> 'text',
				'title' 	=> esc_html__( 'Button', 'codevz-plus' ),
				'default' 	=> esc_html__( 'Join Now', 'codevz-plus' ),
			],
			[
				'name'        => 'icon',
				'type'        => 'icon',
				'title'       => esc_html__( 'Button icon', 'codevz-plus' )
			],
			[
				'name'  	=> 'sk_overall',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'settings' 	=> [ 'background', 'border', 'box-shadow' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_overall_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_overall_mobile' ],
			[
				'name'  	=> 'sk_input',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Input', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Input', 'codevz-plus' ),
				'settings' 	=> [ 'text-align', 'color', 'background', 'border' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_input_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_input_mobile' ],
			[
				'name'  	=> 'sk_button',
				'hover'  	=> '_hover',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Button', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Button', 'codevz-plus' ),
				'settings' 	=> [ 'color', 'background', 'border', 'box-shadow' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_button_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_button_mobile' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_button_hover' ],
			[
				'name'  	=> 'sk_icon',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Button icon', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Button icon', 'codevz-plus' ),
				'settings' 	=> [ 'color', 'background', 'border' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_icon_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_icon_mobile' ],
		];
	}
}

/**
 *
 * Facebook widget
 *
 */
class CodevzFacebook extends WP_Widget {

	public function __construct() {
		parent::__construct( 
			false, 
			'- ' . esc_html__( 'Facebook', 'codevz-plus' ), 
			[ 
				'customize_selective_refresh' => true, 
				'classname' => 'codevz-widget-facebook' 
			]
		);
	}

	// Output
	public function widget( $args, $data ) {

		ob_start(); ?>

			<div id="fb-root"></div><div class="fb-page" data-href="<?php echo isset( $data['url'] ) ? esc_url( $data['url'] ) : ''; ?>" data-small-header="<?php echo isset( $data['head'] ) ? esc_attr( $data['head'] ) : ''; ?>" data-adapt-container-width="true" data-hide-cover="<?php echo isset( $data['cover'] ) ? esc_attr( $data['cover'] ) : ''; ?>" data-hide-cta="false" data-show-facepile="<?php echo isset( $data['faces'] ) ? esc_attr( $data['faces'] ) : ''; ?>" data-show-posts="<?php echo isset( $data['posts'] ) ? esc_attr( $data['posts'] ) : ''; ?>">
			</div><script>(function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(d.getElementById(id))return;js=d.createElement(s);js.id=id;js.src="//connect.facebook.net/en_US/sdk.js#xfbml=1&version=v2.5&appId=376512092550885";fjs.parentNode.insertBefore(js,fjs)}(document,'script','facebook-jssdk'));</script>

		<?php 

		echo empty( $data['url'] ) ? esc_html__( 'Please insert correct facebook url page.', 'codevz-plus' ) : '';

		Codevz_Widget::output( null, $args, $data, ob_get_clean() );

	}
	
	// Update
	public function update( $data, $new ) {
		Codevz_Widget::update( $this, $new );
	}

	// Settings
	public function form( $data ) {
		Codevz_Widget::settings( $this, $data );
	}

	public function fields() {
		return [
			[
				'name'		=> 'title',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Title', 'codevz-plus' ),
				'default' 	=> esc_html__( 'Like us on Facebook', 'codevz-plus' )
			],
			[
				'name'		=> 'url',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Facebook page URL', 'codevz-plus' ),
				'default' 	=> 'https://facebook.com/xtratheme'
			],
			[
				'name'		=> 'head',
				'type'		=> 'switcher',
				'title'		=> esc_html__( 'Show header?', 'codevz-plus' )
			],
			[
				'name'		=> 'posts',
				'type'		=> 'switcher',
				'title'		=> esc_html__( 'Show posts?', 'codevz-plus' ),
				'default'	=> true
			],
			[
				'name'		=> 'faces',
				'type'		=> 'switcher',
				'title'		=> esc_html__( 'Show faces?', 'codevz-plus' ),
				'default'	=> true
			],
			[
				'name'		=> 'cover',
				'type'		=> 'switcher',
				'title'		=> esc_html__( 'Hide cover?', 'codevz-plus' )
			],
		];
	}
}


/**
 *
 * Custom nav menu
 *
 */
class CodevzCustomMenuList extends WP_Widget {

	public function __construct() {
		parent::__construct( 
			false, 
			'- ' . esc_html__( 'Custom Nav Menu', 'codevz-plus' ), 
			[ 
				'customize_selective_refresh' => true, 
				'classname' => 'codevz-widget-custom-nav-menu' 
			]
		);
	}
	
	// Output
	public function widget( $args, $data ) {
		ob_start();

		$css = '';
		if ( ! empty( $args['widget_id'] ) ) {
			$id = $args['widget_id'];

			// Container
			if ( ! empty( $data['sk_container'] ) ) {
				$css .= '#' . $id . ' ul{' . Codevz_Plus::sk_inline_style( $data['sk_container'] ) . '}';
			}
			if ( ! empty( $data['sk_container_tablet'] ) ) {
				$css .= '@media screen and (max-width:768px){#' . $id . ' ul{' . Codevz_Plus::sk_inline_style( $data['sk_container_tablet'] ) . '}}';
			}
			if ( ! empty( $data['sk_container_mobile'] ) ) {
				$css .= '@media screen and (max-width:480px){#' . $id . ' ul{' . Codevz_Plus::sk_inline_style( $data['sk_container_mobile'] ) . '}}';
			}

			// Container
			if ( ! empty( $data['sk_menus'] ) ) {
				$css .= '#' . $id . ' a{' . Codevz_Plus::sk_inline_style( $data['sk_menus'] ) . '}';
			}
			if ( ! empty( $data['sk_menus_hover'] ) ) {
				$css .= '#' . $id . ' a:hover, #' . $id . ' .current_menu a{' . Codevz_Plus::sk_inline_style( $data['sk_menus_hover'] ) . '}';
			}
			if ( ! empty( $data['sk_menus_tablet'] ) ) {
				$css .= '@media screen and (max-width:768px){#' . $id . ' a{' . Codevz_Plus::sk_inline_style( $data['sk_menus_tablet'] ) . '}}';
			}
			if ( ! empty( $data['sk_menus_mobile'] ) ) {
				$css .= '@media screen and (max-width:480px){#' . $id . ' a{' . Codevz_Plus::sk_inline_style( $data['sk_menus_mobile'] ) . '}}';
			}
		}

		$style = empty( $data['style'] ) ? '' : 'codevz-widget-custom-menu-horizontal';

		echo '<div class="' . ( empty( $data['disable_default_styles'] ) ? 'codevz-widget-custom-menu' : '' ) . ' ' . esc_attr( $style ) . '"' . wp_kses_post( (string) Codevz_Plus::data_stlye( $css ) ) . '>';

		if ( empty( $data['menu'] ) ) {
			$data['menu'] = 'primary';
		}

		$menus = get_nav_menu_locations();
		$menu = get_term( $menus[ $data['menu'] ], 'nav_menu' );
		wp_nav_menu( array( 'menu' => ( isset( $menu->slug ) ? $menu->slug : $data['menu'] ) ) );
		
		echo '</div>';

		Codevz_Widget::output( null, $args, $data, ob_get_clean() );
	}
	
	// Update
	public function update( $data, $new ) {
		Codevz_Widget::update( $this, $new );
	}

	// Settings
	public function form( $data ) {
		Codevz_Widget::settings( $this, $data );
	}

	// Fields
	public function fields() {
		return [
			[
				'name'		=> 'title',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Title', 'codevz-plus' ),
				'default' 	=> esc_html__( 'Menu', 'codevz-plus' )
			],
			[
				'name'		=> 'menu',
				'type'		=> 'select',
				'title'		=> esc_html__( 'Menu', 'codevz-plus' ),
				'options' 	=> get_registered_nav_menus(),
				'default' 	=> 'primary'
			],
			[
				'name'		=> 'disable_default_styles',
				'type'		=> 'switcher',
				'title'		=> esc_html__( 'Disable default styles?', 'codevz-plus' ),
			],
			[
				'name'        => 'style',
				'type'        => 'select',
				'title'       => esc_html__( 'Style', 'codevz-plus' ),
				'options'	  => [
					'' 			=> esc_html__('Vertical', 'codevz-plus' ),
					'1' 		=> esc_html__('Horizontal', 'codevz-plus' )
				]
			],
			[
				'name'  	=> 'sk_container',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'settings' 	=> [ 'background', 'border', 'box-shadow', 'display' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_container_mobile' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_container_tablet' ],
			[
				'name'  	=> 'sk_menus',
				'hover'  	=> '_hover',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Links', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Links', 'codevz-plus' ),
				'settings' 	=> [ 'color', 'background', 'border', 'box-shadow' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_menus_mobile' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_menus_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_menus_hover' ],
		];
	}
}


/**
 *
 * Custom menu list widget [Group, New]
 * 
 */
class Codevz_Widget_Custom_Menu_List extends WP_Widget {

	public function __construct() {
		parent::__construct( 
			false, 
			'- ' . esc_html__( 'Custom menu list', 'codevz-plus' ), 
			[ 
				'customize_selective_refresh' => true, 
				'classname' => 'codevz-widget-custom-menu-2' 
			]
		);
	}

	// Output
	public function widget( $args, $data ) {
		ob_start();

		$id 			= Codevz_Plus::uniqid();
		$col 			= empty( $data['columns'] ) ? '' : $data['columns'];
		$col_mobile 	= empty( $data['two_col_mobile'] ) ? '' : ' codevz-custom-menu-two-col-mobile';
		$target 		= empty( $data['target_blank'] ) ? '' : ' target="_blank"';
		$icon_hover 	= empty( $data['sk_icons_hover'] ) ? '' : '.codevz-widget-custom-menu-2 .' . $id . ' a:hover i{' . Codevz_Plus::sk_inline_style( $data['sk_icons_hover'] ) . '}';
		$icon_css 		= empty( $data['sk_icons'] ) ? '' : '.codevz-widget-custom-menu-2 .' . $id . ' i{' . Codevz_Plus::sk_inline_style( $data['sk_icons'] ) . '}' . $icon_hover;
		$default_icon 	= empty( $data['default_icon'] ) ? '' : '<i class="' . $data['default_icon'] . ' mr8"></i>';

		echo '<div class="' . esc_attr( $id . $col_mobile ) . ' clr"' . do_shortcode( (string) Codevz_Plus::data_stlye( $icon_css ) ) . '>';

		$items = isset( $data['items'] ) ? $data['items'] : [];
		$items = json_decode( wp_json_encode( $items ), true );

		$i = 1;

		echo '<div class="clr">';
		foreach( $items as $item ) {
			$title = empty( $item['title'] ) ? '' : $item['title'];
			$icon = empty( $item['icon'] ) ? $default_icon : '<i class="' . $item['icon'] . ' mr8" aria-hidden="true"></i>';
			$link = empty( $item['link'] ) ? '' : $item['link'];

			echo '<div' . ( $col ? ' class="' . esc_attr( $col ) . '"' : '' ) . '><a href="' . esc_attr( $link ) . '"' . do_shortcode( (string) $target ) . '>' . do_shortcode( $icon . $title ) . '</a></div>';
			if ( ( $col === 'col s6' && $i % 2 === 0 ) || ( $col === 'col s4' && $i % 3 === 0 ) ) {
				echo '</div><div class="clr">';
			}

			$i++;
		}
		echo '</div>';

		echo '</div>';

		Codevz_Widget::output( null, $args, $data, ob_get_clean() );
	}
	
	// Update
	public function update( $data, $new ) {
		Codevz_Widget::update( $this, $new );
	}

	// Settings
	public function form( $data ) {
		Codevz_Widget::settings( $this, $data );
	}

	// Widget fields
	public function fields() {
		return [
			[
				'name'		=> 'title',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Title', 'codevz-plus' ),
				'default' 	=> esc_html__( 'Menu', 'codevz-plus' )
			],
			[
				'name'            => 'items',
				'type'            => 'group',
				'title' 		  => '',
				'button_title'    => esc_html__( 'Add', 'codevz-plus' ),
				'fields'          => [
					[
						'id'          => 'title',
						'type'        => 'text',
						'title'       => esc_html__( 'Title', 'codevz-plus' ),
						'default' 	  => esc_html__( 'Title', 'codevz-plus' )
					],
					[
						'id'          => 'icon',
						'type'        => 'icon',
						'title'       => esc_html__('Icon', 'codevz-plus' )
					],
					[
						'id'          => 'link',
						'type'        => 'text',
						'title'       => esc_html__('Link', 'codevz-plus' )
					],
				],
			],
			[
				'name'        => 'default_icon',
				'type'        => 'icon',
				'title'       => esc_html__('Default icon', 'codevz-plus' )
			],
			[
				'name'  	=> 'sk_icons',
				'hover'  	=> '_hover',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Icons', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Icons', 'codevz-plus' ),
				'settings' 	=> [ 'color', 'background' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_icons_hover' ],
			[
				'name'        => 'columns',
				'type'        => 'select',
				'title'       => esc_html__( 'Columns', 'codevz-plus' ),
				'options'	  => [
					'' 				=> '1 ' . esc_html__('Column', 'codevz-plus' ),
					'col s6' 		=> '2 ' . esc_html__('Columns', 'codevz-plus' ),
					'col s4' 		=> '3 ' . esc_html__('Columns', 'codevz-plus' )
				]
			],
			[
				'name'        => 'two_col_mobile',
				'type'        => 'switcher',
				'title'       => esc_html__( 'Two columns on mobile?', 'codevz-plus' )
			],
			[
				'name'		=> 'target_blank',
				'type'		=> 'switcher',
				'title'		=> esc_html__( 'New tab?', 'codevz-plus' )
			],
		];
	}
}


/**
 *
 * Custom menu list widget [Deprecated]
 * 
 */
class CodevzCustomMenuList2 extends WP_Widget {

	private static $count = 18;

	public function __construct() {
		parent::__construct( 
			false, 
			'- ' . esc_html__( 'Custom menu [Deprecated]', 'codevz-plus' ), 
			[ 
				'customize_selective_refresh' => true, 
				'classname' => 'codevz-widget-custom-menu-2-old' 
			]
		);
	}
	
	// Output
	public function widget( $args, $data ) {
		ob_start();

		$col = empty( $data['two_col'] ) ? '' : 'col s6';
		$target = empty( $data['target_blank'] ) ? '' : ' target="_blank"';
		$icon = empty( $data['menus_icon'] ) ? '' : '<i class="' . $data['menus_icon'] . ' mr8"></i>';

		echo '<div class="clr">';
		for( $i = 1; $i < self::$count; $i++ ) {
			if ( ! empty( $data[ 'title_' . $i ] ) && ! empty( $data[ 'link_' . $i ] ) ) {
				echo '<div class="' . esc_attr( $col ) . '"><a href="' . esc_attr( $data[ 'link_' . $i ] ) . '"' . wp_kses_post( (string) $target ) . '>' . do_shortcode( $icon . $data[ 'title_' . $i ] ) . '</a></div>';
				if ( $col && $i % 2 === 0 ) {
					echo '</div><div class="clr">';
				}
			}
		}
		echo '</div>';

		Codevz_Widget::output( 0, $args, $data, ob_get_clean() );
	}
	
	// Update
	public function update( $data, $new ) {
		Codevz_Widget::update( $this, $new );
	}

	// Settings
	public function form( $data ) {
		Codevz_Widget::settings( $this, $data );
	}

	// Fields
	public function fields() {
		$fields = [
			[
				'name'		=> 'title',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Title', 'codevz-plus' ),
				'default' 	=> esc_html__( 'Menu', 'codevz-plus' )
			],
			[
				'name'		=> 'two_col',
				'type'		=> 'switcher',
				'title'		=> esc_html__( 'Two columns?', 'codevz-plus' )
			],
			[
				'name'		=> 'target_blank',
				'type'		=> 'switcher',
				'title'		=> esc_html__( 'New tab?', 'codevz-plus' )
			],
			[
				'name'		=> 'menus_icon',
				'type'		=> 'icon',
				'title'		=> esc_html__( 'Icon', 'codevz-plus' )
			],
		];

		for( $i = 1; $i < self::$count; $i++ ) {
			$fields[] = [
				'name'		=> 'title_' . $i,
				'type'		=> 'text',
				'title'		=> esc_html__( 'Title', 'codevz-plus' ) . ' ' . $i
			];
			$fields[] = [
				'name'		=> 'link_' . $i,
				'type'		=> 'text',
				'title'		=> esc_html__( 'Link', 'codevz-plus' ) . ' ' . $i
			];
		}

		return $fields;
	}
}

/**
 *
 * Widget: Unboxed content
 * 
 */
class Codevz_Widget_Unboxed extends WP_Widget {

	public function __construct() {
		parent::__construct( 
			false, 
			'- ' . esc_html__( 'Unboxed', 'codevz-plus' ), 
			[ 
				'customize_selective_refresh' => true, 
				'classname' => 'codevz-widget-unboxed' 
			]
		);
	}
	
	// Output
	public function widget( $args, $data ) {

		echo '<div class="codevz-widget-unboxed mb30">' . do_shortcode( $data['content'] ) . '</div>';

	}
	
	// Update
	public function update( $data, $new ) {
		Codevz_Widget::update( $this, $new );
	}

	// Settings
	public function form( $data ) {
		Codevz_Widget::settings( $this, $data );
	}

	// Fields
	public function fields() {
		$fields = [
			[
				'name'		=> 'content',
				'type'		=> 'textarea',
				'title'		=> esc_html__( 'Content', 'codevz-plus' )
			],
		];

		return $fields;
	}

}

/**
 *
 * Widget: Codevz Gallery
 * 
 */
class Codevz_Widget_Gallery extends WP_Widget {

	public function __construct() {
		parent::__construct( 
			false, 
			'- ' . esc_html__( 'Gallery', 'codevz-plus' ), 
			[ 
				'customize_selective_refresh' => true, 
				'classname' => 'codevz-widget-gallery' 
			]
		);
	}

	// Output.
	public function widget( $args, $data ) {

		if ( empty( $data['gap'] ) ) {
			$data['gap'] = '10px';
		}
		$data['arrows_position'] = 'arrows_bc';

		// Fix group.
		if ( isset( $data['gallery2'] ) && is_array( $data['gallery2'] ) ) {
			$data['gallery2'] = urlencode( wp_json_encode( json_decode( wp_json_encode( $data['gallery2'] ), true ) ) );
		}

		Codevz_Widget::output( 'cz_gallery', $args, $data );
	}
	
	// Update
	public function update( $data, $new ) {
		Codevz_Widget::update( $this, $new );
	}

	// Settings
	public function form( $data ) {
		Codevz_Widget::settings( $this, $data );
	}

	// Widget fields
	public function fields() {
		return [
			[
				'name'		=> 'title',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Title', 'codevz-plus' ),
				'default' 	=> esc_html__( 'Gallery', 'codevz-plus' )
			],
			[
				'name'        => 'type',
				'type'        => 'select',
				'title'       => esc_html__( 'Type', 'codevz-plus' ),
				'options'	  => [
					'gallery' 		=> esc_html__('Photo Gallery', 'codevz-plus' ),
					'gallery2' 		=> esc_html__('Linkable Gallery', 'codevz-plus' ),
				],
				'default' 	  => 'gallery',
				'attributes' 	=> [ 'data-depend-id' => 'type' ]
			],
			[
				'name'            => 'gallery2',
				'type'            => 'group',
				'title'       	  => esc_html__( 'Linkable Gallery', 'codevz-plus' ),
				'button_title'    => esc_html__( 'Images', 'codevz-plus' ),
				'fields'          => [
					[
						'id'          => 'title',
						'type'        => 'text',
						'title'       => esc_html__( 'Title', 'codevz-plus' )
					],
					[
						'id'          => 'image',
						'type'        => 'image',
						'title'       => esc_html__( 'Image', 'codevz-plus' )
					],
					[
						'id'          => 'link',
						'type'        => 'text',
						'title'       => esc_html__( 'Link', 'codevz-plus' )
					],
				]
			],
			[
				'name'		=> 'images',
				'type'		=> 'gallery',
				'title'		=> esc_html__( 'Photo Gallery', 'codevz-plus' )
			],
			[
				'name'        => 'insta_size',
				'type'        => 'select',
				'title'       => esc_html__( 'Size', 'codevz-plus' ),
				'options'	  => [
					'thumbnail' 	=> esc_html__('Thumbnail', 'codevz-plus' ),
					'large' 		=> esc_html__('Medium', 'codevz-plus' ),
					'original' 		=> esc_html__('Large', 'codevz-plus' )
				],
				'default' 	  => '72',
				'dependency' => [ 'type', '==', 'instagram' ]
			],
			[
				'name'        => 'layout',
				'type'        => 'image_select',
				'title'       => esc_html__( 'Layout', 'codevz-plus' ),
				'options'	  => [
					'cz_grid_c2' 			=> Codevz_Plus::$url . 'assets/img/gallery_4.png',
					'cz_grid_c3' 			=> Codevz_Plus::$url . 'assets/img/gallery_5.png',
					'cz_grid_c1 cz_grid_l1' => Codevz_Plus::$url . 'assets/img/gallery_2.png',
					'cz_metro_5 cz_grid_c3' => Codevz_Plus::$url . 'assets/img/gallery_24.png',
					'cz_metro_6 cz_grid_c3' => Codevz_Plus::$url . 'assets/img/gallery_25.png',
					'cz_grid_carousel' 		=> Codevz_Plus::$url . 'assets/img/gallery_30.png',
				],
				'default' 	  => 'cz_grid_c3',
				'attributes' 	=> [ 'data-depend-id' => 'layout' ]
			],
			[
				'name'		=> 'hover',
				'type'		=> 'select',
				'title'		=> esc_html__( 'Hover', 'codevz-plus' ),
				'options'	=> array_flip(
					[
						esc_html__( 'No hover details', 'codevz-plus' ) 		=> 'cz_grid_1_no_hover',
						esc_html__( 'Only icon on hover', 'codevz-plus' ) 	=> 'cz_grid_1_no_title cz_grid_1_no_desc',
					]
				),
				'default' 	=> ''
			],
			[
				'name'		=> 'slidestoshow',
				'type'		=> 'slider',
				'options' 	=> [ 'unit' => '', 'step' => 1, 'min' => 1, 'max' => 10 ],
				'title'		=> esc_html__( 'Slides to show', 'codevz-plus' ),
				'default'   => '1'
			],
			[
				'name'		=> 'gap',
				'type'		=> 'slider',
				'title'		=> esc_html__( 'Images Gap', 'codevz-plus' )
			],
			[
				'name'		=> 'icon',
				'type'		=> 'icon',
				'title'		=> esc_html__( 'Icon', 'codevz-plus' ),
				'default'	=> 'fa czico-125-add-song'
			],
			[
				'name'  	=> 'sk_img',
				'hover'  	=> '_hover',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Images', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Images', 'codevz-plus' ),
				'settings' 	=> [ 'background', 'border' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_img_hover' ],
			[
				'name'  	=> 'sk_overlay',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Overlay', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Overlay', 'codevz-plus' ),
				'settings' 	=> [ 'background' ]
			],
			[
				'name'  	=> 'sk_icon',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Icon', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Icon', 'codevz-plus' ),
				'settings' 	=> [ 'color', 'background', 'border' ]
			],
			[
				'name'        => 'two_columns_on_mobile',
				'type'        => 'switcher',
				'title'       => esc_html__( 'Two columns on mobile?', 'codevz-plus' )
			],
		];
	}
}

/**
 * Widget: Codevz Posts
 */
class Codevz_Widget_Posts_Grid extends WP_Widget {

	public function __construct() {

		parent::__construct( 
			false, 
			'- ' . esc_html__( 'Posts & Carousel', 'codevz-plus' ), 
			[ 
				'customize_selective_refresh' => true, 
				'classname' => 'codevz-widget-posts' 
			]
		);

	}

	// Output.
	public function widget( $args, $data ) {

		// Default.
		if ( empty( $data['gap'] ) ) {
			$data['gap'] = '10px';
		}

		$data['arrows_position'] = 'arrows_blr';
		$data['dots_position'] = 'dots_bc';
		$data['dots_style'] = 'dots_circle';
		$data['slidestoshow_tablet'] = '1';
		$data['slidestoshow_mobile'] = '1';
		$data['infinite'] = 'true';

		$data['subtitles'] = '%5B%7B%22t%22%3A%22date%22%2C%22i%22%3A%22fa%20czico-108-small-calendar%22%7D%2C%7B%22t%22%3A%22author%22%2C%22i%22%3A%22fa%20czico-100-user-1%22%7D%2C%7B%22t%22%3A%22comments%22%2C%22r%22%3A%22cz_post_data_r%22%2C%22i%22%3A%22far%20fa-comment-alt%22%7D%5D';
		$data['sk_meta'] = 'margin-right:20px;margin-bottom:10px;margin-left:20px;';

		Codevz_Widget::output( 'cz_posts', $args, $data );
	}

	// Update
	public function update( $data, $new ) {
		Codevz_Widget::update( $this, $new );
	}

	// Settings
	public function form( $data ) {
		Codevz_Widget::settings( $this, $data );
	}

	// Widget fields
	public function fields() {

		return [
			[
				'name'		=> 'title',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Title', 'codevz-plus' ),
				'default' 	=> esc_html__( 'Latest posts', 'codevz-plus' )
			],
			[
				'name'        => 'layout',
				'type'        => 'image_select',
				'title'       => esc_html__( 'Layout', 'codevz-plus' ),
				'options'	  => [
						'cz_grid_c1 cz_grid_l1'		=> Codevz_Plus::$url . 'assets/img/gallery_2.png',
						'cz_grid_c2 cz_grid_l2'		=> Codevz_Plus::$url . 'assets/img/gallery_3.png',
						'cz_grid_carousel'			=> Codevz_Plus::$url . 'assets/img/gallery_30.png',
						'cz_posts_list_4'			=> Codevz_Plus::$url . 'assets/img/posts_list_4.png',
				],
				'default' 	  => 'cz_grid_c3',
				'attributes' 	=> [ 'data-depend-id' => 'layout' ]
			],
			[
				'name'		=> 'custom_size',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Images size', 'codevz-plus' ),
				'help' 		=> 'e.g: thumbnail, medium, large, full'
			],
			[
				'name'		=> 'posts_per_page',
				'type'		=> 'slider',
				'title'		=> esc_html__( 'Posts count', 'codevz-plus' ),
				'options' 	=> [ 'unit' => '', 'step' => 1, 'min' => 1, 'max' => 30 ]
			],
			[
				'name'		=> 'slidestoshow',
				'type'		=> 'slider',
				'options' 	=> [ 'unit' => '', 'step' => 1, 'min' => 1, 'max' => 10 ],
				'title'		=> esc_html__( 'Slides to show', 'codevz-plus' ),
				'default'   => '1'
			],
			[
				'name'		=> 'gap',
				'type'		=> 'slider',
				'title'		=> esc_html__( 'Posts Gap', 'codevz-plus' )
			],
			[
				'name'  	=> 'post_type',
				'type'  	=> 'text',
				'title' 	=> esc_html__( 'Post type(s)', 'codevz-plus' )
			],
			[
				'name'  	=> 'cat',
				'type'  	=> 'text',
				'title' 	=> esc_html__( 'Category ID', 'codevz-plus' )
			],
			[
				'name'  	=> 'single_line_title',
				'type'  	=> 'switcher',
				'title' 	=> esc_html__( 'Single line title', 'codevz-plus' )
			],
			[
				'name'		=> 'icon',
				'type'		=> 'icon',
				'title'		=> esc_html__( 'Icon', 'codevz-plus' )
			],
			[
				'name'		=> 'hover',
				'type'		=> 'select',
				'title'		=> esc_html__( 'Posts details', 'codevz-plus' ),
				'options'	=> array_flip(
					[
						esc_html__( 'No hover details', 'codevz-plus' ) 									=> 'cz_grid_1_no_hover',
						esc_html__( 'Only icon on hover', 'codevz-plus' ) 								=> 'cz_grid_1_no_title cz_grid_1_no_desc',
						esc_html__( 'Icon & Title on hover', 'codevz-plus' ) 							=> 'cz_grid_1_no_desc',
						esc_html__( 'Icon & Title & Meta on hover', 'codevz-plus' ) 						=> 'cz_grid_1_yes_all',
						esc_html__( 'Title on hover', 'codevz-plus' ) 									=> 'cz_grid_1_no_icon cz_grid_1_no_desc',
						esc_html__( 'Title & Meta on hover', 'codevz-plus' ) 							=> 'cz_grid_1_no_icon',
						esc_html__( 'Title & Excerpt on hover', 'codevz-plus' ) 							=> 'cz_grid_1_no_icon cz_grid_1_has_excerpt cz_grid_1_no_desc',
						esc_html__( 'Title & Meta & Excerpt on hover', 'codevz-plus' ) 					=> 'cz_grid_1_no_icon cz_grid_1_has_excerpt',
						esc_html__( 'No hover details, Title & Meta after Image', 'codevz-plus' ) 		=> 'cz_grid_1_title_sub_after cz_grid_1_no_hover',
						esc_html__( 'Icon on hover, Title & Meta after Image', 'codevz-plus' ) 			=> 'cz_grid_1_title_sub_after',
						esc_html__( 'Icon on hover, Title & Meta & Excerpt after Image', 'codevz-plus' ) => 'cz_grid_1_title_sub_after cz_grid_1_has_excerpt',
						esc_html__( 'No Icon, Title & Meta & Excerpt after Image', 'codevz-plus' ) 		=> 'cz_grid_1_title_sub_after cz_grid_1_has_excerpt cz_grid_1_no_icon',
						esc_html__( 'Meta on image, Title after image', 'codevz-plus' ) 					=> 'cz_grid_1_title_sub_after cz_grid_1_subtitle_on_img',
						esc_html__( 'Meta on image, Title & Excerpt after image', 'codevz-plus' ) 		=> 'cz_grid_1_title_sub_after cz_grid_1_has_excerpt cz_grid_1_subtitle_on_img',
						esc_html__( 'No image, Title & Meta', 'codevz-plus' ) 							=> 'cz_grid_1_title_sub_after cz_grid_1_no_image',
						esc_html__( 'No image, Title & Meta & Excerpt', 'codevz-plus' ) 					=> 'cz_grid_1_title_sub_after cz_grid_1_has_excerpt cz_grid_1_no_image',
					]
				),
				'default' 	=> 'cz_grid_1_no_icon'
			],
			[
				'name'		=> 'hover_vis',
				'type'		=> 'select',
				'title'		=> esc_html__( 'Hover visibility?', 'codevz-plus' ),
				'options'	=> array_flip(
					[
						esc_html__( 'Show overlay on hover', 'codevz-plus' ) 	=> '',
						esc_html__( 'Hide overlay on hover', 'codevz-plus' ) 	=> 'cz_grid_1_hide_on_hover',
						esc_html__( 'Always show overlay', 'codevz-plus' ) 		=> 'cz_grid_1_always_show',
					]
				),
				'default' 	=> 'cz_grid_1_no_icon'
			],
			[
				'name'  	=> 'sk_overlay',
				'hover'  	=> '_hover',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Overlay', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Overlay', 'codevz-plus' ),
				'settings' 	=> [ 'background' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_overlay_hover' ],
			[
				'name'  	=> 'sk_icon',
				'hover'  	=> '_hover',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Icons', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Icons', 'codevz-plus' ),
				'settings' 	=> [ 'background' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_icon_hover' ],
			[
				'name'  	=> 'sk_content',
				'hover'  	=> '_hover',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Content', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Content', 'codevz-plus' ),
				'settings' 	=> [ 'background' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_content_hover' ],
			[
				'name'  	=> 'sk_title',
				'hover'  	=> '_hover',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Title', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Title', 'codevz-plus' ),
				'settings' 	=> [ 'background' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_title_hover' ],
			[
				'name'  	=> 'sk_dots',
				'hover'  	=> '_hover',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Carousel Dots', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Carousel Dots', 'codevz-plus' ),
				'settings' 	=> [ 'background' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_dots_hover' ],
			[
				'name'  	=> 'sk_prev_icon',
				'hover'  	=> '_hover',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Carousel prev icon', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Carousel prev icon', 'codevz-plus' ),
				'settings' 	=> [ 'color', 'background' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_prev_icon_hover' ],
			[
				'name'  	=> 'sk_next_icon',
				'hover'  	=> '_hover',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Carousel next icon', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Carousel next icon', 'codevz-plus' ),
				'settings' 	=> [ 'color', 'background' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_next_icon_hover' ],
		];

	}

}


/**
 * Widget: Logo, Text, Social
 */
class Codevz_Widget_About extends WP_Widget {

	public function __construct() {
		parent::__construct( 
			false, 
			'- ' . esc_html__( 'About', 'codevz-plus' ), 
			[ 
				'customize_selective_refresh' => true, 
				'classname' => 'codevz-widget-about' 
			]
		);
	}

	// Output
	public function widget( $args, $data ) {

		$widget_id = 'codevz-widget-' . wp_rand( 99, 999 );

		ob_start();
		echo '<div class="' . esc_attr( $widget_id ) . ' ' . ( isset( $data['position'] ) ? esc_attr( $data['position'] ) : '' ) . '">';

		// Logo
		if ( ! empty( $data['logo'] ) ) {

			$lw = empty( $data[ 'logo_size' ] ) ? 'auto' : (integer) $data[ 'logo_size' ];
			$lh = 'auto';

			if ( empty( $lp ) ) {
				$lp = 'px';
			}

			$data['sk_logo'] .= $lw ? 'width:' . esc_attr( $lw . $lp ) . ';' : '';

			echo '<img loading="lazy" class="codevz-widget-about-logo mb30" src="' . esc_attr( $data['logo'] ) . '" width="' . esc_attr( $lw ) . '" height="' . esc_attr( $lh ) . '" alt="Logo" />';
		}

		$about_css = '';

		if ( ! empty( $data['sk_logo'] ) ) {
		    $about_css .= '.' . $widget_id . ' .codevz-widget-about-logo {' . Codevz_Plus::sk_inline_style( $data['sk_logo'] ) . '}';
		}

		if ( ! empty( $data['sk_logo_tablet'] ) ) {
		    $about_css .= '@media (max-width: 768px) {.' . $widget_id . ' .codevz-widget-about-logo {' . Codevz_Plus::sk_inline_style( $data['sk_logo_tablet'] ) . '}}';
		}

		if ( ! empty( $data['sk_logo_mobile'] ) ) {
		    $about_css .= '@media (max-width: 480px) {.' . $widget_id . ' .codevz-widget-about-logo {' . Codevz_Plus::sk_inline_style( $data['sk_logo_mobile'] ) . '}}';
		}

		if ( ! empty( $data['sk_content'] ) ) {
		    $about_css .= '.' . $widget_id . ' .codevz-widget-about-content {' . Codevz_Plus::sk_inline_style( $data['sk_content'] ) . '}';
		}

		if ( ! empty( $data['sk_content_tablet'] ) ) {
		    $about_css .= '@media (max-width: 768px) {.' . $widget_id . ' .codevz-widget-about-content {' . Codevz_Plus::sk_inline_style( $data['sk_content_tablet'] ) . '}}';
		}

		if ( ! empty( $data['sk_content_mobile'] ) ) {
		    $about_css .= '@media (max-width: 480px) {.' . $widget_id . ' .codevz-widget-about-content {' . Codevz_Plus::sk_inline_style( $data['sk_content_mobile'] ) . '}}';
		}

		// Content.
		echo '<div class="codevz-widget-about-content mb30" data-cz-style="' . wp_kses_post( (string) $about_css ) . '">';
		echo empty( $data['content'] ) ? '' : do_shortcode( $data['content'] );
		echo '</div>';

		// Button
		if ( ! empty( $data['button'] ) ) {
			$link = isset( $data['button_link'] ) ? $data['button_link'] : '';
			echo do_shortcode( '[cz_button class="mb30" link="url:' . wp_kses_post( (string) urlencode( $link ) ) . '|||" title="' . wp_kses_post( (string) $data['button'] ) . '" sk_button="' . wp_kses_post( (string) $data['sk_button'] ) . '" sk_button_tablet="' . wp_kses_post( (string) ( empty( $data['sk_button_tablet'] ) ? '' : $data['sk_button_tablet'] ) ) . '" sk_button_mobile="' . wp_kses_post( (string) ( empty( $data['sk_button_mobile'] ) ? '' : $data['sk_button_mobile'] ) ) . '" sk_hover="' . wp_kses_post( (string) $data['sk_button_hover'] ) . '"]' );
		}

		// Social icons
		if ( ! empty( $data['social'] ) ) {
			$out = '[cz_social_icons ';

			if ( isset( $data['social'] ) && is_array( $data['social'] ) ) {
				$data['social'] = json_decode( wp_json_encode( $data['social'] ), true );
				$out .= 'social="' . urlencode( wp_json_encode( $data['social'] ) ) . '" ';
			}

			$out .= 'position="" ';

			if ( isset( $data['fx'] ) ) {
				$out .= 'fx="' . $data['fx'] . '" ';
			}

			if ( isset( $data['color_mode'] ) ) {
				$out .= 'color_mode="' . $data['color_mode'] . '" ';
			}

			if ( isset( $data['sk_icons'] ) ) {
				$out .= 'sk_icons="' . Codevz_Plus::sk_inline_style( $data['sk_icons'] ) . '" ';
			}

			if ( isset( $data['sk_icons_tablet'] ) ) {
				$out .= 'sk_icons_tablet="' . Codevz_Plus::sk_inline_style( $data['sk_icons_tablet'] ) . '" ';
			}

			if ( isset( $data['sk_icons_mobile'] ) ) {
				$out .= 'sk_icons_mobile="' . Codevz_Plus::sk_inline_style( $data['sk_icons_mobile'] ) . '" ';
			}

			if ( isset( $data['sk_icons_hover'] ) ) {
				$out .= 'sk_hover="' . Codevz_Plus::sk_inline_style( $data['sk_icons_hover'] ) . '" ';
			}

			if ( isset( $data['sk_inner_icon'] ) ) {
				$out .= 'sk_inner_icon="' . Codevz_Plus::sk_inline_style( $data['sk_inner_icon'] ) . '" ';
			}

			if ( isset( $data['sk_inner_icon_tablet'] ) ) {
				$out .= 'sk_inner_icon_tablet="' . Codevz_Plus::sk_inline_style( $data['sk_inner_icon_tablet'] ) . '" ';
			}

			if ( isset( $data['sk_inner_icon_mobile'] ) ) {
				$out .= 'sk_inner_icon_mobile="' . Codevz_Plus::sk_inline_style( $data['sk_inner_icon_mobile'] ) . '" ';
			}

			if ( isset( $data['sk_inner_icon_hover'] ) ) {
				$out .= 'sk_inner_icon_hover="' . Codevz_Plus::sk_inline_style( $data['sk_inner_icon_hover'] ) . '" ';
			}

			if ( isset( $data['inline_title'] ) ) {
				$out .= 'inline_title="1" ';
			}

			if ( isset( $data['sk_title'] ) ) {
				$out .= 'sk_title="' . $data['sk_title'] . '" ';
			}

			if ( isset( $data['sk_title_mobile'] ) ) {
				$out .= 'sk_title_mobile="' . $data['sk_title_mobile'] . '" ';
			}

			if ( isset( $data['sk_title_tablet'] ) ) {
				$out .= 'sk_title_tablet="' . $data['sk_title_tablet'] . '" ';
			}

			if ( isset( $data['sk_title_hover'] ) ) {
				$out .= 'sk_title_hover="' . $data['sk_title_hover'] . '" ';
			}

			if ( isset( $data['tooltip'] ) ) {
				$out .= 'tooltip="' . $data['tooltip'] . '" ';
			}

			$out .= '[/cz_social_icons]';

			echo do_shortcode( $out );

		}

		echo '</div>';

		Codevz_Widget::output( null, $args, $data, ob_get_clean() );

	}
	
	// Update
	public function update( $data, $new ) {
		Codevz_Widget::update( $this, $new );
	}

	// Settings
	public function form( $data ) {
		Codevz_Widget::settings( $this, $data );
	}

	// Fields
	public function fields() {
		$fields = [
			[
				'name'		=> 'title',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Title', 'codevz-plus' ),
				'default' 	=> esc_html__( 'About', 'codevz-plus' )
			],
			[
				'name'        => 'position',
				'type'        => 'select',
				'title'       => esc_html__('Position', 'codevz-plus' ),
				'options'	  => [
					'tal' 		=> esc_html__('Left', 'codevz-plus' ),
					'tac' 		=> esc_html__('Center', 'codevz-plus' ),
					'tar' 		=> esc_html__('Right', 'codevz-plus' ),
				]
			],
			[
				'name'		=> 'logo',
				'type'		=> 'upload',
				'title'		=> esc_html__( 'Logo', 'codevz-plus' ),
				'preview'	=> 1
			],
			[
				'name'		=> 'logo_size',
				'type'		=> 'slider',
				'options' 	=> array( 'unit' => 'px', 'step' => 1, 'min' => 50, 'max' => 400 ),
				'title'		=> esc_html__( 'Logo size', 'codevz-plus' )
			],
			[
				'name'		=> 'content',
				'type'		=> 'textarea',
				'title'		=> esc_html__( 'Content', 'codevz-plus' )
			],
			[
				'name'		=> 'button',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Button', 'codevz-plus' )
			],
			[
				'name'		=> 'button_link',
				'type'		=> 'text',
				'title'		=> esc_html__( 'Link', 'codevz-plus' )
			],
			[
				'name'            => 'social',
				'type'            => 'group',
				'title' 		  => '',
				'button_title'    => esc_html__( 'Icons', 'codevz-plus' ),
				'fields'          => [
					[
						'id'          => 'title',
						'type'        => 'text',
						'title'       => esc_html__('Title', 'codevz-plus' )
					],
					[
						'id'          => 'icon',
						'type'        => 'icon',
						'title'       => esc_html__('Icon', 'codevz-plus' )
					],
					[
						'id'          => 'link',
						'type'        => 'text',
						'title'       => esc_html__('Link', 'codevz-plus' )
					],
				]
			],
			[
				'name'        => 'fx',
				'type'        => 'select',
				'title'       => esc_html__('Icons Hover', 'codevz-plus' ),
				'options'	  => [
					'' 					=> esc_html__( '~ Default ~', 'codevz-plus' ),
					'cz_social_fx_0' 	 => esc_html__('Zoom In', 'codevz-plus' ),
					'cz_social_fx_1' 	 => esc_html__('Zoom Out', 'codevz-plus' ),
					'cz_social_fx_2' 	 => esc_html__('Bottom to Top', 'codevz-plus' ),
					'cz_social_fx_3' 	 => esc_html__('Top to Bottom', 'codevz-plus' ),
					'cz_social_fx_4' 	 => esc_html__('Left to Right', 'codevz-plus' ),
					'cz_social_fx_5' 	 => esc_html__('Right to Left', 'codevz-plus' ),
					'cz_social_fx_6' 	 => esc_html__('Rotate', 'codevz-plus' ),
					'cz_social_fx_7' 	 => esc_html__('Infinite Shake', 'codevz-plus' ),
					'cz_social_fx_8' 	 => esc_html__('Infinite Wink', 'codevz-plus' ),
					'cz_social_fx_9' 	 => esc_html__('Quick Bob', 'codevz-plus' ),
					'cz_social_fx_10' 	 => esc_html__('Flip Horizontal', 'codevz-plus' ),
					'cz_social_fx_11' 	 => esc_html__('Flip Vertical', 'codevz-plus' ),
				]
			],
			[
				'name'        => 'color_mode',
				'type'        => 'select',
				'title'       => esc_html__('Color Mode', 'codevz-plus' ),
				'options'	  => [
					'' 							=> esc_html__( '~ Default ~', 'codevz-plus' ),
					'cz_social_colored' 		=> esc_html__( 'Brand Colors', 'codevz-plus' ),
					'cz_social_colored_hover' 	=> esc_html__( 'Brand Colors on Hover', 'codevz-plus' ),
					'cz_social_colored_bg' 		=> esc_html__( 'Brand Background', 'codevz-plus' ),
					'cz_social_colored_bg_hover' => esc_html__( 'Brand Background on Hover', 'codevz-plus' ),
				]
			],
			[
				'name'        => 'tooltip',
				'type'        => 'select',
				'title'       => esc_html__('Tooltip', 'codevz-plus' ),
				'help' 		  => esc_html__( 'StyleKit located in Theme Options > General > Colors & Styles', 'codevz-plus' ),
				'options'	  => [
					'' 								=> esc_html__( '~ Default ~', 'codevz-plus' ),
					'cz_tooltip cz_tooltip_up' 		=> esc_html__('Up', 'codevz-plus' ),
					'cz_tooltip cz_tooltip_down' 	=> esc_html__('Down', 'codevz-plus' ),
					'cz_tooltip cz_tooltip_left' 	=> esc_html__('Left', 'codevz-plus' ),
					'cz_tooltip cz_tooltip_right' 	=> esc_html__('Right', 'codevz-plus' ),
				]
			],
			[
				'name'        => 'inline_title',
				'type'        => 'switcher',
				'title'       => esc_html__('Inline Title', 'codevz-plus' ),
			],
			[
				'name'  	=> 'sk_logo',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Logo', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Logo', 'codevz-plus' ),
				'settings' 	=> [ 'width', 'background', 'border' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_logo_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_logo_mobile' ],
			[
				'name'  	=> 'sk_content',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Content', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Content', 'codevz-plus' ),
				'settings' 	=> [ 'width', 'background', 'border' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_content_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_content_mobile' ],
			[
				'name'  	=> 'sk_button',
				'hover'  	=> '_hover',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Button', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Button', 'codevz-plus' ),
				'settings' 	=> [ 'width', 'background', 'border' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_button_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_button_mobile' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_button_hover' ],
			[
				'name'  	=> 'sk_icons',
				'hover'  	=> '_hover',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Icons', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Icons', 'codevz-plus' ),
				'settings' 	=> [ 'width', 'color', 'background', 'border' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_icons_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_icons_mobile' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_icons_hover' ],
			[
				'name'  	=> 'sk_inner_icon',
				'hover'  	=> '_hover',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Inner icons', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Inner icons', 'codevz-plus' ),
				'settings' 	=> [ 'width', 'color', 'background', 'border' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_inner_icon_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_inner_icon_mobile' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_inner_icon_hover' ],
			[
				'name'  	=> 'sk_title',
				'hover'  	=> '_hover',
				'type'  	=> 'cz_sk',
				'title' 	=> esc_html__( 'Social title', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Social title', 'codevz-plus' ),
				'settings' 	=> [ 'color', 'background', 'border' ]
			],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_title_tablet' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_title_mobile' ],
			[ 'type' => 'cz_sk_hidden', 'name' => 'sk_title_hover' ],
		];

		return $fields;
	}
}


/**
 *
 * Widget: Flickr
 * 
 */
class CodevzFlickr extends WP_Widget {

	public function __construct() {
		parent::__construct( 
			false, 
			'- ' . esc_html__( 'Flickr', 'codevz-plus' ), 
			[ 
				'customize_selective_refresh' => true, 
				'classname' => 'cz_flickr' 
			]
		);
	}
	
	public function form($data) {
		$defaults = array(
			'title' => 'Photostream',
			'id' => '7388060@N08',
			'type' => 'user',
			'number' => '9',
			'shorting' => 'latest',
		);
		$data = wp_parse_args( (array) $data, $defaults );
		
		$title_field = array(
			'echo'  => true,
			'id'    => $this->get_field_name('title'),
			'name'  => $this->get_field_name('title'),
			'type'  => 'text',
			'title' => esc_html__('Title', 'codevz-plus' )
		);
		codevz_add_field( $title_field, esc_attr( $data['title'] ) );

		$id_field = array(
			'echo'  => true,
			'id'    => $this->get_field_name('id'),
			'name'  => $this->get_field_name('id'),
			'type'  => 'text',
			'title' => esc_html__('ID', 'codevz-plus' )
		);
		codevz_add_field( $id_field, esc_attr( $data['id'] ) );

		$number_field = array(
			'echo'  => true,
			'id'    => $this->get_field_name('number'),
			'name'  => $this->get_field_name('number'),
			'type'  => 'text',
			'title' => esc_html__('Count', 'codevz-plus' )
		);
		codevz_add_field( $number_field, esc_attr( $data['number'] ) );

		$type_field = array(
			'echo'  => true,
			'id'    => $this->get_field_name('type'),
			'name'  => $this->get_field_name('type'),
			'type'  => 'select',
			'options' => array(
				'user' => esc_html__('User', 'codevz-plus' ),
				'group' => esc_html__('Group', 'codevz-plus' )
			),
			'title' => esc_html__('Type', 'codevz-plus' )
		);
		codevz_add_field( $type_field, esc_attr( $data['type'] ) );

		$shorting_field = array(
			'echo'  => true,
			'id'    => $this->get_field_name('shorting'),
			'name'  => $this->get_field_name('shorting'),
			'type'  => 'select',
			'options' => array(
				'latest' => esc_html__('Latest Photos', 'codevz-plus' ),
				'random' => esc_html__('Random', 'codevz-plus' )
			),
			'title' => esc_html__('Sorting', 'codevz-plus' )
		);
		codevz_add_field( $shorting_field, esc_attr( $data['shorting'] ) );
	}

	public function update( $new, $data ) {

		$data['title'] 		= wp_kses_post( (string) $new['title'] );
		$data['number'] 	= wp_kses_post( (string) $new['number'] );
		$data['id'] 		= wp_kses_post( (string) $new['id'] );
		$data['type'] 		= wp_kses_post( (string) $new['type'] );
		$data['shorting'] 	= wp_kses_post( (string) $new['shorting'] );

		return $data;

	}

	public function widget( $args, $data ) {

		$output = '';
		$tag = 'script';

		if ( $data['id'] ) {
			$output .= '<div class="flickr-widget clr">';
			$output .= "<" . esc_attr( $tag ) . " src='https://flickr.com/badge_code_v2.gne?count=" . esc_attr( $data['number'] ) . "&amp;display=" . esc_attr( $data['shorting'] ) . "&amp;&amp;layout=x&amp;source=" . esc_attr( $data['type'] ) . "&amp;" . esc_attr( $data['type'] ) . "=" . esc_attr( $data['id'] ) . "&amp;size=s'></" . esc_attr( $tag ) . ">";
			$output .= '</div>';
		}

		Codevz_Widget::output( null, $args, $data, $output );

	}
 
}


/**
 * Soundcloud
 */
if ( !class_exists( 'Codevz_Widget_Soundcloud' ) ) {

	class Codevz_Widget_Soundcloud extends WP_Widget {

		public function __construct() {
			parent::__construct( 
				false, 
				'- ' . esc_html__( 'Soundcloud', 'codevz-plus' ), 
				[ 
					'customize_selective_refresh' => true, 
					'classname' => 'cz_soundcloud' 
				]
			);
		}

		public function widget( $args, $data ) {

			$tag = 'iframe';
			$play = empty( $data['autoplay'] ) ? 'false' : 'true';

			$out = '<' . esc_attr( $tag ) . ' width="100%" height="166" scrolling="no" frameborder="no" src="//w.soundcloud.com/player/?url=' . esc_url( $data['url'] ) . '&amp;auto_play=' . esc_attr( $play ) . '&amp;show_artwork=true"></' . esc_attr( $tag ) . '>';

			Codevz_Widget::output( null, $args, $data, $out );

		}
		public function update( $new_instance, $old_instance ) {
			$data = $old_instance;
			$data['title'] 		= esc_html( $new_instance['title'] );
			$data['url'] 		= esc_url( $new_instance['url'] );
			$data['autoplay'] 	= esc_html( $new_instance['autoplay'] );
			
			return $data;
		}
		public function form( $data ) {

			$defaults = array( 
				'title' 	=> 'SoundCloud', 
				'url' 		=> 'https://soundcloud.com/almerchoy/pitbull-bon-bon', 
				'autoplay' 	=> ''  
			);
			$data = wp_parse_args( (array) $data, $defaults ); ?>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php echo esc_html__('Title', 'codevz-plus' ); ?></label>
				<input id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" value="<?php echo esc_attr( $data['title'] ); ?>" class="widefat" type="text" />
			</p>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'url' ) ); ?>"><?php echo esc_html__('URL', 'codevz-plus' ); ?></label>
				<input id="<?php echo esc_attr( $this->get_field_id( 'url' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'url' ) ); ?>" value="<?php echo esc_url( $data['url'] ); ?>" type="text" class="widefat" />
			</p>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'autoplay' ) ); ?>"><?php echo esc_html__('Autoplay?', 'codevz-plus' ); ?></label>
				<input id="<?php echo esc_attr( $this->get_field_id( 'autoplay' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'autoplay' ) ); ?>" value="true" <?php if( $data['autoplay'] ) echo 'checked="checked"'; ?> type="checkbox" />
			</p>
		<?php
		}

	}

}

/**
 * 
 * Subscribe
 * 
 */
if ( ! class_exists( 'CodevzSubscribe' ) ) {

	class CodevzSubscribe extends WP_Widget {

		public function __construct() {

			parent::__construct( 
				false, 
				'- ' . esc_html__( 'Feedburner', 'codevz-plus' ), 
				[ 
					'customize_selective_refresh' => true, 
					'classname' => 'cz_subscribe' 
				]
			);

		}

		public function form( $data ) {

			$data = wp_parse_args( (array) $data, array('title' => 'Subscribe to RSS Feeds', 'subscribe_text' => 'Get all latest content delivered to your email a few times a month.', 'feedid' => '', 'placeholder' => 'Your Email', 'icon' => 'fa fa-check') );
			
			$title_value = esc_attr( $data['title'] );
			$title_field = array(
				'echo' 	=> true,
				'id'    => $this->get_field_name('title'),
				'name'  => $this->get_field_name('title'),
				'type'  => 'text',
				'title' => esc_html__('Title', 'codevz-plus' )
			);
			codevz_add_field( $title_field, $title_value );

			$subscribe_text_value = esc_attr( $data['subscribe_text'] );
			$subscribe_text_field = array(
				'echo' 	=> true,
				'id'    => $this->get_field_name('subscribe_text'),
				'name'  => $this->get_field_name('subscribe_text'),
				'type'  => 'textarea',
				'title' => esc_html__('Description', 'codevz-plus' )
			);
			codevz_add_field( $subscribe_text_field, $subscribe_text_value );

			$icon_value = esc_attr( $data['icon'] );
			$icon_field = array(
				'echo' 	=> true,
				'id'    => $this->get_field_name('icon'),
				'name'  => $this->get_field_name('icon'),
				'type'  => 'icon',
				'title'	=> esc_html__('Icon', 'codevz-plus' ),
			);
			codevz_add_field( $icon_field, $icon_value );

			$placeholder_value = esc_attr( $data['placeholder'] );
			$placeholder_field = array(
				'echo' 	=> true,
				'id'    => $this->get_field_name('placeholder'),
				'name'  => $this->get_field_name('placeholder'),
				'type'  => 'text',
				'title' => esc_html__('Placeholder', 'codevz-plus' )
			);
			codevz_add_field( $placeholder_field, $placeholder_value );

			$feedid_value = esc_attr( $data['feedid'] );
			$feedid_field = array(
				'echo' 	=> true,
				'id'    => $this->get_field_name('feedid'),
				'name'  => $this->get_field_name('feedid'),
				'type'  => 'text',
				'title' => esc_html__('Feedburner ID or name', 'codevz-plus' )
			);
			codevz_add_field( $feedid_field, $feedid_value );
		}

		public function update( $new, $data ) {

			$data['title'] 			= wp_kses_post( (string) $new['title'] );
			$data['feedid'] 		= wp_kses_post( (string) $new['feedid'] );
			$data['icon'] 			= wp_kses_post( (string) $new['icon'] );
			$data['placeholder'] 	= wp_kses_post( (string) $new['placeholder'] );
			$data['subscribe_text'] = wp_kses_post( (string) $new['subscribe_text'] );

			return $data;

		}

		public function widget( $args, $data ) {

			ob_start();

			$out = '';

			?><p><?php echo wp_kses_post( (string) $data['subscribe_text'] ); ?></p>
			<form class="widget_rss_subscription clr" action="https://feedburner.google.com/fb/a/mailverify" method="post" target="popupwindow" onsubmit="window.open('//feedburner.google.com/fb/a/mailverify?uri=<?php echo esc_attr( $data['feedid'] ); ?>', 'popupwindow', 'scrollbars=yes,width=550,height=520');return true">
				<input type="text" placeholder="<?php echo esc_attr( $data['placeholder'] ) ?>" name="email" required />
				<input type="hidden" value="<?php echo esc_attr( $data['feedid'] ); ?>" name="uri"/>
				<input type="hidden" name="loc" value="en_US"/>
				<button type="submit" id="submit" value="Subscribe"><i class="<?php echo esc_attr( $data['icon'] ); ?>"></i></button>
			</form><?php

			$out = ob_get_clean();

			Codevz_Widget::output( null, $args, $data, $out );

		}

	}

}

/**
 * 
 * Simple ads
 * 
 */
if ( ! class_exists( 'CodevzSimpleAds' ) ) {

	class CodevzSimpleAds extends WP_Widget {

		public function __construct() {
			parent::__construct( 
				false, 
				'- ' . esc_html__( 'Simple Ads', 'codevz-plus' ), 
				[ 
					'customize_selective_refresh' => true, 
					'classname' => 'cz_simple_ads' 
				]
			);
		}
		
		public function widget( $args, $data ) {

			$link  = isset( $data['link'] ) ? $data['link'] : '';
			$img   = isset( $data['img'] )  ? $data['img'] : '';
			$title = isset( $data['title'] )? $data['title'] : '';

			$out = '<a href="'.esc_url( $link ).'" target="_blank"><img loading="lazy" src="'.esc_url( $img ).'" alt="' . esc_attr( $title ) . '" width="200" height="200" /></a>';

			$out .= isset( $data['custom'] ) ? $data['custom'] : '';

			Codevz_Widget::output( null, $args, $data, $out );

		}

		public function update($new,$old) {

			$data = $old;
			$data['title'] = esc_html( $new['title'] );
			$data['img'] = esc_url( $new['img'] );
			$data['link'] = esc_url( $new['link'] );
			$data['custom'] = $new['custom'];

			return $data;
		}
		 
		public function form($data) {

			$defaults = array('title' => '','link' => '','img' => '', 'custom' => '');
			$data = wp_parse_args( (array) $data, $defaults );

			codevz_add_field( array(
				'echo' 	=> true,
				'id'    => $this->get_field_name('title'),
				'name'  => $this->get_field_name('title'),
				'type'  => 'text',
				'title' => esc_html__('Title', 'codevz-plus' )
			), esc_attr( $data['title'] ) ); 

			codevz_add_field( array(
				'echo' 	=> true,
				'id'    => $this->get_field_name('img'),
				'name'  => $this->get_field_name('img'),
				'type'  => 'upload',
				'title' => esc_html__('Image', 'codevz-plus' )
			), esc_attr( $data['img'] ) );

			codevz_add_field( array(
				'echo' 	=> true,
				'id'    => $this->get_field_name('link'),
				'name'  => $this->get_field_name('link'),
				'type'  => 'text',
				'title' => esc_html__('Link', 'codevz-plus' )
			), esc_attr( $data['link'] ) );

			codevz_add_field( array(
				'echo' 		=> true,
				'id'    	=> $this->get_field_name('custom'),
				'name'  	=> $this->get_field_name('custom'),
				'type' 		=> Codevz_Plus::$is_free ? 'content' : 'textarea',
				'content'  	=> Codevz_Plus::pro_badge(),
				'sanitize' 	=> false,
				'title' 	=> esc_html__('Custom Ads', 'codevz-plus' )
			), $data['custom'] );

		}

	}

}

/**
 * 
 * Load page content
 * 
 */
if ( ! class_exists( 'CodevzPageContent' ) ) {

	class CodevzPageContent extends WP_Widget {

		public function __construct() {
			parent::__construct( 
				false, 
				'- ' . esc_html__( 'Page content', 'codevz-plus' ), 
				[ 
					'customize_selective_refresh' => true, 
					'classname' => 'cz_page_content_widget' 
				]
			);
		}
		
		public function widget( $args, $data ) {

			if ( ! empty( $data['id'] ) ) {

				$out = Codevz_Plus::get_page_as_element( esc_attr( $data['id'] ) );

				Codevz_Widget::output( null, $args, $data, $out );

			}
		}

		public function update( $new, $old ) {

			$data = $old;
			$data['title'] 	= esc_html( $new['title'] );
			$data['id'] 	= esc_html( $new['id'] );

			return $data;

		}
		 
		public function form( $data ) {

			$defaults = array( 'title' => '', 'id' => '' );
			$data = wp_parse_args( (array) $data, $defaults );

			codevz_add_field( array(
				'echo' 	=> true,
				'id'    => $this->get_field_name('title'),
				'name'  => $this->get_field_name('title'),
				'type'  => 'text',
				'title' => esc_html__('Title', 'codevz-plus' )
			), esc_attr( $data['title'] ) );

			codevz_add_field( array(
				'echo' 	=> true,
				'id'            => $this->get_field_name('id'),
				'name'  		=> $this->get_field_name('id'),
				'type'          => 'select',
				'title'         => esc_html__('Page', 'codevz-plus' ),
				'options'       => Codevz_Plus::$array_pages,
			), esc_attr( $data['id'] ) );

		}

	}

}

/**
 * 
 * Gallery
 * 
 */
if ( !class_exists( 'CodevzPortfolio' ) ) {

	class CodevzPortfolio extends WP_Widget {

		public function __construct() {
			parent::__construct( 
				false, 
				'- ' . esc_html__( 'Portfolio', 'codevz-plus' ), 
				[ 
					'customize_selective_refresh' => true, 
					'classname' => 'cz_portfolio_widget' 
				]
			);
		}

		public function widget( $args, $data ) {

			ob_start();
			$out = '';

			$post_type 		= isset( $data['post_type'] ) ? $data['post_type'] : 'portfolio';
			$gallery_order 	= isset( $data['gallery_order'] ) ? $data['gallery_order'] : 'DESC';

			$popular = new WP_Query( array(
				'post_type'		=> $post_type,
				'order'			=> $gallery_order,
				'showposts'		=> $data['posts_num']
			) );

			$columns = isset( $data['columns'] ) ? 'xtra-portfolio-widget-' . $data['columns'] : '';

		?>

		<div class="cd_gallery_in clr <?php echo esc_attr( $columns ); ?>">
			<?php while ( $popular->have_posts() ): $popular->the_post(); ?>
					<?php if ( has_post_thumbnail() ): ?>
						<a class="cdEffect noborder" href="<?php the_permalink(); ?>" title="<?php the_title(); ?>">
							<?php

								echo do_shortcode( Codevz_Plus::get_image( get_post_thumbnail_id( get_the_id() ), 'thumbnail' ) );

							?>
							<i class="fas fa-link"></i>
						</a>
					<?php endif; ?>
			<?php endwhile; wp_reset_query(); ?>
		</div>

		<?php
			$out = ob_get_clean();

			Codevz_Widget::output( null, $args, $data, $out );

		}
		
		public function update( $new, $old ) {

			$data = $old;
			$data['title'] = esc_html( $new['title'] );
			$data['gallery_order'] = esc_html( $new['gallery_order'] );
			$data['posts_num'] = esc_html( $new['posts_num'] );
			$data['columns'] = esc_html( $new['columns'] );

			return $data;
		}

		public function form($data) {
			$defaults = array(
				'title' 			=> 'Portfolio',
				'post_type' 		=> 'portfolio',
				'gallery_order' 	=> 'DESC',
				'posts_num' 		=> '9',
				'columns' 			=> '3'
			);
			$data = wp_parse_args( (array) $data, $defaults );
			
			$title_value = esc_attr( $data['title'] );
			$title_field = array(
				'echo' 	=> true,
				'id'    => $this->get_field_name('title'),
				'name'  => $this->get_field_name('title'),
				'type'  => 'text',
				'title' => esc_html__('Title', 'codevz-plus' )
			);
			codevz_add_field( $title_field, $title_value );
			
			$title_value = esc_attr( $data['post_type'] );
			$title_field = array(
				'echo' 	=> true,
				'id'    => $this->get_field_name( 'post_type' ),
				'name'  => $this->get_field_name( 'post_type' ),
				'type'  => 'text',
				'title' => esc_html__( 'Post type', 'codevz-plus' )
			);
			codevz_add_field( $title_field, $title_value );

			$posts_num_value = esc_attr( $data[ 'posts_num' ] );
			$posts_num_field = array(
				'echo' 	=> true,
				'id'    => $this->get_field_name( 'posts_num' ),
				'name'  => $this->get_field_name( 'posts_num' ),
				'type'  => 'number',
				'title'	=> esc_html__( 'Count', 'codevz-plus' ),
			);
			codevz_add_field( $posts_num_field, $posts_num_value );

			$posts_num_value = esc_attr( $data[ 'columns' ] );
			$posts_num_field = array(
				'echo' 	=> true,
				'id'    	=> $this->get_field_name( 'columns' ),
				'name'  	=> $this->get_field_name( 'columns' ),
				'type'  	=> 'radio',
				'options' 	=> [
					'2' 		=> '2',
					'3' 		=> '3',
				],
				'title'		=> esc_html__( 'Columns', 'codevz-plus' ),
			);
			codevz_add_field( $posts_num_field, $posts_num_value );

			$gallery_order_value = esc_attr( $data['gallery_order'] );
			$gallery_order_field = array(
				'echo' 	=> true,
				'id'    => $this->get_field_name('gallery_order'),
				'name'  => $this->get_field_name('gallery_order'),
				'type'  => 'radio',
				'options' => array(
					'DESC' => 'DESC',
					'ASC' => 'ASC'
				),
				'title' => esc_html__('Order', 'codevz-plus' )
			);
			codevz_add_field( $gallery_order_field, $gallery_order_value );
		}
	}

}


/**
 * 
 * Exclusive Ads
 * 
 */
if ( !class_exists( 'Codevz_Widget_exclusive_Ads' ) ) {

	class Codevz_Widget_exclusive_Ads extends WP_Widget {

		public function __construct() {
			parent::__construct( 
				false, 
				'- ' . esc_html__( 'Exclusive Ads', 'codevz-plus' ), 
				[ 
					'customize_selective_refresh' => true, 
					'classname' => 'cz_widget_exclusive_ads' 
				]
			);
		}

		public function widget( $args, $data ) {

			extract( $args );

			$out = '';

			$title = apply_filters( 'widget_title', $data['title'] );

			ob_start();

			echo '<div class="widget cz-exclusive-ads" style="padding:20px;background:none;box-shadow:none;border:1px solid rgba(103, 103, 103, 0.13);">';

			echo $data[ 'title' ] ? '<span style="position: absolute;color:#676767; background: #fff; font-size: 12px; padding: 2px 10px; top: -13px; margin: 0 -10px;">' . esc_html( $data[ 'title' ] ) . '</span>' : '';

			if ( $data[ 'ads_a_image' ] ) {
				echo '<a target="_blank" class="cz-exclusive-ads-big" href="' . esc_html( $data[ 'ads_a_link' ] ) . '"><img loading="lazy" src="' . esc_html( $data[ 'ads_a_image' ] ) . '" alt="ads" /></a>';
			}

			if ( $data[ 'ads_b_image' ] ) {
				echo '<a target="_blank" class="cz-exclusive-ads-small" style="display: inline-block; width: 47%; margin-top: 15px;margin-bottom: -10px;" href="' . esc_html( $data[ 'ads_b_link' ] ) . '"><img loading="lazy" src="' . esc_html( $data[ 'ads_b_image' ] ) . '" alt="ads" /></a>';
			}

			if ( $data[ 'ads_c_image' ] ) {
				echo '<a target="_blank" class="cz-exclusive-ads-small" style="display: inline-block; width: 47%; ' . ( is_rtl() ? 'margin-right:6%;' : 'margin-left:6%;' ) . ' margin-top: 15px;margin-bottom: -10px;" href="' . esc_html( $data[ 'ads_c_link' ] ) . '"><img loading="lazy" src="' . esc_html( $data[ 'ads_c_image' ] ) . '" alt="ads" /></a>';
			}

			echo '</div>';

			$out .= ob_get_clean();

			echo do_shortcode( $out );
		}
			
		// Update
		public function update( $data, $new ) {
			Codevz_Widget::update( $this, $new );
		}

		// Settings
		public function form( $data ) {
			Codevz_Widget::settings( $this, $data );
		}

		// Fields
		public function fields() {
			return [
				[
					'name'		=> 'title',
					'type'		=> 'text',
					'title'		=> esc_html__( 'Title', 'codevz-plus' ),
					'default' 	=> esc_html__( 'Advertisement', 'codevz-plus' )
				],
				[
					'name' 		=> 'ads_a_image',
					'type' 		=> 'upload',
					'title' 	=> esc_html__( 'Big ads image', 'codevz-plus' )
				],
				[
					'name' 		=> 'ads_a_link',
					'type' 		=> 'text',
					'title' 	=> esc_html__( 'Big ads link', 'codevz-plus' )
				],
				[
					'name' 		=> 'ads_b_image',
					'type' 		=> 'upload',
					'title' 	=> esc_html__( 'Small ads image', 'codevz-plus' )
				],
				[
					'name' 		=> 'ads_b_link',
					'type' 		=> 'text',
					'title' 	=> esc_html__( 'Small ads link', 'codevz-plus' )
				],
				[
					'name' 		=> 'ads_c_image',
					'type' 		=> 'upload',
					'title' 	=> esc_html__( 'Small ads image', 'codevz-plus' )
				],
				[
					'name' 		=> 'ads_c_link',
					'type' 		=> 'text',
					'title' 	=> esc_html__( 'Small ads link', 'codevz-plus' )
				],
			];
		}
	}
}


/**
 * 
 * Custom taxonomy list widget
 * 
 */
if ( ! function_exists( 'init_lc_taxonomy' ) && ! class_exists( 'Codevz_Widget_Taxonomy' ) ) {

class Codevz_Widget_Taxonomy extends WP_Widget {

	public function __construct() {
		parent::__construct( 
			false, 
			'- ' . esc_html__( 'Taxonomy menus', 'codevz-plus' ), 
			[ 
				'customize_selective_refresh' => true, 
				'classname' => 'codevz-widget-taxonomy' 
			]
		);
	}

	public function widget( $args, $data ) {

	    extract( $args );

	    // Widget options
	    $tax = $data['taxonomy'];
	    $showcount = !empty( $data['count'] ) ? '1' : '0';
	    $orderby = array_key_exists('orderby', $data) ? $data['orderby'] : 'count';
	    $ascdsc = array_key_exists('ascdsc', $data) ? $data['ascdsc'] : 'desc';
	    $exclude = array_key_exists('exclude', $data) ? $data['exclude'] : '';
	    $childof = array_key_exists('childof', $data) ? $data['childof'] : '';
	    $dropdown = array_key_exists('dropdown', $data) ? $data['dropdown'] : false;

	    ob_start();

	    echo '<style>
	        .codevz-widget-taxonomy .widget_nav_menu {
	            list-style: none;
	            padding: 0
	        }
	        .codevz-widget-taxonomy li {
	            position: relative
	        }
	        .codevz-widget-taxonomy .codevz-widget-toggle {
	            display: flex;
	            align-items: center;
	            justify-content: space-between;
	            text-decoration: none
	        }
	        .codevz-widget-taxonomy .codevz-widget-toggle a {
	            flex-grow: 1;
	            text-decoration: none
	        }
	        .codevz-widget-taxonomy label {
	        	line-height: 1;
	        	padding: 0;
	        	margin: 0;
	            cursor: pointer
	        }
	        .codevz-widget-taxonomy label i {
	        	font-size: 14px;
	            transition: transform 0.3s ease;
	        }
	        .codevz-widget-taxonomy input {
	            display: none !important
	        }
	        .codevz-widget-taxonomy input:checked + div label i {
	            transform: rotate(180deg)
	        }
	        .codevz-widget-taxonomy input:checked ~ ul {
	            display: block
	        }
	        .codevz-widget-taxonomy ul ul {
	            display: none;
	            list-style: circle;
	            padding-left: 20px
	        }
	    </style>';

	    // Get top-level terms
	    $terms = get_terms(array(
	        'taxonomy'   => $tax,
	        'orderby'    => $orderby,
	        'order'      => $ascdsc,
	        'hide_empty' => 0,
	        'exclude'    => $exclude,
	        'parent'     => 0
	    ));

	    if (!empty($terms)) {
	        echo '<ul class="widget_nav_menu">';
	        foreach ($terms as $term) {
	            $children = get_terms(array(
	                'taxonomy'   => $tax,
	                'hide_empty' => 0,
	                'parent'     => $term->term_id
	            ));

	            $has_children = !empty($children);
	            $unique_id = 'codevz-widget-taxonomy-' . $term->term_id;

	            echo '<li>';
	            if ($has_children) {
	                echo '<input type="checkbox" id="' . $unique_id . '">';
	                echo '<div class="codevz-widget-toggle">';
	                echo '<a href="' . esc_url(get_term_link($term)) . '">' . esc_html($term->name) . '</a>';
	                echo '<label for="' . $unique_id . '"><i class="fas fa-chevron-down"></i></label>';
	                echo '</div>';
	                echo '<ul>';
	                foreach ($children as $child) {
	                    echo '<li><a href="' . esc_url(get_term_link($child)) . '">' . esc_html($child->name) . '</a></li>';
	                }
	                echo '</ul>';
	            } else {
	                echo '<a href="' . esc_url(get_term_link($term)) . '" class="codevz-widget-toggle">' . esc_html($term->name) . '</a>';
	            }
	            echo '</li>';
	        }
	        echo '</ul>';
	    }

	    $out = ob_get_clean();

	    Codevz_Widget::output(null, $args, $data, $out);
	}

	/** Widget control update */
	public function update( $new, $data ) {
		
		$data['title']  		= wp_kses_post( (string) $new['title'] );
		$data['taxonomy'] 		= wp_kses_post( (string) $new['taxonomy'] );
		$data['orderby'] 		= wp_kses_post( (string) $new['orderby'] );
		$data['ascdsc'] 		= wp_kses_post( (string) $new['ascdsc'] );
		$data['exclude'] 		= wp_kses_post( (string) $new['exclude'] );
		$data['childof'] 		= wp_kses_post( (string) $new['childof'] );
		$data['hierarchical'] 	= empty( $new['hierarchical'] ) ? 0 : 1;
        $data['count'] 			= empty( $new['count'] ) 		? 0 : 1;
        $data['dropdown'] 		= empty( $new['dropdown'] ) 	? 0 : 1;

		return $data;

	}
	
	/* Widget settings */
	public function form( $data ) {

		if ( $data ) {
			$title  = $data['title'];
			$this_taxonomy = $data['taxonomy'];
			$orderby = $data['orderby'];
			$ascdsc = $data['ascdsc'];
			$exclude = $data['exclude'];
			$childof = $data['childof'];
			$showcount = isset($data['count']) ? (bool) $data['count'] :false;
			$hierarchical = isset( $data['hierarchical'] ) ? (bool) $data['hierarchical'] : false;
			$dropdown = isset( $data['dropdown'] ) ? (bool) $data['dropdown'] : false;
		} else {
			$title  = '';
			$orderby  = 'count';
			$ascdsc  = 'desc';
			$exclude  = '';
			$childof  = '';
			$this_taxonomy = 'category';//this will display the category taxonomy, which is used for normal, built-in posts
			$hierarchical = true;
			$showcount = true;
			$dropdown = false;
		}

		?>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id('title') ); ?>"><?php echo esc_html__( 'Title', 'codevz-plus' ); ?></label>
				<input id="<?php echo esc_attr( $this->get_field_id('title') ); ?>" name="<?php echo esc_attr( $this->get_field_name('title') ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" class="widefat" />
			</p>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id('taxonomy') ); ?>"><?php echo esc_html__( 'Taxonomy', 'codevz-plus' ); ?></label>
				<select name="<?php echo esc_attr( $this->get_field_name('taxonomy') ); ?>" id="<?php echo esc_attr( $this->get_field_id('taxonomy') ); ?>" class="widefat" style="height: auto;" size="4">
			<?php 
			$args=array(
			  'public'   => true,
			  '_builtin' => false //these are manually added to the array later
			); 
			$output = 'names'; // or objects
			$operator = 'and'; // 'and' or 'or'
			$taxonomies=get_taxonomies($args,$output,$operator); 
			$taxonomies[] = 'category';
			$taxonomies[] = 'post_tag';
			$taxonomies[] = 'post_format';
			foreach ($taxonomies as $taxonomy ) { ?>
				<option value="<?php echo esc_attr( $taxonomy ); ?>" <?php if( $taxonomy == $this_taxonomy ) { echo 'selected="selected"'; } ?>><?php echo esc_html( $taxonomy ); ?></option>
			<?php }	?>
			</select>
			</p>
			<div class="lctw-all-options">				
				<input type="checkbox" class="checkbox" id="<?php echo esc_attr( $this->get_field_id('count') ); ?>" name="<?php echo esc_attr( $this->get_field_name('count') ); ?>"<?php checked( $showcount ); ?> />
				<label for="<?php echo esc_attr( $this->get_field_id('count') ); ?>"><?php esc_html_e( 'Show post counts', 'codevz-plus' ); ?></label><br />
				<p>
					<label for="<?php echo esc_attr( $this->get_field_id('orderby') ); ?>"><?php echo esc_html__( 'Order By', 'codevz-plus' ); ?></label>
					<select name="<?php echo esc_attr( $this->get_field_name('orderby') ); ?>" id="<?php echo esc_attr( $this->get_field_id('orderby') ); ?>" class="widefat" >
						<option value="ID" <?php if( $orderby == 'ID' ) { echo 'selected="selected"'; } ?>>ID</option>
						<option value="name" <?php if( $orderby == 'name' ) { echo 'selected="selected"'; } ?>>Name</option>
						<option value="slug" <?php if( $orderby == 'slug' ) { echo 'selected="selected"'; } ?>>Slug</option>
						<option value="count" <?php if( $orderby == 'count' ) { echo 'selected="selected"'; } ?>>Count</option>
						<option value="term_group" <?php if( $orderby == 'term_group' ) { echo 'selected="selected"'; } ?>>Term Group</option>
					</select>
				</p>
				<p>
					<label><input type="radio" name="<?php echo esc_attr( $this->get_field_name('ascdsc') ); ?>" value="asc" <?php if( $ascdsc == 'asc' ) { echo 'checked'; } ?>/> Ascending</label><br/>
					<label><input type="radio" name="<?php echo esc_attr( $this->get_field_name('ascdsc') ); ?>" value="desc" <?php if( $ascdsc == 'desc' ) { echo 'checked'; } ?>/> Descending</label>
				</p>
				<p>
					<label for="<?php echo esc_attr( $this->get_field_id('exclude') ); ?>">Exclude (comma-separated list of ids to exclude)</label><br/>
					<input type="text" class="widefat" name="<?php echo esc_attr( $this->get_field_name('exclude') ); ?>" value="<?php echo esc_attr( $exclude ); ?>" />
				</p>
				<p>
					<label for="<?php echo esc_attr( $this->get_field_id('exclude') ); ?>">Only Show Children of (category id)</label><br/>
					<input type="text" class="widefat" name="<?php echo esc_attr( $this->get_field_name('childof') ); ?>" value="<?php echo esc_attr( $childof ); ?>" />
				</p>
			</div>
<?php 
	}

}
}

/**
 * 
 * Posts widget
 * 
 */
if ( ! class_exists( 'CodevzPostsList' ) ) {

	class CodevzPostsList extends WP_Widget {

		public function __construct() {
			parent::__construct( 
				false, 
				'- ' . esc_html__( 'Posts list', 'codevz-plus' ), 
				[ 
					'customize_selective_refresh' => true, 
					'classname' => 'codevz-widget-posts' 
				]
			);
		}

		public function widget( $args, $data ) {
			extract( $args, EXTR_SKIP );

			$defaults = array(
				'title' 	=> '',
				'show' 		=> '3',
				'orderby'	=> 'date',
				'order'		=> 'DESC',
				'catin' 	=> '',
				'catout' 	=> '',
				'pagecount' => '3',
				'taxis' 	=> '',
				'taxterm' 	=> '',
				'ptipe' 	=> 'post',
				'metakey'	=> '',
				'metavalue' => '',
				'metacompare' => '=',
				'widgetidentifier' => '',
				'widgetclassifier' => '',
				'readmoretitle' 	=> '',
				'readmorelink' 		=> ''
			);

			$data = wp_parse_args( (array) $data, $defaults );

			$post_amount = $data['show'];
			$post_orderby = $data['orderby'];
			$post_order = $data['order'];
			$post_catin = $data['catin'];
			$post_catout = $data['catout'];
			$pagecount = $data['pagecount'];
			$post_taxis = $data['taxis'];
			$post_taxterm = $data['taxterm'];
			$post_typed = $data['ptipe'];
			$post_metakey = $data['metakey'];
			$post_metavalue = $data['metavalue'];
			$post_comparison = $data['metacompare'];
			$post_widgeid = $data['widgetidentifier'];
			$post_widgeclass = $data['widgetclassifier'];
			$post_readmoretitle = $data['readmoretitle'];
			$post_readmorelink = $data['readmorelink'];

			if (!$post_typed) {
				$post_typed = 'post';
			}
			if (!$post_comparison) {
				$post_comparison = '=';
			}
			
			$qargs = array(
				'post_type'   => $post_typed,
				'numberposts' => $post_amount,
				'post_status' => 'publish',
				'orderby'     => $post_orderby,
				'order'       => $post_order
			);
			if ($post_catin) {
				$catin = explode(",", $post_catin);
				$qargs['category__in'] = $catin;
			}
			if ($post_catout) {
				$catout = explode(",", $post_catout);
				$qargs['category__not_in'] = $catout;
			}
			if ($post_taxis && $post_taxterm) {
				$taxray = explode(",", $post_taxterm);
				$qargs['tax_query'] = array(
					array(
						'taxonomy'  => $post_taxis,
						'field'     => 'slug',
						'terms'     => $taxray,
					)
				);
			}
			if ($post_metakey && $post_metavalue) {
				$qargs['meta_query'] = array(
					array(
						'key'     => $post_metakey,
						'value'   => $post_metavalue,
						'compare' => $post_comparison,
					)
				);
			}

			$posts = get_posts( $qargs );

			$toprint = '';
			$count = 1;			

			if ( !empty($posts) ) {
				foreach ( $posts as $post ) {
					setup_postdata( $post );
					$thisprint = '<div class="item_small">';
					$thm = get_the_post_thumbnail( $post->ID, 'thumbnail' );
					if ( has_post_thumbnail( $post->ID ) && $thm ):
						$thisprint .= '<a href="'.get_permalink( $post->ID ).'" title="'.get_the_title( $post->ID ).'">' . $thm . '<i class="fas fa-link"></i></a>';
					endif;
					$thisprint .= '<div class="item-details"><h3><a class="genposts_linktitle" href="'.get_permalink( $post->ID ).'" title="'.get_the_title( $post->ID ).'">'.get_the_title( $post->ID ).'</a></h3>';

					$thisprint .= '<div class="cz_small_post_date">';
					$thisprint .= '<span class="mr8"><i class="fa fa-clock-o mr8" aria-hidden="true"></i>' . mysql2date( get_option( 'date_format' ), $post->post_date ) . '</span>';

					if ( Codevz_Plus::option( 'post_views_count' ) ) {
						$post_views_count = get_post_meta( $post->ID, 'codevz_post_views_count', true );
						$post_views_count = $post_views_count ? $post_views_count : 1;
						$thisprint .= '<span class="mr8"><i class="fas fa-eye mr8" aria-hidden="true"></i>' . esc_html( $post_views_count ) . '</span>';
					}

					$thisprint .= '</div>';

					$thisprint .= '</div></div>';
					$toprint .= $thisprint;
					$count++;
				}
			}

			$readingon = $openprint = $closeprint = '';

			if ($post_readmoretitle && $post_readmorelink) {
				$readingon = '<div class="tac mtt"><a href="' . $post_readmorelink . '" rel="bookmark" title="' . $post_readmoretitle . '" class="tbutton"><span>' . $post_readmoretitle . '</span></a></div>';
			}
			$closeprint .= $readingon;
			$finalprint = $openprint . $toprint . $closeprint;

			Codevz_Widget::output( null, $args, $data, $finalprint );

		}

		public function update( $new, $data ) {

			$data['title']			= wp_kses_post( (string) $new['title'] );
			$data['show']			= wp_kses_post( (string) $new['show'] );
			$data['orderby']		= wp_kses_post( (string) $new['orderby'] );
			$data['order']			= wp_kses_post( (string) $new['order'] );
			$data['catin']			= wp_kses_post( (string) $new['catin'] );
			$data['catout']			= wp_kses_post( (string) $new['catout'] );
			$data['pagecount'] 		= wp_kses_post( (string) $new['pagecount'] );
			$data['taxis'] 			= wp_kses_post( (string) $new['taxis'] );
			$data['taxterm'] 		= wp_kses_post( (string) $new['taxterm'] );
			$data['ptipe'] 			= wp_kses_post( (string) $new['ptipe'] );
			$data['metakey'] 		= wp_kses_post( (string) $new['metakey'] );
			$data['metavalue'] 		= wp_kses_post( (string) $new['metavalue'] );
			$data['metacompare'] 	= wp_kses_post( (string) $new['metacompare'] );
			$data['widgetidentifier'] = wp_kses_post( (string) $new['widgetidentifier'] );
			$data['widgetclassifier'] = wp_kses_post( (string) $new['widgetclassifier'] );
			$data['readmoretitle'] 	= wp_kses_post( (string) $new['readmoretitle'] );
			$data['readmorelink'] 	= wp_kses_post( (string) $new['readmorelink'] );

			return $data;

		}

		public function form( $data ) {

			$defaults = array(
				'title' => esc_html__( 'General Posts', 'codevz-plus' ),
				'show' => '3',
				'orderby'=> 'date',
				'order'=>'DESC',
				'catin' => '',
				'catout' => '',
				'pagecount' => '3',
				'taxis' => '',
				'taxterm' => '',
				'ptipe' => 'post',
				'metakey'=> '',
				'metavalue' => '',
				'metacompare' => '=',
				'widgetidentifier' => '',
				'widgetclassifier' => '',
				'readmoretitle' => '',
				'readmorelink' => ''
			);

			$data = wp_parse_args( (array) $data, $defaults );
			$title = $data['title'];
			$show  = $data['show'];
			$orderby  = $data['orderby'];
			$order  = $data['order'];
			$post_catin = $data['catin'];
			$post_catout = $data['catout'];
			$pagecount = $data['pagecount'];
			$post_taxis = $data['taxis'];
			$post_taxterm = $data['taxterm'];
			$post_typed = $data['ptipe'];
			$post_metakey = $data['metakey'];
			$post_metavalue = $data['metavalue'];
			$post_comparison = $data['metacompare'];
			$post_widgeid = $data['widgetidentifier'];
			$post_widgeclass = $data['widgetclassifier'];
			$post_readmoretitle = $data['readmoretitle'];
			$post_readmorelink = $data['readmorelink'];
			//$term  = $data['term'];

	        // get the parent term
	        //$season = get_term_by( 'slug', 'seasonal', 'featured' );
			$orbe = array('none', 'ID', 'author', 'title', 'name', 'date', 'modified', 'parent', 'rand', 'comment_count', 'menu_order', 'meta_value', 'meta_value_num');
			$metcompare = array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'EXISTS', 'NOT EXISTS');
			
			?>

			<p><?php esc_html_e( 'Title', 'codevz-plus' ); ?> <input class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" value="<?php echo esc_attr( $title ); ?>" /></p>
			
			<p><?php esc_html_e( 'ID', 'codevz-plus' ); ?> <input class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'widgetidentifier' ) ); ?>" value="<?php echo esc_attr($post_widgeid); ?>" /></p>
			
			<p><?php esc_html_e( 'Class', 'codevz-plus' ); ?> <input class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'widgetclassifier' ) ); ?>" value="<?php echo esc_attr($post_widgeclass); ?>" /></p>
			
			<p><?php esc_html_e( 'Post type', 'codevz-plus' ); ?> 	
				<select name="<?php echo esc_attr( $this->get_field_name('ptipe') ); ?>"><?php
			
				$datype = get_post_types(array('public'=>true), 'objects'); 
				foreach($datype as $atipe){
					?>
						<option value="<?php echo esc_attr( $atipe->name ); ?>" <?php if($atipe->name == $post_typed){echo "selected";} ?>><?php echo esc_attr( $atipe->label ); ?></option>
					<?php
				}
				?>
				</select>
			</p>

			<p><?php esc_html_e( 'Number of posts', 'codevz-plus' ); ?> <input class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'show' ) ); ?>" value="<?php echo esc_attr( $show ); ?>" /></p>
			<p><?php esc_html_e( 'Posts to show at once', 'codevz-plus' ); ?> <input class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'pagecount' ) ); ?>" value="<?php echo esc_attr( $pagecount ); ?>" /></p>
	        <p><?php esc_html_e( 'Order by', 'codevz-plus' ); ?>

	            <select name="<?php echo esc_attr( $this->get_field_name( 'orderby' ) ); ?>">
	                <?php
	                foreach( $orbe as $orb ){
	                ?>
	                    <option value="<?php echo esc_attr( $orb ); ?>" <?php selected( $orderby, $orb); ?>><?php echo esc_attr( $orb ); ?></option>
	                <?php } ?>
	            </select>
	        </p>

			<p><?php esc_html_e( 'Order', 'codevz-plus' ); ?>

	            <select name="<?php echo esc_attr( $this->get_field_name( 'order' ) ); ?>">
	                    <option value="ASC" <?php selected( $order, 'ASC'); ?>><?php esc_html_e( 'Ascending', 'codevz-plus' ); ?></option>
						<option value="DESC" <?php selected( $order, 'DESC'); ?>><?php esc_html_e( 'Descending', 'codevz-plus' ); ?></option>
	             </select>
	        </p>
			<p><?php esc_html_e( 'USE ONLY ONE OPTION BELOW', 'codevz-plus' ); ?></p>
			<p><?php esc_html_e( 'Category ID include', 'codevz-plus' ); ?> <input class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'catin' ) ); ?>" value="<?php echo esc_attr( $post_catin ); ?>" /></p>

			<p><?php esc_html_e( 'Category ID exclude', 'codevz-plus' ); ?> <input class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'catout' ) ); ?>" value="<?php echo esc_attr( $post_catout ); ?>" /></p>

			<p><?php esc_html_e( 'Taxonomy', 'codevz-plus' ); ?> <select name="<?php echo esc_attr( $this->get_field_name('taxis') ); ?>"><?php
			
				$dataxes = get_object_taxonomies($post_typed, 'objects');
				foreach($dataxes as $atax){
					?>
						<option value="<?php echo esc_attr( $atax->name ); ?>" <?php if($atax->name == $post_taxis){echo "selected";} ?>><?php echo esc_attr( $atax->label ); ?></option>
					<?php
				}
			?>
			</select>
			<br/>
			<?php esc_html_e( 'Enter the term slug', 'codevz-plus' ); ?>
			<input class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'taxterm' ) ); ?>" value="<?php echo esc_attr( $post_taxterm ); ?>" />
			</p>
			
			<?php esc_html_e( 'Meta key', 'codevz-plus' ); ?> <input class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'metakey' ) ); ?>" value="<?php echo esc_attr( $post_metavalue ); ?>" />
			<br/>
			<?php esc_html_e( 'Meta value', 'codevz-plus' ); ?> <input class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'metavalue' ) ); ?>" value="<?php echo esc_attr( $post_metavalue ); ?>" />
			<br/>
			<?php esc_html_e( 'Meta compare', 'codevz-plus' ); ?>
			<select name="<?php echo esc_attr( $this->get_field_name( 'metacompare' ) ); ?>">
	                <?php
	                foreach( $metcompare as $mc ){
	                ?>
	                    <option value="<?php echo esc_attr( $mc ); ?>" <?php selected( $post_comparison, $mc); ?>><?php echo esc_attr( $mc ); ?></option>
	                <?php } ?>
	            </select>
			</p>
			
			<p><?php esc_html_e( 'Read more', 'codevz-plus' ); ?> <input class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'readmoretitle' ) ); ?>" value="<?php echo esc_attr($post_readmoretitle); ?>" /></p>

			<p><?php esc_html_e( 'Read more link', 'codevz-plus' ); ?> <input class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'readmorelink' ) ); ?>" value="<?php echo esc_attr($post_readmorelink); ?>" /></p>
			<?php
		}
	}
}

/**
 * Register custom widgets
 */
function codevz_register_widgets() {

	global $pagenow;

	if ( ! is_admin() || $pagenow === 'customize.php' || $pagenow === 'widgets.php' || $pagenow === 'admin-ajax.php' ) {

		register_widget( 'Codevz_Widget_Working_Hours' );
		register_widget( 'Codevz_Widget_Stylish_List' );
		register_widget( 'Codevz_Widget_Social_Icons' );
		register_widget( 'Codevz_Widget_Custom_Menu_List' );
		register_widget( 'Codevz_Widget_Gallery' );
		register_widget( 'Codevz_Widget_Posts_Grid' );
		register_widget( 'Codevz_Widget_About' );
		register_widget( 'Codevz_Widget_Login' );
		register_widget( 'Codevz_Widget_exclusive_Ads' );

		register_widget( 'CodevzFacebook' );
		register_widget( 'CodevzFlickr' );
		register_widget( 'CodevzCustomMenuList' );
		register_widget( 'CodevzCustomMenuList2' );
		register_widget( 'CodevzPostsList' );
		register_widget( 'CodevzSimpleAds' );
		register_widget( 'CodevzSubscribe' );
		register_widget( 'CodevzPageContent' );
		register_widget( 'CodevzPortfolio' );
		register_widget( 'Codevz_Widget_Taxonomy' );

		register_widget( 'Codevz_Widget_Soundcloud' );
		register_widget( 'Codevz_Widget_Unboxed' );
		register_widget( 'Xtra_Widget_Newsletter' );

	}

}
add_action( 'widgets_init', 'codevz_register_widgets' );

