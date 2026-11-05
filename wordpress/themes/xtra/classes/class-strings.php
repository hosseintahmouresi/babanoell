<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

/**
 * Theme all translation strings.
 */

if ( ! class_exists( 'Codevz_Core_Strings' ) ) {

	class Codevz_Core_Strings {

		// Class instance.
		private static $instance = null;

		public function __construct() {

			add_action( 'after_setup_theme', [ $this, 'language' ] );

		}

		// Instance.
		public static function instance() {

			if ( self::$instance === null ) {

				self::$instance = new self();

			}

			return self::$instance;

		}

		// Load language(s)
		public function language() {

			$dir = get_template_directory();
			$loc = get_locale();

			load_textdomain( 'xtra', trailingslashit( $dir ) . 'languages/xtra-' . $loc . '.mo' );

			if ( is_child_theme() ) {
				load_textdomain( 'xtra', trailingslashit( $dir . '-child' ) . 'languages/xtra-' . $loc . '.mo' );
			}

		}

		// Get strings.
		public static function get( $string, $sprintf = '' ) {

			$strings = [

				'theme_name' 			=> apply_filters( 'codevz_config_name', false ),
				'codevz_plus' 			=> esc_html__( 'Codevz Plus', 'xtra' ),
				'copyright' 			=> esc_html__( '© All rights reserved, Powered by WordPress.', 'xtra' ),
				'homepage' 				=> esc_html__( 'Home page', 'xtra' ),
				'primary' 				=> esc_html__( 'Primary', 'xtra' ),
				'secondary' 			=> esc_html__( 'Secondary', 'xtra' ),
				'footer' 				=> esc_html__( 'Footer', 'xtra' ),
				'offcanvas_area' 		=> esc_html__( 'Offcanvas', 'xtra' ),
				'product_primary' 		=> esc_html__( 'Shop primary', 'xtra' ),
				'product_secondary' 	=> esc_html__( 'Shop secondary', 'xtra' ),
				'portfolio_primary' 	=> esc_html__( 'Portfolio primary', 'xtra' ),
				'portfolio_secondary' 	=> esc_html__( 'Portfolio secondary', 'xtra' ),
				'add_widgets' 			=> esc_html__( 'Add widgets here to appear in your', 'xtra' ),
				'pro' 					=> esc_html__( 'PRO', 'xtra' ),
				'author_posts' 			=> esc_html__( 'Author posts', 'xtra' ),
				'view_all_posts' 		=> esc_html__( 'View all posts', 'xtra' ),
				'not_found' 			=> esc_html__( 'Nothing Found', 'xtra' ),
				'search' 				=> esc_html__( 'Search', 'xtra' ),
				'no_comment' 			=> esc_html__( 'No comment', 'xtra' ),
				'comment' 				=> esc_html__( 'Comment', 'xtra' ),
				'comments' 				=> esc_html__( 'Comments', 'xtra' ),
				'activation' 			=> esc_html__( 'Activation', 'xtra' ),
				'importer' 				=> esc_html__( 'Demo Importer', 'xtra' ),
				'importer_page' 		=> esc_html__( 'Page Importer', 'xtra' ),
				'plugins' 				=> esc_html__( 'Install Plugins', 'xtra' ),
				'options' 				=> esc_html__( 'Theme Options', 'xtra' ),
				'status' 				=> esc_html__( 'System Status', 'xtra' ),
				'uninstall' 			=> esc_html__( 'Uninstall Demo', 'xtra' ),
				'feedback' 				=> esc_html__( 'Feedback', 'xtra' ),
				'elementor' 			=> esc_html__( 'Elementor Page Builder', 'xtra' ),
				'js_composer' 			=> esc_html__( 'WPBakery Page Builder', 'xtra' ),
				'revslider' 			=> esc_html__( 'Revolution Slider', 'xtra' ),
				'woocommerce' 			=> esc_html__( 'Woocommerce', 'xtra' ),
				'cf7' 					=> esc_html__( 'Contact Form 7', 'xtra' ),
				'wpoptimize' 			=> esc_html__( 'WP Optimize', 'xtra' ),
				'of' 					=> esc_html__( 'of', 'xtra' ),
				'close' 				=> esc_html__( 'Close', 'xtra' ),
				'plugin_before' 		=> esc_html__( 'Installing', 'xtra' ),
				'plugin_after' 			=> esc_html__( 'Activated', 'xtra' ),
				'import_before' 		=> esc_html__( 'Importing', 'xtra' ),
				'import_after' 			=> esc_html__( 'Imported', 'xtra' ),
				'downloading' 			=> esc_html__( 'Downloading', 'xtra' ),
				'demo_files' 			=> esc_html__( 'Demo Files', 'xtra' ),
				'downloaded' 			=> esc_html__( 'Downloaded', 'xtra' ),
				'widgets' 				=> esc_html__( 'Widgets', 'xtra' ),
				'slider' 				=> esc_html__( 'Revolution Slider', 'xtra' ),
				'posts' 				=> esc_html__( 'Pages & Posts', 'xtra' ),
				'images' 				=> esc_html__( 'Images', 'xtra' ),
				'error_500' 			=> esc_html__( 'PHP error 500, Internal server error, Please check your server error log file or contact with support.', 'xtra' ),
				'error_503' 			=> esc_html__( 'PHP error 503, Internal server error, Please try again with same import demo.', 'xtra' ),
				'ajax_error' 			=> esc_html__( 'An error has occured, Please deactivate all plugins except theme plugins and try again, If still have same issue, Please submit ticket to theme author.', 'xtra' ),
				'features' 				=> esc_html__( 'Choose at least one feature to import.', 'xtra' ),
				'feedback_empty' 		=> esc_html__( 'Message box is empty, Please fill the box then submit.', 'xtra' ),
				'page_importer_empty' 	=> esc_html__( 'URL input is empty, Please fill the input then submit.', 'xtra' ),
				'welcome' 				=> esc_html__( 'Welcome to %s WordPress Theme', 'xtra' ),
				'version' 				=> esc_html__( 'Current version:', 'xtra' ),
				'ref' 					=> apply_filters( 'codevz_config_buy_link', esc_html__( 'https://1.envato.market/xtratheme', 'xtra' ) ),
				'docs' 					=> apply_filters( 'codevz_config_docs', esc_html__( 'https://xtratheme.com/docs', 'xtra' ) ),
				'documentation' 		=> esc_html__( 'Documentation', 'xtra' ),
				'youtube' 				=> esc_html__( 'https://www.youtube.com/channel/UCrS1L4oeTRfU1hvIo1gJGjg/videos', 'xtra' ),
				'video_tutorials' 		=> esc_html__( 'Video Tutorials', 'xtra' ),
				'changelog' 			=> apply_filters( 'codevz_config_changelog_link', esc_html__( 'https://xtratheme.com/changelog', 'xtra' ) ),
				'change_log' 			=> esc_html__( 'Change Log', 'xtra' ),
				'ticksy' 				=> apply_filters( 'codevz_config_support_link', esc_html__( 'https://codevz.ticksy.com', 'xtra' ) ),
				'support' 				=> esc_html__( 'Support', 'xtra' ),
				'faqs' 					=> apply_filters( 'codevz_config_faq_link', esc_html__( 'https://xtratheme.com/faqs', 'xtra' ) ),
				'faq' 					=> esc_html__( 'F.A.Q', 'xtra' ),
				'certificate' 			=> esc_html__( 'Activation Certificate', 'xtra' ),
				'deregister_license' 	=> esc_html__( 'Deregister License', 'xtra' ),
				'purchase_code' 		=> esc_html__( 'Your Purchase Code', 'xtra' ),
				'purchase_date' 		=> esc_html__( 'Purchase date:', 'xtra' ),
				'support_until' 		=> esc_html__( 'Support until:', 'xtra' ),
				'support_expired' 		=> esc_html__( 'Your support has been expired, Click on below link and extend your support.', 'xtra' ),
				'extend' 				=> esc_html__( 'Buy extended support or new license', 'xtra' ),
				'license_activation' 	=> esc_html__( 'License Activation', 'xtra' ),
				'deregistered' 			=> esc_html__( 'Your license code on this website deregistered successfully.', 'xtra' ),
				'congrats' 				=> esc_html__( 'Congratulation', 'xtra' ),
				'activated' 			=> esc_html__( 'Your theme has been activated successfully.', 'xtra' ),
				'insert' 				=> esc_html__( 'Please insert a valid license code.', 'xtra' ),
				'activate_war' 			=> esc_html__( 'Please activate your theme via purchase code to access theme features, updates and demo importer.', 'xtra' ),
				'placeholder' 			=> esc_html__( 'Please insert purchase code ...', 'xtra' ),
				'activate' 				=> esc_html__( 'Activate', 'xtra' ),
				'find' 					=> esc_html__( 'How to find purchase code?', 'xtra' ),
				'buy_new' 				=> esc_html__( 'Buy new license', 'xtra' ),
				'install' 				=> esc_html__( 'Install Plugins', 'xtra' ),
				'required' 				=> esc_html__( 'Required', 'xtra' ),
				'recommended' 			=> esc_html__( 'Recommended', 'xtra' ),
				'private' 				=> esc_html__( 'Private repository', 'xtra' ),
				'premium' 				=> esc_html__( 'Premium', 'xtra' ),
				'wp' 					=> esc_html__( 'WordPress repository', 'xtra' ),
				'free_ver' 				=> esc_html__( 'Free version', 'xtra' ),
				'activated_s' 			=> esc_html__( 'Activated successfully', 'xtra' ),
				'tas' 					=> esc_html__( 'Theme activated successfully', 'xtra' ),
				'install_activate' 		=> esc_html__( 'Install & Activate', 'xtra' ),
				'installed_activated' 	=> esc_html__( 'Installed & Activated', 'xtra' ),
				'unlock' 				=> esc_html__( 'Unlock', 'xtra' ),
				'please_wait' 			=> esc_html__( 'Please wait', 'xtra' ),
				'no_plugins' 			=> esc_html__( 'You have installed all the plugins and there is no any plugin to install.', 'xtra' ),
				'filters' 				=> esc_html__( 'Fitlers:', 'xtra' ),
				'all' 					=> esc_html__( 'All Demos', 'xtra' ),
				'starter' 				=> esc_html__( 'Starter', 'xtra' ),
				'type' 					=> esc_html__( 'Type a keyword ...', 'xtra' ),
				'free' 					=> esc_html__( 'FREE', 'xtra' ),
				'import' 				=> esc_html__( 'Import', 'xtra' ),
				'uninstall' 			=> esc_html__( 'Uninstall', 'xtra' ),
				'preview' 				=> esc_html__( 'Preview', 'xtra' ),
				'back' 					=> esc_html__( 'Back to demos', 'xtra' ),
				'welcome_to' 			=> esc_html__( 'Welcome to', 'xtra' ),
				'selected' 				=> esc_html__( 'Selected demo:', 'xtra' ),
				'exclusive' 			=> esc_html__( 'Exclusive', 'xtra' ),
				'wizard' 				=> esc_html__( 'Demo Importer Wizard', 'xtra' ),
				'live_preview' 			=> esc_html__( 'Live preview:', 'xtra' ),
				'elementor_s' 			=> esc_html__( 'Elementor', 'xtra' ),
				'wpbakery' 				=> esc_html__( 'WPBakery', 'xtra' ),
				'choose' 				=> esc_html__( 'Choose page builder:', 'xtra' ),
				'choose_2' 				=> esc_html__( 'Choose Builder', 'xtra' ),
				'ata' 					=> esc_html__( 'Activate your theme with license code to access this feature.', 'xtra' ),
				'desc' 					=> esc_html__( 'By checking this field, wizard will import Arabic version of current demo that you have selected.', 'xtra' ),
				'rtl' 					=> esc_html__( 'RTL version?', 'xtra' ),
				'full_import' 			=> esc_html__( 'Full Import', 'xtra' ),
				'custom_import' 		=> esc_html__( 'Custom Import', 'xtra' ),
				'media' 				=> esc_html__( 'Images & Media', 'xtra' ),
				'imported' 				=> esc_html__( 'Your website has been imported successfully.', 'xtra' ),
				'view_website' 			=> esc_html__( 'View your website', 'xtra' ),
				'customize' 			=> esc_html__( 'Customize webiste', 'xtra' ),
				'error' 				=> esc_html__( 'Error!', 'xtra' ),
				'occured' 				=> esc_html__( 'An error has occured, Please try again.', 'xtra' ),
				'troubleshooting' 		=> esc_html__( 'Troubleshooting', 'xtra' ),
				'prev_step' 			=> esc_html__( 'Prev Step', 'xtra' ),
				'getting_started' 		=> esc_html__( 'Getting Started', 'xtra' ),
				'config' 				=> esc_html__( 'Configuration', 'xtra' ),
				'importing' 			=> esc_html__( 'Please wait, Importing', 'xtra' ),
				'ready' 				=> esc_html__( 'Ready to go!', 'xtra' ),
				'next_step' 			=> esc_html__( 'Next Step', 'xtra' ),
				'single_page' 			=> esc_html__( 'Single Page Importer', 'xtra' ),
				'page_pro' 				=> esc_html__( 'Page importer feature is available only when you %s activate your theme with a valid license code.', 'xtra' ),
				'page_import_war' 		=> esc_html__( 'The demo page you want to import may have a second color, To avoid the color problem, set a second color for your site from Theme Options > General > Colors', 'xtra' ),
				'page_insert' 			=> esc_html__( 'Insert a demo page URL and click on import button then wait for the process to complete.', 'xtra' ),
				'page_insert_link' 		=> esc_html__( 'Insert the demo link ...', 'xtra' ),
				'activation_error' 		=> esc_html__( 'Please activate your theme via purchase code to access theme features, updates and demo importer.', 'xtra' ),
				'valid_url' 			=> esc_html__( 'Please insert a valid URL', 'xtra' ),
				'allow_url_fopen' 		=> esc_html__( 'Enable allow_url_fopen on your server then you can import page.', 'xtra' ),
				'page_imported' 		=> esc_html__( 'Page imported successfully.', 'xtra' ),
				'try_again' 			=> esc_html__( 'Error, Please try again ...', 'xtra' ),
				'responding' 			=> esc_html__( 'Server not responding, Please make sure your link is valid.', 'xtra' ),
				'wrong' 				=> esc_html__( 'Something went wrong, Please try again ...', 'xtra' ),
				'status' 				=> esc_html__( 'System Status', 'xtra' ),
				'good' 					=> esc_html__( 'Good', 'xtra' ),
				'not_active' 			=> esc_html__( 'Theme is not activated', 'xtra' ),
				'php_ver' 				=> esc_html__( 'Server PHP Version', 'xtra' ),
				'php_error' 			=> esc_html__( 'PHP 8.0 or above recommended', 'xtra' ),
				'php_memory' 			=> esc_html__( 'Server PHP Memory Limit', 'xtra' ),
				'128m' 					=> esc_html__( '128M recommended', 'xtra' ),
				'8r' 					=> esc_html__( '8 recommended', 'xtra' ),
				'30r' 					=> esc_html__( '30 recommended', 'xtra' ),
				'max_size' 				=> esc_html__( 'Server PHP Post Max Size', 'xtra' ),
				'execution' 			=> esc_html__( 'Server PHP Max Execution Time', 'xtra' ),
				'server_php' 			=> esc_html__( 'Server PHP', 'xtra' ),
				'curl' 					=> esc_html__( 'PHP cURL or allow_url_fopen is required.', 'xtra' ),
				'active' 				=> esc_html__( 'Active', 'xtra' ),
				'contact' 				=> esc_html__( 'Contact with your server support.', 'xtra' ),
				'feedback' 				=> esc_html__( 'Feedback', 'xtra' ),
				'please_help' 			=> esc_html__( 'Please help us improve the "%s" theme, we have added a feedback form, you can send us your comments and criticisms.', 'xtra' ),
				'thanks' 				=> esc_html__( 'Thanks for purchasing the "%s" theme; to improve the theme, through the following form, you can send your feedback such as report a bug, request a feature, request a demo, ask non-support questions, etc.', 'xtra' ),
				'submit' 				=> esc_html__( 'Submit', 'xtra' ),
				'sent' 					=> esc_html__( 'Your message has been sent successfully.', 'xtra' ),
				'sent_error' 			=> esc_html__( 'Could not send your message, Please try again.', 'xtra' ),
				'no_msg' 				=> esc_html__( 'There is no message to send, Please try again.', 'xtra' ),
				'un_demos' 				=> esc_html__( 'Uninstall Demo(s)', 'xtra' ),
				'un_desc' 				=> esc_html__( 'In this list you can see demos imported on your site previously, You can uninstall any demo data.', 'xtra' ),
				'yet' 					=> esc_html__( 'You have not imported any demo yet.', 'xtra' ),
				'are_you_sure' 			=> esc_html__( 'Are you sure for this?', 'xtra' ),
				'delete' 				=> esc_html__( 'This will be deleted all your website data such as posts, pages, attachments, theme options, sliders, etc. and there is no undo button for this action.', 'xtra' ),
				'no' 					=> esc_html__( 'No, never mind', 'xtra' ),
				'uninstalling' 			=> esc_html__( 'Uninstalling, Please wait', 'xtra' ),
				'yes' 					=> esc_html__( 'Yes please', 'xtra' ),
				'uninstalled' 			=> esc_html__( 'Demo "DEMONAME" uninstalled successfully.', 'xtra' ),
				'reload' 				=> esc_html__( 'Reload page', 'xtra' ),
				'envato_error' 			=> esc_html__( 'Envato error: Your license code is invalid.', 'xtra' ),
				'envato_api' 			=> esc_html__( 'Envato API error:', 'xtra' ),
				'envato_exist' 			=> esc_html__( 'Envato error: The purchase code does not exist.', 'xtra' ),
				'envato_http' 			=> esc_html__( 'Envato error: Failed to validate code due to an HTTP error', 'xtra' ),
				'envato_10sec' 			=> esc_html__( 'Envato error: Please try again in 10 seconds.', 'xtra' ),
				'envato_parsing' 		=> esc_html__( 'Envato error: Parsing response.', 'xtra' ),
				'envato_another' 		=> esc_html__( 'Envato error: Your purchase code is valid but it seems its for another item, Please add correct purchase code.', 'xtra' ),
				'envato_check' 			=> esc_html__( 'Your license not found in our database, Please check your license and try again ...', 'xtra' ),
				'ajax_error' 			=> esc_html__( 'AJAX requested name is empty, Please try again.', 'xtra' ),
				'redirected' 			=> esc_html__( 'Redirected successfully.', 'xtra' ),
				'find_plugin' 			=> esc_html__( 'Could not find plugin "%s" API, Please refresh page and try again.', 'xtra' ),
				'cp_error' 				=> esc_html__( 'Codevz plus plugin is not installed or activated.', 'xtra' ),
				'listed' 				=> esc_html__( 'Plugin "%s" is no listed as a valid plugin.', 'xtra' ),
				'ftp' 					=> esc_html__( 'WordPress required FTP login details', 'xtra' ),
				'wp_api' 				=> esc_html__( 'WordPress API Error:', 'xtra' ),
				'manually' 				=> esc_html__( 'Could not download "%s" plugin ZIP file, Please go to Appearance > Install Plugins and install it manually, and try again demo importer.', 'xtra' ),
				'300s' 					=> esc_html__( 'Error, Through FTP delete plugins > "%s" folder & increase PHP max_execution_time to 300 then try again.', 'xtra' ),
				'plugin_error' 			=> esc_html__( 'Plugin activation error, ', 'xtra' ),
				'plugin_installed' 		=> esc_html__( 'Plugin "%s" installed and activated successfully.', 'xtra' ),
				'plugin_api' 			=> esc_html__( 'Plugin API error:', 'xtra' ),
				'demo_uninstalled' 		=> esc_html__( 'Demo "%s" uninstalled successfully.', 'xtra' ),
				'uninstall_error' 		=> esc_html__( 'Could not uninstall "%s" demo.', 'xtra' ),
				'search_error' 			=> esc_html__( 'Sorry, but nothing matched your search terms. Please try again with some different keywords.', 'xtra' ),
				'slider_placeholder' 	=> esc_html__( 'Please install and activate Slider Revolution Plugin from Dashboard > %s > Install Plugins', 'xtra' ),
				'slider_select' 		=> esc_html__( 'Please edit your page in the backend and from Page settings > Header settings, Select slider name', 'xtra' ),
				'slider_elementor' 		=> esc_html__( 'This area is placeholder for Slider, you can customize the slider on Dashboard > Slider Revolution', 'xtra' ),
				'post_meta_source' 		=> esc_html__( 'Source', 'xtra' ),
				'post_meta_author' 		=> esc_html__( 'Posted by', 'xtra' ),
				'post_meta_date' 		=> esc_html__( 'Published on', 'xtra' ),
				'post_meta_cats' 		=> esc_html__( 'Category(s)', 'xtra' ),
				'post_meta_tags' 		=> esc_html__( 'Tags', 'xtra' ),
				'post_meta_views' 		=> esc_html__( 'Views', 'xtra' ),
				'add_to_wishlist' 		=> esc_html__( 'Add to wishlist', 'xtra' ),
				'browse_wishlist' 		=> esc_html__( 'Browse wishlist', 'xtra' ),
				'service' 				=> esc_html__( 'Sevices', 'xtra' ),
				'shop' 					=> esc_html__( 'Shop', 'xtra' ),
				'blog' 					=> esc_html__( 'Blog', 'xtra' ),

			];

			return isset( $strings[ $string ] ) ? sprintf( $strings[ $string ], $sprintf ) : '';

		}

	}

	Codevz_Core_Strings::instance();

}