<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

/**
 * Duplicator feature for duplicate post, page, product, post types, taxonomies, and etc.
 * 
 * @since 4.9.0
 */

class Codevz_Duplicator {

	use Codevz_Plus_Instance;

	public function __construct() {

		// Handle duplicate function.
		add_action( 'admin_action_codevz_duplicate', array( $this, 'duplicate' ) );

		// Add duplicate link.
		add_filter( 'post_row_actions', 		array( $this, 'duplicate_link'), 10, 2 );
		add_filter( 'page_row_actions', 		array( $this, 'duplicate_link'), 10, 2 );
		add_filter( 'category_row_actions', 	array( $this, 'duplicate_link'), 10, 2 );
		add_filter( 'tag_row_actions', 			array( $this, 'duplicate_link'), 10, 2 );
		add_filter( 'taxonomy_row_actions', 	array( $this, 'duplicate_link'), 10, 2 );

	}

	// Handle duplicate.
	public function duplicate() {

		if ( Codevz_Plus::_GET( 'action' ) === 'codevz_duplicate' && current_user_can( 'edit_posts' ) ) {

			// Params.
			$widget_name 	= Codevz_Plus::_GET( 'widget_name' );
			$widget_id 		= intval( Codevz_Plus::_GET( 'widget_id' ) );
			$widget_sidebar = Codevz_Plus::_GET( 'widget_sidebar' );
			$post 			= Codevz_Plus::_GET( 'post' );
			$term_id 		= Codevz_Plus::_GET( 'term_id' );
			$taxonomy 		= Codevz_Plus::_GET( 'taxonomy' );

			// Duplicate widget.
			if ( $widget_name && $widget_id && $widget_sidebar ) {

				$new_widget_id 	= wp_rand( 999, 9999 );

				// Get widget.
				$widgets = get_option( 'widget_' . $widget_name );

				// Set new widget to the same sidebar.
				if ( isset( $widgets[ $widget_id ] ) ) {

					// Copy widget.
					$widgets[ $new_widget_id ] = $widgets[ $widget_id ];

					// Change new widget title.
					$widgets[ $new_widget_id ][ 'title' ] .= '(' . esc_html__( 'Copy', 'codevz-plus' ) . ')';

					// Update sidebars.
					update_option( 'widget_' . $widget_name, $widgets );

				}

				// Get all sidebars.
				$sidebars = get_option( 'sidebars_widgets' );

				// Set new widget to the same sidebar.
				if ( isset( $sidebars[ $widget_sidebar ] ) ) {

					$sidebars[ $widget_sidebar ][] = $widget_name . '-' . $new_widget_id;

					// Update sidebars.
					update_option( 'sidebars_widgets', $sidebars );

				}

				// Redirect back to the widgets page to reflect the changes
				wp_redirect( admin_url( 'widgets.php' ) );

				exit;

			// Duplicate post or page.
			} else if ( $post ) {

				$content = get_post( $post, ARRAY_A );
				$content[ 'post_title' ] .= ' (' . esc_html__( 'Copy', 'codevz-plus' ) . ')';
				$content[ 'post_status' ] = 'draft';

				$current_time = current_time( 'mysql' );
				$content['post_date'] = $current_time;
				$content['post_date_gmt'] = get_gmt_from_date( $current_time );

				if ( isset( $content[ 'ID' ] ) ) {
					unset( $content[ 'ID' ] );
				} else {
					unset( $content[ 'id' ] );
				}
				unset( $content[ 'guid' ] );
				unset( $content[ 'post_name' ] );

				$post_id = wp_insert_post( $content );

				if ( ! is_wp_error( $post_id ) ) {

					$meta = get_post_meta( $post );

					foreach( $meta as $key => $value ) {

						if ( ! empty( $value[0] ) ) {
							update_post_meta( $post_id, wp_slash( $key ), wp_slash_strings_only( maybe_unserialize( $value[0] ) ) );
						}

					}

					// Success & redirect.
					wp_redirect( admin_url( 'post.php?action=edit&post=' . $post_id ) );

				} else {

					// Got an error message.
					wp_die( esc_html( $post_id->get_error_message() ) );

				}

				exit;

			// Duplicate taxonomy.
			} else if ( $term_id && $taxonomy ) {

				$term = get_term( $term_id, $taxonomy, ARRAY_A );
				$term[ 'name' ] .= ' (' . esc_html__( 'Copy', 'codevz-plus' ) . ')';

				unset( $term[ 'slug' ] );
				unset( $term[ 'term_id' ] );

				$new_term = wp_insert_term( $term['name'], $taxonomy, $term );

				$new_term_id = $new_term['term_id'];

				if ( ! is_wp_error( $new_term_id ) ) {

					$term_meta = get_term_meta( $term_id );

					foreach( $term_meta as $key => $value ) {

						if ( ! empty( $value[0] ) ) {
							update_term_meta( $new_term_id, wp_slash( $key ), wp_slash_strings_only( maybe_unserialize( $value[0] ) ) );
						}

					}

					// Get the post type of taxonomy.
					$post_type = get_taxonomy( $taxonomy );
					$post_type = $post_type->object_type;
					$post_type = empty( $post_type ) ? 'post' : $post_type[0];

					// Success & redirect.
					wp_redirect( admin_url( 'edit-tags.php?taxonomy=' . $taxonomy . '&post_type=' . $post_type ) );

				} else {

					// Got an error message.
					wp_die( esc_html( $new_term_id->get_error_message() ) );

				}

				exit;

			}

		}

	}

	// Add duplicate links to items.
	public function duplicate_link( $actions, $object ) {

		if ( current_user_can( 'edit_posts' ) ) {

			if ( isset( $object->ID ) ) {

				$actions['duplicate'] = '<a href="' . admin_url( 'admin.php?action=codevz_duplicate&post=' . $object->ID ) . '">' . esc_html__( 'Duplicate', 'codevz-plus' ) . '</a>';

			} else if ( isset( $object->term_id ) ) {

				$actions['duplicate'] = '<a href="' . admin_url( 'admin.php?action=codevz_duplicate&term_id=' . $object->term_id . '&taxonomy=' . $object->taxonomy ) . '">' . esc_html__( 'Duplicate', 'codevz-plus' ) . '</a>';

			}

		}

		return $actions;

	}

}

Codevz_Duplicator::instance();