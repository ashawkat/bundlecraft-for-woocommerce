<?php
/**
 * Plugin Name: BundleCraft for WooCommerce
 * Plugin URI: https://github.com/ashawkat/bundlecraft-for-woocommerce
 * Description: Build product bundle promotions with tiered quantity discounts, a modern admin app, analytics, and a customer-facing bundle builder widget.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: BundleCraft
 * Author URI: https://github.com/ashawkat
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bundlecraft-for-woocommerce
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0
 * WC tested up to: 9.4
 *
 * @package BundleCraft
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BUNDLECRAFT_VERSION', '1.0.0' );
define( 'BUNDLECRAFT_DB_VERSION', '1.0' );
define( 'BUNDLECRAFT_PLUGIN_FILE', __FILE__ );
define( 'BUNDLECRAFT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BUNDLECRAFT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BUNDLECRAFT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for BundleCraft classes.
 *
 * Maps BundleCraft\Foo_Bar to includes/class-foo-bar.php.
 *
 * @param string $class_name Fully-qualified class name.
 * @return void
 */
function bundlecraft_autoload( $class_name ) {
	if ( 0 !== strpos( $class_name, 'BundleCraft\\' ) ) {
		return;
	}

	$slug = strtolower( str_replace( [ 'BundleCraft\\', '_' ], [ '', '-' ], $class_name ) );
	$file = BUNDLECRAFT_PLUGIN_DIR . 'includes/class-' . $slug . '.php';

	if ( is_readable( $file ) ) {
		require_once $file;
	}
}
spl_autoload_register( 'bundlecraft_autoload' );

/**
 * Declare compatibility with WooCommerce HPOS (High-Performance Order Storage).
 *
 * @return void
 */
function bundlecraft_declare_hpos_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', BUNDLECRAFT_PLUGIN_FILE, true );
	}
}
add_action( 'before_woocommerce_init', 'bundlecraft_declare_hpos_compatibility' );

/**
 * Activation: create the bundles table and migrate legacy data if present.
 *
 * @return void
 */
function bundlecraft_activate_plugin() {
	BundleCraft\Install::activate();
}
register_activation_hook( __FILE__, 'bundlecraft_activate_plugin' );

/**
 * Deactivation: clear the scheduled coupon cleanup event.
 *
 * @return void
 */
function bundlecraft_deactivate_plugin() {
	$timestamp = wp_next_scheduled( 'bundlecraft_daily_coupon_cleanup' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'bundlecraft_daily_coupon_cleanup' );
	}
}
register_deactivation_hook( __FILE__, 'bundlecraft_deactivate_plugin' );

/**
 * Boot the plugin once WooCommerce is available.
 *
 * @return void
 */
function bundlecraft_boot_plugin() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'bundlecraft_woocommerce_missing_notice' );
		return;
	}

	BundleCraft\Plugin::instance()->boot();
}
add_action( 'plugins_loaded', 'bundlecraft_boot_plugin', 20 );

/**
 * Admin notice shown when WooCommerce is not active.
 *
 * @return void
 */
function bundlecraft_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php echo esc_html__( 'BundleCraft for WooCommerce', 'bundlecraft-for-woocommerce' ); ?></strong>
			<?php echo esc_html__( 'requires WooCommerce to be installed and active.', 'bundlecraft-for-woocommerce' ); ?>
			<a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ); ?>">
				<?php echo esc_html__( 'Install WooCommerce', 'bundlecraft-for-woocommerce' ); ?>
			</a>
		</p>
	</div>
	<?php
}

/**
 * Schedule the daily cleanup of expired bundle coupons.
 *
 * @return void
 */
function bundlecraft_schedule_coupon_cleanup() {
	if ( ! wp_next_scheduled( 'bundlecraft_daily_coupon_cleanup' ) ) {
		wp_schedule_event( time(), 'daily', 'bundlecraft_daily_coupon_cleanup' );
	}
}
add_action( 'wp', 'bundlecraft_schedule_coupon_cleanup' );

/**
 * Delete unused bundle coupons (cron callback).
 *
 * @return void
 */
function bundlecraft_run_coupon_cleanup() {
	if ( class_exists( 'WooCommerce' ) ) {
		BundleCraft\Cart::instance()->cleanup_unused_coupons();
	}
}
add_action( 'bundlecraft_daily_coupon_cleanup', 'bundlecraft_run_coupon_cleanup' );
