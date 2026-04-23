<?php
/**
 * Plugin Name: Bootflow – Product XML & CSV Importer
 * Plugin URI:  https://bootflow.io/woocommerce-xml-csv-importer/
 * Description: Import and update WooCommerce products from XML and CSV feeds with manual field mapping, product variations support, and a reliable import workflow.
 * Version: 0.9.8
 * Author:      Bootflow
 * Author URI:  https://bootflow.io
 * Text Domain: bootflow-product-xml-csv-importer
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Check if WooCommerce is active
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using WordPress core 'active_plugins' filter
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', 'bfpi_woocommerce_missing_notice');
    return;
}

function bfpi_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e('Bootflow Product Importer requires WooCommerce to be installed and active.', 'bootflow-product-xml-csv-importer'); ?></p>
    </div>
    <?php
}

// Debug output is controlled by WordPress WP_DEBUG constant.

/**
 * Currently plugin version.
 */
define('BFPI_VERSION', '0.9.8');
define('BFPI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BFPI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BFPI_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('BFPI_TEXT_DOMAIN', 'bootflow-product-xml-csv-importer'); // WP.org compliance: text domain must match plugin slug

// All features are available in this WordPress.org version.

// Ensure clean output for production
if (!defined('BFPI_DEBUG')) {
    define('BFPI_DEBUG', false);
}

// Load security class early
require_once plugin_dir_path(__FILE__) . 'includes/class-bfpi-security.php';
Bfpi_Security::init(); // Initialize security measures

// Load features class
require_once plugin_dir_path(__FILE__) . 'includes/class-bfpi-features.php';

// Load logger class (respects WP_DEBUG setting)
require_once plugin_dir_path(__FILE__) . 'includes/class-bfpi-logger.php';

/**
 * The code that runs during plugin activation.
 */
function bfpi_activate() {
    require_once plugin_dir_path(__FILE__) . 'includes/class-bfpi-activator.php';
    Bfpi_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function bfpi_deactivate() {
    require_once plugin_dir_path(__FILE__) . 'includes/class-bfpi-deactivator.php';
    Bfpi_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'bfpi_activate');
register_deactivation_hook(__FILE__, 'bfpi_deactivate');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-bfpi.php';

/**
 * Begins execution of the plugin.
 */
function bfpi_run() {
    $plugin = new Bfpi();
    $plugin->run();
}

bfpi_run();

/**
 * Check and update database schema on every load (for existing installations)
 */
add_action('plugins_loaded', 'bfpi_check_db_version');

function bfpi_check_db_version() {
    $current_db_version = get_option('bfpi_db_version', '1.0.0');
    $required_db_version = '1.7.0';
    
    if (version_compare($current_db_version, $required_db_version, '<')) {
        // Run migration
        require_once plugin_dir_path(__FILE__) . 'includes/class-bfpi-activator.php';
        Bfpi_Activator::activate();
    }
}

/**
 * Add plugin action links
 */
add_filter('plugin_action_links_' . BFPI_PLUGIN_BASENAME, 'bfpi_action_links');

function bfpi_action_links($links) {
    // WP.org compliance: proper escaping for URLs and text
    $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=bfpi-import')) . '">' . esc_html__('Import', 'bootflow-product-xml-csv-importer') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

/**
 * HPOS compatibility declaration
 */
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});