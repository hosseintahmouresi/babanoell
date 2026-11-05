<?php
/**
 * Admin Base HTML.
 *
 * @package wp-schema-pro
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="schema-menu-page-wrapper">
	<div id="schema-menu-page">
		<div class="schema-menu-page-content schema-clear">
			<?php
				do_action( 'schema_render_admin_page_content', $menu_page_slug, $page_action );
			?>
		</div>
	</div>
</div>
