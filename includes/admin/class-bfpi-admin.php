<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 * @package    Bfpi
 * @subpackage Bfpi/includes/admin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The admin-specific functionality of the plugin.
 */
class Bfpi_Admin {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles($hook) {
        // Only load on plugin pages
        if (strpos($hook, 'bfpi-import') === false) {
            return;
        }
        
        wp_enqueue_style(
            $this->plugin_name . '-admin',
            BFPI_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts($hook) {
        // Only load on plugin pages
        if (strpos($hook, 'bfpi-import') === false) {
            return;
        }
        
        wp_enqueue_script(
            $this->plugin_name . '-admin',
            BFPI_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util', 'jquery-ui-sortable'),
            $this->version . '.' . time(),
            true
        );

        // Localize script for AJAX
        wp_localize_script(
            $this->plugin_name . '-admin',
            'bfpi_ajax',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bfpi_nonce'),
                'strings' => array(
                    'uploading' => __('Uploading file...', 'bootflow-product-xml-csv-importer'),
                    'parsing' => __('Parsing file structure...', 'bootflow-product-xml-csv-importer'),
                    'importing' => __('Importing products...', 'bootflow-product-xml-csv-importer'),
                    'complete' => __('Import complete!', 'bootflow-product-xml-csv-importer'),
                    'error' => __('An error occurred:', 'bootflow-product-xml-csv-importer'),
                    'confirm_import' => __('Are you sure you want to start the import?', 'bootflow-product-xml-csv-importer'),
                    'test_ai' => __('Testing AI provider...', 'bootflow-product-xml-csv-importer')
                )
            )
        );
        
        // Also localize as bfpiImportData for consistency across all pages
        wp_localize_script(
            $this->plugin_name . '-admin',
            'bfpiImportData',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bfpi_nonce'),
                'i18n' => array(
                    'deleting_products' => __('Deleting Products', 'bootflow-product-xml-csv-importer'),
                    'products_deleted' => __('products deleted', 'bootflow-product-xml-csv-importer'),
                    'cancel' => __('Cancel', 'bootflow-product-xml-csv-importer'),
                    'close' => __('Close', 'bootflow-product-xml-csv-importer'),
                    'confirm_delete_products' => __('Are you sure you want to delete all products from this import?', 'bootflow-product-xml-csv-importer'),
                    'counting_products' => __('Counting products...', 'bootflow-product-xml-csv-importer'),
                    'deleting' => __('Deleting...', 'bootflow-product-xml-csv-importer'),
                    'no_products_found' => __('No products found to delete.', 'bootflow-product-xml-csv-importer'),
                    // translators: %d is the number of products deleted
                    'all_products_deleted' => __('All %d products deleted successfully!', 'bootflow-product-xml-csv-importer'),
                    // File preview navigation
                    'prev' => __('← Prev', 'bootflow-product-xml-csv-importer'),
                    'next' => __('Next →', 'bootflow-product-xml-csv-importer'),
                    'go' => __('Go', 'bootflow-product-xml-csv-importer'),
                    'go_to_product' => __('Go to product:', 'bootflow-product-xml-csv-importer'),
                    // translators: %1$d is the current product number, %2$d is the total number of products
                    'product_x_of_y' => __('Product %1$d of %2$d', 'bootflow-product-xml-csv-importer'),
                    // File preview group headers
                    'basic_info' => __('Basic Info', 'bootflow-product-xml-csv-importer'),
                    'pricing' => __('Pricing', 'bootflow-product-xml-csv-importer'),
                    'inventory' => __('Inventory', 'bootflow-product-xml-csv-importer'),
                    'shipping' => __('Shipping', 'bootflow-product-xml-csv-importer'),
                    'identifiers' => __('Identifiers', 'bootflow-product-xml-csv-importer'),
                    'other_fields' => __('Other Fields', 'bootflow-product-xml-csv-importer'),
                    // Expandable fields
                    'items' => __('items', 'bootflow-product-xml-csv-importer'),
                    'click_to_expand' => __('(click to expand)', 'bootflow-product-xml-csv-importer'),
                    'click' => __('(click)', 'bootflow-product-xml-csv-importer'),
                    /* translators: %d = number of fields */
                    'object_fields' => __('Object (%d fields)', 'bootflow-product-xml-csv-importer'),
                    /* translators: %d = number of items */
                    'all_items' => __('All %d items', 'bootflow-product-xml-csv-importer'),
                    /* translators: %d = number of nested/complex fields */
                    'nested_fields' => __('+ %d nested/complex fields (attributes, variations, etc.) available in dropdown', 'bootflow-product-xml-csv-importer'),
                    'preview' => __('Preview:', 'bootflow-product-xml-csv-importer'),
                    'variable_product_detected' => __('Variable Product Detected', 'bootflow-product-xml-csv-importer'),
                )
            )
        );
    }

    /**
     * Redirect old/incorrect page slugs to correct ones.
     *
     * @since    1.0.0
     */
    public function redirect_old_slugs() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        if (!isset($_GET['page'])) {
            return;
        }
        
        // Only redirect OLD slugs to NEW ones (don't include same->same mappings!)
        $old_slugs = array(
            'bfpi_import_logs' => 'bfpi-import-logs',
        );
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        $current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        
        if (isset($old_slugs[$current_page])) {
            $redirect_url = add_query_arg(array('page' => $old_slugs[$current_page]), admin_url('admin.php'));
            
            // Preserve specific known GET parameters only
            $allowed_params = array( 'import_id', 'step' );
            foreach ( $allowed_params as $param ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                if ( isset( $_GET[ $param ] ) ) {
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                    $redirect_url = add_query_arg( sanitize_key( $param ), absint( wp_unslash( $_GET[ $param ] ) ), $redirect_url );
                }
            }
            
            wp_safe_redirect($redirect_url);
            exit;
        }
    }
    
    /**
     * Render language switcher dropdown for admin pages.
     *
     * @since    1.0.0
     */
    private function render_language_switcher() {
        $locales = Bfpi_i18n::get_supported_locales();
        $current = Bfpi_i18n::get_admin_locale();
        $user_override = get_user_meta(get_current_user_id(), 'bfpi_admin_language', true);
        $is_auto = empty($user_override) || $user_override === 'auto';
        
        // Flag emoji map
        $flags = array(
            'en_US' => '🇬🇧', 'lv' => '🇱🇻', 'es_ES' => '🇪🇸', 'de_DE' => '🇩🇪',
            'fr_FR' => '🇫🇷', 'pt_BR' => '🇧🇷', 'ja' => '🇯🇵', 'it_IT' => '🇮🇹',
            'nl_NL' => '🇳🇱', 'ru_RU' => '🇷🇺', 'zh_CN' => '🇨🇳', 'pl_PL' => '🇵🇱',
            'tr_TR' => '🇹🇷', 'sv_SE' => '🇸🇪', 'id_ID' => '🇮🇩', 'ar' => '🇸🇦',
        );
        
        $current_flag = isset($flags[$current]) ? $flags[$current] : '🌐';
        $current_name = isset($locales[$current]) ? $locales[$current] : 'English';
        
        echo '<div class="bootflow-lang-switcher">';
        echo '<button type="button" class="bootflow-lang-btn" id="bootflow-lang-toggle">';
        echo '<span class="bootflow-lang-flag">' . wp_kses_post($current_flag) . '</span>';
        echo '<span class="bootflow-lang-name">' . esc_html($current_name) . '</span>';
        echo '<span class="dashicons dashicons-arrow-down-alt2"></span>';
        echo '</button>';
        echo '<div class="bootflow-lang-dropdown" id="bootflow-lang-dropdown" style="display:none;">';
        
        // Auto option
        $auto_class = $is_auto ? ' active' : '';
        echo '<a href="#" class="bootflow-lang-option' . esc_attr($auto_class) . '" data-locale="auto">';
        echo '<span class="bootflow-lang-flag">🌐</span>';
        echo '<span class="bootflow-lang-name">Auto (WordPress)</span>';
        echo '</a>';
        
        foreach ($locales as $locale => $name) {
            $flag = isset($flags[$locale]) ? $flags[$locale] : '🌐';
            $active_class = (!$is_auto && $current === $locale) ? ' active' : '';
            echo '<a href="#" class="bootflow-lang-option' . esc_attr($active_class) . '" data-locale="' . esc_attr($locale) . '">';
            echo '<span class="bootflow-lang-flag">' . wp_kses_post($flag) . '</span>';
            echo '<span class="bootflow-lang-name">' . esc_html($name) . '</span>';
            echo '</a>';
        }
        
        echo '</div></div>';
        echo '<input type="hidden" id="bfpi-lang-nonce" value="' . esc_attr(wp_create_nonce('bfpi_switch_language')) . '" />';
    }
    
    /**
     * Add admin menu items.
     *
     * @since    1.0.0
     */
    public function add_admin_menu() {
        // Add top-level menu with icon
        add_menu_page(
            __('Bootflow Importer', 'bootflow-product-xml-csv-importer'),
            __('Bootflow Import', 'bootflow-product-xml-csv-importer'),
            'manage_options',
            'bfpi-import',
            array($this, 'display_import_page'),
            'dashicons-upload',
            56 // Position (after WooCommerce at 55)
        );

        // Add submenu pages
        add_submenu_page(
            'bfpi-import',
            __('New Import', 'bootflow-product-xml-csv-importer'),
            __('New Import', 'bootflow-product-xml-csv-importer'),
            'manage_options',
            'bfpi-import',
            array($this, 'display_import_page')
        );

        add_submenu_page(
            'bfpi-import',
            __('Import History', 'bootflow-product-xml-csv-importer'),
            __('History', 'bootflow-product-xml-csv-importer'),
            'manage_options',
            'bfpi-import-history',
            array($this, 'display_history_page')
        );

        // Upgrade link (opens in new tab via JS)
        add_submenu_page(
            'bfpi-import',
            __('Get PRO', 'bootflow-product-xml-csv-importer'),
            '<span style="color:#f0c33c;">' . esc_html__('Get PRO', 'bootflow-product-xml-csv-importer') . ' ★</span>',
            'manage_options',
            'bfpi-get-pro',
            array($this, 'redirect_to_pro_page')
        );

    }

    /**
     * Display main import page.
     *
     * @since    1.0.0
     */
    public function display_import_page() {
        // Sanitize GET parameters
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $import_id = isset($_GET['import_id']) ? absint(wp_unslash($_GET['import_id'])) : 0;

        // Handle Re-run action with resume dialog
        if ($action === 'rerun' && $import_id > 0) {
            
            // Check if this is a confirmed action (resume or restart)
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (isset($_GET['resume_action'])) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $resume_action = sanitize_key(wp_unslash($_GET['resume_action']));
                $this->rerun_import($import_id, $resume_action === 'resume');
                return;
            }
            
            // Check if import has progress
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query
            $import = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bfpi_imports WHERE id = %d",
                $import_id
            ), ARRAY_A);
            
            if ($import && $import['processed_products'] > 0 && $import['processed_products'] < $import['total_products']) {
                // Show resume dialog
                $this->display_resume_dialog($import);
                return;
            }
            
            // No progress or completed - just restart
            $this->rerun_import($import_id, false);
            return;
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $step = isset($_GET['step']) ? absint(wp_unslash($_GET['step'])) : 1;
        
        echo '<div class="wrap bfpi-import-wrap">';
        echo '<div class="bootflow-header-row">';
        echo '<h1>' . esc_html__('Bootflow – Product XML & CSV Importer', 'bootflow-product-xml-csv-importer') . '</h1>';
        $this->render_language_switcher();
        echo '</div>';
        
        // Progress indicator
        $this->display_progress_indicator($step);
        
        switch ($step) {
            case 1:
                $this->display_step_1_upload();
                break;
            case 2:
                $this->display_step_2_mapping();
                break;
            case 3:
                $this->display_step_3_progress();
                break;
            default:
                $this->display_step_1_upload();
                break;
        }
        
        echo '</div>';
    }

    /**
     * Display progress indicator.
     *
     * @since    1.0.0
     * @param    int $current_step Current step
     */
    private function display_progress_indicator($current_step) {
        $steps = array(
            1 => __('Upload File', 'bootflow-product-xml-csv-importer'),
            2 => __('Map Fields', 'bootflow-product-xml-csv-importer'),
            3 => __('Import Progress', 'bootflow-product-xml-csv-importer')
        );
        
        echo '<div class="bfpi-progress-indicator">';
        echo '<ul class="bfpi-steps">';
        
        foreach ($steps as $step_num => $step_name) {
            $class = 'step';
            if ($step_num < $current_step) {
                $class .= ' completed';
            } elseif ($step_num == $current_step) {
                $class .= ' active';
            }
            
            echo '<li class="' . esc_attr($class) . '">';
            echo '<span class="step-number">' . esc_html($step_num) . '</span>';
            echo '<span class="step-name">' . esc_html($step_name) . '</span>';
            echo '</li>';
        }
        
        echo '</ul>';
        echo '</div>';
    }

    /**
     * Display step 1: File upload.
     *
     * @since    1.0.0
     */
    private function display_step_1_upload() {
        include_once BFPI_PLUGIN_DIR . 'includes/admin/partials/step-1-upload.php';
    }

    /**
     * Display step 2: Field mapping.
     *
     * @since    1.0.0
     */
    private function display_step_2_mapping() {
        global $wpdb;
        
        // Check if Edit mode (import_id in URL)
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $import_id = isset($_GET['import_id']) ? absint(wp_unslash($_GET['import_id'])) : 0;
        
        // HANDLE POST SUBMISSION FIRST (before any output)
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ($import_id > 0 && isset($_POST['update_import'])) {
            // Redirect to display_import_details for POST handling
            $this->display_import_details($import_id);
            return;
        }
        
        // Get parameters from URL OR from database
        if ($import_id > 0) {
            // Edit mode - load from database
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query
            $import = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bfpi_imports WHERE id = %d",
                $import_id
            ), ARRAY_A);
            
            if ($import) {
                $file_path = $import['file_path'];
                $file_type = $import['file_type'];
                $import_name = $import['name'];
                $schedule_type = $import['schedule_type'];
                $product_wrapper = $import['product_wrapper'];
                $update_existing = $import['update_existing'];
                $skip_unchanged = $import['skip_unchanged'];
                $batch_size = $import['batch_size'] ?? 50;
            } else {
                $file_path = '';
                $file_type = '';
                $import_name = '';
                $schedule_type = '';
                $product_wrapper = 'product';
                $update_existing = '0';
                $skip_unchanged = '0';
                $batch_size = 50;
            }
        } else {
            // New import mode - get from URL parameters
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $file_path = isset($_GET['file_path']) ? sanitize_text_field(wp_unslash($_GET['file_path'])) : '';
            // Validate file_path is within the uploads directory
            if ( $file_path ) {
                $upload_dir = wp_upload_dir();
                $real_path  = realpath( $file_path );
                if ( ! $real_path || strpos( $real_path, realpath( $upload_dir['basedir'] ) ) !== 0 ) {
                    $file_path = '';
                }
            }
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $file_type = isset($_GET['file_type']) ? sanitize_key(wp_unslash($_GET['file_type'])) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $import_name = isset($_GET['import_name']) ? sanitize_text_field(wp_unslash($_GET['import_name'])) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $schedule_type = isset($_GET['schedule_type']) ? sanitize_key(wp_unslash($_GET['schedule_type'])) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $product_wrapper = isset($_GET['product_wrapper']) ? sanitize_text_field(wp_unslash($_GET['product_wrapper'])) : 'product';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $update_existing = isset($_GET['update_existing']) ? sanitize_text_field(wp_unslash($_GET['update_existing'])) : '0';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $skip_unchanged = isset($_GET['skip_unchanged']) ? sanitize_text_field(wp_unslash($_GET['skip_unchanged'])) : '0';
        }
        
        // Pass step-2 data to JavaScript via wp_add_inline_script
        if (!empty($file_path)) {
            $step2_data = array(
                'file_path'       => $file_path,
                'file_type'       => $file_type,
                'import_name'     => $import_name,
                'schedule_type'   => $schedule_type,
                'product_wrapper' => $product_wrapper,
                'update_existing' => $update_existing,
                'skip_unchanged'  => $skip_unchanged,
                'batch_size'      => intval($batch_size ?? 50),
                'ajax_url'        => admin_url('admin-ajax.php'),
                'nonce'           => wp_create_nonce('bfpi_nonce'),
            );
            wp_add_inline_script(
                $this->plugin_name . '-admin',
                'var bfpiImportData = ' . wp_json_encode($step2_data) . ';',
                'before'
            );
        }
        
        include_once BFPI_PLUGIN_DIR . 'includes/admin/partials/step-2-mapping.php';
    }

    /**
     * Display step 3: Import progress.
     *
     * @since    1.0.0
     */
    private function display_step_3_progress() {
        include_once BFPI_PLUGIN_DIR . 'includes/admin/partials/step-3-progress.php';
    }

    /**
     * Display import history page.
     *
     * @since    1.0.0
     */
    public function display_history_page() {
        global $wpdb;
        
        // Sanitize GET parameters
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $import_id = isset($_GET['import_id']) ? absint(wp_unslash($_GET['import_id'])) : 0;
        
        // Handle edit action - redirect to Step 2 with import data
        if ($action === 'edit' && $import_id > 0) {
            $this->display_step_2_mapping();
            return;
        }
        
        // Handle view action
        if ($action === 'view' && $import_id > 0) {
            $this->display_import_details($import_id);
            return;
        }
        
        // Handle STOP action - stop the import immediately
        if ($action === 'stop' && $import_id > 0) {
            $table = $wpdb->prefix . 'bfpi_imports';
            
            // Update status to stopped/failed
            $wpdb->update($table, array('status' => 'failed'), array('id' => $import_id), array('%s'), array('%d')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table update
            
            // Clear ALL scheduled cron jobs for this import
            $hooks = array('bfpi_process_chunk', 'bfpi_retry_chunk', 'bfpi_single_chunk');
            foreach ($hooks as $hook) {
                // Clear with import_id as first argument
                wp_clear_scheduled_hook($hook, array($import_id));
                // Also try clearing with just import_id
                $crons = _get_cron_array();
                if (!empty($crons)) {
                    foreach ($crons as $timestamp => $cron) {
                        if (isset($cron[$hook])) {
                            foreach ($cron[$hook] as $key => $data) {
                                if (!empty($data['args']) && isset($data['args'][0]) && intval($data['args'][0]) === $import_id) {
                                    wp_unschedule_event($timestamp, $hook, $data['args']);
                                }
                            }
                        }
                    }
                }
            }
            
            // Clear transient locks to stop any running batch immediately
            delete_transient('bfpi_import_lock_' . $import_id);
            delete_transient('bfpi_import_lock_time_' . $import_id);
            
            // Set kill flag transient to stop any currently running process
            set_transient('bfpi_import_killed_' . $import_id, time(), HOUR_IN_SECONDS);
            
            // Also set global kill flag transient
            set_transient('bfpi_import_killed_global', $import_id . ':' . time(), HOUR_IN_SECONDS);
            
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Import stopped successfully.', 'bootflow-product-xml-csv-importer') . '</p></div>';
        }
        
        // Handle delete import action
        if ($action === 'delete_import' && $import_id > 0) {
            
            // Verify nonce
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'delete_import_' . $import_id)) {
                // Get import data to access file_path
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query
                $import = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}bfpi_imports WHERE id = %d",
                    $import_id
                ), ARRAY_A);
                
                // Delete the file if it exists
                if ($import && !empty($import['file_path']) && file_exists($import['file_path'])) {
                    @wp_delete_file($import['file_path']);
                }
                
                // Delete database record
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table delete
                $deleted = $wpdb->delete(
                    $wpdb->prefix . 'bfpi_imports',
                    array('id' => $import_id),
                    array('%d')
                );
                
                if ($deleted) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Import and file deleted successfully.', 'bootflow-product-xml-csv-importer') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Failed to delete import.', 'bootflow-product-xml-csv-importer') . '</p></div>';
                }
            }
        }
        
        // Handle delete products action
        if ($action === 'delete_products' && $import_id > 0) {
            
            // Verify nonce
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'delete_products_' . $import_id)) {
                // Get all products associated with this import
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $product_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_bfpi_import_id' AND meta_value = %d",
                    $import_id
                ));
                
                $deleted_count = 0;
                foreach ($product_ids as $product_id) {
                    if (wp_delete_post($product_id, true)) {
                        $deleted_count++;
                    }
                }
                
                // Update import's processed_products count to 0
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table update
                $wpdb->update(
                    $wpdb->prefix . 'bfpi_imports',
                    array('processed_products' => 0),
                    array('id' => $import_id),
                    array('%d'),
                    array('%d')
                );
                
                if ($deleted_count > 0) {
                    // translators: %d is the number of deleted products
                    echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('%d products deleted successfully.', 'bootflow-product-xml-csv-importer'), intval($deleted_count)) . '</p></div>';
                } else {
                    echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('No products found to delete.', 'bootflow-product-xml-csv-importer') . '</p></div>';
                }
            }
        }
        
        echo '<div class="wrap">';
        echo '<div class="bootflow-header-row">';
        echo '<h1>' . esc_html__('Import History', 'bootflow-product-xml-csv-importer') . '</h1>';
        $this->render_language_switcher();
        echo '</div>';
        
        // Get imports
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $imports = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder -- %i requires WP 6.2+
            $wpdb->prepare( "SELECT * FROM %i ORDER BY created_at DESC", $wpdb->prefix . 'bfpi_imports' ),
            ARRAY_A
        );
        
        if (empty($imports)) {
            echo '<p>' . esc_html__('No imports found.', 'bootflow-product-xml-csv-importer') . '</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . esc_html__('Name', 'bootflow-product-xml-csv-importer') . '</th>';
            echo '<th>' . esc_html__('File Type', 'bootflow-product-xml-csv-importer') . '</th>';
            echo '<th>' . esc_html__('Products', 'bootflow-product-xml-csv-importer') . '</th>';
            echo '<th>' . esc_html__('Status', 'bootflow-product-xml-csv-importer') . '</th>';
            echo '<th>' . esc_html__('Schedule', 'bootflow-product-xml-csv-importer') . '</th>';
            echo '<th>' . esc_html__('Created', 'bootflow-product-xml-csv-importer') . '</th>';
            echo '<th>' . esc_html__('Last Run', 'bootflow-product-xml-csv-importer') . '</th>';
            echo '<th>' . esc_html__('Actions', 'bootflow-product-xml-csv-importer') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($imports as $import) {
                // Get actual product count from database (products with this import_id meta)
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $actual_product_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}postmeta WHERE meta_key = '_bfpi_import_id' AND meta_value = %d",
                    $import['id']
                ));
                
                $schedule_label = 'Disabled';
                if (!empty($import['schedule_type']) && $import['schedule_type'] !== 'none' && $import['schedule_type'] !== 'disabled') {
                    $schedule_labels = array(
                        'bfpi_15min' => __('Every 15 min', 'bootflow-product-xml-csv-importer'),
                        'hourly' => __('Hourly', 'bootflow-product-xml-csv-importer'),
                        'bfpi_6hours' => __('Every 6h', 'bootflow-product-xml-csv-importer'),
                        'daily' => __('Daily', 'bootflow-product-xml-csv-importer'),
                        'weekly' => __('Weekly', 'bootflow-product-xml-csv-importer'),
                        'monthly' => __('Monthly', 'bootflow-product-xml-csv-importer')
                    );
                    $schedule_label = $schedule_labels[$import['schedule_type']] ?? $import['schedule_type'];
                }
                
                echo '<tr>';
                echo '<td>' . esc_html($import['name']) . '</td>';
                echo '<td>' . esc_html(strtoupper($import['file_type'])) . '</td>';
                // translators: %1$d is the database count, %2$d is processed count, %3$d is total in file - Show actual products in DB / processed from file / total in file
                echo '<td title="' . esc_attr(sprintf(__('In database: %1$d, Processed: %2$d, In file: %3$d', 'bootflow-product-xml-csv-importer'), $actual_product_count, $import['processed_products'], $import['total_products'])) . '">' . esc_html($actual_product_count) . ' <small style="color:#666;">(' . esc_html($import['processed_products']) . '/' . esc_html($import['total_products']) . ')</small></td>';
                echo '<td>' . esc_html(ucfirst($import['status'])) . '</td>';
                echo '<td>' . esc_html($schedule_label) . '</td>';
                echo '<td>' . esc_html(Bfpi_i18n::localize_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($import['created_at']))) . '</td>';
                echo '<td>';
                if ($import['last_run']) {
                    $last_run_ts = strtotime($import['last_run']);
                    $ago_seconds = current_time('timestamp') - $last_run_ts;
                    if ($ago_seconds < 60) {
                        $ago_text = __('just now', 'bootflow-product-xml-csv-importer');
                    } elseif ($ago_seconds < 3600) {
                        /* translators: %d = number of minutes */
                        $ago_text = sprintf(__('%d min ago', 'bootflow-product-xml-csv-importer'), intval($ago_seconds / 60));
                    } elseif ($ago_seconds < 86400) {
                        $hours = intval($ago_seconds / 3600);
                        $mins = intval(($ago_seconds % 3600) / 60);
                        /* translators: %1$d = hours, %2$d = minutes */
                        $ago_text = sprintf(__('%1$dh %2$dm ago', 'bootflow-product-xml-csv-importer'), $hours, $mins);
                    } else {
                        /* translators: %d = number of days */
                        $ago_text = sprintf(__('%d days ago', 'bootflow-product-xml-csv-importer'), intval($ago_seconds / 86400));
                    }
                    echo esc_html(Bfpi_i18n::localize_date('d.m.Y H:i:s', $last_run_ts));
                    echo '<br><small style="color:#888;">(' . esc_html($ago_text) . ')</small>';
                    // Show next scheduled run
                    if (!empty($import['schedule_type']) && $import['schedule_type'] !== 'none' && $import['schedule_type'] !== 'disabled') {
                        $intervals = array('bfpi_15min'=>900, 'hourly'=>3600, 'bfpi_6hours'=>21600, 'daily'=>86400, 'weekly'=>604800, 'monthly'=>2592000);
                        $interval = $intervals[$import['schedule_type']] ?? 0;
                        if ($interval > 0) {
                            $next_run_ts = $last_run_ts + $interval;
                            $until_seconds = $next_run_ts - current_time('timestamp');
                            if ($until_seconds <= 0) {
                                $next_text = __('⏳ due now', 'bootflow-product-xml-csv-importer');
                            } elseif ($until_seconds < 60) {
                                $next_text = __('⏳ <1 min', 'bootflow-product-xml-csv-importer');
                            } elseif ($until_seconds < 3600) {
                                /* translators: %d = number of minutes */
                                $next_text = sprintf(__('⏳ in %d min', 'bootflow-product-xml-csv-importer'), intval($until_seconds / 60));
                            } else {
                                /* translators: %1$d = hours, %2$d = minutes */
                                $next_text = sprintf(__('⏳ in %1$dh %2$dm', 'bootflow-product-xml-csv-importer'), intval($until_seconds / 3600), intval(($until_seconds % 3600) / 60));
                            }
                            echo '<br><small style="color:#0073aa;">' . esc_html($next_text) . '</small>';
                        }
                    }
                } else {
                    echo esc_html__('Never', 'bootflow-product-xml-csv-importer');
                }
                echo '</td>';
                echo '<td>';
                
                // Edit button
                echo '<a href="' . esc_url(admin_url('admin.php?page=bfpi-import-history&action=edit&import_id=' . $import['id'])) . '" class="button button-small button-primary">' . esc_html__('Edit', 'bootflow-product-xml-csv-importer') . '</a> ';
                
                // Stop button - only show if import is processing
                if ($import['status'] === 'processing') {
                    echo '<a href="' . esc_url(admin_url('admin.php?page=bfpi-import-history&action=stop&import_id=' . $import['id'])) . '" class="button button-small">' . esc_html__('Stop', 'bootflow-product-xml-csv-importer') . '</a> ';
                }
                
                // Re-run button
                echo '<a href="' . esc_url(admin_url('admin.php?page=bfpi-import&action=rerun&import_id=' . $import['id'])) . '" class="button button-small">' . esc_html__('Re-run', 'bootflow-product-xml-csv-importer') . '</a> ';
                
                // Delete import button
                $delete_import_url = wp_nonce_url(
                    admin_url('admin.php?page=bfpi-import-history&action=delete_import&import_id=' . $import['id']),
                    'delete_import_' . $import['id']
                );
                echo '<a href="' . esc_url($delete_import_url) . '" class="button button-small button-link-delete" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this import and its file?', 'bootflow-product-xml-csv-importer')) . '\')">' . esc_html__('Delete import', 'bootflow-product-xml-csv-importer') . '</a> ';
                
                // Delete products button (AJAX with progress)
                echo '<button type="button" class="button button-small button-link-delete delete-products-ajax" data-import-id="' . esc_attr($import['id']) . '" data-nonce="' . esc_attr(wp_create_nonce('bfpi_nonce')) . '">' . esc_html__('Delete products', 'bootflow-product-xml-csv-importer') . '</button>';
                
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
        }
        
        echo '</div>';
    }

    /**
     * Display import details with full editing capability.
     */
    private function display_import_details($import_id) {
        global $wpdb;
        
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('=== display_import_details() called for import_id: ' . $import_id); }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query
        $import = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bfpi_imports WHERE id = %d", $import_id), ARRAY_A);

        if (!$import) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Import not found in database for ID: ' . $import_id); }
            echo '<div class="wrap"><h1>' . esc_html__('Import Not Found', 'bootflow-product-xml-csv-importer') . '</h1>';
            echo '<p><a href="' . esc_url(admin_url('admin.php?page=bfpi-import-history')) . '" class="button">Back</a></p></div>';
            return;
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Import found: ' . $import['name'] . ', has field_mappings: ' . (empty($import['field_mappings']) ? 'NO' : 'YES')); }

        // Patch: If file_path is empty, try to auto-fill from plugin upload dir
        if (empty($import['file_path'])) {
            $upload_dir = wp_upload_dir();
            $plugin_upload_dir = $upload_dir['basedir'] . '/bootflow-product-importer/';
            if (is_dir($plugin_upload_dir)) {
                $files = glob($plugin_upload_dir . '*');
                if ($files && count($files) > 0) {
                    // Try to find a file that matches import name or type
                    $found = false;
                    foreach ($files as $f) {
                        if (stripos(basename($f), $import['name']) !== false || stripos(basename($f), $import['file_type']) !== false) {
                            $import['file_path'] = $f;
                            $found = true;
                            break;
                        }
                    }
                    // If not found, just use the first file
                    if (!$found) {
                        $import['file_path'] = $files[0];
                    }
                }
            }
        }
        
        // Debug: Log request method
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('=== EDIT PAGE LOAD ==='); }

        
        // Handle "Run Import Now" button - saves mapping AND starts import
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (isset($_POST['run_import_now'])) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('=== RUN IMPORT NOW CLICKED ==='); }
            
            // Verify nonce
            if (!check_admin_referer('update_import_' . $import_id, '_wpnonce', false)) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Nonce check FAILED for RUN IMPORT NOW'); }
                wp_die(esc_html__('Security check failed. Please try again.', 'bootflow-product-xml-csv-importer'));
            }
            
            // First, save the mappings (same as update_import)
            $_POST['update_import'] = true; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Trigger save logic below
            // Don't return - let it fall through to save logic, then redirect to step 3
        }
        
        // Handle form submission (only validate nonce on POST, not on GET/view)
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (isset($_POST['update_import'])) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('POST data present: YES'); }
            
            // Verify nonce only for POST submissions
            if (!check_admin_referer('update_import_' . $import_id, '_wpnonce', false)) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Nonce check FAILED for POST submission'); }
                wp_die(esc_html__('Security check failed. Please try again.', 'bootflow-product-xml-csv-importer'));
            }
            
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Nonce check: VALID'); }
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('=== SAVING IMPORT MAPPINGS ==='); }
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $field_mapping = isset($_POST['field_mapping']) ? map_deep( wp_unslash( $_POST['field_mapping'] ), 'sanitize_text_field' ) : array();
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $custom_fields = isset($_POST['custom_fields']) ? map_deep( wp_unslash( $_POST['custom_fields'] ), 'sanitize_text_field' ) : array();
            
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Raw field_mapping count: ' . count($field_mapping)); }

            
            // Inject engine JSON data from edit form hidden inputs
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            if (!empty($_POST['pricing_engine_json'])) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                $pe_data = json_decode(wp_unslash($_POST['pricing_engine_json']), true);
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('BFPI ENGINE: pricing_engine_json RAW = ' . substr(sanitize_text_field(wp_unslash($_POST['pricing_engine_json'])), 0, 200)); }
                if (is_array($pe_data)) {
                    $field_mapping['pricing_engine'] = map_deep($pe_data, 'sanitize_text_field');
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('BFPI ENGINE: pricing_engine INJECTED into field_mapping'); }
                }
            } else {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('BFPI ENGINE: pricing_engine_json is EMPTY in POST'); }
            }
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            if (!empty($_POST['shipping_class_engine_json'])) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                $sc_data = json_decode(wp_unslash($_POST['shipping_class_engine_json']), true);
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('BFPI ENGINE: shipping_class_engine_json RAW = ' . substr(sanitize_text_field(wp_unslash($_POST['shipping_class_engine_json'])), 0, 200)); }
                if (is_array($sc_data)) {
                    $field_mapping['shipping_class_engine'] = map_deep($sc_data, 'sanitize_text_field');
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('BFPI ENGINE: shipping_class_engine INJECTED into field_mapping'); }
                }
            } else {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('BFPI ENGINE: shipping_class_engine_json is EMPTY in POST'); }
            }
            
            // Merge mappings - save ALL fields that have processing_mode or source
            $all_mappings = array();
            // Special taxonomy and engine keys - always save if present
            $always_save_keys = array('categories', 'tags', 'brand', 'pricing_engine', 'shipping_class_engine', 'attributes_variations', 'shipping_class_formula');
            foreach ($field_mapping as $field_key => $mapping_data) {
                // Special keys that should always be saved (taxonomy, engines, etc.)
                if (in_array($field_key, $always_save_keys, true)) {
                    $all_mappings[$field_key] = $mapping_data;
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log("Saving special field: {$field_key}"); }
                }
                // Save field if it has processing_mode OR source OR update_on_sync flag
                // This ensures update_on_sync checkbox state is always saved
                elseif (!empty($mapping_data['processing_mode']) || !empty($mapping_data['source']) || isset($mapping_data['update_on_sync'])) {
                    $all_mappings[$field_key] = $mapping_data;
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log("Saving field: {$field_key} - mode=" . ($mapping_data['processing_mode'] ?? 'none') . " source=" . ($mapping_data['source'] ?? 'none') . " update_on_sync=" . ($mapping_data['update_on_sync'] ?? 'not set')); }
                } else {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log("Skipping empty field: {$field_key}"); }
                }
            }
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Merged mappings count: ' . count($all_mappings)); }

            
            // CRITICAL: If no mappings, don't overwrite existing ones!
            if (empty($all_mappings) && !empty($import['field_mappings'])) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WARNING: No new mappings provided, keeping existing mappings'); }
                $all_mappings = json_decode($import['field_mappings'], true);
                if (!is_array($all_mappings)) {
                    $all_mappings = array();
                }
            }
            
            // Add custom fields
            foreach ($custom_fields as $cf) {
                if (!empty($cf['name']) && !empty($cf['source'])) {
                    $all_mappings['_custom_' . sanitize_key($cf['name'])] = $cf;
                }
            }
            
            // Collect import filters
            $import_filters = array();
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $filter_logic = isset($_POST['filter_logic']) ? sanitize_text_field(wp_unslash($_POST['filter_logic'])) : 'AND';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $draft_non_matching = isset($_POST['draft_non_matching']) ? 1 : 0;
            
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            if (isset($_POST['import_filters']) && is_array($_POST['import_filters'])) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                $raw_filters = map_deep( wp_unslash( $_POST['import_filters'] ), 'sanitize_text_field' );
                foreach ($raw_filters as $filter) {
                    if (!empty($filter['field']) && !empty($filter['operator'])) {
                        // Validate operator is in allowed list
                        $allowed_operators = array('=', '!=', '>', '<', '>=', '<=', 'contains', 'not_contains', 'empty', 'not_empty');
                        $operator = in_array($filter['operator'], $allowed_operators) ? $filter['operator'] : '=';
                        
                        $filter_data = array(
                            'field' => sanitize_text_field($filter['field']),
                            'operator' => $operator,  // Don't sanitize - use validated value
                            'value' => sanitize_text_field($filter['value'] ?? '')
                        );
                        
                        // Add logic if present (for chaining with next filter)
                        if (isset($filter['logic'])) {
                            $filter_data['logic'] = in_array($filter['logic'], array('AND', 'OR')) ? $filter['logic'] : 'AND';
                        }
                        
                        $import_filters[] = $filter_data;
                    }
                }
            }
            
            // IMPORTANT: Preserve file_path and file_url from existing record
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query
            $existing_import = $wpdb->get_row($wpdb->prepare(
                "SELECT file_path, file_url FROM {$wpdb->prefix}bfpi_imports WHERE id = %d", 
                $import_id
            ), ARRAY_A);
            
            // Prepare custom_fields array with full data (including ai_prompt, ai_provider, php_formula etc.)
            $custom_fields_to_save = array();
            
            foreach ($custom_fields as $cf) {
                if (!empty($cf['name']) && !empty($cf['source'])) {
                    $custom_fields_to_save[] = $cf;
                }
            }
            
            
            $update_data = array(
                'file_path' => $existing_import['file_path'],  // Preserve file path
                'file_url' => $existing_import['file_url'],    // Preserve file URL
                'field_mappings' => wp_json_encode($all_mappings),
                'custom_fields' => wp_json_encode($custom_fields_to_save),  // Save custom fields separately too
                'import_filters' => wp_json_encode($import_filters),
                'filter_logic' => $filter_logic,
                'draft_non_matching' => $draft_non_matching,
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                'schedule_type' => sanitize_text_field(wp_unslash($_POST['schedule_type'] ?? $_POST['schedule_type_hidden'] ?? 'none')),
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                'schedule_method' => sanitize_text_field(wp_unslash($_POST['schedule_method'] ?? $_POST['schedule_method_hidden'] ?? 'action_scheduler')),
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                'update_existing' => isset($_POST['update_existing']) ? '1' : '0',
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                'skip_unchanged' => isset($_POST['skip_unchanged']) ? '1' : '0',
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                'handle_missing' => isset($_POST['handle_missing']) ? '1' : '0',
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                'missing_action' => sanitize_text_field(wp_unslash($_POST['missing_action'] ?? 'draft')),
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                'delete_variations' => isset($_POST['delete_variations']) ? '1' : '0',
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                'batch_size' => isset($_POST['batch_size']) ? absint(wp_unslash($_POST['batch_size'])) : 50
            );
            
            // DEBUG: Log schedule fields            
            // DEBUG: Log batch_size specifically
            
            // DEBUG: Show shipping_class_formula in JSON before saving
            if (isset($all_mappings['shipping_class_formula'])) {
            } else {
            }
            
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('JSON to save: ' . substr(wp_json_encode($all_mappings), 0, 500)); }
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Filters to save: ' . wp_json_encode($import_filters)); }
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Filter logic: ' . $filter_logic); }
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Updating import ID: ' . $import_id); }
            
            // DEBUG: Show what we're about to save
            /*
            echo '<div style="background: #fff; padding: 20px; margin: 20px 0; border: 2px solid #0073aa;">';
            echo '<h2>DEBUG: Data being saved to database</h2>';
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
            echo '<h3>Images field:</h3><pre>' . print_r($all_mappings['images'] ?? 'NOT SET', true) . '</pre>';
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
            echo '<h3>Featured Image field:</h3><pre>' . print_r($all_mappings['featured_image'] ?? 'NOT SET', true) . '</pre>';
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
            echo '<h3>Import Filters (' . count($import_filters) . '):</h3><pre>' . print_r($import_filters, true) . '</pre>';
            echo '<h3>Filter Logic: ' . $filter_logic . '</h3>';
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
            echo '<h3>All mappings (first 5):</h3><pre>' . print_r(array_slice($all_mappings, 0, 5, true), true) . '</pre>';
            echo '<h3>Total fields: ' . count($all_mappings) . '</h3>';
            echo '</div>';
            */
            
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Import ID: ' . $import_id); }
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Total mappings: ' . count($all_mappings)); }
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Mappings JSON length: ' . strlen(wp_json_encode($all_mappings))); }
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('File path to save: ' . ($update_data['file_path'] ?? 'NULL')); }
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('schedule_type to save: ' . $update_data['schedule_type']); }
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('schedule_method to save: ' . $update_data['schedule_method']); }
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table update
            $result = $wpdb->update(
                $wpdb->prefix . 'bfpi_imports', 
                $update_data, 
                array('id' => $import_id), 
                array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d'),  // 14 formats for 14 fields
                array('%d')
            );
            
            // DEBUG: Verify what was actually saved
            if ($result !== false) {
                $saved_import = $wpdb->get_row($wpdb->prepare("SELECT field_mappings, file_path FROM {$wpdb->prefix}bfpi_imports WHERE id = %d", $import_id), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query
                if ($saved_import) {
                    $saved_mappings = json_decode($saved_import['field_mappings'], true);
                    if (isset($saved_mappings['sku'])) {
                    }
                    $saved_mappings = json_decode($saved_import['field_mappings'], true);
                    if (isset($saved_mappings['shipping_class_formula'])) {
                    } else {
                    }
                }
            }
            
            // Check if "Run Import Now" was clicked
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $should_run_import = isset($_POST['run_import_now']);
            
            if ($should_run_import) {
                
                // Set import status to processing and reset processed count
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table update
                $wpdb->update(
                    $wpdb->prefix . 'bfpi_imports',
                    array(
                        'status' => 'pending',  // Set to pending - progress page will kickstart
                        'processed_products' => 0  // Reset processed count
                    ),
                    array('id' => $import_id),
                    array('%s', '%d'),
                    array('%d')
                );
                
                // DON'T trigger import here - let progress page kickstart handle it
                // This prevents double processing
                
                // Redirect to progress page (step 3)
                $redirect_url = admin_url('admin.php?page=bfpi-import&step=3&import_id=' . $import_id);
                wp_safe_redirect($redirect_url);
                exit;
            }
            
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Import updated successfully.', 'bootflow-product-xml-csv-importer') . '</p></div>';
            $import = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bfpi_imports WHERE id = %d", $import_id), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query
            
            // DEBUG: Log reloaded batch_size
        }
        
        // Get existing mappings from field_mappings column
        $existing_mappings = array();
        $mapping_source = 'field_mappings';
        
        if (!empty($import['field_mappings'])) {
            $existing_mappings = json_decode($import['field_mappings'], true);
            if (!is_array($existing_mappings)) {
                $existing_mappings = array();
            }
        }
        
        if (!is_array($existing_mappings)) {
            $existing_mappings = array();
        }
        
        // Load saved custom fields from BOTH sources:
        // 1. From field_mappings with '_custom_' prefix (new format)
        // 2. From dedicated custom_fields column (old format)
        $saved_custom_fields = array();
        
        // FORCE DEBUG - always log
        
        // First, check field_mappings for _custom_ prefixed keys
        foreach ($existing_mappings as $key => $mapping) {
            if (strpos($key, '_custom_') === 0 && is_array($mapping)) {
                $saved_custom_fields[] = $mapping;
            }
        }
        
        // If no custom fields found in field_mappings, check custom_fields column
        if (empty($saved_custom_fields) && !empty($import['custom_fields'])) {
            $legacy_custom_fields = json_decode($import['custom_fields'], true);
            if (is_array($legacy_custom_fields)) {
                $saved_custom_fields = $legacy_custom_fields;
            }
        }
        
        
        if (!empty($existing_mappings)) {        } else {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Import Edit - No field_mappings data found in import record'); }
        }
        
        if (!empty($saved_custom_fields)) {        }
        
        // Generate secret key
        $import_secret = get_option('bfpi_secret_' . $import_id);
        if (empty($import_secret)) {
            $import_secret = wp_generate_password(32, false);
            update_option('bfpi_secret_' . $import_id, $import_secret);
        }
        
        $cron_url = admin_url('admin-ajax.php?action=bfpi_single_cron&import_id=' . $import_id . '&secret=' . $import_secret);
        
        // Load file structure for dropdowns - use XML Parser for proper nested field support
        $file_path = $import['file_path'];
        $file_fields = array();
        if (file_exists($file_path)) {
            if ($import['file_type'] === 'xml') {
                
                // Use XML Parser class to get proper structure with nested fields
                $xml_parser = new Bfpi_XML_Parser();
                $structure_result = $xml_parser->parse_structure($file_path, $import['product_wrapper'] ?: 'product', 1, 1);
                
                if (!empty($structure_result['structure'])) {
                    // Extract field paths from structure (filter out object/array types, only keep text fields)
                    foreach ($structure_result['structure'] as $field) {
                        if ($field['type'] !== 'object' && $field['type'] !== 'array') {
                            $file_fields[] = $field['path'];
                        }
                    }
                } else {
                }
            }
        } else {
        }
        
        // WooCommerce fields structure
        $woocommerce_fields = array(
            'basic' => array(
                'title' => __('Basic Product Fields', 'bootflow-product-xml-csv-importer'),
                'fields' => array(
                    'sku' => array('label' => __('Product Code (SKU)', 'bootflow-product-xml-csv-importer'), 'required' => true),
                    'name' => array('label' => __('Product Name', 'bootflow-product-xml-csv-importer'), 'required' => false),
                    'description' => array('label' => __('Description', 'bootflow-product-xml-csv-importer'), 'required' => false),
                    'short_description' => array('label' => __('Short Description', 'bootflow-product-xml-csv-importer'), 'required' => false),
                    'status' => array('label' => __('Product Status', 'bootflow-product-xml-csv-importer'), 'required' => false),
                )
            ),
            'pricing' => array(
                'title' => __('Pricing Fields', 'bootflow-product-xml-csv-importer'),
                'fields' => array(
                    'regular_price' => array('label' => __('Regular Price', 'bootflow-product-xml-csv-importer'), 'required' => false),
                    'sale_price' => array('label' => __('Sale Price', 'bootflow-product-xml-csv-importer'), 'required' => false),
                    'tax_status' => array('label' => __('Tax Status', 'bootflow-product-xml-csv-importer'), 'required' => false),
                    'tax_class' => array('label' => __('Tax Class', 'bootflow-product-xml-csv-importer'), 'required' => false),
                )
            ),
            'inventory' => array(
                'title' => __('Inventory Fields', 'bootflow-product-xml-csv-importer'),
                'fields' => array(
                    'manage_stock' => array('label' => __('Manage Stock', 'bootflow-product-xml-csv-importer'), 'required' => false),
                    'stock_quantity' => array('label' => __('Stock Quantity', 'bootflow-product-xml-csv-importer'), 'required' => false),
                    'stock_status' => array('label' => __('Stock Status', 'bootflow-product-xml-csv-importer'), 'required' => false),
                    'backorders' => array('label' => __('Allow Backorders', 'bootflow-product-xml-csv-importer'), 'required' => false),
                )
            ),
            'physical' => array(
                'title' => __('Physical Properties', 'bootflow-product-xml-csv-importer'),
                'fields' => array(
                    'weight' => array('label' => __('Weight', 'bootflow-product-xml-csv-importer'), 'required' => false),
                    'length' => array('label' => __('Length', 'bootflow-product-xml-csv-importer'), 'required' => false),
                    'width' => array('label' => __('Width', 'bootflow-product-xml-csv-importer'), 'required' => false),
                    'height' => array('label' => __('Height', 'bootflow-product-xml-csv-importer'), 'required' => false),
                )
            ),
            'pricing_engine' => array(
                'title' => __('Price Markup', 'bootflow-product-xml-csv-importer'),
                'fields' => array()
            ),
            'shipping_class_engine' => array(
                'title' => __('Shipping Class Rules', 'bootflow-product-xml-csv-importer'),
                'fields' => array()
            ),
            'media' => array(
                'title' => __('Media Fields', 'bootflow-product-xml-csv-importer'),
                'fields' => array(
                    'images' => array(
                        'label' => __('Product Images', 'bootflow-product-xml-csv-importer'), 
                        'required' => false,
                        'type' => 'textarea',
                        'description' => __('Enter image URLs or use placeholders: {image} = first image, {image[1]} = first, {image[2]} = second, {image*} = all images. Separate multiple values with commas.', 'bootflow-product-xml-csv-importer')
                    ),
                    'featured_image' => array('label' => __('Featured Image', 'bootflow-product-xml-csv-importer'), 'required' => false),
                )
            ),
            'taxonomy' => array(
                'title' => __('Categories & Tags', 'bootflow-product-xml-csv-importer'),
                'fields' => array(
                    'categories' => array('label' => __('Product Categories', 'bootflow-product-xml-csv-importer'), 'required' => false),
                    'tags' => array('label' => __('Product Tags', 'bootflow-product-xml-csv-importer'), 'required' => false),
                    'brand' => array('label' => __('Brand', 'bootflow-product-xml-csv-importer'), 'required' => false),
                )
            ),
            'seo' => array(
                'title' => __('SEO Fields', 'bootflow-product-xml-csv-importer'),
                'fields' => array(
                    'meta_title' => array('label' => __('Meta Title', 'bootflow-product-xml-csv-importer'), 'required' => false),
                    'meta_description' => array('label' => __('Meta Description', 'bootflow-product-xml-csv-importer'), 'required' => false),
                    'meta_keywords' => array('label' => __('Meta Keywords', 'bootflow-product-xml-csv-importer'), 'required' => false),
                )
            )
        );
        
        $settings = get_option('bfpi_settings', array());
        $ai_providers = array('openai' => 'OpenAI GPT', 'gemini' => 'Google Gemini', 'claude' => 'Anthropic Claude', 'grok' => 'xAI Grok', 'copilot' => 'Microsoft Copilot');
        
        // Output HTML
        include_once BFPI_PLUGIN_DIR . 'includes/admin/partials/import-edit.php';
    }

    /**
     * Display resume dialog for partially completed imports.
     *
     * @since    1.0.0
     * @param    array $import Import data
     */
    private function display_resume_dialog($import) {
        $percentage = round(($import['processed_products'] / $import['total_products']) * 100, 1);
        $remaining = $import['total_products'] - $import['processed_products'];
        
        echo '<div class="wrap bfpi-import-wrap">';
        echo '<h1>' . esc_html__('Resume Import?', 'bootflow-product-xml-csv-importer') . '</h1>';
        
        echo '<div class="card" style="max-width: 600px; padding: 20px; margin: 20px 0;">';
        echo '<h2 style="margin-top: 0;">' . esc_html($import['name']) . '</h2>';
        
        echo '<div class="import-progress-summary" style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">';
        echo '<p style="font-size: 16px; margin: 0 0 10px 0;">';
        echo '<strong>' . esc_html__('Current Progress:', 'bootflow-product-xml-csv-importer') . '</strong> ';
        echo '<span style="color: #0073aa; font-size: 20px;">' . esc_html($percentage) . '%</span>';
        echo '</p>';
        // translators: %1$d is processed products count, %2$d is total products count
        echo '<p style="margin: 5px 0;">' . sprintf(esc_html__('%1$d of %2$d products processed', 'bootflow-product-xml-csv-importer'), 
            intval($import['processed_products']), intval($import['total_products'])) . '</p>';
        // translators: %d is remaining products count
        echo '<p style="margin: 5px 0; color: #666;">' . sprintf(esc_html__('%d products remaining', 'bootflow-product-xml-csv-importer'), intval($remaining)) . '</p>';
        echo '</div>';
        
        echo '<p style="font-size: 14px; color: #555;">' . esc_html__('This import was previously started. Would you like to:', 'bootflow-product-xml-csv-importer') . '</p>';
        
        echo '<div class="resume-actions" style="display: flex; gap: 15px; margin-top: 20px;">';
        
        // Resume button
        $resume_url = admin_url('admin.php?page=bfpi-import&action=rerun&import_id=' . $import['id'] . '&resume_action=resume');
        echo '<a href="' . esc_url($resume_url) . '" class="button button-primary button-hero" style="display: flex; align-items: center; gap: 8px;">';
        echo '<span class="dashicons dashicons-controls-play" style="margin-top: 5px;"></span>';
        echo '<span>';
        echo '<strong>' . esc_html__('Continue Import', 'bootflow-product-xml-csv-importer') . '</strong><br>';
        // translators: %d is the product number to resume from
        echo '<small style="font-weight: normal;">' . sprintf(esc_html__('Resume from product %d', 'bootflow-product-xml-csv-importer'), intval($import['processed_products']) + 1) . '</small>';
        echo '</span>';
        echo '</a>';
        
        // Start Over button
        $restart_url = admin_url('admin.php?page=bfpi-import&action=rerun&import_id=' . $import['id'] . '&resume_action=restart');
        echo '<a href="' . esc_url($restart_url) . '" class="button button-secondary button-hero" style="display: flex; align-items: center; gap: 8px;" onclick="return confirm(\'' . esc_js(__('Are you sure? This will reset progress and start from the beginning.', 'bootflow-product-xml-csv-importer')) . '\')">';
        echo '<span class="dashicons dashicons-update" style="margin-top: 5px;"></span>';
        echo '<span>';
        echo '<strong>' . esc_html__('Start Over', 'bootflow-product-xml-csv-importer') . '</strong><br>';
        echo '<small style="font-weight: normal;">' . esc_html__('Reset and import all products', 'bootflow-product-xml-csv-importer') . '</small>';
        echo '</span>';
        echo '</a>';
        
        echo '</div>';
        
        // Cancel link
        echo '<p style="margin-top: 20px;">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=bfpi-import-history')) . '">' . esc_html__('← Back to Import History', 'bootflow-product-xml-csv-importer') . '</a>';
        echo '</p>';
        
        echo '</div>'; // .card
        echo '</div>'; // .wrap
    }

    /**
     * Re-run an existing import.
     *
     * @since    1.0.0
     * @param    int $import_id Import ID to re-run
     * @param    bool $resume Whether to resume from current position (true) or restart (false)
     */
    private function rerun_import($import_id, $resume = false) {
        global $wpdb;
        
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/bootflow-product-importer/logs/import_debug.log';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query
        $import = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bfpi_imports WHERE id = %d", $import_id), ARRAY_A);
        
        if (!$import) {
            wp_die(esc_html__('Import not found.', 'bootflow-product-xml-csv-importer'));
        }
        
        // CRITICAL: Clear any kill flag transients that might have been set by Stop action
        delete_transient('bfpi_import_killed_' . $import_id);
        delete_transient('bfpi_import_killed_global');
        
        // Clear transient locks from previous run
        delete_transient('bfpi_import_lock_' . $import_id);
        delete_transient('bfpi_import_lock_time_' . $import_id);
        
        // Prepare update data
        $update_data = array(
            'status' => 'pending'  // Use pending - kickstart will set to processing
        );
        $update_formats = array('%s');
        
        // Only reset processed count if NOT resuming
        if (!$resume) {
            $update_data['processed_products'] = 0;
            $update_formats[] = '%d';
        } else {
        }
        
        // Update import status
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table update
        $result = $wpdb->update(
            $wpdb->prefix . 'bfpi_imports',
            $update_data,
            array('id' => $import_id),
            $update_formats,
            array('%d')
        );
        
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log("Status reset result: " . ($result !== false ? "SUCCESS" : "FAILED")); }
        
        // DON'T trigger import here - let progress page kickstart handle it
        // This prevents double processing when both this function and kickstart run
        
        // Just redirect to progress page - kickstart will start the import
        $redirect_url = admin_url('admin.php?page=bfpi-import&step=3&import_id=' . $import_id);
        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Normalize PHP formula to fix common user mistakes.
     * Makes formulas more forgiving and user-friendly.
     *
     * @since    1.0.0
     * @param    string $formula Raw formula from user
     * @return   string Normalized formula ready for execution
     */
    private function normalize_php_formula($formula) {
        $formula = trim($formula);
        
        // Handle simple expressions without any control structures
        $has_control = preg_match('/\b(if|else|elseif|switch|for|foreach|while|do)\b/i', $formula);
        
        if (!$has_control) {
            // Simple expression - just add return
            $formula = rtrim($formula, ';');
            return 'return ' . $formula . ';';
        }
        
        // For complex formulas with control structures, keep the original formatting
        // Only do minimal normalization to preserve multi-line code blocks
        
        // If formula already ends with return statement, use as-is
        if (preg_match('/return\s+[^;]+;\s*$/i', $formula)) {
            return $formula;
        }
        
        // If formula has else block covering all cases, use as-is
        if (stripos($formula, 'else {') !== false || stripos($formula, 'else{') !== false) {
            return $formula;
        }
        
        // Pattern: condition ? true : false (ternary without return)
        if (preg_match('/^\$?\w+.*\?.*:.*$/i', $formula) && stripos($formula, 'return') === false) {
            $formula = rtrim($formula, ';');
            return 'return ' . $formula . ';';
        }
        
        // For simple single-line if without braces, normalize
        $single_line = preg_replace('/\s+/', ' ', $formula);
        if (preg_match('/^if\s*\((.+?)\)\s*return\s+(.+?)(?:;?\s*)?$/i', $single_line, $matches)) {
            $condition = trim($matches[1]);
            $return_value = rtrim(trim($matches[2]), ';');
            return "if ({$condition}) { return {$return_value}; } return \$value;";
        }
        
        return $formula;
    }

    /**
     * Detect file type from URL path patterns.
     *
     * @since    1.0.0
     * @param    string $url The URL to analyze
     * @return   string 'xml', 'csv', or empty string if unknown
     */
    private function detect_file_type_from_url($url) {
        // First check file extension
        $path = wp_parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        if (in_array($extension, array('xml', 'csv'))) {
            return $extension;
        }
        
        // Check URL path patterns (e.g., /xml/ or /csv/)
        $url_lower = strtolower($url);
        
        if (strpos($url_lower, '/xml/') !== false || strpos($url_lower, '/xml?') !== false) {
            return 'xml';
        }
        
        if (strpos($url_lower, '/csv/') !== false || strpos($url_lower, '/csv?') !== false) {
            return 'csv';
        }
        
        // Check query parameters
        $query = wp_parse_url($url, PHP_URL_QUERY);
        if ($query) {
            parse_str($query, $params);
            foreach ($params as $key => $value) {
                $key_lower = strtolower($key);
                $value_lower = strtolower($value);
                
                if (in_array($key_lower, array('format', 'type', 'output', 'export'))) {
                    if (in_array($value_lower, array('xml', 'csv'))) {
                        return $value_lower;
                    }
                }
            }
        }
        
        return '';
    }

    /**
     * Detect file type from file content.
     *
     * @since    1.0.0
     * @param    string $file_path Path to the file
     * @return   string 'xml' or 'csv' (defaults to xml if uncertain)
     */
    private function detect_file_type_from_content($file_path) {
        if (!file_exists($file_path)) {
            return 'xml'; // Default fallback
        }
        
        // Read first 4KB of file
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Required for binary file type detection
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return 'xml';
        }
        
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
        $sample = fread($handle, 4096);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose($handle);
        
        if (empty($sample)) {
            return 'xml';
        }
        
        // Trim BOM and whitespace
        $sample = ltrim($sample, "\xEF\xBB\xBF\xFE\xFF\xFF\xFE\x00"); // UTF-8, UTF-16 BOMs
        $sample = ltrim($sample);
        
        // Check for XML declaration or root element
        if (strpos($sample, '<?xml') === 0) {
            return 'xml';
        }
        
        // Check if starts with < (likely XML element)
        if (strpos($sample, '<') === 0) {
            return 'xml';
        }
        
        // Check for common CSV patterns
        // CSV typically has commas or semicolons as delimiters
        $first_line_end = strpos($sample, "\n");
        $first_line = $first_line_end !== false ? substr($sample, 0, $first_line_end) : $sample;
        
        // Count potential delimiters in first line
        $comma_count = substr_count($first_line, ',');
        $semicolon_count = substr_count($first_line, ';');
        $tab_count = substr_count($first_line, "\t");
        
        // If we have multiple delimiters, likely CSV
        if ($comma_count >= 2 || $semicolon_count >= 2 || $tab_count >= 2) {
            // Additional check: CSV shouldn't have XML-like content
            if (strpos($sample, '</') === false && strpos($sample, '/>') === false) {
                return 'csv';
            }
        }
        
        // Check Content-Type from response headers if available in meta
        // This is a fallback for downloaded files
        
        // Default to XML if uncertain
        return 'xml';
    }

    /**
     * Handle file upload AJAX request.
     *
     * @since    1.0.0
     */
    public function handle_file_upload() {
        // Log every invocation to detect duplicates
        
        // Verify nonce - support both standard POST and FormData
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : (isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '');
        if (!wp_verify_nonce($nonce, 'bfpi_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'bootflow-product-xml-csv-importer')));
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to access this page.', 'bootflow-product-xml-csv-importer')));
            return;
        }
        
        try {
            // Debug: Log received data (remove this in production)
            
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $upload_method = sanitize_text_field(wp_unslash($_POST['upload_method'] ?? ''));
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $import_name = sanitize_text_field(wp_unslash($_POST['import_name'] ?? ''));
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $schedule_type = sanitize_text_field(wp_unslash($_POST['schedule_type'] ?? 'once'));
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $product_wrapper = sanitize_text_field(wp_unslash($_POST['product_wrapper'] ?? 'product'));
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $update_existing = isset($_POST['update_existing']) ? '1' : '0';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $skip_unchanged = isset($_POST['skip_unchanged']) ? '1' : '0';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $force_file_type = isset($_POST['force_file_type']) ? sanitize_text_field(wp_unslash($_POST['force_file_type'])) : 'auto';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $handle_missing = isset($_POST['handle_missing']) ? '1' : '0';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $missing_action = isset($_POST['missing_action']) ? sanitize_text_field(wp_unslash($_POST['missing_action'])) : 'draft';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $delete_variations = isset($_POST['delete_variations']) ? '1' : '0';
            
            // Validate required fields
            if (empty($import_name)) {
                throw new Exception(__('Import name is required.', 'bootflow-product-xml-csv-importer'));
            }
            
            if (empty($upload_method)) {
                throw new Exception(__('Upload method is required.', 'bootflow-product-xml-csv-importer'));
            }
            
            $file_path = '';
            $file_type = '';
            
            if ($upload_method === 'file' && isset($_FILES['file'])) {
                // Handle file upload - sanitize individual fields from $_FILES superglobal
                $uploaded_file = array(
                    'name'     => isset($_FILES['file']['name']) ? sanitize_file_name(wp_unslash($_FILES['file']['name'])) : '',
                    'type'     => isset($_FILES['file']['type']) ? sanitize_mime_type(wp_unslash($_FILES['file']['type'])) : '',
                    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- tmp_name is validated by wp_handle_upload() via is_uploaded_file()
                    'tmp_name' => isset($_FILES['file']['tmp_name']) ? wp_unslash($_FILES['file']['tmp_name']) : '',
                    'error'    => isset($_FILES['file']['error']) ? intval($_FILES['file']['error']) : UPLOAD_ERR_NO_FILE,
                    'size'     => isset($_FILES['file']['size']) ? intval($_FILES['file']['size']) : 0,
                );
                
                if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception(__('File upload error.', 'bootflow-product-xml-csv-importer'));
                }
                
                $file_type = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
                
                // Allow files without extension (from URLs) or with xml/csv extension
                if (!empty($file_type) && !in_array($file_type, array('xml', 'csv'))) {
                    throw new Exception(__('Invalid file type. Only XML and CSV files are allowed.', 'bootflow-product-xml-csv-importer'));
                }
                
                // Apply force file type if set
                if ($force_file_type !== 'auto') {
                    $file_type = $force_file_type;
                } elseif (empty($file_type)) {
                    // Auto-detect from content if no extension
                    $file_type = $this->detect_file_type_from_content($uploaded_file['tmp_name']);
                }
                
                $upload_dir = wp_upload_dir();
                $basedir = $upload_dir['basedir'];
                $plugin_upload_dir = $basedir . '/bootflow-product-importer/';
                
                // Create directory if it doesn't exist
                if (!is_dir($plugin_upload_dir)) {
                    wp_mkdir_p($plugin_upload_dir);
                }
                
                // Use wp_handle_upload for secure file handling
                // test_type => false: skip WP MIME check — extension already validated above
                $upload_overrides = array(
                    'test_form' => false,
                    'test_type' => false,
                    'unique_filename_callback' => function( $dir, $name, $ext ) {
                        return time() . '_' . sanitize_file_name( $name );
                    },
                );
                // Override upload dir to our custom directory
                add_filter( 'upload_dir', function( $dirs ) use ( $plugin_upload_dir ) {
                    $dirs['path']    = rtrim( $plugin_upload_dir, '/' );
                    $dirs['url']     = '';
                    $dirs['subdir']  = '';
                    $dirs['basedir'] = rtrim( $plugin_upload_dir, '/' );
                    $dirs['baseurl'] = '';
                    return $dirs;
                });
                $uploaded = wp_handle_upload( $uploaded_file, $upload_overrides );
                remove_all_filters( 'upload_dir' );
                
                if ( isset( $uploaded['error'] ) ) {
                    throw new Exception( esc_html( $uploaded['error'] ) );
                }
                $file_path = $uploaded['file'];
                
            } elseif ($upload_method === 'url') {
                throw new Exception(__('URL import is not supported.', 'bootflow-product-xml-csv-importer'));
            } else {
                throw new Exception(__('No file provided.', 'bootflow-product-xml-csv-importer'));
            }
            
            // Validate file exists
            if (!file_exists($file_path)) {
                throw new Exception(__('File upload failed - file does not exist.', 'bootflow-product-xml-csv-importer'));
            }
            
            // Validate file size
            $file_size = filesize($file_path);
            if ($file_size === 0) {
                wp_delete_file($file_path);
                throw new Exception(__('File is empty.', 'bootflow-product-xml-csv-importer'));
            }
            
            // Load parser classes if not already loaded
            if ($file_type === 'xml') {
                if (!class_exists('Bfpi_XML_Parser')) {
                    require_once BFPI_PLUGIN_DIR . 'includes/class-bfpi-xml-parser.php';
                }
                $parser = new Bfpi_XML_Parser();
                $validation = $parser->validate_xml_file($file_path);
            } else {
                if (!class_exists('Bfpi_CSV_Parser')) {
                    require_once BFPI_PLUGIN_DIR . 'includes/class-bfpi-csv-parser.php';
                }
                $parser = new Bfpi_CSV_Parser();
                $validation = $parser->validate_csv_file($file_path);
            }
            
            if (!$validation['valid']) {
                if (file_exists($file_path)) {
                    wp_delete_file($file_path);
                }
                throw new Exception($validation['message']);
            }
            
            // Count products before redirect
            $total_products = 0;
            if ($file_type === 'xml') {
                $count_result = $parser->count_products_and_extract_structure($file_path, $product_wrapper);
                if ($count_result['success']) {
                    $total_products = $count_result['total_products'];
                }
            } else {
                $count_result = $parser->count_rows_and_extract_structure($file_path);
                if ($count_result['success']) {
                    $total_products = $count_result['total_rows'];
                }
            }
            
            // Store total products in a transient (avoid PHP sessions)
            set_transient( 'bfpi_import_total_products_' . get_current_user_id(), $total_products, HOUR_IN_SECONDS );
            
            // Create import record in database
            global $wpdb;
            $table_name = $wpdb->prefix . 'bfpi_imports';
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table insert
            $wpdb->insert(
                $table_name,
                array(
                    'name' => $import_name,
                    'file_path' => $file_path,
                    'file_url' => $file_path, // Store same path for backward compatibility
                    'file_type' => $file_type,
                    'product_wrapper' => $product_wrapper,
                    'schedule_type' => $schedule_type,
                    'update_existing' => $update_existing,
                    'skip_unchanged' => $skip_unchanged,
                    'handle_missing' => $handle_missing,
                    'missing_action' => $missing_action,
                    'delete_variations' => $delete_variations,
                    'total_products' => $total_products,
                    'status' => 'pending',
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d', '%d', '%s', '%s')
            );
            
            $import_id = $wpdb->insert_id;
            
            wp_send_json_success(array(
                'message' => __('File uploaded successfully.', 'bootflow-product-xml-csv-importer'),
                'total_products' => $total_products,
                'import_id' => $import_id,
                'redirect_url' => admin_url('admin.php?page=bfpi-import&step=2&import_id=' . $import_id)
            ));
            
        } catch (Exception $e) {
            // Debug: Log the error (remove this in production)
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Bootflow Import - Upload error: ' . $e->getMessage()); }
            
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Handle parse structure AJAX request.
     *
     * @since    1.0.0
     */
    public function handle_parse_structure() {
        // Verify nonce
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'bfpi_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'bootflow-product-xml-csv-importer')));
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to access this page.', 'bootflow-product-xml-csv-importer')));
            return;
        }
        
        try {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Bootflow Import - Parse structure started'); }
            
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $file_path = sanitize_text_field(wp_unslash($_POST['file_path']));
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $file_type = sanitize_text_field(wp_unslash($_POST['file_type']));
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $page = intval(wp_unslash($_POST['page'] ?? 1));
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $per_page = intval(wp_unslash($_POST['per_page'] ?? 5));
            
            if (defined('WP_DEBUG') && WP_DEBUG) { 
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Bootflow Import - Parse structure params: ' . wp_json_encode([
                    'file_path' => $file_path,
                    'file_type' => $file_type,
                    'page' => $page,
                    'per_page' => $per_page
                ])); 
            }
            
            // Wait for file to be fully written - retry up to 5 times
            $max_retries = 5;
            $retry_delay = 200000; // 200ms in microseconds
            $file_ready = false;
            
            for ($i = 0; $i < $max_retries; $i++) {
                if (file_exists($file_path)) {
                    $file_size = filesize($file_path);
                    if ($file_size > 0) {
                        // Try to open and read file to ensure it's not locked
                        clearstatcache(true, $file_path);
                        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Checking file lock status
                        $handle = @fopen($file_path, 'r');
                        if ($handle) {
                            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
                            fclose($handle);
                            $file_ready = true;
                            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Bootflow Import - File ready after ' . ($i + 1) . ' attempts'); }
                            break;
                        }
                    }
                }
                if ($i < $max_retries - 1) {
                    usleep($retry_delay);
                }
            }
            
            if (!$file_ready) {
                throw new Exception(__('File is not ready yet. Please refresh the page and try again.', 'bootflow-product-xml-csv-importer'));
            }
            
            if ($file_type === 'xml') {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                $product_wrapper = sanitize_text_field(wp_unslash($_POST['product_wrapper']));
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Bootflow Import - Using XML parser with wrapper: ' . $product_wrapper); }
                
                $xml_parser = new Bfpi_XML_Parser();
                $result = $xml_parser->parse_structure($file_path, $product_wrapper, $page, $per_page);
            } else {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Bootflow Import - Using CSV parser'); }
                $csv_parser = new Bfpi_CSV_Parser();
                $result = $csv_parser->parse_structure($file_path, $page, $per_page);
            }
            
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Bootflow Import - Parse structure completed successfully'); }
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Bootflow Import - Parse structure error: ' . $e->getMessage()); }
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Bootflow Import - Parse structure trace: ' . $e->getTraceAsString()); }
            
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Handle start import AJAX request.
     *
     * @since    1.0.0
     */
    public function handle_start_import() {
        
        // Verify nonce - support both standard POST and FormData
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : (isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '');
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Nonce check: ' . ($nonce ? 'exists' : 'missing')); }
        
        if (!wp_verify_nonce($nonce, 'bfpi_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'bootflow-product-xml-csv-importer')));
            return;
        }
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Nonce verified successfully'); }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to access this page.', 'bootflow-product-xml-csv-importer')));
            return;
        }
        
        try {
            // Debug: Log received data (remove this in production)
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Bootflow Import - Start import data received'); }
            
            // Decode JSON strings from FormData
            $field_mapping = array();
            $custom_fields = array();
            $import_filters = array();
            
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            if (isset($_POST['field_mapping_json'])) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                $field_mapping = json_decode(wp_unslash($_POST['field_mapping_json']), true);
                if (is_array($field_mapping)) {
                    $field_mapping = map_deep($field_mapping, 'sanitize_text_field');
                }

            }
            
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            if (isset($_POST['custom_fields_json'])) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                $custom_fields = json_decode(wp_unslash($_POST['custom_fields_json']), true);
                if (is_array($custom_fields)) {
                    $custom_fields = map_deep($custom_fields, 'sanitize_text_field');
                }

            }
            
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            if (isset($_POST['import_filters_json'])) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                $import_filters = json_decode(wp_unslash($_POST['import_filters_json']), true);
                if (is_array($import_filters)) {
                    $import_filters = map_deep($import_filters, 'sanitize_text_field');
                }

            }
            
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('After decode - field_mapping count: ' . count($field_mapping)); }

            
            // Check if this is updating existing import
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $import_id = isset($_POST['import_id']) ? intval(wp_unslash($_POST['import_id'])) : 0;
            
            // Collect import data from form fields
            $import_data = array(
                'import_id' => $import_id,  // Pass import_id to importer
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                'import_name' => sanitize_text_field(wp_unslash($_POST['import_name'] ?? '')),
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                'file_path' => sanitize_text_field(wp_unslash($_POST['file_path'] ?? '')),
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                'file_type' => sanitize_text_field(wp_unslash($_POST['file_type'] ?? '')),
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                'schedule_type' => sanitize_text_field(wp_unslash($_POST['schedule_type'] ?? 'once')),
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                'product_wrapper' => sanitize_text_field(wp_unslash($_POST['product_wrapper'] ?? 'product')),
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                'update_existing' => isset($_POST['update_existing']) ? sanitize_text_field(wp_unslash($_POST['update_existing'])) : '0',
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                'skip_unchanged' => isset($_POST['skip_unchanged']) ? sanitize_text_field(wp_unslash($_POST['skip_unchanged'])) : '0',
                'field_mapping' => $field_mapping,
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                'processing_modes' => isset($_POST['processing_modes']) ? map_deep(wp_unslash($_POST['processing_modes']), 'sanitize_text_field') : array(),
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                'processing_configs' => isset($_POST['processing_configs']) ? map_deep(wp_unslash($_POST['processing_configs']), 'sanitize_text_field') : array(),
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                'ai_settings' => isset($_POST['ai_settings']) ? map_deep(wp_unslash($_POST['ai_settings']), 'sanitize_text_field') : array(),
                'custom_fields' => $custom_fields,
                'import_filters' => $import_filters,
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                'filter_logic' => sanitize_text_field(wp_unslash($_POST['filter_logic'] ?? 'AND')),
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
                'draft_non_matching' => isset($_POST['draft_non_matching']) ? 1 : 0
            );
            
            // Load importer class if not loaded
            if (!class_exists('Bfpi_Importer')) {
                require_once BFPI_PLUGIN_DIR . 'includes/class-bfpi-importer.php';
            }
            
            $importer = new Bfpi_Importer();
            $import_id = $importer->start_import($import_data);
            
            wp_send_json_success(array(
                'import_id' => $import_id,
                'message' => __('Import started successfully.', 'bootflow-product-xml-csv-importer'),
                'debug' => 'Import ID: ' . $import_id . ', File: ' . $import_data['file_path']
            ));
            
        } catch (Exception $e) {
            // Debug: Log the error (remove this in production)
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Bootflow Import - Start import error: ' . $e->getMessage()); }
            
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Handle kickstart import AJAX request.
     * Triggers import processing for stuck imports at 0%.
     *
     * @since    1.0.0
     */
    public function handle_kickstart_import() {
        // Verify nonce
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'bfpi_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'bootflow-product-xml-csv-importer')));
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to access this page.', 'bootflow-product-xml-csv-importer')));
            return;
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        $import_id = intval(wp_unslash($_POST['import_id']));
        
        
        try {
            global $wpdb;
            
            // Check if import is already in progress or completed
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query
            $import = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bfpi_imports WHERE id = %d",
                $import_id
            ));
            
            if (!$import) {
                wp_send_json_error(array('message' => __('Import not found.', 'bootflow-product-xml-csv-importer')));
                return;
            }
            
            // Don't kickstart if already completed
            if ($import->status === 'completed') {
                wp_send_json_success(array(
                    'message' => __('Import already completed.', 'bootflow-product-xml-csv-importer'),
                    'skipped' => true
                ));
                return;
            }
            
            // For processing status with products already done, check if actively processing
            // (Don't skip for pending status - that means we need to resume/restart)
            if ($import->status === 'processing' && intval($import->processed_products) > 0) {
                // Check if there's a lock (meaning it's actively running)
                $lock = get_transient('bfpi_import_lock_' . $import_id);
                if ($lock !== false) {
                    wp_send_json_success(array(
                        'message' => __('Import already in progress.', 'bootflow-product-xml-csv-importer'),
                        'skipped' => true
                    ));
                    return;
                }
            }
            
            // Set status to processing before triggering
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table update
            $wpdb->update(
                $wpdb->prefix . 'bfpi_imports',
                array('status' => 'processing'),
                array('id' => $import_id),
                array('%s'),
                array('%d')
            );
            
            // Determine correct offset - for Resume, start from where we left off
            $offset = intval($import->processed_products);
            $batch_size = intval($import->batch_size) ?: 5;
            
            // Trigger import chunk processing directly with correct offset
            do_action('bfpi_process_chunk', $import_id, $offset, $batch_size);
            
            
            wp_send_json_success(array(
                'message' => __('Import processing started.', 'bootflow-product-xml-csv-importer'),
                'offset' => $offset
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Handle cron ping AJAX request - triggers WP-Cron to run.
     * 
     * This is called every 2 seconds from progress page to ensure
     * WP-Cron continues processing import chunks.
     * Also checks for stuck imports and reschedules them.
     *
     * @since    1.0.0
     */
    public function handle_ping_cron() {
        // Security: verify nonce and capability
        check_ajax_referer( 'bfpi_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        $import_id = isset($_POST['import_id']) ? intval(wp_unslash($_POST['import_id'])) : 0;
        
        // Trigger WP-Cron
        spawn_cron();
        
        // Check if import is stuck (has processing status but no scheduled cron event)
        if ($import_id > 0) {
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query
            $import = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bfpi_imports WHERE id = %d",
                $import_id
            ));
            
            if ($import && $import->status === 'processing') {
                // Check if there's a scheduled cron event for this import
                $has_scheduled_event = false;
                $cron_array = _get_cron_array();
                if (is_array($cron_array)) {
                    foreach ($cron_array as $timestamp => $crons) {
                        if (isset($crons['bfpi_process_chunk'])) {
                            foreach ($crons['bfpi_process_chunk'] as $key => $event) {
                                if (isset($event['args'][0]) && $event['args'][0] == $import_id) {
                                    $has_scheduled_event = true;
                                    break 2;
                                }
                            }
                        }
                    }
                }
                
                // Check for stale lock (lock exists but is older than 3 minutes)
                $lock_key = 'bfpi_import_lock_' . $import_id;
                $lock_time_key = 'bfpi_import_lock_time_' . $import_id;
                $lock_exists = get_transient($lock_key) !== false;
                $lock_time = get_transient($lock_time_key);
                $lock_age = $lock_time ? (time() - intval($lock_time)) : 999;
                $lock_is_stale = $lock_exists && $lock_age > 180;
                
                // If no scheduled event and no active lock (or stale lock), reschedule
                if (!$has_scheduled_event && (!$lock_exists || $lock_is_stale)) {
                    // Clear stale lock if exists
                    if ($lock_is_stale) {
                        delete_transient($lock_key);
                        delete_transient($lock_time_key);
                    }
                    
                    // Calculate next offset based on already processed products
                    $processed = intval($import->processed_products);
                    $chunk_size = 5; // Match the chunk size used elsewhere
                    
                    // Schedule next chunk
                    wp_schedule_single_event(time(), 'bfpi_process_chunk', array($import_id, $processed, $chunk_size));
                    
                }
            }
        }
        
        // Return minimal response
        wp_send_json_success(array('pinged' => true));
    }

    /**
     * Handle test URL AJAX request.
     * Tests if a URL is accessible via wp_safe_remote_get
     * WP.org compliance: SSRF protection and proper validation
     *
     * @since    1.0.0
     */
    public function handle_test_url() {
        wp_send_json_error(array('message' => __('URL testing is not supported.', 'bootflow-product-xml-csv-importer')));
    }

    /**
     * Handle get progress AJAX request.
     *
     * @since    1.0.0
     */
    public function handle_get_progress() {
        // Verify nonce
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'bfpi_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'bootflow-product-xml-csv-importer')));
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to access this page.', 'bootflow-product-xml-csv-importer')));
            return;
        }
        
        global $wpdb;
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        $import_id = intval(wp_unslash($_POST['import_id']));
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query
        $import = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}bfpi_imports WHERE id = %d", $import_id),
            ARRAY_A
        );
        
        if (!$import) {
            wp_send_json_error(array('message' => __('Import not found.', 'bootflow-product-xml-csv-importer')));
        }
        
        // Get only the 50 most recent logs, ordered by ID (more reliable than timestamp)
        // Exclude progress logs - only product-related logs
        $chunk_pattern = $wpdb->esc_like('Chunk ') . '%';
        $processing_pattern = $wpdb->esc_like('Processing chunk ') . '%';
        $processed_pattern = $wpdb->esc_like('Processed ') . '%/%';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bfpi_import_logs 
                 WHERE import_id = %d 
                 AND message NOT LIKE %s 
                 AND message NOT LIKE %s
                 AND message NOT LIKE %s
                 ORDER BY id DESC LIMIT 50",
                $import_id,
                $chunk_pattern,
                $processing_pattern,
                $processed_pattern
            ),
            ARRAY_A
        );
        
        $percentage = $import['total_products'] > 0 ? round(($import['processed_products'] / $import['total_products']) * 100) : 0;
        
        // Calculate current chunk and total chunks
        $batch_size = intval($import['batch_size'] ?? 50);
        $total_chunks = $import['total_products'] > 0 ? ceil($import['total_products'] / $batch_size) : 1;
        $current_chunk = $import['processed_products'] > 0 ? ceil($import['processed_products'] / $batch_size) : 1;
        
        // Get import start time
        $start_time = strtotime($import['created_at']);
        
        wp_send_json_success(array(
            'status' => $import['status'],
            'total_products' => $import['total_products'],
            'processed_products' => $import['processed_products'],
            'percentage' => $percentage,
            'start_time' => $start_time,
            'current_chunk' => $current_chunk,
            'total_chunks' => $total_chunks,
            'logs' => $logs
        ));
    }

    /**
     * Handle save mapping AJAX request.
     *
     * @since    1.0.0
     */
    public function handle_save_mapping() {
        global $wpdb;
        
        // DEBUG: Log that handler was called
        
        // Verify nonce
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'bfpi_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'bootflow-product-xml-csv-importer')));
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to access this page.', 'bootflow-product-xml-csv-importer')));
            return;
        }
        
        try {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $import_id = intval(wp_unslash($_POST['import_id']));
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $mapping_data_json = wp_unslash( $_POST['mapping_data'] ?? '' );
            $mapping_data = json_decode( $mapping_data_json, true );
            
            if (!$import_id || !$mapping_data) {
                wp_send_json_error(array('message' => __('Invalid import ID or mapping data.', 'bootflow-product-xml-csv-importer')));
                return;
            }
            
            // Sanitize mapping data recursively
            $mapping_data = map_deep( $mapping_data, 'sanitize_text_field' );
            
            // Prepare field mappings for database
            $field_mappings = array();
            
            // Process standard field mappings
            if (isset($mapping_data['field_mapping'])) {
                $field_mappings = $mapping_data['field_mapping'];
            }
            
            // Process custom fields - save as array, not individual fields
            if (isset($mapping_data['custom_fields']) && is_array($mapping_data['custom_fields'])) {
                // Add each custom field with a unique key (use _custom_ prefix for consistency)
                $cf_index = 0;
                foreach ($mapping_data['custom_fields'] as $field_config) {
                    if (is_array($field_config) && !empty($field_config['name'])) {
                        $field_mappings['_custom_' . $cf_index] = $field_config;
                        $cf_index++;
                    }
                }
            }
            
            // Also save custom_fields in separate column for backward compatibility
            $custom_fields_array = isset($mapping_data['custom_fields']) ? $mapping_data['custom_fields'] : array();
            
            // Prepare update data
            $update_data = array(
                'field_mappings' => wp_json_encode($field_mappings, JSON_UNESCAPED_UNICODE),
                'custom_fields' => wp_json_encode($custom_fields_array, JSON_UNESCAPED_UNICODE)
            );
            
            // Add filters if present
            if (isset($mapping_data['import_filters'])) {
                $update_data['import_filters'] = wp_json_encode($mapping_data['import_filters'], JSON_UNESCAPED_UNICODE);
            }
            
            // Add filter logic if present
            if (isset($mapping_data['filter_logic'])) {
                $update_data['filter_logic'] = sanitize_text_field($mapping_data['filter_logic']);
            }
            
            // Add draft_non_matching if present
            if (isset($mapping_data['draft_non_matching'])) {
                $update_data['draft_non_matching'] = intval($mapping_data['draft_non_matching']);
            }
            
            // Add update_existing if present (CRITICAL FIX)
            if (isset($mapping_data['update_existing'])) {
                $update_data['update_existing'] = $mapping_data['update_existing'] === '1' ? '1' : '0';
            }
            
            // Add skip_unchanged if present (CRITICAL FIX)
            if (isset($mapping_data['skip_unchanged'])) {
                $update_data['skip_unchanged'] = $mapping_data['skip_unchanged'] === '1' ? '1' : '0';
            }
            
            // Add batch_size if present (CRITICAL FIX)
            if (isset($mapping_data['batch_size'])) {
                $update_data['batch_size'] = intval($mapping_data['batch_size']);
            }
            
            // Add schedule_type if present (for scheduled imports)
            if (isset($mapping_data['schedule_type'])) {
                $valid_schedules = array('none', 'disabled', 'bfpi_15min', 'hourly', 'bfpi_6hours', 'daily', 'weekly', 'monthly');
                $schedule = sanitize_text_field($mapping_data['schedule_type']);
                if (in_array($schedule, $valid_schedules)) {
                    $update_data['schedule_type'] = $schedule;
                }
            }
            
            // Add schedule_method if present (action_scheduler or server_cron)
            if (isset($mapping_data['schedule_method'])) {
                $valid_methods = array('action_scheduler', 'server_cron');
                $method = sanitize_text_field($mapping_data['schedule_method']);
                if (in_array($method, $valid_methods)) {
                    $update_data['schedule_method'] = $method;
                }
            }
            
            // Update database
            $table_name = $wpdb->prefix . 'bfpi_imports';
            
            // Build format specifiers dynamically based on update_data keys
            $format_map = array(
                'field_mappings' => '%s',
                'import_filters' => '%s',
                'filter_logic' => '%s',
                'draft_non_matching' => '%d',
                'update_existing' => '%s',
                'skip_unchanged' => '%s',
                'batch_size' => '%d',
                'schedule_type' => '%s',
                'schedule_method' => '%s'
            );
            $formats = array();
            foreach (array_keys($update_data) as $key) {
                $formats[] = isset($format_map[$key]) ? $format_map[$key] : '%s';
            }
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table update
            $result = $wpdb->update(
                $table_name,
                $update_data,
                array('id' => $import_id),
                $formats,
                array('%d')
            );
            
            if ($result === false) {
                wp_send_json_error(array('message' => __('Database error: ', 'bootflow-product-xml-csv-importer') . $wpdb->last_error));
                return;
            }
            
            wp_send_json_success(array(
                'message' => __('Mapping configuration saved successfully.', 'bootflow-product-xml-csv-importer'),
                'updated_fields' => count($field_mappings)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Handle test shipping formula AJAX request.
     *
     * @since    1.0.0
     */
    public function handle_test_shipping() {
        wp_send_json_error(array('message' => __('Shipping formula testing is not supported.', 'bootflow-product-xml-csv-importer')));
    }

    /**
     * Handle cron import execution.
     *
     * @since    1.0.0
     */
    public function handle_cron_import() {
        return; // Scheduled imports not supported.
    }

    /**
     * Handle single import cron execution.
     */
    public function handle_single_import_cron() {
        return; // Scheduled imports not supported.
    }

    /**
     * Handle AJAX control import (pause/resume/stop/retry).
     */
    public function ajax_control_import() {
        global $wpdb;
        
        check_ajax_referer('bfpi_nonce', 'nonce');
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error( 'Insufficient permissions.' ); }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        $import_id = intval(wp_unslash($_POST['import_id'] ?? 0));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        $action = sanitize_text_field(wp_unslash($_POST['control_action'] ?? ''));
        
        if (!$import_id || !$action) {
            wp_send_json_error('Missing parameters');
        }
        
        $table = $wpdb->prefix . 'bfpi_imports';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query
        $import = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $import_id));
        
        if (!$import) {
            wp_send_json_error('Import not found');
        }
        
        switch ($action) {
            case 'pause':
                $wpdb->update($table, array('status' => 'paused'), array('id' => $import_id), array('%s'), array('%d')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
                
                // Clear cron jobs
                $hooks = array('bfpi_process_chunk', 'bfpi_retry_chunk', 'bfpi_single_chunk');
                foreach ($hooks as $hook) {
                    wp_clear_scheduled_hook($hook, array($import_id));
                }
                
                // Clear transient locks to stop running batch immediately
                delete_transient('bfpi_import_lock_' . $import_id);
                delete_transient('bfpi_import_lock_time_' . $import_id);
                
                wp_send_json_success(array('status' => 'paused', 'message' => 'Import paused'));
                break;
                
            case 'resume':
                $wpdb->update($table, array('status' => 'processing'), array('id' => $import_id), array('%s'), array('%d')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
                
                // Schedule next chunk to start processing with correct parameters
                $current_offset = intval($import->processed_products);
                $batch_size = intval($import->batch_size) ?: 10;
                wp_schedule_single_event(time() + 2, 'bfpi_process_chunk', array($import_id, $current_offset, $batch_size));
                
                wp_send_json_success(array('status' => 'processing', 'message' => 'Import resumed'));
                break;
                
            case 'stop':
                $wpdb->update($table, array('status' => 'failed'), array('id' => $import_id), array('%s'), array('%d')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
                
                // Clear ALL cron jobs
                $hooks = array('bfpi_process_chunk', 'bfpi_retry_chunk', 'bfpi_single_chunk');
                foreach ($hooks as $hook) {
                    wp_clear_scheduled_hook($hook, array($import_id));
                    wp_clear_scheduled_hook($hook);
                }
                
                // Clear transient locks to stop running batch immediately
                delete_transient('bfpi_import_lock_' . $import_id);
                delete_transient('bfpi_import_lock_time_' . $import_id);
                
                wp_send_json_success(array('status' => 'failed', 'message' => 'Import stopped'));
                break;
                
            case 'retry':
                $wpdb->update($table, array('status' => 'processing'), array('id' => $import_id), array('%s'), array('%d')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
                wp_send_json_success(array('status' => 'processing', 'message' => 'Import retrying'));
                break;
                
            default:
                wp_send_json_error('Invalid action');
        }
    }

    /**
     * Handle async batch processing (for re-run button).
     */
    public function handle_process_batch() {
        global $wpdb;
        
        // Security: verify nonce and capability
        check_ajax_referer('bfpi_nonce', 'nonce');
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        $import_id = intval(wp_unslash($_POST['import_id'] ?? 0));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        $offset = intval(wp_unslash($_POST['offset'] ?? 0));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        $limit = intval(wp_unslash($_POST['limit'] ?? 50));
        
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Bootflow Import: import_id=' . $import_id . ', offset=' . $offset . ', limit=' . $limit); }
        
        if (empty($import_id)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Bootflow Import: Missing import_id in process_batch'); }
            wp_send_json_error('Missing import ID');
        }
        
        // CHECK IMPORT STATUS BEFORE PROCESSING
        $table = $wpdb->prefix . 'bfpi_imports';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query
        $import = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $import_id));
        
        if (!$import) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Bootflow Import: Import not found: ' . $import_id); }
            wp_send_json_error('Import not found');
        }
        
        if ($import->status !== 'processing') {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Bootflow Import: Import status is ' . $import->status . ' - ABORTING BATCH'); }
            wp_send_json_error('Import not in processing status: ' . $import->status);
        }
        
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Bootflow Import: Processing batch for import #' . $import_id . ', offset=' . $offset); }
        
        // DEBUG: Check if importer class exists
        if (!class_exists('Bfpi_Importer')) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Bootflow Import: Importer class does not exist, loading...'); }
            require_once BFPI_PLUGIN_DIR . 'includes/class-bfpi-importer.php';
        }
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Bootflow Import: Importer class loaded successfully'); }
        
        try {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Bootflow Import: Creating Importer instance...'); }
            $importer = new Bfpi_Importer();
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Bootflow Import: Importer instance created, calling process_import_chunk...'); }
            
            $result = $importer->process_import_chunk($offset, $limit, $import_id);
            
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Bootflow Import: Batch result - processed=' . ($result['processed'] ?? 0) . ', errors=' . count($result['errors'] ?? [])); }
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Bootflow Import: Full result: ' . wp_json_encode($result)); }
            
            wp_send_json_success($result);
        } catch (Exception $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Bootflow Import: EXCEPTION in batch processing: ' . $e->getMessage()); }
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Bootflow Import: Exception trace: ' . $e->getTraceAsString()); }
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle WP Cron chunk processing.
     * Called by wp_schedule_single_event hook.
     *
     * @since    1.0.0
     * @param    int $import_id Import ID
     * @param    int $offset Starting offset
     * @param    int $limit Chunk size
     */
    public function handle_cron_process_chunk($import_id, $offset, $limit) {
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/bootflow-product-importer/logs/import_debug.log';
        
        try {
            // Load importer class if not loaded
            if (!class_exists('Bfpi_Importer')) {
                require_once BFPI_PLUGIN_DIR . 'includes/class-bfpi-importer.php';
            }
            
            $importer = new Bfpi_Importer($import_id);
            $result = $importer->process_import_chunk($offset, $limit, $import_id);
            
            
        } catch (Exception $e) {
        }
    }


    /**
     * Handle update import URL AJAX request.
     *
     * @since    1.0.0
     */
    public function handle_update_import_url() {
        // Verify nonce
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'bfpi_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'bootflow-product-xml-csv-importer')));
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'bootflow-product-xml-csv-importer')));
            return;
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        $import_id = intval(wp_unslash($_POST['import_id'] ?? 0));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        $new_url = esc_url_raw( wp_unslash( $_POST['file_url'] ?? '' ) );
        
        if (!$import_id || !$new_url) {
            wp_send_json_error(array('message' => __('Invalid import ID or URL.', 'bootflow-product-xml-csv-importer')));
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'bfpi_imports';
        
        // Get current import to find old file
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query with safe prefix
        $import = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM `' . esc_sql( $table_name ) . '` WHERE id = %d',
                $import_id
            ),
            ARRAY_A
        );
        
        if (!$import) {
            wp_send_json_error(array('message' => __('Import not found.', 'bootflow-product-xml-csv-importer')));
            return;
        }
        
        // Delete old XML file if it exists and URL is changing
        if (!empty($import['file_url']) && $import['file_url'] !== $new_url) {
            if (file_exists($import['file_url'])) {
                wp_delete_file($import['file_url']);
            }
        }
        
        // Update URL in database
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $updated = $wpdb->update(
            $table_name,
            array('original_file_url' => $new_url),
            array('id' => $import_id),
            array('%s'),
            array('%d')
        );
        
        if ($updated === false) {
            wp_send_json_error(array('message' => __('Failed to update URL in database.', 'bootflow-product-xml-csv-importer')));
            return;
        }
        
        wp_send_json_success(array('message' => __('URL updated successfully. Next import will use the new URL.', 'bootflow-product-xml-csv-importer')));
    }

    /**
     * Download import file from URL for cron jobs.
     * WP.org compliance: URL validation and wp_safe_remote_get
     *
     * @since    1.0.0
     * @param    string $url File URL
     * @param    int $import_id Import ID
     * @return   array Result with success status and file_path or message
     */
    private function download_import_file($url, $import_id) {
        return array('success' => false, 'message' => __('URL import is not supported.', 'bootflow-product-xml-csv-importer'));
    }
    
    /**
     * AJAX handler to detect attribute values from source field.
     */
    public function ajax_detect_attribute_values() {
        check_ajax_referer('bfpi_nonce', 'nonce');
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error( 'Insufficient permissions.' ); }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        $import_id = intval(wp_unslash($_POST['import_id'] ?? 0));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        $source_field = sanitize_text_field(wp_unslash($_POST['source_field'] ?? ''));
        
        if (!$import_id || !$source_field) {
            wp_send_json_error(array('message' => 'Missing parameters'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'bfpi_imports';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query
        $import = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $import_id));
        
        if (!$import) {
            wp_send_json_error(array('message' => 'Import not found'));
        }
        
        $file_path = $import->file_path;
        if (!file_exists($file_path)) {
            wp_send_json_error(array('message' => 'Import file not found'));
        }
        
        // Parse file to extract unique values from source field
        $values = array();
        
        try {
            if ($import->file_type === 'xml') {
                $xml_parser = new Bfpi_XML_Parser();
                $products = $xml_parser->parse($file_path, $import->product_wrapper);
                
                // Extract values from source field
                foreach ($products as $product) {
                    $value = $this->get_nested_value($product, $source_field);
                    if (!empty($value)) {
                        if (is_array($value)) {
                            // If array of values (e.g., multiple attributes)
                            foreach ($value as $v) {
                                if (is_array($v) && isset($v['value'])) {
                                    $values[] = $v['value'];
                                } else {
                                    $values[] = (string)$v;
                                }
                            }
                        } else {
                            $values[] = (string)$value;
                        }
                    }
                }
            } else {
                // CSV parsing
                $csv_parser = new Bfpi_CSV_Parser();
                $products = $csv_parser->parse($file_path);
                
                foreach ($products as $product) {
                    if (isset($product[$source_field]) && !empty($product[$source_field])) {
                        // Check if comma-separated
                        if (strpos($product[$source_field], ',') !== false) {
                            $split_values = array_map('trim', explode(',', $product[$source_field]));
                            $values = array_merge($values, $split_values);
                        } else {
                            $values[] = $product[$source_field];
                        }
                    }
                }
            }
            
            // Get unique values and limit to first 10 for UI
            $values = array_unique($values);
            $values = array_filter($values); // Remove empty
            $values = array_values($values); // Re-index
            
            // Limit to 20 values max for UI performance
            if (count($values) > 20) {
                $values = array_slice($values, 0, 20);
            }
            
            if (empty($values)) {
                wp_send_json_error(array('message' => 'No values found in source field: ' . $source_field));
            }
            
            wp_send_json_success(array('values' => $values));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Error parsing file: ' . $e->getMessage()));
        }
    }
    
    /**
     * Get nested value from array using dot notation.
     */
    private function get_nested_value($array, $path) {
        $keys = explode('.', $path);
        $value = $array;
        
        foreach ($keys as $key) {
            // Handle array notation like [0]
            if (preg_match('/(.+)\[(\d+)\]/', $key, $matches)) {
                $key = $matches[1];
                $index = intval($matches[2]);
                
                if (isset($value[$key]) && is_array($value[$key]) && isset($value[$key][$index])) {
                    $value = $value[$key][$index];
                } else {
                    return null;
                }
            } elseif (isset($value[$key])) {
                $value = $value[$key];
            } else {
                return null;
            }
        }
        
        return $value;
    }

    /**
     * AJAX: Save mapping recipe
     */
    public function ajax_save_recipe() {
        wp_send_json_error(array('message' => __('Recipes are not supported.', 'bootflow-product-xml-csv-importer')));
    }

    /**
     * AJAX: Load recipe
     */
    public function ajax_load_recipe() {
        wp_send_json_error(array('message' => __('Recipes are not supported.', 'bootflow-product-xml-csv-importer')));
    }

    /**
     * AJAX: Delete recipe
     */
    public function ajax_delete_recipe() {
        wp_send_json_error(array('message' => __('Recipes are not supported.', 'bootflow-product-xml-csv-importer')));
    }

    /**
     * AJAX: Get recipes list
     */
    public function ajax_get_recipes() {
        check_ajax_referer('bfpi_nonce', 'nonce');
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error( 'Insufficient permissions.' ); }
        
        wp_send_json_success(array(
            'recipes' => $this->get_recipes_list()
        ));
    }

    /**
     * Helper: Get recipes list for dropdown
     */
    private function get_recipes_list() {
        $recipes = get_option('bfpi_recipes', array());
        $list = array();
        
        foreach ($recipes as $id => $recipe) {
            $list[] = array(
                'id' => $id,
                'name' => $recipe['name'],
                'created_at' => $recipe['created_at']
            );
        }
        
        // Sort by created_at descending (newest first)
        usort($list, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return $list;
    }

    /**
     * AJAX: Auto-detect mapping based on field name matching
     */
    public function ajax_auto_detect_mapping() {
        check_ajax_referer('bfpi_nonce', 'nonce');
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error( 'Insufficient permissions.' ); }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        $source_fields = isset($_POST['source_fields']) ? array_map('sanitize_text_field', wp_unslash($_POST['source_fields'])) : array();
        
        if (empty($source_fields)) {
            wp_send_json_error(array('message' => __('No source fields provided', 'bootflow-product-xml-csv-importer')));
        }
        
        // WooCommerce field aliases for matching
        $field_aliases = array(
            'sku' => array('sku', 'product_code', 'item_code', 'article', 'artikuls', 'product_sku', 'code', 'item_sku', 'articlecode', 'itemcode', 'productcode', 'id', 'product_id'),
            'name' => array('name', 'title', 'product_name', 'product_title', 'item_name', 'nosaukums', 'productname', 'item', 'productdescription'),
            'description' => array('description', 'desc', 'content', 'product_description', 'full_description', 'apraksts', 'long_description', 'longdescription', 'fulldescription'),
            'short_description' => array('short_description', 'short_desc', 'excerpt', 'summary', 'shortdescription', 'brief', 'intro'),
            'regular_price' => array('price', 'regular_price', 'cena', 'retail_price', 'list_price', 'msrp', 'regularprice', 'listprice', 'baseprice', 'base_price', 'unit_price'),
            'sale_price' => array('sale_price', 'special_price', 'discount_price', 'saleprice', 'discountprice', 'offer_price'),
            'stock_quantity' => array('stock', 'quantity', 'qty', 'stock_quantity', 'inventory', 'daudzums', 'stockqty', 'stock_qty', 'available', 'count', 'amount'),
            'weight' => array('weight', 'svars', 'mass', 'wt', 'productweight', 'product_weight'),
            'length' => array('length', 'garums', 'len', 'productlength'),
            'width' => array('width', 'platums', 'wid', 'productwidth'),
            'height' => array('height', 'augstums', 'hgt', 'productheight'),
            'categories' => array('category', 'categories', 'kategorija', 'cat', 'product_category', 'produkta_kategorija', 'categorypath', 'category_path'),
            'tags' => array('tags', 'tag', 'birkas', 'keywords', 'product_tags'),
            'images' => array('images', 'image', 'attels', 'picture', 'photo', 'img', 'product_image', 'gallery', 'image_url', 'imageurl', 'picture_url', 'pictureurl', 'photos'),
            'featured_image' => array('featured_image', 'main_image', 'primary_image', 'featuredimage', 'mainimage', 'primaryimage', 'thumbnail'),
            'brand' => array('brand', 'manufacturer', 'razotajs', 'make', 'producer', 'vendor'),
            'ean' => array('ean', 'ean13', 'ean_code', 'barcode', 'gtin13'),
            'upc' => array('upc', 'upc_code', 'gtin12'),
            'isbn' => array('isbn', 'isbn13', 'isbn10'),
            'mpn' => array('mpn', 'manufacturer_part_number', 'part_number', 'partnumber'),
            'gtin' => array('gtin', 'gtin14', 'global_trade_item_number'),
            'product_type' => array('product_type', 'type', 'producttype', 'item_type', 'itemtype'),
            'status' => array('status', 'product_status', 'availability', 'state', 'active'),
            'manage_stock' => array('manage_stock', 'managestock', 'track_stock', 'trackstock'),
            'stock_status' => array('stock_status', 'stockstatus', 'availability_status', 'in_stock', 'instock'),
            'backorders' => array('backorders', 'backorder', 'allow_backorder'),
            'tax_status' => array('tax_status', 'taxstatus', 'taxable'),
            'tax_class' => array('tax_class', 'taxclass', 'tax_rate', 'vat_class'),
            'featured' => array('featured', 'is_featured', 'highlight', 'recommended'),
            'virtual' => array('virtual', 'is_virtual', 'digital'),
            'downloadable' => array('downloadable', 'is_downloadable', 'download'),
            'sold_individually' => array('sold_individually', 'soldindividually', 'single_only'),
            'reviews_allowed' => array('reviews_allowed', 'reviewsallowed', 'enable_reviews', 'allow_reviews'),
            'purchase_note' => array('purchase_note', 'purchasenote', 'order_note'),
            'menu_order' => array('menu_order', 'menuorder', 'sort_order', 'position'),
            'external_url' => array('external_url', 'externalurl', 'affiliate_link', 'product_url'),
            'meta_title' => array('meta_title', 'metatitle', 'seo_title', 'page_title'),
            'meta_description' => array('meta_description', 'metadescription', 'seo_description'),
            'meta_keywords' => array('meta_keywords', 'metakeywords', 'seo_keywords', 'keywords', 'tags_seo', 'search_keywords'),
            'shipping_class' => array('shipping_class', 'shippingclass', 'delivery_class'),
            'upsell_ids' => array('upsell_ids', 'upsell', 'upsells', 'upsell_products', 'upsell_product_ids', 'related_upsell'),
            'cross_sell_ids' => array('cross_sell_ids', 'cross_sell', 'crosssell', 'cross_sells', 'crosssells', 'cross_sell_products'),
            'grouped_products' => array('grouped_products', 'grouped', 'group_products', 'product_group', 'grouped_product_ids'),
            'parent_id' => array('parent_id', 'parent', 'parent_product', 'parent_sku', 'parent_product_id'),
        );
        
        $suggestions = array();
        $matched_woo_fields = array(); // Track already matched WooCommerce fields
        
        // First pass: exact matches
        foreach ($source_fields as $source_field) {
            $source_lower = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $source_field));
            
            foreach ($field_aliases as $woo_field => $aliases) {
                if (isset($matched_woo_fields[$woo_field])) {
                    continue; // Skip already matched fields
                }
                
                foreach ($aliases as $alias) {
                    $alias_clean = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $alias));
                    
                    if ($source_lower === $alias_clean) {
                        $suggestions[$woo_field] = array(
                            'source_field' => $source_field,
                            'confidence' => 100,
                            'match_type' => 'exact'
                        );
                        $matched_woo_fields[$woo_field] = true;
                        break 2;
                    }
                }
            }
        }
        
        // Second pass: partial matches (contains)
        foreach ($source_fields as $source_field) {
            $source_lower = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $source_field));
            
            foreach ($field_aliases as $woo_field => $aliases) {
                if (isset($matched_woo_fields[$woo_field])) {
                    continue;
                }
                
                foreach ($aliases as $alias) {
                    $alias_clean = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $alias));
                    
                    // Check if source contains alias or alias contains source
                    if (strlen($alias_clean) >= 3 && (
                        strpos($source_lower, $alias_clean) !== false || 
                        strpos($alias_clean, $source_lower) !== false
                    )) {
                        // Calculate confidence based on match quality
                        $confidence = 70;
                        if (strpos($source_lower, $alias_clean) === 0 || strpos($alias_clean, $source_lower) === 0) {
                            $confidence = 85; // Starts with match is better
                        }
                        
                        if (!isset($suggestions[$woo_field]) || $suggestions[$woo_field]['confidence'] < $confidence) {
                            $suggestions[$woo_field] = array(
                                'source_field' => $source_field,
                                'confidence' => $confidence,
                                'match_type' => 'partial'
                            );
                            $matched_woo_fields[$woo_field] = true;
                        }
                        break;
                    }
                }
            }
        }
        
        // Sort suggestions by confidence
        uasort($suggestions, function($a, $b) {
            return $b['confidence'] - $a['confidence'];
        });
        
        $total_fields = count($field_aliases);
        $matched_fields = count($suggestions);
        
        wp_send_json_success(array(
            'suggestions' => $suggestions,
            'matched_count' => $matched_fields,
            'total_fields' => $total_fields,
            'message' => sprintf(
                // translators: %1$d is matched count, %2$d is total fields
                __('Auto-detected %1$d of %2$d fields', 'bootflow-product-xml-csv-importer'),
                $matched_fields,
                $total_fields
            )
        ));
    }
    
    /**
     * AJAX handler to get products count for an import
     */
    public function ajax_get_products_count() {
        
        // Verify nonce - use false to return false instead of die()
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'bfpi_nonce')) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('ajax_get_products_count - nonce failed'); }
            wp_send_json_error(array('message' => __('Security check failed', 'bootflow-product-xml-csv-importer')));
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('ajax_get_products_count - permission denied'); }
            wp_send_json_error(array('message' => __('Permission denied', 'bootflow-product-xml-csv-importer')));
            return;
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        $import_id = isset($_POST['import_id']) ? intval(wp_unslash($_POST['import_id'])) : 0;
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('ajax_get_products_count - import_id: ' . $import_id); }
        
        if (!$import_id) {
            wp_send_json_error(array('message' => __('Invalid import ID', 'bootflow-product-xml-csv-importer')));
            return;
        }
        
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}postmeta WHERE meta_key = '_bfpi_import_id' AND meta_value = %d",
            $import_id
        ));
        
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('ajax_get_products_count - count: ' . $count); }
        
        wp_send_json_success(array(
            'count' => intval($count),
            'import_id' => $import_id
        ));
    }
    
    /**
     * AJAX handler to delete products in batches with progress
     */
    public function ajax_delete_products_batch() {
        // Enable error handling to catch fatal errors
        try {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('ajax_delete_products_batch STARTED'); }
            
            // Verify nonce - use global nonce for consistency
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $import_id = isset($_POST['import_id']) ? intval(wp_unslash($_POST['import_id'])) : 0;
            
            if (!$import_id) {
                wp_send_json_error(array('message' => __('Invalid import ID', 'bootflow-product-xml-csv-importer')));
                return;
            }
            
            // Try global nonce first, then import-specific
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
            $valid_nonce = wp_verify_nonce($nonce, 'bfpi_nonce') || 
                           wp_verify_nonce($nonce, 'delete_products_' . $import_id);
            
            if (!$valid_nonce) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('ajax_delete_products_batch - nonce failed'); }
                wp_send_json_error(array('message' => __('Security check failed', 'bootflow-product-xml-csv-importer')));
                return;
            }
            
            if (!current_user_can('manage_woocommerce')) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('ajax_delete_products_batch - permission denied'); }
                wp_send_json_error(array('message' => __('Permission denied', 'bootflow-product-xml-csv-importer')));
                return;
            }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        $batch_size = isset($_POST['batch_size']) ? intval(wp_unslash($_POST['batch_size'])) : 10;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        $offset = isset($_POST['offset']) ? intval(wp_unslash($_POST['offset'])) : 0;
        
        // Increase time limit for deletion
        if (function_exists('set_time_limit')) {
            @set_time_limit(300); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged -- Required for long-running imports
        }
        
        global $wpdb;
        
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('DELETE_PRODUCTS_BATCH: Starting for import_id=' . $import_id . ', batch_size=' . $batch_size); }
        
        // Get batch of product IDs
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $product_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_bfpi_import_id' AND meta_value = %d LIMIT %d",
            $import_id,
            $batch_size
        ));
        
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('DELETE_PRODUCTS_BATCH: Found ' . count($product_ids) . ' products to delete'); }
        
        $deleted_count = 0;
        foreach ($product_ids as $product_id) {
            try {
                if (wp_delete_post($product_id, true)) {
                    $deleted_count++;
                }
            } catch (Exception $e) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('DELETE_PRODUCTS_BATCH: Error deleting product ' . $product_id . ': ' . $e->getMessage()); }
            }
        }
        
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('DELETE_PRODUCTS_BATCH: Deleted ' . $deleted_count . ' products'); }
        
        // Get remaining count
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $remaining = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}postmeta WHERE meta_key = '_bfpi_import_id' AND meta_value = %d",
            $import_id
        ));
        
        $completed = ($remaining == 0);
        
        // If completed, update import's processed_products count to 0
        if ($completed) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $wpdb->prefix . 'bfpi_imports',
                array('processed_products' => 0),
                array('id' => $import_id),
                array('%d'),
                array('%d')
            );
        }
        
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('ajax_delete_products_batch SUCCESS: deleted=' . $deleted_count . ', remaining=' . $remaining); }
        
        wp_send_json_success(array(
            'deleted' => $deleted_count,
            'remaining' => intval($remaining),
            'completed' => $completed,
            'message' => $completed 
                ? __('All products deleted successfully', 'bootflow-product-xml-csv-importer')
                // translators: placeholder values
                : sprintf(__('Deleted %1$d products, %2$d remaining...', 'bootflow-product-xml-csv-importer'), $deleted_count, $remaining)
        ));
        
        } catch (Exception $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('ajax_delete_products_batch EXCEPTION: ' . $e->getMessage()); }
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        } catch (Error $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('ajax_delete_products_batch ERROR: ' . $e->getMessage()); }
            wp_send_json_error(array('message' => 'Fatal Error: ' . $e->getMessage()));
        }
    }

    /**
     * Redirect to upgrade page (fallback for menu item).
     *
     * @since 1.0.0
     */
    public function redirect_to_pro_page() {
        wp_safe_redirect( 'https://bootflow.io/woocommerce-xml-csv-importer/' );
        exit;
    }

    /**
     * Enqueue inline JS to make upgrade menu link open in a new tab.
     *
     * @since 1.0.0
     */
    public function print_pro_menu_script() {
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'bfpi-' ) === false ) {
            return;
        }
        $js = "jQuery(function($){
            $('#adminmenu a[href*=\"bfpi-get-pro\"]')
                .attr('target', '_blank')
                .attr('href', 'https://bootflow.io/woocommerce-xml-csv-importer/');
        });";
        wp_add_inline_script( 'jquery', $js );
    }
}
