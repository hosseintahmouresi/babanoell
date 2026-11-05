<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.

/**
 * News Ticker
 * 
 * @author Codevz
 * @link http://codevz.com/
 */

class Codevz_WPBakery_news_ticker {

	public $name = false;

	public function __construct( $name ) {
		$this->name = $name;
	}

	/**
	 * Shortcode settings
	 */
	public function in( $wpb = false ) {
		add_shortcode( $this->name, [ $this, 'out' ] );

		$settings = array(
			'category'		=> Codevz_Plus::$title,
			//'deprecated' 	=> '4.6',
			'base'			=> $this->name,
			'name'			=> esc_html__( 'News Ticker', 'codevz-plus' ),
			'description'	=> esc_html__( 'News ticker slider', 'codevz-plus' ),
			'icon'			=> 'czi',
			'params'		=> array(
				array(
					'type' 			=> 'cz_sc_id',
					'param_name' 	=> 'id',
					'save_always' 	=> true
				),
				array(
					'type' 			=> 'dropdown',
					'heading' 		=> esc_html__('Type', 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name' 	=> 'type',
					'value'		=> array(
						esc_html__( 'Slide', 'codevz-plus' ) 		=> 'slider',
						esc_html__( 'Fade', 'codevz-plus' ) 		=> 'fade',
						esc_html__( 'Vertical', 'codevz-plus' ) 	=> 'vertical',
					)
				),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__( "Badge", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "badge_title",
					"value"  		=> esc_html__( "TRENDING", 'codevz-plus' ),
					'admin_label' 	=> true
				),
				array(
					"type"        	=> "cz_slider",
					"heading"     	=> esc_html__("Auto play seconds", 'codevz-plus' ),
					"value"  		=> '4',
					'options' 		=> array( 'unit' => '', 'step' => 1, 'min' => 1, 'max' => 10 ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "speed"
				),

				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Styling', 'codevz-plus' ),
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_con',
					"heading"     	=> esc_html__( "Container", 'codevz-plus' ),
					'button' 		=> esc_html__( "Container", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_con_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_con_tablet' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_badge',
					"heading"     	=> esc_html__( "Badge", 'codevz-plus' ),
					'button' 		=> esc_html__( "Badge", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'position', 'top', 'right', 'bottom', 'left', 'color', 'font-family', 'font-size', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_badge_mobile' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_links',
					"heading"     	=> esc_html__( "Links", 'codevz-plus' ),
					'button' 		=> esc_html__( "Links", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_links_mobile' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_meta',
					"heading"     	=> esc_html__( "Meta", 'codevz-plus' ),
					'button' 		=> esc_html__( "Meta", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_meta_mobile' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_arrows',
					'hover_id'	 	=> 'sk_arrows_hover',
					"heading"     	=> esc_html__( "Arrows", 'codevz-plus' ),
					'button' 		=> esc_html__( "Arrows", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_arrows_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_arrows_hover' ),

				// WP_Query
				array(
					'type' 			=> 'autocomplete',
					'heading' 		=> esc_html__('Post type(s)', 'codevz-plus' ),
					'settings' 		=> array(
						'multiple'		=> true,
						'save_always'	=> true,
						'sortable' 		=> true,
						'groups' 		=> true,
						'unique_values' => true,
					),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name' 	=> 'post_type',
					'std'			=> 'post',
					'group' 		=> esc_html__( 'Query', 'codevz-plus' )
				),
				array(
					"type"			=> "dropdown",
					"heading"		=> esc_html__("Orderby", 'codevz-plus'),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"	=> "orderby",
					"value"			=> array(
						esc_html__("Date", 'codevz-plus')	=> 'date',
						esc_html__("ID", 'codevz-plus')		=> 'ID',
						esc_html__("Random", 'codevz-plus') => 'rand',
						esc_html__("Author", 'codevz-plus') => 'author',
						esc_html__("Title", 'codevz-plus')	=> 'title',
						esc_html__("Name", 'codevz-plus')	=> 'name',
						esc_html__("Type", 'codevz-plus')	=> 'type',
						esc_html__("Modified", 'codevz-plus') => 'modified',
						esc_html__("Parent ID", 'codevz-plus') => 'parent',
						esc_html__("Comment Count", 'codevz-plus') => 'comment_count',
					),
					'group' 		=> esc_html__( 'Query', 'codevz-plus' )
				),
				array(
					"type"			=> "dropdown",
					"heading"		=> esc_html__("Order", 'codevz-plus'),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"	=> "order",
					"value"			=> array(
						esc_html__("Descending", 'codevz-plus') => 'DESC',
						esc_html__("Ascending", 'codevz-plus') => 'ASC',
					),
					'group' 		=> esc_html__( 'Query', 'codevz-plus' )
				), 
				array(
					"type"			=> "dropdown",
					"heading"		=> esc_html__("Category Taxonomy", 'codevz-plus'),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"	=> "cat_tax",
					"value"			=> get_taxonomies(),
					"std"			=> 'category',
					'group' 		=> esc_html__( 'Query', 'codevz-plus' )
				),
				array(
					'type' 			=> 'autocomplete',
					'heading' 		=> esc_html__('Category(s)', 'codevz-plus' ),
					'settings' 		=> array(
						'multiple'		=> true,
						'save_always'	=> true,
						'sortable' 		=> true,
						'groups' 		=> true,
						'unique_values' => true,
					),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name' 	=> 'cat',
					'group' 		=> esc_html__( 'Query', 'codevz-plus' )
				),
				array(
					'type' 			=> 'autocomplete',
					'heading' 		=> esc_html__('Exclude Category(s)', 'codevz-plus' ),
					'settings' 		=> array(
						'multiple'		=> true,
						'save_always'	=> true,
						'sortable' 		=> true,
						'groups' 		=> true,
						'unique_values' => true,
					),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name' 	=> 'cat_exclude',
					'group' 		=> esc_html__( 'Query', 'codevz-plus' )
				),
				array(
					"type"			=> "dropdown",
					"heading"		=> esc_html__("Tags Taxonomy", 'codevz-plus'),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"	=> "tag_tax",
					"value"			=> get_taxonomies(),
					"std"			=> 'post_tag',
					'group' 		=> esc_html__( 'Query', 'codevz-plus' )
				),
				array(
					'type' 			=> 'autocomplete',
					'heading' 		=> esc_html__('Tag', 'codevz-plus' ),
					'settings' 		=> array(
						'multiple'		=> false,
						'save_always'	=> true,
					),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name' 	=> 'tag_id',
					'group' 		=> esc_html__( 'Query', 'codevz-plus' )
				),
				array(
					'type' 			=> 'autocomplete',
					'heading' 		=> esc_html__('Exclude Tag', 'codevz-plus' ),
					'settings' 		=> array(
						'multiple'		=> false,
						'save_always'	=> true,
					),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name' 	=> 'tag_exclude',
					'group' 		=> esc_html__( 'Query', 'codevz-plus' )
				),
				array(
					'type' 			=> 'autocomplete',
					'heading' 		=> esc_html__( 'Filter by posts', 'codevz-plus' ),
					'settings' 		=> array(
						'multiple'		=> true,
						'save_always'	=> true,
						'sortable' 		=> true,
						'groups' 		=> true,
						'unique_values' => true,
					),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name' 	=> 'post__in',
					'group' 		=> esc_html__( 'Query', 'codevz-plus' )
				),
				array(
					'type' 			=> 'autocomplete',
					'heading' 		=> esc_html__( 'Filter by authors', 'codevz-plus' ),
					'settings' 		=> array(
						'multiple'		=> true,
						'save_always'	=> true,
						'sortable' 		=> true,
						'groups' 		=> true,
						'unique_values' => true,
					),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name' 	=> 'author__in',
					'group' 		=> esc_html__( 'Query', 'codevz-plus' )
				),
				array(
					'type' 			=> 'textfield',
					'heading' 		=> esc_html__('Search keyword', 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name' 	=> 's',
					'group' 		=> esc_html__( 'Query', 'codevz-plus' )
				),

				// Advanced
				array(
					'type' 			=> 'checkbox',
					'heading' 		=> esc_html__( 'Hide on Desktop?', 'codevz-plus' ),
					'param_name' 	=> 'hide_on_d',
					'edit_field_class' => 'vc_col-xs-4',
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				), 
				array(
					'type' 			=> 'checkbox',
					'heading' 		=> esc_html__( 'Hide on Tablet?', 'codevz-plus' ),
					'param_name' 	=> 'hide_on_t',
					'edit_field_class' => 'vc_col-xs-4',
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				), 
				array(
					'type' 			=> 'checkbox',
					'heading' 		=> esc_html__( 'Hide on Mobile?', 'codevz-plus' ),
					'param_name' 	=> 'hide_on_m',
					'edit_field_class' => 'vc_col-xs-4',
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Animation & Class', 'codevz-plus' ),
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				Codevz_Plus::wpb_animation_tab( false ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_brfx',
					"heading"     	=> esc_html__( "Block Reveal", 'codevz-plus' ),
					'button' 		=> esc_html__( "Block Reveal", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99 hidden',
					'group' 	=> esc_html__( 'Advanced', 'codevz-plus' ),
					'settings' 		=> array( 'background' )
				),
				array(
					"type"        	=> "cz_slider",
					"heading"     	=> esc_html__("Animation Delay", 'codevz-plus' ),
					"description" 	=> 'e.g. 500ms',
					"param_name"  	=> "anim_delay",
					'options' 		=> array( 'unit' => 'ms', 'step' => 100, 'min' => 0, 'max' => 5000 ),
					'edit_field_class' => 'vc_col-xs-6',
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__( "Extra Class", 'codevz-plus' ),
					"param_name"  	=> "class",
					'edit_field_class' => 'vc_col-xs-6',
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
			)

		);

		return $wpb ? vc_map( $settings ) : $settings;
	}

	/**
	 *
	 * Shortcode output
	 * 
	 * @return string
	 * 
	 */
	public function out( $atts, $content = '' ) {

		// Settings.
		$atts = Codevz_Plus::shortcode_atts( $this, $atts );

		// ID
		if ( ! $atts['id'] ) {
			$atts['id'] = Codevz_Plus::uniqid();
			$public = 1;
		}

		// Styles
		if ( isset( $public ) || Codevz_Plus::$vc_editable || Codevz_Plus::$is_admin ) {
			$css_id = '#' . $atts['id'];

			$css_array = array(
				'sk_brfx' 			=> $css_id . ':before',
				'sk_con' 			=> $css_id . ' .cz_ticker',
				'sk_badge' 			=> $css_id . ' .cz_ticker_badge',
				'sk_links' 			=> $css_id . ' a',
				'sk_meta' 			=> $css_id . ' small',
				'sk_arrows' 		=> $css_id . ' button',
				'sk_arrows_hover' 	=> $css_id . ' button:hover',
			);

			$css 	= Codevz_Plus::sk_style( $atts, $css_array );
			$css_t 	= Codevz_Plus::sk_style( $atts, $css_array, '_tablet' );
			$css_m 	= Codevz_Plus::sk_style( $atts, $css_array, '_mobile' );

			$css .= $atts['anim_delay'] ? $css_id . '{animation-delay:' . $atts['anim_delay'] . '}' : '';

		} else {
			Codevz_Plus::load_font( $atts['sk_badge'] );
			Codevz_Plus::load_font( $atts['sk_links'] );
			Codevz_Plus::load_font( $atts['sk_meta'] );
		}

		// Slick Slider
		$speed = (int) $atts['speed'];
		$slick = array(
			'slidesToShow'		=> 1, 
			'slidesToScroll'	=> 1, 
			'fade'				=> false, 
			'vertical'			=> false, 
			'infinite'			=> true, 
			'speed'				=> 1000, 
			'autoplay'			=> true, 
			'autoplaySpeed'		=> $speed . '000', 
			'dots'				=> false,
		);

		if ( $atts['type'] === 'slider' ) {
			$slick = ' data-slick=\'' . wp_json_encode(array_merge( $slick, array() )) . '\'';
		} else if ( $atts['type'] === 'vertical' ) {
			$slick = ' data-slick=\'' . wp_json_encode(array_merge( $slick, array( 'verticalSwiping' => true, 'vertical' => true ) )) . '\'';
		} else {
			$slick = ' data-slick=\'' . wp_json_encode(array_merge( $slick, array( 'fade' => true ) )) . '\'';
		}

		// Classes
		$classes = array();
		$classes[] = 'cz_ticker arrows_tr arrows_inner';
		
		// Out
		$out = '<div id="' . $atts['id'] . '" class="' . $atts['id'] . ' relative clr"' . Codevz_Plus::data_stlye( $css, $css_t, $css_m ) . '>';
		$out .= $atts['badge_title'] ? '<div class="cz_ticker_badge">' . $atts['badge_title'] . '</div>' : '';
		$out .= '<div' . Codevz_Plus::classes( $atts, $classes ) . $slick . ' data-slick-prev="fa fa-angle-left" data-slick-next="fa fa-angle-right">';

		// Categories
		if ( $atts['cat'] && ! empty( $atts['cat_tax'] ) ) {
			$tax_query[] = array(
				'taxonomy'  => $atts['cat_tax'],
				'field'     => 'term_id',
				'terms'     => explode( ',', str_replace( ', ', ',', $atts['cat'] ) )
			);
		}

		// Exclude Categories
		if ( $atts['cat_exclude'] && ! empty( $atts['cat_tax'] ) ) {
			$tax_query[] = array(
				'taxonomy'  => $atts['cat_tax'],
				'field'     => 'term_id',
				'terms'     => explode( ',', str_replace( ', ', ',', $atts['cat_exclude'] ) ),
				'operator' 	=> 'NOT IN',
			);
		}

		// Tags
		if ( $atts['tag_id'] && ! empty( $atts['tag_tax'] ) ) {
			$tax_query[] = array(
				'taxonomy'  => $atts['tag_tax'],
				'field'     => 'term_id',
				'terms'     => explode( ',', str_replace( ', ', ',', $atts['tag_id'] ) )
			);
		}

		// Exclude Tags
		if ( $atts['tag_exclude'] && ! empty( $atts['tag_tax'] ) ) {
			$tax_query[] = array(
				'taxonomy'  => $atts['tag_tax'],
				'field'     => 'term_id',
				'terms'     => explode( ',', str_replace( ', ', ',', $atts['tag_exclude'] ) ),
				'operator' 	=> 'NOT IN',
			);
		}

		// Post types.
		$atts['post_type'] = $atts['post_type'] ? explode( ',', str_replace( ', ', ',', $atts['post_type'] ) ) : 'post';

		$q = new WP_Query( array_filter( $atts ) );

		if ( $q->have_posts() ) {
			while ( $q->have_posts() ) {
				$q->the_post();
				$out .= '<div class="cz_news_ticker_post"><a href="' . get_the_permalink() . '">' . get_the_title() . '</a> <small>' . get_the_date() . '</small></div>';
			}
		}

		wp_reset_postdata();
		$out .= '</div></div>';

		return Codevz_Plus::_out( $atts, $out, 'slick( true )', $this->name, 'cz_carousel' );
	}

}