<?php
/**
 * Plugin orchestrator.
 *
 * @package BundleCraft
 */

namespace BundleCraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots the sub-systems, registers admin/menu/enqueue hooks, and renders
 * the admin page shells.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Slug of the top-level admin page.
	 */
	const MENU_SLUG = 'bundlecraft';

	/**
	 * Singleton accessor.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boots every sub-system and hooks into WordPress/WooCommerce.
	 *
	 * @return void
	 */
	public function boot() {
		add_action( 'init', [ Install::class, 'maybe_upgrade' ] );
		add_action( 'init', [ $this, 'register_shortcode' ], 5 );

		Cart::instance();

		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
		add_filter( 'plugin_action_links_' . BUNDLECRAFT_PLUGIN_BASENAME, [ $this, 'plugin_action_links' ] );
	}

	/**
	 * Registers the storefront shortcode.
	 *
	 * @return void
	 */
	public function register_shortcode() {
		$shortcode = new Shortcode();
		$shortcode->register_hooks();
	}

	/**
	 * Registers REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		$rest = new Rest();
		$rest->register_hooks();
		$rest->register_routes();
	}

	/**
	 * Registers the admin menu and page callbacks.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		add_menu_page(
			__( 'BundleCraft', 'bundlecraft-for-woocommerce' ),
			__( 'BundleCraft', 'bundlecraft-for-woocommerce' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_bundles_page' ],
			BUNDLECRAFT_PLUGIN_URL . 'assets/img/bundlecraft-icon.svg',
			56
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Bundles', 'bundlecraft-for-woocommerce' ),
			__( 'Bundles', 'bundlecraft-for-woocommerce' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_bundles_page' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Bundle Analytics', 'bundlecraft-for-woocommerce' ),
			__( 'Analytics', 'bundlecraft-for-woocommerce' ),
			'manage_options',
			'bundlecraft-analytics',
			[ $this, 'render_analytics_page' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'bundlecraft-for-woocommerce' ),
			__( 'Settings', 'bundlecraft-for-woocommerce' ),
			'manage_options',
			'bundlecraft-settings',
			[ $this, 'render_settings_page' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Diagnostics', 'bundlecraft-for-woocommerce' ),
			__( 'Diagnostics', 'bundlecraft-for-woocommerce' ),
			'manage_options',
			'bundlecraft-diagnostics',
			[ $this, 'render_diagnostics_page' ]
		);
	}

	/**
	 * Enqueues the admin app and shared admin styles.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		// Inline-only style handle carrying the menu icon sizing rules on
		// every admin screen (replaces the legacy echoed <style> block).
		wp_register_style( 'bundlecraft-admin-chrome', '', [], BUNDLECRAFT_VERSION );
		wp_enqueue_style( 'bundlecraft-admin-chrome' );
		wp_add_inline_style(
			'bundlecraft-admin-chrome',
			'#adminmenu #toplevel_page_bundlecraft .wp-menu-image img{width:20px;height:20px;padding:6px 0;opacity:.6}'
			. '#adminmenu #toplevel_page_bundlecraft:hover .wp-menu-image img,'
			. '#adminmenu #toplevel_page_bundlecraft.wp-has-current-submenu .wp-menu-image img{opacity:1}'
		);

		if ( false === strpos( $hook, 'bundlecraft' ) ) {
			return;
		}

		$css_version = $this->asset_version( 'assets/build/admin.css' );
		$js_version  = $this->asset_version( 'assets/build/admin.js' );

		wp_enqueue_style( 'bundlecraft-admin', BUNDLECRAFT_PLUGIN_URL . 'assets/build/admin.css', [], $css_version );
		wp_enqueue_script( 'bundlecraft-admin', BUNDLECRAFT_PLUGIN_URL . 'assets/build/admin.js', [], $js_version, true );

		wp_localize_script(
			'bundlecraft-admin',
			'bundlecraftAdmin',
			[
				'restUrl'    => esc_url_raw( rest_url( Rest::NAMESPACE_V1 ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'page'       => $this->current_admin_page(),
				'dateFormat' => get_option( 'date_format' ),
				'currency'   => $this->currency_config(),
				'settings'   => Settings::get(),
				'i18n'       => $this->admin_i18n(),
			]
		);
	}

	/**
	 * Enqueues the frontend widget assets when the current view can contain
	 * a bundle widget.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets() {
		if ( is_admin() || ! $this->should_enqueue_frontend_assets() ) {
			return;
		}

		$this->localize_frontend_assets();
	}

	/**
	 * Registers, localizes, and prints the frontend widget assets.
	 *
	 * @return void
	 */
	private function localize_frontend_assets() {
		$css_version = $this->asset_version( 'assets/build/frontend.css' );
		$js_version  = $this->asset_version( 'assets/build/frontend.js' );

		wp_enqueue_style( 'bundlecraft-frontend', BUNDLECRAFT_PLUGIN_URL . 'assets/build/frontend.css', [], $css_version );
		wp_enqueue_script( 'bundlecraft-frontend', BUNDLECRAFT_PLUGIN_URL . 'assets/build/frontend.js', [], $js_version, true );

		$session_discount = [];

		if ( class_exists( 'WooCommerce' ) && function_exists( 'WC' ) && WC()->session ) {
			$session = Cart::instance()->get_session_data();

			if ( $session && ! empty( $session['bundle_id'] ) ) {
				$session_discount = $session;
			}
		}

		wp_localize_script(
			'bundlecraft-frontend',
			'bundlecraftFrontend',
			[
				'restUrl'         => esc_url_raw( rest_url( Rest::NAMESPACE_V1 ) ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'cartUrl'         => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
				'sessionDiscount' => $session_discount,
				'discountLabel'   => __( 'Bundle Discount', 'bundlecraft-for-woocommerce' ),
				'currency'        => $this->currency_config(),
				'i18n'            => $this->frontend_i18n(),
			]
		);

		/**
		 * Fires after the frontend widget assets have been enqueued.
		 */
		do_action( 'bundlecraft_frontend_assets_enqueued' );
	}

	/**
	 * Detects whether the current request renders a bundle widget.
	 *
	 * @return bool
	 */
	private function should_enqueue_frontend_assets() {
		if ( Frontend::assets_forced() ) {
			$should_load = true;
		} elseif ( is_singular() ) {
			$should_load = is_a( get_post(), 'WP_Post' ) && has_shortcode( get_post()->post_content, 'bundlecraft_bundle' );
		} else {
			$should_load = false;

			foreach ( $GLOBALS['posts'] ?? [] as $post ) {
				if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bundlecraft_bundle' ) ) {
					$should_load = true;
					break;
				}
			}
		}

		/**
		 * Filters whether the bundle widget assets should load on this request.
		 *
		 * @param bool $should_load
		 */
		return apply_filters( 'bundlecraft_should_enqueue_frontend_assets', $should_load );
	}

	/**
	 * Adds a Settings/Bundles quick link on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$custom = [
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ),
				esc_html__( 'Bundles', 'bundlecraft-for-woocommerce' )
			),
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=bundlecraft-settings' ) ),
				esc_html__( 'Settings', 'bundlecraft-for-woocommerce' )
			),
		];

		return array_merge( $custom, $links );
	}

	/**
	 * Page renderers. Each is a thin shell the Vue admin app mounts into.
	 *
	 * @return void
	 */
	public function render_bundles_page() {
		$this->render_page_shell( 'bundles' );
	}

	/**
	 * Renders the analytics page shell.
	 *
	 * @return void
	 */
	public function render_analytics_page() {
		$this->render_page_shell( 'analytics' );
	}

	/**
	 * Renders the settings page shell.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		$this->render_page_shell( 'settings' );
	}

	/**
	 * Renders the diagnostics page shell.
	 *
	 * @return void
	 */
	public function render_diagnostics_page() {
		$this->render_page_shell( 'diagnostics' );
	}

	/**
	 * Prints the admin app mount point.
	 *
	 * @param string $page Page identifier for the Vue app.
	 * @return void
	 */
	private function render_page_shell( $page ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div id="bundlecraft-admin-app" class="bundlecraft-admin-app" data-page="<?php echo esc_attr( $page ); ?>">
			<p><?php esc_html_e( 'Loading BundleCraft…', 'bundlecraft-for-woocommerce' ); ?></p>
		</div>
		<noscript>
			<div class="notice notice-error"><p><?php esc_html_e( 'BundleCraft requires JavaScript.', 'bundlecraft-for-woocommerce' ); ?></p></div>
		</noscript>
		<?php
	}

	/**
	 * Resolves the current BundleCraft admin page identifier.
	 *
	 * @return string
	 */
	private function current_admin_page() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : self::MENU_SLUG; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$map = [
			self::MENU_SLUG          => 'bundles',
			'bundlecraft-analytics'  => 'analytics',
			'bundlecraft-settings'   => 'settings',
			'bundlecraft-diagnostics' => 'diagnostics',
		];

		return $map[ $page ] ?? 'bundles';
	}

	/**
	 * Cache-busting version string for an asset file.
	 *
	 * @param string $relative_path Path relative to the plugin dir.
	 * @return string
	 */
	private function asset_version( $relative_path ) {
		$file = BUNDLECRAFT_PLUGIN_DIR . $relative_path;

		if ( file_exists( $file ) ) {
			return BUNDLECRAFT_VERSION . '.' . (string) filemtime( $file );
		}

		return BUNDLECRAFT_VERSION;
	}

	/**
	 * Currency formatting configuration for the JS apps.
	 *
	 * @return array
	 */
	private function currency_config() {
		return [
			// Symbols arrive HTML-encoded (e.g. "&#36;"); the JS apps render
			// them as text, so hand over the decoded form.
			'symbol'        => function_exists( 'get_woocommerce_currency_symbol' )
				? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8' )
				: '$',
			'position'      => get_option( 'woocommerce_currency_pos', 'left' ),
			'thousand_sep'  => get_option( 'woocommerce_price_thousand_sep', ',' ),
			'decimal_sep'   => get_option( 'woocommerce_price_decimal_sep', '.' ),
			'decimals'      => absint( get_option( 'woocommerce_price_num_decimals', 2 ) ),
		];
	}

	/**
	 * Localized strings for the admin app.
	 *
	 * @return array
	 */
	private function admin_i18n() {
		return [
			// Generic.
			'cancel'            => __( 'Cancel', 'bundlecraft-for-woocommerce' ),
			'confirm'           => __( 'Confirm', 'bundlecraft-for-woocommerce' ),
			'add'               => __( 'Add', 'bundlecraft-for-woocommerce' ),
			'addedLabel'        => __( 'Added', 'bundlecraft-for-woocommerce' ),
			'edit'              => __( 'Edit', 'bundlecraft-for-woocommerce' ),
			'delete'            => __( 'Delete', 'bundlecraft-for-woocommerce' ),
			'remove'            => __( 'Remove', 'bundlecraft-for-woocommerce' ),
			'loading'           => __( 'Loading…', 'bundlecraft-for-woocommerce' ),
			'saving'            => __( 'Saving…', 'bundlecraft-for-woocommerce' ),
			'searching'         => __( 'Searching…', 'bundlecraft-for-woocommerce' ),
			'error'             => __( 'Something went wrong. Please try again.', 'bundlecraft-for-woocommerce' ),
			'loadError'         => __( 'Could not load data.', 'bundlecraft-for-woocommerce' ),
			'ok'                => __( 'OK', 'bundlecraft-for-woocommerce' ),
			'warning'           => __( 'Warning', 'bundlecraft-for-woocommerce' ),
			'yes'               => __( 'Yes', 'bundlecraft-for-woocommerce' ),
			'no'                => __( 'No', 'bundlecraft-for-woocommerce' ),
			'enabled'           => __( 'Enabled', 'bundlecraft-for-woocommerce' ),
			'disabled'          => __( 'Disabled', 'bundlecraft-for-woocommerce' ),
			'status'            => __( 'Status', 'bundlecraft-for-woocommerce' ),
			'date'              => __( 'Date', 'bundlecraft-for-woocommerce' ),
			'total'             => __( 'Total', 'bundlecraft-for-woocommerce' ),
			'discount'          => __( 'Discount', 'bundlecraft-for-woocommerce' ),

			// Bundles view.
			'yourBundles'       => __( 'Your Bundles', 'bundlecraft-for-woocommerce' ),
			'newBundle'         => __( 'New Bundle', 'bundlecraft-for-woocommerce' ),
			'editBundle'        => __( 'Edit Bundle', 'bundlecraft-for-woocommerce' ),
			'noBundles'         => __( 'No bundles yet. Create your first one!', 'bundlecraft-for-woocommerce' ),
			'products'          => __( 'products', 'bundlecraft-for-woocommerce' ),
			'tiers'             => __( 'tiers', 'bundlecraft-for-woocommerce' ),
			'copyShortcode'     => __( 'Copy shortcode', 'bundlecraft-for-woocommerce' ),
			'copied'            => __( 'Shortcode copied!', 'bundlecraft-for-woocommerce' ),
			'deleteBundleTitle' => __( 'Delete bundle?', 'bundlecraft-for-woocommerce' ),
			'deleteConfirm'     => __( 'Delete this bundle? This cannot be undone.', 'bundlecraft-for-woocommerce' ),
			'deleted'           => __( 'Bundle deleted.', 'bundlecraft-for-woocommerce' ),
			'saved'             => __( 'Bundle saved.', 'bundlecraft-for-woocommerce' ),
			'save'              => __( 'Save Bundle', 'bundlecraft-for-woocommerce' ),
			'needName'          => __( 'A name is required.', 'bundlecraft-for-woocommerce' ),
			'needProducts'      => __( 'Add at least one product.', 'bundlecraft-for-woocommerce' ),
			'needTier'          => __( 'Add at least one discount tier.', 'bundlecraft-for-woocommerce' ),
			'bundleName'        => __( 'Bundle name', 'bundlecraft-for-woocommerce' ),
			'description'       => __( 'Description', 'bundlecraft-for-woocommerce' ),
			'showOnStorefront'  => __( 'Show on storefront', 'bundlecraft-for-woocommerce' ),
			'showTitle'         => __( 'Show title', 'bundlecraft-for-woocommerce' ),
			'productsSection'   => __( 'Products', 'bundlecraft-for-woocommerce' ),
			'searchProducts'    => __( 'Search products', 'bundlecraft-for-woocommerce' ),
			'searchPlaceholder' => __( 'Type at least 2 characters…', 'bundlecraft-for-woocommerce' ),
			'selectedProducts'  => __( 'Selected products (drag to reorder)', 'bundlecraft-for-woocommerce' ),
			'noProductsSelected' => __( 'No products selected yet.', 'bundlecraft-for-woocommerce' ),
			'discountSection'   => __( 'Discount rules', 'bundlecraft-for-woocommerce' ),
			'useQuantity'       => __( 'Use quantity mode (steppers instead of checkboxes)', 'bundlecraft-for-woocommerce' ),
			'maxQuantity'       => __( 'Maximum quantity per product', 'bundlecraft-for-woocommerce' ),
			'tiersHint'         => __( 'Tiers: buy at least this many items, unlock this percentage off. Items count total units in the bundle.', 'bundlecraft-for-woocommerce' ),
			'tierQuantity'      => __( 'Items', 'bundlecraft-for-woocommerce' ),
			'tierDiscount'      => __( '% Off', 'bundlecraft-for-woocommerce' ),
			'addTier'           => __( 'Add tier', 'bundlecraft-for-woocommerce' ),
			'appearanceSection' => __( 'Appearance & behavior', 'bundlecraft-for-woocommerce' ),
			'headingText'       => __( 'Heading text', 'bundlecraft-for-woocommerce' ),
			'showHeading'       => __( 'Show heading', 'bundlecraft-for-woocommerce' ),
			'hintText'          => __( 'Hint text', 'bundlecraft-for-woocommerce' ),
			'showHint'          => __( 'Show hint', 'bundlecraft-for-woocommerce' ),
			'progressText'      => __( 'Progress section text', 'bundlecraft-for-woocommerce' ),
			'showProgress'      => __( 'Show progress', 'bundlecraft-for-woocommerce' ),
			'buttonText'        => __( 'Button text', 'bundlecraft-for-woocommerce' ),
			'cartBehavior'      => __( 'After adding to cart', 'bundlecraft-for-woocommerce' ),
			'openSidecart'      => __( 'Open cart / sidecart', 'bundlecraft-for-woocommerce' ),
			'redirectToCart'    => __( 'Redirect to cart', 'bundlecraft-for-woocommerce' ),
			'primaryColor'      => __( 'Primary', 'bundlecraft-for-woocommerce' ),
			'accentColor'       => __( 'Accent', 'bundlecraft-for-woocommerce' ),
			'hoverBgColor'      => __( 'Hover background', 'bundlecraft-for-woocommerce' ),
			'hoverAccentColor'  => __( 'Hover accent', 'bundlecraft-for-woocommerce' ),
			'buttonTextColor'   => __( 'Button text', 'bundlecraft-for-woocommerce' ),

			// Analytics view.
			'dateRange'         => __( 'Date range', 'bundlecraft-for-woocommerce' ),
			'startDate'         => __( 'Start date', 'bundlecraft-for-woocommerce' ),
			'endDate'           => __( 'End date', 'bundlecraft-for-woocommerce' ),
			'apply'             => __( 'Apply', 'bundlecraft-for-woocommerce' ),
			'statCoupons'       => __( 'Coupons Created', 'bundlecraft-for-woocommerce' ),
			'statRevenue'       => __( 'Bundle Revenue', 'bundlecraft-for-woocommerce' ),
			'statOrders'        => __( 'Bundle Orders', 'bundlecraft-for-woocommerce' ),
			'statUsageRate'     => __( 'Coupon Usage Rate', 'bundlecraft-for-woocommerce' ),
			'statUsed'          => __( 'Used', 'bundlecraft-for-woocommerce' ),
			'statUnused'        => __( 'Unused', 'bundlecraft-for-woocommerce' ),
			'statUsage'         => __( 'Times used', 'bundlecraft-for-woocommerce' ),
			'withBundle'        => __( 'With Bundle', 'bundlecraft-for-woocommerce' ),
			'withoutBundle'     => __( 'Without Bundle', 'bundlecraft-for-woocommerce' ),
			'chartCoupons'      => __( 'Coupon Usage', 'bundlecraft-for-woocommerce' ),
			'chartRevenue'      => __( 'Bundle Revenue Over Time', 'bundlecraft-for-woocommerce' ),
			'chartCart'         => __( 'Cart Share', 'bundlecraft-for-woocommerce' ),
			'chartTopBundles'   => __( 'Top Bundles', 'bundlecraft-for-woocommerce' ),
			'recentOrders'      => __( 'Recent Bundle Orders', 'bundlecraft-for-woocommerce' ),
			'noOrders'          => __( 'No bundle orders in this period yet.', 'bundlecraft-for-woocommerce' ),
			'order'             => __( 'Order', 'bundlecraft-for-woocommerce' ),
			'range_7days'       => __( 'Last 7 Days', 'bundlecraft-for-woocommerce' ),
			'range_30days'      => __( 'Last 30 Days', 'bundlecraft-for-woocommerce' ),
			'range_90days'      => __( 'Last 90 Days', 'bundlecraft-for-woocommerce' ),
			'range_this_month'  => __( 'This Month', 'bundlecraft-for-woocommerce' ),
			'range_last_month'  => __( 'Last Month', 'bundlecraft-for-woocommerce' ),
			'range_this_quarter' => __( 'This Quarter', 'bundlecraft-for-woocommerce' ),
			'range_this_year'   => __( 'This Year', 'bundlecraft-for-woocommerce' ),
			'range_custom'      => __( 'Custom Range', 'bundlecraft-for-woocommerce' ),

			// Settings view.
			'settingsTitle'     => __( 'Settings', 'bundlecraft-for-woocommerce' ),
			'saveSettings'      => __( 'Save Settings', 'bundlecraft-for-woocommerce' ),
			'settingsSaved'     => __( 'Settings saved.', 'bundlecraft-for-woocommerce' ),
			'enableLogging'     => __( 'Enable debug logging', 'bundlecraft-for-woocommerce' ),
			'loggingHint'       => __( 'When enabled, BundleCraft writes diagnostic messages to the WooCommerce log (WooCommerce → Status → Logs, source "bundlecraft-for-woocommerce"). Leave this off on production stores unless support asks you to enable it.', 'bundlecraft-for-woocommerce' ),
			'settingsHero'      => __( 'Tune how BundleCraft behaves on your store. Changes save instantly.', 'bundlecraft-for-woocommerce' ),
			'stateSaved'        => __( 'All changes saved', 'bundlecraft-for-woocommerce' ),
			'stateSaving'       => __( 'Saving…', 'bundlecraft-for-woocommerce' ),
			'stateUnsaved'      => __( 'Unsaved changes', 'bundlecraft-for-woocommerce' ),
			'generalGroup'      => __( 'General', 'bundlecraft-for-woocommerce' ),
			'cartGroup'         => __( 'Cart & discounts', 'bundlecraft-for-woocommerce' ),
			'defaultBehavior'   => __( 'Default cart behavior', 'bundlecraft-for-woocommerce' ),
			'defaultBehaviorHint' => __( 'Preselected for every new bundle you create. You can still change it per bundle in the editor.', 'bundlecraft-for-woocommerce' ),
			'couponLifetime'    => __( 'Coupon lifetime', 'bundlecraft-for-woocommerce' ),
			'couponLifetimeHint' => __( 'How long a bundle discount coupon stays valid before it is automatically cleaned up. Longer windows leave more unused coupons behind.', 'bundlecraft-for-woocommerce' ),
			'debugLogging'      => __( 'Debug logging', 'bundlecraft-for-woocommerce' ),
			'loggingHintShort'  => __( 'Write diagnostic messages to the WooCommerce log while troubleshooting.', 'bundlecraft-for-woocommerce' ),
			'lifetime24'        => __( '24 hours', 'bundlecraft-for-woocommerce' ),
			'lifetime48'        => __( '48 hours', 'bundlecraft-for-woocommerce' ),
			'lifetime72'        => __( '3 days', 'bundlecraft-for-woocommerce' ),
			'lifetime168'       => __( '1 week', 'bundlecraft-for-woocommerce' ),
			'openSidecart'      => __( 'Open cart / sidecart', 'bundlecraft-for-woocommerce' ),
			'redirectToCart'    => __( 'Redirect to cart', 'bundlecraft-for-woocommerce' ),

			// Diagnostics view.
			'environment'       => __( 'Environment', 'bundlecraft-for-woocommerce' ),
			'healthChecks'      => __( 'Health Checks', 'bundlecraft-for-woocommerce' ),
			'diag_wp_version'   => __( 'WordPress Version', 'bundlecraft-for-woocommerce' ),
			'diag_wc_version'   => __( 'WooCommerce Version', 'bundlecraft-for-woocommerce' ),
			'diag_php_version'  => __( 'PHP Version', 'bundlecraft-for-woocommerce' ),
			'diag_plugin_version' => __( 'Plugin Version', 'bundlecraft-for-woocommerce' ),
			'diag_db_version'   => __( 'Database Schema Version', 'bundlecraft-for-woocommerce' ),
			'diag_memory_limit' => __( 'Memory Limit', 'bundlecraft-for-woocommerce' ),
			'diag_timezone'     => __( 'Timezone', 'bundlecraft-for-woocommerce' ),
			'diag_store_url'    => __( 'Store URL', 'bundlecraft-for-woocommerce' ),
			'diag_rest_url'     => __( 'REST Endpoint', 'bundlecraft-for-woocommerce' ),
			'check_table_exists' => __( 'Bundles table exists', 'bundlecraft-for-woocommerce' ),
			'check_sessions_table' => __( 'WooCommerce sessions table exists', 'bundlecraft-for-woocommerce' ),
			'check_is_wc_loaded' => __( 'WooCommerce loaded', 'bundlecraft-for-woocommerce' ),
			'check_logging_enabled' => __( 'Debug logging enabled', 'bundlecraft-for-woocommerce' ),
			'check_bundle_count' => __( 'Bundles stored', 'bundlecraft-for-woocommerce' ),
			'check_legacy_migrated' => __( 'Legacy data migrated', 'bundlecraft-for-woocommerce' ),
			'check_legacy_table' => __( 'Legacy "mmb" table still present', 'bundlecraft-for-woocommerce' ),
		];
	}

	/**
	 * Localized strings for the storefront widget.
	 *
	 * @return array
	 */
	private function frontend_i18n() {
		return [
			'items'         => __( 'items', 'bundlecraft-for-woocommerce' ),
			'item'          => __( 'item', 'bundlecraft-for-woocommerce' ),
			'subtotal'      => __( 'Subtotal', 'bundlecraft-for-woocommerce' ),
			'discount'      => __( 'Discount', 'bundlecraft-for-woocommerce' ),
			'total'         => __( 'Total', 'bundlecraft-for-woocommerce' ),
			'addToCart'     => __( 'Add to Cart', 'bundlecraft-for-woocommerce' ),
			'adding'        => __( 'Adding…', 'bundlecraft-for-woocommerce' ),
			'added'         => __( 'Added to cart!', 'bundlecraft-for-woocommerce' ),
			'selectVariation' => __( 'Select variation', 'bundlecraft-for-woocommerce' ),
			'chooseProduct' => __( 'Select this product', 'bundlecraft-for-woocommerce' ),
			'summary'       => __( 'Summary', 'bundlecraft-for-woocommerce' ),
			'emptySummary'  => __( 'Select products to see your bundle pricing.', 'bundlecraft-for-woocommerce' ),
			/* translators: 1: number of items, 2: discount percentage */
			'unlockMore'    => __( 'Add %1$s more item(s) to unlock %2$s off', 'bundlecraft-for-woocommerce' ),
			/* translators: %s: discount percentage */
			'unlocked'      => __( '%1$s off unlocked!', 'bundlecraft-for-woocommerce' ),
			/* translators: %d: maximum quantity */
			'maxReached'    => __( 'Maximum of %d items per bundle reached.', 'bundlecraft-for-woocommerce' ),
			'addError'      => __( 'Could not add the bundle to your cart.', 'bundlecraft-for-woocommerce' ),
			'viewCart'      => __( 'View Cart', 'bundlecraft-for-woocommerce' ),
			'outOfStock'    => __( 'Out of stock', 'bundlecraft-for-woocommerce' ),
		];
	}
}
