<?php
/**
 * Fired during plugin deactivation.
 *
 * @since      1.0.0
 * @package    Bfpi
 * @subpackage Bfpi/includes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fired during plugin deactivation.
 */
class Bfpi_Deactivator {

    /**
     * Plugin deactivation handler.
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('bfpi_cleanup');
        wp_clear_scheduled_hook('bfpi_process');

        // Flush rewrite rules
        flush_rewrite_rules();

        // Clean up temporary files (optional - only if user wants complete cleanup)
        $clean_on_deactivate = get_option('bfpi_clean_on_deactivate', false);
        if ($clean_on_deactivate) {
            self::cleanup_temp_files();
        }
    }

    /**
     * Clean up temporary files.
     *
     * @since    1.0.0
     */
    private static function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/bfpi-import/temp/';

        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    wp_delete_file($file);
                }
            }
        }
    }

    /**
     * Complete plugin removal (called from uninstall.php).
     *
     * @since    1.0.0
     */
    public static function uninstall() {
        global $wpdb;

        // Remove database tables
        $table_imports = $wpdb->prefix . 'bfpi_imports';
        $table_logs = $wpdb->prefix . 'bfpi_import_logs';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder -- Uninstall cleanup, %i requires WP 6.2+
        $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table_imports));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder -- Uninstall cleanup, %i requires WP 6.2+
        $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table_logs));

        // Remove plugin options
        delete_option('bfpi_settings');
        delete_option('bfpi_db_version');
        delete_option('bfpi_clean_on_deactivate');

        // Remove user meta
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup with specific prefix
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
                'bfpi_%'
            )
        );

        // Remove upload directory
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/bfpi-import/';
        if (is_dir($plugin_upload_dir)) {
            self::remove_directory($plugin_upload_dir);
        }

        // Clear any cached data
        wp_cache_flush();
    }

    /**
     * Recursively remove directory.
     *
     * @since    1.0.0
     * @param    string $dir Directory path
     */
    private static function remove_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                self::remove_directory($path);
            } else {
                wp_delete_file($path);
            }
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Required for recursive directory removal
        rmdir($dir);
    }
}