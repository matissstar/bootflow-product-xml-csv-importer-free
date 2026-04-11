<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @since      1.0.0
 * @package    Bfpi
 * @subpackage Bfpi/includes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The core plugin class.
 */
class Bfpi {

    /**
     * The loader that's responsible for maintaining and registering all hooks.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Bfpi_Loader    $loader
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * @since    1.0.0
     */
    public function __construct() {
        if (defined('BFPI_VERSION')) {
            $this->version = BFPI_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'bfpi-import';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_cron_hooks();
        $this->add_custom_cron_schedules();
    }

    /**
     * Register cron hooks (must work in all contexts, not just admin).
     *
     * @since    1.0.0
     */
    private function define_cron_hooks() {
        // Instantiate admin class for cron callbacks
        $plugin_admin = new Bfpi_Admin($this->get_plugin_name(), $this->get_version());
        
        // WP Cron hook for processing chunks
        $this->loader->add_action('bfpi_process_chunk', $plugin_admin, 'handle_cron_process_chunk', 10, 3);
    }

    /**
     * Add custom cron schedules.
     *
     * @since    1.0.0
     * @access   private
     */
    private function add_custom_cron_schedules() {
        $this->loader->add_filter('cron_schedules', $this, 'custom_cron_schedules');
    }

    /**
     * Define custom cron schedule intervals.
     *
     * @since    1.0.0
     * @param    array $schedules Existing schedules
     * @return   array Modified schedules
     */
    public function custom_cron_schedules($schedules) {
        // Every 15 minutes
        $schedules['bfpi_15min'] = array(
            'interval' => 15 * 60,
            'display'  => __('Every 15 Minutes', 'bootflow-product-xml-csv-importer')
        );
        
        // Every 6 hours
        $schedules['bfpi_6hours'] = array(
            'interval' => 6 * 60 * 60,
            'display'  => __('Every 6 Hours', 'bootflow-product-xml-csv-importer')
        );
        
        return $schedules;
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        
        /**
         * The class responsible for orchestrating the actions and filters of the core plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-bfpi-loader.php';

        /**
         * Security and validation functionality
         */
        if (!class_exists('Bfpi_Security')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-bfpi-security.php';
        }

        /**
         * The class responsible for defining internationalization functionality of the plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-bfpi-i18n.php';

        /**
         * The class responsible for defining all actions in the admin area.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/admin/class-bfpi-admin.php';

        /**
         * Core functionality classes
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-bfpi-xml-parser.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-bfpi-csv-parser.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-bfpi-importer.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-bfpi-processor.php';
        
        // Optional classes - load only if they exist
        $ai_providers_file = plugin_dir_path(dirname(__FILE__)) . 'includes/class-bfpi-ai-providers.php';
        if (file_exists($ai_providers_file)) {
            require_once $ai_providers_file;
        }
        
        $scheduler_file = plugin_dir_path(dirname(__FILE__)) . 'includes/class-bfpi-scheduler.php';
        if (file_exists($scheduler_file)) {
            require_once $scheduler_file;
        }

        $this->loader = new Bfpi_Loader();
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale() {
        $plugin_i18n = new Bfpi_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
        // Reload with user's language preference (user is not yet known at plugins_loaded)
        $this->loader->add_action('admin_init', $plugin_i18n, 'reload_textdomain_for_user');
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        $plugin_admin = new Bfpi_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_init', $plugin_admin, 'redirect_old_slugs');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_admin_menu');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'print_pro_menu_script');
        $this->loader->add_action('wp_ajax_bfpi_upload_file', $plugin_admin, 'handle_file_upload');
        $this->loader->add_action('wp_ajax_bfpi_parse_structure', $plugin_admin, 'handle_parse_structure');
        $this->loader->add_action('wp_ajax_bfpi_start_import', $plugin_admin, 'handle_start_import');
        $this->loader->add_action('wp_ajax_bfpi_get_progress', $plugin_admin, 'handle_get_progress');
        $this->loader->add_action('wp_ajax_bfpi_kickstart', $plugin_admin, 'handle_kickstart_import');
        $this->loader->add_action('wp_ajax_bfpi_ping_cron', $plugin_admin, 'handle_ping_cron');

        $this->loader->add_action('wp_ajax_bfpi_save_mapping', $plugin_admin, 'handle_save_mapping');
        
        // Import management
        $this->loader->add_action('wp_ajax_bfpi_update_url', $plugin_admin, 'handle_update_import_url');
        
        // Control import endpoint (pause/resume/stop/retry)
        $this->loader->add_action('wp_ajax_bfpi_control_import', $plugin_admin, 'ajax_control_import');
        
        // Detect attribute values endpoint
        $this->loader->add_action('wp_ajax_bfpi_detect_attribute_values', $plugin_admin, 'ajax_detect_attribute_values');
        
        // Recipe management endpoints

        $this->loader->add_action('wp_ajax_bfpi_auto_detect_mapping', $plugin_admin, 'ajax_auto_detect_mapping');
        
        // Delete products with progress
        $this->loader->add_action('wp_ajax_bfpi_delete_products_batch', $plugin_admin, 'ajax_delete_products_batch');
        
        // Language switcher
        $this->loader->add_action('wp_ajax_bfpi_switch_language', 'Bfpi_i18n', 'ajax_switch_language');
        $this->loader->add_action('wp_ajax_bfpi_get_products_count', $plugin_admin, 'ajax_get_products_count');
        
        // Process batch endpoint (for async re-run)
        $this->loader->add_action('wp_ajax_bfpi_process_batch', $plugin_admin, 'handle_process_batch');
        
        // Cron endpoint - no priv needed, uses secret key


        // Individual import cron endpoint


    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
        
        // Initialize the scheduler for scheduled imports
        if (class_exists('Bfpi_Scheduler')) {
            Bfpi_Scheduler::get_instance();
        }
    }

    /**
     * The name of the plugin used to uniquely identify it.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    Bfpi_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }
}