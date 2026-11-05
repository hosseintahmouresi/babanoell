<?php
//Delete Quick Search options from the database.
if ( defined('ABSPATH') && defined('WP_UNINSTALL_PLUGIN') ) {
	delete_site_option('ws_ame_quick_search');
	delete_option('ws_ame_quick_search');

	//Remove the database cleanup cron event.
	//Hook name must match SearchModule::DB_CLEANUP_CRON_HOOK. The constant itself is not
	//referenced here because the module is not loaded during uninstallation.
	wp_clear_scheduled_hook('ame_qs_database_cleanup');

	//Drop the crawler tables.
	function ws_ame_qs_drop_tables() {
		global $wpdb;
		/** @var wpdb $wpdb */

		$tables = ['ame_qs_items', 'ame_qs_crawler'];
		$prefixedTables = [];
		foreach ($tables as $table) {
			$prefixedTables[] = $wpdb->base_prefix . $table;
		}

		if ( !empty($prefixedTables) ) {
			//phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- AFAIK, there's no safer way to drop tables.
			$wpdb->query('DROP TABLE IF EXISTS ' . implode(', ', $prefixedTables));
			//phpcs:enable
		}
	}

	ws_ame_qs_drop_tables();
}


