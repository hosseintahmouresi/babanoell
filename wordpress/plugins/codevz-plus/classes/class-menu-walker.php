<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

/**
 * Mega Menu Walker
 */

class Codevz_Menu_Walker {

	use Codevz_Plus_Instance;

	public function __construct() {
		add_action( 'init', [ $this, 'init' ], 11 );
	}

	public function init() {
		add_filter( 'wp_edit_nav_menu_walker', [ $this, 'wp_edit_nav_menu_walker' ], 11 );
		add_filter( 'codevz_nav_menu_csf_fields', [ $this, 'add_fields' ], 10, 5 );
		add_action( 'save_post', [ $this, 'save' ] );
	}

	/**
	 * Custom walker class name
	 * @return string
	 */
	public function wp_edit_nav_menu_walker() {
		return 'Codevz_Walker_Nav_Menu_Edit';
	}

	/**
	 * Add new fields to menus
	 * @return array
	 */
	public function add_fields( $new, $item_output, $item, $depth, $args ) {

		foreach( $this->options() as $field ) {
			$lvl = isset( $field['depth'] ) ? ( $field['depth'] == $depth ) : 1;
			if ( $lvl ) {
				$meta_key = $field['name'];
				$field['id'] = $meta_key;
				$field['name'] = 'menu-item-' . $field['name'] . '[' . $item->ID . ']';
				$new .= '<div class="wp-clearfix"></div>' . codevz_add_field( $field, get_post_meta( $item->ID, $meta_key, true ) );
			}
		}

		return $new;
	}

	/**
	 * Save menus custom fields
	 */
	public function save( $id ) {

		if ( get_post_type( $id ) === 'nav_menu_item' ) {

			foreach( $this->options() as $field ) {

				$name = filter_input( INPUT_POST, 'menu-item-' . $field['name'], FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

				if ( ! empty( $name[ $id ] ) ) {
					update_post_meta( $id, $field['name'], $name[ $id ] );
				} else {
					update_post_meta( $id, $field['name'], false );
				}

			}

		}

	}

	/**
	 * Menus options
	 * @return array
	 */
	public function options() {

		$free = Codevz_Plus::is_free();

		return [
			[
				'name'  		=> 'cz_menu_activation',
				'title' 		=> esc_html__( 'Advanced', 'codevz-plus' ),
				'type'  		=> 'switcher'
			],
			[
				'name'  		=> 'cz_menu_col_title',
				'title' 		=> esc_html__( 'Headline', 'codevz-plus' ),
				'type'  		=> 'switcher',
				'depth'			=> 1,
				'dependency' 	=> [ 'cz_menu_activation', '==', 'true' ]
			],
			[
				'name' 			=> 'cz_menu_subtitle',
				'title' 		=> esc_html__( 'Subtitle', 'codevz-plus' ),
				'type' 			=> $free ? 'content' : 'text',
				'content' 		=> Codevz_Plus::pro_badge(),
				'dependency' 	=> [ 'cz_menu_activation', '==', 'true' ]
			],
			[
				'name'  	=> 'cz_menu_css_label',
				'hover_id' 	=> 'cz_menu_css_label_hover',
				'title' 	=> esc_html__( 'Menu', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Menu', 'codevz-plus' ),
				'type'  	=> 'cz_sk',
				'settings' 	=> [ 'color', 'background', 'font-size' ],
				'dependency' => [ 'cz_menu_activation', '==', 'true' ]
			],
			[
				'name'  	=> 'cz_menu_css_label_hover',
				'title' 	=> '',
				'button' 	=> '',
				'type'  	=> 'cz_sk_hidden',
				'settings' 	=> [ 'color', 'background', 'font-size' ],
				'dependency' => [ 'cz_menu_activation', '==', 'true' ]
			],
			[
				'name'  	=> 'cz_menu_css_label_shape',
				'title' 	=> esc_html__( 'Shape', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Shape', 'codevz-plus' ),
				'type'  	=> 'cz_sk',
				'settings' 	=> array( '_class_menu_fx', 'background', 'height', 'width', 'left', 'bottom', 'border' ),
				'dependency' => [ 'cz_menu_activation', '==', 'true' ]
			],
			[
				'name' 		=> 'cz_menu_icon_type',
				'type' 		=> 'select',
				'title' 	=> esc_html__( 'Icon type', 'codevz-plus' ),
				'options' 	=> [
					'' 			=> esc_html__( 'Icon', 'codevz-plus' ),
					'image' 	=> esc_html__( 'Image', 'codevz-plus' ),
				],
				'dependency' => [ 'cz_menu_activation', '==', 'true' ]
			],
			[
				'name'  	=> 'cz_menu_icon',
				'title' 	=> esc_html__( 'Icon', 'codevz-plus' ),
				'type'  	=> 'icon',
				'dependency' => [ 'cz_menu_activation|cz_menu_icon_type', '==|!=', 'true|image' ]
			],
			[
				'name'  	=> 'cz_menu_image',
				'title' 	=> esc_html__( 'Image', 'codevz-plus' ),
				'type'  	=> 'upload',
				'preview'  	=> 1,
				'dependency' => [ 'cz_menu_activation|cz_menu_icon_type', '==|==', 'true|image' ]
			],
			[
				'name'  	=> 'cz_menu_css_icon',
				'title' 	=> esc_html__( 'Icon', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Icon', 'codevz-plus' ),
				'type'  	=> 'cz_sk',
				'settings' 	=> [ 'color', 'background', 'width' ],
				'dependency' => [ 'cz_menu_activation', '==', 'true' ]
			],
			[
				'name'  => 'cz_menu_hide_title',
				'title' => esc_html__('Only icon', 'codevz-plus' ),
				'type'  => 'switcher',
				'dependency' => [ 'cz_menu_icon|cz_menu_activation', '!=|==', '|true' ]
			],
			[
				'name'  		=> 'cz_menu_badge',
				'title' 		=> esc_html__('Badge', 'codevz-plus' ),
				'type' 			=> $free ? 'content' : 'text',
				'content' 		=> Codevz_Plus::pro_badge(),
				'dependency' 	=> [ 'cz_menu_activation', '==', 'true' ]
			],
			[
				'name'  		=> 'cz_menu_css_badge',
				'title' 		=> esc_html__( 'Badge', 'codevz-plus' ),
				'button' 		=> esc_html__( 'Badge', 'codevz-plus' ),
				'type' 			=> $free ? 'content' : 'cz_sk',
				'content' 		=> Codevz_Plus::pro_badge(),
				'settings' 		=> [ 'color', 'font-family', 'background' ],
				'dependency' 	=> [ 'cz_menu_badge|cz_menu_activation', '!=|==', '|true' ]
			],
			[
				'name' 			=> 'cz_menu_visibility',
				'type' 			=> $free ? 'content' : 'select',
				'content' 		=> Codevz_Plus::pro_badge(),
				'title' 		=> esc_html__( 'Visibility', 'codevz-plus' ),
				'options' 		=> [
					'1' 			=> esc_html__( 'Show only to logged-in users', 'codevz-plus' ),
					'2' 			=> esc_html__( 'Show only to non-logged-in users', 'codevz-plus' ),
				],
				'default_option' => esc_html__( '~ Default ~', 'codevz-plus' ),
				'dependency' 	=> [ 'cz_menu_activation', '==', 'true' ]
			],
			[
				'name'  	=> 'cz_menu_css_ul',
				'title' 	=> esc_html__( 'Dropdown', 'codevz-plus' ),
				'button' 	=> esc_html__( 'Dropdown', 'codevz-plus' ),
				'depth'		=> 0,
				'type'  	=> 'cz_sk',
				'settings' 	=> [ 'background', 'width', 'border', 'box-shadow' ],
				'dependency' => [ 'cz_menu_activation', '==', 'true' ]
			],
			[
				'name' 		=> 'cz_menu_megamenu',
				'type' 		=> $free ? 'content' : 'select',
				'content' 	=> Codevz_Plus::pro_badge(),
				'title' 	=> esc_html__( 'Mega menu', 'codevz-plus' ),
				'depth'		=> 0,
				'options' 	=> [
					'listing' 		=> esc_html__( 'Default with children', 'codevz-plus' ),
					'custom' 		=> esc_html__( 'Page content as mega menu', 'codevz-plus' ),
					'custom_code' 	=> esc_html__( 'Custom shortcode as mega menu', 'codevz-plus' ),
				],
				'default_option' => esc_html__( '~ Default ~', 'codevz-plus' ),
				'dependency' 	=> [ 'cz_menu_activation', '==', 'true' ]
			],
			[
				'name' 		=> 'cz_menu_megamenu_id',
				'type' 		=> $free ? 'content' : 'select',
				'content' 	=> Codevz_Plus::pro_badge(),
				'depth'		=> 0,
				'title'		=> esc_html__( '~ Select ~', 'codevz-plus' ),
				'options' 	=> Codevz_Plus::$array_pages,
				'dependency' => [ 'cz_menu_megamenu', 'any', 'custom' ]
			],
			[
				'name' 		=> 'cz_menu_megamenu_width',
				'type' 		=> $free ? 'content' : 'select',
				'content' 	=> Codevz_Plus::pro_badge(),
				'depth'		=> 0,
				'title' 	=> esc_html__( 'Mega menu size', 'codevz-plus' ),
				'options' 	=> [
					'' 							 => esc_html__( '~ Default ~', 'codevz-plus' ),
					'cz_megamenu_center_mode' 	 => esc_html__( 'Center position', 'codevz-plus' ),
					'cz_megamenu_reverse_mode' 	 => esc_html__( 'Reverse position', 'codevz-plus' ),
					'cz_megamenu_width_full_row' => esc_html__( 'Fullwide according to header', 'codevz-plus' ),
					'cz_megamenu_width_fullwide' => esc_html__( 'Fullwide according to window', 'codevz-plus' ),
				],
				'dependency' => [ 'cz_menu_megamenu', 'any', 'listing,custom' ]
			],
			[
				'name'  	=> 'cz_menu_custom',
				'title' 	=> esc_html__('Custom code', 'codevz-plus' ),
				'depth'		=> 0,
				'type' 		=> $free ? 'content' : 'textarea',
				'content' 	=> Codevz_Plus::pro_badge(),
				'sanitize' 	=> false,
				'help'  	=> esc_html__( 'If you fill this field for this column, then title and icon not works for this menu item. This field allows Shortcode and HTML code.', 'codevz-plus' ),
				'dependency' => [ 'cz_menu_megamenu', '==', 'custom_code' ]
			],
		];
	}
}

// Run custom menu walker
Codevz_Menu_Walker::instance();

/**
 * Add Codevz menu walker to Walker_Nav_Menu_Edit
 * @return string
 */
require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
class Codevz_Walker_Nav_Menu_Edit extends Walker_Nav_Menu_Edit {

	public function start_el( &$output, $item, $depth = 0, $args = [], $id = 0 ) {
		parent::start_el( $item_output, $item, $depth, $args );
		$new_fields = apply_filters( 'codevz_nav_menu_csf_fields', '', $item_output, $item, $depth, $args );
		$item_output = $new_fields ? preg_replace('/(?=<div[^>]+class="[^"]*submitbox)/', $new_fields, $item_output) : '';
		$output .= $item_output;
	}

}

/**
 * Add Codevz Walker into WP Walker_Nav_Menu
 * @return string
 */
class Codevz_Walker_nav extends Walker_Nav_Menu {

	public function start_el( &$output, $item, $depth = 0, $args = [], $id = 0 ) {

		$meta = $meta2 = get_post_meta( $item->ID );
		if ( empty( $meta['cz_menu_activation'][0] ) ) {
			$meta = null;
		}
		$mnt = $value = $custom = $ul_css = '';

		if ( ! empty( $meta['cz_menu_visibility'][0] ) ) {
			$is_login = is_user_logged_in();

			if ( ( $meta['cz_menu_visibility'][0] === '1' && ! $is_login ) || ( $meta['cz_menu_visibility'][0] === '2' && $is_login ) ) {
				$output .= apply_filters( 'walker_nav_menu_start_el', '', $item, $depth, $args, $id );
				return;
			}
		}

		$title = empty( $meta['cz_menu_hide_title'][0] ) ? apply_filters( 'the_title', $item->title, $item->ID ) : '';

		$indent = $depth ? str_repeat( "\t", $depth ) : '';
		
		$classes = empty( $item->classes ) ? [] : (array) $item->classes;

		$is_mega = empty( $meta2['cz_menu_megamenu'][0] ) ? '' : ' cz_parent_megamenu';
		
		if ( $is_mega && ! empty( $meta2['cz_menu_megamenu_width'][0] ) ) {
			$classes[] = $meta2['cz_menu_megamenu_width'][0];
		}

		$classes = join( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item ) );

		if ( ! empty( $meta['cz_menu_icon_type'][0] ) && ! empty( $meta['cz_menu_image'][0] ) && $meta['cz_menu_icon_type'][0] === 'image' ) {

			$icon_css = empty( $meta['cz_menu_css_icon'][0] ) ? '' : ' style="' . Codevz_Plus::sk_inline_style( $meta['cz_menu_css_icon'][0] ) . '"';
			$title = $title ? '<i class="menu_icon_image"' . $icon_css . '><img loading="lazy" src="' . $meta['cz_menu_image'][0] . '" alt="menu" /></i>' . $title : '<i class="menu_icon_image menu_icon_no_text" title="' . $title . '"' . $icon_css . '><img loading="lazy" src="' . $meta['cz_menu_image'][0] . '" alt="menu" /></i>';

		} else if ( ! empty( $meta['cz_menu_icon'][0] ) ) {
			
			$icon = $meta['cz_menu_icon'][0];
			$icon_css = empty( $meta['cz_menu_css_icon'][0] ) ? '' : ' style="' . Codevz_Plus::sk_inline_style( $meta['cz_menu_css_icon'][0] ) . '"';
			$title = $title ? '<i class="' . $icon . '"' . $icon_css . '></i>' . $title : '<i class="' . $icon . ' menu_icon_no_text" title="' . $title . '"' . $icon_css . '></i>';
		
		}

		if ( ! empty( $meta['cz_menu_custom'][0] ) ) {
			$custom = do_shortcode( $meta['cz_menu_custom'][0] );
		}

		$ul_css = ( $is_mega && empty( $meta['cz_menu_css_ul'][0] ) ) ? '' : ' data-sub-menu="' . Codevz_Plus::sk_inline_style( isset( $meta['cz_menu_css_ul'][0] ) ? $meta['cz_menu_css_ul'][0] : '' ) . '"';
		
		$classes .= $title ? '' : ' hide';
		$classes = ' class="' . esc_attr( $classes ) . $is_mega . $mnt . '"';
		$li_id = 'menu-' . $args->cz_row_id . '-' . $item->ID;
		$output .= '<li id="' . $li_id . '"' . $classes . $ul_css . '>';

		$attributes  = empty( $item->attr_title ) ? '' : ' title="'  . esc_attr( $item->attr_title ) .'"';
		$attributes .= empty( $item->target )     ? '' : ' target="' . esc_attr( $item->target     ) .'"';
		$attributes .= empty( $item->xfn )        ? '' : ' rel="'    . esc_attr( $item->xfn        ) .'"';
		$attributes .= empty( $item->url )        ? '' : ' href="'   . esc_attr( $item->url        ) .'"';
		$attributes .= ' data-title="'  . esc_attr( wp_strip_all_tags( $title ) ) .'"';

		// Menu badge.
		if ( ! empty( $meta['cz_menu_badge'][0] ) ) {
			$badge_css = empty( $meta['cz_menu_css_badge'][0] ) ? '' : ' style="' . Codevz_Plus::sk_inline_style( $meta['cz_menu_css_badge'][0] ) . '"';
			$title .= '<span class="cz_menu_badge"' . $badge_css . '>' . $meta['cz_menu_badge'][0] . '</span> ';
		}

		$attributes .= empty( $meta['cz_menu_css_label'][0] ) ? '' : ' style="' . Codevz_Plus::sk_inline_style( $meta['cz_menu_css_label'][0] ) . '"';

		$menu_more_css = empty( $meta['cz_menu_css_label_hover'][0] ) ? '' : '#' . $li_id . ' > a:hover {' . str_replace( ';', ' !important;', Codevz_Plus::sk_inline_style( $meta['cz_menu_css_label_hover'][0] ) ) . '}';
		$menu_more_css .= empty( $meta['cz_menu_css_label_shape'][0] ) ? '' : '#' . $li_id . ' > a:before{' . str_replace( ';', ' !important;', Codevz_Plus::sk_inline_style( $meta['cz_menu_css_label_shape'][0] ) ) . '}';

		$attributes .= $menu_more_css ? ' data-cz-style="' . $menu_more_css . '"' : '';
		
		$description = empty( $meta2['cz_menu_subtitle'][0] ) ? '' : '<span class="cz_menu_subtitle">' . $meta2['cz_menu_subtitle'][0] . '</span>';

		if ( $custom ) {
			$item_output = '<div class="cz_menu_custom">' . str_replace( '<p></p>', '', $custom ) . '</div>';
		} else if ( ! empty( $meta2['cz_menu_col_title'][0] ) ) {
			$item_output = '<h6>' . $title . '</h6>';
		} else {
			$item_output = $args->before;
			$item_output .= '<a' . $attributes . '>';
			$menu_icon_fa = ( Codevz_Plus::contains( $classes, 'has-children' ) && ! empty( $args->cz_indicator ) ) ? '<i class="cz_indicator fa fa-ellipsis"></i>' : '';
			$item_output .= $args->link_before . '<span>' . $title . '</span>' . $menu_icon_fa . $description . $args->link_after;
			$item_output .= '</a>';
			$item_output .= ( ! empty( $meta2['cz_menu_megamenu'][0] ) && $meta2['cz_menu_megamenu'][0] === 'custom' && ! empty( $meta2['cz_menu_megamenu_id'][0] ) ) ? '<ul class="sub-menu cz_custom_mega_menu clr">' . Codevz_Plus::get_page_as_element( $meta2['cz_menu_megamenu_id'][0] ) . '</ul>' : '';
			$item_output .= $args->after;
		}

		$output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args, $id );
	}
}