<?php

namespace YahnisElsts\AdminMenuEditor\QuickSearch;

use YahnisElsts\AdminMenuEditor\Customizable\SettingCondition;
use YahnisElsts\AdminMenuEditor\Customizable\SettingsForm;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\AbstractSettingsDictionary;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\ModuleSettings;
use YahnisElsts\WpDependencyWrapper\v1\ScriptDependency;

require_once __DIR__ . '/../../../includes/reflection-callable.php';

class SearchModule extends \amePersistentModule implements \ameExportableModule {
	const DEFAULT_SHORTCUT = 'shift shift';

	const RECENCY_TRACKING_COOKIE = 'ame-qs-recency-tracking';
	const USED_ITEM_COOKIE = 'ame-qs-used-db-items';
	const NAVIGATION_SELECTOR_PARAM = 'ame-qs-target-selector';

	const DB_CLEANUP_CRON_HOOK = 'ame_qs_database_cleanup';
	const STALENESS_THRESHOLD_IN_DAYS = 56;

	protected $optionName = 'ws_ame_quick_search';
	protected $tabOrder = 25;

	protected $defaultSettings = [
		'keyboardShortcut'  => 'shift shift',
		'recencyTracking'   => 'enableOnFirstUse',
		'crawlerEnabled'    => 'ask',
		'crawlerTabVisible' => false,
		'toolbarButton'     => true,
		'toolbarButtonType' => 'iconAndText',
	];

	/**
	 * @var null|SettingsForm
	 */
	protected $settingsForm = null;
	protected $settingsFormAction = 'ame_save_quick_search_settings';

	protected $tabSlug = 'quick-search-settings';
	protected $tabTitle = 'Quick Search';

	/**
	 * @var DbAdapter|null
	 */
	private $dbAdapter = null;

	public function __construct($menuEditor) {
		$this->settingsWrapperEnabled = true;
		parent::__construct($menuEditor);

		add_action('admin_enqueue_scripts', [$this, 'enqueueGlobalAdminDependencies'], 30);

		if ( is_admin() ) {
			add_action('admin_bar_menu', [$this, 'addToolbarSearchButton']);

			$api = new AjaxApi($this, [$this, 'maybeScheduleCleanupEvent']);
			$api->registerAjaxActions();
		}

		add_action('wp_loaded', function () {
			$this->storeDataFromCookies();
		});

		add_action(self::DB_CLEANUP_CRON_HOOK, [$this, 'cleanupDatabase']);
	}

	public function enqueueGlobalAdminDependencies($hookSuffix = '') {
		if ( $this->isSearchDisabledForRequest() ) {
			return;
		}

		if ( !$this->userCanSearch() ) {
			return;
		}

		$baseDeps = $this->menuEditor->get_base_dependencies();

		//Mousetrap library for keyboard shortcuts.
		$hotkeyLibrary = $this->registerLocalScript('ame-mousetrap', 'mousetrap.min.js', [], true);
		//Preserve the original "Mousetrap" global variable in case another plugin uses
		//a different version of this library.
		$hotkeyLibrary->addInlineScript(
			'var wsAmeOriginalMousetrap = window.Mousetrap;',
			'before'
		);
		$hotkeyLibrary->addInlineScript(
			'var wsAmeMousetrap = Mousetrap;
			 if (typeof wsAmeOriginalMousetrap !== "undefined") { window.Mousetrap = wsAmeOriginalMousetrap; }',
			'after'
		);

		//The main script only works with Webpack because it uses NPM packages.
		try {
			$mainScript = $this->menuEditor->get_webpack_registry()->getWebpackEntryPoint('quick-search');
		} catch (\Exception $e) {
			//Bail if the script is not available.
			return;
		}

		$this->storeDataFromCookies();
		$settings = $this->loadSettings();

		$removableQueryArgs = wp_removable_query_args();
		$removableQueryArgs[] = 'return'; //For Theme Customizer links.

		$crawlerEnabledCode = \ameUtils::get($settings, 'crawlerEnabled', 'ask');
		$detectComponents = $crawlerEnabledCode !== 'disabled';
		$menuUrlsToComponents = $this->getNormalizedAdminMenuUrls($removableQueryArgs, $detectComponents);
		$menuUrls = array_keys($menuUrlsToComponents);

		//If navigation to a selector has been requested, verify the nonce and pass the selector
		//to the client-side code.
		$selector = null;
		if ( isset($_GET[self::NAVIGATION_SELECTOR_PARAM]) ) {
			//phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce gets verified below.
			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Custom JSON data, cannot sanitize with WP functions.
			$encodedTarget = stripslashes((string)$_GET[self::NAVIGATION_SELECTOR_PARAM]);
			$parsedTarget = json_decode($encodedTarget, true);
			if (
				isset($parsedTarget['selector'], $parsedTarget['nonce'])
				&& is_string($parsedTarget['nonce'])
			) {
				if ( wp_verify_nonce($parsedTarget['nonce'], self::NAVIGATION_SELECTOR_PARAM) ) {
					$selector = $parsedTarget['selector'];
				}
			}
			//phpcs:enable
		}

		$keyboardShortcut = \ameUtils::get($settings, 'keyboardShortcut', self::DEFAULT_SHORTCUT);
		if ( $keyboardShortcut === '_custom' ) {
			$keyboardShortcut = \ameUtils::get($settings, 'customShortcut', self::DEFAULT_SHORTCUT);
		}
		if ( empty($keyboardShortcut) || !is_string($keyboardShortcut) ) {
			$keyboardShortcut = self::DEFAULT_SHORTCUT;
		}

		$mainScript
			->addDependencies(
				$hotkeyLibrary,
				$baseDeps['ame-knockout'], $baseDeps['ame-ko-extensions'],
				$baseDeps['ame-mini-functional-lib'],
				'jquery', 'jquery-ui-position', 'jquery-ui-resizable'
			)
			->setTypeToModule()
			->setInFooter()
			->addJsVariable('wsAmeQuickSearchData', [
				'jsLogLevel'       => defined('WP_DEBUG') && WP_DEBUG ? 'debug' : 'warn',
				'keyboardShortcut' => $keyboardShortcut,

				'ajaxUrl'                => admin_url('admin-ajax.php'),
				'searchNonce'            => wp_create_nonce(AjaxApi::AJAX_RUN_SEARCH),
				'indexUpdateNonce'       => wp_create_nonce(AjaxApi::AJAX_UPDATE_INDEX),
				'setCrawlerEnabledNonce' => wp_create_nonce(AjaxApi::AJAX_SET_CRAWLER_ENABLED),

				'adminUrl'        => self_admin_url(),
				'siteCookiePath'  => SITECOOKIEPATH,
				'adminCookiePath' => ADMIN_COOKIE_PATH,
				'currentUserId'   => get_current_user_id(),

				'navigationNonce'          => wp_create_nonce(self::NAVIGATION_SELECTOR_PARAM),
				'navigationTargetSelector' => $selector,

				'removableQueryArgs' => $removableQueryArgs,
				'preloadedItems'     => $this->preloadSearchableItems($menuUrls),

				'recencyTracking' => $settings['recencyTracking'],

				'crawlerConfig' => [
					'enabled'                             => $crawlerEnabledCode,
					'ajaxNonces'                          => [
						AjaxApi::AJAX_GET_CRAWL_RECORDS => wp_create_nonce(AjaxApi::AJAX_GET_CRAWL_RECORDS),
						AjaxApi::AJAX_SET_CRAWL_RECORDS => wp_create_nonce(AjaxApi::AJAX_SET_CRAWL_RECORDS),
					],
					'preloadedRecords'                    => $this->getDbAdapter()->fetchCrawlRecords($menuUrls),
					'menuComponents'                      => $detectComponents ? $menuUrlsToComponents : (new \stdClass()),
					'unknownComponentCrawlIntervalInDays' => 14,
					'knownComponentCrawlIntervalInDays'   => 28,
					'minCrawlIntervalInHours'             => 24,
					'crawlerTabVisible'                   => \ameUtils::get($settings, 'crawlerTabVisible', false),
				],
			])
			->enqueue();

		$this->enqueueLocalStyle('ame-quick-search', 'quick-search-styles.css');

		//Add the Knockout templates to the admin footer.
		add_action('admin_footer', [$this, 'printKnockoutTemplates']);

		if ( $hookSuffix ) {
			//Provide stats like memory usage and page generation time to the client-side code.
			add_action('admin_footer-' . $hookSuffix, [$this, 'printPageStatsForJs']);

			//We're using the "admin_footer-{hook_suffix}" hook because it's nearly the last action
			//that fires during an admin page load. Technically, there's also "shutdown", but that
			//happens after the closing </html> tag, so we probably shouldn't output anything there.
		}
	}

	/**
	 * Generate a list of items that should be preloaded into the client-side search index
	 * for the current admin page.
	 *
	 * These items will be available for searching as soon as the user opens the quick search box,
	 * without any additional AJAX requests. However, preloading too many items could slow down
	 * the page load.
	 *
	 * @param string[] $menuUrls
	 * @return array
	 */
	private function preloadSearchableItems($menuUrls) {
		$recentItems = $this->getDbAdapter()->getRecentlyUsedDashboardItems();

		//Exclude items where the corresponding menu URL is not present in the admin menu.
		//We don't want to show items that are not actually accessible.
		$menuUrlLookup = array_flip($menuUrls);
		$recentItems = array_filter($recentItems, function (DashboardItemDefinition $item) use ($menuUrlLookup) {
			return isset($menuUrlLookup[$item->getMenuUrl()]);
		});

		$shortcuts = $this->generatePredefinedMenuShortcuts();

		return array_merge($recentItems, $shortcuts);
	}

	/**
	 * Get the normalized, relative URLs of the admin menu items that are currently present
	 * in the admin menu.
	 *
	 * This method only returns URLs that lead to local admin pages. It excludes external URLs
	 * and URLs that lead to the front end. Absolute URLs are converted to relative URLs,
	 * and known temporary query parameters like "updated" are removed.
	 *
	 * Optionally, the method can also detect the component (e.g. a plugin or theme) that renders
	 * the content of the admin page. The component ID includes the version number.
	 *
	 * @param string[] $removableQueryArgs
	 * @param bool $withComponents
	 * @return array<string, string|null> [relative URL => component]
	 */
	private function getNormalizedAdminMenuUrls($removableQueryArgs, $withComponents = false) {
		//todo: Maybe refactor this and related methods into a separate class. AdminMenuDataCollector/Detector or something.
		static $cachedUrls = null;
		if ( $cachedUrls !== null ) {
			return $cachedUrls;
		}

		$adminUrlParsed = wp_parse_url(self_admin_url());
		if ( empty($adminUrlParsed) || empty($adminUrlParsed['path']) ) {
			return [];
		}

		if ( did_action('admin_menu') || did_action('network_admin_menu') ) {
			$tree = $this->menuEditor->get_active_admin_menu_tree();
		} else {
			$tree = [];
		}
		$outputs = [];

		\ameMenu::for_each($tree, function ($menu) use ($adminUrlParsed, $removableQueryArgs, $withComponents, &$outputs) {
			$url = \ameMenuItem::get($menu, 'url');
			if ( empty($url) ) {
				return;
			}

			$parsingResult = $this->getRelativeAdminPageUrl($url, $adminUrlParsed, $removableQueryArgs);
			if ( $parsingResult ) {
				list($relativeUrl, $parsedUrl) = $parsingResult;

				if ( $withComponents ) {
					$outputs[$relativeUrl] = $this->detectComponentThatRendersMenuContent($menu, $parsedUrl);
				} else {
					$outputs[$relativeUrl] = null;
				}
			}
		});

		return $outputs;
	}

	/**
	 * @param string $inputUrl
	 * @param array $parsedAdminUrl
	 * @param string[] $removableQueryArgs
	 * @return array{0: string, 1:array}|null
	 */
	private function getRelativeAdminPageUrl($inputUrl, $parsedAdminUrl, $removableQueryArgs) {
		$parsedInputUrl = wp_parse_url($inputUrl);
		if ( empty($parsedInputUrl) ) {
			return null;
		}

		//Is the input an absolute-ish URL?
		if (
			!empty($parsedInputUrl['scheme'])
			|| !empty($parsedInputUrl['host'])
			|| !empty($parsedInputUrl['port'])
		) {
			//Scheme, host, and port must match the admin URL.
			if (
				(\ameUtils::get($parsedInputUrl, 'scheme') !== \ameUtils::get($parsedAdminUrl, 'scheme'))
				|| (\ameUtils::get($parsedInputUrl, 'host') !== \ameUtils::get($parsedAdminUrl, 'host'))
				|| (\ameUtils::get($parsedInputUrl, 'port') !== \ameUtils::get($parsedAdminUrl, 'port'))
			) {
				return null;
			}

			//Remove the scheme, host, and port. This effectively converts the URL to a relative
			//URL (relative to the root of the site).
			unset($parsedInputUrl['scheme'], $parsedInputUrl['host'], $parsedInputUrl['port']);
		}

		if ( !empty($parsedInputUrl['path']) ) {
			//If the path starts at the root, it must start with the admin URL path.
			if ( substr($parsedInputUrl['path'], 0, 1) === '/' ) {
				$adminPath = $parsedAdminUrl['path'];
				if ( strpos($parsedInputUrl['path'], $adminPath) !== 0 ) {
					return null;
				}
				//Remove the admin path from the URL.
				$parsedInputUrl['path'] = substr($parsedInputUrl['path'], strlen($adminPath));
			}
		}

		//Remove known temporary query parameters.
		if ( !empty($parsedInputUrl['query']) ) {
			$query = wp_parse_args($parsedInputUrl['query']);
			$query = array_diff_key($query, array_flip($removableQueryArgs));
			if ( empty($query) ) {
				unset($parsedInputUrl['query']);
			} else {
				$parsedInputUrl['query'] = http_build_query($query);
			}
		}

		//Rebuild the URL.
		$relativeUrl = '';
		if ( !empty($parsedInputUrl['path']) ) {
			$relativeUrl .= $parsedInputUrl['path'];
		}
		if ( !empty($parsedInputUrl['query']) ) {
			$relativeUrl .= '?' . $parsedInputUrl['query'];
		}
		//Note that we don't include the fragment.

		return [$relativeUrl, $parsedInputUrl];
	}

	private function detectComponentThatRendersMenuContent($menuItem, $parsedUrl) {
		static $wordPressVersion = null;
		if ( $wordPressVersion === null ) {
			$wordPressVersion = get_bloginfo('version');
		}

		$isWpAdminFile = !empty($parsedUrl['path']) && \ameMenuItem::is_wp_admin_file($parsedUrl['path']);

		//If the URL points to a PHP file in wp-admin and there's no query string, it's normally
		//a built-in WordPress admin page.
		if ( $isWpAdminFile && empty($parsedUrl['query']) ) {
			return 'wordpress:' . $wordPressVersion;
		}

		if ( !\ameUtils::get($menuItem, 'is_plugin_page') ) {
			//We can detect which plugin or theme created an admin page by getting the callback
			//that renders the page and looking at the file path.

			$defaults = \ameUtils::get($menuItem, 'defaults');
			$pageHook = get_plugin_page_hook(
				(string)\ameUtils::get($defaults, 'file', ''),
				(string)\ameUtils::get($defaults, 'parent', '')
			);

			if ( !empty($pageHook) ) {
				$reflections = \ameReflectionCallable::getHookReflections($pageHook);

				//Only look at the first hook callback. Technically, there can be multiple callbacks
				//for any hook, but a menu item will normally have only one. If there are multiple,
				//we cannot determine which one actually renders the page.
				if ( !empty($reflections[0]) ) {
					$path = $reflections[0]->getFileName();
					$component = \ameUtils::getComponentFromPath($path);

					if ( $component ) {
						$version = $this->getComponentVersion($component['type'], $component['path']);
						if ( $version ) {
							return $component['type'] . ':' . $component['path'] . ':' . $version;
						}
					}
				}
			}
		}

		//Technically, we may also be able to detect a component from custom post types and taxonomies,
		//but that's more complicated. We don't do that right now.

		return null;
	}

	private function getComponentVersion($type, $directoryOrFile) {
		static $cachedPluginVersions = null;
		static $cachedThemeVersions = [];

		switch ($type) {
			case 'wordpress':
				return get_bloginfo('version');
			case 'plugin':
				if ( $cachedPluginVersions === null ) {
					$plugins = get_plugins();
					//The $plugins array is indexed by the relative path to the main plugin file,
					//not by the directory name. We only get the directory, so we need to do search
					//the whole array. For performance, we cache the results.
					$cachedPluginVersions = [];
					foreach ($plugins as $path => $plugin) {
						$version = \ameUtils::get($plugin, 'Version');
						if ( $version ) {
							//Note: In rare cases, a plugin might be a single file in the root directory.
							//In that case, we use the file name as the component path.
							$parts = explode('/', $path);
							$path = $parts[0];
							$cachedPluginVersions[$path] = $version;
						}
					}
				}
				return \ameUtils::get($cachedPluginVersions, $directoryOrFile);
			case 'theme':
				if ( !array_key_exists($directoryOrFile, $cachedThemeVersions) ) {
					$theme = wp_get_theme($directoryOrFile);
					if ( $theme->exists() ) {
						$cachedThemeVersions[$directoryOrFile] = $theme->get('Version');
					} else {
						$cachedThemeVersions[$directoryOrFile] = null;
					}
				}
				return $cachedThemeVersions[$directoryOrFile];
		}

		return null;
	}

	private function generatePredefinedMenuShortcuts() {
		$makePluginsShortcut = function ($item) {
			static $done = false;
			if ( $done ) {
				return;
			}
			$done = true;

			$filters = [
				'Active Plugins'          => 'active',
				'Inactive Plugins'        => 'inactive',
				'Recently Active Plugins' => 'recently_activated',
				': Must-Use Plugins'      => 'mustuse',
				': Drop-ins'              => 'dropins',
				': Update Available'      => 'upgrade',

				': Auto-updates Enabled'  => 'auto-update-enabled',
				': Auto-updates Disabled' => 'auto-update-disabled',
			];

			$menuTitle = \ameMenuTemplateBuilder::sanitizeMenuTitle(
				\ameMenuItem::get($item, 'menu_title', 'Plugins')
			);

			foreach ($filters as $label => $filter) {
				$params = ['plugin_status' => $filter];
				$relativeUrl = add_query_arg($params, 'plugins.php');

				if ( substr($label, 0, 1) === ':' ) {
					$label = $menuTitle . $label;
				}

				yield new DashboardItemDefinition(
					$label,
					new DashboardItemOrigin('plugins.php'),
					new DashboardItemTarget('filter', $relativeUrl),
					'f:url=' . $relativeUrl,
					[]
				);
			}
		};

		$generators = [
			'plugins.php>plugins.php' => $makePluginsShortcut,
			'>plugins.php'            => $makePluginsShortcut,
		];

		$shortcuts = [];

		if ( did_action('admin_menu') || did_action('network_admin_menu') ) {
			$tree = $this->menuEditor->get_active_admin_menu_tree();
		} else {
			$tree = [];
		}
		\ameMenu::for_each($tree, function ($menu) use ($generators, &$shortcuts) {
			$id = \ameMenuItem::get($menu, 'template_id');
			if ( isset($generators[$id]) ) {
				foreach ($generators[$id]($menu) as $link) {
					$shortcuts[] = $link;
				}
			}
		});

		return $shortcuts;
	}

	private function storeDataFromCookies() {
		if ( !$this->userCanUpdateIndex() ) {
			return;
		}

		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		//Update recency tracking settings based on the cookie. This setting is modified by the UI
		//and stored in a cookie, then we persist it in the database during the next request.
		if ( isset($_COOKIE[self::RECENCY_TRACKING_COOKIE]) ) {
			$settings = $this->loadSettings();
			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated against a list of known values below.
			$recencyTracking = (string)($_COOKIE[self::RECENCY_TRACKING_COOKIE]);
			$validValues = ['enableOnFirstUse', 'disabled', 'enabled'];
			if (
				in_array($recencyTracking, $validValues, true)
				&& ($recencyTracking !== $settings['recencyTracking'])
			) {
				$this->settings['recencyTracking'] = $recencyTracking;
				$this->saveSettings();
			}
			//Remove the cookie.
			setcookie(self::RECENCY_TRACKING_COOKIE, '', time() - 24 * 3600, ADMIN_COOKIE_PATH, COOKIE_DOMAIN);
		}

		//Store "last used" timestamps for dashboard items.
		if ( isset($_COOKIE[self::USED_ITEM_COOKIE]) ) {
			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated & sanitized below.
			$usedItems = json_decode(stripslashes($_COOKIE[self::USED_ITEM_COOKIE]), true);
			if ( is_array($usedItems) ) {
				$updates = [];
				$now = time();
				$minTimestamp = $now - 30 * 24 * 3600;
				$maxTimestamp = $now + 3600;
				foreach ($usedItems as $key => $value) {
					//Value should be a reasonable Unix timestamp.
					if ( !is_int($value) || ($value < $minTimestamp) || ($value > $maxTimestamp) ) {
						continue;
					}

					//The key should be a menu URL + relative ID, separated by a newline.
					$parts = explode("\n", $key, 2);
					if ( count($parts) !== 2 ) {
						continue;
					}
					if ( empty($parts[0]) || empty($parts[1]) ) {
						continue;
					}

					$updates[] = [
						'menuUrl'    => $parts[0],
						'relativeId' => $parts[1],
						'timestamp'  => $value,
					];
				}

				$this->getDbAdapter()->updateRecentlyUsedDashboardItems($updates);
			}
			//Remove the cookie.
			setcookie(self::USED_ITEM_COOKIE, '', time() - 24 * 3600, ADMIN_COOKIE_PATH, COOKIE_DOMAIN);
		}
	}

	public function printKnockoutTemplates() {
		$settingsPageUrl = $this->getTabUrl();
		require __DIR__ . '/ko-templates.php';
	}

	public function getDbAdapter() {
		if ( $this->dbAdapter === null ) {
			require_once __DIR__ . '/database.php';
			$this->dbAdapter = new DbAdapter();
		}
		return $this->dbAdapter;
	}

	public function printPageStatsForJs() {
		$pageGenerationTime = timer_stop(0);
		if ( isset($_SERVER['REQUEST_TIME_FLOAT']) && is_numeric($_SERVER['REQUEST_TIME_FLOAT']) ) {
			$pageGenerationTime = microtime(true) - floatval($_SERVER['REQUEST_TIME_FLOAT']);
		}

		$stats = [
			'phpPeakMemoryUsage' => memory_get_peak_usage(),
			'phpMemoryLimit'     => ini_get('memory_limit'),
			'pageGenerationTime' => $pageGenerationTime,
		];

		?>
		<script>
			window.wsAmeQuickSearchPageStats = window.wsAmeQuickSearchPageStats || [];
			<?php foreach ($stats as $key => $value): ?>
			window.wsAmeQuickSearchPageStats.push(<?php echo wp_json_encode([$key, $value]); ?>);
			<?php endforeach; ?>
		</script>
		<?php
	}

	public function createSettingInstances(ModuleSettings $settings) {
		$f = $settings->settingFactory();

		return [
			$f->stringEnum(
				'keyboardShortcut',
				['shift shift', 'ctrl+k', '/', '_custom'],
				'Keyboard shortcut'
			)
				->describeChoice('shift shift', 'Press <kbd>Shift</kbd> twice')
				->describeChoice('ctrl+k', '<kbd>Ctrl+K</kbd>')
				->describeChoice('/', '<kbd>/</kbd>')
				->describeChoice('_custom', 'Custom'),
			$f->string(
				'customShortcut',
				'Custom keyboard shortcut',
				['maxLength' => 50]
			),
			$f->stringEnum(
				'recencyTracking',
				['enableOnFirstUse', 'enabled', 'disabled',],
				'Remember recently used items'
			)
				->describeChoice('enableOnFirstUse', 'Automatically enable on first search'),
			$f->stringEnum(
				'crawlerEnabled',
				['ask', 'enabled', 'disabled',],
				'Automatic indexing'
			)
				->describeChoice('ask', 'Ask in the search screen'),
			$f->boolean(
				'crawlerTabVisible',
				'Show the "Crawler" tab in the search panel (for debugging)',
				['groupTitle' => '"Crawler" tab']
			),

			$f->boolean(
				'toolbarButton',
				'Add a search button to the Toolbar in the admin dashboard',
				['groupTitle' => 'Toolbar button']
			),
			$f->stringEnum(
				'toolbarButtonType',
				['iconAndText', 'iconOnly'],
				'Button style'
			)
				->describeChoice('iconAndText', 'Icon and text')
				->describeChoice('iconOnly', 'Just an icon'),
		];
	}

	protected function getInterfaceStructure() {
		$settings = $this->loadSettings();
		$b = $settings->elementBuilder();

		$shortcutSetting = $settings->getSetting('keyboardShortcut');
		$toolbarButtonSetting = $settings->getSetting('toolbarButton');

		$structure = $b->structure(
			$b->group(
				'Keyboard shortcut',
				$b->radioGroup('keyboardShortcut'),
				$b->auto('customShortcut')->enabled(
					new SettingCondition($shortcutSetting, '==', '_custom')
				)->inputClasses('ame-qs-custom-shortcut'),
				$b->html(
					'<p class="description ame-qs-shortcut-syntax">
						Modifiers: <kbd>ctrl</kbd>, <kbd>alt</kbd>, <kbd>shift</kbd>, <kbd>meta</kbd><br>
						Special keys: <kbd>space</kbd>, <kbd>tab</kbd>, <kbd>esc</kbd>, <kbd>ins</kbd>,
						              <kbd>left</kbd>, <kbd>up</kbd>, <kbd>right</kbd>, etc.<br>
						Examples: <kbd>ctrl+shift+k</kbd>, <kbd>a+b</kbd>, <kbd>a b c</kbd> (key sequence)
					 </p>'
				),
				$b->html(
					'<p class="ame-qs-shortcut-test-container">
						<button type="button" class="button button-secondary" id="ame-qs-test-shortcut"
							data-bind="click: $root.toggleHotkeyTest.bind($root), 
							text: hotkeyTestingInProgress() ? \'Stop Test\' : \'Test Shortcut\'">Test Shortcut</button>
						<span class="ame-qs-shortcut-test-status" data-bind="text: hotkeyTestStatus">...</span>
					</p>'
				)
			),
			$b->group(
				'Toolbar button',
				$b->auto($toolbarButtonSetting),
				$b->radioGroup('toolbarButtonType')
					->classes('ame-qs-toolbar-button-type')
					->enabled(
						new SettingCondition($toolbarButtonSetting, 'truthy', null)
					)
			),
			$b->auto('crawlerEnabled'),
			$b->auto('crawlerTabVisible'),
			$b->auto('recencyTracking')
		);
		return $structure->build();
	}

	protected function getSettingsForm() {
		if ( $this->settingsForm === null ) {
			$this->settingsForm = SettingsForm::builder($this->settingsFormAction)
				->id('ame-quick-search-settings-form')
				->settings($this->loadSettings()->getRegisteredSettings())
				->structure($this->getInterfaceStructure())
				->submitUrl($this->getTabUrl(['noheader' => 1]))
				->redirectAfterSaving($this->getTabUrl(['updated' => 1]))
				->skipMissingFields()
				->build();
		}
		return $this->settingsForm;
	}

	public function handleSettingsForm($post = []) {
		if ( !$this->userCanChangeSettings() ) {
			wp_die('You do not have permission to change Quick Search settings');
		}

		$this->getSettingsForm()->handleUpdateRequest($post);
	}

	protected function getTemplateVariables($templateName) {
		$variables = parent::getTemplateVariables($templateName);
		$variables['settingsForm'] = $this->getSettingsForm();
		return $variables;
	}

	public function enqueueTabScripts() {
		parent::enqueueTabScripts();
		$settings = $this->loadSettings();

		ScriptDependency::create(
			plugins_url('qs-settings-tab.js', __FILE__),
			'ame-quick-search-settings'
		)
			->addDependencies('jquery', 'ame-knockout', 'ame-jquery-cookie')
			->setTypeToModule()
			->addJsVariable(
				'wsAmeQuickSearchSettingsData',
				[
					'settings' => array_merge($this->defaultSettings, $settings->toArray()),
					'idPrefix' => $settings->getIdPrefix(),
				]
			)
			->enqueue();
	}

	public function addToolbarSearchButton($adminBar) {
		if ( $this->isSearchDisabledForRequest() ) {
			return;
		}

		if ( !$this->userCanSearch() ) {
			return;
		}

		$settings = $this->loadSettings();
		if ( !$settings->get('toolbarButton') ) {
			return;
		}

		$toolbarButtonType = $settings->get('toolbarButtonType');
		$title = '<span class="ab-icon"></span>';
		if ( $toolbarButtonType === 'iconAndText' ) {
			$title .= ' Search';
		}

		$classes = [];
		if ( $toolbarButtonType === 'iconOnly' ) {
			$classes[] = 'ame-qs-tb-icon-only';
		}

		$adminBar->add_node([
			'id'     => 'ame-quick-search-tb',
			'title'  => $title,
			'parent' => 'top-secondary',
			'meta'   => [
				'title' => 'Open the Quick Search box',
				'class' => implode(' ', $classes),
			],
		]);
	}

	/**
	 * Schedule a periodic Cron event to clean up the crawl database.
	 *
	 * Does nothing if the event is already scheduled.
	 */
	public function maybeScheduleCleanupEvent() {
		if ( wp_next_scheduled(self::DB_CLEANUP_CRON_HOOK) === false ) {
			wp_schedule_event(
				time() + 2 * DAY_IN_SECONDS, //No need to run this immediately.
				'weekly',
				self::DB_CLEANUP_CRON_HOOK
			);
		}
	}

	public function cleanupDatabase() {
		$this->getDbAdapter()->deleteStaleEntries(
			self::STALENESS_THRESHOLD_IN_DAYS,
			self::STALENESS_THRESHOLD_IN_DAYS
		);
	}

	/**
	 * Is the search script disabled for the current request or admin page?
	 *
	 * Searching doesn't work in some contexts, e.g. in AJAX requests, and can conflict with
	 * certain plugins or themes.
	 *
	 * @return bool
	 */
	private function isSearchDisabledForRequest() {
		//Don't load the search script on AJAX requests. This usually won't come up, but there are
		//circumstances where the "admin_enqueue_scripts" hook is fired during AJAX requests, e.g. if
		//the request calls wp_iframe().
		if ( function_exists('wp_doing_ajax') && wp_doing_ajax() ) {
			return true;
		}

		//Did someone else already enqueue Knockout? Their script may conflict with ours if they
		//apply bindings to the entire document, so we'll disable search for this request.
		if ( function_exists('wp_script_is') && did_action('admin_enqueue_scripts') ) {
			if ( wp_script_is('knockout', 'enqueued') ) {
				return true;
			}
		}

		global $pagenow;
		$queryParams = $this->menuEditor->get_query_params();

		//Compatibility fix for Toolset Types 3.5.2 and Toolset Blocks 1.6.18.
		//Toolset Blocks uses Knockout on its "Edit Content Template" page when in "Classic Editor"
		//mode. It applies KO bindings to the entire document. This causes a JS error when our KO
		//template is added to the page footer because the Toolset Blocks view model obviously doesn't
		//have the properties used in our template.
		//Similar issues apply to "Toolset -> Custom Fields", "Toolset -> Edit Group", etc.
		$unsafePages = [
			'ct-editor'           => true,
			'types-custom-fields' => true,
			'wpcf-edit'           => true,
			'types-relationships' => true,
		];
		if (
			($pagenow === 'admin.php')
			&& isset($queryParams['page'])
			&& isset($unsafePages[$queryParams['page']])
		) {
			return true;
		}

		return false;
	}

	//region Permission checks
	public function userCanSearch() {
		return $this->menuEditor->current_user_can_edit_menu();
	}

	public function userCanUpdateIndex() {
		return $this->menuEditor->current_user_can_edit_menu() && current_user_can('activate_plugins');
	}

	public function userCanChangeSettings() {
		return $this->menuEditor->current_user_can_edit_menu();
	}

	//endregion

	//region Export/import
	public function getExportOptionLabel() {
		return 'Quick Search settings'; //Does not include the search index.
	}

	public function getExportOptionDescription() {
		return '';
	}

	public function exportSettings() {
		if ( $this->settingsWrapperEnabled ) {
			$settings = $this->loadSettings();
			if ( $settings instanceof AbstractSettingsDictionary ) {
				return $settings->toArray();
			} else {
				return null;
			}
		} else {
			return $this->loadSettings();
		}
	}

	public function importSettings($newSettings) {
		if ( !is_array($newSettings) || empty($newSettings) ) {
			return;
		}

		$this->mergeSettingsWith($newSettings);
		$this->saveSettings();
	}
	//endregion
}

abstract class SearchableItemDefinition implements \JsonSerializable {
	/**
	 * @var string
	 */
	protected $label;
	/**
	 * @var string[]
	 */
	protected $location;

	public function __construct($label, $location = []) {
		$this->label = $label;
		$this->location = $location;
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'label'    => $this->label,
			'location' => $this->location,
		];
	}
}

class DashboardItemOrigin implements \JsonSerializable {
	private $pageUrl;
	private $menuUrl;

	public function __construct($menuUrl, $pageUrl = null) {
		$this->menuUrl = $menuUrl;
		$this->pageUrl = $pageUrl;
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		$data = [
			'menuUrl' => $this->menuUrl,
		];
		if ( $this->pageUrl !== null ) {
			$data['pageUrl'] = $this->pageUrl;
		}
		return $data;
	}

	/**
	 * @return mixed
	 */
	public function getMenuUrl() {
		return $this->menuUrl;
	}
}

class DashboardItemTarget implements \JsonSerializable {
	private $type;
	private $url;
	private $selector;

	public function __construct($type, $url = '', $selector = '') {
		$this->url = $url;
		$this->type = $type;
		$this->selector = $selector;
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		$data = [
			'type' => $this->type,
		];
		if ( $this->url !== '' ) {
			$data['url'] = $this->url;
		}
		if ( $this->selector !== '' ) {
			$data['selector'] = $this->selector;
		}
		return $data;
	}
}

class DashboardItemDefinition extends SearchableItemDefinition {
	/**
	 * @var DashboardItemOrigin
	 */
	private $origin;
	/**
	 * @var DashboardItemTarget
	 */
	private $target;
	private $relativeId;

	public function __construct(
		$label,
		DashboardItemOrigin $origin,
		DashboardItemTarget $target,
		$relativeId,
		$location = []
	) {
		parent::__construct($label, $location);
		$this->origin = $origin;
		$this->target = $target;
		$this->relativeId = $relativeId;
	}

	public function getMenuUrl() {
		return $this->origin->getMenuUrl();
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		$data = parent::jsonSerialize();
		$data['origin'] = $this->origin;
		$data['target'] = $this->target;
		$data['relativeId'] = $this->relativeId;
		$data['type'] = 'dashboardItem';
		return $data;
	}
}

class AjaxApi {
	const AJAX_GET_CRAWL_RECORDS = 'ws-ame-qs-get-crawl-records';
	const AJAX_SET_CRAWL_RECORDS = 'ws-ame-qs-set-crawl-records';
	const AJAX_UPDATE_INDEX = 'ws-ame-qs-update-dashboard-index';
	const AJAX_RUN_SEARCH = 'ws-ame-qs-quick-search';

	const AJAX_SET_CRAWLER_ENABLED = 'ws-ame-qs-set-crawler-enabled';

	private $module;
	private $actionsRegistered = false;

	/**
	 * @var callable|null
	 */
	private $afterIndexUpdate;

	/**
	 * @param SearchModule $module
	 * @param callable|null $afterIndexUpdate
	 */
	public function __construct(SearchModule $module, $afterIndexUpdate = null) {
		$this->module = $module;
		$this->afterIndexUpdate = $afterIndexUpdate;
	}

	public function registerAjaxActions() {
		if ( $this->actionsRegistered ) {
			return;
		}
		$module = $this->module;

		ajaw_v1_CreateAction(self::AJAX_UPDATE_INDEX)
			->requiredParam('updates')
			->method('post')
			->permissionCallback([$module, 'userCanUpdateIndex'])
			->handler([$this, 'ajaxUpdateIndex'])
			->register();

		ajaw_v1_CreateAction(self::AJAX_GET_CRAWL_RECORDS)
			->method('post')
			->requiredParam('urls')
			->permissionCallback([$module, 'userCanUpdateIndex'])
			->handler([$this, 'ajaxGetCrawlRecords'])
			->register();

		ajaw_v1_CreateAction(self::AJAX_SET_CRAWL_RECORDS)
			->requiredParam('records')
			->method('post')
			->permissionCallback([$module, 'userCanUpdateIndex'])
			->handler([$this, 'ajaxSetCrawlRecords'])
			->register();

		ajaw_v1_CreateAction(self::AJAX_RUN_SEARCH)
			->method('post')
			->requiredParam('query', 'string')
			->requiredParam('presentMenuUrls', 'string')
			->permissionCallback([$module, 'userCanSearch'])
			->handler([$this, 'ajaxSearch'])
			->register();

		ajaw_v1_CreateAction(self::AJAX_SET_CRAWLER_ENABLED)
			->method('post')
			->requiredParam('enabled', 'string')
			->permissionCallback([$module, 'userCanChangeSettings'])
			->handler([$this, 'ajaxSetCrawlerEnabled'])
			->register();

		$this->actionsRegistered = true;
	}

	public function ajaxUpdateIndex($params) {
		$serializedUpdates = $params['updates'];
		$updates = json_decode($serializedUpdates, true);
		if ( !is_array($updates) ) {
			wp_send_json_error(new \WP_Error('invalid_updates_param', 'Invalid updates - array expected'), 400);
			exit;
		}

		$results = [];
		$dbAdapter = $this->module->getDbAdapter();

		foreach ($updates as $menuUrl => $items) {
			if ( !is_array($items) ) {
				wp_send_json_error(new \WP_Error('invalid_update', 'Invalid update list for menu - array expected'), 400);
				exit;
			}

			list($inserted, $error) = $dbAdapter->setFoundDashboardItemsFor($menuUrl, $items, true);
			/** @var \WP_Error $error */
			if ( $error && $error->has_errors() ) {
				wp_send_json_error($error, 500);
				exit;
			}

			$results[$menuUrl] = $inserted;
		}

		if ( $this->afterIndexUpdate ) {
			call_user_func($this->afterIndexUpdate);
		}

		wp_send_json_success($results);
		exit;
	}

	public function ajaxGetCrawlRecords($params) {
		$serializedUrls = $params['urls'];
		$urls = json_decode($serializedUrls, true);
		if ( !is_array($urls) ) {
			wp_send_json_error(new \WP_Error('invalid_urls_param', 'Invalid URLs - array expected'), 400);
			exit; //wp_send_json_error() already exits, but the IDE doesn't know that.
		}

		if ( count($urls) > 200 ) {
			wp_send_json_error(new \WP_Error('too_many_urls', 'Too many URLs for one request'), 400);
			exit;
		}

		$sanitizedUrls = array_map(function ($input) {
			if ( !is_string($input) ) {
				return null;
			}
			return substr($input, 0, 2048);
		}, $urls);
		$sanitizedUrls = array_filter($sanitizedUrls);

		$records = $this->module->getDbAdapter()->fetchCrawlRecords($sanitizedUrls);

		wp_send_json_success($records);
		exit;
	}

	public function ajaxSetCrawlRecords($params) {
		$serializedRecords = $params['records'];
		$records = json_decode($serializedRecords, true);
		if ( !is_array($records) ) {
			wp_send_json_error(new \WP_Error('invalid_records_param', 'Invalid records - array expected'), 400);
			exit;
		}

		$result = $this->module->getDbAdapter()->updateCrawlRecords($records, true);

		if ( !empty($result['errors']) && empty($result['inserted']) && empty($result['updated']) ) {
			wp_send_json_error($result['errors'], 500);
			exit;
		}

		wp_send_json_success([
			'inserted' => $result['inserted'],
			'updated'  => $result['updated'],
			'errors'   => $this->serializeWpErrorForJson($result['errors']),
		]);
		exit;
	}

	/**
	 * @param \WP_Error|mixed $error
	 * @return array
	 */
	private function serializeWpErrorForJson($error) {
		if ( !is_wp_error($error) ) {
			return [];
		}

		//Same format as used by wp_send_json_error().
		$result = [];
		foreach ($error->errors as $code => $messages) {
			foreach ($messages as $message) {
				$result[] = [
					'code'    => $code,
					'message' => $message,
				];
			}
		}
		return $result;
	}

	public function ajaxSearch($params) {
		$query = $params['query'];

		$presentMenuUrls = json_decode($params['presentMenuUrls'], true);
		if ( !is_array($presentMenuUrls) ) {
			wp_send_json_error(new \WP_Error(
				'invalid_present_menu_urls_param',
				'Invalid presentMenuUrls - array expected'),
				400
			);
			exit;
		}
		$menuUrlLookup = array_flip($presentMenuUrls);

		//We need to filter the results against the present menu URLs, but using an IN clause
		//with a huge number of items is not efficient (on some sites, the admin menu can have
		//hundreds of items). Instead, we request more results than we need, then filter in PHP.
		//Hopefully, a reasonable query won't return too many results.
		$maxResults = 100;
		$requestedResults = $maxResults * 2 + 1;

		$results = $this->module->getDbAdapter()->searchDashboardItems($query, $requestedResults);

		//Filter out items that are not present in the current admin menu.
		$results = array_filter($results, function (DashboardItemDefinition $item) use ($menuUrlLookup) {
			$url = $item->getMenuUrl();
			if ( !is_string($url) ) {
				return true; //Should not happen, but we'll include the item just in case.
			}
			return isset($menuUrlLookup[$url]);
		});

		//Reindex the array. array_filter() preserves keys, and if we end up with sparse keys,
		//the results would be sent as an object instead of an array. The client expects an array.
		$results = array_values($results);

		if ( count($results) > $maxResults ) {
			$hasMoreResults = true;
			$results = array_slice($results, 0, $maxResults);
		} else {
			$hasMoreResults = false;
		}

		wp_send_json_success([
			'items'   => $results,
			'hasMore' => $hasMoreResults,
		]);
		exit;
	}

	public function ajaxSetCrawlerEnabled($params) {
		$validValues = ['enabled', 'disabled'];
		if ( !in_array($params['enabled'], $validValues, true) ) {
			wp_send_json_error(new \WP_Error('invalid_value', 'Invalid value'), 400);
			exit;
		}

		$settings = $this->module->loadSettings();
		$settings->set('crawlerEnabled', $params['enabled']);
		$this->module->saveSettings();
		wp_send_json_success();
		exit;
	}
}